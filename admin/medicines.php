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


$message = "";

$error = "";

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


/* ADD MEDICINE */

if (
    isset($_POST["action"]) &&
    $_POST["action"] === "add"
) {

    $medicine_name =
        trim($_POST["medicine_name"] ?? "");

    $generic_name =
        trim($_POST["generic_name"] ?? "");

    $category =
        trim($_POST["category"] ?? "");

    $description =
        trim($_POST["description"] ?? "");


    if (empty($medicine_name)) {

        $error =
            "Medicine name is required.";

    } else {

        $stmt =
            $conn->prepare(

                "INSERT INTO medicines
                (
                    medicine_name,
                    generic_name,
                    category,
                    description
                )
                VALUES (?, ?, ?, ?)"

            );


        $stmt->bind_param(

            "ssss",

            $medicine_name,
            $generic_name,
            $category,
            $description

        );


        if ($stmt->execute()) {

            $message =
                "Medicine added successfully.";

        } else {

            $error =
                "Failed to add medicine.";

        }

    }

}


/* DELETE MEDICINE */

if (isset($_GET["delete"])) {

    $id =
        (int) $_GET["delete"];


    $stmt =
        $conn->prepare(

            "DELETE FROM medicines
            WHERE id = ?"

        );


    $stmt->bind_param(
        "i",
        $id
    );


    if ($stmt->execute()) {

        $message =
            "Medicine deleted successfully.";

    }

}


/* SEARCH */

$search =
    trim($_GET["search"] ?? "");


if (!empty($search)) {

    $stmt =
        $conn->prepare(

            "SELECT *
            FROM medicines

            WHERE
                medicine_name LIKE ?
                OR generic_name LIKE ?
                OR category LIKE ?

            ORDER BY id DESC"

        );


    $term =
        "%$search%";


    $stmt->bind_param(

        "sss",

        $term,
        $term,
        $term

    );


    $stmt->execute();


    $medicines =
        $stmt->get_result();

} else {

    $medicines =
        $conn->query(

            "SELECT *
            FROM medicines
            ORDER BY id DESC"

        );

}

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

Medicines | MediConnect Admin

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

    top:
    0;

    left:
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

    background:
    rgba(239,68,68,.08);

    color:
    #ff6b6b;

    text-decoration:
    none;

    border-radius:
    10px;

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
    30px;

}


.header h1 {

    font-size:
    28px;

}


.header p {

    color:
    #8b95a5;

    margin-top:
    6px;

    font-size:
    14px;

}


/* CARD */

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

    margin-bottom:
    25px;

}


/* FORM */

.form-grid {

    display:
    grid;

    grid-template-columns:
    repeat(2, 1fr);

    gap:
    15px;

}


.full {

    grid-column:
    span 2;

}


.input-group {

    display:
    flex;

    flex-direction:
    column;

}


.input-group label {

    color:
    #8b95a5;

    font-size:
    12px;

    margin-bottom:
    8px;

}


.input-group input,

.input-group textarea {

    background:
    #212836;

    border:
    1px solid
    #262d3d;

    color:
    white;

    padding:
    13px;

    border-radius:
    10px;

    outline:
    none;

}


.input-group textarea {

    resize:
    vertical;

    min-height:
    90px;

}


.input-group input:focus,

.input-group textarea:focus {

    border-color:
    #1d8cf8;

}


.add-btn {

    margin-top:
    20px;

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

    font-weight:
    600;

    cursor:
    pointer;

}


/* ALERT */

.success {

    background:
    rgba(34,197,94,.12);

    color:
    #4ade80;

    padding:
    13px;

    border-radius:
    10px;

    margin-bottom:
    20px;

}


.error {

    background:
    rgba(239,68,68,.12);

    color:
    #f87171;

    padding:
    13px;

    border-radius:
    10px;

    margin-bottom:
    20px;

}


/* SEARCH */

.toolbar {

    display:
    flex;

    justify-content:
    space-between;

    margin-bottom:
    20px;

}


.search-form {

    display:
    flex;

    width:
    400px;

    max-width:
    100%;

}


.search-form input {

    flex:
    1;

    background:
    #212836;

    border:
    1px solid
    #262d3d;

    color:
    white;

    padding:
    12px;

    border-radius:
    10px 0 0 10px;

    outline:
    none;

}


.search-form button {

    background:
    #1d8cf8;

    color:
    white;

    border:
    none;

    padding:
    0 20px;

    border-radius:
    0 10px 10px 0;

    cursor:
    pointer;

}


/* TABLE */

.table-wrap {

    overflow-x:
    auto;

}


table {

    width:
    100%;

    border-collapse:
    collapse;

}


th {

    text-align:
    left;

    padding:
    15px;

    color:
    #8b95a5;

    font-size:
    12px;

    border-bottom:
    1px solid
    #262d3d;

}


td {

    padding:
    16px 15px;

    border-bottom:
    1px solid
    #262d3d;

    font-size:
    14px;

}


.delete-btn {

    color:
    #f87171;

    background:
    rgba(239,68,68,.1);

    padding:
    8px 12px;

    border-radius:
    8px;

    text-decoration:
    none;

    font-size:
    12px;

}


/* MOBILE */

@media(max-width:850px) {

    .form-grid {

        grid-template-columns:
        1fr;

    }

    .full {

        grid-column:
        span 1;

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

        font-size:
        25px;

        color:
        #1d8cf8;

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


<a
href="medicines.php"
class="active"
>

💉 Medicines

</a>


<a href="reports.php">

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

Medicines

</h1>

<p>

Add and manage medicine information.

</p>
</div>

<?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>

</div>



<?php if (!empty($message)): ?>

<div class="success">

<?php echo htmlspecialchars($message); ?>

</div>

<?php endif; ?>


<?php if (!empty($error)): ?>

<div class="error">

<?php echo htmlspecialchars($error); ?>

</div>

<?php endif; ?>



<!-- ADD MEDICINE -->

<div class="card">


<h3
style="
margin-bottom:20px;
"
>

Add New Medicine

</h3>


<form method="POST">


<input
type="hidden"
name="action"
value="add"
>


<div class="form-grid">


<div class="input-group">

<label>

Medicine Name *

</label>

<input
type="text"
name="medicine_name"
placeholder="Enter medicine name"
required
>

</div>



<div class="input-group">

<label>

Generic Name

</label>

<input
type="text"
name="generic_name"
placeholder="Enter generic name"
>

</div>



<div class="input-group">

<label>

Category

</label>

<input
type="text"
name="category"
placeholder="Example: Antibiotic"
>

</div>



<div class="input-group full">

<label>

Description

</label>

<textarea
name="description"
placeholder="Enter medicine description"
></textarea>

</div>


</div>


<button
type="submit"
class="add-btn"
>

+ Add Medicine

</button>


</form>


</div>



<!-- MEDICINE LIST -->

<div class="card">


<div class="toolbar">

<form
method="GET"
class="search-form"
>

<input
type="text"
name="search"
value="<?php echo htmlspecialchars($search); ?>"
placeholder="Search medicines..."
>

<button type="submit">

Search

</button>

</form>


</div>


<div class="table-wrap">


<table>


<thead>

<tr>

<th>ID</th>

<th>MEDICINE</th>

<th>GENERIC NAME</th>

<th>CATEGORY</th>

<th>CREATED</th>

<th>ACTION</th>

</tr>

</thead>


<tbody>


<?php if ($medicines && $medicines->num_rows > 0): ?>


<?php while ($medicine = $medicines->fetch_assoc()): ?>


<tr>


<td>

#<?php echo $medicine["id"]; ?>

</td>


<td>

<?php
echo htmlspecialchars(
$medicine["medicine_name"]
);
?>

</td>


<td>

<?php
echo htmlspecialchars(
$medicine["generic_name"] ?: "-"
);
?>

</td>


<td>

<?php
echo htmlspecialchars(
$medicine["category"] ?: "-"
);
?>

</td>


<td>

<?php
echo date(
"d M Y",
strtotime(
$medicine["created_at"]
)
);
?>

</td>


<td>

<a
class="delete-btn"
href="?delete=<?php echo $medicine["id"]; ?>"
onclick="return confirm('Delete this medicine?')"
>

Delete

</a>

</td>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td
colspan="6"
style="
text-align:center;
color:#8b95a5;
padding:30px;
"
>

No medicines found.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</div>


<script>


/* Auto hide messages */

setTimeout(

function() {

    const alerts =
        document.querySelectorAll(
            ".success, .error"
        );


    alerts.forEach(

        function(alert) {

            alert.style.transition =
                "0.5s";

            alert.style.opacity =
                "0";


            setTimeout(

                function() {

                    alert.remove();

                },

                500

            );

        }

    );

},

4000);


</script>

<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>

</html>