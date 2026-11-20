
<?php
session_start();
require_once 'connection.php';

// If already logged in, redirect according to role
if (isset($_SESSION['user_id'])) {

    if (($_SESSION['role'] ?? '') === 'owner') {
        header("Location: dashboard.php");
    } else {
        header("Location: staff_dashboard.php");
    }

    exit();
}

$message = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {

        $message = "Please enter your email and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role
             FROM users
             WHERE email = ?"
        );

        if ($stmt) {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {

                    // Create a secure new session ID
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];

                    $stmt->close();

                    // Redirect according to the user's role
                    if ($user['role'] === 'owner') {

                        header("Location: owner_dashboard.php");

                    } else {

                        header("Location: staff_dashboard.php");
                    }

                    exit();

                } else {

                    $message = "Incorrect email or password.";
                }

            } else {

                $message = "Incorrect email or password.";
            }

            $stmt->close();

        } else {

            $message = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SpazaSmart - Login</title>

    <link rel="stylesheet" href="/SpazaSmart/style.css?v=12">
</head>

<body class="login-page">

    <div class="login-container">

        <div class="login-brand">

            <div class="login-brand-icon">S</div>

            <div class="login-brand-name">SPAZASMART</div>

            <div class="login-brand-caption">
                SHOP MANAGEMENT SYSTEM
            </div>

        </div>

        <h1 class="login-heading">Welcome Back</h1>

        <p class="login-description">
            Sign in to manage your shop's products, sales and stock.
        </p>

        <?php if (!empty($message)): ?>

            <div class="login-message error">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form">

            <div class="login-form-group">

                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    value="<?= htmlspecialchars($email) ?>"
                    required
                >

            </div>

            <div class="login-form-group">

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>

            <button type="submit" class="login-submit-button">
                Sign In
            </button>

        </form>

        <p class="login-register-link">
            Don't have an account?
            <a href="register.php">Register here</a>
        </p>

        <div class="login-footer">
            SpazaSmart © 2026. All rights reserved.
        </div>

    </div>

</body>
</html>