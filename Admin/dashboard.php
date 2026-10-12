<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/booking.php';

$kpi = db_one(
    "SELECT COUNT(*) AS bookings,
            COALESCE(SUM(b.status = 'Cancelled'), 0) AS cancelled,
            COALESCE(SUM(b.total_amount), 0) AS gross,
            COALESCE(SUM(" . REFUNDED_SQL . "), 0) AS refunded,
            COALESCE(SUM(CASE WHEN b.refund_status = 'Pending' THEN b.refund_amount ELSE 0 END), 0) AS pending_amount,
            COALESCE(SUM(b.refund_status = 'Pending'), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN b.status <> 'Cancelled' THEN " . TICKETS_SQL . " ELSE 0 END), 0) AS tickets,
            COALESCE(SUM(DATE(b.booked_at) = CURDATE()), 0) AS today
     FROM booking_details b"
);
$users = (int) db_one('SELECT COUNT(*) AS n FROM user_reg_login')['n'];
$revenue = (int) $kpi['gross'] - (int) $kpi['refunded'];

$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = 0;
}
foreach (db_all("SELECT DATE(b.booked_at) AS d, SUM(b.total_amount - " . REFUNDED_SQL . ") AS amount FROM booking_details b WHERE b.booked_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(b.booked_at)") as $row) {
    $days[$row['d']] = (int) $row['amount'];
}

$top_movies = db_all(
    "SELECT TRIM(b.user_movie_name) AS movie, SUM(b.total_amount - " . REFUNDED_SQL . ") AS amount
     FROM booking_details b GROUP BY TRIM(b.user_movie_name) ORDER BY amount DESC LIMIT 5"
);

$classes = array_fill_keys(SEAT_CLASSES, 0);
foreach (db_all('SELECT seat_class, COUNT(*) AS n FROM booking_seats GROUP BY seat_class') as $row) {
    $classes[$row['seat_class']] = ($classes[$row['seat_class']] ?? 0) + (int) $row['n'];
}
foreach (db_all("SELECT SUBSTRING_INDEX(b.user_price, ' ', 1) AS cls, SUM(" . TICKETS_SQL . ") AS n FROM booking_details b WHERE b.show_id IS NULL AND b.status <> 'Cancelled' AND b.user_price REGEXP '^(Economy|Business|First)' GROUP BY cls") as $row) {
    $classes[$row['cls']] += (int) $row['n'];
}

$statuses = array_column(db_all('SELECT status, COUNT(*) AS n FROM booking_details GROUP BY status'), 'n', 'status');
$recent = db_all('SELECT booking_ref, user_name, user_movie_name, user_date, user_movie_dur, total_amount, status, payment_method, booked_at FROM booking_details ORDER BY booked_at DESC, booking_id DESC LIMIT 8');

$tiles = [
    ['Total Revenue', money($revenue), 'fa-money', 1, 'Net of refunds paid'],
    ['Tickets Sold', number_format($kpi['tickets']), 'fa-ticket', 2, 'Seats in active bookings'],
    ['Total Bookings', number_format($kpi['bookings']), 'fa-book', 3, 'All time'],
    ['Cancelled', number_format($kpi['cancelled']), 'fa-times-circle', 4, $kpi['bookings'] ? round($kpi['cancelled'] * 100 / $kpi['bookings']) . '% of bookings' : '-'],
    ['Total Refunded', money($kpi['refunded']), 'fa-undo', 1, 'Approved refunds'],
    ['Pending Refunds', money($kpi['pending_amount']), 'fa-hourglass-half', 2, $kpi['pending_count'] . ' waiting for approval'],
    ['Users', number_format($users), 'fa-users', 3, 'Registered customers'],
    ["Today's Bookings", number_format($kpi['today']), 'fa-calendar', 4, date('D, d M Y')],
];

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap">
      <h2 class="h5 no-margin-bottom">Dashboard - <?php echo e($_SESSION['admin']); ?></h2>
      <div class="btn-group btn-group-sm mt-2 mt-md-0">
        <a href="shows.php" class="btn btn-outline-secondary"><i class="fa fa-film"></i> Shows</a>
        <a href="refunds.php" class="btn btn-outline-secondary"><i class="fa fa-undo"></i> Refunds<?php echo $kpi['pending_count'] ? ' <span class="badge badge-warning">' . (int) $kpi['pending_count'] . '</span>' : ''; ?></a>
        <a href="scan_ticket.php" class="btn btn-outline-secondary"><i class="fa fa-qrcode"></i> Scan</a>
        <a href="reports.php" class="btn btn-outline-secondary"><i class="fa fa-bar-chart"></i> Reports</a>
      </div>
    </div>
  </div>

  <section class="no-padding-top no-padding-bottom">
    <div class="container-fluid">
      <div class="row">
        <?php foreach ($tiles as $tile): ?>
          <div class="col-md-3 col-sm-6">
            <div class="statistic-block block">
              <div class="progress-details d-flex align-items-end justify-content-between">
                <div class="title">
                  <div class="icon"><i class="fa <?php echo $tile[2]; ?>"></i></div><strong><?php echo e($tile[0]); ?></strong>
                </div>
                <div class="number dashtext-<?php echo $tile[3]; ?>" style="font-size:1.4rem;"><?php echo e($tile[1]); ?></div>
              </div>
              <small class="text-muted"><?php echo e($tile[4]); ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="no-padding-bottom">
    <div class="container-fluid">
      <div class="row">
        <div class="col-lg-8">
          <div class="block">
            <div class="title"><strong>Revenue - last 7 days</strong></div>
            <canvas id="chartRevenue" height="120"></canvas>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="block">
            <div class="title"><strong>Booking status</strong></div>
            <canvas id="chartStatus" height="210"></canvas>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="block">
            <div class="title"><strong>Top 5 movies by revenue</strong></div>
            <canvas id="chartMovies" height="120"></canvas>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="block">
            <div class="title"><strong>Seat class split</strong></div>
            <canvas id="chartClasses" height="210"></canvas>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="margin-bottom-sm">
    <div class="container-fluid">
      <div class="block">
        <div class="title d-flex justify-content-between"><strong>Recent bookings</strong><a href="booking.php">View all</a></div>
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead><tr><th>Ref</th><th>Customer</th><th>Movie</th><th>Show</th><th>Total</th><th>Payment</th><th>Status</th><th>Booked</th></tr></thead>
            <tbody>
              <?php if (!$recent): ?><tr><td colspan="8" class="text-center">No bookings yet.</td></tr><?php endif; ?>
              <?php foreach ($recent as $r): ?>
                <tr>
                  <td><?php echo e($r['booking_ref']); ?></td>
                  <td><?php echo e($r['user_name']); ?></td>
                  <td><?php echo e($r['user_movie_name']); ?></td>
                  <td><small><?php echo e($r['user_date'] . ' ' . $r['user_movie_dur']); ?></small></td>
                  <td><?php echo money($r['total_amount']); ?></td>
                  <td><?php echo e($r['payment_method'] ?: '-'); ?></td>
                  <td><span class="badge badge-<?php echo ['Booked' => 'success', 'Used' => 'info', 'Cancelled' => 'danger'][$r['status']] ?? 'secondary'; ?>"><?php echo e($r['status']); ?></span></td>
                  <td><small><?php echo e(date('d M, g:i A', strtotime($r['booked_at']))); ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.Chart) return;
  Chart.defaults.global.defaultFontColor = '#6b7084';
  var palette = ['#ff55a5', '#864DD9', '#3fa9f5', '#f5c542', '#2ecc71'];
  var pkr = function (v) { return 'PKR ' + Number(v).toLocaleString('en-US'); };
  var money = { callbacks: { label: function (t, d) { return pkr(d.datasets[t.datasetIndex].data[t.index]); } } };
  var grid = { color: 'rgba(31,34,48,0.07)' };

  new Chart(document.getElementById('chartRevenue'), {
    type: 'line',
    data: {
      labels: <?php echo json_encode(array_map(function ($d) { return date('D d M', strtotime($d)); }, array_keys($days))); ?>,
      datasets: [{ data: <?php echo json_encode(array_values($days)); ?>, borderColor: '#ff55a5', backgroundColor: 'rgba(255,85,165,0.15)', pointBackgroundColor: '#ff55a5', lineTension: 0.3 }]
    },
    options: { legend: { display: false }, tooltips: money, scales: { yAxes: [{ gridLines: grid, ticks: { beginAtZero: true, callback: pkr } }], xAxes: [{ gridLines: grid }] } }
  });

  new Chart(document.getElementById('chartMovies'), {
    type: 'horizontalBar',
    data: {
      labels: <?php echo json_encode(array_column($top_movies, 'movie')); ?>,
      datasets: [{ data: <?php echo json_encode(array_map('intval', array_column($top_movies, 'amount'))); ?>, backgroundColor: palette }]
    },
    options: { legend: { display: false }, tooltips: money, scales: { xAxes: [{ gridLines: grid, ticks: { beginAtZero: true, callback: pkr } }], yAxes: [{ gridLines: { display: false } }] } }
  });

  new Chart(document.getElementById('chartClasses'), {
    type: 'doughnut',
    data: { labels: <?php echo json_encode(array_keys($classes)); ?>, datasets: [{ data: <?php echo json_encode(array_values($classes)); ?>, backgroundColor: ['#3fa9f5', '#864DD9', '#f5c542'], borderWidth: 0 }] },
    options: { legend: { position: 'bottom' } }
  });

  new Chart(document.getElementById('chartStatus'), {
    type: 'pie',
    data: { labels: <?php echo json_encode(array_keys($statuses)); ?>, datasets: [{ data: <?php echo json_encode(array_map('intval', array_values($statuses))); ?>, backgroundColor: <?php echo json_encode(array_map(function ($s) { return ['Booked' => '#2ecc71', 'Used' => '#3fa9f5', 'Cancelled' => '#ff5860'][$s] ?? '#888'; }, array_keys($statuses))); ?>, borderWidth: 0 }] },
    options: { legend: { position: 'bottom' } }
  });
});
</script>
<?php include('admin_footer.php'); ?>
