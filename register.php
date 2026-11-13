
<?php
include 'connection.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Check if email already exists
    $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {

        $message = "Email already registered.";

    } else {

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Assign default role
        $role = "staff";

        // Insert user
        $stmt = $conn->prepare(
            "INSERT INTO users (full_name, email, password, role)
            VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssss",
            $full_name,
            $email,
            $hashed_password,
            $role
        );

        if ($stmt->execute()) {
            $message = "Registration successful! You can now log in.";
        } else {
            $message = "Registration failed. Please try again.";
        }

        $stmt->close();
    }

    $check->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpazaSmart - Register</title>
</head>
<body>

    <h2>Create Your SpazaSmart Account</h2>

    <p><?php echo htmlspecialchars($message); ?></p>

    <form method="POST" action="">

        <label>Full Name:</label><br>
        <input type="text" name="full_name" required>
        <br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required>
        <br><br>

        <label>Password:</label><br>
        <input type="password" name="password"
               minlength="8" required>
        <br><br>

        <button type="submit">Register</button>

    </form>

    <p>Already have an account?
        <a href="login.php">Login here</a>
    </p>

</body>
</html>
