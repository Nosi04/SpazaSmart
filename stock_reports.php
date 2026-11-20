
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

// Stock reports are restricted to the owner
if (($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: staff_dashboard.php");
    exit();
}

// Default dates
$start = $_GET['start_date'] ?? date('Y-m-01');
$end = $_GET['end_date'] ?? date('Y-m-d');

$message = "";

// Validate dates
$start_date = DateTime::createFromFormat('!Y-m-d', $start);
$end_date = DateTime::createFromFormat('!Y-m-d', $end);

if (
    !$start_date ||
    !$end_date ||
    $start_date->format('Y-m-d') !== $start ||
    $end_date->format('Y-m-d') !== $end ||
    $start > $end
) {
    $start = date('Y-m-01');
    $end = date('Y-m-d');
    $message = "Invalid date range. Showing the default report instead.";
}

// Reusable date range condition
$date_condition = "
    movement_date >= ?
    AND movement_date < DATE_ADD(?, INTERVAL 1 DAY)
";

// Summary report
$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN movement_type = 'Stock In'
            THEN quantity ELSE 0 END) AS total_in,

        SUM(CASE WHEN movement_type = 'Stock Out'
            THEN quantity ELSE 0 END) AS total_out

    FROM stock_movement
    WHERE $date_condition
");

$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Daily stock report
$stmt = $conn->prepare("
    SELECT
        DATE(movement_date) AS report_date,

        SUM(CASE WHEN movement_type = 'Stock In'
            THEN quantity ELSE 0 END) AS stock_in,

        SUM(CASE WHEN movement_type = 'Stock Out'
            THEN quantity ELSE 0 END) AS stock_out

    FROM stock_movement
    WHERE $date_condition
    GROUP BY DATE(movement_date)
    ORDER BY report_date DESC
");

$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$daily = $stmt->get_result();
$stmt->close();

// Monthly stock report
$stmt = $conn->prepare("
    SELECT
        DATE_FORMAT(movement_date, '%Y-%m') AS report_month,

        SUM(CASE WHEN movement_type = 'Stock In'
            THEN quantity ELSE 0 END) AS stock_in,

        SUM(CASE WHEN movement_type = 'Stock Out'
            THEN quantity ELSE 0 END) AS stock_out

    FROM stock_movement
    WHERE $date_condition
    GROUP BY DATE_FORMAT(movement_date, '%Y-%m')
    ORDER BY report_month DESC
");

$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$monthly = $stmt->get_result();
$stmt->close();

// Top 10 stock-out products
$stmt = $conn->prepare("
    SELECT
        p.product_name,
        SUM(sm.quantity) AS total

    FROM stock_movement sm

    JOIN products p
        ON sm.product_id = p.product_id

    WHERE sm.movement_type = 'Stock Out'
    AND sm.movement_date >= ?
    AND sm.movement_date < DATE_ADD(?, INTERVAL 1 DAY)

    GROUP BY p.product_id, p.product_name
    ORDER BY total DESC
    LIMIT 10
");

$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$top_products = $stmt->get_result();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Stock Reports | SpazaSmart</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<h1>Stock Reports</h1>

<p>View and analyse your stock movements.</p>

<?php if (!empty($message)): ?>
    <p><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>


<!-- Date Filter -->
<div class="card">

    <form method="GET">

        <label>From:</label>

        <input
            type="date"
            name="start_date"
            value="<?php echo htmlspecialchars($start); ?>"
            required
        >

        <label>To:</label>

        <input
            type="date"
            name="end_date"
            value="<?php echo htmlspecialchars($end); ?>"
            required
        >

        <button type="submit">Generate Report</button>

    </form>

</div>


<!-- Summary -->
<div class="stats">

    <div class="card">

        <h3>Total Stock In</h3>

        <h1>
            <?php echo number_format($summary['total_in'] ?? 0); ?>
        </h1>

    </div>


    <div class="card">

        <h3>Total Stock Out</h3>

        <h1>
            <?php echo number_format($summary['total_out'] ?? 0); ?>
        </h1>

    </div>

</div>


<!-- Daily Report -->
<div class="card">

    <h2>Daily Stock Report</h2>

    <table>

        <tr>
            <th>Date</th>
            <th>Stock In</th>
            <th>Stock Out</th>
        </tr>

        <?php if ($daily->num_rows > 0): ?>

            <?php while ($r = $daily->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php echo htmlspecialchars($r['report_date']); ?>
                    </td>

                    <td>
                        <?php echo number_format($r['stock_in']); ?>
                    </td>

                    <td>
                        <?php echo number_format($r['stock_out']); ?>
                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="3">No stock movements found for this period.</td>
            </tr>

        <?php endif; ?>

    </table>

</div>


<!-- Monthly Report -->
<div class="card">

    <h2>Monthly Stock Report</h2>

    <table>

        <tr>
            <th>Month</th>
            <th>Stock In</th>
            <th>Stock Out</th>
        </tr>

        <?php if ($monthly->num_rows > 0): ?>

            <?php while ($r = $monthly->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php echo htmlspecialchars($r['report_month']); ?>
                    </td>

                    <td>
                        <?php echo number_format($r['stock_in']); ?>
                    </td>

                    <td>
                        <?php echo number_format($r['stock_out']); ?>
                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="3">No monthly records found.</td>
            </tr>

        <?php endif; ?>

    </table>

</div>


<!-- Top 10 Stock-Out Products -->
<div class="card">

    <h2>Top 10 Stock-Out Products</h2>

    <table>

        <tr>
            <th>Product</th>
            <th>Total Stock Out</th>
        </tr>

        <?php if ($top_products->num_rows > 0): ?>

            <?php while ($r = $top_products->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php echo htmlspecialchars($r['product_name']); ?>
                    </td>

                    <td>
                        <?php echo number_format($r['total']); ?>
                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="2">No stock-out records found.</td>
            </tr>

        <?php endif; ?>

    </table>

</div>

</body>
</html>
