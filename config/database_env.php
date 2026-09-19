<?php
/* ============================================================
   config/database_env.php — single source of truth for the
   MySQL connection settings, shared by every entry point
   (config/database.php, docker/migrate.php, ...).

   Resolution order (first match wins):
     1. MYSQL_URL  or  DATABASE_URL     → mysql://user:pass@host:port/db
     2. Individual variables (both spellings are supported —
        Railway injects the non-underscore form, other hosts may
        use the underscore form):
          MYSQLHOST / MYSQL_HOST
          MYSQLPORT / MYSQL_PORT
          MYSQLUSER / MYSQL_USER
          MYSQLPASSWORD / MYSQL_PASSWORD
          MYSQLDATABASE / MYSQL_DATABASE
     3. Local XAMPP defaults:
          host=localhost  port=3306  user=root  pass=''  db=mediconnect

   Usage:
     $db = mc_db_config();   // -> ['host','port','user','pass','db']
============================================================ */

/* Deterministic, bounded DB behavior for every consumer of this file:
   - turn off mysqli exception mode so a down DB is handled via
     ->connect_error instead of an uncaught fatal (PHP 8.1+ default),
   - cap each connection attempt so a blackholed/unreachable host can
     never stall startup for the default (~60s) per-attempt wait. */
mysqli_report(MYSQLI_REPORT_OFF);
ini_set('mysqli.connect_timeout', '5');

function mc_env_first(array $names, ?string $fallback = null): ?string {
    foreach ($names as $name) {
        $v = getenv($name);
        if (is_string($v) && $v !== '') {
            return $v;
        }
    }
    return $fallback;
}

function mc_db_config(): array {
    $cfg = [
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'root',
        'pass' => '',
        'db'   => 'mediconnect',
    ];

    /* 1) Full connection URL, if provided. */
    $url = mc_env_first(['MYSQL_URL', 'DATABASE_URL']);
    if ($url !== null) {
        $u = parse_url($url);
        if (is_array($u) && !empty($u['host'])) {
            $cfg['host'] = (string)$u['host'];
            if (isset($u['user'])) $cfg['user'] = urldecode((string)$u['user']);
            if (isset($u['pass'])) $cfg['pass'] = urldecode((string)$u['pass']);
            if (isset($u['path']))  $cfg['db']   = trim((string)$u['path'], '/');
            if (isset($u['port']))  $cfg['port'] = (int)$u['port'];
            return $cfg;
        }
    }

    /* 2) Individual variables (Railway + underscore spellings). */
    $cfg['host'] = mc_env_first(['MYSQLHOST', 'MYSQL_HOST'], $cfg['host']);
    $cfg['port'] = (int)mc_env_first(['MYSQLPORT', 'MYSQL_PORT'], (string)$cfg['port']);
    $cfg['user'] = mc_env_first(['MYSQLUSER', 'MYSQL_USER'], $cfg['user']);
    $cfg['pass'] = mc_env_first(['MYSQLPASSWORD', 'MYSQL_PASSWORD'], $cfg['pass']);
    $cfg['db']   = mc_env_first(['MYSQLDATABASE', 'MYSQL_DATABASE'], $cfg['db']);

    return $cfg;
}

/* Renders a friendly HTML "database unavailable" page (used when the DB
   cannot be reached at request time). Never contains credentials. */
function mc_db_error_page(string $message): void {
    if (!headers_sent()) {
        http_response_code(500);
    }
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>MediConnect — Database unavailable</title></head>'
       . '<body style="font-family:Segoe UI,Arial,sans-serif;background:#0f1319;color:#f5f7fa;margin:0;padding:48px 24px;text-align:center;">'
       . '<div style="max-width:640px;margin:0 auto;">'
       . '<h1 style="font-size:28px;">MediConnect — database unavailable</h1>'
       . '<p style="color:#9aa7b4;">The web server is running, but the database could not be reached.</p>'
       . '<p style="background:#1a212b;border:1px solid #2a3442;border-radius:8px;padding:14px;font-family:Consolas,monospace;font-size:13px;text-align:left;">' . $safe . '</p>'
       . '<p style="font-size:13px;color:#9aa7b4;">If you are the developer: make sure the MySQL service is linked to this service and that '
       . '<b>MYSQL_URL</b> (or <b>MYSQLHOST</b>, <b>MYSQLPORT</b>, <b>MYSQLUSER</b>, <b>MYSQLPASSWORD</b>, <b>MYSQLDATABASE</b>) environment '
       . 'variables are set. On local XAMPP, no variables are needed.</p>'
       . '</div></body></html>';
    exit(1);
}
?>