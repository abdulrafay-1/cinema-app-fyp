<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/booking.php';

function booked_seat_count($show_id)
{
    $row = db_one('SELECT COUNT(*) AS n FROM booking_seats WHERE show_id = ?', 'i', [(int) $show_id]);
    return (int) $row['n'];
}

function validate_show_form($post, $existing)
{
    $data = [
        'movie_id' => (int) ($post['movie_id'] ?? 0),
        'cin_id' => (int) ($post['cin_id'] ?? 0),
        'show_date' => trim($post['show_date'] ?? ''),
        'show_time' => substr(trim($post['show_time'] ?? ''), 0, 5),
        'hall_name' => trim($post['hall_name'] ?? ''),
        'total_rows' => (int) ($post['total_rows'] ?? 8),
        'seats_per_row' => (int) ($post['seats_per_row'] ?? 12),
        'status' => ($post['status'] ?? 'Active') === 'Cancelled' ? 'Cancelled' : 'Active',
    ];
    $errors = [];
    if (!db_one('SELECT 1 FROM addmovie WHERE movie_id = ?', 'i', [$data['movie_id']])) {
        $errors[] = 'Please choose a movie.';
    }
    if (!db_one('SELECT 1 FROM cinema WHERE cin_id = ?', 'i', [$data['cin_id']])) {
        $errors[] = 'Please choose a cinema.';
    }
    $date = DateTime::createFromFormat('Y-m-d', $data['show_date']);
    if (!$date || $date->format('Y-m-d') !== $data['show_date']) {
        $errors[] = 'Please enter a valid show date.';
    }
    if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $data['show_time'])) {
        $errors[] = 'Please enter a valid show time.';
    }
    if ($data['hall_name'] === '' || strlen($data['hall_name']) > 50) {
        $errors[] = 'Hall name is required (max 50 characters).';
    }
    if ($data['total_rows'] < 1 || $data['total_rows'] > 12) {
        $errors[] = 'Rows must be between 1 and 12.';
    }
    if ($data['seats_per_row'] < 1 || $data['seats_per_row'] > 20) {
        $errors[] = 'Seats per row must be between 1 and 20.';
    }
    if (!$errors && !$existing && strtotime($data['show_date'] . ' ' . $data['show_time']) <= time()) {
        $errors[] = 'A new show must be in the future.';
    }
    if (!$errors) {
        $clash = db_one(
            'SELECT show_id FROM shows WHERE cin_id = ? AND hall_name = ? AND show_date = ? AND show_time = ? AND show_id <> ?',
            'isssi',
            [$data['cin_id'], $data['hall_name'], $data['show_date'], $data['show_time'], $existing ? (int) $existing['show_id'] : 0]
        );
        if ($clash) {
            $errors[] = 'That hall already has a show at this date and time.';
        }
    }
    if (!$errors && $existing && booked_seat_count($existing['show_id']) > 0
        && ((int) $existing['total_rows'] !== $data['total_rows'] || (int) $existing['seats_per_row'] !== $data['seats_per_row'])) {
        $errors[] = 'This show already has bookings, so its seat layout cannot be changed.';
    }
    return [$data, $errors];
}

$form_errors = [];
$form_values = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $show_id = (int) ($_POST['show_id'] ?? 0);
    $existing = $show_id ? get_show($show_id) : null;

    if ($action === 'delete' && $existing) {
        $booked = booked_seat_count($show_id);
        if ($booked > 0) {
            flash('danger', 'Show #' . $show_id . ' has ' . $booked . ' booked seats and cannot be deleted. Set its status to Cancelled instead.');
        } else {
            db_query('DELETE FROM seat_holds WHERE show_id = ?', 'i', [$show_id]);
            db_query('DELETE FROM shows WHERE show_id = ?', 'i', [$show_id]);
            log_activity('Deleted show', 'show_id=' . $show_id . ' ' . $existing['movie_name'] . ' ' . $existing['show_date'] . ' ' . $existing['show_time']);
            flash('success', 'Show deleted.');
        }
        redirect('shows.php');
    }

    if ($action === 'save') {
        list($form_values, $form_errors) = validate_show_form($_POST, $existing);
        if (!$form_errors) {
            $v = $form_values;
            $params = [$v['movie_id'], $v['cin_id'], $v['show_date'], $v['show_time'], $v['hall_name'], $v['total_rows'], $v['seats_per_row'], $v['status']];
            if ($existing) {
                $params[] = $show_id;
                $ok = db_query(
                    'UPDATE shows SET movie_id = ?, cin_id = ?, show_date = ?, show_time = ?, hall_name = ?, total_rows = ?, seats_per_row = ?, status = ? WHERE show_id = ?',
                    'iisssiisi',
                    $params
                );
                $label = 'Updated show';
            } else {
                $ok = db_query(
                    'INSERT INTO shows (movie_id, cin_id, show_date, show_time, hall_name, total_rows, seats_per_row, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    'iisssiis',
                    $params
                );
                $show_id = mysqli_insert_id(db());
                $label = 'Added show';
            }
            if ($ok !== false) {
                log_activity($label, 'show_id=' . $show_id . ' movie_id=' . $v['movie_id'] . ' cin_id=' . $v['cin_id'] . ' ' . $v['show_date'] . ' ' . $v['show_time'] . ' ' . $v['status']);
                flash('success', $label . ' successfully.');
                redirect('shows.php');
            }
            $form_errors[] = 'Could not save the show. Please try again.';
        }
    }
}

$editing = isset($_GET['edit']) ? get_show((int) $_GET['edit']) : null;
if ($form_values === null) {
    $form_values = $editing ? $editing : [
        'movie_id' => 0, 'cin_id' => 0, 'show_date' => date('Y-m-d', strtotime('+1 day')), 'show_time' => '19:00',
        'hall_name' => 'Hall 1', 'total_rows' => 8, 'seats_per_row' => 12, 'status' => 'Active',
    ];
}
$form_show_id = $editing ? (int) $editing['show_id'] : (int) ($_POST['show_id'] ?? 0);

$view = $_GET['view'] ?? 'upcoming';
$where = ['upcoming' => 's.show_date >= CURDATE()', 'past' => 's.show_date < CURDATE()', 'all' => '1 = 1'];
if (!isset($where[$view])) {
    $view = 'upcoming';
}
$order = $view === 'past' ? 'DESC' : 'ASC';
$shows = db_all(
    "SELECT s.*, m.movie_name, c.cinema_name,
            (SELECT COUNT(*) FROM booking_seats b WHERE b.show_id = s.show_id) AS booked,
            TIMESTAMP(s.show_date, s.show_time) < NOW() AS is_past
     FROM shows s
     JOIN addmovie m ON m.movie_id = s.movie_id
     JOIN cinema c ON c.cin_id = s.cin_id
     WHERE {$where[$view]}
     ORDER BY s.show_date $order, s.show_time $order"
);
$movies = db_all('SELECT movie_id, movie_name FROM addmovie ORDER BY movie_name');
$cinemas = db_all('SELECT cin_id, cinema_name FROM cinema ORDER BY cinema_name');

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">Shows &amp; Schedules</h2>
    </div>
  </div>

  <div class="container-fluid">
    <?php echo render_flash(); ?>
    <?php foreach ($form_errors as $error): ?>
      <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endforeach; ?>
  </div>

  <div class="col-lg-12">
    <div class="block">
      <div class="title"><strong><?php echo $form_show_id ? 'Edit Show #' . $form_show_id : 'Add Show'; ?></strong></div>
      <div class="block-body">
        <form method="post" action="shows.php">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="show_id" value="<?php echo $form_show_id; ?>">
          <div class="form-row">
            <div class="form-group col-md-4">
              <label class="form-control-label">Movie</label>
              <select name="movie_id" class="form-control" required>
                <option value="">Select movie</option>
                <?php foreach ($movies as $movie): ?>
                  <option value="<?php echo (int) $movie['movie_id']; ?>" <?php echo (int) $form_values['movie_id'] === (int) $movie['movie_id'] ? 'selected' : ''; ?>><?php echo e($movie['movie_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-4">
              <label class="form-control-label">Cinema</label>
              <select name="cin_id" class="form-control" required>
                <option value="">Select cinema</option>
                <?php foreach ($cinemas as $cinema): ?>
                  <option value="<?php echo (int) $cinema['cin_id']; ?>" <?php echo (int) $form_values['cin_id'] === (int) $cinema['cin_id'] ? 'selected' : ''; ?>><?php echo e($cinema['cinema_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-4">
              <label class="form-control-label">Hall</label>
              <input type="text" name="hall_name" class="form-control" maxlength="50" value="<?php echo e($form_values['hall_name']); ?>" required>
            </div>
            <div class="form-group col-md-3">
              <label class="form-control-label">Date</label>
              <input type="date" name="show_date" class="form-control" value="<?php echo e($form_values['show_date']); ?>" required>
            </div>
            <div class="form-group col-md-3">
              <label class="form-control-label">Time</label>
              <input type="time" name="show_time" class="form-control" value="<?php echo e(substr($form_values['show_time'], 0, 5)); ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label class="form-control-label">Rows (A–L)</label>
              <input type="number" name="total_rows" class="form-control" min="1" max="12" value="<?php echo (int) $form_values['total_rows']; ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label class="form-control-label">Seats / row</label>
              <input type="number" name="seats_per_row" class="form-control" min="1" max="20" value="<?php echo (int) $form_values['seats_per_row']; ?>" required>
            </div>
            <div class="form-group col-md-2">
              <label class="form-control-label">Status</label>
              <select name="status" class="form-control">
                <option <?php echo $form_values['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option <?php echo $form_values['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
              </select>
            </div>
          </div>
          <button type="submit" class="btn btn-secondary" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);"><?php echo $form_show_id ? 'Update Show' : 'Add Show'; ?></button>
          <?php if ($form_show_id): ?><a href="shows.php" class="btn btn-link">Cancel edit</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-12">
    <div class="block">
      <div class="title d-flex justify-content-between align-items-center flex-wrap">
        <strong>Shows (<?php echo count($shows); ?>)</strong>
        <div class="btn-group btn-group-sm">
          <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $key => $text): ?>
            <a href="shows.php?view=<?php echo $key; ?>" class="btn <?php echo $view === $key ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $text; ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th>#</th><th>Movie</th><th>Cinema / Hall</th><th>Date</th><th>Time</th><th>Seats</th><th style="min-width:160px;">Occupancy</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$shows): ?>
              <tr><td colspan="9" class="text-center">No shows found.</td></tr>
            <?php endif; ?>
            <?php foreach ($shows as $show):
                $capacity = (int) $show['total_rows'] * (int) $show['seats_per_row'];
                $pct = $capacity ? round($show['booked'] * 100 / $capacity) : 0; ?>
              <tr>
                <th scope="row"><?php echo (int) $show['show_id']; ?></th>
                <td><?php echo e($show['movie_name']); ?></td>
                <td><?php echo e($show['cinema_name']); ?><br><small><?php echo e($show['hall_name']); ?></small></td>
                <td><?php echo e(date('d M Y', strtotime($show['show_date']))); ?></td>
                <td><?php echo e(show_time_label($show['show_time'])); ?></td>
                <td><?php echo (int) $show['booked']; ?> / <?php echo $capacity; ?></td>
                <td>
                  <div class="progress" style="height:16px;">
                    <div class="progress-bar <?php echo $pct >= 80 ? 'bg-danger' : ($pct >= 50 ? 'bg-warning' : 'bg-success'); ?>" style="width: <?php echo max($pct, 2); ?>%"><?php echo $pct; ?>%</div>
                  </div>
                </td>
                <td>
                  <?php if ($show['status'] === 'Cancelled'): ?><span class="badge badge-danger">Cancelled</span>
                  <?php elseif ($show['is_past']): ?><span class="badge badge-secondary">Finished</span>
                  <?php else: ?><span class="badge badge-success">Active</span><?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                  <a href="shows.php?edit=<?php echo (int) $show['show_id']; ?>&amp;view=<?php echo $view; ?>" title="Edit"><i class="fa fa-pencil btn"></i></a>
                  <form method="post" action="shows.php" style="display:inline;" onsubmit="return confirm('Delete this show?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="show_id" value="<?php echo (int) $show['show_id']; ?>">
                    <button type="submit" class="btn btn-link p-0" title="Delete"><i class="fa fa-trash btn"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php include('admin_footer.php'); ?>
