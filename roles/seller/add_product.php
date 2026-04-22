<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'] ?? 0;
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

$success = "";
$error = "";

$productName = "";
$shortDescription = "";
$fullDescription = "";
$price = "";
$stockQuantity = "";
$brand = "";
$categoryId = "";
$publishStatus = "draft";

$categories = [];

$catSql = "SELECT category_id, category_name FROM nps_categories ORDER BY category_name ASC";
$catResult = mysqli_query($conn, $catSql);
if ($catResult) {
    while ($row = mysqli_fetch_assoc($catResult)) {
        $categories[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = trim($_POST['product_name'] ?? '');
    $shortDescription = trim($_POST['short_description'] ?? '');
    $fullDescription = trim($_POST['full_description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $stockQuantity = trim($_POST['stock_quantity'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $categoryId = trim($_POST['category_id'] ?? '');
    $publishStatus = trim($_POST['publish_status'] ?? 'draft');

    if (
        $productName === '' ||
        $shortDescription === '' ||
        $fullDescription === '' ||
        $price === '' ||
        $stockQuantity === '' ||
        $categoryId === ''
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || $price < 0) {
        $error = "Price must be a valid positive number.";
    } elseif (!is_numeric($stockQuantity) || $stockQuantity < 0) {
        $error = "Stock quantity must be a valid positive number.";
    } elseif (!in_array($publishStatus, ['draft', 'published', 'hidden'])) {
        $error = "Invalid publish status selected.";
    } else {
        $insertSql = "
            INSERT INTO nps_products
            (seller_id, category_id, product_name, short_description, full_description, price, stock_quantity, publish_status, brand, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $stmt = mysqli_prepare($conn, $insertSql);
        mysqli_stmt_bind_param(
            $stmt,
            "iisssdiss",
            $sellerId,
            $categoryId,
            $productName,
            $shortDescription,
            $fullDescription,
            $price,
            $stockQuantity,
            $publishStatus,
            $brand
        );

        if (mysqli_stmt_execute($stmt)) {
            $newProductId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
                $uploadDir = "../../uploads/products/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $originalName = basename($_FILES["product_image"]["name"]);
                $fileName = time() . "_" . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $originalName);
                $targetFile = $uploadDir . $fileName;
                $dbPath = "uploads/products/" . $fileName;

                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                $fileType = mime_content_type($_FILES['product_image']['tmp_name']);

                if (!in_array($fileType, $allowedTypes)) {
                    $error = "Product saved, but image type is invalid. Only JPG, PNG, and WEBP are allowed.";
                } else {
                    if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $targetFile)) {
                        $imgSql = "INSERT INTO nps_product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)";
                        $stmt = mysqli_prepare($conn, $imgSql);
                        mysqli_stmt_bind_param($stmt, "is", $newProductId, $dbPath);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    } else {
                        $error = "Product saved, but image upload failed.";
                    }
                }
            }

            if ($error === "") {
                header("Location: /NextPickStore/roles/seller/my_products.php?added=1");
                exit();
            }
        } else {
            $error = "Failed to save product.";
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - NextPick</title>
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
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar img {
            height: 34px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .seller-badge {
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
            min-width: 0;
        }

        .page-title-box {
            margin-bottom: 22px;
        }

        .page-title-box h1 {
            font-size: 32px;
            margin-bottom: 6px;
            color: #111827;
        }

        .page-title-box p {
            color: #667085;
            font-size: 15px;
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

        .form-layout {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 18px;
            align-items: start;
        }

        .card {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-radius: 18px;
            padding: 20px;
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding-bottom: 18px;
            border-bottom: 1px solid #eceff3;
            margin-bottom: 18px;
        }

        .card-header h3 {
            font-size: 18px;
            margin-bottom: 4px;
            color: #111827;
        }

        .card-header p {
            font-size: 14px;
            color: #667085;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 18px;
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
            color: #222;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d9d9df;
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            background: #fff;
            color: #222;
        }

        input::placeholder,
        textarea::placeholder {
            color: #98a2b3;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .upload-box {
            border: 1px dashed #cfd6e4;
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            background: #fafbff;
        }

        .upload-big-icon {
            width: 54px;
            height: 54px;
            margin: 0 auto 12px;
            border-radius: 50%;
            border: 2px solid #3158ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: #fff;
        }

        .upload-box p {
            color: #667085;
            font-size: 13px;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .upload-box input[type="file"] {
            padding: 10px;
            background: #fff;
        }

        .publish-title {
            margin: 16px 0 12px;
            font-size: 15px;
            font-weight: 700;
            color: #222;
        }

        .publish-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .publish-card {
            border: 1px solid #d9d9df;
            border-radius: 12px;
            padding: 12px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #fff;
        }

        .publish-card input {
            width: auto;
            margin-top: 2px;
        }

        .publish-card strong {
            display: block;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .publish-card span {
            font-size: 12px;
            color: #667085;
        }

        .info-note {
            margin-top: 14px;
            background: #f3f6fb;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            color: #667085;
        }

        .tips-card {
            margin-top: 18px;
            background: #f7f9ff;
            border: 1px solid #e6ecff;
            border-radius: 16px;
            padding: 22px 20px;
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .tips-illustration {
            width: 120px;
            min-width: 120px;
            text-align: center;
            font-size: 58px;
            color: #3158ff;
        }

        .tips-content h3 {
            font-size: 18px;
            margin-bottom: 12px;
            color: #111827;
        }

        .tips-content ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .tips-content li {
            font-size: 14px;
            color: #222;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tips-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #2156e6;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            flex-shrink: 0;
        }

        .side-actions {
            display: flex;
            gap: 12px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 12px;
            padding: 13px 20px;
            font-size: 15px;
            cursor: pointer;
            font-weight: 600;
            text-align: center;
        }

        .btn-primary {
            background: #2156e6;
            color: #fff;
        }

        .btn-light {
            background: #fff;
            color: #222;
            border: 1px solid #d9d9df;
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
            border: 1px solid #d0d5dd;
            border-radius: 10px;
            outline: none;
            margin-top: 8px;
        }

        .socials {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            font-size: 22px;
            color: #667085;
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

        @media (max-width: 1200px) {
            .form-layout,
            .footer-top {
                grid-template-columns: 1fr;
            }

            .publish-options {
                grid-template-columns: 1fr;
            }
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .tips-card {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 640px) {
            .page-title-box h1 {
                font-size: 26px;
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
        <img src="/NextPickStore/assets/images/logos/logo.png" alt="NextPick Logo">

        <div class="topbar-right">
            <div class="seller-badge"><?php echo strtoupper(substr($sellerName, 0, 1)); ?></div>
            <div class="topbar-user">
                <strong><?php echo htmlspecialchars($sellerName); ?></strong>
                <span>Seller</span>
            </div>
        </div>
    </div>

    <div class="main-layout">
        <aside class="sidebar">
            <h2>Welcome,<br><?php echo htmlspecialchars($firstName); ?></h2>

            <ul class="menu">
                <li><a href="/NextPickStore/roles/seller/dashboard.php"><img src="../../assets/images/icons/seller icon/dashboard.png" alt="" class="menu-icon-img"><span>Dashboard</span></a></li>
                <li><a href="/NextPickStore/roles/seller/my_products.php"><img src="../../assets/images/icons/seller icon/inventory-management.png" alt="" class="menu-icon-img"><span>Inventory Management</span></a></li>
                <li><a href="/NextPickStore/roles/seller/add_product.php" class="active"><img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img"><span>Add Product</span></a></li>
                <li><a href="/NextPickStore/roles/buyer/my_orders.php"><img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img"><span>Orders</span></a></li>
                <li><a href="#"><img src="../../assets/images/icons/seller icon/client.png" alt="" class="menu-icon-img"><span>Customer Data</span></a></li>
                <li><a href="/NextPickStore/roles/seller/reports.php"><img src="../../assets/images/icons/seller icon/seo-report.png" alt="" class="menu-icon-img"><span>Analytics & Reports</span></a></li>
                <li><a href="#"><img src="../../assets/images/icons/seller icon/settings.png" alt="" class="menu-icon-img"><span>Settings</span></a></li>
                <li><a href="#"><img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img"><span>Help Center</span></a></li>
                <li><a href="/NextPickStore/auth/logout.php"><img src="../../assets/images/icons/seller icon/logout.png" alt="" class="menu-icon-img"><span>Log out</span></a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="page-title-box">
                <h1>Add Product</h1>
                <p>Fill in the details below to create a new product.</p>
            </div>

            <?php if ($error !== "") { ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-layout">
                    <div>
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <h3>Product Information</h3>
                                    <p>Add the basic details of your product.</p>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Product Name</label>
                                    <input type="text" name="product_name" placeholder="Enter product name" value="<?php echo htmlspecialchars($productName); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Brand</label>
                                    <input type="text" name="brand" placeholder="Enter brand name (optional)" value="<?php echo htmlspecialchars($brand); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category_id" required>
                                        <option value="">Select category</option>
                                        <?php foreach ($categories as $category) { ?>
                                            <option value="<?php echo $category['category_id']; ?>" <?php echo ((string)$categoryId === (string)$category['category_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Price (BHD)</label>
                                    <input type="number" step="0.01" min="0" name="price" placeholder="0.000" value="<?php echo htmlspecialchars($price); ?>" required>
                                </div>

                                <div class="form-group full">
                                    <label>Stock Quantity</label>
                                    <input type="number" min="0" name="stock_quantity" placeholder="Enter stock quantity" value="<?php echo htmlspecialchars($stockQuantity); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="tips-card">
                            <div class="tips-illustration">📦</div>
                            <div class="tips-content">
                                <h3>Tips for Better Results</h3>
                                <ul>
                                    <li><span class="tips-dot">✓</span>Use clear and descriptive product names</li>
                                    <li><span class="tips-dot">✓</span>Add high quality images for better visibility</li>
                                    <li><span class="tips-dot">✓</span>Provide detailed descriptions to build trust</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <h3>Description & Media</h3>
                                    <p>Provide more information and upload media.</p>
                                </div>
                            </div>

                            <div class="form-group full">
                                <label>Short Description</label>
                                <input type="text" name="short_description" maxlength="255" placeholder="Enter short description (max 250 characters)" value="<?php echo htmlspecialchars($shortDescription); ?>" required>
                            </div>

                            <div class="form-group full" style="margin-top:16px;">
                                <label>Full Description</label>
                                <textarea name="full_description" placeholder="Enter full description of the product" required><?php echo htmlspecialchars($fullDescription); ?></textarea>
                            </div>

                            <div class="form-group full" style="margin-top:16px;">
                                <label>Product Image</label>
                                <div class="upload-box">
                                    <div class="upload-big-icon">↑</div>
                                    <p>Drag & drop an image here, or browse to upload.<br>JPG, PNG, WEBP up to 5MB</p>
                                    <input type="file" name="product_image" accept=".jpg,.jpeg,.png,.webp">
                                </div>
                            </div>

                            <div class="publish-title">Publish Status</div>

                            <div class="publish-options">
                                <label class="publish-card">
                                    <input type="radio" name="publish_status" value="draft" <?php echo ($publishStatus === 'draft') ? 'checked' : ''; ?>>
                                    <div>
                                        <strong>Draft</strong>
                                        <span>Save as draft</span>
                                    </div>
                                </label>

                                <label class="publish-card">
                                    <input type="radio" name="publish_status" value="published" <?php echo ($publishStatus === 'published') ? 'checked' : ''; ?>>
                                    <div>
                                        <strong>Published</strong>
                                        <span>Make it live</span>
                                    </div>
                                </label>

                                <label class="publish-card">
                                    <input type="radio" name="publish_status" value="hidden" <?php echo ($publishStatus === 'hidden') ? 'checked' : ''; ?>>
                                    <div>
                                        <strong>Hidden</strong>
                                        <span>Keep it hidden</span>
                                    </div>
                                </label>
                            </div>

                            <div class="info-note">You can change the publish status anytime later.</div>

                            <div class="side-actions">
                                <button type="submit" class="btn btn-primary">Save Product</button>
                                <a href="/NextPickStore/roles/seller/my_products.php" class="btn btn-light">Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
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

            <div class="newsletter">
                <h4>Sign up for exclusive offers and the latest news!</h4>
                <input type="email" placeholder="Your email...">
                <div class="socials">
                </div>
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

</body>
</html>