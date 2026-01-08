<?php
/***************************************************
 * INVOICE (Red theme) — With Sidebar + Header (Responsive + Quick-Edit)
 * - Same DB + save logic
 * - Sidebar include kept: include __DIR__.'/includes/sidebar.php'
 * - Sticky toolbar; fixed FAB bottom-right
 * - Dark gray gradient quick-edit panel (Rechnung an, Rechnungsdaten, Bemerkung)
 * - Only .doc scrolls; mobile-friendly width & controls
 ***************************************************/

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
  header('Location: login.php?next='.urlencode($next));
  exit;
}

/* ---- DB ---- */
$mysqli = new mysqli('localhost', 'root', '', 'invoices_db');
if ($mysqli->connect_error) die('DB connection failed: '.$mysqli->connect_error);
$mysqli->set_charset('utf8mb4');

/* ---- Helpers ---- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---- Load company profile ---- */
$company = null;
if ($resC = $mysqli->query("SELECT * FROM company_profile ORDER BY id DESC LIMIT 1")) {
  if ($resC->num_rows) $company = $resC->fetch_assoc();
}

/* ---- Load invoice ---- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = null;
if ($id > 0) {
  $res = $mysqli->query("SELECT * FROM invoices WHERE id=$id");
  if ($res && $res->num_rows) $row = $res->fetch_assoc();
}

/* ---- Save (supports AJAX via ajax=1) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id           = (int)($_POST['id'] ?? 0);
  $customer     = $mysqli->real_escape_string($_POST['customer_name'] ?? '');
  $bill_to      = $mysqli->real_escape_string($_POST['bill_to'] ?? '');
  $inv_no       = $mysqli->real_escape_string($_POST['invoice_number'] ?? '');
  $inv_date     = $mysqli->real_escape_string($_POST['invoice_date'] ?? date('Y-m-d'));
  $ref_text     = $mysqli->real_escape_string($_POST['ref_text'] ?? '');
  $vat_rate     = (float)($_POST['vat_rate'] ?? 0);
  $subtotal     = (float)($_POST['subtotal'] ?? 0);
  $vat_amount   = (float)($_POST['vat_amount'] ?? 0);
  $total        = (float)($_POST['total'] ?? 0);
  $amount_due   = (float)($_POST['amount_due'] ?? 0);
  $remark       = $mysqli->real_escape_string($_POST['remark'] ?? '');
  $items_json   = $mysqli->real_escape_string($_POST['items_json'] ?? '[]');
  $lump_sum     = (int)($_POST['lump_sum'] ?? 0);
  $isAjax       = !empty($_POST['ajax']) && $_POST['ajax']==='1';

  if ($id > 0) {
    $sql = "UPDATE invoices SET
      customer_name='$customer', bill_to='$bill_to',
      invoice_number='$inv_no', invoice_date='$inv_date',
      ref_text='$ref_text', vat_rate=$vat_rate,
      subtotal=$subtotal, vat_amount=$vat_amount,
      total=$total, amount_due=$amount_due,
      remark='$remark', items_json='$items_json',
      lump_sum=$lump_sum
    WHERE id=$id";
  } else {
    $sql = "INSERT INTO invoices
      (customer_name,bill_to,invoice_number,invoice_date,ref_text,vat_rate,
       subtotal,vat_amount,total,amount_due,remark,items_json,lump_sum)
      VALUES
      ('$customer','$bill_to','$inv_no','$inv_date','$ref_text',$vat_rate,
       $subtotal,$vat_amount,$total,$amount_due,'$remark','$items_json',$lump_sum)";
  }

  if ($mysqli->query($sql)) {
    $newId = $id ?: $mysqli->insert_id;
    if ($isAjax) {
      header('Content-Type: application/json');
      echo json_encode(['ok'=>true,'id'=>$newId]);
      exit;
    }
    header("Location: invoice_form.php?id=".$newId."&saved=1");
    exit;
  } else {
    if ($isAjax) {
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'error'=>$mysqli->error]);
      exit;
    }
    header("Location: invoice_form.php".($id?("?id=".$id):"")."&error=".urlencode($mysqli->error));
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8" />
<title>Rechnung – Bearbeiten</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>

<!-- Export libs -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<style>
:root{
  --bg:#f5f7fb; --ink:#0f172a; --muted:#6b7280; --card:#ffffff; --line:#e5e7eb;
  --accent-50:#fef2f2; --accent-100:#fee2e2; --accent-200:#fecaca; --accent-300:#fca5a5;
  --accent-400:#f87171; --accent-500:#ef4444; --accent-600:#dc2626; --accent-700:#b91c1c;
  --accent-800:#991b1b; --accent-900:#7f1d1d; --surface:#fff7f7; --black:#000;
  --radius:14px; --sidebar-w:260px;
  --pad:10mm; --doc-w:210mm; /* A4 width */
}

/* App layout */
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
a{color:var(--accent-700);text-decoration:none} a:hover{text-decoration:underline}
.layout{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh}
.sidebar{background:#fff;border-right:1px solid var(--line);padding:18px;position:sticky;top:0;height:100dvh;z-index:20}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.brand .logo{width:150px;height:50px;object-fit:contain}
.brand h1{font-size:16px;margin:0}
.menu a{display:flex;gap:10px;padding:10px 12px;border-radius:10px;color:#111;font-weight:500}
.menu a:hover{background:var(--accent-50)} .menu a.active{background:var(--accent-600);color:#fff}
.menu .section-title{margin:12px 8px 6px;font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.04em}

/* Main */
.content{display:flex;flex-direction:column;min-height:100dvh;padding:0}
.topbar{
  position:sticky; top:0; z-index:15;
  display:flex;gap:10px;align-items:center;justify-content:space-between;
  background:var(--accent-600);padding:10px 14px;border-radius:0 0 12px 12px;
}
.title{font-size:18px;font-weight:800;margin:0;color:white;}
.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{background:#fff;color:#111;border:1px solid var(--line);padding:10px 14px;border-radius:10px;font-weight:700;cursor:pointer}
.btn:hover{background:#f8fafc}
.btn.primary{background:var(--accent-600);color:#fff;border-color:var(--accent-700)}
.btn.primary:hover{background:var(--accent-700)}

/* Burger (mobile sidebar toggle) */
.burger{
  display:none; gap:8px; align-items:center;
  background:#fff; border:1px solid var(--line); border-radius:10px; padding:8px 10px; font-weight:700;
}
@media (max-width: 900px){ .burger{ display:inline-flex; } }

/* Work area: sticky toolbar + scroll only the doc */
.toolbar{
  position:sticky; top:56px; /* below topbar */
  z-index:12;
  width:var(--doc-w); max-width:calc(100% - 20px); margin:8px auto 8px;
  display:flex; justify-content:center; gap:8px; flex-wrap:wrap;
  background:#fff; padding:6px var(--pad);
  border:1px solid var(--accent-200); border-bottom:none;
  border-radius:8px 8px 0 0;
  box-shadow:0 4px 10px rgba(0,0,0,.05);
}

/* Container that controls scrolling */
.workarea{
  display:flex; flex-direction:column; gap:0;
  min-height:0; /* allow child to size */
  padding:0 16px 84px; /* bottom space for FAB */
}
.doc-scroll{
  width:100%;
  max-width:calc(var(--doc-w) + 0px);
  margin:0 auto;
  /* Only this scrolls */
  overflow:auto;
  /* Height: viewport minus topbar (~56) minus toolbar (~70) minus margins */
  height:calc(100dvh - 56px - 72px - 20px);
  background:transparent;
}

/* Export wrapper + page */
#exportWrap{width:var(--doc-w);margin:0 auto;background:#fff;box-shadow:0 8px 20px rgba(0,0,0,.07);}
.doc{width:var(--doc-w);min-height:297mm;background:#fff;position:relative;overflow:hidden;padding-bottom:25mm;page-break-after:always}

/* Sections */
.section{padding:8mm var(--pad)} .section + .section{padding-top:6mm}

/* Invoice header */
.header{display:flex;justify-content:space-between;gap:14px;border-bottom:1px solid var(--accent-200);padding-bottom:6mm;background:linear-gradient(0deg,#fff,#fff),linear-gradient(180deg,#ef4444 0%,#ffffff 100%); background-blend-mode:normal; border-top-left-radius:12px;border-top-right-radius:12px}
.header .brand{display:flex;gap:12px;align-items:flex-start}
.company-block{font-size:12px;line-height:1.4;text-align:right}

/* Grid/boxes */
.grid-two{display:grid;grid-template-columns:1fr 1fr;column-gap:14px;align-items:start}
.box{padding:10px;background:#fff;box-shadow:0 1px 0 rgba(0,0,0,.02);break-inside:avoid;page-break-inside:avoid}
.small{font-size:12px;color:var(--muted)}
h2{margin:0 0 4px;font-size:15px;color:#111}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px}
.meta div{display:flex;gap:8px;border-bottom:1px dashed var(--line);padding:4px 0}

/* Table */
table{width:100%;border-collapse:collapse;font-size:12px}
thead th{font-weight:600;color:#111;border-bottom:1px solid var(--accent-900);padding:6px 8px;text-align:left}
tbody td{padding:6px 8px;border-bottom:1px solid var(--line);vertical-align:top}
tbody tr:nth-child(odd) td{background:rgba(239,68,68,0.02)}
tfoot td{padding:5px 8px}
tfoot tr.total td{border-top:2px solid #111;font-weight:700}
.right{text-align:right} .muted{color:var(--muted)}
[contenteditable="true"]:hover{outline:1px dashed #cbd5e1}

/* Inline inputs */
#items tbody td input{
  box-sizing:border-box;border:none;border-bottom:1px solid #0000005d;height:22px;padding:2px 8px;line-height:20px;vertical-align:middle;font-variant-numeric:lining-nums tabular-nums;
}
#items tbody td:nth-child(3) input, #items tbody td:nth-child(5) input { text-align:right; }

/* Group rows */
tbody tr.group-row td{
  background:#fff !important;border-top:1px solid #000;border-bottom:0;font-weight:700;color:#111;padding:10px 8px 4px;
}
tbody tr.group-row:first-child td{border-top:0}
.row-actions{float:right;margin-left:8px;font-weight:400}

/* Lump-sum: hide Menge/Einheit/Preis/ZwSum (3..6) */
#items.lumpsum thead th:nth-child(3),
#items.lumpsum thead th:nth-child(4),
#items.lumpsum thead th:nth-child(5),
#items.lumpsum thead th:nth-child(6),
#items.lumpsum tbody td:nth-child(3),
#items.lumpsum tbody td:nth-child(4),
#items.lumpsum tbody td:nth-child(5),
#items.lumpsum tbody td:nth-child(6){display:none}

/* Bemerkung placeholder + export-hiding */
#remarkText:empty::before { content: attr(data-placeholder); color:#94a3b8; font-style:italic; }
body.exporting .hide-when-export { display:none !important; }

/* Footer (only on LAST page in export) */
.footer{position:absolute;bottom:10mm;left:var(--pad);right:var(--pad);border-top:1px solid #000;padding-top:6mm}

/* QR page */
.page-break{break-before:page}
.qr-page{display:flex;flex-direction:column;min-height:297mm;padding:8mm var(--pad)}
.qr-bottom{margin-top:auto}

/* Hide UI when exporting/printing */
body.exporting .toolbar,.exporting .actions,.exporting .btn,.exporting .btn-mini,.exporting .no-print,.exporting .burger{display:none !important;}
body.exporting #items tbody tr td:nth-child(3) input,
body.exporting #items tbody tr td:nth-child(4) input,
body.exporting #items tbody tr td:nth-child(5) input{border:0 !important;outline:0 !important;background:transparent !important;box-shadow:none !important;}
body.exporting .doc{height:297mm !important; overflow:hidden !important}

/* Print */
@page{size:A4;margin:0;padding:0}
@media print{
  html,body{margin:0;background:#fff !important;width:210mm;height:297mm}
  .sidebar,.topbar,.toolbar,.btn,.actions,.no-print,.burger{display:none !important}
  #exportWrap{width:210mm !important;margin:0 !important;box-shadow:none !important}
  tbody tr:nth-child(odd) td{background:transparent !important}
  input{border:0 !important;background:transparent !important;padding:0 !important;width:auto}
  a[href]:after{content:""}
  .footer{bottom:10mm}
}

/* Buttons inside items section (rounded) */
.actions .btn{
  border: 1px solid var(--accent-300); background: #fff; color: var(--accent-700);
  font-weight: 600; padding: 8px 14px; border-radius: 999px; cursor: pointer;
  box-shadow: 0 1px 0 rgba(0,0,0,.04); transition: background .15s ease, box-shadow .15s ease, transform .02s ease;
}
.actions .btn:hover{background: var(--accent-50);}
.actions .btn:active{transform: translateY(1px);}
#lumpBtn{ border-color: var(--accent-600); color: var(--accent-700); }
#lumpBtn.active{
  background: linear-gradient(180deg, var(--accent-600), var(--accent-700));
  color: #fff; border-color: var(--accent-700); box-shadow: 0 2px 8px rgba(220,38,38,.25);
}
.actions .btn:nth-child(3){ border-style: dashed; }
.btn-mini{ border:1px solid var(--accent-300); background:#fff; color:var(--accent-700); padding:2px 8px; border-radius:999px; }
.btn-mini:hover{ background: var(--accent-50); }

/* Toast + Loader */
.toast{
  position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
  background:#111; color:#fff; padding:14px 18px; border-radius:12px; z-index:9999;
  box-shadow:0 12px 30px rgba(0,0,0,.25); display:none; max-width:80%; text-align:center;
}
.toast.error{background:#b91c1c}
.toast.show{display:block; animation:fade .25s ease}
@keyframes fade{from{opacity:0; transform:translate(-50%,-56%)} to{opacity:1; transform:translate(-50%,-50%)}}
.loader{
  position:fixed; inset:0; background:rgba(255,255,255,.65); z-index:9998; display:none;
  backdrop-filter: blur(2px);
}
.loader.show{display:block}
.loader:after{
  content:""; position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
  width:46px; height:46px; border:4px solid #e5e7eb; border-top-color:#ef4444; border-radius:999px; animation:spin .9s linear infinite;
}
@keyframes spin{to{transform:translate(-50%,-50%) rotate(360deg)}}

/* =========================
   Quick-Edit Floating Panel
   ========================= */
.qe-fab{
  position:fixed; right:16px; bottom:16px; /* always bottom-right */
  z-index:9996; background:#111827; color:#fff; border:1px solid #374151;
  padding:12px 14px; border-radius:12px; cursor:pointer; box-shadow:0 8px 24px rgba(0,0,0,.35);
}
.qe-fab:hover{ background:#0f172a; }

.qe-backdrop{
  position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9994; display:none;
}
.qe-backdrop.show{ display:block; }
body.exporting .qe-backdrop, body.exporting .qe-fab, body.exporting .qe-panel{ display:none !important; }

.qe-panel{
  position:fixed; top:0; right:0; height:100dvh; width:380px; max-width:92vw;
  z-index:9995;
  color:#fff; background:linear-gradient(180deg,#1f2937 0%, #111827 100%); /* gray gradient */
  box-shadow:-12px 0 30px rgba(0,0,0,.35);
  transform:translateX(100%); transition:transform .25s ease;
  display:flex; flex-direction:column;
}
.qe-panel.open{ transform:translateX(0); }
.qe-head{
  padding:14px 16px; display:flex; align-items:center; justify-content:space-between;
  border-bottom:1px solid #4b5563; background:rgba(0,0,0,.15);
}
.qe-title{ margin:0; font-size:16px; font-weight:700; }
.qe-close{ background:#111827; color:#fff; border:1px solid #4b5563; padding:6px 10px; border-radius:8px; cursor:pointer; }
.qe-body{ padding:14px 16px; overflow:auto; }
.qe-body label{ display:block; font-size:12px; color:#e5e7eb; margin:12px 0 6px; }
.qe-body input[type="text"],
.qe-body input[type="number"],
.qe-body input[type="date"],
.qe-body textarea{
  width:100%; border:1px solid #4b5563; background:#111827; color:#fff; border-radius:8px; padding:8px 10px; outline:none;
}
.qe-help{ font-size:12px; color:#9ca3af; margin-top:10px;}
.qe-section{ margin-bottom:18px; }

/* ==============
   Mobile tuning
   ============== */
@media (max-width: 900px){
  :root{ --pad:6mm; }
  .layout{ grid-template-columns:1fr; }
  .sidebar{ position:fixed; left:0; top:0; bottom:0; width:min(86vw, 320px); transform:translateX(-100%); transition:transform .2s ease; z-index:30; }
  .sidebar.open{ transform:translateX(0); }
  .topbar{ border-radius:0; }
  .toolbar{ top:56px; padding:6px 8px; }
  .workarea{ padding:0 10px 84px; }
  /* Make the page fit screens: use 100% width of container */
  #exportWrap, .doc{ width:100%; }
  /* Reduce paddings inside sections for compact view */
  .section{ padding:6mm var(--pad); }
  .grid-two{ grid-template-columns:1fr; row-gap:10px; }
}
</style>
</head>
<body>
<div class="layout">
  <!-- Sidebar -->
  <?php include __DIR__.'/includes/sidebar.php'; ?>

  <!-- Main content -->
  <main class="content">
    <!-- App header -->
    <div class="topbar">
      <button class="burger no-print" id="burgerBtn">☰ Menü</button>
      <h2 class="title">Rechnung <?= $id ? '#'.(int)$id : '(neu)' ?></h2>
      <div class="actions">
        <a class="btn secondary" href="index.php">‹ Zur Übersicht</a>
        <button class="btn" type="button" onclick="saveInvoice()">💾 Speichern</button>
        <button class="btn primary" type="button" onclick="downloadPDF()">📄 PDF herunterladen</button>
      </div>
    </div>

    <!-- Toolbar (doc width, sticky) -->
    <div class="toolbar">
      <form method="post" id="saveForm">
        <input type="hidden" name="id" value="<?= h($row['id'] ?? '') ?>">
        <input type="hidden" name="customer_name" id="customer_name">
        <input type="hidden" name="bill_to" id="bill_to_input">
        <input type="hidden" name="invoice_number" id="invoice_number_input">
        <input type="hidden" name="invoice_date" id="invoice_date_input">
        <input type="hidden" name="ref_text" id="ref_text_input">
        <input type="hidden" name="vat_rate" id="vat_rate_input">
        <input type="hidden" name="subtotal" id="subtotal_input">
        <input type="hidden" name="vat_amount" id="vat_amount_input">
        <input type="hidden" name="total" id="total_input">
        <input type="hidden" name="amount_due" id="amount_due_input">
        <input type="hidden" name="remark" id="remark_input">
        <input type="hidden" name="items_json" id="items_json_input">
        <input type="hidden" name="lump_sum" id="lump_sum_input">
        <button type="button" class="btn" onclick="saveInvoice()">💾 Speichern</button>
      </form>
      <button class="btn primary" onclick="downloadPDF()">📄 PDF herunterladen</button>
    </div>

    <!-- Only this area scrolls -->
    <div class="workarea">
      <div class="doc-scroll" id="docScroll">
        <div id="exportWrap">
          <!-- Invoice page (screen); export paginates clones -->
          <main class="doc" id="doc">
            <!-- Header -->
            <section class="section header">
              <div class="brand">
                <div class="logo" style="width:auto;height:auto;">
                  <?php if (!empty($company['logo_path'])): ?>
                    <img src="<?= h($company['logo_path']) ?>" alt="Logo" style="max-width:150px;max-height:60px;object-fit:contain;">
                  <?php endif; ?>
                </div>
              </div>
              <div class="company-block" contenteditable="true">
                <div><?= h($company['street'] ?? 'Feldstrasse 110') ?></div>
                <div><?= h(($company['zip'] ?? '4123').' '.($company['city'] ?? 'Allschwil')) ?></div>
                <div>Tel: <?= h($company['phone'] ?? '076 50 30 788') ?></div>
                <div><?= h($company['email'] ?? 'info@krasniqi-plattenleger.ch') ?></div>
              </div>
            </section>

            <!-- Address / meta -->
            <section class="section" style="padding-top:6mm">
              <div class="grid-two">
                <div class="box">
                  <h2>Rechnung an</h2>
                  <div id="billTo" contenteditable="true" style="white-space:pre-line"><?= h($row['bill_to'] ?? "Name Surname\nAdresse") ?></div>
                </div>
                <div class="box">
                  <h2>Rechnungsdaten</h2>
                  <div class="meta">
                    <div><span class="muted">Rechnung Nr.</span><span contenteditable="true" id="invNumber"><?= h($row['invoice_number'] ?? '00000') ?></span></div>
                    <div><span class="muted">Datum</span><span contenteditable="true" id="invDate"><?= h($row['invoice_date'] ?? date('Y-m-d')) ?></span></div>
                    <div><span class="muted">Zahlungsart</span><span contenteditable="true">Rechnung</span></div>
                    <div><span class="muted">Referenz</span><span contenteditable="true" id="refText"><?= h($row['ref_text'] ?? '') ?></span></div>
                    <div><span class="muted">ID-nr</span><span contenteditable="true"><?= h($company['company_id'] ?? 'CHE-255-255-255') ?></span></div>
                    <div><span class="muted">MWST-Satz</span><span contenteditable="true" id="vatRate"><?= h($row['vat_rate'] ?? ($company['vat_rate'] ?? '8.1')) ?></span><span class="muted"> %</span></div>
                  </div>
                </div>
              </div>
            </section>

            <!-- Items -->
            <section class="section" style="padding-top:6mm">
              <h2>Leistungen</h2>

              <div class="actions no-print">
                <button class="btn" onclick="addRow()">+ Position hinzufügen</button>
                <button class="btn" id="lumpBtn" onclick="toggleLumpSum()">Alles inkl.</button>
                <button class="btn" onclick="addGroup()">+ Abschnitt / Raum</button>
              </div>

              <div class="table-wrap">
                <table id="items">
                  <thead>
                    <tr>
                      <th style="width:6%">Pos.</th>
                      <th>Beschreibung</th>
                      <th style="width:10%">Menge</th>
                      <th style="width:10%">Einheit</th>
                      <th style="width:14%">Einzelpreis (CHF)</th>
                      <th class="right" style="width:16%">Zwischensumme (CHF)</th>
                      <th style="width:6%"></th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                  <tfoot>
                    <!-- Rabatt -->
                    <tr class="no-print">
                      <td colspan="4"></td>
                      <td class="right">
                        <strong>Rabatt</strong> <span class="small muted">(vor MWST)</span><br>
                        <label class="small">Prozent %:
                          <input id="discountPct" type="number" step="0.01" value="0" style="width:80px" class="simple-input">
                        </label>
                        <span class="small" style="margin:0 6px">oder</span>
                        <label class="small">CHF:
                          <input id="discountAbs" type="text" value="0.00" style="width:100px" class="simple-input">
                        </label>
                      </td>
                      <td class="right muted" id="discountAmount">–0.00 CHF</td>
                      <td></td>
                    </tr>
                    <tr id="discountSummary" class="conditional-row">
                      <td colspan="4"></td>
                      <td class="right" id="discountSummaryLabel"><strong>Rabatt</strong></td>
                      <td class="right" id="discountSummaryValue">–0.00 CHF</td>
                      <td></td>
                    </tr>

                    <!-- Lump sum -->
                    <tr id="lumpSumRow" class="no-print" style="display:none">
                      <td colspan="4"></td>
                      <td class="right"><strong>Alles inkl. Preis (CHF)</strong></td>
                      <td class="right"><input id="lumpTotalInput" class="simple-input" type="text" inputmode="decimal" value="0.00"></td>
                      <td></td>
                    </tr>
                    <tr id="lumpSumSummary" class="conditional-row" style="display:none">
                      <td colspan="4"></td>
                      <td class="right"><strong>Alles inkl. Preis</strong></td>
                      <td class="right" id="lumpTotalValue">0.00 CHF</td>
                      <td></td>
                    </tr>

                    <!-- Totals -->
                    <tr>
                      <td colspan="4" class="no-print muted">Texte editierbar • Zahlen aktualisieren die Summen automatisch.</td>
                      <td class="right muted">Zwischensumme</td>
                      <td class="right" id="subtotal">0.00 CHF</td>
                      <td></td>
                    </tr>
                    <tr>
                      <td colspan="4"></td>
                      <td class="right muted">MWST <span id="vatLabel">8.1%</span></td>
                      <td class="right" id="vatAmount">0.00 CHF</td>
                      <td></td>
                    </tr>
                    <tr class="total">
                      <td colspan="4"></td>
                      <td class="right"><strong>Total Preis</strong></td>
                      <td class="right" id="grandTotal">0.00 CHF</td>
                      <td></td>
                    </tr>

                    <!-- Zahlen -->
                    <tr class="no-print">
                      <td colspan="4"></td>
                      <td class="right"><strong>Zahlen</strong></td>
                      <td class="right"><input id="zahlenInput" class="simple-input" type="text" inputmode="decimal" placeholder="0.00"></td>
                      <td></td>
                    </tr>
                    <tr id="zahlenSummaryRow" class="conditional-row">
                      <td colspan="4"></td>
                      <td class="right"><strong>Zahlen</strong></td>
                      <td class="right" id="zahlenSummaryValue">0.00</td>
                      <td></td>
                    </tr>

                    <!-- Anzahlung -->
                    <tr class="no-print">
                      <td colspan="4"></td>
                      <td class="right"><strong>Anzahlung bezahlt</strong></td>
                      <td class="right"><input id="depositPaidInput" class="simple-input" type="text" inputmode="decimal" value="0.00"></td>
                      <td></td>
                    </tr>
                    <tr id="depositPaidSummary" class="conditional-row">
                      <td colspan="4"></td>
                      <td class="right"><strong>Anzahlung bezahlt</strong></td>
                      <td class="right" id="depositPaidValue">0.00 CHF</td>
                      <td></td>
                    </tr>

                    <!-- Restbetrag -->
                    <tr id="dueRow" class="total conditional-row">
                      <td colspan="4"></td>
                      <td class="right"><strong>Restbetrag</strong></td>
                      <td class="right" id="amountDue">0.00 CHF</td>
                      <td></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </section>

            <!-- Remark -->
            <section class="section" id="remarkSection" style="padding-top:6mm">
              <div class="box">
                <strong style="font-size:12px; color:#b91c1c;">Bemerkung:</strong>
                <div id="remarkText" data-placeholder="(optional) zusätzliche Hinweise …" contenteditable="true" class="small" style="margin-top:6px; min-height:18px"><?= h($row['remark'] ?? '') ?></div>
              </div>
            </section>

            <!-- Footer (only on LAST invoice page when exporting) -->
            <section class="section footer" style="padding-top:6mm">
              <div class="grid-two">
                <div class="box" style="font-size:12px; line-height:1.4">
                  <h2 style="font-size:13.5px; margin-bottom:6px">Zahlungsinformationen</h2>
                  <div><span class="muted">Bank:</span> <span contenteditable="true" id="bank"><?= h($company['bank'] ?? 'UBS Allschwil') ?></span></div>
                  <div><span class="muted">IBAN:</span> <span contenteditable="true" id="iban"><?= h($company['iban'] ?? 'CH26 0023 3233 3300 2501 L') ?></span></div>
                  <div class="small" contenteditable="true">Bitte überweisen Sie gemäss obigen Angaben.</div>
                </div>
                <div class="box" style="font-size:12px">
                  <div class="small" style="border-top:#000 1px solid; width:160px; padding-top:6px"><small>Unterschrift/Stempel</small></div>
                  <div class="small" contenteditable="true"><?= h(($company['signer'] ?? 'SHKELQIM KRASNIQI').' • '.($company['signer_title'] ?? 'Geschäftsführer')) ?></div>
                </div>
              </div>
            </section>
          </main>

          <!-- QR Page -->
          <section class="qr-page page-break">
            <h2 style="margin:0 0 8px 0">QR-Zahlteil</h2>
            <div style="display:grid; grid-template-columns:35mm 1fr 1fr; gap:12px; font-size:12px">
              <div style="width:35mm; height:35mm; border:1px solid var(--line); display:grid; place-items:center; color:var(--muted); font-size:10px">QR-CH<br>Platzhalter</div>
              <div>
                <h3 style="margin:0 0 6px 0; font-size:13px">Empfangsschein</h3>
                <div style="display:grid; grid-template-columns:110px 1fr; gap:6px; margin:4px 0"><div class="muted">Währung / Betrag</div><div>CHF — <span id="qrAmount">0.00</span></div></div>
                <div style="display:grid; grid-template-columns:110px 1fr; gap:6px; margin:4px 0"><div class="muted">Konto</div><div id="qrIban">—</div></div>
                <div style="display:grid; grid-template-columns:110px 1fr; gap:6px; margin:4px 0"><div class="muted">Zahlbar an</div><div id="qrTo"><?= h(($company['name'] ?? 'Firma').', '.($company['street'] ?? '').', '.(($company['zip'] ?? '').' '.($company['city'] ?? ''))) ?></div></div>
                <div style="display:grid; grid-template-columns:110px 1fr; gap:6px; margin:4px 0"><div class="muted">Zahlbar durch</div><div>[Name/Adresse des Einzahlers]</div></div>
              </div>
              <div>
                <h3 style="margin:0 0 6px 0; font-size:13px">Zahlteil</h3>
                <div style="display:grid; grid-template-columns:110px 1fr; gap:6px; margin:4px 0"><div class="muted">Währung</div><div>CHF</div></div>
                <div style="display:grid; grid-template-columns:110px 1fr; gap:6px; margin:4px 0"><div class="muted">Betrag</div><div id="qrAmount2">0.00</div></div>
                <div style="display:grid; grid-template-columns:110px 1fr; gap:6px; margin:4px 0"><div class="muted">Konto</div><div id="qrIban2">—</div></div>
                <div style="display:grid; grid-template-columns:110px 1fr; gap:6px; margin:4px 0"><div class="muted">Zahlbar an</div><div id="qrTo2"><?= h(($company['name'] ?? 'Firma').', '.($company['street'] ?? '').', '.(($company['zip'] ?? '').' '.($company['city'] ?? ''))) ?></div></div>
              </div>
            </div>
            <div class="qr-bottom small muted">QR-Code wird manuell hinzugefügt.</div>
          </section>
        </div><!-- /exportWrap -->
      </div><!-- /doc-scroll -->
    </div><!-- /workarea -->
  </main>
</div>

<!-- Loader + Toast -->
<div id="loader" class="loader"></div>
<div id="toast" class="toast"></div>

<!-- ===== Quick-Edit Floating UI (outside export area) ===== -->
<button id="qeToggle" class="qe-fab no-print" title="Schnelleditieren">⚙️</button>
<div id="qeBackdrop" class="qe-backdrop no-print"></div>
<aside id="qePanel" class="qe-panel no-print" aria-hidden="true">
  <div class="qe-head">
    <h3 class="qe-title">Schnelleditieren</h3>
    <button class="qe-close" id="qeClose">✕</button>
  </div>
  <div class="qe-body">
    <div class="qe-section">
      <h4 style="margin:0;color:#fff;">Rechnung an</h4>
      <label>Empfänger (mehrzeilig)</label>
      <textarea id="qeBillTo" rows="4" spellcheck="false"></textarea>
    </div>

    <div class="qe-section">
      <h4 style="margin:0;color:#fff;">Rechnungsdaten</h4>
      <label>Rechnung Nr.</label>
      <input id="qeInvNo" type="text" inputmode="numeric" />

      <label>Datum</label>
      <input id="qeDate" type="date" />

      <label>Referenz (optional)</label>
      <input id="qeRef" type="text" />

      <label>MWST (%)</label>
      <input id="qeVat" type="number" step="0.01" />
      <div class="qe-help">Änderungen werden live übernommen</div>
    </div>

    <div class="qe-section">
      <h4 style="margin:0;color:#fff;">Bemerkung</h4>
      <label>Bemerkungstext</label>
      <textarea id="qeRemark" rows="4" spellcheck="false"></textarea>
    </div>
  </div>
</aside>

<script>
/* ---- sidebar toggle (mobile) ---- */
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.querySelector('.sidebar');
function toggleSidebar(){ sidebar?.classList.toggle('open'); }
burgerBtn?.addEventListener('click', toggleSidebar);

/* Close sidebar when clicking backdrop area (optional if your sidebar has its own close) */
document.addEventListener('click', (e)=>{
  if (!sidebar) return;
  if (!sidebar.classList.contains('open')) return;
  const withinSidebar = e.target.closest('.sidebar');
  const withinBurger = e.target.closest('#burgerBtn');
  if (!withinSidebar && !withinBurger) sidebar.classList.remove('open');
});

/* ---- autopdf ---- */
if (new URLSearchParams(location.search).get('autopdf') === '1') {
  setTimeout(() => downloadPDF(), 400);
}

const { jsPDF } = window.jspdf;

/* ===== Config ===== */
const MAX_ITEMS = 50;
let lumpSumMode = <?= isset($row['lump_sum']) && (int)$row['lump_sum']==1 ? 'true':'false' ?>;

/* ===== Utils ===== */
const fmtCHF = n => (parseFloat(n||0)).toLocaleString("de-CH",{minimumFractionDigits:2, maximumFractionDigits:2});
function toNumber(v){
  v = String(v ?? '').trim().replace(/[\s\u00A0\u202F\u2019']/g,'').replace(/,(?=\d{3}\b)/g,'');
  if(v.includes(',') && !v.includes('.')) v = v.replace(',', '.');
  const n = parseFloat(v); return isFinite(n) ? n : 0;
}
function money(text){
  let v = String(text || '');
  v = v.replace(/[A-Za-z]/g,'').replace(/[\s\u00A0\u202F\u2019']/g,'');
  if (v.includes(',') && !v.includes('.')) v = v.replace(',', '.');
  v = v.replace(/,(?=\d{3}(?:\D|$))/g,'');
  const n = parseFloat(v);
  return isFinite(n) ? n : 0;
}
function firstLine(s){ return (String(s||'').split(/\r?\n/)[0]||'').trim(); }
function removeRow(btn){ const tr = btn.closest('tr'); if(tr){ tr.remove(); renumberRows(); recalc(); } }
function renumberRows(){
  let n = 1;
  document.querySelectorAll('#items tbody tr').forEach(tr=>{
    if (tr.classList.contains('group-row')) return;
    const cell = tr.querySelector('td.pos'); if(cell) cell.textContent = n++;
  });
}
function getCompanyLine(){
  const lines = [...document.querySelectorAll('.company-block > div')].map(d=>d.innerText.trim()).filter(Boolean);
  const addr = lines.slice(0,2).join(', ');
  return (addr || lines.join(', ')) || '—';
}
function toast(msg, isErr=false){
  const el = document.getElementById('toast'); if(!el) return;
  el.textContent = msg; el.className = 'toast show' + (isErr?' error':'');
  setTimeout(()=> el.classList.remove('show'), 1700);
}
function loader(on){ const l = document.getElementById('loader'); if(!l) return; if(on) l.classList.add('show'); else l.classList.remove('show'); }

/* ===== Row ops ===== */
function addRow(){
  const tbody = document.querySelector("#items tbody");
  const count = tbody.querySelectorAll("tr:not(.group-row)").length;
  if(count >= MAX_ITEMS){ alert(`Maximal ${MAX_ITEMS} Positionen pro Rechnung.`); return; }
  const tr = document.createElement("tr");
  tr.innerHTML = `
    <td class="pos right"></td>
    <td contenteditable="true">Position</td>
    <td><input type="number" step="any" value="1" style="width:100%"></td>
    <td><input type="text" value="m" style="width:100%"></td>
    <td><input type="text" value="0.00" style="width:100%"></td>
    <td class="right line-total">0.00 CHF</td>
    <td><button class="btn" style="padding:4px 8px" onclick="removeRow(this)">🗑</button></td>`;
  tbody.appendChild(tr);
  tr.querySelectorAll('input').forEach(i=>{ i.addEventListener('input', recalc); i.addEventListener('change', recalc); });
  renumberRows(); recalc();
}
function addGroup(label){
  const tbody = document.querySelector("#items tbody");
  const tr = document.createElement("tr");
  tr.className = "group-row";
  tr.setAttribute("data-row-type", "group");
  tr.innerHTML = `<td colspan="7" contenteditable="true">${label || 'Abschnitt / Raum'}<span class="row-actions"><button class="btn" style="padding:2px 6px" onclick="removeRow(this)">🗑</button></span></td>`;
  tbody.appendChild(tr);
}

/* ===== Lump-sum ===== */
function toggleLumpSum(){
  lumpSumMode = !lumpSumMode;
  document.getElementById('lumpBtn').classList.toggle('active', lumpSumMode);
  const tbl = document.getElementById('items');
  tbl.classList.toggle('lumpsum', lumpSumMode);
  document.getElementById('lumpSumRow').style.display = lumpSumMode ? '' : 'none';
  recalc();
}

/* ===== QR updater ===== */
function setQR(amount){
  const iban = (document.getElementById("iban")?.textContent || "").trim();
  const companyLine = getCompanyLine();
  const a = fmtCHF(amount);
  const set = (id,val)=>{ const el=document.getElementById(id); if(el) el.textContent = val; };
  set("qrAmount", a); set("qrAmount2", a);
  set("qrIban", iban || "—"); set("qrIban2", iban || "—");
  set("qrTo", companyLine); set("qrTo2", companyLine);
}

/* ===== Remark visibility ===== */
function syncRemarkVisibility(){
  const txt = (document.getElementById('remarkText')?.innerText || '').trim();
  const sec = document.getElementById('remarkSection');
  if (sec) sec.style.display = txt.length ? '' : 'none';
}

/* ===== Calculations ===== */
function recalc(){
  let baseSubtotal = 0;

  if(!lumpSumMode){
    document.querySelectorAll("#items tbody tr").forEach(tr=>{
      if (tr.classList.contains('group-row')) return;
      const qty   = toNumber(tr.querySelector('td:nth-child(3) input')?.value);
      const price = toNumber(tr.querySelector('td:nth-child(5) input')?.value);
      const line  = qty * price;
      baseSubtotal += line;
      const cell  = tr.querySelector(".line-total");
      if(cell) cell.textContent = fmtCHF(line) + ' CHF';
    });
    const ls = document.getElementById('lumpSumSummary'); if(ls) ls.style.display='none';
  } else {
    const val = toNumber(document.getElementById('lumpTotalInput')?.value || '0');
    baseSubtotal = val;
    const has = val > 0;
    const row = document.getElementById('lumpSumSummary');
    if(row){ row.style.display = has ? '' : 'none'; if(has) document.getElementById('lumpTotalValue').textContent = fmtCHF(val)+' CHF'; }
  }

  // Rabatt
  const pct = toNumber(document.getElementById('discountPct')?.value || '0');
  const abs = toNumber(document.getElementById('discountAbs')?.value || '0');
  let discount = 0, label = '';
  if (abs>0){ discount = abs; label = `Rabatt: ${fmtCHF(abs)} CHF`; }
  else if (pct>0){ discount = baseSubtotal*(pct/100); label = `Rabatt: ${String(pct).replace('.',',')}%`; }
  const ds = document.getElementById('discountSummary');
  if (ds){ ds.style.display = (discount>0) ? '' : 'none';
           document.getElementById('discountSummaryLabel').innerHTML = `<strong>${label||'Rabatt'}</strong>`;
           document.getElementById('discountSummaryValue').textContent = '–'+fmtCHF(discount)+' CHF'; }
  document.getElementById('discountAmount').textContent = '–'+fmtCHF(discount)+' CHF';

  // VAT + totals
  const vatRate = toNumber(document.getElementById("vatRate")?.textContent || '0');
  const taxable = Math.max(0, baseSubtotal - discount);
  const vat     = taxable * (vatRate/100);
  const total   = taxable + vat;

  document.getElementById("subtotal").textContent   = fmtCHF(taxable) + " CHF";
  document.getElementById("vatLabel").textContent   = (isFinite(vatRate)? String(vatRate).replace('.',',') : "0") + "%";
  document.getElementById("vatAmount").textContent  = fmtCHF(vat) + " CHF";
  document.getElementById("grandTotal").textContent = fmtCHF(total) + " CHF";

  // Zahlen
  const zahlenRaw = (document.getElementById('zahlenInput')?.value || '').trim();
  const zr = document.getElementById('zahlenSummaryRow');
  if (zr){ zr.style.display = zahlenRaw ? '' : 'none'; document.getElementById('zahlenSummaryValue').textContent = zahlenRaw || '0.00'; }

  // Anzahlung → Restbetrag
  const depAmt = toNumber(document.getElementById('depositPaidInput')?.value || '0');
  if(depAmt > 0){
    const rest = Math.max(0, total - depAmt);
    document.getElementById('depositPaidValue').textContent = fmtCHF(depAmt) + ' CHF';
    document.getElementById('amountDue').textContent        = fmtCHF(rest) + ' CHF';
    document.getElementById('depositPaidSummary').style.display = '';
    document.getElementById('dueRow').style.display = '';
    setQR(rest);
  } else {
    document.getElementById('depositPaidSummary').style.display = 'none';
    document.getElementById('dueRow').style.display = 'none';
    document.getElementById('amountDue').textContent = fmtCHF(total) + ' CHF';
    setQR(total);
  }

  syncRemarkVisibility();
}

/* ===== Fill hidden fields (for save & ajax save) ===== */
function fillHiddenFields(){
  const bill = document.getElementById('billTo')?.innerText || '';
  document.getElementById('customer_name').value       = firstLine(bill) || 'Unbekannt';
  document.getElementById('bill_to_input').value       = bill.trim();
  document.getElementById('invoice_number_input').value= document.getElementById('invNumber')?.innerText.trim() || '';
  document.getElementById('invoice_date_input').value  = document.getElementById('invDate')?.innerText.trim() || '';
  document.getElementById('ref_text_input').value      = document.getElementById('refText')?.innerText.trim() || '';
  document.getElementById('vat_rate_input').value      = document.getElementById('vatRate')?.innerText.trim() || '';

  document.getElementById('subtotal_input').value   = money(document.getElementById('subtotal')?.innerText);
  document.getElementById('vat_amount_input').value = money(document.getElementById('vatAmount')?.innerText);
  document.getElementById('total_input').value      = money(document.getElementById('grandTotal')?.innerText);
  document.getElementById('amount_due_input').value = money(document.getElementById('amountDue')?.innerText);

  document.getElementById('remark_input').value = document.getElementById('remarkText')?.innerText.trim() || '';
  document.getElementById('lump_sum_input').value = lumpSumMode ? '1' : '0';

  const rows = [...document.querySelectorAll("#items tbody tr")].map(tr=>{
    if (tr.classList.contains('group-row')){
      return { type:'group', desc: tr.cells[0]?.innerText.replace(/🗑/,'').trim() || 'Abschnitt / Raum' };
    }
    return {
      type:'item',
      pos:  tr.querySelector('td.pos')?.innerText.trim() || '',
      desc: tr.cells[1]?.innerText.trim(),
      qty:  tr.querySelector('td:nth-child(3) input')?.value || '',
      unit: tr.querySelector('td:nth-child(4) input')?.value || '',
      price:tr.querySelector('td:nth-child(5) input')?.value || '',
      line: tr.querySelector(".line-total")?.textContent || ''
    };
  });
  document.getElementById('items_json_input').value = JSON.stringify(rows);
}

/* ===== Normal save ===== */
function saveInvoice(){
  fillHiddenFields();
  document.getElementById('saveForm').submit();
}

/* ===== Silent AJAX save (used before PDF) ===== */
async function saveInvoiceAjax(){
  fillHiddenFields();
  const form = document.getElementById('saveForm');
  const fd = new FormData(form);
  fd.set('ajax','1');

  const res = await fetch(window.location.href.split('#')[0], {
    method: 'POST',
    body: fd,
    credentials: 'same-origin'
  });

  try {
    const data = await res.json();
    if (res.ok && data && data.ok && data.id) {
      form.querySelector('input[name="id"]').value = data.id;
      const url = new URL(window.location.href);
      url.searchParams.set('id', data.id);
      window.history.replaceState({}, '', url);
      toast('Gespeichert ✓');
      return true;
    }
  } catch(_) {}
  toast('Fehler beim Speichern', true);
  return false;
}

/* ===== Export helpers ===== */
async function renderNodeToImage(node){
  const prevShadow = node.style.boxShadow, prevMargin = node.style.margin;
  node.style.boxShadow='none'; node.style.margin='0';
  const canvas = await html2canvas(node,{ scale:2, backgroundColor:'#fff', useCORS:true,
    scrollX:0, scrollY:0, windowWidth:Math.max(node.scrollWidth,node.clientWidth),
    windowHeight:Math.max(node.scrollHeight,node.clientHeight)});
  node.style.boxShadow = prevShadow; node.style.margin = prevMargin;
  return canvas.toDataURL('image/jpeg', 0.98);
}
function cloneDocSkeleton(baseDoc){
  const page = baseDoc.cloneNode(true);
  page.setAttribute('data-export-page','1');
  const tbl = page.querySelector('#items');
  const tb  = tbl?.querySelector('tbody');
  const tf  = tbl?.querySelector('tfoot');
  if (tb) tb.innerHTML = '';
  if (tf) tf.remove(); // totals go only to last page
  return page;
}
function measureTfootHeight(srcFoot){
  if (!srcFoot) return 0;
  const ghost = srcFoot.cloneNode(true);
  ghost.style.visibility='hidden';
  const holder = document.createElement('div');
  holder.style.position='absolute'; holder.style.left='-99999px';
  holder.appendChild(ghost); document.body.appendChild(holder);
  const h = Math.ceil(ghost.getBoundingClientRect().height) + 6;
  holder.remove(); return h;
}
function buildPaginatedPages(){
  const wrap = document.getElementById('exportWrap');
  const base = document.querySelector('.doc');
  const qr   = document.querySelector('.qr-page');

  const srcTable = base.querySelector('#items');
  const srcBody  = srcTable.querySelector('tbody');
  const srcFoot  = srcTable.querySelector('tfoot');
  const rows     = Array.from(srcBody.children);
  const tfootH   = measureTfootHeight(srcFoot);

  const pages = [];
  let i = 0;
  const MAX_EXPORT_PAGES = 50; // safety

  while (i < rows.length && pages.length < MAX_EXPORT_PAGES){
    const page = cloneDocSkeleton(base);
    wrap.appendChild(page); pages.push(page);

    const tb = page.querySelector('#items tbody');
    const footer = page.querySelector('.footer');

    const tbTop   = tb.getBoundingClientRect().top;
    const footTop = footer.getBoundingClientRect().top;
    const limit   = Math.floor(footTop - tbTop - 6);

    let added = 0;
    while (i < rows.length){
      const lastRow = (i === rows.length - 1);
      const reserve = lastRow ? tfootH : 0;

      const clone = rows[i].cloneNode(true);
      tb.appendChild(clone);

      const used = tb.getBoundingClientRect().bottom - tbTop;
      if (used > (limit - reserve)){
        tb.removeChild(clone);
        break;
      }
      i++; added++;
    }

    if (added === 0 && i < rows.length){
      tb.appendChild(rows[i].cloneNode(true));
      i++;
    }
  }

  // Footer + Totals only on LAST invoice page
  if (pages.length){
    pages.forEach((p,idx)=>{ if (idx !== pages.length-1) p.querySelector('.footer')?.remove(); });
    const last = pages[pages.length-1];
    const lastTB = last.querySelector('#items tbody');
    const srcFoot2 = document.querySelector('#items tfoot');
    if (srcFoot2) lastTB.after(srcFoot2.cloneNode(true));
  }

  // Append QR page
  if (qr){
    const q = qr.cloneNode(true); q.setAttribute('data-export-page','1');
    wrap.appendChild(q); pages.push(q);
  }
  return pages;
}

/* ===== PDF (saves first, then exports) ===== */
async function downloadPDF(){
  loader(true);
  const saved = await saveInvoiceAjax().catch(()=>false);
  if (!saved) { loader(false); return; }

  const active = document.activeElement; if (active?.blur) active.blur();
  document.body.classList.add('exporting');

  // Hide empty "Bemerkung" ONLY in export
  const remarkSection = document.getElementById('remarkSection');
  const remarkEmpty = !(document.getElementById('remarkText')?.innerText || '').trim();
  if (remarkEmpty && remarkSection) remarkSection.classList.add('hide-when-export');

  const originals = Array.from(document.querySelectorAll('.doc, .qr-page'));
  const pages = buildPaginatedPages();
  originals.forEach(n => n.classList.add('hide-during-export'));
  await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

  try{
    const pdf = new jsPDF('p','mm','a4');
    const W = pdf.internal.pageSize.getWidth();
    const H = pdf.internal.pageSize.getHeight();

    const name = firstLine(document.getElementById('billTo')?.innerText) || 'Rechnung';
    const dateStr = (document.getElementById('invDate')?.innerText || '').trim() || new Date().toISOString().slice(0,10);
    const filename = `${name} - Rechnung - ${dateStr}.pdf`;

    for (let p = 0; p < pages.length; p++){
      const img = await renderNodeToImage(pages[p]);
      if (p) pdf.addPage();
      pdf.addImage(img, 'JPEG', 0, 0, W, H, undefined, 'FAST');
    }
    pdf.save(filename);
    toast('PDF gespeichert ✓');
  } catch (e){
    console.error(e);
    toast('PDF-Export fehlgeschlagen', true);
  } finally {
    document.querySelectorAll('[data-export-page="1"]').forEach(n => n.remove());
    originals.forEach(n => n.classList.remove('hide-during-export'));
    if (remarkEmpty && remarkSection) remarkSection.classList.remove('hide-when-export');
    document.body.classList.remove('exporting');
    loader(false);
  }
}

/* ===== Wire inputs ===== */
document.getElementById('discountPct')?.addEventListener('input', recalc);
document.getElementById('discountAbs')?.addEventListener('input', recalc);
document.getElementById('zahlenInput')?.addEventListener('input', recalc);
document.getElementById('depositPaidInput')?.addEventListener('input', recalc);
document.getElementById('lumpTotalInput')?.addEventListener('input', recalc);
document.getElementById('remarkText')?.addEventListener('input', syncRemarkVisibility);

/* ===== Quick-Edit Panel Logic ===== */
const qePanel = document.getElementById('qePanel');
const qeBackdrop = document.getElementById('qeBackdrop');
const qeToggle = document.getElementById('qeToggle');
const qeClose = document.getElementById('qeClose');

function qeOpen(){
  // Prefill from document
  const billTo = document.getElementById('billTo')?.innerText || '';
  document.getElementById('qeBillTo').value = billTo;

  document.getElementById('qeInvNo').value = (document.getElementById('invNumber')?.innerText || '').trim();
  const invDate = (document.getElementById('invDate')?.innerText || '').trim();
  document.getElementById('qeDate').value = /^\d{4}-\d{2}-\d{2}$/.test(invDate) ? invDate : '';
  document.getElementById('qeRef').value  = (document.getElementById('refText')?.innerText || '').trim();

  const vatTxt = (document.getElementById('vatRate')?.innerText || '').replace(',','.');
  document.getElementById('qeVat').value = toNumber(vatTxt).toString();

  const remarkTxt = (document.getElementById('remarkText')?.innerText || '').trim();
  document.getElementById('qeRemark').value = remarkTxt;

  qeBackdrop.classList.add('show');
  qePanel.classList.add('open');
  qePanel.setAttribute('aria-hidden','false');
}
function qeClosePanel(){
  qeBackdrop.classList.remove('show');
  qePanel.classList.remove('open');
  qePanel.setAttribute('aria-hidden','true');
}
qeToggle?.addEventListener('click', qeOpen);
qeClose?.addEventListener('click', qeClosePanel);
qeBackdrop?.addEventListener('click', qeClosePanel);
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') qeClosePanel(); });

// Live apply changes from panel → document
function applyQuickEdit(){
  const bt = document.getElementById('qeBillTo').value;
  const invNo = document.getElementById('qeInvNo').value.trim();
  const date  = document.getElementById('qeDate').value;
  const ref   = document.getElementById('qeRef').value.trim();
  const vat   = document.getElementById('qeVat').value;
  const rk    = document.getElementById('qeRemark').value;

  const billToEl = document.getElementById('billTo');
  if (billToEl){ billToEl.innerText = bt; }

  const invNumEl = document.getElementById('invNumber'); if(invNumEl) invNumEl.innerText = invNo || '';
  const invDateEl= document.getElementById('invDate');   if(invDateEl) invDateEl.innerText = date || '';
  const refEl    = document.getElementById('refText');   if(refEl) refEl.innerText = ref || '';
  const vatEl    = document.getElementById('vatRate');   if(vatEl) vatEl.innerText = (vat || '').replace('.',',');

  const remarkEl = document.getElementById('remarkText');
  if (remarkEl){ remarkEl.innerText = rk; }

  recalc();
}
['qeBillTo','qeInvNo','qeDate','qeRef','qeVat','qeRemark'].forEach(id=>{
  const el = document.getElementById(id);
  el?.addEventListener('input', applyQuickEdit);
});

/* ===== Seed / Load ===== */
(function seed(){
  const saved = <?= $row && !empty($row['items_json']) ? $row['items_json'] : '[]' ?>;
  if (Array.isArray(saved) && saved.length){
    saved.forEach(r=>{
      if (r.type === 'group'){ addGroup(r.desc || 'Abschnitt / Raum'); }
      else {
        addRow();
        const tr = document.querySelector("#items tbody tr:last-child");
        tr.cells[1].innerText = r.desc || 'Position';
        tr.querySelector('td:nth-child(3) input').value = r.qty || 1;
        tr.querySelector('td:nth-child(4) input').value = r.unit || 'm';
        tr.querySelector('td:nth-child(5) input').value = r.price || '0.00';
      }
    });
  } else {
    addGroup('Erdgeschoss'); addRow();
  }

  if (lumpSumMode){
    document.getElementById('items').classList.add('lumpsum');
    document.getElementById('lumpSumRow').style.display='';
  }

  renumberRows(); recalc(); syncRemarkVisibility();

  // Show redirect toasts
  <?php if (isset($_GET['saved'])): ?> toast('Gespeichert ✓'); <?php endif; ?>
  <?php if (isset($_GET['error'])): ?> toast('Fehler: <?= h($_GET['error']) ?>', true); <?php endif; ?>
})();
</script>
</body>
</html>
