<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /NextPickStore/roles/admin/manage_products/manage_comments.php');
    exit;
}

$conn = getConnection();
$adminId = (int)($_SESSION['user_id'] ?? 0);

$commentId = (int)($_POST['comment_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);

if ($commentId <= 0 || $productId <= 0) {
    die('Invalid request.');
}

if ($adminId > 0) {
    $adminStmt = mysqli_prepare($conn, "SET @current_admin_id = ?");
    mysqli_stmt_bind_param($adminStmt, "i", $adminId);
    mysqli_stmt_execute($adminStmt);
    mysqli_stmt_close($adminStmt);
}

$sql = "DELETE FROM nps_comments WHERE comment_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $commentId);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['comment_delete_success'] = 'Comment deleted successfully.';
} else {
    $_SESSION['comment_delete_success'] = 'Failed to delete comment.';
}

mysqli_stmt_close($stmt);

header('Location: /NextPickStore/roles/admin/manage_products/view_comments.php?id=' . $productId);
exit;
