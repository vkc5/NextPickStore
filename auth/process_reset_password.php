<?php
include_once '../includes/config.php';
include_once '../includes/session.php';
include_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/auth/forgot_password.php');
}

if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_verified'])) {
    redirect('/NextPickStore/auth/forgot_password.php');
}

$conn = getConnection();

$email = $_SESSION['reset_email'];
$password = trim($_POST['password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

$errors = [];

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if ($confirmPassword === '') {
    $errors['confirm_password'] = 'Please confirm your password.';
} elseif ($password !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if (!empty($errors)) {
    $_SESSION['reset_password_errors'] = $errors;
    redirect('/NextPickStore/auth/reset_password.php');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$sql = "UPDATE nps_users SET password_hash = ? WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $passwordHash, $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

unset(
    $_SESSION['reset_email'],
    $_SESSION['reset_code'],
    $_SESSION['reset_expires'],
    $_SESSION['reset_verified'],
    $_SESSION['forgot_errors'],
    $_SESSION['forgot_old'],
    $_SESSION['reset_verify_errors'],
    $_SESSION['reset_password_errors']
);

$_SESSION['login_errors'] = [
    'general' => 'Password updated successfully. Please log in.'
];

redirect('/NextPickStore/auth/login.php');