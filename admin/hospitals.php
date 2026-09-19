<?php
session_start();
require_once "../config/database.php";

if (empty($_SESSION["user"]) || ($_SESSION["user"]["role"] ?? "") !== "admin") {
    header("Location: login.php");
    exit;
}

require_once "../partials/_right.php";
$rightCfg = [
    "ntable"             => "admin_notifications",
    "recipient_col"      => "admin_id",
    "recipient_id"       => (int)($_SESSION["user"]["id"] ?? 1),
    "profile_table"      => "admins",
    "profile_id_field"   => "id",
    "profile_label_field"=> "name",
    "profile_email_field"=> "email",
    "session_label_key"  => "name",
    "profile_title"      => "Admin Profile",
    "profile_fields"     => [
        ["name" => "name",  "label" => "Name"],
    ],
];
right_handle($conn, $rightCfg);
$rightUnread = right_unread($conn, $rightCfg);

$message = "";

if (isset($_GET["approve"])) {
    $id = (int)$_GET["approve"];

    $stmt = $conn->prepare(
        "UPDATE hospitals SET status='approved' WHERE id=?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $n = $conn->prepare(
        "INSERT INTO hospital_notifications (hospital_id, title, message, type, related_id)
         VALUES (?, 'Registration approved', 'Your hospital registration has been approved. You can now log in, manage doctors and view appointment requests.', 'approval', ?)"
    );
    if ($n) {
        $n->bind_param("ii", $id, $id);
        $n->execute();
    }

    $message = "Hospital approved successfully.";
}

if (isset($_GET["reject"])) {
    $id = (int)$_GET["reject"];

    $stmt = $conn->prepare(
        "UPDATE hospitals SET status='rejected' WHERE id=?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $n = $conn->prepare(
        "INSERT INTO hospital_notifications (hospital_id, title, message, type, related_id)
         VALUES (?, 'Registration rejected', 'Your hospital registration was not approved. If this is a mistake, please contact support.', 'approval', ?)"
    );
    if ($n) {
        $n->bind_param("ii", $id, $id);
        $n->execute();
    }

    $message = "Hospital rejected.";
}

$hospitals = $conn->query(
    "SELECT * FROM hospitals ORDER BY id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hospitals | MediConnect Admin</title>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif}
body{background:#0f1319;color:white}
.sidebar{position:fixed;width:260px;height:100vh;background:#121720;border-right:1px solid #262d3d;padding:28px 18px;display:flex;flex-direction:column}
.logo{font-size:20px;font-weight:700;margin-bottom:45px}.logo span{color:#1d8cf8}
.menu{display:flex;flex-direction:column;gap:7px}.menu a{padding:13px 15px;border-radius:10px;color:#8b95a5;text-decoration:none;font-size:14px}.menu a:hover,.menu a.active{background:rgba(29,140,248,.12);color:#1d8cf8}
.logout{margin-top:auto}.logout a{display:block;padding:13px;text-align:center;background:rgba(239,68,68,.08);color:#ff6b6b;border-radius:10px;text-decoration:none}
.main{margin-left:260px;padding:35px}.header{margin-bottom:30px;display:flex;justify-content:space-between;align-items:center;gap:15px}.header h1{font-size:28px}.header p{color:#8b95a5;margin-top:6px;font-size:14px}
.card{background:#171c26;border:1px solid #262d3d;border-radius:18px;padding:25px}
.alert{background:rgba(34,197,94,.12);color:#4ade80;padding:13px;border-radius:10px;margin-bottom:20px;font-size:13px}
table{width:100%;border-collapse:collapse}th{padding:15px;text-align:left;color:#8b95a5;font-size:12px;border-bottom:1px solid #262d3d}td{padding:17px 15px;border-bottom:1px solid #262d3d;font-size:14px}
.badge{padding:6px 10px;border-radius:20px;font-size:11px}.pending{background:rgba(245,158,11,.12);color:#fbbf24}.approved{background:rgba(34,197,94,.12);color:#4ade80}.rejected{background:rgba(239,68,68,.12);color:#f87171}
.action{padding:8px 12px;border-radius:8px;text-decoration:none;font-size:12px;margin-right:5px}.approve{background:rgba(34,197,94,.12);color:#4ade80}.reject{background:rgba(239,68,68,.12);color:#f87171}
@media(max-width:750px){.sidebar{width:70px;padding:20px 8px}.logo{font-size:0}.logo:after{content:'+';font-size:25px;color:#1d8cf8}.menu a{font-size:0}.main{margin-left:70px;padding:20px}.table-wrap{overflow-x:auto}}

.bname{font-size:17px;white-space:nowrap;}@media(max-width:768px){.bname{font-size:0 !important;}}
</style>
<?php require_once "../partials/_theme.php"; ?>
</head>

<body>

<div class="sidebar">
<div class="logo" style="display:flex;align-items:center;gap:10px;"><img src="../assets/logo.png" alt="MediConnect" style="height:48px;width:auto;max-width:100%;"><span class="bname" style="color:#f5f7fa;">MediConnect<span style="color:#1d8cf8;">.</span></span></div>

<div class="menu">
<a href="dashboard.php">📊 Dashboard</a>
<a href="users.php">👥 Users</a>
<a href="hospitals.php" class="active">🏥 Hospitals</a>
<a href="pharmacies.php">💊 Pharmacies</a>
<a href="medicines.php">💉 Medicines</a>
<a href="reports.php">📈 Reports</a>
</div>

<div class="logout"><a href="../logout.php">🚪 Logout</a></div>
</div>

<div class="main">

<div class="header">
<div>
<h1>Hospitals</h1>
<p>Manage and approve registered hospitals.</p>
</div>
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
</div>

<div class="card">

<?php if ($message): ?>
<div class="alert"><?php echo $message; ?></div>
<?php endif; ?>

<div class="table-wrap">

<table>

<thead>
<tr>
<th>ID</th>
<th>HOSPITAL</th>
<th>EMAIL</th>
<th>PHONE</th>
<th>STATUS</th>
<th>ACTION</th>
</tr>
</thead>

<tbody>

<?php if ($hospitals && $hospitals->num_rows > 0): ?>

<?php while ($hospital = $hospitals->fetch_assoc()): ?>

<tr>

<td>#<?php echo $hospital["id"]; ?></td>

<td>
<?php
echo htmlspecialchars(
$hospital["hospital_name"] ?? "Hospital"
);
?>
</td>

<td>
<?php
echo htmlspecialchars(
$hospital["email"] ?? "-"
);
?>
</td>

<td>
<?php
echo htmlspecialchars(
$hospital["phone"] ?? "-"
);
?>
</td>

<td>

<span class="badge <?php echo strtolower($hospital["status"] ?? "pending"); ?>">

<?php
echo ucfirst(
$hospital["status"] ?? "pending"
);
?>

</span>

</td>

<td>

<?php if (($hospital["status"] ?? "") === "pending"): ?>

<a
class="action approve"
href="?approve=<?php echo $hospital["id"]; ?>"
onclick="return confirm('Approve this hospital?')"
>
Approve
</a>

<a
class="action reject"
href="?reject=<?php echo $hospital["id"]; ?>"
onclick="return confirm('Reject this hospital?')"
>
Reject
</a>

<?php else: ?>

-

<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>
<td colspan="6" style="text-align:center;color:#8b95a5;padding:30px;">
No hospitals found.
</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>
<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>
</html>