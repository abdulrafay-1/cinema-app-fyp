<?php
require_once __DIR__ . '/../includes/booking.php';

$slides = db_all(
    "SELECT m.movie_id, m.movie_name, m.backdrop, m.description, m.rating, m.release_date, m.movie_duration, g.genre_name
     FROM addmovie m
     LEFT JOIN genre g ON g.gen_id = m.movie_genre_id
     WHERE m.backdrop IS NOT NULL AND m.backdrop <> ''
     ORDER BY EXISTS (SELECT 1 FROM shows s WHERE s.movie_id = m.movie_id AND s.status = 'Active' AND TIMESTAMP(s.show_date, s.show_time) > NOW()) DESC,
              m.popularity DESC, m.movie_id DESC
     LIMIT 6"
);
if (!$slides) {
    return;
}
?>
<style>
  .hs-item { position: relative; }
  .hs-item img { width: 100%; height: clamp(260px, 52vw, 560px); object-fit: cover; display: block; }
  .hs-shade { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(20, 20, 24, .92) 0%, rgba(20, 20, 24, .55) 45%, rgba(20, 20, 24, 0) 75%), linear-gradient(0deg, rgba(43, 43, 49, 1) 0%, rgba(43, 43, 49, 0) 30%); }
  .hs-caption { position: absolute; left: 6%; bottom: 14%; max-width: 560px; color: #fff; text-align: left; }
  .hs-caption h2 { font-family: 'Ubuntu', sans-serif; font-size: clamp(22px, 3.4vw, 46px); text-transform: uppercase; margin: 0 0 10px; color: #fff; line-height: 1.1; }
  .hs-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; font-size: 13px; color: rgba(255, 255, 255, .8); }
  .hs-meta span { background: rgba(255, 255, 255, .12); padding: 3px 10px; border-radius: 20px; }
  .hs-caption p { color: rgba(255, 255, 255, .78); font-size: 15px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 18px; }
  .hs-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  @media (max-width: 767px) { .hs-caption { left: 5%; right: 5%; bottom: 10%; } .hs-caption p { display: none; } }
</style>
<?php echo booking_css(); ?>
<div id="carouselExampleFade" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="6000">
  <div class="carousel-indicators">
    <?php foreach ($slides as $i => $slide): ?>
      <button type="button" data-bs-target="#carouselExampleFade" data-bs-slide-to="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>" <?php echo $i === 0 ? 'aria-current="true"' : ''; ?> aria-label="<?php echo e($slide['movie_name']); ?>"></button>
    <?php endforeach; ?>
  </div>
  <div class="carousel-inner">
    <?php foreach ($slides as $i => $slide): ?>
      <div class="carousel-item hs-item <?php echo $i === 0 ? 'active' : ''; ?>">
        <img src="Admin/upload-images/<?php echo e($slide['backdrop']); ?>" alt="<?php echo e($slide['movie_name']); ?>" <?php echo $i === 0 ? '' : 'loading="lazy"'; ?>>
        <div class="hs-shade"></div>
        <div class="hs-caption">
          <h2><?php echo e($slide['movie_name']); ?></h2>
          <div class="hs-meta">
            <?php if ((float) $slide['rating'] > 0): ?><span><i class="fa fa-star" style="color:#f5c542;"></i> <?php echo e(number_format((float) $slide['rating'], 1)); ?></span><?php endif; ?>
            <?php if ($slide['release_date']): ?><span><?php echo e(date('Y', strtotime($slide['release_date']))); ?></span><?php endif; ?>
            <?php if ($slide['genre_name']): ?><span><?php echo e($slide['genre_name']); ?></span><?php endif; ?>
            <?php if ($slide['movie_duration'] && $slide['movie_duration'] !== 'TBA'): ?><span><?php echo e($slide['movie_duration']); ?></span><?php endif; ?>
          </div>
          <p><?php echo e($slide['description']); ?></p>
          <div class="hs-actions">
            <a class="cx-btn" href="select_show.php?movie_id=<?php echo (int) $slide['movie_id']; ?>"><i class="fa fa-ticket-alt"></i> Book Now</a>
            <a class="cx-btn cx-btn-ghost" href="moviedetail.php?movdet=<?php echo (int) $slide['movie_id']; ?>">Details</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>
</div>
