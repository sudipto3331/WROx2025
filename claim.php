<?php
// claim.php — How to claim & redemption request (matches SORA Labs theme)
require_once __DIR__.'/config.php';

// Pull user + credits for display/prefill
$isLoggedIn = !empty($_SESSION['user_id']);
$me = null;
$currentCredit = null;
if ($isLoggedIn) {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT username, email, first_name, last_name, green_credit FROM users WHERE id=:id");
  $stmt->execute([':id'=>$_SESSION['user_id']]);
  $me = $stmt->fetch() ?: null;
  $currentCredit = $me['green_credit'] ?? 0;
}

// Form handling
$errors = [];
$sent   = false;

$name        = $isLoggedIn ? ($me['first_name'] ?: $me['username']) : '';
$email       = $isLoggedIn ? ($me['email'] ?? '') : '';
$phone       = '';
$partner     = 'GreenMart';
$method      = 'Digital voucher (email)';
$points      = '';
$notes       = '';

$partners = ['GreenMart', 'Eco Bazar', 'City Nursery', 'Urban Hardware', 'Community Co-op'];
$methods  = ['Digital voucher (email)', 'In-store pickup', 'Home delivery (where available)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    csrf_check();

    // Honeypot
    if (!empty($_POST['homepage'])) throw new Exception('Spam detected.');

    if (!rate_limit('claim_form', 3, 300)) {
      throw new Exception('Too many requests. Please try again in a few minutes.');
    }

    // Collect inputs
    $name    = trim($_POST['name'] ?? $name);
    $phone   = trim($_POST['phone'] ?? '');
    $partner = trim($_POST['partner'] ?? $partner);
    $method  = trim($_POST['method'] ?? $method);
    $points  = trim($_POST['points'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');

    // Email: account or guest
    if ($isLoggedIn) {
      $email = $me['email'] ?? '';
    } else {
      $email = trim($_POST['email'] ?? '');
    }

    // Validate
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80)           $errors['name'] = 'Please provide your name (2–80 chars).';
    if (!$isLoggedIn) {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors['email'] = 'Please enter a valid email address.';
      }
    } else {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Your account email looks invalid.';
      }
    }
    if ($phone && !preg_match('/^[0-9+\-\s()]{6,25}$/', $phone))  $errors['phone'] = 'Please enter a valid phone.';
    if (!in_array($partner, $partners, true))                     $errors['partner'] = 'Please select a partner.';
    if (!in_array($method, $methods, true))                       $errors['method'] = 'Please select a delivery method.';
    if ($points === '' || !ctype_digit($points) || (int)$points <= 0) {
      $errors['points'] = 'Enter a positive number of points.';
    }
    // Optional: soft check against current balance (not deducting here)
    if ($isLoggedIn && $currentCredit !== null && ctype_digit($points) && (int)$points > (int)$currentCredit) {
      $errors['points'] = 'Requested points exceed your current balance.';
    }
    if (mb_strlen($notes) > 1000)                                 $errors['notes'] = 'Notes are too long (max 1000 chars).';

    if ($errors) throw new Exception('Please fix the highlighted fields.');

    // Build admin email
    $safe = fn($s)=>htmlspecialchars($s ?? '', ENT_QUOTES);
    $metaTable = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;line-height:21px;color:#d7e2ee;">
      <tr><td style="padding:2px 0;"><strong>Name:</strong> '.$safe($name).'</td></tr>
      <tr><td style="padding:2px 0;"><strong>Email:</strong> '.$safe($email).'</td></tr>
      <tr><td style="padding:2px 0;"><strong>Phone:</strong> '.$safe($phone).'</td></tr>
      <tr><td style="padding:2px 0;"><strong>User ID:</strong> '.($isLoggedIn ? (int)$_SESSION['user_id'] : 'guest').'</td></tr>'.
      ($isLoggedIn ? '<tr><td style="padding:2px 0;"><strong>Current Credit:</strong> '.(int)$currentCredit.'</td></tr>' : '').
      '<tr><td style="padding:8px 0 0 0;"><strong>Requested Points:</strong> '.(int)$points.'</td></tr>
      <tr><td style="padding:2px 0;"><strong>Partner:</strong> '.$safe($partner).'</td></tr>
      <tr><td style="padding:2px 0;"><strong>Method:</strong> '.$safe($method).'</td></tr>'.
      ($notes ? '<tr><td style="padding:8px 0 0 0;"><strong>Notes:</strong><br>'.nl2br($safe($notes)).'</td></tr>' : '').
      '<tr><td style="padding:12px 0 0 0; font-size:12px; color:#9fb6c9;">
        <em>IP:</em> '.$safe($_SERVER['REMOTE_ADDR'] ?? 'unknown').'
      </td></tr>
    </table>';

    $ok = send_templated_mail(
      MAIL_FROM,
      'Green Credit redemption request — '.$partner,
      'New Green Credit claim',
      'New redemption request submitted',
      $metaTable,
      'Open Dashboard', route('dashboard')
    );

    if (!$ok) throw new Exception('Failed to submit claim. Please try again later.');

    // Acknowledgement to user
    $ackHtml = '<p style="margin:0 0 12px 0;">Hi '.$safe($name).',</p>
      <p style="margin:0 0 12px 0;">We received your <strong>Green Credit</strong> redemption request.</p>
      <p style="margin:0 0 12px 0;">
        <strong>Points:</strong> '.(int)$points.'<br>
        <strong>Partner:</strong> '.$safe($partner).'<br>
        <strong>Method:</strong> '.$safe($method).'
      </p>
      <p style="margin:0 0 12px 0;">We’ll review and email you next steps or your voucher code shortly.</p>
      <p style="margin:0;">Track your balance on your <a href="'.route('dashboard').'">Dashboard</a>.</p>';
    @send_templated_mail($email, 'We got your Green Credit claim — SORA Labs', 'Claim received', 'Claim submitted', $ackHtml, 'Open Dashboard', route('dashboard'));

    $sent = true;
    // Reset form on success
    $phone = $notes = '';
    $points = '';

  } catch (Throwable $e) {
    if (!$errors) $errors['fatal'] = $e->getMessage();
  }
}

theme_head('Claim — SORA Labs');
topbar('claim'); ?>

<style>
  /* Space below fixed top bar so content never hides under it */
  body { padding-top: 20px; }
  @media (max-width: 991.98px) { body { padding-top: 30px; } }

  /* Stronger glass for readability */
  .glass-strong{
    background:
      radial-gradient(1200px 800px at 15% 5%, rgba(108,99,255,.26), transparent 60%),
      radial-gradient(1000px 700px at 85% 25%, rgba(0,231,255,.24), transparent 60%),
      radial-gradient(900px 700px at 40% 95%, rgba(255,110,199,.22), transparent 60%),
      rgba(18,22,30,.92);
    border: 1px solid rgba(255,255,255,0.16);
    color: #f4f8ff;
    backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
    border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,.35);
  }
  .glass-strong .form-control, .glass-strong .form-select, .glass-strong .form-check-input{
    background-color: rgba(255,255,255,.08); color:#f4f8ff; border-color: rgba(255,255,255,.22);
  }
  .glass-strong .form-control::placeholder{ color:#e6eeff; opacity:.9; }
  .glass-strong .form-control:focus, .glass-strong .form-select:focus{
    box-shadow: 0 0 0 .25rem rgba(99,179,237,.25); border-color:#84c5ff;
  }
  .btn-ghost{ background:rgba(255,255,255,.06); color:#f4f8ff; border:1px solid rgba(255,255,255,.22); }
  .btn-ghost:hover{ background:rgba(255,255,255,.12); }
  .small-dim{ color:#cfd9e6; opacity:.95; }

  .card-lite{ background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.18); color:#f4f8ff; }
  .is-invalid{ border-color:#ff6b6b !important; }
  .invalid-feedback{ display:block; }
  .badge-gc{ background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; border:0; }
</style>

<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-10">

      <!-- Header -->
      <section class="glass-strong p-4 p-md-5 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <span class="dot" aria-hidden="true"></span>
            <h1 class="h4 mb-0 brand">Claim Your Green Credit</h1>
          </div>
          <span class="badge text-bg-dark border border-1 border-light-subtle">Redeem & save</span>
        </div>
        <p class="small small-dim mb-0 mt-2">
          Turn your verified tree contributions into rewards at partner shops and initiatives.
        </p>
      </section>

      <div class="row g-4">
        <!-- Left: How it works + Where to use -->
        <div class="col-12 col-lg-6">
          <section class="glass-strong p-4 mb-4">
            <h2 class="h5 mb-3">How claiming works</h2>
            <ol class="mb-3 small">
              <li><strong>Earn credits</strong> by uploading clear photos of newly planted trees. Photos are verified automatically.</li>
              <li><strong>Track your balance</strong> on the Dashboard. Credits may take a moment to appear after verification.</li>
              <li><strong>Submit a claim</strong> below with your preferred partner and delivery method.</li>
              <li><strong>Get your reward</strong> — usually a digital voucher or in-store pickup confirmation.</li>
            </ol>
            <div class="alert alert-info border-0 py-2 small mb-0">
              <i class="bi bi-info-circle me-1"></i>
              Program terms and minimum redemption amounts may apply. We’ll confirm by email.
            </div>
          </section>

          <section class="glass-strong p-4">
            <h2 class="h5 mb-3">Where you can use your points</h2>
            <ul class="mb-3 small">
              <li><strong>Local shops</strong> — tools, planters, soil, eco-friendly goods.</li>
              <li><strong>Nurseries</strong> — saplings, fertilizers, garden accessories.</li>
              <li><strong>Community drives</strong> — redeem to fund neighborhood planting events.</li>
            </ul>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge text-bg-dark border border-light-subtle">GreenMart</span>
              <span class="badge text-bg-dark border border-light-subtle">Eco Bazar</span>
              <span class="badge text-bg-dark border border-light-subtle">City Nursery</span>
              <span class="badge text-bg-dark border border-light-subtle">Urban Hardware</span>
              <span class="badge text-bg-dark border border-light-subtle">Community Co-op</span>
            </div>
          </section>
        </div>

        <!-- Right: Your balance + Claim form -->
        <div class="col-12 col-lg-6">
          <section class="glass-strong p-4 mb-4">
            <h2 class="h5 mb-3">Your Green Credit</h2>
            <?php if ($isLoggedIn): ?>
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars($me['first_name'] ?: $me['username']) ?></div>
                  <div class="small small-dim"><?= htmlspecialchars($me['email']) ?></div>
                </div>
                <div>
                  <span class="badge badge-gc fs-6 px-3 py-2"><?= (int)$currentCredit ?> pts</span>
                </div>
              </div>
              <div class="mt-2 small small-dim">
                View all credit activity in your <a href="<?= route('dashboard') ?>">Dashboard</a>.
              </div>
            <?php else: ?>
              <div class="alert alert-warning border-0 mb-2">
                <i class="bi bi-person-exclamation me-1"></i> Please log in to see your balance.
              </div>
              <a href="<?= route('login') ?>" class="btn btn-light btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Login / Registration</a>
            <?php endif; ?>
          </section>

          <section class="glass-strong p-4">
            <h2 class="h5 mb-3">Start a claim</h2>

            <?php if ($sent): ?>
              <div class="alert alert-success border-0">
                <i class="bi bi-check2-circle me-1"></i>
                Your claim request was submitted. We’ll email you next steps shortly.
              </div>
            <?php elseif (!empty($errors['fatal'])): ?>
              <div class="alert alert-danger border-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?= htmlspecialchars($errors['fatal']) ?>
              </div>
            <?php endif; ?>

            <form method="post" novalidate class="mt-2">
              <?= csrf_field() ?>
              <!-- Honeypot -->
              <input type="text" name="homepage" value="" autocomplete="off" style="position:absolute;left:-5000px;width:1px;height:1px;opacity:0;" tabindex="-1" aria-hidden="true">

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input type="text" name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>" value="<?= htmlspecialchars($name) ?>" placeholder="Your full name" required>
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
                  <label class="form-label">Phone (optional)</label>
                  <input type="text" name="phone" class="form-control <?= isset($errors['phone'])?'is-invalid':'' ?>" value="<?= htmlspecialchars($phone) ?>" placeholder="+8801XXXXXXXXX">
                  <?php if(isset($errors['phone'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['phone']) ?></div><?php endif; ?>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Partner</label>
                  <select name="partner" class="form-select <?= isset($errors['partner'])?'is-invalid':'' ?>">
                    <?php foreach ($partners as $p): ?>
                      <option <?= $partner===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if(isset($errors['partner'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['partner']) ?></div><?php endif; ?>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Delivery method</label>
                  <select name="method" class="form-select <?= isset($errors['method'])?'is-invalid':'' ?>">
                    <?php foreach ($methods as $m): ?>
                      <option <?= $method===$m?'selected':'' ?>><?= htmlspecialchars($m) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if(isset($errors['method'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['method']) ?></div><?php endif; ?>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Points to redeem</label>
                  <input type="number" name="points" min="1" step="1" class="form-control <?= isset($errors['points'])?'is-invalid':'' ?>" value="<?= htmlspecialchars($points) ?>" placeholder="e.g., 10" required>
                  <?php if(isset($errors['points'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['points']) ?></div><?php endif; ?>
                </div>

                <div class="col-12">
                  <label class="form-label">Notes (optional)</label>
                  <textarea name="notes" rows="4" class="form-control <?= isset($errors['notes'])?'is-invalid':'' ?>" placeholder="Any preference or special instruction…"><?= htmlspecialchars($notes) ?></textarea>
                  <?php if(isset($errors['notes'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['notes']) ?></div><?php endif; ?>
                </div>

                <div class="col-12 d-flex gap-2">
                  <button type="submit" class="btn btn-light">
                    <i class="bi bi-patch-check-fill me-1"></i>Submit claim
                  </button>
                  <a href="<?= route('faq') ?>" class="btn btn-ghost"><i class="bi bi-question-circle me-1"></i>FAQ</a>
                  <a href="<?= route('contact') ?>" class="btn btn-ghost"><i class="bi bi-envelope me-1"></i>Contact</a>
                </div>
              </div>
            </form>
          </section>
        </div>
      </div>

      <!-- Program notes -->
      <section class="glass-strong p-4 mt-2">
        <h2 class="h6 mb-2">Program notes</h2>
        <ul class="small mb-0">
          <li>Claims are reviewed to confirm eligibility and current balance; approved claims may be fulfilled as digital vouchers or pickup confirmations.</li>
          <li>Points are non-transferable and may expire in accordance with program policy.</li>
          <li>Partner availability can change. We’ll suggest alternatives if your selected partner is unavailable.</li>
        </ul>
      </section>

    </div>
  </div>
</div>

<?php theme_foot();
