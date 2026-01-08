<?php

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
  header('Location: login.php?next='.urlencode($next));
  exit;
}
include 'config.php';
$invoice = [
  'id' => '',
  'customer_name' => '',
  'bill_to' => "Kunde Vorname Nachname\nStrasse 00,\nPLZ Ort",
  'invoice_number' => '',
  'invoice_date' => date('Y-m-d'),
  'ref_text' => '',
  'vat_rate' => '8.1',
  'discount' => '0.00',
  'subtotal' => '0.00',
  'vat_amount' => '0.00',
  'total' => '0.00',
  'amount_due' => '0.00',
  'remark' => '',
  'items_json' => '[]'
];
include 'invoice_form.php';
