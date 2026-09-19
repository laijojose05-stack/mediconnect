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
?>