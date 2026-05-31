<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/roles/admin/manage_users/delete_users.php');
}

$conn = getConnection();

$userId = (int)($_POST['user_id'] ?? 0);
$nextStatus = trim($_POST['next_status'] ?? '');
$currentAdminId = $_SESSION['user_id'] ?? 0;

$allowedStatuses = ['active', 'inactive'];

if ($userId <= 0) {
    die('Invalid user ID.');
}

if (!in_array($nextStatus, $allowedStatuses, true)) {
    $_SESSION['deactivate_user_success'] = 'Invalid status change request.';
    redirect('/NextPickStore/roles/admin/manage_users/deactivate_user.php?id=' . $userId);
}

/* prevent admin from deactivating themselves */
if ($userId === (int)$currentAdminId && $nextStatus === 'inactive') {
    $_SESSION['deactivate_user_success'] = 'You cannot deactivate your own account.';
    redirect('/NextPickStore/roles/admin/manage_users/deactivate_user.php?id=' . $userId);
}

$sql = "UPDATE nps_users SET status = ? WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $nextStatus, $userId);

if (mysqli_stmt_execute($stmt)) {
    if ($nextStatus === 'active') {
        $_SESSION['deactivate_user_success'] = 'User activated successfully.';
    } else {
        $_SESSION['deactivate_user_success'] = 'User deactivated successfully.';
    }
} else {
    $_SESSION['deactivate_user_success'] = 'Failed to update user status.';
}

mysqli_stmt_close($stmt);

redirect('/NextPickStore/roles/admin/manage_users/deactivate_user.php?id=' . $userId);