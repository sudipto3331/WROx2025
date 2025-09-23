<?php
require_once __DIR__.'/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // Basic validation
  $username   = trim($_POST['username'] ?? '');
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name  = trim($_POST['last_name'] ?? '');
  $email      = trim($_POST['email'] ?? '');
  $password   = $_POST['password'] ?? '';
  $nid        = trim($_POST['nid'] ?? '');
  $gender     = $_POST['gender'] ?? null;
  $age_group  = trim($_POST['age_group'] ?? '');
  $dob        = $_POST['dob'] ?? null;
  $profession = trim($_POST['profession'] ?? '');
  $address    = trim($_POST['address'] ?? '');
  $terms      = isset($_POST['terms']) ? 1 : 0;

  $errors = [];

  if ($username === '' || !preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) $errors[] = 'Username must be 3–32 chars (letters, numbers, _ . -).';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
  if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
  if (!$terms) $errors[] = 'You must agree to Terms & Conditions.';

  // Unique checks
  $pdo = db();
  $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = :email OR username = :username");
  $stmt->execute([':email'=>$email, ':username'=>$username]);
  if ($stmt->fetch()) $errors[] = 'Username or email already exists.';

  // Handle uploads (optional multiple)
  $docPaths = [];
  if (!empty($_FILES['docs']) && is_array($_FILES['docs']['name'])) {
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
    $allowed = ['application/pdf','image/jpeg','image/png','image/webp'];
    for ($i=0; $i<count($_FILES['docs']['name']); $i++) {
      if ($_FILES['docs']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
      if ($_FILES['docs']['error'][$i] !== UPLOAD_ERR_OK) { $errors[] = 'Upload error for document #'.($i+1); continue; }
      $type = mime_content_type($_FILES['docs']['tmp_name'][$i]);
      if (!in_array($type, $allowed)) { $errors[] = 'Unsupported file type for document #'.($i+1); continue; }
      if ($_FILES['docs']['size'][$i] > 5*1024*1024) { $errors[] = 'File too large (max 5MB) for document #'.($i+1); continue; }
      $ext = pathinfo($_FILES['docs']['name'][$i], PATHINFO_EXTENSION);
      $name = 'doc_'.date('YmdHis').'_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext ?: 'bin');
      $dest = UPLOAD_DIR . '/' . $name;
      if (!move_uploaded_file($_FILES['docs']['tmp_name'][$i], $dest)) { $errors[] = 'Failed to move uploaded file #'.($i+1); continue; }
      $docPaths[] = 'uploads/'.$name;
    }
  }

  if (empty($errors)) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $token = token64();
    $exp   = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO users
      (username, email, password_hash, first_name, last_name, nid, terms_accepted, gender, age_group, dob, profession, address, other_docs, email_verified, verification_token, verification_expires)
      VALUES (:username, :email, :hash, :first, :last, :nid, :terms, :gender, :ageg, :dob, :prof, :addr, :docs, 0, :token, :exp)");
    $stmt->execute([
      ':username'=>$username, ':email'=>$email, ':hash'=>$hash,
      ':first'=>$first_name ?: null, ':last'=>$last_name ?: null, ':nid'=>$nid ?: null,
      ':terms'=>$terms, ':gender'=>$gender ?: null, ':ageg'=>$age_group ?: null,
      ':dob'=>($dob ?: null), ':prof'=>$profession ?: null, ':addr'=>$address ?: null,
      ':docs'=> $docPaths ? json_encode($docPaths, JSON_UNESCAPED_SLASHES) : null,
      ':token'=>$token, ':exp'=>$exp
    ]);

    // Send verification email
    $vlink = base_url().'/verify.php?token='.$token;
    $html = '<p>Hi '.htmlspecialchars($first_name ?: $username).',</p>
             <p>Verify your email by clicking the button below:</p>
             <p><a href="'.$vlink.'" style="background:#4f46e5;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;">Verify Email</a></p>
             <p>Or open this link: <br><a href="'.$vlink.'">'.$vlink.'</a></p>
             <p>This link expires in 24 hours.</p>';

    send_mail_html($email, 'Verify your email — SORA Labs', $html);

    flash('success', 'Registration successful. Please check your email to verify your account.');
    header('Location: register.php');
    exit;
  } else {
    flash('error', implode('<br>', $errors));
    header('Location: register.php');
    exit;
  }
}

theme_head('Register — SORA Labs');
topbar(''); ?>
<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <section class="glass p-4 p-md-5">
        <h1 class="h4 mb-3">Create your account</h1>
        <?php if ($m = flash('success')): ?>
          <div class="alert alert-success"><?= $m ?></div>
        <?php endif; if ($e = flash('error')): ?>
          <div class="alert alert-danger"><?= $e ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <?= csrf_field() ?>
          <div class="col-md-6">
            <label class="form-label">Username *</label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">First name</label>
            <input type="text" name="first_name" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Last name</label>
            <input type="text" name="last_name" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" minlength="8" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">NID (optional)</label>
            <input type="text" name="nid" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select">
              <option value="">— Select —</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
              <option value="prefer_not">Prefer not to say</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Age group (optional)</label>
            <input type="text" name="age_group" class="form-control" placeholder="e.g. 18-24">
          </div>
          <div class="col-md-4">
            <label class="form-label">DOB (optional)</label>
            <input type="date" name="dob" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Profession (optional)</label>
            <input type="text" name="profession" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Address (optional)</label>
            <input type="text" name="address" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">Other necessary documents (PDF/JPG/PNG/WebP, max 5MB each)</label>
            <input type="file" name="docs[]" class="form-control" multiple>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" name="terms" id="terms" required>
              <label class="form-check-label" for="terms">
                I agree to the <a href="#" target="_blank">Terms &amp; Conditions</a> *
              </label>
            </div>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-light"><i class="bi bi-person-plus me-1"></i> Register</button>
            <a class="btn btn-ghost" href="login.php">Already have an account? Login</a>
          </div>
        </form>
      </section>
    </div>
  </div>
</div>
<?php theme_foot();
