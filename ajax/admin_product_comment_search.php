<?php
include_once '../includes/auth_guard.php';
include_once '../includes/config.php';
include_once '../includes/session.php';

requireRole(['Admin']);

header('Content-Type: application/json');

$conn = getConnection();

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        p.product_id,
        p.product_name,
        p.publish_status,
        img.image_path,
        COUNT(c.comment_id) AS total_comments
    FROM nps_products p
    LEFT JOIN nps_product_images img
        ON p.product_id = img.product_id AND img.is_primary = 1
    LEFT JOIN nps_comments c
        ON p.product_id = c.product_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR CAST(p.product_id AS CHAR) LIKE ?)";
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'ss';
}

$sql .= "
    GROUP BY
        p.product_id,
        p.product_name,
        p.publish_status,
        img.image_path
    ORDER BY p.product_id DESC
";

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
    'count' => count($rows),
    'rows' => $rows
]);