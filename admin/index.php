<?php
session_start();
require __DIR__ . '/../config.php';

$err = '';
if (isset($_SESSION['admin_id'])) {
  header('Location: dashboard.php'); exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = trim($_POST['id'] ?? '');
  $pw = $_POST['password'] ?? '';

  if ($id === '' || $pw === '') {
    $err = 'Please enter your email/username and password.';
  } else {
    $sql = "SELECT id, username, email, password_hash
            FROM admin_users
            WHERE email = :email OR username = :username
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $id, ':username' => $id]);
    $user = $stmt->fetch();

    if ($user && password_verify($pw, $user['password_hash'])) {
      $_SESSION['admin_id'] = (int)$user['id'];
      $_SESSION['admin_username'] = $user['username'];
      header('Location: dashboard.php'); exit;
    } else {
      $err = 'Invalid credentials.';
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — Mr.Look</title>
<style>
  :root {
    --bg:#0b1326; --panel:#111827; --text:#e5e7eb; --muted:#9ca3af; --accent:#f59e0b; --radius:14px;
  }
  * { box-sizing: border-box; }
  body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:var(--bg); color:var(--text); display:grid; place-items:center; min-height:100dvh; }
  .card { width: 100%; max-width: 420px; background:var(--panel); padding:24px; border-radius:var(--radius); box-shadow: 0 10px 30px rgba(0,0,0,.35); }
  h1 { margin:0 0 14px; font-size:1.4rem; }
  p.muted { color:var(--muted); margin:0 0 16px; }
  label { display:block; margin:12px 0 6px; font-weight:600; }
  input { width:100%; padding:12px 14px; border-radius:10px; border:1px solid #1f2937; background:#0f172a; color:var(--text); }
  .btn { width:100%; margin-top:16px; padding:12px 14px; border:none; border-radius:10px; background:var(--accent); color:#111; font-weight:700; cursor:pointer; }
  .error { background:#3b0d0d; color:#ffdcdc; padding:10px 12px; border-radius:10px; margin:8px 0 0; }
</style>
</head>
<body>
  <form class="card" method="post" action="index.php" autocomplete="off">
    <h1>Admin Login</h1>
    <p class="muted">Use your <b>email</b> or <b>username</b> and password.</p>

    <label for="id">Email or Username</label>
    <input id="id" name="id" required placeholder="admin@mrlook.com or mrlookadmin">

    <label for="password">Password</label>
    <input id="password" type="password" name="password" required placeholder="••••••••">

    <?php if ($err): ?>
      <div class="error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <button class="btn" type="submit">Sign in</button>
  </form>
</body>
</html>
