<?php
/**
 * docker/migrate.php — idempotent schema bootstrap for deploy-time init.
 *
 * Runs from the container entrypoint (docker/startup.sh) before Apache starts.
 *   • Resolves the DB connection exactly like the app does
 *     (config/database_env.php: MYSQL_URL / MYSQL_* env vars → XAMPP defaults).
 *   • Creates the database if it does not exist.
 *   • Imports database/database.sql ONLY when the database has no tables yet.
 *     The dump's `CREATE DATABASE` / `USE mediconnect` lines are stripped so
 *     it imports cleanly into whatever database name Railway provides.
 *   • Never crashes the app: on any failure it exits 1 and startup.sh simply
 *     skips the init and boots the web server anyway (see docker/startup.sh).
 *
 * Exit 0 = ready (imported or already present). Exit 1 = skipped/failed.
 */

/* Return connection errors (no exceptions) so we can manage retries ourselves. */
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/../config/database_env.php';

$cfg  = mc_db_config();
$host = $cfg['host'];
$port = (int)$cfg['port'];
$user = $cfg['user'];
$pass = $cfg['pass'];
$db   = $cfg['db'];

echo "migrate: connecting to MySQL at {$host}:{$port} (db: {$db})\n";

/* Wait up to ~80s for MySQL to accept connections (plugin provisioning).
   If the host is reachable but credentials are wrong it fails fast
   instead of waiting the full window. */
$mysqli = null;
for ($i = 0; $i < 40; $i++) {
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