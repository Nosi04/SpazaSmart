
<?php
session_start();
require_once 'connection.php';

// =====================================
// LOGIN AND OWNER ACCESS PROTECTION
// =====================================

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Profit reports are restricted to the owner
if (($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: staff_dashboard.php");
    exit();
}

// Default reporting period
$start = $_GET['start_date'] ?? date('Y-m-01');
$end = $_GET['end_date'] ?? date('Y-m-d');

// Validate dates
$d1 = DateTime::createFromFormat('!Y-m-d', $start);
$d2 = DateTime::createFromFormat('!Y-m-d', $end);

if (
    !$d1 ||
    !$d2 ||
    $d1->format('Y-m-d') !== $start ||
    $d2->format('Y-m-d') !== $end ||
    $start > $end
) {
    $start = date('Y-m-01');
    $end = date('Y-m-d');
}

// Profit summary
$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(si.subtotal), 0) AS revenue,
        COALESCE(SUM(si.quantity * p.buying_price), 0) AS cost,
        COALESCE(
            SUM(si.subtotal - (si.quantity * p.buying_price)), 0
        ) AS profit,
        COALESCE(SUM(si.quantity), 0) AS quantity_sold,
        COUNT(DISTINCT s.sale_id) AS transactions
    FROM sale_items si
    JOIN sales s
        ON si.sale_id = s.sale_id
    JOIN products p
        ON si.product_id = p.product_id
    WHERE s.sale_date >= ?
      AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
");

$stmt->bind_param("ss", $start, $end);
$stmt->execute();

$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Product profit breakdown
$stmt = $conn->prepare("
    SELECT
        p.product_name,
        SUM(si.quantity) AS quantity_sold,
        SUM(si.subtotal) AS revenue,
        SUM(si.quantity * p.buying_price) AS cost,
        SUM(
            si.subtotal - (si.quantity * p.buying_price)
        ) AS profit
    FROM sale_items si
    JOIN sales s
        ON si.sale_id = s.sale_id
    JOIN products p
        ON si.product_id = p.product_id
    WHERE s.sale_date >= ?
      AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY p.product_id, p.product_name
    ORDER BY profit DESC
");

$stmt->bind_param("ss", $start, $end);
$stmt->execute();

$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Currency formatting
function money($amount) {
    return 'R' . number_format((float)$amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profit Report | SpazaSmart</title>

    <link rel="stylesheet" href="/SpazaSmart/style.css?v=9">
</head>

<body class="profit-page">

<div class="profit-container">

    <!-- PAGE HEADER -->
    <header class="profit-header">

        <div>
            <span class="profit-tag">SPAZASMART BUSINESS INSIGHTS</span>

            <h1>Profit and Performance</h1>

            <p>
                Track your shop's revenue, product costs and profit performance.
            </p>
        </div>

        <a href="dashboard.php" class="profit-back">
            &larr; Back to Dashboard
        </a>

    </header>

    <!-- PAGE TITLE -->
    <div class="profit-page-heading">
        <div>
            <h2>Profit Overview</h2>
            <p>Review your financial performance for a selected period.</p>
        </div>
    </div>

    <!-- DATE FILTER -->
    <section class="profit-filter-card">

        <div class="profit-section-heading">
            <div>
                <h3>Generate Profit Report</h3>
                <p>Select a date range to view your business performance.</p>
            </div>
        </div>

        <form method="GET" class="profit-filter">

            <div class="profit-filter-field">
                <label for="start_date">Start Date</label>

                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?= htmlspecialchars($start) ?>"
                    max="<?= htmlspecialchars(date('Y-m-d')) ?>"
                    required
                >
            </div>

            <div class="profit-filter-field">
                <label for="end_date">End Date</label>

                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?= htmlspecialchars($end) ?>"
                    max="<?= htmlspecialchars(date('Y-m-d')) ?>"
                    required
                >
            </div>

            <button type="submit" class="profit-generate-button">
                Generate Report
            </button>

            <button
                type="button"
                class="profit-print-button"
                onclick="window.print()"
            >
                Print Report
            </button>

        </form>

    </section>

    <!-- REPORT STATISTICS -->
    <section class="profit-statistics">

        <div class="profit-stat-card revenue-card">
            <div class="profit-stat-top">
                <span class="profit-stat-icon">R</span>
                <span class="profit-stat-label">REVENUE</span>
            </div>

            <p>Total Revenue</p>

            <h2><?= money($summary['revenue']) ?></h2>

            <small>Money received from sales</small>
        </div>

        <div class="profit-stat-card cost-card">
            <div class="profit-stat-top">
                <span class="profit-stat-icon">C</span>
                <span class="profit-stat-label">COST</span>
            </div>

            <p>Cost of Products Sold</p>

            <h2><?= money($summary['cost']) ?></h2>

            <small>Estimated cost of sold products</small>
        </div>

        <div class="profit-stat-card gross-profit-card">
            <div class="profit-stat-top">
                <span class="profit-stat-icon">+</span>
                <span class="profit-stat-label">PROFIT</span>
            </div>

            <p>Gross Profit</p>

            <h2><?= money($summary['profit']) ?></h2>

            <small>Revenue minus product costs</small>
        </div>

        <div class="profit-stat-card units-card">
            <div class="profit-stat-top">
                <span class="profit-stat-icon">U</span>
                <span class="profit-stat-label">UNITS</span>
            </div>

            <p>Units Sold</p>

            <h2><?= number_format($summary['quantity_sold']) ?></h2>

            <small>Total quantity sold</small>
        </div>

        <div class="profit-stat-card transactions-card">
            <div class="profit-stat-top">
                <span class="profit-stat-icon">T</span>
                <span class="profit-stat-label">SALES</span>
            </div>

            <p>Total Transactions</p>

            <h2><?= number_format($summary['transactions']) ?></h2>

            <small>Number of completed sales</small>
        </div>

    </section>

    <!-- REPORT PERIOD -->
    <section class="profit-period-card">

        <div class="profit-period-icon">
            &#128197;
        </div>

        <div>
            <h3>Report Period</h3>

            <p>
                <strong>From:</strong>
                <?= htmlspecialchars($start) ?>

                <span class="profit-period-divider">|</span>

                <strong>To:</strong>
                <?= htmlspecialchars($end) ?>
            </p>
        </div>

    </section>

    <!-- PRODUCT PROFIT BREAKDOWN -->
    <section class="profit-table-card">

        <div class="profit-section-heading">
            <div>
                <h2>Product Profit Breakdown</h2>

                <p>
                    Compare the revenue, cost and profit generated by each product.
                </p>
            </div>
        </div>

        <div class="profit-table-wrapper">

            <table class="profit-table">

                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity Sold</th>
                        <th>Revenue</th>
                        <th>Cost</th>
                        <th>Profit</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($products) > 0): ?>

                        <?php foreach ($products as $p): ?>

                            <tr>

                                <td class="profit-product-name">
                                    <?= htmlspecialchars($p['product_name']) ?>
                                </td>

                                <td>
                                    <?= number_format($p['quantity_sold']) ?>
                                </td>

                                <td>
                                    <?= money($p['revenue']) ?>
                                </td>

                                <td>
                                    <?= money($p['cost']) ?>
                                </td>

                                <td class="profit-value">
                                    <?= money($p['profit']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="profit-empty">
                                No sales recorded for this period.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <footer class="profit-footer">
        SpazaSmart Profit and Business Performance Report
    </footer>

</div>

</body>
</html>
