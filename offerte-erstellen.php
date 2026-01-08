<?php
/***************************************************
 * OFFERTEN – Neu erstellen -> Editor
 ***************************************************/
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = 'offerte-erstellen.php';
  header('Location: login.php?next=' . urlencode($next));
  exit;
}
header('Location: offerte-edit.php');
exit;
