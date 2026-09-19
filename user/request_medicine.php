<?php
require '_init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: catalogue.php');
    exit;
}

$uid      = (int)$_SESSION['user_id'];
$medId    = (int)($_POST['medicine_id'] ?? 0);
$pharmId  = (int)($_POST['pharmacy_id'] ?? 0);
$qty      = max(1, (int)($_POST['quantity'] ?? 1));
$notes    = trim($_POST['notes'] ?? '');
$presc    = null;

/* ---------- Validate ---------- */
$med = null;
$st = $conn->prepare("SELECT id, medicine_name FROM medicines WHERE id = ?");
if ($st) {
    $st->bind_param("i", $medId);
    $st->execute();
    $res = $st->get_result();
    $med = ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}
if (!$med) { flash('error', 'Medicine not found.'); header('Location: catalogue.php'); exit; }

$st = $conn->prepare("SELECT id FROM pharmacies WHERE id = ? AND status = 'approved'");
if ($st) {
    $st->bind_param("i", $pharmId);
    $st->execute();
    $res = $st->get_result();
    if (!$res || $res->num_rows === 0) {
        flash('error', 'Please select a valid pharmacy.');
        header('Location: catalogue.php?id=' . $medId);
        exit;
    }
} else {
    flash('error', 'Please select a valid pharmacy.');
    header('Location: catalogue.php?id=' . $medId);
    exit;
}

/* ---------- Prescription upload ---------- */
if (!empty($_FILES['prescription']['name'])) {
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext     = strtolower(pathinfo($_FILES['prescription']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        flash('error', 'Prescription must be JPG, PNG, or PDF.');
        header('Location: catalogue.php?id=' . $medId);
        exit;
    }
    if ($_FILES['prescription']['size'] > 5 * 1024 * 1024) {
        flash('error', 'Prescription file must be under 5 MB.');
        header('Location: catalogue.php?id=' . $medId);
        exit;
    }

    $dir = __DIR__ . '/uploads/prescriptions';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $fname = uniqid('rx_', true) . '.' . $ext;
    if (move_uploaded_file($_FILES['prescription']['tmp_name'], "$dir/$fname")) {
        $presc = "uploads/prescriptions/$fname";
    }
}

/* ---------- Insert ---------- */
$st = $conn->prepare(
    "INSERT INTO medicine_requests
        (user_id, pharmacy_id, medicine_id, quantity, prescription_image, notes, status)
     VALUES (?, ?, ?, ?, ?, ?, 'pending')"
);
if (!$st) { flash('error', 'Something went wrong. Please try again.'); header('Location: catalogue.php?id=' . $medId); exit; }
$st->bind_param("iiiiss", $uid, $pharmId, $medId, $qty, $presc, $notes);
if (!$st->execute()) {
    flash('error', 'Something went wrong. Please try again.');
    header('Location: catalogue.php?id=' . $medId);
    exit;
}

$reqId = (int)$conn->insert_id;

$st2 = $conn->prepare(
    "INSERT INTO user_notifications (user_id, title, message, type, related_id)
     VALUES (?, ?, ?, 'medicine_request', ?)"
);
if ($st2) {
    $msg = "Your request for {$med['medicine_name']} (Qty: $qty) has been sent to the pharmacy.";
    $nt = 'Medicine request submitted';
    $st2->bind_param("issi", $uid, $nt, $msg, $reqId);
    $st2->execute();
}

$st3 = $conn->prepare(
    "INSERT INTO pharmacy_notifications (pharmacy_id, title, message, type, related_id)
     VALUES (?, ?, ?, 'medicine_request', ?)"
);
if ($st3) {
    $phMsg = "New medicine request from {$_SESSION['user_name']} for {$med['medicine_name']} (Qty: $qty).";
    $nt = 'New medicine request';
    $st3->bind_param("issi", $pharmId, $nt, $phMsg, $reqId);
    $st3->execute();
}

flash('success', 'Medicine request submitted successfully!');
header('Location: appointments.php?tab=requests');
exit;