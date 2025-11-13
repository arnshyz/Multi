<?php
require_once __DIR__ . '/auth.php';

auth_session_start();

if (!auth_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$account = auth_current_account();
$account = $account ? auth_normalize_account($account) : null;
$username = 'Pengguna';
if ($account) {
    $usernameCandidate = trim((string)($account['name'] ?? ''));
    if ($usernameCandidate === '') {
        $usernameCandidate = trim((string)($account['username'] ?? ''));
    }
    if ($usernameCandidate !== '') {
        $username = $usernameCandidate;
    }
}

?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kling 2.5 Pro · Image to Video Reference</title>
    <meta name="description" content="Dokumentasi internal Kling v2.5 Pro Freepik API lengkap dengan contoh request dan form uji coba.">
    <link rel="icon" type="image/png" href="/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="kling25pro.css">
</head>
<body class="kling-doc dark-mode">
    <div class="doc-wrapper">
        <header class="doc-header glass-card">
            <div class="doc-header__info">
                <span class="doc-chip">Freepik · Image to Video</span>
                <h1>Kling v2.5 Pro</h1>
                <p>Endpoint resmi dari Freepik untuk mengubah gambar tunggal menjadi video sinematik 5–12 detik menggunakan model Kling 2.5 Pro.</p>
            </div>
            <div class="doc-header__meta">
                <div class="doc-user-chip" aria-label="Akun aktif">
                    <span class="doc-user-avatar"><?php echo htmlspecialchars(strtoupper(substr($username, 0, 2)), ENT_QUOTES); ?></span>
                    <div class="doc-user-meta">
                        <span class="doc-user-label">Masuk sebagai</span>
                        <span class="doc-user-name"><?php echo htmlspecialchars($username, ENT_QUOTES); ?></span>
                    </div>
                </div>
                <a href="index.php" class="btn-secondary">← Kembali ke Dashboard</a>
            </div>
        </header>


            <section class="doc-section glass-card" aria-labelledby="tryIt">

                <form id="klingForm" class="doc-form" novalidate>
                    <div class="doc-form-grid">
                        <div class="form-field">
                            <label for="imageInput">Image URL <span>*</span></label>
                            <input type="url" id="imageInput" name="image" placeholder="https://..." required>
                        </div>
                        <div class="form-field">
                            <label for="durationInput">Durasi <span>*</span></label>
                            <select id="durationInput" name="duration" required>
                                <option value="5" selected>5 detik (default)</option>
                                <option value="10">10 detik</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="cfgInput">CFG Scale <span>*</span></label>
                            <input type="number" id="cfgInput" name="cfg_scale" step="0.1" min="0.1" max="2" value="0.5" required>
                        </div>
                        <div class="form-field">
                            <label for="webhookInput">Webhook URL</label>
                            <input type="url" id="webhookInput" name="webhook_url" placeholder="https://example.com/webhook">
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="promptInput">Prompt <span>*</span></label>
                        <textarea id="promptInput" name="prompt" rows="3" placeholder="Describe the desired motion and camera style" required></textarea>
                    </div>
                    <div class="form-field">
                        <label for="negativeInput">Negative Prompt</label>
                        <textarea id="negativeInput" name="negative_prompt" rows="2" placeholder="Things to avoid"></textarea>
                    </div>
                    <div class="doc-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">Kirim Request</button>
                        <button type="button" class="btn-secondary" id="resetBtn">Reset</button>
                    </div>
                    <div class="doc-status" id="docStatus" role="status" aria-live="polite"></div>
                </form>
                <div class="doc-result" aria-live="polite">
                    <h3>Response</h3>
                    <pre class="code-block" id="responsePayload">{ }
</pre>
                </div>
                <div class="doc-result" id="tasksResult">
                    <div class="doc-result__header">
                        <h3>Daftar Task Aktif</h3>
                        <button type="button" class="btn-secondary" id="fetchTasksBtn">Muat Daftar</button>
                    </div>
                    <pre class="code-block" id="tasksPayload">{ }
</pre>
                </div>
                <div class="doc-result" id="statusResult">
                    <div class="doc-result__header">
                        <h3>Status Task</h3>
                    </div>
                    <form id="statusForm" class="doc-inline-form" novalidate>
                        <label class="sr-only" for="statusTaskId">Task ID</label>
                        <input type="text" id="statusTaskId" name="task_id" placeholder="Masukkan Task ID" required>
                        <button type="submit" class="btn-primary" id="statusSubmitBtn">Cek Status</button>
                    </form>
                    <pre class="code-block" id="statusPayload">{ }
</pre>
                </div>
                <div class="doc-result" id="videoResult">
                    <div class="doc-result__header">
                        <h3>Hasil Video</h3>
                        <button type="button" class="btn-primary" id="fetchVideoBtn" disabled>Ambil Video</button>
                    </div>
                    <div class="kling-results-empty" id="klingResultsEmpty">Belum ada video yang tersedia. Kirim atau muat task untuk melihat hasilnya.</div>
                    <div class="kling-results-grid" id="klingResults" aria-live="polite"></div>
                </div>
            </section>
        </main>
    </div>

    <div class="kling-preview" id="klingPreviewModal" aria-hidden="true">
        <div class="kling-preview__dialog" role="dialog" aria-modal="true" aria-labelledby="klingPreviewTitle">
            <button type="button" class="kling-preview__close" id="klingPreviewClose" aria-label="Tutup preview">&times;</button>
            <div class="kling-preview__body">
                <h3 class="kling-preview__title" id="klingPreviewTitle">Preview Video</h3>
                <div class="kling-preview__content" id="klingPreviewBody"></div>
            </div>
            <a href="#" class="kling-preview__download" id="klingPreviewDownload" download target="_blank" rel="noopener">Download Video</a>
        </div>
    </div>

    <script>
    (() => {
        const form = document.getElementById('klingForm');
        const statusEl = document.getElementById('docStatus');
        const responseEl = document.getElementById('responsePayload');
        const fetchVideoBtn = document.getElementById('fetchVideoBtn');
        const resultsGrid = document.getElementById('klingResults');
        const resultsEmpty = document.getElementById('klingResultsEmpty');
        const previewModal = document.getElementById('klingPreviewModal');
        const previewBody = document.getElementById('klingPreviewBody');
        const previewClose = document.getElementById('klingPreviewClose');
        const previewDownload = document.getElementById('klingPreviewDownload');
        const fetchTasksBtn = document.getElementById('fetchTasksBtn');
        const tasksPayloadEl = document.getElementById('tasksPayload');
        const statusForm = document.getElementById('statusForm');
        const statusTaskIdInput = document.getElementById('statusTaskId');
        const statusPayloadEl = document.getElementById('statusPayload');
        const statusSubmitBtn = document.getElementById('statusSubmitBtn');
        const resetBtn = document.getElementById('resetBtn');
        const submitBtn = document.getElementById('submitBtn');

        let currentTaskId = null;
        let lastRequestContext = null;
        const generatedVideos = [];
        const DRIVE_ENDPOINT = 'index.php?api=drive';

        function setStatus(message, type = 'info') {
            if (!statusEl) return;
            statusEl.textContent = message || '';
            statusEl.dataset.state = type;
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

        function nowIso() {
            try {
                return new Date().toISOString();
            } catch (err) {
                return '';
            }
        }

        async function persistDriveItems(items) {
            if (!items || !items.length) {
                return null;
            }

            const response = await fetch(DRIVE_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ items })
            });

            let data = null;
            try {
                data = await response.json();
            } catch (err) {
                // ignore json parse errors
            }

            if (!response.ok || !data || data.ok !== true) {
                const message = data && data.error ? data.error : `Gagal menyimpan drive (status ${response.status})`;
                throw new Error(message);
            }

            return data.data && data.data.items ? data.data.items : [];
        }

        function closePreview() {
            if (!previewModal) return;
            previewModal.classList.remove('is-visible');
            previewModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            if (previewBody) {
                const playingVideo = previewBody.querySelector('video');
                if (playingVideo && typeof playingVideo.pause === 'function') {
                    try {
                        playingVideo.pause();
                    } catch (err) {
                        // ignore pause error
                    }
                }
                previewBody.innerHTML = '';
            }
            if (previewDownload) {
                previewDownload.href = '#';
                previewDownload.style.display = 'none';
            }
        }

        function openPreview(url) {
            if (!previewModal || !previewBody) return;
            const absoluteUrl = ensureAbsoluteUrl(url);
            if (!absoluteUrl) return;

            previewBody.innerHTML = '';
            const video = document.createElement('video');
            video.src = absoluteUrl;
            video.controls = true;
            video.autoplay = true;
            video.loop = true;
            video.playsInline = true;
            video.className = 'kling-preview__video';

            previewBody.appendChild(video);

            if (previewDownload) {
                previewDownload.href = absoluteUrl;
                previewDownload.style.display = 'inline-flex';
            }

            previewModal.classList.add('is-visible');
            previewModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        }

        function getContextDetails(context = {}) {
            const fallback = lastRequestContext || {};
            return {
                taskId: context.taskId || currentTaskId || fallback.taskId || null,
                prompt: context.prompt || fallback.prompt || '',
                negativePrompt: context.negativePrompt || fallback.negativePrompt || '',
                duration: context.duration != null ? context.duration : (fallback.duration != null ? fallback.duration : null),
                cfgScale: context.cfgScale != null ? context.cfgScale : (fallback.cfgScale != null ? fallback.cfgScale : null),
                image: context.image || fallback.image || ''
            };
        }

        function renderVideoCards() {
            if (!resultsGrid || !resultsEmpty) return;

            if (!generatedVideos.length) {
                resultsGrid.innerHTML = '';
                resultsEmpty.hidden = false;
                return;
            }

            resultsEmpty.hidden = true;
            resultsGrid.innerHTML = '';

            generatedVideos.forEach((entry, index) => {
                const card = document.createElement('div');
                card.className = 'kling-result-card';

                const header = document.createElement('div');
                header.className = 'kling-result-header';

                const title = document.createElement('div');
                title.className = 'kling-result-title';
                title.textContent = `Video ${index + 1}`;
                header.appendChild(title);

                if (entry.taskId) {
                    const taskBadge = document.createElement('span');
                    taskBadge.className = 'kling-result-task';
                    taskBadge.textContent = entry.taskId;
                    header.appendChild(taskBadge);
                }

                card.appendChild(header);

                const media = document.createElement('div');
                media.className = 'kling-result-media';

                if (entry.image) {
                    const imageUrl = ensureAbsoluteUrl(entry.image);
                    if (/^https?:\/\//i.test(imageUrl)) {
                        const imageCard = document.createElement('div');
                        imageCard.className = 'kling-thumb-card';
                        const image = document.createElement('img');
                        image.src = imageUrl;
                        image.alt = 'Image prompt reference';
                        image.loading = 'lazy';
                        image.className = 'kling-thumb-image';
                        imageCard.appendChild(image);
                        media.appendChild(imageCard);
                    }
                }

                const videoWrapper = document.createElement('div');
                videoWrapper.className = 'kling-video-card';

                const video = document.createElement('video');
                video.src = entry.url;
                video.controls = true;
                video.loop = true;
                video.muted = true;
                video.playsInline = true;
                video.preload = 'metadata';
                video.className = 'kling-result-video';
                videoWrapper.appendChild(video);

                const actions = document.createElement('div');
                actions.className = 'kling-result-actions';

                const previewBtn = document.createElement('button');
                previewBtn.type = 'button';
                previewBtn.className = 'kling-action-btn kling-action-btn--ghost';
                previewBtn.textContent = 'Preview';
                previewBtn.addEventListener('click', () => openPreview(entry.url));
                actions.appendChild(previewBtn);

                const downloadLink = document.createElement('a');
                downloadLink.href = entry.url;
                downloadLink.target = '_blank';
                downloadLink.rel = 'noopener noreferrer';
                downloadLink.download = '';
                downloadLink.className = 'kling-action-btn';
                downloadLink.textContent = 'Download';
                actions.appendChild(downloadLink);

                const saveBtn = document.createElement('button');
                saveBtn.type = 'button';
                saveBtn.className = 'kling-action-btn kling-action-btn--primary';
                saveBtn.textContent = entry.saved ? 'Tersimpan ✓' : (entry.saving ? 'Menyimpan…' : 'Simpan ke Drive');
                saveBtn.disabled = entry.saved || entry.saving;
                saveBtn.addEventListener('click', () => saveKlingVideoToDrive(entry));
                actions.appendChild(saveBtn);

                videoWrapper.appendChild(actions);
                media.appendChild(videoWrapper);
                card.appendChild(media);

                const meta = document.createElement('div');
                meta.className = 'kling-result-meta';
                const metaParts = [];
                if (entry.duration) {
                    metaParts.push(`${entry.duration}s`);
                }
                if (entry.cfgScale != null) {
                    metaParts.push(`CFG ${entry.cfgScale}`);
                }
                if (metaParts.length) {
                    meta.textContent = metaParts.join(' • ');
                    card.appendChild(meta);
                }

                const prompt = document.createElement('p');
                prompt.className = 'kling-result-prompt';
                prompt.textContent = entry.prompt ? entry.prompt : 'Prompt tidak tersedia.';
                card.appendChild(prompt);

                if (entry.negativePrompt) {
                    const negative = document.createElement('p');
                    negative.className = 'kling-result-negative';
                    negative.textContent = `Negative: ${entry.negativePrompt}`;
                    card.appendChild(negative);
                }

                const statusLine = document.createElement('div');
                statusLine.className = 'kling-result-status';
                statusLine.dataset.state = entry.error ? 'error' : (entry.saved ? 'success' : (entry.saving ? 'info' : 'muted'));
                if (entry.error) {
                    statusLine.textContent = entry.error;
                } else if (entry.saved) {
                    statusLine.textContent = entry.savedAt ? `Tersimpan ke drive (${entry.savedAt})` : 'Tersimpan ke drive';
                } else if (entry.saving) {
                    statusLine.textContent = 'Menyimpan video ke drive…';
                } else {
                    statusLine.textContent = 'Belum disimpan ke drive';
                }
                card.appendChild(statusLine);

                resultsGrid.appendChild(card);
            });
        }

        function upsertGeneratedVideo(url, context = {}) {
            if (!url) {
                return null;
            }

            const absoluteUrl = ensureAbsoluteUrl(url);
            if (!/^https?:\/\//i.test(absoluteUrl)) {
                return null;
            }

            const details = getContextDetails(context);
            let entry = generatedVideos.find(item => item.url === absoluteUrl);
            if (!entry) {
                entry = {
                    id: `kling-${Date.now()}-${Math.random().toString(16).slice(2)}`,
                    url: absoluteUrl,
                    taskId: details.taskId,
                    prompt: details.prompt || '',
                    negativePrompt: details.negativePrompt || '',
                    duration: details.duration,
                    cfgScale: details.cfgScale,
                    image: details.image || '',
                    createdAt: nowIso(),
                    saved: false,
                    saving: false,
                    savedAt: null,
                    error: '',
                    autoSave: true
                };
                generatedVideos.push(entry);
            } else {
                entry.taskId = entry.taskId || details.taskId;
                if (details.prompt) entry.prompt = details.prompt;
                if (details.negativePrompt) entry.negativePrompt = details.negativePrompt;
                if (details.duration != null) entry.duration = details.duration;
                if (details.cfgScale != null) entry.cfgScale = details.cfgScale;
                if (details.image) entry.image = details.image;
            }

            return entry;
        }

        function updateGeneratedVideos(urls, context = {}) {
            if (Array.isArray(urls)) {
                urls.forEach(url => upsertGeneratedVideo(url, context));
            }

            renderVideoCards();

            generatedVideos
                .filter(entry => entry.autoSave && !entry.saved && !entry.saving)
                .forEach(entry => saveKlingVideoToDrive(entry, { auto: true }));
        }

        async function saveKlingVideoToDrive(entry, { auto = false } = {}) {
            if (!entry || !entry.url) {
                return;
            }

            if (entry.saved || entry.saving) {
                entry.autoSave = false;
                return;
            }

            entry.saving = true;
            entry.error = '';
            entry.autoSave = false;
            renderVideoCards();

            const payload = {
                type: 'video',
                url: entry.url,
                model: 'kling-v2-5-pro',
                prompt: entry.prompt || null,
                created_at: entry.createdAt || nowIso()
            };

            const thumb = ensureAbsoluteUrl(entry.image);
            if (/^https?:\/\//i.test(thumb)) {
                payload.thumbnail_url = thumb;
            }

            try {
                await persistDriveItems([payload]);
                entry.saved = true;
                entry.savedAt = nowIso();
                if (!auto) {
                    setStatus('Video berhasil disimpan ke drive.', 'success');
                }
            } catch (err) {
                entry.error = err && err.message ? err.message : 'Gagal menyimpan video ke drive.';
                if (!auto) {
                    setStatus(entry.error, 'error');
                } else {
                    console.warn('Auto-save drive gagal:', err);
                }
            } finally {
                entry.saving = false;
                renderVideoCards();
            }
        }

        if (previewClose) {
            previewClose.addEventListener('click', closePreview);
        }

        if (previewModal) {
            previewModal.addEventListener('click', event => {
                if (event.target === previewModal) {
                    closePreview();
                }
            });
        }

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closePreview();
            }
        });

        function clearOutputs() {
            if (responseEl) {
                responseEl.textContent = '{ }\n';
            }
            if (tasksPayloadEl) {
                tasksPayloadEl.textContent = '{ }\n';
            }
            if (statusPayloadEl) {
                statusPayloadEl.textContent = '{ }\n';
            }
            generatedVideos.length = 0;
            renderVideoCards();
            if (fetchVideoBtn) {
                fetchVideoBtn.disabled = true;
            }
            currentTaskId = null;
            lastRequestContext = null;
            if (statusTaskIdInput) {
                statusTaskIdInput.value = '';
            }
            closePreview();
        }

        function normalizeData(data) {
            if (data && typeof data === 'object' && 'data' in data) {
                const inner = data.data;
                if (inner && typeof inner === 'object') {
                    return inner;
                }
            }
            return data;
        }

        function collectValidationMessages(source) {
            const messages = [];
            if (source == null) {
                return messages;
            }

            const pushMessage = (field, message) => {
                if (!message) {
                    return;
                }
                const prefix = field ? `${field}: ` : '';
                messages.push(`${prefix}${message}`);
            };

            const handleItem = (item) => {
                if (!item) {
                    return;
                }
                if (typeof item === 'string') {
                    messages.push(item);
                    return;
                }
                if (typeof item === 'object') {
                    const field = item.field || (Array.isArray(item.loc) ? item.loc.join('.') : item.loc);
                    const detail = item.message || item.msg || item.detail || item.error;
                    if (detail) {
                        pushMessage(field, detail);
                        return;
                    }
                    Object.entries(item).forEach(([key, value]) => {
                        if (typeof value === 'string') {
                            pushMessage(key, value);
                        }
                    });
                }
            };

            if (Array.isArray(source)) {
                source.forEach(handleItem);
                return messages;
            }

            if (typeof source === 'object') {
                Object.entries(source).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        value.forEach(item => {
                            if (typeof item === 'string') {
                                pushMessage(key, item);
                            } else if (item && typeof item === 'object') {
                                const detail = item.message || item.msg || item.detail || item.error;
                                if (detail) {
                                    pushMessage(key, detail);
                                }
                            }
                        });
                    } else if (typeof value === 'string') {
                        pushMessage(key, value);
                    } else if (value && typeof value === 'object') {
                        const nestedMessages = collectValidationMessages(value);
                        nestedMessages.forEach(msg => pushMessage(key, msg));
                    }
                });
                return messages;
            }

            if (typeof source === 'string') {
                messages.push(source);
            }

            return messages;
        }

        function renderJson(target, payload) {
            if (!target) return;
            try {
                target.textContent = JSON.stringify(payload, null, 2);
            } catch (err) {
                target.textContent = String(payload || '');
            }
        }

        function renderResponse(payload) {
            renderJson(responseEl, payload);
        }

        function updateStatusTaskId(taskId) {
            if (!statusTaskIdInput || !taskId) {
                return;
            }
            statusTaskIdInput.value = taskId;
        }

        function extractVideoUrlsFromResponse(payload) {
            const results = [];
            const seen = new Set();

            const visit = (value, hint = '') => {
                if (!value) return;

                if (typeof value === 'string') {
                    if (/^https?:\/\//i.test(value)) {
                        const lowerHint = String(hint).toLowerCase();
                        const looksLikeVideo = /(\\.mp4|\\.mov|\\.webm|\\.mkv|\\.avi)(\?.*)?$/i.test(value) || /video|result|url|download/.test(lowerHint);
                        if (looksLikeVideo && !seen.has(value)) {
                            seen.add(value);
                            results.push(value);
                        }
                    }
                    return;
                }

                if (Array.isArray(value)) {
                    value.forEach(item => visit(item, hint));
                    return;
                }

                if (typeof value === 'object') {
                    Object.entries(value).forEach(([key, val]) => {
                        const nextHint = hint ? `${hint}.${key}` : key;
                        visit(val, nextHint);
                    });
                }
            };

            visit(payload);
            return results;
        }

        async function callFreepikEndpoint({ path, method = 'GET', body } = {}) {
            if (!path) {
                throw new Error('Endpoint path wajib diisi.');
            }

            const payload = { path, method, contentType: 'json' };
            if (method !== 'GET' && typeof body !== 'undefined') {
                payload.body = body;
            }

            const res = await fetch('index.php?api=freepik', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const text = await res.text();
            let json;
            try {
                json = JSON.parse(text);
            } catch (err) {
                console.error('Response bukan JSON valid:', text);
                throw new Error('Server mengembalikan respon non-JSON.');
            }

            if (!json.ok) {
                const status = json.status || 'Error';
                const rawData = json.data;

                let message = (rawData && typeof rawData === 'object' && (rawData.message || rawData.error || rawData.detail)) || json.error || 'Permintaan gagal';
                const extraMessages = [];

                if (typeof rawData === 'string') {
                    extraMessages.push(rawData);
                } else if (rawData && typeof rawData === 'object') {
                    ['errors', 'details', 'detail', 'validation_errors'].forEach(key => {
                        if (key in rawData) {
                            const extracted = collectValidationMessages(rawData[key]);
                            extracted.forEach(msg => {
                                if (msg && !extraMessages.includes(msg)) {
                                    extraMessages.push(msg);
                                }
                            });
                        }
                    });
                }

                if (!message && extraMessages.length) {
                    message = extraMessages.shift();
                }

                const detailText = extraMessages.length ? ` (${extraMessages.join('; ')})` : '';
                throw new Error(`HTTP ${status}: ${message}${detailText}`);
            }

            return json.data;
        }

        if (resetBtn && form) {
            resetBtn.addEventListener('click', () => {
                form.reset();
                clearOutputs();
                setStatus('Form berhasil direset.', 'info');
            });
        }

        if (form) {
            form.addEventListener('submit', async event => {
                event.preventDefault();

                const formData = new FormData(form);
                const image = String(formData.get('image') || '').trim();
                const prompt = String(formData.get('prompt') || '').trim();
                const negative = String(formData.get('negative_prompt') || '').trim();
                const durationRaw = String(formData.get('duration') || '').trim();
                const cfgRaw = String(formData.get('cfg_scale') || '').trim();
                const webhook = String(formData.get('webhook_url') || '').trim();

                if (!image || !prompt) {
                    setStatus('Image URL dan prompt wajib diisi.', 'error');
                    return;
                }

                const payload = {
                    image,
                    prompt,
                };

                const allowedDurations = new Set(['5', '8', '12']);
                const durationValue = allowedDurations.has(durationRaw) ? durationRaw : '5';
                payload.duration = durationValue;

                let cfgValue = cfgRaw === '' ? 0.5 : Number(cfgRaw);
                if (Number.isNaN(cfgValue) || cfgValue <= 0) {
                    setStatus('CFG scale harus berupa angka positif.', 'error');
                    return;
                }
                payload.cfg_scale = Number(cfgValue.toFixed(2));

                if (negative) {
                    payload.negative_prompt = negative;
                }
                if (webhook) {
                    payload.webhook_url = webhook;
                }

                const durationNumber = Number(durationValue);
                const contextSnapshot = {
                    prompt,
                    negativePrompt: negative,
                    image,
                    duration: Number.isNaN(durationNumber) ? null : durationNumber,
                    cfgScale: payload.cfg_scale,
                    taskId: null
                };

                clearOutputs();
                lastRequestContext = contextSnapshot;
                setStatus('Mengirim permintaan ke Freepik...', 'info');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.dataset.loading = 'true';
                }

                try {
                    const data = await callFreepikEndpoint({
                        path: '/v1/ai/image-to-video/kling-v2-5-pro',
                        method: 'POST',
                        body: payload
                    });

                    renderResponse(data);
                    const normalized = normalizeData(data);
                    const taskId = normalized && (normalized.task_id || normalized.taskId || null);

                    if (taskId) {
                        currentTaskId = taskId;
                        updateStatusTaskId(taskId);
                        if (lastRequestContext) {
                            lastRequestContext.taskId = taskId;
                        }
                        if (fetchVideoBtn) {
                            fetchVideoBtn.disabled = false;
                        }
                        setStatus(`Permintaan diterima. Task ID: ${taskId}`, 'success');
                    } else {
                        currentTaskId = null;
                        if (lastRequestContext) {
                            lastRequestContext.taskId = null;
                        }
                        if (fetchVideoBtn) {
                            fetchVideoBtn.disabled = true;
                        }
                        setStatus('Permintaan dikirim. Pantau status task melalui endpoint status.', 'success');
                    }
                } catch (error) {
                    console.error('Gagal membuat task Kling:', error);
                    setStatus(error.message || 'Gagal mengirim request.', 'error');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        delete submitBtn.dataset.loading;
                    }
                }
            });
        }

        if (fetchVideoBtn) {
            fetchVideoBtn.addEventListener('click', async () => {
                if (!currentTaskId) {
                    setStatus('Task ID belum tersedia. Kirim request terlebih dahulu.', 'error');
                    return;
                }

                setStatus('Mengambil URL video...', 'info');
                fetchVideoBtn.disabled = true;

                try {
                    const data = await callFreepikEndpoint({
                        path: `/v1/ai/image-to-video/kling-v2-5-pro/${encodeURIComponent(currentTaskId)}/video`,
                        method: 'GET'
                    });

                    const normalized = normalizeData(data);
                    renderResponse(data);
                    const urls = extractVideoUrlsFromResponse(normalized);
                    updateGeneratedVideos(urls, { taskId: currentTaskId });

                    if (urls.length) {
                        setStatus('URL video berhasil diambil.', 'success');
                    } else {
                        setStatus('Video belum tersedia, coba lagi beberapa saat lagi.', 'warning');
                        fetchVideoBtn.disabled = false;
                    }
                } catch (error) {
                    console.error('Gagal mengambil video Kling:', error);
                    setStatus(error.message || 'Gagal mengambil video.', 'error');
                    fetchVideoBtn.disabled = false;
                } finally {
                    if (fetchVideoBtn) {
                        fetchVideoBtn.disabled = !currentTaskId;
                    }
                }
            });
        }

        if (fetchTasksBtn) {
            fetchTasksBtn.addEventListener('click', async () => {
                setStatus('Memuat daftar task Kling...', 'info');
                fetchTasksBtn.disabled = true;

                try {
                    const data = await callFreepikEndpoint({
                        path: '/v1/ai/image-to-video/kling-v2-5-pro/tasks',
                        method: 'GET'
                    });

                    renderJson(tasksPayloadEl, data);
                    setStatus('Daftar task berhasil dimuat.', 'success');

                    const normalized = normalizeData(data);
                    if (normalized && Array.isArray(normalized.items) && normalized.items.length) {
                        const firstTask = normalized.items[0];
                        const id = firstTask && (firstTask.task_id || firstTask.taskId);
                        if (id) {
                            updateStatusTaskId(id);
                        }
                    }
                } catch (error) {
                    console.error('Gagal memuat daftar task Kling:', error);
                    setStatus(error.message || 'Gagal memuat daftar task.', 'error');
                } finally {
                    fetchTasksBtn.disabled = false;
                }
            });
        }

        if (statusForm) {
            statusForm.addEventListener('submit', async event => {
                event.preventDefault();
                const taskId = statusTaskIdInput ? statusTaskIdInput.value.trim() : '';

                if (!taskId) {
                    setStatus('Masukkan Task ID terlebih dahulu.', 'error');
                    return;
                }

                setStatus(`Mengambil status task ${taskId}...`, 'info');
                if (statusSubmitBtn) {
                    statusSubmitBtn.disabled = true;
                }

                try {
                    const data = await callFreepikEndpoint({
                        path: `/v1/ai/image-to-video/kling-v2-5-pro/${encodeURIComponent(taskId)}`,
                        method: 'GET'
                    });

                    renderJson(statusPayloadEl, data);
                    setStatus('Status task berhasil diambil.', 'success');

                    const normalized = normalizeData(data);
                    const latestTaskId = normalized && (normalized.task_id || normalized.taskId);
                    if (latestTaskId) {
                        currentTaskId = latestTaskId;
                        updateStatusTaskId(latestTaskId);
                        if (lastRequestContext) {
                            lastRequestContext.taskId = latestTaskId;
                        } else {
                            lastRequestContext = { taskId: latestTaskId };
                        }
                        if (fetchVideoBtn) {
                            fetchVideoBtn.disabled = false;
                        }
                    }

                    const urls = extractVideoUrlsFromResponse(normalized);
                    if (urls.length) {
                        updateGeneratedVideos(urls, { taskId: currentTaskId });
                    }
                } catch (error) {
                    console.error('Gagal mengambil status task Kling:', error);
                    setStatus(error.message || 'Gagal mengambil status task.', 'error');
                } finally {
                    if (statusSubmitBtn) {
                        statusSubmitBtn.disabled = false;
                    }
                }
            });
        }

        clearOutputs();
    })();
    </script>
</body>
</html>
