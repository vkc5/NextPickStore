<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId = (int)($_SESSION['user_id'] ?? 0);
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

$today = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime('-30 days'));
$startDate = trim($_GET['start_date'] ?? $defaultStart);
$endDate = trim($_GET['end_date'] ?? $today);
$statusFilter = trim($_GET['status'] ?? '');
$stockFilter = trim($_GET['stock_filter'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$allowedStatuses = ['', 'pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
$allowedStockFilters = ['all', 'in_stock', 'low_stock', 'out_of_stock', 'hidden'];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = $defaultStart;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $today;
}
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}
if (!in_array($stockFilter, $allowedStockFilters, true)) {
    $stockFilter = 'all';
}

$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

function fetchOne($conn, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result) ?: [];
    mysqli_stmt_close($stmt);
    return $row;
}

function fetchAllRows($conn, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function stockStatusText($quantity)
{
    $quantity = (int)$quantity;
    if ($quantity <= 0) {
        return 'Out of Stock';
    }
    if ($quantity <= 10) {
        return 'Low Stock';
    }
    return 'In Stock';
}

function stockStatusClass($quantity)
{
    $quantity = (int)$quantity;
    if ($quantity <= 0) {
        return 'out-stock';
    }
    if ($quantity <= 10) {
        return 'low-stock';
    }
    return 'in-stock';
}

function renderStars($rating)
{
    $rating = max(0, min(5, (int)round((float)$rating)));
    return str_repeat('*', $rating) . str_repeat('-', 5 - $rating);
}

function addCommonFilters(&$sql, &$types, &$params, $statusFilter, $startDateTime, $endDateTime)
{
    $sql .= " AND o.order_date BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = $startDateTime;
    $params[] = $endDateTime;

    if ($statusFilter !== '') {
        $sql .= " AND o.order_status = ?";
        $types .= 's';
        $params[] = $statusFilter;
    }
}

$orderWhereSql = "
    FROM nps_orders o
    INNER JOIN nps_order_items oi ON o.order_id = oi.order_id
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    WHERE p.seller_id = ?
";
$orderTypes = 'i';
$orderParams = [$sellerId];
addCommonFilters($orderWhereSql, $orderTypes, $orderParams, $statusFilter, $startDateTime, $endDateTime);

$summary = fetchOne($conn, "
    SELECT
        IFNULL(SUM(oi.subtotal), 0) AS total_revenue,
        COUNT(DISTINCT o.order_id) AS total_orders,
        IFNULL(SUM(oi.quantity), 0) AS units_sold,
        COUNT(DISTINCT o.buyer_id) AS buyers_reached
    $orderWhereSql
", $orderTypes, $orderParams);

$inventory = fetchOne($conn, "
    SELECT
        COUNT(*) AS total_products,
        SUM(CASE WHEN publish_status = 'published' THEN 1 ELSE 0 END) AS published_products,
        SUM(CASE WHEN publish_status = 'draft' THEN 1 ELSE 0 END) AS draft_products,
        SUM(CASE WHEN publish_status = 'hidden' THEN 1 ELSE 0 END) AS hidden_products,
        SUM(CASE WHEN stock_quantity <= 10 THEN 1 ELSE 0 END) AS low_stock_products
    FROM nps_products
    WHERE seller_id = ?
", 'i', [$sellerId]);

$engagement = fetchOne($conn, "
    SELECT
        (
            SELECT COUNT(*)
            FROM nps_product_views pv
            INNER JOIN nps_products p ON pv.product_id = p.product_id
            WHERE p.seller_id = ? AND pv.view_date BETWEEN ? AND ?
        ) AS total_views,
        (
            SELECT COUNT(*)
            FROM nps_comments c
            INNER JOIN nps_products p ON c.product_id = p.product_id
            WHERE p.seller_id = ? AND c.created_at BETWEEN ? AND ?
        ) AS total_comments,
        (
            SELECT COUNT(*)
            FROM nps_ratings r
            INNER JOIN nps_products p ON r.product_id = p.product_id
            WHERE p.seller_id = ? AND r.created_at BETWEEN ? AND ?
        ) AS total_ratings,
        (
            SELECT ROUND(AVG(r.rating_value), 2)
            FROM nps_ratings r
            INNER JOIN nps_products p ON r.product_id = p.product_id
            WHERE p.seller_id = ? AND r.created_at BETWEEN ? AND ?
        ) AS average_rating
", 'ississississ', [
    $sellerId, $startDateTime, $endDateTime,
    $sellerId, $startDateTime, $endDateTime,
    $sellerId, $startDateTime, $endDateTime,
    $sellerId, $startDateTime, $endDateTime
]);

$topProduct = fetchOne($conn, "
    SELECT
        p.product_name,
        IFNULL(SUM(CASE WHEN o.order_id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS units_sold,
        IFNULL(SUM(CASE WHEN o.order_id IS NOT NULL THEN oi.subtotal ELSE 0 END), 0) AS revenue
    FROM nps_products p
    LEFT JOIN nps_order_items oi ON p.product_id = oi.product_id
    LEFT JOIN nps_orders o
        ON oi.order_id = o.order_id
       AND o.order_date BETWEEN ? AND ?
       " . ($statusFilter !== '' ? "AND o.order_status = ?" : "") . "
    WHERE p.seller_id = ?
    GROUP BY p.product_id, p.product_name
    ORDER BY units_sold DESC, revenue DESC, p.product_name ASC
    LIMIT 1
", $statusFilter !== '' ? 'sssi' : 'ssi', $statusFilter !== '' ? [$startDateTime, $endDateTime, $statusFilter, $sellerId] : [$startDateTime, $endDateTime, $sellerId]);

$chartRows = fetchAllRows($conn, "
    SELECT DATE(o.order_date) AS sale_date, IFNULL(SUM(oi.subtotal), 0) AS revenue
    $orderWhereSql
    GROUP BY DATE(o.order_date)
    ORDER BY sale_date ASC
", $orderTypes, $orderParams);

$chartLabels = [];
$chartValues = [];
foreach ($chartRows as $row) {
    $chartLabels[] = date('M d', strtotime($row['sale_date']));
    $chartValues[] = (float)$row['revenue'];
}
if (empty($chartLabels)) {
    $chartLabels = ['No sales'];
    $chartValues = [0];
}
$maxChart = max($chartValues);
if ($maxChart <= 0) {
    $maxChart = 1;
}

$orderStatsSql = "
    SELECT
        oi.product_id,
        SUM(oi.quantity) AS units_sold,
        SUM(oi.subtotal) AS revenue,
        COUNT(DISTINCT o.order_id) AS order_count
    FROM nps_order_items oi
    INNER JOIN nps_orders o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN ? AND ?
";
$performanceTypes = 'ss';
$performanceParams = [$startDateTime, $endDateTime];

if ($statusFilter !== '') {
    $orderStatsSql .= " AND o.order_status = ?";
    $performanceTypes .= 's';
    $performanceParams[] = $statusFilter;
}

$orderStatsSql .= " GROUP BY oi.product_id";

$performanceSql = "
    SELECT
        p.product_id,
        p.product_name,
        p.publish_status,
        p.stock_quantity,
        pi.image_path,
        IFNULL(os.units_sold, 0) AS units_sold,
        IFNULL(os.revenue, 0) AS revenue,
        IFNULL(os.order_count, 0) AS order_count,
        IFNULL(vs.view_count, 0) AS view_count,
        IFNULL(cs.comment_count, 0) AS comment_count,
        IFNULL(rs.rating_count, 0) AS rating_count,
        rs.average_rating
    FROM nps_products p
    LEFT JOIN nps_product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
    LEFT JOIN ($orderStatsSql) os ON p.product_id = os.product_id
    LEFT JOIN (
        SELECT product_id, COUNT(*) AS view_count
        FROM nps_product_views
        WHERE view_date BETWEEN ? AND ?
        GROUP BY product_id
    ) vs ON p.product_id = vs.product_id
    LEFT JOIN (
        SELECT product_id, COUNT(*) AS comment_count
        FROM nps_comments
        WHERE created_at BETWEEN ? AND ?
        GROUP BY product_id
    ) cs ON p.product_id = cs.product_id
    LEFT JOIN (
        SELECT product_id, COUNT(*) AS rating_count, ROUND(AVG(rating_value), 2) AS average_rating
        FROM nps_ratings
        WHERE created_at BETWEEN ? AND ?
        GROUP BY product_id
    ) rs ON p.product_id = rs.product_id
    WHERE p.seller_id = ?
";
$performanceTypes .= 'ssssssi';
array_push($performanceParams, $startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime, $sellerId);

if ($stockFilter === 'in_stock') {
    $performanceSql .= " AND p.stock_quantity > 10";
} elseif ($stockFilter === 'low_stock') {
    $performanceSql .= " AND p.stock_quantity BETWEEN 1 AND 10";
} elseif ($stockFilter === 'out_of_stock') {
    $performanceSql .= " AND p.stock_quantity = 0";
} elseif ($stockFilter === 'hidden') {
    $performanceSql .= " AND p.publish_status = 'hidden'";
} else {
    $performanceSql .= " AND p.publish_status <> 'hidden'";
}

if ($search !== '') {
    $performanceSql .= " AND p.product_name LIKE ?";
    $performanceTypes .= 's';
    $performanceParams[] = '%' . $search . '%';
}

$performanceSql .= "
    ORDER BY revenue DESC, units_sold DESC, view_count DESC, p.product_name ASC
";

$productRows = fetchAllRows($conn, $performanceSql, $performanceTypes, $performanceParams);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=seller_product_performance.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Product', 'Publish Status', 'Stock', 'Stock Status', 'Units Sold', 'Revenue', 'Orders', 'Views', 'Comments', 'Ratings', 'Average Rating']);
    foreach ($productRows as $row) {
        fputcsv($output, [
            $row['product_name'],
            $row['publish_status'],
            $row['stock_quantity'],
            stockStatusText($row['stock_quantity']),
            $row['units_sold'],
            number_format((float)$row['revenue'], 2),
            $row['order_count'],
            $row['view_count'],
            $row['comment_count'],
            $row['rating_count'],
            $row['average_rating'] ?: '0.00'
        ]);
    }
    fclose($output);
    exit;
}

$recentRatings = fetchAllRows($conn, "
    SELECT
        r.rating_value,
        r.created_at,
        u.full_name,
        p.product_name
    FROM nps_ratings r
    INNER JOIN nps_products p ON r.product_id = p.product_id
    INNER JOIN nps_users u ON r.user_id = u.user_id
    WHERE p.seller_id = ?
      AND r.created_at BETWEEN ? AND ?
    ORDER BY r.created_at DESC
    LIMIT 6
", 'iss', [$sellerId, $startDateTime, $endDateTime]);

$recentComments = fetchAllRows($conn, "
    SELECT
        c.comment_text,
        c.created_at,
        u.full_name,
        p.product_name
    FROM nps_comments c
    INNER JOIN nps_products p ON c.product_id = p.product_id
    INNER JOIN nps_users u ON c.user_id = u.user_id
    WHERE p.seller_id = ?
      AND c.created_at BETWEEN ? AND ?
    ORDER BY c.created_at DESC
    LIMIT 6
", 'iss', [$sellerId, $startDateTime, $endDateTime]);

$queryBase = http_build_query([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'status' => $statusFilter,
    'stock_filter' => $stockFilter,
    'search' => $search
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - NextPick</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; }
        body { background: #f4f4f6; color: #222; }
        a { text-decoration: none; color: inherit; }
        .page-wrapper { max-width: calc(100% - 50px); margin: 25px auto; background: #fff; border: 1px solid #e8e8e8; min-height: 90vh; }
        .topbar { padding: 18px 28px; border-bottom: 1px solid #ececec; background: #fafafa; display: flex; align-items: center; justify-content: space-between; }
        .topbar img { height: 34px; width: auto; object-fit: contain; display: block; }
        .topbar-right { display: flex; align-items: center; gap: 14px; }
        .seller-badge { width: 38px; height: 38px; border-radius: 50%; background: #e8e8ff; color: #3158ff; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .topbar-user { display: flex; flex-direction: column; gap: 2px; }
        .topbar-user strong { font-size: 14px; }
        .topbar-user span { font-size: 12px; color: #666; }
        .main-layout { display: flex; min-height: 700px; }
        .sidebar { width: 255px; border-right: 1px solid #ececec; padding: 28px 18px; background: #fff; flex-shrink: 0; }
        .sidebar h2 { font-size: 28px; line-height: 1.15; margin-bottom: 28px; font-weight: 700; color: #111827; }
        .menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .menu a { display: flex; align-items: center; gap: 12px; min-height: 48px; padding: 12px 14px; border-radius: 12px; color: #111827; font-size: 15px; font-weight: 500; transition: 0.2s ease; white-space: nowrap; }
        .menu a:hover { background: #f4f6ff; color: #3158ff; }
        .menu a.active { background: #eef2ff; color: #3158ff; font-weight: 600; }
        .menu-icon-img { width: 18px; height: 18px; object-fit: contain; flex-shrink: 0; }
        .content { flex: 1; padding: 28px; background: #fcfcfc;
            border-radius: 14px; min-width: 0; }
        .page-title { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 18px; }
        .page-title h1 { font-size: 30px; color: #111827; margin-bottom: 6px; }
        .page-title p { color: #667085; font-size: 14px; }
        .filters { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; display: grid; grid-template-columns: repeat(5, minmax(130px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .filters label { display: block; font-size: 12px; font-weight: 700; color: #555; margin-bottom: 6px; }
        .filters input, .filters select { width: 100%; min-height: 42px; border: 1px solid #d9d9df; border-radius: 10px; padding: 9px 11px; background: #fff; }
        .filter-actions { display: flex; gap: 8px; align-items: end; }
        .btn { border: none; border-radius: 10px; min-height: 42px; padding: 10px 14px; font-size: 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        .btn-primary { background: #3158ff; color: #fff; }
        .btn-light { background: #eef2ff; color: #3158ff; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
        .kpi-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; min-height: 116px; }
        .kpi-card .label { color: #667085; font-size: 13px; font-weight: 700; margin-bottom: 10px; }
        .kpi-card .value { font-size: 25px; font-weight: 800; color: #111827; line-height: 1.2; }
        .kpi-card .note { color: #667085; font-size: 13px; margin-top: 8px; }
        .grid { display: grid; grid-template-columns: 1.05fr 1.4fr; gap: 18px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; margin-bottom: 18px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; }
        .card-title { font-size: 18px; font-weight: 800; color: #111827; }
        .chart { height: 280px; display: flex; align-items: end; gap: 10px; border-bottom: 1px solid #e5e7eb; padding: 10px 6px 0; overflow-x: auto; }
        .bar-wrap { min-width: 42px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 8px; height: 100%; }
        .bar { width: 24px; border-radius: 8px 8px 0 0; background: #3158ff; min-height: 4px; }
        .bar-label { color: #667085; font-size: 11px; white-space: nowrap; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { text-align: left; background: #f3f4f6; color: #344054; padding: 12px 10px; font-size: 13px; font-weight: 800; }
        td { padding: 12px 10px; border-top: 1px solid #f0f0f0; font-size: 14px; vertical-align: middle; }
        .report-thumb { width: 42px; height: 42px; border-radius: 10px; object-fit: cover; background: #f3f4f6; }
        .status-badge, .publish-badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 800; text-transform: capitalize; }
        .in-stock, .publish-published { background: #e7faed; color: #1b8a46; }
        .low-stock, .publish-draft { background: #fff5d6; color: #9c6b00; }
        .out-stock, .publish-hidden { background: #ffe8e8; color: #b42318; }
        .activity-list { display: grid; gap: 10px; }
        .activity-row { border: 1px solid #f0f0f0; border-radius: 12px; padding: 12px; }
        .activity-row strong { display: block; color: #111827; margin-bottom: 4px; }
        .activity-row span { color: #667085; font-size: 12px; }
        .stars { color: #f59e0b; letter-spacing: 1px; }
        .empty { color: #667085; font-size: 14px; padding: 10px 0; }
        .footer { border-top: 1px solid #ececec; background: #fff; margin-top: 24px; }
        .footer-top { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; padding: 28px; }
        .footer h4 { font-size: 14px; margin-bottom: 12px; }
        .footer p, .footer a, .footer li { font-size: 13px; color: #666; line-height: 1.8; }
        .footer ul { list-style: none; }
        .footer-bottom { border-top: 1px solid #f0f0f0; padding: 16px 28px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 12px; color: #666; }
        .footer-links { display: flex; gap: 18px; flex-wrap: wrap; }
        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .grid { grid-template-columns: 1fr; }
            .filters { grid-template-columns: repeat(2, 1fr); }
            .footer-top { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 900px) {
            .main-layout { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #ececec; }
        }
        @media (max-width: 640px) {
            .page-wrapper { max-width: 100%; margin: 0; }
            .topbar, .content, .sidebar, .footer-top, .footer-bottom { padding-left: 16px; padding-right: 16px; }
            .kpi-grid, .filters, .footer-top { grid-template-columns: 1fr; }
            .page-title { display: block; }
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
                <li><a href="reports.php" class="active"><img src="../../assets/images/icons/admin/report.png" alt="" class="menu-icon-img"><span>Analytics & Reports</span></a></li>
                <li><a href="help_center.php"><img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img"><span>Help Center</span></a></li>
                <li><a href="profile.php"><img src="../../assets/images/icons/admin/profile.png" alt="" class="menu-icon-img"><span>My Profile</span></a></li>
                <li><a href="../../auth/logout.php"><img src="../../assets/images/icons/admin/logout.png" alt="" class="menu-icon-img"><span>Log out</span></a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="page-title">
                <div>
                    <h1>Analytics & Reports</h1>
                    <p>Seller-only performance data from orders, product views, ratings, comments, and stock.</p>
                </div>
                <a class="btn btn-light" href="/NextPickStore/roles/seller/reports.php?<?php echo htmlspecialchars($queryBase); ?>&export=csv">Export CSV</a>
            </div>

            <form class="filters" method="GET">
                <div>
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div>
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div>
                    <label for="status">Order Status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach (array_slice($allowedStatuses, 1) as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="stock_filter">Products</label>
                    <select id="stock_filter" name="stock_filter">
                        <option value="all" <?php echo $stockFilter === 'all' ? 'selected' : ''; ?>>Visible products</option>
                        <option value="in_stock" <?php echo $stockFilter === 'in_stock' ? 'selected' : ''; ?>>In stock</option>
                        <option value="low_stock" <?php echo $stockFilter === 'low_stock' ? 'selected' : ''; ?>>Low stock</option>
                        <option value="out_of_stock" <?php echo $stockFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of stock</option>
                        <option value="hidden" <?php echo $stockFilter === 'hidden' ? 'selected' : ''; ?>>Hidden</option>
                    </select>
                </div>
                <div>
                    <label for="search">Product Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Product name">
                </div>
                <div class="filter-actions">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-light" href="reports.php">Reset</a>
                </div>
            </form>

            <section class="kpi-grid">
                <div class="kpi-card">
                    <div class="label">Revenue</div>
                    <div class="value">BHD <?php echo number_format((float)($summary['total_revenue'] ?? 0), 2); ?></div>
                    <div class="note">From seller items in selected orders.</div>
                </div>
                <div class="kpi-card">
                    <div class="label">Orders / Units</div>
                    <div class="value"><?php echo (int)($summary['total_orders'] ?? 0); ?> / <?php echo (int)($summary['units_sold'] ?? 0); ?></div>
                    <div class="note">Distinct orders and sold item quantity.</div>
                </div>
                <div class="kpi-card">
                    <div class="label">Engagement</div>
                    <div class="value"><?php echo (int)($engagement['total_views'] ?? 0); ?> views</div>
                    <div class="note"><?php echo (int)($engagement['total_comments'] ?? 0); ?> comments, <?php echo (int)($engagement['total_ratings'] ?? 0); ?> ratings.</div>
                </div>
                <div class="kpi-card">
                    <div class="label">Average Rating</div>
                    <div class="value"><?php echo $engagement['average_rating'] ? number_format((float)$engagement['average_rating'], 2) : '0.00'; ?>/5</div>
                    <div class="note">Top: <?php echo htmlspecialchars($topProduct['product_name'] ?? '-'); ?></div>
                </div>
                <div class="kpi-card">
                    <div class="label">Products</div>
                    <div class="value"><?php echo (int)($inventory['total_products'] ?? 0); ?></div>
                    <div class="note"><?php echo (int)($inventory['published_products'] ?? 0); ?> published, <?php echo (int)($inventory['draft_products'] ?? 0); ?> draft.</div>
                </div>
                <div class="kpi-card">
                    <div class="label">Low Stock</div>
                    <div class="value"><?php echo (int)($inventory['low_stock_products'] ?? 0); ?></div>
                    <div class="note"><?php echo (int)($inventory['hidden_products'] ?? 0); ?> hidden products.</div>
                </div>
                <div class="kpi-card">
                    <div class="label">Buyers Reached</div>
                    <div class="value"><?php echo (int)($summary['buyers_reached'] ?? 0); ?></div>
                    <div class="note">Unique buyers for this seller.</div>
                </div>
                <div class="kpi-card">
                    <div class="label">Top Product Units</div>
                    <div class="value"><?php echo (int)($topProduct['units_sold'] ?? 0); ?></div>
                    <div class="note">BHD <?php echo number_format((float)($topProduct['revenue'] ?? 0), 2); ?> revenue.</div>
                </div>
            </section>

            <section class="grid">
                <div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Revenue by Day</div>
                            <span><?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?></span>
                        </div>
                        <div class="chart">
                            <?php foreach ($chartLabels as $index => $label): ?>
                                <?php
                                    $value = $chartValues[$index];
                                    $height = ($value / $maxChart) * 235;
                                    if ($height < 4) {
                                        $height = 4;
                                    }
                                ?>
                                <div class="bar-wrap" title="BHD <?php echo number_format($value, 2); ?>">
                                    <div class="bar" style="height: <?php echo (int)$height; ?>px;"></div>
                                    <div class="bar-label"><?php echo htmlspecialchars($label); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Recent Ratings</div>
                        </div>
                        <div class="activity-list">
                            <?php if (empty($recentRatings)): ?>
                                <div class="empty">No ratings in this period.</div>
                            <?php else: ?>
                                <?php foreach ($recentRatings as $rating): ?>
                                    <div class="activity-row">
                                        <strong><?php echo htmlspecialchars($rating['product_name']); ?></strong>
                                        <div class="stars"><?php echo htmlspecialchars(renderStars($rating['rating_value'])); ?></div>
                                        <span><?php echo htmlspecialchars($rating['full_name']); ?> / <?php echo date('M d, Y', strtotime($rating['created_at'])); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Recent Comments</div>
                        </div>
                        <div class="activity-list">
                            <?php if (empty($recentComments)): ?>
                                <div class="empty">No comments in this period.</div>
                            <?php else: ?>
                                <?php foreach ($recentComments as $comment): ?>
                                    <div class="activity-row">
                                        <strong><?php echo htmlspecialchars($comment['product_name']); ?></strong>
                                        <p><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                                        <span><?php echo htmlspecialchars($comment['full_name']); ?> / <?php echo date('M d, Y', strtotime($comment['created_at'])); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Product Performance</div>
                        <span><?php echo count($productRows); ?> rows</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Stock</th>
                                    <th>Units</th>
                                    <th>Revenue</th>
                                    <th>Orders</th>
                                    <th>Views</th>
                                    <th>Comments</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($productRows)): ?>
                                    <tr><td colspan="9" class="empty">No products match the selected filters.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($productRows as $row): ?>
                                        <tr>
                                            <td>
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <img class="report-thumb" src="/NextPickStore/<?php echo htmlspecialchars($row['image_path'] ?: 'uploads/products/view.png'); ?>" alt="">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($row['product_name']); ?></strong><br>
                                                        <span class="publish-badge publish-<?php echo htmlspecialchars($row['publish_status']); ?>"><?php echo htmlspecialchars($row['publish_status']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="status-badge <?php echo stockStatusClass($row['stock_quantity']); ?>"><?php echo stockStatusText($row['stock_quantity']); ?></span></td>
                                            <td><?php echo (int)$row['stock_quantity']; ?></td>
                                            <td><?php echo (int)$row['units_sold']; ?></td>
                                            <td>BHD <?php echo number_format((float)$row['revenue'], 2); ?></td>
                                            <td><?php echo (int)$row['order_count']; ?></td>
                                            <td><?php echo (int)$row['view_count']; ?></td>
                                            <td><?php echo (int)$row['comment_count']; ?></td>
                                            <td><?php echo $row['average_rating'] ? number_format((float)$row['average_rating'], 2) : '0.00'; ?> (<?php echo (int)$row['rating_count']; ?>)</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <?php include_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
</div>
</body>
</html>
