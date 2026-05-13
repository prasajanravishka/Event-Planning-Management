<!DOCTYPE html>
<html lang="en">
<head>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.js"> </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User List</title>
  <link rel="stylesheet" href="Bookinglist.css">
  <link rel="stylesheet" href="navbar.css">
</head>
<body>
  <?php include 'navbar.php'; ?> <!-- Moved navbar to the correct position -->

  <div class="container" id="makepdf">
    <h1>User List</h1>
    <table>
      <tr>
        <th>User ID</th>
        <th>User Name</th>
        <th>Full Name</th>
        <th>Gmail</th>
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
      $sql = "SELECT id, username, fullname, email FROM users"; // Corrected column name
      $result = $conn->query($sql);

      if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
              echo "<tr>";
              echo "<td>" . htmlspecialchars($row['id']) . "</td>";
              echo "<td>" . htmlspecialchars($row['username']) . "</td>";
              echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
              echo "<td>" . htmlspecialchars($row['email']) . "</td>";
              echo "</tr>";
          }
      } else {
          echo "<tr><td colspan='4' style='text-align:center;'>No Users found</td></tr>";
      }

      // Close the connection
      $conn->close();
      ?>
    </table>

    </div>
      <!-- Button to trigger PDF download -->
    <button id="button" style="background-color: #4CAF50; padding: 10px 20px; color: white; border-radius: 5px; margin-top: 20px;">Download PDF</button>
  
  <script>
    let button = document.getElementById("button");

    button.addEventListener("click", function () {
        // Generate PDF from the content inside the container
        html2pdf().from(document.getElementById("makepdf")).save("user_list.pdf");
    });
  </script>
</body>
</html>
