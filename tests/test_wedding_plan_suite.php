<?php
/**
 * Automated Test Suite for Wedding Planning & Previous Events Showcase
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/database.php';

echo "=======================================================\n";
echo " Running Wedding Planning & Previous Events Suite\n";
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

// 1. Check file existence & lint
assert_true(file_exists($rootDir . '/public/events/WeddingsSlids.php'), "public/events/WeddingsSlids.php exists");
$lint_output = shell_exec("php -l " . escapeshellarg($rootDir . '/public/events/WeddingsSlids.php'));
assert_true(strpos($lint_output, 'No syntax errors') !== false, "WeddingsSlids.php passes PHP linting");

// 2. Check Database Connectivity & Wedding Services
$wedding_services_res = $conn->query("SELECT s.* FROM services s 
                                      JOIN event_types et ON s.event_type_id = et.event_type_id 
                                      WHERE et.type_name = 'Weddings' OR et.event_type_id = 1 
                                      ORDER BY s.priority_rank ASC");
$services_count = $wedding_services_res ? $wedding_services_res->num_rows : 0;
assert_true($services_count >= 6, "Weddings catalog has >= 6 services mapped (Found: $services_count)");

// 3. Verify specific required services exist
$svc_names = [];
if ($wedding_services_res) {
    while ($r = $wedding_services_res->fetch_assoc()) {
        $svc_names[] = $r['service_name'];
    }
}
assert_true(in_array('Poruwa/Decorators', $svc_names), "Poruwa/Decorators service exists");
assert_true(in_array('Catering', $svc_names), "Catering service exists");
assert_true(in_array('Photography & Videography', $svc_names), "Photography & Videography service exists");
assert_true(in_array('Kandyan Dancers and Drummers', $svc_names), "Kandyan Dancers and Drummers service exists");
assert_true(in_array('Jayamangala Gatha Choir', $svc_names), "Jayamangala Gatha Choir service exists");
assert_true(in_array('Ashtaka Narrator', $svc_names), "Ashtaka Narrator service exists");

// 4. Check Supplier Listings for Weddings
$pkg_res = $conn->query("SELECT sl.*, sup.business_name, s.service_name 
                         FROM supplier_listings sl
                         JOIN suppliers sup ON sl.supplier_id = sup.id
                         JOIN services s ON sl.service_id = s.service_id
                         WHERE s.event_type_id = 1 AND sl.status = 'active'");
$pkg_count = $pkg_res ? $pkg_res->num_rows : 0;
assert_true($pkg_count >= 3, "Active supplier packages exist for Weddings (Found: $pkg_count)");

// 5. Verify Image Assets Exist
$required_images = [
    '/public/assets/images/W1.jpg',
    '/public/assets/images/W2.jpg',
    '/public/assets/images/W3.jpg',
    '/public/assets/images/W4.jpg',
    '/public/assets/images/W5.jpg',
    '/public/assets/images/look-from-white-chairs-arranged-wedding-ceremony.jpg',
    '/public/assets/images/wedding-couple-best-friends-are-drinking-champagne-celebrating-park-wedding-day.jpg'
];
$images_ok = true;
foreach ($required_images as $img) {
    if (!file_exists($rootDir . $img)) {
        $images_ok = false;
        echo "    Missing image: $img\n";
    }
}
assert_true($images_ok, "All showcase wedding gallery images exist on disk");

// 6. Verify Booking.php Pre-Selection GET Handling
$booking_code = file_get_contents($rootDir . '/public/Booking.php');
assert_true(strpos($booking_code, '$pre_event') !== false, "Booking.php reads pre_event from GET");
assert_true(strpos($booking_code, '$pre_venue') !== false, "Booking.php reads pre_venue from GET");
assert_true(strpos($booking_code, '$pre_guests') !== false, "Booking.php reads pre_guests from GET");
assert_true(strpos($booking_code, '$pre_details') !== false, "Booking.php reads pre_details/package from GET");

// 7. Verify Navigation Links from Home.php and ChooseEvent.php
$home_code = file_get_contents($rootDir . '/public/Home.php');
assert_true(strpos($home_code, 'events/WeddingsSlids.php') !== false, "Home.php links to events/WeddingsSlids.php");

$choose_code = file_get_contents($rootDir . '/public/ChooseEvent.php');
assert_true(strpos($choose_code, 'events/WeddingsSlids.php') !== false, "ChooseEvent.php links to events/WeddingsSlids.php");

// 8. Verify weddings page content structure
$wedding_page_content = file_get_contents($rootDir . '/public/events/WeddingsSlids.php');
assert_true(strpos($wedding_page_content, 'Previous Weddings Showcase') !== false, "Previous Weddings Showcase section present");
assert_true(strpos($wedding_page_content, 'dossierModal') !== false, "Wedding Dossier interactive modal present");
assert_true(strpos($wedding_page_content, 'filter-btn') !== false, "Category filter buttons present");
assert_true(strpos($wedding_page_content, 'Food.php') !== false, "Catering estimator links present");

echo "\n=======================================================\n";
echo "Summary: $passed Passed, $failed Failed\n";
echo "=======================================================\n";

if ($failed === 0) {
    echo "🎉 All Wedding Planning & Previous Events tests PASSED!\n";
    exit(0);
} else {
    echo "❌ Some tests failed!\n";
    exit(1);
}
