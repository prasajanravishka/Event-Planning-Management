<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    header("Location: admin/Admin.php");
    exit();
}
include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Console - EventEase</title>
    <style>
        .summary-wrapper {
            max-width: 1000px;
            margin: 40px auto 80px;
            padding: 0 20px;
        }

        .summary-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .summary-header h1 {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .summary-header p {
            color: var(--text-muted);
            font-size: 16px;
        }

        .console-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .console-card {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 30px;
        }

        .console-card:hover {
            transform: translateY(-5px);
        }

        .console-icon {
            font-size: 40px;
            color: var(--primary);
            background: rgba(139, 92, 246, 0.1);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(139, 92, 246, 0.2);
            margin-bottom: 20px;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.1);
        }

        .console-card h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .console-card p {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .console-card .btn {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="summary-wrapper">
        <div class="summary-header">
            <h1>Organizer Console</h1>
            <p>Access management controls, check database records, and export reports.</p>
        </div>

        <div class="console-grid">
            <!-- Users Panel -->
            <div class="glass-card console-card">
                <div class="console-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h2>User Registrations</h2>
                <p>Monitor active accounts, trace user profiles, and manage system database clients.</p>
                <a href="admin/Userlist.php" class="btn btn-primary">Manage Users</a>
            </div>

            <!-- Bookings Panel -->
            <div class="glass-card console-card">
                <div class="console-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h2>Event Bookings</h2>
                <p>Access client requests, confirm schedules, view specific venues, and export PDF summaries.</p>
                <a href="admin/Bookinglist.php" class="btn btn-primary">Manage Bookings</a>
            </div>

            <!-- Budgets Panel -->
            <div class="glass-card console-card">
                <div class="console-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h2>Budget Summaries</h2>
                <p>Oversee computed calculations, monitor food expenses, and inspect financial allocations.</p>
                <a href="events/FoodBudsummary.php" class="btn btn-primary">View Budgets</a>
            </div>
        </div>
    </div>
</body>
</html>