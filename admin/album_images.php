<?php
// /admin/album_images.php
declare(strict_types=1);
session_start();
require __DIR__ . '/../config.php';
if (empty($_SESSION['admin_id'])) { header('Location: /admin/login.php'); exit; }

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function check_csrf(): void { if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(403); exit('Bad CSRF'); } }

$MAX_IMAGES   = 10;
$UPLOAD_WEB   = 'assets/uploads';
$UPLOAD_ROOT  = realpath(__DIR__ . '/../' . $UPLOAD_WEB) ?: (__DIR__ . '/../' . $UPLOAD_WEB);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/albums.php'); exit; }

$q = $pdo->prepare("SELECT id, slug, title FROM albums WHERE id = :id");
$q->execute([':id'=>$id]);
$album = $q->fetch(PDO::FETCH_ASSOC);
if (!$album) { header('Location: /admin/albums.php'); exit; }

$dir = $UPLOAD_ROOT . '/' . $album['slug'];
@mkdir($dir, 0775, true);

// ---------- helpers ----------
function scan_imgs(string $dir): array {
  $files = glob($dir . '/img*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
  usort($files, function($a,$b){
    $na = (int)preg_replace('~\D+~','',basename($a));
    $nb = (int)preg_replace('~\D+~','',basename($b));
    return $na <=> $nb;
  });
  return $files;
}
function web_from_fs(string $path): string {
  $root = realpath(__DIR__.'/..');
  $real = realpath($path);
  return ltrim(str_replace($root, '', $real), '/');
}
function reindex_and_sync(PDO $pdo, int $albumId, string $slug, string $dir, string $webBase): void {
  $list = scan_imgs($dir);
  // rename to contiguous img1..imgN
  $i = 1;
  foreach ($list as $old) {
    $ext = strtolower(pathinfo($old, PATHINFO_EXTENSION));
    $new = $dir . "/img{$i}." . $ext;
    if ($old !== $new) @rename($old, $new);
    $i++;
  }
  // rebuild DB rows to match file order
  $pdo->prepare("DELETE FROM photos WHERE album_id = :id")->execute([':id'=>$albumId]);
  $list = scan_imgs($dir);
  $i = 1;
  foreach ($list as $f) {
    $rel = "$webBase/$slug/" . basename($f);
    $pdo->prepare("INSERT INTO photos (album_id, title, file_path, sort_order, created_at)
                   VALUES (:aid, :title, :path, :ord, NOW())")
        ->execute([':aid'=>$albumId, ':title'=>"Photo $i", ':path'=>$rel, ':ord'=>$i]);
    $i++;
  }
}

// ---------- actions ----------
$msg = '';
$type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'upload') {
    check_csrf();
    $existing = scan_imgs($dir);
    $slots = $MAX_IMAGES - count($existing);
    if ($slots <= 0) {
      $msg = "This album already has $MAX_IMAGES images.";
      $type = 'warn';
    } else {
      $files = $_FILES['images'] ?? null;
      if ($files && is_array($files['name'])) {
        $added = 0; $allowed = ['jpg','jpeg','png','webp'];
        $N = count($files['name']);
        for ($i=0; $i<$N; $i++) {
          if ($added >= $slots) break;
          if (!is_uploaded_file($files['tmp_name'][$i])) continue;
          $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
          if (!in_array($ext, $allowed, true)) $ext = 'jpg';
          $existing = scan_imgs($dir);
          $next = count($existing) + 1;
          $dst = $dir . "/img{$next}.$ext";
          if (@move_uploaded_file($files['tmp_name'][$i], $dst)) $added++;
        }
        reindex_and_sync($pdo, (int)$album['id'], $album['slug'], $dir, $UPLOAD_WEB);
        $msg = "Uploaded $added file(s).";
        $type = $added ? 'ok' : 'warn';
      }
    }
  }

  if ($action === 'delete') {
    check_csrf();
    $sel = $_POST['del'] ?? [];
    $removed = 0;
    foreach ($sel as $name) {
      $name = basename($name);
      $path = $dir . '/' . $name;
      if (is_file($path) && str_starts_with($name, 'img')) {
        if (@unlink($path)) $removed++;
      }
    }
    reindex_and_sync($pdo, (int)$album['id'], $album['slug'], $dir, $UPLOAD_WEB);
    $msg = $removed ? "Deleted $removed image(s)." : "No images selected.";
    $type = $removed ? 'ok' : 'info';
  }
}

$imgs  = scan_imgs($dir);
$count = count($imgs);
$left  = max(0, $MAX_IMAGES - $count);
$ratio = $MAX_IMAGES ? ($count / $MAX_IMAGES) : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Images · <?= e($album['title']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/admin.css">
  <style>
    :root{
      --bg:#071120; --panel:#0b1420; --panel-2:#0f1a2a;
      --ink:#eaf2ff; --muted:#c7d3e4; --muted2:#9fb0c8;
      --border:#1f2a3a; --border2:#2a3243; --chip:#121a26;
      --accent:#ffd166; --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444;
    }
    html,body{background:var(--bg); color:var(--ink)}
    .topbar{position:sticky; top:0; z-index:20; background:var(--panel-2); border-bottom:1px solid var(--border)}
    .topbar__in{max-width:1100px;margin:0 auto;padding:12px 16px;display:flex;align-items:center;gap:10px}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;text-decoration:none;border:1px solid var(--border2);background:var(--chip);color:var(--ink);cursor:pointer}
    .btn:hover{transform:translateY(-1px);background:#162235}
    .btn.primary{background:var(--accent);border-color:var(--accent);color:#111;font-weight:900}
    .btn.ghost{background:transparent}
    .btn.small{padding:8px 10px;border-radius:10px}
    .page{max-width:1100px;margin:20px auto 40px;padding:0 16px}
    .bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    .grow{flex:1}
    .badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:var(--chip);border:1px solid var(--border2);color:var(--muted)}
    .badge.ok{background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.35); color:#b6f3c8}
    .badge.warn{background:rgba(245,158,11,.10); border-color:rgba(245,158,11,.35); color:#ffe1a8}
    .barline{height:10px;border-radius:999px;background:#0c1726;border:1px solid var(--border2);overflow:hidden}
    .barline > span{display:block;height:100%;background:linear-gradient(90deg,#38bdf8,#3b82f6);width:<?= (int)round($ratio*100) ?>%}
    .panel{background:var(--panel);border:1px solid var(--border);border-radius:14px;box-shadow:0 12px 28px rgba(0,0,0,.18)}
    .section{padding:16px}
    h1{font-size:1.2rem;margin:0}
    h3{margin:0 0 10px 0;color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px}
    .card{position:relative;overflow:hidden;border-radius:12px;border:1px solid var(--border2);background:#0e1422}
    .thumb{width:100%;height:150px;object-fit:cover;display:block}
    .pick{position:absolute;left:8px;top:8px;background:rgba(0,0,0,.55);border:1px solid rgba(255,255,255,.25);backdrop-filter:saturate(130%) blur(4px);color:#fff;border-radius:8px;padding:6px 8px;font-size:.88rem}
    .name{position:absolute;right:8px;bottom:8px;background:rgba(0,0,0,.55);border:1px solid rgba(255,255,255,.25);border-radius:8px;padding:4px 8px;font-size:.82rem;color:#fff}
    .muted{color:var(--muted)}
    .msg{margin:12px 0 0;padding:10px 12px;border-radius:12px;border:1px solid var(--border2);background:var(--chip)}
    .msg.ok{border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.10);color:#b6f3c8}
    .msg.warn{border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.10);color:#ffe1a8}
    .msg.info{color:var(--muted)}
    .drop{display:grid;place-items:center;text-align:center;padding:18px;border:2px dashed var(--border2);border-radius:12px;background:#0c1726;color:var(--muted)}
    .drop.drag{background:#0e2038}
    input[type=file]{display:none}
    .mini{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
    .mini img{width:58px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--border2)}
    .tools{display:flex;gap:8px;flex-wrap:wrap}
  </style>
</head>
<body>
  <!-- top -->
  <div class="topbar">
    <div class="topbar__in">
      <a class="btn ghost" href="/admin/albums.php">← Albums</a>
      <h1>Images — <?= e($album['title']) ?></h1>
      <span class="grow"></span>
      <a class="btn" href="/album.php?slug=<?= e($album['slug']) ?>" target="_blank" rel="noopener">View album</a>
      <a class="btn" href="/admin/dashboard.php">Dashboard</a>
    </div>
  </div>

  <div class="page">
    <div class="panel section">
      <div class="bar">
        <span class="badge<?= $left ? '' : ' warn' ?>">
          <strong><?= $count ?>/<?= $MAX_IMAGES ?></strong> images
        </span>
        <div class="grow barline"><span></span></div>
        <span class="badge<?= $left ? ' ok' : ' warn' ?>"><?= $left ?> slot<?= $left===1?'':'s' ?> left</span>
      </div>

      <?php if ($msg): ?>
        <div class="msg <?= e($type) ?>"><?= e($msg) ?></div>
      <?php endif; ?>
    </div>

    <!-- Upload -->
    <div class="panel section" style="margin-top:14px">
      <h3>Upload images (max <?= $MAX_IMAGES ?> per album, cover is separate)</h3>

      <form id="upForm" method="post" enctype="multipart/form-data"<?= $left ? '' : ' aria-disabled="true"' ?>>
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="upload">
        <label class="drop<?= $left? '' : ' muted' ?>" id="drop">
          <div>
            <div style="font-weight:800;margin-bottom:6px"><?= $left ? 'Drag & drop files here' : 'Limit reached' ?></div>
            <div class="muted"><?= $left ? 'or click to choose (JPG, PNG, WEBP). Up to '.$left.' file'.($left===1?'':'s').' now.' : 'Please delete some images to free up slots.' ?></div>
          </div>
          <input id="file" type="file" name="images[]" accept="image/*" multiple <?= $left? '' : 'disabled' ?>>
        </label>
        <div id="mini" class="mini" aria-live="polite"></div>
        <div class="tools" style="margin-top:10px">
          <button class="btn primary" type="submit" <?= $left? '' : 'disabled' ?>>Upload</button>
        </div>
      </form>
    </div>

    <!-- Current images -->
    <div class="panel section" style="margin-top:14px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">
        <h3 style="margin:0">Current images</h3>
        <?php if ($imgs): ?>
          <div class="tools">
            <button class="btn small" type="button" id="selAll">Select all</button>
            <button class="btn small" type="button" id="selNone">None</button>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!$imgs): ?>
        <p class="muted">No images yet.</p>
      <?php else: ?>
        <form id="delForm" method="post" onsubmit="return confirm('Delete selected images?')">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="delete">

          <div class="grid" id="grid">
            <?php foreach ($imgs as $f): $base = basename($f); $web = '/'.e(web_from_fs($f)); ?>
              <div class="card">
                <img class="thumb" src="<?= $web ?>?v=<?= @filemtime($f) ?>" alt="">
                <label class="pick">
                  <input type="checkbox" name="del[]" value="<?= e($base) ?>"> Select
                </label>
                <span class="name"><?= e($base) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="tools" style="margin-top:12px">
            <button class="btn" type="submit">Delete selected</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // --- drag & drop + preview (client-side cap to remaining slots) ---
    (function(){
      const left = <?= (int)$left ?>;
      if (!left) return;
      const drop = document.getElementById('drop');
      const file = document.getElementById('file');
      const mini = document.getElementById('mini');

      const clampFiles = (list) => {
        // enforce remaining slots
        const dt = new DataTransfer();
        const n = Math.min(list.length, left);
        for (let i=0; i<n; i++) dt.items.add(list[i]);
        return dt.files;
      };

      const renderMini = () => {
        mini.innerHTML = '';
        const n = Math.min(file.files.length, left);
        for (let i=0; i<n; i++) {
          const img = document.createElement('img');
          img.src = URL.createObjectURL(file.files[i]);
          img.onload = () => URL.revokeObjectURL(img.src);
          mini.appendChild(img);
        }
      };

      drop.addEventListener('click', () => file.click());
      drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('drag'); });
      drop.addEventListener('dragleave', () => drop.classList.remove('drag'));
      drop.addEventListener('drop', (e) => {
        e.preventDefault(); drop.classList.remove('drag');
        if (!e.dataTransfer || !e.dataTransfer.files?.length) return;
        file.files = clampFiles(e.dataTransfer.files);
        renderMini();
      });
      file.addEventListener('change', () => { file.files = clampFiles(file.files); renderMini(); });
    })();

    // --- select all / none ---
    (function(){
      const all = document.getElementById('selAll');
      const none = document.getElementById('selNone');
      const grid = document.getElementById('grid');
      if (!grid) return;
      const boxes = () => grid.querySelectorAll('input[type="checkbox"][name="del[]"]');

      all?.addEventListener('click', () => boxes().forEach(b => b.checked = true));
      none?.addEventListener('click', () => boxes().forEach(b => b.checked = false));
    })();
  </script>
</body>
</html>
