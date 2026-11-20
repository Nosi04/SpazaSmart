
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

// Only owners can manage categories
if (($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: staff_dashboard.php");
    exit();
}

$message = "";

// =====================================
// ADD CATEGORY
// =====================================

if (isset($_POST['add'])) {

    $name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === "" || $description === "") {
        $message = "Please complete all fields.";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO categories (category_name, description)
            VALUES (?, ?)
        ");

        $stmt->bind_param("ss", $name, $description);

        if ($stmt->execute()) {
            header("Location: categories.php?success=added");
            exit();
        } else {
            $message = "Unable to add category. Check whether the category already exists.";
        }

        $stmt->close();
    }
}

// =====================================
// UPDATE CATEGORY
// =====================================

if (isset($_POST['update'])) {

    $id = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($id <= 0 || $name === "" || $description === "") {
        $message = "Please enter valid category details.";
    } else {

        $stmt = $conn->prepare("
            UPDATE categories
            SET category_name = ?, description = ?
            WHERE category_id = ?
        ");

        $stmt->bind_param("ssi", $name, $description, $id);

        if ($stmt->execute()) {
            header("Location: categories.php?success=updated");
            exit();
        } else {
            $message = "Unable to update category.";
        }

        $stmt->close();
    }
}

// =====================================
// DELETE CATEGORY
// =====================================

if (isset($_POST['delete'])) {

    $id = (int)($_POST['category_id'] ?? 0);

    if ($id <= 0) {
        $message = "Invalid category selected.";
    } else {

        $check = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM products
            WHERE category_id = ?
        ");

        $check->bind_param("i", $id);
        $check->execute();

        $linked = $check->get_result()->fetch_assoc()['total'];
        $check->close();

        if ($linked > 0) {
            $message = "This category cannot be deleted because it is linked to existing products.";
        } else {

            $stmt = $conn->prepare("
                DELETE FROM categories
                WHERE category_id = ?
            ");

            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                header("Location: categories.php?success=deleted");
                exit();
            } else {
                $message = "Category could not be deleted.";
            }

            $stmt->close();
        }
    }
}

// =====================================
// SUCCESS MESSAGES
// =====================================

if (isset($_GET['success'])) {

    $messages = [
        'added' => 'Category added successfully!',
        'updated' => 'Category updated successfully!',
        'deleted' => 'Category deleted successfully!'
    ];

    $message = $messages[$_GET['success']] ?? '';
}

// =====================================
// GET CATEGORY FOR EDITING
// =====================================

$editCategory = null;

if (isset($_GET['edit'])) {

    $id = (int)$_GET['edit'];

    $stmt = $conn->prepare("
        SELECT *
        FROM categories
        WHERE category_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $editCategory = $stmt->get_result()->fetch_assoc();

    $stmt->close();
}

// =====================================
// SEARCH CATEGORIES
// =====================================

$search = trim($_GET['search'] ?? '');
$term = "%" . $search . "%";

$stmt = $conn->prepare("
    SELECT *
    FROM categories
    WHERE category_name LIKE ?
    ORDER BY category_id DESC
");

$stmt->bind_param("s", $term);
$stmt->execute();

$categories = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Category Management | SpazaSmart</title>

    <link rel="stylesheet" href="/SpazaSmart/style.css?v=5">
</head>

<body class="categories-page">

<div class="category-page">

    <!-- PAGE HEADER -->
    <div class="page-header">

        <div>
            <span class="category-tag">SPAZASMART MANAGEMENT</span>

            <h1>Category Management</h1>

            <p>Organise your shop products into categories.</p>
        </div>

        <a href="dashboard.php" class="back-button">
            ← Back to Dashboard
        </a>

    </div>

    <!-- SUCCESS AND ERROR MESSAGES -->
    <?php if ($message !== ""): ?>

        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>

    <?php endif; ?>

    <!-- ADD / EDIT CATEGORY -->
    <div class="form-box">

        <h2>
            <?= $editCategory ? "Edit Category" : "Add New Category" ?>
        </h2>

        <form method="POST" action="categories.php">

            <?php if ($editCategory): ?>

                <input
                    type="hidden"
                    name="category_id"
                    value="<?= (int)$editCategory['category_id'] ?>"
                >

            <?php endif; ?>

            <div class="form-group">

                <label>Category Name</label>

                <input
                    type="text"
                    name="category_name"
                    placeholder="Enter category name"
                    value="<?= htmlspecialchars($editCategory['category_name'] ?? '') ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Description</label>

                <textarea
                    name="description"
                    placeholder="Enter category description"
                    required
                ><?= htmlspecialchars($editCategory['description'] ?? '') ?></textarea>

            </div>

            <button
                type="submit"
                name="<?= $editCategory ? 'update' : 'add' ?>"
            >
                <?= $editCategory ? 'Update Category' : 'Add Category' ?>
            </button>

            <?php if ($editCategory): ?>

                <a href="categories.php">Cancel Editing</a>

            <?php endif; ?>

        </form>

    </div>

    <!-- CATEGORY LIST -->
    <div class="table-box">

        <h2>Category List</h2>

        <!-- SEARCH -->
        <form method="GET" action="categories.php" class="search">

            <input
                type="text"
                name="search"
                placeholder="Search category..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit">Search</button>

            <a href="categories.php">Clear Search</a>

        </form>

        <!-- CATEGORY TABLE -->
        <table>

            <thead>

                <tr>
                    <th>Category ID</th>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>

            </thead>

            <tbody>

                <?php if ($categories->num_rows > 0): ?>

                    <?php while ($row = $categories->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= (int)$row['category_id'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['category_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['description']) ?>
                            </td>

                            <td>

                                <a href="categories.php?edit=<?= (int)$row['category_id'] ?>">
                                    Edit
                                </a>

                                <form method="POST" action="categories.php">

                                    <input
                                        type="hidden"
                                        name="category_id"
                                        value="<?= (int)$row['category_id'] ?>"
                                    >

                                    <button type="submit" name="delete">
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">No categories found.</td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
