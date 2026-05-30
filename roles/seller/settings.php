<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'];
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

$successMessage = '';
$errorMessage   = '';
$passwordError  = '';

/* =========================
   LOAD SELLER DATA
========================= */
$sql = "SELECT user_id, full_name, email, phone_number, address, created_at, password_hash
        FROM nps_users
        WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    die("Seller account not found.");
}

$fullName    = $user['full_name'] ?? '';
$email       = $user['email'] ?? '';
$phone       = $user['phone_number'] ?? '';
$address     = $user['address'] ?? '';
$createdAt   = $user['created_at'] ?? '';

$nameParts = preg_split('/\s+/', trim($fullName), 2);
$firstNameField = $nameParts[0] ?? '';
$lastNameField  = $nameParts[1] ?? '';

$storeName        = $fullName;
$storeDescription = 'Seller account for managing products, orders, and reports.';
$storeAddress     = $address;

/* =========================
   HANDLE FORM SUBMIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstNameField = trim($_POST['first_name'] ?? '');
    $lastNameField  = trim($_POST['last_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $address        = trim($_POST['address'] ?? '');

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($firstNameField === '' || $lastNameField === '') {
        $errorMessage = 'First name and last name are required.';
    } elseif ($email === '') {
        $errorMessage = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } else {
        $newFullName = trim($firstNameField . ' ' . $lastNameField);

        $checkSql = "SELECT user_id FROM nps_users WHERE email = ? AND user_id <> ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "si", $email, $sellerId);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $emailExists = mysqli_fetch_assoc($checkResult);
        mysqli_stmt_close($checkStmt);

        if ($emailExists) {
            $errorMessage = 'This email is already used by another account.';
        } else {
            $updateSql = "UPDATE nps_users
                          SET full_name = ?, email = ?, phone_number = ?, address = ?
                          WHERE user_id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, "ssssi", $newFullName, $email, $phone, $address, $sellerId);
            $profileUpdated = mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            if ($profileUpdated) {
                $_SESSION['full_name'] = $newFullName;
                $sellerName   = $newFullName;
                $fullName     = $newFullName;
                $storeName    = $newFullName;
                $storeAddress = $address;
                $firstName    = explode(' ', $newFullName)[0];
                $successMessage = 'Profile information updated successfully.';
            } else {
                $errorMessage = 'Failed to update profile information.';
            }

            if ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '') {
                if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                    $passwordError = 'To change password, fill all password fields.';
                } elseif (!password_verify($currentPassword, $user['password_hash'])) {
                    $passwordError = 'Current password is incorrect.';
                } elseif (strlen($newPassword) < 8) {
                    $passwordError = 'New password must be at least 8 characters.';
                } elseif ($newPassword !== $confirmPassword) {
                    $passwordError = 'New password and confirm password do not match.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $passSql = "UPDATE nps_users SET password_hash = ? WHERE user_id = ?";
                    $passStmt = mysqli_prepare($conn, $passSql);
                    mysqli_stmt_bind_param($passStmt, "si", $newHash, $sellerId);
                    $passUpdated = mysqli_stmt_execute($passStmt);
                    mysqli_stmt_close($passStmt);

                    if ($passUpdated) {
                        $successMessage = 'Profile and password updated successfully.';
                        $user['password_hash'] = $newHash;
                    } else {
                        $passwordError = 'Failed to update password.';
                    }
                }
            }
        }
    }
}

$initials = '';
foreach (explode(' ', trim($sellerName)) as $part) {
    if ($part !== '') $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - NextPick</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f4f6;
            color: #222;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .page-wrapper {
    max-width: calc(100% - 50px);
    margin: 25px auto;
    background: #fff;
    border: 1px solid #e8e8e8;
    min-height: 90vh;
    display: flex;
    flex-direction: column;
}
        /* ── TOPBAR ── */
        .topbar {
            padding: 18px 28px;
            border-bottom: 1px solid #ececec;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar img {
            height: 34px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .seller-badge {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e8e8ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .topbar-user {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .topbar-user strong { font-size: 14px; }
        .topbar-user span   { font-size: 12px; color: #666; }

        /* ── LAYOUT ── */
        .main-layout {
    display: flex;
    flex: 1;
}

        .sidebar {
    width: 255px;
    border-right: 1px solid #ececec;
    padding: 28px 18px;
    background: #fff;
    flex-shrink: 0;
    align-self: stretch; /* ← ADD THIS */
}
        .sidebar h2 {
            font-size: 28px;
            line-height: 1.15;
            margin-bottom: 28px;
            font-weight: 700;
            color: #111827;
        }

        .menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 48px;
            padding: 12px 14px;
            border-radius: 12px;
            color: #111827;
            font-size: 15px;
            font-weight: 500;
            transition: 0.2s ease;
        }

        .menu a:hover  { background: #f4f6ff; color: #3158ff; }
        .menu a.active { background: #eef2ff; color: #3158ff; font-weight: 600; }

        .menu-icon-img {
            width: 18px;
            height: 18px;
            object-fit: contain;
            flex-shrink: 0;
        }

        /* ── CONTENT ── */
        .content {
    flex: 1;
    padding: 28px;
    background: #fcfcfc;
    min-width: 0;
    align-self: stretch; /* ← ADD THIS */
}
        .page-title-box {
            padding: 0 0 18px 0;
            margin-bottom: 6px;
        }

        .page-title-box h1 {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        /* ── SETTINGS CARD ── */
        .settings-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
            width: 100%;
            box-shadow: 0 2px 8px rgba(17, 24, 39, 0.04);
        }

        .profile-top {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 18px;
            border-bottom: 1px solid #ececec;
            margin-bottom: 20px;
        }

        .avatar {
            width: 74px;
            height: 74px;
            border-radius: 50%;
            background: #e8e6f2;
            color: #5b5873;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            flex-shrink: 0;
        }

        .profile-meta h2  { font-size: 20px; margin-bottom: 4px; color: #1f2937; }
        .profile-meta p   { color: #555; font-size: 14px; margin-bottom: 10px; }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-success { background: #eaf8ea; color: #1f7a1f; border: 1px solid #cceacc; }
        .alert-error   { background: #fdeaea; color: #b42318; border: 1px solid #f3c7c7; }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 12px;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .field { margin-bottom: 12px; }

        .field label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .field input,
        .field textarea {
            width: 100%;
            border: 1px solid #cfd7e3;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
            background: #fff;
        }

        .field input:focus,
        .field textarea:focus {
            border-color: #3158ff;
            box-shadow: 0 0 0 3px rgba(49, 88, 255, 0.08);
        }

        .field textarea { min-height: 72px; resize: vertical; }

        .small-note { font-size: 12px; color: #6b7280; margin-top: 4px; }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 22px;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 12px 22px;
            font-size: 15px;
            cursor: pointer;
            font-weight: 700;
        }

        .btn-primary   { background: #3158ff; color: #fff; }
        .btn-secondary { background: #fff; color: #36567f; border: 1px solid #8aa0c1; }

        /* ── FOOTER ── */
.footer {
    border-top: 1px solid #ececec;
    background: #fff;
    margin-top: 0;
}

        .footer-top {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            padding: 28px;
        }

        .footer h4 { font-size: 14px; margin-bottom: 12px; }

        .footer p,
        .footer a,
        .footer li { font-size: 13px; color: #666; line-height: 1.8; }

        .footer ul { list-style: none; }

        .footer-bottom {
            border-top: 1px solid #f0f0f0;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: #666;
        }

        .footer-links { display: flex; gap: 18px; flex-wrap: wrap; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1100px) {
            .settings-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 1200px) {
            .footer-top { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 950px) {
            .main-layout { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #ececec; }
        }

        @media (max-width: 640px) {
            .form-grid-2  { grid-template-columns: 1fr; }
            .profile-top  { flex-direction: column; align-items: flex-start; }
            .actions      { flex-direction: column; }
            .footer-top   { grid-template-columns: 1fr; }
            .topbar, .content, .sidebar,
            .footer-top, .footer-bottom { padding-left: 16px; padding-right: 16px; }
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- TOPBAR -->
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

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <h2>Welcome,<br><?php echo htmlspecialchars($firstName); ?></h2>

            <ul class="menu">
    <li>
        <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/dashboard.png" alt="" class="menu-icon-img">
            <span>Dashboard</span>
        </a>
    </li>

    <li>
        <a href="my_products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'my_products.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/inventory-management.png" alt="" class="menu-icon-img">
            <span>Inventory Management</span>
        </a>
    </li>

    <li>
        <a href="add_product.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_product.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img">
            <span>Add Product</span>
        </a>
    </li>

    <li>
        <a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img">
            <span>Orders</span>
        </a>
    </li>

    <li>
        <a href="customer_Data.php" class="<?= basename($_SERVER['PHP_SELF']) == 'customer_Data.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/client.png" alt="" class="menu-icon-img">
            <span>Customer Data</span>
        </a>
    </li>

    <li>
        <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/seo-report.png" alt="" class="menu-icon-img">
            <span>Analytics & Reports</span>
        </a>
    </li>

    <li>
        <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/settings.png" alt="" class="menu-icon-img">
            <span>Settings</span>
        </a>
    </li>

    <li>
        <a href="help_center.php" class="<?= basename($_SERVER['PHP_SELF']) == 'help_center.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img">
            <span>Help Center</span>
        </a>
    </li>

    <li>
        <a href="../../auth/logout.php">
            <img src="../../assets/images/icons/seller icon/logout.png" alt="" class="menu-icon-img">
            <span>Log out</span>
        </a>
    </li>
</ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="content">
            <div class="page-title-box">
                <h1>Account Settings</h1>
            </div>

            <?php if ($successMessage !== '') { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php } ?>
            <?php if ($errorMessage !== '') { ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php } ?>
            <?php if ($passwordError !== '') { ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($passwordError); ?></div>
            <?php } ?>

            <div class="settings-card">
                <div class="profile-top">
                    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="profile-meta">
                        <h2><?php echo htmlspecialchars($sellerName); ?></h2>
                        <p>Seller since: <?php echo !empty($createdAt) ? date('M d, Y', strtotime($createdAt)) : '-'; ?></p>
                    </div>
                </div>

                <form method="POST">
                    <div class="settings-grid">
                        <div class="left-side">
                            <div class="section-title">Personal Information</div>

                            <div class="form-grid-2">
                                <div class="field">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($firstNameField); ?>">
                                </div>
                                <div class="field">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($lastNameField); ?>">
                                </div>
                            </div>

                            <div class="field">
                                <label>Contact Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            </div>

                            <div class="field">
                                <label>Phone</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                            </div>

                            <div class="section-title" style="margin-top:14px;">Store Information</div>

                            <div class="field">
                                <label>Store Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($storeName); ?>" readonly>
                            </div>

                            <div class="field">
                                <label>Store Description</label>
                                <textarea readonly><?php echo htmlspecialchars($storeDescription); ?></textarea>
                                <div class="small-note">Display only.</div>
                            </div>

                            <div class="field">
                                <label>Business Address</label>
                                <input type="text" name="address" value="<?php echo htmlspecialchars($storeAddress); ?>">
                            </div>
                        </div>

                        <div class="right-side">
                            <div class="section-title">Security</div>

                            <div class="field">
                                <label>Change Password</label>
                                <input type="password" name="current_password" placeholder="Current Password">
                            </div>
                            <div class="field">
                                <input type="password" name="new_password" placeholder="New Password">
                            </div>
                            <div class="field">
                                <input type="password" name="confirm_password" placeholder="Confirm New Password">
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-top">
            <div>
                <h4>E-commerce support</h4>
                <p>NEXTPICK</p>
                <p>Manama, Bahrain</p>
                <p>Phone: +973 123 4567</p>
                <p>Email: support@nextpick.com</p>
            </div>
            <div>
                <h4>Working hours</h4>
                <p>Monday to Friday: 09:00 - 18:00</p>
                <p>Saturday: 10:00 - 16:00</p>
                <p>Sunday: Closed</p>
            </div>
            <div>
                <h4>About us</h4>
                <ul>
                    <li><a href="#">Stores</a></li>
                    <li><a href="#">Corporate website</a></li>
                    <li><a href="#">Exclusive Offers</a></li>
                    <li><a href="#">Career</a></li>
                </ul>
            </div>
            <div>
                <h4>Help & Support</h4>
                <ul>
                    <li><a href="#">Help center</a></li>
                    <li><a href="#">Payments</a></li>
                    <li><a href="#">Product returns</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div>© 2024 NEXTPICK. All Rights Reserved.</div>
            <div class="footer-links">
                <a href="#">Privacy policy</a>
                <a href="#">Cookie settings</a>
                <a href="#">Terms and conditions</a>
                <a href="#">Imprint</a>
            </div>
        </div>
    </footer>

</div>
</body>
</html>