<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

$filter_movie = (int) ($_GET['movie_id'] ?? $_POST['movie_id'] ?? 0);
$back = 'reviews.php' . ($filter_movie ? '?movie_id=' . $filter_movie : '');

// Hide / Show / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $review_id = (int) ($_POST['review_id'] ?? 0);
    $review = db_one(
        'SELECT r.review_id, r.rating, r.status, m.movie_name, u.name AS user_name
         FROM reviews r LEFT JOIN addmovie m ON m.movie_id = r.movie_id LEFT JOIN user_reg_login u ON u.id = r.user_id
         WHERE r.review_id = ?',
        'i',
        [$review_id]
    );
    $about = $review ? 'review_id=' . $review_id . ', ' . $review['user_name'] . ' on ' . $review['movie_name'] : '';

    if (!csrf_ok() || !$review) {
        flash('danger', 'Invalid request. Please try again.');
    } elseif ($action === 'hide' || $action === 'show') {
        $new_status = $action === 'hide' ? 'Hidden' : 'Visible';
        db_query('UPDATE reviews SET status = ? WHERE review_id = ?', 'si', [$new_status, $review_id]);
        log_activity($action === 'hide' ? 'Hid review' : 'Showed review', $about);
        flash('success', 'Review is now ' . strtolower($new_status) . '.');
    } elseif ($action === 'delete') {
        db_query('DELETE FROM reviews WHERE review_id = ?', 'i', [$review_id]);
        log_activity('Deleted review', $about);
        flash('success', 'Review deleted.');
    }
    redirect($back);
}

$movies = db_all('SELECT movie_id, movie_name FROM addmovie ORDER BY movie_name');
$reviews = db_all(
    'SELECT r.*, m.movie_name, u.name AS user_name, u.email AS user_email
     FROM reviews r LEFT JOIN addmovie m ON m.movie_id = r.movie_id LEFT JOIN user_reg_login u ON u.id = r.user_id
     WHERE (? = 0 OR r.movie_id = ?)
     ORDER BY r.created_at DESC',
    'ii',
    [$filter_movie, $filter_movie]
);

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">Reviews</h2>
    </div>
  </div>

  <div class="col-lg-12">
    <?php echo render_flash(); ?>
    <div class="block">
      <div class="title d-flex justify-content-between align-items-center flex-wrap">
        <strong><?php echo count($reviews); ?> review(s)</strong>
        <form method="get" action="reviews.php" class="form-inline">
          <label class="mr-2" for="movie_id">Movie</label>
          <select name="movie_id" id="movie_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
            <option value="0">All movies</option>
            <?php foreach ($movies as $m): ?>
              <option value="<?php echo (int) $m['movie_id']; ?>" <?php echo $filter_movie === (int) $m['movie_id'] ? 'selected' : ''; ?>><?php echo e($m['movie_name']); ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button type="submit" class="btn btn-sm btn-primary">Filter</button></noscript>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr><th>Movie</th><th>User</th><th>Stars</th><th>Comment</th><th>Date</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (!$reviews): ?>
              <tr><td colspan="7" class="text-center">No reviews<?php echo $filter_movie ? ' for this movie' : ''; ?> yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($reviews as $r): ?>
              <tr>
                <td><?php echo $r['movie_name'] !== null ? e($r['movie_name']) : '<span class="text-muted">(deleted movie)</span>'; ?></td>
                <td><?php echo $r['user_name'] !== null ? e($r['user_name']) . '<br><small>' . e($r['user_email']) . '</small>' : '<span class="text-muted">(deleted user)</span>'; ?></td>
                <td style="white-space:nowrap;color:#f5b301;" title="<?php echo (int) $r['rating']; ?> of 5">
                  <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa <?php echo $i <= (int) $r['rating'] ? 'fa-star' : 'fa-star-o'; ?>"></i><?php endfor; ?>
                </td>
                <td style="max-width:320px;"><?php echo nl2br(e($r['comment'])); ?></td>
                <td><small><?php echo e(date('d M Y, g:i A', strtotime($r['created_at']))); ?></small></td>
                <td><span class="badge <?php echo $r['status'] === 'Hidden' ? 'badge-secondary' : 'badge-success'; ?>"><?php echo e($r['status']); ?></span></td>
                <td style="white-space:nowrap;">
                  <form method="post" action="reviews.php" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="review_id" value="<?php echo (int) $r['review_id']; ?>">
                    <input type="hidden" name="movie_id" value="<?php echo $filter_movie; ?>">
                    <?php if ($r['status'] === 'Hidden'): ?>
                      <button type="submit" name="action" value="show" class="btn btn-sm btn-outline-success"><i class="fa fa-eye"></i> Show</button>
                    <?php else: ?>
                      <button type="submit" name="action" value="hide" class="btn btn-sm btn-outline-secondary"><i class="fa fa-eye-slash"></i> Hide</button>
                    <?php endif; ?>
                  </form>
                  <form method="post" action="reviews.php" class="d-inline" onsubmit="return confirm('Delete this review?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="review_id" value="<?php echo (int) $r['review_id']; ?>">
                    <input type="hidden" name="movie_id" value="<?php echo $filter_movie; ?>">
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
