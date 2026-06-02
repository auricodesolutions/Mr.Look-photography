<?php
// booking.php
session_start();
require __DIR__ . '/config.php';     // must define $pdo (PDO connection)

// --- helpers / paths ---
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$uploadAbs = __DIR__ . '/assets/uploads/bookings/';
$uploadPub = 'assets/uploads/bookings/';
if (!is_dir($uploadAbs)) { @mkdir($uploadAbs, 0777, true); }

// --- defaults & result flags ---
$ok = false;
$error = '';
$vals = [
  'name'    => '',
  'email'   => '',
  'phone'   => '',
  'service' => '',
  'date'    => '',
  'time'    => '',
  'package' => '',
  'message' => '',
];

// --- handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // collect values
  foreach ($vals as $k => $v) { $vals[$k] = trim((string)($_POST[$k] ?? '')); }

  // basic validation (server-side)
  if ($vals['name'] === '')                       $error = 'Please enter your name.';
  elseif (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
  elseif ($vals['phone'] === '')                  $error = 'Enter a phone number.';
  elseif ($vals['service'] === '')                $error = 'Choose a service.';
  elseif ($vals['date'] === '')                   $error = 'Pick a date.';
  elseif ($vals['time'] === '')                   $error = 'Choose a time.';
  elseif ($vals['package'] === '')                $error = 'Select a package.';

  // uploads (optional, multiple)
  $savedPaths = [];
  if (!$error && isset($_FILES['ref']) && is_array($_FILES['ref']['name'])) {
    $allowed = ['jpg','jpeg','png','webp'];
    $count   = count($_FILES['ref']['name']);
    for ($i=0; $i<$count; $i++) {
      $err = $_FILES['ref']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
      if ($err === UPLOAD_ERR_NO_FILE) continue;
      if ($err !== UPLOAD_ERR_OK) continue;

      $name = (string)($_FILES['ref']['name'][$i] ?? '');
      $tmp  = (string)($_FILES['ref']['tmp_name'][$i] ?? '');
      $size = (int)($_FILES['ref']['size'][$i] ?? 0);
      $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

      if (!in_array($ext, $allowed, true)) continue;
      if ($size > 8 * 1024 * 1024) continue; // 8MB/file

      $base = 'booking_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
      $dest = $uploadAbs . $base;
      if (is_uploaded_file($tmp) && move_uploaded_file($tmp, $dest)) {
        $savedPaths[] = $uploadPub . $base; // public relative path
      }
    }
  }

  // insert
  if (!$error) {
    try {
      $stmt = $pdo->prepare(
        "INSERT INTO bookings
           (full_name, email, phone, service, event_date, event_time, package, notes, reference_image)
         VALUES
           (:n, :e, :p, :s, :d, :t, :pkg, :notes, :refs)"
      );
      $stmt->execute([
        ':n'    => $vals['name'],
        ':e'    => $vals['email'],
        ':p'    => $vals['phone'],
        ':s'    => $vals['service'],
        ':d'    => $vals['date'],
        ':t'    => $vals['time'],
        ':pkg'  => $vals['package'],
        ':notes'=> $vals['message'],
        // store JSON array of file paths
        ':refs' => json_encode($savedPaths, JSON_UNESCAPED_SLASHES),
      ]);
      $ok = true;
      // clear form values after success (page reload will show toast)
      foreach ($vals as $k => $v) { $vals[$k] = ''; }
    } catch (Throwable $th) {
      $error = 'Sorry, we could not save your request right now.';
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Book a Shoot — Mr.Look</title>

  <!-- Remix Icons + Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>

  <style>
    :root{
      /* Mr.Look theme tokens */
      --white:#fff;
      --black:#000;
      --amber:#f59e0b;
      --amber-600:#d97706;
      --gray-50:#fafafa;
      --gray-100:#f5f5f5;
      --gray-200:#e5e7eb;
      --gray-300:#d1d5db;
      --gray-500:#6b7280;
      --gray-700:#374151;

      /* Bootstrap CSS variables override */
      --bs-body-bg: var(--white);
      --bs-body-color: rgba(0,0,0,.92);
      --bs-primary: var(--amber);
      --bs-primary-rgb: 245,158,11;
      --bs-border-color: rgba(0,0,0,.12);
      --bs-border-radius: 14px;
      --bs-btn-border-radius: 12px;

      --ring: 0 0 0 .25rem rgba(245,158,11,.25);
      --shadow: 0 18px 45px rgba(0,0,0,.08);
      --container-max: 1100px;
    }

    body{ font:15px/1.6 system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }

    .container-custom{ width:min(var(--container-max), 92%); margin-inline:auto; }

    /* Navbar (glass on top of hero) */
    .navbar{
      backdrop-filter: saturate(1.2) blur(8px);
      -webkit-backdrop-filter: saturate(1.2) blur(8px);
      background: linear-gradient(to bottom, rgba(0,0,0,.92), rgba(0,0,0,.78));
      border-bottom: 1px solid var(--bs-border-color);
    }
    .navbar .navbar-brand{ letter-spacing:3px; font-weight:700; font-size: 1.3rem;color:#fff; text-decoration: none; align-items: center;}
    .navbar .nav-link{ color:#f3f3f3; font-weight:600; }
    .navbar .nav-link:hover{ color:#fff; }

  /* Hero */
    .hero{
      position:relative; isolation:isolate;
      min-height: clamp(360px, 56vh, 620px);
      display:grid; align-items:center;
      background: var(--gray-50);
      overflow:hidden;
    }
    .hero::after{
      content:""; position:absolute; inset:0; z-index:0;
      background:
        radial-gradient(900px 380px at 18% 12%, rgba(245,158,11,.12), transparent 70%),
        linear-gradient(180deg, rgba(0,0,0,0.00) 0%, rgba(0,0,0,.55) 100%);
      pointer-events:none;
    }
    .hero-media{ position:absolute; inset:0; z-index:-1; overflow:hidden; }
    .hero-img{
      width:100%; height:100%; object-fit:cover;
      filter: saturate(1.02) contrast(1.02) brightness(.92);
      transform: scale(1.02);
      opacity:0; transition: opacity .5s ease;
    }
    .hero-img.loaded{ opacity:1; }
    .hero-content{ position:relative; z-index:1; padding:48px 0; }
    .hero h1{ color:#fff; font-weight:900; letter-spacing:.02em; text-shadow:0 2px 16px rgba(0,0,0,.08); }
    .hero p{ color:var(--gray-200); max-width: 680px; }

    /* Brand button */
    .btn-brand{
      --bs-btn-bg: linear-gradient(135deg,var(--amber),var(--amber-600));
      --bs-btn-color:#111;
      --bs-btn-border-color: transparent;
      background: var(--bs-btn-bg);
      color: var(--bs-btn-color);
      border-color: var(--bs-btn-border-color);
      font-weight:800;
      box-shadow: 0 10px 22px rgba(245,158,11,.25);
    }
    .btn-brand:hover{ filter:saturate(1.06) brightness(1.02); }
    .btn-outline-dark{ border-color: var(--bs-border-color); }

    /* Cards */
    .card{ box-shadow: var(--shadow); border:1px solid var(--bs-border-color); }
    .card-header{ background:var(--white); }

    /* Form bits */
    .form-label{ font-weight:700; color:#111; }
    .input-group-text i{ font-size:20px; opacity:.85; }
    .form-control:focus, .form-select:focus{ box-shadow: var(--ring); border-color: var(--amber); }

    /* Toggle pills for Service */
    .toggle-group .btn{
      border:1px solid var(--bs-border-color);
      color:#111; background:#fff;
      transition: transform .1s ease;
    }
    .toggle-group .btn:hover{ transform: translateY(-1px); }
    .btn-check:checked + .btn{
      background: linear-gradient(135deg,var(--amber),var(--amber-600));
      border-color: transparent; color:#111; font-weight:800;
      box-shadow: 0 10px 22px rgba(245,158,11,.25);
    }

    /* Section header */
    .section-title{ font-weight:900; letter-spacing:.02em; }

    /* Feature icons */
    .feature i{ font-size:28px; }

    /* Reveal on scroll */
    .reveal{ opacity:0; transform: translateY(14px); transition: .5s ease; }
    .reveal.show{ opacity:1; transform:none; }

    /* Footer line */
   .foot{
    padding:36px 0 56px;
    background:var(--black);
    color:var(--gray-300);
    text-align:center;
    border-top:1px solid var(--border);
  }

    /* Toast center bottom */
    .toast-holder{
      position:fixed; left:50%; bottom:22px; transform:translateX(-50%); z-index:1080;
    }

    @media (max-width: 576px){
      .hero .actions{ justify-content:flex-start; }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container-custom d-flex align-items-center justify-content-between">
    <a class="btn btn-outline-light me-3" href="index.php">
      <i class="ri-home-4-line"></i> Home
    </a>
    <a class="navbar-brand mx-auto" href="index.php">MR.LOOK</a>
  </div>
</nav>

<!-- Hero -->
<header class="hero">
  <div class="hero-media">
    <img src="assets/img65.jpeg" alt="" class="hero-img" loading="lazy" id="heroImg">
  </div>
  <div class="container-custom hero-content">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <h1 class="display-5 mb-3">Let’s capture your big day.</h1>
        <p class="mb-4">Weddings, portraits, events, and commercial shoots — crafted with care and delivered fast.</p>
        <div class="d-flex gap-2 flex-wrap actions">
          <a href="#form" class="btn btn-brand"><i class="ri-send-plane-2-fill me-1"></i> Request Booking</a>
        </div>
      </div>
      <div class="col-lg-5 ms-lg-auto">
        <div class="card reveal">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="ri-calendar-check-line fs-4 text-warning"></i>
              <strong>Fast confirmation • Within 24 hours</strong>
            </div>
            <p class="mb-0 text-muted"> 10% advance payment required to reserve your date.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Booking Form -->
<main class="py-5" id="form">
  <div class="container-custom">
    <h2 class="section-title h1 mb-3 reveal">Book your date</h2>
    <p class="text-muted mb-4 reveal">Fill this quick form. We’ll email/text you to confirm.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-8">
        <form class="card reveal needs-validation" id="bookForm" method="post" enctype="multipart/form-data" novalidate>
          <div class="card-body p-4 p-md-5">
            <!-- Contacts -->
            <div class="row g-3">
              <div class="col-md-6">
                <label for="name" class="form-label">Full name</label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text"><i class="ri-user-line"></i></span>
                  <input id="name" name="name" class="form-control" placeholder="Your name" autocomplete="name" value="<?= e($vals['name']) ?>" required>
                  <div class="invalid-feedback">Please enter your name.</div>
                </div>
              </div>
              <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text"><i class="ri-mail-line"></i></span>
                  <input id="email" name="email" type="email" class="form-control" placeholder="you@email.com" autocomplete="email" value="<?= e($vals['email']) ?>" required>
                  <div class="invalid-feedback">Enter a valid email.</div>
                </div>
              </div>
              <div class="col-md-6">
                <label for="phone" class="form-label">Phone</label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text"><i class="ri-phone-line"></i></span>
                  <input id="phone" name="phone" class="form-control" placeholder="+94 77 6767 279"
                         inputmode="tel" pattern="^[+0-9\s-]{7,20}$" title="Use digits, +, spaces or dashes" value="<?= e($vals['phone']) ?>" required>
                  <div class="invalid-feedback">Enter a valid phone number.</div>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <!-- Service (toggle pills) -->
            <div class="mb-3">
              <label class="form-label d-block">Service</label>
              <div class="btn-group toggle-group flex-wrap" role="group" aria-label="Service">
                <input type="radio" class="btn-check" name="service" id="srvWedding" value="Wedding" <?= $vals['service']==='Wedding'?'checked':'' ?> required>
                <label class="btn btn-light me-2 mb-2" for="srvWedding"><i class="ri-hearts-line me-1"></i> Wedding</label>

                <input type="radio" class="btn-check" name="service" id="srvPortrait" value="Portrait" <?= $vals['service']==='Portrait'?'checked':'' ?>>
                <label class="btn btn-light me-2 mb-2" for="srvPortrait"><i class="ri-user-smile-line me-1"></i> Portrait</label>

                <input type="radio" class="btn-check" name="service" id="srvEvent" value="Event" <?= $vals['service']==='Event'?'checked':'' ?>>
                <label class="btn btn-light me-2 mb-2" for="srvEvent"><i class="ri-calendar-event-line me-1"></i> Event</label>

                <input type="radio" class="btn-check" name="service" id="srvCommercial" value="Commercial" <?= $vals['service']==='Commercial'?'checked':'' ?>>
                <label class="btn btn-light me-2 mb-2" for="srvCommercial"><i class="ri-store-line me-1"></i> Commercial</label>
              </div>
              <div class="form-text">Choose one (you can clarify more below).</div>
            </div>

            <!-- Date/Time/Package -->
            <div class="row g-3 mt-1">
              <div class="col-md-4">
                <label for="dateInput" class="form-label">Date</label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text"><i class="ri-calendar-line"></i></span>
                  <input id="dateInput" name="date" type="date" class="form-control" value="<?= e($vals['date']) ?>" required>
                  <div class="invalid-feedback">Pick a date.</div>
                </div>
              </div>
              <div class="col-md-4">
                <label for="time" class="form-label">Time</label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text"><i class="ri-time-line"></i></span>
                  <input id="time" name="time" type="time" class="form-control" value="<?= e($vals['time']) ?>" required>
                  <div class="invalid-feedback">Choose a time.</div>
                </div>
              </div>
              <div class="col-md-4">
                <label for="package" class="form-label">Package</label>
                <select id="package" name="package" class="form-select form-select-lg" required>
                  <option value="">Choose…</option>
                  <option <?= $vals['package']==='Mini Package'?'selected':'' ?>>Mini Package</option>
                  <option <?= $vals['package']==='Signature'?'selected':'' ?>>Signature</option>
                  <option <?= $vals['package']==='Full Day'?'selected':'' ?>>Full Day</option>
                </select>
                <div class="invalid-feedback">Select a package.</div>
              </div>
            </div>

            <!-- Message -->
            <div class="mt-4">
              <label for="message" class="form-label">Notes (optional)</label>
              <textarea id="message" name="message" rows="4" class="form-control" placeholder="Tell us about your event…"><?= e($vals['message']) ?></textarea>
            </div>

            <!-- Files -->
            <div class="mt-3">
              <label for="ref" class="form-label">Reference images (optional)</label>
              <input id="ref" name="ref[]" type="file" class="form-control" accept="image/*" multiple>
            </div>

            <!-- Actions -->
            <div class="d-flex flex-wrap gap-2 align-items-center mt-4">
              <button class="btn btn-brand btn-lg" type="submit"><i class="ri-send-plane-2-fill me-1"></i> Request Booking</button>
              <span class="text-muted">No payment now. We’ll confirm availability first.</span>
            </div>
          </div>
        </form>
      </div>

      <!-- Side panel -->
      <div class="col-lg-4">
        <div class="card mb-4 reveal">
          <div class="card-header py-3">
            <strong>Why book with us</strong>
          </div>
          <div class="card-body">
            <div class="d-flex gap-3 align-items-start feature mb-3">
              <i class="ri-flashlight-line text-warning"></i>
              <div><strong>Fast delivery</strong><div class="text-muted">Edited photos in 48–72h (events) or 7 days (weddings).</div></div>
            </div>
            <div class="d-flex gap-3 align-items-start feature mb-3">
              <i class="ri-shield-check-line text-warning"></i>
              <div><strong>Secure contract</strong><div class="text-muted">Clear scope, no surprise fees.</div></div>
            </div>
            <div class="d-flex gap-3 align-items-start feature">
              <i class="ri-timer-line text-warning"></i>
              <div><strong>Always on time</strong><div class="text-muted">We arrive early and prepared.</div></div>
            </div>
          </div>
        </div>

        <div class="card reveal">
          <div class="card-header py-3">
            <strong>Need help?</strong>
          </div>
          <div class="card-body">
            <p class="text-muted mb-3">Prefer WhatsApp or a quick call? We’re happy to chat.</p>
            <a class="btn btn-outline-dark w-100 mb-2" href="tel:+94770000000"><i class="ri-phone-fill me-1"></i> +94 77 6767 279</a>
            <a class="btn btn-outline-dark w-100" href="mailto:hello@mrlookstudio.com"><i class="ri-mail-fill me-1"></i> hello@mrlookstudio.com</a>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<footer class="foot">© <span id="year"></span> Mr.Look — All rights reserved.</footer>

<!-- Toast -->
<div class="toast-holder">
  <div class="toast align-items-center" style="background:#ecb80beb; border-color: var(--black) ;border-radius: 30px;" role="status" aria-live="polite" id="toast" data-bs-delay="2200">
    <div class="d-flex">
      <div class="toast-body">
        <i class="ri-check-line text-warning me-1" style="font-weight: 700;"></i> Sent! We’ll get back to you soon.
      </div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Year
  document.getElementById('year').textContent = new Date().getFullYear();

  // Hero image fade-in after load
  const heroImg = document.getElementById('heroImg');
  if (heroImg.complete) heroImg.classList.add('loaded');
  heroImg.addEventListener('load', ()=> heroImg.classList.add('loaded'));

  // Min date = today (local)
  (function(){
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth()+1).padStart(2,'0');
    const dd = String(d.getDate()).padStart(2,'0');
    document.getElementById('dateInput').setAttribute('min', `${yyyy}-${mm}-${dd}`);
  })();

  // Reveal on scroll
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{ if(e.isIntersecting) e.target.classList.add('show'); });
  }, { threshold:.1 });
  document.querySelectorAll('.reveal').forEach(el=> io.observe(el));

  // Persist basic fields
  (function(){
    const form = document.getElementById('bookForm');
    ['name','email','phone'].forEach(k=>{
      const el = form.elements[k];
      const saved = localStorage.getItem(`booking_${k}`);
      if(saved && !el.value) el.value = saved;              // don't override server values
      el?.addEventListener('input', ()=> localStorage.setItem(`booking_${k}`, el.value));
    });
  })();

  // Bootstrap validation (allow POST if valid)
  (function(){
    const form = document.getElementById('bookForm');
    form.addEventListener('submit', (e)=>{
      if(!form.checkValidity()){
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  })();

  // If PHP saved successfully, show the toast & clear localStorage
  <?php if ($ok): ?>
    (function(){
      const toast = new bootstrap.Toast(document.getElementById('toast'));
      toast.show();
      ['name','email','phone'].forEach(k=> localStorage.removeItem(`booking_${k}`));
    })();
  <?php endif; ?>
</script>
</body>
</html>
