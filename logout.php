<?php
session_start();
session_destroy(); // Destroy all session data
header("Location: Home.php"); // Redirect to login page
exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout</title>
    <link rel="stylesheet" href="logout.css"> <!-- Link to CSS file -->
</head>
<body>

    <div class="logout-container">
        <h2>Are you sure you want to log out?</h2>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>

</body>
</html>
