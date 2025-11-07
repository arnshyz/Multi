<?php
require_once __DIR__ . '/auth.php';

auth_session_start();

if (!auth_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$currentUser = (string)($_SESSION['auth_user'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dasboard – AI Hub + Filmmaker + UGC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="/logo.png">
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page" data-theme="light">

<div class="workspace sidebar-open">
  <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="true">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 6h16M4 12h10M4 18h16" stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
    <span class="sidebar-toggle-label">Menu</span>
  </button>
  <div class="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-title">AKAY.IO</div>
    <div class="sidebar-sub">AI Hub • Filmmaker • UGC Tool</div>
  </div>
  <nav class="sidebar-nav">
    <button class="sidebar-link active" data-target="viewDashboard">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Dashboard</span>
    </button>
    <button class="sidebar-link" data-target="viewAccount">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M10.325 4.317a1 1 0 0 1 .987-.817h1.376a1 1 0 0 1 .987.817l.287 1.436a1 1 0 0 0 .96.804l1.45.055a1 1 0 0 1 .939.734l.345 1.31a1 1 0 0 1-.276.98l-1.07 1.026a1 1 0 0 0-.3.95l.332 1.406a1 1 0 0 1-.6 1.141l-1.307.522a1 1 0 0 0-.62.83l-.135 1.452a1 1 0 0 1-.995.915h-1.38a1 1 0 0 1-.994-.915l-.135-1.452a1 1 0 0 0-.62-.83l-1.307-.522a1 1 0 0 1-.6-1.141l.332-1.406a1 1 0 0 0-.3-.95l-1.07-1.026a1 1 0 0 1-.276-.98l.345-1.31a1 1 0 0 1 .939-.734l1.45-.055a1 1 0 0 0 .96-.804z" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"></circle></svg>
      </span>
      <span class="nav-label">Pengaturan Akun</span>
    </button>
    <button class="sidebar-link hidden" data-target="viewDrive" id="driveNavButton">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l1.5 2H19a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" stroke-linecap="round" stroke-linejoin="round"></path><path d="M3 11h18" stroke-linecap="round"></path></svg>
      </span>
      <span class="nav-label">Drive</span>
    </button>
    <button class="sidebar-link" data-target="viewFilm" data-feature="filmmaker">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 6h14a2 2 0 0 1 2 2v10H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm0 0V4m4 2V4m4 2V4m4 2V4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Filmmaker</span>
    </button>
    <button class="sidebar-link" data-target="viewUGC" data-feature="ugc">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 5h16M4 12h16M4 19h16" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">UGC Tool</span>
    </button>
    <div class="sidebar-section">AI Generators</div>
    <button class="sidebar-link" data-target="viewHub" data-feature="imageGen">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.364-6.364-2.121 2.121M8.757 15.243l-2.121 2.121m12.728 0-2.121-2.121M8.757 8.757 6.636 6.636" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Image Generator</span>
    </button>
    <button class="sidebar-link" data-target="viewHub" data-feature="imageEdit">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="m15.232 5.232 3.536 3.536M4 20h4.5L19.768 8.732a2.5 2.5 0 0 0 0-3.536l-1.964-1.964a2.5 2.5 0 0 0-3.536 0L4 16.5V20z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Image Editing</span>
    </button>
    <button class="sidebar-link" data-target="viewHub" data-feature="videoGen">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4.5 6h9a2.5 2.5 0 0 1 2.5 2.5v7a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 2 15.5v-7A2.5 2.5 0 0 1 4.5 6zm11 2.5 6-3v11l-6-3z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Video Generator</span>
    </button>
    <button class="sidebar-link" data-target="viewHub" data-feature="lipsync">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 5a4 4 0 0 1 4 4v2a4 4 0 1 1-8 0V9a4 4 0 0 1 4-4zm0 14v-3m-5 3h10" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Lipsync Studio</span>
    </button>
  </nav>
  <div class="sidebar-actions">
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle theme">☀️</button>
    <div class="profile-card" id="profileCard">
      <div class="profile-main">
        <div class="profile-avatar" id="profileAvatar">FM</div>
        <div class="profile-text">
          <div class="profile-title">
            <span class="profile-display" id="profileDisplay">User</span>
            <span class="profile-badge" id="profileBadge">PRO</span>
          </div>
          <div class="profile-username" id="profileUsername">@username</div>
        </div>
      </div>
      <div class="profile-credit">
        <span class="credit-label">Credit</span>
        <span class="credit-value" id="profileCoins">0</span>
        <span class="credit-status"><span class="status-dot"></span><span id="profileStatusText">Live</span></span>
      </div>
      <button type="button" class="profile-topup" id="profileTopup">Top Up Credit</button>
    </div>
    <button type="button" class="logout-btn" id="logoutButton">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M10 12h11l-3-3m3 3-3 3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      <span class="logout-label">Keluar</span>
    </button>
  </div>
</div>

  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <main class="workspace-main">
    <div class="mobile-coin-banner" id="mobileCoinBanner" role="status" aria-live="polite">
      <div class="mobile-coin-info">
        <span class="mobile-coin-label">Sisa Saldo Kredit</span>
        <span class="mobile-coin-value" id="mobileCoinValue">0</span>
      </div>
      <button type="button" class="topup-badge" id="mobileCoinTopup">Top Up</button>
    </div>
    <div id="viewDashboard" class="dashboard-view app-view">
      <section class="overview-hero">
        <div class="hero-text">
          <h1>Dashboard Overview</h1>
          <p>Selamat datang kembali! Pantau progress generate konten dan saldo koin kamu.</p>
          <div class="profile-main">
          <div class="profile-avatar" id="profileAvatarMobile">FM</div>
          <div class="profile-text">
            <div class="profile-title">
              <span class="profile-display" id="profileDisplayMobile">User</span>
              <span class="verified-badge-star"></span>
              <span class="profile-badge" id="profileBadgeMobile">PRO</span>
            </div>
            <div class="profile-username" id="profileUsernameMobile">@username</div>
          </div>
        </div>
        </div>
        <div class="hero-actions">
          <button type="button" class="profile-topup" id="heroTopup">Top Up Credit</button>
        </div>
      </section>

      <section class="stats-grid">
        <article class="stat-card">
          <span class="stat-label">Saldo Kredit Aktif Kamu</span>
          <span class="stat-value" id="statCoins">0</span>
          <span class="stat-meta">Saldo aktif untuk semua generator</span>
        </article>
        <article class="stat-card">
          <span class="stat-label">Videos Generated</span>
          <span class="stat-value" id="statVideos">0</span>
          <span class="stat-meta">Total job video yang berhasil</span>
        </article>
        <article class="stat-card">
          <span class="stat-label">Images Generated</span>
          <span class="stat-value" id="statImages">0</span>
          <span class="stat-meta">Image & editing task selesai</span>
        </article>
        <article class="stat-card">
          <span class="stat-label">Active Queue</span>
          <span class="stat-value" id="statQueue">0</span>
          <span class="stat-meta">Task yang masih diproses</span>
        </article>
      </section>

      <div class="profile-card profile-card--mobile" id="profileCardMobile">
        
        <div class="profile-credit">
          <span class="credit-label">Credit</span>
          <span class="credit-value" id="profileCoinsMobile">0</span>
          <span class="credit-status"><span class="status-dot"></span><span id="profileStatusMobile">Live</span></span>
        </div>
      </div>
    </div>

    <div id="viewDrive" class="drive-view app-view" hidden>
      <section class="drive-header">
        <div>
          <h1>Creative Drive</h1>
          <p>Simpan dan kelola seluruh hasil generate foto &amp; video kamu di satu tempat.</p>
        </div>
        <div class="drive-meta">
          <span id="driveTotalCount">0 file</span>
          <span>&bull;</span>
          <span id="driveTypeSummary">0 foto • 0 video</span>
        </div>
      </section>

      <section class="drive-filters">
        <div class="drive-filter">
          <label for="driveTypeFilter">Tipe konten</label>
          <select id="driveTypeFilter">
            <option value="all">Semua</option>
            <option value="image">Foto</option>
            <option value="video">Video</option>
          </select>
        </div>
        <div class="drive-filter">
          <label for="driveDateFilter">Tanggal</label>
          <input type="date" id="driveDateFilter">
        </div>
        <div class="drive-filter">
          <label for="driveSortFilter">Urutkan</label>
          <select id="driveSortFilter">
            <option value="newest">Terbaru</option>
            <option value="oldest">Terlama</option>
          </select>
        </div>
        <button type="button" class="small secondary drive-clear-date" id="driveClearDate">Reset tanggal</button>
      </section>

      <section class="drive-content">
        <div id="driveEmpty" class="drive-empty">Belum ada file tersimpan. Generate konten untuk mengisi drive pribadi kamu.</div>
        <div id="driveGrid" class="drive-grid"></div>
      </section>
    </div>

    <div id="viewAccount" class="account-view app-view" hidden>
      <section class="account-hero">
        <div>
          <h1>Pengaturan Akun</h1>
          <p>Kelola foto profil dan keamanan password kamu dari satu tempat.</p>
        </div>
      </section>

      <section class="account-settings" aria-labelledby="accountSettingsTitle">
        <div class="card-soft account-settings-card">
          <div class="header" id="accountSettingsTitle">
            <div>
              <div class="title" style="font-size:16px">Profil &amp; Keamanan</div>
              <div class="subtitle">Perbarui avatar serta ubah password secara aman.</div>
            </div>
          </div>

          <div class="account-settings-grid">
            <form id="avatarForm" class="account-form" novalidate>
              <h3 class="account-form-title">Foto Profil</h3>
              <div class="avatar-row">
                <div class="avatar-preview" id="avatarPreview" aria-hidden="true">
                  <span class="avatar-initials" id="avatarPreviewInitials">FM</span>
                  <img id="avatarPreviewImage" alt="Preview foto profil" loading="lazy">
                </div>
                <div class="avatar-fields">
                  <label class="account-label" for="avatarUrlInput">Link gambar</label>
                  <input type="url" id="avatarUrlInput" placeholder="https://contoh.com/avatar.jpg" autocomplete="off">
                  <div class="account-form-actions">
                    <input type="file" id="avatarFileInput" accept="image/*" style="display:none">
                    <button type="button" class="secondary small" id="avatarUploadBtn">Upload foto</button>
                    <button type="button" class="secondary small" id="avatarRemoveBtn">Hapus</button>
                  </div>
                  <p class="account-form-hint">Disarankan gambar persegi minimal 200×200px.</p>
                  <button type="submit" class="account-save-btn">Simpan Avatar</button>
                  <div class="account-form-status" id="avatarFormStatus" role="status"></div>
                </div>
              </div>
            </form>

            <form id="passwordForm" class="account-form" novalidate>
              <h3 class="account-form-title">Ganti Password</h3>
              <div class="account-form-grid">
                <label class="account-label" for="currentPasswordInput">Password saat ini</label>
                <input type="password" id="currentPasswordInput" autocomplete="current-password" required>

                <label class="account-label" for="newPasswordInput">Password baru</label>
                <input type="password" id="newPasswordInput" autocomplete="new-password" minlength="6" required>

                <label class="account-label" for="confirmPasswordInput">Konfirmasi password</label>
                <input type="password" id="confirmPasswordInput" autocomplete="new-password" minlength="6" required>
              </div>
              <p class="account-form-hint">Gunakan minimal 6 karakter kombinasi huruf & angka.</p>
              <button type="submit" class="account-save-btn">Update Password</button>
              <div class="account-form-status" id="passwordFormStatus" role="status"></div>
            </form>
          </div>
        </div>
      </section>
    </div>

<!-- ======================= AI HUB ======================= -->
<div id="viewHub" class="hub-app app-view" hidden>
  <div class="hub-column">
    <div class="card">
      <div class="header">
        <div>
          <div class="title">AKAY-AI Studio</div>
          <div class="subtitle">
            <span id="featureLabel">Image Generator</span> · Single PHP • Multi model Freepik
          </div>
        </div>
        <div class="badge">
          <span class="dot-large"></span>
          <span>Proxy PHP aktif</span>
        </div>
      </div>
    </div>

    <div class="card-soft hub-model-card">
      <div class="select-group">
        <div class="model-group-label">Model</div>
        <select id="modelSelect">
          <optgroup label="Text → Image">
            <option value="gemini">Gemini 2.5 Flash</option>
            <option value="imagen3">Google Imagen 3</option>
            <option value="seedream4">Seedream 4</option>
            <option value="seedream4edit">Seedream 4 Edit</option>
            <option value="fluxPro11">Flux Pro v1.1</option>
          </optgroup>
          <optgroup label="Upscale / Edit">
            <option value="upscalerCreative">Upscaler Creative</option>
            <option value="upscalePrecV1">Upscale Precision V1</option>
            <option value="upscalePrecV2">Upscale Precision V2</option>
            <option value="removeBg">Remove Background</option>
          </optgroup>
          <optgroup label="Image → Video">
            <option value="wan480">Wan v2.2 – 480p</option>
            <option value="wan720">Wan v2.2 – 720p</option>
            <option value="seedancePro480">Seedance Pro – 480p</option>
            <option value="seedancePro720">Seedance Pro – 720p</option>
            <option value="seedancePro1080">Seedance Pro – 1080p</option>
            <option value="klingStd21">Kling Std v2.1</option>
            <option value="kling25Pro">Kling v2.5 Pro</option>
            <option value="minimax1080">MiniMax Hailuo 02 – 1080p</option>
          </optgroup>
          <optgroup label="Lip Sync">
            <option value="latentSync">Latent-Sync</option>
          </optgroup>
        </select>
      </div>
      <div class="small-label">Hint input</div>
      <div id="modelHint" class="muted" style="font-size:11px">
        Text→Image: cukup prompt.
        Upscale/remove BG: wajib image URL.
        Image→Video: image URL + prompt.
        Latent-Sync: video URL + audio URL.
      </div>
    </div>

    <div class="card hub-form-card">
      <form id="jobForm">
        <div class="two-col">
          <div id="rowPrompt">
            <label for="prompt">Prompt</label>
            <textarea id="prompt" placeholder="Deskripsikan gambar/video yang diinginkan"></textarea>
          </div>

          <div>
            <div id="fieldsTitle" class="form-section-title">Image Generator</div>

          <div id="geminiModeSection" class="gemini-mode-section hidden">
            <div class="form-section-title">PILIH MODE</div>
            <div class="gemini-mode-toggle">
              <button type="button" class="gemini-mode-btn active" data-gemini-mode="text">
                <strong>Text-to-Image</strong>
              </button>
              <button type="button" class="gemini-mode-btn" data-gemini-mode="single">
                <strong>Image-to-Image</strong>
              </button>
              <button type="button" class="gemini-mode-btn" data-gemini-mode="multi">
                <strong>⭐ Multi-Image Reference</strong>
              </button>
            </div>
            <div id="geminiModeDescription" class="gemini-mode-desc">
              Mode 1: Text-to-Image — Masukkan prompt deskriptif tanpa gambar.
            </div>
          </div>

          <div id="geminiReferenceSection" class="gemini-reference-section hidden">
            <div class="form-section-title">Reference Images</div>
            <div class="gemini-dropzone" id="geminiDropzone">
              <input id="geminiFileInput" type="file" accept="image/*" multiple style="display:none">
              <div class="gemini-dropzone-info">
                <strong>Upload referensi</strong>
                <span class="gemini-ref-helper" id="geminiRefHelper">Mode 2 membutuhkan 1 gambar referensi.</span>
                <span style="font-size:10px; opacity:0.8;">Drag &amp; drop, klik, atau paste (Ctrl+V) saat mode aktif.</span>
                <span style="font-size:11px; color:var(--text);">Dipilih: <b id="geminiDropCounter">0/1</b></span>
              </div>
              <div class="gemini-dropzone-actions">
                <button type="button" class="small secondary" id="geminiFileButton">Pilih file</button>
              </div>
            </div>
            <div class="gemini-ref-add field-row">
              <input id="geminiRefUrl" type="text" placeholder="https://...jpg">
              <button type="button" class="small" id="geminiRefAddBtn">Tambah URL</button>
            </div>
            <div class="gemini-ref-list" id="geminiRefList"></div>
          </div>

          <div id="rowImageUrl">
            <label for="imageUrl">Image URL</label>
            <input id="imageUrl" type="text" placeholder="https://...jpg / .png">
            <div class="upload-area" id="imageUploadArea">
              <input id="imageUploadInput" type="file" accept="image/*" style="display:none">
              <div class="upload-dropzone" id="imageUploadDropzone">
                <div class="upload-dropzone-content">
                  <strong>Upload langsung ke server</strong>
                  <span>Drag & drop, klik, atau paste gambar. URL akan terisi otomatis.</span>
                  <span style="opacity:0.8">Format JPG, PNG, WEBP hingga 15MB.</span>
                </div>
                <div class="upload-dropzone-actions">
                  <img id="imageUploadPreview" class="upload-preview" style="display:none" alt="Preview upload">
                  <button type="button" class="small secondary" id="imageUploadButton">Pilih file</button>
                </div>
              </div>
              <div class="upload-status" id="imageUploadStatus"></div>
            </div>
          </div>

          <div id="rowVideoAudio" class="field-row hidden" style="margin-top:4px">
            <div>
              <label for="videoUrl">Video URL (Lipsync)</label>
              <input id="videoUrl" type="text" placeholder="https://...mp4">
            </div>
            <div>
              <label for="audioUrl">Audio URL (Lipsync)</label>
              <input id="audioUrl" type="text" placeholder="https://...mp3 / .wav">
            </div>
          </div>

          <div id="rowVideoSettings" class="field-row hidden" style="margin-top:4px">
            <div>
              <label for="videoDuration">Durasi video</label>
              <select id="videoDuration"></select>
            </div>
            <div>
              <label for="videoLayout">Layout</label>
              <select id="videoLayout"></select>
            </div>
          </div>

          <div id="rowTIOptions" class="field-row hidden" style="margin-top:4px">
            <div>
              <label for="numImages">Jumlah image</label>
              <input id="numImages" type="number" min="1" max="4" value="1">
            </div>
            <div>
              <label for="aspectRatio">Aspect ratio</label>
              <select id="aspectRatio">
                <option value="">Auto</option>
                <option value="square_1_1">1:1</option>
                <option value="portrait_3_4">3:4</option>
                <option value="portrait_9_16">9:16</option>
                <option value="landscape_16_9">16:9</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="btn-group">
        <button type="submit" id="submitBtn">Jalankan task</button>
        <button type="button" class="secondary" id="clearPromptBtn">Clear form</button>
      </div>

        <div class="status-bar">
          <div class="status-text" id="statusText">Siap.</div>
          <div class="status-pill" id="statusPill">IDLE</div>
          <div class="status-progress" id="statusProgressWrapper">
            <div class="status-progress-label">Progress <span id="statusPercent">0%</span></div>
            <div class="progress-track">
              <div class="progress-fill" id="statusProgressFill"></div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <div class="card-soft preview-card">
      <div class="header" style="margin-bottom:6px">
        <div>
          <div class="title" style="font-size:14px">Status & Preview</div>
          <div class="subtitle">Preview kecil + tombol download</div>
        </div>
        <button type="button" class="small secondary" id="clearPreviewBtn">Clear preview</button>
      </div>

      <div id="previewEmpty" class="muted" style="font-size:11px">
        Belum ada hasil. Jalankan task atau klik "View" dari history.
      </div>

      <div id="previewContainer" style="display:none">
        <div class="small-label">Job aktif</div>
        <div id="previewJobMeta" class="muted" style="font-size:11px;margin-bottom:6px"></div>
        <div class="preview-progress" id="previewProgress">
          <div class="status-progress-label">Progress <span id="previewProgressPercent">0%</span></div>
          <div class="progress-track">
            <div class="progress-fill" id="previewProgressFill"></div>
          </div>
        </div>
        <div class="preview-grid" id="previewGrid"></div>
      </div>
    </div>
  </div>

  <div class="jobs-col hub-side">
    <div class="card-soft">
      <div class="header" style="margin-bottom:6px">
        <div>
          <div class="title" style="font-size:14px">Queue</div>
          <div class="subtitle">Task aktif</div>
        </div>
        <button type="button" class="small secondary" id="refreshQueueBtn">Refresh</button>
      </div>
      <div id="queueList" class="jobs-list"></div>
      <div id="queueEmpty" class="muted" style="font-size:11px">Queue kosong.</div>
    </div>

    <div class="card-soft">
      <div class="header" style="margin-bottom:6px">
        <div>
          <div class="title" style="font-size:14px">History</div>
          <div class="subtitle">Selesai / gagal (localStorage)</div>
        </div>
        <button type="button" class="small secondary" id="clearHistoryBtn">Clear history</button>
      </div>
      <div id="historyList" class="jobs-list"></div>
      <div id="historyEmpty" class="muted" style="font-size:11px">Belum ada history.</div>
    </div>
  </div>
</div>

<!-- ======================= FILMMAKER ======================= -->
<div id="viewFilm" class="film-app app-view" hidden>
  <div class="card">
    <div class="header">
      <div>
        <div class="title">Filmmaker</div>
        <div class="subtitle">Buat rangkaian scene sinematik dari satu karakter & story brief</div>
      </div>
      <div class="badge">
        <span>Gemini Flash 2.5</span>
      </div>
    </div>
    <div class="film-scenes-board">
      <div id="filmScenesEmpty" class="film-empty-state">
        <div>
          <div class="film-empty-icon">🎬</div>
          <div class="subtitle">Hasil Generate Akan Muncul Disini</div>
          <div class="muted" style="font-size:11px">
            Upload character image dan isi story brief di sisi kanan, lalu klik “Generate Scenes”.
          </div>
        </div>
      </div>
      <div id="filmScenesContainer" class="film-scenes-container"></div>
    </div>
  </div>

  <div class="card-soft">
    <div class="header" style="margin-bottom:8px">
      <div>
        <div class="title" style="font-size:14px">Film Settings</div>
        <div class="subtitle">Create cinematic scenes</div>
      </div>
    </div>

    <div class="film-settings-section">
      <div>
        <div class="small-label">Character Reference</div>
        <div id="filmCharacterDrop" class="film-dropzone">
          <input id="filmCharacterInput" type="file" accept="image/*" style="display:none">
          <div id="filmCharacterIdle" class="film-drop-inner">
            <div style="margin-bottom:4px;">Upload Foto Kamu</div>
            <span>PNG, JPG · rekomendasi foto Close UP</span>
          </div>
          <img id="filmCharacterPreview" class="film-character-preview" style="display:none" alt="Character preview">
        </div>
      </div>

<div>
        <div class="small-label">Filmmaker State Custom</div>
        <div id="filmStatePicker" class="ugc-style-picker film-state-picker">
          <button type="button" id="filmStateTrigger" class="ugc-style-trigger">
            <div class="ugc-style-trigger-main">
              <span id="filmStateIcon" class="ugc-style-icon">🎬</span>
              <div class="ugc-style-trigger-text">
                <div id="filmStateLabel" class="ugc-style-label">AUTO STATE</div>
                <div id="filmStateDescription" class="ugc-style-description">Tidak Memilih · diproses oleh server</div>
              </div>
            </div>
            <span class="ugc-style-caret">▾</span>
          </button>
          <div id="filmStateMenu" class="ugc-style-menu hidden"></div>
          <input type="hidden" id="filmStateValue" value="auto">
        </div>
      </div>

      <div>
        <label for="filmBrief">Story Brief</label>
        <textarea id="filmBrief" placeholder="Contoh: Seorang detektif cyberpunk menyelidiki kasus misteri di kota neon saat hujan malam..."></textarea>
      </div>

      <div>
        <div class="film-slider-row">
          <div class="small-label">Number of Scenes</div>
          <div id="filmSceneCountLabel" class="small-label" style="text-align:right">6 scenes</div>
        </div>
        <input id="filmSceneCount" type="range" min="2" max="12" value="6">
      </div>

      <div>
        <div class="small-label" style="margin-bottom:4px;">Aspect Ratio</div>
        <div class="film-aspect-toggle">
          <button type="button" class="film-aspect-btn film-aspect-active" data-film-aspect="9:16">9:16 Vertical</button>
          <button type="button" class="film-aspect-btn" data-film-aspect="16:9">16:9 Cinema</button>
        </div>
      </div>

      

      <div>
        <button type="button" id="filmGenerateBtn" style="width:100%;margin-top:4px;">
          Generate Scenes
        </button>
        <div class="muted" style="font-size:10px;margin-top:6px;">
          Setiap scene akan memanggil model Gemini Flash 2.5 secara terpisah. Progress scene akan muncul di panel kiri.
        </div>
        <div class="progress-inline" id="filmProgress">
          <div class="progress-label"><span>Progress</span><span id="filmProgressValue">0%</span></div>
          <div class="progress-bar">
            <div class="progress-fill" id="filmProgressFill"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ======================= UGC TOOL ======================= -->
<div id="viewUGC" class="ugc-app app-view" hidden>
  <div class="card-soft">
    <div class="header" style="margin-bottom:6px">
      <div>
        <div class="title" style="font-size:16px">UGC Prompt Generator</div>
        <div class="subtitle">AI-powered UGC photography prompts from your product images</div>
      </div>
    </div>

    <div id="ugcList" class="ugc-list-card">
      <div id="ugcEmpty" class="ugc-empty">
        No UGC images generated yet<br>
        Upload product images dan klik <b>Generate UGC</b>.
      </div>
    </div>
  </div>

  <div class="card-soft">
    <div class="header" style="margin-bottom:8px">
      <div>
        <div class="title" style="font-size:14px">Upload &amp; Settings</div>
        <div class="subtitle">Configure your prompts</div>
      </div>
    </div>

    <div class="film-settings-section">
      <div>
        <div class="small-label">Product Images (max 3)</div>
        <div class="film-dropzone" id="ugcProductDrop">
          <input id="ugcProductInput" type="file" accept="image/*" multiple style="display:none">
          <div class="film-drop-inner">
            <div style="margin-bottom:4px;">Click, drag &amp; drop, atau paste</div>
            <span>PNG, JPG · max 3 images (3 Foto Produk Kamu)</span>
          </div>
        </div>
        <div id="ugcProductPreview" class="ugc-product-preview"></div>
      </div>

      <div>
        <div class="small-label">Model Image (Optional)</div>
        <div class="film-dropzone" id="ugcModelDrop">
          <input id="ugcModelInput" type="file" accept="image/*" style="display:none">
          <div id="ugcModelIdle" class="film-drop-inner">
            <div style="margin-bottom:4px;">Click, drag &amp; drop, atau paste</div>
            <span>PNG, JPG · 1 image (1 Foto Model)</span>
          </div>
          <img id="ugcModelPreview" class="film-character-preview" style="display:none" alt="Model preview">
        </div>
      </div>

      <div>
        <div class="small-label">Prompt Style</div>
        <div id="ugcStylePicker" class="ugc-style-picker">
          <button type="button" id="ugcStyleTrigger" class="ugc-style-trigger">
            <div class="ugc-style-trigger-main">
              <span id="ugcStyleIcon" class="ugc-style-icon">✨</span>
              <div class="ugc-style-trigger-text">
                <div id="ugcStyleLabel" class="ugc-style-label">Basic</div>
                <div id="ugcStyleDescription" class="ugc-style-description">Diverse &amp; Flexible contexts</div>
              </div>
            </div>
            <span class="ugc-style-caret">▾</span>
          </button>
          <div id="ugcStyleMenu" class="ugc-style-menu hidden"></div>
          <input type="hidden" id="ugcStyleValue" value="basic">
        </div>
      </div>

      <div>
        <div class="small-label">Product Brief (Optional)</div>
        <textarea id="ugcBrief" placeholder="Contoh: Mempromosikan botol air berkelanjutan untuk penggemar kebugaran, menekankan gaya hidup ramah lingkungan dan aktivitas luar ruangan"></textarea>
      </div>

      <div>
        <button type="button" id="ugcGenerateBtn" style="width:100%;margin-top:4px;">
          Generate UGC
        </button>
        <div class="muted" style="font-size:10px;margin-top:6px;">
          Sistem akan membuat 5 ide UGC beserta gambar dari Gemini Flash 2.5.
          Tiap baris punya prompt video + tombol Generate Video (Wan 720) &amp; Download.
        </div>
        <div class="progress-inline" id="ugcProgress">
          <div class="progress-label"><span>Progress</span><span id="ugcProgressValue">0%</span></div>
          <div class="progress-bar">
            <div class="progress-fill" id="ugcProgressFill"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

</main>
</div>

<div id="assetPreviewModal" class="asset-preview hidden">
  <div class="asset-preview-inner">
    <button type="button" id="assetPreviewClose" class="asset-preview-close">&times;</button>
    <div id="assetPreviewBody" class="asset-preview-body"></div>
    <a id="assetPreviewDownload" class="asset-preview-download" href="#" download target="_blank">Download file</a>
  </div>
</div>

<div class="maintenance-overlay" id="maintenanceOverlay">
  <div class="maintenance-overlay__content">
    <div class="maintenance-overlay__icon">🛠️</div>
    <h2>Website Sedang Maintenance</h2>
    <p id="maintenanceOverlayMessage">Kami sedang melakukan perawatan sistem. Silakan kembali beberapa saat lagi.</p>
  </div>
</div>

<div class="topup-modal" id="topupModal">
  <div class="topup-modal__dialog">
    <button type="button" class="topup-modal__close" id="topupCloseBtn">&times;</button>
    <h3 class="topup-modal__title">Pilih nominal Top Up</h3>
    <div class="topup-modal__options" id="topupOptions"></div>
    <button type="button" class="topup-badge" id="topupConfirmBtn" disabled>Top Up via WhatsApp</button>
    <div class="topup-modal__hint" id="topupHint">Pilih nominal untuk lanjut ke WhatsApp.</div>
  </div>
</div>

<script>
  const themeToggle = document.getElementById('themeToggle');
  const profileCard = document.getElementById('profileCard');
  const profileDisplayEl = document.getElementById('profileDisplay');
  const profileUsernameEl = document.getElementById('profileUsername');
  const profileCoinsEl = document.getElementById('profileCoins');
  const profileBadgeEl = document.getElementById('profileBadge');
  const profileAvatarEl = document.getElementById('profileAvatar');
  const profileStatusTextEl = document.getElementById('profileStatusText');
  const profileCardMobile = document.getElementById('profileCardMobile');
  const profileDisplayMobile = document.getElementById('profileDisplayMobile');
  const profileUsernameMobile = document.getElementById('profileUsernameMobile');
  const profileBadgeMobile = document.getElementById('profileBadgeMobile');
  const profileAvatarMobile = document.getElementById('profileAvatarMobile');
  const profileCoinsMobile = document.getElementById('profileCoinsMobile');
  const profileStatusMobileEl = document.getElementById('profileStatusMobile');
  const mobileCoinBanner = document.getElementById('mobileCoinBanner');
  const mobileCoinValue = document.getElementById('mobileCoinValue');
  const mobileCoinTopup = document.getElementById('mobileCoinTopup');
  const avatarForm = document.getElementById('avatarForm');
  const avatarUrlInput = document.getElementById('avatarUrlInput');
  const avatarFileInput = document.getElementById('avatarFileInput');
  const avatarUploadBtn = document.getElementById('avatarUploadBtn');
  const avatarRemoveBtn = document.getElementById('avatarRemoveBtn');
  const avatarFormStatus = document.getElementById('avatarFormStatus');
  const avatarPreviewImage = document.getElementById('avatarPreviewImage');
  const avatarPreviewInitials = document.getElementById('avatarPreviewInitials');
  const passwordForm = document.getElementById('passwordForm');
  const currentPasswordInput = document.getElementById('currentPasswordInput');
  const newPasswordInput = document.getElementById('newPasswordInput');
  const confirmPasswordInput = document.getElementById('confirmPasswordInput');
  const passwordFormStatus = document.getElementById('passwordFormStatus');
  const logoutButton = document.getElementById('logoutButton');
  const workspace = document.querySelector('.workspace');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const mobileSidebarQuery = typeof window.matchMedia === 'function'
    ? window.matchMedia('(max-width: 960px)')
    : { matches: false };
  const statCoinsEl = document.getElementById('statCoins');
  const statVideosEl = document.getElementById('statVideos');
  const statImagesEl = document.getElementById('statImages');
  const statQueueEl = document.getElementById('statQueue');
  const driveNavButton = document.getElementById('driveNavButton');
  const driveGrid = document.getElementById('driveGrid');
  const driveEmpty = document.getElementById('driveEmpty');
  const driveTypeFilter = document.getElementById('driveTypeFilter');
  const driveSortFilter = document.getElementById('driveSortFilter');
  const driveDateFilter = document.getElementById('driveDateFilter');
  const driveClearDateBtn = document.getElementById('driveClearDate');
  const driveTotalCountEl = document.getElementById('driveTotalCount');
  const driveTypeSummaryEl = document.getElementById('driveTypeSummary');
  const maintenanceOverlayEl = document.getElementById('maintenanceOverlay');
  const maintenanceOverlayMessageEl = document.getElementById('maintenanceOverlayMessage');
  const topupModalEl = document.getElementById('topupModal');
  const topupCloseBtn = document.getElementById('topupCloseBtn');
  const topupOptionsEl = document.getElementById('topupOptions');
  const topupConfirmBtn = document.getElementById('topupConfirmBtn');
  const topupHintEl = document.getElementById('topupHint');
  const topupOpeners = [
    document.getElementById('profileTopup'),
    document.getElementById('heroTopup'),
    mobileCoinTopup,
    document.getElementById('profileTopupMobile')
  ].filter(Boolean);
  const filmProgressEl = document.getElementById('filmProgress');
  const filmProgressFill = document.getElementById('filmProgressFill');
  const filmProgressValue = document.getElementById('filmProgressValue');
  const ugcProgressEl = document.getElementById('ugcProgress');
  const ugcProgressFill = document.getElementById('ugcProgressFill');
  const ugcProgressValue = document.getElementById('ugcProgressValue');

  const API_BASE = 'index.php';
  const ACCOUNT_ENDPOINT = `${API_BASE}?api=account`;
  const ACCOUNT_THEME_ENDPOINT = `${API_BASE}?api=account-theme`;
  const ACCOUNT_COINS_ENDPOINT = `${API_BASE}?api=account-coins`;
  const LOGOUT_ENDPOINT = `${API_BASE}?api=logout`;
  const DRIVE_ENDPOINT = `${API_BASE}?api=drive`;
  const DRIVE_DELETE_ENDPOINT = `${API_BASE}?api=drive-delete`;
  const ACCOUNT_AVATAR_ENDPOINT = `${API_BASE}?api=account-avatar`;
  const ACCOUNT_PASSWORD_ENDPOINT = `${API_BASE}?api=account-password`;
  const FREEPIK_ENDPOINT = `${API_BASE}?api=freepik`;
  const CACHE_ENDPOINT = `${API_BASE}?api=cache`;
  const UPLOAD_ENDPOINT = `${API_BASE}?api=upload`;

  const TOPUP_AMOUNTS = [10, 20, 30, 40, 50, 100, 150, 200];
  const TOPUP_WHATSAPP = 'https://wa.me/62818404222';
  const PLATFORM_FEATURE_META = {
    imageGen: { label: 'Image Generator' },
    imageEdit: { label: 'Image Editing' },
    videoGen: { label: 'Video Generator' },
    lipsync: { label: 'Lipsync Studio' },
    filmmaker: { label: 'Filmmaker' },
    ugc: { label: 'UGC Tool' }
  };
  const HUB_FEATURE_KEYS = ['imageGen', 'imageEdit', 'videoGen', 'lipsync'];

  let currentAccount = null;
  let currentTheme = 'light';
  let platformState = defaultPlatformState();
  let selectedTopupAmount = null;

  const COIN_COST_STANDARD = 1;
  const COIN_COST_FILM_PER_SCENE = 1;
  const COIN_COST_UGC = 1;
  let jobs = [];
  let modelConfigMap = {};
  let driveItems = [];
  let driveLoaded = false;
  let driveLoading = false;

  function defaultPlatformState() {
    return {
      maintenance: { active: false, message: '', updated_at: null },
      generators: {}
    };
  }

  function normalizePlatformState(platform) {
    const state = defaultPlatformState();
    if (!platform || typeof platform !== 'object') {
      return state;
    }
    if (platform.maintenance && typeof platform.maintenance === 'object') {
      const maint = platform.maintenance;
      state.maintenance = {
        active: !!maint.active,
        message: maint.message ? String(maint.message) : '',
        updated_at: maint.updated_at || null
      };
    }
    const items = Array.isArray(platform.generators)
      ? platform.generators
      : (platform.generators && typeof platform.generators === 'object'
          ? Object.values(platform.generators)
          : []);
    items.forEach(item => {
      if (!item || typeof item !== 'object') return;
      const key = item.key || item.id;
      if (!key) return;
      state.generators[key] = {
        label: item.label || PLATFORM_FEATURE_META[key]?.label || key,
        enabled: item.enabled !== false,
        description: item.description || '',
        updated_at: item.updated_at || null
      };
    });
    return state;
  }

  function getFeatureLabel(featureKey) {
    if (!featureKey) return 'Generator';
    if (platformState.generators[featureKey] && platformState.generators[featureKey].label) {
      return platformState.generators[featureKey].label;
    }
    return PLATFORM_FEATURE_META[featureKey]?.label || featureKey;
  }

  function featureAvailableForCurrentUser(featureKey) {
    if (!featureKey) return true;
    if (currentAccount && currentAccount.role === 'admin') {
      return true;
    }
    const entry = platformState.generators[featureKey];
    if (!entry) return true;
    return entry.enabled !== false;
  }

  function showFeatureLockedMessage(featureKey) {
    const label = getFeatureLabel(featureKey);
    const message = platformState.maintenance.message || 'Generator sedang maintenance. Silakan coba lagi nanti.';
    alert(`${label} tidak tersedia sementara untuk pengguna.\n${message}`);
  }

  function clampPercent(value) {
    if (!Number.isFinite(value)) return 0;
    if (value < 0) return 0;
    if (value > 100) return 100;
    return Math.round(value);
  }

  function summarizeTaskProgress(items, { isStarted, isCompleted }) {
    if (!Array.isArray(items) || !items.length) {
      return { total: 0, started: 0, completed: 0, percent: 0 };
    }
    let started = 0;
    let completed = 0;
    items.forEach(item => {
      if (isStarted(item)) started += 1;
      if (isCompleted(item)) completed += 1;
    });
    const total = items.length;
    const inProgress = Math.max(0, started - completed);
    const weighted = completed + inProgress * 0.5;
    const percent = clampPercent((weighted / total) * 100);
    return { total, started, completed, percent };
  }

  function setInlineProgressState(wrapper, fill, valueEl, percent, show, label) {
    const pct = clampPercent(percent);
    if (wrapper) wrapper.classList.toggle('active', !!show);
    if (fill) fill.style.width = pct + '%';
    if (valueEl) valueEl.textContent = label || (pct + '%');
  }

  function ensureAbsoluteUrl(url) {
    if (!url || typeof url !== 'string') {
      return '';
    }
    try {
      return new URL(url, window.location.origin).href;
    } catch (err) {
      return url;
    }
  }

  function showInlineStatus(el, message, mode) {
    if (!el) return;
    el.textContent = message || '';
    el.classList.remove('ok', 'err', 'progress');
    if (!message) {
      el.style.display = 'none';
      return;
    }
    if (mode) {
      el.classList.add(mode);
    }
    el.style.display = 'block';
  }

  function applyAvatarToElement(el, avatarUrl, initials) {
    if (!el) return;
    const hasImage = avatarUrl && /^https?:\/\//i.test(avatarUrl);
    if (hasImage) {
      el.style.backgroundImage = `url('${avatarUrl.replace(/'/g, "\\'")}')`;
      el.classList.add('profile-avatar--image');
      el.textContent = '';
    } else {
      el.style.backgroundImage = 'none';
      el.classList.remove('profile-avatar--image');
      el.textContent = initials || '';
    }
  }

  function updateAvatarPreview(avatarUrl, initials) {
    if (avatarPreviewImage) {
      const valid = avatarUrl && /^https?:\/\//i.test(avatarUrl);
      if (valid) {
        avatarPreviewImage.src = avatarUrl;
        avatarPreviewImage.style.display = 'block';
      } else {
        avatarPreviewImage.removeAttribute('src');
        avatarPreviewImage.style.display = 'none';
      }
    }
    if (avatarPreviewInitials) {
      avatarPreviewInitials.textContent = initials || '';
      avatarPreviewInitials.style.display = avatarPreviewImage && avatarPreviewImage.style.display === 'block' ? 'none' : 'flex';
    }
  }

  function pickErrorMessage(err) {
    if (!err) return 'Terjadi kesalahan.';
    if (typeof err === 'string') return err;
    if (Array.isArray(err)) {
      return err.find(item => typeof item === 'string' && item.trim() !== '') || 'Terjadi kesalahan.';
    }
    if (typeof err === 'object') {
      const values = Object.values(err);
      for (const value of values) {
        if (!value) continue;
        if (typeof value === 'string' && value.trim() !== '') {
          return value;
        }
        if (Array.isArray(value)) {
          const found = value.find(item => typeof item === 'string' && item.trim() !== '');
          if (found) return found;
        }
      }
    }
    return 'Terjadi kesalahan.';
  }

  function updateNavAvailability() {
    if (!navButtons) return;
    navButtons.forEach(btn => {
      const featureKey = btn.dataset.feature;
      if (!featureKey) {
        btn.classList.remove('locked');
        return;
      }
      const locked = !featureAvailableForCurrentUser(featureKey);
      btn.classList.toggle('locked', locked);
      if (typeof btn.disabled === 'boolean') {
        btn.disabled = locked;
      }
      if (locked) {
        btn.setAttribute('aria-disabled', 'true');
        btn.title = `${getFeatureLabel(featureKey)} sementara tidak tersedia.`;
      } else {
        btn.removeAttribute('aria-disabled');
        btn.removeAttribute('title');
      }
    });
  }

  function updateFeatureTabsAvailability() {
    if (!featureTabs.length) return;
    featureTabs.forEach(btn => {
      const key = btn.dataset.feature;
      if (!key) return;
      const locked = !featureAvailableForCurrentUser(key);
      btn.classList.toggle('locked', locked);
      if (typeof btn.disabled === 'boolean') {
        btn.disabled = locked;
      }
      if (locked) {
        btn.setAttribute('aria-disabled', 'true');
      } else {
        btn.removeAttribute('aria-disabled');
      }
    });
  }

  function updateMaintenanceOverlay() {
    if (!maintenanceOverlayEl) return;
    const isLocked = platformState.maintenance.active && !(currentAccount && currentAccount.role === 'admin');
    maintenanceOverlayEl.classList.toggle('active', isLocked);
    if (isLocked) {
      if (maintenanceOverlayMessageEl) {
        maintenanceOverlayMessageEl.textContent = platformState.maintenance.message || 'Website sedang maintenance. Kami segera kembali!';
      }
      document.body.classList.add('maintenance-active');
    } else {
      document.body.classList.remove('maintenance-active');
    }
  }

  function applyPlatformState(newState) {
    platformState = normalizePlatformState(newState);
    updateNavAvailability();
    updateFeatureTabsAvailability();
    updateMaintenanceOverlay();
    if (!featureAvailableForCurrentUser(currentFeature)) {
      const fallback = HUB_FEATURE_KEYS.find(key => featureAvailableForCurrentUser(key));
      if (fallback) {
        currentFeature = fallback;
        if (viewHubSection && viewHubSection.style.display !== 'none') {
          setFeature(fallback);
        }
      } else if (viewHubSection && viewHubSection.style.display !== 'none') {
        showView('viewDashboard');
      }
    }

    if (viewFilmSection && viewFilmSection.style.display !== 'none' && !featureAvailableForCurrentUser('filmmaker')) {
      showFeatureLockedMessage('filmmaker');
      showView('viewDashboard');
    }

    if (viewUGCSection && viewUGCSection.style.display !== 'none' && !featureAvailableForCurrentUser('ugc')) {
      showFeatureLockedMessage('ugc');
      showView('viewDashboard');
    }
  }

  function getWhatsappMessage(amount) {
    const username = currentAccount && currentAccount.username
      ? `@${currentAccount.username}`
      : (currentAccount && currentAccount.display_name) ? currentAccount.display_name : 'user';
    return `Halo Admin, saya ${username}. Saya ingin top up ${amount} credit.`;
  }

  function refreshTopupConfirm() {
    if (!topupConfirmBtn) return;
    if (!selectedTopupAmount) {
      topupConfirmBtn.disabled = true;
      if (topupHintEl) topupHintEl.textContent = 'Pilih nominal untuk lanjut ke WhatsApp.';
      return;
    }
    topupConfirmBtn.disabled = false;
    if (topupHintEl) {
      topupHintEl.textContent = `Konfirmasi top up ${selectedTopupAmount} credit via WhatsApp.`;
    }
  }

  function handleTopupOption(amount) {
    selectedTopupAmount = amount;
    if (topupOptionsEl) {
      topupOptionsEl.querySelectorAll('.topup-option').forEach(btn => {
        const value = Number(btn.dataset.amount || '0');
        btn.classList.toggle('active', value === amount);
      });
    }
    refreshTopupConfirm();
  }

  function buildTopupOptions() {
    if (!topupOptionsEl || !TOPUP_AMOUNTS.length) return;
    if (topupOptionsEl.childElementCount) return;
    TOPUP_AMOUNTS.forEach(amount => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'topup-option';
      btn.dataset.amount = String(amount);
      btn.textContent = `${amount} Credit`;
      btn.addEventListener('click', () => handleTopupOption(amount));
      topupOptionsEl.appendChild(btn);
    });
  }

  function resetTopupSelection() {
    selectedTopupAmount = null;
    if (topupOptionsEl) {
      topupOptionsEl.querySelectorAll('.topup-option').forEach(btn => btn.classList.remove('active'));
    }
  }

  function openTopupModal() {
    buildTopupOptions();
    resetTopupSelection();
    refreshTopupConfirm();
    if (topupModalEl) {
      topupModalEl.classList.add('show');
    }
    document.body.classList.add('modal-open');
  }

  function closeTopupModal() {
    if (topupModalEl) {
      topupModalEl.classList.remove('show');
    }
    const previewEl = document.querySelector('.asset-preview');
    const activePreview = previewEl && !previewEl.classList.contains('hidden');
    if (!activePreview) {
      document.body.classList.remove('modal-open');
    }
    resetTopupSelection();
    refreshTopupConfirm();
  }

  function launchTopupWhatsapp() {
    if (!selectedTopupAmount) return;
    const message = getWhatsappMessage(selectedTopupAmount);
    const url = `${TOPUP_WHATSAPP}?text=${encodeURIComponent(message)}`;
    window.open(url, '_blank', 'noopener');
    closeTopupModal();
  }

  function closeSidebarOnMobile() {
    if (!workspace || !mobileSidebarQuery.matches) return;
    if (workspace.classList.contains('sidebar-open')) {
      workspace.classList.remove('sidebar-open');
      if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
    }
  }

  function syncSidebarForViewport() {
    if (!workspace) return;
    if (mobileSidebarQuery.matches) {
      workspace.classList.remove('sidebar-collapsed');
      workspace.classList.remove('sidebar-open');
      if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
    } else {
      workspace.classList.add('sidebar-open');
      const collapsed = workspace.classList.contains('sidebar-collapsed');
      if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
    }
  }

  syncSidebarForViewport();
  if (mobileSidebarQuery && typeof mobileSidebarQuery.addEventListener === 'function') {
    mobileSidebarQuery.addEventListener('change', syncSidebarForViewport);
  } else if (mobileSidebarQuery && typeof mobileSidebarQuery.addListener === 'function') {
    mobileSidebarQuery.addListener(syncSidebarForViewport);
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      if (!workspace) return;
      if (mobileSidebarQuery.matches) {
        const isOpen = workspace.classList.toggle('sidebar-open');
        sidebarToggle.setAttribute('aria-expanded', String(isOpen));
      } else {
        const isCollapsed = workspace.classList.toggle('sidebar-collapsed');
        sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
      }
    });
  }

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebarOnMobile);
  }

  topupOpeners.forEach(btn => {
    btn.addEventListener('click', () => {
      openTopupModal();
    });
  });

  if (topupCloseBtn) {
    topupCloseBtn.addEventListener('click', () => {
      closeTopupModal();
    });
  }

  if (topupConfirmBtn) {
    topupConfirmBtn.addEventListener('click', () => {
      launchTopupWhatsapp();
    });
  }

  if (topupModalEl) {
    topupModalEl.addEventListener('click', event => {
      if (event.target === topupModalEl) {
        closeTopupModal();
      }
    });
  }

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && topupModalEl && topupModalEl.classList.contains('show')) {
      closeTopupModal();
    }
  });

  function updateDashboardStats() {
    if (statCoinsEl) {
      const coins = currentAccount && Number.isFinite(Number(currentAccount.coins))
        ? Number(currentAccount.coins)
        : 0;
      statCoinsEl.textContent = coins.toLocaleString('id-ID');
    }

    const totalJobs = Array.isArray(jobs) ? jobs : [];
    const queueCount = totalJobs.filter(job => !finalStatus(job.status)).length;
    const completed = totalJobs.filter(job => finalStatus(job.status));

    const videoCount = completed.filter(job => {
      const cfg = modelConfigMap[job.modelId];
      const type = job.type || (cfg && cfg.type);
      return type === 'video';
    }).length;

    const imageCount = completed.filter(job => {
      const cfg = modelConfigMap[job.modelId];
      const type = job.type || (cfg && cfg.type);
      return type === 'image' || type === 'edit';
    }).length;

    if (statVideosEl) statVideosEl.textContent = videoCount.toLocaleString('id-ID');
    if (statImagesEl) statImagesEl.textContent = imageCount.toLocaleString('id-ID');
    if (statQueueEl) statQueueEl.textContent = queueCount.toLocaleString('id-ID');
  }

  function setDriveAccess(enabled) {
    if (!driveNavButton) return;
    driveNavButton.classList.toggle('hidden', !enabled);
    driveNavButton.disabled = !enabled;
  }

  function jobOutputType(job) {
    if (!job) return 'image';
    const cfg = modelConfigMap[job.modelId];
    const type = job.type || (cfg && cfg.type);
    return type === 'video' ? 'video' : 'image';
  }

  function driveModelLabel(modelId) {
    if (!modelId) return 'Hasil Generate';
    const cfg = modelConfigMap[modelId];
    if (cfg && cfg.label) return cfg.label;
    return String(modelId).toUpperCase();
  }

  function formatDriveDate(iso) {
    if (!iso) return '-';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
  }

  function updateDriveSummary() {
    const total = Array.isArray(driveItems) ? driveItems.length : 0;
    const photos = Array.isArray(driveItems) ? driveItems.filter(item => (item.type || 'image') !== 'video').length : 0;
    const videos = total - photos;
    if (driveTotalCountEl) {
      driveTotalCountEl.textContent = `${total.toLocaleString('id-ID')} file`;
    }
    if (driveTypeSummaryEl) {
      driveTypeSummaryEl.textContent = `${photos.toLocaleString('id-ID')} foto • ${videos.toLocaleString('id-ID')} video`;
    }
  }

  function renderDriveItems() {
    if (!driveGrid || !driveEmpty) {
      return;
    }

    const typeValue = driveTypeFilter ? driveTypeFilter.value : 'all';
    const sortValue = driveSortFilter ? driveSortFilter.value : 'newest';
    const dateValue = driveDateFilter ? driveDateFilter.value : '';

    let items = Array.isArray(driveItems) ? driveItems.slice() : [];

    if (typeValue === 'image' || typeValue === 'video') {
      items = items.filter(item => (item.type || 'image') === typeValue);
    }

    if (dateValue) {
      items = items.filter(item => {
        const iso = item.created_at || item.createdAt || '';
        return typeof iso === 'string' && iso.slice(0, 10) === dateValue;
      });
    }

    items.sort((a, b) => {
      const aTime = new Date(a.created_at || a.createdAt || 0).getTime();
      const bTime = new Date(b.created_at || b.createdAt || 0).getTime();
      if (sortValue === 'oldest') {
        return aTime - bTime;
      }
      return bTime - aTime;
    });

    driveEmpty.style.display = items.length ? 'none' : 'block';
    driveGrid.innerHTML = '';

    items.forEach(item => {
      if (!item || !item.url) return;
      const card = document.createElement('div');
      card.className = 'drive-card';

      const thumb = document.createElement('div');
      thumb.className = 'drive-thumb';
      const type = (item.type || 'image') === 'video' ? 'video' : 'image';
      thumb.dataset.type = type;

      const badge = document.createElement('span');
      badge.className = 'drive-type-badge';
      badge.textContent = type === 'video' ? 'VIDEO' : 'FOTO';
      thumb.appendChild(badge);

      if (type === 'video') {
        const video = document.createElement('video');
        video.src = (item.thumbnail_url && /^https?:/i.test(item.thumbnail_url)) ? item.thumbnail_url : item.url;
        video.muted = true;
        video.loop = true;
        video.autoplay = true;
        video.playsInline = true;
        thumb.appendChild(video);
      } else {
        const img = document.createElement('img');
        img.src = (item.thumbnail_url && /^https?:/i.test(item.thumbnail_url)) ? item.thumbnail_url : item.url;
        img.alt = 'Drive item';
        thumb.appendChild(img);
      }

      thumb.addEventListener('click', () => {
        openAssetPreview(item.url, type);
      });

      const footer = document.createElement('div');
      footer.className = 'drive-card-footer';

      const title = document.createElement('strong');
      title.textContent = driveModelLabel(item.model);
      footer.appendChild(title);

      const meta = document.createElement('span');
      meta.textContent = formatDriveDate(item.created_at || item.createdAt);
      footer.appendChild(meta);

      if (item.prompt) {
        const promptEl = document.createElement('span');
        promptEl.textContent = item.prompt;
        footer.appendChild(promptEl);
      }

      const actions = document.createElement('div');
      actions.className = 'drive-actions';

      const previewBtn = document.createElement('button');
      previewBtn.type = 'button';
      previewBtn.textContent = 'Preview';
      previewBtn.addEventListener('click', () => openAssetPreview(item.url, type));
      actions.appendChild(previewBtn);

      const downloadLink = document.createElement('a');
      downloadLink.href = item.url;
      downloadLink.target = '_blank';
      downloadLink.rel = 'noopener';
      downloadLink.textContent = 'Download';
      downloadLink.setAttribute('download', '');
      actions.appendChild(downloadLink);

      const deleteBtn = document.createElement('button');
      deleteBtn.type = 'button';
      deleteBtn.className = 'danger';
      deleteBtn.textContent = 'Hapus';
      deleteBtn.addEventListener('click', () => deleteDriveEntry(item, deleteBtn));
      actions.appendChild(deleteBtn);

      card.appendChild(thumb);
      card.appendChild(footer);
      card.appendChild(actions);
      driveGrid.appendChild(card);
    });

    updateDriveSummary();
  }

  async function loadDriveItems(force = false) {
    if (!currentAccount || driveLoading) {
      return;
    }
    if (driveLoaded && !force) {
      renderDriveItems();
      return;
    }

    driveLoading = true;
    try {
      const res = await fetch(DRIVE_ENDPOINT, {
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error((data && data.error) || 'Gagal memuat drive.');
      }
      driveItems = Array.isArray(data.data && data.data.items) ? data.data.items : [];
      driveLoaded = true;
      renderDriveItems();
    } catch (err) {
      console.warn('Gagal memuat drive:', err);
    } finally {
      driveLoading = false;
    }
  }

  async function persistDriveItems(items) {
    if (!items || !items.length) {
      return null;
    }

    const res = await fetch(DRIVE_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ items })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error((data && data.error) || 'Gagal menyimpan drive.');
    }

    driveItems = Array.isArray(data.data && data.data.items) ? data.data.items : [];
    driveLoaded = true;
    renderDriveItems();
    return driveItems;
  }

  async function saveSceneToDrive(scene, button) {
    if (!scene || !scene.url) {
      alert('Scene belum memiliki gambar.');
      return;
    }

    const url = ensureAbsoluteUrl(scene.url);
    if (!/^https?:\/\//i.test(url)) {
      alert('URL scene belum siap disimpan.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'Menyimpan…';
    }

    const entry = {
      type: 'image',
      url,
      model: scene.modelId || 'filmmaker',
      prompt: scene.prompt || null,
      created_at: nowIso()
    };

    try {
      await persistDriveItems([entry]);
      if (button) {
        button.textContent = 'Tersimpan ✓';
        setTimeout(() => {
          button.disabled = false;
          button.textContent = 'Simpan ke Drive';
        }, 1800);
      }
    } catch (err) {
      if (button) {
        button.disabled = false;
        button.textContent = 'Simpan ke Drive';
      }
      alert('Gagal menyimpan scene ke drive: ' + (err.message || err));
    }
  }

  async function saveUgcImageToDrive(item, button) {
    if (!item || !item.imageUrl) {
      alert('Gambar UGC belum siap.');
      return;
    }

    const url = ensureAbsoluteUrl(item.imageUrl || item.remoteUrl);
    if (!/^https?:\/\//i.test(url)) {
      alert('URL gambar tidak valid.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'Menyimpan…';
    }

    const entry = {
      type: 'image',
      url,
      model: 'ugc-image',
      prompt: item.prompt || null,
      created_at: nowIso()
    };

    try {
      await persistDriveItems([entry]);
      if (button) {
        button.textContent = 'Tersimpan ✓';
        setTimeout(() => {
          button.disabled = false;
          button.textContent = 'Simpan ke Drive';
        }, 1800);
      }
    } catch (err) {
      if (button) {
        button.disabled = false;
        button.textContent = 'Simpan ke Drive';
      }
      alert('Gagal menyimpan gambar ke drive: ' + (err.message || err));
    }
  }

  async function saveUgcVideoToDrive(item, button) {
    if (!item || !item.videoUrl) {
      alert('Video UGC belum siap.');
      return;
    }

    const url = ensureAbsoluteUrl(item.videoUrl);
    if (!/^https?:\/\//i.test(url)) {
      alert('URL video tidak valid.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'Menyimpan…';
    }

    const entry = {
      type: 'video',
      url,
      model: 'ugc-video',
      prompt: item.videoPrompt || item.prompt || null,
      created_at: nowIso()
    };

    const thumb = ensureAbsoluteUrl(item.imageUrl || item.remoteUrl || '');
    if (/^https?:\/\//i.test(thumb)) {
      entry.thumbnail_url = thumb;
    }

    try {
      await persistDriveItems([entry]);
      if (button) {
        button.textContent = 'Tersimpan ✓';
        setTimeout(() => {
          button.disabled = false;
          button.textContent = 'Simpan Video';
        }, 1800);
      }
    } catch (err) {
      if (button) {
        button.disabled = false;
        button.textContent = 'Simpan Video';
      }
      alert('Gagal menyimpan video ke drive: ' + (err.message || err));
    }
  }

  async function deleteDriveEntry(item, button) {
    if (!item) return;
    const payload = {};
    if (item.id) payload.id = item.id;
    if (item.url) payload.url = item.url;
    if (!payload.id && !payload.url) {
      alert('Item drive tidak valid.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'Menghapus…';
    }

    try {
      const res = await fetch(DRIVE_DELETE_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(pickErrorMessage(data && data.error));
      }
      driveItems = Array.isArray(data.data && data.data.items) ? data.data.items : [];
      driveLoaded = true;
      renderDriveItems();
    } catch (err) {
      alert('Gagal menghapus file: ' + (err.message || err));
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = 'Hapus';
      }
    }
  }

  async function syncJobToDrive(job) {
    if (!job || !currentAccount) return;
    if (!finalStatus(job.status)) return;
    if (job.driveSynced) return;

    const type = jobOutputType(job);
    const urls = new Set();
    const payload = [];

    const pushUrl = url => {
      if (typeof url !== 'string') return;
      if (!/^https?:\/\//i.test(url)) return;
      if (urls.has(url)) return;
      urls.add(url);
      payload.push({
        type,
        url,
        model: job.modelId || null,
        prompt: job.prompt || null,
        created_at: job.updatedAt || job.createdAt || nowIso()
      });
    };

    if (Array.isArray(job.generated)) {
      job.generated.forEach(pushUrl);
    }
    pushUrl(job.extraUrl || null);

    if (!payload.length) {
      return;
    }

    try {
      await persistDriveItems(payload);
      job.driveSynced = nowIso();
      saveJobs();
    } catch (err) {
      console.warn('Gagal sinkron drive:', err);
    }
  }

  function applyTheme(theme) {
    currentTheme = theme === 'light' ? 'light' : 'dark';
    document.body.dataset.theme = currentTheme;
    if (themeToggle) {
      themeToggle.textContent = currentTheme === 'dark' ? '🌙' : '☀️';
    }
  }

  function initialsFrom(name, username) {
    const source = name && name.trim() ? name : username || '';
    const words = source.split(/\s+/).filter(Boolean);
    if (!words.length) {
      return username ? username.slice(0, 2).toUpperCase() : 'FM';
    }
    const letters = words.slice(0, 2).map(w => w[0]);
    return letters.join('').toUpperCase();
  }

  function currentAccountInitials() {
    if (currentAccount) {
      return initialsFrom(currentAccount.display_name || currentAccount.username || 'User', currentAccount.username);
    }
    return initialsFrom('User', 'user');
  }

  function formatSubscriptionLabel(subscription) {
    if (!subscription) return 'FREE';
    return String(subscription).toUpperCase();
  }

  function updateProfileCard(account) {
    if (!profileCard || !account) return;
    const display = account.display_name || account.username || 'User';
    const username = account.username ? `@${account.username}` : '';
    const coins = Number.isFinite(Number(account.coins)) ? Number(account.coins) : 0;
    const subscription = formatSubscriptionLabel(account.subscription);
    const isBanned = !!account.is_banned;
    const isBlocked = !!account.is_blocked;
    const formattedCoins = coins.toLocaleString('id-ID');
    const initials = initialsFrom(display, account.username);

    if (profileDisplayEl) profileDisplayEl.textContent = display;
    if (profileUsernameEl) profileUsernameEl.textContent = username;
    if (profileCoinsEl) profileCoinsEl.textContent = formattedCoins;
    if (profileBadgeEl) profileBadgeEl.textContent = subscription;
    applyAvatarToElement(profileAvatarEl, account.avatar_url, initials);

    if (profileDisplayMobile) profileDisplayMobile.textContent = display;
    if (profileUsernameMobile) profileUsernameMobile.textContent = username;
    if (profileBadgeMobile) profileBadgeMobile.textContent = subscription;
    if (profileCoinsMobile) profileCoinsMobile.textContent = formattedCoins;
    applyAvatarToElement(profileAvatarMobile, account.avatar_url, initials);
    if (mobileCoinValue) mobileCoinValue.textContent = formattedCoins;
    if (avatarUrlInput) {
      avatarUrlInput.value = account.avatar_url || '';
    }
    updateAvatarPreview(account.avatar_url || '', initials);

    if (profileStatusTextEl) {
      if (isBanned) {
        profileStatusTextEl.textContent = 'Banned';
      } else if (isBlocked) {
        profileStatusTextEl.textContent = 'Blocked';
      } else {
        profileStatusTextEl.textContent = 'Live';
      }
    }

    if (profileStatusMobileEl) {
      if (isBanned) {
        profileStatusMobileEl.textContent = 'Banned';
      } else if (isBlocked) {
        profileStatusMobileEl.textContent = 'Blocked';
      } else {
        profileStatusMobileEl.textContent = 'Live';
      }
    }

    const statusDot = profileCard.querySelector('.status-dot');
    if (statusDot) {
      statusDot.classList.toggle('offline', isBanned || isBlocked);
    }

    if (profileCardMobile) {
      const mobileDot = profileCardMobile.querySelector('.status-dot');
      if (mobileDot) {
        mobileDot.classList.toggle('offline', isBanned || isBlocked);
      }
      profileCardMobile.classList.toggle('profile-card--alert', isBanned || isBlocked);
    }

    profileCard.classList.toggle('profile-card--alert', isBanned || isBlocked);
    updateDashboardStats();
  }

  async function fetchAccountState() {
    const res = await fetch(ACCOUNT_ENDPOINT, {
      credentials: 'same-origin'
    });
    if (!res.ok) {
      throw new Error('Gagal memuat akun (HTTP ' + res.status + ')');
    }
    const payload = await res.json();
    if (!payload.ok) {
      throw new Error(payload.error || 'Gagal memuat akun.');
    }
    return payload.data || {};
  }

  async function persistTheme(theme) {
    const res = await fetch(ACCOUNT_THEME_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ theme })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error((data && data.error) || 'Gagal menyimpan tema.');
    }
    return data.data;
  }

  async function loadAccountState() {
    try {
      const account = await fetchAccountState();
      currentAccount = account;
      applyPlatformState(account.platform || null);
      applyTheme(account.theme || currentTheme);
      updateProfileCard(account);
      setDriveAccess(true);
      if (driveLoaded) {
        renderDriveItems();
      } else {
        updateDriveSummary();
      }
    } catch (err) {
      console.warn('Tidak dapat memuat akun:', err);
      applyTheme(currentTheme);
      currentAccount = null;
      applyPlatformState(null);
      driveItems = [];
      driveLoaded = false;
      setDriveAccess(false);
      renderDriveItems();
    }
  }

  function ensureCoins(amount) {
    if (!currentAccount) {
      return false;
    }
    const balance = Number.isFinite(Number(currentAccount.coins)) ? Number(currentAccount.coins) : 0;
    return balance >= amount;
  }

  async function spendCoins(amount) {
    if (!amount || amount <= 0) return;
    const res = await fetch(ACCOUNT_COINS_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ amount })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      const message = (data && data.error) ? JSON.stringify(data.error) : 'Koin tidak bisa diperbarui.';
      throw new Error(message);
    }
    if (!currentAccount) currentAccount = {};
    currentAccount.coins = data.data && typeof data.data.coins !== 'undefined' ? data.data.coins : currentAccount.coins;
    updateProfileCard(currentAccount);
  }

  if (themeToggle) {
    themeToggle.addEventListener('click', async () => {
      const next = currentTheme === 'dark' ? 'light' : 'dark';
      const previous = currentTheme;
      applyTheme(next);
      try {
        const updated = await persistTheme(next);
        if (updated) {
          currentAccount = updated;
          updateProfileCard(updated);
        }
      } catch (err) {
        console.warn('Gagal menyimpan tema:', err);
        applyTheme(previous);
      }
    });
  }

  if (logoutButton) {
    logoutButton.addEventListener('click', async () => {
      if (logoutButton.classList.contains('loading')) {
        return;
      }

      logoutButton.classList.add('loading');
      try {
        const res = await fetch(LOGOUT_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({})
        });

        if (!res.ok) {
          const text = await res.text();
          console.error('Logout gagal:', text);
          alert('Logout gagal. Silakan coba lagi.');
          return;
        }

        window.location.reload();
      } catch (err) {
        console.error('Logout error', err);
        alert('Terjadi kesalahan saat logout.');
      } finally {
        logoutButton.classList.remove('loading');
      }
    });
  }

  if (avatarUploadBtn && avatarFileInput) {
    avatarUploadBtn.addEventListener('click', () => avatarFileInput.click());
  }

  if (avatarRemoveBtn) {
    avatarRemoveBtn.addEventListener('click', () => {
      if (avatarUrlInput) {
        avatarUrlInput.value = '';
      }
      updateAvatarPreview('', currentAccountInitials());
      showInlineStatus(avatarFormStatus, 'Avatar akan dihapus setelah disimpan.', 'progress');
    });
  }

  if (avatarUrlInput) {
    avatarUrlInput.addEventListener('input', () => {
      const value = avatarUrlInput.value.trim();
      updateAvatarPreview(value, currentAccountInitials());
      showInlineStatus(avatarFormStatus, '', null);
    });
  }

  if (avatarFileInput) {
    avatarFileInput.addEventListener('change', async event => {
      const file = event.target.files && event.target.files[0];
      if (!file) return;
      if (!file.type || !file.type.startsWith('image/')) {
        showInlineStatus(avatarFormStatus, 'File harus gambar (PNG/JPG/WEBP).', 'err');
        avatarFileInput.value = '';
        return;
      }

      showInlineStatus(avatarFormStatus, `Mengunggah ${file.name}…`, 'progress');
      try {
        const result = await uploadFileToServer(file);
        if (!result || !result.url) {
          throw new Error('Upload gagal.');
        }
        if (avatarUrlInput) {
          avatarUrlInput.value = result.url;
        }
        updateAvatarPreview(result.url, currentAccountInitials());
        showInlineStatus(avatarFormStatus, 'Upload berhasil, klik Simpan Avatar untuk menyimpan.', 'ok');
      } catch (err) {
        showInlineStatus(avatarFormStatus, err.message || 'Upload gagal.', 'err');
      } finally {
        avatarFileInput.value = '';
      }
    });
  }

  if (avatarForm) {
    avatarForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (!currentAccount) {
        alert('Data akun belum siap. Muat ulang halaman.');
        return;
      }

      const avatarValue = avatarUrlInput ? avatarUrlInput.value.trim() : '';
      if (avatarValue && !/^https?:\/\//i.test(avatarValue)) {
        showInlineStatus(avatarFormStatus, 'Gunakan URL gambar yang diawali http/https.', 'err');
        return;
      }

      showInlineStatus(avatarFormStatus, 'Menyimpan avatar…', 'progress');
      try {
        const res = await fetch(ACCOUNT_AVATAR_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ avatar: avatarValue })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
          throw new Error(pickErrorMessage(data && data.error));
        }

        if (!currentAccount) currentAccount = {};
        currentAccount = Object.assign({}, currentAccount, data.data || {});
        updateProfileCard(currentAccount);
        showInlineStatus(avatarFormStatus, 'Avatar diperbarui.', 'ok');
      } catch (err) {
        showInlineStatus(avatarFormStatus, err.message || 'Gagal menyimpan avatar.', 'err');
      }
    });
  }

  if (passwordForm) {
    passwordForm.addEventListener('submit', async event => {
      event.preventDefault();
      const current = currentPasswordInput ? currentPasswordInput.value : '';
      const next = newPasswordInput ? newPasswordInput.value : '';
      const confirm = confirmPasswordInput ? confirmPasswordInput.value : '';

      if (!next || next.length < 6) {
        showInlineStatus(passwordFormStatus, 'Password baru minimal 6 karakter.', 'err');
        return;
      }
      if (next !== confirm) {
        showInlineStatus(passwordFormStatus, 'Konfirmasi password harus sama.', 'err');
        return;
      }

      showInlineStatus(passwordFormStatus, 'Memperbarui password…', 'progress');
      try {
        const res = await fetch(ACCOUNT_PASSWORD_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ current, password: next, confirm })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
          throw new Error(pickErrorMessage(data && data.error));
        }

        if (currentPasswordInput) currentPasswordInput.value = '';
        if (newPasswordInput) newPasswordInput.value = '';
        if (confirmPasswordInput) confirmPasswordInput.value = '';
        showInlineStatus(passwordFormStatus, data.message || 'Password berhasil diperbarui.', 'ok');
      } catch (err) {
        showInlineStatus(passwordFormStatus, err.message || 'Gagal memperbarui password.', 'err');
      }
    });

    [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(input => {
      if (!input) return;
      input.addEventListener('input', () => showInlineStatus(passwordFormStatus, '', null));
    });
  }

  setDriveAccess(false);
  updateDriveSummary();
  renderDriveItems();
  loadAccountState();

  // ===== GEMINI MODES =====
  const GEMINI_MODE_META = {
    text: {
      title: 'Mode 1: Text-to-Image',
      desc: 'Masukkan prompt deskriptif tanpa gambar referensi.',
      helper: 'Mode ini tidak memerlukan gambar referensi.',
      min: 0,
      max: 0
    },
    single: {
      title: 'Mode 2: Single Image-to-Image',
      desc: 'Tambahkan 1 gambar referensi untuk diedit mengikuti caption.',
      helper: 'Unggah atau tambahkan tepat 1 URL gambar referensi.',
      min: 1,
      max: 1
    },
    multi: {
      title: 'Mode 3: Multi-Image Reference',
      desc: 'Gunakan 2-3 gambar referensi agar gaya/objeknya digabungkan.',
      helper: 'Unggah hingga 3 gambar referensi sekaligus (minimal 2).',
      min: 2,
      max: 3
    }
  };

  const VIDEO_LAYOUT_OPTIONS = [
    { value: 'portrait',  label: 'Portrait 9:16',  ratio: 'portrait_9_16' },
    { value: 'landscape', label: 'Landscape 16:9', ratio: 'landscape_16_9' },
    { value: 'square',    label: 'Square 1:1',     ratio: 'square_1_1' }
  ];

  const VIDEO_LAYOUT_TO_RATIO = VIDEO_LAYOUT_OPTIONS.reduce((acc, opt) => {
    acc[opt.value] = opt.ratio;
    return acc;
  }, {});

  const VIDEO_MODEL_DURATION_OPTIONS = {
    wan480: { values: [4, 6, 10], default: 6, defaultLayout: 'portrait' },
    wan720: { values: [4, 6, 10], default: 6, defaultLayout: 'portrait' },
    seedancePro480: { values: [5, 10], default: 5, defaultLayout: 'portrait' },
    seedancePro720: { values: [5, 10], default: 5, defaultLayout: 'portrait' },
    seedancePro1080: { values: [5, 10], default: 5, defaultLayout: 'landscape' },
    klingStd21: { values: [5, 8], default: 5, defaultLayout: 'portrait' },
    kling25Pro: { values: [5, 8, 12], default: 5, defaultLayout: 'landscape' },
    minimax1080: { values: [6, 12], default: 6, defaultLayout: 'landscape' },
    _default: { values: [5], default: 5, defaultLayout: 'portrait' }
  };

  function mapVideoAspect(layoutKey) {
    if (!layoutKey) return 'auto';
    const key = String(layoutKey).toLowerCase();
    return VIDEO_LAYOUT_TO_RATIO[key] || 'auto';
  }

  function applyVideoExtras(body, formData = {}) {
    if (!body || typeof body !== 'object') return body;
    const duration = formData.videoDuration;
    if (typeof duration === 'number' && !Number.isNaN(duration) && duration > 0) {
      body.duration = duration;
    }
    const ratio = mapVideoAspect(formData.videoLayout);
    if (ratio && (ratio !== 'auto' || !body.aspect_ratio)) {
      body.aspect_ratio = ratio;
    } else if (!body.aspect_ratio) {
      body.aspect_ratio = 'auto';
    }
    return body;
  }

  // ===== MODEL CONFIG =====
  const MODEL_CONFIG = {
    gemini: {
      id: 'gemini',
      label: 'Gemini 2.5 Flash',
      type: 'image',
      path: '/v1/ai/gemini-2-5-flash-image-preview',
      statusPath: taskId => `/v1/ai/gemini-2-5-flash-image-preview/${taskId}`,
      buildBody: f => {
        const body = { prompt: f.prompt, num_images: f.numImages || 1 };
        if (f.aspectRatio) body.aspect_ratio = f.aspectRatio;
        if (Array.isArray(f.referenceImages) && f.referenceImages.length) {
          body.reference_images = f.referenceImages;
        }
        return body;
      }
    },
    imagen3: {
      id: 'imagen3',
      label: 'Imagen 3',
      type: 'image',
      path: '/v1/ai/text-to-image/imagen3',
      statusPath: taskId => `/v1/ai/text-to-image/imagen3/${taskId}`,
      buildBody: f => {
        const body = { prompt: f.prompt, num_images: f.numImages || 1 };
        if (f.aspectRatio) body.aspect_ratio = f.aspectRatio;
        return body;
      }
    },
    seedream4: {
      id: 'seedream4',
      label: 'Seedream 4',
      type: 'image',
      path: '/v1/ai/text-to-image/seedream-v4',
      statusPath: taskId => `/v1/ai/text-to-image/seedream-v4/${taskId}`,
      buildBody: f => ({ prompt: f.prompt, num_images: f.numImages || 1 })
    },
    seedream4edit: {
    id: 'seedream4edit',
    label: 'Seedream 4 Edit',
    type: 'image',
    // WAJIB: pakai endpoint EDIT, bukan seedream-v4
    path: '/v1/ai/text-to-image/seedream-v4-edit',
    statusPath: taskId => `/v1/ai/text-to-image/seedream-v4-edit/${taskId}`,
    buildBody: f => {
      const refs = [];
      // product images
      if (Array.isArray(f.productImages)) refs.push(...f.productImages);
      // model image optional
      if (f.modelImage) refs.push(f.modelImage);

      return {
        prompt: f.prompt,
        aspect_ratio: f.aspect_ratio || 'square_1_1',
        guidance_scale: 2.5,
        reference_images: refs  // <== ini yang bikin produk mengikuti foto upload
      };
    }
  },

    fluxPro11: {
      id: 'fluxPro11',
      label: 'Flux Pro v1.1',
      type: 'image',
      path: '/v1/ai/text-to-image/flux-pro-v1-1',
      statusPath: taskId => `/v1/ai/text-to-image/flux-pro-v1-1/${taskId}`,
      buildBody: f => {
        const body = { prompt: f.prompt, num_images: f.numImages || 1 };
        if (f.aspectRatio) body.aspect_ratio = f.aspectRatio;
        return body;
      }
    },

    upscalerCreative: {
      id: 'upscalerCreative',
      label: 'Upscaler Creative',
      type: 'image',
      path: '/v1/ai/image-upscaler',
      statusPath: taskId => `/v1/ai/image-upscaler/${taskId}`,
      buildBody: f => ({ image: f.imageUrl, prompt: f.prompt || undefined })
    },
    upscalePrecV1: {
      id: 'upscalePrecV1',
      label: 'Upscale Precision V1',
      type: 'image',
      path: '/v1/ai/image-upscaler-precision',
      statusPath: taskId => `/v1/ai/image-upscaler-precision/${taskId}`,
      buildBody: f => ({ image: f.imageUrl, prompt: f.prompt || undefined })
    },
    upscalePrecV2: {
      id: 'upscalePrecV2',
      label: 'Upscale Precision V2',
      type: 'image',
      path: '/v1/ai/image-upscaler-precision-v2',
      statusPath: taskId => `/v1/ai/image-upscaler-precision-v2/${taskId}`,
      buildBody: f => ({ image: f.imageUrl, prompt: f.prompt || undefined })
    },
    removeBg: {
      id: 'removeBg',
      label: 'Remove Background',
      type: 'image',
      path: '/v1/ai/beta/remove-background',
      statusPath: null,
      contentType: 'form',
      buildBody: f => `image_url=${encodeURIComponent(f.imageUrl)}`
    },

    wan480: {
      id: 'wan480',
      label: 'Wan v2.2 – 480p',
      type: 'video',
      path: '/v1/ai/image-to-video/wan-v2-2-480p',
      statusPath: taskId => `/v1/ai/image-to-video/wan-v2-2-480p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    wan720: {
      id: 'wan720',
      label: 'Wan v2.2 – 720p',
      type: 'video',
      path: '/v1/ai/image-to-video/wan-v2-2-720p',
      statusPath: taskId => `/v1/ai/image-to-video/wan-v2-2-720p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    seedancePro480: {
      id: 'seedancePro480',
      label: 'Seedance Pro – 480p',
      type: 'video',
      path: '/v1/ai/image-to-video/seedance-pro-480p',
      statusPath: taskId => `/v1/ai/image-to-video/seedance-pro-480p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    seedancePro720: {
      id: 'seedancePro720',
      label: 'Seedance Pro – 720p',
      type: 'video',
      path: '/v1/ai/image-to-video/seedance-pro-720p',
      statusPath: taskId => `/v1/ai/image-to-video/seedance-pro-720p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    seedancePro1080: {
      id: 'seedancePro1080',
      label: 'Seedance Pro – 1080p',
      type: 'video',
      path: '/v1/ai/image-to-video/seedance-pro-1080p',
      statusPath: taskId => `/v1/ai/image-to-video/seedance-pro-1080p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    klingStd21: {
      id: 'klingStd21',
      label: 'Kling Std v2.1',
      type: 'video',
      path: '/v1/ai/image-to-video/kling-v2-1-std',
      statusPath: taskId => `/v1/ai/image-to-video/kling-v2-1-std/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    kling25Pro: {
      id: 'kling25Pro',
      label: 'Kling v2.5 Pro',
      type: 'video',
      path: '/v1/ai/image-to-video/kling-v2-5-pro',
      statusPath: taskId => `/v1/ai/image-to-video/kling-v2-5-pro/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    minimax1080: {
      id: 'minimax1080',
      label: 'MiniMax Hailuo 02 – 1080p',
      type: 'video',
      path: '/v1/ai/image-to-video/minimax-hailuo-02-1080p',
      statusPath: taskId => `/v1/ai/image-to-video/minimax-hailuo-02-1080p/${taskId}`,
      buildBody: f => applyVideoExtras({
        prompt: f.prompt,
        first_frame_image: f.imageUrl || undefined
      }, f)
    },

    latentSync: {
      id: 'latentSync',
      label: 'Latent-Sync',
      type: 'video',
      path: '/v1/ai/lip-sync/latent-sync',
      statusPath: taskId => `/v1/ai/lip-sync/latent-sync/${taskId}`,
      buildBody: f => ({
        video_url: f.videoUrl,
        audio_url: f.audioUrl,
        prompt: f.prompt || undefined
      })
    }
  };
  modelConfigMap = MODEL_CONFIG;

  const FEATURE_MODELS = {
    imageGen: ['gemini','imagen3','seedream4','fluxPro11'],
    imageEdit: ['seedream4edit','upscalerCreative','upscalePrecV1','upscalePrecV2','removeBg'],
    videoGen: ['wan480','wan720','seedancePro480','seedancePro720','seedancePro1080','klingStd21','kling25Pro','minimax1080'],
    lipsync: ['latentSync']
  };

  const STORAGE_KEY = 'freepik_jobs_v1';
  jobs = loadJobs();
  updateDashboardStats();
  let activeJobId = null;
  let pollingTimers = {};
  let progressTimers = {};
  let statusProgressHideTimeout = null;

  function loadJobs() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return [];
      const arr = JSON.parse(raw);
      if (!Array.isArray(arr)) return [];
      return arr.map(job => {
        if (job && typeof job === 'object') {
          if (typeof job.progress !== 'number') {
            job.progress = finalStatus(job.status) ? 100 : 0;
          }
          if (job.driveSynced && typeof job.driveSynced !== 'string') {
            job.driveSynced = String(job.driveSynced);
          }
        }
        return job;
      });
    } catch {
      return [];
    }
  }
  function saveJobs() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(jobs));
  }
  function uuid() {
    return 'xxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }
  function nowIso() { return new Date().toISOString(); }
  function shortTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  }
  function finalStatus(status) {
    if (!status) return false;
    const s = status.toUpperCase();
    return s === 'COMPLETED' || s === 'FAILED' || s === 'ERROR';
  }

  function getJobProgress(job) {
    if (!job) return 0;
    if (typeof job.progress === 'number' && !Number.isNaN(job.progress)) {
      return Math.max(0, Math.min(100, Math.round(job.progress)));
    }
    return finalStatus(job.status) ? 100 : 0;
  }

  function hideStatusProgress() {
    if (statusProgressWrapper) {
      statusProgressWrapper.classList.remove('active');
    }
    if (statusProgressFill) {
      statusProgressFill.style.width = '0%';
    }
    if (statusPercent) {
      statusPercent.textContent = '0%';
    }
    if (previewProgress) {
      previewProgress.classList.remove('active');
    }
    if (previewProgressFill) {
      previewProgressFill.style.width = '0%';
    }
    if (previewProgressPercent) {
      previewProgressPercent.textContent = '0%';
    }
  }

  function syncStatusProgress(job) {
    if (!statusProgressWrapper || !statusProgressFill || !statusPercent) return;
    clearTimeout(statusProgressHideTimeout);

    if (!job) {
      hideStatusProgress();
      return;
    }

    const percent = getJobProgress(job);
    statusProgressWrapper.classList.add('active');
    statusProgressFill.style.width = percent + '%';
    statusPercent.textContent = percent + '%';

    if (previewProgress && previewProgressFill && previewProgressPercent) {
      previewProgress.classList.add('active');
      previewProgressFill.style.width = percent + '%';
      previewProgressPercent.textContent = percent + '%';
    }

    if (finalStatus(job.status) && percent >= 100) {
      statusProgressHideTimeout = setTimeout(() => {
        hideStatusProgress();
      }, 1400);
    }
  }

  function stopProgressTimer(jobId) {
    if (progressTimers[jobId]) {
      clearInterval(progressTimers[jobId]);
      delete progressTimers[jobId];
    }
  }

  function startJobProgress(job) {
    if (!job) return;
    if (typeof job.progress !== 'number' || Number.isNaN(job.progress)) {
      job.progress = finalStatus(job.status) ? 100 : 8;
    } else if (job.progress < 5 && !finalStatus(job.status)) {
      job.progress = 8;
    }

    stopProgressTimer(job.id);
    if (finalStatus(job.status)) {
      job.progress = 100;
      updateProgressUI(job);
      return;
    }

    progressTimers[job.id] = setInterval(() => {
      const current = jobs.find(j => j.id === job.id);
      if (!current) {
        stopProgressTimer(job.id);
        return;
      }
      if (finalStatus(current.status)) {
        current.progress = 100;
        stopProgressTimer(job.id);
        updateProgressUI(current);
        saveJobs();
        return;
      }
      const target = 92;
      const next = Math.min(target, (current.progress || 0) + (Math.random() * 7 + 3));
      current.progress = next;
      updateProgressUI(current);
      saveJobs();
    }, 1600);

    updateProgressUI(job);
  }

  function finishJobProgress(job) {
    if (!job) return;
    job.progress = 100;
    stopProgressTimer(job.id);
    updateProgressUI(job);
    saveJobs();
  }

  function updateProgressUI(job) {
    if (job && activeJobId === job.id) {
      syncStatusProgress(job);
    }
    renderJobs();
  }

  const modelSelect = document.getElementById('modelSelect');
  const modelHint = document.getElementById('modelHint');
  const promptInput = document.getElementById('prompt');
  const imageUrlInput = document.getElementById('imageUrl');
  const imageUploadInput = document.getElementById('imageUploadInput');
  const imageUploadButton = document.getElementById('imageUploadButton');
  const imageUploadDropzone = document.getElementById('imageUploadDropzone');
  const imageUploadStatus = document.getElementById('imageUploadStatus');
  const imageUploadPreview = document.getElementById('imageUploadPreview');
  const geminiModeSection = document.getElementById('geminiModeSection');
  const geminiModeButtons = document.querySelectorAll('[data-gemini-mode]');
  const geminiModeDescription = document.getElementById('geminiModeDescription');
  const geminiReferenceSection = document.getElementById('geminiReferenceSection');
  const geminiDropzone = document.getElementById('geminiDropzone');
  const geminiFileInput = document.getElementById('geminiFileInput');
  const geminiFileButton = document.getElementById('geminiFileButton');
  const geminiRefHelper = document.getElementById('geminiRefHelper');
  const geminiDropCounter = document.getElementById('geminiDropCounter');
  const geminiRefUrl = document.getElementById('geminiRefUrl');
  const geminiRefAddBtn = document.getElementById('geminiRefAddBtn');
  const geminiRefList = document.getElementById('geminiRefList');
  const videoUrlInput = document.getElementById('videoUrl');
  const audioUrlInput = document.getElementById('audioUrl');
  const rowVideoSettings = document.getElementById('rowVideoSettings');
  const videoDurationSelect = document.getElementById('videoDuration');
  const videoLayoutSelect = document.getElementById('videoLayout');
  const numImagesInput = document.getElementById('numImages');
  const aspectRatioInput = document.getElementById('aspectRatio');
  const submitBtn = document.getElementById('submitBtn');
  const clearPromptBtn = document.getElementById('clearPromptBtn');
  const statusText = document.getElementById('statusText');
  const statusPill = document.getElementById('statusPill');
  const statusProgressWrapper = document.getElementById('statusProgressWrapper');
  const statusProgressFill = document.getElementById('statusProgressFill');
  const statusPercent = document.getElementById('statusPercent');
  const previewEmpty = document.getElementById('previewEmpty');
  const previewContainer = document.getElementById('previewContainer');
  const previewJobMeta = document.getElementById('previewJobMeta');
  const previewGrid = document.getElementById('previewGrid');
  const previewProgress = document.getElementById('previewProgress');
  const previewProgressFill = document.getElementById('previewProgressFill');
  const previewProgressPercent = document.getElementById('previewProgressPercent');
  const clearPreviewBtn = document.getElementById('clearPreviewBtn');
  const queueList = document.getElementById('queueList');
  const queueEmpty = document.getElementById('queueEmpty');
  const historyList = document.getElementById('historyList');
  const historyEmpty = document.getElementById('historyEmpty');
  const refreshQueueBtn = document.getElementById('refreshQueueBtn');
  const clearHistoryBtn = document.getElementById('clearHistoryBtn');
  const featureTabs = document.querySelectorAll('.feature-tab');
  const featureLabel = document.getElementById('featureLabel');
  let navButtons = [];
  let viewDashboardSection = null;
  let viewDriveSection = null;
  let viewHubSection = null;
  let viewFilmSection = null;
  let viewUGCSection = null;
  let viewAccountSection = null;
  let viewSections = {};

  const rowPrompt      = document.getElementById('rowPrompt');
  const rowImageUrl    = document.getElementById('rowImageUrl');
  const rowVideoAudio  = document.getElementById('rowVideoAudio');
  const rowTIOptions   = document.getElementById('rowTIOptions');
  const fieldsTitle    = document.getElementById('fieldsTitle');

  // Film
  const filmCharacterDrop = document.getElementById('filmCharacterDrop');
  const filmCharacterInput = document.getElementById('filmCharacterInput');
  const filmCharacterIdle = document.getElementById('filmCharacterIdle');
  const filmCharacterPreview = document.getElementById('filmCharacterPreview');
  const filmBriefInput = document.getElementById('filmBrief');
  const filmSceneCount = document.getElementById('filmSceneCount');
  const filmSceneCountLabel = document.getElementById('filmSceneCountLabel');
  const filmAspectButtons = document.querySelectorAll('[data-film-aspect]');
  const filmStatePicker = document.getElementById('filmStatePicker');
  const filmStateTrigger = document.getElementById('filmStateTrigger');
  const filmStateMenu = document.getElementById('filmStateMenu');
  const filmStateValueInput = document.getElementById('filmStateValue');
  const filmStateLabelEl = document.getElementById('filmStateLabel');
  const filmStateDescEl = document.getElementById('filmStateDescription');
  const filmStateIconEl = document.getElementById('filmStateIcon');
  const filmGenerateBtn = document.getElementById('filmGenerateBtn');
  const filmScenesEmpty = document.getElementById('filmScenesEmpty');
  const filmScenesContainer = document.getElementById('filmScenesContainer');

  // UGC
  const ugcList           = document.getElementById('ugcList');
  const ugcEmpty          = document.getElementById('ugcEmpty');
  const ugcProductDrop    = document.getElementById('ugcProductDrop');
  const ugcProductInput   = document.getElementById('ugcProductInput');
  const ugcProductPreview = document.getElementById('ugcProductPreview');
  const ugcModelDrop       = document.getElementById('ugcModelDrop');
  const ugcModelInput      = document.getElementById('ugcModelInput');
  const ugcModelIdle       = document.getElementById('ugcModelIdle');
  const ugcModelPreview    = document.getElementById('ugcModelPreview');
  const ugcStylePicker     = document.getElementById('ugcStylePicker');
  const ugcStyleTrigger    = document.getElementById('ugcStyleTrigger');
  const ugcStyleMenu       = document.getElementById('ugcStyleMenu');
  const ugcStyleValueInput = document.getElementById('ugcStyleValue');
  const ugcStyleLabelEl    = document.getElementById('ugcStyleLabel');
  const ugcStyleDescEl     = document.getElementById('ugcStyleDescription');
  const ugcStyleIconEl     = document.getElementById('ugcStyleIcon');
  const ugcBriefInput      = document.getElementById('ugcBrief');
  const ugcGenerateBtn     = document.getElementById('ugcGenerateBtn');

  function setStatus(text, mode = 'idle') {
    statusText.textContent = text;
    statusText.classList.remove('flash');
    void statusText.offsetWidth;
    statusText.classList.add('flash');
    statusPill.classList.remove('ok', 'err');
    if (mode === 'ok') {
      statusPill.textContent = 'OK';
      statusPill.classList.add('ok');
    } else if (mode === 'err') {
      statusPill.textContent = 'ERROR';
      statusPill.classList.add('err');
    } else {
      statusPill.textContent = 'IDLE';
    }
  }

  let geminiMode = 'text';
  let geminiReferences = [];

  function isHttpUrl(value) {
    return typeof value === 'string' && /^https?:\/\//i.test(value);
  }

  function isDataImage(value) {
    return typeof value === 'string' && /^data:image\//i.test(value);
  }

  function stripDataUrlPrefix(dataUrl) {
    return typeof dataUrl === 'string'
      ? dataUrl.replace(/^data:image\/[a-z0-9.+-]+;base64,/i, '')
      : dataUrl;
  }

  async function fetchLocalImageAsBase64(url) {
    const absolute = new URL(url, window.location.href).href;
    const res = await fetch(absolute, {
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    const blob = await res.blob();
    return await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onerror = () => reject(new Error('Konversi base64 gagal'));
      reader.onloadend = () => {
        const result = reader.result;
        if (typeof result === 'string') {
          resolve(stripDataUrlPrefix(result));
        } else {
          reject(new Error('Pembacaan file tidak valid'));
        }
      };
      reader.readAsDataURL(blob);
    });
  }

  async function prepareGeminiReferenceImages(urls) {
    const results = [];
    for (const entry of urls) {
      if (!entry) continue;
      if (isDataImage(entry)) {
        results.push(stripDataUrlPrefix(entry));
        continue;
      }
      if (isHttpUrl(entry)) {
        try {
          const target = new URL(entry, window.location.href);
          if (target.origin !== window.location.origin) {
            results.push(entry);
            continue;
          }
        } catch (err) {
          // Jika URL tidak valid, lanjutkan ke percobaan fetch lokal di bawah
        }
      }

      try {
        const base64 = await fetchLocalImageAsBase64(entry);
        results.push(base64);
      } catch (err) {
        throw new Error(`Gagal membaca referensi: ${err.message}`);
      }
    }
    return results;
  }

  function getGeminiMeta(mode = geminiMode) {
    return GEMINI_MODE_META[mode] || GEMINI_MODE_META.text;
  }

  function getGeminiLimit(mode = geminiMode) {
    const meta = getGeminiMeta(mode);
    return meta && typeof meta.max === 'number' ? meta.max : 0;
  }

  function resetGeminiState(resetMode = true) {
    if (resetMode) geminiMode = 'text';
    geminiReferences = [];
    if (geminiRefUrl) geminiRefUrl.value = '';
  }

  function renderGeminiRefs(isGemini = (modelSelect && modelSelect.value === 'gemini')) {
    if (!geminiRefList) return;
    const meta = getGeminiMeta();
    if (geminiDropCounter) {
      const current = isGemini ? geminiReferences.length : 0;
      const total = meta.max || 0;
      geminiDropCounter.textContent = `${current}/${total}`;
    }
    if (geminiRefHelper) {
      if (!isGemini) {
        geminiRefHelper.textContent = 'Aktifkan Gemini Flash untuk menambah referensi.';
      } else if (meta.max > 0) {
        geminiRefHelper.textContent = `${meta.helper} (${geminiReferences.length}/${meta.max}).`;
      } else {
        geminiRefHelper.textContent = meta.helper;
      }
    }

    geminiRefList.innerHTML = '';
    if (!isGemini) {
      if (geminiDropzone) geminiDropzone.classList.remove('has-file');
      return;
    }

    if (geminiMode === 'text') {
      const info = document.createElement('div');
      info.className = 'muted';
      info.style.fontSize = '11px';
      info.textContent = 'Mode text-only tidak menggunakan referensi.';
      geminiRefList.appendChild(info);
      if (geminiDropzone) geminiDropzone.classList.remove('has-file');
      return;
    }

    if (!geminiReferences.length) {
      const empty = document.createElement('div');
      empty.className = 'muted';
      empty.style.fontSize = '11px';
      empty.textContent = 'Belum ada referensi. Upload gambar atau tambahkan URL di bawah.';
      geminiRefList.appendChild(empty);
      if (geminiDropzone) geminiDropzone.classList.remove('has-file');
      return;
    }

    geminiReferences.forEach((ref, idx) => {
      const item = document.createElement('div');
      item.className = 'gemini-ref-item';

      const thumb = document.createElement('img');
      thumb.className = 'gemini-ref-thumb';
      thumb.src = ref.url;
      thumb.alt = ref.name || ('Referensi ' + (idx + 1));

      const metaDiv = document.createElement('div');
      metaDiv.className = 'gemini-ref-meta';
      const title = document.createElement('strong');
      title.textContent = ref.name || ('Referensi ' + (idx + 1));
      const urlSpan = document.createElement('span');
      urlSpan.textContent = ref.url;
      metaDiv.appendChild(title);
      metaDiv.appendChild(urlSpan);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'small secondary';
      removeBtn.textContent = 'Hapus';
      removeBtn.addEventListener('click', () => {
        geminiReferences.splice(idx, 1);
        renderGeminiRefs(modelSelect && modelSelect.value === 'gemini');
      });

      item.appendChild(thumb);
      item.appendChild(metaDiv);
      item.appendChild(removeBtn);
      geminiRefList.appendChild(item);
    });

    if (geminiDropzone) {
      geminiDropzone.classList.toggle('has-file', geminiReferences.length > 0);
    }
  }

  function updateGeminiModeUI(isGemini = (modelSelect && modelSelect.value === 'gemini')) {
    const meta = getGeminiMeta();
    if (geminiModeButtons && geminiModeButtons.forEach) {
      geminiModeButtons.forEach(btn => {
        const mode = btn.dataset.geminiMode;
        btn.classList.toggle('active', mode === geminiMode);
      });
    }
    if (geminiModeDescription && meta) {
      geminiModeDescription.textContent = `${meta.title} — ${meta.desc}`;
    }
    if (geminiModeSection) {
      geminiModeSection.classList.toggle('hidden', !isGemini);
    }
    if (geminiReferenceSection) {
      const showRefs = isGemini && geminiMode !== 'text';
      geminiReferenceSection.classList.toggle('hidden', !showRefs);
    }
    renderGeminiRefs(isGemini);
  }

  function syncGeminiVisibility() {
    const isGemini = modelSelect && modelSelect.value === 'gemini';
    if (!isGemini) {
      resetGeminiState(true);
    }
    updateGeminiModeUI(isGemini);
  }

  function setGeminiMode(mode) {
    if (!mode || !GEMINI_MODE_META[mode]) return;
    if (geminiMode === mode) {
      updateGeminiModeUI(modelSelect && modelSelect.value === 'gemini');
      return;
    }
    geminiMode = mode;
    const limit = getGeminiLimit(mode);
    if (limit === 0) {
      geminiReferences = [];
    } else if (geminiReferences.length > limit) {
      geminiReferences = geminiReferences.slice(0, limit);
    }
    updateGeminiModeUI(modelSelect && modelSelect.value === 'gemini');
  }

  if (geminiModeButtons && geminiModeButtons.forEach) {
    geminiModeButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const mode = btn.dataset.geminiMode;
        setGeminiMode(mode);
      });
    });
  }


  function setModelHint(id) {
    if (id === 'gemini') {
      modelHint.textContent = 'Gemini Flash 2.5: pilih mode (Text / 1 referensi / 2-3 referensi). Prompt wajib, num_images & aspect ratio opsional.';
    } else if (['imagen3','seedream4','fluxPro11'].includes(id)) {
      modelHint.textContent = 'Text-to-image: wajib prompt. num_images opsional. Aspect ratio opsional.';
    } else if (id === 'seedream4edit') {
      modelHint.textContent = 'Seedream 4 Edit: wajib prompt + image (URL). Direkomendasikan untuk workflow UGC.';
    } else if (['upscalerCreative','upscalePrecV1','upscalePrecV2'].includes(id)) {
      modelHint.textContent = 'Upscaler: wajib image URL. Prompt opsional.';
    } else if (id === 'removeBg') {
      modelHint.textContent = 'Remove Background: wajib image URL. Response langsung URL hasil (valid 5 menit).';
    } else if (['wan480','wan720','seedancePro480','seedancePro720','seedancePro1080','klingStd21','kling25Pro','minimax1080'].includes(id)) {
      modelHint.textContent = 'Image-to-video: wajib image URL + prompt singkat. Pilih durasi & layout (portrait / landscape / square).';
    } else if (id === 'latentSync') {
      modelHint.textContent = 'Latent-Sync: wajib video URL dan audio URL. Prompt opsional.';
    } else {
      modelHint.textContent = 'Isi prompt dan field sesuai model.';
    }
  }

  function getVideoDurationMeta(modelId) {
    return VIDEO_MODEL_DURATION_OPTIONS[modelId] || VIDEO_MODEL_DURATION_OPTIONS._default;
  }

  function ensureVideoLayoutOptions() {
    if (!videoLayoutSelect || videoLayoutSelect.dataset.populated) return;
    videoLayoutSelect.innerHTML = '';
    VIDEO_LAYOUT_OPTIONS.forEach(opt => {
      const option = document.createElement('option');
      option.value = opt.value;
      option.textContent = opt.label;
      videoLayoutSelect.appendChild(option);
    });
    videoLayoutSelect.dataset.populated = '1';
  }

  function configureVideoControls(modelId) {
    if (!rowVideoSettings || !videoDurationSelect || !videoLayoutSelect) return;
    ensureVideoLayoutOptions();

    const meta = getVideoDurationMeta(modelId);
    const durations = Array.isArray(meta.values) && meta.values.length ? meta.values : getVideoDurationMeta('_default').values;

    videoDurationSelect.innerHTML = '';
    durations.forEach(sec => {
      const option = document.createElement('option');
      option.value = String(sec);
      option.textContent = `${sec} detik`;
      videoDurationSelect.appendChild(option);
    });

    const defaultDuration = meta.default || durations[0] || null;
    if (defaultDuration) {
      videoDurationSelect.value = String(defaultDuration);
    } else if (videoDurationSelect.options.length) {
      videoDurationSelect.selectedIndex = 0;
    }

    const layoutDefault = (meta.defaultLayout && VIDEO_LAYOUT_TO_RATIO[meta.defaultLayout]) ? meta.defaultLayout : (videoLayoutSelect.options[0] ? videoLayoutSelect.options[0].value : '');
    if (layoutDefault) {
      videoLayoutSelect.value = layoutDefault;
    }
  }

  function updateFields() {
    const id = modelSelect.value;

    const isT2I = ['gemini','imagen3','seedream4','fluxPro11'].includes(id);
    const isEdit = ['seedream4edit','upscalerCreative','upscalePrecV1','upscalePrecV2','removeBg'].includes(id);
    const isI2V = ['wan480','wan720','seedancePro480','seedancePro720','seedancePro1080','klingStd21','kling25Pro','minimax1080'].includes(id);
    const isLip = id === 'latentSync';

    rowImageUrl.classList.add('hidden');
    rowVideoAudio.classList.add('hidden');
    rowVideoSettings.classList.add('hidden');
    rowTIOptions.classList.add('hidden');
    rowPrompt.classList.remove('hidden');

    if (isT2I) {
      fieldsTitle.textContent = 'Image Generator';
      rowTIOptions.classList.remove('hidden');
    } else if (isEdit) {
      fieldsTitle.textContent = 'Image Editing';
      rowImageUrl.classList.remove('hidden');
      if (id === 'removeBg') rowPrompt.classList.add('hidden');
    } else if (isI2V) {
      fieldsTitle.textContent = 'Video Generator';
      rowImageUrl.classList.remove('hidden');
      rowVideoSettings.classList.remove('hidden');
      configureVideoControls(id);
    } else if (isLip) {
      fieldsTitle.textContent = 'Lipsync Studio';
      rowVideoAudio.classList.remove('hidden');
    } else {
      fieldsTitle.textContent = 'Input';
    }

    syncGeminiVisibility();
  }

  let currentFeature = 'imageGen';

  function setFeature(featureKey) {
    if (!featureKey) return;
    if (!featureAvailableForCurrentUser(featureKey)) {
      showFeatureLockedMessage(featureKey);
      updateFeatureTabsAvailability();
      return;
    }
    currentFeature = featureKey;
    if (featureTabs.length) {
      featureTabs.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.feature === featureKey);
      });
    }

    updateFeatureTabsAvailability();

    const allowed = new Set(FEATURE_MODELS[featureKey] || []);
    const options = modelSelect.querySelectorAll('option');
    let firstVisible = null;

    options.forEach(opt => {
      const id = opt.value;
      if (!id) return;
      if (allowed.has(id)) {
        opt.disabled = false;
        opt.hidden = false;
        if (!firstVisible) firstVisible = opt;
      } else {
        opt.disabled = true;
        opt.hidden = true;
      }
    });

    if (firstVisible) {
      modelSelect.value = firstVisible.value;
      setModelHint(firstVisible.value);
      updateFields();
    }

    if (!featureLabel) return;
    let label;
    if (featureKey === 'imageGen') label = 'Image Generator';
    else if (featureKey === 'imageEdit') label = 'Image Editing';
    else if (featureKey === 'videoGen') label = 'Video Generator';
    else if (featureKey === 'lipsync') label = 'Lipsync Studio';
    else label = 'AI Hub';
    featureLabel.textContent = label;
    if (navButtons.length && viewHubSection && viewHubSection.style.display !== 'none') {
      activateNav('viewHub', featureKey);
    }
  }

  function renderJobs() {
    queueList.innerHTML = '';
    historyList.innerHTML = '';

    const queue = jobs.filter(j => !finalStatus(j.status));
    const history = jobs.filter(j => finalStatus(j.status));

    queueEmpty.style.display = queue.length ? 'none' : 'block';
    historyEmpty.style.display = history.length ? 'none' : 'block';

    queue.forEach(j => queueList.appendChild(renderJobItem(j)));
    history.forEach(j => historyList.appendChild(renderJobItem(j)));

    if (activeJobId) {
      const activeJob = jobs.find(j => j.id === activeJobId);
      if (activeJob) syncStatusProgress(activeJob);
    }

    updateDashboardStats();
  }

  function renderJobItem(job) {
    const cfg = MODEL_CONFIG[job.modelId];
    const el = document.createElement('div');
    el.className = 'job-item';

    const header = document.createElement('div');
    header.className = 'job-header';

    const title = document.createElement('div');
    title.className = 'job-title';
    title.textContent = cfg ? cfg.label : job.modelId;

    const status = document.createElement('div');
    status.className = 'job-status';
    const st = (job.status || '').toUpperCase();
    if (st === 'COMPLETED') status.classList.add('completed');
    else if (st === 'FAILED' || st === 'ERROR') status.classList.add('error');
    else status.classList.add('in-progress');
    status.textContent = st || 'UNKNOWN';

    header.appendChild(title);
    header.appendChild(status);

    const meta = document.createElement('div');
    meta.className = 'job-meta';
    meta.textContent = `${shortTime(job.createdAt)} • ${job.type.toUpperCase()} • ${job.taskId ? 'task ' + job.taskId.slice(0,8) : 'no task_id'}`;

    const actions = document.createElement('div');
    actions.className = 'job-actions';

    const viewBtn = document.createElement('button');
    viewBtn.type = 'button';
    viewBtn.className = 'small secondary';
    viewBtn.textContent = 'View';
    viewBtn.onclick = () => {
      activeJobId = job.id;
      renderPreview(job);
    };
    actions.appendChild(viewBtn);

    const cfgHasStatus = cfg && cfg.statusPath && job.taskId;
    if (!finalStatus(job.status) && cfgHasStatus) {
      const checkBtn = document.createElement('button');
      checkBtn.type = 'button';
      checkBtn.className = 'small secondary';
      checkBtn.textContent = 'Check';
      checkBtn.onclick = () => pollJobOnce(job.id);
      actions.appendChild(checkBtn);
    }

    el.appendChild(header);
    el.appendChild(meta);

    if (!finalStatus(job.status)) {
      const progressWrap = document.createElement('div');
      progressWrap.className = 'job-progress';

      const label = document.createElement('div');
      label.className = 'job-progress-label';
      const pct = getJobProgress(job);
      label.innerHTML = `<span>Progress</span><span>${pct}%</span>`;

      const track = document.createElement('div');
      track.className = 'progress-track';
      const fill = document.createElement('div');
      fill.className = 'progress-fill';
      fill.style.width = pct + '%';
      track.appendChild(fill);

      progressWrap.appendChild(label);
      progressWrap.appendChild(track);
      el.appendChild(progressWrap);
    }

    el.appendChild(actions);
    return el;
  }

  function renderPreview(job) {
    if (!job) {
      previewContainer.style.display = 'none';
      previewEmpty.style.display = 'block';
      syncStatusProgress(null);
      return;
    }
    const flashPreviewItem = (el) => {
      if (!el) return;
      el.classList.add('preview-item--active');
      setTimeout(() => {
        el.classList.remove('preview-item--active');
      }, 220);
    };
    
    const cfg = MODEL_CONFIG[job.modelId];
    previewContainer.style.display = 'block';
    previewEmpty.style.display = 'none';

    previewJobMeta.textContent =
      `${cfg ? cfg.label : job.modelId} • status ${job.status || 'UNKNOWN'} • dibuat ${shortTime(job.createdAt)}`;

    syncStatusProgress(job);

    previewGrid.innerHTML = '';

    const urls = [];
    if (Array.isArray(job.localUrls) && job.localUrls.length) {
      urls.push(...job.localUrls);
    } else if (Array.isArray(job.generated)) {
      urls.push(...job.generated);
    }
    if (job.extraUrl && !urls.length) urls.push(job.extraUrl);

    if (!urls.length) {
      const msg = document.createElement('div');
      msg.className = 'muted';
      msg.style.fontSize = '11px';
      msg.textContent = 'Belum ada URL hasil untuk job ini.';
      previewGrid.appendChild(msg);
      return;
    }

    urls.forEach((url, idx) => {
      const item = document.createElement('div');
      item.className = 'preview-item';

      const assetType = (job.type === 'video' || isVideoUrl(url)) ? 'video' : 'image';

      const thumb = document.createElement('div');
      thumb.className = 'preview-thumb';
      thumb.classList.add(assetType === 'video' ? 'is-video' : 'is-image');

      let media;
      if (assetType === 'video') {
        media = document.createElement('video');
        media.src = url;
        media.controls = true;
        media.loop = true;
        media.muted = true;
        media.playsInline = true;
      } else {
        media = document.createElement('img');
        media.src = url;
        media.alt = `Result ${idx + 1}`;
        media.classList.add('clickable-media');
      }

      thumb.appendChild(media);

      if (assetType === 'image') {
        thumb.addEventListener('click', () => {
          flashPreviewItem(item);
          openAssetPreview(url, assetType);
        });
      } else {
        thumb.addEventListener('click', () => {
          flashPreviewItem(item);
        });
      }

      const metaRow = document.createElement('div');
      metaRow.className = 'preview-meta';

      const btnGroup = document.createElement('div');
      btnGroup.className = 'preview-btn-group';

      const previewBtn = document.createElement('button');
      previewBtn.type = 'button';
      previewBtn.className = 'small secondary';
      previewBtn.textContent = 'Preview';
      previewBtn.addEventListener('click', () => {
        flashPreviewItem(item);
        openAssetPreview(url, assetType);
      });
      btnGroup.appendChild(previewBtn);

      const urlSpan = document.createElement('div');
      urlSpan.className = 'preview-url';
      urlSpan.textContent = url;

      const dlLink = document.createElement('a');
      dlLink.href = url;
      dlLink.target = '_blank';
      dlLink.download = '';
      dlLink.className = 'download-link';

      const dlBtn = document.createElement('button');
      dlBtn.type = 'button';
      dlBtn.className = 'small';
      dlBtn.textContent = 'Download';
      dlLink.appendChild(dlBtn);

      btnGroup.appendChild(dlLink);

      metaRow.appendChild(btnGroup);
      metaRow.appendChild(urlSpan);

      item.appendChild(thumb);
      item.appendChild(metaRow);

      previewGrid.appendChild(item);
    });
  }

  function refreshPreview() {
    const job = jobs.find(j => j.id === activeJobId);
    renderPreview(job || null);
  }

  async function callFreepik(cfg, body, method = 'POST') {
    const payload = {
      path: cfg.statusPath && method === 'GET' ? cfg.statusPath(body.taskId) : cfg.path,
      method,
      body: method === 'GET' ? undefined : body,
      contentType: cfg.contentType || 'json'
    };

    const res = await fetch(FREEPIK_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      console.error('Response /?api=freepik bukan JSON. Raw:', text);
      throw new Error('Endpoint PHP mengembalikan HTML / non-JSON. Cek bagian proxy di atas file.');
    }

    if (!json.ok) {
      throw new Error(`HTTP ${json.status} – ${(json.data && json.data.message) || json.error || 'unknown error'}`);
    }
    return json.data;
  }

  // ===== CACHE DI SERVER =====
  async function cacheUrl(remoteUrl) {
    const res = await fetch(CACHE_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: remoteUrl })
    });

    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      console.error('Response /?api=cache bukan JSON. Raw:', text);
      throw new Error('Endpoint cache mengembalikan HTML / non-JSON.');
    }

    if (!json.ok) {
      throw new Error(json.error || 'Cache gagal');
    }
    return json.url || json.path;
  }

  function resetImageUploadArea(clearStatus = true) {
    if (imageUploadInput) imageUploadInput.value = '';
    if (imageUploadPreview) {
      imageUploadPreview.src = '';
      imageUploadPreview.style.display = 'none';
    }
    if (imageUploadDropzone) {
      imageUploadDropzone.classList.remove('has-file', 'dragover');
    }
    if (clearStatus) setImageUploadStatus('', null);
  }

  function setImageUploadStatus(text, mode) {
    if (!imageUploadStatus) return;
    imageUploadStatus.textContent = text || '';
    imageUploadStatus.classList.remove('ok', 'err', 'progress');
    if (!text) {
      imageUploadStatus.style.display = 'none';
      return;
    }
    if (mode) imageUploadStatus.classList.add(mode);
    imageUploadStatus.style.display = 'block';
  }

  async function uploadFileToServer(file) {
    const formData = new FormData();
    formData.append('file', file);

    const res = await fetch(UPLOAD_ENDPOINT, {
      method: 'POST',
      body: formData
    });

    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      console.error('Response /?api=upload bukan JSON. Raw:', text);
      throw new Error('Endpoint upload mengembalikan format tidak valid.');
    }

    if (!json.ok) {
      throw new Error(json.error || 'Upload gagal');
    }

    return {
      url: json.url || json.path,
      name: json.name || file.name
    };
  }

  async function uploadImageFile(file) {
    if (!file) return;
    if (!file.type || !file.type.startsWith('image/')) {
      setImageUploadStatus('File harus gambar (PNG/JPG/WEBP).', 'err');
      return;
    }

    setImageUploadStatus('Mengunggah ' + file.name + '…', 'progress');

    try {
      const result = await uploadFileToServer(file);
      const url = result.url;

      if (imageUrlInput && url) {
        imageUrlInput.value = url;
      }
      if (imageUploadPreview && url) {
        imageUploadPreview.src = url;
        imageUploadPreview.style.display = 'block';
      }
      if (imageUploadDropzone) {
        imageUploadDropzone.classList.add('has-file');
      }

      setImageUploadStatus('Upload sukses: ' + (result.name || file.name), 'ok');
      setStatus('Gambar berhasil diupload ke server.', 'ok');
    } catch (err) {
      console.error(err);
      setImageUploadStatus(err.message || 'Upload gagal.', 'err');
      setStatus('Upload gagal: ' + err.message, 'err');
    }
  }

  function handleImageFileList(fileList) {
    if (!fileList || !fileList.length) return;
    const file = fileList[0];
    resetImageUploadArea();
    uploadImageFile(file);
  }

  async function uploadGeminiFile(file) {
    if (!file || !file.type || !file.type.startsWith('image/')) {
      setStatus('File harus gambar (PNG/JPG/WEBP).', 'err');
      return;
    }
    const isGemini = modelSelect && modelSelect.value === 'gemini';
    if (!isGemini) {
      setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
      return;
    }
    const limit = getGeminiLimit();
    if (!limit) {
      setStatus('Mode text-only tidak memerlukan referensi.', 'err');
      return;
    }
    if (geminiReferences.length >= limit) {
      setStatus(`Batas referensi tercapai (${limit}).`, 'err');
      return;
    }

    try {
      setStatus('Mengunggah referensi ' + file.name + '…');
      const result = await uploadFileToServer(file);
      const url = result.url;
      if (!url) throw new Error('URL hasil upload tidak ditemukan.');
      if (geminiReferences.some(ref => ref.url === url)) {
        setStatus('Gambar sudah ada dalam daftar referensi.', 'err');
        return;
      }
      geminiReferences.push({ url, name: result.name || file.name, source: 'upload' });
      renderGeminiRefs(isGemini);
      setStatus('Referensi ditambahkan.', 'ok');
    } catch (err) {
      console.error(err);
      setStatus('Upload referensi gagal: ' + err.message, 'err');
    }
  }

  async function handleGeminiFileList(fileList) {
    if (!fileList || !fileList.length) return;
    const isGemini = modelSelect && modelSelect.value === 'gemini';
    if (!isGemini) {
      setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
      return;
    }
    const limit = getGeminiLimit();
    if (!limit) {
      setStatus('Mode text-only tidak memerlukan referensi.', 'err');
      return;
    }
    let available = limit - geminiReferences.length;
    if (available <= 0) {
      setStatus(`Batas referensi tercapai (${limit}).`, 'err');
      return;
    }

    const files = Array.from(fileList).filter(f => f && f.type && f.type.startsWith('image/'));
    if (!files.length) {
      setStatus('Tidak ada file gambar yang valid.', 'err');
      return;
    }

    let processed = 0;
    for (const file of files) {
      if (available <= 0) break;
      await uploadGeminiFile(file);
      processed += 1;
      available = limit - geminiReferences.length;
    }

    if (processed < files.length) {
      setStatus('Sebagian file diabaikan karena batas referensi tercapai.', 'idle');
    }
  }

  function addGeminiReferenceUrl() {
    if (!geminiRefUrl) return;
    const isGemini = modelSelect && modelSelect.value === 'gemini';
    if (!isGemini) {
      setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
      return;
    }
    const url = geminiRefUrl.value.trim();
    if (!url) {
      setStatus('Masukkan URL gambar referensi terlebih dahulu.', 'err');
      return;
    }
    if (!/^https?:\/\//i.test(url)) {
      setStatus('URL harus diawali http:// atau https://', 'err');
      return;
    }
    const limit = getGeminiLimit();
    if (!limit) {
      setStatus('Mode text-only tidak memerlukan referensi.', 'err');
      return;
    }
    if (geminiReferences.length >= limit) {
      setStatus(`Batas referensi tercapai (${limit}).`, 'err');
      return;
    }
    if (geminiReferences.some(ref => ref.url === url)) {
      setStatus('URL sudah ada dalam daftar referensi.', 'err');
      return;
    }

    const nameHint = url.split(/[/?#]/).filter(Boolean).pop() || 'URL Referensi';
    geminiReferences.push({ url, name: nameHint, source: 'url' });
    geminiRefUrl.value = '';
    renderGeminiRefs(isGemini);
    setStatus('URL referensi ditambahkan.', 'ok');
  }

  if (imageUploadButton) {
    imageUploadButton.addEventListener('click', () => {
      if (imageUploadInput) imageUploadInput.click();
    });
  }

  if (imageUploadInput) {
    imageUploadInput.addEventListener('change', e => {
      handleImageFileList(e.target.files);
      e.target.value = '';
    });
  }

  if (imageUploadDropzone) {
    imageUploadDropzone.addEventListener('click', () => {
      if (imageUploadInput) imageUploadInput.click();
    });
    ['dragenter','dragover'].forEach(evt => {
      imageUploadDropzone.addEventListener(evt, ev => {
        ev.preventDefault();
        imageUploadDropzone.classList.add('dragover');
      });
    });
    ['dragleave','dragend'].forEach(evt => {
      imageUploadDropzone.addEventListener(evt, ev => {
        ev.preventDefault();
        imageUploadDropzone.classList.remove('dragover');
      });
    });
    imageUploadDropzone.addEventListener('drop', ev => {
      ev.preventDefault();
      imageUploadDropzone.classList.remove('dragover');
      if (ev.dataTransfer && ev.dataTransfer.files) {
        handleImageFileList(ev.dataTransfer.files);
      }
    });
  }

  if (geminiFileButton) {
    geminiFileButton.addEventListener('click', () => {
      if (!modelSelect || modelSelect.value !== 'gemini') {
        setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
        return;
      }
      if (geminiMode === 'text') {
        setStatus('Mode text-only tidak memerlukan referensi.', 'err');
        return;
      }
      if (geminiFileInput) geminiFileInput.click();
    });
  }

  if (geminiFileInput) {
    geminiFileInput.addEventListener('change', async e => {
      await handleGeminiFileList(e.target.files);
      e.target.value = '';
    });
  }

  if (geminiDropzone) {
    geminiDropzone.addEventListener('click', () => {
      if (!modelSelect || modelSelect.value !== 'gemini') {
        setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
        return;
      }
      if (geminiMode === 'text') {
        setStatus('Mode text-only tidak memerlukan referensi.', 'err');
        return;
      }
      if (geminiFileInput) geminiFileInput.click();
    });
    ['dragenter','dragover'].forEach(evt => {
      geminiDropzone.addEventListener(evt, ev => {
        const isGemini = modelSelect && modelSelect.value === 'gemini';
        if (!isGemini || geminiMode === 'text') return;
        ev.preventDefault();
        geminiDropzone.classList.add('dragover');
      });
    });
    ['dragleave','dragend'].forEach(evt => {
      geminiDropzone.addEventListener(evt, ev => {
        ev.preventDefault();
        geminiDropzone.classList.remove('dragover');
      });
    });
    geminiDropzone.addEventListener('drop', async ev => {
      ev.preventDefault();
      geminiDropzone.classList.remove('dragover');
      const isGemini = modelSelect && modelSelect.value === 'gemini';
      if (!isGemini) {
        setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
        return;
      }
      if (geminiMode === 'text') {
        setStatus('Mode text-only tidak memerlukan referensi.', 'err');
        return;
      }
      if (ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files.length) {
        await handleGeminiFileList(ev.dataTransfer.files);
      }
    });
  }

  if (geminiRefAddBtn) {
    geminiRefAddBtn.addEventListener('click', () => addGeminiReferenceUrl());
  }

  if (geminiRefUrl) {
    geminiRefUrl.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        addGeminiReferenceUrl();
      }
    });
  }

  if (imageUrlInput) {
    imageUrlInput.addEventListener('input', () => {
      if (!imageUrlInput.value) {
        resetImageUploadArea();
      }
    });
  }

  document.addEventListener('paste', async ev => {
    const files = ev.clipboardData && ev.clipboardData.files;
    if (!files || !files.length) return;
    const target = ev.target;
    const tag = target && target.tagName ? target.tagName.toLowerCase() : '';
    if (['input','textarea'].includes(tag)) return;

    const isGemini = modelSelect && modelSelect.value === 'gemini' && geminiMode !== 'text';
    if (isGemini) {
      ev.preventDefault();
      setStatus('Menempel gambar referensi ke Gemini…');
      await handleGeminiFileList(files);
      return;
    }

    if (!imageUploadDropzone) return;
    handleImageFileList(files);
    setStatus('Menempel gambar dari clipboard…');
  });

  async function ensureLocalFiles(job) {
    if (!job || !Array.isArray(job.generated) || !job.generated.length) return;
    if (job.localUrls && job.localUrls.length === job.generated.length) return;

    const local = [];
    for (const u of job.generated) {
      try {
        const lu = await cacheUrl(u);
        local.push(lu);
      } catch (e) {
        console.error('Gagal cache', u, e);
      }
    }
    if (local.length) {
      job.localUrls = local;
      job.updatedAt = nowIso();
      saveJobs();
      renderJobs();
      if (job.id === activeJobId) refreshPreview();
    }
  }

  async function createTask(modelId) {
    const cfg = MODEL_CONFIG[modelId];
    if (!cfg) throw new Error('Model tidak dikenal');

    const formData = {
      prompt: promptInput.value.trim(),
      imageUrl: imageUrlInput.value.trim(),
      videoUrl: videoUrlInput.value.trim(),
      audioUrl: audioUrlInput.value.trim(),
      videoDuration: (videoDurationSelect && videoDurationSelect.value) ? Number(videoDurationSelect.value) : null,
      videoLayout: videoLayoutSelect && videoLayoutSelect.value ? videoLayoutSelect.value : null,
      numImages: numImagesInput.value ? Number(numImagesInput.value) : null,
      aspectRatio: aspectRatioInput.value || null
    };

    if (formData.imageUrl) {
      formData.imageUrl = ensureAbsoluteUrl(formData.imageUrl);
      if (imageUrlInput) {
        imageUrlInput.value = formData.imageUrl;
      }
    }
    if (formData.videoUrl) {
      formData.videoUrl = ensureAbsoluteUrl(formData.videoUrl);
      if (videoUrlInput) {
        videoUrlInput.value = formData.videoUrl;
      }
    }
    if (formData.audioUrl) {
      formData.audioUrl = ensureAbsoluteUrl(formData.audioUrl);
      if (audioUrlInput) {
        audioUrlInput.value = formData.audioUrl;
      }
    }

    const requireImageModels = [
      'upscalerCreative','upscalePrecV1','upscalePrecV2','removeBg',
      'wan480','wan720','seedancePro480','seedancePro720','seedancePro1080',
      'klingStd21','kling25Pro','minimax1080','seedream4edit'
    ];

    if (!formData.prompt && ['gemini','imagen3','seedream4','fluxPro11'].includes(modelId)) {
      throw new Error('Prompt wajib diisi untuk model text-to-image.');
    }
    if (requireImageModels.includes(modelId) && !formData.imageUrl) {
      throw new Error('Image URL wajib diisi untuk model ini.');
    }
    if (modelId === 'latentSync') {
      if (!formData.videoUrl || !formData.audioUrl) {
        throw new Error('Latent-Sync butuh video URL dan audio URL.');
      }
    }

    let usedGeminiRefs = null;
    let usedGeminiMode = null;
    if (modelId === 'gemini') {
      const meta = getGeminiMeta(geminiMode);
      const refs = geminiReferences.map(ref => ref.url).filter(Boolean);
      if (meta.max > 0) {
        if (refs.length < meta.min) {
          if (geminiMode === 'single') {
            throw new Error('Mode 2 membutuhkan tepat 1 gambar referensi.');
          }
          throw new Error('Mode 3 membutuhkan minimal 2 gambar referensi.');
        }
        if (refs.length > meta.max) {
          refs.splice(meta.max);
        }
      }
      if (refs.length) {
        try {
          formData.referenceImages = await prepareGeminiReferenceImages(refs);
        } catch (err) {
          throw new Error('Gagal memproses gambar referensi: ' + err.message);
        }
      } else {
        formData.referenceImages = [];
      }
      formData.geminiMode = geminiMode;
      usedGeminiRefs = refs.slice();
      usedGeminiMode = geminiMode;
    }

    const body = cfg.buildBody(formData);
    const data = await callFreepik(cfg, body, 'POST');

    let taskId = null;
    let status = null;
    let generated = null;
    let extraUrl = null;

    if (data && data.data && typeof data.data === 'object') {
      taskId   = data.data.task_id || null;
      status   = data.data.status   || null;
      generated = data.data.generated || null;
    } else if (data && typeof data === 'object') {
      if (cfg.id === 'removeBg') {
        extraUrl = data.url || data.high_resolution || data.preview || null;
        generated = [];
        if (data.url) generated.push(data.url);
        else if (data.high_resolution) generated.push(data.high_resolution);
        else if (data.preview) generated.push(data.preview);
        status = 'COMPLETED';
      } else if (Array.isArray(data.generated)) {
        generated = data.generated;
      }
    }

    return {
      taskId,
      status,
      generated,
      extraUrl,
      references: usedGeminiRefs,
      geminiModeUsed: usedGeminiMode,
      formData
    };
  }

  async function fetchStatus(modelId, taskId) {
    const cfg = MODEL_CONFIG[modelId];
    if (!cfg || !cfg.statusPath) throw new Error('Model tidak punya endpoint status.');
    const data = await callFreepik(cfg, { taskId }, 'GET');

    let status = null;
    let generated = null;
    if (data && data.data) {
      status = data.data.status || null;
      generated = data.data.generated || null;
    }
    return { status, generated };
  }

  function startPolling(job) {
    const cfg = MODEL_CONFIG[job.modelId];
    if (!cfg || !cfg.statusPath || !job.taskId) return;
    if (pollingTimers[job.id]) clearInterval(pollingTimers[job.id]);

    pollingTimers[job.id] = setInterval(() => {
      pollJobOnce(job.id);
    }, 8000);
  }

  async function pollJobOnce(jobId) {
    const job = jobs.find(j => j.id === jobId);
    if (!job) return;
    const cfg = MODEL_CONFIG[job.modelId];
    if (!cfg || !cfg.statusPath || !job.taskId) return;

    try {
      const { status, generated } = await fetchStatus(job.modelId, job.taskId);
      job.status = status || job.status;
      if (generated && Array.isArray(generated) && generated.length) {
        job.generated = generated;
      }
      job.updatedAt = nowIso();
      saveJobs();
      renderJobs();
      if (job.id === activeJobId) refreshPreview();

      if (finalStatus(job.status)) {
        finishJobProgress(job);
        if (pollingTimers[job.id]) {
          clearInterval(pollingTimers[job.id]);
          delete pollingTimers[job.id];
        }
        if (job.generated && job.generated.length) {
          await ensureLocalFiles(job);
        }
        await syncJobToDrive(job);
        if (job.type === 'video') {
          const url = (job.localUrls && job.localUrls[0]) ||
                      (job.generated && job.generated[0]) ||
                      job.extraUrl || null;
          if (url) {
            ugcItems.forEach(it => {
              if (it.videoJobId === job.id && !it.videoUrl) {
                it.videoUrl = url;
              }
            });
            renderUgcList();
          }
        }
      }
    } catch (err) {
      console.error(err);
      setStatus('Gagal cek status: ' + err.message, 'err');
    }
  }

  document.getElementById('jobForm').addEventListener('submit', async e => {
    e.preventDefault();
    const modelId = modelSelect.value;
    const cfg = MODEL_CONFIG[modelId];
    if (!cfg) {
      setStatus('Model tidak valid.', 'err');
      return;
    }

    if (!featureAvailableForCurrentUser(currentFeature)) {
      showFeatureLockedMessage(currentFeature);
      return;
    }

    if (!currentAccount) {
      setStatus('Data akun belum siap, coba lagi sesaat lagi.', 'err');
      return;
    }
    if (!ensureCoins(COIN_COST_STANDARD)) {
      setStatus('Koin kamu tidak cukup untuk generate.', 'err');
      return;
    }

    submitBtn.disabled = true;
    setStatus('Server proses generate..');

    try {
      const {
        taskId,
        status,
        generated,
        extraUrl,
        references,
        geminiModeUsed,
        formData
      } = await createTask(modelId);
      await spendCoins(COIN_COST_STANDARD);
      const jobId = uuid();

      const job = {
        id: jobId,
        modelId,
        type: cfg.type,
        taskId: taskId || null,
        createdAt: nowIso(),
        updatedAt: nowIso(),
        status: status || (taskId ? 'CREATED' : 'COMPLETED'),
        generated: generated || [],
        extraUrl: extraUrl || null,
        prompt: formData ? formData.prompt || null : null
      };

      if (modelId === 'gemini') {
        job.references = Array.isArray(references) ? references : [];
        job.geminiMode = geminiModeUsed || geminiMode;
      }

      jobs.unshift(job);
      saveJobs();
      renderJobs();

      activeJobId = jobId;
      renderPreview(job);

      if (taskId && !finalStatus(job.status)) {
        startJobProgress(job);
        startPolling(job);
        setStatus('Task dibuat: ' + taskId.slice(0,8) + '…', 'ok');
      } else {
        finishJobProgress(job);
        setStatus('Task selesai (synchronous).', 'ok');
        if (job.generated && job.generated.length) {
          await ensureLocalFiles(job);
        }
        await syncJobToDrive(job);
      }
    } catch (err) {
      console.error(err);
      setStatus('Error: ' + err.message, 'err');
    } finally {
      submitBtn.disabled = false;
    }
  });

  modelSelect.addEventListener('change', () => {
    setModelHint(modelSelect.value);
    updateFields();
  });

  clearPromptBtn.addEventListener('click', () => {
    promptInput.value = '';
    imageUrlInput.value = '';
    videoUrlInput.value = '';
    audioUrlInput.value = '';
    numImagesInput.value = '1';
    aspectRatioInput.value = '';
    if (videoDurationSelect && modelSelect) {
      configureVideoControls(modelSelect.value);
    }
    resetImageUploadArea();
    resetGeminiState(true);
    updateGeminiModeUI(modelSelect && modelSelect.value === 'gemini');
    setStatus('Form dibersihkan.');
  });
  clearPreviewBtn.addEventListener('click', () => {
    activeJobId = null;
    renderPreview(null);
  });
  refreshQueueBtn.addEventListener('click', async () => {
    const queue = jobs.filter(j => !finalStatus(j.status) && j.taskId);
    for (const job of queue) {
      await pollJobOnce(job.id);
    }
    setStatus('Queue di-refresh.', 'ok');
  });
  clearHistoryBtn.addEventListener('click', () => {
    jobs = jobs.filter(j => !finalStatus(j.status));
    saveJobs();
    renderJobs();
    if (activeJobId && !jobs.find(j => j.id === activeJobId)) {
      activeJobId = null;
      renderPreview(null);
    }
  });

  if (featureTabs.length) {
    featureTabs.forEach(btn => {
      btn.addEventListener('click', () => {
        const key = btn.dataset.feature;
        if (!featureAvailableForCurrentUser(key)) {
          showFeatureLockedMessage(key);
          return;
        }
        setFeature(key);
      });
    });
  }

  if (driveTypeFilter) {
    driveTypeFilter.addEventListener('change', renderDriveItems);
  }
  if (driveSortFilter) {
    driveSortFilter.addEventListener('change', renderDriveItems);
  }
  if (driveDateFilter) {
    driveDateFilter.addEventListener('change', renderDriveItems);
  }
  if (driveClearDateBtn) {
    driveClearDateBtn.addEventListener('click', () => {
      if (driveDateFilter) driveDateFilter.value = '';
      renderDriveItems();
    });
  }

  navButtons = Array.from(document.querySelectorAll('.sidebar-link'));
  viewDashboardSection = document.getElementById('viewDashboard');
  viewDriveSection = document.getElementById('viewDrive');
  viewHubSection = document.getElementById('viewHub');
  viewFilmSection = document.getElementById('viewFilm');
  viewUGCSection = document.getElementById('viewUGC');
  viewAccountSection = document.getElementById('viewAccount');
  viewSections = {
    viewDashboard: viewDashboardSection,
    viewAccount: viewAccountSection,
    viewDrive: viewDriveSection,
    viewHub: viewHubSection,
    viewFilm: viewFilmSection,
    viewUGC: viewUGCSection
  };

  updateNavAvailability();
  updateFeatureTabsAvailability();
  updateMaintenanceOverlay();

  function activateNav(target, featureKey) {
    navButtons.forEach(btn => {
      const isHub = btn.dataset.target === 'viewHub';
      const matches = btn.dataset.target === target && (!isHub || (btn.dataset.feature || 'imageGen') === (featureKey || 'imageGen'));
      btn.classList.toggle('active', matches);
    });
  }

  function setActiveView(target) {
    Object.entries(viewSections).forEach(([key, el]) => {
      if (!el) return;
      const isActive = key === target;
      if (isActive) {
        el.style.display = '';
        el.removeAttribute('hidden');
      } else {
        el.style.display = 'none';
        el.setAttribute('hidden', '');
      }
    });
  }

  function showView(target, featureKey) {
    if (target === 'viewFilm' && !featureAvailableForCurrentUser('filmmaker')) {
      showFeatureLockedMessage('filmmaker');
      return;
    }
    if (target === 'viewUGC' && !featureAvailableForCurrentUser('ugc')) {
      showFeatureLockedMessage('ugc');
      return;
    }

    let resolvedFeatureKey = featureKey;
    if (target === 'viewHub') {
      resolvedFeatureKey = featureKey || currentFeature || 'imageGen';
      if (!featureAvailableForCurrentUser(resolvedFeatureKey)) {
        showFeatureLockedMessage(resolvedFeatureKey);
        return;
      }
    }

    setActiveView(target);

    if (target === 'viewHub') {
      setFeature(resolvedFeatureKey || 'imageGen');
    }
    if (target === 'viewDrive') {
      loadDriveItems();
    }

    activateNav(target, resolvedFeatureKey);
    closeSidebarOnMobile();
  }

  navButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled) {
        return;
      }
      const target = btn.dataset.target || 'viewDashboard';
      const featureKey = btn.dataset.feature || (target === 'viewHub' ? 'imageGen' : undefined);
      if (featureKey && !featureAvailableForCurrentUser(featureKey)) {
        showFeatureLockedMessage(featureKey);
        return;
      }
      showView(target, featureKey);
    });
  });

  showView('viewDashboard');

  // ===== FILMMAKER STATE =====
  let filmCharacterDataUrl = null;
  let filmAspect = '16:9';
  let filmScenes = [];
  let filmPollTimer = null;
  let filmStateMode = 'auto';

  const filmSceneLocations = [
    'crowded street market filled with ambient details',
    'rain-soaked neon city alley with reflective puddles',
    'dimly lit interior workspace surrounded by holographic monitors',
    'rooftop overlooking the skyline during blue hour',
    'abandoned warehouse with shafts of light piercing through windows',
    'lush urban park with misty morning atmosphere',
    'hi-tech control room glowing with translucent interfaces',
    'narrow subway platform with motion blur from passing trains'
  ];

  const filmSceneLightingPresets = [
    'dramatic rim lighting with strong contrast',
    'soft diffused lighting with pastel highlights',
    'high-contrast chiaroscuro with deep shadows',
    'golden hour sunlight with warm highlights',
    'cold tungsten practicals mixed with cyan fill light',
    'moody volumetric light cutting through atmosphere',
    'noir-style lighting with slatted window shadows',
    'neon glow accents with reflective surfaces'
  ];

  const filmSceneCameraAngles = [
    'wide establishing shot from a slightly elevated angle',
    'shoulder-level medium shot highlighting expressions',
    'dynamic low-angle shot that empowers the protagonist',
    'tracking shot with slight motion blur to imply movement',
    'close-up focusing on hands and important props',
    'dutch angle to emphasize tension and imbalance',
    'overhead shot revealing spatial relationships',
    'long lens compression shot isolating the subject'
  ];

  const filmSceneMoods = [
    'anticipation and intrigue',
    'quiet determination',
    'rising tension with subtle anxiety',
    'pulse-pounding urgency',
    'mystery with analytical focus',
    'confrontational and intense',
    'reflective calm after the storm',
    'hopeful yet unresolved cliffhanger tone'
  ];

  const filmNarrativeBeats = [
    'Opening beat that introduces the world and protagonist.',
    'Complication emerges, revealing a new obstacle.',
    'Discovery beat where new information shifts the stakes.',
    'Escalation sequence pushing the conflict forward.',
    'Strategic regrouping before the confrontation.',
    'Climactic confrontation with the central threat.',
    'Falling action showing immediate consequences.',
    'Teaser for the next chapter, leaving a lingering question.'
  ];

  const FILM_STATE_PRESETS = [
    {
      key: 'auto',
      label: 'AUTO STATE',
      description: 'Tidak Memilih · diproses oleh server',
      icon: '🤖',
      prompt: ''
    },
    {
      key: 'kdramaMelodrama',
      label: 'Melodrama K-Drama',
      description: 'Emotional, Slow-Burn',
      icon: '🌸',
      prompt: `
    **Scene Lokasi**: lush urban park with misty morning atmosphere or a rooftop overlooking the skyline during blue hour, often featuring rain.
    **LightingPresets**: soft diffused lighting with pastel highlights, giving a washed-out, dreamy look, mixed with ambient blue moon lighting for a cool night scene.
    **SceneCameraAngles**: long lens compression shot isolating the subject, emphasizing distance and longing. Use smooth crane shots revealing scale and transition, but keep the pacing slow.
    **SceneMoods**: quiet determination mixed with melancholic and deep sadness, often leading to a hopeful yet unresolved cliffhanger tone.
    **NarrativeBeats**: Two characters experience a painful separation or a moment of deep regret and longing. The dialogue is minimal; the emphasis is on sustained eye contact, slow reaction shots, and the environment reflecting their inner turmoil.
  `
    },
    {
      key: 'detectiveRealization',
      label: 'Realisasi Detektif',
      description: 'Misteri, Fokus',
      icon: '🔍',
      prompt: `
    **Scene Lokasi**: ornate, empty library reading room with dust motes dancing in the light.
    **LightingPresets**: high-contrast chiaroscuro with deep shadows, emphasizing one point of light.
    **SceneCameraAngles**: extreme close-up on the eyes to reveal deep emotion, transitioning to an overhead shot revealing spatial relationships.
    **SceneMoods**: mystery with analytical focus, shifting to sudden, jarring shock.
    **NarrativeBeats**: The protagonist finds the crucial, hidden clue in the seemingly empty room, followed immediately by the realization of the killer's identity.
  `
    },
    {
      key: 'cinematicBlockbuster',
      label: 'Film Sinematik',
      description: 'High-End, Wide-Screen Drama',
      icon: '🎞️',
      prompt: `
    **Scene Lokasi**: rooftop overlooking the skyline during blue hour, or a hi-tech control room glowing with translucent interfaces.
    **LightingPresets**: moody volumetric light cutting through atmosphere, utilizing a Teal & Orange color palette. Use dramatic rim lighting with strong contrast to separate the subject from the background.
    **SceneCameraAngles**: smooth crane shot revealing scale and transition, primarily using the wide establishing shot from a slightly elevated angle, often framed in a 21:9 aspect ratio.
    **SceneMoods**: rising tension with subtle anxiety, leading to quiet determination. The overall feeling is grand and polished.
    **NarrativeBeats**: The protagonist stands alone, contemplating a large-scale impending threat or decision that affects the city/world. The scene is about establishing the stakes and their powerful, singular resolve against a vast backdrop.
  `
    },
    {
      key: 'romanticNostalgia',
      label: 'Nostalgia Hangat',
      description: 'Reflektif Penuh Kenyamanan',
      icon: '☕️',
      prompt: `
    **Scene Lokasi**: a vintage diner booth late at night, under a broken neon sign.
    **LightingPresets**: warm fireplace glow casting dynamic shadows on walls, mixed with practical lighting only (lamps and screens) for natural realism.
    **SceneCameraAngles**: over-the-shoulder (OTS) shot for dialogue and conversation, frequently using a close-up focusing on hands (touching or holding a cup).
    **SceneMoods**: warm nostalgia and comfort of familiarity, transitioning to a hopeful yet unresolved cliffhanger tone.
    **NarrativeBeats**: Two characters share a late-night conversation, reminiscing about a shared past. The dialogue leads to a moment of soft realization about their current feelings for each other, leaving the future unspoken but implied.
  `
    },
    {
      key: 'nusantaraEpic',
      label: 'Epos Nusantara',
      description: 'Rich Cultural and Historical',
      icon: '🕌',
      prompt: `
    **Scene Lokasi**: a crowded street market filled with ambient details, featuring traditional textiles and food vendors, or an ancient, sun-drenched temple ruin.
    **LightingPresets**: strong, natural golden hour sunlight with warm highlights, emphasizing deep saturation of colors (Reds, Yellows, Golds). Use high-contrast lighting to define textures.
    **SceneCameraAngles**: wide establishing shot from a slightly elevated angle to capture the scope of the location, contrasted with close-up focusing on hands (crafting, offering, or traditional gestures).
    **SceneMoods**: quiet determination and a sense of warm nostalgia, leading to an eventual hopeful yet unresolved cliffhanger tone.
    **NarrativeBeats**: The protagonist is engaged in a moment of traditional ritual or communal life. The scene slowly builds tension as they must choose between preserving cultural heritage and navigating modern challenges. The focus is on the beauty of the setting and the gravitas of the decision.
  `
    },
    {
      key: 'situationalComedy',
  label: 'Komedi Situasional Ceria',
  description: 'Bright, High-Key',
  icon: '😂', 
  prompt: `
    **Scene Lokasi**: sun-drenched apartment balcony with potted plants and city noise, or a brightly lit, sterile location like a hi-tech control room glowing with translucent interfaces.
    **LightingPresets**: high-key bright white lighting for a sterile or comedic effect, or soft diffused lighting with maximum brightness to avoid shadows and ensure clear visibility of facial reactions.
    **SceneCameraAngles**: shoulder-level medium shot highlighting expressions and physical comedy, frequently using a reverse shot showing the exaggerated reaction of the listening character. Avoid dynamic movements like steadicam or crane shots.
    **SceneMoods**: whimsical and lighthearted with a sense of wonder, transitioning to playful competition and confident swagger. The tone must remain fast-paced and upbeat.
    **NarrativeBeats**: Two characters are in a minor disagreement or awkward situation. The action focuses on rapid-fire dialogue and visual gags. The scene ends abruptly with a punchline or a highly confused reaction from one character.
  `
    },
    {
      key: 'ecomAd',
      label: 'Iklan E-Commerce',
      description: 'Product Spotlight',
      icon: '🛍️',
      prompt: 'Product-forward advertising energy with hero lighting, macro beauty shots, upbeat rhythm, benefits-focused overlays, and aspirational lifestyle cutaways.'
    },
    {
      key: 'aiExplainer',
      label: 'Video Explainer AI',
      description: 'Futuristic Insight',
      icon: '🤖',
      prompt: 'Futuristic explainer tone blending holographic UI overlays, smooth dolly or orbit moves, clean gradients, and concise voiceover-ready storytelling beats.'
    },
    {
      key: 'formalPresentation',
      label: 'Presentasi YouTube',
      description: 'Formal',
      icon: '🧑‍🏫',
      prompt: 'Professional presentation mood with studio lighting, center-framed speaker, slide-insert cutaways, minimal transitions, and calm confident pacing.'
    },
    {
      key: 'cyberpunk',
      label: 'CyberPunk',
      description: 'Neon Noir',
      icon: '🌌',
      prompt: 'Cyberpunk worldbuilding drenched in neon, rain-soaked surfaces, holographic signage, kinetic camera moves, synth ambience, and magenta-teal color contrast.'
    }
  ];

  const FILM_STATE_LIBRARY = FILM_STATE_PRESETS.reduce((map, preset) => {
    map[preset.key] = preset;
    return map;
  }, {});

  function getFilmStatePreset(key = 'auto') {
    return FILM_STATE_LIBRARY[key] || FILM_STATE_LIBRARY.auto;
  }

  function updateFilmStateActiveState(activeKey) {
    if (!filmStateMenu) return;
    filmStateMenu.querySelectorAll('.ugc-style-option').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.value === activeKey);
    });
  }

  function toggleFilmStateMenu(forceOpen) {
    if (!filmStateMenu || !filmStateTrigger) return;
    const shouldOpen = typeof forceOpen === 'boolean'
      ? forceOpen
      : filmStateMenu.classList.contains('hidden');
    if (shouldOpen) {
      filmStateMenu.classList.remove('hidden');
      filmStateTrigger.classList.add('open');
    } else {
      filmStateMenu.classList.add('hidden');
      filmStateTrigger.classList.remove('open');
    }
  }

  function selectFilmState(key, closeMenu = true) {
    const preset = getFilmStatePreset(key);
    filmStateMode = preset.key;
    if (filmStateValueInput) filmStateValueInput.value = preset.key;
    if (filmStateLabelEl) filmStateLabelEl.textContent = preset.label;
    if (filmStateDescEl) filmStateDescEl.textContent = preset.description;
    if (filmStateIconEl) filmStateIconEl.textContent = preset.icon || '🎬';
    updateFilmStateActiveState(preset.key);
    if (closeMenu) toggleFilmStateMenu(false);
  }

  function renderFilmStateMenu() {
    if (!filmStateMenu) return;
    filmStateMenu.innerHTML = '';
    FILM_STATE_PRESETS.forEach(preset => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'ugc-style-option';
      option.dataset.value = preset.key;
      option.innerHTML = `
        <span class="ugc-style-option-icon">${preset.icon || '🎬'}</span>
        <div class="ugc-style-option-meta">
          <div class="ugc-style-option-label">${preset.label}</div>
          <div class="ugc-style-option-desc">${preset.description}</div>
        </div>
      `;
      option.addEventListener('click', () => selectFilmState(preset.key));
      filmStateMenu.appendChild(option);
    });
  }

  function capitalizeFirst(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function ensureSentence(text, fallback) {
    const base = (text || '').trim();
    if (!base) return fallback;
    return /[.!?…]$/.test(base) ? base : base + '.';
  }

  function collapseWhitespace(text) {
    return (text || '').replace(/\s+/g, ' ').trim();
  }

  function summarizeSceneSnippet(text) {
    const cleaned = collapseWhitespace(text);
    if (!cleaned) {
      return 'Peristiwa penting yang mendorong cerita';
    }
    return cleaned.length > 140 ? cleaned.slice(0, 137) + '…' : cleaned;
  }

  function extractStoryPartsForScenes(brief, count) {
    const cleaned = (brief || '').replace(/\r\n?/g, '\n');
    const parts = [];

    cleaned
      .split(/\n+/)
      .map(part => part.trim())
      .filter(Boolean)
      .forEach(part => parts.push(part));

    if (parts.length < count) {
      cleaned
        .split(/(?<=[.!?])\s+/)
        .map(part => part.trim())
        .filter(Boolean)
        .forEach(sentence => {
          if (!parts.includes(sentence)) {
            parts.push(sentence);
          }
        });
    }

    if (!parts.length) {
      parts.push(brief.trim() || 'Describe the protagonist in action');
    }

    const baseLength = parts.length;
    let idx = 0;
    while (parts.length < count) {
      parts.push(parts[idx % baseLength]);
      idx += 1;
    }

    return parts.slice(0, count);
  }

  function buildFilmScenePlans(brief, count) {
    const parts = extractStoryPartsForScenes(brief, count);
    const preset = getFilmStatePreset(filmStateMode);
    const storyBriefSection = ensureSentence(summarizeSceneSnippet(brief), 'Story brief tidak tersedia.');
    const filmStateSection = preset.key === 'auto'
      ? 'AUTO STATE · Sistem menentukan treatment sinematik terbaik.'
      : `${preset.label} · ${preset.prompt}`;
    let previousSummary = 'Belum ada scene sebelumnya; buka cerita berdasarkan story brief.';

    return parts.map((part, idx) => {
      const index = idx + 1;
      const action = ensureSentence(capitalizeFirst(part), 'Describe the protagonist in action.');
      const environment = filmSceneLocations[idx % filmSceneLocations.length];
      const lighting = filmSceneLightingPresets[idx % filmSceneLightingPresets.length];
      const camera = filmSceneCameraAngles[idx % filmSceneCameraAngles.length];
      const mood = filmSceneMoods[idx % filmSceneMoods.length];
      const nextSummary = idx + 1 < parts.length ? summarizeSceneSnippet(parts[idx + 1]) : null;
      const prevSummaryForPrompt = previousSummary;

      const beatIntro = ensureSentence(filmNarrativeBeats[idx % filmNarrativeBeats.length], 'Bangun ketegangan cerita.');
      const reactionLine = idx === 0
        ? 'Respon langsung terhadap premis story brief dan kenalkan motivasi utama tokoh'
        : `Respon terhadap peristiwa sebelumnya: ${prevSummaryForPrompt}`;
      const causeLine = nextSummary
        ? `Picu aksi menuju adegan selanjutnya: ${nextSummary}`
        : 'Berikan konsekuensi akhir yang menutup bab ini sekaligus membuka ruang refleksi';
      const narrativeBeat = [
        beatIntro,
        ensureSentence(reactionLine, 'Respon terhadap adegan sebelumnya.'),
        ensureSentence(causeLine, 'Picu aksi berikutnya.')
      ].join(' ');

      const currentSummary = ensureSentence(summarizeSceneSnippet(part), 'Adegan bergerak maju.');
      previousSummary = currentSummary;

      const previousLabel = index === 1 ? 'Scene Sebelumnya' : `Scene ${index - 1}`;
      const promptSections = [
        `[StoryBrief] ${storyBriefSection}`,
        `[FilmmakerState] ${filmStateSection}`,
        `[RINGKASAN Singkat ${previousLabel}] ${prevSummaryForPrompt}`,
        `[NarrativeBeat Scene ${index}] ${narrativeBeat}`,
        'characterReferences: gunakan gambar referensi karakter yang diupload agar tokoh konsisten.',
        `Setting/environment: ${environment}. Lighting: ${lighting}. Camera style: ${camera}. Mood: ${mood}.`
      ];

      return {
        index,
        prompt: promptSections.join(' '),
        meta: {
          action,
          environment,
          lighting,
          camera,
          mood,
          previousSummary: prevSummaryForPrompt,
          narrativeBeat,
          theme: preset ? preset.label : 'AUTO STATE'
        }
      };
    });
  }

  filmCharacterDrop.addEventListener('click', () => filmCharacterInput.click());

  filmCharacterInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('File harus gambar (PNG/JPG).');
      return;
    }
    const reader = new FileReader();
    reader.onload = ev => {
      filmCharacterDataUrl = ev.target.result;
      filmCharacterPreview.src = filmCharacterDataUrl;
      filmCharacterPreview.style.display = 'block';
      filmCharacterIdle.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });

  filmSceneCount.addEventListener('input', () => {
    filmSceneCountLabel.textContent = filmSceneCount.value + ' scenes';
  });

  renderFilmStateMenu();
  const initialFilmState = filmStateValueInput && filmStateValueInput.value ? filmStateValueInput.value : 'auto';
  selectFilmState(initialFilmState, false);

  if (filmStateTrigger) {
    filmStateTrigger.addEventListener('click', () => {
      const shouldOpen = filmStateMenu ? filmStateMenu.classList.contains('hidden') : true;
      toggleFilmStateMenu(shouldOpen);
    });
  }

  if (filmStatePicker) {
    document.addEventListener('click', event => {
      if (!filmStateMenu || !filmStateTrigger) return;
      if (!filmStatePicker.contains(event.target)) {
        toggleFilmStateMenu(false);
      }
    });
  }

  filmAspectButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      filmAspectButtons.forEach(b => b.classList.remove('film-aspect-active'));
      btn.classList.add('film-aspect-active');
      filmAspect = btn.dataset.filmAspect;
    });
  });

  function updateFilmProgressUI() {
    if (!filmProgressEl) return;
    const summary = summarizeTaskProgress(filmScenes, {
      isStarted: scene => !!(scene && scene.taskId),
      isCompleted: scene => !!(scene && finalStatus(scene.status))
    });
    const label = summary.total
      ? `${summary.percent}% (${summary.completed}/${summary.total})`
      : '0%';
    setInlineProgressState(filmProgressEl, filmProgressFill, filmProgressValue, summary.percent, summary.total > 0, label);
  }

  function renderFilmScenes() {
    if (!filmScenes.length) {
      filmScenesEmpty.style.display = 'flex';
      filmScenesContainer.innerHTML = '';
      updateFilmProgressUI();
      return;
    }
    filmScenesEmpty.style.display = 'none';
    filmScenesContainer.innerHTML = '';

    filmScenes.forEach(scene => {
      const card = document.createElement('div');
      card.className = 'film-scene-card';

      const header = document.createElement('div');
      header.className = 'film-scene-header';

      const title = document.createElement('div');
      title.className = 'film-scene-title';
      title.textContent = 'Scene ' + scene.index;

      const status = document.createElement('div');
      status.className = 'film-scene-status';
      const st = (scene.status || '').toUpperCase();
      if (st === 'COMPLETED') {
        status.classList.add('done');
        status.textContent = 'COMPLETED';
      } else if (st === 'FAILED' || st === 'ERROR') {
        status.classList.add('error');
        status.textContent = st;
      } else {
        status.classList.add('progress');
        status.textContent = st || 'CREATED';
      }

      header.appendChild(title);
      header.appendChild(status);
      card.appendChild(header);

      if (scene.url) {
        const img = document.createElement('img');
        img.className = 'film-scene-thumb';
        img.src = scene.url;
        img.alt = 'Scene ' + scene.index;
        img.classList.add('clickable-media');
        img.addEventListener('click', () => openAssetPreview(scene.url, 'image'));
        card.appendChild(img);

        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.justifyContent = 'flex-end';
        actions.style.marginTop = '4px';
        actions.style.gap = '6px';

        const previewBtn = document.createElement('button');
        previewBtn.type = 'button';
        previewBtn.className = 'small secondary';
        previewBtn.textContent = 'Preview';
        previewBtn.addEventListener('click', () => openAssetPreview(scene.url, 'image'));
        actions.appendChild(previewBtn);

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'small secondary';
        saveBtn.textContent = 'Simpan ke Drive';
        saveBtn.disabled = !scene.url;
        saveBtn.addEventListener('click', () => saveSceneToDrive(scene, saveBtn));
        actions.appendChild(saveBtn);

        const a = document.createElement('a');
        a.href = scene.url;
        a.target = '_blank';
        a.download = '';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'small';
        btn.textContent = 'Download';

        a.appendChild(btn);
        actions.appendChild(a);
        card.appendChild(actions);
      }

      const promptDiv = document.createElement('div');
      promptDiv.className = 'film-scene-prompt';
      promptDiv.textContent = scene.prompt || '';
      card.appendChild(promptDiv);

      filmScenesContainer.appendChild(card);
    });

    updateFilmProgressUI();
  }

  async function pollFilmScenesOnce() {
    const pending = filmScenes.filter(s => s.taskId && !finalStatus(s.status));
    if (!pending.length) return;

    for (const scene of pending) {
      try {
        const { status, generated } = await fetchStatus('gemini', scene.taskId);
        if (status) scene.status = status;
        if (generated && Array.isArray(generated) && generated.length) {
          const remote = generated[0];
          try {
            const local = await cacheUrl(remote);
            scene.url = local || remote;
          } catch {
            scene.url = remote;
          }
        }
      } catch (err) {
        scene.status = 'ERROR';
      }
    }
    renderFilmScenes();

    const stillPending = filmScenes.some(s => s.taskId && !finalStatus(s.status));
    if (!stillPending && filmPollTimer) {
      clearInterval(filmPollTimer);
      filmPollTimer = null;
    }
  }

  function startFilmPolling() {
    if (filmPollTimer) clearInterval(filmPollTimer);
    filmPollTimer = setInterval(pollFilmScenesOnce, 8000);
  }

  filmGenerateBtn.addEventListener('click', async () => {
    if (!featureAvailableForCurrentUser('filmmaker')) {
      showFeatureLockedMessage('filmmaker');
      return;
    }
    const brief = filmBriefInput.value.trim();
    const count = Number(filmSceneCount.value || '0');

    if (!filmCharacterDataUrl) {
      alert('Upload character image dulu.');
      return;
    }
    if (!brief) {
      alert('Story brief wajib diisi.');
      return;
    }
    if (count < 1) {
      alert('Minimal 1 scene.');
      return;
    }

    const cfg = MODEL_CONFIG.gemini;
    const base64 = filmCharacterDataUrl.replace(/^data:image\/[a-zA-Z+]+;base64,/, '');

    const scenePlans = buildFilmScenePlans(brief, count);
    const requiredCoins = Math.max(1, scenePlans.length * COIN_COST_FILM_PER_SCENE);
    if (!currentAccount) {
      alert('Data akun belum siap. Muat ulang halaman.');
      return;
    }
    if (!ensureCoins(requiredCoins)) {
      alert('Koin kamu tidak cukup untuk generate film.');
      return;
    }

    filmGenerateBtn.disabled = true;
    filmScenes = [];
    renderFilmScenes();

    let successfulScenes = 0;

    for (const plan of scenePlans) {
      const scenePrompt = plan.prompt;

      const body = {
        prompt: scenePrompt,
        num_images: 1,
        reference_images: [base64]
      };
      if (filmAspect === '9:16') {
        body.aspect_ratio = 'social_story_9_16';
      } else if (filmAspect === '16:9') {
        body.aspect_ratio = 'widescreen_16_9';
      }

      let taskId = null;
      let status = 'ERROR';
      let success = false;

      try {
        const data = await callFreepik(cfg, body, 'POST');
        status = 'CREATED';
        if (data && data.data) {
          taskId = data.data.task_id || null;
          status  = data.data.status   || status;
        }
        success = true;
      } catch (err) {
        console.error(err);
        status = 'ERROR';
      }

      filmScenes.push({
        index: plan.index,
        prompt: scenePrompt,
        meta: plan.meta,
        taskId,
        status,
        url: null
      });

      if (success) {
        const normalized = String(status || '').toUpperCase();
        if (normalized !== 'ERROR' && normalized !== 'FAILED') {
          successfulScenes += 1;
        }
      }

      renderFilmScenes();
    }

    const coinsToSpend = successfulScenes * COIN_COST_FILM_PER_SCENE;
    if (coinsToSpend > 0) {
      try {
        await spendCoins(coinsToSpend);
      } catch (err) {
        console.error('Gagal mengurangi koin film:', err);
        alert('Koin tidak dapat dikurangi: ' + err.message);
        try {
          await loadAccountState();
        } catch (loadErr) {
          console.warn('Tidak bisa me-refresh akun setelah gagal mengurangi koin:', loadErr);
        }
      }
    }

    startFilmPolling();
    filmGenerateBtn.disabled = false;
  });

  const UGC_IDEA_COUNT = 5;

  const UGC_STYLE_GROUPS = [
    {
      id: 'basic',
      title: 'Basic – Diverse & Flexible',
      styles: [
        {
          key: 'basic',
          label: 'Basic',
          description: 'Diverse & Flexible contexts',
          icon: '✨',
          prompt: 'balanced lifestyle composition highlighting the product naturally, creator-driven framing, warm ambient light'
        }
      ]
    },
    {
      id: 'action',
      title: 'Action with Product',
      styles: [
        { key: 'holding', label: 'Holding', description: 'Hand Positions & Grips', icon: '🤲', prompt: 'close-up of hands holding the product, showcasing grip, authentic user perspective, gentle focus on logo' },
        { key: 'using', label: 'Using', description: 'Active Application', icon: '👐', prompt: 'real person actively using the product mid-action, dynamic pose, candid smile, motion-friendly blur, real environment' },
        { key: 'unboxing', label: 'Unboxing', description: 'First Reveal', icon: '📦', prompt: 'first reveal moment with packaging being opened, excited reaction, tabletop context, natural daylight and product focus' },
        { key: 'in_use', label: 'In-Use', description: 'Real Moments', icon: '🎬', prompt: 'real-life scenario capturing product in everyday use, documentary angle, genuine emotion, ambient background details' },
        { key: 'quick_demo', label: 'Quick Demo', description: 'How-To Tutorial', icon: '🎥', prompt: 'step-by-step demonstration shot, clear view of key features, instructional framing, slightly angled top view' }
      ]
    },
    {
      id: 'showcase',
      title: 'Showcase & Demos',
      styles: [
        { key: 'pointing', label: 'Pointing', description: 'Direct Attention', icon: '👉', prompt: 'creator pointing at the product or key detail, guiding viewer attention, mid-shot with confident gesture' },
        { key: 'demo_review', label: 'Demo', description: 'Review Style', icon: '🎤', prompt: 'review-style presentation facing camera, product held near face, talk-to-camera energy, inviting smile' },
        { key: 'hands_only', label: 'Hands Only', description: 'Product Focus', icon: '✋', prompt: 'hands-only macro focus with minimal background, product centered, soft diffused lighting, tactile emphasis' },
        { key: 'size_compare', label: 'Size Compare', description: 'Scale Reference', icon: '📏', prompt: 'product side-by-side with familiar object to show scale, neutral background, overhead or eye-level perspective' }
      ]
    },
    {
      id: 'lifestyle',
      title: 'Lifestyle',
      styles: [
        { key: 'home', label: 'Home', description: 'Indoor Cozy', icon: '🏡', prompt: 'cozy indoor home setting, warm lighting, soft furnishings, lifestyle moment featuring the product naturally' },
        { key: 'outdoor', label: 'Outdoor', description: 'On-the-Go', icon: '🏞️', prompt: 'on-the-go outdoor lifestyle, natural sunlight, candid movement, engaging urban or nature backdrop' },
        { key: 'workspace', label: 'Workspace', description: 'Productivity', icon: '💻', prompt: 'productive workspace scene, desk accessories, modern tech aesthetic, cool daylight, tidy composition' },
        { key: 'routine', label: 'Routine', description: 'Step-by-Step', icon: '🔁', prompt: 'daily routine storytelling, sequential actions, clean bathroom or vanity setting, natural morning light' },
        { key: 'travel', label: 'Travel', description: 'Portable & Compact', icon: '✈️', prompt: 'portable travel vibe with suitcase or travel props, aspirational destination lighting, compact packing view' },
        { key: 'bedroom', label: 'Bedroom', description: 'Modern Lifestyle', icon: '🛏️', prompt: 'comfortable bedroom environment, soft textiles, morning glow, relaxed mood featuring the product' },
        { key: 'studio_fitting', label: 'Studio Fitting', description: 'Product Review', icon: '👗', prompt: 'studio fitting setup with fashion rack or mirror, editorial lighting, model evaluating outfit with product' },
        { key: 'getting_ready', label: 'Getting Ready', description: 'GRWM Routine', icon: '💄', prompt: 'get ready with me energy, mirror interaction, beauty station props, playful yet polished atmosphere' }
      ]
    },
    {
      id: 'flatlay',
      title: 'Flat Lay',
      styles: [
        { key: 'flatlay_minimal', label: 'Minimal', description: 'Clean & Simple', icon: '⚪', prompt: 'minimalist flat lay on neutral surface, overhead view, generous negative space, soft shadow, curated props' },
        { key: 'flatlay_styled', label: 'Styled', description: 'Editorial Props', icon: '🎀', prompt: 'editorial flat lay with styled props, colorful backdrop, layered textures, overhead composition with depth' }
      ]
    },
    {
      id: 'outfit',
      title: 'Outfit & Variants',
      styles: [
        { key: 'outfit', label: 'Outfit', description: 'Fashion OOTD Layout', icon: '👚', prompt: 'full outfit moment or layout, fashion-forward styling, vertical framing, confident stance or neatly arranged pieces' },
        { key: 'ingredients', label: 'Ingredients', description: 'Contents Focus', icon: '🥣', prompt: 'product components or ingredients arranged neatly, storytelling labels, tabletop lighting, organized composition' },
        { key: 'color_variants', label: 'Color Variants', description: 'Options Showcase', icon: '🎨', prompt: 'multiple color variations displayed side-by-side, consistent spacing, vibrant yet controlled palette, clean backdrop' }
      ]
    },
    {
      id: 'closeup',
      title: 'Close-Up',
      styles: [
        { key: 'detail', label: 'Detail', description: 'Feature Focus', icon: '🔍', prompt: 'macro detail close-up showcasing craftsmanship, shallow depth of field, crisp highlight on key feature' },
        { key: 'texture', label: 'Texture', description: 'Material Feel', icon: '🧵', prompt: 'extreme close-up emphasizing material texture, directional lighting to reveal surface qualities' },
        { key: 'swatches', label: 'Swatches', description: 'Texture Samples', icon: '🧩', prompt: 'product swatches or samples arranged in grid, clean background, color accuracy, top-down view' },
        { key: 'packaging', label: 'Packaging', description: 'Design Focus', icon: '🎁', prompt: 'packaging hero shot, clean studio backdrop, professional lighting, crisp shadows highlighting structure' }
      ]
    }
  ];

  const DEFAULT_UGC_STYLE_KEY = 'basic';
  const UGC_STYLE_LIBRARY = UGC_STYLE_GROUPS.reduce((acc, group) => {
    group.styles.forEach(style => {
      acc[style.key] = style;
    });
    return acc;
  }, {});

  let ugcProductImages = [];
  let ugcModelImage = null;
  let ugcItems = [];
  let ugcPollTimer = null;

  function normalizeUgcReference(entry) {
    if (!entry) return null;
    if (typeof entry === 'string') {
      return stripDataUrlPrefix(entry);
    }
    if (entry.dataUrl) {
      return stripDataUrlPrefix(entry.dataUrl);
    }
    if (entry.url) {
      return entry.url;
    }
    return null;
  }

  function buildUgcReferences() {
    const referenceLimit = 3;
    const productRefs = ugcProductImages
      .map(img => normalizeUgcReference(img))
      .filter(Boolean);

    const refs = productRefs.slice(0, referenceLimit);
    const modelRef = normalizeUgcReference(ugcModelImage);

    if (modelRef) {
      if (!productRefs.length) {
        return [modelRef];
      }
      if (refs.length < referenceLimit) {
        refs.push(modelRef);
      } else {
        refs[refs.length - 1] = modelRef;
      }
    }

    return refs;
  }

  function getUgcStyle(key = DEFAULT_UGC_STYLE_KEY) {
    return UGC_STYLE_LIBRARY[key] || UGC_STYLE_LIBRARY[DEFAULT_UGC_STYLE_KEY];
  }

  function buildUgcImagePrompt(basePrompt, styleKey, index) {
    const style = getUgcStyle(styleKey);
    const cleaned = (basePrompt || '').trim().replace(/\s+/g, ' ');
    const promptBase = cleaned || 'Product UGC photo shot';
    return `UGC Image #${index}: ${promptBase}. Style focus: ${style.label} — ${style.prompt}. Capture in square format with authentic creator energy.`;
  }

  function updateUgcStyleActiveState(activeKey) {
    if (!ugcStyleMenu) return;
    const options = ugcStyleMenu.querySelectorAll('.ugc-style-option');
    options.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.value === activeKey);
    });
  }

  function selectUgcStyle(key, closeMenu = true) {
    const style = getUgcStyle(key);
    if (ugcStyleValueInput) ugcStyleValueInput.value = style.key;
    if (ugcStyleLabelEl) ugcStyleLabelEl.textContent = style.label;
    if (ugcStyleDescEl) ugcStyleDescEl.textContent = style.description;
    if (ugcStyleIconEl) ugcStyleIconEl.textContent = style.icon || '✨';
    updateUgcStyleActiveState(style.key);
    if (closeMenu) toggleUgcStyleMenu(false);
  }

  function renderUgcStyleMenu() {
    if (!ugcStyleMenu) return;
    ugcStyleMenu.innerHTML = '';
    UGC_STYLE_GROUPS.forEach(group => {
      const groupEl = document.createElement('div');
      groupEl.className = 'ugc-style-group';

      const titleEl = document.createElement('div');
      titleEl.className = 'ugc-style-group-title';
      titleEl.textContent = group.title;
      groupEl.appendChild(titleEl);

      group.styles.forEach(style => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'ugc-style-option';
        option.dataset.value = style.key;
        option.innerHTML = `
          <span class="ugc-style-option-icon">${style.icon || '✨'}</span>
          <div class="ugc-style-option-meta">
            <div class="ugc-style-option-label">${style.label}</div>
            <div class="ugc-style-option-desc">${style.description}</div>
          </div>
        `;
        option.addEventListener('click', () => {
          selectUgcStyle(style.key);
        });
        groupEl.appendChild(option);
      });

      ugcStyleMenu.appendChild(groupEl);
    });

    const current = ugcStyleValueInput && ugcStyleValueInput.value ? ugcStyleValueInput.value : DEFAULT_UGC_STYLE_KEY;
    updateUgcStyleActiveState(current);
  }

  function toggleUgcStyleMenu(forceOpen) {
    if (!ugcStyleMenu || !ugcStyleTrigger) return;
    const isHidden = ugcStyleMenu.classList.contains('hidden');
    const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : isHidden;
    if (shouldOpen) {
      ugcStyleMenu.classList.remove('hidden');
      ugcStyleTrigger.classList.add('open');
    } else {
      ugcStyleMenu.classList.add('hidden');
      ugcStyleTrigger.classList.remove('open');
    }
  }

  function updateUgcProgressUI() {
    if (!ugcProgressEl) return;
    const summary = summarizeTaskProgress(ugcItems, {
      isStarted: item => !!(item && item.taskId),
      isCompleted: item => !!(item && finalStatus(item.status))
    });
    const label = summary.total
      ? `${summary.percent}% (${summary.completed}/${summary.total})`
      : '0%';
    setInlineProgressState(ugcProgressEl, ugcProgressFill, ugcProgressValue, summary.percent, summary.total > 0, label);
  }

  function renderUgcList() {
    ugcList.innerHTML = '';
    if (!ugcItems.length) {
      ugcEmpty.style.display = 'flex';
      ugcList.appendChild(ugcEmpty);
      updateUgcProgressUI();
      return;
    }
    ugcEmpty.style.display = 'none';

    ugcItems.forEach(item => {
      const row = document.createElement('div');
      row.className = 'ugc-row';
      row.dataset.index = item.index;

      const left = document.createElement('div');
      left.className = 'ugc-media-block';

      const imgCard = document.createElement('div');
      imgCard.className = 'ugc-media-card';

      if (item.imageUrl) {
        const img = document.createElement('img');
        img.src = item.imageUrl;
        img.alt = 'UGC Image ' + item.index;
        img.classList.add('clickable-media');
        img.addEventListener('click', () => openAssetPreview(item.imageUrl, 'image'));
        imgCard.innerHTML = '';
        imgCard.appendChild(img);
      } else {
        imgCard.innerHTML = '<div><div class=\"ugc-media-title\">Image #' + item.index +
          '</div><div class=\"ugc-media-status\">Generating... ' + (item.status || 'CREATED') + '</div></div>';
      }

      const videoCard = document.createElement('div');
      videoCard.className = 'ugc-video-card';
      if (item.videoUrl) {
        videoCard.innerHTML = '';
        const title = document.createElement('div');
        title.className = 'ugc-media-title';
        title.textContent = 'Video ready';

        const video = document.createElement('video');
        video.src = item.videoUrl;
        video.controls = true;
        video.loop = true;
        video.muted = true;
        video.playsInline = true;

        const actions = document.createElement('div');
        actions.className = 'ugc-video-actions';

        const previewVideoBtn = document.createElement('button');
        previewVideoBtn.type = 'button';
        previewVideoBtn.className = 'small secondary';
        previewVideoBtn.textContent = 'Preview Video';
        previewVideoBtn.addEventListener('click', () => openAssetPreview(item.videoUrl, 'video'));
        actions.appendChild(previewVideoBtn);

        const videoDownloadLink = document.createElement('a');
        videoDownloadLink.href = item.videoUrl;
        videoDownloadLink.target = '_blank';
        videoDownloadLink.download = '';
        videoDownloadLink.className = 'download-link';

        const videoDownloadBtn = document.createElement('button');
        videoDownloadBtn.type = 'button';
        videoDownloadBtn.className = 'small';
        videoDownloadBtn.textContent = 'Download';
        videoDownloadLink.appendChild(videoDownloadBtn);
        actions.appendChild(videoDownloadLink);

        const videoSaveBtn = document.createElement('button');
        videoSaveBtn.type = 'button';
        videoSaveBtn.className = 'small secondary';
        videoSaveBtn.textContent = 'Simpan Video';
        videoSaveBtn.addEventListener('click', () => saveUgcVideoToDrive(item, videoSaveBtn));
        actions.appendChild(videoSaveBtn);

        videoCard.appendChild(title);
        videoCard.appendChild(video);
        videoCard.appendChild(actions);
      } else if (item.videoJobId) {
        videoCard.innerHTML = '<div><div class=\"ugc-media-title\">Video generating...</div><div class=\"ugc-media-status\">Check status di Queue</div></div>';
      } else {
        videoCard.innerHTML = '<div><div class=\"ugc-media-title\">Video</div><div class=\"ugc-media-status\">No video yet</div></div>';
      }

      left.appendChild(imgCard);
      left.appendChild(videoCard);

      const right = document.createElement('div');
      right.className = 'ugc-right';

      const pLabel = document.createElement('div');
      pLabel.className = 'ugc-prompt-label';
      pLabel.textContent = 'UGC Prompt #' + item.index;
      const pText = document.createElement('textarea');
      pText.value = item.prompt || '';
      pText.rows = 3;
      pText.addEventListener('input', () => {
        item.prompt = pText.value;
      });

      const vLabel = document.createElement('div');
      vLabel.className = 'ugc-prompt-label';
      vLabel.textContent = 'Video Animation Prompt';
      const vText = document.createElement('textarea');
      vText.placeholder = 'contoh: model showing the product with a smile';
      vText.value = item.videoPrompt || '';
      vText.rows = 2;
      vText.addEventListener('input', () => {
        item.videoPrompt = vText.value;
      });

      const btnRow = document.createElement('div');
      btnRow.className = 'btn-group';

      const previewImgBtn = document.createElement('button');
      previewImgBtn.type = 'button';
      previewImgBtn.className = 'secondary small';
      previewImgBtn.textContent = 'Preview Image';
      previewImgBtn.disabled = !item.imageUrl;
      if (item.imageUrl) {
        previewImgBtn.addEventListener('click', () => openAssetPreview(item.imageUrl, 'image'));
      }

      const dlBtn = document.createElement('button');
      dlBtn.type = 'button';
      dlBtn.className = 'small';
      dlBtn.textContent = 'Download Image';
      dlBtn.disabled = !item.imageUrl;
      if (item.imageUrl) {
        dlBtn.addEventListener('click', () => {
          const a = document.createElement('a');
          a.href = item.imageUrl;
          a.download = '';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        });
      }

      const saveImgBtn = document.createElement('button');
      saveImgBtn.type = 'button';
      saveImgBtn.className = 'small secondary';
      saveImgBtn.textContent = 'Simpan ke Drive';
      saveImgBtn.disabled = !item.imageUrl;
      if (item.imageUrl) {
        saveImgBtn.addEventListener('click', () => saveUgcImageToDrive(item, saveImgBtn));
      }

      const vidBtn = document.createElement('button');
      vidBtn.type = 'button';
      vidBtn.className = 'small';
      vidBtn.textContent = 'Generate Video';
      vidBtn.disabled = !item.imageUrl;
      vidBtn.addEventListener('click', () => ugcGenerateVideo(item));

      btnRow.appendChild(previewImgBtn);
      btnRow.appendChild(dlBtn);
      btnRow.appendChild(saveImgBtn);
      btnRow.appendChild(vidBtn);

      right.appendChild(pLabel);
      right.appendChild(pText);
      right.appendChild(vLabel);
      right.appendChild(vText);
      right.appendChild(btnRow);

      row.appendChild(left);
      row.appendChild(right);

      ugcList.appendChild(row);
    });

    updateUgcProgressUI();
  }

  async function pollUgcOnce() {
    const pending = ugcItems.filter(s => s.taskId && !finalStatus(s.status));
    if (!pending.length) return;

    for (const item of pending) {
      try {
        const { status, generated } = await fetchStatus('gemini', item.taskId);
        if (status) item.status = status;
       if (generated && Array.isArray(generated) && generated.length && !item.imageUrl) {
  const remote = generated[0];

  // SIMPAN URL ASLI DARI FREEPIK (WAJIB)
  item.remoteUrl = remote;

  try {
    const local = await cacheUrl(remote);
    // imageUrl = file di server kamu (buat preview & download)
    item.imageUrl = local || remote;
  } catch {
    item.imageUrl = remote;
  }
}


      } catch (e) {
        item.status = 'ERROR';
      }
    }
    renderUgcList();

    const stillPending = ugcItems.some(s => s.taskId && !finalStatus(s.status));
    if (!stillPending && ugcPollTimer) {
      clearInterval(ugcPollTimer);
      ugcPollTimer = null;
    }
  }

  function startUgcPolling() {
    if (ugcPollTimer) clearInterval(ugcPollTimer);
    ugcPollTimer = setInterval(() => { pollUgcOnce(); }, 8000);
  }

  ugcProductDrop.addEventListener('click', () => ugcProductInput.click());
  ugcProductInput.addEventListener('change', e => {
    const files = Array.from(e.target.files || []);
    ugcProductImages = [];
    const max = 3;
    const used = files.slice(0, max);
    ugcProductPreview.innerHTML = '';
    used.forEach((file, idx) => {
      if (!file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = ev => {
        ugcProductImages.push({ id: idx + 1, dataUrl: ev.target.result });
        const img = document.createElement('img');
        img.src = ev.target.result;
        ugcProductPreview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  });

  ugcModelDrop.addEventListener('click', () => ugcModelInput.click());
  ugcModelInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('Model image harus gambar');
      return;
    }
    const reader = new FileReader();
    reader.onload = ev => {
      ugcModelImage = { dataUrl: ev.target.result };
      ugcModelPreview.src = ev.target.result;
      ugcModelPreview.style.display = 'block';
      ugcModelIdle.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });

  if (ugcStyleTrigger) {
    ugcStyleTrigger.addEventListener('click', () => {
      const shouldOpen = ugcStyleMenu ? ugcStyleMenu.classList.contains('hidden') : true;
      toggleUgcStyleMenu(shouldOpen);
    });
  }

  if (ugcStylePicker) {
    document.addEventListener('click', event => {
      if (!ugcStyleMenu || !ugcStyleTrigger) return;
      if (!ugcStylePicker.contains(event.target)) {
        toggleUgcStyleMenu(false);
      }
    });
  }

  renderUgcStyleMenu();
  const initialUgcStyle = ugcStyleValueInput && ugcStyleValueInput.value ? ugcStyleValueInput.value : DEFAULT_UGC_STYLE_KEY;
  selectUgcStyle(initialUgcStyle, false);

  async function ugcGenerate() {
    if (!featureAvailableForCurrentUser('ugc')) {
      showFeatureLockedMessage('ugc');
      return;
    }
    if (!ugcProductImages.length) {
      alert('Minimal upload 1 product image.');
      return;
    }
    if (!currentAccount) {
      alert('Data akun belum siap. Muat ulang halaman.');
      return;
    }
    const styleKey = (ugcStyleValueInput && ugcStyleValueInput.value) || DEFAULT_UGC_STYLE_KEY;
    const brief = ugcBriefInput.value.trim() || 'Product UGC photo shot';
    const requiredCoins = Math.max(1, UGC_IDEA_COUNT * COIN_COST_UGC);
    if (!ensureCoins(requiredCoins)) {
      alert('Koin kamu tidak cukup untuk generate UGC.');
      return;
    }

    ugcGenerateBtn.disabled = true;
    ugcItems = [];
    renderUgcList();

    const cfg = MODEL_CONFIG.gemini;
    const refs = buildUgcReferences();
    let successfulIdeas = 0;

    for (let i = 1; i <= UGC_IDEA_COUNT; i++) {
      const prompt = buildUgcImagePrompt(brief, styleKey, i);
      const item = {
        index: i,
        prompt,
        videoPrompt: '',
        status: 'CREATED',
        taskId: null,
        imageUrl: null,
        videoJobId: null,
        videoUrl: null
      };
      ugcItems.push(item);
      renderUgcList();

      const body = {
        prompt,
        num_images: 1,
        aspect_ratio: 'square_1_1'
      };
      if (refs.length) {
        body.reference_images = refs.slice();
      }
      let success = false;
      try {
        const data = await callFreepik(cfg, body, 'POST');
        if (data && data.data) {
          item.taskId = data.data.task_id || null;
          item.status = data.data.status || 'CREATED';
        }
        success = true;
      } catch (e) {
        console.error(e);
        item.status = 'ERROR';
      }
      if (success) {
        const normalized = String(item.status || '').toUpperCase();
        if (normalized !== 'ERROR' && normalized !== 'FAILED') {
          successfulIdeas += 1;
        }
      }
      renderUgcList();
    }

    if (ugcItems.some(s => s.taskId)) startUgcPolling();
    const coinsToSpend = successfulIdeas * COIN_COST_UGC;
    if (coinsToSpend > 0) {
      try {
        await spendCoins(coinsToSpend);
      } catch (err) {
        console.error('Gagal mengurangi koin UGC:', err);
        alert('Koin tidak dapat dikurangi: ' + err.message);
        try {
          await loadAccountState();
        } catch (loadErr) {
          console.warn('Tidak bisa me-refresh akun setelah gagal mengurangi koin:', loadErr);
        }
      }
    }
    ugcGenerateBtn.disabled = false;
  }

  ugcGenerateBtn.addEventListener('click', () => { ugcGenerate(); });

  async function ugcGenerateVideo(item) {
    if (!featureAvailableForCurrentUser('ugc')) {
      showFeatureLockedMessage('ugc');
      return;
    }
    // WAJIB: pakai URL asli dari Freepik, bukan path lokal
    if (!item.remoteUrl || !item.remoteUrl.startsWith('http')) {
      alert('URL gambar untuk video belum valid.\n' +
            'Pastikan UGC image sudah COMPLETED, lalu klik Generate Video lagi.');
      return;
    }

    const cfg = MODEL_CONFIG.wan720;
    const body = {
      prompt: item.videoPrompt || ('UGC video animation for image #' + item.index),
      image: item.remoteUrl,   // <-- PENTING
      duration: 5,
      aspect_ratio: 'auto'
    };

    try {
      const data = await callFreepik(cfg, body, 'POST');
      let taskId = null;
      let status = 'CREATED';
      let generated = null;

      if (data && data.data) {
        taskId = data.data.task_id || null;
        status  = data.data.status   || status;
        generated = data.data.generated || null;
      }

      const jobId = uuid();
      const job = {
        id: jobId,
        modelId: 'wan720',
        type: 'video',
        taskId,
        createdAt: nowIso(),
        updatedAt: nowIso(),
        status,
        generated: generated || [],
        extraUrl: null,
        prompt: body.prompt || null
      };
      jobs.unshift(job);
      saveJobs();
      renderJobs();
      if (taskId && !finalStatus(status)) {
        startJobProgress(job);
        startPolling(job);
      } else {
        finishJobProgress(job);
        if (job.generated && job.generated.length) {
          await ensureLocalFiles(job);
        }
        await syncJobToDrive(job);
      }

      item.videoJobId = jobId;
      renderUgcList();
    } catch (e) {
      console.error(e);
      alert('Gagal membuat video: ' + e.message);
    }
  }


const assetPreviewModal = document.getElementById('assetPreviewModal');
const assetPreviewBody = document.getElementById('assetPreviewBody');
const assetPreviewClose = document.getElementById('assetPreviewClose');
const assetPreviewDownload = document.getElementById('assetPreviewDownload');

function isVideoUrl(url = '') {
  return /\.(mp4|webm|mov|m4v)(\?|$)/i.test(url);
}

function openAssetPreview(url, type = 'image') {
  if (!assetPreviewModal || !assetPreviewBody || !url) return;
  assetPreviewBody.innerHTML = '';
  let el;
  if (type === 'video' || isVideoUrl(url)) {
    el = document.createElement('video');
    el.src = url;
    el.controls = true;
    el.autoplay = true;
    el.loop = true;
    el.playsInline = true;
  } else {
    el = document.createElement('img');
    el.src = url;
    el.alt = 'Preview';
    el.classList.add('clickable-media');
  }
  assetPreviewBody.appendChild(el);

  if (assetPreviewDownload) {
    assetPreviewDownload.href = url;
    assetPreviewDownload.style.display = 'inline-flex';
  }

  assetPreviewModal.classList.remove('hidden');
  document.body.classList.add('modal-open');
}

function closeAssetPreview() {
  if (!assetPreviewModal || !assetPreviewBody) return;
  const video = assetPreviewBody.querySelector('video');
  if (video) {
    video.pause();
  }
  assetPreviewBody.innerHTML = '';
  assetPreviewModal.classList.add('hidden');
  document.body.classList.remove('modal-open');
  if (assetPreviewDownload) {
    assetPreviewDownload.href = '#';
    assetPreviewDownload.style.display = 'none';
  }
}

if (assetPreviewClose) {
  assetPreviewClose.addEventListener('click', closeAssetPreview);
}

if (assetPreviewModal) {
  assetPreviewModal.addEventListener('click', event => {
    if (event.target === assetPreviewModal) {
      closeAssetPreview();
    }
  });
}

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    if (assetPreviewModal && !assetPreviewModal.classList.contains('hidden')) {
      closeAssetPreview();
    }
    toggleUgcStyleMenu(false);
  }
});


  // ===== INIT =====
  setFeature('imageGen');
  jobs.filter(j => !finalStatus(j.status)).forEach(job => startJobProgress(job));
  renderJobs();
  jobs.filter(j => finalStatus(j.status) && !j.driveSynced).forEach(job => {
    syncJobToDrive(job).catch(err => console.warn('Resync drive gagal:', err));
  });
  if (jobs.length) {
    const lastCompleted = jobs.find(j => finalStatus(j.status)) || jobs[0];
    activeJobId = lastCompleted.id;
    renderPreview(lastCompleted);
  }
  updateFilmProgressUI();
  updateUgcProgressUI();
  filmSceneCountLabel.textContent = filmSceneCount.value + ' scenes';
</script>
</body>
</html>
