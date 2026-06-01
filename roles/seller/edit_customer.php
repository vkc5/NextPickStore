<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);

$customerId = (int)($_GET['id'] ?? 0);

if ($customerId > 0) {
    header('Location: /NextPickStore/roles/seller/customer_orders.php?id=' . $customerId);
    exit;
}

header('Location: /NextPickStore/roles/seller/customer_data.php');
exit;
