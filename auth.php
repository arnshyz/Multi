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

function auth_storage_default(): array
{
    $now = gmdate('c');
    return [
        'users' => [],
        'apiKeys' => [],
        'meta' => [
            'rollingIndex' => 0,
        ],
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

function auth_build_default_account(): array
{
    $now = gmdate('c');
    $defaults = auth_default_credential_values();
    return [
        'id' => 'account-default-admin',
        'username' => $defaults['username'],
        'email' => '',
        'role' => 'admin',
        'password_hash' => $defaults['password_hash'],
        'freepik_api_key' => null,
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

function auth_normalize_account($account): array
{
    if (!is_array($account)) {
        $account = [];
    }

    $account['id'] = isset($account['id']) && $account['id'] !== '' ? (string)$account['id'] : uniqid('acct_', true);
    $account['username'] = isset($account['username']) ? trim((string)$account['username']) : '';
    $account['email'] = isset($account['email']) ? trim((string)$account['email']) : '';
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
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'freepik_api_key' => $apiKey,
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
