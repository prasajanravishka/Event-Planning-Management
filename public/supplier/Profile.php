<?php
session_start();
include __DIR__ . '/../../config/database.php';

// If logged in as admin, redirect to Admin Console (Admins edit suppliers via Admin Panel)
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header("Location: ../admin/Suppliers.php");
    exit();
}

// Ensure user is logged in and is a supplier
if (!isset($_SESSION['login_user']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'supplier') {
    header("location: ../Login.php");
    exit();
}

$username = $_SESSION['login_user'];
$error = '';
$success = '';

// Fetch the user_id for the logged-in supplier
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user_id = (int)$user['id'];
} else {
    die("User not found.");
}
$stmt->close();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_profile'])) {
    $business_name = trim($_POST['business_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $location = trim($_POST['location'] ?? '');

    // Array of selected service IDs
    $submitted_services = isset($_POST['services']) && is_array($_POST['services'])
        ? array_filter(array_map('intval', $_POST['services']))
        : [];

    if (empty($business_name)) {
        $error = "Business Name is required.";
    } else {
        // If category is not set, derive from first selected service
        if (empty($category) && !empty($submitted_services)) {
            $first_id = (int)$submitted_services[0];
            $cat_lookup = $conn->prepare("SELECT service_name FROM services WHERE service_id = ?");
            $cat_lookup->bind_param("i", $first_id);
            $cat_lookup->execute();
            $cat_res = $cat_lookup->get_result();
            if ($cat_res && $crow = $cat_res->fetch_assoc()) {
                $category = $crow['service_name'];
            }
            $cat_lookup->close();
        }

        $conn->begin_transaction();
        try {
            // Check if profile exists
            $check_stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            $supplier_id = 0;
            if ($check_result->num_rows > 0) {
                $existing_supp = $check_result->fetch_assoc();
                $supplier_id = (int)$existing_supp['id'];

                // Update existing profile
                $update_stmt = $conn->prepare("UPDATE suppliers SET business_name=?, category=?, description=?, contact_phone=?, location=? WHERE user_id=?");
                $update_stmt->bind_param("sssssi", $business_name, $category, $description, $contact_phone, $location, $user_id);
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to update profile: " . $update_stmt->error);
                }
                $update_stmt->close();
            } else {
                // Insert new profile
                $insert_stmt = $conn->prepare("INSERT INTO suppliers (user_id, business_name, category, description, contact_phone, location) VALUES (?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("isssss", $user_id, $business_name, $category, $description, $contact_phone, $location);
                if (!$insert_stmt->execute()) {
                    throw new Exception("Failed to create profile: " . $insert_stmt->error);
                }
                $supplier_id = (int)$conn->insert_id;
                $insert_stmt->close();
            }
            $check_stmt->close();

            // Synchronize supplier_services junction table
            if ($supplier_id > 0) {
                $del_stmt = $conn->prepare("DELETE FROM supplier_services WHERE supplier_id = ?");
                $del_stmt->bind_param("i", $supplier_id);
                $del_stmt->execute();
                $del_stmt->close();

                if (!empty($submitted_services)) {
                    $ins_stmt = $conn->prepare("INSERT INTO supplier_services (supplier_id, service_id) VALUES (?, ?)");
                    foreach ($submitted_services as $svc_id) {
                        $ins_stmt->bind_param("ii", $supplier_id, $svc_id);
                        $ins_stmt->execute();
                    }
                    $ins_stmt->close();
                }
            }

            $conn->commit();
            $success = "Profile and supplied services updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error saving profile: " . $e->getMessage();
        }
    }
}

// Fetch existing profile data to pre-fill the form
$profile = [
    'id' => 0,
    'business_name' => '',
    'category' => '',
    'description' => '',
    'contact_phone' => '',
    'location' => ''
];

$fetch_stmt = $conn->prepare("SELECT * FROM suppliers WHERE user_id = ?");
$fetch_stmt->bind_param("i", $user_id);
$fetch_stmt->execute();
$fetch_res = $fetch_stmt->get_result();
if ($fetch_res->num_rows > 0) {
    $profile = $fetch_res->fetch_assoc();
}
$fetch_stmt->close();

// Fetch currently selected services for this supplier
$selected_service_ids = [];
if (!empty($profile['id'])) {
    $supp_id = (int)$profile['id'];
    $sel_stmt = $conn->prepare("SELECT service_id FROM supplier_services WHERE supplier_id = ?");
    $sel_stmt->bind_param("i", $supp_id);
    $sel_stmt->execute();
    $sel_res = $sel_stmt->get_result();
    while ($srow = $sel_res->fetch_assoc()) {
        $selected_service_ids[] = (int)$srow['service_id'];
    }
    $sel_stmt->close();
}

// Fetch active event types purely from database (no hardcoded arrays)
$event_types = [];
$et_res = $conn->query("SELECT event_type_id, type_name, description FROM event_types WHERE is_active = 1 ORDER BY event_type_id");
if ($et_res) {
    while ($row = $et_res->fetch_assoc()) {
        $event_types[] = $row;
    }
}

// Fetch all services grouped by event_type_id purely from database
$services_by_event = [];
$svc_res = $conn->query("SELECT service_id, event_type_id, service_name, description, priority_rank, is_required, typical_capacity, notes FROM services ORDER BY event_type_id, priority_rank, service_name");
if ($svc_res) {
    while ($row = $svc_res->fetch_assoc()) {
        $services_by_event[(int)$row['event_type_id']][] = $row;
    }
}

// Fetch categories purely from database
$categories = [];
$cat_res = $conn->query("SELECT DISTINCT service_name FROM services ORDER BY service_name");
if ($cat_res) {
    while ($cr = $cat_res->fetch_assoc()) {
        $categories[] = $cr['service_name'];
    }
}

$conn->close();

include __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Profile & Services - EVENTFLARE</title>
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

        .profile-container {
            max-width: 960px;
            margin: 110px auto 60px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(12px);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-header {
            text-align: center;
            margin-bottom: 35px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 25px;
        }

        .profile-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .profile-header h1 i {
            color: var(--primary);
        }

        .profile-header p {
            color: var(--text-muted);
            font-size: 15px;
            max-width: 650px;
            margin: 0 auto;
            line-height: 1.5;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-main);
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 13px 18px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-main);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
            background: #ffffff;
        }

        textarea.form-input {
            min-height: 100px;
            resize: vertical;
        }

        .section-divider {
            grid-column: 1 / -1;
            margin: 20px 0 10px;
            padding-top: 25px;
            border-top: 2px dashed #e2e8f0;
        }

        .section-title-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
            font-size: 24px;
        }

        .section-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .event-tabs-container {
            grid-column: 1 / -1;
            margin-bottom: 20px;
        }

        .event-tabs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: thin;
        }

        .event-tab-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f1f5f9;
            color: var(--text-main);
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.25s ease;
        }

        .event-tab-btn:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        .event-tab-btn.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
        }

        .event-tab-badge {
            background: rgba(255, 255, 255, 0.25);
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 12px;
            font-weight: 700;
        }

        .event-tab-btn:not(.active) .event-tab-badge {
            background: #e2e8f0;
            color: var(--text-muted);
        }

        .services-panel-wrapper {
            grid-column: 1 / -1;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .service-card {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .service-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
        }

        .service-card.is-selected {
            background: rgba(139, 92, 246, 0.04);
            border-color: var(--primary);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.12);
        }

        .service-card-top {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
        }

        .service-checkbox {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
            cursor: pointer;
            margin-top: 3px;
        }

        .service-card-header {
            flex: 1;
        }

        .service-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-heading);
            line-height: 1.3;
        }

        .service-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-top: 6px;
        }

        .service-meta-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #edf2f7;
        }

        .meta-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            background: #e2e8f0;
            color: #475569;
        }

        .meta-badge.badge-primary {
            background: rgba(139, 92, 246, 0.12);
            color: var(--primary);
        }

        .selection-summary-container {
            grid-column: 1 / -1;
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 10px;
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .summary-header h4 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-count-badge {
            background: var(--primary);
            color: white;
            padding: 2px 9px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .selected-tags-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .service-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-dark);
            transition: all 0.2s;
        }

        .service-chip:hover {
            background: rgba(139, 92, 246, 0.2);
        }

        .service-chip-remove {
            cursor: pointer;
            font-size: 14px;
            color: var(--primary);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            background: rgba(139, 92, 246, 0.15);
            transition: background 0.2s;
        }

        .service-chip-remove:hover {
            background: #ef4444;
            color: #ffffff;
        }

        .empty-summary-text {
            color: var(--text-muted);
            font-size: 13px;
            font-style: italic;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            font-weight: 700;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s, background 0.3s;
            grid-column: 1 / -1;
            margin-top: 15px;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            background: var(--primary-dark);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
        }

        .message {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        .msg-error { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .msg-success { background: #dcfce7; color: #16a34a; border: 1px solid #86efac; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .profile-container { margin: 100px 15px 40px; padding: 25px; }
            .services-grid { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <h1><i class='bx bx-store'></i> Business Profile & Services</h1>
            <p>Update your company details and select the specific items and services you supply for events.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="message msg-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="message msg-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form action="Profile.php" method="POST" id="supplierProfileForm">
            <div class="form-grid">
                
                <!-- Business Name -->
                <div class="form-group">
                    <label class="form-label">Business Name *</label>
                    <input type="text" name="business_name" class="form-input" placeholder="Enter your business name" value="<?= htmlspecialchars($profile['business_name'] ?? '') ?>" required>
                </div>

                <!-- Primary Category -->
                <div class="form-group">
                    <label class="form-label">Primary Service Category *</label>
                    <select name="category" class="form-input" required id="categorySelect">
                        <option value="" disabled <?= empty($profile['category']) ? 'selected' : '' ?>>Select primary category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= ($profile['category'] == $cat) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Contact Phone -->
                <div class="form-group">
                    <label class="form-label">Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-input" placeholder="e.g. +94 77 123 4567" value="<?= htmlspecialchars($profile['contact_phone'] ?? '') ?>">
                </div>

                <!-- Business Location -->
                <div class="form-group">
                    <label class="form-label">Business Location / Address</label>
                    <input type="text" name="location" class="form-input" placeholder="e.g. Colombo, Western Province" value="<?= htmlspecialchars($profile['location'] ?? '') ?>">
                </div>

                <!-- Description -->
                <div class="form-group full-width">
                    <label class="form-label">About Your Business & Services</label>
                    <textarea name="description" class="form-input" placeholder="Describe your experience, packages, equipment, and what makes your services stand out..."><?= htmlspecialchars($profile['description'] ?? '') ?></textarea>
                </div>

                <!-- Section Divider: Event Type and Supply Items Selection -->
                <div class="section-divider">
                    <div class="section-title-wrap">
                        <div class="section-title">
                            <i class='bx bx-list-check'></i> What Event Services Do You Supply?
                        </div>
                    </div>
                    <p class="section-subtitle">
                        Select an event category below to view its included items, then check off the specific services your business supplies. You can choose items across multiple event types.
                    </p>
                </div>

                <?php if (empty($event_types)): ?>
                    <div style="grid-column: 1 / -1; padding: 25px; text-align: center; color: var(--text-muted); background: #f8fafc; border-radius: 12px;">
                        No event types configured in the database yet.
                    </div>
                <?php else: ?>
                    <!-- Event Type Pill Tabs -->
                    <div class="event-tabs-container">
                        <div class="event-tabs" id="eventTabs">
                            <?php 
                            $first_active = true;
                            foreach ($event_types as $index => $et): 
                                $et_id = (int)$et['event_type_id'];
                                $et_services = $services_by_event[$et_id] ?? [];
                                $selected_in_this_et = 0;
                                foreach ($et_services as $s) {
                                    if (in_array((int)$s['service_id'], $selected_service_ids)) {
                                        $selected_in_this_et++;
                                    }
                                }
                            ?>
                                <button type="button" 
                                        class="event-tab-btn <?= $first_active ? 'active' : '' ?>" 
                                        data-event-id="<?= $et_id ?>">
                                    <i class='bx bx-calendar-event'></i> <?= htmlspecialchars($et['type_name']) ?>
                                    <span class="event-tab-badge" id="badge-et-<?= $et_id ?>"><?= $selected_in_this_et ?></span>
                                </button>
                            <?php 
                                $first_active = false;
                            endforeach; 
                            ?>
                        </div>
                    </div>

                    <!-- Services Grid Panels (One panel per Event Type) -->
                    <div class="services-panel-wrapper">
                        <?php 
                        $first_panel = true;
                        foreach ($event_types as $et): 
                            $et_id = (int)$et['event_type_id'];
                            $et_services = $services_by_event[$et_id] ?? [];
                        ?>
                            <div class="event-services-panel" 
                                 id="panel-et-<?= $et_id ?>" 
                                 style="<?= $first_panel ? '' : 'display: none;' ?>">
                                
                                <div style="margin-bottom: 15px; color: var(--text-muted); font-size: 14px;">
                                    <strong><?= htmlspecialchars($et['type_name']) ?> Items:</strong> <?= htmlspecialchars($et['description'] ?? '') ?>
                                </div>

                                <?php if (empty($et_services)): ?>
                                    <div style="padding: 20px; text-align: center; color: var(--text-muted); background: #f8fafc; border-radius: 12px;">
                                        No services catalogued for this event type yet.
                                    </div>
                                <?php else: ?>
                                    <div class="services-grid">
                                        <?php foreach ($et_services as $svc): 
                                            $s_id = (int)$svc['service_id'];
                                            $is_checked = in_array($s_id, $selected_service_ids);
                                        ?>
                                            <div class="service-card <?= $is_checked ? 'is-selected' : '' ?>" 
                                                 data-service-id="<?= $s_id ?>" 
                                                 data-service-name="<?= htmlspecialchars($svc['service_name']) ?>"
                                                 data-event-name="<?= htmlspecialchars($et['type_name']) ?>"
                                                 data-event-id="<?= $et_id ?>">
                                                <div class="service-card-top">
                                                    <input type="checkbox" 
                                                           name="services[]" 
                                                           value="<?= $s_id ?>" 
                                                           id="svc_<?= $s_id ?>" 
                                                           class="service-checkbox" 
                                                           <?= $is_checked ? 'checked' : '' ?>>
                                                    <div class="service-card-header">
                                                        <label for="svc_<?= $s_id ?>" class="service-name" style="cursor: pointer;">
                                                            <?= htmlspecialchars($svc['service_name']) ?>
                                                        </label>
                                                        <div class="service-desc">
                                                            <?= htmlspecialchars($svc['description'] ?? '') ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="service-meta-badges">
                                                    <?php if (!empty($svc['typical_capacity'])): ?>
                                                        <span class="meta-badge"><i class='bx bx-group'></i> Up to <?= (int)$svc['typical_capacity'] ?> guests</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($svc['is_required'])): ?>
                                                        <span class="meta-badge badge-primary"><i class='bx bx-check-circle'></i> Core Service</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php 
                            $first_panel = false;
                        endforeach; 
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Live Summary Tag Cloud of Selected Services -->
                <div class="selection-summary-container">
                    <div class="summary-header">
                        <h4><i class='bx bx-check-double'></i> Your Selected Services & Offerings</h4>
                        <span class="summary-count-badge" id="selectedCountBadge">0 Selected</span>
                    </div>
                    <div class="selected-tags-wrapper" id="selectedTagsWrapper">
                        <span class="empty-summary-text" id="emptySummaryText">
                            No services selected yet. Pick an event type above to choose the items your business supplies.
                        </span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" name="save_profile" class="submit-btn">
                    <i class='bx bx-save'></i> Save Profile & Supplied Services
                </button>
            </div>
            
            <div style="text-align: center; margin-top: 25px;">
                <a href="Dashboard.php" style="color: var(--text-muted); text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                    <i class='bx bx-arrow-back'></i> Back to Supplier Dashboard
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const eventTabButtons = document.querySelectorAll('.event-tab-btn');
            const eventPanels = document.querySelectorAll('.event-services-panel');
            const serviceCards = document.querySelectorAll('.service-card');
            const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
            const selectedTagsWrapper = document.getElementById('selectedTagsWrapper');
            const emptySummaryText = document.getElementById('emptySummaryText');
            const selectedCountBadge = document.getElementById('selectedCountBadge');

            // 1. Tab switching logic
            eventTabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const eventId = this.getAttribute('data-event-id');
                    
                    // Activate tab button
                    eventTabButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    // Show target panel
                    eventPanels.forEach(panel => {
                        panel.style.display = (panel.id === 'panel-et-' + eventId) ? 'block' : 'none';
                    });
                });
            });

            // 2. Card click toggles checkbox
            serviceCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'label') {
                        return;
                    }
                    const chk = this.querySelector('.service-checkbox');
                    if (chk) {
                        chk.checked = !chk.checked;
                        chk.dispatchEvent(new Event('change'));
                    }
                });
            });

            // 3. Checkbox change updates UI, card state, tab counters & summary tags
            function refreshSelectionUI() {
                let totalSelected = 0;
                const countsByEvent = {};
                selectedTagsWrapper.innerHTML = '';

                serviceCheckboxes.forEach(chk => {
                    const card = chk.closest('.service-card');
                    const svcId = chk.value;
                    const svcName = card.getAttribute('data-service-name');
                    const eventName = card.getAttribute('data-event-name');
                    const eventId = card.getAttribute('data-event-id');

                    if (!countsByEvent[eventId]) countsByEvent[eventId] = 0;

                    if (chk.checked) {
                        card.classList.add('is-selected');
                        totalSelected++;
                        countsByEvent[eventId]++;

                        // Create summary chip
                        const chip = document.createElement('div');
                        chip.className = 'service-chip';
                        chip.innerHTML = `<span><strong>${escapeHtml(svcName)}</strong> (${escapeHtml(eventName)})</span>` +
                                         `<span class="service-chip-remove" title="Remove" data-svc-id="${svcId}">&times;</span>`;
                        selectedTagsWrapper.appendChild(chip);
                    } else {
                        card.classList.remove('is-selected');
                    }
                });

                // Update count badge
                selectedCountBadge.textContent = totalSelected + ' Selected';

                // Update tab badges
                document.querySelectorAll('[id^="badge-et-"]').forEach(badge => {
                    const etId = badge.id.replace('badge-et-', '');
                    badge.textContent = countsByEvent[etId] || 0;
                });

                // Empty state text
                if (totalSelected === 0) {
                    selectedTagsWrapper.appendChild(emptySummaryText);
                    emptySummaryText.style.display = 'inline';
                }
            }

            // Remove tag handler (delegation)
            selectedTagsWrapper.addEventListener('click', function(e) {
                if (e.target.classList.contains('service-chip-remove')) {
                    const svcId = e.target.getAttribute('data-svc-id');
                    const targetCheckbox = document.getElementById('svc_' + svcId);
                    if (targetCheckbox) {
                        targetCheckbox.checked = false;
                        targetCheckbox.dispatchEvent(new Event('change'));
                    }
                }
            });

            serviceCheckboxes.forEach(chk => {
                chk.addEventListener('change', refreshSelectionUI);
            });

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Initial render of summary and counters
            refreshSelectionUI();
        });
    </script>
</body>
</html>
