<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Enforce mandatory buyer authentication
if (!isset($_SESSION['login_user'])) {
    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    $redirect_target = !empty($current_uri) ? $current_uri : 'events/PartyBooking.php';
    header("Location: ../Login.php?redirect=" . urlencode($redirect_target));
    exit();
}

include_once __DIR__ . '/../../config/database.php';
include __DIR__ . '/../../includes/navbar.php';

// Authenticated buyer details
$logged_user = $_SESSION['login_user'];
$logged_user_id = $_SESSION['user_id'] ?? null;

if (empty($logged_user_id) && isset($conn)) {
    $u_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
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
$pre_vibe    = trim($_GET['vibe'] ?? 'rave');
$pre_venue   = trim($_GET['venue'] ?? '');
$pre_guests  = intval($_GET['guests'] ?? 150);
if ($pre_guests <= 0) $pre_guests = 150;
$pre_pkg_id  = intval($_GET['package_id'] ?? 0);

$booking_success = false;
$created_booking_id = "";
$error_message = "";

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'confirm_party_booking') {
    $party_name     = trim($_POST['party_name'] ?? 'High-Energy Party Celebration');
    $host_name      = trim($_POST['host_name'] ?? '');
    $contact_phone  = trim($_POST['contact_phone'] ?? '');
    $contact_email  = trim($_POST['contact_email'] ?? '');
    $event_date     = trim($_POST['event_date'] ?? '');
    $day_night      = trim($_POST['day_night'] ?? 'Night');
    $place          = trim($_POST['place'] ?? '');
    if (empty($place)) {
        $place = !empty($pre_venue) ? $pre_venue : 'Venue To Be Coordinated by EVENTFLARE';
    }
    $guest_count    = intval($_POST['guest_count'] ?? 150);
    $party_vibe     = trim($_POST['party_vibe'] ?? 'Neon EDM & Festival Rave');
    $food_tier      = trim($_POST['food_tier'] ?? 'Cocktail Tapas & Finger Bites');
    $extra_details  = trim($_POST['extra_details'] ?? '');
    $selected_pkgs  = $_POST['selected_packages'] ?? []; // format: [service_id => listing_id]

    // Customer username strictly bound to authenticated buyer
    $customer_username = $logged_user;

    if (empty($event_date) || $guest_count <= 0) {
        $error_message = "Please complete all required fields (Party Date and Expected Guest count).";
    } elseif (strtotime($event_date) < strtotime('today')) {
        $error_message = "Party date must be today or in the future.";
    } else {
        // Generate Unique Collision-Resistant Booking ID
        $booking_id = "BKG-DJP-" . strtoupper(bin2hex(random_bytes(4)));
        
        $conn->begin_transaction();
        try {
            // 1. Insert into bookings
            $full_notes = "Party: " . $party_name . " | Host: " . $host_name . " | Contact: " . $contact_phone . " (" . $contact_email . ") | Vibe: " . $party_vibe;
            if (!empty($extra_details)) {
                $full_notes .= " | Special Instructions: " . $extra_details;
            }

            $b_stmt = $conn->prepare("INSERT INTO bookings (BookingID, user_id, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails, status) 
                                      VALUES (?, ?, ?, 'DJ Parties', ?, ?, ?, ?, ?, ?, 'pending')");
            $b_stmt->bind_param("sississss", $booking_id, $logged_user_id, $customer_username, $place, $guest_count, $event_date, $day_night, $food_tier, $full_notes);
            if (!$b_stmt->execute()) {
                throw new Exception("Error saving party booking: " . $b_stmt->error);
            }
            $b_stmt->close();

            // 2. Insert into event_extras
            $extra_equip = "Audio/Visual Lighting Rig, Pro DJ Console, Atmosphere Staging";
            $ee_stmt = $conn->prepare("INSERT INTO event_extras (booking_id, equipment, food_style) VALUES (?, ?, ?)");
            $ee_stmt->bind_param("sss", $booking_id, $extra_equip, $food_tier);
            if (!$ee_stmt->execute()) {
                throw new Exception("Error saving party extras: " . $ee_stmt->error);
            }
            $ee_stmt->close();

            // 3. Insert chosen suppliers into booking_services
            if (!empty($selected_pkgs) && is_array($selected_pkgs)) {
                $bs_stmt = $conn->prepare("INSERT INTO booking_services (booking_id, service_id, supplier_id, listing_id, custom_notes, assigned_cost, status) 
                                           VALUES (?, ?, ?, ?, ?, ?, 'confirmed')");
                
                foreach ($selected_pkgs as $svc_id => $listing_id) {
                    $listing_id = intval($listing_id);
                    if ($listing_id <= 0) continue;

                    // Fetch listing pricing details
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

// Fetch all Services for DJ Parties (event_type_id = 4)
$party_services = [];
if (isset($conn) && !$conn->connect_error) {
    $s_res = $conn->query("SELECT * FROM services WHERE event_type_id = 4 ORDER BY priority_rank ASC, service_id ASC");
    if ($s_res) {
        while ($r = $s_res->fetch_assoc()) {
            $party_services[] = $r;
        }
    }
}

// Fetch Listings for each service grouped by service_id
$service_listings = [];
if (!empty($party_services) && isset($conn)) {
    $svc_ids = array_column($party_services, 'service_id');
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
    <title>DJ & Party Booking Wizard - EVENTFLARE</title>
    <style>
        .orb {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, rgba(236, 72, 153, 0.04) 70%);
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

        .stepper-progress-line {
            position: absolute;
            top: 24px;
            left: 50px;
            right: 50px;
            height: 3px;
            background: rgba(255, 255, 255, 0.1);
            z-index: 1;
        }

        .stepper-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary), #ec4899);
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
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.5);
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
            color: var(--primary);
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
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
            background: rgba(255, 255, 255, 0.08);
        }

        /* Vibe Cards */
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
            border-color: rgba(139, 92, 246, 0.5);
            transform: translateY(-4px);
        }

        .style-card.selected {
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.12);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.25);
        }

        .style-card-icon {
            font-size: 32px;
            color: var(--primary);
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
            border-color: rgba(139, 92, 246, 0.4);
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
            color: var(--primary);
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

        /* Review Summary Table */
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
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .total-amount-display {
            font-size: 34px;
            font-weight: 900;
            color: var(--primary);
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
                <span class="badge" style="background:var(--primary); color:#fff; font-size:12px; margin-bottom:12px; text-transform:uppercase;">Booking Verified</span>
                <h1 style="font-size:32px; font-weight:900; color:var(--text-heading); margin-bottom:10px;">Party Booking Confirmed!</h1>
                <p style="color:var(--text-muted); font-size:15px; max-width:600px; margin:0 auto 25px; line-height:1.7;">
                    Your high-energy DJ & Party celebration has been successfully registered. Our event production engineers have been notified to verify stage dimensions and sound rig allocations.
                </p>
                <div style="display:inline-block; background:rgba(255,255,255,0.04); border:1px dashed var(--primary); border-radius:14px; padding:12px 28px; margin-bottom:30px;">
                    <span style="font-size:12px; color:var(--text-muted); text-transform:uppercase;">Reference ID:</span>
                    <h3 style="font-size:24px; font-weight:800; color:var(--primary); margin:2px 0 0;"><?= htmlspecialchars($created_booking_id) ?></h3>
                </div>

                <div style="display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
                    <a href="../MyBookings.php" class="btn btn-primary"><i class="fas fa-calendar-check"></i> View In My Bookings</a>
                    <a href="DjPartySlide.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Return to Party Showcase</a>
                </div>
            </div>
        <?php else: ?>
            <!-- ================= STEPPER HEADER ================= -->
            <div class="stepper-header">
                <div class="stepper-progress-line">
                    <div class="stepper-progress-bar" id="stepperProgressBar"></div>
                </div>

                <div class="step-node active" id="stepNode1" onclick="goToStep(1)">
                    <div class="step-bubble">1</div>
                    <span class="step-label">Fundamentals</span>
                </div>
                <div class="step-node" id="stepNode2" onclick="goToStep(2)">
                    <div class="step-bubble">2</div>
                    <span class="step-label">Party Vibe</span>
                </div>
                <div class="step-node" id="stepNode3" onclick="goToStep(3)">
                    <div class="step-bubble">3</div>
                    <span class="step-label">Choose DJs & Tech</span>
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

            <form method="POST" action="PartyBooking.php" id="partyBookingWizardForm">
                <input type="hidden" name="action" value="confirm_party_booking">
                <input type="hidden" name="party_vibe" id="selectedPartyVibeInput" value="Neon EDM & Festival Rave">

                <!-- ================= STAGE 1: FUNDAMENTALS ================= -->
                <div class="wizard-step-pane active" id="wizardStep1">
                    <div class="step-card">
                        <h2 class="step-title">1. Party Celebration Fundamentals</h2>
                        <p class="step-desc">Enter the primary event title, organizer details, target date, and expected crowd size.</p>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="party_name">Party / Event Name</label>
                                <input type="text" class="form-input" id="party_name" name="party_name" 
                                       placeholder="e.g. Electric Sunset Beach Rave 2026" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="host_name">Host / Lead Organizer Name</label>
                                <input type="text" class="form-input" id="host_name" name="host_name" 
                                       placeholder="e.g. Arun & Friends / Apex Corp" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_phone">Organizer Contact Number</label>
                                <input type="tel" class="form-input" id="contact_phone" name="contact_phone" 
                                       placeholder="+94 77 123 4567" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_email">Organizer Email Address</label>
                                <input type="email" class="form-input" id="contact_email" name="contact_email" 
                                       placeholder="organizer@eventflare.com" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="event_date">Party Date</label>
                                <input type="date" class="form-input" id="event_date" name="event_date" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="day_night">Party Session Timing</label>
                                <select class="form-select" id="day_night" name="day_night">
                                    <option value="Night">Evening Club Bash (8:00 PM – 2:00 AM)</option>
                                    <option value="Day">Sundowner Day Session (3:00 PM – 9:00 PM)</option>
                                </select>
                            </div>

                            <input type="hidden" id="place" name="place" value="<?= htmlspecialchars($pre_venue) ?>">

                            <div class="form-group">
                                <label class="form-label" for="guest_count">Expected Partygoers (Crowd Size)</label>
                                <input type="number" class="form-input" id="guest_count" name="guest_count" 
                                       min="20" max="2500" value="<?= $pre_guests ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-nav-actions">
                        <a href="DjPartySlide.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
                        <button type="button" class="btn btn-primary" onclick="goToStep(2)">
                            Continue to Party Vibe <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ================= STAGE 2: PARTY VIBE ================= -->
                <div class="wizard-step-pane" id="wizardStep2">
                    <div class="step-card">
                        <h2 class="step-title">2. Choose Your Party Vibe & Refreshment Tier</h2>
                        <p class="step-desc">Select the audio-visual atmosphere and hospitality style that best fits your crowd.</p>

                        <label class="form-label" style="margin-bottom:14px;">Party Concept & Musical Vibe</label>
                        <div class="style-cards-grid">
                            <div class="style-card selected" data-style="Neon EDM & Festival Rave">
                                <div class="style-card-icon"><i class="fas fa-bolt"></i></div>
                                <div class="style-card-title">Neon EDM & Rave</div>
                                <div class="style-card-desc">High-power subwoofers, lasers, intelligent beam movers, and club bangers.</div>
                            </div>

                            <div class="style-card" data-style="Rooftop Sunset Lounge & Deep House">
                                <div class="style-card-icon"><i class="fas fa-cloud-sun"></i></div>
                                <div class="style-card-title">Rooftop Sunset Lounge</div>
                                <div class="style-card-desc">Chic melodic deep house, warm ambient lighting, craft mixology, and skyline views.</div>
                            </div>

                            <div class="style-card" data-style="Retro Funk & 90s Throwback">
                                <div class="style-card-icon"><i class="fas fa-compact-disc"></i></div>
                                <div class="style-card-title">Retro 90s Throwback</div>
                                <div class="style-card-desc">Mirror balls, classic pop hits, golden era hip-hop, and nostalgic cocktails.</div>
                            </div>

                            <div class="style-card" data-style="VIP Private Pool & Villa Bash">
                                <div class="style-card-icon"><i class="fas fa-swimming-pool"></i></div>
                                <div class="style-card-title">VIP Villa Pool Bash</div>
                                <div class="style-card-desc">Day-into-night tropical grooves, live percussion, floating lights, and cold beer buckets.</div>
                            </div>
                        </div>

                        <div class="form-group" style="max-width:500px; margin-top:20px;">
                            <label class="form-label" for="food_tier">Party Refreshment & Beverage Tier</label>
                            <select class="form-select" id="food_tier" name="food_tier">
                                <option value="Cocktail Tapas & Finger Bites">Cocktail Tapas & Finger Bites (Rs. 2,500/pax)</option>
                                <option value="Open Bar & Grazing Charcuterie">Open Bar & Grazing Charcuterie (Rs. 3,800/pax)</option>
                                <option value="Late-Night Gourmet Sliders & Street Food">Late-Night Gourmet Sliders & Street Food (Rs. 4,500/pax)</option>
                            </select>
                            <span style="font-size:12px; color:var(--text-muted); display:block; margin-top:6px;">
                                *Live estimator multiplies this per-person rate by your total expected crowd size.
                            </span>
                        </div>
                    </div>

                    <div class="wizard-nav-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                            <i class="fas fa-arrow-left"></i> Back to Fundamentals
                        </button>
                        <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                            Continue to Choose DJs & Tech <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ================= STAGE 3: MULTI-SUPPLIER SELECTION ================= -->
                <div class="wizard-step-pane" id="wizardStep3">
                    <div class="step-card">
                        <h2 class="step-title">3. Handpick Your DJs, Sound & Production Crew</h2>
                        <p class="step-desc">
                            Browse verified equipment rigs and artists for each service category. Choose the specific package you want, or skip services you plan to coordinate independently.
                        </p>

                        <?php if (empty($party_services)): ?>
                            <p style="color:var(--text-muted);">No services currently configured for DJ Parties.</p>
                        <?php else: ?>
                            <?php foreach ($party_services as $svc): ?>
                                <?php 
                                    $svc_id = $svc['service_id'];
                                    $listings = $service_listings[$svc_id] ?? [];
                                ?>
                                <div class="service-picker-section">
                                    <div class="service-picker-title">
                                        <h4>
                                            <i class="fas fa-check-circle" style="color:var(--primary);"></i>
                                            <?= htmlspecialchars($svc['service_name']) ?>
                                        </h4>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="skipService(<?= $svc_id ?>)">
                                            Skip This Service
                                        </button>
                                    </div>
                                    <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px;">
                                        <?= htmlspecialchars($svc['description']) ?>
                                    </p>

                                    <!-- Hidden radio to represent "Skip" -->
                                    <input type="radio" name="selected_packages[<?= $svc_id ?>]" id="skip_<?= $svc_id ?>" value="" checked style="display:none;">

                                    <?php if (empty($listings)): ?>
                                        <div style="padding:15px; background:rgba(255,255,255,0.02); border:1px dashed var(--card-border); border-radius:12px; font-size:13px; color:var(--text-muted);">
                                            No certified suppliers currently listed under this category. Our team will coordinate standard equipment upon booking.
                                        </div>
                                    <?php else: ?>
                                        <div class="supplier-options-grid">
                                            <?php foreach ($listings as $idx => $pkg): ?>
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
                                                        <span class="sup-name"><?= htmlspecialchars($pkg['business_name']) ?></span>
                                                        <input type="radio" name="selected_packages[<?= $svc_id ?>]" 
                                                               value="<?= $pkg['listing_id'] ?>" 
                                                               <?= $is_preselected ? 'checked' : '' ?>
                                                               style="accent-color:#10b981; width:18px; height:18px;">
                                                    </div>

                                                    <div class="sup-pkg-title"><?= htmlspecialchars($pkg['title']) ?></div>

                                                    <div class="sup-price">
                                                        Rs. <?= number_format($pkg['price'], 2) ?>
                                                        <span style="font-size:11px; color:var(--text-muted); font-weight:normal;">
                                                            <?= $pkg['price_type'] === 'per_person' ? '/ person' : 'fixed total' ?>
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
                            <i class="fas fa-arrow-left"></i> Back to Party Vibe
                        </button>
                        <button type="button" class="btn btn-primary" onclick="goToStep(4)">
                            Review & Live Budget <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ================= STAGE 4: REVIEW & LIVE BUDGET ================= -->
                <div class="wizard-step-pane" id="wizardStep4">
                    <div class="step-card">
                        <h2 class="step-title">4. Review & Live Investment Summary</h2>
                        <p class="step-desc">Verify your party celebration details and inspect the real-time cost breakdown.</p>

                        <div class="review-layout">
                            <div>
                                <h4 style="font-size:16px; font-weight:800; color:var(--text-heading); margin-bottom:12px;">
                                    <i class="fas fa-receipt" style="color:var(--primary);"></i> Chosen Party Production & Crew
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
                                        <!-- Populated dynamically via JS -->
                                    </tbody>
                                </table>

                                <div class="form-group" style="margin-top:25px;">
                                    <label class="form-label" for="extra_details">Special Instructions or Custom Music/Lighting Requests</label>
                                    <textarea class="form-textarea" id="extra_details" name="extra_details" rows="3" 
                                              placeholder="Preferred music genres, must-play tracks, curfew considerations, laser preferences, or VIP security instructions..."></textarea>
                                </div>

                                <div class="auth-account-badge" style="background:rgba(139, 92, 246, 0.08); border:1px solid rgba(139, 92, 246, 0.25); border-radius:14px; padding:16px 20px; margin-top:20px; display:flex; align-items:center; gap:14px;">
                                    <div style="width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg, var(--primary), #ec4899); display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; flex-shrink:0;">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; font-weight:700; color:var(--text-muted);">
                                            Booking Account
                                        </div>
                                        <div style="font-size:15px; font-weight:800; color:var(--text-heading);">
                                            <?= htmlspecialchars($logged_user) ?>
                                            <span style="font-size:12px; font-weight:600; color:var(--primary); margin-left:8px; background:rgba(139, 92, 246, 0.12); padding:2px 8px; border-radius:6px;">
                                                <i class="fas fa-check-circle"></i> Authenticated Buyer
                                            </span>
                                        </div>
                                        <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">
                                            This party booking will be automatically linked to your account and managed via <a href="../MyBookings.php" style="color:var(--primary); font-weight:600; text-decoration:underline;">My Bookings</a>.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="budget-total-box">
                                    <span style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted);">
                                        Estimated Total Investment
                                    </span>
                                    <div class="total-amount-display" id="displayTotalCost">Rs. 0.00</div>
                                    <p style="font-size:12px; color:var(--text-muted); line-height:1.6; margin:0;">
                                        *Includes catering/bar estimation for <strong id="displayGuestsCount">0</strong> guests + chosen supplier package rates.
                                    </p>

                                    <div style="border-top:1px solid rgba(139, 92, 246, 0.2); padding-top:15px; margin-top:10px;">
                                        <div style="font-size:13px; color:var(--text-heading); font-weight:700; margin-bottom:4px;">
                                            <i class="fas fa-shield-alt" style="color:#10b981;"></i> Guaranteed Production Quality
                                        </div>
                                        <p style="font-size:12px; color:var(--text-muted); margin:0;">
                                            All engineers and DJs are certified EVENTFLARE partners with back-up generators and contingency gear.
                                        </p>
                                    </div>

                                    <button type="submit" class="btn btn-primary" style="width:100%; padding:16px; font-size:16px; margin-top:10px;">
                                        <i class="fas fa-check-circle"></i> Confirm Party Booking
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-nav-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(3)">
                            <i class="fas fa-arrow-left"></i> Back to Choose DJs & Tech
                        </button>
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
                const partyName = document.getElementById('party_name').value.trim();
                const hostName = document.getElementById('host_name').value.trim();
                const phone = document.getElementById('contact_phone').value.trim();
                const email = document.getElementById('contact_email').value.trim();
                const date = document.getElementById('event_date').value;
                const guests = parseInt(document.getElementById('guest_count').value);

                if (!partyName || !hostName || !phone || !email || !date || isNaN(guests) || guests <= 0) {
                    alert('Please fill in all required fields (Party Title, Host Name, Phone, Email, Date, and Expected Guests) before proceeding.');
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

        // 2. Vibe Card Selection
        const styleCards = document.querySelectorAll('.style-card');
        styleCards.forEach(card => {
            card.addEventListener('click', () => {
                styleCards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                const styleName = card.getAttribute('data-style');
                document.getElementById('selectedPartyVibeInput').value = styleName;
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
            const guestCount = parseInt(document.getElementById('guest_count').value) || 150;
            document.getElementById('displayGuestsCount').textContent = guestCount;
            
            const tbody = document.getElementById('reviewSummaryBody');
            tbody.innerHTML = '';

            let totalEstimatedCost = 0;

            // A. Add Hospitality / Beverage Tier Base Estimate
            const foodTierSelect = document.getElementById('food_tier');
            const foodTierName = foodTierSelect.options[foodTierSelect.selectedIndex].text;
            let perPaxRate = 2500; // default tapas
            if (foodTierName.includes('3,800')) perPaxRate = 3800;
            if (foodTierName.includes('4,500')) perPaxRate = 4500;

            const cateringTotal = perPaxRate * guestCount;
            totalEstimatedCost += cateringTotal;

            const cateringRow = document.createElement('tr');
            cateringRow.innerHTML = `
                <td><strong>Party Hospitality & Bar (Est.)</strong></td>
                <td>${foodTierName.split('(')[0].trim()} &bull; ${guestCount} Pax</td>
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
