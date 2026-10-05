<?php
include('header.php');
?>

    <!-- home -->
    <section class="home">
		<!-- home bg -->
		<div class="owl-carousel home__bg">
			<div class="item home__cover" data-bg="img/home/home__bg.jpg"></div>
			<div class="item home__cover" data-bg="img/home/home__bg2.jpg"></div>
			<div class="item home__cover" data-bg="img/home/home__bg3.jpg"></div>
			<div class="item home__cover" data-bg="img/home/home__bg4.jpg"></div>
		</div>
		<!-- end home bg -->
        <!-- carousol -->
        <div class="container-fluid">
 <div class="row">

 <div class="wrapper mb-5">
    <div class="typing-demo">
      book your tickets for <span style="color:#ff55a5;text-shadow: 2px 2px 20px #ff5860;">movies</span>
    </div>
 </div>

 <?php include('partials/home_slider.php'); ?>

 </div>
        </div>
</section>
        <!-- homepg carousol -->

<?php
$browser_title = 'Now Showing & All Movies';
include('partials/movie_browser.php');
include('partials/coming_soon.php');
include('footer.php');
?>
