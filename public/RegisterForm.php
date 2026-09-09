<?php 
session_start();
include __DIR__ . '/../config/database.php';  

$error = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = htmlspecialchars(trim($_POST['username']));
    $full_name = htmlspecialchars(trim($_POST['fullname']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $role = 'buyer';

    if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "An account with this email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param("sssss", $username, $full_name, $email, $hashed_password, $role);
                    if ($stmt->execute()) {
                        $success_message = "Registration successful! You can now sign in.";
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
include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - EVENTFLARE</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        /* Base Reset */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --text-main: #334155;
            --text-muted: #64748b;
            --bg-main: #f8fafc;
        }

        /* Split Layout Container */
        .split-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
            width: 100%;
            overflow: hidden;
            background: var(--bg-main);
        }

        /* Left Side: Branding / Image */
        .brand-side {
            position: relative;
            background-image: url('assets/images/signin_bg.jpg');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 100px 60px 60px; /* Added top padding for fixed navbar */
            color: #ffffff;
            text-align: center;
        }

        .brand-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.7) 0%, rgba(139, 92, 246, 0.4) 100%);
            z-index: 1;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            animation: springInLeft 1.2s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }

        .brand-logo {
            font-size: 52px;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 20px;
            text-shadow: 0 10px 30px rgba(0,0,0,0.5);
            background: linear-gradient(to right, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-subtitle {
            font-size: 18px;
            font-weight: 400;
            opacity: 0.9;
            max-width: 400px;
            line-height: 1.6;
        }

        /* Right Side: Form */
        .form-side {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 100px 40px 40px; /* Added top padding for fixed navbar */
            background: var(--bg-main);
            position: relative;
        }

        .auth-card {
            width: 100%;
            max-width: 450px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            opacity: 0;
            animation: springInUp 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
            animation-delay: 0.2s;
        }

        .auth-card h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 25px;
            color: var(--text-heading);
            text-align: center;
        }

        /* Spring Animations */
        @keyframes springInUp {
            0% {
                opacity: 0;
                transform: translateY(100px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes springInLeft {
            0% {
                opacity: 0;
                transform: translateX(-100px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Floating Label Inputs */
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
            font-size: 20px;
            transition: color 0.3s ease;
        }

        .form-input {
            width: 100%;
            padding: 14px 45px 14px 20px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            color: var(--text-main);
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
            outline: none;
        }

        .form-input:focus + i {
            color: var(--primary);
        }

        /* Role Selector */
        .role-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .role-option {
            flex: 1;
            position: relative;
        }

        .role-option input {
            display: none;
        }

        .role-label {
            display: block;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-muted);
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .role-option input:checked + .role-label {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(139, 92, 246, 0.05);
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            border-radius: 12px;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.3s;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            background: var(--primary-dark);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
        }

        .register-link {
            text-align: center;
            font-size: 14px;
            color: var(--text-muted);
        }

        .register-link a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .message-error {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .message-success {
            background: #dcfce7;
            color: #16a34a;
        }

        /* Responsive Mobile Stacking */
        @media (max-width: 900px) {
            .split-layout {
                grid-template-columns: 1fr;
            }
            .brand-side {
                padding: 120px 30px 40px;
                min-height: 40vh;
            }
            .brand-logo {
                font-size: 42px;
            }
            .form-side {
                align-items: flex-start;
                padding: 40px 20px;
                background: var(--bg-main);
            }
        }
    </style>
</head>
<body>
    <div class="split-layout">
        
        <!-- Left Side Branding -->
        <div class="brand-side">
            <div class="brand-overlay"></div>
            <div class="brand-content">
                <div class="brand-logo">EVENTFLARE</div>
                <p class="brand-subtitle">Join the ultimate broker platform and start planning or supplying unforgettable events.</p>
            </div>
        </div>

        <!-- Right Side Form -->
        <div class="form-side">
            <div class="auth-card">
                <form action="RegisterForm.php" method="POST">
                    <h1>Sign Up</h1>
                    
                    <?php if (!empty($error)): ?>
                        <div class="message message-error">
                            <i class='bx bx-error-circle'></i>
                            <?= htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="message message-success">
                            <i class='bx bx-check-circle'></i>
                            <?= htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <input type="hidden" name="role" value="buyer">
                    
                    <div class="input-group">
                        <input type="text" class="form-input" name="fullname" placeholder="Full Name" required>
                        <i class='bx bxs-user-detail'></i>
                    </div>

                    <div class="input-group">
                        <input type="text" class="form-input" name="username" placeholder="Username" required>
                        <i class='bx bxs-user'></i>             
                    </div>

                    <div class="input-group">
                        <input type="email" class="form-input" name="email" placeholder="Email Address" required>
                        <i class='bx bxs-envelope'></i>             
                    </div>
                    
                    <div class="input-group">
                        <input type="password" class="form-input" name="password" placeholder="Password" required>
                        <i class='bx bxs-key'></i>
                    </div>

                    <div class="input-group">
                        <input type="password" class="form-input" name="confirm-password" placeholder="Confirm Password" required>
                        <i class='bx bxs-lock-alt'></i>
                    </div>

                    <button type="submit" class="btn submit-btn" name="register">Sign Up as Client</button>
                    
                    <div class="register-link" style="margin-top: 15px;">
                        <p>Are you a business? <a href="supplier/Register.php">Partner with us</a></p>
                    </div>

                    <div class="register-link">
                        <p>Already have an account? <a href="Login.php">Sign In</a></p>
                    </div>
                </form>
            </div>
        </div>

    </div>
</body>
</html>
