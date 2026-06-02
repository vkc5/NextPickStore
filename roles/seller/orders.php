<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'];
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

/* =========================
   FILTERS
========================= */
$statusFilter = trim($_GET['status'] ?? '');
$orderNumber  = trim($_GET['order_number'] ?? '');
$startDate    = trim($_GET['start_date'] ?? '');
$endDate      = trim($_GET['end_date'] ?? '');

$validStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

if ($orderNumber !== '' && (!ctype_digit($orderNumber) || (int)$orderNumber <= 0)) {
    $orderNumber = '';
}

/* =========================
   FETCH ORDERS
========================= */
$orders = [];

$sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.order_status,
        u.full_name AS customer_name,
        u.email,
        SUM(oi.subtotal) AS seller_total_amount
    FROM nps_orders o
    INNER JOIN nps_users u
        ON o.buyer_id = u.user_id
    INNER JOIN nps_order_items oi
        ON o.order_id = oi.order_id
    INNER JOIN nps_products p
        ON oi.product_id = p.product_id
    WHERE p.seller_id = ?
";

$params = [$sellerId];
$types  = "i";

if ($statusFilter !== '') {
    $sql .= " AND o.order_status = ? ";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($orderNumber !== '') {
    $sql .= " AND o.order_id = ? ";
    $params[] = (int)$orderNumber;
    $types .= "i";
}

if ($startDate !== '') {
    $sql .= " AND DATE(o.order_date) >= ? ";
    $params[] = $startDate;
    $types .= "s";
}

if ($endDate !== '') {
    $sql .= " AND DATE(o.order_date) <= ? ";
    $params[] = $endDate;
    $types .= "s";
}

$sql .= "
    GROUP BY
        o.order_id,
        o.order_date,
        o.order_status,
        u.full_name,
        u.email
    ORDER BY o.order_date DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}
mysqli_stmt_close($stmt);

/* =========================
   COUNTS
========================= */
$totalOrders    = count($orders);
$pendingCount   = 0;
$shippedCount   = 0;
$deliveredCount = 0;

foreach ($orders as $order) {
    if ($order['order_status'] === 'pending')   $pendingCount++;
    if ($order['order_status'] === 'shipped')   $shippedCount++;
    if ($order['order_status'] === 'delivered') $deliveredCount++;
}

function statusBadgeClass($status)
{
    switch (strtolower($status)) {
        case 'pending':   return 'status pending';
        case 'confirmed': return 'status confirmed';
        case 'shipped':   return 'status shipped';
        case 'delivered': return 'status delivered';
        case 'cancelled': return 'status cancelled';
        default:          return 'status';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - NextPick</title>
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
    min-height: 700px;
    flex: 1;
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

        .menu li { margin: 0; }

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

        /* ── SUMMARY CARDS ── */
        .summary-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 18px;
        }

        .summary-card .label {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }

        .summary-card .value {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }

        /* ── ORDERS CARD ── */
        .orders-card {
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 20px;
            padding: 18px 18px 8px;
        }

        /* ── FILTERS ── */
        .filters {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 16px;
            margin-bottom: 18px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 180px;
        }

        .filter-group label {
            font-size: 14px;
            color: #444;
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input[type="number"],
        .filter-group input[type="date"] {
            height: 44px;
            border: 1px solid #d9d9d9;
            border-radius: 10px;
            padding: 0 12px;
            background: #fff;
            outline: none;
            font-size: 14px;
            color: #333;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ── BUTTONS ── */
        .btn {
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary { background: #3158ff; color: #fff; }
        .btn-light   { background: #f1f1f5; color: #222; }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        th {
            text-align: left;
            background: #f3f3f3;
            color: #222;
            padding: 14px 10px;
            font-size: 14px;
            font-weight: 600;
        }

        td {
            padding: 14px 10px;
            border-top: 1px solid #ededed;
            font-size: 15px;
            color: #444;
            vertical-align: middle;
        }

        .checkbox-cell { width: 42px; }
        .checkbox-cell input { width: 18px; height: 18px; cursor: pointer; }

        .order-id { font-weight: 600; color: #555; }

        /* ── STATUS BADGES ── */
        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            min-width: 95px;
            text-align: center;
            text-transform: capitalize;
        }

        .pending   { background: #f7d73c; color: #111; }
        .confirmed { background: #dbeafe; color: #1d4ed8; }
        .shipped   { background: #ff7a00; color: #fff; }
        .delivered { background: #4ac84a; color: #fff; }
        .cancelled { background: #f8d7da; color: #b42318; }

        /* ── ACTION ICONS ── */
        .actions { display: flex; align-items: center; gap: 10px; }

        .icon-btn {
            width: 34px;
            height: 34px;
            border: 1px solid #d9d9d9;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            transition: 0.2s ease;
        }

        .icon-btn:hover { background: #f4f6ff; border-color: #3158ff; }
        .icon-btn svg   { width: 18px; height: 18px; stroke: #666; }

        .empty-state {
            padding: 28px 10px 20px;
            text-align: center;
            color: #777;
            font-size: 15px;
        }

        /* ── FOOTER ── */
        .footer {
    border-top: 1px solid #ececec;
    background: #fff;
    margin-top: auto;  /* ← this is the key change */
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
            .summary-row  { grid-template-columns: repeat(2, 1fr); }
            .footer-top   { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 900px) {
            .main-layout { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #ececec; }
            .summary-row { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 640px) {
            .summary-row  { grid-template-columns: 1fr; }
            .footer-top   { grid-template-columns: 1fr; }
            .topbar, .content, .footer-top, .footer-bottom, .sidebar { padding-left: 16px; padding-right: 16px; }
            .page-title-box h1 { font-size: 24px; }
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
                <li>
                    <a href="dashboard.php">
                        <img src="../../assets/images/icons/admin/home.png" alt="" class="menu-icon-img">
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="my_products.php">
                        <img src="../../assets/images/icons/admin/box.png" alt="" class="menu-icon-img">
                        <span>Inventory Management</span>
                    </a>
                </li>
                <li>
                    <a href="add_product.php">
                        <img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img">
                        <span>Add Product</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php" class="active">
                        <img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img">
                        <span>Orders</span>
                    </a>
                </li>
                <li>
                    <a href="customer_data.php">
                        <img src="../../assets/images/icons/admin/users.png" alt="" class="menu-icon-img">
                        <span>Customer Data</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php">
                        <img src="../../assets/images/icons/admin/report.png" alt="" class="menu-icon-img">
                        <span>Analytics & Reports</span>
                    </a>
                </li>
                <li>
                    <a href="help_center.php">
                        <img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img">
                        <span>Help Center</span>
                    </a>
                </li>
                <li>
                    <a href="../../auth/logout.php">
                        <img src="../../assets/images/icons/admin/logout.png" alt="" class="menu-icon-img">
                        <span>Log out</span>
                    </a>
                </li>
            </ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="content">
            <div class="page-title-box">
                <h1>Orders</h1>
            </div>

            <div class="summary-row">
                <div class="summary-card">
                    <div class="label">Total Orders</div>
                    <div class="value"><?php echo $totalOrders; ?></div>
                </div>
                <div class="summary-card">
                    <div class="label">Pending Orders</div>
                    <div class="value"><?php echo $pendingCount; ?></div>
                </div>
                <div class="summary-card">
                    <div class="label">Shipped Orders</div>
                    <div class="value"><?php echo $shippedCount; ?></div>
                </div>
                <div class="summary-card">
                    <div class="label">Delivered Orders</div>
                    <div class="value"><?php echo $deliveredCount; ?></div>
                </div>
            </div>

            <div class="orders-card">
                <form method="GET" class="filters">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Pending, Confirmed, Shipped, Delivered</option>
                            <option value="pending"   <?php echo ($statusFilter === 'pending')   ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo ($statusFilter === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="shipped"   <?php echo ($statusFilter === 'shipped')   ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo ($statusFilter === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo ($statusFilter === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="order_number">Order Number</label>
                        <input
                            type="number"
                            name="order_number"
                            id="order_number"
                            min="1"
                            placeholder="Example: 12"
                            value="<?php echo htmlspecialchars($orderNumber); ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="orders.php" class="btn btn-light">Reset</a>
                    </div>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">Select</th>
                                <th>Order ID</th>
                                <th>Customer Name</th>
                                <th>Email Address</th>
                                <th>Order Date</th>
                                <th>Total Amount</th>
                                <th>Shipping Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orders)) { ?>
                                <?php foreach ($orders as $order) { ?>
                                    <tr>
                                        <td class="checkbox-cell">
                                            <input type="checkbox" name="selected_orders[]" value="<?php echo (int)$order['order_id']; ?>">
                                        </td>
                                        <td class="order-id">#<?php echo (int)$order['order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($order['email']); ?></td>
                                        <td><?php echo date('Y/m/d', strtotime($order['order_date'])); ?></td>
                                        <td>$<?php echo number_format((float)$order['seller_total_amount'], 2); ?></td>
                                        <td>
                                            <span class="<?php echo statusBadgeClass($order['order_status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($order['order_status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a class="icon-btn" href="edit_order.php?order_id=<?php echo (int)$order['order_id']; ?>" title="Edit Order">
                                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487a2.25 2.25 0 113.182 3.182L8.25 19.463 4 20l.537-4.25L16.862 4.487z"/>
                                                    </svg>
                                                </a>
                                                <a class="icon-btn" href="order_details.php?order_id=<?php echo (int)$order['order_id']; ?>" title="View Order">
                                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.25l7.89 5.26a2 2 0 002.22 0L21 8.25M5.25 19.5h13.5A2.25 2.25 0 0021 17.25v-10.5A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v10.5A2.25 2.25 0 005.25 19.5z"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr>
                                    <td colspan="8" class="empty-state">No orders found for the selected filters.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- FOOTER -->
    <?php include_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

</div>
</body>
</html>
