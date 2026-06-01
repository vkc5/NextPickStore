<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';

requireRole(['Buyer']);

$conn = getConnection();

$userId = $_SESSION['user_id'] ?? 0;

$commentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

$errorMessage = '';
$successMessage = '';

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

/* =========================
   GET COMMENT
========================= */
$comment = null;

if ($commentId > 0 && $userId > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT 
            c.comment_id,
            c.product_id,
            c.user_id,
            c.comment_text,
            c.created_at,
            p.product_name
        FROM nps_comments c
        INNER JOIN nps_products p ON c.product_id = p.product_id
        WHERE c.comment_id = ?
          AND c.user_id = ?
        LIMIT 1
    ");

    mysqli_stmt_bind_param($stmt, "ii", $commentId, $userId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $comment = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
}

if ($comment) {
    $productId = (int)$comment['product_id'];
}

/* =========================
   UPDATE COMMENT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_comment'])) {
    $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $commentText = trim($_POST['comment_text'] ?? '');

    if ($commentText === '') {
        $errorMessage = 'Comment cannot be empty.';
    } else {
        $stmt = mysqli_prepare($conn, "
            UPDATE nps_comments
            SET comment_text = ?
            WHERE comment_id = ?
              AND user_id = ?
        ");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sii", $commentText, $commentId, $userId);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) >= 0) {
                mysqli_stmt_close($stmt);
                header("Location: productDetails.php?id=" . $productId . "&success=comment_updated");
                exit();
            }

            mysqli_stmt_close($stmt);
        }

        $errorMessage = 'Could not update the comment. Please try again.';
    }

    if ($comment) {
        $comment['comment_text'] = $commentText;
    }
}

$pageTitle = $comment ? 'Edit Comment' : 'Comment Not Found';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NextPick</title>

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
            padding: 13px 22px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
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
            color: #333;
        }

        .btn-light:hover {
            background: #e5e5ec;
        }

        .edit-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 24px;
            max-width: 850px;
            margin-bottom: 24px;
        }

        .edit-card h2 {
            font-size: 20px;
            color: #111827;
            margin-bottom: 8px;
        }

        .edit-card p {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
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
            min-height: 150px;
            resize: vertical;
        }

        .form-control:focus {
            border-color: #3158ff;
            box-shadow: 0 0 0 4px #eef3ff;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .alert {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .alert-error {
            background: #fff1f1;
            color: #d82121;
            border: 1px solid #ffd1d1;
        }

        .not-found {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 55px 30px;
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

            .btn {
                width: 100%;
            }

            .form-actions {
                flex-direction: column;
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
    <a href="../../auth/logout.php" class="logout-btn">Logout</a>
</div>

        </div>
    </header>

    <main class="content">

        <!-- PAGE HERO -->
        <section class="page-hero">
            <div>
                <h1>Edit Comment</h1>
                <p>Update your product feedback. Only you can edit your own comment.</p>
            </div>

            <div class="hero-actions">
                <?php if ($comment): ?>
                    <a href="productDetails.php?id=<?php echo (int)$comment['product_id']; ?>">
                        <button type="button" class="btn btn-light">Back to Product</button>
                    </a>
                <?php else: ?>
                    <a href="dashboard.php">
                        <button type="button" class="btn btn-light">Back to Dashboard</button>
                    </a>
                <?php endif; ?>

                <a href="ViewAllProducts.php?type=allproducts">
                    <button type="button" class="btn btn-primary">Browse Products</button>
                </a>
            </div>
        </section>

        <?php if ($comment): ?>

            <section class="edit-card">
                <h2>
                    <?php echo htmlspecialchars($comment['product_name']); ?>
                </h2>

                <p>
                    Edit your comment below, then save the changes.
                </p>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="editComment.php?id=<?php echo (int)$commentId; ?>&product_id=<?php echo (int)$productId; ?>">
                    <input type="hidden" name="comment_id" value="<?php echo (int)$comment['comment_id']; ?>">
                    <input type="hidden" name="product_id" value="<?php echo (int)$comment['product_id']; ?>">

                    <div class="form-group">
                        <label for="comment_text">Your Comment</label>

                        <textarea
                            id="comment_text"
                            name="comment_text"
                            class="form-control"
                            required
                        ><?php echo htmlspecialchars($comment['comment_text']); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_comment" class="btn btn-primary">
                            Save Changes
                        </button>

                        <a href="productDetails.php?id=<?php echo (int)$comment['product_id']; ?>">
                            <button type="button" class="btn btn-light">
                                Cancel
                            </button>
                        </a>
                    </div>
                </form>
            </section>

        <?php else: ?>

            <section class="not-found">
                <div class="empty-icon">💬</div>

                <h2>Comment not found</h2>

                <p>
                    This comment does not exist, or it does not belong to your account.
                </p>

                <a href="dashboard.php">
                    <button class="btn btn-primary">Back to Dashboard</button>
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
