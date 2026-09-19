<?php
// user/_header.php — shared hospital-style layout.
// Expects $pageTitle.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$pageTitle = $pageTitle ?? "Dashboard";
$current   = basename($_SERVER["PHP_SELF"]);

$nav = [
    ["dashboard.php",  "📊", "Dashboard"],
    ["hospitals.php",  "🏥", "Hospitals"],
    ["doctors.php",    "👨‍⚕️", "Doctors"],
    ["pharmacies.php", "🏪", "Pharmacies"],
    ["catalogue.php",  "💊", "Medicines"],
    ["my_requests.php","📥", "Medicine Requests"],
    ["appointments.php","📅", "Appointments"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ($pageTitle ?? 'MediConnect') ?> | MediConnect</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="chatbot.css">
<style>
:root{
--bg:#0f1319; --surface:#171c26; --surface-2:#212836; --border:#262d3d;
--hover:#212836; --accent:#1d8cf8; --accent-soft:rgba(29,140,248,0.15);
--text:#ffffff; --muted:#8b95a5; --success:#66e6aa; --warning:#f0c15a; --danger:#ff8a8a;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;}
body{background:var(--bg);color:var(--text);height:100vh;display:flex;overflow:hidden;}
a{color:var(--accent);text-decoration:none;}
a:hover{opacity:.85;}

/* ---------- SIDEBAR (fixed, never scrolls) ---------- */
.sidebar{width:250px;background:var(--surface);padding:30px 20px;border-right:1px solid var(--border);height:100vh;flex-shrink:0;display:flex;flex-direction:column;position:sticky;top:0;align-self:flex-start;}
.logo{font-size:22px;font-weight:bold;margin-bottom:40px;color:#fff;flex-shrink:0;}
.logo span{color:var(--accent);}
.sidebar .nav-wrap{flex:1;overflow-y:auto;}
.sidebar .nav-wrap::-webkit-scrollbar{width:6px;}
.sidebar .nav-wrap::-webkit-scrollbar-thumb{background:var(--border);border-radius:6px;}
.sidebar a.nav-link{display:block;padding:13px 15px;margin-bottom:5px;color:var(--muted);text-decoration:none;border-radius:10px;font-size:14px;transition:.3s;}
.sidebar a.nav-link:hover,.sidebar a.nav-link.active{background:var(--hover);color:var(--accent);}
.sidebr-user{margin-top:20px;padding-top:18px;border-top:1px solid var(--border);}
.sidebr-user .uname{font-size:13px;font-weight:600;margin-bottom:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sidebr-user a.logout{display:block;padding:11px 15px;color:#ff8a8a;text-decoration:none;border-radius:10px;font-size:14px;transition:.3s;}
.sidebr-user a.logout:hover{background:rgba(255,70,70,.12);}

/* ---------- SIDEBAR USER ---------- */
.sidebr-user{margin-top:20px;padding-top:18px;border-top:1px solid var(--border);flex-shrink:0;}
.sidebr-user .uname{font-size:13px;font-weight:600;margin-bottom:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sidebr-user a.logout{display:block;padding:11px 15px;color:#ff8a8a;text-decoration:none;border-radius:10px;font-size:14px;transition:.3s;}
.sidebr-user a.logout:hover{background:rgba(255,70,70,.12);}

/* ---------- MAIN (only this scrolls) ---------- */
.main-content{flex:1;padding:40px 45px;background:var(--bg);min-width:0;height:100vh;overflow-y:auto;}
.page-head{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:28px;}
.page-head h1{font-size:26px;}
.page-head .subtitle{color:var(--muted);font-size:14px;margin-top:6px;}

/* ---------- CARDS / GRID ---------- */
.card{background:var(--surface);padding:25px;border-radius:15px;margin-bottom:25px;border:1px solid var(--border);}
.grid{display:grid;gap:20px;}
.grid-2{grid-template-columns:repeat(auto-fill,minmax(340px,1fr));}
.grid-3{grid-template-columns:repeat(auto-fill,minmax(270px,1fr));}
.card-item{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;display:flex;flex-direction:column;gap:10px;}
.card-item.active{border-color:var(--accent);}
.card-item .c-head{display:flex;align-items:flex-start;gap:14px;}
.card-item .c-icon{width:44px;height:44px;flex-shrink:0;background:var(--accent-soft);color:var(--accent);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:19px;}
.card-item .c-title{font-weight:600;}
.card-item .c-sub{color:var(--muted);font-size:12px;margin-top:3px;}
.card-item .c-desc{color:var(--muted);font-size:13px;}
.card-item .c-foot{margin-top:auto;display:flex;gap:10px;flex-wrap:wrap;}
@media(max-width:900px){.grid-2,.grid-3{grid-template-columns:1fr;}}

/* ---------- STATS ---------- */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-bottom:28px;}
.stat{background:var(--surface);border:1px solid var(--border);padding:20px 24px;border-radius:16px;}
.stat p{color:var(--muted);font-size:13px;margin-bottom:8px;}
.stat h2{font-size:28px;color:var(--accent);}
.stat.warn h2{color:var(--warning);}

/* ---------- BADGES ---------- */
.badge{display:inline-block;padding:5px 11px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap;}
.badge.warning{background:rgba(240,180,41,.15);color:#f0c15a;border:1px solid rgba(240,180,41,.35);}
.badge.primary{background:var(--accent-soft);color:#6db9ff;border:1px solid rgba(29,140,248,.35);}
.badge.success{background:rgba(0,200,120,.15);color:#66e6aa;border:1px solid rgba(0,200,120,.35);}
.badge.danger{background:rgba(255,70,70,.15);color:#ff8a8a;border:1px solid rgba(255,70,70,.35);}
.badge.secondary{background:rgba(139,149,165,.15);color:#9aa4b5;border:1px solid rgba(139,149,165,.35);}

/* ---------- BUTTONS ---------- */
.btn{display:inline-block;padding:7px 14px;background:#212836;color:#c5ccd8;border:1px solid #30394a;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:.2s;line-height:1.4;}
.btn:hover{color:var(--accent);border-color:var(--accent);opacity:1;}
.btn.primary{background:var(--accent);color:#fff;border-color:var(--accent);}
.btn.primary:hover{background:#1572cd;color:#fff;}
.btn.danger{color:#ff8a8a;border-color:rgba(255,70,70,.4);}
.btn.danger:hover{background:rgba(255,70,70,.18);color:#fff;border-color:#ff4646;}
.btn.block{display:block;width:100%;text-align:center;}

/* ---------- FORMS ---------- */
.form-label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px;font-weight:600;}
.form-group{margin-bottom:16px;}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0 16px;}
.form-row .form-group{min-width:0;}
.form-control,.form-select{width:100%;background:#1b222e;border:1px solid #30394a;border-radius:9px;padding:10px 12px;color:#fff;font-size:14px;outline:none;transition:border .2s,box-shadow .2s;font-family:inherit;}
.form-control:focus,.form-select:focus{border-color:var(--accent);box-shadow:0 0 0 1px var(--accent);}
.form-control::placeholder{color:#596474;}
.form-control:disabled{background:#14181f;color:var(--muted);opacity:.85;}
select.form-select option{background:#171c26;}
textarea.form-control{resize:vertical;}
input[type="file"].form-control{padding:8px 12px;}
input[type="file"].form-control::file-selector-button{background:var(--hover);color:var(--text);border:0;border-right:1px solid var(--border);padding:.4rem .8rem;margin-right:.75rem;cursor:pointer;border-radius:0;}
input[type="file"].form-control::file-selector-button:hover{background:#2d3542;}
input[type="date"]::-webkit-calendar-picker-indicator,
input[type="time"]::-webkit-calendar-picker-indicator{filter:invert(.75);cursor:pointer;}

/* ---------- SEARCH BAR ---------- */
.search-bar{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;margin-bottom:26px;align-items:end;}
.search-bar .form-label{margin-bottom:6px;}
@media(max-width:700px){.search-bar{grid-template-columns:1fr;}}

/* ---------- ACCORDION (details/summary) ---------- */
.acc-item{background:var(--surface);border:1px solid var(--border);border-radius:12px;margin-bottom:12px;overflow:hidden;}
.acc-item>summary{list-style:none;display:flex;align-items:center;gap:14px;width:100%;padding:16px 18px;cursor:pointer;transition:background .2s;}
.acc-item>summary::-webkit-details-marker{display:none;}
.acc-item>summary:hover{background:#1b222e;}
.acc-item>summary::after{content:"";margin-left:auto;width:10px;height:10px;border-right:2px solid var(--muted);border-bottom:2px solid var(--muted);transform:rotate(45deg);transition:transform .2s;flex-shrink:0;}
.acc-item[open]>summary::after{transform:rotate(-135deg);}
.acc-item[open]>summary{background:#1b222e;}
.acc-icon{width:42px;height:42px;flex-shrink:0;background:var(--accent-soft);color:var(--accent);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;}
.acc-title strong{display:block;font-size:15px;}
.acc-title span{color:var(--muted);font-size:12px;margin-top:3px;display:block;}
.acc-body{padding:18px;border-top:1px solid var(--border);}
.meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:18px;}
.meta-grid .label{font-size:12px;color:var(--muted);margin-bottom:3px;}

/* ---------- TABS ---------- */
.tabs{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;}
.tab{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:20px;background:#1b222e;color:var(--muted);font-size:13px;border:1px solid transparent;transition:.2s;}
.tab:hover{background:var(--hover);color:var(--accent);}
.tab.active{background:var(--accent);color:#fff;}
.tab .badge{font-size:10px;padding:2px 8px;}

/* ---------- ROW ITEMS ---------- */
.row-item{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid var(--border);padding:13px 0;gap:10px;}
.row-item:last-child{border-bottom:none;}
.row-item .muted{color:var(--muted);}
.row-item .small{font-size:12px;margin-top:3px;}
.row-item .end{text-align:right;}

/* ---------- TABLE ---------- */
.table-wrap{overflow-x:auto;}
.table{width:100%;border-collapse:collapse;font-size:14px;}
.table th,.table td{padding:11px 12px;text-align:left;border-bottom:1px solid var(--border);}
.table th{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.5px;}
.table tbody tr:hover{background:rgba(33,40,54,.4);}

/* ---------- ALERTS ---------- */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;border:1px solid transparent;}
.alert.success{background:rgba(0,200,120,.12);color:var(--success);border-color:rgba(0,200,120,.35);}
.alert.danger{background:rgba(255,70,70,.12);color:#ff8a8a;border-color:rgba(255,70,70,.35);}
.alert.info{background:rgba(29,140,248,.12);color:#6db9ff;border-color:rgba(29,140,248,.35);}
.alert.warning{background:rgba(240,180,41,.12);color:#f0c15a;border-color:rgba(240,180,41,.35);}
.alert .flex{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}

/* ---------- MISC ---------- */
.empty{padding:45px 20px;text-align:center;}
.empty .big{font-size:34px;margin-bottom:12px;}
.empty h3{margin-bottom:6px;font-size:18px;}
.empty p{color:var(--muted);font-size:13px;margin-bottom:18px;}
.muted{color:var(--muted);}
.small{font-size:12px;}
hr{border:0;border-top:1px solid var(--border);margin:18px 0;}
.flex{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.flex.between{justify-content:space-between;}
.mb{margin-bottom:18px;}
.mt{margin-top:18px;}

/* ---------- DRAWER (slide-in panel) ---------- */
.drawer-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1044;opacity:0;visibility:hidden;transition:opacity .25s ease,visibility .25s ease;}
.drawer-backdrop.show{opacity:1;visibility:visible;}
.drawer{position:fixed;top:0;right:0;height:100vh;width:400px;max-width:100vw;background:var(--surface);border-left:1px solid var(--border);z-index:1045;transform:translateX(105%);transition:transform .28s ease;display:flex;flex-direction:column;}
.drawer.open{transform:translateX(0);}
.drawer-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:18px 20px;border-bottom:1px solid var(--border);flex-shrink:0;}
.drawer-head h3{font-size:16px;}
.drawer-close{background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;padding:4px 8px;border-radius:8px;transition:.2s;}
.drawer-close:hover{color:var(--text);background:var(--hover);}
.drawer-body{flex:1;overflow-y:auto;padding:18px 20px;}
.drawer-body::-webkit-scrollbar{width:8px;}
.drawer-body::-webkit-scrollbar-thumb{background:var(--border);border-radius:6px;}
@media(max-width:520px){.drawer{width:100vw;}}

/* ---------- RESPONSIVE ---------- */
@media(max-width:768px){
    .sidebar{width:70px;padding:25px 12px;}
    .sidebar a.nav-link{font-size:0;padding:14px 0;text-align:center;}
    .sidebar a.nav-link::before{content:attr(data-icon);font-size:20px;display:block;}
    .sidebar .logo{font-size:0;}
    .sidebar .logo span{font-size:22px;}
    .sidebr-user .uname{font-size:0;}
    .sidebr-user a.logout{font-size:0;padding:14px 0;text-align:center;}
    .main-content{padding:25px 20px;}
    .page-head h1{font-size:22px;}
}
@media(max-width:480px){
    .sidebar{width:60px;padding:20px 8px;}
    .main-content{padding:20px 15px;}
}
::-webkit-scrollbar{width:10px;height:10px;}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:6px;}
::selection{background:var(--accent-soft);}

.bname{font-size:17px;white-space:nowrap;}@media(max-width:768px){.bname{font-size:0 !important;}}
</style>
<?php require_once "../partials/_theme.php"; ?>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">

<div class="logo" style="display:flex;align-items:center;gap:10px;"><img src="../assets/logo.png" alt="MediConnect" style="height:44px;width:auto;max-width:100%;"><span class="bname" style="color:#f5f7fa;">MediConnect<span style="color:#1d8cf8;">.</span></span></div>

<div class="nav-wrap">
<?php foreach ($nav as [$file, $icon, $label]): ?>
<a class="nav-link<?= $current === $file ? ' active' : '' ?>" data-icon="<?= $icon ?>" href="<?= $file ?>"><?= $label ?></a>
<?php endforeach; ?>
</div>

<div class="sidebr-user">
<div class="uname"><?= htmlspecialchars($_SESSION["user_name"] ?? "My Profile") ?></div>
<a class="logout" data-icon="🚪" href="logout.php">Logout</a>
</div>

</div>

<!-- MAIN CONTENT -->
<div class="main-content">