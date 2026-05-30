<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'];
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

/* =========================
   FILTERS
========================= */
$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['search'] ?? '');

$validStatuses = ['active', 'inactive', 'blocked'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

/* =========================
   CUSTOMER LIST
   Only buyers who ordered from this seller
========================= */
$customers = [];

$sql = "
    SELECT
        u.user_id,
        u.full_name AS customer_name,
        u.email,
        u.phone_number,
        u.status,
        COUNT(DISTINCT o.order_id) AS total_orders,
        MAX(o.order_date) AS last_purchased_date
    FROM nps_users u
    INNER JOIN nps_orders o
        ON u.user_id = o.buyer_id
    INNER JOIN nps_order_items oi
        ON o.order_id = oi.order_id
    INNER JOIN nps_products p
        ON oi.product_id = p.product_id
    INNER JOIN nps_roles r
        ON u.role_id = r.role_id
    WHERE p.seller_id = ?
      AND r.role_name = 'Buyer'
";

$params = [$sellerId];
$types  = "i";

if ($statusFilter !== '') {
    $sql .= " AND u.status = ? ";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($search !== '') {
    $sql .= " AND (
                u.full_name LIKE ?
                OR u.email LIKE ?
                OR o.order_id LIKE ?
             ) ";
    $searchLike = "%" . $search . "%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "sss";
}

$sql .= "
    GROUP BY
        u.user_id,
        u.full_name,
        u.email,
        u.phone_number,
        u.status
    ORDER BY last_purchased_date DESC, customer_name ASC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $customers[] = $row;
}
mysqli_stmt_close($stmt);

$totalCustomers = count($customers);

function customerStatusClass($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'badge active';
        case 'inactive':
            return 'badge inactive';
        case 'blocked':
            return 'badge blocked';
        default:
            return 'badge';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Data - NextPick</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f4f6;
            color: #222;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .page-wrapper {
    max-width: calc(100% - 50px);
    margin: 25px auto;
    background: #fff;
    border: 1px solid #e8e8e8;
    min-height: 90vh;
    display: flex;
    flex-direction: column;
}

        /* ── TOPBAR ── */
        .topbar {
            padding: 18px 28px;
            border-bottom: 1px solid #ececec;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar img {
            height: 34px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .seller-badge {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e8e8ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .topbar-user {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .topbar-user strong {
            font-size: 14px;
        }

        .topbar-user span {
            font-size: 12px;
            color: #666;
        }

        /* ── LAYOUT ── */
        .main-layout {
    display: flex;
    flex: 1;
}


        .sidebar {
    width: 255px;
    border-right: 1px solid #ececec;
    padding: 28px 18px;
    background: #fff;
    flex-shrink: 0;
    align-self: stretch; /* ← ADD THIS */
}


        .sidebar h2 {
            font-size: 28px;
            line-height: 1.15;
            margin-bottom: 28px;
            font-weight: 700;
            color: #111827;
        }

        .menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .menu li {
            margin: 0;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 48px;
            padding: 12px 14px;
            border-radius: 12px;
            color: #111827;
            font-size: 15px;
            font-weight: 500;
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .menu a:hover {
            background: #f4f6ff;
            color: #3158ff;
        }

        .menu a.active {
            background: #eef2ff;
            color: #3158ff;
            font-weight: 600;
        }

        .menu-icon-img {
            width: 18px;
            height: 18px;
            object-fit: contain;
            flex-shrink: 0;
        }

        /* ── CONTENT ── */
        .content {
    flex: 1;
    padding: 28px;
    background: #fcfcfc;
    min-width: 0;
    align-self: stretch; /* ← ADD THIS */
}


        .page-title-box {
    background: transparent;
    padding: 0 0 18px 0;
    margin-bottom: 6px;
}

        .page-title-box h1 {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .table-card {
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 20px;
            padding: 18px 18px 8px;
        }

        .card-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 18px;
            color: #222;
        }

        .card-title span {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }

        /* ── FILTERS ── */
        .filters {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 16px;
            margin-bottom: 18px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 180px;
        }

        .filter-group label {
            font-size: 14px;
            color: #444;
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input[type="text"] {
            height: 44px;
            border: 1px solid #d9d9d9;
            border-radius: 10px;
            padding: 0 12px;
            background: #fff;
            outline: none;
            font-size: 14px;
            color: #333;
        }

        .search-group {
            flex: 1;
            min-width: 260px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ── BUTTONS ── */
        .btn {
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
        }

        .btn-primary {
            background: #3158ff;
            color: #fff;
        }

        .btn-light {
            background: #f1f1f5;
            color: #222;
        }

        /* ── TABLE ── */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th {
            text-align: left;
            background: #f3f3f3;
            color: #222;
            padding: 14px 10px;
            font-size: 14px;
            font-weight: 600;
        }

        td {
            padding: 14px 10px;
            border-top: 1px solid #ededed;
            font-size: 15px;
            color: #444;
            vertical-align: middle;
        }

        .checkbox-cell {
            width: 42px;
        }

        .checkbox-cell input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .active   { background: #d9f7be; color: #237804; }
        .inactive { background: #fff1b8; color: #ad6800; }
        .blocked  { background: #ffd6d6; color: #c62828; }

        /* ── ACTION ICONS ── */
        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .icon-btn {
            width: 34px;
            height: 34px;
            border: 1px solid #d9d9d9;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            transition: 0.2s ease;
        }

        .icon-btn:hover {
            background: #f4f6ff;
            border-color: #3158ff;
        }

        .icon-btn svg {
            width: 18px;
            height: 18px;
            stroke: #666;
        }

        .empty-state {
            padding: 28px 10px 20px;
            text-align: center;
            color: #777;
            font-size: 15px;
        }

        /* ── FOOTER ── */
        .footer {
    border-top: 1px solid #ececec;
    background: #fff;
    margin-top: 0;
}

        .footer-top {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            padding: 28px;
        }

        .footer h4 {
            font-size: 14px;
            margin-bottom: 12px;
        }

        .footer p,
        .footer a,
        .footer li {
            font-size: 13px;
            color: #666;
            line-height: 1.8;
        }

        .footer ul {
            list-style: none;
        }

        .footer-bottom {
            border-top: 1px solid #f0f0f0;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: #666;
        }

        .footer-links {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 1200px) {
            .footer-top {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 900px) {
            .main-layout {
    display: flex;
    min-height: auto;
}

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #ececec;
            }
        }

        @media (max-width: 640px) {
            .footer-top {
                grid-template-columns: 1fr;
            }

            .topbar,
            .content,
            .footer-top,
            .footer-bottom,
            .sidebar {
                padding-left: 16px;
                padding-right: 16px;
            }

            .page-title-box h1,
            .card-title {
                font-size: 22px;
            }

            .card-title span {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- TOPBAR -->
    <div class="topbar">
        <img src="/NextPickStore/assets/images/Logos/nextpickstore-logo.png" alt="NextPick Logo">
        <div class="topbar-right">
            <div class="seller-badge"><?php echo strtoupper(substr($sellerName, 0, 1)); ?></div>
            <div class="topbar-user">
                <strong><?php echo htmlspecialchars($sellerName); ?></strong>
                <span>Seller</span>
            </div>
        </div>
    </div>

    <div class="main-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <h2>Welcome,<br><?php echo htmlspecialchars($firstName); ?></h2>

            <ul class="menu">
    <li>
        <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/dashboard.png" alt="" class="menu-icon-img">
            <span>Dashboard</span>
        </a>
    </li>

    <li>
        <a href="my_products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'my_products.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/inventory-management.png" alt="" class="menu-icon-img">
            <span>Inventory Management</span>
        </a>
    </li>

    <li>
        <a href="add_product.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_product.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img">
            <span>Add Product</span>
        </a>
    </li>

    <li>
        <a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img">
            <span>Orders</span>
        </a>
    </li>

    <li>
        <a href="customer_Data.php" class="<?= basename($_SERVER['PHP_SELF']) == 'customer_Data.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/client.png" alt="" class="menu-icon-img">
            <span>Customer Data</span>
        </a>
    </li>

    <li>
        <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/seo-report.png" alt="" class="menu-icon-img">
            <span>Analytics & Reports</span>
        </a>
    </li>

    <li>
        <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/settings.png" alt="" class="menu-icon-img">
            <span>Settings</span>
        </a>
    </li>

    <li>
        <a href="help_center.php" class="<?= basename($_SERVER['PHP_SELF']) == 'help_center.php' ? 'active' : '' ?>">
            <img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img">
            <span>Help Center</span>
        </a>
    </li>

    <li>
        <a href="../../auth/logout.php">
            <img src="../../assets/images/icons/seller icon/logout.png" alt="" class="menu-icon-img">
            <span>Log out</span>
        </a>
    </li>
</ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="content">
            <div class="page-title-box">
                <h1>Customer Data</h1>
            </div>

            <div class="table-card">
                <div class="card-title">
                    Customer Listing & Engagement <span>(Total: <?php echo $totalCustomers; ?>)</span>
                </div>

                <form method="GET" class="filters">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Active, Inactive, Blocked</option>
                            <option value="active"   <?php echo ($statusFilter === 'active')   ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="blocked"  <?php echo ($statusFilter === 'blocked')  ? 'selected' : ''; ?>>Blocked</option>
                        </select>
                    </div>

                    <div class="filter-group search-group">
                        <label for="search">Search</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            placeholder="Search for customer by name, email, or order ID"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-light">Search</button>
                        <a href="customer_data.php" class="btn btn-light">Reset</a>
                        <a href="add_customer.php" class="btn btn-primary">Add New Customer</a>
                    </div>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">Select</th>
                                <th>Customer Name</th>
                                <th>Email Address</th>
                                <th>Phone Number</th>
                                <th>Total Orders</th>
                                <th>Last Purchased Date</th>
                                <th>Account Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($customers)) { ?>
                                <?php foreach ($customers as $customer) { ?>
                                    <tr>
                                        <td class="checkbox-cell">
                                            <input type="checkbox" value="<?php echo (int)$customer['user_id']; ?>">
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td>
                                            <?php echo !empty($customer['phone_number'])
                                                ? htmlspecialchars($customer['phone_number'])
                                                : '-'; ?>
                                        </td>
                                        <td><?php echo (int)$customer['total_orders']; ?></td>
                                        <td>
                                            <?php echo !empty($customer['last_purchased_date'])
                                                ? date('Y/m/d', strtotime($customer['last_purchased_date']))
                                                : '-'; ?>
                                        </td>
                                        <td>
                                            <span class="<?php echo customerStatusClass($customer['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($customer['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a class="icon-btn" href="edit_customer.php?id=<?php echo (int)$customer['user_id']; ?>" title="Edit Customer">
                                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487a2.25 2.25 0 113.182 3.182L8.25 19.463 4 20l.537-4.25L16.862 4.487z"/>
                                                    </svg>
                                                </a>
                                                <a class="icon-btn" href="customer_orders.php?id=<?php echo (int)$customer['user_id']; ?>" title="View Orders">
                                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.25l7.89 5.26a2 2 0 002.22 0L21 8.25M5.25 19.5h13.5A2.25 2.25 0 0021 17.25v-10.5A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v10.5A2.25 2.25 0 005.25 19.5z"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr>
                                    <td colspan="8" class="empty-state">No customers found for the selected filters.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-top">
            <div>
                <h4>E-commerce support</h4>
                <p>NEXTPICK</p>
                <p>Manama, Bahrain</p>
                <p>Phone: +973 123 4567</p>
                <p>Email: support@nextpick.com</p>
            </div>
            <div>
                <h4>Working hours</h4>
                <p>Monday to Friday: 09:00 - 18:00</p>
                <p>Saturday: 10:00 - 16:00</p>
                <p>Sunday: Closed</p>
            </div>
            <div>
                <h4>About us</h4>
                <ul>
                    <li><a href="#">Stores</a></li>
                    <li><a href="#">Corporate website</a></li>
                    <li><a href="#">Exclusive Offers</a></li>
                    <li><a href="#">Career</a></li>
                </ul>
            </div>
            <div>
                <h4>Help & Support</h4>
                <ul>
                    <li><a href="#">Help center</a></li>
                    <li><a href="#">Payments</a></li>
                    <li><a href="#">Product returns</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div>© 2024 NEXTPICK. All Rights Reserved.</div>
            <div class="footer-links">
                <a href="#">Privacy policy</a>
                <a href="#">Cookie settings</a>
                <a href="#">Terms and conditions</a>
                <a href="#">Imprint</a>
            </div>
        </div>
    </footer>

</div>
</body>
</html>