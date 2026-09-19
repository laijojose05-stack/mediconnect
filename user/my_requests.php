<?php
require '_init.php';
require_login();

$uid = (int)$_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'all';

/* ============================================================
   Handle POST actions (cancel request)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'cancel_request') {
        $st = $conn->prepare("SELECT m.medicine_name FROM medicine_requests mr JOIN medicines m ON m.id = mr.medicine_id
                              WHERE mr.id = ? AND mr.user_id = ? AND mr.status = 'pending'");
        if ($st) {
            $st->bind_param("ii", $id, $uid);
            $st->execute();
            $res = $st->get_result();
            if ($res && $res->num_rows) {
                $r = $res->fetch_assoc();
                $st2 = $conn->prepare("UPDATE medicine_requests SET status = 'cancelled' WHERE id = ?");
                $st2->bind_param("i", $id);
                $st2->execute();
                $msg = "Your request for {$r['medicine_name']} has been cancelled.";
                $st3 = $conn->prepare(
                    "INSERT INTO user_notifications (user_id, title, message, type, related_id)
                     VALUES (?, ?, ?, 'medicine_request', ?)"
                );
                $nt2 = 'Medicine request cancelled';
                $st3->bind_param("issi", $uid, $nt2, $msg, $id);
                $st3->execute();
                flash('success', 'Medicine request cancelled.');
            }
        }
    }
    header('Location: my_requests.php?tab=' . urlencode($tab));
    exit;
}

/* ============================================================
   Load data depending on tab
============================================================ */
$reqWhere = "WHERE mr.user_id = ?";

switch ($tab) {
    case 'pending':
        $reqWhere .= " AND mr.status = 'pending'";
        break;
    case 'active':
        $reqWhere .= " AND mr.status IN ('accepted','ready')";
        break;
    case 'completed':
        $reqWhere .= " AND mr.status = 'completed'";
        break;
    case 'cancelled':
        $reqWhere .= " AND mr.status = 'cancelled'";
        break;
    case 'rejected':
        $reqWhere .= " AND mr.status = 'rejected'";
        break;
    default:
        $tab = 'all';
}
$reqWhere .= " ORDER BY mr.created_at DESC";

$reqs = [];
$stmt = $conn->prepare(
    "SELECT mr.*, m.medicine_name, p.pharmacy_name
     FROM medicine_requests mr
     JOIN medicines m  ON m.id = mr.medicine_id
     JOIN pharmacies p ON p.id = mr.pharmacy_id
     $reqWhere"
);
if ($stmt) {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) $reqs = $res->fetch_all(MYSQLI_ASSOC);
}

/* Stats */
$statAll       = 0; $statPending = 0; $statActive = 0;
$statCompleted = 0; $statCancelled = 0; $statRejected = 0;
$res = $conn->query("SELECT status, COUNT(*) AS c FROM medicine_requests WHERE user_id = $uid GROUP BY status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        switch ($row['status']) {
            case 'pending':   $statPending   = (int)$row['c']; break;
            case 'accepted':
            case 'ready':     $statActive    += (int)$row['c']; break;
            case 'completed': $statCompleted = (int)$row['c']; break;
            case 'cancelled': $statCancelled = (int)$row['c']; break;
            case 'rejected':  $statRejected  = (int)$row['c']; break;
        }
        $statAll += (int)$row['c'];
    }
}

$pageTitle = 'Medicine Requests';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>Medicine Requests</h1>
    <p class="subtitle">Track your requests to pharmacies here.</p>
  </div>
  <a class="btn primary" href="catalogue.php">💊 Browse Medicines</a>
</div>

<?php if ($s = flash('success')): ?>
  <div class="alert success"><?= e($s) ?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs">
  <?php
  $tabs = [
      'all'       => ['All',         $statAll],
      'pending'   => ['Pending',     $statPending],
      'active'    => ['Active',      $statActive],
      'completed' => ['Completed',   $statCompleted],
      'cancelled' => ['Cancelled',   $statCancelled],
      'rejected'  => ['Rejected',    $statRejected],
  ];
  foreach ($tabs as $key => [$label, $count]): ?>
    <a class="tab<?= $tab === $key ? ' active' : '' ?>" href="?tab=<?= $key ?>">
      <?= e($label) ?>
      <?php if ($count > 0): ?><span class="badge secondary"><?= $count ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$reqs): ?>
  <div class="card empty">
    <div class="big">💊</div>
    <h3>No medicine requests <?= $tab !== 'all' ? 'in this list' : '' ?></h3>
    <p>Request medicines from our catalogue.</p>
    <a class="btn primary" href="catalogue.php">Browse Medicines</a>
  </div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($reqs as $r):
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
      <div class="card-item">
        <div class="flex between" style="align-items:flex-start;">
          <div>
            <div class="c-title"><?= e($r['medicine_name']) ?></div>
            <div class="c-sub">🏪 <?= e($r['pharmacy_name']) ?></div>
          </div>
          <span class="badge <?= $c ?>"><?= e(ucfirst($r['status'])) ?></span>
        </div>
        <div class="small muted">Quantity: <?= (int)$r['quantity'] ?></div>
        <?php if (!empty($r['prescription_image'])): ?>
          <div class="small muted"><a href="<?= e($r['prescription_image']) ?>" target="_blank">📎 View prescription</a></div>
        <?php endif; ?>
        <div class="c-foot">
          <a class="btn" href="catalogue.php?id=<?= (int)$r['medicine_id'] ?>" style="flex:1;text-align:center;">Reorder</a>
          <?php if ($r['status'] === 'pending'): ?>
            <form method="post" style="flex:1;" onsubmit="return confirm('Cancel this request?');">
              <input type="hidden" name="action" value="cancel_request">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn danger block">Cancel</button>
            </form>
          <?php endif; ?>
        </div>
        <div class="small muted">Requested <?= e(date('d M Y, h:i A', strtotime($r['created_at']))) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include '_footer.php'; ?>