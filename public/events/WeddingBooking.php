<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce mandatory buyer authentication
if (!isset($_SESSION['login_user'])) {
    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    $redirect_target = !empty($current_uri) ? $current_uri : 'events/WeddingBooking.php';
    header("Location: ../Login.php?redirect=" . urlencode($redirect_target));
    exit();
}

require_once __DIR__ . '/../../config/database.php';
include __DIR__ . '/../../includes/navbar.php';

// Authenticated buyer details
$logged_user = $_SESSION['login_user'];
$logged_user_id = $_SESSION['user_id'] ?? null;
if (empty($logged_user_id) && isset($conn)) {
    $u_stmt = $conn->prepare("SELECT id, fullname, email FROM users WHERE username = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("s", $logged_user);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($u_row = $u_res->fetch_assoc()) {
            $logged_user_id = (int)$u_row['id'];
            $_SESSION['user_id'] = $logged_user_id;
        }
        $u_stmt->close();
    }
}

// Pre-fill parameters from GET
$pre_style   = trim($_GET['style'] ?? 'poruwa');
$pre_venue   = trim($_GET['venue'] ?? '');
$pre_guests  = intval($_GET['guests'] ?? 200);
if ($pre_guests <= 0) $pre_guests = 200;
$pre_pkg_id  = intval($_GET['package_id'] ?? 0);

$booking_success = false;
$created_booking_id = "";
$error_message = "";

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'confirm_wedding_booking') {
    $bride_groom    = trim($_POST['bride_groom'] ?? '');
    $contact_phone  = trim($_POST['contact_phone'] ?? '');
    $contact_email  = trim($_POST['contact_email'] ?? '');
    $event_date     = trim($_POST['event_date'] ?? '');
    $day_night      = trim($_POST['day_night'] ?? 'Day');
    $place          = trim($_POST['place'] ?? '');
    if (empty($place)) {
        $place = !empty($pre_venue) ? $pre_venue : 'Venue To Be Coordinated';
    }
    $guest_count    = intval($_POST['guest_count'] ?? 100);
    $ceremony_style = trim($_POST['ceremony_style'] ?? 'Royal Traditional Poruwa');
    $food_tier      = trim($_POST['food_tier'] ?? 'Platinum Buffet');
    $extra_details  = trim($_POST['extra_details'] ?? '');
    $selected_pkgs  = $_POST['selected_packages'] ?? []; // format: [service_id => listing_id]

    // Customer username strictly bound to authenticated buyer
    $customer_username = $logged_user;

    if (empty($event_date) || $guest_count <= 0) {
        $error_message = "Please complete all required fields (Date and Guest count).";
    } elseif (strtotime($event_date) < strtotime('today')) {
        $error_message = "Wedding date must be today or in the future.";
    } else {
        // Generate Unique Collision-Resistant Booking ID
        $booking_id = "BKG-WED-" . strtoupper(bin2hex(random_bytes(4)));
        
        $conn->begin_transaction();
        try {
            // 1. Insert into bookings
            $full_notes = "Couple: " . $bride_groom . " | Contact: " . $contact_phone . " (" . $contact_email . ") | Style: " . $ceremony_style;
            if (!empty($extra_details)) {
                $full_notes .= " | Notes: " . $extra_details;
            }

            $b_stmt = $conn->prepare("INSERT INTO bookings (BookingID, user_id, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails, status) 
                                      VALUES (?, ?, ?, 'Weddings', ?, ?, ?, ?, ?, ?, 'pending')");
            $b_stmt->bind_param("sississss", $booking_id, $logged_user_id, $customer_username, $place, $guest_count, $event_date, $day_night, $food_tier, $full_notes);
            if (!$b_stmt->execute()) {
                throw new Exception("Error saving booking: " . $b_stmt->error);
            }
            $b_stmt->close();

            // 2. Insert into event_extras
            $extra_equip = "Ceremonial Stage, Ambient Sound & Audio-Visual Setup";
            $ee_stmt = $conn->prepare("INSERT INTO event_extras (booking_id, equipment, food_style) VALUES (?, ?, ?)");
            $ee_stmt->bind_param("sss", $booking_id, $extra_equip, $food_tier);
            $ee_stmt->execute();
            $ee_stmt->close();

            // 3. Insert chosen supplier packages into booking_services
            if (!empty($selected_pkgs) && is_array($selected_pkgs)) {
                $bs_stmt = $conn->prepare("INSERT INTO booking_services (booking_id, service_id, supplier_id, listing_id, custom_notes, assigned_cost, status) 
                                           VALUES (?, ?, ?, ?, ?, ?, 'confirmed')");
                
                foreach ($selected_pkgs as $svc_id => $pkg_id) {
                    $svc_id = intval($svc_id);
                    $pkg_id = intval($pkg_id);
                    if ($svc_id > 0 && $pkg_id > 0) {
                        // Look up listing details
                        $l_stmt = $conn->prepare("SELECT supplier_id, price, price_type, title FROM supplier_listings WHERE listing_id = ?");
                        $l_stmt->bind_param("i", $pkg_id);
                        $l_stmt->execute();
                        $l_res = $l_stmt->get_result();
                        if ($l_row = $l_res->fetch_assoc()) {
                            $sup_id = (int)$l_row['supplier_id'];
                            $calc_cost = (float)$l_row['price'];
                            if ($l_row['price_type'] === 'per_person') {
                                $calc_cost = $calc_cost * $guest_count;
                            }
                            $notes = "Client Selected Package: " . $l_row['title'];
                            $bs_stmt->bind_param("siiisd", $booking_id, $svc_id, $sup_id, $pkg_id, $notes, $calc_cost);
                            $bs_stmt->execute();
                        }
                        $l_stmt->close();
                    }
                }
                $bs_stmt->close();
            }

            $conn->commit();
            $booking_success = true;
            $created_booking_id = $booking_id;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Fetch Core Wedding Services
$wedding_services = [];
$res_svc = $conn->query("SELECT * FROM services WHERE event_type_id = 1 ORDER BY priority_rank ASC");
if ($res_svc) {
    while ($row = $res_svc->fetch_assoc()) {
        $wedding_services[$row['service_id']] = $row;
    }
}

// Fetch Active Suppliers and their Packages for Weddings
// Grouped by service_id
$suppliers_by_service = [];
$pkg_query = "SELECT sl.*, sup.id as supplier_id, sup.business_name, sup.category, sup.location, sup.contact_phone, sup.logo_url, s.service_name 
              FROM supplier_listings sl
              JOIN suppliers sup ON sl.supplier_id = sup.id
              JOIN services s ON sl.service_id = s.service_id
              WHERE s.event_type_id = 1 AND sl.status = 'active'
              ORDER BY s.priority_rank ASC, sl.price ASC";
$pkg_res = $conn->query($pkg_query);
if ($pkg_res) {
    while ($row = $pkg_res->fetch_assoc()) {
        $sid = $row['service_id'];
        if (!isset($suppliers_by_service[$sid])) {
            $suppliers_by_service[$sid] = [];
        }
        $suppliers_by_service[$sid][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wedding Booking Wizard - EVENTFLARE</title>
    <style>
        /* Ambient Glow Background Orbs */
        .orb {
            position: absolute;
            width: 450px;
            height: 450px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(99, 102, 241, 0) 70%);
            filter: blur(60px);
            z-index: -1;
            pointer-events: none;
        }
        .orb-1 { top: 5%; right: -10%; }
        .orb-2 { top: 40%; left: -12%; }

        .wizard-container {
            max-width: 1100px;
            margin: 100px auto 80px;
            padding: 0 20px;
        }

        /* Progress Stepper Header */
        .stepper-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin-bottom: 45px;
            padding: 0 20px;
        }

        .stepper-progress-line {
            position: absolute;
            top: 24px;
            left: 50px;
            right: 50px;
            height: 3px;
            background: rgba(139, 92, 246, 0.15);
            z-index: 1;
        }

        .stepper-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
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

        .step-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 2px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
            color: var(--text-muted);
            transition: var(--transition-smooth);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .step-node.active .step-circle {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            transform: scale(1.1);
        }

        .step-node.completed .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: #fff;
        }

        .step-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .step-node.active .step-label {
            color: var(--primary);
        }

        /* Wizard Cards */
        .wizard-step-pane {
            display: none;
            animation: fadeInStep 0.4s ease;
        }

        .wizard-step-pane.active {
            display: block;
        }

        @keyframes fadeInStep {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: var(--shadow-premium);
            backdrop-filter: blur(16px);
            margin-bottom: 25px;
        }

        .step-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--text-heading), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .step-desc {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        /* Form Controls */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .full-span {
            grid-column: span 2;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-heading);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .form-input, .form-select, .form-textarea {
            padding: 13px 18px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            background: rgba(255, 255, 255, 0.85);
            color: var(--text-main);
            font-size: 14px;
            font-family: var(--font-body);
            transition: var(--transition-smooth);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
            background: #fff;
        }

        /* Stage 2: Selectable Style Cards */
        .style-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .style-card {
            background: rgba(255, 255, 255, 0.75);
            border: 2px solid var(--card-border);
            border-radius: 18px;
            padding: 24px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition-smooth);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .style-card:hover {
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateY(-4px);
        }

        .style-card.selected {
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.08);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.15);
        }

        .style-icon {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: rgba(139, 92, 246, 0.12);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 14px;
        }

        .style-card.selected .style-icon {
            background: var(--primary);
            color: #fff;
        }

        .style-name {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 6px;
        }

        .style-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Stage 3: Service Categories Accordion / Sections */
        .service-picker-section {
            margin-bottom: 35px;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 25px;
        }

        .service-picker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(139, 92, 246, 0.1);
        }

        .service-picker-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .service-picker-title h4 {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
        }

        .service-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
        }
        .service-badge.req {
            background: rgba(236, 72, 153, 0.12);
            color: #db2777;
            border: 1px solid rgba(236, 72, 153, 0.25);
        }
        .service-badge.opt {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .suppliers-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
        }

        .supplier-option-card {
            background: #fff;
            border: 2px solid var(--card-border);
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: var(--transition-smooth);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .supplier-option-card:hover {
            border-color: rgba(139, 92, 246, 0.35);
            transform: translateY(-3px);
        }

        .supplier-option-card.selected {
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.04);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.12);
        }

        .sup-header-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .sup-logo {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid var(--card-border);
        }

        .sup-name {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
            line-height: 1.3;
        }

        .sup-loc {
            font-size: 12px;
            color: var(--text-muted);
            margin: 0;
        }

        .pkg-title-label {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 6px;
        }

        .pkg-desc-text {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        .pkg-pricing-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            background: rgba(139, 92, 246, 0.06);
            padding: 8px 12px;
            border-radius: 8px;
            margin-top: auto;
        }

        .pkg-cost {
            font-size: 17px;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-heading);
        }

        .pkg-rate-type {
            font-size: 11px;
            color: var(--text-muted);
        }

        .skip-option-row {
            margin-top: 15px;
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--text-muted);
            cursor: pointer;
        }

        /* Stage 4: Review & Live Summary */
        .review-layout {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 30px;
        }

        .review-summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .review-summary-table th, .review-summary-table td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(139, 92, 246, 0.1);
            font-size: 14px;
        }

        .review-summary-table th {
            text-align: left;
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
        }

        .review-summary-table td.cost-col {
            text-align: right;
            font-weight: 700;
            color: var(--primary);
        }

        .budget-total-box {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(99, 102, 241, 0.1));
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 18px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .total-amount-display {
            font-size: 34px;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-heading);
        }

        /* Stepper Navigation Buttons */
        .wizard-nav-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .wizard-nav-actions .btn {
            min-width: 150px;
        }

        /* Success Card */
        .success-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 50px 30px;
            text-align: center;
            box-shadow: var(--shadow-premium);
            max-width: 700px;
            margin: 40px auto;
        }

        .success-icon-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
        }

        .booking-ref-tag {
            display: inline-block;
            background: rgba(139, 92, 246, 0.1);
            border: 1px dashed var(--primary);
            padding: 10px 24px;
            border-radius: 12px;
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-heading);
            letter-spacing: 0.05em;
            margin: 15px 0 25px;
        }

        @media (max-width: 850px) {
            .review-layout {
                grid-template-columns: 1fr;
            }
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
            .full-span {
                grid-column: span 1;
            }
            .stepper-header {
                padding: 0;
            }
            .step-label {
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <!-- Glow Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="wizard-container">
        <?php if ($booking_success): ?>
            <!-- CONFIRMATION SUCCESS VIEW -->
            <div class="success-card">
                <div class="success-icon-badge">
                    <i class="fas fa-check"></i>
                </div>
                <h1 style="font-size:32px; font-weight:800; color:var(--text-heading); margin-bottom:10px;">Wedding Reservation Created!</h1>
                <p style="color:var(--text-muted); font-size:15px; max-width:520px; margin:0 auto;">
                    Congratulations! Your bespoke wedding celebration request and chosen supplier assignments have been successfully registered with our planning office.
                </p>

                <div class="booking-ref-tag">
                    <?= htmlspecialchars($created_booking_id) ?>
                </div>

                <div style="background:rgba(139, 92, 246, 0.05); border:1px solid var(--card-border); border-radius:16px; padding:20px; text-align:left; margin-bottom:30px;">
                    <h4 style="font-size:15px; font-weight:700; color:var(--text-heading); margin-bottom:10px;">Next Steps:</h4>
                    <ul style="color:var(--text-muted); font-size:13px; line-height:1.7; padding-left:20px;">
                        <li>Your dedicated EVENTFLARE wedding coordinator will review your assigned suppliers within 24 hours.</li>
                        <li>You can track vendor confirmation milestones and export your booking dossier as a PDF in your client dashboard.</li>
                        <li>Need to fine-tune your food breakdown? Launch our real-time food budget calculator anytime.</li>
                    </ul>
                </div>

                <div style="display:flex; justify-content:center; gap:15px; flex-wrap:wrap;">
                    <a href="../MyBookings.php" class="btn btn-primary">
                        <i class="fas fa-calendar-alt"></i> View in My Bookings
                    </a>
                    <a href="../Food.php" class="btn btn-secondary">
                        <i class="fas fa-calculator"></i> Refine Catering Budget
                    </a>
                    <a href="WeddingsSlids.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Return to Wedding Showcase
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- WIZARD FORM -->

            <!-- Stepper Progress Navigation -->
            <div class="stepper-header">
                <div class="stepper-progress-line">
                    <div class="stepper-progress-bar" id="stepperProgressBar"></div>
                </div>

                <div class="step-node active" data-step="1" id="stepNode1">
                    <div class="step-circle">1</div>
                    <span class="step-label">Fundamentals</span>
                </div>

                <div class="step-node" data-step="2" id="stepNode2">
                    <div class="step-circle">2</div>
                    <span class="step-label">Ceremony Style</span>
                </div>

                <div class="step-node" data-step="3" id="stepNode3">
                    <div class="step-circle">3</div>
                    <span class="step-label">Choose Suppliers</span>
                </div>

                <div class="step-node" data-step="4" id="stepNode4">
                    <div class="step-circle">4</div>
                    <span class="step-label">Review & Book</span>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div style="background:rgba(220, 38, 38, 0.1); border:1px solid #dc2626; color:#dc2626; border-radius:12px; padding:15px 20px; margin-bottom:25px; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?= htmlspecialchars($error_message) ?></div>
                </div>
            <?php endif; ?>

            <form method="post" action="WeddingBooking.php" id="weddingWizardForm">
                <input type="hidden" name="action" value="confirm_wedding_booking">
                <input type="hidden" name="ceremony_style" id="selectedCeremonyStyleInput" value="Royal Traditional Poruwa">

                <!-- ================= STAGE 1: FUNDAMENTALS ================= -->
                <div class="wizard-step-pane active" id="wizardStep1">
                    <div class="step-card">
                        <h2 class="step-title">1. Couple & Celebration Fundamentals</h2>
                        <p class="step-desc">Let's begin with the essentials of your wedding timeline and location.</p>

                        <div class="form-grid-2">
                            <div class="form-group full-span">
                                <label class="form-label" for="bride_groom">Couple Names (Bride & Groom / Hosts)</label>
                                <input type="text" class="form-input" id="bride_groom" name="bride_groom" 
                                       placeholder="e.g. Kasun Perera & Dilhani Senanayake" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_phone">Primary Contact Phone</label>
                                <input type="tel" class="form-input" id="contact_phone" name="contact_phone" 
                                       placeholder="+94 77 123 4567" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_email">Email Address</label>
                                <input type="email" class="form-input" id="contact_email" name="contact_email" 
                                       placeholder="kasun@example.com" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="event_date">Wedding Date</label>
                                <input type="date" class="form-input" id="event_date" name="event_date" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="day_night">Session Timing</label>
                                <select class="form-select" id="day_night" name="day_night">
                                    <option value="Day">Day Session (Morning Poruwa & Lunch Banquet)</option>
                                    <option value="Night">Evening Session (Twilight Vows & Dinner Gala)</option>
                                </select>
                            </div>

                            <input type="hidden" id="place" name="place" value="<?= htmlspecialchars($pre_venue) ?>">

                            <div class="form-group">
                                <label class="form-label" for="guest_count">Expected Guests (Pax)</label>
                                <input type="number" class="form-input" id="guest_count" name="guest_count" 
                                       min="20" max="1500" value="<?= $pre_guests ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-nav-actions">
                        <a href="WeddingsSlids.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
                        <button type="button" class="btn btn-primary" onclick="goToStep(2)">
                            Continue to Ceremony Style <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ================= STAGE 2: CEREMONY STYLE ================= -->
                <div class="wizard-step-pane" id="wizardStep2">
                    <div class="step-card">
                        <h2 class="step-title">2. Choose Your Ceremony Style</h2>
                        <p class="step-desc">Select the aesthetic that reflects your cultural traditions and personal vision.</p>

                        <div class="style-cards-grid">
                            <div class="style-card selected" data-style="Royal Traditional Poruwa">
                                <div class="style-icon"><i class="fas fa-archway"></i></div>
                                <div class="style-name">Royal Traditional Poruwa</div>
                                <div class="style-desc">Traditional carved Poruwa, Magul Bera drummers, sacred Ashtaka chants, and Jayamangala Gatha blessings.</div>
                            </div>

                            <div class="style-card" data-style="Modern Western Romance">
                                <div class="style-icon"><i class="fas fa-heart"></i></div>
                                <div class="style-name">Modern Western Romance</div>
                                <div class="style-desc">Bespoke floral archway, romantic aisle runners, acoustic music, and a stylish cocktail reception.</div>
                            </div>

                            <div class="style-card" data-style="Coastal & Beachfront Boho">
                                <div class="style-icon"><i class="fas fa-umbrella-beach"></i></div>
                                <div class="style-name">Coastal & Beachfront Boho</div>
                                <div class="style-desc">Driftwood ocean gazebo, sea-breeze floral arrangements, sunset vows, and barefoot beach elegance.</div>
                            </div>

                            <div class="style-card" data-style="Palatial Ballroom Gala">
                                <div class="style-icon"><i class="fas fa-crown"></i></div>
                                <div class="style-name">Palatial Ballroom Gala</div>
                                <div class="style-desc">Grand 5-star crystal chandeliers, intelligent concert uplighting, 4K LED backdrops, and lavish staging.</div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:20px;">
                            <label class="form-label" for="food_tier">Catering & Banquet Tier</label>
                            <select class="form-select" id="food_tier" name="food_tier">
                                <option value="Platinum Wedding Feast (3-Course Buffet)">Platinum 5-Star Buffet (~ Rs. 4,800/pax)</option>
                                <option value="Cocktail & Canapés Evening Reception">Cocktail & Hors d'oeuvres (~ Rs. 3,200/pax)</option>
                                <option value="Executive Multi-Cuisine Plated Feast">Executive Plated Banquet (~ Rs. 3,800/pax)</option>
                            </select>
                            <small style="color:var(--text-muted); margin-top:4px;">
                                You can refine line-item dishes in the <a href="../Food.php" target="_blank" style="color:var(--primary); font-weight:600;">Food Cost Calculator</a>.
                            </small>
                        </div>
                    </div>

                    <div class="wizard-nav-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                            Choose Verified Suppliers <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ================= STAGE 3: CHOOSE SUPPLIERS & PACKAGES ================= -->
                <div class="wizard-step-pane" id="wizardStep3">
                    <div class="step-card">
                        <h2 class="step-title">3. Explore & Select Your Verified Suppliers</h2>
                        <p class="step-desc">
                            Browse all accredited suppliers offering each wedding service. Compare their profiles and select the exact package you want for your special day.
                        </p>

                        <!-- Loop Through Core Wedding Services -->
                        <?php foreach ($wedding_services as $svc_id => $svc): 
                            $is_req = (int)$svc['is_required'];
                            $available_pkgs = $suppliers_by_service[$svc_id] ?? [];
                        ?>
                            <div class="service-picker-section" data-service-id="<?= $svc_id ?>">
                                <div class="service-picker-header">
                                    <div class="service-picker-title">
                                        <h4><?= htmlspecialchars($svc['service_name']) ?></h4>
                                        <span class="service-badge <?= $is_req ? 'req' : 'opt' ?>">
                                            <?= $is_req ? 'Mandatory Pillar' : 'Optional Add-on' ?>
                                        </span>
                                    </div>
                                    <span style="font-size:12px; color:var(--text-muted);">
                                        <?= count($available_pkgs) ?> Option(s) Available
                                    </span>
                                </div>

                                <?php if (!empty($available_pkgs)): ?>
                                    <div class="suppliers-options-grid">
                                        <?php foreach ($available_pkgs as $p_idx => $pkg): 
                                            // Auto-select first if mandatory or pre_selected
                                            $is_pre = ($pkg['listing_id'] == $pre_pkg_id) || ($is_req && $p_idx === 0);
                                        ?>
                                            <div class="supplier-option-card <?= $is_pre ? 'selected' : '' ?>" 
                                                 data-service-id="<?= $svc_id ?>"
                                                 data-listing-id="<?= $pkg['listing_id'] ?>"
                                                 data-supplier-id="<?= $pkg['supplier_id'] ?>"
                                                 data-supplier-name="<?= htmlspecialchars($pkg['business_name']) ?>"
                                                 data-package-title="<?= htmlspecialchars($pkg['title']) ?>"
                                                 data-price="<?= (float)$pkg['price'] ?>"
                                                 data-price-type="<?= htmlspecialchars($pkg['price_type']) ?>">
                                                
                                                <div class="sup-header-info">
                                                    <img src="<?= !empty($pkg['logo_url']) ? htmlspecialchars($pkg['logo_url']) : '../assets/images/logo.jpg' ?>" 
                                                         alt="<?= htmlspecialchars($pkg['business_name']) ?>" class="sup-logo">
                                                    <div>
                                                        <h5 class="sup-name"><?= htmlspecialchars($pkg['business_name']) ?></h5>
                                                        <p class="sup-loc"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($pkg['location']) ?></p>
                                                    </div>
                                                </div>

                                                <div class="pkg-title-label"><?= htmlspecialchars($pkg['title']) ?></div>
                                                <div class="pkg-desc-text"><?= htmlspecialchars($pkg['description']) ?></div>

                                                <div class="pkg-pricing-row">
                                                    <span class="pkg-cost">Rs. <?= number_format($pkg['price'], 2) ?></span>
                                                    <span class="pkg-rate-type">/ <?= str_replace('_', ' ', htmlspecialchars($pkg['price_type'])) ?></span>
                                                </div>

                                                <input type="radio" 
                                                       name="selected_packages[<?= $svc_id ?>]" 
                                                       value="<?= $pkg['listing_id'] ?>" 
                                                       style="display:none;" 
                                                       <?= $is_pre ? 'checked' : '' ?>>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if (!$is_req): ?>
                                        <div class="skip-option-row" onclick="skipService(<?= $svc_id ?>)">
                                            <input type="radio" name="selected_packages[<?= $svc_id ?>]" value="0" id="skip_<?= $svc_id ?>">
                                            <label for="skip_<?= $svc_id ?>" style="cursor:pointer;">Do not require this service / Add later</label>
                                        </div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <p style="font-size:13px; color:var(--text-muted); font-style:italic;">
                                        EVENTFLARE coordinator will auto-match our verified partner for this service upon reservation.
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="wizard-nav-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn btn-primary" onclick="goToStep(4)">
                            Review & Live Budget <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ================= STAGE 4: REVIEW & LIVE BUDGET SUMMARY ================= -->
                <div class="wizard-step-pane" id="wizardStep4">
                    <div class="step-card">
                        <h2 class="step-title">4. Review & Live Investment Summary</h2>
                        <p class="step-desc">Verify your wedding celebration details and inspect the real-time cost breakdown.</p>

                        <div class="review-layout">
                            <!-- Selected Details & Suppliers Table -->
                            <div>
                                <h4 style="font-size:16px; font-weight:800; color:var(--text-heading); margin-bottom:12px;">
                                    <i class="fas fa-receipt" style="color:var(--primary);"></i> Chosen Wedding Services & Suppliers
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
                                        <!-- Dynamically Populated by JavaScript -->
                                    </tbody>
                                </table>

                                <div class="form-group" style="margin-top:25px;">
                                    <label class="form-label" for="extra_details">Special Instructions or Custom Requests</label>
                                    <textarea class="form-textarea" id="extra_details" name="extra_details" rows="3" 
                                              placeholder="Any dietary restrictions, special songs, cultural timing preferences, or venue access requests..."></textarea>
                                </div>

                                <div class="auth-account-badge" style="background:rgba(236, 72, 153, 0.08); border:1px solid rgba(236, 72, 153, 0.25); border-radius:14px; padding:16px 20px; margin-top:20px; display:flex; align-items:center; gap:14px;">
                                    <div style="width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg, #ec4899, #8b5cf6); display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; flex-shrink:0;">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; font-weight:700; color:var(--text-muted);">
                                            Booking Account
                                        </div>
                                        <div style="font-size:15px; font-weight:800; color:var(--text-heading);">
                                            <?= htmlspecialchars($logged_user) ?>
                                            <span style="font-size:12px; font-weight:600; color:#db2777; margin-left:8px; background:rgba(236, 72, 153, 0.12); padding:2px 8px; border-radius:6px;">
                                                <i class="fas fa-check-circle"></i> Authenticated Buyer
                                            </span>
                                        </div>
                                        <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">
                                            This wedding booking will be automatically linked to your account and managed via <a href="../MyBookings.php" style="color:#db2777; font-weight:600; text-decoration:underline;">My Bookings</a>.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Budget Display Column -->
                            <div>
                                <div class="budget-total-box">
                                    <span style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted);">
                                        Estimated Total Investment
                                    </span>
                                    <div class="total-amount-display" id="displayTotalCost">Rs. 0.00</div>
                                    <p style="font-size:12px; color:var(--text-muted); line-height:1.6; margin:0;">
                                        *Includes catering estimation for <strong id="displayGuestsCount">0</strong> guests + chosen supplier package rates. Final invoicing subject to coordinator review.
                                    </p>

                                    <div style="border-top:1px solid rgba(139, 92, 246, 0.2); padding-top:15px; margin-top:10px;">
                                        <div style="font-size:13px; color:var(--text-heading); font-weight:700; margin-bottom:4px;">
                                            <i class="fas fa-shield-alt" style="color:var(--success);"></i> 100% Guaranteed Quality
                                        </div>
                                        <p style="font-size:12px; color:var(--text-muted); margin:0;">
                                            All assigned vendors are certified EVENTFLARE partners with verified service agreements.
                                        </p>
                                    </div>

                                    <button type="submit" class="btn btn-primary" style="width:100%; padding:16px; font-size:16px; margin-top:10px;">
                                        <i class="fas fa-check-circle"></i> Confirm Wedding Booking
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-nav-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(3)"><i class="fas fa-arrow-left"></i> Back to Suppliers</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- JavaScript Stepper Controller & Cost Calculator -->
    <script>
        let currentStep = 1;

        // 1. Navigation Controller
        function goToStep(stepNumber) {
            // Validate before leaving step 1
            if (currentStep === 1 && stepNumber > 1) {
                const brideGroom = document.getElementById('bride_groom').value.trim();
                const phone = document.getElementById('contact_phone').value.trim();
                const email = document.getElementById('contact_email').value.trim();
                const date = document.getElementById('event_date').value;
                const guests = parseInt(document.getElementById('guest_count').value);

                if (!brideGroom || !phone || !email || !date || isNaN(guests) || guests <= 0) {
                    alert('Please fill in all required fields (Couple Names, Phone, Email, Date, and Guests) before proceeding.');
                    return;
                }
            }

            // Switch panes
            document.querySelectorAll('.wizard-step-pane').forEach(p => p.classList.remove('active'));
            const targetPane = document.getElementById('wizardStep' + stepNumber);
            if (targetPane) {
                targetPane.classList.add('active');
            }

            // Update Stepper Header Nodes
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

            // Update progress bar
            const bar = document.getElementById('stepperProgressBar');
            if (bar) {
                const progressPct = ((stepNumber - 1) / 3) * 100;
                bar.style.width = progressPct + '%';
            }

            currentStep = stepNumber;

            // If entering review step, calculate budget
            if (stepNumber === 4) {
                compileReviewAndCost();
            }

            window.scrollTo({ top: 120, behavior: 'smooth' });
        }

        // 2. Ceremony Style Selection
        const styleCards = document.querySelectorAll('.style-card');
        styleCards.forEach(card => {
            card.addEventListener('click', () => {
                styleCards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                const styleName = card.getAttribute('data-style');
                document.getElementById('selectedCeremonyStyleInput').value = styleName;
            });
        });

        // 3. Supplier Card Selection
        const supplierCards = document.querySelectorAll('.supplier-option-card');
        supplierCards.forEach(card => {
            card.addEventListener('click', () => {
                const svcId = card.getAttribute('data-service-id');
                // Unselect other cards in same service
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

        // 4. Live Budget Compilation
        function compileReviewAndCost() {
            const guestCount = parseInt(document.getElementById('guest_count').value) || 100;
            document.getElementById('displayGuestsCount').textContent = guestCount;
            
            const tbody = document.getElementById('reviewSummaryBody');
            tbody.innerHTML = '';

            let totalEstimatedCost = 0;

            // A. Add Catering Tier Base Estimate
            const foodTierSelect = document.getElementById('food_tier');
            const foodTierName = foodTierSelect.options[foodTierSelect.selectedIndex].text;
            let perPaxRate = 4800; // default platinum
            if (foodTierName.includes('3,200')) perPaxRate = 3200;
            if (foodTierName.includes('3,800')) perPaxRate = 3800;

            const cateringTotal = perPaxRate * guestCount;
            totalEstimatedCost += cateringTotal;

            const cateringRow = document.createElement('tr');
            cateringRow.innerHTML = `
                <td><strong>Wedding Catering (Est.)</strong></td>
                <td>Grand Royal Banquet (${foodTierName.split('(')[0].trim()}) &bull; ${guestCount} Pax</td>
                <td class="cost-col">Rs. ${cateringTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            `;
            tbody.appendChild(cateringRow);

            // B. Add Chosen Supplier Packages
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

            // Update Total
            document.getElementById('displayTotalCost').textContent = 'Rs. ' + totalEstimatedCost.toLocaleString(undefined, {minimumFractionDigits: 2});
        }
    </script>
</body>
</html>
