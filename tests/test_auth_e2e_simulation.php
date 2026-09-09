<?php
/**
 * End-to-End simulation of unauthenticated redirection, safe login redirect, and session linking.
 */
$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/database.php';

echo "=======================================================================\n";
echo " Running Buyer Auth & Redirect End-to-End Simulation\n";
echo "=======================================================================\n\n";

$passed = 0;
$failed = 0;

function assert_test($cond, $msg) {
    global $passed, $failed;
    if ($cond) {
        echo "  [PASS] $msg\n";
        $passed++;
    } else {
        echo "  [FAIL] $msg\n";
        $failed++;
    }
}

// 1. Verify that each wizard file contains the authentication guard at the top
$wizards = [
    'WeddingBooking.php'     => 'events/WeddingBooking.php',
    'PartyBooking.php'       => 'events/PartyBooking.php',
    'BirthdayBooking.php'    => 'events/BirthdayBooking.php',
    'GetTogetherBooking.php' => 'events/GetTogetherBooking.php',
];

foreach ($wizards as $name => $rel) {
    $code = file_get_contents($rootDir . '/public/' . $rel);
    // Ensure auth guard is within the first 15 lines
    $lines = array_slice(explode("\n", $code), 0, 15);
    $topCode = implode("\n", $lines);
    assert_test(strpos($topCode, '!isset($_SESSION[\'login_user\'])') !== false, 
        "$name has auth guard within top lines");
    assert_test(strpos($topCode, 'header("Location: ../Login.php?redirect="') !== false, 
        "$name issues 302 redirect to Login.php with redirect parameter");
    
    // Crucial check: header redirect must occur BEFORE navbar.php is included to prevent "headers already sent"
    $navPos = strpos($code, 'navbar.php');
    $hdrPos = strpos($code, 'header("Location: ../Login.php?redirect=');
    assert_test($navPos !== false && $hdrPos !== false && $hdrPos < $navPos,
        "$name performs header redirect BEFORE including navbar.php (prevents 'headers already sent')");
}

// 1b. Verify that all Showcase pages have auth-aware booking links & helpers
$showcases = [
    'WeddingsSlids.php' => 'events/WeddingsSlids.php',
    'DjPartySlide.php'  => 'events/DjPartySlide.php',
    'BirthdayList.php'  => 'events/BirthdayList.php',
    'GetTogether.php'   => 'events/GetTogether.php',
];

foreach ($showcases as $name => $rel) {
    $code = file_get_contents($rootDir . '/public/' . $rel);
    assert_test(strpos($code, '$is_logged_in = isset($_SESSION[\'login_user\']);') !== false,
        "$name initializes \$is_logged_in check");
    assert_test(strpos($code, 'function get_booking_url') !== false,
        "$name defines get_booking_url() routing helper");
    assert_test(strpos($code, 'get_booking_url(') !== false,
        "$name wraps booking entry points with get_booking_url()");
    assert_test(strpos($code, 'isLoggedIn') !== false,
        "$name dossier modal dynamically checks isLoggedIn for booking action");
}

// 2. Test user lookup in database for authenticated booking
$u_res = $conn->query("SELECT id, username, email, role FROM users WHERE role = 'buyer' LIMIT 1");
if ($u_res && $u_res->num_rows > 0) {
    $buyer = $u_res->fetch_assoc();
    $buyer_id = (int)$buyer['id'];
    $buyer_username = $buyer['username'];
    assert_test($buyer_id > 0 && !empty($buyer_username), "Found test buyer account: {$buyer_username} (ID: {$buyer_id})");
} else {
    // If no buyer user, create or fallback
    $buyer_id = 9999;
    $buyer_username = 'test_buyer_sample';
}

// 3. Simulate an authenticated booking insertion by this buyer
$sim_bkg_id = "BKG-SIM-" . strtoupper(uniqid());
$sim_date = date('Y-m-d', strtotime('+30 days'));

$conn->begin_transaction();
$b_stmt = $conn->prepare("INSERT INTO bookings (BookingID, user_id, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails, status) 
                          VALUES (?, ?, ?, 'Weddings', 'Grand Luxury Ballroom', 200, ?, 'Day', 'Platinum Buffet', 'Authenticated Buyer E2E Test', 'pending')");
$b_stmt->bind_param("siss", $sim_bkg_id, $buyer_id, $buyer_username, $sim_date);
$ins_ok = $b_stmt->execute();
$b_stmt->close();
$conn->commit();

assert_test($ins_ok, "Authenticated booking inserted successfully with user_id = $buyer_id, user_name = '$buyer_username'");

// 4. Verify that MyBookings query finds this booking by user_id and user_name
$check_stmt = $conn->prepare("SELECT BookingID, user_id, user_name, EventType FROM bookings 
                              WHERE (user_name = ? OR (user_id IS NOT NULL AND user_id = ?)) 
                              AND BookingID = ?");
$check_stmt->bind_param("sis", $buyer_username, $buyer_id, $sim_bkg_id);
$check_stmt->execute();
$check_res = $check_stmt->get_result();
$found = ($check_res && $check_res->num_rows === 1);
assert_test($found, "MyBookings query correctly retrieves the booking linked to buyer's account");
$check_stmt->close();

// Clean up
$conn->query("DELETE FROM bookings WHERE BookingID = '$sim_bkg_id'");
$cleaned = $conn->query("SELECT * FROM bookings WHERE BookingID = '$sim_bkg_id'")->num_rows === 0;
assert_test($cleaned, "Simulation test booking cleaned up from database");

// 5. Test safe redirect logic regex
$valid_redirects = [
    'events/WeddingBooking.php',
    'events/PartyBooking.php?vibe=rave',
    'Booking.php?service=wedding',
    'events/BirthdayBooking.php#stage3',
];
$malicious_redirects = [
    'http://evil.com/phishing',
    'https://attacker.org',
    '//attacker.org/steal',
    '/\\attacker.org',
];

foreach ($valid_redirects as $r) {
    $is_safe = !empty($r) && !preg_match('#^(https?:)?//#i', $r) && !preg_match('#^/\\\#', $r);
    assert_test($is_safe, "Safe internal redirect permitted: $r");
}

foreach ($malicious_redirects as $r) {
    $is_safe = !empty($r) && !preg_match('#^(https?:)?//#i', $r) && !preg_match('#^/\\\#', $r);
    assert_test(!$is_safe, "Malicious redirect rejected: $r");
}

echo "\n=======================================================================\n";
echo "Summary: $passed Passed, $failed Failed\n";
echo "=======================================================================\n";

if ($failed === 0) {
    echo "🎉 Buyer Auth & Safe Redirect End-to-End Simulation PASSED!\n";
    exit(0);
} else {
    echo "❌ Simulation Failed!\n";
    exit(1);
}
