<?php
include_once '../../includes/auth_guard.php';
requireRole(['Seller']);
include_once '../../includes/config.php';

$conn = getConnection();

$sellerId   = $_SESSION['user_id'];
$sellerName = $_SESSION['full_name'] ?? 'Seller';
$firstName  = $sellerName ? explode(' ', $sellerName)[0] : 'Seller';

$search = trim($_GET['search'] ?? '');

/* =========================
   HELP DATA
========================= */
$categories = [
    [
        'title' => 'Account & Security',
        'icon' => '🛡️',
        'description' => 'Manage your seller account, password, and login safety.',
        'url' => 'help_topic.php?topic=account-security',
        'keywords' => 'account security password login email profile seller safety protect access suspicious'
    ],
    [
        'title' => 'Listing & Products',
        'icon' => '🖥️',
        'description' => 'Learn how to add, edit, and manage your listed products.',
        'url' => 'help_topic.php?topic=listing-products',
        'keywords' => 'listing products add edit product image category stock price description inventory publish hidden draft'
    ],
    [
        'title' => 'Orders & Shipping',
        'icon' => '🚚',
        'description' => 'Track orders, update statuses, and handle shipping steps.',
        'url' => 'help_topic.php?topic=orders-shipping',
        'keywords' => 'orders shipping delivered shipped pending confirmed cancelled customer address tracking status'
    ],
    [
        'title' => 'Payments & Sales',
        'icon' => '💳',
        'description' => 'Understand payments, sales totals, and order income.',
        'url' => 'help_topic.php?topic=payments-sales',
        'keywords' => 'payments sales revenue income benefitpay cash credit card totals earnings money'
    ],
    [
        'title' => 'Analytics & Reports',
        'icon' => '📊',
        'description' => 'Check reports, product performance, and sales insights.',
        'url' => 'help_topic.php?topic=analytics-reports',
        'keywords' => 'analytics reports top selling revenue low stock performance insights views ratings comments'
    ],
    [
        'title' => 'Integrations',
        'icon' => '🔌',
        'description' => 'Support for future add-ons, tools, and platform extensions.',
        'url' => 'help_topic.php?topic=integrations',
        'keywords' => 'integrations tools extensions add-ons email notification shipping service payment gateway 2fa'
    ]
];

$faqItems = [
    [
        'question' => 'How do I add a new electronic item?',
        'answer'   => 'Go to Add Product from the seller panel, fill in the product name, category, price, stock quantity, short description, full description, and upload the product image, then save the product.',
        'url'      => 'help_topic.php?topic=listing-products',
        'keywords' => 'add product item electronics listing create upload image category stock price description'
    ],
    [
        'question' => 'How can I enable Bulk On Sale Discount?',
        'answer'   => 'This feature can be connected later to a discount management page. For now, you can manually edit product prices from your product management page.',
        'url'      => 'help_topic.php?topic=payments-sales',
        'keywords' => 'discount sale bulk price edit product management offer'
    ],
    [
        'question' => 'Why is my product status "Low Stock"?',
        'answer'   => 'A product may appear as low stock when the available quantity becomes small. You should update the stock quantity from your inventory page to avoid running out.',
        'url'      => 'help_topic.php?topic=listing-products',
        'keywords' => 'low stock inventory quantity product status update'
    ],
    [
        'question' => 'How to configure custom inventory thresholds?',
        'answer'   => 'This can be added later as an advanced setting. Currently, the system can display low-stock alerts based on the stock quantity stored in the database.',
        'url'      => 'help_topic.php?topic=listing-products',
        'keywords' => 'inventory thresholds low stock alerts quantity database settings'
    ],
    [
        'question' => 'What is 2FA and how to activate it?',
        'answer'   => '2FA means Two-Factor Authentication. It adds an extra login security step. If your project does not support it yet, you can place it later under Settings as an advanced feature.',
        'url'      => 'help_topic.php?topic=account-security',
        'keywords' => '2fa two factor authentication security login settings'
    ],
    [
        'question' => 'How do I update an order status?',
        'answer'   => 'Open the Orders page, find the order, then change its status depending on the order progress such as pending, confirmed, shipped, or delivered.',
        'url'      => 'help_topic.php?topic=orders-shipping',
        'keywords' => 'update order status pending confirmed shipped delivered orders'
    ],
    [
        'question' => 'Where can I see my sales performance?',
        'answer'   => 'You can review your dashboard and analytics reports pages to track revenue, top products, active orders, and product performance.',
        'url'      => 'help_topic.php?topic=analytics-reports',
        'keywords' => 'sales performance dashboard analytics reports revenue top products'
    ],
    [
        'question' => 'What payment methods are available in the system?',
        'answer'   => 'The sample system supports methods such as Credit Card, Cash on Delivery, and BenefitPay depending on your order records.',
        'url'      => 'help_topic.php?topic=payments-sales',
        'keywords' => 'payment methods credit card cash on delivery benefitpay'
    ],
    [
        'question' => 'How can I track my order status?',
        'answer'   => 'You can track your order in real-time through the "Order History" section in your dashboard.',
        'url'      => 'help_topic.php?topic=orders-shipping',
        'keywords' => 'track order status order history dashboard'
    ],
    [
        'question' => 'What is your return policy?',
        'answer'   => 'We offer a 14-day return policy for all unused items in their original packaging.',
        'url'      => 'help_topic.php?topic=orders-shipping',
        'keywords' => 'return policy returns unused original packaging'
    ],
    [
        'question' => 'How do I contact seller support?',
        'answer'   => 'Our support team is available 24/7 via the Help Center or by emailing support@nextpick.com.',
        'url'      => 'help_topic.php?topic=account-security',
        'keywords' => 'contact seller support help center email support'
    ],
    [
        'question' => 'What payment methods are accepted?',
        'answer'   => 'We accept all major credit cards, PayPal, and secure bank transfers.',
        'url'      => 'help_topic.php?topic=payments-sales',
        'keywords' => 'accepted payment methods paypal bank transfers credit cards'
    ],
    [
        'question' => 'How long does shipping usually take?',
        'answer'   => 'Standard shipping typically takes 3-5 business days depending on your location.',
        'url'      => 'help_topic.php?topic=orders-shipping',
        'keywords' => 'shipping time delivery 3-5 business days'
    ]
];

$quickLinks = [
    ['label' => 'GETTING STARTED GUIDE', 'url' => 'help_topic.php?topic=account-security'],
    ['label' => 'MANAGING PRODUCTS',      'url' => 'help_topic.php?topic=listing-products'],
    ['label' => 'SHIPPING ORDERS',        'url' => 'help_topic.php?topic=orders-shipping']
];

/* =========================
   SEARCH
========================= */
$filteredFaqItems   = $faqItems;
$filteredCategories = $categories;
$searchResults      = [];

if ($search !== '') {
    $searchLower = mb_strtolower($search);

    $filteredFaqItems = array_values(array_filter($faqItems, function ($item) use ($searchLower) {
        $haystack = mb_strtolower($item['question'] . ' ' . $item['answer'] . ' ' . ($item['keywords'] ?? ''));
        return strpos($haystack, $searchLower) !== false;
    }));

    $filteredCategories = array_values(array_filter($categories, function ($item) use ($searchLower) {
        $haystack = mb_strtolower($item['title'] . ' ' . $item['description'] . ' ' . ($item['keywords'] ?? ''));
        return strpos($haystack, $searchLower) !== false;
    }));

    foreach ($filteredFaqItems as $faq) {
        $searchResults[] = ['type' => 'FAQ',      'title' => $faq['question'],  'description' => $faq['answer'],       'url' => $faq['url']];
    }
    foreach ($filteredCategories as $category) {
        $searchResults[] = ['type' => 'Category', 'title' => $category['title'], 'description' => $category['description'], 'url' => $category['url']];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - NextPick</title>
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
}.sidebar {
            width: 255px;
            border-right: 1px solid #ececec;
            padding: 28px 18px;
            background: #fff;
            flex-shrink: 0;
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

        /* ── HELP LAYOUT ── */
        .help-layout {
            display: grid;
            grid-template-columns: 1.2fr 0.9fr;
            gap: 18px;
            align-items: stretch;
        }

        .left-column,
        .right-column {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 18px;
        }

        .card-title {
            font-size: 22px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 14px;
        }

        /* ── SEARCH ── */
        .search-box {
            width: 100%;
            position: relative;
            margin-bottom: 10px;
        }

        .search-box input {
            width: 100%;
            height: 48px;
            border: 1px solid #d8dee8;
            border-radius: 12px;
            padding: 0 96px 0 14px;
            outline: none;
            font-size: 14px;
            background: #fff;
        }

        .search-box input:focus {
            border-color: #3158ff;
            box-shadow: 0 0 0 3px rgba(49, 88, 255, 0.08);
        }

        .search-submit {
            position: absolute;
            right: 6px;
            top: 6px;
            height: 36px;
            border: none;
            background: #3158ff;
            color: #fff;
            border-radius: 9px;
            padding: 0 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .search-help-text {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .quick-links {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .quick-link {
            border: 1px solid #d9d9d9;
            background: #fafafa;
            color: #555;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .quick-link:hover {
            border-color: #3158ff;
            color: #3158ff;
            background: #f7f9ff;
        }

        /* ── SEARCH RESULTS ── */
        .search-results-box {
            margin-top: 14px;
            border-top: 1px solid #ececec;
            padding-top: 14px;
        }

        .search-results-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #222;
        }

        .search-result-item {
            display: block;
            border: 1px solid #ededed;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            background: #fff;
            transition: 0.2s ease;
        }

        .search-result-item:hover {
            border-color: #3158ff;
            background: #f8fbff;
        }

        .search-result-type {
            font-size: 11px;
            font-weight: 700;
            color: #3158ff;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .search-result-item h4 { font-size: 15px; color: #222; margin-bottom: 5px; }
        .search-result-item p  { font-size: 13px; color: #666; line-height: 1.55; }

        /* ── CATEGORIES ── */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .category-box-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .category-box {
            border: 1px solid #e8e8e8;
            border-radius: 18px;
            padding: 24px 18px;
            text-align: center;
            background: #fff;
            min-height: 205px;
            transition: all 0.22s ease;
            box-shadow: 0 2px 8px rgba(17, 24, 39, 0.04);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .category-box:hover {
            border-color: #3158ff;
            background: #f8fbff;
            box-shadow: 0 10px 24px rgba(49, 88, 255, 0.10);
            transform: translateY(-4px);
        }

        .category-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: #f3f6fb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
            border: 1px solid #e8edf5;
        }

        .category-box h4 { font-size: 18px; font-weight: 700; color: #1f2937; margin-bottom: 10px; line-height: 1.3; }
        .category-box p  { font-size: 13px; color: #6b7280; line-height: 1.65; max-width: 240px; }

        /* ── FAQ ── */
        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        details.faq-item {
            border-bottom: 1px solid #ececec;
            padding-bottom: 6px;
        }

        details.faq-item:last-child { border-bottom: none; }

        .faq-item summary {
            list-style: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            color: #222;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
        }

        .faq-item summary::-webkit-details-marker { display: none; }

        .faq-answer {
            padding: 2px 0 10px;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .arrow { font-size: 16px; color: #888; flex-shrink: 0; }

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
            .help-layout  { grid-template-columns: 1fr; }
            .footer-top   { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 950px) {
            .main-layout { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #ececec; }
            .categories-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .categories-grid { grid-template-columns: 1fr; }
            .footer-top { grid-template-columns: 1fr; }
            .topbar, .content, .footer-top, .footer-bottom, .sidebar { padding-left: 16px; padding-right: 16px; }
            .search-box input { padding-right: 12px; }
            .search-submit { position: static; width: 100%; margin-top: 8px; height: 40px; }
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
    <li><a href="dashboard.php"><img src="../../assets/images/icons/seller icon/dashboard.png" alt="" class="menu-icon-img"><span>Dashboard</span></a></li>

    <li><a href="my_products.php"><img src="../../assets/images/icons/seller icon/inventory-management.png" alt="" class="menu-icon-img"><span>Inventory Management</span></a></li>

    <li><a href="add_product.php"><img src="../../assets/images/icons/seller icon/add-to-cart.png" alt="" class="menu-icon-img"><span>Add Product</span></a></li>

    <li><a href="orders.php"><img src="../../assets/images/icons/seller icon/manifest.png" alt="" class="menu-icon-img"><span>Orders</span></a></li>

    <li><a href="customer_data.php"><img src="../../assets/images/icons/seller icon/client.png" alt="" class="menu-icon-img"><span>Customer Data</span></a></li>

    <li><a href="reports.php"><img src="../../assets/images/icons/seller icon/seo-report.png" alt="" class="menu-icon-img"><span>Analytics & Reports</span></a></li>

    <li><a href="settings.php"><img src="../../assets/images/icons/seller icon/settings.png" alt="" class="menu-icon-img"><span>Settings</span></a></li>

    <li><a href="help_center.php"><img src="../../assets/images/icons/seller icon/customer-support.png" alt="" class="menu-icon-img"><span>Help Center</span></a></li>

    <li><a href="../../auth/logout.php"><img src="../../assets/images/icons/seller icon/logout.png" alt="" class="menu-icon-img"><span>Log out</span></a></li>
</ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="content">
            <div class="page-title-box">
                <h1>Help Center</h1>
            </div>

            <div class="help-layout">
                <div class="left-column">
                    <div class="card">
                        <div class="card-title">Seller Help & Support</div>

                        <form method="GET" class="search-box">
                            <input
                                type="text"
                                name="search"
                                placeholder="Search for articles, guides, and FAQs"
                                value="<?php echo htmlspecialchars($search); ?>"
                            >
                            <button type="submit" class="search-submit">Search</button>
                        </form>

                        <div class="search-help-text">
                            Try: password, add product, low stock, shipped, reports, payment
                        </div>

                        <div class="quick-links">
                            <?php foreach ($quickLinks as $link) { ?>
                                <a href="<?php echo htmlspecialchars($link['url']); ?>" class="quick-link">
                                    <?php echo htmlspecialchars($link['label']); ?>
                                </a>
                            <?php } ?>
                        </div>

                        <?php if ($search !== '') { ?>
                            <div class="search-results-box">
                                <div class="search-results-title">
                                    Search Results for "<?php echo htmlspecialchars($search); ?>"
                                </div>

                                <?php if (!empty($searchResults)) { ?>
                                    <?php foreach ($searchResults as $result) { ?>
                                        <a href="<?php echo htmlspecialchars($result['url']); ?>" class="search-result-item">
                                            <div class="search-result-type"><?php echo htmlspecialchars($result['type']); ?></div>
                                            <h4><?php echo htmlspecialchars($result['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($result['description']); ?></p>
                                        </a>
                                    <?php } ?>
                                <?php } else { ?>
                                    <div class="search-result-item">
                                        <div class="search-result-type">No Results</div>
                                        <h4>No matching help topics found</h4>
                                        <p>Try using simpler words like product, order, payment, stock, password, or report.</p>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="card">
                        <div class="card-title">Browse Help By Category</div>

                        <div class="categories-grid">
                            <?php foreach ($filteredCategories as $category) { ?>
                                <a href="<?php echo htmlspecialchars($category['url']); ?>" class="category-box-link">
                                    <div class="category-box">
                                        <div class="category-icon"><?php echo htmlspecialchars($category['icon']); ?></div>
                                        <h4><?php echo htmlspecialchars($category['title']); ?></h4>
                                        <p><?php echo htmlspecialchars($category['description']); ?></p>
                                    </div>
                                </a>
                            <?php } ?>

                            <?php if (empty($filteredCategories)) { ?>
                                <div class="category-box" style="grid-column: 1 / -1;">
                                    <div class="category-icon">🔎</div>
                                    <h4>No category matches</h4>
                                    <p>Try another search word.</p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="right-column">
                    <div class="card">
                        <div class="card-title">Frequently Asked Questions</div>

                        <div class="faq-list">
                            <?php if (!empty($filteredFaqItems)) { ?>
                                <?php foreach ($filteredFaqItems as $faq) { ?>
                                    <details class="faq-item">
                                        <summary>
                                            <span><?php echo htmlspecialchars($faq['question']); ?></span>
                                            <span class="arrow">⌄</span>
                                        </summary>
                                        <div class="faq-answer">
                                            <?php echo htmlspecialchars($faq['answer']); ?>
                                        </div>
                                    </details>
                                <?php } ?>
                            <?php } else { ?>
                                <p style="font-size:14px; color:#666;">No FAQ results found.</p>
                            <?php } ?>
                        </div>
                    </div>
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
</html>he