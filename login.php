<?php
require_once __DIR__.'/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // Rate limit: 5 attempts / 10 minutes
  if (!rate_limit('login', 5, 600)) {
    flash('error', 'Too many attempts. Please try again later.');
    header('Location: login.php'); exit;
  }

  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $pdo   = db();

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
  $stmt->execute([':email'=>$email]);
  $user = $stmt->fetch();

  if (!$user || !password_verify($pass, $user['password_hash'])) {
    flash('error', 'Invalid email or password.');
    header('Location: login.php'); exit;
  }

  if (!$user['email_verified']) {
    // Resend verification token
    if (empty($user['verification_token']) || strtotime($user['verification_expires']) < time()) {
      $token = token64();
      $exp   = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
      $upd = $pdo->prepare("UPDATE users SET verification_token=:t, verification_expires=:e WHERE id=:id");
      $upd->execute([':t'=>$token, ':e'=>$exp, ':id'=>$user['id']]);
      $user['verification_token'] = $token;
    }
    $link = base_url().'/verify.php?token='.$user['verification_token'];
    $html = '<p>Please verify your email to continue.</p><p><a href="'.$link.'">Verify now</a></p>';
    send_mail_html($user['email'], 'Verify your email — SORA Labs', $html);
    flash('error', 'Email not verified. We\'ve sent you a new verification link.');
    header('Location: login.php'); exit;
  }

  // Success
  $_SESSION['user_id'] = $user['id'];
  $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id=:id")->execute([':id'=>$user['id']]);
  header('Location: dashboard.php'); exit;
}

theme_head('Login — SORA Labs');
topbar(''); ?>
<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <section class="glass p-4 p-md-5">
        <h1 class="h4 mb-3">Welcome back</h1>
        <?php if ($m = flash('success')): ?>
          <div class="alert alert-success"><?= $m ?></div>
        <?php endif; if ($e = flash('error')): ?>
          <div class="alert alert-danger"><?= $e ?></div>
        <?php endif; ?>
        <form method="post" class="row g-3">
          <?= csrf_field() ?>
          <div class="col-12">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="col-12">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-light"><i class="bi bi-box-arrow-in-right me-1"></i> Login</button>
            <a class="btn btn-ghost" href="register.php">Create account</a>
            <a class="btn btn-ghost" href="forgot.php">Forgot password?</a>
          </div>
        </form>
      </section>
    </div>
  </div>
</div>
<?php theme_foot();
