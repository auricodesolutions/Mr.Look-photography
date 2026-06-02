<?php
require __DIR__ . '/config.php';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function asset(string $path): string {
  $abs = __DIR__ . '/' . ltrim($path, '/');
  $v   = is_file($abs) ? filemtime($abs) : time();
  return e($path . '?v=' . $v);
}

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') { http_response_code(404); echo 'Album not found'; exit; }

/* 1) Load album meta for hero */
$album = null;
try {
  $q = $pdo->prepare("SELECT id, slug, title, cover_path, created_at FROM albums WHERE slug = :slug LIMIT 1");
  $q->execute([':slug' => $slug]);
  $album = $q->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $album = null; }
if (!$album) { http_response_code(404); echo 'Album not found'; exit; }

/* 2) Resolve album folder (prefer folder of cover, fallback to /assets/uploads/<slug>) */
$albumDirRel = trim(dirname($album['cover_path'] ?? ''), '/');
if ($albumDirRel === '' || !is_dir(__DIR__ . '/' . $albumDirRel)) {
  $try = 'assets/uploads/' . basename($slug);
  if (is_dir(__DIR__ . '/' . $try)) $albumDirRel = $try;
}
$albumDirAbs = __DIR__ . '/' . $albumDirRel;
if ($albumDirRel === '' || !is_dir($albumDirAbs)) { http_response_code(404); echo 'Album folder is missing.'; exit; }

/* 3) Scan photos from folder (exclude cover/hidden/thumbs) */
$glob = glob($albumDirAbs . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
$photos = [];
foreach ($glob as $abs) {
  $base = basename($abs);
  if ($base[0] === '.') continue;
  if (preg_match('/^cover\.(jpe?g|png|webp|gif)$/i', $base)) continue;
  if (preg_match('/(^thumb_|_thumb\.|thumbnail)/i', $base)) continue;
  $photos[] = [
    'file_path' => $albumDirRel . '/' . $base,
    'title'     => pathinfo($base, PATHINFO_FILENAME),
  ];
}
usort($photos, fn($a,$b) => strnatcasecmp($a['file_path'], $b['file_path']));
$photoCount = count($photos);

$canonical = 'https://www.mrlookwedding.com/albums/' . $album['slug'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title><?= e($album['title']) ?> | Mr.Look Weddings</title>
  <meta name="description" content="Album: <?= e($album['title']) ?> — curated by Mr.Look Weddings.">
  <link rel="canonical" href="<?= e($canonical) ?>">

  <!-- Base -->
  <link rel="icon" href="<?= asset('assets/logo.jpeg') ?>">
  <link rel="preload" as="image" href="<?= asset($album['cover_path']) ?>">
  <link rel="stylesheet" href="<?= asset('styles.css') ?>" />
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />

  <!-- OG / Twitter -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= e($album['title']) ?> | Mr.Look Weddings">
  <meta property="og:description" content="Selected moments from <?= e($album['title']) ?>.">
  <meta property="og:image" content="<?= asset($album['cover_path']) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta name="twitter:card" content="summary_large_image">

  <style>
  /* ===== Mr.Look Album — modern, friendly, clean ===== */
  :root{
    --ink:#0f172a; --muted:#64748b; --bg:#ffffff;
    --border:#e5e7eb; --chip:#f8fafc;
    --accent:#f59e0b; --accent-600:#d97706;
    --ring:0 0 0 3px rgba(245,158,11,.25);
  }
  html,body{background:var(--bg); color:var(--ink); -webkit-font-smoothing:antialiased}
  .container{width:min(92%,1200px); margin-inline:auto}

  /* Header */
  .album-header{ position:sticky; top:0; z-index:30;
    background:rgba(255,255,255,.88);
    -webkit-backdrop-filter:saturate(140%) blur(8px); backdrop-filter:saturate(140%) blur(8px);
    border-bottom:1px solid #eef2f7;
  }
  .album-header__inner{ display:flex; align-items:center; gap:10px; padding:10px 0; }
  .album-back{
    display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-weight:800;
    padding:8px 12px; border:1px solid var(--border); border-radius:10px; color:#111; background:#fff;
    transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
  }
  .album-back:hover{ transform:translateY(-1px); box-shadow:0 10px 22px rgba(0,0,0,.06); background:#fffbe8 }
  .album-brand{ margin-left:auto; font-weight:900; letter-spacing:.3px; opacity:.9 }

  /* Hero */
  .album-hero{ position:relative; min-height:46vh; display:grid; place-items:end start; overflow:hidden; isolation:isolate }
  .album-hero img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; filter:grayscale(.04) brightness(.88) }
  .album-hero__overlay{ position:absolute; inset:0;
    background:
      radial-gradient(700px 360px at 12% 70%, rgba(0,0,0,.65) 0%, rgba(0,0,0,.15) 60%, rgba(0,0,0,.55) 90%),
      linear-gradient(180deg, rgba(0,0,0,.45), rgba(0,0,0,.65));
    z-index:1;
  }
  .album-hero__content{ position:relative; z-index:2; padding:44px 0; color:#fff }
  .album-hero__eyebrow{ color:var(--accent); font-weight:800; letter-spacing:.18rem; text-transform:uppercase; opacity:.95 }
  .album-hero__title{ margin:.35rem 0 .4rem; font-size:clamp(2rem,6vw,2.8rem); line-height:1.15; font-weight:800; letter-spacing:.02em }
  .album-hero__sub{ margin:0; opacity:.92; font-weight:600 }
  .album-hero__cta{ display:flex; gap:.6rem; margin-top:1rem }
  .btn{
    display:inline-flex; align-items:center; gap:.55rem; padding:12px 16px; border-radius:12px;
    text-decoration:none; border:1px solid transparent; cursor:pointer; font-weight:800; transition:transform .15s ease, filter .15s ease, box-shadow .15s ease;
  }
  .btn:focus-visible{ outline:none; box-shadow:var(--ring) }
  .btn-primary{ background:linear-gradient(180deg,#ffc24d,var(--accent)); color:#0b0b0b; box-shadow:0 12px 28px rgba(245,158,11,.35) }
  .btn-primary:hover{ transform:translateY(-1px); filter:saturate(1.05) }
  .btn-light{ background:#fff; color:#111; border-color:var(--border) }
  .btn-light:hover{ transform:translateY(-1px) }

  /* Info bar under hero */
  .album-info{ padding:10px 0 0; }
  .chips{ display:flex; gap:8px; flex-wrap:wrap }
  .chip{
    display:inline-flex; align-items:center; gap:.45rem; padding:.5rem .7rem; border-radius:999px;
    background:var(--chip); border:1px solid var(--border); color:#111; font-weight:600; font-size:.92rem;
  }

  /* Masonry grid */
  .album-main{ padding:30px 0 46px; }
  .album-grid{ columns:1; column-gap:16px }
  @media (min-width:640px){ .album-grid{ columns:2 } }
  @media (min-width:980px){ .album-grid{ columns:3 } }
  .album-photo{ break-inside:avoid; margin:0 0 16px; border-radius:14px; overflow:hidden; border:1px solid var(--border); background:#fff; box-shadow:0 14px 30px rgba(0,0,0,.06), inset 0 1px 0 #fff; position:relative }
  .album-photo img{ display:block; width:100%; height:auto; cursor:zoom-in; transition:transform .28s ease, filter .28s ease }
  .album-photo:hover img{ transform:scale(1.015); filter:contrast(1.03) }

  /* Blur-up skeleton */
  .is-loading{ filter:blur(12px) grayscale(.06) brightness(.96); transform:scale(1.01); opacity:.95; transition:filter .6s ease, transform .6s ease, opacity .5s ease }
  .is-loaded{ filter:none; transform:none; opacity:1 }

  /* Lightbox */
  .lightbox{ position:fixed; inset:0; display:none; place-items:center; background:rgba(0,0,0,.90); z-index:10000; padding:22px }
  .lightbox.open{ display:grid }
  .lightbox-img{ max-width:min(92vw,1080px); max-height:80vh; border-radius:14px; background:#000; box-shadow:0 14px 44px rgba(0,0,0,.45) }
  .lightbox-cap{ margin-top:.75rem; color:#ddd; text-align:center }
  .light-btn{ position:absolute; top:50%; transform:translateY(-50%); width:46px; height:46px; display:grid; place-items:center;
    border-radius:50%; border:1px solid rgba(255,255,255,.22); background:rgba(0,0,0,.75); color:#fff; font-size:22px; cursor:pointer }
  .light-prev{ left:22px } .light-next{ right:22px }
  .light-close{ top:22px; right:22px; transform:none }
  .kbd{ margin-left:.35rem; font:inherit; font-size:.82em; padding:.1rem .45rem; border-radius:6px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25) }

  /* Footer comeback */
  .return{ text-align:center; padding:18px 0 36px; }
  .return .btn-light{ padding:10px 14px; }
  </style>
</head>
<body>

  <!-- Header -->
  <header class="album-header">
    <div class="container album-header__inner">
      <a class="album-back" href="/albums_list.php"><i class="ri-arrow-left-line"></i> Back to Albums</a>
      <span class="album-brand">Mr.Look Weddings</span>
    </div>
  </header>

  <!-- Hero -->
  <section class="album-hero" aria-label="Album cover">
    <img class="is-loading" src="<?= asset($album['cover_path']) ?>" alt="<?= e($album['title']) ?>">
    <div class="album-hero__overlay"></div>
    <div class="album-hero__content container">
      <div class="album-hero__eyebrow">Featured Album</div>
      <h1 class="album-hero__title"><?= e($album['title']) ?></h1>
      <p class="album-hero__sub">
        <?= date('M j, Y', strtotime($album['created_at'])) ?> · <?= (int)$photoCount ?> photo<?= $photoCount===1?'':'s' ?>
      </p>
      <div class="album-hero__cta">
        <?php if ($photoCount): ?>
          <a class="btn btn-primary" id="startShow"><i class="ri-play-fill"></i> Start Slideshow</a>
        <?php endif; ?>
        <a class="btn btn-light" href="/albums_list.php#top"><i class="ri-grid-fill"></i> View all albums</a>
      </div>

     
    </div>
  </section>

  <!-- Gallery (scanned from folder) -->
  <main class="album-main container">
    <?php if ($photos): ?>
      <div class="album-grid" id="albumGrid">
        <?php foreach ($photos as $p): ?>
          <figure class="album-photo">
            <img
              src="<?= asset($p['file_path']) ?>"
              alt="<?= e($p['title'] ?: $album['title']) ?>"
              loading="lazy" decoding="async"
              data-cap="<?= e($p['title'] ?: $album['title']) ?>"
              sizes="(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw"
              class="is-loading">
          </figure>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="opacity:.8">No photos were found in this album’s folder.</p>
    <?php endif; ?>
  </main>

  <!-- Return CTA -->
  <div class="return">
    <a class="btn btn-light" href="/albums_list.php"><i class="ri-arrow-left-line"></i> Back to Albums</a>
  </div>

  <!-- Lightbox -->
  <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Image preview">
    <button class="light-btn light-close" aria-label="Close preview">×</button>
    <button class="light-btn light-prev"  aria-label="Previous">‹</button>
    <img class="lightbox-img" alt="Preview">
    <button class="light-btn light-next"  aria-label="Next">›</button>
    <p class="lightbox-cap">
      Tip: use <span class="kbd">←</span> <span class="kbd">→</span> and <span class="kbd">Esc</span>
    </p>
  </div>

  <script>
  // Blur-up skeleton removal
  (function(){
    const imgs = document.querySelectorAll('img.is-loading');
    imgs.forEach(img=>{
      const done = () => img.classList.remove('is-loading'), // 'is-loaded' is purely visual in CSS; removing is-loading is enough
            err  = () => img.classList.remove('is-loading');
      if (img.complete && img.naturalWidth > 0) requestAnimationFrame(done);
      else { img.addEventListener('load', done, {once:true}); img.addEventListener('error', err, {once:true}); }
    });
  })();

  // Lightbox + slideshow
  (function(){
    const grid = document.getElementById('albumGrid');
    const box  = document.getElementById('lightbox');
    if(!grid || !box) return;

    const imgEl = box.querySelector('.lightbox-img');
    const capEl = box.querySelector('.lightbox-cap');
    const prev  = box.querySelector('.light-prev');
    const next  = box.querySelector('.light-next');
    const close = box.querySelector('.light-close');
    const start = document.getElementById('startShow');

    const thumbs = Array.from(grid.querySelectorAll('img'));
    let idx = -1;

    function show(i){
      if(!thumbs.length) return;
      if(i < 0) i = thumbs.length - 1;
      if(i >= thumbs.length) i = 0;
      idx = i;
      const t = thumbs[idx];
      imgEl.src = t.currentSrc || t.src;
      imgEl.alt = t.alt || '';
      capEl.firstChild && (capEl.firstChild.textContent = (t.dataset.cap || '') + ' ');
      box.classList.add('open');
    }
    thumbs.forEach((t,i)=>t.addEventListener('click', ()=>show(i)));
    prev.addEventListener('click', ()=>show(idx-1));
    next.addEventListener('click', ()=>show(idx+1));
    close.addEventListener('click', ()=>box.classList.remove('open'));
    box.addEventListener('click', (e)=>{ if(e.target === box) box.classList.remove('open'); });
    window.addEventListener('keydown', (e)=>{
      if(!box.classList.contains('open')) return;
      if(e.key==='Escape') box.classList.remove('open');
      if(e.key==='ArrowLeft') show(idx-1);
      if(e.key==='ArrowRight') show(idx+1);
    });
    start?.addEventListener('click', ()=>show(0));
  })();
  </script>
</body>
</html>
