<?php
require '_init.php';
require_login();

$uid     = $_SESSION['user_id'];
$q       = trim($_GET['q']        ?? '');
$cat     = trim($_GET['category'] ?? '');
$viewId  = (int)($_GET['id']      ?? 0);
$pharmId = (int)($_GET['pharmacy'] ?? 0);

/* ---------- Pharmacy-scoped browsing (from Pharmacies module) ---------- */
$pharmacyContext = null;
if ($pharmId) {
    ensure_stock_table($pdo);
    $st = $conn->prepare("SELECT id, pharmacy_name, city FROM pharmacies WHERE id = ? AND status = 'approved'");
    if ($st) {
        $st->bind_param("i", $pharmId);
        $st->execute();
        $res = $st->get_result();
        $pharmacyContext = ($res && $res->num_rows) ? $res->fetch_assoc() : null;
    }
    if (!$pharmacyContext) $pharmId = 0;
}

/* ---------- List medicines (paginated) ---------- */
$perPage = 24;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$select = $pharmId
        ? "m.*, pm.price AS ph_price, pm.stock AS ph_stock"
        : "m.*";
$from   = $pharmId
        ? "FROM medicines m
           INNER JOIN pharmacy_medicines pm ON pm.medicine_id = m.id AND pm.pharmacy_id = ?"
        : "FROM medicines m";

$where  = "WHERE 1=1";
$params = [];
$types  = "";
if ($pharmId) { $types .= "i"; $params[] = $pharmId; }
if ($q !== '') {
    $where .= " AND (m.medicine_name LIKE ? OR m.generic_name LIKE ? OR m.category LIKE ? OR m.description LIKE ?)";
    $like = "%$q%";
    $types .= "ssss";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($cat !== '') {
    $where .= " AND m.category = ?";
    $types .= "s";
    $params[] = $cat;
}

/* Total results (for pagination) */
$total = 0;
$st = $conn->prepare("SELECT COUNT(*) AS total $from $where");
if ($st) {
    if ($params) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    if ($res) $total = (int)$res->fetch_row()[0];
}
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$meds = [];
$sql  = "SELECT $select $from $where ORDER BY m.medicine_name ASC LIMIT $perPage OFFSET $offset";
$st = $conn->prepare($sql);
if ($st) {
    if ($params) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    if ($res) $meds = $res->fetch_all(MYSQLI_ASSOC);
}

$cats = [];
$res = $conn->query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL AND category <> '' ORDER BY category");
if ($res) $cats = array_column($res->fetch_all(MYSQLI_ASSOC), 'category');

/* ---------- Load single medicine for details ---------- */
$viewMed = null;
if ($viewId) {
    $st = $conn->prepare("SELECT * FROM medicines WHERE id = ?");
    if ($st) {
        $st->bind_param("i", $viewId);
        $st->execute();
        $res = $st->get_result();
        if ($res && $res->num_rows) $viewMed = $res->fetch_assoc();
    }
}

/* ---------- Pharmacies for request form ---------- */
$pharmacies = [];
$res = $conn->query("SELECT id, pharmacy_name, city FROM pharmacies WHERE status = 'approved' ORDER BY pharmacy_name");
if ($res) $pharmacies = $res->fetch_all(MYSQLI_ASSOC);

$pageTitle = $viewMed ? $viewMed['medicine_name'] : 'Medicines';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>Medicines</h1>
    <p class="subtitle"><?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?></p>
  </div>
  <a class="btn" href="dashboard.php">← Dashboard</a>
</div>

<?php if ($s = flash('success')): ?>
  <div class="alert success"><?= e($s) ?></div>
<?php endif; ?>
<?php if ($f = flash('error')): ?>
  <div class="alert danger"><?= e($f) ?></div>
<?php endif; ?>

<!-- Search -->
<form class="search-bar" method="get">
  <?php if ($pharmId): ?>
    <input type="hidden" name="pharmacy" value="<?= $pharmId ?>">
  <?php endif; ?>
  <div>
    <label class="form-label">Search</label>
    <input class="form-control" name="q" placeholder="Search by name, generic, category, or description…" value="<?= e($q) ?>">
  </div>
  <div>
    <label class="form-label">Category</label>
    <select class="form-select" name="category">
      <option value="">All categories</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= e($c) ?>" <?= $c === $cat ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn primary" style="height:40px;">Search</button>
</form>

<?php if ($pharmacyContext): ?>
  <div class="alert info">
    <div class="flex">
      <span style="flex:1;">🏪 <strong><?= e($pharmacyContext['pharmacy_name']) ?></strong>
      <?= $pharmacyContext['city'] ? ' — ' . e($pharmacyContext['city']) : '' ?> · showing medicines stocked by this pharmacy</span>
      <a class="btn" href="catalogue.php">View all medicines</a>
    </div>
  </div>
<?php endif; ?>

<!-- Details panel (if viewing) -->
<?php if ($viewMed): ?>
  <div class="card" style="border-left:4px solid var(--accent);">
    <div class="flex between mb">
      <div class="flex" style="gap:14px;">
        <div class="c-icon" style="width:52px;height:52px;">💊</div>
        <div>
          <h3 style="font-size:20px;"><?= e($viewMed['medicine_name']) ?></h3>
          <small class="muted"><?= e($viewMed['generic_name'] ?? '—') ?></small>
        </div>
      </div>
      <a class="btn" href="catalogue.php<?= $pharmId ? '?pharmacy=' . $pharmId : '' ?>">✕ Close</a>
    </div>

    <div class="flex mb">
      <?php if (!empty($viewMed['category'])): ?>
        <span class="badge primary"><?= e($viewMed['category']) ?></span>
      <?php endif; ?>
      <span class="small muted">Medicine ID: #<?= (int)$viewMed['id'] ?></span>
    </div>

    <?php if (!empty($viewMed['description'])): ?>
      <p class="mb"><?= nl2br(e($viewMed['description'])) ?></p>
    <?php endif; ?>

    <hr>
    <h5 style="font-size:15px;" class="mb">Request this medicine</h5>

    <?php if (!$pharmacies): ?>
      <div class="alert warning">No approved pharmacies available right now.</div>
    <?php else: ?>
      <form method="post" action="request_medicine.php" enctype="multipart/form-data">
        <input type="hidden" name="medicine_id" value="<?= (int)$viewMed['id'] ?>">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Pharmacy</label>
            <select name="pharmacy_id" class="form-select" required>
              <option value="">Select pharmacy…</option>
              <?php foreach ($pharmacies as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $pharmId === (int)$p['id'] ? 'selected' : '' ?>>
                  <?= e($p['pharmacy_name']) ?><?= $p['city'] ? ' — ' . e($p['city']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" min="1" max="99" value="1" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Prescription <span class="muted">(optional)</span></label>
            <input type="file" name="prescription" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Notes <span class="muted">(optional)</span></label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Any additional info for the pharmacy…"></textarea>
        </div>

        <button class="btn primary">Send Request</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- List -->
<?php if (!$meds): ?>

  <div class="card empty">
    <div class="big">💊</div>
    <h3><?= $pharmacyContext ? 'No medicines stocked yet' : 'No medicines found' ?></h3>
    <p><?= $pharmacyContext ? "This pharmacy hasn't added its stock yet. Try viewing all medicines or another pharmacy." : 'Try a different search or category.' ?></p>
  </div>

<?php else: ?>

  <div class="grid grid-3">
    <?php foreach ($meds as $m): ?>
      <?php $isActive = $viewMed && $viewMed['id'] == $m['id']; ?>
      <div class="card-item<?= $isActive ? ' active' : '' ?>">
        <div class="c-head">
          <div class="c-icon">💊</div>
          <div>
            <div class="c-title"><?= e($m['medicine_name']) ?></div>
            <div class="c-sub"><?= e($m['generic_name'] ?? '—') ?></div>
          </div>
        </div>

        <div class="flex" style="gap:8px;">
          <?php if (!empty($m['category'])): ?>
            <span class="badge primary"><?= e($m['category']) ?></span>
          <?php endif; ?>
          <?php if ($pharmId && isset($m['ph_stock'])): ?>
            <span class="badge <?= (int)$m['ph_stock'] > 0 ? 'success' : 'secondary' ?>">
              <?= (int)$m['ph_stock'] > 0 ? (int)$m['ph_stock'] . ' in stock' : 'Out of stock' ?>
            </span>
          <?php endif; ?>
        </div>

        <?php if (!empty($m['description'])): ?>
          <p class="c-desc"><?= e(mb_strimwidth($m['description'], 0, 110, '…')) ?></p>
        <?php endif; ?>

        <div class="c-foot">
          <a class="btn primary" href="catalogue.php?id=<?= (int)$m['id'] ?><?= $q ? '&q=' . urlencode($q) : '' ?><?= $cat ? '&category=' . urlencode($cat) : '' ?><?= $pharmId ? '&pharmacy=' . $pharmId : '' ?>">
            <?= $isActive ? 'Viewing' : 'View Details' ?> →
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): $pagerBase = 'catalogue.php?' . ($pharmId ? 'pharmacy=' . (int)$pharmId . '&' : '') . ($q !== '' ? 'q=' . urlencode($q) . '&' : '') . ($cat !== '' ? 'category=' . urlencode($cat) . '&' : ''); ?>
    <div class="flex between" style="margin-top:28px;">
      <span class="small muted">Page <?= $page ?> of <?= $totalPages ?></span>
      <div class="flex" style="gap:6px;">
        <?php if ($page > 1): ?>
          <a class="btn" href="<?= e($pagerBase) ?>page=<?= $page - 1 ?>">← Prev</a>
        <?php endif; ?>
        <span class="small" style="color:var(--accent);font-weight:600;"><?= $page ?></span>
        <?php if ($page < $totalPages): ?>
          <a class="btn" href="<?= e($pagerBase) ?>page=<?= $page + 1 ?>">Next →</a>
        <?php endif; ?>
      </div>
      <form class="flex" style="gap:6px;margin:0;" method="get">
        <?php if ($pharmId): ?><input type="hidden" name="pharmacy" value="<?= (int)$pharmId ?>"><?php endif; ?>
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <?php if ($cat !== ''): ?><input type="hidden" name="category" value="<?= e($cat) ?>"><?php endif; ?>
        <span class="small muted">Go to page</span>
        <input type="number" name="page" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" style="width:70px;" class="form-control">
        <button class="btn" type="submit">Go</button>
      </form>
    </div>
  <?php endif; ?>

<?php endif; ?>}

<?php include '_footer.php'; ?>