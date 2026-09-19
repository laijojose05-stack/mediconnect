<?php
require '_init.php';
require_login();

$q    = trim($_GET['q']    ?? '');
$city = trim($_GET['city'] ?? '');
$open = (int)($_GET['open'] ?? 0);   // hospital id to auto-expand

$sql    = "SELECT * FROM hospitals WHERE status = 'approved'";
$params = [];
if ($q !== '')    { $sql .= " AND (hospital_name LIKE ? OR specialty LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($city !== '') { $sql .= " AND city = ?"; $params[] = $city; }
$sql .= " ORDER BY hospital_name LIMIT 60";

$rows = [];
$st = $conn->prepare($sql);
if ($st) {
    if ($params) {
        $st->bind_param(str_repeat("s", count($params)), ...$params);
    }
    $st->execute();
    $res = $st->get_result();
    if ($res) $rows = $res->fetch_all(MYSQLI_ASSOC);
}

$cities = [];
$res = $conn->query("SELECT DISTINCT city FROM hospitals WHERE status='approved' AND city IS NOT NULL AND city<>'' ORDER BY city");
if ($res) $cities = array_column($res->fetch_all(MYSQLI_ASSOC), 'city');

/* Preload doctors for auto-open */
$openDoctors = [];
if ($open) {
    $st = $conn->prepare("SELECT id, doctor_name, specialization, phone FROM doctors WHERE hospital_id = ? ORDER BY doctor_name");
    if ($st) {
        $st->bind_param("i", $open);
        $st->execute();
        $res = $st->get_result();
        if ($res) $openDoctors = $res->fetch_all(MYSQLI_ASSOC);
    }
}

$pageTitle = 'Hospitals';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>Hospitals</h1>
    <p class="subtitle"><?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?></p>
  </div>
  <a class="btn" href="dashboard.php">← Dashboard</a>
</div>

<form class="search-bar" method="get">
  <div>
    <label class="form-label">Search</label>
    <input class="form-control" name="q" placeholder="Search hospital or specialty…" value="<?= e($q) ?>">
  </div>
  <div>
    <label class="form-label">City</label>
    <select class="form-select" name="city">
      <option value="">All cities</option>
      <?php foreach ($cities as $c): ?>
        <option value="<?= e($c) ?>" <?= $c === $city ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn primary" style="height:40px;">Search</button>
</form>

<?php if (!$rows): ?>

  <div class="card empty">
    <div class="big">🏥</div>
    <h3>No hospitals found</h3>
    <p>Try a different search or city.</p>
  </div>

<?php else: ?>

  <?php foreach ($rows as $h): ?>
    <?php $isOpen = $h['id'] == $open; ?>
    <details class="acc-item" <?= $isOpen ? 'open' : '' ?>>
      <summary class="acc-btn">
        <div class="acc-icon">🏥</div>
        <div class="acc-title">
          <strong><?= e($h['hospital_name']) ?></strong>
          <span><?php if (!empty($h['city'])) echo e($h['city']); ?><?php if (!empty($h['specialty'])) echo ' · ' . e($h['specialty']); ?></span>
        </div>
      </summary>
      <div class="acc-body">

        <div class="meta-grid">
          <div>
            <div class="label">Address</div>
            <div><?= e($h['address'] ?? '—') ?><?= $h['city'] ? ', ' . e($h['city']) : '' ?></div>
          </div>
          <div>
            <div class="label">Phone</div>
            <div><?php if (!empty($h['phone'])): ?><a href="tel:<?= e($h['phone']) ?>"><?= e($h['phone']) ?></a><?php else: ?>—<?php endif; ?></div>
          </div>
          <div>
            <div class="label">Email</div>
            <div><?php if (!empty($h['email'])): ?><a href="mailto:<?= e($h['email']) ?>"><?= e($h['email']) ?></a><?php else: ?>—<?php endif; ?></div>
          </div>
        </div>

        <div class="flex mb">
          <a class="btn primary" href="book_appointment.php?hospital_id=<?= (int)$h['id'] ?>">📅 Book Appointment</a>
          <a class="btn" href="doctors.php?hospital_id=<?= (int)$h['id'] ?>">👨‍⚕️ View Doctors</a>
        </div>

        <?php if ($isOpen): ?>
          <hr>
          <h6 class="mb" style="font-size:14px;">Doctors at this hospital</h6>
          <?php if (!$openDoctors): ?>
            <p class="muted small mb">No doctors listed yet.</p>
          <?php else: ?>
            <div class="grid grid-2">
              <?php foreach ($openDoctors as $d): ?>
                <div class="card-item">
                  <div class="c-head">
                    <div class="c-icon">👨‍⚕️</div>
                    <div>
                      <div class="c-title"><?= e($d['doctor_name']) ?></div>
                      <div class="c-sub"><?= e($d['specialization'] ?? '') ?></div>
                    </div>
                  </div>
                  <div class="c-foot">
                    <a class="btn primary" href="book_appointment.php?doctor_id=<?= (int)$d['id'] ?>">Book</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    </details>
  <?php endforeach; ?>

<?php endif; ?>

<?php include '_footer.php'; ?>