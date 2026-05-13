<?php 
session_start();
include 'navbar.php';
include 'DBc.php';  // Ensure this file contains the correct database connection

// Initialize messages
$error = "";
$success_message = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Retrieve and sanitize input
    $username = htmlspecialchars(trim($_POST['username']));
    $full_name = htmlspecialchars(trim($_POST['fullname']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    // Validate input fields
    if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $sql = "SELECT id FROM admin WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "An account with this email already exists.";
            } else {
                // Insert registration data (including username)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO admin (username, fullname, email, password) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param("ssss", $username, $full_name, $email, $hashed_password);
                    if ($stmt->execute()) {
                        $success_message = "Registration successful! You can now log in.";
                    } else {
                        $error = "Error during registration: " . $stmt->error;
                    }
                } else {
                    $error = "SQL Error: " . $conn->error;
                }
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Event Planning</title>
    <link rel="stylesheet" href="RegisterForm.css">
    <link rel="stylesheet" href="navbar.css">
</head>
<body>
    <div class="register-container">
        <h1>Create an Account</h1>

        <!-- Display error or success messages -->
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
            <!-- Username Field -->
            <div class="form-group">
                <label for="username">User name</label>
                <input type="text" id="username" name="username" placeholder="Your username" required>
            </div>

            <!-- Full Name Field -->
            <div class="form-group">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" placeholder="Your full name" required>
            </div>

            <!-- Email Field -->
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Your email" required>
            </div>

            <!-- Password Field -->
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="New password" required>
            </div>

            <!-- Confirm Password Field -->
            <div class="form-group">
                <label for="confirm-password">Confirm Password</label>
                <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm your password" required>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="register-btn" name="register">Register</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="Admin.php">Login here</a>
        </div>
    </div>
</body>
</html>
