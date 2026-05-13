<!DOCTYPE html>
<html lang="en">
<head>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Budget List</title>
  <link rel="stylesheet" href="Bookinglist.css">
  <link rel="stylesheet" href="navbar.css">
</head>
<body>

  <?php include 'navbar.php'; ?>

  <div class="container" id="makepdf">
    <h1>Budget List</h1>
    <table>
      <tr>
        <th>ID</th>
        <th>Total Budget</th>
        <th>Food Budget</th>
        <th>Buffet Cost</th>
        <th>Beverages Cost</th>
        <th>Desserts Cost</th>
        <th>Snacks Cost</th>
        <th>Total Spent</th>
        <th>Remaining Budget</th>
        <th>Variance</th>
      </tr>

      <?php
      // Database connection
      $servername = "localhost";
      $username = "root";
      $password = "";
      $dbname = "login";

      $conn = new mysqli($servername, $username, $password, $dbname);
      if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
      }

      // Fetch budget data
      $sql = "SELECT id, total_budget, food_budget, buffet_cost, beverages_cost, 
               desserts_cost, snacks_cost, total_spent, remaining_budget, variance 
        FROM budget";

              
      $result = $conn->query($sql);

      if (!$result) {
          die("Query failed: " . $conn->error);
      }

      if ($result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
              echo "<tr>";
              echo "<td>" . htmlspecialchars($row['id']) . "</td>";
              echo "<td>" . htmlspecialchars($row['total_budget']) . "</td>";
              echo "<td>" . htmlspecialchars($row['food_budget']) . "</td>";
              echo "<td>" . htmlspecialchars($row['buffet_cost']) . "</td>";
              echo "<td>" . htmlspecialchars($row['beverages_cost']) . "</td>";
              echo "<td>" . htmlspecialchars($row['desserts_cost']) . "</td>";
              echo "<td>" . htmlspecialchars($row['snacks_cost']) . "</td>";
              echo "<td>" . htmlspecialchars($row['total_spent']) . "</td>";
              echo "<td>" . htmlspecialchars($row['remaining_budget']) . "</td>";
              echo "<td>" . htmlspecialchars($row['variance']) . "</td>";
              echo "</tr>";
          }
      } else {
          echo "<tr><td colspan='10' style='text-align:center;'>No budget found</td></tr>";
      }

      $conn->close();
      ?>
    </table>
  </div>

  <button id="button">Download PDF</button>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      let button = document.getElementById("button");

      button.addEventListener("click", function () {
        html2pdf().from(document.getElementById("makepdf")).save("budget_list.pdf");
      });
    });
  </script>

</body>
</html>
