<?php
session_start();
include 'navbar.php';
include("DBc.php");  // Ensure this file contains the correct database connection

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve and sanitize user input
    $username = trim(htmlspecialchars($_POST['username'])); 
    $password = trim($_POST['password']);

    // Check if fields are empty
    if (empty($username) || empty($password)) {
        $error = 'User ID and Password are required.';
    } else {
        // Use the database connection from DBc.php
        global $conn; 

        // Check connection
        if (!$conn) {
            die("Database connection failed: " . mysqli_connect_error());
        }

        // Prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            // Fetch the user data
            $row = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $row['password'])) {
                // Password is correct, start session
                $_SESSION['login_user'] = $username;
                session_regenerate_id(true); // Regenerate session ID for security
                header("location: Summary.php"); // Redirect to dashboard or next page
                exit();
            } else {
                $error = 'Incorrect password!';
            }
        } else {
            $error = 'Invalid username!';
        }

        // Close statement and connection
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="Login.css">
    <link rel="stylesheet" href="navbar.css">
</head>
<body>

    <div class="wrapper">
        <form action="Admin.php" method="POST">
            <h1>Admin Login</h1>
            
            <!-- Display error message -->
            <?php if (!empty($error)): ?>
                <div class="error-message" style="color: red; margin-bottom: 10px;">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="Input-box">
                <input type="text" name="username" placeholder="User name" required>
                <i class='bx bxs-user'></i>             
            </div>
            <div class="Input-box">
                <input type="password" name="password" placeholder="Password" required>
                <i class='bx bxs-key'></i>
            </div>
            <div class="Remember-Forget">
                <label><input type="checkbox"> Remember me</label>
                <a href="#">Forgot Password?</a>
            </div>

            <button type="submit" class="btn">Login</button>
            
            <div class="Register-link">
                <p>Don't have an account? <a href="AdminRegistation.php">Register</a></p>
            </div>
        </form>
    </div>

</body>
</html>