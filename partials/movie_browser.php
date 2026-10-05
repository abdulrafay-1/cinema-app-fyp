<?php
require_once __DIR__ . '/../includes/booking.php';

$browser_industry_id = isset($browser_industry_id) ? (int) $browser_industry_id : 0;
$browser_title = isset($browser_title) ? $browser_title : 'Movies';
$browser_filters = [
    'genre' => (int) ($_GET['genre'] ?? 0),
    'language' => (int) ($_GET['language'] ?? 0),
    'cinema' => (int) ($_GET['cinema'] ?? 0),
    'q' => trim((string) ($_GET['q'] ?? '')),
];

$browser_where = ['1 = 1'];
$browser_types = '';
$browser_params = [];
foreach (['industry' => [$browser_industry_id, 'm.movie_indus_id = ?'], 'genre' => [$browser_filters['genre'], 'm.movie_genre_id = ?'], 'language' => [$browser_filters['language'], 'm.movie_lang_id = ?']] as $browser_rule) {
    if ($browser_rule[0] > 0) {
        $browser_where[] = $browser_rule[1];
        $browser_types .= 'i';
        $browser_params[] = $browser_rule[0];
    }
}
if ($browser_filters['cinema'] > 0) {
    $browser_where[] = "EXISTS (SELECT 1 FROM shows sc WHERE sc.movie_id = m.movie_id AND sc.cin_id = ? AND sc.status = 'Active' AND TIMESTAMP(sc.show_date, sc.show_time) > NOW())";
    $browser_types .= 'i';
    $browser_params[] = $browser_filters['cinema'];
}
if ($browser_filters['q'] !== '') {
    $browser_where[] = 'm.movie_name LIKE ?';
    $browser_types .= 's';
    $browser_params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $browser_filters['q']) . '%';
}

$browser_movies = db_all(
    "SELECT m.movie_id, m.movie_name, m.movie_img, m.description, m.rating, m.release_date, g.genre_name, i.industry_name, l.language_name,
            (SELECT MIN(TIMESTAMP(s.show_date, s.show_time)) FROM shows s
              WHERE s.movie_id = m.movie_id AND s.status = 'Active' AND TIMESTAMP(s.show_date, s.show_time) > NOW()) AS next_show
     FROM addmovie m
     LEFT JOIN genre g ON g.gen_id = m.movie_genre_id
     LEFT JOIN industry i ON i.indus_id = m.movie_indus_id
     LEFT JOIN language l ON l.lang_id = m.movie_lang_id
     WHERE " . implode(' AND ', $browser_where) . "
     ORDER BY next_show IS NULL, m.popularity DESC, next_show, m.movie_id DESC",
    $browser_types,
    $browser_params
);
$browser_options = [
    'genre' => db_all('SELECT gen_id AS id, genre_name AS name FROM genre ORDER BY genre_name'),
    'language' => db_all('SELECT lang_id AS id, language_name AS name FROM language ORDER BY language_name'),
    'cinema' => db_all('SELECT cin_id AS id, cinema_name AS name FROM cinema ORDER BY cinema_name'),
];
$browser_action = basename($_SERVER['SCRIPT_NAME']);
$browser_active = $browser_filters['genre'] || $browser_filters['language'] || $browser_filters['cinema'] || $browser_filters['q'] !== '';

echo booking_css();
?>
<style>
  .mb-filters { display: grid; grid-template-columns: 2fr repeat(3, 1fr) auto; gap: 10px; align-items: end; }
  .mb-filters label { display: block; font-size: 12px; color: var(--cx-muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 1px; }
  .mb-filters select.cx-input { appearance: auto; }
  .mb-search { position: relative; }
  .mb-suggest { position: absolute; left: 0; right: 0; top: 100%; margin-top: 4px; background: #1f1f23; border: 1px solid var(--cx-line); border-radius: 8px; z-index: 50; overflow: hidden; display: none; box-shadow: 0 10px 30px rgba(0, 0, 0, .5); }
  .mb-suggest.is-open { display: block; }
  .mb-suggest a { display: flex; gap: 10px; align-items: center; padding: 8px 10px; color: #fff; text-decoration: none; }
  .mb-suggest a:hover, .mb-suggest a.is-active { background: rgba(255, 85, 165, .15); }
  .mb-suggest img { width: 32px; height: 46px; object-fit: cover; border-radius: 4px; }
  .mb-suggest small { display: block; color: var(--cx-muted); }
  .mb-card { background: var(--cx-card); border-radius: 10px; overflow: hidden; height: 100%; display: flex; flex-direction: column; box-shadow: 0 5px 25px rgba(0, 0, 0, .3); transition: transform .2s; }
  .mb-card:hover { transform: translateY(-4px); }
  .mb-card .mb-poster { position: relative; aspect-ratio: 2 / 3; background: #1a1a1e; }
  .mb-card .mb-poster img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .mb-card .mb-flag { position: absolute; left: 10px; top: 10px; }
  .mb-card .mb-body { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
  .mb-card h3 { font-size: 16px; margin: 0; color: #fff; font-family: 'Ubuntu', sans-serif; text-transform: uppercase; line-height: 1.3; }
  .mb-card h3 a { color: #fff; }
  .mb-card .mb-next { font-size: 12px; color: var(--cx-muted); }
  .mb-card .mb-actions { margin-top: auto; display: flex; gap: 6px; }
  .mb-card .mb-actions .cx-btn { flex: 1; padding: 8px 6px; font-size: 13px; }
  @media (max-width: 991px) { .mb-filters { grid-template-columns: 1fr 1fr; } .mb-filters .mb-search { grid-column: 1 / -1; } }
</style>
<div class="container cx-page" style="margin-top:40px;" id="movies">
  <h2 style="margin-bottom:16px;"><?php echo e($browser_title); ?></h2>
  <form class="cx-card mb-filters" method="get" action="<?php echo e($browser_action); ?>#movies" autocomplete="off">
    <div class="mb-search">
      <label for="mb-q">Search</label>
      <input type="search" class="cx-input" id="mb-q" name="q" value="<?php echo e($browser_filters['q']); ?>" placeholder="Type a movie name..." aria-autocomplete="list" aria-controls="mb-suggest">
      <div class="mb-suggest" id="mb-suggest" role="listbox"></div>
    </div>
    <?php foreach (['genre' => 'Genre', 'language' => 'Language', 'cinema' => 'Cinema'] as $browser_key => $browser_label): ?>
      <div>
        <label for="mb-<?php echo $browser_key; ?>"><?php echo $browser_label; ?></label>
        <select class="cx-input" id="mb-<?php echo $browser_key; ?>" name="<?php echo $browser_key; ?>" onchange="this.form.submit()">
          <option value="0">All</option>
          <?php foreach ($browser_options[$browser_key] as $browser_option): ?>
            <option value="<?php echo (int) $browser_option['id']; ?>" <?php echo $browser_filters[$browser_key] === (int) $browser_option['id'] ? 'selected' : ''; ?>><?php echo e(trim($browser_option['name'])); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endforeach; ?>
    <div style="display:flex;gap:6px;">
      <button type="submit" class="cx-btn" style="padding:10px 16px;"><i class="fa fa-search"></i></button>
      <?php if ($browser_active): ?><a href="<?php echo e($browser_action); ?>#movies" class="cx-btn cx-btn-ghost" style="padding:10px 14px;" title="Clear filters"><i class="fa fa-times"></i></a><?php endif; ?>
    </div>
  </form>

  <p class="cx-muted" style="font-size:13px;"><?php echo count($browser_movies); ?> movie<?php echo count($browser_movies) === 1 ? '' : 's'; ?> found<?php echo $browser_filters['cinema'] ? ' with upcoming shows at this cinema' : ''; ?>.</p>

  <?php if (!$browser_movies): ?>
    <div class="cx-card text-center cx-muted">No movies match these filters. <a href="<?php echo e($browser_action); ?>#movies" style="color:var(--cx-pink);">Clear filters</a></div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($browser_movies as $browser_movie): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <div class="mb-card">
          <a class="mb-poster" href="moviedetail.php?movdet=<?php echo (int) $browser_movie['movie_id']; ?>">
            <img src="Admin/upload-images/<?php echo e($browser_movie['movie_img']); ?>" alt="<?php echo e($browser_movie['movie_name']); ?>" loading="lazy">
            <span class="mb-flag cx-badge <?php echo $browser_movie['next_show'] ? 'cx-badge-green' : 'cx-badge-grey'; ?>"><?php echo $browser_movie['next_show'] ? 'Now Showing' : 'No shows'; ?></span>
            <?php if ((float) $browser_movie['rating'] > 0): ?><span class="cx-badge" style="position:absolute;right:10px;top:10px;background:rgba(0,0,0,.7);color:#f5c542;"><i class="fa fa-star"></i> <?php echo e(number_format((float) $browser_movie['rating'], 1)); ?></span><?php endif; ?>
          </a>
          <div class="mb-body">
            <h3><a href="moviedetail.php?movdet=<?php echo (int) $browser_movie['movie_id']; ?>"><?php echo e(trim($browser_movie['movie_name'])); ?></a></h3>
            <?php if ($browser_movie['release_date']): ?><div class="mb-next"><?php echo e(date('d M Y', strtotime($browser_movie['release_date']))); ?></div><?php endif; ?>
            <div>
              <?php foreach (['genre_name', 'industry_name', 'language_name'] as $browser_field): if ($browser_movie[$browser_field]): ?>
                <span class="cx-badge" style="font-size:11px;"><?php echo e(trim($browser_movie[$browser_field])); ?></span>
              <?php endif; endforeach; ?>
            </div>
            <div class="mb-next"><?php echo $browser_movie['next_show'] ? '<i class="fa fa-clock"></i> Next: ' . e(date('D d M, g:i A', strtotime($browser_movie['next_show']))) : 'Shows coming soon'; ?></div>
            <div class="mb-actions">
              <a class="cx-btn cx-btn-ghost" href="moviedetail.php?movdet=<?php echo (int) $browser_movie['movie_id']; ?>">Details</a>
              <a class="cx-btn <?php echo $browser_movie['next_show'] ? '' : 'is-disabled'; ?>" href="select_show.php?movie_id=<?php echo (int) $browser_movie['movie_id']; ?>">Book</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="cx-muted" style="font-size:11px;margin-top:18px;display:flex;align-items:center;gap:8px;">
    <img src="https://www.themoviedb.org/assets/2/v4/logos/v2/blue_short-8e7b30f73a4020692ccca9c88bafe5dcb6f8a62a4c6bc55cd9ba82bb2cd95f6c.svg" alt="TMDB" style="height:12px;" loading="lazy">
    This product uses the TMDB API but is not endorsed or certified by TMDB.
  </p>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var input = document.getElementById('mb-q');
  var box = document.getElementById('mb-suggest');
  var timer = null, active = -1, seq = 0;
  function close() { box.classList.remove('is-open'); box.innerHTML = ''; active = -1; }
  function render(results) {
    box.innerHTML = '';
    results.forEach(function (r) {
      var a = document.createElement('a');
      a.href = 'moviedetail.php?movdet=' + r.id;
      a.setAttribute('role', 'option');
      var img = document.createElement('img'); img.src = r.poster; img.alt = '';
      var text = document.createElement('div');
      var name = document.createElement('div'); name.textContent = r.name;
      var meta = document.createElement('small'); meta.textContent = r.meta;
      text.appendChild(name); text.appendChild(meta);
      a.appendChild(img); a.appendChild(text);
      box.appendChild(a);
    });
    if (!results.length) {
      var none = document.createElement('a'); none.textContent = 'No movies found'; none.href = 'javascript:void(0)';
      box.appendChild(none);
    }
    box.classList.add('is-open');
  }
  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { close(); return; }
    timer = setTimeout(function () {
      var mine = ++seq;
      fetch('ajax/search.php?q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (data) { if (mine === seq) render(data.results || []); })
        .catch(close);
    }, 200);
  });
  input.addEventListener('keydown', function (e) {
    var items = box.querySelectorAll('a');
    if (!items.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
      items.forEach(function (it, i) { it.classList.toggle('is-active', i === active); });
    } else if (e.key === 'Enter' && active >= 0) {
      e.preventDefault();
      window.location.href = items[active].href;
    } else if (e.key === 'Escape') {
      close();
    }
  });
  document.addEventListener('click', function (e) { if (!box.contains(e.target) && e.target !== input) close(); });
});
</script>
