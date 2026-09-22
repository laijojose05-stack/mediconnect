<?php
/* Shared theme toggle: dark (default) + light. Injected into <head> on hospital pages. */
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script>
try{ var mcnTheme = localStorage.getItem("mcn-theme"); if(mcnTheme){ document.documentElement.setAttribute("data-theme", mcnTheme); } }catch(e){}
</script>

<style>

.theme-toggle{
    position:fixed;
    right:22px;
    bottom:22px;
    z-index:2000;
    width:42px;
    height:42px;
    border-radius:50%;
    border:1px solid #262d3d;
    background:#171c26;
    color:#cdd6e4;
    font-size:18px;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 4px 14px rgba(0,0,0,0.35);
    transition:border-color 0.15s ease, color 0.15s ease;
}

.theme-toggle:hover{
    border-color:#1d8cf8;
    color:#1d8cf8;
}

.theme-toggle .icon-sun,
.theme-toggle .icon-moon{
    display:none;
}

html[data-theme="dark"] .theme-toggle .icon-sun{
    display:block;
}

html[data-theme="light"] .theme-toggle .icon-moon{
    display:block;
}

[data-theme="light"] .theme-toggle{
    background:#ffffff;
    border-color:#d6dee9;
    color:#64748b;
    box-shadow:0 4px 14px rgba(15,23,42,0.12);
}

/* =============================
   LIGHT THEME OVERRIDES
============================= */

[data-theme="light"] body,
[data-theme="light"] .main-content,
[data-theme="light"] .content{
    background:#eef2f7;
    color:#1c2430;
}

[data-theme="light"] label{
    color:#526072;
}

[data-theme="light"] .sidebar{
    background:#ffffff;
    border-right:1px solid #e4e9f1;
}

[data-theme="light"] .logo{
    color:#1c2430;
}

[data-theme="light"] .sidebar a{
    color:#64748b;
}

[data-theme="light"] .sidebar a:hover,
[data-theme="light"] .sidebar a.active{
    background:#eaf1fb;
    color:#1d8cf8;
}

[data-theme="light"] .card,
[data-theme="light"] .stat{
    background:#ffffff;
    border:1px solid #e4e9f1;
}

[data-theme="light"] .schedule-row,
[data-theme="light"] .doc-card{
    background:#ffffff;
    border:1px solid #e4e9f1;
}

[data-theme="light"] th,
[data-theme="light"] td{
    border-color:#e8edf4;
}

[data-theme="light"] th{
    color:#7a8699;
}

[data-theme="light"] .header p,
[data-theme="light"] .card-subtitle,
[data-theme="light"] .specialization,
[data-theme="light"] .row-item .muted{
    color:#7a8699;
}

[data-theme="light"] .time-text{
    color:#556375;
}

[data-theme="light"] .doc-card-name{
    color:#1c2430;
}

[data-theme="light"] .day-off,
[data-theme="light"] .empty{
    color:#98a2b0;
}

[data-theme="light"] .btn{
    background:#e3e9f1;
    border-color:#d6dee9;
    color:#334155;
}

[data-theme="light"] .btn:hover{
    background:#d8e0ea;
}

[data-theme="light"] .btn.link{
    background:transparent;
    border-color:transparent;
    color:#1d8cf8;
}

[data-theme="light"] .tab-btn{
    background:#e9eef5;
    color:#475569;
}

[data-theme="light"] .tab-btn.active{
    background:#1d8cf8;
    color:#ffffff;
}

[data-theme="light"] .tab-btn.active:hover{
    background:#1572cd;
}

[data-theme="light"] input,
[data-theme="light"] select,
[data-theme="light"] textarea{
    background:#ffffff;
    border-color:#d6dee9;
    color:#1c2430;
}

[data-theme="light"] .day-label{
    background:#e9eef5;
    color:#475569;
}

[data-theme="light"] .day-option input:checked + .day-label{
    background:rgba(29,140,248,0.18);
    color:#1d8cf8;
}

[data-theme="light"] .chip{
    background:rgba(29,140,248,0.10);
    border-color:rgba(29,140,248,0.30);
}

[data-theme="light"] .chip-time{
    color:#4a8fd0;
}

[data-theme="light"] .doc-avatar,
[data-theme="light"] .doc-card-avatar{
    background:rgba(29,140,248,0.10);
    border-color:rgba(29,140,248,0.35);
}

[data-theme="light"] .badge.primary{
    color:#1d8cf8;
}

[data-theme="light"] .badge.success,
[data-theme="light"] .approved,
[data-theme="light"] .message{
    color:#12925f;
}

[data-theme="light"] .badge.warning,
[data-theme="light"] .pending,
[data-theme="light"] .leave{
    color:#b57a00;
}

[data-theme="light"] .badge.danger,
[data-theme="light"] .badge.secondary{
    color:#7a8699;
}

[data-theme="light"] .rejected,
[data-theme="light"] .unavailable,
[data-theme="light"] .error{
    color:#d64545;
}

[data-theme="light"] .delete-btn,
[data-theme="light"] .delete-link,
[data-theme="light"] .clear-link,
[data-theme="light"] .mini-delete,
[data-theme="light"] .chip-x,
[data-theme="light"] .reject-btn{
    color:#d64545;
}

/* =============================
   USER MODULE (CSS-variable based)
============================= */

html[data-theme="light"]{
    --bg:#eef2f7;
    --surface:#ffffff;
    --surface-2:#e9eef5;
    --border:#e4e9f1;
    --hover:#eaf1fb;
    --accent:#1d8cf8;
    --accent-soft:rgba(29,140,248,0.10);
    --text:#1c2430;
    --muted:#7a8699;
    --success:#12925f;
    --warning:#b57a00;
    --danger:#d64545;
}

[data-theme="light"] .subtitle{
    color:#7a8699;
}

[data-theme="light"] .btn.primary{
    background:#1d8cf8;
    border-color:#1d8cf8;
    color:#ffffff;
}

[data-theme="light"] .btn.primary:hover{
    background:#1572cd;
    color:#ffffff;
}

[data-theme="light"] .btn.danger{
    color:#d64545;
    border-color:rgba(255,70,70,0.4);
    background:transparent;
}

[data-theme="light"] .btn.danger:hover{
    background:rgba(255,70,70,0.18);
    color:#ffffff;
}

[data-theme="light"] .tab{
    background:#e9eef5;
    color:#475569;
}

[data-theme="light"] .tab.active{
    background:#1d8cf8;
    color:#ffffff;
}

[data-theme="light"] .table tbody tr:hover,
[data-theme="light"] .acc-item>summary:hover,
[data-theme="light"] .acc-item[open]>summary{
    background:#f1f5fa;
}

[data-theme="light"] select option,
[data-theme="light"] .form-select option{
    background:#ffffff;
    color:#1c2430;
}

[data-theme="light"] .form-control:disabled{
    background:#f1f5fa;
    color:#7a8699;
}

[data-theme="light"] input[type="time"]::-webkit-calendar-picker-indicator{
    filter:none;
}

[data-theme="light"] .alert.danger{
    color:#d64545;
}

[data-theme="light"] .alert.info{
    color:#1d8cf8;
}

[data-theme="light"] .alert.warning{
    color:#b57a00;
}

/* =============================
   ADMIN MODULE
============================= */

[data-theme="light"] .main{
    background:#eef2f7;
}

[data-theme="light"] .menu a,
[data-theme="light"] .logout a{
    color:#64748b;
}

[data-theme="light"] .menu a:hover,
[data-theme="light"] .menu a.active{
    background:#eaf1fb;
    color:#1d8cf8;
}

/* =============================
   AUTH PAGES (.app-card)
============================= */

[data-theme="light"] .app-card{
    background:#ffffff;
    border-color:#e4e9f1;
    box-shadow:0 25px 50px -12px rgba(15,23,42,0.15);
}

[data-theme="light"] .nav-brand{
    color:#1c2430;
}

[data-theme="light"] .bottom-logo{
    color:#1c2430;
}

[data-theme="light"] .input-box{
    background:#f1f5fa;
}

[data-theme="light"] .input-box input{
    color:#1c2430;
}

[data-theme="light"] .input-box input::placeholder{
    color:#98a2b0;
}

[data-theme="light"] .remember-me,
[data-theme="light"] .signup-text,
[data-theme="light"] .description{
    color:#64748b;
}

[data-theme="light"] .error{
    background:#fdeaea;
    color:#c0392b;
    border-color:#f2c2c2;
}

[data-theme="light"] .success{
    background:#eafaf2;
    color:#12925f;
    border-color:#bfe8d6;
}

/* =============================
   ROOT MARKETING PAGES
============================= */

[data-theme="light"] .nav-links a{
    color:#526072;
}

[data-theme="light"] .nav-links a:hover,
[data-theme="light"] .nav-links a.active{
    color:#1c2430;
}

[data-theme="light"] .login-btn,
[data-theme="light"] .secondary-btn{
    color:#334155;
    border-color:#d6dee9;
}

[data-theme="light"] .login-btn:hover,
[data-theme="light"] .secondary-btn:hover{
    color:#1d8cf8;
    border-color:#1d8cf8;
}

[data-theme="light"] .hero-text,
[data-theme="light"] .hero-description,
[data-theme="light"] .section-heading p,
[data-theme="light"] .section-description{
    color:#64748b;
}

[data-theme="light"] .badge{
    color:#1d8cf8;
}

[data-theme="light"] .floating-card{
    background:#ffffff;
    border-color:#e4e9f1;
    box-shadow:0 15px 40px rgba(15,23,42,0.12);
}

[data-theme="light"] .mini-title{
    color:#64748b;
}

[data-theme="light"] .search-select,
[data-theme="light"] .search-input{
    background:#ffffff;
    border-color:#d6dee9;
    color:#1c2430;
}

[data-theme="light"] .search-input::placeholder{
    color:#98a2b0;
}

[data-theme="light"] .service-card,
[data-theme="light"] .step,
[data-theme="light"] .info-card,
[data-theme="light"] .form-card,
[data-theme="light"] .mission-card,
[data-theme="light"] .why-card,
[data-theme="light"] .intro-box{
    background:#ffffff;
    border-color:#e4e9f1;
}

[data-theme="light"] .service-card p,
[data-theme="light"] .step p,
[data-theme="light"] .info-card>p,
[data-theme="light"] .form-card>p{
    color:#64748b;
}

[data-theme="light"] .cta-box{
    background:linear-gradient(135deg,#ffffff,#f1f5fa);
    border-color:#d6dee9;
}

[data-theme="light"] .cta-box p{
    color:#64748b;
}

[data-theme="light"] .footer-description,
[data-theme="light"] .footer-column a{
    color:#64748b;
}

[data-theme="light"] .footer-bottom{
    color:#596372;
}

[data-theme="light"] .footer-grid{
    border-color:#e4e9f1;
}

/* ============ MOBILE RESPONSIVE HARDENING (all modules) ============ */

/* Hamburger + mobile nav (any .navbar with .nav-links) */
.nav-toggle{
    display:none;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    gap:4px;
    width:42px;
    height:42px;
    border-radius:10px;
    border:1px solid #262d3d;
    background:#171c26;
    color:#e6edf3;
    cursor:pointer;
    transition:.2s;
    flex-shrink:0;
}
.nav-toggle:hover{border-color:#1d8cf8;}
.nav-toggle span{display:block;width:18px;height:2px;background:#e6edf3;border-radius:2px;transition:.2s;}
.nav-toggle[aria-expanded="true"] span:nth-child(1){transform:translateY(6px) rotate(45deg);}
.nav-toggle[aria-expanded="true"] span:nth-child(2){opacity:0;}
.nav-toggle[aria-expanded="true"] span:nth-child(3){transform:translateY(-6px) rotate(-45deg);}
.nav-menu-mobile{
    display:none;
    width:100%;
    background:rgba(15,19,25,.97);
    backdrop-filter:blur(15px);
    border-top:1px solid #202733;
}
.nav-menu-mobile.open{display:block;}
.nav-menu-mobile a{
    display:block;
    padding:13px 18px;
    color:#aeb7c5;
    border-bottom:1px solid #202733;
    font-size:14px;
    font-weight:600;
}
.nav-menu-mobile a:hover,
.nav-menu-mobile a.active{color:#fff;background:rgba(255,255,255,.05);}
.nav-menu-mobile .nav-actions{flex-direction:column;align-items:stretch;gap:8px;padding:12px 18px;border-bottom:1px solid #202733;}
.nav-menu-mobile .nav-actions,
.nav-menu-mobile div[class]:not(.nav-menu-mobile) .login-btn,
.nav-menu-mobile .login-btn,
.nav-menu-mobile .primary-btn{display:inline-flex!important;justify-content:center;text-align:center;width:100%;}
.navbar{flex-wrap:wrap;}

/* Page headers / action buttons wrap on narrow screens */
.header,.page-header{flex-wrap:wrap;}
.right-actions{max-width:100%;}
@media(max-width:520px){
    .right-actions{width:100%;}
    .right-btn{flex:1;justify-content:center;padding:10px 10px;font-size:13px;}
}

@media (max-width: 980px){
    .nav-links{display:none!important;}
    .nav-toggle{display:flex!important;}
    .navbar{height:auto!important;min-height:64px;padding-top:10px;padding-bottom:10px;}
    .navbar .logo img{height:44px!important;}
}

[data-theme="light"] .nav-menu-mobile{background:rgba(248,250,252,.97);}
[data-theme="light"] .nav-menu-mobile a{color:#526072;border-color:#e4e9f1;}
[data-theme="light"] .nav-menu-mobile a:hover,
[data-theme="light"] .nav-menu-mobile a.active{color:#1c2430;background:rgba(15,23,42,.05);}

@media (max-width: 750px){
    .sidebar .menu a{
        font-size:0;
        text-align:center;
        padding:13px 0;
    }
    .sidebar .menu a::first-letter{
        font-size:18px;
    }
    .sidebar .logout a{
        font-size:0;
        text-align:center;
    }
    .sidebar .logout a::first-letter{
        font-size:16px;
    }
}

.table-wrap,
.table-wrapper{
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
}

table{
    max-width:100%;
}

img{max-width:100%;height:auto;}

@media(max-width:600px){
    .hero h1{font-size:clamp(36px,9vw,48px);letter-spacing:-1.5px;}
    .hero-image{height:320px;border-radius:18px;}
    .card-one,.card-two{position:relative;left:auto;right:auto;bottom:auto;top:auto;width:auto;margin-top:12px;}
    .hero-visual{display:flex;flex-direction:column;gap:12px;}
    .search-box{flex-direction:column;padding:12px;}
    .search-select,.search-input,.search-btn{width:100%;}
    .search-btn{height:48px;}
    .container{width:min(1180px,94%);}
    .cta-box{padding:40px 20px;}
    .cta-box h2{font-size:26px;}
    .section-heading h2{font-size:27px;}
    .footer-grid{grid-template-columns:1fr;gap:24px;}
    .footer-bottom{flex-direction:column;gap:8px;}
    .logo img{height:48px;}
}

/* =============================
   MOBILE-FIRST DASHBOARD
   (bottom navigation replaces sidebar on phones)
============================= */

/* Bottom nav — hidden by default, shown on phones */
.mcn-bnav{display:none;}
.mcn-sheet{display:none;}

[data-theme="light"] .mcn-bnav-item{color:#526072;}
[data-theme="light"] .mcn-bnav-item.active{color:#1d8cf8;background:rgba(29,140,248,.12);}
[data-theme="light"] .mcn-bnav{background:rgba(248,250,252,.97);border-color:#e4e9f1;}
[data-theme="light"] .mcn-sheet{background:#ffffff;border-color:#e4e9f1;}
[data-theme="light"] .mcn-sheet-item{color:#334155;}
[data-theme="light"] .mcn-sheet-item:hover,
[data-theme="light"] .mcn-sheet-item.active{background:#eaf1fb;color:#1d8cf8;}

@media(max-width:768px){
    html,body{overflow-x:hidden;}

    /* Hide the big sidebar on phones */
    .sidebar{display:none!important;}

    /* Content takes the full width */
    .content,.main{width:100%!important;max-width:100%!important;margin-left:0!important;padding:18px 16px 90px!important;}
    .main-content{width:100%!important;max-width:100%!important;margin-left:0!important;padding:18px 16px 90px!important;}

    /* Compact headers */
    .header,.page-header,.page-head{margin-bottom:20px;}
    .header h1,.page-header h1,.page-head h1{font-size:22px;line-height:1.25;}
    .header p,.subtitle{font-size:13px;margin-top:4px;}
    .right-actions{width:100%;justify-content:stretch;}
    .right-btn{flex:1;justify-content:center;padding:11px 8px;font-size:13px;}

    /* Responsive cards */
    .stats,.cards{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;}
    .stat,.card{padding:16px;}
    .stat h2{font-size:26px;}
    .grid-2,.grid-3,.side-grid{grid-template-columns:1fr!important;}
    .row-2{grid-template-columns:1fr;}

    /* Bottom navigation bar */
    .mcn-bnav{
        display:flex;
        position:fixed;
        left:0;right:0;bottom:0;
        z-index:2050;
        align-items:stretch;
        justify-content:space-around;
        background:rgba(15,19,25,.97);
        backdrop-filter:blur(18px);
        border-top:1px solid #262d3d;
        padding:6px 4px;
        padding-bottom:calc(6px + env(safe-area-inset-bottom));
        box-shadow:0 -8px 30px rgba(0,0,0,.35);
    }
    .mcn-bnav-item{
        flex:1;
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
        gap:3px;
        min-height:52px;
        padding:6px 2px;
        border-radius:12px;
        color:#8b95a5;
        text-decoration:none!important;
        font-size:10px;
        font-weight:600;
        letter-spacing:.2px;
        transition:background .15s,color .15s;
        -webkit-tap-highlight-color:transparent;
    }
    .mcn-bnav-item i{font-size:20px;line-height:1;}
    .mcn-bnav-item span{
        max-width:100%;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
        padding:0 2px;
    }
    .mcn-bnav-item.active{color:#1d8cf8;background:rgba(29,140,248,.12);}
    .mcn-bnav-item:active{transform:scale(.96);}

    /* More sheet */
    .mcn-backdrop{
        position:fixed;inset:0;z-index:2060;
        background:rgba(0,0,0,.55);
        opacity:0;visibility:hidden;
        transition:opacity .25s ease,visibility .25s ease;
    }
    .mcn-backdrop.show{opacity:1;visibility:visible;}
    .mcn-sheet{
        display:block;
        position:fixed;left:0;right:0;bottom:0;z-index:2061;
        background:#14181f;
        border:1px solid #262d3d;
        border-bottom:none;
        border-radius:20px 20px 0 0;
        padding:16px 12px;
        padding-bottom:calc(16px + env(safe-area-inset-bottom));
        transform:translateY(105%);
        transition:transform .28s ease;
        max-height:74vh;
        overflow-y:auto;
        -webkit-overflow-scrolling:touch;
    }
    .mcn-sheet.open{transform:translateY(0);}
    .mcn-sheet-head{display:flex;align-items:center;justify-content:space-between;padding:4px 6px 12px;}
    .mcn-sheet-head h3{font-size:16px;}
    .mcn-sheet-close{background:none;border:none;color:#8b95a5;font-size:22px;cursor:pointer;padding:4px 8px;border-radius:8px;line-height:1;}
    .mcn-sheet-close:hover{color:#fff;background:#212836;}
    .mcn-sheet-item{
        display:flex;
        align-items:center;
        gap:14px;
        padding:14px 12px;
        margin-bottom:4px;
        border-radius:12px;
        color:#cdd5e0;
        text-decoration:none!important;
        font-size:14px;
        font-weight:600;
        -webkit-tap-highlight-color:transparent;
    }
    .mcn-sheet-item i{font-size:19px;width:24px;text-align:center;color:#1d8cf8;}
    .mcn-sheet-item:hover,
    .mcn-sheet-item.active{background:#212836;color:#fff;}

    .theme-toggle{bottom:calc(84px + env(safe-area-inset-bottom));}
}
</style>

<script>
/* Hamburger menu for marketing navbar (.nav-links lives inside .navbar) */
(function(){
    function initNav(){
        var bar = document.querySelector(".navbar");
        if(!bar){ return; }
        var links = bar.querySelector(".nav-links");
        var actions = bar.querySelector(".nav-actions");
        if(!links){ return; }
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "nav-toggle";
        btn.setAttribute("aria-label","Toggle menu");
        btn.setAttribute("aria-expanded","false");
        btn.innerHTML = "<span></span><span></span><span></span>";
        bar.appendChild(btn);
        var menu = document.createElement("div");
        menu.className = "nav-menu-mobile";
        var linkClone = links.cloneNode(true);
        linkClone.classList.remove("nav-links");
        linkClone.classList.add("nav-mobile-links");
        menu.appendChild(linkClone);
        if(actions){
            var act = actions.cloneNode(true);
            act.classList.remove("nav-actions");
            menu.appendChild(act);
        }
        bar.appendChild(menu);
        bar.appendChild(btn);
        btn.addEventListener("click", function(){
            var open = menu.classList.toggle("open");
            btn.setAttribute("aria-expanded", open ? "true" : "false");
        });
        menu.querySelectorAll("a").forEach(function(a){
            a.addEventListener("click", function(){
                menu.classList.remove("open");
                btn.setAttribute("aria-expanded","false");
            });
        });
    }
    if(document.readyState !== "loading"){ initNav(); }
    else { document.addEventListener("DOMContentLoaded", initNav); }
})();

(function(){
    function wrapTables(){
        document.querySelectorAll("table").forEach(function(t){
            var p = t.parentNode;
            if(p && (p.classList.contains("table-wrap") || p.classList.contains("table-wrapper"))){ return; }
            var w = document.createElement("div");
            w.className = "table-wrap";
            if(p){ p.insertBefore(w, t); }
            w.appendChild(t);
        });
    }
    if(document.readyState !== "loading"){ wrapTables(); }
    else { document.addEventListener("DOMContentLoaded", wrapTables); }
})();

/* Mobile bottom nav built from the existing .sidebar (dashboards only) */
(function(){
    function cleanLabel(s){
        return (s || "")
            .replace(/\p{Extended_Pictographic}/gu, "")
            .replace(/\s+/g, " ")
            .trim();
    }
    function iconFor(href){
        var f = (href || "").split("/").pop().split("?")[0];
        var map = {
            "dashboard.php":"bi-grid-1x2-fill",
            "doctors.php":"bi-person-heart",
            "departments.php":"bi-building",
            "appointments.php":"bi-calendar2-check",
            "availability.php":"bi-clock-history",
            "logout.php":"bi-box-arrow-right",
            "users.php":"bi-people-fill",
            "hospitals.php":"bi-hospital",
            "pharmacies.php":"bi-shop",
            "medicines.php":"bi-capsule",
            "reports.php":"bi-bar-chart-line",
            "my_requests.php":"bi-inbox",
            "catalogue.php":"bi-capsule",
            "stock.php":"bi-box-seam",
            "stock_edit.php":"bi-clipboard",
            "requests.php":"bi-inbox",
            "request_medicine.php":"bi-capsule",
            "book_appointment.php":"bi-calendar-plus",
            "profile.php":"bi-person-circle"
        };
        return "bi " + (map[f] || "bi-circle");
    }
    function initBottomNav(){
        var sb = document.querySelector(".sidebar");
        if(!sb){ return; }
        var links = sb.querySelectorAll("a[href]");
        var items = [], seen = {};
        links.forEach(function(a){
            var href = a.getAttribute("href");
            if(!href || href.charAt(0) === "#"){ return; }
            var key = href.split("/").pop().split("?")[0];
            if(seen[key]){ return; }
            seen[key] = 1;
            items.push({ href: href, label: cleanLabel(a.textContent) || key, icon: iconFor(href) });
        });
        if(!items.length){ return; }
        var cur = location.pathname.split("/").pop().split("?")[0];
        function isActive(href){
            return href.split("/").pop().split("?")[0] === cur;
        }
        var primary = [], secondary = [];
        items.forEach(function(it){
            if(/logout/i.test(it.label)){ secondary.push(it); }
            else if(primary.length < 4){ primary.push(it); }
            else{ secondary.push(it); }
        });

        var nav = document.createElement("nav");
        nav.className = "mcn-bnav";
        nav.setAttribute("aria-label", "Primary");
        primary.forEach(function(it){
            var a = document.createElement("a");
            a.className = "mcn-bnav-item" + (isActive(it.href) ? " active" : "");
            a.href = it.href;
            a.innerHTML = '<i class="' + it.icon + '"></i><span></span>';
            a.querySelector("span").textContent = it.label;
            nav.appendChild(a);
        });

        if(secondary.length){
            var moreBtn = document.createElement("button");
            moreBtn.type = "button";
            moreBtn.className = "mcn-bnav-item mcn-more";
            moreBtn.setAttribute("aria-haspopup", "true");
            moreBtn.innerHTML = '<i class="bi bi-three-dots"></i><span>More</span>';
            nav.appendChild(moreBtn);

            var backdrop = document.createElement("div");
            backdrop.className = "mcn-backdrop";
            var sheet = document.createElement("div");
            sheet.className = "mcn-sheet";
            var head = document.createElement("div");
            head.className = "mcn-sheet-head";
            head.innerHTML = '<h3>Menu</h3><button type="button" class="mcn-sheet-close" aria-label="Close">&times;</button>';
            sheet.appendChild(head);
            items.forEach(function(it){
                var a = document.createElement("a");
                a.className = "mcn-sheet-item" + (isActive(it.href) ? " active" : "");
                a.href = it.href;
                a.innerHTML = '<i class="' + it.icon + '"></i>';
                a.appendChild(document.createTextNode(it.label));
                sheet.appendChild(a);
            });
            document.body.appendChild(backdrop);
            document.body.appendChild(sheet);

            function open(){ sheet.classList.add("open"); backdrop.classList.add("show"); }
            function close(){ sheet.classList.remove("open"); backdrop.classList.remove("show"); }
            moreBtn.addEventListener("click", open);
            head.querySelector(".mcn-sheet-close").addEventListener("click", close);
            backdrop.addEventListener("click", close);
            document.addEventListener("keydown", function(e){ if(e.key === "Escape"){ close(); } });
        }
        document.body.appendChild(nav);
    }
    if(document.readyState !== "loading"){ initBottomNav(); }
    else { document.addEventListener("DOMContentLoaded", initBottomNav); }
})();
</script>

<script>
(function(){
    function applyTheme(t){
        document.documentElement.setAttribute("data-theme", t);
        try{ localStorage.setItem("mcn-theme", t); }catch(e){}
    }
    function init(){
        var btn = document.getElementById("themeToggle");
        if(!btn){ return; }
        btn.addEventListener("click", function(){
            var cur = document.documentElement.getAttribute("data-theme") === "light" ? "dark" : "light";
            applyTheme(cur);
        });
    }
    if(document.readyState !== "loading"){ init(); } else { document.addEventListener("DOMContentLoaded", init); }
})();
</script>

<button id="themeToggle" class="theme-toggle" title="Toggle theme" aria-label="Toggle light/dark theme">
<span class="icon-sun">&#9728;</span><span class="icon-moon">&#9790;</span>
</button>