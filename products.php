
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'connection.php';

$message = "";

// ADD PRODUCT
if (isset($_POST['add_product'])) {

    $category_id = (int) $_POST['category_id'];
    $supplier_id = (int) $_POST['supplier_id'];
    $product_name = trim($_POST['product_name']);
    $buying_price = (float) $_POST['buying_price'];
    $selling_price = (float) $_POST['selling_price'];
    $quantity = (int) $_POST['quantity'];
    $minimum_stock = (int) $_POST['minimum_stock'];

    if ($buying_price < 0 || $selling_price < 0 ||
        $quantity < 0 || $minimum_stock < 0) {

        $message = "Values cannot be negative.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO products
            (category_id, supplier_id, product_name,
            buying_price, selling_price, quantity, minimum_stock)
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            die("Insert preparation failed: " . $conn->error);
        }

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
            exit;
        } else {
            $message = "Error adding product: " . $stmt->error;
        }

        $stmt->close();
    }
}


// DELETE PRODUCT
if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    $stmt = $conn->prepare(
        "DELETE FROM products WHERE product_id = ?"
    );

    if (!$stmt) {
        die("Delete preparation failed: " . $conn->error);
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: products.php?success=deleted");
        exit;
    } else {
        $message = "Unable to delete product. It may have related stock movement records.";
    }

    $stmt->close();
}


// EDIT / UPDATE PRODUCT
if (isset($_POST['update_product'])) {

    $product_id = (int) $_POST['product_id'];
    $category_id = (int) $_POST['category_id'];
    $supplier_id = (int) $_POST['supplier_id'];
    $product_name = trim($_POST['product_name']);
    $buying_price = (float) $_POST['buying_price'];
    $selling_price = (float) $_POST['selling_price'];
    $quantity = (int) $_POST['quantity'];
    $minimum_stock = (int) $_POST['minimum_stock'];

    if (
        $product_name === "" ||
        $buying_price < 0 ||
        $selling_price < 0 ||
        $quantity < 0 ||
        $minimum_stock < 0
    ) {
        $message = "Please enter valid product details.";
    } else {

        $stmt = $conn->prepare(
            "UPDATE products SET
                category_id = ?,
                supplier_id = ?,
                product_name = ?,
                buying_price = ?,
                selling_price = ?,
                quantity = ?,
                minimum_stock = ?
            WHERE product_id = ?"
        );

        if (!$stmt) {
            die("Update preparation failed: " . $conn->error);
        }

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
            exit;
        } else {
            $message = "Error updating product: " . $stmt->error;
        }

        $stmt->close();
    }
}



// SUCCESS MESSAGES
if (isset($_GET['success'])) {

    if ($_GET['success'] == "added") {
        $message = "Product added successfully!";
    }

    if ($_GET['success'] == "deleted") {
        $message = "Product deleted successfully!";
    }

    if ($_GET['success'] == "updated") {
    $message = "Product updated successfully!";
}
}


// SEARCH PRODUCTS
$search = $_GET['search'] ?? '';

$stmt = $conn->prepare(
    "SELECT * FROM products
    WHERE product_name LIKE ?
    ORDER BY product_id DESC"
);

if (!$stmt) {
    die("Search preparation failed: " . $conn->error);
}

$searchTerm = "%" . $search . "%";

$stmt->bind_param("s", $searchTerm);

if (!$stmt->execute()) {
    die("Search failed: " . $stmt->error);
}

$products = $stmt->get_result();


// GET PRODUCT FOR EDITING
$editProduct = null;

if (isset($_GET['edit'])) {

    $edit_id = (int) $_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT * FROM products WHERE product_id = ?"
    );

    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $editProduct = $result->fetch_assoc();
    }

    $stmt->close();
}


// STOCK STATISTICS
$totalProducts = 0;
$lowStock = 0;

$stats = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN quantity <= minimum_stock THEN 1 ELSE 0 END) AS low_stock
     FROM products"
);

if ($stats) {
    $data = $stats->fetch_assoc();
    $totalProducts = $data['total'] ?? 0;
    $lowStock = $data['low_stock'] ?? 0;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">

<title>Product Management | SpazaSmart</title>

<style>

* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    padding: 30px;
    background: #f4f6f9;
    color: #263238;
}

.container {
    max-width: 1200px;
    margin: auto;
}

h1 {
    color: #174d35;
}

.subtitle {
    color: #666;
    margin-bottom: 25px;
}

.message {
    background: #d9f5e5;
    color: #176b45;
    padding: 14px;
    margin-bottom: 20px;
    border-radius: 6px;
}

.statistics {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    box-shadow: 0 2px 8px #00000010;
}

.stat-card h3 {
    color: #666;
    margin-top: 0;
}

.stat-card p {
    font-size: 28px;
    font-weight: bold;
    color: #174d35;
    margin-bottom: 0;
}

.form-box, .table-box {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px #00000010;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

label {
    font-weight: bold;
}

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    margin-top: 7px;
}

button {
    background: #176b45;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 15px;
}

button:hover {
    background: #104b30;
}

.search {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.search input {
    max-width: 350px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

th {
    background: #174d35;
    color: white;
}

.low {
    color: #d97706;
    font-weight: bold;
}

.in-stock {
    color: #15803d;
    font-weight: bold;
}

.delete {
    color: #dc2626;
    text-decoration: none;
    font-weight: bold;
}

.delete:hover {
    text-decoration: underline;
}

@media(max-width: 700px) {

    body {
        padding: 12px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .statistics {
        grid-template-columns: 1fr;
    }

    .table-box {
        overflow-x: auto;
    }

}

</style>

</head>

<body>

<div class="container">

    <h1>Product Management</h1>

    <p class="subtitle">
        Manage your Spaza Smart inventory.
    </p>


    <?php if ($message != ""): ?>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <!-- STATISTICS -->

    <div class="statistics">

        <div class="stat-card">

            <h3>Total Products</h3>

            <p><?= $totalProducts ?></p>

        </div>


        <div class="stat-card">

            <h3>Low Stock Products</h3>

            <p><?= $lowStock ?></p>

        </div>

    </div>


    <!-- ADD PRODUCT FORM -->

    
<div class="form-box">

    <h2>
        <?= $editProduct ? "Edit Product" : "Add New Product" ?>
    </h2>

    <form method="POST">

        <?php if ($editProduct): ?>

            <input
                type="hidden"
                name="product_id"
                value="<?= $editProduct['product_id'] ?>"
            >

        <?php endif; ?>

        <div class="form-grid">

            <div>
                <label>Product Name</label>
                <input
                    type="text"
                    name="product_name"
                    value="<?= htmlspecialchars($editProduct['product_name'] ?? '') ?>"
                    placeholder="Enter product name"
                    required
                >
            </div>

            <div>
                <label>Category ID</label>
                <input
                    type="number"
                    name="category_id"
                    min="1"
                    value="<?= htmlspecialchars($editProduct['category_id'] ?? '') ?>"
                    required
                >
            </div>

            <div>
                <label>Supplier ID</label>
                <input
                    type="number"
                    name="supplier_id"
                    min="1"
                    value="<?= htmlspecialchars($editProduct['supplier_id'] ?? '') ?>"
                    required
                >
            </div>

            <div>
                <label>Buying Price (R)</label>
                <input
                    type="number"
                    name="buying_price"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars($editProduct['buying_price'] ?? '') ?>"
                    required
                >
            </div>

            <div>
                <label>Selling Price (R)</label>
                <input
                    type="number"
                    name="selling_price"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars($editProduct['selling_price'] ?? '') ?>"
                    required
                >
            </div>

            <div>
                <label>Quantity</label>
                <input
                    type="number"
                    name="quantity"
                    min="0"
                    value="<?= htmlspecialchars($editProduct['quantity'] ?? '') ?>"
                    required
                >
            </div>

            <div>
                <label>Minimum Stock</label>
                <input
                    type="number"
                    name="minimum_stock"
                    min="0"
                    value="<?= htmlspecialchars($editProduct['minimum_stock'] ?? '') ?>"
                    required
                >
            </div>

        </div>

        <?php if ($editProduct): ?>

            <button type="submit" name="update_product">
                Update Product
            </button>

            <a href="products.php"
               style="margin-left:15px; color:#dc2626;">
                Cancel
            </a>

        <?php else: ?>

            <button type="submit" name="add_product">
                Add Product
            </button>

        <?php endif; ?>

    </form>

</div>

    <!-- PRODUCT TABLE -->

    <div class="table-box">

        <h2>Available Products</h2>


        <form method="GET" class="search">

            <input
                type="text"
                name="search"
                placeholder="Search products..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit">
                Search
            </button>

        </form>


        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Product</th>
                    <th>Buying Price</th>
                    <th>Selling Price</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

                <?php if ($products->num_rows > 0): ?>

                    <?php while ($row = $products->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= $row['product_id'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['product_name']) ?>
                            </td>

                            <td>
                                R<?= number_format($row['buying_price'], 2) ?>
                            </td>

                            <td>
                                R<?= number_format($row['selling_price'], 2) ?>
                            </td>

                            <td>
                                <?= $row['quantity'] ?>
                            </td>

                            <td>

                                <?php if ($row['quantity'] <= $row['minimum_stock']): ?>

                                    <span class="low">Low Stock</span>

                                <?php else: ?>

                                    <span class="in-stock">In Stock</span>

                                <?php endif; ?>

                            
<td>

    <a
        href="products.php?edit=<?= $row['product_id'] ?>"
        style="color:#176b45; text-decoration:none; font-weight:bold;"
    >
        Edit
    </a>

    |

    <a
        class="delete"
        href="products.php?delete=<?= $row['product_id'] ?>"
        onclick="return confirm('Are you sure you want to delete this product?')"
    >
        Delete
    </a>

</td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="7">
                            No products found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
