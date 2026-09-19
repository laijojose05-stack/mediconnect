<?php
/**
 * Shared right-side "Notifications + My Profile" UI for non-user modules.
 *
 * Usage (after $conn is ready and the module auth guard passed):
 *
 *   $cfg = [
 *     'ntable'             => 'hospital_notifications',  // notifications table
 *     'recipient_col'      => 'hospital_id',             // recipient column in that table
 *     'recipient_id'       => (int)$_SESSION['hospital_id'],
 *     'profile_table'      => 'hospitals',
 *     'profile_id_field'   => 'id',
 *     'profile_label_field'=> 'hospital_name',
 *     'profile_email_field'=> 'email',
 *     'session_label_key'  => 'hospital_name',
 *     'profile_title'      => 'Hospital Profile',
 *     'profile_fields'     => [
 *        ['name'=>'hospital_name','label'=>'Hospital Name'],
 *        ['name'=>'phone','label'=>'Phone'],
 *        ['name'=>'address','label'=>'Address','type'=>'textarea'],
 *     ],
 *   ];
 *
 *   right_handle($conn, $cfg);                                   // processes POSTs (top of page)
 *   $rightUnread = right_unread($conn, $cfg);
 *   ... in page header: <?= right_buttons($rightUnread, $cfg['profile_title']) ?>
 *   ... before </body>: <?= right_panels($conn, $cfg, $rightUnread) ?>
 */

if (!function_exists('right_handle')) {

function right_escape(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function right_unread($conn, array $cfg): int {
    $st = $conn->prepare("SELECT COUNT(*) AS c FROM {$cfg['ntable']} WHERE {$cfg['recipient_col']}=? AND is_read=0");
    if (!$st) return 0;
    $st->bind_param("i", $cfg['recipient_id']);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    return (int)($r['c'] ?? 0);
}

function right_handle($conn, array $cfg): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['action'])) return;
    $act  = (string)$_POST['action'];
    $back = basename($_SERVER['PHP_SELF']);
    $id   = (int)$cfg['recipient_id'];

    if ($act === 'notif_read_all') {
        $st = $conn->prepare("UPDATE {$cfg['ntable']} SET is_read=1 WHERE {$cfg['recipient_col']}=?");
        $st->bind_param("i", $id);
        $st->execute();
        header("Location: $back#notifications");
        exit;
    }
    if ($act === 'notif_read' || $act === 'notif_delete') {
        $nid = (int)($_POST['id'] ?? 0);
        if ($act === 'notif_read') {
            $st = $conn->prepare("UPDATE {$cfg['ntable']} SET is_read=1 WHERE id=? AND {$cfg['recipient_col']}=?");
        } else {
            $st = $conn->prepare("DELETE FROM {$cfg['ntable']} WHERE id=? AND {$cfg['recipient_col']}=?");
        }
        $st->bind_param("ii", $nid, $id);
        $st->execute();
        header("Location: $back#notifications");
        exit;
    }
    if ($act === 'profile_update') {
        $label = trim($_POST['profile_label'] ?? '');
        if ($label !== '') {
            $sql = "UPDATE {$cfg['profile_table']} SET {$cfg['profile_label_field']}=?";
            $vals = [$label];
            $types = "s";
            foreach ($cfg['profile_fields'] as $f) {
                if ($f['name'] === $cfg['profile_label_field']) continue;
                $sql .= ", " . $f['name'] . "=?";
                $vals[] = trim($_POST[$f['name']] ?? '');
                $types .= "s";
            }
            $sql .= " WHERE {$cfg['profile_id_field']}=?";
            $vals[] = $id;
            $types .= "i";
            $st = $conn->prepare($sql);
            $st->bind_param($types, ...$vals);
            $st->execute();
            $_SESSION[$cfg['session_label_key']] = $label;
            if (isset($_SESSION["user"]) && $cfg['profile_table'] === 'admins') {
                $_SESSION["user"]["name"] = $label;
            }
        }

        $pw = $_POST['new_password'] ?? '';
        $err = null;
        if ($pw !== '') {
            $cur = $_POST['current_password'] ?? '';
            if (strlen($pw) < 6) {
                $err = "New password must be at least 6 characters.";
            } else {
                $st = $conn->prepare("SELECT * FROM {$cfg['profile_table']} WHERE {$cfg['profile_id_field']}=?");
                $st->bind_param("i", $id);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                if (!$row || !password_verify($cur, $row['password'])) {
                    $err = "Current password is incorrect.";
                } else {
                    $h = password_hash($pw, PASSWORD_DEFAULT);
                    $st = $conn->prepare("UPDATE {$cfg['profile_table']} SET password=? WHERE {$cfg['profile_id_field']}=?");
                    $st->bind_param("si", $h, $id);
                    $st->execute();
                }
            }
        }
        $_SESSION['right_flash'] = $err === null
            ? ['success', 'Profile updated successfully.']
            : ['error', $err];
        header("Location: $back#profile");
        exit;
    }
}

function right_buttons(int $unread, string $profileTitle): string {
    $badge = $unread > 0
        ? ' <span class="right-badge">' . ($unread > 9 ? '9+' : $unread) . '</span>'
        : '';
    return '<div class="right-actions">'
         . '<button class="right-btn" type="button" data-open-drawer="rightNotifDrawer">🔔 Notifications' . $badge . '</button>'
         . '<button class="right-btn" type="button" data-open-drawer="rightProfileDrawer">👤 ' . right_escape($profileTitle) . '</button>'
         . '</div>';
}

function right_panels($conn, array $cfg, int $unread): void {
    $id = (int)$cfg['recipient_id'];
    $profile = null;
    $st = $conn->prepare("SELECT * FROM {$cfg['profile_table']} WHERE {$cfg['profile_id_field']}=?");
    $st->bind_param("i", $id);
    $st->execute();
    if ($st) {
        $res = $st->get_result();
        $profile = $res ? $res->fetch_assoc() : null;
    }
    $label = $profile[$cfg['profile_label_field']] ?? '';
    $email = $cfg['profile_email_field'] && $profile ? ($profile[$cfg['profile_email_field']] ?? '') : '';

    $notifs = [];
    $st = $conn->prepare("SELECT * FROM {$cfg['ntable']} WHERE {$cfg['recipient_col']}=? ORDER BY created_at DESC, id DESC LIMIT 15");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    if ($res) $notifs = $res->fetch_all(MYSQLI_ASSOC);

    $main = $cfg['profile_label_field'];
    $editable = [];
    foreach ($cfg['profile_fields'] as $f) {
        if ($f['name'] === $main) continue;
        $editable[] = $f;
    }

    $flash = $_SESSION['right_flash'] ?? null;
    unset($_SESSION['right_flash']);
    ?>
<style>
.right-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.right-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 16px;border-radius:10px;border:1px solid #262d3d;background:#171c26;color:#e6edf3;font-size:14px;cursor:pointer;transition:.2s;}
.right-btn:hover{background:#212836;border-color:#1d8cf8;}
.right-btn .right-badge{margin-left:4px;background:#ff4d4f;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;min-width:20px;text-align:center;}
.drawer-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2004;opacity:0;visibility:hidden;transition:opacity .25s ease,visibility .25s ease;}
.drawer-backdrop.show{opacity:1;visibility:visible;}
.drawer{position:fixed;top:0;right:0;height:100vh;width:400px;max-width:100vw;background:#14181f;border-left:1px solid #262d3d;z-index:2005;transform:translateX(105%);transition:transform .28s ease;display:flex;flex-direction:column;}
.drawer.open{transform:translateX(0);}
.drawer-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:18px 20px;border-bottom:1px solid #262d3d;flex-shrink:0;}
.drawer-head h3{font-size:16px;}
.drawer-close{background:none;border:none;color:#8b95a5;font-size:20px;cursor:pointer;padding:4px 8px;border-radius:8px;transition:.2s;}
.drawer-close:hover{color:#fff;background:#212836;}
.drawer-body{flex:1;overflow-y:auto;padding:18px 20px;}
.drawer-body::-webkit-scrollbar{width:8px;}
.drawer-body::-webkit-scrollbar-thumb{background:#262d3d;border-radius:6px;}
.notif-item{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #1f2632;padding:14px 0;}
.notif-item:last-child{border-bottom:none;}
.notif-item strong{display:block;font-size:14px;}
.notif-item p{font-size:13px;color:#8b95a5;margin-top:3px;}
.notif-time{font-size:11px;color:#596474;margin-top:5px;}
.notif-actions{display:flex;gap:6px;flex-shrink:0;align-items:center;}
.notif-actions form{display:inline;}
.mini-btn{background:#212836;border:1px solid #2c3444;color:#cdd5e0;border-radius:8px;padding:6px 10px;font-size:12px;cursor:pointer;transition:.2s;}
.mini-btn:hover{border-color:#1d8cf8;color:#fff;}
.mini-btn.danger:hover{border-color:#ff4d4f;}
.rf-label{display:block;font-size:12px;color:#8b95a5;margin:14px 0 6px;}
.rf-input{width:100%;background:#171c26;border:1px solid #2c3444;color:#e6edf3;border-radius:10px;padding:10px 12px;font-size:14px;outline:none;}
.rf-input:focus{border-color:#1d8cf8;}
.rf-input:disabled{background:#14181f;color:#596474;opacity:.8;}
textarea.rf-input{resize:vertical;font-family:inherit;}
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;border:1px solid transparent;}
.alert.success{background:rgba(0,200,120,.12);color:#66e6aa;border-color:rgba(0,200,120,.35);}
.alert.error{background:rgba(255,70,70,.12);color:#ff8a8a;border-color:rgba(255,70,70,.35);}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.btn-save{background:#1d8cf8;color:#fff;border:none;border-radius:10px;padding:11px 18px;font-size:14px;font-weight:600;cursor:pointer;transition:.2s;}
.btn-save:hover{background:#1673d1;}
.btn-cancel{background:#212836;color:#cdd5e0;border:1px solid #2c3444;border-radius:10px;padding:11px 18px;font-size:14px;cursor:pointer;transition:.2s;}
.btn-cancel:hover{color:#fff;border-color:#ff4d4f;}
.muted2{color:#596474;font-size:12px;}
.right-empty{padding:30px 10px;text-align:center;color:#8b95a5;font-size:13px;}
@media(max-width:520px){.drawer{width:100vw;}}
</style>

<div class="drawer-backdrop" id="rightBackdrop"></div>

<div class="drawer" id="rightNotifDrawer" aria-hidden="true">
  <div class="drawer-head">
    <h3>🔔 Notifications</h3>
    <div class="right-actions" style="margin:0;">
      <form method="post">
        <input type="hidden" name="action" value="notif_read_all">
        <button class="mini-btn" type="submit">Mark all read</button>
      </form>
      <button type="button" class="drawer-close" aria-label="Close">✕</button>
    </div>
  </div>
  <div class="drawer-body">
    <?php if (!$notifs): ?>
      <div class="right-empty">You're all caught up 🎉</div>
    <?php else: ?>
      <?php foreach ($notifs as $n): ?>
        <div class="notif-item">
          <div>
            <strong><?= right_escape($n['title']) ?></strong>
            <p><?= right_escape($n['message']) ?></p>
            <div class="notif-time"><?= right_escape(date('M j, g:i a', strtotime($n['created_at']))) ?></div>
          </div>
          <div class="notif-actions">
            <?php if (empty($n['is_read'])): ?>
              <form method="post">
                <input type="hidden" name="action" value="notif_read">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button class="mini-btn" type="submit" title="Mark as read">✓</button>
              </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Delete this notification?');">
              <input type="hidden" name="action" value="notif_delete">
              <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
              <button class="mini-btn danger" type="submit" title="Delete">✕</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="drawer" id="rightProfileDrawer" aria-hidden="true">
  <div class="drawer-head">
    <h3>👤 <?= right_escape($cfg['profile_title']) ?></h3>
    <button type="button" class="drawer-close" aria-label="Close">✕</button>
  </div>
  <div class="drawer-body">
    <?php if ($flash): ?>
      <div class="alert <?= $flash[0] === 'success' ? 'success' : 'error' ?>"><?= right_escape($flash[1]) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="action" value="profile_update">
      <label class="rf-label"><?= right_escape($cfg['profile_label_field'] === 'hospital_name' ? 'Hospital Name' : ($cfg['profile_label_field'] === 'pharmacy_name' ? 'Pharmacy Name' : 'Name')) ?></label>
      <input class="rf-input" name="profile_label" value="<?= right_escape($label) ?>" required>
      <?php if ($email !== ''): ?>
        <div class="rf-label" style="margin-bottom:6px;">Email (read-only)</div>
        <input class="rf-input" value="<?= right_escape($email) ?>" disabled>
      <?php endif; ?>
      <?php foreach ($editable as $f): ?>
        <label class="rf-label"><?= right_escape($f['label']) ?></label>
        <?php if (($f['type'] ?? '') === 'textarea'): ?>
          <textarea class="rf-input" name="<?= right_escape($f['name']) ?>" rows="2"><?= right_escape($profile[$f['name']] ?? '') ?></textarea>
        <?php else: ?>
          <input class="rf-input" name="<?= right_escape($f['name']) ?>" value="<?= right_escape($profile[$f['name']] ?? '') ?>">
        <?php endif; ?>
      <?php endforeach; ?>

      <hr style="border:0;border-top:1px solid #1f2632;margin:18px 0;">
      <div class="muted2" style="margin-bottom:6px;">Change Password <span class="muted2">(leave blank to keep current)</span></div>
      <label class="rf-label">Current Password</label>
      <input class="rf-input" type="password" name="current_password" autocomplete="current-password">
      <label class="rf-label">New Password</label>
      <input class="rf-input" type="password" name="new_password" autocomplete="new-password">

      <div style="display:flex;gap:10px;margin-top:20px;">
        <button class="btn-save" type="submit">Save Changes</button>
        <button class="btn-cancel" type="button" onclick="rightCloseAll()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  function closeAll() {
    document.querySelectorAll('.drawer').forEach(function (d) {
      d.classList.remove('open');
      d.setAttribute('aria-hidden', 'true');
    });
    var bd = document.getElementById('rightBackdrop');
    if (bd) bd.classList.remove('show');
  }
  window.rightCloseAll = closeAll;
  function open(id) {
    closeAll();
    var d = document.getElementById(id);
    if (!d) return;
    d.classList.add('open');
    d.setAttribute('aria-hidden', 'false');
    var bd = document.getElementById('rightBackdrop');
    if (bd) bd.classList.add('show');
  }
  document.querySelectorAll('[data-open-drawer]').forEach(function (b) {
    b.addEventListener('click', function () { open(b.getAttribute('data-open-drawer')); });
  });
  document.querySelectorAll('.drawer .drawer-close').forEach(function (x) {
    x.addEventListener('click', closeAll);
  });
  var bd = document.getElementById('rightBackdrop');
  if (bd) bd.addEventListener('click', closeAll);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
  var h = window.location.hash;
  if (h === '#notifications') open('rightNotifDrawer');
  else if (h === '#profile') open('rightProfileDrawer');
})();
</script>
    <?php
}

}