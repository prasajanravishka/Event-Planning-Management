<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Booking</title>
    <link rel="stylesheet" href="navbar.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-image: url(happy-people-celebrating-having-fun.jpg);
            background-size: cover;
            
            font-family: Arial, sans-serif;
            color: white;
            text-align: center;
        }
        .container {
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        h1 {
            margin-bottom: 20px;
        }
        .button-group {
            display: flex;
            gap: 20px;
            justify-content: center;
        }
        .btn {
            text-decoration: none;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn:hover {
            background: rgba(255, 255, 255, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>You can get a summary</h1>
        <div class="button-group">
            <a href="Userlist.php" class="btn">User</a>
            <a href="Bookinglist.php" class="btn">Booking</a>
            <a href="FoodBudsummary.php" class="btn">Budget Summary</a>

        </div>
    </div>
</body>
</html>
<?php
include 'navbar.php';
?>