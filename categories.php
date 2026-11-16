
<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";

// Add category
if (isset($_POST['add'])) {
    $name = trim($_POST['category_name']);
    $description = trim($_POST['description']);

    if ($name && $description) {
        $stmt = $conn->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);

        $message = $stmt->execute()
            ? "Category added successfully!"
            : "Unable to add category. It may already exist.";
    } else {
        $message = "Please complete all fields.";
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id=?");
    $stmt->bind_param("i", $id);

    $message = $stmt->execute()
        ? "Category deleted successfully!"
        : "Unable to delete category. It may be linked to products.";
}

// Search categories
$search = $_GET['search'] ?? '';
$stmt = $conn->prepare("SELECT * FROM categories WHERE category_name LIKE ? ORDER BY category_id DESC");
$term = "%$search%";
$stmt->bind_param("s", $term);
$stmt->execute();
$categories = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="style.css">
<title>Category Management</title>
<style>
body{font-family:Arial;background:#f4f6f9;padding:25px}
.card{background:white;padding:20px;margin-bottom:20px;border-radius:8px}
input,textarea,button{padding:10px;margin:7px 0;width:100%;box-sizing:border-box}
button{background:#198754;color:white;border:0;cursor:pointer}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid #ddd;text-align:left}
th{background:#173b32;color:white}
a{color:red}
</style>
</head>
<body>

<h1>Category Management</h1>
<p><?= htmlspecialchars($message) ?></p>

<div class="card">
<h2>Add Category</h2>

<form method="POST">
<input type="text" name="category_name" placeholder="Category Name" required>
<textarea name="description" placeholder="Category Description" required></textarea>
<button type="submit" name="add">Add Category</button>
</form>
</div>

<div class="card">
<h2>Category List</h2>

<form method="GET">
<input type="text" name="search" placeholder="Search category..." value="<?= htmlspecialchars($search) ?>">
<button type="submit">Search</button>
</form>

<table>
<tr>
<th>ID</th><th>Category</th><th>Description</th><th>Action</th>
</tr>

<?php while($row = $categories->fetch_assoc()): ?>
<tr>
<td><?= $row['category_id'] ?></td>
<td><?= htmlspecialchars($row['category_name']) ?></td>
<td><?= htmlspecialchars($row['description']) ?></td>
<td>
<a href="categories.php?delete=<?= $row['category_id'] ?>"
onclick="return confirm('Delete this category?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>

</table>
</div>

</body>
</html>
