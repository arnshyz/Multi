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

$accountPayload = $account ? auth_account_public_payload($account) : null;
$username = '';
if ($account) {
    $username = trim((string)($account['name'] ?? ''));
    if ($username === '') {
        $username = trim((string)($account['username'] ?? ''));
    }
}
if ($username === '') {
    $username = 'Pengguna';
}

$coinBalance = $accountPayload['coins'] ?? 0;
$coinBalanceFormatted = number_format((int)$coinBalance, 0, ',', '.');

$themeOptions = [
    'romantic' => [
        'label' => 'Romantic Fusion',
        'description' => 'Palet rose gold dengan highlight lembut ala golden hour dan kilau editorial.',
        'template' => 'Gabungkan kedua foto menjadi potret editorial pernikahan yang romantis. Pastikan ada cahaya keemasan hangat dari golden hour, dengan highlight pearlescent yang lembut dan flare lensa soft focus yang artistik. Ciptakan kedalaman sinematik pada komposisi, dan pastikan gaya keseluruhan menyatu dengan sempurna, seolah-olah ini adalah satu sesi pemotretan yang direncanakan dengan indah.'
    ],
    'urban' => [
        'label' => 'Neo Urban Story',
        'description' => 'Mood metropolis malam dengan kontras matte, refleksi neon cyan-magenta, dan nuansa futuristik.',
        'template' => 'Gabungkan kedua foto ini dalam sebuah narasi fashion neo-urban. Latar belakangnya adalah kota di malam hari yang sinematik, didominasi oleh cahaya neon teal dan magenta yang memantul di permukaan. Berikan sentuhan akhir matte dengan kontras tinggi dan bayangan dramatis untuk menciptakan suasana yang intens dan bergaya.'
    ],
    'tropical' => [
        'label' => 'Tropical Journey',
        'description' => 'Kombinasi warna tropis vibrant dengan kilau matahari dan ambience liburan energik.',
        'template' => 'Gabungkan kedua foto ini menjadi satu gambar editorial perjalanan tropis. Pastikan palet warna didominasi oleh aqua dan lime yang cerah, menonjolkan kulit yang terpapar matahari dan nuansa gerakan yang ringan dan berangin. Latar belakang harus menampilkan pemandangan resor sinematik yang indah dengan detail dedaunan tropis yang rimbun.'
    ],
    'heritage' => [
        'label' => 'Heritage Elegance',
        'description' => 'Sentuhan tradisional hangat dengan tekstur kaya dan detail busana elegan.',
        'template' => 'Gabungkan kedua foto ini menjadi sebuah potret upacara warisan yang berkesan. Gunakan pencahayaan ambar yang hangat dan lembut untuk menciptakan suasana yang syahdu. Pastikan detail tekstil yang rumit pada pakaian atau latar belakang terlihat jelas. Tambahkan efek kabut sinematik yang lembut untuk sentuhan dramatis. Kedua subjek harus berpose dengan anggun dan bermartabat, seolah sedang menceritakan kisah abadi dari masa lalu.'
    ],
    'holiday' => [
        'label' => 'Liburan',
        'description' => 'Suasana pantai cerah dengan pasir putih luas, air laut biru jernih, serta deretan pohon kelapa yang melengkung.',
        'template' => 'Gabungkan foto ini ke dalam suasana liburan di pantai Indonesia yang cerah dan indah. Pastikan ada elemen khas pantai seperti pasir putih, air laut biru jernih, dan pohon kelapa. Pancarkan suasana relaksasi dan kebahagiaan saat berlibur.'
    ]
];

$defaultThemeKey = 'romantic';
$defaultTheme = $themeOptions[$defaultThemeKey];
$defaultPromptTemplate = $defaultTheme['template'];
$defaultThemeDescription = $defaultTheme['description'];

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
                        <div class="credit-pill" id="creditPill" role="status" aria-live="polite">
                            <span class="credit-label">Saldo Kredit</span>
                            <span class="credit-value" id="creditValue"><?php echo htmlspecialchars($coinBalanceFormatted, ENT_QUOTES); ?></span>
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
<?php foreach ($themeOptions as $key => $option): ?>
                            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>"<?php echo $key === $defaultThemeKey ? ' selected' : ''; ?>><?php echo htmlspecialchars($option['label'], ENT_QUOTES); ?></option>
<?php endforeach; ?>
                        </select>
                        <p id="themeHint" class="muted"><?php echo htmlspecialchars($defaultThemeDescription, ENT_QUOTES); ?></p>
                    </div>

                    <div>
                        <label for="promptStyle">Prompt style / Tema penggabungan</label>
                        <textarea id="promptStyle" rows="4" placeholder="contoh: dreamy editorial portrait blending our references with golden hour glow"><?php echo htmlspecialchars($defaultPromptTemplate, ENT_QUOTES); ?></textarea>
                        <p class="muted">Tambahkan detail suasana atau warna. <button type="button" class="link" id="resetPromptButton">Gunakan template tema</button></p>
                    </div>

                    <div>
                        <div class="small-label">Foto referensi</div>
                        <div id="dropzone" class="film-dropzone" tabindex="0">
                            <input id="referenceInput" type="file" accept="image/*" multiple hidden>
                            <div class="film-drop-inner">
                                <div style="margin-bottom:4px;">Tarik &amp; lepas atau <button type="button" class="link" id="browseButton">pilih dari perangkat</button></div>
                                <span>Gunakan 2–3 foto (JPG, PNG, atau WEBP) untuk menjaga konsistensi wajah. (Usahakan Foto CloseUP)</span>
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

        const initialAccount = <?php echo json_encode($accountPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const ACCOUNT_ENDPOINT = 'index.php?api=account';
        const ACCOUNT_COINS_ENDPOINT = 'index.php?api=account-coins';
        const COIN_COST_PER_POSE = 1;
        const API_ENDPOINT = 'index.php?api=freepik';
        const CREATE_PATH = '/v1/ai/gemini-2-5-flash-image-preview';
        const STATUS_PATH = (taskId) => `/v1/ai/gemini-2-5-flash-image-preview/${taskId}`;
        const POLL_INTERVAL = 8000;
        const MIN_FILES = 2;
        const MAX_FILES = 3;
        const UPLOAD_ENDPOINT = 'index.php?api=upload';
        const CACHE_ENDPOINT = 'index.php?api=cache';

        let currentAccount = initialAccount;
        let coinsDebitedForRun = false;
        const referenceUploadMap = new Map();
        const cachedImageMap = new Map();

        const creditValueEl = document.getElementById('creditValue');
        const creditPillEl = document.getElementById('creditPill');

        function formatCoins(value) {
            const number = Number.isFinite(Number(value)) ? Number(value) : 0;
            return number.toLocaleString('id-ID');
        }

        function updateCreditDisplay() {
            if (!creditValueEl) {
                return;
            }
            const balance = currentAccount && Number.isFinite(Number(currentAccount.coins))
                ? Number(currentAccount.coins)
                : 0;
            creditValueEl.textContent = formatCoins(balance);
            if (creditPillEl) {
                creditPillEl.dataset.balance = String(balance);
            }
        }

        async function fetchAccountState() {
            const res = await fetch(ACCOUNT_ENDPOINT, {
                credentials: 'same-origin',
            });
            const payload = await res.json();
            if (!res.ok || !payload.ok) {
                throw new Error((payload && payload.error) || 'Gagal memuat akun.');
            }
            return payload.data || null;
        }

        async function refreshAccountState() {
            try {
                const account = await fetchAccountState();
                currentAccount = account;
                updateCreditDisplay();
                return account;
            } catch (error) {
                console.warn('Tidak dapat memperbarui akun:', error);
                throw error;
            }
        }

        function ensureCoins(amount) {
            if (!currentAccount) {
                return false;
            }
            const balance = Number.isFinite(Number(currentAccount.coins))
                ? Number(currentAccount.coins)
                : 0;
            return balance >= amount;
        }

        async function spendCoins(amount) {
            if (!amount || amount <= 0) {
                return;
            }
            const res = await fetch(ACCOUNT_COINS_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ amount }),
            });
            const payload = await res.json();
            if (!res.ok || !payload.ok) {
                const message = (payload && payload.error) || 'Saldo koin gagal diperbarui.';
                throw new Error(typeof message === 'string' ? message : 'Saldo koin gagal diperbarui.');
            }
            const data = payload.data || {};
            if (!currentAccount) {
                currentAccount = {};
            }
            if (typeof data.coins !== 'undefined') {
                currentAccount.coins = data.coins;
            }
            updateCreditDisplay();
        }

        async function deductCoinsForSuccess(successCount) {
            if (coinsDebitedForRun || !successCount) {
                return;
            }
            const totalCost = successCount * COIN_COST_PER_POSE;
            if (!totalCost) {
                return;
            }
            await spendCoins(totalCost);
            coinsDebitedForRun = true;
        }

        updateCreditDisplay();
        refreshAccountState().catch(() => {
            /* noop */
        });

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

        const themeOptions = <?php echo json_encode($themeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const DEFAULT_THEME_KEY = <?php echo json_encode($defaultThemeKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        const poseVariants = [
            {
                key: 'closeUpGlow',
                badge: 'Pose 1',
                title: 'Siluet Harmonis',
                shot: 'Close-up portrait angle dengan bahu sedikit miring, tatapan lembut ke kamera, tangan menyentuh wajah secara elegan.',
                aspectRatio: 'portrait_3_4',
                description(theme, prompt) {
                    const base = 'Close-up harmonis menonjolkan ekspresi utama dengan highlight lembut.';
                    if (prompt) {
                        return `${base} Tema "${prompt}" ditanamkan pada warna dan pencahayaan wajah.`;
                    }
                    const themeDescription = theme && theme.description ? theme.description : '';
                    return `${base} ${themeDescription}`.trim();
                }
            },
            {
                key: 'dynamicMotion',
                badge: 'Pose 2',
                title: 'Dynamic Motion',
                shot: 'Full body fashion stride dengan motion blur halus, kain bergerak dramatis, ekspresi percaya diri ke samping.',
                aspectRatio: 'portrait_3_4',
                description(theme, prompt) {
                    const base = 'Gerakan dramatis dengan komposisi diagonal dan aksen cahaya dinamis.';
                    if (prompt) {
                        return `${base} Tema "${prompt}" diaplikasikan pada wardrobe dan motion blur.`;
                    }
                    const themeDescription = theme && theme.description ? theme.description : '';
                    return `${base} ${themeDescription}`.trim();
                }
            },
            {
                key: 'wideStory',
                badge: 'Pose 3',
                title: 'Wide Storytelling',
                shot: 'Wide storytelling frame yang memperlihatkan karakter berinteraksi dengan lingkungan tematik.',
                aspectRatio: 'landscape_3_2',
                description(theme, prompt) {
                    const base = 'Storytelling shot menonjolkan kostum penuh dan suasana latar.';
                    if (prompt) {
                        return `${base} Tema "${prompt}" diterapkan pada warna environment.`;
                    }
                    const themeDescription = theme && theme.description ? theme.description : '';
                    return `${base} ${themeDescription}`.trim();
                }
            },
            {
                key: 'detailCinematic',
                badge: 'Pose 4',
                title: 'Cinematic Detail',
                shot: 'Medium close-up fokus pada aksesori dan tekstur bahan dengan pencahayaan dramatis.',
                aspectRatio: 'square_1_1',
                description(theme, prompt) {
                    const base = 'Detail shot memperlihatkan aksesori dan tekstur bahan dengan dramatis.';
                    if (prompt) {
                        return `${base} Tema "${prompt}" ditekankan pada highlight detail.`;
                    }
                    const themeDescription = theme && theme.description ? theme.description : '';
                    return `${base} ${themeDescription}`.trim();
                }
            }
        ];

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

        function statusIndicatorKey(status) {
            const value = normalizeStatus(status);
            if (value === 'COMPLETED') {
                return 'completed';
            }
            if (value === 'FAILED' || value === 'ERROR') {
                return 'error';
            }
            if (value === 'PROCESSING' || value === 'RUNNING' || value === 'IN_PROGRESS') {
                return 'processing';
            }
            return 'pending';
        }

        function finalStatus(status) {
            const value = normalizeStatus(status);
            return value === 'COMPLETED' || value === 'FAILED' || value === 'ERROR';
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
            if (!themeHint) {
                return;
            }
            const key = themeOptions[themeKey] ? themeKey : DEFAULT_THEME_KEY;
            const option = themeOptions[key];
            if (option && option.description) {
                themeHint.textContent = option.description;
            } else {
                themeHint.textContent = '';
            }
        }

        function ensurePromptTemplate(themeKey, force = false) {
            const key = themeOptions[themeKey] ? themeKey : DEFAULT_THEME_KEY;
            const option = themeOptions[key];
            if (!option || !promptStyleInput) {
                return;
            }
            if (force || !promptDirty || !promptStyleInput.value.trim()) {
                promptStyleInput.value = option.template;
                promptDirty = false;
            }
        }

        function setPromptDirty() {
            if (!promptStyleInput) {
                return;
            }
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

        function fileCacheKey(file) {
            if (!file) {
                return 'unknown-0-0';
            }
            const name = file.name ? String(file.name) : 'unknown';
            const size = Number.isFinite(Number(file.size)) ? Number(file.size) : 0;
            const modified = Number.isFinite(Number(file.lastModified)) ? Number(file.lastModified) : 0;
            return `${name}-${size}-${modified}`;
        }

        async function uploadReferenceFile(file) {
            const formData = new FormData();
            const fileName = file && file.name ? file.name : 'reference.jpg';
            formData.append('file', file, fileName);

            const res = await fetch(UPLOAD_ENDPOINT, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });

            const text = await res.text();
            let payload;
            try {
                payload = JSON.parse(text);
            } catch (error) {
                throw new Error('Respon upload tidak valid.');
            }

            if (!res.ok || !payload.ok) {
                const message = (payload && payload.error) || 'Gagal mengunggah foto referensi ke server.';
                throw new Error(typeof message === 'string' ? message : 'Gagal mengunggah foto referensi ke server.');
            }

            return payload;
        }

        function ensureReferenceUpload(file) {
            const key = fileCacheKey(file);
            if (!referenceUploadMap.has(key)) {
                const pending = uploadReferenceFile(file).catch((error) => {
                    referenceUploadMap.delete(key);
                    throw error;
                });
                referenceUploadMap.set(key, pending);
            }
            return referenceUploadMap.get(key);
        }

        async function ensureAllReferenceUploads(files) {
            if (!files || !files.length) {
                return [];
            }
            return Promise.all(files.map((file) => ensureReferenceUpload(file)));
        }

        async function cacheGeneratedAsset(url) {
            if (!url) {
                return null;
            }
            if (cachedImageMap.has(url)) {
                return cachedImageMap.get(url);
            }

            const pending = (async () => {
                const res = await fetch(CACHE_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ url }),
                });

                const text = await res.text();
                let payload;
                try {
                    payload = JSON.parse(text);
                } catch (error) {
                    throw new Error('Respon cache tidak valid.');
                }

                if (!res.ok || !payload.ok) {
                    const message = (payload && payload.error) || 'Gagal menyimpan hasil ke server.';
                    throw new Error(typeof message === 'string' ? message : 'Gagal menyimpan hasil ke server.');
                }

                return payload.url || null;
            })().catch((error) => {
                cachedImageMap.delete(url);
                throw error;
            });

            cachedImageMap.set(url, pending);
            return pending;
        }

        function renderPreview() {
            if (!previewGrid || !dropzone) {
                return;
            }
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
            if (referenceInput) {
                referenceInput.value = '';
            }
            renderPreview();

            ensureAllReferenceUploads(selectedFiles).catch((error) => {
                console.error('Upload referensi gagal:', error);
                updateFormStatus(error.message || 'Gagal mengunggah foto referensi ke server.', 'error');
            });

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
            const accent = customPrompt && customPrompt.trim() !== '' ? customPrompt.trim() : (theme.template || '');
            const segments = [
                '[MultiReference Blend] Gabungkan semua foto referensi, pertahankan wajah, rambut, dan kostum yang konsisten.',
                `[Theme Treatment] ${theme.label || ''}. ${theme.description || ''}`,
                `[Pose Direction] ${variant.shot}`,
                `[Styling Motif] ${accent}`,
                '[Camera & Lighting] cinematic lighting, editorial photography, high dynamic range, rich texture, 8k detail.',
                '[Quality] sharp focus, clean background, no watermark, no text overlay.'
            ];
            return segments.join(' ');
        }

        function prepareVariantRequest(themeKey, variant, customPrompt, referencesBase64) {
            const fallbackTheme = themeOptions[DEFAULT_THEME_KEY] || {};
            const theme = themeOptions[themeKey] || fallbackTheme;
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
            const response = data && data.data ? data.data : {};
            return {
                taskId: response.task_id || null,
                status: response.status || 'CREATED'
            };
        }

        async function fetchGeminiStatus(taskId) {
            if (!taskId) {
                throw new Error('Task ID tidak ditemukan.');
            }
            const data = await callFreepik({ path: STATUS_PATH(taskId), method: 'GET' });
            const response = data && data.data ? data.data : {};
            const generated = Array.isArray(response.generated) ? response.generated : [];
            return {
                status: response.status || null,
                generated
            };
        }

        function renderResults() {
            if (!resultGrid) {
                return;
            }
            resultGrid.innerHTML = '';
            if (!tasks.length) {
                showEmptyState(true);
                return;
            }

            showEmptyState(false);
            const fragment = document.createDocumentFragment();

            tasks.forEach((task) => {
                const statusKey = statusIndicatorKey(task.status);
                const tile = document.createElement('article');
                tile.className = 'pose-tile';
                tile.dataset.status = statusKey;
                tile.setAttribute('aria-label', `${task.variant.badge} - ${statusLabel(task.status)}`);
                tile.tabIndex = 0;

                const frame = document.createElement('div');
                frame.className = 'pose-frame';

                if (task.imageUrl) {
                    const img = document.createElement('img');
                    img.className = 'pose-image';
                    img.src = task.imageUrl;
                    img.alt = task.variant.title;
                    frame.appendChild(img);
                } else {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'pose-placeholder';
                    const spinner = document.createElement('span');
                    spinner.className = 'pose-spinner';
                    spinner.setAttribute('aria-hidden', 'true');
                    placeholder.appendChild(spinner);
                    frame.appendChild(placeholder);
                }

                const statusDot = document.createElement('span');
                statusDot.className = `pose-status-dot pose-status-dot--${statusKey}`;
                statusDot.setAttribute('aria-hidden', 'true');
                statusDot.title = statusLabel(task.status);
                frame.appendChild(statusDot);

                tile.appendChild(frame);

                if (task.imageUrl) {
                    const toolbar = document.createElement('div');
                    toolbar.className = 'pose-toolbar';

                    if (!task.downloadName) {
                        const timestamp = Date.now();
                        task.downloadName = `${task.variant.key || 'pose'}-${timestamp}.png`;
                    }

                    const openLink = document.createElement('a');
                    openLink.href = task.imageUrl;
                    openLink.target = '_blank';
                    openLink.rel = 'noopener';
                    openLink.className = 'pose-icon-btn';
                    openLink.textContent = '↗';
                    openLink.setAttribute('aria-label', 'Buka pose di tab baru');
                    toolbar.appendChild(openLink);

                    const downloadLink = document.createElement('a');
                    downloadLink.href = task.imageUrl;
                    downloadLink.download = task.downloadName;
                    downloadLink.className = 'pose-icon-btn';
                    downloadLink.textContent = '⬇';
                    downloadLink.setAttribute('aria-label', 'Unduh pose');
                    toolbar.appendChild(downloadLink);

                    tile.appendChild(toolbar);
                }

                fragment.appendChild(tile);
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
            const pending = tasks.filter((task) => task.taskId && !finalStatus(task.status));
            if (!pending.length) {
                stopPolling();
                return;
            }

            for (const task of pending) {
                try {
                    const { status, generated } = await fetchGeminiStatus(task.taskId);
                    if (status) {
                        task.status = status;
                    }
                    if (generated && generated.length) {
                        const remoteUrl = generated[0];
                        if (remoteUrl) {
                            const previousRemote = task.remoteUrl;
                            const remoteChanged = remoteUrl !== previousRemote;
                            const attempts = remoteChanged ? 0 : (typeof task.cacheAttempts === 'number' ? task.cacheAttempts : 0);
                            const shouldRetryCache = !!task.cacheError && attempts < 3;

                            if (remoteChanged || shouldRetryCache || !task.imageUrl) {
                                try {
                                    const cachedUrl = await cacheGeneratedAsset(remoteUrl);
                                    task.imageUrl = cachedUrl || remoteUrl;
                                    task.cachedUrl = cachedUrl || null;
                                    task.cacheError = null;
                                } catch (error) {
                                    task.imageUrl = remoteUrl;
                                    task.cachedUrl = null;
                                    task.cacheError = error.message || 'Gagal menyimpan hasil ke server.';
                                    console.warn('Gagal menyimpan hasil ke server:', error);
                                }

                                task.remoteUrl = remoteUrl;
                                task.cacheAttempts = attempts + 1;
                            }
                        }
                    }
                } catch (error) {
                    task.status = 'ERROR';
                    task.error = error.message || 'Gagal mengambil status generasi.';
                }
            }

            renderResults();

            const completed = tasks.filter((task) => finalStatus(task.status)).length;
            if (completed === tasks.length) {
                stopPolling();
                const successCount = tasks.filter((task) => normalizeStatus(task.status) === 'COMPLETED' && task.imageUrl).length;
                const hasCacheError = tasks.some((task) => task.cacheError);
                let statusMessage = 'Semua pose gagal diproses. Coba lagi.';
                let statusType = 'error';
                if (successCount === tasks.length && successCount > 0) {
                    statusMessage = 'Selesai! Semua pose berhasil dibuat.';
                    statusType = 'success';
                } else if (successCount > 0) {
                    statusMessage = `${successCount} pose berhasil. Periksa pose lain yang gagal.`;
                    statusType = 'error';
                }

                if (successCount > 0) {
                    try {
                        await deductCoinsForSuccess(successCount);
                    } catch (error) {
                        console.error('Gagal memperbarui saldo kredit:', error);
                        updateFormStatus('Hasil berhasil dibuat, tetapi saldo kredit tidak dapat diperbarui. Hubungi admin.', 'error');
                        return;
                    }
                }

                if (successCount > 0 && hasCacheError) {
                    if (statusType === 'success') {
                        statusMessage = 'Hasil berhasil dibuat, tetapi tidak semua file dapat disalin ke server. Gunakan tombol unduh bila diperlukan.';
                    } else {
                        statusMessage = `${statusMessage} Namun, tidak semua file dapat disalin ke server. Gunakan tombol unduh bila diperlukan.`;
                    }
                    statusType = 'error';
                }

                updateFormStatus(statusMessage, statusType);
                return;
            }

            if (completed > 0) {
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

            await refreshAccountState().catch(() => {});

            const requiredCoins = poseVariants.length * COIN_COST_PER_POSE;
            if (!ensureCoins(requiredCoins)) {
                updateFormStatus(`Saldo kredit kamu tidak mencukupi. Dibutuhkan minimal ${requiredCoins} kredit untuk membuat 4 pose.`, 'error');
                return;
            }

            setLoadingState(true);
            updateFormStatus('Mengunggah referensi ke server…', 'info');
            stopPolling();
            tasks = [];
            coinsDebitedForRun = false;
            renderResults();

            try {
                await ensureAllReferenceUploads(selectedFiles);
            } catch (error) {
                setLoadingState(false);
                updateFormStatus(error.message || 'Gagal mengunggah foto referensi ke server.', 'error');
                return;
            }

            updateFormStatus('Mengonversi referensi dan menyiapkan permintaan…', 'info');

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

            tasks = poseVariants.map((variant) => ({
                variant,
                themeKey,
                customPrompt,
                prompt: '',
                taskId: null,
                status: 'PENDING',
                imageUrl: null,
                remoteUrl: null,
                cachedUrl: null,
                cacheError: null,
                cacheAttempts: 0,
                downloadName: null,
                error: null
            }));
            renderResults();

            let successCount = 0;
            let failureCount = 0;

            for (const task of tasks) {
                try {
                    const { prompt, body } = prepareVariantRequest(themeKey, task.variant, customPrompt, base64Images);
                    task.prompt = prompt;
                    const { taskId, status } = await createGeminiTask(body);
                    task.taskId = taskId;
                    task.status = status || 'CREATED';
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

        if (dropzone && referenceInput) {
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
                if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                    handleFiles(event.dataTransfer.files);
                }
            });
        }

        if (browseButton && referenceInput) {
            browseButton.addEventListener('click', () => {
                referenceInput.click();
            });
        }

        if (referenceInput) {
            referenceInput.addEventListener('change', () => {
                if (referenceInput.files && referenceInput.files.length) {
                    handleFiles(referenceInput.files);
                }
            });
        }

        if (themeSelect) {
            themeSelect.addEventListener('change', () => {
                const themeKey = themeSelect.value;
                renderThemeHint(themeKey);
                ensurePromptTemplate(themeKey);
            });
        }

        if (promptStyleInput) {
            promptStyleInput.addEventListener('input', setPromptDirty);
        }

        if (resetPromptButton && themeSelect) {
            resetPromptButton.addEventListener('click', () => {
                ensurePromptTemplate(themeSelect.value, true);
            });
        }

        if (form) {
            form.addEventListener('submit', submitForm);
        }

        if (themeSelect) {
            renderThemeHint(themeSelect.value);
            ensurePromptTemplate(themeSelect.value, true);
        }

        renderPreview();
        showEmptyState(true);
    })();
    </script>
</body>
</html>
