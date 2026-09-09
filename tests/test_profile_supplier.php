<?php
include __DIR__ . '/../config/database.php';

echo "=== SUPPLIER PROFILE & SERVICES SYNC TEST ===" . PHP_EOL;

// 1. Create or get test user
$u_res = $conn->query("SELECT id FROM users WHERE username = 'test_supplier_auto'");
if ($u_res && $u_res->num_rows > 0) {
    $user_id = $u_res->fetch_assoc()['id'];
} else {
    $conn->query("INSERT INTO users (username, fullname, email, password, role) VALUES ('test_supplier_auto', 'Auto Tester', 'test_supp_auto@example.com', 'pwd123', 'supplier')");
    $user_id = $conn->insert_id;
}
echo "1. User ID: " . $user_id . PHP_EOL;

// 2. Simulate Profile POST save logic
$business_name = 'Starlight Wedding & Event Decor';
$category = 'Poruwa/Decorators';
$description = 'Premier decor and floral styling.';
$contact_phone = '+94 71 999 8888';
$location = 'Kandy, Central Province';
$submitted_services = [1, 5, 7];

// Upsert supplier
$chk = $conn->query("SELECT id FROM suppliers WHERE user_id = $user_id");
if ($chk && $chk->num_rows > 0) {
    $supp_id = $chk->fetch_assoc()['id'];
    $conn->query("UPDATE suppliers SET business_name = '$business_name', category = '$category', description = '$description', contact_phone = '$contact_phone', location = '$location' WHERE id = $supp_id");
} else {
    $conn->query("INSERT INTO suppliers (user_id, business_name, category, description, contact_phone, location) VALUES ($user_id, '$business_name', '$category', '$description', '$contact_phone', '$location')");
    $supp_id = $conn->insert_id;
}
echo "2. Supplier ID: " . $supp_id . PHP_EOL;

// Sync supplier_services
$conn->query("DELETE FROM supplier_services WHERE supplier_id = $supp_id");
foreach ($submitted_services as $svc_id) {
    $conn->query("INSERT INTO supplier_services (supplier_id, service_id) VALUES ($supp_id, $svc_id)");
}

// Verify count
$c = $conn->query("SELECT COUNT(*) as cnt FROM supplier_services WHERE supplier_id = $supp_id")->fetch_assoc()['cnt'];
echo "3. Initial Selected Services Count: " . $c . " (Expected: 3)" . PHP_EOL;
if ((int)$c !== 3) {
    die("FAILED: Expected 3 services, found $c\n");
}

// Test second sync with [1, 6]
$submitted_services2 = [1, 6];
$conn->query("DELETE FROM supplier_services WHERE supplier_id = $supp_id");
foreach ($submitted_services2 as $svc_id) {
    $conn->query("INSERT INTO supplier_services (supplier_id, service_id) VALUES ($supp_id, $svc_id)");
}
$c2 = $conn->query("SELECT COUNT(*) as cnt FROM supplier_services WHERE supplier_id = $supp_id")->fetch_assoc()['cnt'];
echo "4. Updated Selected Services Count: " . $c2 . " (Expected: 2)" . PHP_EOL;
if ((int)$c2 !== 2) {
    die("FAILED: Expected 2 services, found $c2\n");
}

// Verify service details join
$join_res = $conn->query("SELECT ss.service_id, s.service_name, et.type_name FROM supplier_services ss JOIN services s ON ss.service_id = s.service_id JOIN event_types et ON s.event_type_id = et.event_type_id WHERE ss.supplier_id = $supp_id");
echo "5. Stored Offerings:" . PHP_EOL;
while ($r = $join_res->fetch_assoc()) {
    echo "   - " . $r['service_name'] . " (" . $r['type_name'] . ")" . PHP_EOL;
}

// Teardown test user
$conn->query("DELETE FROM users WHERE id = $user_id"); // ON DELETE CASCADE deletes supplier and supplier_services
$post_del = $conn->query("SELECT COUNT(*) as cnt FROM supplier_services WHERE supplier_id = $supp_id")->fetch_assoc()['cnt'];
echo "6. Cascade Delete Verification: " . $post_del . " remaining (Expected: 0)" . PHP_EOL;

if ((int)$post_del === 0) {
    echo ">>> ALL TESTS PASSED! <<<" . PHP_EOL;
} else {
    echo "FAILED: Cascade delete did not remove supplier_services." . PHP_EOL;
}
?>
