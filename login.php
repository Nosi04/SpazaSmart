
<?php
session_start();
require_once 'connection.php';

// If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
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

        // Check if email exists
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

                // Verify password
                if (password_verify($password, $user['password'])) {

                    // Create a new session ID
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];

                    // Redirect to dashboard
                    $stmt->close();
                    header("Location: dashboard.php");
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

            <div class="login-brand-name">
                SPAZASMART
            </div>

            <div class="login-brand-caption">
                SHOP MANAGEMENT SYSTEM
            </div>
        </div>

        <div class="login-heading">
            Welcome Back
        </div>

        <p class="login-description">
            Sign in to manage your shop and keep your business running smoothly.
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
                    autocomplete="email"
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
                    autocomplete="current-password"
                >

            </div>

            <button type="submit" class="login-submit-button">
                Sign In
            </button>

        </form>

        <div class="login-register-link">
            Don't have an account?
            <a href="register.php">Create an account</a>
        </div>

        <div class="login-footer">
            SpazaSmart &copy; <?= date("Y") ?>
        </div>

    </div>

</body>
</html>
