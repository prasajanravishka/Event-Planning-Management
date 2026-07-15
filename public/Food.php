<?php
session_start();
include __DIR__ . '/../config/database.php';

// Initialize variables
$totalBudget = 10000;
$foodBudget = 3500;

$buffetQuantity = 150;
$buffetPrice = 10;
$beveragesQuantity = 100;
$beveragesPrice = 5;
$dessertsQuantity = 100;
$dessertsPrice = 3;
$snacksQuantity = 200;
$snacksPrice = 2;

$totalSpent = 0;
$remainingBudget = 0;
$variance = 0;

$success_message = "";
$error_message = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect input values
    $totalBudget = floatval($_POST['total-budget'] ?? 10000);
    $foodBudget = floatval($_POST['food-budget'] ?? 3500);

    $buffetQuantity = intval($_POST['buffet-quantity'] ?? 150);
    $buffetPrice = floatval($_POST['buffet-unit-price'] ?? 10);
    $beveragesQuantity = intval($_POST['beverages-quantity'] ?? 100);
    $beveragesPrice = floatval($_POST['beverages-unit-price'] ?? 5);
    $dessertsQuantity = intval($_POST['desserts-quantity'] ?? 100);
    $dessertsPrice = floatval($_POST['desserts-unit-price'] ?? 3);
    $snacksQuantity = intval($_POST['snacks-quantity'] ?? 200);
    $snacksPrice = floatval($_POST['snacks-unit-price'] ?? 2);

    // Perform Calculations
    $buffetCost = $buffetQuantity * $buffetPrice;
    $beveragesCost = $beveragesQuantity * $beveragesPrice;
    $dessertsCost = $dessertsQuantity * $dessertsPrice;
    $snacksCost = $snacksQuantity * $snacksPrice;

    $totalSpent = $buffetCost + $beveragesCost + $dessertsCost + $snacksCost;
    $remainingBudget = $foodBudget - $totalSpent;
    $variance = $foodBudget - $totalSpent;

    // Check if saving to database
    if (isset($_POST['save-to-db'])) {
        $stmt = $conn->prepare("INSERT INTO budgets (total_budget, food_budget, buffet_cost, beverages_cost, desserts_cost, snacks_cost, total_spent, remaining_budget, variance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ddddddddd", $totalBudget, $foodBudget, $buffetCost, $beveragesCost, $dessertsCost, $snacksCost, $totalSpent, $remainingBudget, $variance);

            if ($stmt->execute()) {
                $success_message = "Budget details computed and saved to database successfully!";
            } else {
                $error_message = "Error saving budget details: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Database error: " . $conn->error;
        }
    }
}
$conn->close();

include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Budget Calculator - EventEase</title>
    <style>
        .calculator-wrapper {
            max-width: 900px;
            margin: 40px auto 80px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 40px;
        }

        .calc-title h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .calc-title p {
            color: var(--text-muted);
            margin-bottom: 25px;
        }

        .budget-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 20px;
        }

        .category-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
            padding: 10px 0;
        }

        .category-title {
            grid-column: span 2;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-white);
            margin-bottom: -5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .action-buttons .btn {
            flex: 1;
            font-size: 14px;
            padding: 10px;
        }

        .results-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .metric-card h3 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .metric-card p {
            font-size: 28px;
            font-family: var(--font-heading);
            font-weight: 800;
            color: var(--text-white);
        }

        .metric-card.alert-success p {
            color: var(--success);
        }

        .metric-card.alert-danger p {
            color: var(--error);
        }

        @media (max-width: 768px) {
            .calculator-wrapper {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="calculator-wrapper">
        <!-- Input section -->
        <div class="glass-card calc-form-container">
            <div class="calc-title">
                <h1>Food Budget Calculator</h1>
                <p>Configure allocations, quantity demands, and pricing tiers to calculate food expenses.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success_message; ?></div>
                </div>
            <?php endif; ?>

            <form action="Food.php" method="POST">
                <div class="budget-inputs">
                    <div class="form-group">
                        <label class="form-label" for="total-budget">Total Event Budget ($)</label>
                        <input type="number" id="total-budget" class="form-input" name="total-budget" value="<?php echo $totalBudget; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="food-budget">Food Budget Limit ($)</label>
                        <input type="number" id="food-budget" class="form-input" name="food-budget" value="<?php echo $foodBudget; ?>" required>
                    </div>
                </div>

                <!-- Buffet -->
                <div class="category-row">
                    <div class="category-title">Buffet Service</div>
                    <div class="form-group">
                        <label class="form-label">Guests Quantity</label>
                        <input type="number" class="form-input" name="buffet-quantity" value="<?php echo $buffetQuantity; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit Price ($)</label>
                        <input type="number" step="0.01" class="form-input" name="buffet-unit-price" value="<?php echo $buffetPrice; ?>" required>
                    </div>
                </div>

                <!-- Beverages -->
                <div class="category-row">
                    <div class="category-title">Beverages</div>
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-input" name="beverages-quantity" value="<?php echo $beveragesQuantity; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit Price ($)</label>
                        <input type="number" step="0.01" class="form-input" name="beverages-unit-price" value="<?php echo $beveragesPrice; ?>" required>
                    </div>
                </div>

                <!-- Desserts -->
                <div class="category-row">
                    <div class="category-title">Desserts</div>
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-input" name="desserts-quantity" value="<?php echo $dessertsQuantity; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit Price ($)</label>
                        <input type="number" step="0.01" class="form-input" name="desserts-unit-price" value="<?php echo $dessertsPrice; ?>" required>
                    </div>
                </div>

                <!-- Snacks -->
                <div class="category-row">
                    <div class="category-title">Snacks</div>
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-input" name="snacks-quantity" value="<?php echo $snacksQuantity; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit Price ($)</label>
                        <input type="number" step="0.01" class="form-input" name="snacks-unit-price" value="<?php echo $snacksPrice; ?>" required>
                    </div>
                </div>

                <div class="action-buttons">
                    <button type="submit" name="calculate" class="btn btn-secondary">Calculate</button>
                    <button type="submit" name="save-to-db" class="btn btn-primary">Save to Database</button>
                </div>
            </form>
        </div>

        <!-- Output section -->
        <div class="results-panel">
            <div class="glass-card">
                <h2 style="font-size: 20px; margin-bottom: 20px; text-align: center;">Summary Report</h2>
                
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div class="metric-card">
                        <h3>Total Spent</h3>
                        <p>$<?php echo number_format($totalSpent, 2); ?></p>
                    </div>

                    <?php
                    $is_over = $remainingBudget < 0;
                    $class = $is_over ? 'alert-danger' : 'alert-success';
                    $label = $is_over ? 'Budget Deficit' : 'Remaining Budget';
                    ?>
                    <div class="metric-card <?php echo $class; ?>">
                        <h3><?php echo $label; ?></h3>
                        <p>$<?php echo number_format(abs($remainingBudget), 2); ?></p>
                    </div>

                    <div class="metric-card <?php echo $class; ?>">
                        <h3>Variance</h3>
                        <p><?php echo $is_over ? '-' : '+'; ?>$<?php echo number_format(abs($variance), 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>