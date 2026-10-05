<?php
require_once __DIR__ . '/customer_functions.php';

$user = require_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movie_id = (int) ($_POST['movie_id'] ?? 0);
    if (!csrf_ok()) {
        flash('danger', 'Invalid request. Please try again.');
    } elseif (($_POST['action'] ?? '') === 'remove') {
        $removed = db_query('DELETE FROM favorites WHERE user_id = ? AND movie_id = ?', 'ii', [(int) $user['id'], $movie_id]);
        flash($removed > 0 ? 'success' : 'danger', $removed > 0 ? 'Removed from your favorites.' : 'That movie was not in your favorites.');
    }
    redirect('favorites.php');
}

// newest favorite first, with genre and average rating for the card
$favorites = db_all(
    "SELECT m.movie_id, m.movie_name, m.movie_img, g.genre_name,
            (SELECT AVG(r.rating) FROM reviews r WHERE r.movie_id = m.movie_id AND r.status = 'Visible') AS average
     FROM favorites f
     JOIN addmovie m ON m.movie_id = f.movie_id
     LEFT JOIN genre g ON g.gen_id = m.movie_genre_id
     WHERE f.user_id = ?
     ORDER BY f.id DESC",
    'i',
    [(int) $user['id']]
);

include('header.php');
?>
<div class="container cx-page">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 style="margin:0;"><i class="fas fa-heart" style="color:var(--cx-pink);"></i> My Favorites</h2>
    <?php if ($favorites): ?>
      <span class="cx-muted"><?php echo count($favorites); ?> movie<?php echo count($favorites) === 1 ? '' : 's'; ?></span>
    <?php endif; ?>
  </div>

  <?php echo render_customer_flash(); ?>

  <?php if (!$favorites): ?>
    <div class="cx-card cu-empty">
      <i class="far fa-heart"></i>
      <h3>No favorites yet</h3>
      <p class="cx-muted">Tap the heart on any movie page to save it here.</p>
      <a href="index.php#movies" class="cx-btn">Browse movies</a>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($favorites as $fav): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="cu-card">
            <a class="cu-poster" href="moviedetail.php?movdet=<?php echo (int) $fav['movie_id']; ?>">
              <img src="Admin/upload-images/<?php echo e($fav['movie_img']); ?>" alt="<?php echo e($fav['movie_name']); ?>" loading="lazy">
            </a>
            <div class="cu-card-body">
              <h3><a href="moviedetail.php?movdet=<?php echo (int) $fav['movie_id']; ?>"><?php echo e(trim($fav['movie_name'])); ?></a></h3>
              <div>
                <?php if ($fav['genre_name']): ?><span class="cx-badge" style="font-size:11px;"><?php echo e(trim($fav['genre_name'])); ?></span><?php endif; ?>
              </div>
              <div class="cx-muted" style="font-size:13px;">
                <?php echo $fav['average'] !== null ? stars_html(round((float) $fav['average'], 1)) . ' ' . e(number_format((float) $fav['average'], 1)) : 'No ratings yet'; ?>
              </div>
              <div class="cu-card-actions">
                <a class="cx-btn cx-btn-ghost" href="moviedetail.php?movdet=<?php echo (int) $fav['movie_id']; ?>">Details</a>
                <form method="post" action="favorites.php" class="m-0">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="movie_id" value="<?php echo (int) $fav['movie_id']; ?>">
                  <button type="submit" class="cx-btn" title="Remove from favorites"><i class="fas fa-heart-broken"></i> Remove</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include('footer.php'); ?>
