<?php
// contact.php — Contact page (matches SORA Labs theme)
require_once __DIR__.'/config.php';

// Optional: load logged-in user's info for prefill
$isLoggedIn = !empty($_SESSION['user_id']);
$me = null;
if ($isLoggedIn) {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT username, email, first_name, last_name FROM users WHERE id=:id");
  $stmt->execute([':id'=>$_SESSION['user_id']]);
  $me = $stmt->fetch() ?: null;
}

$errors = [];
$sent = false;
$name = '';
$email = $me['email'] ?? '';
$subject = '';
$category = 'General';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    csrf_check();

    // Honeypot (bots will fill it)
    if (!empty($_POST['website'])) {
      throw new Exception('Spam detected.');
    }

    if (!rate_limit('contact_form', 3, 300)) {
      throw new Exception('Too many requests. Please try again in a few minutes.');
    }

    // Collect inputs
    $name     = trim($_POST['name'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $message  = trim($_POST['message'] ?? '');

    // Email source: logged-in users use account email; guests provide one
    if ($isLoggedIn) {
      $email = $me['email'] ?? '';
    } else {
      $email = trim($_POST['email'] ?? '');
    }

    // Validate
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80)      $errors['name'] = 'Please provide your name (2–80 chars).';
    if (!$isLoggedIn) {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors['email'] = 'Please enter a valid email address.';
      }
    } else {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Your account email looks invalid. Please update it in settings.';
      }
    }
    if (mb_strlen($subject) < 3 || mb_strlen($subject) > 120) $errors['subject'] = 'Subject should be 3–120 characters.';
    if (mb_strlen($message) < 10 || mb_strlen($message) > 4000) $errors['message'] = 'Message should be 10–4000 characters.';

    if ($errors) throw new Exception('Please fix the highlighted fields.');

    // Build HTML content
    $safeName   = htmlspecialchars($name, ENT_QUOTES);
    $safeEmail  = htmlspecialchars($email, ENT_QUOTES);
    $safeSubj   = htmlspecialchars($subject, ENT_QUOTES);
    $safeCat    = htmlspecialchars($category, ENT_QUOTES);
    $safeMsg    = nl2br(htmlspecialchars($message, ENT_QUOTES));

    $metaTable = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;line-height:21px;color:#d7e2ee;">
      <tr><td style="padding:2px 0;"><strong>From:</strong> '.$safeName.' ('.$safeEmail.')</td></tr>
      <tr><td style="padding:2px 0;"><strong>Category:</strong> '.$safeCat.'</td></tr>
      <tr><td style="padding:2px 0;"><strong>Subject:</strong> '.$safeSubj.'</td></tr>
      <tr><td style="padding:8px 0 0 0;"><strong>Message:</strong><br>'.$safeMsg.'</td></tr>
      <tr><td style="padding:12px 0 0 0; font-size:12px; color:#9fb6c9;">
        <em>IP:</em> '.htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown', ENT_QUOTES).'
        &nbsp;|&nbsp; <em>User ID:</em> '.($isLoggedIn ? (int)$_SESSION['user_id'] : 'guest').'
      </td></tr>
    </table>';

    // Send to site inbox (MAIL_FROM)
    $adminSubject = 'Contact form — '.$subject;
    $ok = send_templated_mail(
      MAIL_FROM,
      $adminSubject,
      'New contact message',
      'New contact message received via SORA Labs',
      $metaTable,
      'Open Dashboard', route('dashboard')
    );

    if (!$ok) throw new Exception('Failed to send message. Please try again later.');

    // Optional: send acknowledgement to user
    if ($email) {
      $ackHtml = '<p style="margin:0 0 12px 0;">Hi '.$safeName.',</p>
        <p style="margin:0 0 12px 0;">Thanks for reaching out to <strong>SORA Labs</strong>. We received your message and will get back to you soon.</p>
        <p style="margin:0 0 12px 0;"><strong>Your subject:</strong> '.$safeSubj.'<br><strong>Category:</strong> '.$safeCat.'</p>
        <p style="margin:0;">You can also check our <a href="'.route('faq').'">FAQ</a> for quick answers.</p>';
      send_templated_mail($email, 'We got your message — SORA Labs', 'Thanks for contacting us', 'Auto-acknowledgement', $ackHtml, 'Visit FAQ', route('faq'));
    }

    $sent = true;
    // Clear form values on success
    $name = $subject = $message = '';
    if (!$isLoggedIn) $email = '';

  } catch (Throwable $e) {
    if (!$errors) $errors['fatal'] = $e->getMessage();
  }
}

theme_head('Contact — SORA Labs');
topbar('contact'); ?>

<style>
  /* Space below fixed top bar so content never hides under it */
  body { padding-top: 84px; }
  @media (max-width: 991.98px) { body { padding-top: 96px; } }

  /* Stronger glass for readability (desktop + mobile) */
  .glass-strong {
    background:
      radial-gradient(1200px 800px at 15% 5%, rgba(108,99,255,.26), transparent 60%),
      radial-gradient(1000px 700px at 85% 25%, rgba(0,231,255,.24), transparent 60%),
      radial-gradient(900px 700px at 40% 95%, rgba(255,110,199,.22), transparent 60%),
      rgba(18,22,30,.92);
    border: 1px solid rgba(255,255,255,0.16);
    color: #f4f8ff;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,.35);
  }
  .glass-strong .form-control, .glass-strong .form-select, .glass-strong .form-check-input {
    background-color: rgba(255,255,255,.08);
    color: #f4f8ff;
    border-color: rgba(255,255,255,.22);
  }
  .glass-strong .form-control::placeholder { color: #e6eeff; opacity: .9; }
  .glass-strong .form-control:focus, .glass-strong .form-select:focus {
    box-shadow: 0 0 0 .25rem rgba(99,179,237,.25);
    border-color: #84c5ff;
  }
  .btn-ghost{ background:rgba(255,255,255,.06); color:#f4f8ff; border:1px solid rgba(255,255,255,.22); }
  .btn-ghost:hover{ background:rgba(255,255,255,.12); }
  .small-dim { color:#cfd9e6; opacity:.95; }

  .card-lite { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.18); color:#f4f8ff; }

  .is-invalid { border-color:#ff6b6b !important; }
  .invalid-feedback { display:block; }
</style>

<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-10">

      <!-- Header -->
      <section class="glass-strong p-4 p-md-5 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <span class="dot" aria-hidden="true"></span>
            <h1 class="h4 mb-0 brand">Contact Us</h1>
          </div>
          <span class="badge text-bg-dark border border-1 border-light-subtle">We reply ASAP</span>
        </div>
        <p class="small small-dim mb-0 mt-2">Questions, feedback, or a quick hello — we’d love to hear from you.</p>
      </section>

      <!-- Content -->
      <div class="row g-4">
        <!-- Form -->
        <div class="col-12 col-lg-7">
          <section class="glass-strong p-4 p-md-4">
            <?php if ($sent): ?>
              <div class="alert alert-success border-0">
                <i class="bi bi-check2-circle me-1"></i>
                Your message has been sent. We’ll get back to you shortly.
              </div>
            <?php elseif (!empty($errors['fatal'])): ?>
              <div class="alert alert-danger border-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?= htmlspecialchars($errors['fatal']) ?>
              </div>
            <?php endif; ?>

            <form method="post" class="mt-2" novalidate>
              <?= csrf_field() ?>
              <!-- honeypot -->
              <input type="text" name="website" value="" autocomplete="off" style="position:absolute;left:-5000px;width:1px;height:1px;opacity:0;" tabindex="-1" aria-hidden="true">

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Your Name</label>
                  <input type="text" name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>" value="<?= htmlspecialchars($name) ?>" placeholder="e.g., Afsana Rahman" required>
                  <?php if(isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <?php if ($isLoggedIn): ?>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" disabled>
                    <div class="form-text small-dim">Using your account email</div>
                  <?php else: ?>
                    <input type="email" name="email" class="form-control <?= isset($errors['email'])?'is-invalid':'' ?>" value="<?= htmlspecialchars($email) ?>" placeholder="you@example.com" required>
                    <?php if(isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                  <?php endif; ?>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Category</label>
                  <select name="category" class="form-select">
                    <?php
                      $cats = ['General','Account','Bug Report','Feature Request','Partnership'];
                      foreach ($cats as $c) {
                        $sel = ($category === $c) ? ' selected' : '';
                        echo '<option'.$sel.'>'.htmlspecialchars($c).'</option>';
                      }
                    ?>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Subject</label>
                  <input type="text" name="subject" class="form-control <?= isset($errors['subject'])?'is-invalid':'' ?>" value="<?= htmlspecialchars($subject) ?>" placeholder="Short summary" required>
                  <?php if(isset($errors['subject'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['subject']) ?></div><?php endif; ?>
                </div>

                <div class="col-12">
                  <label class="form-label">Message</label>
                  <textarea name="message" rows="6" class="form-control <?= isset($errors['message'])?'is-invalid':'' ?>" placeholder="Tell us more..."><?= htmlspecialchars($message) ?></textarea>
                  <?php if(isset($errors['message'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['message']) ?></div><?php endif; ?>
                </div>

                <div class="col-12 d-flex gap-2">
                  <button type="submit" class="btn btn-light"><i class="bi bi-envelope-paper-heart me-1"></i>Send Message</button>
                  <a href="<?= route('faq') ?>" class="btn btn-ghost"><i class="bi bi-question-circle me-1"></i>FAQ</a>
                </div>
              </div>
            </form>
          </section>
        </div>

        <!-- Sidebar / Info -->
        <div class="col-12 col-lg-5">
          <div class="card card-lite p-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bi bi-info-circle"></i><strong>Tips for faster replies</strong>
            </div>
            <ul class="small mb-0">
              <li>Include any relevant links, screenshots, or steps to reproduce.</li>
              <li>For account issues, mention your username or registered email.</li>
              <li>Check the <a href="<?= route('faq') ?>">FAQ</a> for quick answers.</li>
            </ul>
          </div>

          <div class="card card-lite p-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bi bi-at"></i><strong>Direct email</strong>
            </div>
            <p class="small mb-2">You can also email us at:</p>
            <div class="d-flex align-items-center gap-2">
              <span class="badge text-bg-dark border border-light-subtle">info@soralabs.cc</span>
            </div>
          </div>

          <div class="card card-lite p-3">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bi bi-link-45deg"></i><strong>Quick links</strong>
            </div>
            <div class="d-grid gap-2">
              <a class="btn btn-ghost btn-sm" href="<?= route('dashboard') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
              <a class="btn btn-ghost btn-sm" href="<?= route('leaderboard') ?>"><i class="bi bi-trophy me-1"></i>Leaderboard</a>
              <a class="btn btn-ghost btn-sm" href="<?= route('status') ?>"><i class="bi bi-globe-americas me-1"></i>Overall Status</a>
              <a class="btn btn-ghost btn-sm" href="<?= route('home') ?>"><i class="bi bi-geo-alt me-1"></i>Zone Dashboard</a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php theme_foot();
