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

$message = '';

/* =========================
   CART ACTIONS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* UPDATE QUANTITY WITH STOCK CHECK */
    if (isset($_POST['update_quantity'])) {
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if ($productId > 0 && isset($_SESSION['cart'][$productId])) {

            $stmt = mysqli_prepare($conn, "
                SELECT stock_quantity
                FROM nps_products
                WHERE product_id = ?
                  AND publish_status = 'published'
                LIMIT 1
            ");

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $productId);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $product = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if ($product) {
                    $stockQuantity = (int)$product['stock_quantity'];

                    if ($stockQuantity <= 0) {
                        unset($_SESSION['cart'][$productId]);
                        $message = 'This product is out of stock and was removed from your cart.';
                    } else {
                        if ($quantity > $stockQuantity) {
                            $quantity = $stockQuantity;
                            $message = 'Quantity adjusted to available stock.';
                        } else {
                            $message = 'Cart quantity updated successfully.';
                        }

                        $_SESSION['cart'][$productId] = $quantity;
                    }
                } else {
                    unset($_SESSION['cart'][$productId]);
                    $message = 'This product is no longer available and was removed from your cart.';
                }
            } else {
                $message = 'Could not update cart quantity. Please try again.';
            }
        }
    }

    /* REMOVE ITEM */
    if (isset($_POST['remove_item'])) {
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

        if ($productId > 0 && isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
            $message = 'Item removed from cart.';
        }
    }

    /* CLEAR CART */
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        $message = 'Cart cleared successfully.';
    }
}

/* =========================
   LOAD CART ITEMS FROM SESSION
========================= */
$cartItems = [];

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $productId = (int)$productId;
        $quantity = (int)$quantity;

        if ($productId <= 0 || $quantity <= 0) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        $stmt = mysqli_prepare($conn, "
            SELECT
                p.product_id,
                p.product_Name,
                p.short_description,
                p.price,
                p.stock_quantity,
                pi.image_path
            FROM nps_products p
            LEFT JOIN nps_product_images pi
                ON p.product_id = pi.product_id 
                AND pi.is_primary = 1
            WHERE p.product_id = ?
              AND p.publish_status = 'published'
            LIMIT 1
        ");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $productId);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $product = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if ($product) {
                $stockQuantity = (int)$product['stock_quantity'];

                if ($stockQuantity <= 0) {
                    unset($_SESSION['cart'][$productId]);
                    continue;
                }

                if ($quantity > $stockQuantity) {
                    $quantity = $stockQuantity;
                    $_SESSION['cart'][$productId] = $quantity;
                }

                $product['quantity'] = $quantity;
                $cartItems[] = $product;
            } else {
                unset($_SESSION['cart'][$productId]);
            }
        }
    }
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - NextPick</title>

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
            image-rendering: auto;
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

        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 24px;
            align-items: start;
        }

        .cart-card,
        .summary-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 20px;
        }

        .card-title {
            font-size: 20px;
            color: #111827;
            margin-bottom: 18px;
            font-weight: 800;
        }

        .cart-message {
            background: #eef3ff;
            color: #3158ff;
            border: 1px solid #dbe4ff;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr 150px 120px;
            gap: 18px;
            align-items: center;
            padding: 18px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-img {
            width: 120px;
            height: 100px;
            background: #f8f8fb;
            border-radius: 14px;
            border: 1px solid #eeeeee;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 10px;
        }

        .item-img img {
            max-width: 100%;
            max-height: 85px;
            object-fit: contain;
        }

        .item-info h3 {
            font-size: 15px;
            color: #111827;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .item-info p {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
            max-width: 520px;
        }

        .stock {
            display: inline-flex;
            margin-top: 8px;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 999px;
        }

        .in-stock-label {
            background: #e7faed;
            color: #1b8a46;
        }

        .out-stock-label {
            background: #ffe8e8;
            color: #d82121;
        }

        .qty-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .qty-input {
            width: 68px;
            height: 38px;
            border: 1px solid #d7d7d7;
            border-radius: 10px;
            outline: none;
            text-align: center;
            font-size: 14px;
        }

        .small-btn {
            height: 38px;
            border: none;
            border-radius: 10px;
            padding: 0 12px;
            background: #eef3ff;
            color: #3158ff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .small-btn:hover {
            background: #dfe8ff;
        }

        .item-price {
            text-align: right;
        }

        .item-price strong {
            display: block;
            color: #3158ff;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .remove-btn {
            border: none;
            background: transparent;
            color: #d82121;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .remove-btn:hover {
            text-decoration: underline;
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

        .checkout-btn-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-top: 18px;
            height: 48px;
            border-radius: 12px;
            background: #1A4DE1;
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
        }

        .checkout-btn-link:hover {
            background: #123dcc;
        }

        .clear-cart-form {
            margin-top: 12px;
        }

        .clear-cart-btn {
            width: 100%;
            height: 42px;
            border: none;
            border-radius: 12px;
            background: #fff1f1;
            color: #d82121;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
        }

        .clear-cart-btn:hover {
            background: #ffe4e4;
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

            .cart-layout {
                grid-template-columns: 1fr;
            }

            .cart-item {
                grid-template-columns: 100px 1fr;
            }

            .item-price {
                text-align: left;
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

            .cart-item {
                grid-template-columns: 1fr;
            }

            .item-img {
                width: 100%;
                height: 160px;
            }

            .qty-form {
                flex-wrap: wrap;
            }

            .footer-top {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

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
                <a href="cart.php" class="icon-btn active" title="Cart">🛒</a>
                <a href="orders.php" class="icon-btn" title="Orders">🧾</a>
                <a href="../../auth/logout.php" class="logout-btn">Logout</a>
            </div>

        </div>
    </header>

    <main class="content">

        <section class="page-hero">
            <div>
                <h1>Shopping Cart</h1>
                <p>Review your selected products, update quantities, or continue shopping.</p>
            </div>

            <div class="hero-actions">
                <a href="dashboard.php">
                    <button class="btn btn-light" type="button">Back to Dashboard</button>
                </a>

                <a href="ViewAllProducts.php?type=allproducts">
                    <button class="btn btn-primary" type="button">Continue Shopping</button>
                </a>
            </div>
        </section>

        <?php if ($message !== ''): ?>
            <div class="cart-message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($cartItems)): ?>

            <section class="cart-layout">

                <div class="cart-card">
                    <h2 class="card-title">Cart Items</h2>

                    <?php foreach ($cartItems as $item): ?>
                        <?php
                            $itemTotal = (float)$item['price'] * (int)$item['quantity'];
                            $imagePath = !empty($item['image_path'])
                                ? '../../' . htmlspecialchars($item['image_path'])
                                : '../../assets/images/products/default.png';

                            $isItemInStock = (int)$item['stock_quantity'] > 0;
                        ?>

                        <div class="cart-item">
                            <a href="productDetails.php?id=<?php echo (int)$item['product_id']; ?>" class="item-img">
                                <img src="<?php echo $imagePath; ?>" alt="Product image">
                            </a>

                            <div class="item-info">
                                <h3><?php echo htmlspecialchars($item['product_Name']); ?></h3>
                                <p><?php echo htmlspecialchars($item['short_description']); ?></p>

                                <span class="stock <?php echo $isItemInStock ? 'in-stock-label' : 'out-stock-label'; ?>">
                                    <?php echo $isItemInStock ? 'In Stock' : 'Out of Stock'; ?>
                                </span>
                            </div>

                            <form method="POST" class="qty-form">
                                <input 
                                    type="hidden" 
                                    name="product_id" 
                                    value="<?php echo (int)$item['product_id']; ?>"
                                >

                                <input 
                                    class="qty-input" 
                                    type="number" 
                                    name="quantity" 
                                    min="1" 
                                    max="<?php echo max(1, (int)$item['stock_quantity']); ?>"
                                    value="<?php echo (int)$item['quantity']; ?>"
                                >

                                <button class="small-btn" type="submit" name="update_quantity">
                                    Update
                                </button>
                            </form>

                            <div class="item-price">
                                <strong>€<?php echo number_format($itemTotal, 2); ?></strong>

                                <form method="POST">
                                    <input 
                                        type="hidden" 
                                        name="product_id" 
                                        value="<?php echo (int)$item['product_id']; ?>"
                                    >

                                    <button class="remove-btn" type="submit" name="remove_item">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <aside class="summary-card">
                    <h2 class="card-title">Order Summary</h2>

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

                    <a href="checkout.php" class="checkout-btn-link">
                        Proceed to Checkout
                    </a>

                    <form method="POST" class="clear-cart-form">
                        <button class="clear-cart-btn" type="submit" name="clear_cart">
                            Clear Cart
                        </button>
                    </form>

                    <div class="notice-box">
                        Orders can be reviewed before checkout. Product availability is based on current stock.
                    </div>
                </aside>

            </section>

        <?php else: ?>

            <section class="empty-cart">
                <div class="empty-icon">🛒</div>

                <h2>Your cart is empty</h2>

                <p>
                    You have not added any products yet. Browse NextPick Store and choose your favorite electronics.
                </p>

                <a href="ViewAllProducts.php?type=allproducts">
                    <button class="btn btn-primary">Start Shopping</button>
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