<?php
// claim.php — Green Credit shop + how-to-claim (SORA Labs theme)
require_once __DIR__.'/config.php';

/**
 * Assumptions:
 * - config.php provides: db(), csrf_check(), csrf_field(), route($name),
 *   rate_limit($key,$count,$seconds), send_templated_mail($to,$subj,$pre,$title,$html,$cta,$url),
 *   theme_head($title), topbar($active), theme_foot()
 */

// ---------------------------------
// AUTH + USER
// ---------------------------------
$isLoggedIn = !empty($_SESSION['user_id']);
$me = null;
$currentCredit = 0;

if ($isLoggedIn) {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, green_credit FROM users WHERE id=:id");
  $stmt->execute([':id'=>$_SESSION['user_id']]);
  $me = $stmt->fetch() ?: null;
  $currentCredit = (int)($me['green_credit'] ?? 0);
}

// ---------------------------------
// PARTNERS / PRODUCTS (WearDHK)
// ---------------------------------
$wearDhkProducts = [
  // sku, name, credits, img, short description
  ['sku'=>'WDHK-TEE-CLASSIC', 'name'=>'WearDHK Classic T-Shirt', 'credits'=>1, 'img'=>'/assets/partners/weardhk/classic.jpg', 'desc'=>'Soft cotton • Unisex'],
  ['sku'=>'WDHK-TEE-BAMBOO',  'name'=>'Bamboo Blend Tee',       'credits'=>160, 'img'=>'/assets/partners/weardhk/bamboo.jpg',  'desc'=>'Breathable bamboo • Unisex'],
  ['sku'=>'WDHK-HOODIE',      'name'=>'Logo Hoodie',            'credits'=>260, 'img'=>'/assets/partners/weardhk/hoodie.png',   'desc'=>'Mid-weight fleece • Unisex'],
  ['sku'=>'WDHK-CAP',         'name'=>'Embroidered Cap',        'credits'=>120, 'img'=>'/assets/partners/weardhk/cap.webp',     'desc'=>'Adjustable strapback'],
  ['sku'=>'WDHK-TOTE',        'name'=>'Organic Tote Bag',       'credits'=>90,  'img'=>'/assets/partners/weardhk/bag.webp',     'desc'=>'Heavy canvas • Reusable'],
];
// Index by SKU for secure lookup during checkout
$PRODUCTS_BY_SKU = [];
foreach ($wearDhkProducts as $p) { $PRODUCTS_BY_SKU[$p['sku']] = $p; }

// ---------------------------------
// TABLES: Orders + Status Log (create/upgrade if needed)
// ---------------------------------
try {
  $pdoInit = db();
  $pdoInit->exec("
    CREATE TABLE IF NOT EXISTS gc_orders (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      user_email VARCHAR(190) NOT NULL,
      sku VARCHAR(60) NOT NULL,
      product_name VARCHAR(190) NOT NULL,
      unit_credits INT NOT NULL,
      qty INT NOT NULL DEFAULT 1,
      total_credits INT NOT NULL,
      full_name VARCHAR(120) NOT NULL,
      phone VARCHAR(40) NOT NULL,
      address_line1 VARCHAR(190) NOT NULL,
      address_line2 VARCHAR(190) NULL,
      city VARCHAR(80) NOT NULL,
      postcode VARCHAR(20) NULL,
      notes VARCHAR(1000) NULL,
      status ENUM('new','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'new',
      status_note VARCHAR(1000) NULL,
      courier VARCHAR(80) NULL,
      tracking_code VARCHAR(120) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      status_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX(user_id), INDEX(sku), INDEX(status), INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
  $pdoInit->exec("
    CREATE TABLE IF NOT EXISTS gc_order_status_log (
      id INT AUTO_INCREMENT PRIMARY KEY,
      order_id INT NOT NULL,
      old_status ENUM('new','processing','shipped','delivered','cancelled') NULL,
      new_status ENUM('new','processing','shipped','delivered','cancelled') NOT NULL,
      note VARCHAR(1000) NULL,
      actor_type ENUM('system','admin','user') NOT NULL DEFAULT 'system',
      actor_id INT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX(order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Throwable $e) { /* ignore bootstrap errors */ }

// ---------------------------------
// STATE for forms (manual claim kept)
// ---------------------------------
$errors = [];
$sent   = false;

// Manual claim defaults (kept)
$name        = $isLoggedIn ? ($me['first_name'] ?: $me['username']) : '';
$email       = $isLoggedIn ? ($me['email'] ?? '') : '';
$phone       = '';
$partner     = 'GreenMart';
$method      = 'Digital voucher (email)';
$points      = '';
$notes       = '';
$partners = ['GreenMart', 'Eco Bazar', 'City Nursery', 'Urban Hardware', 'Community Co-op', 'WearDHK'];
$methods  = ['Digital voucher (email)', 'In-store pickup', 'Home delivery (where available)'];

// ---------------------------------
// POST: WearDHK product redemption
// ---------------------------------
$orderSuccess = false; $orderMsg = ''; $newOrderId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'redeem_product') {
  try {
    csrf_check();
    if (!$isLoggedIn) throw new Exception('Please log in to redeem products.');
    if (!rate_limit('claim_buy', 5, 300)) throw new Exception('Too many orders. Please try again in a few minutes.');
    if (!empty($_POST['homepage_bp'])) throw new Exception('Spam detected.');

    // Inputs
    $sku   = trim($_POST['sku'] ?? '');
    $qty   = (int)($_POST['qty'] ?? 1);
    $full  = trim($_POST['full_name'] ?? ($me['first_name'] ?: $me['username']));
    $ph    = trim($_POST['phone'] ?? '');
    $a1    = trim($_POST['address_line1'] ?? '');
    $a2    = trim($_POST['address_line2'] ?? '');
    $city  = trim($_POST['city'] ?? '');
    $zip   = trim($_POST['postcode'] ?? '');
    $n2    = trim($_POST['notes_ship'] ?? '');

    // Validate product/qty
    if (!$sku || !isset($PRODUCTS_BY_SKU[$sku])) throw new Exception('Invalid product.');
    $qty = max(1, min(5, $qty)); // 1..5

    // Validate shipping/contact
    if (mb_strlen($full) < 2 || mb_strlen($full) > 120) throw new Exception('Enter a valid full name.');
    if (!preg_match('/^[0-9+\-\s()]{6,25}$/', $ph))     throw new Exception('Enter a valid phone.');
    if (mb_strlen($a1) < 6 || mb_strlen($a1) > 190)     throw new Exception('Enter a valid address line 1.');
    if (mb_strlen($city) < 2 || mb_strlen($city) > 80)  throw new Exception('Enter a valid city.');
    if (mb_strlen($a2) > 190)                            throw new Exception('Address line 2 is too long.');
    if (mb_strlen($zip) > 20)                            throw new Exception('Postcode is too long.');
    if (mb_strlen($n2) > 1000)                           throw new Exception('Notes are too long.');

    $prod  = $PRODUCTS_BY_SKU[$sku];
    $unit  = (int)$prod['credits'];
    $total = $unit * $qty;

    // Atomic deduction + order
    $pdo = db();
    $pdo->beginTransaction();

    $st = $pdo->prepare("SELECT green_credit, email FROM users WHERE id=:id FOR UPDATE");
    $st->execute([':id'=>$_SESSION['user_id']]);
    $row = $st->fetch();
    if (!$row) throw new Exception('Account not found.');
    $balance   = (int)$row['green_credit'];
    $acctEmail = $row['email'];

    if ($balance < $total) throw new Exception('Insufficient Green Credit for this order.');

    // Deduct credits
    $upd = $pdo->prepare("UPDATE users SET green_credit = green_credit - :c WHERE id=:id");
    $upd->execute([':c'=>$total, ':id'=>$_SESSION['user_id']]);

    // Log deduction
    $log = $pdo->prepare("INSERT INTO green_credit_log (user_id, delta, reason) VALUES (:uid, :delta, :reason)");
    $log->execute([
      ':uid'=>$_SESSION['user_id'],
      ':delta'=> -$total,
      ':reason'=>"WearDHK order {$sku} x{$qty}"
    ]);

    // Create order
    $ins = $pdo->prepare("
      INSERT INTO gc_orders
        (user_id, user_email, sku, product_name, unit_credits, qty, total_credits,
         full_name, phone, address_line1, address_line2, city, postcode, notes, status)
      VALUES
        (:uid, :email, :sku, :pname, :unit, :qty, :total,
         :fullname, :phone, :a1, :a2, :city, :zip, :notes, 'new')
    ");
    $ins->execute([
      ':uid'=>$_SESSION['user_id'],
      ':email'=>$acctEmail,
      ':sku'=>$sku,
      ':pname'=>$prod['name'],
      ':unit'=>$unit,
      ':qty'=>$qty,
      ':total'=>$total,
      ':fullname'=>$full,
      ':phone'=>$ph,
      ':a1'=>$a1,
      ':a2'=>$a2 ?: null,
      ':city'=>$city,
      ':zip'=>$zip ?: null,
      ':notes'=>$n2 ?: null,
    ]);
    $newOrderId = (int)$pdo->lastInsertId();

    // Audit status log (optional)
    $pdo->prepare("INSERT INTO gc_order_status_log (order_id, old_status, new_status, note, actor_type, actor_id)
                   VALUES (:oid, NULL, 'new', 'Order placed', 'user', :uid)")
        ->execute([':oid'=>$newOrderId, ':uid'=>$_SESSION['user_id']]);

    $pdo->commit();

    // Update page memory of balance
    $currentCredit = max(0, $balance - $total);

    // Emails
    $safe = fn($s)=>htmlspecialchars($s ?? '', ENT_QUOTES);
    $summaryTable = '
      <table cellpadding="0" cellspacing="0" border="0" style="font-size:14px;line-height:22px;color:#d7e2ee;">
        <tr><td><strong>Order #:</strong> '.$newOrderId.'</td></tr>
        <tr><td><strong>Product:</strong> '.$safe($prod['name']).' ('.$safe($sku).')</td></tr>
        <tr><td><strong>Qty:</strong> '.$qty.'</td></tr>
        <tr><td><strong>Total:</strong> '.$total.' Green Credit</td></tr>
        <tr><td style="padding-top:8px;"><strong>Ship to:</strong><br>'.
            $safe($full).'<br>'.
            $safe($a1).($a2?'<br>'.$safe($a2):'').'<br>'.
            $safe($city).($zip?' '.$safe($zip):'').'<br>'.
            'Phone: '.$safe($ph).
        '</td></tr>'.
        ($n2 ? '<tr><td style="padding-top:8px;"><strong>Notes:</strong><br>'.$safe(nl2br($n2)).'</td></tr>' : '').
      '</table>';

    @send_templated_mail(
      $acctEmail,
      'Your WearDHK order (Green Credit) — SORA Labs',
      'Order received',
      'Thanks for redeeming with Green Credit',
      $summaryTable,
      'View Dashboard',
      route('dashboard')
    );
    @send_templated_mail(
      'info@soralabs.cc',
      'New WearDHK GC order #'.$newOrderId,
      'Shipment needed',
      'Prepare WearDHK fulfillment',
      $summaryTable,
      'Admin Dashboard',
      route('dashboard')
    );

    $orderSuccess = true;
    $orderMsg = "Order placed successfully. {$total} credits deducted.";

  } catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $orderSuccess = false;
    $orderMsg = $e->getMessage();
  }
}

// ---------------------------------
// POST: Existing manual claim form (kept)
// ---------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'manual_claim') {
  try {
    csrf_check();
    if (!empty($_POST['homepage'])) throw new Exception('Spam detected.');
    if (!rate_limit('claim_form', 3, 300)) throw new Exception('Too many requests. Please try again in a few minutes.');

    // Collect inputs
    $name    = trim($_POST['name'] ?? $name);
    $phone   = trim($_POST['phone'] ?? '');
    $partner = trim($_POST['partner'] ?? $partner);
    $method  = trim($_POST['method'] ?? $method);
    $points  = trim($_POST['points'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');

    // Email: account or guest
    if ($isLoggedIn) { $email = $me['email'] ?? ''; }
    else { $email = trim($_POST['email'] ?? ''); }

    // Validate
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80)           $errors['name'] = 'Please provide your name (2–80 chars).';
    if (!$isLoggedIn) {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors['email'] = 'Please enter a valid email address.';
    } else {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Your account email looks invalid.';
    }
    if ($phone && !preg_match('/^[0-9+\-\s()]{6,25}$/', $phone))  $errors['phone'] = 'Please enter a valid phone.';
    if (!in_array($partner, $partners, true))                     $errors['partner'] = 'Please select a partner.';
    if (!in_array($method, $methods, true))                       $errors['method'] = 'Please select a delivery method.';
    if ($points === '' || !ctype_digit($points) || (int)$points <= 0) $errors['points'] = 'Enter a positive number of points.';
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
    $phone = $notes = '';
    $points = '';
  } catch (Throwable $e) {
    if (!$errors) $errors['fatal'] = $e->getMessage();
  }
}

// ---------------------------------
// PAGE
// ---------------------------------
theme_head('Claim — SORA Labs');
topbar('claim');
?>
<style>
  body { padding-top: 20px; }
  @media (max-width: 991.98px) { body { padding-top: 30px; } }

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
  .badge-gc{ background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; border:0; }

  /* Product grid */
  .product-card{
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 16px;
    overflow: hidden;
    color: #f4f8ff;
  }
  .product-thumb{
    background: linear-gradient(135deg, rgba(108,99,255,.25), rgba(0,231,255,.2));
    aspect-ratio: 4/3; width: 100%; object-fit: cover; display:block;
  }
  .credit-pill{
    display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px;
    background: rgba(22, 163, 74, .22); border: 1px solid rgba(34, 197, 94, .5);
    font-weight: 600;
  }
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
          Turn verified tree contributions into real products with our partner <strong>WearDHK</strong>.
        </p>
      </section>

      <!-- Balance + Order flash -->
      <div class="row g-4">
        <div class="col-12 col-lg-6">
          <section class="glass-strong p-4 h-100">
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
                View all activity in your <a href="<?= route('dashboard') ?>">Dashboard</a>.
              </div>
            <?php else: ?>
              <div class="alert alert-warning border-0 mb-2">
                <i class="bi bi-person-exclamation me-1"></i> Please log in to redeem with Green Credit.
              </div>
              <a href="<?= route('login') ?>" class="btn btn-light btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Login / Registration</a>
            <?php endif; ?>

            <?php if ($orderMsg): ?>
              <div class="alert <?= $orderSuccess ? 'alert-success' : 'alert-danger' ?> border-0 mt-3">
                <?= htmlspecialchars($orderMsg) ?><?= $orderSuccess && $newOrderId ? ' (Order #'.(int)$newOrderId.')' : '' ?>
              </div>
            <?php endif; ?>
          </section>
        </div>

        <div class="col-12 col-lg-6">
          <section class="glass-strong p-4 h-100">
            <h2 class="h5 mb-3">How it works</h2>
            <ol class="mb-3 small">
              <li><strong>Browse products</strong> from WearDHK below. Each item shows the Green Credit needed.</li>
              <li><strong>Redeem</strong> (credits are deducted on confirmation).</li>
              <li><strong>We email you</strong> an order receipt and also notify our team for shipment & tracking.</li>
            </ol>
            <div class="alert alert-info border-0 py-2 small mb-0">
              <i class="bi bi-info-circle me-1"></i>
              Shipping is available within Bangladesh. Sizes/colors are confirmed by email if needed.
            </div>
          </section>
        </div>
      </div>

      <!-- WearDHK Product Grid -->
      <section class="glass-strong p-4 mt-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <h2 class="h5 mb-0"><i class="bi bi-bag-heart me-2"></i>WearDHK Products</h2>
          <a class="btn btn-ghost btn-sm" href="https://weardhk.com/all-products" target="_blank" rel="noopener">View all at WearDHK</a>
        </div>

        <div class="row g-3">
          <?php foreach ($wearDhkProducts as $p): ?>
            <div class="col-12 col-sm-6 col-lg-4">
              <div class="product-card">
                <img src="<?= htmlspecialchars($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-thumb" onerror="this.style.opacity=0.9;">
                <div class="p-3">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                      <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                      <div class="small small-dim"><?= htmlspecialchars($p['desc']) ?></div>
                    </div>
                    <span class="credit-pill"><?= (int)$p['credits'] ?> pts</span>
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-2 mt-3">
                    <div class="small small-dim">SKU: <?= htmlspecialchars($p['sku']) ?></div>
                    <?php if ($isLoggedIn): ?>
                      <button
                        class="btn btn-light btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#redeemModal"
                        data-sku="<?= htmlspecialchars($p['sku']) ?>"
                        data-name="<?= htmlspecialchars($p['name']) ?>"
                        data-credits="<?= (int)$p['credits'] ?>"
                      >
                        Redeem
                      </button>
                    <?php else: ?>
                      <a href="<?= route('login') ?>" class="btn btn-light btn-sm">Login to Redeem</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Manual Claim (kept) -->
      <section class="glass-strong p-4 mt-4">
        <h2 class="h5 mb-3">Manual claim (vouchers / partners)</h2>

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
          <input type="hidden" name="action" value="manual_claim">
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
              <input type="number" name="points" min="1" step="1" class="form-control <?= isset($errors['points'])?'is-invalid':'' ?>" value="<?= htmlspecialchars($points) ?>" placeholder="e.g., 100" required>
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

      <!-- Program notes -->
      <section class="glass-strong p-4 mt-4">
        <h2 class="h6 mb-2">Program notes</h2>
        <ul class="small mb-0">
          <li>Orders deduct Green Credit immediately and are non-refundable. If a size/color is unavailable, we’ll contact you to adjust or refund credits.</li>
          <li>Delivery usually within 3–7 business days (Bangladesh). You’ll receive shipment updates by email.</li>
          <li>Manual claims and partner availability can change. We’ll suggest alternatives where needed.</li>
        </ul>
      </section>

    </div>
  </div>
</div>

<!-- Redeem Modal (WearDHK) -->
<div class="modal fade" id="redeemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content glass-strong">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="redeem_product">
      <!-- honeypot -->
      <input type="text" name="homepage_bp" value="" autocomplete="off" style="position:absolute;left:-5000px;width:1px;height:1px;opacity:0;" tabindex="-1" aria-hidden="true">

      <div class="modal-header border-0">
        <h5 class="modal-title"><i class="bi bi-bag-check-fill me-2"></i>Redeem Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter:invert(1)"></button>
      </div>
      <div class="modal-body pt-0">
        <?php if (!$isLoggedIn): ?>
          <div class="alert alert-warning border-0 mb-0">Please log in to redeem.</div>
        <?php else: ?>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Product</label>
              <input type="text" id="rm_product_name" class="form-control" value="" disabled>
              <input type="hidden" id="rm_sku" name="sku">
            </div>
            <div class="col-6">
              <label class="form-label">Unit (pts)</label>
              <input type="text" id="rm_unit" class="form-control" value="" disabled>
              <input type="hidden" id="rm_unit_hidden" value="">
            </div>
            <div class="col-6">
              <label class="form-label">Quantity</label>
              <select name="qty" id="rm_qty" class="form-select">
                <?php for($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
              </select>
            </div>

            <div class="col-12">
              <div class="small small-dim">Shipping address</div>
            </div>

            <div class="col-12">
              <label class="form-label">Full name</label>
              <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($me['first_name'] ?: $me['username']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" placeholder="+8801XXXXXXXXX" required>
            </div>
            <div class="col-12">
              <label class="form-label">Address line 1</label>
              <input type="text" name="address_line1" class="form-control" placeholder="House, road, area" required>
            </div>
            <div class="col-12">
              <label class="form-label">Address line 2 (optional)</label>
              <input type="text" name="address_line2" class="form-control" placeholder="Apartment, landmark">
            </div>
            <div class="col-md-6">
              <label class="form-label">City</label>
              <input type="text" name="city" class="form-control" placeholder="Dhaka" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Postcode (optional)</label>
              <input type="text" name="postcode" class="form-control" placeholder="">
            </div>
            <div class="col-12">
              <label class="form-label">Notes for delivery (optional)</label>
              <textarea name="notes_ship" class="form-control" rows="2" placeholder="Size/color, leave with guard, etc."></textarea>
            </div>

            <div class="col-12">
              <div class="alert alert-dark border-0">
                <i class="bi bi-calculator me-1"></i>
                <span id="rm_total">Total: — pts</span>
                <div class="small mt-1">Your balance: <strong><?= (int)$currentCredit ?></strong> pts</div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-0">
        <?php if ($isLoggedIn): ?>
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-light"><i class="bi bi-patch-check-fill me-1"></i>Confirm & Deduct</button>
        <?php else: ?>
          <a href="<?= route('login') ?>" class="btn btn-light">Login</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script>
  // Fill Redeem modal with product data
  const redeemModal = document.getElementById('redeemModal');
  if (redeemModal) {
    redeemModal.addEventListener('show.bs.modal', (ev) => {
      const btn = ev.relatedTarget;
      if (!btn) return;

      const sku = btn.getAttribute('data-sku');
      const name = btn.getAttribute('data-name');
      const unit = parseInt(btn.getAttribute('data-credits') || '0', 10);

      const skuInp = redeemModal.querySelector('#rm_sku');
      const nameInp = redeemModal.querySelector('#rm_product_name');
      const unitInp = redeemModal.querySelector('#rm_unit');
      const unitHidden = redeemModal.querySelector('#rm_unit_hidden');
      const qtySel = redeemModal.querySelector('#rm_qty');
      const totalLbl = redeemModal.querySelector('#rm_total');

      skuInp.value = sku;
      nameInp.value = name;
      unitInp.value = unit + ' pts';
      unitHidden.value = String(unit);
      qtySel.value = '1';
      totalLbl.textContent = 'Total: ' + unit + ' pts';

      const updateTotal = () => {
        const u = parseInt(unitHidden.value || '0', 10);
        const q = parseInt(qtySel.value || '1', 10);
        totalLbl.textContent = 'Total: ' + (u*q) + ' pts';
      };
      qtySel.onchange = updateTotal;
    });
  }
</script>

<?php theme_foot();
