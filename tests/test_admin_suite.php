<?php
require_once __DIR__ . '/../config/database.php';

echo "Running Admin Modernization Integration Tests...\n";
echo "=================================================\n";

$passed = 0;
$failed = 0;

function assert_test($label, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

// 1. Admin Table Check
$res = $conn->query("SELECT id, username, fullname FROM admin LIMIT 1");
assert_test("Admin account exists in database", $res && $res->num_rows > 0);

// 2. bookings.status column
$res = $conn->query("SHOW COLUMNS FROM bookings LIKE 'status'");
assert_test("bookings table contains 'status' column", $res && $res->num_rows > 0);

// 3. contact_messages.is_read column
$res = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'is_read'");
assert_test("contact_messages table contains 'is_read' column", $res && $res->num_rows > 0);

// 4. Test updating booking status
$b_res = $conn->query("SELECT BookingID, status FROM bookings LIMIT 1");
if ($b_res && $b_row = $b_res->fetch_assoc()) {
    $bid = $b_row['BookingID'];
    $stmt = $conn->prepare("UPDATE bookings SET status = 'in_progress' WHERE BookingID = ?");
    $stmt->bind_param("s", $bid);
    $ok = $stmt->execute();
    $stmt->close();
    assert_test("Able to update booking status to in_progress", $ok);
} else {
    echo "  [INFO] No bookings found in DB to test status update.\n";
}

// 5. Test suppliers directory and listings count query
$s_res = $conn->query("SELECT s.id, s.business_name, COUNT(sl.listing_id) as cnt FROM suppliers s LEFT JOIN supplier_listings sl ON s.id = sl.supplier_id GROUP BY s.id");
assert_test("Supplier directory query with aggregated listings succeeds", $s_res !== false);

// 6. Test Budgets aggregate query
$bgt_res = $conn->query("SELECT SUM(total_budget) as tb, SUM(total_spent) as ts, SUM(variance) as vr FROM budgets");
assert_test("Budgets aggregate summation query succeeds", $bgt_res !== false);

// 7. Verify file existence
assert_test("includes/admin_auth.php exists", file_exists(__DIR__ . '/../includes/admin_auth.php'));
assert_test("public/admin/Suppliers.php exists", file_exists(__DIR__ . '/../public/admin/Suppliers.php'));
assert_test("public/admin/SupplierListings.php exists", file_exists(__DIR__ . '/../public/admin/SupplierListings.php'));
assert_test("public/admin/EventTypes.php exists", file_exists(__DIR__ . '/../public/admin/EventTypes.php'));
assert_test("public/admin/AdminStaff.php exists", file_exists(__DIR__ . '/../public/admin/AdminStaff.php'));
assert_test("public/admin/Budgets.php exists", file_exists(__DIR__ . '/../public/admin/Budgets.php'));
assert_test("public/admin/Ratings.php exists", file_exists(__DIR__ . '/../public/admin/Ratings.php'));
assert_test("public/MyBookings.php exists", file_exists(__DIR__ . '/../public/MyBookings.php'));

// 8. Test Add & Edit Supplier Workflow
$rand = rand(10000, 99999);
$test_user = "testvendor_{$rand}";
$test_email = "vendor_{$rand}@example.com";
$test_business = "Grand Events {$rand}";

// Insert test supplier user
$p_hash = password_hash('password123', PASSWORD_DEFAULT);
$role = 'supplier';
$stmt = $conn->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES (?, 'Test Vendor', ?, ?, ?)");
$stmt->bind_param("ssss", $test_user, $test_email, $p_hash, $role);
$user_ok = $stmt->execute();
$new_uid = (int)$conn->insert_id;
$stmt->close();

assert_test("Admin can create supplier user account", $user_ok);

// Insert test supplier profile
$stmt = $conn->prepare("INSERT INTO suppliers (user_id, business_name, category, location, contact_phone) VALUES (?, ?, 'Catering', 'Colombo', '+94 77 123 4567')");
$stmt->bind_param("is", $new_uid, $test_business);
$supp_ok = $stmt->execute();
$new_sid = (int)$conn->insert_id;
$stmt->close();

assert_test("Admin can create supplier profile", $supp_ok);

// Edit test supplier profile
$updated_business = "Royal Grand Events {$rand}";
$upd_stmt = $conn->prepare("UPDATE suppliers SET business_name = ?, location = 'Kandy' WHERE id = ?");
$upd_stmt->bind_param("si", $updated_business, $new_sid);
$edit_ok = $upd_stmt->execute();
$upd_stmt->close();

assert_test("Admin can edit supplier details", $edit_ok);

// 9. Test Add, Toggle, and Delete Listing for Supplier
$svc_res = $conn->query("SELECT service_id FROM services LIMIT 1");
$svc_id = ($svc_res && $row = $svc_res->fetch_assoc()) ? (int)$row['service_id'] : 1;

$l_stmt = $conn->prepare("INSERT INTO supplier_listings (supplier_id, service_id, title, price, price_type, status) VALUES (?, ?, 'VIP Package {$rand}', 50000.00, 'total_package', 'active')");
$l_stmt->bind_param("ii", $new_sid, $svc_id);
$l_ok = $l_stmt->execute();
$new_lid = (int)$conn->insert_id;
$l_stmt->close();

assert_test("Admin can create listing for supplier", $l_ok);

// Toggle listing status
$t_stmt = $conn->prepare("UPDATE supplier_listings SET status = IF(status = 'active', 'inactive', 'active') WHERE listing_id = ?");
$t_stmt->bind_param("i", $new_lid);
$t_ok = $t_stmt->execute();
$t_stmt->close();

assert_test("Admin can toggle listing status", $t_ok);

// 10. Test Supplier Dashboard Boundary (Verifying file code redirects admins)
$dash_code = file_get_contents(__DIR__ . '/../public/supplier/Dashboard.php');
assert_test("Supplier Dashboard redirects admin to Admin Console", strpos($dash_code, "header(\"Location: ../admin/Suppliers.php\");") !== false);

// Cleanup test records
$conn->query("DELETE FROM supplier_listings WHERE listing_id = {$new_lid}");
$conn->query("DELETE FROM suppliers WHERE id = {$new_sid}");
$conn->query("DELETE FROM users WHERE id = {$new_uid}");

// 11. Test Event Types & Services Workflow
$test_et_name = "Corporate Gala {$rand}";
$et_stmt = $conn->prepare("INSERT INTO event_types (type_name, description, is_active) VALUES (?, 'Test event category', 1)");
$et_stmt->bind_param("s", $test_et_name);
$et_ok = $et_stmt->execute();
$new_et_id = (int)$conn->insert_id;
$et_stmt->close();

assert_test("Admin can create new Event Type", $et_ok);

// Toggle event type active status
$et_tgl = $conn->prepare("UPDATE event_types SET is_active = IF(is_active = 1, 0, 1) WHERE event_type_id = ?");
$et_tgl->bind_param("i", $new_et_id);
$tgl_ok = $et_tgl->execute();
$et_tgl->close();

assert_test("Admin can toggle Event Type status", $tgl_ok);

// Add nested service to event type
$svc_stmt = $conn->prepare("INSERT INTO services (event_type_id, service_name, description, is_required, typical_capacity) VALUES (?, 'Lighting & Rigging', 'Stage spotlights', 1, 500)");
$svc_stmt->bind_param("i", $new_et_id);
$svc_ok = $svc_stmt->execute();
$new_s_id = (int)$conn->insert_id;
$svc_stmt->close();

assert_test("Admin can add sub-service under Event Type", $svc_ok);

// Clean up event type & cascading services
$conn->query("DELETE FROM services WHERE service_id = {$new_s_id}");
$del_et = $conn->query("DELETE FROM event_types WHERE event_type_id = {$new_et_id}");
assert_test("Admin can delete test Event Type", $del_et !== false);

// 12. Test Reservation Dossier Multi-Table Query
$dossier_test_sql = "SELECT b.*, 
                            u.fullname as client_fullname, u.email as client_email,
                            ee.equipment, ee.food_style,
                            bg.total_budget, bg.food_budget, bg.total_spent, bg.remaining_budget, bg.variance
                     FROM bookings b
                     LEFT JOIN users u ON b.user_name = u.username
                     LEFT JOIN event_extras ee ON b.BookingID = ee.booking_id
                     LEFT JOIN budgets bg ON b.BookingID = bg.booking_id
                     LIMIT 5";
$dossier_res = $conn->query($dossier_test_sql);
assert_test("Reservation Dossier multi-table join query syntax succeeds", $dossier_res !== false);

// 13. Test booking_services table structure
$bs_table_check = $conn->query("SHOW TABLES LIKE 'booking_services'");
assert_test("booking_services table exists in database", $bs_table_check && $bs_table_check->num_rows > 0);

// 14. Test Event Services & Supplier Breakdown Workflow (Self-contained test)
$t_bid = 'BKG-TEST-' . rand(10000, 99999);
$conn->query("INSERT INTO bookings (BookingID, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails, status) 
              VALUES ('{$t_bid}', 'admin', 'Weddings', 'Test Venue', 100, '2026-12-01', 'Day', 'Buffet', 'Test details', 'confirmed')");

$sample_svc_res = $conn->query("SELECT service_id FROM services LIMIT 1");
$sample_svc = ($sample_svc_res && $row = $sample_svc_res->fetch_assoc()) ? (int)$row['service_id'] : 1;

// Create temporary supplier for booking service test
$t_uid = 0;
$conn->query("INSERT INTO users (username, fullname, email, password, role) VALUES ('test_sup_bs_{$rand}', 'Test Sup BS', 'test_sup_bs_{$rand}@example.com', '{$p_hash}', 'supplier')");
$t_uid = (int)$conn->insert_id;
$conn->query("INSERT INTO suppliers (user_id, business_name, category) VALUES ({$t_uid}, 'Test Vendor BS', 'Catering')");
$t_sid = (int)$conn->insert_id;

$ins_bs = $conn->prepare("INSERT INTO booking_services (booking_id, service_id, supplier_id, custom_notes, assigned_cost, status) VALUES (?, ?, ?, 'Test automated assignment', 15000.00, 'confirmed')");
$ins_bs->bind_param("sii", $t_bid, $sample_svc, $t_sid);
$ins_ok = $ins_bs->execute();
$new_bs_id = (int)$conn->insert_id;
$ins_bs->close();
assert_test("Admin can assign supplier to a booking service", $ins_ok);

// Verify multi-table breakdown query on the test booking
$t_breakdown_sql = "SELECT bs.booking_id, s.service_name, sup.business_name, bs.assigned_cost
                    FROM booking_services bs
                    JOIN services s ON bs.service_id = s.service_id
                    JOIN suppliers sup ON bs.supplier_id = sup.id
                    WHERE bs.booking_id = '{$t_bid}'";
$t_res = $conn->query($t_breakdown_sql);
assert_test("Booking services relational query retrieves assigned vendor", $t_res && $t_res->num_rows > 0);

// 15. Test Removing Service Supplier assignment
$del_bs = $conn->prepare("DELETE FROM booking_services WHERE id = ?");
$del_bs->bind_param("i", $new_bs_id);
$del_ok = $del_bs->execute();
$del_bs->close();
assert_test("Admin can remove supplier assignment from a booking service", $del_ok);

// Clean up temporary test booking & temporary supplier
$conn->query("DELETE FROM bookings WHERE BookingID = '{$t_bid}'");
$conn->query("DELETE FROM suppliers WHERE id = {$t_sid}");
$conn->query("DELETE FROM users WHERE id = {$t_uid}");

// 16. Test Ratings & Reviews Architecture
$res = $conn->query("SHOW TABLES LIKE 'ratings'");
assert_test("ratings table exists in database", $res && $res->num_rows > 0);

// 17. Verify ratings table schema columns
$col_res = $conn->query("SHOW COLUMNS FROM ratings LIKE 'admin_status'");
assert_test("ratings table has 'admin_status' moderation column", $col_res && $col_res->num_rows > 0);

// 18. Test Customer Review Submission & Average Rating Query
$sample_cust = $conn->query("SELECT id FROM users WHERE role = 'buyer' LIMIT 1")->fetch_assoc();
$sample_supp = $conn->query("SELECT id FROM suppliers LIMIT 1")->fetch_assoc();
$sample_bkg = $conn->query("SELECT BookingID FROM bookings LIMIT 1")->fetch_assoc();

if ($sample_cust && $sample_supp && $sample_bkg) {
    $c_id = (int)$sample_cust['id'];
    $s_id = (int)$sample_supp['id'];
    $b_id = $sample_bkg['BookingID'];

    $ins_rev = $conn->prepare("
        INSERT INTO ratings (booking_id, user_id, supplier_id, rating, review_title, review_text, admin_status)
        VALUES (?, ?, ?, 5, 'Integration Test Review', 'Automated test content', 'approved')
        ON DUPLICATE KEY UPDATE rating = 5, review_title = 'Integration Test Review', review_text = 'Automated test content'
    ");
    $ins_rev->bind_param("sii", $b_id, $c_id, $s_id);
    $ins_ok = $ins_rev->execute();
    $ins_rev->close();
    assert_test("Customer can submit verified rating and review", $ins_ok);

    // 19. Test supplier rating aggregation query
    $agg_res = $conn->query("SELECT AVG(rating) as avg_score, COUNT(*) as cnt FROM ratings WHERE supplier_id = {$s_id} AND admin_status = 'approved'");
    $agg_row = $agg_res ? $agg_res->fetch_assoc() : null;
    assert_test("Supplier average rating aggregation query succeeds", $agg_row && (float)$agg_row['avg_score'] > 0);

    // 20. Test Admin Moderation Toggle (Approved -> Hidden)
    $upd_rev = $conn->prepare("UPDATE ratings SET admin_status = 'hidden' WHERE booking_id = ? AND user_id = ? AND supplier_id = ?");
    $upd_rev->bind_param("sii", $b_id, $c_id, $s_id);
    $upd_ok = $upd_rev->execute();
    $upd_rev->close();
    assert_test("Admin can moderate and hide a customer review", $upd_ok);

    // 21. Clean up test rating
    $del_rev = $conn->prepare("DELETE FROM ratings WHERE booking_id = ? AND user_id = ? AND supplier_id = ?");
    $del_rev->bind_param("sii", $b_id, $c_id, $s_id);
    $del_ok = $del_rev->execute();
    $del_rev->close();
    assert_test("Admin can delete a review record", $del_ok);
}

echo "=================================================\n";
echo "Test Results: {$passed} Passed, {$failed} Failed.\n";
if ($failed === 0) {
    echo "All integration tests passed successfully!\n";
}
exit($failed > 0 ? 1 : 0);

