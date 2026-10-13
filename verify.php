<?php
require_once __DIR__ . '/includes/booking.php';

$ref = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$genuine = $ref !== '' && ticket_signature_ok($ref, (string) ($_GET['sig'] ?? ''));
$ticket = $genuine ? load_booking($ref) : null;

if (!$genuine) {
    $verdict = ['bad', 'fa-times-circle', 'Not a valid CineMax ticket', 'This QR code was not issued by CineMax Karachi, or it has been altered. Do not accept it.'];
} elseif (!$ticket) {
    $verdict = ['bad', 'fa-question-circle', 'Ticket not found', 'The signature is genuine, but this booking no longer exists. Please contact the box office.'];
} else {
    list($verify_date, $verify_time) = booking_when($ticket);
    $past_day = $ticket['show_date'] && $ticket['show_date'] < date('Y-m-d');
    if ($ticket['status'] === 'Cancelled') {
        $verdict = ['bad', 'fa-ban', 'Cancelled ticket', 'This booking was cancelled' . ($ticket['cancelled_at'] ? ' on ' . date('d M Y, g:i A', strtotime($ticket['cancelled_at'])) : '') . ' and is not valid for entry.'];
    } elseif ($ticket['status'] === 'Used') {
        $verdict = ['warn', 'fa-door-open', 'Already used', 'Genuine ticket, checked in' . ($ticket['checked_in_at'] ? ' at ' . date('d M Y, g:i A', strtotime($ticket['checked_in_at'])) : '') . '. It cannot be used again.'];
    } elseif ($past_day) {
        $verdict = ['warn', 'fa-calendar-times', 'Show date has passed', 'Genuine ticket, but the show was on ' . $verify_date . '.'];
    } else {
        $verdict = ['ok', 'fa-check-circle', 'Genuine ticket', 'Issued by CineMax Karachi and valid for entry to the show below.'];
    }
}

include('header.php');
echo booking_css();
?>
<style>
  .vf { max-width: 680px; margin: 0 auto; }
  .vf-verdict { display: flex; gap: 16px; align-items: center; border-radius: 14px; padding: 20px 22px; margin-bottom: 18px; border: 1px solid; }
  .vf-verdict i { font-size: 40px; }
  .vf-verdict h2 { margin: 0 0 4px; font-size: 24px; }
  .vf-verdict p { margin: 0; color: var(--cx-text-soft); }
  .vf-ok { background: #ecfbf2; border-color: rgba(46, 204, 113, .45); }
  .vf-ok i, .vf-ok h2 { color: #1b7a46 !important; }
  .vf-warn { background: #fff7e8; border-color: rgba(255, 159, 28, .5); }
  .vf-warn i, .vf-warn h2 { color: #a85d00 !important; }
  .vf-bad { background: #fff1f2; border-color: rgba(255, 88, 96, .45); }
  .vf-bad i, .vf-bad h2 { color: #b4232c !important; }
  .vf-movie { display: flex; gap: 18px; }
  .vf-movie img { width: 96px; height: 140px; object-fit: cover; border-radius: 8px; flex: 0 0 auto; }
  .vf-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; margin-top: 12px; }
  .vf-grid small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--cx-muted); }
  .vf-grid strong { color: var(--cx-text); overflow-wrap: anywhere; }
  .vf-seats { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
  @media (max-width: 575px) { .vf-movie { flex-direction: column; } .vf-grid { grid-template-columns: 1fr; } }
</style>
<div class="container cx-page">
  <div class="vf">
    <div class="vf-verdict vf-<?php echo $verdict[0]; ?>" role="status">
      <i class="fa <?php echo $verdict[1]; ?>"></i>
      <div>
        <h2><?php echo e($verdict[2]); ?></h2>
        <p><?php echo e($verdict[3]); ?></p>
      </div>
    </div>

    <?php if ($ticket): ?>
      <div class="cx-card">
        <div class="vf-movie">
          <img src="Admin/upload-images/<?php echo e($ticket['user_movie_img']); ?>" alt="">
          <div style="min-width:0;flex:1;">
            <h3 style="margin:0;text-transform:uppercase;"><?php echo e($ticket['user_movie_name']); ?></h3>
            <div class="vf-grid">
              <div><small>Cinema</small><strong><?php echo e($ticket['user_movie_cinema']); ?></strong></div>
              <div><small>Hall</small><strong><?php echo e($ticket['hall_name'] ?: '-'); ?></strong></div>
              <div><small>Date</small><strong><?php echo e($verify_date); ?></strong></div>
              <div><small>Time</small><strong><?php echo e($verify_time); ?></strong></div>
              <div><small>Booking</small><strong style="font-family:monospace;"><?php echo e($ticket['booking_ref']); ?></strong></div>
              <div><small>Name</small><strong><?php echo e(mask_name($ticket['user_name'])); ?></strong></div>
            </div>
          </div>
        </div>
        <div style="margin-top:16px;">
          <small class="cx-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:1px;">Seats (<?php echo $ticket['seats'] ? count($ticket['seats']) : e($ticket['user_seat']); ?>)</small>
          <div class="vf-seats">
            <?php foreach ($ticket['seats'] as $seat): ?>
              <span class="cx-badge"><?php echo e($seat['seat_label']); ?> &middot; <?php echo e($seat['seat_class']); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <p class="cx-muted" style="font-size:12px;text-align:center;margin-top:16px;">The QR code on every CineMax e-ticket carries a digital signature. Only tickets issued by CineMax Karachi pass this check; entry is confirmed when staff scan it at the door.</p>
  </div>
</div>
<?php include('footer.php'); ?>
