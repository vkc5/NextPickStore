<?php
include_once __DIR__ . '/session.php';
include_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    redirect('/NextPickStore/auth/login.php');
}

function requireRole($allowedRoles = []) {
    $role = $_SESSION['role_name'] ?? null;

    if (!$role || !in_array($role, $allowedRoles)) {
        redirect('/NextPickStore/auth/login.php');
    }
}
?>