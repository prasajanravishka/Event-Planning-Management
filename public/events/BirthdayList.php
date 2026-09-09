<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../../config/database.php';
include __DIR__ . '/../../includes/navbar.php';

// Auth State & Booking Redirect Helper
$is_logged_in = isset($_SESSION['login_user']);
if (!function_exists('get_booking_url')) {
    function get_booking_url($relative_target, $is_logged_in) {
        if ($is_logged_in) {
            return $relative_target;
        }
        return '../Login.php?redirect=' . urlencode('events/' . $relative_target);
    }
}

// 1. Fetch Birthday Services from Database (event_type_id = 3)
$birthday_services = [];
if (isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT s.* FROM services s 
                            JOIN event_types et ON s.event_type_id = et.event_type_id 
                            WHERE et.type_name = 'Birthdays' OR et.event_type_id = 3 
                            ORDER BY s.priority_rank ASC, s.service_id ASC");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $birthday_services[] = $row;
        }
        $stmt->close();
    }
}

// 2. Fetch Active Supplier Packages for Birthdays
$birthday_packages = [];
if (isset($conn) && !$conn->connect_error) {
    $pkg_query = "SELECT sl.*, sup.business_name, sup.category, sup.contact_phone, sup.location, s.service_name 
                  FROM supplier_listings sl
                  JOIN suppliers sup ON sl.supplier_id = sup.id
                  JOIN services s ON sl.service_id = s.service_id
                  WHERE s.event_type_id = 3 AND sl.status = 'active'
                  ORDER BY sl.listing_id ASC";
    $pkg_res = $conn->query($pkg_query);
    if ($pkg_res) {
        while ($row = $pkg_res->fetch_assoc()) {
            $birthday_packages[] = $row;
        }
    }
}

// 3. Curated Previous Events Showcase for Birthdays
$previous_birthdays = [
    [
        'id'          => 'bkg-bdy-01',
        'title'       => 'Aarav’s 1st Wild Jungle Safari Adventure',
        'category'    => 'kids',
        'category_lbl'=> 'Kids & Adventure',
        'venue'       => 'Waters Edge Grand Pavilion Lawn, Battaramulla',
        'date'        => 'July 18, 2026',
        'guests'      => 80,
        'image'       => '../assets/images/B1.jpg',
        'tag'         => 'Jungle Safari & 3D Animal Cake',
        'summary'     => 'A playful, vibrant first birthday transformed into a tropical safari. Featured customized organic balloon garlands, lifelike animal props, interactive mascot games, and a chocolate jungle river fountain.',
        'services'    => [
            'Themed Decor'        => 'Lotus Floral & Decor (Organic 3-Tier Safari Balloon Arch & Plinths)',
            'Artisan Cake'        => 'Sweet Symphony (3-Tier Fondant Jungle Animal Sculpture Cake)',
            'Entertainment'       => 'Ranranga Interactive Games & Magic Show Troupe',
            'Kids Catering'       => 'Grand Royal Catering (Mini Chicken Sliders, Pizza Bites & Fruit Skewers)'
        ],
        'quote'       => '"Aarav’s first birthday looked like a storybook wonderland. The custom animal cake was breathtaking!"',
        'client'      => 'Dr. Dinesh & Nilushi Ranasinghe'
    ],
    [
        'id'          => 'bkg-bdy-02',
        'title'       => 'Senuri’s Sweet 16 Parisian Glamour Gala',
        'category'    => 'sweet16',
        'category_lbl'=> 'Sweet 16 & 21st',
        'venue'       => 'The Kingsbury Balmoral Ballroom, Colombo',
        'date'        => 'September 05, 2026',
        'guests'      => 120,
        'image'       => '../assets/images/B2.jpg',
        'tag'         => 'Rose Gold Elegance & Macaron Towers',
        'summary'     => 'An exquisite sweet sixteen inspired by Parisian haute couture. Styled with rose gold sequin arches, bespoke photobooth mirror, a luxury French dessert spread, and a private teen dancefloor with DJ beats.',
        'services'    => [
            'Glamour Decor'       => 'Lotus Floral Elegance (Rose Gold Shimmer Wall & Floral Rings)',
            'French Patisserie'   => 'Sweet Symphony (Custom Macaron Tower & Belgian Truffle Bar)',
            'DJ & Lighting'       => 'Pulse DJ & Live Beats (Top-40 Commercial & Teen Dance Rig)',
            'High Tea Banquet'    => 'Grand Royal Catering (Deluxe High Tea & Mocktail Spritzers)'
        ],
        'quote'       => '"My daughter and her school friends felt like royalty. Everything from the photo booth to the music was on point!"',
        'client'      => 'Chathurika Wickremasinghe'
    ],
    [
        'id'          => 'bkg-bdy-03',
        'title'       => 'Uncle Nihal’s 60th Vintage Diamond Jubilee',
        'category'    => 'milestone',
        'category_lbl'=> 'Milestone Jubilees',
        'venue'       => 'Mount Lavinia Hotel Ocean Terrace',
        'date'        => 'October 12, 2026',
        'guests'      => 150,
        'image'       => '../assets/images/B3.jpg',
        'tag'         => 'Golden Milestone & Acoustic Jazz',
        'summary'     => 'A sophisticated 60th diamond jubilee celebrating six decades of memories. Featured a retrospective memory photo gallery, live saxophone jazz trio, and a bespoke carvery buffet under seaside stars.',
        'services'    => [
            'Heritage Staging'    => 'Lotus Decor (Warm Fairy Light Canopies & Retrospective Photo Wall)',
            'Jubilee Cake'        => 'Sweet Symphony (Classic 2-Tier Rich Plum & Gold Accent Cake)',
            'Acoustic Jazz'       => 'Pulse Live Beats (Saxophone & Double Bass Jazz Ensemble)',
            'Seaside Banquet'     => 'Grand Royal Banquet (Slow-Roasted Carvery & Seafood Spread)'
        ],
        'quote'       => '"A deeply touching, perfectly coordinated evening. EVENTFLARE honored our family patriarch with unmatched poise."',
        'client'      => 'Suren & Dilani Gooneratne'
    ],
    [
        'id'          => 'bkg-bdy-04',
        'title'       => 'Princess Aria’s Enchanted Fairytale Castle Party',
        'category'    => 'fairytale',
        'category_lbl'=> 'Princess & Fairytale',
        'venue'       => 'Cinnamon Grand Garden Suite Lawn, Colombo',
        'date'        => 'May 20, 2026',
        'guests'      => 65,
        'image'       => '../assets/images/B4.jpg',
        'tag'         => 'Pastel Castles & Live Magician',
        'summary'     => 'A dreamlike pastel fairytale garden complete with life-sized castle turret facades, glitter face-painting stations, an illusionist magic act, and a personalized royal cupcake tower.',
        'services'    => [
            'Castle Facade Decor' => 'Lotus Decor (Pastel Pink/Lilac Balloon Castle & Throne Seating)',
            'Custom Confection'   => 'Sweet Symphony (Royal Castle Fondant Cake & 50 Themed Cupcakes)',
            'Magic & Animation'   => 'Ranranga Children Entertainers (Illusion Show & Face Painting)',
            'Snack Feast'         => 'Grand Royal Catering (Cheesy Pizza Pockets, Rainbow Skewers & Punch)'
        ],
        'quote'       => '"Aria was grinning from ear to ear! The live magic show and the princess throne were pure wonder."',
        'client'      => 'Prasanna & Melani Fernando'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birthday Celebrations Showcase - EVENTFLARE</title>
    <style>
        .orb {
            position: absolute;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.12) 0%, rgba(139, 92, 246, 0.04) 70%);
            filter: blur(70px);
            z-index: -1;
            pointer-events: none;
        }
        .orb-1 { top: 5%; right: -10%; }
        .orb-2 { top: 38%; left: -15%; }
        .orb-3 { bottom: 15%; right: -10%; }

        .bdy-page {
            max-width: 1200px;
            margin: 100px auto 80px;
            padding: 0 20px;
        }

        /* Hero Section */
        .bdy-hero {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 40px;
            align-items: center;
            margin-bottom: 70px;
        }

        .hero-content {
            text-align: left;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(236, 72, 153, 0.12);
            border: 1px solid rgba(236, 72, 153, 0.3);
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            color: #db2777;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .hero-title {
            font-size: 46px;
            font-weight: 900;
            line-height: 1.15;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--text-heading) 40%, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: 16px;
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 30px;
            max-width: 580px;
        }

        .hero-cta-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .hero-stats {
            display: flex;
            gap: 30px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid var(--card-border);
        }

        .stat-item h3 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0 0 4px;
        }

        .stat-item p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Hero Visual Slider */
        .hero-visual-card {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--card-border);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            background: var(--card-bg);
            height: 440px;
        }

        .hero-slider-wrapper {
            display: flex;
            width: 400%;
            height: 100%;
            animation: heroBdySlide 16s infinite ease-in-out;
        }

        .hero-slider-wrapper img {
            width: 25%;
            height: 100%;
            object-fit: cover;
        }

        @keyframes heroBdySlide {
            0%, 20%   { transform: translateX(0%); }
            25%, 45%  { transform: translateX(-25%); }
            50%, 70%  { transform: translateX(-50%); }
            75%, 95%  { transform: translateX(-75%); }
            100%      { transform: translateX(0%); }
        }

        .hero-visual-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 24px;
            background: linear-gradient(to top, rgba(15, 9, 30, 0.9) 0%, transparent 100%);
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        /* Section Headings */
        .section-header-box {
            text-align: center;
            margin: 70px 0 35px;
        }

        .section-tag {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #db2777;
            margin-bottom: 8px;
            display: block;
        }

        .section-header-box h2 {
            font-size: 34px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 12px;
        }

        .section-header-box p {
            color: var(--text-muted);
            font-size: 15px;
            max-width: 650px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Filter Navigation */
        .filter-nav {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 35px;
        }

        .filter-btn {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-muted);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-smooth);
        }

        .filter-btn:hover, .filter-btn.active {
            background: #db2777;
            color: #ffffff;
            border-color: #db2777;
            box-shadow: 0 4px 15px rgba(219, 39, 119, 0.35);
        }

        /* Showcase Grid */
        .showcase-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }

        .showcase-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .showcase-card:hover {
            transform: translateY(-8px);
            border-color: rgba(236, 72, 153, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25), 0 0 20px rgba(236, 72, 153, 0.2);
        }

        .showcase-img-wrap {
            height: 230px;
            position: relative;
            overflow: hidden;
        }

        .showcase-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .showcase-card:hover .showcase-img-wrap img {
            transform: scale(1.08);
        }

        .showcase-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(15, 9, 30, 0.85);
            backdrop-filter: blur(8px);
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .showcase-guests-pill {
            position: absolute;
            bottom: 14px;
            right: 14px;
            background: rgba(219, 39, 119, 0.9);
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .showcase-body {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .showcase-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .showcase-venue {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .showcase-summary {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .showcase-footer {
            border-top: 1px solid var(--card-border);
            padding-top: 14px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .showcase-footer .btn {
            flex: 1;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            white-space: nowrap;
            justify-content: center;
            gap: 6px;
            line-height: 1.3;
        }

        .showcase-footer .btn i {
            font-size: 12px;
        }

        /* Dossier Modal */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(10, 6, 22, 0.8);
            backdrop-filter: blur(10px);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-backdrop.active {
            display: flex;
        }

        .modal-card {
            background: var(--bg-surface);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            max-width: 750px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            position: relative;
            padding: 35px;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--card-border);
            color: var(--text-heading);
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition-smooth);
        }

        .modal-close-btn:hover {
            background: #db2777;
            color: #fff;
        }

        .modal-specs-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-spec-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 14px;
        }

        .modal-spec-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #db2777;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .modal-spec-val {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-heading);
            margin: 0;
        }

        /* Packages Grid */
        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }

        .package-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
            position: relative;
        }

        .package-card:hover {
            border-color: rgba(236, 72, 153, 0.4);
            transform: translateY(-4px);
        }

        .pkg-tag {
            font-size: 11px;
            font-weight: 700;
            color: #db2777;
            text-transform: uppercase;
            margin-bottom: 6px;
            display: inline-block;
        }

        .pkg-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 6px;
        }

        .pkg-supplier {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pkg-price {
            font-size: 22px;
            font-weight: 800;
            color: #db2777;
            margin-bottom: 12px;
        }

        .pkg-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 18px;
            flex-grow: 1;
        }

        /* Testimonials Section */
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 70px;
        }

        .testimonial-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 26px;
            box-shadow: var(--shadow-premium);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .stars-row {
            color: #fbbf24;
            font-size: 14px;
            display: flex;
            gap: 3px;
        }

        .testimonial-text {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.7;
            font-style: italic;
            flex-grow: 1;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .author-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ec4899, #8b5cf6);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }

        .author-meta h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
        }

        .author-meta p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 0;
        }

        /* FAQ */
        .faq-wrap {
            max-width: 800px;
            margin: 0 auto 70px;
        }

        .faq-item {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            margin-bottom: 12px;
            overflow: hidden;
            transition: var(--transition-smooth);
        }

        .faq-question {
            padding: 18px 24px;
            font-size: 15px;
            font-weight: 700;
            color: var(--text-heading);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .faq-answer {
            padding: 0 24px 18px;
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            display: none;
        }

        .faq-item.active .faq-answer {
            display: block;
        }

        .faq-item.active .faq-icon {
            transform: rotate(180deg);
            color: #db2777;
        }

        /* Bottom Conversion Box */
        .conversion-box {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.12) 0%, rgba(139, 92, 246, 0.08) 100%);
            border: 1px solid rgba(236, 72, 153, 0.3);
            border-radius: 24px;
            padding: 50px 30px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            margin-bottom: 40px;
        }

        .conversion-box h2 {
            font-size: 32px;
            font-weight: 900;
            color: var(--text-heading);
            margin-bottom: 12px;
        }

        .conversion-box p {
            font-size: 15px;
            color: var(--text-muted);
            max-width: 550px;
            margin: 0 auto 28px;
            line-height: 1.6;
        }

        @media (max-width: 900px) {
            .bdy-hero {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .hero-content {
                text-align: center;
            }
            .hero-cta-group, .hero-stats {
                justify-content: center;
            }
            .hero-visual-card {
                height: 320px;
            }
            .modal-specs-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="bdy-page">
        <!-- ================= HERO SECTION ================= -->
        <section class="bdy-hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-birthday-cake"></i> Extraordinary Birthday Celebrations
                </div>
                <h1 class="hero-title">Custom Themes, Artisan Cakes & Joyful Milestones</h1>
                <p class="hero-desc">
                    Whether it is a child’s magical first adventure, an elegant sweet sixteen, or a golden 60th diamond jubilee, EVENTFLARE unites bespoke decorators, award-winning bakeries, interactive magicians, and finger-food feasts.
                </p>
                <div class="hero-cta-group">
                    <a href="<?= get_booking_url('BirthdayBooking.php', $is_logged_in) ?>" class="btn btn-primary" style="background:#db2777; border-color:#db2777;">
                        <i class="fas fa-gift"></i> Plan Birthday Now
                    </a>
                    <a href="#showcase" class="btn btn-secondary">
                        <i class="fas fa-images"></i> Previous Birthdays
                    </a>
                    <a href="#catalog" class="btn btn-secondary">
                        <i class="fas fa-magic"></i> Cakes & Entertainers
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <h3>100+</h3>
                        <p>Bespoke 3D Cake Themes</p>
                    </div>
                    <div class="stat-item">
                        <h3>Certified</h3>
                        <p>Magicians & Game Hosts</p>
                    </div>
                    <div class="stat-item">
                        <h3>Stress-Free</h3>
                        <p>Setup & Coordination</p>
                    </div>
                </div>
            </div>

            <!-- Dynamic Slider Visual -->
            <div class="hero-visual-card">
                <div class="hero-slider-wrapper">
                    <img src="../assets/images/B1.jpg" alt="Birthday 1">
                    <img src="../assets/images/B2.jpg" alt="Birthday 2">
                    <img src="../assets/images/B3.jpg" alt="Birthday 3">
                    <img src="../assets/images/B4.jpg" alt="Birthday 4">
                </div>
                <div class="hero-visual-overlay">
                    <div>
                        <span style="font-size:12px; text-transform:uppercase; color:#db2777; font-weight:700; letter-spacing:0.05em;">
                            Milestones Made Magical
                        </span>
                        <h4 style="font-size:16px; margin:4px 0 0; font-weight:700;">From 1st Birthdays to Golden Jubilees</h4>
                    </div>
                    <span class="badge" style="background:#db2777; color:#fff; font-size:12px;">Verified Partners</span>
                </div>
            </div>
        </section>

        <!-- ================= PREVIOUS EVENTS SHOWCASE ================= -->
        <section id="showcase">
            <div class="section-header-box">
                <span class="section-tag">Celebration Gallery</span>
                <h2>Real Birthdays Choreographed by EVENTFLARE</h2>
                <p>Explore our recent milestone parties, jungle themes, and glamour galas. Click any birthday to inspect its custom cake design, entertainers, and theme styling.</p>
            </div>

            <!-- Filter Pills -->
            <div class="filter-nav">
                <button class="filter-btn active" onclick="filterShowcase('all', this)">All Themes</button>
                <button class="filter-btn" onclick="filterShowcase('kids', this)">Kids & Adventure</button>
                <button class="filter-btn" onclick="filterShowcase('sweet16', this)">Sweet 16 & 21st</button>
                <button class="filter-btn" onclick="filterShowcase('milestone', this)">Milestone Jubilees</button>
                <button class="filter-btn" onclick="filterShowcase('fairytale', this)">Princess & Fairytale</button>
            </div>

            <!-- Cards Grid -->
            <div class="showcase-grid">
                <?php foreach ($previous_birthdays as $b): ?>
                    <div class="showcase-card" data-category="<?= htmlspecialchars($b['category']) ?>">
                        <div class="showcase-img-wrap">
                            <img src="<?= htmlspecialchars($b['image']) ?>" alt="<?= htmlspecialchars($b['title']) ?>" loading="lazy">
                            <span class="showcase-badge"><?= htmlspecialchars($b['category_lbl']) ?></span>
                            <span class="showcase-guests-pill"><i class="fas fa-users"></i> <?= $b['guests'] ?> Guests</span>
                        </div>
                        <div class="showcase-body">
                            <h3 class="showcase-title"><?= htmlspecialchars($b['title']) ?></h3>
                            <div class="showcase-venue">
                                <i class="fas fa-map-marker-alt" style="color:#db2777;"></i>
                                <?= htmlspecialchars($b['venue']) ?>
                            </div>
                            <p class="showcase-summary"><?= htmlspecialchars($b['summary']) ?></p>
                            <div class="showcase-footer">
                                <button type="button" class="btn btn-secondary btn-sm" 
                                        onclick="openBirthdayDossier(<?= htmlspecialchars(json_encode($b)) ?>)">
                                    <i class="fas fa-eye"></i> Inspect Setup
                                </button>
                                <a href="<?= get_booking_url('BirthdayBooking.php?theme=' . urlencode($b['category']) . '&guests=' . $b['guests'], $is_logged_in) ?>" class="btn btn-primary btn-sm" style="background:#db2777; border-color:#db2777;">
                                    Book Similar <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ================= VERIFIED SERVICES & PACKAGES CATALOG ================= -->
        <section id="catalog">
            <div class="section-header-box">
                <span class="section-tag">Verified Creators</span>
                <h2>Birthday Decor, Custom Cakes & Catering Catalog</h2>
                <p>Compare artisan bakeries, themed balloon decorators, illusionist magicians, and party snack buffets.</p>
            </div>

            <div class="packages-grid">
                <?php if (empty($birthday_packages)): ?>
                    <div style="grid-column:1/-1; text-align:center; padding:40px; color:var(--text-muted);">
                        No birthday packages currently published. Use our Birthday Wizard to build your custom celebration.
                    </div>
                <?php else: ?>
                    <?php foreach ($birthday_packages as $pkg): ?>
                        <div class="package-card">
                            <span class="pkg-tag"><?= htmlspecialchars($pkg['service_name']) ?></span>
                            <h3 class="pkg-title"><?= htmlspecialchars($pkg['title']) ?></h3>
                            <div class="pkg-supplier">
                                <i class="fas fa-award" style="color:#db2777;"></i>
                                <?= htmlspecialchars($pkg['business_name']) ?>
                                <?php if (!empty($pkg['location'])): ?>
                                    &bull; <?= htmlspecialchars($pkg['location']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="pkg-price">
                                Rs. <?= number_format($pkg['price'], 2) ?>
                                <span style="font-size:12px; color:var(--text-muted); font-weight:normal;">
                                    / <?= $pkg['price_type'] === 'per_person' ? 'pax' : 'event' ?>
                                </span>
                            </div>
                            <p class="pkg-desc"><?= htmlspecialchars($pkg['description']) ?></p>
                            <a href="<?= get_booking_url('BirthdayBooking.php?package_id=' . $pkg['listing_id'], $is_logged_in) ?>" class="btn btn-primary btn-sm" style="width:100%; text-align:center; background:#db2777; border-color:#db2777;">
                                <i class="fas fa-check-circle"></i> Book With Package
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ================= TESTIMONIALS ================= -->
        <section style="margin-top:80px;">
            <div class="section-header-box">
                <span class="section-tag">Client Love</span>
                <h2>What Birthday Families Say</h2>
            </div>

            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="stars-row">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "The 3D jungle cake from Sweet Symphony looked too good to cut, but tasted even better! The kids loved the mascot games. Will book again next year!"
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">NR</div>
                        <div class="author-meta">
                            <h4>Nilushi Ranasinghe</h4>
                            <p>Mom of 1-Year-Old Aarav</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="stars-row">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "Organizing my dad’s 60th birthday was effortless with the 4-stage wizard. We handpicked the acoustic duo and carvery feast with zero stress."
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">SG</div>
                        <div class="author-meta">
                            <h4>Suren Gooneratne</h4>
                            <p>60th Jubilee Host</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="stars-row">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "The shimmer wall and balloon garland were the highlights of my daughter’s Sweet 16 photos. Absolute professional execution."
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">CW</div>
                        <div class="author-meta">
                            <h4>Chathurika W.</h4>
                            <p>Sweet 16 Mother</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= FAQ ================= -->
        <section class="faq-wrap">
            <div class="section-header-box">
                <span class="section-tag">Planning Advice</span>
                <h2>Frequently Asked Birthday Questions</h2>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>How far in advance should we book custom 3D fondant cakes?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    For multi-tier 3D sculptural or hand-painted cakes, our artisan bakers require at least 5 to 7 days advance notice. Standard 2-tier celebration cakes can be prepared within 72 hours through our fast-track booking.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Can you accommodate children’s food allergies (nuts, dairy, gluten)?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Yes, all our catering partners and patisseries label ingredients clearly. You can specify nut-free, eggless, or gluten-free requirements directly in Stage 4 of our Birthday Wizard under special instructions.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Do magicians and entertainers bring their own sound equipment?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Yes, our certified performers arrive fully equipped with portable battery-operated wireless microphones and background music players suitable for garden or indoor party spaces.
                </div>
            </div>
        </section>

        <!-- ================= BOTTOM CONVERSION BANNER ================= -->
        <div class="conversion-box">
            <h2>Make Their Birthday Extraordinary</h2>
            <p>Step into our specialized 4-stage Birthday Booking Wizard to choose your celebration theme, artisan cake designer, entertainer, and catering.</p>
            <div style="display:flex; justify-content:center; gap:15px; flex-wrap:wrap;">
                <a href="<?= get_booking_url('BirthdayBooking.php', $is_logged_in) ?>" class="btn btn-primary" style="padding:16px 36px; font-size:16px; background:#db2777; border-color:#db2777;">
                    <i class="fas fa-gift"></i> Start Birthday Wizard
                </a>
                <a href="../ChooseEvent.php" class="btn btn-secondary" style="padding:16px 30px; font-size:16px;">
                    <i class="fas fa-th-large"></i> Explore Other Celebrations
                </a>
            </div>
        </div>
    </div>

    <!-- ================= INTERACTIVE BIRTHDAY DOSSIER MODAL ================= -->
    <div class="modal-backdrop" id="birthdayDossierModal" onclick="closeModalOnBackdrop(event)">
        <div class="modal-card">
            <button type="button" class="modal-close-btn" onclick="closeBirthdayDossier()">&times;</button>
            <span id="modalCategory" style="font-size:12px; font-weight:800; color:#db2777; text-transform:uppercase; letter-spacing:0.06em;"></span>
            <h2 id="modalTitle" style="font-size:24px; font-weight:800; color:var(--text-heading); margin:8px 0 16px;"></h2>
            
            <img id="modalImage" src="" alt="Birthday Setup" style="width:100%; height:260px; object-fit:cover; border-radius:16px; margin-bottom:20px; border:1px solid var(--card-border);">

            <p id="modalSummary" style="font-size:14px; color:var(--text-muted); line-height:1.7; margin-bottom:20px;"></p>

            <h4 style="font-size:15px; font-weight:700; color:var(--text-heading); margin-bottom:12px;">Assigned Creators & Suppliers</h4>
            <div id="modalServices" class="modal-specs-list"></div>

            <div style="background:rgba(236, 72, 153, 0.08); border:1px solid rgba(236, 72, 153, 0.2); border-radius:14px; padding:16px; margin-top:20px;">
                <p id="modalQuote" style="font-size:13px; font-style:italic; color:var(--text-heading); margin:0 0 6px;"></p>
                <span id="modalClient" style="font-size:12px; font-weight:700; color:#db2777;"></span>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:25px;">
                <button type="button" class="btn btn-secondary" onclick="closeBirthdayDossier()">Close</button>
                <a id="modalBookBtn" href="BirthdayBooking.php" class="btn btn-primary" style="background:#db2777; border-color:#db2777;">
                    <i class="fas fa-gift"></i> Book With This Theme
                </a>
            </div>
        </div>
    </div>

    <!-- JavaScript Controller -->
    <script>
        function filterShowcase(category, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const cards = document.querySelectorAll('.showcase-card');
            cards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function openBirthdayDossier(bdy) {
            document.getElementById('modalCategory').textContent = bdy.category_lbl + ' • ' + bdy.guests + ' GUESTS';
            document.getElementById('modalTitle').textContent = bdy.title;
            document.getElementById('modalImage').src = bdy.image;
            document.getElementById('modalSummary').textContent = bdy.summary;
            document.getElementById('modalQuote').textContent = bdy.quote;
            document.getElementById('modalClient').textContent = '— ' + bdy.client;

            const sList = document.getElementById('modalServices');
            sList.innerHTML = '';
            for (const [svcName, supDesc] of Object.entries(bdy.services)) {
                const item = document.createElement('div');
                item.className = 'modal-spec-item';
                item.innerHTML = `
                    <div class="modal-spec-title">${svcName}</div>
                    <p class="modal-spec-val">${supDesc}</p>
                `;
                sList.appendChild(item);
            }

            const isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;
            const targetWizard = `BirthdayBooking.php?theme=${encodeURIComponent(bdy.category)}&guests=${bdy.guests}`;
            document.getElementById('modalBookBtn').href = isLoggedIn ? targetWizard : `../Login.php?redirect=${encodeURIComponent('events/' + targetWizard)}`;

            document.getElementById('birthdayDossierModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeBirthdayDossier() {
            document.getElementById('birthdayDossierModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function closeModalOnBackdrop(e) {
            if (e.target.id === 'birthdayDossierModal') {
                closeBirthdayDossier();
            }
        }

        function toggleFaq(el) {
            const item = el.closest('.faq-item');
            item.classList.toggle('active');
        }
    </script>
</body>
</html>
