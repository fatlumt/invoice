<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  header('Location: login.php');
  exit;
}

include 'config.php'; // ✅ database connection

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
  $stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
}

// ✅ Redirect back to list
header("Location: rechnungen.php");
exit;