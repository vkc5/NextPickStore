<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';
include_once '../../includes/functions.php';

requireRole(['Buyer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/roles/buyer/profile.php');
}

$conn = getConnection();
$userId = (int)($_SESSION['user_id'] ?? 0);

$fullName = sanitize($_POST['full_name'] ?? '');
$phoneNumber = sanitize($_POST['phone_number'] ?? '');
$address = sanitize($_POST['address'] ?? '');

$errors = [];

if ($fullName === '') {
    $errors['full_name'] = 'Full name is required.';
} elseif (!preg_match('/^[A-Za-z\s]{3,100}$/', $fullName)) {
    $errors['full_name'] = 'Full name must contain letters and spaces only.';
}

if ($phoneNumber !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phoneNumber)) {
    $errors['phone_number'] = 'Phone number format is invalid.';
}

if ($address === '') {
    $errors['address'] = 'Address is required.';
}

if (!empty($errors)) {
    $_SESSION['profile_errors'] = $errors;
    $_SESSION['profile_old'] = [
        'full_name' => $fullName,
        'phone_number' => $phoneNumber,
        'address' => $address
    ];
    redirect('/NextPickStore/roles/buyer/profile.php');
}

$sql = "
    UPDATE nps_users
    SET full_name = ?,
        phone_number = ?,
        address = ?
    WHERE user_id = ?
      AND role_id = (SELECT role_id FROM nps_roles WHERE role_name = 'Buyer' LIMIT 1)
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssi", $fullName, $phoneNumber, $address, $userId);

if (!$stmt || !mysqli_stmt_execute($stmt)) {
    $_SESSION['profile_errors'] = [
        'general' => 'Could not update profile. Please try again.'
    ];
    redirect('/NextPickStore/roles/buyer/profile.php');
}

mysqli_stmt_close($stmt);

$_SESSION['full_name'] = $fullName;
$_SESSION['profile_success'] = 'Profile updated successfully.';

unset($_SESSION['profile_errors'], $_SESSION['profile_old']);

redirect('/NextPickStore/roles/buyer/profile.php');
?>
