<?php
/* ============================================================
   config/database.php — MySQL connection (mysqli)
   ------------------------------------------------------------
   Works BOTH locally (XAMPP defaults) and on Railway:

   • Local:  no env vars set → localhost / root / '' / mediconnect
   • Railway: reads MYSQL_URL (or MYSQLHOST/MYSQLPORT/MYSQLUSER/
             MYSQLPASSWORD/MYSQLDATABASE) injected by the Railway
             MySQL plugin.

   Also leaves $host, $username, $password, $database, $port for
   user/_init.php's PDO connection.
============================================================ */

function mc_env(string $key, string $fallback): string {
    $v = getenv($key);
    return is_string($v) && $v !== '' ? $v : $fallback;
}

$dbUrl    = (string)(getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '');
$host     = mc_env('MYSQLHOST', 'localhost');
$username = mc_env('MYSQLUSER', 'root');
$password = mc_env('MYSQLPASSWORD', '');
$database = mc_env('MYSQLDATABASE', 'mediconnect');
$port     = (int)mc_env('MYSQLPORT', '3306');

/* Prefer a full connection URL when provided (mysql://user:pass@host:port/db) */
if ($dbUrl !== '') {
    $u = parse_url($dbUrl);
    if (is_array($u) && !empty($u['host'])) {
        $host     = (string)$u['host'];
        $username = isset($u['user']) ? urldecode((string)$u['user']) : $username;
        $password = isset($u['pass']) ? urldecode((string)$u['pass']) : $password;
        $database = isset($u['path']) ? trim((string)$u['path'], '/') : $database;
        $port     = isset($u['port']) ? (int)$u['port'] : $port;
    }
}

$conn = @new mysqli($host, $username, $password, $database, $port);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>