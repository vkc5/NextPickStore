<?php
include_once '../includes/session.php';
include_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/auth/register.php');
}

if (empty($_SESSION['register_email']) || empty($_SESSION['verification_code'])) {
    redirect('/NextPickStore/auth/register.php');
}

if (time() > ($_SESSION['verification_expires'] ?? 0)) {
    $_SESSION['verify_errors'] = [
        'general' => 'The verification code has expired. Please register again.'
    ];
    redirect('/NextPickStore/auth/verify_email.php');
}

$enteredCode =
    trim($_POST['digit1'] ?? '') .
    trim($_POST['digit2'] ?? '') .
    trim($_POST['digit3'] ?? '') .
    trim($_POST['digit4'] ?? '') .
    trim($_POST['digit5'] ?? '') .
    trim($_POST['digit6'] ?? '');

if (!preg_match('/^\d{6}$/', $enteredCode)) {
    $_SESSION['verify_errors'] = [
        'general' => 'Please enter the complete 6-digit code.'
    ];
    redirect('/NextPickStore/auth/verify_email.php');
}

if ($enteredCode !== $_SESSION['verification_code']) {
    $_SESSION['verify_errors'] = [
        'general' => 'The entered code is wrong. Please try again.'
    ];
    redirect('/NextPickStore/auth/verify_email.php');
}

$_SESSION['email_verified'] = true;

redirect('/NextPickStore/auth/add_info.php');