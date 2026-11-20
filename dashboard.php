
<?php
session_start();
require_once 'connection.php';

// Protect owner dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only owners can access this dashboard
if (($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: staff_dashboard.php");
    exit();
}

// =====================================
// DASHBOARD STATISTICS
// =====================================

// Total products and low-stock products
$result = $conn->query("
    SELECT
        COUNT(*) AS total_products,
        COALESCE(
            SUM(
                CASE
                    WHEN quantity <= minimum_stock THEN 1
                    ELSE 0
                END
            ), 0
        ) AS low_stock
    FROM products
");

$product_stats = $result->fetch_assoc();

$products = (int)$product_stats['total_products'];
$low_stock = (int)$product_stats['low_stock'];

// Today's sales and transactions
$result = $conn->query("
    SELECT
        COUNT(*) AS transactions,
        COALESCE(SUM(total_amount), 0) AS total_sales
    FROM sales
    WHERE sale_date >= CURDATE()
    AND sale_date < CURDATE() + INTERVAL 1 DAY
");

$sales_stats = $result->fetch_assoc();

$sales = (float)$sales_stats['total_sales'];
$transactions = (int)$sales_stats['transactions'];

// User information
$full_name = $_SESSION['full_name'] ?? 'User';
$current_date = date('l, d F Y');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SpazaSmart | Dashboard</title>

     <link rel="stylesheet" href="/SpazaSmart/style.css?v=2">
</head>

<body class="dashboard-page">

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="dashboard-sidebar">

        <div class="sidebar-brand">

            <div class="brand-icon">S</div>

            <div>
                <h2>SpazaSmart</h2>
                <span>SHOP MANAGEMENT</span>
            </div>

        </div>

        <div class="sidebar-label">MAIN MENU</div>

        <nav class="sidebar-nav">

            <a href="dashboard.php" class="sidebar-link active">
                <span class="nav-icon">⌂</span>
                Dashboard
            </a>

            <a href="products.php" class="sidebar-link">
                <span class="nav-icon">▦</span>
                Products
            </a>

            <a href="categories.php" class="sidebar-link">
                <span class="nav-icon">▤</span>
                Categories
            </a>

            <a href="sales.php" class="sidebar-link">
                <span class="nav-icon">▣</span>
                Sales
            </a>

            <a href="stock.php" class="sidebar-link">
                <span class="nav-icon">▥</span>
                Stock Management
            </a>

            <div class="sidebar-label">BUSINESS</div>

            <a href="suppliers.php" class="sidebar-link">
                <span class="nav-icon">♧</span>
                Suppliers
            </a>

            <a href="profit.php" class="sidebar-link">
                <span class="nav-icon">R</span>
                Profit
            </a>

            <a href="reports.php" class="sidebar-link">
                <span class="nav-icon">▧</span>
                Reports
            </a>

        </nav>

        <!-- USER INFORMATION -->
        <div class="sidebar-bottom">

            <div class="sidebar-user">

                <div class="user-avatar">
                    <?= htmlspecialchars(strtoupper(substr($full_name, 0, 1))) ?>
                </div>

                <div class="user-details">

                    <strong>
                        <?= htmlspecialchars($full_name) ?>
                    </strong>

                    <span>Shop Administrator</span>

                </div>

            </div>

            <a href="logout.php" class="sidebar-logout">
                ↪ Logout
            </a>

        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="dashboard-main">

        <!-- TOP BAR -->
        <header class="dashboard-topbar">

            <div>

                <span class="topbar-label">OVERVIEW</span>

                <h1>Dashboard</h1>

            </div>

            <div class="topbar-date">

                <span>Today</span>

                <strong>
                    <?= htmlspecialchars($current_date) ?>
                </strong>

            </div>

        </header>


        <!-- PAGE CONTENT -->
        <div class="dashboard-content">

            <!-- WELCOME SECTION -->
            <section class="dashboard-welcome">

                <div>

                    <span class="welcome-tag">
                        WELCOME BACK
                    </span>

                    <h2>
                        Hello, <?= htmlspecialchars($full_name) ?>!
                    </h2>

                    <p>
                        Here's what's happening with your shop today.
                        Manage your products, sales and stock all in one place.
                    </p>

                </div>

                <div class="welcome-decoration">
                    S
                </div>

            </section>


            <!-- STATISTICS -->
            <section class="dashboard-section">

                <div class="section-heading">

                    <div>

                        <h2>Today's Overview</h2>

                        <p>
                            Monitor your shop's daily performance.
                        </p>

                    </div>

                </div>


                <div class="dashboard-stats">

                    <!-- TOTAL PRODUCTS -->
                    <div class="dashboard-stat-card">

                        <div class="stat-top">

                            <span>Total Products</span>

                            <div class="stat-icon green-icon">
                                ▦
                            </div>

                        </div>

                        <h3>
                            <?= number_format($products) ?>
                        </h3>

                        <p>
                            Products registered in your shop
                        </p>

                    </div>


                    <!-- LOW STOCK -->
                    <div class="dashboard-stat-card">

                        <div class="stat-top">

                            <span>Low Stock</span>

                            <div class="stat-icon orange-icon">
                                !
                            </div>

                        </div>

                        <h3>
                            <?= number_format($low_stock) ?>
                        </h3>

                        <p>
                            Products requiring attention
                        </p>

                    </div>


                    <!-- TODAY'S SALES -->
                    <div class="dashboard-stat-card">

                        <div class="stat-top">

                            <span>Today's Sales</span>

                            <div class="stat-icon blue-icon">
                                R
                            </div>

                        </div>

                        <h3>
                            R<?= number_format($sales, 2) ?>
                        </h3>

                        <p>
                            Total sales recorded today
                        </p>

                    </div>


                    <!-- TRANSACTIONS -->
                    <div class="dashboard-stat-card">

                        <div class="stat-top">

                            <span>Transactions</span>

                            <div class="stat-icon purple-icon">
                                ▣
                            </div>

                        </div>

                        <h3>
                            <?= number_format($transactions) ?>
                        </h3>

                        <p>
                            Transactions recorded today
                        </p>

                    </div>

                </div>

            </section>


            <!-- LOW STOCK ALERT -->
            <?php if ($low_stock > 0): ?>

                <section class="dashboard-stock-alert">

                    <div class="alert-icon">
                        !
                    </div>

                    <div class="alert-content">

                        <h3>
                            Stock needs your attention
                        </h3>

                        <p>
                            You have <?= number_format($low_stock) ?>
                            product(s) that have reached their minimum stock level.
                        </p>

                    </div>

                    <a href="stock.php">
                        Manage Stock →
                    </a>

                </section>

            <?php else: ?>

                <section class="dashboard-stock-success">

                    <span>✓</span>

                    <div>

                        <strong>
                            Stock levels look good!
                        </strong>

                        <p>
                            No products are currently at or below their minimum stock level.
                        </p>

                    </div>

                </section>

            <?php endif; ?>


            <!-- SHOP MANAGEMENT -->
            <section class="dashboard-section">

                <div class="section-heading">

                    <div>

                        <h2>Shop Management</h2>

                        <p>
                            Quick access to your shop management tools.
                        </p>

                    </div>

                </div>


                <div class="dashboard-menu">

                    <!-- PRODUCTS -->
                    <a href="products.php" class="dashboard-menu-card">

                        <div class="menu-icon green-icon">
                            ▦
                        </div>

                        <h3>Products</h3>

                        <p>
                            Add, edit, delete and search for shop products.
                        </p>

                        <span>
                            Manage Products →
                        </span>

                    </a>


                    <!-- SALES -->
                    <a href="sales.php" class="dashboard-menu-card">

                        <div class="menu-icon blue-icon">
                            ▣
                        </div>

                        <h3>Sales</h3>

                        <p>
                            Record customer purchases and manage transactions.
                        </p>

                        <span>
                            Manage Sales →
                        </span>

                    </a>


                    <!-- STOCK -->
                    <a href="stock.php" class="dashboard-menu-card">

                        <div class="menu-icon orange-icon">
                            ▥
                        </div>

                        <h3>Stock</h3>

                        <p>
                            Monitor available stock and record stock movements.
                        </p>

                        <span>
                            Manage Stock →
                        </span>

                    </a>


                    <!-- CATEGORIES -->
                    <a href="categories.php" class="dashboard-menu-card">

                        <div class="menu-icon purple-icon">
                            ▤
                        </div>

                        <h3>Categories</h3>

                        <p>
                            Organise your products into different categories.
                        </p>

                        <span>
                            View Categories →
                        </span>

                    </a>


                    <!-- SUPPLIERS -->
                    <a href="suppliers.php" class="dashboard-menu-card">

                        <div class="menu-icon green-icon">
                            ♧
                        </div>

                        <h3>Suppliers</h3>

                        <p>
                            Keep supplier information organised and accessible.
                        </p>

                        <span>
                            Manage Suppliers →
                        </span>

                    </a>


                    <!-- REPORTS -->
                    <a href="reports.php" class="dashboard-menu-card">

                        <div class="menu-icon blue-icon">
                            ▧
                        </div>

                        <h3>Reports</h3>

                        <p>
                            View sales history and business performance.
                        </p>

                        <span>
                            View Reports →
                        </span>

                    </a>


                    <!-- PROFIT -->
                    <a href="profit.php" class="dashboard-menu-card">

                        <div class="menu-icon orange-icon">
                            R
                        </div>

                        <h3>Profit Analysis</h3>

                        <p>
                            Monitor revenue, costs and business profitability.
                        </p>

                        <span>
                            View Profits →
                        </span>

                    </a>

                </div>

            </section>


            <!-- FOOTER -->
            <footer class="dashboard-footer">

                <p>
                    © <?= date('Y') ?> SpazaSmart.
                    Smart Shop Management System.
                </p>

            </footer>

        </div>

    </main>

</div>

<script src="script.js"></script>

</body>
</html>
