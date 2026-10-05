<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

// read-only page: newest first, search box, 20 rows per page
$per_page = 20;
$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';
$search_sql = '(? = \'\' OR admin_email LIKE ? OR action LIKE ? OR details LIKE ?)';
$search_params = [$q, $like, $like, $like];

$total_row = db_one("SELECT COUNT(*) AS n FROM activity_log WHERE $search_sql", 'ssss', $search_params);
$total = $total_row ? (int) $total_row['n'] : 0;
$pages = max(1, (int) ceil($total / $per_page));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * $per_page;

$rows = db_all(
    "SELECT * FROM activity_log WHERE $search_sql ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
    'ssssii',
    array_merge($search_params, [$per_page, $offset])
);

function log_page_url($page, $q)
{
    return 'activity_log.php?' . http_build_query(array_filter(['q' => $q, 'page' => $page > 1 ? $page : null]));
}

include('admin_header.php');
?>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">Activity Log</h2>
    </div>
  </div>

  <div class="col-lg-12">
    <div class="block">
      <div class="title d-flex justify-content-between align-items-center flex-wrap">
        <strong><?php echo $total; ?> action(s)<?php echo $q !== '' ? ' matching "' . e($q) . '"' : ''; ?></strong>
        <form method="get" action="activity_log.php" class="form-inline">
          <input type="text" name="q" value="<?php echo e($q); ?>" class="form-control form-control-sm mr-2" placeholder="Search admin, action or details">
          <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-search"></i> Search</button>
          <?php if ($q !== ''): ?>
            <a href="activity_log.php" class="btn btn-sm btn-outline-secondary ml-1">Clear</a>
          <?php endif; ?>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr><th>#</th><th>When</th><th>Admin</th><th>Action</th><th>Details</th></tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="5" class="text-center"><?php echo $q !== '' ? 'Nothing matches your search.' : 'No admin actions logged yet.'; ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <th scope="row"><?php echo (int) $r['id']; ?></th>
                <td style="white-space:nowrap;"><small><?php echo e(date('d M Y, g:i A', strtotime($r['created_at']))); ?></small></td>
                <td><?php echo $r['admin_email'] !== null ? e($r['admin_email']) : '<span class="text-muted">-</span>'; ?></td>
                <td><strong><?php echo e($r['action']); ?></strong></td>
                <td><small><?php echo e($r['details']); ?></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1): ?>
        <nav>
          <ul class="pagination pagination-sm justify-content-center mb-0">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo e(log_page_url($page - 1, $q)); ?>">&laquo; Newer</a></li>
            <li class="page-item disabled"><span class="page-link">Page <?php echo $page; ?> of <?php echo $pages; ?></span></li>
            <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo e(log_page_url($page + 1, $q)); ?>">Older &raquo;</a></li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
<?php include('admin_footer.php'); ?>
