<?php
session_start();
include __DIR__ . '/../../config/database.php';

// If logged in as admin, redirect to Admin Console (Admins manage suppliers via Admin Panel)
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header("Location: ../admin/Suppliers.php");
    exit();
}

// Ensure user is logged in as a supplier
if (!isset($_SESSION['login_user']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'supplier') {
    header("Location: ../Login.php");
    exit();
}

$listing_count = 0;
$business_name = 'Supplier Dashboard';
$supp_stmt = $conn->prepare("SELECT s.id, s.business_name FROM users u JOIN suppliers s ON u.id = s.user_id WHERE u.username = ?");
$supp_stmt->bind_param("s", $_SESSION['login_user']);
$supp_stmt->execute();
$supp_res = $supp_stmt->get_result();
if ($supp_res->num_rows > 0) {
    $row = $supp_res->fetch_assoc();
    $supp_id = (int)$row['id'];
    $business_name = $row['business_name'];
    $cnt_res = $conn->query("SELECT COUNT(*) as c FROM supplier_listings WHERE supplier_id = $supp_id");
    if ($cnt_res) {
        $listing_count = (int)$cnt_res->fetch_assoc()['c'];
    }
}
$supp_stmt->close();
$conn->close();

include __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($business_name); ?> - Supplier Dashboard</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .supplier-wrapper {
            max-width: 1200px;
            margin: 50px auto 100px;
            padding: 0 20px;
        }

        .supplier-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .supplier-header h1 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--text-heading) 30%, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .supplier-header p {
            color: var(--text-muted);
            font-size: 16px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .dash-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .dash-card:hover {
            transform: translateY(-8px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2), 0 0 20px var(--card-glow);
        }

        .dash-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .dash-card h3 {
            font-size: 22px;
            color: var(--text-heading);
            margin-bottom: 10px;
        }

        .dash-card p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .dash-btn {
            margin-top: auto;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="supplier-wrapper">
        <div class="supplier-header">
            <h1><?= htmlspecialchars($business_name); ?></h1>
            <p>Welcome back, <strong><?= htmlspecialchars($_SESSION['login_user']); ?></strong>! Manage your venues, services, and packages.</p>
        </div>

        <div class="dashboard-grid">
            
            <div class="dash-card">
                <i class="fas fa-id-badge dash-icon"></i>
                <h3>Business Profile</h3>
                <p>Complete or update your business details, category, phone, location, and services offered.</p>
                <a href="Profile.php" class="btn btn-primary dash-btn">Edit Profile</a>
            </div>

            <div class="dash-card">
                <i class="fas fa-store dash-icon"></i>
                <h3>My Listings</h3>
                <p>View, edit, or add new packages and services. Currently <strong><?= $listing_count; ?> package<?= $listing_count === 1 ? '' : 's'; ?></strong> listed.</p>
                <a href="Listings.php" class="btn btn-primary dash-btn">Manage Listings</a>
            </div>

            <div class="dash-card">
                <i class="fas fa-calendar-check dash-icon"></i>
                <h3>Active Bookings</h3>
                <p>Review event bookings that clients have requested for your services.</p>
                <button type="button" class="btn btn-primary dash-btn" disabled title="Coming Soon" style="opacity: 0.6; cursor: not-allowed;">View Bookings</button>
            </div>

            <div class="dash-card">
                <i class="fas fa-chart-line dash-icon"></i>
                <h3>Earnings & Stats</h3>
                <p>Track your revenue, view popular services, and analyze your performance on the EVENTFLARE platform.</p>
                <button type="button" class="btn btn-secondary dash-btn" disabled title="Coming Soon" style="opacity: 0.6; cursor: not-allowed;">View Analytics</button>
            </div>

        </div>
    </div>
</body>
</html>
