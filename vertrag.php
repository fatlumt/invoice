<?php
/***************************************************
 * vertrag.php — Unified Design (same as Offerten & Rechnungen)
 ***************************************************/
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/php_errors.log');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

const TABLE_NAME = 'contracts';

/* ---------- Helpers ---------- */
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function chf($n){return number_format((float)$n,2,',',"'").' CHF';}
function json_out($arr,$status=200){http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($arr,JSON_UNESCAPED_UNICODE);exit;}
function get_totals(mysqli $c){
  $a=$c->query("SELECT
    COALESCE(SUM(total_amount),0) AS total_sum,
    COALESCE(SUM(CASE WHEN status='aktiv' THEN total_amount ELSE 0 END),0) AS active_sum,
    COALESCE(SUM(CASE WHEN status='abgelaufen' THEN total_amount ELSE 0 END),0) AS expired_sum,
    COALESCE(SUM(CASE WHEN status='gekuendigt' THEN total_amount ELSE 0 END),0) AS cancelled_sum,
    COUNT(*) AS contract_count
  FROM ".TABLE_NAME)->fetch_assoc();
  return [
    'total_sum'=>chf($a['total_sum']??0),
    'active_sum'=>chf($a['active_sum']??0),
    'expired_sum'=>chf($a['expired_sum']??0),
    'cancelled_sum'=>chf($a['cancelled_sum']??0),
    'contract_count'=>(int)($a['contract_count']??0)
  ];
}

/* ---------- AJAX ---------- */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax'])){
  if(empty($_SESSION['user'])) json_out(['success'=>false,'error'=>'Session abgelaufen'],403);
  $action=$_POST['ajax'];
  $id=(int)($_POST['id']??0);

  if($action==='update_status_vertrag'){
    $s=$_POST['status']??'';
    $st=$conn->prepare("UPDATE ".TABLE_NAME." SET status=? WHERE id=?");
    $st->bind_param('si',$s,$id);
    $st->execute();
    json_out(array_merge(['success'=>true],get_totals($conn)));
  }

  if($action==='delete_vertrag'){
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
$where=$params=[];$types='';
if($q!==''){ $where[]="(customer_name LIKE ? OR contract_number LIKE ?)"; $like="%$q%"; $types='ss'; $params=[$like,$like]; }
$whereSql=$where?'WHERE '.implode(' AND ',$where):'';
$sql="SELECT id,customer_name,contract_number,contract_date,total_amount,status FROM ".TABLE_NAME." $whereSql ORDER BY contract_date DESC";
$st=$conn->prepare($sql);
if($types)$st->bind_param($types,...$params);
$st->execute();$res=$st->get_result();
$totals=get_totals($conn);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Verträge – Übersicht</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --bg:#f5f7fb;--ink:#0f172a;--muted:#6b7280;--line:#e5e7eb;
  --accent:#ef4444;--ok:#16a34a;--warn:#b45309;--bad:#dc2626;
}
body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,system-ui,sans-serif;}
.layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh;}
.content{padding:24px;}
.topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;
  background:var(--accent);color:#fff;padding:12px 18px;border-radius:12px;margin-bottom:16px;}
.menu-btn{display:none;background:none;border:none;font-size:22px;color:#fff;cursor:pointer;margin-right:10px;}
.btn{background:#fff;color:#111;border:1px solid #ccc;padding:8px 12px;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none;}

/* Cards (same as Offerten/Rechnungen) */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:14px 0 18px;}
.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:16px 18px;
  box-shadow:0 4px 10px rgba(0,0,0,.04);transition:transform .15s ease, box-shadow .15s ease;}
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
.status-select.aktiv{color:var(--ok);border-color:var(--ok);}
.status-select.abgelaufen{color:var(--warn);border-color:var(--warn);}
.status-select.gekuendigt{color:var(--bad);border-color:var(--bad);}
.action-btn{font-size:13px;text-decoration:none;margin-right:8px;}
.action-btn.edit{color:#1d4ed8;}
.action-btn.del{color:var(--bad);}
.action-btn.pdf{color:#000;}

/* Popups */
#popup,#actionPopup{
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
@keyframes fadeIn{from{opacity:0;transform:scale(.9);}to{opacity:1;transform:scale(1);}}

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
</style>
</head>
<body>
<div class="layout">
  <?php include __DIR__.'/includes/sidebar.php'; ?>
  <main class="content">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:10px;">
        <button class="menu-btn" id="menuBtn">☰</button>
        <h2 style="margin:0;">Verträge – Übersicht</h2>
      </div>
      <a class="btn" href="vertrag-erstellen.php">+ Neuer Vertrag</a>
    </div>

    <!-- Rechnungen-style cards -->
    <section class="cards">
      <div class="card">
        <div class="label">Gesamt</div>
        <div class="value" id="cardTotal"><?=h($totals['total_sum'])?></div>
        <div class="tiny"><?= $totals['contract_count'] ?> Verträge insgesamt</div>
      </div>
      <div class="card">
        <div class="label">Aktiv</div>
        <div class="value" id="cardActive"><?=h($totals['active_sum'])?></div>
        <div class="tiny">Laufende Verträge</div>
      </div>
      <div class="card">
        <div class="label">Abgelaufen</div>
        <div class="value" id="cardExpired"><?=h($totals['expired_sum'])?></div>
        <div class="tiny">Bereits beendet</div>
      </div>
      <div class="card">
        <div class="label">Gekündigt</div>
        <div class="value" id="cardCancelled"><?=h($totals['cancelled_sum'])?></div>
        <div class="tiny">Vorzeitig beendet</div>
      </div>
    </section>

    <table>
      <thead><tr><th>ID</th><th>Kunde</th><th>Nr.</th><th>Datum</th><th>Betrag</th><th>Status</th><th>Aktionen</th></tr></thead>
      <tbody>
      <?php if($res->num_rows===0): ?>
        <tr><td colspan="7" style="text-align:center;color:#777">Keine Verträge gefunden</td></tr>
      <?php else: while($r=$res->fetch_assoc()): $s=$r['status']; ?>
        <tr data-id="<?=$r['id']?>">
          <td data-label="ID">#<?=$r['id']?></td>
          <td data-label="Kunde"><?=h($r['customer_name'])?></td>
          <td data-label="Nr."><?=h($r['contract_number'])?></td>
          <td data-label="Datum"><?=h($r['contract_date'])?></td>
          <td data-label="Betrag"><?=chf($r['total_amount'])?></td>
          <td data-label="Status">
            <select class="status-select <?=h($s)?>" data-id="<?=$r['id']?>">
              <option value="aktiv"<?=$s==='aktiv'?' selected':''?>>Aktiv</option>
              <option value="abgelaufen"<?=$s==='abgelaufen'?' selected':''?>>Abgelaufen</option>
              <option value="gekuendigt"<?=$s==='gekuendigt'?' selected':''?>>Gekündigt</option>
            </select>
          </td>
          <td data-label="Aktionen">
            <a class="action-btn edit" href="vertrag-edit.php?id=<?=$r['id']?>">Bearbeiten</a>
            <a class="action-btn pdf" href="vertrag_pdf.php?id=<?=$r['id']?>" target="_blank">PDF</a>
            <a class="action-btn del" href="#" data-del="<?=$r['id']?>">Löschen</a>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </main>
</div>

<!-- Popups -->
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

<script>
const sidebar=document.querySelector('.sidebar');
document.getElementById('menuBtn').onclick=()=>sidebar.classList.toggle('active');

function showPopup(msg,ok=true){
  const p=document.getElementById('popup'),m=document.getElementById('popupMsg');
  m.textContent=msg;m.className='popup-content '+(ok?'ok':'err');
  p.style.display='flex';clearTimeout(showPopup._t);
  showPopup._t=setTimeout(()=>p.style.display='none',1600);
}

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

async function refreshTotals(d){
  if(d.total_sum)document.getElementById('cardTotal').textContent=d.total_sum;
  if(d.active_sum)document.getElementById('cardActive').textContent=d.active_sum;
  if(d.expired_sum)document.getElementById('cardExpired').textContent=d.expired_sum;
  if(d.cancelled_sum)document.getElementById('cardCancelled').textContent=d.cancelled_sum;
}

document.addEventListener('change',async e=>{
  const sel=e.target;if(!sel.matches('.status-select'))return;
  const id=sel.dataset.id,val=sel.value;
  try{
    const r=await fetch('vertrag.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({ajax:'update_status_vertrag',id,status:val})});
    const d=await r.json();
    if(!d.success)return showPopup(d.error||'Fehler',false);
    await refreshTotals(d);showPopup('Status gespeichert ✓',true);
  }catch{showPopup('Verbindungsfehler',false);}
});

document.addEventListener('click',e=>{
  const del=e.target.closest('[data-del]');if(!del)return;
  e.preventDefault();const id=del.dataset.del;
  confirmAction('Vertrag löschen','Sind Sie sicher, dass Sie diesen Vertrag löschen möchten?',async()=>{
    try{
      const r=await fetch('vertrag.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({ajax:'delete_vertrag',id})});
      const d=await r.json();
      if(!d.success)return showPopup(d.error||'Fehler beim Löschen',false);
      document.querySelector(`tr[data-id="${id}"]`)?.remove();
      await refreshTotals(d);showPopup('Vertrag gelöscht ✓',true);
    }catch{showPopup('Verbindungsfehler',false);}
  });
});
</script>
</body>
</html>
