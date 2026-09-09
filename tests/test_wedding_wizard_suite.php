<?php
/**
 * Automated Test Suite for Dedicated Wedding Booking Wizard (Option A)
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/database.php';

echo "=======================================================\n";
echo " Running Dedicated Wedding Booking Wizard Test Suite\n";
echo "=======================================================\n\n";

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

// 1. File existence & linting
$wizardPath = $rootDir . '/public/events/WeddingBooking.php';
assert_true(file_exists($wizardPath), "public/events/WeddingBooking.php exists");
$lint_output = shell_exec("php -l " . escapeshellarg($wizardPath));
assert_true(strpos($lint_output, 'No syntax errors') !== false, "WeddingBooking.php passes PHP linting");

// 2. Query verification for available suppliers per service
$pkg_query = "SELECT sl.*, sup.business_name, s.service_name 
              FROM supplier_listings sl
              JOIN suppliers sup ON sl.supplier_id = sup.id
              JOIN services s ON sl.service_id = s.service_id
              WHERE s.event_type_id = 1 AND sl.status = 'active'";
$pkg_res = $conn->query($pkg_query);
$pkg_count = $pkg_res ? $pkg_res->num_rows : 0;
assert_true($pkg_count >= 3, "Supplier listings query returns active wedding packages (Found: $pkg_count)");

// 3. Multi-table Atomic Transaction Test
$test_booking_id = "BKG-TEST-WIZ-" . strtoupper(uniqid());
$test_user_name = "test_wizard_buyer";
$test_place = "The Grand Cinnamon Ballroom, Galle";
$test_guests = 200;
$test_date = date('Y-m-d', strtotime('+3 months'));
$test_day_night = "Night";
$test_food_tier = "Platinum Wedding Feast (3-Course Buffet)";
$test_notes = "Test Couple: Alex & Sam | Wizard Automated Test";

$conn->begin_transaction();
try {
    // A. Insert into bookings
    $b_stmt = $conn->prepare("INSERT INTO bookings (BookingID, user_id, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails, status) 
                              VALUES (?, NULL, ?, 'Weddings', ?, ?, ?, ?, ?, ?, 'pending')");
    $b_stmt->bind_param("sssissss", $test_booking_id, $test_user_name, $test_place, $test_guests, $test_date, $test_day_night, $test_food_tier, $test_notes);
    $b_stmt->execute();
    $b_stmt->close();

    // B. Insert into event_extras
    $extra_equip = "Ceremonial Stage, Ambient Sound & Audio-Visual Setup";
    $ee_stmt = $conn->prepare("INSERT INTO event_extras (booking_id, equipment, food_style) VALUES (?, ?, ?)");
    $ee_stmt->bind_param("sss", $test_booking_id, $extra_equip, $test_food_tier);
    $ee_stmt->execute();
    $ee_stmt->close();

    // C. Insert into booking_services (Decor, Catering, Photo)
    $bs_stmt = $conn->prepare("INSERT INTO booking_services (booking_id, service_id, supplier_id, listing_id, custom_notes, assigned_cost, status) 
                               VALUES (?, ?, ?, ?, ?, ?, 'confirmed')");
    
    // Fetch 3 real active listings from database
    $l_res = $conn->query("SELECT listing_id, supplier_id, service_id, price, price_type, title FROM supplier_listings WHERE service_id IN (1, 5, 6) AND status = 'active' LIMIT 3");
    $expected_sum = 0.0;
    while ($lr = $l_res->fetch_assoc()) {
        $cost = (float)$lr['price'];
        if ($lr['price_type'] === 'per_person') {
            $cost = $cost * $test_guests;
        }
        $expected_sum += $cost;
        $note = "Wizard Selection: " . $lr['title'];
        $bs_stmt->bind_param("siiisd", $test_booking_id, $lr['service_id'], $lr['supplier_id'], $lr['listing_id'], $note, $cost);
        $bs_stmt->execute();
    }

    $bs_stmt->close();
    $conn->commit();
    $db_ok = true;
} catch (Throwable $e) {
    $conn->rollback();
    $db_ok = false;
    echo "    [TRANSACTION ERROR]: " . $e->getMessage() . " on line " . $e->getLine() . "\n";
}

assert_true($db_ok, "Atomic multi-table booking transaction committed successfully");

// 4. Verify Record Retrieval Across All 3 Tables
$chk_b = $conn->query("SELECT * FROM bookings WHERE BookingID = '$test_booking_id'");
assert_true($chk_b && $chk_b->num_rows === 1, "Booking record verified in bookings table");

$chk_ee = $conn->query("SELECT * FROM event_extras WHERE booking_id = '$test_booking_id'");
assert_true($chk_ee && $chk_ee->num_rows === 1, "Extras record verified in event_extras table");

$chk_bs = $conn->query("SELECT * FROM booking_services WHERE booking_id = '$test_booking_id'");
assert_true($chk_bs && $chk_bs->num_rows === 3, "3 vendor service assignments verified in booking_services table");

// 5. Verify Total Sum Calculation
$sum_res = $conn->query("SELECT SUM(assigned_cost) as total_services_cost FROM booking_services WHERE booking_id = '$test_booking_id'");
$sum_row = $sum_res->fetch_assoc();
assert_true((float)$sum_row['total_services_cost'] === (float)$expected_sum, "Total service assignments sum matches exact line items (Rs. " . number_format($expected_sum, 2) . ")");

// 6. Clean Up Test Records
$conn->query("DELETE FROM bookings WHERE BookingID = '$test_booking_id'");
$clean_chk = $conn->query("SELECT * FROM bookings WHERE BookingID = '$test_booking_id'");
assert_true($clean_chk && $clean_chk->num_rows === 0, "Test booking record successfully cleaned up");

// 7. Verify Linking from WeddingsSlids.php & Booking.php
$slides_content = file_get_contents($rootDir . '/public/events/WeddingsSlids.php');
assert_true(strpos($slides_content, 'WeddingBooking.php') !== false, "WeddingsSlids.php connects to WeddingBooking.php");

$booking_content = file_get_contents($rootDir . '/public/Booking.php');
assert_true(strpos($booking_content, 'WeddingBooking.php') !== false, "Booking.php recommends WeddingBooking.php for weddings");

// 8. Verify Wizard UI Elements
$wizard_content = file_get_contents($wizardPath);
assert_true(strpos($wizard_content, 'stepper-header') !== false, "Progress stepper header present in wizard");
assert_true(strpos($wizard_content, 'service-picker-section') !== false, "Service picker sections present for multi-supplier selection");
assert_true(strpos($wizard_content, 'displayTotalCost') !== false, "Live budget estimator element present in wizard");
assert_true(strpos($wizard_content, 'compileReviewAndCost') !== false, "JavaScript live price calculation function present");

// 9. Verify Venue Name & City removal from Stage 1
assert_true(strpos($wizard_content, '<label class="form-label" for="place">Venue Name & City</label>') === false, "Venue Name & City visible input field removed from Stage 1");
assert_true(strpos($wizard_content, "Venue To Be Coordinated") !== false, "Fallback venue coordination logic active in backend");

echo "\n=======================================================\n";
echo "Summary: $passed Passed, $failed Failed\n";
echo "=======================================================\n";

if ($failed === 0) {
    echo "🎉 All Wedding Booking Wizard tests PASSED!\n";
    exit(0);
} else {
    echo "❌ Some tests failed!\n";
    exit(1);
}
