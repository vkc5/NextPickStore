<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';

requireRole(['Admin']);

$conn = getConnection();

$adminName = $_SESSION['full_name'] ?? 'Admin';

/* -----------------------------
  Summary Cards
  ----------------------------- */
$totalUsers = 0;
$totalProducts = 0;
$totalComments = 0;
$totalOrders = 0;

$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

$whereClause = '';
if (in_array($statusFilter, $allowedStatuses)) {
    $safeStatus = mysqli_real_escape_string($conn, $statusFilter);
    $whereClause = " WHERE o.order_status = '$safeStatus' ";
}

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM nps_users");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalUsers = $row['total'];
}

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM nps_products");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalProducts = $row['total'];
}

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM nps_comments");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalComments = $row['total'];
}

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM nps_orders");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalOrders = $row['total'];
}

/* -----------------------------
  Recent Orders Table
  ----------------------------- */
$recentOrders = [];

$sql = "
    SELECT 
        oi.order_item_id,
        p.product_name,
        o.shipping_address,
        o.order_date,
        oi.quantity,
        oi.subtotal,
        o.order_status,
        img.image_path
    FROM nps_order_items oi
    INNER JOIN nps_orders o ON oi.order_id = o.order_id
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    LEFT JOIN nps_product_images img 
        ON p.product_id = img.product_id AND img.is_primary = 1
    $whereClause
    ORDER BY o.order_date DESC, oi.order_item_id DESC
";

$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recentOrders[] = $row;
    }
}

function formatStatusClass($status) {
    switch (strtolower($status)) {
        case 'delivered':
            return 'status-delivered';
        case 'pending':
            return 'status-pending';
        case 'cancelled':
            return 'status-cancelled';
        case 'confirmed':
            return 'status-confirmed';
        case 'shipped':
            return 'status-shipped';
        default:
            return 'status-default';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard - NextPick</title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
            }

            body {
                background: #e9e9e9;
                color: #222;
            }

            a {
                text-decoration: none;
                color: inherit;
            }

            .page-wrapper {
                margin: 25px auto;
                background: #f6f6f6;
                border-radius: 14px;
                padding: 25px 30px 0;
                min-height: calc(100vh - 50px);
                margin-left: 25px;
                margin-right: 25px;
            }

            .topbar {
                background: #fff;
                border-radius: 10px;
                padding: 16px 22px;
                margin-bottom: 28px;
            }

            .topbar img {
                height: 24px;
                width: auto;
            }

            .main-layout {
                display: grid;
                grid-template-columns: 220px 1fr;
                gap: 28px;
            }

            .sidebar {
                padding-top: 8px;
            }

            .sidebar h2 {
                font-size: 20px;
                margin-bottom: 26px;
                font-weight: 700;
            }

            .nav-menu {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .nav-link {
                display: flex !important;
                align-items: center;
                gap: 12px;
                padding: 12px 14px;
                border-radius: 12px;
                font-size: 16px;
                color: #222;
                transition: all 0.25s ease;
            }

            .nav-link img {
                width: 20px;
                height: 20px;
                object-fit: contain;
                transition: transform 0.25s ease;
            }

            .nav-link:hover {
                background: #eef3ff;
                color: #2155f5;
                box-shadow: 0 6px 16px rgba(33, 85, 245, 0.10);
                transform: translateX(3px);
            }

            .nav-link:hover img {
                transform: scale(1.08);
            }

            .nav-link.active {
                background: #eef3ff;
                color: #2155f5;
                font-weight: 600;
                box-shadow: 0 6px 16px rgba(33, 85, 245, 0.10);
            }

            .nav-link.logout-link {
                margin-top: 10px;
            }

            .filter-form {
                display: flex;
                align-items: center;
            }

            .status-filter {
                min-width: 170px;
                height: 42px;
                padding: 0 14px;
                border: 1px solid #d8dce8;
                border-radius: 10px;
                background: #fff;
                color: #333;
                font-size: 14px;
                outline: none;
                cursor: pointer;
                transition: all 0.25s ease;
            }

            .status-filter:hover {
                border-color: #2155f5;
                box-shadow: 0 6px 14px rgba(33, 85, 245, 0.10);
            }

            .status-filter:focus {
                border-color: #2155f5;
                box-shadow: 0 0 0 3px rgba(33, 85, 245, 0.12);
            }

            .content {
                padding-bottom: 30px;
            }

            .section-title {
                background: #efefef;
                border-radius: 10px;
                padding: 16px 22px;
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 24px;
            }

            .stats-row {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 18px;
                margin-bottom: 28px;
            }

            .stat-card {
                background: #fff;
                border-radius: 16px;
                padding: 20px;
                min-height: 140px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            }

            .stat-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
            }

            .stat-label {
                font-size: 15px;
                color: #666;
                margin-bottom: 10px;
            }

            .stat-value {
                font-size: 38px;
                font-weight: 700;
                line-height: 1;
            }

            .stat-icon {
                width: 58px;
                height: 58px;
                border-radius: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 26px;
                font-weight: bold;
            }

            .icon-users {
                background: #ece9ff;
                color: #7a6cff;
            }
            .icon-products {
                background: #fff2d9;
                color: #dba100;
            }
            .icon-comments {
                background: #ddf8e9;
                color: #1bb36b;
            }
            .icon-orders {
                background: #e8f0ff;
                color: #2155f5;
            }

            .stat-note {
                font-size: 14px;
                color: #666;
                margin-top: 16px;
            }

            .orders-card {
                background: #fff;
                border-radius: 18px;
                padding: 24px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            }

            .orders-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 18px;
            }

            .orders-header h3 {
                font-size: 20px;
                font-weight: 700;
            }

            .table-wrapper {
                height: 420px;   /* force fixed height */
                overflow-y: auto;
                overflow-x: auto;
                border-radius: 12px;
            }

            .table-wrapper::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }

            .table-wrapper::-webkit-scrollbar-thumb {
                background: #cfcfcf;
                border-radius: 10px;
            }

            .table-wrapper::-webkit-scrollbar-track {
                background: #f3f3f3;
                border-radius: 10px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            thead th {
                background: #f1f2f6;
                padding: 14px 12px;
                font-size: 14px;
                text-align: left;
                color: #333;
            }

            thead th:first-child {
                border-top-left-radius: 10px;
                border-bottom-left-radius: 10px;
            }

            thead th:last-child {
                border-top-right-radius: 10px;
                border-bottom-right-radius: 10px;
            }

            tbody td {
                padding: 18px 12px;
                border-bottom: 1px solid #ececec;
                vertical-align: middle;
                font-size: 14px;
                color: #444;
            }

            .product-cell {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .product-cell img {
                width: 42px;
                height: 42px;
                border-radius: 8px;
                object-fit: cover;
                background: #f2f2f2;
            }

            .status-badge {
                display: inline-block;
                padding: 8px 14px;
                border-radius: 999px;
                font-size: 13px;
                font-weight: 600;
                color: #fff;
                min-width: 95px;
                text-align: center;
            }

            .status-delivered {
                background: #17b978;
            }
            .status-pending {
                background: #f0b429;
            }
            .status-cancelled {
                background: #ff5b5b;
            }
            .status-confirmed {
                background: #4c8bf5;
            }
            .status-shipped {
                background: #7a6cff;
            }
            .status-default {
                background: #8d8d8d;
            }

            .footer {
                margin-top: 35px;
                border-top: 1px solid #ddd;
                padding: 28px 0 18px;
            }

            .footer-top {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 25px;
                margin-bottom: 22px;
                font-size: 14px;
                color: #666;
            }

            .footer-top h4 {
                color: #222;
                margin-bottom: 10px;
                font-size: 15px;
            }

            .footer-bottom {
                border-top: 1px solid #ddd;
                padding-top: 16px;
                font-size: 13px;
                color: #666;
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
            }

            @media (max-width: 1100px) {
                .stats-row {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            @media (max-width: 900px) {
                .main-layout {
                    grid-template-columns: 1fr;
                }

                .sidebar {
                    padding-bottom: 10px;
                }
            }

            @media (max-width: 640px) {
                .stats-row {
                    grid-template-columns: 1fr;
                }

                .page-wrapper {
                    padding: 18px 16px 0;
                }

                .footer-top {
                    grid-template-columns: 1fr;
                }

                .stat-value {
                    font-size: 32px;
                }
            }
        </style>
    </head>
    <body>
        <div class="page-wrapper">
            <div class="topbar">
                <img src="../../assets/images/Logos/NextPickStore-Logo.png" alt="NextPick Logo">
            </div>

            <div class="main-layout">
                <aside class="sidebar">
                    <h2>Hi, <?php echo htmlspecialchars($adminName); ?></h2>

                    <nav class="nav-menu">
                        <a href="dashboard.php" class="nav-link active">
                            <img src="../../assets/images/icons/admin/home.png" alt="Dashboard">
                            <span>Dashboard</span>
                        </a>

                        <a href="manage_users/manage_users.php" class="nav-link">
                            <img src="../../assets/images/icons/admin/users.png" alt="Manage users">
                            <span>Manage users</span>
                        </a>

                        <a href="manage_products/manage_products.php" class="nav-link">
                            <img src="../../assets/images/icons/admin/box.png" alt="Manage products">
                            <span>Manage products</span>
                        </a>

                        <a href="system_reports/system_reports.php" class="nav-link">
                            <img src="../../assets/images/icons/admin/report.png" alt="System reports">
                            <span>System reports</span>
                        </a>

                        <a href="profile/profile.php" class="nav-link">
                            <img src="../../assets/images/icons/admin/profile.png" alt="My profile">
                            <span>My profile</span>
                        </a>

                        <a href="../../auth/logout.php" class="nav-link logout-link">
                            <img src="../../assets/images/icons/admin/logout.png" alt="Log out">
                            <span>Logout</span>
                        </a>
                    </nav>
                </aside>

                <main class="content">
                    <div class="section-title">Dashboard</div>

                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-top">
                                <div>
                                    <div class="stat-label">Total Users</div>
                                    <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                                </div>
                                <div class="stat-icon icon-users">👥</div>
                            </div>
                            <div class="stat-note">Registered users in the system</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-top">
                                <div>
                                    <div class="stat-label">Total Products</div>
                                    <div class="stat-value"><?php echo number_format($totalProducts); ?></div>
                                </div>
                                <div class="stat-icon icon-products">📦</div>
                            </div>
                            <div class="stat-note">Products currently stored</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-top">
                                <div>
                                    <div class="stat-label">Total Comments</div>
                                    <div class="stat-value"><?php echo number_format($totalComments); ?></div>
                                </div>
                                <div class="stat-icon icon-comments">💬</div>
                            </div>
                            <div class="stat-note">Customer comments submitted</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-top">
                                <div>
                                    <div class="stat-label">Total Orders</div>
                                    <div class="stat-value"><?php echo number_format($totalOrders); ?></div>
                                </div>
                                <div class="stat-icon icon-orders">🛒</div>
                            </div>
                            <div class="stat-note">Orders placed by buyers</div>
                        </div>
                    </div>

                    <div class="orders-card">
                        <div class="orders-header">
                            <h3>Orders Details</h3>

                            <form method="GET" class="filter-form">
                                <select name="status" class="status-filter" onchange="this.form.submit()">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo ($statusFilter === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="shipped" <?php echo ($statusFilter === 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo ($statusFilter === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo ($statusFilter === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </form>
                        </div>

                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Location</th>
                                        <th>Date - Time</th>
                                        <th>Piece</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentOrders)): ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td>
                                                    <div class="product-cell">
                                                        <img src="../../<?php echo htmlspecialchars($order['image_path'] ?: 'assets/images/placeholder.png'); ?>" alt="Product">
                                                        <span><?php echo htmlspecialchars($order['product_name']); ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                                                <td><?php echo date('d.m.Y - h:i A', strtotime($order['order_date'])); ?></td>
                                                <td><?php echo (int) $order['quantity']; ?></td>
                                                <td>$<?php echo number_format($order['subtotal'], 2); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo formatStatusClass($order['order_status']); ?>">
                                                        <?php echo htmlspecialchars(ucfirst($order['order_status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6">No order data found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>
            </div>

            <footer class="footer">
                <div class="footer-top">
                    <div>
                        <h4>E-commerce support</h4>
                        <div>NEXTPICK</div>
                        <div>Damstraat 123</div>
                        <div>1012 AB Amsterdam</div>
                        <div>The Netherlands</div>
                        <br>
                        <div>Phone: +31 20 123 4567</div>
                        <div>Email: support@nextpick.com</div>
                    </div>

                    <div>
                        <h4>About us</h4>
                        <div>Career</div>
                    </div>

                    <div>
                        <h4>Help & Support</h4>
                        <div>Help center</div>
                        <div>FAQ</div>
                    </div>

                    <div>
                        <h4>Find Us</h4>
                        <div>Facebook | Instagram | Twitter</div>
                    </div>
                </div>

                <div class="footer-bottom">
                    <div>© 2026 NEXTPICK. All Rights Reserved.</div>
                    <div>Privacy policy &nbsp;&nbsp; Cookie settings &nbsp;&nbsp; Terms and conditions</div>
                </div>
            </footer>
        </div>
    </body>
</html>