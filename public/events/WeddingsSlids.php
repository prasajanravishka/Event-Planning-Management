<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../../config/database.php';
include __DIR__ . '/../../includes/navbar.php';

// 1. Fetch Wedding Services from Database
$wedding_services = [];
if (isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT s.* FROM services s 
                            JOIN event_types et ON s.event_type_id = et.event_type_id 
                            WHERE et.type_name = 'Weddings' OR et.event_type_id = 1 
                            ORDER BY s.priority_rank ASC");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $wedding_services[] = $row;
        }
        $stmt->close();
    }
}

// 2. Fetch Active Supplier Packages for Weddings
$wedding_packages = [];
if (isset($conn) && !$conn->connect_error) {
    $pkg_query = "SELECT sl.*, sup.business_name, sup.category, sup.contact_phone, sup.location, s.service_name 
                  FROM supplier_listings sl
                  JOIN suppliers sup ON sl.supplier_id = sup.id
                  JOIN services s ON sl.service_id = s.service_id
                  WHERE s.event_type_id = 1 AND sl.status = 'active'
                  ORDER BY sl.listing_id ASC";
    $pkg_res = $conn->query($pkg_query);
    if ($pkg_res) {
        while ($row = $pkg_res->fetch_assoc()) {
            $wedding_packages[] = $row;
        }
    }
}

// 3. Curated Previous Events Showcase
$previous_weddings = [
    [
        'id'          => 'bkg-wed-01',
        'title'       => 'Kasun & Dilhani’s Grand Coastal Celebration',
        'category'    => 'coastal',
        'category_lbl'=> 'Coastal & Beachfront',
        'venue'       => 'The Grand Cinnamon Cove Resort & Spa, Galle',
        'date'        => 'October 24, 2026',
        'guests'      => 280,
        'image'       => '../assets/images/W1.jpg',
        'tag'         => 'Traditional Poruwa & Coastal Lawn',
        'summary'     => 'A breathtaking sunset ceremony overlooking the Indian Ocean. Featured a handcrafted floral Poruwa on the manicured lawn, followed by a 3-course seafood banquet and 4K aerial cinema.',
        'services'    => [
            'Decor & Poruwa'      => 'Lotus Floral Elegance (Royal Poruwa & Fresh Lotus Floral Mandap)',
            'Catering'            => 'Grand Royal Banquet (Platinum 3-Course Buffet, 280 Pax)',
            'Photography & Drone' => 'Aurora Cinematography (4K Drone Coverage & Pre-Shoot Reel)',
            'Music & Atmosphere'  => 'Ranranga Cultural Troupe & Pulse DJ Live Beats'
        ],
        'quote'       => '"EVENTFLARE took care of all vendor coordination. Our coastal wedding in Galle was completely stress-free!"',
        'client'      => 'Kasun & Dilhani Perera'
    ],
    [
        'id'          => 'bkg-wed-06',
        'title'       => 'Malithi & Roshan’s Royal Heritage Kandyan Wedding',
        'category'    => 'poruwa',
        'category_lbl'=> 'Traditional Poruwa',
        'venue'       => 'The Kingsbury Victorian Ballroom, Colombo',
        'date'        => 'December 18, 2026',
        'guests'      => 320,
        'image'       => '../assets/images/W2.jpg',
        'tag'         => 'Authentic Cultural Grandeur',
        'summary'     => 'A majestic traditional celebration filled with royal Sinhala customs. Honored with a 12-member Kandyan dance procession, sacred Ashtaka blessings, and exquisite gold-foil confectionery.',
        'services'    => [
            'Cultural Ensemble'   => 'Ranranga Traditional Ensemble (Kandyan Drummers & Jayamangala Choir)',
            'Decor & Floral'      => 'Lotus Floral Elegance (Carved Heritage Poruwa & Oil Lamp Setup)',
            'Confectionery'       => 'Sweet Symphony (3-Tier Belgian Chocolate & Gold Foil Cake)',
            'Photography'         => 'Aurora Cinematography (Multi-camera 4K Ceremony Feature)'
        ],
        'quote'       => '"The traditional dancers and choir coordination were flawless. Every blessing stanza echoed with royal elegance."',
        'client'      => 'Malithi De Silva'
    ],
    [
        'id'          => 'bkg-wed-03',
        'title'       => 'Ananya & Chathura’s Twilight Garden Vows',
        'category'    => 'garden',
        'category_lbl'=> 'Garden Romance',
        'venue'       => 'Waters Edge Grand Pavilion, Battaramulla',
        'date'        => 'June 15, 2026',
        'guests'      => 180,
        'image'       => '../assets/images/look-from-white-chairs-arranged-wedding-ceremony.jpg',
        'tag'         => 'Fairy-Light Botanical Garden',
        'summary'     => 'An enchanting outdoor evening celebration under canopies of fairy lights and vintage wooden cross-back seating. Featured bespoke artisanal grazing stations and an unplugged acoustic trio.',
        'services'    => [
            'Outdoor Staging'     => 'Lotus Floral Decor (Vintage Cross-Back Chairs & Canopy)',
            'Bar & Drinks'        => 'Velvet Mobile Cocktail Bar (Botanical Spritzers & Mocktails)',
            'Artisan Grazing'     => 'Sweet Symphony (Parisian Macaron Grazing Table)',
            'Acoustic Music'      => 'Pulse Live Beats (Unplugged Acoustic Trio & Vocals)'
        ],
        'quote'       => '"Our guests are still talking about the fairytale fairy-light setup and the custom dessert station!"',
        'client'      => 'Ananya & Chathura'
    ],
    [
        'id'          => 'bkg-wed-04',
        'title'       => 'Dinesh & Senuri’s Minimalist Beachfront Vows',
        'category'    => 'coastal',
        'category_lbl'=> 'Coastal & Beachfront',
        'venue'       => 'Jetwing Lighthouse Oceanside, Galle',
        'date'        => 'February 12, 2026',
        'guests'      => 150,
        'image'       => '../assets/images/wedding-couple-best-friends-are-drinking-champagne-celebrating-park-wedding-day.jpg',
        'tag'         => 'Boho Sunset Chic',
        'summary'     => 'An intimate seaside union illuminated by golden-hour sunset skies. Featured a natural driftwood arch, champagne toast reception, and candid documentary photojournalism.',
        'services'    => [
            'Seaside Arch'        => 'Lotus Floral Elegance (Organic Pampas & Orchid Arch)',
            'Hospitality'         => 'Grand Royal Catering (Artisan Canapés & Cocktail Reception)',
            'Photography'         => 'Aurora Cinematography (Golden Hour Drone Portraits)',
            'Security & Valet'    => 'Aegis Event Security (VIP Guest Escort & Valet Entry)'
        ],
        'quote'       => '"Seamless scheduling from start to finish. Everything matched our minimalist vision."',
        'client'      => 'Dinesh & Senuri'
    ],
    [
        'id'          => 'bkg-wed-05',
        'title'       => 'Nadeesha & Kavindu’s Palatial Crystal Ballroom Reception',
        'category'    => 'ballroom',
        'category_lbl'=> 'Luxury Ballroom',
        'venue'       => 'Shangri-La Main Ballroom, Colombo 02',
        'date'        => 'January 10, 2026',
        'guests'      => 420,
        'image'       => '../assets/images/W4.jpg',
        'tag'         => 'Modern Luxury & Crystal Chandeliers',
        'summary'     => 'A high-profile grand wedding gala with imported chandeliers, concert-grade acoustic engineering, dual LED wall visuals, and an extravagant 5-course international dining experience.',
        'services'    => [
            'Ballroom Rigging'    => 'Lumina Audio Visual (Line Array Sound & Intelligent Uplighting)',
            'Catering'            => 'Grand Royal Banquet (5-Course Plated & Carvery Feast)',
            'Entertainment'       => 'Pulse DJ & Live Beats (Saxophone & Club Hits DJ Setup)',
            'Custom Cake'         => 'Sweet Symphony (4-Tier Hand-Painted Floral Cake)'
        ],
        'quote'       => '"A truly world-class banquet. The lighting, sound, and dining were beyond five stars."',
        'client'      => 'Nadeesha & Kavindu'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wedding Planning & Previous Events Showcase - EVENTFLARE</title>
    <style>
        /* Ambient Glow Background Orbs */
        .orb {
            position: absolute;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(236, 72, 153, 0.03) 70%);
            filter: blur(70px);
            z-index: -1;
            pointer-events: none;
        }
        .orb-1 { top: 5%; right: -10%; }
        .orb-2 { top: 35%; left: -15%; }
        .orb-3 { bottom: 15%; right: -10%; }

        /* Main Container */
        .wedding-page {
            max-width: 1200px;
            margin: 100px auto 80px;
            padding: 0 20px;
        }

        /* Hero Section */
        .wedding-hero {
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
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.25);
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .hero-content h1 {
            font-size: 48px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--text-heading) 20%, var(--primary) 70%, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-content p.subtitle {
            font-size: 17px;
            color: var(--text-muted);
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .hero-metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 35px;
            padding: 18px 20px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            backdrop-filter: blur(12px);
        }

        .metric-item {
            text-align: center;
        }

        .metric-item .val {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-heading);
            display: block;
        }

        .metric-item .lbl {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .hero-cta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        /* Hero Visual Slider Mosaic */
        .hero-visual-card {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--card-border);
            box-shadow: 0 20px 45px rgba(139, 92, 246, 0.15);
            height: 480px;
        }

        .hero-visual-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .hero-visual-card:hover img {
            transform: scale(1.04);
        }

        .hero-card-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 40%, rgba(15, 10, 30, 0.85) 100%);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 30px;
            color: #fff;
        }

        .hero-card-overlay h3 {
            color: #fff;
            font-size: 24px;
            margin-bottom: 6px;
        }

        .hero-card-overlay p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 14px;
            margin: 0;
        }

        /* Section Headings */
        .section-title-wrap {
            text-align: center;
            margin: 70px 0 35px;
        }

        .section-tag {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 8px;
            display: block;
        }

        .section-title-wrap h2 {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-heading), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
        }

        .section-title-wrap p {
            font-size: 15px;
            color: var(--text-muted);
            max-width: 650px;
            margin: 0 auto;
        }

        /* Filter Tabs for Previous Events */
        .filter-tabs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .filter-btn {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-muted);
            padding: 10px 22px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-smooth);
        }

        .filter-btn:hover, .filter-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }

        /* Previous Events Grid */
        .weddings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .wedding-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
            display: flex;
            flex-direction: column;
        }

        .wedding-card:hover {
            transform: translateY(-8px);
            border-color: rgba(139, 92, 246, 0.35);
            box-shadow: 0 20px 40px rgba(139, 92, 246, 0.15), 0 0 15px var(--card-glow);
        }

        .wedding-img-wrap {
            position: relative;
            height: 240px;
            overflow: hidden;
        }

        .wedding-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .wedding-card:hover .wedding-img-wrap img {
            transform: scale(1.06);
        }

        .wedding-cat-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(15, 10, 30, 0.75);
            backdrop-filter: blur(8px);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .wedding-guests-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(139, 92, 246, 0.85);
            backdrop-filter: blur(8px);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .wedding-card-body {
            padding: 25px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .wedding-card-body h3 {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .wedding-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .wedding-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .wedding-summary {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .wedding-card-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .wedding-card-actions .btn {
            flex: 1;
            padding: 10px 14px;
            font-size: 13px;
        }

        /* 6 Core Services Grid */
        .services-catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 60px;
        }

        .service-pill-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
            display: flex;
            flex-direction: column;
        }

        .service-pill-card:hover {
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateY(-4px);
        }

        .service-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .service-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(139, 92, 246, 0.12);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .badge-req {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge-req.mandatory {
            background: rgba(236, 72, 153, 0.15);
            color: #db2777;
            border: 1px solid rgba(236, 72, 153, 0.3);
        }
        .badge-req.optional {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .service-pill-card h4 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 8px;
        }

        .service-pill-card p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        .service-footer-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--text-muted);
            border-top: 1px solid var(--card-border);
            padding-top: 12px;
        }

        /* Supplier Packages Grid */
        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 25px;
            margin-bottom: 60px;
        }

        .pkg-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
        }

        .pkg-card:hover {
            transform: translateY(-6px);
            border-color: rgba(139, 92, 246, 0.35);
        }

        .pkg-img {
            height: 180px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .pkg-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .pkg-vendor {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .pkg-body h4 {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 10px;
            color: var(--text-heading);
            line-height: 1.3;
        }

        .pkg-price-row {
            margin: 15px 0 20px;
            padding: 10px 14px;
            background: rgba(139, 92, 246, 0.06);
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }

        .pkg-price {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-heading);
        }

        .pkg-type {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: lowercase;
        }

        .pkg-body .btn {
            margin-top: auto;
            width: 100%;
        }

        /* 4-Step Planning Roadmap */
        .steps-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 60px;
        }

        .step-box {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 26px 20px;
            text-align: center;
            box-shadow: var(--shadow-premium);
            position: relative;
            transition: var(--transition-smooth);
        }

        .step-box:hover {
            transform: translateY(-4px);
            border-color: rgba(139, 92, 246, 0.3);
        }

        .step-num {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            font-weight: 800;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .step-box h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 8px;
        }

        .step-box p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Catering Calculation Callout Banner */
        .catering-banner {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(99, 102, 241, 0.15));
            border: 1px solid rgba(139, 92, 246, 0.25);
            border-radius: 20px;
            padding: 35px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 70px;
            backdrop-filter: blur(12px);
        }

        .catering-banner-text h3 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 8px;
        }

        .catering-banner-text p {
            font-size: 14px;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0;
        }

        /* Bottom Final CTA */
        .final-cta-card {
            background: linear-gradient(135deg, #1f143d 0%, #301a61 100%);
            border-radius: 24px;
            padding: 50px 40px;
            text-align: center;
            color: #fff;
            margin-bottom: 50px;
            border: 1px solid rgba(139, 92, 246, 0.3);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }

        .final-cta-card h2 {
            font-size: 38px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 15px;
        }

        .final-cta-card p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.85);
            max-width: 650px;
            margin: 0 auto 30px;
            line-height: 1.7;
        }

        .final-cta-buttons {
            display: flex;
            justify-content: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .final-cta-buttons .btn {
            min-width: 200px;
        }

        /* Wedding Dossier Interactive Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(10, 8, 20, 0.7);
            backdrop-filter: blur(8px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            max-width: 750px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            padding: 35px;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-main);
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition-smooth);
        }

        .modal-close-btn:hover {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
        }

        .modal-img {
            width: 100%;
            height: 260px;
            object-fit: cover;
            border-radius: 16px;
            margin-bottom: 20px;
        }

        .modal-services-list {
            margin: 20px 0;
            background: rgba(139, 92, 246, 0.04);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 16px 20px;
        }

        .modal-service-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 14px;
        }
        .modal-service-row:last-child {
            border-bottom: none;
        }
        .modal-service-lbl {
            font-weight: 700;
            color: var(--text-heading);
        }
        .modal-service-val {
            color: var(--text-muted);
        }

        /* Responsive Breakpoints */
        @media (max-width: 900px) {
            .wedding-hero {
                grid-template-columns: 1fr;
            }
            .hero-content {
                text-align: center;
            }
            .hero-metrics {
                grid-template-columns: repeat(2, 1fr);
            }
            .hero-cta {
                justify-content: center;
            }
            .hero-visual-card {
                height: 360px;
            }
            .steps-container {
                grid-template-columns: repeat(2, 1fr);
            }
            .catering-banner {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }

        @media (max-width: 600px) {
            .wedding-page {
                margin-top: 80px;
            }
            .hero-content h1 {
                font-size: 34px;
            }
            .weddings-grid {
                grid-template-columns: 1fr;
            }
            .steps-container {
                grid-template-columns: 1fr;
            }
            .final-cta-card {
                padding: 35px 20px;
            }
            .final-cta-card h2 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <!-- Background Glow Elements -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="wedding-page">
        <!-- 1. HERO SECTION -->
        <section class="wedding-hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-gem"></i> Curated Luxury Wedding Planning
                </div>
                <h1>Crafting Your Timeless<br>Wedding Love Story</h1>
                <p class="subtitle">
                    Celebrate your devotion with bespoke floral Poruwas, 5-star banquets, and cinematic memories. Browse through our executed weddings below, handpick accredited suppliers, and book your custom celebration.
                </p>

                <!-- High-Level Metric Proof -->
                <div class="hero-metrics">
                    <div class="metric-item">
                        <span class="val">150+</span>
                        <span class="lbl">Weddings Planned</span>
                    </div>
                    <div class="metric-item">
                        <span class="val">10</span>
                        <span class="lbl">Supplier Categories</span>
                    </div>
                    <div class="metric-item">
                        <span class="val">4.9 ★</span>
                        <span class="lbl">Couple Rating</span>
                    </div>
                    <div class="metric-item">
                        <span class="val">100%</span>
                        <span class="lbl">Verified Vendors</span>
                    </div>
                </div>

                <div class="hero-cta">
                    <a href="WeddingBooking.php" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Book Wedding Now
                    </a>
                    <a href="#past-weddings" class="btn btn-secondary">
                        <i class="fas fa-images"></i> View Past Weddings
                    </a>
                    <a href="../Food.php" class="btn btn-secondary">
                        <i class="fas fa-calculator"></i> Catering Estimator
                    </a>
                </div>
            </div>

            <div class="hero-visual-card">
                <img src="../assets/images/W1.jpg" alt="Luxury Wedding Setup">
                <div class="hero-card-overlay">
                    <span style="font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#e9d5ff; font-weight:700;">Featured Event</span>
                    <h3>The Galle Coastal Wedding</h3>
                    <p>Poruwa ceremony & luxury 3-course banquet planned by EVENTFLARE</p>
                </div>
            </div>
        </section>

        <!-- 2. PREVIOUS WEDDINGS SHOWCASE -->
        <section id="past-weddings">
            <div class="section-title-wrap">
                <span class="section-tag">Real Celebrations</span>
                <h2>Previous Weddings Showcase</h2>
                <p>Browse authentic weddings planned and coordinated through EVENTFLARE. Every celebration is custom designed with verified caterers, decorators, and cultural artists.</p>
            </div>

            <!-- Filter Controls -->
            <div class="filter-tabs">
                <button class="filter-btn active" data-filter="all">All Weddings (5)</button>
                <button class="filter-btn" data-filter="poruwa">Traditional Poruwa</button>
                <button class="filter-btn" data-filter="coastal">Coastal & Beachfront</button>
                <button class="filter-btn" data-filter="ballroom">Luxury Ballroom</button>
                <button class="filter-btn" data-filter="garden">Garden Romance</button>
            </div>

            <!-- Weddings Grid -->
            <div class="weddings-grid">
                <?php foreach ($previous_weddings as $idx => $wed): ?>
                    <div class="wedding-card" data-category="<?= htmlspecialchars($wed['category']) ?>">
                        <div class="wedding-img-wrap">
                            <img src="<?= htmlspecialchars($wed['image']) ?>" alt="<?= htmlspecialchars($wed['title']) ?>">
                            <div class="wedding-cat-badge"><?= htmlspecialchars($wed['category_lbl']) ?></div>
                            <div class="wedding-guests-badge">
                                <i class="fas fa-users"></i> <?= htmlspecialchars($wed['guests']) ?> Guests
                            </div>
                        </div>
                        <div class="wedding-card-body">
                            <h3><?= htmlspecialchars($wed['title']) ?></h3>
                            <div class="wedding-meta">
                                <span><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> <?= htmlspecialchars($wed['venue']) ?></span>
                                <span><i class="fas fa-calendar-alt" style="color:var(--primary);"></i> <?= htmlspecialchars($wed['date']) ?></span>
                            </div>
                            <p class="wedding-summary"><?= htmlspecialchars($wed['summary']) ?></p>
                            
                            <div class="wedding-card-actions">
                                <button type="button" class="btn btn-secondary view-dossier-btn" 
                                        data-index="<?= $idx ?>">
                                    <i class="fas fa-info-circle"></i> View Dossier
                                </button>
                                <a href="WeddingBooking.php?style=<?= urlencode($wed['category']) ?>&venue=<?= urlencode($wed['venue']) ?>&guests=<?= $wed['guests'] ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-check"></i> Book Similar
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 3. SIX CORE WEDDING SERVICES CATALOG -->
        <section style="margin-top: 80px;">
            <div class="section-title-wrap">
                <span class="section-tag">Platform Catalog</span>
                <h2>6 Essential Wedding Services Included</h2>
                <p>From the solemn Poruwa rituals to drone cinematography, our platform connects you directly with certified specialists across all wedding essentials.</p>
            </div>

            <div class="services-catalog-grid">
                <?php if (!empty($wedding_services)): ?>
                    <?php 
                    $service_icons = [
                        'Poruwa/Decorators'            => 'fa-archway',
                        'Ashtaka Narrator'             => 'fa-scroll',
                        'Jayamangala Gatha Choir'      => 'fa-microphone-alt',
                        'Kandyan Dancers and Drummers' => 'fa-drum',
                        'Catering'                     => 'fa-utensils',
                        'Photography & Videography'    => 'fa-camera-retro'
                    ];
                    foreach ($wedding_services as $svc): 
                        $icon = $service_icons[$svc['service_name']] ?? 'fa-star';
                        $is_req = (int)($svc['is_required'] ?? 0);
                    ?>
                        <div class="service-pill-card">
                            <div class="service-header-row">
                                <div class="service-icon-box">
                                    <i class="fas <?= $icon ?>"></i>
                                </div>
                                <span class="badge-req <?= $is_req ? 'mandatory' : 'optional' ?>">
                                    <?= $is_req ? 'Mandatory Pillar' : 'Optional Add-on' ?>
                                </span>
                            </div>
                            <h4><?= htmlspecialchars($svc['service_name']) ?></h4>
                            <p><?= htmlspecialchars($svc['notes'] ?? $svc['description']) ?></p>
                            <div class="service-footer-info">
                                <span><i class="fas fa-users"></i> Up to <?= htmlspecialchars($svc['typical_capacity'] ?? 300) ?> Guests</span>
                                <span style="font-weight:600; color:var(--primary);">Rank #<?= htmlspecialchars($svc['priority_rank']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; grid-column:span 3; color:var(--text-muted);">Wedding catalog services currently updating.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- 4. FEATURED SUPPLIER WEDDING PACKAGES -->
        <?php if (!empty($wedding_packages)): ?>
        <section style="margin-top: 50px;">
            <div class="section-title-wrap">
                <span class="section-tag">Direct Vendor Packages</span>
                <h2>Featured Wedding Packages & Listings</h2>
                <p>Browse authentic packages created by our verified wedding suppliers with transparent pricing and live capacities.</p>
            </div>

            <div class="packages-grid">
                <?php foreach ($wedding_packages as $pkg): ?>
                    <div class="pkg-card">
                        <div class="pkg-img" style="background-image: url('<?= !empty($pkg['image_url']) ? htmlspecialchars($pkg['image_url']) : '../assets/images/wedding.jpg' ?>');"></div>
                        <div class="pkg-body">
                            <div class="pkg-vendor"><?= htmlspecialchars($pkg['business_name']) ?></div>
                            <h4><?= htmlspecialchars($pkg['title']) ?></h4>
                            <p style="font-size:13px; color:var(--text-muted); line-height:1.5; margin-bottom:12px;">
                                <?= htmlspecialchars(mb_strimwidth($pkg['description'] ?? '', 0, 100, '...')) ?>
                            </p>
                            
                            <div class="pkg-price-row">
                                <div>
                                    <span class="pkg-price">Rs. <?= number_format($pkg['price'], 2) ?></span>
                                    <span class="pkg-type">/ <?= str_replace('_', ' ', htmlspecialchars($pkg['price_type'])) ?></span>
                                </div>
                                <div style="font-size:12px; color:var(--text-muted);">
                                    <i class="fas fa-user-friends"></i> <?= htmlspecialchars($pkg['capacity'] ?? 200) ?>
                                </div>
                            </div>

                            <a href="WeddingBooking.php?package_id=<?= $pkg['listing_id'] ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-shopping-cart"></i> Book With Package
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- 5. HOW WE PLAN YOUR WEDDING (4 STEPS) -->
        <section style="margin-top: 60px;">
            <div class="section-title-wrap">
                <span class="section-tag">Streamlined Flow</span>
                <h2>Plan Your Wedding in 4 Simple Steps</h2>
                <p>Our intelligent platform coordinates everything from catering estimation to verified vendor assignment.</p>
            </div>

            <div class="steps-container">
                <div class="step-box">
                    <div class="step-num">1</div>
                    <h4>Pick Venue & Style</h4>
                    <p>Select your preferred setting—from beachfront pavilions to royal Kandyan Poruwa ballrooms.</p>
                </div>

                <div class="step-box">
                    <div class="step-num">2</div>
                    <h4>Calculate Catering</h4>
                    <p>Use our parametric catering budget tool to estimate buffet, dessert, and beverage costs dynamically.</p>
                </div>

                <div class="step-box">
                    <div class="step-num">3</div>
                    <h4>Handpick Verified Vendors</h4>
                    <p>Browse our verified decorators, caterers, cinematographers, and choir groups and choose the ones you love.</p>
                </div>

                <div class="step-box">
                    <div class="step-num">4</div>
                    <h4>Track & PDF Dossier</h4>
                    <p>Monitor reservation milestones in your client portal and export your complete event dossier to PDF.</p>
                </div>
            </div>
        </section>

        <!-- 6. CATERING CALCULATOR CALLOUT BANNER -->
        <section>
            <div class="catering-banner">
                <div class="catering-banner-text">
                    <h3>Need to Estimate Your Food & Beverage Budget?</h3>
                    <p>Calculate your catering expenditures across buffets, drinks, desserts, and snacks with our live parametric tool before completing your booking.</p>
                </div>
                <div>
                    <a href="../Food.php" class="btn btn-primary" style="white-space:nowrap;">
                        <i class="fas fa-calculator"></i> Launch Cost Calculator
                    </a>
                </div>
            </div>
        </section>

        <!-- 7. COUPLE REVIEWS & TESTIMONIALS -->
        <section style="margin-bottom: 70px;">
            <div class="section-title-wrap">
                <span class="section-tag">Testimonials</span>
                <h2>Words from Our Happy Couples</h2>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:25px;">
                <div class="glass-card" style="padding:25px;">
                    <div style="color:#f59e0b; margin-bottom:12px; font-size:16px;">★★★★★</div>
                    <p style="font-size:14px; color:var(--text-main); font-style:italic; line-height:1.7; margin-bottom:15px;">
                        "EVENTFLARE took care of all vendor coordination. Our coastal wedding in Galle was completely stress-free! The drone footage by Aurora was breathtaking."
                    </p>
                    <div style="font-weight:700; font-size:14px; color:var(--text-heading);">— Kasun & Dilhani Perera</div>
                    <div style="font-size:12px; color:var(--text-muted);">Grand Cinnamon Cove, Galle</div>
                </div>

                <div class="glass-card" style="padding:25px;">
                    <div style="color:#f59e0b; margin-bottom:12px; font-size:16px;">★★★★★</div>
                    <p style="font-size:14px; color:var(--text-main); font-style:italic; line-height:1.7; margin-bottom:15px;">
                        "The traditional dancers and choir coordination were flawless. Every blessing stanza echoed with royal elegance. Having our budget and supplier list in one PDF dossier made life so easy."
                    </p>
                    <div style="font-weight:700; font-size:14px; color:var(--text-heading);">— Malithi De Silva</div>
                    <div style="font-size:12px; color:var(--text-muted);">Kingsbury Ballroom, Colombo</div>
                </div>

                <div class="glass-card" style="padding:25px;">
                    <div style="color:#f59e0b; margin-bottom:12px; font-size:16px;">★★★★★</div>
                    <p style="font-size:14px; color:var(--text-main); font-style:italic; line-height:1.7; margin-bottom:15px;">
                        "Our guests are still raving about the fairy-light setup and the custom dessert station. Being able to pre-calculate catering costs in Food.php kept us right on budget!"
                    </p>
                    <div style="font-weight:700; font-size:14px; color:var(--text-heading);">— Ananya & Chathura</div>
                    <div style="font-size:12px; color:var(--text-muted);">Waters Edge, Battaramulla</div>
                </div>
            </div>
        </section>

        <!-- 8. FINAL BOTTOM CTA -->
        <section>
            <div class="final-cta-card">
                <h2>Ready to Begin Your Wedding Story?</h2>
                <p>Let EVENTFLARE connect you with accredited vendors, manage your event timeline, and turn your dream celebration into reality.</p>
                <div class="final-cta-buttons">
                    <a href="WeddingBooking.php" class="btn btn-primary" style="background:#fff; color:var(--primary); font-weight:700;">
                        <i class="fas fa-calendar-check"></i> Launch Wedding Wizard
                    </a>
                    <a href="../ChooseEvent.php" class="btn btn-secondary" style="border-color:rgba(255,255,255,0.4); color:#fff;">
                        <i class="fas fa-th-large"></i> Explore Other Events
                    </a>
                    <a href="../Contact.php" class="btn btn-secondary" style="border-color:rgba(255,255,255,0.4); color:#fff;">
                        <i class="fas fa-envelope"></i> Contact Coordinator
                    </a>
                </div>
            </div>
        </section>
    </div>

    <!-- 9. WEDDING DOSSIER INTERACTIVE MODAL -->
    <div class="modal-backdrop" id="dossierModal">
        <div class="modal-card">
            <button class="modal-close-btn" id="closeModalBtn"><i class="fas fa-times"></i></button>
            <img src="" alt="Wedding Event" class="modal-img" id="modalImg">
            <span class="hero-badge" id="modalBadge" style="margin-bottom:10px;"></span>
            <h2 id="modalTitle" style="font-size:26px; font-weight:800; margin-bottom:6px; color:var(--text-heading);"></h2>
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">
                <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> <span id="modalVenue"></span> &bull; 
                <i class="fas fa-users" style="color:var(--primary);"></i> <span id="modalGuests"></span> &bull;
                <i class="fas fa-calendar-alt" style="color:var(--primary);"></i> <span id="modalDate"></span>
            </div>
            <p id="modalSummary" style="font-size:14px; color:var(--text-main); line-height:1.7; margin-bottom:20px;"></p>
            
            <h4 style="font-size:16px; font-weight:700; color:var(--text-heading); margin-bottom:8px;">Assigned Service Breakdown:</h4>
            <div class="modal-services-list" id="modalServicesList"></div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:25px;">
                <button type="button" class="btn btn-secondary" id="modalCloseAction">Close</button>
                <a href="WeddingBooking.php" class="btn btn-primary" id="modalBookBtn">
                    <i class="fas fa-check"></i> Book With This Style
                </a>
            </div>
        </div>
    </div>

    <!-- JavaScript Controllers -->
    <script>
        const weddingsData = <?php echo json_encode($previous_weddings); ?>;

        // 1. Filter Tabs Logic
        const filterBtns = document.querySelectorAll('.filter-btn');
        const weddingCards = document.querySelectorAll('.wedding-card');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const filter = btn.getAttribute('data-filter');
                weddingCards.forEach(card => {
                    const cardCat = card.getAttribute('data-category');
                    if (filter === 'all' || cardCat === filter) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // 2. Dossier Modal Logic
        const modal = document.getElementById('dossierModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const modalCloseAction = document.getElementById('modalCloseAction');
        const viewButtons = document.querySelectorAll('.view-dossier-btn');

        viewButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.getAttribute('data-index'));
                const data = weddingsData[idx];
                if (!data) return;

                document.getElementById('modalImg').src = data.image;
                document.getElementById('modalBadge').textContent = data.category_lbl;
                document.getElementById('modalTitle').textContent = data.title;
                document.getElementById('modalVenue').textContent = data.venue;
                document.getElementById('modalGuests').textContent = data.guests + ' Guests';
                document.getElementById('modalDate').textContent = data.date;
                document.getElementById('modalSummary').textContent = data.summary;

                const servicesContainer = document.getElementById('modalServicesList');
                servicesContainer.innerHTML = '';
                for (const [key, val] of Object.entries(data.services)) {
                    const row = document.createElement('div');
                    row.className = 'modal-service-row';
                    row.innerHTML = `<span class="modal-service-lbl">${key}</span><span class="modal-service-val">${val}</span>`;
                    servicesContainer.appendChild(row);
                }

                const bookBtn = document.getElementById('modalBookBtn');
                bookBtn.href = `WeddingBooking.php?style=${encodeURIComponent(data.category)}&venue=${encodeURIComponent(data.venue)}&guests=${data.guests}`;

                modal.classList.add('open');
            });
        });

        function closeModal() {
            modal.classList.remove('open');
        }

        closeModalBtn.addEventListener('click', closeModal);
        modalCloseAction.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    </script>
</body>
</html>
