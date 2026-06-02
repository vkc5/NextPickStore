<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';

requireRole(['Buyer']);

$conn = getConnection();
$userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = mysqli_prepare($conn, "
    SELECT u.full_name, u.email, u.phone_number, u.address
    FROM nps_users u
    WHERE u.user_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$categoryResult = mysqli_query($conn, "
    SELECT c.category_id, c.category_name
    FROM nps_categories c
    ORDER BY c.category_name ASC
    LIMIT 8
");

$categoriess = [];
if ($categoryResult) {
    while ($r = mysqli_fetch_assoc($categoryResult)) {
        $categoriess[] = $r;
    }
}

$errors = $_SESSION['profile_errors'] ?? [];
$old = $_SESSION['profile_old'] ?? [];
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_errors'], $_SESSION['profile_old'], $_SESSION['profile_success']);

$fullName = $old['full_name'] ?? ($user['full_name'] ?? '');
$phoneNumber = $old['phone_number'] ?? ($user['phone_number'] ?? '');
$address = $old['address'] ?? ($user['address'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Profile - NextPick</title>
    <link rel="stylesheet" href="../../assets/css/buyer-dropdown.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        a { text-decoration: none; color: inherit; }
        img { display: block; max-width: 100%; }

        body { font-family: Arial, Helvetica, sans-serif; background: #f4f4f6; color: #222; }

        .page-wrapper {
            max-width: calc(100% - 50px);
            margin: 25px auto;
            background: #fff;
            border: 1px solid #e8e8e8;
            min-height: 90vh;
            overflow: hidden;
        }

        .buyer-header { padding: 28px; background: #fff; border-bottom: 1px solid #ececec; }
        .buyer-nav {
            width: 100%;
            min-height: 74px;
            background: #fff;
            border-radius: 16px;
            padding: 0 32px;
            display: grid;
            grid-template-columns: 230px 210px minmax(300px, 420px) auto;
            align-items: center;
            gap: 28px;
            box-shadow: 0 10px 28px rgba(17,24,39,0.06);
        }
        .nav-logo { display: flex; align-items: center; }
        .nav-logo img { width: 150px; height: auto; object-fit: contain; }

        .all-products-btn {
            height: 46px;
            padding: 0 22px;
            border-radius: 10px;
            background: #fff;
            color: #222;
            border: 1px solid #f0f0f0;
            box-shadow: 0 6px 18px rgba(17,24,39,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
            width: 100%;
            cursor: pointer;
        }
        .dropdown-menu {
            position: absolute;
            top: 54px;
            left: 0;
            width: 230px;
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 14px;
            box-shadow: 0 18px 35px rgba(17,24,39,0.12);
        }
        .dropdown-menu a {
            display: block;
            padding: 11px 12px;
            border-radius: 10px;
            color: #333;
            font-size: 13px;
            font-weight: 600;
        }
        .dropdown-divider { height: 1px; background: #eee; margin: 8px 0; }

        .buyer-search {
            height: 42px;
            width: 100%;
            background: #fff;
            border: 1px solid #dbe4ff;
            border-radius: 10px;
            padding: 0 8px 0 18px;
            display: flex;
            align-items: center;
            overflow: hidden;
        }
        .buyer-search input {
            flex: 1;
            height: 100%;
            border: none;
            outline: none;
            background: transparent;
            color: #111827;
            font-size: 13px;
            padding: 0 10px;
        }
        .buyer-search button {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 50%;
            background: transparent;
            color: #3158ff;
            cursor: pointer;
            font-size: 15px;
        }
        .nav-actions { display: flex; align-items: center; justify-content: flex-end; gap: 14px; }
        .icon-btn {
            width: 38px;
            height: 38px;
            background: #f7f8fc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #111827;
            transition: 0.2s ease;
        }
        .icon-btn:hover { background: #eef3ff; color: #3158ff; }
        .logout-btn {
            background: #1A4DE1;
            color: #fff;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .content { padding: 28px; background: #fcfcfc; }
        .page-hero {
            background: linear-gradient(135deg, #eef3ff, #ffffff);
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 26px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
        }
        .page-hero h1 { font-size: 32px; color: #111827; margin-bottom: 8px; }
        .page-hero p { color: #666; font-size: 14px; line-height: 1.6; }

        .profile-layout {
            display: grid;
            grid-template-columns: 340px minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }
        .profile-card,
        .form-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            padding: 24px;
        }
        .avatar {
            width: 76px;
            height: 76px;
            border-radius: 18px;
            background: #eef3ff;
            color: #3158ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 18px;
        }
        .profile-card h2 { font-size: 22px; color: #111827; margin-bottom: 6px; }
        .profile-card p { font-size: 14px; color: #666; line-height: 1.6; }
        .profile-meta { display: grid; gap: 12px; margin-top: 20px; }
        .profile-meta div { border-top: 1px solid #f0f0f0; padding-top: 12px; }
        .profile-meta span { display: block; color: #888; font-size: 12px; margin-bottom: 4px; }
        .profile-meta strong { color: #111827; font-size: 14px; line-height: 1.5; }

        .form-card h2 { font-size: 22px; color: #111827; margin-bottom: 18px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 14px; font-weight: 800; color: #111827; }
        .form-control {
            width: 100%;
            min-height: 46px;
            border: 1px solid #d7d7d7;
            border-radius: 12px;
            outline: none;
            padding: 12px 14px;
            font-size: 14px;
            background: #fff;
            color: #111827;
        }
        textarea.form-control { min-height: 120px; resize: vertical; }
        .form-control:focus { border-color: #3158ff; box-shadow: 0 0 0 4px #eef3ff; }
        .readonly { background: #f7f8fc; color: #777; }
        .field-error { color: #d82121; font-size: 12px; font-weight: 700; }
        .alert {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .alert-success { background: #e7faed; color: #1b8a46; border: 1px solid #c9f0d6; }
        .alert-error { background: #fff1f1; color: #d82121; border: 1px solid #ffd1d1; }
        .btn-primary {
            margin-top: 18px;
            border: none;
            border-radius: 12px;
            background: #1A4DE1;
            color: #fff;
            padding: 13px 22px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
        }
        .btn-primary:hover { background: #123dcc; }
        @media (max-width: 1100px) {
            .buyer-nav { grid-template-columns: 1fr; padding: 20px; gap: 16px; }
            .nav-logo, .buyer-search, .nav-actions, .products-dropdown { width: 100%; }
            .nav-actions { justify-content: center; }
            .profile-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .page-wrapper { max-width: 100%; margin: 0; }
            .buyer-header, .content { padding: 18px; }
            .page-hero { flex-direction: column; align-items: flex-start; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
<div class="page-wrapper">
    <header class="buyer-header">
        <div class="buyer-nav">
            <a href="dashboard.php" class="nav-logo">
                <img src="../../assets/images/Logos/nextpickstore-logo.png" alt="NextPick Logo">
            </a>

            <div class="products-dropdown">
                <button type="button" class="all-products-btn">All Products</button>
                <div class="dropdown-menu">
                    <a href="ViewAllProducts.php?type=allproducts">All Products</a>
                    <a href="ViewAllProducts.php?type=latest">Latest Arrivals</a>
                    <a href="ViewAllProducts.php?type=bestSeller">Best Sellers</a>
                    <?php if (!empty($categoriess)): ?>
                        <div class="dropdown-divider"></div>
                        <?php foreach ($categoriess as $cat): ?>
                            <a href="productCategory.php?id=<?php echo $cat['category_id']; ?>&name=<?php echo urlencode($cat['category_name']); ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <form class="buyer-search" method="GET" action="dashboard.php">
                <input type="text" name="q" placeholder="I am Searching for...">
                <button type="submit" aria-label="Search">⌕</button>
            </form>

            <div class="nav-actions">
                <a href="cart.php" class="icon-btn" title="Cart">🛒</a>
                <a href="orders.php" class="icon-btn" title="Orders">🧾</a>
                <a href="profile.php" class="icon-btn profile-link active" title="Profile">👤</a>
                <a href="../../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <main class="content">
        <section class="page-hero">
            <div>
                <h1>Buyer Profile</h1>
                <p>View and update your account contact and delivery information.</p>
            </div>
        </section>

        <section class="profile-layout">
            <aside class="profile-card">
                <div class="avatar"><?php echo strtoupper(substr($fullName ?: 'B', 0, 1)); ?></div>
                <h2><?php echo htmlspecialchars($fullName ?: 'Buyer'); ?></h2>
                <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>

                <div class="profile-meta">
                    <div>
                        <span>Phone</span>
                        <strong><?php echo htmlspecialchars($phoneNumber ?: 'Not set'); ?></strong>
                    </div>
                    <div>
                        <span>Address</span>
                        <strong><?php echo htmlspecialchars($address ?: 'Not set'); ?></strong>
                    </div>
                </div>
            </aside>

            <section class="form-card">
                <h2>Edit Profile</h2>

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($errors['general']); ?></div>
                <?php endif; ?>

                <form method="POST" action="process_profile.php">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input class="form-control" type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($fullName); ?>" required>
                            <?php if (!empty($errors['full_name'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['full_name']); ?></span><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input class="form-control readonly" type="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <input class="form-control" type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phoneNumber); ?>">
                            <?php if (!empty($errors['phone_number'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['phone_number']); ?></span><?php endif; ?>
                        </div>

                        <div class="form-group full">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" required><?php echo htmlspecialchars($address); ?></textarea>
                            <?php if (!empty($errors['address'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['address']); ?></span><?php endif; ?>
                        </div>
                    </div>

                    <button class="btn-primary" type="submit">Save Profile</button>
                </form>
            </section>
        </section>
    </main>
</div>
</body>
</html>
