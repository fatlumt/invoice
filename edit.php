<?php

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
  header('Location: login.php?next='.urlencode($next));
  exit;
}
include 'config.php';
$id = (int)$_GET['id'];
$res = $conn->query("SELECT * FROM invoices WHERE id=$id");
if (!$res || $res->num_rows === 0) die("Rechnung nicht gefunden");
$invoice = $res->fetch_assoc();
include 'invoice_form.php';
