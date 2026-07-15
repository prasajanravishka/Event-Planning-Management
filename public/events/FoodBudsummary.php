<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    header("Location: ../admin/Admin.php");
    exit();
}
include __DIR__ . '/../../config/database.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget List - EventEase</title>
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
        }

        .list-header h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .list-actions {
            display: flex;
            gap: 12px;
        }

        .pdf-content {
            background: #0d091e;
            padding: 30px;
            border-radius: 16px;
            border: 1px solid var(--card-border);
        }
    </style>
</head>
<body>
    <div class="list-wrapper">
        <div class="list-header">
            <h1>Budget Summaries</h1>
            <div class="list-actions">
                <button id="downloadPdf" class="btn btn-primary">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <a href="../Summary.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Console
                </a>
            </div>
        </div>

        <div class="pdf-content" id="makepdf">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Total Budget</th>
                            <th>Food Budget</th>
                            <th>Buffet Cost</th>
                            <th>Beverages Cost</th>
                            <th>Desserts Cost</th>
                            <th>Snacks Cost</th>
                            <th>Total Spent</th>
                            <th>Remaining</th>
                            <th>Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT id, total_budget, food_budget, buffet_cost, beverages_cost, 
                                       desserts_cost, snacks_cost, total_spent, remaining_budget, variance 
                                FROM budgets";
                        $result = $conn->query($sql);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                echo "<td>$" . number_format($row['total_budget'], 2) . "</td>";
                                echo "<td>$" . number_format($row['food_budget'], 2) . "</td>";
                                echo "<td>$" . number_format($row['buffet_cost'], 2) . "</td>";
                                echo "<td>$" . number_format($row['beverages_cost'], 2) . "</td>";
                                echo "<td>$" . number_format($row['desserts_cost'], 2) . "</td>";
                                echo "<td>$" . number_format($row['snacks_cost'], 2) . "</td>";
                                echo "<td>$" . number_format($row['total_spent'], 2) . "</td>";
                                echo "<td>$" . number_format($row['remaining_budget'], 2) . "</td>";
                                echo "<td>$" . number_format($row['variance'], 2) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='10' style='text-align:center;'>No budget logs found.</td></tr>";
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.getElementById("downloadPdf").addEventListener("click", function () {
            const element = document.getElementById("makepdf");
            const opt = {
                margin:       10,
                filename:     'budget_list.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#0d091e' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });
    </script>
</body>
</html>
