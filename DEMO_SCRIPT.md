# Demo Script (Rafay's features)

Open the site at **http://localhost:8080**. The camera scanner only works on `https://` or `localhost` (not `127.0.0.1` on some browsers, and not a LAN IP).

## Before the demo

1. `docker compose up -d`
2. Apply migrations once (safe to repeat):
   ```
   docker compose exec -T db mysql -uroot dbmo < database/migrations/001_shared_schema.sql
   docker compose exec -T db mysql -uroot dbmo < database/migrations/002_rafay_refund_reason.sql
   docker compose exec -T db mysql -uroot dbmo < database/migrations/003_rafay_admin_email.sql
   ```
3. Refresh the catalog from TMDB the day before the demo: apply `005_rafay_tmdb.sql`, back up, then run `docker compose exec web php database/seed_tmdb.php --yes` (key in `includes/secrets.php`, see TEAM_NOTES "TMDB catalog"). This loads the latest now-playing movies, creates shows for the next 7 days (some within 24 hours, to show the 50% refund) and clears old bookings.
4. Accounts:
   - Customer: `Demo@outlook.com` / `123`
   - Second customer for the seat-hold demo: register one on Sign Up (needs Umair's signup fix), or run:
     `docker compose exec db mysql -uroot dbmo -e "insert into user_reg_login (name,email,password) values ('Tester','tester@test.com','123')"`
   - Admin: `admin@gmail.com` / `12345` at `/Admin/login.php`
5. Open two browser windows: a normal one (customer), and a private one (admin, and later the second customer).

## 1. Browse, filter, live search
1. Home page → scroll to **Now Showing & All Movies**.
2. Type the first letters of a title in **Search** (e.g. `res` for Resident Evil) → poster suggestions appear. Press ↓ and Enter to open one, or click it.
3. Back on Home: pick **Genre = Action**. The grid reloads. Then pick **Language = Hindi** to narrow it further. Click **✕** to clear.
4. Pick any **Cinema**. Only movies with upcoming shows there are listed.
5. Point out the home **slider** (TMDB backdrops with rating, year, genre, runtime and Book Now), the ⭐ ratings on cards, and **Coming Soon** (TMDB upcoming releases with "In N days" badges and trailer links).
6. Menu **Movies Categories → Hollywood / Bollywood / Lollywood**. Same filters, limited to that industry (mapped from each movie's original language / country).

## 2. Pick a show
1. On any movie card click **Book** (the old `book_ticket.php?movbook=<movie id>` link also still works and redirects).
2. Point out the date tabs. Click another date, then click a showtime (each shows the hall and seats left).
3. When logged out you land on **Sign in**. Log in as Demo and you return to the same seat map.

## 3. Seat map
1. Point out: screen at the top, rows A–C Economy (blue), D–F Business (purple), G–H First (gold), legend with prices.
2. Click **E6, E7, G3**. They turn green, and the bottom bar shows the seats and the live total.
3. Try to select an 11th seat → "up to 10 seats" message.
4. Click **Continue** → seats are held for 5 minutes.

### 3b. Double-booking protection (optional, two windows)
1. In the private window, log in as `tester@test.com`, open the same show.
2. Within ~10 seconds Demo's seats show **orange (On hold)** and can't be clicked.
3. If both pick the same free seat and press Continue, the second one gets "Sorry, X was just taken…".

## 4. Checkout
1. Point out the **"Seats held for 4:5x"** countdown bottom-right.
2. Click **+** on Popcorn (Large) → totals update live.
3. Type `BADCODE` → **Apply** → "not valid". Type `EID20` → **Apply** → 20% off, totals and loyalty points update.
4. Click **Proceed to payment**.

## 5. Payment
1. **Card** tab: `4242 4242 4242 4242` (auto-spaced), expiry `1228` (becomes `12/28`), CVV `123`.
2. Click **Pay PKR …** → "Processing payment..." for ~2.5 s → **Booking confirmed!**
3. Alternative: **JazzCash** tab → mobile `03001234567` → **Send OTP** → a demo SMS shows the code → type it → Pay. A wrong OTP shows an error.
4. On the success page: e-ticket with poster, ref `CNX-YYYY-000NN`, show, hall, seats, snacks, discount, total, payment ****4242, and a QR code.
5. Click **Print / Save as PDF** → only the ticket prints.

## 6. My Bookings and cancel
1. Menu **My Account**: loyalty points at the top, tabs **Upcoming / Past / Cancelled** with counts.
2. **View Ticket** opens the ticket again (other users get "Ticket not found").
3. On a show **more than 24h away** click **Cancel** → SweetAlert shows "Refund: PKR X (100%)" → **Yes, cancel it**.
4. You land on **Cancelled**: status Cancelled, Refund **Pending**, loyalty points reduced.
5. Cancel a show **less than 24h away** → dialog shows **50%**.

## 7. Admin dashboard
1. Private window → `/Admin/login.php` → admin login.
2. Dashboard tiles: Total Revenue, Tickets Sold, Total Bookings, Cancelled, Total Refunded, Pending Refunds, Users, Today's Bookings (all from the database).
3. Charts: revenue last 7 days, top 5 movies, booking status, seat class split. Recent bookings table below.

## 8. Refunds
1. Top-right **Refunds** button (badge = pending count) → **Pending** tab.
2. **Approve** the 100% one → moves to **Refunded**, totals update.
3. On the 50% one type a reason (e.g. "Cancelled within 24 hours") → **Reject** → **Rejected** tab shows the reason.
4. Customer window → My Account → Cancelled: badges **Refunded** / **Rejected** + reason.

## 9. Scan tickets
1. **Scan** button → **Start camera** → allow camera.
2. Show the ticket QR (customer's ticket page on a phone, or the printed PDF) → green **VALID - Entry allowed** with booking details.
3. Scan it again → orange **ALREADY USED**. The customer's ticket now has a **USED** stamp.
4. Manual entry: type the cancelled booking's ref → red **CANCELLED**. Type `CNX-0000-00000` → grey **NOT FOUND**.

## 10. Shows management
1. **Shows** button → add a show: movie, cinema, hall, date, time, rows, seats per row → **Add Show**.
2. Point out the occupancy % bars. Trying to add the same hall/date/time again shows a clash error.
3. Delete a show that has bookings → refused ("set its status to Cancelled instead").

## 11. All bookings
1. Sidebar **All Bookings** → filter **Status = Cancelled**, or a date range or movie.
2. Search a booking ref → **View** → modal with seats, snacks, discount, masked card, refund info.

## 12. Reports
1. **Reports** button → defaults to this month. Try **Last 7 days** / **This year**.
2. Filter by **Movie** or **Cinema** → the cards (net revenue, gross, tickets, refunds, snacks, discounts) and the per-movie and per-cinema tables update.
3. **Export CSV** → opens in Excel. **Print** → clean printable report.

## 13. Blocked users
1. Set a customer to `status = 'Blocked'` (Umair's user admin page, or phpMyAdmin).
2. Log in as them → "Your account has been blocked. Please contact support for help."
