CREATE DATABASE IF NOT EXISTS `event_planning_management`;
USE `event_planning_management`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `fullname` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'buyer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin Table
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `fullname` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookings Table
CREATE TABLE IF NOT EXISTS `bookings` (
  `BookingID` VARCHAR(50) PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `user_name` VARCHAR(50) NOT NULL,
  `EventType` VARCHAR(50) NOT NULL,
  `Place` VARCHAR(100) NOT NULL,
  `NumberOfGuests` INT NOT NULL,
  `EventDate` DATE NOT NULL,
  `DayNight` VARCHAR(20) NOT NULL,
  `FoodPreferences` VARCHAR(50) NOT NULL,
  `ExtraDetails` TEXT,
  `status` ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Budgets Table
CREATE TABLE IF NOT EXISTS `budgets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_name` VARCHAR(50) DEFAULT NULL,
  `booking_id` VARCHAR(50) DEFAULT NULL,
  `total_budget` DECIMAL(10,2) NOT NULL,
  `food_budget` DECIMAL(10,2) NOT NULL,
  `buffet_cost` DECIMAL(10,2) NOT NULL,
  `beverages_cost` DECIMAL(10,2) NOT NULL,
  `desserts_cost` DECIMAL(10,2) NOT NULL,
  `snacks_cost` DECIMAL(10,2) NOT NULL,
  `total_spent` DECIMAL(10,2) NOT NULL,
  `remaining_budget` DECIMAL(10,2) NOT NULL,
  `variance` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contact Messages Table
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firstname` VARCHAR(50) NOT NULL,
  `lastname` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event Extras Table
CREATE TABLE IF NOT EXISTS `event_extras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` VARCHAR(50) NOT NULL UNIQUE,
  `equipment` VARCHAR(50),
  `food_style` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`BookingID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Suppliers Profile Table
CREATE TABLE IF NOT EXISTS `suppliers` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- EVENT TYPES & SERVICES CATALOG  (normalised one-to-many)
-- ============================================================================

-- -------------------------------------------------------
-- DDL  –  Table: event_types
-- -------------------------------------------------------
-- DATA DICTIONARY
-- +-----------------+-------------------+-------------------------------------------+
-- | Column          | Type              | Description                               |
-- +-----------------+-------------------+-------------------------------------------+
-- | event_type_id   | INT (PK, AI)      | Unique identifier for the event category  |
-- | type_name       | VARCHAR(100)      | Human-readable event category label       |
-- | description     | TEXT              | Detailed summary of the event category    |
-- | is_active       | TINYINT(1)        | 1 = available for booking, 0 = disabled   |
-- | created_at      | TIMESTAMP         | Row creation timestamp                    |
-- | updated_at      | TIMESTAMP         | Last modification timestamp               |
-- +-----------------+-------------------+-------------------------------------------+

CREATE TABLE IF NOT EXISTS `event_types` (
  `event_type_id` INT AUTO_INCREMENT PRIMARY KEY,
  `type_name`     VARCHAR(100) NOT NULL UNIQUE,
  `description`   TEXT,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- DDL  –  Table: services
-- -------------------------------------------------------
-- DATA DICTIONARY
-- +--------------------+-------------------+----------------------------------------------------+
-- | Column             | Type              | Description                                        |
-- +--------------------+-------------------+----------------------------------------------------+
-- | service_id         | INT (PK, AI)      | Unique identifier for each service offering        |
-- | event_type_id      | INT (FK)          | References event_types(event_type_id)               |
-- | service_name       | VARCHAR(150)      | Short label for the service (e.g. "Catering")      |
-- | description        | TEXT              | Verbatim description from the service catalogue    |
-- | priority_rank      | INT               | Display/importance order within its event type     |
-- | is_required        | TINYINT(1)        | 1 = mandatory for the event, 0 = optional add-on   |
-- | typical_capacity   | INT               | Expected guest-count range this service supports   |
-- | notes              | TEXT              | Free-form operational/planning notes               |
-- | created_at         | TIMESTAMP         | Row creation timestamp                             |
-- | updated_at         | TIMESTAMP         | Last modification timestamp                        |
-- +--------------------+-------------------+----------------------------------------------------+

CREATE TABLE IF NOT EXISTS `services` (
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

  -- Constraints
  CONSTRAINT `fk_services_event_type`
    FOREIGN KEY (`event_type_id`) REFERENCES `event_types`(`event_type_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `chk_priority_rank`  CHECK (`priority_rank` >= 0),
  CONSTRAINT `chk_typical_capacity` CHECK (`typical_capacity` IS NULL OR `typical_capacity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- INDEXES  –  optimise common query patterns
-- -------------------------------------------------------
CREATE INDEX `idx_services_event_type`
  ON `services` (`event_type_id`);

CREATE INDEX `idx_services_event_type_priority`
  ON `services` (`event_type_id`, `priority_rank`);

CREATE INDEX `idx_services_name`
  ON `services` (`service_name`);

CREATE INDEX `idx_event_types_active`
  ON `event_types` (`is_active`);

-- -------------------------------------------------------
-- DDL  –  Table: supplier_services (Junction Table)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_supplier_service` (`supplier_id`, `service_id`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- DDL  –  Table: supplier_listings (Inventory & Packages)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_listings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- DDL  –  Table: booking_services (Service-Level Supplier Assignments)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `booking_services` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- DML  –  Seed data for the 5 core Event Types
-- ============================================================================

INSERT INTO `event_types` (`event_type_id`, `type_name`, `description`, `is_active`) VALUES
(1, 'Weddings',       'Elegant setups with floral arrangements, luxurious staging, and music.', 1),
(2, 'Get Togethers',  'Reconnections with friends/family featuring custom buffet layouts.',     1),
(3, 'Birthdays',      'Unforgettable experiences with customized themes, cakes, and games.',    1),
(4, 'DJ Parties',     'High-energy events with top-tier DJs, sound systems, and neon lighting.',1),
(5, 'Hotel Venues',   'Luxurious hotels and elegant halls for premium hosting facilities.',     1);

-- ============================================================================
-- DML  –  Seed data for Services  (≥ 3 per event type)
-- ============================================================================

-- ----- 1. Weddings (6 services) -----
INSERT INTO `services`
  (`event_type_id`, `service_name`, `description`, `priority_rank`, `is_required`, `typical_capacity`, `notes`)
VALUES
  (1, 'Poruwa/Decorators',
       'Poruwa/Decorators',
       1, 1, 250,
       'Traditional Poruwa ceremony setup with floral and lighting decorations. Must be booked at least 4 weeks in advance.'),

  (1, 'Ashtaka Narrator',
       'Ashtaka Narrator',
       2, 1, 250,
       'Narrates the eight stanzas of blessings during the Poruwa ceremony. Coordinate timing with the Jayamangala Gatha Choir.'),

  (1, 'Jayamangala Gatha Choir',
       'Jayamangala Gatha Choir',
       3, 1, 250,
       'Choir that chants the Jayamangala Gatha blessings. Typically 4-6 vocalists required.'),

  (1, 'Kandyan Dancers and Drummers',
       'Kandyan Dancers and Drummers',
       4, 0, 250,
       'Traditional Kandyan dance troupe for the ceremonial procession. Group sizes range from 6-20 performers.'),

  (1, 'Catering',
       'Catering',
       5, 1, 300,
       'Full-service wedding catering covering multi-course meals, dessert stations, and beverages.'),

  (1, 'Photography & Videography',
       'Photography & Videography',
       6, 1, 300,
       'Professional photo and video coverage including pre-shoot, ceremony, and reception highlights.');

-- ----- 2. Get Togethers (3 services) -----
INSERT INTO `services`
  (`event_type_id`, `service_name`, `description`, `priority_rank`, `is_required`, `typical_capacity`, `notes`)
VALUES
  (2, 'Catering',
       'Catering',
       1, 1, 100,
       'Buffet or plated meal service tailored for casual and semi-formal gatherings.'),

  (2, 'Venue/Furniture Rentals',
       'Venue/Furniture Rentals',
       2, 1, 100,
       'Tables, chairs, tents, and venue booking for indoor or outdoor get-togethers.'),

  (2, 'Light Entertainment',
       'Light Entertainment',
       3, 0, 100,
       'Acoustic music, lawn games, or a small PA system for background entertainment.');

-- ----- 3. Birthdays (4 services) -----
INSERT INTO `services`
  (`event_type_id`, `service_name`, `description`, `priority_rank`, `is_required`, `typical_capacity`, `notes`)
VALUES
  (3, 'Decorators',
       'Decorators',
       1, 1, 80,
       'Themed balloon arches, banners, table centrepieces, and stage backdrops.'),

  (3, 'Bakeries & Confectioners',
       'Bakeries & Confectioners',
       2, 1, 80,
       'Custom birthday cakes, cupcakes, and confectionery platters. Requires 1-week lead time for custom designs.'),

  (3, 'Entertainment',
       'Entertainment',
       3, 0, 80,
       'Magicians, face painters, bounce houses, or live performers for birthday celebrations.'),

  (3, 'Catering',
       'Catering',
       4, 1, 100,
       'Party-style catering with finger food, snack platters, and kid-friendly menus.');

-- ----- 4. DJ Parties (5 services) -----
INSERT INTO `services`
  (`event_type_id`, `service_name`, `description`, `priority_rank`, `is_required`, `typical_capacity`, `notes`)
VALUES
  (4, 'Audio/Visual (A/V)',
       'Audio/Visual (A/V)',
       1, 1, 500,
       'Full sound system, LED walls, laser lighting, and fog machines. Venue power supply must be verified.'),

  (4, 'DJs/Artists',
       'DJs/Artists',
       2, 1, 500,
       'Professional DJs or live performing artists. Rider requirements must be confirmed 2 weeks before event.'),

  (4, 'Venue',
       'Venue',
       3, 1, 500,
       'Indoor club, open-air arena, or rooftop space with adequate power and crowd capacity.'),

  (4, 'Bar & Beverage Services',
       'Bar & Beverage Services',
       4, 0, 500,
       'Licensed bar service with mixologists, beverage inventory, and age-verification protocols.'),

  (4, 'Security',
       'Security',
       5, 1, 500,
       'Trained security personnel for crowd control, entry screening, and emergency response.');

-- ----- 5. Hotel Venues (4 services) -----
INSERT INTO `services`
  (`event_type_id`, `service_name`, `description`, `priority_rank`, `is_required`, `typical_capacity`, `notes`)
VALUES
  (5, 'Elegant Halls/Banquet Spaces',
       'Elegant Halls/Banquet Spaces',
       1, 1, 400,
       'Grand ballrooms or banquet halls with stage, AV hookups, and flexible seating layouts.'),

  (5, 'In-House Catering',
       'In-House Catering',
       2, 1, 400,
       'Hotel-managed cuisine with customisable menus, wait staff, and dietary accommodation.'),

  (5, 'Guest Accommodations',
       'Guest Accommodations',
       3, 0, 200,
       'Discounted room blocks for event guests, including suite upgrades for VIPs.'),

  (5, 'Logistics & Parking',
       'Logistics & Parking',
       4, 1, 400,
       'Valet service, signage, shuttle coordination, and dedicated parking lot allocation.');

-- ============================================================================
-- DML  –  Seed Data: Administrative Accounts (admin)
-- ============================================================================
-- Password for all admin accounts: admin123
INSERT IGNORE INTO `admin` (`id`, `username`, `fullname`, `email`, `password`) VALUES
(1, 'admin', 'System Administrator', 'admin@eventflare.com', '$2y$10$5CoyYp2jWvYbjQrrJuantOxjaI8jOpXwFma2XC83KRnnjH6hAzSGi'),
(2, 'sarah.admin', 'Sarah Alwis (Event Operations Lead)', 'sarah.alwis@eventflare.com', '$2y$10$5CoyYp2jWvYbjQrrJuantOxjaI8jOpXwFma2XC83KRnnjH6hAzSGi'),
(3, 'dilan.audit', 'Dilan Jayasinghe (Financial Auditor)', 'dilan.audit@eventflare.com', '$2y$10$5CoyYp2jWvYbjQrrJuantOxjaI8jOpXwFma2XC83KRnnjH6hAzSGi');

-- ============================================================================
-- DML  –  Seed Data: Users (Admins, Buyers/Clients, and Suppliers)
-- ============================================================================
-- Password for admin users: admin123
-- Password for buyers & suppliers: password123
INSERT IGNORE INTO `users` (`id`, `username`, `fullname`, `email`, `password`, `role`) VALUES
-- Admins in users table (allows login via public/Login.php)
(1, 'admin', 'System Administrator', 'admin@eventflare.com', '$2y$10$5CoyYp2jWvYbjQrrJuantOxjaI8jOpXwFma2XC83KRnnjH6hAzSGi', 'admin'),
(2, 'sarah.admin', 'Sarah Alwis', 'sarah.alwis@eventflare.com', '$2y$10$5CoyYp2jWvYbjQrrJuantOxjaI8jOpXwFma2XC83KRnnjH6hAzSGi', 'admin'),
(3, 'dilan.audit', 'Dilan Jayasinghe', 'dilan.audit@eventflare.com', '$2y$10$5CoyYp2jWvYbjQrrJuantOxjaI8jOpXwFma2XC83KRnnjH6hAzSGi', 'admin'),

-- Event Clients / Buyers
(10, 'kasun.perera', 'Kasun Perera', 'kasun.perera@gmail.com', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'buyer'),
(11, 'dilhani.s', 'Dilhani Senanayake', 'dilhani.s@outlook.com', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'buyer'),
(12, 'nimal.fernando', 'Nimal Fernando', 'nimal.fernando@yahoo.com', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'buyer'),
(13, 'ananya.sharma', 'Ananya Sharma', 'ananya.sharma@gmail.com', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'buyer'),
(14, 'chathura.k', 'Chathura Kulatunga', 'chathura.k@hotmail.com', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'buyer'),
(15, 'malithi.desilva', 'Malithi De Silva', 'malithi.desilva@gmail.com', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'buyer'),

-- Event Suppliers (Covering full supplier range)
(20, 'grand_royal_catering', 'Chef Rohan Jayawardena', 'info@grandroyalcatering.lk', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier'),
(21, 'lotus_florals', 'Manel Wickramasinghe', 'design@lotusflorals.lk', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier'),
(22, 'lumina_av_sound', 'Dinesh Fonseka', 'events@luminaav.lk', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier'),
(23, 'cinnamon_cove_hotel', 'Amanda Ratnayake (GM)', 'banquets@cinnamoncove.com', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier'),
(24, 'dj_pulse_entertainment', 'Kevin Samarasinghe', 'booking@djpulse.lk', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier'),
(25, 'aurora_studios', 'Nuwan Rathnayake', 'hello@aurorastudios.lk', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier'),
(26, 'sweet_symphony', 'Chathurika Perera', 'orders@sweetsymphony.lk', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier'),
(27, 'ranranga_cultural', 'Pradeep Gunawardana', 'bookings@ranrangadance.lk', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier'),
(28, 'aegis_security', 'Capt. Sumith Bandara', 'operations@aegisprotection.lk', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier'),
(29, 'velvet_mixology', 'Tariq Mansoor', 'bar@velvetmixology.lk', '$2y$10$1WTOO2DDmoN62GoZPbrMOOnGivrzbEhQ/eJGwTJTq1HOObIC6kn4S', 'supplier');

-- ============================================================================
-- DML  –  Seed Data: Suppliers Profiles (10 Diverse Categories)
-- ============================================================================
INSERT IGNORE INTO `suppliers` (`id`, `user_id`, `business_name`, `category`, `description`, `contact_phone`, `location`, `logo_url`) VALUES
(1, 20, 'Grand Royal Banquet & Catering', 'Catering', 'Premier luxury catering service specializing in 5-star wedding banquets, corporate dining, and bespoke buffet installations across the island.', '+94 11 269 4820', 'Colombo 07', 'https://images.unsplash.com/photo-1555244162-803834f70033?w=300'),
(2, 21, 'Lotus Floral Elegance & Decor', 'Decorators', 'Master wedding designers and florists specializing in royal Poruwa setups, grand balloon installations, and custom thematic stage backdrops.', '+94 81 223 9012', 'Kandy City', 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=300'),
(3, 22, 'Lumina Audio Visual & Stage Rigging', 'Audio/Visual (A/V)', 'High-end concert-grade audio engineering, intelligent moving heads, outdoor LED walls, lasers, and professional stage trussing.', '+94 77 341 8892', 'Mount Lavinia', 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=300'),
(4, 23, 'The Grand Cinnamon Cove Resort & Spa', 'Hotel Venues', '5-star beachfront resort providing sweeping oceanview ballrooms, luxury guest accommodations, and full-service valet parking.', '+94 91 438 7700', 'Galle Fort', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=300'),
(5, 24, 'Pulse DJ & Live Beats Production', 'DJs/Artists', 'Chart-topping event DJs, live saxophonists, acoustic trios, and charismatic masters of ceremonies for weddings, galas, and EDM club nights.', '+94 71 882 1045', 'Colombo 03', 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=300'),
(6, 25, 'Aurora Cinematography & Photography', 'Photography & Videography', 'Award-winning cinematographers offering 4K HDR coverage, cinematic drone videography, candid photography, and luxury custom albums.', '+94 76 991 3421', 'Nugegoda', 'https://images.unsplash.com/photo-1537633552985-df8429e8048b?w=300'),
(7, 26, 'Sweet Symphony Artisan Bakery & Desserts', 'Bakeries & Confectioners', 'High-end patisserie crafting bespoke multi-tiered wedding cakes, themed celebration cakes, macaron towers, and French pastry dessert tables.', '+94 11 582 3911', 'Rajagiriya, Colombo', 'https://images.unsplash.com/photo-1535141192574-5d4897c13136?w=300'),
(8, 27, 'Ranranga Traditional Dance & Choral Ensemble', 'Cultural Performers', 'Celebrated traditional cultural troupe providing authentic Kandyan Ves dancers, Pantheru drummers, revered Ashtaka narrators, and Jayamangala Gatha choir vocalists.', '+94 81 334 1198', 'Pilimathalawa, Kandy', 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=300'),
(9, 28, 'Aegis Event Security & Valet Services', 'Security', 'Licensed, professional event security personnel, bouncer details, metal detection entry screening, VIP escort services, and trained valet drivers.', '+94 11 250 8831', 'Colombo 05', 'https://images.unsplash.com/photo-1582139329536-e7284fece509?w=300'),
(10, 29, 'Velvet Mobile Cocktail Bar & Mixology', 'Bar & Beverage Services', 'Sophisticated mobile bar setup with flair mixologists, custom signature cocktail menus, craft mocktails, ice carving, and premium glassware hire.', '+94 77 620 9944', 'Colombo 07', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=300');

-- ============================================================================
-- DML  –  Seed Data: Supplier Services (Junction Table Mappings)
-- ============================================================================
INSERT IGNORE INTO `supplier_services` (`supplier_id`, `service_id`) VALUES
(1, 5),   -- Grand Royal -> Catering (Weddings)
(1, 7),   -- Grand Royal -> Catering (Get Togethers)
(1, 13),  -- Grand Royal -> Catering (Birthdays)
(1, 20),  -- Grand Royal -> In-House Catering (Hotel Venues)
(2, 1),   -- Lotus Floral -> Poruwa/Decorators (Weddings)
(2, 8),   -- Lotus Floral -> Venue/Furniture Rentals (Get Togethers)
(2, 10),  -- Lotus Floral -> Decorators (Birthdays)
(3, 9),   -- Lumina AV -> Light Entertainment (Get Togethers)
(3, 14),  -- Lumina AV -> Audio/Visual (A/V) (DJ Parties)
(4, 16),  -- Cinnamon Cove -> Venue (DJ Parties)
(4, 19),  -- Cinnamon Cove -> Elegant Halls/Banquet Spaces (Hotel Venues)
(4, 21),  -- Cinnamon Cove -> Guest Accommodations (Hotel Venues)
(4, 22),  -- Cinnamon Cove -> Logistics & Parking (Hotel Venues)
(5, 9),   -- Pulse DJ -> Light Entertainment (Get Togethers)
(5, 12),  -- Pulse DJ -> Entertainment (Birthdays)
(5, 15),  -- Pulse DJ -> DJs/Artists (DJ Parties)
(6, 6),   -- Aurora Studios -> Photography & Videography (Weddings)
(7, 11),  -- Sweet Symphony -> Bakeries & Confectioners (Birthdays)
(8, 2),   -- Ranranga Cultural -> Ashtaka Narrator (Weddings)
(8, 3),   -- Ranranga Cultural -> Jayamangala Gatha Choir (Weddings)
(8, 4),   -- Ranranga Cultural -> Kandyan Dancers and Drummers (Weddings)
(9, 18),  -- Aegis Security -> Security (DJ Parties)
(9, 22),  -- Aegis Security -> Logistics & Parking (Hotel Venues)
(10, 17); -- Velvet Mixology -> Bar & Beverage Services (DJ Parties)

-- ============================================================================
-- DML  –  Seed Data: Supplier Listings & Inventory Packages
-- ============================================================================
INSERT IGNORE INTO `supplier_listings` (`listing_id`, `supplier_id`, `service_id`, `title`, `description`, `price`, `price_type`, `capacity`, `image_url`, `status`) VALUES
-- Grand Royal Banquet & Catering
(1, 1, 5, 'Platinum Wedding Feast (3-Course Buffet)', 'Five-star wedding banquet comprising multi-cuisine salads, signature carvery, 4 international meats, seafood specialties, and 12 delectable desserts.', 4800.00, 'per_person', 450, 'https://images.unsplash.com/photo-1555244162-803834f70033?w=500', 'active'),
(2, 1, 7, 'Cocktail & Canapés Evening Reception', 'Exquisite selection of warm & cold artisan hors d\'oeuvres, live sushi rolling, slider bar, and gourmet dessert shooters.', 3200.00, 'per_person', 300, 'https://images.unsplash.com/photo-1505236858219-8359eb29e329?w=500', 'active'),
(3, 1, 13, 'Kids Fiesta Birthday Buffet & Snacks', 'Colorful party spread featuring mini burger sliders, chicken nuggets, waffle cones, french fries, and fruit skewers.', 2500.00, 'per_person', 120, 'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=500', 'active'),

-- Lotus Floral Elegance & Decor
(4, 2, 1, 'Royal Heritage Poruwa & Floral Pavilion', 'Carved traditional Poruwa adorned with fresh lotus blossoms, orchids, oil lamp setup, ceremonial set, and illuminated walkway.', 225000.00, 'total_package', 350, 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=500', 'active'),
(5, 2, 10, 'Fairytale Balloon Arch & Backdrop Styling', 'Custom thematic organic balloon garland, customized acrylic name signage, neon number sign, and cylindrical dessert plinths.', 65000.00, 'total_package', 150, 'https://images.unsplash.com/photo-1527529482837-4698179dc6ce?w=500', 'active'),
(6, 2, 8, 'Rustic Outdoor Garden Seating & Canopy Setup', 'Vintage wooden cross-back chairs, banquet tables with linen overlays, fairy-light canopy, and chic cocktail barrels.', 95000.00, 'total_package', 120, 'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=500', 'active'),

-- Lumina Audio Visual & Stage Rigging
(7, 3, 14, 'Concert Stadium Audio & Stage Lighting Rig', 'High-fidelity dual line array system, 12 moving heads, laser beams, hazers, digital mixer, and dedicated sound engineer.', 185000.00, 'per_day', 600, 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=500', 'active'),
(8, 3, 14, 'Club Laser Lighting & Fog Effects System', 'High-powered RGB lasers, CO2 jets, stroboscopes, and dynamic computerized light control for high-energy dance parties.', 65000.00, 'per_day', 400, 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=500', 'active'),
(9, 3, 9, 'Acoustic PA & Wireless Microphone Package', 'Compact crystal-clear column PA system, 4 wireless microphones, and Bluetooth aux audio input for intimate gatherings.', 35000.00, 'per_day', 150, 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=500', 'active'),

-- The Grand Cinnamon Cove Resort & Spa
(10, 4, 19, 'Grand Horizon Oceanview Ballroom (Full Day)', 'Palatial oceanfront grand ballroom with imported crystal chandeliers, integrated acoustic treatment, private bridal suite, and dedicated banquet lobby.', 450000.00, 'per_day', 450, 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?w=500', 'active'),
(11, 4, 16, 'Sunset Beachside Lawn Pavilion', 'Expansive manicured lawn with panoramic ocean views, private beach access, ambient festoon lighting, and ocean breeze setup.', 280000.00, 'per_day', 300, 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=500', 'active'),
(12, 4, 21, 'Executive Suite Block & Guest Accommodations', 'Block booking of 10 Deluxe Oceanview Suites for wedding entourage or VIP guests, including breakfast and luxury spa access.', 150000.00, 'per_day', 50, 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=500', 'active'),

-- Pulse DJ & Live Beats Production
(13, 5, 15, 'Club Hits & Top 40 Live DJ Package', '5 hours of continuous high-energy party mixing by renowned DJ Kevin, covering EDM, commercial hits, Sinhala baila remixes, and hip-hop.', 75000.00, 'total_package', 500, 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=500', 'active'),
(14, 5, 9, 'Acoustic Trio & Ambient Vocals', 'Unplugged acoustic setup featuring lead vocals, acoustic guitar, and cajon percussion for elegant cocktail hours or dinners.', 60000.00, 'per_hour', 200, 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?w=500', 'active'),
(15, 5, 12, 'Interactive Birthday Party Emcee & Kids Games', 'Lively host managing fun party games, music competitions, cake cutting ceremony, and upbeat dance routines.', 30000.00, 'total_package', 100, 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=500', 'active'),

-- Aurora Cinematography & Photography
(16, 6, 6, 'Signature Wedding Cinematography (4K + Drone)', 'Full-day multi-camera coverage, cinematic 4K drone videography, 5-minute teaser highlight reel, and full ceremony documentary feature.', 290000.00, 'total_package', 400, 'https://images.unsplash.com/photo-1537633552985-df8429e8048b?w=500', 'active'),
(17, 6, 6, 'Pre-Shoot & Destination Portrait Session', '3-hour creative couples session at scenic location of your choice, including 40 magazine-retouched images and a guestbook canvas.', 95000.00, 'total_package', 10, 'https://images.unsplash.com/photo-1606800052052-a08af7148866?w=500', 'active'),
(18, 6, 6, 'Event Documentary Photography Coverage', 'Professional candid and formal photography covering corporate conferences, milestone birthdays, or anniversaries.', 55000.00, 'per_day', 250, 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?w=500', 'active'),

-- Sweet Symphony Artisan Bakery & Desserts
(19, 7, 11, 'Grand 3-Tier Handcrafted Fondant Wedding Cake', 'Exquisite 3-tier cake featuring Belgian chocolate ganache, handmade sugar florals, gold foil detailing, and custom flavor pairings.', 85000.00, 'total_package', 250, 'https://images.unsplash.com/photo-1535141192574-5d4897c13136?w=500', 'active'),
(20, 7, 11, 'Theme Custom Birthday Cake & Cupcake Tower (50 Pax)', 'Custom sculpted 2-tier themed birthday cake accompanied by 36 matching artisan cupcakes with custom fondant toppers.', 42000.00, 'total_package', 80, 'https://images.unsplash.com/photo-1588195538326-c5b1e9f80a1b?w=500', 'active'),
(21, 7, 11, 'Luxury French Macaron & Dessert Grazing Station', 'Decadent display of 120 assorted Parisian macarons, tartlets, chocolate truffles, and mini cheesecakes.', 60000.00, 'total_package', 150, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=500', 'active'),

-- Ranranga Traditional Dance & Choral Ensemble
(22, 8, 4, 'Grand Kandyan Dance Troupe & Drum Procession (12 Artists)', 'Traditional ceremonial entrance parade with 6 Ves dancers, 4 Pantheru drummers, and 2 ceremonial flag bearers.', 120000.00, 'total_package', 350, 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=500', 'active'),
(23, 8, 2, 'Traditional Ashtaka Narrator & Jayamangala Gatha Blessing', 'Revered and eloquent narrator delivering traditional Sanskrit blessings, accompanied by a 6-member girls choir in traditional attire.', 45000.00, 'total_package', 300, 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?w=500', 'active'),

-- Aegis Event Security & Valet Services
(24, 9, 18, 'VIP Event Security Detail & Bouncer Squad (6 Officers)', 'Six SIA-trained security officers for crowd control, guest list credential checks, bag screening, and perimeter protection.', 55000.00, 'per_day', 500, 'https://images.unsplash.com/photo-1582139329536-e7284fece509?w=500', 'active'),
(25, 9, 22, 'Valet Parking Management & Traffic Direction Team', 'Professional 6-person uniformed valet team providing vehicle greeting, secure designated parking, and numbered ticketing.', 40000.00, 'per_day', 350, 'https://images.unsplash.com/photo-1506521781263-d8422e82f27a?w=500', 'active'),

-- Velvet Mobile Cocktail Bar & Mixology
(26, 10, 17, 'Premium Mobile Cocktail Bar & Mixologists (5 Hours)', 'Illuminated bar station, 2 master flair mixologists, artisan syrups, dehydrated garnishes, premium ice, and custom cocktail menu.', 110000.00, 'total_package', 400, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=500', 'active'),
(27, 10, 17, 'Mocktail Bar & Artisan Lemonade Station', 'Non-alcoholic craft beverage bar with fresh fruit infusions, botanical spritzers, and organic fruit purees.', 45000.00, 'total_package', 250, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500', 'active');

-- ============================================================================
-- DML  –  Seed Data: Bookings (10 Multi-Status Bookings across all Event Types)
-- ============================================================================
INSERT IGNORE INTO `bookings` (`BookingID`, `user_id`, `user_name`, `EventType`, `Place`, `NumberOfGuests`, `EventDate`, `DayNight`, `FoodPreferences`, `ExtraDetails`, `status`) VALUES
('BKG-WED-2026-01', 10, 'kasun.perera', 'Weddings', 'The Grand Cinnamon Cove Ballroom, Galle', 280, '2026-10-24', 'Night', 'Buffet', 'Poruwa ceremony, 3-course dinner, drone photography, live band.', 'confirmed'),
('BKG-DJ-2026-02', 11, 'dilhani.s', 'DJ Parties', 'Sunset Beachside Lawn, Mount Lavinia', 350, '2026-11-14', 'Night', 'Cocktail/Snacks', 'Neon lasers, heavy bass sound system, professional security.', 'pending'),
('BKG-BDAY-2026-03', 12, 'nimal.fernando', 'Birthdays', 'Waters Edge Pavilion, Battaramulla', 75, '2026-09-28', 'Day', 'Buffet', 'Circus theme, magician, custom 2-tier chocolate cake.', 'in_progress'),
('BKG-HOTEL-2026-04', 13, 'ananya.sharma', 'Hotel Venues', 'Shangri-La Main Ballroom, Colombo', 220, '2026-06-15', 'Day', 'Set Menu', 'Corporate gala setup, AV podium, plated 3-course lunch.', 'completed'),
('BKG-GET-2026-05', 14, 'chathura.k', 'Get Togethers', 'Villa Republic, Bentota', 45, '2026-05-10', 'Night', 'BBQ/Snacks', 'Outdoor pool party, acoustic music, BBQ dinner.', 'cancelled'),
('BKG-WED-2026-06', 15, 'malithi.desilva', 'Weddings', 'Kingsbury Victorian Hall, Colombo', 320, '2026-12-18', 'Day', 'Buffet', 'Traditional Kandyan dancers, choir, floral decor, 4K video.', 'confirmed'),
('BKG-DJ-2026-07', 10, 'kasun.perera', 'DJ Parties', 'Viharamahadevi Amphitheatre, Colombo 07', 500, '2026-12-31', 'Night', 'Finger Food', 'New Year countdown festival, 3 guest DJs, full lighting.', 'pending'),
('BKG-HOTEL-2026-08', 11, 'dilhani.s', 'Hotel Venues', 'Jetwing Lighthouse, Galle', 150, '2026-08-20', 'Night', 'Buffet', 'Alumni annual reunion dinner, oceanfront seating.', 'completed'),
('BKG-BDAY-2026-09', 13, 'ananya.sharma', 'Birthdays', 'Earls Regency Grand Ballroom, Kandy', 90, '2026-10-05', 'Night', 'Buffet', 'Golden jubilee 50th birthday dinner, live acoustic trio.', 'in_progress'),
('BKG-GET-2026-10', 12, 'nimal.fernando', 'Get Togethers', 'Bolgoda Lake Resort, Moratuwa', 60, '2026-11-28', 'Day', 'Buffet', 'Family reunion picnic by the lake with lawn games.', 'pending');

-- ============================================================================
-- DML  –  Seed Data: Event Extras
-- ============================================================================
INSERT IGNORE INTO `event_extras` (`booking_id`, `equipment`, `food_style`) VALUES
('BKG-WED-2026-01', 'Stage Lighting, Floral Mandap, 4K Projector', 'International Buffet & Live Cooking'),
('BKG-DJ-2026-02', 'Line Array Sound, Smoke Machine, Laser Rig', 'Finger Food & Mocktail Bar'),
('BKG-BDAY-2026-03', 'PA System, Party Balloons, Photo Booth', 'Dessert Table & Kids Finger Food'),
('BKG-HOTEL-2026-04', 'Podium Mics, Dual LED Screens, Sound System', 'Executive High Tea & Plated Lunch'),
('BKG-GET-2026-05', 'Acoustic Speaker, Garden Gazebo', 'BBQ Grill & Local Sri Lankan Buffet'),
('BKG-WED-2026-06', 'Traditional Poruwa, LED Ambience, Drone', 'Grand Sri Lankan & Eastern Fusion Feast'),
('BKG-DJ-2026-07', 'Subwoofers, Co2 Cannons, Moving Heads', 'Street Food Stalls & Energy Drinks'),
('BKG-HOTEL-2026-08', 'Banquet Lighting & Podium Sound', 'Seafood Extravaganza Buffet'),
('BKG-BDAY-2026-09', 'Ambient Uplighting & Wireless Mics', 'Western Buffet & Custom Cake'),
('BKG-GET-2026-10', 'Portable Sound Box & Outdoor Canopy', 'Rice & Curry Feast with Dessert Counter');

-- ============================================================================
-- DML  –  Seed Data: Booking Services (Assignments & Costs)
-- ============================================================================
INSERT IGNORE INTO `booking_services` (`booking_id`, `service_id`, `supplier_id`, `listing_id`, `custom_notes`, `assigned_cost`, `status`) VALUES
('BKG-WED-2026-01', 1, 2, 4, 'Theme colors: Ivory and blush pink with gold accents', 225000.00, 'confirmed'),
('BKG-WED-2026-01', 5, 1, 1, '280 guests at Rs. 4,800/pax platinum buffet', 1344000.00, 'confirmed'),
('BKG-WED-2026-01', 6, 6, 16, 'Full day drone and 4K cinema coverage', 290000.00, 'confirmed'),
('BKG-DJ-2026-02', 14, 3, 7, 'Bass heavy setting required for open beach lawn', 185000.00, 'requested'),
('BKG-DJ-2026-02', 15, 5, 13, 'Set time 8:00 PM to 1:00 AM', 75000.00, 'requested'),
('BKG-DJ-2026-02', 18, 9, 24, 'Wristband entry check at beach gate', 55000.00, 'requested'),
('BKG-BDAY-2026-03', 10, 2, 5, 'Pastel circus colors with name cutout', 65000.00, 'confirmed'),
('BKG-BDAY-2026-03', 11, 7, 20, 'Belgium chocolate truffle cake with 36 cupcakes', 42000.00, 'confirmed'),
('BKG-BDAY-2026-03', 12, 5, 15, 'Includes magic show and balloon twisting for kids', 30000.00, 'confirmed'),
('BKG-WED-2026-06', 1, 2, 4, 'Deep maroon and jasmine floral arrangements', 225000.00, 'confirmed'),
('BKG-WED-2026-06', 4, 8, 22, '12-member Ves dancers for traditional procession', 120000.00, 'confirmed'),
('BKG-WED-2026-06', 6, 6, 16, 'Pre-shoot plus wedding day documentary', 290000.00, 'confirmed');

-- ============================================================================
-- DML  –  Seed Data: Budgets & Expense Logs
-- ============================================================================
INSERT IGNORE INTO `budgets` (`user_name`, `booking_id`, `total_budget`, `food_budget`, `buffet_cost`, `beverages_cost`, `desserts_cost`, `snacks_cost`, `total_spent`, `remaining_budget`, `variance`) VALUES
('kasun.perera', 'BKG-WED-2026-01', 2500000.00, 1500000.00, 1344000.00, 180000.00, 120000.00, 50000.00, 2200000.00, 300000.00, 300000.00),
('dilhani.s', 'BKG-DJ-2026-02', 750000.00, 250000.00, 0.00, 150000.00, 35000.00, 65000.00, 630000.00, 120000.00, 120000.00),
('nimal.fernando', 'BKG-BDAY-2026-03', 350000.00, 180000.00, 120000.00, 25000.00, 42000.00, 20000.00, 310000.00, 40000.00, 40000.00),
('ananya.sharma', 'BKG-HOTEL-2026-04', 1800000.00, 950000.00, 700000.00, 150000.00, 80000.00, 50000.00, 1720000.00, 80000.00, 80000.00),
('chathura.k', 'BKG-GET-2026-05', 200000.00, 120000.00, 90000.00, 20000.00, 10000.00, 15000.00, 0.00, 200000.00, 200000.00),
('malithi.desilva', 'BKG-WED-2026-06', 3200000.00, 1800000.00, 1536000.00, 200000.00, 150000.00, 60000.00, 2850000.00, 350000.00, 350000.00),
('kasun.perera', 'BKG-DJ-2026-07', 1200000.00, 400000.00, 250000.00, 200000.00, 40000.00, 80000.00, 850000.00, 350000.00, 350000.00),
('dilhani.s', 'BKG-HOTEL-2026-08', 1500000.00, 900000.00, 750000.00, 150000.00, 80000.00, 40000.00, 1420000.00, 80000.00, 80000.00),
('ananya.sharma', 'BKG-BDAY-2026-09', 480000.00, 260000.00, 180000.00, 35000.00, 45000.00, 25000.00, 410000.00, 70000.00, 70000.00),
('nimal.fernando', 'BKG-GET-2026-10', 280000.00, 160000.00, 120000.00, 25000.00, 20000.00, 15000.00, 220000.00, 60000.00, 60000.00);

-- ============================================================================
-- DML  –  Seed Data: Contact Messages (Client Inquiries)
-- ============================================================================
INSERT IGNORE INTO `contact_messages` (`firstname`, `lastname`, `email`, `phone`, `message`, `is_read`) VALUES
('Kaveen', 'Jayasuriya', 'kaveen.j@gmail.com', '+94 77 123 9988', 'Hello, I am planning a wedding reception for 300 guests in Galle this December. Could you recommend top catering and decor suppliers who can collaborate?', 1),
('Sanduni', 'Wickrama', 'sanduni.w@yahoo.com', '+94 71 445 2201', 'Inquiring about booking audio/visual stage lighting and an EDM DJ for a college graduation bash in Colombo. Are dates in November open?', 0),
('Dr. Priyantha', 'Dissanayake', 'priyantha.d@medicare.lk', '+94 81 223 4455', 'We require conference venue facilities with simultaneous translation headsets and live streaming for our annual medical seminar.', 1),
('Hansi', 'Madushani', 'hansi.m@gmail.com', '+94 76 890 1123', 'Can you provide a package quote for a 1st birthday party with balloon decor, 2-tier custom fondant cake, and kid entertainment?', 0),
('Lakshan', 'Weerakkody', 'lakshan.w@techpulse.io', '+94 70 332 9901', 'Interested in signing up as an official event partner for sound production and drone filming. Who should our team contact?', 0),
('Tharushi', 'Alwis', 'tharushi.alwis@gmail.com', '+94 77 554 8871', 'Thank you for coordinating our beach get-together last weekend! The food and acoustic music trio were phenomenal.', 1);

-- ============================================================================
-- VALIDATION CHECKLIST  (run after import to verify data integrity)
-- ============================================================================

-- 1. All 5 event types exist
-- SELECT COUNT(*) AS event_type_count FROM `event_types`;
--   → Expected: 5

-- 2. Total services inserted
-- SELECT COUNT(*) AS total_services FROM `services`;
--   → Expected: 22

-- 3. Every service references a valid event type (FK integrity)
-- SELECT s.service_id, s.service_name
--   FROM `services` s
--   LEFT JOIN `event_types` et ON s.event_type_id = et.event_type_id
--   WHERE et.event_type_id IS NULL;
--   → Expected: 0 rows (no orphans)

-- 4. No duplicate service names within the same event type
-- SELECT event_type_id, service_name, COUNT(*) AS cnt
--   FROM `services`
--   GROUP BY event_type_id, service_name
--   HAVING cnt > 1;
--   → Expected: 0 rows

-- 5. Priority ranks are non-negative
-- SELECT service_id, priority_rank
--   FROM `services`
--   WHERE priority_rank < 0;
--   → Expected: 0 rows

-- 6. Typical capacity is positive where set
-- SELECT service_id, typical_capacity
--   FROM `services`
--   WHERE typical_capacity IS NOT NULL AND typical_capacity <= 0;
--   → Expected: 0 rows

-- 7. Minimum 3 services per event type
-- SELECT et.type_name, COUNT(s.service_id) AS svc_count
--   FROM `event_types` et
--   LEFT JOIN `services` s ON et.event_type_id = s.event_type_id
--   GROUP BY et.event_type_id
--   HAVING svc_count < 3;
--   → Expected: 0 rows

-- ============================================================================
-- EXTENSION GUIDE
-- ============================================================================
-- To add a new event type:
--   1. INSERT INTO `event_types` (type_name, description) VALUES ('New Type', '...');
--   2. Note the generated event_type_id (or use LAST_INSERT_ID()).
--   3. INSERT INTO `services` (event_type_id, service_name, description, priority_rank,
--      is_required, typical_capacity, notes) VALUES (<id>, '...', '...', 1, 1, 100, '...');
--
-- To deactivate an event type without deleting data:
--   UPDATE `event_types` SET is_active = 0 WHERE event_type_id = <id>;
--
-- To query all services for a given event type in display order:
--   SELECT s.* FROM `services` s
--     JOIN `event_types` et ON s.event_type_id = et.event_type_id
--     WHERE et.type_name = 'Weddings'
--     ORDER BY s.priority_rank;
-- ============================================================================
