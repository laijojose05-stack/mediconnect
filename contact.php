<?php
session_start();

$loggedIn = isset($_SESSION["user"]);
$isAdmin = $loggedIn && (($_SESSION["user"]["role"] ?? "") === "admin");
$userName = $loggedIn ? ($_SESSION["user"]["name"] ?? "User") : "";

$messageSent = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($name !== "" && filter_var($email, FILTER_VALIDATE_EMAIL) && $message !== "") {
        $messageSent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediConnect | Contact Us</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html{scroll-behavior:smooth}
body{font-family:"Plus Jakarta Sans",sans-serif;background:#0f1319;color:#f5f7fa;line-height:1.6}
a{text-decoration:none;color:inherit}.container{width:min(1180px,92%);margin:auto}
nav{position:sticky;top:0;z-index:1000;background:rgba(15,19,25,.92);backdrop-filter:blur(15px);border-bottom:1px solid #202733}
.navbar{height:76px;display:flex;align-items:center;justify-content:space-between}.logo{font-size:23px;font-weight:800;letter-spacing:-.8px}.logo span{color:#1d8cf8}
.nav-links{display:flex;align-items:center;gap:32px}.nav-links a{color:#aeb7c5;font-size:14px;font-weight:600;transition:.2s}.nav-links a:hover,.nav-links a.active{color:#fff}
.nav-actions{display:flex;align-items:center;gap:12px}.login-btn{padding:10px 18px;border:1px solid #2b3442;border-radius:9px;font-size:13px;font-weight:700;color:#dce3ec}.login-btn:hover{border-color:#1d8cf8;color:#1d8cf8}
.primary-btn{display:inline-flex;align-items:center;justify-content:center;background:#1d8cf8;color:#fff;padding:12px 20px;border-radius:9px;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:.2s}.primary-btn:hover{background:#0877df;transform:translateY(-1px)}
section{padding:85px 0}.hero{padding:90px 0 65px;border-bottom:1px solid #202733}
.badge{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:30px;background:rgba(29,140,248,.1);border:1px solid rgba(29,140,248,.25);color:#52a9ff;font-size:11px;font-weight:800;letter-spacing:1px;margin-bottom:22px}.badge-dot{width:7px;height:7px;background:#1d8cf8;border-radius:50%}
h1{font-size:clamp(42px,5vw,64px);line-height:1.05;letter-spacing:-3px;font-weight:800;margin-bottom:20px}h1 span{color:#1d8cf8}
.lead{color:#929cab;max-width:700px;font-size:16px;line-height:1.8}.contact-grid{display:grid;grid-template-columns:.85fr 1.15fr;gap:28px}
.info-card,.form-card{background:#151a22;border:1px solid #252e3a;border-radius:15px;padding:30px}.info-card h2,.form-card h2{font-size:24px;letter-spacing:-.7px;margin-bottom:10px}.info-card>p,.form-card>p{color:#858f9e;font-size:13px;margin-bottom:26px}
.info-item{padding:19px 0;border-top:1px solid #252e3a}.info-item:first-of-type{border-top:0}.info-item strong{display:block;font-size:13px;margin-bottom:5px}.info-item span{color:#858f9e;font-size:13px}
form{display:grid;gap:15px}.row{display:grid;grid-template-columns:1fr 1fr;gap:15px}label{font-size:11px;color:#aeb7c5;font-weight:700;margin-bottom:7px;display:block}
input,textarea{width:100%;background:#11161e;border:1px solid #252e3b;color:#dce3ec;border-radius:9px;padding:13px 14px;font-family:inherit;outline:none;font-size:13px}input:focus,textarea:focus{border-color:#1d8cf8}textarea{min-height:145px;resize:vertical}
.success{padding:13px 15px;background:rgba(29,140,248,.1);border:1px solid rgba(29,140,248,.3);border-radius:9px;color:#52a9ff;font-size:13px;margin-bottom:18px}
.cta{padding:20px 0 70px}.cta-box{background:linear-gradient(135deg,#162235,#101722);border:1px solid #29415d;border-radius:20px;padding:45px;text-align:center}.cta-box h2{font-size:30px;margin-bottom:10px}.cta-box p{color:#8f9baa;font-size:14px;margin-bottom:22px}
footer{border-top:1px solid #202733;padding:55px 0 25px;background:#0c1016}.footer-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:40px;padding-bottom:40px}.footer-description{color:#727d8d;font-size:13px;max-width:320px;margin-top:14px}.footer-column h4{font-size:13px;margin-bottom:17px}.footer-column a{display:block;color:#707b8b;font-size:12px;margin-bottom:10px}.footer-column a:hover{color:#1d8cf8}.footer-bottom{border-top:1px solid #202733;padding-top:20px;display:flex;justify-content:space-between;color:#596372;font-size:11px}
@media(max-width:900px){.nav-links{display:none}.contact-grid{grid-template-columns:1fr}.footer-grid{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.navbar{height:68px}.nav-actions .primary-btn{display:none}h1{font-size:43px;letter-spacing:-2px}.hero{padding-top:60px}section{padding:65px 0}.row{grid-template-columns:1fr}.info-card,.form-card{padding:23px}.footer-grid{grid-template-columns:1fr;gap:25px}.footer-bottom{flex-direction:column;gap:8px}}
</style>
<?php require_once "partials/_theme.php"; ?>
</head>
<body>
<nav>
<div class="container navbar">
<a href="index.php" class="logo" style="display:flex;align-items:center;gap:10px;"><img src="assets/logo.png" alt="MediConnect" style="height:64px;width:auto;"><span style="color:#f5f7fa;font-size:17px;">MediConnect<span style="color:#1d8cf8;">.</span></span></a>
<div class="nav-links"><a href="index.php">Home</a><a href="contact.php" class="active">Contact Us</a><a href="about.php">About</a></div>
<div class="nav-actions">
<?php if($isAdmin): ?><a href="admin/dashboard.php" class="primary-btn">Dashboard</a>
<?php elseif($loggedIn): ?><a href="#" class="primary-btn"><?= htmlspecialchars($userName) ?></a>
<?php else: ?><a href="login.php" class="login-btn">Login</a><?php endif; ?>
</div>
</div>
</nav>

<section class="hero">
<div class="container">
<div class="badge"><span class="badge-dot"></span> GET IN TOUCH</div>
<h1>We’re here to <span>help.</span></h1>
<p class="lead">Have a question about MediConnect, its healthcare information, or how the platform works? Send us a message and we’ll be happy to hear from you.</p>
</div>
</section>

<section>
<div class="container contact-grid">
<div class="info-card">
<h2>Contact MediConnect</h2>
<p>Use the details below or send us a message through the contact form.</p>
<div class="info-item"><strong>✉ Email</strong><span>laijojose05@gmail.com</span></div>
<div class="info-item"><strong>📞 Phone</strong><span>+91 85907 75834</span></div>
<div class="info-item"><strong>📍 Location</strong><span>East Kallada, Kollam, Kerala, India</span></div>
<div class="info-item"><strong>⏱ Support</strong><span>We aim to respond as soon as possible.</span></div>
</div>

<div class="form-card">
<h2>Send us a message</h2>
<p>Fill in the form below and tell us how we can help.</p>
<?php if($messageSent): ?><div class="success">Thanks, <?= htmlspecialchars($name) ?>. Your message has been received.</div><?php endif; ?>
<form method="post" action="contact.php">
<div class="row">
<div><label for="name">NAME</label><input id="name" name="name" type="text" placeholder="Your name" required></div>
<div><label for="email">EMAIL</label><input id="email" name="email" type="email" placeholder="you@example.com" required></div>
</div>
<div><label for="message">MESSAGE</label><textarea id="message" name="message" placeholder="How can we help you?" required></textarea></div>
<button type="submit" class="primary-btn">Send Message</button>
</form>
</div>
</div>
</section>

<section class="cta">
<div class="container"><div class="cta-box"><h2>Your healthcare journey starts here.</h2><p>Find hospitals, pharmacies and medicine information through one simple and connected platform.</p><a href="index.php#search" class="primary-btn">Start Exploring</a></div></div>
</section>

<footer>
<div class="container">
<div class="footer-grid">
<div><a href="index.php" class="logo"><img src="assets/logo.png" alt="MediConnect" style="height:28px;width:auto;display:block;"></a><p class="footer-description">A connected healthcare platform designed to make healthcare information easier to discover and access.</p></div>
<div class="footer-column"><h4>Platform</h4><a href="index.php#hospitals">Hospitals</a><a href="index.php#pharmacies">Pharmacies</a><a href="index.php#medicines">Medicines</a><a href="index.php#search">Search</a></div>
<div class="footer-column"><h4>Company</h4><a href="about.php">About</a><a href="contact.php">Contact</a><a href="#">Privacy</a><a href="#">Terms</a></div>
<div class="footer-column"><h4>Account</h4><?php if($loggedIn): ?><?php if($isAdmin): ?><a href="admin/dashboard.php">Dashboard</a><?php endif; ?><a href="#">My Account</a><?php else: ?><a href="login.php">Login</a><?php endif; ?></div>
</div>
<div class="footer-bottom"><span>© <?= date("Y") ?> MediConnect. All rights reserved.</span><span>Healthcare • Connected • Simplified</span></div>
</div>
</footer>
</body>
</html>
