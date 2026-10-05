SET NAMES utf8mb4;

ALTER TABLE booking_details
  ADD COLUMN IF NOT EXISTS user_id INT NULL,
  ADD COLUMN IF NOT EXISTS show_id INT NULL,
  ADD COLUMN IF NOT EXISTS booking_ref VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS total_amount INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS snacks_amount INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS discount_amount INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS promo_code VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'Booked',
  ADD COLUMN IF NOT EXISTS refund_status VARCHAR(20) NOT NULL DEFAULT 'None',
  ADD COLUMN IF NOT EXISTS refund_amount INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS checked_in_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS booked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD UNIQUE KEY IF NOT EXISTS uq_booking_ref (booking_ref),
  ADD KEY IF NOT EXISTS idx_booking_user (user_id),
  ADD KEY IF NOT EXISTS idx_booking_show (show_id),
  ADD KEY IF NOT EXISTS idx_booking_status (status);

UPDATE booking_details
SET total_amount = CASE
    WHEN user_price REGEXP ':[[:space:]]*[0-9]+[[:space:]]*$' AND user_seat REGEXP '^[0-9]+$'
    THEN CAST(TRIM(SUBSTRING_INDEX(user_price, ':', -1)) AS UNSIGNED) * CAST(user_seat AS UNSIGNED)
    ELSE 0
  END
WHERE total_amount = 0;

UPDATE booking_details
SET booking_ref = CONCAT('CNX-', YEAR(booked_at), '-', LPAD(booking_id, 5, '0'))
WHERE booking_ref IS NULL;

UPDATE booking_details b
JOIN user_reg_login u ON u.email = b.user_email
SET b.user_id = u.id
WHERE b.user_id IS NULL;

ALTER TABLE user_reg_login
  ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'Active',
  ADD COLUMN IF NOT EXISTS loyalty_points INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE addmovie
  ADD COLUMN IF NOT EXISTS description TEXT NULL;

CREATE TABLE IF NOT EXISTS shows (
  show_id INT NOT NULL AUTO_INCREMENT,
  movie_id INT NOT NULL,
  cin_id INT NOT NULL,
  show_date DATE NOT NULL,
  show_time VARCHAR(30) NOT NULL,
  hall_name VARCHAR(50) NOT NULL DEFAULT 'Hall 1',
  total_rows INT NOT NULL DEFAULT 8,
  seats_per_row INT NOT NULL DEFAULT 12,
  status VARCHAR(20) NOT NULL DEFAULT 'Active',
  PRIMARY KEY (show_id),
  KEY idx_shows_date (show_date),
  CONSTRAINT fk_shows_movie FOREIGN KEY (movie_id) REFERENCES addmovie (movie_id),
  CONSTRAINT fk_shows_cinema FOREIGN KEY (cin_id) REFERENCES cinema (cin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS booking_seats (
  id INT NOT NULL AUTO_INCREMENT,
  booking_id INT NOT NULL,
  show_id INT NOT NULL,
  seat_label VARCHAR(5) NOT NULL,
  seat_class VARCHAR(20) NOT NULL,
  price INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_show_seat (show_id, seat_label),
  KEY idx_bs_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS seat_holds (
  id INT NOT NULL AUTO_INCREMENT,
  show_id INT NOT NULL,
  seat_label VARCHAR(5) NOT NULL,
  user_id INT NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hold_seat (show_id, seat_label),
  KEY idx_hold_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS snacks (
  snack_id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  price INT NOT NULL DEFAULT 0,
  image VARCHAR(255) NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (snack_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS booking_snacks (
  id INT NOT NULL AUTO_INCREMENT,
  booking_id INT NOT NULL,
  snack_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  price INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_bsn_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promo_codes (
  promo_id INT NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  discount_percent INT NOT NULL,
  max_uses INT NOT NULL DEFAULT 0,
  used_count INT NOT NULL DEFAULT 0,
  valid_until DATE NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (promo_id),
  UNIQUE KEY uq_promo_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reviews (
  review_id INT NOT NULL AUTO_INCREMENT,
  movie_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Visible',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (review_id),
  KEY idx_reviews_movie (movie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favorites (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  movie_id INT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fav (user_id, movie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coming_soon (
  id INT NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  poster VARCHAR(255) NULL,
  trailer_url VARCHAR(255) NULL,
  release_date DATE NULL,
  description TEXT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT NOT NULL AUTO_INCREMENT,
  admin_email VARCHAR(100) NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO shows (movie_id, cin_id, show_date, show_time, hall_name)
SELECT m.movie_id,
       c.cin_id,
       CURDATE() + INTERVAL ((m.movie_id + n.k * 2) % 7 + 1) DAY,
       ELT((m.movie_id + n.k) % 5 + 1, '13:00', '16:00', '19:25', '21:10', '22:40'),
       CONCAT('Hall ', n.k + 1)
FROM addmovie m
JOIN (SELECT 0 AS k UNION ALL SELECT 1 UNION ALL SELECT 2) n
JOIN (SELECT cin_id, ROW_NUMBER() OVER (ORDER BY cin_id) - 1 AS rn FROM cinema) c
  ON c.rn = (m.movie_id + n.k * 3) % (SELECT COUNT(*) FROM cinema)
WHERE (n.k < 2 OR m.movie_id % 2 = 0)
  AND NOT EXISTS (SELECT 1 FROM shows);

INSERT INTO snacks (name, price)
SELECT * FROM (
  SELECT 'Popcorn (Large)' AS name, 650 AS price UNION ALL
  SELECT 'Popcorn (Regular)', 400 UNION ALL
  SELECT 'Nachos with Cheese', 550 UNION ALL
  SELECT 'Soft Drink', 250 UNION ALL
  SELECT 'Hot Dog', 450 UNION ALL
  SELECT 'Combo: Large Popcorn + 2 Drinks', 1050
) s
WHERE NOT EXISTS (SELECT 1 FROM snacks);

INSERT IGNORE INTO promo_codes (code, discount_percent, max_uses, valid_until) VALUES
  ('EID20', 20, 100, '2027-12-31'),
  ('FYP10', 10, 1000, '2027-12-31'),
  ('WELCOME50', 50, 5, '2027-12-31');

UPDATE addmovie SET description = CASE movie_id
  WHEN 6 THEN 'Jake Sully and Neytiri now raise a family on Pandora. When an old threat returns, they leave the forests and seek refuge with the ocean clans of the Metkayina reef people.'
  WHEN 7 THEN 'Maula Jatt, a fierce fighter haunted by the murder of his family, clashes with Noori Natt, the most feared warrior of the Natt clan. A blood feud that decides the fate of Punjab.'
  WHEN 8 THEN 'Nearly 5,000 years after he was granted the powers of the ancient gods and imprisoned, Teth-Adam is freed and unleashes his own brand of justice on the modern world.'
  WHEN 9 THEN 'Super-spy Orson Fortune and his team recruit Hollywood''s biggest movie star to help them on an undercover mission to stop the sale of a deadly new weapons technology.'
  WHEN 10 THEN 'The people of Wakanda fight to protect their nation from intervening world powers after the death of King T''Challa, and face a powerful new enemy from beneath the sea.'
  WHEN 11 THEN 'A young nun who believes she is called to fight the devil trains as an exorcist and finds herself in a spiritual battle with a demonic force tied to her own past.'
  WHEN 12 THEN 'When the Primm family moves to New York City, their young son Josh struggles to adapt, until he discovers Lyle, a singing crocodile who loves baths, caviar and great music.'
  WHEN 13 THEN 'A Pakistani intelligence officer goes on a high-stakes mission to dismantle a network that threatens the country. Action, loyalty and sacrifice on the line.'
  WHEN 14 THEN 'A light-hearted family comedy about two families brought together by a match that nobody planned. Love, misunderstandings and big wedding drama follow.'
  WHEN 15 THEN 'Former wrestler Mahavir Singh Phogat trains his daughters Geeta and Babita to become world-class wrestlers, defying village traditions to chase a gold medal for India.'
  WHEN 16 THEN 'A devoted, kind-hearted man takes it upon himself to reunite a mute six-year-old girl, lost in India, with her family across the border in Pakistan.'
  ELSE description
END
WHERE description IS NULL OR description = '';

UPDATE addmovie
SET description = CONCAT(movie_name, ' is now showing in cinemas. Book your seats early for the best view.')
WHERE description IS NULL OR description = '';

INSERT INTO coming_soon (title, poster, trailer_url, release_date, description)
SELECT * FROM (
  SELECT 'The Batman Part II' AS title,
         '68c48d141e8381607486e3fb_66a4263d01a185d5ea22eeec_6408f6e7b5811271dc883aa8_batman-min.png' AS poster,
         NULL AS trailer_url,
         CURDATE() + INTERVAL 30 DAY AS release_date,
         'Bruce Wayne returns to the streets of Gotham as the Dark Knight in the next chapter of the saga.' AS description
  UNION ALL
  SELECT 'Karachi Nights', NULL, NULL, CURDATE() + INTERVAL 45 DAY,
         'A taxi driver gets pulled into a one-night chase across Karachi after a stranger leaves a bag in his back seat.'
) c
WHERE NOT EXISTS (SELECT 1 FROM coming_soon);
