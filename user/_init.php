<?php
// user/_init.php — session, DB, helpers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php'; // provides $conn (mysqli) + $host/$username/$password/$database/$port

$host     = $host     ?? 'localhost';
$db       = $database ?? 'mediconnect';
$user     = $username ?? 'root';
$pass     = $password ?? '';
$port     = $port     ?? 3306;
$charset  = 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=$charset",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/* ---------- Helpers ---------- */
function e($s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function flash(string $key, ?string $msg = null) {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return null; }
    $m = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $m;
}

function current_user(PDO $pdo): ?array {
    if (!is_logged_in()) return null;
    $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$_SESSION['user_id']]);
    return $st->fetch() ?: null;
}

/* ---------- Auto-cancel past appointments ---------- */
if (is_logged_in() && isset($conn)) {
    $auto_cancel_uid = (int)$_SESSION['user_id'];
    $st = $conn->query(
        "UPDATE appointments
         SET status = 'cancelled'
         WHERE user_id = $auto_cancel_uid
         AND appointment_date < CURDATE()
         AND status IN ('pending','approved')"
    );
    if (!$st) { /* ignore DB failure */ }
}

/* ---------- Keep session name in sync with DB ---------- */
if (is_logged_in()) {
    try {
        $st = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $st->execute([(int)$_SESSION['user_id']]);
        $name = $st->fetchColumn();
        if ($name !== false && $name !== null && $name !== '') {
            $_SESSION['user_name'] = $name;
        }
    } catch (PDOException $e) { /* ignore */ }
}

function nav_active(string $page): string {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}

function unread_notifications(PDO $pdo, int $uid): int {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
        $st->execute([$uid]);
        return (int)$st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function ensure_stock_table(PDO $pdo): void {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pharmacy_medicines (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                pharmacy_id  INT NOT NULL,
                medicine_id  INT NOT NULL,
                price        DECIMAL(10,2) DEFAULT NULL,
                stock        INT DEFAULT 0,
                created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_pharm_med (pharmacy_id, medicine_id),
                CONSTRAINT fk_pm_pharm FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE,
                CONSTRAINT fk_pm_med   FOREIGN KEY (medicine_id) REFERENCES medicines(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) { /* ignore */ }
}