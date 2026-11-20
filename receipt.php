
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

// Only owners and staff can access receipts
if (!in_array($_SESSION['role'] ?? '', ['owner', 'staff'], true)) {
    header("Location: login.php");
    exit();
}

// =====================================
// VALIDATE SALE ID
// =====================================

if (
    !isset($_GET['sale_id']) ||
    !filter_var($_GET['sale_id'], FILTER_VALIDATE_INT) ||
    (int)$_GET['sale_id'] <= 0
) {
    die("Invalid receipt number.");
}

$sale_id = (int)$_GET['sale_id'];

// =====================================
// GET SALE DETAILS
// =====================================

$stmt = $conn->prepare("
    SELECT s.sale_id, s.total_amount, s.payment_method,
           s.sale_date, u.full_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.user_id
    WHERE s.sale_id = ?
");

$stmt->bind_param("i", $sale_id);
$stmt->execute();

$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check whether sale exists
if (!$sale) {
    die("Receipt not found.");
}

// =====================================
// GET PURCHASED PRODUCTS
// =====================================

$stmt = $conn->prepare("
    SELECT p.product_name, si.quantity,
           si.unit_price, si.subtotal
    FROM sale_items si
    JOIN products p ON si.product_id = p.product_id
    WHERE si.sale_id = ?
");

$stmt->bind_param("i", $sale_id);
$stmt->execute();

$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// =====================================
// FORMAT MONEY
// =====================================

function money($amount) {
    return 'R' . number_format((float)$amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>SpazaSmart - Receipt</title>

    <link rel="stylesheet" href="/SpazaSmart/style.css">

</head>

<body>

<div class="container">

    <h1>Spaza Smart</h1>

    <h2>Sales Receipt</h2>

    <hr>

    <p>
        <strong>Receipt Number:</strong>
        #<?= (int)$sale['sale_id'] ?>
    </p>

    <p>
        <strong>Cashier:</strong>
        <?= htmlspecialchars($sale['full_name'] ?? 'Unknown') ?>
    </p>

    <p>
        <strong>Date:</strong>
        <?= htmlspecialchars($sale['sale_date']) ?>
    </p>

    <p>
        <strong>Payment Method:</strong>
        <?= htmlspecialchars($sale['payment_method']) ?>
    </p>

    <hr>

    <h3>Purchased Products</h3>

    <table>

        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>

        <tbody>

            <?php if ($items): ?>

                <?php foreach ($items as $item): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($item['product_name']) ?>
                        </td>

                        <td>
                            <?= (int)$item['quantity'] ?>
                        </td>

                        <td>
                            <?= money($item['unit_price']) ?>
                        </td>

                        <td>
                            <?= money($item['subtotal']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4">
                        No products found for this sale.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

    <hr>

    <h2>
        Total: <?= money($sale['total_amount']) ?>
    </h2>

    <p>Thank you for shopping with Spaza Smart!</p>

    <button type="button" onclick="window.print()">
        Print Receipt
    </button>

    <a href="sales.php">
        Back to Sales
    </a>

</div>

</body>
</html>
