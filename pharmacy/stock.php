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

/* Delete */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $id = (int)($_POST["id"] ?? 0);

    $stmt = $conn->prepare("DELETE FROM pharmacy_medicines WHERE id = ? AND pharmacy_id = ?");
    $stmt->bind_param("ii", $id, $pharmacy_id);
    $stmt->execute();

    $_SESSION["flash_success"] = "Stock item removed.";
    header("Location: stock.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT pm.*, m.medicine_name, m.generic_name, m.category
     FROM pharmacy_medicines pm
     JOIN medicines m ON m.id = pm.medicine_id
     WHERE pm.pharmacy_id = ?
     ORDER BY m.medicine_name"
);
$stmt->bind_param("i", $pharmacy_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Stock | MediConnect</title>

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
.subtitle{color:#8b95a5;font-size:14px;margin-top:6px;}

/* ALERTS */
.alert{padding:13px 15px;border-radius:10px;margin-bottom:20px;font-size:13px;}
.alert.success{background:rgba(0,200,120,0.12);border:1px solid rgba(0,200,120,0.3);color:#66e6aa;}
.alert.error{background:rgba(255,70,70,0.12);border:1px solid rgba(255,70,70,0.3);color:#ff7777;}

/* CARD / TABLE */
.card{background:#171c26;border-radius:15px;border:1px solid #262d3d;overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th,td{padding:15px;border-bottom:1px solid #262d3d;text-align:left;white-space:nowrap;}
th{color:#8b95a5;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;}
td{font-size:14px;}
tr:last-child td{border-bottom:none;}
td .muted{color:#8b95a5;}
td .name{font-weight:600;}

/* BADGES */
.badge{display:inline-block;padding:5px 11px;border-radius:20px;font-size:11px;font-weight:600;}
.badge.success{background:rgba(0,200,120,0.15);color:#66e6aa;border:1px solid rgba(0,200,120,0.35);}
.badge.warning{background:rgba(240,180,41,0.15);color:#f0c15a;border:1px solid rgba(240,180,41,0.35);}
.badge.danger{background:rgba(255,70,70,0.15);color:#ff8a8a;border:1px solid rgba(255,70,70,0.35);}
.badge.primary{background:rgba(29,140,248,0.15);color:#6db9ff;border:1px solid rgba(29,140,248,0.35);}

/* BTNS */
.btn{display:inline-block;padding:6px 12px;background:#212836;color:#c5ccd8;border:1px solid #30394a;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:0.2s;}
.btn:hover{color:#1d8cf8;border-color:#1d8cf8;}
.btn.primary{background:#1d8cf8;border-color:#1d8cf8;color:white;}
.btn.primary:hover{background:#1572cd;}
.btn.danger{background:transparent;border:1px solid #30394a;color:#c5ccd8;}
.btn.danger:hover{border-color:rgba(255,70,70,0.5);color:#ff8a8a;}
.inline-form{display:inline-block;}

.empty-msg{text-align:center;color:#8b95a5;padding:50px 20px;font-size:14px;}
.empty-msg .btn{margin-top:16px;}

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
<a href="requests.php" data-icon="📥">Requests</a>
<a href="stock.php" class="active" data-icon="📦">My Stock</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- MAIN CONTENT -->
<div class="main-content">

<div class="page-header">
<div>
<h1>My Stock</h1>
<p class="subtitle"><?php echo count($rows); ?> item<?php echo count($rows) === 1 ? "" : "s"; ?></p>
</div>
<div style="display:flex;gap:10px;align-items:center;">
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
<a class="btn primary" href="stock_edit.php">+ Add Medicine</a>
</div>
</div>

<?php if ($flashSuccess !== ""): ?>
<div class="alert success"><?php echo htmlspecialchars($flashSuccess); ?></div>
<?php endif; ?>
<?php if ($flashError !== ""): ?>
<div class="alert error"><?php echo htmlspecialchars($flashError); ?></div>
<?php endif; ?>

<?php if (!$rows): ?>
<div class="card empty-msg">
No stock added yet. Add medicines to your stock so patients can request them.
<br>
<a class="btn primary" href="medicines.php">Browse Catalogue</a>
</div>
<?php else: ?>

<div class="card">
<table>
<thead>
<tr>
<th>Medicine</th>
<th>Category</th>
<th>Price</th>
<th>Stock</th>
<th>Updated</th>
<th></th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td>
<div class="name"><?php echo htmlspecialchars($r["medicine_name"]); ?></div>
<div class="muted"><?php echo htmlspecialchars($r["generic_name"] ?? ""); ?></div>
</td>
<td>
<?php if (!empty($r["category"])): ?>
<span class="badge primary"><?php echo htmlspecialchars($r["category"]); ?></span>
<?php else: ?>
<span class="muted">—</span>
<?php endif; ?>
</td>
<td>
<?php if ($r["price"] !== null): ?>
Rs <?php echo number_format((float)$r["price"], 2); ?>
<?php else: ?>
<span class="muted">—</span>
<?php endif; ?>
</td>
<td>
<?php if ((int)$r["stock"] > 5): ?>
<span class="badge success"><?php echo (int)$r["stock"]; ?> in stock</span>
<?php elseif ((int)$r["stock"] > 0): ?>
<span class="badge warning"><?php echo (int)$r["stock"]; ?> low</span>
<?php else: ?>
<span class="badge danger">Out of stock</span>
<?php endif; ?>
</td>
<td><span class="muted"><?php echo htmlspecialchars(date("d M Y", strtotime($r["updated_at"] ?? $r["created_at"]))); ?></span></td>
<td>
<a class="btn" href="stock_edit.php?id=<?php echo (int)$r["id"]; ?>">Edit</a>
<form class="inline-form" method="POST" onsubmit="return confirm('Remove this from stock?');">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
<button class="btn danger" type="submit">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

</div>
<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>
</html>