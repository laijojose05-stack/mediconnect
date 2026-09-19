<?php
session_start();

if (!isset($_SESSION["hospital_id"])) {
    header("Location: login.php");
    exit();
}

require_once "../config/database.php";

$hospital_id = (int) $_SESSION["hospital_id"];

/* =====================================================
   AUTO-CLEANUP: DELETE PAST DOCTOR LEAVE / EXCEPTIONS
===================================================== */

$purge_exceptions_sql = "
    DELETE FROM doctor_exceptions
    WHERE hospital_id = ?
    AND exception_date < CURDATE()
";

$purge_exceptions_stmt = $conn->prepare($purge_exceptions_sql);

if ($purge_exceptions_stmt) {
    $purge_exceptions_stmt->bind_param("i", $hospital_id);
    $purge_exceptions_stmt->execute();
    $purge_exceptions_stmt->close();
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

$message = "";
$error = "";


/* =====================================================
   WEEKLY SCHEDULE - ADD
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_schedule"])
) {

    $doctor_id = (int) ($_POST["doctor_id"] ?? 0);
    $days = $_POST["days"] ?? [];
    $start_time = $_POST["start_time"] ?? "";
    $end_time = $_POST["end_time"] ?? "";

    if ($doctor_id <= 0) {
        $error = "Please select a doctor.";
    } elseif (empty($days)) {
        $error = "Please select at least one working day.";
    } elseif (empty($start_time) || empty($end_time)) {
        $error = "Please select working hours.";
    } elseif ($start_time >= $end_time) {
        $error = "End time must be later than start time.";
    } else {

        $check_sql = "
            SELECT id
            FROM doctors
            WHERE id = ?
            AND hospital_id = ?
        ";

        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {
            $error = "Database Error: " . $conn->error;
        } else {

            $check_stmt->bind_param("ii", $doctor_id, $hospital_id);
            $check_stmt->execute();
            $doctor_result = $check_stmt->get_result();

            if ($doctor_result->num_rows === 0) {
                $error = "Selected doctor does not belong to your hospital.";
            } else {

                $success_count = 0;

                foreach ($days as $day) {
                    $day = trim($day);

                    $delete_sql = "
                        DELETE FROM doctor_schedules
                        WHERE doctor_id = ?
                        AND hospital_id = ?
                        AND day_of_week = ?
                    ";

                    $delete_stmt = $conn->prepare($delete_sql);

                    if ($delete_stmt) {
                        $delete_stmt->bind_param("iis", $doctor_id, $hospital_id, $day);
                        $delete_stmt->execute();
                        $delete_stmt->close();
                    }

                    $insert_sql = "
                        INSERT INTO doctor_schedules
                        (hospital_id, doctor_id, day_of_week, start_time, end_time, is_available)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ";

                    $insert_stmt = $conn->prepare($insert_sql);

                    if ($insert_stmt) {
                        $insert_stmt->bind_param("iisss", $hospital_id, $doctor_id, $day, $start_time, $end_time);

                        if ($insert_stmt->execute()) {
                            $success_count++;
                        }

                        $insert_stmt->close();
                    }
                }

                if ($success_count > 0) {
                    $message = "Weekly schedule saved successfully.";
                } else {
                    $error = "Could not save the schedule.";
                }
            }

            $check_stmt->close();
        }
    }
}


/* =====================================================
   ADD DOCTOR EXCEPTION / LEAVE
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["add_exception"])
) {

    $doctor_id = (int) ($_POST["exception_doctor_id"] ?? 0);
    $exception_date = $_POST["exception_date"] ?? "";
    $status = $_POST["exception_status"] ?? "unavailable";
    $reason = trim($_POST["reason"] ?? "");

    if ($doctor_id <= 0) {
        $error = "Please select a doctor.";
    } elseif (empty($exception_date)) {
        $error = "Please select a date.";
    } else {

        $verify_sql = "
            SELECT id
            FROM doctors
            WHERE id = ?
            AND hospital_id = ?
        ";

        $verify_stmt = $conn->prepare($verify_sql);

        if (!$verify_stmt) {
            $error = "Database Error: " . $conn->error;
        } else {

            $verify_stmt->bind_param("ii", $doctor_id, $hospital_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();

            if ($verify_result->num_rows === 0) {
                $error = "Invalid doctor selected.";
            } else {

                $remove_sql = "
                    DELETE FROM doctor_exceptions
                    WHERE doctor_id = ?
                    AND hospital_id = ?
                    AND exception_date = ?
                ";

                $remove_stmt = $conn->prepare($remove_sql);

                if ($remove_stmt) {
                    $remove_stmt->bind_param("iis", $doctor_id, $hospital_id, $exception_date);
                    $remove_stmt->execute();
                    $remove_stmt->close();
                }

                $exception_sql = "
                    INSERT INTO doctor_exceptions
                    (hospital_id, doctor_id, exception_date, status, reason)
                    VALUES (?, ?, ?, ?, ?)
                ";

                $exception_stmt = $conn->prepare($exception_sql);

                if (!$exception_stmt) {
                    $error = "Database Error: " . $conn->error;
                } else {
                    $exception_stmt->bind_param("iisss", $hospital_id, $doctor_id, $exception_date, $status, $reason);

                    if ($exception_stmt->execute()) {
                        $message = "Doctor exception added successfully.";
                    } else {
                        $error = "Could not save exception.";
                    }

                    $exception_stmt->close();
                }
            }

            $verify_stmt->close();
        }
    }
}


/* =====================================================
   DELETE WEEKLY SCHEDULE
===================================================== */

if (
    isset($_GET["delete_schedule"]) &&
    is_numeric($_GET["delete_schedule"])
) {

    $schedule_id = (int) $_GET["delete_schedule"];

    $delete_schedule_sql = "
        DELETE FROM doctor_schedules
        WHERE id = ?
        AND hospital_id = ?
    ";

    $delete_schedule_stmt = $conn->prepare($delete_schedule_sql);

    if ($delete_schedule_stmt) {
        $delete_schedule_stmt->bind_param("ii", $schedule_id, $hospital_id);
        $delete_schedule_stmt->execute();
        $delete_schedule_stmt->close();

        header("Location: availability.php?success=schedule_deleted");
        exit();
    }
}

/* =====================================================
   DELETE ALL SCHEDULES FOR A DOCTOR
===================================================== */

if (
    isset($_GET["delete_schedule_doctor"]) &&
    is_numeric($_GET["delete_schedule_doctor"])
) {

    $clear_doctor_id = (int) $_GET["delete_schedule_doctor"];

    $clear_schedule_sql = "
        DELETE FROM doctor_schedules
        WHERE doctor_id = ?
        AND hospital_id = ?
    ";

    $clear_schedule_stmt = $conn->prepare($clear_schedule_sql);

    if ($clear_schedule_stmt) {
        $clear_schedule_stmt->bind_param("ii", $clear_doctor_id, $hospital_id);
        $clear_schedule_stmt->execute();
        $clear_schedule_stmt->close();

        header("Location: availability.php?success=schedule_deleted");
        exit();
    }
}

/* =====================================================
   DELETE SELECTED SCHEDULE ROWS (single chip removal)
===================================================== */

if (
    isset($_GET["delete_schedule_batch"]) &&
    $_GET["delete_schedule_batch"] !== ""
) {

    $batch_ids = array_filter(
        array_map("intval", explode(",", $_GET["delete_schedule_batch"])),
        function ($id) { return $id > 0; }
    );

    if (count($batch_ids) > 0) {

        $placeholders = implode(",", array_fill(0, count($batch_ids), "?"));

        $batch_sql = "
            DELETE FROM doctor_schedules
            WHERE id IN ($placeholders)
            AND hospital_id = ?
        ";

        $batch_stmt = $conn->prepare($batch_sql);

        if ($batch_stmt) {
            $types = str_repeat("i", count($batch_ids)) . "i";
            $params = array_merge($batch_ids, [$hospital_id]);
            $batch_stmt->bind_param($types, ...$params);
            $batch_stmt->execute();
            $batch_stmt->close();

            header("Location: availability.php?success=schedule_deleted");
            exit();
        }
    }
}


/* =====================================================
   DELETE EXCEPTION
===================================================== */

if (
    isset($_GET["delete_exception"]) &&
    is_numeric($_GET["delete_exception"])
) {

    $exception_id = (int) $_GET["delete_exception"];

    $delete_exception_sql = "
        DELETE FROM doctor_exceptions
        WHERE id = ?
        AND hospital_id = ?
    ";

    $delete_exception_stmt = $conn->prepare($delete_exception_sql);

    if ($delete_exception_stmt) {
        $delete_exception_stmt->bind_param("ii", $exception_id, $hospital_id);
        $delete_exception_stmt->execute();
        $delete_exception_stmt->close();

        header("Location: availability.php?success=exception_deleted");
        exit();
    }
}


/* =====================================================
   SUCCESS MESSAGE
===================================================== */

if (isset($_GET["success"])) {
    if ($_GET["success"] === "schedule_deleted") {
        $message = "Schedule deleted successfully.";
    }

    if ($_GET["success"] === "exception_deleted") {
        $message = "Exception deleted successfully.";
    }
}


/* =====================================================
   GET HOSPITAL DOCTORS
===================================================== */

$doctor_sql = "
    SELECT id, doctor_name, specialization
    FROM doctors
    WHERE hospital_id = ?
    ORDER BY doctor_name ASC
";

$doctor_stmt = $conn->prepare($doctor_sql);

$doctors = [];

if ($doctor_stmt) {
    $doctor_stmt->bind_param("i", $hospital_id);
    $doctor_stmt->execute();
    $doctor_result = $doctor_stmt->get_result();

    while ($doctor = $doctor_result->fetch_assoc()) {
        $doctors[] = $doctor;
    }

    $doctor_stmt->close();
}


/* =====================================================
   GET WEEKLY SCHEDULES
===================================================== */

$schedule_sql = "
    SELECT
        ds.id,
        ds.doctor_id,
        ds.day_of_week,
        ds.start_time,
        ds.end_time,
        d.doctor_name,
        d.specialization

    FROM doctor_schedules ds

    INNER JOIN doctors d
        ON ds.doctor_id = d.id

    WHERE ds.hospital_id = ?

    ORDER BY
        d.doctor_name ASC,
        FIELD(
            ds.day_of_week,
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday'
        )
";

$schedule_stmt = $conn->prepare($schedule_sql);

$schedules = [];

if ($schedule_stmt) {
    $schedule_stmt->bind_param("i", $hospital_id);
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();

    while ($schedule = $schedule_result->fetch_assoc()) {
        $schedules[] = $schedule;
    }

    $schedule_stmt->close();
}

/* Group schedule rows by doctor so each doctor appears once (merged cells) */
$scheduleGroups = [];

foreach ($schedules as $schedule) {
    $did = (int)$schedule["doctor_id"];

    if (!isset($scheduleGroups[$did])) {
        $scheduleGroups[$did] = [
            "doctor_name"    => $schedule["doctor_name"],
            "specialization" => $schedule["specialization"],
            "rows"           => [],
        ];
    }

    $scheduleGroups[$did]["rows"][] = $schedule;
}

/* Build contiguous-day runs per doctor (rows already ordered by day of week) */
$dayIndex = ["Monday" => 0, "Tuesday" => 1, "Wednesday" => 2, "Thursday" => 3, "Friday" => 4, "Saturday" => 5, "Sunday" => 6];
$dayShort = ["Monday" => "Mon", "Tuesday" => "Tue", "Wednesday" => "Wed", "Thursday" => "Thu", "Friday" => "Fri", "Saturday" => "Sat", "Sunday" => "Sun"];

foreach ($scheduleGroups as $did => $g) {
    $scheduleGroups[$did]["runs"] = [];
    $runs = &$scheduleGroups[$did]["runs"];

    foreach ($g["rows"] as $s) {
        $d = $dayIndex[$s["day_of_week"]];
        $n = count($runs);

        if (
            $n > 0 &&
            $runs[$n - 1]["end_idx"] + 1 === $d &&
            $runs[$n - 1]["start_time"] === $s["start_time"] &&
            $runs[$n - 1]["end_time"] === $s["end_time"]
        ) {
            $runs[$n - 1]["end_idx"] = $d;
            $runs[$n - 1]["ids"][] = $s["id"];
        } else {
            $runs[] = [
                "start_idx"  => $d,
                "end_idx"    => $d,
                "start_time" => $s["start_time"],
                "end_time"   => $s["end_time"],
                "ids"        => [$s["id"]],
            ];
        }
    }
}
unset($runs);


/* =====================================================
   GET EXCEPTIONS
===================================================== */

$exception_list_sql = "
    SELECT
        de.id,
        de.exception_date,
        de.status,
        de.reason,
        d.doctor_name,
        d.specialization

    FROM doctor_exceptions de

    INNER JOIN doctors d
        ON de.doctor_id = d.id

    WHERE de.hospital_id = ?

    ORDER BY
        de.exception_date ASC
";

$exception_list_stmt = $conn->prepare($exception_list_sql);

$exceptions = [];

if ($exception_list_stmt) {
    $exception_list_stmt->bind_param("i", $hospital_id);
    $exception_list_stmt->execute();
    $exception_result = $exception_list_stmt->get_result();

    while ($exception = $exception_result->fetch_assoc()) {
        $exceptions[] = $exception;
    }

    $exception_list_stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Availability | MediConnect</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background:#0f1319;
    color:#ffffff;
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
    margin-bottom:30px;
}

.page-header h1{
    font-size:32px;
    margin-bottom:8px;
}

.page-header p{
    color:#8b95a5;
}

/* ===============================
   ALERTS
================================ */

.message,
.error{
    padding:15px 18px;
    border-radius:12px;
    margin-bottom:25px;
}

.message{
    background:rgba(0,200,120,0.12);
    border:1px solid rgba(0,200,120,0.3);
    color:#66e6aa;
}

.error{
    background:rgba(255,70,70,0.12);
    border:1px solid rgba(255,70,70,0.3);
    color:#ff7777;
}

/* ===============================
   TABS
================================ */

.tabs{
    display:flex;
    gap:10px;
    margin-bottom:25px;
    flex-wrap:wrap;
}

.tab-btn{
    border:none;
    background:#212836;
    color:#8b95a5;
    padding:12px 20px;
    border-radius:10px;
    cursor:pointer;
    font-size:14px;
}

.tab-btn.active{
    background:#1d8cf8;
    color:white;
}

.tab-content{
    display:none;
}

.tab-content.active{
    display:block;
}

/* ===============================
   GRID
================================ */

.grid{
    display:grid;
    grid-template-columns:1fr;
    gap:22px;
}

.grid > .card:first-child{
    max-width:680px;
}

.section-divider{
    display:flex;
    align-items:center;
    gap:12px;
    margin:30px 0 18px;
    color:#8b95a5;
    font-size:13px;
    font-weight:700;
    letter-spacing:0.05em;
    text-transform:uppercase;
}

.section-divider::after{
    content:"";
    flex:1;
    height:1px;
    background:#262d3d;
}

.show-sched-row{
    display:flex;
    justify-content:center;
    margin-top:22px;
}

.show-sched-btn{
    padding:12px 26px;
    border:1px dashed #3a4a63;
    border-radius:12px;
    background:transparent;
    color:#8b95a5;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition:border-color 0.2s ease, color 0.2s ease;
}

.show-sched-btn:hover{
    border-color:#1d8cf8;
    color:#1d8cf8;
}

/* ===============================
   CARD
================================ */

.card{
    background:#171c26;
    border:1px solid #262d3d;
    border-radius:18px;
    padding:25px;
}

.card h2{
    font-size:19px;
    margin-bottom:8px;
}

.card-subtitle{
    color:#8b95a5;
    font-size:13px;
    margin-bottom:25px;
}

/* ===============================
   FORM
================================ */

.form-group{
    margin-bottom:18px;
}

label{
    display:block;
    margin-bottom:8px;
    color:#b8c0cc;
    font-size:14px;
}

select,
input{
    width:100%;
    padding:13px;
    border-radius:10px;
    border:1px solid #30394a;
    background:#212836;
    color:white;
    outline:none;
    font-size:14px;
}

select:focus,
input:focus{
    border-color:#1d8cf8;
}

/* ===============================
   DAYS
================================ */

.days-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(110px,1fr));
    gap:10px;
}

.day-option{
    position:relative;
}

.day-option input{
    position:absolute;
    opacity:0;
}

.day-label{
    display:block;
    padding:12px;
    text-align:center;
    border-radius:10px;
    background:#212836;
    border:1px solid #30394a;
    cursor:pointer;
    color:#8b95a5;
    font-size:14px;
}

.day-option input:checked + .day-label{
    background:rgba(29,140,248,0.18);
    border-color:#1d8cf8;
    color:#1d8cf8;
}

/* ===============================
   TIME ROW
================================ */

.time-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}

/* ===============================
   BUTTON
================================ */

.submit-btn{
    width:100%;
    padding:14px;
    border:none;
    border-radius:12px;
    background:#1d8cf8;
    color:white;
    font-size:15px;
    font-weight:bold;
    cursor:pointer;
    margin-top:5px;
}

.submit-btn:hover{
    background:#1572cd;
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
}

th{
    text-align:left;
    color:#8b95a5;
    font-size:12px;
    padding:13px;
    border-bottom:1px solid #262d3d;
}

td{
    padding:14px 13px;
    border-bottom:1px solid #262d3d;
    font-size:14px;
}

.doctor-name{
    font-weight:bold;
}

.specialization{
    color:#8b95a5;
    font-size:12px;
    margin-top:4px;
}

/* ===============================
   BADGES
================================ */

.day-badge{
    display:inline-block;
    padding:5px 9px;
    border-radius:20px;
    background:rgba(29,140,248,0.12);
    color:#1d8cf8;
    font-size:12px;
}

.status-badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
}

.leave{
    background:rgba(255,150,0,0.15);
    color:#ffb14a;
}

.unavailable{
    background:rgba(255,70,70,0.15);
    color:#ff7777;
}

/* ===============================
   DELETE
================================ */

.delete-btn{
    color:#ff7777;
    text-decoration:none;
    font-size:13px;
    font-weight:bold;
}

.delete-btn:hover{
    text-decoration:underline;
}

.clear-link{
    display:inline-block;
    color:#ff7777;
    text-decoration:none;
    font-size:12px;
    margin-top:8px;
}

.clear-link:hover{
    text-decoration:underline;
}

.schedule-list{
    display:flex;
    flex-direction:column;
    gap:12px;
    margin-top:18px;
}

.schedule-row{
    display:flex;
    align-items:center;
    gap:20px;
    background:#171d2b;
    border:1px solid #262d3d;
    border-radius:10px;
    padding:16px 18px;
}

.doc-col{
    display:flex;
    align-items:center;
    gap:12px;
    width:235px;
    flex-shrink:0;
}

.doc-avatar{
    width:44px;
    height:44px;
    border-radius:50%;
    background:rgba(29,140,248,0.15);
    border:1px solid rgba(29,140,248,0.35);
    color:#1d8cf8;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:bold;
    font-size:15px;
    flex-shrink:0;
}

.doc-meta{
    min-width:0;
}

.chips-col{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    flex:1;
}

.chip{
    position:relative;
    background:rgba(29,140,248,0.10);
    border:1px solid rgba(29,140,248,0.25);
    border-radius:9px;
    padding:7px 13px;
    min-width:96px;
}

.chip-day{
    display:block;
    font-weight:bold;
    font-size:13px;
    color:#1d8cf8;
}

.chip-time{
    display:block;
    font-size:11px;
    color:#7fb8f7;
    margin-top:3px;
}

.chip-x{
    position:absolute;
    top:-9px;
    right:-9px;
    width:18px;
    height:18px;
    border-radius:50%;
    background:#ff7777;
    color:#ffffff;
    font-size:12px;
    line-height:18px;
    text-align:center;
    text-decoration:none;
    opacity:0;
    transition:opacity 0.15s ease;
}

.chip:hover .chip-x{
    opacity:1;
}

.chip-x:hover{
    background:#ff4444;
}

.action-col{
    flex-shrink:0;
}

.doc-picker{
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-top:8px;
}

.doc-card{
    position:relative;
    display:flex;
    align-items:center;
    gap:12px;
    padding:13px 16px;
    border:1px solid #262d3d;
    border-radius:10px;
    background:#171d2b;
    cursor:pointer;
    transition:border-color 0.15s ease, background 0.15s ease;
    width:100%;
}

.doc-card input{
    position:absolute;
    opacity:0;
    pointer-events:none;
}

.doc-card:hover{
    border-color:#3a4a63;
}

.doc-card:has(input:checked){
    border-color:#1d8cf8;
    background:rgba(29,140,248,0.08);
}

.doc-card input:checked ~ .doc-card-avatar{
    border-color:#1d8cf8;
    background:rgba(29,140,248,0.25);
}

.doc-card-avatar{
    width:38px;
    height:38px;
    border-radius:50%;
    background:rgba(29,140,248,0.12);
    border:1px solid rgba(29,140,248,0.30);
    color:#1d8cf8;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:bold;
    font-size:13px;
    flex-shrink:0;
}

.doc-card-meta{
    display:flex;
    flex-direction:column;
    min-width:0;
}

.doc-card-name{
    font-weight:bold;
    font-size:13px;
    color:#e5eaf1;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.doc-card-spec{
    font-size:11px;
    color:#8b95a5;
    margin-top:2px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.check{
    position:absolute;
    top:50%;
    right:14px;
    transform:translateY(-50%);
    width:19px;
    height:19px;
    border-radius:50%;
    background:#1d8cf8;
    color:#ffffff;
    font-size:11px;
    line-height:19px;
    text-align:center;
    opacity:0;
    transition:opacity 0.15s ease;
}

.doc-card:has(input:checked) .check{
    opacity:1;
}

.form-group select{
    width:100%;
    padding:13px;
    background:#212836;
    border:1px solid #30394a;
    border-radius:8px;
    color:#fff;
    font-size:14px;
    font-family:inherit;
    outline:none;
    cursor:pointer;
    margin-top:8px;
}

.form-group select:focus{
    border-color:#1d8cf8;
}

.form-group select option{
    background:#171c26;
    color:#fff;
}

/* ===============================
   EMPTY
================================ */

.empty{
    text-align:center;
    padding:40px;
    color:#8b95a5;
}

/* ===============================
   RESPONSIVE
================================ */

@media(max-width:900px){
    .grid{
        grid-template-columns:1fr;
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

    .main-content{
        margin-left:70px;
        padding:25px 20px;
    }

    .page-header h1{
        font-size:25px;
    }

    .days-grid{
        grid-template-columns:1fr;
    }

    .time-row{
        grid-template-columns:1fr;
    }

    .schedule-row{
        flex-direction:column;
        align-items:flex-start;
        gap:14px;
    }

    .doc-col{
        width:auto;
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
<a href="appointments.php" data-icon="📅">Appointments</a>
<a href="availability.php" class="active" data-icon="⏰">Availability</a>
<a href="logout.php" data-icon="🚪">Logout</a>

</div>

<!-- ===============================
     MAIN CONTENT
================================ -->

<div class="main-content">

<div class="page-header">
<h1>Doctor Availability</h1>
<p>Create weekly schedules and manage doctor leave days.</p>
<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
</div>

<?php if (!empty($message)): ?>
<div class="message"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- ===============================
     TABS
================================ -->

<div class="tabs">
<button class="tab-btn active" onclick="openTab('schedule', this)">Weekly Schedule</button>
<button class="tab-btn" onclick="openTab('exception', this)">Leave / Unavailable</button>
</div>

<!-- =================================================
     WEEKLY SCHEDULE TAB
================================================= -->

<div id="schedule" class="tab-content active">



<div class="grid">

<!-- FORM -->
<div class="card">
<h2>Set Weekly Schedule</h2>
<p class="card-subtitle">Select a doctor, working days and hours.</p>

<form method="POST">
<input type="hidden" name="save_schedule" value="1">

<div class="form-group">
<label>Select Doctor</label>
<select name="doctor_id" required>
<option value="" disabled selected>Choose a doctor</option>
<?php foreach ($doctors as $doctor): ?>
<?php
    $dn = ucwords(trim($doctor["doctor_name"]));
    $sp = ucwords(trim($doctor["specialization"]));
    $sp = $sp === "" ? "-" : $sp;
?>
<option value="<?php echo (int)$doctor["id"]; ?>"><?php echo htmlspecialchars($dn); ?> &mdash; <?php echo htmlspecialchars($sp); ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="form-group">
<label>Working Days</label>
<div class="days-grid">
<?php
$week_days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
foreach ($week_days as $day):
?>
<div class="day-option">
<input type="checkbox" id="<?php echo strtolower($day); ?>" name="days[]" value="<?php echo $day; ?>">
<label for="<?php echo strtolower($day); ?>" class="day-label"><?php echo $day; ?></label>
</div>
<?php endforeach; ?>
</div>
</div>

<div class="time-row">
<div class="form-group">
<label>Start Time</label>
<input type="time" name="start_time" value="09:00" required>
</div>
<div class="form-group">
<label>End Time</label>
<input type="time" name="end_time" value="17:00" required>
</div>
</div>

<button type="submit" class="submit-btn">Save Weekly Schedule</button>
</form>
</div>
</div>

<div class="show-sched-row">
<button type="button" class="show-sched-btn" id="toggleSchedBtn">📋 Show Current Weekly Schedules</button>
</div>

<!-- ===============================
     CURRENT WEEKLY SCHEDULES SECTION
================================ -->
<div class="section-divider" id="schedDivider" style="display:none;">Current Weekly Schedules</div>

<div class="card" id="schedSection" style="display:none;">
<h2>Current Weekly Schedules</h2>
<p class="card-subtitle">Saved doctor working hours.</p>

<?php if (count($schedules) > 0): ?>
<div class="schedule-list">
<?php foreach ($scheduleGroups as $g): ?>
<?php
    $dn = ucwords(trim($g["doctor_name"]));
    $sp = ucwords(trim($g["specialization"]));
    $sp = $sp === "" ? "-" : $sp;
    $initials = strtoupper(substr($dn, 0, 2));
    if (strpos($dn, " ") !== false) {
        $parts = explode(" ", $dn, 2);
        $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
?>
<div class="schedule-row">
<div class="doc-col">
<div class="doc-avatar"><?php echo htmlspecialchars($initials); ?></div>
<div class="doc-meta">
<div class="doctor-name"><?php echo htmlspecialchars($dn); ?></div>
<div class="specialization"><?php echo htmlspecialchars($sp); ?></div>
</div>
</div>

<div class="chips-col">
<?php foreach ($g["runs"] as $run): ?>
<?php
    $shortDays = array_values($dayShort);
    $dayLabel = $shortDays[$run["start_idx"]];
    if ($run["end_idx"] !== $run["start_idx"]) {
        $dayLabel .= "&ndash;" . $shortDays[$run["end_idx"]];
    }
    $batchLink = "availability.php?delete_schedule_batch=" . implode(",", $run["ids"]);
?>
<span class="chip">
<span class="chip-day"><?php echo $dayLabel; ?></span>
<span class="chip-time"><?php echo date("g:i A", strtotime($run["start_time"])) . "&ndash;" . date("g:i A", strtotime($run["end_time"])); ?></span>
<a class="chip-x" href="<?php echo $batchLink; ?>" onclick="return confirm('Delete this schedule block?');" title="Delete block">&times;</a>
</span>
<?php endforeach; ?>
</div>

<div class="action-col">
<a class="delete-btn" href="availability.php?delete_schedule_doctor=<?php echo (int)$g["rows"][0]["doctor_id"]; ?>" onclick="return confirm('Delete all schedules for this doctor?');">Clear all</a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="empty">No weekly schedules added yet.</p>
<?php endif; ?>

</div>
</div>

<!-- =================================================
     EXCEPTION TAB
================================================= -->

<div id="exception" class="tab-content">

<div class="grid">

<!-- EXCEPTION FORM -->
<div class="card">
<h2>Mark Doctor Unavailable</h2>
<p class="card-subtitle">Use this for leave or special unavailable dates.</p>

<form method="POST">
<input type="hidden" name="add_exception" value="1">

<div class="form-group">
<label>Select Doctor</label>
<select name="exception_doctor_id" required>
<option value="" disabled selected>Choose a doctor</option>
<?php foreach ($doctors as $doctor): ?>
<?php
    $dn = ucwords(trim($doctor["doctor_name"]));
    $sp = ucwords(trim($doctor["specialization"]));
    $sp = $sp === "" ? "-" : $sp;
?>
<option value="<?php echo (int)$doctor["id"]; ?>"><?php echo htmlspecialchars($dn); ?> &mdash; <?php echo htmlspecialchars($sp); ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="form-group">
<label>Date</label>
<input type="date" name="exception_date" min="<?php echo date("Y-m-d"); ?>" required>
</div>

<div class="form-group">
<label>Status</label>
<select name="exception_status">
<option value="unavailable">Unavailable</option>
<option value="leave">On Leave</option>
</select>
</div>

<div class="form-group">
<label>Reason</label>
<input type="text" name="reason" placeholder="Optional reason">
</div>

<button type="submit" class="submit-btn">Save Exception</button>
</form>
</div>

<!-- EXCEPTIONS LIST -->
<div class="card">
<h2>Upcoming Exceptions</h2>
<p class="card-subtitle">Doctor leave and unavailable dates.</p>

<div class="table-wrapper">
<table>
<thead>
<tr>
<th>Doctor</th>
<th>Date</th>
<th>Status</th>
<th>Reason</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php if (count($exceptions) > 0): ?>
<?php foreach ($exceptions as $exception): ?>
<tr>
<td>
<div class="doctor-name"><?php echo htmlspecialchars(ucwords(trim($exception["doctor_name"]))); ?></div>
<div class="specialization"><?php echo htmlspecialchars(ucwords(trim($exception["specialization"])) ?: "-"); ?></div>
</td>
<td><?php echo date("d M Y", strtotime($exception["exception_date"])); ?></td>
<td>
<span class="status-badge <?php echo htmlspecialchars($exception["status"]); ?>">
<?php echo ucfirst(htmlspecialchars($exception["status"])); ?>
</span>
</td>
<td><?php echo !empty($exception["reason"]) ? htmlspecialchars($exception["reason"]) : "-"; ?></td>
<td>
<a class="delete-btn" href="availability.php?delete_exception=<?php echo $exception["id"]; ?>" onclick="return confirm('Delete this exception?');">Delete</a>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="5" class="empty">No exceptions added yet.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</div>

</div>

<script>
function openTab(tabName, button){
    const tabs = document.querySelectorAll(".tab-content");
    tabs.forEach(function(tab){
        tab.classList.remove("active");
    });

    document.getElementById(tabName).classList.add("active");

    const buttons = document.querySelectorAll(".tab-btn");
    buttons.forEach(function(btn){
        btn.classList.remove("active");
    });

    button.classList.add("active");
}

(function(){
    var btn = document.getElementById("toggleSchedBtn");
    var divider = document.getElementById("schedDivider");
    var section = document.getElementById("schedSection");
    if(!btn || !divider || !section){ return; }
    btn.addEventListener("click", function(){
        var hidden = divider.style.display === "none";
        divider.style.display = hidden ? "flex" : "none";
        section.style.display = hidden ? "block" : "none";
        btn.innerHTML = hidden ? "▲ Hide Current Weekly Schedules" : "📋 Show Current Weekly Schedules";
        if(hidden){
            setTimeout(function(){
                section.scrollIntoView({behavior:"smooth", block:"start"});
            }, 60);
        }
    });
})();
</script>

<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>
</body>
</html>