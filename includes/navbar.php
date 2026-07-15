<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calculate path prefix relative to the public root
$current_uri = $_SERVER['SCRIPT_NAME'];
$path_prefix = '';
if (strpos($current_uri, '/admin/') !== false || strpos($current_uri, '/events/') !== false) {
    $path_prefix = '../';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navbar</title>
    <!-- Link the design system -->
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Floating Glass Navbar Style */
        .navbar-header {
            width: 100%;
            padding: 20px 40px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: var(--transition-smooth);
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid var(--card-border);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 12px 30px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.08);
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo a {
            display: flex;
            align-items: center;
            font-family: var(--font-heading);
            font-size: 22px;
            font-weight: 800;
            color: var(--text-heading);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            gap: 10px;
        }

        .logo img {
            height: 35px;
            width: 35px;
            border-radius: 50px;
            border: 1.5px solid var(--primary);
            object-fit: cover;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            align-items: center;
            gap: 8px;
        }

        .nav-link {
            font-family: var(--font-heading);
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 10px 18px;
            border-radius: 30px;
            transition: var(--transition-smooth);
        }

        .nav-link:hover {
            color: var(--primary);
            background: rgba(139, 92, 246, 0.05);
        }

        .nav-link.active {
            color: #ffffff;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        /* Push content down because navbar is fixed */
        body {
            padding-top: 110px;
        }

        /* Mobile Hamburger styles */
        .mobile-toggle {
            display: none;
            color: var(--text-heading);
            font-size: 24px;
            cursor: pointer;
        }

        @media (max-width: 992px) {
            .navbar-header {
                padding: 10px 20px;
            }
            .mobile-toggle {
                display: block;
            }
            .nav-menu {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 70px;
                left: 20px;
                right: 20px;
                background: rgba(255, 255, 255, 0.96);
                border: 1px solid var(--card-border);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                padding: 20px;
                box-shadow: var(--shadow-premium);
            }
            .nav-menu.active {
                display: flex;
            }
            .nav-link {
                width: 100%;
                text-align: center;
                display: block;
            }
        }
    </style>
</head>
<body>
    <header class="navbar-header">
        <div class="navbar-container">
            <!-- Logo -->
            <div class="logo">
                <a href="<?php echo $path_prefix; ?>Home.php">
                    <img src="<?php echo $path_prefix; ?>assets/images/logo.jpg" alt="Logo">
                    EventEase
                </a>
            </div>
            
            <!-- Mobile Toggle -->
            <div class="mobile-toggle" id="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </div>

            <!-- Navbar Links -->
            <ul class="nav-menu" id="nav-menu">
                <li><a href="<?php echo $path_prefix; ?>Home.php" class="nav-link">Home</a></li>
                <li><a href="<?php echo $path_prefix; ?>AboutUs.php" class="nav-link">About</a></li>
                <li><a href="<?php echo $path_prefix; ?>Contact.php" class="nav-link">Contact</a></li>
                <?php if (isset($_SESSION['login_user'])): ?>
                    <li><a href="<?php echo $path_prefix; ?>admin/BookinglistClient.php" class="nav-link">My Bookings</a></li>
                    <li><a href="<?php echo $path_prefix; ?>logout.php" class="nav-link btn btn-secondary" style="border-radius:30px; padding: 8px 20px; font-size: 13px;">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $path_prefix; ?>Login.php" class="nav-link">Client Login</a></li>
                    <li><a href="<?php echo $path_prefix; ?>admin/Admin.php" class="nav-link">Organizer Login</a></li>
                <?php endif; ?>
            </ul>
        </div> 
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('mobile-menu-toggle');
            const menu = document.getElementById('nav-menu');
            
            toggle.addEventListener('click', () => {
                menu.classList.toggle('active');
                const icon = toggle.querySelector('i');
                if(menu.classList.contains('active')) {
                    icon.className = 'fas fa-times';
                } else {
                    icon.className = 'fas fa-bars';
                }
            });

            // Set Active link
            const currentFile = window.location.pathname.split("/").pop();
            const links = document.querySelectorAll('.nav-link');
            links.forEach(link => {
                const href = link.getAttribute('href');
                const linkFile = href.substring(href.lastIndexOf('/') + 1);
                if (linkFile === currentFile || (currentFile === '' && linkFile === 'Home.php')) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
