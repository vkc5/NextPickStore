<?php
include_once '../includes/session.php';

$_SESSION = [];
session_unset();
session_destroy();

header("Location: /NextPickStore/auth/login.php");
exit();
?>