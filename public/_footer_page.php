<?php
$projectBase = '/NextPickStore';
$logoPath = $projectBase . '/assets/images/Logos/nextpickstore-logo.png';
$pageKey = $pageKey ?? 'help_center';

$pages = [
    'help_center' => [
        'title' => 'Help Center',
        'eyebrow' => 'Support Hub',
        'intro' => 'Find quick answers about accounts, orders, payments, returns, and shopping safely with NextPick.',
        'stats' => [
            ['value' => '24/7', 'label' => 'Help access'],
            ['value' => '6', 'label' => 'Support categories'],
            ['value' => '1 day', 'label' => 'Typical reply time'],
        ],
        'cards_title' => 'Browse Help By Category',
        'cards' => [
            ['icon' => 'AC', 'title' => 'Account & Security', 'text' => 'Password, profile, login safety, and account access support.'],
            ['icon' => 'OR', 'title' => 'Orders & Delivery', 'text' => 'Track orders, understand statuses, and review delivery guidance.'],
            ['icon' => 'PM', 'title' => 'Payments', 'text' => 'Card, BenefitPay, checkout safety, and payment confirmation help.'],
            ['icon' => 'RT', 'title' => 'Returns', 'text' => 'Return eligibility, product condition rules, and refund timing.'],
            ['icon' => 'PR', 'title' => 'Products', 'text' => 'Product details, availability, ratings, and seller information.'],
            ['icon' => 'SP', 'title' => 'Support', 'text' => 'Contact NextPick support for order or account questions.'],
        ],
        'faq' => [
            ['q' => 'How do I contact support?', 'a' => 'Email support@nextpick.com or use the support contact information shown on this page.'],
            ['q' => 'Can I use the Help Center without logging in?', 'a' => 'Yes. These footer information pages are public and do not require an account.'],
            ['q' => 'Where can I check order details?', 'a' => 'Buyers can sign in and open their Orders page to view order history and order status.'],
        ],
        'aside_title' => 'Contact Support',
        'aside' => ['Email: support@nextpick.com', 'Phone: +973 123 4567', 'Location: Manama, Bahrain'],
    ],
    'payments' => [
        'title' => 'Payments',
        'eyebrow' => 'Checkout Confidence',
        'intro' => 'NextPick supports simple payment options for electronics shopping, with clear confirmation and refund support.',
        'stats' => [
            ['value' => 'Secure', 'label' => 'Payment handling'],
            ['value' => 'Fast', 'label' => 'Checkout flow'],
            ['value' => 'Refund', 'label' => 'Support available'],
        ],
        'cards_title' => 'Payment Methods',
        'cards' => [
            ['icon' => 'CC', 'title' => 'Credit and Debit Cards', 'text' => 'Use major cards during checkout where card payment is available.'],
            ['icon' => 'BP', 'title' => 'BenefitPay', 'text' => 'BenefitPay is supported as a familiar local payment option.'],
            ['icon' => 'COD', 'title' => 'Cash on Delivery', 'text' => 'Pay when your order arrives if COD is available for the order.'],
            ['icon' => 'RF', 'title' => 'Refund Support', 'text' => 'Approved refunds are handled according to the return and payment method used.'],
        ],
        'sections' => [
            ['heading' => 'Payment safety', 'items' => ['Review order totals before confirming checkout.', 'Never share card details by email or message.', 'Contact support if a payment appears duplicated.']],
            ['heading' => 'Payment confirmation', 'items' => ['After checkout, buyers can review order details from their account.', 'Payment status depends on the chosen method and order processing.']],
        ],
    ],
    'product_returns' => [
        'title' => 'Product Returns',
        'eyebrow' => 'Returns and Refunds',
        'intro' => 'Return eligible products through a clear process when items are unused, complete, and in original condition.',
        'stats' => [
            ['value' => '30-Day', 'label' => 'Return guidance'],
            ['value' => 'Easy', 'label' => 'Step process'],
            ['value' => 'Support', 'label' => 'Available'],
        ],
        'cards_title' => 'Return Steps',
        'cards' => [
            ['icon' => '01', 'title' => 'Review Eligibility', 'text' => 'Check that the product is unused, undamaged, and includes accessories.'],
            ['icon' => '02', 'title' => 'Contact Support', 'text' => 'Share your order information and reason for the return.'],
            ['icon' => '03', 'title' => 'Prepare Product', 'text' => 'Pack the item safely in its original packaging where possible.'],
            ['icon' => '04', 'title' => 'Refund Review', 'text' => 'Refund timing depends on approval and the payment method.'],
        ],
        'sections' => [
            ['heading' => 'Return conditions', 'items' => ['Products should be unused and in original condition.', 'Accessories, manuals, and packaging should be included.', 'Damaged or incomplete returns may be rejected.']],
            ['heading' => 'Refund timing', 'items' => ['Refunds are reviewed after the returned item is checked.', 'Bank or card processing may add extra days after approval.']],
        ],
    ],
    'faq' => [
        'title' => 'FAQ',
        'eyebrow' => 'Common Questions',
        'intro' => 'Quick answers for account, order, payment, return, and product questions.',
        'stats' => [
            ['value' => '5', 'label' => 'Question groups'],
            ['value' => 'Quick', 'label' => 'Answers'],
            ['value' => 'Public', 'label' => 'No login needed'],
        ],
        'faq' => [
            ['q' => 'How do I create an account?', 'a' => 'Use the registration page, verify your details, and sign in with your email and password.'],
            ['q' => 'How can I track an order?', 'a' => 'Buyers can open Orders after logging in to view order history and current status.'],
            ['q' => 'Which payment methods are supported?', 'a' => 'The platform supports card payment, BenefitPay, and Cash on Delivery where available.'],
            ['q' => 'Can I return a product?', 'a' => 'Eligible unused products can be returned according to the Product Returns guidance.'],
            ['q' => 'Where do product ratings come from?', 'a' => 'Ratings and reviews are submitted by buyers through the product review flow.'],
        ],
        'cards_title' => 'FAQ Categories',
        'cards' => [
            ['icon' => 'AC', 'title' => 'Account', 'text' => 'Login, profile, and security questions.'],
            ['icon' => 'OR', 'title' => 'Orders', 'text' => 'Order status, delivery, and order history.'],
            ['icon' => 'PY', 'title' => 'Payments', 'text' => 'Checkout, confirmation, and refund basics.'],
            ['icon' => 'RT', 'title' => 'Returns', 'text' => 'Eligibility, return process, and timing.'],
            ['icon' => 'PD', 'title' => 'Products', 'text' => 'Details, stock, ratings, and categories.'],
        ],
    ],
    'stores' => [
        'title' => 'Stores',
        'eyebrow' => 'Locations',
        'intro' => 'NextPick support and store information for shoppers in Bahrain.',
        'stats' => [
            ['value' => 'Manama', 'label' => 'Main office'],
            ['value' => '6 days', 'label' => 'Weekly support'],
            ['value' => 'Local', 'label' => 'Bahrain service'],
        ],
        'cards_title' => 'Branch Information',
        'cards' => [
            ['icon' => 'MN', 'title' => 'Manama Support Desk', 'text' => 'Customer support, order questions, and general assistance.'],
            ['icon' => 'RF', 'title' => 'Returns Counter', 'text' => 'Return coordination and product inspection by appointment.'],
            ['icon' => 'PK', 'title' => 'Pickup Support', 'text' => 'Future pickup assistance for selected orders and offers.'],
        ],
        'sections' => [
            ['heading' => 'Working hours', 'items' => ['Monday to Friday: 09:00 - 18:00', 'Saturday: 10:00 - 16:00', 'Sunday: Closed']],
        ],
    ],
    'corporate' => [
        'title' => 'Corporate Website',
        'eyebrow' => 'About NextPick',
        'intro' => 'NextPick is an electronics commerce platform focused on practical shopping, seller tools, and clear customer support.',
        'stats' => [
            ['value' => '3', 'label' => 'User roles'],
            ['value' => 'Bahrain', 'label' => 'Market focus'],
            ['value' => 'Electronics', 'label' => 'Core category'],
        ],
        'cards_title' => 'Company Values',
        'cards' => [
            ['icon' => 'TR', 'title' => 'Trust', 'text' => 'Clear product information and support-first commerce.'],
            ['icon' => 'SP', 'title' => 'Speed', 'text' => 'Simple workflows for browsing, checkout, and order management.'],
            ['icon' => 'CL', 'title' => 'Clarity', 'text' => 'Readable policies, clean interfaces, and predictable user journeys.'],
        ],
        'sections' => [
            ['heading' => 'Mission', 'items' => ['Make electronics shopping easier to browse, compare, order, and manage.', 'Support buyers, sellers, and admins with focused platform tools.']],
        ],
    ],
    'offers' => [
        'title' => 'Exclusive Offers',
        'eyebrow' => 'Deals and Promotions',
        'intro' => 'Explore seasonal electronics deals, bundle ideas, and limited shopping offers.',
        'stats' => [
            ['value' => 'Deals', 'label' => 'Featured offers'],
            ['value' => 'Bundles', 'label' => 'Value picks'],
            ['value' => 'Limited', 'label' => 'Promo windows'],
        ],
        'cards_title' => 'Featured Offers',
        'cards' => [
            ['icon' => '10', 'title' => 'Accessory Savings', 'text' => 'Save on selected accessories when purchasing compatible electronics.'],
            ['icon' => 'BD', 'title' => 'Bundle Deals', 'text' => 'Pair laptops, audio, and smart devices for better value.'],
            ['icon' => 'NW', 'title' => 'New Arrival Picks', 'text' => 'Featured discounts on selected recently listed products.'],
            ['icon' => 'ST', 'title' => 'Student Essentials', 'text' => 'Practical tech combinations for study, work, and everyday use.'],
        ],
    ],
    'career' => [
        'title' => 'Career',
        'eyebrow' => 'Work With NextPick',
        'intro' => 'Join a practical commerce team focused on electronics, customer support, and reliable platform operations.',
        'stats' => [
            ['value' => 'Support', 'label' => 'Customer roles'],
            ['value' => 'Tech', 'label' => 'Platform roles'],
            ['value' => 'Growth', 'label' => 'Learning focus'],
        ],
        'cards_title' => 'Open Role Areas',
        'cards' => [
            ['icon' => 'CS', 'title' => 'Customer Support Associate', 'text' => 'Help shoppers with accounts, orders, returns, and product questions.'],
            ['icon' => 'OP', 'title' => 'Operations Coordinator', 'text' => 'Coordinate order workflows, sellers, and store support tasks.'],
            ['icon' => 'QA', 'title' => 'Product Quality Assistant', 'text' => 'Review product information and support return inspection workflows.'],
        ],
        'sections' => [
            ['heading' => 'Benefits', 'items' => ['Hands-on commerce experience.', 'Clear team processes and practical training.', 'Opportunities to support both customers and sellers.']],
            ['heading' => 'Apply', 'items' => ['Send your CV and preferred role area to careers@nextpick.com.']],
        ],
    ],
    'privacy_policy' => [
        'title' => 'Privacy Policy',
        'eyebrow' => 'Data Privacy',
        'intro' => 'This page explains how NextPick handles account, order, and support information in a clear and practical way.',
        'stats' => [
            ['value' => 'Account', 'label' => 'Data'],
            ['value' => 'Order', 'label' => 'Data'],
            ['value' => 'Support', 'label' => 'Data'],
        ],
        'sections' => [
            ['heading' => 'Information we use', 'items' => ['Account details such as name, email, phone, and role.', 'Order and payment-related information needed to process purchases.', 'Support messages used to answer questions and resolve issues.']],
            ['heading' => 'How information is used', 'items' => ['To manage accounts and authentication.', 'To process orders, returns, and support requests.', 'To improve platform reliability and user experience.']],
            ['heading' => 'Your choices', 'items' => ['Keep your profile details accurate.', 'Contact support for privacy-related questions.', 'Do not share account credentials with anyone.']],
        ],
    ],
    'cookie_settings' => [
        'title' => 'Cookie Settings',
        'eyebrow' => 'Preferences',
        'intro' => 'Cookie preferences help explain how the site can use essential, analytics, and preference cookies.',
        'stats' => [
            ['value' => 'Required', 'label' => 'Essential cookies'],
            ['value' => 'Optional', 'label' => 'Analytics cookies'],
            ['value' => 'Local', 'label' => 'Preferences'],
        ],
        'toggles' => [
            ['title' => 'Essential cookies', 'text' => 'Required for login, sessions, checkout, and core security.', 'enabled' => true, 'locked' => true],
            ['title' => 'Analytics cookies', 'text' => 'Help understand page usage and improve the shopping experience.', 'enabled' => false, 'locked' => false],
            ['title' => 'Preference cookies', 'text' => 'Remember simple interface preferences where supported.', 'enabled' => true, 'locked' => false],
        ],
        'sections' => [
            ['heading' => 'Cookie note', 'items' => ['This page provides a static preferences interface for project display.', 'A future implementation can save preferences in browser storage or the database.']],
        ],
    ],
    'terms' => [
        'title' => 'Terms and Conditions',
        'eyebrow' => 'Platform Rules',
        'intro' => 'These terms outline practical responsibilities for using NextPick as a buyer, seller, or administrator.',
        'stats' => [
            ['value' => 'Fair', 'label' => 'Use'],
            ['value' => 'Clear', 'label' => 'Orders'],
            ['value' => 'Safe', 'label' => 'Accounts'],
        ],
        'sections' => [
            ['heading' => 'User responsibilities', 'items' => ['Provide accurate account and contact information.', 'Use the platform respectfully and only for legitimate activity.', 'Keep login credentials private.']],
            ['heading' => 'Orders and payments', 'items' => ['Review product details and order totals before checkout.', 'Payment and delivery options may depend on order availability.', 'Order status updates are managed through the platform workflow.']],
            ['heading' => 'Products and content', 'items' => ['Sellers are responsible for accurate product information.', 'Reviews should be relevant, honest, and respectful.']],
        ],
    ],
    'imprint' => [
        'title' => 'Imprint',
        'eyebrow' => 'Legal and Contact',
        'intro' => 'Official company and support information for NextPickStore.',
        'stats' => [
            ['value' => 'NEXTPICK', 'label' => 'Trading name'],
            ['value' => 'Manama', 'label' => 'Location'],
            ['value' => '+973', 'label' => 'Bahrain contact'],
        ],
        'sections' => [
            ['heading' => 'Company information', 'items' => ['NEXTPICK / NextPickStore', 'Manama, Kingdom of Bahrain', 'E-commerce electronics platform']],
            ['heading' => 'Contact details', 'items' => ['Phone: +973 123 4567', 'Email: support@nextpick.com', 'Support hours: Monday to Friday, 09:00 - 18:00']],
            ['heading' => 'Responsible contact', 'items' => ['For platform, policy, and support questions, contact support@nextpick.com.']],
        ],
    ],
];

$page = $pages[$pageKey] ?? $pages['help_center'];
$search = trim($_GET['search'] ?? '');

function np_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function np_render_stats($stats)
{
    foreach ($stats ?? [] as $stat) {
        echo '<div class="np-stat"><strong>' . np_h($stat['value']) . '</strong><span>' . np_h($stat['label']) . '</span></div>';
    }
}

function np_render_cards($cards)
{
    foreach ($cards ?? [] as $card) {
        echo '<article class="np-card">';
        echo '<div class="np-card-icon">' . np_h($card['icon']) . '</div>';
        echo '<h3>' . np_h($card['title']) . '</h3>';
        echo '<p>' . np_h($card['text']) . '</p>';
        echo '</article>';
    }
}

function np_render_sections($sections)
{
    foreach ($sections ?? [] as $section) {
        echo '<section class="np-section">';
        echo '<h2>' . np_h($section['heading']) . '</h2>';
        echo '<ul>';
        foreach ($section['items'] as $item) {
            echo '<li>' . np_h($item) . '</li>';
        }
        echo '</ul>';
        echo '</section>';
    }
}

function np_render_faq($items)
{
    foreach ($items ?? [] as $item) {
        echo '<details class="np-faq-item">';
        echo '<summary><span>' . np_h($item['q']) . '</span><span class="np-faq-arrow">+</span></summary>';
        echo '<p>' . np_h($item['a']) . '</p>';
        echo '</details>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo np_h($page['title']); ?> - NextPick</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f4f4f6; color: #222; font-family: Arial, Helvetica, sans-serif; }
        a { color: inherit; text-decoration: none; }
        .np-wrapper { max-width: calc(100% - 50px); margin: 25px auto; min-height: 90vh; background: #fff; border: 1px solid #e8e8e8; display: flex; flex-direction: column; }
        .np-topbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 16px 22px; background: #fff; border-radius: 10px; }
        .np-logo { height: 24px; width: auto; object-fit: contain; display: block; }
        .np-nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .np-nav a { padding: 9px 12px; border-radius: 10px; color: #4b5563; font-size: 13px; font-weight: 700; }
        .np-nav a:hover, .np-nav a.active { background: #eef2ff; color: #3158ff; }
        .np-content { padding: 28px; background: #fcfcfc; border-top: 1px solid #ececec; }
        .np-hero { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.75fr); gap: 18px; margin-bottom: 18px; }
        .np-panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 22px; }
        .np-eyebrow { color: #3158ff; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 9px; }
        h1 { color: #1f2937; font-size: 32px; line-height: 1.15; margin-bottom: 10px; }
        .np-intro { color: #5f6b7a; font-size: 15px; line-height: 1.8; max-width: 780px; }
        .np-search { display: flex; gap: 8px; margin-top: 18px; }
        .np-search input { flex: 1; min-width: 0; height: 48px; border: 1px solid #d8dee8; border-radius: 12px; padding: 0 14px; font-size: 14px; outline: none; }
        .np-search input:focus { border-color: #3158ff; box-shadow: 0 0 0 3px rgba(49, 88, 255, .08); }
        .np-btn { border: none; background: #3158ff; color: #fff; border-radius: 10px; padding: 0 16px; min-height: 42px; font-size: 13px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        .np-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .np-stat { min-height: 90px; background: #f8fbff; border: 1px solid #e8edf5; border-radius: 16px; padding: 16px; display: flex; flex-direction: column; justify-content: center; gap: 5px; }
        .np-stat strong { color: #3158ff; font-size: 24px; line-height: 1; }
        .np-stat span { color: #606a78; font-size: 13px; font-weight: 700; }
        .np-main-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(280px, .8fr); gap: 18px; align-items: start; }
        .np-card-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .np-card { min-height: 176px; background: #fff; border: 1px solid #e8e8e8; border-radius: 18px; padding: 20px; transition: .2s ease; box-shadow: 0 2px 8px rgba(17, 24, 39, .04); }
        .np-card:hover { border-color: #3158ff; background: #f8fbff; box-shadow: 0 10px 24px rgba(49, 88, 255, .1); transform: translateY(-3px); }
        .np-card-icon { width: 52px; height: 52px; border-radius: 16px; background: #f3f6fb; border: 1px solid #e8edf5; color: #3158ff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; margin-bottom: 14px; }
        .np-card h3 { font-size: 18px; color: #1f2937; margin-bottom: 8px; line-height: 1.3; }
        .np-card p { font-size: 13px; color: #6b7280; line-height: 1.65; }
        .np-panel-title { font-size: 22px; font-weight: 800; color: #1f2937; margin-bottom: 14px; }
        .np-section { border-top: 1px solid #ececec; padding-top: 16px; margin-top: 16px; }
        .np-section:first-child { border-top: none; padding-top: 0; margin-top: 0; }
        .np-section h2 { color: #1f2937; font-size: 20px; margin-bottom: 10px; }
        .np-section ul { padding-left: 20px; }
        .np-section li { color: #4b5563; font-size: 14px; line-height: 1.75; margin-bottom: 7px; }
        .np-faq-list { display: flex; flex-direction: column; gap: 6px; }
        .np-faq-item { border-bottom: 1px solid #ececec; padding-bottom: 6px; }
        .np-faq-item:last-child { border-bottom: none; }
        .np-faq-item summary { list-style: none; cursor: pointer; display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; font-size: 15px; font-weight: 700; color: #222; }
        .np-faq-item summary::-webkit-details-marker { display: none; }
        .np-faq-item p { color: #666; font-size: 14px; line-height: 1.6; padding: 0 0 10px; }
        .np-faq-arrow { color: #3158ff; flex-shrink: 0; }
        .np-contact-list { display: grid; gap: 10px; }
        .np-contact-list div { border: 1px solid #ededed; border-radius: 12px; padding: 12px; color: #555; font-size: 14px; background: #fff; }
        .np-toggle-list { display: grid; gap: 12px; }
        .np-toggle-row { border: 1px solid #e8e8e8; border-radius: 16px; padding: 16px; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: #fff; }
        .np-toggle-row h3 { font-size: 16px; margin-bottom: 5px; color: #1f2937; }
        .np-toggle-row p { color: #666; font-size: 13px; line-height: 1.5; }
        .np-switch { width: 48px; height: 28px; border-radius: 999px; background: #d1d5db; padding: 3px; flex-shrink: 0; }
        .np-switch span { display: block; width: 22px; height: 22px; border-radius: 50%; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.18); }
        .np-switch.on { background: #3158ff; }
        .np-switch.on span { margin-left: 20px; }
        .np-save-note { margin-top: 12px; color: #667085; font-size: 13px; }
        .footer { margin-top: 0; border-radius: 0; }
        @media (max-width: 1120px) { .np-hero, .np-main-grid { grid-template-columns: 1fr; } .np-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 720px) { .np-wrapper { max-width: calc(100% - 22px); margin: 12px auto; } .np-topbar { align-items: flex-start; flex-direction: column; } .np-content { padding: 16px; } h1 { font-size: 26px; } .np-search { flex-direction: column; } .np-stats, .np-card-grid { grid-template-columns: 1fr; } .np-toggle-row { align-items: flex-start; } }
    </style>
</head>
<body>
<div class="np-wrapper">
    <header class="np-topbar">
        <a href="<?php echo $projectBase; ?>/roles/buyer/dashboard.php" aria-label="NextPick home">
            <img class="np-logo" src="<?php echo $logoPath; ?>" alt="NextPick Logo">
        </a>
        <nav class="np-nav" aria-label="Footer page navigation">
            <a class="<?php echo $pageKey === 'help_center' ? 'active' : ''; ?>" href="<?php echo $projectBase; ?>/public/help_center.php">Help</a>
            <a class="<?php echo $pageKey === 'payments' ? 'active' : ''; ?>" href="<?php echo $projectBase; ?>/public/payments.php">Payments</a>
            <a class="<?php echo $pageKey === 'product_returns' ? 'active' : ''; ?>" href="<?php echo $projectBase; ?>/public/product_returns.php">Returns</a>
            <a class="<?php echo $pageKey === 'faq' ? 'active' : ''; ?>" href="<?php echo $projectBase; ?>/public/faq.php">FAQ</a>
        </nav>
    </header>

    <main class="np-content">
        <section class="np-hero">
            <div class="np-panel">
                <div class="np-eyebrow"><?php echo np_h($page['eyebrow']); ?></div>
                <h1><?php echo np_h($page['title']); ?></h1>
                <p class="np-intro"><?php echo np_h($page['intro']); ?></p>
                <?php if ($pageKey === 'help_center') { ?>
                    <form class="np-search" method="get">
                        <input type="text" name="search" value="<?php echo np_h($search); ?>" placeholder="Search help articles, payments, returns, orders">
                        <button class="np-btn" type="submit">Search</button>
                    </form>
                <?php } ?>
            </div>
            <aside class="np-panel">
                <div class="np-stats"><?php np_render_stats($page['stats'] ?? []); ?></div>
            </aside>
        </section>

        <section class="np-main-grid">
            <div class="np-panel">
                <?php if (!empty($page['cards'])) { ?>
                    <div class="np-panel-title"><?php echo np_h($page['cards_title'] ?? 'Information'); ?></div>
                    <div class="np-card-grid"><?php np_render_cards($page['cards']); ?></div>
                <?php } ?>

                <?php if (!empty($page['toggles'])) { ?>
                    <div class="np-panel-title">Cookie Preferences</div>
                    <div class="np-toggle-list">
                        <?php foreach ($page['toggles'] as $toggle) { ?>
                            <div class="np-toggle-row">
                                <div>
                                    <h3><?php echo np_h($toggle['title']); ?></h3>
                                    <p><?php echo np_h($toggle['text']); ?></p>
                                </div>
                                <div class="np-switch <?php echo $toggle['enabled'] ? 'on' : ''; ?>" aria-hidden="true"><span></span></div>
                            </div>
                        <?php } ?>
                    </div>
                    <button class="np-btn" type="button" style="margin-top:14px;">Save Preferences</button>
                    <p class="np-save-note">Static display button for this project page.</p>
                <?php } ?>

                <?php if (!empty($page['sections'])) { ?>
                    <?php np_render_sections($page['sections']); ?>
                <?php } ?>
            </div>

            <aside class="np-panel">
                <?php if (!empty($page['faq'])) { ?>
                    <div class="np-panel-title">Frequently Asked Questions</div>
                    <div class="np-faq-list"><?php np_render_faq($page['faq']); ?></div>
                <?php } elseif (!empty($page['aside'])) { ?>
                    <div class="np-panel-title"><?php echo np_h($page['aside_title'] ?? 'Information'); ?></div>
                    <div class="np-contact-list">
                        <?php foreach ($page['aside'] as $line) { ?>
                            <div><?php echo np_h($line); ?></div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="np-panel-title">Need Help?</div>
                    <div class="np-contact-list">
                        <div>Email: support@nextpick.com</div>
                        <div>Phone: +973 123 4567</div>
                        <div>Working hours: Monday to Saturday</div>
                    </div>
                <?php } ?>
            </aside>
        </section>
    </main>

    <?php include_once dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
</body>
</html>
