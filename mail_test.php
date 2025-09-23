<?php
// mail_test.php — send a test email using config.php (PHPMailer SMTP)
require_once __DIR__ . '/config.php';

// Optional: quick warning if SMTP password wasn't set
$showPassWarning = (defined('SMTP_PASS') && SMTP_PASS === 'CHANGE_ME_STRONG_PASS');

$statusMsg = null;
$statusType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF protect
  csrf_check();

  $to      = trim($_POST['to'] ?? '');
  $subject = trim($_POST['subject'] ?? 'SMTP test — SORA Labs');
  $message = $_POST['message'] ?? '<p>Hello from <strong>SORA Labs</strong> SMTP test!</p><p>This is a test email.</p>';

  // Basic validation
  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $statusType = 'danger';
    $statusMsg  = 'Please enter a valid recipient email address.';
  } else {
    $ok = send_mail_html($to, $subject, $message);
    if ($ok) {
      $statusType = 'success';
      $statusMsg  = 'Test email sent! Check your inbox (and Spam folder).';
    } else {
      $statusType = 'danger';
      $statusMsg  = 'Sending failed. Double-check SMTP host/port/username/password and DNS (DKIM/SPF/DMARC).';
    }
  }
}

theme_head('Email Test — SORA Labs');
topbar('');
?>
<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <section class="glass p-4 p-md-5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <div class="d-flex align-items-center gap-3">
            <span class="dot" aria-hidden="true"></span>
            <h1 class="h5 mb-0 brand">SMTP Email Test</h1>
          </div>
          <span class="badge text-bg-dark border border-1 border-light-subtle">PHPMailer</span>
        </div>

        <?php if ($showPassWarning): ?>
          <div class="alert alert-warning">
            <strong>Heads up:</strong> You’re still using the placeholder SMTP password
            (<code>CHANGE_ME_STRONG_PASS</code>). Set <code>SMTP_PASS</code> via env or config before testing.
          </div>
        <?php endif; ?>

        <?php if ($statusMsg): ?>
          <div class="alert alert-<?= htmlspecialchars($statusType) ?>"><?= $statusMsg ?></div>
        <?php endif; ?>

        <div class="mb-3 small text-secondary">
          <i class="bi bi-gear me-1"></i>
          Using <strong><?= htmlspecialchars(SMTP_HOST) ?></strong> on port <strong><?= (int)SMTP_PORT ?></strong> (<?= htmlspecialchars(strtoupper(SMTP_ENCRYPTION)) ?>),
          From: <strong><?= htmlspecialchars(MAIL_FROM_NAME) ?> &lt;<?= htmlspecialchars(MAIL_FROM) ?>&gt;</strong>
        </div>

        <form method="post" class="row g-3">
          <?= csrf_field() ?>
          <div class="col-12">
            <label class="form-label">To (recipient email)</label>
            <input type="email" name="to" class="form-control" placeholder="your.email@example.com" required>
          </div>
          <div class="col-12">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control" value="SMTP test — SORA Labs">
          </div>
          <div class="col-12">
            <label class="form-label">HTML Message</label>
            <textarea name="message" class="form-control" rows="6"><p>Hello from <strong>SORA Labs</strong> SMTP test!</p><p>This is a test email.</p></textarea>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-light"><i class="bi bi-send me-1"></i> Send Test Email</button>
            <a class="btn btn-ghost" href="login.php"><i class="bi bi-person me-1"></i> Go to Login</a>
          </div>
        </form>

        <hr class="border-secondary-subtle my-4">

        <div class="small text-secondary">
          <i class="bi bi-life-preserver me-1"></i>
          If it fails:
          <ul class="mb-0">
            <li>Confirm mailbox and password in cPanel (Email Accounts).</li>
            <li>Check <strong>SMTP_HOST</strong> and port from “Connect Devices” (usually <code>mail.yourdomain</code> + 587 TLS).</li>
            <li>Ensure DKIM/SPF/DMARC are set (cPanel → Email Deliverability).</li>
            <li>Try port <strong>465</strong> with <code>SMTP_ENCRYPTION=ssl</code> if 587 is blocked.</li>
          </ul>
        </div>
      </section>
    </div>
  </div>
</div>
<?php theme_foot();
