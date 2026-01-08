<?php
/***************************************************
 * VERTRAG – Edit Page (same style as edit.php for invoices)
 * Loads contract by ID and includes vertrag_form.php
 ***************************************************/
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
  header('Location: login.php?next=' . urlencode($next));
  exit;
}

include 'config.php';

$id = (int)($_GET['id'] ?? 0);
$res = $conn->query("SELECT * FROM contracts WHERE id=$id");
if (!$res || $res->num_rows === 0) {
  die("Vertrag nicht gefunden");
}
$vertrag = $res->fetch_assoc();

include 'vertrag-erstellen.php';
