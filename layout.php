<?php
require_once __DIR__ . '/bootstrap.php';

function render_header(string $title): void {
    global $APP_NAME, $EXTRA_HEAD, $HIDE_PAGE_TITLE, $BODY_CLASS;
    $user = current_user();
    $isActiveUser = $user && function_exists('user_active_status') ? user_active_status($user['email']) : (bool)$user;
    $currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    $isActive = function (array $pages) use ($currentPage): string {
        return in_array($currentPage, $pages, true) ? ' active' : '';
    };
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | <?= e($APP_NAME) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
  <?= $EXTRA_HEAD ?? '' ?>
  <style>
    .content-wrapper { min-height: 100vh; }
    .table-sm input { min-width: 82px; }
    .brand-link { font-weight: 700; }
    .progress-overlay {
      align-items: center;
      background: rgba(15, 23, 42, .55);
      bottom: 0;
      display: none;
      justify-content: center;
      left: 0;
      position: fixed;
      right: 0;
      top: 0;
      z-index: 2000;
    }
    .progress-overlay.show { display: flex; }
    .progress-panel {
      background: #fff;
      border-radius: 6px;
      box-shadow: 0 18px 45px rgba(15, 23, 42, .25);
      max-width: 420px;
      padding: 22px;
      width: calc(100% - 32px);
    }
    .progress-panel .progress { height: 18px; }
    .user-status-dot {
      border-radius: 999px;
      display: inline-block;
      height: 9px;
      margin-right: 7px;
      width: 9px;
    }
    .user-status-dot.active { background: #22c55e; }
    .user-status-dot.inactive { background: #ef4444; }
    .nav-sidebar .nav-link.important-input-menu {
      font-size: 1.05rem;
      font-weight: 700;
    }
    .nav-sidebar .nav-link.active {
      background: #2563eb !important;
      color: #fff !important;
      font-weight: 700;
    }
    .nav-sidebar .nav-link:not(.active):hover {
      background: rgba(255, 255, 255, .12);
      color: #fff;
    }
    .password-toggle {
      border-bottom-left-radius: 0;
      border-top-left-radius: 0;
      min-width: 44px;
    }
    .header-logo-group {
      align-items: center;
      display: flex;
      gap: 14px;
      padding: 0 12px;
    }
    .header-logo-bps {
      max-height: 42px;
      max-width: 285px;
      object-fit: contain;
    }
    .header-logo-se {
      max-height: 46px;
      max-width: 170px;
      object-fit: contain;
    }
    .app-footer {
      color: #6b7280;
      font-size: .9rem;
      text-align: center;
    }
    @media (max-width: 767.98px) {
      .header-logo-bps { max-width: 145px; }
      .header-logo-se { max-width: 88px; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini <?= e($BODY_CLASS ?? '') ?>">
<div class="wrapper">
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a></li>
    </ul>
    <ul class="navbar-nav ml-auto">
      <li class="nav-item d-none d-sm-flex header-logo-group">
        <img class="header-logo-bps" src="assets/img/logo-bps-kaltim.png" alt="BPS Provinsi Kalimantan Timur">
        <img class="header-logo-se" src="assets/img/logo_Sensus_Ekonomi_2026.png" alt="Sensus Ekonomi 2026">
      </li>
      <li class="nav-item"><span class="nav-link"><span class="user-status-dot <?= $isActiveUser ? 'active' : 'inactive' ?>"></span><?= e($user['email'] ?? '') ?> <?= $user ? '(' . ($isActiveUser ? 'aktif' : 'tidak aktif') . ')' : '' ?></span></li>
      <?php if ($user): ?>
        <li class="nav-item dropdown">
          <a class="nav-link" data-toggle="dropdown" href="#" aria-label="Pengaturan"><i class="fas fa-gear"></i></a>
          <div class="dropdown-menu dropdown-menu-right">
            <button class="dropdown-item" type="button" data-toggle="modal" data-target="#changePasswordModal"><i class="fas fa-key mr-2"></i>Ganti Password</button>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="logout.php"><i class="fas fa-right-from-bracket mr-2"></i>Logout</a>
          </div>
        </li>
      <?php endif; ?>
    </ul>
  </nav>
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index.php" class="brand-link"><span class="brand-text"><?= e($APP_NAME) ?></span></a>
    <div class="sidebar">
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column">
          <?php if ($user): ?>
            <li class="nav-item"><a class="nav-link<?= $isActive(['index.php', '']) ?>" href="index.php"><i class="nav-icon fas fa-chart-column"></i><p>Dashboard</p></a></li>
            <?php if (in_array($user['role'], ['superadmin','admin_kab'], true)): ?>
              <li class="nav-item"><a class="nav-link important-input-menu<?= $isActive(['input.php']) ?>" href="input.php"><i class="nav-icon fas fa-pen"></i><p>Input Harian</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['superadmin','admin_kab'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['edit.php']) ?>" href="edit.php"><i class="nav-icon fas fa-edit"></i><p>Edit Harian</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['admin_kab','superadmin','viewer_prov','viewer_kab'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['progress_area.php','progress.php']) ?>" href="progress_area.php"><i class="nav-icon fas fa-chart-line"></i><p>Progress Line Chart</p></a></li>
            <?php endif; ?>
            <?php if ($user['role'] === 'pengawas'): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['progress.php']) ?>" href="progress.php?type=pencacah"><i class="nav-icon fas fa-chart-line"></i><p>Progress Line Chart</p></a></li>
            <?php elseif ($user['role'] === 'pencacah'): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['progress.php']) ?>" href="progress.php?type=pencacah"><i class="nav-icon fas fa-chart-line"></i><p>Progress Line Chart</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['admin_kab','superadmin','viewer_prov','viewer_kab','pengawas','pencacah'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['status_view.php']) ?>" href="status_view.php"><i class="nav-icon fas fa-table-list"></i><p class="text-nowrap">Status Assign. by SubSLS</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['admin_kab','superadmin','viewer_prov','viewer_kab'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['rekap_petugas.php','rekap_petugas_weekly.php','rekap_petugas_daily.php']) ?>" href="rekap_petugas.php"><i class="nav-icon fas fa-clipboard-list"></i><p>Rekap Petugas</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['pengawas','pencacah'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['rekap_petugas.php','rekap_petugas_weekly.php']) ?>" href="rekap_petugas.php"><i class="nav-icon fas fa-clipboard-list"></i><p>Rekap Petugas</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['admin_kab','superadmin','pengawas'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['status_selesai.php']) ?>" href="status_selesai.php"><i class="nav-icon fas fa-circle-check"></i><p>Status Selesai SubSLS</p></a></li>
            <?php endif; ?>
            <?php if ($user['role'] === 'superadmin' || ($user['role'] === 'viewer_prov' && normalize_email($user['email'] ?? '') === 'viewer6400@bps.go.id')): ?>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['admin_kab','superadmin'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['weekly_report.php']) ?>" href="weekly_report.php"><i class="nav-icon fas fa-file-lines"></i><p>Weekly Report</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['superadmin','admin_kab'], true)): ?>
              <li class="nav-item"><a class="nav-link" href="upload_petugas.php"><i class="nav-icon fas fa-file-import"></i><p>Upload Master Petugas</p></a></li>
              <li class="nav-item"><a class="nav-link" href="assignment_template.php"><i class="nav-icon fas fa-file-import"></i><p>Upload Alokasi SubSLS</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['admin_kab','viewer_kab','superadmin','viewer_prov'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['master_wilayah.php']) ?>" href="master_wilayah.php"><i class="nav-icon fas fa-map"></i><p>Master Wilayah</p></a></li>
              <li class="nav-item"><a class="nav-link<?= $isActive(['petugas.php']) ?>" href="petugas.php"><i class="nav-icon fas fa-address-book"></i><p>Daftar Petugas</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['superadmin','admin_kab','viewer_prov','viewer_kab'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['assignment.php']) ?>" href="assignment.php"><i class="nav-icon fas fa-user-gear"></i><p>Alokasi SubSLS</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['superadmin','admin_kab'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['export_daily.php']) ?>" href="export_daily.php"><i class="nav-icon fas fa-file-csv"></i><p>Export Data Daily</p></a></li>
            <?php endif; ?>
            <?php if ($user['role'] === 'superadmin'): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['snapshot.php']) ?>" href="snapshot.php"><i class="nav-icon fas fa-calendar-check"></i><p>Isi Snapshot Tanggal</p></a></li>
              <li class="nav-item"><a class="nav-link<?= $isActive(['public_dashboard_update.php']) ?>" href="public_dashboard_update.php"><i class="nav-icon fas fa-globe"></i><p>Dashboard Publik</p></a></li>
              <li class="nav-item"><a class="nav-link<?= $isActive(['performance_update.php']) ?>" href="performance_update.php"><i class="nav-icon fas fa-gauge-high"></i><p>Update Data Performa</p></a></li>
              <li class="nav-item"><a class="nav-link<?= $isActive(['mobile_update.php']) ?>" href="mobile_update.php"><i class="nav-icon fas fa-bullhorn"></i><p>Edit Pop-up Login</p></a></li>
              <li class="nav-item"><a class="nav-link<?= $isActive(['user_passwords.php']) ?>" href="user_passwords.php"><i class="nav-icon fas fa-key"></i><p>Ganti Password User</p></a></li>
              <li class="nav-item"><a class="nav-link<?= $isActive(['backup_database.php']) ?>" href="backup_database.php"><i class="nav-icon fas fa-database"></i><p>Backup Database</p></a></li>
              <li class="nav-item"><a class="nav-link<?= $isActive(['import.php']) ?>" href="import.php"><i class="nav-icon fas fa-file-import"></i><p>Import Master</p></a></li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['pengawas','pencacah'], true)): ?>
              <li class="nav-item"><a class="nav-link<?= $isActive(['subsls_data.php']) ?>" href="subsls_data.php"><i class="nav-icon fas fa-table"></i><p>Data SubSLS</p></a></li>
            <?php endif; ?>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </aside>
  <div class="content-wrapper">
    <?php if (empty($HIDE_PAGE_TITLE)): ?>
      <section class="content-header"><div class="container-fluid"><h1><?= e($title) ?></h1></div></section>
    <?php endif; ?>
    <section class="content"><div class="container-fluid">
      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= nl2br(e($msg)) ?></div><?php endif; ?>
      <?php if ($msg = flash('error')): ?><div class="alert alert-danger"><?= nl2br(e($msg)) ?></div><?php endif; ?>
<?php
}

function render_footer(): void {
global $EXTRA_FOOTER_SCRIPTS;
$user = current_user();
$showMobileUpdateModal = $user
    && !empty($_SESSION['show_mobile_update_modal']);
if ($showMobileUpdateModal) {
    unset($_SESSION['show_mobile_update_modal']);
}
$mobileUpdateContent = $showMobileUpdateModal && function_exists('mobile_update_content') ? mobile_update_content() : [];
?>
    </div></section>
  </div>
  <footer class="main-footer app-footer">
    <i class="far fa-copyright mr-1"></i>2026 Tim SPBE BPS Provinsi Kalimantan Timur
  </footer>
</div>
<?php if (current_user()): ?>
<div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form class="modal-content" method="post" action="change_password.php">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabel">Ganti Password</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Password Lama</label>
          <div class="input-group">
            <input class="form-control" name="old_password" type="password" required autocomplete="current-password">
            <div class="input-group-append"><button class="btn btn-outline-secondary password-toggle" type="button"><i class="fas fa-eye"></i></button></div>
          </div>
        </div>
        <div class="form-group">
          <label>Password Baru</label>
          <div class="input-group">
            <input class="form-control" name="new_password" type="password" required autocomplete="new-password">
            <div class="input-group-append"><button class="btn btn-outline-secondary password-toggle" type="button"><i class="fas fa-eye"></i></button></div>
          </div>
        </div>
        <div class="form-group mb-0">
          <label>Ulangi Password Baru</label>
          <div class="input-group">
            <input class="form-control" name="confirm_password" type="password" required autocomplete="new-password">
            <div class="input-group-append"><button class="btn btn-outline-secondary password-toggle" type="button"><i class="fas fa-eye"></i></button></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" type="submit">Ganti</button>
        <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php if ($showMobileUpdateModal): ?>
<div class="modal fade" id="mobileUpdateModal" tabindex="-1" role="dialog" aria-labelledby="mobileUpdateModalLabel" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="mobileUpdateModalLabel">Informasi Update Aplikasi Fasih Mobile</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="accordion" id="mobileUpdateAccordion">
          <?php foreach ($mobileUpdateContent as $idx => $item): ?>
            <?php
              $collapseId = 'mobileUpdateItem' . $idx;
              $headId = 'mobileUpdateHead' . $idx;
              $isOpen = $idx === 0;
            ?>
            <div class="card <?= $idx === count($mobileUpdateContent) - 1 ? 'mb-0' : 'mb-2' ?>">
              <div class="card-header p-0" id="<?= e($headId) ?>">
                <button class="btn btn-link btn-block text-left font-weight-normal <?= $isOpen ? '' : 'collapsed' ?>" type="button" data-toggle="collapse" data-target="#<?= e($collapseId) ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="<?= e($collapseId) ?>">
                  <strong><?= e($item['title']) ?></strong><?= trim((string)$item['subtitle']) !== '' ? ' ' . e($item['subtitle']) : '' ?>
                </button>
              </div>
              <div id="<?= e($collapseId) ?>" class="collapse <?= $isOpen ? 'show' : '' ?>" aria-labelledby="<?= e($headId) ?>" data-parent="#mobileUpdateAccordion">
                <div class="card-body py-2">
                  <ul class="mb-0 pl-3">
                    <?php foreach ($item['details'] as $detail): ?><li><?= e($detail) ?></li><?php endforeach; ?>
                  </ul>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" type="button" data-dismiss="modal">Mengerti</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<div class="progress-overlay" id="submitProgressOverlay" aria-hidden="true">
  <div class="progress-panel">
    <div class="d-flex align-items-center mb-3">
      <div class="spinner-border text-primary mr-3" role="status"></div>
      <div>
        <strong id="submitProgressTitle">Memproses data...</strong>
        <div class="text-muted small" id="submitProgressText">Mohon tunggu, sistem sedang menyimpan.</div>
      </div>
    </div>
    <div class="progress">
      <div class="progress-bar progress-bar-striped progress-bar-animated" id="submitProgressBar" style="width: 0%">0%</div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<?= $EXTRA_FOOTER_SCRIPTS ?? '' ?>
<?php if ($showMobileUpdateModal): ?>
<script>
$(function () {
  $('#mobileUpdateModal').modal('show');
});
</script>
<?php endif; ?>
<script>
(function () {
  const overlay = document.getElementById('submitProgressOverlay');
  const bar = document.getElementById('submitProgressBar');
  const title = document.getElementById('submitProgressTitle');
  const text = document.getElementById('submitProgressText');
  let timer = null;

  function setProgress(value) {
    const percent = Math.max(0, Math.min(100, Math.round(value)));
    bar.style.width = percent + '%';
    bar.textContent = percent + '%';
  }

  function showProgress(form) {
    const customTitle = form.getAttribute('data-progress-title') || 'Memproses data...';
    const customText = form.getAttribute('data-progress-text') || 'Mohon tunggu, sistem sedang menyimpan.';
    title.textContent = customTitle;
    text.textContent = customText;
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
    setProgress(3);

    let value = 3;
    clearInterval(timer);
    timer = setInterval(function () {
      const step = value < 45 ? 7 : (value < 80 ? 4 : 1);
      value = Math.min(95, value + step);
      setProgress(value);
      if (value >= 95) {
        text.textContent = 'Hampir selesai, menunggu respons server...';
      }
    }, 350);
  }

  function stopProgress() {
    clearInterval(timer);
  }

  function failProgress(message) {
    stopProgress();
    title.textContent = 'Proses gagal';
    text.textContent = message || 'Terjadi kesalahan saat memproses data.';
    bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
    bar.classList.add('bg-danger');
    setProgress(100);
  }

  function resetProgressBarStyle() {
    bar.classList.add('progress-bar-animated', 'progress-bar-striped');
    bar.classList.remove('bg-danger', 'bg-success');
  }

  function showAjaxUploadProgress(form) {
    resetProgressBarStyle();
    const customTitle = form.getAttribute('data-progress-title') || 'Mengupload data...';
    title.textContent = customTitle;
    text.textContent = 'Mengupload file ke server...';
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
    setProgress(1);
    stopProgress();
  }

  function submitAjaxUpload(form) {
    if (form.hasAttribute('data-ajax-upload-batch')) {
      submitAjaxUploadBatch(form);
      return;
    }
    showAjaxUploadProgress(form);
    form.querySelectorAll('button, input[type="submit"]').forEach(function (button) {
      button.disabled = true;
    });

    const xhr = new XMLHttpRequest();
    let processingStarted = false;
    function startProcessingWait() {
      if (processingStarted) return;
      processingStarted = true;
      text.textContent = 'Upload selesai. Server sedang mengimpor CSV...';
      let value = 80;
      setProgress(value);
      stopProgress();
      timer = setInterval(function () {
        value = Math.min(95, value + 1);
        setProgress(value);
        if (value >= 95) {
          text.textContent = 'Masih memproses di server, mohon tunggu...';
        }
      }, 900);
    }
    xhr.open((form.method || 'POST').toUpperCase(), form.action || window.location.href, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.upload.addEventListener('progress', function (event) {
      if (!event.lengthComputable) {
        text.textContent = 'Mengupload file ke server...';
        return;
      }
      const uploadPct = Math.round(event.loaded / event.total * 80);
      setProgress(Math.max(1, uploadPct));
      text.textContent = 'Upload file ' + Math.round(event.loaded / event.total * 100) + '%. Setelah ini server mengimpor CSV.';
      if (event.loaded >= event.total) {
        startProcessingWait();
      }
    });

    xhr.addEventListener('load', function () {
      if (xhr.status >= 200 && xhr.status < 300) {
        stopProgress();
        setProgress(95);
        text.textContent = 'Upload selesai. Server sedang menyimpan hasil dan memuat ulang halaman...';
        let payload = null;
        try {
          payload = JSON.parse(xhr.responseText || '{}');
        } catch (e) {}
        bar.classList.remove('progress-bar-animated');
        bar.classList.add('bg-success');
        setProgress(100);
        window.setTimeout(function () {
          window.location.href = payload && payload.redirect ? payload.redirect : window.location.href;
        }, 450);
        return;
      }

      let message = xhr.status ? 'HTTP ' + xhr.status : 'Upload gagal.';
      try {
        const payload = JSON.parse(xhr.responseText || '{}');
        if (payload.message) message = payload.message;
        if (payload.error) message = payload.error;
      } catch (e) {}
      failProgress(message);
      form.querySelectorAll('button, input[type="submit"]').forEach(function (button) {
        button.disabled = false;
      });
    });

    xhr.addEventListener('error', function () {
      failProgress('Koneksi upload terputus atau ditolak server.');
      form.querySelectorAll('button, input[type="submit"]').forEach(function (button) {
        button.disabled = false;
      });
    });

    xhr.send(new FormData(form));
  }

  function formNonFileData(form) {
    const data = new FormData();
    Array.prototype.forEach.call(form.elements, function (field) {
      if (!field.name || field.disabled || field.type === 'file') return;
      if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
      data.append(field.name, field.value);
    });
    return data;
  }

  function escapeProgressHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function uploadOneBatchFile(form, file, index, total) {
    return new Promise(function (resolve) {
      const xhr = new XMLHttpRequest();
      const formData = formNonFileData(form);
      formData.append('csv_file', file, file.name);
      const basePct = index / total * 100;
      const spanPct = 1 / total * 100;
      let processingStarted = false;

      function setBatchProgress(localPct, message) {
        setProgress(basePct + (Math.max(0, Math.min(100, localPct)) / 100 * spanPct));
        text.textContent = message;
      }

      function startProcessingWait() {
        if (processingStarted) return;
        processingStarted = true;
        let localValue = 82;
        setBatchProgress(localValue, 'File ' + (index + 1) + '/' + total + ' selesai diupload. Server sedang mengimpor: ' + file.name);
        stopProgress();
        timer = setInterval(function () {
          localValue = Math.min(95, localValue + 1);
          setBatchProgress(localValue, 'Server masih mengimpor file ' + (index + 1) + '/' + total + ': ' + file.name);
        }, 900);
      }

      xhr.open((form.method || 'POST').toUpperCase(), form.action || window.location.href, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.addEventListener('progress', function (event) {
        if (!event.lengthComputable) {
          setBatchProgress(10, 'Mengupload file ' + (index + 1) + '/' + total + ': ' + file.name);
          return;
        }
        const uploadPct = Math.round(event.loaded / event.total * 80);
        setBatchProgress(uploadPct, 'Upload file ' + (index + 1) + '/' + total + ' ' + Math.round(event.loaded / event.total * 100) + '%: ' + file.name);
        if (event.loaded >= event.total) {
          startProcessingWait();
        }
      });
      xhr.addEventListener('load', function () {
        stopProgress();
        let payload = null;
        try {
          payload = JSON.parse(xhr.responseText || '{}');
        } catch (e) {}
        if (xhr.status >= 200 && xhr.status < 300) {
          setBatchProgress(100, 'File ' + (index + 1) + '/' + total + ' selesai: ' + file.name);
          resolve({ ok: true, file: file.name, message: payload && payload.message ? payload.message : 'Selesai' });
          return;
        }
        resolve({ ok: false, file: file.name, message: payload && (payload.message || payload.error) ? (payload.message || payload.error) : ('HTTP ' + xhr.status) });
      });
      xhr.addEventListener('error', function () {
        stopProgress();
        resolve({ ok: false, file: file.name, message: 'Koneksi upload terputus atau ditolak server.' });
      });
      xhr.send(formData);
    });
  }

  async function submitAjaxUploadBatch(form) {
    const fileInput = form.querySelector('input[type="file"]');
    const files = fileInput ? Array.prototype.slice.call(fileInput.files || []) : [];
    if (!files.length) return;

    resetProgressBarStyle();
    title.textContent = form.getAttribute('data-progress-title') || 'Mengupload data...';
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
    setProgress(0);
    form.querySelectorAll('button, input[type="submit"]').forEach(function (button) {
      button.disabled = true;
    });

    const results = [];
    for (let i = 0; i < files.length; i++) {
      results.push(await uploadOneBatchFile(form, files[i], i, files.length));
    }

    const success = results.filter(function (item) { return item.ok; }).length;
    const failed = results.length - success;
    stopProgress();
    setProgress(100);
    bar.classList.remove('progress-bar-animated');
    bar.classList.add(failed ? 'bg-danger' : 'bg-success');
    title.textContent = failed ? 'Upload selesai dengan error' : 'Upload selesai';
    const successLines = results
      .filter(function (item) { return item.ok; })
      .slice(0, 3)
      .map(function (item) { return escapeProgressHtml(item.file + ': ' + item.message); });
    const failedLines = results
      .filter(function (item) { return !item.ok; })
      .slice(0, 5)
      .map(function (item) { return escapeProgressHtml(item.file + ': ' + item.message); });
    text.innerHTML = success + ' file sukses, ' + failed + ' file gagal.'
      + (successLines.length ? '<br><strong>Contoh sukses:</strong><br>' + successLines.join('<br>') : '')
      + (failedLines.length ? '<br><strong>Gagal:</strong><br>' + failedLines.join('<br>') : '');
    form.querySelectorAll('button, input[type="submit"]').forEach(function (button) {
      button.disabled = false;
    });
    if (!failed) {
      if (fileInput) fileInput.value = '';
      window.setTimeout(function () {
        window.location.href = form.getAttribute('data-success-url') || window.location.href;
      }, 900);
    }
  }

  document.querySelectorAll('form[data-progress-submit]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.hasAttribute('data-ajax-upload')) {
        event.preventDefault();
        submitAjaxUpload(form);
        return;
      }
      resetProgressBarStyle();
      showProgress(form);
      form.querySelectorAll('button, input[type="submit"]').forEach(function (button) {
        button.disabled = true;
      });
    });
  });

  window.addEventListener('beforeunload', function () {
    if (overlay.classList.contains('show')) {
      clearInterval(timer);
      setProgress(100);
      text.textContent = 'Selesai, memuat halaman hasil...';
    }
  });

  window.addEventListener('pageshow', function () {
    clearInterval(timer);
    resetProgressBarStyle();
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
    setProgress(0);
  });
})();

document.querySelectorAll('.password-toggle').forEach(function (button) {
  button.addEventListener('click', function () {
    const input = this.closest('.input-group').querySelector('input');
    const icon = this.querySelector('i');
    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    icon.classList.toggle('fa-eye', visible);
    icon.classList.toggle('fa-eye-slash', !visible);
  });
});
</script>
</body>
</html>
<?php
}