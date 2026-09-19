<?php
/* Shared theme toggle: dark (default) + light. Injected into <head> on hospital pages. */
?>
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

</style>

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