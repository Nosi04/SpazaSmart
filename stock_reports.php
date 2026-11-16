
<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$start = $_GET['start_date'] ?? date('Y-m-01');
$end = $_GET['end_date'] ?? date('Y-m-d');

// Summary
$stmt = $conn->prepare("
    SELECT
    SUM(CASE WHEN movement_type='Stock In' THEN quantity ELSE 0 END) AS total_in,
    SUM(CASE WHEN movement_type='Stock Out' THEN quantity ELSE 0 END) AS total_out
    FROM stock_movement
    WHERE DATE(movement_date) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Daily report
$stmt = $conn->prepare("
    SELECT DATE(movement_date) AS report_date,
    SUM(CASE WHEN movement_type='Stock In' THEN quantity ELSE 0 END) AS stock_in,
    SUM(CASE WHEN movement_type='Stock Out' THEN quantity ELSE 0 END) AS stock_out
    FROM stock_movement
    WHERE DATE(movement_date) BETWEEN ? AND ?
    GROUP BY DATE(movement_date)
    ORDER BY report_date DESC
");
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$daily = $stmt->get_result();

// Monthly report
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(movement_date, '%Y-%m') AS report_month,
    SUM(CASE WHEN movement_type='Stock In' THEN quantity ELSE 0 END) AS stock_in,
    SUM(CASE WHEN movement_type='Stock Out' THEN quantity ELSE 0 END) AS stock_out
    FROM stock_movement
    WHERE DATE(movement_date) BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(movement_date, '%Y-%m')
    ORDER BY report_month DESC
");
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$monthly = $stmt->get_result();

// Most stock-out products
$stmt = $conn->prepare("
    SELECT p.product_name, SUM(sm.quantity) AS total
    FROM stock_movement sm
    JOIN products p ON sm.product_id = p.product_id
    WHERE sm.movement_type='Stock Out'
    AND DATE(sm.movement_date) BETWEEN ? AND ?
    GROUP BY p.product_id, p.product_name
    ORDER BY total DESC LIMIT 10
");
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$top_products = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="style.css">
<title>Stock Reports | Spaza Smart</title>
<style>
body{font-family:Arial;background:#f4f6f9;padding:25px}
.card{background:white;padding:20px;margin-bottom:20px;border-radius:8px}
.stats{display:flex;gap:20px}
.stats .card{flex:1}
input,button{padding:10px;margin:8px 5px}
button{background:#198754;color:white;border:0;cursor:pointer}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;text-align:left;border-bottom:1px solid #ddd}
th{background:#173b32;color:white}
</style>
</head>
<body>

<h1>Stock Reports</h1>
<p>View and analyse your stock movements.</p>

<div class="card">
<form method="GET">
    <label>From:</label>
    <input type="date" name="start_date" value="<?= htmlspecialchars($start) ?>" required>

    <label>To:</label>
    <input type="date" name="end_date" value="<?= htmlspecialchars($end) ?>" required>

    <button type="submit">Generate Report</button>
</form>
</div>

<div class="stats">
    <div class="card">
        <h3>Total Stock In</h3>
        <h1><?= number_format($summary['total_in'] ?? 0) ?></h1>
    </div>

    <div class="card">
        <h3>Total Stock Out</h3>
        <h1><?= number_format($summary['total_out'] ?? 0) ?></h1>
    </div>
</div>

<div class="card">
<h2>Daily Stock Report</h2>
<table>
<tr><th>Date</th><th>Stock In</th><th>Stock Out</th></tr>
<?php while($r = $daily->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($r['report_date']) ?></td>
<td><?= $r['stock_in'] ?></td>
<td><?= $r['stock_out'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<div class="card">
<h2>Monthly Stock Report</h2>
<table>
<tr><th>Month</th><th>Stock In</th><th>Stock Out</th></tr>
<?php while($r = $monthly->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($r['report_month']) ?></td>
<td><?= $r['stock_in'] ?></td>
<td><?= $r['stock_out'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<div class="card">
<h2>Top 10 Stock-Out Products</h2>
<table>
<tr><th>Product</th><th>Total Stock Out</th></tr>
<?php while($r = $top_products->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($r['product_name']) ?></td>
<td><?= $r['total'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

</body>
</html>
