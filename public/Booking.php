<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    $redirect_target = !empty($current_uri) ? $current_uri : 'Booking.php';
    header("Location: Login.php?redirect=" . urlencode($redirect_target));
    exit();
}
include __DIR__ . '/../config/database.php';

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Use trim only — htmlspecialchars applied on output, not storage
    $user_name = trim($_POST['user_name'] ?? '');
    $eventType = trim($_POST['EventType'] ?? '');
    $place = trim($_POST['Place'] ?? '');
    $numberOfGuests = intval($_POST['NumberOfGuests'] ?? 0);
    $eventDate = trim($_POST['EventDate'] ?? '');
    $dayNight = trim($_POST['DayNight'] ?? '');
    $foodPreferences = trim($_POST['FoodPreferences'] ?? '');
    $extraDetails = trim($_POST['ExtraDetails'] ?? '');

    if (empty($user_name)) {
        $error_message = "User Name is required.";
    } elseif (!empty($eventDate) && strtotime($eventDate) < strtotime('today')) {
        $error_message = "Event date must be today or in the future.";
    } else {
        // Use uniqid to prevent collision (time-based IDs collide within the same second)
        $bookingID = "BKG" . strtoupper(uniqid());
        
        $stmt = $conn->prepare("INSERT INTO bookings (BookingID, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("sssisssss", $bookingID, $user_name, $eventType, $place, $numberOfGuests, $eventDate, $dayNight, $foodPreferences, $extraDetails);
            
            if ($stmt->execute()) {
                $success_message = "Booking successfully created! Your Booking ID is <strong>" . htmlspecialchars($bookingID) . "</strong>";
            } else {
                $error_message = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "SQL Error: " . $conn->error;
        }
    }
}

// Fetch event types purely from database for dropdown
$event_types_result = $conn->query("SELECT type_name FROM event_types WHERE is_active = 1 ORDER BY event_type_id");
$event_types = [];
if ($event_types_result) {
    while ($et = $event_types_result->fetch_assoc()) {
        $event_types[] = $et['type_name'];
    }
}

// Support URL parameters for pre-selection (e.g. from Wedding Plan page or packages)
$pre_event   = trim($_GET['event'] ?? '');
$pre_venue   = trim($_GET['venue'] ?? '');
$pre_guests  = intval($_GET['guests'] ?? 0);
$pre_package = trim($_GET['package'] ?? '');
$pre_details = trim($_GET['details'] ?? '');
if (!empty($pre_package) && empty($pre_details)) {
    $pre_details = "Interested in Package: " . $pre_package;
}

include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Event - EVENTFLARE</title>
    <style>
        .booking-wrapper {
            max-width: 1100px;
            margin: 40px auto 80px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 40px;
        }

        .booking-container h1 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .booking-container p.subtitle {
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        .booking-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .full-width {
            grid-column: span 2;
        }

        .calc-link {
            font-size: 13px;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }

        .calc-link:hover {
            color: var(--text-white);
        }

        .submit-btn-container {
            margin-top: 15px;
        }

        .image-banner {
            background-image: linear-gradient(rgba(10, 8, 19, 0.2), rgba(10, 8, 19, 0.85)), url(assets/images/evnt.jpg);
            background-size: cover;
            background-position: center;
            border-radius: 20px;
            border: 1px solid var(--card-border);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 40px;
            box-shadow: var(--shadow-premium);
            min-height: 450px;
        }

        .image-banner h2 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .image-banner p {
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.6;
        }

        @media (max-width: 900px) {
            .booking-wrapper {
                grid-template-columns: 1fr;
            }
            .image-banner {
                min-height: 300px;
                order: -1;
            }
        }
        @media (max-width: 600px) {
            .booking-form {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="booking-wrapper">
        <div class="glass-card booking-container">
            <h1>Book Your Celebration</h1>
            <p class="subtitle">Enter your event details to secure your booking instantly.</p>

            <?php if (!empty($error_message)): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success_message; ?></div>
                </div>
            <?php endif; ?>

            <?php if (strcasecmp($pre_event, 'Weddings') === 0): ?>
                <div style="background:linear-gradient(135deg, rgba(139, 92, 246, 0.12), rgba(236, 72, 153, 0.1)); border:1px solid rgba(139, 92, 246, 0.3); border-radius:14px; padding:18px 20px; margin-bottom:25px; display:flex; align-items:center; justify-content:space-between; gap:15px; flex-wrap:wrap;">
                    <div>
                        <strong style="color:var(--primary); font-size:14px; display:block; margin-bottom:3px;"><i class="fas fa-gem"></i> Planning a Wedding?</strong>
                        <span style="font-size:13px; color:var(--text-muted);">Try our specialized 4-stage Wedding Wizard to browse and handpick your caterers, florists, and cinematographers with live cost estimation!</span>
                    </div>
                    <a href="events/WeddingBooking.php" class="btn btn-primary" style="padding:8px 18px; font-size:13px; white-space:nowrap;">
                        Launch Wedding Wizard <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php elseif (stripos($pre_event, 'DJ') !== false || stripos($pre_event, 'Party') !== false): ?>
                <div style="background:linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(236, 72, 153, 0.08)); border:1px solid rgba(139, 92, 246, 0.3); border-radius:14px; padding:18px 20px; margin-bottom:25px; display:flex; align-items:center; justify-content:space-between; gap:15px; flex-wrap:wrap;">
                    <div>
                        <strong style="color:var(--primary); font-size:14px; display:block; margin-bottom:3px;"><i class="fas fa-bolt"></i> Planning a DJ & Party Event?</strong>
                        <span style="font-size:13px; color:var(--text-muted);">Try our specialized 4-stage DJ Party Wizard to select lighting rigs, pro DJs, and mobile cocktail bars with live pricing!</span>
                    </div>
                    <a href="events/PartyBooking.php" class="btn btn-primary" style="padding:8px 18px; font-size:13px; white-space:nowrap;">
                        Launch DJ Party Wizard <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php elseif (stripos($pre_event, 'Birth') !== false): ?>
                <div style="background:linear-gradient(135deg, rgba(236, 72, 153, 0.12), rgba(139, 92, 246, 0.08)); border:1px solid rgba(236, 72, 153, 0.3); border-radius:14px; padding:18px 20px; margin-bottom:25px; display:flex; align-items:center; justify-content:space-between; gap:15px; flex-wrap:wrap;">
                    <div>
                        <strong style="color:#db2777; font-size:14px; display:block; margin-bottom:3px;"><i class="fas fa-birthday-cake"></i> Planning a Birthday?</strong>
                        <span style="font-size:13px; color:var(--text-muted);">Try our specialized 4-stage Birthday Wizard to choose custom 3D cakes, themed balloon backdrops, and magicians!</span>
                    </div>
                    <a href="events/BirthdayBooking.php" class="btn btn-primary" style="background:#db2777; border-color:#db2777; padding:8px 18px; font-size:13px; white-space:nowrap;">
                        Launch Birthday Wizard <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php elseif (stripos($pre_event, 'Together') !== false || stripos($pre_event, 'Reunion') !== false): ?>
                <div style="background:linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(139, 92, 246, 0.08)); border:1px solid rgba(16, 185, 129, 0.3); border-radius:14px; padding:18px 20px; margin-bottom:25px; display:flex; align-items:center; justify-content:space-between; gap:15px; flex-wrap:wrap;">
                    <div>
                        <strong style="color:#10b981; font-size:14px; display:block; margin-bottom:3px;"><i class="fas fa-handshake"></i> Planning a Get Together or Reunion?</strong>
                        <span style="font-size:13px; color:var(--text-muted);">Try our specialized 4-stage Reunion Wizard to handpick live BBQ grills, marquee tents, and acoustic sing-along acts!</span>
                    </div>
                    <a href="events/GetTogetherBooking.php" class="btn btn-primary" style="background:#10b981; border-color:#10b981; padding:8px 18px; font-size:13px; white-space:nowrap;">
                        Launch Reunion Wizard <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>

            <form class="booking-form" method="post" action="Booking.php">
                <div class="form-group full-width">
                    <label class="form-label" for="user_name">Customer Username</label>
                    <input type="text" id="user_name" class="form-input" placeholder="e.g. johndoe" name="user_name" 
                           value="<?php echo isset($_SESSION['login_user']) ? htmlspecialchars($_SESSION['login_user']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="EventType">Event Type</label>
                    <select id="EventType" class="form-input" name="EventType" required>
                        <option value="">Select category</option>
                        <?php foreach ($event_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= (strcasecmp($pre_event, $type) === 0) ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="Place">Venue Location</label>
                    <input type="text" id="Place" class="form-input" placeholder="City or Hotel Name" name="Place" 
                           value="<?= !empty($pre_venue) ? htmlspecialchars($pre_venue) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="NumberOfGuests">Expected Guests</label>
                    <input type="number" id="NumberOfGuests" class="form-input" placeholder="0" name="NumberOfGuests" min="1" 
                           value="<?= ($pre_guests > 0) ? htmlspecialchars($pre_guests) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="EventDate">Event Date</label>
                    <input type="date" id="EventDate" class="form-input" name="EventDate" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="DayNight">Day or Night</label>
                    <select id="DayNight" class="form-input" name="DayNight" required>
                        <option value="">Select time</option>
                        <option value="Day">Day Session</option>
                        <option value="Night">Night Session</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="FoodPreferences">Food Pricing Tier</label>
                    <select id="FoodPreferences" class="form-input" name="FoodPreferences" required>
                        <option value="">Select pricing</option>
                        <option value="High price">High Tier</option>
                        <option value="Middle price">Medium Tier</option>
                        <option value="Low Price">Economy Tier</option>
                    </select>
                    <a href="Food.php" class="calc-link" target="_blank">
                        <i class="fas fa-calculator"></i> Use Food Cost Calculator
                    </a>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="details">Special Instructions & Details</label>
                    <textarea id="details" class="form-input" style="height:100px; resize:none;" name="ExtraDetails" placeholder="Add extra requirements..."><?= !empty($pre_details) ? htmlspecialchars($pre_details) : '' ?></textarea>
                </div>
                
                <div class="submit-btn-container full-width">
                    <button type="submit" class="btn btn-primary" style="width:100%;">Confirm Event Booking</button>
                </div>
            </form>
        </div>

        <div class="image-banner">
            <h2>Experience Perfection</h2>
            <p>From private birthday parties to grand wedding receptions, we handle all logistics. Our platform keeps your scheduling, budget metrics, and coordinator connection completely streamlined.</p>
        </div>
    </div>
<?php $conn->close(); ?>
</body>
</html>
