<?php
/* MediConnect styled error page - single template, driven by HTTP status code.
   Apache ErrorDocument rewrites (see .htaccess / docker/startup.sh) point here
   with ?code=NNN. Falls back to REDIRECT_STATUS, then 404. */
session_start();

$loggedIn = isset($_SESSION["user"]);
$isAdmin = $loggedIn && (($_SESSION["user"]["role"] ?? "") === "admin");
$userName = $loggedIn ? ($_SESSION["user"]["name"] ?? "User") : "";

$codeRaw = $_GET["code"] ?? $_SERVER["REDIRECT_STATUS"] ?? 404;
$code = (int)$codeRaw;
if ($code < 400 || $code > 599) { $code = 404; }
http_response_code($code);

$pages = [
    400 => ["icon" => "❓", "headline" => "That request didn't work.", "message" => "We couldn't understand the request you sent. Please go back, double-check the link, and try again."],
    401 => ["icon" => "🔒", "headline" => "Please sign in first.", "message" => "This page needs you to be signed in. Log in to continue, or create a free MediConnect account."],
    403 => ["icon" => "🚫", "headline" => "You can't go in there.", "message" => "You don't have permission to view this page. If you believe this is a mistake, contact our support team."],
    404 => ["icon" => "🩺", "headline" => "We couldn't find that page.", "message" => "The page you're looking for may have moved or no longer exists. Let's get you back on track."],
    405 => ["icon" => "🛑", "headline" => "That action isn't allowed.", "message" => "This page doesn't support the request method that was used. Please use the buttons or links provided instead."],
    408 => ["icon" => "⏳", "headline" => "The request timed out.", "message" => "Our servers took too long to respond. Please refresh the page or try again in a moment."],
    500 => ["icon" => "⚠️", "headline" => "Something went wrong.", "message" => "An unexpected error occurred on our end. Our team has been notified - please try again shortly."],
    502 => ["icon" => "🌐", "headline" => "We can't reach our servers.", "message" => "The connection to our servers was lost. Please wait a moment and try again."],
    503 => ["icon" => "🧑‍⚕️", "headline" => "We'll be right back.", "message" => "MediConnect is undergoing brief maintenance right now. Please check back in a few minutes."],
];
if (!isset($pages[$code])) { $code = 404; }
$p = $pages[$code];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= $code ?> | MediConnect</title>
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
.primary-btn,.secondary-btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 20px;border-radius:9px;font-size:13px;font-weight:700;transition:.2s;cursor:pointer;border:none}
.primary-btn{background:#1d8cf8;color:#fff}.primary-btn:hover{background:#0877df;transform:translateY(-1px)}
.secondary-btn{border:1px solid #2b3442;color:#dce3ec}.secondary-btn:hover{border-color:#1d8cf8;color:#1d8cf8}
a:focus-visible,button:focus-visible{outline:2px solid #1d8cf8;outline-offset:3px}
.error-hero{padding:96px 0 84px;text-align:center;border-bottom:1px solid #202733;background:radial-gradient(circle at 50% 0%,rgba(29,140,248,.12),transparent 45%)}
.error-inner{max-width:660px;margin:auto}
.badge{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:30px;background:rgba(29,140,248,.1);border:1px solid rgba(29,140,248,.25);color:#52a9ff;font-size:11px;font-weight:800;letter-spacing:1px;margin-bottom:30px}.badge-dot{width:7px;height:7px;background:#1d8cf8;border-radius:50%}
.error-icon{width:64px;height:64px;margin:0 auto 26px;display:flex;align-items:center;justify-content:center;background:rgba(29,140,248,.12);color:#1d8cf8;border:1px solid rgba(29,140,248,.3);border-radius:16px;font-size:30px}
.error-code{font-size:clamp(96px,20vw,190px);font-weight:800;line-height:1;letter-spacing:-6px;background:linear-gradient(180deg,#7fbfff,#1d8cf8 60%,#104eb4);-webkit-background-clip:text;background-clip:text;color:transparent;margin-bottom:10px}
.error-inner h1{font-size:clamp(26px,4vw,38px);letter-spacing:-1.2px;font-weight:800;margin-bottom:14px}
.error-inner h1 span{color:#1d8cf8}
.lead{color:#929cab;font-size:16px;line-height:1.8;margin:0 auto 30px;max-width:520px}
.error-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.error-path{display:block;color:#596372;font-size:11px;margin-top:22px;word-break:break-all}
section{padding:70px 0}
.section-heading{max-width:650px;margin:0 auto 38px;text-align:center}
.section-label{color:#1d8cf8;font-size:11px;font-weight:800;letter-spacing:1.3px;text-transform:uppercase;margin-bottom:10px}
.section-heading h2{font-size:32px;line-height:1.2;letter-spacing:-1.3px;margin-bottom:10px}
.section-heading p{color:#8993a2;font-size:14px}
.error-quick{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.quick-card{background:#151a22;border:1px solid #252e3a;padding:26px 24px;border-radius:15px;transition:.25s}
.quick-card:hover{transform:translateY(-4px);border-color:#1d8cf8}
.quick-icon{width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:rgba(29,140,248,.11);color:#1d8cf8;border-radius:11px;font-size:20px;margin-bottom:15px}
.quick-card h3{font-size:16px;margin-bottom:7px}
.quick-card p{color:#858f9e;font-size:13px;line-height:1.7}
.quick-card .go{display:inline-block;color:#4ca7ff;font-size:12px;font-weight:700;margin-top:14px}
.cta{padding:50px 0 70px}
.cta-box{background:linear-gradient(135deg,#162235,#101722);border:1px solid #29415d;border-radius:20px;padding:45px;text-align:center}
.cta-box h2{font-size:30px;margin-bottom:10px}.cta-box p{color:#8f9baa;font-size:14px;margin-bottom:22px}
footer{border-top:1px solid #202733;padding:55px 0 25px;background:#0c1016}
.footer-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:40px;padding-bottom:40px}
.footer-description{color:#727d8d;font-size:13px;max-width:320px;margin-top:14px}
.footer-column h4{font-size:13px;margin-bottom:17px}
.footer-column a{display:block;color:#707b8b;font-size:12px;margin-bottom:10px}.footer-column a:hover{color:#1d8cf8}
.footer-bottom{border-top:1px solid #202733;padding-top:20px;display:flex;justify-content:space-between;color:#596372;font-size:11px}
[data-theme="light"] .error-hero{background:radial-gradient(circle at 50% 0%,rgba(29,140,248,.08),transparent 45%)}
[data-theme="light"] .error-code{background:linear-gradient(180deg,#0f6fd6,#1d8cf8 55%,#104eb4);-webkit-background-clip:text;background-clip:text}
[data-theme="light"] .error-icon{background:rgba(29,140,248,.1);border-color:rgba(29,140,248,.3)}
@media(max-width:900px){.nav-links{display:none}.footer-grid{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.navbar{height:68px}.nav-actions .login-btn{display:none}.error-hero{padding-top:60px}.error-code{letter-spacing:-4px}section{padding:55px 0}.error-quick{grid-template-columns:1fr}.cta-box{padding:34px 20px}.footer-grid{grid-template-columns:1fr;gap:25px}.footer-bottom{flex-direction:column;gap:8px}}
@media(prefers-reduced-motion:reduce){.primary-btn,.secondary-btn,.quick-card{transition:none;transform:none}html{scroll-behavior:auto}}
</style>
<?php require_once "partials/_theme.php"; ?>
</head>
<body>
<nav>
<div class="container navbar">
<a href="index.php" class="logo" style="display:flex;align-items:center;gap:10px;"><img src="assets/logo.png" alt="MediConnect" style="height:64px;width:auto;"><span style="color:#f5f7fa;font-size:17px;">MediConnect<span style="color:#1d8cf8;">.</span></span></a>
<div class="nav-links"><a href="index.php">Home</a><a href="contact.php">Contact Us</a><a href="about.php">About</a></div>
<div class="nav-actions">
<?php if($isAdmin): ?><a href="admin/dashboard.php" class="primary-btn">Dashboard</a>
<?php elseif($loggedIn): ?><a href="#" class="primary-btn"><?= htmlspecialchars($userName) ?></a>
<?php else: ?><a href="login.php" class="login-btn">Login</a><?php endif; ?>
</div>
</div>
</nav>

<section class="error-hero" role="main">
<div class="container error-inner">
<div class="badge"><span class="badge-dot"></span> HTTP ERROR <?= $code ?></div>
<div class="error-icon" aria-hidden="true"><?= $p["icon"] ?></div>
<div class="error-code" aria-hidden="true"><?= $code ?></div>
<h1><?= $p["headline"] ?> <?php if ($code === 404): ?><span>Don't worry.</span><?php endif; ?></h1>
<p class="lead"><?= $p["message"] ?></p>
<div class="error-actions">
<a href="index.php" class="primary-btn">Back to Home</a>
<a href="contact.php" class="secondary-btn">Contact Us</a>
</div>
<span class="error-path">Requested page: <?= htmlspecialchars($_SERVER["REQUEST_URI"] ?? "") ?></span>
</div>
</section>

<section>
<div class="container">
<div class="section-heading">
<div class="section-label">Still exploring</div>
<h2>You might also want to visit.</h2>
<p>These popular sections can usually point you in the right direction.</p>
</div>
<div class="error-quick">
<a class="quick-card" href="index.php#hospitals">
<div class="quick-icon" aria-hidden="true">🏥</div>
<h3>Hospitals</h3>
<p>Discover hospitals and the departments and services they offer.</p>
<span class="go">Explore hospitals →</span>
</a>
<a class="quick-card" href="index.php#pharmacies">
<div class="quick-icon" aria-hidden="true">💊</div>
<h3>Pharmacies</h3>
<p>Find nearby pharmacies and the medicine services they provide.</p>
<span class="go">Explore pharmacies →</span>
</a>
<a class="quick-card" href="index.php#medicines">
<div class="quick-icon" aria-hidden="true">🧬</div>
<h3>Medicines</h3>
<p>Browse medicine information and general healthcare guidance.</p>
<span class="go">Browse medicines →</span>
</a>
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