function initImageUploader({
  fileInputId,
  uploadBtnId,
  urlInputId,
  previewImgId,
  previewPlaceholderId,
  statusElId,
  notifyElId,
  uploadEndpoint = '/dashboard/admin/upload_assets_img.php',
  allowedTypes = ['image/png','image/jpeg','image/webp'],
  maxSizeKB = 300,
  targetFieldName = 'foto_profil'
}) {
  const fileInput = document.getElementById(fileInputId);
  const uploadBtn = document.getElementById(uploadBtnId);
  const urlInput = document.getElementById(urlInputId);
  const previewImg = document.getElementById(previewImgId);
  const previewPlaceholder = document.getElementById(previewPlaceholderId);
  const statusEl = document.getElementById(statusElId);
  const notifyEl = document.getElementById(notifyElId);

  if (!fileInput || !uploadBtn) return;

  if (!uploadBtn._bound) {
    uploadBtn._bound = true;
    uploadBtn.addEventListener('click', function(){
      const file = fileInput.files && fileInput.files[0];
      if (!file) return showNotify(notifyEl, 'Please select an image file first.', 'error');
      if (!allowedTypes.includes(file.type)) return showNotify(notifyEl, 'Format not allowed.', 'error');
      if (file.size > maxSizeKB * 1024) return showNotify(notifyEl, `File too large (max ${maxSizeKB}KB).`, 'error');

      const fd = new FormData();
      fd.append('file', file);
      statusEl.textContent = 'Uploading...';
      uploadBtn.disabled = true;

      fetch(uploadEndpoint, { method:'POST', body: fd, credentials:'same-origin' })
        .then(async r => {
          uploadBtn.disabled = false;
          statusEl.textContent = '';
          const txt = await r.text();
          let data;
          try { data = JSON.parse(txt); } catch(e){ console.error('Upload not JSON', txt); return showNotify(notifyEl, 'Server error (lihat console)', 'error'); }
          if (data.success) {
            if (previewPlaceholder) previewPlaceholder.style.display = 'none';
            if (previewImg) { previewImg.src = data.url; previewImg.style.display = 'block'; }
            if (urlInput) urlInput.value = data.url;
            document.querySelectorAll(`input[name="${targetFieldName}"]`).forEach(i => i.value = data.url);
            showNotify(notifyEl, 'Upload successful.');
          } else {
            showNotify(notifyEl, data.message || 'Upload failed.', 'error');
          }
        })
        .catch(err => {
          uploadBtn.disabled = false;
          statusEl.textContent = '';
          showNotify(notifyEl, 'Upload failed: ' + (err.message || 'network'), 'error');
        });
    });
  }

  if (urlInput && !urlInput._bound) {
    urlInput._bound = true;
    urlInput.addEventListener('change', function(){
      const v = (urlInput.value || '').trim();
      if (!v) {
        if (previewPlaceholder) previewPlaceholder.style.display = 'block';
        if (previewImg) { previewImg.style.display = 'none'; previewImg.src = ''; }
        return;
      }
      if (previewPlaceholder) previewPlaceholder.style.display = 'none';
      if (previewImg) { previewImg.src = v; previewImg.style.display = 'block'; }
      document.querySelectorAll(`input[name="${targetFieldName}"]`).forEach(i => i.value = v);
    });
  }
}
