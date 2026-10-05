<?php
require_once __DIR__ . '/includes/booking.php';

require_login();
$user = current_user_row();

$rows = db_all(
    "SELECT b.*, s.show_date, s.show_time, s.hall_name,
            IF(s.show_id IS NULL, NULL, TIMESTAMP(s.show_date, s.show_time)) AS starts_at
     FROM booking_details b
     LEFT JOIN shows s ON s.show_id = b.show_id
     WHERE b.user_id = ? OR (b.user_id IS NULL AND b.user_email = ?)
     ORDER BY b.booked_at DESC, b.booking_id DESC",
    'is',
    [(int) $user['id'], $user['email']]
);

$groups = ['upcoming' => [], 'past' => [], 'cancelled' => []];
foreach ($rows as $row) {
    if ($row['status'] === 'Cancelled') {
        $groups['cancelled'][] = $row;
    } elseif ($row['status'] === 'Booked' && $row['starts_at'] && strtotime($row['starts_at']) > time()) {
        $groups['upcoming'][] = $row;
    } else {
        $groups['past'][] = $row;
    }
}
usort($groups['upcoming'], function ($a, $b) { return strcmp($a['starts_at'], $b['starts_at']); });

$tab = isset($_GET['tab'], $groups[$_GET['tab']]) ? $_GET['tab'] : 'upcoming';
$labels = ['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'];
$pending_refunds = array_sum(array_map(function ($r) { return $r['refund_status'] === 'Pending' ? (int) $r['refund_amount'] : 0; }, $groups['cancelled']));

include('header.php');
echo booking_css();
?>
<section class="section section--first section--bg" data-bg="img/section/section.jpg">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <div class="section__wrap">
          <h2 class="section__title">My Account &amp; Bookings</h2>
          <ul class="breadcrumb">
            <li class="breadcrumb__item"><a href="index.php">Home</a></li>
            <li class="breadcrumb__item breadcrumb__item--active">Account &amp; Bookings</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container cx-page" style="margin-top:30px;">
  <div class="row g-3" style="margin-bottom:10px;">
    <div class="col-12 col-md-6">
      <div class="cx-card" style="height:100%;">
        <div class="cx-muted" style="font-size:13px;">Signed in as</div>
        <h4 style="margin:2px 0;"><?php echo e($user['name']); ?></h4>
        <div class="cx-muted"><?php echo e($user['email']); ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="cx-card text-center" style="height:100%;">
        <div style="font-size:30px;font-weight:700;background:var(--cx-accent);-webkit-background-clip:text;background-clip:text;color:transparent;"><?php echo (int) $user['loyalty_points']; ?></div>
        <div class="cx-muted" style="font-size:13px;"><i class="fa fa-star"></i> Loyalty points</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="cx-card text-center" style="height:100%;">
        <div style="font-size:30px;font-weight:700;"><?php echo count($groups['upcoming']); ?></div>
        <div class="cx-muted" style="font-size:13px;">Upcoming shows</div>
      </div>
    </div>
  </div>

  <?php echo render_flash(); ?>

  <div class="cx-tabs">
    <?php foreach ($labels as $key => $text): ?>
      <a class="cx-tab <?php echo $tab === $key ? 'is-active' : ''; ?>" href="userbooking.php?tab=<?php echo $key; ?>" style="min-width:120px;">
        <?php echo $text; ?> <strong><?php echo count($groups[$key]); ?></strong>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($tab === 'cancelled' && $pending_refunds > 0): ?>
    <div class="cx-alert cx-alert-info"><?php echo money($pending_refunds); ?> in refunds is waiting for approval.</div>
  <?php endif; ?>

  <?php if (!$groups[$tab]): ?>
    <div class="cx-card text-center">
      <p class="cx-muted" style="margin:0 0 14px;">No <?php echo strtolower($labels[$tab]); ?> bookings.</p>
      <a href="index.php" class="cx-btn cx-btn-sm">Browse movies</a>
    </div>
  <?php endif; ?>

  <?php foreach ($groups[$tab] as $b):
      list($date, $time) = booking_when($b);
      $quote = $tab === 'upcoming' ? refund_quote($b) : null; ?>
    <div class="cx-card">
      <div class="row g-3 align-items-center">
        <div class="col-3 col-md-2">
          <img class="cx-poster" src="Admin/upload-images/<?php echo e($b['user_movie_img']); ?>" alt="">
        </div>
        <div class="col-9 col-md-6">
          <div style="margin-bottom:6px;">
            <?php echo status_badge($b['status']); ?>
            <?php if ($b['refund_status'] !== 'None'): ?>Refund: <?php echo status_badge($b['refund_status']); ?><?php endif; ?>
          </div>
          <h4 style="margin:0 0 4px;"><?php echo e($b['user_movie_name']); ?></h4>
          <div class="cx-muted" style="font-size:14px;">
            <?php echo e($date); ?> &middot; <?php echo e($time); ?><br>
            <?php echo e($b['user_movie_cinema']); ?><?php echo $b['hall_name'] ? ' &middot; ' . e($b['hall_name']) : ''; ?><br>
            <?php echo $b['show_id'] ? 'Seats: ' . e($b['user_seat']) : e($b['user_seat']) . ' seat(s) &middot; ' . e($b['user_price']); ?>
          </div>
          <?php if ($b['refund_status'] !== 'None'): ?>
            <div style="font-size:13px;margin-top:6px;" class="cx-muted">
              Refund amount: <strong style="color:#fff;"><?php echo money($b['refund_amount']); ?></strong>
              <?php if ($b['refund_status'] === 'Rejected' && $b['refund_reason']): ?><br>Reason: <?php echo e($b['refund_reason']); ?><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="col-12 col-md-4 text-md-end">
          <div class="cx-muted" style="font-size:12px;"><?php echo e($b['booking_ref']); ?></div>
          <div style="font-size:20px;font-weight:700;margin-bottom:10px;"><?php echo money($b['total_amount']); ?></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
            <a class="cx-btn cx-btn-sm" href="ticket.php?ref=<?php echo urlencode($b['booking_ref']); ?>"><i class="fa fa-ticket-alt"></i> View Ticket</a>
            <?php if ($quote): ?>
              <form method="post" action="cancel_booking.php" class="cancel-form" style="margin:0;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="ref" value="<?php echo e($b['booking_ref']); ?>">
                <button type="button" class="cx-btn cx-btn-sm cx-btn-ghost cancel-btn"
                        data-movie="<?php echo e($b['user_movie_name']); ?>"
                        data-amount="<?php echo money($quote['amount']); ?>"
                        data-percent="<?php echo (int) $quote['percent']; ?>"><i class="fa fa-times"></i> Cancel</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <p class="cx-muted" style="font-size:12px;margin-top:10px;">Refund policy: cancel 24 hours or more before the show for a 100% refund, less than 24 hours for 50%. Loyalty points earned on a booking are removed when it is cancelled.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
document.querySelectorAll('.cancel-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var d = btn.dataset;
    var html = document.createElement('div');
    html.innerHTML = '<p></p><p style="font-size:20px;font-weight:700;margin:6px 0;"></p><p style="font-size:13px;opacity:.75;margin:0;"></p>';
    html.children[0].textContent = d.movie;
    html.children[1].textContent = 'Refund: ' + d.amount + ' (' + d.percent + '%)';
    html.children[2].textContent = d.percent === '100' ? 'Cancelling 24h+ before the show gives a full refund.' : 'Less than 24h before the show, only 50% is refunded.';
    Swal.fire({
      title: 'Cancel this booking?',
      html: html,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, cancel it',
      cancelButtonText: 'Keep booking',
      confirmButtonColor: '#ff5860',
      background: '#28282d',
      color: '#fff'
    }).then(function (r) { if (r.isConfirmed) btn.closest('form').submit(); });
  });
});
</script>
<?php include('footer.php'); ?>
