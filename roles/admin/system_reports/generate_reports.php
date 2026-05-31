<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

$conn = getConnection();
$adminName = $_SESSION['full_name'] ?? 'Admin';

$sellers = [];
$sellerSql = "
    SELECT u.user_id, u.full_name
    FROM nps_users u
    INNER JOIN nps_roles r ON u.role_id = r.role_id
    WHERE r.role_name = 'Seller'
    ORDER BY u.full_name ASC
";
$sellerResult = mysqli_query($conn, $sellerSql);
if ($sellerResult) {
    while ($row = mysqli_fetch_assoc($sellerResult)) {
        $sellers[] = $row;
    }
}

$brands = [];
$brandResult = mysqli_query($conn, "SELECT DISTINCT brand FROM nps_products WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand ASC");
if ($brandResult) {
    while ($row = mysqli_fetch_assoc($brandResult)) {
        $brands[] = $row['brand'];
    }
}

$success = $_SESSION['report_generate_success'] ?? '';
$error = $_SESSION['report_generate_error'] ?? '';

unset($_SESSION['report_generate_success'], $_SESSION['report_generate_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - NextPick</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background: #e9e9e9; color: #222; }
        a { text-decoration: none; color: inherit; }
        .page-wrapper { margin: 25px; background: #f6f6f6; border-radius: 14px; padding: 25px 30px 0; min-height: calc(100vh - 50px); }
        .topbar { background: #fff; border-radius: 10px; padding: 16px 22px; margin-bottom: 28px; }
        .topbar img { height: 24px; width: auto; }
        .main-layout { display: grid; grid-template-columns: 220px 1fr; gap: 28px; }
        .sidebar { padding-top: 8px; }
        .sidebar h2 { font-size: 20px; margin-bottom: 26px; font-weight: 700; }
        .nav-menu { display: flex; flex-direction: column; gap: 12px; }
        .nav-link { display: flex !important; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; font-size: 16px; color: #222; transition: all 0.25s ease; }
        .nav-link img { width: 20px; height: 20px; object-fit: contain; transition: transform 0.25s ease; }
        .nav-link:hover { background: #eef3ff; color: #2155f5; box-shadow: 0 6px 16px rgba(33, 85, 245, 0.10); transform: translateX(3px); }
        .nav-link:hover img { transform: scale(1.08); }
        .nav-link.active { background: #eef3ff; color: #2155f5; font-weight: 600; box-shadow: 0 6px 16px rgba(33, 85, 245, 0.10); }
        .nav-link.logout-link { margin-top: 10px; }

        .content { padding-bottom: 30px; }
        .section-title { background: #efefef; border-radius: 10px; padding: 16px 22px; font-size: 18px; font-weight: 600; margin-bottom: 24px; }
        .title-back-link { display: inline-flex; align-items: center; gap: 10px; color: #222; font-weight: 600; transition: all 0.25s ease; }
        .title-back-link:hover { color: #2155f5; }
        .back-arrow { font-size: 20px; transition: transform 0.25s ease; }
        .title-back-link:hover .back-arrow { transform: translateX(-3px); }

        .message-box {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }
        .success-box { background: #eafaf1; border: 1px solid #b8e7c8; color: #1a7f4b; }
        .error-box { background: #fff1f0; border: 1px solid #f5b7b1; color: #c0392b; }

        .report-type-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .type-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
            max-width: 760px;
            margin-left: auto;
            margin-right: auto;
        }

        .type-card {
            position: relative;
            background: #fff;
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 24px 16px;
            text-align: center;
            cursor: pointer;
            transition: 0.25s ease;
        }

        .type-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(33,85,245,0.10);
        }

        .type-card input {
            position: absolute;
            opacity: 0;
        }

        .type-card.active {
            background: #3b82f6;
            color: white;
            box-shadow: 0 10px 20px rgba(59,130,246,0.18);
        }

        .type-card .icon {
            font-size: 34px;
            margin-bottom: 10px;
        }

        .filter-card {
            background: #fff;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            max-width: 900px;
            margin: 0 auto;
        }

        .filter-card h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 18px;
        }

        .field-group label {
            display: block;
            font-size: 13px;
            margin-bottom: 8px;
            color: #444;
            font-weight: 600;
        }

        .field-group input,
        .field-group select {
            width: 100%;
            height: 42px;
            border: 1px solid #d8dce8;
            border-radius: 10px;
            padding: 0 12px;
            font-size: 14px;
            outline: none;
            background: #fff;
        }

        .field-group input:focus,
        .field-group select:focus {
            border-color: #2155f5;
            box-shadow: 0 0 0 3px rgba(33,85,245,0.10);
        }

        .form-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .clear-btn {
            min-width: 110px;
            height: 42px;
            border: 1px solid #444;
            border-radius: 999px;
            background: #fff;
            cursor: pointer;
            font-weight: 600;
        }

        .generate-btn {
            min-width: 120px;
            height: 42px;
            border: none;
            border-radius: 999px;
            background: #3b82f6;
            color: #fff;
            cursor: pointer;
            font-weight: 600;
        }

        .footer { margin-top: 35px; border-top: 1px solid #ddd; padding: 28px 0 18px; }
        .footer-top { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 22px; font-size: 14px; color: #666; }
        .footer-top h4 { color: #222; margin-bottom: 10px; font-size: 15px; }
        .footer-bottom { border-top: 1px solid #ddd; padding-top: 16px; font-size: 13px; color: #666; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; }

        @media (max-width: 1000px) {
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
            .type-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            .main-layout { grid-template-columns: 1fr; }
            .sidebar { padding-bottom: 10px; }
        }
        @media (max-width: 640px) {
            .page-wrapper { padding: 18px 16px 0; }
            .footer-top { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
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
                <a href="system_reports.php" class="title-back-link">
                    <span class="back-arrow">←</span>
                    <span>Generate Reports</span>
                </a>
            </div>

            <?php if (!empty($success)): ?>
                <div class="message-box success-box"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="message-box error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="process_generate_report.php" method="POST">
                <div class="report-type-title">Choose Report Type</div>

                <div class="type-grid">
                    <label class="type-card active-card" data-type="Orders Report">
                        <input type="radio" name="report_type" value="Orders Report" checked>
                        <div class="icon">🛒</div>
                        <div>Orders Report</div>
                    </label>

                    <label class="type-card" data-type="Products by Seller Report">
                        <input type="radio" name="report_type" value="Products by Seller Report">
                        <div class="icon">👤</div>
                        <div>Products by Seller</div>
                    </label>

                    <label class="type-card" data-type="Popular Products Report">
                        <input type="radio" name="report_type" value="Popular Products Report">
                        <div class="icon">⭐</div>
                        <div>Popular Products</div>
                    </label>
                </div>

                <div class="filter-card">
                    <h3>Filter</h3>

                    <div class="filter-grid">
                        <div class="field-group">
                            <label>Order Status</label>
                            <select name="order_status">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="field-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date">
                        </div>

                        <div class="field-group">
                            <label>End Date</label>
                            <input type="date" name="end_date">
                        </div>

                        <div class="field-group">
                            <label>Seller</label>
                            <select name="seller_id">
                                <option value="">All / Not needed</option>
                                <?php foreach ($sellers as $seller): ?>
                                    <option value="<?php echo (int)$seller['user_id']; ?>">
                                        <?php echo htmlspecialchars($seller['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field-group">
                            <label>Limit (Popular Products)</label>
                            <input type="number" name="limit_count" min="1" max="50" value="10">
                        </div>

                        <div class="field-group">
                            <label>Brand</label>
                            <select name="brand">
                                <option value="">All</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo htmlspecialchars($brand); ?>">
                                        <?php echo htmlspecialchars($brand); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="clear-btn">Clear</button>
                        <button type="submit" class="generate-btn">Generate</button>
                    </div>
                </div>
            </form>
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

<script>
    const cards = document.querySelectorAll('.type-card');

    function refreshActiveCard() {
        cards.forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            if (radio.checked) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
        });
    }

    cards.forEach(card => {
        card.addEventListener('click', function () {
            const radio = card.querySelector('input[type="radio"]');
            radio.checked = true;
            refreshActiveCard();
        });
    });

    refreshActiveCard();
</script>
</body>
</html>