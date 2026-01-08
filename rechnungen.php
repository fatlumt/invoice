<?php
/***************************************************
 * rechnungen.php — Übersicht (old cards + modern popups)
 * - Sidebar + responsive layout
 * - Search + status + date filters
 * - Sorting + pagination
 * - Autosave invoice status (Offen/Bezahlt) via fetch()
 * - AJAX delete with custom confirmation popup
 * - Export modal -> print-friendly HTML -> Save as PDF (no QR page)
 ***************************************************/

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = $_SERVER['REQUEST_URI'] ?? 'rechnungen.php';
  header('Location: login.php?next='.urlencode($next));
  exit;
}
include 'config.php';

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function chf($n){ return number_format((float)$n, 2, ',', "'") . ' CHF'; }
function ymd(DateTimeImmutable $dt){ return $dt->format('Y-m-d'); }

/* ---------- AJAX: autosave status / delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
  header('Content-Type: application/json; charset=utf-8');

  if ($_POST['ajax'] === 'update_status') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id > 0 && in_array($status, ['offen','bezahlt'], true)) {
      // mirror legacy amount_due (0 if bezahlt, 1 if offen)
      $amount_due = ($status === 'bezahlt') ? 0 : 1;
      $stmt = $conn->prepare("UPDATE invoices SET status=?, amount_due=? WHERE id=?");
      $stmt->bind_param('sdi', $status, $amount_due, $id);
      $stmt->execute();
    }
    // fresh sums
    $agg = $conn->query("
      SELECT
        COALESCE(SUM(CASE WHEN status='bezahlt' THEN total ELSE 0 END),0) AS paid_sum,
        COALESCE(SUM(CASE WHEN status='offen'   THEN total ELSE 0 END),0) AS unpaid_sum
      FROM invoices
    ")->fetch_assoc();
    echo json_encode([
      'success'     => true,
      'paid_sum'    => chf($agg['paid_sum'] ?? 0),
      'unpaid_sum'  => chf($agg['unpaid_sum'] ?? 0),
    ]);
    exit;
  }

  if ($_POST['ajax'] === 'delete_invoice') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $st = $conn->prepare("DELETE FROM invoices WHERE id=?");
      $st->bind_param('i', $id);
      $st->execute();
    }
    $agg = $conn->query("
      SELECT
        COALESCE(SUM(CASE WHEN status='bezahlt' THEN total ELSE 0 END),0) AS paid_sum,
        COALESCE(SUM(CASE WHEN status='offen'   THEN total ELSE 0 END),0) AS unpaid_sum
      FROM invoices
    ")->fetch_assoc();
    echo json_encode([
      'success'     => true,
      'paid_sum'    => chf($agg['paid_sum'] ?? 0),
      'unpaid_sum'  => chf($agg['unpaid_sum'] ?? 0),
    ]);
    exit;
  }

  echo json_encode(['success'=>false,'error'=>'Unknown action']); exit;
}

/* ---------- Filters (from list page OR export modal) ---------- */
$q       = trim($_GET['q'] ?? '');
$statusF = $_GET['status'] ?? ''; // '', 'bezahlt', 'offen'
$range   = $_GET['range']  ?? 'all'; // all | this-month | last-month | this-year | custom
$startI  = $_GET['start']  ?? '';
$endI    = $_GET['end']    ?? '';

$today   = new DateTimeImmutable('today');
switch ($range) {
  case 'this-month':
    $s = $today->modify('first day of this month');
    $e = $today->modify('last day of this month');
    break;
  case 'last-month':
    $s = $today->modify('first day of last month');
    $e = $today->modify('last day of last month');
    break;
  case 'this-year':
    $y = (int)$today->format('Y');
    $s = new DateTimeImmutable("$y-01-01");
    $e = new DateTimeImmutable("$y-12-31");
    break;
  case 'custom':
    $s = $startI ? new DateTimeImmutable($startI) : $today->modify('-10 years');
    $e = $endI   ? new DateTimeImmutable($endI)   : $today;
    break;
  case 'all':
  default:
    $s = $today->modify('-50 years');
    $e = $today->modify('+1 day');
    break;
}
$start_ymd = ymd($s);
$end_ymd   = ymd($e);

/* ---------- WHERE builder ---------- */
$where  = [];
$params = [];
$types  = "";

if ($range !== 'all') {
  $where[] = "DATE(invoice_date) BETWEEN ? AND ?";
  $params[] = $start_ymd; $params[] = $end_ymd;
  $types   .= "ss";
}
if ($q !== '') {
  $where[]  = "(customer_name LIKE ? OR invoice_number LIKE ? OR bill_to LIKE ?)";
  $like      = "%$q%";
  $params[]  = $like; $params[] = $like; $params[] = $like;
  $types    .= "sss";
}
if ($statusF === 'bezahlt' || $statusF === 'offen') {
  $where[]  = "status = ?";
  $params[] = $statusF;
  $types   .= "s";
}
$whereSql = $where ? "WHERE ".implode(" AND ", $where) : "";

/* ---------- Sorting & Pagination ---------- */
$allowedSort = ['id','customer_name','invoice_number','invoice_date','subtotal','vat_amount','total','status'];
$sort = in_array($_GET['sort'] ?? '', $allowedSort, true) ? $_GET['sort'] : 'invoice_date';
$dir  = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';

$per_page = max(5, min(100, (int)($_GET['per_page'] ?? 15)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page-1) * $per_page;

/* ---------- Totals for cards (respect filters) ---------- */
$aggSql = "
  SELECT
    COALESCE(SUM(subtotal),0)   AS net_sum,
    COALESCE(SUM(vat_amount),0) AS vat_sum,
    COALESCE(SUM(total),0)      AS gross_sum,
    COALESCE(SUM(CASE WHEN status='bezahlt' THEN total ELSE 0 END),0) AS paid_sum,
    COALESCE(SUM(CASE WHEN status='offen'   THEN total ELSE 0 END),0) AS unpaid_sum,
    COUNT(*) AS inv_count
  FROM invoices
  $whereSql
";
$agg = $conn->prepare($aggSql);
if ($types !== "") { $agg->bind_param($types, ...$params); }
$agg->execute();
$tot = $agg->get_result()->fetch_assoc();
$vat_rate = 8.1;
$vat_due  = $tot['net_sum'] * ($vat_rate/100);

/* ---------- Count for pagination ---------- */
$countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM invoices $whereSql");
if ($types !== "") { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$total_rows  = (int)$countStmt->get_result()->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));

/* ---------- Main list (paged) ---------- */
$listSql = "
  SELECT id, customer_name, invoice_number, invoice_date, subtotal, vat_amount, total, status
  FROM invoices
  $whereSql
  ORDER BY $sort $dir, id DESC
  LIMIT ? OFFSET ?
";
$listTypes  = $types . "ii";
$listParams = array_merge($params, [$per_page, $offset]);
$listStmt   = $conn->prepare($listSql);
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$res = $listStmt->get_result();

/* ==========================================================
   EXPORT (print friendly) — open in new tab & Save as PDF
   ========================================================== */
if (isset($_GET['export']) && $_GET['export'] === 'print') {
  // Fetch ALL rows for the current filter (no pagination)
  $expSql = "
    SELECT id, customer_name, invoice_number, invoice_date, bill_to, subtotal, vat_amount, total, status
    FROM invoices
    $whereSql
    ORDER BY invoice_date DESC, id DESC
  ";
  $expStmt = $conn->prepare($expSql);
  if ($types !== "") { $expStmt->bind_param($types, ...$params); }
  $expStmt->execute();
  $expRes = $expStmt->get_result();
  ?>
  <!DOCTYPE html>
  <html lang="de">
  <head>
    <meta charset="utf-8">
    <title>Export – Rechnungen (Druck / PDF)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      @page { size: A4; margin: 14mm; }
      body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#0f172a; }
      h1 { margin:0 0 12px; font-size:18px; }
      .meta { font-size:12px; color:#6b7280; margin-bottom:14px; }
      table { width:100%; border-collapse: collapse; }
      th, td { padding:8px 10px; border-bottom: 1px solid #e5e7eb; font-size:12px; vertical-align: top; }
      th { background:#fafafa; text-align:left; }
      .right { text-align:right; }
      .status-paid { color:#16a34a; font-weight:700; }
      .status-unpaid { color:#dc2626; font-weight:700; }
      .footer { margin-top:12px; font-size:11px; color:#6b7280; }
      .noprint { margin: 10px 0 16px; }
      .btn { display:inline-block; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; background:#fff; cursor:pointer; font-size:14px; }
      @media print {.noprint { display:none !important; }}
    </style>
  </head>
  <body>
    <div class="noprint">
      <button class="btn" onclick="window.print()">🖨️ Drucken / Als PDF speichern</button>
    </div>
    <h1>Rechnungen – Export</h1>
    <div class="meta">
      Zeitraum:
      <?php
        if ($range==='all') { echo 'Alle'; }
        elseif ($range==='custom') { echo h($start_ymd).' bis '.h($end_ymd); }
        else {
          $labels = [
            'this-month' => 'Diesen Monat',
            'last-month' => 'Letzter Monat',
            'this-year'  => 'Dieses Jahr'
          ];
          echo h($labels[$range] ?? $range)." (".h($start_ymd)." – ".h($end_ymd).")";
        }
      ?>
      <?php if ($statusF==='bezahlt' || $statusF==='offen') echo ' • Status: '.h($statusF); ?>
      <?php if ($q!=='') echo " • Suche: '".h($q)."'"; ?>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width:7%">ID</th>
          <th style="width:22%">Kunde</th>
          <th style="width:14%">Rechnungs-Nr.</th>
          <th style="width:16%">Datum</th>
          <th style="width:14%" class="right">Netto</th>
          <th style="width:12%" class="right">MWST</th>
          <th style="width:15%" class="right">Total</th>
          <th style="width:10%">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($expRes->num_rows===0): ?>
          <tr><td colspan="8" style="text-align:center;color:#6b7280">Keine Rechnungen gefunden.</td></tr>
        <?php else: while($r=$expRes->fetch_assoc()): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td>
              <strong><?= h($r['customer_name'] ?: '—') ?></strong><br>
              <small style="color:#6b7280"><?= nl2br(h($r['bill_to'] ?? '')) ?></small>
            </td>
            <td><?= h($r['invoice_number'] ?: '—') ?></td>
            <td><?= h($r['invoice_date']) ?></td>
            <td class="right"><?= chf($r['subtotal']) ?></td>
            <td class="right"><?= chf($r['vat_amount']) ?></td>
            <td class="right"><strong><?= chf($r['total']) ?></strong></td>
            <td><?= $r['status']==='bezahlt'
                   ? '<span class="status-paid">Bezahlt</span>'
                   : '<span class="status-unpaid">Offen</span>' ?></td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>

    <div class="footer">
      * Diese Druckansicht exportiert nur die Liste der Rechnungen (ohne QR-Seite oder Einzel-PDFs).
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* ======================
   NORMAL LIST PAGE VIEW
   ====================== */
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Rechnungen – Übersicht</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<style>
:root{
  --bg:#f5f7fb; --ink:#0f172a; --muted:#6b7280; --card:#ffffff; --line:#e5e7eb;
  --accent-50:#fef2f2; --accent-200:#fecaca; --accent-600:#dc2626; --accent-700:#b91c1c;
  --radius:14px; --sidebar-w:260px; --paid:#16a34a;
}
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--ink);
  font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
a{color:var(--accent-700);text-decoration:none} a:hover{text-decoration:underline}

.layout{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh}
.sidebar{background:#fff;border-right:1px solid var(--line);padding:18px;position:sticky;top:0;height:100dvh;z-index:20;transition:transform .25s ease}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.content{padding:22px 26px}

/* Red topbar */
.topbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:16px;
  background-color:var(--accent-600); color:white;padding:10px; border-radius:15px;}
.title{font-size:20px;font-weight:700;margin:0}
.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{background:var(--accent-600);color:#fff;border:1px solid var(--accent-700);padding:10px 14px;border-radius:10px;font-weight:600;cursor:pointer}
.btn:hover{background:var(--accent-700)}
.btn.secondary{background:#fff;color:#111;border:1px solid #d1d5db}
.btnw{background:white;color:black;border:1px solid var(--accent-700);padding:10px 14px;border-radius:10px;font-weight:600;cursor:pointer}
.btnw:hover{background:var(--accent-700)}
.btnw.secondary{background:#fff;color:#111;border:1px solid #d1d5db}

/* OLD 4 CARDS (restored) */
.cards{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:14px;margin:14px 0 18px
}
.card{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:16px 18px;
  box-shadow:0 4px 10px rgba(0,0,0,.04);transition:transform .15s ease, box-shadow .15s ease}
.card:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,.06)}
.card .label{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.03em}
.card .value{font-size:22px;font-weight:800;margin-top:6px}
.tiny{font-size:12px;color:var(--muted)}

/* Panels / table */
.panel{background:#fff;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;box-shadow:0 6px 14px rgba(0,0,0,.05);margin-bottom:16px}
.panel .hd{display:flex;justify-content:space-between;align-items:center;padding:12px;border-bottom:1px solid var(--line);background:#fff}

.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{font-size:12px;color:#111;padding:12px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap}
tbody td{padding:12px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:middle}
tbody tr:hover{background:rgba(239,68,68,.03)}
.right{text-align:right}
.badge{display:inline-flex;align-items:center;background:var(--accent-600);border:1px solid var(--accent-200);color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:600}
.status-paid{color:var(--paid);font-weight:700}
.status-unpaid{color:var(--accent-600);font-weight:700}
.status-select{padding:6px 8px;border:1px solid var(--line);border-radius:8px;cursor:pointer;font:inherit}
.status-select.paid{border-color:var(--paid);color:var(--paid);font-weight:600}
.status-select.unpaid{border-color:var(--accent-600);color:var(--accent-600);font-weight:600}

/* Search bar */
.search-box{
  background:#fff;border:1px solid var(--line);border-radius:12px;padding:10px 12px;margin-bottom:16px;box-shadow:0 2px 5px rgba(0,0,0,.03)
}
.search-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.search-box input[type="text"],
.search-box select,
.search-box input[type="date"]{
  border:1px solid var(--line);border-radius:8px;padding:8px 10px;font:inherit;background:#fff
}
.search-box input[type="text"]{flex:1;min-width:180px}
.date-range{display:flex;gap:6px;align-items:center}

/* Modern Popups (Export + Confirmation + Toast) */
#popup,#actionPopup,#filterModal{
  display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.55);
  backdrop-filter:blur(3px);justify-content:center;align-items:center;
}
.popup-content{
  background:#fff;padding:26px 45px;border-radius:16px;font-size:18px;font-weight:600;
  box-shadow:0 8px 25px rgba(0,0,0,.3);animation:fadeIn .25s ease;text-align:center;
}
.popup-content.ok{border-left:6px solid var(--paid);color:var(--paid);}
.popup-content.err{border-left:6px solid var(--accent-600);color:var(--accent-600);}
#actionPopup .popup-content{font-size:16px;font-weight:500;}
#filterModal .popup-content{max-width:420px;width:90%;text-align:left;border-radius:18px;}
#filterModal label{font-size:14px;font-weight:600;color:#333;margin-top:10px;display:block;}
#filterModal input,#filterModal select{width:100%;padding:8px;border:1px solid var(--line);border-radius:6px;margin-top:5px;}
#filterModal .modal-actions{display:flex;justify-content:space-between;margin-top:20px;}
@keyframes fadeIn{from{opacity:0;transform:scale(.96);}to{opacity:1;transform:scale(1);}}

/* Responsive */
@media (max-width: 900px){
  .layout{grid-template-columns:1fr}
  .sidebar{position:fixed;left:0;top:0;height:100dvh;width:var(--sidebar-w);transform:translateX(-100%);box-shadow:0 20px 40px rgba(0,0,0,.2)}
  .sidebar.open{transform:translateX(0)}
  .content{padding:18px}
  .cards{grid-template-columns:1fr}
}
@media (max-width:720px){
  .table-wrap{display:block}
  thead{display:none}
  tbody tr{display:block;background:#fff;border:1px solid var(--line);border-radius:12px;margin:10px 8px;padding:6px 8px}
  tbody td{display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed var(--line);padding:10px 6px}
  tbody td:last-child{border-bottom:0}
  tbody td::before{content:attr(data-label);font-weight:600;color:#111;margin-right:12px}
  .right{text-align:left}
  .search-row{flex-direction:column;align-items:stretch}
  .search-box input,.search-box select,.btn{width:100%}
}
</style>
</head>
<body>
<div class="layout">
  <?php include __DIR__.'/includes/sidebar.php'; ?>

  <main class="content">
    <div class="topbar">
      <h2 class="title">Rechnungen – Übersicht</h2>
      <div class="actions">
        <!-- <buttn class="btn secondary" type="button" id="openExport">📥 Exportieren</button> -->
        <a href="create.php" class="btnw btn-sm">+ Neue Rechnung</a>
      </div>
    </div>

    <!-- OLD 4 CARDS (restored) -->
    <section class="cards">
      <div class="card">
        <div class="label">Umsatz (Brutto, inkl. MWST)</div>
        <div class="value"><?= chf($tot['gross_sum']) ?></div>
        <div class="tiny"><?= (int)$tot['inv_count'] ?> Rechnungen<?= $q ? " • Filter: '".h($q)."'" : "" ?></div>
      </div>
      <div class="card">
        <div class="label">Netto (vor MWST)</div>
        <div class="value"><?= chf($tot['net_sum']) ?></div>
        <div class="tiny">Berechnungsbasis für MWST</div>
      </div>
      <div class="card">
        <div class="label">An Staat zu zahlen (MWST <?= number_format($vat_rate,1,',','') ?>%)</div>
        <div class="value"><?= chf($vat_due) ?></div>
        <div class="tiny">Gespeicherte MWST: <?= chf($tot['vat_sum']) ?></div>
      </div>
      <div class="card">
        <div class="label">Status</div>
        <div class="value">
          <span class="status-paid" id="paidSum"><?= chf($tot['paid_sum']) ?></span>
          <span class="tiny">bezahlt</span> •<br/>
          <span class="status-unpaid" id="unpaidSum"><?= chf($tot['unpaid_sum']) ?></span>
          <span class="tiny">offen</span>
        </div>
      </div>
    </section>

    <!-- Filters -->
    <form class="search-box" method="get" action="rechnungen.php" id="filterForm">
      <div class="search-row">
        <input type="text" name="q" value="<?=h($q)?>" placeholder="Suchen… (Kunde, Nr, Adresse)">
        <select name="status">
          <option value="">Status: Alle</option>
          <option value="bezahlt" <?= $statusF==='bezahlt'?'selected':'' ?>>Bezahlt</option>
          <option value="offen"   <?= $statusF==='offen'?'selected':'' ?>>Offen</option>
        </select>
        <select name="range" id="range" onchange="toggleDateInputs(this.value)">
          <option value="all"        <?= $range==='all'?'selected':'' ?>>Alle</option>
          <option value="this-month" <?= $range==='this-month'?'selected':'' ?>>Diesen Monat</option>
          <option value="last-month" <?= $range==='last-month'?'selected':'' ?>>Letzter Monat</option>
          <option value="this-year"  <?= $range==='this-year'?'selected':'' ?>>Dieses Jahr</option>
          <option value="custom"     <?= $range==='custom'?'selected':'' ?>>Benutzerdefiniert</option>
        </select>
        <div id="customDates" class="date-range" <?= $range==='custom'?'':'style="display:none"' ?>>
          <input type="date" name="start" value="<?= h($range==='custom' ? $start_ymd : '') ?>">
          <input type="date" name="end"   value="<?= h($range==='custom' ? $end_ymd   : '') ?>">
        </div>
        <button type="submit" class="btn">Filtern</button>
      </div>
    </form>

    <!-- Invoices table -->
    <section class="panel">
      <div class="hd">
        <div class="tiny">Rechnungen (<?= $total_rows ?>)</div>
        <div class="tiny">Seite <?= $page ?>/<?= $total_pages ?></div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <?php
              function th_sort($label,$col,$sort,$dir){
                $next = ($sort===$col && $dir==='ASC')?'desc':'asc';
                $qs = $_GET; $qs['sort']=$col; $qs['dir']=$next;
                $arrow = $sort===$col ? ($dir==='ASC'?'↑':'↓') : '';
                echo "<th><a href='".h('?'.http_build_query($qs))."'>$label $arrow</a></th>";
              }
            ?>
            <tr>
              <?php th_sort('ID','id',$sort,$dir); ?>
              <th>Kunde</th>
              <?php th_sort('Rechnung Nr.','invoice_number',$sort,$dir); ?>
              <?php th_sort('Datum','invoice_date',$sort,$dir); ?>
              <?php th_sort('Netto','subtotal',$sort,$dir); ?>
              <?php th_sort('MWST','vat_amount',$sort,$dir); ?>
              <?php th_sort('Total','total',$sort,$dir); ?>
              <?php th_sort('Status','status',$sort,$dir); ?>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($res->num_rows === 0): ?>
            <tr><td colspan="9" class="tiny" style="text-align:center;color:var(--muted);padding:18px">Keine Rechnungen gefunden.</td></tr>
          <?php else: while ($row = $res->fetch_assoc()): ?>
            <?php $paid = ($row['status']==='bezahlt'); ?>
            <tr data-id="<?= (int)$row['id'] ?>">
              <td data-label="ID">#<?= (int)$row['id'] ?></td>
              <td data-label="Kunde"><?= h($row['customer_name'] ?: '—') ?></td>
              <td data-label="Rechnung Nr."><span class="badge"><?= h($row['invoice_number'] ?: '—') ?></span></td>
              <td data-label="Datum"><?= h($row['invoice_date']) ?></td>
              <td data-label="Netto" class="right"><?= chf($row['subtotal']) ?></td>
              <td data-label="MWST" class="right"><?= chf($row['vat_amount']) ?></td>
              <td data-label="Total" class="right"><strong><?= chf($row['total']) ?></strong></td>
              <td data-label="Status">
                <select class="status-select <?= $paid?'paid':'unpaid' ?>" data-id="<?= (int)$row['id'] ?>">
                  <option value="offen"   <?= $row['status']==='offen'?'selected':'' ?>>Offen</option>
                  <option value="bezahlt" <?= $row['status']==='bezahlt'?'selected':'' ?>>Bezahlt</option>
                </select>
              </td>
              <td data-label="Aktionen">
                <a href="edit.php?id=<?= (int)$row['id'] ?>" class="action-btn edit">Bearbeiten</a> |
                <a href="edit.php?id=<?= (int)$row['id'] ?>&autopdf=1" target="_blank" class="action-btn pdf">PDF</a> |
                <a href="#" class="action-btn del" data-del="<?= (int)$row['id'] ?>">Löschen</a>
              </td>
            </tr>
          <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div class="actions" style="justify-content:flex-end;padding:12px">
          <?php
            $qs = $_GET;
            $prev = max(1, $page-1); $next = min($total_pages, $page+1);
            $qs['page']=1;             echo '<a class="btn secondary" href="?'.h(http_build_query($qs)).'">« Erste</a> ';
            $qs['page']=$prev;         echo '<a class="btn secondary" href="?'.h(http_build_query($qs)).'">‹ Zurück</a> ';
            $qs['page']=$next;         echo '<a class="btn secondary" href="?'.h(http_build_query($qs)).'">Weiter ›</a> ';
            $qs['page']=$total_pages;  echo '<a class="btn secondary" href="?'.h(http_build_query($qs)).'">Letzte »</a>';
          ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</div>

<!-- Toast popup -->
<div id="popup"><div class="popup-content" id="popupMsg"></div></div>

<!-- Delete Confirmation Popup -->
<div id="actionPopup">
  <div class="popup-content">
    <h3 id="actTitle">Bestätigung</h3>
    <p id="actText">Sind Sie sicher?</p>
    <div style="display:flex;justify-content:center;gap:10px;">
      <button id="yesAct" class="btn" style="background:#16a34a;color:#fff;border:none;">Ja</button>
      <button id="noAct" class="btn">Abbrechen</button>
    </div>
  </div>
</div>

<!-- Export Modal (modern popup) -->
<div id="filterModal">
  <div class="popup-content">
    <h3>Export – Filter</h3>
    <form method="get" action="rechnungen.php" target="_blank" id="exportForm">
      <input type="hidden" name="export" value="print">
      <label>Suche</label>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="optional">
      <label>Status</label>
      <select name="status">
        <option value="">Alle</option>
        <option value="bezahlt" <?= $statusF==='bezahlt'?'selected':'' ?>>Bezahlt</option>
        <option value="offen"   <?= $statusF==='offen'?'selected':'' ?>>Offen</option>
      </select>
      <label>Zeitraum</label>
      <select name="range" id="expRange">
        <option value="all"        <?= $range==='all'?'selected':'' ?>>Alle</option>
        <option value="this-month" <?= $range==='this-month'?'selected':'' ?>>Diesen Monat</option>
        <option value="last-month" <?= $range==='last-month'?'selected':'' ?>>Letzter Monat</option>
        <option value="this-year"  <?= $range==='this-year'?'selected':'' ?>>Dieses Jahr</option>
        <option value="custom"     <?= $range==='custom'?'selected':'' ?>>Benutzerdefiniert</option>
      </select>
      <div id="expCustom" style="<?= $range==='custom'?'':'display:none' ?>;display:flex;gap:8px">
        <input type="date" name="start" value="<?= h($range==='custom' ? $start_ymd : '') ?>" style="flex:1;">
        <input type="date" name="end"   value="<?= h($range==='custom' ? $end_ymd   : '') ?>" style="flex:1;">
      </div>
      <div class="modal-actions">
        <button type="submit" class="btn" style="background:#16a34a;color:#fff;border:none;">PDF öffnen</button>
        <button type="button" class="btn" id="cancelExport">Abbrechen</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleDateInputs(val){
  const cd = document.getElementById('customDates');
  if (cd) cd.style.display = (val === 'custom') ? 'flex' : 'none';
}

// Toast popup
function showPopup(msg, ok=true){
  const p=document.getElementById('popup'), m=document.getElementById('popupMsg');
  m.textContent=msg; m.className='popup-content '+(ok?'ok':'err');
  p.style.display='flex'; clearTimeout(showPopup._t);
  showPopup._t=setTimeout(()=>p.style.display='none',1600);
}

// Confirm popup
function confirmAction(title,text,cb){
  const pop=document.getElementById('actionPopup');
  document.getElementById('actTitle').textContent=title;
  document.getElementById('actText').textContent=text;
  const y=document.getElementById('yesAct'),n=document.getElementById('noAct');
  pop.style.display='flex';
  function done(){pop.style.display='none';y.removeEventListener('click',ok);n.removeEventListener('click',cancel);}
  function ok(){done();if(typeof cb==='function')cb();}
  function cancel(){done();}
  y.addEventListener('click',ok); n.addEventListener('click',cancel);
}

// Open/close export modal
const modal = document.getElementById('filterModal');
document.getElementById('openExport').addEventListener('click', ()=> { modal.style.display='flex'; document.body.style.overflow='hidden'; });
document.getElementById('cancelExport').addEventListener('click', ()=> { modal.style.display='none'; document.body.style.overflow=''; });
modal.addEventListener('click', (e)=>{ if (e.target===modal) { modal.style.display='none'; document.body.style.overflow=''; } });

// Toggle custom fields inside export modal
document.getElementById('expRange').addEventListener('change', function(){
  document.getElementById('expCustom').style.display = (this.value==='custom') ? 'flex' : 'none';
});

// Autosave status + live totals
document.addEventListener('change',async e=>{
  const sel=e.target;if(!sel.matches('.status-select'))return;
  const id=sel.dataset.id,val=sel.value;
  try{
    const r=await fetch('rechnungen.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({ajax:'update_status',id,status:val})});
    const d=await r.json();
    sel.classList.toggle('paid', val==='bezahlt');
    sel.classList.toggle('unpaid', val==='offen');
    if (d.paid_sum)   document.getElementById('paidSum').textContent   = d.paid_sum;
    if (d.unpaid_sum) document.getElementById('unpaidSum').textContent = d.unpaid_sum;
    showPopup('Status gespeichert ✓', true);
  }catch{showPopup('Verbindungsfehler', false);}
});

// Delete with custom confirmation + AJAX
document.addEventListener('click',e=>{
  const del=e.target.closest('[data-del]'); if(!del)return;
  e.preventDefault(); const id=del.dataset.del;
  confirmAction('Rechnung löschen','Sind Sie sicher, dass Sie diese Rechnung löschen möchten?',async()=>{
    try{
      const r=await fetch('rechnungen.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({ajax:'delete_invoice',id})});
      const d=await r.json();
      if(!d.success)return showPopup('Fehler beim Löschen',false);
      document.querySelector(`tr[data-id="${id}"]`)?.remove();
      if (d.paid_sum)   document.getElementById('paidSum').textContent   = d.paid_sum;
      if (d.unpaid_sum) document.getElementById('unpaidSum').textContent = d.unpaid_sum;
      showPopup('Rechnung gelöscht ✓',true);
    }catch{showPopup('Verbindungsfehler',false);}
  });
});
</script>
</body>
</html>
