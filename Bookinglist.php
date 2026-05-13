<!DOCTYPE html>
<html lang="en">
<head>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking List</title>
  <link rel="stylesheet" href="Bookinglist.css">
  <link rel="stylesheet" href="navbar.css">
  <style>
    
  </style>
</head>
<body>
  <?php include 'navbar.php'; ?> <!-- Moved navbar to the correct position -->

  <div class="container" id="makepdf">
    <h1>Booking List</h1>
    <table>
      <tr>
        <th>Booking ID</th>
        <th>User Name</th>
        <th>Event Type</th>
        <th>Place</th>
        <th>No. of Guests</th>
        <th>Date</th>
        <th>Time</th>
        <th>Food</th>
        <th>Details</th>
      </tr>

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

      // Fetch data from the database
      $sql = "SELECT BookingID, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails FROM bookings"; // Corrected column name
      $result = $conn->query($sql);

      if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
              echo "<tr>";
              echo "<td>" . htmlspecialchars($row['BookingID']) . "</td>";
              echo "<td>" . htmlspecialchars($row['user_name']) . "</td>"; // Changed from user_id to user_name
              echo "<td>" . htmlspecialchars($row['EventType']) . "</td>";
              echo "<td>" . htmlspecialchars($row['Place']) . "</td>";
              echo "<td>" . htmlspecialchars($row['NumberOfGuests']) . "</td>";
              echo "<td>" . htmlspecialchars($row['EventDate']) . "</td>";
              echo "<td>" . htmlspecialchars($row['DayNight']) . "</td>";
              echo "<td>" . htmlspecialchars($row['FoodPreferences']) . "</td>";
              echo "<td>" . htmlspecialchars($row['ExtraDetails']) . "</td>";
              echo "</tr>";
          }
      } else {
          echo "<tr><td colspan='9' style='text-align:center;'>No bookings found</td></tr>";
      }

      // Close the connection
      $conn->close();
      ?>
    </table>

    
  </div>
<!-- Button to Generate PDF -->
<button id="button">Download PDF</button>
  <script>
    let button = document.getElementById("button");

    button.addEventListener("click", function () {
        // Generate PDF from the content inside the container
        html2pdf().from(document.getElementById("makepdf")).save("booking_list.pdf");
    });
  </script>
</body>
</html>