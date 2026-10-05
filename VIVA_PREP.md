# Viva Preparation – CineMax Karachi

Short, simple answers you can say out loud. Read them, then practise saying them in your own words.

---

## 1. Architecture in simple words

**Type of app:** a classic server-side PHP website. Every page is a `.php` file. When the browser asks for a page, PHP reads/writes the MySQL database and sends back HTML. There is no framework; we wrote small helper functions ourselves.

**Flow of one request**
```
Browser ──► page.php ──► includes/functions.php (session, db_query, e, csrf)
                │                 │
                │                 └──► MySQL database "dbmo" (prepared statements)
                └──► header.php + HTML + footer.php ──► Browser
```
Changes (forms) use **POST → check CSRF token → save → redirect → show a message** ("Post/Redirect/Get"), so refreshing the page never repeats an action.

**Folders**

| Folder / file | What's inside |
|---|---|
| root `*.php` | customer pages: home, movie page, seat map, checkout, payment, ticket, My Bookings, profile, favorites, contact, about, FAQ, pricing |
| `header.php`, `footer.php` | shared navbar and footer on every customer page |
| `includes/functions.php` | `db_query()` / `db_one()` / `db_all()` (prepared statements), `e()` (safe output), `csrf_field()` / `csrf_ok()`, `flash()`, `redirect()`, `current_user_id()`, `money()` |
| `includes/booking.php` | the booking rules: seat classes, seat holds, totals, promo check, `finalize_booking()`, refund rule, ticket check-in |
| `includes/admin_functions.php` | `log_activity()` for the admin activity log |
| `customer_functions.php` | customer helpers: logged-in user (+ blocked check), avatar, stars, site name and contact details |
| `partials/` | reusable page parts: show list, movie browser, ticket card, hold timer, coming soon |
| `ajax/` | small JSON endpoints: seat hold, seat status, live search, promo check, payment OTP |
| `Admin/` | admin panel; `auth.php` blocks anyone not logged in; `admin_header.php` is the sidebar; one page per feature |
| `database/` | `migrations/001…005` (changes on top of `dbmo.sql`), `SCHEMA_CONTRACT.md` (the rules for every table) |
| `assets/`, `css/`, `js/`, `img/`, `uploads/profile/` | styles, scripts, images, customer profile photos |

**Database tables and how they connect**

```
cinema ─┐                                   user_reg_login ──┬── reviews ──┐
genre ──┤                                         │          ├── favorites ┤
industry┼──► addmovie ◄── movie_price             │          │             │
language┤      │   (matched by movie name)        │          └─────────────┴──► addmovie
date ───┘      │                                  │
               ▼                                  ▼
             shows ───────────────────────► booking_details ──► booking_seats  (seats sold)
          (movie+cinema+date+time+hall)     (one per booking)  └► booking_snacks ──► snacks
               │                                  │
               └──► seat_holds (5-min holds)      └── promo_code (copy of promo_codes.code)

Stand-alone: admin_login, promo_codes, coming_soon, contact_messages, activity_log
```

- `addmovie` is the centre: each movie points to a cinema, genre, industry, language and date (foreign keys).
- `shows` = one screening (movie, cinema, date, time, hall). Foreign keys to `addmovie` and `cinema`, so a movie or cinema with shows can't be deleted.
- `booking_details` = one booking: who, which show, totals, payment method, status (`Booked` / `Cancelled` / `Used`) and refund status. It also keeps **copies** of the movie name, date and price text, so an old ticket still reads correctly after the admin edits a movie.
- `booking_seats` = one row per sold seat; `seat_holds` = seats being held for 5 minutes.
- `user_reg_login` = customers (with status, loyalty points, photo); `admin_login` = admins.

---

## 2. Questions about Umair's features (10)

**1. What did you build in this project?**
The admin management pages (movies, cinemas, genres, languages, dates, seat prices, snacks, promo codes, coming soon, users, reviews, messages, activity log) and the customer account side (sign up, reviews and ratings, favorites, profile with photo and password change, contact form), plus the navbar, footer, about, FAQ and pricing pages.

**2. How does the admin panel stop people who aren't logged in?**
Every admin page starts with `include('auth.php');`. It checks `$_SESSION['admin']`; if it's empty it redirects to `login.php` and stops the page with `exit`, before any data is shown or changed. The delete pages have it too.

**3. How does the review system make sure one user writes only one review per movie?**
Before saving, the code looks for an existing review by that user for that movie. If there is one it **updates** it, otherwise it inserts a new one. The user sees their own review with Edit and Delete instead of an empty form.

**4. How is the average rating calculated?**
`SELECT COUNT(*), AVG(rating) FROM reviews WHERE movie_id = ? AND status = 'Visible'`. Hidden reviews don't count. If there are no reviews we show "No ratings yet". Half stars are drawn when the average is x.5 or more.

**5. How do favorites avoid duplicates?**
The `favorites` table has a UNIQUE key on (user_id, movie_id), and we insert with `INSERT IGNORE`. Clicking the heart twice can't create two rows; clicking again deletes the row (toggle).

**6. How do you handle image uploads safely?**
One shared function `admin_upload_image()`: only JPG, PNG or WEBP; `getimagesize()` checks the file is really an image (a renamed PHP file fails); max 2 MB; the file gets a **random name** like `3f9a…c1.png`, so users can't choose the name or overwrite files. The profile photo folder also has an `.htaccess` that refuses to run scripts.

**7. How does Block / Unblock work?**
The admin sets `user_reg_login.status` to `Blocked`. `signin.php` refuses blocked users. If they are already logged in, `header.php` calls `customer()`, which sees the status and clears their session on the next page load.

**8. What was the price edit bug you fixed?**
The UPDATE in `price_edit.php` had no WHERE clause, so editing one movie's prices changed **every** movie. I added `WHERE price_id = ?` and also made it check that prices are whole numbers, because checkout adds them up as numbers.

**9. Why can't the admin delete a snack or a cinema sometimes?**
A cinema with shows is protected by a foreign key; the delete page catches the database error and shows "Delete those first". A snack that was already sold is still needed by old tickets (they join on `snacks` to show its name), so we tell the admin to **deactivate** it instead.

**10. What is the activity log for?**
`log_activity()` saves who (admin email), what (e.g. "Deleted cinema") and when for every admin add, edit and delete. It's useful for accountability: if prices change, we can see who changed them. The page is newest first with search and 20 rows per page.

---

## 3. Questions about the whole project (10)

**11. What is SQL injection and how did you prevent it?**
SQL injection is when user input changes the SQL itself, e.g. typing `' OR 1=1 --` as a password. We use **prepared statements**: the SQL is sent with `?` placeholders and the values are sent separately with `bind_param`, so input is always treated as data, never as SQL. Our helper `db_query($sql, 'si', [$name, $id])` does this everywhere new code touches user input.

**12. What is XSS and how did you prevent it?**
Cross-site scripting is when someone saves HTML/JavaScript (e.g. in a review) that then runs in other users' browsers. Every value we print goes through `e()`, which is `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`, so `<script>` is shown as text. We tested this with reviews and contact messages.

**13. What is CSRF and how does your token work?**
Cross-site request forgery is when another website makes your logged-in browser submit a form to our site (e.g. "delete cinema"). We give each session a random 32-character token (`bin2hex(random_bytes(16))`), put it in a hidden field (or in the delete link), and on submit compare it with `hash_equals()`. Another site can't read the token, so its fake request is refused.

**14. How do you stop two people booking the same seat?**
Three layers. (1) When you click Continue, the seats go into `seat_holds`, which has a **UNIQUE key on (show_id, seat_label)**, so the database itself refuses a second hold on the same seat. (2) Holds expire after 5 minutes so seats aren't blocked forever. (3) Paying moves the seats into `booking_seats`, which also has a UNIQUE (show_id, seat_label) key, inside a **transaction**: either the whole booking is saved or nothing is.

**15. What is a transaction and why do you use one?**
A group of database changes that succeed or fail together (`BEGIN … COMMIT`, or `ROLLBACK` on error). A booking writes the booking row, seats, snacks, promo count and loyalty points; if a seat fails, we roll back so there's never a half-saved booking or a charged customer without seats.

**16. Explain the refund policy.**
From `SCHEMA_CONTRACT.md`: cancel **24 hours or more** before the show → **100%** refund; **less than 24 hours** → **50%**; once the show has started you can't cancel. Cancelling sets status `Cancelled`, refund status `Pending`, releases the seats and removes the loyalty points. An admin then approves (`Refunded`) or rejects with a reason (`Rejected`).

**17. How is the total price calculated?**
Tickets (each seat's class price from `movie_price`) + snacks − discount. Discount = `floor((tickets + snacks) × promo % / 100)`. All money is whole rupees stored as INT. Loyalty points = `floor(total / 100)`.

**18. How does the QR ticket work?**
After payment we create a reference `CNX-2026-00012` from the booking id. The QR code contains **only** that reference (no personal data). The admin scanner reads it and calls `check_in_ticket()`: if the booking is `Booked` it becomes `Used` with a check-in time (VALID); scanning again shows ALREADY USED; cancelled or unknown references are refused.

**19. Why are some values (movie name, date) copied into booking_details?**
So history doesn't change. If the admin renames a movie or deletes a show later, the customer's old ticket and the reports still show what they actually bought. It's a deliberate denormalisation for records.

**20. How did two people work on one codebase without breaking each other's work?**
GitHub with clear file ownership in `TEAM_NOTES.md`: each file has one owner, and if you need a change in the other person's file you write a request instead of editing it. Database changes go only through numbered migration files, and `SCHEMA_CONTRACT.md` is the agreed description of every table. We pull before work, commit small pieces, `git pull --rebase`, then push.

---

## 4. Limitations and future work (be honest)

| Limitation now | What we would do next |
|---|---|
| **Passwords are stored in plain text** (inherited from the original project; login compares them directly) | Store `password_hash()` and check with `password_verify()`; migrate existing users on their next login |
| **Payment is a demo** (card / JazzCash / Easypaisa forms, OTP shown on screen) | Real gateway (JazzCash / Easypaisa merchant APIs or Stripe) with server-side payment confirmation |
| No email or SMS (no booking confirmation, no password reset) | Send the e-ticket by email (PHPMailer) and OTP/SMS through a provider; add "forgot password" |
| Some older pages still build SQL with string interpolation (mostly reads of database values) | Move the remaining queries to `db_query()` prepared statements |
| Checkout/payment endpoints don't check the CSRF token yet, and on PHP 8.2 a seat taken by someone else shows a PHP error instead of a friendly message (the double booking is still blocked) | Both are listed in TEAM_NOTES as fixes for the next version |
| One admin role; admin login has no rate limit | Roles (manager / box office), login attempt limits, 2-factor login |
| `movie_price` is linked to movies by name, not by id | Add `movie_id` to `movie_price` with a foreign key |
| No automated tests (we tested with scripted end-to-end runs) | PHPUnit tests for totals, refunds and seat holds |
| Website only | Mobile app (or PWA) using the same database through an API; push notifications for upcoming shows |
| Shows must be added manually | Recurring show schedules (e.g. "every day at 7:25 PM for 2 weeks") |

---

## 5. Quick facts to remember

- Database: **dbmo**, MariaDB 10.4, **21 tables** after the migrations.
- Seat classes: **A–C Economy, D–F Business, G–H First**; hall = 8 rows × 12 seats = 96 seats.
- Max **10 seats** per booking, holds last **5 minutes**.
- Refund: **≥ 24h = 100%, < 24h = 50%**, not after the show starts.
- Loyalty: **1 point per PKR 100**.
- Booking reference: **CNX-YYYY-00001**.
- Logins: admin `admin@gmail.com` / `12345`, customer `Demo@outlook.com` / `123`.
