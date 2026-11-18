
<?php
session_start();
require_once 'connection.php';

$message = "";
$messageType = "success";

// Escape output safely
function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ADD PRODUCT
if (isset($_POST['add_product'])) {

    $category_id = (int)($_POST['category_id'] ?? 0);
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $product_name = trim($_POST['product_name'] ?? '');
    $buying_price = (float)($_POST['buying_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);

    if (
        $product_name === '' ||
        $category_id <= 0 ||
        $supplier_id <= 0 ||
        $buying_price < 0 ||
        $selling_price < 0 ||
        $quantity < 0 ||
        $minimum_stock < 0
    ) {
        $message = "Please enter valid product details.";
        $messageType = "error";
    } else {

        // Confirm category exists
        $check = $conn->prepare(
            "SELECT category_id FROM categories WHERE category_id = ?"
        );
        $check->bind_param("i", $category_id);
        $check->execute();
        $categoryExists = $check->get_result()->num_rows > 0;
        $check->close();

        // Confirm supplier exists
        $check = $conn->prepare(
            "SELECT supplier_id FROM suppliers WHERE supplier_id = ?"
        );
        $check->bind_param("i", $supplier_id);
        $check->execute();
        $supplierExists = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$categoryExists || !$supplierExists) {
            $message = "Please select a valid category and supplier.";
            $messageType = "error";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO products
                (category_id, supplier_id, product_name,
                 buying_price, selling_price, quantity, minimum_stock)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iisddii",
                $category_id,
                $supplier_id,
                $product_name,
                $buying_price,
                $selling_price,
                $quantity,
                $minimum_stock
            );

            if ($stmt->execute()) {
                header("Location: products.php?success=added");
                exit();
            } else {
                $message = "Unable to add product: " . $stmt->error;
                $messageType = "error";
            }

            $stmt->close();
        }
    }
}

// UPDATE PRODUCT
if (isset($_POST['update_product'])) {

    $product_id = (int)($_POST['product_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $product_name = trim($_POST['product_name'] ?? '');
    $buying_price = (float)($_POST['buying_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);

    if (
        $product_id <= 0 ||
        $product_name === '' ||
        $category_id <= 0 ||
        $supplier_id <= 0 ||
        $buying_price < 0 ||
        $selling_price < 0 ||
        $quantity < 0 ||
        $minimum_stock < 0
    ) {
        $message = "Please enter valid product details.";
        $messageType = "error";
    } else {

        $stmt = $conn->prepare("
            UPDATE products SET
                category_id = ?,
                supplier_id = ?,
                product_name = ?,
                buying_price = ?,
                selling_price = ?,
                quantity = ?,
                minimum_stock = ?
            WHERE product_id = ?
        ");

        $stmt->bind_param(
            "iisddiii",
            $category_id,
            $supplier_id,
            $product_name,
            $buying_price,
            $selling_price,
            $quantity,
            $minimum_stock,
            $product_id
        );

        if ($stmt->execute()) {
            header("Location: products.php?success=updated");
            exit();
        } else {
            $message = "Unable to update product: " . $stmt->error;
            $messageType = "error";
        }

        $stmt->close();
    }
}

// DELETE PRODUCT
if (isset($_POST['delete_product'])) {

    $product_id = (int)($_POST['product_id'] ?? 0);

    $stmt = $conn->prepare(
        "DELETE FROM products WHERE product_id = ?"
    );

    $stmt->bind_param("i", $product_id);

    if ($stmt->execute()) {

        if ($stmt->affected_rows > 0) {
            header("Location: products.php?success=deleted");
            exit();
        } else {
            $message = "Product was not found.";
            $messageType = "error";
        }

    } else {
        $message = "Product could not be deleted. It may be linked to existing sales or stock records.";
        $messageType = "error";
    }

    $stmt->close();
}

// SUCCESS MESSAGES
if (isset($_GET['success'])) {

    $successMessages = [
        'added' => 'Product added successfully!',
        'updated' => 'Product updated successfully!',
        'deleted' => 'Product deleted successfully!'
    ];

    if (isset($successMessages[$_GET['success']])) {
        $message = $successMessages[$_GET['success']];
        $messageType = "success";
    }
}

// LOAD CATEGORIES
$categories = $conn->query("
    SELECT category_id, category_name
    FROM categories
    ORDER BY category_name ASC
");

// LOAD SUPPLIERS
$suppliers = $conn->query("
    SELECT supplier_id, supplier_name
    FROM suppliers
    ORDER BY supplier_name ASC
");

// GET PRODUCT FOR EDITING
$editProduct = null;

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare("
        SELECT * FROM products
        WHERE product_id = ?
    ");

    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $editProduct = $result->fetch_assoc();
    }

    $stmt->close();
}

// SEARCH PRODUCTS
$search = trim($_GET['search'] ?? '');
$searchTerm = "%" . $search . "%";

$stmt = $conn->prepare("
    SELECT
        p.product_id,
        p.product_name,
        p.buying_price,
        p.selling_price,
        p.quantity,
        p.minimum_stock,
        c.category_name,
        s.supplier_name
    FROM products p
    LEFT JOIN categories c
        ON p.category_id = c.category_id
    LEFT JOIN suppliers s
        ON p.supplier_id = s.supplier_id
    WHERE p.product_name LIKE ?
    ORDER BY p.product_id DESC
");

$stmt->bind_param("s", $searchTerm);
$stmt->execute();

$products = $stmt->get_result();

// STOCK STATISTICS
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(
            SUM(CASE
                WHEN quantity <= minimum_stock THEN 1
                ELSE 0
            END), 0
        ) AS low_stock
    FROM products
");

$statistics = $stats->fetch_assoc();

$totalProducts = $statistics['total'];
$lowStock = $statistics['low_stock'];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management | SpazaSmart</title>
    <link rel="stylesheet" href="/SpazaSmart/style.css?v=3">
</head>

<body class="product-page">

<div class="product-page">

    <div class="page-header">
        <div>
            <h1>Product Management</h1>
            <p class="subtitle">Manage your SpazaSmart inventory.</p>
        </div>

        <a href="dashboard.php" class="back-button">
            Back to Dashboard
        </a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?= clean($message) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="statistics">

        <div class="stat-card">
            <h3>Total Products</h3>
            <p><?= number_format($totalProducts) ?></p>
        </div>

        <div class="stat-card">
            <h3>Low Stock Products</h3>
            <p><?= number_format($lowStock) ?></p>
        </div>

    </div>

    <!-- ADD / EDIT PRODUCT FORM -->
    <div class="form-box">

        <h2>
            <?= $editProduct ? "Edit Product" : "Add New Product" ?>
        </h2>

        <form method="POST" action="products.php">

            <?php if ($editProduct): ?>
                <input
                    type="hidden"
                    name="product_id"
                    value="<?= (int)$editProduct['product_id'] ?>"
                >
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group">
                    <label>Product Name</label>
                    <input
                        type="text"
                        name="product_name"
                        value="<?= clean($editProduct['product_name'] ?? '') ?>"
                        placeholder="Enter product name"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Category</label>

                    <select name="category_id" required>
                        <option value="">Select Category</option>

                        <?php while ($category = $categories->fetch_assoc()): ?>
                            <option
                                value="<?= (int)$category['category_id'] ?>"
                                <?= (
                                    isset($editProduct['category_id']) &&
                                    $editProduct['category_id'] == $category['category_id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= clean($category['category_name']) ?>
                            </option>
                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Supplier</label>

                    <select name="supplier_id" required>
                        <option value="">Select Supplier</option>

                        <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                            <option
                                value="<?= (int)$supplier['supplier_id'] ?>"
                                <?= (
                                    isset($editProduct['supplier_id']) &&
                                    $editProduct['supplier_id'] == $supplier['supplier_id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= clean($supplier['supplier_name']) ?>
                            </option>
                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Buying Price (R)</label>
                    <input
                        type="number"
                        name="buying_price"
                        min="0"
                        step="0.01"
                        value="<?= clean($editProduct['buying_price'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Selling Price (R)</label>
                    <input
                        type="number"
                        name="selling_price"
                        min="0"
                        step="0.01"
                        value="<?= clean($editProduct['selling_price'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input
                        type="number"
                        name="quantity"
                        min="0"
                        value="<?= clean($editProduct['quantity'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Minimum Stock</label>
                    <input
                        type="number"
                        name="minimum_stock"
                        min="0"
                        value="<?= clean($editProduct['minimum_stock'] ?? '') ?>"
                        required
                    >
                </div>

            </div>

            <button
                type="submit"
                name="<?= $editProduct ? 'update_product' : 'add_product' ?>"
            >
                <?= $editProduct ? 'Update Product' : 'Add Product' ?>
            </button>

            <?php if ($editProduct): ?>
                <a href="products.php">Cancel Editing</a>
            <?php endif; ?>

        </form>

    </div>

    <!-- SEARCH PRODUCTS -->
    <div class="table-box">

        <h2>Product List</h2>

        <form method="GET" action="products.php" class="search">

            <input
                type="text"
                name="search"
                placeholder="Search product name..."
                value="<?= clean($search) ?>"
            >

            <button type="submit">Search</button>

            <a href="products.php">Clear Search</a>

        </form>

        <table>

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Supplier</th>
                    <th>Buying Price</th>
                    <th>Selling Price</th>
                    <th>Quantity</th>
                    <th>Minimum Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($products->num_rows > 0): ?>

                    <?php while ($product = $products->fetch_assoc()): ?>

                        <tr>

                            <td><?= (int)$product['product_id'] ?></td>

                            <td><?= clean($product['product_name']) ?></td>

                            <td><?= clean($product['category_name'] ?? 'Not assigned') ?></td>

                            <td><?= clean($product['supplier_name'] ?? 'Not assigned') ?></td>

                            <td>R<?= number_format((float)$product['buying_price'], 2) ?></td>

                            <td>R<?= number_format((float)$product['selling_price'], 2) ?></td>

                            <td><?= (int)$product['quantity'] ?></td>

                            <td><?= (int)$product['minimum_stock'] ?></td>

                            <td>
                                <?php if ($product['quantity'] <= $product['minimum_stock']): ?>
                                    <span class="low">Low Stock</span>
                                <?php else: ?>
                                    <span class="in-stock">In Stock</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <a href="products.php?edit=<?= (int)$product['product_id'] ?>">
                                    Edit
                                </a>

                                <form method="POST" action="products.php">
                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= (int)$product['product_id'] ?>"
                                    >

                                    <button type="submit" name="delete_product">
                                        Delete
                                    </button>
                                </form>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10">No products found.</td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>

