-- Umair: demo rows so the admin Reviews and Messages pages aren't empty.
-- Safe to re-run: each row is only added when the same row isn't there yet,
-- and reviews are only added when the Demo user and the movie exist.
SET NAMES utf8mb4;

INSERT INTO reviews (movie_id, user_id, rating, comment, status, created_at)
SELECT m.movie_id, u.id, s.rating, s.comment, s.status, NOW() - INTERVAL s.days_ago DAY
FROM (
  SELECT 'AVATAR: THE WAY OF WATER' AS movie_name, 5 AS rating,
         'Amazing visuals, the underwater scenes were worth the big screen. Booking was quick too.' AS comment,
         'Visible' AS status, 3 AS days_ago
  UNION ALL
  SELECT 'THE LEGEND OF MAULA JATT', 4,
         'Great action and Fawad Khan was brilliant. A bit long in the middle.', 'Visible', 2
  UNION ALL
  SELECT 'BLACK ADAM', 1,
         'Worst movie ever!!! Visit my site for free tickets', 'Hidden', 1
) s
JOIN addmovie m ON m.movie_name = s.movie_name
JOIN user_reg_login u ON u.email = 'Demo@outlook.com'
WHERE NOT EXISTS (SELECT 1 FROM reviews r WHERE r.movie_id = m.movie_id AND r.user_id = u.id AND r.comment = s.comment);

INSERT INTO contact_messages (name, email, subject, message, is_read, created_at)
SELECT s.name, s.email, s.subject, s.message, s.is_read, NOW() - INTERVAL s.hours_ago HOUR
FROM (
  SELECT 'Ayesha Khan' AS name, 'ayesha.khan@example.com' AS email, 'Group booking for 15 people' AS subject,
         'Assalam o Alaikum,\nWe are a university group of 15 students. Can we book a whole row for the Saturday evening show, and is there a group discount?\n\nThanks,\nAyesha' AS message,
         0 AS is_read, 5 AS hours_ago
  UNION ALL
  SELECT 'Hamza Ali', 'hamza.ali@example.com', 'Refund not received yet',
         'I cancelled my booking two days ago and the refund still shows Pending. How long does it usually take?',
         1, 30
) s
WHERE NOT EXISTS (SELECT 1 FROM contact_messages c WHERE c.email = s.email AND c.subject = s.subject);
