<?php
session_start();
include __DIR__ . '/../config/database.php';
include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Event - EVENTFLARE</title>
    <style>
        .choose-wrapper {
            max-width: 1200px;
            margin: 60px auto 100px;
            padding: 0 20px;
            text-align: center;
        }

        .choose-wrapper h1 {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--text-heading) 30%, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .choose-wrapper p.subtitle {
            font-size: 16px;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto 50px;
            line-height: 1.7;
        }

        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .event-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            overflow: hidden;
            text-align: left;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
            text-decoration: none;
            display: flex;
            flex-direction: column;
        }

        .event-card:hover {
            transform: translateY(-8px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2), 0 0 20px var(--card-glow);
        }

        .event-img {
            width: 100%;
            height: 200px;
            background-size: cover;
            background-position: center;
            border-bottom: 1px solid var(--card-border);
            transition: transform 0.5s ease;
        }
        
        .event-card:hover .event-img {
            transform: scale(1.05);
        }
        
        .img-overflow-hidden {
            overflow: hidden;
        }

        .event-content {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            background: var(--card-bg);
            z-index: 2;
        }

        .event-content h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 10px;
        }

        .event-content p {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 20px;
            line-height: 1.6;
            flex-grow: 1;
        }

        .event-link {
            font-weight: 600;
            font-size: 14px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .event-card:hover .event-link {
            color: var(--primary-hover);
        }

        .event-link i {
            transition: transform 0.3s ease;
        }

        .event-card:hover .event-link i {
            transform: translateX(4px);
        }

    </style>
</head>
<body>
    <div class="choose-wrapper">
        <h1>What's your starter plan?</h1>
        <p class="subtitle">Select the type of celebration you're planning. We will guide you through our specialized premium packages and venues tailored just for you.</p>

        <div class="event-grid">
            
            <a href="events/WeddingsSlids.php" class="event-card">
                <div class="img-overflow-hidden">
                    <div class="event-img" style="background-image: url('assets/images/wedding.jpg');"></div>
                </div>
                <div class="event-content">
                    <h3>Weddings</h3>
                    <p>Celebrate your love story with bespoke floral Poruwas, luxury banquets, and verified vendors. Explore previous weddings & plan yours.</p>
                    <div class="event-link">Plan Wedding & View Showcase <i class="fas fa-arrow-right"></i></div>
                </div>
            </a>

            <a href="events/GetTogether.php" class="event-card">
                <div class="img-overflow-hidden">
                    <div class="event-img" style="background-image: url('assets/images/get.jpg');"></div>
                </div>
                <div class="event-content">
                    <h3>Get Together</h3>
                    <p>Reconnect with classmates, family, and colleagues with live BBQ grills, marquee tents, and acoustic sing-alongs.</p>
                    <div class="event-link">Plan Reunion & View Showcase <i class="fas fa-arrow-right"></i></div>
                </div>
            </a>

            <a href="events/BirthdayList.php" class="event-card">
                <div class="img-overflow-hidden">
                    <div class="event-img" style="background-image: url('assets/images/birth.jpg');"></div>
                </div>
                <div class="event-content">
                    <h3>Birthday</h3>
                    <p>Unforgettable birthday experiences with customized themes, artisan 3D cakes, magicians, and snack buffets.</p>
                    <div class="event-link">Plan Birthday & View Showcase <i class="fas fa-arrow-right"></i></div>
                </div>
            </a>

            <a href="events/DjPartySlide.php" class="event-card">
                <div class="img-overflow-hidden">
                    <div class="event-img" style="background-image: url('assets/images/happy-men-women-throwing-confetti.jpg');"></div>
                </div>
                <div class="event-content">
                    <h3>DJ Party</h3>
                    <p>Bring high-voltage energy with top-tier DJs, 4K laser lighting rigs, mobile cocktail bars, and crowd security.</p>
                    <div class="event-link">Plan Party & View Showcase <i class="fas fa-arrow-right"></i></div>
                </div>
            </a>

            <a href="events/HotelSlide.php" class="event-card">
                <div class="img-overflow-hidden">
                    <div class="event-img" style="background-image: url('assets/images/Hotel.jpg');"></div>
                </div>
                <div class="event-content">
                    <h3>Hotel Venues</h3>
                    <p>Discover luxurious hotels and elegant halls that offer premium hosting facilities and full service options.</p>
                    <div class="event-link">Explore Package <i class="fas fa-arrow-right"></i></div>
                </div>
            </a>

        </div>
    </div>
</body>
</html>
