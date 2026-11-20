
<?php
session_start();
require_once 'connection.php';

// =====================================
// LOGIN AND ROLE PROTECTION
// =====================================

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only owners and staff can access stock management
if (!in_array($_SESSION['role'] ?? '', ['owner', 'staff'], true)) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

// Display success message after redirect
if (isset($_SESSION['stock_message'])) {
    $message = $_SESSION['stock_message'];
    $message_type = "success";

    unset($_SESSION['stock_message']);
}

// Record stock movement
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_id = (int)($_POST['product_id'] ?? 0);
    $type = $_POST['movement_type'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $user_id = (int)$_SESSION['user_id'];

    if (
        $product_id <= 0 ||
        $quantity < 1 ||
        !in_array($type, ['Stock In', 'Stock Out'], true)
    ) {
        $message = "Please enter valid stock details.";
        $message_type = "error";

    } else {

        try {

            $conn->begin_transaction();

            // Get current product quantity
            $stmt = $conn->prepare(
                "SELECT quantity
                 FROM products
                 WHERE product_id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $product_id);
            $stmt->execute();

            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product) {
                throw new Exception("Product not found.");
            }

            $current = (int)$product['quantity'];

            // Prevent stock from going below zero
            if ($type === "Stock Out" && $quantity > $current) {
                throw new Exception(
                    "Insufficient stock. Only $current units available."
                );
            }

            // Calculate new stock quantity
            $new_quantity = ($type === "Stock In")
                ? $current + $quantity
                : $current - $quantity;

            // Update product quantity
            $stmt = $conn->prepare(
                "UPDATE products
                 SET quantity = ?
                 WHERE product_id = ?"
            );

            $stmt->bind_param("ii", $new_quantity, $product_id);
            $stmt->execute();
            $stmt->close();

            // Save stock movement history
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

            // Save all database changes
            $conn->commit();

            // Redirect to prevent duplicate submissions
            $_SESSION['stock_message'] =
                "Stock movement recorded successfully!";

            header("Location: stock.php");
            exit();

        } catch (Throwable $e) {

            $conn->rollback();

            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}

// Dashboard statistics

// Total products
$total_products = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products"
)->fetch_assoc()['total'];

// Total available stock units
$total_stock = $conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM products"
)->fetch_assoc()['total'];

// Low-stock products using individual minimum stock levels
$low_stock = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE quantity <= minimum_stock"
)->fetch_assoc()['total'];

// Total stock received
$total_in = $conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM stock_movement
     WHERE movement_type = 'Stock In'"
)->fetch_assoc()['total'];

// Total stock going out
$total_out = $conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM stock_movement
     WHERE movement_type = 'Stock Out'"
)->fetch_assoc()['total'];

// Product dropdown
$products = $conn->query(
    "SELECT product_id, product_name, quantity
     FROM products
     ORDER BY product_name ASC"
);

// Search and filter stock history
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'All';

if (!in_array($filter, ['All', 'Stock In', 'Stock Out'], true)) {
    $filter = 'All';
}

// Stock movement history query
$sql = "SELECT
            sm.movement_id,
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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Stock Management | SpazaSmart</title>

    <link rel="stylesheet" href="/SpazaSmart/style.css?v=7">
</head>

<body class="stock-page">

<div class="stock-container">
    <a href="<?= ($_SESSION['role'] ?? '') === 'owner'
    ? 'dashboard.php'
    : 'staff_dashboard.php' ?>" class="stock-back">
    &larr; Back to Dashboard
</a>


    <!-- PAGE HEADER -->
     <div>
        <span class="stock-tag">SPAZASMART MANAGEMENT</span>

        <h1>Stock Management</h1>

        <p>
            Manage inventory, track stock movements
            and monitor stock levels.
        </p>

    </div>

    <!-- SUCCESS AND ERROR MESSAGES -->
    <?php if ($message !== ""): ?>

        <div class="stock-message <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <!-- FIRST ROW OF STATISTICS -->
    <div class="stock-stats">

        <div class="stock-stat-card">

            <h3>Total Products</h3>

            <h2><?= number_format($total_products) ?></h2>

        </div>


        <div class="stock-stat-card">

            <h3>Available Stock Units</h3>

            <h2><?= number_format($total_stock) ?></h2>

        </div>


        <div class="stock-stat-card in">

            <h3>Total Stock In</h3>

            <h2><?= number_format($total_in) ?></h2>

        </div>


        <div class="stock-stat-card out">

            <h3>Total Stock Out</h3>

            <h2><?= number_format($total_out) ?></h2>

        </div>

    </div>


    <!-- SECOND ROW: LOW STOCK -->
    <div class="stock-stats">

        <div class="stock-stat-card low">

            <h3>Low Stock Products</h3>

            <h2><?= number_format($low_stock) ?></h2>

        </div>

    </div>


    <!-- MAIN CONTENT -->
    <div class="stock-content">


        <!-- RECORD STOCK MOVEMENT -->
        <div class="stock-panel">

            <h2>Record Stock Movement</h2>

            <form method="POST" class="stock-form">

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

                <input
                    type="number"
                    name="quantity"
                    min="1"
                    placeholder="Enter quantity"
                    required
                >


                <button type="submit" class="stock-submit">
                    Record Movement
                </button>

            </form>

        </div>


        <!-- STOCK MOVEMENT HISTORY -->
        <div class="stock-panel">

            <h2>Stock Movement History</h2>

            <!-- SEARCH AND FILTER -->
            <form method="GET" class="stock-filters">

                <input
                    type="text"
                    name="search"
                    placeholder="Search product or user..."
                    value="<?= htmlspecialchars($search) ?>"
                >

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

                <button type="submit" class="stock-search-btn">
                    Search
                </button>

            </form>


            <!-- HISTORY TABLE -->
            <div class="stock-table-wrapper">

                <table class="stock-table">

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

                                <td>
                                    <?= (int)$r['movement_id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($r['product_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $r['full_name'] ?? 'Unknown User'
                                    ) ?>
                                </td>

                                <td>

                                    <span class="stock-badge <?= 
                                        $r['movement_type'] === 'Stock In'
                                        ? 'stock-in'
                                        : 'stock-out'
                                    ?>">

                                        <?= htmlspecialchars(
                                            $r['movement_type']
                                        ) ?>

                                    </span>

                                </td>

                                <td>
                                    <?= (int)$r['quantity'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($r['movement_date']) ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6" class="stock-empty">
                                No stock movements found.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <p class="stock-note">
                Showing up to 100 recent stock movements.
            </p>

        </div>

    </div>

</div>

</body>
</html>


