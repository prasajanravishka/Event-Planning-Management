<?php
include __DIR__ . '/../config/database.php';

echo "=== SUPPLIER LISTINGS CRUD TEST ===" . PHP_EOL;

// 1. Setup test user and supplier
$conn->query("INSERT INTO users (username, fullname, email, password, role) VALUES ('test_listing_user', 'Listings Tester', 'test_listing@example.com', 'pass123', 'supplier')");
$user_id = $conn->insert_id;
echo "1. Created Test User ID: " . $user_id . PHP_EOL;

$conn->query("INSERT INTO suppliers (user_id, business_name, category) VALUES ($user_id, 'Elite Decor Co.', 'Poruwa/Decorators')");
$supplier_id = $conn->insert_id;
echo "2. Created Test Supplier ID: " . $supplier_id . PHP_EOL;

// Associate service_id = 1 (Poruwa/Decorators)
$conn->query("INSERT INTO supplier_services (supplier_id, service_id) VALUES ($supplier_id, 1)");

// 3. Create Listing
$title = "Royal Heritage Poruwa Setup";
$desc = "Full wooden Poruwa with fresh lotus and white jasmine decorations.";
$price = 75000.00;
$price_type = "total_package";
$capacity = 250;
$image_url = "https://images.unsplash.com/photo-1519741497674-611481863552";
$status = "active";

$stmt = $conn->prepare("INSERT INTO supplier_listings (supplier_id, service_id, title, description, price, price_type, capacity, image_url, status) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssdss", $supplier_id, $title, $desc, $price, $price_type, $capacity, $image_url, $status);
$stmt->execute();
$listing_id = $conn->insert_id;
$stmt->close();
echo "3. Created Listing ID: " . $listing_id . PHP_EOL;

// Verify creation
$res = $conn->query("SELECT * FROM supplier_listings WHERE listing_id = $listing_id")->fetch_assoc();
if (!$res || $res['title'] !== $title || (float)$res['price'] !== 75000.00) {
    die("FAILED: Listing creation verification failed.\n");
}
echo "   [PASSED] Listing created with correct title and price." . PHP_EOL;

// 4. Update Listing
$new_price = 85000.00;
$new_capacity = 300;
$upd = $conn->prepare("UPDATE supplier_listings SET price = ?, capacity = ? WHERE listing_id = ? AND supplier_id = ?");
$upd->bind_param("diii", $new_price, $new_capacity, $listing_id, $supplier_id);
$upd->execute();
$upd->close();

$res_upd = $conn->query("SELECT * FROM supplier_listings WHERE listing_id = $listing_id")->fetch_assoc();
if ((float)$res_upd['price'] !== 85000.00 || (int)$res_upd['capacity'] !== 300) {
    die("FAILED: Listing update verification failed.\n");
}
echo "4. [PASSED] Listing updated price to 85000 and capacity to 300." . PHP_EOL;

// 5. Toggle Status
$toggle = $conn->prepare("UPDATE supplier_listings SET status = IF(status = 'active', 'inactive', 'active') WHERE listing_id = ? AND supplier_id = ?");
$toggle->bind_param("ii", $listing_id, $supplier_id);
$toggle->execute();
$toggle->close();

$res_tog = $conn->query("SELECT status FROM supplier_listings WHERE listing_id = $listing_id")->fetch_assoc();
if ($res_tog['status'] !== 'inactive') {
    die("FAILED: Status toggle failed. Expected 'inactive', got " . $res_tog['status'] . "\n");
}
echo "5. [PASSED] Listing status successfully toggled to 'inactive'." . PHP_EOL;

// 6. Delete Listing
$del = $conn->prepare("DELETE FROM supplier_listings WHERE listing_id = ? AND supplier_id = ?");
$del->bind_param("ii", $listing_id, $supplier_id);
$del->execute();
$del->close();

$res_del = $conn->query("SELECT COUNT(*) as cnt FROM supplier_listings WHERE listing_id = $listing_id")->fetch_assoc();
if ((int)$res_del['cnt'] !== 0) {
    die("FAILED: Listing deletion failed.\n");
}
echo "6. [PASSED] Listing successfully deleted." . PHP_EOL;

// 7. Cleanup User & Cascade check
$conn->query("DELETE FROM users WHERE id = $user_id");
echo "7. Cleaned up test user and supplier." . PHP_EOL;
echo ">>> ALL LISTINGS TESTS PASSED! <<<" . PHP_EOL;
?>
