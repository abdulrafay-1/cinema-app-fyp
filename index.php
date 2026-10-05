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

 <div id="carouselExampleFade" class="carousel slide carousel-fade" data-bs-ride="carousel">
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img src="img/home/slider1.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider4.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider3.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider7.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider5.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider6.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider2.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider9.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider10.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider11.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider12.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider13.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider14.jpg" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item">
      <img src="img/home/slider15.jpg" class="d-block w-100" alt="...">
    </div>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>
</div>

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
