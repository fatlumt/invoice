<?php
/***************************************************
 * VERTRAG – Final Professional Edition
 * - Compact A4 layout (balanced typography)
 * - Sidebar + Editor + Add Section
 * - Proper padding, header + footer
 * - Saves only #bodyContent
 * - PDF export (html2canvas + jsPDF)
 ***************************************************/
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$mysqli = new mysqli('localhost', 'root', '', 'invoices_db');
if ($mysqli->connect_error) die('DB connection failed: '.$mysqli->connect_error);
$mysqli->set_charset('utf8mb4');

$company = $mysqli->query("SELECT * FROM company_profile ORDER BY id DESC LIMIT 1")->fetch_assoc();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = null;
if ($id > 0) {
  $stmt = $mysqli->prepare("SELECT * FROM contracts WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
}

/* ---- Save ---- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $isAjax = isset($_POST['ajax']) && $_POST['ajax']==='1';
  $id = (int)($_POST['id'] ?? 0);
  $fields = [
    'customer_name','customer_address','contract_number','contract_date',
    'title','body','total_amount','payment_terms','signer','signer_title'
  ];
  $data = [];
  foreach($fields as $f) $data[$f] = $_POST[$f] ?? '';

  if ($id>0) {
    $q=$mysqli->prepare("UPDATE contracts SET customer_name=?,customer_address=?,contract_number=?,contract_date=?,title=?,body=?,total_amount=?,payment_terms=?,signer=?,signer_title=? WHERE id=?");
    $q->bind_param('ssssssdsssi',
      $data['customer_name'],$data['customer_address'],$data['contract_number'],$data['contract_date'],
      $data['title'],$data['body'],$data['total_amount'],$data['payment_terms'],$data['signer'],$data['signer_title'],$id);
    $ok=$q->execute();
  } else {
    $q=$mysqli->prepare("INSERT INTO contracts (customer_name,customer_address,contract_number,contract_date,title,body,total_amount,payment_terms,signer,signer_title) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $q->bind_param('ssssssdsss',
      $data['customer_name'],$data['customer_address'],$data['contract_number'],$data['contract_date'],
      $data['title'],$data['body'],$data['total_amount'],$data['payment_terms'],$data['signer'],$data['signer_title']);
    $ok=$q->execute();
    if ($ok) $id=$mysqli->insert_id;
  }

  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok'=>$ok,'id'=>$id,'error'=>$ok?null:$mysqli->error]);
    exit;
  } else {
    header('Location: vertrag_form.php?id='.$id.($ok?'&ok=1':'&err=1'));
    exit;
  }
}

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$companyName = $company['name'] ?? 'Krasniqi Plattenleger';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Vertrag <?= $id?'#'.$id:'neu' ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<style>
:root{
  --accent:#dc2626;--accent-dark:#b91c1c;--line:#e5e7eb;
  --bg:#f6f7fa;--sidebar-w:260px;
}
*{box-sizing:border-box}
body{margin:0;font-family:"Inter","Segoe UI",system-ui,sans-serif;background:var(--bg);color:#111;}
.layout{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh}
.sidebar{transition:transform .3s ease}
.content{padding:20px}
.topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;background:var(--accent);color:#fff;padding:12px 18px;border-radius:12px;margin-bottom:16px}
.title{font-weight:700;margin:0;font-size:20px}
.menu-btn{display:none;background:none;border:none;font-size:22px;color:#fff;cursor:pointer;margin-right:10px}
.btn{background:var(--accent);color:#fff;border:none;padding:8px 14px;border-radius:10px;cursor:pointer;font-weight:600}
.btn:hover{background:var(--accent-dark)}
.btn.secondary{background:#fff;color:#111;border:1px solid var(--line)}

#exportWrap{background:#fff;margin:auto;max-width:900px;box-shadow:0 4px 10px rgba(0,0,0,.1);padding:20px;border-radius:16px}

/* === Compact professional print style === */
.doc{
  font-size:15px;
  line-height:1.5;
  background:#fff;
  padding:15mm 17mm;
  color:#111;
  border:1px solid #f2f2f2;
}
.doc h1{text-align:center;color:var(--accent-dark);font-size:19px;margin:9mm 0 6mm;}
.doc h3{font-size:17px;margin:4mm 0 2mm;color:#000;}
.doc p,.doc li{font-size:15px;margin-bottom:3mm;}
.doc ul{margin:3mm 0 5mm 6mm}

.doc-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:9mm;}
.logo-box img{max-width:120px;max-height:50px;object-fit:contain;}
.company-block{text-align:right;font-size:13px;line-height:1.4;color:#444;}
.divider{height:2px;background:var(--accent);border-radius:2px;margin:5mm 0 8mm;}
.signatures{display:flex;justify-content:space-between;margin-top:14mm;gap:10mm;}
.sig-box{flex:1;text-align:center;}
.sig-line{border-top:1px solid #222;width:75%;margin:14mm auto 4px;padding-top:4px;font-size:12.5px;}
.sig-label{font-size:12.5px;color:#555;}

/* Popup */
.popup{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(2px);z-index:9999;justify-content:center;align-items:center}
.popup-content{background:#fff;padding:22px 40px;border-radius:16px;font-size:18px;font-weight:600;box-shadow:0 8px 25px rgba(0,0,0,.3)}
.popup-content.ok{border-left:6px solid #16a34a;color:#16a34a}
.popup-content.err{border-left:6px solid #dc2626;color:#dc2626}

/* Editor */
.editor{position:fixed;right:20px;top:90px;width:340px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px;box-shadow:0 4px 20px rgba(0,0,0,.15);transition:transform .3s}
.editor.closed{transform:translateX(calc(100% + 20px))}
.editor label{font-size:13px;color:#555;margin-top:8px;display:block}
.editor input,.editor textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px}
.toggle{position:absolute;left:-32px;top:16px;width:32px;height:32px;border-radius:8px 0 0 8px;background:var(--accent);color:#fff;display:grid;place-items:center;cursor:pointer}
.editor .btn-row{margin-top:10px;display:flex;gap:6px;flex-wrap:wrap}

/* Responsive */
@media(max-width:900px){
  .layout{grid-template-columns:1fr}
  .sidebar{position:fixed;top:0;left:0;width:80%;height:100%;background:#fff;border-right:1px solid var(--line);transform:translateX(-100%);z-index:1000}
  .sidebar.active{transform:translateX(0)}
  .menu-btn{display:block}
  .editor{position:fixed;bottom:0;top:auto;left:0;right:0;width:100%;border-radius:16px 16px 0 0;padding:16px}
  .editor.closed{transform:translateY(100%)}
  .toggle{top:-38px;left:50%;transform:translateX(-50%) rotate(90deg)}
  #exportWrap{padding:14px;margin-bottom:160px}
}
@media print{
  .sidebar,.topbar,.editor{display:none!important}
  body{background:#fff;transform:scale(1.05);transform-origin:top left;}
  #exportWrap{box-shadow:none;margin:0;}
  .doc{padding:13mm 15mm;font-size:15px;line-height:1.5;}
}
</style>
</head>
<body>
<div class="layout">
  <?php include __DIR__.'/includes/sidebar.php'; ?>

  <main class="content">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:8px;">
        <button class="menu-btn" id="menuBtn">☰</button>
        <h2 class="title">Vertrag <?= $id?'#'.$id:'(neu)' ?></h2>
      </div>
      <div>
        <a href="vertrag.php" class="btn secondary">‹ Übersicht</a>
        <button class="btn" type="button" onclick="saveContract()">💾 Speichern</button>
        <button class="btn" type="button" onclick="downloadPDF()">📄 PDF</button>
      </div>
    </div>

    <form method="post" id="saveForm" style="display:none">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <input type="hidden" name="customer_name" id="f_customer">
      <input type="hidden" name="customer_address" id="f_address">
      <input type="hidden" name="contract_number" id="f_number">
      <input type="hidden" name="contract_date" id="f_date">
      <input type="hidden" name="title" id="f_title">
      <input type="hidden" name="body" id="f_body">
      <input type="hidden" name="total_amount" id="f_total">
      <input type="hidden" name="payment_terms" id="f_terms">
      <input type="hidden" name="signer" id="f_signer">
      <input type="hidden" name="signer_title" id="f_signer_title">
    </form>

    <div id="exportWrap">
      <div class="doc" id="doc" contenteditable="true">
        <div class="doc-header">
          <div class="logo-box">
            <?php if (!empty($company['logo_path'])): ?>
              <img src="<?= h($company['logo_path']) ?>" alt="Logo">
            <?php else: ?><div style="width:110px;height:45px;border:2px solid var(--accent);border-radius:8px"></div><?php endif; ?>
          </div>
          <div class="company-block">
            <div><b><?= h($companyName) ?></b></div>
            <div><?= h($company['street'] ?? 'Baslerstrasse 349') ?></div>
            <div><?= h(($company['zip'] ?? '4123').' '.($company['city'] ?? 'Allschwil')) ?></div>
            <div>+<?= h(preg_replace('/\D/','',$company['phone'] ?? '41765030788')) ?></div>
            <div><?= h($company['email'] ?? 'info@krasniqi-plattenleger.ch') ?></div>
          </div>
        </div>

        <div class="divider"></div>
        <h1><?= h($row['title'] ?? 'VERTRAG') ?></h1>

        <p><b>Kunde:</b> <?= h($row['customer_name'] ?? '[Kunde]') ?><br>
        <b>Adresse:</b> <?= h($row['customer_address'] ?? '') ?><br>
        <b>Datum:</b> <?= h($row['contract_date'] ?? date('Y-m-d')) ?></p>

        <p>Zwischen dem Kunden und <b><?= h($companyName) ?></b> wird folgender Vertrag geschlossen:</p>

        <div id="bodyContent">
          <?= $row['body'] ?? '<p style="opacity:.7">Bitte Vertragsinhalt einfügen …</p>' ?>
        </div>

        <div class="signatures">
          <div class="sig-box">
            <div class="sig-line">Unterschrift / Stempel</div>
            <div class="sig-label"><?= h($company['signer'] ?? 'SHKELQIM KRASNIQI') ?></div>
            <div class="sig-label"><?= h($company['signer_title'] ?? 'Geschäftsführer') ?></div>
          </div>
          <div class="sig-box">
            <div class="sig-line">Unterschrift</div>
            <div class="sig-label"><?= h($row['customer_name'] ?? 'Kunde') ?></div>
            <div class="sig-label"><?= h($row['customer_address'] ?? 'Adresse') ?></div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- === Slide Editor === -->
<div class="editor closed" id="editor">
  <button class="toggle" onclick="toggleEditor()">▸</button>
  <label>Kunde</label><input id="iName" value="<?= h($row['customer_name'] ?? '') ?>">
  <label>Adresse</label><input id="iAddr" value="<?= h($row['customer_address'] ?? '') ?>">
  <label>Nr.</label><input id="iNumber" value="<?= h($row['contract_number'] ?? '') ?>">
  <label>Datum</label><input type="date" id="iDate" value="<?= h($row['contract_date'] ?? date('Y-m-d')) ?>">
  <label>Betrag (CHF)</label><input id="iTotal" value="<?= h($row['total_amount'] ?? '') ?>">
  <label>Zahlungsbedingungen</label><textarea id="iTerms" rows="3"><?= h($row['payment_terms'] ?? '') ?></textarea>
  <div class="btn-row">
    <button class="btn" type="button" onclick="saveContract()">💾</button>
    <button class="btn secondary" type="button" onclick="downloadPDF()">PDF</button>
  </div>
</div>

<div id="popup" class="popup"><div class="popup-content" id="popupMsg"></div></div>

<script>
const $=id=>document.getElementById(id);
function toggleEditor(){$('editor').classList.toggle('closed');}
const sidebar=document.querySelector('.sidebar');
const menuBtn=document.getElementById('menuBtn');
if(menuBtn)menuBtn.onclick=()=>sidebar.classList.toggle('active');

function fillForm(){
  $('f_customer').value=$('iName').value;
  $('f_address').value=$('iAddr').value;
  $('f_number').value=$('iNumber').value;
  $('f_date').value=$('iDate').value;
  $('f_title').value='VERTRAG';
  $('f_body').value=document.getElementById('bodyContent').innerHTML;
  $('f_total').value=$('iTotal').value;
  $('f_terms').value=$('iTerms').value;
}
function saveContract(){fillForm();$('saveForm').submit();}
function showPopup(msg,ok=true){
  const p=$('popup'),m=$('popupMsg');
  m.textContent=msg;m.className='popup-content '+(ok?'ok':'err');
  p.style.display='flex';clearTimeout(showPopup._t);
  showPopup._t=setTimeout(()=>p.style.display='none',1500);
}
async function saveAjax(){
  fillForm();
  const fd=new FormData($('saveForm'));fd.set('ajax','1');
  try{
    const r=await fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json();
    if(j.ok){history.replaceState({},'',`?id=${j.id}`);showPopup('Gespeichert ✓',true);return true;}
    showPopup('Fehler beim Speichern',false);
  }catch(e){showPopup('Verbindungsfehler',false);}
  return false;
}
async function downloadPDF(){
  if(!(await saveAjax()))return;
  const {jsPDF}=window.jspdf;
  const pdf=new jsPDF('p','mm','a4');
  const c=await html2canvas($('doc'),{scale:3,backgroundColor:'#fff',useCORS:true});
  const img=c.toDataURL('image/png');
  const W=pdf.internal.pageSize.getWidth(),H=pdf.internal.pageSize.getHeight();
  pdf.addImage(img,'PNG',0,0,W,H,'','FAST');
  pdf.save(`${$('iName').value||'Vertrag'}.pdf`);
}
</script>
</body>
</html>
