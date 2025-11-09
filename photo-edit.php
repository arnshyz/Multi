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
if ($account) {
    $username = trim((string)($account['name'] ?? ''));
    if ($username === '') {
        $username = trim((string)($account['username'] ?? ''));
    }
}
if ($username === '') {
    $username = 'Pengguna';
}

?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flash 2.5 Photo Edit Studio</title>
    <meta name="description" content="Edit foto dengan Gemini Flash 2.5 dan hasilkan empat pose berbeda dari satu sesi.">
    <link rel="icon" type="image/png" href="/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="photo-edit.css">
</head>
<body>
    <div class="app-shell">
        <header class="app-header">
            <div class="title-group">
                <span class="badge badge-primary">Flash 2.5</span>
                <h1>Photo Edit Studio</h1>
                <p>Ubah kumpulan foto referensi menjadi empat pose unik menggunakan model <strong>Gemini Flash 2.5</strong>.</p>
            </div>
            <div class="header-actions">
                <div class="user-chip" aria-label="Akun aktif">
                    <span class="avatar-circle" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($username, 0, 2))); ?></span>
                    <span class="user-meta">
                        <span class="user-label">Masuk sebagai</span>
                        <span class="user-name"><?php echo htmlspecialchars($username, ENT_QUOTES); ?></span>
                    </span>
                </div>
                <a href="index.php" class="btn-secondary">← Kembali ke Dashboard</a>
            </div>
        </header>

        <main class="app-main">
            <section class="panel panel-controls" aria-labelledby="formTitle">
                <div class="panel-header">
                    <div>
                        <h2 id="formTitle">Setelan Generasi</h2>
                        <p>Pilih kategori prompt, unggah beberapa foto referensi, dan jalankan sesi edit Flash 2.5.</p>
                    </div>
                </div>

                <form id="editForm" class="edit-form" novalidate>
                    <div class="form-field">
                        <label class="field-label">Model yang digunakan</label>
                        <div class="model-chip" role="text">Gemini Flash 2.5 · Multi Pose</div>
                        <p class="field-hint">Model dikunci ke Flash 2.5 untuk konsistensi hasil edit.</p>
                    </div>

                    <div class="form-field">
                        <label for="styleSelect" class="field-label">Kategori prompt style</label>
                        <select id="styleSelect" class="field-select" aria-describedby="styleHint">
                            <option value="wedding">Pernikahan</option>
                            <option value="vacation">Liburan</option>
                            <option value="office">Kerja</option>
                            <option value="akad">Akad Nikah</option>
                        </select>
                        <p id="styleHint" class="field-hint"></p>
                    </div>

                    <div class="form-field">
                        <label for="promptPreview" class="field-label">Template prompt</label>
                        <textarea id="promptPreview" class="field-textarea" rows="4" readonly></textarea>
                    </div>

                    <div class="form-field">
                        <label class="field-label" for="referenceInput">Foto referensi</label>
                        <div id="dropzone" class="dropzone" tabindex="0">
                            <input id="referenceInput" type="file" accept="image/*" multiple hidden>
                            <div class="dropzone-icon" aria-hidden="true">📁</div>
                            <div class="dropzone-copy">
                                <strong>Tarik &amp; lepas</strong> atau <button type="button" class="link" id="browseButton">pilih dari perangkat</button>
                                <span class="dropzone-sub">Maksimal 6 foto dalam format JPG, PNG, atau WEBP.</span>
                            </div>
                        </div>
                        <div id="referencePreview" class="preview-grid" aria-live="polite"></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="generateButton">Generate 4 Pose</button>
                        <div class="form-status" id="formStatus" role="status"></div>
                    </div>
                </form>
            </section>

            <section class="panel panel-results" aria-labelledby="resultTitle">
                <div class="panel-header">
                    <div>
                        <h2 id="resultTitle">Hasil Generate</h2>
                        <p>Output akan otomatis menampilkan empat pose berbeda sesuai kategori prompt terpilih.</p>
                    </div>
                </div>

                <div id="resultGrid" class="result-grid" aria-live="polite">
                    <div class="empty-state" id="emptyState">
                        <span class="empty-emoji" aria-hidden="true">✨</span>
                        <p>Belum ada hasil. Unggah beberapa foto referensi lalu jalankan Flash 2.5.</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <template id="skeletonTemplate">
        <article class="result-card loading">
            <div class="result-thumb shimmer"></div>
            <div class="result-meta">
                <div class="line w-60 shimmer"></div>
                <div class="line w-40 shimmer"></div>
            </div>
        </article>
    </template>

    <template id="resultTemplate">
        <article class="result-card">
            <div class="result-thumb-wrapper">
                <span class="pose-badge"></span>
                <img class="result-thumb" alt="Hasil generasi Flash 2.5">
            </div>
            <div class="result-meta">
                <span class="result-style"></span>
                <p class="result-desc"></p>
                <div class="result-actions">
                    <button type="button" class="btn-ghost result-download">Download</button>
                </div>
            </div>
        </article>
    </template>

    <script>
    (function() {
        const styleSelect = document.getElementById('styleSelect');
        const styleHint = document.getElementById('styleHint');
        const promptPreview = document.getElementById('promptPreview');
        const form = document.getElementById('editForm');
        const formStatus = document.getElementById('formStatus');
        const generateButton = document.getElementById('generateButton');
        const resultGrid = document.getElementById('resultGrid');
        const skeletonTemplate = document.getElementById('skeletonTemplate');
        const resultTemplate = document.getElementById('resultTemplate');
        const emptyState = document.getElementById('emptyState');
        const dropzone = document.getElementById('dropzone');
        const referenceInput = document.getElementById('referenceInput');
        const browseButton = document.getElementById('browseButton');
        const previewGrid = document.getElementById('referencePreview');

        const MAX_FILES = 6;
        let selectedFiles = [];

        const styleOptions = {
            wedding: {
                label: 'Pernikahan',
                description: 'Tampilan elegan dengan tone hangat, cocok untuk dokumentasi momen resepsi.',
                template: 'Gemini Flash 2.5, wedding editorial retouch, cinematic lighting, fokus pada ekspresi pasangan, nuansa emas hangat, kualitas majalah.',
                adjustments: {
                    filter: 'brightness(1.08) contrast(1.12) saturate(1.22)',
                    overlayColor: '#f9d8bf',
                    overlayOpacity: 0.16
                },
                poses: [
                    'Pose 1 · Tatapan romantis berhadapan dengan senyum lembut.',
                    'Pose 2 · Pegangan tangan sambil berjalan di aisle yang dihiasi bunga.',
                    'Pose 3 · Close-up cincin dengan bokeh lampu hangat.',
                    'Pose 4 · Pelukan hangat dengan veil tertiup angin lembut.'
                ]
            },
            vacation: {
                label: 'Liburan',
                description: 'Suasana santai penuh warna dengan cahaya alami khas destinasi tropis.',
                template: 'Gemini Flash 2.5, travel lifestyle edit, vibrant summer palette, langit biru dan cahaya matahari, ekspresi ceria dan energik.',
                adjustments: {
                    filter: 'brightness(1.12) contrast(1.08) saturate(1.35)',
                    overlayColor: '#8ad5ff',
                    overlayOpacity: 0.12
                },
                poses: [
                    'Pose 1 · Melompat di pantai dengan ombak di belakang.',
                    'Pose 2 · Duduk santai di kursi kayu menghadap laut.',
                    'Pose 3 · Membentangkan tangan menikmati pemandangan kota tua.',
                    'Pose 4 · Berjalan dengan tas ransel dan senyum lebar.'
                ]
            },
            office: {
                label: 'Kerja',
                description: 'Gaya profesional modern dengan palet warna netral dan clean lighting.',
                template: 'Gemini Flash 2.5, corporate portrait retouch, pencahayaan soft studio, pakaian formal minimalis, kesan percaya diri dan profesional.',
                adjustments: {
                    filter: 'brightness(0.98) contrast(1.18) saturate(0.88)',
                    overlayColor: '#d7dde4',
                    overlayOpacity: 0.14
                },
                poses: [
                    'Pose 1 · Berdiri tegap dengan tangan menyilang dan tatapan fokus.',
                    'Pose 2 · Duduk di meja kerja sambil menatap laptop.',
                    'Pose 3 · Pegang notebook dan tersenyum ke kamera.',
                    'Pose 4 · Gaya candid berjalan melewati koridor kantor.'
                ]
            },
            akad: {
                label: 'Akad Nikah',
                description: 'Sentuhan tradisional yang lembut dengan detail busana adat dan dekorasi sakral.',
                template: 'Gemini Flash 2.5, intimate akad ceremony edit, soft pastel tone, detail busana adat, nuansa sakral dan hangat.',
                adjustments: {
                    filter: 'brightness(1.1) contrast(1.07) saturate(1.18)',
                    overlayColor: '#f2e7d7',
                    overlayOpacity: 0.18
                },
                poses: [
                    'Pose 1 · Momen ijab kabul dengan fokus pada ekspresi khidmat.',
                    'Pose 2 · Close-up saling menyematkan cincin.',
                    'Pose 3 · Duduk berdampingan di pelaminan dengan senyum tenang.',
                    'Pose 4 · Menunjukkan buku nikah dengan latar dekorasi adat.'
                ]
            }
        };

        function updateStyleUI() {
            const value = styleSelect.value;
            const option = styleOptions[value] || styleOptions.wedding;
            styleHint.textContent = option.description;
            promptPreview.value = option.template;
        }

        function setStatus(message, state) {
            if (!formStatus) return;
            formStatus.textContent = message || '';
            formStatus.dataset.state = state || '';
        }

        function clearResults() {
            resultGrid.innerHTML = '';
            if (emptyState) {
                emptyState.hidden = true;
            }
        }

        function showSkeletons() {
            clearResults();
            for (let i = 0; i < 4; i++) {
                const fragment = skeletonTemplate.content.cloneNode(true);
                resultGrid.appendChild(fragment);
            }
        }

        function renderResults(styleKey, styledImages) {
            const option = styleOptions[styleKey] || styleOptions.wedding;
            clearResults();
            if (!styledImages.length) {
                if (emptyState) {
                    emptyState.hidden = false;
                    resultGrid.appendChild(emptyState);
                }
                return;
            }

            if (emptyState) {
                emptyState.hidden = true;
            }

            styledImages.forEach((result, index) => {
                const fragment = resultTemplate.content.cloneNode(true);
                const image = fragment.querySelector('.result-thumb');
                const badge = fragment.querySelector('.pose-badge');
                const styleLabel = fragment.querySelector('.result-style');
                const description = fragment.querySelector('.result-desc');
                const downloadBtn = fragment.querySelector('.result-download');

                image.src = result.url;
                image.alt = `Hasil Flash 2.5 ${option.label} pose ${index + 1}`;
                badge.textContent = `Pose ${index + 1}`;
                styleLabel.textContent = `${option.label} · Flash 2.5`;
                description.textContent = option.poses[index] || option.poses[option.poses.length - 1] || '';
                downloadBtn.dataset.url = result.url;
                downloadBtn.dataset.filename = result.filename;

                resultGrid.appendChild(fragment);
            });
        }

        function fileToDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve({
                    name: file.name,
                    dataUrl: reader.result
                });
                reader.onerror = () => reject(new Error('Gagal membaca file referensi.'));
                reader.readAsDataURL(file);
            });
        }

        function createStyledImage(sourceUrl, adjustments) {
            const { filter = 'none', overlayColor = '', overlayOpacity = 0 } = adjustments || {};
            return new Promise((resolve, reject) => {
                const image = new Image();
                image.decoding = 'async';
                image.onload = () => {
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    if (!context) {
                        reject(new Error('Browser tidak mendukung kanvas untuk pemrosesan gambar.'));
                        return;
                    }

                    const maxDimension = 1600;
                    const longestSide = Math.max(image.naturalWidth, image.naturalHeight) || 1;
                    const scale = Math.min(1, maxDimension / longestSide);
                    canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
                    canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));

                    context.filter = filter;
                    context.drawImage(image, 0, 0, canvas.width, canvas.height);

                    if (overlayColor && overlayOpacity > 0) {
                        context.globalAlpha = overlayOpacity;
                        context.fillStyle = overlayColor;
                        context.fillRect(0, 0, canvas.width, canvas.height);
                        context.globalAlpha = 1;
                    }

                    try {
                        const output = canvas.toDataURL('image/jpeg', 0.92);
                        resolve(output);
                    } catch (error) {
                        reject(new Error('Gagal membentuk hasil edit bergaya.'));
                    }
                };
                image.onerror = () => reject(new Error('Gagal memuat gambar referensi.'));
                image.src = sourceUrl;
            });
        }

        async function generateStyledImages(styleKey, sources) {
            const option = styleOptions[styleKey] || styleOptions.wedding;
            const totalResults = 4;
            if (!sources.length) {
                return [];
            }

            const tasks = Array.from({ length: totalResults }, (_, index) => {
                const source = sources[index % sources.length];
                const filename = `flash-25-${styleKey}-pose-${index + 1}.jpg`;
                return createStyledImage(source.dataUrl, option.adjustments).then((styledUrl) => ({
                    url: styledUrl,
                    filename
                }));
            });

            return Promise.all(tasks);
        }

        function isImageFile(file) {
            if (file.type && file.type.startsWith('image/')) {
                return true;
            }

            const name = (file.name || '').toLowerCase();
            return /\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i.test(name);
        }

        function handleFiles(fileList) {
            const files = Array.from(fileList).filter(isImageFile);
            if (!files.length) {
                selectedFiles = [];
                previewGrid.innerHTML = '';
                previewGrid.dataset.empty = 'true';
                setStatus('Format file tidak dikenali. Unggah foto dalam format JPG, PNG, WEBP, GIF, BMP, HEIC, atau HEIF.', 'error');
                return;
            }

            setStatus('', '');

            selectedFiles = files.slice(0, MAX_FILES);
            previewGrid.innerHTML = '';
            selectedFiles.forEach(file => {
                const url = URL.createObjectURL(file);
                const item = document.createElement('figure');
                item.className = 'preview-item';

                const img = document.createElement('img');
                img.src = url;
                img.alt = file.name;
                img.addEventListener('load', () => URL.revokeObjectURL(url));

                const caption = document.createElement('figcaption');
                caption.textContent = file.name;

                item.appendChild(img);
                item.appendChild(caption);
                previewGrid.appendChild(item);
            });
            previewGrid.dataset.empty = 'false';
        }

        function ensureFilesSelected() {
            if (selectedFiles.length) {
                return true;
            }
            setStatus('Unggah minimal satu foto referensi terlebih dahulu.', 'error');
            dropzone.focus();
            return false;
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
            dropzone.classList.add('dragging');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dragging');
        });

        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropzone.classList.remove('dragging');
            if (event.dataTransfer && event.dataTransfer.files) {
                handleFiles(event.dataTransfer.files);
            }
        });

        referenceInput.addEventListener('change', () => {
            if (referenceInput.files) {
                handleFiles(referenceInput.files);
            }
        });

        browseButton.addEventListener('click', () => {
            referenceInput.click();
        });

        resultGrid.addEventListener('click', (event) => {
            const target = event.target;
            if (target && target.classList.contains('result-download')) {
                const url = target.dataset.url;
                const filename = target.dataset.filename || 'flash-25.jpg';
                if (url) {
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            setStatus('', '');
            if (!ensureFilesSelected()) {
                return;
            }

            const styleKey = styleSelect.value;
            const option = styleOptions[styleKey] || styleOptions.wedding;
            generateButton.disabled = true;
            generateButton.classList.add('loading');
            setStatus('Menjalankan Flash 2.5… menyesuaikan foto dengan gaya terpilih.', 'info');
            showSkeletons();

            try {
                const sources = await Promise.all(selectedFiles.map(fileToDataUrl));
                const styledImages = await generateStyledImages(styleKey, sources);
                renderResults(styleKey, styledImages);
                setStatus(`Selesai! 4 pose gaya ${option.label} siap diunduh.`, 'success');
            } catch (error) {
                console.error(error);
                clearResults();
                resultGrid.appendChild(emptyState);
                emptyState.hidden = false;
                setStatus(error.message || 'Terjadi kesalahan saat menghasilkan hasil edit.', 'error');
            } finally {
                generateButton.disabled = false;
                generateButton.classList.remove('loading');
            }
        });

        styleSelect.addEventListener('change', updateStyleUI);
        updateStyleUI();
    })();
    </script>
</body>
</html>
