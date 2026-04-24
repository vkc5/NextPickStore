<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

$conn = getConnection();
$adminName = $_SESSION['full_name'] ?? 'Admin';
$reportId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($reportId <= 0) {
    die('Invalid report ID.');
}

$reportSql = "
    SELECT r.*, u.full_name AS created_by_name
    FROM nps_reports r
    INNER JOIN nps_users u ON r.created_by = u.user_id
    WHERE r.report_id = ?
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

$rows = [];
$rowSql = "SELECT row_data FROM nps_report_rows WHERE report_id = ? ORDER BY row_no ASC";
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

$headers = !empty($rows) ? array_keys($rows[0]) : [];
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>View Report - NextPick</title>
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
                box-shadow: 0 6px 16px rgba(33, 85, 245, 0.10);
                transform: translateX(3px);
            }
            .nav-link:hover img {
                transform: scale(1.08);
            }
            .nav-link.active {
                background: #eef3ff;
                color: #2155f5;
                font-weight: 600;
                box-shadow: 0 6px 16px rgba(33, 85, 245, 0.10);
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

            .report-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 18px;
                gap: 12px;
                flex-wrap: wrap;
            }

            .report-meta {
                color: #666;
                font-size: 14px;
            }

            .export-btn {
                padding: 12px 18px;
                border: 2px solid #3b82f6;
                color: #3b82f6;
                border-radius: 999px;
                font-weight: 600;
                background: #fff;
            }

            .report-card {
                background: #fff;
                border-radius: 18px;
                padding: 22px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            }

            .table-wrapper {
                overflow-x: auto;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            thead th {
                background: #dfe1e7;
                text-align: left;
                padding: 14px 16px;
                font-size: 13px;
            }

            tbody td {
                padding: 14px 16px;
                font-size: 14px;
                border-bottom: 1px solid #ececec;
            }

            tbody tr:nth-child(even) {
                background: #f7f7f7;
            }

            .empty-box {
                text-align: center;
                padding: 30px 20px;
                color: #666;
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
                        <a href="../manage_products/manage_products.php" class="nav-link">
                            <img src="../../../assets/images/icons/admin/box.png" alt="Manage products">
                            <span>Manage products</span>
                        </a>
                        <a href="system_reports.php" class="nav-link active">
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
                        <a href="view_reports.php" class="title-back-link">
                            <span class="back-arrow">←</span>
                            <span>System Reports</span>
                        </a>
                    </div>

                    <div class="report-header">
                        <div class="report-meta">
                            <strong><?php echo htmlspecialchars($report['report_title']); ?></strong><br>
                            Report #<?php echo (int) $report['report_id']; ?> |
                            <?php echo htmlspecialchars($report['report_type']); ?> |
                            By <?php echo htmlspecialchars($report['created_by_name']); ?> |
                            <?php echo htmlspecialchars($report['created_at']); ?>
                        </div>

                        <a href="export_report.php?id=<?php echo (int) $report['report_id']; ?>" class="export-btn">
                            Export to Excel
                        </a>
                    </div>

                    <div class="report-card">
                        <?php if (!empty($rows)): ?>
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <?php foreach ($headers as $header): ?>
                                                <th><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $header))); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $row): ?>
                                            <tr>
                                                <?php foreach ($headers as $header): ?>
                                                    <td><?php echo htmlspecialchars((string) ($row[$header] ?? '')); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-box">No saved rows found for this report.</div>
                        <?php endif; ?>
                    </div>
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