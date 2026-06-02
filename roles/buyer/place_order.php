<?php
include_once '../../includes/auth_guard.php';
requireRole(['Buyer']);

// Checkout.php owns the order placement workflow. Keep this file only as a
// compatibility redirect for older links that still point to place_order.php.
header('Location: /NextPickStore/roles/buyer/checkout.php');
exit;
