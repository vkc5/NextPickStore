<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$timeout_duration = 1800; // 1800 seconds = 30 minutes

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: /NextPickStore/auth/login.php?timeout=1");
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time();
?>