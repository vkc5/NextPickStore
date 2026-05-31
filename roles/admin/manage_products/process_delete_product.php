<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /NextPickStore/roles/admin/manage_products/view_products.php');
    exit;
}

$conn = getConnection();

$productId = (int)($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    die('Invalid product ID.');
}

$sql = "
    UPDATE nps_products
    SET publish_status = 'hidden',
        updated_at = NOW()
    WHERE product_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: /NextPickStore/roles/admin/manage_products/view_products.php');
exit;