<?php
// settings.php — Account Settings (glass theme) • SORA Labs
require_once __DIR__.'/config.php';
require_login();

$pdo = db();
$userId = (int)$_SESSION['user_id'];

// ---- Optional: ensure extra columns exist (safe to keep) ----
$maybeAlters = [
  "ALTER TABLE users ADD COLUMN gender VARCHAR(32) NULL",
  "ALTER TABLE users ADD COLUMN phone VARCHAR(32) NULL",
  "ALTER TABLE users ADD COLUMN date_of_birth DATE NULL",
  "ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL",
  "ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL",
  "ALTER TABLE users ADD COLUMN country VARCHAR(100) NULL",
  "ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL"
];
foreach ($maybeAlters as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) {} }

// ---- CSRF token ----
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// ---- Handle POST actions ----
$flash_ok = null; $flash_err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token  = $_POST['csrf'] ?? '';
  if (!hash_equals($csrf, $token)) {
    $flash_err = 'Invalid request token. Please try again.';
  } else {
    if ($action === 'save_profile') {
      // Sanitize inputs
      $username   = trim($_POST['username'] ?? '');
      $first_name = trim($_POST['first_name'] ?? '');
      $last_name  = trim($_POST['last_name'] ?? '');
      $gender     = trim($_POST['gender'] ?? '');
      $gender_other = trim($_POST['gender_other'] ?? '');
      $phone      = trim($_POST['phone'] ?? '');
      $dob        = trim($_POST['date_of_birth'] ?? '');
      $address    = trim($_POST['address'] ?? '');
      $city       = trim($_POST['city'] ?? '');
      $country    = trim($_POST['country'] ?? '');

      // Keep email immutable
      // Validate gender
      $allowedGenders = ['male','female','nonbinary','prefer_not','other'];
      if (!in_array($gender, $allowedGenders, true)) $gender = null;
      if ($gender === 'other' && $gender_other !== '') {
        $gender = substr($gender_other, 0, 32);
      }

      // Validate date (YYYY-MM-DD)
      if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $flash_err = 'Invalid date of birth format (use YYYY-MM-DD).';
      } else {
        try {
          $u = $pdo->prepare("
            UPDATE users SET
              username=:u, first_name=:f, last_name=:l, gender=:g, phone=:p,
              date_of_birth=:dob, address=:a, city=:c, country=:ct
            WHERE id=:id
          ");
          $u->execute([
            ':u'   => ($username !== '' ? $username : null),
            ':f'   => ($first_name !== '' ? $first_name : null),
            ':l'   => ($last_name !== '' ? $last_name : null),
            ':g'   => ($gender ?: null),
            ':p'   => ($phone !== '' ? $phone : null),
            ':dob' => ($dob !== '' ? $dob : null),
            ':a'   => ($address !== '' ? $address : null),
            ':c'   => ($city !== '' ? $city : null),
            ':ct'  => ($country !== '' ? $country : null),
            ':id'  => $userId
          ]);
          $flash_ok = 'Profile updated successfully.';
        } catch (Throwable $e) {
          $flash_err = 'Update failed: '.$e->getMessage();
        }
      }
    } elseif ($action === 'delete_account') {
      $confirm = trim($_POST['confirm_text'] ?? '');
      if ($confirm !== 'DELETE') {
        $flash_err = 'Type DELETE (all caps) to confirm account deletion.';
      } else {
        try {
          $pdo->beginTransaction();
          // Remove user-related rows first (no-op if FKs not present)
          $pdo->prepare("DELETE FROM green_credit_log WHERE user_id=:id")->execute([':id'=>$userId]);
          $pdo->prepare("DELETE FROM tree_submissions WHERE user_id=:id")->execute([':id'=>$userId]);
          $pdo->prepare("DELETE FROM users WHERE id=:id LIMIT 1")->execute([':id'=>$userId]);
          $pdo->commit();

          // Log out and redirect
          session_regenerate_id(true);
          $_SESSION = [];
          if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
              $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
          }
          session_destroy();
          header('Location: /login.php?deleted=1');
          exit;
        } catch (Throwable $e) {
          $pdo->rollBack();
          $flash_err = 'Deletion failed: '.$e->getMessage();
        }
      }
    }
  }
}

// ---- Load user profile (fresh) ----
$stmt = $pdo->prepare("
  SELECT username, email, first_name, last_name, gender, phone, date_of_birth, address, city, country, created_at
  FROM users WHERE id=:id
");
$stmt->execute([':id'=>$userId]);
$me = $stmt->fetch();

// Helpers
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

theme_head('Account Settings — SORA Labs');
topbar('');
?>
<style>
  /* Glass theme (desktop) */
  .glass {
    background:
      radial-gradient(1200px 800px at 20% 10%, rgba(108,99,255,.28), transparent 60%),
      radial-gradient(1000px 700px at 80% 30%, rgba(0,231,255,.22), transparent 60%),
      radial-gradient(900px 700px at 50% 90%, rgba(255,110,199,.24), transparent 60%),
      rgba(18,22,30,.78);
    border: 1px solid rgba(255,255,255,0.16);
    color: #eaf2ff;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-radius: 18px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.35);
  }
  .glass .text-secondary { color: #cfe2ff !important; opacity: .9; }
  .btn-ghost {
    background: rgba(255,255,255,0.06); color: #f4f8ff;
    border: 1px solid rgba(255,255,255,0.22);
  }
  .btn-ghost:hover { background: rgba(255,255,255,0.12); }

  /* Form fields to match dark/glass theme */
  .form-control, .form-select, .form-check-input {
    background-color: rgba(255,255,255,0.06);
    color: #eaf2ff;
    border: 1px solid rgba(255,255,255,0.25);
  }
  .form-control::placeholder { color: #d7e6ff; opacity: 0.6; }
  .form-control:focus, .form-select:focus {
    border-color: #84c5ff;
    box-shadow: 0 0 0 .25rem rgba(99, 179, 237, 0.25);
  }
  .form-text { color: #dae8ff; }

  .badge-soft {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.18);
    color: #eaf2ff;
    border-radius: 999px;
    padding: 6px 10px;
  }

  /* Danger Zone */
  .danger {
    background:
      radial-gradient(800px 500px at 10% 0%, rgba(239,68,68,.28), transparent 60%),
      rgba(18,22,30,.86);
    border: 1px solid rgba(239,68,68,.4);
  }
  .btn-danger-ghost {
    background: rgba(239,68,68,.12);
    color: #ffd4d4;
    border: 1px solid rgba(239,68,68,.45);
  }
  .btn-danger-ghost:hover {
    background: rgba(239,68,68,.22);
  }

  /* Mobile contrast fix */
  @media (max-width: 991.98px) {
    .glass,
    .navbar,
    .topbar,
    .container .glass {
      background:
        radial-gradient(800px 500px at 15% 0%, rgba(108,99,255,.28), transparent 60%),
        radial-gradient(600px 400px at 100% 20%, rgba(0,231,255,.22), transparent 60%),
        rgba(18,22,30,.92) !important;
      border-color: rgba(255,255,255,.16) !important;
      color: #f4f8ff !important;
    }
    .form-text { color: #dae8ff !important; }
  }
</style>

<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-10">
      <section class="glass p-4 p-md-5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
          <h1 class="h4 mb-0">Account Settings</h1>
          <div class="d-flex gap-2">
            <a class="btn btn-ghost" href="/index_plant.php"><i class="bi bi-map me-1"></i>Zone Map</a>
            <a class="btn btn-light" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
          </div>
        </div>

        <?php if ($flash_ok): ?>
          <div class="alert alert-success"><?= h($flash_ok) ?></div>
        <?php elseif ($flash_err): ?>
          <div class="alert alert-danger"><?= h($flash_err) ?></div>
        <?php endif; ?>

        <div class="row g-4">
          <!-- Profile -->
          <div class="col-12 col-lg-8">
            <div class="glass p-3 p-md-4 h-100">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="fw-semibold">Profile</div>
                <span class="badge-soft">Member since: <?= h($me['created_at'] ?? '—') ?></span>
              </div>

              <form method="post" class="row g-3">
                <input type="hidden" name="action" value="save_profile">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                <div class="col-md-6">
                  <label class="form-label">Username</label>
                  <input type="text" class="form-control" name="username" value="<?= h($me['username'] ?? '') ?>" maxlength="50">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email (read-only)</label>
                  <input type="email" class="form-control" value="<?= h($me['email'] ?? '') ?>" readonly>
                  <div class="form-text">Email cannot be changed.</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">First name</label>
                  <input type="text" class="form-control" name="first_name" value="<?= h($me['first_name'] ?? '') ?>" maxlength="80">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Last name</label>
                  <input type="text" class="form-control" name="last_name" value="<?= h($me['last_name'] ?? '') ?>" maxlength="80">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Gender</label>
                  <?php
                    $g = strtolower((string)($me['gender'] ?? ''));
                    $known = in_array($g, ['male','female','nonbinary','prefer_not','other'], true) ? $g : 'other';
                    $otherVal = (!in_array($g, ['male','female','nonbinary','prefer_not'], true) && $g !== '') ? $g : '';
                  ?>
                  <select class="form-select" name="gender" id="genderSelect">
                    <option value="" <?= $known===''?'selected':'';?>>(Select)</option>
                    <option value="male" <?= $known==='male'?'selected':'';?>>Male</option>
                    <option value="female" <?= $known==='female'?'selected':'';?>>Female</option>
                    <option value="nonbinary" <?= $known==='nonbinary'?'selected':'';?>>Non-binary</option>
                    <option value="prefer_not" <?= $known==='prefer_not'?'selected':'';?>>Prefer not to say</option>
                    <option value="other" <?= ($known==='other')?'selected':'';?>>Other</option>
                  </select>
                </div>

                <div class="col-md-6" id="genderOtherWrap" style="display: <?= ($known==='other' && $otherVal!=='')?'block':'none'; ?>">
                  <label class="form-label">Gender (specify)</label>
                  <input type="text" class="form-control" name="gender_other" id="genderOther" value="<?= h($otherVal) ?>" maxlength="32" placeholder="Type here">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" name="phone" value="<?= h($me['phone'] ?? '') ?>" maxlength="32" placeholder="+8801XXXXXXXXX">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Date of birth</label>
                  <input type="date" class="form-control" name="date_of_birth" value="<?= h($me['date_of_birth'] ?? '') ?>">
                </div>

                <div class="col-12">
                  <label class="form-label">Address</label>
                  <input type="text" class="form-control" name="address" value="<?= h($me['address'] ?? '') ?>" maxlength="255" placeholder="Street address">
                </div>

                <div class="col-md-6">
                  <label class="form-label">City</label>
                  <input type="text" class="form-control" name="city" value="<?= h($me['city'] ?? '') ?>" maxlength="100">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Country</label>
                  <input type="text" class="form-control" name="country" value="<?= h($me['country'] ?? '') ?>" maxlength="100">
                </div>

                <div class="col-12 d-flex gap-2 pt-2">
                  <button type="submit" class="btn btn-light"><i class="bi bi-check2-circle me-1"></i>Save changes</button>
                  <a href="dashboard.php" class="btn btn-ghost"><i class="bi bi-speedometer2 me-1"></i>Back to Dashboard</a>
                </div>
              </form>
            </div>
          </div>

          <!-- Side info + Danger zone -->
          <div class="col-12 col-lg-4">
            <div class="glass p-3 p-md-4 mb-4">
              <div class="fw-semibold mb-2">Account</div>
              <div class="text-secondary small mb-2">You can change your personal details except email.</div>
              <div class="text-secondary small">Email: <span class="fw-semibold"><?= h($me['email'] ?? '—') ?></span></div>
            </div>

            <div class="glass danger p-3 p-md-4">
              <div class="d-flex align-items-center justify-content-between">
                <div class="fw-semibold text-danger">Danger Zone</div>
                <i class="bi bi-exclamation-triangle text-danger-emphasis"></i>
              </div>
              <div class="small text-secondary mt-1">
                Permanently delete your account and all associated data (submissions, credits). This action cannot be undone.
              </div>

              <form method="post" class="mt-3">
                <input type="hidden" name="action" value="delete_account">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <label class="form-label">Type <span class="fw-bold">DELETE</span> to confirm</label>
                <input type="text" name="confirm_text" class="form-control" placeholder="DELETE" required>
                <button type="submit" class="btn btn-danger-ghost mt-3">
                  <i class="bi bi-trash3 me-1"></i>Delete my account
                </button>
              </form>
            </div>
          </div>
        </div>

      </section>
    </div>
  </div>
</div>

<script>
  // Show/hide "Gender (specify)" when "Other" is selected
  document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('genderSelect');
    const wrap = document.getElementById('genderOtherWrap');
    const other = document.getElementById('genderOther');
    if (sel) {
      sel.addEventListener('change', () => {
        if (sel.value === 'other') {
          wrap.style.display = 'block';
          if (other) other.focus();
        } else {
          wrap.style.display = 'none';
          if (other) other.value = '';
        }
      });
    }
  });
</script>

<?php theme_foot();
