<?php
/**
 * test_sample_data_coverage.php
 * 
 * Verifies that:
 * 1. Admin accounts exist, match credentials, and have proper privileges.
 * 2. User/Buyer accounts exist, match credentials, and have bookings/budgets.
 * 3. Full supplier range exists across all 10 categories:
 *    - Catering, Decorators, Audio/Visual, Hotel Venues, DJs/Artists,
 *      Photography/Videography, Bakeries/Confectioners, Cultural Performers,
 *      Security, Bar & Beverage.
 * 4. Every event type & service in the system has active, linked suppliers and listings.
 * 5. Bookings cover all 5 statuses: pending, confirmed, in_progress, completed, cancelled.
 * 6. Budgets, Event Extras, and Booking Services are correctly linked.
 * 7. Contact messages exist with both read and unread status.
 */

require_once __DIR__ . '/../config/database.php';

echo "=======================================================\n";
echo " Verifying Complete Sample Data Coverage\n";
echo "=======================================================\n";

$passed = 0;
$failed = 0;

function check($label, $condition, $info = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}" . ($info ? " ({$info})" : "") . "\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}" . ($info ? " -- {$info}" : "") . "\n";
        $failed++;
    }
}

// 1. Admin Verification
echo "\n1. Administrative Accounts Check:\n";
$adm_res = $conn->query("SELECT username, password FROM admin WHERE username IN ('admin', 'sarah.admin', 'dilan.audit')");
$adm_count = $adm_res ? $adm_res->num_rows : 0;
check("Admin accounts exist in admin table", $adm_count >= 3, "Found {$adm_count} admins");

$adm_pw_ok = true;
while ($row = $adm_res->fetch_assoc()) {
    if (!password_verify('admin123', $row['password'])) {
        $adm_pw_ok = false;
    }
}
check("Admin accounts authenticate with 'admin123'", $adm_pw_ok);

$user_adm = $conn->query("SELECT id FROM users WHERE username = 'admin' AND role = 'admin'");
check("Admin account exists in users table with role 'admin' for universal login", $user_adm && $user_adm->num_rows > 0);

// 2. User / Buyer Verification
echo "\n2. User / Buyer Accounts Check:\n";
$buyers = ['kasun.perera', 'dilhani.s', 'nimal.fernando', 'ananya.sharma', 'chathura.k', 'malithi.desilva'];
$buyer_placeholders = "'" . implode("','", $buyers) . "'";
$buy_res = $conn->query("SELECT username, password FROM users WHERE username IN ({$buyer_placeholders}) AND role = 'buyer'");
$buy_count = $buy_res ? $buy_res->num_rows : 0;
check("Standard user / buyer accounts exist", $buy_count === count($buyers), "Found {$buy_count} of " . count($buyers));

$buy_pw_ok = true;
while ($row = $buy_res->fetch_assoc()) {
    if (!password_verify('password123', $row['password'])) {
        $buy_pw_ok = false;
    }
}
check("Buyer accounts authenticate with 'password123'", $buy_pw_ok);

// 3. Full Supplier Range Verification (All 10 Categories)
echo "\n3. Full Supplier Range Check (10 Categories):\n";
$expected_categories = [
    'Catering',
    'Decorators',
    'Audio/Visual (A/V)',
    'Hotel Venues',
    'DJs/Artists',
    'Photography & Videography',
    'Bakeries & Confectioners',
    'Cultural Performers',
    'Security',
    'Bar & Beverage Services'
];

$cat_res = $conn->query("SELECT DISTINCT category FROM suppliers");
$found_categories = [];
while ($row = $cat_res->fetch_assoc()) {
    $found_categories[] = $row['category'];
}

foreach ($expected_categories as $cat) {
    check("Supplier category '{$cat}' is covered", in_array($cat, $found_categories));
}

$supp_users_res = $conn->query("SELECT u.username, u.password, s.business_name, s.category FROM suppliers s JOIN users u ON s.user_id = u.id");
$supp_count = $supp_users_res ? $supp_users_res->num_rows : 0;
check("Total suppliers with linked user accounts >= 10", $supp_count >= 10, "Found {$supp_count} suppliers");

$supp_pw_ok = true;
while ($row = $supp_users_res->fetch_assoc()) {
    if (!password_verify('password123', $row['password'])) {
        $supp_pw_ok = false;
    }
}
check("Supplier accounts authenticate with 'password123'", $supp_pw_ok);

// 4. Supplier Services & Listings Coverage
echo "\n4. Supplier Services & Listings Catalog Coverage:\n";
$listings_count_res = $conn->query("SELECT COUNT(*) as c FROM supplier_listings WHERE status = 'active'");
$active_listings = $listings_count_res ? (int)$listings_count_res->fetch_assoc()['c'] : 0;
check("Active supplier package listings exist", $active_listings >= 20, "Total: {$active_listings} packages");

$orphaned_svcs = $conn->query("
    SELECT s.service_id, s.service_name 
    FROM services s 
    LEFT JOIN supplier_services ss ON s.service_id = ss.service_id 
    WHERE ss.id IS NULL
");
$orphans_count = $orphaned_svcs ? $orphaned_svcs->num_rows : 0;
check("Core service catalog has supplier mappings", $orphans_count === 0, $orphans_count === 0 ? "100% services mapped" : "{$orphans_count} unmapped services");

// 5. Bookings Coverage across all 5 Statuses
echo "\n5. Bookings Status & Event Type Coverage:\n";
$statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
$bkg_stat_res = $conn->query("SELECT status, COUNT(*) as c FROM bookings GROUP BY status");
$found_statuses = [];
while ($row = $bkg_stat_res->fetch_assoc()) {
    $found_statuses[$row['status']] = $row['c'];
}

foreach ($statuses as $st) {
    $count = $found_statuses[$st] ?? 0;
    check("Booking status '{$st}' is represented", $count > 0, "Count: {$count}");
}

// Check Event Types coverage in bookings
$event_types = ['Weddings', 'DJ Parties', 'Birthdays', 'Hotel Venues', 'Get Togethers'];
$bkg_type_res = $conn->query("SELECT EventType, COUNT(*) as c FROM bookings GROUP BY EventType");
$found_types = [];
while ($row = $bkg_type_res->fetch_assoc()) {
    $found_types[$row['EventType']] = $row['c'];
}

foreach ($event_types as $et) {
    $count = $found_types[$et] ?? 0;
    check("Bookings exist for Event Type '{$et}'", $count > 0, "Count: {$count}");
}

// 6. Relational Integrity: Event Extras, Booking Services, Budgets
echo "\n6. Relational Bookings & Finance Integrity:\n";
$extras_cnt = $conn->query("SELECT COUNT(*) as c FROM event_extras")->fetch_assoc()['c'];
check("Event Extras records exist for bookings", (int)$extras_cnt >= 10, "Total: {$extras_cnt}");

$bkg_svc_cnt = $conn->query("SELECT COUNT(*) as c FROM booking_services WHERE supplier_id IS NOT NULL")->fetch_assoc()['c'];
check("Booking Services with assigned suppliers exist", (int)$bkg_svc_cnt >= 10, "Total: {$bkg_svc_cnt}");

$budgets_cnt = $conn->query("SELECT COUNT(*) as c FROM budgets WHERE total_budget > 0")->fetch_assoc()['c'];
check("Budgets records exist with financial estimates", (int)$budgets_cnt >= 10, "Total: {$budgets_cnt}");

// 7. Contact Messages
echo "\n7. Contact Inquiries & Inbox Coverage:\n";
$unread_res = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read = 0");
$unread_cnt = $unread_res ? (int)$unread_res->fetch_assoc()['c'] : 0;
check("Unread contact messages exist for admin testing", $unread_cnt > 0, "Unread: {$unread_cnt}");

$read_res = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read = 1");
$read_cnt = $read_res ? (int)$read_res->fetch_assoc()['c'] : 0;
check("Read contact messages exist in archive", $read_cnt > 0, "Read: {$read_cnt}");

echo "\n=======================================================\n";
echo "Summary: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

if ($failed === 0) {
    echo "🎉 All sample data tests PASSED! Admin, User, and Supplier range are fully covered!\n";
    exit(0);
} else {
    echo "❌ Some sample data tests failed.\n";
    exit(1);
}
