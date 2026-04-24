<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

$conn = getConnection();

$adminName = $_SESSION['full_name'] ?? 'Admin';
$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($userId <= 0) {
    die('Invalid user ID.');
}

$success = $_SESSION['deactivate_user_success'] ?? '';
unset($_SESSION['deactivate_user_success']);

$sql = "
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.phone_number,
        u.address,
        u.status,
        r.role_name
    FROM nps_users u
    INNER JOIN nps_roles r ON u.role_id = r.role_id
    WHERE u.user_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    die('User not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Deactivate User - NextPick</title>
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

            .form-card {
                background: #fff;
                border-radius: 18px;
                padding: 28px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            }

            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 18px;
            }

            .field-group {
                display: flex;
                flex-direction: column;
            }

            .field-group.full-width {
                grid-column: 1 / -1;
            }

            .field-group label {
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 8px;
                color: #444;
            }

            .input-wrapper {
                position: relative;
            }

            .input-wrapper input {
                width: 100%;
                height: 48px;
                border: 1px solid #d3d3d3;
                border-radius: 10px;
                padding: 0 44px 0 14px;
                font-size: 14px;
                outline: none;
                background: #fafafa;
                color: #444;
            }

            .input-icon {
                position: absolute;
                right: 14px;
                top: 50%;
                transform: translateY(-50%);
                color: #2155f5;
                font-size: 16px;
            }

            .status-box {
                margin-top: 10px;
                width: 320px;
                max-width: 100%;
                background: #fafafa;
                border: 1px solid #d9d9d9;
                border-radius: 14px;
                padding: 12px 20px;
                text-align: center;
            }

            .status-box .small-label {
                color: #2155f5;
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 4px;
            }

            .status-box .id-value {
                font-size: 13px;
                color: #222;
                text-transform: capitalize;
            }

            .warning-box {
                margin-top: 14px;
                background: #fff4ec;
                border: 1px solid #ffd4b5;
                color: #9c4700;
                padding: 12px 14px;
                border-radius: 10px;
                font-size: 14px;
            }

            .success-box {
                background: #eafaf1;
                border: 1px solid #b8e7c8;
                color: #1a7f4b;
                padding: 12px 14px;
                border-radius: 10px;
                margin-bottom: 18px;
                font-size: 14px;
            }

            .form-actions {
                display: flex;
                justify-content: flex-end;
                margin-top: 20px;
            }

            .deactivate-btn {
                min-width: 135px;
                height: 44px;
                border: none;
                border-radius: 10px;
                background: #d84315;
                color: white;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: 0.25s ease;
            }

            .deactivate-btn:hover {
                background: #bf360c;
                box-shadow: 0 8px 18px rgba(216, 67, 21, 0.18);
            }

            .deactivate-btn:disabled {
                background: #c7c7c7;
                cursor: not-allowed;
                box-shadow: none;
            }

            .activate-btn {
                min-width: 135px;
                height: 44px;
                border: none;
                border-radius: 10px;
                background: #1e9c57;
                color: white;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: 0.25s ease;
            }

            .activate-btn:hover {
                background: #167844;
                box-shadow: 0 8px 18px rgba(30, 156, 87, 0.18);
            }

            .activate-btn:disabled {
                background: #c7c7c7;
                cursor: not-allowed;
                box-shadow: none;
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

                .info-grid {
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

                        <a href="manage_users.php" class="nav-link active">
                            <img src="../../../assets/images/icons/admin/users.png" alt="Manage users">
                            <span>Manage users</span>
                        </a>

                        <a href="../manage_products/manage_products.php" class="nav-link">
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
                        <a href="delete_users.php" class="title-back-link">
                            <span class="back-arrow">←</span>
                            <span>Delete User</span>
                        </a>
                    </div>

                    <div class="form-card">
                        <?php if (!empty($success)): ?>
                            <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <div class="info-grid">
                            <div class="field-group">
                                <label>Name</label>
                                <div class="input-wrapper">
                                    <input type="text" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                                    <span class="input-icon"></span>
                                </div>
                            </div>

                            <div class="field-group">
                                <label>E-mail address</label>
                                <div class="input-wrapper">
                                    <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    <span class="input-icon"></span>
                                </div>
                            </div>

                            <div class="field-group">
                                <label>Phone number</label>
                                <div class="input-wrapper">
                                    <input type="text" value="<?php echo htmlspecialchars($user['phone_number']); ?>" readonly>
                                    <span class="input-icon"></span>
                                </div>
                            </div>

                            <div class="field-group">
                                <label>Status</label>
                                <div class="input-wrapper">
                                    <input type="text" value="<?php echo htmlspecialchars($user['status']); ?>" readonly>
                                    <span class="input-icon"></span>
                                </div>
                            </div>

                            <div class="field-group full-width">
                                <label>Address</label>
                                <div class="input-wrapper">
                                    <input type="text" value="<?php echo htmlspecialchars($user['address']); ?>" readonly>
                                    <span class="input-icon"></span>
                                </div>
                            </div>

                            <div class="field-group">
                                <label>Role</label>
                                <div class="input-wrapper">
                                    <input type="text" value="<?php echo htmlspecialchars($user['role_name']); ?>" readonly>
                                    <span class="input-icon"></span>
                                </div>
                            </div>
                        </div>

                        <div class="status-box">
                            <div class="small-label">Auto Generated ID:</div>
                            <div class="id-value"><?php echo htmlspecialchars($user['user_id']); ?></div>
                        </div>

                        <div class="warning-box">
                            This action will not remove the user from the database. It will only change the account status between <strong>active</strong> and <strong>inactive</strong>.
                        </div>

                        <?php
                        $currentStatus = strtolower($user['status']);
                        $nextStatus = '';
                        $buttonText = '';
                        $buttonClass = 'deactivate-btn';
                        $isDisabled = false;

                        if ($currentStatus === 'active') {
                            $nextStatus = 'inactive';
                            $buttonText = 'Deactivate';
                            $buttonClass = 'deactivate-btn';
                        } elseif ($currentStatus === 'inactive') {
                            $nextStatus = 'active';
                            $buttonText = 'Activate';
                            $buttonClass = 'activate-btn';
                        } else {
                            $isDisabled = true;
                            $buttonText = 'Blocked User';
                        }
                        ?>

                        <form action="process_deactivate_user.php" method="POST" class="form-actions">
                            <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                            <input type="hidden" name="next_status" value="<?php echo htmlspecialchars($nextStatus); ?>">

                            <button type="submit" class="<?php echo $buttonClass; ?>" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($buttonText); ?>
                            </button>
                        </form>
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