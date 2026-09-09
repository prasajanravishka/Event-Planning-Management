<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_user'])) {
    header("Location: Login.php?redirect=" . urlencode('Slide.php'));
    exit();
}
include_once __DIR__ . '/../config/database.php';

$user = $_SESSION['login_user'];
$user_id = $_SESSION['user_id'] ?? 0;
if (empty($user_id) && isset($conn) && !$conn->connect_error) {
    $u_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("s", $user);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($u_row = $u_res->fetch_assoc()) {
            $user_id = (int)$u_row['id'];
            $_SESSION['user_id'] = $user_id;
        }
        $u_stmt->close();
    }
}

// 1. Metric: Active Bookings
$active_count = 0;
if (isset($conn) && !$conn->connect_error) {
    $act_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings 
                                WHERE (user_name = ? OR (user_id IS NOT NULL AND user_id = ?)) 
                                AND status IN ('pending', 'in progress', 'confirmed')");
    if ($act_stmt) {
        $act_stmt->bind_param("si", $user, $user_id);
        $act_stmt->execute();
        $active_count = (int)$act_stmt->get_result()->fetch_assoc()['total'];
        $act_stmt->close();
    }
}

// 2. Metric: Next Event & Days Remaining
$next_event = null;
$days_left = null;
if (isset($conn) && !$conn->connect_error) {
    $nxt_stmt = $conn->prepare("SELECT BookingID, EventType, EventDate, Place, status 
                                FROM bookings 
                                WHERE (user_name = ? OR (user_id IS NOT NULL AND user_id = ?)) 
                                AND EventDate >= CURDATE() 
                                AND status IN ('pending', 'in progress', 'confirmed') 
                                ORDER BY EventDate ASC LIMIT 1");
    if ($nxt_stmt) {
        $nxt_stmt->bind_param("si", $user, $user_id);
        $nxt_stmt->execute();
        $next_res = $nxt_stmt->get_result();
        if ($nxt_row = $next_res->fetch_assoc()) {
            $next_event = $nxt_row;
            $diff_seconds = strtotime($next_event['EventDate']) - strtotime(date('Y-m-d'));
            $days_left = max(0, (int)ceil($diff_seconds / 86400));
        }
        $nxt_stmt->close();
    }
}

// 3. Metric: Assigned Verified Suppliers
$suppliers_count = 0;
if (isset($conn) && !$conn->connect_error) {
    $sup_stmt = $conn->prepare("SELECT COUNT(DISTINCT bs.supplier_id) AS total_sups 
                                FROM booking_services bs 
                                JOIN bookings b ON bs.booking_id = b.BookingID 
                                WHERE (b.user_name = ? OR (b.user_id IS NOT NULL AND b.user_id = ?)) 
                                AND bs.supplier_id IS NOT NULL");
    if ($sup_stmt) {
        $sup_stmt->bind_param("si", $user, $user_id);
        $sup_stmt->execute();
        $suppliers_count = (int)$sup_stmt->get_result()->fetch_assoc()['total_sups'];
        $sup_stmt->close();
    }
}

// 4. Metric: Completed Celebrations
$completed_count = 0;
if (isset($conn) && !$conn->connect_error) {
    $cmp_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings 
                                WHERE (user_name = ? OR (user_id IS NOT NULL AND user_id = ?)) 
                                AND status = 'completed'");
    if ($cmp_stmt) {
        $cmp_stmt->bind_param("si", $user, $user_id);
        $cmp_stmt->execute();
        $completed_count = (int)$cmp_stmt->get_result()->fetch_assoc()['total'];
        $cmp_stmt->close();
    }
}

// 5. Recent Booking Record for Preview Card
$recent_booking = null;
$recent_bkg_suppliers = 0;
if (isset($conn) && !$conn->connect_error) {
    $rec_stmt = $conn->prepare("SELECT * FROM bookings 
                                WHERE (user_name = ? OR (user_id IS NOT NULL AND user_id = ?)) 
                                ORDER BY created_at DESC, BookingID DESC LIMIT 1");
    if ($rec_stmt) {
        $rec_stmt->bind_param("si", $user, $user_id);
        $rec_stmt->execute();
        $rec_res = $rec_stmt->get_result();
        if ($r_row = $rec_res->fetch_assoc()) {
            $recent_booking = $r_row;
            // Get count of assigned suppliers for this booking
            $bs_cnt_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM booking_services WHERE booking_id = ? AND supplier_id IS NOT NULL");
            if ($bs_cnt_stmt) {
                $bs_cnt_stmt->bind_param("s", $recent_booking['BookingID']);
                $bs_cnt_stmt->execute();
                $recent_bkg_suppliers = (int)$bs_cnt_stmt->get_result()->fetch_assoc()['c'];
                $bs_cnt_stmt->close();
            }
        }
        $rec_stmt->close();
    }
}

include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Event Hub & Command Center - EVENTFLARE</title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/global.css">

    <style>
        :root {
            --hub-bg: #090614;
            --hub-surface: rgba(22, 16, 44, 0.7);
            --hub-card-border: rgba(139, 92, 246, 0.2);
            --hub-accent-purple: #8b5cf6;
            --hub-accent-pink: #ec4899;
            --hub-accent-amber: #f59e0b;
            --hub-accent-emerald: #10b981;
            --hub-accent-blue: #3b82f6;
        }

        body {
            background-color: var(--hub-bg);
            color: var(--text-main);
            font-family: var(--font-body);
            overflow-x: hidden;
        }

        .dashboard-container {
            max-width: 1240px;
            margin: 30px auto 80px;
            padding: 0 24px;
        }

        /* Top Welcome Banner */
        .hub-welcome {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 20px;
            border-bottom: 1px solid var(--hub-card-border);
            padding-bottom: 24px;
        }

        .welcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(139, 92, 246, 0.12);
            color: #c084fc;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid rgba(139, 92, 246, 0.3);
            margin-bottom: 10px;
        }

        .welcome-title {
            font-size: 34px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 6px 0;
            letter-spacing: -0.02em;
        }

        .welcome-title span {
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-subtitle {
            color: var(--text-muted);
            font-size: 15px;
            margin: 0;
        }

        .welcome-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Metric Cards Strip */
        .metrics-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 35px;
        }

        .metric-card {
            background: var(--hub-surface);
            backdrop-filter: blur(12px);
            border: 1px solid var(--hub-card-border);
            border-radius: 18px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--metric-color, var(--primary));
        }

        .metric-card:hover {
            transform: translateY(-4px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
        }

        .metric-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: var(--icon-bg, rgba(139, 92, 246, 0.15));
            color: var(--metric-color, var(--primary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .metric-details {
            flex-grow: 1;
            min-width: 0;
        }

        .metric-val {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
            margin-bottom: 3px;
        }

        .metric-lbl {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Hero Split: Interactive Slider (Left) + Status Widget (Right) */
        .hub-hero-split {
            display: grid;
            grid-template-columns: 1.45fr 1fr;
            gap: 25px;
            margin-bottom: 50px;
        }

        /* Interactive Showcase Slider */
        .slider-card {
            background: var(--hub-surface);
            border: 1px solid var(--hub-card-border);
            border-radius: 22px;
            position: relative;
            overflow: hidden;
            height: 420px;
            box-shadow: var(--shadow-premium);
        }

        .slide-item {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.7s cubic-bezier(0.4, 0, 0.2, 1), transform 0.7s ease;
            transform: scale(1.03);
            display: flex;
            align-items: flex-end;
        }

        .slide-item.active {
            opacity: 1;
            visibility: visible;
            transform: scale(1);
            z-index: 2;
        }

        .slide-bg-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }

        .slide-gradient-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, rgba(10, 6, 22, 0.2) 0%, rgba(10, 6, 22, 0.85) 60%, rgba(10, 6, 22, 0.98) 100%);
            z-index: 2;
        }

        .slide-content {
            position: relative;
            z-index: 3;
            padding: 30px;
            width: 100%;
        }

        .slide-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 5px 12px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(6px);
            color: #ffffff;
            margin-bottom: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .slide-title {
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 8px 0;
            line-height: 1.25;
        }

        .slide-desc {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            margin: 0 0 20px 0;
            line-height: 1.5;
            max-width: 520px;
        }

        .slide-ctas {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Slider Controls */
        .slider-nav-arrows {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 10;
            display: flex;
            gap: 8px;
        }

        .slider-ctrl-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(15, 10, 30, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: var(--transition-smooth);
            font-size: 12px;
        }

        .slider-ctrl-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            transform: scale(1.08);
        }

        .slider-dots {
            position: absolute;
            bottom: 24px;
            right: 30px;
            z-index: 10;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .slider-dot {
            width: 8px;
            height: 8px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.35);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .slider-dot.active {
            width: 24px;
            background: var(--primary);
            box-shadow: 0 0 10px var(--primary);
        }

        /* Recent Booking / Onboarding Card */
        .status-card-wrap {
            background: var(--hub-surface);
            border: 1px solid var(--hub-card-border);
            border-radius: 22px;
            padding: 26px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 420px;
            box-shadow: var(--shadow-premium);
            position: relative;
        }

        .status-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 14px;
            margin-bottom: 16px;
        }

        .status-card-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .status-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-badge.in-progress {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .status-badge.confirmed {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-badge.completed {
            background: rgba(139, 92, 246, 0.15);
            color: #c084fc;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .status-body-content {
            flex-grow: 1;
        }

        .booking-ref-title {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 6px 0;
            line-height: 1.3;
        }

        .booking-spec-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 16px 0;
        }

        .bkg-spec-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 10px 14px;
        }

        .bkg-spec-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .bkg-spec-val {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bkg-suppliers-pill {
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.25);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 12px;
            color: #c084fc;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        /* Empty State */
        .empty-state-wrap {
            text-align: center;
            padding: 20px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(139, 92, 246, 0.12);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 16px;
            border: 1px solid rgba(139, 92, 246, 0.25);
        }

        .empty-state-title {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 8px;
        }

        .empty-state-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 20px;
            max-width: 320px;
        }

        /* Section Headings */
        .hub-section-head {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 15px;
        }

        .hub-section-tag {
            font-size: 11px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: block;
            margin-bottom: 4px;
        }

        .hub-section-title {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            letter-spacing: -0.01em;
        }

        .hub-section-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin: 4px 0 0;
        }

        /* 4 Event Universes Grid */
        .universe-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
            margin-bottom: 60px;
        }

        .universe-card {
            background: var(--hub-surface);
            border: 1px solid var(--hub-card-border);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: var(--transition-smooth);
            position: relative;
            box-shadow: var(--shadow-premium);
        }

        .universe-card:hover {
            transform: translateY(-6px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 16px 36px rgba(0,0,0,0.4), 0 0 15px var(--card-glow);
        }

        .universe-img-wrap {
            height: 160px;
            position: relative;
            overflow: hidden;
        }

        .universe-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .universe-card:hover .universe-img-wrap img {
            transform: scale(1.08);
        }

        .universe-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(10, 6, 22, 0.85);
            backdrop-filter: blur(8px);
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .universe-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .universe-title {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 6px;
            line-height: 1.3;
        }

        .universe-specs {
            font-size: 11px;
            font-weight: 700;
            color: var(--accent-color, var(--primary));
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .universe-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .universe-actions {
            display: flex;
            gap: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 14px;
        }

        .universe-actions .btn {
            flex: 1;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            white-space: nowrap;
            justify-content: center;
            gap: 5px;
        }

        /* Quick Planning Tools Grid */
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .tool-card {
            background: var(--hub-surface);
            border: 1px solid var(--hub-card-border);
            border-radius: 18px;
            padding: 22px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            transition: var(--transition-smooth);
            text-decoration: none;
        }

        .tool-card:hover {
            transform: translateY(-4px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
            background: rgba(26, 19, 52, 0.85);
        }

        .tool-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(139, 92, 246, 0.12);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            border: 1px solid rgba(139, 92, 246, 0.2);
            transition: var(--transition-smooth);
        }

        .tool-card:hover .tool-icon {
            background: var(--primary);
            color: #ffffff;
            transform: scale(1.06);
        }

        .tool-info h4 {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 4px;
        }

        .tool-info p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.4;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1100px) {
            .metrics-strip {
                grid-template-columns: repeat(2, 1fr);
            }
            .universe-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .tools-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .hub-hero-split {
                grid-template-columns: 1fr;
            }
            .slider-card {
                height: 380px;
            }
            .status-card-wrap {
                height: auto;
            }
        }

        @media (max-width: 650px) {
            .metrics-strip {
                grid-template-columns: 1fr;
            }
            .universe-grid {
                grid-template-columns: 1fr;
            }
            .tools-grid {
                grid-template-columns: 1fr;
            }
            .hub-welcome {
                flex-direction: column;
                align-items: flex-start;
            }
            .welcome-title {
                font-size: 28px;
            }
            .slider-card {
                height: 350px;
            }
            .slide-title {
                font-size: 20px;
            }
            .slide-desc {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- ================= TOP WELCOME BANNER ================= -->
        <div class="hub-welcome">
            <div>
                <div class="welcome-badge">
                    <i class="fas fa-crown"></i> Client Concierge Hub
                </div>
                <h1 class="welcome-title">
                    Welcome Back, <span><?= htmlspecialchars($user) ?></span>!
                </h1>
                <p class="welcome-subtitle">
                    Manage your active reservations, explore verified supplier packages, or launch an event booking wizard.
                </p>
            </div>
            <div class="welcome-actions">
                <a href="MyBookings.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-list-check"></i> My Bookings
                </a>
                <a href="ChooseEvent.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Plan New Event
                </a>
            </div>
        </div>

        <!-- ================= LIVE DATABASE METRICS STRIP ================= -->
        <div class="metrics-strip">
            <!-- 1. Active Reservations -->
            <div class="metric-card" style="--metric-color:#8b5cf6; --icon-bg:rgba(139, 92, 246, 0.15);">
                <div class="metric-icon-wrap">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="metric-details">
                    <div class="metric-val"><?= $active_count ?></div>
                    <div class="metric-lbl">Active Reservations</div>
                </div>
            </div>

            <!-- 2. Next Celebration Countdown -->
            <div class="metric-card" style="--metric-color:#3b82f6; --icon-bg:rgba(59, 130, 246, 0.15);">
                <div class="metric-icon-wrap">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="metric-details">
                    <div class="metric-val">
                        <?= $days_left !== null ? ($days_left === 0 ? 'Today!' : $days_left . ' <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Days</span>') : 'None' ?>
                    </div>
                    <div class="metric-lbl">
                        <?= $next_event ? htmlspecialchars($next_event['EventType']) : 'Next Celebration' ?>
                    </div>
                </div>
            </div>

            <!-- 3. Linked Verified Suppliers -->
            <div class="metric-card" style="--metric-color:#10b981; --icon-bg:rgba(16, 185, 129, 0.15);">
                <div class="metric-icon-wrap">
                    <i class="fas fa-handshake"></i>
                </div>
                <div class="metric-details">
                    <div class="metric-val"><?= $suppliers_count ?></div>
                    <div class="metric-lbl">Suppliers Linked</div>
                </div>
            </div>

            <!-- 4. Completed Events -->
            <div class="metric-card" style="--metric-color:#ec4899; --icon-bg:rgba(236, 72, 153, 0.15);">
                <div class="metric-icon-wrap">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="metric-details">
                    <div class="metric-val"><?= $completed_count ?></div>
                    <div class="metric-lbl">Past Celebrations</div>
                </div>
            </div>
        </div>

        <!-- ================= HERO ROW: INTERACTIVE SLIDER + LIVE STATUS CARD ================= -->
        <div class="hub-hero-split">
            <!-- Left: Interactive Showcase Carousel -->
            <div class="slider-card" id="hubSliderCard">
                <!-- Controls -->
                <div class="slider-nav-arrows">
                    <button type="button" class="slider-ctrl-btn" id="sliderPrevBtn" title="Previous Slide">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="slider-ctrl-btn" id="sliderPlayPauseBtn" title="Pause/Play Auto-Slide">
                        <i class="fas fa-pause"></i>
                    </button>
                    <button type="button" class="slider-ctrl-btn" id="sliderNextBtn" title="Next Slide">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <!-- Slide 1: Weddings -->
                <div class="slide-item active" data-slide-index="0">
                    <img src="assets/images/W1.jpg" alt="Luxury Weddings" class="slide-bg-img">
                    <div class="slide-gradient-overlay"></div>
                    <div class="slide-content">
                        <span class="slide-tag" style="border-color:rgba(139, 92, 246, 0.4); color:#c084fc;">
                            <i class="fas fa-gem"></i> Curated Weddings
                        </span>
                        <h2 class="slide-title">Poruwa Rituals, Floral Canopies & Grand Receptions</h2>
                        <p class="slide-desc">
                            Choreograph your nuptials with accredited Poruwa masters, luxury stage decor, five-star catering, and cinematic drone photography.
                        </p>
                        <div class="slide-ctas">
                            <a href="events/WeddingsSlids.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-images"></i> Explore Showcase
                            </a>
                            <a href="events/WeddingBooking.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-heart"></i> Plan Wedding Now
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Slide 2: DJ & Night Parties -->
                <div class="slide-item" data-slide-index="1">
                    <img src="assets/images/D1.jpg" alt="DJ & Parties" class="slide-bg-img">
                    <div class="slide-gradient-overlay"></div>
                    <div class="slide-content">
                        <span class="slide-tag" style="border-color:rgba(236, 72, 153, 0.4); color:#f472b6;">
                            <i class="fas fa-bolt"></i> High-Energy Nightlife
                        </span>
                        <h2 class="slide-title">Concert Sound, Laser Beam Staging & Verified DJs</h2>
                        <p class="slide-desc">
                            Experience booming line-array sound systems, intelligent moving head beams, confetti cannons, and flair cocktail mixology stations.
                        </p>
                        <div class="slide-ctas">
                            <a href="events/DjPartySlide.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-images"></i> Explore Showcase
                            </a>
                            <a href="events/PartyBooking.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-play"></i> Plan Party Now
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Slide 3: Birthday Milestones -->
                <div class="slide-item" data-slide-index="2">
                    <img src="assets/images/B1.jpg" alt="Birthday Milestones" class="slide-bg-img">
                    <div class="slide-gradient-overlay"></div>
                    <div class="slide-content">
                        <span class="slide-tag" style="border-color:rgba(219, 39, 119, 0.4); color:#f472b6;">
                            <i class="fas fa-cake-candles"></i> Bespoke Milestones
                        </span>
                        <h2 class="slide-title">Themed Backdrops, Balloon Arches & Dessert Bars</h2>
                        <p class="slide-desc">
                            From 1st birthday fairytale stages to 50th golden jubilees, enjoy curated entertainment, 360 photo booths, and designer cakes.
                        </p>
                        <div class="slide-ctas">
                            <a href="events/BirthdayList.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-images"></i> Explore Showcase
                            </a>
                            <a href="events/BirthdayBooking.php" class="btn btn-primary btn-sm" style="background:#db2777; border-color:#db2777;">
                                <i class="fas fa-wand-magic-sparkles"></i> Plan Birthday Now
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Slide 4: Get Togethers & Reunions -->
                <div class="slide-item" data-slide-index="3">
                    <img src="assets/images/G1.jpg" alt="Get Togethers" class="slide-bg-img">
                    <div class="slide-gradient-overlay"></div>
                    <div class="slide-content">
                        <span class="slide-tag" style="border-color:rgba(16, 185, 129, 0.4); color:#34d399;">
                            <i class="fas fa-users"></i> Reconnect & Celebrate
                        </span>
                        <h2 class="slide-title">Sizzling Live BBQ Grills, Canopies & Acoustic Music</h2>
                        <p class="slide-desc">
                            Batch meets, company alumni, and family reunions with live outdoor charcoal BBQ stations, weather-proof marquees, and acoustic sing-alongs.
                        </p>
                        <div class="slide-ctas">
                            <a href="events/GetTogether.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-images"></i> Explore Showcase
                            </a>
                            <a href="events/GetTogetherBooking.php" class="btn btn-primary btn-sm" style="background:#10b981; border-color:#10b981;">
                                <i class="fas fa-handshake"></i> Plan Reunion Now
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Dot Indicators -->
                <div class="slider-dots" id="sliderDots">
                    <div class="slider-dot active" onclick="jumpToSlide(0)"></div>
                    <div class="slider-dot" onclick="jumpToSlide(1)"></div>
                    <div class="slider-dot" onclick="jumpToSlide(2)"></div>
                    <div class="slider-dot" onclick="jumpToSlide(3)"></div>
                </div>
            </div>

            <!-- Right: Recent Booking Status Card -->
            <div class="status-card-wrap">
                <div class="status-card-header">
                    <h3 class="status-card-title">
                        <i class="fas fa-bell" style="color:var(--primary);"></i> Reservation Status
                    </h3>
                    <a href="MyBookings.php" style="font-size:12px; font-weight:700; color:var(--primary); text-decoration:none;">
                        View All (<?= $active_count ?>) <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if ($recent_booking): 
                    $st = strtolower($recent_booking['status']);
                    $st_class = 'pending';
                    $st_label = 'Pending Review';
                    $st_icon = 'fa-clock';
                    if ($st === 'in progress') {
                        $st_class = 'in-progress';
                        $st_label = 'In Progress';
                        $st_icon = 'fa-spinner fa-spin';
                    } elseif ($st === 'confirmed') {
                        $st_class = 'confirmed';
                        $st_label = 'Confirmed & Locked';
                        $st_icon = 'fa-check-circle';
                    } elseif ($st === 'completed') {
                        $st_class = 'completed';
                        $st_label = 'Completed';
                        $st_icon = 'fa-flag-checkered';
                    }
                ?>
                    <div class="status-body-content">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <span style="font-size:12px; font-family:monospace; color:var(--text-muted); font-weight:700;">
                                #<?= htmlspecialchars($recent_booking['BookingID']) ?>
                            </span>
                            <span class="status-badge <?= $st_class ?>">
                                <i class="fas <?= $st_icon ?>"></i> <?= $st_label ?>
                            </span>
                        </div>

                        <h4 class="booking-ref-title">
                            <?= htmlspecialchars($recent_booking['EventType']) ?>
                        </h4>

                        <div class="booking-spec-grid">
                            <div class="bkg-spec-item">
                                <div class="bkg-spec-label">Target Date</div>
                                <div class="bkg-spec-val">
                                    <i class="fas fa-calendar-day" style="color:var(--primary); font-size:11px;"></i>
                                    <?= date('M d, Y', strtotime($recent_booking['EventDate'])) ?>
                                </div>
                            </div>
                            <div class="bkg-spec-item">
                                <div class="bkg-spec-label">Time & Guests</div>
                                <div class="bkg-spec-val">
                                    <?= htmlspecialchars($recent_booking['DayNight'] ?? 'Day') ?> &bull; <?= (int)$recent_booking['NumberOfGuests'] ?> Pax
                                </div>
                            </div>
                            <div class="bkg-spec-item" style="grid-column:1/-1;">
                                <div class="bkg-spec-label">Selected Venue</div>
                                <div class="bkg-spec-val">
                                    <i class="fas fa-map-marker-alt" style="color:var(--primary); font-size:11px;"></i>
                                    <?= htmlspecialchars($recent_booking['Place'] ?? 'To be coordinated') ?>
                                </div>
                            </div>
                        </div>

                        <div class="bkg-suppliers-pill">
                            <i class="fas fa-people-arrows"></i>
                            <span>
                                <?= $recent_bkg_suppliers > 0 ? "<strong>$recent_bkg_suppliers</strong> Verified Supplier(s) Assigned" : "Vendor Coordination Underway" ?>
                            </span>
                        </div>
                    </div>

                    <a href="MyBookings.php" class="btn btn-secondary btn-sm" style="width:100%; text-align:center;">
                        <i class="fas fa-eye"></i> Manage Reservation Dossier
                    </a>
                <?php else: ?>
                    <div class="empty-state-wrap">
                        <div class="empty-state-icon">
                            <i class="fas fa-wand-magic-sparkles"></i>
                        </div>
                        <h4 class="empty-state-title">No Active Reservations</h4>
                        <p class="empty-state-desc">
                            Select an event type below to launch our customized 4-stage booking wizard and configure your suppliers in real-time.
                        </p>
                        <a href="ChooseEvent.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-sparkles"></i> Start Planning An Event
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================= 4 EVENT UNIVERSES GATEWAYS ================= -->
        <div class="hub-section-head">
            <div>
                <span class="hub-section-tag">Event Portfolios</span>
                <h2 class="hub-section-title">Explore 4 Dedicated Event Worlds</h2>
                <p class="hub-section-subtitle">
                    Inspect case studies, accredited supplier packages, or step into the dedicated 4-stage booking wizards.
                </p>
            </div>
            <a href="ChooseEvent.php" class="btn btn-secondary btn-sm">
                View All Categories <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="universe-grid">
            <!-- 1. Weddings -->
            <div class="universe-card">
                <div class="universe-img-wrap">
                    <img src="assets/images/W1.jpg" alt="Weddings" loading="lazy">
                    <span class="universe-badge"><i class="fas fa-heart"></i> Weddings</span>
                </div>
                <div class="universe-body">
                    <h3 class="universe-title">Weddings & Poruwa</h3>
                    <div class="universe-specs" style="color:#c084fc;">
                        Poruwa • Decor • Photography • Music
                    </div>
                    <p class="universe-desc">
                        Traditional customs meet modern luxury. Choreograph Poruwa rituals, five-star floral canopies, and drone cinematography.
                    </p>
                    <div class="universe-actions">
                        <a href="events/WeddingsSlids.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i> Showcase
                        </a>
                        <a href="events/WeddingBooking.php" class="btn btn-primary btn-sm">
                            Book Wizard <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 2. DJ Parties -->
            <div class="universe-card">
                <div class="universe-img-wrap">
                    <img src="assets/images/D1.jpg" alt="DJ Parties" loading="lazy">
                    <span class="universe-badge"><i class="fas fa-bolt"></i> DJ Parties</span>
                </div>
                <div class="universe-body">
                    <h3 class="universe-title">DJ & Club Nights</h3>
                    <div class="universe-specs" style="color:#f472b6;">
                        Sound Rigs • Intelligent Lasers • DJs
                    </div>
                    <p class="universe-desc">
                        High-energy sound rigs, moving head beams, smoke hazers, and accredited DJs with custom music playlist coordination.
                    </p>
                    <div class="universe-actions">
                        <a href="events/DjPartySlide.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i> Showcase
                        </a>
                        <a href="events/PartyBooking.php" class="btn btn-primary btn-sm">
                            Book Wizard <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 3. Birthdays -->
            <div class="universe-card">
                <div class="universe-img-wrap">
                    <img src="assets/images/B1.jpg" alt="Birthdays" loading="lazy">
                    <span class="universe-badge"><i class="fas fa-cake-candles"></i> Birthdays</span>
                </div>
                <div class="universe-body">
                    <h3 class="universe-title">Milestone Birthdays</h3>
                    <div class="universe-specs" style="color:#db2777;">
                        Custom Themes • Balloon Art • Cakes
                    </div>
                    <p class="universe-desc">
                        Curate bespoke balloon garlands, dessert tables, 360 photobooths, and kids/adult entertainment for all milestone ages.
                    </p>
                    <div class="universe-actions">
                        <a href="events/BirthdayList.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i> Showcase
                        </a>
                        <a href="events/BirthdayBooking.php" class="btn btn-primary btn-sm" style="background:#db2777; border-color:#db2777;">
                            Book Wizard <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 4. Get Togethers -->
            <div class="universe-card">
                <div class="universe-img-wrap">
                    <img src="assets/images/G1.jpg" alt="Get Togethers" loading="lazy">
                    <span class="universe-badge"><i class="fas fa-users"></i> Reunions</span>
                </div>
                <div class="universe-body">
                    <h3 class="universe-title">Reunions & Get Togethers</h3>
                    <div class="universe-specs" style="color:#10b981;">
                        Live BBQ • Marquees • Acoustic Duos
                    </div>
                    <p class="universe-desc">
                        Outdoor batch meets, alumni mixers, and family picnics featuring charcoal BBQ buffets, canopies, and sing-along musicians.
                    </p>
                    <div class="universe-actions">
                        <a href="events/GetTogether.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i> Showcase
                        </a>
                        <a href="events/GetTogetherBooking.php" class="btn btn-primary btn-sm" style="background:#10b981; border-color:#10b981;">
                            Book Wizard <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= QUICK PLANNING TOOLS ================= -->
        <div class="hub-section-head">
            <div>
                <span class="hub-section-tag">Concierge Toolkit</span>
                <h2 class="hub-section-title">Quick Planning Utilities</h2>
                <p class="hub-section-subtitle">
                    Fast calculators and directories to streamline your event budgeting and vendor selections.
                </p>
            </div>
        </div>

        <div class="tools-grid">
            <a href="Food.php" class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="tool-info">
                    <h4>Food & Drink Calculator</h4>
                    <p>Accurately estimate buffet, beverage, and BBQ costs per guest.</p>
                </div>
            </a>

            <a href="events/HotelSlide.php" class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="tool-info">
                    <h4>Hotels & Venues</h4>
                    <p>Explore five-star ballrooms, beach resorts, and outdoor gardens.</p>
                </div>
            </a>

            <a href="MyBookings.php" class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="tool-info">
                    <h4>My Invoices & Bookings</h4>
                    <p>Review active contracts, rate vendors, and inspect service breakdowns.</p>
                </div>
            </a>

            <a href="Booking.php" class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="tool-info">
                    <h4>Express Date Lock</h4>
                    <p>Need a quick reservation? Place a priority booking directly.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- ================= JAVASCRIPT CONTROLLER FOR SLIDER ================= -->
    <script>
        (function() {
            const slides = document.querySelectorAll('.slide-item');
            const dots = document.querySelectorAll('.slider-dot');
            const prevBtn = document.getElementById('sliderPrevBtn');
            const nextBtn = document.getElementById('sliderNextBtn');
            const playPauseBtn = document.getElementById('sliderPlayPauseBtn');
            const sliderCard = document.getElementById('hubSliderCard');

            let currentSlide = 0;
            let slideInterval = null;
            let isPaused = false;
            const slideDelay = 5000; // 5 seconds

            function showSlide(index) {
                if (index < 0) {
                    currentSlide = slides.length - 1;
                } else if (index >= slides.length) {
                    currentSlide = 0;
                } else {
                    currentSlide = index;
                }

                slides.forEach((slide, i) => {
                    if (i === currentSlide) {
                        slide.classList.add('active');
                    } else {
                        slide.classList.remove('active');
                    }
                });

                dots.forEach((dot, i) => {
                    if (i === currentSlide) {
                        dot.classList.add('active');
                    } else {
                        dot.classList.remove('active');
                    }
                });
            }

            function nextSlide() {
                showSlide(currentSlide + 1);
            }

            function prevSlide() {
                showSlide(currentSlide - 1);
            }

            window.jumpToSlide = function(index) {
                showSlide(index);
                resetAutoPlay();
            };

            function startAutoPlay() {
                if (!slideInterval && !isPaused) {
                    slideInterval = setInterval(nextSlide, slideDelay);
                }
            }

            function stopAutoPlay() {
                if (slideInterval) {
                    clearInterval(slideInterval);
                    slideInterval = null;
                }
            }

            function resetAutoPlay() {
                stopAutoPlay();
                startAutoPlay();
            }

            function togglePlayPause() {
                isPaused = !isPaused;
                if (isPaused) {
                    stopAutoPlay();
                    playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                    playPauseBtn.title = "Resume Auto-Slide";
                } else {
                    startAutoPlay();
                    playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                    playPauseBtn.title = "Pause Auto-Slide";
                }
            }

            // Event Listeners
            if (nextBtn) nextBtn.addEventListener('click', function() {
                nextSlide();
                resetAutoPlay();
            });

            if (prevBtn) prevBtn.addEventListener('click', function() {
                prevSlide();
                resetAutoPlay();
            });

            if (playPauseBtn) playPauseBtn.addEventListener('click', togglePlayPause);

            // Pause on mouse hover, resume on mouse leave
            if (sliderCard) {
                sliderCard.addEventListener('mouseenter', function() {
                    if (!isPaused) stopAutoPlay();
                });
                sliderCard.addEventListener('mouseleave', function() {
                    if (!isPaused) startAutoPlay();
                });
            }

            // Initial auto-play start
            startAutoPlay();
        })();
    </script>
</body>
</html>
