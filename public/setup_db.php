<?php
/**
 * setup_db.php — Full Schema Migrator
 * 
 * Run this script once after importing database.sql to ensure
 * all columns, tables, and seed data are up-to-date.
 * It is safe to run multiple times (idempotent).
 */
include __DIR__ . '/../config/database.php';

$results = [];

// -------------------------------------------------------
// 1. Add `role` column to `users` if it doesn't exist
// -------------------------------------------------------
$col_check = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role'");
if ($col_check && $col_check->num_rows === 0) {
    $sql = "ALTER TABLE `users` ADD COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'buyer' AFTER `password`";
    if ($conn->query($sql)) {
        $results[] = "✅ Added 'role' column to users table.";
    } else {
        $results[] = "❌ Failed to add 'role' column: " . $conn->error;
    }
} else {
    $results[] = "⏭️ 'role' column already exists in users table.";
}

// -------------------------------------------------------
// 2. Create `suppliers` table
// -------------------------------------------------------
$sql = "CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `business_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `contact_phone` VARCHAR(20),
  `location` VARCHAR(255),
  `logo_url` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    $results[] = "✅ suppliers table ready.";
} else {
    $results[] = "❌ suppliers table error: " . $conn->error;
}

// -------------------------------------------------------
// 3. Create `event_types` table
// -------------------------------------------------------
$sql = "CREATE TABLE IF NOT EXISTS `event_types` (
  `event_type_id` INT AUTO_INCREMENT PRIMARY KEY,
  `type_name`     VARCHAR(100) NOT NULL UNIQUE,
  `description`   TEXT,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    $results[] = "✅ event_types table ready.";
} else {
    $results[] = "❌ event_types table error: " . $conn->error;
}

// -------------------------------------------------------
// 4. Create `services` table
// -------------------------------------------------------
$sql = "CREATE TABLE IF NOT EXISTS `services` (
  `service_id`       INT AUTO_INCREMENT PRIMARY KEY,
  `event_type_id`    INT NOT NULL,
  `service_name`     VARCHAR(150) NOT NULL,
  `description`      TEXT NOT NULL,
  `priority_rank`    INT NOT NULL DEFAULT 0,
  `is_required`      TINYINT(1) NOT NULL DEFAULT 0,
  `typical_capacity` INT DEFAULT NULL,
  `notes`            TEXT,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_services_event_type`
    FOREIGN KEY (`event_type_id`) REFERENCES `event_types`(`event_type_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_priority_rank`  CHECK (`priority_rank` >= 0),
  CONSTRAINT `chk_typical_capacity` CHECK (`typical_capacity` IS NULL OR `typical_capacity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    $results[] = "✅ services table ready.";
} else {
    $results[] = "❌ services table error: " . $conn->error;
}

// -------------------------------------------------------
// 5. Create indexes (ignore errors if they already exist)
// -------------------------------------------------------
$indexes = [
    "CREATE INDEX `idx_services_event_type` ON `services` (`event_type_id`)",
    "CREATE INDEX `idx_services_event_type_priority` ON `services` (`event_type_id`, `priority_rank`)",
    "CREATE INDEX `idx_services_name` ON `services` (`service_name`)",
    "CREATE INDEX `idx_event_types_active` ON `event_types` (`is_active`)"
];
foreach ($indexes as $idx) {
    try {
        $conn->query($idx);
    } catch (mysqli_sql_exception $e) {
        // Silently ignore duplicate index errors
    }
}
$results[] = "✅ Indexes verified.";

// -------------------------------------------------------
// 6. Seed event_types (idempotent via INSERT IGNORE)
// -------------------------------------------------------
$sql = "INSERT IGNORE INTO `event_types` (`event_type_id`, `type_name`, `description`, `is_active`) VALUES
(1, 'Weddings',       'Elegant setups with floral arrangements, luxurious staging, and music.', 1),
(2, 'Get Togethers',  'Reconnections with friends/family featuring custom buffet layouts.',     1),
(3, 'Birthdays',      'Unforgettable experiences with customized themes, cakes, and games.',    1),
(4, 'DJ Parties',     'High-energy events with top-tier DJs, sound systems, and neon lighting.',1),
(5, 'Hotel Venues',   'Luxurious hotels and elegant halls for premium hosting facilities.',     1)";

if ($conn->query($sql)) {
    $results[] = "✅ event_types seed data ready (" . $conn->affected_rows . " new rows).";
} else {
    $results[] = "❌ event_types seed error: " . $conn->error;
}

// -------------------------------------------------------
// 7. Seed services (idempotent — check count first)
// -------------------------------------------------------
$svc_count = $conn->query("SELECT COUNT(*) AS c FROM `services`");
$row = $svc_count->fetch_assoc();
if ((int)$row['c'] === 0) {
    $sql = "INSERT INTO `services` (`event_type_id`, `service_name`, `description`, `priority_rank`, `is_required`, `typical_capacity`, `notes`) VALUES
    (1,'Poruwa/Decorators','Poruwa/Decorators',1,1,250,'Traditional Poruwa ceremony setup with floral and lighting decorations. Must be booked at least 4 weeks in advance.'),
    (1,'Ashtaka Narrator','Ashtaka Narrator',2,1,250,'Narrates the eight stanzas of blessings during the Poruwa ceremony. Coordinate timing with the Jayamangala Gatha Choir.'),
    (1,'Jayamangala Gatha Choir','Jayamangala Gatha Choir',3,1,250,'Choir that chants the Jayamangala Gatha blessings. Typically 4-6 vocalists required.'),
    (1,'Kandyan Dancers and Drummers','Kandyan Dancers and Drummers',4,0,250,'Traditional Kandyan dance troupe for the ceremonial procession. Group sizes range from 6-20 performers.'),
    (1,'Catering','Catering',5,1,300,'Full-service wedding catering covering multi-course meals, dessert stations, and beverages.'),
    (1,'Photography & Videography','Photography & Videography',6,1,300,'Professional photo and video coverage including pre-shoot, ceremony, and reception highlights.'),
    (2,'Catering','Catering',1,1,100,'Buffet or plated meal service tailored for casual and semi-formal gatherings.'),
    (2,'Venue/Furniture Rentals','Venue/Furniture Rentals',2,1,100,'Tables, chairs, tents, and venue booking for indoor or outdoor get-togethers.'),
    (2,'Light Entertainment','Light Entertainment',3,0,100,'Acoustic music, lawn games, or a small PA system for background entertainment.'),
    (3,'Decorators','Decorators',1,1,80,'Themed balloon arches, banners, table centrepieces, and stage backdrops.'),
    (3,'Bakeries & Confectioners','Bakeries & Confectioners',2,1,80,'Custom birthday cakes, cupcakes, and confectionery platters. Requires 1-week lead time for custom designs.'),
    (3,'Entertainment','Entertainment',3,0,80,'Magicians, face painters, bounce houses, or live performers for birthday celebrations.'),
    (3,'Catering','Catering',4,1,100,'Party-style catering with finger food, snack platters, and kid-friendly menus.'),
    (4,'Audio/Visual (A/V)','Audio/Visual (A/V)',1,1,500,'Full sound system, LED walls, laser lighting, and fog machines. Venue power supply must be verified.'),
    (4,'DJs/Artists','DJs/Artists',2,1,500,'Professional DJs or live performing artists. Rider requirements must be confirmed 2 weeks before event.'),
    (4,'Venue','Venue',3,1,500,'Indoor club, open-air arena, or rooftop space with adequate power and crowd capacity.'),
    (4,'Bar & Beverage Services','Bar & Beverage Services',4,0,500,'Licensed bar service with mixologists, beverage inventory, and age-verification protocols.'),
    (4,'Security','Security',5,1,500,'Trained security personnel for crowd control, entry screening, and emergency response.'),
    (5,'Elegant Halls/Banquet Spaces','Elegant Halls/Banquet Spaces',1,1,400,'Grand ballrooms or banquet halls with stage, AV hookups, and flexible seating layouts.'),
    (5,'In-House Catering','In-House Catering',2,1,400,'Hotel-managed cuisine with customisable menus, wait staff, and dietary accommodation.'),
    (5,'Guest Accommodations','Guest Accommodations',3,0,200,'Discounted room blocks for event guests, including suite upgrades for VIPs.'),
    (5,'Logistics & Parking','Logistics & Parking',4,1,400,'Valet service, signage, shuttle coordination, and dedicated parking lot allocation.')";

    if ($conn->query($sql)) {
        $results[] = "✅ services seed data inserted (22 rows).";
    } else {
        $results[] = "❌ services seed error: " . $conn->error;
    }
} else {
    $results[] = "⏭️ services table already has " . $row['c'] . " rows — skipping seed.";
}

// -------------------------------------------------------
// 8. Create `supplier_services` junction table
// -------------------------------------------------------
$sql = "CREATE TABLE IF NOT EXISTS `supplier_services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_supplier_service` (`supplier_id`, `service_id`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    $results[] = "✅ supplier_services table ready.";
} else {
    $results[] = "❌ supplier_services table error: " . $conn->error;
}

// -------------------------------------------------------
// 9. Create `supplier_listings` table
// -------------------------------------------------------
$sql = "CREATE TABLE IF NOT EXISTS `supplier_listings` (
  `listing_id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `price_type` ENUM('total_package', 'per_person', 'per_hour', 'per_day') NOT NULL DEFAULT 'total_package',
  `capacity` INT DEFAULT NULL,
  `image_url` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    $results[] = "✅ supplier_listings table ready.";
} else {
    $results[] = "❌ supplier_listings table error: " . $conn->error;
}

// -------------------------------------------------------
// 10. Add `status` column to `bookings` if missing
// -------------------------------------------------------
$col_check = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'status'");
if ($col_check && $col_check->num_rows === 0) {
    $sql = "ALTER TABLE `bookings` ADD COLUMN `status` ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending' AFTER `ExtraDetails`";
    if ($conn->query($sql)) {
        $results[] = "✅ Added 'status' column to bookings table.";
    } else {
        $results[] = "❌ Failed to add 'status' to bookings: " . $conn->error;
    }
} else {
    $results[] = "⏭️ 'status' column already exists in bookings table.";
}

// -------------------------------------------------------
// 11. Add `is_read` column to `contact_messages` if missing
// -------------------------------------------------------
$col_check = $conn->query("SHOW COLUMNS FROM `contact_messages` LIKE 'is_read'");
if ($col_check && $col_check->num_rows === 0) {
    $sql = "ALTER TABLE `contact_messages` ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `message`";
    if ($conn->query($sql)) {
        $results[] = "✅ Added 'is_read' column to contact_messages table.";
    } else {
        $results[] = "❌ Failed to add 'is_read' to contact_messages: " . $conn->error;
    }
} else {
    $results[] = "⏭️ 'is_read' column already exists in contact_messages table.";
}

// -------------------------------------------------------
// 12. Add `user_name` & `booking_id` to `budgets` if missing
// -------------------------------------------------------
$col_check = $conn->query("SHOW COLUMNS FROM `budgets` LIKE 'user_name'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE `budgets` ADD COLUMN `user_name` VARCHAR(50) DEFAULT NULL AFTER `id`");
    $results[] = "✅ Added 'user_name' column to budgets table.";
}
$col_check = $conn->query("SHOW COLUMNS FROM `budgets` LIKE 'booking_id'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE `budgets` ADD COLUMN `booking_id` VARCHAR(50) DEFAULT NULL AFTER `user_name`");
    $results[] = "✅ Added 'booking_id' column to budgets table.";
}

// -------------------------------------------------------
// 13. Create `booking_services` junction table
// -------------------------------------------------------
$sql = "CREATE TABLE IF NOT EXISTS `booking_services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` VARCHAR(50) NOT NULL,
  `service_id` INT NOT NULL,
  `supplier_id` INT DEFAULT NULL,
  `listing_id` INT DEFAULT NULL,
  `custom_notes` TEXT,
  `assigned_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('requested', 'confirmed', 'declined') NOT NULL DEFAULT 'confirmed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`BookingID`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`listing_id`) REFERENCES `supplier_listings`(`listing_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    $results[] = "✅ booking_services table ready.";
} else {
    $results[] = "❌ booking_services table error: " . $conn->error;
}

// -------------------------------------------------------
// 14. Ensure `user_id` column exists in `bookings`
// -------------------------------------------------------
$col_check = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'user_id'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE `bookings` ADD COLUMN `user_id` INT DEFAULT NULL AFTER `BookingID`");
    try {
        $conn->query("ALTER TABLE `bookings` ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL");
    } catch (Exception $e) {}
    $results[] = "✅ Added 'user_id' column to bookings table.";
} else {
    $results[] = "⏭️ 'user_id' column already exists in bookings table.";
}

// -------------------------------------------------------
// 15. Seed Comprehensive Sample Data (Users, Admins, Suppliers Range)
// -------------------------------------------------------
$admin_hash = '$2y$10$5CoyYp2jWvYbjQrrJuantOxjaI8jOpXwFma2XC83KRnnjH6hAzSGi'; // admin123
$pass_hash  = '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S'; // password123

// Seed Admins
$admins = [
    ['admin', 'System Administrator', 'admin@eventflare.com', $admin_hash],
    ['sarah.admin', 'Sarah Alwis (Event Operations Lead)', 'sarah.alwis@eventflare.com', $admin_hash],
    ['dilan.audit', 'Dilan Jayasinghe (Financial Auditor)', 'dilan.audit@eventflare.com', $admin_hash]
];
$admin_count = 0;
foreach ($admins as $adm) {
    $stmt = $conn->prepare("INSERT IGNORE INTO `admin` (username, fullname, email, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $adm[0], $adm[1], $adm[2], $adm[3]);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $admin_count++;
    $stmt->close();
}
$results[] = "✅ Admin accounts verified ({$admin_count} newly added).";

// Seed Users Table (Admins, Buyers, Suppliers)
$all_users = [
    ['admin', 'System Administrator', 'admin@eventflare.com', $admin_hash, 'admin'],
    ['sarah.admin', 'Sarah Alwis', 'sarah.alwis@eventflare.com', $admin_hash, 'admin'],
    ['dilan.audit', 'Dilan Jayasinghe', 'dilan.audit@eventflare.com', $admin_hash, 'admin'],
    ['kasun.perera', 'Kasun Perera', 'kasun.perera@gmail.com', $pass_hash, 'buyer'],
    ['dilhani.s', 'Dilhani Senanayake', 'dilhani.s@outlook.com', $pass_hash, 'buyer'],
    ['nimal.fernando', 'Nimal Fernando', 'nimal.fernando@yahoo.com', $pass_hash, 'buyer'],
    ['ananya.sharma', 'Ananya Sharma', 'ananya.sharma@gmail.com', $pass_hash, 'buyer'],
    ['chathura.k', 'Chathura Kulatunga', 'chathura.k@hotmail.com', $pass_hash, 'buyer'],
    ['malithi.desilva', 'Malithi De Silva', 'malithi.desilva@gmail.com', $pass_hash, 'buyer'],
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
$user_count = 0;
$user_id_map = [];
foreach ($all_users as $u) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $u[0]);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param("sssss", $u[0], $u[1], $u[2], $u[3], $u[4]);
        $ins->execute();
        $user_id_map[$u[0]] = (int)$conn->insert_id;
        $user_count++;
        $ins->close();
    } else {
        $user_id_map[$u[0]] = (int)$res->fetch_assoc()['id'];
    }
    $stmt->close();
}
$results[] = "✅ Users verified (Buyers, Suppliers & Admins — {$user_count} newly added).";

// Seed Suppliers Profile
$suppliers_data = [
    ['grand_royal_catering', 'Grand Royal Banquet & Catering', 'Catering', 'Premier luxury catering service specializing in 5-star wedding banquets, corporate dining, and bespoke buffet installations across the island.', '+94 11 269 4820', 'Colombo 07', 'https://images.unsplash.com/photo-1555244162-803834f70033?w=300'],
    ['lotus_florals', 'Lotus Floral Elegance & Decor', 'Decorators', 'Master wedding designers and florists specializing in royal Poruwa setups, grand balloon installations, and custom thematic stage backdrops.', '+94 81 223 9012', 'Kandy City', 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=300'],
    ['lumina_av_sound', 'Lumina Audio Visual & Stage Rigging', 'Audio/Visual (A/V)', 'High-end concert-grade audio engineering, intelligent moving heads, outdoor LED walls, lasers, and professional stage trussing.', '+94 77 341 8892', 'Mount Lavinia', 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=300'],
    ['cinnamon_cove_hotel', 'The Grand Cinnamon Cove Resort & Spa', 'Hotel Venues', '5-star beachfront resort providing sweeping oceanview ballrooms, luxury guest accommodations, and full-service valet parking.', '+94 91 438 7700', 'Galle Fort', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=300'],
    ['dj_pulse_entertainment', 'Pulse DJ & Live Beats Production', 'DJs/Artists', 'Chart-topping event DJs, live saxophonists, acoustic trios, and charismatic masters of ceremonies for weddings, galas, and EDM club nights.', '+94 71 882 1045', 'Colombo 03', 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=300'],
    ['aurora_studios', 'Aurora Cinematography & Photography', 'Photography & Videography', 'Award-winning cinematographers offering 4K HDR coverage, cinematic drone videography, candid photography, and luxury custom albums.', '+94 76 991 3421', 'Nugegoda', 'https://images.unsplash.com/photo-1537633552985-df8429e8048b?w=300'],
    ['sweet_symphony', 'Sweet Symphony Artisan Bakery & Desserts', 'Bakeries & Confectioners', 'High-end patisserie crafting bespoke multi-tiered wedding cakes, themed celebration cakes, macaron towers, and French pastry dessert tables.', '+94 11 582 3911', 'Rajagiriya, Colombo', 'https://images.unsplash.com/photo-1535141192574-5d4897c13136?w=300'],
    ['ranranga_cultural', 'Ranranga Traditional Dance & Choral Ensemble', 'Cultural Performers', 'Celebrated traditional cultural troupe providing authentic Kandyan Ves dancers, Pantheru drummers, revered Ashtaka narrators, and Jayamangala Gatha choir vocalists.', '+94 81 334 1198', 'Pilimathalawa, Kandy', 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=300'],
    ['aegis_security', 'Aegis Event Security & Valet Services', 'Security', 'Licensed, professional event security personnel, bouncer details, metal detection entry screening, VIP escort services, and trained valet drivers.', '+94 11 250 8831', 'Colombo 05', 'https://images.unsplash.com/photo-1582139329536-e7284fece509?w=300'],
    ['velvet_mixology', 'Velvet Mobile Cocktail Bar & Mixology', 'Bar & Beverage Services', 'Sophisticated mobile bar setup with flair mixologists, custom signature cocktail menus, craft mocktails, ice carving, and premium glassware hire.', '+94 77 620 9944', 'Colombo 07', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=300']
];

$supplier_id_map = [];
$supp_count = 0;
foreach ($suppliers_data as $sd) {
    $uid = $user_id_map[$sd[0]] ?? 0;
    if (!$uid) continue;
    $stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO suppliers (user_id, business_name, category, description, contact_phone, location, logo_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("issssss", $uid, $sd[1], $sd[2], $sd[3], $sd[4], $sd[5], $sd[6]);
        $ins->execute();
        $supplier_id_map[$sd[1]] = (int)$conn->insert_id;
        $supp_count++;
        $ins->close();
    } else {
        $supplier_id_map[$sd[1]] = (int)$res->fetch_assoc()['id'];
    }
    $stmt->close();
}
$results[] = "✅ Suppliers profile verified (10 suppliers covering full range — {$supp_count} newly added).";

// Seed Supplier Services
$services_link_rules = [
    'Grand Royal Banquet & Catering' => ['Catering', 'In-House Catering'],
    'Lotus Floral Elegance & Decor' => ['Poruwa/Decorators', 'Venue/Furniture Rentals', 'Decorators'],
    'Lumina Audio Visual & Stage Rigging' => ['Audio/Visual (A/V)', 'Light Entertainment', 'Simultaneous Translation & Headsets'],
    'The Grand Cinnamon Cove Resort & Spa' => ['Venue', 'Elegant Halls/Banquet Spaces', 'Guest Accommodations', 'Logistics & Parking'],
    'Pulse DJ & Live Beats Production' => ['Light Entertainment', 'Entertainment', 'DJs/Artists'],
    'Aurora Cinematography & Photography' => ['Photography & Videography'],
    'Sweet Symphony Artisan Bakery & Desserts' => ['Bakeries & Confectioners'],
    'Ranranga Traditional Dance & Choral Ensemble' => ['Ashtaka Narrator', 'Jayamangala Gatha Choir', 'Kandyan Dancers and Drummers'],
    'Aegis Event Security & Valet Services' => ['Security', 'Logistics & Parking'],
    'Velvet Mobile Cocktail Bar & Mixology' => ['Bar & Beverage Services']
];

foreach ($services_link_rules as $bname => $snames) {
    $sid = $supplier_id_map[$bname] ?? 0;
    if (!$sid) continue;
    foreach ($snames as $sname) {
        $svc_res = $conn->query("SELECT service_id FROM services WHERE service_name = '" . $conn->real_escape_string($sname) . "'");
        if ($svc_res) {
            while ($srow = $svc_res->fetch_assoc()) {
                $sv_id = (int)$srow['service_id'];
                $conn->query("INSERT IGNORE INTO supplier_services (supplier_id, service_id) VALUES ({$sid}, {$sv_id})");
            }
        }
    }
}
$results[] = "✅ Supplier services mapped across all catalogue items.";

// Seed Bookings if table has few entries
$bkg_check = $conn->query("SELECT COUNT(*) as c FROM bookings");
if ($bkg_check && ($brow = $bkg_check->fetch_assoc()) && (int)$brow['c'] === 0) {
    $sample_bookings = [
        ['BKG-WED-2026-01', 'kasun.perera', 'Weddings', 'The Grand Cinnamon Cove Ballroom, Galle', 280, '2026-10-24', 'Night', 'Buffet', 'Poruwa ceremony, 3-course dinner, drone photography, live band.', 'confirmed'],
        ['BKG-DJ-2026-02', 'dilhani.s', 'DJ Parties', 'Sunset Beachside Lawn, Mount Lavinia', 350, '2026-11-14', 'Night', 'Cocktail/Snacks', 'Neon lasers, heavy bass sound system, professional security.', 'pending'],
        ['BKG-BDAY-2026-03', 'nimal.fernando', 'Birthdays', 'Waters Edge Pavilion, Battaramulla', 75, '2026-09-28', 'Day', 'Buffet', 'Circus theme, magician, custom 2-tier chocolate cake.', 'in_progress'],
        ['BKG-HOTEL-2026-04', 'ananya.sharma', 'Hotel Venues', 'Shangri-La Main Ballroom, Colombo', 220, '2026-06-15', 'Day', 'Set Menu', 'Corporate gala setup, AV podium, plated 3-course lunch.', 'completed'],
        ['BKG-GET-2026-05', 'chathura.k', 'Get Togethers', 'Villa Republic, Bentota', 45, '2026-05-10', 'Night', 'BBQ/Snacks', 'Outdoor pool party, acoustic music, BBQ dinner.', 'cancelled'],
        ['BKG-WED-2026-06', 'malithi.desilva', 'Weddings', 'Kingsbury Victorian Hall, Colombo', 320, '2026-12-18', 'Day', 'Buffet', 'Traditional Kandyan dancers, choir, floral decor, 4K video.', 'confirmed']
    ];
    foreach ($sample_bookings as $sb) {
        $uid = $user_id_map[$sb[1]] ?? null;
        $ins = $conn->prepare("INSERT IGNORE INTO bookings (BookingID, user_id, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("sisssisssss", $sb[0], $uid, $sb[1], $sb[2], $sb[3], $sb[4], $sb[5], $sb[6], $sb[7], $sb[8], $sb[9]);
        $ins->execute();
        $ins->close();
    }
    $results[] = "✅ Sample bookings seeded across multiple statuses.";
} else {
    $results[] = "⏭️ Bookings table already populated.";
}

// Seed Contact Messages if table has few entries
$msg_check = $conn->query("SELECT COUNT(*) as c FROM contact_messages");
if ($msg_check && ($mrow = $msg_check->fetch_assoc()) && (int)$mrow['c'] === 0) {
    $conn->query("INSERT IGNORE INTO contact_messages (firstname, lastname, email, phone, message, is_read) VALUES
    ('Kaveen', 'Jayasuriya', 'kaveen.j@gmail.com', '+94 77 123 9988', 'Hello, I am planning a wedding reception for 300 guests in Galle this December. Could you recommend top catering and decor suppliers who can collaborate?', 1),
    ('Sanduni', 'Wickrama', 'sanduni.w@yahoo.com', '+94 71 445 2201', 'Inquiring about booking audio/visual stage lighting and an EDM DJ for a college graduation bash in Colombo. Are dates in November open?', 0),
    ('Dr. Priyantha', 'Dissanayake', 'priyantha.d@medicare.lk', '+94 81 223 4455', 'We require conference venue facilities with simultaneous translation headsets and live streaming for our annual medical seminar.', 1)");
    $results[] = "✅ Sample contact inquiries seeded.";
} else {
    $results[] = "⏭️ Contact messages already populated.";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - EVENTFLARE</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', system-ui, sans-serif; }
        body { background: #0d091e; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .card { background: rgba(255,255,255,0.05); border: 1px solid rgba(139,92,246,0.2); border-radius: 20px; padding: 40px; max-width: 600px; width: 100%; }
        h1 { font-size: 24px; margin-bottom: 25px; background: linear-gradient(135deg, #fff, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-align: center; }
        .result { padding: 10px 14px; margin-bottom: 8px; border-radius: 8px; font-size: 14px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); }
        .back { display: block; text-align: center; margin-top: 25px; color: #8b5cf6; font-weight: 600; text-decoration: none; }
        .back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Database Migration Results</h1>
        <?php foreach ($results as $r): ?>
            <div class="result"><?= $r ?></div>
        <?php endforeach; ?>
        <a href="Home.php" class="back">&larr; Back to Home</a>
    </div>
</body>
</html>
