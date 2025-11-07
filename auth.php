<?php
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

    return [
        'id' => $id,
        'type' => $type,
        'url' => $url,
        'thumbnail_url' => $thumb,
        'model' => $model,
        'prompt' => $prompt,
        'created_at' => $createdAt,
    ];
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

    if (!auth_validate_freepik_api_key($apiKey, $errorMessage)) {
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
        if (isset($account['freepik_api_key']) && $account['freepik_api_key'] && hash_equals((string)$account['freepik_api_key'], $apiKey)) {
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
        'freepik_api_key' => $apiKey,
        'subscription' => 'pro',
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
        'coins' => (int)($account['coins'] ?? 0),
        'freepik_api_key' => $account['freepik_api_key'] ?? null,
        'is_banned' => !empty($account['is_banned']),
        'is_blocked' => !empty($account['is_blocked']),
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
        'coins' => (int)($account['coins'] ?? 0),
        'freepik_api_key' => $account['freepik_api_key'] ?? null,
        'theme' => $account['theme'] ?? 'dark',
        'avatar_url' => $account['avatar_url'] ?? null,
        'is_banned' => !empty($account['is_banned']),
        'is_blocked' => !empty($account['is_blocked']),
        'created_at' => $account['created_at'] ?? null,
        'updated_at' => $account['updated_at'] ?? null,
        'last_login_at' => $account['last_login_at'] ?? null,
        'last_login_ip' => $account['last_login_ip'] ?? null,
    ];
}

function auth_drive_get_items(string $accountId): array
{
    $data = auth_storage_read();
    foreach ($data['accounts'] as $account) {
        if (!is_array($account)) {
            continue;
        }
        if (($account['id'] ?? null) !== $accountId) {
            continue;
        }

        $normalized = auth_normalize_account($account);
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
            $payload[$normalized['url']] = $normalized;
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

    $existingMap = [];
    foreach ($existing as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $url = $entry['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $existingMap[$url] = $entry;
        }
    }

    foreach (array_values($payload) as $item) {
        $url = $item['url'];
        if (isset($existingMap[$url])) {
            continue;
        }
        array_unshift($existing, $item);
        $existingMap[$url] = $item;
    }

    $maxItems = 500;
    if (count($existing) > $maxItems) {
        $existing = array_slice($existing, 0, $maxItems);
    }

    $account['drive_items'] = $existing;
    $account['updated_at'] = gmdate('c');
    $data['accounts'][$foundIndex] = auth_normalize_account($account);

    if (!auth_storage_write($data)) {
        $errors['general'] = 'Gagal menyimpan penyimpanan drive.';
        return null;
    }

    return $data['accounts'][$foundIndex]['drive_items'] ?? [];
}

function auth_drive_delete_item(string $accountId, ?string $itemId, ?string $itemUrl, array &$errors = [])
{
    $errors = [];
    $itemId = $itemId ? trim($itemId) : '';
    $itemUrl = $itemUrl ? trim($itemUrl) : '';

    if ($itemId === '' && $itemUrl === '') {
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

        if ($matches) {
            $removed = true;
            continue;
        }

        $filtered[] = $entry;
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
