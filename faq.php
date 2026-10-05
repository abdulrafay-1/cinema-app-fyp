<?php
include('header.php');

// questions and answers (the refund and loyalty rules come from database/SCHEMA_CONTRACT.md)
$faqs = [
    ['How do I book a ticket?',
     'Open a movie from the home page (or use the search box), then click <b>Book Tickets</b> or <b>Select Seats</b> next to a show. Pick a date and showtime, choose your seats on the seat map, click <b>Continue</b>, add snacks or a promo code at checkout, and pay. You need to be signed in to book; if you are not, we send you to sign in and bring you back to your seats.'],
    ['What are the seat classes and prices?',
     'Every hall has three classes: rows <b>A to C are Economy</b>, <b>D to F are Business</b> and <b>G to H are First class</b>. Each movie has its own price for each class, listed on the <a href="pricing.php">Ticket Pricing</a> page and shown on the seat map when you hover a seat.'],
    ['How many seats can I book, and how long are they kept for me?',
     'You can book up to <b>10 seats</b> in one booking. When you click Continue, your seats are <b>held for 5 minutes</b> while you check out, and nobody else can take them. A timer shows how much time is left. If it runs out, the seats are released and you can pick them again.'],
    ['Can I order snacks?',
     'Yes. At checkout you can add popcorn, nachos, drinks, hot dogs and combos with the + and - buttons. The snack total is added to your booking and printed on your e-ticket, so you just show the ticket at the snack counter.'],
    ['How do promo codes work?',
     'Type the code at checkout and click <b>Apply</b>. The discount is a percentage of your tickets and snacks. You can use one code per booking. A code can stop working when it expires or reaches its usage limit; you will see a message if it is not valid.'],
    ['Which payment methods can I use?',
     'You can pay by <b>debit/credit card</b>, <b>JazzCash</b> or <b>Easypaisa</b>. JazzCash and Easypaisa ask for a one-time code (OTP). Note: this is a final year project, so payments are a safe demo and no real money is charged. We only keep the last 4 digits of your card or mobile number.'],
    ['How do I get my e-ticket and use the QR code?',
     'Right after payment you see your e-ticket with a booking reference (like CNX-2026-00001) and a QR code. You can open it any time from <b>My Bookings</b> and <b>print or save it as a PDF</b>. At the cinema, staff scan the QR code once; after that the ticket is marked as used and can\'t be used again.'],
    ['Can I cancel? How much do I get back?',
     'Yes, from <b>My Bookings</b>, as long as the show has not started. If you cancel <b>24 hours or more</b> before the show you get a <b>100% refund</b>; if you cancel <b>less than 24 hours</b> before, you get <b>50%</b>. Your seats are released straight away and the refund shows as <b>Pending</b> until our staff approve it. If a refund is rejected, the reason is shown on your booking.'],
    ['What are loyalty points?',
     'You earn <b>1 loyalty point for every PKR 100</b> you spend on a booking. Your points are shown on your <a href="profile.php">Profile</a> and on My Bookings. If you cancel a booking, the points you earned for it are removed.'],
    ['How do I manage my account?',
     'Use the menu under your name. On <a href="profile.php">Profile</a> you can change your name, phone number and photo, and change your password. Your email is your login, so it can\'t be changed. You can also save movies to <a href="favorites.php">Favorites</a> and write one review per movie. If your account is blocked or you need help, please <a href="contact.php">contact us</a>.'],
];
?>
	<!-- page title -->
	<section class="section section--first section--bg" data-bg="img/section/section.jpg">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<div class="section__wrap">
						<!-- section title -->
						<h2 class="section__title">FAQ</h2>
						<!-- end section title -->

						<!-- breadcrumb -->
						<ul class="breadcrumb">
							<li class="breadcrumb__item"><a href="index.php">Home</a></li>
							<li class="breadcrumb__item breadcrumb__item--active">FAQ</li>
						</ul>
						<!-- end breadcrumb -->
					</div>
				</div>
			</div>
		</div>
	</section>
	<!-- end page title -->

	<!-- faq: <details> opens and closes without any JavaScript and works on every phone -->
	<div class="container cx-page" style="margin-top:40px;">
		<p class="cx-muted">Answers to the questions we get most often. Can't find yours? <a href="contact.php" style="color:var(--cx-pink);">Send us a message</a>.</p>
		<div class="cu-faq">
			<?php foreach ($faqs as $i => $faq): ?>
				<details class="cx-card" <?php echo $i === 0 ? 'open' : ''; ?>>
					<summary><?php echo e($faq[0]); ?></summary>
					<!-- answers are our own fixed text (with a few <b>/<a> tags), not user input -->
					<p><?php echo $faq[1]; ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
	<!-- end faq -->

<?php
include('footer.php');
?>
