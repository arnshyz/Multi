<?php
require_once __DIR__ . '/auth.php';

auth_session_start();

$clientIp = 'Unknown';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwardedFor = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
    $candidate = trim($forwardedFor[0] ?? '');
    if ($candidate !== '') {
        $clientIp = $candidate;
    }
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
    $candidate = trim((string)$_SERVER['REMOTE_ADDR']);
    if ($candidate !== '') {
        $clientIp = $candidate;
    }
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

    $dir = __DIR__ . '/generated';
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

    $dir = __DIR__ . '/generated';
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
?>
<?php
if (!auth_is_logged_in()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    ?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKAY.IO - Artificial Intelligence Tool</title>
    <meta name="description" content="Transformasi data, kreasi, dan strategi Anda dengan kecerdasan buatan.">
    <link rel="icon" type="image/png" href="/logo.png">
    <meta property="og:url" content="https://www.akay.io/">
    <meta property="og:image" content="https://i.pcmag.com/imagery/articles/02hgT9Zk2u7PcF9ybJhuQDS-1.fit_lim.v1724605536.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <script>
        (function () {
            var theme = 'light';
            try {
                var stored = localStorage.getItem('akay-theme');
                if (stored === 'dark') {
                    theme = 'dark';
                }
            } catch (error) {
                theme = 'light';
            }
            var root = document.documentElement;
            root.setAttribute('data-theme', theme);
            root.classList.remove('light-mode', 'dark-mode');
            root.classList.add(theme === 'dark' ? 'dark-mode' : 'light-mode');
        })();
    </script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="light-mode">
    
    <div class="security-gate-overlay" id="securityGateOverlay">
        <section class="auth-card security security-card glass-card" id="securityGate">
            <div class="security-alert" role="alert">
                <span class="security-icon" aria-hidden="true">⚠️</span>
                <div class="security-copy">
                    <h1>Security Check</h1>
                    <p>Validasi alamat IP Anda sebelum memasuki Website.</p>
                </div>
            </div>
            <div class="ip-panel">
                <span class="ip-label">Your IP Address</span>
                <span class="ip-value" id="securityIp">...loading IP...</span> 
                <span class="ip-note">Aktivitas login Anda dipantau untuk mencegah pembagian tidak sah.</span>
            </div>
            <button type="button" id="securityContinue" class="btn-primary">Lanjutkan</button>
            <span class="ip-note small-note">Dikembangkan oleh AKAY STUDIO</span>
            <div class="security-status" id="securityStatus"></div>
        </section>
    </div>
    <div class="background">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <header>
        <div class="navbar glass-card">
            <div class="logo">
                <img src="logo.png" alt="AKAY.IO Logo" class="logo-icon">
                AKAY
            </div>

            <nav class="desktop-menu">
                <a href="#product">Product</a>
                <a href="#use-cases">Use Cases</a>
                <a href="#resources">Resource</a>
                <a href="#pricing">Pricing</a>
            </nav>

            <div class="nav-actions">
                <button id="dark-mode-toggle" class="icon-btn" aria-label="Toggle tema">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="#" class="btn-login"><i class="fas fa-arrow-right"></i> Login</a>
                <a href="#" class="btn-signup">Sign Up</a>

                <button class="menu-toggle" id="menu-toggle" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <div class="mobile-dropdown glass-card" id="mobile-menu">
        <a href="#product">Product</a>
        <a href="#use-cases">Use Cases</a>
        <a href="#resources">Resource</a>
        <a href="#pricing">Pricing</a>
        <div class="mobile-actions">
            <a href="#" class="btn-login-mobile"><i class="fas fa-arrow-right"></i> Login</a>
            <a href="#" class="btn-signup-mobile"><i class="fas fa-user-plus"></i> Sign Up</a>
        </div>
    </div>

    <main class="hero-section" id="product">
        <div class="hero-text">
            <h1>
                <span class="gradient-text">Hasilkan Konten</span>
                Instan
            </h1>
            <p>Powered by <strong>Kling 2.5 Pro &amp; Seedance </strong> — Model generasi video AI tercanggih di sidoarjo</p>
            <p class="subtitle-mobile">menghasilkan video berkualitas studio tanpa keterampilan teknis apa pun.</p>

            <div class="cta-area">
                <a href="#" class="cta-button">
                    <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                    <span class="text-part">Start Testing Free</span>
                </a>
                <div class="active-users">1000+ Active Users</div>
            </div>
        </div>

        <div class="stats-panel">
            <div class="stat-card glass-card stat-big">
                <p>10K+</p>
                <span>Videos Created by users</span>
            </div>
            <div class="stat-card-row">
                <div class="stat-card glass-card stat-small">
                    <p>30s</p>
                    <span>Avg. Time</span>
                </div>
                <div class="stat-card glass-card stat-small">
                    <p>98%</p>
                    <span>Success Rate</span>
                </div>
            </div>
            <div class="stat-card glass-card feature-badge">
                <i class="fas fa-rocket"></i> Real-time processing
            </div>
        </div>
    </main>

    <div id="login-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="login-modal-title">
        <div class="modal-content glass-card">
            <button class="close-btn" type="button" aria-label="Tutup">&times;</button>
            <h2 id="login-modal-title">Login ke AKAY.IO</h2>
            
            <form>
                <input type="text" name="username" placeholder="Username" required autocomplete="username">
                <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
                <button type="submit" class="btn-primary">Login</button>
                <div class="form-status" data-role="login-status" aria-live="polite"></div>
            </form>
        </div>
    </div>

    <div id="signup-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="signup-modal-title">
        <div class="modal-content glass-card">
            <button class="close-btn" type="button" aria-label="Tutup">&times;</button>
            <h2 id="signup-modal-title">Hubungi No Dibawah</h2>
            <div class="login-ip-stamp">
        <span class="stamp-icon" aria-hidden="true">🔒</span>
        <span>Untuk Sementara Pendaftaran Di tutup:</span>
        <span class="stamp-value"><?= htmlspecialchars($clientIp, ENT_QUOTES) ?></span>
      </div>
            <form>
                <input type="text" name="username" placeholder="Nama Pengguna" required autocomplete="username">
                <input type="email" name="email" placeholder="Email" autocomplete="email">
                <input type="password" name="password" placeholder="Password" required autocomplete="new-password">
                </button href="wa.me/6282213316764">0818-404-222</button/>
                <div class="form-status" data-role="signup-status" aria-live="polite"></div>
            </form>
        </div>
    </div>

    <section class="product-features" aria-labelledby="product-section-title">
        <div class="product-header">
            <h2 class="product-title" id="product-section-title">Pembuat Iklan Video AI</h2>
            <p class="product-subtitle">dibangun untuk kinerja instan</p>
            <p class="product-description">Ubah halaman produk apa pun menjadi iklan video yang unggul—dibuat, diuji, dan dioptimalkan dalam hitungan detik.</p>
        </div>

        <div class="feature-card glass-card">
            <div class="feature-image">
                <span class="image-badge">UGC Affiliate & Seller</span>
                <img src="https://xxx.akay.web.id/fp_20251106_171107_26c79bb5.png" alt="Instant UGC Video Generation">
            </div>
            <div class="feature-content">
                <span class="feature-tag">AI-POWERED</span>
                <h3>Pembuatan Video UGC Instan</h3>
                <p>Ubah produk apa pun menjadi video konten buatan pengguna yang autentik dalam hitungan menit. AI kami menemukan musik dan video menarik yang tampak seperti berasal dari pelanggan sungguhan.</p>
                <a href="#" class="btn-try-now">
                    <i class="fas fa-arrow-right"></i> Try it now
                </a>
            </div>
        </div>

        <div class="feature-card glass-card reverse-layout">
            <div class="feature-image">
                <span class="image-badge">Product Demo</span>
                <img src="https://via.placeholder.com/250x350/FF66B2/FFFFFF?text=Product+2" alt="Real-Time Ad Optimization">
            </div>
            <div class="feature-content">
                <span class="feature-tag">LIGHTNING FAST</span>
                <h3>Real-Time Ad Optimization</h3>
                <p>Uji berbagai variasi secara otomatis dan optimalkan untuk konversi maksimal. AI kami menganalisis performa dan menyesuaikan iklan Anda secara real-time untuk hasil terbaik.</p>
                <a href="#" class="btn-try-now">
                    <i class="fas fa-arrow-right"></i> Try it now
                </a>
            </div>
        </div>

        <div class="feature-card glass-card">
            <div class="feature-image">
                <span class="image-badge">Analytics View</span>
                <img src="https://via.placeholder.com/250x350/66FFFF/FFFFFF?text=Product+3" alt="Data-Driven Insights">
            </div>
            <div class="feature-content">
                <span class="feature-tag">PERFORMANCE</span>
                <h3>Data-Driven Insights</h3>
                <p>Dapatkan analisis mendetail tentang apa yang berhasil dan apa yang tidak. Lacak interaksi, konversi, dan ROI di semua iklan video Anda dengan wawasan yang dapat ditindaklanjuti.</p>
                <a href="#" class="btn-try-now">
                    <i class="fas fa-arrow-right"></i> Try it now
                </a>
            </div>
        </div>

        <div class="feature-card glass-card reverse-layout">
            <div class="feature-image">
                <span class="image-badge">Target Audience</span>
                <img src="https://via.placeholder.com/250x350/FFD700/FFFFFF?text=Product+4" alt="Audience Precision">
            </div>
            <div class="feature-content">
                <span class="feature-tag">SMART TARGETING</span>
                <h3>Audience Precision</h3>
                <p>Jangkau audiens yang tepat di waktu yang tepat. AI kami secara otomatis mengidentifikasi dan menargetkan pelanggan ideal Anda berdasarkan perilaku dan pola interaksi..</p>
                <a href="#" class="btn-try-now">
                    <i class="fas fa-arrow-right"></i> Try it now
                </a>
            </div>
        </div>
    </section>

    <section class="use-cases" id="use-cases" aria-labelledby="use-cases-title">
        <div class="use-cases-header">
            <h2 id="use-cases-title">Cara Kerja AKAY.IO</h2>
            <p>Dari inspirasi hingga pengoptimalan—alur kerja lengkap Anda untuk membuat iklan video yang unggul.</p>
        </div>

        <div class="workflow-grid">
            <div class="workflow-card glass-card">
                <div class="card-icon"><i class="fas fa-search"></i></div>
                <div class="card-number">01</div>
                <h4>Get Inspired</h4>
                <p class="card-subtitle">Temukan apa yang berhasil.</p>
                <p>Jelajahi iklan berkinerja terbaik di seluruh kategori atau pesaing Anda berdasarkan daya tarik, nilai jual, dan visual.</p>
                <div class="underline-blue"></div>
            </div>

            <div class="workflow-card glass-card">
                <div class="card-icon"><i class="fas fa-rocket"></i></div>
                <div class="card-number">02</div>
                <h4>Create Winning Ads</h4>
                <p class="card-subtitle">Dari tautan hingga peluncuran, seketika.</p>
                <p>Ubah URL produk atau aset statis menjadi iklan video yang memukau. Sesuaikan dengan musik, emosi, atau sulih suara.</p>
                <div class="underline-red"></div>
            </div>

            <div class="workflow-card glass-card">
                <div class="card-icon"><i class="fas fa-rocket"></i></div>
                <div class="card-number">03</div>
                <h4>Launch and Test</h4>
                <p class="card-subtitle">Uji semuanya dengan teliti.</p>
                <p>Berkreasilah dengan beragam varian video. Temukan pemenang berdasarkan format utama, konten, atau audiens—secara otomatis.</p>
                <div class="underline-orange"></div>
            </div>

            <div class="workflow-card glass-card">
                <div class="card-icon"><i class="fas fa-rocket"></i></div>
                <div class="card-number">04</div>
                <h4>Learn and Optimize</h4>
                <p class="card-subtitle">Ketahui apa yang berhasil—dan alasannya.</p>
                <p>Dapatkan wawasan real-time tentang ROAS, CPA, dan metrik penting lainnya. Kenali kelelahan dan tingkatkan kampanye dengan cepat.</p>
                <div class="underline-cyan"></div>
            </div>
        </div>

        <div class="cases-cta">
            <p>Ready to transform your ad creation process?</p>
            <a href="#" class="cta-button-small">
                <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                <span class="text-part">Get Started Free</span>
            </a>
        </div>

        <div class="real-results" id="resources">
            <div class="results-text">
                <h3>Real Results</h3>
                <small>Based on internal benchmarks &amp; internal data</small>
            </div>
            <div class="stats-data">
                <div class="stat-item">
                    <p class="stat-number stat-blue">2.7x</p>
                    <small>More leads vs. static image ads</small>
                </div>
                <div class="stat-item">
                    <p class="stat-number stat-red">1.7x</p>
                    <small>Higher ROI</small>
                </div>
                <div class="stat-item">
                    <p class="stat-number stat-green">90%</p>
                    <small>Lower production cost</small>
                </div>
            </div>
        </div>
    </section>

    <section class="pricing-section" id="pricing" aria-labelledby="pricing-title">
        <div class="pricing-header">
            <h2 id="pricing-title">Pick a plan</h2>
            <p>or get started for free</p>
            <p class="pricing-subtitle">Plans for creators, marketers, and agencies of all sizes.</p>
        </div>

        <div class="pricing-grid">
            <div class="plan-card glass-card">
                <h3>Free Plan</h3>
                <p class="plan-tag">Start testing on AKAY.IO at no cost</p>
                <div class="price-container">
                    <span class="currency">Rp</span>
                    <span class="price-value" data-final-price="0">0</span>
                </div>
                <p class="price-lifetime">Forever</p>

                <div class="key-features">
                    <h4>Key Features:</h4>
                    <ul>
                        <li><i class="fas fa-check-circle"></i> Browse dashboard only</li>
                        <li><i class="fas fa-check-circle"></i> View features &amp; pricing</li>
                        <li class="disabled-feature"><i class="fas fa-circle-minus"></i> No generator access</li>
                    </ul>
                </div>

                <a href="#" class="btn-plan-select">
                    <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                    <span class="text-part">Get Started</span>
                </a>
            </div>

            <div class="plan-card glass-card most-popular">
                <div class="popular-badge">★ MOST POPULAR</div>
                <h3>Pro Plan</h3>
                <p class="plan-tag">For creators, marketers, and agencies</p>
                <div class="price-container">
                    <span class="currency">Rp</span>
                    <span class="price-value" data-final-price="399000">399.000</span>
                </div>
                <p class="price-lifetime">/ month</p>
                <div class="price-strikeout">
                    <p>Rp <del>500.000</del></p>
                    <span class="save-badge">Save 20%</span>
                </div>

                <div class="key-features">
                    <h4>Key Features:</h4>
                    <ul>
                        <li><i class="fas fa-check-circle"></i> Veo 3 &amp; Veo 2 - UNLIMITED</li>
                        <li><i class="fas fa-check-circle"></i> Voice &amp; Image Generator - UNLIMITED</li>
                        <li><i class="fas fa-check-circle"></i> Face Swap - UNLIMITED</li>
                        <li><i class="fas fa-check-circle"></i> Grup Tutorial Premium</li>
                        <li><i class="fas fa-check-circle"></i> + 7 more features</li>
                    </ul>
                </div>

                <a href="#" class="btn-plan-select btn-primary-plan">
                    <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                    <span class="text-part">Get Full Access</span>
                </a>
            </div>

            <div class="plan-card glass-card">
                <h3>Master Plan</h3>
                <p class="plan-tag">Ultimate power for professionals</p>
                <div class="price-container">
                    <span class="currency">Rp</span>
                    <span class="price-value" data-final-price="699000">699.000</span>
                </div>
                <p class="price-lifetime">/ month</p>

                <div class="key-features">
                    <h4>Key Features:</h4>
                    <ul>
                        <li><i class="fas fa-check-circle"></i> Semua Fitur Pro Plan</li>
                        <li><i class="fas fa-check-circle"></i> Kling, Seedance, Nano Banana Unlimited - Selamanya</li>
                        <li><i class="fas fa-check-circle"></i> Bot Telegram All-in-One</li>
                        <li><i class="fas fa-check-circle"></i> Kecepatan Tinggi &amp; Stabil</li>
                        <li><i class="fas fa-check-circle"></i> No Garansi (Trick &amp; Celah)</li>
                        <li><i class="fas fa-check-circle"></i> + 9 more features</li>
                    </ul>
                </div>

                <a href="#" class="btn-plan-select">
                    <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                    <span class="text-part">Get Master Access</span>
                </a>
            </div>
        </div>

        <div class="contact-footer">
            <p>Need a custom solution? <a href="wa.me/62818404222">Contact us</a> for enterprise pricing</p>
        </div>
    </section>

    <section class="review-section" aria-labelledby="review-section-title">
        <div class="review-header">
            <h2 id="review-section-title">Loved by Marketers</h2>
            <p>who create winning ads</p>
            <p class="review-subtitle">Bergabunglah dengan ribuan pemasar yang sukses dalam kampanye iklan mereka dengan pembuatan video bertenaga AI.</p>
        </div>

        <div class="reviews-grid">
            <div class="review-card glass-card">
                <div class="quote-icon">”</div>
                <div class="rating">★★★★★</div>
                <p class="review-text">"Wah, fitur unlimited Veo 3.1 benar-benar gila. Saya menonton iklan gila-gilaan tanpa khawatir kehabisan kredit. Sungguh, ini mengubah alur kerja saya."</p>
                <div class="review-tag">Unlimited Veo 3.1</div>
                <div class="reviewer-info">
                    <div class="reviewer-avatar">S</div>
                    <div class="reviewer-details">
                        <p class="reviewer-name">Sarah Jombang</p>
                        <small>E-commerce Manager at FashionHub</small>
                    </div>
                </div>
            </div>

            <div class="review-card glass-card">
                <div class="quote-icon">”</div>
                <div class="rating">★★★★★</div>
                <p class="review-text">"Anjay, Akaygen untuk konten UGC keren banget! Videonya terlihat autentik banget, sampai-sampai kamu bisa bersumpah kalau itu dari pelanggan asli. Tingkat konversi saya melonjak drastis. Nggak nyangka ini bagus banget."</p>
                <div class="review-tag">AkayGen UGC Master</div>
                <div class="reviewer-info">
                    <div class="reviewer-avatar">M</div>
                    <div class="reviewer-details">
                        <p class="reviewer-name">JND Store</p>
                        <small>Content Creator at TechGadgets Pro</small>
                    </div>
                </div>
            </div>

            <div class="review-card glass-card">
                <div class="quote-icon">”</div>
                <div class="rating">★★★★★</div>
                <p class="review-text">"Oke, Sora 2 seharga Rp500 itu benar-benar gila. Platform lain mematok harga Rp15.000 untuk barang yang sama. Masa sih? Harga segitu nggak masuk akal. Sudah hemat ribuan bulan ini."</p>
                <div class="review-tag">Sora 2 - Rp 500 only</div>
                <div class="reviewer-info">
                    <div class="reviewer-avatar">E</div>
                    <div class="reviewer-details">
                        <p class="reviewer-name">Bagus</p>
                        <small>Founder &amp; CEO at BeautyBox</small>
                    </div>
                </div>
            </div>

            <div class="review-card glass-card">
                <div class="quote-icon">”</div>
                <div class="rating">★★★★★</div>
                <p class="review-text">"Kombinasi Veo tanpa batas + Sora 2 yang murah sungguh luar biasa nilainya. Saya sedang menguji banyak variasinya sekarang. Klien saya sangat puas dengan hasilnya. Platform ini sebenarnya adalah kode curang."</p>
                <div class="review-tag">Testing 100+ variations</div>
                <div class="reviewer-info">
                    <div class="reviewer-avatar">D</div>
                    <div class="reviewer-details">
                        <p class="reviewer-name">Erwanda Ade</p>
                        <small>Performance Marketer at GrowthLabs</small>
                    </div>
                </div>
            </div>

            <div class="review-card glass-card">
                <div class="quote-icon">”</div>
                <div class="rating">★★★★★</div>
                <p class="review-text">"Video UGC AkayGen itu keren banget. Saking nyatanya, pengikut saya sampai mengira saya mempekerjakan kreator sungguhan. Dan faktanya videonya nggak terbatas? Aduh, saya nggak akan pernah kembali ke pengeditan manual."</p>
                <div class="review-tag">100% organic look</div>
                <div class="reviewer-info">
                    <div class="reviewer-avatar">J</div>
                    <div class="reviewer-details">
                        <p class="reviewer-name">Dimas Bagus</p>
                        <small>Social Media Manager at FitnessPro</small>
                    </div>
                </div>
            </div>

            <div class="review-card glass-card">
                <div class="quote-icon">”</div>
                <div class="rating">★★★★★</div>
                <p class="review-text">"Membandingkan AKAY.IO dengan 3 pesaing dan jujur ​​saja, tidak ada tandingannya. Veo 3.1 unlimited + Sora 2 dengan harga gila-gilaan + kualitas NexaGen? Batalkan semua yang lain. Ini dia alatnya sekarang."</p>
                <div class="review-tag">Switched from 3 platforms</div>
                <div class="reviewer-info">
                    <div class="reviewer-avatar">A</div>
                    <div class="reviewer-details">
                        <p class="reviewer-name">Wibi Santoso</p>
                        <small>Head of Growth at DigitalNomad</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="review-stats">
            <div class="stat-item">
                <p class="stat-number">10K+</p>
                <small>Active Users</small>
            </div>
            <div class="stat-item">
                <p class="stat-number">2M+</p>
                <small>Videos Generated</small>
            </div>
            <div class="stat-item">
                <p class="stat-number">98%</p>
                <small>Satisfaction Rate</small>
            </div>
            <div class="stat-item">
                <p class="stat-number">4.9/5</p>
                <small>Average Rating</small>
            </div>
        </div>
    </section>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-col footer-info">
                <div class="footer-logo">
                    <img src="logo.png" alt="AKAY.IO Logo" class="logo-icon">
                    AKAY NUSANTARA
                </div>
                <p class="footer-description">Iklan video bertenaga AI yang menghasilkan konversi. Buat iklan yang sukses dalam hitungan detik dengan kekuatan kecerdasan buatan..</p>

                <div class="social-icons">
                    <a href="#" class="icon-circle"><i class="fab fa-tiktok"></i></a>
                    <a href="#" class="icon-circle"><i class="fab fa-telegram-plane"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h3>PRODUCT</h3>
                <ul>
                    <li><a href="#product">Get Started</a></li>
                    <li><a href="#pricing">Pricing</a></li>
                    <li><a href="#product">Features</a></li>
                    <li><a href="#use-cases">How It Works</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>RESOURCES</h3>
                <ul>
                    <li><a href="#resources">Blog</a></li>
                    <li><a href="#resources">Tutorials</a></li>
                    <li><a href="#resources">Case Studies</a></li>
                    <li><a href="#resources">Help Center</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>COMPANY</h3>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Contact</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© 2025 AKAY NUSANTARA. All rights reserved.</p>
            <div class="bottom-links">
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="#"><i class="fas fa-envelope"></i> Contact</a>
            </div>
        </div>
    </footer>
    

    <script>
        const endpoint = <?= json_encode($_SERVER['PHP_SELF'] ?? '') ?>;

        const menuToggle = document.getElementById('menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const bodyEl = document.body;
        const rootEl = document.documentElement;

        const loginModal = document.getElementById('login-modal');
        const signupModal = document.getElementById('signup-modal');
        const loginButtons = Array.from(document.querySelectorAll('.btn-login, .btn-login-mobile'));
        const signupButtons = Array.from(document.querySelectorAll('.btn-signup, .btn-signup-mobile'));
        const closeButtons = Array.from(document.querySelectorAll('.modal .close-btn'));

        const loginForm = loginModal ? loginModal.querySelector('form') : null;
        const signupForm = signupModal ? signupModal.querySelector('form') : null;
        const loginStatus = loginForm ? loginForm.querySelector('[data-role="login-status"]') : null;
        const signupStatus = signupForm ? signupForm.querySelector('[data-role="signup-status"]') : null;

        function setBodyOverflow(hidden) {
            document.body.style.overflow = hidden ? 'hidden' : 'auto';
        }

        function openModal(modal) {
            if (!modal) return;
            modal.classList.add('is-open');
            setBodyOverflow(true);
        }

        function closeModal(modal) {
            if (!modal) return;
            modal.classList.remove('is-open');
            if (!document.querySelector('.modal.is-open')) {
                setBodyOverflow(false);
            }
        }

        function updateMenuIcon() {
            if (!menuToggle) return;
            const icon = menuToggle.querySelector('i');
            if (!icon) return;
            if (mobileMenu && mobileMenu.classList.contains('open')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }

        function toggleMobileMenu() {
            if (!mobileMenu) return;
            mobileMenu.classList.toggle('open');
            updateMenuIcon();
        }

        if (menuToggle) {
            menuToggle.addEventListener('click', toggleMobileMenu);
        }

        function closeMobileMenu() {
            if (!mobileMenu) return;
            mobileMenu.classList.remove('open');
            updateMenuIcon();
        }

        function setTheme(mode, options = {}) {
            const theme = mode === 'dark' ? 'dark' : 'light';

            if (rootEl) {
                rootEl.setAttribute('data-theme', theme);
                rootEl.classList.remove('light-mode', 'dark-mode');
                rootEl.classList.add(theme === 'dark' ? 'dark-mode' : 'light-mode');
            }

            if (bodyEl) {
                bodyEl.classList.remove('light-mode', 'dark-mode');
                bodyEl.classList.add(theme === 'dark' ? 'dark-mode' : 'light-mode');
            }

            if (options.persist !== false) {
                try {
                    localStorage.setItem('akay-theme', theme);
                } catch (error) {
                    /* no-op */
                }
            }

            const icon = darkModeToggle ? darkModeToggle.querySelector('i') : null;
            if (icon) {
                icon.classList.remove('fa-moon', 'fa-sun');
                icon.classList.add(theme === 'dark' ? 'fa-sun' : 'fa-moon');
            }

            if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
                try {
                    window.dispatchEvent(new CustomEvent('akay-theme-change', { detail: theme }));
                } catch (error) {
                    /* ignore event errors */
                }
            }

            return theme;
        }

const securityGateOverlay = document.getElementById('securityGateOverlay');
const securityIpElement = document.getElementById('securityIp');
const securityContinueBtn = document.getElementById('securityContinue');

// 1. Ambil IP Publik dari API (membutuhkan koneksi internet)
function fetchPublicIp() {
    // Menggunakan API publik yang aman untuk mendapatkan alamat IP
    fetch('https://api.ipify.org?format=json') 
        .then(response => response.json())
        .then(data => {
            securityIpElement.textContent = data.ip;
        })
        .catch(error => {
            console.error('Failed to fetch IP:', error);
            securityIpElement.textContent = 'IP Not Found';
        });
}

// 2. Sembunyikan Gerbang Keamanan saat tombol diklik
securityContinueBtn.addEventListener('click', () => {
    // Tambahkan class 'hidden' untuk memulai animasi transisi dan menyembunyikan
    securityGateOverlay.classList.add('hidden'); 
    
    // Hapus overlay sepenuhnya setelah transisi selesai
    setTimeout(() => {
        securityGateOverlay.style.display = 'none';
    }, 500); // Sesuaikan dengan durasi transisi CSS
});


// Panggil fungsi saat DOM sudah dimuat
document.addEventListener('DOMContentLoaded', () => {
    // ... (Pastikan semua kode inisialisasi lain ada di sini) ...
    
    fetchPublicIp(); // Ambil IP saat halaman dimuat
});

        let savedTheme = 'light';
        try {
            const storedTheme = localStorage.getItem('akay-theme');
            if (storedTheme === 'dark') {
                savedTheme = 'dark';
            }
        } catch (error) {
            savedTheme = 'light';
        }
        setTheme(savedTheme);

        window.addEventListener('storage', (event) => {
            if (event.key === 'akay-theme') {
                const next = event.newValue === 'dark' ? 'dark' : 'light';
                setTheme(next, { persist: false });
            }
        });

        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', () => {
                const nextTheme = bodyEl.classList.contains('dark-mode') ? 'light' : 'dark';
                setTheme(nextTheme);
            });
        }

        loginButtons.forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                closeMobileMenu();
                if (loginForm) {
                    loginForm.reset();
                }
                if (loginStatus) {
                    loginStatus.textContent = '';
                    loginStatus.dataset.state = '';
                }
                openModal(loginModal);
                if (loginForm) {
                    const firstInput = loginForm.querySelector('input');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }
            });
        });

        signupButtons.forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                closeMobileMenu();
                if (signupForm) {
                    signupForm.reset();
                }
                if (signupStatus) {
                    signupStatus.textContent = '';
                    signupStatus.dataset.state = '';
                }
                openModal(signupModal);
                if (signupForm) {
                    const firstInput = signupForm.querySelector('input');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }
            });
        });

        closeButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const parentModal = btn.closest('.modal');
                closeModal(parentModal);
            });
        });

        window.addEventListener('click', (event) => {
            if (event.target === loginModal) {
                closeModal(loginModal);
            }
            if (event.target === signupModal) {
                closeModal(signupModal);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal(loginModal);
                closeModal(signupModal);
            }
        });

        function setStatus(el, message, state) {
            if (!el) return;
            el.textContent = message || '';
            if (state) {
                el.dataset.state = state;
            } else {
                delete el.dataset.state;
            }
        }

        function extractErrorMessage(errorData, fallback) {
            if (!errorData) return fallback;
            if (typeof errorData === 'string') return errorData;
            if (Array.isArray(errorData)) return errorData.join(', ');
            if (typeof errorData === 'object') {
                const parts = [];
                for (const value of Object.values(errorData)) {
                    if (!value) continue;
                    if (Array.isArray(value)) {
                        parts.push(value.join(', '));
                    } else {
                        parts.push(String(value));
                    }
                }
                if (parts.length) {
                    return parts.join(' ');
                }
            }
            return fallback;
        }

        if (loginForm) {
            loginForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const formData = new FormData(loginForm);
                const username = formData.get('username');
                const password = formData.get('password');

                if (!username || !password) {
                    setStatus(loginStatus, 'Lengkapi username dan password.', 'error');
                    return;
                }

                const submitButton = loginForm.querySelector('button[type="submit"]');
                if (submitButton) submitButton.disabled = true;
                setStatus(loginStatus, 'Memproses login…', 'info');

                try {
                    const response = await fetch(`${endpoint}?api=login`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ username, password })
                    });
                    const data = await response.json().catch(() => null);

                    if (!response.ok || !data || !data.ok) {
                        const message = extractErrorMessage(data ? data.error || data.message : null, `HTTP ${response.status}`);
                        throw new Error(message);
                    }

                    setStatus(loginStatus, 'Login berhasil! Mengarahkan…', 'success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 600);
                } catch (error) {
                    setStatus(loginStatus, error.message || 'Login gagal. Coba lagi.', 'error');
                } finally {
                    if (submitButton) submitButton.disabled = false;
                }
            });
        }

        if (signupForm) {
            signupForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const formData = new FormData(signupForm);
                const username = formData.get('username');
                const email = formData.get('email');
                const password = formData.get('password');

                if (!username || !password) {
                    setStatus(signupStatus, 'Username dan password wajib diisi.', 'error');
                    return;
                }

                const submitButton = signupForm.querySelector('button[type="submit"]');
                if (submitButton) submitButton.disabled = true;
                setStatus(signupStatus, 'Mendaftarkan akun…', 'info');

                try {
                    const payload = { username, password };
                    if (email) {
                        payload.email = email;
                    }

                    const response = await fetch(`${endpoint}?api=register`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    });
                    const data = await response.json().catch(() => null);

                    if (!response.ok || !data || !data.ok) {
                        const message = extractErrorMessage(data ? data.error || data.message : null, `HTTP ${response.status}`);
                        throw new Error(message);
                    }

                    setStatus(signupStatus, 'Registrasi berhasil! Silakan login.', 'success');
                    setTimeout(() => {
                        closeModal(signupModal);
                        if (loginForm) {
                            loginForm.reset();
                        }
                        openModal(loginModal);
                    }, 700);
                } catch (error) {
                    setStatus(signupStatus, error.message || 'Registrasi gagal. Coba lagi.', 'error');
                } finally {
                    if (submitButton) submitButton.disabled = false;
                }
            });
        }

        function animatePrice(element, finalPrice, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const easedProgress = 1 - Math.pow(1 - progress, 3);
                const currentPrice = Math.floor(easedProgress * finalPrice);
                const formattedPrice = currentPrice.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                element.textContent = formattedPrice;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const priceElements = document.querySelectorAll('.price-value');
            priceElements.forEach((element) => {
                const finalPrice = parseInt(element.getAttribute('data-final-price') || '0', 10);
                element.textContent = '0';
                if (finalPrice > 0) {
                    animatePrice(element, finalPrice, 1500);
                }
            });
        });
    </script>
</body>
</html>
<?php
    exit;
}

header('Location: dashboard.php');
exit;
