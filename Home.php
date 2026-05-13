<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Event Ease</title>
    <link rel="stylesheet" href="Home.css">
    <link rel="stylesheet" href="navbar.css">
    <style>
        
    </style>
</head>
<body>
    <!-- Navbar Section -->
    

    <!-- Header Section -->
    <div class="header">
        <h1>Welcome to Event Ease</h1>
        <p class="description">
            Our all-in-one event planning management system! Streamline your 
            event planning process with our intuitive platform designed to help 
            you organize, manage, and execute events of any size with ease. From
             creating detailed schedules and managing guest lists to coordinating 
             vendors and tracking budgets, EventEase empowers you to plan with confidence.
              Whether you're organizing a wedding, corporate event, or personal celebration,
               we've got the tools you need to make it a success. Let’s turn your vision into reality,
                stress-free!
        </p>
    </div>
    <?php
include 'navbar.php';
?>
    <!-- Product Section -->
    <div class="box1">
        <a href="HotelSlide.php">
            <div class="card">
                <div class="image-img" style="background-image:url(Hotel.jpg);">

                </div>
                <h2>Hotels</h2>
            </div>
        </a>
        <a href="WeddingsSlids.php">
            <div class="card">
                <div class="image-img" style="background-image:url(wedding.jpg);">

                </div>
                <h2>Weddings</h2>
            </div>
        </a>
        <a href="DjPartySlide.php">
            <div class="card">
                <div class="image-img" style="background-image:url(happy-men-women-throwing-confetti.jpg);">

                </div>
                <h2>Dj Party</h2>
            </div>
        </a>
        <a href="BirthdayList.php">
            <div class="card">
                <div class="image-img" style="background-image:url(birth.jpg);">
                   
                 </div>
                <h2>Birthday</h2>
            </div>
        </a>
        <a href="GetTogether.php">
            <div class="card">
                <div class="image-img" style="background-image:url(get.jpg);">

                </div>
                <h2>Get Together</h2>
            </div>
        </a>
    </div>

    
</body>
</html>
