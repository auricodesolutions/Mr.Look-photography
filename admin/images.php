<?php
// /admin/images.php  — No DB, overwrite files in /assets

session_start();
// if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// ---------- PATHS ----------
$ROOT_ABS   = realpath(__DIR__ . '/../');                         // /project-root
$ASSETS_ABS = $ROOT_ABS . DIRECTORY_SEPARATOR . 'assets';         // /project-root/assets

@mkdir($ASSETS_ABS . '/uploads', 0777, true);    // used earlier; keeping
@mkdir($ASSETS_ABS . '/gallery', 0777, true);    // for gallery.php auto-loads
@mkdir($ASSETS_ABS . '/hero-imgs', 0777, true);  // ensure hero slides dir exists

// ---------- MAPS (STATIC FILES YOU SHOW ON SITE) ----------

// Home — Hero Slides (4 images used on index.php hero)
$HERO_SLIDES = [
  ['slug' => 'hero_1', 'label' => 'Hero Slide 1', 'path' => 'assets/hero-imgs/img01.jpeg'],
  ['slug' => 'hero_2', 'label' => 'Hero Slide 2', 'path' => 'assets/hero-imgs/img02.jpeg'],
  ['slug' => 'hero_3', 'label' => 'Hero Slide 3', 'path' => 'assets/hero-imgs/img03.jpeg'],
  ['slug' => 'hero_4', 'label' => 'Hero Slide 4', 'path' => 'assets/hero-imgs/img04.jpeg'],
];

// Home — Featured Projects (9 cards on index)
$PROJECTS = [
  1 => ['label' => 'Project #1', 'path' => 'assets/img16.jpeg'],
  2 => ['label' => 'Project #2', 'path' => 'assets/Img9.jpg'],
  3 => ['label' => 'Project #3', 'path' => 'assets/img17.jpeg'],
  4 => ['label' => 'Project #4', 'path' => 'assets/img11.jpeg'],
  5 => ['label' => 'Project #5', 'path' => 'assets/img19.jpeg'],
  6 => ['label' => 'Project #6', 'path' => 'assets/img23.jpeg'],
  7 => ['label' => 'Project #7', 'path' => 'assets/img56.jpeg'],
  8 => ['label' => 'Project #8', 'path' => 'assets/img21.jpeg'],
  9 => ['label' => 'Project #9', 'path' => 'assets/img22.jpeg'],
];

// Only keep About image (Legacy Hero + Hero Card removed)
$SLOTS = [
  ['slug' => 'about_image', 'label' => 'About — Avatar', 'path' => 'assets/owner.jpeg'],
];

// Packages (9)
$PACKAGES = [
  ['slug'=>'package_1', 'label'=>'Package 1', 'path'=>'assets/packages/pkg2.jpeg'],
  ['slug'=>'package_2', 'label'=>'Package 2', 'path'=>'assets/packages/pkg3.jpeg'],
  ['slug'=>'package_3', 'label'=>'Package 3', 'path'=>'assets/packages/pkg4.jpeg'],
  ['slug'=>'package_4', 'label'=>'Package 4', 'path'=>'assets/packages/pkg5.jpeg'],
  ['slug'=>'package_5', 'label'=>'Package 5', 'path'=>'assets/packages/pkg6.jpeg'],
  ['slug'=>'package_6', 'label'=>'Package 6', 'path'=>'assets/packages/pkg7.jpeg'],
  ['slug'=>'package_7', 'label'=>'Package 7', 'path'=>'assets/packages/pkg8.jpeg'],
  ['slug'=>'package_8', 'label'=>'Package 8', 'path'=>'assets/packages/pkg9.jpeg'],
  ['slug'=>'package_9', 'label'=>'Package 9', 'path'=>'assets/packages/extra.jpeg'],
];

// Gallery page — curated 24 tiles in gallery.php
$GALLERY_CURATED = [
  ['label'=>'Gallery #1',  'path'=>'assets/img11.jpeg'],
  ['label'=>'Gallery #2',  'path'=>'assets/img13.jpeg'],
  ['label'=>'Gallery #3',  'path'=>'assets/img14.jpeg'],
  ['label'=>'Gallery #4',  'path'=>'assets/img58.jpeg'],
  ['label'=>'Gallery #5',  'path'=>'assets/img16.jpeg'],
  ['label'=>'Gallery #6',  'path'=>'assets/img18.jpeg'],
  ['label'=>'Gallery #7',  'path'=>'assets/img28.jpeg'],
  ['label'=>'Gallery #8',  'path'=>'assets/img25.jpeg'],
  ['label'=>'Gallery #9',  'path'=>'assets/img26.jpeg'],
  ['label'=>'Gallery #10', 'path'=>'assets/img51.jpeg'],
  ['label'=>'Gallery #11', 'path'=>'assets/img33.jpeg'],
  ['label'=>'Gallery #12', 'path'=>'assets/img40.jpeg'],
  ['label'=>'Gallery #13', 'path'=>'assets/img29.jpeg'],
  ['label'=>'Gallery #14', 'path'=>'assets/img30.jpeg'],
  ['label'=>'Gallery #15', 'path'=>'assets/Img8.jpg'],
  ['label'=>'Gallery #16', 'path'=>'assets/img27.jpeg'],
  ['label'=>'Gallery #17', 'path'=>'assets/img34.jpeg'],
  ['label'=>'Gallery #18', 'path'=>'assets/img31.jpeg'],
  ['label'=>'Gallery #19', 'path'=>'assets/img32.jpeg'],
  ['label'=>'Gallery #20', 'path'=>'assets/img38.jpeg'],
  ['label'=>'Gallery #21', 'path'=>'assets/img44.jpeg'],
  ['label'=>'Gallery #22', 'path'=>'assets/img54.jpeg'],
  ['label'=>'Gallery #23', 'path'=>'assets/img61.jpeg'],
  ['label'=>'Gallery #24', 'path'=>'assets/img59.jpeg'],
];

// ---------- HELPERS ----------
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function is_http(string $p): bool { return (bool)preg_match('~^https?://~i', $p); }
function rel_to_abs(string $root, string $rel): string {
  $rel = ltrim($rel, '/');
  $abs = realpath($root . DIRECTORY_SEPARATOR . $rel); // may be false if not exist
  if ($abs === false) $abs = $root . DIRECTORY_SEPARATOR . $rel;
  return $abs;
}
function ensure_in_assets(string $root, string $rel): bool {
  $rel = ltrim($rel, '/');
  if (str_starts_with($rel, 'assets/') === false) return false;
  if (str_contains($rel, '..')) return false;
  $abs = realpath($root) ?: $root;
  $target = realpath($root . DIRECTORY_SEPARATOR . $rel);
  if ($target === false) $target = $root . DIRECTORY_SEPARATOR . $rel;
  return str_starts_with(str_replace('\\','/',$target), str_replace('\\','/',$abs . '/'));
}
function web_from_root(string $rel): string {
  $siteRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); // /Project
  return $siteRoot . '/' . ltrim($rel, '/');
}
function preview_src(string $rootAbs, string $rel): string {
  $abs = rel_to_abs($rootAbs, $rel);
  $q   = is_file($abs) ? (string)filemtime($abs) : (string)time();
  $web = web_from_root($rel);
  return $web . (str_contains($web,'?')?'&':'?') . 'v=' . $q;
}
function overwrite_target_file(string $targetAbs, array $file, string &$msg): bool {
  if ($file['error'] !== UPLOAD_ERR_OK) { $msg = 'Upload error.'; return false; }
  $max = 8*1024*1024; // 8MB
  $allowed = ['jpg','jpeg','png','webp','gif'];
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if ($file['size'] > $max) { $msg = 'File too large (max 8MB).'; return false; }
  if (!in_array($ext, $allowed, true)) { $msg = 'Invalid type. Allowed: JPG, PNG, WEBP, GIF.'; return false; }
  $dir = dirname($targetAbs);
  if (!is_dir($dir)) @mkdir($dir, 0777, true);
  return move_uploaded_file($file['tmp_name'], $targetAbs);
}

// ---------- POST ACTIONS ----------
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'replace_static') {
    $rel = trim($_POST['path'] ?? '');
    if (!$rel || !ensure_in_assets($ROOT_ABS, $rel)) {
      $flash = 'Invalid target path.';
    } elseif (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
      $flash = 'Choose a file.';
    } else {
      $abs = rel_to_abs($ROOT_ABS, $rel);
      if (overwrite_target_file($abs, $_FILES['image'], $flash)) {
        $flash = '✅ Updated ' . e($rel);
      }
    }
  }

  if ($action === 'gallery_add') {
    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
      $name = $_FILES['image']['name'];
      $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $base = 'gallery_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
      $dest = $ASSETS_ABS . '/gallery/' . $base;
      if (overwrite_target_file($dest, $_FILES['image'], $flash)) {
        $flash = '✅ Added to assets/gallery/' . e($base);
      }
    } else {
      $flash = 'Choose an image to upload.';
    }
  }

  if ($action === 'gallery_delete') {
    $file = basename($_POST['file'] ?? '');
    $abs  = $ASSETS_ABS . '/gallery/' . $file;
    if (is_file($abs)) {
      @unlink($abs);
      $flash = '🗑️ Deleted ' . e($file);
    } else {
      $flash = 'File not found.';
    }
  }
}

// ---------- LOAD FILE LISTS ----------
function card_view(string $rootAbs, string $label, string $relPath): array {
  $exists = is_file(rel_to_abs($rootAbs, $relPath));
  return [
    'label'  => $label,
    'path'   => $relPath,
    'exists' => $exists,
    'src'    => preview_src($rootAbs, $relPath),
  ];
}

$heroSlideViews = [];
foreach ($HERO_SLIDES as $s) $heroSlideViews[] = card_view($ROOT_ABS, $s['label'], $s['path']);

$projectViews  = [];
foreach ($PROJECTS as $i=>$row) $projectViews[] = card_view($ROOT_ABS, $row['label']." (#{$i})", $row['path']);

$slotViews     = [];
foreach ($SLOTS as $s) $slotViews[] = card_view($ROOT_ABS, $s['label'], $s['path']);

$packageViews  = [];
foreach ($PACKAGES as $s) $packageViews[] = card_view($ROOT_ABS, $s['label'], $s['path']);

$galleryCuratedViews = [];
foreach ($GALLERY_CURATED as $g) $galleryCuratedViews[] = card_view($ROOT_ABS, $g['label'], $g['path']);

$galleryFiles = glob($ASSETS_ABS . '/gallery/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE) ?: [];
usort($galleryFiles, fn($a,$b)=> (filemtime($b)?:0) <=> (filemtime($a)?:0));
$galleryUploads = array_map(function($abs) use($ROOT_ABS){
  $rel = 'assets/gallery/' . basename($abs);
  return card_view($ROOT_ABS, basename($abs), $rel);
}, $galleryFiles);

// ---------- VIEW ----------
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Images Manager — Admin (No DB)</title>
<style>
:root{--bg:#0b1326;--panel:#0f172a;--text:#e5e7eb;--muted:#9ca3af;--accent:#f59e0b;--accent2:#d97706;--border:#1f2937;--radius:14px}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}

/* layout */
.header{position:sticky;top:0;background:rgba(15,23,42,.9);backdrop-filter:blur(8px);border-bottom:1px solid var(--border);padding:12px 16px;z-index:20}
.header__row{max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:10px}
.pill{display:inline-block;padding:6px 10px;background:#111827;border:1px solid var(--border);border-radius:999px;color:var(--muted);text-decoration:none}
.menu-btn{display:none;align-items:center;gap:8px;border:1px solid var(--border);background:#0b1222;color:var(--text);padding:8px 10px;border-radius:10px;cursor:pointer}
@media (max-width: 992px){ .menu-btn{display:inline-flex} }

.app{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:260px 1fr;gap:16px;padding:18px}
@media (max-width: 992px){
  .app{grid-template-columns:1fr}
}

/* sidebar */
.sidebar{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:12px;position:sticky;top:76px;height:calc(100dvh - 84px);overflow:auto}
.sidebar h3{margin:6px 6px 10px;font-size:1rem;color:#fff}
.navlist{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px}
.navlist a{display:block;text-decoration:none;color:var(--text);padding:10px 12px;border-radius:10px;border:1px solid transparent}
.navlist a:hover{background:#0b1222;border-color:var(--border)}
.navlist a.active{background:#13231c;border-color:#244635;color:#c8ffd7}

@media (max-width: 992px){
  .sidebar{
    position:fixed;inset:64px auto 0 0;width:260px;
    transform:translateX(-102%);transition:transform .25s ease;z-index:30
  }
  body.drawer-open .sidebar{ transform:translateX(0) }
  .drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);z-index:25;opacity:0;visibility:hidden;transition:.2s}
  body.drawer-open .drawer-overlay{opacity:1;visibility:visible}
}

/* cards/content */
h2{margin:18px 0 8px}
.grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;display:flex;flex-direction:column}
.thumb{aspect-ratio:4/3;background:#0b1222;display:grid;place-items:center;overflow:hidden}
.thumb img{width:100%;height:100%;object-fit:cover;display:block}
.body{padding:12px}
input[type="file"]{width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#0b1222;color:var(--text)}
.btn{padding:10px 12px;border:none;border-radius:10px;background:var(--accent);color:#111;font-weight:700;cursor:pointer}
.btn:hover{background:var(--accent2)}
.btn-del{background:#b91c1c;color:#fff}
.section{margin-top:22px}
.flash{margin:12px 0;padding:10px 12px;border-radius:10px;background:#13231c;border:1px solid #244635;color:#c8ffd7}
.badge-miss{display:inline-block;background:#7f1d1d;color:#fff;border-radius:999px;padding:4px 10px;font-size:.8rem}
.rowline{display:grid;gap:8px;grid-template-columns:1fr auto}
.path{color:var(--muted);font-size:.85rem;margin-top:6px}
code{background:#0b1222;border:1px solid var(--border);padding:2px 6px;border-radius:6px}
.help{color:var(--muted);font-size:.9rem}

/* small helper */
.section .subtle{color:var(--muted);font-size:.9rem;margin:6px 0 0}
</style>
</head>
<body>

<header class="header">
  <div class="header__row">
    <button class="menu-btn" id="menuBtn" aria-controls="sidebar" aria-expanded="false">☰ Menu</button>
    <span class="pill">Images Manager (No DB)</span>
    <div style="flex:1"></div>
    <a href="dashboard.php" class="pill">← Back</a>
  </div>
</header>

<div class="drawer-overlay" id="drawerOverlay" hidden></div>

<div class="app">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar" aria-label="Section navigation">
    <h3>Sections</h3>
    <ul class="navlist" id="sideNav">
      <li><a href="#hero-slides">Hero Slides (4)</a></li>
      <li><a href="#about">About Image</a></li>
      <li><a href="#projects">Featured Projects</a></li>
      <li><a href="#packages">Packages</a></li>
      <li><a href="#gallery-curated">Gallery — Curated</a></li>
      <li><a href="#gallery-uploads">Gallery — Uploads</a></li>
      <li><a href="#top">↑ Top</a></li>
    </ul>
  </aside>

  <!-- MAIN CONTENT -->
  <main>
    <?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>

    <!-- HERO SLIDES -->
    <section class="section" id="hero-slides">
      <h2>Home — Hero Slides (4 images)</h2>
      <p class="help">These power the animated hero in <code>index.php</code> (<code>assets/hero-imgs/img01.jpeg</code> … <code>img04.jpeg</code>).</p>
      <div class="grid">
        <?php foreach ($heroSlideViews as $v): ?>
        <div class="card">
          <div class="thumb"><img src="<?= e($v['src']) ?>" alt="<?= e($v['label']) ?>"></div>
          <div class="body">
            <div class="rowline">
              <strong><?= e($v['label']) ?></strong>
              <span><?= $v['exists'] ? 'File exists' : '<span class="badge-miss">Missing</span>' ?></span>
            </div>
            <div class="path"><?= e($v['path']) ?></div>
            <form method="post" enctype="multipart/form-data" style="margin-top:8px">
              <input type="hidden" name="action" value="replace_static">
              <input type="hidden" name="path" value="<?= e($v['path']) ?>">
              <input type="file" name="image" accept="image/*" required>
              <button class="btn" type="submit" style="margin-top:8px">Update</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ABOUT -->
    <section class="section" id="about">
      <h2>About Image</h2>
      <div class="grid">
        <?php foreach ($slotViews as $v): ?>
        <div class="card">
          <div class="thumb"><img src="<?= e($v['src']) ?>" alt="<?= e($v['label']) ?>"></div>
          <div class="body">
            <div class="rowline">
              <strong><?= e($v['label']) ?></strong>
              <span><?= $v['exists'] ? 'File exists' : '<span class="badge-miss">Missing</span>' ?></span>
            </div>
            <div class="path"><?= e($v['path']) ?></div>
            <form method="post" enctype="multipart/form-data" style="margin-top:8px">
              <input type="hidden" name="action" value="replace_static">
              <input type="hidden" name="path" value="<?= e($v['path']) ?>">
              <input type="file" name="image" accept="image/*" required>
              <button class="btn" type="submit" style="margin-top:8px">Update</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- PROJECTS -->
    <section class="section" id="projects">
      <h2>Home — Featured Projects (Static Paths)</h2>
      <p class="help">Uploads overwrite these exact files so your home gallery updates without changing code.</p>
      <div class="grid">
        <?php foreach ($projectViews as $v): ?>
        <div class="card">
          <div class="thumb"><img src="<?= e($v['src']) ?>" alt="<?= e($v['label']) ?>"></div>
          <div class="body">
            <div class="rowline">
              <strong><?= e($v['label']) ?></strong>
              <span><?= $v['exists'] ? 'File exists' : '<span class="badge-miss">Missing</span>' ?></span>
            </div>
            <div class="path"><?= e($v['path']) ?></div>
            <form method="post" enctype="multipart/form-data" style="margin-top:8px">
              <input type="hidden" name="action" value="replace_static">
              <input type="hidden" name="path" value="<?= e($v['path']) ?>">
              <input type="file" name="image" accept="image/*" required>
              <button class="btn" type="submit" style="margin-top:8px">Update</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- PACKAGES -->
    <section class="section" id="packages">
      <h2>Packages</h2>
      <div class="grid">
        <?php foreach ($packageViews as $v): ?>
        <div class="card">
          <div class="thumb"><img src="<?= e($v['src']) ?>" alt="<?= e($v['label']) ?>"></div>
          <div class="body">
            <div class="rowline">
              <strong><?= e($v['label']) ?></strong>
              <span><?= $v['exists'] ? 'File exists' : '<span class="badge-miss">Missing</span>' ?></span>
            </div>
            <div class="path"><?= e($v['path']) ?></div>
            <form method="post" enctype="multipart/form-data" style="margin-top:8px">
              <input type="hidden" name="action" value="replace_static">
              <input type="hidden" name="path" value="<?= e($v['path']) ?>">
              <input type="file" name="image" accept="image/*" required>
              <button class="btn" type="submit" style="margin-top:8px">Update</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- GALLERY CURATED -->
    <section class="section" id="gallery-curated">
      <h2>Gallery — Curated (24 tiles)</h2>
      <p class="help">These are the images hard-coded in <code>gallery.php</code>. Updating here overwrites the file in <code>/assets/…</code> and the gallery page shows the change immediately.</p>
      <div class="grid">
        <?php foreach ($galleryCuratedViews as $v): ?>
        <div class="card">
          <div class="thumb"><img src="<?= e($v['src']) ?>" alt="<?= e($v['label']) ?>"></div>
          <div class="body">
            <div class="rowline">
              <strong><?= e($v['label']) ?></strong>
              <span><?= $v['exists'] ? 'File exists' : '<span class="badge-miss">Missing</span>' ?></span>
            </div>
            <div class="path"><?= e($v['path']) ?></div>
            <form method="post" enctype="multipart/form-data" style="margin-top:8px">
              <input type="hidden" name="action" value="replace_static">
              <input type="hidden" name="path" value="<?= e($v['path']) ?>">
              <input type="file" name="image" accept="image/*" required>
              <button class="btn" type="submit" style="margin-top:8px">Update</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    
  </main>
</div>

<script>
// Drawer toggle (mobile)
const menuBtn = document.getElementById('menuBtn');
const overlay = document.getElementById('drawerOverlay');
function closeDrawer(){ document.body.classList.remove('drawer-open'); menuBtn.setAttribute('aria-expanded','false'); overlay.hidden = true; }
function openDrawer(){ document.body.classList.add('drawer-open'); menuBtn.setAttribute('aria-expanded','true'); overlay.hidden = false; }
menuBtn?.addEventListener('click', () => {
  if (document.body.classList.contains('drawer-open')) closeDrawer(); else openDrawer();
});
overlay?.addEventListener('click', closeDrawer);
window.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeDrawer(); });

// Close drawer when a nav link is clicked
document.querySelectorAll('.sidebar a').forEach(a => a.addEventListener('click', () => {
  if (window.matchMedia('(max-width: 992px)').matches) closeDrawer();
}));

// Scroll spy: highlight active link
const sections = Array.from(document.querySelectorAll('main section[id]'));
const links = new Map(Array.from(document.querySelectorAll('.sidebar a')).map(a => [a.getAttribute('href').replace('#',''), a]));
const spy = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    const id = entry.target.id;
    const link = links.get(id);
    if (!link) return;
    if (entry.isIntersecting) {
      document.querySelectorAll('.sidebar a.active').forEach(x=>x.classList.remove('active'));
      link.classList.add('active');
    }
  });
}, { rootMargin: '-40% 0px -55% 0px', threshold: 0.01 });
sections.forEach(sec => spy.observe(sec));
</script>
</body>
</html>
