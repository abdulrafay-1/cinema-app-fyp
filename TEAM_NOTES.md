# Team Notes

## File ownership

**Rafay (core):** `book_ticket.php`, `userbooking.php`, `signin.php`, `index.php`, `hollywood.php`, `bollywood.php`, `lollywood.php`, `select_show.php`, `seat_map.php`, `checkout.php`, `payment.php`, `booking_success.php`, `ticket.php`, `cancel_booking.php`, `ajax/*`, `partials/*`, `includes/*`, `database/*`, `Admin/auth.php`, `Admin/dashboard.php`, `Admin/booking.php`, `Admin/refunds.php`, `Admin/scan_ticket.php`, `Admin/shows.php`, `Admin/reports.php`.

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

### For Umair (other)
3. **Admin auth:** only `dashboard.php` and `booking.php` check the admin login right now. Every other admin page and every `*_del.php` handler can be opened without logging in. Add `include('auth.php');` as the first line of each.
4. **`header.php` line 2:** change `session_start();` to `if (session_status() === PHP_SESSION_NONE) { session_start(); }`. New pages start the session before including the header, and on XAMPP the unguarded call prints a notice.
5. **`Admin/price_edit.php` line 88:** the UPDATE has no `WHERE`, so editing one movie's prices overwrites **every** row. Add `where price_id='$movie_price_edit_id'`.
6. **Deleting movies/cinemas:** `shows` has foreign keys to `addmovie` and `cinema`, so deleting one that has shows fails. Show a message like "Delete this movie's shows first".
7. **moviedetail.php:** the include line for the upcoming-shows block comes with Phase 1.

### For Rafay
(none yet)
