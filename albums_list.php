<?php
require __DIR__ . '/config.php';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function asset(string $path): string {
  $abs = __DIR__ . '/' . ltrim($path, '/');
  $v   = is_file($abs) ? filemtime($abs) : time();
  return $path . '?v=' . $v;
}

/* ---- Inputs ---- */
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$q       = trim($_GET['q'] ?? '');
$sort    = $_GET['sort'] ?? 'new';  // new | old | az | za

$sortMap = [
  'new' => 'created_at DESC, id DESC',
  'old' => 'created_at ASC, id ASC',
  'az'  => 'title ASC, id ASC',
  'za'  => 'title DESC, id DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['new'];
$offset  = ($page - 1) * $perPage;

/* Small helper to preserve query params in links */
function qs(array $overrides = []): string {
  $keep = $_GET;
  foreach ($overrides as $k => $v) {
    if ($v === null) unset($keep[$k]);
    else $keep[$k] = $v;
  }
  $q = http_build_query($keep);
  return $q ? ('?'.$q) : '';
}

/* ---- Count + fetch ---- */
$where = '';
$params = [];
if ($q !== '') {
  $where = "WHERE title LIKE :q";
  $params[':q'] = '%'.$q.'%';
}

$total = 0;
try {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM albums $where");
  foreach ($params as $k => $v) $stmt->bindValue($k, $v);
  $stmt->execute();
  $total = (int)$stmt->fetchColumn();
} catch (Throwable $e) { $total = 0; }

$albums = [];
try {
  $sql = "SELECT id, slug, title, cover_path, created_at
          FROM albums
          $where
          ORDER BY $orderBy
          LIMIT :lim OFFSET :off";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) $stmt->bindValue($k, $v);
  $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
  $stmt->execute();
  $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $albums = []; }

$pages = max(1, (int)ceil($total / $perPage));

/* Canonical + prev/next for SEO */
$baseUrl   = 'https://www.mrlookwedding.com/albums.php';
$canonQS   = $_GET; unset($canonQS['page']); // canonical has no page param unless >1
$canonical = $baseUrl . (!empty($canonQS) ? ('?'.http_build_query($canonQS)) : '');
if ($page > 1) $canonical .= (strpos($canonical, '?') === false ? '?' : '&') . 'page='.$page;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Albums | Mr.Look Weddings</title>
  <meta name="description" content="Browse our curated collection of photo albums.">
  <link rel="canonical" href="<?= e($canonical) ?>">
  <?php if ($page > 1): ?><link rel="prev" href="<?= e($baseUrl . qs(['page' => $page - 1])) ?>"><?php endif; ?>
  <?php if ($page < $pages): ?><link rel="next" href="<?= e($baseUrl . qs(['page' => $page + 1])) ?>"><?php endif; ?>

  <link rel="icon" href="<?= asset('assets/logo.jpeg') ?>">
  <link rel="stylesheet" href="<?= asset('styles.css') ?>">

  <style>
    /* ===== Scoped styles for Albums page ===== */
    :root{ --ink:#0f172a; --muted:#64748b; --yellow:#f59e0b; --border:#e5e7eb; }

    .albums-shell{ background:#fff; color:#111; min-height:100vh; }

    /* Sticky utility bar */
    .albums-bar{
      position:sticky; top:0; z-index:30;
      background:rgba(255,255,255,.95);
      backdrop-filter:saturate(140%) blur(6px);
      border-bottom:1px solid var(--border);
    }
    .albums-bar__inner{
      width:min(1200px,92%); margin:0 auto; padding:10px 0;
      display:grid; grid-template-columns:auto 1fr auto; gap:12px; align-items:center;
    }
    .back-btn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 14px; border:1px solid var(--border); border-radius:12px;
      background:#fff; color:#111; text-decoration:none; font-weight:800;
      box-shadow:0 10px 20px rgba(0,0,0,.06);
    }
    .back-btn:hover{ transform:translateY(-1px); background:#ffe8b3; border-color:#ffd166; }

    .albums-title{ margin:0; font-weight:900; color:var(--ink); font-size:clamp(1.2rem,3.5vw,1.6rem); }
    .bar-right{ display:flex; gap:10px; align-items:center; }

    .search{
      display:flex; align-items:center; gap:8px;
      padding:8px 10px; border:1px solid var(--border); border-radius:10px; background:#fff;
    }
    .search input{
      border:none; outline:none; width:220px; max-width:50vw; font-size:14px;
    }
    .sort{
      padding:8px 10px; border:1px solid var(--border); border-radius:10px;
      background:#fff; font-size:14px;
    }

    .albums-page{ padding:22px 0 32px; }
    .albums-head{ width:min(1200px,92%); margin:0 auto 16px; color:var(--muted); }
    .albums-head .meta{ font-size:.95rem; }

    .albums-grid{
      width:min(1200px,92%); margin:0 auto;
      display:grid; gap:18px; grid-template-columns: repeat(1, minmax(0,1fr));
    }
    @media (min-width:700px){ .albums-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); } }
    @media (min-width:1040px){ .albums-grid{ grid-template-columns: repeat(3, minmax(0,1fr)); } }

    .card{
      border:1px solid var(--border); border-radius:16px; overflow:hidden; background:#fff;
      box-shadow:0 14px 36px rgba(15,23,42,.08), inset 0 1px 0 #fff;
      transition:transform .2s ease, box-shadow .2s ease;
    }
    .card:hover{ transform:translateY(-2px); box-shadow:0 18px 46px rgba(15,23,42,.12); }
    .card a{ display:block; text-decoration:none; color:inherit; }

    .media{ position:relative; aspect-ratio: 4/3; overflow:hidden; }
    .media img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; transition:transform .5s ease; }
    .card:hover .media img{ transform:scale(1.06); }

    .media .overlay{
      position:absolute; inset:0; display:grid; place-items:end; padding:10px;
      background:linear-gradient(180deg, rgba(0,0,0,0) 40%, rgba(0,0,0,.55) 100%);
      color:#fff; opacity:0; transition:opacity .25s ease;
    }
    .card:hover .media .overlay{ opacity:1; }
    .open-btn{
      display:inline-block; padding:10px 12px; background:#fff; color:#111; border-radius:10px; font-weight:800;
      text-decoration:none; border:1px solid #ddd;
    }

    .card h3{ margin:12px 14px 6px; font-size:18px; font-weight:900; color:var(--ink); }
    .date{ margin:0 14px 14px; color:var(--muted); font-size:.92rem; }

    .pager{
      width:min(1200px,92%); margin:20px auto 0;
      display:flex; flex-wrap:wrap; gap:8px; justify-content:center;
    }
    .pager a, .pager span{
      padding:10px 14px; border:1px solid var(--border); border-radius:10px; text-decoration:none; color:#111;
      background:#fff;
    }
    .pager .active{ background:var(--yellow); border-color:var(--yellow); color:#111; font-weight:800; }

    .bottom-actions{ width:min(1200px,92%); margin:24px auto 8px; display:flex; justify-content:center; }
    .home-big{
      display:inline-flex; align-items:center; gap:8px;
      padding:12px 18px; border-radius:12px; text-decoration:none; font-weight:900;
      background:linear-gradient(180deg, #ffb703, #f59e0b); color:#0b0b0b; border:1px solid rgba(0,0,0,.12);
      box-shadow:0 12px 28px rgba(244,163,7,.35);
    }
    .home-big:hover{ transform:translateY(-1px); filter:saturate(1.05); }
  </style>
</head>
<body class="albums-shell">

  <!-- Sticky top bar -->
  <div class="albums-bar">
    <div class="albums-bar__inner">
      <a class="back-btn" href="/#albums" aria-label="Back to Home">← Home</a>
      <h1 class="albums-title">All Albums</h1>
      
    </div>
  </div>

  <section class="albums-page">
    <div class="albums-head">
      <p class="meta">
        <?= $total ? "Showing <strong>".count($albums)."</strong> of <strong>$total</strong> album(s)" : "No albums found"; ?>
        <?php if ($q !== ''): ?> for query “<strong><?= e($q) ?></strong>”<?php endif; ?>
        <?php if ($pages > 1): ?> — Page <?= $page ?> of <?= $pages ?><?php endif; ?>
      </p>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="pager" aria-label="Pagination (top)">
        <?php if ($page > 1): ?>
          <a href="<?= e(qs(['page' => $page-1])) ?>">‹ Prev</a>
        <?php endif; ?>
        <?php for ($i=1; $i<=$pages; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="active"><?= $i ?></span>
          <?php else: ?>
            <a href="<?= e(qs(['page' => $i])) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <a href="<?= e(qs(['page' => $page+1])) ?>">Next ›</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

    <div class="albums-grid">
      <?php foreach ($albums as $a): ?>
        <article class="card">
          <a href="/album.php?slug=<?= e($a['slug']) ?>">
            <div class="media">
              <?php if (!empty($a['cover_path'])): ?>
                <img src="/<?= e(asset($a['cover_path'])) ?>" alt="<?= e($a['title']) ?>" loading="lazy" decoding="async">
              <?php else: ?>
                <img src="/<?= e(asset('assets/placeholder.jpg')) ?>" alt="<?= e($a['title']) ?>" loading="lazy" decoding="async">
              <?php endif; ?>
              <div class="overlay"><span class="open-btn">Open album</span></div>
            </div>
            <h3><?= e($a['title']) ?></h3>
            <?php if (!empty($a['created_at'])): ?>
              <p class="date"><?= date('M j, Y', strtotime($a['created_at'])) ?></p>
            <?php endif; ?>
          </a>
        </article>
      <?php endforeach; ?>

      <?php if (!$albums): ?>
        <p style="grid-column:1/-1;text-align:center;opacity:.8">
          No albums to display. <a href="/#albums">Go back to Home</a>.
        </p>
      <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="pager" aria-label="Pagination (bottom)">
        <?php if ($page > 1): ?>
          <a href="<?= e(qs(['page' => $page-1])) ?>">‹ Prev</a>
        <?php endif; ?>
        <?php for ($i=1; $i<=$pages; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="active"><?= $i ?></span>
          <?php else: ?>
            <a href="<?= e(qs(['page' => $i])) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <a href="<?= e(qs(['page' => $page+1])) ?>">Next ›</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

    <div class="bottom-actions">
      <a class="home-big" href="/#albums">← Back to Home</a>
    </div>
  </section>
</body>
</html>
