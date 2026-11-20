
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

// Only owners can manage suppliers
if (($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: staff_dashboard.php");
    exit();
}

$message = "";
$messageType = "success";

// ADD SUPPLIER
if (isset($_POST['add'])) {

    $name = trim($_POST['supplier_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name !== "" && $phone !== "" &&
        filter_var($email, FILTER_VALIDATE_EMAIL) && $address !== "") {

        $stmt = $conn->prepare("
            INSERT INTO suppliers
            (supplier_name, phone_number, email, address)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param("ssss", $name, $phone, $email, $address);

        if ($stmt->execute()) {
            header("Location: suppliers.php?success=added");
            exit();
        } else {
            $message = "Unable to add supplier.";
            $messageType = "error";
        }

        $stmt->close();

    } else {
        $message = "Please enter valid supplier details.";
        $messageType = "error";
    }
}

// UPDATE SUPPLIER
if (isset($_POST['update'])) {

    $id = (int)($_POST['supplier_id'] ?? 0);
    $name = trim($_POST['supplier_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($id > 0 && $name !== "" && $phone !== "" &&
        filter_var($email, FILTER_VALIDATE_EMAIL) && $address !== "") {

        $stmt = $conn->prepare("
            UPDATE suppliers
            SET supplier_name = ?,
                phone_number = ?,
                email = ?,
                address = ?
            WHERE supplier_id = ?
        ");

        $stmt->bind_param(
            "ssssi",
            $name,
            $phone,
            $email,
            $address,
            $id
        );

        if ($stmt->execute()) {
            header("Location: suppliers.php?success=updated");
            exit();
        } else {
            $message = "Unable to update supplier.";
            $messageType = "error";
        }

        $stmt->close();

    } else {
        $message = "Please enter valid supplier details.";
        $messageType = "error";
    }
}

// DELETE SUPPLIER
if (isset($_POST['delete'])) {

    $id = (int)($_POST['supplier_id'] ?? 0);

    if ($id > 0) {

        // Check whether supplier is linked to products
        $check = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM products
            WHERE supplier_id = ?
        ");

        $check->bind_param("i", $id);
        $check->execute();

        $linked = $check->get_result()->fetch_assoc()['total'];

        $check->close();

        if ($linked > 0) {

            $message = "This supplier cannot be deleted because products are linked to it.";
            $messageType = "error";

        } else {

            $stmt = $conn->prepare("
                DELETE FROM suppliers
                WHERE supplier_id = ?
            ");

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                header("Location: suppliers.php?success=deleted");
                exit();
            } else {
                $message = "Unable to delete supplier.";
                $messageType = "error";
            }

            $stmt->close();
        }

    } else {
        $message = "Invalid supplier selected.";
        $messageType = "error";
    }
}

// SUCCESS MESSAGES
if (isset($_GET['success'])) {

    if ($_GET['success'] === 'added') {
        $message = "Supplier added successfully!";
    } elseif ($_GET['success'] === 'updated') {
        $message = "Supplier updated successfully!";
    } elseif ($_GET['success'] === 'deleted') {
        $message = "Supplier deleted successfully!";
    }
}

// LOAD SUPPLIER FOR EDITING
$edit_supplier = null;

if (isset($_GET['edit'])) {

    $id = (int)$_GET['edit'];

    if ($id > 0) {

        $stmt = $conn->prepare("
            SELECT *
            FROM suppliers
            WHERE supplier_id = ?
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $edit_supplier = $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}

// SEARCH SUPPLIERS
$search = trim($_GET['search'] ?? '');

$stmt = $conn->prepare("
    SELECT *
    FROM suppliers
    WHERE supplier_name LIKE ?
    ORDER BY supplier_id DESC
");

$term = "%{$search}%";

$stmt->bind_param("s", $term);
$stmt->execute();

$suppliers = $stmt->get_result();

$totalSuppliers = $suppliers->num_rows;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Supplier Management | SpazaSmart</title>

    <link rel="stylesheet" href="/SpazaSmart/style.css?v=8">
</head>

<body class="suppliers-page">

<div class="suppliers-container">

    <!-- PAGE HEADER -->
    <div class="suppliers-header">

        <div>
            <span class="suppliers-tag">SPAZASMART MANAGEMENT</span>

            <h1>Supplier Management</h1>

            <p>
                Manage supplier information and maintain your supplier records.
            </p>
        </div>

        <a href="dashboard.php" class="suppliers-back">
            &larr; Back to Dashboard
        </a>

    </div>

    <!-- MESSAGE -->
    <?php if ($message !== ""): ?>

        <div class="suppliers-message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <!-- SUPPLIER STATISTICS -->
    <div class="supplier-summary">

        <div class="supplier-summary-card">
            <div>
                <p>Total Suppliers</p>
                <h2><?= $totalSuppliers ?></h2>
            </div>

            <span class="supplier-summary-icon">S</span>
        </div>

    </div>

    <!-- ADD / EDIT SUPPLIER -->
    <div class="supplier-card supplier-form-card">

        <div class="supplier-section-heading">

            <div>
                <h2>
                    <?= $edit_supplier ? "Edit Supplier Details" : "Add New Supplier" ?>
                </h2>

                <p>
                    <?= $edit_supplier
                        ? "Update the selected supplier's information."
                        : "Enter the details of a new supplier below." ?>
                </p>
            </div>

        </div>

        <form method="POST" action="suppliers.php<?= $edit_supplier ? '?edit=' . (int)$edit_supplier['supplier_id'] : '' ?>">

            <?php if ($edit_supplier): ?>

                <input
                    type="hidden"
                    name="supplier_id"
                    value="<?= (int)$edit_supplier['supplier_id'] ?>"
                >

            <?php endif; ?>

            <div class="supplier-form-grid">

                <div class="supplier-form-group">
                    <label for="supplier_name">Supplier Name</label>

                    <input
                        type="text"
                        id="supplier_name"
                        name="supplier_name"
                        placeholder="Enter supplier name"
                        value="<?= htmlspecialchars($edit_supplier['supplier_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="supplier-form-group">
                    <label for="phone_number">Phone Number</label>

                    <input
                        type="tel"
                        id="phone_number"
                        name="phone_number"
                        placeholder="Enter phone number"
                        value="<?= htmlspecialchars($edit_supplier['phone_number'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="supplier-form-group">
                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter email address"
                        value="<?= htmlspecialchars($edit_supplier['email'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="supplier-form-group">
                    <label for="address">Address</label>

                    <input
                        type="text"
                        id="address"
                        name="address"
                        placeholder="Enter supplier address"
                        value="<?= htmlspecialchars($edit_supplier['address'] ?? '') ?>"
                        required
                    >
                </div>

            </div>

            <div class="supplier-form-actions">

                <?php if ($edit_supplier): ?>

                    <button type="submit" name="update" class="supplier-primary-button">
                        Update Supplier
                    </button>

                    <a href="suppliers.php" class="supplier-cancel-button">
                        Cancel
                    </a>

                <?php else: ?>

                    <button type="submit" name="add" class="supplier-primary-button">
                        Add Supplier
                    </button>

                <?php endif; ?>

            </div>

        </form>

    </div>

    <!-- SUPPLIER LIST -->
    <div class="supplier-card supplier-list-card">

        <div class="supplier-section-heading">

            <div>
                <h2>Supplier List</h2>
                <p>View and manage all registered suppliers.</p>
            </div>

        </div>

        <!-- SEARCH -->
        <form method="GET" action="suppliers.php" class="supplier-search">

            <input
                type="text"
                name="search"
                placeholder="Search suppliers by name..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit">
                Search
            </button>

            <?php if ($search !== ""): ?>
                <a href="suppliers.php" class="supplier-clear-search">
                    Clear
                </a>
            <?php endif; ?>

        </form>

        <!-- TABLE -->
        <div class="supplier-table-wrapper">

            <table class="supplier-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Supplier Name</th>
                        <th>Phone Number</th>
                        <th>Email Address</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($suppliers->num_rows > 0): ?>

                        <?php while ($row = $suppliers->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= (int)$row['supplier_id'] ?>
                                </td>

                                <td class="supplier-name-cell">
                                    <?= htmlspecialchars($row['supplier_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['phone_number']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['email']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['address']) ?>
                                </td>

                                <td class="supplier-actions">

                                    <a
                                        href="suppliers.php?edit=<?= (int)$row['supplier_id'] ?>"
                                        class="supplier-edit-button"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="suppliers.php"
                                        onsubmit="return confirm('Are you sure you want to delete this supplier?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="supplier_id"
                                            value="<?= (int)$row['supplier_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete"
                                            class="supplier-delete-button"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="supplier-empty">
                                No suppliers found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <div class="suppliers-footer">
        SpazaSmart Supplier Management
    </div>

</div>

</body>
</html>
