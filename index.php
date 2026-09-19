<?php
session_start();

$loggedIn = isset($_SESSION["user"]);
$isAdmin = $loggedIn && (($_SESSION["user"]["role"] ?? "") === "admin");
$userName = $loggedIn ? ($_SESSION["user"]["name"] ?? "User") : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MediConnect | Your Healthcare, Connected</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background: #0f1319;
            color: #f5f7fa;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            width: min(1180px, 92%);
            margin: auto;
        }

        /* ================= NAVBAR ================= */

        nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(15, 19, 25, 0.92);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid #202733;
        }

        .navbar {
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -0.8px;
        }

        .logo span {
            color: #1d8cf8;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .nav-links a {
            color: #aeb7c5;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #fff;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .login-btn {
            padding: 10px 18px;
            border: 1px solid #2b3442;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            color: #dce3ec;
        }

        .login-btn:hover {
            border-color: #1d8cf8;
            color: #1d8cf8;
        }

        .primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #1d8cf8;
            color: white;
            padding: 12px 20px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .primary-btn:hover {
            background: #0877df;
            transform: translateY(-1px);
        }

        /* ================= HERO ================= */

        .hero {
            padding: 90px 0 70px;
            overflow: hidden;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 65px;
            align-items: center;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 30px;
            background: rgba(29, 140, 248, 0.10);
            border: 1px solid rgba(29, 140, 248, 0.25);
            color: #52a9ff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 22px;
        }

        .badge-dot {
            width: 7px;
            height: 7px;
            background: #1d8cf8;
            border-radius: 50%;
        }

        .hero h1 {
            font-size: clamp(42px, 5vw, 68px);
            line-height: 1.05;
            letter-spacing: -3px;
            font-weight: 800;
            margin-bottom: 24px;
        }

        .hero h1 span {
            color: #1d8cf8;
        }

        .hero-text {
            color: #929cab;
            max-width: 580px;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .hero-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .secondary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border: 1px solid #2b3442;
            border-radius: 9px;
            color: #dce3ec;
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s;
        }

        .secondary-btn:hover {
            border-color: #1d8cf8;
            color: #1d8cf8;
        }

        /* ================= HERO CARD ================= */

        .hero-visual {
            position: relative;
        }

        .hero-image {
            width: 100%;
            height: 470px;
            object-fit: cover;
            border-radius: 24px;
            opacity: 0.82;
            border: 1px solid #27303d;
        }

        .floating-card {
            position: absolute;
            background: #171c26;
            border: 1px solid #293342;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            border-radius: 14px;
            padding: 17px;
        }

        .card-one {
            left: -35px;
            bottom: 45px;
            width: 205px;
        }

        .card-two {
            right: -25px;
            top: 35px;
            width: 190px;
        }

        .mini-title {
            color: #7f8a9a;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 7px;
            font-weight: 700;
        }

        .mini-value {
            font-size: 16px;
            font-weight: 800;
        }

        .mini-icon {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(29, 140, 248, 0.12);
            color: #1d8cf8;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 17px;
        }

        /* ================= SEARCH ================= */

        .search-section {
            margin-top: 10px;
            padding-bottom: 80px;
        }

        .search-box {
            background: #171c26;
            border: 1px solid #293342;
            border-radius: 16px;
            padding: 9px;
            display: flex;
            gap: 8px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.22);
        }

        .search-select,
        .search-input {
            background: #11161e;
            border: 1px solid #252e3b;
            color: #dce3ec;
            height: 50px;
            border-radius: 9px;
            padding: 0 15px;
            font-family: inherit;
            outline: none;
        }

        .search-select {
            width: 180px;
        }

        .search-input {
            flex: 1;
        }

        .search-input::placeholder {
            color: #677282;
        }

        .search-btn {
            width: 125px;
            border: none;
            border-radius: 9px;
            background: #1d8cf8;
            color: #fff;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        /* ================= SECTION ================= */

        section {
            padding: 85px 0;
        }

        .section-heading {
            max-width: 650px;
            margin-bottom: 42px;
        }

        .section-label {
            color: #1d8cf8;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.3px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .section-heading h2 {
            font-size: 34px;
            line-height: 1.2;
            letter-spacing: -1.3px;
            margin-bottom: 12px;
        }

        .section-heading p {
            color: #8993a2;
            font-size: 14px;
        }

        /* ================= SERVICES ================= */

        .services {
            border-top: 1px solid #202733;
            border-bottom: 1px solid #202733;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .service-card {
            background: #151a22;
            border: 1px solid #252e3a;
            padding: 28px;
            border-radius: 15px;
            transition: 0.25s;
        }

        .service-card:hover {
            transform: translateY(-4px);
            border-color: #1d8cf8;
        }

        .service-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(29, 140, 248, 0.11);
            color: #1d8cf8;
            border-radius: 11px;
            font-size: 22px;
            margin-bottom: 20px;
        }

        .service-card h3 {
            font-size: 17px;
            margin-bottom: 9px;
        }

        .service-card p {
            color: #858f9e;
            font-size: 13px;
            line-height: 1.7;
        }

        .service-link {
            display: inline-block;
            color: #4ca7ff;
            font-size: 12px;
            font-weight: 700;
            margin-top: 17px;
        }

        /* ================= HOW IT WORKS ================= */

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .step {
            position: relative;
            padding: 28px;
            background: #12171f;
            border: 1px solid #252e3a;
            border-radius: 15px;
        }

        .step-number {
            color: #1d8cf8;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .step h3 {
            font-size: 17px;
            margin-bottom: 9px;
        }

        .step p {
            color: #858f9e;
            font-size: 13px;
        }

        /* ================= CTA ================= */

        .cta {
            padding: 70px 0;
        }

        .cta-box {
            position: relative;
            overflow: hidden;
            background: linear-gradient(
                135deg,
                #162235,
                #101722
            );
            border: 1px solid #29415d;
            border-radius: 20px;
            padding: 55px;
            text-align: center;
        }

        .cta-box h2 {
            font-size: 34px;
            letter-spacing: -1px;
            margin-bottom: 12px;
        }

        .cta-box p {
            max-width: 580px;
            margin: 0 auto 25px;
            color: #8f9baa;
            font-size: 14px;
        }

        /* ================= FOOTER ================= */

        footer {
            border-top: 1px solid #202733;
            padding: 55px 0 25px;
            background: #0c1016;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 40px;
            padding-bottom: 40px;
        }

        .footer-description {
            color: #727d8d;
            font-size: 13px;
            max-width: 320px;
            margin-top: 14px;
        }

        .footer-column h4 {
            font-size: 13px;
            margin-bottom: 17px;
        }

        .footer-column a {
            display: block;
            color: #707b8b;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .footer-column a:hover {
            color: #1d8cf8;
        }

        .footer-bottom {
            border-top: 1px solid #202733;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            color: #596372;
            font-size: 11px;
        }

        /* ================= MOBILE ================= */

        @media (max-width: 900px) {

            .nav-links {
                display: none;
            }

            .hero-grid {
                grid-template-columns: 1fr;
            }

            .hero {
                padding-top: 60px;
            }

            .hero-visual {
                max-width: 650px;
                margin: auto;
            }

            .service-grid,
            .steps {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }

            .card-one {
                left: 15px;
            }

            .card-two {
                right: 15px;
            }
        }

        @media (max-width: 600px) {

            .navbar {
                height: 68px;
            }

            .nav-actions .primary-btn {
                display: none;
            }

            .hero h1 {
                font-size: 43px;
                letter-spacing: -2px;
            }

            .hero-text {
                font-size: 14px;
            }

            .hero-image {
                height: 350px;
            }

            .search-box {
                flex-direction: column;
                padding: 12px;
            }

            .search-select,
            .search-input,
            .search-btn {
                width: 100%;
            }

            .search-btn {
                height: 48px;
            }

            section {
                padding: 65px 0;
            }

            .section-heading h2 {
                font-size: 28px;
            }

            .cta-box {
                padding: 40px 22px;
            }

            .cta-box h2 {
                font-size: 27px;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }

            .footer-bottom {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
<?php require_once "partials/_theme.php"; ?>
</head>

<body>

<!-- ================= NAVBAR ================= -->

<nav>
    <div class="container navbar">

        <a href="index.php" class="logo" style="display:flex;align-items:center;gap:10px;">
            <img src="assets/logo.png" alt="MediConnect" style="height:64px;width:auto;">
            <span style="color:#f5f7fa;font-size:17px;">MediConnect<span style="color:#1d8cf8;">.</span></span>
        </a>

        <div class="nav-links">
            <a href="index.php" class="active">Home</a>
            <a href="contact.php">Contact Us</a>
            <a href="about.php">About Us</a>
        </div>

        <div class="nav-actions">
                <a href="login.php" class="login-btn">
                    Login
                </a>
        </div>

    </div>
</nav>


<!-- ================= HERO ================= -->

<section class="hero">
    <div class="container hero-grid">

        <div>

            <div class="badge">
                <span class="badge-dot"></span>
                YOUR HEALTH, CONNECTED
            </div>

            <h1>
                Find the healthcare you need.
                <span>Faster.</span>
            </h1>

            <p class="hero-text">
                MediConnect helps you discover hospitals, pharmacies,
                medicines and healthcare services in one simple platform.
                Find the right healthcare information when you need it.
            </p>

            <div class="hero-buttons">

                <a href="#search" class="primary-btn">
                    Explore Healthcare
                </a>

                <a href="#about" class="secondary-btn">
                    Learn More
                </a>

            </div>

        </div>


        <div class="hero-visual">

            <img
                class="hero-image"
                src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=1000&q=85"
                alt="Healthcare professional"
            >

            <div class="floating-card card-one">

                <div class="mini-icon">✚</div>

                <div class="mini-title">
                    Healthcare
                </div>

                <div class="mini-value">
                    All in one place
                </div>

            </div>

            <div class="floating-card card-two">

                <div class="mini-title">
                    MediConnect
                </div>

                <div class="mini-value">
                    Simple. Fast. Connected.
                </div>

            </div>

        </div>

    </div>
</section>

<!-- ================= SERVICES ================= -->

<section class="services" id="services">

    <div class="container">

        <div class="section-heading">

            <div class="section-label">
                Explore
            </div>

            <h2>
                Everything you need for better healthcare access.
            </h2>

            <p>
                MediConnect brings essential healthcare information together
                so you can find what you need without jumping between different platforms.
            </p>

        </div>


        <div class="service-grid" id="hospitals">

            <div class="service-card">

                <div class="service-icon">
                    🏥
                </div>

                <h3>
                    Hospitals
                </h3>

                <p>
                    Discover hospitals and healthcare facilities and
                    find the services and departments available.
                </p>

                <a href="#hospitals" class="service-link">
                    Explore hospitals →
                </a>

            </div>


            <div class="service-card" id="pharmacies">

                <div class="service-icon">
                    💊
                </div>

                <h3>
                    Pharmacies
                </h3>

                <p>
                    Find pharmacies and access useful information about
                    available healthcare and medicine services.
                </p>

                <a href="#pharmacies" class="service-link">
                    Explore pharmacies →
                </a>

            </div>


            <div class="service-card" id="medicines">

                <div class="service-icon">
                    🧬
                </div>

                <h3>
                    Medicines
                </h3>

                <p>
                    Browse medicine information and learn about their
                    uses and general healthcare information.
                </p>

                <a href="#medicines" class="service-link">
                    Browse medicines →
                </a>

            </div>

        </div>

    </div>

</section>


<!-- ================= ABOUT ================= -->

<section id="about">

    <div class="container">

        <div class="section-heading">

            <div class="section-label">
                How it works
            </div>

            <h2>
                Healthcare information, made simple.
            </h2>

            <p>
                MediConnect is designed to make finding healthcare
                resources easier and more convenient.
            </p>

        </div>


        <div class="steps">

            <div class="step">

                <div class="step-number">
                    01 — SEARCH
                </div>

                <h3>
                    Search what you need
                </h3>

                <p>
                    Search for hospitals, pharmacies or medicines
                    using the MediConnect search system.
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    02 — DISCOVER
                </div>

                <h3>
                    Explore your options
                </h3>

                <p>
                    View relevant healthcare information and
                    compare the available options.
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    03 — CONNECT
                </div>

                <h3>
                    Get connected
                </h3>

                <p>
                    Use the information provided to connect with
                    the healthcare service that suits your needs.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- ================= CTA ================= -->

<section class="cta">

    <div class="container">

        <div class="cta-box">

            <h2>
                Your healthcare journey starts here.
            </h2>

            <p>
                Find hospitals, pharmacies and medicine information
                through one simple and connected platform.
            </p>

            <a href="#search" class="primary-btn">
                Start Exploring
            </a>

        </div>

    </div>

</section>


<!-- ================= FOOTER ================= -->

<footer>

    <div class="container">

        <div class="footer-grid">

            <div>

                <a href="index.php" class="logo">
                    <img src="assets/logo.png" alt="MediConnect" style="height:28px;width:auto;display:block;">
                </a>

                <p class="footer-description">
                    A connected healthcare platform designed to make
                    healthcare information easier to discover and access.
                </p>

            </div>


            <div class="footer-column">

                <h4>
                    Platform
                </h4>

                <a href="#hospitals">Hospitals</a>
                <a href="#pharmacies">Pharmacies</a>
                <a href="#medicines">Medicines</a>
                <a href="#search">Search</a>

            </div>


            <div class="footer-column">

                <h4>
                    Company
                </h4>

                <a href="#about">About</a>
                <a href="#">Contact</a>
                <a href="#">Privacy</a>
                <a href="#">Terms</a>

            </div>


            <div class="footer-column">

                <h4>
                    Account
                </h4>

                <?php if ($loggedIn): ?>

                    <?php if ($isAdmin): ?>
                        <a href="admin/dashboard.php">Dashboard</a>
                    <?php endif; ?>

                    <a href="#">My Account</a>

                <?php else: ?>

                    <a href="admin/login.php">Login</a>

                <?php endif; ?>

            </div>

        </div>


        <div class="footer-bottom">

            <span>
                © <?= date("Y") ?> MediConnect. All rights reserved.
            </span>

            <span>
                Healthcare • Connected • Simplified
            </span>

        </div>

    </div>

</footer>


</body>
</html>