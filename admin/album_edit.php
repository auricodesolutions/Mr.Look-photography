<?php
// /admin/album_edit.php
declare(strict_types=1);
session_start();
require __DIR__ . '/../config.php';

if (empty($_SESSION['admin_id'])) { header('Location: /admin/login.php'); exit; }

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function check_csrf(): void { if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(403); exit('Bad CSRF'); } }

$ALBUM_LIMIT = 12;

$UPLOAD_WEB  = 'assets/uploads';
$UPLOAD_ROOT = realpath(__DIR__ . '/../' . $UPLOAD_WEB) ?: (__DIR__ . '/../' . $UPLOAD_WEB);

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = $id ? 'edit' : 'create';

// Load existing album (if editing)
$album = ['id'=>0,'slug'=>'','title'=>'','cover_path'=>''];
if ($id) {
  $q = $pdo->prepare("SELECT id, slug, title, cover_path FROM albums WHERE id = :id");
  $q->execute([':id'=>$id]);
  $album = $q->fetch(PDO::FETCH_ASSOC) ?: $album;
}

// Counts (for limit)
try {
  $totalAlbums = (int)$pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn();
} catch (Throwable $e) { $totalAlbums = 0; }
$limitReached = ($mode === 'create' && $totalAlbums >= $ALBUM_LIMIT);

// Helpers
function next_album_slug(PDO $pdo): string {
  $rows = $pdo->query("SELECT slug FROM albums")->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $n = 1;
  do { $slug = sprintf('album%02d', $n++); } while (in_array($slug, $rows, true));
  return $slug;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();

  // Hard-enforce the limit on create
  try {
    $currentCount = (int)$pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn();
  } catch (Throwable $e) { $currentCount = $ALBUM_LIMIT; }
  if ($mode === 'create' && $currentCount >= $ALBUM_LIMIT) {
    $errors[] = "Album limit reached ($ALBUM_LIMIT). Delete one to add a new album.";
  }

  $title = trim($_POST['title'] ?? '');
  $slug  = trim($_POST['slug'] ?? '');

  if ($title === '') $errors[] = 'Title is required.';
  if (!preg_match('/^album\d{2,}$/', $slug)) $errors[] = 'Slug must look like album01, album02…';

  // unique slug
  $q = $pdo->prepare("SELECT id FROM albums WHERE slug = :slug LIMIT 1");
  $q->execute([':slug'=>$slug]);
  $other = $q->fetch(PDO::FETCH_ASSOC);
  if ($other && (int)$other['id'] !== (int)$album['id']) $errors[] = 'Slug already exists.';

  if (!$errors) {
    // Ensure destination folder
    $oldSlug = $album['slug'] ?: null;
    $oldDir  = $oldSlug ? ($UPLOAD_ROOT . '/' . $oldSlug) : null;
    $newDir  = $UPLOAD_ROOT . '/' . $slug;

    if ($oldSlug && $oldSlug !== $slug && is_dir($oldDir)) {
      @rename($oldDir, $newDir);
      // update photos file_path references
      $pdo->prepare("UPDATE photos SET file_path = REPLACE(file_path, :old, :new) WHERE album_id = :id")
          ->execute([':old'=>"$UPLOAD_WEB/$oldSlug/", ':new'=>"$UPLOAD_WEB/$slug/", ':id'=>$album['id']]);
    } elseif (!is_dir($newDir)) {
      @mkdir($newDir, 0775, true);
    }

    // Optional cover upload
    $coverPath = $album['cover_path'] ?? '';
    if (!empty($_FILES['cover']['name']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
      // Clean any old cover.* to avoid stale files
      foreach (glob($newDir.'/cover.*') ?: [] as $old) @unlink($old);
      $dst = $newDir . '/cover.' . $ext;
      if (@move_uploaded_file($_FILES['cover']['tmp_name'], $dst)) {
        $coverPath = "$UPLOAD_WEB/$slug/cover.$ext";
      }
    }

    if ($mode === 'create') {
      $stmt = $pdo->prepare("INSERT INTO albums (slug, title, cover_path, created_at) VALUES (:slug,:title,:cover,NOW())");
      $stmt->execute([':slug'=>$slug, ':title'=>$title, ':cover'=>$coverPath]);
      $id = (int)$pdo->lastInsertId();
    } else {
      $stmt = $pdo->prepare("UPDATE albums SET slug=:slug, title=:title, cover_path=:cover WHERE id=:id");
      $stmt->execute([':slug'=>$slug, ':title'=>$title, ':cover'=>$coverPath, ':id'=>$album['id']]);
      $id = (int)$album['id'];
    }

    header("Location: /admin/album_images.php?id=$id&saved=1"); exit;
  }
}

// defaults for create
if ($mode === 'create' && $album['slug'] === '') {
  $album['slug'] = next_album_slug($pdo);
}
$suggestSlug = next_album_slug($pdo);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $mode==='create'?'Add':'Edit' ?> Album · Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/admin.css">
  <style>
    /* ======= Dark admin tokens (same as list page) ======= */
    :root{
      --bg:#071120; --panel:#0b1420; --panel-2:#0f1a2a;
      --ink:#eaf2ff; --muted:#c7d3e4; --muted-2:#9fb0c8;
      --border:#1f2a3a; --border-2:#2a3243; --chip:#121a26;
      --accent:#ffd166; --danger:#f87171;
    }
    html,body{background:var(--bg); color:var(--ink)}
    .topbar{position:sticky; top:0; z-index:20; background:var(--panel-2); border-bottom:1px solid var(--border); }
    .topbar__inner{max-width:1000px; margin:0 auto; padding:12px 16px; display:flex; align-items:center; gap:10px}
    .title{margin:0; font-weight:900; font-size:1.15rem}
    .btn{display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:12px; text-decoration:none; cursor:pointer; border:1px solid var(--border-2); background:var(--chip); color:var(--ink)}
    .btn:hover{transform:translateY(-1px); background:#162235}
    .btn.primary{background:var(--accent); color:#111; border-color:var(--accent); font-weight:900}
    .btn.primary[disabled]{opacity:.6; cursor:not-allowed; transform:none}
    .btn.ghost{background:transparent}
    .page{max-width:1000px; margin:20px auto 40px; padding:0 16px}
    .card{background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:16px; box-shadow:0 10px 24px rgba(0,0,0,.18)}
    .grid{display:grid; gap:14px}
    @media(min-width:820px){ .grid.two{grid-template-columns:1fr 1fr} }
    label{display:block; margin:0 0 6px; color:var(--muted)}
    .ctl{width:100%; padding:12px 12px; border-radius:12px; background:var(--chip); color:var(--ink); border:1px solid var(--border-2); outline:none}
    .ctl::placeholder{color:var(--muted-2)}
    .help{font-size:.92rem; color:var(--muted); margin-top:6px}
    .errors{border:1px solid rgba(248,113,113,.35); background:rgba(248,113,113,.12); padding:10px 12px; border-radius:12px; color:#fecaca}
    .muted{color:var(--muted)}
    .actions{display:flex; gap:10px; flex-wrap:wrap}
    .preview{display:flex; align-items:flex-start; gap:14px}
    .cover-img{width:180px; height:130px; object-fit:cover; border-radius:12px; border:1px solid var(--border); background:#0a1220}
    .drop{display:block; text-align:center; padding:16px; border:1px dashed var(--border-2); border-radius:12px; cursor:pointer; background:rgba(18,26,38,.35)}
    .limit-note{padding:10px 12px; border-radius:12px; background:#261a00; border:1px solid #3b2a00; color:var(--accent)}
  </style>
</head>
<body>
  <!-- Top bar -->
  <div class="topbar">
    <div class="topbar__inner">
      <a class="btn ghost" href="/admin/albums.php">← Albums</a>
      <h1 class="title"><?= $mode==='create'?'Add':'Edit' ?> Album</h1>
      <span style="margin-left:auto"></span>
      <a class="btn" href="/admin/dashboard.php">Dashboard</a>
    </div>
  </div>

  <div class="page">
    <?php if ($limitReached): ?>
      <p class="limit-note">Album limit reached (<?= $ALBUM_LIMIT ?>). Delete an album to create a new one.</p>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="errors card" style="margin-bottom:14px">
        <strong>We couldn’t save:</strong>
        <ul style="margin:6px 0 0 18px">
          <?php foreach ($errors as $er): ?>
            <li><?= e($er) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form class="card" method="post" enctype="multipart/form-data" <?= $limitReached ? 'aria-disabled="true"' : '' ?>>
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <div class="grid">
        <div>
          <label for="title">Title</label>
          <input class="ctl" id="title" type="text" name="title" required
                 placeholder="e.g., Sunset Beach Shoot" value="<?= e($album['title']) ?>" <?= $limitReached?'disabled':'' ?>>
        </div>

        <div class="grid two">
          <div>
            <label for="slug">Slug</label>
            <div style="display:flex; gap:8px">
              <input class="ctl" id="slug" type="text" name="slug" pattern="^album\d{2,}$" required
                     placeholder="album01" value="<?= e($album['slug']) ?>" <?= $limitReached?'disabled':'' ?>>
              <button class="btn" type="button" id="suggestBtn" title="Suggest next available slug"
                      <?= $limitReached?'disabled':'' ?>>Suggest</button>
            </div>
            <p class="help">Use the pattern <code>album01</code>, <code>album02</code> …</p>
          </div>

          <div>
            <label for="cover">Cover image (optional)</label>
            <label class="drop" for="cover">Click to choose an image (JPG/PNG/WEBP)</label>
            <input class="ctl" id="cover" name="cover" type="file" accept="image/*" style="display:none" <?= $limitReached?'disabled':'' ?>>
            <p class="help">After saving, you can upload up to <strong>10 photos</strong> for this album.</p>
          </div>
        </div>

        <div class="preview">
          <?php if (!empty($album['cover_path'])): ?>
            <img id="coverImg" class="cover-img" src="/<?= e($album['cover_path']) ?>?v=<?= @filemtime(__DIR__.'/../'.$album['cover_path']) ?>" alt="Cover preview">
          <?php else: ?>
            <img id="coverImg" class="cover-img" src="" alt="Cover preview" style="display:none">
          <?php endif; ?>
          <div class="muted">
            <div><strong>Tip:</strong> A horizontal image looks best for a cover.</div>
            <div>Recommended min size: 1200×800px.</div>
          </div>
        </div>

        <div class="actions">
          <button class="btn primary" type="submit" <?= $limitReached?'disabled':'' ?>>
            Save &amp; Manage Images
          </button>
          <a class="btn" href="/admin/albums.php">Cancel</a>
        </div>
      </div>
    </form>
  </div>

  <script>
    // Suggest next slug from server-provided value (or keep current if editing)
    (function(){
      const suggest = "<?= e($suggestSlug) ?>";
      const input = document.getElementById('slug');
      const btn   = document.getElementById('suggestBtn');
      if (btn) btn.addEventListener('click', () => {
        // If empty or invalid, fill; else increment helper
        const m = /^album(\d+)$/.exec(input.value.trim());
        if (m) {
          const n = String(parseInt(m[1], 10) + 1).padStart(Math.max(2, m[1].length), '0');
          input.value = 'album' + n;
        } else {
          input.value = suggest;
        }
        input.focus();
      });
    })();

    // Cover preview
    (function(){
      const file = document.getElementById('cover');
      const img  = document.getElementById('coverImg');
      if (!file) return;
      file.addEventListener('change', () => {
        if (file.files && file.files[0]) {
          const url = URL.createObjectURL(file.files[0]);
          img.src = url;
          img.style.display = 'block';
        }
      });
    })();
  </script>
</body>
</html>
