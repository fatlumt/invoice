<?php
/***************************************************
 * Offerte – Editor (responsive topbar + floating edit + quick-edit sidebar)
 * - White background, mobile-friendly stacked buttons
 * - Floating ⚙️ button (desktop bottom-right; on mobile it sits just below the topbar)
 * - Quick-Edit sidebar: client name, address, dates, reference, VAT, remark
 * - Instant save (debounced) via AJAX, plus normal save
 * - A4 export via html2canvas + jsPDF (unchanged behavior)
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

/* ---- Load offer ---- */
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = null;
if ($id > 0) {
  $res = $mysqli->query("SELECT * FROM offerten WHERE id=$id");
  if ($res && $res->num_rows) $row = $res->fetch_assoc();
}

/* ---- Save (supports AJAX via ajax=1) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id           = (int)($_POST['id'] ?? 0);
  $customer     = $mysqli->real_escape_string($_POST['customer_name'] ?? '');
  $bill_to      = $mysqli->real_escape_string($_POST['bill_to'] ?? '');
  $offer_no     = $mysqli->real_escape_string($_POST['offer_number'] ?? '');
  $offer_date   = $mysqli->real_escape_string($_POST['offer_date'] ?? date('Y-m-d'));
  $valid_until  = $mysqli->real_escape_string($_POST['valid_until'] ?? date('Y-m-d', strtotime('+30 days')));
  $ref_text     = $mysqli->real_escape_string($_POST['ref_text'] ?? '');
  $vat_rate     = (float)($_POST['vat_rate'] ?? 0);
  $subtotal     = (float)($_POST['subtotal'] ?? 0);
  $vat_amount   = (float)($_POST['vat_amount'] ?? 0);
  $total        = (float)($_POST['total'] ?? 0);
  $remark       = $mysqli->real_escape_string($_POST['remark'] ?? '');
  $items_json   = $mysqli->real_escape_string($_POST['items_json'] ?? '[]');
  $lump_sum     = (int)($_POST['lump_sum'] ?? 0);
  $isAjax       = !empty($_POST['ajax']) && $_POST['ajax']==='1';

  if ($id > 0) {
    $sql = "UPDATE offerten SET
      customer_name='$customer', bill_to='$bill_to',
      offer_number='$offer_no', offer_date='$offer_date', valid_until='$valid_until',
      ref_text='$ref_text', vat_rate=$vat_rate,
      subtotal=$subtotal, vat_amount=$vat_amount, total=$total,
      remark='$remark', items_json='$items_json', lump_sum=$lump_sum
    WHERE id=$id";
  } else {
    $sql = "INSERT INTO offerten
      (customer_name,bill_to,offer_number,offer_date,valid_until,ref_text,vat_rate,
      subtotal,vat_amount,total,remark,items_json,lump_sum)
      VALUES
      ('$customer','$bill_to','$offer_no','$offer_date','$valid_until','$ref_text',$vat_rate,
      $subtotal,$vat_amount,$total,'$remark','$items_json',$lump_sum)";
  }

  if ($mysqli->query($sql)) {
    $newId = $id ?: $mysqli->insert_id;
    if ($isAjax) {
      header('Content-Type: application/json');
      echo json_encode(['ok'=>true,'id'=>$newId]);
      exit;
    }
    header("Location: offerte-edit.php?id=".$newId."&saved=1");
    exit;
  } else {
    if ($isAjax) {
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'error'=>$mysqli->error]);
      exit;
    }
    header("Location: offerte-edit.php".($id?("?id=".$id):"")."&error=".urlencode($mysqli->error));
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8" />
<title>Offerte – Bearbeiten</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>

<!-- Export libs -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<style>
:root{
  --bg:#ffffff; /* full white app background */
  --ink:#0f172a; --muted:#6b7280; --card:#ffffff; --line:#e5e7eb;
  --accent-50:#fef2f2; --accent-100:#fee2e2; --accent-200:#fecaca; --accent-300:#fca5a5;
  --accent-400:#f87171; --accent-500:#ef4444; --accent-600:#dc2626; --accent-700:#b91c1c;
  --accent-800:#991b1b; --accent-900:#7f1d1d; --surface:#fff7f7; --black:#000;
  --radius:14px; --sidebar-w:260px;
  --pad:10mm; --doc-w:210mm;
}

/* App layout */
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
a{color:var(--accent-700);text-decoration:none} a:hover{text-decoration:underline}
.layout{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh}
.sidebar{background:#fff;border-right:1px solid var(--line);padding:18px;position:sticky;top:0;height:100dvh;z-index:20}
.content{padding:22px 26px}
.topbar{
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;
  background:var(--accent-600);padding:12px 18px;border-radius:12px;margin-bottom:16px;color:#fff;
}
.title{font-size:20px;font-weight:800;margin:0;color:#fff}
.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{background:#fff;color:#111;border:1px solid var(--line);padding:10px 14px;border-radius:999px;font-weight:700;cursor:pointer}
.btn:hover{background:#f8fafc}
.btn.primary{background:var(--accent-600);color:#fff;border-color:var(--accent-700)}
.btn.primary:hover{background:var(--accent-700)}
.btn.secondary{background:#fff;color:#111;border:1px solid var(--line)}
.toolbar{position:sticky; top:0; z-index:10; width:var(--doc-w); max-width:calc(100% - 20px); margin:0 auto 10px; display:flex; justify-content:center; gap:8px; flex-wrap:wrap; background:#fff; padding:6px var(--pad); border:1px solid var(--accent-200); border-bottom:none; border-top-left-radius:8px; border-top-right-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,.05);}

/* Mobile stacking for topbar actions */
@media (max-width: 700px){
  .layout{grid-template-columns:1fr}
  .content{padding:18px}
  .topbar{flex-direction:column;align-items:flex-start;gap:12px}
  .actions{width:100%;flex-direction:column;align-items:stretch}
  .actions .btn{width:100%;text-align:center;justify-content:center}
}

/* Export wrapper + doc pages */
#exportWrap{width:var(--doc-w);margin:10mm auto;background:#fff;box-shadow:0 8px 20px rgba(0,0,0,.07);}
.doc{width:var(--doc-w);min-height:297mm;background:#fff;position:relative;overflow:hidden;padding-bottom:25mm;page-break-after:always}

/* Sections */
.section{padding:8mm var(--pad)} .section + .section{padding-top:6mm}

/* Header */
.header{display:flex;justify-content:space-between;gap:14px;border-bottom:1px solid var(--accent-200);padding-bottom:6mm;background:linear-gradient(0deg, #fff, #fff), linear-gradient(180deg, #ef4444 0%, #ffffff 100%); background-blend-mode:normal; border-top-left-radius:12px;border-top-right-radius:12px}
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
tbody tr.group-row td{background:#fff !important;border-top:1px solid #000;border-bottom:0;font-weight:700;color:#111;padding:10px 8px 4px;}
tbody tr.group-row:first-child td{border-top:0}
.row-actions{float:right;margin-left:8px;font-weight:400}

/* Lump-sum hides cols */
#items.lumpsum thead th:nth-child(3),
#items.lumpsum thead th:nth-child(4),
#items.lumpsum thead th:nth-child(5),
#items.lumpsum thead th:nth-child(6),
#items.lumpsum tbody td:nth-child(3),
#items.lumpsum tbody td:nth-child(4),
#items.lumpsum tbody td:nth-child(5),
#items.lumpsum tbody td:nth-child(6){display:none}

/* Remark placeholder + export-hiding */
#remarkText:empty::before { content: attr(data-placeholder); color:#94a3b8; font-style:italic; }
body.exporting .hide-when-export { display:none !important; }

/* Footer (only on LAST page in export) */
.footer{position:absolute;bottom:10mm;left:var(--pad);right:var(--pad);border-top:1px solid #000;padding-top:6mm}

/* QR page */
.page-break{break-before:page}
.qr-page{display:flex;flex-direction:column;min-height:297mm;padding:8mm var(--pad)}
.qr-bottom{margin-top:auto}

/* Hide UI when exporting/printing */
body.exporting .toolbar,.exporting .actions,.exporting .btn,.exporting .btn-mini,.exporting .no-print{display:none !important;}
body.exporting #items tbody tr td:nth-child(3) input,
body.exporting #items tbody tr td:nth-child(4) input,
body.exporting #items tbody tr td:nth-child(5) input{border:0 !important;outline:0 !important;background:transparent !important;box-shadow:none !important;}
body.exporting .doc{height:297mm !important; overflow:hidden !important}
@page{size:A4;margin:0;padding:0}
@media print{
  html,body{margin:0;background:#fff !important;width:210mm;height:297mm}
  #exportWrap{width:210mm !important;margin:0 !important;box-shadow:none !important}
  .toolbar,.btn,.actions,.no-print{display:none !important}
  tbody tr:nth-child(odd) td{background:transparent !important}
  input{border:0 !important;background:transparent !important;padding:0 !important;width:auto}
  a[href]:after{content:""}
  .footer{bottom:10mm}
}

/* Buttons inside items section */
.actions .btn{border: 1px solid var(--accent-300); background: #fff; color: var(--accent-700); font-weight: 600; padding: 8px 14px; border-radius: 999px; cursor: pointer; box-shadow: 0 1px 0 rgba(0,0,0,.04); transition: background .15s ease, box-shadow .15s ease, transform .02s ease;}
.actions .btn:hover{background: var(--accent-50);}
.actions .btn:active{transform: translateY(1px);}
#lumpBtn{ border-color: var(--accent-600); color: var(--accent-700); }
#lumpBtn.active{ background: linear-gradient(180deg, var(--accent-600), var(--accent-700)); color: #fff; border-color: var(--accent-700); box-shadow: 0 2px 8px rgba(220,38,38,.25); }
.actions .btn:nth-child(3){ border-style: dashed; }
.btn-mini{ border:1px solid var(--accent-300); background:#fff; color:var(--accent-700); padding:2px 8px; border-radius:999px; }
.btn-mini:hover{ background: var(--accent-50); }

/* Toast + Loader */
.toast{position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); background:#111; color:#fff; padding:14px 18px; border-radius:12px; z-index:9999; box-shadow:0 12px 30px rgba(0,0,0,.25); display:none; max-width:80%; text-align:center;}
.toast.error{background:#b91c1c}
.toast.show{display:block; animation:fade .25s ease}
@keyframes fade{from{opacity:0; transform:translate(-50%,-56%)} to{opacity:1; transform:translate(-50%,-50%)}}
.loader{position:fixed; inset:0; background:rgba(255,255,255,.65); z-index:9998; display:none; backdrop-filter: blur(2px);}
.loader.show{display:block}
.loader:after{content:""; position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:46px; height:46px; border:4px solid #e5e7eb; border-top-color:#ef4444; border-radius:999px; animation:spin .9s linear infinite;}
@keyframes spin{to{transform:translate(-50%,-50%) rotate(360deg)}}

/* Floating quick-edit button (⚙️) */
.qe-fab{
  position:fixed; right:18px; bottom:18px; width:48px; height:48px; border-radius:999px;
  border:none; background:#111; color:#fff; font-size:20px; cursor:pointer; z-index:10000;
  box-shadow:0 10px 24px rgba(0,0,0,.22);
}
.qe-fab:hover{filter:brightness(1.1)}
/* On mobile, place it just below the topbar area (not covering content) */
@media (max-width:700px){
  .qe-fab{ position:sticky; bottom:auto; right:auto; margin-left:auto; display:block; }
}

/* Quick-Edit panel (right sidebar) */
.qe-panel{
  position:fixed; top:0; right:-380px; width:360px; max-width:88vw; height:100dvh;
  background:#fff; border-left:1px solid var(--line); box-shadow:-16px 0 40px rgba(0,0,0,.18);
  z-index:10001; display:flex; flex-direction:column; transition:right .28s ease;
}
.qe-panel.open{ right:0; }
.qe-head{display:flex; align-items:center; justify-content:space-between; gap:8px; padding:12px 14px; border-bottom:1px solid var(--line);}
.qe-body{padding:14px; overflow:auto}
.qe-body label{font-size:12px; color:#374151; font-weight:700; display:block; margin:10px 0 6px}
.qe-body input[type="text"],
.qe-body input[type="date"],
.qe-body textarea{width:100%; border:1px solid var(--line); border-radius:10px; padding:10px 12px; font:inherit; background:#fff}
.qe-row{display:grid; grid-template-columns:1fr 1fr; gap:10px}
@media (max-width:520px){ .qe-row{grid-template-columns:1fr} }
.qe-foot{padding:12px 14px; border-top:1px solid var(--line); display:flex; gap:10px; justify-content:flex-end}
.qe-pill{font-size:12px; color:#6b7280}
</style>
</head>
<body>
<div class="layout">
  <?php include __DIR__.'/includes/sidebar.php'; ?>

  <!-- Main content -->
  <main class="content">
    <!-- App header -->
    <div class="topbar">
      <h2 class="title">Offerte <?= $id ? '#'.(int)$id : '(neu)' ?></h2>
      <div class="actions">
        <a class="btn secondary" href="offerten.php">‹ Zur Übersicht</a>
        <button class="btn" type="button" onclick="saveOffer()">💾 Speichern</button>
        <button class="btn primary" type="button" onclick="downloadPDF()">📄 PDF herunterladen</button>
      </div>
    </div>

    <!-- Toolbar (doc width, sticky) -->
    <div class="toolbar">
      <form method="post" id="saveForm">
        <input type="hidden" name="id" value="<?= h($row['id'] ?? '') ?>">
        <input type="hidden" name="customer_name" id="customer_name">
        <input type="hidden" name="bill_to" id="bill_to_input">
        <input type="hidden" name="offer_number" id="offer_number_input">
        <input type="hidden" name="offer_date" id="offer_date_input">
        <input type="hidden" name="valid_until" id="valid_until_input">
        <input type="hidden" name="ref_text" id="ref_text_input">
        <input type="hidden" name="vat_rate" id="vat_rate_input">
        <input type="hidden" name="subtotal" id="subtotal_input">
        <input type="hidden" name="vat_amount" id="vat_amount_input">
        <input type="hidden" name="total" id="total_input">
        <input type="hidden" name="remark" id="remark_input">
        <input type="hidden" name="items_json" id="items_json_input">
        <input type="hidden" name="lump_sum" id="lump_sum_input">
        <button type="button" class="btn" onclick="saveOffer()">💾 Speichern</button>
      </form>
      <button class="btn primary" onclick="downloadPDF()">📄 PDF herunterladen</button>
    </div>

    <!-- Export wrapper -->
    <div id="exportWrap">
      <!-- Offer page (screen); export paginates clones -->
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
              <h2>Offerte an</h2>
              <div id="billTo" contenteditable="true" style="white-space:pre-line"><?= h($row['bill_to'] ?? "Name Surname\nAdresse") ?></div>
            </div>
            <div class="box">
              <h2>Offertendaten</h2>
              <div class="meta">
                <div><span class="muted">Offerte Nr.</span><span contenteditable="true" id="offerNumber"><?= h($row['offer_number'] ?? '00000') ?></span></div>
                <div><span class="muted">Datum</span><span contenteditable="true" id="offerDate"><?= h($row['offer_date'] ?? date('Y-m-d')) ?></span></div>
                <div><span class="muted">Gültig bis</span><span contenteditable="true" id="validUntil"><?= h($row['valid_until'] ?? date('Y-m-d', strtotime('+30 days'))) ?></span></div>
                <div><span class="muted">Referenz</span><span contenteditable="true" id="refText"><?= h($row['ref_text'] ?? '') ?></span></div>
                <div><span class="muted">MWST-Satz</span><span contenteditable="true" id="vatRate"><?= h($row['vat_rate'] ?? ($company['vat_rate'] ?? '8.1')) ?></span><span class="muted"> %</span></div>
              </div>
            </div>
          </div>
        </section>

        <!-- Items -->
        <section class="section" style="padding-top:6mm">
          <h2>Leistungen (Offerte)</h2>

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
                  <td class="right"><strong>Total (Offerte)</strong></td>
                  <td class="right" id="grandTotal">0.00 CHF</td>
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

        <!-- Footer (only on LAST offer page when exporting) -->
        <section class="section footer" style="padding-top:6mm">
          <div class="grid-two">
            <div class="box" style="font-size:12px; line-height:1.4">
              <h2 style="font-size:13.5px; margin-bottom:6px">Zahlungsinformationen</h2>
              <div><span class="muted">Bank:</span> <span contenteditable="true" id="bank"><?= h($company['bank'] ?? 'UBS Allschwil') ?></span></div>
              <div><span class="muted">IBAN:</span> <span contenteditable="true" id="iban"><?= h($company['iban'] ?? 'CH26 0023 3233 3300 2501 L') ?></span></div>
              <div class="small" contenteditable="true">Zahlung gemäss vereinbarten Konditionen nach Auftragsbestätigung.</div>
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
  </main>
</div>

<!-- Floating quick-edit button -->
<button class="qe-fab" id="qeOpenBtn" title="Schnell bearbeiten">⚙️</button>

<!-- Quick-Edit Panel -->
<aside class="qe-panel" id="qePanel" aria-hidden="true">
  <div class="qe-head">
    <strong>Schnell bearbeiten</strong>
    <span class="qe-pill" id="qeSavedPill" style="display:none">✓ gespeichert</span>
    <button class="btn-mini" id="qeCloseBtn">✕</button>
  </div>
  <div class="qe-body">
    <label>Kunde (erste Zeile = Name)</label>
    <input type="text" id="qeCustomer" placeholder="Name / Firma" value="<?= h($row['customer_name'] ?? '') ?>">
    <label>Adresse / Rechnung an</label>
    <textarea id="qeBillTo" rows="4" placeholder="Anschrift"><?= h($row['bill_to'] ?? '') ?></textarea>

    <div class="qe-row">
      <div>
        <label>Offerte Nr.</label>
        <input type="text" id="qeNumber" value="<?= h($row['offer_number'] ?? '00000') ?>">
      </div>
      <div>
        <label>MWST %</label>
        <input type="text" id="qeVat" value="<?= h($row['vat_rate'] ?? ($company['vat_rate'] ?? '8.1')) ?>">
      </div>
    </div>

    <div class="qe-row">
      <div>
        <label>Datum</label>
        <input type="date" id="qeDate" value="<?= h($row['offer_date'] ?? date('Y-m-d')) ?>">
      </div>
      <div>
        <label>Gültig bis</label>
        <input type="date" id="qeValid" value="<?= h($row['valid_until'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
      </div>
    </div>

    <label>Referenz</label>
    <input type="text" id="qeRef" value="<?= h($row['ref_text'] ?? '') ?>">

    <label>Bemerkung</label>
    <textarea id="qeRemark" rows="3" placeholder="(optional) zusätzliche Hinweise …"><?= h($row['remark'] ?? '') ?></textarea>
  </div>
  <div class="qe-foot">
    <button class="btn" id="qeSaveBtn" title="Speichern">💾 Speichern</button>
  </div>
</aside>

<!-- Loader + Toast -->
<div id="loader" class="loader"></div>
<div id="toast" class="toast"></div>

<!-- PART 2 (JavaScript) will be inserted here before </body></html> -->
<script>
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
function toast(msg, isErr=false){
  const el = document.getElementById('toast'); if(!el) return;
  el.textContent = msg; el.className = 'toast show' + (isErr?' error':'');
  setTimeout(()=> el.classList.remove('show'), 1700);
}
function loader(on){ const l = document.getElementById('loader'); if(!l) return; if(on) l.classList.add('show'); else l.classList.remove('show'); }

/* ===== Row ops ===== */
function removeRow(btn){ const tr = btn.closest('tr'); if(tr){ tr.remove(); renumberRows(); recalc(); } }
function renumberRows(){
  let n = 1;
  document.querySelectorAll('#items tbody tr').forEach(tr=>{
    if (tr.classList.contains('group-row')) return;
    const cell = tr.querySelector('td.pos'); if(cell) cell.textContent = n++;
  });
}
function addRow(){
  const tbody = document.querySelector("#items tbody");
  const count = tbody.querySelectorAll("tr:not(.group-row)").length;
  if(count >= MAX_ITEMS){ alert(`Maximal ${MAX_ITEMS} Positionen pro Offerte.`); return; }
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

/* ===== QR helpers ===== */
function getCompanyLine(){
  const lines = [...document.querySelectorAll('.company-block > div')].map(d=>d.innerText.trim()).filter(Boolean);
  const addr = lines.slice(0,2).join(', ');
  return (addr || lines.join(', ')) || '—';
}
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

  setQR(total);
  syncRemarkVisibility();
}

/* ===== Fill hidden fields ===== */
function fillHiddenFields(){
  const bill = document.getElementById('billTo')?.innerText || '';
  document.getElementById('customer_name').value       = firstLine(bill) || 'Unbekannt';
  document.getElementById('bill_to_input').value       = bill.trim();
  document.getElementById('offer_number_input').value  = document.getElementById('offerNumber')?.innerText.trim() || '';
  document.getElementById('offer_date_input').value    = document.getElementById('offerDate')?.innerText.trim() || '';
  document.getElementById('valid_until_input').value   = document.getElementById('validUntil')?.innerText.trim() || '';
  document.getElementById('ref_text_input').value      = document.getElementById('refText')?.innerText.trim() || '';
  document.getElementById('vat_rate_input').value      = document.getElementById('vatRate')?.innerText.trim() || '';

  document.getElementById('subtotal_input').value   = money(document.getElementById('subtotal')?.innerText);
  document.getElementById('vat_amount_input').value = money(document.getElementById('vatAmount')?.innerText);
  document.getElementById('total_input').value      = money(document.getElementById('grandTotal')?.innerText);

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
function saveOffer(){ fillHiddenFields(); document.getElementById('saveForm').submit(); }

/* ===== Silent AJAX save ===== */
async function saveOfferAjax(){
  fillHiddenFields();
  const form = document.getElementById('saveForm');
  const fd = new FormData(form);
  fd.set('ajax','1');
  const res = await fetch(window.location.href.split('#')[0], { method: 'POST', body: fd, credentials: 'same-origin' });
  try {
    const data = await res.json();
    if (res.ok && data && data.ok && data.id) {
      form.querySelector('input[name="id"]').value = data.id;
      const url = new URL(window.location.href);
      url.searchParams.set('id', data.id);
      window.history.replaceState({}, '', url);
      const pill = document.getElementById('qeSavedPill'); if (pill){ pill.style.display='inline'; setTimeout(()=>pill.style.display='none',1200); }
      return true;
    }
  } catch(_) {}
  toast('Fehler beim Speichern', true);
  return false;
}

/* ===== Export helpers ===== (omitted for brevity—same as before) */
/* Keep your original html2canvas/jspdf logic here unchanged */

/* ===== Quick-Edit panel behavior ===== */
const qeOpen  = document.getElementById('qeOpenBtn');
const qeClose = document.getElementById('qeCloseBtn');
const qePanel = document.getElementById('qePanel');
qeOpen?.addEventListener('click', ()=>{ qePanel.classList.add('open'); qePanel.setAttribute('aria-hidden','false'); });
qeClose?.addEventListener('click', ()=>{ qePanel.classList.remove('open'); qePanel.setAttribute('aria-hidden','true'); });

/* ✨ NEW: close on outside click or ESC */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    qePanel.classList.remove('open');
    qePanel.setAttribute('aria-hidden','true');
  }
});
document.addEventListener('click', e => {
  const inside = qePanel.contains(e.target);
  const trigger = qeOpen.contains(e.target);
  if (!inside && !trigger) {
    qePanel.classList.remove('open');
    qePanel.setAttribute('aria-hidden','true');
  }
});

/* ===== Sync Quick-Edit <-> Doc + instant save (same as before) ===== */
// ... (keep your existing syncFromPanelToDoc, syncFromDocToPanel, scheduleInstantSave, etc.)

</script>
</body>
</html>
