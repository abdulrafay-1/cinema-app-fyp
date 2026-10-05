# Team Notes

## File ownership

**Rafay (core):** `book_ticket.php`, `userbooking.php`, `signin.php`, `index.php`, `hollywood.php`, `bollywood.php`, `lollywood.php`, `select_show.php`, `seat_map.php`, `checkout.php`, `payment.php`, `booking_success.php`, `ticket.php`, `cancel_booking.php`, `ajax/*`, `partials/*`, `includes/*`, `database/*`, `assets/*`, `DEMO_SCRIPT.md`, `Admin/auth.php`, `Admin/dashboard.php`, `Admin/booking.php`, `Admin/refunds.php`, `Admin/scan_ticket.php`, `Admin/shows.php`, `Admin/reports.php`.

**Umair:** everything else, including `moviedetail.php`, `header.php`, `footer.php`, `about.php`, `faq.php`, `signup.php`, `profile.php`, `favorites.php`, `contact.php`, `Admin/admin_header.php` and the other `/Admin` pages.

If you need a change in the other person's file, write it under **Requests** below instead of editing it.

Workflow: commit after each piece of work, then `git pull --rebase origin main` and `git push origin main`.

## Applying migrations

Read `database/SCHEMA_CONTRACT.md` first. Migrations are safe to re-run.

```
docker compose exec -T db mysql -uroot dbmo < database/migrations/001_shared_schema.sql
```

XAMPP: phpMyAdmin → `dbmo` → Import → pick the file.

Seed shows are created relative to the day you run the migration (next 7 days). Add more from **Admin → Shows** once Phase 1 lands.

| Migration | Author | What |
|---|---|---|
| 001_shared_schema.sql | Rafay | new columns on booking_details / user_reg_login / addmovie, all new tables, seed data |
| 002_rafay_refund_reason.sql | Rafay | `booking_details.refund_reason`, `refund_processed_at` (admin refund decisions) |
| 003_rafay_admin_email.sql | Rafay | seed admin login renamed to `admin@gmail.com` (password unchanged: `12345`). Safe to re-run; re-run it if you applied it before 7 Oct |
| 005_rafay_tmdb.sql | Rafay | TMDB columns on `addmovie` (`tmdb_id`, `backdrop`, `release_date`, `rating`, `runtime`, `popularity`) and `coming_soon.tmdb_id` |
| 004_umair_sample_data.sql | Umair | demo data only: 3 reviews by the Demo user (one Hidden) and 2 contact messages (one unread). No schema change |
| 005_umair_demo_data.sql | Umair | demo data only: 3 customers (password `123456`), 5 finished + 2 upcoming bookings, 1 cancelled with a **pending** refund, 1 refunded, reviews, favorites, messages. Bookings are built like `finalize_booking()` (seats, snacks, promo count, loyalty). Uses a temporary procedure (dropped at the end). Run once per DB; safe to re-run |

Later migrations: `database/migrations/00X_<name>_<what>.sql`, add a row above.

## TMDB catalog (real movie data)

The movie catalog comes from TMDB. Each developer seeds their own database:

1. Get a free key at https://www.themoviedb.org/settings/api (either the "API Key" or the "API Read Access Token" works).
2. `cp includes/secrets.example.php includes/secrets.php` and paste the key between the quotes. `secrets.php` is git-ignored, so never commit the key or paste it anywhere else.
3. Apply migration 005, back up, then run the seeder:
   ```
   docker compose exec -T db mysql -uroot dbmo < database/migrations/005_rafay_tmdb.sql
   docker compose exec -T db mysqldump -uroot dbmo > backup.sql
   docker compose exec web php database/seed_tmdb.php --yes
   ```
   It replaces all movies, shows, prices, coming soon, bookings, refunds, reviews and favorites. Users, admins, cinemas, snacks and promo codes stay. Re-run it any time to refresh "now playing". Downloaded posters (`Admin/upload-images/tmdb_*`) are git-ignored.

## Helpers you can use

### Protecting an admin page
First line of every admin page, before `admin_header.php` or any output:

```php
<?php
include('auth.php');
include('admin_header.php');
```

It starts the session and redirects to `login.php` when no admin is logged in.

### Logging admin actions
```php
require_once __DIR__ . '/../includes/admin_functions.php';
log_activity('Deleted cinema', 'cin_id=' . $id);
```

`admin_functions.php` also loads `includes/functions.php`, which gives you:
- `db_query($sql, $types, $params)`: prepared statement. Returns a result for SELECT, affected rows for writes, `false` on error
- `db_all(...)` / `db_one(...)`: fetch all rows / one row as assoc arrays
- `e($s)`: HTML-escape for output
- `current_user_id()`: logged-in customer id or null
- `money(1100)` → `PKR 1,100`
- `flash('success', 'Saved')` + `echo render_flash();`
- `redirect($url)`
- `csrf_field()` inside a form + `csrf_ok()` when handling the POST

Including `functions.php` also starts the session if needed and sets the Asia/Karachi time zone.

## Requests

### For Umair (URGENT: broken by migration 001)
Migration 001 added columns, so INSERTs without a column list now fail with "Column count doesn't match":

1. **`signup.php` line 69**: signup is broken. Replace with:
   ```php
   $ux_insert=mysqli_query($con,"insert into user_reg_login (name,email,password) values('$ux_name','$ux_email','$ux_pass')");
   ```
   **Done (Umair Task 2):** column-list insert (prepared statement), plus duplicate-email check, email format check and inline error messages.
2. **`Admin/addmovie.php` line 178**: Add Movie is broken. Replace with:
   ```php
   $insert=mysqli_query($con,"insert into addmovie (movie_name,movie_cin_id,movie_genre_id,movie_indus_id,movie_lang_id,movie_date_id,movie_img,movie_trl_url,movie_duration) values('$mov_name','$mov_cinema','$mov_genre','$mov_industry','$mov_lang','$mov_date','$mov_image','$mov_trl_url','$mov_dur')");
   ```
   Also add a `description` textarea and save it (column `addmovie.description`).
   **Done (Umair Task 1):** column-list insert (prepared statement), description textarea on add and edit, and editing keeps the old poster when no new one is uploaded.

### For Umair (other)
3. **Admin auth:** only `dashboard.php` and `booking.php` check the admin login right now. Every other admin page and every `*_del.php` handler can be opened without logging in. Add `include('auth.php');` as the first line of each.
   **Done (Umair Task 1)** for all of Umair's admin pages and `*_del.php` handlers.
4. **`header.php` line 2:** change `session_start();` to `if (session_status() === PHP_SESSION_NONE) { session_start(); }`. New pages start the session before including the header, and on XAMPP the unguarded call prints a notice.
   **Done (Umair Task 2).**
5. **`Admin/price_edit.php` line 88:** the UPDATE has no `WHERE`, so editing one movie's prices overwrites **every** row. Add `where price_id='$movie_price_edit_id'`.
   **Done (Umair Task 1).**
6. **Deleting movies/cinemas:** `shows` has foreign keys to `addmovie` and `cinema`, so deleting one that has shows fails. Show a message like "Delete this movie's shows first".
   **Done (Umair Task 1):** every `*_del.php` catches the foreign-key error and shows "still used by ... Delete those first".
7. **moviedetail.php: upcoming shows block.** Paste this just above the `<!-- book movie btn -->` section:
   ```php
   <?php $show_list_movie_id = $movie_details['movie_id']; include('partials/show_list.php'); ?>
   ```
   Also point the "Book Tickets" button to `select_show.php?movie_id=<?php echo $movie_details['movie_id']; ?>` (the old `book_ticket.php` link will redirect there after Phase 2 anyway).
   **Done (Umair Task 2):** the include is wrapped in `file_exists()`; "Select Seats" opens `seat_map.php`. The synopsis now shows `addmovie.description` instead of the template's Ant-Man text.
8. **Admin/admin_header.php sidebar:** please add these links (pages already exist or are coming):
   ```html
   <li><a href="shows.php"> <i class="fa fa-film"></i>Shows </a></li>
   <li><a href="refunds.php"> <i class="fa fa-undo"></i>Refunds </a></li>
   <li><a href="scan_ticket.php"> <i class="fa fa-qrcode"></i>Scan Ticket </a></li>
   <li><a href="reports.php"> <i class="fa fa-bar-chart"></i>Reports </a></li>
   ```
   **Done (Umair Task 1)**, under a "Shows & Tickets" heading.

9. **footer.php:** `img/google-play-badge.png` is referenced but missing, so every page logs a 404 in the console. Remove the badge or add the image.
   **Done (Umair Task 2):** Google Play badge removed, the App Store badge stays.
10. **Font Awesome:** header.php loads both FA 4.7 and FA 5.15. The FA5 sheet wins, so FA4-only names (`fa-pencil`, `fa-ticket`, `fa-video-camera`, `fa-clock-o`) render blank on public pages. Use FA5 names (`fa-pencil-alt`, `fa-ticket-alt`, `fa-video`, `fa-clock`).
   **Done (Umair Task 2):** Umair's public files only use `fa-star` (valid in FA5). header.php now drops the unused FA 4.7 sheet and loads FA5's official `v4-shims.min.css`, so any FA4 name (`fa fa-pencil`, `fa fa-clock-o`, ...) still shows an icon on every public page.

11. **Admin/admin_footer.php:** it loads `js/charts-home.js` on every admin page. That file draws the template's fake demo charts on canvas ids that no page has any more, so each admin page logs ~10 "Failed to create chart" console errors. Please remove that `<script>` line. `dashboard.php` draws its own charts with the same Chart.js.
   **Done (Umair Task 2).**

12. **Heads-up, Rafay edited `Admin/admin_header.php`** (on request): the sidebar avatar photo is replaced by a generic icon (your logged-in-email name line was kept), and `Admin/img/admin.jpg` was deleted. Pull before editing that file.

13. **Heads-up, Rafay redesigned `Admin/login.php`** (on request): split card in the site theme (brand panel with a TMDB backdrop + form), fixed labels above icon inputs instead of the template's floating labels (which overlapped on autofill), show/hide password, inline error message, Back to website link. Login now runs before any output with a prepared statement and a header redirect; already-logged-in admins go straight to the dashboard. It no longer uses `admin_alert.php`, `front.js` or the template's form-validate. Pull before editing that file.

14. **Done by Rafay (on request), `moviedetail.php`:** the SYNOPSIS / SHOWS / OTHER DETAILS accordion is replaced by an always-visible details card (poster, genre/language/industry/year/runtime chips, synopsis, TMDB rating, audience rating, runtime, release date, Book / Trailer / Reviews buttons). The trailer iframe only renders when `movie_trl_url` is set, otherwise the backdrop is shown. The old SHOWS panel was dropped because Upcoming Shows below covers it. Pull before editing that file.
15. **Sample reviews (migration 004)** pointed at the old movies, so the TMDB seeder deletes them. Re-add sample reviews for TMDB titles if you want the Reviews page populated (match by `movie_name` after seeding).

### For Rafay
No changes needed, just FYI from Umair Task 1:
- `Admin/admin_header.php` now does `require_once __DIR__ . '/../includes/admin_functions.php';`, so every admin page that includes the header gets `e()`, `db_query()` and `log_activity()`. Your own `require_once` lines still work.
- The sidebar now has a "Shows & Tickets" group: Shows, Scan Ticket, Refunds, Reports.
- `activity_log` now gets rows for add/edit/delete on cinema, genre, industry, language, date, seat prices and movies.

From Umair Task 2 (FYI, nothing broken found):
- Tested sign up → sign in → movie page → Select Seats → seat map; all worked.
- New admin pages: snacks, promo_codes, coming_soon, reviews, messages, activity_log, and a rewritten user.php (Block/Unblock, View). They write to `snacks`, `promo_codes` and `coming_soon`, which your checkout and home page read: inactive snacks / promo codes disappear from checkout, and promo codes are saved in UPPER CASE to match `find_promo()`. A snack that appears in `booking_snacks` can't be deleted (your ticket and booking pages join on it); the admin is told to deactivate it instead.
- Snack images and coming-soon posters are saved in `Admin/upload-images/` with random names (`Admin/admin_upload.php`).
- Optional, later: passwords are still stored and compared in plain text. If you want hashing, `signin.php` would need `password_verify()` and `signup.php` would use `password_hash()`; tell me and I'll change signup at the same time.

From Umair Task 3 (customer pages):
- New pages: `profile.php`, `favorites.php`, `contact.php`; `moviedetail.php` now has real ratings, reviews (one per user, editable), a favorite heart and "More like this". Shared helpers for these are in `customer_functions.php` (`customer()`, `require_customer()`, `avatar_html()`, `stars_html()`).
- `header.php` now: one navbar for guests and users (Contact for everyone; avatar + name menu with Profile / My Bookings / Favorites), loads `assets/booking.css` and `css/customer.css` on every public page, and **signs out a user the admin has blocked** on their next page load.
- Profile photos go to `uploads/profile/` (random names, not committed to git, scripts can't run there). Profile password change keeps the plain-text format `signin.php` checks; new passwords (signup and profile) need at least 6 characters.
- Optional request for Rafay: (1) `require_login()` / `current_user_id()` could also refuse `status='Blocked'`, so a blocked user can't finish a checkout POST before the header signs them out. (2) A migration adding `UNIQUE KEY (movie_id, user_id)` on `reviews` would make "one review per movie" a database rule too; the code already enforces it.

From Umair Task 4 (full end-to-end test on XAMPP, PHP 8.2). Everything works end to end: browse/search/filter, seat map, holds, checkout with snacks + EID20, card and JazzCash payment, e-ticket + QR, My Bookings, cancel (100% and 50%), refunds approve/reject, scanner (VALID / ALREADY_USED / CANCELLED / NOT_FOUND), dashboard, reports + CSV. Requests for Rafay:
1. **Bug (PHP 8.1+ only, so XAMPP but not Docker 7.4): a seat that someone else is holding gives a PHP "Fatal error" instead of "Sorry, X was just taken".** In PHP 8.1+ mysqli throws `mysqli_sql_exception` on the duplicate key, so `db_query()` never returns `false` and `hold_seats()` (includes/booking.php line ~122) never reaches its conflict message. The UNIQUE key still stops the double booking (tested), it's only the error page. The same applies to the `booking_seats` insert in `finalize_booking()`. Simplest fix in `includes/functions.php` `db_query()`: wrap `mysqli_stmt_execute()` in `try { ... } catch (mysqli_sql_exception $e) { mysqli_stmt_close($stmt); return false; }` (or call `mysqli_report(MYSQLI_REPORT_OFF);` once at the top). Reproduce: user 1 holds C9, user 2 POSTs `show_id=<id>&seats=C9` to `ajax/seat_hold.php`.
2. **CSRF:** `checkout.php`, `payment.php`, `ajax/seat_hold.php`, `ajax/promo.php` and `ajax/payment_otp.php` accept POSTs without `csrf_ok()`. Low risk (payment is a demo), but every other form now has a token, so a viva question could catch it.
3. **alert() still used (Umair replaced all of his with SweetAlert2):** `signin.php` lines 63 and 90, `partials/hold_timer.php` line 18, `Admin/scan_ticket.php` line 143. SweetAlert2 v11 is now loaded in the `<head>` of `header.php` and `Admin/admin_header.php`, so `Swal.fire({icon: 'error', title: '...'})` works on every page.
4. **DEMO_SCRIPT.md:** "Menu My Account" is now the name menu (top right) → My Bookings. Umair wrote the joint 15–20 min script in `DEMO_SCRIPT_TEAM.md` (didn't edit yours); maybe add a link to it at the top of yours.
5. Seed shows only cover 7 days from when 001 was applied; before the demo, add fresh shows in Admin → Shows (or re-run 005 after adding them for upcoming demo bookings).

From Umair's full test (all 25 of Umair's features pass on XAMPP):
- FYI Rafay: when commit 2528e9b deleted the old posters, git dropped the now-empty `Admin/upload-images/` folder on fresh clones, so uploads failed and `seed_tmdb.php`'s `realpath()` returned false. Fixed by tracking `Admin/upload-images/.gitkeep` (your `tmdb_*` ignore rule still works) and `admin_upload_image()` now creates the folder if it's missing.
- Movie posters (Add / Edit Movie) now use the shared `admin_upload_image()` (real-image check, max 2 MB, random name). A replaced or deleted movie's poster file is removed only if it has a random upload name, so `tmdb_*` posters are never deleted.
- To do for Umair: `005_umair_demo_data.sql` matches the old movie names, so after `seed_tmdb.php` it adds customers, reviews and messages but no bookings. Re-point it at TMDB titles after the next reseed. Rafay's requests 1–3 from Task 4 (PHP 8.2 seat-hold error, CSRF on checkout/payment, remaining alert()) are still open.
