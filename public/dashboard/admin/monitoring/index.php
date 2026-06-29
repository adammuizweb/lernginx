<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../modules/register_module.php';

$user = get_user_from_session($pdo);
if ( ! $user || !in_array($user['role'], ['teacher', 'admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Monitoring - Dashboard';
    require_once __DIR__ . '/../partials/layout.php';
    exit;
}

// base assign URL (relatif)
$assignBase = '../assign/index.php';

// -------------------- Queries --------------------

// 1) Total siswa aktif (role = siswa, is_deleted = 0)
$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_deleted = 0")->fetchColumn();

// 2) Jumlah siswa (distinct) yang punya modul dengan masing-masing status (0 = aktif, 1 = pending, 2 = nonaktif)
// pastikan exclude is_deleted
$statusCounts = [0 => 0, 1 => 0, 2 => 0];
$stmt = $pdo->prepare("
    SELECT m.status, COUNT(DISTINCT m.user_id) AS cnt
    FROM modules m
    JOIN users u ON u.id = m.user_id
    WHERE u.role = 'student' AND u.is_deleted = 0 AND m.status IN (0,1,2)
    GROUP BY m.status
");
$stmt->execute();
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = (int)$r['status'];
    $statusCounts[$status] = (int)$r['cnt'];
}

// 3) Per-kategori breakdown: for each category count distinct users (siswa aktif) per status
$stmt = $pdo->prepare("
    SELECT
      c.id,
      c.name,
      COUNT(DISTINCT CASE WHEN m.status = 0 AND u.role = 'student' AND u.is_deleted = 0 THEN m.user_id END) AS cnt_active,
      COUNT(DISTINCT CASE WHEN m.status = 1 AND u.role = 'student' AND u.is_deleted = 0 THEN m.user_id END) AS cnt_pending,
      COUNT(DISTINCT CASE WHEN m.status = 2 AND u.role = 'student' AND u.is_deleted = 0 THEN m.user_id END) AS cnt_nonactive
    FROM categories c
    LEFT JOIN modules m ON m.category_id = c.id
    LEFT JOIN users u ON u.id = m.user_id
    GROUP BY c.id
    ORDER BY cnt_active DESC, cnt_pending DESC, cnt_nonactive DESC, c.name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// helper links
function link_assign_all() {
    return "../assign/index.php";
}
function link_assign_status($status) {
    return "../assign/index.php?" . http_build_query(['status' => $status]);
}
function link_assign_category($catId) {
    return "../assign/index.php?" . http_build_query(['category' => $catId]);
}
function link_assign_category_status($catId, $status) {
    return "../assign/index.php?" . http_build_query(['category' => $catId, 'status' => $status]);
}

// -------------------- Render HTML --------------------
ob_start();
?>

<h2>📊 Monitoring - Sistem</h2>

<div class="monitoring-xxx-wrap">

  <!-- Full-width: Total -->
<div class="monitoring-xxx-card">
    <div class="monitoring-xxx-hero">
      <div class="monitoring-xxx-meta">Total Students</div>
      <a class="monitoring-xxx-kpi" href="<?= htmlspecialchars(link_assign_all(), ENT_QUOTES) ?>"><?= number_format($totalActive) ?></a>
      <div class="monitoring-xxx-note">Only counting registered students</div>
    </div>

    <hr style="margin:12px 0;border:none;border-top:1px solid var(--border);">

    <div class="monitoring-xxx-meta" style="margin-bottom:8px;">Module status summary (unique students per status):</div>

    <div class="monitoring-xxx-grid-status">
      <a class="monitoring-xxx-status-item" href="<?= htmlspecialchars(link_assign_status(0), ENT_QUOTES) ?>">
        <h4>✅ Active</h4>
        <div class="num"><?= number_format($statusCounts[0]) ?></div>
        <div class="monitoring-xxx-note">Students with at least 1 Active module</div>
      </a>

      <a class="monitoring-xxx-status-item" href="<?= htmlspecialchars(link_assign_status(1), ENT_QUOTES) ?>">
        <h4>⏳ Pending</h4>
        <div class="num"><?= number_format($statusCounts[1]) ?></div>
        <div class="monitoring-xxx-note">Students with at least 1 Pending module</div>
      </a>

      <a class="monitoring-xxx-status-item" href="<?= htmlspecialchars(link_assign_status(2), ENT_QUOTES) ?>">
        <h4>❌ Inactive</h4>
        <div class="num"><?= number_format($statusCounts[2]) ?></div>
        <div class="monitoring-xxx-note">Students with at least 1 Inactive module</div>
      </a>
    </div>
  </div>

  <!-- 2 Columns: Category & Chart -->
  <div class="monitoring-xxx-bottom">
<div class="monitoring-xxx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <div class="monitoring-xxx-meta">Per-category (unique students per status)</div>
        <h3 style="margin:6px 0 0 0;">Module Categories</h3>
      </div>
      <div class="monitoring-xxx-note">Click number to open registration page filtered by category + status</div>
    </div>

    <div class="monitoring-xxx-cats" style="margin-top:12px;">
      <?php if (empty($categories)): ?>
        <div class="monitoring-xxx-note">No categories yet.</div>
      <?php else: ?>
        <?php foreach ($categories as $c):
            $cid = (int)$c['id'];
            $cname = $c['name'] ?? '—';
            $cntA = (int)($c['cnt_active'] ?? 0);
            $cntP = (int)($c['cnt_pending'] ?? 0);
            $cntN = (int)($c['cnt_nonactive'] ?? 0);
        ?>
          <div class="monitoring-xxx-cat-row" role="group" aria-label="<?= htmlspecialchars($cname) ?>">
<div class="monitoring-xxx-cat-name">
  <a class="monitoring-xxx-link" href="javascript:void(0)"
     onclick="updatePieChart(
       '<?= htmlspecialchars(addslashes($cname)) ?>',
       <?= (int)$cid ?>,
       <?= (int)$cntA ?>,
       <?= (int)$cntP ?>,
       <?= (int)$cntN ?>
     )">
     <?= htmlspecialchars($cname) ?>
  </a>
</div>
            <div class="monitoring-xxx-cat-stats">
              <a class="monitoring-xxx-stat-pill monitoring-xxx-stat-active" title="Active students in category <?= htmlspecialchars($cname) ?>" href="<?= htmlspecialchars(link_assign_category_status($cid, 0), ENT_QUOTES) ?>">
                ✅ <?= number_format($cntA) ?>
              </a>
              <a class="monitoring-xxx-stat-pill monitoring-xxx-stat-pending" title="Pending students in category <?= htmlspecialchars($cname) ?>" href="<?= htmlspecialchars(link_assign_category_status($cid, 1), ENT_QUOTES) ?>">
                ⏳ <?= number_format($cntP) ?>
              </a>
              <a class="monitoring-xxx-stat-pill monitoring-xxx-stat-non" title="Inactive students in category <?= htmlspecialchars($cname) ?>" href="<?= htmlspecialchars(link_assign_category_status($cid, 2), ENT_QUOTES) ?>">
                ❌ <?= number_format($cntN) ?>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="monitoring-xxx-note" style="margin-top:10px;">
      Excerpt: select a category to update the chart.
    </div>
  </div>

<div class="monitoring-xxx-chart-wrap">
  <div class="monitoring-xxx-chart-header">
    <h3 id="monitoring-xxx-chart-title">📊 Statistics Chart — All Categories</h3>
    <button type="button" class="monitoring-xxx-btn-reset" onclick="resetPieChart()">🔄 Default</button>
  </div>
  <canvas id="monitoringPieChart"></canvas>
</div>

  </div>

<!-- Pastikan hanya 1x Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(function() {
  // Prevent double initialization
  if (window.monitoringPieChartInitialized) return;
  window.monitoringPieChartInitialized = true;

  const ctx = document.getElementById('monitoringPieChart').getContext('2d');

  // Data default semua kategori
  const defaultData = {
    label: 'All Categories',
    values: {
      active: <?= (int)$statusCounts[0] ?>,
      pending: <?= (int)$statusCounts[1] ?>,
      nonactive: <?= (int)$statusCounts[2] ?>
    },
    categoryId: null
  };

  // Fungsi URL assign
  function getAssignUrl(categoryId, status) {
    const base = '../assign/index.php';
    const params = new URLSearchParams();
    if (categoryId) params.append('category', categoryId);
    if (status !== undefined) params.append('status', status);
    return `${base}?${params.toString()}`;
  }

  let pieChart = null;
  let currentCategoryId = null;

  // Fungsi render chart baru
  function createPieChart(active, pending, nonactive, titleText) {
    if (pieChart) pieChart.destroy();

    pieChart = new Chart(ctx, {
      type: 'pie',
      data: {
        labels: ['Active', 'Pending', 'Inactive'],
        datasets: [{
          data: [active, pending, nonactive],
          backgroundColor: ['#4CAF50', '#FFC107', '#F44336'],
          hoverOffset: 10,
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          title: { display: true, text: titleText }
        },
        onClick: (evt, elements) => {
          if (!elements.length) return;
          const idx = elements[0].index;
          const status = idx; // 0=aktif,1=pending,2=nonaktif
          const url = getAssignUrl(currentCategoryId, status);
          window.open(url, '_blank');
        },
        animation: {
          animateRotate: true,
          animateScale: true
        }
      }
    });
  }

  // Fungsi update via klik kategori
  window.updatePieChart = function(categoryName, categoryId, active, pending, nonactive) {
    currentCategoryId = categoryId;
    document.getElementById('monitoring-xxx-chart-title').innerText = `📊 Statistics Chart — ${categoryName}`;
    createPieChart(active, pending, nonactive, `Distribution: ${categoryName}`);
  };

  // Fungsi reset (tombol Default)
  window.resetPieChart = function() {
    currentCategoryId = null;
    document.getElementById('monitoring-xxx-chart-title').innerText = '📊 Statistics Chart — All Categories';
    createPieChart(
      defaultData.values.active,
      defaultData.values.pending,
      defaultData.values.nonactive,
      'Distribution: All Categories'
    );
  };

  // Render awal
  document.addEventListener('DOMContentLoaded', resetPieChart);
})();
</script>

</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Monitoring - Dashboard';
require_once __DIR__ . '/../../partials/layout.php';
