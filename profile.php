<?php
require_once __DIR__ . '/customer_functions.php';

$user = require_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_ok()) {
        flash('danger', 'Invalid request. Please try again.');
    } elseif ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            flash('danger', 'Please enter your name (max 100 characters).');
        } elseif ($phone !== '' && !preg_match('/^\+?[0-9][0-9 -]{6,18}$/', $phone)) {
            flash('danger', 'Please enter a valid phone number, e.g. 0300 1234567.');
        } else {
            // same upload rules as the admin pages: JPG/PNG/WEBP, real image, max 2 MB, random file name
            $upload_error = '';
            $photo = admin_upload_image('profile_pic', $upload_error, PROFILE_UPLOAD_DIR);
            if ($photo === false) {
                flash('danger', $upload_error);
            } else {
                db_query(
                    'UPDATE user_reg_login SET name = ?, phone = ?, profile_pic = ? WHERE id = ?',
                    'sssi',
                    [$name, $phone === '' ? null : $phone, $photo ?: $user['profile_pic'], (int) $user['id']]
                );
                if ($photo) {
                    admin_delete_upload($user['profile_pic'], PROFILE_UPLOAD_DIR);
                }
                // the navbar and booking pages read the name from the session
                $_SESSION['ux'] = $name;
                flash('success', 'Your profile was updated.');
            }
        }
    } elseif ($action === 'remove_photo') {
        db_query('UPDATE user_reg_login SET profile_pic = NULL WHERE id = ?', 'i', [(int) $user['id']]);
        admin_delete_upload($user['profile_pic'], PROFILE_UPLOAD_DIR);
        flash('success', 'Your photo was removed.');
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $row = db_one('SELECT password FROM user_reg_login WHERE id = ?', 'i', [(int) $user['id']]);
        // passwords are kept in the same (plain) format signin.php compares against, so login keeps working
        if (!$row || !hash_equals((string) $row['password'], $current)) {
            flash('danger', 'Your current password is not correct.');
        } elseif (strlen($new) < 6) {
            flash('danger', 'The new password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flash('danger', 'The new passwords do not match.');
        } elseif ($new === $current) {
            flash('danger', 'The new password is the same as the current one.');
        } else {
            db_query('UPDATE user_reg_login SET password = ? WHERE id = ?', 'si', [$new, (int) $user['id']]);
            flash('success', 'Your password was changed. Use the new one next time you sign in.');
        }
    }
    redirect('profile.php');
}

$stats = db_one(
    "SELECT
        (SELECT COUNT(*) FROM booking_details WHERE (user_id = ? OR (user_id IS NULL AND user_email = ?)) AND status <> 'Cancelled') AS bookings,
        (SELECT COUNT(*) FROM favorites WHERE user_id = ?) AS favorites,
        (SELECT COUNT(*) FROM reviews WHERE user_id = ?) AS reviews",
    'isii',
    [(int) $user['id'], $user['email'], (int) $user['id'], (int) $user['id']]
);

include('header.php');
?>
<div class="container cx-page">
  <?php echo render_customer_flash(); ?>

  <div class="cx-card">
    <div class="cu-profile-head">
      <?php echo avatar_html($user, 96); ?>
      <div style="flex:1;min-width:200px;">
        <h2><?php echo e($user['name']); ?></h2>
        <div class="cx-muted"><?php echo e($user['email']); ?></div>
        <div class="cx-muted" style="font-size:13px;">Member since <?php echo e(date('F Y', strtotime($user['created_at']))); ?></div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="userbooking.php" class="cx-btn"><i class="fas fa-ticket-alt"></i> My Bookings</a>
        <a href="favorites.php" class="cx-btn cx-btn-ghost"><i class="fas fa-heart"></i> Favorites</a>
      </div>
    </div>
    <div class="row g-3 mt-2">
      <div class="col-6 col-md-3"><div class="cu-stat"><strong><?php echo (int) $user['loyalty_points']; ?></strong><span>Loyalty points</span></div></div>
      <div class="col-6 col-md-3"><div class="cu-stat"><strong><?php echo (int) $stats['bookings']; ?></strong><span>Bookings</span></div></div>
      <div class="col-6 col-md-3"><div class="cu-stat"><strong><?php echo (int) $stats['favorites']; ?></strong><span>Favorites</span></div></div>
      <div class="col-6 col-md-3"><div class="cu-stat"><strong><?php echo (int) $stats['reviews']; ?></strong><span>Reviews</span></div></div>
    </div>
    <p class="cx-muted mt-3 mb-0" style="font-size:13px;">You earn 1 loyalty point for every PKR 100 you spend on bookings.</p>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="cx-card h-100">
        <h3 style="margin-top:0;">Edit profile</h3>
        <form method="post" action="profile.php" enctype="multipart/form-data" class="cu-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="profile">
          <div class="cu-field">
            <label for="name">Name</label>
            <input type="text" class="cx-input" id="name" name="name" maxlength="100" value="<?php echo e($user['name']); ?>" required>
          </div>
          <div class="cu-field">
            <label for="email">Email</label>
            <input type="email" class="cx-input" id="email" value="<?php echo e($user['email']); ?>" readonly>
            <div class="cu-help">Your email is your login, so it can't be changed here.</div>
          </div>
          <div class="cu-field">
            <label for="phone">Phone</label>
            <input type="tel" class="cx-input" id="phone" name="phone" maxlength="20" value="<?php echo e($user['phone']); ?>" placeholder="0300 1234567">
          </div>
          <div class="cu-field">
            <label for="profile_pic">Profile picture</label>
            <input type="file" class="cx-input" id="profile_pic" name="profile_pic" accept=".jpg,.jpeg,.png,.webp">
            <div class="cu-help">JPG, PNG or WEBP, max 2 MB. Leave empty to keep your current picture.</div>
          </div>
          <button type="submit" class="cx-btn">Save changes</button>
        </form>
        <?php if ($user['profile_pic']): ?>
          <form method="post" action="profile.php" class="mt-2" onsubmit="return confirm('Remove your profile picture?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="remove_photo">
            <button type="submit" class="cx-btn cx-btn-sm cx-btn-ghost"><i class="fas fa-trash-alt"></i> Remove photo</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="cx-card h-100">
        <h3 style="margin-top:0;">Change password</h3>
        <form method="post" action="profile.php" class="cu-form" autocomplete="off">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="password">
          <div class="cu-field">
            <label for="current_password">Current password</label>
            <input type="password" class="cx-input" id="current_password" name="current_password" autocomplete="current-password" required>
          </div>
          <div class="cu-field">
            <label for="new_password">New password</label>
            <input type="password" class="cx-input" id="new_password" name="new_password" minlength="6" autocomplete="new-password" required>
            <div class="cu-help">At least 6 characters.</div>
          </div>
          <div class="cu-field">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" class="cx-input" id="confirm_password" name="confirm_password" minlength="6" autocomplete="new-password" required>
          </div>
          <button type="submit" class="cx-btn">Change password</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include('footer.php'); ?>
