<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["pharmacy_id"])) {
    header("Location: login.php");
    exit();
}

$pharmacy_id = (int)$_SESSION["pharmacy_id"];
$pharmacy_name = $_SESSION["pharmacy_name"] ?? "Pharmacy";

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

/* Stats */
$pending = 0;
$accepted = 0;
$stockCnt = 0;
$lowCnt = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM medicine_requests
     WHERE pharmacy_id = $pharmacy_id AND status = 'pending'"
);
if ($result) $pending = (int)$result->fetch_assoc()["total"];

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM medicine_requests
     WHERE pharmacy_id = $pharmacy_id AND status IN ('accepted','ready')"
);
if ($result) $accepted = (int)$result->fetch_assoc()["total"];

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM pharmacy_medicines WHERE pharmacy_id = $pharmacy_id"
);
if ($result) $stockCnt = (int)$result->fetch_assoc()["total"];

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM pharmacy_medicines
     WHERE pharmacy_id = $pharmacy_id AND stock <= 5"
);
if ($result) $lowCnt = (int)$result->fetch_assoc()["total"];

/* Recent requests */
$recent = [];
$result = $conn->query(
    "SELECT mr.*, m.medicine_name, u.name AS user_name
     FROM medicine_requests mr
     JOIN medicines m ON m.id = mr.medicine_id
     JOIN users u     ON u.id = mr.user_id
     WHERE mr.pharmacy_id = $pharmacy_id
     ORDER BY mr.created_at DESC LIMIT 6"
);
if ($result) $recent = $result->fetch_all(MYSQLI_ASSOC);

/* Low stock list */
$lowList = [];
$result = $conn->query(
    "SELECT pm.*, m.medicine_name
     FROM pharmacy_medicines pm
     JOIN medicines m ON m.id = pm.medicine_id
     WHERE pm.pharmacy_id = $pharmacy_id AND pm.stock <= 5
     ORDER BY pm.stock ASC LIMIT 6"
);
if ($result) $lowList = $result->fetch_all(MYSQLI_ASSOC);

$flash = $_GET["msg"] ?? "";
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pharmacy Dashboard | MediConnect</title>

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

/* CARDS */
.card{background:#171c26;padding:25px;border-radius:15px;margin-bottom:25px;border:1px solid #262d3d;}
.card h5{font-size:15px;margin-bottom:15px;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;}
.stat{background:#171c26;border:1px solid #262d3d;padding:22px 24px;border-radius:16px;}
.stat p{color:#8b95a5;font-size:13px;margin-bottom:8px;}
.stat h2{font-size:30px;color:#1d8cf8;}
.stat.warn h2{color:#f0c15a;}
.stat.bad h2{color:#ff8a8a;}

/* TWO-COLUMN LAYOUT */
.side-grid{display:grid;grid-template-columns:7fr 5fr;gap:25px;align-items:start;}
@media(max-width:900px){.side-grid{grid-template-columns:1fr;}}

/* ROWS */
.row-item{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #262d3d;padding:13px 0;gap:10px;}
.row-item:last-child{border-bottom:none;}
.row-item .muted{color:#8b95a5;}
.row-item .small{font-size:12px;margin-top:3px;}
.row-item .end{text-align:right;}

/* BADGES */
.badge{display:inline-block;padding:5px 11px;border-radius:20px;font-size:11px;font-weight:600;}
.badge.warning{background:rgba(240,180,41,0.15);color:#f0c15a;border:1px solid rgba(240,180,41,0.35);}
.badge.primary{background:rgba(29,140,248,0.15);color:#6db9ff;border:1px solid rgba(29,140,248,0.35);}
.badge.success{background:rgba(0,200,120,0.15);color:#66e6aa;border:1px solid rgba(0,200,120,0.35);}
.badge.danger{background:rgba(255,70,70,0.15);color:#ff8a8a;border:1px solid rgba(255,70,70,0.35);}
.badge.secondary{background:rgba(139,149,165,0.15);color:#9aa4b5;border:1px solid rgba(139,149,165,0.35);}

/* BUTTONS */
.btn{display:inline-block;padding:6px 12px;background:#212836;color:#c5ccd8;border:1px solid #30394a;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:0.2s;}
.btn:hover{color:#1d8cf8;border-color:#1d8cf8;}

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

<a href="dashboard.php" class="active" data-icon="📊">Dashboard</a>
<a href="medicines.php" data-icon="💊">Medicine Catalogue</a>
<a href="requests.php" data-icon="📥">Requests</a>
<a href="stock.php" data-icon="📦">My Stock</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- MAIN CONTENT -->
<div class="main-content">

<div class="page-header">
<div>
<h1>Welcome, <?php echo htmlspecialchars($pharmacy_name); ?></h1>
<p class="subtitle">Pharmacy Management Dashboard</p>
</div>
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
</div>

<?php if ($flash === "updated"): ?>
<div class="card" style="border-color:rgba(0,200,120,0.35);color:#66e6aa;">Profile updated successfully.</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats">

<div class="stat">
<p>Pending Requests</p>
<h2><?php echo $pending; ?></h2>
</div>

<div class="stat">
<p>Accepted / Ready</p>
<h2><?php echo $accepted; ?></h2>
</div>

<div class="stat">
<p>Stock Items</p>
<h2><?php echo $stockCnt; ?></h2>
</div>

<div class="stat bad">
<p>Low Stock</p>
<h2><?php echo $lowCnt; ?></h2>
</div>

</div>

<div class="side-grid">

<!-- RECENT REQUESTS -->
<div class="card">
<h5>Recent Requests</h5>

<?php if (!$recent): ?>
<p style="color:#8b95a5;">No requests yet.</p>
<?php else: ?>
<?php foreach ($recent as $r):
    $c = "secondary";
    switch (strtolower($r["status"])) {
        case "pending":  $c = "warning";  break;
        case "accepted": $c = "primary";  break;
        case "ready":    $c = "success";  break;
        case "completed":$c = "success";  break;
        case "rejected": $c = "danger";   break;
    }
?>
<div class="row-item">
<div>
<div><strong><?php echo htmlspecialchars($r["medicine_name"]); ?></strong></div>
<div class="small muted"><?php echo htmlspecialchars($r["user_name"]); ?> · Qty: <?php echo (int)$r["quantity"]; ?></div>
<div class="small muted"><?php echo htmlspecialchars(date("d M Y, h:i A", strtotime($r["created_at"]))); ?></div>
</div>
<span class="badge <?php echo $c; ?>"><?php echo htmlspecialchars(ucfirst($r["status"])); ?></span>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>

<!-- LOW STOCK -->
<div class="card">
<h5>Low Stock</h5>

<?php if (!$lowList): ?>
<p style="color:#8b95a5;">All stock levels are healthy.</p>
<?php else: ?>
<?php foreach ($lowList as $l): ?>
<div class="row-item">
<div>
<div><strong><?php echo htmlspecialchars($l["medicine_name"]); ?></strong></div>
<div class="small muted">Stock: <?php echo (int)$l["stock"]; ?></div>
</div>
<a class="btn" href="stock_edit.php?id=<?php echo (int)$l["id"]; ?>">Update</a>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>

</div>

</div>

<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>
</html>