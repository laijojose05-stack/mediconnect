<?php
require '_init.php';
require_login();

$q    = trim($_GET['q']              ?? '');
$spec = trim($_GET['specialization'] ?? '');
$hosp = (int)($_GET['hospital_id']   ?? 0);
$open = (int)($_GET['open']          ?? 0);

$sql    = "SELECT d.*, h.hospital_name
           FROM doctors d
           LEFT JOIN hospitals h ON h.id = d.hospital_id
           WHERE 1=1";
$params = [];
if ($q !== '')    { $sql .= " AND d.doctor_name LIKE ?"; $params[] = "%$q%"; }
if ($spec !== '') { $sql .= " AND d.specialization = ?"; $params[] = $spec; }
if ($hosp > 0)    { $sql .= " AND d.hospital_id = ?";     $params[] = $hosp; }
$sql .= " ORDER BY d.doctor_name LIMIT 60";

$docs = [];
$st = $conn->prepare($sql);
if ($st) {
    if ($params) {
        $st->bind_param(str_repeat("s", count($params)), ...$params);
    }
    $st->execute();
    $res = $st->get_result();
    if ($res) $docs = $res->fetch_all(MYSQLI_ASSOC);
}

$specs = [];
$res = $conn->query("SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL AND specialization <> '' ORDER BY specialization");
if ($res) $specs = array_column($res->fetch_all(MYSQLI_ASSOC), 'specialization');

$hospitals = [];
$res = $conn->query("SELECT id, hospital_name FROM hospitals WHERE status='approved' ORDER BY hospital_name");
if ($res) $hospitals = $res->fetch_all(MYSQLI_ASSOC);

/* Preload schedule for auto-open */
$openSched = [];
if ($open) {
    $st = $conn->prepare(
        "SELECT * FROM doctor_schedules WHERE doctor_id = ?
         ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')"
    );
    if ($st) {
        $st->bind_param("i", $open);
        $st->execute();
        $res = $st->get_result();
        if ($res) $openSched = $res->fetch_all(MYSQLI_ASSOC);
    }
}

$pageTitle = 'Doctors';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>Doctors</h1>
    <p class="subtitle"><?= count($docs) ?> result<?= count($docs) === 1 ? '' : 's' ?></p>
  </div>
  <a class="btn" href="dashboard.php">← Dashboard</a>
</div>

<form class="search-bar" method="get" style="grid-template-columns:1fr 1fr 1fr auto;">
  <div>
    <label class="form-label">Search</label>
    <input class="form-control" name="q" placeholder="Search doctor name…" value="<?= e($q) ?>">
  </div>
  <div>
    <label class="form-label">Specialization</label>
    <select class="form-select" name="specialization">
      <option value="">All specialties</option>
      <?php foreach ($specs as $s): ?>
        <option value="<?= e($s) ?>" <?= $s === $spec ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label">Hospital</label>
    <select class="form-select" name="hospital_id">
      <option value="">All hospitals</option>
      <?php foreach ($hospitals as $h): ?>
        <option value="<?= (int)$h['id'] ?>" <?= $h['id'] == $hosp ? 'selected' : '' ?>><?= e($h['hospital_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn primary" style="height:40px;">Search</button>
</form>

<?php if (!$docs): ?>

  <div class="card empty">
    <div class="big">👨‍⚕️</div>
    <h3>No doctors found</h3>
    <p>Try different filters.</p>
  </div>

<?php else: ?>

  <?php foreach ($docs as $d): ?>
    <?php $isOpen = $d['id'] == $open; ?>
    <details class="acc-item" <?= $isOpen ? 'open' : '' ?>>
      <summary class="acc-btn">
        <div class="acc-icon">👨‍⚕️</div>
        <div class="acc-title">
          <strong><?= e($d['doctor_name']) ?></strong>
          <span><?= e($d['specialization'] ?? '') ?><?php if (!empty($d['hospital_name'])) echo ' · 🏥 ' . e($d['hospital_name']); ?></span>
        </div>
      </summary>
      <div class="acc-body">

        <div class="meta-grid">
          <div>
            <div class="label">Hospital</div>
            <div><?php if (!empty($d['hospital_name'])): ?><a href="hospitals.php?open=<?= (int)$d['hospital_id'] ?>"><?= e($d['hospital_name']) ?></a><?php else: ?>—<?php endif; ?></div>
          </div>
          <div>
            <div class="label">Phone</div>
            <div><?php if (!empty($d['phone'])): ?><a href="tel:<?= e($d['phone']) ?>"><?= e($d['phone']) ?></a><?php else: ?>—<?php endif; ?></div>
          </div>
          <div>
            <div class="label">Email</div>
            <div><?php if (!empty($d['email'])): ?><a href="mailto:<?= e($d['email']) ?>"><?= e($d['email']) ?></a><?php else: ?>—<?php endif; ?></div>
          </div>
        </div>

        <a class="btn primary mb" href="book_appointment.php?doctor_id=<?= (int)$d['id'] ?>">📅 Book Appointment</a>

        <?php if ($isOpen): ?>
          <hr>
          <h6 class="mb" style="font-size:14px;">Weekly Schedule</h6>
          <?php if (!$openSched): ?>
            <p class="muted small mb">Schedule not published yet.</p>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Day</th><th>From</th><th>To</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($openSched as $s): ?>
                    <tr>
                      <td><strong><?= e($s['day_of_week']) ?></strong></td>
                      <td><?= e(substr($s['start_time'], 0, 5)) ?></td>
                      <td><?= e(substr($s['end_time'],   0, 5)) ?></td>
                      <td>
                        <?php if (!empty($s['is_available'])): ?>
                          <span class="badge success">Available</span>
                        <?php else: ?>
                          <span class="badge secondary">Closed</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    </details>
  <?php endforeach; ?>

<?php endif; ?>

<?php include '_footer.php'; ?>