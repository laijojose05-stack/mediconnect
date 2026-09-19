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

    <title>MediConnect | About Us</title>

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

        .about-hero {
            padding: 110px 0 100px;

            border-bottom: 1px solid #202733;

            background:
                radial-gradient(
                    circle at 80% 30%,
                    rgba(29, 140, 248, 0.10),
                    transparent 35%
                );
        }

        .hero-content {
            max-width: 850px;
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

            margin-bottom: 24px;
        }

        .badge-dot {
            width: 7px;
            height: 7px;

            background: #1d8cf8;

            border-radius: 50%;
        }

        .about-hero h1 {
            font-size: clamp(45px, 6vw, 72px);

            line-height: 1.05;

            letter-spacing: -3.5px;

            font-weight: 800;

            margin-bottom: 25px;
        }

        .about-hero h1 span {
            color: #1d8cf8;
        }

        .hero-description {
            max-width: 700px;

            color: #929cab;

            font-size: 17px;

            line-height: 1.8;
        }


        /* ================= COMMON SECTION ================= */

        section {
            padding: 90px 0;
        }

        .section-label {
            color: #1d8cf8;

            font-size: 11px;

            font-weight: 800;

            letter-spacing: 1.3px;

            text-transform: uppercase;

            margin-bottom: 10px;
        }

        .section-title {
            font-size: 36px;

            line-height: 1.2;

            letter-spacing: -1.4px;

            margin-bottom: 15px;
        }

        .section-description {
            color: #8993a2;

            font-size: 14px;

            max-width: 650px;

            line-height: 1.8;
        }


        /* ================= INTRO ================= */

        .intro {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 70px;

            align-items: center;
        }

        .intro-text p {
            color: #858f9e;

            font-size: 14px;

            line-height: 1.9;

            margin-top: 20px;
        }

        .intro-box {
            background: #151a22;

            border: 1px solid #252e3a;

            border-radius: 18px;

            padding: 35px;

            position: relative;

            overflow: hidden;
        }

        .intro-box::before {
            content: "";

            position: absolute;

            width: 180px;
            height: 180px;

            border-radius: 50%;

            background: rgba(29, 140, 248, 0.08);

            right: -70px;
            top: -70px;
        }

        .big-plus {
            font-size: 75px;

            line-height: 1;

            font-weight: 800;

            color: #1d8cf8;

            margin-bottom: 20px;

            position: relative;
        }

        .intro-box h3 {
            font-size: 22px;

            margin-bottom: 10px;

            position: relative;
        }

        .intro-box p {
            color: #858f9e;

            font-size: 13px;

            line-height: 1.8;

            position: relative;
        }


        /* ================= MISSION ================= */

        .mission-section {
            border-top: 1px solid #202733;

            border-bottom: 1px solid #202733;

            background: #0d1117;
        }

        .mission-header {
            text-align: center;

            max-width: 700px;

            margin: 0 auto 45px;
        }

        .mission-header .section-description {
            margin: auto;
        }

        .mission-grid {
            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 18px;
        }

        .mission-card {
            background: #151a22;

            border: 1px solid #252e3a;

            border-radius: 15px;

            padding: 30px;

            transition: 0.25s;
        }

        .mission-card:hover {
            transform: translateY(-5px);

            border-color: #1d8cf8;
        }

        .mission-icon {
            width: 50px;
            height: 50px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: rgba(29, 140, 248, 0.11);

            color: #1d8cf8;

            border-radius: 11px;

            font-size: 22px;

            margin-bottom: 20px;
        }

        .mission-card h3 {
            font-size: 17px;

            margin-bottom: 10px;
        }

        .mission-card p {
            color: #858f9e;

            font-size: 13px;

            line-height: 1.75;
        }


        /* ================= WHY MEDICONNECT ================= */

        .why-grid {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 60px;

            align-items: center;
        }

        .feature-list {
            margin-top: 30px;
        }

        .feature {
            display: flex;

            gap: 17px;

            padding: 18px 0;

            border-bottom: 1px solid #202733;
        }

        .feature:first-child {
            border-top: 1px solid #202733;
        }

        .feature-number {
            color: #1d8cf8;

            font-size: 12px;

            font-weight: 800;

            min-width: 30px;
        }

        .feature h3 {
            font-size: 15px;

            margin-bottom: 4px;
        }

        .feature p {
            color: #7f8998;

            font-size: 12px;
        }

        .why-card {
            background:
                linear-gradient(
                    145deg,
                    #162235,
                    #111821
                );

            border: 1px solid #29415d;

            border-radius: 20px;

            padding: 45px;
        }

        .why-card .quote {
            font-size: 26px;

            line-height: 1.35;

            font-weight: 700;

            letter-spacing: -0.8px;

            margin-bottom: 22px;
        }

        .why-card .quote span {
            color: #1d8cf8;
        }

        .why-card p {
            color: #8f9baa;

            font-size: 13px;

            line-height: 1.8;
        }


        /* ================= HOW IT WORKS ================= */

        .how-section {
            border-top: 1px solid #202733;
        }

        .how-header {
            margin-bottom: 42px;
        }

        .steps {
            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 22px;
        }

        .step {
            background: #12171f;

            border: 1px solid #252e3a;

            border-radius: 15px;

            padding: 28px;

            position: relative;
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

            background:
                linear-gradient(
                    135deg,
                    #162235,
                    #101722
                );

            border: 1px solid #29415d;

            border-radius: 20px;

            padding: 60px 30px;

            text-align: center;
        }

        .cta-box::before {
            content: "+";

            position: absolute;

            right: 60px;
            top: -35px;

            font-size: 180px;

            font-weight: 800;

            color: rgba(29, 140, 248, 0.05);
        }

        .cta-box h2 {
            font-size: 35px;

            letter-spacing: -1.2px;

            margin-bottom: 12px;

            position: relative;
        }

        .cta-box p {
            max-width: 600px;

            margin: 0 auto 25px;

            color: #8f9baa;

            font-size: 14px;

            position: relative;
        }

        .cta-box .primary-btn {
            position: relative;
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

            .intro,
            .why-grid {
                grid-template-columns: 1fr;
            }

            .mission-grid,
            .steps {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }

        }


        @media (max-width: 600px) {

            .navbar {
                height: 68px;
            }

            .nav-actions .primary-btn {
                display: none;
            }

            .about-hero {
                padding: 70px 0;
            }

            .about-hero h1 {
                font-size: 44px;

                letter-spacing: -2px;
            }

            .hero-description {
                font-size: 14px;
            }

            section {
                padding: 65px 0;
            }

            .section-title {
                font-size: 29px;
            }

            .intro-box,
            .why-card {
                padding: 28px;
            }

            .cta-box {
                padding: 45px 22px;
            }

            .cta-box h2 {
                font-size: 28px;
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

            <a href="index.php">
                Home
            </a>

            <a href="contact.php">
                Contact Us
            </a>

            <a href="about.php" class="active">
                About
            </a>

        </div>


        <div class="nav-actions">

            <?php if ($isAdmin): ?>

                <a href="admin/dashboard.php" class="primary-btn">
                    Dashboard
                </a>

            <?php elseif ($loggedIn): ?>

                <a href="#" class="primary-btn">
                    <?= htmlspecialchars($userName) ?>
                </a>

            <?php else: ?>

                <a href="login.php" class="login-btn">
                    Login
                </a>
            <?php endif; ?>

        </div>

    </div>

</nav>



<!-- ================= HERO ================= -->

<section class="about-hero">

    <div class="container">

        <div class="hero-content">

            <div class="badge">

                <span class="badge-dot"></span>

                ABOUT MEDICONNECT

            </div>


            <h1>

                Connecting people with

                <span>better healthcare.</span>

            </h1>


            <p class="hero-description">

                MediConnect is a connected healthcare platform designed
                to make healthcare information easier to discover,
                understand and access — all from one simple place.

            </p>

        </div>

    </div>

</section>



<!-- ================= INTRO ================= -->

<section>

    <div class="container">

        <div class="intro">


            <div class="intro-text">

                <div class="section-label">
                    Who We Are
                </div>


                <h2 class="section-title">
                    Healthcare information shouldn't be complicated.
                </h2>


                <p>

                    Finding the right healthcare resource can sometimes
                    mean searching through multiple websites and platforms.

                    MediConnect brings important healthcare information
                    together so users can explore hospitals, pharmacies,
                    medicines and healthcare services more conveniently.

                </p>


                <p>

                    Our goal is simple: create a platform where useful
                    healthcare information is easier to find and easier
                    to understand.

                </p>

            </div>



            <div class="intro-box">

                <div class="big-plus">
                    +
                </div>


                <h3>
                    Your Healthcare, Connected.
                </h3>


                <p>

                    One platform for discovering healthcare resources,
                    exploring available options and getting connected
                    with the information you need.

                </p>

            </div>

        </div>

    </div>

</section>



<!-- ================= MISSION ================= -->

<section class="mission-section">

    <div class="container">


        <div class="mission-header">

            <div class="section-label">
                Our Mission
            </div>


            <h2 class="section-title">
                Making healthcare access simpler.
            </h2>


            <p class="section-description">

                MediConnect focuses on bringing essential healthcare
                information into one connected experience.

            </p>

        </div>



        <div class="mission-grid">


            <div class="mission-card">

                <div class="mission-icon">
                    🔎
                </div>


                <h3>
                    Easy Discovery
                </h3>


                <p>

                    Search for hospitals, pharmacies and medicines
                    without having to switch between different platforms.

                </p>

            </div>



            <div class="mission-card">

                <div class="mission-icon">
                    🏥
                </div>


                <h3>
                    Healthcare Resources
                </h3>


                <p>

                    Explore useful information about healthcare facilities,
                    departments, services and available resources.

                </p>

            </div>



            <div class="mission-card">

                <div class="mission-icon">
                    🔗
                </div>


                <h3>
                    Stay Connected
                </h3>


                <p>

                    Connect with the healthcare information and services
                    that are relevant to your needs.

                </p>

            </div>


        </div>

    </div>

</section>



<!-- ================= WHY MEDICONNECT ================= -->

<section>

    <div class="container">

        <div class="why-grid">


            <div>

                <div class="section-label">
                    Why MediConnect
                </div>


                <h2 class="section-title">
                    Built around a simpler healthcare experience.
                </h2>


                <p class="section-description">

                    MediConnect brings the important pieces together
                    so users can spend less time searching and more
                    time finding the information they need.

                </p>



                <div class="feature-list">


                    <div class="feature">

                        <div class="feature-number">
                            01
                        </div>

                        <div>

                            <h3>
                                One connected platform
                            </h3>

                            <p>
                                Access different categories of healthcare
                                information from one place.
                            </p>

                        </div>

                    </div>



                    <div class="feature">

                        <div class="feature-number">
                            02
                        </div>

                        <div>

                            <h3>
                                Simple search experience
                            </h3>

                            <p>
                                Find hospitals, pharmacies and medicines
                                through an easy-to-use interface.
                            </p>

                        </div>

                    </div>



                    <div class="feature">

                        <div class="feature-number">
                            03
                        </div>

                        <div>

                            <h3>
                                Designed for convenience
                            </h3>

                            <p>
                                Healthcare information is organized to
                                make exploration faster and easier.
                            </p>

                        </div>

                    </div>


                </div>

            </div>



            <div class="why-card">

                <div class="quote">

                    “Healthcare information should be

                    <span>accessible, simple and connected.</span>”

                </div>


                <p>

                    That's the idea behind MediConnect.
                    Instead of making users search through
                    disconnected sources, we aim to provide
                    a straightforward healthcare discovery
                    experience in one platform.

                </p>

            </div>


        </div>

    </div>

</section>



<!-- ================= HOW IT WORKS ================= -->

<section class="how-section">

    <div class="container">


        <div class="how-header">

            <div class="section-label">
                How It Works
            </div>


            <h2 class="section-title">
                Three simple steps.
            </h2>


            <p class="section-description">

                MediConnect keeps the healthcare discovery
                process simple from beginning to end.

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
                    explore the available options.

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

                    Use the information provided to connect
                    with the healthcare service that suits you.

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
                Start exploring healthcare.
            </h2>


            <p>

                Discover hospitals, pharmacies and medicine
                information through one simple platform.

            </p>


            <a href="index.php#search" class="primary-btn">
                Explore Healthcare
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

                    A connected healthcare platform designed
                    to make healthcare information easier
                    to discover and access.

                </p>

            </div>



            <div class="footer-column">

                <h4>
                    Platform
                </h4>

                <a href="index.php#hospitals">
                    Hospitals
                </a>

                <a href="index.php#pharmacies">
                    Pharmacies
                </a>

                <a href="index.php#medicines">
                    Medicines
                </a>

                <a href="index.php#search">
                    Search
                </a>

            </div>



            <div class="footer-column">

                <h4>
                    Company
                </h4>

                <a href="about.php">
                    About
                </a>

                <a href="contact.php">
                    Contact
                </a>

                <a href="#">
                    Privacy
                </a>

                <a href="#">
                    Terms
                </a>

            </div>



            <div class="footer-column">

                <h4>
                    Account
                </h4>


                <?php if ($loggedIn): ?>


                    <?php if ($isAdmin): ?>

                        <a href="admin/dashboard.php">
                            Dashboard
                        </a>

                    <?php endif; ?>


                    <a href="#">
                        My Account
                    </a>


                <?php else: ?>


                    <a href="login.php">
                        Login
                    </a>


                <?php endif; ?>

            </div>


        </div>



        <div class="footer-bottom">

            <span>
                © <?= date("Y") ?> MediConnect.
                All rights reserved.
            </span>


            <span>
                Healthcare • Connected • Simplified
            </span>

        </div>

    </div>

</footer>


</body>
</html>