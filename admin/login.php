<?php

session_start();

require_once "../config/database.php";

if (
    isset($_SESSION["user"]) &&
    ($_SESSION["user"]["role"] ?? "") === "admin"
) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password
             FROM admins
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $admin = $result->fetch_assoc();

            if (password_verify($password, $admin["password"])) {

                $_SESSION["user"] = [
                    "id" => $admin["id"],
                    "name" => $admin["name"],
                    "email" => $admin["email"],
                    "role" => "admin"
                ];

                header("Location: dashboard.php");
                exit;

            } else {

                $error = "Invalid email or password.";

            }

        } else {

            $error = "Invalid email or password.";

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

<title>Admin Login | MediConnect</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

body {

    background: #0f1319;

    color: #ffffff;

    display: flex;

    justify-content: center;

    align-items: center;

    min-height: 100vh;

    padding: 20px;

}


/* MAIN CARD */

.app-card {

    width: 100%;

    max-width: 1000px;

    min-height: 620px;

    background: #171c26;

    border-radius: 24px;

    display: flex;

    position: relative;

    overflow: hidden;

    border: 1px solid #262d3d;

    box-shadow:
        0 25px 50px -12px
        rgba(0,0,0,.5);

}


/* NAVBAR */

.navbar {

    position: absolute;

    top: 0;

    left: 0;

    width: 100%;

    padding: 32px 48px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    z-index: 10;

}


.nav-brand {

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 18px;

    font-weight: 700;

    color: white;

    text-decoration: none;

}


.brand-icon {

    width: 30px;

    height: 30px;

    border-radius: 50%;

    background: transparent;

    display: flex;

    justify-content: center;

    align-items: center;

}


.blue {

    color: #1d8cf8;

}


.nav-links {

    display: flex;

    gap: 28px;

}


.nav-links a {

    color: #8b95a5;

    text-decoration: none;

    font-size: 14px;

}


.nav-links a:hover {

    color: white;

}


/* LOGIN SECTION */

.form-section {

    flex: 1.2;

    padding:
        130px
        48px
        48px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    z-index: 2;

}


.admin-badge {

    display: inline-block;

    width: fit-content;

    background:
        rgba(29,140,248,.12);

    color: #1d8cf8;

    padding: 7px 12px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;

    margin-bottom: 15px;

}


.main-title {

    font-size: 38px;

    font-weight: 700;

    margin-bottom: 8px;

}


.tagline {

    font-size: 17px;

    color: #1d8cf8;

    margin-bottom: 10px;

}


.description {

    color: #8b95a5;

    font-size: 14px;

    line-height: 1.6;

    margin-bottom: 28px;

}


/* ERROR */

.error {

    background:
        rgba(239,68,68,.12);

    border:
        1px solid
        rgba(239,68,68,.3);

    color: #ff6b6b;

    padding: 12px;

    border-radius: 10px;

    font-size: 13px;

    margin-bottom: 18px;

}


/* FORM */

.form-container {

    display: flex;

    flex-direction: column;

    gap: 16px;

}


.input-box {

    position: relative;

    background: #212836;

    border:
        1px solid
        transparent;

    border-radius: 14px;

    padding: 10px 16px;

}


.input-box:focus-within {

    border-color: #1d8cf8;

}


.input-box label {

    display: block;

    font-size: 11px;

    color: #6c788a;

    margin-bottom: 4px;

}


.input-box input {

    width: 100%;

    background: transparent;

    border: none;

    outline: none;

    color: white;

    font-size: 15px;

    padding-right: 35px;

}


.input-icon {

    position: absolute;

    right: 16px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #6c788a;

}


/* OPTIONS */

.form-options {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin: 4px 0;

    font-size: 13px;

}


.remember {

    display: flex;

    align-items: center;

    gap: 8px;

    color: #8b95a5;

}


.remember input {

    accent-color: #1d8cf8;

}


/* BUTTON */

.submit-btn {

    width: 100%;

    margin-top: 18px;

    padding: 15px;

    border: none;

    border-radius: 14px;

    background: #1d8cf8;

    color: white;

    font-size: 15px;

    font-weight: 600;

    cursor: pointer;

    transition: .2s;

}


.submit-btn:hover {

    background: #1572cd;

    transform:
        translateY(-1px);

}


.back-link {

    text-align: center;

    margin-top: 20px;

    font-size: 13px;

}


.back-link a {

    color: #1d8cf8;

    text-decoration: none;

}


/* RIGHT IMAGE */

.bg-section {

    flex: 1;

    position: relative;

    background:

        linear-gradient(
            135deg,
            rgba(23,28,38,.3),
            rgba(15,19,25,.9)
        ),

        url(
            'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=1000&q=80'
        )

        center / cover no-repeat;

}


.bottom-logo {

    position: absolute;

    bottom: 35px;

    right: 35px;

    font-size: 32px;

    font-weight: 800;

    color: white;

    opacity: .7;

}


/* RESPONSIVE */

@media(max-width:850px) {

    .app-card {

        flex-direction: column;

    }

    .bg-section {

        min-height: 200px;

    }

}


@media(max-width:600px) {

    .navbar {

        padding: 25px;

    }

    .nav-links {

        display: none;

    }

    .form-section {

        padding:
            110px
            25px
            35px;

    }

    .main-title {

        font-size: 30px;

    }

}

</style>
<?php require_once "../partials/_theme.php"; ?>

</head>


<body>


<div class="app-card">


<!-- NAVBAR -->

<header class="navbar">

    <a
        href="../index.php"
        class="nav-brand"
    >

        <span class="brand-icon"><img src="../assets/logo.png" alt="MediConnect" style="width:100%;height:100%;object-fit:contain;"></span>

        MediConnect<span class="blue">.</span>

    </a>


    <div class="nav-links">

        <a href="../index.php">

            Home

        </a>

        <a href="#">

            Contact

        </a>

    </div>

</header>



<!-- LOGIN FORM -->

<main class="form-section">


<div class="admin-badge">

    ADMIN PORTAL

</div>


<h1 class="main-title">

    Welcome back<span class="blue">.</span>

</h1>


<h2 class="tagline">

    Manage MediConnect, Securely.

</h2>


<p class="description">

    Access the MediConnect administration
    panel and manage users, hospitals,
    pharmacies and medicines.

</p>


<?php if (!empty($error)): ?>

<div class="error">

    <?php
    echo htmlspecialchars($error);
    ?>

</div>

<?php endif; ?>


<form
    method="POST"
    autocomplete="off"
>


<div class="form-container">


<!-- EMAIL -->

<div class="input-box">

<label for="email">

Email Address

</label>


<input

type="email"

id="email"

name="email"

required

placeholder="admin@example.com"

>


<span class="input-icon">

✉

</span>

</div>



<!-- PASSWORD -->

<div class="input-box">

<label for="password">

Password

</label>


<input

type="password"

id="password"

name="password"

required

placeholder="••••••••"

>


<span
class="input-icon"
onclick="togglePassword()"
style="cursor:pointer"
>

👁

</span>

</div>



<!-- OPTIONS -->

<div class="form-options">

<label class="remember">

<input
type="checkbox"
name="remember"
>

Remember me

</label>

</div>


</div>


<button
type="submit"
class="submit-btn"
>

Login to Dashboard

</button>


</form>


<div class="back-link">

<a href="../index.php">

← Back to MediConnect

</a>

</div>


</main>



<!-- IMAGE -->

<div class="bg-section">

<div class="bottom-logo">

<img src="../assets/logo.png" alt="MediConnect" style="width:96px;height:auto;opacity:.9;">

</div>

</div>


</div>


<script>

function togglePassword() {

    const password =
        document.getElementById(
            "password"
        );

    if (
        password.type ===
        "password"
    ) {

        password.type =
            "text";

    } else {

        password.type =
            "password";

    }

}

</script>


</body>

</html>