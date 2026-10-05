<?php
include('header.php');

// what the project does, shown as cards (edit the text here)
$customer_features = [
    ['fa-search', 'Browse & search', 'Filter movies by genre, language and cinema, with live search suggestions and a Coming Soon list.'],
    ['fa-couch', 'Seat map', 'Pick seats on a live map: Economy, Business and First class rows, each with its own price.'],
    ['fa-lock', 'Fair seat holds', 'Chosen seats are held for 5 minutes, so two people can never buy the same seat.'],
    ['fa-hamburger', 'Snacks & promo codes', 'Add popcorn and drinks at checkout and apply discount codes such as EID20.'],
    ['fa-qrcode', 'E-ticket with QR', 'Pay by card, JazzCash or Easypaisa (demo) and get a printable e-ticket with a QR code.'],
    ['fa-undo', 'Cancel & refund', 'Cancel from My Bookings: full refund up to 24 hours before the show, 50% after that.'],
    ['fa-star', 'Reviews & favorites', 'Rate movies with 1 to 5 stars, write a review and save favorites.'],
    ['fa-user-circle', 'Your account', 'Profile with photo, loyalty points, password change, booking history and a contact form.'],
];
$admin_features = [
    ['fa-chart-line', 'Dashboard & reports', 'Revenue, tickets and refunds at a glance, charts, and reports you can export to CSV.'],
    ['fa-film', 'Movies & shows', 'Add movies with posters and descriptions, schedule shows per cinema and hall.'],
    ['fa-ticket-alt', 'Bookings & refunds', 'Search all bookings, approve or reject refund requests with a reason.'],
    ['fa-camera', 'Ticket scanner', 'Scan the QR code at the door: valid, already used or cancelled.'],
    ['fa-tags', 'Snacks & promo codes', 'Manage the snack menu and discount codes with usage limits and expiry dates.'],
    ['fa-users', 'Users & moderation', 'Block or unblock customers, hide unwanted reviews, read contact messages.'],
    ['fa-history', 'Activity log', 'Every admin add, edit and delete is recorded with who did it and when.'],
    ['fa-shield-alt', 'Secure by design', 'Login-protected admin pages, prepared SQL statements and CSRF tokens on every form.'],
];
$tech = [
    ['fab fa-php', 'PHP 8.2', 'Server-side pages (also runs on PHP 7.4)'],
    ['fas fa-database', 'MySQL / MariaDB', 'Database "dbmo" accessed with mysqli prepared statements'],
    ['fab fa-bootstrap', 'Bootstrap 5 & 4', 'Responsive layout (FlixGo customer theme, Bootstrapious admin theme)'],
    ['fab fa-js', 'JavaScript & jQuery', 'Seat map, live search, countdown timer, SweetAlert2 popups'],
    ['fas fa-chart-pie', 'Chart.js', 'Admin dashboard charts'],
    ['fas fa-server', 'XAMPP / Docker', 'Apache web server, runs locally on XAMPP or in Docker'],
];
$team = [
    ['Abdul Rafay', 'Core booking system', 'Database design, shows, seat map and seat holds, checkout, payment, e-tickets and QR scanner, cancellations and refunds, dashboard, reports and search.'],
    ['Umair', 'Admin management & customer pages', 'Admin pages for movies, prices, snacks, promo codes, coming soon, users, reviews, messages and activity log; sign up, profile, favorites, reviews, contact and site pages.'],
];
?>

<!-- page title -->

<section class="section section--first section--bg" data-bg="img/section/section.jpg">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<div class="section__wrap">
						<!-- section title -->
						<h2 class="section__title">About Us</h2>
						<!-- end section title -->

						<!-- breadcrumb -->
						<ul class="breadcrumb">
							<li class="breadcrumb__item"><a href="index.php">Home</a></li>
							<li class="breadcrumb__item breadcrumb__item--active">About Us</li>
						</ul>
						<!-- end breadcrumb -->
					</div>
				</div>
			</div>
		</div>
	</section>
	<!-- end page title -->

<div class="container cx-page" style="margin-top:40px;">

  <!-- what it is -->
  <div class="cx-card">
    <h2 style="margin-top:0;"><?php echo e(SITE_NAME); ?>: online movie ticket booking</h2>
    <p class="cu-lead"><?php echo e(SITE_NAME); ?> is a web application for booking cinema tickets in Karachi. Customers browse what's showing, choose a show, pick their exact seats on a seat map, add snacks, apply a promo code and pay. They instantly get an e-ticket with a QR code that the cinema scans at the door.</p>
    <p class="cu-lead mb-0">Behind it is an admin panel where cinema staff manage movies, shows, prices, snacks and promo codes, handle refunds, moderate reviews and follow sales on a live dashboard.</p>
  </div>

  <!-- features -->
  <h3 class="cu-section-title"><i class="fas fa-user"></i> For customers</h3>
  <div class="row g-3 mb-4">
    <?php foreach ($customer_features as $f): ?>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="cx-card cu-feature h-100 mb-0">
          <i class="fas <?php echo $f[0]; ?>"></i>
          <h4><?php echo e($f[1]); ?></h4>
          <p><?php echo e($f[2]); ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <h3 class="cu-section-title"><i class="fas fa-user-shield"></i> For cinema staff (admin panel)</h3>
  <div class="row g-3 mb-4">
    <?php foreach ($admin_features as $f): ?>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="cx-card cu-feature h-100 mb-0">
          <i class="fas <?php echo $f[0]; ?>"></i>
          <h4><?php echo e($f[1]); ?></h4>
          <p><?php echo e($f[2]); ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- technologies -->
  <h3 class="cu-section-title"><i class="fas fa-code"></i> Technologies used</h3>
  <div class="cx-card">
    <div class="row g-3">
      <?php foreach ($tech as $t): ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <div class="cu-tech">
            <i class="<?php echo $t[0]; ?>"></i>
            <div><strong><?php echo e($t[1]); ?></strong><span><?php echo e($t[2]); ?></span></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- team -->
  <h3 class="cu-section-title"><i class="fas fa-users"></i> The team</h3>
  <div class="row g-3">
    <?php foreach ($team as $member): ?>
      <div class="col-12 col-md-6">
        <div class="cx-card h-100 mb-0 cu-team">
          <?php echo avatar_html(['name' => $member[0], 'profile_pic' => null], 64); ?>
          <div>
            <h4><?php echo e($member[0]); ?></h4>
            <div class="cu-role"><?php echo e($member[1]); ?></div>
            <p><?php echo e($member[2]); ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (SITE_UNIVERSITY !== '' || SITE_SUPERVISOR !== ''): ?>
    <p class="cx-muted text-center mt-4 mb-0">
      Final Year Project<?php echo SITE_DEPARTMENT !== '' ? ', ' . e(SITE_DEPARTMENT) : ''; ?><?php echo SITE_UNIVERSITY !== '' ? ', ' . e(SITE_UNIVERSITY) : ''; ?><?php echo SITE_SUPERVISOR !== '' ? '. Supervisor: ' . e(SITE_SUPERVISOR) : ''; ?>.
    </p>
  <?php endif; ?>

  <div class="text-center mt-4">
    <a href="index.php#movies" class="cx-btn"><i class="fas fa-film"></i> Browse movies</a>
    <a href="contact.php" class="cx-btn cx-btn-ghost">Contact us</a>
  </div>
</div>

<?php
include('footer.php');
?>
