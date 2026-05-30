<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';

requireRole(['Buyer']);

$conn = getConnection();

$userId = $_SESSION['user_id'] ?? 0;

$commentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($commentId <= 0 || $productId <= 0 || $userId <= 0) {
    header("Location: dashboard.php");
    exit();
}

/* =========================
   CHECK COMMENT OWNER
   Buyer can delete only their own comment
========================= */
$stmt = mysqli_prepare($conn, "
    SELECT comment_id, product_id, user_id
    FROM nps_comments
    WHERE comment_id = ?
      AND product_id = ?
      AND user_id = ?
    LIMIT 1
");

if (!$stmt) {
    header("Location: productDetails.php?id=" . $productId . "&error=delete_failed");
    exit();
}

mysqli_stmt_bind_param($stmt, "iii", $commentId, $productId, $userId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$comment = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$comment) {
    header("Location: productDetails.php?id=" . $productId . "&error=not_owner");
    exit();
}

/* =========================
   DELETE COMMENT
========================= */
$stmt = mysqli_prepare($conn, "
    DELETE FROM nps_comments
    WHERE comment_id = ?
      AND product_id = ?
      AND user_id = ?
");

if (!$stmt) {
    header("Location: productDetails.php?id=" . $productId . "&error=delete_failed");
    exit();
}

mysqli_stmt_bind_param($stmt, "iii", $commentId, $productId, $userId);
mysqli_stmt_execute($stmt);

$deleted = mysqli_stmt_affected_rows($stmt);

mysqli_stmt_close($stmt);

if ($deleted > 0) {
    header("Location: productDetails.php?id=" . $productId . "&success=comment_deleted");
    exit();
}

header("Location: productDetails.php?id=" . $productId . "&error=delete_failed");
exit();
?>