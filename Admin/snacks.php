<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/admin_upload.php';

// all changes are POST + CSRF, then redirect back (so a page refresh never repeats an action)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $snack_id = (int) ($_POST['snack_id'] ?? 0);
    $snack = $snack_id ? db_one('SELECT * FROM snacks WHERE snack_id = ?', 'i', [$snack_id]) : null;
    $back = 'snacks.php';

    if (!csrf_ok()) {
        flash('danger', 'Invalid request. Please try again.');
    } elseif ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        // on an error, go back to the same form
        $back = $snack_id ? 'snacks.php?edit=' . $snack_id : 'snacks.php';

        if ($snack_id && !$snack) {
            flash('danger', 'That snack no longer exists.');
        } elseif ($name === '' || mb_strlen($name) > 100) {
            flash('danger', 'Snack name is required (max 100 characters).');
        } elseif (!ctype_digit($price) || (int) $price > 100000) {
            flash('danger', 'Price must be a whole number of rupees (0 to 100,000).');
        } else {
            $upload_error = '';
            $image = admin_upload_image('image', $upload_error);
            if ($image === false) {
                flash('danger', $upload_error);
            } elseif ($snack) {
                $new_image = $image ?: $snack['image'];
                db_query('UPDATE snacks SET name = ?, price = ?, image = ?, is_active = ? WHERE snack_id = ?', 'sisii', [$name, (int) $price, $new_image, $is_active, $snack_id]);
                if ($image) {
                    admin_delete_upload($snack['image']);
                }
                log_activity('Edited snack', 'snack_id=' . $snack_id . ', ' . $name . ', ' . money($price));
                flash('success', 'Snack "' . $name . '" updated.');
                $back = 'snacks.php';
            } else {
                db_query('INSERT INTO snacks (name, price, image, is_active) VALUES (?, ?, ?, ?)', 'sisi', [$name, (int) $price, $image, $is_active]);
                log_activity('Added snack', $name . ', ' . money($price));
                flash('success', 'Snack "' . $name . '" added.');
            }
        }
    } elseif (!$snack) {
        flash('danger', 'That snack no longer exists.');
    } elseif ($action === 'toggle') {
        $new_state = (int) $snack['is_active'] ? 0 : 1;
        db_query('UPDATE snacks SET is_active = ? WHERE snack_id = ?', 'ii', [$new_state, $snack_id]);
        log_activity($new_state ? 'Activated snack' : 'Deactivated snack', 'snack_id=' . $snack_id . ', ' . $snack['name']);
        flash('success', 'Snack "' . $snack['name'] . '" is now ' . ($new_state ? 'active' : 'inactive') . '.');
    } elseif ($action === 'delete') {
        // old tickets show snack names by joining booking_snacks to snacks, so a sold snack must stay
        $used = db_one('SELECT COUNT(*) AS n FROM booking_snacks WHERE snack_id = ?', 'i', [$snack_id]);
        if ((int) $used['n'] > 0) {
            flash('danger', '"' . $snack['name'] . '" is in ' . (int) $used['n'] . ' booking(s), so it can\'t be deleted. Deactivate it instead.');
        } else {
            db_query('DELETE FROM snacks WHERE snack_id = ?', 'i', [$snack_id]);
            admin_delete_upload($snack['image']);
            log_activity('Deleted snack', 'snack_id=' . $snack_id . ', ' . $snack['name']);
            flash('success', 'Snack "' . $snack['name'] . '" deleted.');
        }
    }
    redirect($back);
}

$edit = isset($_GET['edit']) ? db_one('SELECT * FROM snacks WHERE snack_id = ?', 'i', [(int) $_GET['edit']]) : null;
$form = $edit ?: ['snack_id' => 0, 'name' => '', 'price' => '', 'image' => null, 'is_active' => 1];
$snacks = db_all('SELECT s.*, (SELECT COUNT(*) FROM booking_snacks bs WHERE bs.snack_id = s.snack_id) AS sold FROM snacks s ORDER BY s.is_active DESC, s.name');

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">Snacks</h2>
    </div>
  </div>

  <div class="col-lg-12">
    <?php echo render_flash(); ?>
    <div class="block">
      <div class="title"><strong><?php echo $edit ? 'Edit Snack' : 'Add Snack'; ?></strong></div>
      <div class="block-body">
        <form class="form-horizontal" method="post" action="snacks.php" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="snack_id" value="<?php echo (int) $form['snack_id']; ?>">
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Snack Name</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="name" maxlength="100" value="<?php echo e($form['name']); ?>" placeholder="e.g. Popcorn (Large)" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Price (PKR)</label>
            <div class="col-sm-9">
              <input type="number" class="form-control" name="price" min="0" max="100000" step="1" value="<?php echo e($form['price']); ?>" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Image</label>
            <div class="col-sm-9">
              <?php if ($form['image']): ?>
                <img src="upload-images/<?php echo e($form['image']); ?>" width="60" height="60" style="object-fit:cover;margin-bottom:8px;" alt="">
              <?php endif; ?>
              <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png,.webp">
              <small class="form-text text-muted">JPG, PNG or WEBP, max 2 MB.<?php echo $edit ? ' Leave empty to keep the current image.' : ' Optional: without an image the customer page shows an emoji.'; ?></small>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Status</label>
            <div class="col-sm-9">
              <div class="i-checks">
                <input id="is_active" type="checkbox" name="is_active" value="1" class="checkbox-template" <?php echo (int) $form['is_active'] ? 'checked' : ''; ?>>
                <label for="is_active">Active (customers can order it at checkout)</label>
              </div>
            </div>
          </div>
          <div class="col-sm-9 ml-auto">
            <button type="submit" class="btn btn-secondary" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);"><?php echo $edit ? 'Update Snack' : 'Add Snack'; ?></button>
            <?php if ($edit): ?>
              <a href="snacks.php" class="btn btn-outline-secondary">Cancel</a>
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
            <tr><th>#</th><th>Image</th><th>Name</th><th>Price</th><th>Sold in</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (!$snacks): ?>
              <tr><td colspan="7" class="text-center">No snacks yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($snacks as $s): ?>
              <tr>
                <th scope="row"><?php echo (int) $s['snack_id']; ?></th>
                <td>
                  <?php if ($s['image']): ?>
                    <img src="upload-images/<?php echo e($s['image']); ?>" width="45" height="45" style="object-fit:cover;" alt="">
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td><?php echo e($s['name']); ?></td>
                <td><?php echo money($s['price']); ?></td>
                <td><?php echo (int) $s['sold']; ?> booking(s)</td>
                <td>
                  <span class="badge <?php echo (int) $s['is_active'] ? 'badge-success' : 'badge-secondary'; ?>"><?php echo (int) $s['is_active'] ? 'Active' : 'Inactive'; ?></span>
                </td>
                <td style="white-space:nowrap;">
                  <a href="snacks.php?edit=<?php echo (int) $s['snack_id']; ?>" title="Edit"><i class="fa fa-pencil btn"></i></a>
                  <form method="post" action="snacks.php" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="snack_id" value="<?php echo (int) $s['snack_id']; ?>">
                    <button type="submit" class="btn btn-sm <?php echo (int) $s['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"><?php echo (int) $s['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                  </form>
                  <form method="post" action="snacks.php" class="d-inline" onsubmit="return confirm('Delete this snack?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="snack_id" value="<?php echo (int) $s['snack_id']; ?>">
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
