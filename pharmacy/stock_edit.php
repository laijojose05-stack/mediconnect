<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["pharmacy_id"])) {
    header("Location: login.php");
    exit();
}

$pharmacy_id = (int)$_SESSION["pharmacy_id"];
$id          = (int)($_GET["id"] ?? 0);

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

/* Load existing if editing */
$item = null;
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM pharmacy_medicines WHERE id = ? AND pharmacy_id = ?");
    $stmt->bind_param("ii", $id, $pharmacy_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();

    if (!$item) {
        $_SESSION["flash_error"] = "Stock item not found.";
        header("Location: stock.php");
        exit;
    }
}

$err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $medicineId = (int)($_POST["medicine_id"] ?? 0);
    $price      = ($_POST["price"] ?? "") !== "" ? (float)$_POST["price"] : null;
    $stock      = max(0, (int)($_POST["stock"] ?? 0));

    if (!$medicineId) {
        $err = "Please select a medicine.";
    } else {
        $chk = $conn->prepare("SELECT id FROM medicines WHERE id = ?");
        $chk->bind_param("i", $medicineId);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            $err = "Invalid medicine selected.";
        }
    }

    if ($err === "") {
        if ($item) {
            $stmt = $conn->prepare(
                "UPDATE pharmacy_medicines SET medicine_id = ?, price = ?, stock = ?
                 WHERE id = ? AND pharmacy_id = ?"
            );
            $stmt->bind_param("idiii", $medicineId, $price, $stock, $item["id"], $pharmacy_id);

            if ($stmt->execute()) {
                $_SESSION["flash_success"] = "Stock updated.";
            } else {
                $err = $conn->errno === 1062
                    ? "That medicine is already in your stock. Edit it instead."
                    : "Database error: " . $stmt->error;
            }
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO pharmacy_medicines (pharmacy_id, medicine_id, price, stock)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("iidi", $pharmacy_id, $medicineId, $price, $stock);

            if ($stmt->execute()) {
                $_SESSION["flash_success"] = "Medicine added to stock.";
            } else {
                $err = $conn->errno === 1062
                    ? "That medicine is already in your stock. Edit it instead."
                    : "Database error: " . $stmt->error;
            }
        }

        if ($err === "") {
            header("Location: stock.php");
            exit;
        }
    }
}

/* Load medicines dropdown */
$meds = [];
$result = $conn->query("SELECT id, medicine_name, generic_name, category FROM medicines ORDER BY medicine_name");
if ($result) $meds = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $item ? "Edit Stock | MediConnect" : "Add Stock | MediConnect"; ?></title>

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
.alert.error{background:rgba(255,70,70,0.12);border:1px solid rgba(255,70,70,0.3);color:#ff7777;}

/* CARD */
.card{max-width:640px;background:#171c26;padding:30px;border-radius:15px;border:1px solid #262d3d;margin:0 auto;}
.card h3{margin-bottom:20px;font-size:18px;}

/* FORM */
label{display:block;color:#8b95a5;font-size:12px;margin-top:16px;margin-bottom:7px;}
input[type="number"],select,input[type="text"]{width:100%;background:#212836;border:1px solid #30394a;padding:13px;color:white;border-radius:9px;font-size:14px;outline:none;cursor:pointer;}
input:focus,select:focus{border-color:#1d8cf8;}
select option{background:#171c26;color:white;}
.form-row{display:flex;gap:15px;flex-wrap:wrap;}
.form-row .field{flex:1;min-width:180px;}
.form-row .field input{width:100%;}

button{padding:13px 30px;color:white;border:none;border-radius:9px;cursor:pointer;font-size:14px;font-weight:600;margin-top:22px;}
button[type="submit"]{background:#1d8cf8;}
button[type="submit"]:hover{background:#1572cd;}
.btn{display:inline-block;padding:6px 12px;background:#212836;color:#c5ccd8;border:1px solid #30394a;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:0.2s;}
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
    .form-row{flex-direction:column;}
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
<h1><?php echo $item ? "Edit Stock Item" : "Add Medicine to Stock"; ?></h1>
</div>
<div style="display:flex;gap:10px;align-items:center;">
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
<a class="btn" href="stock.php">← Back to Stock</a>
</div>
</div>

<?php if ($err !== ""): ?>
<div class="alert error"><?php echo htmlspecialchars($err); ?></div>
<?php endif; ?>

<div class="card">

<form method="POST">

<label>Medicine *</label>
<select name="medicine_id" required>
<option value="">Select a medicine…</option>
<?php foreach ($meds as $m): ?>
<option value="<?php echo (int)$m["id"]; ?>"
<?php echo (int)$m["id"] === (int)($item["medicine_id"] ?? $_POST["medicine_id"] ?? 0) ? "selected" : ""; ?>>
<?php echo htmlspecialchars($m["medicine_name"]); ?>
<?php echo $m["generic_name"] ? " — " . htmlspecialchars($m["generic_name"]) : ""; ?>
<?php echo $m["category"] ? " (" . htmlspecialchars($m["category"]) . ")" : ""; ?>
</option>
<?php endforeach; ?>
</select>

<div class="form-row">
<div class="field">
<label>Price (Rs)</label>
<input type="number" step="0.01" min="0" name="price"
       value="<?php echo htmlspecialchars($item["price"] ?? $_POST["price"] ?? ""); ?>"
       placeholder="120.00">
</div>
<div class="field">
<label>Stock Quantity *</label>
<input type="number" min="0" name="stock"
       value="<?php echo htmlspecialchars($item["stock"] ?? $_POST["stock"] ?? 0); ?>"
       required>
</div>
</div>

<button type="submit"><?php echo $item ? "Update Stock" : "Add to Stock"; ?></button>

</form>

</div>

</div>
<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>
</html>