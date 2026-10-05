<?php
require_once __DIR__ . '/includes/booking.php';

require_login();
$user_id = current_user_id();

$show = bookable_show(isset($_GET['show_id']) ? $_GET['show_id'] : 0);
if (!$show) {
    flash('danger', 'That show is no longer available for booking. Please pick another show.');
    redirect('index.php');
}

$prices = movie_prices($show['movie_name']);
$states = seat_states($show['show_id'], $user_id);
$rows = row_letters($show['total_rows']);
$per_row = (int) $show['seats_per_row'];
$aisle_after = (int) ceil($per_row / 2);

include('header.php');
echo booking_css();
?>
<div class="container cx-page">
  <div class="cx-card">
    <div class="row g-3 align-items-center">
      <div class="col-3 col-md-1">
        <img class="cx-poster" src="Admin/upload-images/<?php echo e($show['movie_img']); ?>" alt="">
      </div>
      <div class="col-9 col-md-7">
        <h3 style="margin:0 0 4px;"><?php echo e($show['movie_name']); ?></h3>
        <div class="cx-muted">
          <?php echo e($show['cinema_name']); ?> &middot; <?php echo e($show['hall_name']); ?><br>
          <?php echo e(show_date_label($show['show_date'])); ?> &middot; <?php echo e(show_time_label($show['show_time'])); ?>
        </div>
      </div>
      <div class="col-12 col-md-4 text-md-end">
        <a class="cx-btn cx-btn-sm cx-btn-ghost" href="select_show.php?movie_id=<?php echo (int) $show['movie_id']; ?>"><i class="fa fa-arrow-left"></i> Change show</a>
      </div>
    </div>
  </div>

  <?php echo render_flash(); ?>

  <?php if (!$prices): ?>
    <div class="cx-alert cx-alert-error">Ticket prices are not set for this movie yet, so booking is closed. Please try another movie.</div>
  <?php else: ?>
  <div id="seat-msg"></div>

  <div class="cx-card">
    <div class="cx-screen">SCREEN</div>
    <div class="cx-seat-wrap">
      <div class="cx-seats" id="seat-grid">
        <?php $previous_class = null; foreach ($rows as $row): $class = seat_class($row); ?>
          <?php if ($previous_class !== null && $class !== $previous_class): ?><div class="cx-class-gap"></div><?php endif; $previous_class = $class; ?>
          <div class="cx-row">
            <span class="cx-row-label"><?php echo $row; ?></span>
            <?php for ($n = 1; $n <= $per_row; $n++): $label = $row . $n;
                $state = in_array($label, $states['booked'], true) ? 'is-booked' : (in_array($label, $states['held'], true) ? 'is-held' : (in_array($label, $states['mine'], true) ? 'is-selected' : '')); ?>
              <button type="button" class="cx-seat c-<?php echo $class; ?> <?php echo $state; ?>" data-label="<?php echo $label; ?>" data-class="<?php echo $class; ?>" title="<?php echo $label . ' · ' . $class . ' · ' . money($prices[$class]); ?>" <?php echo ($state === 'is-booked' || $state === 'is-held') ? 'disabled' : ''; ?>><?php echo $n; ?></button>
              <?php if ($n === $aisle_after && $per_row > 1): ?><span class="cx-aisle"></span><?php endif; ?>
            <?php endfor; ?>
            <span class="cx-row-label"><?php echo $row; ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="cx-legend">
      <span><i style="border-color:var(--cx-economy)"></i>Economy <?php echo money($prices['Economy']); ?></span>
      <span><i style="border-color:var(--cx-business)"></i>Business <?php echo money($prices['Business']); ?></span>
      <span><i style="border-color:var(--cx-first)"></i>First <?php echo money($prices['First']); ?></span>
      <span><i style="background:var(--cx-selected);border-color:var(--cx-selected)"></i>Selected</span>
      <span><i style="background:var(--cx-held);border-color:var(--cx-held)"></i>On hold</span>
      <span><i style="background:var(--cx-booked);border-color:var(--cx-booked)"></i>Booked</span>
    </div>
    <p class="cx-muted text-center" style="font-size:12px;margin:0;">Max <?php echo MAX_SEATS; ?> seats per booking. Seats are held for <?php echo HOLD_MINUTES; ?> minutes once you continue.</p>
  </div>

  <div class="cx-summary">
    <div>
      <div class="cx-muted" style="font-size:13px;">Selected seats</div>
      <div id="seat-list" style="font-weight:600;">None</div>
    </div>
    <div style="display:flex;align-items:center;gap:18px;">
      <div class="cx-total" id="seat-total"><?php echo money(0); ?></div>
      <button type="button" class="cx-btn" id="seat-continue" disabled>Continue <i class="fa fa-arrow-right"></i></button>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($prices): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var showId = <?php echo (int) $show['show_id']; ?>;
  var maxSeats = <?php echo MAX_SEATS; ?>;
  var prices = <?php echo json_encode($prices); ?>;
  var grid = document.getElementById('seat-grid');
  var listEl = document.getElementById('seat-list');
  var totalEl = document.getElementById('seat-total');
  var btn = document.getElementById('seat-continue');
  var msg = document.getElementById('seat-msg');

  function seats() { return Array.prototype.slice.call(grid.querySelectorAll('.cx-seat')); }
  function selected() { return seats().filter(function (s) { return s.classList.contains('is-selected'); }); }
  function money(n) { return 'PKR ' + n.toLocaleString('en-US'); }
  function notify(text, type) {
    msg.innerHTML = text ? '<div class="cx-alert cx-alert-' + (type || 'error') + '"></div>' : '';
    if (text) { msg.firstChild.textContent = text; msg.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }
  function refresh() {
    var chosen = selected();
    var total = chosen.reduce(function (sum, s) { return sum + prices[s.dataset.class]; }, 0);
    listEl.textContent = chosen.length ? chosen.map(function (s) { return s.dataset.label; }).join(', ') : 'None';
    totalEl.textContent = money(total);
    btn.disabled = chosen.length === 0;
  }

  grid.addEventListener('click', function (e) {
    var seat = e.target.closest('.cx-seat');
    if (!seat || seat.disabled) return;
    if (!seat.classList.contains('is-selected') && selected().length >= maxSeats) {
      notify('You can select up to ' + maxSeats + ' seats per booking.');
      return;
    }
    notify('');
    seat.classList.toggle('is-selected');
    refresh();
  });

  function applyStates(data) {
    var lost = [];
    seats().forEach(function (s) {
      var label = s.dataset.label;
      var taken = data.booked.indexOf(label) !== -1 ? 'is-booked' : (data.held.indexOf(label) !== -1 ? 'is-held' : '');
      if (taken && s.classList.contains('is-selected')) lost.push(label);
      s.classList.remove('is-booked', 'is-held');
      if (taken) { s.classList.remove('is-selected'); s.classList.add(taken); }
      s.disabled = !!taken;
    });
    if (lost.length) notify('Seat ' + lost.join(', ') + ' was just taken by someone else. Please choose another seat.');
    refresh();
  }

  btn.addEventListener('click', function () {
    var body = new FormData();
    body.append('action', 'hold');
    body.append('show_id', showId);
    body.append('seats', selected().map(function (s) { return s.dataset.label; }).join(','));
    btn.disabled = true;
    btn.textContent = 'Holding seats...';
    fetch('ajax/seat_hold.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) { window.location.href = data.redirect; return; }
        if (data.conflicts) {
          data.conflicts.forEach(function (label) {
            var s = grid.querySelector('[data-label="' + label + '"]');
            if (s) { s.classList.remove('is-selected'); s.classList.add('is-held'); s.disabled = true; }
          });
        }
        notify(data.message || 'Could not hold your seats. Please try again.');
        btn.innerHTML = 'Continue <i class="fa fa-arrow-right"></i>';
        refresh();
      })
      .catch(function () {
        notify('Network error. Please check your connection and try again.');
        btn.innerHTML = 'Continue <i class="fa fa-arrow-right"></i>';
        refresh();
      });
  });

  setInterval(function () {
    fetch('ajax/seat_status.php?show_id=' + showId, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) { if (data.ok) applyStates(data); })
      .catch(function () {});
  }, 10000);

  refresh();
});
</script>
<?php endif; ?>
<?php include('footer.php'); ?>
