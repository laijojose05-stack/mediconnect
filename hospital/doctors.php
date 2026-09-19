<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["hospital_id"])) {
    header("Location: login.php");
    exit();
}

$hospital_id = $_SESSION["hospital_id"];

$sql = "SELECT department_name FROM departments WHERE hospital_id=? ORDER BY department_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$deptResult = $stmt->get_result();
$departments = [];
while ($row = $deptResult->fetch_assoc()) {
    $departments[] = $row;
}

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

// ADD DOCTOR
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $doctor_name = trim($_POST["doctor_name"]);
    $specialization = trim($_POST["specialization"]);
    $phone = trim($_POST["phone"]);

    $stmt = $conn->prepare(
        "INSERT INTO doctors (hospital_id, doctor_name, specialization, phone) VALUES (?, ?, ?, ?)"
    );

    $stmt->bind_param("isss", $hospital_id, $doctor_name, $specialization, $phone);
    $stmt->execute();
    
    // Optional: redirect to refresh the page
    header("Location: doctors.php");
    exit();
}

// DELETE DOCTOR
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);

    $stmt = $conn->prepare(
        "DELETE FROM doctors WHERE id=? AND hospital_id=?"
    );

    $stmt->bind_param("ii", $id, $hospital_id);
    $stmt->execute();
    
    header("Location: doctors.php");
    exit();
}

// GET ALL DOCTORS
$stmt = $conn->prepare(
    "SELECT * FROM doctors WHERE hospital_id=? ORDER BY id DESC"
);

$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$doctors = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctors | MediConnect</title>

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

/* SIDEBAR */
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

/* MAIN CONTENT */
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

/* CARDS */
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

/* FORM */
.form-row{
    display:flex;
    gap:15px;
    flex-wrap:wrap;
}

input[type="text"]{
    padding:13px;
    background:#212836;
    border:1px solid #30394a;
    border-radius:8px;
    color:white;
    font-size:14px;
    outline:none;
    flex:1;
    min-width:200px;
}

input[type="text"]:focus{
    border-color:#1d8cf8;
}

select{
    padding:13px;
    background:#212836;
    border:1px solid #30394a;
    border-radius:8px;
    color:white;
    font-size:14px;
    outline:none;
    flex:1;
    min-width:200px;
    cursor:pointer;
}

select:focus{
    border-color:#1d8cf8;
}

select option{
    background:#171c26;
    color:white;
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
    white-space:nowrap;
}

button:hover{
    background:#1572cd;
}

/* TABLE */
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
}

.delete-link:hover{
    text-decoration:underline;
}

.empty-msg{
    text-align:center;
    color:#8b95a5;
    padding:30px;
}

/* RESPONSIVE */
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
    }

    input[type="text"]{
        min-width:auto;
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
<a href="doctors.php" class="active" data-icon="👨‍⚕️">Doctors</a>
<a href="departments.php" data-icon="🏛️">Departments</a>
<a href="appointments.php" data-icon="📅">Appointments</a>
<a href="availability.php" data-icon="⏰">Availability</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- MAIN CONTENT -->
<div class="main-content">

<div class="page-header">
<h1>👨‍⚕️ Doctors Management</h1>
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
</div>

<!-- ADD DOCTOR FORM -->
<div class="card">
<h3>➕ Add New Doctor</h3>
<form method="POST">
<div class="form-row">
<input type="text" name="doctor_name" placeholder="Doctor Name" required>
<select name="specialization" id="specSelect" required>
<option value="" disabled selected>Select specialization...</option>
<?php foreach ($departments as $dept): ?>
<option value="<?php echo htmlspecialchars(trim($dept["department_name"])); ?>"><?php echo htmlspecialchars(trim($dept["department_name"])); ?></option>
<?php endforeach; ?>
<option value="__other__">Other...</option>
</select>
<input type="text" id="specOther" placeholder="Type specialization" style="display:none;">
<input type="text" name="phone" placeholder="Phone Number" required>
<button>Add Doctor</button>
</div>
</form>
</div>

<!-- DOCTORS LIST -->
<div class="card">
<h3>📋 All Doctors</h3>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Specialization</th>
<th>Phone</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php if ($doctors->num_rows > 0): ?>
<?php while($doctor = $doctors->fetch_assoc()): ?>
<tr>
<td>#<?php echo $doctor["id"]; ?></td>
<td><strong><?php echo htmlspecialchars($doctor["doctor_name"]); ?></strong></td>
<td><?php echo htmlspecialchars($doctor["specialization"]); ?></td>
<td><?php echo htmlspecialchars($doctor["phone"]); ?></td>
<td>
<a class="delete-link" href="?delete=<?php echo $doctor["id"]; ?>" onclick="return confirm('Delete this doctor?')">🗑️ Delete</a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="5" class="empty-msg">No doctors added yet. Add your first doctor above!</td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

</div>

</div>

<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

<script>
(function(){
    var sel = document.getElementById("specSelect");
    var other = document.getElementById("specOther");
    if(!sel || !other){ return; }
    sel.addEventListener("change", function(){
        var isOther = sel.value === "__other__";
        other.style.display = isOther ? "block" : "none";
        if(isOther){
            sel.disabled = true;
            sel.removeAttribute("name");
            other.setAttribute("name", "specialization");
            other.required = true;
            other.focus();
        } else {
            sel.removeAttribute("disabled");
            sel.setAttribute("name", "specialization");
            other.removeAttribute("name");
            other.required = false;
        }
    });
})();
</script>

</body>
</html>