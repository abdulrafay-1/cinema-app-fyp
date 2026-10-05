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

Later migrations: `database/migrations/00X_<name>_<what>.sql`, add a row above.

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
5. **`Admin/price_edit.php` line 88:** the UPDATE has no `WHERE`, so editing one movie's prices overwrites **every** row. Add `where price_id='$movie_price_edit_id'`.
   **Done (Umair Task 1).**
6. **Deleting movies/cinemas:** `shows` has foreign keys to `addmovie` and `cinema`, so deleting one that has shows fails. Show a message like "Delete this movie's shows first".
   **Done (Umair Task 1):** every `*_del.php` catches the foreign-key error and shows "still used by ... Delete those first".
7. **moviedetail.php: upcoming shows block.** Paste this just above the `<!-- book movie btn -->` section:
   ```php
   <?php $show_list_movie_id = $movie_details['movie_id']; include('partials/show_list.php'); ?>
   ```
   Also point the "Book Tickets" button to `select_show.php?movie_id=<?php echo $movie_details['movie_id']; ?>` (the old `book_ticket.php` link will redirect there after Phase 2 anyway).
8. **Admin/admin_header.php sidebar:** please add these links (pages already exist or are coming):
   ```html
   <li><a href="shows.php"> <i class="fa fa-film"></i>Shows </a></li>
   <li><a href="refunds.php"> <i class="fa fa-undo"></i>Refunds </a></li>
   <li><a href="scan_ticket.php"> <i class="fa fa-qrcode"></i>Scan Ticket </a></li>
   <li><a href="reports.php"> <i class="fa fa-bar-chart"></i>Reports </a></li>
   ```
   **Done (Umair Task 1)**, under a "Shows & Tickets" heading.

9. **footer.php:** `img/google-play-badge.png` is referenced but missing, so every page logs a 404 in the console. Remove the badge or add the image.
10. **Font Awesome:** header.php loads both FA 4.7 and FA 5.15. The FA5 sheet wins, so FA4-only names (`fa-pencil`, `fa-ticket`, `fa-video-camera`, `fa-clock-o`) render blank on public pages. Use FA5 names (`fa-pencil-alt`, `fa-ticket-alt`, `fa-video`, `fa-clock`).

11. **Admin/admin_footer.php:** it loads `js/charts-home.js` on every admin page. That file draws the template's fake demo charts on canvas ids that no page has any more, so each admin page logs ~10 "Failed to create chart" console errors. Please remove that `<script>` line. `dashboard.php` draws its own charts with the same Chart.js.

12. **Heads-up, Rafay edited `Admin/admin_header.php`** (on request): the sidebar avatar photo is replaced by a generic icon (your logged-in-email name line was kept), and `Admin/img/admin.jpg` was deleted. Pull before editing that file.

### For Rafay
No changes needed, just FYI from Umair Task 1:
- `Admin/admin_header.php` now does `require_once __DIR__ . '/../includes/admin_functions.php';`, so every admin page that includes the header gets `e()`, `db_query()` and `log_activity()`. Your own `require_once` lines still work.
- The sidebar now has a "Shows & Tickets" group: Shows, Scan Ticket, Refunds, Reports.
- `activity_log` now gets rows for add/edit/delete on cinema, genre, industry, language, date, seat prices and movies.
