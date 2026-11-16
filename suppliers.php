
<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";

// Add supplier
if (isset($_POST['add'])) {
    $name = trim($_POST['supplier_name']);
    $phone = trim($_POST['phone_number']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    if ($name && $phone && filter_var($email, FILTER_VALIDATE_EMAIL) && $address) {
        $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, phone_number, email, address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $phone, $email, $address);
        $stmt->execute();
        $message = "Supplier added successfully!";
    } else {
        $message = "Please enter valid supplier details.";
    }
}

// Delete supplier
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $message = "Supplier deleted successfully!";
    } else {
        $message = "Unable to delete supplier.";
    }
}

// Search suppliers
$search = $_GET['search'] ?? '';
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_name LIKE ? ORDER BY supplier_id DESC");
$term = "%$search%";
$stmt->bind_param("s", $term);
$stmt->execute();
$suppliers = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="style.css">
<title>Supplier Management</title>
<style>
body{font-family:Arial;background:#f4f6f9;padding:25px}
.card{background:white;padding:20px;margin-bottom:20px;border-radius:8px}
input,button{padding:10px;margin:7px 0}
input{width:100%;box-sizing:border-box}
button{background:#198754;color:white;border:0;cursor:pointer}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid #ddd;text-align:left}
th{background:#173b32;color:white}
a{color:red}
</style>
</head>
<body>

<h1>Supplier Management</h1>
<p><?= htmlspecialchars($message) ?></p>

<div class="card">
<h2>Add Supplier</h2>
<form method="POST">
<input type="text" name="supplier_name" placeholder="Supplier Name" required>
<input type="tel" name="phone_number" placeholder="Phone Number" required>
<input type="email" name="email" placeholder="Email Address" required>
<input type="text" name="address" placeholder="Address" required>
<button type="submit" name="add">Add Supplier</button>
</form>
</div>

<div class="card">
<h2>Supplier List</h2>

<form method="GET">
<input type="text" name="search" placeholder="Search supplier..." value="<?= htmlspecialchars($search) ?>">
<button type="submit">Search</button>
</form>

<table>
<tr>
<th>ID</th><th>Name</th><th>Phone</th>
<th>Email</th><th>Address</th><th>Action</th>
</tr>

<?php while($row = $suppliers->fetch_assoc()): ?>
<tr>
<td><?= $row['supplier_id'] ?></td>
<td><?= htmlspecialchars($row['supplier_name']) ?></td>
<td><?= htmlspecialchars($row['phone_number']) ?></td>
<td><?= htmlspecialchars($row['email']) ?></td>
<td><?= htmlspecialchars($row['address']) ?></td>
<td>
<a href="suppliers.php?delete=<?= $row['supplier_id'] ?>"
onclick="return confirm('Delete this supplier?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

</body>
</html>
