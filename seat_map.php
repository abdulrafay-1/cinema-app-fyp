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
$capacity = count($rows) * $per_row;
$free = $capacity - count($states['booked']) - count($states['held']);
$class_rgb = ['Economy' => '63, 169, 245', 'Business' => '166, 108, 255', 'First' => '224, 168, 0'];

include('header.php');
echo booking_css();
?>
<style>
  .sm-hero { position: relative; border-radius: 18px; overflow: hidden; margin-bottom: 24px; background: #1d1d22; box-shadow: 0 10px 40px rgba(0, 0, 0, .4); }
  .sm-hero-bg { position: absolute; inset: 0; background-size: cover; background-position: center; filter: blur(2px) brightness(.45); transform: scale(1.05); }
  .sm-hero-bg::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(29, 29, 34, .96) 0%, rgba(29, 29, 34, .7) 55%, rgba(29, 29, 34, .35) 100%); }
  .sm-hero-in { position: relative; display: flex; gap: 22px; align-items: center; padding: 24px; flex-wrap: wrap; }
  .sm-hero img.sm-poster { width: 92px; border-radius: 10px; box-shadow: 0 8px 22px rgba(0, 0, 0, .5); }
  .sm-hero h1 { font-size: clamp(22px, 3vw, 32px); margin: 0 0 10px; text-transform: uppercase; color: #fff; font-family: 'Ubuntu', sans-serif; }
  .sm-chips { display: flex; flex-wrap: wrap; gap: 8px; }
  .sm-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .12); color: rgba(255, 255, 255, .9); font-size: 13px; backdrop-filter: blur(6px); }
  .sm-chip i { color: #ff55a5; }
  .sm-hero-side { margin-left: auto; display: flex; flex-direction: column; align-items: flex-end; gap: 12px; }
  .sm-steps { display: flex; align-items: center; gap: 8px; font-size: 12px; color: rgba(255, 255, 255, .5); }
  .sm-steps b { display: inline-flex; width: 22px; height: 22px; border-radius: 50%; align-items: center; justify-content: center; background: rgba(255, 255, 255, .1); color: rgba(255, 255, 255, .6); font-size: 11px; margin-right: 4px; }
  .sm-steps .is-on { color: #fff; font-weight: 600; }
  .sm-steps .is-on b { background: linear-gradient(90deg, #ff55a5, #ff5860); color: #fff; }
  .sm-steps .sm-line { width: 22px; height: 1px; background: rgba(255, 255, 255, .2); }

  .sm-map { background: var(--cx-card); border-radius: 18px; padding: 30px 20px 22px; border: 1px solid var(--cx-line); box-shadow: var(--cx-shadow); }
  .sm-screen { position: relative; max-width: 520px; margin: 0 auto 34px; text-align: center; }
  .sm-screen-arc { height: 26px; border-radius: 50% 50% 0 0 / 100% 100% 0 0; border-top: 5px solid #ff55a5; box-shadow: 0 -18px 30px -14px rgba(255, 85, 165, .9); }
  .sm-screen-glow { position: absolute; left: 8%; right: 8%; top: 6px; height: 60px; background: radial-gradient(ellipse at top, rgba(255, 85, 165, .22), transparent 70%); pointer-events: none; }
  .sm-screen small { display: block; margin-top: 2px; letter-spacing: 8px; font-size: 11px; color: var(--cx-muted); }
  .sm-grid { display: flex; flex-direction: column; align-items: center; gap: 7px; min-width: max-content; margin: 0 auto; }
  .sm-scroll { overflow-x: auto; padding-bottom: 6px; }
  .sm-divider { display: flex; align-items: center; gap: 12px; width: 100%; margin: 12px 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; font-weight: 700; }
  .sm-divider::before, .sm-divider::after { content: ''; flex: 1; height: 1px; background: currentColor; opacity: .25; }
  .sm-divider:first-child { margin-top: 0; }
  .sm-row { display: flex; align-items: center; gap: 7px; }
  .sm-rowlabel { width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: var(--cx-muted); background: #eef0f5; }
  .sm-aisle { width: 22px; }
  .sm-page .cx-seat { --rgb: 63, 169, 245; width: 32px; height: 30px; border-radius: 9px 9px 5px 5px; border: 1.5px solid rgba(var(--rgb), .55); background: rgba(var(--rgb), .10); color: var(--cx-text-soft); font-size: 10px; font-weight: 600; box-shadow: inset 0 -4px 0 rgba(31, 34, 48, .08); transition: transform .12s, background .15s, box-shadow .15s, border-color .15s; }
  .sm-page .cx-seat.c-Business { --rgb: 166, 108, 255; }
  .sm-page .cx-seat.c-First { --rgb: 224, 168, 0; }
  .sm-page .cx-seat:hover:not([disabled]):not(.is-selected) { transform: translateY(-3px); border-color: rgb(var(--rgb)); background: rgba(var(--rgb), .22); box-shadow: inset 0 -4px 0 rgba(31, 34, 48, .08), 0 6px 14px rgba(var(--rgb), .3); color: var(--cx-text); }
  .sm-page .cx-seat:focus-visible { outline: 2px solid var(--cx-pink); outline-offset: 2px; }
  .sm-page .cx-seat.is-selected { background: linear-gradient(180deg, #3ee08a, #22b866); border-color: #3ee08a; color: #06311a; box-shadow: inset 0 -4px 0 rgba(0, 0, 0, .12), 0 4px 12px rgba(46, 204, 113, .45); transform: translateY(-2px); }
  .sm-page .cx-seat.is-held { background: rgba(255, 159, 28, .85); border-color: #ff9f1c; color: #3a2400; box-shadow: none; }
  .sm-page .cx-seat.is-booked { background: #e3e5ec; border-color: #e3e5ec; color: transparent; box-shadow: none; position: relative; }
  .sm-page .cx-seat.is-booked::after { content: '\00d7'; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #a3a8b8; font-size: 15px; }
  .sm-legend { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 26px; }
  .sm-legend span { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; background: var(--cx-bg); border: 1px solid var(--cx-line); font-size: 12px; color: var(--cx-text-soft); }
  .sm-legend i { width: 14px; height: 13px; border-radius: 4px 4px 2px 2px; border: 1.5px solid; display: inline-block; }

  .sm-summary { background: var(--cx-card); border-radius: 18px; padding: 22px; border: 1px solid var(--cx-line); box-shadow: var(--cx-shadow); position: sticky; top: 100px; }
  .sm-summary h3 { font-size: 18px; margin: 0 0 4px; color: var(--cx-text); }
  .sm-avail { font-size: 12px; color: var(--cx-muted); margin-bottom: 16px; }
  .sm-avail b { color: #1b7a46; }
  .sm-seatchips { display: flex; flex-wrap: wrap; gap: 6px; min-height: 34px; margin-bottom: 16px; }
  .sm-seatchip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 6px 5px 10px; border-radius: 8px; background: #ecfbf2; border: 1px solid rgba(46, 204, 113, .45); color: #1b7a46; font-size: 13px; font-weight: 600; cursor: pointer; }
  .sm-seatchip span { width: 18px; height: 18px; border-radius: 5px; display: inline-flex; align-items: center; justify-content: center; background: rgba(27, 122, 70, .12); font-size: 12px; }
  .sm-seatchip:hover span { background: #ff5860; color: #fff; }
  .sm-empty { color: var(--cx-muted); font-size: 13px; }
  .sm-lines { border-top: 1px dashed var(--cx-line); padding-top: 12px; margin-bottom: 12px; }
  .sm-line { display: flex; justify-content: space-between; font-size: 14px; color: var(--cx-text-soft); padding: 3px 0; }
  .sm-total { display: flex; justify-content: space-between; align-items: baseline; border-top: 1px solid var(--cx-line); padding-top: 12px; margin-bottom: 16px; }
  .sm-total span { color: var(--cx-muted); }
  .sm-total strong { font-size: 26px; color: var(--cx-text); }
  .sm-summary .cx-btn { width: 100%; padding: 13px; font-size: 15px; }
  .sm-hint { font-size: 12px; color: var(--cx-muted); margin: 12px 0 0; display: flex; gap: 6px; }
  .sm-count { display: none; }

  @media (max-width: 991px) {
    .sm-page { padding-bottom: 110px; }
    .sm-summary { position: fixed; top: auto; left: 0; right: 0; bottom: 0; z-index: 1030; border-radius: 16px 16px 0 0; box-shadow: 0 -8px 30px rgba(31, 34, 48, .12); padding: 14px 16px; display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 4px 14px; background: rgba(255, 255, 255, .97); backdrop-filter: blur(8px); }
    .sm-summary h3, .sm-avail, .sm-lines, .sm-hint { display: none; }
    .sm-seatchips { grid-column: 1 / -1; min-height: 0; margin: 0; flex-wrap: nowrap; overflow-x: auto; }
    .sm-seatchips:empty { display: none; }
    .sm-total { border: 0; padding: 0; margin: 0; flex-direction: column; align-items: flex-start; }
    .sm-total strong { font-size: 20px; }
    .sm-summary .cx-btn { width: auto; padding: 11px 20px; }
    .sm-count { display: inline; }
    .sm-hero-side { margin-left: 0; align-items: flex-start; width: 100%; }
  }
  @media (max-width: 575px) {
    .sm-hero-in { padding: 16px; gap: 14px; }
    .sm-hero img.sm-poster { width: 64px; }
    .sm-map { padding: 22px 8px 16px; }
    .sm-page .cx-seat { width: 20px; height: 20px; font-size: 8px; border-radius: 6px 6px 3px 3px; border-width: 1px; }
    .sm-row { gap: 2px; }
    .sm-grid { gap: 5px; }
    .sm-rowlabel { width: 16px; height: 16px; font-size: 9px; }
    .sm-aisle { width: 8px; }
    .sm-steps { display: none; }
  }
</style>

<div class="container cx-page sm-page">
  <div class="sm-hero">
    <?php if (!empty($show['backdrop'])): ?>
      <div class="sm-hero-bg" style="background-image:url('Admin/upload-images/<?php echo e(rawurlencode($show['backdrop'])); ?>')"></div>
    <?php endif; ?>
    <div class="sm-hero-in">
      <img class="sm-poster" src="Admin/upload-images/<?php echo e($show['movie_img']); ?>" alt="">
      <div>
        <h1><?php echo e($show['movie_name']); ?></h1>
        <div class="sm-chips">
          <span class="sm-chip"><i class="fa fa-video"></i> <?php echo e($show['cinema_name']); ?></span>
          <span class="sm-chip"><i class="fa fa-door-open"></i> <?php echo e($show['hall_name']); ?></span>
          <span class="sm-chip"><i class="fa fa-calendar-alt"></i> <?php echo e(show_date_label($show['show_date'])); ?></span>
          <span class="sm-chip"><i class="fa fa-clock"></i> <?php echo e(show_time_label($show['show_time'])); ?></span>
        </div>
      </div>
      <div class="sm-hero-side">
        <div class="sm-steps" aria-label="Booking steps">
          <span class="is-on"><b>1</b>Seats</span><span class="sm-line"></span>
          <span><b>2</b>Checkout</span><span class="sm-line"></span>
          <span><b>3</b>Payment</span>
        </div>
        <a class="cx-btn cx-btn-sm cx-btn-ghost" href="select_show.php?movie_id=<?php echo (int) $show['movie_id']; ?>"><i class="fa fa-arrow-left"></i> Change show</a>
      </div>
    </div>
  </div>

  <?php echo render_flash(); ?>

  <?php if (!$prices): ?>
    <div class="cx-alert cx-alert-error">Ticket prices are not set for this movie yet, so booking is closed. Please try another movie.</div>
  <?php else: ?>
  <div id="seat-msg"></div>

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="sm-map">
        <div class="sm-screen">
          <div class="sm-screen-glow"></div>
          <div class="sm-screen-arc"></div>
          <small>SCREEN</small>
        </div>
        <div class="sm-scroll">
          <div class="sm-grid" id="seat-grid">
            <?php $previous_class = null; foreach ($rows as $row): $class = seat_class($row); ?>
              <?php if ($class !== $previous_class): $previous_class = $class; ?>
                <div class="sm-divider" style="color:rgb(<?php echo $class_rgb[$class]; ?>);"><?php echo $class; ?> &middot; <?php echo money($prices[$class]); ?></div>
              <?php endif; ?>
              <div class="sm-row">
                <span class="sm-rowlabel"><?php echo $row; ?></span>
                <?php for ($n = 1; $n <= $per_row; $n++): $label = $row . $n;
                    $state = in_array($label, $states['booked'], true) ? 'is-booked' : (in_array($label, $states['held'], true) ? 'is-held' : (in_array($label, $states['mine'], true) ? 'is-selected' : ''));
                    $taken = $state === 'is-booked' || $state === 'is-held'; ?>
                  <button type="button" class="cx-seat c-<?php echo $class; ?> <?php echo $state; ?>" data-label="<?php echo $label; ?>" data-class="<?php echo $class; ?>"
                          title="<?php echo $label . ' · ' . $class . ' · ' . money($prices[$class]) . ($state === 'is-booked' ? ' · Booked' : ($state === 'is-held' ? ' · On hold' : '')); ?>"
                          aria-label="Seat <?php echo $label; ?>, <?php echo $class; ?>, <?php echo money($prices[$class]); ?><?php echo $taken ? ', unavailable' : ''; ?>"
                          aria-pressed="<?php echo $state === 'is-selected' ? 'true' : 'false'; ?>" <?php echo $taken ? 'disabled' : ''; ?>><?php echo $n; ?></button>
                  <?php if ($n === $aisle_after && $per_row > 1): ?><span class="sm-aisle"></span><?php endif; ?>
                <?php endfor; ?>
                <span class="sm-rowlabel"><?php echo $row; ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="sm-legend">
          <span><i style="border-color:rgb(63,169,245);background:rgba(63,169,245,.15)"></i>Economy</span>
          <span><i style="border-color:rgb(166,108,255);background:rgba(166,108,255,.15)"></i>Business</span>
          <span><i style="border-color:rgb(224,168,0);background:rgba(224,168,0,.12)"></i>First</span>
          <span><i style="background:#2ecc71;border-color:#2ecc71"></i>Selected</span>
          <span><i style="background:#ff9f1c;border-color:#ff9f1c"></i>On hold</span>
          <span><i style="background:#e3e5ec;border-color:#e3e5ec"></i>Booked</span>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <aside class="sm-summary" aria-live="polite">
        <h3>Your seats</h3>
        <div class="sm-avail"><b><?php echo max(0, $free); ?></b> of <?php echo $capacity; ?> seats available</div>
        <div class="sm-seatchips" id="seat-list"></div>
        <div class="sm-lines" id="seat-breakdown"></div>
        <div class="sm-total">
          <span>Total <span class="sm-count" id="seat-count"></span></span>
          <strong id="seat-total"><?php echo money(0); ?></strong>
        </div>
        <button type="button" class="cx-btn" id="seat-continue" disabled>Continue <i class="fa fa-arrow-right"></i></button>
        <p class="sm-hint"><i class="fa fa-info-circle"></i> Up to <?php echo MAX_SEATS; ?> seats per booking. Seats are held for <?php echo HOLD_MINUTES; ?> minutes once you continue.</p>
      </aside>
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
  var classes = <?php echo json_encode(SEAT_CLASSES); ?>;
  var grid = document.getElementById('seat-grid');
  var listEl = document.getElementById('seat-list');
  var breakdownEl = document.getElementById('seat-breakdown');
  var totalEl = document.getElementById('seat-total');
  var countEl = document.getElementById('seat-count');
  var btn = document.getElementById('seat-continue');
  var msg = document.getElementById('seat-msg');
  var btnHtml = btn.innerHTML;

  function seats() { return Array.prototype.slice.call(grid.querySelectorAll('.cx-seat')); }
  function selected() { return seats().filter(function (s) { return s.classList.contains('is-selected'); }); }
  function money(n) { return 'PKR ' + n.toLocaleString('en-US'); }
  function notify(text, type) {
    msg.innerHTML = text ? '<div class="cx-alert cx-alert-' + (type || 'error') + '"></div>' : '';
    if (text) { msg.firstChild.textContent = text; msg.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }
  function setSelected(seat, on) {
    seat.classList.toggle('is-selected', on);
    seat.setAttribute('aria-pressed', on ? 'true' : 'false');
  }
  function refresh() {
    var chosen = selected();
    var total = 0;
    var counts = {};
    listEl.innerHTML = '';
    chosen.forEach(function (s) {
      total += prices[s.dataset.class];
      counts[s.dataset.class] = (counts[s.dataset.class] || 0) + 1;
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'sm-seatchip';
      chip.title = 'Remove seat ' + s.dataset.label;
      chip.appendChild(document.createTextNode(s.dataset.label));
      var x = document.createElement('span');
      x.textContent = '×';
      chip.appendChild(x);
      chip.addEventListener('click', function () { setSelected(s, false); notify(''); refresh(); });
      listEl.appendChild(chip);
    });
    if (!chosen.length) {
      var empty = document.createElement('div');
      empty.className = 'sm-empty';
      empty.textContent = 'Tap seats on the map to select them.';
      listEl.appendChild(empty);
    }
    breakdownEl.innerHTML = '';
    classes.forEach(function (c) {
      if (!counts[c]) return;
      var line = document.createElement('div');
      line.className = 'sm-line';
      var a = document.createElement('span'); a.textContent = c + ' × ' + counts[c];
      var b = document.createElement('span'); b.textContent = money(prices[c] * counts[c]);
      line.appendChild(a); line.appendChild(b);
      breakdownEl.appendChild(line);
    });
    breakdownEl.style.display = chosen.length ? '' : 'none';
    totalEl.textContent = money(total);
    countEl.textContent = chosen.length ? '(' + chosen.length + ' seat' + (chosen.length > 1 ? 's' : '') + ')' : '';
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
    setSelected(seat, !seat.classList.contains('is-selected'));
    refresh();
  });

  function applyStates(data) {
    var lost = [];
    seats().forEach(function (s) {
      var label = s.dataset.label;
      var taken = data.booked.indexOf(label) !== -1 ? 'is-booked' : (data.held.indexOf(label) !== -1 ? 'is-held' : '');
      if (taken && s.classList.contains('is-selected')) lost.push(label);
      s.classList.remove('is-booked', 'is-held');
      if (taken) { setSelected(s, false); s.classList.add(taken); }
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
            if (s) { setSelected(s, false); s.classList.add('is-held'); s.disabled = true; }
          });
        }
        notify(data.message || 'Could not hold your seats. Please try again.');
        btn.innerHTML = btnHtml;
        refresh();
      })
      .catch(function () {
        notify('Network error. Please check your connection and try again.');
        btn.innerHTML = btnHtml;
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
