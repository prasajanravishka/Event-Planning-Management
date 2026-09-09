<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../../config/database.php';
include __DIR__ . '/../../includes/navbar.php';

// 1. Fetch DJ & Party Services from Database (event_type_id = 4)
$party_services = [];
if (isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT s.* FROM services s 
                            JOIN event_types et ON s.event_type_id = et.event_type_id 
                            WHERE et.type_name = 'DJ Parties' OR et.event_type_id = 4 
                            ORDER BY s.priority_rank ASC, s.service_id ASC");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $party_services[] = $row;
        }
        $stmt->close();
    }
}

// 2. Fetch Active Supplier Packages for DJ Parties
$party_packages = [];
if (isset($conn) && !$conn->connect_error) {
    $pkg_query = "SELECT sl.*, sup.business_name, sup.category, sup.contact_phone, sup.location, s.service_name 
                  FROM supplier_listings sl
                  JOIN suppliers sup ON sl.supplier_id = sup.id
                  JOIN services s ON sl.service_id = s.service_id
                  WHERE s.event_type_id = 4 AND sl.status = 'active'
                  ORDER BY sl.listing_id ASC";
    $pkg_res = $conn->query($pkg_query);
    if ($pkg_res) {
        while ($row = $pkg_res->fetch_assoc()) {
            $party_packages[] = $row;
        }
    }
}

// 3. Curated Previous Events Showcase for DJ & Parties
$previous_parties = [
    [
        'id'          => 'bkg-djp-01',
        'title'       => 'Neon Nights Rooftop Rave & Laser Show',
        'category'    => 'rave',
        'category_lbl'=> 'EDM & Club Raves',
        'venue'       => 'Cinnamon Lakeside Rooftop Lounge, Colombo',
        'date'        => 'November 14, 2026',
        'guests'      => 250,
        'image'       => '../assets/images/happy-men-women-throwing-confetti.jpg',
        'tag'         => 'High-Octane EDM & 4K Laser Rig',
        'summary'     => 'An electrifying sky-high party featuring intelligent beam movers, heavy low-end subwoofers, a synchronized laser show, and live mix sets from premier Sri Lankan DJs.',
        'services'    => [
            'A/V & Light Rig'     => 'Lumina Audio Visual (Concert Moving Heads & Laser Truss)',
            'DJ & Artists'        => 'Pulse DJ & Live Beats (Headliner 4-Hour Nonstop EDM Set)',
            'Bar & Beverages'     => 'Velvet Mobile Cocktail Bar (Signature Neon Cocktails & Shots)',
            'Security & Access'   => 'Aegis Event Security (Bouncers & VIP Wristband Control)'
        ],
        'quote'       => '"The sound quality and laser sync blew everyone away! The rooftop was buzzing all night long."',
        'client'      => 'Kavinda & Friends (Annual Reunion)'
    ],
    [
        'id'          => 'bkg-djp-02',
        'title'       => 'Sunset Beats Beachfront Deep House Lounge',
        'category'    => 'sunset',
        'category_lbl'=> 'Sunset & Rooftops',
        'venue'       => 'Bentota Beach Resort Palm Lawn',
        'date'        => 'January 23, 2026',
        'guests'      => 180,
        'image'       => '../assets/images/happy-people-celebrating-having-fun.jpg',
        'tag'         => 'Tropical Deep House & Cocktails',
        'summary'     => 'A bohemian beachfront sunset gathering with chill deep house grooves, warm festoon lighting, artisanal grazing tables, and a craft rum mixology station.',
        'services'    => [
            'Sound & Ambient Rig' => 'Lumina Audio Visual (Weatherproof Sound & Warm String Bulbs)',
            'Live Mix Performer'  => 'Pulse DJ (Sunset Melodic & Organic Deep House)',
            'Cocktail Bar'        => 'Velvet Mobile Bar (Coconut Spritzers & Rum Infusions)',
            'Lawn Staging'        => 'Lotus Floral & Decor (Boho Low Seating & Bean Bags)'
        ],
        'quote'       => '"Watching the sun dip below the ocean while sipping craft cocktails with smooth deep house beats was pure magic."',
        'client'      => 'Ayesha & Tarik (Private Sundowner)'
    ],
    [
        'id'          => 'bkg-djp-03',
        'title'       => 'Apex Innovations Corporate Mega Gala & After-Party',
        'category'    => 'gala',
        'category_lbl'=> 'Mega Galas & Arenas',
        'venue'       => 'BMICH Main Exhibition Hall B, Colombo',
        'date'        => 'December 28, 2026',
        'guests'      => 450,
        'image'       => '../assets/images/D1.jpg',
        'tag'         => 'Arena Sound & Dual DJ Battle',
        'summary'     => 'A high-impact corporate celebration transitioning into an arena-grade after-party with dual DJ battles, cryo CO2 jets, indoor pyrotechnics, and full VIP hospitality.',
        'services'    => [
            'Arena Production'    => 'Lumina Audio Visual (Line Array Sound & Cryo CO2 Cannons)',
            'Headline DJs'        => 'Pulse DJ Production (Commercial Hits & Retro Throwback Battle)',
            'Hospitality & Bar'   => 'Grand Royal Banquet & Velvet Mixology (Late Night Buffet & Open Bar)',
            'Crowd Security'      => 'Aegis Event Security (12-Member Security Detail & Valet Parking)'
        ],
        'quote'       => '"Our team had the time of their lives! The transition from awards dinner to nightclub energy was phenomenal."',
        'client'      => 'Sarah Mendis (HR Director, Apex Global)'
    ],
    [
        'id'          => 'bkg-djp-04',
        'title'       => 'Retro 90s Club Throwback & Vinyl Night',
        'category'    => 'retro',
        'category_lbl'=> 'Retro & Themed Parties',
        'venue'       => 'Colombo Club Speakeasy Lounge',
        'date'        => 'March 06, 2026',
        'guests'      => 120,
        'image'       => '../assets/images/festive-young-friends-having-fun-with-confetti.jpg',
        'tag'         => 'Disco Mirror Balls & Classic Hits',
        'summary'     => 'An energetic nostalgic bash with spinning mirror balls, neon silhouette cutouts, 80s/90s chart-topping anthems, and gourmet comfort-food sliders.',
        'services'    => [
            'Retro Staging'       => 'Lumina Audio Visual (Twin Mirror Balls & Neon Glow Floods)',
            'DJ & Host'           => 'Pulse DJ & Live Beats (90s Pop, Funk & Golden Era Hip Hop)',
            'Late-Night Food'     => 'Grand Royal Catering (Artisan Sliders & Loaded Tacos Station)',
            'Mixology'            => 'Velvet Mobile Bar (Retro Flaming Cocktails & Punch Bowls)'
        ],
        'quote'       => '"Every single track hit right. The dancefloor was packed until 3 AM!"',
        'client'      => 'Dr. Rohan & Shanika'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DJ & Party Celebrations Showcase - EVENTFLARE</title>
    <style>
        /* Ambient Glow Background Orbs */
        .orb {
            position: absolute;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, rgba(236, 72, 153, 0.04) 70%);
            filter: blur(70px);
            z-index: -1;
            pointer-events: none;
        }
        .orb-1 { top: 5%; right: -10%; }
        .orb-2 { top: 35%; left: -15%; }
        .orb-3 { bottom: 15%; right: -10%; }

        /* Main Container */
        .party-page {
            max-width: 1200px;
            margin: 100px auto 80px;
            padding: 0 20px;
        }

        /* Hero Section */
        .party-hero {
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
            background: rgba(139, 92, 246, 0.12);
            border: 1px solid rgba(139, 92, 246, 0.3);
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .hero-title {
            font-size: 46px;
            font-weight: 900;
            line-height: 1.15;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--text-heading) 40%, var(--primary));
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

        /* Hero Image Slider Card */
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
            animation: heroPartySlide 16s infinite ease-in-out;
        }

        .hero-slider-wrapper img {
            width: 25%;
            height: 100%;
            object-fit: cover;
        }

        @keyframes heroPartySlide {
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
            color: var(--primary);
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

        /* Filter Pills */
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
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.35);
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
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25), 0 0 20px var(--card-glow);
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
            background: rgba(139, 92, 246, 0.9);
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
            padding-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            background: var(--primary);
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
            color: var(--primary);
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
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateY(-4px);
        }

        .pkg-tag {
            font-size: 11px;
            font-weight: 700;
            color: var(--primary);
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
            color: var(--primary);
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
            background: linear-gradient(135deg, var(--primary), #ec4899);
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

        /* FAQ Accordion */
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
            color: var(--primary);
        }

        /* Bottom Conversion Box */
        .conversion-box {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(236, 72, 153, 0.08) 100%);
            border: 1px solid rgba(139, 92, 246, 0.3);
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
            .party-hero {
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

    <div class="party-page">
        <!-- ================= HERO SECTION ================= -->
        <section class="party-hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-bolt"></i> Premier DJ & Party Production
                </div>
                <h1 class="hero-title">High-Energy Sound, Lights & Legendary Parties</h1>
                <p class="hero-desc">
                    From rooftop EDM raves and beach sundowners to arena corporate galas, EVENTFLARE connects you with accredited sound engineers, headline DJs, mobile cocktail bars, and intelligent lighting rigs.
                </p>
                <div class="hero-cta-group">
                    <a href="PartyBooking.php" class="btn btn-primary">
                        <i class="fas fa-play"></i> Book Party Now
                    </a>
                    <a href="#showcase" class="btn btn-secondary">
                        <i class="fas fa-images"></i> Previous Parties
                    </a>
                    <a href="#catalog" class="btn btn-secondary">
                        <i class="fas fa-music"></i> Sound & DJ Packages
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <h3>50+</h3>
                        <p>Pro DJs & Artists</p>
                    </div>
                    <div class="stat-item">
                        <h3>4K & Laser</h3>
                        <p>Intelligent Light Rigs</p>
                    </div>
                    <div class="stat-item">
                        <h3>100%</h3>
                        <p>Sound Safety & Permits</p>
                    </div>
                </div>
            </div>

            <!-- Dynamic Slider Visual -->
            <div class="hero-visual-card">
                <div class="hero-slider-wrapper">
                    <img src="../assets/images/happy-men-women-throwing-confetti.jpg" alt="Neon Party 1">
                    <img src="../assets/images/D1.jpg" alt="Party 2">
                    <img src="../assets/images/happy-people-celebrating-having-fun.jpg" alt="Party 3">
                    <img src="../assets/images/festive-young-friends-having-fun-with-confetti.jpg" alt="Party 4">
                </div>
                <div class="hero-visual-overlay">
                    <div>
                        <span style="font-size:12px; text-transform:uppercase; color:var(--primary); font-weight:700; letter-spacing:0.05em;">
                            Live Concert Engineering
                        </span>
                        <h4 style="font-size:16px; margin:4px 0 0; font-weight:700;">Curated Nightlife & Private Celebrations</h4>
                    </div>
                    <span class="badge" style="background:var(--primary); color:#fff; font-size:12px;">Verified Tech</span>
                </div>
            </div>
        </section>

        <!-- ================= PREVIOUS EVENTS SHOWCASE ================= -->
        <section id="showcase">
            <div class="section-header-box">
                <span class="section-tag">Case Studies & Past Celebrations</span>
                <h2>Real Parties Choreographed by EVENTFLARE</h2>
                <p>Browse our previous club nights, rooftop sundowners, and festival galas. Click any event to inspect its equipment setup, DJ lineup, and book a matching vibe.</p>
            </div>

            <!-- Category Filter Pills -->
            <div class="filter-nav">
                <button class="filter-btn active" onclick="filterShowcase('all', this)">All Vibes</button>
                <button class="filter-btn" onclick="filterShowcase('rave', this)">EDM & Club Raves</button>
                <button class="filter-btn" onclick="filterShowcase('sunset', this)">Sunset & Rooftops</button>
                <button class="filter-btn" onclick="filterShowcase('gala', this)">Mega Galas & Arenas</button>
                <button class="filter-btn" onclick="filterShowcase('retro', this)">Retro & Themed Parties</button>
            </div>

            <!-- Cards Grid -->
            <div class="showcase-grid">
                <?php foreach ($previous_parties as $p): ?>
                    <div class="showcase-card" data-category="<?= htmlspecialchars($p['category']) ?>">
                        <div class="showcase-img-wrap">
                            <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
                            <span class="showcase-badge"><?= htmlspecialchars($p['category_lbl']) ?></span>
                            <span class="showcase-guests-pill"><i class="fas fa-users"></i> <?= $p['guests'] ?> Guests</span>
                        </div>
                        <div class="showcase-body">
                            <h3 class="showcase-title"><?= htmlspecialchars($p['title']) ?></h3>
                            <div class="showcase-venue">
                                <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i>
                                <?= htmlspecialchars($p['venue']) ?>
                            </div>
                            <p class="showcase-summary"><?= htmlspecialchars($p['summary']) ?></p>
                            <div class="showcase-footer">
                                <button type="button" class="btn btn-secondary btn-sm" 
                                        onclick="openPartyDossier(<?= htmlspecialchars(json_encode($p)) ?>)">
                                    <i class="fas fa-eye"></i> Inspect Setup
                                </button>
                                <a href="PartyBooking.php?vibe=<?= urlencode($p['category']) ?>&guests=<?= $p['guests'] ?>" class="btn btn-primary btn-sm">
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
                <span class="section-tag">Accredited Production Partners</span>
                <h2>DJ, Sound & Party Production Catalog</h2>
                <p>Choose from top-tier sound providers, resident club DJs, cocktail mixologists, and venue security teams with transparent rates.</p>
            </div>

            <div class="packages-grid">
                <?php if (empty($party_packages)): ?>
                    <div style="grid-column:1/-1; text-align:center; padding:40px; color:var(--text-muted);">
                        No supplier packages currently published. Use our Party Wizard to request custom setups.
                    </div>
                <?php else: ?>
                    <?php foreach ($party_packages as $pkg): ?>
                        <div class="package-card">
                            <span class="pkg-tag"><?= htmlspecialchars($pkg['service_name']) ?></span>
                            <h3 class="pkg-title"><?= htmlspecialchars($pkg['title']) ?></h3>
                            <div class="pkg-supplier">
                                <i class="fas fa-award" style="color:var(--primary);"></i>
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
                            <a href="PartyBooking.php?package_id=<?= $pkg['listing_id'] ?>" class="btn btn-primary btn-sm" style="width:100%; text-align:center;">
                                <i class="fas fa-check-circle"></i> Book With Package
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ================= CLIENT REVIEWS ================= -->
        <section style="margin-top:80px;">
            <div class="section-header-box">
                <span class="section-tag">Social Proof & Host Feedback</span>
                <h2>What Party Hosts Say About Us</h2>
                <p>Real feedback from private birthday parties, alumni batches, and corporate gala organizers.</p>
            </div>

            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="stars-row">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "The sound system was crystal clear without any distortion. Pulse DJ kept our 300+ guests moving until the venue lights turned on. Seamless experience!"
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">KM</div>
                        <div class="author-meta">
                            <h4>Kasun Mudannayake</h4>
                            <p>Annual Batch Reunion Organizer</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="stars-row">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "Velvet Mobile Cocktail Bar and the Lumina lighting rig made our rooftop party look like a club in Ibiza. Guests are still talking about it weeks later!"
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">DW</div>
                        <div class="author-meta">
                            <h4>Dilshan Wickremasinghe</h4>
                            <p>30th Birthday Bash Host</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="stars-row">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "From handling local decibel compliance and security wristbands to managing the dual DJ console, EVENTFLARE was 100% dependable."
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">SM</div>
                        <div class="author-meta">
                            <h4>Shalini Mendis</h4>
                            <p>Corporate Events Lead</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= FAQ ACCORDION ================= -->
        <section class="faq-wrap">
            <div class="section-header-box">
                <span class="section-tag">Essential Guidance</span>
                <h2>DJ & Party Planning Frequently Asked Questions</h2>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>What sound power and wattages do you recommend for outdoor vs indoor events?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    For indoor hotel halls up to 200 pax, an active 5kW to 8kW sound rig with dual 18" subwoofers is optimal. For outdoor beachfront or rooftop venues with 200–500 guests, we engineer 12kW+ line array systems with auxiliary delays to deliver punchy bass while preventing neighbor spillover.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Do your DJs take song requests and custom genre playlists?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Yes, absolutely! Through our 4-stage Party Booking Wizard, you can specify preferred genres (EDM, Commercial Pop, 90s Throwback, Afrobeat, Deep House) as well as your "Must-Play" and "Do-Not-Play" tracks directly to the assigned headline artist.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>How do you handle sound permits and outdoor curfew restrictions?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    EVENTFLARE works strictly with certified production suppliers who comply with local police permits and municipal sound ordinance levels. We also provide silent disco headphone add-ons for after-hours parties past midnight!
                </div>
            </div>
        </section>

        <!-- ================= BOTTOM CONVERSION BANNER ================= -->
        <div class="conversion-box">
            <h2>Ready to Host an Epic Party?</h2>
            <p>Step into our specialized 4-stage DJ & Party Booking Wizard to select your sound rig, handpick your DJ, calculate your live budget, and lock in your date.</p>
            <div style="display:flex; justify-content:center; gap:15px; flex-wrap:wrap;">
                <a href="PartyBooking.php" class="btn btn-primary" style="padding:16px 36px; font-size:16px;">
                    <i class="fas fa-bolt"></i> Start Party Booking Wizard
                </a>
                <a href="../ChooseEvent.php" class="btn btn-secondary" style="padding:16px 30px; font-size:16px;">
                    <i class="fas fa-th-large"></i> Explore Other Celebrations
                </a>
            </div>
        </div>
    </div>

    <!-- ================= INTERACTIVE PARTY DOSSIER MODAL ================= -->
    <div class="modal-backdrop" id="partyDossierModal" onclick="closeModalOnBackdrop(event)">
        <div class="modal-card">
            <button type="button" class="modal-close-btn" onclick="closePartyDossier()">&times;</button>
            <span id="modalCategory" style="font-size:12px; font-weight:800; color:var(--primary); text-transform:uppercase; letter-spacing:0.06em;"></span>
            <h2 id="modalTitle" style="font-size:24px; font-weight:800; color:var(--text-heading); margin:8px 0 16px;"></h2>
            
            <img id="modalImage" src="" alt="Party Setup" style="width:100%; height:260px; object-fit:cover; border-radius:16px; margin-bottom:20px; border:1px solid var(--card-border);">

            <p id="modalSummary" style="font-size:14px; color:var(--text-muted); line-height:1.7; margin-bottom:20px;"></p>

            <h4 style="font-size:15px; font-weight:700; color:var(--text-heading); margin-bottom:12px;">Assigned Production & Vendor Breakdown</h4>
            <div id="modalServices" class="modal-specs-list"></div>

            <div style="background:rgba(139, 92, 246, 0.08); border:1px solid rgba(139, 92, 246, 0.2); border-radius:14px; padding:16px; margin-top:20px;">
                <p id="modalQuote" style="font-size:13px; font-style:italic; color:var(--text-heading); margin:0 0 6px;"></p>
                <span id="modalClient" style="font-size:12px; font-weight:700; color:var(--primary);"></span>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:25px;">
                <button type="button" class="btn btn-secondary" onclick="closePartyDossier()">Close</button>
                <a id="modalBookBtn" href="PartyBooking.php" class="btn btn-primary">
                    <i class="fas fa-play"></i> Book With This Vibe
                </a>
            </div>
        </div>
    </div>

    <!-- JavaScript Controller -->
    <script>
        // 1. Filter Showcase Cards
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

        // 2. Dossier Modal Open/Close
        function openPartyDossier(party) {
            document.getElementById('modalCategory').textContent = party.category_lbl + ' • ' + party.guests + ' GUESTS';
            document.getElementById('modalTitle').textContent = party.title;
            document.getElementById('modalImage').src = party.image;
            document.getElementById('modalSummary').textContent = party.summary;
            document.getElementById('modalQuote').textContent = party.quote;
            document.getElementById('modalClient').textContent = '— ' + party.client;

            // Render Assigned Services
            const sList = document.getElementById('modalServices');
            sList.innerHTML = '';
            for (const [svcName, supDesc] of Object.entries(party.services)) {
                const item = document.createElement('div');
                item.className = 'modal-spec-item';
                item.innerHTML = `
                    <div class="modal-spec-title">${svcName}</div>
                    <p class="modal-spec-val">${supDesc}</p>
                `;
                sList.appendChild(item);
            }

            // Link to Booking Wizard
            document.getElementById('modalBookBtn').href = `PartyBooking.php?vibe=${encodeURIComponent(party.category)}&guests=${party.guests}`;

            document.getElementById('partyDossierModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closePartyDossier() {
            document.getElementById('partyDossierModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function closeModalOnBackdrop(e) {
            if (e.target.id === 'partyDossierModal') {
                closePartyDossier();
            }
        }

        // 3. FAQ Toggle
        function toggleFaq(el) {
            const item = el.closest('.faq-item');
            item.classList.toggle('active');
        }
    </script>
</body>
</html>
