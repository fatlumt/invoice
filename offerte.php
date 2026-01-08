<?php
/***************************************************
 * offerte.php — Übersicht (Rechnungen-style cards + modern UI)
 ***************************************************/
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/php_errors.log');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

const TABLE_NAME = 'offerten';

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function chf($n){ return number_format((float)$n, 2, ',', "'") . ' CHF'; }
function json_out($arr,$status=200){
  if (ob_get_level()) @ob_end_clean();
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}
function get_totals(mysqli $c){
  $a=$c->query("
    SELECT
      COALESCE(SUM(total),0) AS gross_sum,
      COALESCE(SUM(CASE WHEN status='angenommen' THEN total ELSE 0 END),0) AS accepted_sum,
      COALESCE(SUM(CASE WHEN status='offen' THEN total ELSE 0 END),0) AS open_sum,
      COALESCE(SUM(CASE WHEN status='abgelehnt' THEN total ELSE 0 END),0) AS declined_sum,
      COUNT(*) AS offer_count
    FROM ".TABLE_NAME
  )->fetch_assoc();
  return [
    'gross_sum'=>chf($a['gross_sum']??0),
    'accepted_sum'=>chf($a['accepted_sum']??0),
    'open_sum'=>chf($a['open_sum']??0),
    'declined_sum'=>chf($a['declined_sum']??0),
    'offer_count'=>(int)($a['offer_count']??0)
  ];
}

/* ---------- AJAX ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
  if (empty($_SESSION['user'])) json_out(['success'=>false,'error'=>'Session expired'],403);
  $id=(int)($_POST['id']??0);
  $action=$_POST['ajax'];

  if ($action==='update_status_offerte') {
    $status=$_POST['status']??'';
    if ($id<=0 || !in_array($status,['offen','angenommen','abgelehnt'],true))
      json_out(['success'=>false,'error'=>'Ungültige Daten'],422);
    $st=$conn->prepare("UPDATE ".TABLE_NAME." SET status=? WHERE id=?");
    $st->bind_param('si',$status,$id);
    $st->execute();
    json_out(array_merge(['success'=>true],get_totals($conn)));
  }

  if ($action==='delete_offerte') {
    if ($id<=0) json_out(['success'=>false,'error'=>'Ungültige ID'],422);
    $st=$conn->prepare("DELETE FROM ".TABLE_NAME." WHERE id=?");
    $st->bind_param('i',$id);
    $st->execute();
    json_out(array_merge(['success'=>true],get_totals($conn)));
  }

  json_out(['success'=>false,'error'=>'Unbekannte Aktion'],400);
}

/* ---------- PAGE ---------- */
if(empty($_SESSION['user'])){header('Location: login.php');exit;}

$q=trim($_GET['q']??'');
$statusFilter=$_GET['status']??'';
$dateFrom=$_GET['from']??'';
$dateTo=$_GET['to']??'';

$where=$params=[];$types='';
if($q!==''){ $where[]="(customer_name LIKE ? OR offer_number LIKE ?)"; $like="%$q%"; $types.='ss'; $params[]=$like; $params[]=$like; }
if($statusFilter!==''){ $where[]="status=?"; $types.='s'; $params[]=$statusFilter; }
if($dateFrom!==''){ $where[]="offer_date>=?"; $types.='s'; $params[]=$dateFrom; }
if($dateTo!==''){ $where[]="offer_date<=?"; $types.='s'; $params[]=$dateTo; }

$whereSql=$where?'WHERE '.implode(' AND ',$where):'';

$sql="SELECT id,customer_name,offer_number,offer_date,valid_until,total,status
      FROM ".TABLE_NAME." $whereSql ORDER BY offer_date DESC,id DESC";
$st=$conn->prepare($sql);
if($types)$st->bind_param($types,...$params);
$st->execute();$res=$st->get_result();
$totals=get_totals($conn);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Offerten – Übersicht</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --bg:#f5f7fb;--ink:#0f172a;--muted:#6b7280;--line:#e5e7eb;
  --accent:#ef4444;--ok:#16a34a;--bad:#dc2626;
}
body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,system-ui,sans-serif;}
.layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh;}
.content{padding:24px;}
.topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;
  background:var(--accent);color:#fff;padding:12px 18px;border-radius:12px;margin-bottom:16px;}
.menu-btn{display:none;background:none;border:none;font-size:22px;color:#fff;cursor:pointer;margin-right:10px;}
.btn{background:#fff;color:#111;border:1px solid #ccc;padding:8px 12px;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none;}

/* --- Matching rechnungen cards --- */
.cards{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:14px;margin:14px 0 18px;
}
.card{
  background:#fff;border:1px solid var(--line);border-radius:20px;padding:16px 18px;
  box-shadow:0 4px 10px rgba(0,0,0,.04);
  transition:transform .15s ease, box-shadow .15s ease;
}
.card:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,.06);}
.card .label{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.03em;}
.card .value{font-size:22px;font-weight:800;margin-top:6px;}
.tiny{font-size:12px;color:var(--muted);}

/* Table */
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;
  box-shadow:0 4px 10px rgba(0,0,0,.05);}
th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
th{background:#f9fafb;}
tbody tr:hover{background:#fdf2f2;}
.status-select{padding:6px 8px;border-radius:8px;border:1px solid #ccc;font:inherit;}
.status-select.offen{color:#b45309;border-color:#b45309;}
.status-select.angenommen{color:var(--ok);border-color:var(--ok);}
.status-select.abgelehnt{color:var(--bad);border-color:var(--bad);}
.action-btn{font-size:13px;text-decoration:none;margin-right:8px;}
.action-btn.edit{color:#1d4ed8;}
.action-btn.del{color:var(--bad);}
.action-btn.pdf{color:#000;}

/* Popups & Filter modal */
#popup,#actionPopup,#filterModal{
  display:none;position:fixed;inset:0;z-index:999999;
  background:rgba(0,0,0,.55);backdrop-filter:blur(3px);
  justify-content:center;align-items:center;
}
.popup-content{
  background:#fff;padding:26px 45px;border-radius:16px;font-size:18px;font-weight:600;
  box-shadow:0 8px 25px rgba(0,0,0,.3);animation:fadeIn .25s ease;text-align:center;
}
.popup-content.ok{border-left:6px solid var(--ok);color:var(--ok);}
.popup-content.err{border-left:6px solid var(--bad);color:var(--bad);}
#actionPopup .popup-content{font-size:16px;font-weight:500;}
#filterModal .popup-content{max-width:420px;width:90%;text-align:left;border-radius:18px;}
#filterModal label{font-size:14px;font-weight:600;color:#333;margin-top:10px;display:block;}
#filterModal input,#filterModal select{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;margin-top:5px;}
#filterModal .modal-actions{display:flex;justify-content:space-between;margin-top:20px;}
@keyframes fadeIn{from{opacity:0;transform:scale(.9);}to{opacity:1;transform:scale(1);}}
.layout,.sidebar,.content{overflow:visible!important;z-index:0!important;}
@media(max-width:800px){
  .layout{grid-template-columns:1fr;}
  .sidebar{position:fixed;top:0;left:0;width:80%;height:100%;background:#fff;
    border-right:1px solid var(--line);transform:translateX(-100%);
    transition:transform .3s ease;z-index:1000;}
  .sidebar.active{transform:translateX(0);}
  .menu-btn{display:block;}
  table,thead,tbody,tr,td,th{display:block;}
  thead{display:none;}
  tr{background:#fff;border:1px solid #eee;border-radius:10px;margin:8px 0;padding:6px;}
  td{display:flex;justify-content:space-between;padding:6px;}
  td::before{content:attr(data-label);font-weight:600;color:#555;}
}
@media(max-width:600px){
  #filterModal .popup-content{
    width:90%;max-width:none;margin:0 auto;border-radius:20px;padding:20px;
  }
}
</style>
</head>
<body>
<div class="layout">
  <?php include __DIR__.'/includes/sidebar.php'; ?>
  <main class="content">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:10px;">
        <button class="menu-btn" id="menuBtn">☰</button>
        <h2 style="margin:0;">Offerten – Übersicht</h2>
      </div>
      <div style="display:flex;gap:10px;">
        <button class="btn" id="filterBtn">🔍 Suche & Filtern</button>
        <a class="btn" href="offerte-erstellen.php">+ Neue Offerte</a>
      </div>
    </div>

    <!-- Rechnungen-style cards -->
    <section class="cards">
      <div class="card">
        <div class="label">Gesamt (Brutto)</div>
        <div class="value" id="cardGross"><?=h($totals['gross_sum'])?></div>
        <div class="tiny"><?= $totals['offer_count'] ?> Offerten insgesamt</div>
      </div>
      <div class="card">
        <div class="label">Angenommen</div>
        <div class="value" id="cardAccepted"><?=h($totals['accepted_sum'])?></div>
        <div class="tiny">Erfolgreiche Offerten</div>
      </div>
      <div class="card">
        <div class="label">Offen</div>
        <div class="value" id="cardOpen"><?=h($totals['open_sum'])?></div>
        <div class="tiny">Noch nicht entschieden</div>
      </div>
      <div class="card">
        <div class="label">Abgelehnt</div>
        <div class="value" id="cardDeclined"><?=h($totals['declined_sum'])?></div>
        <div class="tiny">Nicht angenommene Offerten</div>
      </div>
    </section>

    <table>
      <thead><tr><th>ID</th><th>Kunde</th><th>Offerte Nr.</th><th>Datum</th><th>Gültig bis</th><th>Total</th><th>Status</th><th>Aktionen</th></tr></thead>
      <tbody>
      <?php if($res->num_rows===0): ?>
        <tr><td colspan="8" style="text-align:center;color:#777">Keine Offerten gefunden</td></tr>
      <?php else: while($r=$res->fetch_assoc()): $s=$r['status']; ?>
        <tr data-id="<?=$r['id']?>">
          <td data-label="ID">#<?=$r['id']?></td>
          <td data-label="Kunde"><?=h($r['customer_name']?:'—')?></td>
          <td data-label="Offerte Nr."><?=h($r['offer_number']?:'—')?></td>
          <td data-label="Datum"><?=h($r['offer_date']?:'—')?></td>
          <td data-label="Gültig bis"><?=h($r['valid_until']?:'—')?></td>
          <td data-label="Total"><?=chf($r['total'])?></td>
          <td data-label="Status">
            <select class="status-select <?=h($s)?>" data-id="<?=$r['id']?>">
              <option value="offen"<?=$s==='offen'?' selected':''?>>Offen</option>
              <option value="angenommen"<?=$s==='angenommen'?' selected':''?>>Angenommen</option>
              <option value="abgelehnt"<?=$s==='abgelehnt'?' selected':''?>>Abgelehnt</option>
            </select>
          </td>
          <td data-label="Aktionen">
            <a class="action-btn edit" href="offerte-edit.php?id=<?=$r['id']?>">Bearbeiten</a>
            <a class="action-btn pdf" href="offerte_pdf.php?id=<?=$r['id']?>" target="_blank">PDF</a>
            <a class="action-btn del" href="#" data-del="<?=$r['id']?>">Löschen</a>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </main>
</div>

<!-- popups -->
<div id="popup"><div class="popup-content" id="popupMsg"></div></div>
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

<!-- Filter Modal -->
<div id="filterModal">
  <div class="popup-content">
    <h3>Suche & Filter</h3>
    <form method="get" id="filterForm">
      <label>Suchbegriff:</label>
      <input type="text" name="q" value="<?=h($q)?>" placeholder="Kunde oder Nr. …">
      <label>Status:</label>
      <select name="status">
        <option value="">– Alle –</option>
        <option value="offen"<?=$statusFilter==='offen'?' selected':''?>>Offen</option>
        <option value="angenommen"<?=$statusFilter==='angenommen'?' selected':''?>>Angenommen</option>
        <option value="abgelehnt"<?=$statusFilter==='abgelehnt'?' selected':''?>>Abgelehnt</option>
      </select>
      <label>Von:</label><input type="date" name="from" value="<?=h($dateFrom)?>">
      <label>Bis:</label><input type="date" name="to" value="<?=h($dateTo)?>">
      <div class="modal-actions">
        <button type="submit" class="btn" style="background:#16a34a;color:#fff;border:none;">Filtern</button>
        <a href="offerte.php" class="btn">Zurücksetzen</a>
      </div>
    </form>
  </div>
</div>

<script>
const sidebar=document.querySelector('.sidebar');
document.getElementById('menuBtn').onclick=()=>sidebar.classList.toggle('active');

// Filter modal
const filterModal=document.getElementById('filterModal');
document.getElementById('filterBtn').onclick=()=>{filterModal.style.display='flex';document.body.style.overflow='hidden';};
filterModal.onclick=e=>{if(e.target===filterModal){filterModal.style.display='none';document.body.style.overflow='';}};

// Popup toast
function showPopup(msg,ok=true){
  const p=document.getElementById('popup'),m=document.getElementById('popupMsg');
  m.textContent=msg;m.className='popup-content '+(ok?'ok':'err');
  p.style.display='flex';clearTimeout(showPopup._t);
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
  y.addEventListener('click',ok);n.addEventListener('click',cancel);
}

// Refresh totals
async function refreshTotals(d){
  if(d.gross_sum)document.getElementById('cardGross').textContent=d.gross_sum;
  if(d.accepted_sum)document.getElementById('cardAccepted').textContent=d.accepted_sum;
  if(d.open_sum)document.getElementById('cardOpen').textContent=d.open_sum;
  if(d.declined_sum)document.getElementById('cardDeclined').textContent=d.declined_sum;
}

// Autosave status
document.addEventListener('change',async e=>{
  const sel=e.target;if(!sel.matches('.status-select'))return;
  const id=sel.dataset.id,val=sel.value;
  try{
    const r=await fetch('offerte.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({ajax:'update_status_offerte',id,status:val})});
    const d=await r.json();
    if(!d.success)return showPopup(d.error||'Fehler',false);
    await refreshTotals(d);showPopup('Status gespeichert ✓',true);
  }catch{showPopup('Verbindungsfehler',false);}
});

// Delete confirm
document.addEventListener('click',e=>{
  const del=e.target.closest('[data-del]');if(!del)return;
  e.preventDefault();const id=del.dataset.del;
  confirmAction('Offerte löschen','Sind Sie sicher, dass Sie diese Offerte löschen möchten?',async()=>{
    try{
      const r=await fetch('offerte.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({ajax:'delete_offerte',id})});
      const d=await r.json();
      if(!d.success)return showPopup(d.error||'Fehler',false);
      document.querySelector(`tr[data-id="${id}"]`)?.remove();
      await refreshTotals(d);showPopup('Offerte gelöscht ✓',true);
    }catch{showPopup('Verbindungsfehler',false);}
  });
});
</script>
</body>
</html>
