<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    header("Location: Login.php");
    exit();
}
include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EventEase</title>
    <style>
        .dashboard-container {
            max-width: 1000px;
            margin: 40px auto 80px;
            padding: 0 20px;
        }

        .welcome-header {
            margin-bottom: 40px;
            text-align: center;
        }

        .welcome-header h1 {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .welcome-header p {
            color: var(--text-muted);
            font-size: 16px;
        }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 40px;
        }

        #slider {
            width: 100%;
            overflow: hidden;
            position: relative;
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-premium);
            height: 380px;
        }

        .slider-wrapper {
            display: flex;
            width: 100%;
            animation: slider 15s infinite ease-in-out;
        }

        .slider-wrapper img {
            width: 100%;
            flex-shrink: 0;
            height: 380px;
            object-fit: cover;
        }

        /* 5-image Keyframes */
        @keyframes slider {
            0%, 16% { transform: translateX(0%); }
            20%, 36% { transform: translateX(-100%); }
            40%, 56% { transform: translateX(-200%); }
            60%, 76% { transform: translateX(-300%); }
            80%, 96% { transform: translateX(-400%); }
            100% { transform: translateX(0%); }
        }

        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 20px;
            justify-content: center;
        }

        .action-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: var(--transition-smooth);
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateX(8px);
            border-color: rgba(139, 92, 246, 0.4);
            background: rgba(22, 18, 45, 0.8);
            box-shadow: 0 10px 25px rgba(0,0,0,0.4), 0 0 15px var(--card-glow);
        }

        .action-icon {
            font-size: 32px;
            color: var(--primary);
            background: rgba(139, 92, 246, 0.1);
            width: 64px;
            height: 64px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .action-info h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-white);
        }

        .action-info p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        @media (max-width: 800px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            #slider {
                height: 280px;
            }
            .slider-wrapper img {
                height: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="welcome-header">
            <h1>Welcome Back, <?php echo htmlspecialchars($_SESSION['login_user']); ?>!</h1>
            <p>Your event management dashboard is active. Explore venues, organize costs, or confirm bookings.</p>
        </div>

        <div class="dashboard-layout">
            <!-- Left: Featured slider -->
            <div id="slider">
                <div class="slider-wrapper">
                    <img src="assets/images/wedding-couple-best-friends-are-drinking-champagne-celebrating-park-wedding-day.jpg" alt="Slide 1">
                    <img src="assets/images/photo-through-tree-branches-with-leaves-evening-time-friends-have-dinner-gorgeous-outdoor-place.jpg" alt="Slide 2">
                    <img src="assets/images/look-from-white-chairs-arranged-wedding-ceremony.jpg" alt="Slide 3">
                    <img src="assets/images/happy-men-women-throwing-confetti.jpg" alt="Slide 4">
                    <img src="assets/images/AdobeStock_165794769_Preview.jpeg" alt="Slide 5">
                </div>
            </div>

            <!-- Right: Action panel -->
            <div class="quick-actions">
                <a href="Booking.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="action-info">
                        <h3>Book an Event</h3>
                        <p>Lock in your preferred date and venue choice immediately.</p>
                    </div>
                </a>

                <a href="Food.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="action-info">
                        <h3>Food Calculator</h3>
                        <p>Calculate your buffet and beverage expenses accurately.</p>
                    </div>
                </a>

                <a href="admin/BookinglistClient.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-list-ul"></i>
                    </div>
                    <div class="action-info">
                        <h3>My Booking List</h3>
                        <p>Review the details and status of your active events.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
