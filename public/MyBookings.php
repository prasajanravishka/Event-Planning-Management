<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    header("Location: Login.php");
    exit();
}
include __DIR__ . '/../config/database.php';

$user = $_SESSION['login_user'];
$user_id = $_SESSION['user_id'] ?? 0;
if (empty($user_id) && isset($conn)) {
    $u_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("s", $user);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($u_row = $u_res->fetch_assoc()) {
            $user_id = (int)$u_row['id'];
            $_SESSION['user_id'] = $user_id;
        }
        $u_stmt->close();
    }
}

// Handle Rating & Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    $booking_id = trim($_POST['booking_id'] ?? '');
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 5);
    $review_title = trim(htmlspecialchars($_POST['review_title'] ?? ''));
    $review_text = trim(htmlspecialchars($_POST['review_text'] ?? ''));

    if ($rating < 1) $rating = 1;
    if ($rating > 5) $rating = 5;

    if (!empty($booking_id) && $supplier_id > 0 && $user_id > 0) {
        $ins = $conn->prepare("
            INSERT INTO ratings (booking_id, user_id, supplier_id, rating, review_title, review_text, admin_status)
            VALUES (?, ?, ?, ?, ?, ?, 'approved')
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_title = VALUES(review_title), review_text = VALUES(review_text), admin_status = 'approved'
        ");
        if ($ins) {
            $ins->bind_param("siiiss", $booking_id, $user_id, $supplier_id, $rating, $review_title, $review_text);
            $ins->execute();
            $ins->close();
        }
    }
    header("Location: MyBookings.php?reviewed=1");
    exit();
}

include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - EVENTFLARE</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.js"></script>
    <style>
        .list-wrapper {
            max-width: 1200px;
            margin: 40px auto 80px;
            padding: 0 20px;
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .list-header h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-heading), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .list-actions {
            display: flex;
            gap: 12px;
        }

        .booking-card-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-premium);
        }

        .client-table {
            width: 100%;
            border-collapse: collapse;
        }

        .client-table th, .client-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .client-table th {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
        }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-confirmed, .status-completed { background: #dcfce7; color: #15803d; }
        .status-in_progress { background: #dbeafe; color: #1d4ed8; }
        .status-cancelled { background: #fee2e2; color: #b91c1c; }

        /* Client Inspection Modal */
        .client-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(6px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .client-modal.show {
            display: flex;
        }
        .client-modal-dialog {
            background: #ffffff;
            border-radius: 20px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .client-modal-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .client-modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .client-modal-footer {
            padding: 16px 24px;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(0,0,0,0.06);
        }

        /* Stepper inside client modal */
        .c-stepper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            background: #f8fafc;
            padding: 18px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
        }
        .c-stepper::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 35px;
            right: 35px;
            height: 3px;
            background: #e2e8f0;
            z-index: 1;
        }
        .c-step {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            flex: 1;
        }
        .c-step-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #ffffff;
            border: 3px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
        }
        .c-step.completed .c-step-circle {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }
        .c-step.active .c-step-circle {
            background: var(--primary);
            border-color: #c4b5fd;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.2);
        }
        .c-step-text {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
        }
        .c-step.completed .c-step-text { color: #065f46; }
        .c-step.active .c-step-text { color: var(--primary); font-weight: 700; }
    </style>
</head>
<body>
    <div class="list-wrapper">
        <div class="list-header">
            <div>
                <h1>My Reservations & Bookings</h1>
                <p style="color: var(--text-muted); margin-top: 5px;">View your booked event schedules, status confirmations, and details.</p>
            </div>
            <div class="list-actions">
                <button id="downloadPdf" class="btn btn-primary" style="padding: 10px 20px; border-radius: 30px;">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <a href="Slide.php" class="btn btn-secondary" style="padding: 10px 20px; border-radius: 30px;">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <div class="booking-card-container" id="makepdf">
            <div style="overflow-x: auto;">
                <table class="client-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Event Type</th>
                            <th>Location</th>
                            <th>Guests</th>
                            <th>Date & Time</th>
                            <th>Preferences</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $user = $_SESSION['login_user'];
                        $user_id = $_SESSION['user_id'] ?? 0;
                        if (empty($user_id) && isset($conn)) {
                            $u_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                            if ($u_stmt) {
                                $u_stmt->bind_param("s", $user);
                                $u_stmt->execute();
                                $u_res = $u_stmt->get_result();
                                if ($u_row = $u_res->fetch_assoc()) {
                                    $user_id = (int)$u_row['id'];
                                    $_SESSION['user_id'] = $user_id;
                                }
                                $u_stmt->close();
                            }
                        }

                        // Preload existing reviews submitted by this customer
                        $user_ratings_map = [];
                        $ur_stmt = $conn->prepare("SELECT id, booking_id, supplier_id, rating, review_title, review_text FROM ratings WHERE user_id = ?");
                        if ($ur_stmt) {
                            $ur_stmt->bind_param("i", $user_id);
                            $ur_stmt->execute();
                            $ur_res = $ur_stmt->get_result();
                            while ($ur = $ur_res->fetch_assoc()) {
                                $user_ratings_map[$ur['booking_id'] . '_' . $ur['supplier_id']] = $ur;
                            }
                            $ur_stmt->close();
                        }

                        // Preload assigned services & suppliers for client's bookings
                        $services_map = [];
                        $bs_stmt = $conn->prepare("
                            SELECT bs.id as bs_id, bs.booking_id, bs.service_id, bs.supplier_id, bs.listing_id,
                                   bs.custom_notes, bs.assigned_cost, bs.status as assignment_status,
                                   s.service_name, s.is_required,
                                   sup.business_name, sup.contact_phone, sup.location as supplier_location,
                                   sl.title as listing_title, sl.price_type
                            FROM booking_services bs
                            JOIN bookings b ON bs.booking_id = b.BookingID
                            JOIN services s ON bs.service_id = s.service_id
                            LEFT JOIN suppliers sup ON bs.supplier_id = sup.id
                            LEFT JOIN supplier_listings sl ON bs.listing_id = sl.listing_id
                            WHERE (b.user_name = ? OR (b.user_id IS NOT NULL AND b.user_id = ?))
                            ORDER BY s.priority_rank ASC, bs.id ASC
                        ");
                        if ($bs_stmt) {
                            $bs_stmt->bind_param("si", $user, $user_id);
                            $bs_stmt->execute();
                            $bs_res = $bs_stmt->get_result();
                            while ($bs_row = $bs_res->fetch_assoc()) {
                                $k = $bs_row['booking_id'] . '_' . $bs_row['supplier_id'];
                                $bs_row['user_rating'] = $user_ratings_map[$k] ?? null;
                                $services_map[$bs_row['booking_id']][] = $bs_row;
                            }
                            $bs_stmt->close();
                        }

                        $stmt = $conn->prepare("
                            SELECT b.*, ee.equipment, ee.food_style
                            FROM bookings b
                            LEFT JOIN event_extras ee ON b.BookingID = ee.booking_id
                            WHERE (b.user_name = ? OR (b.user_id IS NOT NULL AND b.user_id = ?))
                            ORDER BY b.EventDate DESC
                        ");
                        if ($stmt) {
                            $stmt->bind_param("si", $user, $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $st = $row['status'] ?: 'pending';
                                    $st_class = 'status-' . $st;
                                    $assigned_services = $services_map[$row['BookingID']] ?? [];
                                    echo "<tr>";
                                    echo "<td><strong>#" . htmlspecialchars($row['BookingID']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row['EventType']) . "</td>";
                                    echo "<td><i class='fas fa-map-marker-alt fa-xs' style='color:#ef4444;'></i> " . htmlspecialchars($row['Place']) . "</td>";
                                    echo "<td>" . (int)$row['NumberOfGuests'] . "</td>";
                                    echo "<td>" . date('M j, Y', strtotime($row['EventDate'])) . " (" . htmlspecialchars($row['DayNight']) . ")</td>";
                                    echo "<td>" . htmlspecialchars($row['FoodPreferences'] ?: 'Standard') . "</td>";
                                    echo "<td><span class='status-pill {$st_class}'>" . ucfirst(str_replace('_', ' ', $st)) . "</span></td>";
                                    echo "<td>
                                            <button type='button' class='btn btn-outline btn-sm client-view-btn' 
                                                    style='padding: 6px 14px; border-radius: 20px; border: 1px solid var(--primary); color: var(--primary); background: #fff; cursor: pointer; font-size: 12px; font-weight: 700;'
                                                    data-reservation='" . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . "'
                                                    data-services='" . htmlspecialchars(json_encode($assigned_services), ENT_QUOTES, 'UTF-8') . "'>
                                                <i class='fas fa-eye'></i> Inspect
                                            </button>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' style='text-align:center; padding: 40px; color: var(--text-muted);'>No bookings found for your account. <a href='Booking.php' style='color:var(--primary); font-weight:700;'>Book an event now</a></td></tr>";
                            }
                            $stmt->close();
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Client Detail Inspection Modal -->
    <div class="client-modal" id="clientDetailModal">
        <div class="client-modal-dialog">
            <div class="client-modal-header">
                <h3 id="cModalTitle" style="margin: 0; font-size: 18px; font-weight: 800; color: var(--text-heading);"></h3>
                <button type="button" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-muted);" onclick="closeClientModal()">&times;</button>
            </div>
            <div class="client-modal-body" id="cModalBody">
                <!-- Injected via JavaScript -->
            </div>
            <div class="client-modal-footer">
                <button type="button" id="cPrintVoucherBtn" style="padding: 8px 18px; border-radius: 20px; border: 1px solid var(--primary); color: var(--primary); background: #fff; font-weight: 700; cursor: pointer;">
                    <i class="fas fa-print"></i> Print Voucher Slip
                </button>
                <button type="button" style="padding: 8px 18px; border-radius: 20px; border: none; background: #e2e8f0; font-weight: 700; cursor: pointer;" onclick="closeClientModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal: Rate & Review Supplier -->
    <div class="client-modal" id="rateSupplierModal">
        <div class="client-modal-dialog" style="max-width: 520px;">
            <div class="client-modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: #fffbeb; color: #b45309; display: flex; align-items: center; justify-content: center; font-size: 16px; border: 1px solid #fde68a;">
                        <i class="fas fa-star" style="color: #f59e0b;"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--text-heading);">Rate & Review Supplier</h3>
                        <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);" id="rateSupplierSubtitle">Share your verified event experience</p>
                    </div>
                </div>
                <button type="button" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-muted);" onclick="closeRateModal()">&times;</button>
            </div>
            <form method="POST" action="MyBookings.php" id="rateSupplierForm">
                <input type="hidden" name="action" value="submit_rating">
                <input type="hidden" name="booking_id" id="rateBookingId">
                <input type="hidden" name="supplier_id" id="rateSupplierId">
                <input type="hidden" name="rating" id="rateScoreInput" value="5">

                <div class="client-modal-body" style="padding: 20px;">
                    <!-- Star Picker -->
                    <div style="text-align: center; margin-bottom: 20px; background: #f8fafc; padding: 16px; border-radius: 14px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 13px; font-weight: 700; color: var(--text-heading); margin-bottom: 8px;">
                            Overall Experience Score
                        </div>
                        <div id="starPickerContainer" style="display: inline-flex; gap: 8px; font-size: 32px; cursor: pointer;">
                            <i class="fas fa-star star-interactive" data-val="1" style="color: #f59e0b;"></i>
                            <i class="fas fa-star star-interactive" data-val="2" style="color: #f59e0b;"></i>
                            <i class="fas fa-star star-interactive" data-val="3" style="color: #f59e0b;"></i>
                            <i class="fas fa-star star-interactive" data-val="4" style="color: #f59e0b;"></i>
                            <i class="fas fa-star star-interactive" data-val="5" style="color: #f59e0b;"></i>
                        </div>
                        <div id="starScoreLabel" style="font-size: 13px; font-weight: 800; color: #b45309; margin-top: 6px;">
                            5 / 5 - Exceptional Quality
                        </div>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-heading); margin-bottom: 4px;">
                            Headline / Title (Optional)
                        </label>
                        <input type="text" name="review_title" id="rateReviewTitle" class="form-input" 
                               placeholder="e.g. Exceptional service and exquisite presentation!"
                               style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px;">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-heading); margin-bottom: 4px;">
                            Detailed Feedback & Comments
                        </label>
                        <textarea name="review_text" id="rateReviewText" class="form-input" rows="4"
                                  placeholder="Share how the vendor performed regarding punctuality, quality of deliverables, and communication..."
                                  style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px;"></textarea>
                    </div>
                </div>

                <div class="client-modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="btn btn-secondary" onclick="closeRateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 20px; border-radius: 20px; font-weight: 700;">
                        <i class="fas fa-paper-plane"></i> Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const cModal = document.getElementById('clientDetailModal');
        const cModalBody = document.getElementById('cModalBody');
        const cModalTitle = document.getElementById('cModalTitle');
        const cPrintBtn = document.getElementById('cPrintVoucherBtn');
        let activeReservation = null;
        let activeServices = [];

        document.querySelectorAll('.client-view-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const r = JSON.parse(btn.getAttribute('data-reservation'));
                const services = JSON.parse(btn.getAttribute('data-services') || '[]');
                activeReservation = r;
                activeServices = services;
                cModalTitle.innerText = `Reservation #${r.BookingID}`;

                const st = r.status || 'pending';
                const isCancelled = (st === 'cancelled');

                let pendingClass = 'completed';
                let confirmedClass = (st === 'confirmed' || st === 'in_progress' || st === 'completed') ? 'completed' : (st === 'pending' ? 'active' : '');
                let inProgClass = (st === 'in_progress' || st === 'completed') ? 'completed' : (st === 'confirmed' ? 'active' : '');
                let completedClass = (st === 'completed') ? 'completed' : (st === 'in_progress' ? 'active' : '');

                let stepperHtml = '';
                if (isCancelled) {
                    stepperHtml = `
                        <div style="background: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 14px; color: #b91c1c; font-weight: 700; text-align: center; margin-bottom: 20px;">
                            <i class="fas fa-times-circle"></i> This booking is currently CANCELLED. Contact support for reinstatement.
                        </div>
                    `;
                } else {
                    stepperHtml = `
                        <div class="c-stepper">
                            <div class="c-step ${pendingClass}">
                                <div class="c-step-circle"><i class="fas fa-file-alt"></i></div>
                                <div class="c-step-text">Submitted</div>
                            </div>
                            <div class="c-step ${confirmedClass}">
                                <div class="c-step-circle"><i class="fas fa-check"></i></div>
                                <div class="c-step-text">Confirmed</div>
                            </div>
                            <div class="c-step ${inProgClass}">
                                <div class="c-step-circle"><i class="fas fa-spinner"></i></div>
                                <div class="c-step-text">In Progress</div>
                            </div>
                            <div class="c-step ${completedClass}">
                                <div class="c-step-circle"><i class="fas fa-flag-checkered"></i></div>
                                <div class="c-step-text">Completed</div>
                            </div>
                        </div>
                    `;
                }

                let servicesHtml = '';
                if (services && services.length > 0) {
                    servicesHtml = `
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--primary); letter-spacing: 0.5px;">
                                    <i class="fas fa-handshake"></i> Booked Services & Contracted Suppliers (${services.length})
                                </span>
                            </div>
                            <div style="display: grid; gap: 10px;">
                                ${services.map(s => {
                                    let ratingActionHtml = '';
                                    if (s.supplier_id) {
                                        if (s.user_rating) {
                                            ratingActionHtml = `
                                                <div style="margin-top: 6px; display: inline-flex; align-items: center; gap: 6px; background: #fffbeb; border: 1px solid #fde68a; padding: 2px 10px; border-radius: 12px;">
                                                    <span style="color: #b45309; font-size: 11px; font-weight: 800;">
                                                        <i class="fas fa-star" style="color: #f59e0b;"></i> ${s.user_rating.rating}.0 / 5.0
                                                    </span>
                                                    <button type="button" style="background: none; border: none; color: var(--primary); font-size: 11px; font-weight: 700; cursor: pointer; text-decoration: underline; padding: 0;"
                                                            onclick="triggerRateSupplier('${r.BookingID}', ${s.supplier_id}, '${escapeJs(s.business_name || 'Specialist')}', ${s.user_rating.rating}, '${escapeJs(s.user_rating.review_title || '')}', '${escapeJs(s.user_rating.review_text || '')}')">
                                                        Edit Review
                                                    </button>
                                                </div>
                                            `;
                                        } else {
                                            ratingActionHtml = `
                                                <div style="margin-top: 6px;">
                                                    <button type="button" style="padding: 3px 10px; border-radius: 12px; background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
                                                            onclick="triggerRateSupplier('${r.BookingID}', ${s.supplier_id}, '${escapeJs(s.business_name || 'Specialist')}', 5, '', '')">
                                                        <i class="fas fa-star" style="color: #f59e0b;"></i> Rate Supplier
                                                    </button>
                                                </div>
                                            `;
                                        }
                                    }
                                    return `
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 10px 14px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                        <div>
                                            <div style="font-weight: 700; font-size: 13px; color: var(--text-heading);">${s.service_name}</div>
                                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                                <i class="fas fa-store fa-xs" style="color:var(--primary);"></i> <strong>${s.business_name || 'Assigned Specialist'}</strong>
                                                ${s.listing_title ? ` &bull; <span style="color:#2563eb;">${s.listing_title}</span>` : ''}
                                            </div>
                                            ${ratingActionHtml}
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 12px; font-weight: 600; color: #15803d;"><i class="fas fa-phone-alt fa-xs"></i> ${s.contact_phone || 'Direct coordination'}</div>
                                            <span class="status-pill status-${s.assignment_status || 'confirmed'}" style="font-size: 10px; padding: 2px 8px; margin-top: 3px; display:inline-block;">${(s.assignment_status || 'confirmed').toUpperCase()}</span>
                                        </div>
                                    </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    `;
                } else {
                    servicesHtml = `
                        <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 14px; margin-bottom: 16px; text-align: center; color: #64748b; font-size: 13px;">
                            <i class="fas fa-user-clock"></i> Specialists and suppliers are being assigned for your booking by our event managers.
                        </div>
                    `;
                }

                cModalBody.innerHTML = `
                    ${stepperHtml}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div style="background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <small style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;">EVENT CATEGORY & TIMING</small>
                            <div style="font-weight: 700; font-size: 15px; color: var(--text-heading);">${r.EventType}</div>
                            <div style="font-size: 13px; color: #64748b; margin-top: 2px;">Timing: ${r.DayNight} Event</div>
                        </div>
                        <div style="background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <small style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;">RESERVED DATE</small>
                            <div style="font-weight: 700; font-size: 15px; color: var(--primary);">${r.EventDate}</div>
                            <div style="font-size: 13px; color: #64748b; margin-top: 2px;">Headcount: ${r.NumberOfGuests} Guests</div>
                        </div>
                    </div>

                    <div style="background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 16px;">
                        <small style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;">VENUE / DESTINATION</small>
                        <div style="font-weight: 600; font-size: 14px; color: var(--text-heading);"><i class="fas fa-map-marker-alt fa-xs" style="color: #ef4444;"></i> ${r.Place}</div>
                    </div>

                    ${servicesHtml}

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div style="background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <small style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;">FOOD & CATERING</small>
                            <div style="font-weight: 600; font-size: 13px;">${r.FoodPreferences || 'Standard Catering'}</div>
                            <small style="color: #64748b;">${r.food_style ? `Style: ${r.food_style}` : ''}</small>
                        </div>
                        <div style="background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <small style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;">EQUIPMENT SETUP</small>
                            <div style="font-weight: 600; font-size: 13px;">${r.equipment || 'Standard House Package'}</div>
                        </div>
                    </div>

                    <div style="background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <small style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px;">SPECIAL REQUESTS & INSTRUCTIONS</small>
                        <div style="font-size: 13px; line-height: 1.5; color: var(--text-heading);">${r.ExtraDetails || 'None submitted.'}</div>
                    </div>
                `;
                cModal.classList.add('show');
            });
        });

        function closeClientModal() {
            cModal.classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target === cModal) closeClientModal();
        }

        cPrintBtn.addEventListener('click', () => {
            if (!activeReservation) return;
            const r = activeReservation;
            const services = activeServices || [];

            let servicesRows = '';
            if (services.length > 0) {
                servicesRows = `
                    <h3 style="margin-top:25px; margin-bottom:10px; font-size:15px; color:#1e293b;">Booked Services & Assigned Suppliers</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Service Category</th>
                                <th>Supplier / Vendor</th>
                                <th>Selected Package</th>
                                <th>Supplier Phone</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${services.map(s => `
                                <tr>
                                    <td><strong>${s.service_name}</strong></td>
                                    <td>${s.business_name || 'Specialist'}</td>
                                    <td>${s.listing_title || s.custom_notes || '-'}</td>
                                    <td>${s.contact_phone || '-'}</td>
                                    <td>${(s.assignment_status || 'confirmed').toUpperCase()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }

            const win = window.open('', '', 'width=850,height=650');
            win.document.write(`
                <html>
                <head>
                    <title>Voucher - #${r.BookingID}</title>
                    <style>
                        body { font-family: sans-serif; padding: 30px; color: #1e293b; }
                        h1 { color: #6d28d9; margin: 0; font-size: 24px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                        th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; font-size: 13px; }
                        th { background: #f8fafc; font-weight: 700; color: #475569; }
                    </style>
                </head>
                <body>
                    <div style="display:flex; justify-content:space-between; border-bottom: 2px solid #8b5cf6; padding-bottom: 10px;">
                        <div>
                            <h1>EVENTFLARE</h1>
                            <p style="margin:4px 0 0; color:#64748b; font-size:12px;">Official Booking Voucher & Service Itinerary</p>
                        </div>
                        <div style="text-align:right;">
                            <strong>#${r.BookingID}</strong><br>
                            <small>Status: ${(r.status || 'PENDING').toUpperCase()}</small>
                        </div>
                    </div>
                    <table>
                        <tr><th style="width:25%;">Event Category</th><td>${r.EventType} (${r.DayNight})</td></tr>
                        <tr><th>Date & Venue</th><td>${r.EventDate} at ${r.Place}</td></tr>
                        <tr><th>Guests</th><td>${r.NumberOfGuests} Persons</td></tr>
                        <tr><th>Catering</th><td>${r.FoodPreferences || 'Standard'}</td></tr>
                        <tr><th>Equipment</th><td>${r.equipment || 'Standard House Package'}</td></tr>
                        <tr><th>Notes</th><td>${r.ExtraDetails || 'None'}</td></tr>
                    </table>
                    ${servicesRows}
                </body>
                </html>
            `);
            win.document.close();
            win.focus();
            win.print();
            win.close();
        });

        document.getElementById("downloadPdf").addEventListener("click", function () {
            const element = document.getElementById("makepdf");
            const opt = {
                margin:       10,
                filename:     'my_bookings.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });

        // Rating & Review Modal Logic
        const rateModal = document.getElementById('rateSupplierModal');
        const rateScoreInput = document.getElementById('rateScoreInput');
        const starScoreLabel = document.getElementById('starScoreLabel');
        const starItems = document.querySelectorAll('.star-interactive');

        const scoreDescriptions = {
            1: '1 / 5 - Poor Quality / Unsatisfied',
            2: '2 / 5 - Fair / Below Expectations',
            3: '3 / 5 - Good / Standard Delivery',
            4: '4 / 5 - Great / Highly Recommended',
            5: '5 / 5 - Exceptional Quality & Execution'
        };

        function setInteractiveRating(score) {
            rateScoreInput.value = score;
            starScoreLabel.innerText = scoreDescriptions[score] || `${score} / 5`;
            starItems.forEach(star => {
                const val = parseInt(star.getAttribute('data-val'));
                if (val <= score) {
                    star.className = 'fas fa-star star-interactive';
                    star.style.color = '#f59e0b';
                } else {
                    star.className = 'far fa-star star-interactive';
                    star.style.color = '#cbd5e1';
                }
            });
        }

        starItems.forEach(star => {
            star.addEventListener('click', () => {
                const val = parseInt(star.getAttribute('data-val'));
                setInteractiveRating(val);
            });
            star.addEventListener('mouseenter', () => {
                const val = parseInt(star.getAttribute('data-val'));
                starItems.forEach(s => {
                    const sv = parseInt(s.getAttribute('data-val'));
                    s.style.color = (sv <= val) ? '#f59e0b' : '#cbd5e1';
                });
            });
            star.addEventListener('mouseleave', () => {
                const current = parseInt(rateScoreInput.value);
                setInteractiveRating(current);
            });
        });

        function triggerRateSupplier(bookingId, supplierId, supplierName, rating = 5, title = '', text = '') {
            document.getElementById('rateBookingId').value = bookingId;
            document.getElementById('rateSupplierId').value = supplierId;
            document.getElementById('rateSupplierSubtitle').innerText = `Reviewing: ${supplierName} (Booking #${bookingId})`;
            document.getElementById('rateReviewTitle').value = title;
            document.getElementById('rateReviewText').value = text;
            setInteractiveRating(parseInt(rating) || 5);

            rateModal.classList.add('show');
        }

        function closeRateModal() {
            rateModal.classList.remove('show');
        }

        function escapeJs(str) {
            if (!str) return '';
            return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n');
        }

        window.addEventListener('click', (e) => {
            if (e.target === rateModal) closeRateModal();
        });

        if (window.location.search.includes('reviewed=1')) {
            const toast = document.createElement('div');
            toast.style.cssText = 'position:fixed; bottom:25px; right:25px; background:#10b981; color:#fff; padding:14px 22px; border-radius:12px; font-weight:700; box-shadow:0 10px 25px rgba(0,0,0,0.15); z-index:99999; display:flex; align-items:center; gap:10px; font-size:14px;';
            toast.innerHTML = '<i class="fas fa-check-circle fa-lg"></i> Thank you! Your review has been published.';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4500);
        }
    </script>
</body>
</html>
