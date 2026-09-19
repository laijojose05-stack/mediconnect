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

// ADD DEPARTMENT
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $department_name = trim($_POST["department_name"]);

    $stmt = $conn->prepare(
        "INSERT INTO departments (hospital_id, department_name) VALUES (?, ?)"
    );

    $stmt->bind_param("is", $hospital_id, $department_name);
    $stmt->execute();
    
    header("Location: departments.php");
    exit();
}

// DELETE DEPARTMENT
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);

    $stmt = $conn->prepare(
        "DELETE FROM departments WHERE id=? AND hospital_id=?"
    );

    $stmt->bind_param("ii", $id, $hospital_id);
    $stmt->execute();
    
    header("Location: departments.php");
    exit();
}

// GET ALL DEPARTMENTS
$stmt = $conn->prepare(
    "SELECT * FROM departments WHERE hospital_id=? ORDER BY id DESC"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$departments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Departments | MediConnect</title>

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
    background:#171c26;
    padding:25px;
    border-radius:15px;
    margin-bottom:25px;
    border:1px solid #262d3d;
}

.card h3{
    margin-bottom:15px;
    font-size:18px;
}

/* ===============================
   FORM
================================ */

.form-row{
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    align-items:center;
}

input[type="text"]{
    padding:13px 16px;
    background:#212836;
    border:1px solid #30394a;
    border-radius:8px;
    color:white;
    font-size:14px;
    outline:none;
    flex:1;
    min-width:250px;
}

input[type="text"]:focus{
    border-color:#1d8cf8;
    box-shadow:0 0 0 3px rgba(29,140,248,0.1);
}

button{
    padding:13px 25px;
    background:#1d8cf8;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size:14px;
    font-weight:600;
    transition:0.3s;
    white-space:nowrap;
}

button:hover{
    background:#1572cd;
    transform:translateY(-2px);
}

/* ===============================
   TABLE
================================ */

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:15px;
    border-bottom:1px solid #262d3d;
    text-align:left;
}

th{
    color:#8b95a5;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}

td{
    font-size:14px;
}

.delete-link{
    color:#ff7777;
    text-decoration:none;
    font-weight:bold;
    font-size:13px;
    padding:5px 12px;
    border-radius:6px;
    transition:0.3s;
}

.delete-link:hover{
    background:rgba(255,70,70,0.15);
    text-decoration:underline;
}

.empty-msg{
    text-align:center;
    color:#8b95a5;
    padding:30px;
}

/* ===============================
   DEPARTMENT BADGE
================================ */

.dept-badge{
    display:inline-block;
    padding:6px 14px;
    background:rgba(29,140,248,0.12);
    border:1px solid rgba(29,140,248,0.2);
    border-radius:20px;
    color:#1d8cf8;
    font-size:13px;
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

    .form-row{
        flex-direction:column;
        align-items:stretch;
    }

    input[type="text"]{
        min-width:auto;
        width:100%;
    }

    button{
        width:100%;
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

    .page-header h1{
        font-size:22px;
    }

    .card{
        padding:18px;
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
<a href="departments.php" class="active" data-icon="🏛️">Departments</a>
<a href="appointments.php" data-icon="📅">Appointments</a>
<a href="availability.php" data-icon="⏰">Availability</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- ===============================
     MAIN CONTENT
================================ -->

<div class="main-content">

<div class="page-header">
<h1>🏛️ Departments</h1>
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
</div>

<!-- ===============================
     ADD DEPARTMENT
================================ -->

<div class="card">
<h3>➕ Add New Department</h3>
<form method="POST">
<div class="form-row">
<input type="text" name="department_name" placeholder="Enter department name (e.g. Cardiology, Pediatrics)" required>
<button>Add Department</button>
</div>
</form>
</div>

<!-- ===============================
     DEPARTMENTS LIST
================================ -->

<div class="card">
<h3>📋 All Departments</h3>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>ID</th>
<th>Department Name</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php if ($departments->num_rows > 0): ?>
<?php while($row = $departments->fetch_assoc()): ?>
<tr>
<td>#<?php echo $row["id"]; ?></td>
<td><span class="dept-badge"><?php echo htmlspecialchars($row["department_name"]); ?></span></td>
<td>
<a class="delete-link" href="?delete=<?php echo $row["id"]; ?>" onclick="return confirm('Delete this department?')">🗑️ Delete</a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="3" class="empty-msg">No departments added yet. Add your first department above!</td>
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