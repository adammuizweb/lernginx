<?php
// pageslist.php
// Komponen: daftar halaman (pages) dalam bentuk carousel
// Prefix CSS / class: pageslist-***
// Usage: include 'pageslist.php'; lalu di JS: initNewsCarousel('#pageslist-carousel');
// Expects: optional $pdo PDO object in scope. If not present, show demo items.

$perFetch = $perFetch ?? 12; // boleh override sebelum include
$base = $base ?? '';         // base path situs (mis. '/subdir'), boleh override sebelum include

// helper kecil (sanitize + format tanggal)
function pl_e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function pl_indo_date($datetime){
    $mnames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    try {
        $d = new DateTime($datetime);
        $day = $d->format('d');
        $month = (int)$d->format('m');
        $year = $d->format('Y');
        return $day . ' ' . $mnames[$month-1] . ' ' . $year;
    } catch (Exception $e) {
        return pl_e($datetime);
    }
}

// ambil data dari DB jika $pdo ada
$use_demo = false;
$pages = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $limit = (int)$perFetch;
        if ($limit <= 0) $limit = 12;
        $sql = "
            SELECT id, title, slug, thumbnail, excerpt, created_at
            FROM pages
            WHERE is_deleted = 0
              AND status = 'published'
            ORDER BY created_at DESC
            LIMIT :limit
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$pages) $pages = [];
    } catch (Exception $ex) {
        error_log('pageslist.php error: ' . $ex->getMessage());
        $pages = [];
    }
} else {
    $use_demo = true;
    $pages = [
        ['id'=>1,'title'=>'Contoh Halaman 1','slug'=>'contoh-halaman-1','thumbnail'=>'/assets/demo-page1.jpg','excerpt'=>'Ringkasan halaman 1','created_at'=>'2024-06-05 08:00:00'],
        ['id'=>2,'title'=>'Contoh Halaman 2','slug'=>'contoh-halaman-2','thumbnail'=>'/assets/demo-page2.jpg','excerpt'=>'Ringkasan halaman 2','created_at'=>'2024-05-20 12:00:00'],
        ['id'=>3,'title'=>'Contoh Halaman 3','slug'=>'contoh-halaman-3','thumbnail'=>'/assets/demo-page3.jpg','excerpt'=>'Ringkasan halaman 3','created_at'=>'2024-04-15 09:00:00'],
    ];
}

// fallback image SVG kecil untuk yang tidak ada thumbnail
function pl_thumb_fallback_svg($text = 'No Image', $w = 600, $h = 360){
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'"><rect width="100%" height="100%" fill="#f3f6f8"/><text x="50%" y="50%" fill="#c8d4df" dominant-baseline="middle" text-anchor="middle" font-size="20">'.$text.'</text></svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}
?>

<section class="pageslist-section" aria-labelledby="pageslist-title">
  <div class="pageslist-wrap">
    <div class="pageslist-header">
      <div class="pageslist-underline"></div>
      <h2 id="pageslist-title">Halaman Terbaru</h2>
    </div>

    <div class="pageslist-carousel" id="pageslist-carousel" aria-roledescription="carousel" tabindex="0">
      <div class="pageslist-track" role="list">
        <?php if (empty($pages)): ?>
          <article class="pageslist-card" role="listitem">
            <div class="pageslist-thumb">
              <img src="<?= pl_thumb_fallback_svg('Belum ada halaman') ?>" alt="No pages">
            </div>
            <div class="pageslist-card-body">
              <div class="pageslist-date">--</div>
              <h3 class="pageslist-title">Belum ada halaman yang dipublikasikan.</h3>
            </div>
          </article>
        <?php else: foreach ($pages as $p):
            $thumb = !empty($p['thumbnail']) ? $p['thumbnail'] : pl_thumb_fallback_svg('No Image');
            // url: base + slug (sesuaikan jika sistem URL-mu beda)
            $url = rtrim($base, '/') . '/' . rawurlencode($p['slug']) . '/';
        ?>
        <article class="pageslist-card" role="listitem">
          <div class="pageslist-thumb">
            <img src="<?= pl_e($thumb) ?>" alt="<?= pl_e($p['title']) ?>" loading="lazy">
          </div>
          <div class="pageslist-card-body">
            <div class="pageslist-date"><?= pl_e(pl_indo_date($p['created_at'])) ?></div>
            <h3 class="pageslist-title"><a href="<?= pl_e($url) ?>"><?= pl_e($p['title']) ?></a></h3>
            <?php if (!empty($p['excerpt'])): ?>
              <p class="pageslist-excerpt"><?= pl_e(mb_strimwidth(strip_tags($p['excerpt']), 0, 140, '...')) ?></p>
            <?php endif; ?>
            <div class="pageslist-actions">
              <a class="pageslist-detail-btn" href="<?= pl_e($url) ?>">Buka Halaman</a>
            </div>
          </div>
        </article>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="pageslist-controls">
      <div class="pageslist-dots" id="pageslist-dots" role="tablist" aria-label="Pindah slide"></div>
    </div>

    <div class="pageslist-seeall-wrap">
      <a class="pageslist-seeall-btn" href="#">Lihat Semua Halaman</a>
    </div>
  </div>
</section>

<style>
/* Scoped CSS untuk pageslist-*** (salin ke file CSSmu jika perlu) */
.pageslist-section{ padding:44px 0 56px; font-family:Inter,system-ui,Arial; }
.pageslist-wrap{ max-width:1180px; margin:0 auto; padding:0 18px; text-align:center; }
.pageslist-header{ margin-bottom:18px; }
.pageslist-underline{ width:84px;height:4px;background:#ffd600;margin:0 auto 10px;border-radius:4px; }
.pageslist-header h2{ color:#0a78d8;font-size:28px;margin:0;font-weight:700; }

.pageslist-carousel{ overflow:hidden; position:relative; margin:14px 0 8px; }
.pageslist-track{ display:flex; gap:24px; transition:transform .45s cubic-bezier(.22,.9,.35,1); will-change:transform; padding:6px 10px; touch-action:pan-y; }
.pageslist-card{ background:#fff;border-radius:12px;box-shadow:0 10px 24px rgba(15,32,55,0.06);flex:0 0 300px;display:flex;flex-direction:column;overflow:hidden;min-width:240px; position:relative; }
.pageslist-thumb{ height:160px; overflow:hidden; background:#f6f8fa; display:block; }
.pageslist-thumb img{ width:100%; height:100%; object-fit:cover; display:block; transition: transform .38s cubic-bezier(.2,.9,.3,1); }
.pageslist-card-body{ padding:14px 16px 18px; text-align:left; }
.pageslist-date{ color:#8f99a4; font-size:13px; margin-bottom:8px; }
.pageslist-title{ font-size:15px; margin:0 0 8px; color:#232323; line-height:1.3; font-weight:700; }
.pageslist-title a{ color:inherit; text-decoration:none; }
.pageslist-excerpt{ color:#59656e; font-size:14px; margin:0 0 10px; }
.pageslist-actions{}
.pageslist-detail-btn{ font-size:13px; color:#0a78d8; text-decoration:none; font-weight:600; display:inline-block; padding:6px 0; }

/* dots & see-all */
.pageslist-dots{ display:flex; gap:10px; align-items:center; justify-content:center; margin-top:16px; }
.pageslist-dot{ width:10px;height:10px;border-radius:50%;background:#eaeff5;cursor:pointer;display:inline-block;border:none;padding:0; }
.pageslist-dot[aria-selected="true"]{ background:#0a78d8; transform:scale(1.06); box-shadow:0 4px 12px rgba(10,120,216,0.12); }
.pageslist-seeall-wrap{ margin-top:12px; }
.pageslist-seeall-btn{ display:inline-block;padding:9px 20px;border-radius:8px;border:2px solid #0a78d8;color:#0a78d8;text-decoration:none;font-weight:600;background:white; }

/* hover */
.pageslist-card { transition: transform .24s cubic-bezier(.22,.9,.35,1), box-shadow .24s ease; }
.pageslist-card:hover { transform: translateY(-6px); box-shadow:0 18px 40px rgba(15,32,55,0.12); }
.pageslist-card:hover .pageslist-thumb img { transform: scale(1.05); }

/* responsive */
@media (max-width:640px){
  .pageslist-thumb{ height:150px; }
  .pageslist-card{ border-radius:10px; min-width:220px; }
  .pageslist-header h2{ font-size:20px; }
}
</style>

<!-- Inisialisasi JS: pastikan file JS initNewsCarousel sudah dimuat (dari kode yang kamu beri) -->
<script>
  // Jika kamu ingin kustomisasi, panggil dengan opsi: { autoplayInterval: 4000, transitionMs: 450 }
  if (typeof initNewsCarousel === 'function') {
    // default init untuk komponen ini
    initNewsCarousel('#pageslist-carousel', { autoplayInterval: 4000, transitionMs: 450 });
  } else {
    // jika belum ada fungsi carousel, tidak fatal — kamu bisa load library JS-mu
    console.warn('initNewsCarousel belum terdefinisi. Load file JS carousel sebelum include ini agar otomatis bekerja.');
  }
</script>
