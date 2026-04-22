<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId = $_SESSION['user_id'] ?? 0;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: /NextPickStore/roles/seller/my_products.php?error=invalid");
    exit();
}

$productId = (int) $_GET['id'];

/* =========================
   CHECK PRODUCT BELONGS TO SELLER
========================= */
$checkSql = "
    SELECT product_id
    FROM nps_products
    WHERE product_id = ?
      AND seller_id = ?
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $checkSql);
mysqli_stmt_bind_param($stmt, "ii", $productId, $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$row = mysqli_fetch_assoc($result)) {
    mysqli_stmt_close($stmt);
    header("Location: /NextPickStore/roles/seller/my_products.php?error=notfound");
    exit();
}
mysqli_stmt_close($stmt);

/* =========================
   CHECK IF PRODUCT EXISTS IN ORDERS
========================= */
$orderCheckSql = "
    SELECT COUNT(*) AS total
    FROM nps_order_items
    WHERE product_id = ?
";
$stmt = mysqli_prepare($conn, $orderCheckSql);
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$orderRow = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ((int)$orderRow['total'] > 0) {
    header("Location: /NextPickStore/roles/seller/my_products.php?error=linked_orders");
    exit();
}

/* =========================
   DELETE PRODUCT
   product_images/comments/ratings/views cascade automatically
========================= */
$deleteSql = "
    DELETE FROM nps_products
    WHERE product_id = ?
      AND seller_id = ?
";
$stmt = mysqli_prepare($conn, $deleteSql);
mysqli_stmt_bind_param($stmt, "ii", $productId, $sellerId);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header("Location: /NextPickStore/roles/seller/my_products.php?deleted=1");
    exit();
} else {
    mysqli_stmt_close($stmt);
    header("Location: /NextPickStore/roles/seller/my_products.php?error=delete_failed");
    exit();
}
?>