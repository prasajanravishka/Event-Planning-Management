<?php
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Slider</title>
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="Slide.css">
    
</head>
<style>
        * {
            margin: 0;
            padding: 0; 
            box-sizing: border-box;
           
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-image: url(happy-people-celebrating-having-fun.jpg);
             height: auto;
             width: 100%;
             background-size:cover;
            background-color: rgb(255, 255, 255);
        }

        #slider {
            width: 80%;
            max-width: 800px;
            overflow: hidden;
            position: relative;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
            margin-top: 60px;
        }

        .slider-wrapper {
            top: 20px;
            display: flex;
            width: 100%; /* Five images */
            animation: slider 10s infinite linear;
        }

        .slider-wrapper img {
            width: 100%;
            flex-shrink: 0;
        }

        @keyframes slider {
            0%, 20% { transform: translateX(0%); }
            25%, 45% { transform: translateX(-100%); }
            50%, 70% { transform: translateX(-200%); }
            75%, 95% { transform: translateX(-300%); }
            100% { transform: translateX(-400%); }
        }

        /* Button Styling */
        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .connect-btn, .booking-btn {
            display: inline-block;
            width: 200px;
            padding: 10px 20px;
            background-color: #007BFF;
            color: white;
            text-align: center;
            text-decoration: none;
            font-size: 18px;
            border-radius: 5px;
            transition: 0.3s;
            border: none;
            cursor: pointer;
        }

        .connect-btn:hover, .booking-btn:hover {
            background-color: #0056b3;
        }

        .booking-btn {
            background-color: #28a745;
        }

        .booking-btn:hover {
            background-color: #218838;
        }
</Style>
<body>

   <div id="slider">
        <div class="slider-wrapper">
            <img src="D1.jpg" alt="Image 1">
            <img src="D2.jpg" alt="Image 2">
            <img src="D3.jpg" alt="Image 3">
            <img src="D4.jpg" alt="Image 4">
            
        </div>
   </div> 

   

</body>
</html>
