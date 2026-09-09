<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'suppliers';

// 1. Handle Add New Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid. Please try again.');
        header('Location: Suppliers.php');
        exit();
    }

    $username = trim(htmlspecialchars($_POST['username'] ?? ''));
    $fullname = trim(htmlspecialchars($_POST['fullname'] ?? ''));
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $business_name = trim(htmlspecialchars($_POST['business_name'] ?? ''));
    $category = trim(htmlspecialchars($_POST['category'] ?? ''));
    $contact_phone = trim(htmlspecialchars($_POST['contact_phone'] ?? ''));
    $location = trim(htmlspecialchars($_POST['location'] ?? ''));
    $description = trim(htmlspecialchars($_POST['description'] ?? ''));

    if (empty($username) || empty($fullname) || empty($email) || empty($password) || empty($business_name)) {
        set_flash_message('error', 'Username, full name, email, password, and business name are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('error', 'Invalid email address format.');
    } elseif (strlen($password) < 6) {
        set_flash_message('error', 'Password must be at least 6 characters long.');
    } else {
        // Check username and email uniqueness
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            set_flash_message('error', 'A user with this username or email already exists.');
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 'supplier';
            $ins_user = $conn->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)");
            $ins_user->bind_param("sssss", $username, $fullname, $email, $hashed, $role);

            if ($ins_user->execute()) {
                $new_user_id = (int)$conn->insert_id;
                $ins_supp = $conn->prepare("INSERT INTO suppliers (user_id, business_name, category, description, contact_phone, location) VALUES (?, ?, ?, ?, ?, ?)");
                $ins_supp->bind_param("isssss", $new_user_id, $business_name, $category, $description, $contact_phone, $location);

                if ($ins_supp->execute()) {
                    set_flash_message('success', "New supplier '{$business_name}' (@{$username}) created successfully!");
                } else {
                    set_flash_message('error', "User created but supplier profile failed: " . $ins_supp->error);
                }
                $ins_supp->close();
            } else {
                set_flash_message('error', "Failed to create user account: " . $ins_user->error);
            }
            $ins_user->close();
        }
        $check->close();
    }
    header('Location: Suppliers.php');
    exit();
}

// 2. Handle Edit Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_supplier') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header('Location: Suppliers.php');
        exit();
    }

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $business_name = trim(htmlspecialchars($_POST['business_name'] ?? ''));
    $category = trim(htmlspecialchars($_POST['category'] ?? ''));
    $contact_phone = trim(htmlspecialchars($_POST['contact_phone'] ?? ''));
    $location = trim(htmlspecialchars($_POST['location'] ?? ''));
    $description = trim(htmlspecialchars($_POST['description'] ?? ''));
    $fullname = trim(htmlspecialchars($_POST['fullname'] ?? ''));

    if ($supplier_id > 0 && !empty($business_name)) {
        $upd_supp = $conn->prepare("UPDATE suppliers SET business_name = ?, category = ?, contact_phone = ?, location = ?, description = ? WHERE id = ?");
        $upd_supp->bind_param("sssssi", $business_name, $category, $contact_phone, $location, $description, $supplier_id);
        $ok = $upd_supp->execute();
        $upd_supp->close();

        if (!empty($fullname) && $user_id > 0) {
            $upd_user = $conn->prepare("UPDATE users SET fullname = ? WHERE id = ?");
            $upd_user->bind_param("si", $fullname, $user_id);
            $upd_user->execute();
            $upd_user->close();
        }

        if ($ok) {
            set_flash_message('success', "Supplier '{$business_name}' updated successfully.");
        } else {
            set_flash_message('error', "Failed to update supplier.");
        }
    } else {
        set_flash_message('error', "Business name cannot be empty.");
    }
    header('Location: Suppliers.php');
    exit();
}

// 3. Handle Supplier Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_supplier') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid. Please try again.');
        header('Location: Suppliers.php');
        exit();
    }
    $del_id = (int)($_POST['supplier_id'] ?? 0);
    if ($del_id > 0) {
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        if ($stmt->execute()) {
            set_flash_message('success', "Supplier #{$del_id} and related services/listings removed successfully.");
        } else {
            set_flash_message('error', "Failed to delete supplier: " . $stmt->error);
        }
        $stmt->close();
    }
    header('Location: Suppliers.php');
    exit();
}

// 4. Search and filter parameters
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(s.business_name LIKE ? OR u.username LIKE ? OR u.fullname LIKE ? OR s.location LIKE ?)";
    $like = "%{$search}%";
    $params[] = &$like;
    $params[] = &$like;
    $params[] = &$like;
    $params[] = &$like;
    $types .= "ssss";
}

if (!empty($category_filter)) {
    $where_clauses[] = "s.category = ?";
    $params[] = &$category_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch categories for dropdowns
$categories = [];
$cat_res = $conn->query("SELECT DISTINCT category FROM suppliers WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
if ($cat_res) {
    while ($c = $cat_res->fetch_assoc()) {
        $categories[] = $c['category'];
    }
}
if (empty($categories)) {
    $categories = ['Catering', 'Decorators', 'Photography & Videography', 'Venue/Furniture Rentals', 'Entertainment', 'Audio/Visual (A/V)', 'Bakeries & Confectioners'];
}

// Preload supplier service capabilities
$supplier_services_map = [];
$ss_res = $conn->query("
    SELECT ss.supplier_id, s.service_id, s.service_name, et.type_name
    FROM supplier_services ss
    JOIN services s ON ss.service_id = s.service_id
    LEFT JOIN event_types et ON s.event_type_id = et.event_type_id
    ORDER BY s.service_name ASC
");
if ($ss_res) {
    while ($r = $ss_res->fetch_assoc()) {
        $supplier_services_map[$r['supplier_id']][] = $r;
    }
}

// Preload supplier packages & listings
$supplier_listings_map = [];
$sl_res = $conn->query("
    SELECT sl.listing_id, sl.supplier_id, sl.service_id, sl.title, sl.price, sl.price_type, sl.status, sl.description,
           s.service_name
    FROM supplier_listings sl
    LEFT JOIN services s ON sl.service_id = s.service_id
    ORDER BY sl.status ASC, sl.listing_id DESC
");
if ($sl_res) {
    while ($r = $sl_res->fetch_assoc()) {
        $supplier_listings_map[$r['supplier_id']][] = $r;
    }
}

// Preload contracted event bookings
$supplier_bookings_map = [];
$sb_res = $conn->query("
    SELECT bs.id as bs_id, bs.booking_id, bs.supplier_id, bs.service_id, bs.assigned_cost, bs.status as assignment_status, bs.custom_notes,
           b.EventType, b.EventDate, b.Place, b.NumberOfGuests, b.status as booking_status,
           u.fullname as client_name, s.service_name
    FROM booking_services bs
    JOIN bookings b ON bs.booking_id = b.BookingID
    JOIN services s ON bs.service_id = s.service_id
    LEFT JOIN users u ON b.user_name = u.username
    ORDER BY b.EventDate DESC
");
if ($sb_res) {
    while ($r = $sb_res->fetch_assoc()) {
        $supplier_bookings_map[$r['supplier_id']][] = $r;
    }
}

// Preload supplier ratings
$supplier_ratings_map = [];
$sr_res = $conn->query("
    SELECT supplier_id, 
           COUNT(*) as review_count, 
           AVG(rating) as avg_rating
    FROM ratings 
    WHERE admin_status = 'approved' 
    GROUP BY supplier_id
");
if ($sr_res) {
    while ($r = $sr_res->fetch_assoc()) {
        $supplier_ratings_map[$r['supplier_id']] = [
            'count' => (int)$r['review_count'],
            'avg' => round((float)$r['avg_rating'], 1)
        ];
    }
}

// Fetch suppliers
$sql = "SELECT s.id, s.business_name, s.category, s.contact_phone, s.location, s.description, s.created_at,
               u.id AS user_id, u.username, u.fullname, u.email,
               COUNT(sl.listing_id) AS total_listings,
               SUM(CASE WHEN sl.status = 'active' THEN 1 ELSE 0 END) AS active_listings
        FROM suppliers s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN supplier_listings sl ON s.id = sl.supplier_id
        WHERE {$where_sql}
        GROUP BY s.id
        ORDER BY s.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$suppliers_res = $stmt->get_result();
$suppliers = [];
while ($row = $suppliers_res->fetch_assoc()) {
    $sid = $row['id'];
    $row['services'] = $supplier_services_map[$sid] ?? [];
    $row['listings'] = $supplier_listings_map[$sid] ?? [];
    $row['bookings'] = $supplier_bookings_map[$sid] ?? [];
    $row['rating_info'] = $supplier_ratings_map[$sid] ?? ['count' => 0, 'avg' => 0.0];
    $suppliers[] = $row;
}
$stmt->close();

// Fetch total customers count for directory segmented switcher
$total_customers_count = 0;
$cust_count_res = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'buyer'");
if ($cust_count_res) {
    $total_customers_count = (int)$cust_count_res->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - EVENTFLARE Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.js"></script>
</head>
<body class="dashboard-body">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1>Supplier Management</h1>
                <p>Register new vendors, update business profiles, and control vendor listings.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <!-- Directory Segmented Navigation Switcher -->
        <div class="directory-segment-bar">
            <a href="Userlist.php" class="directory-segment-pill">
                <i class="fas fa-user-friends"></i> Customers
                <span class="segment-badge"><?= $total_customers_count; ?></span>
            </a>
            <a href="Suppliers.php" class="directory-segment-pill active">
                <i class="fas fa-store"></i> Suppliers
                <span class="segment-badge"><?= count($suppliers); ?></span>
            </a>
        </div>

        <?= render_flash_message(); ?>

        <!-- Toolbar -->
        <div class="dashboard-toolbar">
            <form method="GET" action="Suppliers.php" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                <div class="toolbar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search business, owner, city..." value="<?= htmlspecialchars($search); ?>">
                </div>
                <select name="category" class="toolbar-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat); ?>" <?= ($category_filter === $cat) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if (!empty($search) || !empty($category_filter)): ?>
                    <a href="Suppliers.php" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </form>

            <div class="toolbar-actions" style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="btn btn-primary btn-sm" id="openAddSupplierBtn">
                    <i class="fas fa-plus-circle"></i> Add New Supplier
                </button>
                <a href="SupplierListings.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-boxes"></i> All Supplier Listings
                </a>
                <button id="downloadPdf" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button id="downloadCsv" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
            </div>
        </div>

        <!-- Supplier Table -->
        <div class="dashboard-panel" id="makepdf">
            <div class="dashboard-table-container">
                <table class="dashboard-table" id="suppliersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Business Name</th>
                            <th>Owner Account</th>
                            <th>Category</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Listings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($suppliers)): ?>
                            <?php foreach ($suppliers as $s): ?>
                                <tr>
                                    <td><strong>#<?= $s['id']; ?></strong></td>
                                    <td>
                                        <div>
                                            <a href="javascript:void(0)" class="view-supplier-btn"
                                               data-supplier='<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8'); ?>'
                                               style="font-weight: 700; color: var(--text-heading); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"
                                               title="Click to inspect complete supplier dossier">
                                                <span><?= htmlspecialchars($s['business_name']); ?></span>
                                                <i class="fas fa-external-link-alt" style="color: var(--primary); font-size: 11px;"></i>
                                            </a>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 3px;">
                                            <small style="color: var(--text-muted);">Joined <?= date('M j, Y', strtotime($s['created_at'])); ?></small>
                                            <?php if (!empty($s['rating_info']['count'])): ?>
                                                <a href="Ratings.php?supplier_id=<?= $s['id']; ?>" 
                                                   style="display: inline-flex; align-items: center; gap: 3px; background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; border-radius: 12px; font-size: 11px; font-weight: 800; padding: 1px 7px; text-decoration: none;"
                                                   title="<?= $s['rating_info']['count']; ?> verified customer reviews (click to inspect in Ratings Console)">
                                                    <i class="fas fa-star" style="color: #f59e0b; font-size: 10px;"></i>
                                                    <?= number_format($s['rating_info']['avg'], 1); ?>
                                                    <span style="color: #92400e; font-weight: 500;">(<?= $s['rating_info']['count']; ?>)</span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($s['fullname']); ?></div>
                                        <small style="color: var(--text-muted);"><i class="fas fa-user"></i> <?= htmlspecialchars($s['username']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?= htmlspecialchars($s['category'] ?: 'Unassigned'); ?></span>
                                    </td>
                                    <td>
                                        <div><i class="fas fa-phone fa-xs" style="color:var(--primary);"></i> <?= htmlspecialchars($s['contact_phone'] ?: 'N/A'); ?></div>
                                        <small style="color: var(--text-muted);"><i class="fas fa-envelope fa-xs"></i> <?= htmlspecialchars($s['email']); ?></small>
                                    </td>
                                    <td>
                                        <i class="fas fa-map-marker-alt fa-xs" style="color: #ef4444;"></i> <?= htmlspecialchars($s['location'] ?: 'Not set'); ?>
                                    </td>
                                    <td>
                                        <strong><?= (int)$s['total_listings']; ?></strong> total
                                        <span style="color: #10b981; font-size: 11px;">(<?= (int)$s['active_listings']; ?> active)</span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <!-- View Details Dossier -->
                                            <button type="button" class="btn btn-outline btn-sm view-supplier-btn"
                                                    data-supplier='<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8'); ?>'
                                                    title="View Full Supplier Dossier, Listings & Assigned Events"
                                                    style="border-color: var(--primary); color: var(--primary); font-weight: 700;">
                                                <i class="fas fa-eye"></i> View Profile
                                            </button>

                                            <!-- Control Listings in Admin Panel -->
                                            <a href="SupplierListings.php?supplier_id=<?= $s['id']; ?>" 
                                               class="btn btn-outline btn-sm" title="View and Control this Supplier's Listings">
                                                <i class="fas fa-boxes"></i> Listings (<?= (int)$s['total_listings']; ?>)
                                            </a>

                                            <!-- Edit Supplier Profile -->
                                            <button type="button" class="btn btn-outline btn-sm edit-supplier-btn"
                                                    data-supplier='<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8'); ?>'
                                                    title="Edit Supplier Profile Details">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>

                                            <!-- Delete Supplier -->
                                            <form method="POST" action="Suppliers.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete supplier \'<?= htmlspecialchars(addslashes($s['business_name'])); ?>\'? All listings and vendor data will be permanently removed.');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_supplier">
                                                <input type="hidden" name="supplier_id" value="<?= $s['id']; ?>">
                                                <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Supplier">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-store-slash" style="font-size: 32px; display:block; margin-bottom: 10px; color: #cbd5e1;"></i>
                                    No suppliers found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal: Add New Supplier -->
    <div class="admin-modal" id="addSupplierModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Register New Supplier</h3>
                <button type="button" class="modal-close" onclick="closeModal('addSupplierModal')">&times;</button>
            </div>
            <form method="POST" action="Suppliers.php">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_supplier">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Business Name *</label>
                            <input type="text" name="business_name" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. Royal Blooms Floral" required>
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Primary Category *</label>
                            <input type="text" name="category" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. Decorators, Catering" required list="catList">
                            <datalist id="catList">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Account Username *</label>
                            <input type="text" name="username" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. royalblooms" required>
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Owner Full Name *</label>
                            <input type="text" name="fullname" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. Amanda Perera" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Email Address *</label>
                            <input type="email" name="email" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="vendor@example.com" required>
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Temporary Password *</label>
                            <input type="password" name="password" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="Min 6 characters" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Contact Phone</label>
                            <input type="text" name="contact_phone" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="+94 77 123 4567">
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Location / City</label>
                            <input type="text" name="location" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. Colombo">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Description / Profile Notes</label>
                        <textarea name="description" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border); min-height:80px;" placeholder="Brief summary of items and packages supplied..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addSupplierModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Register Supplier</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Supplier -->
    <div class="admin-modal" id="editSupplierModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary);"></i> Edit Supplier Profile</h3>
                <button type="button" class="modal-close" onclick="closeModal('editSupplierModal')">&times;</button>
            </div>
            <form method="POST" action="Suppliers.php">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_supplier">
                <input type="hidden" name="supplier_id" id="editSupplierId" value="">
                <input type="hidden" name="user_id" id="editUserId" value="">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Business Name *</label>
                            <input type="text" name="business_name" id="editBusinessName" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" required>
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Category *</label>
                            <input type="text" name="category" id="editCategory" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);" required list="catList">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Owner Full Name</label>
                            <input type="text" name="fullname" id="editFullname" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);">
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Username</label>
                            <input type="text" id="editUsername" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border); background:#f1f5f9;" readonly>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Contact Phone</label>
                            <input type="text" name="contact_phone" id="editPhone" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);">
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Location / City</label>
                            <input type="text" name="location" id="editLocation" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border);">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Description</label>
                        <textarea name="description" id="editDescription" class="form-input" style="width:100%; padding: 10px 12px; border-radius:8px; border:1px solid var(--card-border); min-height:80px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editSupplierModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: View Supplier Dossier -->
    <div class="admin-modal" id="viewSupplierModal">
        <div class="modal-dialog modal-dialog-lg">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(139, 92, 246, 0.12); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 20px;">
                        <i class="fas fa-store"></i>
                    </div>
                    <div>
                        <h3 id="vModalTitle" style="margin: 0; font-size: 18px; font-weight: 800; color: var(--text-heading);">Supplier Dossier</h3>
                        <p id="vModalSubtitle" style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">Verified Partner Profile & Operational Records</p>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('viewSupplierModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewSupplierBody" style="max-height: 75vh; overflow-y: auto;">
                <!-- Populated dynamically by JS -->
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <button type="button" class="btn btn-outline btn-sm" id="printSupplierDossierBtn" style="border-color: var(--primary); color: var(--primary); font-weight: 700;">
                    <i class="fas fa-print"></i> Print Supplier Dossier
                </button>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="#" id="vViewReviewsBtn" class="btn btn-outline btn-sm" style="color: #b45309; border-color: #fde68a; background: #fffbeb;">
                        <i class="fas fa-star" style="color:#f59e0b;"></i> Customer Reviews
                    </a>
                    <a href="#" id="vManageListingsBtn" class="btn btn-outline btn-sm">
                        <i class="fas fa-boxes"></i> Manage Listings
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" id="vEditProfileBtn">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('viewSupplierModal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal helpers
        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        document.getElementById('openAddSupplierBtn').addEventListener('click', () => openModal('addSupplierModal'));

        // Edit Supplier Click
        document.querySelectorAll('.edit-supplier-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const s = JSON.parse(btn.getAttribute('data-supplier'));
                openEditModal(s);
            });
        });

        function openEditModal(s) {
            document.getElementById('editSupplierId').value = s.id;
            document.getElementById('editUserId').value = s.user_id;
            document.getElementById('editBusinessName').value = s.business_name || '';
            document.getElementById('editCategory').value = s.category || '';
            document.getElementById('editFullname').value = s.fullname || '';
            document.getElementById('editUsername').value = s.username || '';
            document.getElementById('editPhone').value = s.contact_phone || '';
            document.getElementById('editLocation').value = s.location || '';
            document.getElementById('editDescription').value = s.description || '';
            openModal('editSupplierModal');
        }

        let activeDossierSupplier = null;

        // View Supplier Click
        document.querySelectorAll('.view-supplier-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const s = JSON.parse(btn.getAttribute('data-supplier'));
                activeDossierSupplier = s;
                renderSupplierDossier(s);
                openModal('viewSupplierModal');
            });
        });

        function renderSupplierDossier(s) {
            document.getElementById('vModalTitle').innerText = `${s.business_name}`;
            document.getElementById('vModalSubtitle').innerText = `Supplier ID #${s.id} &bull; Joined ${s.created_at || 'Recently'}`;

            // Update footer links
            document.getElementById('vManageListingsBtn').href = `SupplierListings.php?supplier_id=${s.id}`;
            document.getElementById('vViewReviewsBtn').href = `Ratings.php?supplier_id=${s.id}`;
            document.getElementById('vEditProfileBtn').onclick = () => {
                closeModal('viewSupplierModal');
                openEditModal(s);
            };

            // Calculate total contracted booking revenue
            const bookings = s.bookings || [];
            let totalRevenue = 0;
            bookings.forEach(b => {
                totalRevenue += parseFloat(b.assigned_cost || 0);
            });

            // Service tags
            const services = s.services || [];
            let servicesTagsHtml = '';
            if (services.length > 0) {
                servicesTagsHtml = services.map(svc => `
                    <span style="display:inline-flex; align-items:center; gap:5px; background: #eef2ff; color: #4338ca; padding: 5px 12px; border-radius: 16px; font-size: 12px; font-weight: 700; border: 1px solid #c7d2fe;">
                        <i class="fas fa-check-circle fa-xs"></i> ${svc.service_name}
                    </span>
                `).join(' ');
            } else {
                servicesTagsHtml = `<span style="color: #64748b; font-size: 13px;">${s.category || 'General event vendor'}</span>`;
            }

            // Listings rows
            const listings = s.listings || [];
            let listingsHtml = '';
            if (listings.length > 0) {
                listingsHtml = `
                    <div style="overflow-x: auto; margin-top: 10px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                                    <th style="padding: 10px 12px;">Package / Offering</th>
                                    <th style="padding: 10px 12px;">Category</th>
                                    <th style="padding: 10px 12px;">Pricing</th>
                                    <th style="padding: 10px 12px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${listings.map(l => `
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 10px 12px;">
                                            <div style="font-weight: 700; color: var(--text-heading);">${l.title}</div>
                                            ${l.description ? `<small style="color: #64748b;">${l.description.substring(0, 70)}...</small>` : ''}
                                        </td>
                                        <td style="padding: 10px 12px;">${l.service_name || '-'}</td>
                                        <td style="padding: 10px 12px; font-weight: 700; color: #15803d;">
                                            LKR ${Number(l.price).toLocaleString()}
                                            <small style="color:#64748b; font-weight: normal; display:block;">${(l.price_type || '').replace('_', ' ')}</small>
                                        </td>
                                        <td style="padding: 10px 12px;">
                                            <span class="status-pill status-${l.status === 'active' ? 'confirmed' : 'cancelled'}" style="font-size: 11px; padding: 2px 8px;">
                                                ${(l.status || 'active').toUpperCase()}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                listingsHtml = `
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 20px; text-align: center; color: #64748b; font-size: 13px; margin-top: 10px;">
                        <i class="fas fa-boxes" style="font-size: 24px; color: #cbd5e1; margin-bottom: 6px; display: block;"></i>
                        No specific packages listed yet. <a href="SupplierListings.php?supplier_id=${s.id}" style="color: var(--primary); font-weight: 700;">Add first listing &rarr;</a>
                    </div>
                `;
            }

            // Bookings rows
            let bookingsHtml = '';
            if (bookings.length > 0) {
                bookingsHtml = `
                    <div style="overflow-x: auto; margin-top: 10px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                                    <th style="padding: 10px 12px;">Booking ID</th>
                                    <th style="padding: 10px 12px;">Event & Client</th>
                                    <th style="padding: 10px 12px;">Assigned Service</th>
                                    <th style="padding: 10px 12px;">Agreed Cost</th>
                                    <th style="padding: 10px 12px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${bookings.map(b => `
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 10px 12px;">
                                            <a href="Bookinglist.php?search=${b.booking_id}" target="_blank" style="font-weight: 800; color: var(--primary); text-decoration: none;">
                                                #${b.booking_id} <i class="fas fa-external-link-alt fa-xs" style="font-size: 10px;"></i>
                                            </a>
                                            <small style="display:block; color: #64748b;">${b.EventDate}</small>
                                        </td>
                                        <td style="padding: 10px 12px;">
                                            <div style="font-weight: 700; color: var(--text-heading);">${b.EventType}</div>
                                            <small style="color: #64748b;">${b.client_name || 'Client'} &bull; ${b.Place}</small>
                                        </td>
                                        <td style="padding: 10px 12px;">
                                            <div style="font-weight: 600;">${b.service_name}</div>
                                            ${b.custom_notes ? `<small style="color: #64748b;">${b.custom_notes.substring(0, 50)}...</small>` : ''}
                                        </td>
                                        <td style="padding: 10px 12px; font-weight: 700; color: #15803d;">
                                            LKR ${Number(b.assigned_cost).toLocaleString()}
                                        </td>
                                        <td style="padding: 10px 12px;">
                                            <span class="status-pill status-${b.assignment_status || 'confirmed'}" style="font-size: 11px; padding: 2px 8px;">
                                                ${(b.assignment_status || 'confirmed').toUpperCase()}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                bookingsHtml = `
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 20px; text-align: center; color: #64748b; font-size: 13px; margin-top: 10px;">
                        <i class="fas fa-calendar-times" style="font-size: 24px; color: #cbd5e1; margin-bottom: 6px; display: block;"></i>
                        No events currently assigned to this vendor. You can assign them inside any booking dossier in <a href="Bookinglist.php" style="color: var(--primary); font-weight: 700;">Bookings &rarr;</a>
                    </div>
                `;
            }

            document.getElementById('viewSupplierBody').innerHTML = `
                <!-- Quick Stats Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 20px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center;">
                        <small style="color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; display: block;">TOTAL PACKAGES</small>
                        <div style="font-size: 20px; font-weight: 800; color: var(--text-heading); margin-top: 4px;">${s.total_listings}</div>
                    </div>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 12px; text-align: center;">
                        <small style="color: #166534; font-size: 11px; font-weight: 700; text-transform: uppercase; display: block;">ACTIVE PACKAGES</small>
                        <div style="font-size: 20px; font-weight: 800; color: #15803d; margin-top: 4px;">${s.active_listings}</div>
                    </div>
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 12px; text-align: center;">
                        <small style="color: #1e40af; font-size: 11px; font-weight: 700; text-transform: uppercase; display: block;">CONTRACTED EVENTS</small>
                        <div style="font-size: 20px; font-weight: 800; color: #2563eb; margin-top: 4px;">${bookings.length}</div>
                    </div>
                    <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; padding: 12px; text-align: center;">
                        <small style="color: #6b21a8; font-size: 11px; font-weight: 700; text-transform: uppercase; display: block;">TOTAL ALLOCATIONS</small>
                        <div style="font-size: 16px; font-weight: 800; color: var(--primary); margin-top: 6px;">LKR ${Number(totalRevenue).toLocaleString()}</div>
                    </div>
                    <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; padding: 12px; text-align: center;">
                        <small style="color: #92400e; font-size: 11px; font-weight: 700; text-transform: uppercase; display: block;">CUSTOMER RATING</small>
                        <div style="font-size: 18px; font-weight: 800; color: #b45309; margin-top: 4px; display: flex; align-items: center; justify-content: center; gap: 4px;">
                            <i class="fas fa-star" style="color: #f59e0b;"></i>
                            ${s.rating_info && s.rating_info.count > 0 ? `${Number(s.rating_info.avg).toFixed(1)} <small style="font-size:12px; font-weight:600; color:#92400e;">(${s.rating_info.count})</small>` : '<span style="font-size:12px; font-weight:600; color:#94a3b8;">No reviews</span>'}
                        </div>
                    </div>
                </div>

                <!-- 2-Column Contact & Identity Profile -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-id-card"></i> Profile & Account Information
                        </div>
                        <div style="display: grid; gap: 10px; font-size: 13px;">
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">BUSINESS NAME</strong>
                                <span style="font-weight: 700; font-size: 15px; color: var(--text-heading);">${s.business_name}</span>
                            </div>
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">PRIMARY CATEGORY</strong>
                                <span class="badge badge-primary">${s.category || 'Unassigned'}</span>
                            </div>
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">ACCOUNT HOLDER</strong>
                                <span style="font-weight: 600;">${s.fullname}</span> 
                                <small style="color: #64748b;">(@${s.username})</small>
                            </div>
                        </div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-address-book"></i> Operating Contacts & Location
                        </div>
                        <div style="display: grid; gap: 10px; font-size: 13px;">
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">DIRECT PHONE</strong>
                                <a href="tel:${s.contact_phone}" style="font-weight: 700; color: #15803d; text-decoration: none;">
                                    <i class="fas fa-phone-alt fa-xs"></i> ${s.contact_phone || 'No phone provided'}
                                </a>
                            </div>
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">EMAIL ADDRESS</strong>
                                <a href="mailto:${s.email}" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                                    <i class="fas fa-envelope fa-xs"></i> ${s.email}
                                </a>
                            </div>
                            <div>
                                <strong style="color: #64748b; font-size: 11px; display: block;">OPERATING BASE / CITY</strong>
                                <span style="font-weight: 600; color: var(--text-heading);"><i class="fas fa-map-marker-alt fa-xs" style="color: #ef4444;"></i> ${s.location || 'Island-wide'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Business Description -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-align-left"></i> Business Bio & Profile Notes
                    </div>
                    <div style="font-size: 13px; line-height: 1.6; color: var(--text-heading);">
                        ${s.description ? s.description.replace(/\n/g, '<br>') : '<em style="color:#94a3b8;">No detailed description submitted.</em>'}
                    </div>
                </div>

                <!-- Service Capabilities -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-concierge-bell"></i> Registered Service Capabilities (${services.length})
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        ${servicesTagsHtml}
                    </div>
                </div>

                <!-- Catalog Packages & Listings -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-boxes"></i> Catalog Packages & Offerings (${listings.length})
                        </div>
                        <a href="SupplierListings.php?supplier_id=${s.id}" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 10px;">
                            <i class="fas fa-external-link-alt fa-xs"></i> Manage in Listings
                        </a>
                    </div>
                    ${listingsHtml}
                </div>

                <!-- Contracted Event Bookings -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-calendar-check"></i> Contracted Event Bookings & Engagements (${bookings.length})
                        </div>
                        <a href="Bookinglist.php" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 4px 10px;">
                            <i class="fas fa-calendar"></i> All Bookings
                        </a>
                    </div>
                    ${bookingsHtml}
                </div>
            `;
        }

        // Print Supplier Dossier
        document.getElementById('printSupplierDossierBtn').addEventListener('click', () => {
            if (!activeDossierSupplier) return;
            const s = activeDossierSupplier;
            const listings = s.listings || [];
            const bookings = s.bookings || [];

            let listingsRows = '';
            if (listings.length > 0) {
                listingsRows = listings.map(l => `
                    <tr>
                        <td><strong>${l.title}</strong></td>
                        <td>${l.service_name || '-'}</td>
                        <td>LKR ${Number(l.price).toLocaleString()} (${(l.price_type || '').replace('_', ' ')})</td>
                        <td>${(l.status || 'active').toUpperCase()}</td>
                    </tr>
                `).join('');
            } else {
                listingsRows = `<tr><td colspan="4" style="text-align:center; color:#94a3b8;">No packages currently listed.</td></tr>`;
            }

            let bookingsRows = '';
            if (bookings.length > 0) {
                bookingsRows = bookings.map(b => `
                    <tr>
                        <td><strong>#${b.booking_id}</strong></td>
                        <td>${b.EventType} (${b.EventDate})</td>
                        <td>${b.client_name || '-'}</td>
                        <td>${b.service_name}</td>
                        <td>LKR ${Number(b.assigned_cost).toLocaleString()}</td>
                        <td>${(b.assignment_status || 'confirmed').toUpperCase()}</td>
                    </tr>
                `).join('');
            } else {
                bookingsRows = `<tr><td colspan="6" style="text-align:center; color:#94a3b8;">No events assigned to this vendor yet.</td></tr>`;
            }

            const win = window.open('', '', 'width=850,height=700');
            win.document.write(`
                <html>
                <head>
                    <title>Supplier Dossier - ${s.business_name}</title>
                    <style>
                        body { font-family: sans-serif; padding: 30px; color: #1e293b; line-height: 1.5; }
                        h1 { color: #6d28d9; margin: 0; font-size: 24px; }
                        h3 { color: #0f172a; margin: 25px 0 10px; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
                        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
                        th { background: #f8fafc; font-weight: 700; color: #475569; }
                    </style>
                </head>
                <body>
                    <div style="display:flex; justify-content:space-between; border-bottom: 2px solid #8b5cf6; padding-bottom: 12px;">
                        <div>
                            <h1>EVENTFLARE</h1>
                            <p style="margin:4px 0 0; color:#64748b; font-size:12px;">Official Supplier Partner Dossier</p>
                        </div>
                        <div style="text-align:right;">
                            <strong style="font-size:16px;">#SUP-${s.id}</strong><br>
                            <small>Printed: ${new Date().toLocaleDateString()}</small>
                        </div>
                    </div>

                    <div style="margin-top:20px; display:grid; grid-template-columns:1fr 1fr; gap:15px; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0;">
                        <div>
                            <strong>Business Name:</strong> ${s.business_name}<br>
                            <strong>Category:</strong> ${s.category || 'General'}<br>
                            <strong>Contact Person:</strong> ${s.fullname} (@${s.username})<br>
                        </div>
                        <div>
                            <strong>Direct Phone:</strong> ${s.contact_phone || '-'}<br>
                            <strong>Email:</strong> ${s.email}<br>
                            <strong>Operating Base:</strong> ${s.location || '-'}<br>
                        </div>
                    </div>

                    <h3>Profile Summary & Description</h3>
                    <p style="font-size:13px; color:#334155;">${s.description || 'No description recorded.'}</p>

                    <h3>Catalog Packages & Pricing</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Package Title</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>${listingsRows}</tbody>
                    </table>

                    <h3>Contracted Event Assignments</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Event & Date</th>
                                <th>Client</th>
                                <th>Component</th>
                                <th>Contracted Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>${bookingsRows}</tbody>
                    </table>
                </body>
                </html>
            `);
            win.document.close();
            win.focus();
            win.print();
            win.close();
        });

        // Auto-open dossier if ?view_id=X in URL
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const viewId = urlParams.get('view_id');
            if (viewId) {
                const targetBtn = document.querySelector(`.view-supplier-btn[data-supplier*='"id":${viewId},']`) 
                               || document.querySelector(`.view-supplier-btn[data-supplier*='"id":"${viewId}"']`);
                if (targetBtn) {
                    targetBtn.click();
                }
            }
        });

        window.onclick = function(e) {
            ['addSupplierModal', 'editSupplierModal', 'viewSupplierModal'].forEach(id => {
                const el = document.getElementById(id);
                if (e.target === el) closeModal(id);
            });
        }

        // PDF Export
        document.getElementById("downloadPdf").addEventListener("click", function () {
            const element = document.getElementById("makepdf");
            const opt = {
                margin:       10,
                filename:     'suppliers_directory.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });

        // CSV Export
        document.getElementById("downloadCsv").addEventListener("click", function () {
            const table = document.getElementById("suppliersTable");
            let csv = [];
            for (let row of table.rows) {
                let cols = [];
                for (let i = 0; i < row.cells.length - 1; i++) {
                    let text = row.cells[i].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                    cols.push('"' + text.replace(/"/g, '""') + '"');
                }
                csv.push(cols.join(","));
            }
            const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "suppliers_list.csv";
            link.click();
        });
    </script>
</body>
</html>
