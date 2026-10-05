<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

// Active / Expired / Used up / Inactive, using the same rules as find_promo() at checkout
function promo_badge($p)
{
    if (!(int) $p['is_active']) {
        return ['Inactive', 'badge-secondary'];
    }
    if ($p['valid_until'] !== null && $p['valid_until'] < date('Y-m-d')) {
        return ['Expired', 'badge-warning'];
    }
    if ((int) $p['max_uses'] > 0 && (int) $p['used_count'] >= (int) $p['max_uses']) {
        return ['Used up', 'badge-danger'];
    }
    return ['Active', 'badge-success'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $promo_id = (int) ($_POST['promo_id'] ?? 0);
    $promo = $promo_id ? db_one('SELECT * FROM promo_codes WHERE promo_id = ?', 'i', [$promo_id]) : null;
    $back = 'promo_codes.php';

    if (!csrf_ok()) {
        flash('danger', 'Invalid request. Please try again.');
    } elseif ($action === 'save') {
        // checkout upper-cases what the customer types, so codes are stored in upper case
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $percent = trim($_POST['discount_percent'] ?? '');
        $max_uses = trim($_POST['max_uses'] ?? '0');
        $valid_until = trim($_POST['valid_until'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $back = $promo_id ? 'promo_codes.php?edit=' . $promo_id : 'promo_codes.php';
        $date = DateTime::createFromFormat('Y-m-d', $valid_until);

        if ($promo_id && !$promo) {
            flash('danger', 'That promo code no longer exists.');
        } elseif (!preg_match('/^[A-Z0-9]{3,30}$/', $code)) {
            flash('danger', 'Code must be 3 to 30 letters or numbers, no spaces.');
        } elseif (!ctype_digit($percent) || (int) $percent < 1 || (int) $percent > 100) {
            flash('danger', 'Discount must be between 1 and 100 %.');
        } elseif (!ctype_digit($max_uses)) {
            flash('danger', 'Max uses must be 0 (unlimited) or more.');
        } elseif ($valid_until !== '' && (!$date || $date->format('Y-m-d') !== $valid_until)) {
            flash('danger', 'Please enter a valid "valid until" date.');
        } elseif (db_one('SELECT promo_id FROM promo_codes WHERE code = ? AND promo_id <> ?', 'si', [$code, $promo_id])) {
            flash('danger', 'The code ' . $code . ' already exists. Please choose another one.');
        } else {
            $valid_until = $valid_until === '' ? null : $valid_until;
            if ($promo) {
                db_query(
                    'UPDATE promo_codes SET code = ?, discount_percent = ?, max_uses = ?, valid_until = ?, is_active = ? WHERE promo_id = ?',
                    'siisii',
                    [$code, (int) $percent, (int) $max_uses, $valid_until, $is_active, $promo_id]
                );
                log_activity('Edited promo code', $code . ' ' . (int) $percent . '%');
                flash('success', 'Promo code ' . $code . ' updated.');
            } else {
                db_query(
                    'INSERT INTO promo_codes (code, discount_percent, max_uses, valid_until, is_active) VALUES (?, ?, ?, ?, ?)',
                    'siisi',
                    [$code, (int) $percent, (int) $max_uses, $valid_until, $is_active]
                );
                log_activity('Added promo code', $code . ' ' . (int) $percent . '%');
                flash('success', 'Promo code ' . $code . ' added.');
            }
            $back = 'promo_codes.php';
        }
    } elseif (!$promo) {
        flash('danger', 'That promo code no longer exists.');
    } elseif ($action === 'toggle') {
        $new_state = (int) $promo['is_active'] ? 0 : 1;
        db_query('UPDATE promo_codes SET is_active = ? WHERE promo_id = ?', 'ii', [$new_state, $promo_id]);
        log_activity($new_state ? 'Activated promo code' : 'Deactivated promo code', $promo['code']);
        flash('success', 'Promo code ' . $promo['code'] . ' is now ' . ($new_state ? 'active' : 'inactive') . '.');
    } elseif ($action === 'delete') {
        // bookings keep their own copy of the code, so deleting it doesn't change old bookings
        db_query('DELETE FROM promo_codes WHERE promo_id = ?', 'i', [$promo_id]);
        log_activity('Deleted promo code', $promo['code']);
        flash('success', 'Promo code ' . $promo['code'] . ' deleted.');
    }
    redirect($back);
}

$edit = isset($_GET['edit']) ? db_one('SELECT * FROM promo_codes WHERE promo_id = ?', 'i', [(int) $_GET['edit']]) : null;
$form = $edit ?: ['promo_id' => 0, 'code' => '', 'discount_percent' => '', 'max_uses' => 0, 'valid_until' => '', 'is_active' => 1];
$promos = db_all('SELECT * FROM promo_codes ORDER BY is_active DESC, code');

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">Promo Codes</h2>
    </div>
  </div>

  <div class="col-lg-12">
    <?php echo render_flash(); ?>
    <div class="block">
      <div class="title"><strong><?php echo $edit ? 'Edit Promo Code' : 'Add Promo Code'; ?></strong></div>
      <div class="block-body">
        <form class="form-horizontal" method="post" action="promo_codes.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="promo_id" value="<?php echo (int) $form['promo_id']; ?>">
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Code</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="code" maxlength="30" pattern="[A-Za-z0-9]{3,30}" value="<?php echo e($form['code']); ?>" placeholder="e.g. EID20" style="text-transform:uppercase;" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Discount and Limits</label>
            <div class="col-sm-9">
              <div class="row">
                <div class="col-md-4">
                  <small class="text-muted">Discount %</small>
                  <input type="number" class="form-control" name="discount_percent" min="1" max="100" value="<?php echo e($form['discount_percent']); ?>" required>
                </div>
                <div class="col-md-4">
                  <small class="text-muted">Max uses (0 = unlimited)</small>
                  <input type="number" class="form-control" name="max_uses" min="0" value="<?php echo (int) $form['max_uses']; ?>" required>
                </div>
                <div class="col-md-4">
                  <small class="text-muted">Valid until (empty = no end date)</small>
                  <input type="date" class="form-control" name="valid_until" value="<?php echo e($form['valid_until']); ?>">
                </div>
              </div>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 form-control-label">Status</label>
            <div class="col-sm-9">
              <div class="i-checks">
                <input id="is_active" type="checkbox" name="is_active" value="1" class="checkbox-template" <?php echo (int) $form['is_active'] ? 'checked' : ''; ?>>
                <label for="is_active">Active (customers can use it at checkout)</label>
              </div>
            </div>
          </div>
          <div class="col-sm-9 ml-auto">
            <button type="submit" class="btn btn-secondary" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);"><?php echo $edit ? 'Update Promo Code' : 'Add Promo Code'; ?></button>
            <?php if ($edit): ?>
              <a href="promo_codes.php" class="btn btn-outline-secondary">Cancel</a>
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
            <tr><th>#</th><th>Code</th><th>Discount</th><th>Used</th><th>Valid until</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (!$promos): ?>
              <tr><td colspan="7" class="text-center">No promo codes yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($promos as $p):
                list($badge_text, $badge_class) = promo_badge($p); ?>
              <tr>
                <th scope="row"><?php echo (int) $p['promo_id']; ?></th>
                <td><strong><?php echo e($p['code']); ?></strong></td>
                <td><?php echo (int) $p['discount_percent']; ?>%</td>
                <td><?php echo (int) $p['used_count']; ?> / <?php echo (int) $p['max_uses'] > 0 ? (int) $p['max_uses'] : '&infin;'; ?></td>
                <td><?php echo $p['valid_until'] ? e(date('d M Y', strtotime($p['valid_until']))) : 'No end date'; ?></td>
                <td><span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span></td>
                <td style="white-space:nowrap;">
                  <a href="promo_codes.php?edit=<?php echo (int) $p['promo_id']; ?>" title="Edit"><i class="fa fa-pencil btn"></i></a>
                  <form method="post" action="promo_codes.php" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="promo_id" value="<?php echo (int) $p['promo_id']; ?>">
                    <button type="submit" class="btn btn-sm <?php echo (int) $p['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"><?php echo (int) $p['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                  </form>
                  <form method="post" action="promo_codes.php" class="d-inline" onsubmit="return confirm('Delete promo code <?php echo e($p['code']); ?>?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="promo_id" value="<?php echo (int) $p['promo_id']; ?>">
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
