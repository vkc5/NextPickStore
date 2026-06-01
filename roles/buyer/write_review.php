<?php
include_once '../../includes/auth_guard.php';
requireRole(['Buyer']);

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($productId > 0) {
    header('Location: /NextPickStore/roles/buyer/productDetails.php?id=' . $productId . '#reviews');
    exit;
}

header('Location: /NextPickStore/roles/buyer/reviews.php');
exit;
