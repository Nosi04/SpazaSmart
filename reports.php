
<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$start = DateTime::createFromFormat('!Y-m-d', $start_date);
$end = DateTime::createFromFormat('!Y-m-d', $end_date);

if (!$start || !$end || $start->format('Y-m-d') != $start_date ||
    $end->format('Y-m-d') != $end_date || $start_date > $end_date) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}

// Sales summary
$stmt = $conn->prepare("SELECT COUNT(*) AS transactions,
    COALESCE(SUM(total_amount),0) AS revenue,
    COALESCE(AVG(total_amount),0) AS average_sale
    FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Sales history
$stmt = $conn->prepare("SELECT s.*, u.full_name
    FROM sales s LEFT JOIN users u ON s.user_id = u.user_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    ORDER BY s.sale_date DESC");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Best-selling products
$stmt = $conn->prepare("SELECT p.product_name,
    SUM(si.quantity) AS quantity_sold,
    SUM(si.subtotal) AS revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN products p ON si.product_id = p.product_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY p.product_id, p.product_name
    ORDER BY quantity_sold DESC");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$best_sellers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Low-stock products
$result = $conn->query("SELECT product_name, quantity, minimum_stock
    FROM products
    WHERE quantity <= minimum_stock
    ORDER BY quantity ASC");
$low_stock = $result->fetch_all(MYSQLI_ASSOC);

// Stock movements
$stmt = $conn->prepare("SELECT movement_type, SUM(quantity) AS total
    FROM stock_movement
    WHERE DATE(movement_date) BETWEEN ? AND ?
    GROUP BY movement_type");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$movements = $stmt->get_result();

$stock_in = 0;
$stock_out = 0;

while ($row = $movements->fetch_assoc()) {
    if (strtolower(trim($row['movement_type'])) == 'stock in') {
        $stock_in = $row['total'];
    } elseif (strtolower(trim($row['movement_type'])) == 'stock out') {
        $stock_out = $row['total'];
    }
}
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
<title>Spaza Smart Reports</title>

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
    max-width: 1150px;
    margin: 30px auto;
    padding: 0 20px;
}

.back {
    color: #173b32;
    text-decoration: none;
    font-weight: bold;
}

.filter, .section, .card {
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 18px;
    margin-bottom: 22px;
}

.card p { color: #777; }
.card h2 { color: #173b32; margin: 0; }

.section { overflow-x: auto; }
.section h2 { color: #173b32; }

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 500px;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

th {
    background: #173b32;
    color: white;
}

.low {
    color: #dc3545;
    font-weight: bold;
}

.receipt {
    color: #198754;
    text-decoration: none;
    font-weight: bold;
}

.empty {
    text-align: center;
    color: #777;
}

@media print {
    header, .filter, .back, .receipt {
        display: none;
    }
    body { background: white; }
}
</style>
</head>

<body>

<header>
    <h1>Spaza Smart</h1>
    <p>Business Reports</p>
</header>

<div class="container">

<a href="dashboard.php" class="back">← Dashboard</a>

<h2>Reports Overview</h2>

<form method="GET" class="filter">
    <div>
        <label>Start Date</label>
        <input type="date" name="start_date"
        value="<?= htmlspecialchars($start_date) ?>" required>
    </div>

    <div>
        <label>End Date</label>
        <input type="date" name="end_date"
        value="<?= htmlspecialchars($end_date) ?>" required>
    </div>

    <button type="submit">Generate Report</button>
    <button type="button" onclick="window.print()">Print Report</button>
</form>

<div class="cards">
    <div class="card">
        <p>Total Revenue</p>
        <h2><?= money($summary['revenue']) ?></h2>
    </div>

    <div class="card">
        <p>Total Transactions</p>
        <h2><?= number_format($summary['transactions']) ?></h2>
    </div>

    <div class="card">
        <p>Average Sale</p>
        <h2><?= money($summary['average_sale']) ?></h2>
    </div>

    <div class="card">
        <p>Low-Stock Products</p>
        <h2><?= count($low_stock) ?></h2>
    </div>
</div>

<div class="cards">
    <div class="card">
        <p>Stock Received</p>
        <h2><?= number_format($stock_in) ?></h2>
    </div>

    <div class="card">
        <p>Stock Issued</p>
        <h2><?= number_format($stock_out) ?></h2>
    </div>
</div>

<div class="section">
<h2>Sales History</h2>
<table>
<tr>
    <th>Sale ID</th>
    <th>Cashier</th>
    <th>Payment</th>
    <th>Date</th>
    <th>Total</th>
    <th>Receipt</th>
</tr>

<?php if ($sales): foreach ($sales as $sale): ?>
<tr>
    <td><?= (int)$sale['sale_id'] ?></td>
    <td><?= htmlspecialchars($sale['full_name'] ?? 'Unknown') ?></td>
    <td><?= htmlspecialchars($sale['payment_method']) ?></td>
    <td><?= htmlspecialchars($sale['sale_date']) ?></td>
    <td><?= money($sale['total_amount']) ?></td>
    <td><a class="receipt" href="receipt.php?sale_id=<?= (int)$sale['sale_id'] ?>">View</a></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="6" class="empty">No sales found.</td></tr>
<?php endif; ?>
</table>
</div>

<div class="section">
<h2>Best-Selling Products</h2>
<table>
<tr>
    <th>Product</th>
    <th>Quantity Sold</th>
    <th>Revenue</th>
</tr>

<?php if ($best_sellers): foreach ($best_sellers as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['product_name']) ?></td>
    <td><?= number_format($p['quantity_sold']) ?></td>
    <td><?= money($p['revenue']) ?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="3" class="empty">No product sales found.</td></tr>
<?php endif; ?>
</table>
</div>

<div class="section">
<h2>Low-Stock Products</h2>
<table>
<tr>
    <th>Product</th>
    <th>Current Quantity</th>
    <th>Minimum Stock</th>
    <th>Status</th>
</tr>

<?php if ($low_stock): foreach ($low_stock as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['product_name']) ?></td>
    <td><?= (int)$p['quantity'] ?></td>
    <td><?= (int)$p['minimum_stock'] ?></td>
    <td class="low">Low Stock</td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="4" class="empty">All products have sufficient stock.</td></tr>
<?php endif; ?>
</table>
</div>

</div>
</body>
</html>
