<?php
include_once '../../includes/auth_guard.php';
requireRole(['Buyer']);

header('Location: /NextPickStore/roles/buyer/orders.php');
exit;
