<?php
session_start();
require_once "../config/database.php";

if (isset($_SESSION["hospital_id"])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = $_GET["success"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $error = "Please enter your email and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, hospital_name, email, password, status
             FROM hospitals
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows !== 1) {

                $error = "Invalid email or password.";

            } else {

                $hospital = $result->fetch_assoc();

                if (!password_verify($password, $hospital["password"])) {

                    $error = "Invalid email or password.";

                } elseif ($hospital["status"] !== "approved") {

                    $error = "Your hospital account is not approved yet.";

                } else {

                    session_regenerate_id(true);

                    $_SESSION["hospital_id"] = (int)$hospital["id"];
                    $_SESSION["hospital_name"] = $hospital["hospital_name"];
                    $_SESSION["hospital_email"] = $hospital["email"];

                    header("Location: dashboard.php");
                    exit();
                }
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Hospital Login | MediConnect</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

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

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 20px;
}


/* Main Card */

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
        rgba(0, 0, 0, 0.5);
}


/* Navbar */

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

    color: #ffffff;

    text-decoration: none;

    font-size: 1.15rem;

    font-weight: 700;
}


.brand-icon {

    width: 28px;

    height: 28px;

    background: transparent;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;
}


.dot-blue {

    color: #1d8cf8;
}


.nav-links {

    display: flex;

    gap: 32px;
}


.nav-links a {

    color: #8b95a5;

    text-decoration: none;

    font-size: 0.95rem;

    font-weight: 500;
}


.nav-links a:hover {

    color: #ffffff;
}


/* Form Section */

.form-section {

    flex: 1.2;

    padding: 120px 48px 48px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    z-index: 5;
}


.main-title {

    font-size: 2.25rem;

    font-weight: 700;

    margin-bottom: 6px;

    letter-spacing: -0.5px;
}


.tagline {

    font-size: 1.1rem;

    font-weight: 600;

    color: #1d8cf8;

    margin-bottom: 8px;
}


.description {

    font-size: 0.875rem;

    color: #8b95a5;

    line-height: 1.5;

    margin-bottom: 28px;

    max-width: 420px;
}


/* Messages */

.message {

    padding: 13px 15px;

    border-radius: 10px;

    margin-bottom: 18px;

    font-size: 13px;
}


.error {

    background: #3b1d25;

    color: #ff9eaa;

    border: 1px solid #5c2733;
}


.success {

    background: #15372e;

    color: #83e8c7;

    border: 1px solid #205342;
}


/* Input */

.form-container {

    display: flex;

    flex-direction: column;

    gap: 16px;

    margin-bottom: 20px;
}


.input-box {

    position: relative;

    background: #212836;

    border-radius: 14px;

    padding: 10px 16px;

    border: 1.5px solid transparent;

    transition: 0.2s;
}


.input-box:focus-within {

    border-color: #1d8cf8;

    box-shadow:
        0 0 0 1px #1d8cf8;
}


.input-box label {

    display: block;

    font-size: 0.7rem;

    color: #6c788a;

    margin-bottom: 3px;

    font-weight: 600;
}


.input-box input {

    width: 100%;

    background: transparent;

    border: none;

    outline: none;

    color: #ffffff;

    font-size: 0.95rem;

    font-weight: 500;

    padding-right: 28px;
}


.input-box input::placeholder {

    color: #596474;
}


/* Icon */

.input-icon {

    position: absolute;

    right: 16px;

    top: 50%;

    transform: translateY(-50%);

    color: #6c788a;

    width: 18px;

    height: 18px;

    display: flex;

    align-items: center;

    justify-content: center;
}


/* Options */

.form-options {

    display: flex;

    align-items: center;

    justify-content: space-between;

    font-size: 0.85rem;

    margin-bottom: 8px;
}


.remember-me {

    display: flex;

    align-items: center;

    gap: 8px;

    color: #8b95a5;

    cursor: pointer;
}


.remember-me input {

    accent-color: #1d8cf8;

    width: 15px;

    height: 15px;
}


.forgot-link {

    color: #1d8cf8;

    text-decoration: none;

    font-weight: 500;
}


.forgot-link:hover {

    text-decoration: underline;
}


/* Login Button */

.submit-btn {

    width: 100%;

    background: #1d8cf8;

    color: #ffffff;

    border: none;

    border-radius: 14px;

    padding: 14px 20px;

    font-size: 0.95rem;

    font-weight: 600;

    cursor: pointer;

    box-shadow:
        0 8px 20px
        rgba(29, 140, 248, 0.3);

    transition: 0.2s;
}


.submit-btn:hover {

    background: #1572cd;

    transform: translateY(-1px);
}


/* Registration */

.signup-text {

    margin-top: 20px;

    font-size: 0.875rem;

    color: #8b95a5;
}


.signup-text a {

    color: #1d8cf8;

    text-decoration: none;

    font-weight: 600;
}


/* Right Image */

.bg-section {

    flex: 1;

    position: relative;

    background:

        linear-gradient(
            135deg,
            rgba(23, 28, 38, 0.3),
            rgba(15, 19, 25, 0.85)
        ),

        url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=1000&q=80')

        center / cover no-repeat;

    display: flex;

    align-items: flex-end;

    justify-content: flex-end;

    padding: 48px;
}


/* Curved Line */

.bg-section::before {

    content: '';

    position: absolute;

    top: -10%;

    left: -80px;

    width: 160px;

    height: 120%;

    border-left:
        1px dashed
        rgba(255, 255, 255, 0.15);

    border-radius: 50%;
}


/* Bottom Logo */

.bottom-logo {

    color: #ffffff;

    font-size: 2rem;

    font-weight: 800;

    opacity: 0.8;
}


/* Mobile */

@media (max-width: 850px) {

    .app-card {

        flex-direction: column;
    }

    .form-section {

        width: 100%;

        padding: 110px 35px 40px;
    }

    .bg-section {

        min-height: 180px;

        width: 100%;
    }

}


@media (max-width: 500px) {

    .navbar {

        padding: 25px;
    }

    .nav-links {

        gap: 15px;
    }

    .form-section {

        padding-left: 25px;

        padding-right: 25px;
    }

    .main-title {

        font-size: 1.9rem;
    }

}

</style>
<?php require_once "../partials/_theme.php"; ?>

</head>


<body>


<div class="app-card">


<!-- NAVBAR -->

<header class="navbar">

    <a href="#" class="nav-brand">

        <span class="brand-icon"><img src="../assets/logo.png" alt="MediConnect" style="width:100%;height:100%;object-fit:contain;"></span>

        MediConnect<span class="dot-blue">.</span>

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


    <h1 class="main-title">

        Hospital Login<span class="dot-blue">.</span>

    </h1>


    <h2 class="tagline">

        Secure Hospital Portal

    </h2>


    <p class="description">

        Manage doctors, patients, appointments,
        departments and availability from your
        MediConnect hospital dashboard.

    </p>



    <?php if (!empty($success)): ?>

        <div class="message success">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <?php if (!empty($error)): ?>

        <div class="message error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <form
        action="login.php"
        method="POST"
        autocomplete="off"
    >


        <div class="form-container">


            <!-- EMAIL -->

            <div class="input-box">

                <label for="email">
                    HOSPITAL EMAIL
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="hospital@example.com"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    autocomplete="off"
                    required
                >

                <div class="input-icon">

                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path
                            d="M4 4h16c1.1 0 2 .9 2 2v12
                            c0 1.1-.9 2-2 2H4
                            c-1.1 0-2-.9-2-2V6
                            c0-1.1.9-2 2-2z"
                        />

                        <polyline points="22,6 12,13 2,6"/>

                    </svg>

                </div>

            </div>



            <!-- PASSWORD -->

            <div class="input-box">

                <label for="password">
                    PASSWORD
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="new-password"
                    required
                >

                <div class="input-icon">

                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <circle
                            cx="12"
                            cy="12"
                            r="3"
                        />

                        <path
                            d="M1 12s4-8 11-8
                            11 8 11 8-4 8-11 8
                            -11-8-11-8z"
                        />

                    </svg>

                </div>

            </div>



            <!-- OPTIONS -->

            <div class="form-options">

                <label class="remember-me">

                    <input
                        type="checkbox"
                        name="remember"
                    >

                    Remember me

                </label>


                <a
                    href="forgot_password.php"
                    class="forgot-link"
                >

                    Forgot Password?

                </a>

            </div>

        </div>



        <button
            type="submit"
            class="submit-btn"
        >

            Login

        </button>


    </form>



    <p class="signup-text">

        Don't have a hospital account?

        <a href="register.php">
            Register Hospital
        </a>

    </p>


</main>



<!-- IMAGE -->

<div class="bg-section">

    <div class="bottom-logo">
        <img src="../assets/logo.png" alt="MediConnect" style="width:96px;height:auto;opacity:.9;">
    </div>

</div>


</div>


</body>

</html>