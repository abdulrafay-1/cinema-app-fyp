<?php
require_once __DIR__ . '/includes/booking.php';

require_login();
$ticket = load_booking($_GET['ref'] ?? '');
if (!$ticket || !owns_booking($ticket)) {
    flash('danger', 'Ticket not found.');
    redirect('userbooking.php');
}
$success = !empty($success_page);

include('header.php');
echo booking_css();
?>
<div class="container cx-page">
  <?php if ($success): ?>
    <div class="text-center" style="margin-bottom:26px;">
      <div style="width:76px;height:76px;border-radius:50%;background:var(--cx-selected);display:inline-flex;align-items:center;justify-content:center;font-size:40px;color:#0b2e1a;margin-bottom:12px;"><i class="fa fa-check"></i></div>
      <h2>Booking confirmed!</h2>
      <p class="cx-muted">Your e-ticket is ready. You earned <strong style="color:#fff;"><?php echo loyalty_points_for($ticket['total_amount']); ?></strong> loyalty points.</p>
    </div>
  <?php else: ?>
    <h2 style="margin-bottom:18px;">Your e-ticket</h2>
  <?php endif; ?>

  <?php include('partials/ticket_card.php'); ?>

  <div class="text-center" style="margin-top:26px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
    <button type="button" class="cx-btn" onclick="window.print()"><i class="fa fa-print"></i> Print / Save as PDF</button>
    <a class="cx-btn cx-btn-ghost" href="userbooking.php"><i class="fa fa-ticket-alt"></i> My bookings</a>
    <a class="cx-btn cx-btn-ghost" href="index.php"><i class="fa fa-film"></i> Book another movie</a>
  </div>
</div>
<?php include('footer.php'); ?>
