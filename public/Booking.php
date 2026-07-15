<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    header("Location: Login.php");
    exit();
}
include __DIR__ . '/../config/database.php';

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_name = $_POST['user_name'] ?? '';
    $eventType = $_POST['EventType'] ?? '';
    $place = $_POST['Place'] ?? '';
    $numberOfGuests = intval($_POST['NumberOfGuests'] ?? 0);
    $eventDate = $_POST['EventDate'] ?? '';
    $dayNight = $_POST['DayNight'] ?? '';
    $foodPreferences = $_POST['FoodPreferences'] ?? '';
    $extraDetails = $_POST['ExtraDetails'] ?? '';

    if (empty($user_name)) {
        $error_message = "User Name is required.";
    } else {
        $bookingID = "BKG" . time(); 
        
        $stmt = $conn->prepare("INSERT INTO bookings (BookingID, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("sssisssss", $bookingID, $user_name, $eventType, $place, $numberOfGuests, $eventDate, $dayNight, $foodPreferences, $extraDetails);
            
            if ($stmt->execute()) {
                $success_message = "Booking successfully created! Your Booking ID is <strong>$bookingID</strong>";
            } else {
                $error_message = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "SQL Error: " . $conn->error;
        }
    }
}
$conn->close();

include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Event - EventEase</title>
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
                        <option value="Wedding">Wedding</option>
                        <option value="Party">Party</option>
                        <option value="Hotel Venue">Hotel Venue</option>
                        <option value="Birthday">Birthday</option>
                        <option value="Get Together">Get Together</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="Place">Venue Location</label>
                    <input type="text" id="Place" class="form-input" placeholder="City or Hotel Name" name="Place" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="NumberOfGuests">Expected Guests</label>
                    <input type="number" id="NumberOfGuests" class="form-input" placeholder="0" name="NumberOfGuests" min="1" required>
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
                    <textarea id="details" class="form-input" style="height:100px; resize:none;" name="ExtraDetails" placeholder="Add extra requirements..."></textarea>
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
</body>
</html>