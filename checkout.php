<?php
require_once __DIR__ . '/includes/booking.php';

require_login();
$user_id = current_user_id();
$show_id = (int) ($_POST['show_id'] ?? ($_GET['show_id'] ?? 0));
$checkout = load_checkout($user_id, $show_id);
if (!$checkout) {
    flash('danger', 'Your seat hold has expired or no seats are selected. Please select your seats again.');
    redirect(bookable_show($show_id) ? 'seat_map.php?show_id=' . $show_id : 'index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $promo_code = strtoupper(trim($_POST['promo_code'] ?? ''));
    $promo_error = null;
    if ($promo_code !== '') {
        list($promo, $promo_error) = find_promo($promo_code);
    }
    $_SESSION['checkout']['snacks'] = clean_snack_qty($_POST['qty'] ?? []);
    $_SESSION['checkout']['promo'] = $promo_error ? null : ($promo_code ?: null);
    if ($promo_error) {
        $error = $promo_error;
        $checkout = load_checkout($user_id, $show_id);
    } else {
        redirect('payment.php?show_id=' . $show_id);
    }
}

$show = $checkout['show'];
$t = $checkout['totals'];
$state = $checkout['state'];
$snacks = active_snacks();

include('header.php');
echo booking_css();
?>
<div class="container cx-page">
  <h2 style="margin-bottom:18px;">Checkout</h2>
  <?php echo render_flash(); ?>
  <?php if ($error): ?><div class="cx-alert cx-alert-error"><?php echo e($error); ?></div><?php endif; ?>

  <form method="post" action="checkout.php" id="checkout-form">
    <input type="hidden" name="show_id" value="<?php echo (int) $show['show_id']; ?>">
    <div class="row g-4">
      <div class="col-12 col-lg-7">
        <div class="cx-card">
          <div class="row g-3 align-items-center">
            <div class="col-3"><img class="cx-poster" src="Admin/upload-images/<?php echo e($show['movie_img']); ?>" alt=""></div>
            <div class="col-9">
              <h4 style="margin-bottom:4px;"><?php echo e($show['movie_name']); ?></h4>
              <div class="cx-muted">
                <?php echo e($show['cinema_name']); ?> &middot; <?php echo e($show['hall_name']); ?><br>
                <?php echo e(show_date_label($show['show_date'])); ?> &middot; <?php echo e(show_time_label($show['show_time'])); ?>
              </div>
              <div style="margin-top:10px;">
                <?php foreach ($t['seats'] as $seat): ?>
                  <span class="cx-badge" title="<?php echo e($seat['class']); ?>"><?php echo e($seat['label']); ?> &middot; <?php echo e($seat['class']); ?></span>
                <?php endforeach; ?>
              </div>
              <a href="seat_map.php?show_id=<?php echo (int) $show['show_id']; ?>" class="cx-muted" style="font-size:13px;display:inline-block;margin-top:8px;"><i class="fa fa-pencil"></i> Change seats</a>
            </div>
          </div>
        </div>

        <div class="cx-card">
          <h4 style="margin-bottom:4px;">Add snacks</h4>
          <p class="cx-muted" style="font-size:13px;">Collect them at the counter with your ticket.</p>
          <?php foreach ($snacks as $snack): $qty = $state['snacks'][(int) $snack['snack_id']] ?? 0; ?>
            <div class="cx-show-row" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid var(--cx-line);">
              <div style="display:flex;align-items:center;gap:12px;">
                <?php if ($snack['image']): ?>
                  <img src="Admin/upload-images/<?php echo e($snack['image']); ?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:6px;">
                <?php else: ?>
                  <span style="font-size:30px;line-height:1;"><?php echo snack_icon($snack['name']); ?></span>
                <?php endif; ?>
                <div>
                  <div style="font-weight:600;"><?php echo e($snack['name']); ?></div>
                  <div class="cx-muted" style="font-size:13px;"><?php echo money($snack['price']); ?></div>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:8px;" class="snack-qty" data-price="<?php echo (int) $snack['price']; ?>">
                <button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-step="-1" aria-label="Remove one">&minus;</button>
                <input type="number" name="qty[<?php echo (int) $snack['snack_id']; ?>]" value="<?php echo (int) $qty; ?>" min="0" max="10" class="cx-input" style="width:56px;text-align:center;padding:6px;" readonly>
                <button type="button" class="cx-btn cx-btn-sm cx-btn-ghost" data-step="1" aria-label="Add one">+</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="cx-card" style="position:sticky;top:90px;">
          <h4>Order summary</h4>
          <label class="cx-muted" for="promo-input" style="font-size:13px;">Promo code</label>
          <div style="display:flex;gap:8px;margin-bottom:6px;">
            <input type="text" id="promo-input" class="cx-input" placeholder="e.g. FYP10" value="<?php echo e($state['promo']); ?>" style="text-transform:uppercase;">
            <button type="button" class="cx-btn cx-btn-sm" id="promo-apply">Apply</button>
          </div>
          <input type="hidden" name="promo_code" id="promo-code" value="<?php echo e($state['promo']); ?>">
          <div id="promo-msg" style="font-size:13px;min-height:20px;margin-bottom:10px;"><?php if ($t['promo_code']): ?><span style="color:#6ee7a0;"><?php echo e($t['promo_code']); ?> applied: <?php echo (int) $t['promo_percent']; ?>% off.</span><?php endif; ?></div>

          <table class="cx-table">
            <tr><td>Tickets (<?php echo count($t['seats']); ?>)</td><td class="text-end" id="sum-tickets" data-amount="<?php echo (int) $t['tickets_amount']; ?>"><?php echo money($t['tickets_amount']); ?></td></tr>
            <tr><td>Snacks</td><td class="text-end" id="sum-snacks"><?php echo money($t['snacks_amount']); ?></td></tr>
            <tr><td>Discount <span id="sum-promo-label"><?php echo $t['promo_code'] ? '(' . e($t['promo_code']) . ')' : ''; ?></span></td><td class="text-end" id="sum-discount" style="color:#6ee7a0;">- <?php echo money($t['discount']); ?></td></tr>
            <tr><td style="font-size:18px;font-weight:700;">Total</td><td class="text-end" style="font-size:22px;font-weight:700;" id="sum-total"><?php echo money($t['total']); ?></td></tr>
          </table>
          <p class="cx-muted" style="font-size:12px;margin:10px 0;">You will earn <strong id="sum-points"><?php echo loyalty_points_for($t['total']); ?></strong> loyalty points.</p>
          <button type="submit" class="cx-btn" style="width:100%;">Proceed to payment <i class="fa fa-lock"></i></button>
        </div>
      </div>
    </div>
  </form>
</div>

<?php $hold_timer_seconds = $checkout['seconds_left']; $hold_timer_show_id = $show['show_id']; include('partials/hold_timer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var percent = <?php echo (int) $t['promo_percent']; ?>;
  var tickets = <?php echo (int) $t['tickets_amount']; ?>;
  var promoInput = document.getElementById('promo-input');
  var promoCode = document.getElementById('promo-code');
  var promoMsg = document.getElementById('promo-msg');
  function money(n) { return 'PKR ' + n.toLocaleString('en-US'); }
  function recalc() {
    var snacks = 0;
    document.querySelectorAll('.snack-qty').forEach(function (box) {
      snacks += parseInt(box.dataset.price, 10) * parseInt(box.querySelector('input').value, 10);
    });
    var subtotal = tickets + snacks;
    var discount = Math.floor(subtotal * percent / 100);
    var total = subtotal - discount;
    document.getElementById('sum-snacks').textContent = money(snacks);
    document.getElementById('sum-discount').textContent = '- ' + money(discount);
    document.getElementById('sum-total').textContent = money(total);
    document.getElementById('sum-points').textContent = Math.floor(total / 100);
    document.getElementById('sum-promo-label').textContent = promoCode.value ? '(' + promoCode.value + ')' : '';
  }
  document.querySelectorAll('.snack-qty').forEach(function (box) {
    var input = box.querySelector('input');
    box.querySelectorAll('[data-step]').forEach(function (b) {
      b.addEventListener('click', function () {
        input.value = Math.max(0, Math.min(10, parseInt(input.value, 10) + parseInt(b.dataset.step, 10)));
        recalc();
      });
    });
  });
  function showPromo(text, ok) {
    promoMsg.innerHTML = '';
    var span = document.createElement('span');
    span.style.color = ok ? '#6ee7a0' : '#ff9da2';
    span.textContent = text;
    promoMsg.appendChild(span);
  }
  document.getElementById('promo-apply').addEventListener('click', function () {
    var code = promoInput.value.trim().toUpperCase();
    if (!code) { promoCode.value = ''; percent = 0; showPromo('', true); recalc(); return; }
    var body = new FormData();
    body.append('code', code);
    fetch('ajax/promo.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) { promoCode.value = data.code; percent = data.percent; }
        else { promoCode.value = ''; percent = 0; }
        showPromo(data.message, data.ok);
        recalc();
      })
      .catch(function () { showPromo('Could not check the code. Please try again.', false); });
  });
  promoInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('promo-apply').click(); }
  });
});
</script>
<?php include('footer.php'); ?>
