<?php
include_once '../includes/session.php';
include_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/auth/forgot_password.php');
}

if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_code'])) {
    redirect('/NextPickStore/auth/forgot_password.php');
}

if (time() > ($_SESSION['reset_expires'] ?? 0)) {
    $_SESSION['reset_verify_errors'] = [
        'general' => 'The verification code has expired. Please request a new one.'
    ];
    redirect('/NextPickStore/auth/verify_reset_code.php');
}

$enteredCode =
    trim($_POST['digit1'] ?? '') .
    trim($_POST['digit2'] ?? '') .
    trim($_POST['digit3'] ?? '') .
    trim($_POST['digit4'] ?? '') .
    trim($_POST['digit5'] ?? '') .
    trim($_POST['digit6'] ?? '');

if (!preg_match('/^\d{6}$/', $enteredCode)) {
    $_SESSION['reset_verify_errors'] = [
        'general' => 'Please enter the complete 6-digit code.'
    ];
    redirect('/NextPickStore/auth/verify_reset_code.php');
}

if ($enteredCode !== $_SESSION['reset_code']) {
    $_SESSION['reset_verify_errors'] = [
        'general' => 'The entered code is wrong. Please try again.'
    ];
    redirect('/NextPickStore/auth/verify_reset_code.php');
}

$_SESSION['reset_verified'] = true;

redirect('/NextPickStore/auth/reset_password.php');