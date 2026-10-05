<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/booking.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $booking_id = (int) ($_POST['booking_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $booking = db_one('SELECT booking_ref, refund_amount FROM booking_details WHERE booking_id = ?', 'i', [$booking_id]);

    if (!csrf_ok() || !$booking) {
        flash('danger', 'Invalid request.');
    } elseif ($action === 'approve') {
        $done = db_query(
            "UPDATE booking_details SET refund_status = 'Refunded', refund_processed_at = NOW() WHERE booking_id = ? AND refund_status = 'Pending'",
            'i',
            [$booking_id]
        );
        if ($done === 1) {
            log_activity('Approved refund', $booking['booking_ref'] . ' ' . money($booking['refund_amount']));
            flash('success', 'Refund of ' . money($booking['refund_amount']) . ' for ' . $booking['booking_ref'] . ' approved.');
        } else {
            flash('danger', 'This refund was already processed.');
        }
    } elseif ($action === 'reject') {
        if ($reason === '') {
            flash('danger', 'Please give a reason for rejecting the refund.');
        } else {
            $done = db_query(
                "UPDATE booking_details SET refund_status = 'Rejected', refund_reason = ?, refund_processed_at = NOW() WHERE booking_id = ? AND refund_status = 'Pending'",
                'si',
                [mb_substr($reason, 0, 255), $booking_id]
            );
            if ($done === 1) {
                log_activity('Rejected refund', $booking['booking_ref'] . ' reason: ' . $reason);
                flash('success', 'Refund for ' . $booking['booking_ref'] . ' rejected.');
            } else {
                flash('danger', 'This refund was already processed.');
            }
        }
    }
    redirect('refunds.php?tab=' . urlencode($_POST['tab'] ?? 'Pending'));
}

$tabs = ['Pending', 'Refunded', 'Rejected'];
$tab = in_array($_GET['tab'] ?? '', $tabs, true) ? $_GET['tab'] : 'Pending';
$totals = [];
foreach (db_all("SELECT refund_status, COUNT(*) AS n, COALESCE(SUM(refund_amount), 0) AS amount FROM booking_details WHERE refund_status IN ('Pending', 'Refunded', 'Rejected') GROUP BY refund_status") as $row) {
    $totals[$row['refund_status']] = $row;
}
$rows = db_all(
    'SELECT booking_id, booking_ref, user_name, user_email, user_movie_name, user_date, user_movie_dur, total_amount, refund_amount,
            refund_reason, cancelled_at, refund_processed_at, payment_method
     FROM booking_details WHERE refund_status = ? ORDER BY cancelled_at DESC',
    's',
    [$tab]
);

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">Refunds</h2>
    </div>
  </div>

  <section class="no-padding-top no-padding-bottom">
    <div class="container-fluid">
      <?php echo render_flash(); ?>
      <div class="row">
        <?php foreach (['Pending' => ['dashtext-2', 'fa-hourglass-half'], 'Refunded' => ['dashtext-3', 'fa-check'], 'Rejected' => ['dashtext-1', 'fa-ban']] as $name => $style): ?>
          <div class="col-md-4 col-sm-6">
            <div class="statistic-block block">
              <div class="progress-details d-flex align-items-end justify-content-between">
                <div class="title">
                  <div class="icon"><i class="fa <?php echo $style[1]; ?>"></i></div><strong><?php echo $name; ?> (<?php echo (int) ($totals[$name]['n'] ?? 0); ?>)</strong>
                </div>
                <div class="number <?php echo $style[0]; ?>"><?php echo money($totals[$name]['amount'] ?? 0); ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="col-lg-12">
    <div class="block">
      <div class="title d-flex justify-content-between align-items-center flex-wrap">
        <strong><?php echo $tab; ?> refunds</strong>
        <div class="btn-group btn-group-sm">
          <?php foreach ($tabs as $name): ?>
            <a href="refunds.php?tab=<?php echo $name; ?>" class="btn <?php echo $tab === $name ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $name; ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th>Ref</th><th>Customer</th><th>Movie / Show</th><th>Paid</th><th>Refund</th><th>Cancelled</th>
              <th><?php echo $tab === 'Pending' ? 'Action' : ($tab === 'Rejected' ? 'Reason' : 'Processed'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="7" class="text-center">No <?php echo strtolower($tab); ?> refunds.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo e($r['booking_ref']); ?></td>
                <td><?php echo e($r['user_name']); ?><br><small><?php echo e($r['user_email']); ?></small></td>
                <td><?php echo e($r['user_movie_name']); ?><br><small><?php echo e($r['user_date'] . ' ' . $r['user_movie_dur']); ?></small></td>
                <td><?php echo money($r['total_amount']); ?><br><small><?php echo e($r['payment_method']); ?></small></td>
                <td><strong><?php echo money($r['refund_amount']); ?></strong><br><small><?php echo $r['total_amount'] > 0 ? round($r['refund_amount'] * 100 / $r['total_amount']) . '%' : '-'; ?></small></td>
                <td><small><?php echo $r['cancelled_at'] ? e(date('d M Y, g:i A', strtotime($r['cancelled_at']))) : '-'; ?></small></td>
                <td style="min-width:240px;">
                  <?php if ($tab === 'Pending'): ?>
                    <form method="post" action="refunds.php" class="d-inline">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="tab" value="Pending">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="booking_id" value="<?php echo (int) $r['booking_id']; ?>">
                      <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve refund of <?php echo money($r['refund_amount']); ?>?');"><i class="fa fa-check"></i> Approve</button>
                    </form>
                    <form method="post" action="refunds.php" class="mt-2 d-flex">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="tab" value="Pending">
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="booking_id" value="<?php echo (int) $r['booking_id']; ?>">
                      <input type="text" name="reason" class="form-control form-control-sm mr-1" placeholder="Reason for rejecting" maxlength="255" required>
                      <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-ban"></i> Reject</button>
                    </form>
                  <?php elseif ($tab === 'Rejected'): ?>
                    <?php echo e($r['refund_reason']); ?>
                  <?php else: ?>
                    <small><?php echo $r['refund_processed_at'] ? e(date('d M Y, g:i A', strtotime($r['refund_processed_at']))) : '-'; ?></small>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php include('admin_footer.php'); ?>
