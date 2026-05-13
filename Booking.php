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

// Initialize variables
$bookingID = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data safely
    $user_name = $_POST['user_name'] ?? '';
    $eventType = $_POST['EventType'] ?? '';
    $place = $_POST['Place'] ?? '';
    $numberOfGuests = intval($_POST['NumberOfGuests'] ?? 0);
    $eventDate = $_POST['EventDate'] ?? '';
    $dayNight = $_POST['DayNight'] ?? '';
    $foodPreferences = $_POST['FoodPreferences'] ?? '';
    $extraDetails = $_POST['ExtraDetails'] ?? '';

    // Validate user_name (to prevent null values)
    if (empty($user_name)) {
        die("<p style='color: red;'>Error: User Name is required.</p>");
    }

    // Generate a unique Booking ID
    $bookingID = "BKG" . time(); // Dynamic Booking ID based on the current timestamp

    // Prepare SQL statement
    $stmt = $conn->prepare("INSERT INTO bookings (BookingID, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        // Bind parameters and execute
        $stmt->bind_param("sssisssss", $bookingID, $user_name, $eventType, $place, $numberOfGuests, $eventDate, $dayNight, $foodPreferences, $extraDetails);
        
        // Execute the statement
        if ($stmt->execute()) {
            echo "<p style='color: green;'>Booking successfully created! Your Booking ID is <b>$bookingID</b></p>";
        } else {
            echo "<p style='color: red;'>Error: " . $stmt->error . "</p>";
        }

        // Close the statement
        $stmt->close();
    } else {
        die("SQL Error: " . $conn->error);
    }
}

// Close connection
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EventFlare Booking</title>
  <link rel="stylesheet" href="Booking.css">
  <link rel="stylesheet" href="navbar.css">
</head>
<body>
  <div class="container">
    <div class="form-section">
      <div class="logo">
        <!-- Add your logo here if needed -->
      </div>
      <form class="booking-form" method="post" action="">

        <label for="user_name">User Name</label>
        <input type="text" id="user_name" placeholder="Enter User Name" name="user_name" required>

        <label for="EventType">Event Type</label>
        <select id="EventType" name="EventType" required>
          <option value="">Select</option>
          <option value="Wedding">Wedding</option>
          <option value="Party">Party</option>
        </select>
        
        <label for="Place">Place</label>
        <input type="text" id="Place" placeholder="Enter Place" name="Place" required>
        
        <label for="NumberOfGuests">No. of Guests</label>
        <input type="number" id="NumberOfGuests" placeholder="0" name="NumberOfGuests" required>
        
        <label for="EventDate">Date</label>
        <input type="date" id="EventDate" name="EventDate" required>
        
        <label for="DayNight">Day/Night</label>
        <select id="DayNight" name="DayNight" required>
          <option value="">Select</option>
          <option value="Day">Day</option>
          <option value="Night">Night</option>
        </select>
        
        <label for="FoodPreferences">Food</label>
        <select id="FoodPreferences" name="FoodPreferences" required>
          <option value="">Select</option>
          <option value="High price">High price</option>
          <option value="Middle price">Middle price</option>
          <option value="Low Price">Low Price</option>
        </select>

        <label for="ExtraDetails">Food Calculator</label>
        <li><a href="Food.php">Food Calculator</a></li>   

        <label for="ExtraDetails">Extra Details</label>
        <textarea id="details" name="ExtraDetails" placeholder="Enter extra details"></textarea>
        
        <button type="submit" class="book-btn">Book</button>
      </form>
    </div>
    <div class="image-section">
      <img src="evnt.jpg" alt="DJ Event">
    </div>
  </div>
</body>
</html>