<?php
include __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warm Get Togethers - EventEase</title>
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
            animation: slider 12s infinite ease-in-out;
        }

        .slider-wrapper img {
            width: 100%;
            flex-shrink: 0;
            height: 480px;
            object-fit: cover;
        }

        /* 4-image Keyframes */
        @keyframes slider {
            0%, 20% { transform: translateX(0%); }
            25%, 45% { transform: translateX(-100%); }
            50%, 70% { transform: translateX(-200%); }
            75%, 95% { transform: translateX(-300%); }
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
        <h1 class="slide-title">Warm Get Togethers</h1>
        <div id="slider">
            <div class="slider-wrapper">
                <img src="../assets/images/G1.jpg" alt="Get Together 1">
                <img src="../assets/images/G2.jpg" alt="Get Together 2">
                <img src="../assets/images/G3.jpg" alt="Get Together 3">
                <img src="../assets/images/G4.jpg" alt="Get Together 4">
            </div>
        </div> 

        <div class="btn-group">
            <a href="../Booking.php" class="btn btn-primary">Book Gathering Now</a>
            <a href="../Home.php" class="btn btn-secondary">Go Back</a>
        </div>
    </div>
</body>
</html>
