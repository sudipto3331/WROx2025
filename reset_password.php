<?php
require_once __DIR__.'/config.php';

$pdo = db();
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $token = $_POST['token'] ?? '';
  $pass1 = $_POST['password'] ?? '';
  $pass2 = $_POST['password2'] ?? '';
  if (!$token) { flash('error','Invalid request.'); header('Location: reset_password.php'); exit; }
  if (strlen($pass1) < 8 || $pass1 !== $pass2) {
    flash('error','Passwords must match and be at least 8 characters.'); header('Location: reset_password.php?token='.$token); exit;
  }
  $stmt = $pdo->prepare("SELECT pr.id reset_id, u.id user_id FROM password_resets pr
                         JOIN users u ON pr.user_id = u.id
                         WHERE pr.token = :t AND pr.used=0 AND pr.expires_at > NOW()");
  $stmt->execute([':t'=>$token]);
  $row = $stmt->fetch();
  if (!$row) { http_response_code(400); exit('Invalid or expired token.'); }

  $hash = password_hash($pass1, PASSWORD_DEFAULT);
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE users SET password_hash = :h WHERE id=:id")->execute([':h'=>$hash, ':id'=>$row['user_id']]);
  $pdo->prepare("UPDATE password_resets SET used=1 WHERE id=:id")->execute([':id'=>$row['reset_id']]);
  $pdo->commit();

  flash('success','Password updated. You can now login.');
  header('Location: login.php'); exit;
}

theme_head('Reset Password — SORA Labs');
topbar(''); ?>
<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <section class="glass p-4 p-md-5">
        <h1 class="h5 mb-3">Set a new password</h1>
        <?php if ($m = flash('success')): ?>
          <div class="alert alert-success"><?= $m ?></div>
        <?php endif; if ($e = flash('error')): ?>
          <div class="alert alert-danger"><?= $e ?></div>
        <?php endif; ?>
        <?php if (!$token): ?>
          <div class="alert alert-warning">Missing token. Use the link from your email.</div>
        <?php endif; ?>
        <form method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="col-12">
            <label class="form-label">New password</label>
            <input type="password" name="password" class="form-control" minlength="8" required>
          </div>
          <div class="col-12">
            <label class="form-label">Confirm password</label>
            <input type="password" name="password2" class="form-control" minlength="8" required>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-light"><i class="bi bi-key me-1"></i> Update password</button>
            <a class="btn btn-ghost" href="login.php">Cancel</a>
          </div>
        </form>
      </section>
    </div>
  </div>
</div>
<?php theme_foot();
