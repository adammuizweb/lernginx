<?php
// setelah bangun $tree (tetap sama seperti sebelumnya), ambil juga maxParentLimit
$limitStmt = $pdo->prepare("SELECT value FROM registration_policies WHERE key_name = 'max_parent_modules_per_student' LIMIT 1");
$limitStmt->execute();
$limitVal = $limitStmt->fetchColumn();
$maxParentLimit = ($limitVal !== false) ? max(1, (int)$limitVal) : 2;
?>
<section class="notice-row" id="notice">
  <div class="notice-left" aria-hidden="true">
    <div class="svg-wrap">
<svg id="Layer_2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 88.42 182.43"><defs><style>.cls-1,.cls-2{fill:#fff;}.cls-1,.cls-3,.cls-4,.cls-5{stroke:#000;stroke-miterlimit:10;stroke-width:.02px;}.cls-6{fill:#727272;}.cls-7{fill:#0d162d;}.cls-8{fill:#ffb5a3;}.cls-9{fill:#72a5c1;}.cls-10{fill:#d12c11;}.cls-3{fill:#fbc52c;}.cls-11{fill:#1b2e49;}.cls-12{fill:#f66;}.cls-13{fill:#512410;}.cls-4{fill:#a07d30;}.cls-14{fill:#331408;}.cls-15{fill:#3f190a;}.cls-16{fill:#f29e8c;}.cls-17{fill:#fa502c;}.cls-18{fill:#1d1d1b;}.cls-5{fill:#fffce9;}.cls-19{fill:#fdd958;}.cls-20{fill:#793b1c;}</style></defs><g id="Layer_1-2"><path class="cls-8" d="M74.29,73.31s3.21-5.69,4.19-5.5-.02,3.64-.02,3.64c0,0,5.62-3.72,6.2-3.26.59.45-3.69,4.53-3.69,4.53,0,0,5.61-2.08,6.07-.86.47,1.22-4.92,3.22-4.92,3.22,0,0,5.7.73,5.64,1.23-.06.51-6.83,1.19-6.83,1.19,0,0,3.54,1.45,3.54,2.11,0,.67-5.47.09-10.56-3.64l.37-2.67Z"/><path class="cls-10" d="M38.09,46.12c-11.09-.62-6.69,23.93-2.93,28.39,3.76,4.46,38.76,2.6,38.76,2.6,0,0,1.41-.98.37-3.79,0,0-7.87-2.15-18.27-4.38-10.4-2.23-11.74-1.34-11.85-6.24-.11-4.91.68-16.19-6.07-16.57Z"/><polygon class="cls-1" points="83.45 31.42 84.59 39.33 53.82 41.7 53.48 33.7 83.45 31.42"/><polygon class="cls-3" points="52.16 32.97 52.57 42.33 85.83 40.28 85.75 38.7 54.27 41.05 54 34.27 84.22 31.89 84.02 30.5 52.16 32.97"/><polygon class="cls-1" points="84.32 47.49 84.6 39.82 55.12 42.26 55.45 48.38 84.32 47.49"/><polygon class="cls-4" points="54.26 49.21 53.86 41.79 85.69 38.78 85.77 40.3 55.62 42.84 55.89 47.78 85.01 46.96 84.97 48.31 54.26 49.21"/><polygon class="cls-1" points="86.37 56.82 86.61 46.9 53.9 49.77 54.32 57.68 86.37 56.82"/><polygon class="cls-3" points="53.01 58.75 52.49 49.14 87.81 45.56 87.92 47.53 54.46 50.52 54.8 56.91 87.14 56.14 87.1 57.89 53.01 58.75"/><polygon class="cls-1" points="84.75 84.43 85.47 76.47 54.7 77.38 54.7 83.75 84.75 84.43"/><polygon class="cls-4" points="53.42 84.55 53.42 76.81 86.66 75.45 86.66 77.04 55.19 78 55.19 83.15 85.5 83.92 85.37 85.32 53.42 84.55"/><polygon class="cls-1" points="84.82 67.81 85.51 75.45 55.94 77.07 55.95 68.46 84.82 67.81"/><polygon class="cls-3" points="54.71 67.69 54.7 77.61 86.66 76.43 86.66 74.91 56.4 76.47 56.41 69.03 85.54 68.3 85.42 66.95 54.71 67.69"/><polygon class="cls-1" points="86.38 58.38 87.15 68.28 54.32 67.15 54.32 59.23 86.38 58.38"/><polygon class="cls-4" points="52.95 58.23 52.95 67.85 88.41 69.54 88.41 67.57 54.84 66.38 54.84 59.97 87.18 59.02 87.05 57.28 52.95 58.23"/><path class="cls-8" d="M42.12,173.98l6.95.59-4.93-65.28-10.9-2.55c2.76,17.13,7.55,53.83,8.87,67.24Z"/><polygon class="cls-2" points="41.61 175.93 49.76 175.61 48.32 159.35 40.35 159.35 41.61 175.93"/><polygon class="cls-2" points="18.94 175.61 11.2 175.61 12.19 160.22 20.5 160.22 18.94 175.61"/><path class="cls-11" d="M40.67,172.01c3.89.38,5.25.94,9.1.02l-7-79.89-17.15-6.84,15.06,86.72Z"/><path class="cls-11" d="M10.33,171.91c3.34.45,6.29.95,9.5.39l15.45-79.96-19.16-.3-5.79,79.87Z"/><path class="cls-17" d="M24.67,42.29c.95-.67,3.52-1.3,3.85-1.16.33-.15,2.93.36,3.9.99,3.8,2.44,5.89,3.49,9.1,6.06,1.69,1.35,2.15,9.31,2.23,12.48.21,8.88-.06,35.52-.06,35.52-5.45,6.19-18.87,3.76-25.06,2.22-1.98-.49-3.23-.9-3.23-.9l.86-14.03-.04-11.57-.04-12.62-2.83.06c-3.07.07-3.29-4.23-3.29-4.23-.13-6.01,8.84-8.73,14.6-12.81Z"/><path class="cls-5" d="M36.23,44.3s3.59,13.11-.68,13.57c-7.66.82-14.71-13.31-14.71-13.31l6.22-3.78,4.74.3,4.43,3.22Z"/><path class="cls-8" d="M27.68,42.69c1.58.46,3.69.41,5,.32l.05,2.12c.08,3.56-7.54,3.73-7.62.17l-.08-3.68c.96.45,1.91.85,2.66,1.06Z"/><path class="cls-8" d="M24.83,33.47l7.62-.17.12,5.07c-2.42-.28-5.74-.66-7.65-.9l-.09-3.99Z"/><path class="cls-16" d="M27.68,42.69c-.75-.22-1.7-.62-2.66-1.06l-.1-4.17c1.91.24,5.23.62,7.65.9l.11,4.65c-1.31.09-3.42.14-5-.32Z"/><path class="cls-16" d="M33.62,41.15h0c6.83-1.19,11.41-7.68,10.23-14.52l-2.32-13.37c-1.18-6.83-7.68-11.41-14.52-10.23h0c-6.83,1.18-11.41,7.68-10.23,14.52l2.32,13.37c1.18,6.83,7.68,11.41,14.52,10.23Z"/><path class="cls-8" d="M35.38,40.85h0c6.28-1.09,9.74-6.82,8.65-13.1l-1.91-15.61c-1.09-6.28-7.06-10.49-13.35-9.4h0c-6.28,1.09-10.49,7.06-9.4,13.35l2.66,15.36c1.09,6.28,7.06,10.49,13.35,9.4Z"/><path class="cls-7" d="M40.07,17.75c-1.33,0-2.57.14-3.83.48-.53.14-1.06-.21-1.16-.75h0c-.08-.45.18-.91.61-1.05,1.46-.48,2.95-.67,4.46-.56.46.03.82.42.85.88h0c.03.54-.39,1-.93,1Z"/><path class="cls-7" d="M28.71,19.51c-1.48.06-2.84.3-4.23.75-.52.17-1.07-.16-1.19-.69l-.03-.12c-.1-.45.13-.92.56-1.08,1.58-.61,3.2-.9,4.86-.86.46.01.84.38.89.84v.12c.08.54-.32,1.03-.86,1.05Z"/><path class="cls-16" d="M20.29,28.32c.91,2.74.07,5.29-1.87,5.7-1.94.4-4.25-1.49-5.15-4.23-.91-2.74-.07-5.29,1.87-5.7,1.94-.4,4.25,1.49,5.15,4.23Z"/><path class="cls-16" d="M37.88,26.91s-.33,2.34-2.11,2.45-2.81-1.55-2.81-1.55c0,0,1.82.52,2.89.33,1.07-.19,2.03-1.23,2.03-1.23Z"/><path class="cls-6" d="M20.12,13.69s14.95-9.35,24.19,1.64c0,0,.48-8.69-4.11-12.23C35.61-.43,22.12-2.99,16.28,7.31c-4.83.49-7.37,5.44-5.48,9.74,1.16,2.63,2.94,5,4.03,6.32.47.57,1.07,1.03,1.76,1.29.64.24,1.56.77,2.39,1.98,0,0,.66-1.68,1.32-3.12,0,0,1.86-4.73-.18-9.83Z"/><path class="cls-7" d="M34.93,33.89c-.66.08-1.17.3-1.57.6-.65-.9-.79-1.96-.79-1.96,0,0,2.51.59,4.03.22,1.53-.37,2.75-.99,2.75-.99,0,0,.16,2.25-1.36,3.38-.56-.77-1.5-1.44-3.06-1.25Z"/><path class="cls-12" d="M33.36,34.48c.4-.29.91-.52,1.57-.6,1.57-.19,2.5.48,3.06,1.25-.4.29-.91.52-1.57.6-1.57.19-2.5-.48-3.06-1.25Z"/><path class="cls-2" d="M63.13,182.43l-22.34-.08-.02-1.48h22.34c.07.49.08,1.01.02,1.56Z"/><path class="cls-18" d="M40.67,173.83c0-.44.35-.79.79-.79l8.18-.04,2.41,2.76,5.72.58c2.75,0,4.97,1.78,5.34,4.53h-22.34l-.08-5.28h-.01s0-1.76,0-1.76Z"/><path class="cls-2" d="M57.48,176.31s-2.44.51-4,1.91h-1.87s1.08-1.22,3.47-2.15l2.41.24Z"/><path class="cls-2" d="M54.19,175.98s-2.44.51-4,1.91h-1.87s1.08-1.22,3.47-2.15l2.4.24Z"/><path class="cls-2" d="M10.43,180.87l.02,1.48,22.34.08c.06-.55.05-1.07-.02-1.56H10.43Z"/><path class="cls-18" d="M32.77,180.87c-.35-2.7-2.49-4.35-5.24-4.35l-6.01-.66-2.23-2.85-8.18.04c-.44,0-.79.36-.79.79v1.76s.02,0,.02,0l.08,5.28h22.35Z"/><path class="cls-2" d="M26.72,176.39s-2.44.51-4,1.91h-1.87s1.08-1.22,3.47-2.15l2.4.24Z"/><path class="cls-2" d="M23.44,176.07s-2.44.51-4,1.91h-1.87s1.08-1.22,3.47-2.15l2.4.24Z"/><path class="cls-7" d="M28.46,23.5c.26,1.15-.07,2.21-.74,2.36-.67.15-1.42-.66-1.68-1.81-.26-1.15.07-2.21.74-2.36.67-.15,1.42.66,1.68,1.81Z"/><path class="cls-7" d="M39.94,21.52c.26,1.15-.07,2.21-.74,2.36-.67.15-1.42-.66-1.68-1.81-.26-1.15.07-2.21.74-2.36.67-.15,1.42.66,1.68,1.81Z"/><path class="cls-9" d="M15.71,97.59l-.57,7.94s5.3-.76,7.98-6.18c0,0-3.63-.72-7.4-1.76Z"/><path class="cls-20" d="M7.11,116.9c-.84-5.76-7.93-56.64,2.25-68.42,1.57-1.82,3.57-2.78,5.77-2.78s4.19.93,5.87,2.76c10.77,11.78,6.53,62.57,6.01,68.32l-4.36-.39c1.36-15.1,3.14-56.2-4.88-64.98-1.09-1.2-2.03-1.34-2.63-1.34s-1.48.13-2.46,1.27c-7.52,8.7-3.44,49.82-1.23,64.93l-4.33.63Z"/><path class="cls-8" d="M52.52,76.15s3.21-5.69,4.19-5.5-.02,3.64-.02,3.64c0,0,5.62-3.72,6.2-3.26.59.45-3.69,4.53-3.69,4.53,0,0,5.61-2.08,6.07-.86.47,1.22-4.92,3.22-4.92,3.22,0,0,5.7.73,5.64,1.23-.06.51-6.83,1.19-6.83,1.19,0,0,3.54,1.45,3.54,2.11s-5.47.09-10.56-3.64l.37-2.67Z"/><path class="cls-10" d="M16.33,48.96c-11.09-.62-6.69,23.93-2.93,28.39,3.76,4.46,38.76,2.6,38.76,2.6,0,0,1.42-.98.37-3.79,0,0-7.87-2.15-18.27-4.38-10.4-2.23-11.74-1.34-11.85-6.24-.11-4.91.68-16.19-6.07-16.57Z"/><rect class="cls-15" x="2.32" y="104.35" width="29.88" height="22.33" rx="1" ry="1"/><rect class="cls-13" y="104.35" width="29.88" height="22.33" rx="1" ry="1"/><polygon class="cls-14" points="14.94 104.35 1.39 104.35 4.03 119.1 25.85 119.1 28.5 104.35 14.94 104.35"/><rect class="cls-19" x="12.62" y="117.87" width="4.64" height="2.46"/><path class="cls-8" d="M24.74,26.61s2.29-3.3,6.26-.81l-1.2,2.43-5.06-1.61Z"/><path class="cls-8" d="M35.73,24.71s2.29-3.3,6.26-.81l-1.2,2.43-5.06-1.61Z"/><path class="cls-2" d="M27.84,23.04c.1.27.03.55-.14.61s-.4-.11-.5-.38c-.1-.27-.03-.55.14-.61.18-.06.4.11.5.38Z"/><path class="cls-2" d="M39.42,21.16c.1.27.03.55-.14.61s-.4-.11-.5-.38c-.1-.27-.03-.55.14-.61s.4.11.5.38Z"/></g></svg></div>
  </div>
  
<?php
// dashboard/partials/beranda.php
// Partial ini dipakai pada beranda dashboard ketika siswa belum memiliki modul aktif.
// Pastikan file ini berada di folder yang sama seperti sebelumya.

if (!defined('DASHBOARD_CONTEXT')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

// guard: pastikan $pdo tersedia
if (!isset($pdo)) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ambil user jika belum didefinisikan
if (!isset($user) || !$user) {
    if (function_exists('get_user_from_session')) {
        $user = get_user_from_session($pdo);
    }
}

// jika tidak ada user, tampil pesan singkat dan stop
if (!$user) {
    echo '<div class="notice-right"><p>Access: please log in to view modules.</p></div>';
    return;
}

// include helper render (pastikan file ini ada)
$renderPath = __DIR__ . '/functions/render_category_checkbox.php';
if (!file_exists($renderPath)) {
    echo '<div class="notice-right"><p class="error">Function render_category_checkbox not found.</p></div>';
    return;
}
require_once $renderPath;

// Ambil kebijakan sistem (default status)
$policyStmt = $pdo->prepare("SELECT value FROM registration_policies WHERE key_name = 'default_student_module_status' LIMIT 1");
$policyStmt->execute();
$policy = $policyStmt->fetchColumn();
$defaultStatus = ($policy !== false) ? (int)$policy : 0;

// Ambil max parent limit (default 2)
$limitStmt = $pdo->prepare("SELECT value FROM registration_policies WHERE key_name = 'max_parent_modules_per_student' LIMIT 1");
$limitStmt->execute();
$limitVal = $limitStmt->fetchColumn();
$maxParentLimit = ($limitVal !== false) ? max(1, (int)$limitVal) : 2;

// Ambil semua kategori
$stmt = $pdo->query("SELECT id, name, slug, parent_id FROM categories ORDER BY COALESCE(parent_id, 0), name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil nama kategori
$categoryNames = [];
foreach ($categories as $row) {
    $categoryNames[(int)$row['id']] = $row['name'];
}

// Ambil modul user
$stmt = $pdo->prepare("SELECT id, category_id, status, is_reviewed FROM modules WHERE user_id = ?");
$stmt->execute([$user['id']]);
$userModules = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $userModules[(int)$r['category_id']] = [
        'id' => (int)$r['id'],
        'status' => (int)$r['status'],
        'is_reviewed' => ((int)$r['is_reviewed'] === 1)
    ];
}

// Ambil modul yang aktif dan sudah direview (untuk konfirmasi pembatalan)
$reviewedModules = [];
foreach ($userModules as $cid => $mod) {
    if ($mod['status'] === 0 && $mod['is_reviewed']) {
        $reviewedModules[$cid] = $categoryNames[$cid] ?? "Category #$cid";
    }
}

// Bangun pohon kategori
$tree = [];
foreach ($categories as $c) {
    $tree[(int)$c['parent_id']][] = $c;
}

?>

<div class="notice-right">
  <!-- BARIS 1: bagian yang punya animasi melayang (FLOAT) -->
  <div class="notice-right-top">
    <div class="text-wrap">
      <h1 class="title" data-full="Hai, <?= htmlspecialchars($user['display_name'] ?? $user['username']) ?> !"></h1>
      <p class="subtitle">Your account has been created successfully.</p>
      <p class="body">Explore the learning modules that interest you. All modules are free and available anytime.</p>
      <div class="actions" style="display: none;">
        <button class="btn primary" id="signup" onclick="window.location.href='/dashboard/'">Enroll Module</button>
        <button class="btn ghost" id="back" onclick="window.location.href='/dashboard/'">Back</button>
      </div>
    </div>
  </div>

  <!-- BARIS 2: bagian statis (tanpa animasi) -->
  <div class="notice-right-bottom" aria-hidden="false">
    <div class="bottom-inner">
      <h3 class="bottom-title">Module Selection</h3>

      <!-- FORM PILIH MODUL -->
      <form method="POST" action="/dashboard/student/daftar.php" id="moduleForm" novalidate>
        <input type="hidden" name="from" value="beranda_notice_bottom">

        <div class="module-limit-info" aria-live="polite" style="margin-bottom:8px;">
          <strong>Maximum main modules you can enroll:</strong>
          <span id="currentParentCount">0</span> / <span id="maxParentLimit"><?= (int)$maxParentLimit ?></span>
        </div>

        <div class="module-checkboxes" aria-live="polite">
          <?php
            $top = $tree[0] ?? $tree[null] ?? [];
            render_category_checkbox($top, $tree, $userModules, $defaultStatus);
          ?>
        </div>

        <div style="margin-top:10px;">
          <button type="submit" id="submitModules" class="btn primary" style="padding:8px 12px; font-size:14px;">Save Selection</button>
        </div>
      </form>

      <!-- Modal Konfirmasi (untuk pembatalan modul yang sudah direview) -->
      <div class="modal-konfirm" id="modalKonfirm" role="dialog" aria-modal="true" style="display:none">
        <div class="modal-body">
          <h3>Cancel Confirmation</h3>
          <p>The following modules have been reviewed by teacher and will be canceled:</p>
          <ul id="reviewedList"></ul>
          <p>Canceling will resubmit or change the status. Continue cancellation?</p>
          <div class="modal-actions">
            <button class="btn btn-danger" id="confirmCancel" type="button">Yes, Cancel</button>
            <button class="btn btn-secondary" id="cancelModal" type="button">Cancel</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- build parent map untuk behavior parent include child -->
<script>
// build child->parent mapping (same source as before)
window.categoryParentMap = <?= json_encode(array_reduce($tree, function($carry,$children) {
foreach ($children as $child) {
$carry[(int)$child['id']] = (int)$child['parent_id'];
}
return $carry;
}, []), JSON_UNESCAPED_SLASHES) ?>;
</script>
<script>
(function () {
  'use strict';

  // gunakan parentMap yang sudah dibuat oleh server-side di atas
  const parentMap = window.categoryParentMap || {};

  // build childMap untuk traversal descendant cepat (parentId => [childId,...])
  const childMap = {};
  Object.entries(parentMap).forEach(([childStr, parent]) => {
    const child = parseInt(childStr, 10);
    const pid = parent === null ? 0 : parseInt(parent, 10);
    if (!childMap[pid]) childMap[pid] = [];
    childMap[pid].push(child);
  });

  // ambil limit dari window (jika sudah diekspor) atau fallback ke PHP value
  const maxLimit = (window.MAX_PARENT_LIMIT !== undefined)
    ? parseInt(window.MAX_PARENT_LIMIT, 10)
    : <?= (int)$maxParentLimit ?>;

  // expose global (jangan override jika sudah ada)
  window.MAX_PARENT_LIMIT = window.MAX_PARENT_LIMIT || maxLimit;

  const currentCountEl = document.getElementById('currentParentCount');
  const maxLimitEl = document.getElementById('maxParentLimit');
  if (maxLimitEl) maxLimitEl.textContent = maxLimit;

  // Dapatkan semua descendant (DFS)
  function getAllDescendants(startId) {
    const out = [];
    const stack = [startId];
    while (stack.length) {
      const cur = stack.pop();
      const children = childMap[cur] || [];
      for (const c of children) {
        out.push(c);
        stack.push(c);
      }
    }
    return out;
  }

  // Perbaikan getTopParent: naik sampai parent === 0/null atau tidak ada
  function getTopParent(cid) {
    cid = parseInt(cid, 10);
    if (!parentMap.hasOwnProperty(cid)) return cid;
    let cur = cid;
    while (parentMap.hasOwnProperty(cur) && parentMap[cur] !== 0 && parentMap[cur] !== null) {
      cur = parentMap[cur];
    }
    return cur;
  }

  // Simple toast (jika sistem punya style, bisa diganti)
  function showWarningbhn(message, type = 'error') {
    let container = document.getElementById('warningbhn-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'warningbhn-container';
      container.style.position = 'fixed';
      container.style.right = '12px';
      container.style.top = '12px';
      container.style.zIndex = 9999;
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `warningbhn-toast ${type}`;
    toast.textContent = message;
    toast.style.marginTop = '8px';
    toast.style.padding = '10px 14px';
    toast.style.borderRadius = '6px';
    toast.style.boxShadow = '0 2px 8px rgba(0,0,0,0.12)';
    toast.style.background = (type === 'error') ? '#ffdede' : '#fff5d6';
    toast.style.color = '#222';
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity .25s';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }

  // Hitung top-level parents dari checkbox tercentang
  function updateParentCount() {
    const checked = Array.from(document.querySelectorAll('.module-checkboxes input[type="checkbox"]:checked'))
      .map(cb => parseInt(cb.value, 10));

    const topParents = new Set();
    checked.forEach(id => topParents.add(getTopParent(id)));

    if (currentCountEl) currentCountEl.textContent = topParents.size;
    return topParents.size;
  }

  // Terapkan toggle ke semua descendant dari parentId
  function onParentToggle(parentId, checked) {
    const descendants = getAllDescendants(parentId);
    for (const d of descendants) {
      const cb = document.querySelector(`.module-checkboxes input[type="checkbox"][value="${d}"]`);
      if (!cb) continue;
      cb.checked = checked;
    }
  }

  // Inisialisasi dan event listeners
  document.addEventListener('DOMContentLoaded', () => {
    const checkboxes = Array.from(document.querySelectorAll('.module-checkboxes input[type="checkbox"]'));
    if (!checkboxes.length) return;

    // Jika parent sudah checked pada load, sinkronkan descendants
    checkboxes.forEach(cb => {
      const id = parseInt(cb.value, 10);
      if ((childMap[id] || []).length > 0 && cb.checked) {
        onParentToggle(id, true);
      }
    });

    // Pasang listener pada setiap checkbox
    checkboxes.forEach(cb => {
      cb.addEventListener('change', function () {
        if (this.disabled) return;
        const id = parseInt(this.value, 10);

        // Jika ini node parent (memiliki children)
        if ((childMap[id] || []).length > 0) {
          if (this.checked) {
            // Simulasikan checked set dan pastikan tidak melebihi maxLimit
            const checkedNow = Array.from(document.querySelectorAll('.module-checkboxes input[type="checkbox"]:checked'))
              .map(x => parseInt(x.value, 10));
            if (!checkedNow.includes(id)) checkedNow.push(id);

            const tpSet = new Set();
            checkedNow.forEach(cid => tpSet.add(getTopParent(cid)));

            if (tpSet.size > maxLimit) {
              this.checked = false;
              showWarningbhn(`Maximum main module limit reached. You can only enroll a maximum of ${maxLimit} modul utama.`, 'error');
              updateParentCount();
              return;
            }

            onParentToggle(id, true);
          } else {
            // uncheck parent -> uncheck descendants
            onParentToggle(id, false);
          }
        } else {
          // Node child-only: pastikan check tidak melanggar limit top-parent
          if (this.checked) {
            const checkedNow = Array.from(document.querySelectorAll('.module-checkboxes input[type="checkbox"]:checked'))
              .map(x => parseInt(x.value, 10));
            if (!checkedNow.includes(id)) checkedNow.push(id);

            const tpSet = new Set();
            checkedNow.forEach(cid => tpSet.add(getTopParent(cid)));

            if (tpSet.size > maxLimit) {
              this.checked = false;
              showWarningbhn(`Maximum main module limit reached. You can only enroll a maximum of ${maxLimit} modul utama.`, 'error');
              updateParentCount();
              return;
            }
          }
        }

        updateParentCount();
      });
    });

    // initial update
    updateParentCount();

    // modal confirm submit behavior: sama seperti sebelumnya
    const form = document.getElementById('moduleForm');
    const modal = document.getElementById('modalKonfirm');
    const confirmBtn = document.getElementById('confirmCancel');
    const cancelBtn = document.getElementById('cancelModal');
    const reviewedList = document.getElementById('reviewedList');
    const reviewedModules = <?= json_encode($reviewedModules) ?>;

    if (form && modal && confirmBtn && cancelBtn && reviewedList) {
      form.addEventListener('submit', function (e) {
        const checked = Array.from(document.querySelectorAll('input[name="modules[]"]:checked'))
          .map(cb => parseInt(cb.value, 10));

        const cancelingReviewed = Object.entries(reviewedModules)
          .filter(([id]) => !checked.includes(parseInt(id, 10)))
          .map(([id, name]) => name);

        if (cancelingReviewed.length > 0) {
          e.preventDefault();
          reviewedList.innerHTML = cancelingReviewed.map(name => `<li>${name}</li>`).join('');
          modal.style.display = 'flex';

          confirmBtn.onclick = () => {
            modal.style.display = 'none';
            form.submit();
          };

          cancelBtn.onclick = () => {
            modal.style.display = 'none';
          };
        }
      });
    }
  });
})();
</script>

<!-- Keep the animation / visual JS for the top panel (unchanged) -->
<script>
(function(){
  const notice = document.getElementById('notice');
  const titleEl = document.querySelector('.title');
  const subtitle = document.querySelector('.subtitle');
  const body = document.querySelector('.body');
  const svg = document.querySelector('.svg-wrap svg');
  const signup = document.getElementById('signup');
  const back = document.getElementById('back');

  if(svg){
    svg.classList.add('svg-popup');
    svg.addEventListener('animationend', ()=> {
      svg.classList.remove('svg-popup');
      svg.style.opacity = '1';
      svg.style.transform = 'none';
    }, { once:true });
  }

  function typeText(el, fullText, speed=48){
    el.textContent = '';
    el.classList.add('typing');
    let i = 0;
    const tick = () => {
      if(i <= fullText.length - 1){
        el.textContent += fullText[i++];
        setTimeout(tick, speed + Math.random()*18);
      } else {
        el.classList.remove('typing');
        subtitle.classList.add('enter');
        body.classList.add('enter');
      }
    };
    tick();
  }

  const initialDelay = 220; // ms
  const fullTitle = titleEl.dataset.full || 'Welcome ...!';
  setTimeout(()=> typeText(titleEl, fullTitle, 38), initialDelay);

  function createBurst(btn){
    const rect = btn.getBoundingClientRect();
    const btnRectWidth = rect.width;
    const btnRectHeight = rect.height;
    const colors = ['rgba(255,255,255,0.96)','rgba(255,255,255,0.72)','rgba(255,255,255,0.44)'];
    for(let i=0;i<7;i++){
      const dot = document.createElement('span');
      dot.className = 'burst';
      dot.style.background = colors[i%colors.length];
      dot.style.left = (btnRectWidth/2 - 4) + 'px';
      dot.style.top = (btnRectHeight/2 - 4) + 'px';
      dot.style.position = 'absolute';
      btn.appendChild(dot);
      const angle = (Math.PI*2) * (i/7) + (Math.random()*0.6 - 0.3);
      const dist = 18 + Math.random()*44;
      const tx = Math.cos(angle)*dist;
      const ty = Math.sin(angle)*dist - (6 + Math.random()*6);
      if(dot.animate){
        dot.animate([
          { transform:'translate(0,0) scale(0.6)', opacity:1 },
          { transform:`translate(${tx}px, ${ty}px) scale(1)`, opacity:0.9 },
          { transform:`translate(${tx*1.6}px, ${ty*1.6}px) scale(0.18)`, opacity:0 }
        ], { duration: 540 + Math.random()*280, easing: 'cubic-bezier(.2,.9,.2,1)' }).onfinish = ()=> dot.remove();
      } else {
        setTimeout(()=> dot.remove(), 900);
      }
    }
  }

  [signup, back].forEach(btn => {
    if (!btn) return;
    btn.addEventListener('mouseenter', (e) => {
      const target = e.currentTarget;
      createBurst(target);
      target.classList.remove('heboh');
      void target.offsetWidth;
      target.classList.add('heboh');
      setTimeout(() => { if (target) target.classList.remove('heboh'); }, 700);
    });

    btn.addEventListener('click', (e) => {
      const target = e.currentTarget;
      if (!target) return;
      target.classList.add('heboh');
      setTimeout(() => { if (target) target.classList.remove('heboh'); }, 700);
    });
  });

  if(svg) svg.style.willChange = 'opacity, transform';

})();
</script>
<style>
/* --- layout dasar (modifikasi kecil) --- */
.notice-row{
  display:flex;
  gap:28px;
  align-items:flex-start; /* align atas agar dua kolom kanan rapi */
  padding:28px;
  border-radius:12px;
  max-width:1100px;
  margin:20px auto;
  background: var(--card-bg);
}

/* kiri (SVG) tetap */
.notice-left{
  width:var(--left-w);
  height:var(--left-h);
  flex:0 0 var(--left-w);
  display:flex;
  align-items:center;
  justify-content:center;
  padding:10px;
  border-radius:10px;
}

/* svg-wrap & popup tetap sama */
.svg-wrap{ width:100%; height:100%; display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; }
.svg-wrap svg{ width:100%; height:100%; display:block; opacity:0; transform: translateY(18px) scale(.98); transform-origin:center; transform-box:fill-box; }
.svg-popup{ animation: svgPopOnce 560ms cubic-bezier(.2,.9,.2,1) forwards; }
@keyframes svgPopOnce{
  from{ transform: translateY(18px) scale(.98); opacity:0; }
  to{ transform: translateY(0) scale(1); opacity:1; }
}

/* kanan dipecah jadi dua baris vertikal */
.notice-right{
  flex:1 1 auto; min-width:0;
  display:flex;
  flex-direction:column;
  gap:14px;
}

/* --- BARIS ATAS: masih punya animasi "melayang" --- */
.notice-right-top{
  /* animasi floating hanya pada bagian atas */
  animation: rightFloat 6.8s ease-in-out infinite;
  will-change: transform;
  /* agar teks & tombol tidak memanjang melebihi kotak */
  background: transparent;
  padding: 6px 0;
}

/* --- BARIS BAWAH: statis, tanpa animasi --- */
.notice-right-bottom{
  background: rgba(255,255,255,0.03); /* contoh: sedikit beda background, sesuaikan theme */
  border-radius:10px;
  padding:12px 14px;
  box-shadow: 0 6px 18px rgba(27,46,73,0.04);
  color: var(--text-muted);
  font-size:15px;
  line-height:1.45;
  /* pastikan tidak ikut floating */
  animation: none;
}

/* styling internal bawah */
.bottom-inner { max-width:100%; }
.bottom-title {
  margin:0 0 6px 0;
  font-weight:700;
  font-size:16px;
}
.bottom-text { margin:0 0 8px 0; }
.module-list {
  margin:0;
  padding-left:18px;
  color:var(--accent);
  font-weight:600;
}

/* teks & tombol (sama seperti sebelumnya) */
.text-wrap{ font-family:"Poppins","Montserrat",system-ui,Roboto,Arial; }
.title{
  font-family:"Montserrat","Poppins";
  font-weight:800;
  font-size:44px;
  margin:0 0 6px 0;
  overflow:hidden;
  white-space:nowrap;
  letter-spacing:0.5px;
  min-height:58px;
  position:relative;
}
.title.typing::after{
  content: "";
  display:inline-block;
  width:2px;
  height:1.05em;
  background:var(--accent);
  position:absolute;
  right: -6px;
  top:8px;
  animation: blinkCursor 900ms steps(1) infinite;
}
@keyframes blinkCursor{ 50%{ opacity:0 } }
.subtitle, .body{
  font-size:17px;
  margin:0 0 12px 0;
  opacity:0;
  transform: translateY(8px);
  transition: opacity 420ms ease, transform 420ms cubic-bezier(.2,.9,.2,1);
}
.subtitle{ font-size:18px; font-weight:600; margin-bottom:10px; }
.subtitle.enter, .body.enter{ opacity:1; transform:none; }

/* tombol, burst, heboh dll (tetap seperti sebelumnya) */
.actions{ display:flex; gap:12px; align-items:center; }
.btn{ border:0; padding:11px 16px; border-radius:10px; font-weight:700; cursor:pointer; font-size:20px; background:transparent; color:var(--accent); transition: transform .18s, box-shadow .18s; position:relative; overflow:visible; }
.btn.primary{ background: linear-gradient(90deg,#1b2e49,#2b4a7a); color:#fff; box-shadow: 0 6px 18px rgba(27,46,73,0.14); border: none; }
.btn.ghost{ border:1px dashed rgba(0,0,0,0.08); color:var(--accent); background:transparent; }
.btn:hover{ transform: translateY(-8px) scale(1.04); box-shadow: 0 18px 40px rgba(27,46,73,0.16); }
@keyframes hebohPulse { 0%{ transform: translateY(0) scale(1); } 30%{ transform: translateY(-14px) scale(1.14); } 70%{ transform: translateY(-6px) scale(1.06); } 100%{ transform: translateY(0) scale(1); } }
.btn.heboh { animation: hebohPulse 560ms cubic-bezier(.2,.9,.2,1); }
.burst{ position:absolute; width:8px; height:8px; border-radius:50%; pointer-events:none; }

/* float animation hanya untuk kanan-atas */
@keyframes rightFloat{
  0%{ transform: translateY(0); }
  25%{ transform: translateY(-8px); }
  50%{ transform: translateY(0); }
  75%{ transform: translateY(6px); }
  100%{ transform: translateY(0); }
}

/* prefer reduced motion: matikan animasi jika user memilih reduce */
@media (prefers-reduced-motion: reduce) {
  .notice-right-top, .svg-wrap svg, .btn.heboh { animation: none !important; transition: none !important; }
}

/* responsive: pada layar kecil, stack tetap ok */
@media (max-width:880px){
  .notice-row{ flex-direction:column; gap:18px; padding:18px; max-width:720px; align-items:center; }
  .notice-left{ width:180px; height:370px; }
  .title{ font-size:34px; min-height:48px; white-space:normal; } /* biarkan break di mobile */
  .notice-right{ width:100%; }
  .notice-right-bottom{ padding:10px; }
}
</style>
