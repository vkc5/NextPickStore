<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';
include_once '../../includes/functions.php';

requireRole(['Seller']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/roles/seller/profile.php');
}

$conn = getConnection();
$sellerId = (int)($_SESSION['user_id'] ?? 0);

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$password = trim($_POST['password'] ?? '');

$errors = [];

$_SESSION['seller_profile_old'] = [
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
    mysqli_stmt_bind_param($checkStmt, "si", $email, $sellerId);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $existingEmail = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);

    if ($existingEmail) {
        $errors['email'] = 'This email is already in use.';
    }
}

if ($password !== '' && !isStrongPassword($password)) {
    $errors['password'] = passwordStrengthMessage();
}

if (!empty($errors)) {
    $_SESSION['seller_profile_errors'] = $errors;
    redirect('/NextPickStore/roles/seller/profile.php');
}

if ($password !== '') {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE nps_users
            SET full_name = ?, email = ?, phone_number = ?, address = ?, password_hash = ?
            WHERE user_id = ?
              AND role_id = (SELECT role_id FROM nps_roles WHERE role_name = 'Seller' LIMIT 1)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssi", $fullName, $email, $phoneNumber, $address, $passwordHash, $sellerId);
} else {
    $sql = "UPDATE nps_users
            SET full_name = ?, email = ?, phone_number = ?, address = ?
            WHERE user_id = ?
              AND role_id = (SELECT role_id FROM nps_roles WHERE role_name = 'Seller' LIMIT 1)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssi", $fullName, $email, $phoneNumber, $address, $sellerId);
}

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['full_name'] = $fullName;
    $_SESSION['email'] = $email;
    $_SESSION['seller_profile_success'] = 'Profile updated successfully.';
    unset($_SESSION['seller_profile_errors'], $_SESSION['seller_profile_old']);
} else {
    $_SESSION['seller_profile_errors'] = [
        'full_name' => 'Something went wrong while updating the profile.'
    ];
}

mysqli_stmt_close($stmt);

redirect('/NextPickStore/roles/seller/profile.php');
?>
