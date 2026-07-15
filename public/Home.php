<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/../config/database.php';

$contact_error = "";
$contact_success = "";

// Process Quick Contact Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $firstname = htmlspecialchars(trim($_POST['firstname'] ?? ''));
    $lastname = htmlspecialchars(trim($_POST['lastname'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

    if (empty($firstname) || empty($lastname) || empty($email) || empty($phone) || empty($message)) {
        $contact_error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("INSERT INTO contact_messages (firstname, lastname, email, phone, message) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssss", $firstname, $lastname, $email, $phone, $message);
            if ($stmt->execute()) {
                $contact_success = "Thank you! Your inquiry was submitted successfully.";
            } else {
                $contact_error = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $contact_error = "Database error: " . $conn->error;
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
    <title>EventEase - Seamless Event Planning & Management</title>
    <style>
        /* Ambient Background Orbs */
        .orb {
            position: absolute;
            width: 450px;
            height: 450px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(99, 102, 241, 0) 70%);
            filter: blur(60px);
            z-index: -1;
            pointer-events: none;
        }
        .orb-1 { top: 5%; left: -10%; }
        .orb-2 { top: 40%; right: -15%; }
        .orb-3 { bottom: 10%; left: -15%; }

        /* Hero Section */
        .hero {
            max-width: 900px;
            margin: 80px auto 50px;
            text-align: center;
            padding: 0 20px;
            position: relative;
        }

        .hero h1 {
            font-size: 58px;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--text-heading) 40%, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.03em;
            line-height: 1.15;
        }

        .hero p.description {
            font-size: 18px;
            color: var(--text-muted);
            max-width: 750px;
            margin: 0 auto 35px;
            line-height: 1.8;
        }

        .hero-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .hero-buttons .btn {
            min-width: 180px;
        }

        /* Section Styling */
        .section-header {
            text-align: center;
            margin-bottom: 45px;
            padding: 0 20px;
        }

        .section-header h2 {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-heading), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 15px;
        }

        /* Categories Grid */
        .grid-container {
            max-width: 1200px;
            margin: 60px auto 100px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            padding: 0 20px;
        }

        .category-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 320px;
            position: relative;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
        }

        .category-card:hover {
            transform: translateY(-8px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6), 0 0 20px var(--card-glow);
        }

        .card-image {
            height: 100%;
            background-size: cover;
            background-position: center;
            transition: var(--transition-smooth);
        }

        .category-card:hover .card-image {
            transform: scale(1.05);
        }

        .card-content {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(18, 12, 38, 0.85);
            backdrop-filter: blur(8px);
            position: absolute;
            bottom: 0;
            width: 100%;
            border-top: 1px solid var(--card-border);
        }

        .card-content h2 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: var(--text-white);
        }

        .card-icon {
            color: var(--primary);
            font-size: 18px;
            transition: var(--transition-smooth);
        }

        .category-card:hover .card-icon {
            transform: translateX(4px);
            color: var(--text-white);
        }

        /* Why Choose Us features */
        .features-wrapper {
            max-width: 1200px;
            margin: 80px auto 100px;
            padding: 0 20px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .feature-card {
            text-align: left;
            padding: 35px 30px;
        }

        .feature-icon {
            font-size: 28px;
            color: var(--primary);
            background: rgba(139, 92, 246, 0.08);
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(139, 92, 246, 0.15);
            margin-bottom: 25px;
        }

        .feature-card h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* About Us Split Section */
        .about-section {
            max-width: 1100px;
            margin: 100px auto;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 50px;
            align-items: center;
            padding: 0 20px;
        }

        .about-text h2 {
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--text-heading), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .about-text p {
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 25px;
        }

        .about-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .metric-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: var(--transition-smooth);
        }

        .metric-box:hover {
            border-color: rgba(139, 92, 246, 0.2);
            background: rgba(255, 255, 255, 0.04);
        }

        .metric-num {
            font-size: 36px;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-heading);
            margin-bottom: 6px;
        }

        .metric-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .about-visual {
            background-image: linear-gradient(rgba(10, 8, 19, 0.2), rgba(10, 8, 19, 0.8)), url(assets/images/happy-people-celebrating-having-fun.jpg);
            background-size: cover;
            background-position: center;
            border-radius: 20px;
            border: 1px solid var(--card-border);
            height: 400px;
            box-shadow: var(--shadow-premium);
        }

        /* Quick Contact Form */
        .contact-section {
            max-width: 800px;
            margin: 100px auto;
            padding: 0 20px;
        }

        .contact-form-card {
            padding: 40px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 10px;
        }

        .full-width {
            grid-column: span 2;
        }

        /* FAQ Accordion */
        .faq-section {
            max-width: 800px;
            margin: 100px auto;
            padding: 0 20px;
        }

        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
        }

        .faq-item {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            overflow: hidden;
            transition: var(--transition-smooth);
        }

        .faq-question {
            padding: 22px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            font-family: var(--font-heading);
            color: var(--text-heading);
            user-select: none;
        }

        .faq-answer {
            padding: 0 24px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0, 1, 0, 1), padding 0.3s ease;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .faq-item.active {
            border-color: rgba(139, 92, 246, 0.3);
            background: rgba(255, 255, 255, 0.85);
        }

        .faq-item.active .faq-answer {
            padding: 0 24px 24px;
            max-height: 200px; /* arbitrary height to slide open */
            transition: max-height 0.3s cubic-bezier(1, 0, 1, 0), padding 0.3s ease;
        }

        .faq-icon {
            font-size: 14px;
            color: var(--text-muted);
            transition: var(--transition-smooth);
        }

        .faq-item.active .faq-icon {
            transform: rotate(180deg);
            color: var(--primary);
        }

        /* Modern Footer */
        .footer {
            background: var(--bg-darker);
            border-top: 1px solid var(--card-border);
            padding: 80px 0 35px;
            margin-top: 120px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 40px;
            padding: 0 20px;
        }

        .footer-brand h3 {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 18px;
        }

        .footer-brand p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
            max-width: 320px;
        }

        .footer-links h4, .footer-social h4 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 22px;
            color: var(--text-white);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: var(--text-muted);
            font-size: 14px;
        }

        .footer-links a:hover {
            color: var(--primary);
            padding-left: 4px;
        }

        .footer-social-icons {
            display: flex;
            gap: 12px;
        }

        .social-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.03);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            border: 1px solid var(--card-border);
            transition: var(--transition-smooth);
        }

        .social-btn:hover {
            background: var(--primary);
            color: var(--text-white);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
        }

        .footer-bottom {
            text-align: center;
            margin-top: 70px;
            padding-top: 25px;
            border-top: 1px solid var(--card-border);
            font-size: 13px;
            color: var(--text-muted);
        }

        @media (max-width: 900px) {
            .about-section {
                grid-template-columns: 1fr;
            }
            .about-visual {
                height: 300px;
            }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 42px;
            }
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            .hero-buttons .btn {
                width: 100%;
                max-width: 280px;
            }
            .footer-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            .contact-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
        }

        /* Alternating Services Layout */
        .services-alternate {
            max-width: 1100px;
            margin: 80px auto;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            gap: 80px;
        }

        .alt-row {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .alt-row.alt-reverse {
            grid-template-columns: 1fr 1.2fr;
        }

        .alt-text {
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
        }

        .alt-text h3 {
            font-size: 26px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-heading), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .alt-text p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .alt-text .btn {
            align-self: flex-start;
            margin-top: 10px;
        }

        .alt-image {
            height: 340px;
            border-radius: 24px;
            border: 1px solid var(--card-border);
            background-size: cover;
            background-position: center;
            box-shadow: var(--shadow-premium);
            transition: var(--transition-smooth);
        }

        .alt-row:hover .alt-image {
            transform: scale(1.02);
            border-color: rgba(139, 92, 246, 0.3);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5), 0 0 15px var(--card-glow);
        }

        .alt-row:hover .alt-text {
            border-color: rgba(139, 92, 246, 0.3);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5), 0 0 15px var(--card-glow);
        }

        @media (max-width: 768px) {
            .services-alternate {
                gap: 50px;
            }
            .alt-row, .alt-row.alt-reverse {
                grid-template-columns: 1fr;
                gap: 25px;
            }
            .alt-image {
                height: 250px;
                order: -1; /* image on top on mobile */
            }
        }
    </style>
</head>
<body>
    <!-- Background Glow Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Hero Section -->
    <section class="hero">
        <h1>Celebrate Life's Moments,<br>Logistics Simplified.</h1>
        <p class="description">
            EventEase is a state-of-the-art event planning platform. We provide intuitive coordinators, dynamic food calculators, and secure venue booking systems to make your planning stress-free.
        </p>
        <div class="hero-buttons">
            <a href="Booking.php" class="btn btn-primary">Book Event Now</a>
        </div>
    </section>

    <!-- Services Alternate Section -->
    <section>
        <div class="section-header">
            <h2>Select Your Event Style</h2>
            <p>We craft tailored visual architectures and plans for multiple celebrations.</p>
        </div>
        
        <div class="services-alternate">
            <!-- Row 1: Hotels -->
            <div class="alt-row">
                <div class="alt-text">
                    <h3>Hotels & Venues</h3>
                    <p>Discover luxurious hotels, elegant halls, and beautiful outdoor venues. Our partners offer premium hosting facilities with full service options and top-tier coordination details.</p>
                    <a href="events/HotelSlide.php" class="btn btn-primary">Explore Venues</a>
                </div>
                <div class="alt-image" style="background-image: url(assets/images/Hotel.jpg);"></div>
            </div>

            <!-- Row 2: Weddings (Swapped) -->
            <div class="alt-row alt-reverse">
                <div class="alt-image" style="background-image: url(assets/images/wedding.jpg);"></div>
                <div class="alt-text">
                    <h3>Weddings</h3>
                    <p>Celebrate your love story in a beautifully choreographed wedding. From floral arrangements to staging, music, and lighting, we customize every detail to match your vision.</p>
                    <a href="events/WeddingsSlids.php" class="btn btn-primary">Plan Wedding</a>
                </div>
            </div>

            <!-- Row 3: DJ & Parties -->
            <div class="alt-row">
                <div class="alt-text">
                    <h3>DJ & Parties</h3>
                    <p>Bring energy to your party with top-tier DJs, interactive sound systems, and stunning neon light setups. Perfect for corporate events, anniversaries, or private celebrations.</p>
                    <a href="events/DjPartySlide.php" class="btn btn-primary">Book DJ Party</a>
                </div>
                <div class="alt-image" style="background-image: url(assets/images/happy-men-women-throwing-confetti.jpg);"></div>
            </div>

            <!-- Row 4: Birthdays (Swapped) -->
            <div class="alt-row alt-reverse">
                <div class="alt-image" style="background-image: url(assets/images/birth.jpg);"></div>
                <div class="alt-text">
                    <h3>Birthdays</h3>
                    <p>Create unforgettable birthday experiences for kids and adults alike. We coordinate customized theme designs, custom cakes, interactive games, and premium catering packages.</p>
                    <a href="events/BirthdayList.php" class="btn btn-primary">Book Birthday</a>
                </div>
            </div>

            <!-- Row 5: Get Togethers -->
            <div class="alt-row">
                <div class="alt-text">
                    <h3>Get Togethers</h3>
                    <p>Reconnect with old friends, family members, or colleagues. Our comfortable spaces, custom buffet layouts, and soft backgrounds set the perfect mood for sharing stories.</p>
                    <a href="events/GetTogether.php" class="btn btn-primary">Plan Reunion</a>
                </div>
                <div class="alt-image" style="background-image: url(assets/images/get.jpg);"></div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Feature Section -->
    <section class="features-wrapper">
        <div class="section-header">
            <h2>Engineered For Perfection</h2>
            <p>Enjoy robust modules built to coordinate your details with ease.</p>
        </div>

        <div class="features-grid">
            <div class="glass-card feature-card">
                <div class="feature-icon">
                    <i class="fas fa-magic"></i>
                </div>
                <h3>Smart Event Planner</h3>
                <p>Register packages, customize equipment addons, and allocate food styles in a central platform.</p>
            </div>

            <div class="glass-card feature-card">
                <div class="feature-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <h3>Real-Time Calculator</h3>
                <p>Estimate beverage and buffet expenses instantly, control allocations, and save summaries to database tables.</p>
            </div>

            <div class="glass-card feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Secure Booking System</h3>
                <p>Generate encrypted Booking IDs, track logs, and filter your schedule details under strict database privacy filters.</p>
            </div>
        </div>
    </section>

    <!-- About Section Panel -->
    <section class="about-section">
        <div class="about-text">
            <h2>Our Story & Vision</h2>
            <p>
                We believe that planning shouldn't get in the way of celebrating. EventEase bridges the gap between creativity and coordination, providing clients and admins a unified environment. We reduce logistical complexities and planning efforts.
            </p>
            <div class="about-metrics">
                <div class="metric-box">
                    <div class="metric-num">5K+</div>
                    <div class="metric-label">Events Managed</div>
                </div>
                <div class="metric-box">
                    <div class="metric-num">95%</div>
                    <div class="metric-label">Happy Clients</div>
                </div>
            </div>
        </div>
        <div class="about-visual"></div>
    </section>

    <!-- Quick Contact Form Section -->
    <section class="contact-section">
        <div class="glass-card contact-form-card">
            <div class="section-header" style="margin-bottom:25px; padding:0;">
                <h2 style="font-size:30px;">Quick Message</h2>
                <p style="font-size:14px;">Leave an inquiry and our coordinators will reach out directly.</p>
            </div>

            <?php if (!empty($contact_error)): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $contact_error; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($contact_success)): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $contact_success; ?></div>
                </div>
            <?php endif; ?>

            <form action="Home.php#contact" id="contact" method="POST">
                <div class="contact-grid">
                    <div class="form-group">
                        <label class="form-label" for="firstname">First Name</label>
                        <input type="text" id="firstname" class="form-input" name="firstname" placeholder="John" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="lastname">Last Name</label>
                        <input type="text" id="lastname" class="form-input" name="lastname" placeholder="Doe" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" class="form-input" name="email" placeholder="john@example.com" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="text" id="phone" class="form-input" name="phone" placeholder="011-1234567" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="message">Message</label>
                        <textarea id="message" class="form-input" style="height: 100px; resize: none;" name="message" placeholder="Type your requirements here..." required></textarea>
                    </div>

                    <div class="full-width">
                        <button type="submit" name="send_message" class="btn btn-primary" style="width: 100%;">Send Inquiry</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- FAQ Accordion Section -->
    <section class="faq-section">
        <div class="section-header">
            <h2>Frequently Asked Questions</h2>
            <p>Get answers to common planner questions.</p>
        </div>

        <div class="faq-list">
            <div class="faq-item">
                <div class="faq-question">
                    <span>How do I secure an event booking?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Simply navigate to the Bookings tab, register or log in, fill in your details (expected guest counts, dates, tier lists), and click Book. The system instantly generates a secure Booking ID.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    <span>Can I calculate catering budgets before committing?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Yes! You can use our integrated Food Budget Calculator to enter Buffet, Snack, and Drink prices. Compare totals against your allocations and save calculations directly to your profile.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    <span>How can I modify my booking parameters?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Navigate to the Addons page under Book Event. Enter your secure Booking ID to dynamically update venue places, guest quantities, and select AV equipment setups.
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand">
                <h3>EventEase</h3>
                <p>Providing premium digital planning utilities. We simplify celebrating so you can focus on the memories.</p>
            </div>
            
            <div class="footer-links">
                <h4>Site Links</h4>
                <ul>
                    <li><a href="Home.php">Home</a></li>
                    <li><a href="AboutUs.php">About Us</a></li>
                    <li><a href="Contact.php">Contact Us</a></li>
                    <li><a href="Booking.php">Book Event</a></li>
                </ul>
            </div>

            <div class="footer-social">
                <h4>Connect With Us</h4>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 15px;">Follow us on social networks for party concepts and venue guides.</p>
                <div class="footer-social-icons">
                    <a href="#" class="social-btn"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            &copy; <?php echo date("Y"); ?> EventEase. All rights reserved. Designed for celebrations.
        </div>
    </footer>

    <!-- Accordion Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                question.addEventListener('click', () => {
                    // Toggle active class
                    const isActive = item.classList.contains('active');
                    
                    // Close others
                    faqItems.forEach(innerItem => innerItem.classList.remove('active'));
                    
                    if(!isActive) {
                        item.classList.add('active');
                    }
                });
            });
        });
    </script>
</body>
</html>
