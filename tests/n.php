<?php
// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "login";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to generate the next serial number
function generateSerialNumber($conn) {
    // Fetch the last serial number from the database
    $result = $conn->query("SELECT last_serial_number FROM serial_numbers WHERE id = 1");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastSerialNumber = $row['last_serial_number'];
    } else {
        $lastSerialNumber = 0;
        $conn->query("INSERT INTO serial_numbers (id, last_serial_number) VALUES (1, 0)");
    }

    // Increment the serial number
    $newSerialNumber = $lastSerialNumber + 1;

    // Update the last serial number in the database
    $conn->query("UPDATE serial_numbers SET last_serial_number = $newSerialNumber WHERE id = 1");

    // Return the new serial number (formatted with leading zeros)
    return str_pad($newSerialNumber, 2, '0', STR_PAD_LEFT);
}

// Generate Booking ID
$bookingId = generateSerialNumber($conn);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eventType = $_POST['EventType'];
    $place = $_POST['Place'];
    $numberOfGuests = intval($_POST['NumberOfGuests']);
    $eventDate = $_POST['EventDate'];
    $dayNight = $_POST['DayNight'];
    $foodPreferences = $_POST['FoodPreferences'];
    $extraDetails = $_POST['ExtraDetails'];

    // Prepared statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO bookings (BookingID, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisssss", $bookingId, $eventType, $place, $numberOfGuests, $eventDate, $dayNight, $foodPreferences, $extraDetails);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>Booking successfully created!</p>";
    } else {
        echo "<p style='color: red;'>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventFlare Booking</title>
    <link rel="stylesheet" href="Booking.css">
</head>
<body>
    <div class="container">
        <div class="form-section">
            <div class="logo">
                <img src="eventflare-logo.png" alt="EventFlare Logo">
            </div>
            <form class="booking-form" method="post" action="">
                <label for="booking-id">Booking ID</label>
                <input type="text" id="booking-id" name="booking-id" value="<?php echo $bookingId; ?>" readonly>

                <label for="EventType">Event Type</label>
                <select id="EventType" name="EventType">
                    <option>Select</option>
                    <option>Wedding</option>
                    <option>Party</option>
                </select>

                <label for="Place">Place</label>
                <input type="text" id="Place" placeholder="Enter Place" name="Place">

                <label for="NumberOfGuests">No. of Guests</label>
                <input type="number" id="NumberOfGuests" placeholder="0" name="NumberOfGuests">

                <label for="EventDate">Date</label>
                <input type="date" id="EventDate" name="EventDate">

                <label for="DayNight">Day/Night</label>
                <select id="DayNight" name="DayNight">
                    <option>Select</option>
                    <option>Day</option>
                    <option>Night</option>
                </select>

                <label for="FoodPreferences">Food</label>
                <select id="FoodPreferences" name="FoodPreferences">
                    <option>Select</option>
                    <option>High price</option>
                    <option>Middle price</option>
                    <option>Low Price</option>
                </select>

                <label for="ExtraDetails">Extra Details</label>
                <textarea id="details" name="ExtraDetails" placeholder="Enter extra details"></textarea>

                <button type="submit" class="book-btn">Book</button>
            </form>
        </div>
        <div class="image-section">
            <img src="istockphoto-1483833011-612x612.jpg" alt="DJ Event">
        </div>
    </div>
</body>
</html>
