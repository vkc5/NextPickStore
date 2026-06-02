<?php
include_once '../../includes/auth_guard.php';
requireRole(['Buyer']);
include_once '../../includes/config.php';

$conn = getConnection();

$buyerId = $_SESSION['user_id'] ?? 0;
$buyerName = $_SESSION['full_name'] ?? 'Buyer';
$firstName = $buyerName ? explode(' ', $buyerName)[0] : 'Buyer';

/*
|--------------------------------------------------------------------------
| Rating summary
|--------------------------------------------------------------------------
*/
$averageRating = 0;
$totalRatings = 0;

$summarySql = "
    SELECT 
        IFNULL(AVG(rating_value), 0) AS average_rating,
        COUNT(*) AS total_ratings
    FROM nps_ratings
";

$stmt = mysqli_prepare($conn, $summarySql);
mysqli_stmt_execute($stmt);
$summaryResult = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($summaryResult)) {
    $averageRating = round((float)$row['average_rating'], 1);
    $totalRatings = (int)$row['total_ratings'];
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Rating distribution
|--------------------------------------------------------------------------
*/
$ratingCounts = [
    5 => 0,
    4 => 0,
    3 => 0,
    2 => 0,
    1 => 0
];

$distSql = "
    SELECT rating_value, COUNT(*) AS rating_count
    FROM nps_ratings
    GROUP BY rating_value
";

$stmt = mysqli_prepare($conn, $distSql);
mysqli_stmt_execute($stmt);
$distResult = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($distResult)) {
    $ratingValue = (int)$row['rating_value'];
    if ($ratingValue >= 1 && $ratingValue <= 5) {
        $ratingCounts[$ratingValue] = (int)$row['rating_count'];
    }
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Reviews list
|--------------------------------------------------------------------------
*/
$reviews = [];

$reviewsSql = "
    SELECT
        c.comment_id,
        c.comment_text,
        c.created_at,
        c.updated_at,
        c.user_id,
        u.full_name,
        p.product_name,
        p.product_id,
        r.rating_value
    FROM nps_comments c
    INNER JOIN nps_users u
        ON c.user_id = u.user_id
    INNER JOIN nps_products p
        ON c.product_id = p.product_id
    LEFT JOIN nps_ratings r
        ON c.user_id = r.user_id
        AND c.product_id = r.product_id
    ORDER BY c.created_at DESC
";

$stmt = mysqli_prepare($conn, $reviewsSql);
mysqli_stmt_execute($stmt);
$reviewsResult = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($reviewsResult)) {
    $reviews[] = $row;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function renderStars($rating)
{
    $rating = (int)$rating;
    if ($rating < 0) $rating = 0;
    if ($rating > 5) $rating = 5;

    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reviews - NextPick</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f4f6;
            color: #222;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .page-wrapper {
            max-width: calc(100% - 50px);
            margin: 25px auto;
            background: #fff;
            border: 1px solid #e8e8e8;
            min-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            padding: 18px 28px;
            border-bottom: 1px solid #ececec;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar img {
            height: 34px;
            width: auto;
            object-fit: contain;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .buyer-badge {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e8e8ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .topbar-user {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .topbar-user strong {
            font-size: 14px;
        }

        .topbar-user span {
            font-size: 12px;
            color: #666;
        }

        .main-layout {
            display: flex;
            flex: 1;
        }

        .sidebar {
            width: 255px;
            border-right: 1px solid #ececec;
            padding: 28px 18px;
            background: #fff;
            flex-shrink: 0;
        }

        .sidebar h2 {
            font-size: 28px;
            line-height: 1.15;
            margin-bottom: 28px;
            font-weight: 700;
            color: #111827;
        }

        .menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .menu a {
            display: flex;
            align-items: center;
            min-height: 48px;
            padding: 12px 14px;
            border-radius: 12px;
            color: #111827;
            font-size: 15px;
            font-weight: 500;
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .menu a:hover {
            background: #f4f6ff;
            color: #3158ff;
        }

        .menu a.active {
            background: #eef2ff;
            color: #3158ff;
            font-weight: 600;
        }

        .content {
            flex: 1;
            padding: 28px;
            background: #fcfcfc;
            min-width: 0;
        }

        .content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .content-header h1 {
            font-size: 32px;
            color: #111827;
            margin-bottom: 6px;
        }

        .content-header p {
            color: #666;
            font-size: 14px;
        }

        .btn-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: #3158ff;
            color: #fff;
        }

        .btn-light {
            background: #f1f1f5;
            color: #222;
        }

        .reviews-grid {
            display: grid;
            grid-template-columns: 0.9fr 1.4fr;
            gap: 18px;
            align-items: start;
        }

        .card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 20px;
        }

        .card h3 {
            font-size: 18px;
            color: #111827;
            margin-bottom: 16px;
        }

        .rating-number {
            font-size: 42px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 4px;
        }

        .stars {
            color: #f97316;
            font-size: 20px;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .rating-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .rating-row {
            display: grid;
            grid-template-columns: 60px 1fr 45px;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 14px;
            color: #444;
        }

        .bar-bg {
            height: 9px;
            border-radius: 999px;
            background: #ececec;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background: #3158ff;
            border-radius: 999px;
        }

        .write-box {
            margin-top: 18px;
            padding: 16px;
            border-radius: 14px;
            background: #f7f8ff;
            border: 1px solid #e3e7ff;
        }

        .write-box p {
            font-size: 14px;
            color: #555;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .review-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .review-card {
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 16px;
            background: #fff;
        }

        .review-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .review-product {
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }

        .review-user {
            font-size: 13px;
            color: #666;
        }

        .review-date {
            font-size: 12px;
            color: #888;
        }

        .review-text {
            color: #444;
            font-size: 14px;
            line-height: 1.7;
            margin-top: 10px;
        }

        .review-actions {
            display: flex;
            gap: 12px;
            margin-top: 12px;
            font-size: 13px;
        }

        .review-actions a,
        .review-actions button {
            border: none;
            background: transparent;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            color: #3158ff;
            font-weight: 600;
        }

        .review-actions .delete-link {
            color: #d82121;
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
            from { opacity: 0; transform: scale(0.92); }
            to { opacity: 1; transform: scale(1); }
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

        .empty-state {
            padding: 28px;
            text-align: center;
            color: #666;
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
        }

        .footer {
            border-top: 1px solid #ececec;
            background: #fff;
            margin-top: auto;
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

        @media (max-width: 1000px) {
            .main-layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #ececec;
            }

            .reviews-grid {
                grid-template-columns: 1fr;
            }

            .footer-top {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .page-wrapper {
                max-width: 100%;
                margin: 0;
            }

            .content-header {
                align-items: flex-start;
            }

            .footer-top {
                grid-template-columns: 1fr;
            }

            .topbar,
            .content,
            .footer-top,
            .footer-bottom,
            .sidebar {
                padding-left: 16px;
                padding-right: 16px;
            }
        }
    </style>
</head>

<body>
<div class="page-wrapper">

    <div class="topbar">
        <img src="/NextPickStore/assets/images/Logos/nextpickstore-logo.png" alt="NextPick Logo">

        <div class="topbar-right">
            <div class="buyer-badge"><?php echo strtoupper(substr($buyerName, 0, 1)); ?></div>
            <div class="topbar-user">
                <strong><?php echo htmlspecialchars($buyerName); ?></strong>
                <span>Buyer</span>
            </div>
        </div>
    </div>

    <div class="main-layout">

        <aside class="sidebar">
            <h2>Welcome,<br><?php echo htmlspecialchars($firstName); ?></h2>

            <ul class="menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="products.php">Browse Products</a></li>
                <li><a href="place_order.php">Place Order</a></li>
                <li><a href="my_orders.php">My Orders</a></li>
                <li><a href="reviews.php" class="active">Reviews</a></li>
                <li><a href="write_review.php">Write Review</a></li>
                <li><a href="rate_us.php">Rate Us</a></li>
                <li><a href="../../auth/logout.php">Log out</a></li>
            </ul>
        </aside>

        <main class="content">

            <div class="content-header">
                <div>
                    <h1>Reviews</h1>
                    <p>View customer feedback, ratings, and product review activity.</p>
                </div>

                <div class="btn-row">
                    <a href="write_review.php" class="btn btn-primary">Write a Comment</a>
                    <a href="rate_us.php" class="btn btn-light">Rate Us</a>
                </div>
            </div>

            <div class="reviews-grid">

                <div>
                    <div class="card">
                        <h3>Customer Reviews</h3>

                        <div class="rating-number"><?php echo number_format($averageRating, 1); ?></div>
                        <div class="stars"><?php echo renderStars(round($averageRating)); ?></div>
                        <div class="rating-text">
                            Based on <?php echo $totalRatings; ?> rating<?php echo $totalRatings == 1 ? '' : 's'; ?>
                        </div>

                        <?php for ($i = 5; $i >= 1; $i--) { ?>
                            <?php
                                $count = $ratingCounts[$i];
                                $percent = $totalRatings > 0 ? round(($count / $totalRatings) * 100) : 0;
                            ?>
                            <div class="rating-row">
                                <span><?php echo $i; ?> star</span>
                                <div class="bar-bg">
                                    <div class="bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                                <span><?php echo $percent; ?>%</span>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="write-box">
                        <p>
                            Bought something recently? Share your experience with other buyers and help them choose better products.
                        </p>
                        <a href="write_review.php" class="btn btn-primary">Write Review</a>
                    </div>
                </div>

                <div>
                    <?php if (!empty($reviews)) { ?>
                        <div class="review-list">
                            <?php foreach ($reviews as $review) { ?>
                                <div class="review-card">
                                    <div class="review-top">
                                        <div>
                                            <div class="review-product">
                                                <?php echo htmlspecialchars($review['product_name']); ?>
                                            </div>

                                            <div class="review-user">
                                                By <?php echo htmlspecialchars($review['full_name']); ?>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="stars">
                                                <?php echo renderStars((int)($review['rating_value'] ?? 0)); ?>
                                            </div>

                                            <div class="review-date">
                                                <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="review-text">
                                        <?php echo htmlspecialchars($review['comment_text']); ?>
                                    </div>

                                    <?php if ((int)$review['user_id'] === (int)$buyerId) { ?>
                                        <div class="review-actions">
                                            <a href="write_review.php?edit_comment_id=<?php echo (int)$review['comment_id']; ?>">Edit</a>
                                            <button
                                                type="button"
                                                class="delete-link"
                                                onclick="openReviewDeleteModal(<?php echo (int)$review['comment_id']; ?>, <?php echo (int)$review['product_id']; ?>)">
                                                Delete
                                            </button>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="empty-state">
                            <h3>No reviews yet</h3>
                            <p>Be the first buyer to write a review.</p>
                            <br>
                            <a href="write_review.php" class="btn btn-primary">Write Review</a>
                        </div>
                    <?php } ?>
                </div>

            </div>

        </main>
    </div>

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
                <h4>Help & Support</h4>
                <ul>
                    <li><a href="#">Help center</a></li>
                    <li><a href="#">Payments</a></li>
                    <li><a href="#">Product returns</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div>© 2024 NEXTPICK. All Rights Reserved.</div>
            <div class="footer-links">
                <a href="#">Privacy policy</a>
                <a href="#">Cookie settings</a>
                <a href="#">Terms and conditions</a>
                <a href="#">Imprint</a>
            </div>
        </div>
    </footer>

</div>
<div class="delete-modal-overlay" id="reviewDeleteModalOverlay">
    <div class="delete-modal">
        <div class="delete-modal-icon">!</div>

        <h3>Delete review</h3>
        <p>
            Are you sure you want to delete this review?<br>
            This action cannot be undone.
        </p>

        <div class="delete-modal-actions">
            <button type="button" class="modal-cancel-btn" onclick="closeReviewDeleteModal()">Cancel</button>
            <button type="button" class="modal-delete-btn" id="reviewConfirmDeleteBtn">Delete</button>
        </div>
    </div>
</div>
<script>
    var reviewDeleteCommentId = 0;
    var reviewDeleteProductId = 0;

    function openReviewDeleteModal(commentId, productId) {
        reviewDeleteCommentId = commentId || 0;
        reviewDeleteProductId = productId || 0;
        document.getElementById('reviewDeleteModalOverlay').classList.add('show');
    }

    function closeReviewDeleteModal() {
        reviewDeleteCommentId = 0;
        reviewDeleteProductId = 0;
        document.getElementById('reviewDeleteModalOverlay').classList.remove('show');
    }

    document.getElementById('reviewConfirmDeleteBtn').addEventListener('click', function () {
        if (reviewDeleteCommentId > 0 && reviewDeleteProductId > 0) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'deleteComment.php';

            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = reviewDeleteCommentId;
            form.appendChild(idInput);

            var productInput = document.createElement('input');
            productInput.type = 'hidden';
            productInput.name = 'product_id';
            productInput.value = reviewDeleteProductId;
            form.appendChild(productInput);

            document.body.appendChild(form);
            form.submit();
        }
    });

    document.getElementById('reviewDeleteModalOverlay').addEventListener('click', function (event) {
        if (event.target === this) {
            closeReviewDeleteModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeReviewDeleteModal();
        }
    });
</script>
</body>
</html>
