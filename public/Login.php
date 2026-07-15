<?php
session_start();
include __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim(htmlspecialchars($_POST['username'])); 
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = 'Username and Password are required.';
    } else {
        global $conn; 
        if (!$conn) {
            die("Database connection failed: " . mysqli_connect_error());
        }

        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['login_user'] = $username;
                header("location: Slide.php"); 
                exit();
            } else {
                $error = 'Incorrect password!';
            }
        } else {
            $error = 'Invalid username!';
        }
        $stmt->close();
        $conn->close();
    }
}
include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Login - EventEase</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .auth-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 110px);
            padding: 20px;
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        .auth-card h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group i {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 18px;
        }

        .input-group .form-input {
            width: 100%;
            padding-right: 45px;
        }

        .remember-forget {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 25px;
        }

        .remember-forget label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .remember-forget input[type="checkbox"] {
            accent-color: var(--primary);
        }

        .submit-btn {
            width: 100%;
            margin-bottom: 20px;
        }

        .register-link {
            font-size: 14px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="glass-card auth-card">
            <form action="Login.php" method="POST">
                <h1>Client Login</h1>
                
                <!-- Display error message -->
                <?php if (!empty($error)): ?>
                    <div class="message message-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="input-group">
                    <input type="text" class="form-input" name="username" placeholder="Username" required>
                    <i class='bx bxs-user'></i>             
                </div>
                <div class="input-group">
                    <input type="password" class="form-input" name="password" placeholder="Password" required>
                    <i class='bx bxs-key'></i>
                </div>
                
                <div class="remember-forget">
                    <label><input type="checkbox"> Remember me</label>
                    <a href="#">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-primary submit-btn">Login</button>
                
                <div class="register-link">
                    <p>Don't have an account? <a href="RegisterForm.php">Register</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
