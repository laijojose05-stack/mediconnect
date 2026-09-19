<?php
session_start();

require_once "../config/database.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $hospital_name = trim($_POST["hospital_name"] ?? "");
    $email         = trim($_POST["email"] ?? "");
    $password      = $_POST["password"] ?? "";
    $confirm       = $_POST["confirm_password"] ?? "";
    $phone         = trim($_POST["phone"] ?? "");
    $address       = trim($_POST["address"] ?? "");
    $city          = trim($_POST["city"] ?? "");
    $specialty     = trim($_POST["specialty"] ?? "");

    /* ==============================
       VALIDATION
    ============================== */

    if (
        empty($hospital_name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm) ||
        empty($phone) ||
        empty($address) ||
        empty($city) ||
        empty($specialty)
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    } elseif ($password !== $confirm) {

        $error = "Passwords do not match.";

    } else {

        /* ==============================
           CHECK EXISTING EMAIL
        ============================== */

        $check_sql = "
            SELECT id
            FROM hospitals
            WHERE email = ?
            LIMIT 1
        ";

        $check_stmt = $conn->prepare($check_sql);

        if ($check_stmt === false) {

            $error = "Database error: " . $conn->error;

        } else {

            $check_stmt->bind_param(
                "s",
                $email
            );

            $check_stmt->execute();

            $check_result =
                $check_stmt->get_result();

            if ($check_result->num_rows > 0) {

                $error =
                    "A hospital account with this email already exists.";

            } else {

                /* ==============================
                   HASH PASSWORD
                ============================== */

                $hashed_password =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                /* ==============================
                   INSERT HOSPITAL
                   status automatically becomes
                   'pending' from database default
                ============================== */

                $insert_sql = "
                    INSERT INTO hospitals
                    (
                        hospital_name,
                        email,
                        password,
                        phone,
                        address,
                        city,
                        specialty
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?)
                ";

                $insert_stmt =
                    $conn->prepare($insert_sql);


                if ($insert_stmt === false) {

                    $error =
                        "Database error: " .
                        $conn->error;

                } else {

                    $insert_stmt->bind_param(
                        "sssssss",
                        $hospital_name,
                        $email,
                        $hashed_password,
                        $phone,
                        $address,
                        $city,
                        $specialty
                    );


                    if ($insert_stmt->execute()) {

                        $newHospId = (int)$insert_stmt->insert_id;
                        $n = $conn->prepare(
                            "INSERT INTO admin_notifications (admin_id, title, message, type, related_id)
                             VALUES (1, 'New hospital pending approval', 'Hospital \"$hospital_name\" ($email) registered and is awaiting approval.', 'registration', ?)"
                        );
                        if ($n) {
                            $n->bind_param("i", $newHospId);
                            $n->execute();
                        }

                        $success =
                            "Hospital registration submitted successfully. " .
                            "Your account is waiting for admin approval.";

                        /*
                         * Clear form values after successful registration.
                         */
                        $hospital_name = "";
                        $email = "";
                        $phone = "";
                        $address = "";
                        $city = "";
                        $specialty = "";

                    } else {

                        $error =
                            "Registration failed: " .
                            $insert_stmt->error;
                    }

                    $insert_stmt->close();
                }
            }

            $check_stmt->close();
        }
    }
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
Register Hospital | MediConnect
</title>


<style>

/* =========================================
   RESET
========================================= */

*{
    margin:0;
    padding:0;
    box-sizing:border-box;

    font-family:Arial, sans-serif;
}


/* =========================================
   BODY
========================================= */

body{

    min-height:100vh;

    background:#0f1319;

    color:#ffffff;

    display:flex;

    justify-content:center;

    align-items:center;

    padding:30px 20px;
}


/* =========================================
   MAIN CARD
========================================= */

.app-card{

    width:100%;

    max-width:1050px;

    background:#171c26;

    border:1px solid #262d3d;

    border-radius:24px;

    overflow:hidden;

    display:flex;

    position:relative;

    box-shadow:
        0 25px 50px
        rgba(0,0,0,0.5);
}


/* =========================================
   NAVBAR
========================================= */

.navbar{

    position:absolute;

    top:0;
    left:0;

    width:100%;

    height:80px;

    padding:25px 45px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    z-index:10;
}


.nav-brand{

    display:flex;

    align-items:center;

    gap:10px;

    color:#ffffff;

    text-decoration:none;

    font-size:18px;

    font-weight:bold;
}


.brand-icon{

    width:30px;

    height:30px;

    background:transparent;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:21px;

    font-weight:bold;
}


.dot-blue{

    color:#1d8cf8;
}


.nav-links{

    display:flex;

    gap:25px;
}


.nav-links a{

    color:#8b95a5;

    text-decoration:none;

    font-size:14px;
}


.nav-links a:hover{

    color:#ffffff;
}


/* =========================================
   FORM SECTION
========================================= */

.form-section{

    width:58%;

    padding:
        115px
        50px
        45px;
}


.main-title{

    font-size:34px;

    margin-bottom:8px;
}


.tagline{

    color:#1d8cf8;

    font-size:17px;

    margin-bottom:10px;
}


.description{

    color:#8b95a5;

    font-size:13px;

    line-height:1.6;

    margin-bottom:25px;

    max-width:600px;
}


/* =========================================
   ALERT
========================================= */

.alert{

    padding:13px 15px;

    border-radius:10px;

    margin-bottom:18px;

    font-size:13px;

    line-height:1.5;
}


.error{

    background:
        rgba(255,70,70,0.12);

    border:
        1px solid
        rgba(255,70,70,0.3);

    color:#ff7777;
}


.success{

    background:
        rgba(0,200,120,0.12);

    border:
        1px solid
        rgba(0,200,120,0.3);

    color:#66e6aa;
}


/* =========================================
   FORM GRID
========================================= */

.form-grid{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:14px;
}


.full-width{

    grid-column:
        1 / -1;
}


/* =========================================
   INPUT BOX
========================================= */

.input-box{

    background:#212836;

    border:
        1px solid
        #30394a;

    border-radius:13px;

    padding:10px 15px;

    transition:0.2s;
}


.input-box:focus-within{

    border-color:#1d8cf8;

    box-shadow:
        0 0 0 1px
        #1d8cf8;
}


.input-box label{

    display:block;

    color:#8b95a5;

    font-size:10px;

    font-weight:bold;

    margin-bottom:5px;
}


.input-box input,
.input-box textarea,
.input-box select{

    width:100%;

    border:none;

    outline:none;

    background:transparent;

    color:#ffffff;

    font-size:14px;
}


.input-box input{

    height:24px;
}


.input-box textarea{

    resize:none;

    height:65px;

    padding-top:3px;
}


.input-box select{

    height:25px;

    cursor:pointer;
}


.input-box select option{

    background:#171c26;

    color:#ffffff;
}


/* =========================================
   PASSWORD STRENGTH
========================================= */

.password-info{

    margin-top:5px;

    color:#667085;

    font-size:10px;
}


/* =========================================
   BUTTON
========================================= */

.submit-btn{

    width:100%;

    border:none;

    border-radius:13px;

    padding:15px;

    margin-top:18px;

    background:#1d8cf8;

    color:#ffffff;

    font-size:15px;

    font-weight:bold;

    cursor:pointer;

    transition:0.2s;

    box-shadow:
        0 8px 20px
        rgba(29,140,248,0.25);
}


.submit-btn:hover{

    background:#1572cd;

    transform:translateY(-1px);
}


.submit-btn:disabled{

    opacity:0.6;

    cursor:not-allowed;

    transform:none;
}


/* =========================================
   LOGIN LINK
========================================= */

.login-text{

    text-align:center;

    margin-top:20px;

    color:#8b95a5;

    font-size:13px;
}


.login-text a{

    color:#1d8cf8;

    text-decoration:none;

    font-weight:bold;
}


/* =========================================
   RIGHT VISUAL
========================================= */

.bg-section{

    width:42%;

    min-height:680px;

    position:relative;

    background:

        linear-gradient(
            135deg,
            rgba(23,28,38,0.25),
            rgba(15,19,25,0.88)
        ),

        url(
            'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=1000&q=80'
        )

        center/cover
        no-repeat;

    display:flex;

    align-items:flex-end;

    padding:45px;
}


.bg-overlay{

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            to top,
            rgba(15,19,25,0.95),
            transparent 65%
        );
}


.bg-content{

    position:relative;

    z-index:2;
}


.bg-content h2{

    font-size:28px;

    margin-bottom:12px;
}


.bg-content p{

    color:#c5ccd8;

    font-size:13px;

    line-height:1.7;
}


.badge{

    display:inline-block;

    margin-top:18px;

    padding:8px 12px;

    border-radius:20px;

    background:
        rgba(29,140,248,0.15);

    border:
        1px solid
        rgba(29,140,248,0.35);

    color:#6db9ff;

    font-size:11px;

    font-weight:bold;
}


/* =========================================
   MOBILE
========================================= */

@media(max-width:850px){

    .app-card{

        flex-direction:column;

        max-width:600px;
    }


    .form-section{

        width:100%;

        padding:
            110px
            30px
            35px;
    }


    .bg-section{

        width:100%;

        min-height:220px;

        order:-1;

        padding:30px;
    }

}


@media(max-width:600px){

    body{

        padding:10px;
    }


    .navbar{

        padding:
            22px 25px;
    }


    .nav-links{

        display:none;
    }


    .form-grid{

        grid-template-columns:1fr;
    }


    .full-width{

        grid-column:auto;
    }


    .main-title{

        font-size:28px;
    }


    .form-section{

        padding:
            105px
            22px
            30px;
    }

}

</style>
<?php require_once "../partials/_theme.php"; ?>

</head>


<body>


<div class="app-card">


<!-- =====================================
     NAVBAR
===================================== -->

<header class="navbar">


<a
    href="../index.php"
    class="nav-brand"
>

<span class="brand-icon"><img src="../assets/logo.png" alt="MediConnect" style="width:100%;height:100%;object-fit:contain;"></span>

MediConnect

<span class="dot-blue">

.

</span>

</a>


<div class="nav-links">

<a href="../index.php">
Home
</a>

<a href="login.php">
Hospital Login
</a>

</div>


</header>


<!-- =====================================
     FORM
===================================== -->

<main class="form-section">


<h1 class="main-title">

Register Hospital<span class="dot-blue">.</span>

</h1>


<h2 class="tagline">

Join MediConnect Healthcare Network

</h2>


<p class="description">

Create your hospital account to manage
doctors, departments, appointments,
patients and healthcare services.

</p>


<!-- ERROR -->

<?php if (!empty($error)): ?>

<div class="alert error">

<?php

echo htmlspecialchars($error);

?>

</div>

<?php endif; ?>


<!-- SUCCESS -->

<?php if (!empty($success)): ?>

<div class="alert success">

<?php

echo htmlspecialchars($success);

?>

<br><br>

<a
    href="login.php"
    style="
        color:#6db9ff;
        text-decoration:none;
        font-weight:bold;
    "
>

Go to Hospital Login →

</a>

</div>

<?php endif; ?>


<form
    method="POST"
    id="registrationForm"
    autocomplete="off"
>


<div class="form-grid">


<!-- HOSPITAL NAME -->

<div class="input-box full-width">

<label for="hospital_name">

Hospital Name *

</label>

<input
    type="text"
    id="hospital_name"
    name="hospital_name"
    placeholder="Enter hospital name"
    value="<?php
        echo htmlspecialchars(
            $hospital_name ?? ""
        );
    ?>"
    required
>

</div>


<!-- EMAIL -->

<div class="input-box">

<label for="email">

Email Address *

</label>

<input
    type="email"
    id="email"
    name="email"
    placeholder="hospital@example.com"
    value="<?php
        echo htmlspecialchars(
            $email ?? ""
        );
    ?>"
    required
>

</div>


<!-- PHONE -->

<div class="input-box">

<label for="phone">

Phone Number *

</label>

<input
    type="tel"
    id="phone"
    name="phone"
    placeholder="Enter phone number"
    value="<?php
        echo htmlspecialchars(
            $phone ?? ""
        );
    ?>"
    required
>

</div>


<!-- PASSWORD -->

<div class="input-box">

<label for="password">

Password *

</label>

<input
    type="password"
    id="password"
    name="password"
    placeholder="Minimum 6 characters"
    minlength="6"
    required
>

</div>


<!-- CONFIRM PASSWORD -->

<div class="input-box">

<label for="confirm_password">

Confirm Password *

</label>

<input
    type="password"
    id="confirm_password"
    name="confirm_password"
    placeholder="Re-enter password"
    minlength="6"
    required
>

</div>


<!-- CITY -->

<div class="input-box">

<label for="city">

City *

</label>

<input
    type="text"
    id="city"
    name="city"
    placeholder="Enter city"
    value="<?php
        echo htmlspecialchars(
            $city ?? ""
        );
    ?>"
    required
>

</div>


<!-- SPECIALTY -->

<div class="input-box">

<label for="specialty">

Hospital Specialty *

</label>

<select
    id="specialty"
    name="specialty"
    required
>

<option value="">
Select specialty
</option>

<?php

$specialties = [

    "General Hospital",
    
    "superspecialty Hospital",

    " nursing Home",
    
    "Multi-Specialty Hospital",

    "Cardiology",

    "Neurology",

    "Orthopedics",

    "Pediatrics",

    "Gynecology",

    "Dermatology",

    "Oncology",

    "ENT",

    "Ophthalmology",

    "Other"

];

foreach ($specialties as $item):

?>

<option
    value="<?php
        echo htmlspecialchars($item);
    ?>"
    <?php
    if (
        ($specialty ?? "") === $item
    ) {
        echo "selected";
    }
    ?>
>

<?php

echo htmlspecialchars($item);

?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- ADDRESS -->

<div class="input-box full-width">

<label for="address">

Hospital Address *

</label>

<textarea
    id="address"
    name="address"
    placeholder="Enter complete hospital address"
    required
><?php

echo htmlspecialchars(
    $address ?? ""
);

?></textarea>

</div>


</div>


<button
    type="submit"
    class="submit-btn"
    id="submitBtn"
>

Submit Hospital Registration

</button>


</form>


<p class="login-text">

Already registered?

<a href="login.php">

Hospital Login

</a>

</p>


</main>


<!-- =====================================
     RIGHT SIDE
===================================== -->

<section class="bg-section">


<div class="bg-overlay"></div>


<div class="bg-content">


<h2>

Healthcare,
Connected.

</h2>


<p>

Register your hospital with MediConnect
and manage your healthcare services
through one connected platform.

</p>


<span class="badge">

ADMIN APPROVAL REQUIRED

</span>


</div>


</section>


</div>


<script>

/* =========================================
   PASSWORD MATCH VALIDATION
========================================= */

const form =
    document.getElementById(
        "registrationForm"
    );

const password =
    document.getElementById(
        "password"
    );

const confirmPassword =
    document.getElementById(
        "confirm_password"
    );

const submitButton =
    document.getElementById(
        "submitBtn"
    );


form.addEventListener(
    "submit",
    function(event){

        if (
            password.value !==
            confirmPassword.value
        ){

            event.preventDefault();

            alert(
                "Passwords do not match."
            );

            confirmPassword.focus();

            return;
        }


        if (
            password.value.length < 6
        ){

            event.preventDefault();

            alert(
                "Password must contain at least 6 characters."
            );

            password.focus();

            return;
        }


        submitButton.disabled = true;

        submitButton.textContent =
            "Creating Account...";
    }
);


/* =========================================
   PHONE VALIDATION
========================================= */

const phone =
    document.getElementById(
        "phone"
    );


phone.addEventListener(
    "input",
    function(){

        this.value =
            this.value.replace(
                /[^0-9+\-\s]/g,
                ""
            );
    }
);

</script>


</body>

</html>