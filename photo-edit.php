<?php
require_once __DIR__ . '/auth.php';

auth_session_start();

if (!auth_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$account = auth_current_account();
if ($account) {
    $account = auth_normalize_account($account);
}

$username = '';
$coinBalance = 0;
if ($account) {
    $username = trim((string)($account['name'] ?? ''));
    if ($username === '') {
        $username = trim((string)($account['username'] ?? ''));
    }
    if (isset($account['coins'])) {
        $coinBalance = (int)$account['coins'];
    }
}
if ($username === '') {
    $username = 'Pengguna';
}

$platform = auth_platform_public_view();
$flashFeature = $platform['generators']['flashPhotoEdit'] ?? null;
$isFlashEnabled = $flashFeature && !empty($flashFeature['enabled']);
if (!auth_is_admin() && !$isFlashEnabled) {
    http_response_code(403);
    $message = $platform['maintenance']['message'] ?? 'Fitur ini sedang tidak tersedia.';
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Fitur Dinonaktifkan</title>'
        . '<style>body{margin:0;font-family:system-ui;background:#020617;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;}'
        . '.card{max-width:420px;text-align:center;padding:32px;border-radius:20px;background:rgba(15,23,42,0.82);border:1px solid rgba(96,165,250,0.28);box-shadow:0 25px 60px rgba(15,23,42,0.25);}'
        . '.card h1{margin:0 0 12px;font-size:24px;} .card p{margin:0;font-size:14px;line-height:1.6;color:rgba(148,163,184,0.9);} .card a{display:inline-flex;margin-top:20px;padding:10px 18px;border-radius:999px;background:rgba(79,70,229,0.25);color:#c7d2fe;text-decoration:none;font-weight:600;}</style>'
        . '</head><body><div class="card"><h1>Flash Photo Edit Dimatikan</h1>'
        . '<p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>'
        . '<a href="index.php">← Kembali ke Dashboard</a>'
        . '</div></body></html>';
    exit;
}

?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flash 2.5 Multi Reference Studio</title>
    <meta name="description" content="Gabungkan 2-3 foto referensi dengan Gemini Flash 2.5 Mode 3 dan hasilkan empat pose berbeda dalam satu tema.">
    <link rel="icon" type="image/png" href="/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="photo-edit.css">
</head>
<body>
    <div class="photo-film-wrapper">
        <header class="photo-film-header">
            <div class="photo-film-heading">
                <div>
                    <span class="film-badge">Gemini Flash 2.5</span>
                    <h1>Flash Photo Edit</h1>
                    <p>Padukan 2-3 foto referensi kamu untuk membuat empat pose sinematik ala Filmmaker dengan kualitas asli dari Flash 2.5.</p>
                </div>
                <div class="photo-film-meta">
                    <div class="user-stack">
                        <div class="credit-pill" aria-live="polite">
                            <span class="credit-label">Saldo Kredit</span>
                            <span class="credit-value" id="creditBalance" data-balance="<?php echo (int)$coinBalance; ?>"><?php echo number_format((int)$coinBalance, 0, ',', '.'); ?></span>
                        </div>
                        <div class="user-chip" aria-label="Akun aktif">
                            <span class="avatar-circle" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($username, 0, 2))); ?></span>
                            <span class="user-meta">
                                <span class="user-label">Masuk sebagai</span>
                                <span class="user-name"><?php echo htmlspecialchars($username, ENT_QUOTES); ?></span>
                            </span>
                        </div>
                    </div>
                    <a href="index.php" class="btn-secondary">← Kembali ke Dashboard</a>
                </div>
            </div>
        </header>

        <main class="photo-film-main">
            <section class="card photo-film-board" aria-labelledby="resultTitle">
                <div class="header">
                    <div>
                        <div class="title" id="resultTitle">Hasil Generate</div>
                        <div class="subtitle">Empat pose akan muncul di sini, lengkap dengan status progres seperti panel Filmmaker.</div>
                    </div>
                </div>
                <div class="film-scenes-board">
                    <div id="emptyState" class="film-empty-state">
                        <div>
                            <div class="film-empty-icon">✨</div>
                            <div class="subtitle">Belum ada hasil</div>
                            <div class="muted" style="font-size:11px">Unggah 2-3 foto referensi dan klik "Generate" untuk memulai.</div>
                        </div>
                    </div>
                    <div id="resultGrid" class="film-scenes-container"></div>
                </div>
            </section>

            <section class="card-soft photo-film-settings" aria-labelledby="formTitle">
                <div class="header" style="margin-bottom:8px">
                    <div>
                        <div class="title" id="formTitle" style="font-size:16px">Setelan Photo Edit</div>
                        <div class="subtitle">Pilih treatment gaya, atur prompt, lalu kirim 4 permintaan Gemini Flash 2.5 Mode 3.</div>
                    </div>
                </div>

                <form id="editForm" class="film-settings-section" novalidate>
                    <div>
                        <div class="small-label">Model yang digunakan</div>
                        <div class="model-chip" role="text">Gemini Flash 2.5 · Mode 3 Multi-Reference</div>
                        <p class="muted">Model dikunci ke Flash 2.5 untuk menjaga konsistensi karakter dari foto referensi.</p>
                    </div>

                    <div>
                        <label for="themeSelect">Tema dasar</label>
                        <select id="themeSelect" aria-describedby="themeHint">
                            <option value="romantic">Romantic Fusion</option>
                            <option value="urban">Neo Urban Story</option>
                            <option value="tropical">Tropical Journey</option>
                            <option value="heritage">Heritage Elegance</option>
                        </select>
                        <p id="themeHint" class="muted"></p>
                    </div>

                    <div>
                        <label for="promptStyle">Prompt style / Tema penggabungan</label>
                        <textarea id="promptStyle" rows="4" placeholder="contoh: dreamy editorial portrait blending our references with golden hour glow"></textarea>
                        <p class="muted">Tambahkan detail suasana atau warna. <button type="button" class="link" id="resetPromptButton">Gunakan template tema</button></p>
                    </div>

                    <div>
                        <div class="small-label">Foto referensi</div>
                        <div id="dropzone" class="film-dropzone" tabindex="0">
                            <input id="referenceInput" type="file" accept="image/*" multiple hidden>
                            <div class="film-drop-inner">
                                <div style="margin-bottom:4px;">Tarik &amp; lepas atau <button type="button" class="link" id="browseButton">pilih dari perangkat</button></div>
                                <span>Gunakan 2–3 foto (JPG, PNG, atau WEBP) untuk menjaga konsistensi wajah.</span>
                            </div>
                        </div>
                        <div id="referencePreview" class="preview-grid"></div>
                    </div>

                    <div>
                        <button type="submit" class="btn-primary" id="generateButton">Generate 4 Pose Multi-Reference</button>
                        <div class="account-form-status" id="formStatus" role="status"></div>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <script>
    (function () {
        'use strict';

        const API_ENDPOINT = 'index.php?api=freepik';
        const CREATE_PATH = '/v1/ai/gemini-2-5-flash-image-preview';
        const STATUS_PATH = (taskId) => `/v1/ai/gemini-2-5-flash-image-preview/${taskId}`;
        const POLL_INTERVAL = 8000;
        const MIN_FILES = 2;
        const MAX_FILES = 3;
        const VIDEO_CREATE_PATH = '/v1/ai/image-to-video/seedance-pro-1080p';
        const VIDEO_STATUS_PATH = (taskId) => `/v1/ai/image-to-video/seedance-pro-1080p/${taskId}`;
        const VIDEO_POLL_INTERVAL = 9000;
        const VIDEO_DURATION_SECONDS = 5;
        const VIDEO_RATIO_DEFAULT = 'landscape_16_9';
        const VIDEO_RATIO_MAP = {
            portrait_3_4: 'portrait_9_16',
            portrait_4_5: 'portrait_9_16',
            portrait_9_16: 'portrait_9_16',
            portrait: 'portrait_9_16',
            landscape_3_2: 'landscape_16_9',
            landscape_16_9: 'landscape_16_9',
            landscape: 'landscape_16_9',
            square_1_1: 'square_1_1',
            square: 'square_1_1'
        };

        const numberFormatter = new Intl.NumberFormat('id-ID');

        const themeSelect = document.getElementById('themeSelect');
        const themeHint = document.getElementById('themeHint');
        const promptStyleInput = document.getElementById('promptStyle');
        const resetPromptButton = document.getElementById('resetPromptButton');
        const form = document.getElementById('editForm');
        const formStatus = document.getElementById('formStatus');
        const generateButton = document.getElementById('generateButton');
        const resultGrid = document.getElementById('resultGrid');
        const emptyState = document.getElementById('emptyState');
        const dropzone = document.getElementById('dropzone');
        const referenceInput = document.getElementById('referenceInput');
        const browseButton = document.getElementById('browseButton');
        const previewGrid = document.getElementById('referencePreview');
        const creditBalanceEl = document.getElementById('creditBalance');

        const themeOptions = {
            romantic: {
                label: 'Romantic Fusion',
                description: 'Palet rose gold dengan highlight lembut ala golden hour dan kilau editorial.',
                template: 'romantic editorial portrait blend, warm golden hour glow, pearlescent highlights, soft focus lens flare, cinematic depth, cohesive styling',
            },
            urban: {
                label: 'Neo Urban Story',
                description: 'Mood metropolis malam dengan kontras matte, refleksi neon cyan-magenta, dan nuansa futuristik.',
                template: 'neo urban fashion narrative, cinematic night city, teal and magenta glow, reflective surfaces, high contrast matte finish, dramatic shadows',
            },
            tropical: {
                label: 'Tropical Journey',
                description: 'Kombinasi warna tropis vibrant dengan kilau matahari dan ambience liburan energik.',
                template: 'tropical travel editorial, vivid aqua and lime palette, sun-kissed skin, breezy motion, cinematic resort backdrops, lush foliage details',
            },
            heritage: {
                label: 'Heritage Elegance',
                description: 'Sentuhan tradisional hangat dengan tekstur kaya dan detail busana elegan.',
                template: 'heritage ceremonial portrait, warm amber lighting, intricate textile details, soft cinematic haze, dignified poses, timeless storytelling',
            }
        };

        const poseVariants = [
            {
                key: 'closeUpGlow',
                badge: 'Pose 1',
                title: 'Pose 1 · Siluet Harmonis',
                shot: 'Close-up portrait angle dengan bahu sedikit miring, tatapan lembut ke kamera, tangan menyentuh wajah secara elegan.',
                aspectRatio: 'portrait_3_4',
                description(theme, prompt) {
                    const base = 'Close-up harmonis menonjolkan ekspresi utama dengan highlight lembut.';
                    return prompt ? `${base} Tema "${prompt}" ditanamkan pada warna dan pencahayaan wajah.` : `${base} ${theme?.description || ''}`.trim();
                }
            },
            {
                key: 'dynamicMotion',
                badge: 'Pose 2',
                title: 'Pose 2 · Dynamic Motion',
                shot: 'Full body fashion stride dengan motion blur halus, kain bergerak dramatis, ekspresi percaya diri ke samping.',
                aspectRatio: 'portrait_3_4',
                description(theme, prompt) {
                    const base = 'Gerakan dramatis dengan komposisi diagonal dan aksen cahaya dinamis.';
                    return prompt ? `${base} Tema "${prompt}" diaplikasikan pada wardrobe dan motion blur.` : `${base} ${theme?.description || ''}`.trim();
                }
            },
            {
                key: 'wideStory',
                badge: 'Pose 3',
                title: 'Pose 3 · Wide Storytelling',
                shot: 'Wide storytelling frame yang memperlihatkan karakter berinteraksi dengan lingkungan tematik.',
                aspectRatio: 'landscape_3_2',
                description(theme, prompt) {
                    const base = 'Storytelling shot menonjolkan kostum penuh dan suasana latar.';
                    return prompt ? `${base} Tema "${prompt}" diterapkan pada warna environment.` : `${base} ${theme?.description || ''}`.trim();
                }
            },
            {
                key: 'detailCinematic',
                badge: 'Pose 4',
                title: 'Pose 4 · Cinematic Detail',
                shot: 'Medium close-up fokus pada aksesori dan tekstur bahan dengan pencahayaan dramatis.',
                aspectRatio: 'square_1_1',
                description(theme, prompt) {
                    const base = 'Detail shot memperlihatkan aksesori dan tekstur bahan dengan dramatis.';
                    return prompt ? `${base} Tema "${prompt}" ditekankan pada highlight detail.` : `${base} ${theme?.description || ''}`.trim();
                }
            }
        ];

        let creditBalance = creditBalanceEl ? Number(creditBalanceEl.dataset.balance || 0) : 0;
        if (!Number.isFinite(creditBalance)) {
            creditBalance = 0;
        }

        const COIN_COST_PER_SUCCESS = 1;
        const REQUIRED_COINS = Math.max(1, poseVariants.length * COIN_COST_PER_SUCCESS);
        const chargedVariants = new Set();
        let coinChargeInFlight = false;

        function createVideoState() {
            return {
                taskId: null,
                status: null,
                videoUrl: null,
                error: null,
                prompt: '',
                timerId: null
            };
        }

        function ensureVideoState(task) {
            if (!task) return createVideoState();
            if (!task.videoState) {
                task.videoState = createVideoState();
            }
            return task.videoState;
        }

        function resolveVideoAspect(task) {
            if (!task || !task.variant) {
                return VIDEO_RATIO_DEFAULT;
            }
            const key = String(task.variant.aspectRatio || '').toLowerCase();
            return VIDEO_RATIO_MAP[key] || VIDEO_RATIO_DEFAULT;
        }

        function updateCreditDisplay() {
            if (!creditBalanceEl) {
                return;
            }
            const safeValue = Math.max(0, Math.round(Number.isFinite(creditBalance) ? creditBalance : 0));
            creditBalanceEl.dataset.balance = String(safeValue);
            creditBalanceEl.textContent = numberFormatter.format(safeValue);
        }

        async function refreshAccountCoins() {
            if (!creditBalanceEl) {
                return;
            }
            try {
                const res = await fetch('index.php?api=account', { credentials: 'same-origin' });
                if (!res.ok) {
                    return;
                }
                const payload = await res.json();
                if (!payload || !payload.ok || !payload.data) {
                    return;
                }
                const coins = Number(payload.data.coins);
                if (Number.isFinite(coins)) {
                    creditBalance = coins;
                    updateCreditDisplay();
                }
            } catch (error) {
                console.warn('Tidak bisa memuat saldo koin:', error);
            }
        }

        function ensureCoins(amount) {
            if (!Number.isFinite(amount) || amount <= 0) {
                return true;
            }
            return Number.isFinite(creditBalance) && creditBalance >= amount;
        }

        async function spendCoins(amount) {
            if (!amount || amount <= 0) {
                return;
            }

            const res = await fetch('index.php?api=account-coins', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ amount })
            });

            let payload;
            try {
                payload = await res.json();
            } catch (error) {
                throw new Error('Respons saldo tidak valid.');
            }

            if (!res.ok || !payload || !payload.ok) {
                const message = payload?.error
                    ? (typeof payload.error === 'string' ? payload.error : JSON.stringify(payload.error))
                    : `HTTP ${res.status}`;
                throw new Error(message);
            }

            if (payload.data && typeof payload.data.coins !== 'undefined') {
                const next = Number(payload.data.coins);
                if (Number.isFinite(next)) {
                    creditBalance = next;
                } else {
                    creditBalance = Math.max(0, creditBalance - amount);
                }
            } else {
                creditBalance = Math.max(0, creditBalance - amount);
            }

            updateCreditDisplay();
        }

        async function chargeCoinsForCompletedTasks() {
            if (!tasks.length || coinChargeInFlight) {
                return;
            }

            const chargeable = tasks.filter((task) => {
                if (!task) return false;
                if (typeof task.index !== 'number') return false;
                if (chargedVariants.has(task.index)) return false;
                if (normalizeStatus(task.status) !== 'COMPLETED') return false;
                return Boolean(task.imageUrl);
            });

            if (!chargeable.length) {
                return;
            }

            const total = chargeable.length * COIN_COST_PER_SUCCESS;
            coinChargeInFlight = true;
            try {
                await spendCoins(total);
                chargeable.forEach((task) => {
                    chargedVariants.add(task.index);
                });
            } catch (error) {
                console.error('Gagal mengurangi koin:', error);
                updateFormStatus(`Saldo tidak dapat dikurangi: ${error.message || error}`, 'error');
            } finally {
                coinChargeInFlight = false;
            }
        }

        function buildVideoPrompt(task) {
            const theme = themeOptions[task.themeKey] || themeOptions.romantic;
            const motion = (task.variant && task.variant.shot) ? task.variant.shot : 'Cinematic portrait motion with expressive movement.';
            const custom = (task.customPrompt || '').trim();
            const segments = [
                motion,
                `tema ${theme.label}`,
                theme.description,
                custom ? `detail tambahan: ${custom}` : '',
                'smooth cinematic camera move, vivid lighting, 1080p high fidelity, natural motion'
            ].filter(Boolean);
            const promptText = segments.join(' | ').trim();
            return promptText || 'cinematic portrait motion with expressive movement';
        }

        function triggerDownload(url, filename) {
            if (!url) return;
            const link = document.createElement('a');
            link.href = url;
            if (filename) {
                link.download = filename;
            }
            link.target = '_blank';
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        let selectedFiles = [];
        let promptDirty = false;
        let tasks = [];
        let pollTimer = null;

        function normalizeStatus(status) {
            if (!status) return 'PENDING';
            return String(status).toUpperCase();
        }

        function statusLabel(status) {
            const value = normalizeStatus(status);
            switch (value) {
                case 'COMPLETED':
                    return 'SELESAI';
                case 'FAILED':
                case 'ERROR':
                    return 'GAGAL';
                case 'PROCESSING':
                case 'RUNNING':
                case 'IN_PROGRESS':
                    return 'PROSES';
                case 'CREATED':
                case 'PENDING':
                case 'IN_QUEUE':
                    return 'MENUNGGU';
                default:
                    return 'MENUNGGU';
            }
        }

        function statusClass(status) {
            const value = normalizeStatus(status);
            if (value === 'COMPLETED') {
                return 'status-chip status-chip--success';
            }
            if (value === 'FAILED' || value === 'ERROR') {
                return 'status-chip status-chip--error';
            }
            if (value === 'PROCESSING' || value === 'RUNNING' || value === 'IN_PROGRESS') {
                return 'status-chip status-chip--progress';
            }
            return 'status-chip status-chip--pending';
        }

        function finalStatus(status) {
            const value = normalizeStatus(status);
            return value === 'COMPLETED' || value === 'FAILED' || value === 'ERROR';
        }

        function stopVideoPollingForTask(task) {
            if (!task || !task.videoState || !task.videoState.timerId) {
                return;
            }
            clearInterval(task.videoState.timerId);
            task.videoState.timerId = null;
        }

        function stopAllVideoPolling() {
            tasks.forEach((task) => stopVideoPollingForTask(task));
        }

        function updateFormStatus(message, type = 'info') {
            if (!formStatus) return;
            formStatus.textContent = message || '';
            formStatus.dataset.type = type;
        }

        function setLoadingState(isLoading) {
            if (!generateButton) return;
            if (isLoading) {
                generateButton.classList.add('loading');
                generateButton.disabled = true;
            } else {
                generateButton.classList.remove('loading');
                generateButton.disabled = false;
            }
        }

        function showEmptyState(show) {
            if (!emptyState) return;
            emptyState.style.display = show ? '' : 'none';
        }

        function renderThemeHint(themeKey) {
            const option = themeOptions[themeKey];
            if (option) {
                themeHint.textContent = option.description;
            } else {
                themeHint.textContent = '';
            }
        }

        function ensurePromptTemplate(themeKey, force = false) {
            const option = themeOptions[themeKey];
            if (!option) {
                return;
            }
            if (force || !promptDirty || !promptStyleInput.value.trim()) {
                promptStyleInput.value = option.template;
                promptDirty = false;
            }
        }

        function setPromptDirty() {
            promptDirty = true;
        }

        function isImageFile(file) {
            if (!file) return false;
            if (file.type && file.type.startsWith('image/')) {
                return true;
            }
            const name = (file.name || '').toLowerCase();
            return /\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i.test(name);
        }

        function normalizeFileList(fileList) {
            const files = [];
            const seen = new Set();
            for (let i = 0; i < fileList.length; i += 1) {
                const file = fileList[i];
                if (!file || !file.name) continue;
                const key = `${file.name}-${file.size}-${file.lastModified}`;
                if (seen.has(key)) continue;
                seen.add(key);
                files.push(file);
            }
            return files;
        }

        function renderPreview() {
            previewGrid.innerHTML = '';
            if (!selectedFiles.length) {
                previewGrid.style.display = 'none';
                dropzone.classList.remove('has-files');
                return;
            }

            const fragment = document.createDocumentFragment();
            selectedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'preview-item';
                const img = document.createElement('img');
                img.alt = `Referensi ${index + 1}`;
                img.src = URL.createObjectURL(file);
                img.onload = () => URL.revokeObjectURL(img.src);

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'preview-remove';
                removeButton.textContent = '×';
                removeButton.addEventListener('click', () => {
                    selectedFiles.splice(index, 1);
                    renderPreview();
                    if (!selectedFiles.length) {
                        updateFormStatus('Unggah minimal dua foto referensi terlebih dahulu.', 'info');
                    } else if (selectedFiles.length < MIN_FILES) {
                        updateFormStatus(`Tambahkan minimal ${MIN_FILES} foto referensi.`, 'info');
                    } else {
                        updateFormStatus('', 'info');
                    }
                });

                item.appendChild(img);
                item.appendChild(removeButton);
                fragment.appendChild(item);
            });

            previewGrid.appendChild(fragment);
            previewGrid.style.display = 'grid';
            dropzone.classList.add('has-files');
        }

        function handleFiles(fileList) {
            const normalized = normalizeFileList(fileList);
            if (!normalized.length) return;

            const imageFiles = normalized.filter((file) => isImageFile(file));
            if (!imageFiles.length) {
                updateFormStatus('Hanya file gambar (JPG, PNG, WEBP, GIF, BMP, HEIC, HEIF) yang dapat digunakan.', 'error');
                return;
            }

            const messages = [];
            if (imageFiles.length < normalized.length) {
                messages.push('File non-gambar diabaikan.');
            }
            if (imageFiles.length > MAX_FILES) {
                messages.push(`Hanya ${MAX_FILES} foto pertama yang digunakan untuk blending.`);
            }

            selectedFiles = imageFiles.slice(0, MAX_FILES);
            renderPreview();

            if (!selectedFiles.length) {
                messages.push('Unggah minimal dua foto referensi terlebih dahulu.');
            } else if (selectedFiles.length < MIN_FILES) {
                messages.push(`Tambahkan minimal ${MIN_FILES} foto referensi.`);
            }

            if (messages.length) {
                updateFormStatus(messages.join(' '), 'info');
            } else {
                updateFormStatus('', 'info');
            }
        }

        function validateFiles(files) {
            if (!files.length) {
                updateFormStatus('Unggah minimal dua foto referensi terlebih dahulu.', 'error');
                return false;
            }
            if (files.length < MIN_FILES) {
                updateFormStatus(`Tambahkan minimal ${MIN_FILES} foto referensi.`, 'error');
                return false;
            }
            return true;
        }

        function readFileAsDataURL(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => {
                    if (typeof reader.result === 'string') {
                        resolve(reader.result);
                    } else {
                        reject(new Error('Gagal membaca file referensi.'));
                    }
                };
                reader.onerror = () => reject(new Error('Gagal membaca file referensi.'));
                reader.readAsDataURL(file);
            });
        }

        async function convertFilesToBase64(files) {
            const dataUrls = await Promise.all(files.map((file) => readFileAsDataURL(file)));
            return dataUrls
                .map((url) => {
                    const parts = url.split(',');
                    return parts.length > 1 ? parts[1] : '';
                })
                .filter((value) => value && value.trim() !== '');
        }

        function buildVariantPrompt(theme, variant, customPrompt) {
            const accent = customPrompt && customPrompt.trim() !== '' ? customPrompt.trim() : theme.template;
            const segments = [
                '[MultiReference Blend] Gabungkan semua foto referensi, pertahankan wajah, rambut, dan kostum yang konsisten.',
                `[Theme Treatment] ${theme.label}. ${theme.description}`,
                `[Pose Direction] ${variant.shot}`,
                `[Styling Motif] ${accent}`,
                '[Camera & Lighting] cinematic lighting, editorial photography, high dynamic range, rich texture, 8k detail.',
                '[Quality] sharp focus, clean background, no watermark, no text overlay.'
            ];
            return segments.join(' ');
        }

        function prepareVariantRequest(themeKey, variant, customPrompt, referencesBase64) {
            const theme = themeOptions[themeKey] || themeOptions.romantic;
            const prompt = buildVariantPrompt(theme, variant, customPrompt);
            const body = {
                prompt,
                num_images: 1,
                reference_images: referencesBase64,
                aspect_ratio: variant.aspectRatio || 'portrait_3_4'
            };
            return { prompt, theme, body };
        }

        async function callFreepik({ path, method = 'POST', body, contentType = 'json' } = {}) {
            if (!path) {
                throw new Error('Endpoint Freepik tidak valid.');
            }

            const payload = { path, method, contentType };
            if (method !== 'GET' && typeof body !== 'undefined') {
                payload.body = body;
            }

            const res = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const text = await res.text();
            let json;
            try {
                json = JSON.parse(text);
            } catch (error) {
                throw new Error('Respon dari server tidak valid.');
            }

            if (!json.ok) {
                const message = (json.data && json.data.message) || json.error || `HTTP ${json.status || res.status}`;
                throw new Error(message);
            }

            return json.data;
        }

        async function createGeminiTask(body) {
            const data = await callFreepik({ path: CREATE_PATH, method: 'POST', body });
            const response = data?.data || {};
            return {
                taskId: response.task_id || null,
                status: response.status || 'CREATED',
                generated: Array.isArray(response.generated) ? response.generated : []
            };
        }

        async function fetchGeminiStatus(taskId) {
            if (!taskId) {
                throw new Error('Task ID tidak ditemukan.');
            }
            const data = await callFreepik({ path: STATUS_PATH(taskId), method: 'GET' });
            const response = data?.data || {};
            const generated = Array.isArray(response.generated) ? response.generated : [];
            return {
                status: response.status || null,
                generated
            };
        }

        async function pollVideoOnce(task) {
            const videoState = ensureVideoState(task);
            if (!videoState.taskId || finalStatus(videoState.status)) {
                stopVideoPollingForTask(task);
                return;
            }

            try {
                const data = await callFreepik({ path: VIDEO_STATUS_PATH(videoState.taskId), method: 'GET' });
                const response = data?.data || {};
                if (response.status) {
                    videoState.status = response.status;
                }
                const generated = Array.isArray(response.generated) ? response.generated : [];
                if (generated.length) {
                    videoState.videoUrl = generated[0];
                }
            } catch (error) {
                videoState.status = 'ERROR';
                videoState.error = error.message || 'Gagal mengambil status video.';
            }

            renderResults();

            if (finalStatus(videoState.status)) {
                stopVideoPollingForTask(task);
                if (normalizeStatus(videoState.status) === 'COMPLETED' && !videoState.videoUrl && !videoState.error) {
                    videoState.error = 'Video selesai tetapi URL tidak ditemukan.';
                } else if (normalizeStatus(videoState.status) !== 'COMPLETED' && !videoState.error) {
                    videoState.error = 'Video gagal diproses. Coba generate ulang.';
                }
            }
        }

        function startVideoPollingForTask(task) {
            const videoState = ensureVideoState(task);
            stopVideoPollingForTask(task);
            pollVideoOnce(task);
            videoState.timerId = setInterval(() => pollVideoOnce(task), VIDEO_POLL_INTERVAL);
        }

        async function handleGenerateVideo(task) {
            if (!task || !task.imageUrl) {
                updateFormStatus('Gambar pose belum tersedia untuk membuat video.', 'error');
                return;
            }

            const videoState = ensureVideoState(task);
            if (videoState.status && !finalStatus(videoState.status)) {
                return;
            }

            stopVideoPollingForTask(task);

            const prompt = buildVideoPrompt(task) || 'cinematic portrait motion';
            videoState.prompt = prompt;
            videoState.taskId = null;
            videoState.videoUrl = null;
            videoState.error = null;
            videoState.status = 'PROCESSING';
            renderResults();

            try {
                const body = {
                    prompt,
                    motion_prompt: prompt,
                    image: task.imageUrl,
                    duration: VIDEO_DURATION_SECONDS,
                    aspect_ratio: resolveVideoAspect(task)
                };
                const data = await callFreepik({ path: VIDEO_CREATE_PATH, method: 'POST', body });
                const response = data?.data || {};

                videoState.taskId = response.task_id || null;
                videoState.status = response.status || 'CREATED';

                const generated = Array.isArray(response.generated) ? response.generated : [];
                if (generated.length) {
                    videoState.videoUrl = generated[0];
                }

                renderResults();

                if (!videoState.taskId) {
                    videoState.status = 'ERROR';
                    videoState.error = 'Server tidak mengembalikan task ID video.';
                    renderResults();
                    return;
                }

                if (finalStatus(videoState.status)) {
                    if (normalizeStatus(videoState.status) !== 'COMPLETED' && !videoState.error) {
                        videoState.error = 'Video gagal diproses. Coba lagi.';
                    }
                    renderResults();
                    return;
                }

                startVideoPollingForTask(task);
            } catch (error) {
                videoState.status = 'ERROR';
                videoState.error = error.message || 'Gagal mengirim permintaan video.';
                renderResults();
            }
        }

        function renderResults() {
            resultGrid.innerHTML = '';
            if (!tasks.length) {
                showEmptyState(true);
                return;
            }

            showEmptyState(false);
            const fragment = document.createDocumentFragment();

            tasks.forEach((task) => {
                const card = document.createElement('article');
                card.className = 'film-scene-card pose-card';

                const media = document.createElement('div');
                media.className = 'pose-media';

                const videoState = ensureVideoState(task);

                if (task.imageUrl) {
                    const img = document.createElement('img');
                    img.className = 'pose-image';
                    img.src = task.imageUrl;
                    img.alt = task.variant.title;
                    media.appendChild(img);
                } else {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'pose-placeholder';
                    const label = document.createElement('span');
                    label.textContent = finalStatus(task.status) ? 'Belum ada gambar' : 'Menunggu hasil…';
                    placeholder.appendChild(label);
                    if (!finalStatus(task.status)) {
                        const spinner = document.createElement('span');
                        spinner.className = 'pose-spinner';
                        placeholder.appendChild(spinner);
                    }
                    media.appendChild(placeholder);
                }

                const overlay = document.createElement('div');
                overlay.className = 'pose-overlay';

                const infoRow = document.createElement('div');
                infoRow.className = 'pose-overlay-info';

                const titleLabel = document.createElement('span');
                titleLabel.className = 'pose-overlay-label';
                titleLabel.textContent = task.variant.title;
                infoRow.appendChild(titleLabel);

                const statusChipEl = document.createElement('span');
                statusChipEl.className = statusClass(task.status);
                statusChipEl.textContent = statusLabel(task.status);
                infoRow.appendChild(statusChipEl);

                overlay.appendChild(infoRow);

                const actionsRow = document.createElement('div');
                actionsRow.className = 'pose-overlay-actions';

                if (task.imageUrl) {
                    const previewLink = document.createElement('a');
                    previewLink.href = task.imageUrl;
                    previewLink.target = '_blank';
                    previewLink.rel = 'noopener';
                    previewLink.className = 'pose-mini-btn';
                    previewLink.textContent = 'Preview';
                    actionsRow.appendChild(previewLink);

                    const downloadLink = document.createElement('a');
                    downloadLink.href = task.imageUrl;
                    const baseKey = task.variant.key || `pose-${(task.index ?? 0) + 1}`;
                    const token = task.downloadToken || Date.now();
                    if (!task.downloadToken) {
                        task.downloadToken = token;
                    }
                    downloadLink.download = `${baseKey}-${token}.png`;
                    downloadLink.className = 'pose-mini-btn';
                    downloadLink.textContent = 'Download';
                    actionsRow.appendChild(downloadLink);

                    const videoButton = document.createElement('button');
                    videoButton.type = 'button';
                    videoButton.className = 'pose-mini-btn primary';

                    const videoStatusValue = normalizeStatus(videoState.status);
                    let videoLabel = 'Video';
                    let videoDisabled = false;
                    let videoHandler = () => handleGenerateVideo(task);

                    if (videoState.videoUrl && videoStatusValue === 'COMPLETED') {
                        videoLabel = 'Unduh Video';
                        videoHandler = () => triggerDownload(videoState.videoUrl, `${baseKey}-seedance-1080.mp4`);
                    } else if (videoState.status && !finalStatus(videoState.status)) {
                        videoLabel = 'Memproses…';
                        videoDisabled = true;
                        videoHandler = null;
                    } else if (videoState.status && finalStatus(videoState.status) && videoStatusValue !== 'COMPLETED') {
                        videoLabel = 'Ulangi Video';
                    }

                    videoButton.textContent = videoLabel;
                    if (videoDisabled) {
                        videoButton.disabled = true;
                    }
                    if (videoHandler) {
                        videoButton.addEventListener('click', videoHandler);
                    }

                    actionsRow.appendChild(videoButton);
                } else {
                    const hint = document.createElement('span');
                    hint.className = 'pose-overlay-hint';
                    hint.textContent = finalStatus(task.status) ? 'Tidak ada gambar' : 'Menunggu hasil…';
                    actionsRow.appendChild(hint);
                }

                overlay.appendChild(actionsRow);
                media.appendChild(overlay);
                card.appendChild(media);

                if (task.error) {
                    const errorBlock = document.createElement('div');
                    errorBlock.className = 'pose-error';
                    errorBlock.textContent = task.error;
                    card.appendChild(errorBlock);
                }

                if (videoState.error) {
                    const videoError = document.createElement('div');
                    videoError.className = 'pose-video-error';
                    videoError.textContent = videoState.error;
                    card.appendChild(videoError);
                }

                fragment.appendChild(card);
            });

            resultGrid.appendChild(fragment);
        }

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        async function pollOnce() {
            const active = tasks.filter((task) => task.taskId && !finalStatus(task.status));

            if (active.length) {
                for (const task of active) {
                    try {
                        const { status, generated } = await fetchGeminiStatus(task.taskId);
                        if (status) {
                            task.status = status;
                        }
                        if (generated && generated.length) {
                            task.imageUrl = generated[0];
                            if (!task.downloadToken) {
                                task.downloadToken = Date.now();
                            }
                        }
                    } catch (error) {
                        task.status = 'ERROR';
                        task.error = error.message || 'Gagal mengambil status generasi.';
                    }
                }
            }

            await chargeCoinsForCompletedTasks();
            renderResults();

            const completed = tasks.filter((task) => finalStatus(task.status)).length;
            const successCount = tasks.filter((task) => normalizeStatus(task.status) === 'COMPLETED' && task.imageUrl).length;

            if (completed === tasks.length) {
                stopPolling();
                if (successCount === tasks.length) {
                    updateFormStatus('Selesai! Semua pose berhasil dibuat.', 'success');
                } else if (successCount > 0) {
                    updateFormStatus(`${successCount} pose berhasil. Periksa pose lain yang gagal.`, 'error');
                } else {
                    updateFormStatus('Semua pose gagal diproses. Coba lagi.', 'error');
                }
                return;
            }

            if (completed > 0 && completed < tasks.length) {
                updateFormStatus(`Progress: ${completed}/${tasks.length} pose selesai.`, 'info');
            }
        }

        function startPolling() {
            stopPolling();
            pollOnce();
            pollTimer = setInterval(pollOnce, POLL_INTERVAL);
        }

        async function submitForm(event) {
            event.preventDefault();

            if (!validateFiles(selectedFiles)) {
                return;
            }

            setLoadingState(true);
            updateFormStatus('Memeriksa saldo koin…', 'info');
            await refreshAccountCoins();
            if (!ensureCoins(REQUIRED_COINS)) {
                setLoadingState(false);
                updateFormStatus(`Saldo koin tidak cukup. Minimal ${REQUIRED_COINS} koin dibutuhkan.`, 'error');
                return;
            }

            updateFormStatus('Mengunggah referensi dan menyiapkan permintaan…', 'info');
            stopPolling();
            stopAllVideoPolling();
            tasks = [];
            chargedVariants.clear();
            coinChargeInFlight = false;
            renderResults();

            let base64Images;
            try {
                base64Images = await convertFilesToBase64(selectedFiles);
            } catch (error) {
                setLoadingState(false);
                updateFormStatus(error.message || 'Gagal memproses file referensi.', 'error');
                return;
            }

            if (base64Images.length < MIN_FILES) {
                setLoadingState(false);
                updateFormStatus(`Tambahkan minimal ${MIN_FILES} foto referensi.`, 'error');
                return;
            }

            const themeKey = themeSelect.value || 'romantic';
            const customPrompt = promptStyleInput.value.trim();

            tasks = poseVariants.map((variant, index) => ({
                index,
                variant,
                themeKey,
                customPrompt,
                prompt: '',
                taskId: null,
                status: 'PENDING',
                imageUrl: null,
                error: null,
                downloadToken: null,
                videoState: createVideoState()
            }));
            renderResults();

            let successCount = 0;
            let failureCount = 0;

            for (const task of tasks) {
                try {
                    const { prompt, body } = prepareVariantRequest(themeKey, task.variant, customPrompt, base64Images);
                    task.prompt = prompt;
                    const { taskId, status, generated } = await createGeminiTask(body);
                    task.taskId = taskId;
                    task.status = status || 'CREATED';
                    if (generated && generated.length) {
                        task.imageUrl = generated[0];
                        if (!task.downloadToken) {
                            task.downloadToken = Date.now();
                        }
                    }
                    if (!taskId) {
                        task.error = 'Server tidak mengembalikan task ID.';
                        task.status = 'ERROR';
                        failureCount += 1;
                    } else {
                        successCount += 1;
                    }
                } catch (error) {
                    task.status = 'ERROR';
                    task.error = error.message || 'Gagal mengirim permintaan ke Flash 2.5.';
                    failureCount += 1;
                }
                renderResults();
            }

            await chargeCoinsForCompletedTasks();
            setLoadingState(false);

            if (successCount > 0) {
                const message = failureCount > 0
                    ? `${successCount} pose dikirim. ${failureCount} permintaan gagal diajukan. Menunggu hasil…`
                    : 'Semua pose berhasil dikirim. Menunggu hasil dari Flash 2.5…';
                updateFormStatus(message, 'info');
                startPolling();
            } else {
                updateFormStatus('Tidak ada pose yang berhasil dikirim. Periksa koneksi dan coba lagi.', 'error');
            }
        }

        dropzone.addEventListener('click', () => {
            referenceInput.click();
        });

        dropzone.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                referenceInput.click();
            }
        });

        dropzone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('is-dragover');
        });

        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');
            if (event.dataTransfer?.files?.length) {
                handleFiles(event.dataTransfer.files);
            }
        });

        browseButton.addEventListener('click', () => {
            referenceInput.click();
        });

        referenceInput.addEventListener('change', () => {
            if (referenceInput.files?.length) {
                handleFiles(referenceInput.files);
            }
        });

        themeSelect.addEventListener('change', () => {
            const themeKey = themeSelect.value;
            renderThemeHint(themeKey);
            ensurePromptTemplate(themeKey);
        });

        promptStyleInput.addEventListener('input', setPromptDirty);
        resetPromptButton.addEventListener('click', () => {
            ensurePromptTemplate(themeSelect.value, true);
        });

        form.addEventListener('submit', submitForm);

        updateCreditDisplay();
        refreshAccountCoins();
        renderThemeHint(themeSelect.value);
        ensurePromptTemplate(themeSelect.value, true);
        renderPreview();
        showEmptyState(true);
    })();
    </script>
</body>
</html>
