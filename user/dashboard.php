<?php
require '_init.php';
require_login();

$uid = (int)$_SESSION['user_id'];

/* ---------- Stats ---------- */
$appts = 0; $reqs = 0;
$result = $conn->query("SELECT COUNT(*) AS c FROM appointments WHERE user_id = $uid");
if ($result) $appts = (int)$result->fetch_assoc()['c'];
$result = $conn->query("SELECT COUNT(*) AS c FROM medicine_requests WHERE user_id = $uid");
if ($result) $reqs = (int)$result->fetch_assoc()['c'];
$unread = unread_notifications($pdo, $uid);

/* ---------- Recent appointments ---------- */
$recentAppts = [];
$result = $conn->query(
    "SELECT a.*, h.hospital_name
     FROM appointments a
     LEFT JOIN hospitals h ON h.id = a.hospital_id
     WHERE a.user_id = $uid
     ORDER BY a.created_at DESC
     LIMIT 5"
);
if ($result) $recentAppts = $result->fetch_all(MYSQLI_ASSOC);

/* ---------- Recent requests ---------- */
$recentReqs = [];
$result = $conn->query(
    "SELECT mr.*, m.medicine_name, p.pharmacy_name
     FROM medicine_requests mr
     JOIN medicines m  ON m.id = mr.medicine_id
     JOIN pharmacies p ON p.id = mr.pharmacy_id
     WHERE mr.user_id = $uid
     ORDER BY mr.created_at DESC
     LIMIT 5"
);
if ($result) $recentReqs = $result->fetch_all(MYSQLI_ASSOC);

/* ---------- Handle POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nid    = (int)($_POST['id'] ?? 0);

    if ($action === 'notif_read' && $nid) {
        $st = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $st->bind_param("ii", $nid, $uid);
        $st->execute();
        header('Location: dashboard.php#notifications');
        exit;
    } elseif ($action === 'notif_read_all') {
        $st = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?");
        $st->bind_param("i", $uid);
        $st->execute();
        flash('success', 'All notifications marked as read.');
        header('Location: dashboard.php#notifications');
        exit;
    } elseif ($action === 'notif_delete' && $nid) {
        $st = $conn->prepare("DELETE FROM user_notifications WHERE id = ? AND user_id = ?");
        $st->bind_param("ii", $nid, $uid);
        $st->execute();
        header('Location: dashboard.php#notifications');
        exit;
    } elseif ($action === 'profile_update') {
        $name    = trim($_POST['name']    ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            flash('profile_error', 'Name is required.');
        } else {
            $st = $conn->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
            if ($st) {
                $st->bind_param("sssi", $name, $phone, $address, $uid);
                $st->execute();
                $_SESSION['user_name'] = $name;
            }

            $u = current_user($pdo);
            $pw = $_POST['new_password'] ?? '';
            if ($pw !== '') {
                $cur = $_POST['current_password'] ?? '';
                if (strlen($pw) < 6) {
                    flash('profile_error', 'New password must be at least 6 characters.');
                } elseif (!$u || !password_verify($cur, $u['password'])) {
                    flash('profile_error', 'Current password is incorrect.');
                } else {
                    $st = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($st) {
                        $hash = password_hash($pw, PASSWORD_DEFAULT);
                        $st->bind_param("si", $hash, $uid);
                        $st->execute();
                    }
                    flash('profile_success', 'Profile updated successfully.');
                }
            } else {
                flash('profile_success', 'Profile updated successfully.');
            }
        }
        header('Location: dashboard.php#profile');
        exit;
    }
}

/* ---------- Notifications list ---------- */
$notifs = [];
$result = $conn->query(
    "SELECT * FROM user_notifications WHERE user_id = $uid
     ORDER BY created_at DESC LIMIT 50"
);
if ($result) $notifs = $result->fetch_all(MYSQLI_ASSOC);

/* ---------- Profile prefill ---------- */
$user = current_user($pdo);
$flashNotif = flash('success');
$flashPErr  = flash('profile_error');
$flashPOk   = flash('profile_success');

$pageTitle = 'Dashboard';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>Welcome, <?= e($_SESSION['user_name']) ?> 👋</h1>
    <p class="subtitle">Here's what's happening with your health.</p>
  </div>
  <div class="flex" style="gap:10px;">
    <button class="btn" type="button" data-open-drawer="profileDrawer">👤 My Profile</button>
    <button class="btn" type="button" data-open-drawer="notifDrawer">🔔 Notifications
      <?php if ($unread > 0): ?><span class="badge danger" style="margin-left:6px;"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
    </button>
  </div>
</div>

<!-- Stats -->
<div class="stats">
  <div class="stat"><p>Appointments</p><h2><?= $appts ?></h2></div>
  <div class="stat"><p>Medicine Requests</p><h2><?= $reqs ?></h2></div>
  <div class="stat<?= $unread > 0 ? ' warn' : '' ?>"><p>Unread Notifications</p><h2><?= $unread ?></h2></div>
</div>

<div class="grid grid-2" style="align-items:start;">

  <!-- Recent appointments -->
  <div class="card">
    <div class="flex between mb">
      <h5 style="font-size:15px;">Recent Appointments</h5>
      <a class="btn" href="appointments.php">View all</a>
    </div>
    <?php if (!$recentAppts): ?>
      <p class="muted" style="font-size:13px;">No appointments yet. <a href="book_appointment.php">Book one now →</a></p>
    <?php else: ?>
      <?php foreach ($recentAppts as $a):
          $c = 'secondary';
          switch (strtolower($a['status'])) {
              case 'pending':   $c = 'warning'; break;
              case 'approved':  $c = 'primary'; break;
              case 'completed': $c = 'success'; break;
              case 'cancelled':
              case 'rejected':  $c = 'danger';  break;
          }
      ?>
      <div class="row-item">
        <div>
          <div><strong><?= e($a['doctor_name']) ?></strong></div>
          <div class="small muted">🏥 <?= e($a['hospital_name'] ?? '—') ?></div>
          <div class="small muted">📅 <?= e(date('d M Y', strtotime($a['appointment_date']))) ?> · <?= e(date('h:i A', strtotime($a['appointment_time']))) ?></div>
        </div>
        <span class="badge <?= $c ?>"><?= e(ucfirst($a['status'])) ?></span>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Medicine requests -->
  <div class="card">
    <div class="flex between mb">
      <h5 style="font-size:15px;">Recent Medicine Requests</h5>
      <a class="btn" href="my_requests.php">View all</a>
    </div>
    <?php if (!$recentReqs): ?>
      <p class="muted" style="font-size:13px;">No medicine requests yet. <a href="catalogue.php">Browse medicines →</a></p>
    <?php else: ?>
      <?php foreach ($recentReqs as $r):
          $c = 'secondary';
          switch (strtolower($r['status'])) {
              case 'pending':   $c = 'warning'; break;
              case 'accepted':  $c = 'primary'; break;
              case 'ready':     $c = 'success'; break;
              case 'completed': $c = 'success'; break;
              case 'rejected':
              case 'cancelled': $c = 'danger';  break;
          }
      ?>
      <div class="row-item">
        <div>
          <div><strong><?= e($r['medicine_name']) ?></strong></div>
          <div class="small muted">🏪 <?= e($r['pharmacy_name']) ?></div>
          <div class="small muted">Qty: <?= (int)$r['quantity'] ?></div>
        </div>
        <span class="badge <?= $c ?>"><?= e(ucfirst($r['status'])) ?></span>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<!-- Drawer backdrop -->
<div class="drawer-backdrop" id="drawerBackdrop"></div>

<!-- ============ NOTIFICATIONS DRAWER ============ -->
<div class="drawer" id="notifDrawer" aria-hidden="true">
  <div class="drawer-head">
    <h3>🔔 Notifications <?php if ($unread > 0): ?><span class="badge danger" style="margin-left:6px;"><?= $unread ?> new</span><?php endif; ?></h3>
    <button type="button" class="drawer-close" aria-label="Close">✕</button>
  </div>
  <div class="drawer-body">
    <?php if ($flashNotif): ?>
      <div class="alert success"><?= e($flashNotif) ?></div>
    <?php endif; ?>

    <div class="flex between mb">
      <span class="small muted">Your latest activity and updates.</span>
      <?php if ($unread > 0): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="notif_read_all">
          <button class="btn">✓ Mark all read</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$notifs): ?>
      <p class="muted" style="font-size:13px;">You're all caught up.</p>
    <?php else: ?>
      <?php foreach ($notifs as $n): ?>
        <div class="row-item" style="<?= empty($n['is_read']) ? 'border-left:3px solid var(--accent);padding-left:12px;' : '' ?>">
          <div style="flex:1;min-width:0;">
            <div class="flex" style="gap:8px;flex-wrap:wrap;">
              <strong><?= e($n['title']) ?></strong>
              <?php if (empty($n['is_read'])): ?><span class="badge primary">New</span><?php endif; ?>
            </div>
            <div class="small muted mt"><?= nl2br(e($n['message'])) ?></div>
            <div class="small muted mt"><?= e(date('d M Y, h:i A', strtotime($n['created_at']))) ?></div>
          </div>
          <div class="flex" style="gap:6px;flex-wrap:nowrap;">
            <?php if (empty($n['is_read'])): ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="notif_read">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button class="btn" title="Mark read">✓</button>
              </form>
            <?php endif; ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this notification?');">
              <input type="hidden" name="action" value="notif_delete">
              <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
              <button class="btn danger" title="Delete">✕</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ============ PROFILE DRAWER ============ -->
<div class="drawer" id="profileDrawer" aria-hidden="true">
  <div class="drawer-head">
    <h3>👤 My Profile</h3>
    <button type="button" class="drawer-close" aria-label="Close">✕</button>
  </div>
  <div class="drawer-body">
    <?php if ($flashPOk): ?>
      <div class="alert success"><?= e($flashPOk) ?></div>
    <?php endif; ?>
    <?php if ($flashPErr): ?>
      <div class="alert danger"><?= e($flashPErr) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" id="profileForm">
      <input type="hidden" name="action" value="profile_update">

      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input name="name" class="form-control" value="<?= e($user['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email (read-only)</label>
        <input class="form-control" value="<?= e($user['email'] ?? '') ?>" disabled>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?= e($user['address'] ?? '') ?></textarea>
        </div>
      </div>

      <hr>
      <h6 class="mb" style="font-size:14px;">Change Password <span class="muted">(leave blank to keep current)</span></h6>

      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input type="password" name="current_password" class="form-control" autocomplete="current-password">
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" autocomplete="new-password">
      </div>

      <div class="flex">
        <button class="btn primary">Save Changes</button>
        <button type="button" class="btn drawer-close">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var backdrop = document.getElementById('drawerBackdrop');
  var openBtns = [].slice.call(document.querySelectorAll('[data-open-drawer]'));

  function closeAll() {
    [].slice.call(document.querySelectorAll('.drawer')).forEach(function (d) { d.classList.remove('open'); d.setAttribute('aria-hidden', 'true'); });
    if (backdrop) backdrop.classList.remove('show');
  }
  function open(id) {
    closeAll();
    var d = document.getElementById(id);
    if (!d) return;
    d.classList.add('open');
    d.setAttribute('aria-hidden', 'false');
    if (backdrop) backdrop.classList.add('show');
  }

  openBtns.forEach(function (b) {
    b.addEventListener('click', function () { open(b.getAttribute('data-open-drawer')); });
  });
  [].slice.call(document.querySelectorAll('.drawer .drawer-close')).forEach(function (x) {
    x.addEventListener('click', closeAll);
  });
  if (backdrop) backdrop.addEventListener('click', closeAll);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });

  var hash = window.location.hash;
  if (hash === '#notifications') open('notifDrawer');
  else if (hash === '#profile')  open('profileDrawer');
})();
</script>

<?php include '_footer.php'; ?>