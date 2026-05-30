<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';

requireRole(['Buyer']);

$conn = getConnection();

$userId = $_SESSION['user_id'] ?? 0;

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
   SESSION CART SETUP
========================= */
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/* =========================
   HELPER: CHECK COLUMN EXISTS
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
   GET CART ITEMS FROM SESSION
========================= */
function getCartItems($conn)
{
    $items = [];

    if (empty($_SESSION['cart'])) {
        return $items;
    }

    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $productId = (int)$productId;
        $quantity = (int)$quantity;

        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }

        $sql = "
            SELECT
                p.product_id,
                p.product_Name,
                p.short_description,
                p.price,
                p.stock_quantity,
                pi.image_path
            FROM nps_products p
            LEFT JOIN nps_product_images pi
                ON p.product_id = pi.product_id AND pi.is_primary = 1
            WHERE p.product_id = ?
              AND p.publish_status = 'published'
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $productId);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $product = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if ($product) {
                $stockQuantity = (int)$product['stock_quantity'];

                if ($stockQuantity <= 0) {
                    $product['quantity'] = 0;
                } else {
                    if ($quantity > $stockQuantity) {
                        $quantity = $stockQuantity;
                        $_SESSION['cart'][$productId] = $quantity;
                    }

                    $product['quantity'] = $quantity;
                }

                $items[] = $product;
            } else {
                unset($_SESSION['cart'][$productId]);
            }
        }
    }

    return $items;
}

$cartItems = getCartItems($conn);

/* =========================
   TOTALS
========================= */
$subtotal = 0;

foreach ($cartItems as $item) {
    $subtotal += ((float)$item['price'] * (int)$item['quantity']);
}

$shipping = count($cartItems) > 0 ? 2.00 : 0.00;
$discount = $subtotal >= 100 ? 10.00 : 0.00;
$total = max(0, $subtotal + $shipping - $discount);

$errorMessage = '';
$successMessage = '';
$orderId = null;

/* =========================
   PLACE ORDER
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'Cash on Delivery');

    if (empty($cartItems)) {
        $errorMessage = 'Your cart is empty. Please add products before placing an order.';
    } elseif ($fullName === '' || $phone === '' || $address === '') {
        $errorMessage = 'Please fill in your full name, phone number, and delivery address.';
    } else {

        $stockOk = true;

        foreach ($cartItems as $item) {
            if ((int)$item['quantity'] <= 0 || (int)$item['quantity'] > (int)$item['stock_quantity']) {
                $stockOk = false;
                $errorMessage = 'One or more products do not have enough stock.';
                break;
            }
        }

        if ($stockOk) {
            mysqli_begin_transaction($conn);

            try {
                /* =========================
                   INSERT ORDER
                   Your real nps_orders columns:
                   order_id, buyer_id, order_date, order_status,
                   total_amount, shipping_address, payment_method
                ========================= */
                $orderSql = "
                    INSERT INTO nps_orders
                    (buyer_id, order_date, order_status, total_amount, shipping_address, payment_method)
                    VALUES (?, NOW(), 'pending', ?, ?, ?)
                ";

                $stmt = mysqli_prepare($conn, $orderSql);

                if (!$stmt) {
                    throw new Exception(mysqli_error($conn));
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "idss",
                    $userId,
                    $total,
                    $address,
                    $paymentMethod
                );

                mysqli_stmt_execute($stmt);
                $orderId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                /* =========================
                   INSERT ORDER ITEMS
                   This detects whether your table uses:
                   price / unit_price / product_price
                ========================= */
                $priceColumn = pickColumn($conn, 'nps_order_items', [
                    'price',
                    'unit_price',
                    'product_price'
                ]);

                if (!$priceColumn) {
                    throw new Exception('No price column found in nps_order_items.');
                }

                $stockSql = "
                    UPDATE nps_products
                    SET stock_quantity = stock_quantity - ?
                    WHERE product_id = ?
                      AND stock_quantity >= ?
                ";

                foreach ($cartItems as $item) {
                    $productId = (int)$item['product_id'];
                    $quantity = (int)$item['quantity'];
                    $price = (float)$item['price'];

                    $itemSql = "
                        INSERT INTO nps_order_items
                        (order_id, product_id, quantity, `$priceColumn`)
                        VALUES (?, ?, ?, ?)
                    ";

                    $stmt = mysqli_prepare($conn, $itemSql);

                    if (!$stmt) {
                        throw new Exception(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($stmt, "iiid", $orderId, $productId, $quantity, $price);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    $stmt = mysqli_prepare($conn, $stockSql);

                    if (!$stmt) {
                        throw new Exception(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($stmt, "iii", $quantity, $productId, $quantity);
                    mysqli_stmt_execute($stmt);

                    if (mysqli_stmt_affected_rows($stmt) <= 0) {
                        throw new Exception('Stock update failed for one product.');
                    }

                    mysqli_stmt_close($stmt);
                }

                /* Clear session cart after successful order */
                $_SESSION['cart'] = [];

                mysqli_commit($conn);

                $successMessage = 'Order placed successfully. Your order number is #' . $orderId . '.';

                $cartItems = [];
                $subtotal = 0;
                $shipping = 0;
                $discount = 0;
                $total = 0;

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errorMessage = 'Order could not be placed. ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - NextPick</title>

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

        .btn-light {
            background: #f1f1f5;
            color: #222;
        }

        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
            align-items: start;
        }

        .checkout-card,
        .summary-card {
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

        .alert {
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .alert-error {
            background: #fff1f1;
            color: #d82121;
            border: 1px solid #ffd1d1;
        }

        .alert-success {
            background: #e7faed;
            color: #1b8a46;
            border: 1px solid #c9f0d6;
        }

        .form-grid {
            display: grid;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 800;
            color: #111827;
        }

        .form-control {
            width: 100%;
            min-height: 46px;
            border: 1px solid #d7d7d7;
            border-radius: 12px;
            outline: none;
            padding: 12px 14px;
            font-size: 14px;
            background: #fff;
            color: #111827;
        }

        textarea.form-control {
            min-height: 110px;
            resize: vertical;
        }

        .form-control:focus {
            border-color: #3158ff;
            box-shadow: 0 0 0 4px #eef3ff;
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .payment-option {
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 14px;
            background: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .payment-option input {
            accent-color: #1A4DE1;
        }

        .payment-option span {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }

        .order-item {
            display: grid;
            grid-template-columns: 72px 1fr auto;
            gap: 12px;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-img {
            width: 72px;
            height: 62px;
            border-radius: 12px;
            border: 1px solid #eeeeee;
            background: #f8f8fb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            overflow: hidden;
        }

        .order-img img {
            max-width: 100%;
            max-height: 52px;
            object-fit: contain;
        }

        .order-info h3 {
            font-size: 13px;
            color: #111827;
            margin-bottom: 5px;
            line-height: 1.35;
        }

        .order-info p {
            font-size: 12px;
            color: #777;
        }

        .order-price {
            font-size: 13px;
            color: #3158ff;
            font-weight: 800;
            white-space: nowrap;
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

        .place-order-btn {
            width: 100%;
            margin-top: 18px;
            height: 50px;
            border: none;
            border-radius: 12px;
            background: #1A4DE1;
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
        }

        .place-order-btn:hover {
            background: #123dcc;
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

        .empty-cart {
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

        .empty-cart h2 {
            font-size: 24px;
            color: #111827;
            margin-bottom: 10px;
        }

        .empty-cart p {
            font-size: 14px;
            color: #666;
            margin-bottom: 22px;
            line-height: 1.6;
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

            .checkout-layout {
                grid-template-columns: 1fr;
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

            .payment-options {
                grid-template-columns: 1fr;
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
                <a href="orders.php" class="icon-btn" title="Orders">🧾</a>
                <a href="profile/" class="icon-btn" title="Profile">♡</a>
                <a href="../../auth/logout.php" class="logout-btn">Logout</a>
            </div>

        </div>
    </header>

    <main class="content">

        <!-- PAGE HERO -->
        <section class="page-hero">
            <div>
                <h1>Checkout</h1>
                <p>Confirm your order details and place your order securely.</p>
            </div>

            <div class="hero-actions">
                <a href="cart.php">
                    <button class="btn btn-light" type="button">Back to Cart</button>
                </a>

                <a href="ViewAllProducts.php?type=allproducts">
                    <button class="btn btn-primary" type="button">Continue Shopping</button>
                </a>
            </div>
        </section>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>

            <section class="empty-cart">
                <div class="empty-icon">✅</div>
                <h2>Thank you for your order</h2>
                <p>Your order has been saved successfully. You can continue shopping or view your orders.</p>

                <a href="orders.php">
                    <button class="btn btn-primary">View My Orders</button>
                </a>
            </section>

        <?php elseif (!empty($cartItems)): ?>

            <form method="POST">
                <section class="checkout-layout">

                    <!-- DELIVERY FORM -->
                    <div class="checkout-card">
                        <h2 class="card-title">Delivery Details</h2>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input 
                                    class="form-control"
                                    type="text" 
                                    id="full_name" 
                                    name="full_name"
                                    placeholder="Enter your full name"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input 
                                    class="form-control"
                                    type="text" 
                                    id="phone" 
                                    name="phone"
                                    placeholder="Enter your phone number"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="address">Delivery Address</label>
                                <textarea 
                                    class="form-control"
                                    id="address" 
                                    name="address"
                                    placeholder="Enter your delivery address"
                                    required
                                ></textarea>
                            </div>

                            <div class="form-group">
                                <label>Payment Method</label>

                                <div class="payment-options">
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="Cash on Delivery" checked>
                                        <span>Cash on Delivery</span>
                                    </label>

                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="Credit Card">
                                        <span>Credit Card</span>
                                    </label>

                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="BenefitPay">
                                        <span>BenefitPay</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ORDER SUMMARY -->
                    <aside class="summary-card">
                        <h2 class="card-title">Order Summary</h2>

                        <?php foreach ($cartItems as $item): ?>
                            <?php
                                $itemTotal = (float)$item['price'] * (int)$item['quantity'];
                                $imagePath = !empty($item['image_path'])
                                    ? '../../' . htmlspecialchars($item['image_path'])
                                    : '../../assets/images/products/default.png';
                            ?>

                            <div class="order-item">
                                <div class="order-img">
                                    <img src="<?php echo $imagePath; ?>" alt="Product image">
                                </div>

                                <div class="order-info">
                                    <h3><?php echo htmlspecialchars($item['product_Name']); ?></h3>
                                    <p>Qty: <?php echo (int)$item['quantity']; ?></p>
                                </div>

                                <div class="order-price">
                                    €<?php echo number_format($itemTotal, 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

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
                            <span>€<?php echo number_format($total, 2); ?></span>
                        </div>

                        <button class="place-order-btn" type="submit" name="place_order">
                            Place Order
                        </button>

                        <div class="notice-box">
                            Please review your products before placing the order. Once confirmed, the cart will be cleared.
                        </div>
                    </aside>

                </section>
            </form>

        <?php else: ?>

            <section class="empty-cart">
                <div class="empty-icon">🛒</div>
                <h2>Your cart is empty</h2>
                <p>You need to add products to your cart before checkout.</p>

                <a href="ViewAllProducts.php?type=allproducts">
                    <button class="btn btn-primary">Start Shopping</button>
                </a>
            </section>

        <?php endif; ?>

    </main>

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