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

        <main class="doc-main">
            <section class="doc-hero glass-card" aria-labelledby="docOverview">
                <div>
                    <h2 id="docOverview">Ringkasan Model</h2>
                    <p>Kling 2.5 Pro mendukung pembuatan video dengan kualitas premium, cocok untuk konten produk, fashion, dan kampanye UGC. Gunakan parameter tambahan seperti <code>negative_prompt</code> dan <code>cfg_scale</code> untuk memoles hasil akhir.</p>
                    <ul class="doc-hero-list">
                        <li>Durasi video tersedia: 5, 8, dan 12 detik.</li>
                        <li>Video dihasilkan secara asinkron – gunakan <code>task_id</code> untuk polling status.</li>
                        <li>Endpoint video terpisah: <code>/video</code> untuk mengambil URL MP4 final.</li>
                    </ul>
                </div>
                <div class="doc-hero-meta">
                    <div class="doc-stat">
                        <span class="doc-stat-label">HTTP Method</span>
                        <span class="doc-stat-value">POST</span>
                    </div>
                    <div class="doc-stat">
                        <span class="doc-stat-label">Endpoint</span>
                        <span class="doc-stat-value">/v1/ai/image-to-video/kling-v2-5-pro</span>
                    </div>
                    <div class="doc-stat">
                        <span class="doc-stat-label">Video Path</span>
                        <span class="doc-stat-value">{task_id}/video</span>
                    </div>
                </div>
            </section>

            <div class="doc-grid">
                <section class="doc-section glass-card" aria-labelledby="httpRequest">
                    <h2 id="httpRequest">HTTP Request</h2>
                    <div class="endpoint-card">
                        <span class="method-pill">POST</span>
                        <code>/v1/ai/image-to-video/kling-v2-5-pro</code>
                    </div>
                    <p>Kirim payload JSON ke endpoint di atas untuk memulai proses generasi video.</p>
                </section>

                <section class="doc-section glass-card" aria-labelledby="httpHeaders">
                    <h2 id="httpHeaders">Headers</h2>
                    <ul class="doc-list">
                        <li><code>Content-Type: application/json</code></li>
                        <li><code>X-Freepik-API-Key: &lt;API_KEY_AKTIF&gt;</code></li>
                        <li>Opsional: <code>Accept-Language</code> untuk preferensi bahasa notifikasi webhook.</li>
                    </ul>
                </section>
            </div>

            <section class="doc-section glass-card" aria-labelledby="requestBody">
                <h2 id="requestBody">Request Body</h2>
                <table class="doc-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Tipe</th>
                            <th>Wajib?</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>image</code></td>
                            <td>string (URL)</td>
                            <td>Ya</td>
                            <td>URL gambar publik yang akan dianimasikan.</td>
                        </tr>
                        <tr>
                            <td><code>prompt</code></td>
                            <td>string</td>
                            <td>Ya</td>
                            <td>Instruksi singkat tentang gaya atau adegan video.</td>
                        </tr>
                        <tr>
                            <td><code>negative_prompt</code></td>
                            <td>string</td>
                            <td>Tidak</td>
                            <td>Deskripsi yang ingin dihindari dari hasil.</td>
                        </tr>
                        <tr>
                            <td><code>duration</code></td>
                            <td>number</td>
                            <td>Tidak</td>
                            <td>Lama video dalam detik. Pilihan: 5, 8, atau 12.</td>
                        </tr>
                        <tr>
                            <td><code>cfg_scale</code></td>
                            <td>number</td>
                            <td>Tidak</td>
                            <td>Kontrol kekuatan prompt. Nilai rekomendasi 0.5 – 1.0.</td>
                        </tr>
                        <tr>
                            <td><code>webhook_url</code></td>
                            <td>string (URL)</td>
                            <td>Tidak</td>
                            <td>URL webhook untuk menerima notifikasi ketika video selesai.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="doc-section glass-card" aria-labelledby="exampleRequest">
                <h2 id="exampleRequest">Contoh Request (PHP cURL)</h2>
<pre class="code-block"><code class="language-php">&lt;?php
$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL =&gt; 'https://api.freepik.com/v1/ai/image-to-video/kling-v2-5-pro',
  CURLOPT_RETURNTRANSFER =&gt; true,
  CURLOPT_CUSTOMREQUEST =&gt; 'POST',
  CURLOPT_HTTPHEADER =&gt; [
    'Content-Type: application/json',
    'X-Freepik-API-Key: &lt;API_KEY_ANDA&gt;',
  ],
  CURLOPT_POSTFIELDS =&gt; json_encode([
    'webhook_url'     =&gt; 'https://example.com/webhook',
    'image'           =&gt; 'https://example.com/product.jpg',
    'prompt'          =&gt; 'Product beauty shot with dramatic lighting',
    'negative_prompt' =&gt; 'motion blur, watermark',
    'duration'        =&gt; 8,
    'cfg_scale'       =&gt; 0.7,
  ]),
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

echo $err ? 'cURL Error: ' . $err : $response;
</code></pre>
                <p class="doc-note"><strong>Tip:</strong> simpan <code>task_id</code> dari response untuk polling status dan mengambil video akhir.</p>
            </section>

            <section class="doc-section glass-card" aria-labelledby="videoDownload">
                <h2 id="videoDownload">Mengambil Video Final</h2>
                <p>Setelah status task berubah menjadi <strong>COMPLETED</strong>, panggil endpoint berikut untuk mendapatkan URL video:</p>
                <div class="endpoint-card">
                    <span class="method-pill method-pill--get">GET</span>
                    <code>/v1/ai/image-to-video/kling-v2-5-pro/{task_id}/video</code>
                </div>
                <p>Response akan berisi daftar URL yang dapat diunduh. Pastikan URL disalin atau diunduh sebelum kadaluarsa.</p>
            </section>

            <section class="doc-section glass-card" aria-labelledby="tryIt">
                <h2 id="tryIt">Coba Langsung</h2>
                <p>Gunakan form berikut untuk menguji endpoint dengan Freepik API key yang terhubung ke akun Anda.</p>
                <form id="klingForm" class="doc-form" novalidate>
                    <div class="doc-form-grid">
                        <div class="form-field">
                            <label for="imageInput">Image URL <span>*</span></label>
                            <input type="url" id="imageInput" name="image" placeholder="https://..." required>
                        </div>
                        <div class="form-field">
                            <label for="durationInput">Durasi</label>
                            <select id="durationInput" name="duration">
                                <option value="">Default (5 detik)</option>
                                <option value="5">5 detik</option>
                                <option value="8">8 detik</option>
                                <option value="12">12 detik</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="cfgInput">CFG Scale</label>
                            <input type="number" id="cfgInput" name="cfg_scale" step="0.1" min="0" max="2" placeholder="0.5">
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
                const message = (json.data && json.data.message) || json.error || 'Permintaan gagal';
                throw new Error(`HTTP ${json.status}: ${message}`);
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

                if (negative) {
                    payload.negative_prompt = negative;
                }
                if (durationRaw) {
                    const duration = Number(durationRaw);
                    if (!Number.isNaN(duration) && duration > 0) {
                        payload.duration = duration;
                    }
                }
                if (cfgRaw) {
                    const cfg = Number(cfgRaw);
                    if (!Number.isNaN(cfg)) {
                        payload.cfg_scale = cfg;
                    }
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
