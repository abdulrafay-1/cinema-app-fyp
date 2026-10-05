<?php
require_once __DIR__ . '/includes/functions.php';

// getting specific movie id
$mov_det_id=isset($_GET['movdet']) ? $_GET['movdet'] : '';

$movie_details=db_one("select * from addmovie where movie_id=?", 'i', [$mov_det_id]);

// unknown or missing id: go back to the home page instead of showing an empty page
if(!$movie_details){
    redirect('index.php');
}

include('header.php');
?>

<!-- movie details -->
<section>



<!-- movie title name -->
    <section>
              <div class="container" style="margin-top:120px;">
                         <h2 class="mb-4 display-3" style="color:white;text-transform:uppercase;text-align:center;">

                               <?php echo "📽 &nbsp;".e($movie_details['movie_name']); ?>
                         </h2>
             </div>
    </section>
<!-- movie title name -->




<!-- movie trailer -->

    <section>

          <div class="container">

                <iframe width="1140" height="641" src="<?php echo  e($movie_details['movie_trl_url']); ?>?autoplay=1" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen style="box-shadow: 0 5px 25px 0 rgb(0 0 0 / 30%);"></iframe>   

          </div>

    </section>
<!-- movie trailer -->



<!-- movie accordion -->

<div class="container">

<div class="accordion accordion-flush" id="accordionFlushExample">
  <div class="accordion-item">
    <h2 class="accordion-header" id="flush-headingOne">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
      SYNOPSIS:
      </button>
    </h2>
    <div id="flush-collapseOne" class="accordion-collapse collapse" aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
      <div class="accordion-body">
            <?php
            // description is set by the admin on Add / Edit Movie
            if(!empty($movie_details['description'])){
                echo nl2br(e($movie_details['description']));
            }
            else{
                echo "<b>".e($movie_details['movie_name'])."</b> is now showing in cinemas.";
            }
            ?>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header" id="flush-headingTwo">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
        SHOWS:
      </button>
    </h2>
    <div id="flush-collapseTwo" class="accordion-collapse collapse" aria-labelledby="flush-headingTwo" data-bs-parent="#accordionFlushExample">
      <div class="accordion-body">
      <span>Cinema :
            <?php 
                                         	
            $cin_data=db_one("select cinema_name from cinema where cin_id=?", 'i', [$movie_details['movie_cin_id']]);
            echo $cin_data ? e($cin_data['cinema_name']) : '-';
                                         	
                                         	
            ?>
            </span><hr>
            <span>Show-Times :
            <?php 
                                         	
      	echo e($movie_details['movie_duration']);
                                         	
                                         	
            ?>
            </span><hr>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header" id="flush-headingThree">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">
        OTHER DETAILS:
      </button>
    </h2>
    <div id="flush-collapseThree" class="accordion-collapse collapse" aria-labelledby="flush-headingThree" data-bs-parent="#accordionFlushExample">
      <div class="accordion-body">
            <span>Ratings :
            <span class="fa fa-star checked"></span>
            <span class="fa fa-star checked"></span>
            <span class="fa fa-star checked"></span>
            <span class="fa fa-star checked"></span>
            <span class="fa fa-star checked"></span>
            </span><br><hr>
            <span>Genre : 
            <?php 

            $genre_data=db_one("select genre_name from genre where gen_id=?", 'i', [$movie_details['movie_genre_id']]);
            echo $genre_data ? e($genre_data['genre_name']) : '-';

            ?>
            </span><br><hr>
            <span>Industry :
            <?php
                   $indus_data=db_one("select industry_name from industry where indus_id=?", 'i', [$movie_details['movie_indus_id']]);
                   echo $indus_data ? e($indus_data['industry_name']) : '-';
            ?>
            </span><br><hr>
            <span>Language :
                  <?php 
                  $lang_data=db_one("select language_name from language where lang_id=?", 'i', [$movie_details['movie_lang_id']]);
                  echo $lang_data ? e($lang_data['language_name']) : '-';
                  ?>
            </span><br><hr>
      </div>
    </div>
  </div>
</div>

</div>

<!-- movie accordion -->





<!-- upcoming shows (Rafay's partial); file_exists keeps this page working if the partial is ever missing -->
<?php if (file_exists(__DIR__ . '/partials/show_list.php')) { $show_list_movie_id = $movie_details['movie_id']; include('partials/show_list.php'); } ?>

<!-- book movie btn -->
<hr style="color:white;">
<center>
<a href="select_show.php?movie_id=<?php echo (int) $movie_details['movie_id']; ?>" class="header__sign-in">
      <i class="icon ion-ios-log-in"></i>
      <span>Book Tickets</span>
</a>
</center>
<hr style="color:white;">
<!-- book movie btn -->





<!-- movie details -->
</section>


<?php
include('footer.php');
?>