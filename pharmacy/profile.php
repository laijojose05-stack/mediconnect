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

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name    = trim($_POST["pharmacy_name"] ?? "");
    $phone   = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $city    = trim($_POST["city"] ?? "");

    if ($name === "") {
        $error = "Pharmacy name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE pharmacies SET pharmacy_name=?, phone=?, address=?, city=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $phone, $address, $city, $pharmacy_id);
        $stmt->execute();
        $_SESSION["pharmacy_name"] = $name;

        $newPass = $_POST["new_password"] ?? "";
        if ($newPass !== "") {
            $curPass = $_POST["current_password"] ?? "";
            if (strlen($newPass) < 6) {
                $error = "New password must be at least 6 characters.";
            } else {
                $stmt = $conn->prepare("SELECT password FROM pharmacies WHERE id=?");
                $stmt->bind_param("i", $pharmacy_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();

                if (!$row || !password_verify($curPass, $row["password"])) {
                    $error = "Current password is incorrect.";
                } else {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE pharmacies SET password=? WHERE id=?");
                    $stmt->bind_param("si", $hash, $pharmacy_id);
                    $stmt->execute();
                }
            }
        }

        if ($error === "") {
            $message = "Profile updated.";
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM pharmacies WHERE id=?");
$stmt->bind_param("i", $pharmacy_id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pharmacy Profile | MediConnect</title>

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

/* CARDS */
.card{background:#171c26;padding:25px;border-radius:15px;margin-bottom:25px;border:1px solid #262d3d;}
.card h3{margin-bottom:15px;font-size:18px;}
.card h5{font-size:15px;margin-bottom:15px;}
.side-grid{display:grid;grid-template-columns:7fr 5fr;gap:25px;align-items:start;}
@media(max-width:900px){.side-grid{grid-template-columns:1fr;}}

/* ALERTS */
.alert{padding:13px 15px;border-radius:10px;margin-bottom:20px;font-size:13px;}
.alert.success{background:rgba(0,200,120,0.12);border:1px solid rgba(0,200,120,0.3);color:#66e6aa;}
.alert.error{background:rgba(255,70,70,0.12);border:1px solid rgba(255,70,70,0.3);color:#ff7777;}

/* FORMS */
label{display:block;color:#8b95a5;font-size:12px;margin-top:16px;margin-bottom:7px;}
input[type="text"],input[type="email"],input[type="password"]{width:100%;background:#212836;border:1px solid #30394a;padding:13px;color:white;border-radius:9px;font-size:14px;outline:none;}
input:focus{border-color:#1d8cf8;}
input:disabled{opacity:0.5;cursor:not-allowed;}
.form-row{display:flex;gap:15px;flex-wrap:wrap;}
.form-row input{flex:1;min-width:180px;}

hr{border:none;border-top:1px solid #262d3d;margin:22px 0;}
button{background:#1d8cf8;border:none;color:white;padding:13px 30px;border-radius:9px;cursor:pointer;font-size:14px;font-weight:600;margin-top:18px;}
button:hover{background:#1572cd;}

/* INFO LIST */
.info-item{margin-bottom:15px;}
.info-item .muted{color:#8b95a5;font-size:12px;margin-bottom:3px;}
.badge{display:inline-block;padding:5px 11px;border-radius:20px;font-size:11px;font-weight:600;}
.badge.success{background:rgba(0,200,120,0.15);color:#66e6aa;border:1px solid rgba(0,200,120,0.35);}
.badge.warning{background:rgba(240,180,41,0.15);color:#f0c15a;border:1px solid rgba(240,180,41,0.35);}

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
<a href="stock.php" data-icon="📦">My Stock</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- MAIN CONTENT -->
<div class="main-content">

<div class="page-header">
<div>
<h1>Pharmacy Profile</h1>
</div>
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
</div>

<?php if ($message !== ""): ?>
<div class="alert success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error !== ""): ?>
<div class="alert error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="side-grid">

<!-- EDIT FORM -->
<div class="card">

<form method="POST">

<label>Pharmacy Name</label>
<input type="text" name="pharmacy_name" value="<?php echo htmlspecialchars($p["pharmacy_name"]); ?>" required>

<label>Email Address</label>
<input type="email" value="<?php echo htmlspecialchars($p["email"]); ?>" disabled>

<label>Phone</label>
<input type="text" name="phone" value="<?php echo htmlspecialchars($p["phone"] ?? ""); ?>">

<div class="form-row">
<div>
<label>Address</label>
<input type="text" name="address" value="<?php echo htmlspecialchars($p["address"] ?? ""); ?>">
</div>
<div>
<label>City</label>
<input type="text" name="city" value="<?php echo htmlspecialchars($p["city"] ?? ""); ?>">
</div>
</div>

<hr>

<h5>Change Password <span style="color:#8b95a5;font-size:12px;font-weight:400;">(leave blank to keep current)</span></h5>

<div class="form-row">
<div>
<label>Current Password</label>
<input type="password" name="current_password">
</div>
<div>
<label>New Password</label>
<input type="password" name="new_password">
</div>
</div>

<button>Save Changes</button>

</form>

</div>

<!-- ACCOUNT INFO -->
<div class="card">
<h3>Account Info</h3>

<div class="info-item">
<div class="muted">License</div>
<div><?php echo htmlspecialchars($p["license_number"] ?? "—"); ?></div>
</div>

<div class="info-item">
<div class="muted">Status</div>
<span class="badge <?php echo ($p["status"] ?? "pending") === "approved" ? "success" : "warning"; ?>">
<?php echo htmlspecialchars(ucfirst($p["status"] ?? "pending")); ?>
</span>
</div>

<div class="info-item">
<div class="muted">Member Since</div>
<div><?php echo htmlspecialchars($p["created_at"] ?? "—"); ?></div>
</div>
</div>

</div>

</div>

<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>
</html>