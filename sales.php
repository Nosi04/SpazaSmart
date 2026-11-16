
<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();

    try {
        $user_id = $_SESSION['user_id'];
        $payment = $_POST['payment_method'];
        $products = $_POST['product_id'];
        $quantities = $_POST['quantity'];
        $total = 0;
        $items = [];

        foreach ($products as $i => $id) {
            $id = (int)$id;
            $qty = (int)$quantities[$i];

            if ($id <= 0 || $qty <= 0) {
                throw new Exception("Select a product and enter a valid quantity.");
            }

            $stmt = $conn->prepare(
                "SELECT product_name, selling_price, quantity FROM products WHERE product_id=? FOR UPDATE"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();

            if (!$product || $product['quantity'] < $qty) {
                throw new Exception("Insufficient stock for " . ($product['product_name'] ?? 'selected product'));
            }

            $price = $product['selling_price'];
            $subtotal = $price * $qty;
            $total += $subtotal;

            $items[] = [
                'id' => $id,
                'qty' => $qty,
                'price' => $price,
                'subtotal' => $subtotal
            ];
        }

        $stmt = $conn->prepare(
            "INSERT INTO sales (user_id, total_amount, payment_method, sale_date) VALUES (?, ?, ?, NOW())"
        );
        $stmt->bind_param("ids", $user_id, $total, $payment);
        $stmt->execute();
        $sale_id = $conn->insert_id;

        foreach ($items as $item) {
            $stmt = $conn->prepare(
                "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iiidd",
                $sale_id,
                $item['id'],
                $item['qty'],
                $item['price'],
                $item['subtotal']
            );
            $stmt->execute();

            $stmt = $conn->prepare(
                "UPDATE products SET quantity = quantity - ? WHERE product_id = ?"
            );
            $stmt->bind_param("ii", $item['qty'], $item['id']);
            $stmt->execute();

            $type = "Stock Out";
            $stmt = $conn->prepare(
                "INSERT INTO stock_movement (product_id, user_id, movement_type, quantity, movement_date) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param("iisi", $item['id'], $user_id, $type, $item['qty']);
            $stmt->execute();
        }

        $conn->commit();
        $message = "Sale recorded successfully! Total: R" . number_format($total, 2);

    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
    }
}

$productList = $conn->query(
    "SELECT product_id, product_name, selling_price, quantity FROM products WHERE quantity > 0 ORDER BY product_name"
);

$sales = $conn->query(
    "SELECT * FROM sales ORDER BY sale_date DESC"
);
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="style.css">
    <title>Sales Management - Spaza Smart</title>
    <style>
        body { font-family: Arial; margin: 30px; background: #f5f5f5; }
        .container { background: white; padding: 25px; border-radius: 10px; }
        input, select, button { padding: 10px; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #198754; color: white; }
        .message { color: green; }
        .item { margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Sales Management</h2>

    <p class="message"><?= htmlspecialchars($message) ?></p>

    <h3>Record New Sale</h3>

    <form method="POST">
        <div id="items">
            <div class="item">
                <select name="product_id[]" class="product" required>
                    <option value="">Select Product</option>
                    <?php
                    $productList->data_seek(0);
                    while ($p = $productList->fetch_assoc()):
                    ?>
                        <option value="<?= $p['product_id'] ?>"
                            data-price="<?= $p['selling_price'] ?>">
                            <?= htmlspecialchars($p['product_name']) ?>
                            - R<?= number_format($p['selling_price'], 2) ?>
                            (Stock: <?= $p['quantity'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="number" name="quantity[]" class="qty"
                       min="1" placeholder="Quantity" required>

                <span class="subtotal">R0.00</span>
                <button type="button" onclick="this.parentElement.remove(); calculate()">Remove</button>
            </div>
        </div>

        <button type="button" onclick="addItem()">Add Product</button>

        <h3>Total: R<span id="total">0.00</span></h3>

        <label>Payment Method:</label>
        <select name="payment_method" required>
            <option value="Cash">Cash</option>
            <option value="Card">Card</option>
            <option value="EFT">EFT</option>
        </select>

        <button type="submit">Complete Sale</button>
    </form>

    <hr>

    <h3>Sales History</h3>

    <table>
        <tr>
            <th>Sale ID</th>
            <th>Total Amount</th>
            <th>Payment Method</th>
            <th>Date</th>
        </tr>

        <?php while ($sale = $sales->fetch_assoc()): ?>
        <tr>
            <td><?= $sale['sale_id'] ?></td>
            <td>R<?= number_format($sale['total_amount'], 2) ?></td>
            <td><?= htmlspecialchars($sale['payment_method']) ?></td>
            <td><?= $sale['sale_date'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<script>
function calculate() {
    let total = 0;

    document.querySelectorAll('.item').forEach(item => {
        let select = item.querySelector('.product');
        let qty = item.querySelector('.qty').value;
        let price = select.selectedOptions[0]?.dataset.price || 0;
        let subtotal = price * qty;

        item.querySelector('.subtotal').textContent = 'R' + subtotal.toFixed(2);
        total += subtotal;
    });

    document.getElementById('total').textContent = total.toFixed(2);
}

document.addEventListener('input', calculate);
document.addEventListener('change', calculate);

function addItem() {
    let item = document.querySelector('.item').cloneNode(true);
    item.querySelector('.product').selectedIndex = 0;
    item.querySelector('.qty').value = '';
    item.querySelector('.subtotal').textContent = 'R0.00';
    document.getElementById('items').appendChild(item);
}
</script>

</body>
</html>
