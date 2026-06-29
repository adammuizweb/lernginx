<?php
// partials/congratulations.php
if (!isset($user)) return;

// cek apakah profil lengkap sekarang (display_name .. nisn)
$checkFields = ['display_name','tempat_lahir','tanggal_lahir','asal_sekolah','tahun_masuk','tingkat_kelas','jurusan','nisn'];
$complete = true;
foreach ($checkFields as $f) {
    if (trim($user[$f] ?? '') === '') { $complete = false; break; }
}
?>

<div id="firstlog-congrats" class="firstlog-congrats" aria-hidden="true">
  <div class="firstlog-congrats-inner" role="dialog" aria-modal="true">
    <div class="firstlog-congrats-svg" aria-hidden="true">
      <!-- check animation -->
      <svg width="120" height="120" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
        <circle cx="60" cy="60" r="54" fill="var(--accent)" opacity="0.12"/>
        <path d="M36 62 L52 78 L86 44" fill="none" stroke="var(--accent)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">
          <animate attributeName="stroke-dasharray" dur="700ms" from="0 200" to="200 0" fill="freeze"/>
        </path>
      </svg>
    </div>
    <h3 class="firstlog-congrats-title">Congratulations — Your profile is complete!</h3>
    <p class="firstlog-congrats-sub">Thank you for filling in your data. Enjoy all the features.</p>
    <div style="text-align:center;margin-top:12px;">
      <button class="firstlog-btn firstlog-btn-primary firstlog-close-congrats">Close</button>
    </div>
  </div>
</div>

<style>
#firstlog-congrats.firstlog-show { display:flex; }
.firstlog-congrats {
  display:none;
  position: fixed;
  inset: 0;
  align-items: center;
  justify-content: center;
  z-index: 1500;
  background: rgba(2,6,23,0.36);
}
.firstlog-congrats-inner {
  width: 100%;
  max-width: 420px;
  background: var(--surface);
  border-radius: 14px;
  padding: 20px;
  text-align: center;
  box-shadow: var(--card-shadow);
  color: var(--text);
}
.firstlog-congrats-svg { margin-bottom:8px; display:flex; justify-content:center; }
.firstlog-congrats-title { margin:8px 0 0; font-size:18px; }
.firstlog-congrats-sub { margin:6px 0 0; color:var(--muted); font-size:13px; }
</style>

<script>
(function(){
  const complete = <?= json_encode($complete ? true : false) ?>;
  document.addEventListener('DOMContentLoaded', function(){
    try {
      const flag = localStorage.getItem('firstlog-submitted');
      if (flag && complete) {
        // show congrats
        const el = document.getElementById('firstlog-congrats');
        if (el) {
          el.style.display = 'flex';
          el.classList.add('firstlog-show');
          localStorage.removeItem('firstlog-submitted');
        }
      } else {
        // ensure flag removed if not complete or too old (optional)
        // If flag older than 5 minutes -> remove
        if (flag) {
          try {
            const ts = parseInt(flag,10);
            if (!isNaN(ts) && (Date.now() - ts) > (1000 * 60 * 30)) {
              localStorage.removeItem('firstlog-submitted');
            }
          } catch(e){}
        }
      }

      const closeBtn = document.querySelector('.firstlog-close-congrats');
      closeBtn?.addEventListener('click', function(){ 
        const el = document.getElementById('firstlog-congrats');
        if (el) { el.style.display='none'; el.classList.remove('firstlog-show'); }
      });
    } catch(e){}
  });
})();
</script>
