<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /NextPickStore/roles/admin/system_reports/generate_reports.php');
    exit;
}

$conn = getConnection();

$adminId = $_SESSION['user_id'] ?? 0;

$reportType = trim($_POST['report_type'] ?? '');
$orderStatus = trim($_POST['order_status'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$sellerId = trim($_POST['seller_id'] ?? '');
$limitCount = (int)($_POST['limit_count'] ?? 10);
$brand = trim($_POST['brand'] ?? '');

if ($startDate === '' || $endDate === '') {
    $_SESSION['report_generate_error'] = 'Start date and end date are required.';
    header('Location: /NextPickStore/roles/admin/system_reports/generate_reports.php');
    exit;
}

$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

$procedureName = '';
$reportTitle = '';
$generatedRows = [];
$filters = [
    'report_type' => $reportType,
    'order_status' => $orderStatus,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'seller_id' => $sellerId,
    'limit_count' => $limitCount,
    'brand' => $brand
];

try {
    if ($reportType === 'Orders Report') {
        $procedureName = 'sp_orders_report';
        $reportTitle = 'Orders Report - ' . date('Y-m-d H:i:s');

        $stmt = mysqli_prepare($conn, "CALL sp_orders_report(?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $startDateTime, $endDateTime, $orderStatus);
    } elseif ($reportType === 'Products by Seller Report') {
        if ($sellerId === '') {
            throw new Exception('Seller is required for Products by Seller Report.');
        }

        $procedureName = 'sp_seller_product_performance';
        $reportTitle = 'Products by Seller Report - ' . date('Y-m-d H:i:s');

        $sellerIdInt = (int)$sellerId;
        $stmt = mysqli_prepare($conn, "CALL sp_seller_product_performance(?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $sellerIdInt, $startDateTime, $endDateTime);
    } elseif ($reportType === 'Popular Products Report') {
        $procedureName = 'sp_popular_products_report';
        $reportTitle = 'Popular Products Report - ' . date('Y-m-d H:i:s');

        if ($limitCount <= 0) {
            $limitCount = 10;
        }

        $stmt = mysqli_prepare($conn, "CALL sp_popular_products_report(?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssi", $startDateTime, $endDateTime, $limitCount);
    } else {
        throw new Exception('Invalid report type.');
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $generatedRows[] = $row;
    }

    mysqli_stmt_close($stmt);

    while (mysqli_more_results($conn) && mysqli_next_result($conn)) {
        $extraResult = mysqli_store_result($conn);
        if ($extraResult instanceof mysqli_result) {
            mysqli_free_result($extraResult);
        }
    }

    $filtersJson = json_encode($filters, JSON_UNESCAPED_UNICODE);

    $insertReportSql = "
        INSERT INTO nps_reports
        (report_type, report_title, created_by, procedure_name, filters_json, status)
        VALUES (?, ?, ?, ?, ?, 'generated')
    ";
    $insertReportStmt = mysqli_prepare($conn, $insertReportSql);
    mysqli_stmt_bind_param($insertReportStmt, "ssiss", $reportType, $reportTitle, $adminId, $procedureName, $filtersJson);
    mysqli_stmt_execute($insertReportStmt);

    $reportId = mysqli_insert_id($conn);
    mysqli_stmt_close($insertReportStmt);

    if (!empty($generatedRows)) {
        $insertRowSql = "INSERT INTO nps_report_rows (report_id, row_no, row_data) VALUES (?, ?, ?)";
        $insertRowStmt = mysqli_prepare($conn, $insertRowSql);

        $rowNo = 1;
        foreach ($generatedRows as $row) {
            $rowJson = json_encode($row, JSON_UNESCAPED_UNICODE);
            mysqli_stmt_bind_param($insertRowStmt, "iis", $reportId, $rowNo, $rowJson);
            mysqli_stmt_execute($insertRowStmt);
            $rowNo++;
        }

        mysqli_stmt_close($insertRowStmt);
    }

    $_SESSION['report_generate_success'] = 'Report generated successfully.';
    header('Location: /NextPickStore/roles/admin/system_reports/view_report.php?id=' . $reportId);
    exit;

} catch (Exception $e) {
    $_SESSION['report_generate_error'] = $e->getMessage();
    header('Location: /NextPickStore/roles/admin/system_reports/generate_reports.php');
    exit;
}