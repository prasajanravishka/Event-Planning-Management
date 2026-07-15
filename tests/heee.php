<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Event Ease</title>
    <link rel="stylesheet" href="Home.css">
    <style>
        /* Global Styles */
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-image: url('festive-young-friends-having-fun-with-confetti.jpg');
            background-size: cover;
            color: #fff;
        }

        h1, h2 {
            font-family: 'Karla', sans-serif;
            margin: 0;
        }

        /* Navbar Styles */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgba(0, 0, 0, 0.7);
            padding: 10px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Logo Styles */
        .logo img {
            height: 50px; /* Adjust logo height */
            width: auto;
        }

        .navbar ul {
            display: flex;
            list-style-type: none;
            margin: 0;
            padding: 0;
        }

        .navbar li {
            margin: 0 15px;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            display: block;
            border-radius: 5px;
        }

        .navbar a:hover {
            background-color: rgba(192, 151, 151, 0.8);
        }

        /* Header Section */
        .header {
            text-align: center;
            padding: 80px 20px;
        }

        .header h1 {
            font-size: 70px;
            font-style: oblique;
        }

        .description {
            font-style: oblique;
            font-weight: bold;
            font-size: 20px;
            max-width: 800px;
            margin: 20px auto;
            text-align: center;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: center;
            }

            .navbar ul {
                flex-direction: column;
                text-align: center;
            }

            .navbar li {
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar Section -->
    <div class="navbar">
        <div class="logo">
            <a href="Home.php">
                <img src="logo.png" alt="Event Ease Logo">
            </a>
        </div>
        <ul>
            <li><a href="heee.php">Hee</a></li>
            <li><a href="haa.php">Haaa</a></li>
            <li><a href="Home.php">Home</a></li>
            <li><a href="AboutUs.php">About</a></li>
            <li><a href="Contact.php">Contact Us</a></li>
            <li><a href="LogIn.php">Log in</a></li>
            <li><a href="logout.php">Log out</a></li>
            <li><a href="Admin.php">Admin</a></li>
        </ul>
    </div>

    <!-- Header Section -->
    <div class="header">
        <h1>Welcome to Event Ease</h1>
        <p class="description">
            Our all-in-one event planning management system! Streamline your event planning process with our intuitive platform designed to help you organize, manage, and execute events of any size with ease. From creating detailed schedules and managing guest lists to coordinating vendors and tracking budgets, EventEase empowers you to plan with confidence.
        </p>
    </div>
</body>
</html>
