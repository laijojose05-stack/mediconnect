<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["pharmacy_id"])) {
    header("Location: login.php");
    exit();
}

$pharmacy_id = (int)$_SESSION["pharmacy_id"];

require_once "../partials/_right.php";
$rightCfg = [
    "ntable"             => "pharmacy_notifications",
    "recipient_col"      => "pharmacy_id",
    "recipient_id"       => (int)$pharmacy_id,
    "profile_table"      => "pharmacies",
    "profile_id_field"   => "id",
    "profile_label_field"=> "pharmacy_name",
    "profile_email_field"=> "email",
    "session_label_key"  => "pharmacy_name",
    "profile_title"      => "Pharmacy Profile",
    "profile_fields"     => [
        ["name" => "pharmacy_name", "label" => "Pharmacy Name"],
        ["name" => "phone",         "label" => "Phone"],
        ["name" => "address",       "label" => "Address", "type" => "textarea"],
        ["name" => "city",          "label" => "City"],
        ["name" => "license_number", "label" => "License Number"],
    ],
];
right_handle($conn, $rightCfg);
$rightUnread = right_unread($conn, $rightCfg);

$flashSuccess = $_SESSION["flash_success"] ?? "";
$flashError   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

/* ---------- Handle status changes ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $id     = (int)($_POST["id"] ?? 0);

    $validActions = [
        "accept"   => "accepted",
        "reject"   => "rejected",
        "ready"    => "ready",
        "complete" => "completed",
    ];

    if (isset($validActions[$action])) {
        $stmt = $conn->prepare(
            "SELECT mr.*, m.medicine_name
             FROM medicine_requests mr
             JOIN medicines m ON m.id = mr.medicine_id
             WHERE mr.id = ? AND mr.pharmacy_id = ?"
        );
        $stmt->bind_param("ii", $id, $pharmacy_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();

        if ($r) {
            $newStatus = $validActions[$action];

            $stmt = $conn->prepare("UPDATE medicine_requests SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $id);
            $stmt->execute();

            /* Notify user */
            $titles = [
                "accepted"  => "Request accepted",
                "rejected"  => "Request rejected",
                "ready"     => "Medicine ready for pickup",
                "completed" => "Request completed",
            ];
            $msgs = [
                "accepted"  => "Your request for {$r["medicine_name"]} has been accepted. The pharmacy will prepare it shortly.",
                "rejected"  => "Unfortunately, your request for {$r["medicine_name"]} was rejected.",
                "ready"     => "Your medicine {$r["medicine_name"]} is ready for pickup at the pharmacy.",
                "completed" => "Your request for {$r["medicine_name"]} has been completed.",
            ];

            $notify = $conn->prepare(
                "INSERT INTO user_notifications (user_id, title, message, type, related_id)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $uid     = (int)$r["user_id"];
            $type    = "medicine_request";
            $title   = $titles[$newStatus];
            $message = $msgs[$newStatus];
            $notify->bind_param("isssi", $uid, $title, $message, $type, $id);
            $notify->execute();

            $_SESSION["flash_success"] = "Request marked as {$newStatus}.";
        } else {
            $_SESSION["flash_error"] = "Request not found.";
        }
    }

    header("Location: requests.php?tab=" . urlencode($_GET["tab"] ?? "all"));
    exit;
}

/* ---------- Filters ---------- */
$tab = $_GET["tab"] ?? "all";

$where  = "WHERE mr.pharmacy_id = ?";
$params = [$pharmacy_id];
$types  = "i";

switch ($tab) {
    case "pending":  $where .= " AND mr.status = 'pending'";  break;
    case "accepted": $where .= " AND mr.status IN ('accepted','ready')"; break;
    case "closed":   $where .= " AND mr.status IN ('completed','rejected','cancelled')"; break;
}
$where .= " ORDER BY mr.created_at DESC";

$stmt = $conn->prepare(
    "SELECT mr.*, m.medicine_name, m.generic_name, u.name AS user_name,
            u.phone AS user_phone, u.address AS user_address
     FROM medicine_requests mr
     JOIN medicines m ON m.id = mr.medicine_id
     JOIN users u ON u.id = mr.user_id
     $where"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Stats */
$statAll = $statPending = $statAccepted = $statClosed = 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM medicine_requests WHERE pharmacy_id=$pharmacy_id");
if ($result) $statAll = (int)$result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM medicine_requests WHERE pharmacy_id=$pharmacy_id AND status='pending'");
if ($result) $statPending = (int)$result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM medicine_requests WHERE pharmacy_id=$pharmacy_id AND status IN ('accepted','ready')");
if ($result) $statAccepted = (int)$result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM medicine_requests WHERE pharmacy_id=$pharmacy_id AND status IN ('completed','rejected','cancelled')");
if ($result) $statClosed = (int)$result->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medicine Requests | MediConnect</title>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#0f1319;color:white;min-height:100vh;display:flex;}

/* SIDEBAR */
.sidebar{width:250px;background:#171c26;padding:30px 20px;border-right:1px solid #262d3d;min-height:100vh;flex-shrink:0;}
.logo{font-size:22px;font-weight:bold;margin-bottom:45px;color:white;}
.logo span{color:#1d8cf8}
.sidebar a{display:block;padding:13px 15px;margin-bottom:5px;color:#8b95a5;text-decoration:none;border-radius:10px;font-size:14px;transition:0.3s;}
.sidebar a:hover,.sidebar a.active{background:#212836;color:#1d8cf8;}

/* MAIN CONTENT */
.main-content{flex:1;padding:40px 45px;background:#0f1319;}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;}
.page-header h1{font-size:32px;}

/* ALERTS */
.alert{padding:13px 15px;border-radius:10px;margin-bottom:20px;font-size:13px;}
.alert.success{background:rgba(0,200,120,0.12);border:1px solid rgba(0,200,120,0.3);color:#66e6aa;}
.alert.error{background:rgba(255,70,70,0.12);border:1px solid rgba(255,70,70,0.3);color:#ff7777;}

/* TABS */
.tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:25px;}
.tabs a{display:inline-block;padding:9px 18px;background:#171c26;border:1px solid #262d3d;border-radius:20px;color:#8b95a5;text-decoration:none;font-size:13px;}
.tabs a.active{background:#1d8cf8;border-color:#1d8cf8;color:white;}
.tabs a span{opacity:0.8;font-size:11px;margin-left:4px;}

/* CARDS */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;}
.card{background:#171c26;padding:20px;border-radius:15px;border:1px solid #262d3d;display:flex;flex-direction:column;}
.card h6{font-size:15px;margin-bottom:2px;}
.card .gen{color:#8b95a5;font-size:12px;margin-bottom:8px;}
.card hr{border:none;border-top:1px solid #262d3d;margin:12px 0;}
.info-line{font-size:13px;margin-bottom:6px;color:#c5ccd8;}
.info-line .muted{color:#8b95a5;}
.info-line a{color:#6db9ff;text-decoration:none;}
.info-line a:hover{text-decoration:underline;}

/* ACTIONS */
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;padding-top:12px;}

/* BTNS */
.btn{display:inline-block;padding:7px 13px;background:#212836;color:#c5ccd8;border:1px solid #30394a;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:0.2s;}
.btn:hover{color:#1d8cf8;border-color:#1d8cf8;}
.btn.success{background:rgba(0,200,120,0.15);border-color:rgba(0,200,120,0.35);color:#66e6aa;}
.btn.success:hover{background:rgba(0,200,120,0.25);color:#66e6aa;}
.btn.primary{background:rgba(29,140,248,0.15);border-color:rgba(29,140,248,0.35);color:#6db9ff;}
.btn.primary:hover{background:rgba(29,140,248,0.25);color:#6db9ff;}
.btn.danger{background:rgba(255,70,70,0.15);border-color:rgba(255,70,70,0.35);color:#ff8a8a;}
.btn.danger:hover{background:rgba(255,70,70,0.25);color:#ff8a8a;}
.btn.outline-danger{background:transparent;border:1px solid #30394a;color:#c5ccd8;}
.btn.outline-danger:hover{border-color:rgba(255,70,70,0.5);color:#ff8a8a;}

/* BADGES */
.badge{display:inline-block;padding:5px 11px;border-radius:20px;font-size:11px;font-weight:600;margin-left:auto;}
.badge.warning{background:rgba(240,180,41,0.15);color:#f0c15a;border:1px solid rgba(240,180,41,0.35);}
.badge.primary{background:rgba(29,140,248,0.15);color:#6db9ff;border:1px solid rgba(29,140,248,0.35);}
.badge.success{background:rgba(0,200,120,0.15);color:#66e6aa;border:1px solid rgba(0,200,120,0.35);}
.badge.danger{background:rgba(255,70,70,0.15);color:#ff8a8a;border:1px solid rgba(255,70,70,0.35);}
.badge.secondary{background:rgba(139,149,165,0.15);color:#9aa4b5;border:1px solid rgba(139,149,165,0.35);}

.empty-msg{text-align:center;color:#8b95a5;padding:50px 20px;font-size:14px;background:#171c26;border:1px solid #262d3d;border-radius:15px;}
.time{color:#8b95a5;font-size:12px;margin-top:10px;}

/* RESPONSIVE */
@media(max-width:768px){
    .sidebar{width:70px;padding:25px 12px;}
    .sidebar a{font-size:0;padding:14px 0;text-align:center;}
    .sidebar a::before{content:attr(data-icon);font-size:20px;display:block;}
    .logo{font-size:0;}
    .logo span{font-size:22px;}
    .main-content{padding:25px 20px;}
    .page-header h1{font-size:26px;}
}
@media(max-width:480px){
    .sidebar{width:60px;padding:20px 8px;}
    .main-content{padding:20px 15px;}
}

.bname{font-size:17px;white-space:nowrap;}@media(max-width:768px){.bname{font-size:0 !important;}}
</style>
<?php require_once "../partials/_theme.php"; ?>

</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

<div class="logo" style="display:flex;align-items:center;gap:10px;"><img src="../assets/logo.png" alt="MediConnect" style="height:44px;width:auto;max-width:100%;"><span class="bname" style="color:#f5f7fa;">MediConnect<span style="color:#1d8cf8;">.</span></span></div>

<a href="dashboard.php" data-icon="📊">Dashboard</a>
<a href="medicines.php" data-icon="💊">Medicine Catalogue</a>
<a href="requests.php" class="active" data-icon="📥">Requests</a>
<a href="stock.php" data-icon="📦">My Stock</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- MAIN CONTENT -->
<div class="main-content">

<div class="page-header">
<div>
<h1>Medicine Requests</h1>
</div>
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
</div>

<?php if ($flashSuccess !== ""): ?>
<div class="alert success"><?php echo htmlspecialchars($flashSuccess); ?></div>
<?php endif; ?>
<?php if ($flashError !== ""): ?>
<div class="alert error"><?php echo htmlspecialchars($flashError); ?></div>
<?php endif; ?>

<!-- TABS -->
<div class="tabs">
<a href="?tab=all"      class="<?php echo $tab === "all" ? "active" : ""; ?>">All <span><?php echo $statAll; ?></span></a>
<a href="?tab=pending"  class="<?php echo $tab === "pending" ? "active" : ""; ?>">Pending <span><?php echo $statPending; ?></span></a>
<a href="?tab=accepted" class="<?php echo $tab === "accepted" ? "active" : ""; ?>">Accepted <span><?php echo $statAccepted; ?></span></a>
<a href="?tab=closed"   class="<?php echo $tab === "closed" ? "active" : ""; ?>">Closed <span><?php echo $statClosed; ?></span></a>
</div>

<?php if (!$rows): ?>
<div class="empty-msg">No requests in this view. Requests from patients will appear here.</div>
<?php else: ?>

<div class="grid">
<?php foreach ($rows as $r):
    $c = "secondary";
    switch (strtolower($r["status"])) {
        case "pending":   $c = "warning";  break;
        case "accepted":  $c = "primary";  break;
        case "ready":     $c = "success";  break;
        case "completed": $c = "success";  break;
        case "rejected":  $c = "danger";   break;
    }
?>
<div class="card">

<div style="display:flex;align-items:flex-start;">
<div>
<h6><?php echo htmlspecialchars($r["medicine_name"]); ?></h6>
<div class="gen"><?php echo htmlspecialchars($r["generic_name"] ?? "—"); ?></div>
</div>
<span class="badge <?php echo $c; ?>"><?php echo htmlspecialchars(ucfirst($r["status"])); ?></span>
</div>

<hr>

<div class="info-line"><span class="muted">Patient: </span><?php echo htmlspecialchars($r["user_name"]); ?></div>
<?php if (!empty($r["user_phone"])): ?>
<div class="info-line"><span class="muted">Phone: </span><?php echo htmlspecialchars($r["user_phone"]); ?></div>
<?php endif; ?>
<?php if (!empty($r["user_address"])): ?>
<div class="info-line"><span class="muted">Address: </span><?php echo htmlspecialchars($r["user_address"]); ?></div>
<?php endif; ?>
<div class="info-line"><span class="muted">Quantity: </span><strong><?php echo (int)$r["quantity"]; ?></strong></div>

<?php if (!empty($r["notes"])): ?>
<div class="info-line"><span class="muted">Notes: </span><?php echo htmlspecialchars($r["notes"]); ?></div>
<?php endif; ?>

<?php if (!empty($r["prescription_image"])): ?>
<div class="info-line">
<span class="muted">📄 </span>
<a href="../user/<?php echo htmlspecialchars($r["prescription_image"]); ?>" target="_blank">View prescription</a>
</div>
<?php endif; ?>

<!-- ACTIONS -->
<div class="actions">
<?php if ($r["status"] === "pending"): ?>
<form method="POST">
<input type="hidden" name="action" value="accept">
<input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
<button class="btn success" type="submit">Accept</button>
</form>
<form method="POST" onsubmit="return confirm('Reject this request?');">
<input type="hidden" name="action" value="reject">
<input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
<button class="btn danger" type="submit">Reject</button>
</form>
<?php elseif ($r["status"] === "accepted"): ?>
<form method="POST">
<input type="hidden" name="action" value="ready">
<input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
<button class="btn primary" type="submit">Mark Ready</button>
</form>
<form method="POST" onsubmit="return confirm('Reject this request?');">
<input type="hidden" name="action" value="reject">
<input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
<button class="btn outline-danger" type="submit">Reject</button>
</form>
<?php elseif ($r["status"] === "ready"): ?>
<form method="POST">
<input type="hidden" name="action" value="complete">
<input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
<button class="btn success" type="submit">Mark Completed</button>
</form>
<?php endif; ?>
</div>

<div class="time">Requested <?php echo htmlspecialchars(date("d M Y, h:i A", strtotime($r["created_at"]))); ?></div>

</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

</div>
<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>
</html>