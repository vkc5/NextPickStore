<?php
include_once '../includes/config.php';
include_once '../includes/session.php';
include_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/NextPickStore/auth/login.php');
}

$conn = getConnection();

$email = sanitize($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

$errors = [];
$old = [
    'email' => $email
];

/*
|--------------------------------------------------------------------------
| Server-side validation
|--------------------------------------------------------------------------
*/
if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
}

/*
|--------------------------------------------------------------------------
| Stop if validation failed
|--------------------------------------------------------------------------
*/
if (!empty($errors)) {
    $_SESSION['login_errors'] = $errors;
    $_SESSION['login_old'] = $old;
    redirect('/NextPickStore/auth/login.php');
}

/*
|--------------------------------------------------------------------------
| Fetch user with role using prepared statement
|--------------------------------------------------------------------------
*/
$sql = "SELECT 
            u.user_id,
            u.full_name,
            u.email,
            u.password_hash,
            u.status,
            r.role_name
        FROM nps_users u
        INNER JOIN nps_roles r ON u.role_id = r.role_id
        WHERE u.email = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    $_SESSION['login_errors'] = [
        'general' => 'Something went wrong. Please try again later.'
    ];
    $_SESSION['login_old'] = $old;
    redirect('/NextPickStore/auth/login.php');
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Check email exists
|--------------------------------------------------------------------------
*/
if (!$user) {
    $_SESSION['login_errors'] = [
        'general' => 'Incorrect email or password.'
    ];
    $_SESSION['login_old'] = $old;
    redirect('/NextPickStore/auth/login.php');
}

/*
|--------------------------------------------------------------------------
| Check account status
|--------------------------------------------------------------------------
*/
if ($user['status'] !== 'active') {
    $_SESSION['login_errors'] = [
        'general' => 'Your account is not active. Please contact support.'
    ];
    $_SESSION['login_old'] = $old;
    redirect('/NextPickStore/auth/login.php');
}

/*
|--------------------------------------------------------------------------
| Verify hashed password
|--------------------------------------------------------------------------
*/
if (!password_verify($password, $user['password_hash'])) {
    $_SESSION['login_errors'] = [
        'general' => 'Incorrect email or password.'
    ];
    $_SESSION['login_old'] = $old;
    redirect('/NextPickStore/auth/login.php');
}

/*
|--------------------------------------------------------------------------
| Create session
|--------------------------------------------------------------------------
*/
session_regenerate_id(true);

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role_name'] = $user['role_name'];

/*
|--------------------------------------------------------------------------
| Redirect by role
|--------------------------------------------------------------------------
*/
redirectByRole($user['role_name']);