<?php
require '_init.php';
require_login();

$uid  = (int)$_SESSION['user_id'];
$user = current_user($pdo);
if (!$user) { header('Location: logout.php'); exit; }

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') {
        $err = 'Name is required.';
    } else {
        $st = $conn->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
        if ($st) {
            $st->bind_param("sssi", $name, $phone, $address, $uid);
            $st->execute();
            $_SESSION['user_name'] = $name;
        }

        $newPass = $_POST['new_password'] ?? '';
        if ($newPass !== '') {
            $curPass = $_POST['current_password'] ?? '';
            if (strlen($newPass) < 6)                              $err = 'New password must be at least 6 characters.';
            elseif (!password_verify($curPass, $user['password'])) $err = 'Current password is incorrect.';
            else {
                $st = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($st) {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $st->bind_param("si", $hash, $uid);
                    $st->execute();
                }
            }
        }

        if (!$err) {
            $msg  = 'Profile updated successfully.';
            $user = current_user($pdo);
        }
    }
}

$pageTitle = 'My Profile';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>My Profile</h1>
    <p class="subtitle">Manage your account details and password.</p>
  </div>
</div>

<div style="max-width:720px;">

  <?php if ($msg): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert danger"><?= e($err) ?></div><?php endif; ?>

  <div class="card">
    <div class="flex mb" style="gap:14px;">
      <div class="c-icon" style="width:52px;height:52px;">👤</div>
      <div>
        <h3 style="font-size:19px;"><?= e($user['name']) ?></h3>
        <small class="muted"><?= e($user['email']) ?></small>
      </div>
    </div>

    <form method="post" autocomplete="off">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input name="name" class="form-control" value="<?= e($user['name']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email (read-only)</label>
        <input class="form-control" value="<?= e($user['email']) ?>" disabled>
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
        <input type="password" name="current_password" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control">
      </div>

      <div class="flex">
        <button class="btn primary">Save Changes</button>
        <a class="btn" href="dashboard.php">Cancel</a>
      </div>
    </form>
  </div>

  <div class="card">
    <h6 class="mb" style="font-size:14px;">Account Information</h6>
    <div class="meta-grid">
      <div>
        <div class="label">Member Since</div>
        <div><?= e($user['created_at'] ?? '—') ?></div>
      </div>
      <div>
        <div class="label">Status</div>
        <div>
          <?php $st = strtolower($user['status'] ?? 'active'); ?>
          <span class="badge <?= $st === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst($st)) ?></span>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include '_footer.php'; ?>