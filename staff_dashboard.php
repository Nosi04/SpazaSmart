
<?php
session_start();
require_once 'connection.php';

// Only logged-in staff members can access this dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Prevent owners from accessing the staff dashboard
if (strtolower($_SESSION['role'] ?? '') !== 'staff') {
    header("Location: dashboard.php");
    exit();
}

$userName = $_SESSION['full_name'] ?? 'Staff';
$today = date('l, d F Y');

// Today's sales statistics
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_sales,
        COALESCE(SUM(total_amount), 0) AS total_revenue
    FROM sales
    WHERE DATE(sale_date) = CURDATE()
");

$stmt->execute();
$salesStats = $stmt->get_result()->fetch_assoc();

$totalSales = $salesStats['total_sales'] ?? 0;
$totalRevenue = $salesStats['total_revenue'] ?? 0;

// Total products
$result = $conn->query("SELECT COUNT(*) AS total FROM products");
$totalProducts = $result->fetch_assoc()['total'] ?? 0;

// Low-stock products
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
    WHERE quantity <= minimum_stock
");
$lowStock = $result->fetch_assoc()['total'] ?? 0;

// Recent sales recorded by this staff member
$stmt = $conn->prepare("
    SELECT sale_id, total_amount, payment_method, sale_date
    FROM sales
    WHERE user_id = ?
    ORDER BY sale_date DESC
    LIMIT 5
");

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recentSales = $stmt->get_result();

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | SpazaSmart</title>
    <link rel="stylesheet" href="/SpazaSmart/style.css?v=11">
</head>

<body class="dashboard-page">

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="dashboard-sidebar">

        <div class="sidebar-brand">
            <div class="brand-icon">S</div>
            <div>
                <strong>SpazaSmart</strong>
                <span class="sidebar-label">Staff Portal</span>
            </div>
        </div>

        <nav class="sidebar-nav">

            <a href="staff_dashboard.php" class="sidebar-link active">
                <span class="nav-icon">⌂</span>
                Dashboard
            </a>

            <a href="products.php" class="sidebar-link">
                <span class="nav-icon">▣</span>
                View Products
            </a>

            <a href="stock.php" class="sidebar-link">
                <span class="nav-icon">⇅</span>
                Stock Management
            </a>

            <a href="sales.php" class="sidebar-link">
                <span class="nav-icon">▤</span>
                Sales
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="sidebar-user">
                <div class="user-avatar">
                    <?= strtoupper(substr($userName, 0, 1)); ?>
                </div>

                <div class="user-details">
                    <strong><?= clean($userName); ?></strong>
                    <span>Staff Member</span>
                </div>
            </div>

            <a href="logout.php" class="sidebar-logout">
                Logout
            </a>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="dashboard-main">

        <header class="dashboard-topbar">
            <div>
                <span class="topbar-label">STAFF WORKSPACE</span>
                <h2>Staff Dashboard</h2>
            </div>

            <div class="topbar-date">
                <?= clean($today); ?>
            </div>
        </header>

        <div class="dashboard-content">

            <!-- WELCOME -->
            <section class="dashboard-welcome">
                <div>
                    <span class="welcome-tag">STAFF PORTAL</span>
                    <h1>Welcome, <?= clean($userName); ?>!</h1>
                    <p>Manage daily shop activities, stock and customer sales.</p>
                </div>

                <div class="welcome-decoration">S</div>
            </section>

            <!-- STATISTICS -->
            <section class="dashboard-section">

                <div class="section-heading">
                    <h2>Today's Overview</h2>
                    <p>Your daily shop activity at a glance.</p>
                </div>

                <div class="dashboard-stats">

                    <div class="dashboard-stat-card">
                        <div class="stat-top">
                            <span>Today's Sales</span>
                            <div class="stat-icon green-icon">R</div>
                        </div>
                        <h3><?= number_format((int)$totalSales); ?></h3>
                        <p>Transactions recorded today</p>
                    </div>

                    <div class="dashboard-stat-card">
                        <div class="stat-top">
                            <span>Today's Revenue</span>
                            <div class="stat-icon blue-icon">R</div>
                        </div>
                        <h3>R<?= number_format((float)$totalRevenue, 2); ?></h3>
                        <p>Total sales value today</p>
                    </div>

                    <div class="dashboard-stat-card">
                        <div class="stat-top">
                            <span>Available Products</span>
                            <div class="stat-icon purple-icon">▣</div>
                        </div>
                        <h3><?= number_format((int)$totalProducts); ?></h3>
                        <p>Products in the system</p>
                    </div>

                    <div class="dashboard-stat-card">
                        <div class="stat-top">
                            <span>Low Stock Items</span>
                            <div class="stat-icon orange-icon">!</div>
                        </div>
                        <h3><?= number_format((int)$lowStock); ?></h3>
                        <p>Items requiring attention</p>
                    </div>

                </div>
            </section>

            <!-- QUICK ACTIONS -->
            <section class="dashboard-section">

                <div class="section-heading">
                    <h2>Quick Actions</h2>
                    <p>Access the tools you need for daily operations.</p>
                </div>

                <div class="dashboard-menu">

                    <a href="products.php" class="dashboard-menu-card">
                        <div class="menu-icon">▣</div>
                        <h3>View Products</h3>
                        <p>Check product information and available quantities.</p>
                    </a>

                    <a href="stock.php" class="dashboard-menu-card">
                        <div class="menu-icon">⇅</div>
                        <h3>Manage Stock</h3>
                        <p>Record stock received and stock issued.</p>
                    </a>

                    <a href="sales.php" class="dashboard-menu-card">
                        <div class="menu-icon">▤</div>
                        <h3>Record Sales</h3>
                        <p>Process customer purchases and sales transactions.</p>
                    </a>

                </div>
            </section>

            <!-- RECENT SALES -->
            <section class="dashboard-section">

                <div class="section-heading">
                    <h2>My Recent Sales</h2>
                    <p>Your five most recent transactions.</p>
                </div>

                <div class="dashboard-stock-alert">

                    <div class="alert-content">

                        <?php if ($recentSales->num_rows > 0): ?>

                            <table>
                                <thead>
                                    <tr>
                                        <th>Sale ID</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php while ($sale = $recentSales->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= clean($sale['sale_id']); ?></td>
                                            <td>R<?= number_format((float)$sale['total_amount'], 2); ?></td>
                                            <td><?= clean($sale['payment_method']); ?></td>
                                            <td><?= clean($sale['sale_date']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>

                        <?php else: ?>

                            <p>You have not recorded any sales yet.</p>

                        <?php endif; ?>

                    </div>

                </div>

            </section>

        </div>

        <footer class="dashboard-footer">
            SpazaSmart &copy; <?= date('Y'); ?>. Staff Portal.
        </footer>

    </main>

</div>

</body>
</html>
