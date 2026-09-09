<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../../config/database.php';
include __DIR__ . '/../../includes/navbar.php';

// Auth State
$is_logged_in = isset($_SESSION['user']) || isset($_SESSION['user_name']) || isset($_SESSION['username']);
$logged_user = $_SESSION['user'] ?? $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$logged_user_id = $_SESSION['user_id'] ?? null;

if ($is_logged_in && empty($logged_user_id) && isset($conn)) {
    $u_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("s", $logged_user);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($u_row = $u_res->fetch_assoc()) {
            $logged_user_id = (int)$u_row['id'];
        }
        $u_stmt->close();
    }
}

// Pre-fill parameters from GET
$pre_theme   = trim($_GET['theme'] ?? 'kids');
$pre_venue   = trim($_GET['venue'] ?? '');
$pre_guests  = intval($_GET['guests'] ?? 80);
if ($pre_guests <= 0) $pre_guests = 80;
$pre_pkg_id  = intval($_GET['package_id'] ?? 0);

$booking_success = false;
$created_booking_id = "";
$error_message = "";

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'confirm_birthday_booking') {
    $celebrant_name = trim($_POST['celebrant_name'] ?? 'Celebrant');
    $milestone_age  = trim($_POST['milestone_age'] ?? '');
    $contact_phone  = trim($_POST['contact_phone'] ?? '');
    $contact_email  = trim($_POST['contact_email'] ?? '');
    $event_date     = trim($_POST['event_date'] ?? '');
    $day_night      = trim($_POST['day_night'] ?? 'Day');
    $place          = trim($_POST['place'] ?? '');
    if (empty($place)) {
        $place = !empty($pre_venue) ? $pre_venue : 'Venue To Be Coordinated by EVENTFLARE';
    }
    $guest_count    = intval($_POST['guest_count'] ?? 80);
    $birthday_theme = trim($_POST['birthday_theme'] ?? 'Whimsical Fairy & Princess Fantasy');
    $food_tier      = trim($_POST['food_tier'] ?? 'Kids & Teens Party Snacks Fiesta');
    $extra_details  = trim($_POST['extra_details'] ?? '');
    $selected_pkgs  = $_POST['selected_packages'] ?? []; // format: [service_id => listing_id]

    // Determine customer username
    $customer_username = $logged_user;
    if (empty($customer_username)) {
        $customer_username = trim($_POST['guest_username'] ?? '');
        if (empty($customer_username)) {
            $customer_username = !empty($contact_email) ? explode('@', $contact_email)[0] : 'birthday_host';
        }
    }

    if (empty($event_date) || $guest_count <= 0) {
        $error_message = "Please complete all required fields (Celebration Date and Expected Guests).";
    } elseif (strtotime($event_date) < strtotime('today')) {
        $error_message = "Birthday celebration date must be today or in the future.";
    } else {
        // Generate Unique Collision-Resistant Booking ID
        $booking_id = "BKG-BDY-" . strtoupper(bin2hex(random_bytes(4)));
        
        $conn->begin_transaction();
        try {
            // 1. Insert into bookings
            $full_notes = "Celebrant: " . $celebrant_name . " (Milestone: " . $milestone_age . ") | Contact: " . $contact_phone . " (" . $contact_email . ") | Theme: " . $birthday_theme;
            if (!empty($extra_details)) {
                $full_notes .= " | Special Instructions: " . $extra_details;
            }

            $b_stmt = $conn->prepare("INSERT INTO bookings (BookingID, user_id, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails, status) 
                                      VALUES (?, ?, ?, 'Birthdays', ?, ?, ?, ?, ?, ?, 'pending')");
            $b_stmt->bind_param("sississss", $booking_id, $logged_user_id, $customer_username, $place, $guest_count, $event_date, $day_night, $food_tier, $full_notes);
            if (!$b_stmt->execute()) {
                throw new Exception("Error saving birthday booking: " . $b_stmt->error);
            }
            $b_stmt->close();

            // 2. Insert into event_extras
            $extra_equip = "Bespoke Themed Backdrop, Custom Cake Plinths, Entertainment Setup";
            $ee_stmt = $conn->prepare("INSERT INTO event_extras (booking_id, equipment, food_style) VALUES (?, ?, ?)");
            $ee_stmt->bind_param("sss", $booking_id, $extra_equip, $food_tier);
            if (!$ee_stmt->execute()) {
                throw new Exception("Error saving birthday extras: " . $ee_stmt->error);
            }
            $ee_stmt->close();

            // 3. Insert chosen suppliers into booking_services
            if (!empty($selected_pkgs) && is_array($selected_pkgs)) {
                $bs_stmt = $conn->prepare("INSERT INTO booking_services (booking_id, service_id, supplier_id, listing_id, custom_notes, assigned_cost, status) 
                                           VALUES (?, ?, ?, ?, ?, ?, 'confirmed')");
                
                foreach ($selected_pkgs as $svc_id => $listing_id) {
                    $listing_id = intval($listing_id);
                    if ($listing_id <= 0) continue;

                    $l_query = $conn->prepare("SELECT supplier_id, service_id, price, price_type, title FROM supplier_listings WHERE listing_id = ?");
                    $l_query->bind_param("i", $listing_id);
                    $l_query->execute();
                    $l_res = $l_query->get_result();
                    if ($l_row = $l_res->fetch_assoc()) {
                        $cost = (float)$l_row['price'];
                        if ($l_row['price_type'] === 'per_person') {
                            $cost = $cost * $guest_count;
                        }
                        $note = "Client Selection: " . $l_row['title'];
                        $bs_stmt->bind_param("siiisd", $booking_id, $l_row['service_id'], $l_row['supplier_id'], $listing_id, $note, $cost);
                        if (!$bs_stmt->execute()) {
                            throw new Exception("Error linking supplier service: " . $bs_stmt->error);
                        }
                    }
                    $l_query->close();
                }
                $bs_stmt->close();
            }

            $conn->commit();
            $booking_success = true;
            $created_booking_id = $booking_id;
        } catch (Throwable $e) {
            $conn->rollback();
            $error_message = "Transaction failed: " . $e->getMessage();
        }
    }
}

// Fetch all Services for Birthdays (event_type_id = 3)
$birthday_services = [];
if (isset($conn) && !$conn->connect_error) {
    $s_res = $conn->query("SELECT * FROM services WHERE event_type_id = 3 ORDER BY priority_rank ASC, service_id ASC");
    if ($s_res) {
        while ($r = $s_res->fetch_assoc()) {
            $birthday_services[] = $r;
        }
    }
}

// Fetch Listings for each service grouped by service_id
$service_listings = [];
if (!empty($birthday_services) && isset($conn)) {
    $svc_ids = array_column($birthday_services, 'service_id');
    $ids_str = implode(',', array_map('intval', $svc_ids));
    if (!empty($ids_str)) {
        $l_sql = "SELECT sl.*, sup.business_name, sup.category, sup.contact_phone, sup.location 
                  FROM supplier_listings sl 
                  JOIN suppliers sup ON sl.supplier_id = sup.id 
                  WHERE sl.service_id IN ($ids_str) AND sl.status = 'active'
                  ORDER BY sl.price ASC";
        $l_res = $conn->query($l_sql);
        if ($l_res) {
            while ($row = $l_res->fetch_assoc()) {
                $service_listings[$row['service_id']][] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birthday Booking Wizard - EVENTFLARE</title>
    <style>
        .orb {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.15) 0%, rgba(139, 92, 246, 0.04) 70%);
            filter: blur(80px);
            z-index: -1;
            pointer-events: none;
        }
        .orb-1 { top: 8%; right: -12%; }
        .orb-2 { top: 45%; left: -15%; }

        .wizard-container {
            max-width: 1100px;
            margin: 100px auto 90px;
            padding: 0 20px;
        }

        /* Stepper Navigation Header */
        .stepper-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin-bottom: 45px;
            padding: 0 20px;
        }

        .stepper-progress-track {
            position: absolute;
            top: 24px;
            left: 50px;
            right: 50px;
            height: 4px;
            background: rgba(255, 255, 255, 0.08);
            z-index: 1;
        }

        .stepper-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #ec4899, #db2777);
            transition: width 0.4s ease;
        }

        .step-node {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .step-bubble {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 2px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: var(--text-muted);
            transition: var(--transition-smooth);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .step-node.active .step-bubble {
            background: #db2777;
            border-color: #db2777;
            color: #fff;
            box-shadow: 0 0 20px rgba(219, 39, 119, 0.5);
        }

        .step-node.completed .step-bubble {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }

        .step-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .step-node.active .step-label {
            color: #db2777;
        }

        /* Wizard Cards */
        .step-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: var(--shadow-premium);
            margin-bottom: 30px;
        }

        .step-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 8px;
        }

        .step-desc {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .wizard-step-pane {
            display: none;
        }

        .wizard-step-pane.active {
            display: block;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Inputs */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 8px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 12px 16px;
            color: var(--text-heading);
            font-size: 14px;
            transition: var(--transition-smooth);
            box-sizing: border-box;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #db2777;
            outline: none;
            box-shadow: 0 0 15px rgba(219, 39, 119, 0.3);
            background: rgba(255, 255, 255, 0.08);
        }

        /* Theme Cards */
        .style-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .style-card {
            background: rgba(255, 255, 255, 0.03);
            border: 2px solid var(--card-border);
            border-radius: 18px;
            padding: 22px;
            cursor: pointer;
            transition: var(--transition-smooth);
            text-align: center;
        }

        .style-card:hover {
            border-color: rgba(236, 72, 153, 0.5);
            transform: translateY(-4px);
        }

        .style-card.selected {
            border-color: #db2777;
            background: rgba(236, 72, 153, 0.12);
            box-shadow: 0 10px 25px rgba(219, 39, 119, 0.25);
        }

        .style-card-icon {
            font-size: 32px;
            color: #db2777;
            margin-bottom: 12px;
        }

        .style-card-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 6px;
        }

        .style-card-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Supplier Picker Section */
        .service-picker-section {
            margin-bottom: 35px;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 30px;
        }

        .service-picker-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .service-picker-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .service-picker-title h4 {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .supplier-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 18px;
        }

        .supplier-option-card {
            background: rgba(255, 255, 255, 0.03);
            border: 2px solid var(--card-border);
            border-radius: 16px;
            padding: 18px;
            cursor: pointer;
            transition: var(--transition-smooth);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .supplier-option-card:hover {
            border-color: rgba(236, 72, 153, 0.4);
        }

        .supplier-option-card.selected {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.08);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.2);
        }

        .sup-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .sup-name {
            font-size: 13px;
            font-weight: 700;
            color: #db2777;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .sup-pkg-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 8px;
        }

        .sup-price {
            font-size: 18px;
            font-weight: 800;
            color: #10b981;
            margin-bottom: 10px;
        }

        .sup-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 12px;
            flex-grow: 1;
        }

        .sup-select-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            border-top: 1px solid var(--card-border);
            padding-top: 10px;
            margin-top: auto;
        }

        .supplier-option-card.selected .sup-select-btn {
            color: #10b981;
        }

        /* Review Layout */
        .review-layout {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 30px;
        }

        .review-summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .review-summary-table th, .review-summary-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--card-border);
            text-align: left;
        }

        .review-summary-table th {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .review-summary-table td.cost-col {
            text-align: right;
            font-weight: 700;
            color: var(--text-heading);
        }

        .budget-total-box {
            background: rgba(236, 72, 153, 0.1);
            border: 1px solid rgba(236, 72, 153, 0.3);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .total-amount-display {
            font-size: 34px;
            font-weight: 900;
            color: #db2777;
            line-height: 1;
        }

        .wizard-nav-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .form-grid, .review-layout {
                grid-template-columns: 1fr;
            }
            .stepper-header {
                padding: 0;
            }
            .step-label {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="wizard-container">
        <?php if ($booking_success): ?>
            <!-- ================= CONFIRMATION SCREEN ================= -->
            <div class="step-card" style="text-align:center; padding:60px 30px;">
                <div style="width:76px; height:76px; border-radius:50%; background:rgba(16, 185, 129, 0.15); color:#10b981; font-size:36px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; border:1px solid rgba(16, 185, 129, 0.3);">
                    <i class="fas fa-check"></i>
                </div>
                <span class="badge" style="background:#db2777; color:#fff; font-size:12px; margin-bottom:12px; text-transform:uppercase;">Celebration Confirmed</span>
                <h1 style="font-size:32px; font-weight:900; color:var(--text-heading); margin-bottom:10px;">Birthday Booking Locked In!</h1>
                <p style="color:var(--text-muted); font-size:15px; max-width:600px; margin:0 auto 25px; line-height:1.7;">
                    Your custom birthday celebration is now reserved. Our pastry artisans and themed event coordinators have received your setup brief.
                </p>
                <div style="display:inline-block; background:rgba(255,255,255,0.04); border:1px dashed #db2777; border-radius:14px; padding:12px 28px; margin-bottom:30px;">
                    <span style="font-size:12px; color:var(--text-muted); text-transform:uppercase;">Reference ID:</span>
                    <h3 style="font-size:24px; font-weight:800; color:#db2777; margin:2px 0 0;"><?= htmlspecialchars($created_booking_id) ?></h3>
                </div>

                <div style="display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
                    <a href="../MyBookings.php" class="btn btn-primary" style="background:#db2777; border-color:#db2777;"><i class="fas fa-calendar-check"></i> View In My Bookings</a>
                    <a href="BirthdayList.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Return to Birthday Showcase</a>
                </div>
            </div>
        <?php else: ?>
            <!-- ================= STEPPER HEADER ================= -->
            <div class="stepper-header">
                <div class="stepper-progress-track">
                    <div class="stepper-progress-bar" id="stepperProgressBar"></div>
                </div>

                <div class="step-node active" id="stepNode1" onclick="goToStep(1)">
                    <div class="step-bubble">1</div>
                    <span class="step-label">Celebrant</span>
                </div>
                <div class="step-node" id="stepNode2" onclick="goToStep(2)">
                    <div class="step-bubble">2</div>
                    <span class="step-label">Theme & Food</span>
                </div>
                <div class="step-node" id="stepNode3" onclick="goToStep(3)">
                    <div class="step-bubble">3</div>
                    <span class="step-label">Choose Cakes & Decor</span>
                </div>
                <div class="step-node" id="stepNode4" onclick="goToStep(4)">
                    <div class="step-bubble">4</div>
                    <span class="step-label">Review & Budget</span>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div style="background:rgba(239, 68, 68, 0.12); border:1px solid rgba(239, 68, 68, 0.3); border-radius:14px; padding:16px 20px; color:#f87171; margin-bottom:25px; font-weight:600; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="BirthdayBooking.php" id="birthdayBookingWizardForm">
                <input type="hidden" name="action" value="confirm_birthday_booking">
                <input type="hidden" name="birthday_theme" id="selectedBirthdayThemeInput" value="Whimsical Fairy & Princess Fantasy">

                <!-- ================= STAGE 1: FUNDAMENTALS ================= -->
                <div class="wizard-step-pane active" id="wizardStep1">
                    <div class="step-card">
                        <h2 class="step-title">1. Celebrant & Party Fundamentals</h2>
                        <p class="step-desc">Enter the celebrant’s name, milestone, primary contact info, and expected guest count.</p>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="celebrant_name">Celebrant’s Full Name</label>
                                <input type="text" class="form-input" id="celebrant_name" name="celebrant_name" 
                                       placeholder="e.g. Senuri Ranasinghe / Uncle Nihal" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="milestone_age">Milestone or Age Turning</label>
                                <input type="text" class="form-input" id="milestone_age" name="milestone_age" 
                                       placeholder="e.g. 1st Birthday / Sweet 16 / 21st / 60th Jubilee" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_phone">Primary Contact Phone</label>
                                <input type="tel" class="form-input" id="contact_phone" name="contact_phone" 
                                       placeholder="+94 77 123 4567" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_email">Host Email Address</label>
                                <input type="email" class="form-input" id="contact_email" name="contact_email" 
                                       placeholder="host@eventflare.com" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="event_date">Celebration Date</label>
                                <input type="date" class="form-input" id="event_date" name="event_date" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="day_night">Celebration Session</label>
                                <select class="form-select" id="day_night" name="day_night">
                                    <option value="Day">Afternoon Tea & Games (2:00 PM – 6:00 PM)</option>
                                    <option value="Night">Evening Dinner Gala (6:30 PM – 11:30 PM)</option>
                                </select>
                            </div>

                            <input type="hidden" id="place" name="place" value="<?= htmlspecialchars($pre_venue) ?>">

                            <div class="form-group">
                                <label class="form-label" for="guest_count">Expected Guests (Kids & Adults)</label>
                                <input type="number" class="form-input" id="guest_count" name="guest_count" 
                                       min="20" max="1000" value="<?= $pre_guests ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-nav-actions">
                        <a href="BirthdayList.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
                        <button type="button" class="btn btn-primary" style="background:#db2777; border-color:#db2777;" onclick="goToStep(2)">
                            Continue to Theme & Food <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ================= STAGE 2: THEME & FOOD ================= -->
                <div class="wizard-step-pane" id="wizardStep2">
                    <div class="step-card">
                        <h2 class="step-title">2. Choose Celebration Theme & Food Tier</h2>
                        <p class="step-desc">Select the decorative theme concept and dining style that matches the celebrant.</p>

                        <label class="form-label" style="margin-bottom:14px;">Celebration Theme Concept</label>
                        <div class="style-cards-grid">
                            <div class="style-card selected" data-style="Whimsical Fairy & Princess Fantasy">
                                <div class="style-card-icon"><i class="fas fa-crown"></i></div>
                                <div class="style-card-title">Princess & Fairytale</div>
                                <div class="style-card-desc">Pastel balloon castles, floral plinths, throne seating, and glitter accents.</div>
                            </div>

                            <div class="style-card" data-style="Superheroes & Jungle Adventure">
                                <div class="style-card-icon"><i class="fas fa-paw"></i></div>
                                <div class="style-card-title">Superheroes & Safari</div>
                                <div class="style-card-desc">Action-packed themes, animal cutouts, safari balloon arches, and adventure games.</div>
                            </div>

                            <div class="style-card" data-style="Sweet 16 / 21st Rose Gold Glamour">
                                <div class="style-card-icon"><i class="fas fa-gem"></i></div>
                                <div class="style-card-title">Rose Gold Glamour</div>
                                <div class="style-card-desc">Shimmer sequin walls, LED milestone numbers, macaron towers, and teen DJ beats.</div>
                            </div>

                            <div class="style-card" data-style="Vintage Milestone Classic (40th / 50th / 60th)">
                                <div class="style-card-icon"><i class="fas fa-award"></i></div>
                                <div class="style-card-title">Milestone Jubilee</div>
                                <div class="style-card-desc">Fairy light canopies, retrospective photo galleries, jazz music, and curated carvery.</div>
                            </div>
                        </div>

                        <div class="form-group" style="max-width:500px; margin-top:20px;">
                            <label class="form-label" for="food_tier">Party Catering & Dessert Tier</label>
                            <select class="form-select" id="food_tier" name="food_tier">
                                <option value="Kids & Teens Party Snacks Fiesta">Kids & Teens Party Snacks Fiesta (Rs. 2,200/pax)</option>
                                <option value="Deluxe Birthday High Tea & Savory Spread">Deluxe Birthday High Tea & Savory Spread (Rs. 3,200/pax)</option>
                                <option value="Grand Celebration Banquet & Carvery">Grand Celebration Banquet & Carvery (Rs. 4,200/pax)</option>
                            </select>
                            <span style="font-size:12px; color:var(--text-muted); display:block; margin-top:6px;">
                                *Live estimator multiplies this per-person rate by your total expected guest count.
                            </span>
                        </div>
                    </div>

                    <div class="wizard-nav-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                            <i class="fas fa-arrow-left"></i> Back to Celebrant
                        </button>
                        <button type="button" class="btn btn-primary" style="background:#db2777; border-color:#db2777;" onclick="goToStep(3)">
                            Continue to Cakes & Decor <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ================= STAGE 3: MULTI-SUPPLIER SELECTION ================= -->
                <div class="wizard-step-pane" id="wizardStep3">
                    <div class="step-card">
                        <h2 class="step-title">3. Handpick Your Custom Bakers, Decorators & Hosts</h2>
                        <p class="step-desc">
                            Select certified patisseries for custom cakes, balloon decorators, and interactive game entertainers. Choose your favorite or skip optional services.
                        </p>

                        <?php if (empty($birthday_services)): ?>
                            <p style="color:var(--text-muted);">No services currently configured for Birthdays.</p>
                        <?php else: ?>
                            <?php foreach ($birthday_services as $svc): ?>
                                <?php 
                                    $svc_id = $svc['service_id'];
                                    $listings = $service_listings[$svc_id] ?? [];
                                ?>
                                <div class="service-picker-section">
                                    <div class="service-picker-title">
                                        <h4>
                                            <i class="fas fa-check-circle" style="color:#db2777;"></i>
                                            <?= htmlspecialchars($svc['service_name']) ?>
                                        </h4>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="skipService(<?= $svc_id ?>)">
                                            Skip This Service
                                        </button>
                                    </div>
                                    <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px;">
                                        <?= htmlspecialchars($svc['description']) ?>
                                    </p>

                                    <input type="radio" name="selected_packages[<?= $svc_id ?>]" id="skip_<?= $svc_id ?>" value="" checked style="display:none;">

                                    <?php if (empty($listings)): ?>
                                        <div style="padding:15px; background:rgba(255,255,255,0.02); border:1px dashed var(--card-border); border-radius:12px; font-size:13px; color:var(--text-muted);">
                                            No verified listings currently published for this category. Standard options can be arranged with your coordinator.
                                        </div>
                                    <?php else: ?>
                                        <div class="supplier-options-grid">
                                            <?php foreach ($listings as $pkg): ?>
                                                <?php 
                                                    $is_preselected = ($pre_pkg_id > 0 && $pkg['listing_id'] == $pre_pkg_id);
                                                ?>
                                                <div class="supplier-option-card <?= $is_preselected ? 'selected' : '' ?>" 
                                                     data-service-id="<?= $svc_id ?>"
                                                     data-supplier-name="<?= htmlspecialchars($pkg['business_name']) ?>"
                                                     data-package-title="<?= htmlspecialchars($pkg['title']) ?>"
                                                     data-price="<?= $pkg['price'] ?>"
                                                     data-price-type="<?= $pkg['price_type'] ?>">
                                                    
                                                    <div class="sup-header">
                                                        <span class="sup-name" style="color:#db2777;"><?= htmlspecialchars($pkg['business_name']) ?></span>
                                                        <input type="radio" name="selected_packages[<?= $svc_id ?>]" 
                                                               value="<?= $pkg['listing_id'] ?>" 
                                                               <?= $is_preselected ? 'checked' : '' ?>
                                                               style="accent-color:#10b981; width:18px; height:18px;">
                                                    </div>

                                                    <div class="sup-pkg-title"><?= htmlspecialchars($pkg['title']) ?></div>

                                                    <div class="sup-price">
                                                        Rs. <?= number_format($pkg['price'], 2) ?>
                                                        <span style="font-size:11px; color:var(--text-muted); font-weight:normal;">
                                                            <?= $pkg['price_type'] === 'per_person' ? '/ person' : 'total package' ?>
                                                        </span>
                                                    </div>

                                                    <div class="sup-desc"><?= htmlspecialchars($pkg['description']) ?></div>

                                                    <div class="sup-select-btn">
                                                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($pkg['location']) ?></span>
                                                        <span>Click to Select</span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="wizard-nav-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                            <i class="fas fa-arrow-left"></i> Back to Theme & Food
                        </button>
                        <button type="button" class="btn btn-primary" style="background:#db2777; border-color:#db2777;" onclick="goToStep(4)">
                            Review & Live Budget <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ================= STAGE 4: REVIEW & LIVE BUDGET ================= -->
                <div class="wizard-step-pane" id="wizardStep4">
                    <div class="step-card">
                        <h2 class="step-title">4. Review & Live Investment Summary</h2>
                        <p class="step-desc">Verify your celebration details and inspect the live budget breakdown.</p>

                        <div class="review-layout">
                            <div>
                                <h4 style="font-size:16px; font-weight:800; color:var(--text-heading); margin-bottom:12px;">
                                    <i class="fas fa-receipt" style="color:#db2777;"></i> Chosen Birthday Services & Cake Creators
                                </h4>

                                <table class="review-summary-table">
                                    <thead>
                                        <tr>
                                            <th>Service Category</th>
                                            <th>Selected Supplier & Package</th>
                                            <th style="text-align:right;">Est. Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reviewSummaryBody">
                                        <!-- Populated via JS -->
                                    </tbody>
                                </table>

                                <div class="form-group" style="margin-top:25px;">
                                    <label class="form-label" for="extra_details">Custom Cake Writing & Special Requests</label>
                                    <textarea class="form-textarea" id="extra_details" name="extra_details" rows="3" 
                                              placeholder="Wording on birthday cake, dietary restrictions (nut-free, eggless), favorite songs, game preferences..."></textarea>
                                </div>

                                <?php if (!$is_logged_in): ?>
                                    <div style="background:rgba(236, 72, 153, 0.08); border:1px solid rgba(236, 72, 153, 0.2); border-radius:14px; padding:18px; margin-top:20px;">
                                        <h5 style="color:#db2777; font-weight:800; margin-bottom:6px;"><i class="fas fa-user-lock"></i> Quick Guest Booking</h5>
                                        <p style="color:var(--text-muted); font-size:13px; margin-bottom:12px;">
                                            You are booking as a guest. Enter a username to manage this booking, or sign in to link with your account.
                                        </p>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="guest_username">Preferred Username</label>
                                            <input type="text" class="form-input" id="guest_username" name="guest_username" placeholder="e.g. nilushi_bday">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <div class="budget-total-box">
                                    <span style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted);">
                                        Estimated Total Investment
                                    </span>
                                    <div class="total-amount-display" id="displayTotalCost">Rs. 0.00</div>
                                    <p style="font-size:12px; color:var(--text-muted); line-height:1.6; margin:0;">
                                        *Includes catering/dessert estimation for <strong id="displayGuestsCount">0</strong> guests + chosen supplier packages.
                                    </p>

                                    <div style="border-top:1px solid rgba(236, 72, 153, 0.2); padding-top:15px; margin-top:10px;">
                                        <div style="font-size:13px; color:var(--text-heading); font-weight:700; margin-bottom:4px;">
                                            <i class="fas fa-shield-alt" style="color:#10b981;"></i> 100% Guaranteed Punctuality
                                        </div>
                                        <p style="font-size:12px; color:var(--text-muted); margin:0;">
                                            All bakers and decorators are verified EVENTFLARE partners with temperature-controlled delivery.
                                        </p>
                                    </div>

                                    <button type="submit" class="btn btn-primary" style="width:100%; padding:16px; font-size:16px; margin-top:10px; background:#db2777; border-color:#db2777;">
                                        <i class="fas fa-check-circle"></i> Confirm Birthday Booking
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-nav-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(3)">
                            <i class="fas fa-arrow-left"></i> Back to Cakes & Decor
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- JavaScript Stepper Controller & Cost Calculator -->
    <script>
        let currentStep = 1;

        function goToStep(stepNumber) {
            if (currentStep === 1 && stepNumber > 1) {
                const celebrant = document.getElementById('celebrant_name').value.trim();
                const milestone = document.getElementById('milestone_age').value.trim();
                const phone = document.getElementById('contact_phone').value.trim();
                const email = document.getElementById('contact_email').value.trim();
                const date = document.getElementById('event_date').value;
                const guests = parseInt(document.getElementById('guest_count').value);

                if (!celebrant || !milestone || !phone || !email || !date || isNaN(guests) || guests <= 0) {
                    alert('Please fill in all required fields (Celebrant Name, Milestone, Phone, Email, Date, and Expected Guests) before proceeding.');
                    return;
                }
            }

            document.querySelectorAll('.wizard-step-pane').forEach(p => p.classList.remove('active'));
            const targetPane = document.getElementById('wizardStep' + stepNumber);
            if (targetPane) {
                targetPane.classList.add('active');
            }

            for (let i = 1; i <= 4; i++) {
                const node = document.getElementById('stepNode' + i);
                if (!node) continue;
                node.classList.remove('active', 'completed');
                if (i === stepNumber) {
                    node.classList.add('active');
                } else if (i < stepNumber) {
                    node.classList.add('completed');
                }
            }

            const bar = document.getElementById('stepperProgressBar');
            if (bar) {
                const progressPct = ((stepNumber - 1) / 3) * 100;
                bar.style.width = progressPct + '%';
            }

            currentStep = stepNumber;

            if (stepNumber === 4) {
                compileReviewAndCost();
            }

            window.scrollTo({ top: 120, behavior: 'smooth' });
        }

        const styleCards = document.querySelectorAll('.style-card');
        styleCards.forEach(card => {
            card.addEventListener('click', () => {
                styleCards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                const styleName = card.getAttribute('data-style');
                document.getElementById('selectedBirthdayThemeInput').value = styleName;
            });
        });

        const supplierCards = document.querySelectorAll('.supplier-option-card');
        supplierCards.forEach(card => {
            card.addEventListener('click', () => {
                const svcId = card.getAttribute('data-service-id');
                document.querySelectorAll(`.supplier-option-card[data-service-id="${svcId}"]`).forEach(c => {
                    c.classList.remove('selected');
                });
                card.classList.add('selected');
                const radio = card.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });

        function skipService(svcId) {
            document.querySelectorAll(`.supplier-option-card[data-service-id="${svcId}"]`).forEach(c => {
                c.classList.remove('selected');
            });
            const skipRadio = document.getElementById('skip_' + svcId);
            if (skipRadio) skipRadio.checked = true;
        }

        function compileReviewAndCost() {
            const guestCount = parseInt(document.getElementById('guest_count').value) || 80;
            document.getElementById('displayGuestsCount').textContent = guestCount;
            
            const tbody = document.getElementById('reviewSummaryBody');
            tbody.innerHTML = '';

            let totalEstimatedCost = 0;

            const foodTierSelect = document.getElementById('food_tier');
            const foodTierName = foodTierSelect.options[foodTierSelect.selectedIndex].text;
            let perPaxRate = 2200; // default snacks
            if (foodTierName.includes('3,200')) perPaxRate = 3200;
            if (foodTierName.includes('4,200')) perPaxRate = 4200;

            const cateringTotal = perPaxRate * guestCount;
            totalEstimatedCost += cateringTotal;

            const cateringRow = document.createElement('tr');
            cateringRow.innerHTML = `
                <td><strong>Birthday Catering & Treats (Est.)</strong></td>
                <td>${foodTierName.split('(')[0].trim()} &bull; ${guestCount} Guests</td>
                <td class="cost-col">Rs. ${cateringTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            `;
            tbody.appendChild(cateringRow);

            const selectedCards = document.querySelectorAll('.supplier-option-card.selected');
            selectedCards.forEach(card => {
                const svcTitle = card.closest('.service-picker-section').querySelector('.service-picker-title h4').textContent;
                const supName = card.getAttribute('data-supplier-name');
                const pkgTitle = card.getAttribute('data-package-title');
                const price = parseFloat(card.getAttribute('data-price')) || 0;
                const priceType = card.getAttribute('data-price-type');

                let lineCost = price;
                let calcNote = '';
                if (priceType === 'per_person') {
                    lineCost = price * guestCount;
                    calcNote = ` (Rs. ${price.toLocaleString()}/pax &times; ${guestCount})`;
                }

                totalEstimatedCost += lineCost;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${svcTitle}</strong></td>
                    <td>${supName} &mdash; <em>${pkgTitle}</em>${calcNote}</td>
                    <td class="cost-col">Rs. ${lineCost.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('displayTotalCost').textContent = 'Rs. ' + totalEstimatedCost.toLocaleString(undefined, {minimumFractionDigits: 2});
        }
    </script>
</body>
</html>
