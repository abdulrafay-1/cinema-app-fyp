<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

// Block / Unblock: signin.php refuses users whose status is 'Blocked'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $user = db_one('SELECT id, name, email, status FROM user_reg_login WHERE id = ?', 'i', [$user_id]);

    if (!csrf_ok() || !$user) {
        flash('danger', 'Invalid request. Please try again.');
    } elseif ($action === 'block' || $action === 'unblock') {
        $new_status = $action === 'block' ? 'Blocked' : 'Active';
        db_query('UPDATE user_reg_login SET status = ? WHERE id = ?', 'si', [$new_status, $user_id]);
        log_activity($action === 'block' ? 'Blocked user' : 'Unblocked user', 'id=' . $user_id . ', ' . $user['email']);
        flash('success', $user['name'] . ' (' . $user['email'] . ') is now ' . strtolower($new_status) . '.');
    }
    redirect('user.php' . (isset($_POST['view']) ? '?view=' . $user_id : ''));
}

// the user's bookings: new ones have user_id, very old ones only have the email
$view = isset($_GET['view']) ? db_one('SELECT * FROM user_reg_login WHERE id = ?', 'i', [(int) $_GET['view']]) : null;
$view_bookings = $view ? db_all(
    'SELECT booking_ref, user_movie_name, user_date, user_movie_dur, user_movie_cinema, user_seat, total_amount, status, refund_status, booked_at
     FROM booking_details WHERE user_id = ? OR (user_id IS NULL AND user_email = ?) ORDER BY booked_at DESC',
    'is',
    [(int) $view['id'], $view['email']]
) : [];

$users = db_all(
    'SELECT u.id, u.name, u.email, u.phone, u.status, u.loyalty_points, u.created_at,
            (SELECT COUNT(*) FROM booking_details b WHERE b.user_id = u.id OR (b.user_id IS NULL AND b.user_email = u.email)) AS bookings
     FROM user_reg_login u ORDER BY u.id DESC'
);

function status_badge($status)
{
    return $status === 'Blocked' ? 'badge-danger' : 'badge-success';
}

function booking_badge($status)
{
    return ['Booked' => 'badge-primary', 'Used' => 'badge-success', 'Cancelled' => 'badge-secondary'][$status] ?? 'badge-light';
}

include('admin_header.php');
?>


<!-- must include this file in every page for page title  -->
<div class="page-content">
        <div class="page-header">
          <div class="container-fluid">
            <h2 class="h5 no-margin-bottom">User Registered</h2>
          </div>
        </div>
<!-- must include this file in every page for page title  -->

<div class="col-lg-12">
  <?php echo render_flash(); ?>
</div>

<?php if ($view): ?>
<div class="col-lg-12">
  <div class="block">
    <div class="title d-flex justify-content-between align-items-center flex-wrap">
      <strong>User #<?php echo (int) $view['id']; ?>: <?php echo e($view['name']); ?></strong>
      <a href="user.php" class="btn btn-sm btn-outline-secondary">Close</a>
    </div>
    <div class="row">
      <div class="col-md-4">
        <table class="table table-sm">
          <tr><th>Email</th><td><?php echo e($view['email']); ?></td></tr>
          <tr><th>Phone</th><td><?php echo $view['phone'] ? e($view['phone']) : '-'; ?></td></tr>
          <tr><th>Status</th><td><span class="badge <?php echo status_badge($view['status']); ?>"><?php echo e($view['status']); ?></span></td></tr>
          <tr><th>Loyalty points</th><td><strong><?php echo (int) $view['loyalty_points']; ?></strong></td></tr>
          <tr><th>Joined</th><td><?php echo e(date('d M Y', strtotime($view['created_at']))); ?></td></tr>
        </table>
        <form method="post" action="user.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="user_id" value="<?php echo (int) $view['id']; ?>">
          <input type="hidden" name="view" value="1">
          <?php if ($view['status'] === 'Blocked'): ?>
            <button type="submit" name="action" value="unblock" class="btn btn-sm btn-success"><i class="fa fa-unlock"></i> Unblock</button>
          <?php else: ?>
            <button type="submit" name="action" value="block" class="btn btn-sm btn-danger" onclick="return confirm('Block this user? They will not be able to sign in.');"><i class="fa fa-ban"></i> Block</button>
          <?php endif; ?>
        </form>
      </div>
      <div class="col-md-8">
        <strong>Bookings (<?php echo count($view_bookings); ?>)</strong>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr><th>Ref</th><th>Movie</th><th>Show</th><th>Seats</th><th>Total</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if (!$view_bookings): ?>
                <tr><td colspan="6" class="text-center">No bookings yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($view_bookings as $b): ?>
                <tr>
                  <td><?php echo e($b['booking_ref']); ?><br><small><?php echo e(date('d M Y', strtotime($b['booked_at']))); ?></small></td>
                  <td><?php echo e($b['user_movie_name']); ?><br><small><?php echo e($b['user_movie_cinema']); ?></small></td>
                  <td><small><?php echo e($b['user_date'] . ' ' . $b['user_movie_dur']); ?></small></td>
                  <td><?php echo e($b['user_seat']); ?></td>
                  <td><?php echo money($b['total_amount']); ?></td>
                  <td>
                    <span class="badge <?php echo booking_badge($b['status']); ?>"><?php echo e($b['status']); ?></span>
                    <?php if ($b['refund_status'] !== 'None'): ?><br><small>Refund: <?php echo e($b['refund_status']); ?></small><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="col-lg-12 ">
                <div class="block">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>User Name</th>
                          <th>User Email</th>
                          <th>Points</th>
                          <th>Bookings</th>
                          <th>Joined</th>
                          <th>Status</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!$users): ?>
                          <tr><td colspan="8" class="text-center">No users yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($users as $data_user): ?>
                          <tr>
                            <th scope="row"><?php echo (int) $data_user['id']; ?></th>
                            <td><?php echo e($data_user['name']); ?></td>
                            <td><?php echo e($data_user['email']); ?></td>
                            <td><?php echo (int) $data_user['loyalty_points']; ?></td>
                            <td><?php echo (int) $data_user['bookings']; ?></td>
                            <td><small><?php echo e(date('d M Y', strtotime($data_user['created_at']))); ?></small></td>
                            <td><span class="badge <?php echo status_badge($data_user['status']); ?>"><?php echo e($data_user['status']); ?></span></td>
                            <td style="white-space:nowrap;">
                              <a href="user.php?view=<?php echo (int) $data_user['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-eye"></i> View</a>
                              <form method="post" action="user.php" class="d-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $data_user['id']; ?>">
                                <?php if ($data_user['status'] === 'Blocked'): ?>
                                  <button type="submit" name="action" value="unblock" class="btn btn-sm btn-outline-success">Unblock</button>
                                <?php else: ?>
                                  <button type="submit" name="action" value="block" class="btn btn-sm btn-outline-danger" onclick="return confirm('Block this user? They will not be able to sign in.');">Block</button>
                                <?php endif; ?>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>




<?php

include('admin_footer.php');

?>
