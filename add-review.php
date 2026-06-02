<?php
// add-review.php (light theme)
session_start();
require __DIR__ . '/config.php'; // must set $pdo (PDO) in here

// Only allow same-site redirects
$redirect = $_GET['redirect'] ?? 'index.php#reviews';
if (preg_match('~^https?://~i', $redirect)) { $redirect = 'index.php#reviews'; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name   = trim($_POST['name'] ?? '');
  $name   = $name !== '' ? mb_substr($name, 0, 80) : 'Anonymous';
  $rating = (int)($_POST['rating'] ?? 5);
  if ($rating < 1 || $rating > 5) $rating = 5;
  $text   = trim($_POST['text'] ?? '');
  if ($text === '') {
    $err = 'Please write your review.';
  } else {
    try {
      $stmt = $pdo->prepare(
        "INSERT INTO reviews (customer_name, rating, review, review_date)
         VALUES (:n, :r, :t, :d)"
      );
      $stmt->execute([
        ':n' => $name,
        ':r' => $rating,
        ':t' => $text,
        ':d' => date('Y-m-d'),
      ]);
      header('Location: ' . $redirect);
      exit;
    } catch (Throwable $e) {
      $err = 'Could not save your review. Please try again.';
    }
  }
}
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Add a Review — Mr.Look Photography</title>

  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css" />

  <style>
    /* Light, clean look (white card) */
    :root{
      --yellow:#f59e0b;
      --gray-50:#fafafa; --gray-100:#f5f5f5; --gray-200:#e5e7eb; --gray-300:#d1d5db; --text:#111;
      --shadow:0 20px 60px rgba(0,0,0,.08);
    }
    body { background: var(--gray-100); color: var(--text); }
    .page-wrap { min-height:100svh; display:grid; place-items:center; padding:clamp(16px,3vw,28px); }
    .card-review { width:min(720px,96vw); border-radius:16px; background:#fff; color:var(--text);
                   border:1px solid var(--gray-200); box-shadow:var(--shadow); }
    .brand-mini { display:flex; align-items:center; gap:.6rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
    .brand-mini i { color: var(--yellow); }
    .form-control, textarea { background:#fff; border:1px solid var(--gray-300); color:var(--text); }
    .form-control:focus, textarea:focus { box-shadow:0 0 0 .2rem rgba(245,158,11,.15); border-color:#eab308; }
    .stars-input{ display:flex; flex-direction:row-reverse; gap:6px; }
    .stars-input input{ display:none; }
    .stars-input label{ cursor:pointer; font-size:1.6rem; line-height:1; color:#ccc; transition:transform .1s ease; user-select:none; }
    .stars-input input:checked ~ label, .stars-input label:hover, .stars-input label:hover ~ label{ color:#f5b301; }
    .stars-input label:active{ transform:scale(.94); }
  </style>
</head>
<body>
  <div class="page-wrap">
    <div class="card card-review">
      <div class="card-body p-4 p-sm-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="brand-mini"><i class="ri-star-smile-line"></i> Mr.Look Reviews</div>
          <a href="<?= e($redirect) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="ri-arrow-left-line"></i> Back
          </a>
        </div>

        <h1 class="h4 mb-1">Add your review</h1>
        <p class="text-muted mb-4">Share a few words about your experience. Thank you! 🧡</p>

        <?php if ($err): ?>
          <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endif; ?>

        <form method="post" class="row g-3" autocomplete="on" novalidate>
          <div class="col-md-6">
            <label class="form-label">Your Name</label>
            <input class="form-control" name="name" placeholder="e.g., Sanduni">
          </div>

          <div class="col-md-6">
            <label class="form-label">Rating</label>
            <div class="stars-input" role="radiogroup" aria-label="Rating out of 5">
              <input type="radio" id="r5" name="rating" value="5" checked><label for="r5" title="5 stars">★</label>
              <input type="radio" id="r4" name="rating" value="4"><label for="r4" title="4 stars">★</label>
              <input type="radio" id="r3" name="rating" value="3"><label for="r3" title="3 stars">★</label>
              <input type="radio" id="r2" name="rating" value="2"><label for="r2" title="2 stars">★</label>
              <input type="radio" id="r1" name="rating" value="1"><label for="r1" title="1 star">★</label>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Your Review</label>
            <textarea class="form-control" name="text" rows="4" placeholder="Tell us about your experience…" required></textarea>
          </div>

          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Submit Review</button>
            <a class="btn btn-light" href="<?= e($redirect) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
