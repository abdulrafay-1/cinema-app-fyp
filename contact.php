<?php
require_once __DIR__ . '/customer_functions.php';

// cinema contact details shown next to the form (placeholders: change them in one place here)
const CONTACT_PHONE = '+92 21 3456 7890';
const CONTACT_EMAIL = 'support@onlinemoviebooking.pk';
const CONTACT_ADDRESS = 'Plot 21, Shahrah-e-Faisal, Karachi, Pakistan';
const CONTACT_HOURS = 'Every day, 11:00 AM to 11:00 PM';

$user = customer();
$old = ['name' => $user['name'] ?? '', 'email' => $user['email'] ?? '', 'subject' => '', 'message' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $value) {
        $old[$key] = trim($_POST[$key] ?? '');
    }
    if (!csrf_ok()) {
        $errors[] = 'Invalid request. Please try again.';
    }
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors[] = 'Please enter your name (max 100 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 100) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (mb_strlen($old['subject']) < 3 || mb_strlen($old['subject']) > 200) {
        $errors[] = 'Please enter a subject (3 to 200 characters).';
    }
    if (mb_strlen($old['message']) < 10 || mb_strlen($old['message']) > 3000) {
        $errors[] = 'Please write a message of 10 to 3000 characters.';
    }
    // simple spam guard: one message per minute from the same browser session
    if (!$errors && isset($_SESSION['contact_sent_at']) && time() - $_SESSION['contact_sent_at'] < 60) {
        $errors[] = 'You just sent a message. Please wait a minute before sending another one.';
    }

    if (!$errors) {
        db_query(
            'INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)',
            'ssss',
            [$old['name'], $old['email'], $old['subject'], $old['message']]
        );
        $_SESSION['contact_sent_at'] = time();
        flash('success', 'Thank you, ' . $old['name'] . '! Your message was sent. We usually reply within one working day.');
        redirect('contact.php');
    }
}

include('header.php');
?>
<div class="container cx-page">
  <h2 style="margin-bottom:6px;">Contact Us</h2>
  <p class="cx-muted">Questions about a booking, refunds or group tickets? Send us a message.</p>

  <?php echo render_customer_flash(); ?>
  <?php if ($errors): ?>
    <div class="cx-alert cx-alert-error" role="alert">
      <?php foreach ($errors as $error): ?><div><?php echo e($error); ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="cx-card h-100">
        <form method="post" action="contact.php" class="cu-form" novalidate>
          <?php echo csrf_field(); ?>
          <div class="row g-3">
            <div class="col-md-6 cu-field">
              <label for="name">Your name</label>
              <input type="text" class="cx-input" id="name" name="name" maxlength="100" value="<?php echo e($old['name']); ?>" required>
            </div>
            <div class="col-md-6 cu-field">
              <label for="email">Your email</label>
              <input type="email" class="cx-input" id="email" name="email" maxlength="100" value="<?php echo e($old['email']); ?>" required>
            </div>
          </div>
          <div class="cu-field">
            <label for="subject">Subject</label>
            <input type="text" class="cx-input" id="subject" name="subject" maxlength="200" value="<?php echo e($old['subject']); ?>" placeholder="e.g. Group booking for Saturday" required>
          </div>
          <div class="cu-field">
            <label for="message">Message</label>
            <textarea class="cx-input" id="message" name="message" maxlength="3000" rows="6" required><?php echo e($old['message']); ?></textarea>
          </div>
          <button type="submit" class="cx-btn"><i class="fas fa-paper-plane"></i> Send message</button>
        </form>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="cx-card h-100">
        <h3 style="margin-top:0;">Get in touch</h3>
        <ul class="cu-info">
          <li><i class="fas fa-phone-alt"></i><div><div class="cx-muted" style="font-size:12px;">Phone</div><a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', CONTACT_PHONE)); ?>"><?php echo e(CONTACT_PHONE); ?></a></div></li>
          <li><i class="fas fa-envelope"></i><div><div class="cx-muted" style="font-size:12px;">Email</div><a href="mailto:<?php echo e(CONTACT_EMAIL); ?>"><?php echo e(CONTACT_EMAIL); ?></a></div></li>
          <li><i class="fas fa-map-marker-alt"></i><div><div class="cx-muted" style="font-size:12px;">Address</div><?php echo e(CONTACT_ADDRESS); ?></div></li>
          <li><i class="fas fa-clock"></i><div><div class="cx-muted" style="font-size:12px;">Box office hours</div><?php echo e(CONTACT_HOURS); ?></div></li>
        </ul>
        <p class="cx-muted mt-3 mb-0" style="font-size:13px;">For a booking question, please include your booking reference (e.g. CNX-2026-00001).</p>
      </div>
    </div>
  </div>
</div>
<?php include('footer.php'); ?>
