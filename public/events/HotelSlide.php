<?php
include __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luxury Hotels & Venues - EventEase</title>
    <style>
        .slide-container {
            max-width: 900px;
            margin: 40px auto 80px;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .slide-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 25px;
            text-align: center;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        #slider {
            width: 100%;
            overflow: hidden;
            position: relative;
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-premium);
        }

        .slider-wrapper {
            display: flex;
            width: 100%;
            animation: slider 15s infinite ease-in-out;
        }

        .slider-wrapper img {
            width: 100%;
            flex-shrink: 0;
            height: 480px;
            object-fit: cover;
        }

        /* 5-image Keyframes */
        @keyframes slider {
            0%, 16% { transform: translateX(0%); }
            20%, 36% { transform: translateX(-100%); }
            40%, 56% { transform: translateX(-200%); }
            60%, 76% { transform: translateX(-300%); }
            80%, 96% { transform: translateX(-400%); }
            100% { transform: translateX(0%); }
        }

        .btn-group {
            display: flex;
            gap: 20px;
            margin-top: 35px;
            width: 100%;
            justify-content: center;
        }

        .btn-group .btn {
            min-width: 180px;
        }
    </style>
</head>
<body>
    <div class="slide-container">
        <h1 class="slide-title">Luxury Hotels & Venues</h1>
        <div id="slider">
            <div class="slider-wrapper">
                <img src="../assets/images/H1.jpg" alt="Hotel 1">
                <img src="../assets/images/H2.jpg" alt="Hotel 2">
                <img src="../assets/images/H3.jpg" alt="Hotel 3">
                <img src="../assets/images/H4.jpg" alt="Hotel 4">
                <img src="../assets/images/H5.jpg" alt="Hotel 5">
            </div>
        </div> 

        <div class="btn-group">
            <a href="../Booking.php" class="btn btn-primary">Book Venue Now</a>
            <a href="../Home.php" class="btn btn-secondary">Go Back</a>
        </div>
    </div>
</body>
</html>
