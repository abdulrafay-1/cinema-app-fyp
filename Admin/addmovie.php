<?php
include('auth.php');
include('admin_header.php');
?>


<!-- must include this file in every page for page title  -->
<div class="page-content">
        <div class="page-header">
          <div class="container-fluid">
            <h2 class="h5 no-margin-bottom">Add Movies</h2>
          </div>
        </div>
<!-- must include this file in every page for page title  -->

<div class="col-lg-12">                           
                <div class="block">
                  <div class="title"><strong>Movies</strong></div>
                  <div class="block-body">
                    <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                    <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Enter Movies Name</label>
                        <div class="col-sm-9">
                          <input type="text" class="form-control" name="movie_name" placeholder="Movie Name">
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Enter Cinema Location</label>
                        <div class="col-sm-9">
                        <select class="form-control" name="movie_cinema">
                             <option selected disabled>Select</option>
                                  <?php
                                         $cinema_select=mysqli_query($con,"select * from cinema");

                                         foreach($cinema_select as $cinema_select_data)
                                         {
                                             echo 
                                             "
                                             <option value=$cinema_select_data[cin_id]>$cinema_select_data[cinema_name]</option>
                                             ";
                                         }
                                  ?>
                           </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Enter Movie Genre</label>
                        <div class="col-sm-9">
                        <select class="form-control" name="movie_genre">
                             <option selected disabled>Select</option>
                                  <?php
                                         $genre_select=mysqli_query($con,"select * from genre");

                                         foreach($genre_select as $genre_select_data)
                                         {
                                             echo 
                                             "
                                             <option value=$genre_select_data[gen_id]>$genre_select_data[genre_name]</option>
                                             ";
                                         }
                                  ?>
                           </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Enter Movie Industry</label>
                        <div class="col-sm-9">
                        <select class="form-control" name="movie_industry">
                             <option selected disabled>Select</option>
                                  <?php
                                         $industry_select=mysqli_query($con,"select * from industry");

                                         foreach($industry_select as $industry_select_data)
                                         {
                                             echo 
                                             "
                                             <option value=$industry_select_data[indus_id]>$industry_select_data[industry_name]</option>
                                             ";
                                         }
                                  ?>
                           </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Enter Movie language</label>
                        <div class="col-sm-9">
                        <select class="form-control" name="movie_language">
                             <option selected disabled>Select</option>
                                  <?php
                                         $language_select=mysqli_query($con,"select * from language");

                                         foreach($language_select as $language_select_data)
                                         {
                                             echo 
                                             "
                                             <option value=$language_select_data[lang_id]>$language_select_data[language_name]</option>
                                             ";
                                         }
                                  ?>
                           </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Enter Movie Date</label>
                        <div class="col-sm-9">
                        <select class="form-control" name="movie_date">
                             <option selected disabled>Select</option>
                                  <?php
                                         $date_select=mysqli_query($con,"select * from date");

                                         foreach($date_select as $date_select_data)
                                         {
                                             echo 
                                             "
                                             <option value=$date_select_data[date_id]>$date_select_data[date_name]</option>
                                             ";
                                         }
                                  ?>
                           </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Enter Movie Poster Image</label>
                        <div class="col-sm-9">
                          <input type="file" class="form-control" name="movie_img" placeholder="Movie Poster" required>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Enter Movie Trailer</label>
                        <div class="col-sm-9">
                          <input type="text" class="form-control" name="movie_trl_url" placeholder="Movie Trailer URL">
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Movie Description</label>
                        <div class="col-sm-9">
                          <textarea class="form-control" name="movie_description" rows="4" placeholder="Short story of the movie"></textarea>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Movie Duration</label>
                        <div class="col-sm-9">
                        <select  class="form-control" name="movie_duration">
                          <option disabled selected>Movie Duration in Hours . (Example : 5 AM to 7 AM )</option>
                                       <option>1:00 PM to 2:15 PM</option>
                                       <option>2:15 PM to 4:00 PM</option>
                                       <option>4:00 PM to 5:30 PM</option>
                                       <option>5:30 PM to 7:25 PM</option>
                                       <option>7:25 PM to 9:10 PM</option>
                                       <option>9:10 PM PM to 10:40 PM</option>
                                       <option>10:40 PM PM to 12:00 AM</option>
                                       <option>12:00 PM PM to 1:30 AM</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-sm-9 ml-auto">
                          <button type="submit" class="btn btn-secondary" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);" name="add_movie_btn">Add Movies</button>
                        </div>
                    </form>
                  </div>
                </div>
              </div>

<!-- insert add movie to data base -->
              <?php 
              
              
              if(isset($_POST['add_movie_btn']))
              {
                  // the "Select" options are disabled, so an unpicked dropdown is not sent at all
                  $mov_name=isset($_POST['movie_name']) ? trim($_POST['movie_name']) : '';
                  $mov_cinema=isset($_POST['movie_cinema']) ? $_POST['movie_cinema'] : '';
                  $mov_genre=isset($_POST['movie_genre']) ? $_POST['movie_genre'] : '';
                  $mov_industry=isset($_POST['movie_industry']) ? $_POST['movie_industry'] : '';
                  $mov_lang=isset($_POST['movie_language']) ? $_POST['movie_language'] : '';
                  $mov_date=isset($_POST['movie_date']) ? $_POST['movie_date'] : '';
                  $mov_trl_url=isset($_POST['movie_trl_url']) ? $_POST['movie_trl_url'] : '';
                  $mov_dur=isset($_POST['movie_duration']) ? $_POST['movie_duration'] : '';
                  $mov_desc=isset($_POST['movie_description']) ? trim($_POST['movie_description']) : '';
                  // for img upload and save
                  $mov_image=isset($_FILES['movie_img']) ? basename($_FILES['movie_img']['name']) : '';
                  $mov_image_tmp=isset($_FILES['movie_img']) ? $_FILES['movie_img']['tmp_name'] : '';
                  // only image files go into upload-images
                  $allowed_ext=['jpg','jpeg','png','gif','webp'];
                  if($mov_name=='' || $mov_cinema=='' || $mov_genre=='' || $mov_industry=='' || $mov_lang=='' || $mov_date=='' || $mov_dur=='')
                  {
                      echo "<script>alert('Please fill in the movie name and pick every dropdown.');</script>";
                  }
                  else if($mov_image=='' || !in_array(strtolower(pathinfo($mov_image, PATHINFO_EXTENSION)), $allowed_ext))
                  {
                      echo "<script>alert('Please upload a JPG, PNG, GIF or WEBP poster.');</script>";
                  }
                  else
                  {
                      move_uploaded_file($mov_image_tmp,"upload-images/".$mov_image);

                      $insert=db_query("insert into addmovie (movie_name,movie_cin_id,movie_genre_id,movie_indus_id,movie_lang_id,movie_date_id,movie_img,movie_trl_url,movie_duration,description) values (?,?,?,?,?,?,?,?,?,?)",
                          'siiiiissss',
                          [$mov_name,$mov_cinema,$mov_genre,$mov_industry,$mov_lang,$mov_date,$mov_image,$mov_trl_url,$mov_dur,$mov_desc]);

                      if($insert>0)
                      {
                          log_activity('Added movie', $mov_name);
                          echo "<script>window.location.assign('viewmovie.php');</script>";
                      }
                      else
                      {
                          echo "<script>alert('Something Went Wrong Try Another Time!');</script>";
                      }
                  }
              }


              
              
              ?>

<?php

include('admin_footer.php');

?>