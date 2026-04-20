<?php
function sanitize($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header("Location: $path");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role_name'] ?? null;
}

function redirectByRole($roleName) {
    switch ($roleName) {
        case 'Admin':
            redirect('/NextPickStore/roles/admin/dashboard.php');
        case 'Seller':
            redirect('/NextPickStore/roles/seller/dashboard.php');
        case 'Buyer':
            redirect('/NextPickStore/roles/buyer/dashboard.php');
        default:
            redirect('/NextPickStore/index.php');
    }
}

function generateVerificationCode() {
    return str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
}
?>