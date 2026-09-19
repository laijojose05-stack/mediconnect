<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["hospital_id"])) {
    header("Location: login.php");
    exit();
}

$hospital_id = (int) $_SESSION["hospital_id"];

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
$error = "";


/* ==========================================
   APPROVE / REJECT APPOINTMENT
========================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_appointment"])
) {

    $appointment_id = (int) ($_POST["appointment_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    $allowed_status = ["approved", "rejected"];

    if ($appointment_id <= 0 || !in_array($status, $allowed_status)) {
        $error = "Invalid appointment request.";
    } else {
        $update_sql = "
            UPDATE appointments
            SET status = ?
            WHERE id = ?
            AND hospital_id = ?
        ";

        $update_stmt = $conn->prepare($update_sql);

        if ($update_stmt === false) {
            $error = "Database Error: " . $conn->error;
        } else {
            $update_stmt->bind_param("sii", $status, $appointment_id, $hospital_id);

            if ($update_stmt->execute()) {
                $message = "Appointment " . ucfirst($status) . " successfully.";

                /* Notify the patient */
                $info_stmt = $conn->prepare(
                    "SELECT user_id, doctor_name, appointment_date, appointment_time
                     FROM appointments
                     WHERE id = ? AND hospital_id = ?"
                );
                if ($info_stmt) {
                    $info_stmt->bind_param("ii", $appointment_id, $hospital_id);
                    $info_stmt->execute();
                    $info = $info_stmt->get_result()->fetch_assoc();
                    $info_stmt->close();

                    if ($info && $info["user_id"]) {
                        if ($status === "approved") {
                            $nt2 = "Appointment approved";
                            $msg = "Your appointment with {$info["doctor_name"]} on {$info["appointment_date"]} at {$info["appointment_time"]} has been approved.";
                        } else {
                            $nt2 = "Appointment rejected";
                            $msg = "Unfortunately, your appointment with {$info["doctor_name"]} on {$info["appointment_date"]} at {$info["appointment_time"]} was rejected.";
                        }
                        $notify = $conn->prepare(
                            "INSERT INTO user_notifications (user_id, title, message, type, related_id)
                             VALUES (?, ?, ?, 'appointment', ?)"
                        );
                        $ntype = 'appointment';
                        $notify->bind_param("issi", $info["user_id"], $nt2, $msg, $appointment_id);
                        $notify->execute();
                    }
                }
            } else {
                $error = "Could not update appointment.";
            }

            $update_stmt->close();
        }
    }
}


/* ==========================================
   GET APPOINTMENTS
========================================== */

$sql = "
    SELECT *
    FROM appointments
    WHERE hospital_id = ?
    ORDER BY appointment_date DESC
";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $error = "Database query error: " . $conn->error;
    $appointments = null;
} else {
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $appointments = $stmt->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointments | MediConnect</title>

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

/* ===============================
   HEADER
================================ */

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
}

.page-header h1{
    font-size:32px;
}

.page-header .subtitle{
    color:#8b95a5;
    margin-top:5px;
}

/* ===============================
   ALERTS
================================ */

.message{
    background:rgba(0,200,120,0.12);
    border:1px solid rgba(0,200,120,0.3);
    color:#66e6aa;
    padding:15px;
    border-radius:12px;
    margin-bottom:20px;
}

.error{
    background:rgba(255,70,70,0.12);
    border:1px solid rgba(255,70,70,0.3);
    color:#ff7777;
    padding:15px;
    border-radius:12px;
    margin-bottom:20px;
}

/* ===============================
   CARD
================================ */

.card{
    background:#171c26;
    padding:25px;
    border-radius:16px;
    border:1px solid #262d3d;
}

/* ===============================
   TABLE
================================ */

.table-wrapper{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:800px;
}

th,td{
    padding:15px;
    border-bottom:1px solid #262d3d;
    text-align:left;
}

th{
    color:#8b95a5;
    font-size:13px;
}

td{
    font-size:14px;
}

/* ===============================
   STATUS
================================ */

.status{
    padding:6px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
    display:inline-block;
}

.pending{
    background:rgba(255,170,0,0.15);
    color:#ffb14a;
}

.approved{
    background:rgba(0,200,120,0.15);
    color:#66e6aa;
}

.rejected{
    background:rgba(255,70,70,0.15);
    color:#ff7777;
}

/* ===============================
   ACTION BUTTONS
================================ */

.action-form{
    display:flex;
    gap:8px;
}

.approve-btn{
    border:none;
    padding:8px 12px;
    border-radius:8px;
    background:#1d8cf8;
    color:white;
    font-size:12px;
    cursor:pointer;
}

.approve-btn:hover{
    background:#1572cd;
}

.reject-btn{
    border:none;
    padding:8px 12px;
    border-radius:8px;
    background:rgba(255,70,70,0.15);
    color:#ff7777;
    font-size:12px;
    cursor:pointer;
}

.reject-btn:hover{
    background:rgba(255,70,70,0.25);
}

/* ===============================
   EMPTY
================================ */

.empty{
    text-align:center;
    padding:35px;
    color:#8b95a5;
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

<!-- ===============================
     SIDEBAR
================================ -->

<div class="sidebar">

<div class="logo" style="display:flex;align-items:center;gap:10px;"><img src="../assets/logo.png" alt="MediConnect" style="height:44px;width:auto;max-width:100%;"><span class="bname" style="color:#f5f7fa;">MediConnect<span style="color:#1d8cf8;">.</span></span></div>

<a href="dashboard.php" data-icon="📊">Dashboard</a>
<a href="doctors.php" data-icon="👨‍⚕️">Doctors</a>
<a href="departments.php" data-icon="🏛️">Departments</a>
<a href="appointments.php" class="active" data-icon="📅">Appointments</a>
<a href="availability.php" data-icon="⏰">Availability</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- ===============================
     MAIN CONTENT
================================ -->

<div class="main-content">

<div class="page-header">

<div>
<h1>Appointments</h1>
<p class="subtitle">Manage and approve patient appointment requests.</p>
</div>

<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>

</div>

<?php if (!empty($message)): ?>
<div class="message"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">

<div class="table-wrapper">

<table>

<thead>
<tr>
<th>Patient</th>
<th>Doctor</th>
<th>Date</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php if ($appointments !== null && $appointments->num_rows > 0): ?>

<?php while ($appointment = $appointments->fetch_assoc()): ?>

<tr>

<td><?php echo htmlspecialchars($appointment["patient_name"] ?? "Patient"); ?></td>

<td><?php echo htmlspecialchars($appointment["doctor_name"] ?? "Doctor"); ?></td>

<td><?php echo htmlspecialchars($appointment["appointment_date"]); ?></td>

<td>
<?php
$status = strtolower($appointment["status"] ?? "pending");
?>
<span class="status <?php echo htmlspecialchars($status); ?>">
<?php echo ucfirst(htmlspecialchars($status)); ?>
</span>
</td>

<td>

<?php if ($status === "pending"): ?>

<div class="action-form">

<!-- APPROVE -->
<form method="POST">
<input type="hidden" name="update_appointment" value="1">
<input type="hidden" name="appointment_id" value="<?php echo (int)$appointment["id"]; ?>">
<input type="hidden" name="status" value="approved">
<button type="submit" class="approve-btn" onclick="return confirm('Approve this appointment?');">
Approve
</button>
</form>

<!-- REJECT -->
<form method="POST">
<input type="hidden" name="update_appointment" value="1">
<input type="hidden" name="appointment_id" value="<?php echo (int)$appointment["id"]; ?>">
<input type="hidden" name="status" value="rejected">
<button type="submit" class="reject-btn" onclick="return confirm('Reject this appointment?');">
Reject
</button>
</form>

</div>

<?php else: ?>

<span style="color:#8b95a5; font-size:12px;">Updated</span>

<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>
<td colspan="5" class="empty">No appointment requests found.</td>
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