<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';

requireRole(['Buyer']);

$conn = getConnection();

$userId = $_SESSION['user_id'] ?? 0;

function productImagePath($path)
{
    if (!empty($path)) {
        return "../../" . htmlspecialchars($path);
    }

    return "../../assets/images/products/view.png";
}

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
                p.product_name,
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
    $house = trim($_POST['house'] ?? '');
    $road = trim($_POST['road'] ?? '');
    $block = trim($_POST['block'] ?? '');
    $area = trim($_POST['area'] ?? '');

    $paymentMethod = trim($_POST['payment_method'] ?? 'Cash on Delivery');
    $paymentType = trim($_POST['payment_type'] ?? 'Visa');

    // Fake payment fields for demo only. They are validated but not saved.
    $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $expiryDate = trim($_POST['expiry_date'] ?? '');
    $securityCode = trim($_POST['security_code'] ?? '');
    $cardName = trim($_POST['card_name'] ?? '');
    $walletPhone = preg_replace('/\D/', '', $_POST['wallet_phone'] ?? '');
    $googleEmail = trim($_POST['google_email'] ?? '');

    $allowedPaymentMethods = ['Cash on Delivery', 'Online Payment'];
    $allowedPaymentTypes = ['Visa', 'Mastercard', 'Google Pay', 'BenefitPay'];

    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        $paymentMethod = 'Cash on Delivery';
    }

    if ($paymentMethod === 'Online Payment') {
        if (!in_array($paymentType, $allowedPaymentTypes, true)) {
            $paymentType = 'Visa';
        }

        $paymentMethod = $paymentType;
    }

    $shippingAddress = "Name: " . $fullName . "\n"
        . "Phone: " . $phone . "\n"
        . "House/Flat: " . $house . "\n"
        . "Road: " . $road . "\n"
        . "Block: " . $block . "\n"
        . "Area/City: " . $area;

    if (empty($cartItems)) {
        $errorMessage = 'Your cart is empty. Please add products before placing an order.';
    } elseif ($fullName === '' || $phone === '' || $house === '' || $road === '' || $block === '' || $area === '') {
        $errorMessage = 'Please fill in your full name, phone number, and full delivery address.';
    } elseif (!preg_match('/^[A-Za-z\s]{3,}$/', $fullName)) {
        $errorMessage = 'Full name must contain letters only.';
    } elseif (!preg_match('/^[0-9]{8}$/', $phone)) {
        $errorMessage = 'Phone number must be exactly 8 digits.';
    } elseif (($paymentMethod === 'Visa' || $paymentMethod === 'Mastercard') && !preg_match('/^[0-9]{16}$/', $cardNumber)) {
        $errorMessage = 'Card number must be exactly 16 digits.';
    } elseif (($paymentMethod === 'Visa' || $paymentMethod === 'Mastercard') && !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiryDate)) {
        $errorMessage = 'Expiry date must be in MM/YY format.';
    } elseif (($paymentMethod === 'Visa' || $paymentMethod === 'Mastercard') && !preg_match('/^[0-9]{3}$/', $securityCode)) {
        $errorMessage = 'Security code must be exactly 3 digits.';
    } elseif (($paymentMethod === 'Visa' || $paymentMethod === 'Mastercard') && !preg_match('/^[A-Za-z\s]{3,}$/', $cardName)) {
        $errorMessage = 'Name on card must contain letters only.';
    } elseif ($paymentMethod === 'BenefitPay' && !preg_match('/^[0-9]{8}$/', $walletPhone)) {
        $errorMessage = 'BenefitPay phone number must be exactly 8 digits.';
    } elseif ($paymentMethod === 'Google Pay' && !preg_match('/^[0-9]{8}$/', $walletPhone)) {
        $errorMessage = 'Google Pay phone number must be exactly 8 digits.';
    } elseif ($paymentMethod === 'Google Pay' && !filter_var($googleEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid Google Pay email.';
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
                    $shippingAddress,
                    $paymentMethod
                );

                mysqli_stmt_execute($stmt);
                $orderId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

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
                    $lineSubtotal = $price * $quantity;

                    $itemSql = "
                        INSERT INTO nps_order_items
                        (order_id, product_id, quantity, `$priceColumn`, subtotal)
                        VALUES (?, ?, ?, ?, ?)
                    ";

                    $stmt = mysqli_prepare($conn, $itemSql);

                    if (!$stmt) {
                        throw new Exception(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($stmt, "iiidd", $orderId, $productId, $quantity, $price, $lineSubtotal);
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
    <link rel="stylesheet" href="../../assets/css/buyer-dropdown.css">

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
            padding: 24px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
        }

        .page-hero h1 {
            font-size: 30px;
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

        .checkout-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) 430px;
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

        .summary-card {
            position: sticky;
            top: 20px;
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
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-row {
            grid-column: 1 / -1;
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
            min-height: 104px;
            resize: vertical;
        }

        .form-control:focus {
            border-color: #3158ff;
            box-shadow: 0 0 0 4px #eef3ff;
        }


        .payment-section {
            background: #ffffff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 22px;
            margin-top: 8px;
        }

        .payment-section h2 {
            font-size: 22px;
            color: #111827;
            margin-bottom: 8px;
        }

        .payment-subtitle {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
        }

        .payment-card {
            display: flex;
            gap: 14px;
            border: 1px solid #d7d7d7;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 16px;
            background: #fff;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .payment-card:hover,
        .payment-card.selected {
            border-color: #3158ff;
            box-shadow: 0 0 0 4px #eef3ff;
        }

        .payment-card input[type="radio"] {
            margin-top: 5px;
            accent-color: #1A4DE1;
        }

        .payment-card-content {
            width: 100%;
        }

        .payment-card-content h3 {
            font-size: 17px;
            color: #111827;
            margin-bottom: 14px;
        }

        .payment-description {
            font-size: 13px;
            color: #666;
        }

        .payment-type-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .payment-type {
            width: 82px;
            height: 48px;
            border: 1px solid #dddddd;
            border-radius: 10px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s ease;
            padding: 6px;
        }

        .payment-type:hover,
        .payment-type.active {
            border-color: #3158ff;
            box-shadow: 0 0 0 4px #eef3ff;
        }

        .payment-type input {
            display: none;
        }

        .payment-type img {
            max-width: 100%;
            max-height: 34px;
            object-fit: contain;
        }

        .fake-card-form,
        .fake-wallet-form {
            margin-top: 8px;
        }

        .checkbox-row {
            display: block;
            font-size: 14px;
            color: #333;
            margin-top: 12px;
        }

        .checkbox-row input {
            margin-right: 8px;
            accent-color: #1A4DE1;
        }

        .fake-payment-note {
            font-size: 12px;
            color: #777;
            margin-top: 6px;
        }

        .field-error {
            color: #d82121;
            font-size: 12px;
            font-weight: 700;
            min-height: 14px;
        }

        .form-control.input-error {
            border-color: #d82121;
            box-shadow: 0 0 0 4px #fff1f1;
        }

        .field-error {
            min-height: 14px;
            color: #d82121;
            font-size: 12px;
            font-weight: 700;
        }

        .form-control.input-error {
            border-color: #d82121;
            box-shadow: 0 0 0 4px #fff1f1;
        }

        .order-item {
            display: grid;
            grid-template-columns: 68px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-img {
            width: 68px;
            height: 62px;
            border-radius: 12px;
            border: 1px solid #eeeeee;
            background: linear-gradient(135deg, #f8faff, #f4f4f6);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            overflow: hidden;
        }

        .order-img img {
            max-width: 100%;
            max-height: 50px;
            object-fit: contain;
        }

        .order-info h3 {
            font-size: 13px;
            color: #111827;
            margin-bottom: 5px;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
            text-align: right;
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

            .summary-card {
                position: static;
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

            .form-grid {
                grid-template-columns: 1fr;
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
                <a href="profile.php" class="icon-btn profile-link" title="Profile">👤</a>
                <a href="../../auth/logout.php" class="logout-btn">Logout</a>
            </div>

        </div>
    </header>

    <main class="content">

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
                                    pattern="[A-Za-z\s]{3,}"
                                    title="Name should contain letters only"
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
                                    pattern="[0-9]{8}"
                                    maxlength="8"
                                    title="Phone number must be 8 digits"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="house">House / Flat Number</label>
                                <input 
                                    class="form-control"
                                    type="text" 
                                    id="house" 
                                    name="house"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="road">Road Number</label>
                                <input 
                                    class="form-control"
                                    type="text" 
                                    id="road" 
                                    name="road"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="block">Block Number</label>
                                <input 
                                    class="form-control"
                                    type="text" 
                                    id="block" 
                                    name="block"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="area">Area / City</label>
                                <input 
                                    class="form-control"
                                    type="text" 
                                    id="area" 
                                    name="area"
                                    required
                                >
                            </div>

                            <div class="form-group full-row">
                                <div class="payment-section">
                                    <h2>Payment Method</h2>
                                    <p class="payment-subtitle">Choose how you want to pay</p>

                                    <label class="payment-card selected">
                                        <input type="radio" name="payment_method" value="Online Payment" checked>

                                        <div class="payment-card-content">
                                            <h3>Card / Digital Payment</h3>

                                           <div class="payment-type-options">
    <label class="payment-type active">
        <input type="radio" name="payment_type" value="Visa" checked>
        <img src="../../assets/images/payment/visa.png" alt="Visa">
    </label>

    <label class="payment-type">
        <input type="radio" name="payment_type" value="Mastercard">
        <img src="../../assets/images/payment/mastercard.png" alt="Mastercard">
    </label>

    <label class="payment-type">
        <input type="radio" name="payment_type" value="Google Pay">
        <img src="../../assets/images/payment/googlepay.png" alt="Google Pay">
    </label>

    <label class="payment-type">
        <input type="radio" name="payment_type" value="BenefitPay">
        <img src="../../assets/images/payment/benefitpay.png" alt="BenefitPay">
    </label>
</div>

                                            <div class="fake-card-form" id="cardFields">
                                                <div class="form-group full-row">
                                                    <label for="card_number">Card Number</label>
                                                    <input 
                                                        class="form-control"
                                                        type="text"
                                                        id="card_number"
                                                        name="card_number"
                                                        placeholder="1234 5678 9012 3456"
                                                        maxlength="19"
                                                    >
                                                    <small class="field-error" id="cardNumberError"></small>
                                                </div>

                                                <div class="form-grid">
                                                    <div class="form-group">
                                                        <label for="expiry_date">Expiry Date</label>
                                                        <input 
                                                            class="form-control"
                                                            type="text"
                                                            id="expiry_date"
                                                            name="expiry_date"
                                                            placeholder="MM/YY"
                                                            maxlength="5"
                                                        >
                                                        <small class="field-error" id="expiryError"></small>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="security_code">Security Code</label>
                                                        <input 
                                                            class="form-control"
                                                            type="text"
                                                            id="security_code"
                                                            name="security_code"
                                                            placeholder="3 digits"
                                                            maxlength="3"
                                                        >
                                                        <small class="field-error" id="securityError"></small>
                                                    </div>
                                                </div>

                                                <div class="form-group full-row">
                                                    <label for="card_name">Name on Card</label>
                                                    <input 
                                                        class="form-control"
                                                        type="text"
                                                        id="card_name"
                                                        name="card_name"
                                                       
                                                    >
                                                    <small class="field-error" id="cardNameError"></small>
                                                </div>
                                            </div>

                                            <div class="fake-wallet-form" id="walletFields" style="display:none;">
                                                <div class="form-group full-row">
                                                    <label for="wallet_phone">Wallet Phone Number</label>
                                                    <input 
                                                        class="form-control"
                                                        type="text"
                                                        id="wallet_phone"
                                                        name="wallet_phone"
                                                        placeholder="Example: 34117717"
                                                        maxlength="8"
                                                    >
                                                    <small class="field-error" id="walletPhoneError"></small>
                                                </div>

                                                <div class="form-group full-row" id="googleEmailBox" style="display:none;">
                                                    <label for="google_email">Google Pay Email</label>
                                                    <input 
                                                        class="form-control"
                                                        type="email"
                                                        id="google_email"
                                                        name="google_email"
                                                        placeholder="Example: user@gmail.com"
                                                    >
                                                    <small class="field-error" id="googleEmailError"></small>
                                                </div>
                                            </div>

                                            <label class="checkbox-row">
                                                <input type="checkbox" checked>
                                                Billing address is the same as delivery address
                                            </label>
                                        </div>
                                    </label>

                                    <label class="payment-card">
                                        <input type="radio" name="payment_method" value="Cash on Delivery">

                                        <div class="payment-card-content">
                                            <h3>Cash on Delivery</h3>
                                            <p class="payment-description">Pay when your order arrives.</p>
                                        </div>
                                    </label>

                                    
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="summary-card">
                        <h2 class="card-title">Order Summary</h2>

                        <?php foreach ($cartItems as $item): ?>
                            <?php
                                $itemTotal = (float)$item['price'] * (int)$item['quantity'];
                                $imagePath = productImagePath($item['image_path']);
                            ?>

                            <div class="order-item">
                                <div class="order-img">
                                    <img 
                                        src="<?php echo $imagePath; ?>" 
                                        alt="Product image"
                                        onerror="this.onerror=null; this.src='../../assets/images/products/view.png';"
                                    >
                                </div>

                                <div class="order-info">
                                    <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
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
            <div>&copy; 2026 NEXTPICK. All Rights Reserved</div>

            <div class="footer-links">
                <a href="#">Privacy policy</a>
                <a href="#">Cookie settings</a>
                <a href="#">Terms and conditions</a>
                <a href="#">Imprint</a>
            </div>
        </div>
    </footer>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    const paymentCards = document.querySelectorAll('.payment-card');
    const fakeCardForm = document.getElementById('fakeCardForm');

    const cardNumber = document.getElementById('card_number');
    const expiryDate = document.getElementById('expiry_date');
    const securityCode = document.getElementById('security_code');
    const cardName = document.getElementById('card_name');

    const cardNumberError = document.getElementById('cardNumberError');
    const expiryError = document.getElementById('expiryError');
    const securityError = document.getElementById('securityError');
    const nameError = document.getElementById('nameError');

    const checkoutForm = document.querySelector('form[method="POST"]');

    function isCreditCardSelected() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        return selected && selected.value === 'Credit Card';
    }

    function clearCardErrors() {
        if (!cardNumber || !expiryDate || !securityCode || !cardName) return;

        cardNumberError.textContent = '';
        expiryError.textContent = '';
        securityError.textContent = '';
        nameError.textContent = '';

        cardNumber.classList.remove('input-error');
        expiryDate.classList.remove('input-error');
        securityCode.classList.remove('input-error');
        cardName.classList.remove('input-error');
    }

    function updatePaymentView() {
        paymentCards.forEach(card => card.classList.remove('selected'));

        paymentRadios.forEach(radio => {
            if (radio.checked) {
                radio.closest('.payment-card').classList.add('selected');
            }
        });

        if (fakeCardForm) {
            if (isCreditCardSelected()) {
                fakeCardForm.style.display = 'block';
            } else {
                fakeCardForm.style.display = 'none';
                clearCardErrors();
            }
        }
    }

    function showError(input, errorElement, message) {
        input.classList.add('input-error');
        errorElement.textContent = message;
    }

    function validateCardFields() {
        clearCardErrors();

        let valid = true;
        const cleanCardNumber = cardNumber.value.replace(/\s/g, '');
        const expiryPattern = /^(0[1-9]|1[0-2])\/\d{2}$/;
        const securityPattern = /^\d{3}$/;

        if (!/^\d{16}$/.test(cleanCardNumber)) {
            showError(cardNumber, cardNumberError, 'Card number must be 16 digits.');
            valid = false;
        }

        if (!expiryPattern.test(expiryDate.value.trim())) {
            showError(expiryDate, expiryError, 'Use MM/YY format.');
            valid = false;
        }

        if (!securityPattern.test(securityCode.value.trim())) {
            showError(securityCode, securityError, 'Security code must be 3 digits.');
            valid = false;
        }

        if (cardName.value.trim().length < 3) {
            showError(cardName, nameError, 'Name on card is required.');
            valid = false;
        }

        return valid;
    }

    if (cardNumber) {
        cardNumber.addEventListener('input', function () {
            let value = this.value.replace(/\D/g, '').substring(0, 16);
            this.value = value.replace(/(.{4})/g, '$1 ').trim();
        });
    }

    if (expiryDate) {
        expiryDate.addEventListener('input', function () {
            let value = this.value.replace(/\D/g, '').substring(0, 4);

            if (value.length >= 3) {
                value = value.substring(0, 2) + '/' + value.substring(2);
            }

            this.value = value;
        });
    }

    if (securityCode) {
        securityCode.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 3);
        });
    }

    paymentRadios.forEach(radio => {
        radio.addEventListener('change', updatePaymentView);
    });

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (event) {
            if (isCreditCardSelected() && !validateCardFields()) {
                event.preventDefault();
            }
        });
    }

    updatePaymentView();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
    const paymentTypeRadios = document.querySelectorAll('input[name="payment_type"]');
    const paymentCards = document.querySelectorAll('.payment-card');
    const paymentTypes = document.querySelectorAll('.payment-type');

    const cardFields = document.getElementById('cardFields');
    const walletFields = document.getElementById('walletFields');
    const googleEmailBox = document.getElementById('googleEmailBox');

    const cardNumber = document.getElementById('card_number');
    const expiryDate = document.getElementById('expiry_date');
    const securityCode = document.getElementById('security_code');
    const cardName = document.getElementById('card_name');
    const walletPhone = document.getElementById('wallet_phone');
    const googleEmail = document.getElementById('google_email');

    const checkoutForm = document.querySelector('form[method="POST"]');

    function selectedPaymentMethod() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        return selected ? selected.value : '';
    }

    function selectedPaymentType() {
        const selected = document.querySelector('input[name="payment_type"]:checked');
        return selected ? selected.value : '';
    }

    function setError(input, message) {
        if (!input) return;
        let error = input.parentElement.querySelector('.field-error');
        input.classList.add('input-error');

        if (error) {
            error.textContent = message;
        }
    }

    function clearErrors() {
        document.querySelectorAll('.field-error').forEach(error => error.textContent = '');
        document.querySelectorAll('.input-error').forEach(input => input.classList.remove('input-error'));
    }

    function updatePaymentView() {
        paymentCards.forEach(card => card.classList.remove('selected'));

        paymentMethodRadios.forEach(radio => {
            if (radio.checked) {
                radio.closest('.payment-card').classList.add('selected');
            }
        });

        paymentTypes.forEach(type => type.classList.remove('active'));

        paymentTypeRadios.forEach(radio => {
            if (radio.checked) {
                radio.closest('.payment-type').classList.add('active');
            }
        });

        const method = selectedPaymentMethod();
        const type = selectedPaymentType();

        if (method === 'Cash on Delivery') {
            cardFields.style.display = 'none';
            walletFields.style.display = 'none';
            googleEmailBox.style.display = 'none';
            clearErrors();
            return;
        }

        if (type === 'Visa' || type === 'Mastercard') {
            cardFields.style.display = 'block';
            walletFields.style.display = 'none';
            googleEmailBox.style.display = 'none';
        } else if (type === 'BenefitPay') {
            cardFields.style.display = 'none';
            walletFields.style.display = 'block';
            googleEmailBox.style.display = 'none';
        } else if (type === 'Google Pay') {
            cardFields.style.display = 'none';
            walletFields.style.display = 'block';
            googleEmailBox.style.display = 'block';
        }

        clearErrors();
    }

    if (cardNumber) {
        cardNumber.addEventListener('input', function () {
            let value = this.value.replace(/\D/g, '').substring(0, 16);
            this.value = value.replace(/(.{4})/g, '$1 ').trim();
        });
    }

    if (expiryDate) {
        expiryDate.addEventListener('input', function () {
            let value = this.value.replace(/\D/g, '').substring(0, 4);

            if (value.length >= 3) {
                value = value.substring(0, 2) + '/' + value.substring(2);
            }

            this.value = value;
        });
    }

    if (securityCode) {
        securityCode.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 3);
        });
    }

    if (cardName) {
        cardName.addEventListener('input', function () {
            this.value = this.value.replace(/[^A-Za-z\s]/g, '').toUpperCase();
        });
    }

    if (walletPhone) {
        walletPhone.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 8);
        });
    }

    function validatePayment() {
        clearErrors();

        const method = selectedPaymentMethod();
        const type = selectedPaymentType();

        if (method === 'Cash on Delivery') {
            return true;
        }

        let valid = true;

        if (type === 'Visa' || type === 'Mastercard') {
            const cleanCardNumber = cardNumber.value.replace(/\s/g, '');

            if (!/^\d{16}$/.test(cleanCardNumber)) {
                setError(cardNumber, 'Card number must be 16 digits.');
                valid = false;
            }

            if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(expiryDate.value.trim())) {
                setError(expiryDate, 'Expiry date must be in MM/YY format.');
                valid = false;
            }

            if (!/^\d{3}$/.test(securityCode.value.trim())) {
                setError(securityCode, 'Security code must be 3 digits.');
                valid = false;
            }

            if (!/^[A-Za-z\s]{3,}$/.test(cardName.value.trim())) {
                setError(cardName, 'Name on card must contain letters only.');
                valid = false;
            }
        }

        if (type === 'BenefitPay') {
            if (!/^[0-9]{8}$/.test(walletPhone.value.trim())) {
                setError(walletPhone, 'BenefitPay phone number must be 8 digits.');
                valid = false;
            }
        }

        if (type === 'Google Pay') {
            if (!/^[0-9]{8}$/.test(walletPhone.value.trim())) {
                setError(walletPhone, 'Phone number must be 8 digits.');
                valid = false;
            }

            if (googleEmail.value.trim() === '') {
                setError(googleEmail, 'Google Pay email is required.');
                valid = false;
            }
        }

        return valid;
    }

    paymentMethodRadios.forEach(radio => {
        radio.addEventListener('change', updatePaymentView);
    });

    paymentTypeRadios.forEach(radio => {
        radio.addEventListener('change', updatePaymentView);
    });

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (event) {
            if (!validatePayment()) {
                event.preventDefault();
            }
        });
    }

    updatePaymentView();
});
</script>

</body>
</html>
