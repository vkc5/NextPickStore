<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'] ?? 0;
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

/* =========================
   HANDLE REVIEW DELETE
========================= */
if (isset($_GET['hide_rating']) && is_numeric($_GET['hide_rating'])) {
    $ratingId = (int) $_GET['hide_rating'];

    $hideSql = "
        DELETE r
        FROM nps_ratings r
        INNER JOIN nps_products p ON r.product_id = p.product_id
        WHERE r.rating_id = ?
          AND p.seller_id = ?
    ";
    $stmt = mysqli_prepare($conn, $hideSql);
    mysqli_stmt_bind_param($stmt, "ii", $ratingId, $sellerId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: /NextPickStore/roles/seller/reports.php");
    exit();
}

/* =========================
   EXPORT CSV
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $productFilter = trim($_GET['product_filter'] ?? 'all');

    $exportRows = [];

    $sql = "
        SELECT 
            p.product_name,
            IFNULL(SUM(oi.subtotal), 0) AS revenue,
            p.stock_quantity
        FROM nps_products p
        LEFT JOIN nps_order_items oi ON p.product_id = oi.product_id
        WHERE p.seller_id = ?
          AND p.publish_status <> 'hidden'
    ";

    if ($productFilter === 'in_stock') {
        $sql .= " AND p.stock_quantity > 10";
    } elseif ($productFilter === 'low_stock') {
        $sql .= " AND p.stock_quantity BETWEEN 1 AND 10";
    } elseif ($productFilter === 'out_of_stock') {
        $sql .= " AND p.stock_quantity = 0";
    }

    $sql .= "
        GROUP BY p.product_id, p.product_name, p.stock_quantity
        ORDER BY revenue DESC, p.product_name ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $sellerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $stockQty = (int)$row['stock_quantity'];

        if ($stockQty == 0) {
            $statusText = 'Out of Stock';
        } elseif ($stockQty <= 10) {
            $statusText = 'Low Stock';
        } else {
            $statusText = 'In Stock';
        }

        $exportRows[] = [
            'product_name' => $row['product_name'],
            'revenue'      => $row['revenue'],
            'status'       => $statusText
        ];
    }
    mysqli_stmt_close($stmt);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sales_report.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Product Name', 'Revenue', 'Status']);

    foreach ($exportRows as $row) {
        fputcsv($output, [
            $row['product_name'],
            number_format($row['revenue'], 2),
            $row['status']
        ]);
    }

    fclose($output);
    exit();
}

/* =========================
   KPI DATA
========================= */
$totalSales = 0;
$totalOrders = 0;
$topProductName = "-";
$topProductOrders = 0;
$lowStockItems = 0;

$sql = "
    SELECT IFNULL(SUM(oi.subtotal), 0) AS total_sales
    FROM nps_order_items oi
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    INNER JOIN nps_orders o ON oi.order_id = o.order_id
    WHERE p.seller_id = ?
      AND o.order_status IN ('confirmed', 'shipped', 'delivered')
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $totalSales = $row['total_sales'];
}
mysqli_stmt_close($stmt);

$sql = "
    SELECT COUNT(DISTINCT o.order_id) AS total_orders
    FROM nps_orders o
    INNER JOIN nps_order_items oi ON o.order_id = oi.order_id
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    WHERE p.seller_id = ?
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $totalOrders = $row['total_orders'];
}
mysqli_stmt_close($stmt);

$sql = "
    SELECT p.product_name, IFNULL(SUM(oi.quantity), 0) AS total_qty
    FROM nps_products p
    LEFT JOIN nps_order_items oi ON p.product_id = oi.product_id
    WHERE p.seller_id = ?
      AND p.publish_status <> 'hidden'
    GROUP BY p.product_id, p.product_name
    ORDER BY total_qty DESC, p.product_name ASC
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $topProductName = $row['product_name'] ?: '-';
    $topProductOrders = (int)$row['total_qty'];
}
mysqli_stmt_close($stmt);

$sql = "
    SELECT COUNT(*) AS low_stock_items
    FROM nps_products
    WHERE seller_id = ?
      AND publish_status <> 'hidden'
      AND stock_quantity <= 10
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $lowStockItems = $row['low_stock_items'];
}
mysqli_stmt_close($stmt);

/* =========================
   CHART DATA
========================= */
$chartLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$chartValues = array_fill(0, 12, 0);

$sql = "
    SELECT MONTH(o.order_date) AS month_num, IFNULL(SUM(oi.subtotal), 0) AS monthly_sales
    FROM nps_orders o
    INNER JOIN nps_order_items oi ON o.order_id = oi.order_id
    INNER JOIN nps_products p ON oi.product_id = p.product_id
    WHERE p.seller_id = ?
      AND YEAR(o.order_date) = YEAR(CURDATE())
      AND o.order_status IN ('confirmed', 'shipped', 'delivered')
    GROUP BY MONTH(o.order_date)
    ORDER BY MONTH(o.order_date)
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $monthIndex = ((int)$row['month_num']) - 1;
    if ($monthIndex >= 0 && $monthIndex < 12) {
        $chartValues[$monthIndex] = (float)$row['monthly_sales'];
    }
}
mysqli_stmt_close($stmt);

$maxChart = max($chartValues);
if ($maxChart <= 0) {
    $maxChart = 1;
}

/* =========================
   FILTER + PAGINATION
========================= */
$productFilter = trim($_GET['product_filter'] ?? 'all');
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$rowsPerPage = 5;
$offset = ($page - 1) * $rowsPerPage;

/* =========================
   SALES REPORT COUNT
========================= */
$countSql = "
    SELECT COUNT(*) AS total_count
    FROM nps_products p
    WHERE p.seller_id = ?
      AND p.publish_status <> 'hidden'
";

if ($productFilter === 'in_stock') {
    $countSql .= " AND p.stock_quantity > 10";
} elseif ($productFilter === 'low_stock') {
    $countSql .= " AND p.stock_quantity BETWEEN 1 AND 10";
} elseif ($productFilter === 'out_of_stock') {
    $countSql .= " AND p.stock_quantity = 0";
}

$stmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$countResult = mysqli_stmt_get_result($stmt);
$countRow = mysqli_fetch_assoc($countResult);
$totalRows = (int)($countRow['total_count'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $rowsPerPage));
mysqli_stmt_close($stmt);

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $rowsPerPage;
}

/* =========================
   SALES REPORT TABLE
========================= */
$salesRows = [];

$sql = "
    SELECT 
        p.product_id,
        p.product_name,
        IFNULL(SUM(oi.subtotal), 0) AS revenue,
        p.stock_quantity,
        pi.image_path
    FROM nps_products p
    LEFT JOIN nps_order_items oi ON p.product_id = oi.product_id
    LEFT JOIN nps_product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
    WHERE p.seller_id = ?
      AND p.publish_status <> 'hidden'
";

if ($productFilter === 'in_stock') {
    $sql .= " AND p.stock_quantity > 10";
} elseif ($productFilter === 'low_stock') {
    $sql .= " AND p.stock_quantity BETWEEN 1 AND 10";
} elseif ($productFilter === 'out_of_stock') {
    $sql .= " AND p.stock_quantity = 0";
}

$sql .= "
    GROUP BY p.product_id, p.product_name, p.stock_quantity, pi.image_path
    ORDER BY revenue DESC, p.product_name ASC
    LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iii", $sellerId, $rowsPerPage, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $salesRows[] = $row;
}
mysqli_stmt_close($stmt);

/* =========================
   REVIEWS LIST FROM DB
========================= */
$showAllReviews = isset($_GET['show_all']) && $_GET['show_all'] == '1';
$reviewLimit = $showAllReviews ? 100 : 3;
$reviews = [];

$sql = "
    SELECT 
        r.rating_id,
        r.rating_value,
        r.created_at,
        u.full_name,
        p.product_name
    FROM nps_ratings r
    INNER JOIN nps_users u ON r.user_id = u.user_id
    INNER JOIN nps_products p ON r.product_id = p.product_id
    ORDER BY r.created_at DESC
    LIMIT $reviewLimit
";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $reviews[] = $row;
    }
}

function renderStars($count) {
    $count = max(0, min(5, (int)$count));
    return str_repeat('★', $count) . str_repeat('☆', 5 - $count);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - NextPick</title>
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

        .topbar-user strong {
            font-size: 14px;
        }

        .topbar-user span {
            font-size: 12px;
            color: #666;
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

        .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .page-title-box h1 {
            font-size: 32px;
            margin-bottom: 6px;
            color: #111827;
        }

        .page-title-box p {
            color: #667085;
            font-size: 15px;
        }

        .date-filter {
            min-width: 255px;
        }

        .date-filter select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d9d9df;
            border-radius: 12px;
            font-size: 14px;
            background: #fff;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-radius: 16px;
            padding: 18px;
            min-height: 110px;
        }

        .stat-content h4 {
            font-size: 14px;
            color: #667085;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .stat-content h2 {
            font-size: 18px;
            color: #111827;
            margin-bottom: 6px;
        }

        .stat-content p {
            font-size: 13px;
            color: #667085;
        }

        .green-text { color: #16a34a; font-weight: 600; }
        .red-text { color: #ef4444; font-weight: 600; }

        .analytics-grid {
            display: grid;
            grid-template-columns: 1.25fr 1fr;
            gap: 18px;
            margin-top: 18px;
        }

        .left-side {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .right-side {
            display: flex;
            flex-direction: column;
        }

        .card {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-radius: 16px;
            padding: 20px;
        }

        .card-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }

        .card-select,
        .table-top select {
            padding: 10px 12px;
            border: 1px solid #d9d9df;
            border-radius: 10px;
            font-size: 14px;
            background: #fff;
        }

        .chart-area {
            height: 300px;
            border-bottom: 1px solid #eceff3;
            padding: 10px 10px 0;
        }

        .chart-grid {
            height: 100%;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            border-left: 1px solid #ececec;
            border-bottom: 1px solid #ececec;
            padding: 0 14px;
        }

        .chart-bar-group {
            width: 38px;
            min-width: 38px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            height: 100%;
        }

        .chart-bar {
            width: 10px;
            border-radius: 10px;
            background: #2156e6;
        }

        .chart-label {
            font-size: 12px;
            color: #667085;
            padding-bottom: 8px;
        }

        .review-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 18px;
            margin-bottom: 15px;
        }

        .review-header-top h3 {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }

        .more-btn {
            font-size: 13px;
            color: #2156e6;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid #bfd0ff;
            background: #fff;
            border-radius: 10px;
            padding: 8px 14px;
        }

        .review-list {
            display: flex;
            flex-direction: column;
        }

        .review-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #eee;
            gap: 18px;
        }

        .review-row:last-child {
            border-bottom: none;
        }

        .review-left strong {
            display: block;
            font-size: 14px;
            color: #111827;
            margin-bottom: 4px;
        }

        .review-left span {
            font-size: 12px;
            color: #888;
        }

        .review-right {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0;
        }

        .stars {
            color: #f97316;
            font-size: 18px;
            letter-spacing: 1px;
            white-space: nowrap;
        }

        .delete-icon {
            color: #ef4444;
            font-size: 18px;
            text-decoration: none;
        }

        .table-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .table-top-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .export-btn {
            background: #2156e6;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 11px 16px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
        }

        .report-title {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th {
            text-align: left;
            font-size: 13px;
            color: #555;
            background: #f3f4f6;
            padding: 12px 10px;
            font-weight: 600;
        }

        .report-table td {
            padding: 12px 10px;
            border-top: 1px solid #f0f0f0;
            font-size: 14px;
            vertical-align: middle;
        }

        .report-thumb {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #ececec;
            background: #fafafa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .in-stock {
            background: #e7faed;
            color: #1b8a46;
        }

        .low-stock {
            background: #fff0e4;
            color: #ea580c;
        }

        .out-stock {
            background: #ffe8e8;
            color: #b42318;
        }

        .pagination {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            align-items: center;
            margin-top: 18px;
            font-size: 14px;
            color: #667085;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            color: #667085;
        }

        .pagination .active-page {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #2156e6;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
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
            border: 1px solid #d0d5dd;
            border-radius: 10px;
            outline: none;
            margin-top: 8px;
        }

        .socials {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            font-size: 22px;
            color: #667085;
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

        @media (max-width: 1250px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .analytics-grid,
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

            .page-title-box h1 {
                font-size: 26px;
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
        <img src="/NextPickStore/assets/images/logos/logo.png" alt="NextPick Logo">

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
            <h2>Welcome,<br><?php echo htmlspecialchars($firstName); ?></h2>

            <ul class="menu">
                <li><a href="/NextPickStore/roles/seller/dashboard.php"><img src="../../assets/images/icons/seller icon/dashboard.png" alt="" class="menu-icon-img"><span>Dashboard</span></a></li>
                <li><a href="/NextPickStore/roles/seller/my_products.php"><img src="../../assets/images/icons/seller icon/inventory-management.png" alt="" class="menu-icon-img"><span>Inventory Management</span></a></li>
                <li><a href="/NextPickStore/roles/seller/add_product.php"><img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img"><span>Add Product</span></a></li>
                <li><a href="/NextPickStore/roles/buyer/my_orders.php"><img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img"><span>Orders</span></a></li>
                <li><a href="#"><img src="../../assets/images/icons/seller icon/client.png" alt="" class="menu-icon-img"><span>Customer Data</span></a></li>
                <li><a href="/NextPickStore/roles/seller/reports.php" class="active"><img src="../../assets/images/icons/seller icon/seo-report.png" alt="" class="menu-icon-img"><span>Analytics & Reports</span></a></li>
                <li><a href="#"><img src="../../assets/images/icons/seller icon/settings.png" alt="" class="menu-icon-img"><span>Settings</span></a></li>
                <li><a href="#"><img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img"><span>Help Center</span></a></li>
                <li><a href="/NextPickStore/auth/logout.php"><img src="../../assets/images/icons/seller icon/logout.png" alt="" class="menu-icon-img"><span>Log out</span></a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="page-title-row">
                <div class="page-title-box">
                    <h1>Analytics & Reports</h1>
                    <p>Track your store performance and make data-driven decisions.</p>
                </div>

                <div class="date-filter">
                    <select>
                        <option>May 16, 2024 - Jun 14, 2024</option>
                    </select>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-content">
                        <h4>Total Sales</h4>
                        <h2>$<?php echo number_format($totalSales, 2); ?></h2>
                        <p><span class="green-text">↑ 18.6%</span> vs last 30 days</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-content">
                        <h4>Total Orders</h4>
                        <h2><?php echo $totalOrders; ?></h2>
                        <p><span class="green-text">↑ 12.5%</span> vs last 30 days</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-content">
                        <h4>Top Product</h4>
                        <h2><?php echo htmlspecialchars($topProductName); ?></h2>
                        <p><?php echo $topProductOrders; ?> orders</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-content">
                        <h4>Low Stock Items</h4>
                        <h2><?php echo $lowStockItems; ?></h2>
                        <p><span class="red-text">↓ 8</span> vs last 30 days</p>
                    </div>
                </div>
            </div>

            <div class="analytics-grid">
                <div class="left-side">
                    <div class="card">
                        <div class="card-header-row">
                            <div class="card-title">Sales Overview</div>
                            <select class="card-select">
                                <option>2023</option>
                            </select>
                        </div>

                        <div class="chart-area">
                            <div class="chart-grid">
                                <?php foreach ($chartLabels as $index => $label) { 
                                    $height = ($chartValues[$index] / $maxChart) * 240;
                                    if ($height < 20 && $chartValues[$index] > 0) {
                                        $height = 20;
                                    }
                                ?>
                                    <div class="chart-bar-group">
                                        <div class="chart-bar" style="height: <?php echo $height; ?>px;"></div>
                                        <div class="chart-label"><?php echo $label; ?></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                     <div class="review-header-top">
    <h3>Customer Ratings</h3>
    <?php if ($showAllReviews) { ?>
        <a href="/NextPickStore/roles/seller/reports.php" class="more-btn">Less</a>
    <?php } else { ?>
        <a href="/NextPickStore/roles/seller/reports.php?show_all=1" class="more-btn">More ></a>
    <?php } ?>
</div>

<?php if (!empty($reviews)) { ?>
    <div class="review-list">
        <?php foreach ($reviews as $r) { ?>
            <div class="review-row">
                <div class="review-left">
                    <strong><?php echo htmlspecialchars($r['full_name']); ?></strong>
                    <span><?php echo date('M d, Y', strtotime($r['created_at'])); ?></span>
                </div>

                <div class="review-right">
                    <div class="stars"><?php echo renderStars($r['rating_value']); ?></div>
                </div>
            </div>
        <?php } ?>
    </div>
<?php } else { ?>
    <div style="padding-top: 6px; color:#666; font-size:14px;">No ratings available right now.</div>
<?php } ?>
                    </div>
                </div>

                <div class="right-side">
                    <div class="card">
                        <div class="table-top">
                            <div class="table-top-left">
                                <select onchange="location.href='/NextPickStore/roles/seller/reports.php?product_filter=' + this.value;">
                                    <option value="all" <?php echo ($productFilter === 'all') ? 'selected' : ''; ?>>All Products</option>
                                    <option value="in_stock" <?php echo ($productFilter === 'in_stock') ? 'selected' : ''; ?>>In Stock</option>
                                    <option value="low_stock" <?php echo ($productFilter === 'low_stock') ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out_of_stock" <?php echo ($productFilter === 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>

                            <a class="export-btn" href="/NextPickStore/roles/seller/reports.php?export=csv&product_filter=<?php echo urlencode($productFilter); ?>">Export Report</a>
                        </div>

                        <div class="report-title">Sales Report</div>

                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Product Name</th>
                                    <th>Revenue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($salesRows)) { ?>
                                    <?php foreach ($salesRows as $row) { ?>
                                        <?php
                                            $stockQty = (int)$row['stock_quantity'];
                                            if ($stockQty == 0) {
                                                $statusClass = 'out-stock';
                                                $statusText = 'Out of Stock';
                                            } elseif ($stockQty <= 10) {
                                                $statusClass = 'low-stock';
                                                $statusText = 'Low Stock';
                                            } else {
                                                $statusClass = 'in-stock';
                                                $statusText = 'In Stock';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <img
                                                    src="/NextPickStore/<?php echo !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'assets/images/products/default.png'; ?>"
                                                    alt="Product"
                                                    class="report-thumb"
                                                >
                                            </td>
                                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                            <td>$<?php echo number_format($row['revenue'], 2); ?></td>
                                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr>
                                        <td colspan="4">No report data available.</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>

                        <div class="pagination">
                            <?php if ($page > 1) { ?>
                                <a href="/NextPickStore/roles/seller/reports.php?product_filter=<?php echo urlencode($productFilter); ?>&page=<?php echo $page - 1; ?>">‹</a>
                            <?php } ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++) { ?>
                                <?php if ($i == $page) { ?>
                                    <span class="active-page"><?php echo $i; ?></span>
                                <?php } else { ?>
                                    <a href="/NextPickStore/roles/seller/reports.php?product_filter=<?php echo urlencode($productFilter); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php } ?>
                            <?php } ?>

                            <?php if ($page < $totalPages) { ?>
                                <a href="/NextPickStore/roles/seller/reports.php?product_filter=<?php echo urlencode($productFilter); ?>&page=<?php echo $page + 1; ?>">›</a>
                            <?php } ?>
                        </div>
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

        <!-- YOUR BOX (UNCHANGED STYLE, JUST FIXED POSITION) -->
        <div class="seller-footer-box">
            
            
            
            
           

            <div class="social-box">
                <span>Follow us</span>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>

    </div> <!-- ✅ THIS WAS MISSING -->

    <div class="footer-bottom">
        <div>© 2024 NEXTPICK. All Rights Reserved.</div>
        <div class="footer-links">
            <a href="#">Privacy policy</a>
            <a href="#">Cookie settings</a>
            <a href="#">Terms and conditions</a>
            <a href="#">Imprint</a>
        </div>
    </div>
</div>

</body>
</html>