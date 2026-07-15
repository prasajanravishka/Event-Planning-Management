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
    <title>Booking List - EventEase</title>
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
            <h1>Event Bookings</h1>
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
                            <th>Booking ID</th>
                            <th>Username</th>
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
                        $sql = "SELECT BookingID, user_name, EventType, Place, NumberOfGuests, EventDate, DayNight, FoodPreferences, ExtraDetails FROM bookings";
                        $result = $conn->query($sql);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['BookingID']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
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
                            echo "<tr><td colspan='9' style='text-align:center;'>No bookings found</td></tr>";
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
                filename:     'booking_list.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#0d091e' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        });
    </script>
</body>
</html>