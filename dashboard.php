<?php
session_start();

//check if user is logged in
if (!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpazaSmart - Dashboard</title>
</head>
<body>
    <h1>Welcome to SpazaSmart!</h1>

    <h2>Hello, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
</h2>

<p>You have successfully logged in.</p>
<a href="logout.php">Logout</a>
</body>
</html>