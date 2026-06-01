<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';

requireRole(['Buyer']);

$conn = getConnection();

function productImagePath($path)
{
    if (!empty($path)) {
        return "../../" . htmlspecialchars($path);
    }

    return "../../assets/images/products/view.png";
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$userId = $_SESSION['user_id'] ?? 0;

/* === PRODUCT DETAILS === */
$stmt = mysqli_prepare($conn, "
    SELECT 
        p.product_id,
        p.product_name,
        p.brand,
        p.short_description,
        p.full_description,
        p.price,
        p.stock_quantity,
        p.category_id,
        ROUND(AVG(r.rating_value), 2) AS Rating,
        pi.image_path,
        u.full_name AS seller_name,
        c.category_name
    FROM nps_products p
    INNER JOIN nps_users u ON p.seller_id = u.user_id
    LEFT JOIN nps_ratings r ON p.product_id = r.product_id
    LEFT JOIN nps_product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
    LEFT JOIN nps_categories c ON p.category_id = c.category_id
    WHERE p.product_id = ?
      AND p.publish_status = 'published'
    GROUP BY p.product_id
");

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

/* === COMMENTS === */
$comments = [];

$stmt = mysqli_prepare($conn, "
    SELECT 
        c.comment_id,
        c.comment_text,
        c.created_at,
        c.user_id,
        u.full_name
    FROM nps_comments c
    INNER JOIN nps_users u ON c.user_id = u.user_id
    WHERE c.product_id = ?
    ORDER BY c.created_at DESC
");

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$commentResult = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($commentResult)) {
    $comments[] = $row;
}

mysqli_stmt_close($stmt);

/* === USER CURRENT RATING === */
$userRating = '';

if ($product && $userId > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT rating_value
        FROM nps_ratings
        WHERE product_id = ?
          AND user_id = ?
        LIMIT 1
    ");

    mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
    mysqli_stmt_execute($stmt);

    $ratingResult = mysqli_stmt_get_result($stmt);
    $ratingRow = mysqli_fetch_assoc($ratingResult);

    if ($ratingRow) {
        $userRating = $ratingRow['rating_value'];
    }

    mysqli_stmt_close($stmt);
}

/* === CATEGORIES FOR DROPDOWN === */
$categoriess = [];

$catResult = mysqli_query($conn, "
    SELECT 
        c.category_id,
        c.category_name,
        MIN(pi.image_path) AS img
    FROM nps_categories c
    LEFT JOIN nps_products p ON c.category_id = p.category_id
    LEFT JOIN nps_product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name ASC
    LIMIT 6
");

if ($catResult) {
    while ($r = mysqli_fetch_assoc($catResult)) {
        $categoriess[] = $r;
    }
}

/* === PRODUCT VIEW TRACKING === */
if ($product) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO nps_product_views (product_id, user_id, view_date)
        VALUES (?, ?, NOW())
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

$averageRating = $product && $product['Rating'] ? $product['Rating'] : '0.00';
$inStock = $product && isset($product['stock_quantity']) && (int)$product['stock_quantity'] > 0;
$stockText = $inStock ? 'In Stock' : 'Out of Stock';

$pageTitle = $product ? htmlspecialchars($product['product_name']) : 'Product Details';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - NextPick</title>

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

        /* HEADER */
        .buyer-header {
            padding: 28px;
            background: #fff;
            border-bottom: 1px solid #ececec;
        }

        .buyer-nav {
            width: 100%;
            min-height: 74px;
            background: #fff;
            border-radius: 16px;
            padding: 0 32px;
            display: grid;
            grid-template-columns: 230px 210px minmax(300px, 420px) auto;
            align-items: center;
            gap: 28px;
            box-shadow: 0 10px 28px rgba(17,24,39,0.06);
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

        .products-dropdown {
            position: relative;
        }

        .all-products-btn {
            height: 46px;
            padding: 0 22px;
            border-radius: 10px;
            background: #fff;
            color: #222;
            border: 1px solid #f0f0f0;
            box-shadow: 0 6px 18px rgba(17,24,39,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
            width: 100%;
            cursor: pointer;
        }

        .all-products-btn:hover {
            background: #f8f9ff;
            color: #3158ff;
        }

        .dropdown-menu {
            position: absolute;
            top: 54px;
            left: 0;
            width: 230px;
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 14px;
            box-shadow: 0 18px 35px rgba(17,24,39,0.12);
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
            background: #eee;
            margin: 8px 0;
        }

        .buyer-search {
            height: 42px;
            width: 100%;
            background: #fff;
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

        .icon-btn:hover {
            background: #eef3ff;
            color: #3158ff;
        }

        .logout-btn {
            background: #1A4DE1;
            color: #fff;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            border: none;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .logout-btn:hover {
            background: #123dcc;
        }

        /* CONTENT */
        .content {
            padding: 8px 28px 36px;
            background: #fcfcfc;
        }

        .breadcrumb {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 13px;
            color: #888;
            margin: 20px 0 22px;
            flex-wrap: wrap;
        }

        .breadcrumb a {
            color: #3158ff;
            font-weight: 600;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .details-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 24px;
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            gap: 30px;
            margin-bottom: 22px;
        }

        .product-image-box {
            background: linear-gradient(135deg, #f8faff, #f4f4f6);
            border: 1px solid #eeeeee;
            border-radius: 16px;
            min-height: 350px;
            padding: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image-box img {
            width: 100%;
            max-height: 320px;
            object-fit: contain;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .label-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            height: 30px;
            padding: 0 14px;
            border-radius: 999px;
            background: #eef3ff;
            color: #3158ff;
            font-size: 12.5px;
            font-weight: 700;
        }

        .stock-tag {
            display: inline-flex;
            align-items: center;
            height: 30px;
            padding: 0 14px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 700;
        }

        .in-stock {
            background: #e7faed;
            color: #1b8a46;
        }

        .out-stock {
            background: #ffe8e8;
            color: #d82121;
        }

        .product-title {
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            line-height: 1.18;
            letter-spacing: -0.4px;
        }

        .short-desc {
            font-size: 14.5px;
            color: #555;
            line-height: 1.65;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .meta-box {
            border: 1px solid #ececec;
            border-radius: 12px;
            padding: 14px 16px;
            background: #fff;
        }

        .meta-box span {
            display: block;
            font-size: 11.5px;
            color: #999;
            margin-bottom: 5px;
        }

        .meta-box strong {
            font-size: 14px;
            color: #111827;
            font-weight: 700;
        }

        .meta-box .rating-val {
            color: #f59e0b;
        }

        .product-price {
            font-size: 32px;
            font-weight: 800;
            color: #3158ff;
            letter-spacing: -0.5px;
        }

        .quantity-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-width: 160px;
            margin-bottom: 14px;
        }

        .quantity-row label {
            font-size: 13.5px;
            font-weight: 700;
            color: #111827;
        }

        .quantity-row input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #dde1f0;
            border-radius: 10px;
            outline: none;
            font-size: 14px;
            transition: 0.2s;
        }

        .quantity-row input:focus {
            border-color: #3158ff;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 13px 22px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-cart {
            background: #1A4DE1;
            color: #fff;
        }

        .btn-cart:hover {
            background: #123dcc;
        }

        .btn-buy {
            background: #111827;
            color: #fff;
        }

        .btn-buy:hover {
            background: #222f44;
        }

        .btn-back {
            background: #f1f1f5;
            color: #333;
        }

        .btn-back:hover {
            background: #e5e5ec;
        }

        .section-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 18px;
        }

        .section-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .section-card-header .sec-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #3158ff;
        }

        .section-card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .section-card p {
            font-size: 14px;
            color: #555;
            line-height: 1.75;
        }

        /* REVIEW FORM */
        .review-form {
            display: grid;
            gap: 16px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
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
            min-height: 105px;
            resize: vertical;
        }

        .form-control:focus {
            border-color: #3158ff;
            box-shadow: 0 0 0 4px #eef3ff;
        }

        .submit-review-btn {
            width: fit-content;
            border: none;
            border-radius: 10px;
            background: #1A4DE1;
            color: #fff;
            padding: 13px 24px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .submit-review-btn:hover {
            background: #123dcc;
            transform: translateY(-1px);
        }

        .review-message {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .success-msg {
            background: #e7faed;
            color: #1b8a46;
            border: 1px solid #c9f0d6;
        }

        .error-msg {
            background: #fff1f1;
            color: #d82121;
            border: 1px solid #ffd1d1;
        }

        /* REVIEWS */
        .review-summary {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
            padding: 16px;
            background: #fafafa;
            border-radius: 12px;
            border: 1px solid #ececec;
        }

        .review-score {
            font-size: 42px;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }

        .review-stars {
            font-size: 20px;
            color: #f59e0b;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }

        .review-count {
            font-size: 13px;
            color: #888;
        }

        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .review {
            border: 1px solid #ececec;
            border-radius: 12px;
            padding: 16px 18px;
            background: #fff;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .review-author {
            font-size: 13.5px;
            font-weight: 700;
            color: #111827;
        }

        .review-date {
            font-size: 12px;
            color: #aaa;
        }

        .review-text {
            font-size: 13.5px;
            color: #555;
            line-height: 1.65;
        }

        .comment-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .comment-actions a {
            font-size: 12px;
            font-weight: 700;
            color: #3158ff;
        }

        .comment-actions a.delete-link {
            color: #d82121;
        }

        .empty-reviews {
            background: #fafafa;
            border: 1px dashed #dde1f0;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            color: #999;
            font-size: 14px;
        }

        .not-found {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 60px 30px;
            text-align: center;
            margin-bottom: 24px;
        }

        .not-found .empty-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #eef2ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 16px;
        }

        .not-found h2 {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }

        .not-found p {
            font-size: 14px;
            color: #666;
            margin-bottom: 22px;
        }

        /* FOOTER */
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

        .btn:disabled,
        .quantity-row input:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 1100px) {
            .buyer-nav {
                grid-template-columns: 1fr;
                padding: 20px;
                gap: 16px;
            }

            .nav-logo,
            .buyer-search,
            .nav-actions,
            .products-dropdown {
                width: 100%;
            }

            .nav-actions {
                justify-content: center;
            }

            .details-card {
                grid-template-columns: 1fr;
            }

            .meta-grid,
            .form-row {
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

            .content {
                padding: 8px 18px 32px;
            }

            .product-title {
                font-size: 24px;
            }

            .product-price {
                font-size: 28px;
            }

            .product-image-box {
                min-height: 280px;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }

            .buttons {
                flex-direction: column;
            }

            .btn,
            .submit-review-btn {
                width: 100%;
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
                <button type="button" class="all-products-btn">☰ All Products ▾</button>

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
                <input type="text" name="q" placeholder="I am Searching for...">
                <button type="submit" aria-label="Search">⌕</button>
            </form>

            <div class="nav-actions">
                <a href="cart.php" class="icon-btn" title="Cart">🛒</a>
                <a href="orders.php" class="icon-btn" title="Orders">🧾</a>
                <a href="../../auth/logout.php" class="logout-btn">Logout</a>
            </div>

        </div>
    </header>

    <main class="content">

        <!-- BREADCRUMB -->
        <nav class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>/</span>
            <a href="ViewAllProducts.php?type=allproducts">Products</a>
            <span>/</span>

            <?php if ($product && !empty($product['category_name'])): ?>
                <a href="productCategory.php?id=<?php echo $product['category_id']; ?>&name=<?php echo urlencode($product['category_name']); ?>">
                    <?php echo htmlspecialchars($product['category_name']); ?>
                </a>
                <span>/</span>
            <?php endif; ?>

            <span><?php echo $pageTitle; ?></span>
        </nav>

        <?php if ($product): ?>

            <!-- PRODUCT DETAILS -->
            <section class="details-card">

                <div class="product-image-box">
                    <img
                        src="<?php echo productImagePath($product['image_path']); ?>"
                        alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                        onerror="this.onerror=null; this.src='../../assets/images/products/view.png';"
                    >
                </div>

                <div class="product-info">

                    <div class="label-row">
                        <?php if (!empty($product['brand'])): ?>
                            <span class="tag"><?php echo htmlspecialchars($product['brand']); ?></span>
                        <?php endif; ?>

                        <?php if (!empty($product['category_name'])): ?>
                            <span class="tag" style="background:#f3f4f6;color:#555;">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </span>
                        <?php endif; ?>

                        <span class="stock-tag <?php echo $inStock ? 'in-stock' : 'out-stock'; ?>">
                            <?php echo $inStock ? '✓ In Stock' : '✗ Out of Stock'; ?>
                        </span>
                    </div>

                    <h1 class="product-title">
                        <?php echo htmlspecialchars($product['product_name']); ?>
                    </h1>

                    <p class="short-desc">
                        <?php echo htmlspecialchars($product['short_description']); ?>
                    </p>

                    <div class="meta-grid">
                        <div class="meta-box">
                            <span>Average Rating</span>
                            <strong class="rating-val">★ <?php echo htmlspecialchars($averageRating); ?></strong>
                        </div>

                        <div class="meta-box">
                            <span>Sold By</span>
                            <strong><?php echo htmlspecialchars($product['seller_name']); ?></strong>
                        </div>

                        <?php if ($inStock): ?>
                            <div class="meta-box">
                                <span>Available Stock</span>
                                <strong><?php echo (int)$product['stock_quantity']; ?> units</strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="product-price">
                        €<?php echo number_format((float)$product['price'], 2); ?>
                    </div>

                    <?php if (isset($_GET['cart_success']) && $_GET['cart_success'] === 'added'): ?>
                        <div class="review-message success-msg">
                            Product added to cart successfully.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['cart_error']) && $_GET['cart_error'] === 'out_of_stock'): ?>
                        <div class="review-message error-msg">
                            This product is currently out of stock.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['cart_error']) && $_GET['cart_error'] === 'failed'): ?>
                        <div class="review-message error-msg">
                            Product could not be added to cart. Please try again.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="addToCart.php">
                        <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">

                        <div class="quantity-row">
                            <label for="quantity">Quantity</label>

                            <input
                                id="quantity"
                                name="quantity"
                                type="number"
                                value="1"
                                min="1"
                                <?php if ($inStock): ?>
                                    max="<?php echo (int)$product['stock_quantity']; ?>"
                                <?php endif; ?>
                                <?php echo !$inStock ? 'disabled' : ''; ?>
                            >
                        </div>

                        <div class="buttons">
                            <button
                                type="submit"
                                class="btn btn-cart"
                                <?php echo !$inStock ? 'disabled' : ''; ?>
                            >
                                🛒 Add to Cart
                            </button>

                            <button
                                type="submit"
                                name="buy_now"
                                value="1"
                                class="btn btn-buy"
                                <?php echo !$inStock ? 'disabled' : ''; ?>
                            >
                                Buy Now
                            </button>

                            <a href="javascript:history.back()">
                                <button type="button" class="btn btn-back">← Back</button>
                            </a>
                        </div>
                    </form>

                </div>
            </section>

            <!-- DESCRIPTION -->
            <section class="section-card">
                <div class="section-card-header">
                    <span class="sec-label">Details</span>
                    <h2>Product Description</h2>
                </div>

                <p>
                    <?php echo nl2br(htmlspecialchars($product['full_description'])); ?>
                </p>
            </section>

            <!-- ADD REVIEW -->
            <section class="section-card">
                <div class="section-card-header">
                    <span class="sec-label">Review</span>
                    <h2>Add Your Review</h2>
                </div>

                <?php if (isset($_GET['success']) && $_GET['success'] === 'review_added'): ?>
                    <div class="review-message success-msg">
                        Your review was added successfully.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['success']) && $_GET['success'] === 'comment_deleted'): ?>
                    <div class="review-message success-msg">
                        Your comment was deleted successfully.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['success']) && $_GET['success'] === 'comment_updated'): ?>
                    <div class="review-message success-msg">
                        Your comment was updated successfully.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'empty'): ?>
                    <div class="review-message error-msg">
                        Please write a comment or choose a rating.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'not_owner'): ?>
                    <div class="review-message error-msg">
                        You can only delete your own comments.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'delete_failed'): ?>
                    <div class="review-message error-msg">
                        Comment could not be deleted. Please try again.
                    </div>
                <?php endif; ?>

                <form class="review-form" method="POST" action="addComment.php">
                    <input type="hidden" name="product_id" value="<?php echo (int)$id; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="rating_value">Your Rating</label>

                            <select id="rating_value" name="rating_value" class="form-control">
                                <option value="">Choose rating</option>
                                <option value="5" <?php echo $userRating == 5 ? 'selected' : ''; ?>>★★★★★ Excellent</option>
                                <option value="4" <?php echo $userRating == 4 ? 'selected' : ''; ?>>★★★★ Good</option>
                                <option value="3" <?php echo $userRating == 3 ? 'selected' : ''; ?>>★★★ Average</option>
                                <option value="2" <?php echo $userRating == 2 ? 'selected' : ''; ?>>★★ Poor</option>
                                <option value="1" <?php echo $userRating == 1 ? 'selected' : ''; ?>>★ Very Poor</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="comment_text">Your Comment</label>

                            <textarea
                                id="comment_text"
                                name="comment_text"
                                class="form-control"
                                placeholder="Write your comment about this product..."
                            ></textarea>
                        </div>
                    </div>

                    <button type="submit" class="submit-review-btn">
                        Submit Review
                    </button>
                </form>
            </section>

            <!-- REVIEWS -->
            <section class="section-card">
                <div class="section-card-header">
                    <span class="sec-label">Feedback</span>
                    <h2>Customer Reviews</h2>
                </div>

                <?php if (!empty($comments)): ?>
                    <div class="review-summary">
                        <div class="review-score">
                            <?php echo htmlspecialchars($averageRating); ?>
                        </div>

                        <div>
                            <div class="review-stars">
                                <?php
                                $stars = round((float)$averageRating);
                                echo str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
                                ?>
                            </div>

                            <div class="review-count">
                                <?php echo count($comments); ?> review<?php echo count($comments) !== 1 ? 's' : ''; ?>
                            </div>
                        </div>
                    </div>

                    <div class="reviews-list">
                        <?php foreach ($comments as $comment): ?>
                            <div class="review">
                                <div class="review-header">
                                    <span class="review-author">
                                        <?php echo htmlspecialchars($comment['full_name']); ?>
                                    </span>

                                    <span class="review-date">
                                        <?php echo date('M j, Y', strtotime($comment['created_at'])); ?>
                                    </span>
                                </div>

                                <p class="review-text">
                                    <?php echo htmlspecialchars($comment['comment_text']); ?>
                                </p>

                                <?php if ((int)$comment['user_id'] === (int)$userId): ?>
                                    <div class="comment-actions">
                                        <a href="editComment.php?id=<?php echo (int)$comment['comment_id']; ?>&product_id=<?php echo (int)$id; ?>">
                                            Edit
                                        </a>

                                        <a 
                                            class="delete-link"
                                            href="deleteComment.php?id=<?php echo (int)$comment['comment_id']; ?>&product_id=<?php echo (int)$id; ?>"
                                            onclick="return confirm('Are you sure you want to delete this comment?');"
                                        >
                                            Delete
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <div class="empty-reviews">
                        No reviews yet. Be the first to share your experience.
                    </div>
                <?php endif; ?>
            </section>

        <?php else: ?>

            <div class="not-found">
                <div class="empty-icon">🔍</div>

                <h2>Product not found</h2>

                <p>
                    This product may have been removed or is no longer available.
                </p>

                <a href="ViewAllProducts.php?type=allproducts">
                    <button class="btn btn-cart">Browse All Products</button>
                </a>
            </div>

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
