<?php
session_start();
include 'connection.php';

$message ="";

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $email =trim($_POST['email']);
    $password =trim($_POST['password']);

    //check if email exists
    $stmt = $conn->prepare(
        "SELECT user_id, full_name, email, password, role FROM users WHERE EMAIL= ?"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1){
        $user = $result->fetch_assoc();

        //verify password
        if(password_verify($password, $user['password'])){
            //create session
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            //redirect to dashboard
            header("Location: dashboard.php");
            exist();

         }else{
            $message = "Incorrect email or password";
         }
    }else{
        $message = "Incorrect email or password";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpazaSmart - Login</title>
</head>
<body>
    <h2>Login to SpazaSmart</h2>
     <p><?php echo htmlspecialchars($message); ?></p>

     <form method = "POST" action="">

     <label>Email:</label><br>
     <input type= "email" name="email" required>
     <br><br>

     <label>Password:</label><br>
     <input type="password" name="password" required>
     <br><br>

     <button type="submit">Login</button>

</form>

<p>Don't have an account?
    <a href="register.php">Register here</a>
</p>
</body>
</html>