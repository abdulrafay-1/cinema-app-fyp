<?php
list($ticket_date, $ticket_time) = booking_when($ticket);
$ticket_seats = $ticket['seats']
    ? implode(', ', array_map(function ($s) { return $s['seat_label'] . ' (' . $s['seat_class'] . ')'; }, $ticket['seats']))
    : $ticket['user_seat'] . ($ticket['user_price'] ? ' · ' . $ticket['user_price'] : '');
?>
<style>
  .tk { display: flex; flex-wrap: wrap; background: #fff; color: #222; border-radius: 14px; overflow: hidden; border: 1px solid var(--cx-line); box-shadow: 0 12px 32px rgba(31, 34, 48, .12); position: relative; max-width: 900px; margin: 0 auto; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .tk-main { flex: 1 1 520px; display: flex; gap: 20px; padding: 24px; min-width: 0; }
  .tk-main img { width: 130px; height: 190px; object-fit: cover; border-radius: 8px; flex: 0 0 auto; }
  .tk-brand { font-size: 11px; letter-spacing: 3px; color: #ff5860; font-weight: 700; margin-bottom: 4px; }
  .tk h2 { color: #111 !important; font-size: 26px; margin: 0 0 14px; font-family: 'Ubuntu', sans-serif; text-transform: uppercase; }
  .tk-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 22px; }
  .tk-grid div { min-width: 0; }
  .tk-grid small { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888; }
  .tk-grid strong { display: block; font-size: 14px; color: #111; overflow-wrap: anywhere; }
  .tk-stub { flex: 0 0 230px; border-left: 2px dashed #ccc; padding: 24px 18px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; background: linear-gradient(180deg, #fff5f9, #fff); position: relative; }
  .tk-stub::before, .tk-stub::after { content: ''; position: absolute; left: -13px; width: 24px; height: 24px; border-radius: 50%; background: var(--cx-bg); }
  .tk-stub::before { top: -12px; }
  .tk-stub::after { bottom: -12px; }
  .tk-qr { background: #fff; padding: 8px; border-radius: 6px; }
  .tk-qr img, .tk-qr canvas { display: block; }
  .tk-ref { font-family: monospace; font-size: 16px; font-weight: 700; letter-spacing: 1px; color: #111; }
  .tk-stamp { position: absolute; top: 50%; left: 40%; transform: translate(-50%, -50%) rotate(-14deg); font-size: 52px; font-weight: 900; letter-spacing: 6px; border: 6px solid; padding: 4px 22px; border-radius: 10px; opacity: .22; pointer-events: none; }
  .tk-stamp.Cancelled { color: #d0021b; }
  .tk-stamp.Used { color: #1565c0; }
  @media (max-width: 575px) {
    .tk-main { flex-direction: column; padding: 18px; }
    .tk-main img { width: 100px; height: 145px; }
    .tk-stub { flex: 1 1 100%; border-left: 0; border-top: 2px dashed #ccc; }
    .tk-stub::before, .tk-stub::after { display: none; }
  }
  @media print {
    body { background: #fff !important; }
    body > *:not(.cx-page), .cx-page > *:not(.tk) { display: none !important; }
    .cx-page { margin: 0 !important; padding: 0 !important; max-width: none !important; }
    .tk { box-shadow: none; border: 1px solid #ccc; }
    .tk-stub::before, .tk-stub::after { display: none; }
  }
</style>
<div class="tk" id="ticket">
  <div class="tk-main">
    <img src="Admin/upload-images/<?php echo e($ticket['user_movie_img']); ?>" alt="">
    <div style="min-width:0;flex:1;">
      <div class="tk-brand">E-TICKET &middot; ONLINE MOVIE BOOKING</div>
      <h2><?php echo e($ticket['user_movie_name']); ?></h2>
      <div class="tk-grid">
        <div><small>Date</small><strong><?php echo e($ticket_date); ?></strong></div>
        <div><small>Time</small><strong><?php echo e($ticket_time); ?></strong></div>
        <div><small>Cinema</small><strong><?php echo e($ticket['user_movie_cinema']); ?></strong></div>
        <div><small>Hall</small><strong><?php echo e($ticket['hall_name'] ?: '-'); ?></strong></div>
        <div style="grid-column: span 2;"><small>Seats</small><strong><?php echo e($ticket_seats); ?></strong></div>
        <?php if ($ticket['snacks']): ?>
          <div style="grid-column: span 2;"><small>Snacks</small><strong><?php echo e(implode(', ', array_map(function ($s) { return $s['name'] . ' x' . $s['qty']; }, $ticket['snacks']))); ?></strong></div>
        <?php endif; ?>
        <div><small>Name</small><strong><?php echo e($ticket['user_name']); ?></strong></div>
        <div><small>Payment</small><strong><?php echo e($ticket['payment_method'] ?: 'Card'); ?><?php echo $ticket['user_card'] ? ' &middot; ****' . e(substr($ticket['user_card'], -4)) : ''; ?></strong></div>
        <?php if ((int) $ticket['discount_amount'] > 0): ?>
          <div><small>Discount</small><strong>- <?php echo money($ticket['discount_amount']); ?> (<?php echo e($ticket['promo_code']); ?>)</strong></div>
        <?php endif; ?>
        <div><small>Total paid</small><strong><?php echo money($ticket['total_amount']); ?></strong></div>
      </div>
    </div>
  </div>
  <div class="tk-stub">
    <div class="tk-qr" id="ticket-qr" data-code="<?php echo e(ticket_verify_url($ticket['booking_ref'])); ?>"></div>
    <div class="tk-ref"><?php echo e($ticket['booking_ref']); ?></div>
    <div style="font-size:11px;color:#888;text-align:center;">Show this QR code at the entrance. Scan it with any phone to verify the ticket.</div>
  </div>
  <?php if ($ticket['status'] !== 'Booked'): ?>
    <div class="tk-stamp <?php echo e($ticket['status']); ?>"><?php echo e(strtoupper($ticket['status'])); ?></div>
  <?php endif; ?>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  (function () {
    var box = document.getElementById('ticket-qr');
    new QRCode(box, { text: box.dataset.code, width: 170, height: 170, correctLevel: QRCode.CorrectLevel.M });
  })();
</script>
