<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    header("Location: ../Login.php");
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
    <title>My Bookings - EventEase</title>
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
            <h1>My Bookings</h1>
            <div class="list-actions">
                <button id="downloadPdf" class="btn btn-primary">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <a href="../Slide.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <div class="pdf-content" id="makepdf">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Event Type</th>
                            <th>Location</th>
                            <th>Guests</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Pricing Tier</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $user = $_SESSION['login_user'];
                        $stmt = $conn->prepare("SELECT BookingID, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails FROM bookings WHERE user_name = ?");
                        if ($stmt) {
                            $stmt->bind_param("s", $user);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['BookingID']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['EventType']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['Place']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['NumberOfGuests']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['EventDate']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['DayNight']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['FoodPreferences']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['ExtraDetails']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' style='text-align:center;'>No bookings found for your account.</td></tr>";
                            }
                            $stmt->close();
                        } else {
                            echo "<tr><td colspan='8' style='text-align:center;'>Database query error.</td></tr>";
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
                filename:     'my_bookings.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#0d091e' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });
    </script>
</body>
</html>