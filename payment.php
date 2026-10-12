<?php
require_once __DIR__ . '/includes/booking.php';

require_login();
$user_id = current_user_id();
$show_id = (int) ($_POST['show_id'] ?? ($_GET['show_id'] ?? 0));
$checkout = load_checkout($user_id, $show_id);
if (!$checkout) {
    flash('danger', 'Your seat hold has expired. Please select your seats again.');
    redirect(bookable_show($show_id) ? 'seat_map.php?show_id=' . $show_id : 'index.php');
}

function validate_payment($post)
{
    $method = $post['method'] ?? '';
    if ($method === 'Card') {
        $number = preg_replace('/\D/', '', $post['card_number'] ?? '');
        $name = trim($post['card_name'] ?? '');
        $expiry = trim($post['card_expiry'] ?? '');
        $cvv = trim($post['card_cvv'] ?? '');
        if (strlen($number) !== 16) {
            return [null, 'Card number must be 16 digits.'];
        }
        if ($name === '') {
            return [null, 'Please enter the name on the card.'];
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry, $m) || (int) ('20' . $m[2] . $m[1]) < (int) date('Ym')) {
            return [null, 'Card expiry must be a valid future date (MM/YY).'];
        }
        if (!preg_match('/^[0-9]{3,4}$/', $cvv)) {
            return [null, 'CVV must be 3 or 4 digits.'];
        }
        return [substr($number, -4), null];
    }
    if ($method === 'JazzCash' || $method === 'Easypaisa') {
        $mobile = preg_replace('/\D/', '', $post['wallet_mobile'] ?? '');
        $otp = trim($post['wallet_otp'] ?? '');
        $sent = $_SESSION['payment_otp'] ?? null;
        if (!preg_match('/^03[0-9]{9}$/', $mobile)) {
            return [null, 'Enter a valid mobile number like 03001234567.'];
        }
        if (!$sent || $sent['mobile'] !== $mobile || $sent['expires'] < time()) {
            return [null, 'Please request an OTP for this mobile number first.'];
        }
        if (!hash_equals($sent['code'], $otp)) {
            return [null, 'The OTP you entered is incorrect.'];
        }
        return [substr($mobile, -4), null];
    }
    return [null, 'Please choose a payment method.'];
}

$error = null;
$method = $_POST['method'] ?? 'Card';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    list($last4, $error) = validate_payment($_POST);
    if (!$error) {
        $state = $checkout['state'];
        $result = finalize_booking(current_user_row(), $checkout['show'], $state['snacks'], $state['promo'], $method, $last4);
        if ($result['ok']) {
            unset($_SESSION['checkout'], $_SESSION['payment_otp']);
            redirect('booking_success.php?ref=' . urlencode($result['booking_ref']));
        }
        $error = $result['message'];
        if (!load_checkout($user_id, $show_id)) {
            flash('danger', $error);
            redirect('seat_map.php?show_id=' . $show_id);
        }
    }
}

$show = $checkout['show'];
$t = $checkout['totals'];

include('header.php');
echo booking_css();
?>
<style>
  .pay-tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
  .pay-tab { flex: 1 1 120px; padding: 12px; border-radius: 8px; border: 1px solid var(--cx-line); background: transparent; color: var(--cx-muted); font-weight: 600; cursor: pointer; }
  .pay-tab.is-active { border-color: var(--cx-pink); color: var(--cx-text); background: #fff0f7; }
  .pay-pane { display: none; }
  .pay-pane.is-active { display: block; }
  .pay-field { margin-bottom: 14px; }
  .pay-field label { display: block; font-size: 13px; color: var(--cx-muted); margin-bottom: 4px; }
  .pay-overlay { position: fixed; inset: 0; background: rgba(245, 246, 250, .95); z-index: 2000; display: none; align-items: center; justify-content: center; flex-direction: column; color: var(--cx-text); gap: 18px; }
  .pay-overlay.is-active { display: flex; }
  .pay-spinner { width: 64px; height: 64px; border-radius: 50%; border: 5px solid rgba(31, 34, 48, .1); border-top-color: #ff55a5; animation: pay-spin .9s linear infinite; }
  @keyframes pay-spin { to { transform: rotate(360deg); } }
</style>

<div class="container cx-page">
  <h2 style="margin-bottom:18px;">Payment</h2>
  <?php echo render_flash(); ?>
  <?php if ($error): ?><div class="cx-alert cx-alert-error"><?php echo e($error); ?></div><?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="cx-card">
        <form method="post" action="payment.php" id="pay-form" novalidate>
          <input type="hidden" name="show_id" value="<?php echo (int) $show['show_id']; ?>">
          <input type="hidden" name="method" id="pay-method" value="<?php echo e($method); ?>">
          <div class="pay-tabs" role="tablist">
            <button type="button" class="pay-tab" data-method="Card"><i class="fa fa-credit-card"></i> Card</button>
            <button type="button" class="pay-tab" data-method="JazzCash"><i class="fa fa-mobile-alt"></i> JazzCash</button>
            <button type="button" class="pay-tab" data-method="Easypaisa"><i class="fa fa-mobile-alt"></i> Easypaisa</button>
          </div>

          <div class="pay-pane" data-pane="Card">
            <div class="pay-field">
              <label for="card-number">Card number</label>
              <input class="cx-input" id="card-number" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242" maxlength="19">
            </div>
            <div class="pay-field">
              <label for="card-name">Name on card</label>
              <input class="cx-input" id="card-name" name="card_name" autocomplete="cc-name" placeholder="Full name" value="<?php echo e($_SESSION['ux'] ?? ''); ?>">
            </div>
            <div class="row g-3">
              <div class="col-6 pay-field">
                <label for="card-expiry">Expiry (MM/YY)</label>
                <input class="cx-input" id="card-expiry" name="card_expiry" inputmode="numeric" autocomplete="cc-exp" placeholder="12/28" maxlength="5">
              </div>
              <div class="col-6 pay-field">
                <label for="card-cvv">CVV</label>
                <input class="cx-input" id="card-cvv" name="card_cvv" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4" type="password">
              </div>
            </div>
            <p class="cx-muted" style="font-size:12px;"><i class="fa fa-lock"></i> Demo payment. Only the last 4 digits are stored.</p>
          </div>

          <div class="pay-pane" data-pane="Wallet">
            <div class="pay-field">
              <label for="wallet-mobile"><span id="wallet-name">JazzCash</span> mobile number</label>
              <div style="display:flex;gap:8px;">
                <input class="cx-input" id="wallet-mobile" name="wallet_mobile" inputmode="numeric" placeholder="03001234567" maxlength="11">
                <button type="button" class="cx-btn cx-btn-sm" id="otp-send" style="white-space:nowrap;">Send OTP</button>
              </div>
            </div>
            <div id="otp-msg" style="font-size:13px;margin-bottom:10px;"></div>
            <div class="pay-field">
              <label for="wallet-otp">6-digit OTP</label>
              <input class="cx-input" id="wallet-otp" name="wallet_otp" inputmode="numeric" placeholder="______" maxlength="6" autocomplete="one-time-code">
            </div>
          </div>

          <div id="pay-error"></div>
          <button type="submit" class="cx-btn" style="width:100%;margin-top:6px;" id="pay-submit">Pay <?php echo money($t['total']); ?></button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="cx-card">
        <h4 style="margin-bottom:4px;"><?php echo e($show['movie_name']); ?></h4>
        <div class="cx-muted" style="margin-bottom:12px;">
          <?php echo e($show['cinema_name']); ?> &middot; <?php echo e($show['hall_name']); ?><br>
          <?php echo e(show_date_label($show['show_date'])); ?> &middot; <?php echo e(show_time_label($show['show_time'])); ?>
        </div>
        <table class="cx-table">
          <?php foreach ($t['seats'] as $seat): ?>
            <tr><td>Seat <?php echo e($seat['label']); ?> <span class="cx-muted">(<?php echo e($seat['class']); ?>)</span></td><td class="text-end"><?php echo money($seat['price']); ?></td></tr>
          <?php endforeach; ?>
          <?php foreach ($t['snacks'] as $snack): ?>
            <tr><td><?php echo e($snack['name']); ?> &times; <?php echo (int) $snack['qty']; ?></td><td class="text-end"><?php echo money($snack['line']); ?></td></tr>
          <?php endforeach; ?>
          <?php if ($t['discount']): ?>
            <tr><td>Discount (<?php echo e($t['promo_code']); ?>, <?php echo (int) $t['promo_percent']; ?>%)</td><td class="text-end" style="color:#1b7a46;">- <?php echo money($t['discount']); ?></td></tr>
          <?php endif; ?>
          <tr><td style="font-size:18px;font-weight:700;">Total</td><td class="text-end" style="font-size:22px;font-weight:700;"><?php echo money($t['total']); ?></td></tr>
        </table>
        <a href="checkout.php?show_id=<?php echo (int) $show['show_id']; ?>" class="cx-muted" style="font-size:13px;display:inline-block;margin-top:10px;"><i class="fa fa-arrow-left"></i> Back to checkout</a>
      </div>
    </div>
  </div>
</div>

<div class="pay-overlay" id="pay-overlay" aria-live="assertive">
  <div class="pay-spinner"></div>
  <h3 style="color:var(--cx-text);margin:0;">Processing payment...</h3>
  <p class="cx-muted" style="margin:0;">Please don't close or refresh this page.</p>
</div>

<?php $hold_timer_seconds = $checkout['seconds_left']; $hold_timer_show_id = $show['show_id']; include('partials/hold_timer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('pay-form');
  var methodInput = document.getElementById('pay-method');
  var errorBox = document.getElementById('pay-error');
  var cardNumber = document.getElementById('card-number');
  var cardExpiry = document.getElementById('card-expiry');
  var mobile = document.getElementById('wallet-mobile');
  var otpMsg = document.getElementById('otp-msg');

  function select(method) {
    methodInput.value = method;
    document.querySelectorAll('.pay-tab').forEach(function (t) { t.classList.toggle('is-active', t.dataset.method === method); });
    document.querySelectorAll('.pay-pane').forEach(function (p) { p.classList.toggle('is-active', p.dataset.pane === (method === 'Card' ? 'Card' : 'Wallet')); });
    document.getElementById('wallet-name').textContent = method;
    errorBox.innerHTML = '';
  }
  document.querySelectorAll('.pay-tab').forEach(function (t) { t.addEventListener('click', function () { select(t.dataset.method); }); });
  select(methodInput.value || 'Card');

  cardNumber.addEventListener('input', function () {
    var digits = cardNumber.value.replace(/\D/g, '').slice(0, 16);
    cardNumber.value = digits.replace(/(.{4})(?=.)/g, '$1 ');
  });
  cardExpiry.addEventListener('input', function () {
    var d = cardExpiry.value.replace(/\D/g, '').slice(0, 4);
    cardExpiry.value = d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
  });
  document.querySelectorAll('#card-cvv, #wallet-mobile, #wallet-otp').forEach(function (el) {
    el.addEventListener('input', function () { el.value = el.value.replace(/\D/g, ''); });
  });

  function showError(text) {
    errorBox.innerHTML = '<div class="cx-alert cx-alert-error"></div>';
    errorBox.firstChild.textContent = text;
  }

  document.getElementById('otp-send').addEventListener('click', function () {
    var body = new FormData();
    body.append('mobile', mobile.value);
    fetch('ajax/payment_otp.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        otpMsg.textContent = data.message;
        otpMsg.style.color = data.ok ? '#1b7a46' : '#b4232c';
      })
      .catch(function () { otpMsg.textContent = 'Could not send OTP. Please try again.'; });
  });

  function clientCheck() {
    if (methodInput.value === 'Card') {
      if (cardNumber.value.replace(/\D/g, '').length !== 16) return 'Card number must be 16 digits.';
      if (!document.getElementById('card-name').value.trim()) return 'Please enter the name on the card.';
      if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(cardExpiry.value)) return 'Card expiry must be MM/YY.';
      if (!/^\d{3,4}$/.test(document.getElementById('card-cvv').value)) return 'CVV must be 3 or 4 digits.';
    } else {
      if (!/^03\d{9}$/.test(mobile.value)) return 'Enter a valid mobile number like 03001234567.';
      if (!/^\d{6}$/.test(document.getElementById('wallet-otp').value)) return 'Enter the 6-digit OTP.';
    }
    return '';
  }

  var submitting = false;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (submitting) return;
    var problem = clientCheck();
    if (problem) { showError(problem); return; }
    submitting = true;
    document.getElementById('pay-submit').disabled = true;
    document.getElementById('pay-overlay').classList.add('is-active');
    setTimeout(function () { form.submit(); }, 2500);
  });
});
</script>
<?php include('footer.php'); ?>
