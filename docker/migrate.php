<?php
/**
 * docker/migrate.php — idempotent schema bootstrap for deploy-time init.
 *
 * Launched by docker/startup.sh as a BACKGROUND task AFTER Apache is up,
 * so it can never block the web server.
 *   • Resolves the DB connection exactly like the app does
 *     (config/database_env.php: MYSQL_URL / MYSQL_* env vars → XAMPP defaults).
 *   • Creates the database if it does not exist.
 *   • Imports database/database.sql ONLY when the database has no tables yet.
 *     The dump's `CREATE DATABASE` / `USE mediconnect` lines are stripped so
 *     it imports cleanly into whatever database name Railway provides.
 *   • Never blocks the app: on any failure it exits 1 after a bounded retry
 *     window while Apache keeps serving (see docker/startup.sh).
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

$foundEnv = [];
foreach (['MYSQL_URL','DATABASE_URL','MYSQLHOST','MYSQL_HOST','MYSQLPORT','MYSQL_PORT','MYSQLUSER','MYSQL_USER','MYSQLPASSWORD','MYSQL_PASSWORD','MYSQLDATABASE','MYSQL_DATABASE'] as $k) {
    $v = getenv($k);
    if (is_string($v) && $v !== '') $foundEnv[] = $k;
}
echo "migrate: env vars found: " . (count($foundEnv) ? implode(', ', $foundEnv) : 'NONE — using localhost defaults') . "\n";
echo "migrate: connecting to MySQL at {$host}:{$port} (db: {$db})\n";

/* Bounded wait (~60s total). This runs in the BACKGROUND from startup.sh,
   so the web server is already up — we only retry while the MySQL plugin
   finishes provisioning. Never blocks startup: on timeout we give up and
   let the app show a clear database error instead. */
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
    fwrite(STDERR, "migrate: giving up after ~60s — MySQL is unreachable. "
        . ($mysqli ? $mysqli->connect_error : '') . "\n");
    fwrite(STDERR, "migrate: the web server is already running; app pages that need the DB will show a database error.\n");
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

/* Heal the default admin account (idempotent, safe):
   older dumps stored the seed password as PLAINTEXT, which
   admin/login.php's password_verify() rejects. If the stored value is
   not a real password hash, replace it with a bcrypt hash of the seed
   password. A password an admin later CHANGED (valid hash) is never
   overwritten. Default seed: admin@mediconnect.com / admin123. */
$seedEmail = 'admin@mediconnect.com';
$seedPass  = 'admin123';
$adminId   = null;
$storedPw  = null;
if ($stmt = $mysqli->prepare("SELECT id, password FROM admins WHERE email = ?")) {
    $stmt->bind_param('s', $seedEmail);
    $stmt->execute();
    $stmt->bind_result($adminId, $storedPw);
    $stmt->fetch();
    /* free + close the SELECT BEFORE the connection is reused,
       otherwise the next query fails with "Commands out of sync". */
    $stmt->free_result();
    $stmt->close();
}
$isHash = is_string($storedPw)
    && (strncmp($storedPw, '$2y$', 4) === 0 || strncmp($storedPw, '$argon2', 7) === 0);
if ($adminId !== null && !$isHash) {
    $newHash = password_hash($seedPass, PASSWORD_DEFAULT);
    if ($upd = $mysqli->prepare("UPDATE admins SET password = ? WHERE id = ?")) {
        $upd->bind_param('si', $newHash, $adminId);
        if ($upd->execute()) {
            echo "migrate: default admin password repaired (was plaintext).\n";
        }
        $upd->close();
    }
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