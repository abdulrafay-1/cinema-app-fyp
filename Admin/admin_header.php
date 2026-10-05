<?php
include('config.php');
// gives every admin page e(), db_query() and log_activity()
require_once __DIR__ . '/../includes/admin_functions.php';
// SweetAlert2 popups (swal_script) for the admin pages
require_once __DIR__ . '/admin_alert.php';

// unread contact messages, shown as a badge next to "Messages" in the sidebar
$admin_unread_row = db_one('SELECT COUNT(*) AS n FROM contact_messages WHERE is_read = 0');
$admin_unread_messages = $admin_unread_row ? (int) $admin_unread_row['n'] : 0;
?>





<!DOCTYPE html>
<html>
  <head> 
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Admin - CineMax Karachi</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="all,follow">
    <!-- Bootstrap CSS-->
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <!-- Font Awesome CSS-->
    <link rel="stylesheet" href="vendor/font-awesome/css/font-awesome.min.css">
    <!-- Custom Font Icons CSS-->
    <link rel="stylesheet" href="css/font.css">
    <!-- Google fonts - Muli-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Muli:300,400,700">
    <!-- theme stylesheet-->
    <link rel="stylesheet" href="css/style.default.css" id="theme-stylesheet">
    <!-- Custom stylesheet - for your changes-->
    <link rel="stylesheet" href="css/custom.css">
    <!-- Favicon-->
    <link rel="shortcut icon" href="img/favi.png">
    <!-- Tweaks for older IEs--><!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script><![endif]-->
    <!-- SweetAlert2 popups -->
    <script src="<?php echo SWAL_CDN; ?>"></script>
  </head>
  <body>
    <header class="header">   
      <nav class="navbar navbar-expand-lg">
        <div class="container-fluid d-flex align-items-center justify-content-between">
          <div class="navbar-header">
            <!-- Navbar Header--><a href="dashboard.php" class="navbar-brand">
              <div class="brand-text brand-big visible text-uppercase"><strong class="text-primary">CineMax</strong> <strong>Karachi</strong></div>
              </a>
          </div>
            
            <!-- Log out               -->
            <div class="list-inline-item logout">                   <a id="logout" href="logout.php" class="nav-link btn" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);color:white;">Logout <i class="icon-logout"></i></a></div>
          </div>
        </div>
      </nav>
    </header>
    <div class="d-flex align-items-stretch">
      <!-- Sidebar Navigation-->
      <nav id="sidebar">
        <!-- Sidebar Header-->
        <div class="sidebar-header d-flex align-items-center">
          <div class="avatar d-flex align-items-center justify-content-center"><i class="fa fa-user-circle" style="font-size:3rem;color:#8a8d93;"></i></div>
          <div class="title">
            <h1 class="h5" style="word-break: break-all;"><?php echo e(isset($_SESSION['admin']) ? $_SESSION['admin'] : 'Admin'); ?></h1>
          </div>
        </div>
        <!-- Sidebar Navidation Menus--><span class="heading">Main</span>
        <ul class="list-unstyled">
                <li><a href="dashboard.php"> <i class="icon-home"></i> Dashboard</a></li>
                <li><a href="booking.php"> <i class="icon-grid"></i>All Bookings</a></li>
                <li><a href="cinema.php"> <i class="fa fa-video-camera"></i>Cinema </a></li>
                <li><a href="industry.php"> <i class="fa fa-industry"></i>Industry </a></li>
                <li><a href="genre.php"> <i class="icon-padnote"></i>Genre </a></li>
                <li><a href="language.php"> <i class="fa fa-language"></i>Language </a></li>
                <li><a href="date.php"> <i class="fa fa-calendar"></i>Cinema Date </a></li>
                <li><a href="#exampledropdownDropdown" aria-expanded="false" data-toggle="collapse"> <i class="icon-windows"></i>Add & View Movies</a>
                  <ul id="exampledropdownDropdown" class="collapse list-unstyled ">
                    <li><a href="addmovie.php">Add Movies</a></li>
                    <li><a href="viewmovie.php">View Movies</a></li>
                  </ul>
                </li>
                <li><a href="price.php"> <i class="icon-padnote"></i>Seat Price Details </a></li>
                <li><a href="user.php"> <i class="fa fa-bookmark"></i>Number Of Users</a></li>
        </ul>
        <span class="heading">Shows & Tickets</span>
        <ul class="list-unstyled">
                <li><a href="shows.php"> <i class="fa fa-film"></i>Shows </a></li>
                <li><a href="scan_ticket.php"> <i class="fa fa-qrcode"></i>Scan Ticket </a></li>
                <li><a href="refunds.php"> <i class="fa fa-undo"></i>Refunds </a></li>
                <li><a href="reports.php"> <i class="fa fa-bar-chart"></i>Reports </a></li>
        </ul>
        <span class="heading">Customers & Content</span>
        <ul class="list-unstyled">
                <li><a href="snacks.php"> <i class="fa fa-cutlery"></i>Snacks </a></li>
                <li><a href="promo_codes.php"> <i class="fa fa-tags"></i>Promo Codes </a></li>
                <li><a href="reviews.php"> <i class="fa fa-star"></i>Reviews </a></li>
                <li><a href="messages.php"> <i class="fa fa-envelope"></i>Messages <?php if ($admin_unread_messages > 0) { ?><span class="badge badge-danger ml-1"><?php echo $admin_unread_messages; ?></span><?php } ?></a></li>
                <li><a href="coming_soon.php"> <i class="fa fa-clock-o"></i>Coming Soon </a></li>
                <li><a href="activity_log.php"> <i class="fa fa-history"></i>Activity Log </a></li>
        </ul>
      </nav>
      <!-- Sidebar Navigation end-->
      