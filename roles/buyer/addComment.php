<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';

requireRole(['Buyer']);

$conn = getConnection();

$userId = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$commentText = trim($_POST['comment_text'] ?? '');
$ratingValue = isset($_POST['rating_value']) ? (int)$_POST['rating_value'] : 0;

if ($productId <= 0) {
    header("Location: dashboard.php");
    exit();
}

if ($commentText === '' && ($ratingValue < 1 || $ratingValue > 5)) {
    header("Location: productDetails.php?id=" . $productId . "&error=empty");
    exit();
}

/* =========================
   CHECK PRODUCT EXISTS
========================= */
$checkProductSql = "
    SELECT product_id
    FROM nps_products
    WHERE product_id = ?
      AND publish_status = 'published'
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $checkProductSql);
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);

$productResult = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($productResult);

mysqli_stmt_close($stmt);

if (!$product) {
    header("Location: dashboard.php");
    exit();
}

/* =========================
   ADD COMMENT
========================= */
if ($commentText !== '') {
    $commentSql = "
        INSERT INTO nps_comments
        (product_id, user_id, comment_text, created_at)
        VALUES (?, ?, ?, NOW())
    ";

    $stmt = mysqli_prepare($conn, $commentSql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iis", $productId, $userId, $commentText);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/* =========================
   ADD OR UPDATE RATING
   This restricts the buyer to one rating per product.
========================= */
if ($ratingValue >= 1 && $ratingValue <= 5) {

    $checkRatingSql = "
        SELECT rating_id
        FROM nps_Ratings
        WHERE product_id = ?
          AND user_id = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $checkRatingSql);
    mysqli_stmt_bind_param($stmt, "ii", $productId, $userId);
    mysqli_stmt_execute($stmt);

    $ratingResult = mysqli_stmt_get_result($stmt);
    $existingRating = mysqli_fetch_assoc($ratingResult);

    mysqli_stmt_close($stmt);

    if ($existingRating) {
        $updateRatingSql = "
            UPDATE nps_Ratings
            SET rating_value = ?
            WHERE rating_id = ?
              AND user_id = ?
        ";

        $stmt = mysqli_prepare($conn, $updateRatingSql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "iii",
                $ratingValue,
                $existingRating['rating_id'],
                $userId
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        $insertRatingSql = "
            INSERT INTO nps_Ratings
            (product_id, user_id, rating_value, created_at)
            VALUES (?, ?, ?, NOW())
        ";

        $stmt = mysqli_prepare($conn, $insertRatingSql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iii", $productId, $userId, $ratingValue);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

header("Location: productDetails.php?id=" . $productId . "&success=review_added");
exit();
?>