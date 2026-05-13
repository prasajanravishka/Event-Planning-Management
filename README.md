# Event Planing Project (EventEase)

A PHP (XAMPP) based event planning website that lets you browse event categories (Hotels, Weddings, DJ Parties, Birthdays, Get Together), book events, calculate/save food & beverage budgets, and view organizer/admin dashboards.

## Project Structure (main pages)
- **Home.php** – Landing page with links to event categories.
- **navbar.php** – Common navigation bar included by pages.
- **AboutUs.php** – Mission/vision/achievement section.
- **Booking.php** – Booking form that inserts booking data into MySQL.
- **Food.php** – Food & beverage budget calculator (and saves results into DB).
- **FoodBudsummary.php** – Budget summary page (accessible from admin/summary).
- **Summary.php** – Simple dashboard menu (User, Booking, Budget Summary).
- **Userlist.php** – Lists registered users and supports “Download PDF”.
- **Admin.php** / **AdminRegistation.php** – Admin login/registration.

## Tech Stack
- **Backend:** PHP
- **Frontend:** HTML/CSS (page-specific CSS like `Home.css`, `Booking.css`, `Food.css`, etc.)
- **Database:** MySQL via **mysqli**
- **Dependencies:**
  - Uses `html2pdf.bundle.js` in **Userlist.php** for PDF export.

## Database Requirements
The code expects a MySQL database named **`login`** on `localhost` with user `root` (empty password by default in code).

### Tables referenced in current code
- **`users`** (used by `Login.php` and `Userlist.php`)
- **`admin`** (used by `Admin.php`)
- **`bookings`** (used by `Booking.php`)
- **`budgets`** (used by `Food.php`)

> Note: You must create these tables (and columns) according to what the PHP files expect.

## How to Run
1. Start **XAMPP**.
2. Ensure MySQL is running.
3. Put this project in your XAMPP **htdocs** folder (already assumed by path).
4. Open the project in browser:
   - Example: `http://localhost/Event%20Planing%20Project/Home.php`

## Navigation / Main User Flows
- **Browse categories:** Home → HotelSlide / WeddingsSlids / DjPartySlide / BirthdayList / GetTogether
- **Client booking:** Home → Booking → Booking data saved to `bookings`
- **Budget calculation:** Booking → Food calculator (`Food.php`) → calculates totals and can save to `budgets`
- **Admin/Organizer dashboard:** Admin login → Summary menu → Userlist / Bookinglist / FoodBudsummary

## Notes / Potential Issues
- Some pages reference other filenames with different casing (e.g., `LogIn.php` vs `Login.php`)—ensure links match actual filenames.
- `Booking.php` echoes results and expects specific POST field names.
- `Food.php` outputs a “Connected to database successfully!” message in normal execution.

## Screenshots / Assets
The project includes many images (e.g., `logo.jpg`, `wedding.jpg`, `birth.jpg`, etc.) which are referenced directly in the pages.

---
If you want, I can also generate a **full database schema** (CREATE TABLE scripts) by extracting the expected columns from the PHP files in this repo.
