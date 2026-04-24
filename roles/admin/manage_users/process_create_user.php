<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';
include_once '../../../includes/functions.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/roles/admin/manage_users/create_user.php');
}

$conn = getConnection();

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$password = trim($_POST['password'] ?? '');
$roleName = trim($_POST['role_name'] ?? '');

$errors = [];

$_SESSION['create_user_old'] = [
    'full_name' => $fullName,
    'email' => $email,
    'phone_number' => $phoneNumber,
    'address' => $address,
    'role_name' => $roleName
];

if ($fullName === '') {
    $errors['full_name'] = 'Name is required.';
}

if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
} else {
    $checkSql = "SELECT user_id FROM nps_users WHERE email = ? LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "s", $email);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $existingEmail = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);

    if ($existingEmail) {
        $errors['email'] = 'This email is already in use.';
    }
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if (!in_array($roleName, ['Admin', 'Seller', 'Buyer'])) {
    $errors['role_name'] = 'Please select a valid role.';
}

if (!empty($errors)) {
    $_SESSION['create_user_errors'] = $errors;
    redirect('/NextPickStore/roles/admin/manage_users/create_user.php');
}

$roleSql = "SELECT role_id FROM nps_roles WHERE role_name = ? LIMIT 1";
$roleStmt = mysqli_prepare($conn, $roleSql);
mysqli_stmt_bind_param($roleStmt, "s", $roleName);
mysqli_stmt_execute($roleStmt);
$roleResult = mysqli_stmt_get_result($roleStmt);
$roleData = mysqli_fetch_assoc($roleResult);
mysqli_stmt_close($roleStmt);

if (!$roleData) {
    $_SESSION['create_user_errors'] = [
        'role_name' => 'Selected role was not found.'
    ];
    redirect('/NextPickStore/roles/admin/manage_users/create_user.php');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$insertSql = "INSERT INTO nps_users (role_id, full_name, email, password_hash, status, phone_number, address)
              VALUES (?, ?, ?, ?, 'active', ?, ?)";

$insertStmt = mysqli_prepare($conn, $insertSql);
mysqli_stmt_bind_param(
    $insertStmt,
    "isssss",
    $roleData['role_id'],
    $fullName,
    $email,
    $passwordHash,
    $phoneNumber,
    $address
);

if (mysqli_stmt_execute($insertStmt)) {
    $_SESSION['create_user_success'] = 'User created successfully.';
    unset($_SESSION['create_user_old']);
} else {
    $_SESSION['create_user_errors'] = [
        'full_name' => 'Something went wrong while creating the user.'
    ];
}

mysqli_stmt_close($insertStmt);

redirect('/NextPickStore/roles/admin/manage_users/create_user.php');