<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/booking.php';

function valid_date_param($value)
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : '';
}

$f = [
    'from' => valid_date_param($_GET['from'] ?? ''),
    'to' => valid_date_param($_GET['to'] ?? ''),
    'movie' => trim($_GET['movie'] ?? ''),
    'status' => in_array($_GET['status'] ?? '', ['Booked', 'Used', 'Cancelled'], true) ? $_GET['status'] : '',
    'q' => trim($_GET['q'] ?? ''),
];

$where = ['1 = 1'];
$types = '';
$params = [];
if ($f['from']) { $where[] = 'b.booked_at >= ?'; $types .= 's'; $params[] = $f['from'] . ' 00:00:00'; }
if ($f['to']) { $where[] = 'b.booked_at <= ?'; $types .= 's'; $params[] = $f['to'] . ' 23:59:59'; }
if ($f['movie'] !== '') { $where[] = 'TRIM(b.user_movie_name) = ?'; $types .= 's'; $params[] = $f['movie']; }
if ($f['status']) { $where[] = 'b.status = ?'; $types .= 's'; $params[] = $f['status']; }
if ($f['q'] !== '') {
    $where[] = '(b.booking_ref LIKE ? OR b.user_name LIKE ? OR b.user_email LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
$where_sql = implode(' AND ', $where);

$per_page = 25;
$summary = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(b.total_amount), 0) AS amount FROM booking_details b WHERE $where_sql", $types, $params);
$pages = max(1, (int) ceil($summary['n'] / $per_page));
$page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));

$rows = db_all(
    "SELECT b.*, s.hall_name FROM booking_details b LEFT JOIN shows s ON s.show_id = b.show_id
     WHERE $where_sql ORDER BY b.booking_id DESC LIMIT $per_page OFFSET " . (($page - 1) * $per_page),
    $types,
    $params
);

$seats = [];
$snacks = [];
if ($rows) {
    $ids = implode(',', array_map('intval', array_column($rows, 'booking_id')));
    foreach (db_all("SELECT booking_id, seat_label, seat_class, price FROM booking_seats WHERE booking_id IN ($ids) ORDER BY seat_label") as $s) {
        $seats[$s['booking_id']][] = $s['seat_label'] . ' (' . $s['seat_class'] . ', ' . money($s['price']) . ')';
    }
    foreach (db_all("SELECT bs.booking_id, bs.qty, bs.price, sn.name FROM booking_snacks bs JOIN snacks sn ON sn.snack_id = bs.snack_id WHERE bs.booking_id IN ($ids)") as $s) {
        $snacks[$s['booking_id']][] = $s['name'] . ' x' . $s['qty'] . ' (' . money($s['qty'] * $s['price']) . ')';
    }
}

$movies = array_column(db_all('SELECT DISTINCT TRIM(user_movie_name) AS name FROM booking_details ORDER BY name'), 'name');
$query = function ($changes) use ($f) {
    return 'booking.php?' . http_build_query(array_filter(array_merge($f, $changes), 'strlen'));
};

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">All Bookings</h2>
    </div>
  </div>

  <div class="col-lg-12">
    <div class="block">
      <form method="get" action="booking.php" class="form-row align-items-end">
        <div class="form-group col-md-3">
          <label class="form-control-label">Booking ref / name / email</label>
          <input type="text" name="q" class="form-control" value="<?php echo e($f['q']); ?>" placeholder="CNX-2026-00001">
        </div>
        <div class="form-group col-md-2">
          <label class="form-control-label">Booked from</label>
          <input type="date" name="from" class="form-control" value="<?php echo e($f['from']); ?>">
        </div>
        <div class="form-group col-md-2">
          <label class="form-control-label">Booked to</label>
          <input type="date" name="to" class="form-control" value="<?php echo e($f['to']); ?>">
        </div>
        <div class="form-group col-md-2">
          <label class="form-control-label">Movie</label>
          <select name="movie" class="form-control">
            <option value="">All movies</option>
            <?php foreach ($movies as $movie): ?>
              <option <?php echo $f['movie'] === $movie ? 'selected' : ''; ?>><?php echo e($movie); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-1">
          <label class="form-control-label">Status</label>
          <select name="status" class="form-control">
            <option value="">All</option>
            <?php foreach (['Booked', 'Used', 'Cancelled'] as $status): ?>
              <option <?php echo $f['status'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-2">
          <button type="submit" class="btn btn-secondary" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);"><i class="fa fa-filter"></i> Filter</button>
          <a href="booking.php" class="btn btn-link">Reset</a>
        </div>
      </form>
      <small class="text-muted"><?php echo (int) $summary['n']; ?> bookings &middot; <?php echo money($summary['amount']); ?> total</small>
    </div>
  </div>

  <div class="col-lg-12">
    <div class="block">
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr><th>Ref</th><th>Customer</th><th>Movie</th><th>Show</th><th>Seats</th><th>Total</th><th>Status</th><th>Booked</th><th></th></tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?><tr><td colspan="9" class="text-center">No bookings match these filters.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r):
                $detail = [
                    'Booking ref' => $r['booking_ref'],
                    'Customer' => $r['user_name'] . ' (' . $r['user_email'] . ')',
                    'Movie' => $r['user_movie_name'],
                    'Cinema' => $r['user_movie_cinema'] . ($r['hall_name'] ? ' - ' . $r['hall_name'] : ''),
                    'Show' => $r['user_date'] . ' at ' . $r['user_movie_dur'],
                    'Seats' => isset($seats[$r['booking_id']]) ? implode(', ', $seats[$r['booking_id']]) : $r['user_seat'] . ($r['user_price'] ? ' (' . $r['user_price'] . ')' : ''),
                    'Snacks' => isset($snacks[$r['booking_id']]) ? implode(', ', $snacks[$r['booking_id']]) : 'None',
                    'Snacks amount' => money($r['snacks_amount']),
                    'Discount' => $r['discount_amount'] ? money($r['discount_amount']) . ' (' . $r['promo_code'] . ')' : 'None',
                    'Total paid' => money($r['total_amount']),
                    'Payment' => ($r['payment_method'] ?: 'Card') . ($r['user_card'] ? ' ****' . substr($r['user_card'], -4) : ''),
                    'Status' => $r['status'] . ($r['checked_in_at'] ? ' (checked in ' . date('d M, g:i A', strtotime($r['checked_in_at'])) . ')' : ''),
                    'Refund' => $r['refund_status'] === 'None' ? 'None' : $r['refund_status'] . ' - ' . money($r['refund_amount']) . ($r['refund_reason'] ? ' (' . $r['refund_reason'] . ')' : ''),
                    'Booked at' => date('d M Y, g:i A', strtotime($r['booked_at'])),
                ]; ?>
              <tr>
                <td><?php echo e($r['booking_ref']); ?></td>
                <td><?php echo e($r['user_name']); ?><br><small><?php echo e($r['user_email']); ?></small></td>
                <td><?php echo e($r['user_movie_name']); ?></td>
                <td><small><?php echo e($r['user_date']); ?><br><?php echo e($r['user_movie_dur']); ?></small></td>
                <td><small><?php echo e($r['user_seat']); ?></small></td>
                <td><?php echo money($r['total_amount']); ?></td>
                <td>
                  <span class="badge badge-<?php echo ['Booked' => 'success', 'Used' => 'info', 'Cancelled' => 'danger'][$r['status']] ?? 'secondary'; ?>"><?php echo e($r['status']); ?></span>
                  <?php if ($r['refund_status'] !== 'None'): ?><br><small><?php echo e($r['refund_status']); ?></small><?php endif; ?>
                </td>
                <td><small><?php echo e(date('d M Y', strtotime($r['booked_at']))); ?></small></td>
                <td><button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#bookingModal" data-booking="<?php echo e(json_encode($detail)); ?>">View</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1): ?>
        <nav><ul class="pagination pagination-sm mb-0">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo e($query(['page' => $p])); ?>"><?php echo $p; ?></a></li>
          <?php endfor; ?>
        </ul></nav>
      <?php endif; ?>
    </div>
  </div>

  <div class="modal fade" id="bookingModal" tabindex="-1" role="dialog" aria-labelledby="bookingModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content" style="background:#2d3035;color:#ddd;">
        <div class="modal-header">
          <h5 class="modal-title" id="bookingModalTitle">Booking details</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body"><table class="table table-sm mb-0" id="bookingModalTable"></table></div>
      </div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  $('#bookingModal').on('show.bs.modal', function (e) {
    var data = $(e.relatedTarget).data('booking');
    var table = document.getElementById('bookingModalTable');
    table.innerHTML = '';
    document.getElementById('bookingModalTitle').textContent = 'Booking ' + data['Booking ref'];
    Object.keys(data).forEach(function (k) {
      var tr = table.insertRow();
      var a = tr.insertCell(); a.textContent = k; a.style.color = '#8a8d93'; a.style.width = '160px';
      var b = tr.insertCell(); b.textContent = data[k];
    });
  });
});
</script>
<?php include('admin_footer.php'); ?>
