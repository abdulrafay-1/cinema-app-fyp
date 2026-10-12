<?php
require_once __DIR__ . '/includes/booking.php';

$movie_id = isset($_GET['movie_id']) ? (int) $_GET['movie_id'] : 0;
$movie = db_one('SELECT movie_id, movie_name, movie_img, description, movie_trl_url FROM addmovie WHERE movie_id = ?', 'i', [$movie_id]);
if (!$movie) {
    flash('danger', 'Movie not found.');
    redirect('index.php');
}

$shows = upcoming_shows($movie_id, 14);
$dates = array_values(array_unique(array_column($shows, 'show_date')));
$selected_date = isset($_GET['date']) && in_array($_GET['date'], $dates, true) ? $_GET['date'] : ($dates[0] ?? null);

$by_cinema = [];
foreach ($shows as $show) {
    if ($show['show_date'] === $selected_date) {
        $by_cinema[$show['cinema_name']]['location'] = $show['cinema_location'];
        $by_cinema[$show['cinema_name']]['shows'][] = $show;
    }
}
$prices = movie_prices($movie['movie_name']);

include('header.php');
echo booking_css();
?>
<div class="container cx-page">
  <div class="cx-card">
    <div class="row g-3 align-items-center">
      <div class="col-4 col-md-2">
        <img class="cx-poster" src="Admin/upload-images/<?php echo e($movie['movie_img']); ?>" alt="<?php echo e($movie['movie_name']); ?>">
      </div>
      <div class="col-8 col-md-10">
        <h2 style="margin-bottom:6px;"><?php echo e($movie['movie_name']); ?></h2>
        <p class="cx-muted" style="margin-bottom:10px;"><?php echo e($movie['description']); ?></p>
        <?php if ($prices): ?>
          <span class="cx-badge cx-badge-blue">Economy <?php echo money($prices['Economy']); ?></span>
          <span class="cx-badge" style="background:#f3ecff;color:#6b3fc4;">Business <?php echo money($prices['Business']); ?></span>
          <span class="cx-badge cx-badge-orange">First <?php echo money($prices['First']); ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php echo render_flash(); ?>

  <h3 style="margin:10px 0 14px;">Select a show</h3>

  <?php if (!$dates): ?>
    <div class="cx-alert cx-alert-info">No upcoming shows for this movie yet. Please check back soon.</div>
  <?php else: ?>
    <div class="cx-tabs">
      <?php foreach ($dates as $date): ?>
        <a class="cx-tab <?php echo $date === $selected_date ? 'is-active' : ''; ?>" href="select_show.php?movie_id=<?php echo $movie_id; ?>&amp;date=<?php echo e($date); ?>">
          <?php echo e(date('D', strtotime($date))); ?>
          <strong><?php echo e(date('d', strtotime($date))); ?></strong>
          <?php echo e(date('M', strtotime($date))); ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php foreach ($by_cinema as $cinema_name => $cinema): ?>
      <div class="cx-card">
        <h4 style="margin-bottom:2px;"><i class="fa fa-video"></i> <?php echo e($cinema_name); ?></h4>
        <p class="cx-muted" style="font-size:13px;"><?php echo e($cinema['location']); ?></p>
        <div class="cx-showtimes">
          <?php foreach ($cinema['shows'] as $show): ?>
            <a class="cx-showtime <?php echo (int) $show['seats_left'] <= 0 ? 'is-full' : ''; ?>" href="seat_map.php?show_id=<?php echo (int) $show['show_id']; ?>">
              <?php echo e(show_time_label($show['show_time'])); ?>
              <small><?php echo e($show['hall_name']); ?> &middot; <?php echo (int) $show['seats_left'] > 0 ? (int) $show['seats_left'] . ' left' : 'Housefull'; ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php include('footer.php'); ?>
