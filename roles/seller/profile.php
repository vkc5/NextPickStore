<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';
include_once '../../includes/functions.php';

requireRole(['Seller']);

$conn = getConnection();

$sellerId = (int)($_SESSION['user_id'] ?? 0);
$sellerName = $_SESSION['full_name'] ?? 'Seller';

$errors = $_SESSION['seller_profile_errors'] ?? [];
$success = $_SESSION['seller_profile_success'] ?? '';
$old = $_SESSION['seller_profile_old'] ?? [];

unset($_SESSION['seller_profile_errors'], $_SESSION['seller_profile_success'], $_SESSION['seller_profile_old']);

$sql = "SELECT user_id, full_name, email, phone_number, address
        FROM nps_users
        WHERE user_id = ?
          AND role_id = (SELECT role_id FROM nps_roles WHERE role_name = 'Seller' LIMIT 1)
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profileData = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$profileData) {
    die('Seller profile not found.');
}

$fullName = $old['full_name'] ?? $profileData['full_name'];
$email = $old['email'] ?? $profileData['email'];
$phoneNumber = $old['phone_number'] ?? $profileData['phone_number'];
$address = $old['address'] ?? $profileData['address'];
$userId = $profileData['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - NextPick</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; }
        body { background: #f4f4f6; color: #222; }
        a { text-decoration: none; color: inherit; }
        .page-wrapper { max-width: calc(100% - 50px); margin: 25px auto; background: #fff; border: 1px solid #e8e8e8; min-height: 90vh; display: flex; flex-direction: column; }
        .topbar { background: #fff; border-bottom: none; border-radius: 10px; padding: 16px 22px; margin-bottom: 5px; display: flex; align-items: center; justify-content: space-between; }
        .topbar > img { height: 24px; width: auto; object-fit: contain; display: block; }
        .topbar-right { display: flex; align-items: center; gap: 14px; }
        .seller-badge { width: 38px; height: 38px; border-radius: 50%; background: #e8e8ff; color: #3158ff; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .topbar-user { display: flex; flex-direction: column; gap: 2px; }
        .topbar-user strong { font-size: 14px; }
        .topbar-user span { font-size: 12px; color: #666; }
        .main-layout { display: flex; flex: 1; }
        .sidebar { width: 255px; border-right: 1px solid #ececec; padding: 28px 18px; background: #fff; flex-shrink: 0; align-self: stretch; }
        .menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .menu a { display: flex; align-items: center; gap: 12px; min-height: 48px; padding: 12px 14px; border-radius: 12px; color: #111827; font-size: 15px; font-weight: 500; transition: 0.2s ease; }
        .menu a:hover { background: #f4f6ff; color: #3158ff; }
        .menu a.active { background: #eef2ff; color: #3158ff; font-weight: 600; }
        .menu-icon-img { width: 20px; height: 20px; object-fit: contain; flex-shrink: 0; }
        .content { flex: 1; padding: 28px; background: #fcfcfc; border-radius: 14px; min-width: 0; align-self: stretch; }
        .page-title-box { padding: 0 0 18px 0; margin-bottom: 6px; }
        .page-title-box h1 { font-size: 28px; font-weight: 700; color: #333; }
        .profile-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .field-group { display: flex; flex-direction: column; }
        .field-group.full-width { grid-column: 1 / -1; }
        .field-group label { font-size: 14px; font-weight: 700; margin-bottom: 8px; color: #444; }
        .input-wrapper { position: relative; }
        .input-wrapper input { width: 100%; height: 48px; border: 1px solid #3b5cff; border-radius: 10px; padding: 0 44px 0 14px; font-size: 14px; outline: none; transition: 0.25s ease; }
        .input-wrapper input:focus { border-color: #2155f5; box-shadow: 0 0 0 3px rgba(33, 85, 245, 0.12); }
        .input-icon { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #2155f5; font-size: 16px; }
        .error-text { color: #e74c3c; font-size: 12px; margin-top: 6px; }
        .success-box { background: #eafaf1; border: 1px solid #b8e7c8; color: #1a7f4b; padding: 12px 14px; border-radius: 10px; margin-bottom: 18px; font-size: 14px; }
        .id-box { margin-top: 10px; width: 300px; max-width: 100%; background: #fafafa; border: 1px solid #d9d9d9; border-radius: 14px; padding: 12px 20px; text-align: center; }
        .id-box .small-label { color: #2155f5; font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .id-box .id-value { font-size: 15px; font-weight: 700; color: #222; }
        .profile-actions { display: flex; justify-content: flex-end; margin-top: 20px; }
        .save-btn { min-width: 120px; height: 44px; border: none; border-radius: 10px; background: #2155f5; color: white; font-size: 14px; font-weight: 700; cursor: pointer; transition: 0.25s ease; }
        .save-btn:hover { background: #1747db; box-shadow: 0 8px 18px rgba(33, 85, 245, 0.18); }
        .footer { border-top: 1px solid #ececec; background: #fff; margin-top: 0; }
        @media (max-width: 950px) { .main-layout { flex-direction: column; } .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #ececec; } .profile-grid { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .page-wrapper { max-width: 100%; margin: 0; } .topbar, .content, .sidebar { padding-left: 16px; padding-right: 16px; } }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="topbar">
        <img src="/NextPickStore/assets/images/Logos/nextpickstore-logo.png" alt="NextPick Logo">
        <div class="topbar-right">
            <div class="seller-badge"><?php echo strtoupper(substr($sellerName, 0, 1)); ?></div>
            <div class="topbar-user">
                <strong><?php echo htmlspecialchars($sellerName); ?></strong>
                <span>Seller</span>
            </div>
        </div>
    </div>

    <div class="main-layout">
        <aside class="sidebar">
            <ul class="menu">
                <li><a href="dashboard.php"><img src="../../assets/images/icons/admin/home.png" alt="" class="menu-icon-img"><span>Dashboard</span></a></li>
                <li><a href="my_products.php"><img src="../../assets/images/icons/admin/box.png" alt="" class="menu-icon-img"><span>Inventory Management</span></a></li>
                <li><a href="add_product.php"><img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img"><span>Add Product</span></a></li>
                <li><a href="orders.php"><img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img"><span>Orders</span></a></li>
                <li><a href="customer_data.php"><img src="../../assets/images/icons/admin/users.png" alt="" class="menu-icon-img"><span>Customer Data</span></a></li>
                <li><a href="reports.php"><img src="../../assets/images/icons/admin/report.png" alt="" class="menu-icon-img"><span>Analytics & Reports</span></a></li>
                <li><a href="help_center.php"><img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img"><span>Help Center</span></a></li>
                <li><a href="profile.php" class="active"><img src="../../assets/images/icons/admin/profile.png" alt="" class="menu-icon-img"><span>My Profile</span></a></li>
                <li><a href="../../auth/logout.php"><img src="../../assets/images/icons/admin/logout.png" alt="" class="menu-icon-img"><span>Log out</span></a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="page-title-box">
                <h1>My Profile</h1>
            </div>

            <div class="profile-card">
                <?php if (!empty($success)): ?>
                    <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form action="process_profile.php" method="POST">
                    <div class="profile-grid">
                        <div class="field-group">
                            <label>Name</label>
                            <div class="input-wrapper">
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($fullName); ?>">
                                <span class="input-icon">Edit</span>
                            </div>
                            <?php if (!empty($errors['full_name'])): ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['full_name']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="field-group">
                            <label>E-mail address</label>
                            <div class="input-wrapper">
                                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                <span class="input-icon">Edit</span>
                            </div>
                            <?php if (!empty($errors['email'])): ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="field-group">
                            <label>Phone number</label>
                            <div class="input-wrapper">
                                <input type="text" name="phone_number" value="<?php echo htmlspecialchars($phoneNumber); ?>">
                                <span class="input-icon">Edit</span>
                            </div>
                            <?php if (!empty($errors['phone_number'])): ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['phone_number']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="field-group">
                            <label>Password</label>
                            <div class="input-wrapper">
                                <input type="password" name="password" placeholder="Leave empty to keep current password">
                                <span class="input-icon">Edit</span>
                            </div>
                            <?php if (!empty($errors['password'])): ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['password']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="field-group full-width">
                            <label>Address</label>
                            <div class="input-wrapper">
                                <input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>">
                                <span class="input-icon">Edit</span>
                            </div>
                            <?php if (!empty($errors['address'])): ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['address']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="id-box">
                        <div class="small-label">Auto Generated ID:</div>
                        <div class="id-value"><?php echo htmlspecialchars($userId); ?></div>
                    </div>

                    <div class="profile-actions">
                        <button type="submit" class="save-btn">Save</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <?php include_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
</div>
</body>
</html>
