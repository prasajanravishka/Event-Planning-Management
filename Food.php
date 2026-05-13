<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize variables
$totalBudget = 0;
$foodBudget = 0;
$buffetCost = 0;
$beveragesCost = 0;
$dessertsCost = 0;
$snacksCost = 0;
$totalSpent = 0;
$remainingBudget = 0;
$variance = 0;

// Database connection details
$servername = "localhost";
$username = "root"; // Default username for MySQL
$password = ""; // Default password for MySQL
$dbname = "login"; // Database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Connected to the database successfully!<br>";
}

// Check if form is submitted for calculation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "Form submitted!<br>";
    if (isset($_POST['calculate'])) {
        echo "Calculate button clicked!<br>";
        // Get the total budget and food budget
        $totalBudget = $_POST['total-budget'];
        $foodBudget = $_POST['food-budget'];

        // Get the quantities and prices for each category
        $buffetQuantity = $_POST['buffet-quantity'];
        $buffetPrice = $_POST['buffet-unit-price'];
        $beveragesQuantity = $_POST['beverages-quantity'];
        $beveragesPrice = $_POST['beverages-unit-price'];
        $dessertsQuantity = $_POST['desserts-quantity'];
        $dessertsPrice = $_POST['desserts-unit-price'];
        $snacksQuantity = $_POST['snacks-quantity'];
        $snacksPrice = $_POST['snacks-unit-price'];

        // Calculate costs for each category
        $buffetCost = $buffetQuantity * $buffetPrice;
        $beveragesCost = $beveragesQuantity * $beveragesPrice;
        $dessertsCost = $dessertsQuantity * $dessertsPrice;
        $snacksCost = $snacksQuantity * $snacksPrice;

        // Calculate total spent and remaining budget
        $totalSpent = $buffetCost + $beveragesCost + $dessertsCost + $snacksCost;
        $remainingBudget = $foodBudget - $totalSpent;
        $variance = $foodBudget - $totalSpent;
    }

    // Check if form is submitted for saving to database
    if (isset($_POST['save-to-db'])) {
        echo "Save to Database button clicked!<br>";
        echo "Total Budget: $totalBudget<br>";
        echo "Food Budget: $foodBudget<br>";
        echo "Buffet Cost: $buffetCost<br>";
        echo "Beverages Cost: $beveragesCost<br>";
        echo "Desserts Cost: $dessertsCost<br>";
        echo "Snacks Cost: $snacksCost<br>";
        echo "Total Spent: $totalSpent<br>";
        echo "Remaining Budget: $remainingBudget<br>";
        echo "Variance: $variance<br>";

        // Insert data into the database
        $stmt = $conn->prepare("INSERT INTO budgets (total_budget, food_budget, buffet_cost, beverages_cost, desserts_cost, snacks_cost, total_spent, remaining_budget, variance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ddddddddd", $totalBudget, $foodBudget, $buffetCost, $beveragesCost, $dessertsCost, $snacksCost, $totalSpent, $remainingBudget, $variance);

        if ($stmt->execute()) {
            echo "<p style='color: green;'>Data saved to database successfully!</p>";
        } else {
            echo "<p style='color: red;'>Error saving data to database: " . $stmt->error . "</p>";
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Food Budget Calculator</title>
    <link rel="stylesheet" href="Food.css">
    <style>
        /* Additional CSS for button alignment */
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .button-container button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .button-container button[type="submit"] {
            background-color: #4CAF50;
            color: white;
        }
        .button-container button[type="submit"]:hover {
            background-color: #45a049;
        }
        .button-container button[type="reset"] {
            background-color: #f44336;
            color: white;
        }
        .button-container button[type="reset"]:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h1>Event Food Budget Calculator</h1>

    <!-- Form to input data -->
    <form action="" method="POST">
        <div>
            <label for="total-budget">Total Event Budget:</label>
            <input type="number" id="total-budget" name="total-budget" placeholder="10,000" value="<?= isset($totalBudget) ? $totalBudget : 10000 ?>" required>
        </div>

        <div>
            <label for="food-budget">Food & Beverage Budget Allocation:</label>
            <input type="number" id="food-budget" name="food-budget" placeholder="3,500" value="<?= isset($foodBudget) ? $foodBudget : 3500 ?>" required>
        </div>

        <!-- Food Categories -->
        <h3>Food Categories:</h3>

        <div class="category">
            <label for="buffet-quantity">Buffet Quantity:</label>
            <input type="number" id="buffet-quantity" name="buffet-quantity" value="<?= isset($buffetQuantity) ? $buffetQuantity : 150 ?>" required>
            <label for="buffet-unit-price">Buffet Unit Price:</label>
            <input type="number" id="buffet-unit-price" name="buffet-unit-price" value="<?= isset($buffetPrice) ? $buffetPrice : 10 ?>" required>
        </div>

        <div class="category">
            <label for="beverages-quantity">Beverages Quantity:</label>
            <input type="number" id="beverages-quantity" name="beverages-quantity" value="<?= isset($beveragesQuantity) ? $beveragesQuantity : 100 ?>" required>
            <label for="beverages-unit-price">Beverages Unit Price:</label>
            <input type="number" id="beverages-unit-price" name="beverages-unit-price" value="<?= isset($beveragesPrice) ? $beveragesPrice : 5 ?>" required>
        </div>

        <div class="category">
            <label for="desserts-quantity">Desserts Quantity:</label>
            <input type="number" id="desserts-quantity" name="desserts-quantity" value="<?= isset($dessertsQuantity) ? $dessertsQuantity : 100 ?>" required>
            <label for="desserts-unit-price">Desserts Unit Price:</label>
            <input type="number" id="desserts-unit-price" name="desserts-unit-price" value="<?= isset($dessertsPrice) ? $dessertsPrice : 3 ?>" required>
        </div>

        <div class="category">
            <label for="snacks-quantity">Snacks Quantity:</label>
            <input type="number" id="snacks-quantity" name="snacks-quantity" value="<?= isset($snacksQuantity) ? $snacksQuantity : 200 ?>" required>
            <label for="snacks-unit-price">Snacks Unit Price:</label>
            <input type="number" id="snacks-unit-price" name="snacks-unit-price" value="<?= isset($snacksPrice) ? $snacksPrice : 2 ?>" required>
        </div>

        <!-- Button Container -->
        <div class="button-container">
            <div>
                <button type="submit" name="calculate">Calculate</button>
                <button type="submit" name="save-to-db">Save to Database</button>
            </div>
            <button type="reset">Reset</button>
        </div>
    </form>

    <!-- Display Results -->
    <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['calculate'])) : ?>
        <div class="summary">
            <h3>Budget Overview:</h3>
            <p><strong>Total Spent:</strong> <?= number_format($totalSpent, 2) ?></p>
            <p><strong>Remaining Budget:</strong> <?= number_format($remainingBudget, 2) ?></p>
            <p><strong>Variance:</strong> <?= number_format($variance, 2) ?></p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>