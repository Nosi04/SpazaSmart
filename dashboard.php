
<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Dashboard statistics
$products = $conn->query("SELECT COUNT(*) AS total FROM products")
                 ->fetch_assoc()['total'];

$low_stock = $conn->query("
    SELECT COUNT(*) AS total FROM products
    WHERE quantity <= minimum_stock
")->fetch_assoc()['total'];

$sales = $conn->query("
    SELECT COALESCE(SUM(total_amount),0) AS total
    FROM sales WHERE DATE(sale_date) = CURDATE()
")->fetch_assoc()['total'];

$transactions = $conn->query("
    SELECT COUNT(*) AS total FROM sales
    WHERE DATE(sale_date) = CURDATE()
")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Spaza Smart Dashboard</title>

<style>
* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    background: #f4f6f5;
    color: #26352f;
}

header {
    background: #173b32;
    color: white;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

header h1 { margin: 0; }

.logout {
    background: #dc3545;
    color: white;
    padding: 10px 18px;
    text-decoration: none;
    border-radius: 5px;
}

.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.welcome {
    margin-bottom: 25px;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    border-left: 5px solid #198754;
}

.card p {
    color: #777;
}

.card h2 {
    color: #173b32;
    margin-bottom: 0;
}

.alert {
    background: #fff3cd;
    color: #856404;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 25px;
}

.menu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.menu a {
    background: white;
    padding: 25px;
    border-radius: 10px;
    text-decoration: none;
    color: #173b32;
    border: 1px solid #eee;
    transition: 0.2s;
}

.menu a:hover {
    background: #e5f2eb;
    transform: translateY(-3px);
}

.menu h3 {
    margin-top: 0;
}

.menu p {
    color: #777;
}

footer {
    text-align: center;
    padding: 25px;
    color: #777;
}
</style>
</head>

<body>

<header>
    <h1>Spaza Smart</h1>
    <a href="logout.php" class="logout">Logout</a>
</header>

<div class="container">

    <div class="welcome">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h2>
        <p>Manage your spaza shop from one place.</p>
    </div>

    <h2>Today's Overview</h2>

    <div class="cards">

        <div class="card">
            <p>Total Products</p>
            <h2><?= number_format($products) ?></h2>
        </div>

        <div class="card">
            <p>Low-Stock Products</p>
            <h2><?= number_format($low_stock) ?></h2>
        </div>

        <div class="card">
            <p>Today's Sales</p>
            <h2>R<?= number_format((float)$sales, 2) ?></h2>
        </div>

        <div class="card">
            <p>Today's Transactions</p>
            <h2><?= number_format($transactions) ?></h2>
        </div>

    </div>

    <?php if ($low_stock > 0): ?>
        <div class="alert">
            <strong>Stock Alert!</strong>
            You have <?= $low_stock ?> product(s) that need restocking.
        </div>
    <?php endif; ?>

    <h2>Shop Management</h2>

    <div class="menu">

        <a href="products.php">
            <h3>Product Management</h3>
            <p>Add, view, search and manage products.</p>
        </a>

        <a href="stock.php">
            <h3>Stock Management</h3>
            <p>Record stock received and stock issued.</p>
        </a>

        <a href="sales.php">
            <h3>Sales Management</h3>
            <p>Process sales and manage transactions.</p>
        </a>

        <a href="reports.php">
            <h3>Business Reports</h3>
            <p>View sales history and stock reports.</p>
        </a>

        <a href="profit.php">
            <h3>Profit Reports</h3>
            <p>Monitor revenue, costs and gross profit.</p>
        </a>

    </div>

</div>

<footer>
    Spaza Smart &copy; <?= date('Y') ?> | Smart Shop Management
</footer>

</body>
</html>
