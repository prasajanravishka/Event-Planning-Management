<?php
session_start();
include __DIR__ . '/../config/database.php';

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $bookingId = trim(htmlspecialchars($_POST['bookingId']));
    $equipment = $_POST['equipment'] ?? '';
    $foodStyle = $_POST['food'] ?? '';
    $place = trim(htmlspecialchars($_POST['place']));
    $noOfGuests = intval($_POST['noOfGuests']);

    // 1. Verify if booking ID exists
    $check_stmt = $conn->prepare("SELECT BookingID FROM bookings WHERE BookingID = ?");
    $check_stmt->bind_param("s", $bookingId);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows == 0) {
        $error_message = "Booking ID '$bookingId' does not exist. Please create a booking first.";
    } else {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Update booking details
            $update_stmt = $conn->prepare("UPDATE bookings SET Place = ?, NumberOfGuests = ? WHERE BookingID = ?");
            $update_stmt->bind_param("sis", $place, $noOfGuests, $bookingId);
            $update_stmt->execute();
            $update_stmt->close();

            // Insert or update event extras
            $extras_stmt = $conn->prepare("INSERT INTO event_extras (booking_id, equipment, food_style) VALUES (?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE equipment = ?, food_style = ?");
            $extras_stmt->bind_param("sssss", $bookingId, $equipment, $foodStyle, $equipment, $foodStyle);
            $extras_stmt->execute();
            $extras_stmt->close();

            $conn->commit();
            $success_message = "Booking details updated successfully for Booking ID: <strong>$bookingId</strong>";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Failed to update booking: " . $e->getMessage();
        }
    }
    $check_stmt->close();
}
$conn->close();

include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Booking Addons - EventEase</title>
    <style>
        .event-wrapper {
            max-width: 600px;
            margin: 40px auto 80px;
            padding: 0 20px;
        }

        .event-card h1 {
            font-size: 32px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .event-card p.subtitle {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        .submit-btn-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .submit-btn-container .btn {
            flex: 1;
        }

        .calc-btn-container {
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="event-wrapper">
        <div class="glass-card event-card">
            <h1>Manage Booking Addons</h1>
            <p class="subtitle">Customize equipment, food style, and guest count for your active booking.</p>

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

            <form id="bookingForm" method="POST" action="Event.php">
                <div class="form-group">
                    <label class="form-label" for="bookingId">Booking ID</label>
                    <input type="text" id="bookingId" class="form-input" name="bookingId" placeholder="e.g. BKG168941..." required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="equipment">Equipment Addons</label>
                    <select id="equipment" class="form-input" name="equipment" required>
                        <option value="">--Select Equipment--</option>
                        <option value="Sound System">Sound System</option>
                        <option value="Lighting">Lighting Setup</option>
                        <option value="Projector">Projector & Screen</option>
                        <option value="Full AV Setup">Full AV Setup (All)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="place">Venue Location</label>
                    <input type="text" id="place" class="form-input" name="place" placeholder="Confirm venue location" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="food">Food Service Style</label>
                    <select id="food" class="form-input" name="food" required>
                        <option value="">--Select Food Style--</option>
                        <option value="Buffet">Buffet Service</option>
                        <option value="Plated">Plated Dinner</option>
                        <option value="Family Style">Family Style Sharing</option>
                    </select>
                    <div class="calc-btn-container">
                        <a href="Food.php" class="btn btn-secondary" style="font-size:12px; padding: 6px 14px; width: 100%; border-radius: 8px;">
                            <i class="fas fa-calculator"></i> Open Food Budget Calculator
                        </a>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="noOfGuests">No. of Guests</label>
                    <input type="number" id="noOfGuests" class="form-input" name="noOfGuests" min="1" placeholder="Enter guest count" required>
                </div>

                <div class="submit-btn-container">
                    <button type="submit" name="submit" class="btn btn-primary">Update Booking Details</button>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>