<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'];
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    header('Location: orders.php');
    exit;
}

$successMessage = '';
$errorMessage   = '';
$canUpdateOrderStatus = false;

/* =========================
   LOAD ORDER
========================= */
$orderSql = "
    SELECT
        o.order_id,
        o.order_date,
        o.order_status,
        o.total_amount,
        u.full_name AS customer_name,
        u.email AS customer_email,
        u.phone_number AS customer_phone,
        u.address AS customer_address
    FROM nps_orders o
    INNER JOIN nps_users u ON o.buyer_id = u.user_id
    INNER JOIN nps_order_items oi ON o.order_id = oi.order_id
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    WHERE o.order_id = ? AND p.seller_id = ?
    GROUP BY
        o.order_id, o.order_date, o.order_status, o.total_amount,
        u.full_name, u.email, u.phone_number, u.address
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $orderSql);
mysqli_stmt_bind_param($stmt, "ii", $orderId, $sellerId);
mysqli_stmt_execute($stmt);
$orderResult = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($orderResult);
mysqli_stmt_close($stmt);

if (!$order) {
    header('Location: orders.php');
    exit;
}

/* =========================
   STATUS UPDATE LIMIT
   Seller can update order status only if every order item belongs to this seller.
========================= */
$ownershipSql = "
    SELECT COUNT(*) AS other_seller_items
    FROM nps_order_items oi
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
      AND p.seller_id <> ?
";
$ownershipStmt = mysqli_prepare($conn, $ownershipSql);
mysqli_stmt_bind_param($ownershipStmt, "ii", $orderId, $sellerId);
mysqli_stmt_execute($ownershipStmt);
$ownershipResult = mysqli_stmt_get_result($ownershipStmt);
$ownershipRow = mysqli_fetch_assoc($ownershipResult);
mysqli_stmt_close($ownershipStmt);

$canUpdateOrderStatus = ((int)($ownershipRow['other_seller_items'] ?? 0) === 0);

/* =========================
   HANDLE UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = trim($_POST['order_status'] ?? '');
    $allowedStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        $errorMessage = 'Invalid order status.';
    } elseif (!$canUpdateOrderStatus) {
        $errorMessage = 'This order contains products from another seller, so you cannot update the full order status.';
    } else {
        $updateSql = "
            UPDATE nps_orders
            SET order_status = ?
            WHERE order_id = ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM nps_order_items oi
                  INNER JOIN nps_products p ON oi.product_id = p.product_id
                  WHERE oi.order_id = nps_orders.order_id
                    AND p.seller_id <> ?
              )
        ";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, "sii", $newStatus, $orderId, $sellerId);

        if (mysqli_stmt_execute($updateStmt) && mysqli_stmt_affected_rows($updateStmt) > 0) {
            $successMessage = 'Order status updated successfully.';
            $order['order_status'] = $newStatus;
        } else {
            $errorMessage = 'Failed to update order status.';
        }
        mysqli_stmt_close($updateStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order - NextPick</title>
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

        .topbar img {
            height: 34px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        .topbar-right { display: flex; align-items: center; gap: 14px; }

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

        .topbar-user { display: flex; flex-direction: column; gap: 2px; }
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
            align-self: stretch;
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
            align-self: stretch;
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

        /* ── ALERTS ── */
        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-success { background: #eaf8ea; color: #1f7a1f; border: 1px solid #cceacc; }
        .alert-error   { background: #fdeaea; color: #b42318; border: 1px solid #f3c7c7; }

        /* ── CARD ── */
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card h2 {
            margin-bottom: 18px;
            font-size: 22px;
            color: #1f2937;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        /* ── INFO GRID ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .info-box {
            background: #fafafa;
            border: 1px solid #ececec;
            border-radius: 12px;
            padding: 14px;
        }

        .info-box .label { font-size: 13px; color: #666; margin-bottom: 6px; }
        .info-box .value { font-size: 15px; color: #222; font-weight: 600; }

        /* ── FORM ── */
        .field { margin-bottom: 16px; }

        .field label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #333;
        }

        .field select {
            width: 100%;
            max-width: 320px;
            border: 1px solid #cfd7e3;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 14px;
            outline: none;
            background: #fff;
            color: #333;
        }

        .field select:focus {
            border-color: #3158ff;
            box-shadow: 0 0 0 3px rgba(49, 88, 255, 0.08);
        }

        /* ── BUTTONS ── */
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary   { background: #3158ff; color: #fff; }
        .btn-secondary { background: #fff; color: #36567f; border: 1px solid #8aa0c1; }

        /* ── FOOTER ── */
        .footer {
            border-top: 1px solid #ececec;
            background: #fff;
            margin-top: auto;
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
        @media (max-width: 1200px) {
            .footer-top { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 950px) {
            .main-layout { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #ececec; }
        }

        @media (max-width: 700px) {
            .info-grid { grid-template-columns: 1fr; }
            .actions { flex-direction: column; }
            .footer-top { grid-template-columns: 1fr; }
            .topbar, .content, .sidebar,
            .footer-top, .footer-bottom { padding-left: 16px; padding-right: 16px; }
        }

        /* SELLER ADMIN-LIKE TOPBAR OVERRIDES */
        .topbar {
            background: #fff;
            border-bottom: none;
            border-radius: 10px;
            padding: 16px 22px;
            margin-bottom: 5px;
        }

        .topbar > img {
            height: 24px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        .menu-icon-img {
            width: 20px;
            height: 20px;
            object-fit: contain;
            flex-shrink: 0;
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

            <ul class="menu">
    <li><a href="dashboard.php"><img src="../../assets/images/icons/admin/home.png" alt="" class="menu-icon-img"><span>Dashboard</span></a></li>

    <li><a href="my_products.php"><img src="../../assets/images/icons/admin/box.png" alt="" class="menu-icon-img"><span>Inventory Management</span></a></li>

    <li><a href="add_product.php"><img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img"><span>Add Product</span></a></li>

    <li><a href="orders.php"><img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img"><span>Orders</span></a></li>

    <li><a href="customer_data.php"><img src="../../assets/images/icons/admin/users.png" alt="" class="menu-icon-img"><span>Customer Data</span></a></li>

    <li><a href="reports.php"><img src="../../assets/images/icons/admin/report.png" alt="" class="menu-icon-img"><span>Analytics & Reports</span></a></li>

    <li><a href="help_center.php"><img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img"><span>Help Center</span></a></li>

    <li><a href="../../auth/logout.php"><img src="../../assets/images/icons/admin/logout.png" alt="" class="menu-icon-img"><span>Log out</span></a></li>
</ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="content">
            <div class="page-title-box">
                <h1>Edit Order</h1>
            </div>

            <?php if ($successMessage !== '') { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php } ?>
            <?php if ($errorMessage !== '') { ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php } ?>

            <div class="card">
                <h2>Order #<?php echo (int)$order['order_id']; ?></h2>

                <div class="info-grid">
                    <div class="info-box">
                        <div class="label">Customer Name</div>
                        <div class="value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="label">Email</div>
                        <div class="value"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="label">Phone</div>
                        <div class="value"><?php echo htmlspecialchars($order['customer_phone'] ?: '-'); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="label">Order Date</div>
                        <div class="value"><?php echo date('Y/m/d', strtotime($order['order_date'])); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="label">Total Amount</div>
                        <div class="value">$<?php echo number_format((float)$order['total_amount'], 2); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="label">Customer Address</div>
                        <div class="value"><?php echo htmlspecialchars($order['customer_address'] ?: '-'); ?></div>
                    </div>
                </div>

                <form method="POST">
                    <div class="field">
                        <label for="order_status">Shipping Status</label>
                        <select name="order_status" id="order_status">
                            <option value="pending"   <?php echo $order['order_status'] === 'pending'   ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $order['order_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="shipped"   <?php echo $order['order_status'] === 'shipped'   ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="orders.php" class="btn btn-secondary">Back</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- FOOTER -->
    <?php include_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

</div>
</body>
</html>
