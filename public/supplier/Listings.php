<?php
session_start();
include __DIR__ . '/../../config/database.php';

// If logged in as admin, redirect to Admin Supplier Listings Console
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header("Location: ../admin/SupplierListings.php");
    exit();
}

// Ensure user is logged in and is a supplier
if (!isset($_SESSION['login_user']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'supplier') {
    header("Location: ../Login.php");
    exit();
}

$username = $_SESSION['login_user'];
$error = '';
$success = '';

// 1. Fetch user and supplier record
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$u_res = $stmt->get_result();
if ($u_res->num_rows === 0) {
    die("User not found.");
}
$user = $u_res->fetch_assoc();
$user_id = (int)$user['id'];
$stmt->close();

$stmt = $conn->prepare("SELECT id, business_name FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$s_res = $stmt->get_result();
if ($s_res->num_rows === 0) {
    $supplier_id = 0;
    $business_name = 'My Business';
} else {
    $supp = $s_res->fetch_assoc();
    $supplier_id = (int)$supp['id'];
    $business_name = $supp['business_name'];
}
$stmt->close();

// 2. Fetch services configured by this supplier
$supplier_services = [];
if ($supplier_id > 0) {
    $svc_query = "SELECT s.service_id, s.service_name, et.type_name 
                  FROM supplier_services ss 
                  JOIN services s ON ss.service_id = s.service_id 
                  JOIN event_types et ON s.event_type_id = et.event_type_id 
                  WHERE ss.supplier_id = ? 
                  ORDER BY et.type_name, s.service_name";
    $stmt = $conn->prepare($svc_query);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $svc_res = $stmt->get_result();
    while ($row = $svc_res->fetch_assoc()) {
        $supplier_services[] = $row;
    }
    $stmt->close();
}

// 3. Handle Form Actions (Create, Update, Delete, Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $supplier_id > 0) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_listing' || $action === 'update_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        $service_id = (int)($_POST['service_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $price_type = $_POST['price_type'] ?? 'total_package';
        $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
        $image_url = trim($_POST['image_url'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        // Validate allowed price types
        $allowed_price_types = ['total_package', 'per_person', 'per_hour', 'per_day'];
        if (!in_array($price_type, $allowed_price_types)) {
            $price_type = 'total_package';
        }

        if (empty($title)) {
            $error = "Listing title is required.";
        } elseif ($service_id <= 0) {
            $error = "Please select a valid service category for this listing.";
        } elseif ($price < 0) {
            $error = "Price cannot be negative.";
        } else {
            if ($action === 'create_listing') {
                $ins = $conn->prepare("INSERT INTO supplier_listings (supplier_id, service_id, title, description, price, price_type, capacity, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("iissssdss", $supplier_id, $service_id, $title, $description, $price, $price_type, $capacity, $image_url, $status);
                if ($ins->execute()) {
                    $success = "New listing '{$title}' created successfully!";
                } else {
                    $error = "Failed to create listing: " . $ins->error;
                }
                $ins->close();
            } else {
                // Update listing (ensuring it belongs to this supplier)
                $upd = $conn->prepare("UPDATE supplier_listings SET service_id = ?, title = ?, description = ?, price = ?, price_type = ?, capacity = ?, image_url = ?, status = ? WHERE listing_id = ? AND supplier_id = ?");
                $upd->bind_param("issssdssii", $service_id, $title, $description, $price, $price_type, $capacity, $image_url, $status, $listing_id, $supplier_id);
                if ($upd->execute()) {
                    $success = "Listing updated successfully!";
                } else {
                    $error = "Failed to update listing: " . $upd->error;
                }
                $upd->close();
            }
        }
    } elseif ($action === 'toggle_status') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        $toggle_stmt = $conn->prepare("UPDATE supplier_listings SET status = IF(status = 'active', 'inactive', 'active') WHERE listing_id = ? AND supplier_id = ?");
        $toggle_stmt->bind_param("ii", $listing_id, $supplier_id);
        if ($toggle_stmt->execute()) {
            $success = "Listing availability updated!";
        } else {
            $error = "Failed to update status.";
        }
        $toggle_stmt->close();
    } elseif ($action === 'delete_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        $del_stmt = $conn->prepare("DELETE FROM supplier_listings WHERE listing_id = ? AND supplier_id = ?");
        $del_stmt->bind_param("ii", $listing_id, $supplier_id);
        if ($del_stmt->execute()) {
            $success = "Listing removed successfully!";
        } else {
            $error = "Failed to delete listing.";
        }
        $del_stmt->close();
    }
}

// 4. Fetch all listings for this supplier
$listings = [];
if ($supplier_id > 0) {
    $list_query = "SELECT sl.*, s.service_name, et.type_name 
                   FROM supplier_listings sl 
                   JOIN services s ON sl.service_id = s.service_id 
                   JOIN event_types et ON s.event_type_id = et.event_type_id 
                   WHERE sl.supplier_id = ? 
                   ORDER BY sl.created_at DESC";
    $stmt = $conn->prepare($list_query);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $l_res = $stmt->get_result();
    while ($row = $l_res->fetch_assoc()) {
        $listings[] = $row;
    }
    $stmt->close();
}

$conn->close();

// Helper to format price unit labels
function formatPriceType($type) {
    switch ($type) {
        case 'per_person': return 'Per Person / Plate';
        case 'per_hour':   return 'Per Hour';
        case 'per_day':    return 'Per Day';
        case 'total_package':
        default:           return 'Flat Package';
    }
}

// Calculate summary stats
$total_listings = count($listings);
$active_listings = 0;
foreach ($listings as $l) {
    if ($l['status'] === 'active') $active_listings++;
}

include __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Listings & Inventory - EVENTFLARE</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: var(--bg-main);
            background-image: url('../assets/images/signin_bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.88);
            z-index: -1;
        }

        .listings-wrapper {
            max-width: 1140px;
            margin: 100px auto 70px;
            padding: 0 20px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Top Header Bar */
        .top-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-title h1 {
            font-size: 32px;
            font-weight: 800;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title p {
            color: #94a3b8;
            font-size: 15px;
            margin-top: 4px;
        }

        .btn-add-listing {
            background: var(--primary);
            color: white;
            border: none;
            padding: 13px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
            transition: all 0.25s ease;
        }

        .btn-add-listing:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.5);
        }

        /* Stats Cards Row */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 22px 25px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: var(--shadow-premium);
            backdrop-filter: blur(10px);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(139, 92, 246, 0.12);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        .stat-info h3 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.1;
        }

        .stat-info p {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        .msg-error { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .msg-success { background: #dcfce7; color: #16a34a; border: 1px solid #86efac; }

        /* Listings Cards Grid */
        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .listing-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-premium);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .listing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            border-color: rgba(139, 92, 246, 0.4);
        }

        .listing-image-wrap {
            height: 180px;
            background: linear-gradient(135deg, #1e1b4b, #4338ca);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .listing-image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .listing-fallback-icon {
            font-size: 54px;
            color: rgba(255, 255, 255, 0.3);
        }

        .listing-status-tag {
            position: absolute;
            top: 14px;
            right: 14px;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            backdrop-filter: blur(8px);
        }

        .status-active {
            background: rgba(22, 163, 74, 0.85);
            color: #ffffff;
        }

        .status-inactive {
            background: rgba(100, 116, 139, 0.85);
            color: #ffffff;
        }

        .listing-category-badge {
            position: absolute;
            bottom: 12px;
            left: 14px;
            background: rgba(15, 23, 42, 0.85);
            color: #c4b5fd;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .listing-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .listing-title {
            font-size: 19px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .listing-desc {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 18px;
            flex: 1;
        }

        .listing-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .price-badge {
            display: flex;
            flex-direction: column;
        }

        .price-amount {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
        }

        .price-unit {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
        }

        .capacity-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #475569;
            font-weight: 600;
            background: #f1f5f9;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .listing-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
        }

        .btn-action {
            flex: 1;
            padding: 9px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-action-edit {
            background: #f1f5f9;
            color: var(--text-heading);
            border-color: #cbd5e1;
        }
        .btn-action-edit:hover {
            background: #e2e8f0;
        }

        .btn-action-toggle {
            background: #f8fafc;
            color: #475569;
            border-color: #cbd5e1;
        }
        .btn-action-toggle:hover {
            background: #f1f5f9;
        }

        .btn-action-delete {
            flex: 0 0 38px;
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .btn-action-delete:hover {
            background: #fee2e2;
        }

        /* Empty State */
        .empty-listings-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 60px 30px;
            text-align: center;
            box-shadow: var(--shadow-premium);
        }

        .empty-icon {
            font-size: 64px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .empty-listings-card h3 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 10px;
        }

        .empty-listings-card p {
            color: var(--text-muted);
            max-width: 520px;
            margin: 0 auto 25px;
            line-height: 1.6;
        }

        /* Modal Styles */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(6px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal-card {
            background: #ffffff;
            border-radius: 24px;
            max-width: 650px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 35px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideUp 0.3s ease-out;
            position: relative;
        }

        @keyframes modalSlideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            font-size: 26px;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close-btn:hover {
            color: #dc2626;
        }

        .form-grid-modal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: var(--text-main);
            margin-bottom: 7px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-main);
            transition: border-color 0.25s ease;
        }

        .form-input:focus {
            border-color: var(--primary);
            outline: none;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        }

        textarea.form-input {
            min-height: 90px;
            resize: vertical;
        }

        .modal-footer {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-modal-cancel {
            background: #f1f5f9;
            color: var(--text-main);
            border: 1px solid #cbd5e1;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-modal-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 26px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .btn-modal-submit:hover {
            background: var(--primary-dark);
        }
    </style>
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body>
    <div class="listings-wrapper">
        <!-- Top Action Bar -->
        <div class="top-action-bar">
            <div class="page-title">
                <h1><i class='bx bx-package'></i> My Service Listings & Packages</h1>
                <p>Manage individual package offerings, pricing models, and event venue listings for <strong><?= htmlspecialchars($business_name); ?></strong></p>
            </div>
            <div>
                <button type="button" class="btn-add-listing" id="openCreateModalBtn">
                    <i class='bx bx-plus-circle'></i> + Add New Listing
                </button>
            </div>
        </div>

        <!-- Feedback Messages -->
        <?php if (!empty($error)): ?>
            <div class="message msg-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="message msg-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Stats Overview Row -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class='bx bx-store-alt'></i></div>
                <div class="stat-info">
                    <h3><?= $total_listings; ?></h3>
                    <p>Total Listings</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(34, 197, 94, 0.12); color: #16a34a;"><i class='bx bx-check-shield'></i></div>
                <div class="stat-info">
                    <h3><?= $active_listings; ?></h3>
                    <p>Active & Bookable</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.12); color: #2563eb;"><i class='bx bx-category-alt'></i></div>
                <div class="stat-info">
                    <h3><?= count($supplier_services); ?></h3>
                    <p>Services Covered</p>
                </div>
            </div>
        </div>

        <!-- Listings Grid or Empty State -->
        <?php if (empty($listings)): ?>
            <div class="empty-listings-card">
                <i class='bx bx-box empty-icon'></i>
                <h3>No Packages or Listings Added Yet</h3>
                <p>You haven't published any specific packages or offerings yet. Add your first listing to let clients view your packages, pricing, and guest capacity!</p>
                <button type="button" class="btn-add-listing" onclick="document.getElementById('openCreateModalBtn').click();">
                    <i class='bx bx-plus-circle'></i> Add Your First Listing
                </button>
            </div>
        <?php else: ?>
            <div class="listings-grid">
                <?php foreach ($listings as $item): ?>
                    <div class="listing-card">
                        <div class="listing-image-wrap">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="<?= htmlspecialchars($item['title']); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <i class='bx bx-image listing-fallback-icon' style="display:none;"></i>
                            <?php else: ?>
                                <i class='bx bx-image listing-fallback-icon'></i>
                            <?php endif; ?>

                            <span class="listing-status-tag <?= $item['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?= $item['status'] === 'active' ? '● Active' : '● Inactive'; ?>
                            </span>

                            <span class="listing-category-badge">
                                <?= htmlspecialchars($item['type_name']); ?> • <?= htmlspecialchars($item['service_name']); ?>
                            </span>
                        </div>

                        <div class="listing-body">
                            <h3 class="listing-title"><?= htmlspecialchars($item['title']); ?></h3>
                            <p class="listing-desc"><?= htmlspecialchars($item['description'] ?: 'No description provided.'); ?></p>

                            <div class="listing-meta-row">
                                <div class="price-badge">
                                    <span class="price-amount">LKR <?= number_format($item['price'], 2); ?></span>
                                    <span class="price-unit"><?= formatPriceType($item['price_type']); ?></span>
                                </div>
                                <?php if (!empty($item['capacity'])): ?>
                                    <div class="capacity-badge" title="Maximum guest capacity">
                                        <i class='bx bx-group'></i> <?= (int)$item['capacity']; ?> Guests
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="listing-actions">
                                <!-- Edit Button -->
                                <button type="button" 
                                        class="btn-action btn-action-edit edit-listing-btn"
                                        data-listing-id="<?= $item['listing_id']; ?>"
                                        data-service-id="<?= $item['service_id']; ?>"
                                        data-title="<?= htmlspecialchars($item['title']); ?>"
                                        data-description="<?= htmlspecialchars($item['description'] ?? ''); ?>"
                                        data-price="<?= $item['price']; ?>"
                                        data-price-type="<?= $item['price_type']; ?>"
                                        data-capacity="<?= $item['capacity'] ?? ''; ?>"
                                        data-image-url="<?= htmlspecialchars($item['image_url'] ?? ''); ?>"
                                        data-status="<?= $item['status']; ?>">
                                    <i class='bx bx-edit'></i> Edit
                                </button>

                                <!-- Toggle Status Form -->
                                <form method="POST" action="Listings.php" style="flex: 1; display: flex;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="listing_id" value="<?= $item['listing_id']; ?>">
                                    <button type="submit" class="btn-action btn-action-toggle" style="width: 100%;">
                                        <i class='bx bx-power-off'></i> <?= $item['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>

                                <!-- Delete Form -->
                                <form method="POST" action="Listings.php" onsubmit="return confirm('Are you sure you want to permanently delete this listing?');">
                                    <input type="hidden" name="action" value="delete_listing">
                                    <input type="hidden" name="listing_id" value="<?= $item['listing_id']; ?>">
                                    <button type="submit" class="btn-action btn-action-delete" title="Delete Listing">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Back to Dashboard Footer Link -->
        <div style="text-align: center; margin-top: 35px;">
            <a href="Dashboard.php" style="color: #cbd5e1; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i class='bx bx-arrow-back'></i> Back to Supplier Dashboard
            </a>
        </div>
    </div>

    <!-- Create / Edit Listing Modal -->
    <div class="modal-backdrop" id="listingModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2 id="modalTitle"><i class='bx bx-plus-circle'></i> Add New Listing</h2>
                <button type="button" class="modal-close-btn" id="closeModalBtn">&times;</button>
            </div>

            <form method="POST" action="Listings.php" id="listingForm">
                <input type="hidden" name="action" id="formAction" value="create_listing">
                <input type="hidden" name="listing_id" id="listingIdInput" value="0">

                <div class="form-grid-modal">
                    <!-- Service Category Dropdown -->
                    <div class="form-group full-width">
                        <label class="form-label">Service Category *</label>
                        <select name="service_id" id="serviceIdSelect" class="form-input" required>
                            <?php if (empty($supplier_services)): ?>
                                <option value="" disabled selected>-- No services selected in your profile yet --</option>
                            <?php else: ?>
                                <option value="" disabled selected>-- Select which service this package belongs to --</option>
                                <?php foreach ($supplier_services as $svc): ?>
                                    <option value="<?= $svc['service_id']; ?>">
                                        <?= htmlspecialchars($svc['type_name']); ?> &rarr; <?= htmlspecialchars($svc['service_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($supplier_services)): ?>
                            <div style="margin-top: 6px; font-size: 13px; color: #dc2626;">
                                <i class='bx bx-error-circle'></i> You haven't added any services to your profile yet. 
                                <a href="Profile.php" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Select Supplied Services in Profile</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Package Title -->
                    <div class="form-group full-width">
                        <label class="form-label">Package or Listing Title *</label>
                        <input type="text" name="title" id="titleInput" class="form-input" placeholder="e.g. Royal Traditional Poruwa & Floral Decor" required>
                    </div>

                    <!-- Price -->
                    <div class="form-group">
                        <label class="form-label">Price (LKR) *</label>
                        <input type="number" step="0.01" min="0" name="price" id="priceInput" class="form-input" placeholder="e.g. 75000.00" required>
                    </div>

                    <!-- Pricing Model -->
                    <div class="form-group">
                        <label class="form-label">Pricing Model *</label>
                        <select name="price_type" id="priceTypeSelect" class="form-input" required>
                            <option value="total_package">Total Package (Flat Fee)</option>
                            <option value="per_person">Per Person / Plate</option>
                            <option value="per_day">Per Day (Venues / Halls)</option>
                            <option value="per_hour">Per Hour (DJs / Entertainment)</option>
                        </select>
                    </div>

                    <!-- Guest Capacity -->
                    <div class="form-group">
                        <label class="form-label">Guest Capacity (Optional)</label>
                        <input type="number" min="1" name="capacity" id="capacityInput" class="form-input" placeholder="e.g. 250">
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label class="form-label">Availability Status</label>
                        <select name="status" id="statusSelect" class="form-input">
                            <option value="active">Active (Visible to Clients)</option>
                            <option value="inactive">Inactive / Unavailable</option>
                        </select>
                    </div>

                    <!-- Image URL -->
                    <div class="form-group full-width">
                        <label class="form-label">Cover Image URL (Optional)</label>
                        <input type="url" name="image_url" id="imageUrlInput" class="form-input" placeholder="https://example.com/photos/package.jpg">
                    </div>

                    <!-- Description -->
                    <div class="form-group full-width">
                        <label class="form-label">Package Inclusions & Description</label>
                        <textarea name="description" id="descriptionInput" class="form-input" placeholder="Describe all items included in this package, preparation times, and special options..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="btn-modal-submit" id="submitModalBtn">Save Listing</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('listingModal');
            const openCreateModalBtn = document.getElementById('openCreateModalBtn');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const cancelModalBtn = document.getElementById('cancelModalBtn');
            const modalTitle = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            const listingIdInput = document.getElementById('listingIdInput');
            const serviceIdSelect = document.getElementById('serviceIdSelect');
            const titleInput = document.getElementById('titleInput');
            const priceInput = document.getElementById('priceInput');
            const priceTypeSelect = document.getElementById('priceTypeSelect');
            const capacityInput = document.getElementById('capacityInput');
            const statusSelect = document.getElementById('statusSelect');
            const imageUrlInput = document.getElementById('imageUrlInput');
            const descriptionInput = document.getElementById('descriptionInput');
            const submitModalBtn = document.getElementById('submitModalBtn');

            function openModalForCreate() {
                modalTitle.innerHTML = "<i class='bx bx-plus-circle'></i> Add New Listing";
                formAction.value = 'create_listing';
                listingIdInput.value = '0';
                serviceIdSelect.selectedIndex = 0;
                titleInput.value = '';
                priceInput.value = '';
                priceTypeSelect.value = 'total_package';
                capacityInput.value = '';
                statusSelect.value = 'active';
                imageUrlInput.value = '';
                descriptionInput.value = '';
                submitModalBtn.textContent = 'Create Listing';
                modal.classList.add('open');
            }

            function openModalForEdit(button) {
                modalTitle.innerHTML = "<i class='bx bx-edit'></i> Edit Listing";
                formAction.value = 'update_listing';
                listingIdInput.value = button.getAttribute('data-listing-id');
                serviceIdSelect.value = button.getAttribute('data-service-id');
                titleInput.value = button.getAttribute('data-title');
                priceInput.value = button.getAttribute('data-price');
                priceTypeSelect.value = button.getAttribute('data-price-type');
                capacityInput.value = button.getAttribute('data-capacity');
                statusSelect.value = button.getAttribute('data-status');
                imageUrlInput.value = button.getAttribute('data-image-url');
                descriptionInput.value = button.getAttribute('data-description');
                submitModalBtn.textContent = 'Save Changes';
                modal.classList.add('open');
            }

            function closeModal() {
                modal.classList.remove('open');
            }

            if (openCreateModalBtn) {
                openCreateModalBtn.addEventListener('click', openModalForCreate);
            }

            if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
            if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });

            document.querySelectorAll('.edit-listing-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    openModalForEdit(this);
                });
            });
        });
    </script>
</body>
</html>
