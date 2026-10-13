<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/booking.php';

function check_in_ticket($raw)
{
    list($ref, $sig) = parse_ticket_code($raw);
    if ($ref === '') {
        return ['result' => 'NOT_FOUND', 'ref' => $ref];
    }
    if ($sig !== null && !ticket_signature_ok($ref, $sig)) {
        log_activity('Rejected ticket with invalid QR signature', $ref);
        return ['result' => 'FORGED', 'ref' => $ref];
    }
    $b = load_booking($ref);
    if (!$b) {
        return ['result' => 'NOT_FOUND', 'ref' => $ref];
    }
    $show_today = !$b['show_date'] || $b['show_date'] === date('Y-m-d');
    if ($b['status'] === 'Booked' && !$show_today) {
        $result = 'WRONG_DAY';
    } else {
        $updated = db_query(
            "UPDATE booking_details SET status = 'Used', checked_in_at = NOW() WHERE booking_ref = ? AND status = 'Booked'",
            's',
            [$ref]
        );
        $b = load_booking($ref);
        if ($updated === 1) {
            log_activity('Checked in ticket', $ref);
            $result = 'VALID';
        } else {
            $result = $b['status'] === 'Cancelled' ? 'CANCELLED' : 'ALREADY_USED';
        }
    }
    list($date, $time) = booking_when($b);
    return [
        'result' => $result,
        'ref' => $b['booking_ref'],
        'verified' => $sig !== null,
        'booking' => [
            'Movie' => $b['user_movie_name'],
            'Cinema' => $b['user_movie_cinema'],
            'Hall' => $b['hall_name'] ?: '-',
            'Show' => $date . ' at ' . $time,
            'Seats' => $b['seats'] ? implode(', ', array_column($b['seats'], 'seat_label')) . ' (' . count($b['seats']) . ')' : $b['user_seat'],
            'Customer' => $b['user_name'] . ' (' . $b['user_email'] . ')',
            'Snacks' => $b['snacks'] ? implode(', ', array_map(function ($s) { return $s['name'] . ' x' . $s['qty']; }, $b['snacks'])) : 'None',
            'Total' => money($b['total_amount']),
            'Status' => $b['status'],
            'Checked in' => $b['checked_in_at'] ? date('d M Y, g:i A', strtotime($b['checked_in_at'])) : '-',
            'Cancelled' => $b['cancelled_at'] ? date('d M Y, g:i A', strtotime($b['cancelled_at'])) : '-',
        ],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        json_out(['result' => 'NOT_FOUND', 'ref' => 'Session expired, reload the page'], 403);
    }
    json_out(check_in_ticket($_POST['ref'] ?? ''));
}

$today_checked_in = db_one("SELECT COUNT(*) AS n FROM booking_details WHERE DATE(checked_in_at) = CURDATE()")['n'];

include('admin_header.php');
?>
<style>
  #reader { width: 100%; max-width: 420px; margin: 0 auto; border-radius: 8px; overflow: hidden; background: #111; min-height: 240px; }
  #reader video { border-radius: 8px; }
  .scan-result { border-radius: 8px; overflow: hidden; display: none; }
  .scan-result.is-shown { display: block; }
  .scan-head { padding: 18px; font-size: 26px; font-weight: 800; color: #fff; letter-spacing: 1px; }
  .scan-head.VALID { background: #28a745; }
  .scan-head.ALREADY_USED { background: #fd7e14; }
  .scan-head.CANCELLED { background: #dc3545; }
  .scan-head.NOT_FOUND { background: #6c757d; }
  .scan-head.FORGED { background: #8b1a1a; }
  .scan-head.WRONG_DAY { background: #6f42c1; }
  .scan-body table td { padding: 6px 10px; }
</style>
<div class="page-content">
  <div class="page-header">
    <div class="container-fluid">
      <h2 class="h5 no-margin-bottom">Scan Ticket &middot; <small><?php echo (int) $today_checked_in; ?> checked in today</small></h2>
    </div>
  </div>
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-6">
        <div class="block">
          <div class="title"><strong>Camera scanner</strong></div>
          <div id="reader"></div>
          <div class="text-center mt-3">
            <button type="button" class="btn btn-primary" id="cam-start" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);border:0;"><i class="fa fa-camera"></i> Start camera</button>
            <button type="button" class="btn btn-secondary" id="cam-stop" style="display:none;"><i class="fa fa-stop"></i> Stop</button>
          </div>
          <p class="text-muted small text-center mt-2 mb-0" id="cam-msg">Camera needs https or http://localhost. Use manual entry otherwise.</p>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="block">
          <div class="title"><strong>Manual entry</strong></div>
          <form id="manual-form" class="form-inline" autocomplete="off">
            <input type="text" class="form-control mr-2 mb-2" id="manual-ref" placeholder="CNX-2026-00001 or scanned link" style="flex:1;" required>
            <button type="submit" class="btn btn-secondary mb-2" style="background-image: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%);">Check in</button>
          </form>
        </div>
        <div class="block scan-result" id="scan-result">
          <div class="scan-head" id="scan-head"></div>
          <div class="scan-body p-2">
            <div class="text-warning small px-2" id="scan-warn"></div>
            <table class="table table-sm mb-0" id="scan-table"></table>
          </div>
        </div>
      </div>
    </div>
  </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var labels = { VALID: 'VALID - Entry allowed', ALREADY_USED: 'ALREADY USED', CANCELLED: 'CANCELLED', NOT_FOUND: 'NOT FOUND', FORGED: 'NOT GENUINE - QR signature invalid', WRONG_DAY: 'WRONG DAY - Not for today' };
  var lastRef = '', lastAt = 0, busy = false;

  function render(data) {
    var box = document.getElementById('scan-result');
    var head = document.getElementById('scan-head');
    var table = document.getElementById('scan-table');
    head.className = 'scan-head ' + data.result;
    head.textContent = labels[data.result] + (data.ref ? '  ·  ' + data.ref : '');
    table.innerHTML = '';
    document.getElementById('scan-warn').textContent = data.booking ? (data.verified ? 'QR signature verified: this ticket was issued by CineMax.' : 'Reference typed in or old ticket: QR signature not checked.') : ''; document.getElementById('scan-warn').className = 'small px-2 pt-1 ' + (data.verified ? 'text-success' : 'text-warning');
    if (data.booking) {
      Object.keys(data.booking).forEach(function (k) {
        var tr = table.insertRow();
        var a = tr.insertCell(); a.textContent = k; a.style.color = '#8a8d93'; a.style.width = '120px';
        var b = tr.insertCell(); b.textContent = data.booking[k];
      });
    }
    box.classList.add('is-shown');
  }

  function check(ref) {
    ref = (ref || '').trim();
    if (!ref || busy) return;
    if (ref === lastRef && Date.now() - lastAt < 4000) return;
    lastRef = ref; lastAt = Date.now(); busy = true;
    var body = new FormData();
    body.append('ref', ref);
    body.append('csrf', <?php echo json_encode(csrf_token()); ?>);
    fetch('scan_ticket.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(render)
      .catch(function () { alert('Could not reach the server. Please try again.'); })
      .then(function () { busy = false; });
  }

  document.getElementById('manual-form').addEventListener('submit', function (e) {
    e.preventDefault();
    lastRef = '';
    check(document.getElementById('manual-ref').value);
  });

  var scanner = null;
  var startBtn = document.getElementById('cam-start');
  var stopBtn = document.getElementById('cam-stop');
  startBtn.addEventListener('click', function () {
    scanner = scanner || new Html5Qrcode('reader');
    scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, check, function () {})
      .then(function () { startBtn.style.display = 'none'; stopBtn.style.display = ''; document.getElementById('cam-msg').textContent = 'Point the camera at the ticket QR code.'; })
      .catch(function (err) { document.getElementById('cam-msg').textContent = 'Camera unavailable: ' + err + '. Use manual entry.'; });
  });
  stopBtn.addEventListener('click', function () {
    scanner.stop().then(function () { stopBtn.style.display = 'none'; startBtn.style.display = ''; });
  });
});
</script>
<?php include('admin_footer.php'); ?>
