<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';

requireRole(['Buyer']);

$conn = getConnection();

$userId = (int)($_SESSION['user_id'] ?? 0);
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($userId <= 0 || $productId <= 0) {
    header("Location: dashboard.php");
    exit();
}

$stmt = mysqli_prepare($conn, "
    DELETE FROM nps_ratings
    WHERE product_id = ?
      AND user_id = ?
");

if (!$stmt) {
    header("Location: productDetails.php?id=" . $productId . "&error=rating_delete_failed");
    exit();
}

mysqli_stmt_bind_param($stmt, "ii", $productId, $userId);
mysqli_stmt_execute($stmt);
$deleted = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if ($deleted > 0) {
    header("Location: productDetails.php?id=" . $productId . "&success=rating_deleted");
    exit();
}

header("Location: productDetails.php?id=" . $productId . "&error=rating_delete_failed");
exit();
?>
