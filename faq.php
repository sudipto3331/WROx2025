<?php
// faq.php — Frequently Asked Questions (matches SORA Labs theme)
require_once __DIR__.'/config.php';

theme_head('FAQ — SORA Labs');
topbar('faq'); ?>

<style>
  /* Space below fixed top bar so content never hides under it */
  body { padding-top: 20px; }
  @media (max-width: 991.98px) { body { padding-top: 30px; } }

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
  .glass-strong .form-control {
    background-color: rgba(255,255,255,.08);
    color: #f4f8ff;
    border-color: rgba(255,255,255,.22);
  }
  .glass-strong .form-control::placeholder { color: #e6eeff; opacity: .9; }
  .glass-strong .form-control:focus {
    box-shadow: 0 0 0 .25rem rgba(99,179,237,.25);
    border-color: #84c5ff;
  }
  .btn-ghost{ background:rgba(255,255,255,.06); color:#f4f8ff; border:1px solid rgba(255,255,255,.22); }
  .btn-ghost:hover{ background:rgba(255,255,255,.12); }
  .small-dim { color:#cfd9e6; opacity:.95; }

  /* Accordion look */
  .accordion.glass-strong .accordion-item {
    background: transparent; border: 1px solid rgba(255,255,255,.14); border-radius: 14px; overflow: hidden;
  }
  .accordion.glass-strong .accordion-item + .accordion-item { margin-top: 10px; }
  .accordion.glass-strong .accordion-button {
    background: rgba(255,255,255,.06); color:#f4f8ff; font-weight:600;
  }
  .accordion.glass-strong .accordion-button:not(.collapsed) {
    background: rgba(255,255,255,.10); color:#ffffff;
  }
  .accordion.glass-strong .accordion-button:focus {
    border-color: rgba(255,255,255,.22); box-shadow: 0 0 0 .25rem rgba(99,179,237,.25);
  }
  .accordion.glass-strong .accordion-body {
    background: rgba(255,255,255,.04); color:#f4f8ff;
  }

  /* CTA cards */
  .quick-links .card { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.18); color:#f4f8ff; }

  /* Tiny helper to hide non-matching FAQ items during search */
  .faq-item.filtered-out { display: none !important; }
</style>

<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-10">

      <!-- Header / Search -->
      <section class="glass-strong p-4 p-md-5 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <span class="dot" aria-hidden="true"></span>
            <h1 class="h4 mb-0 brand">Frequently Asked Questions</h1>
          </div>
          <span class="badge text-bg-dark border border-1 border-light-subtle">Updated <?= htmlspecialchars(date('M j, Y')) ?></span>
        </div>

        <div class="mt-3">
          <label for="faqSearch" class="form-label small-dim"><i class="bi bi-search me-1"></i>Search FAQs</label>
          <input type="text" id="faqSearch" class="form-control" placeholder="Type a keyword, e.g. credit, verification, GPS, leaderboard…">
        </div>

        <div class="mt-3 small small-dim">
          Quick links:
          <a class="ms-2" href="#faq-credit">Green Credit</a> •
          <a href="#faq-verify">Verification</a> •
          <a href="#faq-tasks">Tasks</a> •
          <a href="#faq-map">Maps & Datasets</a> •
          <a href="#faq-privacy">Privacy</a> •
          <a href="#faq-troubleshoot">Troubleshooting</a>
        </div>
      </section>

      <!-- FAQ Accordion -->
      <section class="glass-strong p-3 p-md-4">
        <div class="accordion glass-strong" id="faqAccordion">

          <!-- What is Green Credit -->
          <div class="accordion-item faq-item" id="faq-credit" data-text="green credit points score how earn award threshold confidence">
            <h2 class="accordion-header" id="headingCredit">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCredit" aria-expanded="false" aria-controls="collapseCredit">
                <i class="bi bi-patch-check-fill me-2"></i> What is Green Credit and how do I earn it?
              </button>
            </h2>
            <div id="collapseCredit" class="accordion-collapse collapse" aria-labelledby="headingCredit" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Green Credit is our community reward for verified tree-planting contributions. When you upload a photo of your planted tree with location, our system checks it using an open plant identification API. If the photo passes the confidence threshold (currently 10%), we automatically add credit to your account. You can review your total and history on your <a href="<?= route('dashboard') ?>">Dashboard</a> and compete on the <a href="<?= route('leaderboard') ?>">Leaderboard</a>.
              </div>
            </div>
          </div>

          <!-- How many points -->
          <div class="accordion-item faq-item" data-text="how many points red yellow credit values configuration">
            <h2 class="accordion-header" id="headingPoints">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePoints" aria-expanded="false" aria-controls="collapsePoints">
                <i class="bi bi-coin me-2"></i> How many points do I get per verified submission?
              </button>
            </h2>
            <div id="collapsePoints" class="accordion-collapse collapse" aria-labelledby="headingPoints" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Points vary by task level at the selected map location:
                <ul class="mb-0">
                  <li><strong>Red area:</strong> +2 Green Credit</li>
                  <li><strong>Yellow area:</strong> +1 Green Credit</li>
                  <li><strong>Green area:</strong> +0 (no action required)</li>
                </ul>
                These values and thresholds can be adjusted by admins; the current setup is reflected in the UI you see.
              </div>
            </div>
          </div>

          <!-- Verification -->
          <div class="accordion-item faq-item" id="faq-verify" data-text="verification plantnet api confidence how it works leaf gps photo rules">
            <h2 class="accordion-header" id="headingVerify">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVerify" aria-expanded="false" aria-controls="collapseVerify">
                <i class="bi bi-shield-check me-2"></i> How does photo verification work?
              </button>
            </h2>
            <div id="collapseVerify" class="accordion-collapse collapse" aria-labelledby="headingVerify" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                We submit your photo to an open plant-identification API and read back a confidence value. If confidence is above the threshold (currently <strong>10%</strong>), your submission is marked verified and credit is added automatically. If GPS is available, we use your device location; otherwise we fallback to the selected map point. Tips for best results:
                <ul class="mb-0 mt-2">
                  <li>Center the tree in good daylight; include leaves and trunk clearly.</li>
                  <li>Avoid cluttered backgrounds and very small trees hidden by other objects.</li>
                  <li>Ensure the image isn’t blurry and the file size isn’t corrupted.</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Tasks -->
          <div class="accordion-item faq-item" id="faq-tasks" data-text="tasks red yellow green what do they mean map circles pollution">
            <h2 class="accordion-header" id="headingTasks">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTasks" aria-expanded="false" aria-controls="collapseTasks">
                <i class="bi bi-list-check me-2"></i> What do the Red / Yellow / Green tasks mean?
              </button>
            </h2>
            <div id="collapseTasks" class="accordion-collapse collapse" aria-labelledby="headingTasks" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Circles on the map represent environmental readings. Their color maps to a task:
                <ul class="mb-2">
                  <li><strong>Red:</strong> Contribute more—plant <strong>2</strong> trees and upload a photo.</li>
                  <li><strong>Yellow:</strong> Moderate—plant <strong>1</strong> tree and upload a photo.</li>
                  <li><strong>Green:</strong> All good—no action required.</li>
                </ul>
                Click a circle to see details and open the “Upload Proof” dialog.
              </div>
            </div>
          </div>

          <!-- Datasets / Map -->
          <div class="accordion-item faq-item" id="faq-map" data-text="dataset real data sensor_ingest selection map legends zoom radius filters">
            <h2 class="accordion-header" id="headingMap">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMap" aria-expanded="false" aria-controls="collapseMap">
                <i class="bi bi-map me-2"></i> What are “DataSet 1–5” vs “Real Data” on the map?
              </button>
            </h2>
            <div id="collapseMap" class="accordion-collapse collapse" aria-labelledby="headingMap" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                The dashboard can visualize different sensor tables:
                <ul class="mb-2">
                  <li><strong>DataSet 1–5:</strong> Demo tables named <code>sensor_ingest_1 … sensor_ingest_5</code>.</li>
                  <li><strong>Real Data:</strong> Live table named <code>real_sensor_ingest</code>.</li>
                </ul>
                You can also change the metric, the time range, and the circle radius. The legend explains color thresholds per metric.
              </div>
            </div>
          </div>

          <!-- Where to see my credits -->
          <div class="accordion-item faq-item" data-text="where see my points dashboard history leaderboard contribution map">
            <h2 class="accordion-header" id="headingWhereCredits">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWhereCredits" aria-expanded="false" aria-controls="collapseWhereCredits">
                <i class="bi bi-speedometer2 me-2"></i> Where can I see my points and submissions?
              </button>
            </h2>
            <div id="collapseWhereCredits" class="accordion-collapse collapse" aria-labelledby="headingWhereCredits" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Visit your <a href="<?= route('dashboard') ?>">Dashboard</a>:
                <ul class="mb-0">
                  <li><strong>Overview:</strong> Total Green Credit and account info.</li>
                  <li><strong>View History:</strong> Each verified submission with image, GPS, and reason.</li>
                  <li><strong>View Contribution (Map):</strong> A map of your own verified trees.</li>
                </ul>
                To compare with others, check the <a href="<?= route('leaderboard') ?>">Leaderboard</a> or the all-user map on <a href="<?= route('status') ?>">Overall Status</a>.
              </div>
            </div>
          </div>

          <!-- Leaderboard rules -->
          <div class="accordion-item faq-item" data-text="leaderboard ranking rules tiebreak verified count created order">
            <h2 class="accordion-header" id="headingLeaderboard">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLeaderboard" aria-expanded="false" aria-controls="collapseLeaderboard">
                <i class="bi bi-trophy me-2"></i> How are ranks calculated on the Leaderboard?
              </button>
            </h2>
            <div id="collapseLeaderboard" class="accordion-collapse collapse" aria-labelledby="headingLeaderboard" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Users are sorted by:
                <ol class="mb-0">
                  <li><strong>Green Credit</strong> (higher is better)</li>
                  <li><strong>Verified count</strong> (as a tiebreak)</li>
                  <li><strong>Account creation time</strong> (earlier registered wins ties)</li>
                </ol>
                Click a user to view their public contribution history.
              </div>
            </div>
          </div>

          <!-- Privacy -->
          <div class="accordion-item faq-item" id="faq-privacy" data-text="privacy data use email photos gps location security delete account settings">
            <h2 class="accordion-header" id="headingPrivacy">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePrivacy" aria-expanded="false" aria-controls="collapsePrivacy">
                <i class="bi bi-lock me-2"></i> How do you use my data and photos?
              </button>
            </h2>
            <div id="collapsePrivacy" class="accordion-collapse collapse" aria-labelledby="headingPrivacy" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                We store your account details, uploaded tree photos, and submission locations to verify contributions and calculate Green Credit. Verified contributions may appear on public maps and rankings. You can update your profile or request deletion from <a href="<?= route('settings') ?>">Account Settings</a>. For support, reach us via <a href="<?= route('contact') ?>">Contact</a>.
              </div>
            </div>
          </div>

          <!-- Troubleshooting -->
          <div class="accordion-item faq-item" id="faq-troubleshoot" data-text="troubleshoot upload failed low confidence no confidence verification unavailable gps not allowed image not clear">
            <h2 class="accordion-header" id="headingTroubleshoot">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTroubleshoot" aria-expanded="false" aria-controls="collapseTroubleshoot">
                <i class="bi bi-tools me-2"></i> Upload/Verification issues — what should I do?
              </button>
            </h2>
            <div id="collapseTroubleshoot" class="accordion-collapse collapse" aria-labelledby="headingTroubleshoot" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Common statuses:
                <ul>
                  <li><strong>Low/No confidence:</strong> Take a clearer photo in daylight; include leaves & trunk prominently.</li>
                  <li><strong>Verification unavailable:</strong> Retry in a moment; ensure your internet connection is stable.</li>
                  <li><strong>GPS denied:</strong> Allow location permission, or we’ll fallback to the selected map point.</li>
                </ul>
                Still stuck? Contact us from <a href="<?= route('contact') ?>">Contact</a> with a brief description and screenshot.
              </div>
            </div>
          </div>

        </div>
      </section>

      <!-- Quick Links -->
      <section class="quick-links mt-3">
        <div class="row g-3">
          <div class="col-sm-6 col-lg-3">
            <div class="card p-3 h-100">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-speedometer2"></i><strong>Dashboard</strong>
              </div>
              <p class="small mb-3">See your total Green Credit and submission history.</p>
              <a class="btn btn-ghost btn-sm" href="<?= route('dashboard') ?>">Open Dashboard</a>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="card p-3 h-100">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-trophy"></i><strong>Leaderboard</strong>
              </div>
              <p class="small mb-3">Top contributors ranked by points & verified trees.</p>
              <a class="btn btn-ghost btn-sm" href="<?= route('leaderboard') ?>">View Leaderboard</a>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="card p-3 h-100">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-globe-americas"></i><strong>Overall Status</strong>
              </div>
              <p class="small mb-3">Explore all users’ verified trees on the map.</p>
              <a class="btn btn-ghost btn-sm" href="<?= route('status') ?>">Open Map</a>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="card p-3 h-100">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-envelope"></i><strong>Need Help?</strong>
              </div>
              <p class="small mb-3">We’re here if you have questions or feedback.</p>
              <a class="btn btn-ghost btn-sm" href="<?= route('contact') ?>">Contact Us</a>
            </div>
          </div>
        </div>
      </section>

    </div>
  </div>
</div>

<script>
  // Simple client-side search: hides FAQ items that don't match the query
  (function(){
    const input = document.getElementById('faqSearch');
    const items = Array.from(document.querySelectorAll('.faq-item'));
    if (!input) return;

    function norm(s){ return (s || '').toLowerCase().trim(); }

    input.addEventListener('input', () => {
      const q = norm(input.value);
      items.forEach(it => {
        const text = (it.getAttribute('data-text') || '') + ' ' + it.innerText;
        const hit = norm(text).includes(q);
        it.classList.toggle('filtered-out', q && !hit);
      });
    });
  })();
</script>

<?php theme_foot();
