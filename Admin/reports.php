<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/booking.php';

function report_date($value, $fallback)
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : $fallback;
}

function csv_cell($value)
{
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

$f = [
    'from' => report_date($_GET['from'] ?? '', date('Y-m-01')),
    'to' => report_date($_GET['to'] ?? '', date('Y-m-d')),
    'movie' => trim($_GET['movie'] ?? ''),
    'cinema' => trim($_GET['cinema'] ?? ''),
];
if ($f['from'] > $f['to']) {
    list($f['from'], $f['to']) = [$f['to'], $f['from']];
}

$where = 'b.booked_at BETWEEN ? AND ?';
$types = 'ss';
$params = [$f['from'] . ' 00:00:00', $f['to'] . ' 23:59:59'];
if ($f['movie'] !== '') {
    $where .= ' AND TRIM(b.user_movie_name) = ?';
    $types .= 's';
    $params[] = $f['movie'];
}
if ($f['cinema'] !== '') {
    $where .= ' AND TRIM(b.user_movie_cinema) = ?';
    $types .= 's';
    $params[] = $f['cinema'];
}

$measures = "COUNT(*) AS bookings,
    COALESCE(SUM(CASE WHEN b.status <> 'Cancelled' THEN " . TICKETS_SQL . " ELSE 0 END), 0) AS tickets,
    COALESCE(SUM(b.total_amount), 0) AS gross,
    COALESCE(SUM(" . REFUNDED_SQL . "), 0) AS refunded,
    COALESCE(SUM(b.total_amount - " . REFUNDED_SQL . "), 0) AS net,
    COALESCE(SUM(CASE WHEN b.refund_status = 'Pending' THEN b.refund_amount ELSE 0 END), 0) AS pending,
    COALESCE(SUM(CASE WHEN b.status <> 'Cancelled' THEN b.snacks_amount ELSE 0 END), 0) AS snacks,
    COALESCE(SUM(CASE WHEN b.status <> 'Cancelled' THEN b.discount_amount ELSE 0 END), 0) AS discounts,
    COALESCE(SUM(b.status = 'Cancelled'), 0) AS cancelled";

if (($_GET['export'] ?? '') === 'csv') {
    $rows = db_all(
        "SELECT b.booking_ref, b.booked_at, b.user_name, b.user_email, b.user_movie_name, b.user_movie_cinema, b.user_date, b.user_movie_dur,
                b.user_seat, " . TICKETS_SQL . " AS tickets, b.status, b.payment_method, b.promo_code, b.snacks_amount, b.discount_amount,
                b.total_amount, b.refund_status, b.refund_amount, b.total_amount - " . REFUNDED_SQL . " AS net
         FROM booking_details b WHERE $where ORDER BY b.booked_at",
        $types,
        $params
    );
    log_activity('Exported report CSV', $f['from'] . ' to ' . $f['to'] . ' (' . count($rows) . ' rows)');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bookings_' . $f['from'] . '_to_' . $f['to'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Booking Ref', 'Booked At', 'Customer', 'Email', 'Movie', 'Cinema', 'Show Date', 'Show Time', 'Seats', 'Tickets', 'Status', 'Payment', 'Promo', 'Snacks', 'Discount', 'Total', 'Refund Status', 'Refund Amount', 'Net Revenue']);
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_cell', array_values($row)));
    }
    fclose($out);
    exit;
}

$total = db_one("SELECT $measures FROM booking_details b WHERE $where", $types, $params);
$by_movie = db_all("SELECT TRIM(b.user_movie_name) AS name, $measures FROM booking_details b WHERE $where GROUP BY TRIM(b.user_movie_name) ORDER BY net DESC", $types, $params);
$by_cinema = db_all("SELECT TRIM(b.user_movie_cinema) AS name, $measures FROM booking_details b WHERE $where GROUP BY TRIM(b.user_movie_cinema) ORDER BY net DESC", $types, $params);
$movies = array_column(db_all('SELECT DISTINCT TRIM(user_movie_name) AS name FROM booking_details ORDER BY name'), 'name');
$cinemas = array_column(db_all('SELECT TRIM(cinema_name) AS name FROM cinema ORDER BY cinema_name'), 'name');
$export_url = 'reports.php?' . http_build_query(array_filter($f, 'strlen') + ['export' => 'csv']);

$cards = [
    ['Net revenue', money($total['net']), 1],
    ['Gross sales', money($total['gross']), 2],
    ['Tickets sold', number_format($total['tickets']), 3],
    ['Bookings', number_format($total['bookings']), 4],
    ['Refunds paid', money($total['refunded']), 1],
    ['Refunds pending', money($total['pending']), 2],
    ['Snacks revenue', money($total['snacks']), 3],
    ['Discounts given', money($total['discounts']), 4],
];

include('admin_header.php');
?>
<style>
  @media print {
    #sidebar, header.header, .no-print, footer.footer { display: none !important; }
    .page-content { width: 100% !important; margin: 0 !important; padding: 0 !important; background: #fff !important; color: #000 !important; }
    .block { background: #fff !important; color: #000 !important; box-shadow: none !important; border: 1px solid #ccc; }
    .table, .table td, .table th, .number, strong, .text-muted { color: #000 !important; }
  }
</style>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap">
      <h2 class="h5 no-margin-bottom">Reports &middot; <?php echo e(date('d M Y', strtotime($f['from']))); ?> to <?php echo e(date('d M Y', strtotime($f['to']))); ?><?php echo $f['movie'] ? ' &middot; ' . e($f['movie']) : ''; ?><?php echo $f['cinema'] ? ' &middot; ' . e($f['cinema']) : ''; ?></h2>
      <div class="no-print mt-2 mt-md-0">
        <a href="<?php echo e($export_url); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa fa-download"></i> Export CSV</a>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
      </div>
    </div>
  </div>

  <div class="col-lg-12 no-print">
    <div class="block">
      <form method="get" action="reports.php" class="form-row align-items-end">
        <div class="form-group col-md-2">
          <label class="form-control-label">From</label>
          <input type="date" name="from" class="form-control" value="<?php echo e($f['from']); ?>">
        </div>
        <div class="form-group col-md-2">
          <label class="form-control-label">To</label>
          <input type="date" name="to" class="form-control" value="<?php echo e($f['to']); ?>">
        </div>
        <div class="form-group col-md-3">
          <label class="form-control-label">Movie</label>
          <select name="movie" class="form-control">
            <option value="">All movies</option>
            <?php foreach ($movies as $movie): ?><option <?php echo $f['movie'] === $movie ? 'selected' : ''; ?>><?php echo e($movie); ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label class="form-control-label">Cinema</label>
          <select name="cinema" class="form-control">
            <option value="">All cinemas</option>
            <?php foreach ($cinemas as $cinema): ?><option <?php echo $f['cinema'] === $cinema ? 'selected' : ''; ?>><?php echo e($cinema); ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-2">
          <button type="submit" class="btn btn-secondary" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);"><i class="fa fa-filter"></i> Apply</button>
          <a href="reports.php" class="btn btn-link">Reset</a>
        </div>
      </form>
      <div class="btn-group btn-group-sm">
        <?php foreach (['Today' => [date('Y-m-d'), date('Y-m-d')], 'Last 7 days' => [date('Y-m-d', strtotime('-6 day')), date('Y-m-d')], 'This month' => [date('Y-m-01'), date('Y-m-d')], 'This year' => [date('Y-01-01'), date('Y-m-d')]] as $label => $range): ?>
          <a class="btn btn-outline-secondary" href="reports.php?<?php echo e(http_build_query(['from' => $range[0], 'to' => $range[1], 'movie' => $f['movie'], 'cinema' => $f['cinema']])); ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <section class="no-padding-top no-padding-bottom">
    <div class="container-fluid">
      <div class="row">
        <?php foreach ($cards as $card): ?>
          <div class="col-md-3 col-sm-6">
            <div class="statistic-block block">
              <div class="progress-details d-flex align-items-end justify-content-between">
                <div class="title"><strong><?php echo e($card[0]); ?></strong></div>
                <div class="number dashtext-<?php echo $card[2]; ?>" style="font-size:1.3rem;"><?php echo e($card[1]); ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="text-muted small">Net revenue = gross sales minus refunds paid. <?php echo (int) $total['cancelled']; ?> cancelled booking(s) in this period. Tickets, snacks and discounts exclude cancelled bookings.</p>
    </div>
  </section>

  <?php foreach (['Per movie' => $by_movie, 'Per cinema' => $by_cinema] as $title => $table): ?>
    <div class="col-lg-12">
      <div class="block">
        <div class="title"><strong><?php echo $title; ?></strong></div>
        <div class="table-responsive">
          <table class="table table-striped table-hover table-sm">
            <thead>
              <tr><th><?php echo $title === 'Per movie' ? 'Movie' : 'Cinema'; ?></th><th class="text-right">Bookings</th><th class="text-right">Tickets</th><th class="text-right">Gross</th><th class="text-right">Snacks</th><th class="text-right">Discounts</th><th class="text-right">Refunded</th><th class="text-right">Net</th></tr>
            </thead>
            <tbody>
              <?php if (!$table): ?><tr><td colspan="8" class="text-center">No bookings in this period.</td></tr><?php endif; ?>
              <?php foreach ($table as $row): ?>
                <tr>
                  <td><?php echo e($row['name']); ?></td>
                  <td class="text-right"><?php echo (int) $row['bookings']; ?></td>
                  <td class="text-right"><?php echo (int) $row['tickets']; ?></td>
                  <td class="text-right"><?php echo money($row['gross']); ?></td>
                  <td class="text-right"><?php echo money($row['snacks']); ?></td>
                  <td class="text-right"><?php echo money($row['discounts']); ?></td>
                  <td class="text-right"><?php echo money($row['refunded']); ?></td>
                  <td class="text-right"><strong><?php echo money($row['net']); ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <?php if ($table): ?>
              <tfoot>
                <tr style="font-weight:700;">
                  <td>Total</td><td class="text-right"><?php echo (int) $total['bookings']; ?></td><td class="text-right"><?php echo (int) $total['tickets']; ?></td>
                  <td class="text-right"><?php echo money($total['gross']); ?></td><td class="text-right"><?php echo money($total['snacks']); ?></td>
                  <td class="text-right"><?php echo money($total['discounts']); ?></td><td class="text-right"><?php echo money($total['refunded']); ?></td>
                  <td class="text-right"><?php echo money($total['net']); ?></td>
                </tr>
              </tfoot>
            <?php endif; ?>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php include('admin_footer.php'); ?>
