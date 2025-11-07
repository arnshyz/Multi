<?php
require_once __DIR__ . '/../auth.php';

auth_session_start();

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function auth_json_response($payload, $status = 200)
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

// ====== CONFIG FREEPIK ======
$FREEPIK_API_KEYS = [
    getenv('FREEPIK_API_KEY_1') ?: 'FPSXbcc5610f664682840d2dfd832d74fc03',
    getenv('FREEPIK_API_KEY_2') ?: 'FPSX06967c376cb6d87d9c551ccb33ed4d56',
    getenv('FREEPIK_API_KEY_3') ?: 'REPLACE_WITH_API_KEY_3',
    getenv('FREEPIK_API_KEY_4') ?: 'REPLACE_WITH_API_KEY_4',
    getenv('FREEPIK_API_KEY_5') ?: 'REPLACE_WITH_API_KEY_5',
];

$FREEPIK_BASE_URL = 'https://api.freepik.com';

$GEMINI_API_KEY = getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_GEMINI_API_KEY') ?: '';
$GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com';

$FREEPIK_REDIS_CONFIG = [
    'host'     => getenv('FREEPIK_REDIS_HOST') ?: getenv('REDIS_HOST') ?: '127.0.0.1',
    'port'     => (int)(getenv('FREEPIK_REDIS_PORT') ?: getenv('REDIS_PORT') ?: 6379),
    'timeout'  => (float)(getenv('FREEPIK_REDIS_TIMEOUT') ?: getenv('REDIS_TIMEOUT') ?: 1.5),
    'password' => getenv('FREEPIK_REDIS_PASSWORD') ?: getenv('REDIS_PASSWORD') ?: null,
    'database' => (int)(getenv('FREEPIK_REDIS_DATABASE') ?: getenv('REDIS_DATABASE') ?: 0),
    'key'      => getenv('FREEPIK_REDIS_KEY') ?: 'freepik:api-keys',
];

function app_base_url(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $scheme = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
        $candidate = strtolower(trim($forwarded[0] ?? ''));
        if ($candidate !== '') {
            $scheme = $candidate;
        }
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = $host ? trim((string)$host) : 'localhost';

    if (strpos($host, ':') === false) {
        $port = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? '';
        $port = trim((string)$port);
        if ($port !== '' && !in_array($port, ['80', '443'], true)) {
            $host .= ':' . $port;
        }
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '/' || $dir === '.') {
        $dir = '';
    }

    $cached = rtrim($scheme . '://' . $host . ($dir ? '/' . ltrim($dir, '/') : ''), '/');

    return $cached;
}

function app_public_url(string $path): string
{
    $base = app_base_url();
    return $base . '/' . ltrim($path, '/');
}

function freepik_normalize_api_keys($keys)
{
    $normalized = [];
    foreach ((array)$keys as $key) {
        if (!is_string($key)) {
            continue;
        }
        $trimmed = trim($key);
        if ($trimmed === '' || stripos($trimmed, 'REPLACE_WITH') !== false) {
            continue;
        }
        $normalized[$trimmed] = true;
    }

    return array_keys($normalized);
}

function freepik_available_api_keys()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $FREEPIK_API_KEYS;

    $keys = [];

    $single = getenv('FREEPIK_API_KEY');
    if (is_string($single) && trim($single) !== '') {
        $keys[] = $single;
    }

    for ($i = 1; $i <= 10; $i++) {
        $envKey = getenv('FREEPIK_API_KEY_' . $i);
        if (is_string($envKey) && trim($envKey) !== '') {
            $keys[] = $envKey;
        }
    }

    $keys = array_merge($keys, (array)$FREEPIK_API_KEYS);

    $cached = freepik_normalize_api_keys($keys);

    return $cached;
}

function freepik_redis_client()
{
    static $client = null;
    static $initialized = false;

    if ($initialized) {
        return $client;
    }

    $initialized = true;

    if (!class_exists('Redis')) {
        return null;
    }

    global $FREEPIK_REDIS_CONFIG;

    try {
        $redis = new Redis();
        $redis->connect(
            $FREEPIK_REDIS_CONFIG['host'],
            $FREEPIK_REDIS_CONFIG['port'],
            $FREEPIK_REDIS_CONFIG['timeout']
        );

        if (!empty($FREEPIK_REDIS_CONFIG['password'])) {
            $redis->auth($FREEPIK_REDIS_CONFIG['password']);
        }

        if (isset($FREEPIK_REDIS_CONFIG['database'])) {
            $redis->select((int)$FREEPIK_REDIS_CONFIG['database']);
        }

        $client = $redis;
    } catch (Throwable $e) {
        $client = null;
    }

    return $client;
}

function freepik_ensure_redis_key_list($redis, $keys)
{
    if (!$redis || !$keys) {
        return;
    }

    global $FREEPIK_REDIS_CONFIG;

    $listKey = (string)$FREEPIK_REDIS_CONFIG['key'];
    if ($listKey === '') {
        $listKey = 'freepik:api-keys';
    }

    $hashKey = $listKey . ':hash';
    $expectedHash = sha1(implode('|', $keys));

    try {
        $currentHash = $redis->get($hashKey);
        if ($currentHash === $expectedHash) {
            return;
        }

        $redis->multi();
        $redis->del($listKey);
        foreach ($keys as $key) {
            $redis->rPush($listKey, $key);
        }
        $redis->set($hashKey, $expectedHash);
        $redis->exec();
    } catch (Throwable $e) {
        // Ignore and allow fallback rotation.
    }
}

function freepik_next_api_key()
{
    $keys = freepik_available_api_keys();
    if (!$keys) {
        return null;
    }

    $redis = freepik_redis_client();
    $listKey = null;
    if ($redis) {
        global $FREEPIK_REDIS_CONFIG;
        $listKey = (string)$FREEPIK_REDIS_CONFIG['key'];
        if ($listKey === '') {
            $listKey = 'freepik:api-keys';
        }

        freepik_ensure_redis_key_list($redis, $keys);

        try {
            $value = $redis->rPopLPush($listKey, $listKey);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        } catch (Throwable $e) {
            // fall through to PHP fallback rotation
        }
    }

    static $index = 0;
    $key = $keys[$index % count($keys)];
    $index++;
    return $key;
}

$requestedApi = isset($_GET['api']) ? (string)$_GET['api'] : null;

if ($requestedApi === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST untuk login.'
        ], 405);
    }

    $raw = file_get_contents('php://input');
    $payload = null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $payload = $decoded;
        }
    }

    $username = '';
    $password = '';

    if (is_array($payload)) {
        $username = isset($payload['username']) ? trim((string)$payload['username']) : '';
        $password = isset($payload['password']) ? (string)$payload['password'] : '';
    }

    if ($username === '' && isset($_POST['username'])) {
        $username = trim((string)$_POST['username']);
    }
    if ($password === '' && isset($_POST['password'])) {
        $password = (string)$_POST['password'];
    }

    if ($username === '' || $password === '') {
        auth_json_response([
            'ok' => false,
            'status' => 422,
            'error' => 'Username dan password wajib diisi.'
        ], 422);
    }

    $account = null;
    if (!auth_verify($username, $password, $account)) {
        auth_record_security_event('login-failed', [
            'username' => $username,
        ]);

        auth_json_response([
            'ok' => false,
            'status' => 401,
            'error' => 'Kredensial tidak valid.'
        ], 401);
    }

    auth_login($account ?? ['username' => $username, 'role' => 'user']);

    auth_json_response([
        'ok' => true,
        'status' => 200,
        'data' => [
            'username' => $account['username'] ?? $username,
            'role' => $account['role'] ?? 'user',
        ]
    ]);
}

if ($requestedApi === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST untuk logout.'
        ], 405);
    }

    auth_logout();
    auth_session_start();

    auth_json_response([
        'ok' => true,
        'status' => 200
    ]);
}

if ($requestedApi === 'register') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST untuk registrasi.'
        ], 405);
    }

    $raw = file_get_contents('php://input');
    $payload = null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $payload = $decoded;
        }
    }

    $apiKey = '';
    $username = '';
    $email = '';
    $password = '';

    if (is_array($payload)) {
        $apiKey = isset($payload['freepik_api_key']) ? (string)$payload['freepik_api_key'] : '';
        $username = isset($payload['username']) ? (string)$payload['username'] : '';
        $email = isset($payload['email']) ? (string)$payload['email'] : '';
        $password = isset($payload['password']) ? (string)$payload['password'] : '';
    }

    if ($apiKey === '' && isset($_POST['freepik_api_key'])) {
        $apiKey = (string)$_POST['freepik_api_key'];
    }
    if ($username === '' && isset($_POST['username'])) {
        $username = (string)$_POST['username'];
    }
    if ($email === '' && isset($_POST['email'])) {
        $email = (string)$_POST['email'];
    }
    if ($password === '' && isset($_POST['password'])) {
        $password = (string)$_POST['password'];
    }

    $result = auth_register_account($apiKey, $username, $email, $password);
    if (empty($result['ok'])) {
        $status = isset($result['status']) ? (int)$result['status'] : 422;
        auth_json_response([
            'ok' => false,
            'status' => $status,
            'error' => $result['errors'] ?? ['general' => 'Registrasi gagal.'],
        ], $status);
    }

    auth_json_response([
        'ok' => true,
        'status' => $result['status'] ?? 201,
        'message' => 'Registrasi berhasil. Silakan login menggunakan kredensial baru.',
        'account' => [
            'username' => $result['account']['username'] ?? $username,
            'email' => $result['account']['email'] ?? $email,
            'role' => $result['account']['role'] ?? 'user',
        ],
    ], $result['status'] ?? 201);
}

if ($requestedApi === 'security-check') {
    $entry = auth_record_security_check();
    auth_json_response([
        'ok' => true,
        'status' => 200,
        'ip' => $entry['ip'] ?? '',
        'timestamp' => $entry['timestamp'] ?? gmdate('c'),
    ]);
}

if ($requestedApi !== null && !in_array($requestedApi, ['login', 'register', 'security-check'], true) && !auth_is_logged_in()) {
    auth_json_response([
        'ok' => false,
        'status' => 401,
        'error' => 'Silakan masuk untuk mengakses API.'
    ], 401);
}

if ($requestedApi === 'account') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode GET untuk mengambil data akun.'
        ], 405);
    }

    $account = auth_current_account();
    if (!$account) {
        auth_json_response([
            'ok' => false,
            'status' => 404,
            'error' => 'Akun tidak ditemukan.'
        ], 404);
    }

    $payload = auth_account_public_payload($account);
    $payload['platform'] = auth_platform_public_view();

    auth_json_response([
        'ok' => true,
        'status' => 200,
        'data' => $payload,
    ]);
}

if ($requestedApi === 'account-theme') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST untuk memperbarui tema.'
        ], 405);
    }

    $account = auth_current_account();
    if (!$account) {
        auth_json_response([
            'ok' => false,
            'status' => 401,
            'error' => 'Sesi berakhir, silakan login ulang.'
        ], 401);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $theme = isset($payload['theme']) ? (string)$payload['theme'] : '';
    $errors = [];
    $updated = auth_set_account_theme($account['id'], $theme, $errors);
    if (!$updated) {
        $status = isset($errors['theme']) ? 422 : 500;
        auth_json_response([
            'ok' => false,
            'status' => $status,
            'error' => $errors ?: 'Gagal memperbarui tema.'
        ], $status);
    }

    auth_json_response([
        'ok' => true,
        'status' => 200,
        'data' => auth_account_public_payload($updated),
    ]);
}

if ($requestedApi === 'account-coins') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST untuk memperbarui koin.'
        ], 405);
    }

    $account = auth_current_account();
    if (!$account) {
        auth_json_response([
            'ok' => false,
            'status' => 401,
            'error' => 'Sesi berakhir, silakan login ulang.'
        ], 401);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $amount = isset($payload['amount']) ? (int)$payload['amount'] : 0;
    if ($amount <= 0) {
        auth_json_response([
            'ok' => false,
            'status' => 422,
            'error' => 'Jumlah koin harus lebih besar dari 0.'
        ], 422);
    }

    $errors = [];
    $updated = auth_adjust_account_coins($account['id'], -$amount, $errors);
    if (!$updated) {
        $status = isset($errors['coins']) ? 409 : (isset($errors['general']) ? 500 : 422);
        auth_json_response([
            'ok' => false,
            'status' => $status,
            'error' => $errors ?: 'Gagal memperbarui koin.'
        ], $status);
    }

    auth_json_response([
        'ok' => true,
        'status' => 200,
        'data' => [
            'coins' => (int)$updated['coins'],
        ],
    ]);
}

if ($requestedApi === 'account-avatar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST untuk memperbarui avatar.'
        ], 405);
    }

    $account = auth_current_account();
    if (!$account) {
        auth_json_response([
            'ok' => false,
            'status' => 401,
            'error' => 'Sesi berakhir, silakan login ulang.'
        ], 401);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $avatar = '';
    if (isset($payload['avatar'])) {
        $avatar = (string)$payload['avatar'];
    } elseif (isset($payload['avatarUrl'])) {
        $avatar = (string)$payload['avatarUrl'];
    }
    $avatar = trim($avatar);

    if ($avatar !== '' && !preg_match('/^https?:\/\//i', $avatar)) {
        auth_json_response([
            'ok' => false,
            'status' => 422,
            'error' => ['avatar' => 'Gunakan URL gambar yang valid (diawali http/https).']
        ], 422);
    }

    $errors = [];
    $updated = auth_update_account_entry($account['id'], ['avatar_url' => $avatar !== '' ? $avatar : null], $errors);
    if (!$updated) {
        $status = isset($errors['general']) ? 500 : 422;
        auth_json_response([
            'ok' => false,
            'status' => $status,
            'error' => $errors ?: 'Gagal memperbarui avatar.'
        ], $status);
    }

    auth_json_response([
        'ok' => true,
        'status' => 200,
        'data' => auth_account_public_payload($updated),
    ]);
}

if ($requestedApi === 'account-password') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST untuk mengganti password.'
        ], 405);
    }

    $account = auth_current_account();
    if (!$account) {
        auth_json_response([
            'ok' => false,
            'status' => 401,
            'error' => 'Sesi berakhir, silakan login ulang.'
        ], 401);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $current = trim((string)($payload['current'] ?? ($payload['currentPassword'] ?? '')));
    $new     = (string)($payload['password'] ?? ($payload['newPassword'] ?? ''));
    $confirm = (string)($payload['confirm'] ?? ($payload['confirmPassword'] ?? ''));

    if ($new === '' || strlen($new) < 6) {
        auth_json_response([
            'ok' => false,
            'status' => 422,
            'error' => ['password' => 'Password baru minimal 6 karakter.']
        ], 422);
    }

    if ($new !== $confirm) {
        auth_json_response([
            'ok' => false,
            'status' => 422,
            'error' => ['confirm' => 'Konfirmasi password tidak cocok.']
        ], 422);
    }

    if (!auth_verify($account['username'] ?? '', $current, $verifiedAccount)) {
        auth_json_response([
            'ok' => false,
            'status' => 403,
            'error' => ['current' => 'Password lama tidak sesuai.']
        ], 403);
    }

    $errors = [];
    $updated = auth_update_account_entry($account['id'], ['password' => $new], $errors);
    if (!$updated) {
        $status = isset($errors['general']) ? 500 : 422;
        auth_json_response([
            'ok' => false,
            'status' => $status,
            'error' => $errors ?: 'Gagal mengubah password.'
        ], $status);
    }

    auth_json_response([
        'ok' => true,
        'status' => 200,
        'message' => 'Password berhasil diperbarui.'
    ]);
}

if ($requestedApi === 'drive') {
    $account = auth_current_account();
    if (!$account) {
        auth_json_response([
            'ok' => false,
            'status' => 401,
            'error' => 'Sesi berakhir, silakan login ulang.'
        ], 401);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $items = auth_drive_get_items($account['id']);
        auth_json_response([
            'ok' => true,
            'status' => 200,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
        if (!$items) {
            auth_json_response([
                'ok' => false,
                'status' => 422,
                'error' => 'Tidak ada item drive yang dikirimkan.'
            ], 422);
        }

        $errors = [];
        $stored = auth_drive_append_items($account['id'], $items, $errors);
        if ($stored === null) {
            $status = isset($errors['items']) ? 422 : (isset($errors['account']) ? 404 : 500);
            auth_json_response([
                'ok' => false,
                'status' => $status,
                'error' => $errors ?: 'Gagal menyimpan drive.'
            ], $status);
        }

        auth_json_response([
            'ok' => true,
            'status' => 200,
            'data' => [
                'items' => $stored,
            ],
        ]);
    }

    auth_json_response([
        'ok' => false,
        'status' => 405,
        'error' => 'Metode tidak diizinkan untuk drive.'
    ], 405);
}

if ($requestedApi === 'drive-delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST atau DELETE untuk menghapus drive.'
        ], 405);
    }

    $account = auth_current_account();
    if (!$account) {
        auth_json_response([
            'ok' => false,
            'status' => 401,
            'error' => 'Sesi berakhir, silakan login ulang.'
        ], 401);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $itemId = isset($payload['id']) ? trim((string)$payload['id']) : '';
    $itemUrl = isset($payload['url']) ? trim((string)$payload['url']) : '';

    if ($itemId === '' && $itemUrl === '') {
        auth_json_response([
            'ok' => false,
            'status' => 422,
            'error' => 'ID atau URL item wajib diisi.'
        ], 422);
    }

    $errors = [];
    $items = auth_drive_delete_item($account['id'], $itemId, $itemUrl, $errors);
    if ($items === null) {
        $status = isset($errors['general']) ? 500 : 404;
        auth_json_response([
            'ok' => false,
            'status' => $status,
            'error' => $errors ?: 'Item drive tidak ditemukan.'
        ], $status);
    }

    auth_json_response([
        'ok' => true,
        'status' => 200,
        'data' => [
            'items' => $items,
        ],
    ]);
}

// ====== UPLOAD FILE: ?api=upload ======
if (isset($_GET['api']) && $_GET['api'] === 'upload') {
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'ok'     => false,
            'status' => 405,
            'error'  => 'Gunakan metode POST untuk upload'
        ]);
        exit;
    }

    if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        echo json_encode([
            'ok'     => false,
            'status' => 400,
            'error'  => 'Tidak ada file yang diunggah'
        ]);
        exit;
    }

    $file = $_FILES['file'];
    if (!empty($file['error'])) {
        echo json_encode([
            'ok'     => false,
            'status' => 400,
            'error'  => 'Upload gagal dengan kode error ' . $file['error']
        ]);
        exit;
    }

    $maxSize = 15 * 1024 * 1024; // 15 MB
    if ($file['size'] > $maxSize) {
        echo json_encode([
            'ok'     => false,
            'status' => 413,
            'error'  => 'Ukuran file maksimal 15MB'
        ]);
        exit;
    }

    $dir = APP_ROOT . '/generated';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    if ($mime && isset($allowed[$mime])) {
        $ext = $allowed[$mime];
    }

    if (!$ext) {
        $ext = 'bin';
    }

    try {
        $rand = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $rand = substr(md5(mt_rand()), 0, 8);
    }

    $filename = 'upload_' . date('Ymd_His') . '_' . $rand . '.' . $ext;
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode([
            'ok'     => false,
            'status' => 500,
            'error'  => 'Gagal menyimpan file upload'
        ]);
        exit;
    }

    $publicPath = 'generated/' . $filename;

    echo json_encode([
        'ok'     => true,
        'status' => 200,
        'path'   => $publicPath,
        'url'    => app_public_url($publicPath),
        'name'   => $file['name']
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ====== CACHE FILE: ?api=cache (download dari URL lalu simpan ke server) ======
if (isset($_GET['api']) && $_GET['api'] === 'cache') {
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $url = $payload['url'] ?? '';

    if (!$url) {
        echo json_encode([
            'ok'     => false,
            'status' => 400,
            'error'  => 'Field \"url\" wajib'
        ]);
        exit;
    }

    $dir = APP_ROOT . '/generated';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $pathPart = parse_url($url, PHP_URL_PATH);
    $ext = pathinfo($pathPart ?? '', PATHINFO_EXTENSION);
    if (!$ext) $ext = 'bin';

    try {
        $rand = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $rand = substr(md5(mt_rand()), 0, 8);
    }

    $filename = 'fp_' . date('Ymd_His') . '_' . $rand . '.' . $ext;
    $dest = $dir . '/' . $filename;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno || $data === false || $status >= 400) {
        echo json_encode([
            'ok'     => false,
            'status' => 500,
            'error'  => 'Gagal download file: ' . ($error ?: ('HTTP ' . $status))
        ]);
        exit;
    }

    if (file_put_contents($dest, $data) === false) {
        echo json_encode([
            'ok'     => false,
            'status' => 500,
            'error'  => 'Gagal menyimpan file ke server'
        ]);
        exit;
    }

    $publicPath = 'generated/' . $filename;

    echo json_encode([
        'ok'     => true,
        'status' => 200,
        'path'   => $publicPath,
        'url'    => app_public_url($publicPath)
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ====== PROXY GEMINI: ?api=gemini ======
if (isset($_GET['api']) && $_GET['api'] === 'gemini') {
    header('Content-Type: application/json; charset=utf-8');

    global $GEMINI_API_KEY, $GEMINI_BASE_URL;

    if (!$GEMINI_API_KEY) {
        echo json_encode([
            'ok'     => false,
            'status' => 500,
            'error'  => 'GEMINI API key belum dikonfigurasi'
        ]);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        echo json_encode([
            'ok'     => false,
            'status' => 400,
            'error'  => 'Payload bukan JSON valid'
        ]);
        exit;
    }

    $path   = $payload['path']   ?? null;
    $method = strtoupper($payload['method'] ?? 'POST');
    $body   = $payload['body']   ?? null;

    if (!$path) {
        echo json_encode([
            'ok'     => false,
            'status' => 400,
            'error'  => 'Field "path" wajib'
        ]);
        exit;
    }

    $path = '/' . ltrim($path, '/');
    $url = rtrim($GEMINI_BASE_URL, '/') . $path;
    $url .= (strpos($url, '?') === false ? '?' : '&') . 'key=' . urlencode($GEMINI_API_KEY);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Accept: application/json'];
    if ($method !== 'GET' && $body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $responseBody = curl_exec($ch);
    $errno        = curl_errno($ch);
    $error        = curl_error($ch);
    $statusCode   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno) {
        echo json_encode([
            'ok'     => false,
            'status' => 500,
            'error'  => 'cURL error: ' . $error
        ]);
        exit;
    }

    $data = null;
    if ($responseBody !== '' && $responseBody !== null) {
        $decoded = json_decode($responseBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $decoded;
        } else {
            $data = $responseBody;
        }
    }

    echo json_encode([
        'ok'     => $statusCode >= 200 && $statusCode < 300,
        'status' => $statusCode,
        'data'   => $data
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ====== PROXY AJAX: ?api=freepik ======
if (isset($_GET['api']) && $_GET['api'] === 'freepik') {
    header('Content-Type: application/json; charset=utf-8');

    $selectedApiKey = freepik_next_api_key();
    if (!$selectedApiKey) {
        echo json_encode([
            'ok'     => false,
            'status' => 500,
            'error'  => 'FREEPIK API key belum dikonfigurasi'
        ]);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        echo json_encode([
            'ok'     => false,
            'status' => 400,
            'error'  => 'Payload bukan JSON valid'
        ]);
        exit;
    }

    $path        = $payload['path']        ?? null;
    $method      = strtoupper($payload['method'] ?? 'POST');
    $body        = $payload['body']        ?? null;
    $contentType = $payload['contentType'] ?? 'json';

    if (!$path) {
        echo json_encode([
            'ok'     => false,
            'status' => 400,
            'error'  => 'Field \"path\" wajib'
        ]);
        exit;
    }

    $url = rtrim($FREEPIK_BASE_URL, '/') . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [
        'x-freepik-api-key: ' . $selectedApiKey,
        'Accept: application/json'
    ];

    if ($method !== 'GET' && $body !== null) {
        if ($contentType === 'form') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $responseBody = curl_exec($ch);
    $errno        = curl_errno($ch);
    $error        = curl_error($ch);
    $statusCode   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno) {
        echo json_encode([
            'ok'     => false,
            'status' => 500,
            'error'  => 'cURL error: ' . $error
        ]);
        exit;
    }

    $data = null;
    if ($responseBody !== '' && $responseBody !== null) {
        $json = json_decode($responseBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $json;
        } else {
            $data = $responseBody;
        }
    }

    echo json_encode([
        'ok'     => $statusCode >= 200 && $statusCode < 300,
        'status' => $statusCode,
        'data'   => $data
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if (!auth_is_logged_in()) {
    if ($requestedApi === null) {
        header('Location: index.php');
        exit;
    }
}

$currentUser = auth_is_logged_in() ? (string)($_SESSION['auth_user'] ?? '') : '';
