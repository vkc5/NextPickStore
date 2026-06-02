<?php
include_once '../../includes/session.php';
include_once '../../includes/functions.php';
include_once '../../includes/config.php';
include 'search.php';

$conn = getConnection();

function productImagePath($path)
{
    if (!empty($path)) {
        return "../../" . htmlspecialchars($path);
    }

    return "../../uploads/products/view.png";
}

function categoryIcon($categoryName)
{
    $name = strtolower($categoryName);

    if (strpos($name, 'camera') !== false) return '📷';
    if (strpos($name, 'headphone') !== false) return '🎧';
    if (strpos($name, 'laptop') !== false) return '💻';
    if (strpos($name, 'smartphone') !== false || strpos($name, 'phone') !== false) return '📱';
    if (strpos($name, 'watch') !== false) return '⌚';
    if (strpos($name, 'speaker') !== false) return '🔊';

    return '🛍️';
}

/* =========================
   SEARCH HANDLING
========================= */
$searchTerm = '';

if (isset($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
} elseif (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
} elseif (isset($_GET['keyword'])) {
    $searchTerm = trim($_GET['keyword']);
} elseif (isset($_POST['q'])) {
    $searchTerm = trim($_POST['q']);
} elseif (isset($_POST['search'])) {
    $searchTerm = trim($_POST['search']);
}

$isSearching = $searchTerm !== '';
$searchResults = [];

if ($isSearching) {
    $searchSql = "
        SELECT
            p.product_id,
            p.product_name,
            p.short_description,
            p.price,
            ROUND(AVG(r.rating_value), 2) AS Rating,
            pi.image_path
        FROM nps_products p
        LEFT JOIN nps_ratings r ON p.product_id = r.product_id
        LEFT JOIN nps_product_images pi
            ON p.product_id = pi.product_id AND pi.is_primary = 1
        LEFT JOIN nps_categories c
            ON p.category_id = c.category_id
        WHERE p.publish_status = 'published'
          AND (
                p.product_name LIKE ?
                OR p.short_description LIKE ?
                OR c.category_name LIKE ?
          )
        GROUP BY p.product_id
        ORDER BY p.created_at DESC
    ";

    $like = "%" . $searchTerm . "%";
    $stmt = mysqli_prepare($conn, $searchSql);
    mysqli_stmt_bind_param($stmt, "sss", $like, $like, $like);
    mysqli_stmt_execute($stmt);

    $res = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($res)) {
        $searchResults[] = $row;
    }

    mysqli_stmt_close($stmt);
}

/* =========================
   LATEST ARRIVED
========================= */
$new = mysqli_query($conn, "
    SELECT
        p.product_id,
        p.product_name,
        p.short_description,
        p.price,
        ROUND(AVG(r.rating_value),2) AS Rating,
        pi.image_path
    FROM nps_products p
    LEFT JOIN nps_ratings r ON p.product_id = r.product_id
    LEFT JOIN nps_product_images pi
        ON p.product_id = pi.product_id AND pi.is_primary = 1
    WHERE p.publish_status = 'published'
    GROUP BY p.product_id
    ORDER BY p.created_at DESC
    LIMIT 5
");

/* =========================
   CATEGORIES
========================= */
$categoryResult = mysqli_query($conn, "
    SELECT
        c.category_id,
        c.category_name,
        MIN(pi.image_path) AS img
    FROM nps_categories c
    LEFT JOIN nps_products p ON c.category_id = p.category_id
    LEFT JOIN nps_product_images pi
        ON p.product_id = pi.product_id AND pi.is_primary = 1
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name ASC
    LIMIT 6
");

$categoriess = [];

if ($categoryResult) {
    while ($r = mysqli_fetch_assoc($categoryResult)) {
        $categoriess[] = $r;
    }
}

/* =========================
   BEST SELLERS
========================= */
$bestSeller = mysqli_query($conn, "
    SELECT
        p.product_id,
        p.product_name,
        p.short_description,
        p.price,
        ROUND(AVG(r.rating_value),2) AS Rating,
        pi.image_path,
        SUM(o.quantity) AS total
    FROM nps_products p
    LEFT JOIN nps_ratings r ON p.product_id = r.product_id
    LEFT JOIN nps_product_images pi
        ON p.product_id = pi.product_id AND pi.is_primary = 1
    INNER JOIN nps_order_items o ON p.product_id = o.product_id
    WHERE p.publish_status = 'published'
    GROUP BY p.product_id
    ORDER BY total DESC
    LIMIT 5
");

$isBuyerSession = isLoggedIn() && (getUserRole() === 'Buyer');
$currentReturnUrl = safeReturnTo($_SERVER['REQUEST_URI'] ?? '/NextPickStore/roles/buyer/dashboard.php');
$cartUrl = $isBuyerSession ? 'cart.php' : loginUrl('/NextPickStore/roles/buyer/cart.php');
$ordersUrl = $isBuyerSession ? 'orders.php' : loginUrl('/NextPickStore/roles/buyer/orders.php');
$profileUrl = $isBuyerSession ? 'profile.php' : loginUrl('/NextPickStore/roles/buyer/profile.php');
$authUrl = $isBuyerSession ? '../../auth/logout.php' : loginUrl($currentReturnUrl);
$authLabel = $isBuyerSession ? 'Logout' : 'Login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Dashboard - NextPick</title>
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

        /* =========================
           HEADER
        ========================= */
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
            cursor: pointer;
            border: 1px solid #f0f0f0;
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

        .icon-btn:hover {
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

        /* =========================
           HERO
        ========================= */
        .hero-section {
            margin: 22px 28px;
            border-radius: 18px;
            overflow: hidden;
            height: 330px;
            background: #d87328;
            position: relative;
            display: flex;
            align-items: center;
        }

        .hero-bg-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center right;
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                90deg,
                rgba(255,255,255,0.88) 0%,
                rgba(255,255,255,0.62) 30%,
                rgba(255,255,255,0.12) 62%,
                rgba(255,255,255,0.02) 100%
            );
        }

        .hero-content {
            position: relative;
            z-index: 5;
            padding: 0 52px;
            max-width: 540px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,0.65);
            border: 1px solid rgba(255,255,255,0.8);
            color: #3158ff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 100px;
            margin-bottom: 20px;
        }

        .hero-title {
            font-size: 40px;
            font-weight: 800;
            color: #111827;
            line-height: 1.08;
            margin-bottom: 12px;
            letter-spacing: -0.6px;
        }

        .hero-sub {
            font-size: 15px;
            color: #333;
            line-height: 1.55;
            margin-bottom: 26px;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
        }

        .btn-hero-primary {
            background: #3158ff;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 12px 22px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-hero-primary:hover {
            background: #1A4DE1;
        }

        .btn-hero-ghost {
            background: rgba(255,255,255,0.85);
            color: #3158ff;
            border: 1px solid #dbe4ff;
            border-radius: 10px;
            padding: 12px 22px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-hero-ghost:hover {
            background: #eef3ff;
        }

        .hero-dots {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 7px;
            z-index: 10;
        }

        .hero-dot {
            height: 6px;
            width: 6px;
            border-radius: 3px;
            background: rgba(255,255,255,0.8);
            cursor: pointer;
            transition: all 0.3s;
        }

        .hero-dot.active {
            width: 22px;
            background: #3158ff;
        }

        /* =========================
           CONTENT
        ========================= */
        .content {
            padding: 8px 28px 36px;
            background: #fcfcfc;
        }

        .section-block {
            margin-bottom: 32px;
        }

        .sec-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 18px;
        }

        .sec-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #3158ff;
            margin-bottom: 4px;
        }

        .sec-title {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .view-all {
            font-size: 13px;
            color: #3158ff;
            font-weight: 700;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 18px;
        }

        .product-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 14px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 245px;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 30px rgba(17,24,39,0.08);
            border-color: #dbe4ff;
        }

        .prod-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: #fff5d6;
            color: #9c6b00;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            z-index: 5;
        }

        .prod-badge.hot {
            background: #ffe8e8;
            color: #b42318;
        }

        .prod-img-wrap {
            width: 100%;
            height: 145px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8faff, #f4f4f6);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 14px;
        }

        .prod-img-wrap img {
            max-width: 82%;
            max-height: 120px;
            object-fit: contain;
        }

        .prod-name {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            line-height: 1.45;
            margin-bottom: 10px;
            flex: 1;
            min-height: 38px;
        }

        .prod-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .prod-price {
            font-size: 14px;
            font-weight: 700;
            color: #3158ff;
        }

        .prod-old {
            font-size: 11px;
            color: #bbb;
            text-decoration: line-through;
            display: block;
            margin-top: 1px;
        }

        .prod-rating {
            font-size: 11.5px;
            color: #888;
        }

        .benefits-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1px;
            background: #ececec;
            border: 1px solid #ececec;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 32px;
        }

        .benefit {
            background: #fff;
            padding: 18px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .benefit-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #eef2ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .benefit-title {
            font-size: 12.5px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 2px;
        }

        .benefit-sub {
            font-size: 11px;
            color: #999;
            line-height: 1.3;
        }

        .cat-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 18px;
        }

        .cat-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 22px 14px 18px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            min-height: 128px;
        }

        .cat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 26px rgba(17,24,39,0.07);
            border-color: #dbe4ff;
        }

        .cat-img-wrap {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: #eef3ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            overflow: hidden;
        }

        .cat-img-wrap img {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .cat-fallback-icon {
            width: 100%;
            height: 100%;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        .cat-name {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .promo-grid {
            display: grid;
            grid-template-columns: 1.65fr 1fr;
            gap: 18px;
            margin-bottom: 32px;
        }

        .promo-card {
            border-radius: 18px;
            overflow: hidden;
            position: relative;
            height: 250px;
            background: #f3f4f6;
            cursor: pointer;
        }

        .promo-card img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #f3f4f6;
            transition: transform 0.4s;
        }

        .promo-card:hover img {
            transform: scale(1.03);
        }

        .promo-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                90deg,
                rgba(0,0,0,0.48) 0%,
                rgba(0,0,0,0.18) 50%,
                rgba(0,0,0,0.08) 100%
            );
        }

        .promo-content {
            position: absolute;
            bottom: 22px;
            left: 22px;
            z-index: 2;
        }

        .promo-tag {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.28);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 6px;
            margin-bottom: 9px;
        }

        .promo-title {
            font-size: 20px;
            color: #fff;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .promo-sub {
            font-size: 12.5px;
            color: rgba(255,255,255,0.8);
            margin-bottom: 14px;
            line-height: 1.45;
            max-width: 300px;
        }

        .btn-promo {
            background: #fff;
            color: #111827;
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
        }

        .search-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .search-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 5px;
        }

        .search-header p {
            font-size: 13.5px;
            color: #666;
        }

        .btn-clear {
            background: #eef2ff;
            color: #3158ff;
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }

        .empty-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 48px 30px;
            text-align: center;
            max-width: 540px;
            margin: 0 auto;
        }

        .empty-icon {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #eef2ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 14px;
        }

        .empty-card h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .empty-card p {
            font-size: 13.5px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .btn-back {
            background: #3158ff;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 22px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .empty-msg {
            color: #aaa;
            font-size: 13.5px;
            padding: 12px 0;
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

        @media (max-width: 1400px) {
            .product-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1100px) {
            .buyer-nav {
                grid-template-columns: 1fr;
                padding: 20px;
                gap: 16px;
            }

            .nav-logo,
            .all-products-btn,
            .buyer-search,
            .nav-actions {
                width: 100%;
            }

            .nav-actions {
                justify-content: center;
            }

            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .cat-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .benefits-row {
                grid-template-columns: repeat(3, 1fr);
            }

            .promo-grid {
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

            .buyer-header {
                padding: 18px;
            }

            .hero-section {
                margin: 16px 18px;
                height: 320px;
            }

            .hero-title {
                font-size: 32px;
            }

            .hero-content {
                padding: 0 30px;
            }

            .content {
                padding: 8px 18px 32px;
            }

            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .cat-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .benefits-row {
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
                <a href="<?php echo htmlspecialchars($cartUrl); ?>" class="icon-btn" title="Cart">🛒</a>
                <a href="<?php echo htmlspecialchars($ordersUrl); ?>" class="icon-btn" title="Orders">🧾</a>
                <a href="<?php echo htmlspecialchars($profileUrl); ?>" class="icon-btn profile-link" title="Profile">👤</a>
                <a href="<?php echo htmlspecialchars($authUrl); ?>" class="logout-btn"><?php echo $authLabel; ?></a>
            </div>

        </div>
    </header>

    <!-- HERO -->
    <section class="hero-section" id="heroSection">
        <img
            class="hero-bg-img"
            src="../../assets/images/products/banner-tech-store.png.png"
            alt="Banner"
            id="heroImg"
            onerror="this.onerror=null; this.style.display='none';"
        >

        <div class="hero-overlay"></div>

        <div class="hero-content">
            <div class="hero-badge">⚡ New Arrivals 2026</div>

            <h1 class="hero-title" id="heroTitle">
                Welcome to<br>NextPick Store
            </h1>

            <p class="hero-sub" id="heroSub">
                Discover the latest electronics and smart products from trusted sellers.
            </p>

            <div class="hero-actions">
                <a href="ViewAllProducts.php?type=allproducts">
                    <button class="btn-hero-primary">Shop Now</button>
                </a>

                <a href="ViewAllProducts.php?type=latest">
                    <button class="btn-hero-ghost">Latest Arrivals</button>
                </a>
            </div>
        </div>

        <div class="hero-dots">
            <div class="hero-dot active" onclick="goSlide(0)"></div>
            <div class="hero-dot" onclick="goSlide(1)"></div>
        </div>
    </section>

    <!-- CONTENT -->
    <main class="content">

        <?php if ($isSearching): ?>

            <div class="section-block">
                <div class="search-header">
                    <div>
                        <h2>Search results</h2>
                        <p>
                            Results for
                            <strong>"<?php echo htmlspecialchars($searchTerm); ?>"</strong>
                            &nbsp;(<?php echo count($searchResults); ?> found)
                        </p>
                    </div>

                    <a href="dashboard.php">
                        <button class="btn-clear">✕ Clear search</button>
                    </a>
                </div>

                <?php if (!empty($searchResults)): ?>
                    <div class="product-grid">
                        <?php foreach ($searchResults as $row): ?>
                            <a href="productDetails.php?id=<?php echo $row['product_id']; ?>">
                                <div class="product-card">
                                    <div class="prod-img-wrap">
                                        <img
                                            src="<?php echo productImagePath($row['image_path']); ?>"
                                            alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                            onerror="this.onerror=null; this.src='../../uploads/products/view.png';"
                                        >
                                    </div>

                                    <div class="prod-name">
                                        <?php echo htmlspecialchars($row['product_name']); ?>
                                    </div>

                                    <div class="prod-footer">
                                        <div>
                                            <div class="prod-price">
                                                €<?php echo number_format($row['price'], 2); ?>
                                            </div>
                                        </div>

                                        <div class="prod-rating">
                                            ★ <?php echo $row['Rating'] ?: '0.00'; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-card">
                        <div class="empty-icon">🔎</div>
                        <h3>No products found</h3>
                        <p>
                            We couldn't find anything matching
                            <strong>"<?php echo htmlspecialchars($searchTerm); ?>"</strong>.
                            <br>
                            Try a different keyword or browse our categories.
                        </p>

                        <a href="dashboard.php">
                            <button class="btn-back">Back to Home</button>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- LATEST ARRIVALS -->
            <div class="section-block">
                <div class="sec-header">
                    <div>
                        <div class="sec-label">New In</div>
                        <div class="sec-title">Latest Arrivals</div>
                    </div>

                    <a href="ViewAllProducts.php?type=latest" class="view-all">
                        View all →
                    </a>
                </div>

                <div class="product-grid">
                    <?php if ($new && mysqli_num_rows($new) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($new)): ?>
                            <a href="productDetails.php?id=<?php echo $row['product_id']; ?>">
                                <div class="product-card">
                                    <span class="prod-badge">−20%</span>

                                    <div class="prod-img-wrap">
                                        <img
                                            src="<?php echo productImagePath($row['image_path']); ?>"
                                            alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                            onerror="this.onerror=null; this.src='../../uploads/products/view.png';"
                                        >
                                    </div>

                                    <div class="prod-name">
                                        <?php echo htmlspecialchars($row['product_name']); ?>
                                    </div>

                                    <div class="prod-footer">
                                        <div>
                                            <div class="prod-price">
                                                €<?php echo number_format($row['price'], 2); ?>
                                            </div>

                                            <div class="prod-old">
                                                €<?php echo number_format($row['price'] + 40, 2); ?>
                                            </div>
                                        </div>

                                        <div class="prod-rating">
                                            ★ <?php echo $row['Rating'] ?: '0.00'; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="empty-msg">No latest products available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- BENEFITS -->
            <div class="benefits-row">
                <div class="benefit">
                    <div class="benefit-icon">🚚</div>
                    <div>
                        <div class="benefit-title">Fast Shipping</div>
                        <div class="benefit-sub">Quick delivery on all orders</div>
                    </div>
                </div>

                <div class="benefit">
                    <div class="benefit-icon">🔁</div>
                    <div>
                        <div class="benefit-title">Easy Returns</div>
                        <div class="benefit-sub">Simple 30-day return policy</div>
                    </div>
                </div>

                <div class="benefit">
                    <div class="benefit-icon">🎁</div>
                    <div>
                        <div class="benefit-title">Special Offers</div>
                        <div class="benefit-sub">Exclusive deals and rewards</div>
                    </div>
                </div>

                <div class="benefit">
                    <div class="benefit-icon">🎧</div>
                    <div>
                        <div class="benefit-title">Support 24/7</div>
                        <div class="benefit-sub">We're here anytime</div>
                    </div>
                </div>

                <div class="benefit">
                    <div class="benefit-icon">🔒</div>
                    <div>
                        <div class="benefit-title">Secure Payment</div>
                        <div class="benefit-sub">100% safe checkout</div>
                    </div>
                </div>
            </div>

            <!-- SHOP BY CATEGORY -->
            <div class="section-block">
                <div class="sec-header">
                    <div>
                        <div class="sec-label">Browse</div>
                        <div class="sec-title">Shop by Category</div>
                    </div>

                    <a href="ViewAllProducts.php?type=allproducts" class="view-all">
                        View all →
                    </a>
                </div>

                <div class="cat-grid">
                    <?php if (!empty($categoriess)): ?>
                        <?php foreach ($categoriess as $cat): ?>
                            <a href="productCategory.php?id=<?php echo $cat['category_id']; ?>&name=<?php echo urlencode($cat['category_name']); ?>">
                                <div class="cat-card">
                                    <div class="cat-img-wrap">
                                        <?php if (!empty($cat['img'])): ?>
                                            <img
                                                src="<?php echo productImagePath($cat['img']); ?>"
                                                alt="<?php echo htmlspecialchars($cat['category_name']); ?>"
                                                onerror="this.style.display='none'; this.parentElement.querySelector('.cat-fallback-icon').style.display='flex';"
                                            >
                                            <span class="cat-fallback-icon" style="display:none;">
                                                <?php echo categoryIcon($cat['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="cat-fallback-icon">
                                                <?php echo categoryIcon($cat['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="cat-name">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-msg">No categories available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PROMO CARDS -->
            <div class="promo-grid">
                <div class="promo-card">
                    <img src="../../assets/images/products/promo-laptop.jpg" alt="MacBook Pro Promo" onerror="this.style.display='none';">

                    <div class="promo-overlay"></div>

                    <div class="promo-content">
                        <div class="promo-tag">New Release</div>
                        <div class="promo-title">MacBook Pro</div>
                        <div class="promo-sub">
                            Powerful performance, elegant design, smooth everyday productivity.
                        </div>

                        <a href="ViewAllProducts.php?type=allproducts">
                            <button class="btn-promo">Shop Now</button>
                        </a>
                    </div>
                </div>

                <div class="promo-card">
                    <img src="../../assets/images/products/promo-speaker.jpg" alt="Speakers Promo" onerror="this.style.display='none';">

                    <div class="promo-overlay"></div>

                    <div class="promo-content">
                        <div class="promo-tag">Featured</div>
                        <div class="promo-title">Premium Audio</div>
                        <div class="promo-sub">
                            The ultimate listening experience for any setup.
                        </div>

                        <a href="ViewAllProducts.php?type=latest">
                            <button class="btn-promo">Buy Now</button>
                        </a>
                    </div>
                </div>
            </div>

            <!-- BEST SELLERS -->
            <div class="section-block">
                <div class="sec-header">
                    <div>
                        <div class="sec-label">Trending</div>
                        <div class="sec-title">Best Sellers</div>
                    </div>

                    <a href="ViewAllProducts.php?type=bestSeller" class="view-all">
                        View all →
                    </a>
                </div>

                <div class="product-grid">
                    <?php if ($bestSeller && mysqli_num_rows($bestSeller) > 0): ?>
                        <?php while ($r = mysqli_fetch_assoc($bestSeller)): ?>
                            <a href="productDetails.php?id=<?php echo $r['product_id']; ?>">
                                <div class="product-card">
                                    <span class="prod-badge hot">−30%</span>

                                    <div class="prod-img-wrap">
                                        <img
                                            src="<?php echo productImagePath($r['image_path']); ?>"
                                            alt="<?php echo htmlspecialchars($r['product_name']); ?>"
                                            onerror="this.onerror=null; this.src='../../uploads/products/view.png';"
                                        >
                                    </div>

                                    <div class="prod-name">
                                        <?php echo htmlspecialchars($r['product_name']); ?>
                                    </div>

                                    <div class="prod-footer">
                                        <div>
                                            <div class="prod-price">
                                                €<?php echo number_format($r['price'], 2); ?>
                                            </div>
                                        </div>

                                        <div class="prod-rating">
                                            ★ <?php echo $r['Rating'] ?: '0.00'; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="empty-msg">No best seller products available.</p>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <!-- FOOTER -->
    <?php include_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

</div>

<script>
    var slides = [
        {
            img: '../../assets/images/products/banner-tech-store.png.png',
            title: 'Welcome to<br>NextPick Store',
            sub: 'Discover the latest electronics and smart products from trusted sellers.'
        },
        {
            img: '../../assets/images/products/banner-electronics.png',
            title: 'Next-Gen<br>Tech Picks',
            sub: 'Explore phones, tablets, watches, earbuds and accessories.'
        }
    ];

    var current = 0;
    var timer;

    function goSlide(i) {
        current = i;

        document.getElementById('heroImg').style.display = 'block';
        document.getElementById('heroImg').src = slides[i].img;
        document.getElementById('heroTitle').innerHTML = slides[i].title;
        document.getElementById('heroSub').textContent = slides[i].sub;

        document.querySelectorAll('.hero-dot').forEach(function(dot, index) {
            dot.classList.toggle('active', index === i);
        });

        clearInterval(timer);

        timer = setInterval(function() {
            goSlide((current + 1) % slides.length);
        }, 5000);
    }

    timer = setInterval(function() {
        goSlide((current + 1) % slides.length);
    }, 5000);
</script>

</body>
</html>
