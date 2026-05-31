<?php
include_once '../includes/auth_guard.php';
include_once '../includes/config.php';
include_once '../includes/session.php';

requireRole(['Admin']);

header('Content-Type: application/json');

$conn = getConnection();

$search = trim($_GET['search'] ?? '');
$roles = $_GET['roles'] ?? [];

$allowedRoles = ['Admin', 'Seller', 'Buyer'];
$selectedRoles = [];

if (is_array($roles)) {
    foreach ($roles as $role) {
        if (in_array($role, $allowedRoles)) {
            $selectedRoles[] = $role;
        }
    }
}

if (empty($selectedRoles)) {
    echo json_encode([
        'count' => 0,
        'rows' => []
    ]);
    exit;
}

$rolePlaceholders = implode(',', array_fill(0, count($selectedRoles), '?'));

$sql = "
    SELECT 
        u.user_id,
        u.full_name,
        u.phone_number,
        r.role_name
    FROM nps_users u
    INNER JOIN nps_roles r ON u.role_id = r.role_id
    WHERE r.role_name IN ($rolePlaceholders)
";

$params = $selectedRoles;
$types = str_repeat('s', count($selectedRoles));

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR CAST(u.user_id AS CHAR) LIKE ?)";
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'ss';
}

$sql .= " ORDER BY u.user_id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
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