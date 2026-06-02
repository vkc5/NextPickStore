<?php
include_once '../../includes/session.php';
include_once '../../includes/functions.php';
include_once '../../includes/config.php';

$conn = getConnection();

$returnTo = safeReturnTo($_POST['return_to'] ?? '/NextPickStore/roles/buyer/dashboard.php');

if (!isLoggedIn() || (getUserRole() !== 'Buyer')) {
    redirect(loginUrl($returnTo));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

/* IMPORTANT: this detects the Buy Now button */
$buyNow = isset($_POST['buy_now']) && $_POST['buy_now'] == '1';

if ($productId <= 0) {
    header("Location: dashboard.php");
    exit();
}

if ($quantity < 1) {
    $quantity = 1;
}

/* Check product exists and stock */
$stmt = mysqli_prepare($conn, "
    SELECT product_id, stock_quantity
    FROM nps_products
    WHERE product_id = ?
      AND publish_status = 'published'
    LIMIT 1
");

if (!$stmt) {
    header("Location: productDetails.php?id=" . $productId . "&cart_error=failed");
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$product) {
    header("Location: dashboard.php");
    exit();
}

$stockQuantity = (int)$product['stock_quantity'];

if ($stockQuantity <= 0) {
    header("Location: productDetails.php?id=" . $productId . "&cart_error=out_of_stock");
    exit();
}

if ($quantity > $stockQuantity) {
    $quantity = $stockQuantity;
}

/* Create session cart */
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/* Add product to session cart */
if (isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId] += $quantity;

    if ($_SESSION['cart'][$productId] > $stockQuantity) {
        $_SESSION['cart'][$productId] = $stockQuantity;
    }
} else {
    $_SESSION['cart'][$productId] = $quantity;
}

/* Buy Now redirects to checkout */
if ($buyNow) {
    header("Location: checkout.php");
    exit();
}

/* Add to Cart stays on product page */
header("Location: productDetails.php?id=" . $productId . "&cart_success=added");
exit();
?>
