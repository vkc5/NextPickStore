<?php
include_once '../../../includes/auth_guard.php';
include_once '../../../includes/config.php';
include_once '../../../includes/session.php';

requireRole(['Admin']);

$adminName = $_SESSION['full_name'] ?? 'Admin';
$conn = getConnection();

$categories = [];
$categoryResult = mysqli_query($conn, "SELECT category_name FROM nps_categories ORDER BY category_name ASC");
if ($categoryResult) {
    while ($row = mysqli_fetch_assoc($categoryResult)) {
        $categories[] = $row['category_name'];
    }
}

$brands = [];
$brandResult = mysqli_query($conn, "SELECT DISTINCT brand FROM nps_products WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand ASC");
if ($brandResult) {
    while ($row = mysqli_fetch_assoc($brandResult)) {
        $brands[] = $row['brand'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>View Products - NextPick</title>
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
                box-shadow: 0 6px 16px rgba(33,85,245,0.10);
                transform: translateX(3px);
            }

            .nav-link:hover img {
                transform: scale(1.08);
            }

            .nav-link.active {
                background: #eef3ff;
                color: #2155f5;
                font-weight: 600;
                box-shadow: 0 6px 16px rgba(33,85,245,0.10);
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

            .filter-bar {
                background: #fff;
                border-radius: 18px 18px 0 0;
                padding: 16px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.04);
                border-bottom: 1px solid #ececec;
            }

            .filter-row {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr 1.4fr;
                gap: 12px;
            }

            .filter-select,
            .search-input {
                width: 100%;
                height: 42px;
                border: 1px solid #d8dce8;
                border-radius: 10px;
                padding: 0 12px;
                font-size: 14px;
                outline: none;
                background: #fff;
                transition: 0.25s ease;
            }

            .search-input:focus,
            .filter-select:focus {
                border-color: #2155f5;
                box-shadow: 0 0 0 3px rgba(33,85,245,0.10);
            }

            .table-card {
                background: #fff;
                border-radius: 0 0 18px 18px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            }

            .table-wrapper {
                height: 420px;
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
                padding: 14px 12px;
                font-size: 13px;
            }

            tbody td {
                padding: 12px;
                font-size: 14px;
                color: #333;
                border-bottom: 1px solid #ececec;
                vertical-align: middle;
            }

            tbody tr:nth-child(even) {
                background: #f7f7f7;
            }

            .product-thumb {
                width: 42px;
                height: 42px;
                border-radius: 8px;
                object-fit: cover;
                background: #f1f1f1;
            }

            .action-wrap {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 34px;
                height: 34px;
                border :none;
                border-radius: 10px;
                transition: 0.25s ease;
            }

            .action-btn:hover {
                background: #eef3ff;
                box-shadow: 0 6px 14px rgba(33,85,245,0.10);
            }

            .action-btn img {
                width: 18px;
                height: 18px;
            }

            .publish-btn-icon:hover {
                background: #eafaf1;
                box-shadow: 0 6px 14px rgba(23, 185, 120, 0.12);
            }
            .delete-btn-icon:hover {
                background: #fff1f0;
                box-shadow: 0 6px 14px rgba(255,77,79,0.12);
            }

            .table-loading,
            .table-empty {
                height: 420px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #666;
                font-size: 14px;
            }

            .delete-modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.28);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                padding: 20px;
            }

            .delete-modal-overlay.show {
                display: flex;
            }

            .delete-modal {
                width: 100%;
                max-width: 360px;
                background: #fff;
                border-radius: 24px;
                padding: 26px 24px 22px;
                text-align: center;
                box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
                animation: modalPop 0.22s ease;
            }

            @keyframes modalPop {
                from {
                    opacity: 0;
                    transform: scale(0.92);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            .delete-modal-icon {
                width: 52px;
                height: 52px;
                margin: 0 auto 16px;
                border-radius: 50%;
                background: #fff1f0;
                color: #ff4d4f;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 26px;
                font-weight: 700;
                box-shadow: 0 0 0 8px rgba(255, 77, 79, 0.10);
            }

            .delete-modal h3 {
                font-size: 24px;
                margin-bottom: 10px;
                font-weight: 700;
                color: #222;
            }

            .delete-modal p {
                font-size: 14px;
                line-height: 1.6;
                color: #666;
                margin-bottom: 22px;
            }

            .delete-modal-actions {
                display: flex;
                gap: 12px;
            }

            .modal-cancel-btn,
            .modal-delete-btn {
                flex: 1;
                height: 46px;
                border: none;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: 0.22s ease;
            }

            .modal-cancel-btn {
                background: #f3f3f3;
                color: #444;
            }

            .modal-cancel-btn:hover {
                background: #e7e7e7;
            }

            .modal-delete-btn {
                background: #ff4d4f;
                color: #fff;
                box-shadow: 0 8px 18px rgba(255, 77, 79, 0.22);
            }

            .modal-delete-btn:hover {
                background: #ef3f41;
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
                .filter-row {
                    grid-template-columns: 1fr 1fr;
                }
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
                .filter-row {
                    grid-template-columns: 1fr;
                }
                .footer-top {
                    grid-template-columns: 1fr;
                }
                .publish-btn-icon:hover {
                    background: #eafaf1;
                    box-shadow: 0 6px 14px rgba(23, 185, 120, 0.12);
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
                            <span>Manage Products</span>
                        </a>
                    </div>

                    <div class="filter-bar">
                        <div class="filter-row">
                            <select id="categoryFilter" class="filter-select">
                                <option value="">Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select id="brandFilter" class="filter-select">
                                <option value="">Brand</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo htmlspecialchars($brand); ?>">
                                        <?php echo htmlspecialchars($brand); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select id="statusFilter" class="filter-select">
                                <option value="">Publish Status</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="hidden">Hidden</option>
                            </select>

                            <input type="text" id="searchInput" class="search-input" placeholder="Quick Search">
                        </div>
                    </div>

                    <div class="table-card">
                        <div class="table-wrapper" id="productsTableContainer">
                            <div class="table-loading">Loading products...</div>
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

        <div class="delete-modal-overlay" id="deleteModalOverlay">
            <div class="delete-modal">
                <div class="delete-modal-icon">!</div>
                <h3>Hide product</h3>
                <p>
                    Are you sure you want to hide this product?<br>
                    The product will no longer appear as published.
                </p>
                <div class="delete-modal-actions">
                    <button type="button" class="modal-cancel-btn" onclick="closeDeleteModal()">Cancel</button>
                    <button type="button" class="modal-delete-btn" id="confirmDeleteBtn">Hide</button>
                </div>
            </div>
        </div>

        <script>
            const categoryFilter = document.getElementById('categoryFilter');
            const brandFilter = document.getElementById('brandFilter');
            const statusFilter = document.getElementById('statusFilter');
            const searchInput = document.getElementById('searchInput');
            const productsTableContainer = document.getElementById('productsTableContainer');

            let debounceTimer;
            let formToSubmit = null;

            function loadProducts() {
                const params = new URLSearchParams();
                params.append('category', categoryFilter.value);
                params.append('brand', brandFilter.value);
                params.append('status', statusFilter.value);
                params.append('search', searchInput.value.trim());

                productsTableContainer.innerHTML = '<div class="table-loading">Loading products...</div>';

                fetch('../../../ajax/admin_product_search.php?' + params.toString())
                        .then(response => response.json())
                        .then(data => {
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
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Item ID</th>
                                        <th>Seller</th>
                                        <th>Manage</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;

                            data.rows.forEach(product => {
                                html += `
                                <tr>
                                    <td><img class="product-thumb" src="../../../${escapeHtml(product.image_path || 'assets/images/placeholder.png')}" alt="Product"></td>
                                    <td>${escapeHtml(product.product_name)}</td>
                                    <td>${escapeHtml(product.brand || '')}</td>
                                    <td>${escapeHtml(product.category_name)}</td>
                                    <td>$${escapeHtml(product.price)}</td>
                                    <td>#${escapeHtml(product.product_id)}</td>
                                    <td>${escapeHtml(product.seller_name)}</td>
                                    <td>
                                        <div class="action-wrap">
                                            <a class="action-btn" href="edit_product.php?id=${encodeURIComponent(product.product_id)}" title="Edit">
                                                <img src="../../../assets/images/icons/admin/edit.png" alt="Edit">
                                            </a>

                                                ${product.publish_status === 'hidden' ? `
                                                    <form action="process_publish_product.php" method="POST" class="delete-product-form">
                                                        <input type="hidden" name="product_id" value="${escapeHtml(product.product_id)}">
                                                        <button type="submit" class="action-btn publish-btn-icon" title="Publish product">
                                                            <img src="../../../assets/images/icons/admin/view.png" alt="Publish">
                                                        </button>
                                                    </form>
                                                ` : `
                                                    <form action="process_delete_product.php" method="POST" class="delete-product-form">
                                                        <input type="hidden" name="product_id" value="${escapeHtml(product.product_id)}">
                                                        <button type="button" class="action-btn delete-btn-icon" onclick="openDeleteModal(this.closest('form'))" title="Hide product">
                                                            <img src="../../../assets/images/icons/admin/delete.png" alt="Hide">
                                                        </button>
                                                    </form>
                                                `}
                                        </div>
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

            function openDeleteModal(form) {
                formToSubmit = form;
                document.getElementById('deleteModalOverlay').classList.add('show');
            }

            function closeDeleteModal() {
                formToSubmit = null;
                document.getElementById('deleteModalOverlay').classList.remove('show');
            }

            document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
                if (formToSubmit) {
                    formToSubmit.submit();
                }
            });

            document.getElementById('deleteModalOverlay').addEventListener('click', function (e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeDeleteModal();
                }
            });

            categoryFilter.addEventListener('change', loadProducts);
            brandFilter.addEventListener('change', loadProducts);
            statusFilter.addEventListener('change', loadProducts);

            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(loadProducts, 250);
            });

            loadProducts();
        </script>
    </body>
</html>