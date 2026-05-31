<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

$adminName = $_SESSION['full_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Comments - NextPick</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background: #e9e9e9; color: #222; }
        a { text-decoration: none; color: inherit; }

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

        .topbar img { height: 24px; width: auto; }

        .main-layout {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 28px;
        }

        .sidebar { padding-top: 8px; }

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

        .nav-link:hover img { transform: scale(1.08); }

        .nav-link.active {
            background: #eef3ff;
            color: #2155f5;
            font-weight: 600;
            box-shadow: 0 6px 16px rgba(33, 85, 245, 0.10);
        }

        .nav-link.logout-link { margin-top: 10px; }

        .content { padding-bottom: 30px; }

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

        .title-back-link:hover { color: #2155f5; }

        .back-arrow {
            font-size: 20px;
            transition: transform 0.25s ease;
        }

        .title-back-link:hover .back-arrow {
            transform: translateX(-3px);
        }

        .comments-layout {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 22px;
            align-items: start;
        }

        .filter-card, .table-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }

        .filter-card { padding: 20px; }

        .filter-card h3 {
            font-size: 16px;
            margin-bottom: 16px;
            font-weight: 700;
        }

        .filter-group { margin-bottom: 18px; }

        .filter-group label {
            display: block;
            font-size: 13px;
            margin-bottom: 8px;
            color: #444;
            font-weight: 600;
        }

        .search-box { position: relative; }

        .search-box input {
            width: 100%;
            height: 44px;
            border: 1px solid #d8dce8;
            border-radius: 12px;
            padding: 0 42px 0 14px;
            font-size: 14px;
            outline: none;
            transition: all 0.25s ease;
        }

        .search-box input:focus {
            border-color: #2155f5;
            box-shadow: 0 0 0 3px rgba(33, 85, 245, 0.10);
        }

        .search-box span {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
            font-size: 16px;
        }

        .clear-btn {
            width: 100%;
            height: 42px;
            border: 1px solid #4a4a4a;
            border-radius: 999px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .table-card { overflow: hidden; }

        .table-top {
            padding: 20px 22px;
            font-size: 16px;
            font-weight: 700;
        }

        .table-wrapper {
            height: 390px;
            overflow-y: auto;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #dfe1e7;
            color: #333;
            text-align: left;
            padding: 14px 18px;
            font-size: 13px;
        }

        tbody td {
            padding: 14px 18px;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #ececec;
            height: 58px;
            vertical-align: middle;
        }

        tbody tr:nth-child(even) { background: #f7f7f7; }

        .product-thumb {
            width: 80px;
            height: 45px;
            border-radius: 8px;
            object-fit: cover;
            background: #f1f1f1;
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            transition: all 0.25s ease;
            font-size: 18px;
            color: #1f2757;
        }

        .view-btn:hover {
            background: #eef3ff;
            box-shadow: 0 6px 14px rgba(33, 85, 245, 0.10);
        }

        .table-loading, .table-empty {
            height: 390px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 22px;
            font-size: 14px;
            color: #666;
            text-align: center;
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

        @media (max-width: 1100px) {
            .comments-layout { grid-template-columns: 1fr; }
        }

        @media (max-width: 900px) {
            .main-layout { grid-template-columns: 1fr; }
            .sidebar { padding-bottom: 10px; }
        }

        @media (max-width: 640px) {
            .page-wrapper { padding: 18px 16px 0; }
            .footer-top { grid-template-columns: 1fr; }
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

                <a href="manage_products.php" class="nav-link active">
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
                <a href="manage_products.php" class="title-back-link">
                    <span class="back-arrow">←</span>
                    <span>Manage Comments</span>
                </a>
            </div>

            <div class="comments-layout">
                <div class="filter-card">
                    <h3>Filter</h3>

                    <div class="filter-group">
                        <label for="searchInput">Search by Product ID or Name</label>
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Enter product ID">
                            <span>⌕</span>
                        </div>
                    </div>

                    <button type="button" class="clear-btn" id="clearFiltersBtn">Clear</button>
                </div>

                <div class="table-card">
                    <div class="table-top">
                        Found: <span id="foundCount">0</span>
                    </div>

                    <div class="table-wrapper" id="productsTableContainer">
                        <div class="table-loading">Loading products...</div>
                    </div>
                </div>
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

<script>
    const searchInput = document.getElementById('searchInput');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    const productsTableContainer = document.getElementById('productsTableContainer');
    const foundCount = document.getElementById('foundCount');

    let debounceTimer;

    function loadProducts() {
        const search = searchInput.value.trim();
        const params = new URLSearchParams();
        params.append('search', search);

        productsTableContainer.innerHTML = '<div class="table-loading">Loading products...</div>';

        fetch('../../../ajax/admin_product_comment_search.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                foundCount.textContent = data.count;

                if (!data.rows || data.rows.length === 0) {
                    productsTableContainer.innerHTML = '<div class="table-empty">No products found.</div>';
                    return;
                }

                let html = `
                    <table>
                        <thead>
                            <tr>
                                <th>Product Thumbnail</th>
                                <th>Product Name</th>
                                <th>Comments</th>
                                <th>Status</th>
                                <th>ID</th>
                                <th>View Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                data.rows.forEach(product => {
                    html += `
                        <tr>
                            <td>
                                <img class="product-thumb" src="../../../${escapeHtml(product.image_path || 'assets/images/placeholder.png')}" alt="Product">
                            </td>
                            <td>${escapeHtml(product.product_name)}</td>
                            <td>${escapeHtml(product.total_comments)}</td>
                            <td>${escapeHtml(product.publish_status)}</td>
                            <td>${escapeHtml(product.product_id)}</td>
                            <td>
                                <a class="view-btn" href="view_comments.php?id=${encodeURIComponent(product.product_id)}" title="View comments">
                                    👁
                                </a>
                            </td>
                        </tr>
                    `;
                });

                html += '</tbody></table>';
                productsTableContainer.innerHTML = html;
            })
            .catch(() => {
                productsTableContainer.innerHTML = '<div class="table-empty">Failed to load products.</div>';
            });
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadProducts, 250);
    });

    clearFiltersBtn.addEventListener('click', function () {
        searchInput.value = '';
        loadProducts();
    });

    loadProducts();
</script>
</body>
</html>