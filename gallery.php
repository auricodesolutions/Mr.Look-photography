<?php
// helper: return a cache-busted URL for local assets
function asset(string $path): string {
  $abs = __DIR__ . '/' . ltrim($path, '/');
  $v   = is_file($abs) ? filemtime($abs) : time();
  return htmlspecialchars($path . '?v=' . $v, ENT_QUOTES, 'UTF-8');
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Your original curated tiles
$defaults = [
  ['src' => 'assets/img11.jpeg', 'alt' => 'First look'],
  ['src' => 'assets/img13.jpeg', 'alt' => 'Portrait by window'],
  ['src' => 'assets/img14.jpeg', 'alt' => 'Night street'],
  ['src' => 'assets/img58.jpeg', 'alt' => 'Ring detail'],
  ['src' => 'assets/img16.jpeg', 'alt' => 'Portrait with blinds'],
  ['src' => 'assets/img18.jpeg', 'alt' => 'Colombo night'],
  ['src' => 'assets/img28.jpeg', 'alt' => 'Sri Lanka dawn'],
  ['src' => 'assets/img25.jpeg', 'alt' => 'Tea hills'],
  ['src' => 'assets/img26.jpeg', 'alt' => 'Couple in city'],
  ['src' => 'assets/img51.jpeg', 'alt' => 'Golden beach'],
  ['src' => 'assets/img33.jpeg', 'alt' => 'Silhouette'],
  ['src' => 'assets/img40.jpeg', 'alt' => 'Bouquet'],
  ['src' => 'assets/img29.jpeg', 'alt' => 'Sri Lanka dawn'],
  ['src' => 'assets/img30.jpeg', 'alt' => 'Tea hills'],
  ['src' => 'assets/Img8.jpg',  'alt' => 'Couple in city'],
  ['src' => 'assets/img27.jpeg', 'alt' => 'Golden beach'],
  ['src' => 'assets/img34.jpeg', 'alt' => 'Silhouette'],
  ['src' => 'assets/img31.jpeg', 'alt' => 'Bouquet'],
  ['src' => 'assets/img32.jpeg', 'alt' => 'Tea hills'],
  ['src' => 'assets/img38.jpeg', 'alt' => 'Couple in city'],
  ['src' => 'assets/img44.jpeg', 'alt' => 'Golden beach'],
  ['src' => 'assets/img54.jpeg', 'alt' => 'Silhouette'],
  ['src' => 'assets/img61.jpeg', 'alt' => 'Bouquet'],
  ['src' => 'assets/img59.jpeg', 'alt' => 'Tea hills'],
];

// Auto-load any files from assets/gallery (no DB)
$uploads = [];
$galleryAbs = __DIR__ . '/assets/gallery';
if (is_dir($galleryAbs)) {
  $files = glob($galleryAbs . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
  usort($files, fn($a,$b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0)); // newest first
  foreach ($files as $abs) {
    $uploads[] = [
      'src' => 'assets/gallery/' . basename($abs),
      'alt' => pathinfo($abs, PATHINFO_FILENAME),
    ];
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mr.Look-Gallery</title>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet" />

  <!--
    Gallery — Light Theme
    Palette: White, Black, Yellow (#f59e0b), Greys
  -->
  <style>
  :root{
    /* Light palette */
    --white:#fff;
    --black:#000;
    --yellow:#f59e0b;

    /* greys */
    --gray-50:#fafafa;
    --gray-100:#f5f5f5;
    --gray-200:#e5e7eb;
    --gray-300:#d1d5db;
    --gray-500:#6b7280;

    /* tokens */
    --bg:var(--white);
    --text:rgba(254, 254, 254, 0.92);
    --muted:var(--gray-500);
    --accent:var(--yellow);
    --accent-600:#d97706;                 /* deeper yellow for hover */
    --tile:#ffffff;                       /* card/tile bg */
    --border:var(--gray-200);
    --container:1200px;
    --radius:16px;
    --shadow:0 20px 60px rgba(0,0,0,.06);
    --ring:0 0 0 3px rgba(245,158,11,.35);
  }

  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{
    margin:0; background:var(--bg); color:var(--text);
    font:15px/1.6 system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif;
  }
  img{max-width:100%; display:block}
  a{color:inherit; text-decoration:none}

  /* Sticky header with soft white glass */
  .sitebar{
    position:sticky; top:0; z-index:10;
    background:linear-gradient(to bottom, rgba(0, 0, 0, 0.92), rgba(0, 0, 0, 0.72));
    -webkit-backdrop-filter:saturate(130%) blur(8px);
    backdrop-filter:saturate(130%) blur(8px);
    border-bottom:1px solid var(--border);
  }
  .site{
    width:min(var(--container),96%); margin-inline:auto;
    padding:12px 0; display:grid; align-items:center; gap:12px;
    grid-template-columns: 1fr auto 1fr; /* left btn, center title, right spacer */
  }
  .site h1{
    margin:0; color:var(--text);
    font-size:clamp(20px,2.2vw,26px);
    letter-spacing:.08em; text-transform:uppercase; font-weight:800; text-align:center;
  }
  .spacer{justify-self:end}
  .back{
    justify-self:start; display:inline-flex; align-items:center; gap:.45rem;
    padding:.5rem .85rem; border-radius:12px; font-weight:700;
    border:1px solid var(--border); background:transparent; color:var(--text);
    transition: background-color .2s ease, border-color .2s ease, transform .1s ease, color .2s ease;
  }
  .back i{font-size:18px; line-height:0}
  .back:hover{
    background:linear-gradient(135deg, var(--accent), var(--accent-600));
    color:#111; border-color:transparent; transform:translateY(-1px)
  }
  .back:focus-visible{ outline:none; box-shadow:var(--ring) }

  /* Grid — even squares */
  .wrap{width:min(var(--container),96%); margin:18px auto 56px}
  .grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
    gap:12px;
  }
  @media (max-width:520px){ .grid{grid-template-columns:repeat(2,1fr);} }

  .tile{
    position:relative; aspect-ratio:1/1; overflow:hidden;
    border-radius:var(--radius);
    background:var(--tile);
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    opacity:0; transform:translateY(6px);
    transition:opacity .35s ease, transform .35s ease, box-shadow .25s ease, border-color .25s ease;
  }
  .tile img{
    width:100%; height:100%; object-fit:cover;
    transition:transform .30s ease;
  }
  .tile:hover img{ transform:scale(1.03); }
  .tile:hover{ box-shadow:var(--shadow), var(--ring); border-color:var(--gray-300); }
  .tile:focus-visible{ outline:none; box-shadow:var(--shadow), var(--ring); }

  /* Fade-in on reveal */
  .tile.revealed{opacity:1; transform:none}

  /* Footer */
  .foot{
    padding:36px 0 56px;
    background:var(--black);
    color:var(--muted);
    text-align:center;
    border-top:1px solid var(--border);
  }
</style>

</head>
<body>
  <div class="sitebar">
    <div class="site">
      <a class="back" href="index.php" aria-label="Back to Home"><i class="ri-arrow-left-line" aria-hidden="true"></i><span>Home</span></a>
      <h1>MR.LOOK — Gallery</h1>
      <div class="spacer"></div>
    </div>
  </div>

  <main class="wrap">
    <section class="grid" id="grid">
      <!-- curated set -->
      <?php foreach ($defaults as $d): ?>
        <a class="tile" href="#">
          <img src="<?= asset($d['src']) ?>" alt="<?= e($d['alt']) ?>" loading="lazy">
        </a>
      <?php endforeach; ?>

      <!-- auto-loaded uploads from assets/gallery -->
      <?php foreach ($uploads as $u): ?>
        <a class="tile" href="#">
          <img src="<?= asset($u['src']) ?>" alt="<?= e($u['alt']) ?>" loading="lazy">
        </a>
      <?php endforeach; ?>
    </section>
  </main>

  <footer class="foot">© <span id="year"></span> Mr.Look — All rights reserved.</footer>

  <script>
    document.getElementById('year').textContent = new Date().getFullYear();

    // simple reveal
    const io = new IntersectionObserver((entries)=>{
      entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('revealed'); io.unobserve(e.target); } });
    }, {threshold:.15});
    document.querySelectorAll('.tile').forEach(el=> io.observe(el));
  </script>
</body>
</html>
