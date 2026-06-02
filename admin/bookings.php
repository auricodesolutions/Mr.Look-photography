<?php
// admin/bookings.php
session_start();
// if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require __DIR__ . '/../config.php'; // provides $pdo

// ---------- PATHS ----------
$ROOT_ABS     = realpath(__DIR__ . '/..');
$UPLOADS_ABS  = $ROOT_ABS . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'bookings' . DIRECTORY_SEPARATOR;
$UPLOADS_URL  = 'assets/uploads/bookings/';

if (!is_dir($UPLOADS_ABS)) { @mkdir($UPLOADS_ABS, 0777, true); }

// ---------- HELPERS ----------
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function trunc(string $s, int $n=80): string { return mb_strlen($s) > $n ? (mb_substr($s,0,$n-1) . '…') : $s; }
function is_http($p){ return (bool)preg_match('~^https?://~i', $p); }
function admin_public(string $path): string {
  // This page is in /admin, so prefix ../ for local relative paths
  return is_http($path) ? $path : '../' . ltrim($path, '/');
}
function safe_unlink_upload(string $rootUploadsAbs, ?string $publicPath): bool {
  if (!$publicPath || is_http($publicPath)) return false;
  $abs = realpath($GLOBALS['ROOT_ABS'] . DIRECTORY_SEPARATOR . ltrim($publicPath, '/'));
  if ($abs && strpos($abs, $rootUploadsAbs) === 0 && is_file($abs)) return @unlink($abs);
  return false;
}

// ---------- ACTIONS ----------
$flash = '';
// Delete a single booking
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
  if ($id) {
    // Load row to remove any uploaded files
    $sel = $pdo->prepare("SELECT reference_image FROM bookings WHERE id = :id LIMIT 1");
    $sel->execute([':id'=>$id]);
    if ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
      $pdo->prepare("DELETE FROM bookings WHERE id = :id LIMIT 1")->execute([':id'=>$id]);
      // reference_image is JSON array of paths (or a single path)
      $refs = $row['reference_image'];
      $paths = [];
      if ($refs) {
        $j = json_decode($refs, true);
        if (is_array($j)) $paths = $j;
        elseif (is_string($refs)) $paths = [$refs];
      }
      foreach ($paths as $p) safe_unlink_upload($UPLOADS_ABS, $p);
      $flash = "🗑️ Booking #$id deleted.";
    } else {
      $flash = "Booking not found.";
    }
  } else {
    $flash = "Invalid booking ID.";
  }
}

// ---------- FILTERS / QUERY ----------
$q     = trim((string)($_GET['q']    ?? ''));
$from  = trim((string)($_GET['from'] ?? ''));
$to    = trim((string)($_GET['to']   ?? ''));
$page  = max(1, (int)($_GET['p'] ?? 1));
$limit = 25;
$off   = ($page-1) * $limit;

$where = [];
$bind  = [];

if ($q !== '') {
  $where[] = "(full_name LIKE :q OR email LIKE :q OR phone LIKE :q OR service LIKE :q OR package LIKE :q)";
  $bind[':q'] = "%$q%";
}
if ($from !== '') { $where[] = "event_date >= :from"; $bind[':from'] = $from; }
if ($to   !== '') { $where[] = "event_date <= :to";   $bind[':to']   = $to;   }

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Export CSV
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="bookings.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID','Full Name','Email','Phone','Service','Event Date','Event Time','Package','Notes','Ref Images','Created At']);
  $st = $pdo->prepare("SELECT * FROM bookings $sqlWhere ORDER BY created_at DESC, id DESC");
  $st->execute($bind);
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $refs = $r['reference_image'];
    if ($refs) {
      $j = json_decode($refs, true);
      if (is_array($j)) $refs = implode(' | ', $j);
    }
    fputcsv($out, [
      $r['id'], $r['full_name'], $r['email'], $r['phone'], $r['service'],
      $r['event_date'], $r['event_time'], $r['package'], $r['notes'], $refs, $r['created_at']
    ]);
  }
  fclose($out);
  exit;
}

// Count + fetch
$cntSt = $pdo->prepare("SELECT COUNT(*) FROM bookings $sqlWhere");
$cntSt->execute($bind);
$total = (int)$cntSt->fetchColumn();

$listSt = $pdo->prepare("SELECT * FROM bookings $sqlWhere ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $off");
$listSt->execute($bind);
$rows = $listSt->fetchAll(PDO::FETCH_ASSOC);

$pages = (int)ceil(max(1, $total) / $limit);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bookings — Admin</title>
<style>
:root{--bg:#0b1326;--panel:#0f172a;--text:#e5e7eb;--muted:#9ca3af;--accent:#f59e0b;--border:#1f2937;--radius:14px}
*{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
.wrap{max-width:1200px;margin:0 auto;padding:18px}
header{position:sticky;top:0;background:rgba(15,23,42,.8);backdrop-filter:blur(8px);border-bottom:1px solid var(--border);padding:14px 18px}
h2{margin:10px 0 8px}
.pill{display:inline-block;padding:6px 10px;background:#111827;border:1px solid var(--border);border-radius:999px;color:var(--muted)}
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius)}
.toolbar{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:12px}
input,select,button{border-radius:10px;border:1px solid var(--border);background:#0b1222;color:var(--text);padding:9px 10px}
button.btn{background:var(--accent);border:none;color:#111;font-weight:800;cursor:pointer}
.btn-ghost{background:#0b1222;color:var(--text);border:1px solid var(--border)}
.btn-danger{background:#b91c1c;color:#fff;border:none}
.flash{margin:12px 0;padding:10px 12px;border-radius:10px;background:#13231c;border:1px solid #244635;color:#c8ffd7}
.table{width:100%;border-collapse:separate;border-spacing:0}
.table th,.table td{padding:10px;border-bottom:1px solid var(--border);vertical-align:top}
.table th{font-weight:700;color:#cbd5e1;text-align:left}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;border:1px solid var(--border);background:#0b1222;color:var(--muted);font-size:.85rem}
.thumb{width:64px;height:64px;object-fit:cover;border-radius:10px;border:1px solid var(--border)}
.row-actions{display:flex;gap:6px;flex-wrap:wrap}
.pagination{display:flex;gap:6px;margin-top:12px;flex-wrap:wrap}
.pagination a,.pagination span{padding:6px 9px;border:1px solid var(--border);border-radius:8px;background:#0b1222;color:var(--text);text-decoration:none}
.pagination .cur{background:var(--accent);border-color:transparent;color:#111;font-weight:800}
.modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.5)}
.modal .inner{background:var(--panel);border:1px solid var(--border);border-radius:16px;max-width:760px;width:96%;padding:16px}
.modal.show{display:flex}
.grid-thumbs{display:flex;gap:8px;flex-wrap:wrap}
</style>
</head>
<body>
<header>
  <div class="wrap" style="display:flex;align-items:center;gap:10px">
    <div class="pill">Bookings</div>
    <div style="flex:1"></div>
    <a href="dashboard.php" class="pill" style="text-decoration:none">← Back</a>
  </div>
</header>

<main class="wrap">
  <?php if ($flash): ?><div class="flash"><?= h($flash) ?></div><?php endif; ?>

  <section class="card" style="padding:14px">
    <form class="toolbar" method="get">
      <div>
        <label>Search<br>
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="name, email, phone, service, package">
        </label>
      </div>
      <div>
        <label>From (date)<br>
          <input type="date" name="from" value="<?= h($from) ?>">
        </label>
      </div>
      <div>
        <label>To (date)<br>
          <input type="date" name="to" value="<?= h($to) ?>">
        </label>
      </div>
      <div>
        <button class="btn" type="submit">Filter</button>
      </div>
      <div style="flex:1"></div>
      <div>
        <a class="btn-ghost" href="?<?= http_build_query(array_filter(['q'=>$q,'from'=>$from,'to'=>$to])) ?>&export=csv">Export CSV</a>
      </div>
    </form>

    <div class="badge" style="margin:6px 0"><?= (int)$total ?> result(s)</div>

    <div style="overflow:auto">
      <table class="table">
        <thead>
          <tr>
            <th style="min-width:70px">ID</th>
            <th style="min-width:220px">Customer</th>
            <th style="min-width:200px">Event</th>
            <th>Notes</th>
            <th style="min-width:120px">Files</th>
            <th style="min-width:160px">Created</th>
            <th style="min-width:160px">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted)">No bookings yet.</td></tr>
        <?php else: foreach ($rows as $r): 
          $refs = [];
          if (!empty($r['reference_image'])) {
            $j = json_decode($r['reference_image'], true);
            if (is_array($j)) $refs = $j;
            elseif (is_string($r['reference_image'])) $refs = [$r['reference_image']];
          }
        ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td>
              <div><strong><?= h($r['full_name']) ?></strong></div>
              <div class="muted"><?= h($r['email']) ?> · <?= h($r['phone']) ?></div>
            </td>
            <td>
              <div><strong><?= h($r['service']) ?></strong> — <?= h($r['package']) ?></div>
              <div class="muted"><?= h($r['event_date']) ?> <?= $r['event_time'] ? '· '.h($r['event_time']) : '' ?></div>
            </td>
            <td><?= h(trunc($r['notes'] ?? '', 90)) ?></td>
            <td>
              <?php if ($refs): ?>
                <div class="grid-thumbs">
                  <?php foreach (array_slice($refs,0,3) as $p): ?>
                    <a href="<?= h(admin_public($p)) ?>" target="_blank" title="Open file">
                      <img class="thumb" src="<?= h(admin_public($p)) ?>" alt="">
                    </a>
                  <?php endforeach; ?>
                </div>
                <?php if (count($refs) > 3): ?><div class="muted">+<?= count($refs)-3 ?> more</div><?php endif; ?>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= h($r['created_at']) ?></td>
            <td class="row-actions">
              <button class="btn-ghost btn-view"
                      data-row='<?= h(json_encode($r, JSON_UNESCAPED_SLASHES)) ?>'
                      type="button">View</button>
              <form method="post" onsubmit="return confirm('Delete booking #<?= (int)$r['id'] ?>?');" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($p=1; $p<=$pages; $p++): 
          $qs = array_filter(['q'=>$q,'from'=>$from,'to'=>$to,'p'=>$p]);
          $url = '?' . http_build_query($qs);
        ?>
          <?php if ($p === $page): ?>
            <span class="cur"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= h($url) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<!-- View Modal -->
<div class="modal" id="viewModal" aria-hidden="true">
  <div class="inner">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <strong>Booking Details</strong>
      <button class="btn-ghost" id="closeModal" type="button">Close</button>
    </div>
    <div id="modalBody" style="display:grid;gap:10px"></div>
  </div>
</div>

<script>
const $ = (s, p=document)=>p.querySelector(s);
const $$ = (s, p=document)=>Array.from(p.querySelectorAll(s));

const modal = $('#viewModal');
const body  = $('#modalBody');
$('#closeModal').addEventListener('click', ()=> modal.classList.remove('show'));
window.addEventListener('keydown', (e)=>{ if(e.key==='Escape') modal.classList.remove('show'); });

$$('.btn-view').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const row = JSON.parse(btn.dataset.row);
    body.innerHTML = `
      <div><strong>#${row.id}</strong> — ${escapeHtml(row.full_name)}</div>
      <div style="color:#9ca3af">${escapeHtml(row.email)} · ${escapeHtml(row.phone)}</div>
      <div><strong>${escapeHtml(row.service)}</strong> — ${escapeHtml(row.package)} · ${escapeHtml(row.event_date)} ${row.event_time ? '· '+escapeHtml(row.event_time):''}</div>
      <div><strong>Notes:</strong><br>${escapeHtml(row.notes||'—')}</div>
      <div><strong>Created:</strong> ${escapeHtml(row.created_at||'')}</div>
      <div id="filesWrap"></div>
    `;
    // files
    const wrap = $('#filesWrap', body);
    let refs = [];
    try { refs = JSON.parse(row.reference_image||'[]'); } catch(e){}
    if(!Array.isArray(refs) && typeof row.reference_image === 'string' && row.reference_image.length){
      refs = [row.reference_image];
    }
    if(refs.length){
      const frag = document.createElement('div');
      frag.className = 'grid-thumbs';
      refs.forEach(p=>{
        const a = document.createElement('a');
        a.href = '../'+p.replace(/^\/+/,'');
        a.target = '_blank';
        const img = document.createElement('img');
        img.className = 'thumb';
        img.src = '../'+p.replace(/^\/+/,'');
        a.appendChild(img);
        frag.appendChild(a);
      });
      wrap.innerHTML = '<strong>Reference images:</strong>';
      wrap.appendChild(frag);
    } else {
      wrap.innerHTML = '<strong>Reference images:</strong> —';
    }
    modal.classList.add('show');
  });
});

function escapeHtml(s){
  return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}
</script>
</body>
</html>
