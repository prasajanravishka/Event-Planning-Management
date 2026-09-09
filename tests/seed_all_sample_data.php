<?php
/**
 * seed_all_sample_data.php
 * 
 * Seeds comprehensive, high-quality sample data covering:
 * - Admin accounts (system admin, operations lead, financial auditor)
 * - User / Buyer accounts (Kasun, Dilhani, Nimal, Ananya, Chathura, Malithi)
 * - Full Supplier Range:
 *     1. Catering (Grand Royal Banquet & Catering)
 *     2. Decorators & Florals (Lotus Floral Elegance & Decor)
 *     3. Audio/Visual & Stage Rigging (Lumina Audio Visual)
 *     4. Hotel Venues & Accommodations (The Grand Cinnamon Cove Resort & Spa)
 *     5. DJs & Live Artists (Pulse DJ & Live Beats Production)
 *     6. Photography & Videography (Aurora Cinematography & Photography)
 *     7. Bakeries & Confectioners (Sweet Symphony Artisan Bakery)
 *     8. Traditional Cultural Performers (Ranranga Cultural Troupe & Choirs)
 *     9. Event Security & Crowd Control (Aegis Event Security)
 *    10. Mobile Bar & Mixology (Velvet Mobile Cocktail Bar)
 * - Supplier services mappings (linking suppliers to catalogue services)
 * - Detailed inventory & package listings with varied price types
 * - Diverse Bookings across all 5 statuses (pending, confirmed, in_progress, completed, cancelled)
 * - Event Extras (equipment, food styling)
 * - Booking Services (supplier assignment with cost and confirmation status)
 * - Budgets & Expense Logs (detailed buffet, beverage, dessert, snack costs, variances)
 * - Contact Inquiries (read & unread customer messages)
 */

require_once __DIR__ . '/../config/database.php';

echo "=======================================================\n";
echo " Seeding Comprehensive EVENTFLARE Sample Data\n";
echo "=======================================================\n\n";

// Password hashes:
// admin123  => $2y$10$5CoyYp2jWvYbjQrrJuantOxjaI8jOpXwFma2XC83KRnnjH6hAzSGi
// password123 => $2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S
$admin_hash = '$2y$10$5CoyYp2jWvYbjQrrJuantOxjaI8jOpXwFma2XC83KRnnjH6hAzSGi';
$pass_hash  = '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S';

// -------------------------------------------------------
// 1. Ensure `user_id` column exists in `bookings`
// -------------------------------------------------------
$col_check = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'user_id'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE `bookings` ADD COLUMN `user_id` INT DEFAULT NULL AFTER `BookingID`");
    try {
        $conn->query("ALTER TABLE `bookings` ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL");
    } catch (Exception $e) {}
    echo "[SCHEMA] Added 'user_id' column to bookings table.\n";
}

// -------------------------------------------------------
// 2. Seed Admin Table
// -------------------------------------------------------
echo "\n--- Seeding Admins ---\n";
$admins = [
    [
        'username' => 'admin',
        'fullname' => 'System Administrator',
        'email'    => 'admin@eventflare.com',
        'password' => $admin_hash
    ],
    [
        'username' => 'sarah.admin',
        'fullname' => 'Sarah Alwis (Event Operations Lead)',
        'email'    => 'sarah.alwis@eventflare.com',
        'password' => $admin_hash
    ],
    [
        'username' => 'dilan.audit',
        'fullname' => 'Dilan Jayasinghe (Financial Auditor)',
        'email'    => 'dilan.audit@eventflare.com',
        'password' => $admin_hash
    ]
];

foreach ($admins as $adm) {
    $stmt = $conn->prepare("SELECT id FROM `admin` WHERE username = ?");
    $stmt->bind_param("s", $adm['username']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO `admin` (username, fullname, email, password) VALUES (?, ?, ?, ?)");
        $ins->bind_param("ssss", $adm['username'], $adm['fullname'], $adm['email'], $adm['password']);
        $ins->execute();
        echo "  [+] Created admin '{$adm['username']}' (Password: admin123)\n";
        $ins->close();
    } else {
        $row = $res->fetch_assoc();
        $upd = $conn->prepare("UPDATE `admin` SET fullname = ?, email = ?, password = ? WHERE id = ?");
        $upd->bind_param("sssi", $adm['fullname'], $adm['email'], $adm['password'], $row['id']);
        $upd->execute();
        echo "  [*] Updated admin '{$adm['username']}'\n";
        $upd->close();
    }
    $stmt->close();
}

// -------------------------------------------------------
// 3. Seed Users (Admins, Buyers, Suppliers)
// -------------------------------------------------------
echo "\n--- Seeding Users Table ---\n";
$all_users = [
    // Admins in users table (ensures Login.php recognizes admin)
    ['admin', 'System Administrator', 'admin@eventflare.com', $admin_hash, 'admin'],
    ['sarah.admin', 'Sarah Alwis', 'sarah.alwis@eventflare.com', $admin_hash, 'admin'],
    ['dilan.audit', 'Dilan Jayasinghe', 'dilan.audit@eventflare.com', $admin_hash, 'admin'],

    // Buyers / Clients
    ['kasun.perera', 'Kasun Perera', 'kasun.perera@gmail.com', $pass_hash, 'buyer'],
    ['dilhani.s', 'Dilhani Senanayake', 'dilhani.s@outlook.com', $pass_hash, 'buyer'],
    ['nimal.fernando', 'Nimal Fernando', 'nimal.fernando@yahoo.com', $pass_hash, 'buyer'],
    ['ananya.sharma', 'Ananya Sharma', 'ananya.sharma@gmail.com', $pass_hash, 'buyer'],
    ['chathura.k', 'Chathura Kulatunga', 'chathura.k@hotmail.com', $pass_hash, 'buyer'],
    ['malithi.desilva', 'Malithi De Silva', 'malithi.desilva@gmail.com', $pass_hash, 'buyer'],

    // Suppliers (Covering full supplier range)
    ['grand_royal_catering', 'Chef Rohan Jayawardena', 'info@grandroyalcatering.lk', $pass_hash, 'supplier'],
    ['lotus_florals', 'Manel Wickramasinghe', 'design@lotusflorals.lk', $pass_hash, 'supplier'],
    ['lumina_av_sound', 'Dinesh Fonseka', 'events@luminaav.lk', $pass_hash, 'supplier'],
    ['cinnamon_cove_hotel', 'Amanda Ratnayake (GM)', 'banquets@cinnamoncove.com', $pass_hash, 'supplier'],
    ['dj_pulse_entertainment', 'Kevin Samarasinghe', 'booking@djpulse.lk', $pass_hash, 'supplier'],
    ['aurora_studios', 'Nuwan Rathnayake', 'hello@aurorastudios.lk', $pass_hash, 'supplier'],
    ['sweet_symphony', 'Chathurika Perera', 'orders@sweetsymphony.lk', $pass_hash, 'supplier'],
    ['ranranga_cultural', 'Pradeep Gunawardana', 'bookings@ranrangadance.lk', $pass_hash, 'supplier'],
    ['aegis_security', 'Capt. Sumith Bandara', 'operations@aegisprotection.lk', $pass_hash, 'supplier'],
    ['velvet_mixology', 'Tariq Mansoor', 'bar@velvetmixology.lk', $pass_hash, 'supplier']
];

$user_id_map = []; // username => user_id

foreach ($all_users as $u) {
    list($uname, $fname, $email, $pwd, $role) = $u;
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param("sssss", $uname, $fname, $email, $pwd, $role);
        $ins->execute();
        $uid = (int)$conn->insert_id;
        $user_id_map[$uname] = $uid;
        echo "  [+] Created user '{$uname}' ({$role})\n";
        $ins->close();
    } else {
        $row = $res->fetch_assoc();
        $uid = (int)$row['id'];
        $user_id_map[$uname] = $uid;
        $upd = $conn->prepare("UPDATE users SET fullname = ?, email = ?, password = ?, role = ? WHERE id = ?");
        $upd->bind_param("ssssi", $fname, $email, $pwd, $role, $uid);
        $upd->execute();
        echo "  [*] Verified user '{$uname}' ({$role}, ID: {$uid})\n";
        $upd->close();
    }
    $stmt->close();
}

// -------------------------------------------------------
// 4. Seed Suppliers Profile (Full Range of 10 Suppliers)
// -------------------------------------------------------
echo "\n--- Seeding Suppliers Profile ---\n";
$suppliers_data = [
    [
        'user_name'     => 'grand_royal_catering',
        'business_name' => 'Grand Royal Banquet & Catering',
        'category'      => 'Catering',
        'description'   => 'Premier luxury catering service specializing in 5-star wedding banquets, corporate dining, and bespoke buffet installations across the island.',
        'contact_phone' => '+94 11 269 4820',
        'location'      => 'Colombo 07',
        'logo_url'      => 'https://images.unsplash.com/photo-1555244162-803834f70033?w=300'
    ],
    [
        'user_name'     => 'lotus_florals',
        'business_name' => 'Lotus Floral Elegance & Decor',
        'category'      => 'Decorators',
        'description'   => 'Master wedding designers and florists specializing in royal Poruwa setups, grand balloon installations, and custom thematic stage backdrops.',
        'contact_phone' => '+94 81 223 9012',
        'location'      => 'Kandy City',
        'logo_url'      => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=300'
    ],
    [
        'user_name'     => 'lumina_av_sound',
        'business_name' => 'Lumina Audio Visual & Stage Rigging',
        'category'      => 'Audio/Visual (A/V)',
        'description'   => 'High-end concert-grade audio engineering, intelligent moving heads, outdoor LED walls, lasers, and professional stage trussing.',
        'contact_phone' => '+94 77 341 8892',
        'location'      => 'Mount Lavinia',
        'logo_url'      => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=300'
    ],
    [
        'user_name'     => 'cinnamon_cove_hotel',
        'business_name' => 'The Grand Cinnamon Cove Resort & Spa',
        'category'      => 'Hotel Venues',
        'description'   => '5-star beachfront resort providing sweeping oceanview ballrooms, luxury guest accommodations, and full-service valet parking.',
        'contact_phone' => '+94 91 438 7700',
        'location'      => 'Galle Fort',
        'logo_url'      => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=300'
    ],
    [
        'user_name'     => 'dj_pulse_entertainment',
        'business_name' => 'Pulse DJ & Live Beats Production',
        'category'      => 'DJs/Artists',
        'description'   => 'Chart-topping event DJs, live saxophonists, acoustic trios, and charismatic masters of ceremonies for weddings, galas, and EDM club nights.',
        'contact_phone' => '+94 71 882 1045',
        'location'      => 'Colombo 03',
        'logo_url'      => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=300'
    ],
    [
        'user_name'     => 'aurora_studios',
        'business_name' => 'Aurora Cinematography & Photography',
        'category'      => 'Photography & Videography',
        'description'   => 'Award-winning cinematographers offering 4K HDR coverage, cinematic drone videography, candid photography, and luxury custom albums.',
        'contact_phone' => '+94 76 991 3421',
        'location'      => 'Nugegoda',
        'logo_url'      => 'https://images.unsplash.com/photo-1537633552985-df8429e8048b?w=300'
    ],
    [
        'user_name'     => 'sweet_symphony',
        'business_name' => 'Sweet Symphony Artisan Bakery & Desserts',
        'category'      => 'Bakeries & Confectioners',
        'description'   => 'High-end patisserie crafting bespoke multi-tiered wedding cakes, themed celebration cakes, macaron towers, and French pastry dessert tables.',
        'contact_phone' => '+94 11 582 3911',
        'location'      => 'Rajagiriya, Colombo',
        'logo_url'      => 'https://images.unsplash.com/photo-1535141192574-5d4897c13136?w=300'
    ],
    [
        'user_name'     => 'ranranga_cultural',
        'business_name' => 'Ranranga Traditional Dance & Choral Ensemble',
        'category'      => 'Cultural Performers',
        'description'   => 'Celebrated traditional cultural troupe providing authentic Kandyan Ves dancers, Pantheru drummers, revered Ashtaka narrators, and Jayamangala Gatha choir vocalists.',
        'contact_phone' => '+94 81 334 1198',
        'location'      => 'Pilimathalawa, Kandy',
        'logo_url'      => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=300'
    ],
    [
        'user_name'     => 'aegis_security',
        'business_name' => 'Aegis Event Security & Valet Services',
        'category'      => 'Security',
        'description'   => 'Licensed, professional event security personnel, bouncer details, metal detection entry screening, VIP escort services, and trained valet drivers.',
        'contact_phone' => '+94 11 250 8831',
        'location'      => 'Colombo 05',
        'logo_url'      => 'https://images.unsplash.com/photo-1582139329536-e7284fece509?w=300'
    ],
    [
        'user_name'     => 'velvet_mixology',
        'business_name' => 'Velvet Mobile Cocktail Bar & Mixology',
        'category'      => 'Bar & Beverage Services',
        'description'   => 'Sophisticated mobile bar setup with flair mixologists, custom signature cocktail menus, craft mocktails, ice carving, and premium glassware hire.',
        'contact_phone' => '+94 77 620 9944',
        'location'      => 'Colombo 07',
        'logo_url'      => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=300'
    ]
];

$supplier_id_map = []; // business_name => supplier_id

foreach ($suppliers_data as $sd) {
    $uid = $user_id_map[$sd['user_name']] ?? 0;
    if (!$uid) continue;

    $stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO suppliers (user_id, business_name, category, description, contact_phone, location, logo_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("issssss", $uid, $sd['business_name'], $sd['category'], $sd['description'], $sd['contact_phone'], $sd['location'], $sd['logo_url']);
        $ins->execute();
        $sid = (int)$conn->insert_id;
        $supplier_id_map[$sd['business_name']] = $sid;
        echo "  [+] Created supplier '{$sd['business_name']}' (ID: {$sid})\n";
        $ins->close();
    } else {
        $row = $res->fetch_assoc();
        $sid = (int)$row['id'];
        $supplier_id_map[$sd['business_name']] = $sid;
        $upd = $conn->prepare("UPDATE suppliers SET business_name = ?, category = ?, description = ?, contact_phone = ?, location = ?, logo_url = ? WHERE id = ?");
        $upd->bind_param("ssssssi", $sd['business_name'], $sd['category'], $sd['description'], $sd['contact_phone'], $sd['location'], $sd['logo_url'], $sid);
        $upd->execute();
        echo "  [*] Verified supplier '{$sd['business_name']}' (ID: {$sid})\n";
        $upd->close();
    }
    $stmt->close();
}

// -------------------------------------------------------
// 5. Seed Supplier Services Mapping (Full Service Coverage)
// -------------------------------------------------------
echo "\n--- Seeding Supplier Services Mapping ---\n";

// Map: business_name => array of service names
$services_link_rules = [
    'Grand Royal Banquet & Catering' => [
        'Catering', // Weddings (#5), Get Togethers (#7), Birthdays (#13)
        'In-House Catering' // Hotel Venues (#20)
    ],
    'Lotus Floral Elegance & Decor' => [
        'Poruwa/Decorators', // Weddings (#1)
        'Venue/Furniture Rentals', // Get Togethers (#8)
        'Decorators' // Birthdays (#10)
    ],
    'Lumina Audio Visual & Stage Rigging' => [
        'Audio/Visual (A/V)', // DJ Parties (#14)
        'Light Entertainment', // Get Togethers (#9)
        'Simultaneous Translation & Headsets' // Corporate Conferences (#26)
    ],
    'The Grand Cinnamon Cove Resort & Spa' => [
        'Venue', // DJ Parties (#16)
        'Elegant Halls/Banquet Spaces', // Hotel Venues (#19)
        'Guest Accommodations', // Hotel Venues (#21)
        'Logistics & Parking' // Hotel Venues (#22)
    ],
    'Pulse DJ & Live Beats Production' => [
        'Light Entertainment', // Get Togethers (#9)
        'Entertainment', // Birthdays (#12)
        'DJs/Artists' // DJ Parties (#15)
    ],
    'Aurora Cinematography & Photography' => [
        'Photography & Videography' // Weddings (#6)
    ],
    'Sweet Symphony Artisan Bakery & Desserts' => [
        'Bakeries & Confectioners' // Birthdays (#11)
    ],
    'Ranranga Traditional Dance & Choral Ensemble' => [
        'Ashtaka Narrator', // Weddings (#2)
        'Jayamangala Gatha Choir', // Weddings (#3)
        'Kandyan Dancers and Drummers' // Weddings (#4)
    ],
    'Aegis Event Security & Valet Services' => [
        'Security', // DJ Parties (#18)
        'Logistics & Parking' // Hotel Venues (#22)
    ],
    'Velvet Mobile Cocktail Bar & Mixology' => [
        'Bar & Beverage Services' // DJ Parties (#17)
    ]
];

foreach ($services_link_rules as $bname => $snames) {
    $sid = $supplier_id_map[$bname] ?? 0;
    if (!$sid) continue;

    foreach ($snames as $sname) {
        $svc_res = $conn->query("SELECT service_id FROM services WHERE service_name = '" . $conn->real_escape_string($sname) . "'");
        if ($svc_res) {
            while ($srow = $svc_res->fetch_assoc()) {
                $sv_id = (int)$srow['service_id'];
                $chk = $conn->query("SELECT id FROM supplier_services WHERE supplier_id = {$sid} AND service_id = {$sv_id}");
                if ($chk && $chk->num_rows === 0) {
                    $conn->query("INSERT INTO supplier_services (supplier_id, service_id) VALUES ({$sid}, {$sv_id})");
                    echo "  [+] Linked '{$bname}' -> Service #{$sv_id} ({$sname})\n";
                }
            }
        }
    }
}

// -------------------------------------------------------
// 6. Seed Supplier Listings (Rich packages for all suppliers)
// -------------------------------------------------------
echo "\n--- Seeding Supplier Listings & Packages ---\n";
$listings_data = [
    // Grand Royal Banquet & Catering
    ['Grand Royal Banquet & Catering', 'Catering', 'Platinum Wedding Feast (3-Course Buffet)', 'Five-star wedding banquet comprising multi-cuisine salads, signature carvery, 4 international meats, seafood specialties, and 12 delectable desserts.', 4800.00, 'per_person', 450, 'https://images.unsplash.com/photo-1555244162-803834f70033?w=500'],
    ['Grand Royal Banquet & Catering', 'Catering', 'Cocktail & Canapés Evening Reception', 'Exquisite selection of warm & cold artisan hors d\'oeuvres, live sushi rolling, slider bar, and gourmet dessert shooters.', 3200.00, 'per_person', 300, 'https://images.unsplash.com/photo-1505236858219-8359eb29e329?w=500'],
    ['Grand Royal Banquet & Catering', 'Catering', 'Kids Fiesta Birthday Buffet & Snacks', 'Colorful party spread featuring mini burger sliders, chicken nuggets, waffle cones, french fries, and fruit skewers.', 2500.00, 'per_person', 120, 'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=500'],

    // Lotus Floral Elegance & Decor
    ['Lotus Floral Elegance & Decor', 'Poruwa/Decorators', 'Royal Heritage Poruwa & Floral Pavilion', 'Carved traditional Poruwa adorned with fresh lotus blossoms, orchids, oil lamp setup, ceremonial set, and illuminated walkway.', 225000.00, 'total_package', 350, 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=500'],
    ['Lotus Floral Elegance & Decor', 'Decorators', 'Fairytale Balloon Arch & Backdrop Styling', 'Custom thematic organic balloon garland, customized acrylic name signage, neon number sign, and cylindrical dessert plinths.', 65000.00, 'total_package', 150, 'https://images.unsplash.com/photo-1527529482837-4698179dc6ce?w=500'],
    ['Lotus Floral Elegance & Decor', 'Venue/Furniture Rentals', 'Rustic Outdoor Garden Seating & Canopy Setup', 'Vintage wooden cross-back chairs, banquet tables with linen overlays, fairy-light canopy, and chic cocktail barrels.', 95000.00, 'total_package', 120, 'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=500'],

    // Lumina Audio Visual & Stage Rigging
    ['Lumina Audio Visual & Stage Rigging', 'Audio/Visual (A/V)', 'Concert Stadium Audio & Stage Lighting Rig', 'High-fidelity dual line array system, 12 moving heads, laser beams, hazers, digital mixer, and dedicated sound engineer.', 185000.00, 'per_day', 600, 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=500'],
    ['Lumina Audio Visual & Stage Rigging', 'Audio/Visual (A/V)', 'Club Laser Lighting & Fog Effects System', 'High-powered RGB lasers, CO2 jets, stroboscopes, and dynamic computerized light control for high-energy dance parties.', 65000.00, 'per_day', 400, 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=500'],
    ['Lumina Audio Visual & Stage Rigging', 'Light Entertainment', 'Acoustic PA & Wireless Microphone Package', 'Compact crystal-clear column PA system, 4 wireless microphones, and Bluetooth aux audio input for intimate gatherings.', 35000.00, 'per_day', 150, 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=500'],

    // The Grand Cinnamon Cove Resort & Spa
    ['The Grand Cinnamon Cove Resort & Spa', 'Elegant Halls/Banquet Spaces', 'Grand Horizon Oceanview Ballroom (Full Day)', 'Palatial oceanfront grand ballroom with imported crystal chandeliers, integrated acoustic treatment, private bridal suite, and dedicated banquet lobby.', 450000.00, 'per_day', 450, 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?w=500'],
    ['The Grand Cinnamon Cove Resort & Spa', 'Venue', 'Sunset Beachside Lawn Pavilion', 'Expansive manicured lawn with panoramic ocean views, private beach access, ambient festoon lighting, and ocean breeze setup.', 280000.00, 'per_day', 300, 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=500'],
    ['The Grand Cinnamon Cove Resort & Spa', 'Guest Accommodations', 'Executive Suite Block & Guest Accommodations', 'Block booking of 10 Deluxe Oceanview Suites for wedding entourage or VIP guests, including breakfast and luxury spa access.', 150000.00, 'per_day', 50, 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=500'],

    // Pulse DJ & Live Beats Production
    ['Pulse DJ & Live Beats Production', 'DJs/Artists', 'Club Hits & Top 40 Live DJ Package', '5 hours of continuous high-energy party mixing by renowned DJ Kevin, covering EDM, commercial hits, Sinhala baila remixes, and hip-hop.', 75000.00, 'total_package', 500, 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=500'],
    ['Pulse DJ & Live Beats Production', 'Light Entertainment', 'Acoustic Trio & Ambient Vocals', 'Unplugged acoustic setup featuring lead vocals, acoustic guitar, and cajon percussion for elegant cocktail hours or dinners.', 60000.00, 'per_hour', 200, 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?w=500'],
    ['Pulse DJ & Live Beats Production', 'Entertainment', 'Interactive Birthday Party Emcee & Kids Games', 'Lively host managing fun party games, music competitions, cake cutting ceremony, and upbeat dance routines.', 30000.00, 'total_package', 100, 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=500'],

    // Aurora Cinematography & Photography
    ['Aurora Cinematography & Photography', 'Photography & Videography', 'Signature Wedding Cinematography (4K + Drone)', 'Full-day multi-camera coverage, cinematic 4K drone videography, 5-minute teaser highlight reel, and full ceremony documentary feature.', 290000.00, 'total_package', 400, 'https://images.unsplash.com/photo-1537633552985-df8429e8048b?w=500'],
    ['Aurora Cinematography & Photography', 'Photography & Videography', 'Pre-Shoot & Destination Portrait Session', '3-hour creative couples session at scenic location of your choice, including 40 magazine-retouched images and a guestbook canvas.', 95000.00, 'total_package', 10, 'https://images.unsplash.com/photo-1606800052052-a08af7148866?w=500'],
    ['Aurora Cinematography & Photography', 'Photography & Videography', 'Event Documentary Photography Coverage', 'Professional candid and formal photography covering corporate conferences, milestone birthdays, or anniversaries.', 55000.00, 'per_day', 250, 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?w=500'],

    // Sweet Symphony Artisan Bakery & Desserts
    ['Sweet Symphony Artisan Bakery & Desserts', 'Bakeries & Confectioners', 'Grand 3-Tier Handcrafted Fondant Wedding Cake', 'Exquisite 3-tier cake featuring Belgian chocolate ganache, handmade sugar florals, gold foil detailing, and custom flavor pairings.', 85000.00, 'total_package', 250, 'https://images.unsplash.com/photo-1535141192574-5d4897c13136?w=500'],
    ['Sweet Symphony Artisan Bakery & Desserts', 'Bakeries & Confectioners', 'Theme Custom Birthday Cake & Cupcake Tower (50 Pax)', 'Custom sculpted 2-tier themed birthday cake accompanied by 36 matching artisan cupcakes with custom fondant toppers.', 42000.00, 'total_package', 80, 'https://images.unsplash.com/photo-1588195538326-c5b1e9f80a1b?w=500'],
    ['Sweet Symphony Artisan Bakery & Desserts', 'Bakeries & Confectioners', 'Luxury French Macaron & Dessert Grazing Station', 'Decadent display of 120 assorted Parisian macarons, tartlets, chocolate truffles, and mini cheesecakes.', 60000.00, 'total_package', 150, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=500'],

    // Ranranga Traditional Dance & Choral Ensemble
    ['Ranranga Traditional Dance & Choral Ensemble', 'Kandyan Dancers and Drummers', 'Grand Kandyan Dance Troupe & Drum Procession (12 Artists)', 'Traditional ceremonial entrance parade with 6 Ves dancers, 4 Pantheru drummers, and 2 ceremonial flag bearers.', 120000.00, 'total_package', 350, 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=500'],
    ['Ranranga Traditional Dance & Choral Ensemble', 'Ashtaka Narrator', 'Traditional Ashtaka Narrator & Jayamangala Gatha Blessing', 'Revered and eloquent narrator delivering traditional Sanskrit blessings, accompanied by a 6-member girls choir in traditional attire.', 45000.00, 'total_package', 300, 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?w=500'],

    // Aegis Event Security & Valet Services
    ['Aegis Event Security & Valet Services', 'Security', 'VIP Event Security Detail & Bouncer Squad (6 Officers)', 'Six SIA-trained security officers for crowd control, guest list credential checks, bag screening, and perimeter protection.', 55000.00, 'per_day', 500, 'https://images.unsplash.com/photo-1582139329536-e7284fece509?w=500'],
    ['Aegis Event Security & Valet Services', 'Logistics & Parking', 'Valet Parking Management & Traffic Direction Team', 'Professional 6-person uniformed valet team providing vehicle greeting, secure designated parking, and numbered ticketing.', 40000.00, 'per_day', 350, 'https://images.unsplash.com/photo-1506521781263-d8422e82f27a?w=500'],

    // Velvet Mobile Cocktail Bar & Mixology
    ['Velvet Mobile Cocktail Bar & Mixology', 'Bar & Beverage Services', 'Premium Mobile Cocktail Bar & Mixologists (5 Hours)', 'Illuminated bar station, 2 master flair mixologists, artisan syrups, dehydrated garnishes, premium ice, and custom cocktail menu.', 110000.00, 'total_package', 400, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=500'],
    ['Velvet Mobile Cocktail Bar & Mixology', 'Bar & Beverage Services', 'Mocktail Bar & Artisan Lemonade Station', 'Non-alcoholic craft beverage bar with fresh fruit infusions, botanical spritzers, and organic fruit purees.', 45000.00, 'total_package', 250, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500']
];

$listing_id_map = []; // title => listing_id

foreach ($listings_data as $ld) {
    list($bname, $sname, $title, $desc, $price, $ptype, $cap, $img) = $ld;
    $sid = $supplier_id_map[$bname] ?? 0;
    if (!$sid) continue;

    $sres = $conn->query("SELECT service_id FROM services WHERE service_name = '" . $conn->real_escape_string($sname) . "' LIMIT 1");
    if (!$sres || $sres->num_rows === 0) continue;
    $sv_id = (int)$sres->fetch_assoc()['service_id'];

    $stmt = $conn->prepare("SELECT listing_id FROM supplier_listings WHERE supplier_id = ? AND title = ?");
    $stmt->bind_param("is", $sid, $title);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO supplier_listings (supplier_id, service_id, title, description, price, price_type, capacity, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $ins->bind_param("iissssis", $sid, $sv_id, $title, $desc, $price, $ptype, $cap, $img);
        $ins->execute();
        $lid = (int)$conn->insert_id;
        $listing_id_map[$title] = $lid;
        echo "  [+] Created listing '{$title}' (Rs. {$price})\n";
        $ins->close();
    } else {
        $row = $res->fetch_assoc();
        $lid = (int)$row['listing_id'];
        $listing_id_map[$title] = $lid;
        $upd = $conn->prepare("UPDATE supplier_listings SET service_id = ?, description = ?, price = ?, price_type = ?, capacity = ?, image_url = ?, status = 'active' WHERE listing_id = ?");
        $upd->bind_param("isssisi", $sv_id, $desc, $price, $ptype, $cap, $img, $lid);
        $upd->execute();
        echo "  [*] Verified listing '{$title}' (ID: {$lid})\n";
        $upd->close();
    }
    $stmt->close();
}

// -------------------------------------------------------
// 7. Seed Bookings (10 bookings spanning all 5 statuses & event types)
// -------------------------------------------------------
echo "\n--- Seeding Bookings ---\n";
$bookings_data = [
    [
        'id'       => 'BKG-WED-2026-01',
        'uname'    => 'kasun.perera',
        'type'     => 'Weddings',
        'place'    => 'The Grand Cinnamon Cove Ballroom, Galle',
        'guests'   => 280,
        'date'     => '2026-10-24',
        'time'     => 'Night',
        'food'     => 'Buffet',
        'extra'    => 'Poruwa ceremony, 3-course dinner, drone photography, live band.',
        'status'   => 'confirmed'
    ],
    [
        'id'       => 'BKG-DJ-2026-02',
        'uname'    => 'dilhani.s',
        'type'     => 'DJ Parties',
        'place'    => 'Sunset Beachside Lawn, Mount Lavinia',
        'guests'   => 350,
        'date'     => '2026-11-14',
        'time'     => 'Night',
        'food'     => 'Cocktail/Snacks',
        'extra'    => 'Neon lasers, heavy bass sound system, professional security.',
        'status'   => 'pending'
    ],
    [
        'id'       => 'BKG-BDAY-2026-03',
        'uname'    => 'nimal.fernando',
        'type'     => 'Birthdays',
        'place'    => 'Waters Edge Pavilion, Battaramulla',
        'guests'   => 75,
        'date'     => '2026-09-28',
        'time'     => 'Day',
        'food'     => 'Buffet',
        'extra'    => 'Circus theme, magician, custom 2-tier chocolate cake.',
        'status'   => 'in_progress'
    ],
    [
        'id'       => 'BKG-HOTEL-2026-04',
        'uname'    => 'ananya.sharma',
        'type'     => 'Hotel Venues',
        'place'    => 'Shangri-La Main Ballroom, Colombo',
        'guests'   => 220,
        'date'     => '2026-06-15',
        'time'     => 'Day',
        'food'     => 'Set Menu',
        'extra'    => 'Corporate gala setup, AV podium, plated 3-course lunch.',
        'status'   => 'completed'
    ],
    [
        'id'       => 'BKG-GET-2026-05',
        'uname'    => 'chathura.k',
        'type'     => 'Get Togethers',
        'place'    => 'Villa Republic, Bentota',
        'guests'   => 45,
        'date'     => '2026-05-10',
        'time'     => 'Night',
        'food'     => 'BBQ/Snacks',
        'extra'    => 'Outdoor pool party, acoustic music, BBQ dinner.',
        'status'   => 'cancelled'
    ],
    [
        'id'       => 'BKG-WED-2026-06',
        'uname'    => 'malithi.desilva',
        'type'     => 'Weddings',
        'place'    => 'Kingsbury Victorian Hall, Colombo',
        'guests'   => 320,
        'date'     => '2026-12-18',
        'time'     => 'Day',
        'food'     => 'Buffet',
        'extra'    => 'Traditional Kandyan dancers, choir, floral decor, 4K video.',
        'status'   => 'confirmed'
    ],
    [
        'id'       => 'BKG-DJ-2026-07',
        'uname'    => 'kasun.perera',
        'type'     => 'DJ Parties',
        'place'    => 'Viharamahadevi Amphitheatre, Colombo 07',
        'guests'   => 500,
        'date'     => '2026-12-31',
        'time'     => 'Night',
        'food'     => 'Finger Food',
        'extra'    => 'New Year countdown festival, 3 guest DJs, full lighting.',
        'status'   => 'pending'
    ],
    [
        'id'       => 'BKG-HOTEL-2026-08',
        'uname'    => 'dilhani.s',
        'type'     => 'Hotel Venues',
        'place'    => 'Jetwing Lighthouse, Galle',
        'guests'   => 150,
        'date'     => '2026-08-20',
        'time'     => 'Night',
        'food'     => 'Buffet',
        'extra'    => 'Alumni annual reunion dinner, oceanfront seating.',
        'status'   => 'completed'
    ],
    [
        'id'       => 'BKG-BDAY-2026-09',
        'uname'    => 'ananya.sharma',
        'type'     => 'Birthdays',
        'place'    => 'Earls Regency Grand Ballroom, Kandy',
        'guests'   => 90,
        'date'     => '2026-10-05',
        'time'     => 'Night',
        'food'     => 'Buffet',
        'extra'    => 'Golden jubilee 50th birthday dinner, live acoustic trio.',
        'status'   => 'in_progress'
    ],
    [
        'id'       => 'BKG-GET-2026-10',
        'uname'    => 'nimal.fernando',
        'type'     => 'Get Togethers',
        'place'    => 'Bolgoda Lake Resort, Moratuwa',
        'guests'   => 60,
        'date'     => '2026-11-28',
        'time'     => 'Day',
        'food'     => 'Buffet',
        'extra'    => 'Family reunion picnic by the lake with lawn games.',
        'status'   => 'pending'
    ]
];

foreach ($bookings_data as $bd) {
    $uid = $user_id_map[$bd['uname']] ?? null;

    $stmt = $conn->prepare("SELECT BookingID FROM bookings WHERE BookingID = ?");
    $stmt->bind_param("s", $bd['id']);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO bookings (BookingID, user_id, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("sisssisssss", $bd['id'], $uid, $bd['uname'], $bd['type'], $bd['place'], $bd['guests'], $bd['date'], $bd['time'], $bd['food'], $bd['extra'], $bd['status']);
        $ins->execute();
        echo "  [+] Created booking '{$bd['id']}' ({$bd['status']})\n";
        $ins->close();
    } else {
        $upd = $conn->prepare("UPDATE bookings SET user_id = ?, user_name = ?, EventType = ?, Place = ?, NumberOfGuests = ?, EventDate = ?, DayNight = ?, FoodPreferences = ?, ExtraDetails = ?, status = ? WHERE BookingID = ?");
        $upd->bind_param("isssissssss", $uid, $bd['uname'], $bd['type'], $bd['place'], $bd['guests'], $bd['date'], $bd['time'], $bd['food'], $bd['extra'], $bd['status'], $bd['id']);
        $upd->execute();
        echo "  [*] Verified booking '{$bd['id']}' ({$bd['status']})\n";
        $upd->close();
    }
    $stmt->close();
}

// -------------------------------------------------------
// 8. Seed Event Extras
// -------------------------------------------------------
echo "\n--- Seeding Event Extras ---\n";
$extras_data = [
    ['BKG-WED-2026-01', 'Stage Lighting, Floral Mandap, 4K Projector', 'International Buffet & Live Cooking'],
    ['BKG-DJ-2026-02', 'Line Array Sound, Smoke Machine, Laser Rig', 'Finger Food & Mocktail Bar'],
    ['BKG-BDAY-2026-03', 'PA System, Party Balloons, Photo Booth', 'Dessert Table & Kids Finger Food'],
    ['BKG-HOTEL-2026-04', 'Podium Mics, Dual LED Screens, Sound System', 'Executive High Tea & Plated Lunch'],
    ['BKG-GET-2026-05', 'Acoustic Speaker, Garden Gazebo', 'BBQ Grill & Local Sri Lankan Buffet'],
    ['BKG-WED-2026-06', 'Traditional Poruwa, LED Ambience, Drone', 'Grand Sri Lankan & Eastern Fusion Feast'],
    ['BKG-DJ-2026-07', 'Subwoofers, Co2 Cannons, Moving Heads', 'Street Food Stalls & Energy Drinks'],
    ['BKG-HOTEL-2026-08', 'Banquet Lighting & Podium Sound', 'Seafood Extravaganza Buffet'],
    ['BKG-BDAY-2026-09', 'Ambient Uplighting & Wireless Mics', 'Western Buffet & Custom Cake'],
    ['BKG-GET-2026-10', 'Portable Sound Box & Outdoor Canopy', 'Rice & Curry Feast with Dessert Counter']
];

foreach ($extras_data as $ed) {
    list($bid, $eq, $fs) = $ed;
    $stmt = $conn->prepare("SELECT id FROM event_extras WHERE booking_id = ?");
    $stmt->bind_param("s", $bid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO event_extras (booking_id, equipment, food_style) VALUES (?, ?, ?)");
        $ins->bind_param("sss", $bid, $eq, $fs);
        $ins->execute();
        echo "  [+] Added event extras for {$bid}\n";
        $ins->close();
    } else {
        $upd = $conn->prepare("UPDATE event_extras SET equipment = ?, food_style = ? WHERE booking_id = ?");
        $upd->bind_param("sss", $eq, $fs, $bid);
        $upd->execute();
        echo "  [*] Verified event extras for {$bid}\n";
        $upd->close();
    }
    $stmt->close();
}

// -------------------------------------------------------
// 9. Seed Booking Services (Assignments linking bookings, services, and suppliers)
// -------------------------------------------------------
echo "\n--- Seeding Booking Services Assignments ---\n";
$booking_svc_data = [
    // Wedding BKG-WED-2026-01
    ['BKG-WED-2026-01', 'Poruwa/Decorators', 'Lotus Floral Elegance & Decor', 'Royal Heritage Poruwa & Floral Pavilion', 'Theme colors: Ivory and blush pink with gold accents', 225000.00, 'confirmed'],
    ['BKG-WED-2026-01', 'Catering', 'Grand Royal Banquet & Catering', 'Platinum Wedding Feast (3-Course Buffet)', '280 guests at Rs. 4,800/pax', 1344000.00, 'confirmed'],
    ['BKG-WED-2026-01', 'Photography & Videography', 'Aurora Cinematography & Photography', 'Signature Wedding Cinematography (4K + Drone)', 'Full day drone and 4K cinema coverage', 290000.00, 'confirmed'],

    // DJ Party BKG-DJ-2026-02
    ['BKG-DJ-2026-02', 'Audio/Visual (A/V)', 'Lumina Audio Visual & Stage Rigging', 'Concert Stadium Audio & Stage Lighting Rig', 'Bass heavy setting required for open lawn', 185000.00, 'requested'],
    ['BKG-DJ-2026-02', 'DJs/Artists', 'Pulse DJ & Live Beats Production', 'Club Hits & Top 40 Live DJ Package', 'Set time 8:00 PM to 1:00 AM', 75000.00, 'requested'],
    ['BKG-DJ-2026-02', 'Security', 'Aegis Event Security & Valet Services', 'VIP Event Security Detail & Bouncer Squad (6 Officers)', 'Wristband entry check at beach gate', 55000.00, 'requested'],

    // Birthday BKG-BDAY-2026-03
    ['BKG-BDAY-2026-03', 'Decorators', 'Lotus Floral Elegance & Decor', 'Fairytale Balloon Arch & Backdrop Styling', 'Pastel circus colors with name cutout', 65000.00, 'confirmed'],
    ['BKG-BDAY-2026-03', 'Bakeries & Confectioners', 'Sweet Symphony Artisan Bakery & Desserts', 'Theme Custom Birthday Cake & Cupcake Tower (50 Pax)', 'Belgium chocolate truffle cake', 42000.00, 'confirmed'],
    ['BKG-BDAY-2026-03', 'Entertainment', 'Pulse DJ & Live Beats Production', 'Interactive Birthday Party Emcee & Kids Games', 'Includes magic show and balloon twisting', 30000.00, 'confirmed'],

    // Wedding BKG-WED-2026-06
    ['BKG-WED-2026-06', 'Poruwa/Decorators', 'Lotus Floral Elegance & Decor', 'Royal Heritage Poruwa & Floral Pavilion', 'Deep maroon and jasmine flowers', 225000.00, 'confirmed'],
    ['BKG-WED-2026-06', 'Kandyan Dancers and Drummers', 'Ranranga Traditional Dance & Choral Ensemble', 'Grand Kandyan Dance Troupe & Drum Procession (12 Artists)', 'Ves dancers for traditional procession', 120000.00, 'confirmed'],
    ['BKG-WED-2026-06', 'Photography & Videography', 'Aurora Cinematography & Photography', 'Signature Wedding Cinematography (4K + Drone)', 'Pre-shoot plus wedding day documentary', 290000.00, 'confirmed']
];

foreach ($booking_svc_data as $bsd) {
    list($bid, $sname, $bname, $ltitle, $notes, $cost, $stat) = $bsd;

    $sres = $conn->query("SELECT service_id FROM services WHERE service_name = '" . $conn->real_escape_string($sname) . "' LIMIT 1");
    if (!$sres || $sres->num_rows === 0) continue;
    $sv_id = (int)$sres->fetch_assoc()['service_id'];

    $sid = $supplier_id_map[$bname] ?? null;
    $lid = $listing_id_map[$ltitle] ?? null;

    $chk = $conn->prepare("SELECT id FROM booking_services WHERE booking_id = ? AND service_id = ?");
    $chk->bind_param("si", $bid, $sv_id);
    $chk->execute();
    $res = $chk->get_result();

    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO booking_services (booking_id, service_id, supplier_id, listing_id, custom_notes, assigned_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("siiisds", $bid, $sv_id, $sid, $lid, $notes, $cost, $stat);
        $ins->execute();
        echo "  [+] Assigned {$bname} to booking {$bid} ({$sname})\n";
        $ins->close();
    } else {
        $row = $res->fetch_assoc();
        $bs_id = (int)$row['id'];
        $upd = $conn->prepare("UPDATE booking_services SET supplier_id = ?, listing_id = ?, custom_notes = ?, assigned_cost = ?, status = ? WHERE id = ?");
        $upd->bind_param("iisdsi", $sid, $lid, $notes, $cost, $stat, $bs_id);
        $upd->execute();
        echo "  [*] Verified assignment for booking {$bid} ({$sname})\n";
        $upd->close();
    }
    $chk->close();
}

// -------------------------------------------------------
// 10. Seed Budgets (Financial logs matching bookings)
// -------------------------------------------------------
echo "\n--- Seeding Budgets ---\n";
$budgets_data = [
    ['kasun.perera', 'BKG-WED-2026-01', 2500000.00, 1500000.00, 1344000.00, 180000.00, 120000.00, 50000.00, 2200000.00, 300000.00, 300000.00],
    ['dilhani.s', 'BKG-DJ-2026-02', 750000.00, 250000.00, 0.00, 150000.00, 35000.00, 65000.00, 630000.00, 120000.00, 120000.00],
    ['nimal.fernando', 'BKG-BDAY-2026-03', 350000.00, 180000.00, 120000.00, 25000.00, 42000.00, 20000.00, 310000.00, 40000.00, 40000.00],
    ['ananya.sharma', 'BKG-HOTEL-2026-04', 1800000.00, 950000.00, 700000.00, 150000.00, 80000.00, 50000.00, 1720000.00, 80000.00, 80000.00],
    ['chathura.k', 'BKG-GET-2026-05', 200000.00, 120000.00, 90000.00, 20000.00, 10000.00, 15000.00, 0.00, 200000.00, 200000.00],
    ['malithi.desilva', 'BKG-WED-2026-06', 3200000.00, 1800000.00, 1536000.00, 200000.00, 150000.00, 60000.00, 2850000.00, 350000.00, 350000.00],
    ['kasun.perera', 'BKG-DJ-2026-07', 1200000.00, 400000.00, 250000.00, 200000.00, 40000.00, 80000.00, 850000.00, 350000.00, 350000.00],
    ['dilhani.s', 'BKG-HOTEL-2026-08', 1500000.00, 900000.00, 750000.00, 150000.00, 80000.00, 40000.00, 1420000.00, 80000.00, 80000.00],
    ['ananya.sharma', 'BKG-BDAY-2026-09', 480000.00, 260000.00, 180000.00, 35000.00, 45000.00, 25000.00, 410000.00, 70000.00, 70000.00],
    ['nimal.fernando', 'BKG-GET-2026-10', 280000.00, 160000.00, 120000.00, 25000.00, 20000.00, 15000.00, 220000.00, 60000.00, 60000.00]
];

foreach ($budgets_data as $bg) {
    list($uname, $bid, $tot, $food, $buff, $bev, $des, $snk, $spnt, $rem, $var) = $bg;

    $chk = $conn->prepare("SELECT id FROM budgets WHERE booking_id = ?");
    $chk->bind_param("s", $bid);
    $chk->execute();
    $res = $chk->get_result();

    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO budgets (user_name, booking_id, total_budget, food_budget, buffet_cost, beverages_cost, desserts_cost, snacks_cost, total_spent, remaining_budget, variance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("ssddddddddd", $uname, $bid, $tot, $food, $buff, $bev, $des, $snk, $spnt, $rem, $var);
        $ins->execute();
        echo "  [+] Added budget for {$bid} (Total: Rs. " . number_format($tot, 2) . ")\n";
        $ins->close();
    } else {
        $upd = $conn->prepare("UPDATE budgets SET user_name = ?, total_budget = ?, food_budget = ?, buffet_cost = ?, beverages_cost = ?, desserts_cost = ?, snacks_cost = ?, total_spent = ?, remaining_budget = ?, variance = ? WHERE booking_id = ?");
        $upd->bind_param("sddddddddds", $uname, $tot, $food, $buff, $bev, $des, $snk, $spnt, $rem, $var, $bid);
        $upd->execute();
        echo "  [*] Verified budget for {$bid}\n";
        $upd->close();
    }
    $chk->close();
}

// -------------------------------------------------------
// 11. Seed Contact Messages
// -------------------------------------------------------
echo "\n--- Seeding Contact Messages ---\n";
$messages = [
    [
        'first'   => 'Kaveen',
        'last'    => 'Jayasuriya',
        'email'   => 'kaveen.j@gmail.com',
        'phone'   => '+94 77 123 9988',
        'msg'     => 'Hello, I am planning a wedding reception for 300 guests in Galle this December. Could you recommend top catering and decor suppliers who can collaborate?',
        'is_read' => 1
    ],
    [
        'first'   => 'Sanduni',
        'last'    => 'Wickrama',
        'email'   => 'sanduni.w@yahoo.com',
        'phone'   => '+94 71 445 2201',
        'msg'     => 'Inquiring about booking audio/visual stage lighting and an EDM DJ for a college graduation bash in Colombo. Are dates in November open?',
        'is_read' => 0
    ],
    [
        'first'   => 'Dr. Priyantha',
        'last'    => 'Dissanayake',
        'email'   => 'priyantha.d@medicare.lk',
        'phone'   => '+94 81 223 4455',
        'msg'     => 'We require conference venue facilities with simultaneous translation headsets and live streaming for our annual medical seminar.',
        'is_read' => 1
    ],
    [
        'first'   => 'Hansi',
        'last'    => 'Madushani',
        'email'   => 'hansi.m@gmail.com',
        'phone'   => '+94 76 890 1123',
        'msg'     => 'Can you provide a package quote for a 1st birthday party with balloon decor, 2-tier custom fondant cake, and kid entertainment?',
        'is_read' => 0
    ],
    [
        'first'   => 'Lakshan',
        'last'    => 'Weerakkody',
        'email'   => 'lakshan.w@techpulse.io',
        'phone'   => '+94 70 332 9901',
        'msg'     => 'Interested in signing up as an official event partner for sound production and drone filming. Who should our team contact?',
        'is_read' => 0
    ],
    [
        'first'   => 'Tharushi',
        'last'    => 'Alwis',
        'email'   => 'tharushi.alwis@gmail.com',
        'phone'   => '+94 77 554 8871',
        'msg'     => 'Thank you for coordinating our beach get-together last weekend! The food and acoustic music trio were phenomenal.',
        'is_read' => 1
    ]
];

foreach ($messages as $m) {
    $chk = $conn->prepare("SELECT id FROM contact_messages WHERE email = ? AND message = ?");
    $chk->bind_param("ss", $m['email'], $m['msg']);
    $chk->execute();
    $res = $chk->get_result();

    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO contact_messages (firstname, lastname, email, phone, message, is_read) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->bind_param("sssssi", $m['first'], $m['last'], $m['email'], $m['phone'], $m['msg'], $m['is_read']);
        $ins->execute();
        echo "  [+] Added message from {$m['first']} {$m['last']}\n";
        $ins->close();
    } else {
        echo "  [*] Message from {$m['first']} {$m['last']} already exists\n";
    }
    $chk->close();
}

echo "\n=======================================================\n";
echo " Seeding Complete! All Roles, Range & Categories Covered.\n";
echo "=======================================================\n";
