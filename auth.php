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

function auth_default_credentials(): array
{
    $base = auth_default_credential_values();
    $base['source'] = 'default';
    return $base;
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
            'source' => 'env',
        ];
    }

    if ($passwordPlain !== '') {
        return [
            'username' => $username,
            'password_plain' => $passwordPlain,
            'source' => 'env',
        ];
    }

    return null;
}

function auth_file_credentials(): ?array
{
    $path = auth_storage_path();
    if (!is_file($path)) {
        return null;
    }

    $json = @file_get_contents($path);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['auth']) || !is_array($data['auth'])) {
        return null;
    }

    $auth = $data['auth'];
    $username = isset($auth['username']) ? trim((string)$auth['username']) : '';
    $passwordHash = isset($auth['password_hash']) ? trim((string)$auth['password_hash']) : '';
    $passwordPlain = isset($auth['password']) ? (string)$auth['password'] : '';

    $updated = false;

    if ($passwordHash === '' && $passwordPlain !== '') {
        $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
        $auth['password_hash'] = $passwordHash;
        unset($auth['password']);
        $updated = true;
    }

    if ($updated) {
        $data['auth'] = $auth;
        @file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    if ($username === '' || $passwordHash === '') {
        return null;
    }

    return [
        'username' => $username,
        'password_hash' => $passwordHash,
        'source' => 'file',
    ];
}

function auth_credentials(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $env = auth_env_credentials();
    if ($env) {
        $cached = $env;
        return $cached;
    }

    $file = auth_file_credentials();
    if ($file) {
        $cached = $file;
        return $cached;
    }

    $cached = auth_default_credentials();
    return $cached;
}

function auth_is_logged_in(): bool
{
    return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['auth_user']);
}

function auth_verify(string $username, string $password): bool
{
    $creds = auth_credentials();

    if (!isset($creds['username']) || $creds['username'] === '') {
        return false;
    }

    if (!hash_equals($creds['username'], $username)) {
        return false;
    }

    if (!empty($creds['password_hash'])) {
        return password_verify($password, $creds['password_hash']);
    }

    if (isset($creds['password_plain'])) {
        return hash_equals($creds['password_plain'], $password);
    }

    return false;
}

function auth_login(string $username): void
{
    auth_session_start();
    session_regenerate_id(true);
    $_SESSION['auth_user'] = $username;
    $_SESSION['auth_time'] = time();
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
