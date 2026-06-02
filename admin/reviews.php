<?php
// admin/reviews.php
session_start();
// if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config.php';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function stars(int $n): string {
  $n = max(1, min(5, (int)$n));
  return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}

// --- Small "migration": ensure reviews.is_approved exists (MySQL 5.7+ safe)
try {
  $pdo->query("SELECT is_approved FROM reviews LIMIT 1");
} catch (Throwable $e) {
  try { $pdo->exec("ALTER TABLE reviews ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 1 AFTER review_date"); } catch (Throwable $ignored) {}
}

$flash = '';

// --- POST actions: approve / hide / delete
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  $id     = (int)($_POST['id'] ?? 0);

  if ($id > 0) {
    if ($action === 'approve') {
      $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 1 WHERE id = :id LIMIT 1");
      $stmt->execute([':id'=>$id]);
      $flash = "✅ Review #$id approved.";
    } elseif ($action === 'hide') {
      $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 0 WHERE id = :id LIMIT 1");
      $stmt->execute([':id'=>$id]);
      $flash = "🙈 Review #$id hidden.";
    } elseif ($action === 'delete') {
      $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = :id LIMIT 1");
      $stmt->execute([':id'=>$id]);
      $flash = "🗑️ Review #$id deleted.";
    }
  }
  // PRG pattern
  header("Location: reviews.php?msg=" . urlencode($flash));
  exit;
}

// --- Filters (GET)
$status = $_GET['status'] ?? 'all';          // all | approved | pending
$q      = trim($_GET['q'] ?? '');

$params = [];
$sql = "SELECT id, customer_name, rating, review, review_date, created_at, is_approved
        FROM reviews WHERE 1";

if ($status === 'approved')  { $sql .= " AND is_approved = 1"; }
if ($status === 'pending')   { $sql .= " AND is_approved = 0"; }

if ($q !== '') {
  $sql .= " AND (customer_name LIKE :q OR review LIKE :q)";
  $params[':q'] = '%' . $q . '%';
}

$sql .= " ORDER BY id DESC LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = $_GET['msg'] ?? $flash;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reviews — Admin</title>
<style>
:root{
  --bg:#0b1326; --panel:#0f172a; --text:#e5e7eb; --muted:#9ca3af;
  --accent:#f59e0b; --border:#1f2937; --radius:14px;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
.wrap{max-width:1200px;margin:0 auto;padding:18px}
header{position:sticky;top:0;background:rgba(15,23,42,.85);backdrop-filter:blur(8px);border-bottom:1px solid var(--border);padding:14px 18px}
.hstack{display:flex;align-items:center;gap:12px}
.pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:#111827;border:1px solid var(--border);border-radius:999px;color:var(--muted);text-decoration:none}
h1{font-size:20px;margin:0}
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:12px}
.controls{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}
input[type="text"],select{padding:10px;border-radius:10px;border:1px solid var(--border);background:#0b1222;color:var(--text)}
button, .btn{padding:10px 12px;border:none;border-radius:10px;background:var(--accent);color:#111;font-weight:700;cursor:pointer;text-decoration:none}
.btn-ghost{background:#111827;color:var(--muted)}
.btn-red{background:#b91c1c;color:#fff}
.grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
.review{display:flex;flex-direction:column;gap:8px}
.rowhead{display:flex;justify-content:space-between;align-items:center}
.badge{padding:.25rem .5rem;border-radius:999px;font-size:.8rem}
.badge-ok{background:#0f3e25;color:#b7ffd7;border:1px solid #115e36}
.badge-pend{background:#3a2a17;color:#ffd69b;border:1px solid #6b4b1a}
blockquote{margin:0;color:#dce2ee}
small{color:var(--muted)}
.flash{margin:12px 0;padding:10px 12px;border-radius:10px;background:#13231c;border:1px solid #244635;color:#c8ffd7}
.actions{display:flex;gap:6px;flex-wrap:wrap}
textarea.preview{width:100%;min-height:80px;background:#0b1222;border:1px solid var(--border);border-radius:10px;color:var(--text);padding:10px}
</style>
</head>
<body>

<header>
  <div class="wrap hstack">
    <a href="dashboard.php" class="pill">← Back</a>
    <h1>Reviews</h1>
    <div style="flex:1"></div>
    <!-- <a class="pill" href="../add-review.php?redirect=../admin/reviews.php">+ Add test review</a> -->
  </div>
</header>

<main class="wrap">
  <?php if ($flash): ?>
    <div class="flash"><?= e($flash) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="get" class="controls">
      <select name="status" aria-label="Status">
        <option value="all"      <?= $status==='all'?'selected':'' ?>>All</option>
        <option value="approved" <?= $status==='approved'?'selected':'' ?>>Approved</option>
        <option value="pending"  <?= $status==='pending'?'selected':'' ?>>Pending/Hidden</option>
      </select>
      <input type="text" name="q" placeholder="Search name or text…" value="<?= e($q) ?>">
      <button type="submit">Filter</button>
      <a class="btn btn-ghost" href="reviews.php">Reset</a>
    </form>

    <?php if (!$rows): ?>
      <p class="pill">No reviews found.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($rows as $r): ?>
          <div class="review card">
            <div class="rowhead">
              <strong>#<?= (int)$r['id'] ?> · <?= e(stars((int)$r['rating'])) ?></strong>
              <?php if ((int)$r['is_approved'] === 1): ?>
                <span class="badge badge-ok">Approved</span>
              <?php else: ?>
                <span class="badge badge-pend">Pending</span>
              <?php endif; ?>
            </div>

            <div>
              <small>
                <?= e($r['customer_name'] ?: 'Anonymous') ?>
                <?php if ($r['review_date']): ?> · <?= e(date('M j, Y', strtotime($r['review_date']))) ?><?php endif; ?>
              </small>
            </div>

            <div>
              <textarea class="preview" readonly><?= e($r['review']) ?></textarea>
            </div>

            <div class="actions">
              <?php if ((int)$r['is_approved'] === 1): ?>
                <!-- <form method="post">
                  <input type="hidden" name="action" value="hide">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit">Hide</button>
                </form> -->
              <?php else: ?>
                <!-- <form method="post">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit">Approve</button>
                </form> -->
              <?php endif; ?>

              <form method="post" onsubmit="return confirm('Delete this review permanently?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn-red" type="submit">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
