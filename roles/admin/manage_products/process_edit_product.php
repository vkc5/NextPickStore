<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /NextPickStore/roles/admin/manage_products/view_products.php');
    exit;
}

$conn = getConnection();

$productId = (int)($_POST['product_id'] ?? 0);
$productName = trim($_POST['product_name'] ?? '');
$shortDescription = trim($_POST['short_description'] ?? '');
$fullDescription = trim($_POST['full_description'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$categoryId = (int)($_POST['category_id'] ?? 0);
$stockQuantity = (int)($_POST['stock_quantity'] ?? 0);
$brand = trim($_POST['brand'] ?? '');
$publishStatus = trim($_POST['publish_status'] ?? '');

$allowedStatuses = ['draft', 'published', 'hidden'];

if (
    $productId <= 0 ||
    $productName === '' ||
    $shortDescription === '' ||
    $fullDescription === '' ||
    $categoryId <= 0 ||
    $price < 0 ||
    $stockQuantity < 0 ||
    !in_array($publishStatus, $allowedStatuses, true)
) {
    $_SESSION['product_edit_error'] = 'Please fill all required fields correctly.';
    header('Location: /NextPickStore/roles/admin/manage_products/edit_product.php?id=' . $productId);
    exit;
}

/* -----------------------------
   Update product data
----------------------------- */
$sql = "
    UPDATE nps_products
    SET
        product_name = ?,
        short_description = ?,
        full_description = ?,
        price = ?,
        category_id = ?,
        stock_quantity = ?,
        brand = ?,
        publish_status = ?,
        updated_at = NOW()
    WHERE product_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmt,
    "sssdiissi",
    $productName,
    $shortDescription,
    $fullDescription,
    $price,
    $categoryId,
    $stockQuantity,
    $brand,
    $publishStatus,
    $productId
);

if (!mysqli_stmt_execute($stmt)) {
    $_SESSION['product_edit_error'] = 'Failed to update product.';
    mysqli_stmt_close($stmt);
    header('Location: /NextPickStore/roles/admin/manage_products/edit_product.php?id=' . $productId);
    exit;
}

mysqli_stmt_close($stmt);

/* -----------------------------
   Image upload
----------------------------- */
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['product_edit_error'] = 'Product updated, but image upload failed.';
        header('Location: /NextPickStore/roles/admin/manage_products/edit_product.php?id=' . $productId);
        exit;
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    $originalName = $_FILES['product_image']['name'];
    $tmpName = $_FILES['product_image']['tmp_name'];

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        $_SESSION['product_edit_error'] = 'Product updated, but image type is not allowed.';
        header('Location: /NextPickStore/roles/admin/manage_products/edit_product.php?id=' . $productId);
        exit;
    }

    $uploadDir = '../../../uploads/products/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $baseName = preg_replace('/[^A-Za-z0-9_\-]/', '-', $baseName);
    $baseName = trim($baseName, '-');

    if ($baseName === '') {
        $baseName = 'product-image';
    }

    $newFileName = $baseName . '.' . $extension;
    $targetPath = $uploadDir . $newFileName;

    $counter = 1;

    while (file_exists($targetPath)) {
        $newFileName = $baseName . ' copy ' . $counter . '.' . $extension;
        $targetPath = $uploadDir . $newFileName;
        $counter++;
    }

    if (!move_uploaded_file($tmpName, $targetPath)) {
        $_SESSION['product_edit_error'] = 'Product updated, but image could not be saved.';
        header('Location: /NextPickStore/roles/admin/manage_products/edit_product.php?id=' . $productId);
        exit;
    }

    $dbImagePath = 'uploads/products/' . $newFileName;

    /* Check if product already has primary image */
    $checkSql = "
        SELECT image_id
        FROM nps_product_images
        WHERE product_id = ? AND is_primary = 1
        LIMIT 1
    ";

    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "i", $productId);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $existingImage = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);

    if ($existingImage) {
        $updateImageSql = "
            UPDATE nps_product_images
            SET image_path = ?
            WHERE image_id = ?
            LIMIT 1
        ";

        $updateImageStmt = mysqli_prepare($conn, $updateImageSql);
        mysqli_stmt_bind_param($updateImageStmt, "si", $dbImagePath, $existingImage['image_id']);
        mysqli_stmt_execute($updateImageStmt);
        mysqli_stmt_close($updateImageStmt);
    } else {
        $insertImageSql = "
            INSERT INTO nps_product_images (product_id, image_path, is_primary)
            VALUES (?, ?, 1)
        ";

        $insertImageStmt = mysqli_prepare($conn, $insertImageSql);
        mysqli_stmt_bind_param($insertImageStmt, "is", $productId, $dbImagePath);
        mysqli_stmt_execute($insertImageStmt);
        mysqli_stmt_close($insertImageStmt);
    }
}

$_SESSION['product_edit_success'] = 'Product updated successfully.';
header('Location: /NextPickStore/roles/admin/manage_products/edit_product.php?id=' . $productId);
exit;