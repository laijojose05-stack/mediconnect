<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["hospital_id"])) {
    header("Location: login.php");
    exit();
}

$hospital_id = $_SESSION["hospital_id"];

$hospital_name = $_SESSION["hospital_name"] ?? "Hospital";

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

$doctor_count = 0;
$department_count = 0;
$appointment_count = 0;
$patient_count = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM doctors
    WHERE hospital_id = $hospital_id"
);

if ($result) {
    $doctor_count = $result->fetch_assoc()["total"];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM departments
    WHERE hospital_id = $hospital_id"
);

if ($result) {
    $department_count = $result->fetch_assoc()["total"];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM appointments
    WHERE hospital_id = $hospital_id"
);

if ($result) {
    $appointment_count = $result->fetch_assoc()["total"];
}

$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM patients
    WHERE hospital_id = $hospital_id"
);

if ($result) {
    $patient_count = $result->fetch_assoc()["total"];
}

/* Upcoming appointments (pending/approved, from today onwards) */
$upcoming = [];
$result = $conn->query(
    "SELECT a.* FROM appointments a
     WHERE a.hospital_id = $hospital_id
       AND a.status IN ('pending','approved')
       AND a.appointment_date >= CURDATE()
     ORDER BY a.appointment_date ASC, a.appointment_time ASC
     LIMIT 5"
);
if ($result) $upcoming = $result->fetch_all(MYSQLI_ASSOC);

/* Recent appointments */
$recent = [];
$result = $conn->query(
    "SELECT a.* FROM appointments a
     WHERE a.hospital_id = $hospital_id
     ORDER BY a.created_at DESC
     LIMIT 6"
);
if ($result) $recent = $result->fetch_all(MYSQLI_ASSOC);

/* Pending appointments count (for the badge) */
$pending_count = 0;
$result = $conn->query(
    "SELECT COUNT(*) AS total FROM appointments
     WHERE hospital_id = $hospital_id AND status = 'pending'"
);
if ($result) $pending_count = (int)$result->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hospital Dashboard | MediConnect</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial,sans-serif;
}

body{
background:#0f1319;
color:white;
display:flex;
min-height:100vh;
}

.sidebar{
width:250px;
background:#171c26;
padding:30px 20px;
border-right:1px solid #262d3d;
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
}

.logo span{color:#1d8cf8}

.sidebar a{
display:block;
padding:13px;
margin-bottom:8px;
color:#8b95a5;
text-decoration:none;
border-radius:10px;
}

.sidebar a:hover,
.sidebar a.active{
background:#212836;
color:#1d8cf8;
}

.content{
flex:1;
margin-left:250px;
padding:40px;
}

.header{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:40px;
}

.header p{
color:#8b95a5;
margin-top:8px;
}

.cards{
display:grid;
grid-template-columns:repeat(4,1fr);
gap:20px;
}

.stat{
background:#171c26;
border:1px solid #262d3d;
padding:25px;
border-radius:16px;
}

.stat p{
color:#8b95a5;
font-size:14px;
margin-bottom:10px;
}

.stat h2{
font-size:32px;
color:#1d8cf8;
}

.card{
background:#171c26;
padding:25px;
border-radius:15px;
margin-bottom:25px;
border:1px solid #262d3d;
}

.card h5{
font-size:15px;
margin-bottom:15px;
}

.side-grid{
display:grid;
grid-template-columns:7fr 5fr;
gap:25px;
align-items:start;
}

.row-item{
display:flex;
justify-content:space-between;
align-items:flex-start;
border-bottom:1px solid #262d3d;
padding:13px 0;
gap:10px;
}

.row-item:last-child{border-bottom:none;}
.row-item .muted{color:#8b95a5;}
.row-item .small{font-size:12px;margin-top:3px;}
.row-item .end{text-align:right;}

.badge{
display:inline-block;
padding:5px 11px;
border-radius:20px;
font-size:11px;
font-weight:600;
white-space:nowrap;
}

.badge.warning{background:rgba(240,180,41,0.15);color:#f0c15a;border:1px solid rgba(240,180,41,0.35);}
.badge.primary{background:rgba(29,140,248,0.15);color:#6db9ff;border:1px solid rgba(29,140,248,0.35);}
.badge.success{background:rgba(0,200,120,0.15);color:#66e6aa;border:1px solid rgba(0,200,120,0.35);}
.badge.danger{background:rgba(255,70,70,0.15);color:#ff8a8a;border:1px solid rgba(255,70,70,0.35);}
.badge.secondary{background:rgba(139,149,165,0.15);color:#9aa4b5;border:1px solid rgba(139,149,165,0.35);}

.quick{
display:flex;
gap:10px;
flex-wrap:wrap;
margin-bottom:5px;
}

.btn{
display:inline-block;
padding:6px 12px;
background:#212836;
color:#c5ccd8;
border:1px solid #30394a;
border-radius:8px;
font-size:12px;
font-weight:600;
cursor:pointer;
text-decoration:none;
transition:0.2s;
}

.btn:hover{
color:#1d8cf8;
border-color:#1d8cf8;
}

.btn.link{background:transparent;border-color:transparent;color:#6db9ff;padding-left:0;padding-right:0;}

@media(max-width:900px){
.side-grid{grid-template-columns:1fr;}

.sidebar{
width:200px;
padding:20px 15px;
}

.sidebar a{
font-size:14px;
padding:12px;
}

.content{
margin-left:200px;
}

.cards{
grid-template-columns:1fr 1fr;
}
}

@media(max-width:600px){

.cards{
grid-template-columns:1fr;
}

.sidebar{
width:170px;
padding:18px 12px;
}

.sidebar a{
font-size:13px;
padding:11px;
}

.content{
margin-left:170px;
padding:25px;
}

}

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

.content{
margin-left:70px;
padding:25px 20px;
}

.cards{
grid-template-columns:1fr;
}

}

@media(max-width:480px){

.sidebar{
width:60px;
padding:20px 8px;
}

.content{
margin-left:60px;
padding:20px 15px;
}

}


.bname{font-size:17px;white-space:nowrap;}@media(max-width:768px){.bname{font-size:0 !important;}}
</style>

<?php require_once "../partials/_theme.php"; ?>

</head>

<body>

<div class="sidebar">

<div class="logo" style="display:flex;align-items:center;gap:10px;"><img src="../assets/logo.png" alt="MediConnect" style="height:44px;width:auto;max-width:100%;"><span class="bname" style="color:#f5f7fa;">MediConnect<span style="color:#1d8cf8;">.</span></span></div>

<a href="dashboard.php" class="active" data-icon="📊">Dashboard</a>

<a href="doctors.php" data-icon="👨‍⚕️">Doctors</a>

<a href="departments.php" data-icon="🏛️">Departments</a>

<a href="appointments.php" data-icon="📅">Appointments</a>

<a href="availability.php" data-icon="⏰">Availability</a>

<a href="logout.php" data-icon="🚪">Logout</a>

</div>


<div class="content">

<div class="header">

<div>

<h1>
Welcome,
<?php echo htmlspecialchars($hospital_name); ?>
</h1>

<p>
Hospital Management Dashboard
</p>

</div>

<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>

</div>


<div class="cards">

<div class="stat">

<p>Total Doctors</p>

<h2>
<?php echo $doctor_count; ?>
</h2>

</div>


<div class="stat">

<p>Departments</p>

<h2>
<?php echo $department_count; ?>
</h2>

</div>


<div class="stat">

<p>Appointments</p>

<h2>
<?php echo $appointment_count; ?>
</h2>

</div>


<div class="stat">

<p>Patients</p>

<h2>
<?php echo $patient_count; ?>
</h2>

</div>

</div>



<div class="side-grid">

<!-- RECENT APPOINTMENTS -->
<div class="card">

<h5>Recent Appointments</h5>

<?php if (!$recent): ?>
<p style="color:#8b95a5;">No appointments yet.</p>
<?php else: ?>
<?php foreach ($recent as $a):
    $c = "secondary";
    switch (strtolower($a["status"])) {
        case "pending":   $c = "warning"; break;
        case "approved":  $c = "primary"; break;
        case "completed": $c = "success"; break;
        case "rejected":  $c = "danger";  break;
        case "cancelled": $c = "secondary"; break;
    }
?>
<div class="row-item">
<div>
<div><strong><?php echo htmlspecialchars($a["patient_name"]); ?></strong></div>
<div class="small muted"><?php echo htmlspecialchars($a["doctor_name"] ?? "—"); ?> · <?php echo htmlspecialchars(date("d M Y", strtotime($a["appointment_date"]))); ?><?php if (!empty($a["appointment_time"])) echo " · " . htmlspecialchars(date("h:i A", strtotime($a["appointment_time"]))); ?></div>
</div>
<span class="badge <?php echo $c; ?>"><?php echo htmlspecialchars(ucfirst($a["status"])); ?></span>
</div>
<?php endforeach; ?>
<a class="btn link" href="appointments.php">View all →</a>
<?php endif; ?>

</div>

<!-- UPCOMING APPOINTMENTS -->
<div class="card">

<h5>Upcoming Appointments</h5>

<?php if (!$upcoming): ?>
<p style="color:#8b95a5;">No upcoming appointments.</p>
<?php else: ?>
<?php foreach ($upcoming as $a): ?>
<div class="row-item">
<div>
<div><strong><?php echo htmlspecialchars($a["patient_name"]); ?></strong></div>
<div class="small muted"><?php echo htmlspecialchars(date("d M Y", strtotime($a["appointment_date"]))); ?><?php if (!empty($a["appointment_time"])) echo " · " . htmlspecialchars(date("h:i A", strtotime($a["appointment_time"]))); ?></div>
</div>
<span class="badge <?php echo ($a["status"] === "approved") ? "primary" : "warning"; ?>"><?php echo htmlspecialchars(ucfirst($a["status"])); ?></span>
</div>
<?php endforeach; ?>
<a class="btn link" href="appointments.php">View all →</a>
<?php endif; ?>

</div>

</div>

</div>

<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>
</html>