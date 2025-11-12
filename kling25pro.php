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
                <div class="doc-result" id="videoResult">
                    <div class="doc-result__header">
                        <h3>Video URLs</h3>
                        <button type="button" class="btn-primary" id="fetchVideoBtn" disabled>Ambil Video</button>
                    </div>
                    <ul class="doc-video-list" id="videoList"></ul>
                </div>
            </section>
        </main>
    </div>

    <script>
    (() => {
        const form = document.getElementById('klingForm');
        const statusEl = document.getElementById('docStatus');
        const responseEl = document.getElementById('responsePayload');
        const fetchVideoBtn = document.getElementById('fetchVideoBtn');
        const videoList = document.getElementById('videoList');
        const resetBtn = document.getElementById('resetBtn');
        const submitBtn = document.getElementById('submitBtn');

        let currentTaskId = null;

        function setStatus(message, type = 'info') {
            if (!statusEl) return;
            statusEl.textContent = message || '';
            statusEl.dataset.state = type;
        }

        function clearOutputs() {
            if (responseEl) {
                responseEl.textContent = '{ }\n';
            }
            if (videoList) {
                videoList.innerHTML = '';
            }
            if (fetchVideoBtn) {
                fetchVideoBtn.disabled = true;
            }
            currentTaskId = null;
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

        function renderResponse(payload) {
            if (!responseEl) return;
            try {
                responseEl.textContent = JSON.stringify(payload, null, 2);
            } catch (err) {
                responseEl.textContent = String(payload || '');
            }
        }

        function renderVideos(urls) {
            if (!videoList) return;
            videoList.innerHTML = '';
            if (!Array.isArray(urls) || !urls.length) {
                const empty = document.createElement('li');
                empty.className = 'doc-video-empty';
                empty.textContent = 'Belum ada URL video yang tersedia.';
                videoList.appendChild(empty);
                return;
            }

            urls.forEach((url, index) => {
                const item = document.createElement('li');
                item.className = 'doc-video-item';

                const label = document.createElement('div');
                label.className = 'doc-video-label';
                label.textContent = `Video ${index + 1}`;
                item.appendChild(label);

                const link = document.createElement('a');
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = url;
                link.className = 'doc-video-link';
                item.appendChild(link);

                videoList.appendChild(item);
            });
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

                clearOutputs();
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
                        if (fetchVideoBtn) {
                            fetchVideoBtn.disabled = false;
                        }
                        setStatus(`Permintaan diterima. Task ID: ${taskId}`, 'success');
                    } else {
                        currentTaskId = null;
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
                    renderVideos(urls);

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
                }
            });
        }

        clearOutputs();
    })();
    </script>
</body>
</html>
