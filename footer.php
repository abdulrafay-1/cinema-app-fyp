
	<!-- footer: every link goes to a real page -->
	<footer class="footer">
		<div class="container">
			<div class="row">
				<!-- about -->
				<div class="col-12 col-md-4">
					<h6 class="footer__title"><?php echo e(SITE_NAME); ?></h6>
					<p class="cx-muted" style="font-size:14px;line-height:1.7;margin-bottom:20px;">Book your cinema tickets online: choose a show, pick your seats, add snacks, pay and get an e-ticket with a QR code.</p>
				</div>
				<!-- end about -->

				<!-- footer list -->
				<div class="col-6 col-md-2">
					<h6 class="footer__title">Explore</h6>
					<ul class="footer__list">
						<li><a href="index.php">Home</a></li>
						<li><a href="index.php#movies">Movies</a></li>
						<li><a href="pricing.php">Ticket Pricing</a></li>
						<li><a href="userbooking.php">My Bookings</a></li>
					</ul>
				</div>
				<!-- end footer list -->

				<!-- footer list -->
				<div class="col-6 col-md-2">
					<h6 class="footer__title">Help</h6>
					<ul class="footer__list">
						<li><a href="about.php">About Us</a></li>
						<li><a href="faq.php">FAQ</a></li>
						<li><a href="contact.php">Contact</a></li>
					</ul>
				</div>
				<!-- end footer list -->

				<!-- footer list -->
				<div class="col-12 col-md-4">
					<h6 class="footer__title">Contact</h6>
					<ul class="footer__list">
						<li><a href="<?php echo e(phone_link(CONTACT_PHONE)); ?>"><i class="fas fa-phone-alt" style="color:#ff55a5;width:18px;"></i> <?php echo e(CONTACT_PHONE); ?></a></li>
						<li><a href="mailto:<?php echo e(CONTACT_EMAIL); ?>"><i class="fas fa-envelope" style="color:#ff55a5;width:18px;"></i> <?php echo e(CONTACT_EMAIL); ?></a></li>
						<li class="cx-muted" style="font-size:14px;"><i class="fas fa-map-marker-alt" style="color:#ff55a5;width:18px;"></i> <?php echo e(CONTACT_ADDRESS); ?></li>
					</ul>
				</div>
				<!-- end footer list -->

				<!-- footer copyright -->
				<div class="col-12">
					<div class="footer__copyright">
						<small>&copy; 2026 <?php echo e(SITE_NAME); ?>. Final Year Project by <?php echo e(SITE_TEAM); ?>.</small>
					</div>
				</div>
				<!-- end footer copyright -->
			</div>
		</div>
	</footer>
	<!-- end footer -->


	<!-- JS -->
	<script src="./bootjs/bootstrap.min.js"></script>
	<!-- bootstrap js -->
	<script src="js/jquery-3.3.1.min.js"></script>
	<script src="js/bootstrap.bundle.min.js"></script>
	<script src="js/owl.carousel.min.js"></script>
	<script src="js/jquery.mousewheel.min.js"></script>
	<script src="js/jquery.mCustomScrollbar.min.js"></script>
	<script src="js/wNumb.js"></script>
	<script src="js/nouislider.min.js"></script>
	<script src="js/plyr.min.js"></script>
	<script src="js/jquery.morelines.min.js"></script>
	<script src="js/photoswipe.min.js"></script>
	<script src="js/photoswipe-ui-default.min.js"></script>
	<script src="js/main.js"></script>
</body>

</html>