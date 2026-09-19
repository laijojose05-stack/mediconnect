<?php
require '_init.php';
require_login();

$uid = (int)$_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'all';

/* ============================================================
   Handle POST actions (cancel appt / cancel request)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'cancel_appt') {
        $st = $conn->prepare("SELECT doctor_name, appointment_date, appointment_time FROM appointments
                              WHERE id = ? AND user_id = ? AND status IN ('pending','approved')");
        if ($st) {
            $st->bind_param("ii", $id, $uid);
            $st->execute();
            $res = $st->get_result();
            if ($res && $res->num_rows) {
                $a = $res->fetch_assoc();
                $st2 = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
                $st2->bind_param("i", $id);
                $st2->execute();
                $msg = "Your appointment with {$a['doctor_name']} on {$a['appointment_date']} at {$a['appointment_time']} has been cancelled.";
                $st3 = $conn->prepare(
                    "INSERT INTO user_notifications (user_id, title, message, type, related_id)
                     VALUES (?, ?, ?, 'appointment', ?)"
                );
                $nt2 = 'Appointment cancelled';
                $st3->bind_param("issi", $uid, $nt2, $msg, $id);
                $st3->execute();
                flash('success', 'Appointment cancelled.');
            }
        }
    }
    header('Location: appointments.php?tab=' . urlencode($tab));
    exit;
}

/* ============================================================
   Load data depending on tab
============================================================ */
$apptWhere = "WHERE a.user_id = ?";

switch ($tab) {
    case 'upcoming':
        $apptWhere .= " AND a.appointment_date >= CURDATE() AND a.status IN ('pending','approved') ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        break;
    case 'pending':
        $apptWhere .= " AND a.status = 'pending' ORDER BY a.appointment_date DESC";
        break;
    case 'past':
        $apptWhere .= " AND (a.appointment_date < CURDATE() OR a.status IN ('completed','cancelled','rejected')) ORDER BY a.appointment_date DESC";
        break;
    default:
        $apptWhere .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
}

$appts = [];
$stmt = $conn->prepare("SELECT a.*, h.hospital_name FROM appointments a LEFT JOIN hospitals h ON h.id = a.hospital_id $apptWhere");
if ($stmt) {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) $appts = $res->fetch_all(MYSQLI_ASSOC);
}

/* Stats */
$statApptAll      = 0; $statApptUpcoming = 0; $statApptPending = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM appointments WHERE user_id = $uid");
if ($res) $statApptAll = (int)$res->fetch_assoc()['c'];
$res = $conn->query("SELECT COUNT(*) AS c FROM appointments WHERE user_id = $uid AND appointment_date >= CURDATE() AND status IN ('pending','approved')");
if ($res) $statApptUpcoming = (int)$res->fetch_assoc()['c'];
$res = $conn->query("SELECT COUNT(*) AS c FROM appointments WHERE user_id = $uid AND status = 'pending'");
if ($res) $statApptPending = (int)$res->fetch_assoc()['c'];

$pageTitle = 'My Activity';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>My Activity</h1>
    <p class="subtitle">All your appointments in one place.</p>
  </div>
  <a class="btn primary" href="book_appointment.php">📅 Book Appointment</a>
</div>

<?php if ($s = flash('success')): ?>
  <div class="alert success"><?= e($s) ?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs">
  <?php
  $tabs = [
      'all'      => ['All Appointments', $statApptAll],
      'upcoming' => ['Upcoming',         $statApptUpcoming],
      'pending'  => ['Pending',          $statApptPending],
      'past'     => ['Past',             ''],
  ];
  foreach ($tabs as $key => [$label, $count]): ?>
    <a class="tab<?= $tab === $key ? ' active' : '' ?>" href="?tab=<?= $key ?>">
      <?= e($label) ?>
      <?php if ($count !== ''): ?><span class="badge secondary"><?= $count ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$appts): ?>
    <div class="card empty">
      <div class="big">📅</div>
      <h3>No appointments here</h3>
      <p>Book an appointment to get started.</p>
      <a class="btn primary" href="book_appointment.php">Book Appointment</a>
    </div>
  <?php else: ?>
    <div class="grid grid-2">
      <?php foreach ($appts as $a):
          $c = 'secondary';
          switch (strtolower($a['status'])) {
              case 'pending':   $c = 'warning'; break;
              case 'approved':  $c = 'primary'; break;
              case 'completed': $c = 'success'; break;
              case 'cancelled':
              case 'rejected':  $c = 'danger';  break;
          }
          $cancellable = in_array($a['status'], ['pending','approved']) && strtotime($a['appointment_date']) >= strtotime('today');
      ?>
        <div class="card-item">
          <div class="flex between" style="align-items:flex-start;">
            <div>
              <div class="c-title"><?= e($a['doctor_name']) ?></div>
              <?php if (!empty($a['hospital_name'])): ?>
                <div class="c-sub">🏥 <?= e($a['hospital_name']) ?></div>
              <?php endif; ?>
            </div>
            <span class="badge <?= $c ?>"><?= e(ucfirst($a['status'])) ?></span>
          </div>
          <div class="small muted">📅 <?= e(date('D, d M Y', strtotime($a['appointment_date']))) ?></div>
          <div class="small muted">🕑 <?= e(date('h:i A', strtotime($a['appointment_time']))) ?></div>
          <div class="small muted">👤 <?= e($a['patient_name']) ?></div>
          <?php if ($cancellable): ?>
            <form method="post" onsubmit="return confirm('Cancel this appointment?');" class="c-foot" style="display:block;">
              <input type="hidden" name="action" value="cancel_appt">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button class="btn danger block">Cancel Appointment</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php include '_footer.php'; ?>