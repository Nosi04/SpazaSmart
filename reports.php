
<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Validate dates
$start = DateTime::createFromFormat('!Y-m-d', $start_date);
$end = DateTime::createFromFormat('!Y-m-d', $end_date);

if (
    !$start || !$end ||
    $start->format('Y-m-d') !== $start_date ||
    $end->format('Y-m-d') !== $end_date ||
    $start_date > $end_date
) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}

// Sales summary
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS transactions,
        COALESCE(SUM(total_amount), 0) AS revenue,
        COALESCE(AVG(total_amount), 0) AS average_sale
    FROM sales
    WHERE sale_date >= ?
    AND sale_date < DATE_ADD(?, INTERVAL 1 DAY)
");

$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();

$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Sales history
$stmt = $conn->prepare("
    SELECT
        s.sale_id,
        s.total_amount,
        s.payment_method,
        s.sale_date,
        u.full_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.user_id
    WHERE s.sale_date >= ?
    AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY s.sale_date DESC
");

$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();

$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Best-selling products
$stmt = $conn->prepare("
    SELECT
        p.product_name,
        SUM(si.quantity) AS quantity_sold,
        SUM(si.subtotal) AS revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN products p ON si.product_id = p.product_id
    WHERE s.sale_date >= ?
    AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY p.product_id, p.product_name
    ORDER BY quantity_sold DESC
");

$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();

$best_sellers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Low-stock products
$result = $conn->query("
    SELECT product_name, quantity, minimum_stock
    FROM products
    WHERE quantity <= minimum_stock
    ORDER BY quantity ASC
");

$low_stock = $result->fetch_all(MYSQLI_ASSOC);

// Stock movements
$stmt = $conn->prepare("
    SELECT
        movement_type,
        COALESCE(SUM(quantity), 0) AS total
    FROM stock_movement
    WHERE movement_date >= ?
    AND movement_date < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY movement_type
");

$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();

$movements = $stmt->get_result();

$stock_in = 0;
$stock_out = 0;

while ($row = $movements->fetch_assoc()) {
    if (strtolower(trim($row['movement_type'])) === 'stock in') {
        $stock_in = (int)$row['total'];
    } elseif (strtolower(trim($row['movement_type'])) === 'stock out') {
        $stock_out = (int)$row['total'];
    }
}

$stmt->close();

// Format money in South African Rand
function money($amount) {
    return 'R' . number_format((float)$amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Business Reports | SpazaSmart</title>

    <link rel="stylesheet" href="/SpazaSmart/style.css?v=10">
</head>

<body class="reports-page">

<div class="reports-container">

    <!-- PAGE HEADER -->
    <header class="reports-header">

        <div>
            <span class="reports-tag">SPAZASMART BUSINESS INSIGHTS</span>

            <h1>Business Reports</h1>

            <p>
                Monitor sales, product performance and stock activity in your shop.
            </p>
        </div>

        <a href="dashboard.php" class="reports-back">
            &larr; Back to Dashboard
        </a>

    </header>

    <!-- PAGE INTRODUCTION -->
    <div class="reports-heading">
        <div>
            <h2>Reports Overview</h2>
            <p>View your business performance for a selected reporting period.</p>
        </div>
    </div>

    <!-- DATE FILTER -->
    <section class="reports-filter-card">

        <div class="reports-section-heading">
            <div>
                <h3>Filter Reports</h3>
                <p>Choose the dates you want to include in your report.</p>
            </div>
        </div>

        <form method="GET" class="reports-filter">

            <div class="reports-filter-field">
                <label for="start_date">Start Date</label>

                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?= htmlspecialchars($start_date) ?>"
                    max="<?= htmlspecialchars(date('Y-m-d')) ?>"
                    required
                >
            </div>

            <div class="reports-filter-field">
                <label for="end_date">End Date</label>

                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?= htmlspecialchars($end_date) ?>"
                    max="<?= htmlspecialchars(date('Y-m-d')) ?>"
                    required
                >
            </div>

            <button type="submit" class="reports-generate-button">
                Generate Report
            </button>

            <button
                type="button"
                class="reports-print-button"
                onclick="window.print()"
            >
                Print Report
            </button>

        </form>

    </section>

    <!-- REPORT PERIOD -->
    <div class="reports-period">
        <div class="reports-period-icon">&#128197;</div>

        <div>
            <h3>Reporting Period</h3>

            <p>
                <strong>From:</strong>
                <?= htmlspecialchars($start_date) ?>

                <span class="reports-period-divider">|</span>

                <strong>To:</strong>
                <?= htmlspecialchars($end_date) ?>
            </p>
        </div>
    </div>

    <!-- SALES SUMMARY -->
    <section class="reports-section">

        <div class="reports-section-heading">
            <div>
                <h2>Sales Summary</h2>
                <p>A quick overview of your sales performance.</p>
            </div>
        </div>

        <div class="reports-cards">

            <div class="reports-stat-card revenue-stat">
                <div class="reports-stat-top">
                    <span class="reports-stat-icon">R</span>
                    <span class="reports-stat-label">REVENUE</span>
                </div>

                <p>Total Revenue</p>
                <h2><?= money($summary['revenue']) ?></h2>
                <small>Total money generated from sales</small>
            </div>

            <div class="reports-stat-card transactions-stat">
                <div class="reports-stat-top">
                    <span class="reports-stat-icon">T</span>
                    <span class="reports-stat-label">TRANSACTIONS</span>
                </div>

                <p>Total Transactions</p>
                <h2><?= number_format($summary['transactions']) ?></h2>
                <small>Number of completed sales</small>
            </div>

            <div class="reports-stat-card average-stat">
                <div class="reports-stat-top">
                    <span class="reports-stat-icon">A</span>
                    <span class="reports-stat-label">AVERAGE</span>
                </div>

                <p>Average Sale</p>
                <h2><?= money($summary['average_sale']) ?></h2>
                <small>Average value per transaction</small>
            </div>

            <div class="reports-stat-card low-stock-stat">
                <div class="reports-stat-top">
                    <span class="reports-stat-icon">!</span>
                    <span class="reports-stat-label">INVENTORY</span>
                </div>

                <p>Low-Stock Products</p>
                <h2><?= number_format(count($low_stock)) ?></h2>
                <small>Products at or below minimum stock</small>
            </div>

        </div>

    </section>

    <!-- STOCK SUMMARY -->
    <section class="reports-section">

        <div class="reports-section-heading">
            <div>
                <h2>Stock Movement Summary</h2>
                <p>Stock received and issued during the selected period.</p>
            </div>
        </div>

        <div class="reports-stock-cards">

            <div class="reports-stock-card stock-received">
                <div class="reports-stock-icon">+</div>

                <div>
                    <p>Stock Received</p>
                    <h2><?= number_format($stock_in) ?></h2>
                    <small>Total units added to inventory</small>
                </div>
            </div>

            <div class="reports-stock-card stock-issued">
                <div class="reports-stock-icon">−</div>

                <div>
                    <p>Stock Issued</p>
                    <h2><?= number_format($stock_out) ?></h2>
                    <small>Total units removed from inventory</small>
                </div>
            </div>

        </div>

    </section>

    <!-- SALES HISTORY -->
    <section class="reports-table-card">

        <div class="reports-section-heading">
            <div>
                <h2>Sales History</h2>
                <p>Transactions recorded during the selected period.</p>
            </div>

            <span class="reports-count">
                <?= number_format(count($sales)) ?> records
            </span>
        </div>

        <div class="reports-table-wrapper">

            <table class="reports-table">

                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Cashier</th>
                        <th>Payment Method</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Receipt</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($sales): ?>

                        <?php foreach ($sales as $sale): ?>

                            <tr>
                                <td class="reports-id">
                                    #<?= (int)$sale['sale_id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($sale['full_name'] ?? 'Unknown') ?>
                                </td>

                                <td>
                                    <span class="reports-payment">
                                        <?= htmlspecialchars($sale['payment_method']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($sale['sale_date']) ?>
                                </td>

                                <td class="reports-money">
                                    <?= money($sale['total_amount']) ?>
                                </td>

                                <td>
                                    <a
                                        class="reports-view-button"
                                        href="receipt.php?sale_id=<?= (int)$sale['sale_id'] ?>"
                                    >
                                        View Receipt
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="reports-empty">
                                No sales found for this period.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- BEST-SELLING PRODUCTS -->
    <section class="reports-table-card">

        <div class="reports-section-heading">
            <div>
                <h2>Best-Selling Products</h2>
                <p>Products ranked by the quantity sold during this period.</p>
            </div>

            <span class="reports-count">
                <?= number_format(count($best_sellers)) ?> products
            </span>
        </div>

        <div class="reports-table-wrapper">

            <table class="reports-table">

                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity Sold</th>
                        <th>Revenue Generated</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($best_sellers): ?>

                        <?php foreach ($best_sellers as $product): ?>

                            <tr>
                                <td class="reports-product-name">
                                    <?= htmlspecialchars($product['product_name']) ?>
                                </td>

                                <td>
                                    <?= number_format($product['quantity_sold']) ?>
                                </td>

                                <td class="reports-money">
                                    <?= money($product['revenue']) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="reports-empty">
                                No product sales found for this period.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- LOW-STOCK PRODUCTS -->
    <section class="reports-table-card">

        <div class="reports-section-heading">
            <div>
                <h2>Low-Stock Products</h2>
                <p>Products that have reached or fallen below their minimum stock level.</p>
            </div>

            <span class="reports-count warning-count">
                <?= number_format(count($low_stock)) ?> products
            </span>
        </div>

        <div class="reports-table-wrapper">

            <table class="reports-table">

                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Current Quantity</th>
                        <th>Minimum Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($low_stock): ?>

                        <?php foreach ($low_stock as $product): ?>

                            <tr>
                                <td class="reports-product-name">
                                    <?= htmlspecialchars($product['product_name']) ?>
                                </td>

                                <td>
                                    <?= (int)$product['quantity'] ?>
                                </td>

                                <td>
                                    <?= (int)$product['minimum_stock'] ?>
                                </td>

                                <td>
                                    <span class="reports-low-badge">
                                        Low Stock
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4" class="reports-empty">
                                All products have sufficient stock.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <footer class="reports-footer">
        SpazaSmart Business Reports
    </footer>

</div>

</body>
</html>
