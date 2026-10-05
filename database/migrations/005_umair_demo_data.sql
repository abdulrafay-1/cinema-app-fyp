-- Umair: demo data so every admin page and dashboard chart has something to show.
-- 3 demo customers (password 123456), finished and upcoming bookings, one cancelled booking with a
-- PENDING refund, one already refunded, plus reviews, favorites and contact messages.
-- Bookings are built the same way checkout builds them (finalize_booking in includes/booking.php):
-- booking_details + booking_seats + booking_snacks, booking_ref CNX-YYYY-00001, promo used_count, loyalty points.
-- Safe to re-run: nothing is added twice. Dates are relative to the day you run it.
-- Run it with the mysql client, Docker (docker compose exec -T db mysql -uroot dbmo < this file)
-- or phpMyAdmin Import (it contains a temporary procedure, so keep the default "DELIMITER" support on).
SET NAMES utf8mb4;

-- ---------------------------------------------------------------- customers
INSERT INTO user_reg_login (name, email, password, phone, created_at)
SELECT s.name, s.email, s.password, s.phone, NOW() - INTERVAL s.days DAY
FROM (
  SELECT 'Ayesha Khan' AS name, 'ayesha.khan@example.com' AS email, '123456' AS password, '0301-2345678' AS phone, 40 AS days
  UNION ALL SELECT 'Hamza Ali', 'hamza.ali@example.com', '123456', '0321-3456789', 25
  UNION ALL SELECT 'Sara Ahmed', 'sara.ahmed@example.com', '123456', NULL, 12
) s
WHERE NOT EXISTS (SELECT 1 FROM user_reg_login u WHERE u.email = s.email);

-- ---------------------------------------------------------------- past shows (for finished / scanned tickets)
-- only on the first run, so re-running on another day doesn't add more past shows
SET @demo_seeded := (SELECT COUNT(*) FROM booking_details
                     WHERE user_email IN ('ayesha.khan@example.com', 'hamza.ali@example.com', 'sara.ahmed@example.com'));

INSERT INTO shows (movie_id, cin_id, show_date, show_time, hall_name)
SELECT m.movie_id, (SELECT MIN(cin_id) FROM cinema), CURDATE() - INTERVAL s.days DAY, s.show_time, 'Hall 4'
FROM (
  SELECT 'AVATAR: THE WAY OF WATER' AS movie_name, 5 AS days, '19:25' AS show_time
  UNION ALL SELECT 'THE LEGEND OF MAULA JATT', 4, '21:10'
  UNION ALL SELECT 'Dangal', 3, '16:00'
  UNION ALL SELECT 'BAJRANGI BHAIJAAN', 2, '19:25'
  UNION ALL SELECT 'BLACK ADAM', 1, '22:40'
) s
JOIN addmovie m ON m.movie_name = s.movie_name
WHERE @demo_seeded = 0;

-- ---------------------------------------------------------------- helper: one complete booking
DROP PROCEDURE IF EXISTS umair_demo_booking;
DELIMITER $$
CREATE PROCEDURE umair_demo_booking(
  IN p_email VARCHAR(200), IN p_show_id INT, IN p_seats VARCHAR(100),
  IN p_snack VARCHAR(100), IN p_snack_qty INT, IN p_promo VARCHAR(30),
  IN p_method VARCHAR(20), IN p_last4 VARCHAR(4), IN p_booked_at DATETIME, IN p_outcome VARCHAR(20))
proc: BEGIN
  DECLARE v_user INT; DECLARE v_name VARCHAR(200); DECLARE v_bid INT; DECLARE v_rest VARCHAR(100);
  DECLARE v_label VARCHAR(5); DECLARE v_class VARCHAR(20); DECLARE v_price INT; DECLARE v_starts DATETIME;
  DECLARE v_tickets INT DEFAULT 0; DECLARE v_snacks INT DEFAULT 0; DECLARE v_pct INT DEFAULT 0; DECLARE v_total INT;
  DECLARE v_eco INT DEFAULT 0; DECLARE v_bus INT DEFAULT 0; DECLARE v_first INT DEFAULT 0;

  SELECT id, name INTO v_user, v_name FROM user_reg_login WHERE email = p_email LIMIT 1;
  -- skip when the user or show is missing, the booking already exists, or a seat is already taken
  IF v_user IS NULL OR p_show_id IS NULL
     OR EXISTS (SELECT 1 FROM booking_details WHERE user_id = v_user AND show_id = p_show_id)
     OR EXISTS (SELECT 1 FROM booking_seats WHERE show_id = p_show_id AND FIND_IN_SET(seat_label, p_seats))
     OR NOT EXISTS (SELECT 1 FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id
                    JOIN movie_price mp ON mp.movie_name_price = m.movie_name WHERE s.show_id = p_show_id) THEN
    LEAVE proc;
  END IF;

  INSERT INTO booking_details
    (user_name, user_email, user_movie_dur, user_movie_cinema, user_movie_name, user_date, user_price, user_seat,
     user_card, user_movie_img, user_id, show_id, total_amount, snacks_amount, discount_amount, promo_code,
     payment_method, status, refund_status, booked_at)
  SELECT v_name, p_email, DATE_FORMAT(STR_TO_DATE(s.show_time, '%H:%i'), '%l:%i %p'), c.cinema_name, m.movie_name,
         DATE_FORMAT(s.show_date, '%a, %d %b %Y'), '', REPLACE(p_seats, ',', ', '), p_last4, m.movie_img,
         v_user, s.show_id, 0, 0, 0, p_promo, p_method, 'Booked', 'None', p_booked_at
  FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id JOIN cinema c ON c.cin_id = s.cin_id
  WHERE s.show_id = p_show_id;
  SET v_bid = LAST_INSERT_ID();
  UPDATE booking_details SET booking_ref = CONCAT('CNX-', YEAR(p_booked_at), '-', LPAD(v_bid, 5, '0')) WHERE booking_id = v_bid;
  SELECT TIMESTAMP(show_date, show_time) INTO v_starts FROM shows WHERE show_id = p_show_id;

  -- seats: rows A-C Economy, D-F Business, G-H First, priced from movie_price
  SET v_rest = p_seats;
  WHILE v_rest <> '' DO
    SET v_label = SUBSTRING_INDEX(v_rest, ',', 1);
    SET v_rest = IF(LOCATE(',', v_rest) > 0, SUBSTRING(v_rest, LOCATE(',', v_rest) + 1), '');
    SET v_class = CASE WHEN LEFT(v_label, 1) IN ('A', 'B', 'C') THEN 'Economy'
                       WHEN LEFT(v_label, 1) IN ('D', 'E', 'F') THEN 'Business' ELSE 'First' END;
    SELECT CAST(CASE v_class WHEN 'Economy' THEN mp.economy WHEN 'Business' THEN mp.business ELSE mp.first_class END AS UNSIGNED)
      INTO v_price
    FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id JOIN movie_price mp ON mp.movie_name_price = m.movie_name
    WHERE s.show_id = p_show_id LIMIT 1;
    INSERT INTO booking_seats (booking_id, show_id, seat_label, seat_class, price) VALUES (v_bid, p_show_id, v_label, v_class, v_price);
    SET v_tickets = v_tickets + v_price;
    SET v_eco = v_eco + (v_class = 'Economy'), v_bus = v_bus + (v_class = 'Business'), v_first = v_first + (v_class = 'First');
  END WHILE;

  IF p_snack IS NOT NULL AND EXISTS (SELECT 1 FROM snacks WHERE name = p_snack) THEN
    INSERT INTO booking_snacks (booking_id, snack_id, qty, price)
    SELECT v_bid, snack_id, p_snack_qty, price FROM snacks WHERE name = p_snack LIMIT 1;
    SET v_snacks = (SELECT price FROM snacks WHERE name = p_snack LIMIT 1) * p_snack_qty;
  END IF;

  IF p_promo IS NOT NULL THEN
    SET v_pct = COALESCE((SELECT discount_percent FROM promo_codes WHERE code = p_promo), 0);
    UPDATE promo_codes SET used_count = used_count + 1 WHERE code = p_promo;
  END IF;

  -- same totals rule as checkout_totals(): discount = floor((tickets + snacks) * % / 100)
  SET v_total = v_tickets + v_snacks - FLOOR((v_tickets + v_snacks) * v_pct / 100);
  UPDATE booking_details SET
    total_amount = v_total, snacks_amount = v_snacks,
    discount_amount = FLOOR((v_tickets + v_snacks) * v_pct / 100),
    user_price = CONCAT_WS(', ', IF(v_eco > 0, CONCAT('Economy x', v_eco), NULL),
                                 IF(v_bus > 0, CONCAT('Business x', v_bus), NULL),
                                 IF(v_first > 0, CONCAT('First x', v_first), NULL))
  WHERE booking_id = v_bid;

  -- what happened afterwards
  IF p_outcome = 'Used' THEN
    UPDATE booking_details SET status = 'Used', checked_in_at = v_starts - INTERVAL 15 MINUTE WHERE booking_id = v_bid;
  ELSEIF p_outcome IN ('RefundPending', 'Refunded') THEN
    -- cancelled 24h+ before the show = 100% refund; cancelling releases the seats
    UPDATE booking_details SET status = 'Cancelled', refund_amount = v_total, cancelled_at = p_booked_at + INTERVAL 1 DAY,
           refund_status = IF(p_outcome = 'Refunded', 'Refunded', 'Pending'),
           refund_processed_at = IF(p_outcome = 'Refunded', p_booked_at + INTERVAL 2 DAY, NULL)
    WHERE booking_id = v_bid;
    DELETE FROM booking_seats WHERE booking_id = v_bid;
  END IF;
END proc$$
DELIMITER ;

-- ---------------------------------------------------------------- bookings
-- finished shows (Hall 4 demo shows above): scanned at the door = Used
SET @show := (SELECT s.show_id FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id WHERE m.movie_name = 'AVATAR: THE WAY OF WATER' AND s.hall_name = 'Hall 4' AND s.show_date < CURDATE() ORDER BY s.show_date DESC LIMIT 1);
CALL umair_demo_booking('ayesha.khan@example.com', @show, 'D4,D5,D6', 'Combo: Large Popcorn + 2 Drinks', 1, 'FYP10', 'Card', '4242', NOW() - INTERVAL 6 DAY, 'Used');

SET @show := (SELECT s.show_id FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id WHERE m.movie_name = 'THE LEGEND OF MAULA JATT' AND s.hall_name = 'Hall 4' AND s.show_date < CURDATE() ORDER BY s.show_date DESC LIMIT 1);
CALL umair_demo_booking('hamza.ali@example.com', @show, 'A5,A6', 'Soft Drink', 2, NULL, 'JazzCash', '4567', NOW() - INTERVAL 5 DAY, 'Used');

SET @show := (SELECT s.show_id FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id WHERE m.movie_name = 'Dangal' AND s.hall_name = 'Hall 4' AND s.show_date < CURDATE() ORDER BY s.show_date DESC LIMIT 1);
CALL umair_demo_booking('sara.ahmed@example.com', @show, 'G5,G6', NULL, 0, NULL, 'Easypaisa', '7788', NOW() - INTERVAL 4 DAY, 'Used');

SET @show := (SELECT s.show_id FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id WHERE m.movie_name = 'BAJRANGI BHAIJAAN' AND s.hall_name = 'Hall 4' AND s.show_date < CURDATE() ORDER BY s.show_date DESC LIMIT 1);
CALL umair_demo_booking('Demo@outlook.com', @show, 'E1,E2,E3,E4', 'Popcorn (Large)', 2, 'EID20', 'Card', '1111', NOW() - INTERVAL 3 DAY, 'Used');

SET @show := (SELECT s.show_id FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id WHERE m.movie_name = 'BLACK ADAM' AND s.hall_name = 'Hall 4' AND s.show_date < CURDATE() ORDER BY s.show_date DESC LIMIT 1);
CALL umair_demo_booking('hamza.ali@example.com', @show, 'C3,C4,C5', 'Nachos with Cheese', 1, NULL, 'Card', '9012', NOW() - INTERVAL 2 DAY, 'Used');

-- upcoming shows (the seed shows from migration 001), at least 2 days away
SET @show := (SELECT s.show_id FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id WHERE m.movie_name = 'AVATAR: THE WAY OF WATER' AND s.status = 'Active' AND TIMESTAMP(s.show_date, s.show_time) > NOW() + INTERVAL 2 DAY ORDER BY s.show_date, s.show_time LIMIT 1);
CALL umair_demo_booking('ayesha.khan@example.com', @show, 'F7,F8', 'Popcorn (Regular)', 2, NULL, 'Card', '4242', NOW() - INTERVAL 1 DAY, 'Booked');

SET @show := (SELECT s.show_id FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id WHERE m.movie_name = 'THE LEGEND OF MAULA JATT' AND s.status = 'Active' AND TIMESTAMP(s.show_date, s.show_time) > NOW() + INTERVAL 2 DAY ORDER BY s.show_date, s.show_time LIMIT 1);
CALL umair_demo_booking('sara.ahmed@example.com', @show, 'H1,H2', 'Hot Dog', 1, 'EID20', 'Easypaisa', '7788', NOW() - INTERVAL 3 HOUR, 'Booked');

-- cancelled 24h+ before the show: refund waiting for the admin (Admin -> Refunds -> Pending)
SET @show := (SELECT s.show_id FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id WHERE m.movie_name = 'BLACK PANTHER: WAKANDA FOREVER' AND s.status = 'Active' AND TIMESTAMP(s.show_date, s.show_time) > NOW() + INTERVAL 2 DAY ORDER BY s.show_date, s.show_time LIMIT 1);
CALL umair_demo_booking('hamza.ali@example.com', @show, 'D8,D9', NULL, 0, NULL, 'JazzCash', '4567', NOW() - INTERVAL 2 DAY, 'RefundPending');

-- cancelled and already refunded (Admin -> Refunds -> Refunded)
SET @show := (SELECT s.show_id FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id WHERE m.movie_name = 'Dangal' AND s.status = 'Active' AND TIMESTAMP(s.show_date, s.show_time) > NOW() + INTERVAL 2 DAY ORDER BY s.show_date, s.show_time LIMIT 1);
CALL umair_demo_booking('ayesha.khan@example.com', @show, 'B6', NULL, 0, NULL, 'Card', '4242', NOW() - INTERVAL 4 DAY, 'Refunded');

DROP PROCEDURE IF EXISTS umair_demo_booking;

-- loyalty points = 1 per PKR 100 of every booking that wasn't cancelled (same rule as the booking code)
UPDATE user_reg_login u SET loyalty_points = (
  SELECT COALESCE(SUM(FLOOR(b.total_amount / 100)), 0) FROM booking_details b
  WHERE b.user_id = u.id AND b.status <> 'Cancelled' AND b.show_id IS NOT NULL)
WHERE u.email IN ('ayesha.khan@example.com', 'hamza.ali@example.com', 'sara.ahmed@example.com', 'Demo@outlook.com');

-- ---------------------------------------------------------------- reviews (all visible)
INSERT INTO reviews (movie_id, user_id, rating, comment, status, created_at)
SELECT m.movie_id, u.id, s.rating, s.comment, 'Visible', NOW() - INTERVAL s.days DAY
FROM (
  SELECT 'ayesha.khan@example.com' AS email, 'AVATAR: THE WAY OF WATER' AS movie_name, 5 AS rating,
         'Watched it in Business class, the seats were great and the 3D looked amazing.' AS comment, 4 AS days
  UNION ALL SELECT 'hamza.ali@example.com', 'THE LEGEND OF MAULA JATT', 5, 'Best Pakistani movie in years. The fight scenes are unreal on the big screen.', 3
  UNION ALL SELECT 'sara.ahmed@example.com', 'Dangal', 4, 'Very inspiring story. Took my parents and they loved it.', 2
  UNION ALL SELECT 'hamza.ali@example.com', 'BLACK ADAM', 3, 'Good action but the story was a bit weak. Nachos were great though!', 1
  UNION ALL SELECT 'Demo@outlook.com', 'BAJRANGI BHAIJAAN', 5, 'Emotional and funny at the same time. A must watch with family.', 2
) s
JOIN addmovie m ON m.movie_name = s.movie_name
JOIN user_reg_login u ON u.email = s.email
WHERE NOT EXISTS (SELECT 1 FROM reviews r WHERE r.movie_id = m.movie_id AND r.user_id = u.id);

-- ---------------------------------------------------------------- favorites
INSERT IGNORE INTO favorites (user_id, movie_id)
SELECT u.id, m.movie_id
FROM (
  SELECT 'Demo@outlook.com' AS email, 'AVATAR: THE WAY OF WATER' AS movie_name
  UNION ALL SELECT 'Demo@outlook.com', 'THE LEGEND OF MAULA JATT'
  UNION ALL SELECT 'Demo@outlook.com', 'ZARRAR'
  UNION ALL SELECT 'ayesha.khan@example.com', 'AVATAR: THE WAY OF WATER'
  UNION ALL SELECT 'hamza.ali@example.com', 'BLACK ADAM'
) s
JOIN addmovie m ON m.movie_name = s.movie_name
JOIN user_reg_login u ON u.email = s.email;

-- ---------------------------------------------------------------- contact messages (one unread)
INSERT INTO contact_messages (name, email, subject, message, is_read, created_at)
SELECT s.name, s.email, s.subject, s.message, s.is_read, NOW() - INTERVAL s.hours HOUR
FROM (
  SELECT 'Sara Ahmed' AS name, 'sara.ahmed@example.com' AS email, 'Wheelchair access' AS subject,
         'Is there wheelchair access to Hall 4? My grandfather wants to come with us next week.' AS message, 0 AS is_read, 2 AS hours
  UNION ALL SELECT 'Bilal Raza', 'bilal.raza@example.com', 'Birthday party booking',
         'Can we book 20 seats together for a birthday on Sunday evening? Do you offer a cake or snack package?', 1, 50
) s
WHERE NOT EXISTS (SELECT 1 FROM contact_messages c WHERE c.email = s.email AND c.subject = s.subject);
