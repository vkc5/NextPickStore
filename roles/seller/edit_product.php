<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId = $_SESSION['user_id'] ?? 0;
$sellerName = $_SESSION['full_name'] ?? 'Seller';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid product ID.");
}

$productId = (int) $_GET['id'];

$success = "";
$error = "";
$product = null;
$categories = [];

/* =========================
   LOAD CATEGORIES
========================= */
$catSql = "SELECT category_id, category_name FROM nps_categories ORDER BY category_name ASC";
$catResult = mysqli_query($conn, $catSql);
if ($catResult) {
    while ($row = mysqli_fetch_assoc($catResult)) {
        $categories[] = $row;
    }
}

/* =========================
   LOAD PRODUCT
========================= */
$sql = "
    SELECT
        p.product_id,
        p.product_name,
        p.short_description,
        p.full_description,
        p.price,
        p.stock_quantity,
        p.brand,
        p.category_id,
        p.publish_status,
        pi.image_path
    FROM nps_products p
    LEFT JOIN nps_product_images pi
        ON p.product_id = pi.product_id AND pi.is_primary = 1
    WHERE p.product_id = ?
      AND p.seller_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $productId, $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$product) {
    die("Product not found or you do not have permission to edit it.");
}

/* =========================
   UPDATE PRODUCT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName       = trim($_POST['product_name'] ?? '');
    $shortDescription  = trim($_POST['short_description'] ?? '');
    $fullDescription   = trim($_POST['full_description'] ?? '');
    $price             = trim($_POST['price'] ?? '');
    $stockQuantity     = trim($_POST['stock_quantity'] ?? '');
    $brand             = trim($_POST['brand'] ?? '');
    $categoryId        = trim($_POST['category_id'] ?? '');
    $publishStatus     = trim($_POST['publish_status'] ?? '');
    $currentImagePath  = $product['image_path'] ?? '';

    if ($productName === '' || $shortDescription === '' || $fullDescription === '' || $price === '' || $stockQuantity === '' || $categoryId === '' || $publishStatus === '') {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || $price < 0) {
        $error = "Price must be a valid positive number.";
    } elseif (!is_numeric($stockQuantity) || $stockQuantity < 0) {
        $error = "Stock quantity must be a valid positive number.";
    } elseif (!in_array($publishStatus, ['draft', 'published', 'hidden'], true)) {
        $error = "Invalid publish status selected.";
    } else {
        $newImagePath = $currentImagePath;

        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
            $uploadDir = "../../uploads/products/";
            $originalName = basename($_FILES["product_image"]["name"]);
            $fileName = time() . "_" . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $originalName);
            $targetFile = $uploadDir . $fileName;
            $dbPath = "uploads/products/" . $fileName;

            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileType = mime_content_type($_FILES['product_image']['tmp_name']);
            $fileSize = (int)($_FILES['product_image']['size'] ?? 0);

            if ($fileSize > 5 * 1024 * 1024) {
                $error = "Image must be 5MB or smaller.";
            } elseif (!in_array($fileType, $allowedTypes)) {
                $error = "Only JPG, PNG, and WEBP images are allowed.";
            } else {
                if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $targetFile)) {
                    $newImagePath = $dbPath;
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }

        if ($error === "") {
            $updateSql = "
                UPDATE nps_products
                SET product_name = ?,
                    short_description = ?,
                    full_description = ?,
                    price = ?,
                    stock_quantity = ?,
                    brand = ?,
                    category_id = ?,
                    publish_status = ?,
                    updated_at = NOW()
                WHERE product_id = ?
                  AND seller_id = ?
            ";

            $stmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param(
                $stmt,
                "sssdisisii",
                $productName,
                $shortDescription,
                $fullDescription,
                $price,
                $stockQuantity,
                $brand,
                $categoryId,
                $publishStatus,
                $productId,
                $sellerId
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                if ($newImagePath !== $currentImagePath) {
                    $checkImgSql = "SELECT image_id FROM nps_product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1";
                    $stmt = mysqli_prepare($conn, $checkImgSql);
                    mysqli_stmt_bind_param($stmt, "i", $productId);
                    mysqli_stmt_execute($stmt);
                    $imgResult = mysqli_stmt_get_result($stmt);
                    $existingImg = mysqli_fetch_assoc($imgResult);
                    mysqli_stmt_close($stmt);

                    if ($existingImg) {
                        $updateImgSql = "UPDATE nps_product_images SET image_path = ? WHERE image_id = ?";
                        $stmt = mysqli_prepare($conn, $updateImgSql);
                        mysqli_stmt_bind_param($stmt, "si", $newImagePath, $existingImg['image_id']);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    } else {
                        $insertImgSql = "INSERT INTO nps_product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)";
                        $stmt = mysqli_prepare($conn, $insertImgSql);
                        mysqli_stmt_bind_param($stmt, "is", $productId, $newImagePath);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }

                header("Location: my_products.php?updated=1");
                exit();
            } else {
                $error = "Failed to update product.";
                mysqli_stmt_close($stmt);
            }
        }
    }

    $product['product_name'] = $productName;
    $product['short_description'] = $shortDescription;
    $product['full_description'] = $fullDescription;
    $product['price'] = $price;
    $product['stock_quantity'] = $stockQuantity;
    $product['brand'] = $brand;
    $product['category_id'] = $categoryId;
    $product['publish_status'] = $publishStatus;
    $product['image_path'] = $newImagePath ?? $product['image_path'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - NextPick</title>
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
        }

        .topbar {
            padding: 18px 28px;
            border-bottom: 1px solid #ececec;
            background: #fafafa;
        }

        .topbar img {
            height: 34px;
            display: block;
        }

        .main-layout {
            display: flex;
            min-height: 700px;
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
            gap: 12px;
            min-height: 48px;
            padding: 12px 14px;
            border-radius: 12px;
            color: #111827;
            font-size: 15px;
            font-weight: 500;
            transition: 0.2s ease;
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

        .menu-icon-img {
            width: 18px;
            height: 18px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .content {
            flex: 1;
            padding: 28px;
            background: #fcfcfc;
            border-radius: 14px;
            min-width: 0;
        }

        .page-title-box {
            margin-bottom: 22px;
        }

        .page-title-box h1 {
            font-size: 32px;
            margin-bottom: 6px;
        }

        .page-title-box p {
            color: #666;
            font-size: 14px;
        }

        .form-card {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-radius: 14px;
            padding: 22px;
        }

        .message {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .message.error {
            background: #ffe8e8;
            color: #b42318;
            border: 1px solid #f5b5b5;
        }

        .product-preview {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 22px;
            padding-bottom: 18px;
            border-bottom: 1px solid #ececec;
        }

        .product-preview img {
            width: 84px;
            height: 84px;
            border-radius: 14px;
            object-fit: cover;
            border: 1px solid #ececec;
            background: #fafafa;
        }

        .product-preview h3 {
            font-size: 20px;
            margin-bottom: 6px;
        }

        .product-preview p {
            color: #666;
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #d9d9df;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            background: #fff;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 22px;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            background: #3158ff;
            color: #fff;
        }

        .btn-light {
            background: #eef2ff;
            color: #3158ff;
        }

        .footer {
            border-top: 1px solid #ececec;
            background: #fff;
            margin-top: 24px;
        }

        .footer-top {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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

        .newsletter input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #3158ff;
            border-radius: 8px;
            outline: none;
            margin-top: 8px;
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

        @media (max-width: 900px) {
            .main-layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #ececec;
            }

            .form-grid,
            .footer-top {
                grid-template-columns: 1fr;
            }
        }

        /* SELLER ADMIN-LIKE TOPBAR OVERRIDES */
        .topbar {
            background: #fff;
            border-bottom: none;
            border-radius: 10px;
            padding: 16px 22px;
            margin-bottom: 5px;
        }

        .topbar > img {
            height: 24px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        .menu-icon-img {
            width: 20px;
            height: 20px;
            object-fit: contain;
            flex-shrink: 0;
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <div class="topbar">
        <img src="/NextPickStore/assets/images/Logos/nextpickstore-logo.png"

    </div>

    <div class="main-layout">
        <aside class="sidebar">

            <ul class="menu">
    <li><a href="dashboard.php"><img src="../../assets/images/icons/admin/home.png" alt="" class="menu-icon-img"><span>Dashboard</span></a></li>

    <li><a href="my_products.php"><img src="../../assets/images/icons/admin/box.png" alt="" class="menu-icon-img"><span>Inventory Management</span></a></li>

    <li><a href="add_product.php"><img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img"><span>Add Product</span></a></li>

    <li><a href="orders.php"><img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img"><span>Orders</span></a></li>

    <li><a href="customer_data.php"><img src="../../assets/images/icons/admin/users.png" alt="" class="menu-icon-img"><span>Customer Data</span></a></li>

    <li><a href="reports.php"><img src="../../assets/images/icons/admin/report.png" alt="" class="menu-icon-img"><span>Analytics & Reports</span></a></li>

    <li><a href="help_center.php"><img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img"><span>Help Center</span></a></li>

    <li><a href="profile.php"><img src="../../assets/images/icons/admin/profile.png" alt="" class="menu-icon-img"><span>My Profile</span></a></li>

    <li><a href="../../auth/logout.php"><img src="../../assets/images/icons/admin/logout.png" alt="" class="menu-icon-img"><span>Log out</span></a></li>
</ul>
        </aside>

        <main class="content">
            <div class="page-title-box">
                <h1>Edit Product</h1>
                <p>Update your product details, stock, and image.</p>
            </div>

            <div class="form-card">
                <?php if ($error !== "") { ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php } ?>

                <div class="product-preview">
                    <img src="/NextPickStore/<?php echo !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'uploads/products/view.png'; ?>" alt="Product">
                    <div>
                        <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        <p>Current brand: <?php echo htmlspecialchars($product['brand'] ?: '-'); ?></p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="sellerProductForm" novalidate>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Product Name</label>
                            <input type="text" name="product_name" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Brand</label>
                            <input type="text" name="brand" value="<?php echo htmlspecialchars($product['brand']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category) { ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo ((int)$product['category_id'] === (int)$category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Publish Status</label>
                            <select name="publish_status" required>
                                <option value="draft" <?php echo ($product['publish_status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo ($product['publish_status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                <option value="hidden" <?php echo ($product['publish_status'] === 'hidden') ? 'selected' : ''; ?>>Hidden</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Price</label>
                            <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Stock Quantity</label>
                            <input type="number" name="stock_quantity" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" required>
                        </div>

                        <div class="form-group full">
                            <label>Short Description</label>
                            <input type="text" name="short_description" value="<?php echo htmlspecialchars($product['short_description']); ?>" required>
                        </div>

                        <div class="form-group full">
                            <label>Full Description</label>
                            <textarea name="full_description" required><?php echo htmlspecialchars($product['full_description']); ?></textarea>
                        </div>

                        <div class="form-group full">
                            <label>Replace Product Image</label>
                            <input type="file" name="product_image" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="my_products.php" class="btn btn-light">Back to Inventory</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <?php include_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
</div>

<script>
document.getElementById('sellerProductForm').addEventListener('submit', function (event) {
    const requiredFields = ['product_name', 'category_id', 'publish_status', 'price', 'stock_quantity', 'short_description', 'full_description'];
    let valid = true;

    requiredFields.forEach(function (name) {
        const field = document.querySelector('[name="' + name + '"]');
        if (!field || field.value.trim() === '') {
            valid = false;
            if (field) field.focus();
        }
    });

    const price = document.querySelector('[name="price"]');
    const stock = document.querySelector('[name="stock_quantity"]');
    if (price && (price.value === '' || Number(price.value) < 0)) valid = false;
    if (stock && (stock.value === '' || Number(stock.value) < 0)) valid = false;

    if (!valid) {
        event.preventDefault();
        alert('Please fill all required product fields correctly.');
    }
});
</script>
</body>
</html>
