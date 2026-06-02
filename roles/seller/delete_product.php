<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /NextPickStore/roles/seller/my_products.php?error=invalid_method");
    exit();
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header("Location: /NextPickStore/roles/seller/my_products.php?error=invalid");
    exit();
}

$productId = (int) $_POST['id'];

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
   HIDE PRODUCT INSTEAD OF DELETING
========================= */
$hideSql = "
    UPDATE nps_products
    SET publish_status = 'hidden',
        updated_at = NOW()
    WHERE product_id = ?
      AND seller_id = ?
";
$stmt = mysqli_prepare($conn, $hideSql);
mysqli_stmt_bind_param($stmt, "ii", $productId, $sellerId);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header("Location: /NextPickStore/roles/seller/my_products.php?hidden=1");
    exit();
} else {
    mysqli_stmt_close($stmt);
    header("Location: /NextPickStore/roles/seller/my_products.php?error=hide_failed");
    exit();
}
?>
