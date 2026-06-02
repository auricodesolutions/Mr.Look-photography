<?php
// /admin/albums.php
declare(strict_types=1);
session_start();
require __DIR__ . '/../config.php';

/* ---------- Auth: reuse your dashboard session --------- */
if (empty($_SESSION['admin_id'])) {
  header('Location: /admin/login.php'); exit;
}

/* ---------- Helpers ---------- */
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function csrf(): string {
  $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function check_csrf(): void {
  if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(403); exit('Bad CSRF');
  }
}

/* ---------- Config ---------- */
$ALBUM_LIMIT = 12;
$UPLOAD_ROOT = realpath(__DIR__ . '/../assets/uploads') ?: (__DIR__ . '/../assets/uploads');

/* ---------- Actions: delete album ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  check_csrf();
  $id = (int)($_POST['id'] ?? 0);

  try {
    $stmt = $pdo->prepare("SELECT slug FROM albums WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      $pdo->beginTransaction();
      $pdo->prepare("DELETE FROM photos WHERE album_id = :id")->execute([':id'=>$id]);
      $pdo->prepare("DELETE FROM albums WHERE id = :id")->execute([':id'=>$id]);
      $pdo->commit();

      if (isset($_POST['delete_files']) && $_POST['delete_files'] === '1') {
        $slug = $row['slug'];
        $dir  = rtrim($UPLOAD_ROOT, '/') . '/' . $slug;
        if (is_dir($dir)) {
          $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
          );
          foreach ($it as $f) { $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath()); }
          @rmdir($dir);
        }
      }
    }
  } catch (Throwable $e) { /* ignore */ }
  header('Location: /admin/albums.php?deleted=1'); exit;
}

/* ---------- Filters ---------- */
$q    = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'new'; // new | old | az | za
$sortMap = [
  'new' => 'created_at DESC, id DESC',
  'old' => 'created_at ASC, id ASC',
  'az'  => 'title ASC, id ASC',
  'za'  => 'title DESC, id DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['new'];

$where  = '';
$params = [];
if ($q !== '') {
  $where = "WHERE title LIKE :q OR slug LIKE :q";
  $params[':q'] = '%'.$q.'%';
}

/* ---------- Counts ---------- */
$totalAll = 0;
try {
  $totalAll = (int)$pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn();
} catch (Throwable $e) { $totalAll = 0; }
$canAdd = ($totalAll < $ALBUM_LIMIT);

/* ---------- Load list ---------- */
$albums = [];
try {
  $sql = "SELECT id, slug, title, cover_path, created_at
          FROM albums
          $where
          ORDER BY $orderBy";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) $stmt->bindValue($k, $v);
  $stmt->execute();
  $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $albums = []; }

/* ---------- Helper ---------- */
function count_album_images(string $root, string $slug): int {
  $dir = rtrim($root,'/') . '/' . $slug;
  if (!is_dir($dir)) return 0;
  $list = glob($dir . '/img*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
  return count($list);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Albums · Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/admin.css"><!-- your admin theme -->

  <style>
    /* ================= Theme tokens (unified dark) ================= */
    :root{
      --bg:#071120;            /* page background */
      --panel:#0b1420;         /* table body */
      --panel-2:#0f1a2a;       /* table header / topbar band */
      --ink:#eaf2ff;           /* primary text (light) */
      --muted:#c7d3e4;         /* secondary text */
      --muted-2:#9fb0c8;       /* placeholder / lighter */
      --border:#1f2a3a;        /* borders */
      --border-2:#2a3243;      /* control borders */
      --chip:#121a26;          /* control bg */
      --accent:#ffd166;        /* brand accent */
      --row-hover:#0e1827;     /* row hover */
    }

    /* ================= Layout / background ================= */
    html, body { background: var(--bg); color: var(--ink); }
    .page{max-width:1200px;margin:24px auto 40px;padding:0 16px}

    .topbar{
      position:sticky; top:0; z-index:20;
      background:var(--panel-2);
      border-bottom:1px solid var(--border);
      padding:10px 0; margin-bottom:16px;
    }
    .topbar__inner{
      max-width:1200px; margin:0 auto; padding:0 16px;
      display:grid; grid-template-columns:auto 1fr auto; gap:12px; align-items:center;
    }
    .title{ margin:0; font-weight:900; font-size:1.25rem; letter-spacing:.2px; color:var(--ink); }

    /* ================= Controls ================= */
    .btn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 14px; border-radius:12px; text-decoration:none; cursor:pointer;
      border:1px solid var(--border-2); background:var(--chip); color:var(--ink);
      transition:transform .15s ease, background .15s ease, border-color .15s ease;
    }
    .btn:hover{ transform:translateY(-1px); background:#162235; }
    .btn.primary{ background:var(--accent); color:#111; border-color:var(--accent); font-weight:900; }
    .btn.primary:hover{ background:#ffca3a; }
    .btn[disabled]{ opacity:.55; cursor:not-allowed; transform:none; }

    .filters{ display:flex; gap:10px; align-items:center }
    .search{
      display:flex; align-items:center; gap:8px;
      padding:8px 10px; border:1px solid var(--border-2); border-radius:10px; background:var(--chip); color:var(--ink);
    }
    .search input{
      background:transparent; border:none; outline:none; color:var(--ink);
      width:220px; max-width:48vw;
    }
    .search input::placeholder{ color:var(--muted-2); }
    .sort{
      padding:8px 10px; border:1px solid var(--border-2); border-radius:10px; background:var(--chip); color:var(--ink);
    }

    /* ================= Callouts / stats ================= */
    .statbar{display:flex; gap:10px; align-items:center; margin:8px 0 16px; flex-wrap:wrap}
    .pill{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px; border-radius:999px; border:1px solid var(--border-2);
      background:var(--chip); font-size:.92rem; color:var(--ink);
    }
    .limit{ background:#261a00; border-color:#3b2a00; color:var(--accent); }
    .progress{ position:relative; height:10px; width:220px; background:#1b2535; border-radius:999px; overflow:hidden; }
    .progress>span{ position:absolute; inset:0; width:var(--w,0%); background:linear-gradient(90deg,var(--accent),#ffb703); }

    /* ================= Table ================= */
    table{width:100%; border-collapse:collapse; background:var(--panel); border:1px solid var(--border); border-radius:12px; overflow:hidden}
    thead th{
      text-align:left; font-weight:800; color:var(--ink);
      background:var(--panel-2); border-bottom:1px solid var(--border);
    }
    th,td{ padding:12px 12px; border-bottom:1px solid var(--border); vertical-align:middle; color:var(--ink) }
    tbody tr:hover{ background:var(--row-hover); }
    .muted{ color:var(--muted); }
    img.thumb{ width:72px; height:56px; object-fit:cover; border-radius:6px; border:1px solid var(--border); display:block }

    .grid{ display:flex; gap:8px; flex-wrap:wrap }
    form.inline{ display:inline }
    code{ background:#101827; color:#dbe6f8; padding:2px 6px; border-radius:6px; border:1px solid #1f2937 }

    /* ================= Responsive ================= */
    @media (max-width:760px){
      .topbar__inner{ grid-template-columns:1fr; gap:10px; }
      .filters{ flex-wrap:wrap }
      .search input{ width:100% }
      .hide-sm{ display:none }
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="topbar__inner">
      <h1 class="title">Albums</h1>

      <form class="filters" method="get">
        <label class="search" aria-label="Search albums">
          🔎 <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search title or slug…">
        </label>
        <select class="sort" name="sort" aria-label="Sort albums">
          <option value="new" <?= $sort==='new'?'selected':'' ?>>Newest first</option>
          <option value="old" <?= $sort==='old'?'selected':'' ?>>Oldest first</option>
          <option value="az"  <?= $sort==='az' ?'selected':'' ?>>Title A–Z</option>
          <option value="za"  <?= $sort==='za' ?'selected':'' ?>>Title Z–A</option>
        </select>
        <button class="btn" type="submit">Apply</button>
        <?php if ($q !== '' || $sort !== 'new'): ?>
          <a class="btn" href="/admin/albums.php">Reset</a>
        <?php endif; ?>
      </form>

      <div class="actions">
        <?php if ($canAdd): ?>
          <a class="btn primary" href="/admin/album_edit.php">+ Add Album</a>
        <?php else: ?>
          <button class="btn primary" disabled title="Album limit reached (<?= $ALBUM_LIMIT ?>). Delete one to add a new album.">+ Add Album</button>
        <?php endif; ?>
        <a class="btn" href="/admin/dashboard.php">Dashboard</a>
      </div>
    </div>
  </div>

  <div class="page">
    <div class="statbar">
      <span class="pill">Total: <strong><?= (int)$totalAll ?></strong> / <?= $ALBUM_LIMIT ?></span>
      <span class="progress" style="--w: <?= min(100, ($totalAll / max($ALBUM_LIMIT,1))*100) ?>%"><span></span></span>
      <?php if (!$canAdd): ?>
        <span class="pill limit">Limit reached — delete an album to add another</span>
      <?php else: ?>
        <span class="pill">You can add <strong><?= ($ALBUM_LIMIT - (int)$totalAll) ?></strong> more</span>
      <?php endif; ?>
      <?php if (isset($_GET['deleted'])): ?>
        <span class="pill">Album deleted.</span>
      <?php endif; ?>
    </div>

    <table role="table" aria-label="Albums list">
      <thead>
        <tr>
          <th class="hide-sm">ID</th>
          <th>Cover</th>
          <th>Title</th>
          <th>Slug</th>
          <th>Images</th>
          <th class="hide-sm">Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($albums as $a): ?>
        <?php
          $count = count_album_images($UPLOAD_ROOT, $a['slug']);
          $coverAbs = __DIR__ . '/../' . $a['cover_path'];
        ?>
        <tr>
          <td class="hide-sm"><?= (int)$a['id'] ?></td>

          <td>
            <?php if (!empty($a['cover_path']) && is_file($coverAbs)): ?>
              <img class="thumb" src="/<?= e($a['cover_path']) ?>?v=<?= @filemtime($coverAbs) ?>" alt="">
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>

          <td><?= e($a['title']) ?></td>
          <td><code><?= e($a['slug']) ?></code></td>
          <td><?= $count ?>/10</td>
          <td class="hide-sm"><?= e(date('Y-m-d', strtotime($a['created_at'] ?? 'now'))) ?></td>

          <td>
            <div class="grid">
              <a class="btn" href="/admin/album_images.php?id=<?= (int)$a['id'] ?>">Images</a>
              <a class="btn" href="/admin/album_edit.php?id=<?= (int)$a['id'] ?>">Edit</a>

              <form class="inline" method="post"
                    onsubmit="return confirm('Delete this album? This removes database rows; files are kept unless you tick the box.')">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <label class="muted" style="font-size:.85rem;margin-right:6px">
                  <input type="checkbox" name="delete_files" value="1"> also delete folder
                </label>
                <button class="btn" type="submit">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$albums): ?>
        <tr><td colspan="7" style="text-align:center; padding:24px" class="muted">No albums found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <p class="muted" style="margin-top:10px">
      Tip: Each album can hold up to <strong>10 images</strong> named like <code>img1.jpg</code>…<code>img10.jpg</code>.
      The cover image is separate (e.g., <code>cover.jpg</code>).
    </p>
  </div>
</body>
</html>
