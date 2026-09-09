<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'suppliers';

// 1. Handle Create / Update / Toggle / Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Security token invalid.');
        header('Location: SupplierListings.php');
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_listing' || $action === 'update_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $service_id = (int)($_POST['service_id'] ?? 0);
        $title = trim(htmlspecialchars($_POST['title'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $price_type = $_POST['price_type'] ?? 'total_package';
        $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
        $image_url = trim($_POST['image_url'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $allowed_price_types = ['total_package', 'per_person', 'per_hour', 'per_day'];
        if (!in_array($price_type, $allowed_price_types)) {
            $price_type = 'total_package';
        }

        if (empty($title) || $supplier_id <= 0 || $service_id <= 0) {
            set_flash_message('error', 'Supplier, service category, and listing title are required.');
        } elseif ($price < 0) {
            set_flash_message('error', 'Price cannot be negative.');
        } else {
            if ($action === 'create_listing') {
                $ins = $conn->prepare("INSERT INTO supplier_listings (supplier_id, service_id, title, description, price, price_type, capacity, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("iissssdss", $supplier_id, $service_id, $title, $description, $price, $price_type, $capacity, $image_url, $status);
                if ($ins->execute()) {
                    set_flash_message('success', "New listing '{$title}' created successfully.");
                } else {
                    set_flash_message('error', "Failed to create listing: " . $ins->error);
                }
                $ins->close();
            } else {
                $upd = $conn->prepare("UPDATE supplier_listings SET supplier_id = ?, service_id = ?, title = ?, description = ?, price = ?, price_type = ?, capacity = ?, image_url = ?, status = ? WHERE listing_id = ?");
                $upd->bind_param("iissssdssi", $supplier_id, $service_id, $title, $description, $price, $price_type, $capacity, $image_url, $status, $listing_id);
                if ($upd->execute()) {
                    set_flash_message('success', "Listing #{$listing_id} updated successfully.");
                } else {
                    set_flash_message('error', "Failed to update listing: " . $upd->error);
                }
                $upd->close();
            }
        }
    } elseif ($action === 'toggle_status') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        $toggle_stmt = $conn->prepare("UPDATE supplier_listings SET status = IF(status = 'active', 'inactive', 'active') WHERE listing_id = ?");
        $toggle_stmt->bind_param("i", $listing_id);
        if ($toggle_stmt->execute()) {
            set_flash_message('success', "Listing #{$listing_id} availability status updated.");
        } else {
            set_flash_message('error', "Failed to update availability.");
        }
        $toggle_stmt->close();
    } elseif ($action === 'delete_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        $del_stmt = $conn->prepare("DELETE FROM supplier_listings WHERE listing_id = ?");
        $del_stmt->bind_param("i", $listing_id);
        if ($del_stmt->execute()) {
            set_flash_message('success', "Listing #{$listing_id} has been permanently deleted.");
        } else {
            set_flash_message('error', "Failed to delete listing.");
        }
        $del_stmt->close();
    }

    $ret_supp = (int)($_POST['supplier_id'] ?? 0);
    $redirect_url = 'SupplierListings.php' . ($ret_supp > 0 ? '?supplier_id=' . $ret_supp : '');
    header('Location: ' . $redirect_url);
    exit();
}

// 2. Fetch Suppliers and Services for Dropdowns
$all_suppliers = [];
$s_res = $conn->query("SELECT id, business_name FROM suppliers ORDER BY business_name ASC");
if ($s_res) {
    while ($r = $s_res->fetch_assoc()) {
        $all_suppliers[] = $r;
    }
}

$all_services = [];
$svc_res = $conn->query("SELECT s.service_id, s.service_name, et.type_name FROM services s JOIN event_types et ON s.event_type_id = et.event_type_id ORDER BY et.type_name, s.service_name ASC");
if ($svc_res) {
    while ($r = $svc_res->fetch_assoc()) {
        $all_services[] = $r;
    }
}

// 3. Search & Filter Parameters
$filter_supplier = (int)($_GET['supplier_id'] ?? 0);
$filter_status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_supplier > 0) {
    $where_clauses[] = "sl.supplier_id = ?";
    $params[] = &$filter_supplier;
    $types .= "i";
}

if (!empty($filter_status)) {
    $where_clauses[] = "sl.status = ?";
    $params[] = &$filter_status;
    $types .= "s";
}

if (!empty($search)) {
    $where_clauses[] = "(sl.title LIKE ? OR sl.description LIKE ? OR sp.business_name LIKE ?)";
    $like = "%{$search}%";
    $params[] = &$like;
    $params[] = &$like;
    $params[] = &$like;
    $types .= "sss";
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch listings
$sql = "SELECT sl.*, sp.business_name, sp.category AS supplier_category, s.service_name, et.type_name
        FROM supplier_listings sl
        JOIN suppliers sp ON sl.supplier_id = sp.id
        JOIN services s ON sl.service_id = s.service_id
        JOIN event_types et ON s.event_type_id = et.event_type_id
        WHERE {$where_sql}
        ORDER BY sl.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Listings Control - EVENTFLARE Admin</title>
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
                <h1>Supplier Listings & Packages</h1>
                <p>Administrative authority to view, create, edit, toggle availability, and remove vendor listings.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <?= render_flash_message(); ?>

        <!-- Toolbar -->
        <div class="dashboard-toolbar">
            <form method="GET" action="SupplierListings.php" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                <div class="toolbar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search package title, description..." value="<?= htmlspecialchars($search); ?>">
                </div>

                <select name="supplier_id" class="toolbar-select" onchange="this.form.submit()">
                    <option value="">All Suppliers</option>
                    <?php foreach ($all_suppliers as $supp): ?>
                        <option value="<?= $supp['id']; ?>" <?= ($filter_supplier === (int)$supp['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($supp['business_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="toolbar-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?= ($filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?= ($filter_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if (!empty($search) || $filter_supplier > 0 || !empty($filter_status)): ?>
                    <a href="SupplierListings.php" class="btn btn-outline btn-sm">Reset</a>
                <?php endif; ?>
            </form>

            <div class="toolbar-actions" style="display: flex; gap: 10px; align-items: center;">
                <a href="Suppliers.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-arrow-left"></i> Suppliers Directory
                </a>
                <button type="button" class="btn btn-primary btn-sm" id="openAddListingBtn">
                    <i class="fas fa-plus-circle"></i> + Add Listing
                </button>
                <button id="downloadPdf" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button id="downloadCsv" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
            </div>
        </div>

        <!-- Listings Table Panel -->
        <div class="dashboard-panel" id="makepdf">
            <div class="dashboard-table-container">
                <table class="dashboard-table" id="listingsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Package Title</th>
                            <th>Supplier</th>
                            <th>Service Category</th>
                            <th>Price</th>
                            <th>Model</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($listings)): ?>
                            <?php foreach ($listings as $l): ?>
                                <?php $is_act = ($l['status'] === 'active'); ?>
                                <tr>
                                    <td><strong>#<?= $l['listing_id']; ?></strong></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-heading);"><?= htmlspecialchars($l['title']); ?></div>
                                        <small style="color: var(--text-muted);"><?= substr(htmlspecialchars($l['description'] ?? ''), 0, 60); ?>...</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <i class="fas fa-store fa-xs"></i> <?= htmlspecialchars($l['business_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($l['service_name']); ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($l['type_name']); ?></small>
                                    </td>
                                    <td><strong>$<?= number_format((float)$l['price'], 2); ?></strong></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $l['price_type'])); ?></td>
                                    <td><?= !empty($l['capacity']) ? (int)$l['capacity'] . ' Guests' : '&mdash;'; ?></td>
                                    <td>
                                        <span class="badge <?= $is_act ? 'badge-success' : 'badge-danger'; ?>">
                                            <?= ucfirst($l['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <!-- Toggle Availability Status -->
                                            <form method="POST" action="SupplierListings.php" style="display:inline;">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="listing_id" value="<?= $l['listing_id']; ?>">
                                                <input type="hidden" name="supplier_id" value="<?= $filter_supplier; ?>">
                                                <button type="submit" class="btn btn-outline btn-sm" title="Toggle Active / Inactive">
                                                    <i class="fas <?= $is_act ? 'fa-eye-slash' : 'fa-eye'; ?>"></i> <?= $is_act ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>

                                            <!-- Edit Listing Button -->
                                            <button type="button" class="btn btn-outline btn-sm edit-listing-btn"
                                                    data-listing='<?= htmlspecialchars(json_encode($l), ENT_QUOTES, 'UTF-8'); ?>'
                                                    title="Edit Package Details">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <!-- Delete Listing Button -->
                                            <form method="POST" action="SupplierListings.php" style="display:inline;" onsubmit="return confirm('Permanently delete listing \'<?= htmlspecialchars(addslashes($l['title'])); ?>\'?');">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_listing">
                                                <input type="hidden" name="listing_id" value="<?= $l['listing_id']; ?>">
                                                <input type="hidden" name="supplier_id" value="<?= $filter_supplier; ?>">
                                                <button type="submit" class="btn btn-danger-outline btn-sm" title="Delete Listing">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-boxes" style="font-size: 32px; display: block; margin-bottom: 10px; color: #cbd5e1;"></i>
                                    No supplier listings found matching your search.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal: Add / Edit Listing -->
    <div class="admin-modal" id="listingModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="modalHeading"><i class="fas fa-box" style="color: var(--primary);"></i> Add Supplier Listing</h3>
                <button type="button" class="modal-close" onclick="closeModal('listingModal')">&times;</button>
            </div>
            <form method="POST" action="SupplierListings.php" id="listingForm">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" id="formAction" value="create_listing">
                <input type="hidden" name="listing_id" id="listingId" value="0">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Target Supplier *</label>
                            <select name="supplier_id" id="modalSupplierSelect" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);" required>
                                <option value="" disabled selected>-- Select Vendor --</option>
                                <?php foreach ($all_suppliers as $s): ?>
                                    <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['business_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Service Category *</label>
                            <select name="service_id" id="modalServiceSelect" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);" required>
                                <option value="" disabled selected>-- Select Service --</option>
                                <?php foreach ($all_services as $svc): ?>
                                    <option value="<?= $svc['service_id']; ?>">
                                        <?= htmlspecialchars($svc['type_name']); ?> &rarr; <?= htmlspecialchars($svc['service_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Package / Listing Title *</label>
                        <input type="text" name="title" id="modalTitleInput" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. Grand Ballroom Reception Package" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Price ($) *</label>
                            <input type="number" step="0.01" min="0" name="price" id="modalPriceInput" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);" required>
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Pricing Model *</label>
                            <select name="price_type" id="modalPriceTypeSelect" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);" required>
                                <option value="total_package">Total Package (Flat Rate)</option>
                                <option value="per_person">Per Person / Plate</option>
                                <option value="per_day">Per Day (Venue / Space)</option>
                                <option value="per_hour">Per Hour (DJs / Music)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Guest Capacity (Optional)</label>
                            <input type="number" min="1" name="capacity" id="modalCapacityInput" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="e.g. 300">
                        </div>
                        <div>
                            <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Availability Status</label>
                            <select name="status" id="modalStatusSelect" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);">
                                <option value="active">Active (Visible)</option>
                                <option value="inactive">Inactive (Hidden)</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Cover Image URL (Optional)</label>
                        <input type="url" name="image_url" id="modalImageInput" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border);" placeholder="https://example.com/photos/item.jpg">
                    </div>

                    <div>
                        <label class="form-label" style="display:block; font-size: 13px; font-weight:600; margin-bottom:4px;">Package Inclusions & Description</label>
                        <textarea name="description" id="modalDescInput" class="form-input" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--card-border); min-height:80px;" placeholder="Details included with this package..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('listingModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Listing</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }

        // Open Add Modal
        document.getElementById('openAddListingBtn').addEventListener('click', () => {
            document.getElementById('formAction').value = 'create_listing';
            document.getElementById('listingId').value = '0';
            document.getElementById('modalHeading').innerHTML = '<i class="fas fa-box" style="color: var(--primary);"></i> Add Supplier Listing';
            document.getElementById('modalSupplierSelect').value = '<?= $filter_supplier ?: ''; ?>';
            document.getElementById('modalServiceSelect').value = '';
            document.getElementById('modalTitleInput').value = '';
            document.getElementById('modalPriceInput').value = '';
            document.getElementById('modalPriceTypeSelect').value = 'total_package';
            document.getElementById('modalCapacityInput').value = '';
            document.getElementById('modalStatusSelect').value = 'active';
            document.getElementById('modalImageInput').value = '';
            document.getElementById('modalDescInput').value = '';
            openModal('listingModal');
        });

        // Edit Listing Click
        document.querySelectorAll('.edit-listing-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const l = JSON.parse(btn.getAttribute('data-listing'));
                document.getElementById('formAction').value = 'update_listing';
                document.getElementById('listingId').value = l.listing_id;
                document.getElementById('modalHeading').innerHTML = '<i class="fas fa-edit" style="color: var(--primary);"></i> Edit Listing #' + l.listing_id;
                document.getElementById('modalSupplierSelect').value = l.supplier_id;
                document.getElementById('modalServiceSelect').value = l.service_id;
                document.getElementById('modalTitleInput').value = l.title || '';
                document.getElementById('modalPriceInput').value = l.price || '0';
                document.getElementById('modalPriceTypeSelect').value = l.price_type || 'total_package';
                document.getElementById('modalCapacityInput').value = l.capacity || '';
                document.getElementById('modalStatusSelect').value = l.status || 'active';
                document.getElementById('modalImageInput').value = l.image_url || '';
                document.getElementById('modalDescInput').value = l.description || '';
                openModal('listingModal');
            });
        });

        window.onclick = function(e) {
            const m = document.getElementById('listingModal');
            if (e.target === m) closeModal('listingModal');
        }

        // PDF Export
        document.getElementById("downloadPdf").addEventListener("click", function () {
            const element = document.getElementById("makepdf");
            const opt = {
                margin:       10,
                filename:     'supplier_listings.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });

        // CSV Export
        document.getElementById("downloadCsv").addEventListener("click", function () {
            const table = document.getElementById("listingsTable");
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
            link.download = "supplier_listings.csv";
            link.click();
        });
    </script>
</body>
</html>
