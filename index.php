<?php
include_once __DIR__ . '/includes/session.php';
include_once __DIR__ . '/includes/config.php';

$conn = getConnection();

$search = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);

$categories = [];
$categoryResult = mysqli_query($conn, "SELECT category_id, category_name FROM nps_categories ORDER BY category_name ASC");
if ($categoryResult) {
    while ($row = mysqli_fetch_assoc($categoryResult)) {
        $categories[] = $row;
    }
}

$sql = "
    SELECT
        p.product_id,
        p.product_name,
        p.short_description,
        p.price,
        p.brand,
        c.category_name,
        pi.image_path,
        ROUND(AVG(r.rating_value), 2) AS average_rating
    FROM nps_products p
    INNER JOIN nps_categories c ON p.category_id = c.category_id
    LEFT JOIN nps_product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
    LEFT JOIN nps_ratings r ON p.product_id = r.product_id
    WHERE p.publish_status = 'published'
";

$types = '';
$params = [];

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.brand LIKE ? OR c.category_name LIKE ?)";
    $searchValue = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($categoryId > 0) {
    $sql .= " AND p.category_id = ?";
    $types .= 'i';
    $params[] = $categoryId;
}

$sql .= "
    GROUP BY p.product_id, p.product_name, p.short_description, p.price, p.brand, c.category_name, pi.image_path
    ORDER BY p.created_at DESC
    LIMIT 24
";

$stmt = mysqli_prepare($conn, $sql);
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);

$isLoggedIn = isset($_SESSION['user_id']);
$dashboardPath = '/NextPickStore/auth/login.php';
if ($isLoggedIn) {
    $role = $_SESSION['role_name'] ?? '';
    if ($role === 'Admin') {
        $dashboardPath = '/NextPickStore/roles/admin/dashboard.php';
    } elseif ($role === 'Seller') {
        $dashboardPath = '/NextPickStore/roles/seller/dashboard.php';
    } elseif ($role === 'Buyer') {
        $dashboardPath = '/NextPickStore/roles/buyer/dashboard.php';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPickStore</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; }
        body { background: #f4f4f6; color: #222; }
        a { color: inherit; text-decoration: none; }
        .page { max-width: 1180px; margin: 0 auto; padding: 24px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 26px; }
        .brand { font-size: 24px; font-weight: 800; }
        .nav-actions { display: flex; gap: 10px; }
        .btn { border: 0; background: #222; color: #fff; padding: 10px 14px; border-radius: 6px; cursor: pointer; font-weight: 700; }
        .btn-light { background: #fff; color: #222; border: 1px solid #ddd; }
        .filters { display: grid; grid-template-columns: 1fr 220px auto; gap: 10px; margin-bottom: 24px; }
        input, select { width: 100%; border: 1px solid #d8d8d8; border-radius: 6px; padding: 11px 12px; font-size: 14px; background: #fff; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .product-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden; }
        .product-card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: #eee; }
        .product-body { padding: 14px; display: grid; gap: 8px; }
        .meta { color: #666; font-size: 13px; }
        .name { font-size: 16px; font-weight: 800; min-height: 40px; }
        .desc { color: #555; font-size: 14px; min-height: 42px; }
        .price-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .price { font-weight: 800; }
        .empty { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 24px; text-align: center; }
        @media (max-width: 720px) {
            .topbar, .filters { grid-template-columns: 1fr; display: grid; }
            .nav-actions { justify-content: stretch; }
            .btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="topbar">
            <a class="brand" href="/NextPickStore/index.php">NextPickStore</a>
            <div class="nav-actions">
                <a class="btn btn-light" href="<?php echo htmlspecialchars($dashboardPath); ?>"><?php echo $isLoggedIn ? 'Dashboard' : 'Login'; ?></a>
                <?php if (!$isLoggedIn): ?>
                    <a class="btn" href="/NextPickStore/auth/register.php">Sign Up</a>
                <?php endif; ?>
            </div>
        </header>

        <form class="filters" method="GET" id="publicSearchForm">
            <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search products, brands, or categories">
            <select name="category_id">
                <option value="0">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo (int)$category['category_id']; ?>" <?php echo $categoryId === (int)$category['category_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Search</button>
        </form>

        <?php if (empty($products)): ?>
            <div class="empty">No published products found.</div>
        <?php else: ?>
            <section class="grid">
                <?php foreach ($products as $product): ?>
                    <?php $imagePath = !empty($product['image_path']) ? $product['image_path'] : 'assets/images/products/view.png'; ?>
                    <article class="product-card">
                        <img src="/NextPickStore/<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        <div class="product-body">
                            <div class="meta"><?php echo htmlspecialchars($product['category_name']); ?><?php echo $product['brand'] ? ' / ' . htmlspecialchars($product['brand']) : ''; ?></div>
                            <div class="name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="desc"><?php echo htmlspecialchars($product['short_description']); ?></div>
                            <div class="price-row">
                                <span class="price">BHD <?php echo number_format((float)$product['price'], 2); ?></span>
                                <span class="meta"><?php echo $product['average_rating'] ? htmlspecialchars($product['average_rating']) . '/5' : 'No rating'; ?></span>
                            </div>
                            <a class="btn btn-light" href="/NextPickStore/auth/login.php">View Details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
