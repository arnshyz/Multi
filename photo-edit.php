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
    <title>Flash 2.5 Multi Reference Studio</title>
    <meta name="description" content="Gabungkan 2-3 foto referensi dengan Gemini Flash 2.5 Mode 3 dan hasilkan empat pose berbeda dalam satu tema.">
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
                <h1>Multi Reference Studio</h1>
                <p>Padukan 2-3 foto referensi dan hasilkan empat pose unik berbasis tema menggunakan <strong>Gemini Flash 2.5 Mode 3</strong>.</p>
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
                        <p>Pilih tema, tentukan prompt style penggabungan, lalu unggah 2-3 foto referensi untuk menjalankan sesi Flash 2.5 Mode 3.</p>
                    </div>
                </div>

                <form id="editForm" class="edit-form" novalidate>
                    <div class="form-field">
                        <label class="field-label">Model yang digunakan</label>
                        <div class="model-chip" role="text">Gemini Flash 2.5 · Mode 3 Multi-Reference</div>
                        <p class="field-hint">Model dikunci ke Flash 2.5 agar blending referensi konsisten.</p>
                    </div>

                    <div class="form-field">
                        <label for="themeSelect" class="field-label">Tema dasar</label>
                        <select id="themeSelect" class="field-select" aria-describedby="themeHint">
                            <option value="romantic">Romantic Fusion</option>
                            <option value="urban">Neo Urban Story</option>
                            <option value="tropical">Tropical Journey</option>
                            <option value="heritage">Heritage Elegance</option>
                        </select>
                        <p id="themeHint" class="field-hint"></p>
                    </div>

                    <div class="form-field">
                        <label for="promptStyle" class="field-label">Prompt style / Tema penggabungan</label>
                        <textarea id="promptStyle" class="field-textarea" rows="4" placeholder="contoh: dreamy editorial portrait blending our references with golden hour glow"></textarea>
                        <p class="field-hint">Tambahkan detail suasana, mood, atau warna yang ingin disatukan. <button type="button" class="link" id="resetPromptButton">Gunakan template tema</button></p>
                    </div>

                    <div class="form-field">
                        <label class="field-label" for="referenceInput">Foto referensi</label>
                        <div id="dropzone" class="dropzone" tabindex="0">
                            <input id="referenceInput" type="file" accept="image/*" multiple hidden>
                            <div class="dropzone-icon" aria-hidden="true">📁</div>
                            <div class="dropzone-copy">
                                <strong>Tarik &amp; lepas</strong> atau <button type="button" class="link" id="browseButton">pilih dari perangkat</button>
                                <span class="dropzone-sub">Gunakan 2–3 foto (JPG, PNG, atau WEBP) untuk digabungkan.</span>
                            </div>
                        </div>
                        <div id="referencePreview" class="preview-grid" aria-live="polite"></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="generateButton">Generate 4 Pose Multi-Reference</button>
                        <div class="form-status" id="formStatus" role="status"></div>
                    </div>
                </form>
            </section>

            <section class="panel panel-results" aria-labelledby="resultTitle">
                <div class="panel-header">
                    <div>
                        <h2 id="resultTitle">Hasil Generate</h2>
                        <p>Output akan otomatis menampilkan empat pose berbeda dengan tema yang sama sesuai prompt style.</p>
                    </div>
                </div>

                <div id="resultGrid" class="result-grid" aria-live="polite">
                    <div class="empty-state" id="emptyState">
                        <span class="empty-emoji" aria-hidden="true">✨</span>
                        <p>Belum ada hasil. Unggah 2-3 foto referensi lalu jalankan Flash 2.5 Mode 3.</p>
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
                accent: { type: 'bars', count: 3, length: 860, width: 36, spacing: 42, angle: -18, color: 'highlight', opacity: 0.18, blend: 'screen' },
                noise: { opacity: 0.06, size: 110, blend: 'soft-light' },
                placements: {
                    2: [
                        { x: -92, y: 26, scale: 0.9, rotate: -5 },
                        { x: 92, y: -18, scale: 1.02, rotate: 5 }
                    ],
                    3: [
                        { x: -118, y: 12, scale: 0.88, rotate: -6 },
                        { x: 48, y: -42, scale: 1.02, rotate: 4 },
                        { x: 128, y: 78, scale: 0.85, rotate: 2 }
                    ],
                    default: [
                        { x: -100, y: 18, scale: 0.9, rotate: -5 },
                        { x: 84, y: -26, scale: 1.0, rotate: 4 },
                        { x: 128, y: 74, scale: 0.84, rotate: 2 }
                    ]
                },
                description(theme, prompt) {
                    const base = 'Layering dinamis yang menangkap gerak tubuh dan transisi antar referensi.';
                    return prompt ? `${base} Nuansa "${prompt}" diolah untuk memberi energi gerakan.` : base;
                }
            },
            {
                key: 'editorialFocus',
                badge: 'Pose 3',
                title: 'Pose 3 · Editorial Focus',
                fit: 0.92,
                filter: 'brightness(1.1) contrast(1.08) saturate(1.2)',
                overlay: { color: 'prompt', opacity: 0.18, blend: 'overlay' },
                gradient: {
                    angle: -48,
                    stops: [
                        { offset: 0, color: 'prompt', opacity: 0.3 },
                        { offset: 0.6, color: 'highlight', opacity: 0.18 },
                        { offset: 1, color: 'background', opacity: 0 }
                    ]
                },
                accent: { type: 'glow', radius: 260, color: 'accent', opacity: 0.28, blend: 'screen' },
                noise: { opacity: 0.05, size: 100, blend: 'overlay' },
                placements: {
                    2: [
                        { x: -46, y: -16, scale: 1.04, rotate: -3 },
                        { x: 66, y: 48, scale: 0.92, rotate: 4 }
                    ],
                    3: [
                        { x: -70, y: -24, scale: 1.0, rotate: -4 },
                        { x: 44, y: 56, scale: 0.92, rotate: 5 },
                        { x: 116, y: -28, scale: 0.88, rotate: 2 }
                    ],
                    default: [
                        { x: -60, y: -20, scale: 1.02, rotate: -4 },
                        { x: 56, y: 54, scale: 0.92, rotate: 4 },
                        { x: 112, y: -30, scale: 0.88, rotate: 2 }
                    ]
                },
                description(theme, prompt) {
                    const base = 'Pose editorial tegak dengan fokus styling dan detail busana yang konsisten.';
                    return prompt ? `${base} Atmosfer "${prompt}" menjaga kesatuan tone.` : base;
                }
            },
            {
                key: 'storyEnding',
                badge: 'Pose 4',
                title: 'Pose 4 · Story Ending',
                fit: 0.9,
                filter: 'brightness(1.02) contrast(1.15) saturate(1.05)',
                overlay: { color: 'accent', opacity: 0.14, blend: 'soft-light' },
                gradient: {
                    angle: 54,
                    stops: [
                        { offset: 0, color: 'highlight', opacity: 0.22 },
                        { offset: 0.5, color: 'prompt', opacity: 0.24 },
                        { offset: 1, color: 'background', opacity: 0 }
                    ]
                },
                accent: { type: 'ring', radius: 280, strokeWidth: 26, color: 'prompt', opacity: 0.24, blend: 'overlay' },
                noise: { opacity: 0.07, size: 96, blend: 'soft-light' },
                placements: {
                    2: [
                        { x: -96, y: 46, scale: 0.9, rotate: -5 },
                        { x: 96, y: -42, scale: 0.9, rotate: 4 }
                    ],
                    3: [
                        { x: -128, y: 54, scale: 0.86, rotate: -5 },
                        { x: 2, y: -36, scale: 1.0, rotate: 3 },
                        { x: 132, y: 64, scale: 0.86, rotate: 5 }
                    ],
                    default: [
                        { x: -110, y: 50, scale: 0.88, rotate: -5 },
                        { x: 0, y: -32, scale: 0.98, rotate: 3 },
                        { x: 128, y: 62, scale: 0.86, rotate: 5 }
                    ]
                },
                description(theme, prompt) {
                    const base = 'Ending shot bernuansa cinematic dengan cerita terpadu dari semua referensi.';
                    return prompt ? `${base} Tema "${prompt}" dipertahankan pada pencahayaan akhir.` : base;
                }
            }
        ];

        function stringToHslColor(str, s = 70, l = 52) {
            const input = str || '';
            let hash = 0;
            for (let i = 0; i < input.length; i += 1) {
                hash = input.charCodeAt(i) + ((hash << 5) - hash);
            }
            const hue = Math.abs(hash) % 360;
            return `hsl(${hue}, ${s}%, ${l}%)`;
        }

        function hexToRgba(hex, alpha = 1) {
            if (typeof hex !== 'string') {
                return `rgba(255, 255, 255, ${alpha})`;
            }
            let sanitized = hex.replace('#', '');
            if (sanitized.length === 3) {
                sanitized = sanitized.split('').map(ch => ch + ch).join('');
            }
            if (sanitized.length !== 6) {
                return `rgba(255, 255, 255, ${alpha})`;
            }
            const num = parseInt(sanitized, 16);
            const r = (num >> 16) & 255;
            const g = (num >> 8) & 255;
            const b = num & 255;
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }

        function withAlpha(color, alpha = 1) {
            if (!color) {
                return `rgba(255, 255, 255, ${alpha})`;
            }
            if (color.startsWith('#')) {
                return hexToRgba(color, alpha);
            }
            const rgbaMatch = color.match(/^rgba?\(([^)]+)\)$/i);
            if (rgbaMatch) {
                const parts = rgbaMatch[1].split(',').map(part => part.trim());
                const [r, g, b] = parts;
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }
            return color;
        }

        function resolveColor(token, theme, promptColor) {
            if (!token) {
                return '#ffffff';
            }
            if (token === 'accent') {
                return theme?.palette?.accent || promptColor;
            }
            if (token === 'highlight') {
                return theme?.palette?.highlight || theme?.palette?.accent || promptColor;
            }
            if (token === 'overlay') {
                return theme?.palette?.overlayColor || theme?.palette?.accent || promptColor;
            }
            if (token === 'prompt') {
                return promptColor;
            }
            if (token === 'background') {
                return theme?.palette?.background || '#0f172a';
            }
            return token;
        }

        function applyOverlay(ctx, canvas, overlayConfig) {
            if (!overlayConfig) {
                return;
            }
            const opacity = typeof overlayConfig.opacity === 'number' ? overlayConfig.opacity : 0;
            if (opacity <= 0) {
                return;
            }
            ctx.save();
            ctx.globalAlpha = opacity;
            ctx.globalCompositeOperation = overlayConfig.blend || 'source-over';
            ctx.fillStyle = withAlpha(overlayConfig.color, overlayConfig.baseAlpha ?? 1);
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.restore();
        }

        function applyGradient(ctx, canvas, gradientConfig, theme, promptColor) {
            if (!gradientConfig || !Array.isArray(gradientConfig.stops) || gradientConfig.stops.length === 0) {
                return;
            }
            const angle = (gradientConfig.angle || 0) * (Math.PI / 180);
            const diagonal = Math.sqrt((canvas.width ** 2) + (canvas.height ** 2));
            const half = diagonal / 2;
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const x0 = centerX + Math.cos(angle) * -half;
            const y0 = centerY + Math.sin(angle) * -half;
            const x1 = centerX + Math.cos(angle) * half;
            const y1 = centerY + Math.sin(angle) * half;
            const gradient = ctx.createLinearGradient(x0, y0, x1, y1);
            gradientConfig.stops.forEach(stop => {
                const offset = Math.max(0, Math.min(1, stop.offset ?? 0));
                const colorToken = resolveColor(stop.color, theme, promptColor);
                const color = withAlpha(colorToken, stop.opacity ?? 1);
                gradient.addColorStop(offset, color);
            });
            ctx.save();
            ctx.globalCompositeOperation = gradientConfig.blend || 'soft-light';
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.restore();
        }

        function drawAccent(ctx, canvas, accentConfig, theme, promptColor) {
            if (!accentConfig) {
                return;
            }
            const colorToken = resolveColor(accentConfig.color, theme, promptColor);
            const color = withAlpha(colorToken, 1);
            if (accentConfig.type === 'ring') {
                const radius = accentConfig.radius || Math.min(canvas.width, canvas.height) * 0.42;
                ctx.save();
                ctx.translate(canvas.width / 2 + (accentConfig.x || 0), canvas.height / 2 + (accentConfig.y || 0));
                ctx.globalCompositeOperation = accentConfig.blend || 'screen';
                ctx.globalAlpha = accentConfig.opacity ?? 0.25;
                ctx.lineWidth = accentConfig.strokeWidth || 32;
                ctx.strokeStyle = color;
                ctx.beginPath();
                ctx.arc(0, 0, radius, 0, Math.PI * 2);
                ctx.stroke();
                ctx.restore();
                return;
            }
            if (accentConfig.type === 'bars') {
                const count = accentConfig.count || 2;
                const length = accentConfig.length || canvas.width * 0.8;
                const width = accentConfig.width || 30;
                const spacing = accentConfig.spacing || 48;
                const angle = (accentConfig.angle || 0) * (Math.PI / 180);
                ctx.save();
                ctx.translate(canvas.width / 2 + (accentConfig.x || 0), canvas.height / 2 + (accentConfig.y || 0));
                ctx.rotate(angle);
                ctx.globalCompositeOperation = accentConfig.blend || 'screen';
                ctx.globalAlpha = accentConfig.opacity ?? 0.2;
                ctx.fillStyle = color;
                for (let i = 0; i < count; i += 1) {
                    const offset = (i - (count - 1) / 2) * (spacing + width);
                    ctx.fillRect(-length / 2, offset - (width / 2), length, width);
                }
                ctx.restore();
                return;
            }
            if (accentConfig.type === 'glow') {
                const radius = accentConfig.radius || Math.max(canvas.width, canvas.height) * 0.45;
                const centerX = canvas.width / 2 + (accentConfig.x || 0);
                const centerY = canvas.height / 2 + (accentConfig.y || 0);
                const gradient = ctx.createRadialGradient(centerX, centerY, radius * 0.2, centerX, centerY, radius);
                gradient.addColorStop(0, withAlpha(color, accentConfig.opacity ?? 0.35));
                gradient.addColorStop(1, withAlpha(color, 0));
                ctx.save();
                ctx.globalCompositeOperation = accentConfig.blend || 'screen';
                ctx.fillStyle = gradient;
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.restore();
            }
        }

        function applyNoise(ctx, canvas, noiseConfig) {
            if (!noiseConfig) {
                return;
            }
            const opacity = typeof noiseConfig.opacity === 'number' ? noiseConfig.opacity : 0.05;
            if (opacity <= 0) {
                return;
            }
            const size = noiseConfig.size || 128;
            const noiseCanvas = document.createElement('canvas');
            noiseCanvas.width = size;
            noiseCanvas.height = size;
            const noiseCtx = noiseCanvas.getContext('2d');
            if (!noiseCtx) {
                return;
            }
            const imageData = noiseCtx.createImageData(size, size);
            const buffer = imageData.data;
            for (let i = 0; i < buffer.length; i += 4) {
                const value = Math.random() * 255;
                buffer[i] = value;
                buffer[i + 1] = value;
                buffer[i + 2] = value;
                buffer[i + 3] = 255;
            }
            noiseCtx.putImageData(imageData, 0, 0);
            const pattern = ctx.createPattern(noiseCanvas, 'repeat');
            if (!pattern) {
                return;
            }
            ctx.save();
            ctx.globalAlpha = opacity;
            ctx.globalCompositeOperation = noiseConfig.blend || 'overlay';
            ctx.fillStyle = pattern;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.restore();
        }

        function resolvePlacement(variant, total, index) {
            const placements = variant.placements || {};
            const specific = placements[total];
            const fallback = placements.default || [];
            if (Array.isArray(specific) && specific.length) {
                return specific[Math.min(index, specific.length - 1)] || specific[specific.length - 1];
            }
            if (Array.isArray(fallback) && fallback.length) {
                return fallback[Math.min(index, fallback.length - 1)] || { x: 0, y: 0, scale: 1, rotate: 0 };
            }
            return { x: 0, y: 0, scale: 1, rotate: 0 };
        }

        function loadImage(sourceUrl) {
            return new Promise((resolve, reject) => {
                const image = new Image();
                image.decoding = 'async';
                image.onload = () => resolve(image);
                image.onerror = () => reject(new Error('Gagal memuat gambar referensi.'));
                image.src = sourceUrl;
            });
        }

        async function composeVariantImage(themeKey, theme, variant, sources, promptStyle) {
            const promptColor = stringToHslColor(`${themeKey}|${promptStyle || theme.label}`);
            const images = await Promise.all(sources.map(item => loadImage(item.dataUrl)));
            if (!images.length) {
                return '';
            }
            const canvas = document.createElement('canvas');
            const outputWidth = variant.width || 960;
            const outputHeight = variant.height || 1200;
            canvas.width = outputWidth;
            canvas.height = outputHeight;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                throw new Error('Browser tidak mendukung kanvas untuk pemrosesan gambar.');
            }

            ctx.fillStyle = theme?.palette?.background || '#0f172a';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const total = images.length;
            const fit = variant.fit || 0.9;
            images.forEach((image, index) => {
                const placement = resolvePlacement(variant, total, index);
                const baseScale = Math.min((canvas.width * fit) / Math.max(1, image.naturalWidth), (canvas.height * fit) / Math.max(1, image.naturalHeight));
                const scale = baseScale * (placement.scale != null ? placement.scale : 1);
                const drawWidth = Math.max(1, image.naturalWidth * scale);
                const drawHeight = Math.max(1, image.naturalHeight * scale);
                const centerX = canvas.width / 2 + (placement.x || 0);
                const centerY = canvas.height / 2 + (placement.y || 0);

                ctx.save();
                ctx.translate(centerX, centerY);
                const rotation = (placement.rotate || 0) * (Math.PI / 180);
                if (rotation) {
                    ctx.rotate(rotation);
                }
                ctx.filter = placement.filter || variant.filter || theme?.palette?.filter || 'none';
                ctx.globalAlpha = placement.alpha != null ? placement.alpha : (variant.alpha != null ? variant.alpha : (theme?.baseAlpha != null ? theme.baseAlpha : 0.9));
                ctx.globalCompositeOperation = placement.blend || variant.blend || 'source-over';
                ctx.drawImage(image, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
                ctx.restore();
            });

            ctx.filter = 'none';
            ctx.globalAlpha = 1;
            ctx.globalCompositeOperation = 'source-over';

            if (theme?.palette?.overlayColor && theme.palette.overlayOpacity > 0) {
                applyOverlay(ctx, canvas, {
                    color: resolveColor('overlay', theme, promptColor),
                    opacity: theme.palette.overlayOpacity,
                    blend: 'soft-light'
                });
            }

            if (variant.overlay) {
                applyOverlay(ctx, canvas, {
                    color: resolveColor(variant.overlay.color, theme, promptColor),
                    opacity: variant.overlay.opacity ?? 0.12,
                    blend: variant.overlay.blend || 'soft-light'
                });
            }

            if (variant.gradient) {
                applyGradient(ctx, canvas, variant.gradient, theme, promptColor);
            }

            if (variant.accent) {
                drawAccent(ctx, canvas, variant.accent, theme, promptColor);
            }

            if (variant.noise) {
                applyNoise(ctx, canvas, variant.noise);
            }

            return canvas.toDataURL('image/jpeg', 0.92);
        }

        async function generateStyledImages(themeKey, promptStyle, sources) {
            const theme = themeOptions[themeKey] || themeOptions.romantic;
            if (!sources.length) {
                return [];
            }
            const trimmedPrompt = (promptStyle || '').trim();
            const tasks = poseVariants.map((variant, index) => (
                composeVariantImage(themeKey, theme, variant, sources, trimmedPrompt).then(url => ({
                    url,
                    filename: `flash-25-${themeKey}-variant-${index + 1}.jpg`,
                    poseLabel: variant.badge || `Pose ${index + 1}`,
                    description: typeof variant.description === 'function' ? variant.description(theme, trimmedPrompt) : (variant.description || ''),
                    title: variant.title
                }))
            ));
            return Promise.all(tasks);
        }

        function setStatus(message, state) {
            if (!formStatus) {
                return;
            }
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
            for (let i = 0; i < 4; i += 1) {
                const fragment = skeletonTemplate.content.cloneNode(true);
                resultGrid.appendChild(fragment);
            }
        }

        function renderResults(themeKey, promptStyle, styledImages) {
            const theme = themeOptions[themeKey] || themeOptions.romantic;
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
            const promptLabel = (promptStyle || '').trim();
            styledImages.forEach((result, index) => {
                const fragment = resultTemplate.content.cloneNode(true);
                const image = fragment.querySelector('.result-thumb');
                const badge = fragment.querySelector('.pose-badge');
                const styleLabel = fragment.querySelector('.result-style');
                const promptEl = fragment.querySelector('.result-prompt');
                const description = fragment.querySelector('.result-desc');
                const downloadBtn = fragment.querySelector('.result-download');

                if (image) {
                    image.src = result.url;
                    image.alt = `Hasil Flash 2.5 ${theme.label} ${result.poseLabel || `Pose ${index + 1}`}`;
                }
                if (badge) {
                    badge.textContent = result.poseLabel || `Pose ${index + 1}`;
                }
                if (styleLabel) {
                    styleLabel.textContent = `${theme.label} · Mode 3 Multi-Reference`;
                }
                if (promptEl) {
                    if (promptLabel) {
                        promptEl.textContent = `Prompt style: ${promptLabel}`;
                        promptEl.hidden = false;
                    } else {
                        promptEl.textContent = '';
                        promptEl.hidden = true;
                    }
                }
                if (description) {
                    description.textContent = result.description || result.title || '';
                }
                if (downloadBtn) {
                    downloadBtn.dataset.url = result.url;
                    downloadBtn.dataset.filename = result.filename;
                }

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

        function isImageFile(file) {
            if (file.type && file.type.startsWith('image/')) {
                return true;
            }
            const name = (file.name || '').toLowerCase();
            return /\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i.test(name);
        }

        function handleFiles(fileList) {
            const files = Array.from(fileList || []).filter(isImageFile);
            if (!files.length) {
                selectedFiles = [];
                previewGrid.innerHTML = '';
                previewGrid.dataset.empty = 'true';
                setStatus('Format file tidak dikenali. Unggah foto dalam format JPG, PNG, WEBP, GIF, BMP, HEIC, atau HEIF.', 'error');
                return;
            }

            selectedFiles = files.slice(0, MAX_FILES);
            previewGrid.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const url = URL.createObjectURL(file);
                const figure = document.createElement('figure');
                figure.className = 'preview-item';

                const img = document.createElement('img');
                img.src = url;
                img.alt = file.name;
                img.addEventListener('load', () => URL.revokeObjectURL(url));

                const caption = document.createElement('figcaption');
                caption.textContent = `Ref ${index + 1} · ${file.name}`;

                figure.appendChild(img);
                figure.appendChild(caption);
                previewGrid.appendChild(figure);
            });
            previewGrid.dataset.empty = selectedFiles.length ? 'false' : 'true';

            if (files.length > MAX_FILES) {
                setStatus('Hanya 3 foto pertama yang digunakan untuk blending.', 'info');
            } else if (selectedFiles.length < MIN_FILES) {
                setStatus('Tambahkan minimal dua foto referensi untuk mode multi-image.', 'info');
            } else {
                setStatus('', '');
            }
        }

        function ensureFilesSelected() {
            if (selectedFiles.length >= MIN_FILES) {
                return true;
            }
            const message = selectedFiles.length ? 'Tambahkan satu foto lagi agar minimal dua referensi tersedia.' : 'Unggah minimal dua foto referensi terlebih dahulu.';
            setStatus(message, 'error');
            if (dropzone) {
                dropzone.focus();
            }
            return false;
        }

        function updateThemeUI(force = false) {
            const themeKey = themeSelect ? themeSelect.value : 'romantic';
            const theme = themeOptions[themeKey] || themeOptions.romantic;
            if (themeHint) {
                themeHint.textContent = theme.description;
            }
            if (promptStyleInput) {
                const currentValue = promptStyleInput.value.trim();
                if (force || !promptDirty || currentValue === '') {
                    promptStyleInput.value = theme.template;
                    promptDirty = false;
                }
                promptStyleInput.placeholder = theme.template;
            }
        }

        if (dropzone) {
            dropzone.addEventListener('click', () => {
                referenceInput?.click();
            });

            dropzone.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    referenceInput?.click();
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
        }

        if (referenceInput) {
            referenceInput.addEventListener('change', () => {
                if (referenceInput.files) {
                    handleFiles(referenceInput.files);
                }
            });
        }

        if (browseButton) {
            browseButton.addEventListener('click', () => {
                referenceInput?.click();
            });
        }

        if (resultGrid) {
            resultGrid.addEventListener('click', (event) => {
                const target = event.target;
                if (target && target.classList && target.classList.contains('result-download')) {
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
        }

        if (form) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                setStatus('', '');
                if (!ensureFilesSelected()) {
                    return;
                }

                const themeKey = themeSelect ? themeSelect.value : 'romantic';
                const promptStyle = promptStyleInput ? promptStyleInput.value : '';
                const theme = themeOptions[themeKey] || themeOptions.romantic;

                generateButton.disabled = true;
                generateButton.classList.add('loading');
                setStatus('Menggabungkan referensi dengan Flash 2.5 Mode 3…', 'info');
                showSkeletons();

                try {
                    const sources = await Promise.all(selectedFiles.map(fileToDataUrl));
                    const styledImages = await generateStyledImages(themeKey, promptStyle, sources);
                    renderResults(themeKey, promptStyle, styledImages);
                    const trimmedPrompt = (promptStyle || '').trim();
                    const promptMessage = trimmedPrompt ? `bertema "${trimmedPrompt}"` : `dengan tema ${theme.label}`;
                    setStatus(`Selesai! 4 pose multi-reference ${promptMessage} siap diunduh.`, 'success');
                } catch (error) {
                    console.error(error);
                    clearResults();
                    if (emptyState) {
                        emptyState.hidden = false;
                        resultGrid.appendChild(emptyState);
                    }
                    setStatus(error.message || 'Terjadi kesalahan saat menghasilkan hasil edit.', 'error');
                } finally {
                    generateButton.disabled = false;
                    generateButton.classList.remove('loading');
                }
            });
        }

        if (themeSelect) {
            themeSelect.addEventListener('change', () => {
                const wasDirty = promptDirty;
                updateThemeUI(false);
                if (wasDirty && promptDirty) {
                    setStatus('Prompt style dipertahankan karena sudah dimodifikasi. Gunakan tombol template untuk mengganti.', 'info');
                } else {
                    setStatus('', '');
                }
            });
        }

        if (promptStyleInput) {
            promptStyleInput.addEventListener('input', () => {
                promptDirty = true;
                setStatus('', '');
            });
        }

        if (resetPromptButton) {
            resetPromptButton.addEventListener('click', () => {
                promptDirty = false;
                updateThemeUI(true);
                setStatus('Template tema diterapkan ulang.', 'info');
                promptStyleInput?.focus();
            });
        }

        updateThemeUI(true);
        if (previewGrid) {
            previewGrid.dataset.empty = 'true';
        }
    })();
    </script>
</body>
</html>
