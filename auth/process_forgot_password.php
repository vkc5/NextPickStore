<?php
include_once '../includes/config.php';
include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/auth/forgot_password.php');
}

$conn = getConnection();

$email = sanitize($_POST['email'] ?? '');

$errors = [];
$old = ['email' => $email];

if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if (!empty($errors)) {
    $_SESSION['forgot_errors'] = $errors;
    $_SESSION['forgot_old'] = $old;
    redirect('/NextPickStore/auth/forgot_password.php');
}

$sql = "SELECT user_id FROM nps_users WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['forgot_errors'] = [
        'email' => 'This email does not exist.'
    ];
    $_SESSION['forgot_old'] = $old;
    redirect('/NextPickStore/auth/forgot_password.php');
}

$resetCode = generateVerificationCode();

$_SESSION['reset_email'] = $email;
$_SESSION['reset_code'] = $resetCode;
$_SESSION['reset_expires'] = time() + 3600;
$_SESSION['reset_verified'] = false;

if (!sendVerificationEmail($email, $resetCode)) {
    unset(
        $_SESSION['reset_email'],
        $_SESSION['reset_code'],
        $_SESSION['reset_expires'],
        $_SESSION['reset_verified']
    );

    $_SESSION['forgot_errors'] = [
        'general' => 'Failed to send verification email. Please try again.'
    ];
    $_SESSION['forgot_old'] = $old;
    redirect('/NextPickStore/auth/forgot_password.php');
}

redirect('/NextPickStore/auth/verify_reset_code.php');