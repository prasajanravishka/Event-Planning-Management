# EventEase - Event Planning & Management Platform

EventEase is a premium, fully responsive event planning and management platform. Re-architected with a modern, light-theme **glassmorphism** design system, it provides seamless client-to-organizer workflows for booking hotels, weddings, DJ parties, birthdays, and get-togethers.

---

## Key Modules & Features

* 🌟 **Responsive Alternating Services**: An aligned, alternating service row selector featuring zoom hover animations and details.
* 💳 **Catering & Food Calculator**: Estimate catering, dessert, and beverage expenses dynamically. Calculations are processed, summarized, and logged in real-time.
* 🔐 **Secure Booking Mechanism**: Generates secure tracking Booking IDs for scheduling events, places, and guest limits. Requires client authentication.
* 📊 **Organizer Control Panel**: Access client records, user credentials, booking logs, and food expense tables.
* 📥 **Interactive Reports**: Client bookings, user registrations, and budget logs support instant, premium landscape PDF export.
* ✉️ **Inquiry Logging**: A quick contact message form integrated directly onto pages that stores submissions securely.

---

## Directory Structure

```
├── config/
│   └── database.php          # Central database connection parameters
├── includes/
│   └── navbar.php            # Floating glassmorphic header widget
├── public/
│   ├── assets/
│   │   ├── css/
│   │   │   └── global.css    # Central Design System (Light glassmorphism)
│   │   └── images/           # Asset images and logos
│   ├── admin/
│   │   ├── Admin.php         # Organizer login
│   │   ├── Userlist.php      # User table grid (PDF export)
│   │   ├── Bookinglist.php   # Master booking grid (PDF export)
│   │   └── ...
│   ├── events/
│   │   ├── FoodBudsummary.php# Budget logs (PDF export)
│   │   └── ...
│   ├── Home.php              # Rebuilt interactive landing page
│   ├── Booking.php           # Protected client booking form
│   ├── Food.php              # Food calculator report sheet
│   └── ...
├── tests/                    # Testing scripts (haa.php, heee.php, n.php)
└── database.sql              # MySQL database structure schema
```

---

## Database Configuration
The system connects to a local MySQL instance with the following settings:
- **Database Name**: `event_planning_management`
- **Port**: `3306` (standard)
- **Authentication**: `root` with empty password `""` (default XAMPP/WAMP settings).

To setup the database, import the `database.sql` file into your MySQL database server via **phpMyAdmin**.

---

## Setup & Running Locally

1. Make sure you have **PHP** (8.1+) and **MySQL/XAMPP** running.
2. Clone the repository into your local directory.
3. Start the PHP local development server from the root directory:
   ```bash
   php -S localhost:8000 -t public
   ```
4. Access the homepage at: **[http://localhost:8000/Home.php](http://localhost:8000/Home.php)**

---

## Organizer Access
To test coordinator functionalities:
1. Register an administrator account at: `http://localhost:8000/admin/AdminRegistation.php`
2. Log in at: `http://localhost:8000/admin/Admin.php`
3. Access the console at: `http://localhost:8000/Summary.php`
