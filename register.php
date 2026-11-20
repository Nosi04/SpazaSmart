
<?php
session_start();
require_once 'connection.php';

// Redirect logged-in staff to their dashboard.
// Allow logged-in owners to access registration
// so they can create additional accounts.
if (isset($_SESSION['user_id']) &&
    ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: staff_dashboard.php");
    exit();
}

$message = "";
$message_type = "";

$full_name = "";
$email = "";
$role = "staff";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'staff';

    // Validate full name
    if ($full_name === '') {
        $message = "Please enter your full name.";
        $message_type = "error";

    } elseif (strlen($full_name) > 255) {
        $message = "Full name is too long.";
        $message_type = "error";

    // Validate email
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";

    // Validate selected role
    } elseif (!in_array($role, ['owner', 'staff'], true)) {
        $message = "Please select a valid account role.";
        $message_type = "error";

    // Only existing owners can create owner accounts
    } elseif (
        $role === 'owner' &&
        ($_SESSION['role'] ?? '') !== 'owner'
    ) {
        $message = "Only an existing owner can create another owner account.";
        $message_type = "error";

    // Validate password
    } elseif (strlen($password) < 8) {
        $message = "Password must contain at least 8 characters.";
        $message_type = "error";

    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";

    } else {

        // Check whether email already exists
        $check = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE email = ?
        ");

        if (!$check) {
            $message = "Something went wrong. Please try again.";
            $message_type = "error";

        } else {

            $check->bind_param("s", $email);
            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $message = "Email already registered.";
                $message_type = "error";

            } else {

                // Securely hash password
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert new user with selected role
                $stmt = $conn->prepare("
                    INSERT INTO users
                    (full_name, email, password, role)
                    VALUES (?, ?, ?, ?)
                ");

                if (!$stmt) {
                    $message = "Registration failed. Please try again.";
                    $message_type = "error";

                } else {

                    $stmt->bind_param(
                        "ssss",
                        $full_name,
                        $email,
                        $hashed_password,
                        $role
                    );

                    if ($stmt->execute()) {

                        $message = "Registration successful! You can now log in.";
                        $message_type = "success";

                        $full_name = "";
                        $email = "";
                        $role = "staff";

                    } else {

                        $message = "Registration failed. Please try again.";
                        $message_type = "error";
                    }

                    $stmt->close();
                }
            }

            $check->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | SpazaSmart</title>

    <link rel="stylesheet" href="/SpazaSmart/style.css?v=13">
</head>

<body class="register-page">

    <div class="register-container">

        <div class="register-brand">
            <div class="register-logo">S</div>
            <span>SPAZASMART</span>
        </div>

        <div class="register-heading">
            <span class="register-tag">GET STARTED</span>

            <h1>Create Your Account</h1>

            <p>
                Register to manage your shop's products, sales and stock.
            </p>
        </div>

        <?php if (!empty($message)): ?>

            <div class="message <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="register.php" class="register-form">

            <div class="register-field">
                <label for="full_name">Full Name</label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars($full_name) ?>"
                    maxlength="255"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="register-field">
                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    value="<?= htmlspecialchars($email) ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <!-- ROLE SELECTION -->
            <div class="register-field">
                <label for="role">Account Role</label>

                <select
                    id="role"
                    name="role"
                    required
                >
                    <option value="staff"
                        <?= $role === 'staff' ? 'selected' : '' ?>>
                        Staff
                    </option>

                    <option value="owner"
                        <?= $role === 'owner' ? 'selected' : '' ?>>
                        Owner
                    </option>
                </select>

                <small>
                    Owner accounts can only be created by an existing owner.
                </small>
            </div>

            <div class="register-field">
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create a password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <small>Password must contain at least 8 characters.</small>
            </div>

            <div class="register-field">
                <label for="confirm_password">Confirm Password</label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Enter your password again"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="register-button">
                Create Account
            </button>

        </form>

        <div class="register-login">
            Already have an account?
            <a href="login.php">Sign in here</a>
        </div>

        <div class="register-footer">
            SpazaSmart &copy; <?= date('Y') ?>. All rights reserved.
        </div>

    </div>

</body>
</html>
