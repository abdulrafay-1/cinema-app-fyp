# CineMax Karachi – Team Demo Script (15–20 min)

Customer journey first, then the admin panel. Each step says **who presents** it:
**[UMAIR]** or **[RAFAY]**. "Say" lines are suggestions; say them in your own words.
Rafay's detailed notes for his parts are in `DEMO_SCRIPT.md`.

Site: **http://localhost/fyp_pro/** (XAMPP) · Admin: **http://localhost/fyp_pro/Admin/login.php**

| Login | Email | Password |
|---|---|---|
| Admin | `admin@gmail.com` | `12345` |
| Customer (main demo) | `Demo@outlook.com` | `123` |
| Customer with history | `ayesha.khan@example.com` | `123456` |
| Card | `4242 4242 4242 4242`, `12/28`, CVV `123` | |

---

## Before the demo (10 minutes earlier, not timed)

1. XAMPP Control Panel → **Start** Apache and MySQL.
2. Make sure migrations 001–005 are applied (README, "Setup on XAMPP").
3. **Shows must be in the future.** Open Admin → Shows. If there are no shows in the next few days, add 3–4 (e.g. AVATAR and MAULA JATT, tomorrow 19:25 and 21:10, plus **one show starting in about 3 hours** for the 50% refund).
4. Open **two browser windows**:
   - Window 1 (normal): customer site, logged out.
   - Window 2 (private / incognito): Admin, logged in.
5. Have the ticket QR ready to scan: after the booking in step 6, keep the ticket open on a phone, or print it.
6. Close other tabs, zoom the browser to 100–110%, turn off notifications.

---

## 1. Introduction – 1 min · [UMAIR]

Say: "This is CineMax Karachi, an online cinema booking system. A customer can find a movie, pick their own seats, add snacks, pay and get an e-ticket with a QR code. Cinema staff manage everything from an admin panel. Rafay built the core booking system: shows, seat map, checkout, payment, tickets, refunds, dashboard and reports. I built the admin management pages and the customer account pages: sign up, reviews, favorites, profile and contact. It's PHP and MySQL with Bootstrap."

## Customer journey (Window 1)

### 2. Sign up – 1 min · [UMAIR]
1. Click **Sign Up** in the menu.
2. Type name `Test Viewer`, email `Demo@outlook.com`, password `secret99` twice → **Sign up** → red message *"An account with this email already exists"*.
   Say: "Emails are unique. The page checks the database before creating the account."
3. Change the email to `viewer@example.com` → **Sign up** → green SweetAlert *"Account created!"* → OK → you land on Sign in.
4. Sign in as **`Demo@outlook.com` / `123`** (it has history, which looks better in the rest of the demo).

### 3. Browse, search and filter – 1.5 min · [RAFAY]
1. Home → scroll to **Now Showing & All Movies**.
2. Type `bla` in **Search** → suggestions appear → click **Black Adam**, then press Back.
3. Pick **Genre** → the grid filters; click **✕** to clear.
4. Scroll to **Coming Soon**. Say: "These come from the coming_soon table that Umair's admin page manages."

### 4. Movie page: rating, reviews, favorite – 2 min · [UMAIR]
1. Open **THE LEGEND OF MAULA JATT**.
2. Point out: the real **average rating** and review count under the title (from visible reviews only), the trailer, which resizes to fit the screen, and the description from the database.
3. Click **♡ Add to favorites** → green message, the heart fills.
4. Scroll to **Reviews**: Demo already has a review → click **Edit**, change to 4 stars → **Update review**.
   Say: "One review per user per movie. If you review again it updates instead of adding a second one. Reviews the admin hides don't count in the average."
5. Scroll to **More like this** (same genre).
6. Scroll up to **Upcoming Shows** and hand over to Rafay.

### 5. Show, seat map and seat hold – 2.5 min · [RAFAY]
1. Click **Select Seats** on a show at least a day away.
2. Explain the colours: rows A–C Economy, D–F Business, G–H First, and the legend with prices.
3. Click **E6, E7, G3** → the bottom bar shows seats and total. Try an 11th seat → "up to 10 seats".
4. Click **Continue** → "Seats held for 5:00".
   Say: "The seats go into seat_holds with a UNIQUE key on show and seat, so nobody else can hold or buy them. The hold expires after 5 minutes."
5. (Optional, 30 s) In the private window, sign in as `ayesha.khan@example.com` / `123456`, open the same show → E6/E7/G3 show as **held** (orange).

### 6. Checkout, payment, e-ticket – 2.5 min · [RAFAY]
1. Checkout: **+** on **Popcorn (Large)** → totals update.
2. Promo `BADCODE` → **Apply** → "not valid". Then `EID20` → 20% off.
3. **Proceed to payment** → Card `4242 4242 4242 4242`, `12/28`, `123` → **Pay** → "Processing…" → **Booking confirmed!**
4. Show the e-ticket: reference `CNX-2026-000NN`, seats, snacks, discount, total, card ****4242, **QR code**. Click **Print / Save as PDF**.
   Say: "The whole booking is saved in one database transaction: booking, seats, snacks, promo count and loyalty points. If any step fails, nothing is saved."

### 7. My Bookings and cancel – 1 min · [RAFAY]
1. Click your **name (top right) → My Bookings** → loyalty points and the Upcoming / Past / Cancelled tabs.
2. On the booking you just made, click **Cancel** → SweetAlert shows **"Refund: PKR … (100%)"** → **Yes, cancel it** → the Cancelled tab shows Refund **Pending**.
   Say: "24 hours or more before the show gives 100%, less than 24 hours gives 50%, and after the show starts you can't cancel. The seats are released immediately."

### 8. Profile, favorites, contact – 2 min · [UMAIR]
1. **Name menu → Profile**: photo or initials, member since, loyalty points, counts.
2. Change the phone to `0300 1234567` and upload a photo (keep one ready on the desktop) → **Save** → the photo appears in the navbar.
   Say: "Uploads must be real JPG/PNG/WEBP images under 2 MB, and get a random file name, so nobody can upload a PHP file."
3. (Optional) Change password: wrong current password → red error. Skip the real change in the demo.
4. **Name menu → Favorites**: Maula Jatt from step 4 is there → **Remove** → it disappears.
5. **Contact** (menu): name and email are filled in → subject `Group booking`, message `Can we book 15 seats for Saturday?` → **Send** → green thank-you.
   Say: "This goes to the admin inbox. You'll see the unread badge in a moment."

## Admin panel (Window 2)

### 9. Dashboard – 1 min · [RAFAY]
1. Admin login → **Dashboard**: revenue, tickets, bookings, cancelled, refunded, pending refunds, users, today's bookings.
2. Charts: revenue for the last 7 days, top 5 movies, booking status, seat class split. Say: "Every number is calculated live from the database."

### 10. Refunds and ticket scanner – 2 min · [RAFAY]
1. **Refunds** → Pending: Hamza's booking (from the demo data) and the one cancelled in step 7. **Approve** one → it moves to Refunded.
2. **Scan Ticket** → **Start camera** → show the ticket QR → green **VALID – Entry allowed**. Scan again → **ALREADY USED**.
   No camera? Type the booking reference in the manual box.
3. Type a cancelled booking's reference → red **CANCELLED**.

### 11. Reports – 0.5 min · [RAFAY]
1. **Reports** → **This month** → **Export CSV** (opens in Excel).

### 12. Admin management pages – 3.5 min · [UMAIR]
1. **Messages**: the red badge in the sidebar → open the message from step 8 → the badge goes down. Say: "Opening marks it as read; I can mark it unread or reply by email."
2. **Reviews**: filter by movie → **Hide** a review → say "hidden reviews disappear from the movie page and the average".
3. **Promo Codes**: point out used/max (EID20 shows 2 / 100 from the demo bookings). Add `final25`, 25%, max uses `1` → it's saved as `FINAL25`. Try adding it again → "already exists". Edit FINAL25 and set "valid until" to yesterday → the badge turns **Expired**. Say: "The badge uses the same rules as checkout: Inactive, Expired, Used up or Active."
4. **Snacks**: Deactivate **Hot Dog** → it disappears from checkout. Say: "A snack that was already sold can't be deleted, because old tickets still show its name, so we deactivate it."
5. **Users**: **View** on Ayesha → her points and bookings. Click **Block** on Demo → in Window 1 reload any page → Demo is signed out, and signing in says "blocked". **Unblock** again.
6. **Add Movies / View Movies**: open the edit (pencil) on a movie → description box, and "leave empty to keep the current poster".
7. **Seat Price Details**: "editing one movie's prices only changes that movie; before my fix it changed all of them".
8. **Activity Log**: every action from this demo is listed with the admin email and time → type `promo` in search.

### 13. Wrap-up – 1 min · [UMAIR + RAFAY]
- [RAFAY] "No double booking: unique keys on seat holds and booked seats, and the booking is saved in one transaction."
- [UMAIR] "Security: prepared statements against SQL injection, `e()` on all output against XSS, CSRF tokens on every form, protected admin pages, safe uploads."
- [UMAIR] "Future work: hashed passwords, a real payment gateway, email/SMS tickets, and a mobile app."
- Both: "Thank you, we're happy to take questions."

---

## If something goes wrong

| Problem | Quick fix |
|---|---|
| No shows / "No shows scheduled" | Admin → Shows → add a show for tomorrow |
| Seat map says the hold expired | Pick the seats again (5-minute hold) |
| Camera doesn't start | Use the manual reference box on Scan Ticket |
| A seat held by someone else shows a PHP error | Known PHP 8.2 issue in TEAM_NOTES (#1 for Rafay); don't demo two users fighting over the same seat until it's fixed |
| "Invalid request" on an admin form | The page was open too long or the session changed: reload and try again |
| Page looks unstyled | Check internet (Bootstrap fonts, SweetAlert, Font Awesome load from CDNs) |

## Timing

| Part | Who | Minutes |
|---|---|---|
| 1 Intro | Umair | 1 |
| 2 Sign up | Umair | 1 |
| 3 Browse | Rafay | 1.5 |
| 4 Movie page | Umair | 2 |
| 5 Seat map | Rafay | 2.5 |
| 6 Checkout + ticket | Rafay | 2.5 |
| 7 Cancel | Rafay | 1 |
| 8 Profile / favorites / contact | Umair | 2 |
| 9 Dashboard | Rafay | 1 |
| 10 Refunds + scan | Rafay | 2 |
| 11 Reports | Rafay | 0.5 |
| 12 Admin pages | Umair | 3.5 |
| 13 Wrap-up | Both | 1 |
| **Total** | | **~21 (skip the optional steps to finish in 18)** |
