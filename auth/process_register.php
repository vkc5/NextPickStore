<?php
include_once '../includes/config.php';
include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/auth/register.php');
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
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_old'] = $old;
    redirect('/NextPickStore/auth/register.php');
}

$sql = "SELECT user_id FROM nps_users WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$existingUser = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($existingUser) {
    $_SESSION['register_errors'] = [
        'email' => 'This email is already registered.'
    ];
    $_SESSION['register_old'] = $old;
    redirect('/NextPickStore/auth/register.php');
}

$verificationCode = generateVerificationCode();

$_SESSION['register_email'] = $email;
$_SESSION['verification_code'] = $verificationCode;
$_SESSION['verification_expires'] = time() + 3600;
$_SESSION['email_verified'] = false;

if (!sendVerificationEmail($email, $verificationCode)) {
    unset(
        $_SESSION['register_email'],
        $_SESSION['verification_code'],
        $_SESSION['verification_expires'],
        $_SESSION['email_verified']
    );

    $_SESSION['register_errors'] = [
        'general' => 'Failed to send verification email. Please try again.'
    ];
    $_SESSION['register_old'] = $old;
    redirect('/NextPickStore/auth/register.php');
}

redirect('/NextPickStore/auth/verify_email.php');