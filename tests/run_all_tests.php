<?php
/**
 * run_all_tests.php — Universal Test Runner
 * 
 * Runs all 5 automated test suites consecutively across any OS/environment.
 */

$test_files = [
    'Admin Console & Workflow Integration Suite' => __DIR__ . '/test_admin_suite.php',
    'Sample Data & Role Coverage Suite'          => __DIR__ . '/test_sample_data_coverage.php',
    'Wedding Planning & Showcase Suite'          => __DIR__ . '/test_wedding_plan_suite.php',
    'Supplier Package Listings CRUD Suite'       => __DIR__ . '/test_listings_crud.php',
    'Supplier Profile & Services Sync Suite'     => __DIR__ . '/test_profile_supplier.php'
];

echo "=======================================================\n";
echo "   EVENTFLARE Automated Test Suite Runner (All Tests)\n";
echo "=======================================================\n\n";

$all_passed = true;
$php_binary = PHP_BINARY ?: 'php';

foreach ($test_files as $name => $path) {
    echo "▶ Running: {$name}...\n";
    echo str_repeat('-', 55) . "\n";
    
    passthru(escapeshellcmd($php_binary) . ' ' . escapeshellarg($path), $exit_code);
    echo "\n";

    if ($exit_code !== 0) {
        $all_passed = false;
    }
}

echo "=======================================================\n";
if ($all_passed) {
    echo "🎉 ALL TEST SUITES PASSED SUCCESSFULLY! (100% Green)\n";
    echo "=======================================================\n";
    exit(0);
} else {
    echo "❌ ONE OR MORE TEST SUITES FAILED!\n";
    echo "=======================================================\n";
    exit(1);
}
