<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';

requireRole(['Buyer']);

$conn = getConnection();

$userId = $_SESSION['user_id'] ?? 0;
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* =========================
   SEARCH HANDLING
========================= */
$searchTerm = '';

if (isset($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
}

/* =========================
   CATEGORIES FOR DROPDOWN
========================= */
$categoryResult = mysqli_query($conn, "
    SELECT 
        c.category_id, 
        c.category_name
    FROM nps_categories c
    ORDER BY c.category_name ASC
    LIMIT 8
");

$categoriess = [];

if ($categoryResult) {
    while ($r = mysqli_fetch_assoc($categoryResult)) {
        $categoriess[] = $r;
    }
}

/* =========================
   HELPER FUNCTIONS
========================= */
function tableColumnExists($conn, $tableName, $columnName)
{
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $columnName = mysqli_real_escape_string($conn, $columnName);

    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    return $result && mysqli_num_rows($result) > 0;
}

function pickColumn($conn, $tableName, $possibleColumns)
{
    foreach ($possibleColumns as $column) {
        if (tableColumnExists($conn, $tableName, $column)) {
            return $column;
        }
    }

    return null;
}

/* =========================
   DETECT ORDER ITEM PRICE COLUMN
========================= */
$priceColumn = pickColumn($conn, 'nps_order_items', [
    'price',
    'unit_price',
    'product_price'
]);

if (!$priceColumn) {
    $priceColumn = 'price';
}

/* =========================
   ORDER DETAILS
========================= */
$order = null;

$orderSql = "
    SELECT
        order_id,
        buyer_id,
        order_date,
        order_status,
        total_amount,
        shipping_address,
        payment_method
    FROM nps_orders
    WHERE order_id = ?
      AND buyer_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $orderSql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $orderId, $userId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
}

/* =========================
   ORDER ITEMS
========================= */
$orderItems = [];

if ($order) {
    $itemsSql = "
        SELECT
            oi.order_item_id,
            oi.product_id,
            oi.quantity,
            oi.`$priceColumn` AS item_price,
            p.product_name,
            p.short_description,
            p.brand,
            pi.image_path
        FROM nps_order_items oi
        INNER JOIN nps_products p
            ON oi.product_id = p.product_id
        LEFT JOIN nps_product_images pi
            ON p.product_id = pi.product_id AND pi.is_primary = 1
        WHERE oi.order_id = ?
        ORDER BY oi.order_item_id ASC
    ";

    $stmt = mysqli_prepare($conn, $itemsSql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $orderId);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $orderItems[] = $row;
        }

        mysqli_stmt_close($stmt);
    }
}

$subtotal = 0;

foreach ($orderItems as $item) {
    $subtotal += ((float)$item['item_price'] * (int)$item['quantity']);
}

$totalAmount = $order ? (float)$order['total_amount'] : 0;
$shipping = count($orderItems) > 0 ? 2.00 : 0.00;
$discount = max(0, ($subtotal + $shipping) - $totalAmount);

function statusClass($status)
{
    $status = strtolower((string)$status);

    if ($status === 'completed' || $status === 'delivered' || $status === 'confirmed') {
        return 'status-completed';
    }

    if ($status === 'cancelled' || $status === 'canceled') {
        return 'status-cancelled';
    }

    if ($status === 'processing' || $status === 'shipped') {
        return 'status-processing';
    }

    return 'status-pending';
}

$status = $order['order_status'] ?? 'pending';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - NextPick</title>

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        img {
            display: block;
            max-width: 100%;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f4f6;
            color: #222;
        }

        .page-wrapper {
            max-width: calc(100% - 50px);
            margin: 25px auto;
            background: #fff;
            border: 1px solid #e8e8e8;
            min-height: 90vh;
            overflow: hidden;
        }

        .buyer-header {
            padding: 28px;
            background: #ffffff;
            border-bottom: 1px solid #ececec;
        }

        .buyer-nav {
            width: 100%;
            min-height: 74px;
            background: #ffffff;
            border-radius: 16px;
            padding: 0 32px;
            display: grid;
            grid-template-columns: 230px 210px minmax(300px, 420px) auto;
            align-items: center;
            gap: 28px;
            box-shadow: 0 10px 28px rgba(17, 24, 39, 0.06);
        }

        .nav-logo {
            display: flex;
            align-items: center;
        }

        .nav-logo img {
            width: 150px;
            height: auto;
            object-fit: contain;
        }

        .all-products-btn {
            height: 46px;
            padding: 0 22px;
            border-radius: 10px;
            background: #ffffff;
            color: #222;
            border: 1px solid #f0f0f0;
            box-shadow: 0 6px 18px rgba(17, 24, 39, 0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
            cursor: pointer;
        }

        .all-products-btn:hover {
            background: #f8f9ff;
            color: #3158ff;
        }

        .products-dropdown {
            position: relative;
        }

        .products-dropdown .all-products-btn {
            width: 100%;
        }

        .dropdown-menu {
            position: absolute;
            top: 54px;
            left: 0;
            width: 230px;
            background: #ffffff;
            border: 1px solid #ececec;
            border-radius: 14px;
            box-shadow: 0 18px 35px rgba(17, 24, 39, 0.12);
            padding: 10px;
            display: none;
            z-index: 100;
        }

        .products-dropdown:hover .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: 11px 12px;
            border-radius: 10px;
            color: #333;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .dropdown-menu a:hover {
            background: #eef3ff;
            color: #3158ff;
        }

        .dropdown-divider {
            height: 1px;
            background: #eeeeee;
            margin: 8px 0;
        }

        .buyer-search {
            height: 42px;
            width: 100%;
            background: #ffffff;
            border: 1px solid #dbe4ff;
            border-radius: 10px;
            padding: 0 8px 0 18px;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .buyer-search input {
            flex: 1;
            height: 100%;
            border: none;
            outline: none;
            background: transparent;
            color: #111827;
            font-size: 13px;
            padding: 0 10px;
        }

        .buyer-search input::placeholder {
            color: #7b88a8;
        }

        .buyer-search button {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 50%;
            background: transparent;
            color: #3158ff;
            cursor: pointer;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .buyer-search button:hover {
            background: #eef3ff;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            background: #f7f8fc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #111827;
            transition: 0.2s ease;
        }

        .icon-btn:hover,
        .icon-btn.active {
            background: #eef3ff;
            color: #3158ff;
        }

        .logout-btn {
            background: #1A4DE1;
            color: #ffffff;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            transition: 0.2s ease;
        }

        .logout-btn:hover {
            background: #123dcc;
        }

        .content {
            padding: 28px;
            background: #fcfcfc;
        }

        .page-hero {
            background: linear-gradient(135deg, #eef3ff, #ffffff);
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 26px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
        }

        .page-hero h1 {
            font-size: 32px;
            color: #111827;
            margin-bottom: 8px;
        }

        .page-hero p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .hero-actions {
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
            font-weight: 700;
            transition: 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: #1A4DE1;
            color: #fff;
        }

        .btn-primary:hover {
            background: #123dcc;
        }

        .btn-light {
            background: #f1f1f5;
            color: #222;
        }

        .btn-light:hover {
            background: #e5e5ec;
        }

        .details-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
            align-items: start;
        }

        .details-card,
        .summary-card,
        .info-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 22px;
        }

        .card-title {
            font-size: 20px;
            color: #111827;
            margin-bottom: 18px;
            font-weight: 800;
        }

        .order-header-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }

        .mini-stat {
            background: #fbfbfd;
            border: 1px solid #eeeeee;
            border-radius: 14px;
            padding: 14px;
        }

        .mini-stat span {
            display: block;
            color: #777;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .mini-stat strong {
            color: #111827;
            font-size: 14px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-pending {
            background: #fff7df;
            color: #a16207;
        }

        .status-processing {
            background: #eef3ff;
            color: #3158ff;
        }

        .status-completed {
            background: #e7faed;
            color: #1b8a46;
        }

        .status-cancelled {
            background: #fff1f1;
            color: #d82121;
        }

        .order-item {
            display: grid;
            grid-template-columns: 90px 1fr auto;
            gap: 16px;
            align-items: center;
            padding: 18px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-img {
            width: 90px;
            height: 78px;
            border-radius: 14px;
            border: 1px solid #eeeeee;
            background: #f8f8fb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 9px;
            overflow: hidden;
        }

        .item-img img {
            max-width: 100%;
            max-height: 64px;
            object-fit: contain;
        }

        .item-info h3 {
            font-size: 15px;
            color: #111827;
            margin-bottom: 6px;
            line-height: 1.35;
        }

        .item-info p {
            font-size: 13px;
            color: #777;
            line-height: 1.5;
        }

        .brand-chip {
            display: inline-flex;
            margin-top: 8px;
            background: #eef3ff;
            color: #3158ff;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 800;
        }

        .item-price {
            text-align: right;
            white-space: nowrap;
        }

        .item-price strong {
            display: block;
            color: #3158ff;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .item-price span {
            display: block;
            color: #777;
            font-size: 12px;
        }

        .summary-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #555;
        }

        .summary-line strong {
            color: #111827;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 0 8px;
            font-size: 20px;
            font-weight: 800;
            color: #111827;
        }

        .summary-total span:last-child {
            color: #3158ff;
        }

        .info-grid {
            display: grid;
            gap: 14px;
        }

        .info-row {
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 12px;
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-row span {
            display: block;
            color: #777;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .info-row strong,
        .info-row p {
            color: #111827;
            font-size: 14px;
            line-height: 1.6;
        }

        .side-stack {
            display: grid;
            gap: 24px;
        }

        .not-found {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 55px 30px;
            text-align: center;
        }

        .empty-icon {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #eef3ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        .not-found h2 {
            font-size: 24px;
            color: #111827;
            margin-bottom: 10px;
        }

        .not-found p {
            font-size: 14px;
            color: #666;
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .notice-box {
            background: #fff;
            border: 1px dashed #dbe4ff;
            border-radius: 14px;
            padding: 14px;
            margin-top: 18px;
            font-size: 13px;
            line-height: 1.6;
            color: #666;
        }

        .footer {
            border-top: 1px solid #ececec;
            background: #fff;
        }

        .footer-top {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            padding: 28px;
        }

        .footer h4 {
            font-size: 14px;
            margin-bottom: 12px;
            color: #222;
            font-weight: 700;
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

        .footer a:hover {
            color: #3158ff;
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
            .buyer-nav {
                grid-template-columns: 1fr;
                padding: 20px;
                gap: 16px;
            }

            .nav-logo,
            .products-dropdown,
            .buyer-search,
            .nav-actions {
                width: 100%;
            }

            .nav-actions {
                justify-content: center;
            }

            .details-layout {
                grid-template-columns: 1fr;
            }

            .order-header-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-top {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .page-wrapper {
                max-width: 100%;
                margin: 0;
            }

            .buyer-header,
            .content {
                padding: 18px;
            }

            .page-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-header-row,
            .order-item {
                grid-template-columns: 1fr;
            }

            .item-img {
                width: 100%;
                height: 160px;
            }

            .item-price {
                text-align: left;
            }

            .footer-top {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <!-- HEADER -->
    <header class="buyer-header">
        <div class="buyer-nav">

            <a href="dashboard.php" class="nav-logo">
                <img src="../../assets/images/Logos/nextpickstore-logo.png" alt="NextPick Logo">
            </a>

            <div class="products-dropdown">
                <button type="button" class="all-products-btn">
                    ☰ All Products ▾
                </button>

                <div class="dropdown-menu">
                    <a href="ViewAllProducts.php?type=allproducts">All Products</a>
                    <a href="ViewAllProducts.php?type=latest">Latest Arrivals</a>
                    <a href="ViewAllProducts.php?type=bestSeller">Best Sellers</a>

                    <?php if (!empty($categoriess)): ?>
                        <div class="dropdown-divider"></div>

                        <?php foreach ($categoriess as $cat): ?>
                            <a href="productCategory.php?id=<?php echo $cat['category_id']; ?>&name=<?php echo urlencode($cat['category_name']); ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <form class="buyer-search" method="GET" action="dashboard.php">
                <input 
                    type="text" 
                    name="q"
                    placeholder="I am Searching for..."
                    value="<?php echo htmlspecialchars($searchTerm); ?>"
                >
                <button type="submit" aria-label="Search">⌕</button>
            </form>

            <div class="nav-actions">
                <a href="cart.php" class="icon-btn" title="Cart">🛒</a>
                <a href="orders.php" class="icon-btn active" title="Orders">🧾</a>
                <a href="../../auth/logout.php" class="logout-btn">Logout</a>
            </div>

        </div>
    </header>

    <main class="content">

        <section class="page-hero">
            <div>
                <h1>Order Details</h1>
                <p>Review order items, delivery information, payment method, and status.</p>
            </div>

            <div class="hero-actions">
                <a href="orders.php">
                    <button class="btn btn-light">Back to Orders</button>
                </a>

                <a href="ViewAllProducts.php?type=allproducts">
                    <button class="btn btn-primary">Continue Shopping</button>
                </a>
            </div>
        </section>

        <?php if ($order): ?>

            <section class="details-layout">

                <div class="details-card">
                    <h2 class="card-title">Order #<?php echo (int)$order['order_id']; ?></h2>

                    <div class="order-header-row">
                        <div class="mini-stat">
                            <span>Order Date</span>
                            <strong><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></strong>
                        </div>

                        <div class="mini-stat">
                            <span>Status</span>
                            <strong class="status-badge <?php echo statusClass($status); ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </strong>
                        </div>

                        <div class="mini-stat">
                            <span>Payment</span>
                            <strong><?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></strong>
                        </div>

                        <div class="mini-stat">
                            <span>Products</span>
                            <strong><?php echo count($orderItems); ?> item<?php echo count($orderItems) === 1 ? '' : 's'; ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($orderItems)): ?>
                        <?php foreach ($orderItems as $item): ?>
                            <?php
                                $lineTotal = (float)$item['item_price'] * (int)$item['quantity'];

                                $imagePath = !empty($item['image_path'])
                                    ? '../../' . htmlspecialchars($item['image_path'])
                                    : '../../assets/images/products/view.png';
                            ?>

                            <div class="order-item">
                                <a href="productDetails.php?id=<?php echo (int)$item['product_id']; ?>" class="item-img">
                                    <img src="<?php echo $imagePath; ?>" alt="Product image">
                                </a>

                                <div class="item-info">
                                    <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>

                                    <p>
                                        <?php echo htmlspecialchars($item['short_description'] ?? ''); ?>
                                    </p>

                                    <?php if (!empty($item['brand'])): ?>
                                        <span class="brand-chip">
                                            <?php echo htmlspecialchars($item['brand']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="item-price">
                                    <strong>€<?php echo number_format($lineTotal, 2); ?></strong>
                                    <span>
                                        Qty <?php echo (int)$item['quantity']; ?> × €<?php echo number_format((float)$item['item_price'], 2); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size:14px; color:#666;">No items found for this order.</p>
                    <?php endif; ?>
                </div>

                <div class="side-stack">

                    <aside class="summary-card">
                        <h2 class="card-title">Payment Summary</h2>

                        <div class="summary-line">
                            <span>Subtotal</span>
                            <strong>€<?php echo number_format($subtotal, 2); ?></strong>
                        </div>

                        <div class="summary-line">
                            <span>Shipping</span>
                            <strong>€<?php echo number_format($shipping, 2); ?></strong>
                        </div>

                        <div class="summary-line">
                            <span>Discount</span>
                            <strong>-€<?php echo number_format($discount, 2); ?></strong>
                        </div>

                        <div class="summary-total">
                            <span>Total</span>
                            <span>€<?php echo number_format($totalAmount, 2); ?></span>
                        </div>

                        <div class="notice-box">
                            This is the final amount saved with your order.
                        </div>
                    </aside>

                    <aside class="info-card">
                        <h2 class="card-title">Delivery Details</h2>

                        <div class="info-grid">
                            <div class="info-row">
                                <span>Buyer ID</span>
                                <strong><?php echo (int)$order['buyer_id']; ?></strong>
                            </div>

                            <div class="info-row">
                                <span>Shipping Address</span>
                                <p><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'N/A')); ?></p>
                            </div>

                            <div class="info-row">
                                <span>Payment Method</span>
                                <strong><?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></strong>
                            </div>
                        </div>
                    </aside>

                </div>

            </section>

        <?php else: ?>

            <section class="not-found">
                <div class="empty-icon">🧾</div>
                <h2>Order not found</h2>
                <p>
                    This order does not exist, or it does not belong to your account.
                </p>

                <a href="orders.php">
                    <button class="btn btn-primary">Back to Orders</button>
                </a>
            </section>

        <?php endif; ?>

    </main>

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
                <h4>Help &amp; Support</h4>
                <ul>
                    <li><a href="#">Help center</a></li>
                    <li><a href="#">Payments</a></li>
                    <li><a href="#">Product returns</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div>&copy; 2026 NEXTPICK. All Rights Reserved.</div>

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
