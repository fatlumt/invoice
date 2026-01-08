<?php
/***************************************************
 * index.php — Responsive Invoices Dashboard (MySQLi)
 * - Sidebar + mobile burger menu
 * - Search, date presets/custom range (default = ALL)  
 * - Sortable, paginated list incl. Rechnung Nr.
 * - Dashboard cards (gross/net, paid/unpaid)
 * - VAT due @ 8.1% (based on subtotal/net)
 * - CSV export of current filter set
 * - Per-customer top totals & quarterly VAT (both collapsible)
 * - PDF button (opens edit.php with ?autopdf=1)
 ***************************************************/

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
  header('Location: login.php?next='.urlencode($next));
  exit;
}
include 'config.php';

/* ---------- DEBUG (optional: add &debug=1) ---------- */
if (isset($_GET['debug'])) {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

/* ---------- CONNECTION SHIM: ensure $conn (mysqli) ---------- */
if (!isset($conn) || !($conn instanceof mysqli)) {
  if (isset($mysqli) && $mysqli instanceof mysqli) {
    $conn = $mysqli;
  } else {
    $conn = new mysqli('localhost', 'root', '', 'invoices_db');
    if ($conn->connect_error) die('DB connection failed: '.$conn->connect_error);
    $conn->set_charset('utf8mb4');
  }
}

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function chf($n){ return number_format((float)$n, 2, ',', "'") . ' CHF'; }
function ymd(DateTimeImmutable $dt){ return $dt->format('Y-m-d'); }

/* ---------- Filters ---------- */
$q      = isset($_GET['q']) ? trim($_GET['q']) : '';
$range  = $_GET['range'] ?? 'all'; // default = ALL (no date filter)
$startI = $_GET['start'] ?? '';
$endI   = $_GET['end'] ?? '';
$today  = new DateTimeImmutable('today');

switch ($range) {
  case 'this-month':
    $s = $today->modify('first day of this month');
    $e = $today->modify('last day of this month');
    break;
  case 'last-month':
    $s = $today->modify('first day of last month');
    $e = $today->modify('last day of last month');
    break;
  case 'this-quarter':
    $m = (int)$today->format('n'); $y = (int)$today->format('Y');
    $qtr = intdiv($m-1, 3)+1; $sm = 1 + 3*($qtr-1); $em = $sm+2;
    $s = new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $sm));
    $e = (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $em)))->modify('last day of this month');
    break;
  case 'this-year':
    $y = (int)$today->format('Y');
    $s = new DateTimeImmutable("$y-01-01"); $e = new DateTimeImmutable("$y-12-31");
    break;
  case 'custom':
    $s = $startI ? new DateTimeImmutable($startI) : $today->modify('-10 years');
    $e = $endI   ? new DateTimeImmutable($endI)   : $today;
    break;
  case 'all':
  default:
    // placeholders; not used in WHERE for 'all'
    $s = $today->modify('-50 years');
    $e = $today->modify('+1 day');
    break;
}
$start_ymd = ymd($s);
$end_ymd   = ymd($e);

/* ---------- WHERE (NO date filter when range = 'all') ---------- */
$where  = [];
$params = [];
$types  = "";

if ($range !== 'all') {
  $where[] = "DATE(invoice_date) BETWEEN ? AND ?";
  $params[] = $start_ymd; $params[] = $end_ymd;
  $types   .= "ss";
}
if ($q !== '') {
  $where[] = "(customer_name LIKE ? OR invoice_number LIKE ? OR bill_to LIKE ?)";
  $like = "%$q%";
  array_push($params, $like, $like, $like);
  $types .= "sss";
}
$whereSql = $where ? "WHERE ".implode(" AND ", $where) : "";

/* ---------- Sorting & Pagination ---------- */
$allowedSort = ['id','customer_name','invoice_number','invoice_date','subtotal','vat_amount','total'];
$sort = in_array($_GET['sort'] ?? '', $allowedSort, true) ? $_GET['sort'] : 'invoice_date';
$dir  = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';

$per_page = max(5, min(100, (int)($_GET['per_page'] ?? 15)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page-1) * $per_page;

/* ---------- CSV Export (current filters/sort) ---------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $expSql = "SELECT customer_name, invoice_number, invoice_date, subtotal, vat_amount, total, amount_due
             FROM invoices $whereSql ORDER BY $sort $dir, id DESC";
  $exp = $conn->prepare($expSql);
  if ($types !== "") { $exp->bind_param($types, ...$params); }
  $exp->execute(); $rows = $exp->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=invoices_export.csv');
  $out = fopen('php://output', 'w');
  fprintf($out, "\xEF\xBB\xBF");
  fputcsv($out, ['Kunde','Rechnung Nr.','Datum','Netto','MWST','Total','Status'], ';');
  while ($r = $rows->fetch_assoc()) {
    $status = ($r['amount_due'] <= 0) ? 'Bezahlt' : 'Offen';
    fputcsv($out, [
      $r['customer_name'],
      $r['invoice_number'],
      $r['invoice_date'],
      number_format((float)$r['subtotal'], 2, ',', "'"),
      number_format((float)$r['vat_amount'], 2, ',', "'"),
      number_format((float)$r['total'], 2, ',', "'"),
      $status
    ], ';');
  }
  fclose($out);
  exit;
}

/* ---------- Dashboard aggregates ---------- */
$aggSql = "
  SELECT
    COALESCE(SUM(subtotal),0)   AS net_sum,
    COALESCE(SUM(vat_amount),0) AS vat_sum,
    COALESCE(SUM(total),0)      AS gross_sum,
    COALESCE(SUM(CASE WHEN amount_due<=0 THEN total ELSE 0 END),0) AS paid_sum,
    COALESCE(SUM(CASE WHEN amount_due>0  THEN total ELSE 0 END),0) AS unpaid_sum,
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

/* ---------- Main list ---------- */
$listSql = "
  SELECT id, customer_name, invoice_number, invoice_date, subtotal, vat_amount, total, amount_due
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

/* ---------- Per-customer totals (top 10) ---------- */
$custSql = "
  SELECT customer_name,
         COUNT(*) AS cnt,
         COALESCE(SUM(total),0)     AS gross_sum,
         COALESCE(SUM(subtotal),0)  AS net_sum,
         COALESCE(SUM(vat_amount),0) AS vat_sum
  FROM invoices
  $whereSql
  GROUP BY customer_name
  ORDER BY gross_sum DESC
  LIMIT 5
";
$cstmt = $conn->prepare($custSql);
if ($types !== "") { $cstmt->bind_param($types, ...$params); }
$cstmt->execute();
$cres = $cstmt->get_result();

/* ---------- VAT by quarter (selected year: if ALL, use current year) ---------- */
$year = ($range === 'all') ? (int)date('Y') : (int)$s->format('Y');
$qSql = "
  SELECT QUARTER(invoice_date) AS q,
         COALESCE(SUM(subtotal),0)   AS net_sum,
         COALESCE(SUM(vat_amount),0) AS vat_sum,
         COALESCE(SUM(total),0)      AS gross_sum
  FROM invoices
  WHERE YEAR(invoice_date)=?
  GROUP BY q
  ORDER BY q
";
$qStmt = $conn->prepare($qSql);
$qStmt->bind_param("i", $year);
$qStmt->execute();
$qres = [];
$qrset = $qStmt->get_result();
while ($row = $qrset->fetch_assoc()) $qres[(int)$row['q']] = $row;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Rechnungen – Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<style>
:root{
  --bg:#f5f7fb; --ink:#0f172a; --muted:#6b7280; --card:#ffffff; --line:#e5e7eb;
  --accent-50:#fef2f2; --accent-200:#fecaca; --accent-600:#dc2626; --accent-700:#b91c1c;
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
.menu .section-title{margin:12px 8px 6px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}

.content{padding:22px 26px}
.topbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:16px; background-color:var(--accent-600); color:white;padding:10px; border-radiu  s:10px;}
.title{font-size:20px;font-weight:700;margin:0}
.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{background:var(--accent-600);color:#fff;border:1px solid var(--accent-700);padding:10px 14px;border-radius:10px;font-weight:600;cursor:pointer}
.btn:hover{background:var(--accent-700)}
.burger{display:none;align-items:center;gap:6px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer}
.burger span{display:block;width:18px;height:2px;background:#111;position:relative}
.burger span::before,.burger span::after{content:"";position:absolute;left:0;width:18px;height:2px;background:#111}
.burger span::before{top:-6px} .burger span::after{top:6px}

.search{display:flex;gap:8px;background:#fff;border:1px solid var(--line);padding:6px 10px;border-radius:10px}
.search input,.search select{border:0;outline:0;font:inherit}
.date-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}

.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:14px 0 18px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:16px;box-shadow:0 4px 10px rgba(0,0,0,.04)}
.card .label{color:var(--muted);font-size:12px}
.card .value{font-size:22px;font-weight:800;margin-top:6px}
.tiny{font-size:12px;color:var(--muted)}

.panel{background:#fff;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;box-shadow:0 6px 14px rgba(0,0,0,.05);margin-bottom:16px}
.panel .hd{display:flex;justify-content:space-between;align-items:center;padding:12px;border-bottom:1px solid var(--line);background:#fff}

table{width:100%;border-collapse:collapse}
thead th{font-size:12px;color:#111;padding:12px;border-bottom:1px solid var(--line);text-align:left}
tbody td{padding:12px;border-bottom:1px solid var(--line);font-size:14px}
tbody tr:hover{background:rgba(239,68,68,.03)}
.right{text-align:right}
.badge{display:inline-flex;align-items:center;background:var(--accent-600);border:1px solid var(--accent-200);color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:600}
.status-paid{color:#16a34a;font-weight:700}
.status-unpaid{color:#dc2626;font-weight:700}
.pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;padding:12px}

/* Collapsible panels using <details> */
.panel.details { overflow:hidden; border-radius:var(--radius); }
.panel.details > summary{
  list-style:none; cursor:pointer; display:flex; justify-content:space-between; align-items:center;
  padding:12px; background:#fff; border-bottom:1px solid var(--line); outline:none;
}
.panel.details > summary::-webkit-details-marker{ display:none; }
.summary-left{ display:flex; gap:10px; align-items:center; }
.chev{ transition:transform .2s ease; display:inline-block; font-weight:800; }
details[open] .chev{ transform:rotate(180deg); }
.panel-body{ padding:0 0 8px 0; }
.panel-body > .wrap{ padding:0 12px 12px; }

/* ---------- Mobile / small screens ---------- */
@media (max-width: 900px){
  .layout{grid-template-columns:1fr}
  .sidebar{position:fixed;left:0;top:0;height:100dvh;width:var(--sidebar-w);transform:translateX(-100%);box-shadow:0 20px 40px rgba(0,0,0,.2)}
  .sidebar.open{transform:translateX(0)}
  .burger{display:flex}
  .content{padding:18px}
  .cards{grid-template-columns:1fr}
}
@media (max-width: 720px){
  .table-wrap{display:block}
  table{border:0}
  thead{display:none}
  tbody tr{
    display:block;background:#fff;border:1px solid var(--line);border-radius:12px;margin:10px 8px;padding:6px 8px;
  }
  tbody td{
    display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed var(--line);padding:10px 6px;
  }
  tbody td:last-child{border-bottom:0}
  tbody td::before{content:attr(data-label);font-weight:600;color:#111;margin-right:12px}
  .right{text-align:left}
  .panel .hd{padding:10px}
  .search{width:100%}
  .actions form{width:100%}
}
@media (max-width:720px){
  .mobile-hide { display: none; }
}

/* --- Header bar --- */
.topbar {
  background: #dc2626;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 12px;
  margin-bottom: 12px;
}

.menu-btn {
  background: #fff;
  color: #dc2626;
  border: none;
  border-radius: 6px;
  font-size: 20px;
  padding: 4px 10px;
  cursor: pointer;
}

.dashboard-title {
  font-size: 20px;
  margin: 0;
}

/* --- Filter section --- */
.filter-section {
  margin-top: 8px;
  width: 100%;
}

.filter-form {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 12px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.ff-row { display: grid; gap: 8px; }
.ff-row-inline { grid-template-columns: 220px 1fr 1fr; align-items: center; }
.ff-actions { grid-template-columns: auto auto; gap: 8px; }

/* --- Buttons & inputs --- */
.filter-form .form-control,
.filter-form .form-select { min-height: 34px; }

.btn {
  background: #dc2626;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 6px 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn:hover { background: #b91c1c; }

/* --- Responsive --- */
@media (max-width: 768px) {
  .ff-row-inline { grid-template-columns: 1fr; }
  .ff-actions { grid-template-columns: 1fr; }
  .ff-actions .btn { width: 100%; }
  .topbar { flex-direction: row; justify-content: space-between; }
}


/* Toolbar */
.toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content:space-between;
  gap: 8px;
  margin-bottom: 0px;
  border-radius: 20px;
}
.toolbar .btn {
  background: #ffffffff;
  color: #000000ff;
  border: none;
  border-radius: 20px;
  padding: 10px 14px;
  font-weight: 900;
  cursor: pointer;
  font-size:15px;
}
.toolbar .btn:hover {
  background: #b91c1c;
  color:white;
}

/* Modal base */
.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.55);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.modal-content {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.25);
  animation: fadeIn 0.3s ease;
}
.modal-content h3 {
  margin-top: 0;
  color: #dc2626;
  text-align: center;
}
.modal-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.modal-form .form-control,
.modal-form .form-select {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 8px 10px;
}
.modal-actions {
  display: flex;
  justify-content: space-between;
  margin-top: 14px;
}
.btn.primary {
  background: #dc2626;
  color: #fff;
}
.btn.secondary {
  background: #eee;
  color: #333;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

</style>
</head>
<body>
<div class="layout">
  <!-- Sidebar -->
  <!-- <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="logo"></div><h1>Krasniqi – Admin</h1>
    </div>
    <nav class="menu">
      <div class="section-title">Navigation</div>
      <a href="index.php" class="active">Dashboard</a>
      <a href="create.php">+ Neue Rechnung</a>
      <a href="#" title="In Arbeit">Berichte</a>
      <a href="#" title="In Arbeit">Einstellungen</a>
      <a href="users.php">Benutzer</a>
    </nav>
  </aside> -->
<?php include __DIR__.'/includes/sidebar.php'; ?>

  <!-- Main -->
  <main class="content w-100">
    <div class="topbar xs-100 ">
      <button class="burger" type="button" aria-label="Menü" onclick="toggleSidebar()"><span></span></button>
      <h2 class="title">Dashboard</h2>
      
   <div class="actions w-100">


<!-- Filters section -->
<div class="toolbar">
  <button type="button" class="btn btn-sm" onclick="openFilterModal()">🔍 Filter</button>
  <a href="create.php" class="btn btn-sm">+ Neue Rechnung</a>
</div>



       

      </div>
    </div>

    <!-- Cards -->
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
  <span class="tiny">bezahlt</span> •
  <span class="status-unpaid" id="unpaidSum"><?= chf($tot['unpaid_sum']) ?></span>
  <span class="tiny">offen</span>
</div>

      </div>
    </section>

   <!-- Invoices table -->
<section class="panel">
  <div class="hd">
    <div class="tiny">Rechnungen (<?= $total_rows ?>)</div>
    <div class="tiny">Seite <?= $page ?>/<?= $total_pages ?></div>
  </div>
  <div class="table-wrap" style="overflow-x:auto">
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
          <th>Status</th>
          <th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <?php
          // detect mobile
          $isMobile = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(android|iphone|mobile|ipad)/i', $_SERVER['HTTP_USER_AGENT']);
          $limitRows = $isMobile ? 5 : $per_page;

          // custom query limiting last 5 for mobile
          $listSql = "
            SELECT id, customer_name, invoice_number, invoice_date, subtotal, vat_amount, total, amount_due
            FROM invoices
            $whereSql
            ORDER BY invoice_date DESC
            LIMIT ? OFFSET ?
          ";
          $listTypes  = $types . "ii";
          $listParams = array_merge($params, [$limitRows, $offset]);
          $listStmt   = $conn->prepare($listSql);
          $listStmt->bind_param($listTypes, ...$listParams);
          $listStmt->execute();
          $res = $listStmt->get_result();

          if ($res->num_rows === 0):
        ?>
          <tr><td colspan="9" class="tiny" style="text-align:center;color:var(--muted);padding:18px">Keine Rechnungen gefunden.</td></tr>
        <?php else: ?>
          <?php while ($row = $res->fetch_assoc()): ?>
            <?php $paid = ($row['amount_due'] <= 0); ?>
            <tr>
              <td data-label="ID" class="mobile-hide">#<?= (int)$row['id'] ?></td>
              <td data-label="Kunde"><?= h($row['customer_name'] ?: '—') ?></td>
              <td data-label="Rechnung Nr."><span class="badge"><?= h($row['invoice_number'] ?: '—') ?></span></td>
              <td data-label="Datum" class=""><?= h($row['invoice_date']) ?></td> 
               <td data-label="Status" class="mobile-hide"><?= $paid ? '<span class="status-paid">Bezahlt</span>' : '<span class="status-unpaid">Offen</span>' ?></td>
              <td data-label="Netto" class="right mobile-hide"><?= chf($row['subtotal']) ?></td>
              <td data-label="MWST" class="right mobile-hide"><?= chf($row['vat_amount']) ?></td>
              <td data-label="Total" class="right mobile-hide"><strong><?= chf($row['total']) ?></strong></td>
           
              <td data-label="Aktionen">
                <a href="edit.php?id=<?= (int)$row['id'] ?>">Bearbeiten</a> |
                <a href="edit.php?id=<?= (int)$row['id'] ?>&autopdf=1" target="_blank">PDF</a> |
                <!-- <a href="delete.php?id=<?= (int)$row['id'] ?>" onclick="return confirm('Diese Rechnung wirklich löschen?')">Löschen</a> -->
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if(!$isMobile && $total_pages > 1): ?>
  <div class="pager">
    <?php
      $qs = $_GET;
      $prev = max(1, $page-1); $next = min($total_pages, $page+1);
      $qs['page']=1;             echo '<a class="btn" href="?'.h(http_build_query($qs)).'">« Erste</a> ';
      $qs['page']=$prev;         echo '<a class="btn" href="?'.h(http_build_query($qs)).'">‹ Zurück</a> ';
      $qs['page']=$next;         echo '<a class="btn" href="?'.h(http_build_query($qs)).'">Weiter ›</a> ';
      $qs['page']=$total_pages;  echo '<a class="btn" href="?'.h(http_build_query($qs)).'">Letzte »</a>';
    ?>
  </div>
  <?php endif; ?>
</section>

<details class="panel details" id="panel-contracts" <?= isset($_COOKIE['panel-contracts']) && $_COOKIE['panel-contracts']==='closed' ? '' : 'open' ?>>
  <summary>
    <div class="summary-left">
      <strong>Offerten</strong>
      <span class="tiny">Gespeicherte Kundenofferten</span>
    </div>
    <span class="chev">▾</span>
  </summary>
  <div class="panel-body">
    <div class="wrap" style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Kunde</th>
            <th>Adresse</th>
            <th>Vertrags-Nr.</th>
            <th>Datum</th>
            <th class="right">Total</th>
            <th>Zahlungsbedingungen</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $resContracts = $conn->query("SELECT * FROM contracts ORDER BY contract_date DESC LIMIT 20");
          if ($resContracts->num_rows === 0): ?>
            <tr><td colspan="8" class="tiny" style="text-align:center;color:var(--muted);padding:18px">Keine Verträge gefunden.</td></tr>
          <?php else: 
            while ($c = $resContracts->fetch_assoc()): ?>
              <tr>
                <td>#<?= (int)$c['id'] ?></td>
                <td><?= h($c['customer_name'] ?: '—') ?></td>
                <td><?= h($c['customer_address'] ?: '—') ?></td>
                <td><?= h($c['contract_number'] ?: '—') ?></td>
                <td><?= h($c['contract_date'] ?: '—') ?></td>
                <td class="right"><?= number_format((float)$c['total_amount'], 2, ',', "'") ?> CHF</td>
                <td><?= h($c['payment_terms'] ?: '—') ?></td>
                <td>
                  <a href="vertrag_form.php?id=<?= (int)$c['id'] ?>">Bearbeiten</a> |
                  <a href="vertrag_form.php?id=<?= (int)$c['id'] ?>&autopdf=1" target="_blank">PDF</a>
                </td>
              </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</details>
    <!-- Contracts (collapsible) -->
<details class="panel details" id="panel-contracts" <?= isset($_COOKIE['panel-contracts']) && $_COOKIE['panel-contracts']==='closed' ? '' : 'open' ?>>
  <summary>
    <div class="summary-left">
      <strong>Verträge (nach Datum)</strong>
      <span class="tiny">Gespeicherte Kundenverträge</span>
    </div>
    <span class="chev">▾</span>
  </summary>
  <div class="panel-body">
    <div class="wrap" style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Kunde</th>
            <th>Adresse</th>
            <th>Vertrags-Nr.</th>
            <th>Datum</th>
            <th class="right">Total</th>
            <th>Zahlungsbedingungen</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $resContracts = $conn->query("SELECT * FROM contracts ORDER BY contract_date DESC LIMIT 20");
          if ($resContracts->num_rows === 0): ?>
            <tr><td colspan="8" class="tiny" style="text-align:center;color:var(--muted);padding:18px">Keine Verträge gefunden.</td></tr>
          <?php else: 
            while ($c = $resContracts->fetch_assoc()): ?>
              <tr>
                <td>#<?= (int)$c['id'] ?></td>
                <td><?= h($c['customer_name'] ?: '—') ?></td>
                <td><?= h($c['customer_address'] ?: '—') ?></td>
                <td><?= h($c['contract_number'] ?: '—') ?></td>
                <td><?= h($c['contract_date'] ?: '—') ?></td>
                <td class="right"><?= number_format((float)$c['total_amount'], 2, ',', "'") ?> CHF</td>
                <td><?= h($c['payment_terms'] ?: '—') ?></td>
                <td>
                  <a href="vertrag_form.php?id=<?= (int)$c['id'] ?>">Bearbeiten</a> |
                  <a href="vertrag_form.php?id=<?= (int)$c['id'] ?>&autopdf=1" target="_blank">PDF</a>
                </td>
              </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</details>

    <!-- Top customers (collapsible) -->
    <details class="panel details" id="panel-customers" <?= isset($_COOKIE['panel-customers']) && $_COOKIE['panel-customers']==='closed' ? '' : 'open' ?>>
      <summary>
        <div class="summary-left">
          <strong>Top Kunden (nach Total)</strong>
          <span class="tiny">Zeitraum &amp; Filter angewandt</span>
        </div>
        <span class="chev">▾</span>
      </summary>
      <div class="panel-body">
        <div class="wrap" style="overflow-x:auto">
          <table>
            <thead>
              <tr><th>Kunde</th><th class="right">Anzahl</th><th class="right">Netto</th><th class="right">MWST</th><th class="right">Brutto</th></tr>
            </thead>
            <tbody>
              <?php if ($cres->num_rows === 0): ?>
                <tr><td colspan="5" class="tiny" style="text-align:center;color:var(--muted);padding:18px">Keine Daten.</td></tr>
              <?php else: ?>
                <?php while ($c = $cres->fetch_assoc()): ?>
                  <tr>
                    <td data-label="Kunde"><?= h($c['customer_name'] ?: '—') ?></td>
                    <td data-label="Anzahl" class="right"><?= (int)$c['cnt'] ?></td>
                    <td data-label="Netto" class="right"><?= chf($c['net_sum']) ?></td>
                    <td data-label="MWST" class="right"><?= chf($c['vat_sum']) ?></td>
                    <td data-label="Brutto" class="right"><strong><?= chf($c['gross_sum']) ?></strong></td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </details>

    <!-- VAT by quarter (collapsible) -->
    <details class="panel details" id="panel-vat" <?= isset($_COOKIE['panel-vat']) && $_COOKIE['panel-vat']==='closed' ? '' : 'open' ?>>
      <summary>
        <div class="summary-left">
          <strong>MWST-Übersicht nach Quartal (<?= (int)$year ?>)</strong>
          <span class="tiny">Satz: <?= number_format($vat_rate,1,',','') ?>%</span>
        </div>
        <span class="chev">▾</span>
      </summary>
      <div class="panel-body">
        <div class="wrap" style="overflow-x:auto">
          <table>
            <thead>
              <tr><th>Quartal</th><th class="right">Netto</th><th class="right">Berechnete MWST</th><th class="right">Gespeicherte MWST</th><th class="right">Brutto</th></tr>
            </thead>
            <tbody>
              <?php for ($qi=1;$qi<=4;$qi++):
                $qr  = $qres[$qi] ?? ['net_sum'=>0,'vat_sum'=>0,'gross_sum'=>0];
                $calc = $qr['net_sum'] * ($vat_rate/100);
              ?>
                <tr>
                  <td data-label="Quartal">Q<?= $qi ?></td>
                  <td data-label="Netto" class="right"><?= chf($qr['net_sum']) ?></td>
                  <td data-label="Berechnete MWST" class="right"><?= chf($calc) ?></td>
                  <td data-label="Gespeicherte MWST" class="right"><?= chf($qr['vat_sum']) ?></td>
                  <td data-label="Brutto" class="right"><strong><?= chf($qr['gross_sum']) ?></strong></td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </div>
    </details>
  </main>
</div>

<script>
function onRangeChange(val){
  const s = document.getElementById('start'), e = document.getElementById('end');
  if (val === 'custom'){ s.disabled = false; e.disabled = false; }
  else { s.disabled = true; e.disabled = true; }
}
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('open'); }
// Close sidebar on outside tap (mobile)
document.addEventListener('click', (ev)=>{
  const sb = document.getElementById('sidebar');
  const burger = ev.target.closest('.burger');
  if (!sb) return;
  if (window.matchMedia('(max-width: 900px)').matches){
    if (!burger && !ev.target.closest('#sidebar') && sb.classList.contains('open')){
      sb.classList.remove('open');
    }
  }
});

// Persist open/closed state for collapsibles
for (const d of document.querySelectorAll('details.panel.details')) {
  d.addEventListener('toggle', () => {
    const id = d.id || '';
    if (!id) return;
    document.cookie = `${id}=${d.open ? 'open' : 'closed'}; path=/; max-age=${60*60*24*30}`;
  });
}document.querySelectorAll('.status-select').forEach(sel=>{
  sel.addEventListener('change', ()=>{
    const id = sel.dataset.id;
    const status = sel.value;

    fetch('update_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(id)}&status=${encodeURIComponent(status)}`
    })
    .then(res => res.json())
    .then(data => {
      // Visual feedback for dropdown
      sel.style.borderColor = (status === '1') ? '#16a34a' : '#dc2626';

      // ✅ update dashboard totals live
      if (data.paid_sum !== undefined && data.unpaid_sum !== undefined) {
        document.getElementById('paidSum').textContent = data.paid_sum;
        document.getElementById('unpaidSum').textContent = data.unpaid_sum;
      }
    })
    .catch(err => console.error(err));
  });
});


function openFilterModal() {
  document.getElementById('filterModal').style.display = 'flex';
}
function closeFilterModal() {
  document.getElementById('filterModal').style.display = 'none';
}
function toggleDates(val) {
  const dr = document.getElementById('dateRange');
  dr.style.display = (val === 'custom') ? 'flex' : 'none';
}
window.addEventListener('click', e => {
  const modal = document.getElementById('filterModal');
  if (e.target === modal) closeFilterModal();
});


</script>
<div id="filterModal" class="modal-overlay">
  <div class="modal-content">
    <h3>Filter Rechnungen</h3>

    <form method="get" action="index.php" class="modal-form">
      <input type="text"
             name="q"
             value="<?= h($q) ?>"
             placeholder="Suche: Kunde, Nr, Adresse …"
             class="form-control"/>

      <select name="status" class="form-select">
        <option value="">Status: Alle</option>
        <option value="bezahlt" <?= ($_GET['status']??'')==='bezahlt'?'selected':'' ?>>Bezahlt</option>
        <option value="offen" <?= ($_GET['status']??'')==='offen'?'selected':'' ?>>Offen</option>
      </select>

      <select name="range" id="range" class="form-select" onchange="toggleDates(this.value)">
        <option value="this-month"   <?= $range==='this-month'?'selected':'' ?>>Diesen Monat</option>
        <option value="last-month"   <?= $range==='last-month'?'selected':'' ?>>Letzter Monat</option>
        <option value="this-quarter" <?= $range==='this-quarter'?'selected':'' ?>>Dieses Quartal</option>
        <option value="this-year"    <?= $range==='this-year'?'selected':'' ?>>Dieses Jahr</option>
        <option value="custom"       <?= $range==='custom'?'selected':'' ?>>Benutzerdefiniert</option>
        <option value="all"          <?= $range==='all'?'selected':'' ?>>Alle</option>
      </select>

      <div id="dateRange" class="d-flex gap-2" style="<?= $range==='custom'?'':'display:none;' ?>">
        <input type="date" name="start" value="<?= h($start_ymd ?? '') ?>" class="form-control">
        <input type="date" name="end" value="<?= h($end_ymd ?? '') ?>" class="form-control">
      </div>

      <div class="modal-actions">
        <button type="submit" class="btn primary">Filtern</button>
        <button type="button" class="btn secondary" onclick="closeFilterModal()">Schließen</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
