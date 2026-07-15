<?php 
session_start();
include __DIR__ . '/../../config/database.php';  

$error = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = htmlspecialchars(trim($_POST['username']));
    $full_name = htmlspecialchars(trim($_POST['fullname']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $sql = "SELECT id FROM admin WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "An account with this email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO admin (username, fullname, email, password) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param("ssss", $username, $full_name, $email, $hashed_password);
                    if ($stmt->execute()) {
                        $success_message = "Organizer registration successful! You can now log in.";
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
include __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Register - EventEase</title>
    <style>
        .auth-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 110px);
            padding: 20px 0;
        }

        .auth-card {
            width: 100%;
            max-width: 460px;
        }

        .auth-card h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 25px;
            text-align: center;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .submit-btn {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .login-link {
            text-align: center;
            font-size: 14px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="glass-card auth-card">
            <form action="AdminRegistation.php" method="POST">
                <h1>Organizer Register</h1>

                <!-- Display error or success messages -->
                <?php if (!empty($error)): ?>
                    <div class="message message-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="message message-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Username Field -->
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" class="form-input" name="username" placeholder="Choose a username" required>
                </div>

                <!-- Full Name Field -->
                <div class="form-group">
                    <label class="form-label" for="fullname">Full Name</label>
                    <input type="text" id="fullname" class="form-input" name="fullname" placeholder="Enter your full name" required>
                </div>

                <!-- Email Field -->
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" class="form-input" name="email" placeholder="name@example.com" required>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" class="form-input" name="password" placeholder="Create a password" required>
                </div>

                <!-- Confirm Password Field -->
                <div class="form-group">
                    <label class="form-label" for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" class="form-input" name="confirm-password" placeholder="Confirm your password" required>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary submit-btn" name="register">Register as Organizer</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="Admin.php">Login here</a>
            </div>
        </div>
    </div>
</body>
</html>
