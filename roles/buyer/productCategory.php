<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';
include 'search.php';

requireRole(['Buyer']);

$conn = getConnection();

$isSearching = $isSearching ?? false;

$id   = isset($_GET['id'])   ? (int)    $_GET['id']            : 0;
$name = isset($_GET['name']) ? trim((string) $_GET['name'])    : '';

$categoryName = $name !== '' ? $name : 'Category';

/* === CATEGORY PRODUCTS === */
$products = [];

$stmt = mysqli_prepare($conn, "
    SELECT 
        p.product_id,
        p.product_name,
        p.short_description,
        p.price,
        p.brand,
        ROUND(AVG(r.rating_value), 2) AS Rating,
        pi.image_path
    FROM nps_products p
    LEFT JOIN nps_Ratings r ON p.product_id = r.product_id
    LEFT JOIN nps_product_images pi 
        ON p.product_id = pi.product_id AND pi.is_primary = 1
    WHERE p.category_id = ?
      AND p.publish_status = 'published'
    GROUP BY p.product_id
    ORDER BY p.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$productResult = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($productResult)) { $products[] = $row; }
mysqli_stmt_close($stmt);

/* === BRAND LIST === */
$brands = [];

$stmt = mysqli_prepare($conn, "
    SELECT DISTINCT brand
    FROM nps_products
    WHERE category_id = ?
      AND brand IS NOT NULL
      AND brand <> ''
    ORDER BY brand ASC
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$brandResult = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($brandResult)) { $brands[] = $row['brand']; }
mysqli_stmt_close($stmt);

/* === CATEGORIES (for dropdown) === */
$categoriess = [];
$catResult = mysqli_query($conn, "
    SELECT c.category_id, c.category_name, MIN(pi.image_path) AS img
    FROM nps_categories c
    LEFT JOIN nps_products p ON c.category_id = p.category_id
    LEFT JOIN nps_product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name ASC LIMIT 6
");
if ($catResult) { while ($r = mysqli_fetch_assoc($catResult)) { $categoriess[] = $r; } }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($categoryName); ?> - NextPick</title>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        a { text-decoration: none; color: inherit; }
        img { display: block; max-width: 100%; }

        body { font-family: Arial, Helvetica, sans-serif; background: #f4f4f6; color: #222; }

        .page-wrapper {
            max-width: calc(100% - 50px);
            margin: 25px auto;
            background: #fff;
            border: 1px solid #e8e8e8;
            min-height: 90vh;
            overflow: hidden;
        }

        /* ─── HEADER ─── */
        .buyer-header { padding: 28px; background: #fff; border-bottom: 1px solid #ececec; }

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

        .nav-logo { display: flex; align-items: center; }
        .nav-logo img { width: 150px; height: auto; object-fit: contain; }

        .products-dropdown { position: relative; }

        .all-products-btn {
            height: 46px; padding: 0 22px; border-radius: 10px;
            background: #fff; color: #222; border: 1px solid #f0f0f0;
            box-shadow: 0 6px 18px rgba(17,24,39,0.04);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; white-space: nowrap;
            width: 100%; cursor: pointer;
        }
        .all-products-btn:hover { background: #f8f9ff; color: #3158ff; }

        .dropdown-menu {
            position: absolute; top: 54px; left: 0; width: 230px;
            background: #fff; border: 1px solid #ececec; border-radius: 14px;
            box-shadow: 0 18px 35px rgba(17,24,39,0.12);
            padding: 10px; display: none; z-index: 100;
        }
        .products-dropdown:hover .dropdown-menu { display: block; }

        .dropdown-menu a {
            display: block; padding: 11px 12px; border-radius: 10px;
            color: #333; font-size: 13px; font-weight: 600; transition: 0.2s ease;
        }
        .dropdown-menu a:hover { background: #eef3ff; color: #3158ff; }
        .dropdown-divider { height: 1px; background: #eee; margin: 8px 0; }

        .buyer-search {
            height: 42px; width: 100%; background: #fff;
            border: 1px solid #dbe4ff; border-radius: 10px;
            padding: 0 8px 0 18px; display: flex; align-items: center; overflow: hidden;
        }
        .buyer-search input {
            flex: 1; height: 100%; border: none; outline: none;
            background: transparent; color: #111827; font-size: 13px; padding: 0 10px;
        }
        .buyer-search input::placeholder { color: #7b88a8; }
        .buyer-search button {
            width: 34px; height: 34px; border: none; border-radius: 50%;
            background: transparent; color: #3158ff; cursor: pointer;
            font-size: 15px; display: flex; align-items: center; justify-content: center;
        }
        .buyer-search button:hover { background: #eef3ff; }

        .nav-actions { display: flex; align-items: center; justify-content: flex-end; gap: 14px; }

        .icon-btn {
            width: 38px; height: 38px; background: #f7f8fc; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 19px; color: #111827; transition: 0.2s ease;
        }
        .icon-btn:hover { background: #eef3ff; color: #3158ff; }

        .logout-btn {
            background: #1A4DE1; color: #fff; border-radius: 10px;
            padding: 12px 24px; font-size: 14px; font-weight: 700;
            white-space: nowrap; transition: 0.2s ease; border: none; cursor: pointer;
        }
        .logout-btn:hover { background: #123dcc; }

        /* ─── CONTENT ─── */
        .content { padding: 8px 28px 36px; background: #fcfcfc; }

        /* Breadcrumb */
        .breadcrumb {
            display: flex; gap: 8px; align-items: center;
            font-size: 13px; color: #888; margin: 20px 0 18px;
        }
        .breadcrumb a { color: #3158ff; font-weight: 600; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* Page hero */
        .page-hero {
            border-radius: 14px;
            background: linear-gradient(135deg, #eef3ff 0%, #fff 100%);
            border: 1px solid #e5e7eb;
            padding: 30px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            margin-bottom: 24px;
        }

        .page-hero-left .sec-label {
            font-size: 11px; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; color: #3158ff; margin-bottom: 6px;
        }
        .page-hero-left h1 {
            font-size: 28px; font-weight: 800; color: #111827;
            margin-bottom: 8px; letter-spacing: -0.3px;
        }
        .page-hero-left p { font-size: 13.5px; color: #666; line-height: 1.55; }

        .hero-actions { display: flex; gap: 10px; flex-shrink: 0; }

        .btn {
            border: none; border-radius: 10px; padding: 11px 20px;
            font-size: 13.5px; cursor: pointer; font-weight: 700; transition: 0.2s ease;
        }
        .btn-primary { background: #1A4DE1; color: #fff; }
        .btn-primary:hover { background: #123dcc; }
        .btn-light { background: #f1f1f5; color: #222; }
        .btn-light:hover { background: #e8e8f0; }

        /* Brands */
        .brands-card {
            background: #fff; border: 1px solid #ececec; border-radius: 14px;
            padding: 18px 20px; margin-bottom: 24px;
        }

        .sec-header {
            display: flex; justify-content: space-between;
            align-items: flex-end; margin-bottom: 14px;
        }
        .sec-header-left .sec-label {
            font-size: 11px; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; color: #3158ff; margin-bottom: 4px;
        }
        .sec-header-left .sec-title { font-size: 18px; font-weight: 700; color: #111827; }
        .sec-meta { font-size: 13px; color: #888; }

        .brand-row { display: flex; gap: 10px; flex-wrap: wrap; }

        .brand-chip {
            display: inline-flex; align-items: center; height: 36px;
            padding: 0 16px; border-radius: 999px;
            border: 1px solid #e5e7eb; background: #fff;
            color: #333; font-size: 13px; font-weight: 600; transition: 0.2s ease;
        }
        .brand-chip:hover { background: #eef3ff; border-color: #cfd9ff; color: #3158ff; }

        /* Products */
        .product-grid {
            display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px;
        }

        .product-card {
            background: #fff; border: 1px solid #ececec; border-radius: 14px;
            padding: 14px; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative; display: flex; flex-direction: column;
        }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.07); }

        .prod-badge {
            position: absolute; top: 12px; left: 12px;
            background: #eef3ff; color: #3158ff;
            font-size: 10px; font-weight: 700;
            padding: 3px 8px; border-radius: 6px; z-index: 5;
        }

        .prod-img-wrap {
            width: 100%; height: 120px;
            display: flex; align-items: center; justify-content: center;
            background: #fafafa; border-radius: 10px; overflow: hidden; margin-bottom: 12px;
        }
        .prod-img-wrap img { max-width: 90%; max-height: 108px; object-fit: contain; }

        .prod-name {
            font-size: 12.5px; font-weight: 600; color: #111827;
            line-height: 1.4; margin-bottom: 6px; flex: 1; min-height: 34px;
        }

        .prod-desc {
            font-size: 11.5px; color: #999; line-height: 1.4;
            margin-bottom: 10px; height: 32px; overflow: hidden;
        }

        .prod-footer {
            display: flex; justify-content: space-between;
            align-items: flex-end; margin-top: auto;
        }
        .prod-price { font-size: 14px; font-weight: 700; color: #3158ff; }
        .prod-rating { font-size: 11.5px; color: #888; }

        /* Empty state */
        .empty-card {
            background: #fff; border: 1px solid #ececec; border-radius: 14px;
            padding: 56px 30px; text-align: center; max-width: 480px; margin: 0 auto;
        }
        .empty-icon {
            width: 58px; height: 58px; border-radius: 50%; background: #eef2ff;
            color: #3158ff; display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin: 0 auto 14px;
        }
        .empty-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; color: #111827; }
        .empty-card p { font-size: 13.5px; color: #666; line-height: 1.6; }

        /* ─── FOOTER ─── */
        .footer { border-top: 1px solid #ececec; background: #fff; }
        .footer-top { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; padding: 28px; }
        .footer h4 { font-size: 14px; margin-bottom: 12px; color: #222; font-weight: 700; }
        .footer p, .footer a, .footer li { font-size: 13px; color: #666; line-height: 1.8; }
        .footer ul { list-style: none; }
        .footer a:hover { color: #3158ff; }
        .footer-bottom {
            border-top: 1px solid #f0f0f0; padding: 16px 28px;
            display: flex; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; font-size: 12px; color: #666;
        }
        .footer-links { display: flex; gap: 18px; flex-wrap: wrap; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1400px) { .product-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 1100px) {
            .buyer-nav { grid-template-columns: 1fr; padding: 20px; gap: 16px; }
            .nav-logo, .buyer-search, .nav-actions { width: 100%; }
            .nav-actions { justify-content: center; }
            .product-grid { grid-template-columns: repeat(3, 1fr); }
            .footer-top { grid-template-columns: repeat(2, 1fr); }
            .page-hero { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 768px) {
            .page-wrapper { max-width: 100%; margin: 0; }
            .buyer-header, .content { padding: 18px; }
            .content { padding: 8px 18px 32px; }
            .product-grid { grid-template-columns: repeat(2, 1fr); }
            .footer-top { grid-template-columns: 1fr; }
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
                            <a href="productCategory.php?id=<?php echo $cat['category_id']; ?>&name=<?php echo urlencode($cat['category_name']); ?>"
                               <?php echo $cat['category_id'] == $id ? 'style="background:#eef3ff;color:#3158ff;"' : ''; ?>>
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
                <a href="cart.php"    class="icon-btn" title="Cart">🛒</a>
                <a href="profile/"    class="icon-btn" title="Wishlist">♡</a>
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
            <span><?php echo htmlspecialchars($categoryName); ?></span>
        </nav>

        <!-- PAGE HERO -->
        <section class="page-hero">
            <div class="page-hero-left">
                <div class="sec-label">Category</div>
                <h1><?php echo htmlspecialchars($categoryName); ?></h1>
                <p>Explore all products under <strong><?php echo htmlspecialchars($categoryName); ?></strong>.</p>
            </div>
            <div class="hero-actions">
                <a href="dashboard.php"><button class="btn btn-light">← Back to Home</button></a>
                <a href="ViewAllProducts.php?type=allproducts"><button class="btn btn-primary">Browse All</button></a>
            </div>
        </section>

        <!-- BRANDS -->
        <?php if (!empty($brands)): ?>
        <div class="brands-card">
            <div class="sec-header">
                <div class="sec-header-left">
                    <div class="sec-label">Filter</div>
                    <div class="sec-title">Available Brands</div>
                </div>
                <span class="sec-meta"><?php echo count($brands); ?> brands</span>
            </div>
            <div class="brand-row">
                <?php foreach ($brands as $b): ?>
                    <span class="brand-chip"><?php echo htmlspecialchars($b); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- PRODUCTS -->
        <div class="sec-header">
            <div class="sec-header-left">
                <div class="sec-label">Browse</div>
                <div class="sec-title"><?php echo htmlspecialchars($categoryName); ?> Products</div>
            </div>
            <span class="sec-meta"><?php echo count($products); ?> products found</span>
        </div>

        <?php if (!empty($products)): ?>
            <div class="product-grid">
                <?php foreach ($products as $row): ?>
                    <a href="productDetails.php?id=<?php echo $row['product_id']; ?>">
                        <div class="product-card">
                            <?php if (!empty($row['brand'])): ?>
                                <span class="prod-badge"><?php echo htmlspecialchars($row['brand']); ?></span>
                            <?php endif; ?>

                            <div class="prod-img-wrap">
                                <img
                                    src="../../<?php echo !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'assets/images/products/default.png'; ?>"
                                    alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                >
                            </div>

                            <div class="prod-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                            <div class="prod-desc"><?php echo htmlspecialchars($row['short_description']); ?></div>

                            <div class="prod-footer">
                                <div class="prod-price">€<?php echo number_format((float)$row['price'], 2); ?></div>
                                <div class="prod-rating">★ <?php echo $row['Rating'] ?: '0.00'; ?></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-card">
                <div class="empty-icon">📦</div>
                <h3>No products yet</h3>
                <p>There are no published products under this category. Try browsing another category.</p>
            </div>
        <?php endif; ?>

    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-top">
            <div>
                <h4>E-commerce support</h4>
                <p>NEXTPICK</p><p>Manama, Bahrain</p>
                <p>Phone: +973 123 4567</p><p>Email: support@nextpick.com</p>
            </div>
            <div>
                <h4>Working hours</h4>
                <p>Monday to Friday: 09:00 - 18:00</p>
                <p>Saturday: 10:00 - 16:00</p><p>Sunday: Closed</p>
            </div>
            <div>
                <h4>About us</h4>
                <ul>
                    <li><a href="#">Stores</a></li><li><a href="#">Corporate website</a></li>
                    <li><a href="#">Exclusive Offers</a></li><li><a href="#">Career</a></li>
                </ul>
            </div>
            <div>
                <h4>Help &amp; Support</h4>
                <ul>
                    <li><a href="#">Help center</a></li><li><a href="#">Payments</a></li>
                    <li><a href="#">Product returns</a></li><li><a href="#">FAQ</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div>&copy; 2026 NEXTPICK. All Rights Reserved.</div>
            <div class="footer-links">
                <a href="#">Privacy policy</a><a href="#">Cookie settings</a>
                <a href="#">Terms and conditions</a><a href="#">Imprint</a>
            </div>
        </div>
    </footer>

</div>
</body>
</html>