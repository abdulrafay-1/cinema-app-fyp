# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Online Movie Booking System: plain PHP 7.4 + MySQL/MariaDB, no framework, no Composer, no build step, no tests, no linter. Pages are standalone `.php` files that mix SQL, form handling and HTML.

## Running it (Docker)

```
docker compose up -d --build     # site at http://127.0.0.1:8080
docker compose down              # stop; add -v to also wipe the DB volume
docker compose exec db mysql -uroot dbmo
```

- `web` is `php:7.4-apache` with `mysqli`; the repo root is bind-mounted at `/var/www/html`, so edits are live (no rebuild).
- `db` is `mariadb:10.4` with an empty root password. `dbmo.sql` is loaded into database `dbmo` only on first init of the `dbdata` volume. To re-seed after changing `dbmo.sql`: `docker compose down -v && docker compose up -d`.
- `config.php` and `Admin/config.php` read the DB host from the `DB_HOST` env var (set to `db` by compose), falling back to `localhost` for XAMPP-style local runs. Credentials (`root`, empty password, db `dbmo`) are hardcoded.
- On this machine `localhost:8080` via curl returned nothing right after startup; `127.0.0.1:8080` worked.

Seed logins (from `dbmo.sql`): user `Demo@outlook.com` / `123`; admin `admin@moviebooking.com` / `12345` at `/Admin/login.php`.

## Architecture

Two separate apps sharing one database:

- **Public site (repo root)**: each page starts with `include('header.php')` (opens session, HTML head, nav) and ends with `include('footer.php')`. `config.php` provides the `$con` mysqli handle. Flow: `index.php` / `bollywood.php` / `hollywood.php` / `lollywood.php` list movies → `moviedetail.php` → `book_ticket.php?movbook=<movie_id>` (requires login) → inserts into `booking_details` → `userbooking.php` shows the user's bookings.
- **Admin panel (`Admin/`)**: `admin_header.php` / `admin_footer.php` wrap every page and include `Admin/config.php` (a duplicate of the root one, so change both together). `Admin/login.php` calls `session_start()` and sets `$_SESSION['admin']`; `dashboard.php` gates on it. CRUD pages per lookup table (`cinema`, `genre`, `industry`, `language`, `date`, `price`) with matching `*_del.php` / `*_edit.php` handlers, plus `addmovie.php` / `edit_movie_del.php` / `viewmovie.php` for movies and `booking.php` / `user.php` for viewing bookings and users. Poster uploads go to `Admin/upload-images/`, which the public site reads directly (`Admin/upload-images/<movie_img>`).
- **Session keys**: users `$_SESSION['ux']` (name) and `$_SESSION['ux_email']`; admin `$_SESSION['admin']` (email). Login checks are inline at the top of each page (`if(!$_SESSION['ux'])` redirects to `signin.php` via a JS `location.assign`).
- **Schema** (`dbmo.sql`): `addmovie` is the hub, with FKs to `cinema`, `date`, `genre`, `industry`, `language`; `movie_price` holds per-movie tier prices; `booking_details` stores denormalised copies of movie/cinema/date/price strings rather than FKs; `user_reg_login` and `admin_login` hold credentials. The dump header names the database `online_movie_booking_system` but the code connects to `dbmo`.

## Team setup and new code

- Two developers; file ownership and cross-owner change requests live in `TEAM_NOTES.md`. Don't edit files owned by the other person, add a request there instead.
- Schema changes go in `database/migrations/00X_*.sql` (re-runnable, MariaDB `IF NOT EXISTS`), applied with `docker compose exec -T db mysql -uroot dbmo < database/migrations/<file>`. `dbmo.sql` is the untouched original dump. Shared table/column rules: `database/SCHEMA_CONTRACT.md`.
- New code uses `includes/functions.php` (`db_query`/`db_one`/`db_all` prepared-statement wrappers, `e()`, `current_user_id()`, `money()`, `flash()`, `redirect()`; sets Asia/Karachi time zone for PHP and MySQL) and `includes/admin_functions.php` (`log_activity()`). Admin pages gate with `include('auth.php');` before any output.
- Git Bash on this machine rewrites `/var/www/...` paths passed to `docker compose exec`; prefix with `MSYS_NO_PATHCONV=1`.

## Things to know before changing code

- Queries are built by string interpolation of `$_GET`/`$_POST`/`$_SESSION` values (SQL injection everywhere), passwords and card numbers are stored in plaintext, and `$_SESSION[...]` is read without `isset` in several places. Existing style; don't assume helpers, escaping or validation exist.
- Page-level bootstrap (`Admin/vendor`, `bootcss`, `bootjs`, `css`, `js`, `fonts`) is vendored static assets; don't edit.
