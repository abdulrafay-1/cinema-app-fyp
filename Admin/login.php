<?php
session_start();
include('config.php');

if (!empty($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

$login_error = '';
$login_email = '';
if (isset($_POST['admin_btn'])) {
    $login_email = trim($_POST['admin_email'] ?? '');
    $stmt = mysqli_prepare($con, 'SELECT email FROM admin_login WHERE email = ? AND password = ? LIMIT 1');
    $password = (string) ($_POST['admin_pass'] ?? '');
    mysqli_stmt_bind_param($stmt, 'ss', $login_email, $password);
    mysqli_stmt_execute($stmt);
    $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($admin) {
        session_regenerate_id(true);
        $_SESSION['admin'] = $admin['email'];
        header('Location: dashboard.php');
        exit;
    }
    $login_error = 'Invalid email or password.';
}

$backdrop_row = mysqli_fetch_assoc(mysqli_query($con, "SELECT backdrop FROM addmovie WHERE backdrop IS NOT NULL AND backdrop <> '' ORDER BY popularity DESC LIMIT 1"));
$backdrop = $backdrop_row ? 'upload-images/' . rawurlencode($backdrop_row['backdrop']) : '';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Admin Login - CineMax Karachi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Muli:300,400,600,700,800">
    <link rel="shortcut icon" href="img/favi.png">
    <style>
      * { box-sizing: border-box; }
      body { margin: 0; min-height: 100vh; font-family: 'Muli', sans-serif; background: radial-gradient(circle at 15% 20%, rgba(255, 85, 165, .14), transparent 40%), radial-gradient(circle at 85% 80%, rgba(255, 88, 96, .12), transparent 40%), #1e1f23; color: #e8e8ea; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
      .al-card { width: 100%; max-width: 980px; display: grid; grid-template-columns: 1.05fr 1fr; border-radius: 22px; overflow: hidden; background: #2a2b30; border: 1px solid rgba(255, 255, 255, .06); box-shadow: 0 30px 80px rgba(0, 0, 0, .55); }
      .al-brand { position: relative; padding: 48px 44px; display: flex; flex-direction: column; justify-content: space-between; min-height: 560px; color: #fff; background: #ff5880; overflow: hidden; }
      .al-brand-bg { position: absolute; inset: 0; background-size: cover; background-position: center; }
      .al-brand::after { content: ''; position: absolute; inset: 0; background: linear-gradient(150deg, rgba(255, 85, 165, .93) 0%, rgba(255, 88, 96, .88) 55%, rgba(120, 30, 70, .9) 100%); }
      .al-brand > * { position: relative; z-index: 1; }
      .al-logo { display: flex; align-items: center; gap: 12px; font-weight: 800; letter-spacing: 1px; font-size: 18px; text-transform: uppercase; }
      .al-logo span { width: 44px; height: 44px; border-radius: 12px; background: rgba(255, 255, 255, .18); display: inline-flex; align-items: center; justify-content: center; font-size: 20px; }
      .al-brand h1 { font-size: 38px; font-weight: 800; line-height: 1.15; margin: 0 0 14px; color: #fff; }
      .al-brand p.lead { font-size: 16px; color: rgba(255, 255, 255, .88); margin: 0 0 28px; max-width: 360px; line-height: 1.6; }
      .al-features { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
      .al-features li { display: flex; align-items: center; gap: 12px; font-size: 14px; color: rgba(255, 255, 255, .95); }
      .al-features i { width: 32px; height: 32px; border-radius: 9px; background: rgba(255, 255, 255, .16); display: inline-flex; align-items: center; justify-content: center; font-size: 14px; flex: 0 0 auto; }
      .al-brand small { color: rgba(255, 255, 255, .75); font-size: 12px; }
      .al-form { padding: 56px 48px; display: flex; flex-direction: column; justify-content: center; }
      .al-form h2 { font-size: 28px; font-weight: 800; color: #fff; margin: 0 0 6px; }
      .al-form .al-sub { color: #8a8d93; margin: 0 0 32px; font-size: 14px; }
      .al-field { margin-bottom: 20px; }
      .al-field label { display: block; font-size: 13px; font-weight: 700; color: #c9cacd; margin-bottom: 8px; }
      .al-input { position: relative; }
      .al-input > i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #6f7279; font-size: 15px; pointer-events: none; transition: color .15s; }
      .al-input input { width: 100%; height: 50px; border-radius: 12px; border: 1.5px solid #3a3b42; background: #1f2024; color: #fff; padding: 0 46px; font-size: 15px; font-family: inherit; outline: none; transition: border-color .15s, box-shadow .15s; }
      .al-input input::placeholder { color: #5d6068; }
      .al-input input:focus { border-color: #ff55a5; box-shadow: 0 0 0 4px rgba(255, 85, 165, .15); }
      .al-input:focus-within > i { color: #ff55a5; }
      .al-input input:-webkit-autofill { -webkit-box-shadow: 0 0 0 40px #1f2024 inset; -webkit-text-fill-color: #fff; caret-color: #fff; }
      .al-eye { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border: 0; border-radius: 9px; background: transparent; color: #6f7279; cursor: pointer; }
      .al-eye:hover, .al-eye:focus-visible { color: #fff; background: rgba(255, 255, 255, .06); outline: none; }
      .al-error { display: flex; align-items: center; gap: 10px; background: rgba(255, 88, 96, .12); border: 1px solid rgba(255, 88, 96, .45); color: #ffb3b6; border-radius: 12px; padding: 12px 14px; font-size: 14px; margin-bottom: 22px; }
      .al-btn { width: 100%; height: 52px; border: 0; border-radius: 12px; background: linear-gradient(90deg, #ff55a5 0%, #ff5860 100%); color: #fff; font-weight: 800; font-size: 15px; letter-spacing: .5px; cursor: pointer; box-shadow: 0 10px 24px rgba(255, 85, 165, .3); transition: transform .1s, box-shadow .15s; margin-top: 6px; }
      .al-btn:hover { box-shadow: 0 12px 28px rgba(255, 85, 165, .45); }
      .al-btn:active { transform: translateY(1px); }
      .al-btn:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
      .al-back { display: inline-flex; align-items: center; gap: 8px; margin-top: 26px; color: #8a8d93; font-size: 14px; text-decoration: none; align-self: center; }
      .al-back:hover { color: #ff55a5; text-decoration: none; }
      @media (max-width: 860px) {
        .al-card { grid-template-columns: 1fr; max-width: 480px; }
        .al-brand { min-height: 0; padding: 32px 28px; gap: 22px; }
        .al-brand h1 { font-size: 28px; }
        .al-features, .al-brand small { display: none; }
        .al-brand p.lead { margin-bottom: 0; }
        .al-form { padding: 36px 28px; }
      }
    </style>
  </head>
  <body>
    <main class="al-card">
      <section class="al-brand">
        <?php if ($backdrop): ?><div class="al-brand-bg" style="background-image:url('<?php echo htmlspecialchars($backdrop, ENT_QUOTES); ?>')"></div><?php endif; ?>
        <div class="al-logo"><span><i class="fa fa-film"></i></span>CineMax Karachi</div>
        <div>
          <h1>Admin Panel</h1>
          <p class="lead">Sign in to manage movies, shows, bookings and refunds for the cinema.</p>
          <ul class="al-features">
            <li><i class="fa fa-calendar"></i>Schedule shows and track seat occupancy</li>
            <li><i class="fa fa-qrcode"></i>Scan e-tickets at the entrance</li>
            <li><i class="fa fa-line-chart"></i>Revenue, refunds and reports in one place</li>
          </ul>
        </div>
        <small>&copy; <?php echo date('Y'); ?> CineMax Karachi &middot; Staff access only</small>
      </section>

      <section class="al-form">
        <h2>Welcome back</h2>
        <p class="al-sub">Enter your admin email and password to continue.</p>

        <?php if ($login_error): ?>
          <div class="al-error" role="alert"><i class="fa fa-exclamation-circle"></i><?php echo htmlspecialchars($login_error, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
          <div class="al-field">
            <label for="login-username">Email address</label>
            <div class="al-input">
              <input id="login-username" type="email" name="admin_email" placeholder="admin@gmail.com" autocomplete="username" required value="<?php echo htmlspecialchars($login_email, ENT_QUOTES); ?>" <?php echo $login_email ? '' : 'autofocus'; ?>>
              <i class="fa fa-envelope-o" aria-hidden="true"></i>
            </div>
          </div>
          <div class="al-field">
            <label for="login-password">Password</label>
            <div class="al-input">
              <input id="login-password" type="password" name="admin_pass" placeholder="Enter your password" autocomplete="current-password" required <?php echo $login_email ? 'autofocus' : ''; ?>>
              <i class="fa fa-lock" aria-hidden="true"></i>
              <button type="button" class="al-eye" id="toggle-password" aria-label="Show password" aria-pressed="false"><i class="fa fa-eye"></i></button>
            </div>
          </div>
          <button id="login" type="submit" name="admin_btn" value="1" class="al-btn">Sign in <i class="fa fa-arrow-right"></i></button>
        </form>
        <a class="al-back" href="../index.php"><i class="fa fa-arrow-left"></i> Back to website</a>
      </section>
    </main>
    <script>
      document.getElementById('toggle-password').addEventListener('click', function () {
        var input = document.getElementById('login-password');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        this.setAttribute('aria-pressed', show ? 'true' : 'false');
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        this.firstChild.className = show ? 'fa fa-eye-slash' : 'fa fa-eye';
      });
    </script>
  </body>
</html>
