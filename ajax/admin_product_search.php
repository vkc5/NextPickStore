<?php
include_once '../includes/auth_guard.php';
include_once '../includes/config.php';
include_once '../includes/session.php';

requireRole(['Admin']);

header('Content-Type: application/json');

$conn = getConnection();

$category = trim($_GET['category'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        p.product_id,
        p.product_name,
        p.brand,
        p.price,
        p.publish_status,
        c.category_name,
        u.full_name AS seller_name,
        img.image_path
    FROM nps_products p
    INNER JOIN nps_categories c ON p.category_id = c.category_id
    INNER JOIN nps_users u ON p.seller_id = u.user_id
    LEFT JOIN nps_product_images img
        ON p.product_id = img.product_id AND img.is_primary = 1
    WHERE 1=1
";

$params = [];
$types = '';

if ($category !== '') {
    $sql .= " AND c.category_name = ?";
    $params[] = $category;
    $types .= 's';
}

if ($brand !== '') {
    $sql .= " AND p.brand = ?";
    $params[] = $brand;
    $types .= 's';
}

if ($status !== '') {
    $sql .= " AND p.publish_status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($search !== '') {
    $sql .= " AND (
        p.product_name LIKE ?
        OR p.brand LIKE ?
        OR CAST(p.product_id AS CHAR) LIKE ?
        OR u.full_name LIKE ?
    )";
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'ssss';
}

$sql .= " ORDER BY p.product_id DESC";

$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'rows' => $rows
]);