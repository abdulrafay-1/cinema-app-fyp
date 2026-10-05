<?php
require_once __DIR__ . '/customer_functions.php';

// getting specific movie id
$mov_det_id=(int) ($_GET['movdet'] ?? 0);

$movie_details=db_one("select * from addmovie where movie_id=?", 'i', [$mov_det_id]);

// unknown or missing id: go back to the home page instead of showing an empty page
if(!$movie_details){
    redirect('index.php');
}

$page_url='moviedetail.php?movdet='.$mov_det_id;

// "Sign in to review" link: require_customer() sends guests to signin.php and brings them back here
if(isset($_GET['signin'])){
    require_customer();
    redirect($page_url.'#reviews');
}

// review and favorite actions (POST + CSRF, then redirect so a refresh doesn't repeat them)
if($_SERVER['REQUEST_METHOD']==='POST'){
    $user=require_customer();
    $action=$_POST['action'] ?? '';
    $anchor='#reviews';

    if(!csrf_ok()){
        flash('danger','Invalid request. Please try again.');
    }
    else if($action==='review_save'){
        $rating=(int) ($_POST['rating'] ?? 0);
        $comment=trim($_POST['comment'] ?? '');
        if($rating<1 || $rating>5){
            flash('danger','Please choose 1 to 5 stars.');
        }
        else if(mb_strlen($comment)<3 || mb_strlen($comment)>1000){
            flash('danger','Please write a comment of 3 to 1000 characters.');
        }
        else{
            // one review per user per movie: update it if it already exists
            $mine=db_one("select review_id from reviews where movie_id=? and user_id=?", 'ii', [$mov_det_id,(int) $user['id']]);
            if($mine){
                db_query("update reviews set rating=?, comment=? where review_id=?", 'isi', [$rating,$comment,(int) $mine['review_id']]);
                flash('success','Your review was updated.');
            }
            else{
                db_query("insert into reviews (movie_id,user_id,rating,comment) values (?,?,?,?)", 'iiis', [$mov_det_id,(int) $user['id'],$rating,$comment]);
                flash('success','Thanks! Your review was posted.');
            }
        }
    }
    else if($action==='review_delete'){
        db_query("delete from reviews where movie_id=? and user_id=?", 'ii', [$mov_det_id,(int) $user['id']]);
        flash('success','Your review was deleted.');
    }
    else if($action==='favorite'){
        $anchor='#top';
        $removed=db_query("delete from favorites where user_id=? and movie_id=?", 'ii', [(int) $user['id'],$mov_det_id]);
        if($removed>0){
            flash('success','Removed from your favorites.');
        }
        else{
            // INSERT IGNORE + the UNIQUE(user_id, movie_id) key: a double click can't add it twice
            db_query("insert ignore into favorites (user_id,movie_id) values (?,?)", 'ii', [(int) $user['id'],$mov_det_id]);
            flash('success','Added to your favorites.');
        }
    }
    redirect($page_url.$anchor);
}

$user=customer();

// average of visible reviews only (reviews the admin hid don't count)
$rating_row=db_one("select count(*) as total, avg(rating) as average from reviews where movie_id=? and status='Visible'", 'i', [$mov_det_id]);
$review_total=(int) $rating_row['total'];
$review_average=$review_total ? round((float) $rating_row['average'], 1) : 0;

// my own review is shown separately (with Edit / Delete), even if the admin hid it
$my_review=$user ? db_one("select * from reviews where movie_id=? and user_id=?", 'ii', [$mov_det_id,(int) $user['id']]) : null;
$editing=$my_review && isset($_GET['edit_review']);

$reviews=db_all(
    "select r.rating, r.comment, r.created_at, u.name, u.profile_pic
     from reviews r join user_reg_login u on u.id=r.user_id
     where r.movie_id=? and r.status='Visible' and r.user_id<>?
     order by r.created_at desc, r.review_id desc",
    'ii',
    [$mov_det_id,$user ? (int) $user['id'] : 0]
);

$is_favorite=$user && db_one("select id from favorites where user_id=? and movie_id=?", 'ii', [(int) $user['id'],$mov_det_id]);

// "More like this": other movies of the same genre
$similar=db_all(
    "select m.movie_id, m.movie_name, m.movie_img,
            (select avg(r.rating) from reviews r where r.movie_id=m.movie_id and r.status='Visible') as average
     from addmovie m where m.movie_genre_id=? and m.movie_id<>?
     order by m.movie_id desc limit 4",
    'ii',
    [(int) $movie_details['movie_genre_id'],$mov_det_id]
);

include('header.php');
?>

<!-- movie details -->
<section id="top">



<!-- movie title name -->
    <section>
              <div class="container" style="margin-top:120px;">
                         <h2 class="mb-3 display-3" style="color:white;text-transform:uppercase;text-align:center;">

                               <?php echo "📽 &nbsp;".e($movie_details['movie_name']); ?>
                         </h2>

                         <?php echo render_customer_flash(); ?>

                         <!-- rating summary + favorite -->
                         <div class="d-flex flex-wrap justify-content-center align-items-center gap-3 mb-4">
                               <div class="cu-rating-big">
                                     <?php if($review_total){ ?>
                                           <span class="cu-score"><?php echo e(number_format($review_average,1)); ?></span>
                                           <?php echo stars_html($review_average); ?>
                                           <a href="#reviews" class="cx-muted"><?php echo $review_total; ?> review<?php echo $review_total===1 ? '' : 's'; ?></a>
                                     <?php } else { ?>
                                           <?php echo stars_html(0); ?>
                                           <span class="cx-muted">No ratings yet</span>
                                     <?php } ?>
                               </div>

                               <form method="post" action="<?php echo e($page_url); ?>" class="m-0">
                                     <?php echo csrf_field(); ?>
                                     <input type="hidden" name="action" value="favorite">
                                     <button type="submit" class="cu-heart <?php echo $is_favorite ? 'is-on' : ''; ?>" aria-pressed="<?php echo $is_favorite ? 'true' : 'false'; ?>">
                                           <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i>
                                           <span><?php echo $is_favorite ? 'In your favorites' : 'Add to favorites'; ?></span>
                                     </button>
                               </form>
                         </div>
             </div>
    </section>
<!-- movie title name -->




<!-- movie trailer: ratio-16x9 keeps it 16:9 on every screen size -->

    <section id="trailer">

          <div class="container">

                <?php if (!empty($movie_details['movie_trl_url'])) { ?>
                <div class="ratio ratio-16x9" style="box-shadow: 0 5px 25px 0 rgb(0 0 0 / 30%);border-radius:16px;overflow:hidden;">
                      <iframe src="<?php echo  e($movie_details['movie_trl_url']); ?>?autoplay=1" title="<?php echo e($movie_details['movie_name']); ?> trailer" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
                </div>
                <?php } else if (!empty($movie_details['backdrop'])) { ?>
                <img src="Admin/upload-images/<?php echo e($movie_details['backdrop']); ?>" alt="<?php echo e($movie_details['movie_name']); ?>" style="width:100%;border-radius:16px;box-shadow: 0 5px 25px 0 rgb(0 0 0 / 30%);">
                <?php } ?>

          </div>

    </section>
<!-- movie trailer -->



<?php
require_once __DIR__ . '/includes/booking.php';
echo booking_css();
$md = db_one(
    "select g.genre_name, i.industry_name, l.language_name
     from addmovie m
     left join genre g on g.gen_id=m.movie_genre_id
     left join industry i on i.indus_id=m.movie_indus_id
     left join language l on l.lang_id=m.movie_lang_id
     where m.movie_id=?",
    'i',
    [$mov_det_id]
);
$md_runtime = (int) ($movie_details['runtime'] ?? 0);
$md_runtime_text = $md_runtime ? intdiv($md_runtime, 60) . 'h ' . ($md_runtime % 60) . 'm' : '';
$md_release = !empty($movie_details['release_date']) ? strtotime($movie_details['release_date']) : null;
$md_tmdb = (float) ($movie_details['rating'] ?? 0);
$md_chips = array_filter([
    $md['genre_name'] ?? null,
    $md['language_name'] ?? null,
    $md['industry_name'] ?? null,
    $md_release ? date('Y', $md_release) : null,
    $md_runtime_text ?: null,
]);
?>
<style>
  .md-card { background: #28282d; border-radius: 16px; padding: 28px; box-shadow: 0 10px 40px rgba(0, 0, 0, .35); border: 1px solid rgba(255, 255, 255, .06); }
  .md-poster { width: 100%; border-radius: 12px; box-shadow: 0 12px 30px rgba(0, 0, 0, .5); display: block; }
  .md-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
  .md-chip { padding: 5px 14px; border-radius: 999px; background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .1); color: rgba(255, 255, 255, .85); font-size: 13px; }
  .md-chip:first-child { background: linear-gradient(90deg, #ff55a5, #ff5860); border-color: transparent; color: #fff; font-weight: 600; }
  .md-label { font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #ff55a5; font-weight: 700; margin-bottom: 8px; }
  .md-synopsis { color: rgba(255, 255, 255, .78); font-size: 16px; line-height: 1.7; margin-bottom: 24px; }
  .md-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 24px; }
  .md-stat { background: rgba(255, 255, 255, .04); border: 1px solid rgba(255, 255, 255, .07); border-radius: 12px; padding: 14px 16px; }
  .md-stat small { display: block; color: rgba(255, 255, 255, .5); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
  .md-stat strong { color: #fff; font-size: 20px; font-weight: 700; }
  .md-stat strong .fa-star { color: #f5c542; font-size: 16px; }
  .md-stat span { color: rgba(255, 255, 255, .5); font-size: 13px; }
  .md-actions { display: flex; flex-wrap: wrap; gap: 10px; }
  @media (max-width: 991px) { .md-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 767px) { .md-card { padding: 18px; } .md-poster { max-width: 200px; margin: 0 auto; } }
</style>
<section class="container mt-4" id="details">
  <div class="md-card">
    <div class="row g-4 align-items-start">
      <div class="col-12 col-md-4 col-lg-3">
        <img class="md-poster" src="Admin/upload-images/<?php echo e($movie_details['movie_img']); ?>" alt="<?php echo e($movie_details['movie_name']); ?> poster">
      </div>
      <div class="col-12 col-md-8 col-lg-9">
        <?php if ($md_chips): ?>
          <div class="md-chips">
            <?php foreach ($md_chips as $md_chip): ?><span class="md-chip"><?php echo e(trim($md_chip)); ?></span><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="md-label">Synopsis</div>
        <p class="md-synopsis"><?php echo !empty($movie_details['description']) ? nl2br(e($movie_details['description'])) : e($movie_details['movie_name']) . ' is now showing in cinemas.'; ?></p>
        <div class="md-stats">
          <div class="md-stat">
            <small>TMDB rating</small>
            <strong><?php echo $md_tmdb > 0 ? '<i class="fa fa-star"></i> ' . e(number_format($md_tmdb, 1)) . '</strong> <span>/ 10</span>' : '-</strong>'; ?>
          </div>
          <div class="md-stat">
            <small>Audience</small>
            <strong><?php echo $review_total ? '<i class="fa fa-star"></i> ' . e(number_format($review_average, 1)) . '</strong> <span>/ 5 &middot; ' . $review_total . '</span>' : '-</strong> <span>No reviews yet</span>'; ?>
          </div>
          <div class="md-stat">
            <small>Runtime</small>
            <strong><?php echo $md_runtime_text ? e($md_runtime_text) : '-'; ?></strong>
          </div>
          <div class="md-stat">
            <small>Release</small>
            <strong style="font-size:17px;"><?php echo $md_release ? e(date('d M Y', $md_release)) : '-'; ?></strong>
          </div>
        </div>
        <div class="md-actions">
          <a class="cx-btn" href="select_show.php?movie_id=<?php echo (int) $movie_details['movie_id']; ?>"><i class="fa fa-ticket-alt"></i> Book Tickets</a>
          <?php if (!empty($movie_details['movie_trl_url'])): ?>
            <a class="cx-btn cx-btn-ghost" href="#trailer"><i class="fa fa-play"></i> Watch Trailer</a>
          <?php endif; ?>
          <a class="cx-btn cx-btn-ghost" href="#reviews"><i class="fa fa-comment"></i> Reviews</a>
        </div>
      </div>
    </div>
  </div>
</section>



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



<!-- reviews -->
<div class="container" id="reviews" style="margin-top:30px;">
  <div class="cx-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <h3 style="color:#fff;margin:0;font-family:'Ubuntu',sans-serif;">Reviews</h3>
      <?php if($review_total){ ?>
        <span class="cx-muted"><?php echo stars_html($review_average); ?> &nbsp;<?php echo e(number_format($review_average,1)); ?> average from <?php echo $review_total; ?> review<?php echo $review_total===1 ? '' : 's'; ?></span>
      <?php } ?>
    </div>

    <?php if(!$user){ ?>
      <!-- guests -->
      <p class="cx-muted mb-3"><a href="<?php echo e($page_url); ?>&amp;signin=1" style="color:var(--cx-pink);">Sign in</a> to rate this movie and write a review.</p>

    <?php } else if($my_review && !$editing){ ?>
      <!-- my review, with Edit / Delete -->
      <div class="cu-mine mb-3">
        <div class="cu-review-head">
          <strong>Your review</strong>
          <?php echo stars_html($my_review['rating']); ?>
          <small class="cx-muted"><?php echo e(date('d M Y', strtotime($my_review['created_at']))); ?></small>
          <?php if($my_review['status']==='Hidden'){ ?>
            <span class="cx-badge cx-badge-orange" title="Only you can see it">Hidden by admin</span>
          <?php } ?>
        </div>
        <p><?php echo e($my_review['comment']); ?></p>
        <div class="d-flex gap-2 mt-3">
          <a href="<?php echo e($page_url); ?>&amp;edit_review=1#reviews" class="cx-btn cx-btn-sm cx-btn-ghost"><i class="fas fa-pencil-alt"></i> Edit</a>
          <form method="post" action="<?php echo e($page_url); ?>" class="m-0" onsubmit="return confirm('Delete your review?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="review_delete">
            <button type="submit" class="cx-btn cx-btn-sm cx-btn-ghost"><i class="fas fa-trash-alt"></i> Delete</button>
          </form>
        </div>
      </div>

    <?php } else { ?>
      <!-- write (or edit) my review -->
      <form method="post" action="<?php echo e($page_url); ?>" class="cu-form cu-mine mb-3">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="review_save">
        <div class="cu-field">
          <label>Your rating</label>
          <!-- radios are listed 5..1 so the CSS can colour every star up to the one under the mouse -->
          <div class="cu-star-input" role="radiogroup" aria-label="Rating">
            <?php for($i=5;$i>=1;$i--){ ?>
              <input type="radio" name="rating" id="rate-<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo $my_review && (int) $my_review['rating']===$i ? 'checked' : ''; ?> required>
              <label for="rate-<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i===1 ? '' : 's'; ?>"><i class="fas fa-star"></i></label>
            <?php } ?>
          </div>
        </div>
        <div class="cu-field">
          <label for="review-comment">Your review</label>
          <textarea class="cx-input" id="review-comment" name="comment" minlength="3" maxlength="1000" placeholder="What did you think of the movie?" required><?php echo $my_review ? e($my_review['comment']) : ''; ?></textarea>
        </div>
        <button type="submit" class="cx-btn"><?php echo $editing ? 'Update review' : 'Post review'; ?></button>
        <?php if($editing){ ?>
          <a href="<?php echo e($page_url); ?>#reviews" class="cx-btn cx-btn-ghost">Cancel</a>
        <?php } ?>
      </form>
    <?php } ?>

    <!-- everyone's visible reviews, newest first -->
    <?php if(!$reviews && !$my_review){ ?>
      <p class="cx-muted m-0">No reviews yet. Be the first to review this movie!</p>
    <?php } ?>
    <?php foreach($reviews as $review){ ?>
      <div class="cu-review">
        <?php echo avatar_html($review, 42); ?>
        <div class="cu-review-body">
          <div class="cu-review-head">
            <strong><?php echo e($review['name']); ?></strong>
            <?php echo stars_html($review['rating']); ?>
            <small class="cx-muted"><?php echo e(date('d M Y', strtotime($review['created_at']))); ?></small>
          </div>
          <p><?php echo e($review['comment']); ?></p>
        </div>
      </div>
    <?php } ?>
  </div>
</div>
<!-- reviews -->



<!-- more like this: same genre -->
<?php if($similar){ ?>
<div class="container" style="margin-top:10px;margin-bottom:40px;">
  <h3 style="color:#fff;font-family:'Ubuntu',sans-serif;margin-bottom:16px;">More like this</h3>
  <div class="row g-3">
    <?php foreach($similar as $sim){ ?>
      <div class="col-6 col-md-3">
        <div class="cu-card">
          <a class="cu-poster" href="moviedetail.php?movdet=<?php echo (int) $sim['movie_id']; ?>">
            <img src="Admin/upload-images/<?php echo e($sim['movie_img']); ?>" alt="<?php echo e($sim['movie_name']); ?>" loading="lazy">
          </a>
          <div class="cu-card-body">
            <h3><a href="moviedetail.php?movdet=<?php echo (int) $sim['movie_id']; ?>"><?php echo e(trim($sim['movie_name'])); ?></a></h3>
            <div class="cx-muted" style="font-size:13px;">
              <?php echo $sim['average']!==null ? stars_html(round((float) $sim['average'],1)).' '.e(number_format((float) $sim['average'],1)) : 'No ratings yet'; ?>
            </div>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>
</div>
<?php } ?>
<!-- more like this -->



<!-- movie details -->
</section>


<?php
include('footer.php');
?>
