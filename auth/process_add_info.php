<?php
include_once '../includes/config.php';
include_once '../includes/session.php';
include_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/auth/register.php');
}

if (empty($_SESSION['register_email']) || empty($_SESSION['email_verified'])) {
    redirect('/NextPickStore/auth/register.php');
}

$conn = getConnection();

$email = $_SESSION['register_email'];
$fullName = sanitize($_POST['full_name'] ?? '');
$roleName = sanitize($_POST['role_name'] ?? '');
$phoneNumber = sanitize($_POST['phone_number'] ?? '');
$address = sanitize($_POST['address'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

$errors = [];
$old = [
    'full_name' => $fullName,
    'role_name' => $roleName,
    'phone_number' => $phoneNumber,
    'address' => $address
];

if ($fullName === '') {
    $errors['full_name'] = 'Full name is required.';
}

if (!in_array($roleName, ['Buyer', 'Seller'])) {
    $errors['role_name'] = 'Please select a valid account type.';
}

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
    $_SESSION['add_info_errors'] = $errors;
    $_SESSION['add_info_old'] = $old;
    redirect('/NextPickStore/auth/add_info.php');
}

$sqlRole = "SELECT role_id FROM nps_roles WHERE role_name = ? LIMIT 1";
$stmtRole = mysqli_prepare($conn, $sqlRole);
mysqli_stmt_bind_param($stmtRole, "s", $roleName);
mysqli_stmt_execute($stmtRole);
$resultRole = mysqli_stmt_get_result($stmtRole);
$role = mysqli_fetch_assoc($resultRole);
mysqli_stmt_close($stmtRole);

if (!$role) {
    $_SESSION['add_info_errors'] = [
        'role_name' => 'Selected role does not exist.'
    ];
    $_SESSION['add_info_old'] = $old;
    redirect('/NextPickStore/auth/add_info.php');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$sqlInsert = "INSERT INTO nps_users
    (role_id, full_name, email, password_hash, status, phone_number, address)
    VALUES (?, ?, ?, ?, 'active', ?, ?)";

$stmtInsert = mysqli_prepare($conn, $sqlInsert);
mysqli_stmt_bind_param(
    $stmtInsert,
    "isssss",
    $role['role_id'],
    $fullName,
    $email,
    $passwordHash,
    $phoneNumber,
    $address
);

if (!mysqli_stmt_execute($stmtInsert)) {
    $_SESSION['add_info_errors'] = [
        'full_name' => 'Something went wrong while creating the account.'
    ];
    $_SESSION['add_info_old'] = $old;
    redirect('/NextPickStore/auth/add_info.php');
}

$newUserId = mysqli_insert_id($conn);
mysqli_stmt_close($stmtInsert);

$_SESSION['user_id'] = $newUserId;
$_SESSION['full_name'] = $fullName;
$_SESSION['email'] = $email;
$_SESSION['role_name'] = $roleName;

unset(
    $_SESSION['register_email'],
    $_SESSION['verification_code'],
    $_SESSION['verification_expires'],
    $_SESSION['email_verified'],
    $_SESSION['register_errors'],
    $_SESSION['register_old'],
    $_SESSION['verify_errors'],
    $_SESSION['add_info_errors'],
    $_SESSION['add_info_old']
);

redirectByRole($roleName);