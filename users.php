<?php

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
  header('Location: login.php?next='.urlencode($next));
  exit;
}
require_once __DIR__.'/config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/* helpers (safe if already defined elsewhere) */
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('csrf_token')) {
  function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
}
if (!function_exists('csrf_check_post')) {
  function csrf_check_post(): bool {
    if ($_SERVER['REQUEST_METHOD']!=='POST') return true;
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $ok;
  }
}
function role_ok($r){ return in_array($r, ['admin','staff'], true); }
function count_admins(mysqli $conn): int {
  $r = $conn->query("SELECT COUNT(*) n FROM users WHERE role='admin'")->fetch_assoc();
  return (int)($r['n'] ?? 0);
}

/* actions */
$errors=[]; $oks=[];
if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (!csrf_check_post()){
    $errors[]='Ungültiges Formular (CSRF). Bitte erneut versuchen.';
  } else {
    $act = $_POST['action'] ?? '';
    try{
      if ($act==='create'){
        $name = trim($_POST['name'] ?? '');
        $email= trim($_POST['email']?? '');
        $role = trim($_POST['role'] ?? 'staff');
        $p1   = (string)($_POST['password'] ?? '');
        $p2   = (string)($_POST['password2']?? '');
        if ($name===''||$email===''||$p1===''||$p2==='')          $errors[]='Bitte alle Felder ausfüllen.';
        elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))         $errors[]='Bitte eine gültige E-Mail angeben.';
        elseif(!role_ok($role))                                   $errors[]='Ungültige Rolle.';
        elseif($p1!==$p2)                                         $errors[]='Passwörter stimmen nicht überein.';
        elseif(strlen($p1)<6)                                     $errors[]='Passwort mindestens 6 Zeichen.';
        else {
          $hash = password_hash($p1, PASSWORD_DEFAULT);
          $st = $conn->prepare("INSERT INTO users(email,name,password_hash,role) VALUES(?,?,?,?)");
          $st->bind_param("ssss",$email,$name,$hash,$role);
          $st->execute();
          $oks[]='Benutzer angelegt.';
        }
      }
      elseif ($act==='role'){
        $uid=(int)($_POST['user_id']??0); $new=trim($_POST['new_role']??'');
        if ($uid<=0 || !role_ok($new)) $errors[]='Ungültige Eingabe.';
        else {
          $cur = $conn->prepare("SELECT role FROM users WHERE id=?");
          $cur->bind_param("i",$uid); $cur->execute();
          $row = $cur->get_result()->fetch_assoc();
          if ($row && $row['role']==='admin' && $new!=='admin' && count_admins($conn)<=1){
            $errors[]='Der letzte Admin kann nicht degradiert werden.';
          } else {
            $up=$conn->prepare("UPDATE users SET role=? WHERE id=?");
            $up->bind_param("si",$new,$uid); $up->execute();
            $oks[]='Rolle aktualisiert.';
          }
        }
      }
      elseif ($act==='reset_pw'){
        $uid=(int)($_POST['user_id']??0);
        $p1=(string)($_POST['new_password']??''); $p2=(string)($_POST['new_password2']??'');
        if ($uid<=0) $errors[]='Ungültige Benutzer-ID.';
        elseif ($p1===''||$p2==='') $errors[]='Bitte Passwort eingeben.';
        elseif ($p1!==$p2) $errors[]='Passwörter stimmen nicht überein.';
        elseif (strlen($p1)<6) $errors[]='Passwort mindestens 6 Zeichen.';
        else {
          $hash=password_hash($p1,PASSWORD_DEFAULT);
          $up=$conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
          $up->bind_param("si",$hash,$uid); $up->execute();
          $oks[]='Passwort gespeichert.';
        }
      }
      elseif ($act==='delete'){
        $uid=(int)($_POST['user_id']??0);
        if ($uid<=0) $errors[]='Ungültige Benutzer-ID.';
        else{
          $cur=$conn->prepare("SELECT role FROM users WHERE id=?");
          $cur->bind_param("i",$uid); $cur->execute();
          $row=$cur->get_result()->fetch_assoc();
          if ($row && $row['role']==='admin' && count_admins($conn)<=1){
            $errors[]='Der letzte Admin kann nicht gelöscht werden.';
          }else{
            $del=$conn->prepare("DELETE FROM users WHERE id=?");
            $del->bind_param("i",$uid); $del->execute();
            $oks[]='Benutzer gelöscht.';
          }
        }
      }
    } catch (Throwable $e){ $errors[]='Fehler: '.$e->getMessage(); }
  }
}

/* list */
$users=[];
$r=$conn->query("SELECT id,email,name,role,created_at FROM users ORDER BY created_at DESC, id DESC");
while($row=$r->fetch_assoc()) $users[]=$row;
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Benutzer – Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
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

/* ==== SIDEBAR: exact copy from index.php ==== */
.layout{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh}
.sidebar{background:#fff;border-right:1px solid var(--line);padding:18px;position:sticky;top:0;height:100dvh;z-index:20;transition:transform .25s ease}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.brand .logo{width:36px;height:36px;border:2px solid var(--accent-600);border-radius:9px}
.brand h1{font-size:16px;margin:0}
.menu a{display:flex;gap:10px;padding:10px 12px;border-radius:10px;color:#111;font-weight:500}
.menu a:hover{background:var(--accent-50)} .menu a.active{background:var(--accent-600);color:#fff}
.menu .section-title{margin:12px 8px 6px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}

/* main/topbar from index */
.content{padding:22px 26px}
.topbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:16px}
.title{font-size:20px;font-weight:700;margin:0}
.burger{display:none;align-items:center;gap:6px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer}
.burger span{display:block;width:18px;height:2px;background:#111;position:relative}
.burger span::before,.burger span::after{content:"";position:absolute;left:0;width:18px;height:2px;background:#111}
.burger span::before{top:-6px} .burger span::after{top:6px}

/* panels (same style family as index) */
.panel{background:#fff;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;box-shadow:0 6px 14px rgba(0,0,0,.05);margin-bottom:16px}
.panel .hd{display:flex;justify-content:space-between;align-items:center;padding:12px;border-bottom:1px solid var(--line);background:#fff}
.card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:16px;box-shadow:0 4px 10px rgba(0,0,0,.04)}

.row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
label{display:block;font-size:12px;color:var(--muted);margin-top:8px}
input,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff}
.btn{background:var(--accent-600);color:#fff;border:1px solid var(--accent-700);padding:10px 14px;border-radius:10px;font-weight:600;cursor:pointer}
.btn:hover{background:var(--accent-700)}
.btn-ghost{background:#fff;color:var(--accent-700);border:1px solid var(--accent-200)}
.flash{padding:10px 12px;border-radius:10px;margin-bottom:10px;font-size:13px}
.flash.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.flash.ok{background:#ecfdf5;border:1px solid #bbf7d0;color:#065f46}

.grid{display:grid;grid-template-columns:340px 1fr;gap:18px}
@media (max-width:900px){
  .layout{grid-template-columns:1fr}
  .sidebar{position:fixed;left:0;top:0;height:100dvh;width:var(--sidebar-w);transform:translateX(-100%);box-shadow:0 20px 40px rgba(0,0,0,.2)}
  .sidebar.open{transform:translateX(0)}
  .grid{grid-template-columns:1fr}
  .burger{display:flex}
}

/* table */
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left}
th{font-size:12px;color:#111}
.muted{color:var(--muted)}
</style>
</head>
<body>
<div class="layout">
  <!-- ==== SAME SIDEBAR MARKUP AS index.php ==== -->
  <!-- <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="logo"></div><h1>Krasniqi – Admin</h1>
    </div>
    <nav class="menu">
      <div class="section-title">Navigation</div>
      <a href="index.php">Dashboard</a>
      <a href="create.php">+ Neue Rechnung</a>
      <a href="#" title="In Arbeit">Berichte</a>
      <a href="#" title="In Arbeit">Einstellungen</a>
      <a href="users.php">Benutzer</a>
    </nav>
  </aside> -->
<?php include __DIR__.'/includes/sidebar.php'; ?>

  <main class="content">
    <div class="topbar">
      <button class="burger" type="button" aria-label="Menü" onclick="document.getElementById('sidebar').classList.toggle('open')">
        <span></span>
      </button>
      <h2 class="title">Benutzerverwaltung</h2>
      <div></div>
    </div>

    <div class="grid">
      <!-- Create user -->
      <div class="card">
        <h3 style="margin:0 0 8px">Benutzer anlegen</h3>
        <?php foreach($errors as $m): ?><div class="flash err"><?= h($m) ?></div><?php endforeach; ?>
        <?php foreach($oks as $m): ?><div class="flash ok"><?= h($m) ?></div><?php endforeach; ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="create">
          <label>Name</label>
          <input type="text" name="name" required>
          <label>E-Mail</label>
          <input type="email" name="email" required>
          <label>Rolle</label>
          <select name="role">
            <option value="staff">Mitarbeiter</option>
            <option value="admin">Admin</option>
          </select>
          <label>Passwort</label>
          <input type="password" name="password" minlength="6" required>
          <label>Passwort wiederholen</label>
          <input type="password" name="password2" minlength="6" required>
          <div style="margin-top:12px"><button class="btn" type="submit">Speichern</button></div>
        </form>
      </div>

      <!-- Users table -->
      <div class="card">
        <h3 style="margin:0 0 8px">Alle Benutzer</h3>
        <table>
          <thead>
            <tr><th style="width:48px">ID</th><th>Name</th><th>E-Mail</th><th style="width:140px">Rolle</th><th>Erstellt</th><th>Aktionen</th></tr>
          </thead>
          <tbody>
            <?php if (!$users): ?>
              <tr><td colspan="6" class="muted">Keine Benutzer vorhanden.</td></tr>
            <?php else: foreach($users as $u): ?>
              <tr>
                <td>#<?= (int)$u['id'] ?></td>
                <td><?= h($u['name']) ?></td>
                <td><?= h($u['email']) ?></td>
                <td>
                  <form method="post" class="row" style="gap:6px;margin:0">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="role">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <select name="new_role">
                      <option value="staff" <?= $u['role']==='staff'?'selected':'' ?>>staff</option>
                      <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>admin</option>
                    </select>
                    <button class="btn btn-ghost" type="submit">OK</button>
                  </form>
                </td>
                <td class="muted"><?= h($u['created_at']) ?></td>
                <td>
                  <form method="post" class="row" style="margin:0;gap:6px">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="reset_pw">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="password" name="new_password" placeholder="Neues PW" minlength="6" required>
                    <input type="password" name="new_password2" placeholder="Wiederholen" minlength="6" required>
                    <button class="btn" type="submit">PW</button>
                  </form>
                  <form method="post" onsubmit="return confirm('Benutzer wirklich löschen?')" style="display:inline-block;margin-left:6px">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-ghost" type="submit">Löschen</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script>
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('open'); }
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
</script>
</body>
</html>
