<?php
require_once __DIR__ . '/../includes/booking.php';

$show_list_shows = upcoming_shows(isset($show_list_movie_id) ? (int) $show_list_movie_id : 0, 7);
echo booking_css();
?>
<div class="container cx-show-list" style="margin-top:30px;">
  <div class="cx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:6px;">
      <h3 style="color:var(--cx-text);margin:0;font-family:'Ubuntu',sans-serif;">Upcoming Shows</h3>
      <?php if ($show_list_shows): ?>
        <a class="cx-btn cx-btn-sm cx-btn-ghost" href="select_show.php?movie_id=<?php echo (int) $show_list_movie_id; ?>">All dates &amp; cinemas</a>
      <?php endif; ?>
    </div>
    <?php if (!$show_list_shows): ?>
      <p class="cx-muted" style="margin:10px 0 0;">No shows scheduled in the next 7 days. Please check back soon.</p>
    <?php endif; ?>
    <?php foreach ($show_list_shows as $show_list_row): ?>
      <div class="cx-show-row">
        <div>
          <div style="color:var(--cx-text);font-weight:600;"><?php echo e(show_date_label($show_list_row['show_date'])); ?> &middot; <?php echo e(show_time_label($show_list_row['show_time'])); ?></div>
          <div class="cx-muted" style="font-size:13px;"><?php echo e($show_list_row['cinema_name']); ?> &middot; <?php echo e($show_list_row['hall_name']); ?> &middot; <?php echo (int) $show_list_row['seats_left']; ?> seats left</div>
        </div>
        <?php if ((int) $show_list_row['seats_left'] > 0): ?>
          <a class="cx-btn cx-btn-sm" href="seat_map.php?show_id=<?php echo (int) $show_list_row['show_id']; ?>">Select Seats</a>
        <?php else: ?>
          <span class="cx-badge cx-badge-red">Housefull</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
