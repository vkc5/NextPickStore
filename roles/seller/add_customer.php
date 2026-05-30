<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'];
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

$fullName = '';
$email    = '';
$phone    = '';
$address  = '';
$status   = 'active';

$errors         = [];
$successMessage = '';

/* =========================
   GET BUYER ROLE ID
========================= */
$buyerRoleId = null;
$roleSql     = "SELECT role_id FROM nps_roles WHERE role_name = 'Buyer' LIMIT 1";
$roleResult  = mysqli_query($conn, $roleSql);
if ($roleRow = mysqli_fetch_assoc($roleResult)) {
    $buyerRoleId = (int)$roleRow['role_id'];
}

if (!$buyerRoleId) {
    die("Buyer role not found in database.");
}

/* =========================
   HANDLE FORM SUBMIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName        = trim($_POST['full_name']        ?? '');
    $email           = trim($_POST['email']            ?? '');
    $phone           = trim($_POST['phone']            ?? '');
    $address         = trim($_POST['address']          ?? '');
    $status          = trim($_POST['status']           ?? 'active');
    $password        = $_POST['password']              ?? '';
    $confirmPassword = $_POST['confirm_password']      ?? '';

    if ($fullName === '') $errors['full_name'] = 'Full name is required.';

    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($phone   === '') $errors['phone']   = 'Phone number is required.';
    if ($address === '') $errors['address'] = 'Address is required.';

    $allowedStatuses = ['active', 'inactive', 'blocked'];
    if (!in_array($status, $allowedStatuses, true)) $status = 'active';

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm the password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $checkSql  = "SELECT user_id FROM nps_users WHERE email = ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "s", $email);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        if (mysqli_fetch_assoc($checkResult)) {
            $errors['email'] = 'This email is already registered.';
        }
        mysqli_stmt_close($checkStmt);
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insertSql = "
            INSERT INTO nps_users
            (role_id, full_name, email, password_hash, created_at, status, phone_number, address)
            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)
        ";
        $insertStmt = mysqli_prepare($conn, $insertSql);
        mysqli_stmt_bind_param($insertStmt, "issssss", $buyerRoleId, $fullName, $email, $passwordHash, $status, $phone, $address);

        if (mysqli_stmt_execute($insertStmt)) {
            $successMessage = 'Customer created successfully.';
            $fullName = $email = $phone = $address = '';
            $status = 'active';
        } else {
            $errors['general'] = 'Failed to create customer.';
        }
        mysqli_stmt_close($insertStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer - NextPick</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; }

        body { background: #f4f4f6; color: #222; }

        a { text-decoration: none; color: inherit; }

        /* ── PAGE WRAPPER ── */
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

        .topbar img { height: 34px; width: auto; object-fit: contain; display: block; }

        .topbar-right { display: flex; align-items: center; gap: 14px; }

        .seller-badge {
            width: 38px; height: 38px; border-radius: 50%;
            background: #e8e8ff; color: #3158ff;
            display: flex; align-items: center; justify-content: center; font-weight: 700;
        }

        .topbar-user { display: flex; flex-direction: column; gap: 2px; }
        .topbar-user strong { font-size: 14px; }
        .topbar-user span   { font-size: 12px; color: #666; }

        /* ── LAYOUT ── */
        .main-layout { display: flex; flex: 1; }

        .sidebar {
            width: 255px; border-right: 1px solid #ececec;
            padding: 28px 18px; background: #fff;
            flex-shrink: 0; align-self: stretch;
        }

        .sidebar h2 { font-size: 28px; line-height: 1.15; margin-bottom: 28px; font-weight: 700; color: #111827; }

        .menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }

        .menu a {
            display: flex; align-items: center; gap: 12px;
            min-height: 48px; padding: 12px 14px; border-radius: 12px;
            color: #111827; font-size: 15px; font-weight: 500; transition: 0.2s ease;
        }

        .menu a:hover  { background: #f4f6ff; color: #3158ff; }
        .menu a.active { background: #eef2ff; color: #3158ff; font-weight: 600; }

        .menu-icon-img { width: 18px; height: 18px; object-fit: contain; flex-shrink: 0; }

        /* ── CONTENT ── */
        .content {
    flex: 1;
    padding: 28px;
    background: #fcfcfc;
    min-width: 0;
    align-self: stretch;
    display: flex;
    flex-direction: column;
    align-items: center;  /* ← centers the card */
}

.page-title-box {
    padding: 0 0 18px 0;
    margin-bottom: 6px;
    width: 100%;
    max-width: 980px;
}
        .page-title-box h1 { font-size: 28px; font-weight: 700; color: #333; }

        /* ── ALERTS ── */
        .alert { border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: #eaf8ea; color: #1f7a1f; border: 1px solid #cceacc; }
        .alert-error   { background: #fdeaea; color: #b42318; border: 1px solid #f3c7c7; }

        /* ── FORM CARD ── */
        .form-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 24px;
    width: 100%;
    max-width: 980px;
}

        .form-card h2 { font-size: 22px; margin-bottom: 18px; color: #1f2937; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }

        /* ── FORM ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .field { margin-bottom: 14px; }

        .field.full { grid-column: 1 / -1; }

        .field label { display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 6px; }

        .field input,
        .field select,
        .field textarea {
            width: 100%; border: 1px solid #cfd7e3; border-radius: 10px;
            padding: 11px 12px; font-size: 14px; outline: none; background: #fff;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: #3158ff;
            box-shadow: 0 0 0 3px rgba(49, 88, 255, 0.08);
        }

        .field textarea { min-height: 90px; resize: vertical; }

        .error-text { color: #c62828; font-size: 12px; margin-top: 6px; }

        /* ── BUTTONS ── */
        .actions { display: flex; gap: 12px; margin-top: 8px; }

        .btn {
            border: none; border-radius: 10px; padding: 12px 18px;
            font-size: 14px; cursor: pointer; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
        }

        .btn-primary   { background: #3158ff; color: #fff; }
        .btn-secondary { background: #fff; color: #36567f; border: 1px solid #8aa0c1; }

        /* ── FOOTER ── */
        .footer { border-top: 1px solid #ececec; background: #fff; margin-top: auto; }

        .footer-top { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; padding: 28px; }

        .footer h4 { font-size: 14px; margin-bottom: 12px; }

        .footer p, .footer a, .footer li { font-size: 13px; color: #666; line-height: 1.8; }

        .footer ul { list-style: none; }

        .footer-bottom {
            border-top: 1px solid #f0f0f0; padding: 16px 28px;
            display: flex; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; font-size: 12px; color: #666;
        }

        .footer-links { display: flex; gap: 18px; flex-wrap: wrap; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1200px) { .footer-top { grid-template-columns: repeat(2, 1fr); } }

        @media (max-width: 950px) {
            .main-layout { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #ececec; }
        }

        @media (max-width: 700px) {
            .form-grid { grid-template-columns: 1fr; }
            .footer-top { grid-template-columns: 1fr; }
            .actions { flex-direction: column; }
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
                <li><a href="dashboard.php"><img src="../../assets/images/icons/seller icon/dashboard.png" alt="" class="menu-icon-img"><span>Dashboard</span></a></li>
                <li><a href="my_products.php"><img src="../../assets/images/icons/seller icon/inventory-management.png" alt="" class="menu-icon-img"><span>Inventory Management</span></a></li>
                <li><a href="add_product.php"><img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img"><span>Add Product</span></a></li>
                <li><a href="orders.php"><img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img"><span>Orders</span></a></li>
                <li><a href="customer_data.php" class="active"><img src="../../assets/images/icons/seller icon/client.png" alt="" class="menu-icon-img"><span>Customer Data</span></a></li>
                <li><a href="reports.php"><img src="../../assets/images/icons/seller icon/seo-report.png" alt="" class="menu-icon-img"><span>Analytics & Reports</span></a></li>
                <li><a href="settings.php"><img src="../../assets/images/icons/seller icon/settings.png" alt="" class="menu-icon-img"><span>Settings</span></a></li>
                <li><a href="help_center.php"><img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img"><span>Help Center</span></a></li>
                <li><a href="../../auth/logout.php"><img src="../../assets/images/icons/seller icon/logout.png" alt="" class="menu-icon-img"><span>Log out</span></a></li>
            </ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="content">
            <div class="page-title-box">
                <h1>Add New Customer</h1>
            </div>

            <?php if ($successMessage !== '') { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php } ?>

            <?php if (!empty($errors['general'])) { ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errors['general']); ?></div>
            <?php } ?>

            <div class="form-card">
                <h2>Create Buyer Account</h2>

                <form method="POST">
                    <div class="form-grid">
                        <div class="field full">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($fullName); ?>">
                            <?php if (!empty($errors['full_name'])) { ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['full_name']); ?></div>
                            <?php } ?>
                        </div>

                        <div class="field full">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            <?php if (!empty($errors['email'])) { ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['email']); ?></div>
                            <?php } ?>
                        </div>

                        <div class="field">
                            <label>Phone Number</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                            <?php if (!empty($errors['phone'])) { ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['phone']); ?></div>
                            <?php } ?>
                        </div>

                        <div class="field">
                            <label>Status</label>
                            <select name="status">
                                <option value="active"   <?php echo $status === 'active'   ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="blocked"  <?php echo $status === 'blocked'  ? 'selected' : ''; ?>>Blocked</option>
                            </select>
                        </div>

                        <div class="field full">
                            <label>Address</label>
                            <textarea name="address"><?php echo htmlspecialchars($address); ?></textarea>
                            <?php if (!empty($errors['address'])) { ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['address']); ?></div>
                            <?php } ?>
                        </div>

                        <div class="field">
                            <label>Password</label>
                            <input type="password" name="password">
                            <?php if (!empty($errors['password'])) { ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['password']); ?></div>
                            <?php } ?>
                        </div>

                        <div class="field">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password">
                            <?php if (!empty($errors['confirm_password'])) { ?>
                                <div class="error-text"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Create Customer</button>
                        <a href="customer_data.php" class="btn btn-secondary">Back</a>
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