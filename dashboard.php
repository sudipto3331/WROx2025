<?php
require_once __DIR__.'/config.php';
require_login();

$pdo = db();
$stmt = $pdo->prepare("SELECT username, email, first_name, last_name, created_at, last_login FROM users WHERE id=:id");
$stmt->execute([':id'=>$_SESSION['user_id']]);
$me = $stmt->fetch();

theme_head('Dashboard — SORA Labs');
topbar(''); ?>
<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-10">
      <section class="glass p-4 p-md-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h1 class="h4 mb-0">Dashboard</h1>
          <div class="d-flex gap-2">
            <a class="btn btn-ghost" href="zone_dashboard_leaflet.php"><i class="bi bi-map me-1"></i>Zone Map</a>
            <a class="btn btn-light" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="glass p-3">
              <div class="fw-semibold">Welcome, <?= htmlspecialchars($me['first_name'] ?: $me['username']) ?>!</div>
              <div class="text-secondary small">Email: <?= htmlspecialchars($me['email']) ?></div>
              <div class="text-secondary small">Member since: <?= htmlspecialchars($me['created_at']) ?></div>
              <div class="text-secondary small">Last login: <?= htmlspecialchars($me['last_login'] ?: '—') ?></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="glass p-3">
              <div class="fw-semibold mb-2">Shortcuts</div>
              <a class="btn btn-ghost me-2 mb-2" href="zone_dashboard_leaflet.php"><i class="bi bi-geo-alt me-1"></i> Open Zone Dashboard</a>
              <a class="btn btn-ghost me-2 mb-2" href="#"><i class="bi bi-gear me-1"></i> Account Settings</a>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
<?php theme_foot();
