<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

app_load_env(__DIR__ . '/.env');
const AUTH_DEFAULT_USERNAME = 'admin';
const AUTH_DEFAULT_PASSWORD_HASH = '$2y$12$8d6CiI0X1xIzaIPxtm6gWujpCv.nUoI6RxiX8MDqq9jia83DeQqGm';

function auth_default_credential_values(): array
{
    return [
        'username' => AUTH_DEFAULT_USERNAME,
        'password_hash' => AUTH_DEFAULT_PASSWORD_HASH,
    ];
}

function auth_platform_default_generators(?string $now = null): array
{
    $now = $now ?: gmdate('c');

    return [
        'imageGen' => [
            'key' => 'imageGen',
            'label' => 'Image Generator',
            'description' => 'Generate image assets dari prompt teks.',
            'enabled' => true,
            'updated_at' => $now,
        ],
        'imageEdit' => [
            'key' => 'imageEdit',
            'label' => 'Image Editing',
            'description' => 'Edit dan upscale image menggunakan Freepik tools.',
            'enabled' => true,
            'updated_at' => $now,
        ],
        'videoGen' => [
            'key' => 'videoGen',
            'label' => 'Video Generator',
            'description' => 'Buat video otomatis dari prompt dan referensi.',
            'enabled' => true,
            'updated_at' => $now,
        ],
        'flashPhotoEdit' => [
            'key' => 'flashPhotoEdit',
            'label' => 'Flash Photo Edit',
            'description' => 'Studio Flash 2.5 multi referensi.',
            'enabled' => true,
            'updated_at' => $now,
        ],
        'lipsync' => [
            'key' => 'lipsync',
            'label' => 'Lipsync Studio',
            'description' => 'Sinkronisasi bibir otomatis dari video dan audio.',
            'enabled' => true,
            'updated_at' => $now,
        ],
        'filmmaker' => [
            'key' => 'filmmaker',
            'label' => 'Filmmaker',
            'description' => 'Generator rangkaian scene sinematik.',
            'enabled' => true,
            'updated_at' => $now,
        ],
        'ugc' => [
            'key' => 'ugc',
            'label' => 'UGC Tool',
            'description' => 'Generator ide konten UGC dan animasi.',
            'enabled' => true,
            'updated_at' => $now,
        ],
        'audioGen' => [
            'key' => 'audioGen',
            'label' => 'Audio Generator',
            'description' => 'Konversi teks menjadi audio natural (TTS).',
            'enabled' => true,
            'updated_at' => $now,
        ],
    ];
}

function auth_platform_defaults(?string $now = null): array
{
    $now = $now ?: gmdate('c');

    return [
        'maintenance' => [
            'active' => false,
            'message' => 'Website sedang maintenance. Kami segera kembali!',
            'updated_at' => $now,
        ],
        'generators' => auth_platform_default_generators($now),
    ];
}

function auth_platform_normalize($platform, ?string $now = null): array
{
    $now = $now ?: gmdate('c');
    $defaults = auth_platform_defaults($now);

    if (!is_array($platform)) {
        $platform = $defaults;
    }

    $normalized = [];

    $maintenance = $platform['maintenance'] ?? [];
    $message = isset($maintenance['message']) ? trim((string)$maintenance['message']) : '';
    if ($message === '') {
        $message = $defaults['maintenance']['message'];
    }
    $normalized['maintenance'] = [
        'active' => !empty($maintenance['active']),
        'message' => $message,
        'updated_at' => isset($maintenance['updated_at']) && is_string($maintenance['updated_at']) && $maintenance['updated_at'] !== ''
            ? $maintenance['updated_at']
            : $now,
    ];

    $normalized['generators'] = [];
    $existingGenerators = [];
    if (isset($platform['generators']) && is_array($platform['generators'])) {
        $existingGenerators = $platform['generators'];
    }

    foreach ($existingGenerators as $key => $item) {
        if (is_array($item) && isset($item['key']) && is_string($item['key'])) {
            $existingGenerators[$item['key']] = $item;
        }
    }

    foreach ($defaults['generators'] as $key => $defaultItem) {
        $item = $existingGenerators[$key] ?? [];
        $label = isset($item['label']) && trim((string)$item['label']) !== ''
            ? trim((string)$item['label'])
            : $defaultItem['label'];
        $description = isset($item['description']) && trim((string)$item['description']) !== ''
            ? trim((string)$item['description'])
            : ($defaultItem['description'] ?? '');
        $enabled = isset($item['enabled']) ? (bool)$item['enabled'] : (bool)$defaultItem['enabled'];
        $updatedAt = isset($item['updated_at']) && is_string($item['updated_at']) && $item['updated_at'] !== ''
            ? $item['updated_at']
            : $defaultItem['updated_at'];

        $normalized['generators'][$key] = [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'enabled' => $enabled,
            'updated_at' => $updatedAt,
        ];
    }

    foreach ($existingGenerators as $key => $item) {
        if (!isset($normalized['generators'][$key]) && is_array($item)) {
            $normalized['generators'][$key] = [
                'key' => is_string($key) ? $key : ($item['key'] ?? $key),
                'label' => isset($item['label']) ? (string)$item['label'] : ($item['key'] ?? 'Generator'),
                'description' => isset($item['description']) ? (string)$item['description'] : '',
                'enabled' => isset($item['enabled']) ? (bool)$item['enabled'] : true,
                'updated_at' => isset($item['updated_at']) && is_string($item['updated_at']) && $item['updated_at'] !== ''
                    ? $item['updated_at']
                    : $now,
            ];
        }
    }

    return $normalized;
}

function auth_storage_default(): array
{
    $now = gmdate('c');
    return [
        'users' => [],
        'apiKeys' => [],
        'meta' => [
            'rollingIndex' => 0,
        ],
        'platform' => auth_platform_defaults($now),
        'auth' => array_merge(
            auth_default_credential_values(),
            [
                'updated_at' => null,
            ]
        ),
        'accounts' => [],
        'security' => [
            'ipChecks' => [],
            'updated_at' => $now,
        ],
        'announcements' => [],
        'metrics' => auth_metrics_defaults($now),
    ];
}

function auth_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $options = [
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ];
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $options['cookie_secure'] = true;
        }
        session_start($options);
    }
}

function auth_storage_path(): string
{
    return __DIR__ . '/admin-data.json';
}

function auth_env_credentials(): ?array
{
    $username = getenv('APP_AUTH_USERNAME');
    if ($username === false) {
        $username = getenv('AUTH_USERNAME');
    }
    $passwordHash = getenv('APP_AUTH_PASSWORD_HASH');
    if ($passwordHash === false) {
        $passwordHash = getenv('AUTH_PASSWORD_HASH');
    }
    $passwordPlain = getenv('APP_AUTH_PASSWORD');
    if ($passwordPlain === false) {
        $passwordPlain = getenv('AUTH_PASSWORD');
    }

    $username = is_string($username) ? trim($username) : '';
    $passwordHash = is_string($passwordHash) ? trim($passwordHash) : '';
    $passwordPlain = is_string($passwordPlain) ? $passwordPlain : '';

    if ($username === '') {
        return null;
    }

    if ($passwordHash !== '') {
        return [
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => 'admin',
            'source' => 'env',
        ];
    }

    if ($passwordPlain !== '') {
        return [
            'username' => $username,
            'password_plain' => $passwordPlain,
            'role' => 'admin',
            'source' => 'env',
        ];
    }

    return null;
}

function auth_storage_write(array $data): bool
{
    $path = auth_storage_path();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $written = @file_put_contents($path, $json, LOCK_EX) !== false;
    if ($written) {
        auth_storage_cache($data);
        if (function_exists('auth_sync_accounts_to_db')) {
            auth_sync_accounts_to_db($data);
        }
    }

    return $written;
}

function auth_storage_cache(?array $data = null, bool $reset = false): ?array
{
    static $cached = null;

    if ($reset) {
        $cached = null;
        return null;
    }

    if ($data !== null) {
        $cached = $data;
    }

    return $cached;
}

function auth_storage_read(bool $fresh = false): array
{
    $cached = auth_storage_cache();
    if (!$fresh && $cached !== null) {
        return $cached;
    }

    $path = auth_storage_path();
    if (!is_file($path)) {
        $data = auth_storage_default();
        auth_storage_write($data);
        return $data;
    }

    $json = @file_get_contents($path);
    if ($json === false) {
        $data = auth_storage_default();
        auth_storage_write($data);
        return $data;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        $data = auth_storage_default();
        auth_storage_write($data);
        return $data;
    }

    $changed = false;

    if (!isset($data['users']) || !is_array($data['users'])) {
        $data['users'] = [];
        $changed = true;
    }

    if (!isset($data['apiKeys']) || !is_array($data['apiKeys'])) {
        $data['apiKeys'] = [];
        $changed = true;
    }

    if (!isset($data['platform']) || !is_array($data['platform'])) {
        $data['platform'] = auth_platform_defaults();
        $changed = true;
    } else {
        $normalizedPlatform = auth_platform_normalize($data['platform']);
        if ($normalizedPlatform !== $data['platform']) {
            $data['platform'] = $normalizedPlatform;
            $changed = true;
        }
    }

    if (!isset($data['meta']) || !is_array($data['meta'])) {
        $data['meta'] = ['rollingIndex' => 0];
        $changed = true;
    } else {
        if (!isset($data['meta']['rollingIndex']) || !is_numeric($data['meta']['rollingIndex'])) {
            $data['meta']['rollingIndex'] = 0;
            $changed = true;
        }
    }

    if (!isset($data['auth']) || !is_array($data['auth'])) {
        $data['auth'] = array_merge(auth_default_credential_values(), ['updated_at' => null]);
        $changed = true;
    } else {
        $auth = $data['auth'];
        $defaults = auth_default_credential_values();
        $username = isset($auth['username']) ? trim((string)$auth['username']) : '';
        if ($username === '') {
            $auth['username'] = $defaults['username'];
            $changed = true;
        } else {
            $auth['username'] = $username;
        }

        $passwordHash = isset($auth['password_hash']) ? trim((string)$auth['password_hash']) : '';
        if ($passwordHash === '' && isset($auth['password']) && $auth['password'] !== '') {
            $passwordHash = password_hash((string)$auth['password'], PASSWORD_DEFAULT);
            $auth['password_hash'] = $passwordHash;
            unset($auth['password']);
            $changed = true;
        }

        if ($passwordHash === '') {
            $auth['password_hash'] = $defaults['password_hash'];
            $changed = true;
        }

        if (!isset($auth['updated_at'])) {
            $auth['updated_at'] = null;
            $changed = true;
        }

        $data['auth'] = $auth;
    }

    if (!isset($data['accounts']) || !is_array($data['accounts'])) {
        $data['accounts'] = [];
        $changed = true;
    }

    if (!isset($data['security']) || !is_array($data['security'])) {
        $data['security'] = [
            'ipChecks' => [],
            'updated_at' => gmdate('c'),
        ];
        $changed = true;
    } else {
        if (!isset($data['security']['ipChecks']) || !is_array($data['security']['ipChecks'])) {
            $data['security']['ipChecks'] = [];
            $changed = true;
        }
        if (!isset($data['security']['updated_at'])) {
            $data['security']['updated_at'] = gmdate('c');
            $changed = true;
        }
    }

    if (!isset($data['announcements']) || !is_array($data['announcements'])) {
        $data['announcements'] = [];
        $changed = true;
    }
    $normalizedAnnouncements = auth_announcements_normalize($data['announcements']);
    if ($normalizedAnnouncements !== $data['announcements']) {
        $data['announcements'] = $normalizedAnnouncements;
        $changed = true;
    }

    if (!isset($data['metrics']) || !is_array($data['metrics'])) {
        $data['metrics'] = auth_metrics_defaults();
        $changed = true;
    }
    $normalizedMetrics = auth_metrics_normalize($data['metrics']);
    if ($normalizedMetrics !== $data['metrics']) {
        $data['metrics'] = $normalizedMetrics;
        $changed = true;
    }

    $data['accounts'] = array_values(array_map('auth_normalize_account', $data['accounts']));

    if (!auth_accounts_contains_admin($data['accounts'])) {
        $data['accounts'][] = auth_build_default_account();
        $changed = true;
    }

    if ($changed) {
        auth_storage_write($data);
    } else {
        auth_storage_cache($data);
    }

    return $data;
}

function auth_platform_state(): array
{
    $data = auth_storage_read();
    return auth_platform_normalize($data['platform'] ?? null);
}

function auth_platform_public_view(): array
{
    $platform = auth_platform_state();
    $generators = [];
    foreach ($platform['generators'] as $key => $item) {
        $generators[$key] = [
            'key' => $item['key'],
            'label' => $item['label'],
            'description' => $item['description'] ?? '',
            'enabled' => !empty($item['enabled']),
            'updated_at' => $item['updated_at'] ?? null,
        ];
    }

    return [
        'maintenance' => [
            'active' => !empty($platform['maintenance']['active']),
            'message' => $platform['maintenance']['message'] ?? '',
            'updated_at' => $platform['maintenance']['updated_at'] ?? null,
        ],
        'generators' => $generators,
    ];
}

function auth_platform_admin_view($platform = null): array
{
    $platform = auth_platform_normalize($platform);
    $generators = [];
    foreach ($platform['generators'] as $item) {
        $generators[] = [
            'key' => $item['key'],
            'label' => $item['label'],
            'description' => $item['description'] ?? '',
            'enabled' => !empty($item['enabled']),
            'updated_at' => $item['updated_at'] ?? null,
        ];
    }

    return [
        'maintenance' => [
            'active' => !empty($platform['maintenance']['active']),
            'message' => $platform['maintenance']['message'] ?? '',
            'updated_at' => $platform['maintenance']['updated_at'] ?? null,
        ],
        'generators' => $generators,
    ];
}

function auth_metrics_defaults(?string $now = null): array
{
    $now = $now ?: gmdate('c');

    return [
        'total_generations' => 0,
        'recent_activity' => [],
        'updated_at' => $now,
    ];
}

function auth_metrics_normalize($metrics, ?string $now = null): array
{
    $now = $now ?: gmdate('c');
    if (!is_array($metrics)) {
        $metrics = [];
    }

    $normalized = [
        'total_generations' => isset($metrics['total_generations'])
            ? max(0, (int)$metrics['total_generations'])
            : 0,
        'recent_activity' => [],
        'updated_at' => isset($metrics['updated_at']) && is_string($metrics['updated_at']) && $metrics['updated_at'] !== ''
            ? (string)$metrics['updated_at']
            : $now,
    ];

    if (isset($metrics['recent_activity']) && is_array($metrics['recent_activity'])) {
        foreach ($metrics['recent_activity'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $entryId = isset($item['id']) && $item['id'] !== '' ? (string)$item['id'] : uniqid('act_', true);
            $timestamp = isset($item['timestamp']) && $item['timestamp'] !== ''
                ? (string)$item['timestamp']
                : $now;

            $normalized['recent_activity'][] = [
                'id' => $entryId,
                'type' => isset($item['type']) ? (string)$item['type'] : 'generation',
                'source' => isset($item['source']) ? (string)$item['source'] : '',
                'user_id' => isset($item['user_id']) && $item['user_id'] !== '' ? (string)$item['user_id'] : null,
                'username' => isset($item['username']) ? (string)$item['username'] : '',
                'detail' => isset($item['detail']) ? (string)$item['detail'] : '',
                'timestamp' => $timestamp,
            ];
        }
    }

    $normalized['recent_activity'] = array_slice($normalized['recent_activity'], 0, 20);

    return $normalized;
}

function auth_metrics_snapshot(): array
{
    $data = auth_storage_read();
    $metrics = auth_metrics_normalize($data['metrics'] ?? null);
    $accounts = isset($data['accounts']) && is_array($data['accounts'])
        ? array_map('auth_normalize_account', $data['accounts'])
        : [];

    $now = time();
    $threshold = 15 * 60; // 15 menit
    $onlineUsers = 0;
    $generatorUsers = 0;

    foreach ($accounts as $account) {
        if (!is_array($account)) {
            continue;
        }

        $isAdmin = ($account['role'] ?? 'user') === 'admin';
        $isRestricted = auth_is_account_restricted($account);

        if (!$isAdmin && !$isRestricted && !empty($account['last_login_at'])) {
            $ts = strtotime((string)$account['last_login_at']);
            if ($ts !== false && ($now - $ts) <= $threshold) {
                $onlineUsers++;
            }
        }

        if (!$isAdmin && !$isRestricted && !empty($account['generation_count'])) {
            $generatorUsers++;
        }
    }

    return [
        'online_users' => $onlineUsers,
        'total_generations' => (int)$metrics['total_generations'],
        'generator_users' => $generatorUsers,
        'recent_activity' => $metrics['recent_activity'],
        'updated_at' => $metrics['updated_at'],
    ];
}

function auth_metrics_record_generation(string $source, array $context = []): void
{
    $source = trim($source);
    if ($source === '') {
        $source = 'unknown';
    }

    $data = auth_storage_read();
    $metrics = auth_metrics_normalize($data['metrics'] ?? null);
    $now = gmdate('c');

    $metrics['total_generations'] = (int)$metrics['total_generations'] + 1;

    $entry = [
        'id' => uniqid('act_', true),
        'type' => isset($context['type']) ? (string)$context['type'] : 'generation',
        'source' => $source,
        'user_id' => isset($context['user_id']) && $context['user_id'] !== '' ? (string)$context['user_id'] : null,
        'username' => isset($context['username']) ? (string)$context['username'] : '',
        'detail' => isset($context['detail']) ? (string)$context['detail'] : '',
        'timestamp' => $now,
    ];

    array_unshift($metrics['recent_activity'], $entry);
    $metrics['recent_activity'] = array_slice($metrics['recent_activity'], 0, 20);
    $metrics['updated_at'] = $now;

    if ($entry['user_id'] !== null) {
        foreach ($data['accounts'] as &$account) {
            if (!is_array($account) || ($account['id'] ?? null) !== $entry['user_id']) {
                continue;
            }
            $normalized = auth_normalize_account($account);
            $normalized['generation_count'] = (int)$normalized['generation_count'] + 1;
            $normalized['last_generation_at'] = $now;
            $normalized['updated_at'] = $now;
            $account = $normalized;
            break;
        }
        unset($account);
    }

    $data['metrics'] = $metrics;
    auth_storage_write($data);
}

function auth_announcements_normalize($announcements, ?string $now = null): array
{
    $now = $now ?: gmdate('c');
    if (!is_array($announcements)) {
        $announcements = [];
    }

    $normalized = [];
    foreach ($announcements as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = isset($item['id']) && $item['id'] !== '' ? (string)$item['id'] : uniqid('note_', true);
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            $title = 'Informasi';
        }
        $description = trim((string)($item['description'] ?? ''));
        $publishedAt = isset($item['published_at']) && $item['published_at'] !== ''
            ? (string)$item['published_at']
            : $now;
        $createdAt = isset($item['created_at']) && $item['created_at'] !== ''
            ? (string)$item['created_at']
            : $now;
        $updatedAt = isset($item['updated_at']) && $item['updated_at'] !== ''
            ? (string)$item['updated_at']
            : $now;

        $normalized[] = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'published_at' => $publishedAt,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    usort($normalized, static function ($a, $b) {
        return strcmp($b['published_at'], $a['published_at']);
    });

    return $normalized;
}

function auth_announcements_admin_view(): array
{
    $data = auth_storage_read();
    return auth_announcements_normalize($data['announcements'] ?? []);
}

function auth_announcements_public_view(): array
{
    $items = auth_announcements_admin_view();
    $now = gmdate('c');

    return array_values(array_filter($items, static function ($item) use ($now) {
        if (!isset($item['published_at'])) {
            return false;
        }
        $ts = strtotime((string)$item['published_at']);
        if ($ts === false) {
            return false;
        }
        return $ts <= strtotime($now) + 1;
    }));
}

function auth_announcements_add(array $payload, array &$errors = [])
{
    $errors = [];
    $title = isset($payload['title']) ? trim((string)$payload['title']) : '';
    $description = isset($payload['description']) ? trim((string)$payload['description']) : '';
    $publishedRaw = isset($payload['published_at']) ? trim((string)$payload['published_at']) : '';

    if ($title === '') {
        $errors['title'] = 'Judul wajib diisi.';
    }
    if ($description === '') {
        $errors['description'] = 'Deskripsi wajib diisi.';
    }

    $publishedTs = $publishedRaw !== '' ? strtotime($publishedRaw) : false;
    if ($publishedTs === false) {
        $publishedTs = time();
    }
    $publishedAt = gmdate('c', $publishedTs);

    if ($errors) {
        return null;
    }

    $data = auth_storage_read();
    $announcements = auth_announcements_normalize($data['announcements'] ?? []);
    $now = gmdate('c');

    $announcement = [
        'id' => uniqid('note_', true),
        'title' => $title,
        'description' => $description,
        'published_at' => $publishedAt,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    array_unshift($announcements, $announcement);
    $announcements = auth_announcements_normalize($announcements);

    $data['announcements'] = $announcements;
    auth_storage_write($data);

    return $announcement;
}

function auth_announcements_update(string $id, array $changes, array &$errors = [])
{
    $errors = [];
    $id = trim($id);
    if ($id === '') {
        $errors['id'] = 'ID pengumuman wajib diisi.';
        return null;
    }

    $data = auth_storage_read();
    $announcements = auth_announcements_normalize($data['announcements'] ?? []);
    $found = null;

    foreach ($announcements as &$item) {
        if (($item['id'] ?? '') !== $id) {
            continue;
        }
        $found = &$item;
        break;
    }
    unset($item);

    if ($found === null) {
        $errors['id'] = 'Pengumuman tidak ditemukan.';
        return null;
    }

    if (array_key_exists('title', $changes)) {
        $title = trim((string)$changes['title']);
        if ($title === '') {
            $errors['title'] = 'Judul wajib diisi.';
        } else {
            $found['title'] = $title;
        }
    }

    if (array_key_exists('description', $changes)) {
        $description = trim((string)$changes['description']);
        if ($description === '') {
            $errors['description'] = 'Deskripsi wajib diisi.';
        } else {
            $found['description'] = $description;
        }
    }

    if (array_key_exists('published_at', $changes)) {
        $publishedRaw = trim((string)$changes['published_at']);
        $publishedTs = $publishedRaw !== '' ? strtotime($publishedRaw) : false;
        if ($publishedTs === false) {
            $errors['published_at'] = 'Tanggal publikasi tidak valid.';
        } else {
            $found['published_at'] = gmdate('c', $publishedTs);
        }
    }

    if ($errors) {
        return null;
    }

    $found['updated_at'] = gmdate('c');
    $announcements = auth_announcements_normalize($announcements);
    $data['announcements'] = $announcements;
    auth_storage_write($data);

    return $found;
}

function auth_announcements_delete(string $id, array &$errors = []): bool
{
    $errors = [];
    $id = trim($id);
    if ($id === '') {
        $errors['id'] = 'ID pengumuman wajib diisi.';
        return false;
    }

    $data = auth_storage_read();
    $announcements = auth_announcements_normalize($data['announcements'] ?? []);
    $before = count($announcements);
    $announcements = array_values(array_filter($announcements, static function ($item) use ($id) {
        return isset($item['id']) && $item['id'] !== $id;
    }));

    if ($before === count($announcements)) {
        $errors['id'] = 'Pengumuman tidak ditemukan.';
        return false;
    }

    $data['announcements'] = $announcements;
    auth_storage_write($data);

    return true;
}

function auth_is_account_restricted(array $account): bool
{
    if (($account['role'] ?? 'user') === 'admin') {
        return false;
    }

    return !empty($account['is_banned']) || !empty($account['is_blocked']);
}

function auth_platform_set_generator(string $key, bool $enabled)
{
    $data = auth_storage_read();
    $platform = auth_platform_normalize($data['platform'] ?? null);

    if (!isset($platform['generators'][$key])) {
        return null;
    }

    $platform['generators'][$key]['enabled'] = $enabled;
    $platform['generators'][$key]['updated_at'] = gmdate('c');

    $data['platform'] = $platform;
    if (!auth_storage_write($data)) {
        return null;
    }

    return $platform;
}

function auth_platform_set_maintenance(bool $active, ?string $message = null)
{
    $data = auth_storage_read();
    $platform = auth_platform_normalize($data['platform'] ?? null);

    $platform['maintenance']['active'] = $active;
    if ($message !== null) {
        $message = trim($message);
        if ($message === '') {
            $defaults = auth_platform_defaults();
            $message = $defaults['maintenance']['message'];
        }
        $platform['maintenance']['message'] = $message;
    }
    $platform['maintenance']['updated_at'] = gmdate('c');

    $data['platform'] = $platform;
    if (!auth_storage_write($data)) {
        return null;
    }

    return $platform;
}

function auth_build_default_account(): array
{
    $now = gmdate('c');
    $defaults = auth_default_credential_values();
    return [
        'id' => 'account-default-admin',
        'username' => $defaults['username'],
        'email' => '',
        'role' => 'admin',
        'display_name' => 'Administrator',
        'password_hash' => $defaults['password_hash'],
        'freepik_api_key' => null,
        'subscription' => 'pro',
        'coins' => 0,
        'is_banned' => false,
        'is_blocked' => false,
        'theme' => 'dark',
        'avatar_url' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'last_login_at' => null,
        'last_login_ip' => null,
        'ip_history' => [],
        'source' => 'default',
    ];
}

function auth_accounts_contains_admin(array $accounts): bool
{
    foreach ($accounts as $account) {
        if (!is_array($account)) {
            continue;
        }
        $role = strtolower((string)($account['role'] ?? ''));
        if ($role === 'admin') {
            return true;
        }
    }

    return false;
}

function auth_drive_normalize_storage_path($path): ?string
{
    if (!is_string($path)) {
        return null;
    }

    $path = trim(str_replace('\\', '/', $path));
    $path = preg_replace('#/{2,}#', '/', $path);
    $path = ltrim($path, '/');

    if ($path === '' || strpos($path, '..') !== false || strpos($path, "\0") !== false) {
        return null;
    }

    return $path;
}

function auth_drive_storage_info(?array $account): array
{
    $accountId = '';
    $username = '';
    if (is_array($account)) {
        $accountId = isset($account['id']) ? (string)$account['id'] : '';
        $username = isset($account['username']) ? (string)$account['username'] : '';
    }

    $candidate = $accountId !== '' ? $accountId : $username;
    if ($candidate === '') {
        $candidate = 'user';
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $candidate));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'user';
    }

    $relative = 'generated/' . $slug;
    $absolute = __DIR__ . '/' . $relative;

    return [
        'slug' => $slug,
        'relative' => $relative,
        'absolute' => $absolute,
    ];
}

function auth_drive_ensure_storage_directory(?array $account): array
{
    $info = auth_drive_storage_info($account);
    if (!is_dir($info['absolute'])) {
        @mkdir($info['absolute'], 0775, true);
    }

    return $info;
}

function auth_drive_extension_from_mime(?string $mime, string $kind = 'image'): string
{
    $mime = $mime ? strtolower(trim($mime)) : '';
    $map = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/bmp'  => 'bmp',
        'image/heic' => 'heic',
        'image/tiff' => 'tiff',
        'video/mp4'  => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-matroska' => 'mkv',
        'video/webm' => 'webm',
        'video/avi'  => 'avi',
    ];

    if ($mime !== '' && isset($map[$mime])) {
        return $map[$mime];
    }

    return $kind === 'video' ? 'mp4' : 'jpg';
}

function auth_drive_download_remote_asset(array $account, string $url, string $kind = 'image'): ?array
{
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return null;
    }

    $storageInfo = auth_drive_ensure_storage_directory($account);
    $dir = $storageInfo['absolute'];
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return null;
    }

    $parsedPath = parse_url($url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($parsedPath ?? '', PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }

    $data = null;
    $contentType = null;
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if (!$ch) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $data = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($errno || $data === false || $httpCode >= 400) {
            return null;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => 1,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);
        if ($data === false) {
            return null;
        }

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (stripos($headerLine, 'content-type:') === 0) {
                    $contentType = trim(substr($headerLine, strlen('content-type:')));
                } elseif (preg_match('#^HTTP/[0-9.]+\s+(\d{3})#i', $headerLine, $matches)) {
                    $httpCode = (int)$matches[1];
                }
            }
        }

        if ($httpCode >= 400 && $httpCode !== 0) {
            return null;
        }
    }

    if (!$ext) {
        $ext = auth_drive_extension_from_mime($contentType, $kind);
    }

    try {
        $rand = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $rand = substr(md5(mt_rand()), 0, 8);
    }

    $basename = 'drive_' . date('Ymd_His') . '_' . $rand . '.' . $ext;
    $dest = rtrim($dir, '/\\') . '/' . $basename;

    if (@file_put_contents($dest, $data) === false) {
        return null;
    }

    @chmod($dest, 0664);

    $relative = $storageInfo['relative'] . '/' . $basename;

    return [
        'relative_path' => $relative,
        'absolute_path' => $dest,
        'url' => app_public_url($relative),
    ];
}

function auth_drive_prepare_local_item(array $account, array $item): ?array
{
    if (!is_array($item)) {
        return null;
    }

    $type = isset($item['type']) && $item['type'] === 'video' ? 'video' : 'image';
    $url = isset($item['url']) ? trim((string)$item['url']) : '';
    $sourceUrl = isset($item['source_url']) ? trim((string)$item['source_url']) : '';
    if ($sourceUrl === '' && $url !== '') {
        $sourceUrl = $url;
    }

    $storagePath = isset($item['storage_path']) ? auth_drive_normalize_storage_path($item['storage_path']) : null;

    if ($storagePath) {
        $absolute = __DIR__ . '/' . $storagePath;
        if (is_file($absolute)) {
            $item['storage_path'] = $storagePath;
            $localUrl = app_public_url($storagePath);
            $item['url'] = $localUrl;
            if ($type !== 'video') {
                $item['thumbnail_url'] = $localUrl;
            }
            if ($sourceUrl !== '') {
                $item['source_url'] = $sourceUrl;
            }

            return $item;
        }

        unset($item['storage_path']);
        $storagePath = null;
    }

    if ($url !== '' && preg_match('#^https?://#i', $url)) {
        $downloaded = auth_drive_download_remote_asset($account, $url, $type);
        if ($downloaded) {
            $item['storage_path'] = $downloaded['relative_path'];
            $item['url'] = $downloaded['url'];
            if ($type !== 'video') {
                $item['thumbnail_url'] = $downloaded['url'];
            }
        }
    }

    if (isset($item['storage_path'])) {
        $normalized = auth_drive_normalize_storage_path($item['storage_path']);
        if ($normalized) {
            $item['storage_path'] = $normalized;
        } else {
            unset($item['storage_path']);
        }
    }

    if ($sourceUrl !== '') {
        $item['source_url'] = $sourceUrl;
    }

    if (!isset($item['url']) || $item['url'] === '') {
        return null;
    }

    return $item;
}

function auth_drive_localize_account_items(array $account, ?bool &$changed = null): array
{
    $changedFlag = false;
    $items = [];
    $seenStorage = [];
    $seenSource = [];

    if (!isset($account['drive_items']) || !is_array($account['drive_items'])) {
        $account['drive_items'] = [];
    }

    foreach ($account['drive_items'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $prepared = auth_drive_prepare_local_item($account, $entry);
        if ($prepared === null) {
            $changedFlag = true;
            continue;
        }

        if ($prepared !== $entry) {
            $changedFlag = true;
        }

        $storage = isset($prepared['storage_path']) ? auth_drive_normalize_storage_path($prepared['storage_path']) : null;
        $source = isset($prepared['source_url']) ? trim((string)$prepared['source_url']) : '';
        if ($source === '' && isset($prepared['url'])) {
            $source = trim((string)$prepared['url']);
            if ($source !== '') {
                $prepared['source_url'] = $source;
            }
        }

        if ($storage && isset($seenStorage[$storage])) {
            $changedFlag = true;
            continue;
        }
        if ($storage) {
            $prepared['storage_path'] = $storage;
            $seenStorage[$storage] = true;
        }

        if ($source !== '' && isset($seenSource[$source])) {
            $changedFlag = true;
            continue;
        }
        if ($source !== '') {
            $seenSource[$source] = true;
        }

        $items[] = $prepared;
    }

    usort($items, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    $account['drive_items'] = $items;

    if ($changed !== null) {
        $changed = $changedFlag;
    }

    return $account;
}

function auth_drive_delete_storage_files(array $account, array $paths): void
{
    if (!$paths) {
        return;
    }

    $info = auth_drive_storage_info($account);
    $baseDir = $info['absolute'];
    $realBase = realpath($baseDir) ?: null;

    foreach ($paths as $path) {
        $normalized = auth_drive_normalize_storage_path($path);
        if (!$normalized) {
            continue;
        }

        if (strpos($normalized, $info['relative']) !== 0) {
            continue;
        }

        $absolute = __DIR__ . '/' . $normalized;
        if (!is_file($absolute)) {
            continue;
        }

        $realTarget = realpath($absolute);
        if ($realBase && $realTarget && strpos($realTarget, $realBase) !== 0) {
            continue;
        }

        @unlink($absolute);
    }
}

function auth_normalize_drive_item($item): ?array
{
    if (!is_array($item)) {
        return null;
    }

    $id = isset($item['id']) && $item['id'] !== '' ? (string)$item['id'] : uniqid('drive_', true);
    $type = isset($item['type']) ? strtolower((string)$item['type']) : 'image';
    if (!in_array($type, ['image', 'video'], true)) {
        $type = 'image';
    }

    $url = isset($item['url']) ? trim((string)$item['url']) : '';
    if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
        return null;
    }

    $thumb = isset($item['thumbnail_url']) && $item['thumbnail_url'] !== ''
        ? (string)$item['thumbnail_url']
        : null;
    if ($thumb !== null && !preg_match('/^https?:\/\//i', $thumb)) {
        $thumb = null;
    }

    $model = isset($item['model']) && $item['model'] !== '' ? (string)$item['model'] : null;
    $prompt = isset($item['prompt']) && $item['prompt'] !== '' ? (string)$item['prompt'] : null;

    $createdAt = isset($item['created_at']) && $item['created_at'] !== ''
        ? (string)$item['created_at']
        : gmdate('c');

    $storagePath = null;
    if (isset($item['storage_path'])) {
        $storagePath = auth_drive_normalize_storage_path($item['storage_path']);
    }

    $sourceUrl = isset($item['source_url']) ? trim((string)$item['source_url']) : '';
    if ($sourceUrl === '' && $url !== '') {
        $sourceUrl = $url;
    }
    if ($sourceUrl !== '' && !preg_match('/^https?:\/\//i', $sourceUrl)) {
        $sourceUrl = '';
    }

    $normalized = [
        'id' => $id,
        'type' => $type,
        'url' => $url,
        'thumbnail_url' => $thumb,
        'model' => $model,
        'prompt' => $prompt,
        'created_at' => $createdAt,
    ];

    if ($storagePath) {
        $normalized['storage_path'] = $storagePath;
    }

    if ($sourceUrl !== '') {
        $normalized['source_url'] = $sourceUrl;
    }

    return $normalized;
}

function auth_normalize_pro_expires_at($value): ?string
{
    if (!isset($value)) {
        return null;
    }

    if ($value instanceof DateTimeInterface) {
        return $value->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }

    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return null;
    }

    return gmdate('c', $timestamp);
}

function auth_account_pro_expired(array $account): bool
{
    $subscription = strtolower((string)($account['subscription'] ?? 'free'));
    if ($subscription !== 'pro') {
        return false;
    }

    $expiresAt = $account['pro_expires_at'] ?? null;
    if (!$expiresAt) {
        return false;
    }

    $timestamp = strtotime((string)$expiresAt);
    if ($timestamp === false) {
        return false;
    }

    return $timestamp < time();
}

function auth_account_pro_active(array $account): bool
{
    $subscription = strtolower((string)($account['subscription'] ?? 'free'));
    if ($subscription !== 'pro') {
        return false;
    }

    if (auth_account_pro_expired($account)) {
        return false;
    }

    return true;
}

function auth_account_effective_subscription(array $account): string
{
    $subscription = strtolower((string)($account['subscription'] ?? 'free'));
    if ($subscription === '' || $subscription === 'pro' && auth_account_pro_expired($account)) {
        return 'free';
    }

    return $subscription ?: 'free';
}

function auth_normalize_account($account): array
{
    if (!is_array($account)) {
        $account = [];
    }

    $account['id'] = isset($account['id']) && $account['id'] !== '' ? (string)$account['id'] : uniqid('acct_', true);
    $account['username'] = isset($account['username']) ? trim((string)$account['username']) : '';
    $account['email'] = isset($account['email']) ? trim((string)$account['email']) : '';
    $account['display_name'] = isset($account['display_name']) ? trim((string)$account['display_name']) : '';
    if ($account['display_name'] === '' && $account['username'] !== '') {
        $pretty = str_replace(['.', '_', '-'], ' ', $account['username']);
        $account['display_name'] = ucwords($pretty);
    }
    $account['role'] = isset($account['role']) ? strtolower((string)$account['role']) : 'user';
    if ($account['role'] === '') {
        $account['role'] = 'user';
    }
    $account['password_hash'] = isset($account['password_hash']) ? (string)$account['password_hash'] : '';
    if (isset($account['password']) && $account['password'] !== '') {
        $account['password_hash'] = password_hash((string)$account['password'], PASSWORD_DEFAULT);
        unset($account['password']);
    }
    $account['freepik_api_key'] = isset($account['freepik_api_key']) && $account['freepik_api_key'] !== ''
        ? (string)$account['freepik_api_key']
        : null;
    $account['subscription'] = isset($account['subscription']) ? strtolower(trim((string)$account['subscription'])) : 'free';
    if ($account['subscription'] === '') {
        $account['subscription'] = 'free';
    }
    $account['pro_expires_at'] = auth_normalize_pro_expires_at($account['pro_expires_at'] ?? null);
    $account['coins'] = isset($account['coins']) ? max(0, (int)$account['coins']) : 0;
    $account['is_banned'] = !empty($account['is_banned']);
    $account['is_blocked'] = !empty($account['is_blocked']);
    $theme = isset($account['theme']) ? strtolower((string)$account['theme']) : 'dark';
    if (!in_array($theme, ['dark', 'light'], true)) {
        $theme = 'dark';
    }
    $account['theme'] = $theme;
    $account['avatar_url'] = isset($account['avatar_url']) && $account['avatar_url'] !== ''
        ? (string)$account['avatar_url']
        : null;
    $account['created_at'] = isset($account['created_at']) ? (string)$account['created_at'] : gmdate('c');
    $account['updated_at'] = isset($account['updated_at']) ? (string)$account['updated_at'] : gmdate('c');
    $account['last_login_at'] = isset($account['last_login_at']) ? (string)$account['last_login_at'] : null;
    $account['last_login_ip'] = isset($account['last_login_ip']) ? (string)$account['last_login_ip'] : null;
    $account['generation_count'] = isset($account['generation_count']) ? max(0, (int)$account['generation_count']) : 0;
    $account['last_generation_at'] = isset($account['last_generation_at']) && $account['last_generation_at'] !== ''
        ? (string)$account['last_generation_at']
        : null;
    $account['ip_history'] = array_values(array_filter(
        isset($account['ip_history']) && is_array($account['ip_history']) ? $account['ip_history'] : [],
        function ($entry) {
            return is_array($entry) && isset($entry['ip']) && $entry['ip'] !== '';
        }
    ));

    $driveItems = [];
    if (isset($account['drive_items']) && is_array($account['drive_items'])) {
        foreach ($account['drive_items'] as $item) {
            $normalized = auth_normalize_drive_item($item);
            if ($normalized) {
                $driveItems[$normalized['id']] = $normalized;
            }
        }
    }
    usort($driveItems, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    $account['drive_items'] = array_values($driveItems);

    $account['source'] = isset($account['source']) ? (string)$account['source'] : 'storage';

    return $account;
}

function auth_is_logged_in(): bool
{
    return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['auth_user']);
}

function auth_current_role(): ?string
{
    if (!auth_is_logged_in()) {
        return null;
    }

    $role = $_SESSION['auth_role'] ?? null;
    return is_string($role) && $role !== '' ? $role : null;
}

function auth_is_admin(): bool
{
    return auth_is_logged_in() && auth_current_role() === 'admin';
}

function auth_current_account_id(): ?string
{
    if (!auth_is_logged_in()) {
        return null;
    }

    $id = $_SESSION['auth_account_id'] ?? null;
    return is_string($id) && $id !== '' ? $id : null;
}

function auth_find_account(string $username): ?array
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }

    // 1) Coba ambil dari MySQL lebih dulu
    if (function_exists('db_get_connection')) {
        try {
            $pdo = db_get_connection();
            if ($pdo instanceof PDO) {
                $stmt = $pdo->prepare("SELECT * FROM accounts WHERE username = :username LIMIT 1");
                $stmt->execute([':username' => $username]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    // Decode kolom JSON agar tetap kompatibel dengan struktur lama
                    if (isset($row['ip_history']) && is_string($row['ip_history']) && $row['ip_history'] !== '') {
                        $decoded = json_decode($row['ip_history'], true);
                        $row['ip_history'] = is_array($decoded) ? $decoded : [];
                    }
                    if (isset($row['drive_items']) && is_string($row['drive_items']) && $row['drive_items'] !== '') {
                        $decoded = json_decode($row['drive_items'], true);
                        $row['drive_items'] = is_array($decoded) ? $decoded : [];
                    }
                    return $row;
                }
            }
        } catch (Throwable $e) {
            // Jika DB error, fallback ke JSON
        }
    }

    // 2) Fallback ke JSON lama (admin-data.json)
    $data = auth_storage_read();
    foreach ($data['accounts'] as $account) {
        if (!is_array($account)) {
            continue;
        }
        if (isset($account['username']) && hash_equals((string)$account['username'], $username)) {
            return $account;
        }
    }

    return null;
}

function auth_verify(string $username, string $password, ?array &$account = null): bool
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    $env = auth_env_credentials();
    if ($env && isset($env['username']) && hash_equals($env['username'], $username)) {
        $valid = false;
        if (!empty($env['password_hash'])) {
            $valid = password_verify($password, $env['password_hash']);
        } elseif (isset($env['password_plain'])) {
            $valid = hash_equals($env['password_plain'], $password);
        }

        if ($valid) {
            $account = [
                'id' => 'env-admin',
                'username' => $env['username'],
                'email' => '',
                'role' => 'admin',
                'source' => 'env',
                'freepik_api_key' => null,
            ];
            return true;
        }
    }

    $record = auth_find_account($username);
    if (!$record || empty($record['password_hash'])) {
        $defaults = auth_default_credential_values();
        if (hash_equals($defaults['username'], $username) && password_verify($password, $defaults['password_hash'])) {
            $account = auth_build_default_account();
            return true;
        }
        return false;
    }

    if (!password_verify($password, (string)$record['password_hash'])) {
        return false;
    }

    if (!empty($record['is_banned']) || !empty($record['is_blocked'])) {
        return false;
    }

    $account = $record;
    return true;
}

function auth_login(array $account): void
{
    auth_session_start();
    session_regenerate_id(true);
    $_SESSION['auth_user'] = $account['username'];
    $_SESSION['auth_role'] = $account['role'] ?? 'user';
    $_SESSION['auth_account_id'] = $account['id'] ?? null;
    $_SESSION['auth_time'] = time();
    $_SESSION['auth_ip'] = $_SERVER['REMOTE_ADDR'] ?? null;

    auth_record_login_event($account);
}

function auth_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }

    session_destroy();
}

function auth_record_login_event(array $account): void
{
    $data = auth_storage_read();
    $id = $account['id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $now = gmdate('c');

    $updated = false;

    foreach ($data['accounts'] as &$stored) {
        if (!is_array($stored) || ($stored['id'] ?? null) !== $id) {
            continue;
        }
        $stored['last_login_at'] = $now;
        $stored['last_login_ip'] = $ip;
        $history = isset($stored['ip_history']) && is_array($stored['ip_history']) ? $stored['ip_history'] : [];
        array_unshift($history, [
            'ip' => $ip,
            'timestamp' => $now,
            'type' => 'login',
        ]);
        $stored['ip_history'] = array_slice($history, 0, 20);
        $stored['updated_at'] = $now;
        $updated = true;
        break;
    }
    unset($stored);

    if ($updated) {
        auth_storage_write($data);
    }

    auth_record_security_event('login', [
        'username' => $account['username'] ?? '',
        'role' => $account['role'] ?? '',
        'ip' => $ip,
    ]);
}

function auth_record_security_event(string $type, array $context = []): void
{
    $data = auth_storage_read();
    $security = $data['security'];
    $history = isset($security['ipChecks']) && is_array($security['ipChecks']) ? $security['ipChecks'] : [];
    $entry = [
        'type' => $type,
        'timestamp' => gmdate('c'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ($context['ip'] ?? ''),
    ];

    foreach ($context as $key => $value) {
        if (in_array($key, ['ip', 'timestamp', 'type'], true)) {
            continue;
        }
        $entry[$key] = $value;
    }

    array_unshift($history, $entry);
    $security['ipChecks'] = array_slice($history, 0, 50);
    $security['updated_at'] = gmdate('c');
    $data['security'] = $security;
    auth_storage_write($data);
}

function auth_validate_freepik_api_key(string $key, ?string &$error = null): bool
{
    $key = trim($key);
    if ($key === '') {
        $error = 'API key Freepik wajib diisi.';
        return false;
    }

    if (!preg_match('/^FPSX[a-zA-Z0-9]{32}$/', $key)) {
        $error = 'Format API key Freepik tidak valid.';
        return false;
    }

    [$valid, $message, $temporary] = auth_probe_freepik_api_key($key);
    if ($temporary) {
        $error = $message ?: 'Tidak dapat memverifikasi API key Freepik saat ini.';
        return false;
    }

    if (!$valid) {
        $error = $message ?: 'API key Freepik ditolak.';
        return false;
    }

    return true;
}

function auth_probe_freepik_api_key(string $key): array
{
    $url = 'https://api.freepik.com/v1/ai/models';
    $userAgent = 'Freepik-Key-Validator/1.1';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-freepik-api-key: ' . $key,
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [false, 'Tidak dapat menghubungi Freepik: ' . $error, true];
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 200 && $status < 300) {
            return [true, null, false];
        }

        if (in_array($status, [401, 403], true)) {
            return [false, 'Freepik menolak API key (HTTP ' . $status . ').', false];
        }

        if ($status === 404) {
            return [false, 'Endpoint verifikasi Freepik tidak ditemukan (HTTP 404).', true];
        }

        return [false, 'Freepik mengembalikan HTTP ' . $status . '.', true];
    }

    // cURL not available, attempt using file_get_contents
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "x-freepik-api-key: {$key}\r\nAccept: application/json\r\nUser-Agent: {$userAgent}\r\n",
            'timeout' => 8,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        return [false, 'Tidak dapat menghubungi Freepik.', true];
    }

    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (stripos($headerLine, 'HTTP/') === 0 && preg_match('/\s(\d{3})\s/', $headerLine, $matches)) {
                $status = (int)$matches[1];
                if ($status >= 200 && $status < 300) {
                    return [true, null, false];
                }
                if (in_array($status, [401, 403], true)) {
                    return [false, 'Freepik menolak API key (HTTP ' . $status . ').', false];
                }
                if ($status === 404) {
                    return [false, 'Endpoint verifikasi Freepik tidak ditemukan (HTTP 404).', true];
                }

                return [false, 'Freepik mengembalikan HTTP ' . $status . '.', true];
            }
        }
    }

    return [false, null, true];
}

function auth_register_account(string $apiKey, string $username, string $email, string $password): array
{
    $errors = [];

    $apiKey = trim($apiKey);
    $hasApiKey = $apiKey !== '';

    if ($hasApiKey && !auth_validate_freepik_api_key($apiKey, $errorMessage)) {
        $errors['apiKey'] = $errorMessage;
    }

    $username = trim($username);
    if ($username === '') {
        $errors['username'] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username)) {
        $errors['username'] = 'Gunakan 3-32 karakter alfanumerik, titik, strip, atau garis bawah.';
    }

    $email = trim($email);
    if ($email === '') {
        $errors['email'] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password minimal 6 karakter.';
    }

    if ($errors) {
        return [
            'ok' => false,
            'errors' => $errors,
            'status' => isset($errors['apiKey']) && strpos($errors['apiKey'], 'Tidak dapat') === 0 ? 503 : 422,
        ];
    }

    $data = auth_storage_read();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    foreach ($data['accounts'] as $account) {
        if (!is_array($account)) {
            continue;
        }
        if (isset($account['username']) && strcasecmp((string)$account['username'], $username) === 0) {
            $errors['username'] = 'Username sudah digunakan.';
        }
        if (isset($account['email']) && $email !== '' && strcasecmp((string)$account['email'], $email) === 0) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
        if ($hasApiKey && isset($account['freepik_api_key']) && $account['freepik_api_key'] && hash_equals((string)$account['freepik_api_key'], $apiKey)) {
            $errors['apiKey'] = 'API key ini sudah terhubung ke akun lain.';
        }
    }

    if ($errors) {
        return [
            'ok' => false,
            'errors' => $errors,
            'status' => 422,
        ];
    }

    $now = gmdate('c');
    $account = [
        'id' => uniqid('acct_', true),
        'username' => $username,
        'email' => $email,
        'role' => 'user',
        'display_name' => ucwords(str_replace(['.', '_', '-'], ' ', $username)),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'freepik_api_key' => $hasApiKey ? $apiKey : null,
        'subscription' => 'free',
        'coins' => 25,
        'is_banned' => false,
        'is_blocked' => false,
        'theme' => 'dark',
        'avatar_url' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'last_login_at' => null,
        'last_login_ip' => null,
        'ip_history' => $ip !== '' ? [[
            'ip' => $ip,
            'timestamp' => $now,
            'type' => 'register',
        ]] : [],
        'source' => 'register',
    ];

    $data['accounts'][] = $account;
    $data['security']['ipChecks'] = array_merge(
        [[
            'type' => 'register',
            'timestamp' => $now,
            'ip' => $ip,
            'username' => $username,
        ]],
        $data['security']['ipChecks']
    );
    $data['security']['ipChecks'] = array_slice($data['security']['ipChecks'], 0, 50);
    $data['security']['updated_at'] = $now;

    if (!auth_storage_write($data)) {
        return [
            'ok' => false,
            'errors' => ['general' => 'Gagal menyimpan akun baru.'],
            'status' => 500,
        ];
    }

    return [
        'ok' => true,
        'status' => 201,
        'account' => $account,
    ];
}

function auth_account_admin_view(array $account): array
{
    return [
        'id' => $account['id'] ?? null,
        'username' => $account['username'] ?? '',
        'display_name' => $account['display_name'] ?? ($account['username'] ?? ''),
        'email' => $account['email'] ?? '',
        'role' => $account['role'] ?? 'user',
        'subscription' => $account['subscription'] ?? 'free',
        'effective_subscription' => auth_account_effective_subscription($account),
        'pro_expires_at' => $account['pro_expires_at'] ?? null,
        'pro_active' => auth_account_pro_active($account),
        'pro_expired' => auth_account_pro_expired($account),
        'coins' => (int)($account['coins'] ?? 0),
        'freepik_api_key' => $account['freepik_api_key'] ?? null,
        'is_banned' => !empty($account['is_banned']),
        'is_blocked' => !empty($account['is_blocked']),
        'generation_count' => (int)($account['generation_count'] ?? 0),
        'last_generation_at' => $account['last_generation_at'] ?? null,
        'restricted' => auth_is_account_restricted($account),
        'theme' => $account['theme'] ?? 'dark',
        'avatar_url' => $account['avatar_url'] ?? null,
        'created_at' => $account['created_at'] ?? null,
        'updated_at' => $account['updated_at'] ?? null,
        'last_login_at' => $account['last_login_at'] ?? null,
        'last_login_ip' => $account['last_login_ip'] ?? null,
    ];
}

function auth_account_public_payload(array $account): array
{
    return [
        'id' => $account['id'] ?? null,
        'username' => $account['username'] ?? '',
        'display_name' => $account['display_name'] ?? ($account['username'] ?? ''),
        'email' => $account['email'] ?? '',
        'role' => $account['role'] ?? 'user',
        'subscription' => $account['subscription'] ?? 'free',
        'effective_subscription' => auth_account_effective_subscription($account),
        'pro_expires_at' => $account['pro_expires_at'] ?? null,
        'pro_active' => auth_account_pro_active($account),
        'pro_expired' => auth_account_pro_expired($account),
        'coins' => (int)($account['coins'] ?? 0),
        'freepik_api_key' => $account['freepik_api_key'] ?? null,
        'theme' => $account['theme'] ?? 'dark',
        'avatar_url' => $account['avatar_url'] ?? null,
        'is_banned' => !empty($account['is_banned']),
        'is_blocked' => !empty($account['is_blocked']),
        'generation_count' => (int)($account['generation_count'] ?? 0),
        'last_generation_at' => $account['last_generation_at'] ?? null,
        'restricted' => auth_is_account_restricted($account),
        'created_at' => $account['created_at'] ?? null,
        'updated_at' => $account['updated_at'] ?? null,
        'last_login_at' => $account['last_login_at'] ?? null,
        'last_login_ip' => $account['last_login_ip'] ?? null,
    ];
}

function auth_drive_get_items(string $accountId): array
{
    $data = auth_storage_read();
    foreach ($data['accounts'] as $idx => $account) {
        if (!is_array($account)) {
            continue;
        }
        if (($account['id'] ?? null) !== $accountId) {
            continue;
        }

        $normalized = auth_normalize_account($account);
        $changed = false;
        $localized = auth_drive_localize_account_items($normalized, $changed);

        if ($changed) {
            $localized['updated_at'] = gmdate('c');
            $data['accounts'][$idx] = auth_normalize_account($localized);
            auth_storage_write($data);
            $normalized = $data['accounts'][$idx];
            $normalized = auth_normalize_account($normalized);
        } else {
            $normalized = $localized;
        }

        return $normalized['drive_items'] ?? [];
    }

    return [];
}

function auth_drive_append_items(string $accountId, array $items, array &$errors = [])
{
    $errors = [];
    if (!$items) {
        $errors['items'] = 'Tidak ada item drive yang dikirimkan.';
        return null;
    }

    $payload = [];
    foreach ($items as $item) {
        $normalized = auth_normalize_drive_item($item);
        if ($normalized) {
            $payload[] = $normalized;
        }
    }

    if (!$payload) {
        $errors['items'] = 'Tidak ada item drive yang valid.';
        return null;
    }

    $data = auth_storage_read();
    $foundIndex = null;
    foreach ($data['accounts'] as $idx => $account) {
        if (is_array($account) && ($account['id'] ?? null) === $accountId) {
            $foundIndex = $idx;
            break;
        }
    }

    if ($foundIndex === null) {
        $errors['account'] = 'Akun tidak ditemukan.';
        return null;
    }

    $account = auth_normalize_account($data['accounts'][$foundIndex]);
    $existing = isset($account['drive_items']) && is_array($account['drive_items'])
        ? $account['drive_items']
        : [];

    $existingSources = [];
    $existingStorage = [];
    foreach ($existing as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $sourceKey = isset($entry['source_url']) ? trim((string)$entry['source_url']) : '';
        if ($sourceKey === '' && isset($entry['url'])) {
            $sourceKey = trim((string)$entry['url']);
        }
        if ($sourceKey !== '') {
            $existingSources[$sourceKey] = true;
        }

        if (isset($entry['storage_path'])) {
            $normalizedPath = auth_drive_normalize_storage_path($entry['storage_path']);
            if ($normalizedPath) {
                $existingStorage[$normalizedPath] = true;
            }
        }
    }

    $prepared = [];
    $newSources = [];
    foreach ($payload as $item) {
        $sourceKey = isset($item['source_url']) ? trim((string)$item['source_url']) : '';
        if ($sourceKey === '' && isset($item['url'])) {
            $sourceKey = trim((string)$item['url']);
        }

        if ($sourceKey !== '' && (isset($existingSources[$sourceKey]) || isset($newSources[$sourceKey]))) {
            continue;
        }

        $preparedItem = auth_drive_prepare_local_item($account, $item);
        if (!$preparedItem) {
            continue;
        }

        $storagePath = isset($preparedItem['storage_path'])
            ? auth_drive_normalize_storage_path($preparedItem['storage_path'])
            : null;

        if ($storagePath && isset($existingStorage[$storagePath])) {
            continue;
        }

        $sourceKey = isset($preparedItem['source_url']) ? trim((string)$preparedItem['source_url']) : $sourceKey;
        if ($sourceKey !== '') {
            if (isset($existingSources[$sourceKey]) || isset($newSources[$sourceKey])) {
                continue;
            }
            $newSources[$sourceKey] = true;
        }

        if ($storagePath) {
            $existingStorage[$storagePath] = true;
        }

        $prepared[] = $preparedItem;
    }

    if (!$prepared) {
        $changed = false;
        $account = auth_drive_localize_account_items($account, $changed);
        if ($changed) {
            $account['updated_at'] = gmdate('c');
            $data['accounts'][$foundIndex] = auth_normalize_account($account);
            if (!auth_storage_write($data)) {
                $errors['general'] = 'Gagal menyimpan penyimpanan drive.';
                return null;
            }
            return $data['accounts'][$foundIndex]['drive_items'] ?? [];
        }

        return $account['drive_items'] ?? [];
    }

    $combined = array_merge($prepared, $existing);
    $filtered = [];
    $seenStorage = [];
    $seenSource = [];
    $seenIds = [];

    foreach ($combined as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $id = isset($entry['id']) ? (string)$entry['id'] : '';
        if ($id !== '' && isset($seenIds[$id])) {
            continue;
        }
        if ($id !== '') {
            $seenIds[$id] = true;
        }

        $storage = isset($entry['storage_path']) ? auth_drive_normalize_storage_path($entry['storage_path']) : null;
        $source = isset($entry['source_url']) ? trim((string)$entry['source_url']) : '';
        if ($source === '' && isset($entry['url'])) {
            $source = trim((string)$entry['url']);
            if ($source !== '') {
                $entry['source_url'] = $source;
            }
        }

        if ($storage && isset($seenStorage[$storage])) {
            continue;
        }
        if ($storage) {
            $entry['storage_path'] = $storage;
            $seenStorage[$storage] = true;
        }

        if ($source !== '' && isset($seenSource[$source])) {
            continue;
        }
        if ($source !== '') {
            $seenSource[$source] = true;
        }

        $filtered[] = $entry;
    }

    usort($filtered, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    $maxItems = 500;
    if (count($filtered) > $maxItems) {
        $filtered = array_slice($filtered, 0, $maxItems);
    }

    $account['drive_items'] = $filtered;
    $account['updated_at'] = gmdate('c');
    $data['accounts'][$foundIndex] = auth_normalize_account($account);

    if (!auth_storage_write($data)) {
        $errors['general'] = 'Gagal menyimpan penyimpanan drive.';
        return null;
    }

    return $data['accounts'][$foundIndex]['drive_items'] ?? [];
}

function auth_drive_delete_item(string $accountId, ?string $itemId, ?string $itemUrl, ?string $storagePath = null, array &$errors = [])
{
    $errors = [];
    $itemId = $itemId ? trim($itemId) : '';
    $itemUrl = $itemUrl ? trim($itemUrl) : '';
    $storagePath = $storagePath ? auth_drive_normalize_storage_path($storagePath) : null;

    if ($itemId === '' && $itemUrl === '' && !$storagePath) {
        $errors['general'] = 'ID atau URL item wajib diisi.';
        return null;
    }

    $data = auth_storage_read();
    $foundIndex = null;
    foreach ($data['accounts'] as $idx => $account) {
        if (is_array($account) && ($account['id'] ?? null) === $accountId) {
            $foundIndex = $idx;
            break;
        }
    }

    if ($foundIndex === null) {
        $errors['account'] = 'Akun tidak ditemukan.';
        return null;
    }

    $account = auth_normalize_account($data['accounts'][$foundIndex]);
    $items = isset($account['drive_items']) && is_array($account['drive_items'])
        ? $account['drive_items']
        : [];

    $filtered = [];
    $removed = false;
    $storageToDelete = [];

    foreach ($items as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $matches = false;
        if ($itemId !== '' && isset($entry['id']) && (string)$entry['id'] === $itemId) {
            $matches = true;
        }
        if (!$matches && $itemUrl !== '' && isset($entry['url']) && strcasecmp((string)$entry['url'], $itemUrl) === 0) {
            $matches = true;
        }
        if (!$matches && $storagePath !== null && isset($entry['storage_path'])) {
            $entryStorage = auth_drive_normalize_storage_path($entry['storage_path']);
            if ($entryStorage && $entryStorage === $storagePath) {
                $matches = true;
            }
        }

        if ($matches) {
            $removed = true;
            if (isset($entry['storage_path'])) {
                $normalizedPath = auth_drive_normalize_storage_path($entry['storage_path']);
                if ($normalizedPath) {
                    $storageToDelete[] = $normalizedPath;
                }
            }
            continue;
        }

        $filtered[] = $entry;
    }

    if (!$removed && $storagePath) {
        $storageToDelete[] = $storagePath;
        $removed = true;
    }

    if (!$removed) {
        $errors['general'] = 'Item drive tidak ditemukan.';
        return null;
    }

    $account['drive_items'] = array_values($filtered);
    $account['updated_at'] = gmdate('c');
    $data['accounts'][$foundIndex] = auth_normalize_account($account);

    if (!auth_storage_write($data)) {
        $errors['general'] = 'Gagal menyimpan perubahan drive.';
        return null;
    }

    if ($storageToDelete) {
        auth_drive_delete_storage_files($account, $storageToDelete);
    }

    return $data['accounts'][$foundIndex]['drive_items'] ?? [];
}

function auth_create_account_entry(array $input, array &$errors = []): ?array
{
    $errors = [];

    $username = trim((string)($input['username'] ?? ''));
    if ($username === '') {
        $errors['username'] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username)) {
        $errors['username'] = 'Gunakan 3-32 karakter alfanumerik, titik, strip, atau garis bawah.';
    }

    $password = (string)($input['password'] ?? '');
    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password minimal 6 karakter.';
    }

    $email = trim((string)($input['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    $displayName = trim((string)($input['display_name'] ?? ''));
    if ($displayName === '' && $username !== '') {
        $displayName = ucwords(str_replace(['.', '_', '-'], ' ', $username));
    }

    $subscription = strtolower(trim((string)($input['subscription'] ?? 'free')));
    if ($subscription === '') {
        $subscription = 'free';
    }

    $proExpiresRaw = isset($input['pro_expires_at']) ? $input['pro_expires_at'] : null;
    $proExpiresAt = auth_normalize_pro_expires_at($proExpiresRaw);

    $role = strtolower(trim((string)($input['role'] ?? 'user')));
    if (!in_array($role, ['user', 'admin'], true)) {
        $role = 'user';
    }

    $coins = isset($input['coins']) ? max(0, (int)$input['coins']) : 0;

    $freepikKey = trim((string)($input['freepik_api_key'] ?? ''));
    if ($freepikKey === '') {
        $freepikKey = null;
    }

    $theme = strtolower(trim((string)($input['theme'] ?? 'dark')));
    if (!in_array($theme, ['dark', 'light'], true)) {
        $theme = 'dark';
    }

    $avatar = trim((string)($input['avatar_url'] ?? ''));
    if ($avatar === '') {
        $avatar = null;
    }

    $data = auth_storage_read();
    foreach ($data['accounts'] as $existing) {
        if (!is_array($existing)) {
            continue;
        }
        if ($username !== '' && isset($existing['username']) && strcasecmp((string)$existing['username'], $username) === 0) {
            $errors['username'] = 'Username sudah digunakan.';
        }
        if ($email !== '' && isset($existing['email']) && $existing['email'] !== '' && strcasecmp((string)$existing['email'], $email) === 0) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
        if ($freepikKey !== null && isset($existing['freepik_api_key']) && $existing['freepik_api_key'] && hash_equals((string)$existing['freepik_api_key'], $freepikKey)) {
            $errors['freepik_api_key'] = 'API key sudah digunakan akun lain.';
        }
    }

    if ($errors) {
        return null;
    }

    $now = gmdate('c');
    $account = [
        'id' => uniqid('acct_', true),
        'username' => $username,
        'display_name' => $displayName !== '' ? $displayName : $username,
        'email' => $email,
        'role' => $role,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'freepik_api_key' => $freepikKey,
        'subscription' => $subscription,
        'pro_expires_at' => $proExpiresAt,
        'coins' => $coins,
        'is_banned' => !empty($input['is_banned']),
        'is_blocked' => !empty($input['is_blocked']),
        'theme' => $theme,
        'avatar_url' => $avatar,
        'created_at' => $now,
        'updated_at' => $now,
        'last_login_at' => null,
        'last_login_ip' => null,
        'ip_history' => [],
        'source' => 'admin',
    ];

    $account = auth_normalize_account($account);
    $data['accounts'][] = $account;

    if (!auth_storage_write($data)) {
        $errors['general'] = 'Gagal menyimpan akun.';
        return null;
    }

    return $account;
}

function auth_update_account_entry(string $id, array $changes, array &$errors = []): ?array
{
    $errors = [];
    $data = auth_storage_read();
    $foundIndex = null;

    foreach ($data['accounts'] as $index => $account) {
        if (!is_array($account) || ($account['id'] ?? null) !== $id) {
            continue;
        }
        $foundIndex = $index;
        break;
    }

    if ($foundIndex === null) {
        $errors['general'] = 'Akun tidak ditemukan.';
        return null;
    }

    $original = $data['accounts'][$foundIndex];
    $updated = $original;

    if (array_key_exists('username', $changes)) {
        $username = trim((string)$changes['username']);
        if ($username === '') {
            $errors['username'] = 'Username wajib diisi.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username)) {
            $errors['username'] = 'Gunakan 3-32 karakter alfanumerik, titik, strip, atau garis bawah.';
        } else {
            $updated['username'] = $username;
        }
    }

    if (array_key_exists('display_name', $changes)) {
        $updated['display_name'] = trim((string)$changes['display_name']);
    }

    if (array_key_exists('email', $changes)) {
        $email = trim((string)$changes['email']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        } else {
            $updated['email'] = $email;
        }
    }

    if (array_key_exists('subscription', $changes)) {
        $sub = strtolower(trim((string)$changes['subscription']));
        $updated['subscription'] = $sub === '' ? 'free' : $sub;
    }

    if (array_key_exists('pro_expires_at', $changes)) {
        $updated['pro_expires_at'] = auth_normalize_pro_expires_at($changes['pro_expires_at']);
    }

    if (array_key_exists('role', $changes)) {
        $role = strtolower(trim((string)$changes['role']));
        if (!in_array($role, ['user', 'admin'], true)) {
            $role = 'user';
        }
        $updated['role'] = $role;
    }

    if (array_key_exists('coins', $changes)) {
        $coins = (int)$changes['coins'];
        if ($coins < 0) {
            $errors['coins'] = 'Koin tidak boleh negatif.';
        } else {
            $updated['coins'] = $coins;
        }
    }

    if (array_key_exists('freepik_api_key', $changes)) {
        $key = trim((string)$changes['freepik_api_key']);
        $updated['freepik_api_key'] = $key === '' ? null : $key;
    }

    if (array_key_exists('is_banned', $changes)) {
        $updated['is_banned'] = (bool)$changes['is_banned'];
    }

    if (array_key_exists('is_blocked', $changes)) {
        $updated['is_blocked'] = (bool)$changes['is_blocked'];
    }

    if (array_key_exists('theme', $changes)) {
        $theme = strtolower(trim((string)$changes['theme']));
        if (!in_array($theme, ['dark', 'light'], true)) {
            $theme = $original['theme'] ?? 'dark';
        }
        $updated['theme'] = $theme;
    }

    if (array_key_exists('avatar_url', $changes)) {
        $avatar = trim((string)$changes['avatar_url']);
        $updated['avatar_url'] = $avatar === '' ? null : $avatar;
    }

    if (array_key_exists('password', $changes)) {
        $newPassword = (string)$changes['password'];
        if ($newPassword !== '') {
            if (strlen($newPassword) < 6) {
                $errors['password'] = 'Password minimal 6 karakter.';
            } else {
                $updated['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
        }
    }

    if ($errors) {
        return null;
    }

    foreach ($data['accounts'] as $index => $account) {
        if (($account['id'] ?? null) === $id) {
            continue;
        }
        if (isset($updated['username']) && $updated['username'] !== '' && isset($account['username']) && strcasecmp((string)$account['username'], (string)$updated['username']) === 0) {
            $errors['username'] = 'Username sudah digunakan.';
        }
        if (isset($updated['email']) && $updated['email'] !== '' && isset($account['email']) && $account['email'] !== '' && strcasecmp((string)$account['email'], (string)$updated['email']) === 0) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
        if (isset($updated['freepik_api_key']) && $updated['freepik_api_key'] !== null && isset($account['freepik_api_key']) && $account['freepik_api_key'] && hash_equals((string)$account['freepik_api_key'], (string)$updated['freepik_api_key'])) {
            $errors['freepik_api_key'] = 'API key sudah digunakan akun lain.';
        }
    }

    if ($errors) {
        return null;
    }

    $updated['updated_at'] = gmdate('c');
    $updated = auth_normalize_account($updated);
    $data['accounts'][$foundIndex] = $updated;

    if (!auth_storage_write($data)) {
        $errors['general'] = 'Gagal menyimpan perubahan.';
        return null;
    }

    return $updated;
}

function auth_delete_account_entry(string $id, array &$errors = []): bool
{
    $errors = [];
    $data = auth_storage_read();
    $accounts = $data['accounts'];
    $filtered = [];
    $deleted = false;

    foreach ($accounts as $account) {
        if (!is_array($account)) {
            continue;
        }
        if (($account['id'] ?? null) === $id) {
            if (($account['role'] ?? 'user') === 'admin') {
                $errors['general'] = 'Tidak dapat menghapus akun admin.';
                return false;
            }
            $deleted = true;
            continue;
        }
        $filtered[] = $account;
    }

    if (!$deleted) {
        $errors['general'] = 'Akun tidak ditemukan.';
        return false;
    }

    $data['accounts'] = $filtered;
    if (!auth_storage_write($data)) {
        $errors['general'] = 'Gagal menghapus akun.';
        return false;
    }

    return true;
}

function auth_adjust_account_coins(string $id, int $delta, array &$errors = [])
{
    $errors = [];
    $data = auth_storage_read();
    foreach ($data['accounts'] as $index => $account) {
        if (!is_array($account) || ($account['id'] ?? null) !== $id) {
            continue;
        }
        $coins = isset($account['coins']) ? (int)$account['coins'] : 0;
        $newCoins = $coins + $delta;
        if ($newCoins < 0) {
            $errors['coins'] = 'Saldo koin tidak mencukupi.';
            return null;
        }
        $account['coins'] = $newCoins;
        $account['updated_at'] = gmdate('c');
        $account = auth_normalize_account($account);
        $data['accounts'][$index] = $account;
        if (!auth_storage_write($data)) {
            $errors['general'] = 'Gagal memperbarui koin.';
            return null;
        }
        return $account;
    }

    $errors['general'] = 'Akun tidak ditemukan.';
    return null;
}

function auth_set_account_theme(string $id, string $theme, array &$errors = [])
{
    $errors = [];
    $theme = strtolower(trim($theme));
    if (!in_array($theme, ['dark', 'light'], true)) {
        $errors['theme'] = 'Tema tidak dikenal.';
        return null;
    }

    return auth_update_account_entry($id, ['theme' => $theme], $errors);
}

function auth_record_security_check(): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $now = gmdate('c');
    $data = auth_storage_read();
    $entry = [
        'type' => 'security-check',
        'timestamp' => $now,
        'ip' => $ip,
    ];

    $data['security']['ipChecks'] = array_merge([$entry], $data['security']['ipChecks']);
    $data['security']['ipChecks'] = array_slice($data['security']['ipChecks'], 0, 50);
    $data['security']['updated_at'] = $now;
    auth_storage_write($data);

    return $entry;
}

function auth_current_account(): ?array
{
    $id = auth_current_account_id();
    if ($id === null) {
        return null;
    }

    $data = auth_storage_read();
    foreach ($data['accounts'] as $account) {
        if (!is_array($account)) {
            continue;
        }
        if (($account['id'] ?? null) === $id) {
            return $account;
        }
    }

    return null;
}

if (!function_exists('auth_sync_accounts_to_db')) {
    /**
     * Sinkronisasi data akun dari admin-data.json ke database MySQL.
     * Dipanggil otomatis setiap kali auth_storage_write() sukses.
     *
     * @param array $data
     * @return void
     */
    function auth_sync_accounts_to_db(array $data): void
    {
        if (!isset($data['accounts']) || !is_array($data['accounts'])) {
            return;
        }
        if (!function_exists('db_sync_accounts')) {
            return;
        }

        try {
            db_sync_accounts($data['accounts']);
        } catch (Throwable $e) {
            // Jangan mengganggu aplikasi utama jika sinkronisasi DB gagal.
        }
    }
}

