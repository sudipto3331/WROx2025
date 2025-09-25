<?php
require_once __DIR__.'/config.php';
require_login();

$pdo = db();

/** -------------------------------------------------
 *  USER PROFILE
 * -------------------------------------------------*/
$stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, created_at, last_login, green_credit FROM users WHERE id=:id");
$stmt->execute([':id'=>$_SESSION['user_id']]);
$me = $stmt->fetch();

/** -------------------------------------------------
 *  SUBMISSION HISTORY (newest first)
 * -------------------------------------------------*/
$hist = [];
try {
  $q = $pdo->prepare("
    SELECT id, task_level, img_path, gps_lat, gps_lng, verified, verifier, confidence, credit_awarded, created_at
    FROM tree_submissions
    WHERE user_id = :uid
    ORDER BY created_at DESC
    LIMIT 500
  ");
  $q->execute([':uid'=>$_SESSION['user_id']]);
  $hist = $q->fetchAll();
} catch (Throwable $e) {
  $hist = [];
}

/** Map-friendly subset */
$hist_map = [];
foreach ($hist as $h) {
  if ($h['gps_lat'] !== null && $h['gps_lng'] !== null) {
    $hist_map[] = [
      'id'         => (int)$h['id'],
      'lat'        => (float)$h['gps_lat'],
      'lng'        => (float)$h['gps_lng'],
      'verified'   => (int)$h['verified'] === 1,
      'credit'     => (int)$h['credit_awarded'],
      'level'      => (string)($h['task_level'] ?? ''),
      'img'        => (string)($h['img_path'] ?? ''),
      'conf'       => is_null($h['confidence']) ? null : (float)$h['confidence'],
      'verifier'   => (string)($h['verifier'] ?? ''),
      'created_at' => (string)($h['created_at'] ?? '')
    ];
  }
}

/** -------------------------------------------------
 *  ORDERS (gc_orders)
 * -------------------------------------------------*/
$orders = [];
try {
  $qo = $pdo->prepare("
    SELECT id, created_at, sku, product_name, unit_credits, qty, total_credits, status,
           full_name, phone, address_line1, address_line2, city, postcode, notes
    FROM gc_orders
    WHERE user_id = :uid
    ORDER BY created_at DESC, id DESC
  ");
  $qo->execute([':uid'=>$_SESSION['user_id']]);
  $orders = $qo->fetchAll();
} catch (Throwable $e) {
  $orders = [];
}

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

theme_head('Dashboard — SORA Labs');
topbar(''); ?>
<style>
  /* Base colorful glass (desktop) */
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

  /* Nav tabs (desktop defaults) */
  .nav-tabs { border-bottom: 1px solid rgba(255,255,255,0.18); }
  .nav-tabs .nav-link {
    color: #eaf2ff; border: 1px solid transparent;
    border-top-left-radius: .5rem; border-top-right-radius: .5rem;
  }
  .nav-tabs .nav-link:hover { border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.06); }
  .nav-tabs .nav-link.active {
    color: #111827; background: #ffffff; border-color: #dee2e6 #dee2e6 #fff;
  }

  /* History entry cards */
  .entry-card {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 14px;
    overflow: hidden;
  }
  .entry-card .meta { color: #eaf2ff; }
  .badge-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
  .badge-ok     { background:#22c55e; }
  .badge-warn   { background:#eab308; }
  .badge-danger { background:#ef4444; }

  /* Orders table: force dark/glass look */
  .table-darkish{
    --bs-table-bg: transparent;
    --bs-table-color: #f4f8ff;
    --bs-table-border-color: rgba(255,255,255,.14);
    --bs-table-striped-bg: rgba(255,255,255,.06);
    --bs-table-striped-color: #f4f8ff;
    --bs-table-hover-bg: rgba(255,255,255,.10);
    --bs-table-hover-color: #ffffff;
  }
  .table-darkish > :not(caption) > * > *{
    background-color: transparent !important;
    color: #f4f8ff !important;
    border-color: rgba(255,255,255,.14) !important;
  }
  .table-darkish thead th{
    background: rgba(255,255,255,.08) !important;
    color: #ffffff !important;
    border-bottom: 1px solid rgba(255,255,255,.22) !important;
  }
  .table-darkish td .small,
  .table-darkish td .text-secondary{
    color: #dbe8ff !important;
  }

  /* Status badges */
  .badge-status{ font-weight:600; letter-spacing:.2px; }
  .status-new        { background: linear-gradient(135deg,#6366f1,#22d3ee); color:#fff; }
  .status-processing { background: linear-gradient(135deg,#f59e0b,#f97316); color:#fff; }
  .status-shipped    { background: linear-gradient(135deg,#16a34a,#22c55e); color:#fff; }
  .status-cancelled  { background: linear-gradient(135deg,#475569,#1f2937); color:#fff; }

  /* Mobile contrast fix (including tabs) */
  @media (max-width: 991.98px) {
    .glass,
    .card,
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
    .nav-tabs {
      border-bottom: 1px solid rgba(255,255,255,0.18) !important;
    }
    .nav-tabs .nav-link {
      color: #f4f8ff !important;
      background: rgba(255,255,255,0.06) !important;
      border-color: rgba(255,255,255,0.18) !important;
      margin-right: 6px;
    }
    .nav-tabs .nav-link.active {
      color: #ffffff !important;
      background: rgba(255,255,255,0.16) !important;
      border-color: rgba(255,255,255,0.28) !important;
    }
    .text-secondary,
    .form-text { color: #dae8ff !important; }
  }

  /* Map tab styles */
  #contribMap { height: 520px; border-radius: 14px; overflow: hidden; }
  .leaflet-container { background: transparent; }

  /* High-visibility plant icon */
  .plant-marker {
    width: 30px; height: 30px; line-height: 28px;
    text-align: center; font-size: 20px;
    border-radius: 50%;
    background: #16a34a;
    border: 2px solid #bbf7d0;
    box-shadow: 0 0 0 2px rgba(0,0,0,.25), 0 8px 18px rgba(0,0,0,.45);
    color: #fff;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,.5));
  }
  .plant-marker .emoji { position: relative; top: -1px; }

  .legend-card {
    position: absolute; bottom: 12px; left: 12px; z-index: 500;
    padding: 8px 10px;
    border-radius: 10px;
    background:
      radial-gradient(400px 300px at 60% -10%, rgba(108,99,255,.35), transparent 60%),
      rgba(18,22,30,.85);
    border: 1px solid rgba(255,255,255,.18);
    color: #eaf2ff;
    backdrop-filter: blur(10px);
  }
  .legend-card .dot {
    display:inline-block; width:10px; height:10px; border-radius:50%;
    background:#ef4444; margin-right:6px; /* red for other submissions */
  }
</style>

<!-- Leaflet CSS (in case your theme doesn't include it) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-10">
      <section class="glass p-4 p-md-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h1 class="h4 mb-0">Dashboard</h1>
          <div class="d-flex gap-2">
            <a class="btn btn-ghost" href="./"><i class="bi bi-map me-1"></i>Zone Map</a>
            <a class="btn btn-light" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
          </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="dashTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="true">
              Overview
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
              History
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="contrib-tab" data-bs-toggle="tab" data-bs-target="#contrib" type="button" role="tab" aria-controls="contrib" aria-selected="false">
              View Contribution (Map)
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab" aria-controls="orders" aria-selected="false">
              Orders
            </button>
          </li>
        </ul>

        <div class="tab-content pt-3">
          <!-- OVERVIEW -->
          <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="glass p-3 h-100">
                  <div class="fw-semibold">Welcome, <?= esc($me['first_name'] ?: $me['username']) ?>!</div>
                  <div class="text-secondary small">Email: <?= esc($me['email']) ?></div>
                  <div class="text-secondary small">Member since: <?= esc($me['created_at']) ?></div>
                  <div class="text-secondary small">Last login: <?= esc($me['last_login'] ?: '—') ?></div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="glass p-3 h-100 d-flex flex-column">
                  <div class="fw-semibold mb-2">Green Credit</div>
                  <div class="display-5 fw-bold"><?= (int)($me['green_credit'] ?? 0) ?></div>
                  <div class="text-secondary small mb-3">Total points collected</div>
                  <div class="mt-auto">
                    <a class="btn btn-ghost me-2 mb-2" href="./"><i class="bi bi-geo-alt me-1"></i> Open Zone Dashboard</a>
                    <button id="btnViewHistory" class="btn btn-ghost mb-2" type="button"><i class="bi bi-clock-history me-1"></i> View History</button>
                  </div>
                </div>
              </div>

              <div class="col-12">
                <div class="glass p-3">
                  <div class="fw-semibold mb-2">Shortcuts</div>
                  <a class="btn btn-ghost me-2 mb-2" href="settings.php"><i class="bi bi-gear me-1"></i> Account Settings</a>
                  <a class="btn btn-ghost me-2 mb-2" href="claim.php"><i class="bi bi-bag-heart me-1"></i> Claim / Shop</a>
                </div>
              </div>
            </div>
          </div>

          <!-- HISTORY -->
          <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
            <?php if (empty($hist)): ?>
              <div class="text-secondary">No submissions yet. Plant a tree from the <a class="link-light" href="/index.php">Zone Map</a> to get started.</div>
            <?php else: ?>
              <div class="row g-3">
                <?php foreach ($hist as $h):
                  $img = $h['img_path'] ?? '';
                  $imgUrl = esc((strpos($img, 'http') === 0 ? $img : '/'.ltrim($img,'/')));
                  $lat = is_null($h['gps_lat']) ? null : (float)$h['gps_lat'];
                  $lng = is_null($h['gps_lng']) ? null : (float)$h['gps_lng'];
                  $latStr = $lat !== null ? number_format($lat, 5) : '—';
                  $lngStr = $lng !== null ? number_format($lng, 5) : '—';
                  $level = $h['task_level'] ?? '—';
                  $conf  = $h['confidence'] !== null ? rtrim(rtrim(number_format((float)$h['confidence'],2), '0'),'.') : '—';
                  $points= (int)($h['credit_awarded'] ?? 0);
                  $ver   = $h['verifier'] ?: '—';
                  $status= ((int)$h['verified'] === 1 && $points > 0) ? 'Verified' : 'Not verified';
                  $badgeClass = ((int)$h['verified'] === 1 && $points > 0) ? 'badge-ok' : 'badge-danger';
                  $mapLink = ($lat !== null && $lng !== null)
                    ? 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng.'#map=18/'.$lat.'/'.$lng
                    : '#';
                ?>
                <div class="col-12 col-md-6">
                  <div class="entry-card d-flex">
                    <div style="width: 120px; background:#000; flex:0 0 120px;">
                      <?php if (!empty($img)): ?>
                        <img src="<?= $imgUrl ?>" alt="Tree photo" style="width:120px;height:100%;object-fit:cover;">
                      <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center text-white-50" style="width:120px;height:100%;">No Image</div>
                      <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 p-3">
                      <div class="d-flex align-items-center justify-content-between">
                        <div>
                          <span class="badge-dot <?= $badgeClass ?>"></span>
                          <span class="fw-semibold"><?= esc(ucfirst($status)) ?></span>
                          <span class="text-secondary small ms-2"><?= esc(strtoupper($level)) ?></span>
                        </div>
                        <div class="fw-semibold">+<?= $points ?></div>
                      </div>
                      <div class="meta small mt-1">
                        <div>Confidence: <?= esc($conf) ?>% • Verifier: <?= esc($ver) ?></div>
                        <div>GPS: <?= $latStr ?>, <?= $lngStr ?> <?php if ($lat !== null && $lng !== null): ?>• <a class="link-light" target="_blank" href="<?= esc($mapLink) ?>">View map</a><?php endif; ?></div>
                        <div>On: <?= esc($h['created_at']) ?></div>
                      </div>
                      <div class="mt-2 text-secondary small">
                        Reason: <?= ((int)$h['verified'] === 1 && $points > 0) ? "Tree verified ({$level})" : "Failed / Pending" ?>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- CONTRIBUTION MAP -->
          <div class="tab-pane fade" id="contrib" role="tabpanel" aria-labelledby="contrib-tab">
            <div class="glass p-3">
              <div class="mb-2 d-flex align-items-center justify-content-between">
                <div class="fw-semibold">Your Contributions</div>
                <div class="text-secondary small"><?= count($hist_map) ?> location(s)</div>
              </div>
              <div class="position-relative">
                <div id="contribMap"></div>
                <div class="legend-card">
                  <div><span class="plant-marker"><span class="emoji">🌱</span></span> Verified & credited</div>
                  <div class="mt-1"><span class="dot"></span> Other submissions</div>
                </div>
              </div>
              <?php if (empty($hist_map)): ?>
                <div class="text-secondary small mt-2">No GPS-tagged submissions yet.</div>
              <?php endif; ?>
            </div>
          </div>

          <!-- ORDERS -->
          <div class="tab-pane fade" id="orders" role="tabpanel" aria-labelledby="orders-tab">
            <div class="glass p-3">
              <div class="d-flex align-items-center justify-content-between">
                <h2 class="h5 mb-0">Your Orders</h2>
                <div class="text-secondary small"><?= count($orders) ?> order(s)</div>
              </div>
              <?php if (empty($orders)): ?>
                <div class="text-secondary mt-2">No orders yet. Redeem from the <a class="link-light" href="<?= route('claim') ?>">Claim</a> page.</div>
              <?php else: ?>
                <div class="table-responsive mt-3">
                  <table class="table table-hover table-striped align-middle mb-0 table-darkish">
                    <thead>
                      <tr>
                        <th style="width:56px;">#</th>
                        <th style="min-width:160px;">When</th>
                        <th style="min-width:220px;">Product</th>
                        <th>Qty</th>
                        <th>Total (pts)</th>
                        <th>Status</th>
                        <th style="width:120px;">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                        $i = 1;
                        foreach ($orders as $o):
                          $status = strtolower($o['status']);
                          $badgeCls = 'status-new';
                          if ($status === 'processing') $badgeCls = 'status-processing';
                          elseif ($status === 'shipped') $badgeCls = 'status-shipped';
                          elseif ($status === 'cancelled') $badgeCls = 'status-cancelled';
                      ?>
                        <tr>
                          <td class="fw-bold"><?= $i++ ?></td>
                          <td><?= esc($o['created_at']) ?></td>
                          <td>
                            <div class="fw-semibold"><?= esc($o['product_name']) ?></div>
                            <div class="small text-secondary">SKU: <?= esc($o['sku']) ?></div>
                            <div class="small text-secondary">Unit: <?= (int)$o['unit_credits'] ?> pts</div>
                          </td>
                          <td><?= (int)$o['qty'] ?></td>
                          <td class="fw-semibold"><?= (int)$o['total_credits'] ?></td>
                          <td>
                            <span class="badge badge-status <?= $badgeCls ?>"><?= strtoupper(esc($o['status'])) ?></span>
                          </td>
                          <td>
                            <button
                              class="btn btn-ghost btn-sm"
                              data-bs-toggle="modal"
                              data-bs-target="#orderModal"
                              data-id="<?= (int)$o['id'] ?>"
                              data-when="<?= esc($o['created_at']) ?>"
                              data-sku="<?= esc($o['sku']) ?>"
                              data-name="<?= esc($o['product_name']) ?>"
                              data-qty="<?= (int)$o['qty'] ?>"
                              data-unit="<?= (int)$o['unit_credits'] ?>"
                              data-total="<?= (int)$o['total_credits'] ?>"
                              data-status="<?= strtoupper(esc($o['status'])) ?>"
                              data-full="<?= esc($o['full_name']) ?>"
                              data-phone="<?= esc($o['phone']) ?>"
                              data-a1="<?= esc($o['address_line1']) ?>"
                              data-a2="<?= esc($o['address_line2']) ?>"
                              data-city="<?= esc($o['city']) ?>"
                              data-zip="<?= esc($o['postcode']) ?>"
                              data-notes="<?= esc($o['notes']) ?>"
                            >
                              <i class="bi bi-eye me-1"></i>View
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </section>
    </div>
  </div>
</div>

<!-- Leaflet JS (in case your theme doesn't include it) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Order Details Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass p-1">
      <div class="modal-header border-0">
        <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Order Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter:invert(1)"></button>
      </div>
      <div class="modal-body pt-0">
        <div class="row g-2">
          <div class="col-12">
            <div class="small text-secondary">Order #</div>
            <div id="om_id" class="fw-semibold">—</div>
          </div>
          <div class="col-md-6">
            <div class="small text-secondary">When</div>
            <div id="om_when">—</div>
          </div>
          <div class="col-md-6">
            <div class="small text-secondary">Status</div>
            <div id="om_status" class="fw-semibold">—</div>
          </div>

          <div class="col-12 mt-2">
            <div class="small text-secondary">Product</div>
            <div class="fw-semibold" id="om_name">—</div>
            <div class="small text-secondary">SKU: <span id="om_sku">—</span></div>
          </div>

          <div class="col-md-4">
            <div class="small text-secondary">Qty</div>
            <div id="om_qty">—</div>
          </div>
          <div class="col-md-4">
            <div class="small text-secondary">Unit</div>
            <div id="om_unit">—</div>
          </div>
          <div class="col-md-4">
            <div class="small text-secondary">Total</div>
            <div id="om_total" class="fw-semibold">—</div>
          </div>

          <div class="col-12 mt-2">
            <div class="small text-secondary">Ship to</div>
            <div id="om_full">—</div>
            <div id="om_a1">—</div>
            <div id="om_a2"></div>
            <div><span id="om_city">—</span> <span id="om_zip"></span></div>
            <div>Phone: <span id="om_phone">—</span></div>
          </div>

          <div class="col-12 mt-2">
            <div class="small text-secondary">Notes</div>
            <div id="om_notes">—</div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <a href="<?= route('claim') ?>" class="btn btn-ghost"><i class="bi bi-bag-heart me-1"></i>Shop more</a>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  // Open History or Map or Orders tab via hash
  document.addEventListener('DOMContentLoaded', () => {
    const hashToTab = {
      '#history': '#history',
      '#contrib': '#contrib',
      '#orders':  '#orders'
    };
    const desired = hashToTab[location.hash];
    if (desired) {
      const btn = document.querySelector(`[data-bs-target="${desired}"]`);
      if (btn) new bootstrap.Tab(btn).show();
    }
  });

  // "View History" button activates the History tab
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnViewHistory');
    const historyTabTrigger = document.getElementById('history-tab');
    if (btn && historyTabTrigger) {
      btn.addEventListener('click', () => {
        new bootstrap.Tab(historyTabTrigger).show();
        history.replaceState(null, '', '#history');
      });
    }
  });

  // Data for map from PHP
  const contributions = <?= json_encode($hist_map, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;

  let contribMap = null;
  let contribMapInited = false;

  function initContribMap() {
    if (contribMapInited) return;
    contribMapInited = true;

    contribMap = L.map('contribMap', { zoomControl: true }).setView([23.7808875, 90.2792371], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 20,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
    }).addTo(contribMap);

    const plantIcon = L.divIcon({
      className: 'plant-marker',
      html: '<span class="emoji">🌱</span>',
      iconSize: [30, 30],
      iconAnchor: [15, 15]
    });

    const plantBounds = [];
    const allBounds = [];

    contributions.forEach(c => {
      if (typeof c.lat !== 'number' || typeof c.lng !== 'number') return;

      if (c.verified && c.credit > 0) {
        const m = L.marker([c.lat, c.lng], { icon: plantIcon }).addTo(contribMap);
        m.bindPopup(renderPopup(c), { maxWidth: 280 });
        plantBounds.push([c.lat, c.lng]);
      } else {
        const dot = L.circleMarker([c.lat, c.lng], {
          radius: 5, color: '#ef4444', weight: 1.5, opacity: 0.95,
          fillColor: '#ef4444', fillOpacity: 0.55
        }).addTo(contribMap);
        dot.bindPopup(renderPopup(c), { maxWidth: 280 });
      }
      allBounds.push([c.lat, c.lng]);
    });

    if (plantBounds.length > 0) {
      if (plantBounds.length === 1) {
        contribMap.setView(plantBounds[0], 17);
      } else {
        try { contribMap.fitBounds(plantBounds, { padding: [40, 40] }); } catch (e) {}
      }
    } else if (allBounds.length > 0) {
      if (allBounds.length === 1) {
        contribMap.setView(allBounds[0], 14);
      } else {
        try { contribMap.fitBounds(allBounds, { padding: [30, 30] }); } catch (e) {}
      }
    }
  }

  function renderPopup(c) {
    const img = c.img ? (c.img.startsWith('http') ? c.img : ('/' + c.img.replace(/^\/+/,''))) : '';
    const conf = (c.conf != null) ? (c.conf + '%') : '—';
    const gps  = `${Number(c.lat).toFixed(5)}, ${Number(c.lng).toFixed(5)}`;
    const when = c.created_at || '—';
    const status = (c.verified && c.credit > 0) ? 'Verified' : 'Not verified';
    const level  = (c.level || '').toUpperCase();
    const mapLink = `https://www.openstreetmap.org/?mlat=${c.lat}&mlon=${c.lng}#map=18/${c.lat}/${c.lng}`;
    return `
      <div style="min-width:200px">
        <div class="fw-semibold mb-1">${status} <span class="text-secondary small ms-1">${level}</span></div>
        <div class="small text-secondary mb-1">Confidence: ${conf} • Points: +${c.credit}</div>
        <div class="small">GPS: ${gps} • <a target="_blank" href="${mapLink}">View map</a></div>
        <div class="small text-secondary mb-2">On: ${when}</div>
        ${img ? `<img src="${img}" alt="Tree" style="width:100%;height:120px;object-fit:cover;border-radius:8px;">` : ''}
      </div>
    `;
  }

  document.addEventListener('shown.bs.tab', (ev) => {
    if (ev.target && ev.target.id === 'contrib-tab') {
      initContribMap();
      setTimeout(() => { if (contribMap) contribMap.invalidateSize(true); }, 200);
    }
  });

  // Orders: fill modal
  const orderModal = document.getElementById('orderModal');
  if (orderModal) {
    orderModal.addEventListener('show.bs.modal', (ev) => {
      const btn = ev.relatedTarget;
      if (!btn) return;
      const set = (sel, val) => {
        const el = orderModal.querySelector(sel);
        if (el) el.textContent = (val && String(val).trim() !== '') ? val : '—';
      };
      set('#om_id',     btn.getAttribute('data-id'));
      set('#om_when',   btn.getAttribute('data-when'));
      set('#om_status', btn.getAttribute('data-status'));
      set('#om_name',   btn.getAttribute('data-name'));
      set('#om_sku',    btn.getAttribute('data-sku'));
      set('#om_qty',    btn.getAttribute('data-qty'));
      set('#om_unit',   btn.getAttribute('data-unit') + ' pts');
      set('#om_total',  btn.getAttribute('data-total') + ' pts');
      set('#om_full',   btn.getAttribute('data-full'));
      set('#om_a1',     btn.getAttribute('data-a1'));
      set('#om_a2',     btn.getAttribute('data-a2') || '');
      set('#om_city',   btn.getAttribute('data-city'));
      set('#om_zip',    btn.getAttribute('data-zip') || '');
      const notes = btn.getAttribute('data-notes');
      set('#om_notes',  notes && notes.length ? notes : '—');
    });
  }
</script>

<?php theme_foot();
