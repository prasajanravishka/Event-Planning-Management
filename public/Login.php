<?php
session_start();
include __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
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
                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['user_role'] = $row['role'] ?? 'buyer';
                
                if ($username === 'admin' || ($_SESSION['user_role'] ?? '') === 'admin') {
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['admin_last_activity'] = time();
                    header("location: admin/Dashboard.php");
                } elseif ($_SESSION['user_role'] === 'supplier') {
                    header("location: supplier/Dashboard.php");
                } else {
                    $redirect = trim($_REQUEST['redirect'] ?? '');
                    // Safe relative redirect check (prevent open redirects)
                    if (!empty($redirect) && !preg_match('#^(https?:)?//#i', $redirect) && !preg_match('#^/\\\#', $redirect)) {
                        header("location: " . $redirect);
                    } else {
                        header("location: Slide.php"); 
                    }
                }
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
    <title>Sign In - EVENTFLARE</title>
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
            max-width: 420px;
            padding: 50px 40px;
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
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 30px;
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
            margin-bottom: 25px;
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
            padding: 16px 45px 16px 20px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
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

        /* Utilities */
        .remember-forget {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        .remember-forget label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
        }

        .remember-forget input[type="checkbox"] {
            accent-color: var(--primary);
            width: 16px;
            height: 16px;
        }

        .remember-forget a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .remember-forget a:hover {
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 25px;
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

        .message-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
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
                <p class="brand-subtitle">The ultimate broker platform linking premium clients with elite event suppliers.</p>
            </div>
        </div>

        <!-- Right Side Form -->
        <div class="form-side">
            <div class="auth-card">
                <form action="Login.php" method="POST">
                    <h1>Sign In</h1>
                    
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_REQUEST['redirect'] ?? '') ?>">
                    
                    <?php if (!empty($_REQUEST['redirect'])): ?>
                        <div style="background:rgba(139, 92, 246, 0.1); border:1px solid rgba(139, 92, 246, 0.25); border-radius:10px; padding:12px 14px; margin-bottom:20px; font-size:13px; color:var(--primary-dark); display:flex; align-items:center; gap:10px;">
                            <i class='bx bx-user-check' style="font-size:20px; color:var(--primary);"></i>
                            <span>Please sign in to your buyer account to complete and manage your event booking.</span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Display error message -->
                    <?php if (!empty($error)): ?>
                        <div class="message message-error">
                            <i class="bx bx-error-circle"></i>
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

                    <button type="submit" class="btn submit-btn">Sign In</button>
                    
                    <div class="register-link">
                        <p>Don't have an account? <a href="RegisterForm.php">Sign Up</a></p>
                    </div>
                </form>
            </div>
        </div>

    </div>
</body>
</html>
