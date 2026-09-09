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

// 1. Fetch Get Together Services from Database (event_type_id = 2)
$gt_services = [];
if (isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT s.* FROM services s 
                            JOIN event_types et ON s.event_type_id = et.event_type_id 
                            WHERE et.type_name = 'Get Togethers' OR et.event_type_id = 2 
                            ORDER BY s.priority_rank ASC, s.service_id ASC");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $gt_services[] = $row;
        }
        $stmt->close();
    }
}

// 2. Fetch Active Supplier Packages for Get Togethers
$gt_packages = [];
if (isset($conn) && !$conn->connect_error) {
    $pkg_query = "SELECT sl.*, sup.business_name, sup.category, sup.contact_phone, sup.location, s.service_name 
                  FROM supplier_listings sl
                  JOIN suppliers sup ON sl.supplier_id = sup.id
                  JOIN services s ON sl.service_id = s.service_id
                  WHERE s.event_type_id = 2 AND sl.status = 'active'
                  ORDER BY sl.listing_id ASC";
    $pkg_res = $conn->query($pkg_query);
    if ($pkg_res) {
        while ($row = $pkg_res->fetch_assoc()) {
            $gt_packages[] = $row;
        }
    }
}

// 3. Curated Previous Events Showcase for Get Togethers & Reunions
$previous_gatherings = [
    [
        'id'          => 'bkg-gtg-01',
        'title'       => 'Royal College Class of 2012 Reunion & Live BBQ',
        'category'    => 'bbq',
        'category_lbl'=> 'Outdoor BBQ',
        'venue'       => 'CR&FC Grounds Pavilion, Colombo 07',
        'date'        => 'August 22, 2026',
        'guests'      => 200,
        'image'       => '../assets/images/G1.jpg',
        'tag'         => 'Live Charcoal Grill & Acoustic Hits',
        'summary'     => 'A spirited reunion bringing together 200 old classmates. Featured a live chef-manned BBQ grill with skewers and burgers, chilled beverage stations, an unplugged acoustic band, and nostalgic batch sports.',
        'services'    => [
            'Catering & Grill'    => 'Grand Royal Banquet (Live Outdoor Charcoal BBQ & Skewers Feast)',
            'Canopy & Seating'    => 'Lumina & Lotus Rentals (Marquee Tents, Picnic Benches & Festoon Bulbs)',
            'Acoustic Duo'        => 'Pulse Live Beats (Unplugged Acoustic Guitar & Sing-Along Anthems)',
            'Sound & PA'          => 'Lumina Audio Visual (Wireless Mics & Ambient Sound Rig)'
        ],
        'quote'       => '"Everyone said it was our best reunion yet. The live BBQ and acoustic sing-along were legendary!"',
        'client'      => 'Danushka & 2012 Organizing Committee'
    ],
    [
        'id'          => 'bkg-gtg-02',
        'title'       => 'Fernandopulle Family Annual Lakeside Gathering',
        'category'    => 'family',
        'category_lbl'=> 'Family Reunions',
        'venue'       => 'Bolgoda Lake Villa Lawn & Garden',
        'date'        => 'December 12, 2026',
        'guests'      => 90,
        'image'       => '../assets/images/G2.jpg',
        'tag'         => 'Traditional Village Clay-Pot Buffet',
        'summary'     => 'A heartwarming cross-generational gathering across four generations. Featured a traditional Sri Lankan clay-pot spread by the water, lawn games for grandchildren, and memory photo displays.',
        'services'    => [
            'Authentic Dining'    => 'Grand Royal Banquet (Traditional Sri Lankan Clay-Pot Village Buffet)',
            'Lawn Staging'        => 'Lotus Decor (Rustic Wooden Cross-Back Chairs & Shaded Parasols)',
            'Soft Background'     => 'Pulse DJ (Calm Instrumental Cello & Flute Playlist)',
            'Family Portraits'    => 'Aurora Cinematography (Generational Group Photos & Highlight Reel)'
        ],
        'quote'       => '"From elderly grandparents to the youngest toddlers, every single guest was comfortable and well fed."',
        'client'      => 'Priyantha Fernandopulle'
    ],
    [
        'id'          => 'bkg-gtg-03',
        'title'       => 'Tech Startup Alumni Summer Mixer',
        'category'    => 'alumni',
        'category_lbl'=> 'Alumni Mixers',
        'venue'       => 'Galle Face Hotel Chequerboard Terrace',
        'date'        => 'April 18, 2026',
        'guests'      => 110,
        'image'       => '../assets/images/G3.jpg',
        'tag'         => 'Sunset Cocktails & High-Top Lounges',
        'summary'     => 'A relaxed evening bringing together founders, engineers, and creatives over sea breezes, high-top cocktail tables, tapas platters, and an open karaoke lounge under the stars.',
        'services'    => [
            'Canapés & Tapas'     => 'Grand Royal Catering (Artisan Sliders, Bruschettas & Grazing Table)',
            'Lounge Rentals'      => 'Lumina Rentals (Illuminated High-Top Cocktail Tables & Bar Stools)',
            'Karaoke & DJ'        => 'Pulse DJ & Live Beats (Wireless Karaoke Console & Sunset Beats)',
            'Mobile Bar'          => 'Velvet Mobile Mixology (Craft Gin & Coconut Water Cocktails)'
        ],
        'quote'       => '"A phenomenal networking vibe. The cocktail setups and relaxed lounge atmosphere struck the perfect chord."',
        'client'      => 'Manesh & Colombo Tech Meetup'
    ],
    [
        'id'          => 'bkg-gtg-04',
        'title'       => 'Silver Jubilee High School Batch High Tea',
        'category'    => 'hightea',
        'category_lbl'=> 'High Tea & Lawn',
        'venue'       => 'Kandy City Hall Heritage Lawn',
        'date'        => 'November 07, 2026',
        'guests'      => 140,
        'image'       => '../assets/images/G4.jpg',
        'tag'         => 'Ceylon High Tea & Memory Photo Wall',
        'summary'     => 'A nostalgic 25-year silver jubilee afternoon tea. Featured vintage floral tableware, tiered savory and pastry towers, retrospective yearbook banners, and a live acoustic violin duo.',
        'services'    => [
            'High Tea Spread'     => 'Sweet Symphony & Grand Royal (Ceylon Spiced High Tea & Eclairs)',
            'Heritage Layout'     => 'Lotus Decor (Vintage Tea Tables, Linen Runners & Photo Displays)',
            'Live Strings'        => 'Ranranga Chamber Strings (Violin & Keyboard Duo)',
            'Keepsake Photography'=> 'Aurora Cinematography (Framed Batch Photo Printing On-Site)'
        ],
        'quote'       => '"Reconnecting with friends after 25 years in such a gorgeous setting brought tears of joy."',
        'client'      => 'Hiruni & Batch of 2001'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Togethers & Reunions Showcase - EVENTFLARE</title>
    <style>
        .orb {
            position: absolute;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.12) 0%, rgba(139, 92, 246, 0.04) 70%);
            filter: blur(70px);
            z-index: -1;
            pointer-events: none;
        }
        .orb-1 { top: 5%; right: -10%; }
        .orb-2 { top: 38%; left: -15%; }
        .orb-3 { bottom: 15%; right: -10%; }

        .gt-page {
            max-width: 1200px;
            margin: 100px auto 80px;
            padding: 0 20px;
        }

        /* Hero Section */
        .gt-hero {
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
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .hero-title {
            font-size: 46px;
            font-weight: 900;
            line-height: 1.15;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--text-heading) 40%, #10b981);
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
            animation: heroGtSlide 16s infinite ease-in-out;
        }

        .hero-slider-wrapper img {
            width: 25%;
            height: 100%;
            object-fit: cover;
        }

        @keyframes heroGtSlide {
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
            color: #10b981;
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
            background: #10b981;
            color: #ffffff;
            border-color: #10b981;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35);
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
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25), 0 0 20px rgba(16, 185, 129, 0.2);
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
            background: rgba(16, 185, 129, 0.9);
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
            background: #10b981;
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
            color: #10b981;
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
            border-color: rgba(16, 185, 129, 0.4);
            transform: translateY(-4px);
        }

        .pkg-tag {
            font-size: 11px;
            font-weight: 700;
            color: #10b981;
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
            color: #10b981;
            margin-bottom: 12px;
        }

        .pkg-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 18px;
            flex-grow: 1;
        }

        /* Testimonials */
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
            background: linear-gradient(135deg, #10b981, #059669);
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
            color: #10b981;
        }

        /* Bottom Conversion Box */
        .conversion-box {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(139, 92, 246, 0.08) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
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
            .gt-hero {
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

    <div class="gt-page">
        <!-- ================= HERO SECTION ================= -->
        <section class="gt-hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-users"></i> Heartfelt Reunions & Get Togethers
                </div>
                <h1 class="hero-title">Reconnect, Reminisce & Celebrate Together</h1>
                <p class="hero-desc">
                    Whether gathering old school batches, company alumni, or multiple generations of family, EVENTFLARE coordinates live outdoor BBQ stations, canopy marquees, acoustic music duos, and comfortable seating.
                </p>
                <div class="hero-cta-group">
                    <a href="<?= get_booking_url('GetTogetherBooking.php', $is_logged_in) ?>" class="btn btn-primary" style="background:#10b981; border-color:#10b981;">
                        <i class="fas fa-handshake"></i> Plan Reunion Now
                    </a>
                    <a href="#showcase" class="btn btn-secondary">
                        <i class="fas fa-images"></i> Previous Gatherings
                    </a>
                    <a href="#catalog" class="btn btn-secondary">
                        <i class="fas fa-utensils"></i> BBQ & Entertainment
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <h3>Live BBQ</h3>
                        <p>Outdoor Charcoal Grills</p>
                    </div>
                    <div class="stat-item">
                        <h3>Marquee & Tents</h3>
                        <p>Weatherproof Canopy Setups</p>
                    </div>
                    <div class="stat-item">
                        <h3>Acoustic</h3>
                        <p>Sing-Along Music & Mics</p>
                    </div>
                </div>
            </div>

            <!-- Dynamic Slider Visual -->
            <div class="hero-visual-card">
                <div class="hero-slider-wrapper">
                    <img src="../assets/images/G1.jpg" alt="Get Together 1">
                    <img src="../assets/images/G2.jpg" alt="Get Together 2">
                    <img src="../assets/images/G3.jpg" alt="Get Together 3">
                    <img src="../assets/images/G4.jpg" alt="Get Together 4">
                </div>
                <div class="hero-visual-overlay">
                    <div>
                        <span style="font-size:12px; text-transform:uppercase; color:#10b981; font-weight:700; letter-spacing:0.05em;">
                            Shared Memories & Good Food
                        </span>
                        <h4 style="font-size:16px; margin:4px 0 0; font-weight:700;">From Batch Meets to Family BBQs</h4>
                    </div>
                    <span class="badge" style="background:#10b981; color:#fff; font-size:12px;">Verified Partners</span>
                </div>
            </div>
        </section>

        <!-- ================= PREVIOUS EVENTS SHOWCASE ================= -->
        <section id="showcase">
            <div class="section-header-box">
                <span class="section-tag">Reunion Case Studies</span>
                <h2>Real Gatherings Choreographed by EVENTFLARE</h2>
                <p>Browse our past school batch meets, family reunions, and tech mixers. Click any gathering to inspect seating layouts, live BBQ menus, and book a matching vibe.</p>
            </div>

            <!-- Filter Pills -->
            <div class="filter-nav">
                <button class="filter-btn active" onclick="filterShowcase('all', this)">All Gatherings</button>
                <button class="filter-btn" onclick="filterShowcase('bbq', this)">Outdoor BBQ</button>
                <button class="filter-btn" onclick="filterShowcase('family', this)">Family Reunions</button>
                <button class="filter-btn" onclick="filterShowcase('alumni', this)">Alumni Mixers</button>
                <button class="filter-btn" onclick="filterShowcase('hightea', this)">High Tea & Lawn</button>
            </div>

            <!-- Cards Grid -->
            <div class="showcase-grid">
                <?php foreach ($previous_gatherings as $g): ?>
                    <div class="showcase-card" data-category="<?= htmlspecialchars($g['category']) ?>">
                        <div class="showcase-img-wrap">
                            <img src="<?= htmlspecialchars($g['image']) ?>" alt="<?= htmlspecialchars($g['title']) ?>" loading="lazy">
                            <span class="showcase-badge"><?= htmlspecialchars($g['category_lbl']) ?></span>
                            <span class="showcase-guests-pill"><i class="fas fa-users"></i> <?= $g['guests'] ?> Guests</span>
                        </div>
                        <div class="showcase-body">
                            <h3 class="showcase-title"><?= htmlspecialchars($g['title']) ?></h3>
                            <div class="showcase-venue">
                                <i class="fas fa-map-marker-alt" style="color:#10b981;"></i>
                                <?= htmlspecialchars($g['venue']) ?>
                            </div>
                            <p class="showcase-summary"><?= htmlspecialchars($g['summary']) ?></p>
                            <div class="showcase-footer">
                                <button type="button" class="btn btn-secondary btn-sm" 
                                        onclick="openGatheringDossier(<?= htmlspecialchars(json_encode($g)) ?>)">
                                    <i class="fas fa-eye"></i> Inspect Setup
                                </button>
                                <a href="<?= get_booking_url('GetTogetherBooking.php?vibe=' . urlencode($g['category']) . '&guests=' . $g['guests'], $is_logged_in) ?>" class="btn btn-primary btn-sm" style="background:#10b981; border-color:#10b981;">
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
                <span class="section-tag">Accredited Providers</span>
                <h2>Reunion BBQ, Canopy Rentals & Acoustic Entertainment</h2>
                <p>Browse transparent packages for live grills, village buffets, canopy marquee rentals, and sing-along acoustic performers.</p>
            </div>

            <div class="packages-grid">
                <?php if (empty($gt_packages)): ?>
                    <div style="grid-column:1/-1; text-align:center; padding:40px; color:var(--text-muted);">
                        No packages currently published. Use our Get Together Wizard to request custom catering and setups.
                    </div>
                <?php else: ?>
                    <?php foreach ($gt_packages as $pkg): ?>
                        <div class="package-card">
                            <span class="pkg-tag"><?= htmlspecialchars($pkg['service_name']) ?></span>
                            <h3 class="pkg-title"><?= htmlspecialchars($pkg['title']) ?></h3>
                            <div class="pkg-supplier">
                                <i class="fas fa-award" style="color:#10b981;"></i>
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
                            <a href="<?= get_booking_url('GetTogetherBooking.php?package_id=' . $pkg['listing_id'], $is_logged_in) ?>" class="btn btn-primary btn-sm" style="width:100%; text-align:center; background:#10b981; border-color:#10b981;">
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
                <span class="section-tag">Host Testimonials</span>
                <h2>What Reunion Organizers Say</h2>
            </div>

            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="stars-row">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "The live BBQ setup was top tier! Grand Royal kept the grilled skewers and burgers hot and fresh throughout the afternoon. The batch couldn't stop praising it."
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">DK</div>
                        <div class="author-meta">
                            <h4>Danushka K.</h4>
                            <p>Royal College 2012 Reunion Committee</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="stars-row">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "The village clay-pot buffet at our Bolgoda family get-together had that authentic rural Sri Lankan taste that brought back so many childhood memories."
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">PF</div>
                        <div class="author-meta">
                            <h4>Priyantha Fernandopulle</h4>
                            <p>Family Reunion Lead</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="stars-row">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "Our startup mixer at Galle Face Hotel ran like clockwork. The high-top tables and acoustic duo created the exact relaxed networking vibe we wanted."
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">MR</div>
                        <div class="author-meta">
                            <h4>Manesh Rodrigo</h4>
                            <p>Tech Community Organizer</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= FAQ ================= -->
        <section class="faq-wrap">
            <div class="section-header-box">
                <span class="section-tag">Planning Help</span>
                <h2>Frequently Asked Gathering Questions</h2>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>What happens if it rains during an outdoor BBQ gathering?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Through our Get Together Wizard, you can choose waterproof aluminum marquee tents with transparent side curtains. Our partners also set up dedicated grill rain canopies to ensure cooking continues uninterrupted.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Can we bring our own beverages or corkage-free drinks?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Yes! You can choose our beverage station add-on with ice chests, glassware, and bartenders, or coordinate your own drinks. Just mention this in Stage 4 of the booking wizard.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Do acoustic musicians allow guests to use microphones for batch speeches?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Yes, all acoustic packages booked through EVENTFLARE include dual wireless handheld microphones connected to the PA system for toasts, alumni speeches, and impromptu sing-alongs.
                </div>
            </div>
        </section>

        <!-- ================= BOTTOM CONVERSION BANNER ================= -->
        <div class="conversion-box">
            <h2>Plan a Gathering Everyone Will Remember</h2>
            <p>Step into our specialized 4-stage Get Together Booking Wizard to select your setting, BBQ menu, furniture rentals, and live acoustic music.</p>
            <div style="display:flex; justify-content:center; gap:15px; flex-wrap:wrap;">
                <a href="<?= get_booking_url('GetTogetherBooking.php', $is_logged_in) ?>" class="btn btn-primary" style="padding:16px 36px; font-size:16px; background:#10b981; border-color:#10b981;">
                    <i class="fas fa-handshake"></i> Start Get Together Wizard
                </a>
                <a href="../ChooseEvent.php" class="btn btn-secondary" style="padding:16px 30px; font-size:16px;">
                    <i class="fas fa-th-large"></i> Explore Other Celebrations
                </a>
            </div>
        </div>
    </div>

    <!-- ================= INTERACTIVE GET TOGETHER DOSSIER MODAL ================= -->
    <div class="modal-backdrop" id="gatheringDossierModal" onclick="closeModalOnBackdrop(event)">
        <div class="modal-card">
            <button type="button" class="modal-close-btn" onclick="closeGatheringDossier()">&times;</button>
            <span id="modalCategory" style="font-size:12px; font-weight:800; color:#10b981; text-transform:uppercase; letter-spacing:0.06em;"></span>
            <h2 id="modalTitle" style="font-size:24px; font-weight:800; color:var(--text-heading); margin:8px 0 16px;"></h2>
            
            <img id="modalImage" src="" alt="Gathering Setup" style="width:100%; height:260px; object-fit:cover; border-radius:16px; margin-bottom:20px; border:1px solid var(--card-border);">

            <p id="modalSummary" style="font-size:14px; color:var(--text-muted); line-height:1.7; margin-bottom:20px;"></p>

            <h4 style="font-size:15px; font-weight:700; color:var(--text-heading); margin-bottom:12px;">Assigned Food & Equipment Setup</h4>
            <div id="modalServices" class="modal-specs-list"></div>

            <div style="background:rgba(16, 185, 129, 0.08); border:1px solid rgba(16, 185, 129, 0.2); border-radius:14px; padding:16px; margin-top:20px;">
                <p id="modalQuote" style="font-size:13px; font-style:italic; color:var(--text-heading); margin:0 0 6px;"></p>
                <span id="modalClient" style="font-size:12px; font-weight:700; color:#10b981;"></span>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:25px;">
                <button type="button" class="btn btn-secondary" onclick="closeGatheringDossier()">Close</button>
                <a id="modalBookBtn" href="<?= get_booking_url('GetTogetherBooking.php', $is_logged_in) ?>" class="btn btn-primary" style="background:#10b981; border-color:#10b981;">
                    <i class="fas fa-handshake"></i> Book With This Vibe
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

        function openGatheringDossier(gt) {
            document.getElementById('modalCategory').textContent = gt.category_lbl + ' • ' + gt.guests + ' GUESTS';
            document.getElementById('modalTitle').textContent = gt.title;
            document.getElementById('modalImage').src = gt.image;
            document.getElementById('modalSummary').textContent = gt.summary;
            document.getElementById('modalQuote').textContent = gt.quote;
            document.getElementById('modalClient').textContent = '— ' + gt.client;

            const sList = document.getElementById('modalServices');
            sList.innerHTML = '';
            for (const [svcName, supDesc] of Object.entries(gt.services)) {
                const item = document.createElement('div');
                item.className = 'modal-spec-item';
                item.innerHTML = `
                    <div class="modal-spec-title">${svcName}</div>
                    <p class="modal-spec-val">${supDesc}</p>
                `;
                sList.appendChild(item);
            }

            const isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;
            const targetWizard = `GetTogetherBooking.php?vibe=${encodeURIComponent(gt.category)}&guests=${gt.guests}`;
            document.getElementById('modalBookBtn').href = isLoggedIn ? targetWizard : `../Login.php?redirect=${encodeURIComponent('events/' + targetWizard)}`;

            document.getElementById('gatheringDossierModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeGatheringDossier() {
            document.getElementById('gatheringDossierModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function closeModalOnBackdrop(e) {
            if (e.target.id === 'gatheringDossierModal') {
                closeGatheringDossier();
            }
        }

        function toggleFaq(el) {
            const item = el.closest('.faq-item');
            item.classList.toggle('active');
        }
    </script>
</body>
</html>
