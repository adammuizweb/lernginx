<?php
// lokasi: /dashboard/admin/media/index.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if ( ! $user || ! in_array($user['role'], ['teacher','admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Media Manager';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// optional: CSRF token from session (if your app uses one)
$csrf = $_SESSION['csrf_token'] ?? '';

ob_start();
?>
<h1>📁 Media Library (BETA VERSION)</h1>
<p>Untuk mengelola gambar yang sudah diupload siapapun. Fitur ini masih dalam pegembangan.</p>
<p>Kelola file gambar yang telah diunggah. Kamu bisa mencari, menyisipkan ke editor, menyalin URL, atau menghapus file.</p>

<div style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
  <button id="openMedia" class="btn">Buka Media Library</button>
  <div style="display:flex;gap:6px;align-items:center;">
    <button id="scanPosts" class="btn" title="Scan folder assets (posts)">Scan Posts</button>
    <button id="scanProfile" class="btn" title="Scan folder profile (registrations)">Scan Profile</button>
    <button id="scanAll" class="btn" title="Scan semua sumber">Scan Semua</button>
  </div>
  <div style="margin-left:auto;color:#666;font-size:.95em">Tip: klik <strong>Insert</strong> untuk menambahkan ke Quill (set `window.__EDITOR_QUILL = quill`).</div>
</div>

<!-- modal (hidden) -->
<div id="mediaManagerModal" style="display:none;position:fixed;inset:0;z-index:9999;">
  <div id="mediaBackdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.45)"></div>
  <div style="position:relative;margin:3vh auto;max-width:1100px;background:#fff;border-radius:8px;padding:12px;box-shadow:0 8px 30px rgba(0,0,0,.2);">
    <header style="display:flex;gap:12px;align-items:center;margin-bottom:8px;">
      <!-- Tabs -->
      <div style="display:flex;gap:6px;">
        <button class="tabBtn btnTab active" data-source="posts" id="tabPosts">Galeri Posts</button>
        <button class="tabBtn btnTab" data-source="profile" id="tabProfile">Galeri Profile</button>
        <button class="tabBtn btnTab" data-source="all" id="tabAll">Semua</button>
      </div>

      <input id="mediaSearch" placeholder="Search files or name..." style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px"/>

      <button id="mediaRefresh" class="btn">Refresh</button>
      <button id="mediaClose" class="btn">Close</button>
    </header>

    <main id="mediaGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;max-height:62vh;overflow:auto;padding:8px;border-top:1px solid #f1f1f1;border-bottom:1px solid #f1f1f1;"></main>

    <footer style="padding-top:8px;display:flex;justify-content:space-between;align-items:center;">
      <div id="mediaPager" style="font-size:.95em;color:#444"></div>
      <div id="mediaStatus" style="color:#666;font-size:.9em"></div>
    </footer>
  </div>
</div>

<style>
  .media-item{border:1px solid #eee;padding:6px;border-radius:6px;display:flex;flex-direction:column;gap:6px;background:#fff}
  .media-thumb{height:96px;object-fit:cover;width:100%;border-radius:4px}
  .small{font-size:12px;padding:6px}
  .btn{padding:8px 10px;border-radius:6px;border:1px solid #ccc;background:#fafafa;cursor:pointer}
  .btn:active{transform:translateY(1px)}
  .btnTab{background:#fff;border:1px solid transparent}
  .btnTab.active{background:#f2f4f7;border-color:#d9dee6}
  .badge-src{display:inline-block;padding:2px 6px;border-radius:4px;font-size:11px;background:#eef; color:#026}
</style>

<script>
/* MediaManager (frontend) with tabs for sources */
(function(){
  const API_BASE = '/dashboard/admin/media';
  let page = 1, per = 24, q = '';
  let activeSource = 'posts'; // default tab
  const modal = document.getElementById('mediaManagerModal');
  const grid = document.getElementById('mediaGrid');
  const searchEl = document.getElementById('mediaSearch');
  const pager = document.getElementById('mediaPager');
  const statusEl = document.getElementById('mediaStatus');
  const refreshBtn = document.getElementById('mediaRefresh');
  const closeBtn = document.getElementById('mediaClose');
  const openBtn = document.getElementById('openMedia');
  const scanPostsBtn = document.getElementById('scanPosts');
  const scanProfileBtn = document.getElementById('scanProfile');
  const scanAllBtn = document.getElementById('scanAll');
  const tabButtons = document.querySelectorAll('.tabBtn');

  openBtn.addEventListener('click', ()=> open());
  closeBtn.addEventListener('click', ()=> close());
  refreshBtn.addEventListener('click', ()=> fetchList());
  scanPostsBtn.addEventListener('click', ()=> scanSource('posts'));
  scanProfileBtn.addEventListener('click', ()=> scanSource('profile'));
  scanAllBtn.addEventListener('click', ()=> scanSource('all'));

  tabButtons.forEach(tb => {
    tb.addEventListener('click', () => {
      tabButtons.forEach(t => t.classList.remove('active'));
      tb.classList.add('active');
      activeSource = tb.dataset.source || 'posts';
      page = 1;
      fetchList();
    });
  });

  function open(initialQ='') {
    q = initialQ || '';
    page = 1;
    searchEl.value = q;
    modal.style.display = 'block';
    fetchList();
  }
  function close(){
    modal.style.display = 'none';
    grid.innerHTML = '';
    pager.innerHTML = '';
    statusEl.textContent = '';
  }

  async function fetchList(){
    grid.innerHTML = '<div>Loading…</div>';
    statusEl.textContent = `Memuat ${activeSource}...`;
    try {
      const res = await fetch(`${API_BASE}/list.php?source=${encodeURIComponent(activeSource)}&q=${encodeURIComponent(q)}&page=${page}&per=${per}`, { credentials:'same-origin' });
      const j = await res.json();
      if (!j.success) { grid.innerHTML = '<div>Error</div>'; statusEl.textContent = j.message || 'Error'; return; }
      renderGrid(j.items || [], j.total || 0);
      statusEl.textContent = `Menampilkan ${j.items.length} dari ${j.total} file (${activeSource})`;
    } catch(e) {
      grid.innerHTML = '<div>Network error</div>';
      statusEl.textContent = 'Network error';
    }
  }

  function renderGrid(items, total){
    grid.innerHTML = '';
    if (!items.length) { grid.innerHTML = '<div>Tidak ada file</div>'; pager.innerHTML=''; return; }
    items.forEach(it => {
      const div = document.createElement('div'); div.className='media-item';
      const img = document.createElement('img'); img.className='media-thumb'; img.src = it.url; img.alt = it.filename;
      const meta = document.createElement('div');
      meta.innerHTML = `<div style="font-size:.85em;word-break:break-word">${it.filename} <span class="badge-src">${it.source ?? activeSource}</span></div>
                        <div style="font-size:.75em;color:#666">${Math.round((it.size/1024))} KB • ${it.uploaded_at}</div>`;
      const actions = document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.justifyContent='space-between';
      const leftGroup = document.createElement('div');
      const btnInsert = document.createElement('button'); btnInsert.className='small'; btnInsert.textContent='Insert';
      const btnCopy = document.createElement('button'); btnCopy.className='small'; btnCopy.textContent='Copy URL';
      leftGroup.append(btnInsert, btnCopy);
      const btnDelete = document.createElement('button'); btnDelete.className='small'; btnDelete.textContent='Hapus';
      actions.append(leftGroup, btnDelete);
      div.append(img, meta, actions);
      grid.appendChild(div);

      btnInsert.addEventListener('click', ()=> {
        if (window.__EDITOR_QUILL) {
          const quill = window.__EDITOR_QUILL;
          const range = quill.getSelection(true);
          quill.insertEmbed(range ? range.index : 0, 'image', it.url, 'user');
          close();
        } else {
          navigator.clipboard.writeText(it.url).then(()=> alert('URL disalin; paste ke editor'));
        }
      });
      btnCopy.addEventListener('click', ()=> {
        navigator.clipboard.writeText(it.url).then(()=> alert('URL disalin'));
      });
      btnDelete.addEventListener('click', async ()=> {
        if (!confirm('Delete this file? This action cannot be undone.')) return;
        try {
          const fd = new FormData();
          if (it.id) fd.append('id', it.id);
          else fd.append('url', it.url);
          const r = await fetch(`${API_BASE}/delete.php`, { method:'POST', body: fd, credentials:'same-origin' });
          const json = await r.json();
          if (json.success) {
            div.remove();
            fetchList();
          } else alert('Failed: '+(json.message||'error'));
        } catch(e) { alert('Network error'); }
      });
    });

    const pages = Math.max(1, Math.ceil(total / per));
    pager.innerHTML = `Hal ${page} dari ${pages} — ${total} file `;
    const left = document.createElement('button'); left.textContent='←'; left.disabled = page <= 1; left.style.marginLeft='8px';
    const right = document.createElement('button'); right.textContent='→'; right.disabled = page >= pages; right.style.marginLeft='6px';
    left.addEventListener('click', ()=> { page = Math.max(1,page-1); fetchList(); });
    right.addEventListener('click', ()=> { page = Math.min(pages,page+1); fetchList(); });
    pager.appendChild(left);
    pager.appendChild(right);
  }

  searchEl.addEventListener('keyup', (e)=> { if (e.key === 'Enter'){ q = searchEl.value.trim(); page=1; fetchList(); } });

  // close on backdrop click
  document.getElementById('mediaBackdrop').addEventListener('click', close);

  // scan source: POST to scan_populate.php with source param
  async function scanSource(source) {
    if (!confirm(`Jalankan scan untuk sumber "${source}"?`)) return;
    try {
      const body = new URLSearchParams(); body.append('source', source);
      const r = await fetch(`${API_BASE}/scan_populate.php`, { method:'POST', body: body, credentials:'same-origin' });
      const j = await r.json();
      if (j.success) {
        alert(`Scan selesai: ${j.total_items} file (lihat console untuk detail)`);
        console.log('scan result', j.results || j);
        // if current active tab is this source or 'all', refresh
        if (activeSource === source || activeSource === 'all') fetchList();
      } else {
        alert('Scan gagal: ' + (j.message || 'error'));
      }
    } catch(err) {
      alert('Network error saat scan');
      console.error(err);
    }
  }

  // expose for debugging
  window.MediaManager = { open: open, fetchList: fetchList, scanSource: scanSource };
})();
</script>

<!-- Auto-register script: watch preview img src or url input set by existing uploader (no change to uploader) -->
<script>
(function(){
  const seen = new Set();
  const API = '/dashboard/admin/media/register_upload.php';
  // accept both asset images and profile registration images
  const isAssetOrProfileUrl = u => {
    if (typeof u !== 'string') return false;
    if (u.indexOf('/assets/img/') === 0) return true;
    if (u.indexOf('/dashboard/profile/static_unchanged/based-registration/') === 0) return true;
    return false;
  };

  function notifyRegister(url) {
    if (!isAssetOrProfileUrl(url) || seen.has(url)) return;
    seen.add(url);
    const body = new URLSearchParams(); body.append('url', url);
    if (navigator.sendBeacon) {
      navigator.sendBeacon(API, body);
    } else {
      fetch(API, { method:'POST', body: body, credentials:'same-origin' }).catch(()=>{});
    }
  }

  // observe attribute changes (img src)
  const mo = new MutationObserver(records => {
    for (const r of records) {
      if (r.type === 'attributes' && r.attributeName === 'src') {
        const el = r.target;
        if (el.tagName === 'IMG') {
          const src = el.getAttribute('src') || '';
          notifyRegister(src);
        }
      }
    }
  });

  const observeImg = img => {
    try { mo.observe(img, {attributes:true, attributeFilter:['src']}); } catch(e){}
  };
  document.querySelectorAll('img').forEach(observeImg);
  new MutationObserver(mr => {
    for (const rec of mr) {
      for (const n of rec.addedNodes) {
        if (n.nodeType === 1) {
          if (n.tagName === 'IMG') observeImg(n);
          n.querySelectorAll && n.querySelectorAll('img').forEach(observeImg);
        }
      }
    }
  }).observe(document.body, {childList:true, subtree:true});

  // also listen to URL inputs (uploader sets url field)
  document.addEventListener('change', e => {
    const t = e.target;
    if (t && t.tagName === 'INPUT') {
      const v = (t.value || '').trim();
      if (isAssetOrProfileUrl(v)) notifyRegister(v);
    }
  });

  // also try to scan existing known inputs on load (in case uploader already set values)
  window.addEventListener('load', ()=> {
    document.querySelectorAll('input[type="text"]').forEach(i => {
      const v = (i.value || '').trim(); if (isAssetOrProfileUrl(v)) notifyRegister(v);
    });
    document.querySelectorAll('img').forEach(img => {
      const s = img.getAttribute('src') || ''; if (isAssetOrProfileUrl(s)) notifyRegister(s);
    });
  });
})();
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Media Manager';
require_once __DIR__ . '/../../partials/layout.php';
