<?php
// /admin/change_password.php
declare(strict_types=1);
session_start();
require __DIR__ . '/../config.php';

if (empty($_SESSION['admin_id'])) { header('Location: /admin/login.php'); exit; }

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function check_csrf(): void {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(403); exit('Bad CSRF'); }
}

$uid = (int)$_SESSION['admin_id'];
$user = null;
try {
  $stmt = $pdo->prepare("SELECT id, username, email, password_hash, contact_number, created_at FROM admin_users WHERE id = :id LIMIT 1");
  $stmt->execute([':id'=>$uid]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $user = null; }

if (!$user) { // logged-in session but user missing
  session_destroy();
  header('Location: /admin/login.php'); exit;
}

$okMsg = '';
$errMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change') {
  check_csrf();

  $current = (string)($_POST['current'] ?? '');
  $new     = (string)($_POST['new'] ?? '');
  $confirm = (string)($_POST['confirm'] ?? '');

  // Basic validation
  if ($current === '' || $new === '' || $confirm === '') {
    $errMsg = 'Please fill in all fields.';
  } elseif (!password_verify($current, $user['password_hash'])) {
    $errMsg = 'Your current password is incorrect.';
  } elseif ($new !== $confirm) {
    $errMsg = 'New password and confirmation do not match.';
  } else {
    // Policy: at least 8 chars, at least one letter and one number
    $lenOK = strlen($new) >= 8;
    $hasL  = preg_match('/[A-Za-z]/', $new);
    $hasD  = preg_match('/\d/', $new);
    if (!$lenOK || !$hasL || !$hasD) {
      $errMsg = 'Use at least 8 characters with letters and numbers.';
    } elseif (hash_equals($current, $new)) {
      $errMsg = 'New password must be different from the current password.';
    } else {
      try {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd  = $pdo->prepare("UPDATE admin_users SET password_hash = :h WHERE id = :id LIMIT 1");
        $upd->execute([':h'=>$hash, ':id'=>$user['id']]);

        // Refresh session id for safety
        session_regenerate_id(true);
        $okMsg = 'Password updated successfully.';
      } catch (Throwable $e) {
        $errMsg = 'Something went wrong while saving the new password.';
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Change Password · Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/admin.css">
  <style>
    /* Page shell (keeps the same admin vibe) */
    .page{max-width:720px;margin:26px auto;padding:0 16px;color:#e9eefb}
    .bar{display:flex;align-items:center;justify-content:space-between;margin:6px 0 18px}
    .meta{opacity:.8;font-size:.95rem}
    .card{background:#0e1422;border:1px solid #2a2f3a;border-radius:14px;box-shadow:0 12px 28px rgba(0,0,0,.22);overflow:hidden}
    .card h2{margin:0;padding:14px 16px;border-bottom:1px solid #212838}
    .body{padding:16px}
    label{display:block;margin:10px 0 6px}
    .row{display:grid;grid-template-columns:1fr auto;align-items:center;gap:10px}
    .field{position:relative}
    input[type=password],input[type=text]{width:100%;padding:12px 40px 12px 12px;border-radius:12px;border:1px solid #394151;background:#121826;color:#e9eefb}
    .toggle{
      position:absolute; right:8px; top:50%; transform:translateY(-50%); width:34px; height:34px;
      display:grid; place-items:center; border-radius:8px; border:1px solid #2b3344; background:#0d1320; color:#cfd7ee; cursor:pointer;
    }
    .help{margin-top:4px;opacity:.7;font-size:.9rem}
    .btn{display:inline-flex;align-items:center;gap:.5rem;padding:10px 16px;border-radius:12px;border:1px solid #394151;text-decoration:none;color:#e9eefb;background:#0f1626;cursor:pointer}
    .btn.primary{background:#ffd166;color:#111;border-color:#ffd166;font-weight:800}
    .btn.ghost{background:#121826}
    .btn:hover{transform:translateY(-1px)}
    .actions{display:flex;gap:10px;margin-top:12px}
    .alert{padding:10px 12px;border-radius:12px;margin-bottom:12px;border:1px solid transparent}
    .alert.ok{background:#19341f;border-color:#2b6f39;color:#c9f3d2}
    .alert.err{background:#3b1a1a;border-color:#8a4040;color:#ffd6d6}
    /* Strength meter */
    .meter{height:10px;border-radius:999px;background:#1a2232;border:1px solid #2c3446;overflow:hidden;margin-top:6px}
    .meter > span{display:block;height:100%;width:0%;background:linear-gradient(90deg,#ff4d4d,#ffd166,#2df39d);transition:width .2s ease}
    .tips{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
    .chip{padding:.45rem .6rem;border-radius:999px;background:#141c2b;border:1px solid #273049;font-size:.9rem}
  </style>
</head>
<body>
  <div class="page">
    <div class="bar">
      <h1 style="margin:0">Change Password</h1>
      <div class="actions">
        <a class="btn" href="/admin/dashboard.php">Dashboard</a>
        <a class="btn ghost" href="/admin/albums.php">Albums</a>
      </div>
    </div>

    <p class="meta">Signed in as <strong><?= e($user['username']) ?></strong> · <?= e($user['email']) ?></p>

    <?php if ($okMsg): ?><div class="alert ok"><?= e($okMsg) ?></div><?php endif; ?>
    <?php if ($errMsg): ?><div class="alert err"><?= e($errMsg) ?></div><?php endif; ?>

    <div class="card">
      <h2>Update your password</h2>
      <div class="body">
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="change">

          <div class="field">
            <label for="current">Current password</label>
            <input id="current" name="current" type="password" required>
            <button type="button" class="toggle" data-toggle="#current" aria-label="Show/hide">👁</button>
          </div>

          <div class="field">
            <label for="new">New password</label>
            <input id="new" name="new" type="password" minlength="8" required aria-describedby="pHelp">
            <button type="button" class="toggle" data-toggle="#new" aria-label="Show/hide">👁</button>
            <div class="meter" aria-hidden="true"><span id="meterFill"></span></div>
            <p id="pHelp" class="help">Use at least 8 characters with letters and numbers.</p>
            <div class="tips">
              <span class="chip">Add a symbol (e.g. !@#)</span>
              <span class="chip">Mix UPPER/lowercase</span>
              <span class="chip">Avoid common words</span>
            </div>
          </div>

          <div class="field">
            <label for="confirm">Confirm new password</label>
            <input id="confirm" name="confirm" type="password" minlength="8" required>
            <button type="button" class="toggle" data-toggle="#confirm" aria-label="Show/hide">👁</button>
          </div>

          <div class="actions" style="margin-top:14px">
            <button class="btn primary" type="submit">Save New Password</button>
            <button class="btn" type="button" id="gen">Generate strong password</button>
          </div>
        </form>
      </div>
    </div>

  </div>

  <script>
    // show/hide toggles
    document.querySelectorAll('.toggle').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const sel = btn.getAttribute('data-toggle');
        const inp = document.querySelector(sel);
        if(!inp) return;
        inp.type = (inp.type === 'password') ? 'text' : 'password';
      });
    });

    // strength meter
    const newPw = document.getElementById('new');
    const meter = document.getElementById('meterFill');
    function score(pw){
      let s = 0;
      if (!pw) return 0;
      if (pw.length >= 8) s += 25;
      if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s += 25;
      if (/\d/.test(pw)) s += 25;
      if (/[^A-Za-z0-9]/.test(pw)) s += 25;
      return Math.min(100, s);
    }
    newPw.addEventListener('input', ()=>{
      const val = newPw.value;
      meter.style.width = score(val) + '%';
    });

    // generator
    document.getElementById('gen').addEventListener('click', ()=>{
      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()_+';
      let out = '';
      for(let i=0;i<14;i++){ out += chars[Math.floor(Math.random()*chars.length)]; }
      newPw.value = out;
      meter.style.width = '100%';
      document.getElementById('confirm').value = '';
      newPw.focus();
    });
  </script>
</body>
</html>
