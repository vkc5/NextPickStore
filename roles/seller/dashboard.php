<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);

echo "Seller Dashboard";
?>

<div style="display:flex; justify-content:flex-end; padding:20px;">
    <a href="/NextPickStore/auth/logout.php" class="logout-btn">Logout</a>
</div>