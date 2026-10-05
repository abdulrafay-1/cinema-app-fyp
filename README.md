# CineMax Karachi – Online Movie Ticket Booking

A web application for booking cinema tickets in Karachi, built as a Final Year Project by **Umair** and **Abdul Rafay**.

Customers browse movies, pick a show, choose their exact seats on a live seat map, add snacks, apply a promo code, pay (demo payment) and get an e-ticket with a QR code. Cinema staff use an admin panel to manage movies, shows, prices, snacks and promo codes, approve refunds, scan tickets at the door and follow sales on a dashboard.

## Features

**Customers**
- Browse, live search and filter movies by genre, language and cinema; Coming Soon list
- Movie page with trailer, description, real star rating, reviews, favorites and "More like this"
- Show selection by date, seat map with Economy (rows A–C), Business (D–F) and First class (G–H)
- Seats held for 5 minutes during checkout, so two people can never buy the same seat
- Checkout with snacks and promo codes, demo payment by card, JazzCash or Easypaisa (with OTP)
- E-ticket with QR code, printable / save as PDF
- My Bookings (upcoming / past / cancelled), cancellation with refund (100% up to 24h before the show, 50% after)
- Loyalty points (1 point per PKR 100), profile with photo, password change, favorites, contact form

**Admin panel** (`/Admin`)
- Dashboard with revenue, tickets, refunds and 4 charts; reports with CSV export and print
- Movies, cinemas, genres, industries, languages, dates, seat prices, shows (with clash check)
- All bookings with filters, refunds (approve / reject with reason), QR ticket scanner
- Snacks, promo codes (usage limits, expiry), Coming Soon movies
- Users (view bookings, block / unblock), review moderation, contact inbox with unread badge
- Activity log of every admin add / edit / delete

**Security**
- Prepared statements (`db_query()`) for user input, `e()` (htmlspecialchars) on output
- CSRF token on every form and admin delete link
- Admin pages protected by `Admin/auth.php`; blocked customers are signed out
- Image uploads: JPG/PNG/WEBP only, real-image check, max 2 MB, random file names
- Double booking prevented by UNIQUE keys on `seat_holds` and `booking_seats`

## Tech stack

| Part | Technology |
|---|---|
| Server | PHP 8.2 (also runs on 7.4), Apache |
| Database | MySQL / MariaDB 10.4, database `dbmo`, mysqli |
| Front end | HTML, CSS, Bootstrap 5 (FlixGo customer theme), Bootstrap 4 (Bootstrapious admin theme), JavaScript, jQuery |
| Libraries | Chart.js (dashboard), SweetAlert2 (popups), Font Awesome, qrcode.js (ticket QR), html5-qrcode (camera scanner) |
| Run locally | XAMPP, or Docker (`docker-compose.yml`) |

## Setup on XAMPP

1. Install XAMPP and start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Put the project in `C:\xampp\htdocs\fyp_pro` (copy the folder, or link it:
   `mklink /J "C:\xampp\htdocs\fyp_pro" "<path to this folder>"` in an admin Command Prompt).
3. Open **phpMyAdmin** (http://localhost/phpmyadmin) → **New** → create a database named **`dbmo`**.
4. Select `dbmo` → **Import** → choose **`dbmo.sql`** → **Go**.
5. Still in `dbmo` → **Import** each migration from `database/migrations/` **in number order**:
   1. `001_shared_schema.sql` – new tables and columns, seed shows, snacks, promo codes
   2. `002_rafay_refund_reason.sql`
   3. `003_rafay_admin_email.sql`
   4. `004_umair_sample_data.sql`
   5. `005_umair_demo_data.sql` – demo customers, bookings, refunds, reviews, messages

   Or from a terminal:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root dbmo < dbmo.sql
   C:\xampp\mysql\bin\mysql.exe -u root dbmo < database\migrations\001_shared_schema.sql
   (repeat for 002 ... 005)
   ```
   All migrations are safe to run again.
6. Open **http://localhost/fyp_pro/**.

`config.php` and `Admin/config.php` connect to `localhost` with user `root` and an empty password. Seed shows cover the 7 days after migration 001 was applied; add new ones in **Admin → Shows** when they run out.

### Docker (optional)
```
docker compose up -d --build              # site at http://localhost:8080
docker compose exec -T db mysql -uroot dbmo < database/migrations/001_shared_schema.sql   # then 002 ... 005
```

## Demo logins

| Role | Login | Password |
|---|---|---|
| Admin (`/Admin/login.php`) | `admin@gmail.com` | `12345` |
| Customer | `Demo@outlook.com` | `123` |
| Demo customers (migration 005) | `ayesha.khan@example.com`, `hamza.ali@example.com`, `sara.ahmed@example.com` | `123456` |

Demo payment: card `4242 4242 4242 4242`, any future expiry (e.g. `12/28`), CVV `123`; or JazzCash/Easypaisa with `03001234567` and the OTP shown on screen. Promo codes: `EID20`, `FYP10`, `WELCOME50`.

## Project structure

```
index.php, moviedetail.php, select_show.php, seat_map.php, checkout.php, payment.php, ticket.php, ...   customer pages
profile.php, favorites.php, contact.php, about.php, faq.php, pricing.php                               customer pages
header.php / footer.php           shared layout (navbar, footer)
includes/                         helpers: functions.php (db_query, e, csrf, flash), booking.php (seats, totals, refunds)
customer_functions.php            customer helpers (current user, avatar, stars, site details)
partials/, ajax/                  reusable page parts and AJAX endpoints (seat holds, search, promo, OTP)
Admin/                            admin panel (auth.php guard, admin_header.php sidebar, one page per feature)
database/migrations/              numbered SQL changes on top of dbmo.sql; SCHEMA_CONTRACT.md documents every table
assets/, css/, js/, img/          styles, scripts, images
```

More detail: `database/SCHEMA_CONTRACT.md` (tables and rules), `TEAM_NOTES.md` (who owns what), `DEMO_SCRIPT_TEAM.md` (demo plan), `VIVA_PREP.md` (architecture and viva questions).

## Team

| Name | Responsibility |
|---|---|
| Abdul Rafay | Database design, shows, seat map and holds, checkout, payment, e-tickets and QR scanner, cancellations and refunds, dashboard, reports, search |
| Umair | Admin management pages (movies, prices, snacks, promo codes, coming soon, users, reviews, messages, activity log), sign up, profile, favorites, reviews, contact, site pages |

Final Year Project, 2026.
