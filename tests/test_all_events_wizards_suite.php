<?php
/**
 * Automated Comprehensive Test Suite for All Event Types & Dedicated 4-Stage Multi-Supplier Wizards
 * Covers: Weddings, DJ & Parties, Birthdays, Get Togethers
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/database.php';

echo "=======================================================================\n";
echo " Running All Event Types & Multi-Supplier Booking Wizards Test Suite\n";
echo "=======================================================================\n\n";

$passed = 0;
$failed = 0;

function assert_true($condition, $message) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $message\n";
        $passed++;
    } else {
        echo "  [FAIL] $message\n";
        $failed++;
    }
}

// 1. Files existence & PHP Linting
$files = [
    'public/events/WeddingsSlids.php',
    'public/events/WeddingBooking.php',
    'public/events/DjPartySlide.php',
    'public/events/PartyBooking.php',
    'public/events/BirthdayList.php',
    'public/events/BirthdayBooking.php',
    'public/events/GetTogether.php',
    'public/events/GetTogetherBooking.php',
    'public/ChooseEvent.php',
    'public/Booking.php',
    'public/Home.php'
];

foreach ($files as $f) {
    $fullPath = $rootDir . '/' . $f;
    assert_true(file_exists($fullPath), "$f exists on disk");
    $lint = shell_exec("php -l " . escapeshellarg($fullPath));
    assert_true(strpos($lint, 'No syntax errors') !== false, "$f passes PHP linting");
}

// 2. Active Supplier Listings Coverage per Event Type
$event_types_to_check = [
    ['id' => 1, 'name' => 'Weddings',      'min' => 6],
    ['id' => 2, 'name' => 'Get Togethers', 'min' => 3],
    ['id' => 3, 'name' => 'Birthdays',     'min' => 4],
    ['id' => 4, 'name' => 'DJ Parties',    'min' => 5],
];

foreach ($event_types_to_check as $et) {
    $stmt = $conn->prepare("SELECT COUNT(sl.listing_id) as cnt 
                            FROM supplier_listings sl 
                            JOIN services s ON sl.service_id = s.service_id 
                            WHERE s.event_type_id = ? AND sl.status = 'active'");
    $stmt->bind_param("i", $et['id']);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $cnt = (int)$res['cnt'];
    $stmt->close();

    assert_true($cnt >= $et['min'], "Event Type [{$et['name']}] has active packages (Found: $cnt, Expected >= {$et['min']})");
}

// 3. Multi-Table Atomic Transaction Tests for DJ Parties, Birthdays, and Get Togethers
$test_cases = [
    [
        'event_type' => 'DJ Parties',
        'prefix'     => 'BKG-TEST-DJP-',
        'name'       => 'Electric Sunset Rave',
        'food_tier'  => 'Cocktail Tapas & Finger Bites',
        'service_ids'=> [14, 15, 17], // Audio/Visual, DJs, Bar
        'guests'     => 200,
        'equip'      => 'A/V Laser Rig & DJ Console'
    ],
    [
        'event_type' => 'Birthdays',
        'prefix'     => 'BKG-TEST-BDY-',
        'name'       => 'Senuri 16th Glamour',
        'food_tier'  => 'Deluxe Birthday High Tea & Savory Spread',
        'service_ids'=> [10, 11, 13], // Decor, Bakery, Catering
        'guests'     => 100,
        'equip'      => 'Balloon Arch & Custom Cake Plinths'
    ],
    [
        'event_type' => 'Get Togethers',
        'prefix'     => 'BKG-TEST-GTG-',
        'name'       => 'Class of 2012 Reunion',
        'food_tier'  => 'Live Outdoor BBQ & Grilled Meats',
        'service_ids'=> [7, 8, 9], // Catering, Rentals, Light Entertainment
        'guests'     => 150,
        'equip'      => 'Marquee Tents & Acoustic Sing-Along Setup'
    ]
];

foreach ($test_cases as $tc) {
    $b_id = $tc['prefix'] . strtoupper(uniqid());
    $test_user = "test_buyer_" . strtolower(substr($tc['event_type'], 0, 3));
    $test_place = "Venue To Be Coordinated by EVENTFLARE";
    $test_date = date('Y-m-d', strtotime('+2 months'));
    $test_day_night = "Night";
    $test_notes = "Automated Transaction Test for " . $tc['name'];

    $conn->begin_transaction();
    try {
        // 1. Insert into bookings
        $b_stmt = $conn->prepare("INSERT INTO bookings (BookingID, user_id, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails, status) 
                                  VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $b_stmt->bind_param("ssssissss", $b_id, $test_user, $tc['event_type'], $test_place, $tc['guests'], $test_date, $test_day_night, $tc['food_tier'], $test_notes);
        $b_stmt->execute();
        $b_stmt->close();

        // 2. Insert into event_extras
        $ee_stmt = $conn->prepare("INSERT INTO event_extras (booking_id, equipment, food_style) VALUES (?, ?, ?)");
        $ee_stmt->bind_param("sss", $b_id, $tc['equip'], $tc['food_tier']);
        $ee_stmt->execute();
        $ee_stmt->close();

        // 3. Insert into booking_services
        $bs_stmt = $conn->prepare("INSERT INTO booking_services (booking_id, service_id, supplier_id, listing_id, custom_notes, assigned_cost, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'confirmed')");

        $svc_in = implode(',', array_map('intval', $tc['service_ids']));
        $l_res = $conn->query("SELECT listing_id, supplier_id, service_id, price, price_type, title 
                               FROM supplier_listings 
                               WHERE service_id IN ($svc_in) AND status = 'active' LIMIT 3");
        
        $assigned_count = 0;
        $total_cost = 0.0;
        while ($lr = $l_res->fetch_assoc()) {
            $cost = (float)$lr['price'];
            if ($lr['price_type'] === 'per_person') {
                $cost = $cost * $tc['guests'];
            }
            $total_cost += $cost;
            $note = "Auto-Test Selection: " . $lr['title'];
            $bs_stmt->bind_param("siiisd", $b_id, $lr['service_id'], $lr['supplier_id'], $lr['listing_id'], $note, $cost);
            $bs_stmt->execute();
            $assigned_count++;
        }
        $bs_stmt->close();

        $conn->commit();
        $tx_ok = true;
    } catch (Throwable $e) {
        $conn->rollback();
        $tx_ok = false;
        echo "    [ERROR in {$tc['event_type']}]: " . $e->getMessage() . "\n";
    }

    assert_true($tx_ok, "[{$tc['event_type']}] Atomic multi-table booking transaction committed");
    assert_true($assigned_count >= 2, "[{$tc['event_type']}] At least 2 service supplier packages linked (Assigned: $assigned_count)");

    // Verify in database
    $chk_b = $conn->query("SELECT * FROM bookings WHERE BookingID = '$b_id'");
    assert_true($chk_b && $chk_b->num_rows === 1, "[{$tc['event_type']}] Master booking verified in bookings table");

    $chk_ee = $conn->query("SELECT * FROM event_extras WHERE booking_id = '$b_id'");
    assert_true($chk_ee && $chk_ee->num_rows === 1, "[{$tc['event_type']}] Extras record verified in event_extras table");

    $chk_bs = $conn->query("SELECT * FROM booking_services WHERE booking_id = '$b_id'");
    assert_true($chk_bs && $chk_bs->num_rows === $assigned_count, "[{$tc['event_type']}] Supplier assignments verified in booking_services table");

    // Clean up
    $conn->query("DELETE FROM bookings WHERE BookingID = '$b_id'");
    $clean_chk = $conn->query("SELECT * FROM bookings WHERE BookingID = '$b_id'");
    assert_true($clean_chk && $clean_chk->num_rows === 0, "[{$tc['event_type']}] Test records cleaned up successfully");
}

// 4. UI & Form Consistency Checks Across All 4 Wizards
$wizards = [
    'WeddingBooking.php'    => $rootDir . '/public/events/WeddingBooking.php',
    'PartyBooking.php'      => $rootDir . '/public/events/PartyBooking.php',
    'BirthdayBooking.php'   => $rootDir . '/public/events/BirthdayBooking.php',
    'GetTogetherBooking.php'=> $rootDir . '/public/events/GetTogetherBooking.php'
];

foreach ($wizards as $name => $path) {
    $content = file_get_contents($path);
    assert_true(strpos($content, '<label class="form-label" for="place">Venue Name & City</label>') === false, 
        "$name Stage 1 has NO visible Venue Name & City input");
    assert_true(strpos($content, 'stepper-header') !== false, "$name contains Progress Stepper");
    assert_true(strpos($content, 'stepper-progress-line') !== false, "$name contains stepper-progress-line");
    assert_true(strpos($content, 'compileReviewAndCost') !== false, "$name contains JavaScript Live Budget Calculator");
    assert_true(strpos($content, 'supplier-option-card') !== false, "$name contains Interactive Supplier Selection Cards");
}

// 5. Cross-Platform Linking Checks
$choose_content = file_get_contents($rootDir . '/public/ChooseEvent.php');
assert_true(strpos($choose_content, 'events/WeddingsSlids.php') !== false, "ChooseEvent.php links to Weddings showcase");
assert_true(strpos($choose_content, 'events/DjPartySlide.php') !== false, "ChooseEvent.php links to DJ Party showcase");
assert_true(strpos($choose_content, 'events/BirthdayList.php') !== false, "ChooseEvent.php links to Birthday showcase");
assert_true(strpos($choose_content, 'events/GetTogether.php') !== false, "ChooseEvent.php links to Get Together showcase");

$booking_content = file_get_contents($rootDir . '/public/Booking.php');
assert_true(strpos($booking_content, 'events/WeddingBooking.php') !== false, "Booking.php recommends WeddingBooking.php");
assert_true(strpos($booking_content, 'events/PartyBooking.php') !== false, "Booking.php recommends PartyBooking.php");
assert_true(strpos($booking_content, 'events/BirthdayBooking.php') !== false, "Booking.php recommends BirthdayBooking.php");
assert_true(strpos($booking_content, 'events/GetTogetherBooking.php') !== false, "Booking.php recommends GetTogetherBooking.php");

$home_content = file_get_contents($rootDir . '/public/Home.php');
assert_true(strpos($home_content, 'events/WeddingsSlids.php') !== false, "Home.php links to WeddingsSlids.php");
assert_true(strpos($home_content, 'events/DjPartySlide.php') !== false, "Home.php links to DjPartySlide.php");
assert_true(strpos($home_content, 'events/BirthdayList.php') !== false, "Home.php links to BirthdayList.php");
assert_true(strpos($home_content, 'events/GetTogether.php') !== false, "Home.php links to GetTogether.php");

// 6. Mandatory Buyer Authentication & Safe Redirect Checks Across All Wizards
$login_content = file_get_contents($rootDir . '/public/Login.php');
assert_true(strpos($login_content, "\$_SESSION['user_id'] = (int)\$row['id'];") !== false, "Login.php stores user_id in session");
assert_true(strpos($login_content, 'name="redirect"') !== false, "Login.php form contains hidden redirect input");
assert_true(strpos($login_content, 'header("location: " . $redirect);') !== false, "Login.php redirects to safe target");

assert_true(strpos($booking_content, 'header("Location: Login.php?redirect="') !== false, "Booking.php redirects unauthenticated users to Login.php with redirect parameter");

$mybookings_content = file_get_contents($rootDir . '/public/MyBookings.php');
assert_true(strpos($mybookings_content, 'b.user_id = ?') !== false, "MyBookings.php queries by user_id and user_name");

foreach ($wizards as $name => $path) {
    $content = file_get_contents($path);
    assert_true(strpos($content, 'name="guest_username"') === false, 
        "$name has completely REMOVED guest_username input field");
    assert_true(strpos($content, 'Quick Guest Booking') === false, 
        "$name has completely REMOVED Quick Guest Booking box");
    assert_true(strpos($content, 'header("Location: ../Login.php?redirect="') !== false, 
        "$name enforces unauthenticated redirect to Login.php with redirect parameter");
    assert_true(strpos($content, 'auth-account-badge') !== false, 
        "$name displays Authenticated Buyer Account badge in Stage 4");
    assert_true(strpos($content, "\$customer_username = \$logged_user;") !== false, 
        "$name strictly sets customer username to authenticated buyer session");
    
    $navPos = strpos($content, 'navbar.php');
    $hdrPos = strpos($content, 'header("Location: ../Login.php?redirect=');
    assert_true($navPos !== false && $hdrPos !== false && $hdrPos < $navPos,
        "$name performs header redirect BEFORE navbar.php include (prevents 'headers already sent')");
}

echo "\n=======================================================================\n";
echo "Summary: $passed Passed, $failed Failed\n";
echo "=======================================================================\n";

if ($failed === 0) {
    echo "🎉 All Event Types & Multi-Supplier Booking Wizards Tests PASSED!\n";
    exit(0);
} else {
    echo "❌ Some tests failed!\n";
    exit(1);
}
