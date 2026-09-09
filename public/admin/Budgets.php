<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_once __DIR__ . '/../../config/database.php';

$active_page = 'budgets';

// Aggregated financial metrics
$totals = [
    'total_budget' => 0.0,
    'food_budget' => 0.0,
    'total_spent' => 0.0,
    'remaining' => 0.0,
    'variance' => 0.0,
    'count' => 0
];

$sum_query = $conn->query("SELECT COUNT(*) as c, SUM(total_budget) as tb, SUM(food_budget) as fb, SUM(total_spent) as ts, SUM(remaining_budget) as rb, SUM(variance) as vr FROM budgets");
if ($sum_query && $r = $sum_query->fetch_assoc()) {
    $totals['count'] = (int)$r['c'];
    $totals['total_budget'] = (float)($r['tb'] ?? 0);
    $totals['food_budget'] = (float)($r['fb'] ?? 0);
    $totals['total_spent'] = (float)($r['ts'] ?? 0);
    $totals['remaining'] = (float)($r['rb'] ?? 0);
    $totals['variance'] = (float)($r['vr'] ?? 0);
}

// Fetch all budget logs
$sql = "SELECT id, user_name, booking_id, total_budget, food_budget, buffet_cost, beverages_cost, desserts_cost, snacks_cost, total_spent, remaining_budget, variance, created_at 
        FROM budgets 
        ORDER BY id DESC";
$result = $conn->query($sql);
$budgets = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $budgets[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial & Budget Audit - EVENTFLARE Admin</title>
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
                <h1>Financial & Budget Console</h1>
                <p>Monitor event food calculations, budget variances, and overall financial logs.</p>
            </div>
            <div class="dashboard-user">
                <i class="fas fa-user-shield"></i>
                <span><?= htmlspecialchars($_SESSION['login_user']); ?> (Admin)</span>
            </div>
        </div>

        <?= render_flash_message(); ?>

        <!-- Key Financial Totals -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-wallet"></i></div>
                <div class="stat-info">
                    <h3>$<?= number_format($totals['total_budget'], 2); ?></h3>
                    <p>Total Managed Budget</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="fas fa-utensils"></i></div>
                <div class="stat-info">
                    <h3>$<?= number_format($totals['food_budget'], 2); ?></h3>
                    <p>Allocated Food Budget</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-receipt"></i></div>
                <div class="stat-info">
                    <h3>$<?= number_format($totals['total_spent'], 2); ?></h3>
                    <p>Total Spent</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-orange"><i class="fas fa-balance-scale"></i></div>
                <div class="stat-info">
                    <h3>$<?= number_format($totals['variance'], 2); ?></h3>
                    <p>Net Financial Variance</p>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="dashboard-toolbar">
            <div style="font-size: 14px; font-weight: 600; color: var(--text-heading);">
                <?= count($budgets); ?> budget calculation logs recorded
            </div>
            <div class="toolbar-actions" style="display:flex; gap: 10px;">
                <button id="downloadPdf" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button id="downloadCsv" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Budget Table Panel -->
        <div class="dashboard-panel" id="makepdf">
            <div class="dashboard-table-container">
                <table class="dashboard-table" id="budgetsTable">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Total Budget</th>
                            <th>Food Budget</th>
                            <th>Buffet Cost</th>
                            <th>Beverages</th>
                            <th>Desserts</th>
                            <th>Snacks</th>
                            <th>Total Spent</th>
                            <th>Remaining</th>
                            <th>Variance</th>
                            <th>Date Logged</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($budgets)): ?>
                            <?php foreach ($budgets as $b): ?>
                                <tr>
                                    <td><strong>#<?= $b['id']; ?></strong></td>
                                    <td><strong>$<?= number_format((float)$b['total_budget'], 2); ?></strong></td>
                                    <td>$<?= number_format((float)$b['food_budget'], 2); ?></td>
                                    <td>$<?= number_format((float)$b['buffet_cost'], 2); ?></td>
                                    <td>$<?= number_format((float)$b['beverages_cost'], 2); ?></td>
                                    <td>$<?= number_format((float)$b['desserts_cost'], 2); ?></td>
                                    <td>$<?= number_format((float)$b['snacks_cost'], 2); ?></td>
                                    <td><strong style="color: var(--primary);">$<?= number_format((float)$b['total_spent'], 2); ?></strong></td>
                                    <td>
                                        <span style="color: <?= ((float)$b['remaining_budget'] < 0) ? '#dc2626' : '#16a34a'; ?>; font-weight: 700;">
                                            $<?= number_format((float)$b['remaining_budget'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: <?= ((float)$b['variance'] < 0) ? '#dc2626' : '#16a34a'; ?>; font-weight: 600;">
                                            $<?= number_format((float)$b['variance'], 2); ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($b['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-file-invoice-dollar" style="font-size: 32px; display: block; margin-bottom: 10px; color: #cbd5e1;"></i>
                                    No budget calculation logs found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // PDF Export
        document.getElementById("downloadPdf").addEventListener("click", function () {
            const element = document.getElementById("makepdf");
            const opt = {
                margin:       10,
                filename:     'budget_summaries.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });

        // CSV Export
        document.getElementById("downloadCsv").addEventListener("click", function () {
            const table = document.getElementById("budgetsTable");
            let csv = [];
            for (let row of table.rows) {
                let cols = [];
                for (let i = 0; i < row.cells.length; i++) {
                    let text = row.cells[i].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                    cols.push('"' + text.replace(/"/g, '""') + '"');
                }
                csv.push(cols.join(","));
            }
            const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "budget_logs.csv";
            link.click();
        });
    </script>
</body>
</html>
