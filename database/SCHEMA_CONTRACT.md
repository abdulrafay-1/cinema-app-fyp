# Schema Contract

Shared rules for both developers. If code and this file disagree, this file wins; change it only together.

Database: `dbmo` (MariaDB 10.4, InnoDB, utf8mb4). Migrations live in `database/migrations/` and use MariaDB's `ADD COLUMN IF NOT EXISTS`, so each one can be re-run safely.

App time zone: **Asia/Karachi**. `includes/functions.php` sets it for PHP (`date_default_timezone_set`) and for MySQL (`SET time_zone = '+05:00'`). Pages that don't include it run on server time (UTC in Docker).

## Existing tables (changed by 001)

### booking_details
| Column | Type | Notes |
|---|---|---|
| booking_id | INT PK AI | |
| user_name, user_email | VARCHAR(200) | denormalised copy at booking time |
| user_movie_dur | VARCHAR(200) | show time text |
| user_movie_cinema, user_movie_name, user_movie_img | VARCHAR(200) | denormalised copies |
| user_date | VARCHAR(200) | show date text |
| user_price | VARCHAR(200) | legacy rows: `"Economy : 1100"`; new rows: seat classes summary |
| user_seat | VARCHAR(200) | legacy: seat count; new: seat labels, e.g. `"A1, A2"` |
| user_card | VARCHAR(200) | **last 4 digits only** for new rows: card last 4, or the JazzCash/Easypaisa mobile's last 4 |
| user_id | INT NULL | `user_reg_login.id`; backfilled from email for old rows |
| show_id | INT NULL | `shows.show_id`; NULL for legacy bookings |
| booking_ref | VARCHAR(20) UNIQUE | `CNX-YYYY-00001` |
| total_amount | INT | final amount paid (PKR), after discount, incl. snacks |
| snacks_amount | INT | |
| discount_amount | INT | |
| promo_code | VARCHAR(30) NULL | |
| payment_method | VARCHAR(20) NULL | `Card` / `JazzCash` / `Easypaisa` |
| status | VARCHAR(20) | `Booked` / `Cancelled` / `Used` |
| refund_status | VARCHAR(20) | `None` / `Pending` / `Refunded` / `Rejected` |
| refund_amount | INT | |
| refund_reason | VARCHAR(255) NULL | admin's reason when Rejected (migration 002) |
| refund_processed_at | DATETIME NULL | when the admin approved/rejected (migration 002) |
| cancelled_at, checked_in_at | DATETIME NULL | |
| booked_at | DATETIME | default now |

### user_reg_login
`id, name, email, password` plus: `phone VARCHAR(20) NULL`, `profile_pic VARCHAR(255) NULL` (filename), `status VARCHAR(20)` `Active`/`Blocked`, `loyalty_points INT`, `created_at DATETIME`.

### addmovie
Adds `description TEXT NULL`.

## New tables

| Table | Columns |
|---|---|
| shows | `show_id` PK, `movie_id` FK→addmovie, `cin_id` FK→cinema, `show_date DATE`, `show_time VARCHAR(30)` (24h `HH:MM`, e.g. `19:25`), `hall_name`, `total_rows` (8), `seats_per_row` (12), `status` `Active`/`Cancelled` |
| booking_seats | `id` PK, `booking_id`, `show_id`, `seat_label` (e.g. `D7`), `seat_class`, `price`; UNIQUE(`show_id`,`seat_label`) |
| seat_holds | `id` PK, `show_id`, `seat_label`, `user_id`, `expires_at`; UNIQUE(`show_id`,`seat_label`) |
| snacks | `snack_id` PK, `name`, `price`, `image` (NULL or filename), `is_active` |
| booking_snacks | `id` PK, `booking_id`, `snack_id`, `qty`, `price` (unit price at booking time) |
| promo_codes | `promo_id` PK, `code` UNIQUE, `discount_percent`, `max_uses`, `used_count`, `valid_until DATE`, `is_active` |
| reviews | `review_id` PK, `movie_id`, `user_id`, `rating` 1–5, `comment`, `status` `Visible`/`Hidden`, `created_at` |
| favorites | `id` PK, `user_id`, `movie_id`; UNIQUE(`user_id`,`movie_id`) |
| contact_messages | `id` PK, `name`, `email`, `subject`, `message`, `is_read`, `created_at` |
| coming_soon | `id` PK, `title`, `poster` (filename in `Admin/upload-images/`, NULL allowed), `trailer_url`, `release_date`, `description` |
| activity_log | `id` PK, `admin_email`, `action`, `details`, `created_at` |

Foreign keys on `shows` mean **a movie or cinema that has shows cannot be deleted** until those shows are deleted.

## Shared rules

**Seat classes by row.** Rows A–C = `Economy`, D–F = `Business`, G–H = `First`. Prices come from `movie_price` (`economy`, `business`, `first_class`) matched by `movie_price.movie_name_price = addmovie.movie_name`. Seat label = row letter + number, e.g. `A1` … `H12`.

**Seat holds.** Selecting seats creates `seat_holds` rows that expire 5 minutes later. Expired holds count as free and get deleted on read. The UNIQUE keys on `seat_holds` and `booking_seats` are what prevent double booking.

**Booking reference.** `CNX-YYYY-00001`: year + booking_id padded to 5 digits (`generate_booking_ref($booking_id)`), set right after the insert, in the same transaction. The ticket QR code contains **only** the booking_ref.

**Refund policy.** Cancelled 24h or more before show start = 100% refund; less than 24h = 50%. Shows that already started can't be cancelled. On cancel: `status='Cancelled'`, `refund_status='Pending'`, `refund_amount` set, `cancelled_at=NOW()`, its `booking_seats` rows deleted (seats released). An admin then sets `refund_status` to `Refunded` or `Rejected`.

**Totals.** `total_amount = tickets + snacks_amount - discount_amount`. Promo discount = `floor((tickets + snacks) * discount_percent / 100)`. `max_uses = 0` means unlimited. `used_count` is incremented inside the booking transaction.

**Loyalty.** 1 point per PKR 100 of `total_amount` (`floor(total/100)`), added on booking, removed on cancel (never below 0).

**Check-in.** The scanner looks up a booking_ref: if `Booked`, it sets `status='Used'`, `checked_in_at=NOW()`. `Cancelled` and `Used` tickets are invalid.

**Users.** `status='Blocked'` users can't log in. Session keys: `ux` (name), `ux_email`, `ux_id`. Admin: `admin` (email).

**Money.** Whole PKR as INT everywhere. Display with `money($amount)` → `PKR 1,100`.
