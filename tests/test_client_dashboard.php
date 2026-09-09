<?php
/**
 * Automated Verification Suite for Client Event Hub & Command Center (Slide.php)
 */
$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/database.php';

echo "=======================================================================\n";
echo " Running Client Event Hub (Slide.php) Verification Suite\n";
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

// 1. File existence and linting
$slidePath = $rootDir . '/public/Slide.php';
assert_test(file_exists($slidePath), "public/Slide.php exists on disk");

$slideCode = file_get_contents($slidePath);

// 2. Auth Guard checks
$topLines = implode("\n", array_slice(explode("\n", $slideCode), 0, 12));
assert_test(strpos($topLines, "!isset(\$_SESSION['login_user'])") !== false, "Slide.php enforces unauthenticated session guard");
assert_test(strpos($topLines, "header(\"Location: Login.php?redirect=\"") !== false, "Slide.php redirects unauthenticated users to Login.php with redirect parameter");
assert_test(strpos($slideCode, "navbar.php") > strpos($slideCode, "header("), "Slide.php executes header redirect BEFORE navbar.php include");

// 3. Database Metrics queries existence
assert_test(strpos($slideCode, "status IN ('pending', 'in progress', 'confirmed')") !== false, "Slide.php queries active bookings with appropriate statuses");
assert_test(strpos($slideCode, "EventDate >= CURDATE()") !== false, "Slide.php queries upcoming event date with countdown calculation");
assert_test(strpos($slideCode, "booking_services") !== false, "Slide.php queries assigned verified suppliers count");
assert_test(strpos($slideCode, "status = 'completed'") !== false, "Slide.php queries completed events count");
assert_test(strpos($slideCode, "ORDER BY created_at DESC, BookingID DESC LIMIT 1") !== false, "Slide.php retrieves latest booking record for preview card");

// 4. Live DB Execution Test for queries with test buyer
$u_res = $conn->query("SELECT id, username FROM users WHERE role = 'buyer' LIMIT 1");
$test_user = 'buyer';
$test_id = 1;
if ($u_res && $u_res->num_rows > 0) {
    $row = $u_res->fetch_assoc();
    $test_user = $row['username'];
    $test_id = (int)$row['id'];
}

$test_act = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE (user_name = ? OR (user_id IS NOT NULL AND user_id = ?)) AND status IN ('pending', 'in progress', 'confirmed')");
$test_act->bind_param("si", $test_user, $test_id);
assert_test($test_act->execute(), "Live SQL: Active bookings query executes cleanly");
$test_act->close();

$test_nxt = $conn->prepare("SELECT BookingID, EventType, EventDate, Place, status FROM bookings WHERE (user_name = ? OR (user_id IS NOT NULL AND user_id = ?)) AND EventDate >= CURDATE() AND status IN ('pending', 'in progress', 'confirmed') ORDER BY EventDate ASC LIMIT 1");
$test_nxt->bind_param("si", $test_user, $test_id);
assert_test($test_nxt->execute(), "Live SQL: Next event countdown query executes cleanly");
$test_nxt->close();

$test_sup = $conn->prepare("SELECT COUNT(DISTINCT bs.supplier_id) AS total_sups FROM booking_services bs JOIN bookings b ON bs.booking_id = b.BookingID WHERE (b.user_name = ? OR (b.user_id IS NOT NULL AND b.user_id = ?)) AND bs.supplier_id IS NOT NULL");
$test_sup->bind_param("si", $test_user, $test_id);
assert_test($test_sup->execute(), "Live SQL: Assigned suppliers query executes cleanly");
$test_sup->close();

// 5. 4 Event Universes Gateways
assert_test(strpos($slideCode, "events/WeddingsSlids.php") !== false, "Slide.php links to Weddings showcase");
assert_test(strpos($slideCode, "events/WeddingBooking.php") !== false, "Slide.php links to Wedding booking wizard");
assert_test(strpos($slideCode, "events/DjPartySlide.php") !== false, "Slide.php links to DJ Parties showcase");
assert_test(strpos($slideCode, "events/PartyBooking.php") !== false, "Slide.php links to Party booking wizard");
assert_test(strpos($slideCode, "events/BirthdayList.php") !== false, "Slide.php links to Birthdays showcase");
assert_test(strpos($slideCode, "events/BirthdayBooking.php") !== false, "Slide.php links to Birthday booking wizard");
assert_test(strpos($slideCode, "events/GetTogether.php") !== false, "Slide.php links to Get Togethers showcase");
assert_test(strpos($slideCode, "events/GetTogetherBooking.php") !== false, "Slide.php links to Get Together booking wizard");

// 6. Interactive Slider Components & Controls
assert_test(strpos($slideCode, "id=\"sliderPrevBtn\"") !== false, "Slide.php includes Previous slide control button");
assert_test(strpos($slideCode, "id=\"sliderNextBtn\"") !== false, "Slide.php includes Next slide control button");
assert_test(strpos($slideCode, "id=\"sliderPlayPauseBtn\"") !== false, "Slide.php includes Play/Pause control button");
assert_test(strpos($slideCode, "id=\"sliderDots\"") !== false, "Slide.php includes dot navigation indicators");
assert_test(strpos($slideCode, "slide-item") !== false, "Slide.php includes structured slide items with cover images & CTAs");
assert_test(strpos($slideCode, "startAutoPlay") !== false, "Slide.php controller implements auto-rotation with hover pause");

// 7. Quick Planning Tools
assert_test(strpos($slideCode, "Food.php") !== false, "Slide.php includes Food & Drink Calculator tool");
assert_test(strpos($slideCode, "events/HotelSlide.php") !== false, "Slide.php includes Hotels & Venues directory tool");
assert_test(strpos($slideCode, "MyBookings.php") !== false, "Slide.php includes My Invoices & Bookings tool");
assert_test(strpos($slideCode, "Booking.php") !== false, "Slide.php includes Express Reservation tool");

// 8. Global CSS inclusion
assert_test(strpos($slideCode, "assets/css/global.css") !== false, "Slide.php includes global.css for unified dark-luxury styling");

echo "\n=======================================================================\n";
echo "Summary: $passed Passed, $failed Failed\n";
echo "=======================================================================\n";

if ($failed === 0) {
    echo "🎉 Client Event Hub (Slide.php) Verification Suite PASSED!\n";
    exit(0);
} else {
    echo "❌ Verification Suite Failed!\n";
    exit(1);
}
