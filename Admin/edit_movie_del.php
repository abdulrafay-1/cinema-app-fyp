<?php
include('auth.php');
include('admin_header.php');
?>
<?php 


$movie_edit_id=isset($_GET['editmov']) ? $_GET['editmov'] : '';
$edit_movie=['movie_name'=>'','movie_img'=>'','movie_trl_url'=>'','movie_duration'=>'','description'=>''];
$edit_movie_select=db_all("select * from addmovie where movie_id=?", 'i', [$movie_edit_id]);
foreach($edit_movie_select as $edit_movie)
{
//     echo $edit_movie['PId'];
   
//     echo $edit_movie['PPrice'];
//     echo $edit_movie['PDescription'];
//     echo $edit_moive['Image'];
}

?>




<!-- must include this file in every page for page title  -->
<div class="page-content">
        <div class="page-header">
          <div class="container-fluid">
            <h2 class="h5 no-margin-bottom">Edit Movie Information</h2>
          </div>
        </div>
<!-- must include this file in every page for page title  -->

<div class="col-lg-12">                           
                <div class="block">
                  <div class="title"><strong>Update Movies</strong></div>
                  <div class="block-body">
                    <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Movies Name</label>
                        <div class="col-sm-9">
                            <!-- hidden input here -->
                        <input type="hidden" name="movie_edit_id" value="<?php echo e($movie_edit_id); ?>">
                        <!-- edit movie name -->
                          <input type="text" class="form-control" name="edit_movie_name" value="<?php echo e($edit_movie['movie_name']); ?>">
                        </div>
                      </div>
                      <div class="form-group row">
                        <!-- edit movie date -->
                        <label class="col-sm-3 form-control-label">Movie Image Poster</label>
                        <div class="col-sm-9">
                          <?php if($edit_movie['movie_img']!=''){ ?>
                            <img src="upload-images/<?php echo e($edit_movie['movie_img']); ?>" width="70" height="70" style="margin-bottom:8px;">
                          <?php } ?>
                          <input type="file" class="form-control" name="edit_movie_img" accept=".jpg,.jpeg,.png,.webp">
                          <small class="form-text text-muted">JPG, PNG or WEBP, max 2 MB. Leave empty to keep the current poster.</small>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Movie Trailer</label>
                        <div class="col-sm-9">
                          <input type="text" class="form-control" name="edit_movie_trl_url" value="<?php echo e($edit_movie['movie_trl_url']); ?>">
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Movie Duration</label>
                        <div class="col-sm-9">
                          <input type="text" class="form-control" name="edit_movie_duration" value="<?php echo e($edit_movie['movie_duration']); ?>">
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 form-control-label">Movie Description</label>
                        <div class="col-sm-9">
                          <textarea class="form-control" name="edit_movie_description" rows="4"><?php echo e($edit_movie['description']); ?></textarea>
                        </div>
                      </div>
                      <div class="col-sm-9 ml-auto">
                          <button type="submit" class="btn btn-secondary" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);" name="edit_add_movie_btn">Update Movie Info</button>
                        </div>
                    </form>
                  </div>
                </div>
              </div>

<!-- update add movie to data base -->
              <?php 
              
              
              if(isset($_POST['edit_add_movie_btn']) && !csrf_ok()){ echo swal_script('error', 'Invalid request. Please reload the page and try again.'); }
                  else if(isset($_POST['edit_add_movie_btn']))
              {
                  $addmovie_edit_id=$_POST['movie_edit_id'];
                  $edit_mov_name=$_POST['edit_movie_name'];
                  $edit_mov_trl_url=$_POST['edit_movie_trl_url'];
                  $edit_mov_dur=$_POST['edit_movie_duration'];
                  $edit_mov_desc=isset($_POST['edit_movie_description']) ? trim($_POST['edit_movie_description']) : '';
                  // for img upload and save: no new file means keep the old poster
                  $edit_mov_image=$edit_movie['movie_img'];
                  // same shared upload as Add Movie: real image check, max 2 MB, random name; null = no new file
                  require_once __DIR__ . '/admin_upload.php';
                  $upload_error='';
                  $new_image=admin_upload_image('edit_movie_img', $upload_error);
                  $image_ok=$new_image!==false;
                  if(!$image_ok)
                  {
                      echo swal_script('error', $upload_error);
                  }
                  else if($new_image)
                  {
                      $edit_mov_image=$new_image;
                  }

                  $movie_edited=false;
                  if($image_ok)
                  {
                      $movie_edited=db_query("update addmovie set movie_name=?, movie_img=?, movie_trl_url=?, movie_duration=?, description=? where movie_id=?",
                          'sssssi',
                          [$edit_mov_name,$edit_mov_image,$edit_mov_trl_url,$edit_mov_dur,$edit_mov_desc,$addmovie_edit_id]);
                  }

                  if($movie_edited!==false){

                    log_activity('Edited movie', 'movie_id=' . $addmovie_edit_id . ', ' . $edit_mov_name);
                    // a replaced poster is deleted only if this app created it (random name), never a shared TMDB poster
                    if($new_image){
                        admin_delete_upload($edit_movie['movie_img']);
                    }

                    echo "
                     <script>
                     window.location.assign('viewmovie.php');
                     </script>
                     ";
                  }


              }


              
              
              ?>

<?php

include('admin_footer.php');

?>