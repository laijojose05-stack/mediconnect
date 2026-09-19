<?php
/**
 * docker/migrate.php — idempotent schema bootstrap for deploy-time init.
 *
 * Runs from the container entrypoint (docker/startup.sh) before Apache starts.
 *   • Connects using the same env vars as config/database.php
 *     (MYSQL_URL, or MYSQLHOST/MYSQLPORT/MYSQLUSER/MYSQLPASSWORD/MYSQLDATABASE).
 *   • Creates the database if it does not exist.
 *   • Imports database/database.sql ONLY when the database has no tables yet.
 *     The dump's `CREATE DATABASE` / `USE mediconnect` lines are stripped so
 *     it imports cleanly into whatever database name Railway provides.
 *
 * Exit 0 = ready (imported or already present). Exit 1 = skipped/failed.
 */

function mc_env(string $key, string $fallback): string {
    $v = getenv($key);
    return is_string($v) && $v !== '' ? $v : $fallback;
}

$dbUrl = (string)(getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '');
$host  = mc_env('MYSQLHOST', 'localhost');
$user  = mc_env('MYSQLUSER', 'root');
$pass  = mc_env('MYSQLPASSWORD', '');
$db    = mc_env('MYSQLDATABASE', 'mediconnect');
$port  = (int)mc_env('MYSQLPORT', '3306');

if ($dbUrl !== '') {
    $u = parse_url($dbUrl);
    if (is_array($u) && !empty($u['host'])) {
        $host = (string)$u['host'];
        $user = isset($u['user']) ? urldecode((string)$u['user']) : $user;
        $pass = isset($u['pass']) ? urldecode((string)$u['pass']) : $pass;
        $db   = isset($u['path']) ? trim((string)$u['path'], '/') : $db;
        $port = isset($u['port']) ? (int)$u['port'] : $port;
    }
}

echo "migrate: connecting to MySQL at {$host}:{$port} (db: {$db})\n";

/* Wait up to ~60s for MySQL to accept connections (plugin provisioning). */
$mysqli = null;
for ($i = 0; $i < 30; $i++) {
    $mysqli = @new mysqli($host, $user, $pass, '', $port);
    if (!$mysqli->connect_errno) {
        break;
    }
    echo "migrate: MySQL not ready ({$mysqli->connect_error}), retrying…\n";
    sleep(2);
}
if (!$mysqli || $mysqli->connect_errno) {
    fwrite(STDERR, "migrate: cannot connect to MySQL: " . ($mysqli ? $mysqli->connect_error : '') . "\n");
    exit(1);
}

/* Try to ensure the database exists (ignore permission errors — the plugin
   usually pre-creates it, and select_db below is the real gate). */
$safeDb = str_replace('`', '``', $db);
$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

if (!$mysqli->select_db($db)) {
    fwrite(STDERR, "migrate: cannot select database '{$db}': {$mysqli->error}\n");
    exit(1);
}

$escDb  = $mysqli->real_escape_string($db);
$tables = 0;
if ($res = $mysqli->query("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = '{$escDb}'")) {
    $tables = (int)$res->fetch_assoc()['n'];
}
if ($tables > 0) {
    echo "migrate: schema already present ({$tables} tables). Skipping import.\n";
    exit(0);
}

$sqlFile = __DIR__ . '/../database/database.sql';
$sql = @file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "migrate: cannot read {$sqlFile}\n");
    exit(1);
}

/* Make the dump database-agnostic. */
$sql = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql); // CREATE DATABASE IF NOT EXISTS mediconnect;
$sql = preg_replace('/USE\s+`?[\w]+`?;/i', '', $sql);         // USE mediconnect;

if ($mysqli->multi_query($sql)) {
    do {
        if ($res = $mysqli->store_result()) {
            $res->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
}
if ($mysqli->error) {
    fwrite(STDERR, "migrate: import failed: {$mysqli->error}\n");
    exit(1);
}

$cnt = 0;
if ($res = $mysqli->query("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = '{$escDb}'")) {
    $cnt = (int)$res->fetch_assoc()['n'];
}
echo "migrate: import complete ({$cnt} tables).\n";
exit(0);