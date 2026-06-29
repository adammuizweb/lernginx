document.addEventListener('DOMContentLoaded', function () {
  const fileInputMain = document.getElementById('fotprof-file');
  const uploadBtnMain = document.getElementById('fotprof-upload-btn');
  if (fileInputMain && uploadBtnMain) {
    fileInputMain.addEventListener('change', function () {
      uploadBtnMain.disabled = !fileInputMain.files.length;
    });
  }

  const fileInputModal = document.getElementById('firstlog-fotprof-file');
  const uploadBtnModal = document.getElementById('firstlog-fotprof-upload-btn');
  if (fileInputModal && uploadBtnModal) {
    fileInputModal.addEventListener('change', function () {
      uploadBtnModal.disabled = !fileInputModal.files.length;
    });
  }
});
// enable upload button when file is selected
