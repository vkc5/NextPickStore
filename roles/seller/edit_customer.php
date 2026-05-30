<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'];
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

$customerId = (int)($_GET['id'] ?? 0);

if ($customerId <= 0) {
    header('Location: customer_data.php');
    exit;
}

/* =========================
   LOAD CUSTOMER
========================= */
$customerSql = "
    SELECT u.user_id, u.full_name, u.email, u.phone_number, u.address, u.status
    FROM nps_users u
    INNER JOIN nps_orders o ON u.user_id = o.buyer_id
    INNER JOIN nps_order_items oi ON o.order_id = oi.order_id
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    INNER JOIN nps_roles r ON u.role_id = r.role_id
    WHERE u.user_id = ? AND p.seller_id = ? AND r.role_name = 'Buyer'
    GROUP BY u.user_id
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $customerSql);
mysqli_stmt_bind_param($stmt, "ii", $customerId, $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$customer) {
    header('Location: customer_data.php');
    exit;
}

$nameParts = preg_split('/\s+/', trim($customer['full_name']), 2);
$custFirstName = $nameParts[0] ?? '';
$custLastName  = $nameParts[1] ?? '';

/* =========================
   HANDLE FORM SUBMIT
========================= */
$successMessage = '';
$errorMessage   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newFirstName = trim($_POST['first_name'] ?? '');
    $newLastName  = trim($_POST['last_name']  ?? '');
    $newEmail     = trim($_POST['email']      ?? '');
    $newPhone     = trim($_POST['phone']      ?? '');
    $newAddress   = trim($_POST['address']    ?? '');

    if ($newFirstName === '' || $newLastName === '') {
        $errorMessage = 'First name and last name are required.';
    } elseif ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'A valid email address is required.';
    } else {
        $newFullName = trim($newFirstName . ' ' . $newLastName);

        /* check duplicate email */
        $checkSql = "SELECT user_id FROM nps_users WHERE email = ? AND user_id <> ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "si", $newEmail, $customerId);
        mysqli_stmt_execute($checkStmt);
        $emailExists = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));
        mysqli_stmt_close($checkStmt);

        if ($emailExists) {
            $errorMessage = 'This email is already used by another account.';
        } else {
            $updateSql = "UPDATE nps_users SET full_name = ?, email = ?, phone_number = ?, address = ? WHERE user_id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, "ssssi", $newFullName, $newEmail, $newPhone, $newAddress, $customerId);

            if (mysqli_stmt_execute($updateStmt)) {
                $successMessage = 'Customer updated successfully.';
                $customer['full_name']    = $newFullName;
                $customer['email']        = $newEmail;
                $customer['phone_number'] = $newPhone;
                $customer['address']      = $newAddress;
                $custFirstName = $newFirstName;
                $custLastName  = $newLastName;
            } else {
                $errorMessage = 'Failed to update customer.';
            }
            mysqli_stmt_close($updateStmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer - NextPick</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; }

        body { background: #f4f4f6; color: #222; }

        a { text-decoration: none; color: inherit; }

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
        .content { flex: 1; padding: 28px; background: #fcfcfc; min-width: 0; align-self: stretch; }

        .page-title-box { padding: 0 0 18px 0; margin-bottom: 6px; }

        .page-title-box h1 { font-size: 28px; font-weight: 700; color: #333; }

        /* ── ALERTS ── */
        .alert { border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: #eaf8ea; color: #1f7a1f; border: 1px solid #cceacc; }
        .alert-error   { background: #fdeaea; color: #b42318; border: 1px solid #f3c7c7; }

        /* ── CARD ── */
        .card {
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 18px; padding: 24px; margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px; font-weight: 700; color: #1f2937;
            margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0;
        }

        /* ── FORM ── */
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .field { margin-bottom: 16px; }

        .field label { display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 6px; }

        .field input {
            width: 100%; height: 46px; border: 1px solid #cfd7e3;
            border-radius: 10px; padding: 0 14px; font-size: 14px;
            outline: none; background: #fff; color: #333;
        }

        .field input:focus {
            border-color: #3158ff;
            box-shadow: 0 0 0 3px rgba(49, 88, 255, 0.08);
        }

        .form-actions { display: flex; gap: 12px; margin-top: 20px; }

        .btn {
            border: none; border-radius: 10px; padding: 12px 22px;
            font-size: 14px; cursor: pointer; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
        }

        .btn-primary   { background: #3158ff; color: #fff; }
        .btn-secondary { background: #f1f1f5; color: #222; }

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

        @media (max-width: 640px) {
            .form-grid-2 { grid-template-columns: 1fr; }
            .footer-top { grid-template-columns: 1fr; }
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
                <h1>Edit Customer</h1>
            </div>

            <?php if ($successMessage !== '') { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php } ?>
            <?php if ($errorMessage !== '') { ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php } ?>

            <div class="card">
                <div class="card-title">Customer Information</div>

                <form method="POST">
                    <div class="form-grid-2">
                        <div class="field">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($custFirstName); ?>" required>
                        </div>
                        <div class="field">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($custLastName); ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                    </div>

                    <div class="field">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($customer['phone_number'] ?? ''); ?>">
                    </div>

                    <div class="field">
                        <label>Address</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="customer_data.php" class="btn btn-secondary">Cancel</a>
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