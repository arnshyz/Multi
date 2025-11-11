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
                    <p>Padukan 2-3 foto referensi kamu untuk membuat empat pose sinematik ala Filmmaker.</p>
                </div>
                <div class="photo-film-meta">
                    <div class="user-chip" aria-label="Akun aktif">
                        <span class="avatar-circle" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($username, 0, 2))); ?></span>
                        <span class="user-meta">
                            <span class="user-label">Masuk sebagai</span>
                            <span class="user-name"><?php echo htmlspecialchars($username, ENT_QUOTES); ?></span>
                        </span>
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
                        <div class="subtitle">Empat pose akan tampil di sini dengan gaya yang konsisten seperti panel Filmmaker.</div>
                    </div>
                </div>
                <div class="film-scenes-board">
                    <div id="emptyState" class="film-empty-state">
                        <div>
                            <div class="film-empty-icon">✨</div>
                            <div class="subtitle">Belum ada hasil</div>
                            <div class="muted" style="font-size:11px">Unggah 2-3 foto referensi dan jalankan Flash 2.5 Mode 3.</div>
                        </div>
                    </div>
                    <div id="resultGrid" class="film-scenes-container"></div>
                </div>
            </section>

            <section class="card-soft photo-film-settings" aria-labelledby="formTitle">
                <div class="header" style="margin-bottom:8px">
                    <div>
                        <div class="title" id="formTitle" style="font-size:16px">Setelan Photo Edit</div>
                        <div class="subtitle">Sama seperti Filmmaker, pilih tema dan atur prompt sebelum generate.</div>
                    </div>
                </div>

                <form id="editForm" class="film-settings-section" novalidate>
                    <div>
                        <div class="small-label">Model yang digunakan</div>
                        <div class="model-chip" role="text">Gemini Flash 2.5 · Mode 3 Multi-Reference</div>
                        <p class="muted">Model dikunci ke Flash 2.5 agar blending referensi konsisten.</p>
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
                                <span>Gunakan 2–3 foto (JPG, PNG, atau WEBP) untuk digabungkan.</span>
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

    <template id="skeletonTemplate">
        <article class="film-scene-card loading">
            <div class="film-scene-thumb shimmer"></div>
            <div class="film-scene-meta">
                <div class="line w-60 shimmer"></div>
                <div class="line w-40 shimmer"></div>
            </div>
        </article>
    </template>

    <template id="resultTemplate">
        <article class="film-scene-card">
            <div class="result-thumb-wrapper">
                <span class="pose-badge"></span>
                <img class="result-thumb" alt="Hasil generasi Flash 2.5">
            </div>
            <div class="result-meta">
                <span class="result-style"></span>
                <p class="result-prompt"></p>
                <p class="result-desc"></p>
                <div class="result-actions">
                    <button type="button" class="btn-ghost result-download">Download</button>
                </div>
            </div>
        </article>
    </template>

    <script>
    (function() {
        const themeSelect = document.getElementById('themeSelect');
        const themeHint = document.getElementById('themeHint');
        const promptStyleInput = document.getElementById('promptStyle');
        const resetPromptButton = document.getElementById('resetPromptButton');
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

        const MIN_FILES = 2;
        const MAX_FILES = 3;
        let selectedFiles = [];
        let promptDirty = false;

        const themeOptions = {
            romantic: {
                label: 'Romantic Fusion',
                description: 'Palet rose gold dengan highlight lembut ala golden hour dan kilau editorial.',
                template: 'romantic editorial portrait blend, warm golden hour glow, pearlescent highlights, soft focus lens flare, cinematic depth, cohesive styling',
                palette: {
                    background: '#120a14',
                    overlayColor: '#f8c4d8',
                    overlayOpacity: 0.14,
                    accent: '#fb7185',
                    highlight: '#fde68a',
                    filter: 'brightness(1.08) contrast(1.12) saturate(1.2)'
                },
                baseAlpha: 0.88
            },
            urban: {
                label: 'Neo Urban Story',
                description: 'Mood metropolis malam dengan kontras matte, refleksi neon cyan-magenta, dan nuansa futuristik.',
                template: 'neo urban fashion narrative, cinematic night city, teal and magenta glow, reflective surfaces, high contrast matte finish, dramatic shadows',
                palette: {
                    background: '#060b12',
                    overlayColor: '#1f2937',
                    overlayOpacity: 0.18,
                    accent: '#38bdf8',
                    highlight: '#a855f7',
                    filter: 'brightness(1.05) contrast(1.18) saturate(1.06)'
                },
                baseAlpha: 0.9
            },
            tropical: {
                label: 'Tropical Journey',
                description: 'Kombinasi warna tropis vibrant dengan kilau matahari dan ambience liburan energik.',
                template: 'tropical travel editorial, vivid aqua and lime palette, sun-kissed skin, breezy motion, cinematic resort backdrops, lush foliage details',
                palette: {
                    background: '#041418',
                    overlayColor: '#34d399',
                    overlayOpacity: 0.12,
                    accent: '#34d399',
                    highlight: '#fbbf24',
                    filter: 'brightness(1.12) contrast(1.08) saturate(1.24)'
                },
                baseAlpha: 0.9
            },
            heritage: {
                label: 'Heritage Elegance',
                description: 'Sentuhan tradisional hangat dengan tekstur kaya dan detail busana elegan.',
                template: 'heritage ceremonial portrait, warm amber lighting, intricate textile details, soft cinematic haze, dignified poses, timeless storytelling',
                palette: {
                    background: '#1a1209',
                    overlayColor: '#f97316',
                    overlayOpacity: 0.1,
                    accent: '#f59e0b',
                    highlight: '#fcd34d',
                    filter: 'brightness(1.06) contrast(1.08) saturate(1.12)'
                },
                baseAlpha: 0.92
            }
        };

        const poseVariants = [
            {
                key: 'closeUpGlow',
                badge: 'Pose 1',
                title: 'Pose 1 · Siluet Harmonis',
                fit: 0.9,
                filter: 'brightness(1.08) contrast(1.12) saturate(1.12)',
                overlay: { color: 'highlight', opacity: 0.12, blend: 'soft-light' },
                gradient: {
                    angle: -25,
                    stops: [
                        { offset: 0, color: 'prompt', opacity: 0.32 },
                        { offset: 1, color: 'background', opacity: 0 }
                    ]
                },
                accent: { type: 'ring', radius: 210, strokeWidth: 36, color: 'accent', opacity: 0.26, blend: 'screen' },
                noise: { opacity: 0.05, size: 120 },
                placements: {
                    2: [
                        { x: -70, y: -40, scale: 0.95, rotate: -6 },
                        { x: 80, y: 48, scale: 0.95, rotate: 4 }
                    ],
                    3: [
                        { x: -80, y: -42, scale: 0.94, rotate: -6 },
                        { x: 88, y: 50, scale: 0.96, rotate: 4 },
                        { x: 8, y: 20, scale: 0.88, rotate: 1 }
                    ],
                    default: [
                        { x: -72, y: -40, scale: 0.94, rotate: -6 },
                        { x: 84, y: 46, scale: 0.95, rotate: 4 },
                        { x: 10, y: 22, scale: 0.88, rotate: 1 }
                    ]
                },
                description(theme, prompt) {
                    const base = 'Close-up harmonis menonjolkan ekspresi utama dengan highlight lembut.';
                    return prompt ? `${base} Tema "${prompt}" dipadukan ke tekstur wajah dan pencahayaan.` : base;
                }
            },
            {
                key: 'dynamicMotion',
                badge: 'Pose 2',
                title: 'Pose 2 · Dynamic Motion',
                fit: 0.94,
                filter: 'brightness(1.04) contrast(1.14) saturate(1.08)',
                overlay: { color: 'accent', opacity: 0.16, blend: 'lighten' },
                gradient: {
                    angle: 32,
                    stops: [
                        { offset: 0, color: 'accent', opacity: 0.2 },
                        { offset: 0.65, color: 'prompt', opacity: 0.26 },
                        { offset: 1, color: 'background', opacity: 0 }
                    ]
                },
                accent: { type: 'bars', count: 3, length: 860, width: 36, spacing: 42, angle: -18, color: 'highlight', opacity:0.18, blend: 'screen' },
                noise: { opacity: 0.06, size: 110, blend: 'soft-light' },
                placements: {
                    2: [
                        { x: -92, y: 26, scale: 0.9, rotate: -5 },
                        { x: 92, y: -18, scale: 1.02, rotate: 5 }
                    ],
                    3: [
                        { x: -118, y: 12, scale: 0.88, rotate: -6 },
                        { x: 48, y: -42, scale: 1.02, rotate: 4 },
                        { x: 130, y: 18, scale: 0.96, rotate: 7 }
                    ],
                    default: [
                        { x: -102, y: 18, scale: 0.88, rotate: -5 },
                        { x: 64, y: -36, scale: 1.02, rotate: 4 },
                        { x: 136, y: 24, scale: 0.96, rotate: 6 }
                    ]
                },
                description(theme, prompt) {
                    const base = 'Gerakan dramatis dengan komposisi diagonal dan aksen cahaya dinamis.';
                    return prompt ? `${base} Tema "${prompt}" dimasukkan pada wardrobe dan motion blur.` : base;
                }
            },
            {
                key: 'wideStory',
                badge: 'Pose 3',
                title: 'Pose 3 · Wide Storytelling',
                fit: 1,
                filter: 'brightness(1.02) contrast(1.08) saturate(1.1)',
                overlay: { color: 'background', opacity: 0.18, blend: 'multiply' },
                gradient: {
                    angle: -15,
                    stops: [
                        { offset: 0, color: 'background', opacity: 0.22 },
                        { offset: 0.5, color: 'accent', opacity: 0.24 },
                        { offset: 1, color: 'prompt', opacity: 0 }
                    ]
                },
                accent: { type: 'grid', size: 160, thickness: 1.6, opacity: 0.16, blend: 'soft-light' },
                noise: { opacity: 0.05, size: 140, blend: 'overlay' },
                placements: {
                    2: [
                        { x: -52, y: -8, scale: 0.92, rotate: -3 },
                        { x: 66, y: 0, scale: 0.98, rotate: 3 }
                    ],
                    3: [
                        { x: -66, y: -6, scale: 0.92, rotate: -4 },
                        { x: 80, y: -2, scale: 0.98, rotate: 3 },
                        { x: 4, y: -4, scale: 0.86, rotate: 0 }
                    ],
                    default: [
                        { x: -66, y: -6, scale: 0.92, rotate: -4 },
                        { x: 80, y: -2, scale: 0.98, rotate: 3 },
                        { x: 4, y: -4, scale: 0.86, rotate: 0 }
                    ]
                },
                description(theme, prompt) {
                    const base = 'Storytelling shot yang menonjolkan kostum penuh dan suasana latar.';
                    return prompt ? `${base} Tema "${prompt}" diterapkan pada palet warna environment.` : base;
                }
            },
            {
                key: 'detailCinematic',
                badge: 'Pose 4',
                title: 'Pose 4 · Cinematic Detail',
                fit: 0.92,
                filter: 'brightness(1.06) contrast(1.16) saturate(1.14)',
                overlay: { color: 'highlight', opacity: 0.14, blend: 'screen' },
                gradient: {
                    angle: 18,
                    stops: [
                        { offset: 0, color: 'highlight', opacity: 0.18 },
                        { offset: 0.45, color: 'accent', opacity: 0.2 },
                        { offset: 1, color: 'background', opacity: 0 }
                    ]
                },
                accent: { type: 'orb', radius: 140, opacity: 0.26, blend: 'screen' },
                noise: { opacity: 0.06, size: 120, blend: 'overlay' },
                placements: {
                    2: [
                        { x: -70, y: 28, scale: 0.94, rotate: -4 },
                        { x: 78, y: -32, scale: 0.98, rotate: 4 }
                    ],
                    3: [
                        { x: -92, y: 30, scale: 0.92, rotate: -5 },
                        { x: 36, y: -46, scale: 1, rotate: 5 },
                        { x: 120, y: 22, scale: 0.94, rotate: 2 }
                    ],
                    default: [
                        { x: -82, y: 24, scale: 0.92, rotate: -5 },
                        { x: 32, y: -44, scale: 1, rotate: 5 },
                        { x: 124, y: 22, scale: 0.94, rotate: 2 }
                    ]
                },
                description(theme, prompt) {
                    const base = 'Detail shot memperlihatkan aksesori dan tekstur bahan dengan dramatis.';
                    return prompt ? `${base} Tema "${prompt}" difokuskan pada highlight detail close-up.` : base;
                }
            }
        ];

        function isImageFile(file) {
            if (!file) return false;
            if (file.type && file.type.startsWith('image/')) {
                return true;
            }
            const name = (file.name || '').toLowerCase();
            return /\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i.test(name);
        }

        function fileToDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => {
                    const result = typeof reader.result === 'string' ? reader.result : '';
                    resolve({
                        name: file?.name || 'reference',
                        dataUrl: result
                    });
                };
                reader.onerror = () => reject(new Error('Gagal membaca file referensi.'));
                try {
                    reader.readAsDataURL(file);
                } catch (error) {
                    reject(new Error('Gagal memproses file referensi.'));
                }
            });
        }

        function resolvePlacement(variant, total, index) {
            const placements = variant?.placements || {};
            const specific = placements[total];
            if (Array.isArray(specific) && specific.length) {
                return specific[Math.min(index, specific.length - 1)] || specific[0];
            }
            const fallback = placements.default;
            if (Array.isArray(fallback) && fallback.length) {
                return fallback[Math.min(index, fallback.length - 1)] || fallback[0];
            }
            return { x: 0, y: 0, scale: 1, rotate: 0 };
        }

        function loadImageSource(source) {
            return new Promise((resolve, reject) => {
                const image = new Image();
                image.decoding = 'async';
                image.onload = () => resolve(image);
                image.onerror = () => reject(new Error('Gagal memuat gambar referensi.'));
                image.src = source;
            });
        }

        function resolveOverlayColor(token, palette, promptColor) {
            if (!token) return promptColor;
            if (token === 'prompt') return promptColor;
            if (palette && palette[token]) {
                return palette[token];
            }
            return token;
        }

        async function composeVariantImage(themeKey, theme, variant, sources, promptText) {
            if (!variant) {
                return '';
            }

            const promptColor = extractPromptAccent(promptText || theme?.label || themeKey);
            const images = await Promise.all(sources.map((source) => loadImageSource(source.dataUrl)));
            if (!images.length) {
                return '';
            }

            const width = variant.width || 720;
            const height = variant.height || 960;
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                throw new Error('Browser tidak mendukung kanvas untuk pemrosesan gambar.');
            }

            ctx.fillStyle = theme?.palette?.background || '#0f172a';
            ctx.fillRect(0, 0, width, height);

            const total = images.length;
            const baseFit = typeof variant.fit === 'number' ? variant.fit : 0.92;

            images.forEach((image, index) => {
                const placement = resolvePlacement(variant, total, index) || {};
                const scaleModifier = placement.scale != null ? placement.scale : 1;
                const rotation = placement.rotate || 0;
                const translateX = placement.x || 0;
                const translateY = placement.y || 0;
                const blend = placement.blend || variant.blend || 'source-over';
                const filter = placement.filter || variant.filter || theme?.palette?.filter || 'none';
                const alpha = placement.alpha != null
                    ? placement.alpha
                    : (variant.alpha != null ? variant.alpha : (theme?.baseAlpha != null ? theme.baseAlpha : 0.9));

                const fitScale = Math.min(
                    (width * baseFit) / Math.max(image.naturalWidth, 1),
                    (height * baseFit) / Math.max(image.naturalHeight, 1)
                ) * scaleModifier;

                const drawWidth = Math.max(image.naturalWidth * fitScale, 1);
                const drawHeight = Math.max(image.naturalHeight * fitScale, 1);

                ctx.save();
                ctx.translate(width / 2 + translateX, height / 2 + translateY);
                if (rotation) {
                    ctx.rotate((rotation * Math.PI) / 180);
                }
                ctx.filter = filter;
                ctx.globalAlpha = alpha;
                ctx.globalCompositeOperation = blend;
                ctx.drawImage(image, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
                ctx.restore();
            });

            ctx.filter = 'none';
            ctx.globalAlpha = 1;
            ctx.globalCompositeOperation = 'source-over';

            if (theme?.palette?.overlayColor && theme.palette.overlayOpacity) {
                ctx.save();
                ctx.globalCompositeOperation = 'soft-light';
                ctx.fillStyle = convertHexToRgba(theme.palette.overlayColor, theme.palette.overlayOpacity);
                ctx.fillRect(0, 0, width, height);
                ctx.restore();
            }

            if (variant.overlay) {
                const overlayColor = resolveOverlayColor(variant.overlay.color, theme?.palette, promptColor);
                ctx.save();
                ctx.globalCompositeOperation = variant.overlay.blend || 'soft-light';
                ctx.fillStyle = convertHexToRgba(overlayColor, variant.overlay.opacity ?? 0.14);
                ctx.fillRect(0, 0, width, height);
                ctx.restore();
            }

            if (variant.gradient) {
                drawGradientOverlay(ctx, variant.gradient, theme?.palette || {}, promptColor);
            }

            if (variant.accent) {
                drawAccent(ctx, variant.accent, theme?.palette || {}, promptColor);
            }

            if (variant.noise) {
                drawNoise(ctx, variant.noise);
            }

            return canvas.toDataURL('image/png');
        }

        async function generateStyledImages(themeKey, promptText, files) {
            if (!files.length) {
                return [];
            }

            const theme = themeOptions[themeKey] || themeOptions.romantic;
            const sources = await Promise.all(files.map((file) => fileToDataUrl(file)));
            const promptLabel = (promptText || '').trim();

            const results = [];
            for (let index = 0; index < poseVariants.length; index += 1) {
                const variant = poseVariants[index];
                const imageUrl = await composeVariantImage(themeKey, theme, variant, sources, promptLabel);
                if (!imageUrl) {
                    throw new Error('Gagal membuat komposisi gambar dari referensi yang dipilih.');
                }
                results.push({
                    imageUrl,
                    downloadName: `${variant.key || 'result'}-${Date.now()}-${index + 1}.png`
                });
            }

            return results;
        }

        const gradientCache = new Map();

        function createSvgElement(tag, attrs = {}) {
            const el = document.createElementNS('http://www.w3.org/2000/svg', tag);
            Object.entries(attrs).forEach(([key, value]) => {
                if (value != null) {
                    el.setAttribute(key, String(value));
                }
            });
            return el;
        }

        function normalizeFileList(fileList) {
            const files = [];
            const seen = new Set();
            for (let i = 0; i < fileList.length; i++) {
                const file = fileList[i];
                if (!file || !file.name) continue;
                const key = `${file.name}-${file.size}-${file.lastModified}`;
                if (seen.has(key)) continue;
                seen.add(key);
                files.push(file);
            }
            return files;
        }

        function renderThemeHint(themeKey) {
            const option = themeOptions[themeKey];
            if (!option) {
                themeHint.textContent = '';
                return;
            }
            themeHint.textContent = option.description;
        }

        function ensurePromptTemplate(themeKey, force = false) {
            const option = themeOptions[themeKey];
            if (!option) return;
            if (force || !promptDirty || !promptStyleInput.value.trim()) {
                promptStyleInput.value = option.template;
                promptDirty = false;
            }
        }

        function setPromptDirty() {
            promptDirty = true;
        }

        function clearResults() {
            resultGrid.innerHTML = '';
        }

        function setLoadingState(isLoading) {
            if (isLoading) {
                generateButton.classList.add('loading');
                generateButton.disabled = true;
            } else {
                generateButton.classList.remove('loading');
                generateButton.disabled = false;
            }
        }

        function updateFormStatus(message, type = 'info') {
            if (!formStatus) return;
            formStatus.textContent = message || '';
            formStatus.dataset.type = type;
        }

        function showEmptyState(show) {
            if (!emptyState) return;
            emptyState.style.display = show ? '' : 'none';
        }

        function showSkeletons(count = 4) {
            const fragment = document.createDocumentFragment();
            for (let i = 0; i < count; i++) {
                const skeleton = skeletonTemplate.content.cloneNode(true);
                fragment.appendChild(skeleton);
            }
            resultGrid.appendChild(fragment);
        }

        function clearSkeletons() {
            resultGrid.querySelectorAll('.loading').forEach((node) => node.remove());
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
                        updateFormStatus('');
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

        function applyThemeToCard(card, theme, promptText) {
            if (!theme || !card) return;

            const palette = theme.palette;
            const thumbWrapper = card.querySelector('.result-thumb-wrapper');
            if (thumbWrapper) {
                thumbWrapper.style.setProperty('--overlay-color', palette.overlayColor);
                thumbWrapper.style.setProperty('--overlay-opacity', theme.baseAlpha);
                thumbWrapper.style.setProperty('--overlay-filter', palette.filter);
            }

            const badge = card.querySelector('.pose-badge');
            if (badge) {
                badge.style.background = palette.accent;
            }

            const styleLabel = card.querySelector('.result-style');
            if (styleLabel) {
                styleLabel.textContent = theme.label;
            }

            const desc = card.querySelector('.result-desc');
            if (desc) {
                desc.textContent = typeof theme.description === 'function'
                    ? theme.description(promptText)
                    : '';
            }
        }

        function createOverlayCanvas(width, height) {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            return canvas;
        }

        function drawGradientOverlay(ctx, config, palette, promptColor) {
            if (!config) return;
            const angle = (config.angle || 0) * (Math.PI / 180);
            const radius = Math.sqrt(ctx.canvas.width ** 2 + ctx.canvas.height ** 2) / 2;
            const centerX = ctx.canvas.width / 2;
            const centerY = ctx.canvas.height / 2;
            const startX = centerX + Math.cos(angle) * radius;
            const startY = centerY + Math.sin(angle) * radius;
            const endX = centerX - Math.cos(angle) * radius;
            const endY = centerY - Math.sin(angle) * radius;
            const gradient = ctx.createLinearGradient(startX, startY, endX, endY);

            config.stops.forEach((stop) => {
                const colorKey = stop.color === 'prompt' ? promptColor : palette[stop.color] || palette.accent;
                gradient.addColorStop(stop.offset, convertHexToRgba(colorKey, stop.opacity));
            });

            ctx.globalCompositeOperation = 'lighter';
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.globalCompositeOperation = 'source-over';
        }

        function drawAccent(ctx, accent, palette, promptColor) {
            if (!accent) return;
            ctx.save();
            ctx.globalCompositeOperation = accent.blend || 'lighter';
            const color = palette[accent.color] || promptColor || palette.accent;

            switch (accent.type) {
                case 'ring':
                    const ring = accent.radius || 200;
                    const strokeWidth = accent.strokeWidth || 40;
                    ctx.beginPath();
                    ctx.arc(ctx.canvas.width / 2, ctx.canvas.height / 2, ring, 0, Math.PI * 2);
                    ctx.lineWidth = strokeWidth;
                    ctx.strokeStyle = convertHexToRgba(color, accent.opacity || 0.2);
                    ctx.stroke();
                    break;
                case 'bars':
                    const length = accent.length || ctx.canvas.width;
                    const width = accent.width || 20;
                    const spacing = accent.spacing || 36;
                    const count = accent.count || 3;
                    const angle = (accent.angle || 0) * (Math.PI / 180);
                    ctx.translate(ctx.canvas.width / 2, ctx.canvas.height / 2);
                    ctx.rotate(angle);
                    ctx.translate(-length / 2, -(width * count + spacing * (count - 1)) / 2);
                    for (let i = 0; i < count; i++) {
                        ctx.fillStyle = convertHexToRgba(color, accent.opacity || 0.2);
                        ctx.fillRect(0, i * (width + spacing), length, width);
                    }
                    break;
                case 'grid':
                    const size = accent.size || 160;
                    const thickness = accent.thickness || 1.4;
                    ctx.strokeStyle = convertHexToRgba(color, accent.opacity || 0.12);
                    ctx.lineWidth = thickness;
                    for (let x = 0; x < ctx.canvas.width; x += size) {
                        ctx.beginPath();
                        ctx.moveTo(x, 0);
                        ctx.lineTo(x, ctx.canvas.height);
                        ctx.stroke();
                    }
                    for (let y = 0; y < ctx.canvas.height; y += size) {
                        ctx.beginPath();
                        ctx.moveTo(0, y);
                        ctx.lineTo(ctx.canvas.width, y);
                        ctx.stroke();
                    }
                    break;
                case 'orb':
                    const radius = accent.radius || 120;
                    const gradient = ctx.createRadialGradient(
                        ctx.canvas.width / 2,
                        ctx.canvas.height / 2,
                        radius * 0.2,
                        ctx.canvas.width / 2,
                        ctx.canvas.height / 2,
                        radius
                    );
                    gradient.addColorStop(0, convertHexToRgba(color, accent.opacity || 0.22));
                    gradient.addColorStop(1, convertHexToRgba(color, 0));
                    ctx.fillStyle = gradient;
                    ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                    break;
                default:
                    break;
            }
            ctx.restore();
        }

        function drawNoise(ctx, noise) {
            if (!noise) return;
            const { opacity = 0.04, size = 120, blend = 'soft-light' } = noise;
            const patternCanvas = document.createElement('canvas');
            patternCanvas.width = size;
            patternCanvas.height = size;
            const patternCtx = patternCanvas.getContext('2d');
            const imageData = patternCtx.createImageData(size, size);
            for (let i = 0; i < imageData.data.length; i += 4) {
                const value = Math.random() * 255;
                imageData.data[i] = value;
                imageData.data[i + 1] = value;
                imageData.data[i + 2] = value;
                imageData.data[i + 3] = opacity * 255;
            }
            patternCtx.putImageData(imageData, 0, 0);
            const pattern = ctx.createPattern(patternCanvas, 'repeat');
            if (!pattern) return;
            ctx.save();
            ctx.globalCompositeOperation = blend;
            ctx.fillStyle = pattern;
            ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.restore();
        }

        function convertHexToRgba(input, alpha = 1) {
            if (!input) {
                return `rgba(255, 255, 255, ${alpha})`;
            }

            const value = String(input).trim();

            if (value.startsWith('#')) {
                let normalized = value.slice(1);
                if (normalized.length === 3) {
                    normalized = normalized.split('').map((c) => c + c).join('');
                }
                if (normalized.length !== 6) {
                    return `rgba(255, 255, 255, ${alpha})`;
                }
                const bigint = parseInt(normalized, 16);
                const r = (bigint >> 16) & 255;
                const g = (bigint >> 8) & 255;
                const b = bigint & 255;
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }

            if (value.startsWith('rgba')) {
                return value;
            }

            if (value.startsWith('rgb')) {
                return value.replace('rgb', 'rgba').replace(')', `, ${alpha})`);
            }

            if (value.startsWith('hsla')) {
                return value;
            }

            if (value.startsWith('hsl')) {
                return value.replace('hsl', 'hsla').replace(')', `, ${alpha})`);
            }

            return `rgba(255, 255, 255, ${alpha})`;
        }

        function extractPromptAccent(prompt) {
            if (!prompt) return '#6366f1';
            const hash = Array.from(prompt).reduce((acc, char) => acc + char.charCodeAt(0), 0);
            const hue = (hash * 37) % 360;
            return `hsl(${hue}, 75%, 62%)`;
        }

        function createOverlayImage(config, theme, promptText) {
            const cacheKey = `${config.key}-${theme.label}-${promptText}`;
            if (gradientCache.has(cacheKey)) {
                return gradientCache.get(cacheKey).cloneNode(true);
            }

            const canvas = createOverlayCanvas(720, 960);
            const ctx = canvas.getContext('2d');
            if (!ctx) return document.createElement('canvas');

            const palette = theme.palette;
            const promptColor = extractPromptAccent(promptText);

            drawGradientOverlay(ctx, config.gradient, palette, promptColor);
            drawAccent(ctx, config.accent, palette, promptColor);
            drawNoise(ctx, config.noise);

            const img = new Image();
            img.src = canvas.toDataURL('image/png');
            gradientCache.set(cacheKey, img);
            return img.cloneNode(true);
        }

        function applyPlacements(wrapper, placements) {
            const previews = wrapper.querySelectorAll('.result-preview');
            if (!placements || !previews.length) return;
            previews.forEach((preview, index) => {
                const config = placements[index] || placements.default?.[index];
                if (!config) return;
                preview.style.setProperty('--translate-x', `${config.x}px`);
                preview.style.setProperty('--translate-y', `${config.y}px`);
                preview.style.setProperty('--scale', config.scale);
                preview.style.setProperty('--rotate', `${config.rotate}deg`);
            });
        }

        function createResultCard(themeKey, variant, promptText, poseIndex) {
            const card = resultTemplate.content.firstElementChild.cloneNode(true);
            const theme = themeOptions[themeKey];
            const palette = theme?.palette;
            const promptColor = extractPromptAccent(promptText);

            const poseBadge = card.querySelector('.pose-badge');
            if (poseBadge) {
                poseBadge.textContent = variant.badge;
                poseBadge.style.background = palette?.accent;
            }

            const overlayImage = createOverlayImage(variant, theme, promptText);
            overlayImage.className = 'result-overlay';

            const wrapper = card.querySelector('.result-thumb-wrapper');
            if (wrapper) {
                wrapper.appendChild(overlayImage);
                wrapper.style.setProperty('--prompt-color', promptColor);
                wrapper.dataset.pose = variant.title;
            }

            const styleLabel = card.querySelector('.result-style');
            if (styleLabel) {
                styleLabel.textContent = variant.title;
            }

            const promptLabel = card.querySelector('.result-prompt');
            if (promptLabel) {
                promptLabel.textContent = promptText;
            }

            const desc = card.querySelector('.result-desc');
            if (desc) {
                desc.textContent = variant.description(theme, promptText);
            }

            card.dataset.poseIndex = poseIndex;
            return card;
        }

        function updateResults(data, themeKey, promptText) {
            clearResults();
            showEmptyState(false);

            if (!Array.isArray(data) || !data.length) {
                updateFormStatus('Tidak ada hasil dari server.', 'error');
                showEmptyState(true);
                return;
            }

            const fragment = document.createDocumentFragment();
            data.forEach((item, index) => {
                const variant = poseVariants[index] || poseVariants[index % poseVariants.length];
                const card = createResultCard(themeKey, variant, promptText, index);
                const image = card.querySelector('.result-thumb');
                const imageUrl = item?.imageUrl || item?.url || '';
                if (image && imageUrl) {
                    image.src = imageUrl;
                }
                const downloadButton = card.querySelector('.result-download');
                if (downloadButton) {
                    downloadButton.addEventListener('click', () => {
                        if (!imageUrl) return;
                        const link = document.createElement('a');
                        link.href = imageUrl;
                        link.download = item?.downloadName || `${variant.key || 'result'}-${Date.now()}.png`;
                        link.click();
                    });
                }
                fragment.appendChild(card);
            });

            resultGrid.appendChild(fragment);
        }

        function validateFiles(files) {
            if (files.length < MIN_FILES) {
                updateFormStatus(`Tambahkan minimal ${MIN_FILES} foto referensi.`, 'error');
                return false;
            }
            if (files.length > MAX_FILES) {
                updateFormStatus(`Maksimal ${MAX_FILES} foto yang bisa diunggah.`, 'error');
                return false;
            }
            return true;
        }

        async function submitForm(event) {
            event.preventDefault();
            updateFormStatus('Menyiapkan sesi...', 'info');

            const themeKey = themeSelect.value;
            const promptText = promptStyleInput.value.trim();

            if (!validateFiles(selectedFiles)) {
                return;
            }

            const imageFiles = selectedFiles.filter((file) => isImageFile(file));
            if (imageFiles.length < MIN_FILES) {
                updateFormStatus(`Tambahkan minimal ${MIN_FILES} foto referensi.`, 'error');
                return;
            }

            const usableFiles = imageFiles.slice(0, MAX_FILES);

            setLoadingState(true);
            showEmptyState(false);
            clearResults();
            showSkeletons(4);

            try {
                const results = await generateStyledImages(themeKey, promptText, usableFiles);
                if (!results.length) {
                    throw new Error('Tidak dapat membuat komposisi dari foto yang dipilih.');
                }

                updateResults(results, themeKey, promptText);
                const themeName = themeOptions[themeKey]?.label || themeKey;
                const promptLabel = promptText ? `bertema "${promptText}"` : `dengan tema ${themeName}`;
                updateFormStatus(`Selesai! 4 pose multi-reference ${promptLabel} siap diunduh.`, 'success');
            } catch (error) {
                console.error(error);
                updateFormStatus(error.message || 'Terjadi kesalahan saat generate.', 'error');
                showEmptyState(true);
            } finally {
                clearSkeletons();
                setLoadingState(false);
            }
        }

        function handleFiles(files) {
            const normalized = normalizeFileList(files);
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

            if (selectedFiles.length && selectedFiles.length < MIN_FILES) {
                messages.push(`Tambahkan minimal ${MIN_FILES} foto referensi.`);
            }

            if (!selectedFiles.length) {
                messages.push('Unggah minimal dua foto referensi terlebih dahulu.');
            }

            if (messages.length) {
                updateFormStatus(messages.join(' '), 'info');
            } else {
                updateFormStatus('');
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

        renderThemeHint(themeSelect.value);
        ensurePromptTemplate(themeSelect.value, true);
        renderPreview();
        showEmptyState(true);
    })();
    </script>
</body>
</html>
