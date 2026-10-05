<?php
$hold_timer_seconds = max(0, (int) $hold_timer_seconds);
$hold_timer_back = 'seat_map.php?show_id=' . (int) $hold_timer_show_id;
?>
<div class="cx-timer" id="hold-timer" role="timer" aria-live="polite">
  <i class="fa fa-clock-o"></i> Seats held for <span id="hold-timer-left">--:--</span>
</div>
<script>
(function () {
  var left = <?php echo $hold_timer_seconds; ?>;
  var back = <?php echo json_encode($hold_timer_back); ?>;
  var box = document.getElementById('hold-timer');
  var out = document.getElementById('hold-timer-left');
  function tick() {
    if (left <= 0) {
      clearInterval(timer);
      out.textContent = '0:00';
      alert('Your seat hold has expired. Please select your seats again.');
      window.location.href = back;
      return;
    }
    out.textContent = Math.floor(left / 60) + ':' + ('0' + (left % 60)).slice(-2);
    box.classList.toggle('is-low', left <= 60);
    left--;
  }
  var timer = setInterval(tick, 1000);
  tick();
})();
</script>
