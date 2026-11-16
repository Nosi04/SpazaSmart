
<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$start = $_GET['start_date'] ?? date('Y-m-01');
$end = $_GET['end_date'] ?? date('Y-m-d');

$d1 = DateTime::createFromFormat('!Y-m-d', $start);
$d2 = DateTime::createFromFormat('!Y-m-d', $end);

if (!$d1 || !$d2 || $d1->format('Y-m-d') != $start ||
    $d2->format('Y-m-d') != $end || $start > $end) {
    $start = date('Y-m-01');
    $end = date('Y-m-d');
}

// Profit summary
$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(si.subtotal),0) AS revenue,
        COALESCE(SUM(si.quantity * p.buying_price),0) AS cost,
        COALESCE(SUM(si.subtotal - (si.quantity * p.buying_price)),0) AS profit
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN products p ON si.product_id = p.product_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
");

$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Product profit
$stmt = $conn->prepare("
    SELECT p.product_name,
        SUM(si.quantity) AS quantity_sold,
        SUM(si.subtotal) AS revenue,
        SUM(si.quantity * p.buying_price) AS cost,
        SUM(si.subtotal - (si.quantity * p.buying_price)) AS profit
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN products p ON si.product_id = p.product_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY p.product_id, p.product_name
    ORDER BY profit DESC
");

$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function money($amount) {
    return 'R' . number_format((float)$amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style.css">
<title>Profit Report | Spaza Smart</title>

<style>
* { box-sizing: border-box; font-family: Arial; }

body {
    margin: 0;
    background: #f4f6f5;
    color: #26352f;
}

header {
    background: #173b32;
    color: white;
    padding: 20px 30px;
}

.container {
    max-width: 1100px;
    margin: 30px auto;
    padding: 0 20px;
}

.back {
    color: #173b32;
    text-decoration: none;
    font-weight: bold;
}

.filter, .card, .section {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 22px;
}

.filter {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: end;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
}

input, button {
    padding: 11px;
    border-radius: 5px;
}

input { border: 1px solid #ccc; }

button {
    background: #198754;
    color: white;
    border: none;
    cursor: pointer;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
}

.card p { color: #777; }
.card h2 { color: #173b32; }

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 550px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

th {
    background: #173b32;
    color: white;
}

.section { overflow-x: auto; }

.profit {
    color: #198754;
    font-weight: bold;
}

@media print {
    header, .filter, .back, button {
        display: none;
    }
}
</style>
</head>

<body>

<header>
    <h1>Spaza Smart</h1>
    <p>Profit and Business Performance</p>
</header>

<div class="container">

<a href="dashboard.php" class="back">← Dashboard</a>

<h2>Profit Overview</h2>

<form method="GET" class="filter">
    <div>
        <label>Start Date</label>
        <input type="date" name="start_date"
        value="<?= htmlspecialchars($start) ?>" required>
    </div>

    <div>
        <label>End Date</label>
        <input type="date" name="end_date"
        value="<?= htmlspecialchars($end) ?>" required>
    </div>

    <button type="submit">Generate Report</button>
    <button type="button" onclick="window.print()">Print</button>
</form>

<div class="cards">

    <div class="card">
        <p>Total Revenue</p>
        <h2><?= money($summary['revenue']) ?></h2>
    </div>

    <div class="card">
        <p>Cost of Products Sold</p>
        <h2><?= money($summary['cost']) ?></h2>
    </div>

    <div class="card">
        <p>Gross Profit</p>
        <h2 class="profit"><?= money($summary['profit']) ?></h2>
    </div>

</div>

<div class="section">

<h2>Product Profit Breakdown</h2>

<table>
<tr>
    <th>Product</th>
    <th>Quantity Sold</th>
    <th>Revenue</th>
    <th>Cost</th>
    <th>Profit</th>
</tr>

<?php if ($products): foreach ($products as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['product_name']) ?></td>
    <td><?= (int)$p['quantity_sold'] ?></td>
    <td><?= money($p['revenue']) ?></td>
    <td><?= money($p['cost']) ?></td>
    <td class="profit"><?= money($p['profit']) ?></td>
</tr>
<?php endforeach; else: ?>
<tr>
    <td colspan="5">No sales recorded for this period.</td>
</tr>
<?php endif; ?>

</table>
</div>

</div>
</body>
</html>
