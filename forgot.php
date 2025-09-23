<?php
require_once __DIR__.'/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $email = trim($_POST['email'] ?? '');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Please enter a valid email.');
    header('Location: forgot.php'); exit;
  }
  $pdo = db();
  $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE email=:e");
  $stmt->execute([':e'=>$email]);
  $user = $stmt->fetch();

  // Always respond success (to avoid email enumeration)
  if ($user) {
    $token = token64();
    $exp   = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:u,:t,:e)")
        ->execute([':u'=>$user['id'], ':t'=>$token, ':e'=>$exp]);
    $link = base_url().'/reset_password.php?token='.$token;
    $html = '<p>We received a password reset request.</p><p><a href="'.$link.'">Reset Password</a></p><p>This link expires in 1 hour.</p>';
    send_mail_html($email, 'Password reset — SORA Labs', $html);
  }
  flash('success','If that email exists, a reset link has been sent.');
  header('Location: forgot.php'); exit;
}

theme_head('Forgot Password — SORA Labs');
topbar(''); ?>
<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <section class="glass p-4 p-md-5">
        <h1 class="h5 mb-3">Forgot your password?</h1>
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
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-light"><i class="bi bi-envelope me-1"></i> Send reset link</button>
            <a class="btn btn-ghost" href="login.php">Back to login</a>
          </div>
        </form>
      </section>
    </div>
  </div>
</div>
<?php theme_foot();
