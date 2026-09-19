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

/* Quick add to stock */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "quick_add") {
    $mid = (int)($_POST["medicine_id"] ?? 0);
    if ($mid) {
        $stmt = $conn->prepare(
            "INSERT INTO pharmacy_medicines (pharmacy_id, medicine_id, price, stock)
             VALUES (?, ?, NULL, 0)"
        );
        $stmt->bind_param("ii", $pharmacy_id, $mid);

        if ($stmt->execute()) {
            header("Location: medicines.php?msg=added");
            exit;
        } else {
            header("Location: medicines.php?msg=exists");
            exit;
        }
    }
}

$msg = $_GET["msg"] ?? "";

/* Search + filter */
$q   = trim($_GET["q"] ?? "");
$cat = trim($_GET["category"] ?? "");

$where  = " WHERE 1=1";
$bindTypes = "i";
$bindParams = [$pharmacy_id];

if ($q !== "") {
    $where .= " AND (m.medicine_name LIKE ? OR m.generic_name LIKE ? OR m.category LIKE ?)";
    $like = "%$q%";
    $bindTypes .= "s"; $bindParams[] = $like;
    $bindTypes .= "s"; $bindParams[] = $like;
    $bindTypes .= "s"; $bindParams[] = $like;
}
if ($cat !== "") {
    $where .= " AND m.category = ?";
    $bindTypes .= "s"; $bindParams[] = $cat;
}

/* Pagination */
$perPage = 24;
$page    = max(1, (int)($_GET["page"] ?? 1));
$offset  = ($page - 1) * $perPage;

/* Count (skip the pharmacy_id placeholder — no join in the count query) */
$total = 0;
$countTypes  = substr($bindTypes, 1);
$countParams = array_slice($bindParams, 1);
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM medicines m $where");
if ($stmt) {
    if ($countParams) $stmt->bind_param($countTypes, ...$countParams);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $total = (int)$row["total"];
}
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$sql = "SELECT m.*, pm.id AS stock_id, pm.price, pm.stock
        FROM medicines m
        LEFT JOIN pharmacy_medicines pm
               ON pm.medicine_id = m.id AND pm.pharmacy_id = ?
        $where
        ORDER BY m.medicine_name LIMIT $perPage OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$meds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$cats = [];
$result = $conn->query(
    "SELECT DISTINCT category FROM medicines
     WHERE category IS NOT NULL AND category <> '' ORDER BY category"
);
if ($result) {
    $cats = array_map(fn($row) => $row["category"], $result->fetch_all(MYSQLI_ASSOC));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medicine Catalogue | MediConnect</title>

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

/* SEARCH FORM */
.search-row{display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end;margin-bottom:25px;}
.search-row .field{flex:1;min-width:200px;}
.search-row .field label{display:block;color:#8b95a5;font-size:12px;margin-bottom:7px;}
.search-row .field.small{flex:0 0 220px;}
input[type="text"],option{width:100%;padding:12px;background:#212836;border:1px solid #30394a;border-radius:8px;color:white;font-size:14px;outline:none;}
input:focus{border-color:#1d8cf8;}
select{width:100%;padding:12px;background:#212836;border:1px solid #30394a;border-radius:8px;color:white;font-size:14px;outline:none;cursor:pointer;}
select option{background:#171c26;}
button{padding:12px 22px;background:#1d8cf8;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;}
button:hover{background:#1572cd;}

/* MEDICINE GRID */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;}
.card{background:#171c26;padding:20px;border-radius:15px;border:1px solid #262d3d;display:flex;flex-direction:column;}
.card.in-stock{border-color:#1d8cf8;}
.card h6{font-size:14px;margin-bottom:2px;}
.card .gen{color:#8b95a5;font-size:12px;margin-bottom:10px;}
.card .detail{color:#8b95a5;font-size:12px;margin-bottom:12px;}
.card .actions{margin-top:auto;padding-top:10px;}
.mt-auto{margin-top:auto;}

/* BADGES */
.badge{display:inline-block;padding:5px 11px;border-radius:20px;font-size:11px;font-weight:600;align-self:flex-start;margin-bottom:10px;}
.badge.success{background:rgba(0,200,120,0.15);color:#66e6aa;border:1px solid rgba(0,200,120,0.35);}
.badge.primary{background:rgba(29,140,248,0.15);color:#6db9ff;border:1px solid rgba(29,140,248,0.35);}

/* BTNS */
.btn{display:inline-block;width:100%;text-align:center;padding:9px 14px;background:#1d8cf8;border:1px solid #1d8cf8;color:white;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:0.2s;}
.btn:hover{background:#1572cd;}
.btn.ghost{background:transparent;color:#6db9ff;}
.btn.ghost:hover{background:rgba(29,140,248,0.1);}

.empty-msg{text-align:center;color:#8b95a5;padding:50px 20px;font-size:14px;}

/* RESPONSIVE */
@media(max-width:768px){
    .sidebar{width:70px;padding:25px 12px;}
    .sidebar a{font-size:0;padding:14px 0;text-align:center;}
    .sidebar a::before{content:attr(data-icon);font-size:20px;display:block;}
    .logo{font-size:0;}
    .logo span{font-size:22px;}
    .main-content{padding:25px 20px;}
    .page-header h1{font-size:26px;}
    .search-row{flex-direction:column;}
    .search-row .field.small{flex:1;min-width:auto;width:100%;}
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
<a href="medicines.php" class="active" data-icon="💊">Medicine Catalogue</a>
<a href="requests.php" data-icon="📥">Requests</a>
<a href="stock.php" data-icon="📦">My Stock</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- MAIN CONTENT -->
<div class="main-content">

<div class="page-header">
<div>
<h1>Medicine Catalogue</h1>
<p class="subtitle">Browse and add medicines to your stock</p>
</div>
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
</div>

<?php if ($msg === "added"): ?>
<div class="alert success">Added to stock. Update price &amp; quantity.</div>
<?php elseif ($msg === "exists"): ?>
<div class="alert error">That medicine is already in your stock.</div>
<?php endif; ?>

<!-- SEARCH -->
<form class="search-row" method="GET">
<div class="field">
<label>Search</label>
<input type="text" name="q" placeholder="Search medicine…" value="<?php echo htmlspecialchars($q); ?>">
</div>
<div class="field small">
<label>Category</label>
<select name="category">
<option value="">All categories</option>
<?php foreach ($cats as $c): ?>
<option value="<?php echo htmlspecialchars($c); ?>" <?php echo $c === $cat ? "selected" : ""; ?>>
<?php echo htmlspecialchars($c); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div>
<button type="submit">Search</button>
</div>
</form>

<?php if (!$meds): ?>
<div class="card empty-msg">No medicines found. Try a different search.</div>
<?php else: ?>

<div class="grid">
<?php foreach ($meds as $m): $inStock = !empty($m["stock_id"]); ?>
<div class="card <?php echo $inStock ? "in-stock" : ""; ?>">

<?php if ($inStock): ?>
<span class="badge success">In stock</span>
<?php endif; ?>

<h6><?php echo htmlspecialchars($m["medicine_name"]); ?></h6>
<div class="gen"><?php echo htmlspecialchars($m["generic_name"] ?? "—"); ?></div>

<?php if (!empty($m["category"])): ?>
<span class="badge primary"><?php echo htmlspecialchars($m["category"]); ?></span>
<?php endif; ?>

<?php if ($inStock): ?>
<div class="detail">
Your price: <?php echo $m["price"] !== null ? "Rs " . number_format((float)$m["price"], 2) : "—"; ?>
· Stock: <?php echo (int)$m["stock"]; ?>
</div>
<?php endif; ?>

<div class="actions">
<?php if ($inStock): ?>
<a class="btn ghost" href="stock_edit.php?id=<?php echo (int)$m["stock_id"]; ?>">Update Stock</a>
<?php else: ?>
<form method="POST">
<input type="hidden" name="action" value="quick_add">
<input type="hidden" name="medicine_id" value="<?php echo (int)$m["id"]; ?>">
<button class="btn" type="submit">Add to Stock</button>
</form>
<?php endif; ?>
</div>

</div>
<?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<?php
$pgBase = "medicines.php?" . ($q !== "" ? "q=" . urlencode($q) . "&" : "") . ($cat !== "" ? "category=" . urlencode($cat) . "&" : "");
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-top:26px;gap:10px;flex-wrap:wrap;">
<span style="color:#8b95a5;font-size:13px;"><?php echo (int)$total; ?> medicines · Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
<div style="display:flex;gap:6px;">
<?php if ($page > 1): ?>
<a class="btn" href="<?php echo htmlspecialchars($pgBase . "page=" . ($page - 1)); ?>">← Prev</a>
<?php endif; ?>
<span style="align-self:center;color:#6db9ff;font-size:13px;font-weight:600;"><?php echo $page; ?></span>
<?php if ($page < $totalPages): ?>
<a class="btn" href="<?php echo htmlspecialchars($pgBase . "page=" . ($page + 1)); ?>">Next →</a>
<?php endif; ?>
</div>
<form method="GET" style="display:flex;gap:6px;align-items:center;margin:0;">
<?php if ($q !== ""): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>"><?php endif; ?>
<?php if ($cat !== ""): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($cat); ?>"><?php endif; ?>
<span style="color:#8b95a5;font-size:13px;">Go to page</span>
<input type="number" name="page" min="1" max="<?php echo (int)$totalPages; ?>" value="<?php echo (int)$page; ?>" style="width:70px;padding:9px;background:#212836;border:1px solid #30394a;border-radius:8px;color:white;font-size:14px;">
<button class="btn" type="submit" style="padding:9px 14px;">Go</button>
</form>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>
</html>