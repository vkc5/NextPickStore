<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

$conn = getConnection();
$adminName = $_SESSION['full_name'] ?? 'Admin';
$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productId <= 0) {
    die('Invalid product ID.');
}

$success = $_SESSION['comment_delete_success'] ?? '';
unset($_SESSION['comment_delete_success']);

$productSql = "
    SELECT
        p.product_id,
        p.product_name,
        p.short_description,
        p.full_description,
        p.price,
        p.brand,
        p.publish_status,
        img.image_path
    FROM nps_products p
    LEFT JOIN nps_product_images img
        ON p.product_id = img.product_id AND img.is_primary = 1
    WHERE p.product_id = ?
    LIMIT 1
";
$productStmt = mysqli_prepare($conn, $productSql);
mysqli_stmt_bind_param($productStmt, "i", $productId);
mysqli_stmt_execute($productStmt);
$productResult = mysqli_stmt_get_result($productStmt);
$product = mysqli_fetch_assoc($productResult);
mysqli_stmt_close($productStmt);

if (!$product) {
    die('Product not found.');
}

$avgRating = 0;
$ratingCount = 0;

$ratingSql = "
    SELECT ROUND(IFNULL(AVG(rating_value), 0), 2) AS avg_rating, COUNT(*) AS total_ratings
    FROM nps_ratings
    WHERE product_id = ?
";
$ratingStmt = mysqli_prepare($conn, $ratingSql);
mysqli_stmt_bind_param($ratingStmt, "i", $productId);
mysqli_stmt_execute($ratingStmt);
$ratingResult = mysqli_stmt_get_result($ratingStmt);
if ($ratingRow = mysqli_fetch_assoc($ratingResult)) {
    $avgRating = $ratingRow['avg_rating'];
    $ratingCount = $ratingRow['total_ratings'];
}
mysqli_stmt_close($ratingStmt);

$comments = [];
$commentSql = "
    SELECT
        c.comment_id,
        c.comment_text,
        c.created_at,
        u.full_name
    FROM nps_comments c
    INNER JOIN nps_users u ON c.user_id = u.user_id
    WHERE c.product_id = ?
    ORDER BY c.created_at DESC, c.comment_id DESC
";
$commentStmt = mysqli_prepare($conn, $commentSql);
mysqli_stmt_bind_param($commentStmt, "i", $productId);
mysqli_stmt_execute($commentStmt);
$commentResult = mysqli_stmt_get_result($commentStmt);

while ($row = mysqli_fetch_assoc($commentResult)) {
    $comments[] = $row;
}
mysqli_stmt_close($commentStmt);

function renderStars($rating) {
    $full = floor($rating);
    $stars = '';
    for ($i = 0; $i < 5; $i++) {
        $stars .= ($i < $full) ? '★' : '☆';
    }
    return $stars;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>View Comments - NextPick</title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
            }
            body {
                background: #e9e9e9;
                color: #222;
            }
            a {
                text-decoration: none;
                color: inherit;
            }

            .page-wrapper {
                margin: 25px;
                background: #f6f6f6;
                border-radius: 14px;
                padding: 25px 30px 0;
                min-height: calc(100vh - 50px);
            }

            .topbar {
                background: #fff;
                border-radius: 10px;
                padding: 16px 22px;
                margin-bottom: 28px;
            }

            .topbar img {
                height: 24px;
                width: auto;
            }

            .main-layout {
                display: grid;
                grid-template-columns: 220px 1fr;
                gap: 28px;
            }

            .sidebar {
                padding-top: 8px;
            }

            .sidebar h2 {
                font-size: 20px;
                margin-bottom: 26px;
                font-weight: 700;
            }

            .nav-menu {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .nav-link {
                display: flex !important;
                align-items: center;
                gap: 12px;
                padding: 12px 14px;
                border-radius: 12px;
                font-size: 16px;
                color: #222;
                transition: all 0.25s ease;
            }

            .nav-link img {
                width: 20px;
                height: 20px;
                object-fit: contain;
                transition: transform 0.25s ease;
            }

            .nav-link:hover {
                background: #eef3ff;
                color: #2155f5;
                box-shadow: 0 6px 16px rgba(33, 85, 245, 0.10);
                transform: translateX(3px);
            }

            .nav-link:hover img {
                transform: scale(1.08);
            }

            .nav-link.active {
                background: #eef3ff;
                color: #2155f5;
                font-weight: 600;
                box-shadow: 0 6px 16px rgba(33, 85, 245, 0.10);
            }

            .nav-link.logout-link {
                margin-top: 10px;
            }

            .content {
                padding-bottom: 30px;
            }

            .section-title {
                background: #efefef;
                border-radius: 10px;
                padding: 16px 22px;
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 16px;
            }

            .title-back-link {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                color: #222;
                font-weight: 600;
                transition: all 0.25s ease;
            }

            .title-back-link:hover {
                color: #2155f5;
            }

            .back-arrow {
                font-size: 20px;
                transition: transform 0.25s ease;
            }

            .title-back-link:hover .back-arrow {
                transform: translateX(-3px);
            }

            .product-id-text {
                color: #888;
                font-size: 13px;
                margin-bottom: 18px;
            }

            .success-box {
                background: #eafaf1;
                border: 1px solid #b8e7c8;
                color: #1a7f4b;
                padding: 12px 14px;
                border-radius: 10px;
                margin-bottom: 18px;
                font-size: 14px;
            }

            .product-card {
                display: grid;
                grid-template-columns: 320px 1fr;
                gap: 26px;
                align-items: start;
                margin-bottom: 34px;
            }

            .main-image-box {
                padding: 10px;
                text-align: center;
            }

            .main-image-box img {
                width: 100%;
                max-width: 320px;
                height: 290px;
                object-fit: cover;
                background: #fff;
                border-radius: 14px;
                border: 1px solid #e9e9e9;
                padding: 10px;
                cursor: pointer;
                transition: transform 0.25s ease, box-shadow 0.25s ease;
            }

            .main-image-box img:hover {
                transform: scale(1.02);
                box-shadow: 0 8px 18px rgba(0, 0, 0, 0.10);
            }

            .image-click-note {
                margin-top: 8px;
                font-size: 12px;
                color: #888;
            }

            .image-modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.35);
                backdrop-filter: blur(6px);
                -webkit-backdrop-filter: blur(6px);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                padding: 20px;
            }

            .image-modal-overlay.show {
                display: flex;
            }

            .image-modal-box {
                position: relative;
                max-width: 90vw;
                max-height: 90vh;
                animation: modalPop 0.22s ease;
            }

            .image-modal-box img {
                display: block;
                max-width: 90vw;
                max-height: 85vh;
                width: auto;
                height: auto;
                border-radius: 16px;
                background: #fff;
                box-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
            }

            .image-modal-close {
                position: absolute;
                top: -14px;
                right: -14px;
                width: 38px;
                height: 38px;
                border: none;
                border-radius: 50%;
                background: #fff;
                color: #222;
                font-size: 22px;
                font-weight: 700;
                cursor: pointer;
                box-shadow: 0 8px 18px rgba(0, 0, 0, 0.18);
                transition: 0.22s ease;
            }

            .image-modal-close:hover {
                background: #f3f3f3;
                transform: scale(1.05);
            }

            .product-info h1 {
                font-size: 24px;
                margin-bottom: 12px;
                font-weight: 700;
            }

            .product-rating {
                font-size: 15px;
                margin-bottom: 14px;
                color: #333;
            }

            .color-row {
                display: flex;
                gap: 10px;
                margin-bottom: 18px;
            }

            .color-dot {
                width: 18px;
                height: 18px;
                border-radius: 50%;
                border: 1px solid #bbb;
            }

            .price-line {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 18px;
                color: #e14a00;
            }

            .desc-line {
                color: #555;
                line-height: 1.7;
                font-size: 14px;
                margin-bottom: 10px;
            }

            .reviews-section-title {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 30px 0 14px;
                font-size: 28px;
                font-weight: 700;
            }

            .comment-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 14px;
                padding: 18px;
                margin-bottom: 14px;
            }

            .comment-top {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 14px;
                flex-wrap: wrap;
            }

            .comment-rating {
                font-size: 18px;
                color: #222;
            }

            .comment-meta {
                display: flex;
                gap: 18px;
                color: #888;
                font-size: 12px;
            }

            .comment-text {
                color: #333;
                line-height: 1.7;
                font-size: 14px;
                margin-bottom: 16px;
                white-space: pre-line;
            }

            .comment-actions {
                display: flex;
                justify-content: flex-end;
            }

            .delete-form {
                display: inline-block;
            }

            .delete-btn {
                background: transparent;
                border: none;
                color: #d13212;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
            }

            .delete-btn:hover {
                text-decoration: underline;
            }

            .empty-comments {
                background: #fff;
                border-radius: 14px;
                padding: 28px;
                text-align: center;
                color: #666;
            }

            .footer {
                margin-top: 35px;
                border-top: 1px solid #ddd;
                padding: 28px 0 18px;
            }

            .footer-top {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 25px;
                margin-bottom: 22px;
                font-size: 14px;
                color: #666;
            }

            .footer-top h4 {
                color: #222;
                margin-bottom: 10px;
                font-size: 15px;
            }

            .footer-bottom {
                border-top: 1px solid #ddd;
                padding-top: 16px;
                font-size: 13px;
                color: #666;
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
            }

            @media (max-width: 950px) {
                .main-layout {
                    grid-template-columns: 1fr;
                }
                .product-card {
                    grid-template-columns: 1fr;
                }
                .sidebar {
                    padding-bottom: 10px;
                }
            }

            @media (max-width: 640px) {
                .page-wrapper {
                    padding: 18px 16px 0;
                }
                .footer-top {
                    grid-template-columns: 1fr;
                }
            }
            .delete-modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.28);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                padding: 20px;
            }

            .delete-modal-overlay.show {
                display: flex;
            }

            .delete-modal {
                width: 100%;
                max-width: 360px;
                background: #fff;
                border-radius: 24px;
                padding: 26px 24px 22px;
                text-align: center;
                box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
                animation: modalPop 0.22s ease;
            }

            @keyframes modalPop {
                from {
                    opacity: 0;
                    transform: scale(0.92);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            .delete-modal-icon {
                width: 52px;
                height: 52px;
                margin: 0 auto 16px;
                border-radius: 50%;
                background: #fff1f0;
                color: #ff4d4f;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 26px;
                font-weight: 700;
                box-shadow: 0 0 0 8px rgba(255, 77, 79, 0.10);
            }

            .delete-modal h3 {
                font-size: 24px;
                margin-bottom: 10px;
                font-weight: 700;
                color: #222;
            }

            .delete-modal p {
                font-size: 14px;
                line-height: 1.6;
                color: #666;
                margin-bottom: 22px;
            }

            .delete-modal-actions {
                display: flex;
                gap: 12px;
            }

            .modal-cancel-btn,
            .modal-delete-btn {
                flex: 1;
                height: 46px;
                border: none;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: 0.22s ease;
            }

            .modal-cancel-btn {
                background: #f3f3f3;
                color: #444;
            }

            .modal-cancel-btn:hover {
                background: #e7e7e7;
            }

            .modal-delete-btn {
                background: #ff4d4f;
                color: #fff;
                box-shadow: 0 8px 18px rgba(255, 77, 79, 0.22);
            }

            .modal-delete-btn:hover {
                background: #ef3f41;
            }
        </style>
    </head>
    <body>
        <div class="page-wrapper">
            <div class="topbar">
                <img src="../../../assets/images/Logos/NextPickStore-Logo.png" alt="NextPick Logo">
            </div>

            <div class="main-layout">
                <aside class="sidebar">
                    <h2>Hi, <?php echo htmlspecialchars($adminName); ?></h2>

                    <nav class="nav-menu">
                        <a href="../dashboard.php" class="nav-link">
                            <img src="../../../assets/images/icons/admin/home.png" alt="Dashboard">
                            <span>Dashboard</span>
                        </a>

                        <a href="../manage_users/manage_users.php" class="nav-link">
                            <img src="../../../assets/images/icons/admin/users.png" alt="Manage users">
                            <span>Manage users</span>
                        </a>

                        <a href="manage_products.php" class="nav-link active">
                            <img src="../../../assets/images/icons/admin/box.png" alt="Manage products">
                            <span>Manage products</span>
                        </a>

                        <a href="../system_reports/system_reports.php" class="nav-link">
                            <img src="../../../assets/images/icons/admin/report.png" alt="System reports">
                            <span>System reports</span>
                        </a>

                        <a href="../profile/profile.php" class="nav-link">
                            <img src="../../../assets/images/icons/admin/profile.png" alt="My profile">
                            <span>My profile</span>
                        </a>

                        <a href="../../../auth/logout.php" class="nav-link logout-link">
                            <img src="../../../assets/images/icons/admin/logout.png" alt="Log out">
                            <span>Logout</span>
                        </a>
                    </nav>
                </aside>

                <main class="content">
                    <div class="section-title">
                        <a href="manage_comments.php" class="title-back-link">
                            <span class="back-arrow">←</span>
                            <span>View Comments</span>
                        </a>
                    </div>

                    <div class="product-id-text">Product ID #<?php echo (int) $product['product_id']; ?></div>

                    <?php if (!empty($success)): ?>
                        <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <div class="product-card">
                        <div class="main-image-box">
                            <img 
                                id="previewTrigger"
                                src="../../../<?php echo htmlspecialchars($product['image_path'] ?: 'assets/images/placeholder.png'); ?>" 
                                alt="Product"
                                >
                            <div class="image-click-note">Click to view image</div>
                        </div>

                        <div class="product-info">
                            <h1><?php echo htmlspecialchars($product['product_name']); ?></h1>

                            <div class="product-rating">
                                <?php echo renderStars($avgRating); ?>
                                <?php echo htmlspecialchars($avgRating); ?>
                                (<?php echo (int) $ratingCount; ?>)
                            </div>

                            <div class="color-row">
                                <span class="color-dot" style="background:#000;"></span>
                                <span class="color-dot" style="background:#9fa6b2;"></span>
                                <span class="color-dot" style="background:#f2f3f7;"></span>
                                <span class="color-dot" style="background:#162447;"></span>
                            </div>

                            <div class="price-line">$<?php echo number_format($product['price'], 2); ?></div>

                            <div class="desc-line"><strong>Brand:</strong> <?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?></div>
                            <div class="desc-line"><strong>Status:</strong> <?php echo htmlspecialchars($product['publish_status']); ?></div>
                            <div class="desc-line"><strong>Short Description:</strong> <?php echo htmlspecialchars($product['short_description']); ?></div>
                            <div class="desc-line"><?php echo nl2br(htmlspecialchars($product['full_description'])); ?></div>
                        </div>
                    </div>

                    <div class="reviews-section-title">
                        <span>User ratings and reviews</span>
                    </div>

                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-card">
                                <div class="comment-top">
                                    <div class="comment-rating">
                                        <?php echo renderStars($avgRating); ?>
                                    </div>

                                    <div class="comment-meta">
                                        <span><?php echo date('d M Y', strtotime($comment['created_at'])); ?></span>
                                        <span><?php echo htmlspecialchars($comment['full_name']); ?></span>
                                    </div>
                                </div>

                                <div class="comment-text">
                                    <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                                </div>

                                <div class="comment-actions">
                                    <form class="delete-form" action="process_delete_comment.php" method="POST">
                                        <input type="hidden" name="comment_id" value="<?php echo (int) $comment['comment_id']; ?>">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['product_id']; ?>">
                                        <button 
                                            type="button"
                                            class="delete-btn"
                                            onclick="openDeleteModal(this.closest('form'))">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-comments">No comments found for this product.</div>
                    <?php endif; ?>
                    <div class="delete-modal-overlay" id="deleteModalOverlay">
                        <div class="delete-modal">
                            <div class="delete-modal-icon">!</div>

                            <h3>Delete comment</h3>
                            <p>
                                Are you sure you want to delete this comment?<br>
                                This action cannot be undone.
                            </p>

                            <div class="delete-modal-actions">
                                <button type="button" class="modal-cancel-btn" onclick="closeDeleteModal()">Cancel</button>
                                <button type="button" class="modal-delete-btn" id="confirmDeleteBtn">Delete</button>
                            </div>
                        </div>
                    </div>

                    <div class="image-modal-overlay" id="imageModalOverlay">
                        <div class="image-modal-box">
                            <button type="button" class="image-modal-close" id="imageModalCloseBtn">×</button>
                            <img 
                                id="imageModalPreview" 
                                src="../../../<?php echo htmlspecialchars($product['image_path'] ?: 'assets/images/placeholder.png'); ?>" 
                                alt="Product Preview"
                                >
                        </div>
                    </div>
                </main>
            </div>

            <footer class="footer">
                <div class="footer-top">
                    <div>
                        <h4>E-commerce support</h4>
                        <div>NEXTPICK</div>
                        <div>Damstraat 123</div>
                        <div>1012 AB Amsterdam</div>
                        <div>The Netherlands</div>
                        <br>
                        <div>Phone: +31 20 123 4567</div>
                        <div>Email: support@nextpick.com</div>
                    </div>
                    <div>
                        <h4>About us</h4>
                        <div>Career</div>
                    </div>
                    <div>
                        <h4>Help & Support</h4>
                        <div>Help center</div>
                        <div>FAQ</div>
                    </div>
                    <div>
                        <h4>Find Us</h4>
                        <div>Facebook | Instagram | Twitter</div>
                    </div>
                </div>

                <div class="footer-bottom">
                    <div>© 2026 NEXTPICK. All Rights Reserved.</div>
                    <div>Privacy policy &nbsp;&nbsp; Cookie settings &nbsp;&nbsp; Terms and conditions</div>
                </div>
            </footer>
        </div>
        <script>
            let formToSubmit = null;

            function openDeleteModal(form) {
                formToSubmit = form;
                document.getElementById('deleteModalOverlay').classList.add('show');
            }

            function closeDeleteModal() {
                formToSubmit = null;
                document.getElementById('deleteModalOverlay').classList.remove('show');
            }

            document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
                if (formToSubmit) {
                    formToSubmit.submit();
                }
            });

            document.getElementById('deleteModalOverlay').addEventListener('click', function (e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });

            const previewTrigger = document.getElementById('previewTrigger');
            const imageModalOverlay = document.getElementById('imageModalOverlay');
            const imageModalCloseBtn = document.getElementById('imageModalCloseBtn');

            function openImageModal() {
                imageModalOverlay.classList.add('show');
            }

            function closeImageModal() {
                imageModalOverlay.classList.remove('show');
            }

            previewTrigger.addEventListener('click', openImageModal);
            imageModalCloseBtn.addEventListener('click', closeImageModal);

            imageModalOverlay.addEventListener('click', function (e) {
                if (e.target === this) {
                    closeImageModal();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeDeleteModal();
                    closeImageModal();
                }
            });
        </script>
    </body>
</html>