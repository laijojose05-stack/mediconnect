<?php
/* ============================================================
   config/database.php — MySQL connection (mysqli) for the app.
   ------------------------------------------------------------
   Connection settings come from config/database_env.php, which
   resolves Railway env vars (MYSQL_URL, MYSQL_* / MYSQL*) and
   falls back to local XAMPP defaults (localhost / root / '' /
   mediconnect) when none are set.

   Exposes $host, $username, $password, $database, $port and $conn
   for the rest of the app (including user/_init.php's PDO DSN).
============================================================ */

require_once __DIR__ . '/database_env.php';

$mcDb      = mc_db_config();
$host      = $mcDb['host'];
$username  = $mcDb['user'];
$password  = $mcDb['pass'];
$database  = $mcDb['db'];
$port      = (int)$mcDb['port'];

/* PHP 8.1+ enables mysqli exceptions by default; turn them off and use
   connect_error so a down DB renders the friendly error page instead of
   throwing a raw fatal exception. */
mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($host, $username, $password, $database, $port);
if ($conn->connect_error) {
    mc_db_error_page("Database Connection Failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
?>