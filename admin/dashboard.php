<?php
// dashboard.php
session_start();
if (!isset($_SESSION['admin_id'])) {
  header('Location: index.php');
  exit;
}
$me = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard — Mr.Look</title>
<style>
  :root {
    --bg:#0b1326; --panel:#0f172a; --text:#e5e7eb; --muted:#9ca3af; --accent:#f59e0b;
    --border:#1f2937; --radius:14px; --sidebar-w:260px; --sidebar-w-collapsed:76px;
  }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; display:flex; min-height:100dvh; }
  .sidebar {
    width:var(--sidebar-w);
    background:var(--panel);
    border-right:1px solid var(--border);
    padding:16px 12px;
    position:sticky; top:0; height:100dvh;
    transition:width .25s ease;
  }
  body.sidebar-collapsed .sidebar { width:var(--sidebar-w-collapsed); }
  .brand { display:flex; align-items:center; gap:10px; margin-bottom:14px; white-space:nowrap; overflow:hidden; }
  .brand .logo { width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#f59e0b,#d97706); display:grid; place-items:center; font-weight:800; color:#111; }
  .brand .name { font-weight:800; }
  body.sidebar-collapsed .brand .name { display:none; }

  .menu { margin-top:6px; display:flex; flex-direction:column; gap:6px; }
  .menu a {
    display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:10px;
    color:var(--text); text-decoration:none; border:1px solid transparent;
  }
  .menu a:hover { background:#111827; border-color:var(--border); }
  .menu .ico { width:22px; display:inline-grid; place-items:center; opacity:.85; }
  body.sidebar-collapsed .menu .label { display:none; }

  .main {
    flex:1; display:flex; flex-direction:column; min-width:0;
  }
  .topbar {
    position:sticky; top:0; z-index:5; background:rgba(15,23,42,.7); backdrop-filter:saturate(140%) blur(8px);
    border-bottom:1px solid var(--border); padding:10px 14px; display:flex; align-items:center; gap:10px;
  }
  .btn-icon {
    width:38px; height:38px; display:inline-grid; place-items:center; border:1px solid var(--border);
    border-radius:10px; background:#0f172a; color:var(--text); cursor:pointer;
  }
  .content { padding:18px; }
  .grid { display:grid; gap:16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
  .card {
    background:var(--panel); border:1px solid var(--border); border-radius:var(--radius);
    padding:16px;
  }
  .muted { color:var(--muted); }
  .pill { display:inline-block; padding:6px 10px; background:#111827; border:1px solid var(--border); border-radius:999px; }
</style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand">
      <div class="logo">ML</div>
      <div class="name">Mr.Look Admin</div>
    </div>
    <nav class="menu">
      <a href="dashboard.php"><span class="ico">🏠</span><span class="label">Dashboard</span></a>
      <a href="images.php"><span class="ico">🖼️</span><span class="label">Images</span></a>
      <a href="albums.php"><span class="ico">🖼️</span><span class="label">Albums</span></a>

      <a href="reviews.php"><span class="ico">⭐</span><span class="label">Reviews</span></a>
      <a href="bookings.php"><span class="ico">📅</span><span class="label">Bookings</span></a>
      <a href="change_password.php"><span class="ico">👤</span><span class="label">Admins</span></a>
      <a href="logout.php"><span class="ico">🚪</span><span class="label">Logout</span></a>
    </nav>
  </aside>

  <main class="main">
    <header class="topbar">
      <button id="menuToggle" class="btn-icon" title="Toggle menu">☰</button>
      <div style="flex:1"></div>
      <span class="pill">Signed in as <b><?= $me ?></b></span>
    </header>

    <section class="content">
      <h2 style="margin:0 0 12px">Dashboard</h2>
      <p class="muted">Quick stats & shortcuts</p>

      <div class="grid" style="margin-top:14px">
        <div class="card">
          <h3 style="margin:0 0 10px">Images</h3>
          <p class="muted">Upload and manage gallery items.</p>
        </div>
        <div class="card">
          <h3 style="margin:0 0 10px">Reviews</h3>
          <p class="muted">Approve or hide customer reviews.</p>
        </div>
        <div class="card">
          <h3 style="margin:0 0 10px">Bookings</h3>
          <p class="muted">View and respond to booking requests.</p>
        </div>
      </div>
    </section>
  </main>

<script>
  document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.body.classList.toggle('sidebar-collapsed');
  });
</script>
</body>
</html>
