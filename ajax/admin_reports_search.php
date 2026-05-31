<?php
include_once '../includes/auth_guard.php';
include_once '../includes/config.php';
include_once '../includes/session.php';

requireRole(['Admin']);

header('Content-Type: application/json');

$conn = getConnection();

$reportId = trim($_GET['report_id'] ?? '');
$reportType = trim($_GET['report_type'] ?? '');

$allowedTypes = [
    'Orders Report',
    'Products by Seller Report',
    'Popular Products Report'
];

$sql = "
    SELECT
        r.report_id,
        r.report_type,
        DATE_FORMAT(r.created_at, '%Y/%m/%d %h:%i %p') AS created_at_formatted,
        u.full_name AS created_by_name
    FROM nps_reports r
    INNER JOIN nps_users u ON r.created_by = u.user_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($reportId !== '') {
    $sql .= " AND CAST(r.report_id AS CHAR) LIKE ?";
    $params[] = '%' . $reportId . '%';
    $types .= 's';
}

if ($reportType !== '' && in_array($reportType, $allowedTypes)) {
    $sql .= " AND r.report_type = ?";
    $params[] = $reportType;
    $types .= 's';
}

$sql .= " ORDER BY r.report_id DESC";

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