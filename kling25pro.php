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
                    <div class="kling-status-card" id="klingStatusCard">
                        <div class="kling-status-card__header">
                            <div>
                                <span class="kling-status-card__label">Status &amp; Preview</span>
                                <h4 class="kling-status-card__title" id="klingStatusTitle">Belum ada task aktif</h4>
                            </div>
                            <button type="button" class="kling-status-clear" id="klingStatusClear" hidden>Clear preview</button>
                        </div>
                        <div class="kling-status-card__body">
                            <div class="kling-status-state">
                                <span class="kling-status-badge" id="klingStatusBadge" data-state="idle">Idle</span>
                                <span class="kling-status-meta" id="klingStatusMeta">Mulai dengan mengirim task baru atau cek status task.</span>
                            </div>
                            <div class="kling-status-progress" id="klingStatusProgress" hidden>
                                <div class="kling-status-progress__bar">
                                    <span class="kling-status-progress__fill" id="klingStatusProgressFill" style="width: 0%"></span>
                                </div>
                                <div class="kling-status-progress__text" id="klingStatusProgressText">0%</div>
                            </div>
                            <div class="kling-status-queue" id="klingStatusQueue">Tidak ada antrean aktif.</div>
                            <div class="kling-status-preview" id="klingStatusPreview">
                                <div class="kling-status-preview__empty">Belum ada preview video.</div>
                            </div>
                            <div class="kling-status-actions" id="klingStatusActions"></div>
                        </div>
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
        const statusCard = document.getElementById('klingStatusCard');
        const statusTitle = document.getElementById('klingStatusTitle');
        const statusBadge = document.getElementById('klingStatusBadge');
        const statusMeta = document.getElementById('klingStatusMeta');
        const statusProgress = document.getElementById('klingStatusProgress');
        const statusProgressFill = document.getElementById('klingStatusProgressFill');
        const statusProgressText = document.getElementById('klingStatusProgressText');
        const statusQueue = document.getElementById('klingStatusQueue');
        const statusPreview = document.getElementById('klingStatusPreview');
        const statusActions = document.getElementById('klingStatusActions');
        const statusClearBtn = document.getElementById('klingStatusClear');

        let currentTaskId = null;
        let lastRequestContext = null;
        let statusPreviewEntryId = null;
        let lastStatusSnapshot = null;
        let webhookChannel = null;
        const generatedVideos = [];
        const autoVideoFetch = new Map();
        const DRIVE_ENDPOINT = 'index.php?api=drive';
        const STATUS_POLL_INTERVAL = 4500;
        let statusPollTimer = null;
        let statusPollTaskId = null;
        let statusPollInflight = false;
        let statusProgressHideTimeout = null;
        const statusProgressAnim = {
            timer: null,
            taskId: null,
            value: 0
        };

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

        function formatDisplayDate(value) {
            if (!value) {
                return '';
            }

            try {
                const date = value instanceof Date ? value : new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return typeof value === 'string' ? value : '';
                }

                return date.toLocaleString('id-ID', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (err) {
                return typeof value === 'string' ? value : '';
            }
        }

        function isFinalStatus(value) {
            if (!value) {
                return false;
            }
            const normalized = String(value).toUpperCase();
            return ['COMPLETE', 'COMPLETED', 'SUCCEEDED', 'SUCCESS', 'FAILED', 'FAILURE', 'ERROR', 'CANCELLED', 'CANCELED'].includes(normalized);
        }

        function determineBadgeState(status, { final = false } = {}) {
            const normalized = status ? String(status).toUpperCase() : '';
            if (!normalized) {
                return 'idle';
            }
            if (['FAILED', 'FAILURE', 'ERROR', 'CANCELLED', 'CANCELED'].includes(normalized)) {
                return 'error';
            }
            if (['COMPLETE', 'COMPLETED', 'SUCCEEDED', 'SUCCESS'].includes(normalized)) {
                return 'success';
            }
            if (['QUEUED', 'QUEUING', 'QUEUE', 'WAITING'].includes(normalized)) {
                return 'warning';
            }
            if (final) {
                return 'success';
            }
            return 'progress';
        }

        function parseProgressValue(source) {
            if (!source || typeof source !== 'object') {
                return null;
            }

            const candidates = [];
            ['progress', 'progress_percent', 'progressPercent', 'progress_percentage', 'percentage', 'percent'].forEach(key => {
                if (source[key] != null && source[key] !== '') {
                    candidates.push(source[key]);
                }
            });

            if (source.metrics && typeof source.metrics === 'object') {
                const metrics = source.metrics;
                ['progress', 'percent', 'percentage'].forEach(key => {
                    if (metrics[key] != null && metrics[key] !== '') {
                        candidates.push(metrics[key]);
                    }
                });
            }

            let progress = null;
            for (const candidate of candidates) {
                if (typeof candidate === 'number' && Number.isFinite(candidate)) {
                    progress = candidate;
                    break;
                }
                const parsed = Number(candidate);
                if (!Number.isNaN(parsed)) {
                    progress = parsed;
                    break;
                }
            }

            if (progress == null) {
                return null;
            }

            if (progress <= 1) {
                progress = progress * 100;
            }

            progress = Math.max(0, Math.min(100, progress));
            return {
                value: progress,
                label: `${Math.round(progress)}%`
            };
        }

        function formatSecondsToDisplay(value) {
            const seconds = Number(value);
            if (!Number.isFinite(seconds) || seconds <= 0) {
                return '';
            }
            if (seconds < 60) {
                return `${Math.round(seconds)} detik`;
            }
            const minutes = seconds / 60;
            if (minutes < 60) {
                return `${Math.round(minutes)} menit`;
            }
            const hours = minutes / 60;
            return `${hours.toFixed(1)} jam`;
        }

        function buildQueueSummary(source) {
            if (!source || typeof source !== 'object') {
                return { summary: '', active: false };
            }

            const queueSources = [];
            ['queue', 'queue_info', 'queueStatus', 'queue_status', 'status_details'].forEach(key => {
                if (source[key] && typeof source[key] === 'object') {
                    queueSources.push(source[key]);
                }
            });

            const details = {};
            queueSources.forEach(item => {
                if (!item || typeof item !== 'object') {
                    return;
                }
                Object.entries(item).forEach(([key, value]) => {
                    if (details[key] == null) {
                        details[key] = value;
                    }
                });
            });

            const pieces = [];
            const status = details.status || details.state || source.queue_status || source.queueState;
            if (status) {
                pieces.push(String(status).toUpperCase());
            }

            const position = details.position ?? details.queue_position ?? source.queue_position ?? source.queuePosition ?? source.position;
            if (position != null && position !== '') {
                pieces.push(`Posisi #${position}`);
            }

            const etaSeconds = details.eta_seconds ?? details.eta ?? details.wait_time ?? source.eta ?? source.wait_time;
            const etaText = formatSecondsToDisplay(etaSeconds);
            if (etaText) {
                pieces.push(`ETA ${etaText}`);
            }

            const estimatedStart = details.starts_in ?? details.start_in ?? null;
            const estimatedStartText = formatSecondsToDisplay(estimatedStart);
            if (estimatedStartText) {
                pieces.push(`Mulai dalam ${estimatedStartText}`);
            }

            const queueActive = pieces.length > 0 || (status && String(status).toUpperCase() !== 'IDLE');
            const summary = pieces.length ? `Job aktif — ${pieces.join(' • ')}` : (queueActive ? 'Job aktif. Menunggu giliran eksekusi.' : 'Tidak ada antrean aktif.');

            return {
                summary,
                active: queueActive
            };
        }

        function hideStatusProgressBar() {
            if (!statusProgress) {
                return;
            }
            statusProgress.hidden = true;
            statusProgress.dataset.state = 'idle';
            if (statusProgressFill) {
                statusProgressFill.style.width = '0%';
            }
            if (statusProgressText) {
                statusProgressText.textContent = '0%';
            }
        }

        function setStatusProgressValue(value, label) {
            if (!statusProgress) {
                return;
            }

            const numeric = Math.max(0, Math.min(100, Number(value) || 0));
            if (statusProgressHideTimeout) {
                clearTimeout(statusProgressHideTimeout);
                statusProgressHideTimeout = null;
            }

            statusProgress.hidden = false;
            statusProgress.dataset.state = numeric >= 100 ? 'complete' : 'running';

            if (statusProgressFill) {
                statusProgressFill.style.width = `${numeric}%`;
            }
            if (statusProgressText) {
                statusProgressText.textContent = label || `${Math.round(numeric)}%`;
            }

            statusProgressAnim.value = numeric;
        }

        function resetStatusProgress() {
            if (statusProgressAnim.timer) {
                clearInterval(statusProgressAnim.timer);
                statusProgressAnim.timer = null;
            }
            statusProgressAnim.taskId = null;
            statusProgressAnim.value = 0;
            if (statusProgressHideTimeout) {
                clearTimeout(statusProgressHideTimeout);
                statusProgressHideTimeout = null;
            }
            hideStatusProgressBar();
        }

        function ensureStatusProgressAnimation(taskId) {
            if (!statusProgress) {
                return;
            }

            if (!taskId) {
                resetStatusProgress();
                return;
            }

            if (statusProgressAnim.taskId !== taskId) {
                if (statusProgressAnim.timer) {
                    clearInterval(statusProgressAnim.timer);
                }
                statusProgressAnim.timer = null;
                statusProgressAnim.taskId = taskId;
                statusProgressAnim.value = 8;
            } else if (!statusProgressAnim.value || statusProgressAnim.value < 8) {
                statusProgressAnim.value = 8;
            }

            setStatusProgressValue(statusProgressAnim.value, `${Math.round(statusProgressAnim.value)}%`);

            if (!statusProgressAnim.timer) {
                statusProgressAnim.timer = setInterval(() => {
                    if (!statusProgressAnim.taskId || statusProgressAnim.taskId !== taskId) {
                        resetStatusProgress();
                        return;
                    }
                    const next = Math.min(92, (statusProgressAnim.value || 0) + (Math.random() * 6 + 4));
                    statusProgressAnim.value = next;
                    setStatusProgressValue(next, `${Math.round(next)}%`);
                }, 1600);
            }
        }

        function syncStatusProgressValue(value, label, { final = false, taskId = null } = {}) {
            if (!statusProgress) {
                return;
            }

            if (statusProgressAnim.timer) {
                clearInterval(statusProgressAnim.timer);
                statusProgressAnim.timer = null;
            }

            if (taskId) {
                statusProgressAnim.taskId = taskId;
            }

            setStatusProgressValue(value, label);

            if (final) {
                statusProgressAnim.taskId = null;
                statusProgressHideTimeout = setTimeout(() => {
                    hideStatusProgressBar();
                }, 1500);
            }
        }

        function stopStatusPolling(taskId = null) {
            if (statusPollTimer) {
                clearInterval(statusPollTimer);
                statusPollTimer = null;
            }
            if (!taskId || statusPollTaskId === taskId) {
                statusPollTaskId = null;
            }
            statusPollInflight = false;
        }

        function startStatusPolling(taskId, { immediate = false } = {}) {
            if (!taskId) {
                return;
            }

            if (statusPollTaskId === taskId && statusPollTimer) {
                return;
            }

            if (statusPollTimer) {
                clearInterval(statusPollTimer);
                statusPollTimer = null;
            }

            statusPollTaskId = taskId;

            const poll = async () => {
                if (!statusPollTaskId || statusPollTaskId !== taskId) {
                    return;
                }
                if (statusPollInflight) {
                    return;
                }

                statusPollInflight = true;
                try {
                    const data = await callFreepikEndpoint({
                        path: `/v1/ai/image-to-video/kling-v2-5-pro/${encodeURIComponent(taskId)}`,
                        method: 'GET'
                    });

                    renderJson(statusPayloadEl, data);
                    const normalized = normalizeData(data);
                    handleTaskStatus(normalized, { source: 'poll', taskId });
                } catch (err) {
                    console.warn('Gagal polling status Kling:', err);
                } finally {
                    statusPollInflight = false;
                }
            };

            if (immediate) {
                poll();
            }

            statusPollTimer = setInterval(poll, STATUS_POLL_INTERVAL);
        }

        function clearStatusPreviewMedia() {
            if (!statusPreview) {
                return;
            }
            const video = statusPreview.querySelector('video');
            if (video && typeof video.pause === 'function') {
                try {
                    video.pause();
                } catch (err) {
                    // ignore
                }
            }
            statusPreview.innerHTML = '<div class="kling-status-preview__empty">Belum ada preview video.</div>';
            if (statusActions) {
                statusActions.innerHTML = '';
            }
            statusPreviewEntryId = null;
            if (statusClearBtn) {
                statusClearBtn.hidden = true;
            }
        }

        function resetStatusPreview() {
            if (!statusCard) {
                return;
            }
            if (statusTitle) {
                statusTitle.textContent = 'Belum ada task aktif';
            }
            if (statusBadge) {
                statusBadge.textContent = 'Idle';
                statusBadge.dataset.state = 'idle';
            }
            if (statusMeta) {
                statusMeta.textContent = 'Mulai dengan mengirim task baru atau cek status task.';
            }
            resetStatusProgress();
            stopStatusPolling();
            if (statusQueue) {
                statusQueue.textContent = 'Tidak ada antrean aktif.';
                statusQueue.dataset.active = 'false';
            }
            clearStatusPreviewMedia();
            lastStatusSnapshot = null;
        }

        function refreshStatusPreviewEntry() {
            if (!statusPreviewEntryId) {
                return;
            }
            const entry = generatedVideos.find(item => item.id === statusPreviewEntryId);
            renderStatusPreviewEntry(entry || null);
        }

        function renderStatusSnapshot(snapshot = {}) {
            if (!statusCard) {
                return;
            }

            if (statusTitle) {
                statusTitle.textContent = snapshot.taskId ? `Task ${snapshot.taskId}` : 'Status task';
            }

            if (statusBadge) {
                const badgeState = determineBadgeState(snapshot.status, { final: snapshot.final });
                statusBadge.dataset.state = badgeState;
                statusBadge.textContent = snapshot.status ? String(snapshot.status).toUpperCase() : 'UNKNOWN';
            }

            if (statusMeta) {
                statusMeta.textContent = snapshot.message || (snapshot.final ? 'Task selesai. Ambil video di bawah.' : 'Pantau progres task secara otomatis.');
            }

            if (snapshot.progress && typeof snapshot.progress.value === 'number') {
                syncStatusProgressValue(snapshot.progress.value, snapshot.progress.label, {
                    final: !!snapshot.final,
                    taskId: snapshot.taskId || statusProgressAnim.taskId || null
                });
            } else if (!snapshot.final && snapshot.taskId) {
                ensureStatusProgressAnimation(snapshot.taskId);
            } else if (snapshot.final) {
                const finalValue = snapshot.progress && typeof snapshot.progress.value === 'number'
                    ? snapshot.progress.value
                    : 100;
                const finalLabel = snapshot.progress && snapshot.progress.label ? snapshot.progress.label : `${Math.round(finalValue)}%`;
                syncStatusProgressValue(finalValue, finalLabel, { final: true, taskId: snapshot.taskId || null });
            } else {
                resetStatusProgress();
            }

            if (statusQueue) {
                statusQueue.textContent = snapshot.queueSummary || 'Tidak ada antrean aktif.';
                statusQueue.dataset.active = snapshot.queueActive ? 'true' : 'false';
            }

            if (statusClearBtn) {
                statusClearBtn.hidden = !statusPreviewEntryId;
            }

            statusCard.dataset.state = snapshot.final ? 'final' : 'active';
        }

        function renderStatusPreviewEntry(entry) {
            if (!statusPreview) {
                return;
            }

            if (!entry) {
                clearStatusPreviewMedia();
                return;
            }

            statusPreviewEntryId = entry.id;
            statusPreview.innerHTML = '';

            const video = document.createElement('video');
            video.src = entry.url;
            video.controls = true;
            video.loop = true;
            video.autoplay = true;
            video.muted = true;
            video.playsInline = true;
            video.className = 'kling-status-preview__video';
            statusPreview.appendChild(video);

            if (statusActions) {
                statusActions.innerHTML = '';

                const previewBtn = document.createElement('button');
                previewBtn.type = 'button';
                previewBtn.className = 'kling-ugc-button kling-ugc-button--ghost';
                previewBtn.textContent = 'Preview';
                previewBtn.addEventListener('click', () => openPreview(entry.url));
                statusActions.appendChild(previewBtn);

                const copyBtn = document.createElement('button');
                copyBtn.type = 'button';
                copyBtn.className = 'kling-ugc-button kling-ugc-button--ghost';
                copyBtn.textContent = 'Copy Link';
                copyBtn.addEventListener('click', async () => {
                    try {
                        await attemptCopyToClipboard(entry.url);
                        setStatus('Link video berhasil disalin.', 'success');
                    } catch (err) {
                        console.error('Gagal menyalin link video:', err);
                        setStatus('Tidak dapat menyalin link video.', 'error');
                    }
                });
                statusActions.appendChild(copyBtn);

                const downloadLink = document.createElement('a');
                downloadLink.href = entry.url;
                downloadLink.target = '_blank';
                downloadLink.rel = 'noopener noreferrer';
                downloadLink.download = '';
                downloadLink.className = 'kling-ugc-button';
                downloadLink.textContent = 'Download';
                statusActions.appendChild(downloadLink);

                const saveBtn = document.createElement('button');
                saveBtn.type = 'button';
                saveBtn.className = 'kling-ugc-button kling-ugc-button--primary';
                if (entry.saved) {
                    saveBtn.textContent = 'Tersimpan ✓';
                    saveBtn.disabled = true;
                } else if (entry.saving) {
                    saveBtn.textContent = 'Menyimpan…';
                    saveBtn.disabled = true;
                } else {
                    saveBtn.textContent = 'Simpan ke Drive';
                }
                saveBtn.addEventListener('click', () => saveKlingVideoToDrive(entry));
                statusActions.appendChild(saveBtn);
            }

            if (statusClearBtn) {
                statusClearBtn.hidden = false;
            }
        }

        async function attemptCopyToClipboard(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
                return;
            }
            return new Promise((resolve, reject) => {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    const successful = document.execCommand('copy');
                    document.body.removeChild(textarea);
                    if (successful) {
                        resolve();
                    } else {
                        reject(new Error('Clipboard tidak tersedia'));
                    }
                } catch (err) {
                    document.body.removeChild(textarea);
                    reject(err);
                }
            });
        }

        function buildStatusSnapshot(payload, context = {}) {
            if (!payload || typeof payload !== 'object') {
                return null;
            }

            const normalized = normalizeData(payload) || {};
            const taskId = context.taskId || normalized.task_id || normalized.taskId || normalized.id || currentTaskId || null;
            const status = normalized.status || normalized.state || normalized.task_status || normalized.result_status || null;
            let progress = parseProgressValue(normalized);
            const queueDetails = buildQueueSummary(normalized);

            const message = normalized.message
                || normalized.status_message
                || normalized.detail
                || normalized.reason
                || normalized.error
                || '';

            let urls = extractVideoUrlsFromResponse(normalized);
            if ((!urls || !urls.length) && Array.isArray(normalized.generated)) {
                urls = extractVideoUrlsFromResponse(normalized.generated);
            }

            const final = isFinalStatus(status) || (urls && urls.length > 0);

            if ((!progress || typeof progress.value !== 'number' || progress.value < 99) && final) {
                progress = { value: 100, label: '100%' };
            }

            return {
                taskId,
                status,
                progress,
                queueSummary: queueDetails.summary,
                queueActive: queueDetails.active,
                message,
                previewUrls: urls || [],
                final,
                source: context.source || 'manual'
            };
        }

        function handleTaskStatus(payload, context = {}) {
            const snapshot = buildStatusSnapshot(payload, context);
            if (!snapshot) {
                return { snapshot: null, entries: [] };
            }

            lastStatusSnapshot = snapshot;
            renderStatusSnapshot(snapshot);

            let entries = [];
            if (snapshot.previewUrls && snapshot.previewUrls.length) {
                const details = getContextDetails({ taskId: snapshot.taskId });
                entries = updateGeneratedVideos(snapshot.previewUrls, details);
                if (entries.length) {
                    renderStatusPreviewEntry(entries[0]);
                }
                if (snapshot.taskId) {
                    autoVideoFetch.delete(snapshot.taskId);
                }
            } else if (snapshot.final) {
                refreshStatusPreviewEntry();
                if (snapshot.taskId) {
                    scheduleAutoVideoFetch(snapshot.taskId);
                }
            } else if (snapshot.taskId) {
                autoVideoFetch.delete(snapshot.taskId);
            }

            if (snapshot.taskId) {
                if (snapshot.final) {
                    stopStatusPolling(snapshot.taskId);
                } else {
                    const shouldImmediate = context && context.source === 'create';
                    startStatusPolling(snapshot.taskId, { immediate: shouldImmediate });
                }
            } else if (snapshot.final) {
                stopStatusPolling();
            }

            return { snapshot, entries };
        }

        function scheduleAutoVideoFetch(taskId) {
            if (!taskId) {
                return;
            }

            const existing = autoVideoFetch.get(taskId);
            if (existing && (existing.fetching || existing.completed)) {
                return;
            }

            autoVideoFetch.set(taskId, { fetching: false, completed: false });

            fetchVideoForTask(taskId, { auto: true }).catch(err => {
                console.warn('Auto fetch video Kling gagal:', err);
            });
        }

        function processWebhookPayload(payload) {
            if (!payload) {
                return;
            }

            let data = payload;
            if (payload.data && typeof payload.data === 'object') {
                data = payload.data;
            }

            if (data.model && !/kling/i.test(String(data.model))) {
                return;
            }

            const taskId = data.task_id || data.taskId || payload.task_id || payload.taskId || currentTaskId || null;
            const result = handleTaskStatus(data, { source: 'webhook', taskId });

            if (result && result.entries && result.entries.length) {
                setStatus('Webhook menerima hasil video terbaru.', 'success');
            } else if (result && result.snapshot && result.snapshot.status) {
                setStatus(`Webhook update: status ${String(result.snapshot.status).toUpperCase()}.`, 'info');
            }
        }

        function setupWebhookListeners() {
            try {
                webhookChannel = new BroadcastChannel('freepik:kling-webhook');
                webhookChannel.addEventListener('message', event => {
                    if (!event) {
                        return;
                    }
                    const { data } = event;
                    if (!data) {
                        return;
                    }
                    if (data.type && !String(data.type).toLowerCase().includes('kling')) {
                        return;
                    }
                    processWebhookPayload(data.payload || data);
                });
            } catch (err) {
                console.warn('BroadcastChannel tidak tersedia untuk webhook Kling:', err);
            }

            window.addEventListener('message', event => {
                if (!event || !event.data) {
                    return;
                }
                const payload = event.data;
                if (payload && payload.__klingWebhook) {
                    processWebhookPayload(payload.data || payload.payload || payload);
                }
            });

            window.klingWebhookDebug = processWebhookPayload;
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
                const row = document.createElement('div');
                row.className = 'kling-ugc-row';

                const mediaColumn = document.createElement('div');
                mediaColumn.className = 'kling-ugc-media';

                const imageCard = document.createElement('div');
                imageCard.className = 'kling-ugc-image-card';
                const imageUrl = ensureAbsoluteUrl(entry.image);
                if (imageUrl && /^https?:\/\//i.test(imageUrl)) {
                    const img = document.createElement('img');
                    img.src = imageUrl;
                    img.alt = 'Image prompt reference';
                    img.loading = 'lazy';
                    img.className = 'kling-ugc-thumb';
                    imageCard.appendChild(img);
                } else {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'kling-ugc-placeholder';
                    placeholder.innerHTML = 'Tidak ada image reference<br><span>Tambahkan URL gambar untuk melihat thumbnail.</span>';
                    imageCard.appendChild(placeholder);
                }
                mediaColumn.appendChild(imageCard);

                const videoCard = document.createElement('div');
                videoCard.className = 'kling-ugc-video-card';

                const videoTitle = document.createElement('div');
                videoTitle.className = 'kling-ugc-media-title';
                videoTitle.textContent = 'Video Kling';
                videoCard.appendChild(videoTitle);

                const video = document.createElement('video');
                video.src = entry.url;
                video.controls = true;
                video.loop = true;
                video.muted = true;
                video.playsInline = true;
                video.preload = 'metadata';
                video.className = 'kling-ugc-video';
                videoCard.appendChild(video);

                const actions = document.createElement('div');
                actions.className = 'kling-ugc-actions';

                const previewBtn = document.createElement('button');
                previewBtn.type = 'button';
                previewBtn.className = 'kling-ugc-button kling-ugc-button--ghost';
                previewBtn.textContent = 'Preview Video';
                previewBtn.addEventListener('click', () => openPreview(entry.url));
                actions.appendChild(previewBtn);

                const downloadLink = document.createElement('a');
                downloadLink.href = entry.url;
                downloadLink.target = '_blank';
                downloadLink.rel = 'noopener noreferrer';
                downloadLink.download = '';
                downloadLink.className = 'kling-ugc-button';
                downloadLink.textContent = 'Download';
                actions.appendChild(downloadLink);

                const saveBtn = document.createElement('button');
                saveBtn.type = 'button';
                saveBtn.className = 'kling-ugc-button kling-ugc-button--primary';
                saveBtn.textContent = entry.saved ? 'Tersimpan ✓' : (entry.saving ? 'Menyimpan…' : 'Simpan ke Drive');
                saveBtn.disabled = entry.saved || entry.saving;
                saveBtn.addEventListener('click', () => saveKlingVideoToDrive(entry));
                actions.appendChild(saveBtn);

                videoCard.appendChild(actions);
                mediaColumn.appendChild(videoCard);

                row.appendChild(mediaColumn);

                const infoColumn = document.createElement('div');
                infoColumn.className = 'kling-ugc-info';

                const header = document.createElement('div');
                header.className = 'kling-ugc-header';

                const title = document.createElement('div');
                title.className = 'kling-ugc-title';
                title.textContent = `Video ${index + 1}`;
                header.appendChild(title);

                if (entry.taskId) {
                    const badge = document.createElement('span');
                    badge.className = 'kling-ugc-badge';
                    badge.textContent = entry.taskId;
                    header.appendChild(badge);
                }

                infoColumn.appendChild(header);

                const chipGroup = document.createElement('div');
                chipGroup.className = 'kling-ugc-chip-group';
                if (entry.duration) {
                    const chip = document.createElement('span');
                    chip.className = 'kling-ugc-chip';
                    chip.textContent = `${entry.duration}s`;
                    chipGroup.appendChild(chip);
                }
                if (entry.cfgScale != null) {
                    const chip = document.createElement('span');
                    chip.className = 'kling-ugc-chip';
                    chip.textContent = `CFG ${entry.cfgScale}`;
                    chipGroup.appendChild(chip);
                }
                if (entry.createdAt) {
                    const createdText = formatDisplayDate(entry.createdAt);
                    if (createdText) {
                        const chip = document.createElement('span');
                        chip.className = 'kling-ugc-chip';
                        chip.textContent = `Dibuat ${createdText}`;
                        chipGroup.appendChild(chip);
                    }
                }
                if (chipGroup.children.length) {
                    infoColumn.appendChild(chipGroup);
                }

                const promptBlock = document.createElement('div');
                promptBlock.className = 'kling-ugc-block';
                const promptLabel = document.createElement('div');
                promptLabel.className = 'kling-ugc-label';
                promptLabel.textContent = 'Prompt';
                const promptText = document.createElement('div');
                promptText.className = 'kling-ugc-text';
                if (entry.prompt) {
                    promptText.textContent = entry.prompt;
                } else {
                    promptText.textContent = 'Prompt tidak tersedia.';
                    promptText.classList.add('is-muted');
                }
                promptBlock.appendChild(promptLabel);
                promptBlock.appendChild(promptText);
                infoColumn.appendChild(promptBlock);

                if (entry.negativePrompt) {
                    const negativeBlock = document.createElement('div');
                    negativeBlock.className = 'kling-ugc-block';
                    const negativeLabel = document.createElement('div');
                    negativeLabel.className = 'kling-ugc-label';
                    negativeLabel.textContent = 'Negative Prompt';
                    const negativeText = document.createElement('div');
                    negativeText.className = 'kling-ugc-text';
                    negativeText.textContent = entry.negativePrompt;
                    negativeBlock.appendChild(negativeLabel);
                    negativeBlock.appendChild(negativeText);
                    infoColumn.appendChild(negativeBlock);
                }

                const statusLine = document.createElement('div');
                statusLine.className = 'kling-ugc-status';
                statusLine.dataset.state = entry.error ? 'error' : (entry.saved ? 'success' : (entry.saving ? 'info' : 'muted'));
                if (entry.error) {
                    statusLine.textContent = entry.error;
                } else if (entry.saved) {
                    const savedText = formatDisplayDate(entry.savedAt);
                    statusLine.textContent = savedText ? `Tersimpan ke drive (${savedText})` : 'Tersimpan ke drive';
                } else if (entry.saving) {
                    statusLine.textContent = 'Menyimpan video ke drive…';
                } else {
                    statusLine.textContent = 'Belum disimpan ke drive';
                }
                infoColumn.appendChild(statusLine);

                row.appendChild(infoColumn);
                resultsGrid.appendChild(row);
            });

            refreshStatusPreviewEntry();
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
            const entries = [];
            if (Array.isArray(urls)) {
                urls.forEach(url => {
                    const entry = upsertGeneratedVideo(url, context);
                    if (entry) {
                        entries.push(entry);
                    }
                });
            }

            renderVideoCards();

            generatedVideos
                .filter(entry => entry.autoSave && !entry.saved && !entry.saving)
                .forEach(entry => saveKlingVideoToDrive(entry, { auto: true }));

            return entries;
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
                refreshStatusPreviewEntry();
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

        if (statusClearBtn) {
            statusClearBtn.addEventListener('click', () => {
                clearStatusPreviewMedia();
                if (lastStatusSnapshot) {
                    renderStatusSnapshot(lastStatusSnapshot);
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
            resetStatusPreview();
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
            const trimmed = typeof text === 'string' ? text.trim() : '';

            if (trimmed) {
                try {
                    json = JSON.parse(trimmed);
                } catch (err) {
                    const firstBrace = trimmed.indexOf('{');
                    const lastBrace = trimmed.lastIndexOf('}');
                    if (firstBrace !== -1 && lastBrace !== -1 && lastBrace > firstBrace) {
                        const candidate = trimmed.slice(firstBrace, lastBrace + 1);
                        try {
                            json = JSON.parse(candidate);
                        } catch (innerErr) {
                            console.warn('Gagal parse JSON dari kandidat substring:', candidate);
                        }
                    }

                    if (!json) {
                        console.warn('Response bukan JSON valid:', trimmed);
                        json = {
                            ok: false,
                            status: res.status,
                            error: 'Server mengembalikan respon non-JSON.',
                            data: trimmed
                        };
                    }
                }
            }

            if (!json) {
                json = {
                    ok: false,
                    status: res.status,
                    error: 'Server tidak mengembalikan data.',
                    data: null
                };
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
                    handleTaskStatus(normalized, { source: 'create', taskId });

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

        async function fetchVideoForTask(taskId, { auto = false } = {}) {
            if (!taskId) {
                if (!auto) {
                    setStatus('Task ID belum tersedia. Kirim request terlebih dahulu.', 'error');
                }
                return null;
            }

            let autoMarker = null;
            if (auto) {
                autoMarker = autoVideoFetch.get(taskId) || { fetching: false, completed: false };
                if (autoMarker.fetching || autoMarker.completed) {
                    return null;
                }
                autoMarker = { ...autoMarker, fetching: true };
                autoVideoFetch.set(taskId, autoMarker);
            }

            if (!auto) {
                setStatus('Mengambil URL video...', 'info');
            }

            if (fetchVideoBtn && !auto) {
                fetchVideoBtn.disabled = true;
            }

            try {
                const data = await callFreepikEndpoint({
                    path: `/v1/ai/image-to-video/kling-v2-5-pro/${encodeURIComponent(taskId)}/video`,
                    method: 'GET'
                });

                const normalized = normalizeData(data);
                renderResponse(data);
                const result = handleTaskStatus(normalized, { source: auto ? 'auto-video' : 'video', taskId });
                const hasUrls = result && result.entries && result.entries.length;

                if (hasUrls) {
                    if (auto) {
                        autoVideoFetch.set(taskId, { fetching: false, completed: true });
                        setStatus('Video selesai diproses dan dimuat otomatis.', 'success');
                    } else {
                        setStatus('URL video berhasil diambil.', 'success');
                    }
                } else if (!auto) {
                    setStatus('Video belum tersedia, coba lagi beberapa saat lagi.', 'warning');
                    if (fetchVideoBtn) {
                        fetchVideoBtn.disabled = false;
                    }
                }

                return hasUrls;
            } catch (error) {
                console.error('Gagal mengambil video Kling:', error);
                if (!auto) {
                    setStatus(error.message || 'Gagal mengambil video.', 'error');
                } else {
                    setStatus('Gagal memuat video otomatis. Silakan ambil manual.', 'error');
                }
                if (fetchVideoBtn && !auto) {
                    fetchVideoBtn.disabled = false;
                }
                if (auto) {
                    autoVideoFetch.set(taskId, { fetching: false, completed: false });
                }
                throw error;
            } finally {
                if (auto) {
                    const marker = autoVideoFetch.get(taskId) || {};
                    autoVideoFetch.set(taskId, { ...marker, fetching: false });
                }
                if (fetchVideoBtn && !auto) {
                    fetchVideoBtn.disabled = !currentTaskId;
                }
            }
        }

        if (fetchVideoBtn) {
            fetchVideoBtn.addEventListener('click', () => {
                fetchVideoForTask(currentTaskId, { auto: false });
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

                    handleTaskStatus(normalized, { source: 'status', taskId: latestTaskId || taskId });
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

        setupWebhookListeners();
        clearOutputs();
    })();
    </script>
</body>
</html>
