<?php
session_start();
include __DIR__ . '/../config/database.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = htmlspecialchars(trim($_POST['firstname'] ?? ''));
    $lastname = htmlspecialchars(trim($_POST['lastname'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

    if (empty($firstname) || empty($lastname) || empty($email) || empty($phone) || empty($message)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("INSERT INTO contact_messages (firstname, lastname, email, phone, message) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssss", $firstname, $lastname, $email, $phone, $message);
            if ($stmt->execute()) {
                $success = "Thank you! Your message has been sent successfully.";
            } else {
                $error = "Error saving message: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
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
    <title>Contact Us - EventEase</title>
    <style>
        .contact-wrapper {
            max-width: 1000px;
            margin: 40px auto 80px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 40px;
        }

        .info-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow-premium);
        }

        .info-icon {
            font-size: 24px;
            color: var(--primary);
            background: rgba(139, 92, 246, 0.1);
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .info-text h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .info-text p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .form-card h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-card p.subtitle {
            color: var(--text-muted);
            margin-bottom: 25px;
        }

        .contact-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .full-width {
            grid-column: span 2;
        }

        .submit-btn {
            width: 100%;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .contact-wrapper {
                grid-template-columns: 1fr;
            }
            .info-panel {
                order: 1;
            }
        }
    </style>
</head>
<body>
    <div class="contact-wrapper">
        <!-- Left: Contact Info -->
        <div class="info-panel">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="info-text">
                    <h3>Address</h3>
                    <p>12 Street Name<br>Colombo, Sri Lanka</p>
                </div>
            </div>

            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <div class="info-text">
                    <h3>Phone Number</h3>
                    <p>011-11111111<br>Mon - Fri, 9am - 6pm</p>
                </div>
            </div>

            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="info-text">
                    <h3>Email Address</h3>
                    <p>support@eventease.com<br>Response within 24 hours</p>
                </div>
            </div>
        </div>

        <!-- Right: Contact Form -->
        <div class="glass-card form-card">
            <h1>Get In Touch</h1>
            <p class="subtitle">Drop us a message and our team will get back to you shortly.</p>

            <?php if (!empty($error)): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success; ?></div>
                </div>
            <?php endif; ?>

            <form class="contact-form" action="Contact.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="firstname">First Name</label>
                    <input type="text" id="firstname" class="form-input" name="firstname" placeholder="First Name" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="lastname">Last Name</label>
                    <input type="text" id="lastname" class="form-input" name="lastname" placeholder="Last Name" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" class="form-input" name="email" placeholder="name@example.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="text" id="phone" class="form-input" name="phone" placeholder="Phone Number" required>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="message">Type your message here...</label>
                    <textarea id="message" class="form-input" style="height: 120px; resize: none;" name="message" placeholder="Write something..." required></textarea>
                </div>

                <div class="full-width">
                    <button type="submit" class="btn btn-primary submit-btn">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
