<?php
/* ---------------------------------------------------------
   company.php — Company Profile with Loader + Toasts
----------------------------------------------------------*/

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
  header('Location: login.php?next=' . urlencode($next));
  exit;
}

/* ---------- DB connection ---------- */
@include_once __DIR__ . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
  $conn = new mysqli('localhost', 'root', '', 'invoices_db');
  if ($conn->connect_error) die('DB connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

/* ---------- Defaults + Load (no warnings) ---------- */
$profile = [
  'id'           => 0,
  'company_name' => '',
  'addr_line1'   => '',
  'postal_code'  => '',
  'city'         => '',
  'phone'        => '',
  'email'        => '',
  'iban'         => '',
  'tax_id'       => '',
  'vat_rate'     => 8.10,
  'logo_path'    => null,
];

$res = $conn->query("SELECT * FROM company_profile ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows) {
  $dbrow = $res->fetch_assoc();
  if (is_array($dbrow)) $profile = array_merge($profile, $dbrow);
}

/* ---------- POST: Save ---------- */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $company_name = trim($_POST['company_name'] ?? '');
  $addr_line1   = trim($_POST['addr_line1']   ?? '');
  $postal_code  = trim($_POST['postal_code']  ?? '');
  $city         = trim($_POST['city']         ?? '');
  $phone        = trim($_POST['phone']        ?? '');
  $email        = trim($_POST['email']        ?? '');
  $iban         = trim($_POST['iban']         ?? '');
  $tax_id       = trim($_POST['tax_id']       ?? '');
  $vat_rate     = is_numeric($_POST['vat_rate'] ?? '') ? (float)$_POST['vat_rate'] : 8.10;

  // Optional logo upload
  $logo_path = $profile['logo_path'];
  if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
    $okExt = ['png','jpg','jpeg','webp','svg'];
    $ext   = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $okExt, true)) {
      $error = 'Ungültiges Logo-Format. Erlaubt: png, jpg, jpeg, webp, svg.';
    } else {
      $dir = __DIR__ . '/uploads';
      if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $newName = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      $dest = $dir . '/' . $newName;
      if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
        $logo_path = 'uploads/' . $newName;
      } else {
        $error = 'Logo konnte nicht gespeichert werden.';
      }
    }
  }

  if (!$error) {
    if ((int)$profile['id'] > 0) {
      // UPDATE (11 params) -> "ssssssssdsi"
      $sql = "UPDATE company_profile SET
                company_name=?, addr_line1=?, postal_code=?, city=?,
                phone=?, email=?, iban=?, tax_id=?, vat_rate=?, logo_path=?
              WHERE id=?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param(
        "ssssssssdsi",
        $company_name, $addr_line1, $postal_code, $city,
        $phone, $email, $iban, $tax_id, $vat_rate, $logo_path, $profile['id']
      );
      $stmt->execute(); $stmt->close();
    } else {
      // INSERT (10 params) -> "ssssssssds"
      $sql = "INSERT INTO company_profile
                (company_name, addr_line1, postal_code, city, phone, email, iban, tax_id, vat_rate, logo_path)
              VALUES (?,?,?,?,?,?,?,?,?,?)";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param(
        "ssssssssds",
        $company_name, $addr_line1, $postal_code, $city, $phone, $email, $iban, $tax_id, $vat_rate, $logo_path
      );
      $stmt->execute(); $profile['id'] = $stmt->insert_id; $stmt->close();
    }

    // PRG: redirect with ok=1 (green toast) OR jump to invoice form
    if (($_POST['action'] ?? '') === 'apply') {
      header('Location: invoice_form.php?ok=1&from=company');
      exit;
    } else {
      header('Location: company.php?ok=1');
      exit;
    }
  } else {
    // keep current values if error
    $profile = array_merge($profile, compact(
      'company_name','addr_line1','postal_code','city','phone','email','iban','tax_id','vat_rate'
    ));
    $profile['logo_path'] = $logo_path;
  }
}

// GET flash for success toast
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Firmenprofil</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
:root{
  --bg:#f5f7fb; --ink:#0f172a; --muted:#6b7280; --card:#ffffff; --line:#e5e7eb;
  --accent-50:#fef2f2; --accent-200:#fecaca; --accent-600:#dc2626; --accent-700:#b91c1c;
  --green:#16a34a; --red:#dc2626;
  --radius:14px; --sidebar-w:260px;
}
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--ink);
  font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
a{color:var(--accent-700);text-decoration:none} a:hover{text-decoration:underline}
.layout{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh}
.sidebar{background:#fff;border-right:1px solid var(--line);padding:18px;position:sticky;top:0;height:100dvh;z-index:20;transition:transform .25s ease}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.brand .logo{width:36px;height:36px;border:2px solid var(--accent-600);border-radius:9px}
.brand h1{font-size:16px;margin:0}
.menu a{display:flex;gap:10px;padding:10px 12px;border-radius:10px;color:#111;font-weight:500}
.menu a:hover{background:var(--accent-50)} .menu a.active{background:var(--accent-600);color:#fff}
.menu .section-title{margin:12px 8px 6px;font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.04em}
.content{padding:22px 26px}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;
  background:linear-gradient(180deg, #ffffff, #fee2e2); padding:12px 14px;border-radius:14px;border:1px solid var(--line)}
.title{font-size:20px;font-weight:700;margin:0}
.btn{background:var(--accent-600);color:#fff;border:1px solid var(--accent-700);padding:10px 14px;border-radius:10px;font-weight:600;cursor:pointer}
.btn:hover{background:var(--accent-700)}
.btn.secondary{background:#fff;color:#111;border:1px solid var(--line)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.field{display:flex;flex-direction:column;gap:6px}
label{font-size:12px;color:#111}
input[type="text"],input[type="email"],input[type="file"],input[type="number"]{
  width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;font:inherit}
.card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:16px;box-shadow:0 4px 10px rgba(0,0,0,.04)}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
@media (max-width: 900px){
  .layout{grid-template-columns:1fr}
  .sidebar{position:fixed;left:0;top:0;height:100dvh;width:var(--sidebar-w);transform:translateX(-100%);box-shadow:0 20px 40px rgba(0,0,0,.2)}
  .sidebar.open{transform:translateX(0)}
  .content{padding:18px}
  .grid{grid-template-columns:1fr}
}
.preview{display:flex; gap:12px; align-items:center; margin-top:8px; color:var(--muted); font-size:12px;}
.preview img{max-height:48px;border-radius:10px;border:1px solid var(--line);background:#fff;padding:6px}

/* ===== Loader overlay ===== */
.loader{
  position:fixed; inset:0; background:rgba(0,0,0,.18);
  display:none; align-items:center; justify-content:center; z-index:1000;
}
.loader.show{display:flex}
.spinner{
  width:54px; height:54px; border-radius:999px;
  border:5px solid #fff; border-top-color: var(--accent-600);
  animation: spin 1s linear infinite;
  box-shadow:0 4px 12px rgba(0,0,0,.2);
}
.loader .label{margin-top:10px; color:#fff; font-weight:700; text-shadow:0 1px 2px rgba(0,0,0,.25); text-align:center}
@keyframes spin { to { transform: rotate(360deg); } }

/* ===== Toasts ===== */
.toast-wrap{
  position:fixed; right:16px; bottom:16px; display:flex; flex-direction:column; gap:10px; z-index:1100;
}
.toast{
  min-width:260px; max-width:360px; padding:12px 14px;
  border-radius:12px; color:#0b1a10; background:#ecfdf5; border:1px solid #bbf7d0;
  box-shadow:0 10px 24px rgba(0,0,0,.15); display:flex; align-items:flex-start; gap:10px;
  animation: slideIn .2s ease-out;
}
.toast.error{ background:#fef2f2; border-color:#fecaca; color:#1f0b0b; }
.toast .title{font-weight:800; font-size:14px}
.toast .msg{font-size:13px; color:#374151}
.toast .x{ margin-left:auto; cursor:pointer; font-weight:900; opacity:.6}
.toast .x:hover{opacity:1}
@keyframes slideIn { from { transform: translateY(8px); opacity:0; } to { transform: translateY(0); opacity:1; } }
</style>
</head>
<body>
<div class="layout">
  <!-- Sidebar -->
  <!-- <aside class="sidebar" id="sidebar">
    <div class="brand"><div class="logo"></div><h1>Krasniqi – Admin</h1></div>
    <nav class="menu">
      <div class="section-title">Navigation</div>
      <a href="index.php">Dashboard</a>
      <a href="create.php">+ Neue Rechnung</a>
      <a href="users.php">Benutzer</a>
      <a href="company.php" class="active">Firmenprofil</a>
    </nav>
  </aside> -->
<?php include __DIR__.'/includes/sidebar.php'; ?>

  <!-- Main -->
  <main class="content">
    <div class="topbar">
      <h2 class="title">Firmenprofil</h2>
      <div class="actions">
        <a class="btn secondary" href="index.php">‹ Zur Übersicht</a>
      </div>
    </div>

    <form class="card" method="post" enctype="multipart/form-data" id="companyForm">
      <input type="hidden" name="id" value="<?= (int)$profile['id'] ?>">

      <div class="grid">
        <div class="field">
          <label>Firmenname</label>
          <input type="text" name="company_name" value="<?= h($profile['company_name']) ?>" required>
        </div>
        <div class="field">
          <label>Adresse (Zeile)</label>
          <input type="text" name="addr_line1" value="<?= h($profile['addr_line1']) ?>">
        </div>

        <div class="field">
          <label>PLZ</label>
          <input type="text" name="postal_code" value="<?= h($profile['postal_code']) ?>">
        </div>
        <div class="field">
          <label>Ort</label>
          <input type="text" name="city" value="<?= h($profile['city']) ?>">
        </div>

        <div class="field">
          <label>Telefon</label>
          <input type="text" name="phone" value="<?= h($profile['phone']) ?>">
        </div>
        <div class="field">
          <label>E-Mail</label>
          <input type="email" name="email" value="<?= h($profile['email']) ?>">
        </div>

        <div class="field">
          <label>IBAN</label>
          <input type="text" name="iban" value="<?= h($profile['iban']) ?>">
        </div>
        <div class="field">
          <label>ID-Nr / Steuer-Nr</label>
          <input type="text" name="tax_id" value="<?= h($profile['tax_id']) ?>">
        </div>

        <div class="field">
          <label>MWST-Satz (%)</label>
          <input type="number" name="vat_rate" step="0.01" value="<?= h(number_format((float)$profile['vat_rate'],2,'.','')) ?>">
          <small style="color:var(--muted)">Wird in Rechnungen verwendet</small>
        </div>
        <div class="field">
          <label>Logo (optional)</label>
          <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg">
          <div class="preview">
            <?php if (!empty($profile['logo_path'])): ?>
              <img src="<?= h($profile['logo_path']) ?>" alt="Logo">
              <span>Aktuelles Logo: <?= h(basename($profile['logo_path'])) ?></span>
            <?php else: ?>
              <span>Kein Logo hochgeladen</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="actions">
        <button class="btn" type="submit" name="action" value="save"  data-loading="Speichern…">💾 Speichern</button>
        <button class="btn" type="submit" name="action" value="apply" data-loading="Übernehmen…">↪ In Rechnung verwenden</button>
      </div>
    </form>
  </main>
</div>

<!-- Loader + Toasts -->
<div class="loader" id="loader">
  <div>
    <div class="spinner" style="margin:auto"></div>
    <div class="label" id="loaderText">Lädt…</div>
  </div>
</div>
<div class="toast-wrap" id="toasts"></div>

<script>
function toggleSidebar(){ document.querySelector('.sidebar').classList.toggle('open'); }

/* ===== Toasts ===== */
function toast(type, title, msg){
  const box = document.getElementById('toasts');
  const el = document.createElement('div');
  el.className = 'toast' + (type==='error' ? ' error' : '');
  el.innerHTML = `
    <div class="title">${title}</div>
    <div class="msg">${msg ? msg : ''}</div>
    <div class="x" title="schließen">×</div>
  `;
  el.querySelector('.x').onclick = ()=> el.remove();
  box.appendChild(el);
  setTimeout(()=>{ el.style.opacity='0'; el.style.transition='opacity .2s'; setTimeout(()=>el.remove(), 200); }, 4500);
}

/* ===== Loader on submit ===== */
const form = document.getElementById('companyForm');
const loader = document.getElementById('loader');
const loaderText = document.getElementById('loaderText');
let clickedBtn;
form.addEventListener('click', (e)=>{
  const b = e.target.closest('button[type="submit"]');
  if (b) clickedBtn = b;
});
form.addEventListener('submit', ()=>{
  loaderText.textContent = clickedBtn?.dataset.loading || 'Lädt…';
  loader.classList.add('show');
});

/* ===== Flash from PHP ===== */
<?php if ($ok): ?>
  toast('success', 'Gespeichert', 'Das Firmenprofil wurde aktualisiert.');
<?php endif; ?>
<?php if ($error): ?>
  toast('error', 'Fehler', <?= json_encode($error) ?>);
<?php endif; ?>
</script>
</body>
</html>
