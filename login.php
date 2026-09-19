<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Choose Login | MediConnect</title>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
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
    min-height: 100vh;
    background: #0f1319;
    color: #ffffff;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 25px;
}


/* MAIN CARD */

.app-card {

    width: 100%;
    max-width: 1100px;

    min-height: 620px;

    background: #171c26;

    border: 1px solid #262d3d;

    border-radius: 24px;

    position: relative;

    overflow: hidden;

    box-shadow:
        0 25px 50px -12px
        rgba(0,0,0,0.6);

}


/* NAVBAR */

.navbar {

    width: 100%;

    padding: 30px 45px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    position: relative;

    z-index: 10;

}


.nav-brand {

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 20px;

    font-weight: 700;

    text-decoration: none;

    color: white;

}


.brand-icon {

    width: 32px;

    height: 32px;

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

    gap: 28px;

}


.nav-links a {

    color: #8b95a5;

    text-decoration: none;

    font-size: 14px;

    transition: 0.3s;

}


.nav-links a:hover {

    color: white;

}


/* CONTENT */

.content {

    max-width: 900px;

    margin: 45px auto 70px;

    padding: 0 30px;

    text-align: center;

}


.content h1 {

    font-size: 42px;

    font-weight: 800;

    letter-spacing: -1px;

    margin-bottom: 12px;

}


.content p {

    color: #8b95a5;

    font-size: 15px;

    line-height: 1.7;

    margin-bottom: 45px;

}


/* LOGIN CARDS */

.login-options {

    display: grid;

    grid-template-columns:
    repeat(3, 1fr);

    gap: 22px;

}


.login-card {

    background: #212836;

    border: 1px solid #2b3445;

    border-radius: 18px;

    padding: 32px 25px;

    text-decoration: none;

    color: white;

    transition: 0.3s;

    cursor: pointer;

}


.login-card:hover {

    transform: translateY(-8px);

    border-color: #1d8cf8;

    box-shadow:
        0 15px 35px
        rgba(29,140,248,0.15);

}


.card-icon {

    width: 65px;

    height: 65px;

    margin: 0 auto 22px;

    background:
    rgba(29,140,248,0.12);

    border: 1px solid
    rgba(29,140,248,0.3);

    border-radius: 18px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;

}


.login-card h2 {

    font-size: 20px;

    margin-bottom: 10px;

}


.login-card p {

    font-size: 13px;

    color: #8b95a5;

    line-height: 1.6;

    margin-bottom: 20px;

}


.login-btn {

    display: inline-block;

    background: #1d8cf8;

    color: white;

    padding: 10px 18px;

    border-radius: 9px;

    font-size: 13px;

    font-weight: 600;

}


/* FOOTER */

.footer-text {

    margin-top: 40px !important;

    font-size: 13px !important;

}


.footer-text a {

    color: #1d8cf8;

    text-decoration: none;

}


/* RESPONSIVE */

@media (max-width: 850px) {

    .login-options {

        grid-template-columns:
        1fr;

        max-width: 400px;

        margin: auto;

    }

    .content {

        margin-top: 25px;

    }

    .content h1 {

        font-size: 32px;

    }

}


@media (max-width: 550px) {

    .navbar {

        padding: 25px;

    }

    .nav-links {

        display: none;

    }

    .content {

        padding: 0 15px;

    }

}

</style>
<?php require_once "partials/_theme.php"; ?>

</head>


<body>


<div class="app-card">


<!-- NAVBAR -->

<header class="navbar">


<a href="login.php" class="nav-brand">


<span class="brand-icon"><img src="assets/logo.png" alt="MediConnect" style="width:100%;height:100%;object-fit:contain;"></span>


MediConnect<span class="dot-blue">.</span>


</a>


<div class="nav-links">

<a href="index.php">Home</a>

<a href="about.php">About</a>

<a href="contact.php">Contact</a>

</div>


</header>



<!-- CONTENT -->

<main class="content">


<h1>

Welcome to MediConnect<span class="dot-blue">.</span>

</h1>


<p>

Choose your account type to continue.
Access healthcare services, manage hospital information,
and find medicines through MediConnect.

</p>



<div class="login-options">


<!-- USER LOGIN -->

<a
href="user/login.php"
class="login-card"
>


<div class="card-icon">

👤

</div>


<h2>

User

</h2>


<p>

Login to search medicines,
find hospitals and manage your profile.

</p>


<span class="login-btn">

User Login →

</span>


</a>



<!-- HOSPITAL LOGIN -->

<a
href="hospital/login.php"
class="login-card"
>


<div class="card-icon">

🏥

</div>


<h2>

Hospital

</h2>


<p>

Manage hospital information,
services and healthcare details.

</p>


<span class="login-btn">

Hospital Login →

</span>


</a>



<!-- PHARMACY LOGIN -->

<a
href="pharmacy/login.php"
class="login-card"
>


<div class="card-icon">

💊

</div>


<h2>

Pharmacy

</h2>


<p>

Manage medicines,
stock information and pharmacy details.

</p>


<span class="login-btn">

Pharmacy Login →

</span>


</a>


</div>


<p class="footer-text">

Don't have an account?

<a href="user/register.php">

Create a User Account

</a>

</p>


</main>


</div>


</body>

</html>