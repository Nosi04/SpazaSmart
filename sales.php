
<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

// Display success message after redirect
if (isset($_SESSION['sales_message'])) {
    $message = $_SESSION['sales_message'];
    $message_type = "success";

    unset($_SESSION['sales_message']);
}

// Record a new sale
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user_id = (int)$_SESSION['user_id'];
    $payment = $_POST['payment_method'] ?? '';
    $products = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    try {

        // Validate payment method
        if (!in_array($payment, ['Cash', 'Card', 'EFT'], true)) {
            throw new Exception("Please select a valid payment method.");
        }

        // Validate product and quantity arrays
        if (
            !is_array($products) ||
            !is_array($quantities) ||
            empty($products) ||
            count($products) !== count($quantities)
        ) {
            throw new Exception("Please add at least one product.");
        }

        $items = [];
        $required_stock = [];
        $total = 0;

        // Validate selected products and quantities
        foreach ($products as $i => $id) {

            $id = filter_var($id, FILTER_VALIDATE_INT);
            $qty = filter_var(
                $quantities[$i] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || !$qty || $id <= 0 || $qty <= 0) {
                throw new Exception(
                    "Select a product and enter a valid quantity."
                );
            }

            // Calculate total quantity needed for each product
            if (!isset($required_stock[$id])) {
                $required_stock[$id] = 0;
            }

            $required_stock[$id] += $qty;

            $items[] = [
                'id' => $id,
                'qty' => $qty
            ];
        }

        // Start database transaction
        $conn->begin_transaction();

        // Lock and check stock for each selected product
        $product_details = [];

        // Sort IDs to help keep locking order consistent
        $product_ids = array_keys($required_stock);
        sort($product_ids);

        foreach ($product_ids as $id) {

            $stmt = $conn->prepare(
                "SELECT product_name, selling_price, quantity
                 FROM products
                 WHERE product_id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product) {
                throw new Exception("Selected product was not found.");
            }

            $available = (int)$product['quantity'];
            $needed = $required_stock[$id];

            if ($available < $needed) {
                throw new Exception(
                    "Insufficient stock for " .
                    $product['product_name'] .
                    ". Available: " . $available .
                    ", Required: " . $needed
                );
            }

            $product_details[$id] = [
                'name' => $product['product_name'],
                'price' => (float)$product['selling_price']
            ];
        }

        // Calculate sale total
        foreach ($items as $index => $item) {

            $id = $item['id'];
            $qty = $item['qty'];

            $price = $product_details[$id]['price'];
            $subtotal = $price * $qty;

            $items[$index]['price'] = $price;
            $items[$index]['subtotal'] = $subtotal;

            $total += $subtotal;
        }

        // Insert sale record
        $stmt = $conn->prepare(
            "INSERT INTO sales
            (user_id, total_amount, payment_method, sale_date)
            VALUES (?, ?, ?, NOW())"
        );

        $stmt->bind_param("ids", $user_id, $total, $payment);
        $stmt->execute();

        $sale_id = $conn->insert_id;
        $stmt->close();

        // Insert individual sale items
        $stmt = $conn->prepare(
            "INSERT INTO sale_items
            (sale_id, product_id, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($items as $item) {

            $stmt->bind_param(
                "iiidd",
                $sale_id,
                $item['id'],
                $item['qty'],
                $item['price'],
                $item['subtotal']
            );

            $stmt->execute();
        }

        $stmt->close();

        // Update product quantities and record stock movements
        foreach ($required_stock as $id => $qty) {

            // Reduce product stock
            $stmt = $conn->prepare(
                "UPDATE products
                 SET quantity = quantity - ?
                 WHERE product_id = ?"
            );

            $stmt->bind_param("ii", $qty, $id);
            $stmt->execute();
            $stmt->close();

            // Record stock movement
            $type = "Stock Out";

            $stmt = $conn->prepare(
                "INSERT INTO stock_movement
                (product_id, user_id, movement_type, quantity, movement_date)
                VALUES (?, ?, ?, ?, NOW())"
            );

            $stmt->bind_param(
                "iisi",
                $id,
                $user_id,
                $type,
                $qty
            );

            $stmt->execute();
            $stmt->close();
        }

        // Save all changes
        $conn->commit();

        // Redirect to prevent duplicate sales
        $_SESSION['sales_message'] =
            "Sale recorded successfully! Total: R" .
            number_format($total, 2);

        header("Location: sales.php");
        exit();

    } catch (Throwable $e) {

        // Undo changes if something goes wrong
        $conn->rollback();

        $message = $e->getMessage();
        $message_type = "error";
    }
}

// Get available products
$productList = $conn->query(
    "SELECT product_id, product_name, selling_price, quantity
     FROM products
     WHERE quantity > 0
     ORDER BY product_name"
);

// Store products for the dropdown
$availableProducts = $productList->fetch_all(MYSQLI_ASSOC);

// Get sales history
$sales = $conn->query(
    "SELECT *
     FROM sales
     ORDER BY sale_date DESC, sale_id DESC"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Sales Management | SpazaSmart</title>

    <link rel="stylesheet" href="/SpazaSmart/style.css?v=6">

</head>

<body class="sales-page">

<div class="sales-container">

     <a href="dashboard.php" class="sales-back-button">
        &larr; Back to Dashboard
    </a>

    <h1>Sales Management</h1>

    <p class="message <?= htmlspecialchars($message_type) ?>">
        <?= htmlspecialchars($message) ?>
    </p>


    <!-- RECORD NEW SALE -->

    <h2>Record New Sale</h2>

    <?php if (count($availableProducts) > 0): ?>

        <form method="POST" id="salesForm">

            <div id="items">

                <div class="item">

                    <label>Select Product</label>

                    <select name="product_id[]" class="product" required>

                        <option value="">Select Product</option>

                        <?php foreach ($availableProducts as $p): ?>

                            <option
                                value="<?= (int)$p['product_id'] ?>"
                                data-price="<?= htmlspecialchars($p['selling_price']) ?>"
                            >

                                <?= htmlspecialchars($p['product_name']) ?>

                                - R<?= number_format($p['selling_price'], 2) ?>

                                (Stock: <?= (int)$p['quantity'] ?>)

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <label>Quantity</label>

                    <input
                        type="number"
                        name="quantity[]"
                        class="qty"
                        min="1"
                        placeholder="Enter quantity"
                        required
                    >

                    <span class="subtotal">R0.00</span>

                    <button
                        type="button"
                        class="remove-item"
                        onclick="removeItem(this)"
                    >
                        Remove
                    </button>

                </div>

            </div>


            <button type="button" onclick="addItem()">
                Add Product
            </button>


            <h3>
                Total: R<span id="total">0.00</span>
            </h3>


            <label>Payment Method</label>

            <select name="payment_method" required>

                <option value="">Select Payment Method</option>

                <option value="Cash">Cash</option>

                <option value="Card">Card</option>

                <option value="EFT">EFT</option>

            </select>


            <button type="submit">
                Complete Sale
            </button>

        </form>

    <?php else: ?>

        <p>No products are currently available for sale.</p>

    <?php endif; ?>


    <hr>


    <!-- SALES HISTORY -->

    <h2>Sales History</h2>

    <table>

        <thead>

            <tr>
                <th>Sale ID</th>
                <th>Total Amount</th>
                <th>Payment Method</th>
                <th>Date</th>
            </tr>

        </thead>

        <tbody>

            <?php if ($sales->num_rows > 0): ?>

                <?php while ($sale = $sales->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= (int)$sale['sale_id'] ?>
                        </td>

                        <td>
                            R<?= number_format($sale['total_amount'], 2) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($sale['payment_method']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($sale['sale_date']) ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4">No sales recorded yet.</td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- SALES CALCULATION JAVASCRIPT -->

<script>

function calculate() {

    let total = 0;

    document.querySelectorAll('.item').forEach(item => {

        let select = item.querySelector('.product');
        let qty = parseInt(item.querySelector('.qty').value) || 0;

        let price = parseFloat(
            select.selectedOptions[0]?.dataset.price || 0
        );

        let subtotal = price * qty;

        item.querySelector('.subtotal').textContent =
            'R' + subtotal.toFixed(2);

        total += subtotal;

    });

    document.getElementById('total').textContent =
        total.toFixed(2);

}


function addItem() {

    let firstItem = document.querySelector('.item');

    let newItem = firstItem.cloneNode(true);

    newItem.querySelector('.product').selectedIndex = 0;

    newItem.querySelector('.qty').value = '';

    newItem.querySelector('.subtotal').textContent = 'R0.00';

    document.getElementById('items').appendChild(newItem);

    calculate();

}


function removeItem(button) {

    let items = document.querySelectorAll('.item');

    if (items.length > 1) {

        button.parentElement.remove();

        calculate();

    } else {

        alert("At least one product must remain.");

    }

}


// Update total when product or quantity changes
document.addEventListener('input', calculate);

document.addEventListener('change', calculate);

</script>

</body>
</html>
