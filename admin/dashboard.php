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


$adminName =
    $_SESSION["user"]["name"]
    ?? "Administrator";

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


function getCount(
    $conn,
    $table,
    $condition = ""
) {

    $sql =
        "SELECT COUNT(*) AS total
         FROM $table";

    if ($condition !== "") {

        $sql .=
            " WHERE $condition";

    }


    $result =
        $conn->query($sql);


    if ($result) {

        return
            $result
            ->fetch_assoc()["total"];

    }


    return 0;

}


$totalUsers =
    getCount(
        $conn,
        "users"
    );


$totalHospitals =
    getCount(
        $conn,
        "hospitals"
    );


$totalPharmacies =
    getCount(
        $conn,
        "pharmacies"
    );


$totalMedicines =
    getCount(
        $conn,
        "medicines"
    );


$pendingHospitals =
    getCount(
        $conn,
        "hospitals",
        "status = 'pending'"
    );


$pendingPharmacies =
    getCount(
        $conn,
        "pharmacies",
        "status = 'pending'"
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

Admin Dashboard | MediConnect

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

left:
0;

top:
0;

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

transition:
.2s;

}


.menu a:hover {

background:
#1b2230;

color:
white;

}


.menu a.active {

background:
rgba(
29,
140,
248,
.12
);

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

border-radius:
10px;

background:
rgba(
239,
68,
68,
.08
);

color:
#ff6b6b;

text-decoration:
none;

text-align:
center;

}


/* MAIN */

.main {

margin-left:
260px;

padding:
35px;

}


/* HEADER */

.header {

display:
flex;

justify-content:
space-between;

align-items:
center;

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

margin-top:
7px;

font-size:
14px;

}


.profile {

display:
flex;

align-items:
center;

gap:
10px;

background:
#171c26;

border:
1px solid
#262d3d;

padding:
8px 15px;

border-radius:
12px;

}


.avatar {

width:
35px;

height:
35px;

border-radius:
50%;

background:
#1d8cf8;

display:
flex;

align-items:
center;

justify-content:
center;

}


/* STATS */

.stats {

display:
grid;

grid-template-columns:
repeat(
4,
1fr
);

gap:
20px;

}


.stat-card {

background:
#171c26;

border:
1px solid
#262d3d;

border-radius:
16px;

padding:
22px;

}


.stat-top {

display:
flex;

justify-content:
space-between;

align-items:
center;

}


.stat-card p {

color:
#8b95a5;

font-size:
13px;

}


.stat-card h2 {

font-size:
30px;

margin-top:
15px;

}


.stat-icon {

width:
42px;

height:
42px;

background:
rgba(
29,
140,
248,
.12
);

color:
#1d8cf8;

border-radius:
12px;

display:
flex;

align-items:
center;

justify-content:
center;

font-size:
20px;

}


/* BOTTOM GRID */

.content-grid {

margin-top:
25px;

display:
grid;

grid-template-columns:
2fr 1fr;

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
16px;

padding:
25px;

}


.card-title {

display:
flex;

justify-content:
space-between;

align-items:
center;

margin-bottom:
25px;

}


.card-title h3 {

font-size:
17px;

}


.card-title a {

color:
#1d8cf8;

font-size:
13px;

text-decoration:
none;

}


/* ACTIVITY */

.activity {

display:
flex;

flex-direction:
column;

gap:
18px;

}


.activity-item {

display:
flex;

gap:
12px;

align-items:
center;

padding-bottom:
15px;

border-bottom:
1px solid
#262d3d;

}


.activity-icon {

width:
38px;

height:
38px;

border-radius:
10px;

background:
#212836;

display:
flex;

align-items:
center;

justify-content:
center;

}


.activity-item h4 {

font-size:
13px;

margin-bottom:
4px;

}


.activity-item p {

font-size:
12px;

color:
#8b95a5;

}


/* PENDING */

.pending-item {

padding:
16px;

background:
#212836;

border-radius:
12px;

margin-bottom:
12px;

}


.pending-item p {

color:
#8b95a5;

font-size:
12px;

margin-top:
6px;

}


.pending-number {

font-size:
28px;

color:
#1d8cf8;

margin-top:
10px;

}


/* MOBILE */

@media(
max-width:
1100px
) {

.stats {

grid-template-columns:
repeat(
2,
1fr
);

}

.content-grid {

grid-template-columns:
1fr;

}

}


@media(
max-width:
750px
) {

.sidebar {

width:
70px;

padding:
20px
10px;

}

.logo {

font-size:
0;

}

.logo::after {

content:'';

font-size:
25px;

color:
#1d8cf8;

}

.menu a {

font-size:
0;

text-align:
center;

}

.menu a::first-letter {

font-size:
18px;

}

.main {

margin-left:
70px;

padding:
20px;

}

.stats {

grid-template-columns:
1fr;

}

.header {

flex-direction:
column;

align-items:
flex-start;

gap:
20px;

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


<a
href="dashboard.php"
class="active"
>

📊 Dashboard

</a>


<a
href="users.php"
>

👥 Users

</a>


<a
href="hospitals.php"
>

🏥 Hospitals

</a>


<a
href="pharmacies.php"
>

💊 Pharmacies

</a>


<a
href="medicines.php"
>

💉 Medicines

</a>


<a
href="reports.php"
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


<!-- HEADER -->

<div class="header">


<div>

<h1>

Dashboard

</h1>


<p>

Welcome back,

<?php
echo
htmlspecialchars(
$adminName
);
?>

</p>

</div>


<div class="profile">

<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>

</div>

</div>



<!-- STATISTICS -->

<div class="stats">


<div class="stat-card">

<div class="stat-top">

<p>

Total Users

</p>


<div class="stat-icon">

👥

</div>

</div>


<h2>

<?php
echo
$totalUsers;
?>

</h2>

</div>



<div class="stat-card">

<div class="stat-top">

<p>

Hospitals

</p>


<div class="stat-icon">

🏥

</div>

</div>


<h2>

<?php
echo
$totalHospitals;
?>

</h2>

</div>



<div class="stat-card">

<div class="stat-top">

<p>

Pharmacies

</p>


<div class="stat-icon">

💊

</div>

</div>


<h2>

<?php
echo
$totalPharmacies;
?>

</h2>

</div>



<div class="stat-card">

<div class="stat-top">

<p>

Medicines

</p>


<div class="stat-icon">

💉

</div>

</div>


<h2>

<?php
echo
$totalMedicines;
?>

</h2>

</div>


</div>



<!-- CONTENT -->

<div class="content-grid">


<!-- ACTIVITY -->

<div class="card">


<div class="card-title">

<h3>

System Overview

</h3>


<a href="reports.php">

View Reports →

</a>

</div>


<div class="activity">


<div class="activity-item">

<div class="activity-icon">

👥

</div>


<div>

<h4>

User Management

</h4>

<p>

Manage registered MediConnect users.

</p>

</div>

</div>



<div class="activity-item">

<div class="activity-icon">

🏥

</div>


<div>

<h4>

Hospital Management

</h4>

<p>

Approve and manage hospital accounts.

</p>

</div>

</div>



<div class="activity-item">

<div class="activity-icon">

💊

</div>


<div>

<h4>

Pharmacy Management

</h4>

<p>

Monitor registered pharmacies.

</p>

</div>

</div>



<div class="activity-item">

<div class="activity-icon">

💉

</div>


<div>

<h4>

Medicine Management

</h4>

<p>

Manage medicine information.

</p>

</div>

</div>


</div>


</div>



<!-- PENDING -->

<div class="card">


<div class="card-title">

<h3>

Pending Requests

</h3>

</div>


<div class="pending-item">

<strong>

Hospitals

</strong>


<p>

Waiting for approval

</p>


<div class="pending-number">

<?php
echo
$pendingHospitals;
?>

</div>

</div>



<div class="pending-item">

<strong>

Pharmacies

</strong>


<p>

Waiting for approval

</p>


<div class="pending-number">

<?php
echo
$pendingPharmacies;
?>

</div>

</div>


</div>


</div>


</div>


<script>


/* SIMPLE PAGE ANIMATION */

document.addEventListener(

"DOMContentLoaded",

function() {

document.querySelectorAll(

".stat-card, .card"

).forEach(

function(element, index) {

element.style.opacity = "0";

element.style.transform =
"translateY(15px)";


setTimeout(

function() {

element.style.transition =
"0.4s";

element.style.opacity =
"1";

element.style.transform =
"translateY(0)";

},

index * 80

);

}

);

}

);


</script>

<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>

</html>