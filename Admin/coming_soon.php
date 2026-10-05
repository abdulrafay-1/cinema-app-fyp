<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/admin_upload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $movie = $id ? db_one('SELECT * FROM coming_soon WHERE id = ?', 'i', [$id]) : null;
    $back = 'coming_soon.php';

    if (!csrf_ok()) {
        flash('danger', 'Invalid request. Please try again.');
    } elseif ($action === 'save') {
        $title = trim($_POST['title'] ?? '');
        $trailer_url = trim($_POST['trailer_url'] ?? '');
        $release_date = trim($_POST['release_date'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $back = $id ? 'coming_soon.php?edit=' . $id : 'coming_soon.php';
        $date = DateTime::createFromFormat('Y-m-d', $release_date);

        if ($id && !$movie) {
            flash('danger', 'That movie no longer exists.');
        } elseif ($title === '' || mb_strlen($title) > 200) {
            flash('danger', 'Title is required (max 200 characters).');
        } elseif ($trailer_url !== '' && (!preg_match('#^https?://#i', $trailer_url) || strlen($trailer_url) > 255)) {
            flash('danger', 'Trailer URL must start with http:// or https://');
        } elseif ($release_date !== '' && (!$date || $date->format('Y-m-d') !== $release_date)) {
            flash('danger', 'Please enter a valid release date.');
        } else {
            $upload_error = '';
            $poster = admin_upload_image('poster', $upload_error);
            if ($poster === false) {
                flash('danger', $upload_error);
            } else {
                $trailer_url = $trailer_url === '' ? null : $trailer_url;
                $release_date = $release_date === '' ? null : $release_date;
                if ($movie) {
                    db_query(
                        'UPDATE coming_soon SET title = ?, poster = ?, trailer_url = ?, release_date = ?, description = ? WHERE id = ?',
                        'sssssi',
                        [$title, $poster ?: $movie['poster'], $trailer_url, $release_date, $description, $id]
                    );
                    if ($poster) {
                        admin_delete_upload($movie['poster']);
                    }
                    log_activity('Edited coming soon', 'id=' . $id . ', ' . $title);
                    flash('success', '"' . $title . '" updated.');
                } else {
                    db_query(
                        'INSERT INTO coming_soon (title, poster, trailer_url, release_date, description) VALUES (?, ?, ?, ?, ?)',
                        'sssss',
                        [$title, $poster, $trailer_url, $release_date, $description]
                    );
                    log_activity('Added coming soon', $title);
                    flash('success', '"' . $title . '" added to Coming Soon.');
                }
                $back = 'coming_soon.php';
            }
        }
    } elseif (!$movie) {
        flash('danger', 'That movie no longer exists.');
    } elseif ($action === 'delete') {
        db_query('DELETE FROM coming_soon WHERE id = ?', 'i', [$id]);
        admin_delete_upload($movie['poster']);
        log_activity('Deleted coming soon', 'id=' . $id . ', ' . $movie['title']);
        flash('success', '"' . $movie['title'] . '" deleted.');
    }
    redirect($back);
}

$edit = isset($_GET['edit']) ? db_one('SELECT * FROM coming_soon WHERE id = ?', 'i', [(int) $_GET['edit']]) : null;
$form = $edit ?: ['id' => 0, 'title' => '', 'poster' => null, 'trailer_url' => '', 'release_date' => '', 'description' => ''];
$movies = db_all('SELECT * FROM coming_soon ORDER BY release_date IS NULL, release_date');
$today = date('Y-m-d');

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">Coming Soon</h2>
    </div>
  </div>

  <div class="col-lg-12">
    <?php echo render_flash(); ?>
    <div class="block">
      <div class="title"><strong><?php echo $edit ? 'Edit Coming Soon Movie' : 'Add Coming Soon Movie'; ?></strong></div>
      <div class="block-body">
        <form class="form-horizontal" method="post" action="coming_soon.php" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>">
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Title</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="title" maxlength="200" value="<?php echo e($form['title']); ?>" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Poster</label>
            <div class="col-sm-9">
              <?php if ($form['poster']): ?>
                <img src="upload-images/<?php echo e($form['poster']); ?>" width="60" height="90" style="object-fit:cover;margin-bottom:8px;" alt="">
              <?php endif; ?>
              <input type="file" class="form-control" name="poster" accept=".jpg,.jpeg,.png,.webp">
              <small class="form-text text-muted">JPG, PNG or WEBP, max 2 MB.<?php echo $edit ? ' Leave empty to keep the current poster.' : ' Optional: without a poster the home page shows the title on a coloured card.'; ?></small>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Trailer URL</label>
            <div class="col-sm-9">
              <input type="url" class="form-control" name="trailer_url" maxlength="255" value="<?php echo e($form['trailer_url']); ?>" placeholder="https://www.youtube.com/watch?v=...">
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Release Date</label>
            <div class="col-sm-9">
              <input type="date" class="form-control" name="release_date" value="<?php echo e($form['release_date']); ?>">
              <small class="form-text text-muted">The home page only shows movies releasing today or later (or with no date yet).</small>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Description</label>
            <div class="col-sm-9">
              <textarea class="form-control" name="description" rows="3"><?php echo e($form['description']); ?></textarea>
            </div>
          </div>
          <div class="col-sm-9 ml-auto">
            <button type="submit" class="btn btn-secondary" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);"><?php echo $edit ? 'Update Movie' : 'Add Movie'; ?></button>
            <?php if ($edit): ?>
              <a href="coming_soon.php" class="btn btn-outline-secondary">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-12">
    <div class="block">
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr><th>#</th><th>Poster</th><th>Title</th><th>Release Date</th><th>Trailer</th><th>Description</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (!$movies): ?>
              <tr><td colspan="7" class="text-center">No coming soon movies yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($movies as $m): ?>
              <tr>
                <th scope="row"><?php echo (int) $m['id']; ?></th>
                <td>
                  <?php if ($m['poster']): ?>
                    <img src="upload-images/<?php echo e($m['poster']); ?>" width="45" height="65" style="object-fit:cover;" alt="">
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td><?php echo e($m['title']); ?></td>
                <td>
                  <?php if ($m['release_date']): ?>
                    <?php echo e(date('d M Y', strtotime($m['release_date']))); ?>
                    <?php if ($m['release_date'] < $today): ?><br><span class="badge badge-secondary">Released (hidden)</span><?php endif; ?>
                  <?php else: ?>
                    TBA
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($m['trailer_url']): ?>
                    <a href="<?php echo e($m['trailer_url']); ?>" target="_blank" rel="noopener"><i class="fa fa-play"></i> Watch</a>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td><small><?php echo e(mb_strimwidth((string) $m['description'], 0, 90, '...')); ?></small></td>
                <td style="white-space:nowrap;">
                  <a href="coming_soon.php?edit=<?php echo (int) $m['id']; ?>" title="Edit"><i class="fa fa-pencil btn"></i></a>
                  <form method="post" action="coming_soon.php" class="d-inline" onsubmit="return confirm('Delete this movie from Coming Soon?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
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
