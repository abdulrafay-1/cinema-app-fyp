<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

$filters = ['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'];
$filter = isset($filters[$_GET['show'] ?? '']) ? $_GET['show'] : 'all';

// Read / Unread / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $msg = db_one('SELECT id, name, email, subject FROM contact_messages WHERE id = ?', 'i', [$id]);
    $back = 'messages.php?show=' . (isset($filters[$_POST['show'] ?? '']) ? $_POST['show'] : 'all');
    $about = $msg ? 'id=' . $id . ', from ' . $msg['email'] . ': ' . $msg['subject'] : '';

    if (!csrf_ok() || !$msg) {
        flash('danger', 'Invalid request. Please try again.');
    } elseif ($action === 'read' || $action === 'unread') {
        db_query('UPDATE contact_messages SET is_read = ? WHERE id = ?', 'ii', [$action === 'read' ? 1 : 0, $id]);
        log_activity($action === 'read' ? 'Marked message read' : 'Marked message unread', $about);
        flash('success', 'Message marked as ' . $action . '.');
    } elseif ($action === 'delete') {
        db_query('DELETE FROM contact_messages WHERE id = ?', 'i', [$id]);
        log_activity('Deleted message', $about);
        flash('success', 'Message deleted.');
    }
    redirect($back);
}

// opening a message marks it as read, like an email inbox
$view = isset($_GET['view']) ? db_one('SELECT * FROM contact_messages WHERE id = ?', 'i', [(int) $_GET['view']]) : null;
if ($view && !(int) $view['is_read']) {
    db_query('UPDATE contact_messages SET is_read = 1 WHERE id = ?', 'i', [(int) $view['id']]);
}

$where = ['all' => '1 = 1', 'unread' => 'is_read = 0', 'read' => 'is_read = 1'][$filter];
$messages = db_all("SELECT * FROM contact_messages WHERE $where ORDER BY created_at DESC");

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">Messages</h2>
    </div>
  </div>

  <div class="col-lg-12">
    <?php echo render_flash(); ?>

    <?php if ($view): ?>
      <div class="block">
        <div class="title d-flex justify-content-between align-items-center flex-wrap">
          <strong><?php echo e($view['subject']); ?></strong>
          <a href="messages.php" class="btn btn-sm btn-outline-secondary">Back to inbox</a>
        </div>
        <p class="mb-1"><strong>From:</strong> <?php echo e($view['name']); ?> &lt;<a href="mailto:<?php echo e($view['email']); ?>?subject=<?php echo rawurlencode('Re: ' . $view['subject']); ?>"><?php echo e($view['email']); ?></a>&gt;</p>
        <p class="mb-3"><small class="text-muted"><?php echo e(date('d M Y, g:i A', strtotime($view['created_at']))); ?></small></p>
        <div style="white-space:pre-wrap;border-left:3px solid #ff5860;padding-left:12px;"><?php echo e($view['message']); ?></div>
        <div class="mt-3">
          <a href="mailto:<?php echo e($view['email']); ?>?subject=<?php echo rawurlencode('Re: ' . $view['subject']); ?>" class="btn btn-sm btn-primary"><i class="fa fa-reply"></i> Reply by email</a>
          <form method="post" action="messages.php" class="d-inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int) $view['id']; ?>">
            <button type="submit" name="action" value="unread" class="btn btn-sm btn-outline-secondary"><i class="fa fa-envelope"></i> Mark as unread</button>
          </form>
          <form method="post" action="messages.php" class="d-inline" onsubmit="return confirm('Delete this message?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int) $view['id']; ?>">
            <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i> Delete</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <div class="block">
      <div class="title d-flex justify-content-between align-items-center flex-wrap">
        <strong>Inbox (<?php echo (int) $admin_unread_messages; ?> unread)</strong>
        <div class="btn-group btn-group-sm">
          <?php foreach ($filters as $key => $label): ?>
            <a href="messages.php?show=<?php echo $key; ?>" class="btn <?php echo $filter === $key ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $label; ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr><th></th><th>From</th><th>Subject</th><th>Received</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (!$messages): ?>
              <tr><td colspan="5" class="text-center">No messages.</td></tr>
            <?php endif; ?>
            <?php foreach ($messages as $m):
                $unread = !(int) $m['is_read']; ?>
              <tr<?php echo $unread ? ' style="font-weight:700;"' : ''; ?>>
                <td><?php echo $unread ? '<span class="badge badge-danger">New</span>' : '<i class="fa fa-envelope-open-o text-muted"></i>'; ?></td>
                <td><?php echo e($m['name']); ?><br><small style="font-weight:400;"><?php echo e($m['email']); ?></small></td>
                <td>
                  <a href="messages.php?view=<?php echo (int) $m['id']; ?>"><?php echo e($m['subject']); ?></a><br>
                  <small class="text-muted" style="font-weight:400;"><?php echo e(mb_strimwidth($m['message'], 0, 80, '...')); ?></small>
                </td>
                <td><small><?php echo e(date('d M Y, g:i A', strtotime($m['created_at']))); ?></small></td>
                <td style="white-space:nowrap;">
                  <a href="messages.php?view=<?php echo (int) $m['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-eye"></i> View</a>
                  <form method="post" action="messages.php" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                    <input type="hidden" name="show" value="<?php echo $filter; ?>">
                    <button type="submit" name="action" value="<?php echo $unread ? 'read' : 'unread'; ?>" class="btn btn-sm btn-outline-secondary"><?php echo $unread ? 'Mark read' : 'Mark unread'; ?></button>
                  </form>
                  <form method="post" action="messages.php" class="d-inline" onsubmit="return confirm('Delete this message?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                    <input type="hidden" name="show" value="<?php echo $filter; ?>">
                    <button type="submit" name="action" value="delete" class="btn btn-link p-0" title="Delete"><i class="fa fa-trash btn"></i></button>
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
