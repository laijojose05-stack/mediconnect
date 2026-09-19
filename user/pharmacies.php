<?php
require '_init.php';
require_login();

$q    = trim($_GET['q']    ?? '');
$city = trim($_GET['city'] ?? '');
$open = (int)($_GET['open'] ?? 0);

$sql    = "SELECT * FROM pharmacies WHERE status = 'approved'";
$params = [];
if ($q !== '')    { $sql .= " AND (pharmacy_name LIKE ? OR license_number LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($city !== '') { $sql .= " AND city = ?"; $params[] = $city; }
$sql .= " ORDER BY pharmacy_name LIMIT 60";

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
$res = $conn->query("SELECT DISTINCT city FROM pharmacies WHERE status='approved' AND city IS NOT NULL AND city<>'' ORDER BY city");
if ($res) $cities = array_column($res->fetch_all(MYSQLI_ASSOC), 'city');

$pageTitle = 'Pharmacies';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>Pharmacies</h1>
    <p class="subtitle"><?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?></p>
  </div>
  <a class="btn" href="dashboard.php">← Dashboard</a>
</div>

<form class="search-bar" method="get">
  <div>
    <label class="form-label">Search</label>
    <input class="form-control" name="q" placeholder="Search pharmacy name or license…" value="<?= e($q) ?>">
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
    <div class="big">🏪</div>
    <h3>No pharmacies found</h3>
    <p>Try a different search or city.</p>
  </div>

<?php else: ?>

  <?php foreach ($rows as $p): ?>
    <?php $isOpen = $p['id'] == $open; ?>
    <details class="acc-item" <?= $isOpen ? 'open' : '' ?>>
      <summary class="acc-btn">
        <div class="acc-icon">🏪</div>
        <div class="acc-title">
          <strong><?= e($p['pharmacy_name']) ?></strong>
          <span><?php if (!empty($p['city'])) echo e($p['city']); ?></span>
        </div>
      </summary>
      <div class="acc-body">

        <div class="meta-grid">
          <div>
            <div class="label">Address</div>
            <div><?= e($p['address'] ?? '—') ?><?= $p['city'] ? ', ' . e($p['city']) : '' ?></div>
          </div>
          <div>
            <div class="label">Phone</div>
            <div><?php if (!empty($p['phone'])): ?><a href="tel:<?= e($p['phone']) ?>"><?= e($p['phone']) ?></a><?php else: ?>—<?php endif; ?></div>
          </div>
          <div>
            <div class="label">Email</div>
            <div><?php if (!empty($p['email'])): ?><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a><?php else: ?>—<?php endif; ?></div>
          </div>
          <div>
            <div class="label">License</div>
            <div>✔ <?= e($p['license_number'] ?? '—') ?></div>
          </div>
        </div>

        <a class="btn primary" href="catalogue.php?pharmacy=<?= (int)$p['id'] ?>">💊 Browse Their Medicines</a>

      </div>
    </details>
  <?php endforeach; ?>

<?php endif; ?>

<?php include '_footer.php'; ?>