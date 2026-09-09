<?php
session_start();
include __DIR__ . '/../../config/database.php';

// If already logged in as admin, redirect to Dashboard
if (isset($_SESSION['login_user']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header("Location: Dashboard.php");
    exit();
}

$error = $_GET['error'] ?? '';
$success = $_GET['msg'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? ''); 
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        if (!$conn) {
            die("Database connection failed: " . mysqli_connect_error());
        }

        $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['login_user'] = $username;
                $_SESSION['user_type'] = 'admin';
                $_SESSION['admin_last_activity'] = time();
                session_regenerate_id(true); 
                header("Location: Dashboard.php"); 
                exit();
            } else {
                $error = 'Incorrect password! Please try again.';
            }
        } else {
            $error = 'Invalid administrative username!';
        }
        $stmt->close();
    }
}
include __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Console Login - EVENTFLARE</title>
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
            max-width: 440px;
            text-align: center;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: var(--shadow-premium);
        }

        .auth-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(139, 92, 246, 0.12);
            color: var(--primary);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .auth-card h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
            color: var(--text-heading);
        }

        .auth-card p.subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 25px;
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
            font-size: 20px;
        }

        .input-group .form-input {
            width: 100%;
            padding: 14px 45px 14px 16px;
            border-radius: 12px;
            border: 1.5px solid var(--card-border);
            background: #f8fafc;
            font-size: 14px;
            transition: var(--transition-smooth);
            outline: none;
        }

        .input-group .form-input:focus {
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12);
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 12px;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-badge">
                <i class="fas fa-shield-alt"></i> Admin Console
            </div>
            
            <h1>Organizer Login</h1>
            <p class="subtitle">Sign in with your administrator credentials to access the management portal.</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert-box alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert-box alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form action="Admin.php" method="POST">
                <div class="input-group">
                    <input type="text" class="form-input" name="username" placeholder="Organizer Username" required autofocus>
                    <i class='bx bxs-user-badge'></i>             
                </div>
                <div class="input-group">
                    <input type="password" class="form-input" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>

                <button type="submit" class="btn btn-primary submit-btn">
                    <i class="fas fa-sign-in-alt"></i> Access Admin Console
                </button>
            </form>

            <div style="font-size: 13px; color: var(--text-muted); border-top: 1px solid rgba(0,0,0,0.06); padding-top: 20px;">
                <p><i class="fas fa-lock fa-xs"></i> Restricted Area &bull; Authorized personnel only.</p>
                <p style="margin-top: 6px;"><a href="../Home.php" style="color: var(--primary); text-decoration: none;">&larr; Return to EventFlare Homepage</a></p>
            </div>
        </div>
    </div>
</body>
</html>
