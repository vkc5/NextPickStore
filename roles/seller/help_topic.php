<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

$topic = $_GET['topic'] ?? '';

$helpTopics = [
    'account-security' => [
        'title' => 'Account & Security',
        'icon' => '🛡️',
        'intro' => 'This section helps sellers protect their account, keep login details secure, and manage account information safely.',
        'sections' => [
            [
                'heading' => 'How to keep your account secure',
                'items' => [
                    'Use a strong password that includes uppercase letters, lowercase letters, numbers, and symbols.',
                    'Do not share your seller login details with anyone.',
                    'Always log out after using the platform on a public or shared computer.',
                    'Change your password immediately if you notice suspicious activity.'
                ]
            ],
            [
                'heading' => 'Account management tips',
                'items' => [
                    'Make sure your email address is correct and active.',
                    'Keep your phone number updated in case support needs to reach you.',
                    'Review your profile details regularly.',
                    'Use future security settings such as two-factor authentication when available.'
                ]
            ],
            [
                'heading' => 'Common account problems',
                'items' => [
                    'Forgot password',
                    'Unable to log in',
                    'Incorrect email saved on the account',
                    'Suspicious login attempts'
                ]
            ]
        ]
    ],

    'listing-products' => [
        'title' => 'Listing & Products',
        'icon' => '🖥️',
        'intro' => 'This section explains how to create, manage, and improve your product listings.',
        'sections' => [
            [
                'heading' => 'How to add a product',
                'items' => [
                    'Open Add Product from the seller panel.',
                    'Enter the product name, category, price, quantity, and descriptions.',
                    'Upload at least one product image.',
                    'Save the product so it appears in your inventory.'
                ]
            ],
            [
                'heading' => 'Best practices',
                'items' => [
                    'Use clear product names.',
                    'Add both short and full descriptions.',
                    'Upload high-quality images.',
                    'Keep stock and price updated.'
                ]
            ]
        ]
    ],

    'orders-shipping' => [
        'title' => 'Orders & Shipping',
        'icon' => '🚚',
        'intro' => 'This section helps you manage orders and shipping correctly.',
        'sections' => [
            [
                'heading' => 'Order statuses',
                'items' => [
                    'Pending: order is waiting for action.',
                    'Confirmed: order has been accepted.',
                    'Shipped: order has been sent.',
                    'Delivered: customer received the order.',
                    'Cancelled: order was cancelled.'
                ]
            ],
            [
                'heading' => 'Shipping tips',
                'items' => [
                    'Check stock before confirming the order.',
                    'Verify shipping details carefully.',
                    'Update the status after shipment.',
                    'Inform customers if delays happen.'
                ]
            ]
        ]
    ],

    'payments-sales' => [
        'title' => 'Payments & Sales',
        'icon' => '💳',
        'intro' => 'This section explains revenue, sales, and payment-related information.',
        'sections' => [
            [
                'heading' => 'Sales overview',
                'items' => [
                    'Your sales depend on product demand, stock, and pricing.',
                    'Completed orders affect total income.',
                    'Updated product details can improve conversion.'
                ]
            ],
            [
                'heading' => 'Payment methods',
                'items' => [
                    'Credit Card',
                    'Cash on Delivery',
                    'BenefitPay',
                    'Other future payment options can be added later.'
                ]
            ]
        ]
    ],

    'analytics-reports' => [
        'title' => 'Analytics & Reports',
        'icon' => '📊',
        'intro' => 'This section helps you understand product performance and sales insights.',
        'sections' => [
            [
                'heading' => 'What reports can show',
                'items' => [
                    'Top-selling products',
                    'Revenue totals',
                    'Order activity',
                    'Low stock items',
                    'General product performance'
                ]
            ],
            [
                'heading' => 'Why reports matter',
                'items' => [
                    'They help identify strong products.',
                    'They show which products need restocking.',
                    'They support better seller decisions.'
                ]
            ]
        ]
    ],

    'integrations' => [
        'title' => 'Integrations',
        'icon' => '🔌',
        'intro' => 'This section introduces possible future integrations and connected tools.',
        'sections' => [
            [
                'heading' => 'Possible integrations',
                'items' => [
                    'Email notifications',
                    'External shipping providers',
                    'Payment gateways',
                    'Advanced reporting tools',
                    'Security tools like 2FA'
                ]
            ],
            [
                'heading' => 'Why integrations help',
                'items' => [
                    'They reduce manual work.',
                    'They improve store management.',
                    'They make the platform more advanced.'
                ]
            ]
        ]
    ]
];

if (!isset($helpTopics[$topic])) {
    $topic = 'account-security';
}

$current = $helpTopics[$topic];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($current['title']) ?> - Help Center</title>
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

        .topbar-user strong { font-size: 14px; }
        .topbar-user span   { font-size: 12px; color: #666; }

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
        }

        .menu a:hover  { background: #f4f6ff; color: #3158ff; }
        .menu a.active { background: #eef2ff; color: #3158ff; font-weight: 600; }

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
            padding: 0 0 18px 0;
            margin-bottom: 6px;
        }

        .page-title-box h1 {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        /* ── TOPIC CARD ── */
        .topic-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
        }

        .topic-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }

        .topic-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            background: #f3f6fb;
            border: 1px solid #e8edf5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            flex-shrink: 0;
        }

        .topic-header h2 { font-size: 30px; color: #1f2937; }

        .intro {
            font-size: 15px;
            color: #5f6b7a;
            line-height: 1.8;
            margin-bottom: 24px;
        }

        .section { margin-bottom: 24px; }

        .section h3 {
            font-size: 20px;
            margin-bottom: 12px;
            color: #1f2937;
        }

        .section ul { padding-left: 22px; }

        .section li {
            margin-bottom: 10px;
            line-height: 1.7;
            color: #4b5563;
        }

        .back-btn {
            display: inline-block;
            margin-top: 8px;
            background: #3158ff;
            color: #fff;
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
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

        .footer h4 { font-size: 14px; margin-bottom: 12px; }

        .footer p,
        .footer a,
        .footer li { font-size: 13px; color: #666; line-height: 1.8; }

        .footer ul { list-style: none; }

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

        .footer-links { display: flex; gap: 18px; flex-wrap: wrap; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1200px) {
            .footer-top { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 950px) {
            .main-layout { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #ececec; }
        }

        @media (max-width: 640px) {
            .footer-top { grid-template-columns: 1fr; }
            .topbar, .content, .sidebar,
            .footer-top, .footer-bottom { padding-left: 16px; padding-right: 16px; }
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
            <h2>Welcome,<br><?= htmlspecialchars($firstName) ?></h2>

            <ul class="menu">
    <li><a href="dashboard.php"><img src="../../assets/images/icons/seller icon/dashboard.png" alt="" class="menu-icon-img"><span>Dashboard</span></a></li>

    <li><a href="my_products.php"><img src="../../assets/images/icons/seller icon/inventory-management.png" alt="" class="menu-icon-img"><span>Inventory Management</span></a></li>

    <li><a href="add_product.php"><img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img"><span>Add Product</span></a></li>

    <li><a href="orders.php"><img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img"><span>Orders</span></a></li>

    <li><a href="customer_data.php"><img src="../../assets/images/icons/seller icon/client.png" alt="" class="menu-icon-img"><span>Customer Data</span></a></li>

    <li><a href="reports.php"><img src="../../assets/images/icons/seller icon/seo-report.png" alt="" class="menu-icon-img"><span>Analytics & Reports</span></a></li>

    <li><a href="help_center.php"><img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img"><span>Help Center</span></a></li>

    <li><a href="../../auth/logout.php"><img src="../../assets/images/icons/seller icon/logout.png" alt="" class="menu-icon-img"><span>Log out</span></a></li>
</ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="content">
            <div class="page-title-box">
                <h1><?= htmlspecialchars($current['title']) ?></h1>
            </div>

            <div class="topic-card">
                <div class="topic-header">
                    <div class="topic-icon"><?= htmlspecialchars($current['icon']) ?></div>
                    <h2><?= htmlspecialchars($current['title']) ?></h2>
                </div>

                <div class="intro">
                    <?= htmlspecialchars($current['intro']) ?>
                </div>

                <?php foreach ($current['sections'] as $section) { ?>
                    <div class="section">
                        <h3><?= htmlspecialchars($section['heading']) ?></h3>
                        <ul>
                            <?php foreach ($section['items'] as $item) { ?>
                                <li><?= htmlspecialchars($item) ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <a href="help_center.php" class="back-btn">Back to Help Center</a>
            </div>
        </main>
    </div>

    <!-- FOOTER -->
    <?php include_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

</div>
</body>
</html>
