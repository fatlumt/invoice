<?php

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) {
  $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
  header('Location: login.php?next='.urlencode($next));
  exit;
}
include 'config.php';

$id = (int)($_POST['id'] ?? 0);
$customer = $conn->real_escape_string($_POST['customer_name'] ?? '');
$bill_to  = $conn->real_escape_string($_POST['bill_to'] ?? '');
$inv_no   = $conn->real_escape_string($_POST['invoice_number'] ?? '');
$inv_date = $conn->real_escape_string($_POST['invoice_date'] ?? date('Y-m-d'));
$ref      = $conn->real_escape_string($_POST['ref_text'] ?? '');
$vat      = (float)($_POST['vat_rate'] ?? 0);
$discount = (float)($_POST['discount'] ?? 0);
$subtotal = (float)($_POST['subtotal'] ?? 0);
$vat_amt  = (float)($_POST['vat_amount'] ?? 0);
$total    = (float)($_POST['total'] ?? 0);
$due      = (float)($_POST['amount_due'] ?? 0);
$remark   = $conn->real_escape_string($_POST['remark'] ?? '');
$items    = $conn->real_escape_string($_POST['items_json'] ?? '[]');

if ($id > 0) {
  $sql = "UPDATE invoices SET 
    customer_name='$customer', bill_to='$bill_to', invoice_number='$inv_no',
    invoice_date='$inv_date', ref_text='$ref', vat_rate=$vat, discount=$discount,
    subtotal=$subtotal, vat_amount=$vat_amt, total=$total, amount_due=$due,
    remark='$remark', items_json='$items' WHERE id=$id";
} else {
  $sql = "INSERT INTO invoices
    (customer_name, bill_to, invoice_number, invoice_date, ref_text, vat_rate, discount, subtotal, vat_amount, total, amount_due, remark, items_json)
    VALUES ('$customer','$bill_to','$inv_no','$inv_date','$ref',$vat,$discount,$subtotal,$vat_amt,$total,$due,'$remark','$items')";
}

if ($conn->query($sql)) {
  header("Location: index.php");
  exit;
} else {
  echo "Fehler: " . $conn->error;
}
