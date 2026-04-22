<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'];
$sellerName = $_SESSION['full_name'] ?? 'Seller';

/* =========================
   DASHBOARD COUNTS
========================= */

$revenue = 0;
$revenueSql = "
    SELECT IFNULL(SUM(oi.subtotal), 0) AS total_revenue
    FROM nps_order_items oi
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    INNER JOIN nps_orders o ON oi.order_id = o.order_id
    WHERE p.seller_id = ?
      AND o.order_status IN ('confirmed', 'shipped', 'delivered')
";
$stmt = mysqli_prepare($conn, $revenueSql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$revenueResult = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($revenueResult)) {
    $revenue = $row['total_revenue'];
}
mysqli_stmt_close($stmt);

$activeOrders = 0;
$activeSql = "
    SELECT COUNT(DISTINCT o.order_id) AS active_orders
    FROM nps_orders o
    INNER JOIN nps_order_items oi ON o.order_id = oi.order_id
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    WHERE p.seller_id = ?
      AND o.order_status IN ('pending', 'confirmed', 'shipped')
";
$stmt = mysqli_prepare($conn, $activeSql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$activeResult = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($activeResult)) {
    $activeOrders = $row['active_orders'];
}
mysqli_stmt_close($stmt);

$totalProducts = 0;
$totalSql = "SELECT COUNT(*) AS total_products FROM nps_products WHERE seller_id = ?";
$stmt = mysqli_prepare($conn, $totalSql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$totalResult = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($totalResult)) {
    $totalProducts = $row['total_products'];
}
mysqli_stmt_close($stmt);

$outOfStock = 0;
$outSql = "SELECT COUNT(*) AS out_stock FROM nps_products WHERE seller_id = ? AND stock_quantity = 0";
$stmt = mysqli_prepare($conn, $outSql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$outResult = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($outResult)) {
    $outOfStock = $row['out_stock'];
}
mysqli_stmt_close($stmt);

/* =========================
   RECENT ORDERS
========================= */
$recentOrders = [];
$recentSql = "
    SELECT 
        o.order_id,
        u.full_name AS customer_name,
        o.order_date,
        SUM(oi.subtotal) AS total_amount,
        o.order_status
    FROM nps_orders o
    INNER JOIN nps_users u ON o.buyer_id = u.user_id
    INNER JOIN nps_order_items oi ON o.order_id = oi.order_id
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    WHERE p.seller_id = ?
    GROUP BY o.order_id, u.full_name, o.order_date, o.order_status
    ORDER BY o.order_date DESC
    LIMIT 5
";
$stmt = mysqli_prepare($conn, $recentSql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$recentResult = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($recentResult)) {
    $recentOrders[] = $row;
}
mysqli_stmt_close($stmt);

/* =========================
   LOW STOCK ALERTS
========================= */
$lowStockProducts = [];
$lowStockSql = "
    SELECT 
        p.product_id,
        p.product_name,
        p.stock_quantity,
        img.image_path
    FROM nps_products p
    LEFT JOIN nps_product_images img 
        ON p.product_id = img.product_id AND img.is_primary = 1
    WHERE p.seller_id = ?
      AND p.stock_quantity <= 7
      AND p.publish_status <> 'hidden'
    ORDER BY p.stock_quantity ASC, p.product_name ASC
    LIMIT 4
";
$stmt = mysqli_prepare($conn, $lowStockSql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$lowStockResult = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($lowStockResult)) {
    $lowStockProducts[] = $row;
}
mysqli_stmt_close($stmt);

/* =========================
   TOP SELLING PRODUCTS
========================= */
$topProducts = [];
$topSql = "
    SELECT 
        p.product_name,
        IFNULL(SUM(oi.quantity), 0) AS total_sold
    FROM nps_products p
    LEFT JOIN nps_order_items oi ON p.product_id = oi.product_id
    LEFT JOIN nps_orders o ON oi.order_id = o.order_id
    WHERE p.seller_id = ?
      AND p.publish_status <> 'hidden'
    GROUP BY p.product_id, p.product_name
    ORDER BY total_sold DESC, p.product_name ASC
    LIMIT 5
";
$stmt = mysqli_prepare($conn, $topSql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$topResult = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($topResult)) {
    $topProducts[] = $row;
}
mysqli_stmt_close($stmt);

$maxSold = 1;
foreach ($topProducts as $item) {
    if ((int)$item['total_sold'] > $maxSold) {
        $maxSold = (int)$item['total_sold'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - NextPick</title>
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
        }

        .topbar {
            padding: 18px 28px;
            border-bottom: 1px solid #ececec;
            background: #fafafa;
        }

        .topbar img {
            height: 34px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        .main-layout {
            display: flex;
            min-height: 700px;
        }

        .sidebar {
            width: 255px;
            border-right: 1px solid #ececec;
            padding: 28px 18px;
            background: #fff;
            flex-shrink: 0;
            position: relative;
            z-index: 20;
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

        .menu li {
            margin: 0;
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
            position: relative;
            z-index: 30;
            pointer-events: auto;
        }

        .menu a:hover {
            background: #f4f6ff;
            color: #3158ff;
        }

        .menu a.active {
            background: #eef2ff;
            color: #3158ff;
            font-weight: 600;
        }

        .menu-icon-img {
            width: 18px;
            height: 18px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .content {
            flex: 1;
            padding: 28px;
            background: #fcfcfc;
            min-width: 0;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .content-header h1 {
            font-size: 32px;
            margin-bottom: 6px;
        }

        .content-header p {
            color: #666;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            background: #3158ff;
            color: #fff;
        }

        .btn-light {
            background: #f1f1f5;
            color: #222;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 18px;
        }

        .stat-card .label {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 31px;
            font-weight: 700;
        }

        .stat-card .danger {
            color: #d82121;
        }

        .cards-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 18px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            font-size: 13px;
            color: #666;
            background: #f6f6f8;
            padding: 12px 10px;
            font-weight: 600;
        }

        td {
            padding: 13px 10px;
            border-top: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: lowercase;
        }

        .pending { background: #fff5d6; color: #9c6b00; }
        .confirmed { background: #e7f1ff; color: #1c5fb8; }
        .shipped { background: #ede8ff; color: #6246d6; }
        .delivered { background: #e7faed; color: #1b8a46; }
        .cancelled { background: #ffe8e8; color: #b42318; }

        .alert-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .alert-item {
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #f1f1f1;
            padding-bottom: 12px;
        }

        .alert-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .alert-item img {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #eee;
            background: #fafafa;
        }

        .alert-info h4 {
            font-size: 14px;
            margin-bottom: 5px;
        }

        .alert-info p {
            color: #d82121;
            font-size: 13px;
            font-weight: 700;
        }

        .bottom-row {
            display: grid;
            grid-template-columns: 1.7fr 1fr;
            gap: 18px;
        }

        .chart-box {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            height: 260px;
            padding: 10px 18px 0;
            border-left: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
            margin-top: 8px;
            overflow-x: auto;
        }

        .bar-group {
            width: 90px;
            min-width: 90px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .bar {
            width: 42px;
            background: linear-gradient(to top, #4f6bff, #7f95ff);
            border-radius: 12px 12px 0 0;
        }

        .bar-label {
            width: 90px;
            height: 38px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            font-size: 12px;
            color: #444;
            text-align: center;
            line-height: 1.35;
            white-space: normal;
            word-break: break-word;
            overflow: hidden;
        }

        .product-table table td,
        .product-table table th {
            padding: 12px 8px;
        }

        .footer {
            border-top: 1px solid #ececec;
            background: #fff;
            margin-top: 24px;
        }

        .footer-top {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 24px;
            padding: 28px;
        }

        .footer h4 {
            font-size: 14px;
            margin-bottom: 12px;
        }

        .footer p,
        .footer a,
        .footer li {
            font-size: 13px;
            color: #666;
            line-height: 1.8;
        }

        .footer ul {
            list-style: none;
        }

        .newsletter input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #3158ff;
            border-radius: 8px;
            outline: none;
            margin-top: 8px;
        }

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

        .footer-links {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .cards-row,
            .bottom-row,
            .footer-top {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .main-layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #ececec;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-header h1 {
                font-size: 24px;
            }

            .topbar,
            .content,
            .footer-top,
            .footer-bottom,
            .sidebar {
                padding-left: 16px;
                padding-right: 16px;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <div class="topbar">
        <img src="../../assets/images/logos/logo.png" alt="NextPick Logo">
    </div>

    <div class="main-layout">
        <aside class="sidebar">
            <h2>Welcome,<br><?php echo htmlspecialchars(explode(' ', $sellerName)[0]); ?></h2>

            <ul class="menu">
                <li>
                    <a href="dashboard.php" class="active">
                        <img src="../../assets/images/icons/seller icon/dashboard.png" alt="Dashboard" class="menu-icon-img">
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="my_products.php">
                        <img src="../../assets/images/icons/seller icon/inventory-management.png" alt="Inventory Management" class="menu-icon-img">
                        <span>Inventory Management</span>
                    </a>
                </li>

                <li>
                    <a href="add_product.php">
                        <img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="Add Product" class="menu-icon-img">
                        <span>Add Product</span>
                    </a>
                </li>

                <li>
                    <a href="../buyer/my_orders.php">
                        <img src="../../assets/images/icons/seller icon/manifest.png" alt="Orders" class="menu-icon-img">
                        <span>Orders</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <img src="../../assets/images/icons/seller icon/client.png" alt="Customer Data" class="menu-icon-img">
                        <span>Customer Data</span>
                    </a>
                </li>

                <li>
                    <a href="reports.php">
                        <img src="../../assets/images/icons/seller icon/seo-report.png" alt="Analytics & Reports" class="menu-icon-img">
                        <span>Analytics & Reports</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <img src="../../assets/images/icons/seller icon/settings.png" alt="Settings" class="menu-icon-img">
                        <span>Settings</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <img src="../../assets/images/icons/seller icon/customer-support.png" alt="Help Center" class="menu-icon-img">
                        <span>Help Center</span>
                    </a>
                </li>

                <li>
                    <a href="../../auth/logout.php">
                        <img src="../../assets/images/icons/seller icon/logout.png" alt="Log out" class="menu-icon-img">
                        <span>Log out</span>
                    </a>
                </li>
            </ul>
        </aside>

        <main class="content">
            <div class="content-header">
                <div>
                    <h1>Dashboard</h1>
                    <p>Manage your store, products, stock, and orders from one place.</p>
                </div>

                <div class="header-actions">
                    <a href="add_product.php"><button class="btn btn-primary">+ Add Product</button></a>
                    <a href="my_products.php"><button class="btn btn-light">View Products</button></a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">TOTAL Revenue</div>
                    <div class="value">$<?php echo number_format($revenue, 2); ?></div>
                </div>

                <div class="stat-card">
                    <div class="label">Active Orders</div>
                    <div class="value"><?php echo $activeOrders; ?></div>
                </div>

                <div class="stat-card">
                    <div class="label">Total Products</div>
                    <div class="value"><?php echo $totalProducts; ?></div>
                </div>

                <div class="stat-card">
                    <div class="label">Out of Stock Items</div>
                    <div class="value danger"><?php echo $outOfStock; ?></div>
                </div>
            </div>

            <div class="cards-row">
                <div class="card">
                    <div class="card-title">Recent Orders</div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentOrders)) { ?>
                                    <?php foreach ($recentOrders as $order) { ?>
                                        <tr>
                                            <td>#<?php echo $order['order_id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="status <?php echo strtolower($order['order_status']); ?>">
                                                    <?php echo htmlspecialchars($order['order_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr>
                                        <td colspan="5">No recent orders found.</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">Low Stock Alerts</div>

                    <div class="alert-list">
                        <?php if (!empty($lowStockProducts)) { ?>
                            <?php foreach ($lowStockProducts as $product) { ?>
                                <div class="alert-item">
                                    <img src="/NextPickStore/<?php echo !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'assets/images/products/default.png'; ?>" alt="Product">
                                    <div class="alert-info">
                                        <h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
                                        <p><?php echo (int)$product['stock_quantity']; ?> units left</p>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <p style="color:#666; font-size:14px;">No low-stock products right now.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="bottom-row">
                <div class="card">
                    <div class="card-title">Top-Selling Products</div>

                    <div class="chart-box">
                        <?php if (!empty($topProducts)) { ?>
                            <?php foreach ($topProducts as $item) {
                                $height = ($item['total_sold'] / $maxSold) * 180;
                                if ($height < 20) {
                                    $height = 20;
                                }
                            ?>
                                <div class="bar-group">
                                    <div class="bar" style="height: <?php echo $height; ?>px;"></div>
                                    <div class="bar-label">
                                        <?php echo htmlspecialchars(mb_strimwidth($item['product_name'], 0, 18, '...')); ?>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <p style="color:#666; font-size:14px;">No sales data available yet.</p>
                        <?php } ?>
                    </div>
                </div>

                <div class="card product-table">
                    <div class="card-title">Top Products Summary</div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Total Sold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($topProducts)) { ?>
                                    <?php foreach ($topProducts as $item) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td><?php echo (int)$item['total_sold']; ?></td>
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr>
                                        <td colspan="2">No product data available.</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

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

            <div class="newsletter">
                <h4>Sign up for exclusive offers and the latest news!</h4>
                <input type="email" placeholder="Your email...">
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