<?php
require_once __DIR__ . '/../includes/booking.php';

$coming_soon = db_all('SELECT * FROM coming_soon WHERE release_date IS NULL OR release_date >= CURDATE() ORDER BY release_date IS NULL, release_date');
if (!$coming_soon) {
    return;
}
echo booking_css();
?>
<section class="section section--bg" data-bg="img/section/section.jpg" style="margin-top:40px;">
  <div class="container">
    <div class="row g-3">
      <div class="col-12">
        <h2 class="section__title">Coming Soon</h2>
      </div>
      <?php foreach ($coming_soon as $soon):
          $days = $soon['release_date'] ? (int) floor((strtotime($soon['release_date']) - strtotime(date('Y-m-d'))) / 86400) : null; ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="cx-card" style="padding:0;overflow:hidden;height:100%;">
            <div style="aspect-ratio:2/3;background:linear-gradient(135deg,#ff55a5,#5b2a86);display:flex;align-items:center;justify-content:center;position:relative;">
              <?php if ($soon['poster']): ?>
                <img src="Admin/upload-images/<?php echo e($soon['poster']); ?>" alt="<?php echo e($soon['title']); ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
              <?php else: ?>
                <span style="font-family:'Ubuntu',sans-serif;font-size:22px;font-weight:700;color:#fff;text-align:center;padding:16px;text-transform:uppercase;"><?php echo e($soon['title']); ?></span>
              <?php endif; ?>
              <?php if ($days !== null): ?>
                <span class="cx-badge cx-badge-orange" style="position:absolute;left:10px;top:10px;"><?php echo $days === 0 ? 'Out today' : 'In ' . $days . ' day' . ($days === 1 ? '' : 's'); ?></span>
              <?php endif; ?>
            </div>
            <div style="padding:12px 14px;">
              <h3 style="font-size:16px;margin:0 0 4px;color:var(--cx-text);text-transform:uppercase;"><?php echo e($soon['title']); ?></h3>
              <div class="cx-muted" style="font-size:12px;margin-bottom:6px;"><?php echo $soon['release_date'] ? 'Releasing ' . e(date('d M Y', strtotime($soon['release_date']))) : 'Release date TBA'; ?></div>
              <p class="cx-muted" style="font-size:13px;margin:0 0 8px;"><?php echo e($soon['description']); ?></p>
              <?php if ($soon['trailer_url'] && preg_match('#^https?://#i', $soon['trailer_url'])): ?>
                <a class="cx-btn cx-btn-sm cx-btn-ghost" href="<?php echo e($soon['trailer_url']); ?>" target="_blank" rel="noopener"><i class="fa fa-play"></i> Trailer</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
