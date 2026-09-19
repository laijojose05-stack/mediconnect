<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["hospital_id"])) {
    header("Location: login.php");
    exit();
}

$hospital_id = $_SESSION["hospital_id"];

require_once "../partials/_right.php";
$rightCfg = [
    "ntable"             => "hospital_notifications",
    "recipient_col"      => "hospital_id",
    "recipient_id"       => (int)$hospital_id,
    "profile_table"      => "hospitals",
    "profile_id_field"   => "id",
    "profile_label_field"=> "hospital_name",
    "profile_email_field"=> "email",
    "session_label_key"  => "hospital_name",
    "profile_title"      => "Hospital Profile",
    "profile_fields"     => [
        ["name" => "hospital_name", "label" => "Hospital Name"],
        ["name" => "phone",         "label" => "Phone"],
        ["name" => "address",       "label" => "Address", "type" => "textarea"],
        ["name" => "city",          "label" => "City"],
        ["name" => "specialty",     "label" => "Specialty"],
    ],
];
right_handle($conn, $rightCfg);
$rightUnread = right_unread($conn, $rightCfg);

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hospital_name = trim($_POST["hospital_name"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);

    $stmt = $conn->prepare(
        "UPDATE hospitals SET hospital_name=?, phone=?, address=? WHERE id=?"
    );

    $stmt->bind_param("sssi", $hospital_name, $phone, $address, $hospital_id);
    $stmt->execute();

    $_SESSION["hospital_name"] = $hospital_name;

    $message = "Profile updated successfully.";
}

$stmt = $conn->prepare(
    "SELECT * FROM hospitals WHERE id=?"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$hospital = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hospital Profile | MediConnect</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background:#0f1319;
    color:white;
    min-height:100vh;
    display:flex;
}

/* ===============================
   SIDEBAR
================================ */

.sidebar{
    width:250px;
    background:#171c26;
    padding:30px 20px;
    border-right:1px solid #262d3d;
    min-height:100vh;
    flex-shrink:0;
    position:fixed;
    top:0;
    left:0;
    bottom:0;
    overflow-y:auto;
}

.logo{
    font-size:22px;
    font-weight:bold;
    margin-bottom:45px;
    color:white;
}

.logo span{color:#1d8cf8}

.sidebar a{
    display:block;
    padding:13px 15px;
    margin-bottom:5px;
    color:#8b95a5;
    text-decoration:none;
    border-radius:10px;
    font-size:14px;
    transition:0.3s;
}

.sidebar a:hover{
    background:#212836;
    color:#1d8cf8;
}

.sidebar a.active{
    background:#212836;
    color:#1d8cf8;
}

/* ===============================
   MAIN CONTENT
================================ */

.main-content{
    flex:1;
    margin-left:250px;
    padding:40px 45px;
    background:#0f1319;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
}

.page-header h1{
    font-size:32px;
}

/* ===============================
   CARD
================================ */

.card{
    max-width:700px;
    background:#171c26;
    padding:35px;
    border-radius:18px;
    border:1px solid #262d3d;
}

/* ===============================
   FORM
================================ */

label{
    display:block;
    color:#b8c0cc;
    font-size:14px;
    margin-top:18px;
    margin-bottom:8px;
}

input,textarea{
    width:100%;
    background:#212836;
    border:1px solid #30394a;
    padding:14px;
    color:white;
    border-radius:10px;
    font-size:14px;
    outline:none;
}

input:focus,textarea:focus{
    border-color:#1d8cf8;
}

textarea{
    height:100px;
    resize:none;
}

input:disabled{
    opacity:0.5;
    cursor:not-allowed;
}

button{
    background:#1d8cf8;
    border:none;
    color:white;
    padding:14px 30px;
    border-radius:10px;
    cursor:pointer;
    font-size:15px;
    font-weight:600;
    margin-top:20px;
}

button:hover{
    background:#1572cd;
}

/* ===============================
   MESSAGE
================================ */

.message{
    background:rgba(0,200,120,0.12);
    border:1px solid rgba(0,200,120,0.3);
    color:#66e6aa;
    padding:15px;
    border-radius:12px;
    margin-bottom:20px;
}

/* ===============================
   RESPONSIVE
================================ */

@media(max-width:768px){
    .sidebar{
        width:70px;
        padding:25px 12px;
    }

    .sidebar a{
        font-size:0;
        padding:14px 0;
        text-align:center;
    }

    .sidebar a::before{
        content:attr(data-icon);
        font-size:20px;
        display:block;
    }

    .logo{
        font-size:0;
    }

    .logo span{
        font-size:22px;
    }

    .main-content{
        margin-left:70px;
        padding:25px 20px;
    }

    .page-header h1{
        font-size:26px;
    }

    .card{
        padding:25px;
    }
}

@media(max-width:480px){
    .sidebar{
        width:60px;
        padding:20px 8px;
    }

    .main-content{
        margin-left:60px;
        padding:20px 15px;
    }

    .card{
        padding:20px;
    }
}


.bname{font-size:17px;white-space:nowrap;}@media(max-width:768px){.bname{font-size:0 !important;}}
</style>

<?php require_once "../partials/_theme.php"; ?>

</head>

<body>

<!-- ===============================
     SIDEBAR
================================ -->

<div class="sidebar">

<div class="logo" style="display:flex;align-items:center;gap:10px;"><img src="../assets/logo.png" alt="MediConnect" style="height:44px;width:auto;max-width:100%;"><span class="bname" style="color:#f5f7fa;">MediConnect<span style="color:#1d8cf8;">.</span></span></div>

<a href="dashboard.php" data-icon="📊">Dashboard</a>
<a href="doctors.php" data-icon="👨‍⚕️">Doctors</a>
<a href="departments.php" data-icon="🏛️">Departments</a>
<a href="appointments.php" data-icon="📅">Appointments</a>
<a href="availability.php" data-icon="⏰">Availability</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- ===============================
     MAIN CONTENT
================================ -->

<div class="main-content">

<div class="page-header">
<h1>Hospital Profile</h1>
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
</div>

<div class="card">

<?php if($message): ?>
<div class="message"><?php echo $message; ?></div>
<?php endif; ?>

<form method="POST">

<label>Hospital Name</label>
<input type="text" name="hospital_name" value="<?php echo htmlspecialchars($hospital["hospital_name"]); ?>" required>

<label>Email Address</label>
<input type="email" value="<?php echo htmlspecialchars($hospital["email"]); ?>" disabled>

<label>Phone</label>
<input type="text" name="phone" value="<?php echo htmlspecialchars($hospital["phone"]); ?>" required>

<label>Address</label>
<textarea name="address"><?php echo htmlspecialchars($hospital["address"]); ?></textarea>

<button>Save Changes</button>

</form>

</div>

</div>

<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>
</body>
</html>