<?php
// DB (needed for the reviews section)
require __DIR__ . '/config.php';

/* -------------------- helpers -------------------- */
// Escape helper
function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Local asset helper with cache-buster
function asset(string $path): string {
  $abs = __DIR__ . '/' . ltrim($path, '/');
  $v   = is_file($abs) ? filemtime($abs) : time();
  return e($path . '?v=' . $v);
}

// Stars helper for reviews (optional)
function stars(int $n): string {
  $n = max(1, min(5, $n));
  return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}

/**
 * picture() — emit <picture> with AVIF/WEBP + <img> fallback.
 */
function picture(string $path, string $alt, array $attrs = []): string {
  $abs = __DIR__ . '/' . ltrim($path, '/');
  $root = __DIR__ . '/';
  $avif = preg_replace('/\.(jpe?g|png)$/i', '.avif', $path);
  $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

  $sources = [];
  if ($avif && is_file($root . ltrim($avif, '/'))) { $sources[] = ['type' => 'image/avif', 'src' => $avif]; }
  if ($webp && is_file($root . ltrim($webp, '/'))) { $sources[] = ['type' => 'image/webp', 'src' => $webp]; }

  // sensible defaults
  $defaults = ['loading' => 'lazy', 'decoding' => 'async'];
  if (!empty($attrs['fetchpriority']) && strtolower((string)$attrs['fetchpriority']) === 'high') {
    $defaults['loading'] = 'eager'; // LCP image
  }
  $attrs = array_merge($defaults, $attrs);

  // Build attribute string
  $attrStr = '';
  foreach ($attrs as $k => $v) {
    if ($v === null || $v === false) continue;
    $attrStr .= ' ' . e($k) . '="' . e((string)$v) . '"';
  }

  ob_start(); ?>
  <picture>
    <?php foreach ($sources as $s): ?>
      <source type="<?= e($s['type']) ?>" srcset="<?= asset($s['src']) ?>">
    <?php endforeach; ?>
    <img src="<?= asset($path) ?>" alt="<?= e($alt) ?>"<?= $attrStr ?>>
  </picture>
  <?php
  return ob_get_clean();
}

/* -------------------- data fetch -------------------- */
// Albums for carousel (latest 10)
$albums = [];
try {
  $stmt = $pdo->query(
    "SELECT slug, title, cover_path
     FROM albums
     ORDER BY created_at DESC
     LIMIT 10"
  );
  $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $albums = []; }

// Reviews for slider
$reviews = [];
try {
  $stmt = $pdo->query(
    "SELECT customer_name, rating, review, review_date
     FROM reviews
     ORDER BY id DESC
     LIMIT 12"
  );
  $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* leave empty */ }

/* -------------------- canonical + redirect -------------------- */
$BASE_URL   = 'https://www.mrlookwedding.com';
$HOME_SLUG  = '/wedding-photography-srilanka-colombo';
$reqPath    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// If someone lands on "/", move them permanently to the preferred home path.
if ($reqPath === '/' || $reqPath === '') {
  header('Location: ' . $BASE_URL . $HOME_SLUG, true, 301);
  exit;
}

// Canonical for this home page (served at the preferred slug)
$canonical = $BASE_URL . $HOME_SLUG;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Primary SEO -->
  <title>Best Wedding Photography Packages, Sri Lanka | Mr.Look – Colombo</title>
  <meta name="description" content="Explore the best wedding photography packages in Sri Lanka. Mr.Look offers cinematic, natural, and elegant photography in Colombo and islandwide — capturing your story beautifully. Book today for professional coverage and fast delivery.">
  <meta name="robots" content="index,follow">
  <meta name="theme-color" content="#000000">

  <!-- Canonical -->
  <link rel="canonical" href="<?= e($canonical) ?>">

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;600;700&display=swap">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />

  <!-- Open Graph -->
  <meta property="og:site_name" content="Mr.Look Wedding Photography">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="en_LK">
  <meta property="og:title" content="Best Wedding Photography Packages, Sri Lanka | Mr.Look – Colombo">
  <meta property="og:description" content="Cinematic wedding & engagement photography across Sri Lanka. Mr.Look captures timeless love stories with elegant, emotional storytelling and professional service.">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= asset('assets/og-cover.jpg') ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Best Wedding Photography Packages, Sri Lanka | Mr.Look – Colombo">
  <meta name="twitter:description" content="Professional wedding & engagement photography in Colombo and across Sri Lanka. Artistic, cinematic, and natural storytelling that turns every moment into a memory.">
  <meta name="twitter:image" content="<?= asset('assets/og-cover.jpg') ?>">

  <!-- Performance: Preload LCP hero image -->
  <link rel="preload" as="image"
        href="<?= asset('assets/hero-imgs/img01.jpeg') ?>"
        imagesrcset="<?= asset('assets/hero-imgs/img01.avif') ?> 1x, <?= asset('assets/hero-imgs/img01.jpeg') ?> 2x"
        imagesizes="100vw" fetchpriority="high">

  <!-- Bootstrap (optional utilities) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Favicon & CSS -->
  <link rel="icon" href="<?= asset('assets/logo.png') ?>">
  <link rel="stylesheet" href="<?= asset('styles.css') ?>" />

  <!-- Minimal preloader + blur-up styles + fold-aware rendering -->
  <style>
    :root{
      --pl-bg:#0b0b0c;        /* preloader bg */
      --pl-ring:#ffffff25;    /* outer ring */
      --pl-accent:#fff;       /* spinning arc */
      --pl-min:650ms;         /* minimum time to show preloader */
    }

    /* Reduce offscreen work */
    .masonry,
    .albums-carousel__container,
    .about-compact,
    .faq-block,
    .section-sep { content-visibility:auto; contain-intrinsic-size: 1000px; }

    /* full-screen overlay */
    .preloader{
      position:fixed; inset:0;
      display:grid; place-items:center;
      background:var(--pl-bg);
      z-index:9999;
      transition:opacity .45s ease, visibility .45s ease;
    }
    .preloader.is-hidden{ opacity:0; visibility:hidden; }

    .pl-wrap{
      position:relative; width:172px; height:172px;
      display:grid; place-items:center; perspective:1100px;
    }
    .pl-logo{
      width:84px; height:84px; border-radius:18px; overflow:hidden;
      display:grid; place-items:center; background:#ffffff08;
      box-shadow:0 6px 30px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.08);
      animation: logoFloat 2.4s ease-in-out infinite;
      transform-style: preserve-3d;
    }
    .pl-logo img{ width:72px; height:72px; object-fit:contain; filter: drop-shadow(0 2px 8px rgba(0,0,0,.25)); }
    .pl-logo-rotate{ animation: yawSwing 1.35s cubic-bezier(.4,0,.2,1) infinite alternate; transform-origin:50% 50%; transform: rotateY(-88deg); backface-visibility:hidden; -webkit-backface-visibility:hidden; }
    .pl-ring{ position:absolute; inset:0; }
    .pl-ring svg{ width:100%; height:100%; transform: rotate(-90deg); }
    .pl-ring .track{ stroke:var(--pl-ring); }
    .pl-ring .arc{ stroke:var(--pl-accent); stroke-linecap:round; stroke-dasharray:440; stroke-dashoffset:330; animation: dash 1.8s ease-in-out infinite; filter: drop-shadow(0 0 12px rgba(255,255,255,.35)); }

    @keyframes dash{ 0%{stroke-dashoffset:440} 50%{stroke-dashoffset:160} 100%{stroke-dashoffset:440} }
    @keyframes logoFloat{ 0%,100%{ transform: translateY(0) } 50%{ transform: translateY(-6px) } }
    @keyframes yawSwing{ 0%{ transform: rotateY(-88deg); opacity:.92 } 50%{ transform: rotateY(0deg); opacity:1 } 100%{ transform: rotateY(88deg); opacity:.92 } }

    /* Smooth (blur-up) image loading */
    img.is-loading{
      filter: blur(14px) saturate(1.05);
      transform: scale(1.02);
      transition: filter .5s ease, transform .5s ease, opacity .5s ease;
      opacity:.75;
    }
    img.is-loaded{ filter:none; transform:none; opacity:1; }

    @media (prefers-reduced-motion: reduce){
      .pl-logo, .pl-logo-rotate, .pl-ring .arc{ animation: none !important; }
    }
    noscript .preloader{ display:none; }
  </style>

  <!-- LocalBusiness JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "@id": "<?= e($canonical) ?>#business",
    "name": "Mr.Look Wedding Photography",
    "url": "<?= e($canonical) ?>",
    "image": ["<?= asset('assets/og-cover.jpg') ?>"],
    "telephone": "+94 77 6767 279",
    "email": "mr.lookphotographer@gmail.com",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "372, Horana Road, Mahabellana",
      "addressLocality": "Panadura",
      "postalCode": "12524",
      "addressCountry": "LK"
    },
    "areaServed": "Sri Lanka",
    "openingHoursSpecification": [{
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],
      "opens": "09:00",
      "closes": "18:00"
    }],
    "sameAs": [
      "https://www.instagram.com/mr_look_weddding/",
      "https://www.facebook.com/profile.php?id=61565476093095",
      "https://tiktok.com/@yourname"
    ],
    "makesOffer": [
      {"@type": "Offer","itemOffered": {"@type": "Service","name": "Wedding Photography"}},
      {"@type": "Offer","itemOffered": {"@type": "Service","name": "Engagement Photography"}},
      {"@type": "Offer","itemOffered": {"@type": "Service","name": "Portrait Photography"}},
      {"@type": "Offer","itemOffered": {"@type": "Service","name": "Event Photography"}}
    ]
  }
  </script>

  <!-- Optional: Breadcrumb (helps reinforce the home at this path) -->
  <script type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"BreadcrumbList",
    "itemListElement":[
      {"@type":"ListItem","position":1,"name":"Home","item":"<?= e($canonical) ?>"}
    ]
  }
  </script>
</head>
    







  
  

<body>
 <aside class="preloader" id="preloader" aria-hidden="true">
  <div class="pl-wrap" role="img" aria-label="Loading Mr.Look Photography">
    <div class="pl-ring" aria-hidden="true">
      <svg viewBox="0 0 160 160">
        <circle class="track" cx="80" cy="80" r="70" fill="none" stroke-width="10"/>
        <circle class="arc"   cx="80" cy="80" r="70" fill="none" stroke-width="10"/>
      </svg>
    </div>
    <div class="pl-logo">
      <img class="pl-logo-rotate" src="<?= asset('assets/logo.png') ?>" alt="Mr.Look logo" width="90" height="90" decoding="async" />
    </div>
  </div>
</aside>

  <!-- Progress bar -->
  <div class="progress-bar" aria-hidden="true"></div>

  
  
  
 

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">

<header class="header" id="top">
  <div class="container nav-wrap nav-3col">
    <!-- Brand → always the canonical home -->
    <a class="logo brand-left slide-down"
       href="<?= e($canonical) ?>#top"
       aria-label="Mr.Look Home" rel="home">
      Mr.Look Weddings
    </a>

    <!-- DESKTOP NAV (visible ≥ 880px) -->
    <nav class="nav-desktop" aria-label="Primary">
      <a href="<?= e($canonical) ?>#"      class="nav-link" aria-current="page">Home</a>
      <a href="<?= e($canonical) ?>#albums"   class="nav-link">Album</a>
      <a href="<?= e($canonical) ?>#packages" class="nav-link">Packages</a>
      <a href="<?= e($canonical) ?>#reviews"  class="nav-link">Reviews</a>
      <a href="<?= e($canonical) ?>#contact"  class="nav-link">Contact</a>
    </nav>

    <!-- Hamburger -->
    <button class="hamburger" aria-controls="nav-mobile" aria-expanded="false" aria-label="Toggle menu">
      <i class="ri-menu-line icon-menu" aria-hidden="true"></i>
      <i class="ri-close-line icon-close" aria-hidden="true"></i>
    </button>
  </div>
</header>

<!-- MOBILE DRAWER (outside header to avoid iOS clipping) -->
<div id="nav-mobile" class="drawer" aria-hidden="true">
  <div class="drawer-scrim" data-close></div>
  <nav class="drawer-panel" role="navigation" aria-label="Primary mobile">
    <a href="<?= e($canonical) ?>#top"      class="drawer-link">Home</a>
    <a href="<?= e($canonical) ?>#albums"   class="drawer-link">Album</a>
    <a href="<?= e($canonical) ?>#packages" class="drawer-link">Packages</a>
    <a href="<?= e($canonical) ?>#reviews"  class="drawer-link">Reviews</a>
    <a href="<?= e($canonical) ?>#contact"  class="drawer-link">Contact</a>
  </nav>
</div>





  <!-- ===== Hero ===== -->
  <section class="hero hero-shot" id="home" aria-label="Hero">
    <div class="hero-media" style="--cycle:24s">
      <!-- LCP (eager + high priority) -->
      <?= picture('assets/hero-imgs/img01.jpeg', 'Couple portrait at sunset', [
        'class'=>'hero-bg', 'width'=>1920, 'height'=>1080, 'sizes'=>'100vw', 'fetchpriority'=>'high'
      ]) ?>
      <!-- Other slides remain lazy -->
      <?= picture('assets/hero-imgs/img02.jpeg', 'Bridal details', [
        'class'=>'hero-bg', 'width'=>1920, 'height'=>1080, 'sizes'=>'100vw', 'style'=>'--offset:-6s'
      ]) ?>
      <?= picture('assets/hero-imgs/img03.jpeg', 'Wedding celebration moment', [
        'class'=>'hero-bg', 'width'=>1920, 'height'=>1080, 'sizes'=>'100vw', 'style'=>'--offset:-12s'
      ]) ?>
      <?= picture('assets/hero-imgs/img04.jpeg', 'Elegant portrait', [
        'class'=>'hero-bg', 'width'=>1920, 'height'=>1080, 'sizes'=>'100vw', 'style'=>'--offset:-18s'
      ]) ?>

      <div class="hero-overlay strong"></div>
    </div>

    <div class="container hero-content">
      <div class="hero-stack">
        <div class="sub">
          <h2 class="subtitle" data-animate style="--delay:.7s">Book Now For Your Event</h2>
          <p class="eyebrow"  data-animate style="--delay:.9s">MR.LOOK WEDDINGS</p>
          <div class="cta"    data-animate style="--delay:1s">
            <a href="#projects" class="btn btn-light">Projects</a>
            <a href="booking.php" class="btn btn-primary">Book Now</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Clients ticker -->
  <section class="clients" id="clients" aria-label="Popular shoot types">
    <div class="container">
      <div class="ticker" data-speed="70">
        <div class="ticker__inner">
          <ul class="ticker__track">
            <li>Weddings</li><li>Portraits</li><li>Fashion</li><li>Baby Portraits</li><li>Commercials</li>
            <li>Weddings</li><li>Baby Portraits</li><li>Portraits</li><li>Events</li>
          </ul>
          <ul class="ticker__track" aria-hidden="true">
            <li>Weddings</li><li>Portraits</li><li>Fashion</li>
            <li>Commercials</li><li>Baby Portraits</li><li>Weddings</li><li>Portraits</li><li>Commercials</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- Projects / Gallery -->
  <section class="section" id="projects">
    <div class="container">
      <header class="section-header" data-animate>
        <h2 class="section-title">Featured Projects</h2>
        <p class="section-sub">A small selection from recent shoots.</p>
      </header>

      <div class="masonry">
        <figure class="card-img"><?= picture('assets/img16.jpeg','Wedding candid',['width'=>900,'height'=>1200,'sizes'=>'(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw']) ?></figure>
        <figure class="card-img"><?= picture('assets/Img9.jpg','Portrait studio light',['width'=>900,'height'=>1200,'sizes'=>'(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw']) ?></figure>
        <figure class="card-img"><?= picture('assets/img17.jpeg','Couple at sunset',['width'=>900,'height'=>1200,'sizes'=>'(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw']) ?></figure>
        <figure class="card-img"><?= picture('assets/img11.jpeg','Event details',['width'=>900,'height'=>1200,'sizes'=>'(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw']) ?></figure>
        <figure class="card-img"><?= picture('assets/img19.jpeg','Bride preparation',['width'=>900,'height'=>1200,'sizes'=>'(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw']) ?></figure>
        <figure class="card-img"><?= picture('assets/img23.jpeg','Outdoor portrait',['width'=>900,'height'=>1200,'sizes'=>'(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw']) ?></figure>
        <figure class="card-img"><?= picture('assets/img56.jpeg','Brand product',['width'=>900,'height'=>1200,'sizes'=>'(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw']) ?></figure>
        <figure class="card-img"><?= picture('assets/img21.jpeg','Ceremony moment',['width'=>900,'height'=>1200,'sizes'=>'(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw']) ?></figure>
        <figure class="card-img"><?= picture('assets/img22.jpeg','Engagement ring',['width'=>900,'height'=>1200,'sizes'=>'(min-width:980px) 32vw, (min-width:640px) 48vw, 92vw']) ?></figure>
      </div>

      <!-- Lightbox -->
      <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Image preview">
        <button class="lightbox-close" aria-label="Close preview">×</button>
        <button class="lightbox-prev" aria-label="Previous">‹</button>
        <img class="lightbox-img" alt="Preview" width="1200" height="900">
        <button class="lightbox-next" aria-label="Next">›</button>
        <p class="lightbox-cap"></p>
      </div>

      <div class="center" data-animate>
        <a href="gallery.php" class="btn btn-primary" style="color:#000">Explore Gallery</a>
      </div>
    </div>
  </section>

  <div class="section-sep" aria-hidden="true"><span></span></div>

  <!-- ABOUT -->
  <section class="py-5 about-compact" id="about" aria-label="About Mr.Look Photography">
    <div class="container">
      <header class="section-header" data-animate>
        <h2 class="section-title">About Mr.Look Weddings</h2>
        <p class="section-sub">Story-driven images. Personal, elegant, timeless.</p>
      </header>

      <div class="row align-items-center g-4">
        <div class="col-lg-4 text-center text-lg-start">
          <figure class="about-avatar-wrap" data-animate>
            <?= picture('assets/owner.jpeg','Photographer — Mr.Look',['class'=>'about-avatar','width'=>480,'height'=>480]) ?>
          </figure>
        </div>

        <div class="col-lg-8" data-animate>
          <p class="about-lead mb-3">
            We capture the heart of your day—real emotion, natural beauty, and the
            little moments you’ll treasure forever. With a passion for storytelling,
            we craft elegant visuals that reflect the love, joy, and personality of every couple.
          </p>

          <ul class="about-list">
            <li><i class="ri-heart-3-line"></i><span><strong>Passion & dedication</strong> in every frame.</span></li>
            <li><i class="ri-sparkling-2-line"></i><span><strong>Creative vision</strong> with artful composition and detail.</span></li>
            <li><i class="ri-user-smile-line"></i><span><strong>Personal approach</strong> tailored to your story.</span></li>
            <li><i class="ri-flashlight-line"></i><span><strong>Fast turnaround</strong> so you can relive the day sooner.</span></li>
          </ul>

          <div class="cta mt-2">
            <a href="#packages" class="btn btn-primary">See Packages</a>
            <a href="booking.php" class="btn btn-light">Book a Date</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="section-sep" aria-hidden="true"><span></span></div>

  <!-- ===== Albums Marquee ===== -->
  <section class="albums-carousel mrlook" id="albums">
    <div class="albums-carousel__container">
      <header class="albums-carousel__header">
        <h2>Curated Collection of Albums!</h2>
        <p>What I have Created!</p>
      </header>

      <div class="albums-carousel__mask" data-speed="75s" data-direction="left">
        <ul class="albums-carousel__track">
          <?php if (!empty($albums)): ?>
            <?php foreach ($albums as $a): ?>
              <li class="album-card">
                <div class="album-card__box">
                  <a href="album.php?slug=<?= e($a['slug']) ?>" class="album-card__media">
                    <?= picture($a['cover_path'], $a['title'], [
                      'width'=>340,'height'=>430,
                      'sizes'=>'(min-width:1200px) 300px, (min-width:992px) 260px, (min-width:768px) 230px, 82vw'
                    ]) ?>
                    <span class="album-card__hover">See Album</span>
                  </a>
                </div>
                <h3 class="album-card__title"><?= e($a['title']) ?></h3>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li class="album-card">
              <div class="album-card__box">
                <a href="#" class="album-card__media">
                  <?= picture('assets/placeholder.jpg', 'Album', ['width'=>340,'height'=>430]) ?>
                  <span class="album-card__hover">See Album</span>
                </a>
              </div>
              <h3 class="album-card__title">Album coming soon</h3>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="albums-carousel__cta">
        <a href="/albums_list.php" class="albums-btn">View more albums</a>
      </div>
    </div>
  </section>

  <div class="section-sep" aria-hidden="true"><span></span></div>

  <!-- ===== Packages / Pricing ===== -->
  <section class="section alt" id="packages">
    <div class="container">
      <header class="section-header" data-animate>
        <h2 class="section-title">Packages</h2>
        <p class="section-sub">Swipe to explore — tap for details.</p>
      </header>

      <div class="cbar" id="pkgCarousel" data-animate>
        <button class="cbtn cprev" aria-label="Previous"><i class="ri-arrow-left-line"></i></button>

        <div class="cview" aria-label="Packages">
          <div class="ctrack">
            <article class="pkg-card minimal portrait">
              <figure class="pkg-img">
                <?= picture('assets/packages/pkg2.jpeg','Wedding — Mr.Look',['width'=>640,'height'=>960]) ?>
                <a href="booking.php?pkg=wedding" class="btn btn-primary pkg-btn">Book Now</a>
              </figure>
            </article>

            <article class="pkg-card minimal portrait">
              <figure class="pkg-img">
                <?= picture('assets/packages/pkg3.jpeg','Engagement — Mr.Look',['width'=>640,'height'=>960]) ?>
                <a href="booking.php?pkg=engagement" class="btn btn-primary pkg-btn">Book Now</a>
              </figure>
            </article>

            <article class="pkg-card minimal portrait">
              <figure class="pkg-img">
                <?= picture('assets/packages/pkg4.jpeg','Pre Shoot — Mr.Look',['width'=>640,'height'=>960]) ?>
                <a href="booking.php?pkg=pre-shoot" class="btn btn-primary pkg-btn">Book Now</a>
              </figure>
            </article>

            <article class="pkg-card minimal portrait">
              <figure class="pkg-img">
                <?= picture('assets/packages/pkg5.jpeg','Custom Package — Mr.Look',['width'=>640,'height'=>960]) ?>
                <a href="booking.php?pkg=custom" class="btn btn-primary pkg-btn">Book Now</a>
              </figure>
            </article>

            <article class="pkg-card minimal portrait">
              <figure class="pkg-img">
                <?= picture('assets/packages/pkg6.jpeg','Pre Shoot — Mr.Look',['width'=>640,'height'=>960]) ?>
                <a href="booking.php?pkg=pre-shoot" class="btn btn-primary pkg-btn">Book Now</a>
              </figure>
            </article>

            <article class="pkg-card minimal portrait">
              <figure class="pkg-img">
                <?= picture('assets/packages/pkg7.jpeg','Pre Shoot — Mr.Look',['width'=>640,'height'=>960]) ?>
                <a href="booking.php?pkg=pre-shoot" class="btn btn-primary pkg-btn">Book Now</a>
              </figure>
            </article>

            <article class="pkg-card minimal portrait">
              <figure class="pkg-img">
                <?= picture('assets/packages/pkg8.jpeg','Pre Shoot — Mr.Look',['width'=>640,'height'=>960]) ?>
                <a href="booking.php?pkg=pre-shoot" class="btn btn-primary pkg-btn">Book Now</a>
              </figure>
            </article>

            <article class="pkg-card minimal portrait">
              <figure class="pkg-img">
                <?= picture('assets/packages/pkg9.jpeg','Pre Shoot — Mr.Look',['width'=>640,'height'=>960]) ?>
                <a href="booking.php?pkg=pre-shoot" class="btn btn-primary pkg-btn">Book Now</a>
              </figure>
            </article>

            <article class="pkg-card minimal portrait">
              <figure class="pkg-img">
                <?= picture('assets/packages/extra.jpeg','Extras — Mr.Look',['width'=>640,'height'=>960]) ?>
              </figure>
            </article>
          </div>
        </div>

        <button class="cbtn cnext" aria-label="Next"><i class="ri-arrow-right-line"></i></button>
      </div>

      <div class="cdots" id="pkgDots" aria-hidden="true"></div>
    </div>
  </section>

  <div class="section-sep" aria-hidden="true"><span></span></div>

  <!-- Reviews -->
  <section class="section" id="reviews">
    <div class="container">
      <header class="section-header" data-animate>
        <h2 class="section-title">Reviews</h2>
        <p class="section-sub">Real words from happy clients.</p>
      </header>

      <div class="revbar" data-animate>
        <button class="rbtn rprev" aria-label="Previous"><i class="ri-arrow-left-line"></i></button>

        <div class="rview" id="revView" aria-label="Client reviews">
          <div class="rtrack" id="reviewsTrack">
            <?php if ($reviews): ?>
              <?php foreach ($reviews as $r):
                $name  = $r['customer_name'] !== '' ? $r['customer_name'] : 'Anonymous';
                $rate  = (int)$r['rating']; if ($rate < 1 || $rate > 5) $rate = 5;
                $stars = str_repeat('★', $rate) . str_repeat('☆', 5 - $rate);
                $text  = $r['review'];
                $date  = $r['review_date'] ? date('M j, Y', strtotime($r['review_date'])) : '';
              ?>
                <figure class="review-card">
                  <div class="review-stars" aria-label="<?= e($rate) ?> out of 5"><?= e($stars) ?></div>
                  <blockquote><?= e($text) ?></blockquote>
                  <figcaption>— <?= e($name) ?><?= $date ? ' · ' . e($date) : '' ?></figcaption>
                </figure>
              <?php endforeach; ?>
            <?php else: ?>
              <figure class="review-card">
                <div class="review-stars" aria-label="5 out of 5">★★★★★</div>
                <blockquote>“Every moment felt natural. The album is stunning!”</blockquote>
                <figcaption>— Kavindi &amp; Tharindu</figcaption>
              </figure>
              <figure class="review-card">
                <div class="review-stars">★★★★★</div>
                <blockquote>“Fast delivery and brilliant colors. Highly recommend.”</blockquote>
                <figcaption>— Ayesh</figcaption>
              </figure>
              <figure class="review-card">
                <div class="review-stars">★★★★★</div>
                <blockquote>“Our brand shoot boosted sales the same week.”</blockquote>
                <figcaption>— Moon Tea</figcaption>
              </figure>
            <?php endif; ?>
          </div>
        </div>

        <button class="rbtn rnext" aria-label="Next"><i class="ri-arrow-right-line"></i></button>

        <a class="btn" href="add-review.php?redirect=index.php#reviews">Write a Review</a>
      </div>
    </div>
  </section>

  <div class="section-sep" aria-hidden="true"><span></span></div>

  <!-- ===== FAQ ===== -->
  <section id="faq" class="faq-block">
    <div class="faq-container">
      <header class="faq-head">
        <h2 class="faq-title">FAQ</h2>
        <p class="faq-sub">Quick answers to common questions.</p>
      </header>

      <div class="faq-grid">
        <details class="faq-item">
          <summary><span>Do you travel for weddings?</span><svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
          <div class="faq-body">Yes — we love destination shoots. Travel fees depend on distance and schedule.</div>
        </details>
        <details class="faq-item">
          <summary><span>How soon do we get photos?</span><svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
          <div class="faq-body">A preview within 48 hours. Full galleries typically within 2–3 weeks.</div>
        </details>
        <details class="faq-item">
          <summary><span>Can we get the RAW files?</span><svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
          <div class="faq-body">We deliver professionally edited JPEGs. RAWs are available by special request.</div>
        </details>
        <details class="faq-item">
          <summary><span>Do you customize packages?</span><svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
          <div class="faq-body">Yes. Tell us your priorities and we’ll tailor coverage and deliverables.</div>
        </details>
        <details class="faq-item">
          <summary><span>What’s required to reserve the date?</span><svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
          <div class="faq-body">A signed agreement and a small booking fee secure your date.</div>
        </details>
        <details class="faq-item">
          <summary><span>Do you offer video too?</span><svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
          <div class="faq-body">Yes—ask for our photo + video bundle options.</div>
        </details>
        <details class="faq-item">
          <summary><span>Can we reschedule if needed?</span><svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
          <div class="faq-body">We’ll do our best to move with you. Reschedule fees depend on availability.</div>
        </details>
        <details class="faq-item">
          <summary><span>Do you provide prints & albums?</span><svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
          <div class="faq-body">Absolutely—premium albums and framed prints are available on request.</div>
        </details>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer footer-modern py-5" id="contact">
    <div class="container">
      <div class="row g-4 align-items-stretch">
        <div class="col-lg-5 d-flex flex-column" data-animate>
          <a href="#top" class="d-inline-flex align-items-center text-decoration-none mb-2">
            <span class="fs-4 fw-bold text-white">Mr.Look Photography</span>
          </a>
          <p class="text-secondary mb-3">We craft timeless stories — weddings, portraits, events & brands.</p>

          <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="tel:+94776767239" class="chip"><i class="ri-phone-line"></i> +94 77 676 7239</a>
            <a href="mailto:mr.lookphotographer@gmail.com" class="chip"><i class="ri-mail-line"></i> mr.lookphotographer@gmail.com</a>
            <span class="chip chip-ghost"><i class="ri-time-line"></i> Mon–Sat 9:00–18:00</span>
          </div>

<div class="footer-social d-flex gap-2 mt-1">
            <a class="btn btn-outline-light btn-icon" href="https://www.instagram.com/mr_look_weddding?igsh=aTV5NXBmcjMwMDJw" target="_blank" aria-label="Instagram"><i class="ri-instagram-line"></i></a>
            <a class="btn btn-outline-light btn-icon" href="https://www.facebook.com/share/18PYFh14oV/?mibextid=wwXIfr" target="_blank" aria-label="Facebook"><i class="ri-facebook-circle-line"></i></a>
            <a class="btn btn-outline-light btn-icon" href="https://www.tiktok.com/@chanukamadusanka_?_r=1&_t=ZS-96HCrWAFYXc" target="_blank" aria-label="TikTok"><i class="ri-tiktok-line"></i></a>
            <a class="btn btn-outline-light btn-icon" href="https://wa.me/94776767279" target="_blank" aria-label="WhatsApp"><i class="ri-whatsapp-line"></i></a>
          </div>
        </div>

        <div class="col-lg-7" data-animate>
          <div class="map-card position-relative rounded-4 overflow-hidden">
            <div class="map-ratio">
              <iframe title="Mr.Look Studio Map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                      src="https://www.google.com/maps?q=Panadura%2C%20Sri%20Lanka&output=embed"></iframe>
            </div>

            <div class="map-overlay card shadow-sm">
              <div class="card-body p-3 p-sm-4">
                <div class="d-flex align-items-start gap-2">
                  <div class="pin"><i class="ri-map-pin-2-fill"></i></div>
                  <div class="flex-grow-1">
                    <h6 class="mb-1 text-white">Mr.Look Photography Studio — Panadura</h6>
                    <p class="mb-2 small text-secondary">372, Horana Road, Mahabellana, Panadura 12524</p>
                    <div class="d-flex flex-wrap gap-2">
                      <a class="btn btn-warning btn-sm fw-semibold" target="_blank"
                         href="https://maps.app.goo.gl/KUWE5ieVNmJBv52P7">Get directions</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <hr class="my-4 border-secondary border-opacity-25">

      <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3" data-animate>
        <p class="mb-0 small text-secondary">© <span id="year"></span> Mr.Look Photography — All rights reserved.</p>

        <a class="crafted-by text-decoration-none" href="https://www.auricodesolutions.com" target="_blank" rel="noopener" aria-label="Crafted by Auricode Solutions">
          <span class="auri-logo-wrap">
            <?= picture('assets/AuricodeLogo.png','Auricode Solutions logo',['class'=>'auri-logo-img','width'=>140,'height'=>40]) ?>
          </span>
          <span class="crafted-text"><span class="by">Crafted by</span><strong>Auricode Solutions</strong></span>
        </a>

        <ul class="nav small">
          <li class="nav-item"><a class="nav-link px-2 subtle-link" href="terms&Condi.html">Terms &amp; Conditions</a></li>
        </ul>
      </div>
    </div>
  </footer>
  
  
  <!--iphone navbar fixed-->
  
<script>
(function(){
  const header  = document.querySelector('.header');
  const burger  = document.querySelector('.hamburger');
  const drawer  = document.getElementById('nav-mobile');
  const scrim   = drawer?.querySelector('[data-close]');

  function setNavHeight(){
    const h = header ? header.getBoundingClientRect().height : 64;
    document.documentElement.style.setProperty('--nav-h', h + 'px');
  }
  setNavHeight();
  ['load','resize','orientationchange','scroll'].forEach(ev =>
    window.addEventListener(ev, setNavHeight, {passive:true})
  );

  function openNav(open){
    drawer.classList.toggle('open', open);
    burger.setAttribute('aria-expanded', String(open));
    document.documentElement.classList.toggle('nav-open', open);
    document.body.classList.toggle('nav-open', open);
    setNavHeight();
  }

  burger?.addEventListener('click', () => openNav(!drawer.classList.contains('open')));
  scrim?.addEventListener('click', () => openNav(false));

  // Close after tapping a link
  drawer?.addEventListener('click', (e) => {
    if (e.target.closest('.drawer-link')) openNav(false);
  });

  // Safety: leaving mobile viewport closes the drawer
  const mq = window.matchMedia('(max-width: 880px)');
  mq.addEventListener?.('change', () => { if (!mq.matches) openNav(false); });
})();
</script>


  
  
  

  <!-- ===== JS: Preloader + Smooth image hydration + Albums carousel ===== -->
  <script>
  // Smooth image loader: add blur-up while loading (skips album marquee which has its own loop)
  function hydrateImages(root = document){
    root.querySelectorAll('img').forEach(img=>{
      if (img.closest('.albums-carousel')) return; // skip marquee
      if (img.complete && img.naturalWidth > 0){
        img.classList.add('is-loaded');
      } else {
        img.classList.add('is-loading');
        const done = () => { img.classList.remove('is-loading'); img.classList.add('is-loaded'); };
        img.addEventListener('load', done, {once:true});
        img.addEventListener('error', () => img.classList.remove('is-loading'), {once:true});
      }
    });
  }

  // Hide preloader when: fonts ready + first hero image loaded (or 2.2s failsafe), with a small minimum show time
  (function(){
    const pre = document.getElementById('preloader');
    const start = performance.now();

    function hide(){
      const elapsed = performance.now() - start;
      const wait = Math.max(0, parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--pl-min')) || 650);
      const left = Math.max(0, wait - elapsed);
      setTimeout(()=> pre.classList.add('is-hidden'), left);
    }

    // Wait for fonts + LCP hero image
    const hero = document.querySelector('.hero .hero-bg');
    const fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
    const heroReady  = (hero && (!hero.complete || hero.naturalWidth === 0))
      ? new Promise(res => { hero.addEventListener('load', res, {once:true}); hero.addEventListener('error', res, {once:true}); })
      : Promise.resolve();

    // Failsafe in case anything hangs
    const timeout = new Promise(res => setTimeout(res, 2200));

    Promise.race([ Promise.all([fontsReady, heroReady]).then(()=>true), timeout ]).then(hide);

    // As soon as DOM is interactive, start hydrating images
    if (document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', () => hydrateImages());
    } else {
      hydrateImages();
    }
  })();
  </script>

  
  
  <!-- Albums marquee (desktop CSS loop + mobile auto-advance) -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const MOBILE = matchMedia('(max-width: 768px)');

    document.querySelectorAll('.albums-carousel.mrlook').forEach(section => {
      const mask  = section.querySelector('.albums-carousel__mask');
      const track = section.querySelector('.albums-carousel__track');
      if (!mask || !track) return;

      const dir = (mask.getAttribute('data-direction') || 'left').toLowerCase();
      if (dir === 'right') mask.classList.add('to-right');

      // Duplicate once for seamless loop
      if (!track.dataset.duplicated){
        track.innerHTML += track.innerHTML;
        track.dataset.duplicated = 'true';
      }

      // Compute desktop duration
      const setDuration = () => {
        const explicit = (mask.getAttribute('data-speed') || '').trim();
        if (explicit.endsWith('s')) {
          section.style.setProperty('--duration', explicit);
        } else {
          const pps = parseFloat(mask.getAttribute('data-pps') || '40');
          const half = Math.max(track.scrollWidth / 2, 1);
          const seconds = Math.max(half / pps, 20);
          section.style.setProperty('--duration', seconds.toFixed(2) + 's');
        }
      };
      setDuration();

      // Random offset for desktop
      const dur = parseFloat(getComputedStyle(section).getPropertyValue('--duration')) || 60;
      track.style.animationDelay = `-${(Math.random() * dur).toFixed(2)}s`;

      // Pause desktop animation when off-screen
      const io = new IntersectionObserver(entries => {
        entries.forEach(e => mask.classList.toggle('is-animating', e.isIntersecting));
      }, { threshold: 0.1 });
      io.observe(mask);

      /* ------------ Mobile auto-scroll ------------ */
      let timer = null;
      let touching = false;

      const gapPx = () => {
        const cs = getComputedStyle(track);
        return parseFloat(cs.gap || cs.columnGap || '0') || 0;
      };
      const cardWidth = () => {
        const first = track.querySelector('.album-card');
        return first ? Math.round(first.getBoundingClientRect().width + gapPx())
                     : mask.clientWidth;
      };
      const halfWidth = () => Math.max(track.scrollWidth / 2, 1);

      const tick = () => {
        if (touching) return;
        if (mask.scrollLeft >= halfWidth() - cardWidth()){
          mask.scrollLeft = 0; // instant reset
        }
        mask.scrollBy({ left: cardWidth(), behavior: 'smooth' });
      };

      const startTimer = () => { stopTimer(); timer = setInterval(() => requestAnimationFrame(tick), 3000); };
      const stopTimer  = () => { if (timer){ clearInterval(timer); timer = null; } };

      const enableMobileAuto = () => { mask.classList.add('is-animating'); startTimer(); };
      const disableMobileAuto = () => { stopTimer(); mask.classList.remove('is-animating'); mask.scrollLeft = 0; };

      // Pause while user interacts
      ['touchstart','pointerdown','mousedown','focusin','mouseenter'].forEach(ev=>{
        mask.addEventListener(ev, () => { touching = true; stopTimer(); }, {passive:true});
      });
      ['touchend','pointerup','mouseup','touchcancel','blur','mouseleave'].forEach(ev=>{
        mask.addEventListener(ev, () => { touching = false; if (MOBILE.matches) startTimer(); }, {passive:true});
      });

      document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopTimer();
        else if (MOBILE.matches) startTimer();
      });

      const handleMode = () => {
        if (MOBILE.matches){ enableMobileAuto(); } else { disableMobileAuto(); }
      };
      MOBILE.addEventListener('change', handleMode);
      window.addEventListener('resize', () => { if (MOBILE.matches) startTimer(); setDuration(); });

      handleMode();
    });
  });
  </script>

  
  
  <!-- iOS-safe mobile auto-scroll (kept) -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const MOBILE = window.matchMedia('(max-width: 768px)');
    const supportsSmooth = CSS.supports && CSS.supports('scroll-behavior: smooth');

    document.querySelectorAll('.albums-carousel.mrlook').forEach(section => {
      const mask  = section.querySelector('.albums-carousel__mask');
      const track = section.querySelector('.albums-carousel__track');
      if (!mask || !track) return;

      // Duplicate once for seamless loop
      if (!track.dataset.duplicated){
        track.insertAdjacentHTML('beforeend', track.innerHTML);
        track.dataset.duplicated = 'true';
      }

      const gapPx = () => parseFloat(getComputedStyle(track).gap || '0') || 0;
      const cardW = () => {
        const first = track.querySelector('.album-card');
        const r = first ? first.getBoundingClientRect() : null;
        return r && r.width ? Math.round(r.width) : Math.round(mask.clientWidth * 0.88);
      };
      const halfWidth = () => Math.max(track.scrollWidth / 2, 1);

      let timer = null, touching = false;

      const stepScroll = () => {
        if (touching) return;
        const step = cardW() + gapPx();

        if (mask.scrollLeft + step + 2 >= halfWidth()) {
          mask.scrollLeft = 0; // instant reset (no flicker)
        }

        if (supportsSmooth) {
          mask.scrollBy({ left: step, behavior: 'smooth' });
        } else {
          const prev = mask.style.scrollBehavior;
          mask.style.scrollBehavior = 'auto';
          mask.scrollLeft += step;
          mask.style.scrollBehavior = prev || '';
        }
      };

      const waitForLayout = () => new Promise(resolve => {
        let tries = 0;
        const check = () => {
          const ready = (track.scrollWidth > mask.clientWidth + 10) && (cardW() > 10);
          if (ready || tries++ > 25) return resolve();
          requestAnimationFrame(check);
        };
        check();
      });

      const start = async () => {
        if (timer || !MOBILE.matches) return;
        await waitForLayout();
        stepScroll();
        timer = setInterval(stepScroll, 3000);
      };
      const stop = () => { if (timer){ clearInterval(timer); timer = null; } };

      ['touchstart','pointerdown','mousedown','focusin','mouseenter'].forEach(ev => {
        mask.addEventListener(ev, () => { touching = true; stop(); }, {passive:true});
      });
      ['touchend','pointerup','mouseup','touchcancel','blur','mouseleave'].forEach(ev => {
        mask.addEventListener(ev, () => { touching = false; if (MOBILE.matches) start(); }, {passive:true});
      });

      document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); else if (MOBILE.matches) start(); });
      window.addEventListener('resize', () => { if (MOBILE.matches) { stop(); start(); } });
      MOBILE.addEventListener('change', () => { stop(); if (MOBILE.matches) start(); });

      start();
    });
  });
  </script>

  <script> document.getElementById('year').textContent = new Date().getFullYear(); </script>

  <!-- Back to top -->
  <button id="toTop" aria-label="Back to top">↑</button>

  <!-- Defer scripts so they don't block rendering -->
  <script src="<?= asset('script.js') ?>" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

  <!-- If JS is disabled, make sure preloader doesn't cover content -->
  <noscript><style>.preloader{display:none!important}</style></noscript>
</body>
</html>
