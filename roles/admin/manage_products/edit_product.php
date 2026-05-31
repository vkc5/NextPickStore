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

$success = $_SESSION['product_edit_success'] ?? '';
$error = $_SESSION['product_edit_error'] ?? '';
unset($_SESSION['product_edit_success'], $_SESSION['product_edit_error']);

$productSql = "
    SELECT
        p.*,
        c.category_name,
        img.image_path
    FROM nps_products p
    INNER JOIN nps_categories c ON p.category_id = c.category_id
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

$categories = [];
$catResult = mysqli_query($conn, "SELECT category_id, category_name FROM nps_categories ORDER BY category_name ASC");
if ($catResult) {
    while ($row = mysqli_fetch_assoc($catResult)) {
        $categories[] = $row;
    }
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
                box-shadow: 0 6px 16px rgba(33,85,245,0.10);
                transform: translateX(3px);
            }

            .nav-link:hover img {
                transform: scale(1.08);
            }

            .nav-link.active {
                background: #eef3ff;
                color: #2155f5;
                font-weight: 600;
                box-shadow: 0 6px 16px rgba(33,85,245,0.10);
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
                margin-bottom: 24px;
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

            .message-box {
                padding: 12px 14px;
                border-radius: 10px;
                margin-bottom: 18px;
                font-size: 14px;
            }

            .success-box {
                background: #eafaf1;
                border: 1px solid #b8e7c8;
                color: #1a7f4b;
            }

            .error-box {
                background: #fff1f0;
                border: 1px solid #f5b7b1;
                color: #c0392b;
            }

            .edit-layout {
                display: grid;
                grid-template-columns: 1.8fr 0.9fr;
                gap: 22px;
                align-items: start;
            }

            .card-box {
                background: #fff;
                border-radius: 18px;
                padding: 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            }

            .card-box h3 {
                font-size: 18px;
                margin-bottom: 8px;
                font-weight: 700;
            }

            .sub-text {
                font-size: 13px;
                color: #777;
                margin-bottom: 16px;
            }

            .field-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }

            .field-group.full-width {
                grid-column: 1 / -1;
            }

            .field-group label {
                display: block;
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 8px;
                color: #444;
            }

            .field-group input,
            .field-group select,
            .field-group textarea {
                width: 100%;
                border: 1px solid #d8dce8;
                border-radius: 10px;
                padding: 12px;
                font-size: 14px;
                outline: none;
                transition: 0.25s ease;
            }

            .field-group textarea {
                min-height: 120px;
                resize: vertical;
            }

            .field-group input:focus,
            .field-group select:focus,
            .field-group textarea:focus {
                border-color: #2155f5;
                box-shadow: 0 0 0 3px rgba(33,85,245,0.10);
            }

            .image-box {
                text-align: center;
                margin-bottom: 18px;
            }

            .image-box img {
                width: 100%;
                max-width: 260px;
                height: 200px;
                object-fit: contain;
                border-radius: 14px;
                background: #f8f8f8;
                border: 1px solid #ececec;
                padding: 10px;
            }

            .side-info {
                margin-bottom: 16px;
            }

            .publish-status-group {
                margin: 20px 0;
            }

            .radio-row {
                display: flex;
                gap: 24px;
                flex-wrap: wrap;
            }

            .radio-item {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
            }

            .radio-item input {
                accent-color: #2155f5;
            }

            .generated-box {
                margin-top: 14px;
                background: #fafafa;
                border: 1px solid #ddd;
                border-radius: 14px;
                padding: 12px 20px;
                text-align: center;
            }

            .generated-box .small-label {
                color: #2155f5;
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 4px;
            }

            .generated-box .id-value {
                font-size: 13px;
                color: #222;
            }

            .submit-btn {
                width: 100%;
                height: 46px;
                border: none;
                border-radius: 10px;
                background: #2155f5;
                color: #fff;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: 0.25s ease;
                margin-top: 14px;
            }

            .submit-btn:hover {
                background: #1747d4;
                box-shadow: 0 10px 22px rgba(33,85,245,0.18);
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

            @media (max-width: 1000px) {
                .edit-layout {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 900px) {
                .main-layout {
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
                .field-grid {
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
                        <a href="view_products.php" class="title-back-link">
                            <span class="back-arrow">←</span>
                            <span>Edit Product</span>
                        </a>
                    </div>

                    <?php if (!empty($success)): ?>
                        <div class="message-box success-box"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="message-box error-box"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form action="process_edit_product.php" method="POST" class="edit-layout" enctype="multipart/form-data">
                        <div class="card-box">
                            <h3>Basic Information</h3>
                            <div class="sub-text">Provide the basic details about your product.</div>

                            <div class="field-grid">
                                <div class="field-group full-width">
                                    <label>Product Name</label>
                                    <input type="text" name="product_name" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                                </div>

                                <div class="field-group full-width">
                                    <label>Short Description</label>
                                    <input type="text" name="short_description" value="<?php echo htmlspecialchars($product['short_description']); ?>" required>
                                </div>

                                <div class="field-group full-width">
                                    <label>Full Description</label>
                                    <textarea name="full_description" required><?php echo htmlspecialchars($product['full_description']); ?></textarea>
                                </div>

                                <div class="field-group">
                                    <label>Price</label>
                                    <input type="number" step="0.01" min="0" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                                </div>

                                <div class="field-group">
                                    <label>Category</label>
                                    <select name="category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo (int) $category['category_id']; ?>" <?php echo ($product['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field-group">
                                    <label>Stock Quantity</label>
                                    <input type="number" min="0" name="stock_quantity" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" required>
                                </div>

                                <div class="field-group">
                                    <label>Brand</label>
                                    <input type="text" name="brand" value="<?php echo htmlspecialchars($product['brand']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="card-box">
                            <h3>Media & Status</h3>
                            <div class="sub-text">Preview the current product image and change publish status.</div>

                            <div class="image-box">
                                <img src="../../../<?php echo htmlspecialchars($product['image_path'] ?: 'assets/images/placeholder.png'); ?>" alt="Product">
                            </div>

                            <div class="side-info">
                                <strong>Current Primary Image</strong>
                            </div>
                            
                            <div class="field-group">
                                <label>Change Product Image</label>
                                <input type="file" name="product_image" accept="image/*">
                                <small style="display:block; margin-top:8px; color:#777;">
                                    Leave empty if you do not want to change the image.
                                </small>
                            </div>
                            
                            <div class="publish-status-group">
                                <label style="display:block; margin-bottom:10px; font-size:14px; font-weight:600;">Publish Status</label>
                                <div class="radio-row">
                                    <label class="radio-item">
                                        <input type="radio" name="publish_status" value="draft" <?php echo ($product['publish_status'] === 'draft') ? 'checked' : ''; ?>>
                                        <span>Draft</span>
                                    </label>

                                    <label class="radio-item">
                                        <input type="radio" name="publish_status" value="published" <?php echo ($product['publish_status'] === 'published') ? 'checked' : ''; ?>>
                                        <span>Published</span>
                                    </label>

                                    <label class="radio-item">
                                        <input type="radio" name="publish_status" value="hidden" <?php echo ($product['publish_status'] === 'hidden') ? 'checked' : ''; ?>>
                                        <span>Hidden</span>
                                    </label>
                                </div>
                            </div>

                            <div class="generated-box">
                                <div class="small-label">Auto Generated ID:</div>
                                <div class="id-value"><?php echo (int) $product['product_id']; ?></div>
                            </div>

                            <input type="hidden" name="product_id" value="<?php echo (int) $product['product_id']; ?>">
                            <button type="submit" class="submit-btn">Save Changes</button>
                        </div>
                    </form>
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
    </body>
</html>