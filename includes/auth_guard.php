<?php
include_once __DIR__ . '/session.php';
include_once __DIR__ . '/functions.php';
include_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect('/NextPickStore/auth/login.php');
}

function requireRole($allowedRoles = []) {
    $role = $_SESSION['role_name'] ?? null;

    if (!$role || !in_array($role, $allowedRoles)) {
        redirect('/NextPickStore/auth/login.php');
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        redirect('/NextPickStore/auth/login.php');
    }

    $conn = getConnection();
    $sql = "
        SELECT u.status, u.full_name, u.email, r.role_name
        FROM nps_users u
        INNER JOIN nps_roles r ON u.role_id = r.role_id
        WHERE u.user_id = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user || $user['status'] !== 'active' || !in_array($user['role_name'], $allowedRoles)) {
        session_unset();
        session_destroy();
        redirect('/NextPickStore/auth/login.php');
    }

    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
}
?>
