<?php

session_start();

require_once "../config/database.php";


if (
    empty($_SESSION["user"]) ||
    ($_SESSION["user"]["role"] ?? "") !== "admin"
) {

    header("Location: login.php");

    exit;

}


require_once "../partials/_right.php";
$rightCfg = [
    "ntable"             => "admin_notifications",
    "recipient_col"      => "admin_id",
    "recipient_id"       => (int)($_SESSION["user"]["id"] ?? 1),
    "profile_table"      => "admins",
    "profile_id_field"   => "id",
    "profile_label_field"=> "name",
    "profile_email_field"=> "email",
    "session_label_key"  => "name",
    "profile_title"      => "Admin Profile",
    "profile_fields"     => [
        ["name" => "name",  "label" => "Name"],
    ],
];
right_handle($conn, $rightCfg);
$rightUnread = right_unread($conn, $rightCfg);


/* FUNCTION */

function getCount(
    $conn,
    $query
) {

    $result =
        $conn->query(
            $query
        );


    if ($result) {

        $data =
            $result->fetch_assoc();


        return
            $data["total"]
            ?? 0;

    }


    return 0;

}


/* USERS */

$totalUsers =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM users"

    );


$activeUsers =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM users
        WHERE status='active'"

    );


$inactiveUsers =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM users
        WHERE status='inactive'"

    );


/* HOSPITALS */

$totalHospitals =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM hospitals"

    );


$approvedHospitals =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM hospitals
        WHERE status='approved'"

    );


$pendingHospitals =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM hospitals
        WHERE status='pending'"

    );


/* PHARMACIES */

$totalPharmacies =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM pharmacies"

    );


$approvedPharmacies =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM pharmacies
        WHERE status='approved'"

    );


$pendingPharmacies =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM pharmacies
        WHERE status='pending'"

    );


/* MEDICINES */

$totalMedicines =
    getCount(

        $conn,

        "SELECT COUNT(*) AS total
        FROM medicines"

    );

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0"
>

<title>

Reports | MediConnect Admin

</title>


<link
href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
rel="stylesheet"
>


<style>


* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

    font-family:
    'Plus Jakarta Sans',
    sans-serif;

}


body {

    background:
    #0f1319;

    color:
    white;

}


/* SIDEBAR */

.sidebar {

    position:
    fixed;

    width:
    260px;

    height:
    100vh;

    background:
    #121720;

    border-right:
    1px solid
    #262d3d;

    padding:
    28px 18px;

    display:
    flex;

    flex-direction:
    column;

}


.logo {

    font-size:
    20px;

    font-weight:
    700;

    margin-bottom:
    45px;

}


.logo span {

    color:
    #1d8cf8;

}


.menu {

    display:
    flex;

    flex-direction:
    column;

    gap:
    7px;

}


.menu a {

    padding:
    13px 15px;

    border-radius:
    10px;

    color:
    #8b95a5;

    text-decoration:
    none;

    font-size:
    14px;

}


.menu a:hover,

.menu a.active {

    background:
    rgba(29,140,248,.12);

    color:
    #1d8cf8;

}


.logout {

    margin-top:
    auto;

}


.logout a {

    display:
    block;

    padding:
    13px;

    text-align:
    center;

    border-radius:
    10px;

    background:
    rgba(239,68,68,.08);

    color:
    #ff6b6b;

    text-decoration:
    none;

}


/* MAIN */

.main {

    margin-left:
    260px;

    padding:
    35px;

}


.header {

    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-bottom:
    35px;

}


.header h1 {

    font-size:
    28px;

}


.header p {

    color:
    #8b95a5;

    font-size:
    14px;

    margin-top:
    7px;

}


/* OVERVIEW */

.report-grid {

    display:
    grid;

    grid-template-columns:
    repeat(4,1fr);

    gap:
    20px;

    margin-bottom:
    25px;

}


.report-card {

    background:
    #171c26;

    border:
    1px solid
    #262d3d;

    padding:
    22px;

    border-radius:
    16px;

}


.report-card p {

    color:
    #8b95a5;

    font-size:
    13px;

}


.report-card h2 {

    margin-top:
    12px;

    font-size:
    32px;

}


/* DETAILED CARDS */

.details-grid {

    display:
    grid;

    grid-template-columns:
    repeat(2,1fr);

    gap:
    20px;

}


.card {

    background:
    #171c26;

    border:
    1px solid
    #262d3d;

    border-radius:
    18px;

    padding:
    25px;

}


.card h3 {

    margin-bottom:
    25px;

}


.row {

    display:
    flex;

    justify-content:
    space-between;

    align-items:
    center;

    padding:
    15px 0;

    border-bottom:
    1px solid
    #262d3d;

}


.row:last-child {

    border-bottom:
    none;

}


.row p {

    color:
    #8b95a5;

    font-size:
    14px;

}


.number {

    font-size:
    20px;

    font-weight:
    700;

    color:
    #1d8cf8;

}


.status {

    padding:
    5px 10px;

    border-radius:
    20px;

    font-size:
    11px;

}


.green {

    background:
    rgba(34,197,94,.12);

    color:
    #4ade80;

}


.yellow {

    background:
    rgba(245,158,11,.12);

    color:
    #fbbf24;

}


.red {

    background:
    rgba(239,68,68,.12);

    color:
    #f87171;

}


/* EXPORT BUTTON */

.export {

    margin-top:
    25px;

    background:
    #1d8cf8;

    color:
    white;

    border:
    none;

    padding:
    13px 20px;

    border-radius:
    10px;

    cursor:
    pointer;

    font-weight:
    600;

}


/* MOBILE */

@media(max-width:1100px) {

    .report-grid {

        grid-template-columns:
        repeat(2,1fr);

    }

}


@media(max-width:750px) {

    .sidebar {

        width:
        70px;

        padding:
        20px 8px;

    }

    .logo {

        font-size:
        0;

    }

    .logo::after {

        content:'';

        color:
        #1d8cf8;

        font-size:
        25px;

    }

    .menu a {

        font-size:
        0;

    }

    .main {

        margin-left:
        70px;

        padding:
        20px;

    }

    .report-grid,

    .details-grid {

        grid-template-columns:
        1fr;

    }

}


.bname{font-size:17px;white-space:nowrap;}@media(max-width:768px){.bname{font-size:0 !important;}}
</style>
<?php require_once "../partials/_theme.php"; ?>

</head>


<body>


<!-- SIDEBAR -->

<div class="sidebar">


<div class="logo" style="display:flex;align-items:center;gap:10px;"><img src="../assets/logo.png" alt="MediConnect" style="height:48px;width:auto;max-width:100%;"><span class="bname" style="color:#f5f7fa;">MediConnect<span style="color:#1d8cf8;">.</span></span></div>


<div class="menu">


<a href="dashboard.php">

📊 Dashboard

</a>


<a href="users.php">

👥 Users

</a>


<a href="hospitals.php">

🏥 Hospitals

</a>


<a href="pharmacies.php">

💊 Pharmacies

</a>


<a href="medicines.php">

💉 Medicines

</a>


<a
href="reports.php"
class="active"
>

📈 Reports

</a>


</div>


<div class="logout">

<a href="../logout.php">

🚪 Logout

</a>

</div>


</div>



<!-- MAIN -->

<div class="main">


<div class="header">

<div>
<h1>

Reports & Analytics

</h1>


<p>

Overview of MediConnect platform statistics.

</p>
</div>

<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>

</div>



<!-- MAIN REPORT CARDS -->

<div class="report-grid">


<div class="report-card">

<p>

Total Users

</p>

<h2>

<?php
echo $totalUsers;
?>

</h2>

</div>



<div class="report-card">

<p>

Hospitals

</p>

<h2>

<?php
echo $totalHospitals;
?>

</h2>

</div>



<div class="report-card">

<p>

Pharmacies

</p>

<h2>

<?php
echo $totalPharmacies;
?>

</h2>

</div>



<div class="report-card">

<p>

Medicines

</p>

<h2>

<?php
echo $totalMedicines;
?>

</h2>

</div>


</div>



<!-- DETAILS -->

<div class="details-grid">


<!-- USERS -->

<div class="card">


<h3>

👥 User Statistics

</h3>


<div class="row">

<p>

Total Users

</p>

<div class="number">

<?php
echo $totalUsers;
?>

</div>

</div>


<div class="row">

<p>

Active Users

</p>

<div class="status green">

<?php
echo $activeUsers;
?>

</div>

</div>


<div class="row">

<p>

Inactive Users

</p>

<div class="status red">

<?php
echo $inactiveUsers;
?>

</div>

</div>


</div>



<!-- HOSPITAL -->

<div class="card">


<h3>

🏥 Hospital Statistics

</h3>


<div class="row">

<p>

Total Hospitals

</p>

<div class="number">

<?php
echo $totalHospitals;
?>

</div>

</div>


<div class="row">

<p>

Approved

</p>

<div class="status green">

<?php
echo $approvedHospitals;
?>

</div>

</div>


<div class="row">

<p>

Pending Approval

</p>

<div class="status yellow">

<?php
echo $pendingHospitals;
?>

</div>

</div>


</div>



<!-- PHARMACY -->

<div class="card">


<h3>

💊 Pharmacy Statistics

</h3>


<div class="row">

<p>

Total Pharmacies

</p>

<div class="number">

<?php
echo $totalPharmacies;
?>

</div>

</div>


<div class="row">

<p>

Approved

</p>

<div class="status green">

<?php
echo $approvedPharmacies;
?>

</div>

</div>


<div class="row">

<p>

Pending Approval

</p>

<div class="status yellow">

<?php
echo $pendingPharmacies;
?>

</div>

</div>


</div>



<!-- MEDICINE -->

<div class="card">


<h3>

💉 Medicine Statistics

</h3>


<div class="row">

<p>

Total Medicines

</p>

<div class="number">

<?php
echo $totalMedicines;
?>

</div>

</div>


<div class="row">

<p>

Platform Status

</p>

<div class="status green">

Active

</div>

</div>


</div>


</div>



<button
class="export"
onclick="printReport()"
>

🖨 Print Report

</button>


</div>



<script>


function printReport() {

    window.print();

}

</script>

<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>

</html>