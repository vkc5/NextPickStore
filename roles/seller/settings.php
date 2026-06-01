<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);

header('Location: /NextPickStore/roles/seller/dashboard.php');
exit;
