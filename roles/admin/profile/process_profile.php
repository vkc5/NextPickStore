<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';
include_once '../../../includes/functions.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/roles/admin/profile/profile.php');
}

$conn = getConnection();

$adminId = $_SESSION['user_id'] ?? 0;

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$password = trim($_POST['password'] ?? '');

$errors = [];

$_SESSION['profile_old'] = [
    'full_name' => $fullName,
    'email' => $email,
    'phone_number' => $phoneNumber,
    'address' => $address
];

if ($fullName === '') {
    $errors['full_name'] = 'Name is required.';
}

if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
} else {
    $checkSql = "SELECT user_id FROM nps_users WHERE email = ? AND user_id != ? LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "si", $email, $adminId);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $existingEmail = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);

    if ($existingEmail) {
        $errors['email'] = 'This email is already in use.';
    }
}

if ($password !== '' && strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if (!empty($errors)) {
    $_SESSION['profile_errors'] = $errors;
    redirect('/NextPickStore/roles/admin/profile/profile.php');
}

if ($password !== '') {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "UPDATE nps_users
            SET full_name = ?, email = ?, phone_number = ?, address = ?, password_hash = ?
            WHERE user_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssi", $fullName, $email, $phoneNumber, $address, $passwordHash, $adminId);
} else {
    $sql = "UPDATE nps_users
            SET full_name = ?, email = ?, phone_number = ?, address = ?
            WHERE user_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssi", $fullName, $email, $phoneNumber, $address, $adminId);
}

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['full_name'] = $fullName;
    $_SESSION['email'] = $email;
    $_SESSION['profile_success'] = 'Profile updated successfully.';
} else {
    $_SESSION['profile_errors'] = [
        'full_name' => 'Something went wrong while updating the profile.'
    ];
}

mysqli_stmt_close($stmt);

redirect('/NextPickStore/roles/admin/profile/profile.php');