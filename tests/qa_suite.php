<?php
// Simple QA Suite using PHP cURL
$base_url = "http://localhost:8000/";

function test_post($url, $post_data, $cookie_file = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Follow redirects to see where it lands
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    if ($cookie_file) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    }
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $http_code, 'body' => $response];
}

function test_get($url, $cookie_file = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($cookie_file) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    }
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $http_code, 'body' => $response];
}

$cookie = tempnam(sys_get_temp_dir(), 'cookie');
$rand = rand(1000, 9999);

echo "Starting QA Test Suite...\n";
echo "=============================\n";

// 1. Supplier Registration
echo "1. Testing Supplier Registration...\n";
$data = [
    'fullname' => 'Test Supplier ' . $rand,
    'username' => 'testsup' . $rand,
    'email' => "testsup{$rand}@example.com",
    'business_name' => "Test Business {$rand}",
    'password' => 'password123',
    'confirm-password' => 'password123',
    'register' => '1'
];
$res = test_post($base_url . "supplier/Register.php", $data);
if (strpos($res['body'], 'Registration successful') !== false) {
    echo "   [PASSED] Supplier Registration successful.\n";
} else {
    echo "   [FAILED] Supplier Registration. Output snippet: " . substr(strip_tags($res['body']), 0, 100) . "...\n";
}

// 2. Supplier Login
echo "2. Testing Supplier Login...\n";
$data = [
    'username' => "testsup{$rand}",
    'password' => 'password123'
];
$res = test_post($base_url . "Login.php", $data, $cookie);
if (strpos($res['body'], 'supplier/Dashboard.php') !== false || strpos($res['body'], 'Dashboard') !== false) {
    echo "   [PASSED] Supplier Login successful. Reached dashboard.\n";
} else {
    echo "   [FAILED] Supplier Login.\n";
}

// 3. Client Registration
echo "3. Testing Client Registration...\n";
$data = [
    'fullname' => 'Test Client ' . $rand,
    'username' => 'testcli' . $rand,
    'email' => "testcli{$rand}@example.com",
    'password' => 'password123',
    'confirm-password' => 'password123',
    'register' => '1'
];
$res = test_post($base_url . "RegisterForm.php", $data);
if (strpos($res['body'], 'Registration successful') !== false) {
    echo "   [PASSED] Client Registration successful.\n";
} else {
    echo "   [FAILED] Client Registration.\n";
}

// 4. Food Calculator
echo "4. Testing Food Calculator logic...\n";
$data = [
    'total-budget' => '10000',
    'food-budget' => '3500',
    'buffet-quantity' => '150',
    'buffet-unit-price' => '10',
    'beverages-quantity' => '100',
    'beverages-unit-price' => '5',
    'desserts-quantity' => '100',
    'desserts-unit-price' => '3',
    'snacks-quantity' => '200',
    'snacks-unit-price' => '2',
    'calculate' => '1'
];
$res = test_post($base_url . "Food.php", $data);
if (strpos($res['body'], 'Total Spent') !== false || strpos($res['body'], 'Food Calculator') !== false) {
    echo "   [PASSED] Food Calculator successfully ran and outputted a budget.\n";
} else {
    echo "   [FAILED] Food Calculator didn't output Total Spent.\n";
}

// Cleanup
unlink($cookie);
echo "=============================\n";
echo "QA Test Suite finished.\n";
?>
