<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

$conn = getConnection();

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reportId <= 0) {
    die('Invalid report ID.');
}

/* -----------------------------
   Get report info
----------------------------- */
$reportSql = "
    SELECT report_id, report_title, report_type
    FROM nps_reports
    WHERE report_id = ?
    LIMIT 1
";
$reportStmt = mysqli_prepare($conn, $reportSql);
mysqli_stmt_bind_param($reportStmt, "i", $reportId);
mysqli_stmt_execute($reportStmt);
$reportResult = mysqli_stmt_get_result($reportStmt);
$report = mysqli_fetch_assoc($reportResult);
mysqli_stmt_close($reportStmt);

if (!$report) {
    die('Report not found.');
}

/* -----------------------------
   Get saved rows
----------------------------- */
$rows = [];

$rowSql = "
    SELECT row_data
    FROM nps_report_rows
    WHERE report_id = ?
    ORDER BY row_no ASC
";
$rowStmt = mysqli_prepare($conn, $rowSql);
mysqli_stmt_bind_param($rowStmt, "i", $reportId);
mysqli_stmt_execute($rowStmt);
$rowResult = mysqli_stmt_get_result($rowStmt);

while ($row = mysqli_fetch_assoc($rowResult)) {
    $decoded = json_decode($row['row_data'], true);
    if (is_array($decoded)) {
        $rows[] = $decoded;
    }
}
mysqli_stmt_close($rowStmt);

if (empty($rows)) {
    die('No rows found for this report.');
}

/* -----------------------------
   Build filename
----------------------------- */
$cleanTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $report['report_title']);
$filename = $cleanTitle . '_Report_' . $reportId . '.csv';

/* -----------------------------
   Update report status
----------------------------- */
$updateSql = "
    UPDATE nps_reports
    SET status = 'exported'
    WHERE report_id = ?
";
$updateStmt = mysqli_prepare($conn, $updateSql);
mysqli_stmt_bind_param($updateStmt, "i", $reportId);
mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

/* -----------------------------
   Output CSV for Excel
----------------------------- */
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

/* UTF-8 BOM for Excel */
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

$headers = array_keys($rows[0]);

/* Pretty header names */
$prettyHeaders = [];
foreach ($headers as $header) {
    $prettyHeaders[] = ucwords(str_replace('_', ' ', $header));
}

fputcsv($output, $prettyHeaders);

foreach ($rows as $row) {
    $line = [];
    foreach ($headers as $header) {
        $line[] = $row[$header] ?? '';
    }
    fputcsv($output, $line);
}

fclose($output);
exit;