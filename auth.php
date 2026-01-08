<?php
// auth.php  — only function definitions, nothing else.
require_once __DIR__.'/config.php';

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_check($t): bool {
  return is_string($t) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function current_user(): ?array {
  if (!empty($_SESSION['user_id'])) {
    static $cache;
    if ($cache) return $cache;
    $stmt = $GLOBALS['conn']->prepare("SELECT id,email,name,role FROM users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $cache = $stmt->get_result()->fetch_assoc() ?: null;
    return $cache;
  }
  return null;
}

function safe_next_url($cand, $default='index.php'): string {
  if (!$cand) return $default;
  $cand = urldecode($cand);
  if (preg_match('~^(?:https?:)?//~i', $cand)) return $default; // same-origin only
  foreach (['login.php','logout.php'] as $bad) if (stripos($cand,$bad)!==false) return $default;
  $cand = ltrim($cand);
  if ($cand === '' || $cand[0] === '?') return $default;
  return $cand[0]==='/' ? $cand : '/'.$cand;
}

function require_login(): void {
  if (!current_user()) {
    $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: login.php?next='.urlencode($next));
    exit;
  }
}

function login(string $email, string $password): bool {
  $stmt = $GLOBALS['conn']->prepare("SELECT id,password_hash FROM users WHERE email=? LIMIT 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    if (password_verify($password, $row['password_hash'])) {
      $_SESSION['user_id'] = (int)$row['id'];
      return true;
    }
  }
  return false;
}

function logout(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
  }
  session_destroy();
}
