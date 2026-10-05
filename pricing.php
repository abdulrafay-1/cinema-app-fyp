<?php
include('header.php');

// prices per movie and class (admin sets them on Admin -> Seat Price Details)
$prices = db_all('SELECT movie_name_price, economy, business, first_class FROM movie_price ORDER BY movie_name_price');
// link each price row to its movie page (prices are matched to movies by name, as in the booking code)
$movie_ids = [];
foreach (db_all('SELECT movie_id, movie_name FROM addmovie') as $m) {
    $movie_ids[$m['movie_name']] = (int) $m['movie_id'];
}
$classes = [
    ['Economy', 'Rows A – C', 'var(--cx-economy)', 'Front rows, best value.'],
    ['Business', 'Rows D – F', 'var(--cx-business)', 'Middle rows, the best view of the screen.'],
    ['First class', 'Rows G – H', 'var(--cx-first)', 'Back rows, our most comfortable seats.'],
];
?>
<!-- page title -->

<section class="section section--first section--bg" data-bg="img/section/section.jpg">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<div class="section__wrap">
						<!-- section title -->
						<h2 class="section__title">Ticket Pricing</h2>
						<!-- end section title -->

						<!-- breadcrumb -->
						<ul class="breadcrumb">
							<li class="breadcrumb__item"><a href="index.php">Home</a></li>
							<li class="breadcrumb__item breadcrumb__item--active">Ticket Prices</li>
						</ul>
						<!-- end breadcrumb -->
					</div>
				</div>
			</div>
		</div>
	</section>
	<!-- end page title -->

<div class="container cx-page" style="margin-top:40px;">

  <!-- seat classes, same colours as the seat map -->
  <div class="row g-3 mb-4">
    <?php foreach ($classes as $c): ?>
      <div class="col-12 col-md-4">
        <div class="cx-card cu-class h-100 mb-0" style="border-top:4px solid <?php echo $c[2]; ?>;">
          <h3 style="margin:0;"><?php echo e($c[0]); ?></h3>
          <div style="color:<?php echo $c[2]; ?>;font-weight:600;"><?php echo e($c[1]); ?></div>
          <p class="cx-muted mb-0 mt-1"><?php echo e($c[3]); ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- prices per movie -->
  <div class="cx-card">
    <h3 style="margin-top:0;">Prices per movie</h3>
    <?php if (!$prices): ?>
      <p class="cx-muted mb-0">Prices will be listed here soon.</p>
    <?php else: ?>
      <!-- table-responsive lets the table scroll sideways on a small phone instead of breaking the page -->
      <div class="table-responsive">
        <table class="cx-table cu-price-table">
          <thead>
            <tr><th>Movie</th><th>Economy</th><th>Business</th><th>First class</th></tr>
          </thead>
          <tbody>
            <?php foreach ($prices as $p): $id = $movie_ids[$p['movie_name_price']] ?? 0; ?>
              <tr>
                <td>
                  <?php if ($id): ?>
                    <a href="moviedetail.php?movdet=<?php echo $id; ?>"><?php echo e(trim($p['movie_name_price'])); ?></a>
                  <?php else: ?>
                    <?php echo e(trim($p['movie_name_price'])); ?>
                  <?php endif; ?>
                </td>
                <td><?php echo money($p['economy']); ?></td>
                <td><?php echo money($p['business']); ?></td>
                <td><?php echo money($p['first_class']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- extras -->
  <div class="row g-3">
    <div class="col-12 col-md-4">
      <div class="cx-card cu-feature h-100 mb-0">
        <i class="fas fa-hamburger"></i>
        <h4>Snacks</h4>
        <p>Add popcorn, nachos, drinks and combos at checkout. They are printed on your e-ticket.</p>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="cx-card cu-feature h-100 mb-0">
        <i class="fas fa-tags"></i>
        <h4>Promo codes</h4>
        <p>Have a code? Apply it at checkout for a percentage off your tickets and snacks.</p>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="cx-card cu-feature h-100 mb-0">
        <i class="fas fa-gift"></i>
        <h4>Loyalty points</h4>
        <p>Earn 1 point for every PKR 100 you spend. See them on your profile.</p>
      </div>
    </div>
  </div>

  <div class="text-center mt-4">
    <a href="index.php#movies" class="cx-btn"><i class="fas fa-ticket-alt"></i> Book now</a>
    <a href="faq.php" class="cx-btn cx-btn-ghost">Questions? Read the FAQ</a>
  </div>
</div>

<?php
include('footer.php');
?>
