<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'];
$sellerName = $_SESSION['full_name'] ?? 'Seller';

/* =========================
   FILTERS
========================= */
$categoryFilter = trim($_GET['category'] ?? '');
$brandFilter    = trim($_GET['brand'] ?? '');
$statusFilter   = trim($_GET['stock_status'] ?? '');
$search         = trim($_GET['search'] ?? '');

/* =========================
   SUMMARY CARDS
========================= */
$totalStockValue = 0;
$uniqueProducts = 0;
$itemsBelowThreshold = 0;

// total stock value
$sql = "
    SELECT IFNULL(SUM(price * stock_quantity), 0) AS total_stock_value
    FROM nps_products
    WHERE seller_id = ?
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $totalStockValue = $row['total_stock_value'];
}
mysqli_stmt_close($stmt);

// unique products
$sql = "
    SELECT COUNT(*) AS total_products
    FROM nps_products
    WHERE seller_id = ?
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $uniqueProducts = $row['total_products'];
}
mysqli_stmt_close($stmt);

// below threshold
$sql = "
    SELECT COUNT(*) AS low_items
    FROM nps_products
    WHERE seller_id = ?
      AND stock_quantity <= 10
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $itemsBelowThreshold = $row['low_items'];
}
mysqli_stmt_close($stmt);

/* =========================
   DROPDOWNS
========================= */
$categories = [];
$sql = "
    SELECT DISTINCT c.category_name
    FROM nps_products p
    INNER JOIN nps_categories c ON p.category_id = c.category_id
    WHERE p.seller_id = ?
    ORDER BY c.category_name ASC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row['category_name'];
}
mysqli_stmt_close($stmt);

$brands = [];
$sql = "
    SELECT DISTINCT brand
    FROM nps_products
    WHERE seller_id = ?
      AND brand IS NOT NULL
      AND brand <> ''
    ORDER BY brand ASC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $brands[] = $row['brand'];
}
mysqli_stmt_close($stmt);

/* =========================
   PRODUCT TABLE
========================= */
$products = [];

$sql = "
    SELECT 
        p.product_id,
        p.product_name,
        p.brand,
        c.category_name,
        p.price,
        p.stock_quantity,
        pi.image_path
    FROM nps_products p
    INNER JOIN nps_categories c ON p.category_id = c.category_id
    LEFT JOIN nps_product_images pi
        ON p.product_id = pi.product_id AND pi.is_primary = 1
    WHERE p.seller_id = ?
";

$types = "i";
$params = [$sellerId];

if ($categoryFilter !== '') {
    $sql .= " AND c.category_name = ?";
    $types .= "s";
    $params[] = $categoryFilter;
}

if ($brandFilter !== '') {
    $sql .= " AND p.brand = ?";
    $types .= "s";
    $params[] = $brandFilter;
}

if ($statusFilter === 'in_stock') {
    $sql .= " AND p.stock_quantity > 10";
}
elseif ($statusFilter === 'low_stock') {
    $sql .= " AND p.stock_quantity BETWEEN 1 AND 10";
}
elseif ($statusFilter === 'out_of_stock') {
    $sql .= " AND p.stock_quantity = 0";
}

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.brand LIKE ? OR c.category_name LIKE ?)";
    $searchValue = "%" . $search . "%";
    $types .= "sss";
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - NextPick</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            width: auto;
            object-fit: contain;
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

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 18px;
        }

        .summary-card .label {
            color: #555;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .summary-card .value {
            font-size: 22px;
            font-weight: 700;
            color: #333;
        }

        .summary-card .danger {
            color: #d82121;
        }

        .inventory-card {
            background: #fff;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 16px;
        }

        .filter-row {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .filter-left {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-right {
            min-width: 240px;
        }

        .filter-row select,
        .filter-row input {
            padding: 11px 12px;
            border: 1px solid #d9d9df;
            border-radius: 10px;
            background: #fff;
            font-size: 14px;
            outline: none;
        }

        .filter-row select {
            min-width: 150px;
        }

        .filter-right input {
            width: 100%;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
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

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            font-size: 13px;
            color: #555;
            background: #f3f4f6;
            padding: 12px 10px;
            font-weight: 600;
        }

        td {
            padding: 12px 10px;
            border-top: 1px solid #f0f0f0;
            font-size: 14px;
            vertical-align: middle;
        }

        .thumb {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #ececec;
            background: #fafafa;
        }

        .product-name {
            font-weight: 600;
            color: #222;
        }

        .sku-text {
            color: #666;
            font-size: 13px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .in-stock {
            background: #e7faed;
            color: #1b8a46;
        }

        .low-stock {
            background: #fff5d6;
            color: #9c6b00;
        }

        .out-stock {
            background: #ffe8e8;
            color: #b42318;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .table-btn {
            padding: 7px 12px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #3158ff;
            background: #fff;
            color: #3158ff;
            cursor: pointer;
        }

        .table-btn.delete {
            border-color: #d82121;
            color: #d82121;
        }

        .empty-box {
            padding: 30px 10px;
            text-align: center;
            color: #666;
            font-size: 14px;
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
    align-items: start;
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

/* added only for help center box */
.seller-footer-box {
    background: #fff;
    border: none; /* شلنا البوكس */
    border-radius: 12px;
    padding: 18px;
}

.btn-help {
    display: inline-block;
    padding: 10px 16px;
    background: #3158ff;
    color: #fff; /* النص أبيض */
    border-radius: 8px;
    font-size: 13px;
}

/* شيلنا الهوفر بالكامل */
.btn-help:hover {
    background: #3158ff;
    color: #fff;
}

.social-icons {
    margin-top: 10px;
}

.social-icons a {
    margin-right: 10px;
    font-size: 15px;
    color: #555;
}

.social-icons a:hover {
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

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .cards-row,
    .bottom-row,
    .footer-top {
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
}

@media (max-width: 640px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .content-header h1 {
        font-size: 24px;
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
        <img src="../../assets/images/logos/logo.png" alt="NextPick Logo">
    </div>

    <div class="main-layout">
        <aside class="sidebar">
            <h2>Welcome,<br><?php echo htmlspecialchars(explode(' ', $sellerName)[0]); ?></h2>

            <ul class="menu">
                <li>
                    <a href="dashboard.php">
                        <img src="../../assets/images/icons/seller icon/dashboard.png" alt="Dashboard" class="menu-icon-img">
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="my_products.php" class="active">
                        <img src="../../assets/images/icons/seller icon/inventory-management.png" alt="Inventory Management" class="menu-icon-img">
                        <span>Inventory Management</span>
                    </a>
                </li>

                <li>
                    <a href="add_product.php">
                        <img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="Add Product" class="menu-icon-img">
                        <span>Add Product</span>
                    </a>
                </li>

                <li>
                    <a href="../buyer/my_orders.php">
                        <img src="../../assets/images/icons/seller icon/manifest.png" alt="Orders" class="menu-icon-img">
                        <span>Orders</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <img src="../../assets/images/icons/seller icon/client.png" alt="Customer Data" class="menu-icon-img">
                        <span>Customer Data</span>
                    </a>
                </li>

                <li>
                   <a href="/NextPickStore/roles/seller/reports.php">
    <img src="../../assets/images/icons/seller icon/seo-report.png" alt="Analytics & Reports" class="menu-icon-img">
    <span>Analytics & Reports</span>
</a>
                </li>

                <li>
                    <a href="#">
                        <img src="../../assets/images/icons/seller icon/settings.png" alt="Settings" class="menu-icon-img">
                        <span>Settings</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <img src="../../assets/images/icons/seller icon/customer-support.png" alt="Help Center" class="menu-icon-img">
                        <span>Help Center</span>
                    </a>
                </li>

                <li>
                    <a href="../../auth/logout.php">
                        <img src="../../assets/images/icons/seller icon/logout.png" alt="Log out" class="menu-icon-img">
                        <span>Log out</span>
                    </a>
                </li>
            </ul>
        </aside>

      <main class="content">
    <div class="page-title-box">
        <h1>Inventory Management</h1>
        <p>Track your products, stock levels, and manage your product list.</p>
    </div>

    <?php if (isset($_GET['deleted']) || isset($_GET['error'])): ?>
        <div class="flash-messages">
            <?php if (isset($_GET['deleted'])): ?>
                <div class="flash-message success">Product deleted successfully.</div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'linked_orders'): ?>
                <div class="flash-message error">This product cannot be deleted because it is linked to existing orders.</div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'notfound'): ?>
                <div class="flash-message error">Product not found.</div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'delete_failed'): ?>
                <div class="flash-message error">Failed to delete product.</div>
            <?php endif; ?>
        </div>

        <script>
            setTimeout(function () {
                if (window.history.replaceState) {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            }, 2000);
        </script>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Total Stock Value</div>
            <div class="value">$<?php echo number_format($totalStockValue, 2); ?></div>
        </div>

        <div class="summary-card">
            <div class="label">Unique Products</div>
            <div class="value"><?php echo $uniqueProducts; ?></div>
        </div>

        <div class="summary-card">
            <div class="label">Items Below Threshold</div>
            <div class="value danger"><?php echo $itemsBelowThreshold; ?></div>
        </div>
    </div>

    <div class="inventory-card">
        <form method="GET">
            <div class="filter-row">
                <div class="filter-left">
                    <select name="category">
                        <option value="">Category</option>
                        <?php foreach ($categories as $category) { ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($categoryFilter === $category) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <select name="brand">
                        <option value="">Brand</option>
                        <?php foreach ($brands as $brand) { ?>
                            <option value="<?php echo htmlspecialchars($brand); ?>" <?php echo ($brandFilter === $brand) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <select name="stock_status">
                        <option value="">Stock Status</option>
                        <option value="in_stock" <?php echo ($statusFilter === 'in_stock') ? 'selected' : ''; ?>>In Stock</option>
                        <option value="low_stock" <?php echo ($statusFilter === 'low_stock') ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out_of_stock" <?php echo ($statusFilter === 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>

                <div class="filter-right">
                    <input type="text" name="search" placeholder="Quick Search" value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="my_products.php" class="btn btn-light">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product Thumbnail</th>
                        <th>Product Name</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock Quantity</th>
                        <th>Low Stock Threshold</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)) { ?>
                        <?php foreach ($products as $product) { ?>
                            <?php
                                $stockQty = (int)$product['stock_quantity'];

                                if ($stockQty == 0) {
                                    $statusClass = 'out-stock';
                                    $statusText = 'Out of Stock';
                                } elseif ($stockQty <= 10) {
                                    $statusClass = 'low-stock';
                                    $statusText = 'Low Stock';
                                } else {
                                    $statusClass = 'in-stock';
                                    $statusText = 'In Stock';
                                }
                            ?>
                            <tr>
                                <td>
                                    <img 
                                        src="../../<?php echo !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'assets/images/products/default.png'; ?>" 
                                        alt="Product" 
                                        class="thumb"
                                    >
                                </td>
                                <td class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td class="sku-text"><?php echo htmlspecialchars($product['brand'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo $stockQty; ?></td>
                                <td>10</td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="table-btn">Edit</a>
                                        <a href="delete_product.php?id=<?php echo $product['product_id']; ?>" class="table-btn delete" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-box">No products found for the selected filters.</div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
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

        <!-- YOUR BOX (UNCHANGED STYLE, JUST FIXED POSITION) -->
        <div class="seller-footer-box">
            
            
            
            
           

            <div class="social-box">
                <span>Follow us</span>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>

    </div> <!-- ✅ THIS WAS MISSING -->

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