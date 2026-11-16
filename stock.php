
<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

// Record stock movement
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_id = (int)($_POST['product_id'] ?? 0);
    $type = $_POST['movement_type'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $user_id = (int)$_SESSION['user_id'];

    if ($product_id <= 0 || $quantity < 1 ||
        !in_array($type, ['Stock In', 'Stock Out'], true)) {

        $message = "Please enter valid stock details.";
        $message_type = "error";

    } else {

        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "SELECT quantity FROM products
                 WHERE product_id = ? FOR UPDATE"
            );

            $stmt->bind_param("i", $product_id);
            $stmt->execute();

            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product) {
                throw new Exception("Product not found.");
            }

            $current = (int)$product['quantity'];

            if ($type === "Stock Out" && $quantity > $current) {
                throw new Exception(
                    "Insufficient stock. Only $current units available."
                );
            }

            $new_quantity = ($type === "Stock In")
                ? $current + $quantity
                : $current - $quantity;

            // Update product quantity
            $stmt = $conn->prepare(
                "UPDATE products SET quantity = ?
                 WHERE product_id = ?"
            );

            $stmt->bind_param("ii", $new_quantity, $product_id);
            $stmt->execute();
            $stmt->close();

            // Save movement history
            $stmt = $conn->prepare(
                "INSERT INTO stock_movement
                (product_id, user_id, movement_type,
                 quantity, movement_date)
                VALUES (?, ?, ?, ?, NOW())"
            );

            $stmt->bind_param(
                "iisi",
                $product_id,
                $user_id,
                $type,
                $quantity
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $message = "Stock movement recorded successfully!";
            $message_type = "success";

        } catch (Throwable $e) {

            $conn->rollback();

            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}

// Dashboard statistics
$total_products = $conn->query(
    "SELECT COUNT(*) AS total FROM products"
)->fetch_assoc()['total'];

$total_stock = $conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM products"
)->fetch_assoc()['total'];

$low_stock = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products WHERE quantity <= 5"
)->fetch_assoc()['total'];

$total_in = $conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM stock_movement
     WHERE movement_type = 'Stock In'"
)->fetch_assoc()['total'];

$total_out = $conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM stock_movement
     WHERE movement_type = 'Stock Out'"
)->fetch_assoc()['total'];

// Product dropdown
$products = $conn->query(
    "SELECT product_id, product_name, quantity
     FROM products ORDER BY product_name ASC"
);

// Search and filter history
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'All';

if (!in_array($filter, ['All', 'Stock In', 'Stock Out'], true)) {
    $filter = 'All';
}

$sql = "SELECT sm.movement_id,
               sm.user_id,
               sm.movement_type,
               sm.quantity,
               sm.movement_date,
               p.product_name,
               u.full_name
        FROM stock_movement sm
        INNER JOIN products p
            ON sm.product_id = p.product_id
        LEFT JOIN users u
            ON sm.user_id = u.user_id
        WHERE 1=1";

$params = [];
$types = "";

if ($filter !== 'All') {
    $sql .= " AND sm.movement_type = ?";
    $params[] = $filter;
    $types .= "s";
}

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ?
              OR u.full_name LIKE ?)";
    $keyword = "%" . $search . "%";
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "ss";
}

$sql .= " ORDER BY sm.movement_date DESC,
          sm.movement_id DESC LIMIT 100";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$history = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">

<title>Stock Management | Spaza Smart</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #f4f6f9;
    color: #333;
    padding: 30px;
}

.container {
    max-width: 1250px;
    margin: auto;
}

.header {
    margin-bottom: 25px;
}

.header h1 {
    color: #173b32;
    margin-bottom: 8px;
}

.header p {
    color: #777;
}

.back {
    display: inline-block;
    margin-bottom: 20px;
    color: #198754;
    text-decoration: none;
    font-weight: bold;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    border-left: 4px solid #198754;
}

.stat-card h3 {
    color: #777;
    font-size: 14px;
    margin-bottom: 12px;
}

.stat-card h2 {
    color: #173b32;
    font-size: 27px;
}

.stat-card.low {
    border-left-color: #dc3545;
}

.stat-card.low h2 {
    color: #dc3545;
}

.stat-card.out {
    border-left-color: #e09b26;
}

.content {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
    align-items: start;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    margin-bottom: 22px;
}

.card h2 {
    color: #173b32;
    font-size: 19px;
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    font-size: 14px;
    margin: 15px 0 7px;
}

input, select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: white;
    outline: none;
}

input:focus, select:focus {
    border-color: #198754;
}

button {
    padding: 12px;
    background: #198754;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

button:hover {
    background: #146c43;
}

.submit-btn {
    width: 100%;
    margin-top: 20px;
}

.alert {
    padding: 13px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.success {
    background: #d9f2e3;
    color: #17643b;
}

.error {
    background: #ffe0e0;
    color: #a32626;
}

.filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.filters input {
    flex: 1;
}

.filters select {
    width: 150px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}

th {
    background: #173b32;
    color: white;
}

tr:hover {
    background: #f8faf9;
}

.badge {
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.stock-in {
    background: #d9f2e3;
    color: #17643b;
}

.stock-out {
    background: #ffe0e0;
    color: #a32626;
}

.filter-btn {
    background: #173b32;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #777;
}

@media(max-width: 950px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .content {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 550px) {
    body {
        padding: 15px;
    }

    .stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .filters {
        flex-direction: column;
    }

    .filters select {
        width: 100%;
    }
}
</style>
</head>

<body>

<div class="container">

<a href="dashboard.php" class="back">
    &larr; Back to Dashboard
</a>

<div class="header">
    <h1>Stock Management</h1>
    <p>Manage inventory, track stock movements and monitor stock levels.</p>
</div>

<?php if ($message !== ""): ?>
<div class="alert <?= $message_type ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Statistics -->
<div class="stats">

    <div class="stat-card">
        <h3>Total Products</h3>
        <h2><?= number_format($total_products) ?></h2>
    </div>

    <div class="stat-card">
        <h3>Available Stock Units</h3>
        <h2><?= number_format($total_stock) ?></h2>
    </div>

    <div class="stat-card">
        <h3>Total Stock In</h3>
        <h2><?= number_format($total_in) ?></h2>
    </div>

    <div class="stat-card out">
        <h3>Total Stock Out</h3>
        <h2><?= number_format($total_out) ?></h2>
    </div>

</div>

<div class="stats">

    <div class="stat-card low">
        <h3>Low Stock Products</h3>
        <h2><?= number_format($low_stock) ?></h2>
    </div>

</div>

<div class="content">

<!-- Stock movement form -->
<div class="card">

    <h2>Record Stock Movement</h2>

    <form method="POST">

        <label>Select Product</label>

        <select name="product_id" required>
            <option value="">Choose a product</option>

            <?php while ($p = $products->fetch_assoc()): ?>
            <option value="<?= (int)$p['product_id'] ?>">
                <?= htmlspecialchars($p['product_name']) ?>
                (<?= (int)$p['quantity'] ?> available)
            </option>
            <?php endwhile; ?>

        </select>

        <label>Movement Type</label>

        <select name="movement_type" required>
            <option value="Stock In">Stock In</option>
            <option value="Stock Out">Stock Out</option>
        </select>

        <label>Quantity</label>

        <input type="number"
               name="quantity"
               min="1"
               placeholder="Enter quantity"
               required>

        <button type="submit" class="submit-btn">
            Record Movement
        </button>

    </form>

</div>

<!-- Stock history -->
<div class="card">

    <h2>Stock Movement History</h2>

    <form method="GET" class="filters">

        <input type="text"
               name="search"
               placeholder="Search product or user..."
               value="<?= htmlspecialchars($search) ?>">

        <select name="filter">
            <option value="All"
                <?= $filter === 'All' ? 'selected' : '' ?>>
                All Movements
            </option>

            <option value="Stock In"
                <?= $filter === 'Stock In' ? 'selected' : '' ?>>
                Stock In
            </option>

            <option value="Stock Out"
                <?= $filter === 'Stock Out' ? 'selected' : '' ?>>
                Stock Out
            </option>
        </select>

        <button type="submit" class="filter-btn">
            Search
        </button>

    </form>

    <div class="table-wrapper">

    <table>

        <thead>
        <tr>
            <th>ID</th>
            <th>Product</th>
            <th>Recorded By</th>
            <th>Movement</th>
            <th>Quantity</th>
            <th>Date</th>
        </tr>
        </thead>

        <tbody>

        <?php if ($history->num_rows > 0): ?>

            <?php while ($r = $history->fetch_assoc()): ?>

            <tr>

                <td><?= (int)$r['movement_id'] ?></td>

                <td>
                    <?= htmlspecialchars($r['product_name']) ?>
                </td>

                <td>
                    <?= htmlspecialchars(
                        $r['full_name'] ?? 'Unknown User'
                    ) ?>
                </td>

                <td>
                    <span class="badge <?= $r['movement_type'] === 'Stock In'
                        ? 'stock-in' : 'stock-out' ?>">
                        <?= htmlspecialchars($r['movement_type']) ?>
                    </span>
                </td>

                <td><?= (int)$r['quantity'] ?></td>

                <td>
                    <?= htmlspecialchars($r['movement_date']) ?>
                </td>

            </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="6" class="empty">
                    No stock movements found.
                </td>
            </tr>

        <?php endif; ?>

        </tbody>

    </table>

    </div>

    <p style="margin-top:15px;color:#777;font-size:13px;">
        Showing up to 100 recent stock movements.
    </p>

</div>

</div>
</div>

</body>
</html>
