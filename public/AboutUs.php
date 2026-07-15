<?php
include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - EventEase</title>
    <style>
        .about-wrapper {
            max-width: 1000px;
            margin: 60px auto 80px;
            padding: 0 20px;
        }

        .about-title {
            font-size: 42px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 50px;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .about-card h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #c084fc, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .about-card p {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <div class="about-wrapper">
        <h1 class="about-title">About Us</h1>
        <div class="cards-grid">
            <div class="glass-card about-card">
                <h2>Mission</h2>
                <p>To empower individuals and organizations by providing a comprehensive, user-friendly platform that simplifies event planning, ensures seamless coordination, and enhances creativity. Our goal is to turn every event into an extraordinary experience through efficient tools, real-time collaboration, and innovative solutions.</p>
            </div>
            
            <div class="glass-card about-card">
                <h2>Vision</h2>
                <p>To become the global leader in event planning technology, revolutionizing how events are conceptualized, organized, and executed. We envision a world where technology bridges creativity and logistics, enabling every event to be unforgettable and stress-free.</p>
            </div>

            <div class="glass-card about-card">
                <h2>Achievement</h2>
                <p>Successfully facilitated the seamless organization of over 5,000 events globally, ranging from corporate conferences and weddings to large-scale festivals. Through our platform, users have saved an average of 30% planning time, achieved 95% client satisfaction, and reduced logistical errors by 40%, earning us recognition as a trusted leader in event planning technology.</p>
            </div>
        </div>
    </div>
</body>
</html>
