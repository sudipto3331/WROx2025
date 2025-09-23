<?php
// leaderboard.php — Ranked by green_credit, with per-user contribution history
// Theme matches the View History cards
require_once __DIR__.'/config.php';

$pdo = db();

// If a specific user is requested, show their verified history
$viewUserId = isset($_GET['user']) ? max(0, intval($_GET['user'])) : 0;

if ($viewUserId > 0) {
  // Fetch user
  $uStmt = $pdo->prepare("
    SELECT id, username, first_name, last_name, email, green_credit, created_at, last_login
    FROM users WHERE id = :id
  ");
  $uStmt->execute([':id'=>$viewUserId]);
  $user = $uStmt->fetch();

  if ($user) {
    // Verified contributions only
    $cStmt = $pdo->prepare("
      SELECT id, task_level, required_trees, img_path, gps_lat, gps_lng,
             confidence, credit_awarded, created_at
      FROM tree_submissions
      WHERE user_id = :uid AND verified = 1
      ORDER BY created_at DESC
    ");
    $cStmt->execute([':uid'=>$viewUserId]);
    $contribs = $cStmt->fetchAll();
  }
} else {
  // Leaderboard (top 100)
  $q = trim($_GET['q'] ?? '');
  $where = '';
  $params = [];
  if ($q !== '') {
    $where = "WHERE (u.username LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q OR u.email LIKE :q)";
    $params[':q'] = "%$q%";
  }

  $sql = "
    SELECT
      u.id, u.username, u.first_name, u.last_name, u.email,
      u.green_credit, u.created_at, u.last_login,
      COALESCE(tstats.cnt_verified, 0) AS verified_count,
      tstats.last_verified
    FROM users u
    LEFT JOIN (
      SELECT user_id, COUNT(*) AS cnt_verified, MAX(created_at) AS last_verified
      FROM tree_submissions
      WHERE verified = 1
      GROUP BY user_id
    ) tstats ON tstats.user_id = u.id
    $where
    ORDER BY
      u.green_credit DESC,
      (tstats.last_verified IS NULL) ASC,
      tstats.last_verified DESC,
      u.created_at ASC
    LIMIT 100
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();
}

theme_head('Leaderboard — SORA Labs');
topbar('leaderboard');
?>
<style>
  /* ===== Match “View History” Theme ===== */
  .section-glass { border-radius: 20px; }

  /* Glassy cards with neon gradients (same as history) */
  .card-glass {
    background:
      radial-gradient(900px 600px at 15% 0%, rgba(108,99,255,.22), transparent 60%),
      radial-gradient(700px 500px at 100% 15%, rgba(0,231,255,.18), transparent 60%),
      rgba(18,22,30,.72);
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.04);
    color: #e9eef5;
  }
  .glass-plain {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 16px;
  }

  .small-dim { color: #b9c7d6; }

  .avatar {
    width: 46px; height: 46px; border-radius: 50%;
    background: rgba(255,255,255,.10);
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 800; color: #e9eef5;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
  }

  /* FORCE all badge text that might default to dark → white for visibility */
  .badge-gc {
    background: linear-gradient(135deg,#34d399,#10b981);
    color: #ffffff; /* was near-black */
    font-weight: 800;
    border-radius: 10px;
    text-shadow: 0 1px 2px rgba(0,0,0,.35);
  }
  /* Bootstrap warning/light badges use dark text by default; override to white */
  .badge.text-bg-warning,
  .badge.text-bg-light {
    color: #ffffff !important;
    text-shadow: 0 1px 2px rgba(0,0,0,.35);
  }

  /* Table styled like the theme cards */
  .table-glass {
    --bs-table-bg: transparent;
    color: #e9eef5;
  }
  .table-wrap {
    background:
      radial-gradient(700px 400px at 20% -20%, rgba(108,99,255,.16), transparent 60%),
      rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,.30), inset 0 0 0 1px rgba(255,255,255,.03);
  }
  .table-glass thead th {
    color: #e9eef5;
    border-color: rgba(255,255,255,.15);
  }
  .table-glass tbody tr {
    background: rgba(255,255,255,.03);
  }
  .table-glass tbody tr:hover {
    background: rgba(255,255,255,.08);
  }
  .table-glass td, .table-glass th {
    border-color: rgba(255,255,255,.12);
  }

  .img-cover { object-fit: cover; width: 100%; max-height: 220px; border-radius: 16px 16px 0 0; }

  .btn-ghost { background: rgba(255,255,255,.06); color: #e9eef5; border:1px solid rgba(255,255,255,.2); }
  .btn-ghost:hover { background: rgba(255,255,255,.12); }

  @media (max-width: 575.98px) {
    .table-responsive::-webkit-scrollbar { height: 8px; }
    .table-responsive::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 999px; }
  }

    /* Off-white text in leaderboard rows */
    :root { --offwhite: #f4f8ff; }

    .table-glass tbody td,
    .table-glass tbody td.fw-bold,
    .table-glass tbody td .fw-semibold,
    .table-glass tbody td .small,
    .table-glass tbody td .small-dim {
    color: var(--offwhite) !important;
    }

</style>

<div class="container my-4">
  <?php if ($viewUserId && !empty($user)): ?>
    <div class="row justify-content-center">
      <div class="col-12 col-xl-10">
        <section class="glass section-glass p-4 p-md-5">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
              <h1 class="h4 mb-1">Contributor Details</h1>
              <div class="text-secondary small">From the global leaderboard</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-ghost" href="<?= htmlspecialchars(route('leaderboard')) ?>"><i class="bi bi-arrow-left me-1"></i>Back to Leaderboard</a>
              <a class="btn btn-light" href="<?= htmlspecialchars(route('dashboard')) ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="card-glass p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                  <div class="avatar">
                    <?= strtoupper(htmlspecialchars(mb_substr((trim($user['first_name'].' '.$user['last_name']) ?: ($user['username'] ?: 'U')),0,1))) ?>
                  </div>
                  <div>
                    <div class="fw-semibold">
                      <?= htmlspecialchars(trim($user['first_name'].' '.$user['last_name']) ?: ($user['username'] ?: 'User #'.$user['id'])) ?>
                    </div>
                    <div class="small-dim"><?= htmlspecialchars($user['email']) ?></div>
                  </div>
                </div>

                <div class="row g-3 mt-2">
                  <div class="col-6">
                    <div class="glass-plain p-2">
                      <div class="small small-dim">Total Green Credit</div>
                      <div class="fs-5 fw-bold"><span class="badge badge-gc"> <?= (int)$user['green_credit'] ?> </span></div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="glass-plain p-2">
                      <div class="small small-dim">Verified Submissions</div>
                      <div class="fs-5 fw-bold">
                        <?= isset($contribs) ? (int)count($contribs) : 0 ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-3 small small-dim">
                  Member since: <?= htmlspecialchars($user['created_at'] ?: '—') ?><br>
                  Last login: <?= htmlspecialchars($user['last_login'] ?: '—') ?>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="card-glass p-3 h-100">
                <div class="fw-semibold mb-2">About Green Credit</div>
                <div class="small small-dim">
                  Credits are added when photo evidence of a newly planted tree is successfully verified.
                  Each entry below shows the tree photo, location, confidence and points awarded.
                </div>
              </div>
            </div>
          </div>

          <hr class="border-secondary-subtle my-4">

          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <div class="fw-semibold">Contribution History</div>
            <div class="small small-dim">Showing verified entries only</div>
          </div>

          <?php if (empty($contribs)): ?>
            <div class="alert alert-dark border-0">No verified contributions yet.</div>
          <?php else: ?>
            <div class="row g-3">
            <?php foreach ($contribs as $c): ?>
              <?php
                $badge = 'success';
                if ($c['task_level'] === 'yellow') $badge = 'warning';
                if ($c['task_level'] === 'red')    $badge = 'danger';
                $lat = (float)$c['gps_lat']; $lng = (float)$c['gps_lng'];
                $osm = 'https://www.openstreetmap.org/?mlat='.rawurlencode((string)$lat).'&mlon='.rawurlencode((string)$lng).'#map=16/'.rawurlencode((string)$lat).'/'.rawurlencode((string)$lng);
              ?>
              <div class="col-12 col-md-6 col-lg-4">
                <div class="card-glass p-0 h-100 d-flex flex-column">
                  <?php if (!empty($c['img_path'])): ?>
                    <a href="<?= htmlspecialchars($c['img_path']) ?>" target="_blank" rel="noopener" class="d-block">
                      <img src="<?= htmlspecialchars($c['img_path']) ?>" class="img-cover" alt="Tree photo">
                    </a>
                  <?php endif; ?>
                  <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between">
                      <span class="badge text-bg-<?= $badge ?> text-uppercase"><?= htmlspecialchars($c['task_level']) ?></span>
                      <span class="small small-dim"><?= htmlspecialchars($c['created_at']) ?></span>
                    </div>
                    <div class="mt-2 small">
                      <div>Location:
                        <a href="<?= htmlspecialchars($osm) ?>" target="_blank" class="link-light text-decoration-underline">
                          <?= number_format($lat,5) ?>, <?= number_format($lng,5) ?>
                        </a>
                      </div>
                      <div>Confidence: <strong><?= $c['confidence']!==null ? htmlspecialchars($c['confidence']).'%' : '—' ?></strong></div>
                      <div>Points: <strong>+<?= (int)$c['credit_awarded'] ?></strong></div>
                      <div class="small small-dim">Required: <?= (int)$c['required_trees'] ?> tree(s)</div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </div>

  <?php else: ?>
    <div class="row justify-content-center">
      <div class="col-12 col-xl-10">
        <section class="glass section-glass p-4 p-md-5">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
              <h1 class="h4 mb-1">Leaderboard</h1>
              <div class="text-secondary small">Top contributors ranked by Green Credit</div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <form method="get" class="d-flex" role="search">
                <input class="form-control me-2" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search by name/email">
                <button class="btn btn-ghost" type="submit"><i class="bi bi-search"></i></button>
              </form>
              <a class="btn btn-light" href="<?= htmlspecialchars(route('dashboard')) ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
            </div>
          </div>

          <?php if (empty($rows)): ?>
            <div class="alert alert-dark border-0">No users found.</div>
          <?php else: ?>
            <div class="table-responsive table-wrap p-2">
              <table class="table align-middle table-glass mb-0">
                <thead>
                  <tr>
                    <th style="width:72px;">Rank</th>
                    <th>User</th>
                    <th class="text-center">Green Credit</th>
                    <th class="d-none d-sm-table-cell">Verified</th>
                    <th class="d-none d-md-table-cell">Last Contribution</th>
                    <th class="d-none d-lg-table-cell">Member Since</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                <?php
                  $rank = 0; $prevScore = null; $displayRank = 0;
                  foreach ($rows as $r):
                    $rank++;
                    if ($prevScore === null || $prevScore != $r['green_credit']) {
                      $displayRank = $rank;
                      $prevScore = $r['green_credit'];
                    }
                    $name = trim(($r['first_name'].' '.$r['last_name'])) ?: ($r['username'] ?: ('User #'.$r['id']));
                    $lastVer = $r['last_verified'] ?: '—';
                    $userUrl = 'leaderboard.php?user='.(int)$r['id'];
                ?>
                  <tr>
                    <td class="fw-bold"><?= $displayRank ?></td>
                    <td>
                      <div class="d-flex align-items-center gap-3">
                        <div class="avatar"><?= strtoupper(htmlspecialchars(mb_substr($name,0,1))) ?></div>
                        <div>
                          <div class="fw-semibold"><?= htmlspecialchars($name) ?></div>
                          <div class="small small-dim"><?= htmlspecialchars($r['email']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="text-center">
                      <span class="badge badge-gc fs-6"><?= (int)$r['green_credit'] ?></span>
                    </td>
                    <td class="d-none d-sm-table-cell"><?= (int)$r['verified_count'] ?></td>
                    <td class="d-none d-md-table-cell"><?= htmlspecialchars($lastVer) ?></td>
                    <td class="d-none d-lg-table-cell"><?= htmlspecialchars($r['created_at']) ?></td>
                    <td class="text-end">
                      <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($userUrl) ?>">
                        <i class="bi bi-person-lines-fill me-1"></i>View History
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="small small-dim mt-2">Showing top 100 users.</div>
          <?php endif; ?>
        </section>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php theme_foot();
