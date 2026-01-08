<?php
// login.php
require_once __DIR__.'/config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/* ---- helpers (safe if already defined elsewhere) ---- */
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
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

/* If already logged in → go home */
if (!empty($_SESSION['user'])) {
  header('Location: index.php'); exit;
}

$err = '';
$next = $_GET['next'] ?? '';
// allow only relative next like "/something" or "index.php"
$next_ok = (is_string($next) && $next !== '' && !preg_match('~^[a-z]+://~i', $next) && strpos($next, "\n")===false);

/* ---- handle POST ---- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check_post()) {
    $err = 'Ungültiges Formular (CSRF). Bitte erneut versuchen.';
  } else {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    if ($email==='' || $pass==='') {
      $err = 'Bitte E-Mail und Passwort eingeben.';
    } else {
      $stmt = $conn->prepare("SELECT id,email,name,password_hash,role FROM users WHERE LOWER(email)=? LIMIT 1");
      $stmt->bind_param("s",$email);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($u = $res->fetch_assoc()) {
        if (password_verify($pass, $u['password_hash'])) {
          session_regenerate_id(true);
          $_SESSION['user'] = [
            'id'    => (int)$u['id'],
            'email' => $u['email'],
            'name'  => $u['name'],
            'role'  => $u['role'],
            'at'    => time(),
          ];
          // optional: rotate CSRF after login
          $_SESSION['csrf'] = bin2hex(random_bytes(32));
          $dest = ($next_ok ? $next : 'index.php');
          header("Location: {$dest}");
          exit;
        }
      }
      $err = 'E-Mail oder Passwort ist falsch.';
    }
  }
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Login – Krasniqi Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
  :root{ --bg:#f5f7fb; --ink:#0f172a; --muted:#6b7280; --line:#e5e7eb;
         --accent:#dc2626; --accent2:#b91c1c; --card:#fff; --radius:14px; }
  *{box-sizing:border-box}
  html,body{margin:0;background:var(--bg);color:var(--ink);
    font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
  .wrap{min-height:100dvh;display:grid;place-items:center;padding:20px}
  .card{width:100%;max-width:420px;background:var(--card);border:1px solid var(--line);
        border-radius:var(--radius);padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.06)}
  .brand{display:flex;align-items:center;gap:10px;margin-bottom:12px}
  .logo{width:36px;height:36px;border:2px solid var(--accent);border-radius:9px}
  h1{margin:0;font-size:18px}
  label{display:block;font-size:12px;color:var(--muted);margin-top:10px}
  input{width:100%;padding:12px;border:1px solid var(--line);border-radius:10px;font:inherit}
  .row{display:flex;justify-content:space-between;align-items:center;margin-top:12px}
  .btn{display:inline-block;background:var(--accent);color:#fff;border:1px solid var(--accent2);
       border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer}
  .btn:hover{background:var(--accent2)}
  .muted{color:var(--muted);font-size:12px}
  .flash{padding:10px 12px;border-radius:10px;margin:10px 0;font-size:13px}
  .flash.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
</style>
</head>
<body>
<div class="wrap">
  <form class="card" method="post" autocomplete="off">
    <div class="brand"><div class="logo"></div><h1>Krasniqi – Login</h1></div>
    <?php if ($err): ?><div class="flash err"><?= h($err) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <?php if ($next_ok): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>

    <label>E-Mail</label>
    <input type="email" name="email" required autofocus>

    <label>Passwort</label>
    <input type="password" name="password" required>

    <div class="row">
      <span class="muted">Tipp: Admin über <code>users.php</code> anlegen</span>
      <button class="btn" type="submit">Einloggen</button>
    </div>
  </form>
</div>
</body>
</html>
