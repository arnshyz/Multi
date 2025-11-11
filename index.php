<?php
require_once __DIR__ . '/auth.php';

auth_session_start();

function auth_json_response($payload, $status = 200)
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

// ====== CONFIG FREEPIK ======
$FREEPIK_API_KEYS = [];
$FREEPIK_API_KEYS_RAW = getenv('FREEPIK_API_KEYS');
if (is_string($FREEPIK_API_KEYS_RAW) && trim($FREEPIK_API_KEYS_RAW) !== '') {
    $FREEPIK_API_KEYS = preg_split('/[\r\n,]+/', $FREEPIK_API_KEYS_RAW, -1, PREG_SPLIT_NO_EMPTY);
    $FREEPIK_API_KEYS = array_map('trim', $FREEPIK_API_KEYS);
    $FREEPIK_API_KEYS = array_filter($FREEPIK_API_KEYS, static function ($value) {
        return $value !== '';
    });
    $FREEPIK_API_KEYS = array_values($FREEPIK_API_KEYS);
}

$FREEPIK_BASE_URL = 'https://api.freepik.com';

$FREEPIK_REDIS_CONFIG = [
    'host'     => getenv('FREEPIK_REDIS_HOST') ?: getenv('REDIS_HOST') ?: '127.0.0.1',
    'port'     => (int)(getenv('FREEPIK_REDIS_PORT') ?: getenv('REDIS_PORT') ?: 6379),
    'timeout'  => (float)(getenv('FREEPIK_REDIS_TIMEOUT') ?: getenv('REDIS_TIMEOUT') ?: 1.5),
    'password' => getenv('FREEPIK_REDIS_PASSWORD') ?: getenv('REDIS_PASSWORD') ?: null,
    'database' => (int)(getenv('FREEPIK_REDIS_DATABASE') ?: getenv('REDIS_DATABASE') ?: 0),
    'key'      => getenv('FREEPIK_REDIS_KEY') ?: 'freepik:api-keys',
];

$GEMINI_API_KEY = trim((string)getenv('GEMINI_API_KEY'));
$GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com';

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

function drive_item_type_from_extension(string $extension): string
{
    $extension = strtolower($extension);
    $videoExtensions = ['mp4', 'mov', 'mkv', 'webm', 'avi'];
    return in_array($extension, $videoExtensions, true) ? 'video' : 'image';
}

function drive_merge_items_with_filesystem(array $account, array $items): array
{
    $info = auth_drive_storage_info($account);
    $dir = $info['absolute'];

    $normalizedItems = [];
    foreach ($items as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        if (isset($entry['storage_path'])) {
            $normalizedPath = auth_drive_normalize_storage_path($entry['storage_path']);
            if ($normalizedPath) {
                $absolute = __DIR__ . '/' . $normalizedPath;
                if (is_file($absolute)) {
                    $entry['storage_path'] = $normalizedPath;
                    $localUrl = app_public_url($normalizedPath);
                    $entry['url'] = $localUrl;
                    if (($entry['type'] ?? 'image') !== 'video') {
                        $entry['thumbnail_url'] = $localUrl;
                    }
                }
            }
        }

        $normalizedItems[] = $entry;
    }

    if ($normalizedItems) {
        $items = $normalizedItems;
    }

    if (!is_dir($dir)) {
        return $items;
    }

    $existingByUrl = [];
    $existingByStorage = [];
    foreach ($items as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $url = isset($entry['url']) ? (string)$entry['url'] : '';
        if ($url !== '') {
            $existingByUrl[$url] = true;
        }
        $storage = isset($entry['storage_path']) ? auth_drive_normalize_storage_path($entry['storage_path']) : null;
        if ($storage) {
            $existingByStorage[$storage] = true;
        }
    }

    $files = glob($dir . '/*');
    if ($files === false) {
        $files = [];
    }

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $basename = basename($file);
        $relative = $info['relative'] . '/' . $basename;
        $url = app_public_url($relative);

        if (isset($existingByUrl[$url]) || isset($existingByStorage[$relative])) {
            continue;
        }

        $type = drive_item_type_from_extension(pathinfo($basename, PATHINFO_EXTENSION));
        $createdAt = @filemtime($file);
        if (!$createdAt) {
            $createdAt = time();
        }

        $items[] = [
            'id' => 'fs_' . sha1($relative),
            'type' => $type,
            'url' => $url,
            'thumbnail_url' => $type === 'image' ? $url : null,
            'model' => 'upload',
            'prompt' => null,
            'created_at' => gmdate('c', $createdAt),
            'storage_path' => $relative,
        ];
    }

    usort($items, static function ($a, $b) {
        $aTime = isset($a['created_at']) ? strtotime((string)$a['created_at']) : false;
        $bTime = isset($b['created_at']) ? strtotime((string)$b['created_at']) : false;
        $aTime = $aTime !== false ? $aTime : 0;
        $bTime = $bTime !== false ? $bTime : 0;
        return $bTime <=> $aTime;
    });

    return array_values($items);
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

function gemini_api_key()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $key = getenv('GEMINI_API_KEY');
    if (!is_string($key)) {
        $key = '';
    }

    $cached = trim($key);

    return $cached;
}

function gemini_api_request($endpoint, $method = 'POST', $body = null, $query = [])
{
    global $GEMINI_BASE_URL;

    $apiKey = gemini_api_key();
    if ($apiKey === '') {
        return [
            'ok'     => false,
            'status' => 500,
            'error'  => 'GEMINI_API_KEY belum dikonfigurasi.'
        ];
    }

    if (!is_array($query)) {
        $query = [];
    }
    $query['key'] = $apiKey;

    $url = rtrim($GEMINI_BASE_URL, '/') . $endpoint;
    $separator = strpos($url, '?') === false ? '?' : '&';
    $url .= $separator . http_build_query($query);

    $method = strtoupper($method ?: 'POST');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Accept: application/json'];

    if ($method !== 'GET' && $body !== null) {
        if (is_array($body) || is_object($body)) {
            $payload = json_encode($body);
        } else {
            $payload = (string)$body;
        }

        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $responseBody = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno) {
        return [
            'ok'     => false,
            'status' => 500,
            'error'  => 'cURL error: ' . $error
        ];
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

    $ok = $statusCode >= 200 && $statusCode < 300;

    $result = [
        'ok'     => $ok,
        'status' => $statusCode,
        'data'   => $data,
    ];

    if (!$ok) {
        if (is_array($data) && isset($data['error'])) {
            if (is_string($data['error'])) {
                $result['error'] = $data['error'];
            } elseif (is_array($data['error']) && isset($data['error']['message'])) {
                $result['error'] = $data['error']['message'];
            }
        }
        if (empty($result['error'])) {
            $result['error'] = 'Permintaan API Gemini gagal.';
        }
    }

    return $result;
}

function gemini_extract_text($response)
{
    if (!is_array($response)) {
        return null;
    }

    if (isset($response['candidates']) && is_array($response['candidates'])) {
        foreach ($response['candidates'] as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    if (is_array($part) && isset($part['text'])) {
                        return (string)$part['text'];
                    }
                }
            }
        }
    }

    if (isset($response['text'])) {
        return (string)$response['text'];
    }

    return null;
}

function gemini_extract_audio($response)
{
    if (!is_array($response)) {
        return null;
    }

    if (isset($response['candidates']) && is_array($response['candidates'])) {
        foreach ($response['candidates'] as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    if (!is_array($part)) {
                        continue;
                    }
                    if (isset($part['inline_data']['data'])) {
                        $mime = isset($part['inline_data']['mime_type'])
                            ? (string)$part['inline_data']['mime_type']
                            : 'audio/mp3';

                        return [
                            'data' => (string)$part['inline_data']['data'],
                            'mime' => $mime,
                        ];
                    }
                }
            }
        }
    }

    if (isset($response['audio'])) {
        $audio = $response['audio'];
        if (is_array($audio) && isset($audio['data'])) {
            return [
                'data' => (string)$audio['data'],
                'mime' => isset($audio['mime']) ? (string)$audio['mime'] : 'audio/mp3',
            ];
        }
        if (is_string($audio)) {
            return [
                'data' => $audio,
                'mime' => 'audio/mp3',
            ];
        }
    }

    return null;
}

function gemini_collect_media_urls($value, &$urls)
{
    if (is_array($value)) {
        foreach ($value as $item) {
            gemini_collect_media_urls($item, $urls);
        }
        return;
    }

    if (!is_string($value)) {
        return;
    }

    if (preg_match('#^https?://#i', $value)) {
        $urls[$value] = true;
    }
}

function gemini_extract_video_urls($response)
{
    if (!is_array($response)) {
        return [];
    }

    $urls = [];
    gemini_collect_media_urls($response, $urls);

    return array_keys($urls);
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
    $record = auth_find_account($username);
    $passwordMatches = false;
    if ($record && isset($record['password_hash']) && $record['password_hash'] !== '') {
        $passwordMatches = password_verify($password, (string)$record['password_hash']);
    }

    if ($passwordMatches && (!empty($record['is_banned']) || !empty($record['is_blocked']))) {
        auth_json_response([
            'ok' => false,
            'status' => 423,
            'error' => 'Akun sedang di banned silahkan mengajukan banding ke customer service'
        ], 423);
    }

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

if ($requestedApi === 'validate-freepik') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST untuk validasi API key.'
        ], 405);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $apiKey = isset($payload['freepik_api_key']) ? trim((string)$payload['freepik_api_key']) : '';
    if ($apiKey === '') {
        auth_json_response([
            'ok' => false,
            'status' => 422,
            'error' => ['apiKey' => 'API key wajib diisi.']
        ], 422);
    }

    $error = null;
    if (auth_validate_freepik_api_key($apiKey, $error)) {
        auth_json_response([
            'ok' => true,
            'status' => 200,
            'message' => 'API key valid dan aktif.'
        ]);
    }

    $status = 422;
    if ($error !== null && (strpos($error, 'Tidak dapat') === 0 || strpos($error, 'Endpoint verifikasi') === 0)) {
        $status = 503;
    }

    auth_json_response([
        'ok' => false,
        'status' => $status,
        'error' => ['apiKey' => $error ?: 'API key tidak valid.']
    ], $status);
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
    $payload['announcements'] = auth_announcements_public_view();

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

    if (auth_is_account_restricted($account)) {
        auth_json_response([
            'ok' => false,
            'status' => 403,
            'error' => 'Akun sedang di banned silahkan mengajukan banding ke customer service'
        ], 403);
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

    $account = auth_normalize_account($account);

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

    $account = auth_normalize_account($account);

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

    $account = auth_normalize_account($account);

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

    $account = auth_normalize_account($account);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $items = auth_drive_get_items($account['id']);
        $items = drive_merge_items_with_filesystem($account, $items);
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

        $storedItems = is_array($stored) ? $stored : [];
        $storedItems = drive_merge_items_with_filesystem($account, $storedItems);

        auth_json_response([
            'ok' => true,
            'status' => 200,
            'data' => [
                'items' => $storedItems,
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

    $account = auth_normalize_account($account);

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $itemId = isset($payload['id']) ? trim((string)$payload['id']) : '';
    $itemUrl = isset($payload['url']) ? trim((string)$payload['url']) : '';
    $storagePath = isset($payload['storage_path']) ? trim((string)$payload['storage_path']) : '';

    if ($itemId === '' && $itemUrl === '' && $storagePath === '') {
        auth_json_response([
            'ok' => false,
            'status' => 422,
            'error' => 'ID atau URL item wajib diisi.'
        ], 422);
    }

    $errors = [];
    $items = auth_drive_delete_item($account['id'], $itemId, $itemUrl, $storagePath !== '' ? $storagePath : null, $errors);
    if ($items === null) {
        $status = isset($errors['general']) ? 500 : 404;
        auth_json_response([
            'ok' => false,
            'status' => $status,
            'error' => $errors ?: 'Item drive tidak ditemukan.'
        ], $status);
    }

    $items = drive_merge_items_with_filesystem($account, $items);

    auth_json_response([
        'ok' => true,
        'status' => 200,
        'data' => [
            'items' => $items,
        ],
    ]);
}

if ($requestedApi === 'gemini') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auth_json_response([
            'ok' => false,
            'status' => 405,
            'error' => 'Gunakan metode POST untuk mengakses Gemini API.'
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
        $payload = [];
    }

    $action = isset($payload['action']) ? strtolower(trim((string)$payload['action'])) : '';
    if ($action === '') {
        auth_json_response([
            'ok' => false,
            'status' => 422,
            'error' => 'Field "action" wajib diisi.'
        ], 422);
    }

    if ($action === 'text') {
        $prompt = isset($payload['prompt']) ? trim((string)$payload['prompt']) : '';
        if ($prompt === '') {
            auth_json_response([
                'ok' => false,
                'status' => 422,
                'error' => ['prompt' => 'Prompt wajib diisi.']
            ], 422);
        }

        $model = isset($payload['model']) ? trim((string)$payload['model']) : '';
        if ($model === '') {
            $model = 'gemini-1.5-flash-latest';
        }

        $system = isset($payload['system']) ? trim((string)$payload['system']) : '';
        $temperature = isset($payload['temperature']) && is_numeric($payload['temperature'])
            ? (float)$payload['temperature']
            : null;
        $maxTokens = isset($payload['maxOutputTokens']) && is_numeric($payload['maxOutputTokens'])
            ? (int)$payload['maxOutputTokens']
            : null;

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ],
                ],
            ],
        ];

        if ($system !== '') {
            $body['system_instruction'] = [
                'role' => 'system',
                'parts' => [
                    ['text' => $system],
                ],
            ];
        }

        $genConfig = [];
        if ($temperature !== null) {
            if ($temperature < 0) {
                $temperature = 0.0;
            }
            if ($temperature > 2) {
                $temperature = 2.0;
            }
            $genConfig['temperature'] = $temperature;
        }
        if ($maxTokens !== null && $maxTokens > 0) {
            $genConfig['maxOutputTokens'] = $maxTokens;
        }
        if ($genConfig) {
            $body['generationConfig'] = $genConfig;
        }

        $result = gemini_api_request('/v1beta/models/' . rawurlencode($model) . ':generateContent', 'POST', $body);
        if (!$result['ok']) {
            $status = $result['status'] ?: 500;
            auth_json_response([
                'ok' => false,
                'status' => $status,
                'error' => $result['error'] ?? 'Permintaan Gemini gagal.'
            ], $status);
        }

        $responseData = is_array($result['data']) ? $result['data'] : [];
        $text = gemini_extract_text($responseData);

        $responsePayload = [
            'model' => $model,
            'text' => $text,
            'raw' => $responseData,
        ];

        auth_metrics_record_generation('gemini', [
            'type' => 'gemini-text',
            'detail' => $model,
            'user_id' => $account['id'] ?? null,
            'username' => $account['username'] ?? '',
        ]);

        auth_json_response([
            'ok' => true,
            'status' => 200,
            'data' => $responsePayload,
        ]);
    }

    if ($action === 'speech') {
        $prompt = isset($payload['prompt']) ? trim((string)$payload['prompt']) : '';
        if ($prompt === '') {
            auth_json_response([
                'ok' => false,
                'status' => 422,
                'error' => ['prompt' => 'Teks untuk dibacakan wajib diisi.']
            ], 422);
        }

        $model = isset($payload['model']) ? trim((string)$payload['model']) : '';
        if ($model === '') {
            $model = 'gemini-1.5-flash-latest';
        }

        $voice = isset($payload['voice']) ? trim((string)$payload['voice']) : 'Puck';
        $language = isset($payload['language']) ? trim((string)$payload['language']) : 'en-US';
        $mimeType = isset($payload['mimeType']) ? trim((string)$payload['mimeType']) : 'audio/mp3';
        if ($mimeType === '') {
            $mimeType = 'audio/mp3';
        }

        $temperature = isset($payload['temperature']) && is_numeric($payload['temperature'])
            ? (float)$payload['temperature']
            : null;

        $generationConfig = [
            'responseMimeType' => $mimeType,
        ];
        if ($voice !== '') {
            $generationConfig['responseVoice'] = $voice;
        }
        if ($temperature !== null) {
            if ($temperature < 0) {
                $temperature = 0.0;
            }
            if ($temperature > 2) {
                $temperature = 2.0;
            }
            $generationConfig['temperature'] = $temperature;
        }

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ],
                ],
            ],
            'generationConfig' => $generationConfig,
        ];

        if ($voice !== '' || $language !== '') {
            $voiceConfig = ['prebuiltVoiceConfig' => []];
            if ($voice !== '') {
                $voiceConfig['prebuiltVoiceConfig']['voiceName'] = $voice;
            }
            if ($language !== '') {
                $voiceConfig['prebuiltVoiceConfig']['languageCode'] = $language;
            }
            $body['voiceConfig'] = $voiceConfig;
        }

        $result = gemini_api_request('/v1beta/models/' . rawurlencode($model) . ':generateContent', 'POST', $body);
        if (!$result['ok']) {
            $status = $result['status'] ?: 500;
            auth_json_response([
                'ok' => false,
                'status' => $status,
                'error' => $result['error'] ?? 'Permintaan Gemini gagal.'
            ], $status);
        }

        $responseData = is_array($result['data']) ? $result['data'] : [];
        $audio = gemini_extract_audio($responseData);
        if (!$audio) {
            auth_json_response([
                'ok' => false,
                'status' => 502,
                'error' => 'Gemini tidak mengembalikan audio.'
            ], 502);
        }

        $responsePayload = [
            'model' => $model,
            'audio' => $audio['data'],
            'mimeType' => $audio['mime'] ?? $mimeType,
            'raw' => $responseData,
        ];

        auth_metrics_record_generation('gemini', [
            'type' => 'gemini-speech',
            'detail' => $model . '|' . $voice,
            'user_id' => $account['id'] ?? null,
            'username' => $account['username'] ?? '',
        ]);

        auth_json_response([
            'ok' => true,
            'status' => 200,
            'data' => $responsePayload,
        ]);
    }

    if ($action === 'veo' || $action === 'video') {
        $prompt = isset($payload['prompt']) ? trim((string)$payload['prompt']) : '';
        if ($prompt === '') {
            auth_json_response([
                'ok' => false,
                'status' => 422,
                'error' => ['prompt' => 'Deskripsi video wajib diisi.']
            ], 422);
        }

        $dialogue = isset($payload['dialogue']) ? trim((string)$payload['dialogue']) : '';
        $model = isset($payload['model']) ? trim((string)$payload['model']) : 'veo-3.1';
        if ($model === '') {
            $model = 'veo-3.1';
        }

        $duration = isset($payload['duration']) ? (int)$payload['duration'] : 0;
        $aspect = isset($payload['aspectRatio']) ? trim((string)$payload['aspectRatio']) : '';
        $style = isset($payload['style']) ? trim((string)$payload['style']) : '';

        $body = [
            'prompt' => [
                'text' => $prompt,
            ],
        ];

        if ($dialogue !== '') {
            $body['narration'] = [
                [
                    'speaker' => 'Narrator',
                    'text' => $dialogue,
                ],
            ];
        }

        $config = [];
        if ($duration > 0) {
            $config['duration'] = 'PT' . $duration . 'S';
        }
        if ($aspect !== '') {
            $config['aspectRatio'] = $aspect;
        }
        if ($style !== '') {
            $config['style'] = $style;
        }
        if ($config) {
            $body['config'] = $config;
        }

        if (!empty($payload['referenceImage']) && is_string($payload['referenceImage'])) {
            $body['referenceImage'] = $payload['referenceImage'];
        }

        $result = gemini_api_request('/v1beta/models/' . rawurlencode($model) . ':generateVideo', 'POST', $body);
        if (!$result['ok']) {
            $status = $result['status'] ?: 500;
            auth_json_response([
                'ok' => false,
                'status' => $status,
                'error' => $result['error'] ?? 'Permintaan Gemini gagal.'
            ], $status);
        }

        $responseData = is_array($result['data']) ? $result['data'] : [];
        $videoUrls = gemini_extract_video_urls($responseData);
        $operation = null;
        if (isset($responseData['name'])) {
            $operation = (string)$responseData['name'];
        } elseif (isset($responseData['operation'])) {
            $operation = (string)$responseData['operation'];
        }

        $responsePayload = [
            'model' => $model,
            'operation' => $operation,
            'videoUrls' => $videoUrls,
            'raw' => $responseData,
        ];

        auth_metrics_record_generation('gemini', [
            'type' => 'gemini-veo',
            'detail' => $model,
            'user_id' => $account['id'] ?? null,
            'username' => $account['username'] ?? '',
        ]);

        auth_json_response([
            'ok' => true,
            'status' => 200,
            'data' => $responsePayload,
        ]);
    }

    auth_json_response([
        'ok' => false,
        'status' => 404,
        'error' => 'Aksi Gemini tidak dikenal.'
    ], 404);
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

    $account = auth_current_account();
    if (!$account) {
        echo json_encode([
            'ok' => false,
            'status' => 401,
            'error' => 'Sesi berakhir, silakan login ulang.'
        ]);
        exit;
    }

    $account = auth_normalize_account($account);

    if (auth_is_account_restricted($account)) {
        echo json_encode([
            'ok' => false,
            'status' => 403,
            'error' => 'Akun sedang di banned silahkan mengajukan banding ke customer service'
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

    $storageInfo = auth_drive_ensure_storage_directory($account);
    $dir = $storageInfo['absolute'];

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

    $publicPath = $storageInfo['relative'] . '/' . $filename;

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

    $account = auth_current_account();
    if (!$account) {
        echo json_encode([
            'ok' => false,
            'status' => 401,
            'error' => 'Sesi berakhir, silakan login ulang.'
        ]);
        exit;
    }

    $account = auth_normalize_account($account);

    if (auth_is_account_restricted($account)) {
        echo json_encode([
            'ok' => false,
            'status' => 403,
            'error' => 'Akun sedang di banned silahkan mengajukan banding ke customer service'
        ]);
        exit;
    }

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

    $storageInfo = auth_drive_ensure_storage_directory($account);
    $dir = $storageInfo['absolute'];

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

    $publicPath = $storageInfo['relative'] . '/' . $filename;

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

    $account = auth_current_account();
    if ($account && auth_is_account_restricted($account)) {
        echo json_encode([
            'ok' => false,
            'status' => 403,
            'error' => 'Akun sedang di banned silahkan mengajukan banding ke customer service'
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

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

    $success = $statusCode >= 200 && $statusCode < 300;

    if ($success && isset($payload['method']) && strtoupper((string)$payload['method']) !== 'GET' && $account) {
        auth_metrics_record_generation('freepik', [
            'type' => 'freepik',
            'detail' => isset($payload['path']) ? (string)$payload['path'] : '',
            'user_id' => $account['id'] ?? null,
            'username' => $account['username'] ?? '',
        ]);
    }

    echo json_encode([
        'ok'     => $success,
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
                <a href="#" class="cta-button js-open-signup">
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
            <h2 id="signup-modal-title">Daftar Akun Baru</h2>
            <form>
                <label for="signupFreepikKey" class="modal-label">Freepik API Key (opsional)</label>
                <div class="api-key-field">
                    <input type="text" id="signupFreepikKey" name="freepik_api_key" placeholder="Opsional: masukkan Freepik API key" autocomplete="off">
                    <button type="button" class="btn-primary" id="freepikValidateBtn">Validasi</button>
                </div>
                <div class="form-status small" data-role="apikey-status" aria-live="polite" data-state="info">Opsional: isi Freepik API key jika ingin menghubungkan akun Freepik.</div>

                <label for="signupUsername" class="modal-label">Username</label>
                <input type="text" id="signupUsername" name="username" placeholder="Nama Pengguna" required autocomplete="username">

                <label for="signupEmail" class="modal-label">Email</label>
                <input type="email" id="signupEmail" name="email" placeholder="Email" required autocomplete="email">

                <label for="signupPassword" class="modal-label">Password</label>
                <input type="password" id="signupPassword" name="password" placeholder="Password" required autocomplete="new-password" minlength="6">

                <button type="submit" class="btn-primary" id="signupSubmit" disabled>Daftar</button>
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
                <img src="assets/images/ugc-affiliate.svg" alt="Visual konsep konten UGC affiliate">
            </div>
            <div class="feature-content">
                <span class="feature-tag">AI-POWERED</span>
                <h3>Pembuatan Video UGC Instan</h3>
                <p>Ubah produk apa pun menjadi video konten buatan pengguna yang autentik dalam hitungan menit. AI kami menemukan musik dan video menarik yang tampak seperti berasal dari pelanggan sungguhan.</p>
                <a href="#" class="btn-try-now js-open-signup">
                    <i class="fas fa-arrow-right"></i> Try it now
                </a>
            </div>
        </div>

        <div class="feature-card glass-card reverse-layout">
            <div class="feature-image">
                <span class="image-badge">Product Demo</span>
                <img src="assets/images/product-demo.svg" alt="Visual demo produk interaktif">
            </div>
            <div class="feature-content">
                <span class="feature-tag">LIGHTNING FAST</span>
                <h3>Real-Time Ad Optimization</h3>
                <p>Uji berbagai variasi secara otomatis dan optimalkan untuk konversi maksimal. AI kami menganalisis performa dan menyesuaikan iklan Anda secara real-time untuk hasil terbaik.</p>
                <a href="#" class="btn-try-now js-open-signup">
                    <i class="fas fa-arrow-right"></i> Try it now
                </a>
            </div>
        </div>

        <div class="feature-card glass-card">
            <div class="feature-image">
                <span class="image-badge">Analytics View</span>
                <img src="assets/images/analytics-view.svg" alt="Visual dashboard analitik">
            </div>
            <div class="feature-content">
                <span class="feature-tag">PERFORMANCE</span>
                <h3>Data-Driven Insights</h3>
                <p>Dapatkan analisis mendetail tentang apa yang berhasil dan apa yang tidak. Lacak interaksi, konversi, dan ROI di semua iklan video Anda dengan wawasan yang dapat ditindaklanjuti.</p>
                <a href="#" class="btn-try-now js-open-signup">
                    <i class="fas fa-arrow-right"></i> Try it now
                </a>
            </div>
        </div>

        <div class="feature-card glass-card reverse-layout">
            <div class="feature-image">
                <span class="image-badge">Target Audience</span>
                <img src="assets/images/target-audience.svg" alt="Visual pemetaan target audiens">
            </div>
            <div class="feature-content">
                <span class="feature-tag">SMART TARGETING</span>
                <h3>Audience Precision</h3>
                <p>Jangkau audiens yang tepat di waktu yang tepat. AI kami secara otomatis mengidentifikasi dan menargetkan pelanggan ideal Anda berdasarkan perilaku dan pola interaksi..</p>
                <a href="#" class="btn-try-now js-open-signup">
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
            <a href="#" class="cta-button-small js-open-signup">
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

                <a href="#" class="btn-plan-select js-open-signup">
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

                <a href="https://wa.me/62818404222?text=Halo%20AKAY%20IO%2C%20saya%20ingin%20registrasi%20paket%20Get%20Full%20Access." class="btn-plan-select btn-primary-plan" target="_blank" rel="noopener">
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

                <a href="https://wa.me/62818404222?text=Halo%20AKAY%20IO%2C%20saya%20ingin%20registrasi%20paket%20Get%20Master%20Access." class="btn-plan-select" target="_blank" rel="noopener">
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
        const signupButtons = Array.from(document.querySelectorAll('.btn-signup, .btn-signup-mobile, .js-open-signup'));
        const closeButtons = Array.from(document.querySelectorAll('.modal .close-btn'));

        const loginForm = loginModal ? loginModal.querySelector('form') : null;
        const signupForm = signupModal ? signupModal.querySelector('form') : null;
        const loginStatus = loginForm ? loginForm.querySelector('[data-role="login-status"]') : null;
        const signupStatus = signupForm ? signupForm.querySelector('[data-role="signup-status"]') : null;
        const apiKeyStatus = signupForm ? signupForm.querySelector('[data-role="apikey-status"]') : null;
        const signupApiKeyInput = document.getElementById('signupFreepikKey');
        const freepikValidateBtn = document.getElementById('freepikValidateBtn');
        const signupSubmitBtn = document.getElementById('signupSubmit');
        let freepikValidated = !signupApiKeyInput || signupApiKeyInput.value.trim() === '';
        let freepikValidating = false;
        let signupSubmitting = false;

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
                if (apiKeyStatus) {
                    setStatus(apiKeyStatus, '', null);
                }
                freepikValidated = true;
                freepikValidating = false;
                if (signupApiKeyInput) {
                    signupApiKeyInput.value = '';
                }
                setDefaultApiKeyStatus();
                refreshSignupControls();
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

        const featureCards = Array.from(document.querySelectorAll('.feature-card'));
        if (featureCards.length) {
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.25 });

                featureCards.forEach((card) => observer.observe(card));
            } else {
                featureCards.forEach((card) => card.classList.add('is-visible'));
            }
        }

        if (signupApiKeyInput) {
            signupApiKeyInput.addEventListener('input', () => {
                const value = signupApiKeyInput.value.trim();
                freepikValidated = value === '';
                if (apiKeyStatus) {
                    if (value === '') {
                        setDefaultApiKeyStatus();
                    } else {
                        setStatus(apiKeyStatus, 'Klik "Validasi" untuk memastikan API key aktif.', 'info');
                    }
                }
                refreshSignupControls();
            });
        }

        if (freepikValidateBtn) {
            freepikValidateBtn.addEventListener('click', async () => {
                if (!signupApiKeyInput) {
                    return;
                }

                const apiKey = signupApiKeyInput.value.trim();
                if (!apiKey) {
                    setStatus(apiKeyStatus, 'Masukkan Freepik API key terlebih dahulu.', 'error');
                    signupApiKeyInput.focus();
                    return;
                }

                freepikValidating = true;
                refreshSignupControls();
                setStatus(apiKeyStatus, 'Memvalidasi API key…', 'info');

                try {
                    const response = await fetch(`${endpoint}?api=validate-freepik`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ freepik_api_key: apiKey })
                    });
                    const data = await response.json().catch(() => null);

                    if (!response.ok || !data || !data.ok) {
                        const message = extractErrorMessage(data ? data.error : null, `HTTP ${response.status}`);
                        throw new Error(message || 'Validasi API key gagal.');
                    }

                    freepikValidated = true;
                    setStatus(apiKeyStatus, 'API key valid dan aktif.', 'success');
                } catch (error) {
                    freepikValidated = false;
                    setStatus(apiKeyStatus, error.message || 'Validasi API key gagal.', 'error');
                } finally {
                    freepikValidating = false;
                    refreshSignupControls();
                }
            });
        }

        function setStatus(el, message, state) {
            if (!el) return;
            el.textContent = message || '';
            if (state) {
                el.dataset.state = state;
            } else {
                delete el.dataset.state;
            }
        }

        function setDefaultApiKeyStatus() {
            if (!apiKeyStatus) return;
            setStatus(apiKeyStatus, 'Opsional: isi Freepik API key jika ingin menghubungkan akun Freepik.', 'info');
        }

        function refreshSignupControls() {
            if (signupSubmitBtn) {
                signupSubmitBtn.disabled = signupSubmitting || freepikValidating;
            }
            if (freepikValidateBtn) {
                const hasKey = signupApiKeyInput && signupApiKeyInput.value.trim() !== '';
                freepikValidateBtn.disabled = freepikValidating || !hasKey;
            }
        }

        setDefaultApiKeyStatus();
        refreshSignupControls();

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
                        const state = response.status === 423 ? 'warning' : 'error';
                        setStatus(loginStatus, message || 'Login gagal. Coba lagi.', state);
                        return;
                    }

                    setStatus(loginStatus, 'Login berhasil! Mengarahkan…', 'success');
                    setTimeout(() => {
                        window.location.reload();
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
                const apiKey = signupApiKeyInput ? signupApiKeyInput.value.trim() : (formData.get('freepik_api_key') || '').trim();

                if (!username || !password) {
                    setStatus(signupStatus, 'Username dan password wajib diisi.', 'error');
                    return;
                }

                const submitButton = signupSubmitBtn || signupForm.querySelector('button[type="submit"]');
                signupSubmitting = true;
                if (submitButton) submitButton.disabled = true;
                refreshSignupControls();
                setStatus(signupStatus, 'Mendaftarkan akun…', 'info');

                try {
                    const payload = { username, password, freepik_api_key: apiKey };
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
                        if (data && data.error && data.error.apiKey) {
                            freepikValidated = false;
                            refreshSignupControls();
                            setStatus(apiKeyStatus, extractErrorMessage(data.error.apiKey, message), 'error');
                        }
                        throw new Error(message);
                    }

                    setStatus(signupStatus, 'Registrasi berhasil! Silakan login.', 'success');
                    freepikValidated = true;
                    if (signupApiKeyInput) signupApiKeyInput.value = '';
                    setDefaultApiKeyStatus();
                    refreshSignupControls();
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
                    signupSubmitting = false;
                    if (submitButton) submitButton.disabled = false;
                    refreshSignupControls();
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

$currentUser = auth_is_logged_in() ? (string)($_SESSION['auth_user'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dasboard – AI Hub + Filmmaker + UGC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="/logo.png">
  <style>

:root {
  font-family: 'Inter', 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color-scheme: light;
  --surface: linear-gradient(135deg, #eef2ff 0%, #f9fbff 55%, #ffffff 100%);
  --sidebar-bg: rgba(255, 255, 255, 0.95);
  --sidebar-border: rgba(214, 226, 245, 0.75);
  --card: #ffffff;
  --card-soft: #f6f8ff;
  --card-overlay: rgba(255, 255, 255, 0.96);
  --border: rgba(208, 217, 235, 0.7);
  --text: #0f172a;
  --muted: rgba(71, 85, 105, 0.72);
  --accent: #3b82f6;
  --accent-soft: rgba(59, 130, 246, 0.18);
  --danger: #ef4444;
  --success: #16a34a;
  --warning: #f59e0b;
  --shadow: rgba(15, 23, 42, 0.08);
  --halo-primary: rgba(79, 70, 229, 0.16);
  --halo-secondary: rgba(14, 165, 233, 0.12);
  --glass: rgba(255, 255, 255, 0.72);
  --input-bg: #ffffff;
  --input-border: rgba(203, 213, 225, 0.75);
  --sidebar-text-muted: rgba(100, 116, 139, 0.75);
  --stat-bg: rgba(59, 130, 246, 0.1);
  --mobile-nav-height: 64px;
}
body {
  margin: 0;
  background: var(--surface);
  color: var(--text);
  transition: background 0.35s ease, color 0.35s ease;
}

.app-view[hidden] {
  display: none !important;
}

button,
.drive-actions a {
  transition: background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease, transform 0.18s ease, color 0.25s ease;
}

button:hover:not(:disabled),
.drive-actions a:hover {
  transform: translateY(-1px);
}

button:active:not(:disabled) {
  transform: translateY(0);
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.has-mobile-bottom-nav {
  min-height: 100vh;
}

.mobile-bottom-nav {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1200;
  display: none;
  align-items: center;
  justify-content: space-between;
  padding: 6px 12px;
  padding-bottom: calc(6px + env(safe-area-inset-bottom, 0px));
  min-height: var(--mobile-nav-height);
  background: linear-gradient(135deg, rgba(79, 70, 229, 0.95) 0%, rgba(37, 99, 235, 0.95) 50%, rgba(14, 165, 233, 0.95) 100%);
  box-shadow: 0 -14px 28px rgba(15, 23, 42, 0.18);
  border-radius: 18px 18px 0 0;
}

.mobile-bottom-nav__item {
  flex: 1 1 0;
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: none;
  background: transparent;
  color: rgba(255, 255, 255, 0.82);
  font-size: 10px;
  font-weight: 600;
  text-transform: capitalize;
  letter-spacing: 0.01em;
  transition: transform 0.25s ease, color 0.25s ease;
  cursor: pointer;
  padding: 4px 0;
}

.mobile-bottom-nav__item:focus {
  outline: none;
}

.mobile-bottom-nav__item[disabled],
.mobile-bottom-nav__item.locked {
  opacity: 0.45;
  cursor: not-allowed;
}

.mobile-bottom-nav__icon {
  width: 32px;
  height: 32px;
  border-radius: 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.16);
  color: #ffffff;
  transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease;
}

.mobile-bottom-nav__icon svg {
  width: 18px;
  height: 18px;
  stroke: currentColor;
  stroke-width: 1.6;
  fill: none;
}

.mobile-bottom-nav__icon i {
  font-size: 16px;
}

.mobile-bottom-nav__label {
  display: block;
}

.mobile-bottom-nav__item.is-active,
.mobile-bottom-nav__item:hover,
.mobile-bottom-nav__item:focus-visible {
  color: #ffffff;
  transform: translateY(-2px);
}

.mobile-bottom-nav__item.is-active .mobile-bottom-nav__icon,
.mobile-bottom-nav__item:hover .mobile-bottom-nav__icon,
.mobile-bottom-nav__item:focus-visible .mobile-bottom-nav__icon {
  background: #ffffff;
  color: var(--accent);
  transform: scale(1.05);
}

@media (max-width: 960px) {
  body.has-mobile-bottom-nav {
    padding-bottom: calc(var(--mobile-nav-height) + env(safe-area-inset-bottom, 0px));
  }

  .mobile-bottom-nav {
    display: flex;
  }

  .workspace {
    padding-bottom: calc(var(--mobile-nav-height) + 16px);
  }

  .workspace-main {
    padding-bottom: calc(var(--mobile-nav-height) + 32px);
  }
}
body::before,
body::after {
  content: "";
  position: fixed;
  inset: -40vh -40vw;
  pointer-events: none;
  background: radial-gradient(circle at center, var(--halo-primary), transparent 60%);
  opacity: 0.35;
  filter: blur(160px);
  animation: orbitGlow 32s linear infinite;
  z-index: 0;
}
body::after {
  animation-duration: 42s;
  animation-direction: reverse;
  background: radial-gradient(circle at center, var(--halo-secondary), transparent 65%);
}
@keyframes orbitGlow {
  from { transform: rotate(0deg) scale(1.05); }
  50% { transform: rotate(180deg) scale(1.08); }
  to { transform: rotate(360deg) scale(1.05); }
}
body[data-theme="dark"] {
  color-scheme: dark;
  --surface: radial-gradient(circle at 15% 10%, rgba(99, 102, 241, 0.16), transparent 55%),
    radial-gradient(circle at 85% 20%, rgba(14, 165, 233, 0.18), transparent 60%), #030712;
  --sidebar-bg: rgba(10, 14, 24, 0.62);
  --sidebar-border: rgba(99, 102, 241, 0.32);
  --card: rgba(13, 20, 34, 0.64);
  --card-soft: rgba(17, 25, 41, 0.54);
  --card-overlay: rgba(8, 14, 26, 0.72);
  --border: rgba(99, 102, 241, 0.35);
  --text: #f8fafc;
  --muted: rgba(203, 213, 225, 0.75);
  --accent: #6366f1;
  --accent-soft: rgba(99, 102, 241, 0.3);
  --danger: #f87171;
  --success: #34d399;
  --warning: #facc15;
  --shadow: rgba(2, 6, 23, 0.45);
  --halo-primary: rgba(99, 102, 241, 0.28);
  --halo-secondary: rgba(14, 165, 233, 0.24);
  --glass: rgba(13, 20, 34, 0.58);
  --input-bg: rgba(9, 16, 28, 0.7);
  --input-border: rgba(138, 152, 181, 0.35);
  --sidebar-text-muted: rgba(191, 202, 222, 0.78);
  --stat-bg: rgba(99, 102, 241, 0.25);
}
body[data-theme="light"] {
  color-scheme: light;
  --surface: linear-gradient(135deg, #eef2ff 0%, #f7faff 60%, #ffffff 100%);
  --sidebar-bg: rgba(255, 255, 255, 0.97);
  --sidebar-border: rgba(214, 226, 245, 0.85);
  --card: #ffffff;
  --card-soft: #f7f9ff;
  --card-overlay: rgba(255, 255, 255, 0.97);
  --border: rgba(209, 220, 238, 0.75);
  --text: #0f172a;
  --muted: rgba(71, 85, 105, 0.68);
  --accent: #2563eb;
  --accent-soft: rgba(37, 99, 235, 0.18);
  --danger: #ef4444;
  --success: #16a34a;
  --warning: #f59e0b;
  --shadow: rgba(15, 23, 42, 0.08);
  --halo-primary: rgba(79, 70, 229, 0.18);
  --halo-secondary: rgba(16, 185, 129, 0.14);
  --glass: rgba(255, 255, 255, 0.78);
  --input-bg: #ffffff;
  --input-border: rgba(205, 214, 231, 0.85);
  --sidebar-text-muted: rgba(100, 116, 139, 0.7);
  --stat-bg: rgba(37, 99, 235, 0.1);
}
* { box-sizing: border-box; }
.workspace {
  display: flex;
  min-height: 100vh;
  position: relative;
  --sidebar-width: 260px;
  --sidebar-collapsed-width: 88px;
  --sidebar-current-width: var(--sidebar-width);
}
.workspace.sidebar-collapsed {
  --sidebar-current-width: var(--sidebar-collapsed-width);
}
.workspace-main {
  flex: 1;
  margin-left: var(--sidebar-current-width);
  padding: 36px 36px 40px;
  display: flex;
  flex-direction: column;
  gap: 28px;
  position: relative;
  z-index: 1;
  transition: margin-left 0.3s ease, padding 0.3s ease;
}
.overview-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 4px 0 8px;
}
.hero-text h1 {
  margin: 0 0 6px;
  font-size: 28px;
  font-weight: 700;
  color: var(--text);
}
.hero-text p {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}
.hero-actions {
  display: flex;
  gap: 12px;
}
.hero-actions .profile-topup {
  justify-self: auto;
  align-self: flex-start;
}

.restriction-banner {
  margin-bottom: 20px;
  padding: 14px 18px;
  border-radius: 18px;
  border: 1px solid rgba(250, 204, 21, 0.35);
  background: rgba(250, 204, 21, 0.12);
  color: var(--text);
  box-shadow: 0 16px 32px rgba(15, 23, 42, 0.18);
}

.restriction-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.restriction-icon {
  font-size: 20px;
  line-height: 1;
}

.mobile-coin-banner {
  display: none;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--card);
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
  position: sticky;
  top: 72px;
  z-index: 5;
}

.mobile-coin-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.mobile-coin-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
}

.mobile-coin-value {
  font-size: 17px;
  font-weight: 600;
  color: var(--text);
}

.mobile-coin-banner .topup-badge {
  width: auto;
  min-width: 100px;
  padding: 8px 14px;
  font-size: 12px;
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 18px;
}
.metric-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 16px;
}
.metric-card {
  border-radius: 18px;
  border: 1px solid var(--border);
  background: var(--card);
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  box-shadow: 0 16px 32px rgba(15, 23, 42, 0.16);
}
.metric-label {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
}
.metric-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--text);
}
.metric-meta {
  font-size: 11px;
  color: var(--muted);
}
.activity-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 20px;
}
.activity-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-radius: 16px;
  border: 1px dashed rgba(148, 163, 184, 0.25);
  background: rgba(15, 23, 42, 0.25);
  padding: 16px;
  max-height: 220px;
  overflow-y: auto;
}
.activity-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(30, 41, 59, 0.65);
  border: 1px solid rgba(99, 102, 241, 0.22);
}
.activity-item strong {
  font-size: 13px;
  color: var(--text);
}
.activity-item span {
  font-size: 12px;
  color: var(--muted);
}
.announcement-board {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.announcement-form {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}
.announcement-form .field-full {
  grid-column: 1 / -1;
}
.announcement-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}
.announcement-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.announcement-item {
  border-radius: 16px;
  border: 1px solid rgba(99, 102, 241, 0.18);
  background: rgba(15, 23, 42, 0.4);
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.announcement-item header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}
.announcement-item h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--text);
}
.announcement-item time {
  font-size: 12px;
  color: var(--muted);
}
.announcement-item p {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}
.announcement-item .actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}
.announcement-item .actions .btn {
  padding: 6px 12px;
  font-size: 12px;
}
.announcement-empty {
  border-radius: 14px;
  border: 1px dashed rgba(148, 163, 184, 0.25);
  background: rgba(15, 23, 42, 0.22);
  padding: 18px;
  font-size: 13px;
  color: var(--muted);
  text-align: center;
}
.dashboard-view {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.drive-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.drive-header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}
.drive-header h1 {
  margin: 0;
  font-size: 26px;
  font-weight: 700;
  color: var(--text);
}
.drive-header p {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--muted);
}
.drive-meta {
  display: flex;
  gap: 12px;
  align-items: center;
  color: var(--muted);
  font-size: 12px;
}
.drive-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 12px 16px;
  align-items: flex-end;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
}
.drive-filter {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 140px;
}
.drive-filter label {
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}
.drive-filter select,
.drive-filter input {
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: 10px;
  padding: 8px 10px;
  color: var(--text);
  min-height: 36px;
}
.drive-content {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.drive-empty {
  padding: 48px 24px;
  border: 1px dashed var(--border);
  border-radius: 16px;
  text-align: center;
  font-size: 13px;
  color: var(--muted);
  background: var(--card-soft);
}
.drive-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
}
@media (min-width: 1280px) {
  .drive-grid {
    grid-template-columns: repeat(6, minmax(0, 1fr));
  }
}
.drive-card {
  background: var(--card);
  border: 1px solid rgba(71, 85, 105, 0.5);
  border-radius: 16px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
  transition: transform 0.2s ease, border-color 0.2s ease;
}
.drive-card:hover {
  transform: translateY(-2px);
  border-color: rgba(99, 102, 241, 0.6);
}
.drive-thumb {
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.4);
  aspect-ratio: 4 / 5;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: zoom-in;
}
.drive-thumb img,
.drive-thumb video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.drive-thumb video {
  pointer-events: none;
}
.drive-thumb[data-type="video"]::after {
  content: '▶';
  position: absolute;
  right: 10px;
  bottom: 10px;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.75);
  color: #fff;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.35);
  z-index: 6;
}
.drive-type-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  background: rgba(15, 23, 42, 0.76);
  color: #fff;
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 4px 8px;
  border-radius: 999px;
  box-shadow: 0 10px 20px rgba(15, 23, 42, 0.35);
  z-index: 6;
}
.drive-card-footer {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.drive-card-footer strong {
  font-size: 12px;
  color: var(--text);
}
.drive-card-footer span {
  font-size: 11px;
  color: var(--muted);
}
.drive-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}
.drive-actions button,
.drive-actions a {
  flex: 1 1 calc(50% - 8px);
  min-width: 120px;
  border-radius: 10px;
  border: 1px solid rgba(99, 102, 241, 0.4);
  background: rgba(99, 102, 241, 0.12);
  color: var(--text);
  padding: 6px 8px;
  font-size: 11px;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease;
}
.drive-actions button:hover,
.drive-actions a:hover {
  background: rgba(99, 102, 241, 0.2);
  border-color: rgba(129, 140, 248, 0.7);
}
.drive-actions .danger {
  background: rgba(239, 68, 68, 0.12);
  border-color: rgba(239, 68, 68, 0.45);
  color: var(--danger);
}
.drive-actions .danger:hover {
  background: rgba(239, 68, 68, 0.22);
  border-color: rgba(239, 68, 68, 0.65);
}
.drive-clear-date {
  margin-left: auto;
}
@media (max-width: 720px) {
  .drive-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .drive-meta {
    width: 100%;
  }
  .drive-filter {
    min-width: 100%;
  }
  .drive-filters {
    align-items: stretch;
  }
}

.stat-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 16px 18px;
  box-shadow: 0 16px 36px rgba(5, 10, 22, 0.35);
  display: flex;
  flex-direction: column;
  gap: 6px;
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}
.stat-label {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
}
.stat-value {
  font-size: 26px;
  font-weight: 700;
  color: var(--text);
}
.stat-meta {
  font-size: 11px;
  color: var(--muted);
}


.account-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.account-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 26px 28px;
  border-radius: 22px;
  border: 1px solid var(--border);
  background: var(--card);
  box-shadow: 0 22px 46px rgba(4, 9, 20, 0.38);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  gap: 18px;
}

.account-hero h1 {
  margin: 0 0 6px;
  font-size: 26px;
  font-weight: 700;
  color: var(--text);
}

.account-hero p {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.account-settings {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.account-settings-card {
  border-radius: 22px;
  border: 1px solid var(--border);
  background: var(--card);
  box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
  padding: 22px 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.account-settings-card .header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.account-settings-card .title {
  font-size: 18px;
  font-weight: 600;
  color: var(--text);
}

.account-settings-card .subtitle {
  font-size: 12px;
  color: var(--muted);
}

.account-settings-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 24px;
  align-items: flex-start;
}

.account-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px 20px;
  border-radius: 18px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  background: var(--card-soft);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
}

.account-form-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--text);
}

.avatar-row {
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: 16px;
  align-items: center;
}

.avatar-preview {
  width: 100%;
  aspect-ratio: 1 / 1;
  border-radius: 20px;
  border: 1px dashed rgba(148, 163, 184, 0.55);
  background: var(--card);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  position: relative;
}

.avatar-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: none;
}

.avatar-preview .avatar-initials {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 20px;
  color: var(--muted);
  letter-spacing: 0.08em;
}

.avatar-fields {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.account-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--muted);
}

.account-form input[type="url"],
.account-form input[type="password"],
.account-form input[type="text"] {
  border-radius: 12px;
  border: 1px solid var(--input-border);
  background: var(--input-bg);
  padding: 10px 12px;
  font-size: 13px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.account-form input:focus {
  outline: none;
  border-color: rgba(59, 130, 246, 0.6);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.account-form-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.account-form-hint {
  margin: 0;
  font-size: 11px;
  color: var(--muted);
}

.account-save-btn {
  align-self: flex-start;
  padding: 8px 16px;
  font-size: 12px;
  border-radius: 999px;
  border: 1px solid rgba(59, 130, 246, 0.6);
  background: linear-gradient(120deg, rgba(59, 130, 246, 0.16), rgba(14, 165, 233, 0.18));
  color: var(--text);
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.account-save-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 26px rgba(59, 130, 246, 0.18);
}

.account-form-status {
  display: none;
  border-radius: 12px;
  padding: 8px 12px;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.5;
  background: rgba(148, 163, 184, 0.16);
  color: var(--muted);
}

.account-form-status.ok {
  background: rgba(22, 163, 74, 0.12);
  color: var(--success);
}

.account-form-status.err {
  background: rgba(239, 68, 68, 0.12);
  color: var(--danger);
}

.account-form-status.progress {
  background: rgba(59, 130, 246, 0.14);
  color: var(--accent);
}

.account-form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

.account-form-grid input {
  width: 100%;
}
.sidebar-toggle {
  position: fixed;
  top: 20px;
  left: calc(var(--sidebar-current-width) + 16px);
  min-width: 44px;
  height: 44px;
  padding: 0 16px;
  border-radius: 14px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.05em;
  cursor: pointer;
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
  z-index: 40;
}
.sidebar-toggle:hover {
  transform: translateY(-2px);
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.16);
}
.sidebar-toggle svg {
  width: 22px;
  height: 22px;
  stroke: currentColor;
  stroke-width: 1.8;
  fill: none;
  transition: transform 0.3s ease;
}
.sidebar-toggle-label {
  display: none;
}
.workspace.sidebar-collapsed .sidebar-toggle svg {
  transform: rotate(180deg);
}
.sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  width: var(--sidebar-current-width);
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 28px 24px 32px;
  background: var(--sidebar-bg);
  border-right: 1px solid var(--sidebar-border);
  backdrop-filter: blur(18px);
  z-index: 30;
  box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
  transition: width 0.3s ease, padding 0.3s ease, transform 0.3s ease;
}
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.38);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  z-index: 25;
  display: none;
}
.workspace.sidebar-open .sidebar-overlay {
  opacity: 1;
  pointer-events: auto;
}
.sidebar-brand {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.sidebar-title {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: var(--text);
}
.sidebar-sub {
  font-size: 12px;
  color: var(--sidebar-text-muted);
}
.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sidebar-section {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--sidebar-text-muted);
  padding: 4px 14px;
  margin: 10px 0 -2px;
}

.sidebar-link {
  border-radius: 16px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--sidebar-text-muted);
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: background 0.2s ease, border 0.2s ease, color 0.2s ease, transform 0.2s ease;
}
.sidebar-link .nav-icon {
  width: 20px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: inherit;
}
.sidebar-link .nav-icon svg {
  width: 18px;
  height: 18px;
  stroke: currentColor;
  stroke-width: 1.6;
  fill: none;
}
.sidebar-link {
  text-align: left;
}
.sidebar-link .dot {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}
.sidebar-link .nav-label {
  flex: 1;
  text-align: left;
}
.sidebar-link .tag {
  margin-left: auto;
  padding: 4px 8px;
  border-radius: 999px;
  background: var(--accent-soft);
  color: var(--accent);
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.sidebar-link.sidebar-link--external {
  text-decoration: none;
}
.sidebar-link.sidebar-link--external:hover {
  color: var(--text);
}
.sidebar-link:hover {
  transform: translateX(4px);
  background: rgba(37, 99, 235, 0.08);
  color: var(--text);
}
.sidebar-link.active {
  background: linear-gradient(135deg, rgba(37, 99, 235, 0.22), rgba(14, 165, 233, 0.18));
  border-color: rgba(37, 99, 235, 0.35);
  color: var(--text);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
}
.sidebar-link.locked {
  opacity: 0.5;
  cursor: not-allowed;
}
.sidebar-link.locked .nav-label::after {
  content: '🔒';
  margin-left: 8px;
  font-size: 12px;
  color: rgba(248, 113, 113, 0.9);
}
.workspace.sidebar-collapsed .sidebar {
  padding: 28px 16px;
}
.workspace.sidebar-collapsed .sidebar-brand,
.workspace.sidebar-collapsed .sidebar-sub {
  display: none;
}
.workspace.sidebar-collapsed .sidebar-section {
  display: none;
}
.workspace.sidebar-collapsed .sidebar-nav {
  align-items: center;
}
.workspace.sidebar-collapsed .sidebar-link {
  justify-content: center;
  padding: 12px 10px;
}
.workspace.sidebar-collapsed .sidebar-link .nav-label,
.workspace.sidebar-collapsed .sidebar-link .dot {
  display: none;
}
.workspace.sidebar-collapsed .sidebar-actions {
  align-items: center;
}
.workspace.sidebar-collapsed .profile-card,
.workspace.sidebar-collapsed .sidebar-sub,
.workspace.sidebar-collapsed .sidebar-title {
  display: none;
}
.workspace.sidebar-collapsed .logout-label {
  display: none;
}
.workspace.sidebar-collapsed .logout-btn {
  padding: 10px;
  width: 44px;
  justify-content: center;
}
.workspace.sidebar-collapsed .logout-btn svg {
  margin: 0;
}
.sidebar-actions {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.theme-toggle {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  border: 1px solid var(--border);
  background: var(--card-soft);
  color: var(--text);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
}
.theme-toggle:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 28px rgba(15, 23, 42, 0.14);
}
.theme-toggle.loading {
  opacity: 0.6;
  pointer-events: none;
}
.profile-card {
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--card);
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 18px 34px rgba(3, 7, 18, 0.35);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.profile-card::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(120deg, rgba(59, 130, 246, 0.14), rgba(14, 165, 233, 0.08));
  opacity: 0.6;
  pointer-events: none;
}
.profile-card--mobile {
  display: none;
}
body[data-theme="light"] .profile-card {
  background: rgba(255, 255, 255, 0.96);
  border-color: rgba(148, 163, 184, 0.32);
  box-shadow: 0 12px 22px rgba(148, 163, 184, 0.22);
}
.profile-card--alert {
  border-color: rgba(248, 113, 113, 0.45);
  box-shadow: 0 18px 38px rgba(248, 113, 113, 0.12);
}
.profile-main {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  z-index: 1;
}
.profile-avatar {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.85), rgba(96, 165, 250, 0.65));
  color: #f8fafc;
  font-weight: 700;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.45);
}
.profile-avatar.profile-avatar--image {
  background-size: cover;
  background-position: center;
  color: transparent;
  text-indent: -9999px;
}
body[data-theme="light"] .profile-avatar {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.9), rgba(37, 99, 235, 0.75));
}
.profile-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.profile-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
}
.profile-badge {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 2px 12px 2px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text);
  background: rgba(148, 163, 184, 0.16);
  box-shadow: none;
  overflow: hidden;
  transition: background 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
}

.profile-badge::before {
  content: "\1F451"; /* Ikon Mahkota */
  font-size: 12px;
}

.profile-badge::after {
  content: "";
  position: absolute;
  top: 0;
  left: -60%;
  width: 50%;
  height: 100%;
  background: linear-gradient(120deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.65), rgba(255, 255, 255, 0));
  opacity: 0;
}

@keyframes badgeShine {
  0% { left: -60%; }
  60% { left: 120%; }
  100% { left: 120%; }
}

.profile-badge--premium {
  color: #583f08;
  background: linear-gradient(90deg, #ffd700, #ffec85);
  box-shadow: 0 6px 16px rgba(255, 215, 0, 0.5);
}

.profile-badge--premium::after {
  opacity: 1;
  animation: badgeShine 2.8s linear infinite;
}

.profile-badge--free {
  color: #111827;
  background: linear-gradient(135deg, #e2e8f0, #cbd5f5);
  box-shadow: none;
}

.profile-badge--free::after {
  opacity: 0;
  animation: none;
}

.profile-badge--free::before {
  filter: grayscale(0.4);
}

.topup-badge {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 2px 12px 2px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  
  /* --- WARNA PINK --- */
  color: #fce7f3; /* Teks Pink Sangat Muda */
  background: linear-gradient(90deg, #db2777, #f472b6); /* Gradasi Pink Tua ke Pink Muda */
  box-shadow: 0 6px 16px rgba(236, 72, 153, 0.35); /* Bayangan Pink */
  /* --- AKHIR WARNA PINK --- */
  
  overflow: hidden;
}

.topup-badge::before {
  content: "\1F451"; /* Ikon Mahkota */
  font-size: 12px;
}

.topup-badge::after {
  content: "";
  position: absolute;
  top: 0;
  left: -60%;
  width: 50%;
  height: 100%;
  
  /* Efek Shine (Kilau) */
  background: linear-gradient(120deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0));
  animation: badgeShine 2.8s linear infinite;
}

@keyframes badgeShine {
  0% { left: -60%; }
  60% { left: 120%; }
  100% { left: 120%; }
}

.verified-badge-star {
  position: relative;
  display: inline-flex;
  justify-content: center;
  align-items: center;

  width: 28px; /* Ukuran badge */
  height: 28px; /* Ukuran badge */

  /* --- Bentuk Bintang Bergerigi (Custom Clip-Path) --- */
  /* Ini adalah perkiraan untuk bentuk bintang bergerigi seperti gambar */
  clip-path: polygon(
    50% 0%, 65% 15%, 100% 19%, 85% 35%, 100% 61%, 75% 65%, 70% 100%, 50% 85%, 30% 100%, 25% 65%, 0% 61%, 15% 35%, 0% 19%, 35% 15%
  );

  /* --- WARNA BIRU BERKILAU --- */
  color: #f0f8ff; /* Warna ikon centang: Biru sangat muda/putih */
  background: linear-gradient(90deg, #1e3a8a, #3b82f6); /* Gradasi Biru Tua ke Biru Terang */
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.5); /* Bayangan Biru */
  /* --- AKHIR WARNA BIRU BERKILAU --- */

  overflow: hidden; /* Penting untuk efek kilau */
  transition: transform 0.3s ease, opacity 0.2s ease;
}

.verified-badge-star::before {
  /* Ikon centang (simbol Unicode) */
  content: "\2713"; /* Centang standar. Gunakan \2714 untuk centang tebal */
  font-size: 16px; /* Ukuran ikon centang */
  line-height: 1; 
  z-index: 2; /* Pastikan ikon di atas kilauan */
}

.verified-badge-star::after {
  content: "";
  position: absolute;
  top: 0;
  left: -100%;
  width: 70%;
  height: 100%;

  /* Efek Kilau/Shine */
  background: linear-gradient(120deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0));
  animation: badgeShineSweep 2.5s linear infinite;
  z-index: 1; /* Di bawah ikon centang, di atas background badge */
}

.verified-badge-star--hidden {
  opacity: 0;
  transform: scale(0.6);
  pointer-events: none;
}

/* Keyframes untuk Animasi Kilauan */
@keyframes badgeShineSweep {
  0% { left: -100%; }
  60% { left: 150%; }
  100% { left: 150%; }
}

.profile-username {
  font-size: 11px;
  color: var(--muted);
}
.profile-credit {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.65);
  border: 1px solid rgba(59, 130, 246, 0.25);
  position: relative;
  z-index: 1;
}
.profile-expiry {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(15, 23, 42, 0.4);
  border: 1px solid rgba(148, 163, 184, 0.25);
  border-radius: 10px;
  padding: 8px 12px;
  font-size: 11px;
  color: rgba(226, 232, 240, 0.85);
  margin-top: 6px;
  gap: 12px;
}

.profile-expiry--active {
  animation: profileExpiryPulse 3s ease-in-out infinite;
  box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.25);
}
.profile-expiry--inline {
  margin-top: 6px;
  background: rgba(15, 23, 42, 0.2);
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 10px;
}
.profile-expiry-label {
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.profile-expiry-value {
  font-weight: 500;
}
.profile-expiry.expired {
  border-color: rgba(248,113,113,0.45);
  color: rgba(248,113,113,0.85);
}
.profile-expiry.expired.profile-expiry--active {
  animation: none;
  box-shadow: none;
}
.profile-expiry.expired .profile-expiry-label {
  color: inherit;
}

@keyframes profileExpiryPulse {
  0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.25); }
  70% { box-shadow: 0 0 0 12px rgba(59, 130, 246, 0); }
  100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
}
.profile-card--mobile .profile-expiry {
  margin-top: 4px;
}
body[data-theme="light"] .profile-credit {
  background: rgba(240, 249, 255, 0.9);
  border-color: rgba(59, 130, 246, 0.35);
}
body[data-theme="light"] .profile-expiry {
  background: rgba(226, 232, 240, 0.55);
  border-color: rgba(148, 163, 184, 0.32);
  color: rgba(30, 41, 59, 0.8);
}
body[data-theme="light"] .profile-expiry.expired {
  background: rgba(254, 226, 226, 0.7);
  border-color: rgba(248, 113, 113, 0.55);
  color: rgba(153, 27, 27, 0.85);
}
.profile-credit .credit-label {
  font-size: 11px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--muted);
}
.profile-credit .credit-value {
  font-size: 16px;
  font-weight: 600;
  color: var(--text);
}
.profile-credit .credit-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--muted);
}
.profile-credit .status-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: var(--success);
  box-shadow: 0 0 0 2px rgba(52, 211, 153, 0.25);
  transition: background 0.2s ease, box-shadow 0.2s ease;
}
.profile-credit .status-dot.offline {
  background: var(--danger);
  box-shadow: 0 0 0 2px rgba(248, 113, 113, 0.35);
}
.profile-topup {
  width: 100%;
  border: none;
  outline: none;
  border-radius: 10px;
  
  /* --- GRADASI PINK BERKILAU --- */
  background: linear-gradient(120deg, rgba(236, 72, 153, 0.95), rgba(244, 114, 182, 0.85)); /* Pink Tua ke Pink Muda */
  color: #ffffff; /* Teks Putih agar kontras */
  font-size: 12px;
  font-weight: 600;
  padding: 8px 12px;
  cursor: pointer;
  box-shadow: 0 14px 28px rgba(236, 72, 153, 0.35); /* Bayangan Pink Tua */
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  text-decoration: none;
  white-space: nowrap;
}

.profile-topup:hover {
  transform: translateY(-1px);
  /* --- BOX-SHADOW HOVER (LEBIH MENYALA) --- */
  box-shadow: 0 16px 30px rgba(236, 72, 153, 0.5); /* Bayangan Pink yang lebih kuat saat hover */
}

/* --- KONDISI ALERT (Jika Diperlukan) --- */
.profile-card--alert .profile-topup {
  /* Menggunakan gradasi Pink/Ungu yang berbeda untuk status alert */
  background: linear-gradient(120deg, rgba(147, 51, 234, 0.95), rgba(192, 132, 252, 0.8));
}
.logout-btn {
  border-radius: 12px;
  border: 1px solid rgba(203, 213, 225, 0.7);
  background: var(--card);
  color: var(--text);
  font-size: 12px;
  padding: 10px 16px;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.logout-btn:hover {
  transform: translateY(-2px);
  border-color: rgba(37, 99, 235, 0.45);
  box-shadow: 0 18px 36px rgba(15, 23, 42, 0.16);
}
.logout-btn svg {
  width: 18px;
  height: 18px;
  stroke: currentColor;
  stroke-width: 1.6;
  fill: none;
}
@media (max-width: 960px) {
  .workspace {
    --sidebar-current-width: 0px;
  }
  .sidebar-toggle {
    top: 16px;
    left: 16px;
    min-width: 60px;
    height: 52px;
    border-radius: 18px;
    box-shadow: 0 24px 46px rgba(15, 23, 42, 0.18);
    font-size: 12px;
  }
  .sidebar-toggle svg {
    width: 20px;
    height: 20px;
  }
  .sidebar-toggle-label {
    display: inline;
  }
  .sidebar {
    width: min(320px, calc(100vw - 32px));
    height: 100vh;
    transform: translateX(-100%);
    border-right: none;
    border-bottom: none;
    padding: 28px 22px 60px;
    display: flex;
    flex-direction: column;
    gap: 24px;
    overflow-y: auto;
    box-shadow: 0 28px 68px rgba(15, 23, 42, 0.35);
    background: var(--sidebar-bg);
    border-radius: 0 24px 24px 0;
  }
  .workspace.sidebar-open .sidebar {
    transform: translateX(0);
  }
  .sidebar-overlay {
    display: block;
  }
  .sidebar-brand {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
  .sidebar-title {
    font-size: 20px;
  }
  .sidebar-nav {
    flex-direction: column;
    gap: 12px;
  }
  .sidebar-link {
    width: 100%;
    justify-content: flex-start;
    padding: 14px 16px;
    border-radius: 18px;
  }
  .sidebar-actions {
    flex-direction: column;
    align-items: stretch;
    gap: 14px;
  }
  .sidebar-actions > * {
    width: 100%;
  }
  .theme-toggle {
    align-self: flex-start;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .profile-card {
    display: none;
  }
  .profile-card--mobile {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px 22px;
    border-radius: 24px;
    border: 1px solid var(--border);
    background: var(--card);
    box-shadow: 0 20px 40px rgba(4, 9, 20, 0.32);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }
  .profile-card--mobile .profile-main {
    align-items: center;
  }
  .profile-card--mobile .profile-avatar {
    width: 48px;
    height: 48px;
    font-size: 18px;
  }
  .mobile-coin-banner {
    display: flex;
  }
  .account-view {
    gap: 20px;
  }
  .account-hero {
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    padding: 22px 24px;
  }
  .account-settings-grid {
    grid-template-columns: 1fr;
  }
  .avatar-row {
    grid-template-columns: 1fr;
  }
  .avatar-preview {
    margin: 0 auto;
    max-width: 140px;
  }
  .account-save-btn {
    width: 100%;
    text-align: center;
    justify-content: center;
    align-self: stretch;
  }
  .profile-card--mobile {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px 22px;
    border-radius: 24px;
    border: 1px solid var(--border);
    background: var(--card);
    box-shadow: 0 20px 40px rgba(4, 9, 20, 0.32);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }
  .profile-card--mobile .profile-main {
    align-items: center;
  }
  .profile-card--mobile .profile-avatar {
    width: 48px;
    height: 48px;
    font-size: 18px;
  }
  .mobile-coin-banner {
    display: flex;
  }
  .account-settings-grid {
    grid-template-columns: 1fr;
  }
  .avatar-row {
    grid-template-columns: 1fr;
  }
  .avatar-preview {
    margin: 0 auto;
    max-width: 140px;
  }
  .account-save-btn {
    width: 100%;
    text-align: center;
    justify-content: center;
    align-self: stretch;
  }
  .logout-btn {
    justify-content: center;
  }
  .workspace-main {
    margin-left: 0;
    padding: 88px 20px 32px;
    gap: 24px;
  }
  .overview-hero {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    padding: 20px 18px;
    border-radius: 22px;
    background: var(--card);
    border: 1px solid var(--border);
    box-shadow: 0 18px 46px rgba(15, 23, 42, 0.12);
  }
  .hero-text h1 {
    font-size: 24px;
  }
  .hero-text p {
    font-size: 13px;
    line-height: 1.5;
  }
  .hero-actions {
    width: 100%;
  }
  .hero-actions .profile-topup {
    width: 100%;
    justify-content: center;
  }
  .stats-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .stat-card {
    padding: 18px 20px;
    border-radius: 22px;
    box-shadow: 0 18px 44px rgba(15, 23, 42, 0.1);
  }
  .hub-app {
    gap: 18px;
    grid-template-columns: 1fr;
  }
  .gemini-grid {
    grid-template-columns: 1fr;
  }
  .hub-column,
  .hub-side {
    gap: 18px;
  }
  .card,
  .card-soft {
    padding: 18px;
    border-radius: 20px;
  }
  .feature-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .feature-tab {
    width: 100%;
  }
  .select-group {
    margin-bottom: 14px;
  }
  .two-col {
    grid-template-columns: 1fr;
    gap: 14px;
  }
  .field-row {
    flex-direction: column;
  }
  .field-row > div {
    width: 100%;
  }
  .btn-group {
    flex-direction: column;
    align-items: stretch;
  }
  .btn-group > * {
    width: 100%;
  }
  .status-bar {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  .status-text {
    flex: initial;
  }
  .status-progress {
    width: 100%;
  }
  .preview-grid {
    display: grid;
    grid-template-columns: 1fr;
  }
  .preview-item {
    width: 100%;
  }
  .jobs-col {
    gap: 16px;
  }
  .jobs-list {
    max-height: none;
  }
  .film-app,
  .ugc-app {
    padding: 0;
    gap: 16px;
    min-height: 0;
  }
  .film-scenes-board {
    padding: 16px;
  }
  .film-scenes-container {
    grid-template-columns: 1fr;
  }
  .ugc-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 720px) {
  .workspace-main {
    padding: 84px 16px 28px;
    gap: 22px;
  }
  .sidebar-toggle {
    top: 14px;
    left: 14px;
  }
  .sidebar {
    width: min(300px, calc(100vw - 28px));
    padding: 24px 18px 54px;
  }
  .hero-text h1 {
    font-size: 22px;
  }
  .hero-actions .profile-topup {
    font-size: 13px;
    padding: 10px 16px;
  }
  .stat-value {
    font-size: 24px;
  }
  .feature-tabs {
    grid-template-columns: 1fr;
  }
  .hub-app {
    gap: 16px;
  }
  .hub-column,
  .hub-side {
    gap: 16px;
  }
  .status-pill {
    width: 100%;
    text-align: center;
  }
  .preview-btn-group {
    flex-direction: column;
    align-items: stretch;
  }
  .preview-btn-group > * {
    width: 100%;
  }
  .ugc-video-actions {
    justify-content: flex-start;
    gap: 10px;
  }
  .ugc-download-btn,
  .ugc-generate-btn {
    width: 100%;
  }
  .film-slider-row {
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
  }
  .film-aspect-toggle {
    flex-wrap: wrap;
    gap: 8px;
  }
  .film-aspect-btn {
    flex: 1 1 calc(50% - 8px);
  }
  .ugc-product-preview img,
  .ugc-product-preview div {
    width: 56px;
    height: 56px;
  }
}

    .hub-app {
      display: grid;
      grid-template-columns: minmax(0, 1.65fr) minmax(0, 1fr);
      gap: 24px;
      align-items: flex-start;
    }
    .hub-column {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .hub-side {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .hub-model-card {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .hub-model-card .select-group {
      margin: 0;
    }
    .hub-form-card form {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }
    .two-col {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 18px;
    }

    @media (max-width: 1200px) {
      .hub-app {
        grid-template-columns: 1fr;
      }
      .hub-side {
        order: 3;
      }
      .gemini-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    .card {
      background: var(--card);
      border-radius: 18px;
      border: 1px solid var(--border);
      padding: 20px 24px;
      box-shadow: 0 22px 46px rgba(6, 11, 25, 0.4);
      position: relative;
      overflow: hidden;
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }
    .card::before { display: none; }
    .card:hover {
      transform: translateY(-3px);
      box-shadow: 0 28px 56px rgba(15, 23, 42, 0.15);
    }
    .card > * { position: relative; z-index: 1; }

    .card-soft {
      background: var(--card-soft);
      border-radius: 18px;
      border: 1px solid var(--border);
      padding: 18px 20px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 18px 38px rgba(4, 8, 20, 0.35);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
    }
    .card-soft::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(14,165,233,0.08));
      opacity: 0;
      transition: opacity 0.3s ease;
      z-index: 0;
    }
    .card-soft:hover::before {
      opacity: 1;
    }
    .card-soft > * { position: relative; z-index: 1; }
    .gemini-view {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    .gemini-hero {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
    }
    .gemini-hero h1 {
      margin: 0 0 6px;
      font-size: 26px;
      font-weight: 700;
      color: var(--text);
    }
    .gemini-hero p {
      margin: 0;
      font-size: 13px;
      color: var(--muted);
    }
    .gemini-hero-badges {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    .gemini-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 999px;
      border: 1px solid rgba(99,102,241,0.38);
      background: rgba(99,102,241,0.16);
      color: #dbeafe;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .gemini-badge--accent {
      border-color: rgba(14,165,233,0.45);
      background: rgba(14,165,233,0.18);
      color: #cffafe;
    }
    .gemini-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 20px;
      align-items: stretch;
    }
    .gemini-card__header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 8px;
    }
    .gemini-card__header h2 {
      margin: 0;
      font-size: 18px;
      font-weight: 600;
      color: var(--text);
    }
    .gemini-card__header p {
      margin: 0;
      font-size: 12px;
      color: var(--muted);
    }
    .gemini-form {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .gemini-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .gemini-field label {
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .gemini-field-row {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }
    .gemini-actions {
      display: flex;
      gap: 10px;
      justify-content: flex-start;
      flex-wrap: wrap;
    }
    .gemini-output-wrapper {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: 10px;
    }
    .gemini-output {
      width: 100%;
      min-height: 150px;
      border-radius: 16px;
      border: 1px solid var(--border);
      background: var(--card-overlay);
      color: var(--text);
      padding: 12px 14px;
      font-family: 'Inter', sans-serif;
      font-size: 13px;
      line-height: 1.6;
      resize: vertical;
    }
    .gemini-output-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    .gemini-audio-output {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 12px;
    }
    .gemini-audio-output audio {
      width: 100%;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: var(--card-overlay);
      padding: 10px;
    }
    .gemini-video-results {
      display: flex;
      flex-direction: column;
      gap: 14px;
      margin-top: 12px;
    }
    .gemini-video-item {
      display: flex;
      flex-direction: column;
      gap: 10px;
      padding: 4px;
      border-radius: 20px;
      background: linear-gradient(135deg, rgba(30, 41, 59, 0.55), rgba(15, 23, 42, 0.35));
      border: 1px solid rgba(148, 163, 184, 0.12);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
    }
    .gemini-video-results video {
      width: 100%;
      border-radius: 18px;
      border: 1px solid var(--border);
      background: var(--card-overlay);
      box-shadow: 0 16px 34px rgba(4, 9, 20, 0.35);
    }
    .gemini-video-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 10px 14px;
      border-radius: 14px;
      background: rgba(15, 23, 42, 0.55);
      border: 1px solid rgba(148, 163, 184, 0.18);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
    }
    .gemini-video-actions .download-link {
      margin-left: auto;
    }
    .gemini-placeholder {
      font-size: 12px;
      color: var(--muted);
      background: rgba(148, 163, 184, 0.1);
      border: 1px dashed rgba(148, 163, 184, 0.35);
      border-radius: 18px;
      padding: 16px;
      text-align: center;
    }
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 10px;
    }
    .title {
      font-size: 18px;
      font-weight: 600;
      letter-spacing: 0.02em;
    }
    .subtitle {
      font-size: 12px;
      color: var(--muted);
    }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      border-radius: 999px;
      padding: 4px 10px;
      border: 1px solid rgba(148, 163, 184, 0.35);
      background: linear-gradient(135deg, rgba(37,99,235,0.16), rgba(14,165,233,0.1));
      color: var(--muted);
    }
    .dot-large {
      width: 9px;
      height: 9px;
      border-radius: 999px;
      background: var(--success);
      box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.25);
    }

    label {
      display: block;
      font-size: 12px;
      margin-bottom: 4px;
      color: var(--muted);
    }
    input[type="text"],
    input[type="number"],
    textarea,
    select {
      width: 100%;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--text);
      font-size: 13px;
      padding: 7px 9px;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    }
    textarea {
      min-height: 90px;
      resize: vertical;
    }
    input:focus,
    textarea:focus,
    select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.45);
      background: var(--card);
    }
    .field-row {
      display: flex;
      gap: 8px;
    }
    .field-row > div { flex: 1; }

    button {
      border-radius: 999px;
      border: none;
      background: linear-gradient(135deg, #6366f1, #22d3ee, #a855f7);
      background-size: 200% 200%;
      color: white;
      font-size: 13px;
      font-weight: 500;
      padding: 8px 16px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: transform 0.12s ease-out, box-shadow 0.12s ease-out, filter 0.12s ease-out;
      box-shadow: 0 12px 30px rgba(79, 70, 229, 0.65);
      white-space: nowrap;
      animation: gradientShift 6s ease infinite;
    }
    button.secondary {
      background: transparent;
      border-radius: 999px;
      border: 1px solid var(--border);
      box-shadow: none;
      padding-inline: 12px;
      animation: none;
    }
    button.small {
      padding: 4px 10px;
      font-size: 11px;
      box-shadow: none;
    }
    button:active {
      transform: translateY(1px);
      box-shadow: 0 8px 20px rgba(79, 70, 229, 0.55);
      filter: brightness(0.96);
    }
    button:disabled {
      opacity: 0.55;
      cursor: default;
      box-shadow: none;
      transform: none;
      animation: none;
    }
    .btn-group {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 8px;
    }

    .select-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 10px;
    }
    select { height: 34px; }
    .model-group-label {
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.09em;
      margin-bottom: 4px;
    }

    .feature-tabs {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }
    .feature-tab {
      flex: 1 1 0;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: var(--card-soft);
      color: var(--muted);
      font-size: 11px;
      padding: 6px 10px;
      cursor: pointer;
      text-align: center;
      transition: all 0.25s ease;
      position: relative;
      overflow: hidden;
    }
    .feature-tab.locked {
      opacity: 0.5;
      cursor: not-allowed;
      border-style: dashed;
    }
    .feature-tab.active {
      background: linear-gradient(135deg, rgba(37,99,235,0.25), rgba(14,165,233,0.2));
      border-color: rgba(37,99,235,0.4);
      color: var(--text);
      box-shadow: 0 16px 32px rgba(37,99,235,0.18);
    }
    .feature-tab::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, rgba(99,102,241,0.2), transparent 55%);
      opacity: 0;
      transition: opacity 0.25s ease;
    }
    .feature-tab:hover::after {
      opacity: 1;
    }

    .status-bar {
      margin-top: 10px;
      font-size: 12px;
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      gap: 10px;
    }
    .status-text {
      color: var(--muted);
      flex: 1 1 180px;
      transition: opacity 0.3s ease, color 0.3s ease;
    }
    .status-text.flash { animation: pulseStatus 0.6s ease; }
    @keyframes pulseStatus {
      0% { opacity: 0.4; }
      50% { opacity: 1; }
      100% { opacity: 0.7; }
    }
    .status-pill {
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 11px;
      border: 1px solid var(--border);
      background: rgba(2, 6, 23, 0.65);
      box-shadow: inset 0 0 0 1px rgba(148,163,184,0.06);
    }
    .status-pill.ok {
      border-color: rgba(74, 222, 128, 0.5);
      color: var(--success);
    }
    .status-pill.err {
      border-color: rgba(248, 113, 113, 0.7);
      color: var(--danger);
    }
    .status-progress {
      flex: 1 1 220px;
      display: none;
      align-items: center;
      gap: 10px;
    }
    .status-progress.active { display: flex; }
    .status-progress-label {
      font-size: 11px;
      color: var(--muted);
      min-width: 48px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .status-progress-label span {
      color: var(--text);
      font-weight: 600;
    }
    .progress-track {
      flex: 1;
      height: 5px;
      border-radius: 999px;
      background: rgba(148, 163, 184, 0.18);
      overflow: hidden;
      position: relative;
    }
    .progress-fill {
      position: absolute;
      inset: 0;
      width: 0%;
      border-radius: 999px;
      background: linear-gradient(120deg, rgba(99,102,241,0.9), rgba(45,212,191,0.9));
      box-shadow: 0 0 14px rgba(79,70,229,0.45);
    }
    .progress-fill::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
      animation: progressShine 1.8s linear infinite;
    }
    @keyframes progressShine {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(100%); }
    }

    .main-layout {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .preview-card { margin-top: 0; }
    .preview-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .preview-progress {
      display: none;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
      font-size: 11px;
      color: var(--muted);
    }
    .preview-progress.active { display: flex; }
    .preview-progress .progress-track {
      height: 4px;
    }
    .watermark-notice {
      font-size: 11px;
      color: var(--muted);
      background: rgba(99, 102, 241, 0.14);
      border: 1px solid rgba(99, 102, 241, 0.25);
      border-radius: 10px;
      padding: 8px 10px;
      line-height: 1.4;
      margin-bottom: 10px;
    }
    .watermark-notice strong {
      color: var(--text);
    }
    .preview-item {
      background: var(--card);
      border-radius: 10px;
      border: 1px solid var(--border);
      padding: 6px;
      width: 210px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      transition: box-shadow 0.2s ease, transform 0.2s ease;
    }
    .preview-item.preview-item--active {
      box-shadow: 0 0 0 2px rgba(79,70,229,0.55);
      transform: translateY(-2px);
    }
    .preview-thumb {
      position: relative;
      width: 100%;
      aspect-ratio: 9 / 16;
      border-radius: 10px;
      overflow: hidden;
      background: #000;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: default;
    }
    .preview-thumb.is-image { cursor: pointer; }
    .preview-thumb img,
    .preview-thumb video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      background: #000;
    }
    .watermark-overlay {
      position: absolute;
      inset: 0;
      pointer-events: none;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      z-index: 4;
    }
    .watermark-overlay::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(8, 14, 26, 0.55), rgba(79, 70, 229, 0.2));
      mix-blend-mode: multiply;
    }
    .watermark-overlay::after {
      content: 'akay.io • akay.io • akay.io\Aakay.io • akay.io • akay.io\Aakay.io • akay.io • akay.io';
      white-space: pre;
      font-size: 14px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.35em;
      color: rgba(255, 255, 255, 0.25);
      text-shadow: 0 0 20px rgba(15, 23, 42, 0.45);
      transform: rotate(-28deg);
      line-height: 2.4;
    }
    [data-watermark="off"] .watermark-overlay {
      display: none;
    }
    .preview-meta {
      display: flex;
      flex-direction: column;
      align-items: stretch;
      gap: 6px;
    }
    .preview-btn-group {
      display: flex;
      justify-content: flex-end;
      gap: 6px;
      flex-wrap: wrap;
      align-items: center;
    }
    .preview-url {
      font-size: 10px;
      color: var(--muted);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .jobs-col {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .jobs-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
      max-height: 320px;
      overflow-y: auto;
    }
    .job-item {
      border-radius: 10px;
      border: 1px solid var(--border);
      padding: 8px 9px;
      background: var(--card);
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 11px;
    }
    .job-header {
      display: flex;
      justify-content: space-between;
      gap: 6px;
      align-items: center;
    }
    .job-title {
      font-weight: 500;
      font-size: 11px;
    }
    .job-status {
      padding: 2px 7px;
      border-radius: 999px;
      border: 1px solid var(--border);
    }
    .job-status.in-progress {
      border-color: rgba(245, 158, 11, 0.7);
      color: #fbbf24;
    }
    .job-status.completed {
      border-color: rgba(74, 222, 128, 0.7);
      color: var(--success);
    }
    .job-status.error {
      border-color: rgba(248, 113, 113, 0.7);
      color: var(--danger);
    }
    .job-meta { color: var(--muted); }
    .job-actions {
      display: flex;
      justify-content: flex-end;
      gap: 6px;
      margin-top: 4px;
    }
    .small-label {
      font-size: 10px;
      color: var(--muted);
      margin-bottom: 2px;
    }
    .muted { color: var(--muted); }
    a.download-link { text-decoration: none; }

    .two-col {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    @media (min-width: 900px) {
      .two-col { flex-direction: row; }
      .two-col > div { flex: 1; }
    }

    .hidden{display:none!important}
    .form-section-title{
      font-size:11px;color:var(--muted);
      text-transform:uppercase;letter-spacing:.08em;
      margin:6px 0 6px
    }

    .upload-area {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: 8px;
    }
    .upload-dropzone {
      border-radius: 12px;
      border: 1px dashed rgba(99,102,241,0.35);
      padding: 14px;
      background: rgba(2, 6, 23, 0.85);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      cursor: pointer;
      transition: border-color 0.3s ease, background 0.3s ease, transform 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    .upload-dropzone::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, rgba(99,102,241,0.18), transparent 55%);
      opacity: 0;
      transition: opacity 0.25s ease;
    }
    .upload-dropzone:hover {
      border-color: rgba(99,102,241,0.6);
      background: rgba(15,23,42,0.85);
      transform: translateY(-1px);
    }
    .upload-dropzone:hover::after,
    .upload-dropzone.dragover::after,
    .upload-dropzone.has-file::after {
      opacity: 1;
    }
    .upload-dropzone.dragover {
      border-color: rgba(34,197,94,0.6);
    }
    .upload-dropzone.has-file {
      border-color: rgba(34,197,94,0.55);
      background: rgba(15,23,42,0.9);
    }
    .upload-dropzone-content {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 11px;
      color: var(--muted);
    }
    .upload-dropzone strong { color: var(--text); font-size: 12px; }
    .upload-dropzone-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .upload-preview {
      width: 64px;
      height: 64px;
      border-radius: 10px;
      border: 1px solid var(--border);
      object-fit: cover;
      background: var(--card);
    }
    .upload-status {
      font-size: 11px;
      color: var(--muted);
      display: none;
    }
    .upload-status.ok { color: var(--success); display: block; }
    .upload-status.err { color: var(--danger); display: block; }
    .upload-status.progress { color: #fbbf24; display: block; }

    .gemini-mode-section {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: 10px;
    }
    .gemini-mode-toggle {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .gemini-mode-btn {
      flex: 1 1 160px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: rgba(2, 6, 23, 0.85);
      color: var(--muted);
      padding: 10px 12px;
      text-align: left;
      font-size: 11px;
      cursor: pointer;
      transition: all 0.25s ease;
      position: relative;
      overflow: hidden;
    }
    .gemini-mode-btn strong {
      display: block;
      color: var(--text);
      font-size: 12px;
      margin-bottom: 4px;
    }
    .gemini-mode-btn span {
      display: block;
      opacity: 0.78;
      line-height: 1.4;
    }
    .gemini-mode-btn::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(99,102,241,0.16), transparent 55%);
      opacity: 0;
      transition: opacity 0.25s ease;
    }
    .gemini-mode-btn:hover::after { opacity: 1; }
    .gemini-mode-btn.active {
      border-color: var(--accent);
      background: var(--accent-soft);
      color: var(--text);
      box-shadow: 0 14px 34px rgba(99,102,241,0.28);
    }
    .gemini-mode-desc {
      font-size: 11px;
      color: var(--muted);
      line-height: 1.5;
    }

    .gemini-reference-section {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: 6px;
    }
    .gemini-dropzone {
      border-radius: 12px;
      border: 1px dashed rgba(99,102,241,0.35);
      padding: 12px;
      background: rgba(2, 6, 23, 0.85);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      cursor: pointer;
      transition: border-color 0.3s ease, background 0.3s ease, transform 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    .gemini-dropzone::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, rgba(99,102,241,0.18), transparent 55%);
      opacity: 0;
      transition: opacity 0.25s ease;
    }
    .gemini-dropzone:hover,
    .gemini-dropzone.dragover {
      border-color: rgba(99,102,241,0.6);
      background: rgba(15,23,42,0.85);
      transform: translateY(-1px);
    }
    .gemini-dropzone:hover::after,
    .gemini-dropzone.dragover::after,
    .gemini-dropzone.has-file::after {
      opacity: 1;
    }
    .gemini-dropzone.has-file {
      border-color: rgba(34,197,94,0.55);
    }
    .gemini-dropzone-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 11px;
      color: var(--muted);
    }
    .gemini-dropzone-info strong {
      color: var(--text);
      font-size: 12px;
    }
    .gemini-dropzone-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .gemini-ref-helper {
      font-size: 11px;
      color: var(--muted);
    }
    .gemini-ref-add input {
      height: 32px;
    }
    .gemini-ref-list {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-top: 4px;
    }
    .gemini-ref-item {
      display: flex;
      align-items: center;
      gap: 10px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: var(--card);
      padding: 6px 8px;
    }
    .gemini-ref-thumb {
      width: 52px;
      height: 52px;
      border-radius: 8px;
      object-fit: cover;
      border: 1px solid var(--border);
      background: #000;
    }
    .gemini-ref-meta {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 2px;
      font-size: 11px;
      color: var(--muted);
      min-width: 0;
    }
    .gemini-ref-meta strong {
      color: var(--text);
      font-size: 11px;
    }
    .gemini-ref-meta span {
      word-break: break-all;
    }

    .job-progress {
      margin-top: 8px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .job-progress-label {
      display: flex;
      justify-content: space-between;
      font-size: 10px;
      color: var(--muted);
    }
    .job-progress .progress-track {
      height: 4px;
    }

    .film-app {
      display: grid;
      grid-template-columns: minmax(0, 2fr) 320px;
      gap: 18px;
      min-height: calc(100vh - 50px);
      padding: 12px 16px 16px;
      position: relative;
      z-index: 1;
    }
    @media (max-width: 1100px) {
      .film-app { grid-template-columns: 1fr; }
    }
    .film-scenes-board {
      background: linear-gradient(135deg, rgba(37,99,235,0.14), rgba(14,165,233,0.1));
      border-radius: 12px;
      border: 1px dashed rgba(75,85,99,0.8);
      padding: 16px;
      min-height: 220px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .film-empty-state {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 20px 10px;
    }
    .film-empty-icon {
      width: 48px;
      height: 48px;
      border-radius: 16px;
      border: 1px solid var(--border);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 8px;
      font-size: 24px;
      background: var(--card);
    }
    .film-scenes-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 10px;
    }
    .film-scene-card {
      background: var(--card);
      border-radius: 10px;
      border: 1px solid var(--border);
      padding: 8px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-size: 11px;
    }
    .film-scene-thumb {
      width: 100%;
      aspect-ratio: 9 / 16;
      border-radius: 12px;
      object-fit: cover;
      background: #0f172a;
    }
    .film-scene-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 6px;
    }
    .film-scene-title {
      font-weight: 500;
    }
    .film-scene-status {
      padding: 2px 7px;
      border-radius: 999px;
      border: 1px solid var(--border);
    }
    .film-scene-status.progress { color: #fbbf24; border-color: rgba(245,158,11,0.8); }
    .film-scene-status.done { color: var(--success); border-color: rgba(74,222,128,0.8); }
    .film-scene-status.error { color: var(--danger); border-color: rgba(248,113,113,0.8); }
    .film-scene-prompt {
      font-size: 11px;
      color: var(--muted);
      white-space: pre-wrap;
    }

    .film-settings-section {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .film-dropzone {
      border-radius: 12px;
      border: 1px dashed #4b5563;
      padding: 10px;
      text-align: center;
      background: var(--card);
      cursor: pointer;
      min-height: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .film-drop-inner {
      font-size: 11px;
      color: var(--muted);
    }
    .film-drop-inner span {
      font-size: 10px;
      opacity: 0.8;
    }
    .film-character-preview {
      max-width: 100%;
      max-height: 180px;
      border-radius: 8px;
    }

    .film-aspect-toggle {
      display: flex;
      gap: 8px;
    }
    .film-aspect-btn {
      flex: 1;
      font-size: 12px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--muted);
      padding: 6px 10px;
      cursor: pointer;
      text-align: center;
    }
    .film-aspect-btn.film-aspect-active {
      background: var(--accent-soft);
      border-color: var(--accent);
      color: var(--text);
    }
    .film-slider-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    .film-slider-row input[type="range"] {
      flex: 1;
    }

    /* ===== UGC TOOL ===== */
    .ugc-app {
      display: grid;
      grid-template-columns: minmax(0, 2.2fr) 320px;
      gap: 18px;
      min-height: calc(100vh - 50px);
      padding: 12px 16px 16px;
      position: relative;
      z-index: 1;
    }
    @media (max-width: 1100px) {
      .ugc-app { grid-template-columns: 1fr; }
    }
    .ugc-list-card {
      display: flex;
      flex-direction: column;
      gap: 18px;
      padding: 0;
      background: transparent;
      border: none;
    }
    .ugc-empty {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 36px 12px;
      font-size: 12px;
      color: var(--muted);
      border-radius: 16px;
      border: 1px dashed rgba(148,163,184,0.3);
      background: linear-gradient(135deg, rgba(37,99,235,0.08), rgba(14,165,233,0.06));
    }
    .ugc-row {
      background: var(--card);
      border-radius: 18px;
      border: 1px solid rgba(148,163,184,0.22);
      box-shadow: 0 24px 44px rgba(15,23,42,0.22);
      display: grid;
      grid-template-columns: minmax(0, 240px) minmax(0, 220px) minmax(0, 1fr);
      gap: 20px;
      padding: 22px;
      align-items: stretch;
    }
    @media (max-width: 900px) {
      .ugc-row {
        grid-template-columns: 1fr;
        padding: 18px;
      }
    }
    .ugc-column {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .ugc-column-label {
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: rgba(148,163,184,0.95);
    }
    .ugc-image-card {
      position: relative;
      border-radius: 16px;
      border: 1px solid rgba(148,163,184,0.25);
      background: linear-gradient(145deg, rgba(37,99,235,0.18), rgba(14,165,233,0.12));
      min-height: 240px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .ugc-image-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .ugc-image-placeholder {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-size: 12px;
      color: rgba(148,163,184,0.85);
      text-align: center;
      padding: 16px;
    }
    .ugc-placeholder-title {
      font-weight: 600;
      color: rgba(226,232,240,0.95);
    }
    .ugc-placeholder-status {
      font-size: 11px;
      color: rgba(148,163,184,0.7);
    }
    .ugc-video-card {
      border-radius: 16px;
      border: 1px solid rgba(30,41,59,0.45);
      background: radial-gradient(circle at top, rgba(17,24,39,0.88), rgba(2,6,23,0.92));
      min-height: 220px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 18px;
      position: relative;
      overflow: hidden;
    }
    .ugc-video-card video {
      width: 100%;
      height: 100%;
      border-radius: 14px;
      object-fit: cover;
      background: #000;
    }
    .ugc-video-placeholder {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-align: center;
      color: rgba(203,213,225,0.9);
      font-size: 12px;
    }
    .ugc-video-placeholder .ugc-placeholder-status {
      color: rgba(148,163,184,0.7);
    }
    .ugc-video-badge {
      position: absolute;
      top: 12px;
      left: 12px;
      background: rgba(34,197,94,0.18);
      color: rgba(187,247,208,0.95);
      font-size: 11px;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid rgba(34,197,94,0.35);
    }
    .ugc-video-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
    }
    .ugc-result-header {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .ugc-result-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--text);
    }
    .ugc-result-subtitle {
      font-size: 13px;
      color: rgba(148,163,184,0.92);
    }
    .ugc-download-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .ugc-download-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 22px;
      border-radius: 999px;
      border: none;
      background: linear-gradient(135deg, rgba(59,130,246,0.92), rgba(14,165,233,0.86));
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .ugc-download-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 30px rgba(14,165,233,0.25);
    }
    .ugc-download-btn:disabled {
      cursor: not-allowed;
      opacity: 0.55;
      transform: none;
      box-shadow: none;
    }
    .ugc-secondary-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
    }
    .ugc-link-btn {
      background: none;
      border: none;
      padding: 0;
      color: rgba(96,165,250,0.95);
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: color 0.2s ease;
    }
    .ugc-link-btn:hover {
      color: rgba(129,212,250,0.95);
    }
    .ugc-link-btn:disabled {
      color: rgba(148,163,184,0.6);
      cursor: not-allowed;
    }
    .ugc-form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .ugc-field-label {
      font-size: 12px;
      font-weight: 600;
      color: rgba(148,163,184,0.92);
    }
    textarea.ugc-textarea {
      width: 100%;
      min-height: 90px;
      border-radius: 12px;
      border: 1px solid rgba(148,163,184,0.25);
      background: rgba(15,23,42,0.45);
      color: var(--text);
      padding: 10px 12px;
      font-size: 13px;
      resize: vertical;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    textarea.ugc-textarea:focus {
      outline: none;
      border-color: rgba(96,165,250,0.65);
      box-shadow: 0 0 0 2px rgba(96,165,250,0.25);
    }
    textarea.ugc-textarea:disabled {
      opacity: 0.65;
      cursor: not-allowed;
    }
    .ugc-generate-row {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .ugc-generate-btn {
      align-self: flex-start;
      border-radius: 999px;
      border: none;
      padding: 10px 22px;
      background: linear-gradient(135deg, rgba(139,92,246,0.92), rgba(14,165,233,0.86));
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .ugc-generate-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 30px rgba(139,92,246,0.3);
    }
    .ugc-generate-btn:disabled {
      cursor: not-allowed;
      opacity: 0.55;
      transform: none;
      box-shadow: none;
    }
    .ugc-product-preview {
      display:flex;
      gap:6px;
      flex-wrap:wrap;
      margin-top:4px;
    }
    .ugc-product-preview img,
    .ugc-product-preview div {
      width:48px;
      height:48px;
      border-radius:8px;
      border:1px solid var(--border);
      object-fit:cover;
      background: var(--card);
      font-size:10px;
      display:flex;
      align-items:center;
      justify-content:center;
      color:var(--muted);
    }
    .ugc-style-picker {
      position: relative;
    }
    .ugc-style-trigger {
      width: 100%;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--text);
      padding: 10px 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      cursor: pointer;
      transition: border-color 0.2s ease, background 0.2s ease;
    }
    .ugc-style-trigger.open {
      border-color: rgba(129,140,248,0.8);
      background: rgba(76,29,149,0.25);
    }
    .ugc-style-trigger-main {
      display: flex;
      align-items: center;
      gap: 10px;
      text-align: left;
    }
    .ugc-style-icon {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(79,70,229,0.18);
      font-size: 16px;
    }
    .ugc-style-trigger-text {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .ugc-style-label {
      font-size: 13px;
      font-weight: 600;
    }
    .ugc-style-description {
      font-size: 11px;
      color: var(--muted);
    }
    .ugc-style-caret {
      font-size: 14px;
      color: var(--muted);
    }
    .ugc-style-menu {
      position: absolute;
      top: calc(100% + 6px);
      left: 0;
      right: 0;
      background: var(--card);
      border-radius: 12px;
      border: 1px solid rgba(71,85,105,0.8);
      box-shadow: 0 18px 35px rgba(15,23,42,0.5);
      max-height: 360px;
      overflow-y: auto;
      padding: 10px 0;
      z-index: 1200;
    }
    .ugc-style-group {
      padding: 6px 12px 10px;
    }
    .ugc-style-group + .ugc-style-group {
      border-top: 1px solid rgba(30,41,59,0.8);
      margin-top: 4px;
      padding-top: 10px;
    }
    .ugc-style-group-title {
      font-size: 11px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: rgba(148,163,184,0.85);
      margin-bottom: 8px;
    }
    .ugc-style-option {
      width: 100%;
      border: none;
      background: transparent;
      color: var(--text);
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 10px;
      border-radius: 10px;
      cursor: pointer;
      transition: background 0.15s ease, color 0.15s ease;
    }
    .ugc-style-option:hover,
    .ugc-style-option.active {
      background: rgba(99,102,241,0.18);
      color: #e0e7ff;
    }
    .ugc-style-option-icon {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(129,140,248,0.18);
      font-size: 15px;
    }
    .ugc-style-option-meta {
      display: flex;
      flex-direction: column;
      text-align: left;
      gap: 2px;
    }
    .ugc-style-option-label {
      font-size: 12px;
      font-weight: 600;
    }
    .ugc-style-option-desc {
      font-size: 10px;
      color: rgba(148,163,184,0.85);
    }
    @media (max-width: 600px) {
      .ugc-style-menu {
        max-height: 300px;
      }
    }
    .asset-preview {
      position: fixed;
      inset: 0;
      background: rgba(2, 6, 23, 0.86);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      z-index: 2000;
    }
    .asset-preview.hidden { display: none; }
    .asset-preview-inner {
      background: var(--card);
      border-radius: 14px;
      border: 1px solid rgba(99,102,241,0.35);
      max-width: min(90vw, 960px);
      max-height: min(90vh, 620px);
      width: 100%;
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      box-shadow: 0 18px 55px rgba(15,23,42,0.8);
      position: relative;
    }
    .asset-preview-close {
      position: absolute;
      top: 8px;
      right: 10px;
      border: none;
      background: rgba(148,163,184,0.12);
      color: #e5e7eb;
      width: 32px;
      height: 32px;
      border-radius: 999px;
      font-size: 20px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s ease;
    }
    .asset-preview-close:hover {
      background: rgba(148,163,184,0.28);
    }
    .asset-preview-body {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .asset-preview-frame {
      position: relative;
      width: min(100%, 520px);
      max-height: 70vh;
      border-radius: 14px;
      background: #000;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .asset-preview-frame img,
    .asset-preview-frame video {
      width: 100%;
      height: 100%;
      object-fit: contain;
      background: #000;
      display: block;
    }
    .asset-preview-download {
      align-self: flex-end;
      display: inline-flex;
      gap: 6px;
      align-items: center;
      padding: 6px 12px;
      border-radius: 999px;
      border: 1px solid rgba(99,102,241,0.5);
      color: #e0e7ff;
      font-size: 12px;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .asset-preview-download:hover {
      background: rgba(99,102,241,0.2);
      border-color: rgba(129,140,248,0.75);
    }
    body.modal-open {
      overflow: hidden;
    }
    .clickable-media {
      cursor: zoom-in;
    }
    .progress-inline {
      display: none;
      flex-direction: column;
      gap: 6px;
      margin-top: 8px;
    }
    .progress-inline.active {
      display: flex;
    }
    .progress-inline .progress-label {
      display: flex;
      justify-content: space-between;
      font-size: 11px;
      color: var(--muted);
    }
    .progress-inline .progress-bar {
      width: 100%;
      height: 4px;
      border-radius: 999px;
      background: rgba(148,163,184,0.25);
      overflow: hidden;
    }
    .progress-inline .progress-fill {
      height: 100%;
      width: 0;
      border-radius: inherit;
      background: linear-gradient(120deg, rgba(37,99,235,0.85), rgba(14,165,233,0.85));
      transition: width 0.3s ease;
    }
    .maintenance-overlay {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 32px;
      background: radial-gradient(circle at center, rgba(15,23,42,0.85), rgba(2,6,23,0.95));
      z-index: 4000;
    }
    .maintenance-overlay::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(59,130,246,0.28), rgba(14,165,233,0.2), rgba(99,102,241,0.28));
      opacity: 0.5;
      animation: maintenanceGlow 12s ease-in-out infinite;
    }
    .maintenance-overlay.active {
      display: flex;
    }
    .maintenance-overlay__content {
      position: relative;
      z-index: 1;
      max-width: 420px;
      width: 100%;
      background: rgba(15,23,42,0.92);
      border: 1px solid rgba(99,102,241,0.25);
      border-radius: 20px;
      padding: 32px 28px;
      text-align: center;
      box-shadow: 0 25px 60px rgba(2,6,23,0.5);
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .maintenance-overlay__icon {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      background: rgba(96,165,250,0.2);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      margin: 0 auto 6px;
    }
    .maintenance-overlay__content h2 {
      margin: 0;
      font-size: 22px;
      color: #e2e8f0;
    }
    .maintenance-overlay__content p {
      margin: 0;
      font-size: 13px;
      color: rgba(226,232,240,0.75);
      line-height: 1.5;
    }
    .maintenance-overlay__logout {
      margin-top: 12px;
      align-self: center;
      padding: 10px 20px;
      border-radius: 999px;
      border: 1px solid rgba(148,163,184,0.45);
      background: rgba(15,23,42,0.6);
      color: rgba(226,232,240,0.9);
      font-weight: 600;
      letter-spacing: 0.03em;
      cursor: pointer;
      transition: background 0.2s ease, transform 0.2s ease;
    }
    .maintenance-overlay__logout:hover {
      background: rgba(59,130,246,0.35);
      transform: translateY(-1px);
    }
    body.maintenance-active {
      overflow: hidden;
    }
    .free-upgrade-modal {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 5500;
    }
    .free-upgrade-modal.active {
      display: flex;
    }
    .free-upgrade-modal__backdrop {
      position: absolute;
      inset: 0;
      background: rgba(2,6,23,0.75);
      backdrop-filter: blur(8px);
    }
    .free-upgrade-modal__dialog {
      position: relative;
      z-index: 1;
      width: min(90%, 420px);
      background: rgba(15,23,42,0.98);
      border-radius: 20px;
      border: 1px solid rgba(59,130,246,0.35);
      padding: 32px 28px;
      text-align: center;
      box-shadow: 0 25px 50px rgba(15,23,42,0.55);
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .free-upgrade-modal__icon {
      width: 68px;
      height: 68px;
      margin: 0 auto;
      border-radius: 18px;
      background: rgba(96,165,250,0.18);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
    }
    .free-upgrade-modal__title {
      margin: 0;
      font-size: 22px;
      color: #e2e8f0;
    }
    .free-upgrade-modal__message {
      margin: 0;
      font-size: 14px;
      color: rgba(226,232,240,0.8);
      line-height: 1.6;
    }
    .free-upgrade-modal__cta {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 18px;
      border-radius: 999px;
      background: linear-gradient(120deg, #22d3ee, #6366f1);
      color: #0f172a;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .free-upgrade-modal__cta:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(59,130,246,0.35);
    }
    .free-upgrade-modal__close {
      position: absolute;
      top: 12px;
      right: 12px;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      border: none;
      background: rgba(148,163,184,0.16);
      color: rgba(226,232,240,0.85);
      font-size: 20px;
      cursor: pointer;
      transition: background 0.2s ease, transform 0.2s ease;
    }
      .free-upgrade-modal__close:hover {
        background: rgba(148,163,184,0.28);
        transform: rotate(90deg);
      }
      body[data-theme="light"] .free-upgrade-modal__dialog {
        background: rgba(255,255,255,0.98);
        border-color: rgba(148,163,184,0.32);
        color: #0f172a;
      }
      body[data-theme="light"] .free-upgrade-modal__message {
        color: rgba(30,41,59,0.75);
      }
      .topup-modal {
        position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(2,6,23,0.65);
      backdrop-filter: blur(6px);
      z-index: 5000;
      padding: 20px;
    }
    .topup-modal.show {
      display: flex;
    }
    .topup-modal__dialog {
      background: var(--card);
      border-radius: 18px;
      border: 1px solid rgba(148,163,184,0.25);
      width: min(360px, 100%);
      padding: 20px 22px;
      box-shadow: 0 24px 60px rgba(15,23,42,0.45);
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .topup-modal__close {
      position: absolute;
      top: 10px;
      right: 12px;
      width: 28px;
      height: 28px;
      border-radius: 999px;
      border: none;
      background: rgba(148,163,184,0.18);
      color: var(--text);
      font-size: 18px;
      cursor: pointer;
      transition: background 0.2s ease;
    }
    .topup-modal__close:hover {
      background: rgba(148,163,184,0.3);
    }
    .topup-modal__title {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
      color: var(--text);
    }
    .topup-modal__options {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }
    .topup-option {
      border-radius: 12px;
      border: 1px solid rgba(148,163,184,0.28);
      background: var(--card-soft);
      color: var(--text);
      font-size: 13px;
      font-weight: 600;
      padding: 10px 8px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .topup-option:hover {
      border-color: rgba(59,130,246,0.45);
    }
    .topup-option.active {
      border-color: rgba(59,130,246,0.6);
      background: linear-gradient(135deg, rgba(59,130,246,0.22), rgba(14,165,233,0.16));
      color: var(--text);
      box-shadow: 0 14px 28px rgba(59,130,246,0.18);
    }
    .topup-modal__confirm {
      margin-top: 4px;
    }
    .topup-modal__hint {
      font-size: 11px;
      color: var(--muted);
      text-align: center;
    }
    @keyframes maintenanceGlow {
      0% { transform: rotate(0deg) scale(1); opacity: 0.45; }
      50% { transform: rotate(180deg) scale(1.04); opacity: 0.6; }
      100% { transform: rotate(360deg) scale(1); opacity: 0.45; }
    }
  .webhook-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 14px;
  }
  .webhook-form .field-row {
    align-items: flex-end;
  }
  .webhook-form select[multiple] {
    min-height: 96px;
    padding: 8px 12px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: rgba(148, 163, 184, 0.08);
    color: var(--text);
  }
  .webhook-actions {
    display: flex;
    justify-content: flex-end;
    align-items: flex-end;
    min-height: 100%;
  }
  .webhook-actions button {
    align-self: flex-end;
  }
  .webhook-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .webhook-item {
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 12px 14px;
    background: rgba(148, 163, 184, 0.06);
  }
  .webhook-item__header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
  }
  .webhook-item__url {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    word-break: break-word;
  }
  .webhook-item__actions {
    display: flex;
    gap: 8px;
  }
  .webhook-item__meta {
    margin-top: 8px;
    font-size: 11px;
    color: var(--muted);
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  </style>
</head>
<body data-theme="dark" class="has-mobile-bottom-nav">

<div class="workspace sidebar-open">
  <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="true">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 6h16M4 12h10M4 18h16" stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
    <span class="sidebar-toggle-label">Menu</span>
  </button>
  <div class="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-title">AKAY.IO</div>
    <div class="sidebar-sub">AI Hub • Filmmaker • UGC Tool</div>
  </div>
  <nav class="sidebar-nav">
    <button class="sidebar-link active" data-target="viewDashboard">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Dashboard</span>
    </button>
    <button class="sidebar-link" data-target="viewAccount">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M10.325 4.317a1 1 0 0 1 .987-.817h1.376a1 1 0 0 1 .987.817l.287 1.436a1 1 0 0 0 .96.804l1.45.055a1 1 0 0 1 .939.734l.345 1.31a1 1 0 0 1-.276.98l-1.07 1.026a1 1 0 0 0-.3.95l.332 1.406a1 1 0 0 1-.6 1.141l-1.307.522a1 1 0 0 0-.62.83l-.135 1.452a1 1 0 0 1-.995.915h-1.38a1 1 0 0 1-.994-.915l-.135-1.452a1 1 0 0 0-.62-.83l-1.307-.522a1 1 0 0 1-.6-1.141l.332-1.406a1 1 0 0 0-.3-.95l-1.07-1.026a1 1 0 0 1-.276-.98l.345-1.31a1 1 0 0 1 .939-.734l1.45-.055a1 1 0 0 0 .96-.804z" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"></circle></svg>
      </span>
      <span class="nav-label">Pengaturan Akun</span>
    </button>
    <button class="sidebar-link hidden" data-target="viewDrive" id="driveNavButton">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l1.5 2H19a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" stroke-linecap="round" stroke-linejoin="round"></path><path d="M3 11h18" stroke-linecap="round"></path></svg>
      </span>
      <span class="nav-label">Drive</span>
    </button>
    <button class="sidebar-link" data-target="viewFilm" data-feature="filmmaker">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 6h14a2 2 0 0 1 2 2v10H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm0 0V4m4 2V4m4 2V4m4 2V4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Filmmaker</span>
    </button>
    <button class="sidebar-link" data-target="viewUGC" data-feature="ugc">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 5h16M4 12h16M4 19h16" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">UGC Tool</span>
    </button>
    <div class="sidebar-section">AI Generators</div>
    <button class="sidebar-link" data-target="viewHub" data-feature="imageGen">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.364-6.364-2.121 2.121M8.757 15.243l-2.121 2.121m12.728 0-2.121-2.121M8.757 8.757 6.636 6.636" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Image Gen</span>
    </button>
    <button class="sidebar-link" data-target="viewHub" data-feature="videoGen">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4.5 6h9a2.5 2.5 0 0 1 2.5 2.5v7a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 2 15.5v-7A2.5 2.5 0 0 1 4.5 6zm11 2.5 6-3v11l-6-3z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Video Gen</span>
    </button>
    <button class="sidebar-link" data-target="viewAudio" data-feature="audioGen">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 3v10a3 3 0 0 0 6 0V9m-6 6a3 3 0 0 1-6 0V9m6 11v-2m-6 2v-2m12 2v-2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Audio Gen</span>
    </button>
    <div class="sidebar-section">Studio Tambahan</div>
    <a class="sidebar-link sidebar-link--external" href="photo-edit.php" id="photoEditLink" data-feature="flashPhotoEdit">
      <span class="nav-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3zm3-1a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H7zm5 3.25a3.75 3.75 0 1 1 0 7.5a3.75 3.75 0 0 1 0-7.5zm0 2a1.75 1.75 0 1 0 0 3.5a1.75 1.75 0 0 0 0-3.5zm4.25-2.75a.75.75 0 1 1-1.5 0a.75.75 0 0 1 1.5 0z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
      <span class="nav-label">Flash Photo Edit</span>
      <span class="tag">Baru</span>
    </a>
  </nav>
  <div class="sidebar-actions">
    <div class="profile-card" id="profileCard">
      <div class="profile-main">
        <div class="profile-avatar" id="profileAvatar">FM</div>
        <div class="profile-text">
          <div class="profile-title">
            <span class="profile-display" id="profileDisplay">User</span>
            <span class="profile-badge profile-badge--premium" id="profileBadge">PRO</span>
          </div>
          <div class="profile-username" id="profileUsername">@username</div>
        </div>
      </div>
      <div class="profile-credit">
        <span class="credit-label">Credit</span>
        <span class="credit-value" id="profileCoins">0</span>
        <span class="credit-status"><span class="status-dot"></span><span id="profileStatusText">Live</span></span>
      </div>
      <div class="profile-expiry" id="profileProExpiry" hidden>
        <span class="profile-expiry-label">PRO berakhir</span>
        <span class="profile-expiry-value" id="profileProExpiryValue">-</span>
      </div>
      <button type="button" class="profile-topup" id="profileTopup">Top Up Credit</button>
    </div>
    <button type="button" class="logout-btn" id="logoutButton">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M10 12h11l-3-3m3 3-3 3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      <span class="logout-label">Keluar</span>
    </button>
  </div>
</div>

  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <main class="workspace-main">
    <div class="mobile-coin-banner" id="mobileCoinBanner" role="status" aria-live="polite">
      <div class="mobile-coin-info">
        <span class="mobile-coin-label">Sisa Saldo Kredit</span>
        <span class="mobile-coin-value" id="mobileCoinValue">0</span>
      </div>
      <button type="button" class="topup-badge" id="mobileCoinTopup">Top Up</button>
    </div>
    <div id="viewDashboard" class="dashboard-view app-view">
      <div id="accountRestrictionBanner" class="restriction-banner" hidden>
        <div class="restriction-content">
          <span class="restriction-icon" aria-hidden="true">⚠️</span>
          <div>
            <strong>Akun dibatasi.</strong>
            <div id="accountRestrictionMessage">Akun Anda sedang dibatasi oleh admin. Hubungi admin untuk reaktivasi.</div>
          </div>
        </div>
      </div>
      <section class="overview-hero">
        <div class="hero-text">
          <h1>Dashboard Overview</h1>
          <p>Selamat datang kembali! Pantau progress generate konten dan saldo koin kamu.</p>
          <div class="profile-main">
          <div class="profile-avatar" id="profileAvatarMobile">FM</div>
          <div class="profile-text">
            <div class="profile-title">
              <span class="profile-display" id="profileDisplayMobile">User</span>
              <span class="verified-badge-star verified-badge-star--hidden" hidden></span>
              <span class="profile-badge profile-badge--premium" id="profileBadgeMobile">PRO</span>
            </div>
            <div class="profile-username" id="profileUsernameMobile">@username</div>
            <div class="profile-expiry profile-expiry--inline" id="profileProExpiryMobile" hidden>
              <span class="profile-expiry-label">PRO berakhir</span>
              <span class="profile-expiry-value" id="profileProExpiryValueMobile">-</span>
            </div>
          </div>
        </div>
        </div>
        <div class="hero-actions">
          <button type="button" class="profile-topup" id="heroTopup">Top Up Credit</button>
        </div>
      </section>

      <section class="stats-grid">
        <article class="stat-card">
          <span class="stat-label">Saldo Kredit Aktif Kamu</span>
          <span class="stat-value" id="statCoins">0</span>
          <span class="stat-meta">Saldo aktif untuk semua generator</span>
        </article>
        <article class="stat-card">
          <span class="stat-label">Videos Generated</span>
          <span class="stat-value" id="statVideos">0</span>
          <span class="stat-meta">Total job video yang berhasil</span>
        </article>
        <article class="stat-card">
          <span class="stat-label">Images Generated</span>
          <span class="stat-value" id="statImages">0</span>
          <span class="stat-meta">Image & editing task selesai</span>
        </article>
        <article class="stat-card">
          <span class="stat-label">Active Queue</span>
          <span class="stat-value" id="statQueue">0</span>
          <span class="stat-meta">Task yang masih diproses</span>
        </article>
      </section>

      <article id="dashboardAnnouncements" class="card-soft announcement-board" hidden>
        <div class="header" style="margin-bottom:8px;">
          <div>
            <div class="title" style="font-size:16px;">Informasi Terbaru</div>
            <div class="subtitle">Pengumuman resmi dari admin akan tampil di sini.</div>
          </div>
        </div>
        <div class="announcement-list" id="dashboardAnnouncementList">
          <div class="announcement-empty">Belum ada informasi terbaru.</div>
        </div>
      </article>

      <div class="profile-card profile-card--mobile" id="profileCardMobile">

        <div class="profile-credit">
          <span class="credit-label">Credit</span>
          <span class="credit-value" id="profileCoinsMobile">0</span>
          <span class="credit-status"><span class="status-dot"></span><span id="profileStatusMobile">Live</span></span>
        </div>
      </div>
    </div>

    <div id="viewDrive" class="drive-view app-view" hidden>
      <section class="drive-header">
        <div>
          <h1>Creative Drive</h1>
          <p>Simpan dan kelola seluruh hasil generate foto &amp; video kamu di satu tempat.</p>
          <p id="driveWatermarkNotice" class="watermark-notice" hidden>
            Hasil generate akun FREE akan menampilkan watermark <strong>akay.io</strong>. Upgrade ke PRO untuk menghilangkan watermark.
          </p>
        </div>
        <div class="drive-meta">
          <span id="driveTotalCount">0 file</span>
          <span>&bull;</span>
          <span id="driveTypeSummary">0 foto • 0 video</span>
        </div>
      </section>

      <section class="drive-filters">
        <div class="drive-filter">
          <label for="driveTypeFilter">Tipe konten</label>
          <select id="driveTypeFilter">
            <option value="all">Semua</option>
            <option value="image">Foto</option>
            <option value="video">Video</option>
          </select>
        </div>
        <div class="drive-filter">
          <label for="driveDateFilter">Tanggal</label>
          <input type="date" id="driveDateFilter">
        </div>
        <div class="drive-filter">
          <label for="driveSortFilter">Urutkan</label>
          <select id="driveSortFilter">
            <option value="newest">Terbaru</option>
            <option value="oldest">Terlama</option>
          </select>
        </div>
        <button type="button" class="small secondary drive-clear-date" id="driveClearDate">Reset tanggal</button>
      </section>

      <section class="drive-content">
        <div id="driveEmpty" class="drive-empty">Belum ada file tersimpan. Generate konten untuk mengisi drive pribadi kamu.</div>
        <div id="driveGrid" class="drive-grid"></div>
      </section>
    </div>

    <div id="viewAccount" class="account-view app-view" hidden>
      <section class="account-hero">
        <div>
          <h1>Pengaturan Akun</h1>
          <p>Kelola foto profil dan keamanan password kamu dari satu tempat.</p>
        </div>
      </section>

      <section class="account-settings" aria-labelledby="accountSettingsTitle">
        <div class="card-soft account-settings-card">
          <div class="header" id="accountSettingsTitle">
            <div>
              <div class="title" style="font-size:16px">Profil &amp; Keamanan</div>
              <div class="subtitle">Perbarui avatar serta ubah password secara aman.</div>
            </div>
          </div>

          <div class="account-settings-grid">
            <form id="avatarForm" class="account-form" novalidate>
              <h3 class="account-form-title">Foto Profil</h3>
              <div class="avatar-row">
                <div class="avatar-preview" id="avatarPreview" aria-hidden="true">
                  <span class="avatar-initials" id="avatarPreviewInitials">FM</span>
                  <img id="avatarPreviewImage" alt="Preview foto profil" loading="lazy">
                </div>
                <div class="avatar-fields">
                  <label class="account-label" for="avatarUrlInput">Link gambar</label>
                  <input type="url" id="avatarUrlInput" placeholder="https://contoh.com/avatar.jpg" autocomplete="off">
                  <div class="account-form-actions">
                    <input type="file" id="avatarFileInput" accept="image/*" style="display:none">
                    <button type="button" class="secondary small" id="avatarUploadBtn">Upload foto</button>
                    <button type="button" class="secondary small" id="avatarRemoveBtn">Hapus</button>
                  </div>
                  <p class="account-form-hint">Disarankan gambar persegi minimal 200×200px.</p>
                  <button type="submit" class="account-save-btn">Simpan Avatar</button>
                  <div class="account-form-status" id="avatarFormStatus" role="status"></div>
                </div>
              </div>
            </form>

            <form id="passwordForm" class="account-form" novalidate>
              <h3 class="account-form-title">Ganti Password</h3>
              <div class="account-form-grid">
                <label class="account-label" for="currentPasswordInput">Password saat ini</label>
                <input type="password" id="currentPasswordInput" autocomplete="current-password" required>

                <label class="account-label" for="newPasswordInput">Password baru</label>
                <input type="password" id="newPasswordInput" autocomplete="new-password" minlength="6" required>

                <label class="account-label" for="confirmPasswordInput">Konfirmasi password</label>
                <input type="password" id="confirmPasswordInput" autocomplete="new-password" minlength="6" required>
              </div>
              <p class="account-form-hint">Gunakan minimal 6 karakter kombinasi huruf & angka.</p>
              <button type="submit" class="account-save-btn">Update Password</button>
              <div class="account-form-status" id="passwordFormStatus" role="status"></div>
            </form>
          </div>
        </div>
      </section>
    </div>

<!-- ======================= AI HUB ======================= -->
<div id="viewHub" class="hub-app app-view" hidden>
  <div class="hub-column">
    <div class="card">
      <div class="header">
        <div>
          <div class="title">AKAY-AI Studio</div>
          <div class="subtitle">
            <span id="featureLabel">Image Gen</span> · Single PHP • Multi model Freepik
          </div>
        </div>
        <div class="badge">
          <span class="dot-large"></span>
          <span>Proxy PHP aktif</span>
        </div>
      </div>
    </div>

    <div class="card-soft hub-model-card">
      <div class="select-group">
        <div class="model-group-label">Model</div>
        <select id="modelSelect">
          <optgroup label="Text → Image">
            <option value="gemini">Gemini 2.5 Flash</option>
            <option value="imagen3">Google Imagen 3</option>
            <option value="seedream4">Seedream 4</option>
            <option value="seedream4edit">Seedream 4 Edit</option>
            <option value="fluxPro11">Flux Pro v1.1</option>
            <option value="mystic">Image Mystic</option>
            <option value="getHyperflux">Get Hyperflux</option>
          </optgroup>
          <optgroup label="Upscale / Edit">
            <option value="upscalerCreative">Upscaler Creative</option>
            <option value="upscalePrecV1">Upscale Precision V1</option>
            <option value="upscalePrecV2">Upscale Precision V2</option>
            <option value="removeBg">Remove Background</option>
          </optgroup>
          <optgroup label="Image → Video">
            <option value="wan480">Wan v2.2 – 480p</option>
            <option value="wan720">Wan v2.2 – 720p</option>
            <option value="seedancePro480">Seedance Pro – 480p</option>
            <option value="seedancePro720">Seedance Pro – 720p</option>
            <option value="seedancePro1080">Seedance Pro – 1080p</option>
            <option value="klingStd21">Kling Std v2.1</option>
            <option value="kling21Master">Kling v2.1 Master</option>
            <option value="kling25Pro">Kling v2.5 Pro</option>
            <option value="pixverse">PixVerse</option>
            <option value="minimax1080">MiniMax Hailuo 02 – 1080p</option>
          </optgroup>
          <optgroup label="Lip Sync">
            <option value="latentSync">Latent-Sync</option>
          </optgroup>
        </select>
      </div>
      <div class="small-label">Hint input</div>
      <div id="modelHint" class="muted" style="font-size:11px">
        Image Gen (Gemini, Imagen, Seedream, Flux, Mystic, Hyperflux): cukup prompt + opsi aspect ratio.
        Image Editing / Upscale: wajib image URL.
        Image→Video & PixVerse/Kling: image URL + prompt.
        Latent-Sync: video URL + audio URL.
      </div>
    </div>

    <div class="card hub-form-card">
      <form id="jobForm">
        <div class="two-col">
          <div id="rowPrompt">
            <label for="prompt">Prompt</label>
            <textarea id="prompt" placeholder="Deskripsikan gambar/video yang diinginkan"></textarea>
          </div>

          <div>
          <div id="fieldsTitle" class="form-section-title">Image Gen</div>

          <div id="geminiModeSection" class="gemini-mode-section hidden">
            <div class="form-section-title">PILIH MODE</div>
            <div class="gemini-mode-toggle">
              <button type="button" class="gemini-mode-btn active" data-gemini-mode="text">
                <strong>Text-to-Image</strong>
              </button>
              <button type="button" class="gemini-mode-btn" data-gemini-mode="single">
                <strong>Image-to-Image</strong>
              </button>
              <button type="button" class="gemini-mode-btn" data-gemini-mode="multi">
                <strong>⭐ Multi-Image Reference</strong>
              </button>
            </div>
            <div id="geminiModeDescription" class="gemini-mode-desc">
              Mode 1: Text-to-Image — Masukkan prompt deskriptif tanpa gambar.
            </div>
          </div>

          <div id="geminiReferenceSection" class="gemini-reference-section hidden">
            <div class="form-section-title">Reference Images</div>
            <div class="gemini-dropzone" id="geminiDropzone">
              <input id="geminiFileInput" type="file" accept="image/*" multiple style="display:none">
              <div class="gemini-dropzone-info">
                <strong>Upload referensi</strong>
                <span class="gemini-ref-helper" id="geminiRefHelper">Mode 2 membutuhkan 1 gambar referensi.</span>
                <span style="font-size:10px; opacity:0.8;">Drag &amp; drop, klik, atau paste (Ctrl+V) saat mode aktif.</span>
                <span style="font-size:11px; color:var(--text);">Dipilih: <b id="geminiDropCounter">0/1</b></span>
              </div>
              <div class="gemini-dropzone-actions">
                <button type="button" class="small secondary" id="geminiFileButton">Pilih file</button>
              </div>
            </div>
            <div class="gemini-ref-add field-row">
              <input id="geminiRefUrl" type="text" placeholder="https://...jpg">
              <button type="button" class="small" id="geminiRefAddBtn">Tambah URL</button>
            </div>
            <div class="gemini-ref-list" id="geminiRefList"></div>
          </div>

          <div id="rowImageUrl">
            <label for="imageUrl">Image URL</label>
            <input id="imageUrl" type="text" placeholder="https://...jpg / .png">
            <div class="upload-area" id="imageUploadArea">
              <input id="imageUploadInput" type="file" accept="image/*" style="display:none">
              <div class="upload-dropzone" id="imageUploadDropzone">
                <div class="upload-dropzone-content">
                  <strong>Upload langsung ke server</strong>
                  <span>Drag & drop, klik, atau paste gambar. URL akan terisi otomatis.</span>
                  <span style="opacity:0.8">Format JPG, PNG, WEBP hingga 15MB.</span>
                </div>
                <div class="upload-dropzone-actions">
                  <img id="imageUploadPreview" class="upload-preview" style="display:none" alt="Preview upload">
                  <button type="button" class="small secondary" id="imageUploadButton">Pilih file</button>
                </div>
              </div>
              <div class="upload-status" id="imageUploadStatus"></div>
            </div>
          </div>

          <div id="rowVideoAudio" class="field-row hidden" style="margin-top:4px">
            <div>
              <label for="videoUrl">Video URL (Lipsync)</label>
              <input id="videoUrl" type="text" placeholder="https://...mp4">
            </div>
            <div>
              <label for="audioUrl">Audio URL (Lipsync)</label>
              <input id="audioUrl" type="text" placeholder="https://...mp3 / .wav">
            </div>
          </div>

          <div id="rowVideoSettings" class="field-row hidden" style="margin-top:4px">
            <div>
              <label for="videoDuration">Durasi video</label>
              <select id="videoDuration"></select>
            </div>
            <div>
              <label for="videoLayout">Layout</label>
              <select id="videoLayout"></select>
            </div>
          </div>

          <div id="rowTIOptions" class="field-row hidden" style="margin-top:4px">
            <div>
              <label for="numImages">Jumlah image</label>
              <input id="numImages" type="number" min="1" max="4" value="1">
            </div>
            <div>
              <label for="aspectRatio">Aspect ratio</label>
              <select id="aspectRatio">
                <option value="">Auto</option>
                <option value="square_1_1">1:1</option>
                <option value="portrait_3_4">3:4</option>
                <option value="portrait_9_16">9:16</option>
                <option value="landscape_16_9">16:9</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="btn-group">
        <button type="submit" id="submitBtn">Jalankan task</button>
        <button type="button" class="secondary" id="clearPromptBtn">Clear form</button>
      </div>

        <div class="status-bar">
          <div class="status-text" id="statusText">Siap.</div>
          <div class="status-pill" id="statusPill">IDLE</div>
          <div class="status-progress" id="statusProgressWrapper">
            <div class="status-progress-label">Progress <span id="statusPercent">0%</span></div>
            <div class="progress-track">
              <div class="progress-fill" id="statusProgressFill"></div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <div class="card-soft preview-card">
      <div class="header" style="margin-bottom:6px">
        <div>
          <div class="title" style="font-size:14px">Status & Preview</div>
          <div class="subtitle">Preview kecil + tombol download</div>
        </div>
        <button type="button" class="small secondary" id="clearPreviewBtn">Clear preview</button>
      </div>

      <div id="previewEmpty" class="muted" style="font-size:11px">
        Belum ada hasil. Jalankan task atau klik "View" dari history.
      </div>

      <div id="previewContainer" style="display:none">
        <div class="small-label">Job aktif</div>
        <div id="previewJobMeta" class="muted" style="font-size:11px;margin-bottom:6px"></div>
        <div class="preview-progress" id="previewProgress">
          <div class="status-progress-label">Progress <span id="previewProgressPercent">0%</span></div>
          <div class="progress-track">
            <div class="progress-fill" id="previewProgressFill"></div>
          </div>
        </div>
        <div id="watermarkNotice" class="watermark-notice" hidden>
          Hasil generate akun FREE akan menampilkan watermark <strong>akay.io</strong>. Upgrade ke PRO untuk menghilangkan watermark.
        </div>
        <div class="preview-grid" id="previewGrid"></div>
      </div>
    </div>
  </div>

  <div class="jobs-col hub-side">
    <div class="card-soft">
      <div class="header" style="margin-bottom:6px">
        <div>
          <div class="title" style="font-size:14px">Queue</div>
          <div class="subtitle">Task aktif</div>
        </div>
        <button type="button" class="small secondary" id="refreshQueueBtn">Refresh</button>
      </div>
      <div id="queueList" class="jobs-list"></div>
      <div id="queueEmpty" class="muted" style="font-size:11px">Queue kosong.</div>
    </div>

    <div class="card-soft">
      <div class="header" style="margin-bottom:6px">
        <div>
          <div class="title" style="font-size:14px">History</div>
          <div class="subtitle">Selesai / gagal (localStorage)</div>
        </div>
        <button type="button" class="small secondary" id="clearHistoryBtn">Clear history</button>
      </div>
      <div id="historyList" class="jobs-list"></div>
      <div id="historyEmpty" class="muted" style="font-size:11px">Belum ada history.</div>
    </div>

    <div class="card-soft">
      <div class="header" style="margin-bottom:6px">
        <div>
          <div class="title" style="font-size:14px">Freepik Webhooks</div>
          <div class="subtitle">Atur callback untuk menerima status task secara otomatis</div>
        </div>
        <button type="button" class="small secondary" id="webhookRefreshBtn">Refresh</button>
      </div>
      <form id="webhookForm" class="webhook-form" novalidate>
        <div class="field-row">
          <div>
            <label for="webhookUrl">Callback URL</label>
            <input type="url" id="webhookUrl" placeholder="https://contoh.com/webhook" required>
          </div>
          <div>
            <label for="webhookEvents">Events</label>
            <select id="webhookEvents" multiple size="3">
              <option value="task.completed" selected>task.completed</option>
              <option value="task.failed">task.failed</option>
              <option value="task.progress">task.progress</option>
            </select>
            <p class="muted" style="font-size:10px;margin-top:4px;">Tahan Ctrl / Cmd untuk memilih lebih dari satu event.</p>
          </div>
        </div>
        <div class="field-row">
          <div>
            <label for="webhookSecret">Secret (opsional)</label>
            <input type="text" id="webhookSecret" placeholder="Token rahasia untuk verifikasi">
          </div>
          <div class="webhook-actions">
            <button type="submit" id="webhookSubmit">Register Webhook</button>
          </div>
        </div>
        <div class="account-form-status" id="webhookStatus" role="status"></div>
      </form>
      <div id="webhookList" class="webhook-list">
        <div class="muted" style="font-size:11px">Belum ada webhook terdaftar.</div>
      </div>
    </div>
  </div>
</div>

<!-- ======================= FILMMAKER ======================= -->
<div id="viewAudio" class="gemini-view app-view" hidden>
  <section class="card gemini-hero">
    <div>
      <h1>Audio Generator (TTS)</h1>
      <p>Ubah skrip teks menjadi audio natural menggunakan Google Gemini Text-to-Speech.</p>
    </div>
    <div class="gemini-hero-badges">
      <span class="gemini-badge">Google Gemini</span>
      <span class="gemini-badge gemini-badge--accent">Speech Generation</span>
    </div>
  </section>

  <div class="gemini-grid">
    <article class="card-soft gemini-card">
      <header class="gemini-card__header">
        <div>
          <h2>Text-to-Speech</h2>
          <p>Tentukan model, suara, dan bahasa lalu unduh audio narasi yang dihasilkan.</p>
        </div>
      </header>
      <form id="geminiSpeechForm" class="gemini-form" novalidate>
        <div class="gemini-field-row">
          <div class="gemini-field">
            <label for="geminiSpeechModel">Model</label>
            <select id="geminiSpeechModel">
              <option value="gemini-1.5-flash-latest">Gemini 1.5 Flash</option>
              <option value="gemini-1.5-pro-latest">Gemini 1.5 Pro</option>
            </select>
          </div>
          <div class="gemini-field">
            <label for="geminiSpeechTemperature">Temperature</label>
            <input type="number" id="geminiSpeechTemperature" step="0.1" min="0" max="2" value="0.3">
          </div>
        </div>
        <div class="gemini-field">
          <label for="geminiSpeechPrompt">Teks Narasi</label>
          <textarea id="geminiSpeechPrompt" rows="5" placeholder="Masukkan skrip narasi yang ingin dibacakan"></textarea>
        </div>
        <div class="gemini-field-row">
          <div class="gemini-field">
            <label for="geminiSpeechVoice">Voice</label>
            <select id="geminiSpeechVoice">
              <option value="Puck">Puck</option>
              <option value="Soleil">Soleil</option>
              <option value="Orion">Orion</option>
              <option value="Luna">Luna</option>
            </select>
          </div>
          <div class="gemini-field">
            <label for="geminiSpeechLanguage">Bahasa</label>
            <select id="geminiSpeechLanguage">
              <option value="id-ID">Indonesia</option>
              <option value="en-US">English (US)</option>
              <option value="en-GB">English (UK)</option>
              <option value="ja-JP">日本語</option>
            </select>
          </div>
        </div>
        <div class="gemini-field-row">
          <div class="gemini-field">
            <label for="geminiSpeechFormat">Format Audio</label>
            <select id="geminiSpeechFormat">
              <option value="audio/mp3">MP3</option>
              <option value="audio/wav">WAV</option>
              <option value="audio/ogg">OGG</option>
            </select>
          </div>
          <div class="gemini-field">
            <label for="geminiSpeechReference">Referensi Suara (opsional)</label>
            <input type="url" id="geminiSpeechReference" placeholder="https://contoh.com/ref.mp3">
          </div>
        </div>
        <div class="gemini-actions">
          <button type="submit" id="geminiSpeechSubmit">Generate Audio</button>
          <button type="button" class="secondary" id="geminiSpeechReset">Reset</button>
        </div>
        <div class="account-form-status" id="geminiSpeechStatus" role="status"></div>
      </form>
      <div class="gemini-audio-output">
        <audio id="geminiSpeechAudio" controls style="display:none"></audio>
        <a id="geminiSpeechDownload" class="download-link" href="#" download hidden>Download Audio</a>
      </div>
      <section class="gemini-guides">
        <h3>Tips Prompt</h3>
        <ul>
          <li>Gunakan detail suasana &amp; emosi: <em>"Narasi ramah dengan energi tinggi untuk video promosi."</em></li>
          <li>Atur pengucapan dengan menambahkan catatan di dalam tanda kurung.</li>
          <li>Gunakan referensi suara bila ingin mencocokkan tone tertentu.</li>
        </ul>
      </section>
    </article>
  </div>
</div>

<div id="viewFilm" class="film-app app-view" hidden>
  <div class="card">
    <div class="header">
      <div>
        <div class="title">Filmmaker</div>
        <div class="subtitle">Buat rangkaian scene sinematik dari satu karakter & story brief</div>
      </div>
      <div class="badge">
        <span>Gemini Flash 2.5</span>
      </div>
    </div>
    <div class="film-scenes-board">
      <div id="filmScenesEmpty" class="film-empty-state">
        <div>
          <div class="film-empty-icon">🎬</div>
          <div class="subtitle">Hasil Generate Akan Muncul Disini</div>
          <div class="muted" style="font-size:11px">
            Upload character image dan isi story brief di sisi kanan, lalu klik “Generate Scenes”.
          </div>
        </div>
      </div>
      <div id="filmScenesContainer" class="film-scenes-container"></div>
    </div>
  </div>

  <div class="card-soft">
    <div class="header" style="margin-bottom:8px">
      <div>
        <div class="title" style="font-size:14px">Film Settings</div>
        <div class="subtitle">Create cinematic scenes</div>
      </div>
    </div>

    <div class="film-settings-section">
      <div>
        <div class="small-label">Character Reference</div>
        <div id="filmCharacterDrop" class="film-dropzone">
          <input id="filmCharacterInput" type="file" accept="image/*" style="display:none">
          <div id="filmCharacterIdle" class="film-drop-inner">
            <div style="margin-bottom:4px;">Upload Foto Kamu</div>
            <span>PNG, JPG · rekomendasi foto Close UP</span>
          </div>
          <img id="filmCharacterPreview" class="film-character-preview" style="display:none" alt="Character preview">
        </div>
      </div>

<div>
        <div class="small-label">Filmmaker State Custom</div>
        <div id="filmStatePicker" class="ugc-style-picker film-state-picker">
          <button type="button" id="filmStateTrigger" class="ugc-style-trigger">
            <div class="ugc-style-trigger-main">
              <span id="filmStateIcon" class="ugc-style-icon">🎬</span>
              <div class="ugc-style-trigger-text">
                <div id="filmStateLabel" class="ugc-style-label">AUTO STATE</div>
                <div id="filmStateDescription" class="ugc-style-description">Tidak Memilih · diproses oleh server</div>
              </div>
            </div>
            <span class="ugc-style-caret">▾</span>
          </button>
          <div id="filmStateMenu" class="ugc-style-menu hidden"></div>
          <input type="hidden" id="filmStateValue" value="auto">
        </div>
      </div>

      <div>
        <label for="filmBrief">Story Brief</label>
        <textarea id="filmBrief" placeholder="Contoh: Seorang detektif cyberpunk menyelidiki kasus misteri di kota neon saat hujan malam..."></textarea>
      </div>

      <div>
        <div class="film-slider-row">
          <div class="small-label">Number of Scenes</div>
          <div id="filmSceneCountLabel" class="small-label" style="text-align:right">6 scenes</div>
        </div>
        <input id="filmSceneCount" type="range" min="2" max="12" value="6">
      </div>

      <div>
        <div class="small-label" style="margin-bottom:4px;">Aspect Ratio</div>
        <div class="film-aspect-toggle">
          <button type="button" class="film-aspect-btn film-aspect-active" data-film-aspect="9:16">9:16 Vertical</button>
          <button type="button" class="film-aspect-btn" data-film-aspect="16:9">16:9 Cinema</button>
        </div>
      </div>

      

      <div>
        <button type="button" id="filmGenerateBtn" style="width:100%;margin-top:4px;">
          Generate Scenes
        </button>
        <div class="muted" style="font-size:10px;margin-top:6px;">
          Setiap scene akan memanggil model Gemini Flash 2.5 secara terpisah. Progress scene akan muncul di panel kiri.
        </div>
        <div class="progress-inline" id="filmProgress">
          <div class="progress-label"><span>Progress</span><span id="filmProgressValue">0%</span></div>
          <div class="progress-bar">
            <div class="progress-fill" id="filmProgressFill"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ======================= UGC TOOL ======================= -->
<div id="viewUGC" class="ugc-app app-view" hidden>
  <div class="card-soft">
    <div class="header" style="margin-bottom:6px">
      <div>
        <div class="title" style="font-size:16px">UGC Prompt Generator</div>
        <div class="subtitle">AI-powered UGC photography prompts from your product images</div>
      </div>
    </div>

    <div id="ugcList" class="ugc-list-card">
      <div id="ugcEmpty" class="ugc-empty">
        No UGC images generated yet<br>
        Upload product images dan klik <b>Generate UGC</b>.
      </div>
    </div>
  </div>

  <div class="card-soft">
    <div class="header" style="margin-bottom:8px">
      <div>
        <div class="title" style="font-size:14px">Upload &amp; Settings</div>
        <div class="subtitle">Configure your prompts</div>
      </div>
    </div>

    <div class="film-settings-section">
      <div>
        <div class="small-label">Product Images (max 3)</div>
        <div class="film-dropzone" id="ugcProductDrop">
          <input id="ugcProductInput" type="file" accept="image/*" multiple style="display:none">
          <div class="film-drop-inner">
            <div style="margin-bottom:4px;">Click, drag &amp; drop, atau paste</div>
            <span>PNG, JPG · max 3 images (3 Foto Produk Kamu)</span>
          </div>
        </div>
        <div id="ugcProductPreview" class="ugc-product-preview"></div>
      </div>

      <div>
        <div class="small-label">Model Image (Optional)</div>
        <div class="film-dropzone" id="ugcModelDrop">
          <input id="ugcModelInput" type="file" accept="image/*" style="display:none">
          <div id="ugcModelIdle" class="film-drop-inner">
            <div style="margin-bottom:4px;">Click, drag &amp; drop, atau paste</div>
            <span>PNG, JPG · 1 image (1 Foto Model)</span>
          </div>
          <img id="ugcModelPreview" class="film-character-preview" style="display:none" alt="Model preview">
        </div>
      </div>

      <div>
        <div class="small-label">Prompt Style</div>
        <div id="ugcStylePicker" class="ugc-style-picker">
          <button type="button" id="ugcStyleTrigger" class="ugc-style-trigger">
            <div class="ugc-style-trigger-main">
              <span id="ugcStyleIcon" class="ugc-style-icon">✨</span>
              <div class="ugc-style-trigger-text">
                <div id="ugcStyleLabel" class="ugc-style-label">Basic</div>
                <div id="ugcStyleDescription" class="ugc-style-description">Diverse &amp; Flexible contexts</div>
              </div>
            </div>
            <span class="ugc-style-caret">▾</span>
          </button>
          <div id="ugcStyleMenu" class="ugc-style-menu hidden"></div>
          <input type="hidden" id="ugcStyleValue" value="basic">
        </div>
      </div>

      <div>
        <div class="small-label">Product Brief (Optional)</div>
        <textarea id="ugcBrief" placeholder="Contoh: Mempromosikan botol air berkelanjutan untuk penggemar kebugaran, menekankan gaya hidup ramah lingkungan dan aktivitas luar ruangan"></textarea>
      </div>

      <div>
        <button type="button" id="ugcGenerateBtn" style="width:100%;margin-top:4px;">
          Generate UGC
        </button>
        <div class="muted" style="font-size:10px;margin-top:6px;">
          Sistem akan membuat 5 ide UGC beserta gambar dari Gemini Flash 2.5.
          Tiap baris punya prompt video + tombol Generate Video (Seedance 1080) &amp; Download.
        </div>
      </div>
    </div>
  </div>
</div>

</main>
</div>

<nav class="mobile-bottom-nav" aria-label="Navigasi utama dashboard mobile">
  <button type="button" class="mobile-bottom-nav__item js-dashboard-nav is-active" data-target="viewDashboard">
    <span class="mobile-bottom-nav__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </span>
    <span class="mobile-bottom-nav__label">Dashboard</span>
  </button>
  <button type="button" class="mobile-bottom-nav__item js-dashboard-nav" data-target="viewHub" data-feature="imageGen">
    <span class="mobile-bottom-nav__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.364-6.364-2.121 2.121M8.757 15.243l-2.121 2.121m12.728 0-2.121-2.121M8.757 8.757 6.636 6.636" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </span>
    <span class="mobile-bottom-nav__label">Image Gen</span>
  </button>
  <button type="button" class="mobile-bottom-nav__item js-dashboard-nav" data-target="viewHub" data-feature="videoGen">
    <span class="mobile-bottom-nav__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4.5 6h9a2.5 2.5 0 0 1 2.5 2.5v7a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 2 15.5v-7A2.5 2.5 0 0 1 4.5 6zm11 2.5 6-3v11l-6-3z" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </span>
    <span class="mobile-bottom-nav__label">Video Gen</span>
  </button>
  <button type="button" class="mobile-bottom-nav__item js-dashboard-nav" data-target="viewFilm" data-feature="filmmaker">
    <span class="mobile-bottom-nav__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 6h14a2 2 0 0 1 2 2v10H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm0 0V4m4 2V4m4 2V4m4 2V4" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </span>
    <span class="mobile-bottom-nav__label">Filmmaker</span>
  </button>
  <button type="button" class="mobile-bottom-nav__item js-dashboard-nav" data-target="viewUGC" data-feature="ugc">
    <span class="mobile-bottom-nav__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 5h16M4 12h16M4 19h16" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </span>
    <span class="mobile-bottom-nav__label">UGC Tool</span>
  </button>
  <button type="button" class="mobile-bottom-nav__item js-dashboard-nav" data-target="viewAccount">
    <span class="mobile-bottom-nav__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M10.325 4.317a1 1 0 0 1 .987-.817h1.376a1 1 0 0 1 .987.817l.287 1.436a1 1 0 0 0 .96.804l1.45.055a1 1 0 0 1 .939.734l.345 1.31a1 1 0 0 1-.276.98l-1.07 1.026a1 1 0 0 0-.3.95l.332 1.406a1 1 0 0 1-.6 1.141l-1.307.522a1 1 0 0 0-.62.83l-.135 1.452a1 1 0 0 1-.995.915h-1.38a1 1 0 0 1-.994-.915l-.135-1.452a1 1 0 0 0-.62-.83l-1.307-.522a1 1 0 0 1-.6-1.141l.332-1.406a1 1 0 0 0-.3-.95l-1.07-1.026a1 1 0 0 1-.276-.98l.345-1.31a1 1 0 0 1 .939-.734l1.45-.055a1 1 0 0 0 .96-.804z" stroke-linecap="round" stroke-linejoin="round"></path>
        <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"></circle>
      </svg>
    </span>
    <span class="mobile-bottom-nav__label">Akun</span>
  </button>
</nav>

<div id="assetPreviewModal" class="asset-preview hidden">
  <div class="asset-preview-inner">
    <button type="button" id="assetPreviewClose" class="asset-preview-close">&times;</button>
    <div id="assetPreviewBody" class="asset-preview-body"></div>
    <a id="assetPreviewDownload" class="asset-preview-download" href="#" download target="_blank">Download file</a>
  </div>
</div>

<div class="maintenance-overlay" id="maintenanceOverlay">
  <div class="maintenance-overlay__content">
    <div class="maintenance-overlay__icon">🛠️</div>
    <h2>Website Sedang Maintenance</h2>
    <p id="maintenanceOverlayMessage">Kami sedang melakukan perawatan sistem. Silakan kembali beberapa saat lagi.</p>
    <button type="button" class="maintenance-overlay__logout" id="maintenanceLogoutButton">Keluar</button>
  </div>
</div>

<div class="topup-modal" id="topupModal">
  <div class="topup-modal__dialog">
    <button type="button" class="topup-modal__close" id="topupCloseBtn">&times;</button>
    <h3 class="topup-modal__title">Pilih nominal Top Up</h3>
    <div class="topup-modal__options" id="topupOptions"></div>
    <button type="button" class="topup-badge" id="topupConfirmBtn" disabled>Top Up via WhatsApp</button>
    <div class="topup-modal__hint" id="topupHint">Pilih nominal untuk lanjut ke WhatsApp.</div>
  </div>
</div>

<div class="free-upgrade-modal" id="freeUpgradeModal" aria-hidden="true">
  <div class="free-upgrade-modal__backdrop" data-dismiss="free-upgrade"></div>
  <div class="free-upgrade-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="freeUpgradeTitle">
    <button type="button" class="free-upgrade-modal__close" id="freeUpgradeClose" aria-label="Tutup pop up">&times;</button>
    <div class="free-upgrade-modal__icon">🚀</div>
    <h3 class="free-upgrade-modal__title" id="freeUpgradeTitle">Upgrade ke PRO</h3>
    <p class="free-upgrade-modal__message">Silahkan upgrade ke PRO untuk akses seluruh tools dan menghilangkan watermark.</p>
    <a class="free-upgrade-modal__cta" id="freeUpgradeAction" href="#" target="_blank" rel="noopener">Upgrade via WhatsApp</a>
  </div>
</div>

<script>
  const themeToggle = document.getElementById('themeToggle');
  const profileCard = document.getElementById('profileCard');
  const profileDisplayEl = document.getElementById('profileDisplay');
  const profileUsernameEl = document.getElementById('profileUsername');
  const profileCoinsEl = document.getElementById('profileCoins');
  const profileBadgeEl = document.getElementById('profileBadge');
  const profileVerifiedBadgeEl = document.querySelector('.verified-badge-star');
  const profileProExpiryEl = document.getElementById('profileProExpiry');
  const profileProExpiryValueEl = document.getElementById('profileProExpiryValue');
  const profileAvatarEl = document.getElementById('profileAvatar');
  const profileStatusTextEl = document.getElementById('profileStatusText');
  const profileCardMobile = document.getElementById('profileCardMobile');
  const profileDisplayMobile = document.getElementById('profileDisplayMobile');
  const profileUsernameMobile = document.getElementById('profileUsernameMobile');
  const profileBadgeMobile = document.getElementById('profileBadgeMobile');
  const profileProExpiryMobileEl = document.getElementById('profileProExpiryMobile');
  const profileProExpiryMobileValueEl = document.getElementById('profileProExpiryValueMobile');
  const profileAvatarMobile = document.getElementById('profileAvatarMobile');
  const profileCoinsMobile = document.getElementById('profileCoinsMobile');
  const profileStatusMobileEl = document.getElementById('profileStatusMobile');
  const mobileCoinBanner = document.getElementById('mobileCoinBanner');
  const mobileCoinValue = document.getElementById('mobileCoinValue');
  const mobileCoinTopup = document.getElementById('mobileCoinTopup');
  const avatarForm = document.getElementById('avatarForm');
  const avatarUrlInput = document.getElementById('avatarUrlInput');
  const avatarFileInput = document.getElementById('avatarFileInput');
  const avatarUploadBtn = document.getElementById('avatarUploadBtn');
  const avatarRemoveBtn = document.getElementById('avatarRemoveBtn');
  const avatarFormStatus = document.getElementById('avatarFormStatus');
  const avatarPreviewImage = document.getElementById('avatarPreviewImage');
  const avatarPreviewInitials = document.getElementById('avatarPreviewInitials');
  const passwordForm = document.getElementById('passwordForm');
  const currentPasswordInput = document.getElementById('currentPasswordInput');
  const newPasswordInput = document.getElementById('newPasswordInput');
  const confirmPasswordInput = document.getElementById('confirmPasswordInput');
  const passwordFormStatus = document.getElementById('passwordFormStatus');
  const logoutButton = document.getElementById('logoutButton');
  const maintenanceLogoutButton = document.getElementById('maintenanceLogoutButton');
  const workspace = document.querySelector('.workspace');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const mobileSidebarQuery = typeof window.matchMedia === 'function'
    ? window.matchMedia('(max-width: 960px)')
    : { matches: false };
  const statCoinsEl = document.getElementById('statCoins');
  const statVideosEl = document.getElementById('statVideos');
  const statImagesEl = document.getElementById('statImages');
  const statQueueEl = document.getElementById('statQueue');
  const driveNavButton = document.getElementById('driveNavButton');
  const driveGrid = document.getElementById('driveGrid');
  const driveEmpty = document.getElementById('driveEmpty');
  const driveTypeFilter = document.getElementById('driveTypeFilter');
  const driveSortFilter = document.getElementById('driveSortFilter');
  const driveDateFilter = document.getElementById('driveDateFilter');
  const driveClearDateBtn = document.getElementById('driveClearDate');
  const driveTotalCountEl = document.getElementById('driveTotalCount');
  const driveTypeSummaryEl = document.getElementById('driveTypeSummary');
  const watermarkNotice = document.getElementById('watermarkNotice');
  const driveWatermarkNotice = document.getElementById('driveWatermarkNotice');
  const maintenanceOverlayEl = document.getElementById('maintenanceOverlay');
  const maintenanceOverlayMessageEl = document.getElementById('maintenanceOverlayMessage');
  const freeUpgradeModal = document.getElementById('freeUpgradeModal');
  const freeUpgradeClose = document.getElementById('freeUpgradeClose');
  const freeUpgradeAction = document.getElementById('freeUpgradeAction');
  const freeUpgradeBackdrop = freeUpgradeModal ? freeUpgradeModal.querySelector('.free-upgrade-modal__backdrop') : null;
  const topupModalEl = document.getElementById('topupModal');
  const topupCloseBtn = document.getElementById('topupCloseBtn');
  const topupOptionsEl = document.getElementById('topupOptions');
  const topupConfirmBtn = document.getElementById('topupConfirmBtn');
  const topupHintEl = document.getElementById('topupHint');
  const topupOpeners = [
    document.getElementById('profileTopup'),
    document.getElementById('heroTopup'),
    mobileCoinTopup,
    document.getElementById('profileTopupMobile')
  ].filter(Boolean);
  const restrictionBanner = document.getElementById('accountRestrictionBanner');
  const restrictionMessageEl = document.getElementById('accountRestrictionMessage');
  const dashboardAnnouncementsEl = document.getElementById('dashboardAnnouncements');
  const dashboardAnnouncementList = document.getElementById('dashboardAnnouncementList');
  const filmProgressEl = document.getElementById('filmProgress');
  const filmProgressFill = document.getElementById('filmProgressFill');
  const filmProgressValue = document.getElementById('filmProgressValue');
  const ugcProgressEl = document.getElementById('ugcProgress');
  const ugcProgressFill = document.getElementById('ugcProgressFill');
  const ugcProgressValue = document.getElementById('ugcProgressValue');
  const photoEditLink = document.getElementById('photoEditLink');
  const externalFeatureLinks = [photoEditLink].filter(Boolean);

  const TOPUP_AMOUNTS = [10, 20, 30, 40, 50, 100, 150, 200];
  const TOPUP_WHATSAPP = 'https://wa.me/62818404222';
  const PLATFORM_FEATURE_META = {
    imageGen: { label: 'Image Gen' },
    videoGen: { label: 'Video Gen' },
    audioGen: { label: 'Audio Gen' },
    filmmaker: { label: 'Filmmaker' },
    ugc: { label: 'UGC Tool' },
    flashPhotoEdit: { label: 'Flash Photo Edit' }
  };
  const HUB_FEATURE_KEYS = ['imageGen', 'videoGen'];

  let currentAccount = null;
  let currentTheme = 'dark';
  let platformState = defaultPlatformState();
  let selectedTopupAmount = null;
  let currentAnnouncements = [];
  let freeUpgradeDismissed = false;
  let freeUpgradeAutoShown = false;

  function getSubscriptionTier(account = currentAccount) {
    if (!account) {
      return 'free';
    }
    const source = account.effective_subscription ?? account.subscription;
    if (!source) {
      return 'free';
    }
    const tier = String(source).toLowerCase();
    if (tier === 'pro' && accountProExpired(account)) {
      return 'free';
    }
    return tier || 'free';
  }

  function accountProExpiry(account = currentAccount) {
    if (!account || !account.pro_expires_at) {
      return null;
    }
    const date = new Date(account.pro_expires_at);
    if (Number.isNaN(date.getTime())) {
      return null;
    }
    return date;
  }

  function accountProActive(account = currentAccount) {
    if (!account) {
      return false;
    }
    if (typeof account.pro_active === 'boolean') {
      return account.pro_active;
    }
    const subscription = (account.subscription || '').toLowerCase();
    if (subscription !== 'pro') {
      return false;
    }
    const expiry = accountProExpiry(account);
    if (!expiry) {
      return true;
    }
    return expiry.getTime() >= Date.now();
  }

  function accountProExpired(account = currentAccount) {
    if (!account) {
      return false;
    }
    if (account.pro_expired === true) {
      return true;
    }
    if (typeof account.pro_active === 'boolean') {
      return account.pro_active === false && (account.subscription || '').toLowerCase() === 'pro';
    }
    const subscription = (account.subscription || '').toLowerCase();
    if (subscription !== 'pro') {
      return false;
    }
    const expiry = accountProExpiry(account);
    if (!expiry) {
      return false;
    }
    return expiry.getTime() < Date.now();
  }

  function isProAccount(account = currentAccount) {
    return getSubscriptionTier(account) === 'pro';
  }

  function formatProExpiry(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function buildFreeUpgradeLink(account = currentAccount) {
    const name = account ? (account.display_name || account.username || 'User') : 'User';
    const message = `Halo, saya ${name} ingin upgrade ke PRO`;
    return `https://wa.me/62818404222?text=${encodeURIComponent(message)}`;
  }

  function updateFreeUpgradeLink(account = currentAccount) {
    if (!freeUpgradeAction) return;
    freeUpgradeAction.href = buildFreeUpgradeLink(account);
  }

  function openFreeUpgradeModal(account = currentAccount) {
    if (!freeUpgradeModal) return;
    updateFreeUpgradeLink(account);
    freeUpgradeModal.classList.add('active');
    freeUpgradeModal.setAttribute('aria-hidden', 'false');
  }

  function closeFreeUpgradeModal(options = {}) {
    if (!freeUpgradeModal) return;
    freeUpgradeModal.classList.remove('active');
    freeUpgradeModal.setAttribute('aria-hidden', 'true');
    if (!options.skipDismiss) {
      freeUpgradeDismissed = true;
    }
  }

  function maybeShowFreeUpgrade(account = currentAccount, { forced = false } = {}) {
    if (!freeUpgradeModal || !account) return;
    if (platformState.maintenance && platformState.maintenance.active && !(currentAccount && currentAccount.role === 'admin')) {
      closeFreeUpgradeModal({ skipDismiss: true });
      freeUpgradeDismissed = false;
      freeUpgradeAutoShown = false;
      return;
    }
    if (account && account.role === 'admin') {
      closeFreeUpgradeModal({ skipDismiss: true });
      freeUpgradeDismissed = false;
      freeUpgradeAutoShown = false;
      return;
    }
    if (accountRestricted(account)) {
      closeFreeUpgradeModal({ skipDismiss: true });
      freeUpgradeDismissed = false;
      freeUpgradeAutoShown = false;
      return;
    }
    const tier = getSubscriptionTier(account);
    if (tier !== 'free') {
      const wasActive = freeUpgradeModal.classList.contains('active');
      closeFreeUpgradeModal({ skipDismiss: true });
      if (wasActive || freeUpgradeDismissed) {
        freeUpgradeDismissed = false;
      }
      freeUpgradeAutoShown = false;
      return;
    }
    updateFreeUpgradeLink(account);
    if (forced) {
      openFreeUpgradeModal(account);
      return;
    }
    if (freeUpgradeModal.classList.contains('active')) {
      return;
    }
    if (freeUpgradeDismissed) {
      return;
    }
    if (freeUpgradeAutoShown) {
      return;
    }
    openFreeUpgradeModal(account);
    freeUpgradeAutoShown = true;
  }

  function shouldShowWatermark(account = currentAccount) {
    return !isProAccount(account);
  }

  function updateWatermarkNotice(account = currentAccount) {
    const tier = getSubscriptionTier(account);
    const show = shouldShowWatermark(account);
    document.body.dataset.subscription = tier;
    document.body.dataset.watermark = show ? 'on' : 'off';
    if (watermarkNotice) {
      watermarkNotice.hidden = !show;
    }
    if (driveWatermarkNotice) {
      driveWatermarkNotice.hidden = !show;
    }
  }

  updateWatermarkNotice(null);

  const COIN_COST_STANDARD = 1;
  const COIN_COST_FILM_PER_SCENE = 1;
  const COIN_COST_UGC = 1;
  let jobs = [];
  let modelConfigMap = {};
  let driveItems = [];
  let driveLoaded = false;
  let driveLoading = false;

  function defaultPlatformState() {
    return {
      maintenance: { active: false, message: '', updated_at: null },
      generators: {}
    };
  }

  function normalizePlatformState(platform) {
    const state = defaultPlatformState();
    if (!platform || typeof platform !== 'object') {
      return state;
    }
    if (platform.maintenance && typeof platform.maintenance === 'object') {
      const maint = platform.maintenance;
      state.maintenance = {
        active: !!maint.active,
        message: maint.message ? String(maint.message) : '',
        updated_at: maint.updated_at || null
      };
    }
    const items = Array.isArray(platform.generators)
      ? platform.generators
      : (platform.generators && typeof platform.generators === 'object'
          ? Object.values(platform.generators)
          : []);
    items.forEach(item => {
      if (!item || typeof item !== 'object') return;
      const key = item.key || item.id;
      if (!key) return;
      state.generators[key] = {
        label: item.label || PLATFORM_FEATURE_META[key]?.label || key,
        enabled: item.enabled !== false,
        description: item.description || '',
        updated_at: item.updated_at || null
      };
    });
    return state;
  }

  function getFeatureLabel(featureKey) {
    if (!featureKey) return 'Generator';
    if (platformState.generators[featureKey] && platformState.generators[featureKey].label) {
      return platformState.generators[featureKey].label;
    }
    return PLATFORM_FEATURE_META[featureKey]?.label || featureKey;
  }

  function featureAvailableForCurrentUser(featureKey) {
    if (!featureKey) return true;
    if (currentAccount && currentAccount.role === 'admin') {
      return true;
    }
    if (accountRestricted()) {
      return false;
    }
    if (platformState.maintenance && platformState.maintenance.active) {
      return false;
    }
    const tier = getSubscriptionTier();
    if (tier === 'free' && featureKey !== 'ugc') {
      return false;
    }
    const entry = platformState.generators[featureKey];
    if (!entry) return true;
    return entry.enabled !== false;
  }

  function showFeatureLockedMessage(featureKey) {
    if (accountRestricted()) {
      showRestrictionNotice();
      return;
    }
    if (platformState.maintenance && platformState.maintenance.active && !(currentAccount && currentAccount.role === 'admin')) {
      const maintenanceMessage = platformState.maintenance.message || 'Website sedang maintenance. Silakan coba lagi nanti.';
      alert(`🛠️ ${maintenanceMessage}`);
      return;
    }
    if (getSubscriptionTier() === 'free' && featureKey !== 'ugc') {
      maybeShowFreeUpgrade(currentAccount, { forced: true });
      return;
    }
    const label = getFeatureLabel(featureKey);
    const message = platformState.maintenance.message || 'Generator sedang maintenance. Silakan coba lagi nanti.';
    alert(`🔒 ${label} sedang dikunci oleh admin.\n${message}`);
  }

  function clampPercent(value) {
    if (!Number.isFinite(value)) return 0;
    if (value < 0) return 0;
    if (value > 100) return 100;
    return Math.round(value);
  }

  function summarizeTaskProgress(items, { isStarted, isCompleted }) {
    if (!Array.isArray(items) || !items.length) {
      return { total: 0, started: 0, completed: 0, percent: 0 };
    }
    let started = 0;
    let completed = 0;
    items.forEach(item => {
      if (isStarted(item)) started += 1;
      if (isCompleted(item)) completed += 1;
    });
    const total = items.length;
    const inProgress = Math.max(0, started - completed);
    const weighted = completed + inProgress * 0.5;
    const percent = clampPercent((weighted / total) * 100);
    return { total, started, completed, percent };
  }

  function setInlineProgressState(wrapper, fill, valueEl, percent, show, label) {
    const pct = clampPercent(percent);
    if (wrapper) wrapper.classList.toggle('active', !!show);
    if (fill) fill.style.width = pct + '%';
    if (valueEl) valueEl.textContent = label || (pct + '%');
  }

  function ensureAbsoluteUrl(url) {
    if (!url || typeof url !== 'string') {
      return '';
    }
    try {
      return new URL(url, window.location.origin).href;
    } catch (err) {
      return url;
    }
  }

  function showInlineStatus(el, message, mode) {
    if (!el) return;
    el.textContent = message || '';
    el.classList.remove('ok', 'err', 'progress');
    if (!message) {
      el.style.display = 'none';
      return;
    }
    if (mode) {
      el.classList.add(mode);
    }
    el.style.display = 'block';
  }

  function formatAnnouncementTime(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString('id-ID', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function renderDashboardAnnouncements(items) {
    currentAnnouncements = Array.isArray(items) ? items.slice() : [];
    if (!dashboardAnnouncementList) {
      if (dashboardAnnouncementsEl) dashboardAnnouncementsEl.hidden = true;
      return;
    }

    dashboardAnnouncementList.innerHTML = '';

    if (!currentAnnouncements.length) {
      const empty = document.createElement('div');
      empty.className = 'announcement-empty';
      empty.textContent = 'Belum ada informasi terbaru.';
      dashboardAnnouncementList.appendChild(empty);
      if (dashboardAnnouncementsEl) dashboardAnnouncementsEl.hidden = true;
      return;
    }

    currentAnnouncements.forEach(item => {
      if (!item || typeof item !== 'object') return;
      const wrapper = document.createElement('div');
      wrapper.className = 'announcement-item';

      const header = document.createElement('header');
      const title = document.createElement('h3');
      title.textContent = item.title || 'Informasi';
      header.appendChild(title);

      const time = document.createElement('time');
      time.dateTime = item.published_at || '';
      time.textContent = formatAnnouncementTime(item.published_at);
      header.appendChild(time);
      wrapper.appendChild(header);

      if (item.description) {
        const desc = document.createElement('p');
        desc.textContent = item.description;
        wrapper.appendChild(desc);
      }

      dashboardAnnouncementList.appendChild(wrapper);
    });

    if (dashboardAnnouncementsEl) {
      dashboardAnnouncementsEl.hidden = false;
    }
  }

  function accountRestricted(account = currentAccount) {
    if (!account || typeof account !== 'object') {
      return false;
    }
    if ((account.role || 'user') === 'admin') {
      return false;
    }
    if (Object.prototype.hasOwnProperty.call(account, 'restricted')) {
      if (account.restricted) {
        return true;
      }
    }
    return !!account.is_banned || !!account.is_blocked;
  }

  function showRestrictionNotice(message) {
    let info = message || 'Akun Anda sedang dibatasi oleh admin. Hubungi admin untuk bantuan lebih lanjut.';
    if (!message && currentAccount) {
      if (currentAccount.is_banned || currentAccount.is_blocked) {
        info = 'Akun sedang di banned silahkan mengajukan banding ke customer service.';
      }
    }
    alert(`⚠️ ${info}`);
  }

  function setDisabledState(element, disabled) {
    if (!element) return;
    const value = !!disabled;
    if (typeof element.disabled === 'boolean') {
      element.disabled = value;
    } else if (value) {
      element.setAttribute('aria-disabled', 'true');
    } else {
      element.removeAttribute('aria-disabled');
    }
    element.classList.toggle('is-disabled', value);
  }

  function applyAccountRestrictions(account = currentAccount) {
    const restricted = accountRestricted(account);
    if (restrictionBanner) {
      restrictionBanner.hidden = !restricted;
    }
    if (restrictionMessageEl) {
      let message = 'Akun Anda sedang dibatasi oleh admin. Hubungi admin untuk reaktivasi.';
      if (account && account.is_banned) {
        message = 'Akun sedang di banned silahkan mengajukan banding ke customer service.';
      } else if (account && account.is_blocked) {
        message = 'Akun sedang di banned silahkan mengajukan banding ke customer service.';
      }
      restrictionMessageEl.textContent = message;
    }

    setDisabledState(submitBtn, restricted);
    setDisabledState(filmGenerateBtn, restricted);
    setDisabledState(ugcGenerateBtn, restricted);
    setDisabledState(geminiTextSubmitBtn, restricted);
    setDisabledState(geminiSpeechSubmitBtn, restricted);
    setDisabledState(geminiVeoSubmitBtn, restricted);
    refreshTopupConfirm();
  }

  function applyAvatarToElement(el, avatarUrl, initials) {
    if (!el) return;
    const hasImage = avatarUrl && /^https?:\/\//i.test(avatarUrl);
    if (hasImage) {
      el.style.backgroundImage = `url('${avatarUrl.replace(/'/g, "\\'")}')`;
      el.classList.add('profile-avatar--image');
      el.textContent = '';
    } else {
      el.style.backgroundImage = 'none';
      el.classList.remove('profile-avatar--image');
      el.textContent = initials || '';
    }
  }

  function updateAvatarPreview(avatarUrl, initials) {
    if (avatarPreviewImage) {
      const valid = avatarUrl && /^https?:\/\//i.test(avatarUrl);
      if (valid) {
        avatarPreviewImage.src = avatarUrl;
        avatarPreviewImage.style.display = 'block';
      } else {
        avatarPreviewImage.removeAttribute('src');
        avatarPreviewImage.style.display = 'none';
      }
    }
    if (avatarPreviewInitials) {
      avatarPreviewInitials.textContent = initials || '';
      avatarPreviewInitials.style.display = avatarPreviewImage && avatarPreviewImage.style.display === 'block' ? 'none' : 'flex';
    }
  }

  function pickErrorMessage(err) {
    if (!err) return 'Terjadi kesalahan.';
    if (typeof err === 'string') return err;
    if (Array.isArray(err)) {
      return err.find(item => typeof item === 'string' && item.trim() !== '') || 'Terjadi kesalahan.';
    }
    if (typeof err === 'object') {
      const values = Object.values(err);
      for (const value of values) {
        if (!value) continue;
        if (typeof value === 'string' && value.trim() !== '') {
          return value;
        }
        if (Array.isArray(value)) {
          const found = value.find(item => typeof item === 'string' && item.trim() !== '');
          if (found) return found;
        }
      }
    }
    return 'Terjadi kesalahan.';
  }

  function updateNavAvailability() {
    if (navButtons) {
      navButtons.forEach(btn => {
        const featureKey = btn.dataset.feature;
        if (!featureKey) {
          btn.classList.remove('locked');
          return;
        }
        const locked = !featureAvailableForCurrentUser(featureKey);
        btn.classList.toggle('locked', locked);
        if (typeof btn.disabled === 'boolean') {
          btn.disabled = locked;
        }
        if (locked) {
          btn.setAttribute('aria-disabled', 'true');
          let title = `🔒 ${getFeatureLabel(featureKey)} dikunci oleh admin.`;
          if (platformState.maintenance && platformState.maintenance.active && !(currentAccount && currentAccount.role === 'admin')) {
            title = `🛠️ ${platformState.maintenance.message || 'Website sedang maintenance. Silakan coba lagi nanti.'}`;
          } else if (getSubscriptionTier() === 'free' && featureKey !== 'ugc') {
            title = '🔓 Upgrade ke PRO untuk membuka seluruh tools.';
          }
          btn.title = title;
        } else {
          btn.removeAttribute('aria-disabled');
          btn.removeAttribute('title');
        }
      });
    }

    if (externalFeatureLinks.length) {
      externalFeatureLinks.forEach(link => {
        const featureKey = link.dataset.feature;
        if (!featureKey) {
          link.classList.remove('locked');
          return;
        }
        const locked = !featureAvailableForCurrentUser(featureKey);
        link.classList.toggle('locked', locked);
        if (locked) {
          if (!link.dataset.href) {
            const originalHref = link.getAttribute('href') || '';
            if (originalHref) {
              link.dataset.href = originalHref;
            }
          }
          link.removeAttribute('href');
          link.setAttribute('aria-disabled', 'true');
          link.setAttribute('tabindex', '-1');
          let title = `🔒 ${getFeatureLabel(featureKey)} dikunci oleh admin.`;
          if (platformState.maintenance && platformState.maintenance.active && !(currentAccount && currentAccount.role === 'admin')) {
            title = `🛠️ ${platformState.maintenance.message || 'Website sedang maintenance. Silakan coba lagi nanti.'}`;
          } else if (getSubscriptionTier() === 'free' && featureKey !== 'ugc') {
            title = '🔓 Upgrade ke PRO untuk membuka seluruh tools.';
          }
          link.title = title;
        } else {
          if (link.dataset.href) {
            link.setAttribute('href', link.dataset.href);
          }
          link.removeAttribute('aria-disabled');
          link.removeAttribute('tabindex');
          link.removeAttribute('title');
        }
      });
    }
  }

  function updateFeatureTabsAvailability() {
    if (!featureTabs.length) return;
    featureTabs.forEach(btn => {
      const key = btn.dataset.feature;
      if (!key) return;
      const locked = !featureAvailableForCurrentUser(key);
      btn.classList.toggle('locked', locked);
      if (typeof btn.disabled === 'boolean') {
        btn.disabled = locked;
      }
      if (locked) {
        btn.setAttribute('aria-disabled', 'true');
        let title = `🔒 ${getFeatureLabel(key)} dikunci oleh admin.`;
        if (platformState.maintenance && platformState.maintenance.active && !(currentAccount && currentAccount.role === 'admin')) {
          title = `🛠️ ${platformState.maintenance.message || 'Website sedang maintenance. Silakan coba lagi nanti.'}`;
        } else if (getSubscriptionTier() === 'free' && key !== 'ugc') {
          title = '🔓 Upgrade ke PRO untuk membuka seluruh tools.';
        }
        btn.title = title;
      } else {
        btn.removeAttribute('aria-disabled');
        btn.removeAttribute('title');
      }
    });
  }

  function updateMaintenanceOverlay() {
    if (!maintenanceOverlayEl) return;
    const isLocked = platformState.maintenance.active && !(currentAccount && currentAccount.role === 'admin');
    maintenanceOverlayEl.classList.toggle('active', isLocked);
    if (isLocked) {
      if (maintenanceOverlayMessageEl) {
        maintenanceOverlayMessageEl.textContent = platformState.maintenance.message || 'Website sedang maintenance. Kami segera kembali!';
      }
      document.body.classList.add('maintenance-active');
    } else {
      document.body.classList.remove('maintenance-active');
    }
  }

  function applyPlatformState(newState) {
    platformState = normalizePlatformState(newState);
    updateNavAvailability();
    updateFeatureTabsAvailability();
    updateMaintenanceOverlay();
    if (!featureAvailableForCurrentUser(currentFeature)) {
      const fallback = HUB_FEATURE_KEYS.find(key => featureAvailableForCurrentUser(key));
      if (fallback) {
        currentFeature = fallback;
        if (viewHubSection && viewHubSection.style.display !== 'none') {
          setFeature(fallback);
        }
      } else if (viewHubSection && viewHubSection.style.display !== 'none') {
        showView('viewDashboard');
      }
    }

    if (viewFilmSection && viewFilmSection.style.display !== 'none' && !featureAvailableForCurrentUser('filmmaker')) {
      showFeatureLockedMessage('filmmaker');
      showView('viewDashboard');
    }

    if (viewUGCSection && viewUGCSection.style.display !== 'none' && !featureAvailableForCurrentUser('ugc')) {
      showFeatureLockedMessage('ugc');
      showView('viewDashboard');
    }
    if (viewAudioSection && viewAudioSection.style.display !== 'none' && !featureAvailableForCurrentUser('audioGen')) {
      showFeatureLockedMessage('audioGen');
      showView('viewDashboard');
    }

    applyAccountRestrictions(currentAccount);
    maybeShowFreeUpgrade(currentAccount);
  }

  function getWhatsappMessage(amount) {
    const username = currentAccount && currentAccount.username
      ? `@${currentAccount.username}`
      : (currentAccount && currentAccount.display_name) ? currentAccount.display_name : 'user';
    return `Halo Admin, saya ${username}. Saya ingin top up ${amount} credit.`;
  }

  function refreshTopupConfirm() {
    if (!topupConfirmBtn) return;
    const disabled = !selectedTopupAmount || accountRestricted();
    setDisabledState(topupConfirmBtn, disabled);
    if (topupHintEl) {
      if (disabled && !selectedTopupAmount) {
        topupHintEl.textContent = 'Pilih nominal untuk lanjut ke WhatsApp.';
      } else if (disabled) {
        topupHintEl.textContent = 'Akun sedang dibatasi. Hubungi admin untuk top up.';
      } else {
        topupHintEl.textContent = `Konfirmasi top up ${selectedTopupAmount} credit via WhatsApp.`;
      }
    }
  }

  function handleTopupOption(amount) {
    selectedTopupAmount = amount;
    if (topupOptionsEl) {
      topupOptionsEl.querySelectorAll('.topup-option').forEach(btn => {
        const value = Number(btn.dataset.amount || '0');
        btn.classList.toggle('active', value === amount);
      });
    }
    refreshTopupConfirm();
  }

  function buildTopupOptions() {
    if (!topupOptionsEl || !TOPUP_AMOUNTS.length) return;
    if (topupOptionsEl.childElementCount) return;
    TOPUP_AMOUNTS.forEach(amount => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'topup-option';
      btn.dataset.amount = String(amount);
      btn.textContent = `${amount} Credit`;
      btn.addEventListener('click', () => handleTopupOption(amount));
      topupOptionsEl.appendChild(btn);
    });
  }

  function resetTopupSelection() {
    selectedTopupAmount = null;
    if (topupOptionsEl) {
      topupOptionsEl.querySelectorAll('.topup-option').forEach(btn => btn.classList.remove('active'));
    }
  }

  function openTopupModal() {
    if (accountRestricted()) {
      showRestrictionNotice('Top up tidak tersedia karena akun Anda sedang dibatasi.');
      return;
    }
    buildTopupOptions();
    resetTopupSelection();
    refreshTopupConfirm();
    if (topupModalEl) {
      topupModalEl.classList.add('show');
    }
    document.body.classList.add('modal-open');
  }

  function closeTopupModal() {
    if (topupModalEl) {
      topupModalEl.classList.remove('show');
    }
    const previewEl = document.querySelector('.asset-preview');
    const activePreview = previewEl && !previewEl.classList.contains('hidden');
    if (!activePreview) {
      document.body.classList.remove('modal-open');
    }
    resetTopupSelection();
    refreshTopupConfirm();
  }

  function launchTopupWhatsapp() {
    if (!selectedTopupAmount) return;
    if (accountRestricted()) {
      showRestrictionNotice('Tidak dapat melanjutkan top up karena akun dibatasi.');
      return;
    }
    const message = getWhatsappMessage(selectedTopupAmount);
    const url = `${TOPUP_WHATSAPP}?text=${encodeURIComponent(message)}`;
    window.open(url, '_blank', 'noopener');
    closeTopupModal();
  }

  function closeSidebarOnMobile() {
    if (!workspace || !mobileSidebarQuery.matches) return;
    if (workspace.classList.contains('sidebar-open')) {
      workspace.classList.remove('sidebar-open');
      if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
    }
  }

  function syncSidebarForViewport() {
    if (!workspace) return;
    if (mobileSidebarQuery.matches) {
      workspace.classList.remove('sidebar-collapsed');
      workspace.classList.remove('sidebar-open');
      if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
    } else {
      workspace.classList.add('sidebar-open');
      const collapsed = workspace.classList.contains('sidebar-collapsed');
      if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
    }
  }

  syncSidebarForViewport();
  if (mobileSidebarQuery && typeof mobileSidebarQuery.addEventListener === 'function') {
    mobileSidebarQuery.addEventListener('change', syncSidebarForViewport);
  } else if (mobileSidebarQuery && typeof mobileSidebarQuery.addListener === 'function') {
    mobileSidebarQuery.addListener(syncSidebarForViewport);
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      if (!workspace) return;
      if (mobileSidebarQuery.matches) {
        const isOpen = workspace.classList.toggle('sidebar-open');
        sidebarToggle.setAttribute('aria-expanded', String(isOpen));
      } else {
        const isCollapsed = workspace.classList.toggle('sidebar-collapsed');
        sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
      }
    });
  }

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebarOnMobile);
  }

  topupOpeners.forEach(btn => {
    btn.addEventListener('click', () => {
      if (accountRestricted()) {
        showRestrictionNotice('Top up tidak tersedia karena akun Anda sedang dibatasi.');
        return;
      }
      openTopupModal();
    });
  });

  if (topupCloseBtn) {
    topupCloseBtn.addEventListener('click', () => {
      closeTopupModal();
    });
  }

  if (topupConfirmBtn) {
    topupConfirmBtn.addEventListener('click', () => {
      launchTopupWhatsapp();
    });
  }

  if (topupModalEl) {
    topupModalEl.addEventListener('click', event => {
      if (event.target === topupModalEl) {
        closeTopupModal();
      }
    });
  }

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && topupModalEl && topupModalEl.classList.contains('show')) {
      closeTopupModal();
    }
  });

  function updateDashboardStats() {
    if (statCoinsEl) {
      const coins = currentAccount && Number.isFinite(Number(currentAccount.coins))
        ? Number(currentAccount.coins)
        : 0;
      statCoinsEl.textContent = coins.toLocaleString('id-ID');
    }

    const totalJobs = Array.isArray(jobs) ? jobs : [];
    const queueCount = totalJobs.filter(job => !finalStatus(job.status)).length;
    const completed = totalJobs.filter(job => finalStatus(job.status));

    const videoCount = completed.filter(job => {
      const cfg = modelConfigMap[job.modelId];
      const type = job.type || (cfg && cfg.type);
      return type === 'video';
    }).length;

    const imageCount = completed.filter(job => {
      const cfg = modelConfigMap[job.modelId];
      const type = job.type || (cfg && cfg.type);
      return type === 'image' || type === 'edit';
    }).length;

    if (statVideosEl) statVideosEl.textContent = videoCount.toLocaleString('id-ID');
    if (statImagesEl) statImagesEl.textContent = imageCount.toLocaleString('id-ID');
    if (statQueueEl) statQueueEl.textContent = queueCount.toLocaleString('id-ID');
  }

  function setDriveAccess(enabled) {
    if (!driveNavButton) return;
    driveNavButton.classList.toggle('hidden', !enabled);
    driveNavButton.disabled = !enabled;
  }

  function jobOutputType(job) {
    if (!job) return 'image';
    const cfg = modelConfigMap[job.modelId];
    const type = job.type || (cfg && cfg.type);
    return type === 'video' ? 'video' : 'image';
  }

  function driveModelLabel(modelId) {
    if (!modelId) return 'Hasil Generate';
    if (modelId === 'upload') return 'Unggahan Manual';
    const cfg = modelConfigMap[modelId];
    if (cfg && cfg.label) return cfg.label;
    return String(modelId).toUpperCase();
  }

  function formatDriveDate(iso) {
    if (!iso) return '-';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
  }

  function updateDriveSummary() {
    const total = Array.isArray(driveItems) ? driveItems.length : 0;
    const photos = Array.isArray(driveItems) ? driveItems.filter(item => (item.type || 'image') !== 'video').length : 0;
    const videos = total - photos;
    if (driveTotalCountEl) {
      driveTotalCountEl.textContent = `${total.toLocaleString('id-ID')} file`;
    }
    if (driveTypeSummaryEl) {
      driveTypeSummaryEl.textContent = `${photos.toLocaleString('id-ID')} foto • ${videos.toLocaleString('id-ID')} video`;
    }
  }

  function renderDriveItems() {
    if (!driveGrid || !driveEmpty) {
      return;
    }

    const typeValue = driveTypeFilter ? driveTypeFilter.value : 'all';
    const sortValue = driveSortFilter ? driveSortFilter.value : 'newest';
    const dateValue = driveDateFilter ? driveDateFilter.value : '';

    let items = Array.isArray(driveItems) ? driveItems.slice() : [];

    if (typeValue === 'image' || typeValue === 'video') {
      items = items.filter(item => (item.type || 'image') === typeValue);
    }

    if (dateValue) {
      items = items.filter(item => {
        const iso = item.created_at || item.createdAt || '';
        return typeof iso === 'string' && iso.slice(0, 10) === dateValue;
      });
    }

    items.sort((a, b) => {
      const aTime = new Date(a.created_at || a.createdAt || 0).getTime();
      const bTime = new Date(b.created_at || b.createdAt || 0).getTime();
      if (sortValue === 'oldest') {
        return aTime - bTime;
      }
      return bTime - aTime;
    });

    driveEmpty.style.display = items.length ? 'none' : 'block';
    driveGrid.innerHTML = '';

    items.forEach(item => {
      if (!item || !item.url) return;
      const card = document.createElement('div');
      card.className = 'drive-card';

      const thumb = document.createElement('div');
      thumb.className = 'drive-thumb';
      const type = (item.type || 'image') === 'video' ? 'video' : 'image';
      thumb.dataset.type = type;

      const badge = document.createElement('span');
      badge.className = 'drive-type-badge';
      badge.textContent = type === 'video' ? 'VIDEO' : 'FOTO';
      thumb.appendChild(badge);

      if (type === 'video') {
        const video = document.createElement('video');
        video.src = (item.thumbnail_url && /^https?:/i.test(item.thumbnail_url)) ? item.thumbnail_url : item.url;
        video.muted = true;
        video.loop = true;
        video.autoplay = true;
        video.playsInline = true;
        thumb.appendChild(video);
      } else {
        const img = document.createElement('img');
        img.src = (item.thumbnail_url && /^https?:/i.test(item.thumbnail_url)) ? item.thumbnail_url : item.url;
        img.alt = 'Drive item';
        thumb.appendChild(img);
      }
      const thumbWatermark = document.createElement('div');
      thumbWatermark.className = 'watermark-overlay';
      thumb.appendChild(thumbWatermark);

      thumb.addEventListener('click', () => {
        openAssetPreview(item.url, type);
      });

      const footer = document.createElement('div');
      footer.className = 'drive-card-footer';

      const title = document.createElement('strong');
      title.textContent = driveModelLabel(item.model);
      footer.appendChild(title);

      const meta = document.createElement('span');
      meta.textContent = formatDriveDate(item.created_at || item.createdAt);
      footer.appendChild(meta);

      if (item.prompt) {
        const promptEl = document.createElement('span');
        promptEl.textContent = item.prompt;
        footer.appendChild(promptEl);
      }

      const actions = document.createElement('div');
      actions.className = 'drive-actions';

      const previewBtn = document.createElement('button');
      previewBtn.type = 'button';
      previewBtn.textContent = 'Preview';
      previewBtn.addEventListener('click', () => openAssetPreview(item.url, type));
      actions.appendChild(previewBtn);

      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.textContent = 'Copy Link';
      copyBtn.addEventListener('click', () => copyDriveLink(item, copyBtn));
      actions.appendChild(copyBtn);

      const downloadLink = document.createElement('a');
      downloadLink.href = item.url;
      downloadLink.target = '_blank';
      downloadLink.rel = 'noopener';
      downloadLink.textContent = 'Download';
      downloadLink.setAttribute('download', '');
      actions.appendChild(downloadLink);

      const deleteBtn = document.createElement('button');
      deleteBtn.type = 'button';
      deleteBtn.className = 'danger';
      deleteBtn.textContent = 'Hapus';
      deleteBtn.addEventListener('click', () => deleteDriveEntry(item, deleteBtn));
      actions.appendChild(deleteBtn);

      card.appendChild(thumb);
      card.appendChild(footer);
      card.appendChild(actions);
      driveGrid.appendChild(card);
    });

    updateDriveSummary();
  }

  async function loadDriveItems(force = false) {
    if (!currentAccount || driveLoading) {
      return;
    }
    if (driveLoaded && !force) {
      renderDriveItems();
      return;
    }

    driveLoading = true;
    try {
      const res = await fetch(DRIVE_ENDPOINT, {
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error((data && data.error) || 'Gagal memuat drive.');
      }
      driveItems = Array.isArray(data.data && data.data.items) ? data.data.items : [];
      driveLoaded = true;
      renderDriveItems();
    } catch (err) {
      console.warn('Gagal memuat drive:', err);
    } finally {
      driveLoading = false;
    }
  }

  async function persistDriveItems(items) {
    if (!items || !items.length) {
      return null;
    }

    const res = await fetch(DRIVE_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ items })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error((data && data.error) || 'Gagal menyimpan drive.');
    }

    driveItems = Array.isArray(data.data && data.data.items) ? data.data.items : [];
    driveLoaded = true;
    renderDriveItems();
    return driveItems;
  }

  async function saveSceneToDrive(scene, button) {
    if (!scene || !scene.url) {
      alert('Scene belum memiliki gambar.');
      return;
    }

    const url = ensureAbsoluteUrl(scene.url);
    if (!/^https?:\/\//i.test(url)) {
      alert('URL scene belum siap disimpan.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'Menyimpan…';
    }

    const entry = {
      type: 'image',
      url,
      model: scene.modelId || 'filmmaker',
      prompt: scene.prompt || null,
      created_at: nowIso()
    };

    try {
      await persistDriveItems([entry]);
      if (button) {
        button.textContent = 'Tersimpan ✓';
        setTimeout(() => {
          button.disabled = false;
          button.textContent = 'Simpan ke Drive';
        }, 1800);
      }
    } catch (err) {
      if (button) {
        button.disabled = false;
        button.textContent = 'Simpan ke Drive';
      }
      alert('Gagal menyimpan scene ke drive: ' + (err.message || err));
    }
  }

  async function saveUgcImageToDrive(item, button) {
    if (!item || !item.imageUrl) {
      alert('Gambar UGC belum siap.');
      return;
    }

    const url = ensureAbsoluteUrl(item.imageUrl || item.remoteUrl);
    if (!/^https?:\/\//i.test(url)) {
      alert('URL gambar tidak valid.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'Menyimpan…';
    }

    const entry = {
      type: 'image',
      url,
      model: 'ugc-image',
      prompt: item.prompt || null,
      created_at: nowIso()
    };

    try {
      await persistDriveItems([entry]);
      if (button) {
        button.textContent = 'Tersimpan ✓';
        setTimeout(() => {
          button.disabled = false;
          button.textContent = 'Simpan ke Drive';
        }, 1800);
      }
    } catch (err) {
      if (button) {
        button.disabled = false;
        button.textContent = 'Simpan ke Drive';
      }
      alert('Gagal menyimpan gambar ke drive: ' + (err.message || err));
    }
  }

  async function saveUgcVideoToDrive(item, button) {
    if (!item || !item.videoUrl) {
      alert('Video UGC belum siap.');
      return;
    }

    const url = ensureAbsoluteUrl(item.videoUrl);
    if (!/^https?:\/\//i.test(url)) {
      alert('URL video tidak valid.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'Menyimpan…';
    }

    const entry = {
      type: 'video',
      url,
      model: 'ugc-video',
      prompt: item.videoPrompt || item.prompt || null,
      created_at: nowIso()
    };

    const thumb = ensureAbsoluteUrl(item.imageUrl || item.remoteUrl || '');
    if (/^https?:\/\//i.test(thumb)) {
      entry.thumbnail_url = thumb;
    }

    try {
      await persistDriveItems([entry]);
      if (button) {
        button.textContent = 'Tersimpan ✓';
        setTimeout(() => {
          button.disabled = false;
          button.textContent = 'Simpan Video';
        }, 1800);
      }
    } catch (err) {
      if (button) {
        button.disabled = false;
        button.textContent = 'Simpan Video';
      }
      alert('Gagal menyimpan video ke drive: ' + (err.message || err));
    }
  }

  async function deleteDriveEntry(item, button) {
    if (!item) return;
    const payload = {};
    if (item.id) payload.id = item.id;
    if (item.url) payload.url = item.url;
    if (item.storage_path) payload.storage_path = item.storage_path;
    if (!payload.id && !payload.url && !payload.storage_path) {
      alert('Item drive tidak valid.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'Menghapus…';
    }

    try {
      const res = await fetch(DRIVE_DELETE_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(pickErrorMessage(data && data.error));
      }
      driveItems = Array.isArray(data.data && data.data.items) ? data.data.items : [];
      driveLoaded = true;
      renderDriveItems();
    } catch (err) {
      alert('Gagal menghapus file: ' + (err.message || err));
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = 'Hapus';
      }
    }
  }

  async function copyDriveLink(item, button) {
    if (!item || !item.url) {
      alert('Link tidak tersedia.');
      return;
    }

    const link = ensureAbsoluteUrl(item.url);
    const originalText = button ? button.textContent : '';

    const fallbackCopy = text => {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'absolute';
      textarea.style.left = '-9999px';
      document.body.appendChild(textarea);
      textarea.select();
      try {
        document.execCommand('copy');
      } finally {
        document.body.removeChild(textarea);
      }
    };

    if (button) {
      button.disabled = true;
      button.textContent = 'Menyalin…';
    }

    try {
      if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        await navigator.clipboard.writeText(link);
      } else {
        fallbackCopy(link);
      }

      if (button) {
        button.textContent = 'Tersalin!';
        setTimeout(() => {
          button.disabled = false;
          button.textContent = 'Copy Link';
        }, 1600);
      } else {
        alert('Link berhasil disalin.');
      }
    } catch (err) {
      if (button) {
        button.disabled = false;
        button.textContent = originalText || 'Copy Link';
      }
      alert('Gagal menyalin link: ' + (err && err.message ? err.message : err));
    }
  }

  async function syncJobToDrive(job) {
    if (!job || !currentAccount) return;
    if (!finalStatus(job.status)) return;
    if (job.driveSynced) return;

    const type = jobOutputType(job);
    const urls = new Set();
    const payload = [];

    const pushUrl = url => {
      if (typeof url !== 'string') return;
      if (!/^https?:\/\//i.test(url)) return;
      if (urls.has(url)) return;
      urls.add(url);
      payload.push({
        type,
        url,
        model: job.modelId || null,
        prompt: job.prompt || null,
        created_at: job.updatedAt || job.createdAt || nowIso()
      });
    };

    if (Array.isArray(job.generated)) {
      job.generated.forEach(pushUrl);
    }
    pushUrl(job.extraUrl || null);

    if (!payload.length) {
      return;
    }

    try {
      await persistDriveItems(payload);
      job.driveSynced = nowIso();
      saveJobs();
    } catch (err) {
      console.warn('Gagal sinkron drive:', err);
    }
  }

  function applyTheme(theme) {
    currentTheme = 'dark';
    document.body.dataset.theme = 'dark';
    if (themeToggle) {
      themeToggle.textContent = '🌙';
    }
  }

  function initialsFrom(name, username) {
    const source = name && name.trim() ? name : username || '';
    const words = source.split(/\s+/).filter(Boolean);
    if (!words.length) {
      return username ? username.slice(0, 2).toUpperCase() : 'FM';
    }
    const letters = words.slice(0, 2).map(w => w[0]);
    return letters.join('').toUpperCase();
  }

  function currentAccountInitials() {
    if (currentAccount) {
      return initialsFrom(currentAccount.display_name || currentAccount.username || 'User', currentAccount.username);
    }
    return initialsFrom('User', 'user');
  }

  function formatSubscriptionLabel(subscription) {
    if (!subscription) return 'FREE';
    return String(subscription).toUpperCase();
  }

  function updateProExpiryDisplay(container, valueEl, iso, expired) {
    if (!container) return;
    if (!iso) {
      container.hidden = true;
      container.classList.remove('expired');
      container.classList.remove('profile-expiry--active');
      if (valueEl) valueEl.textContent = '';
      return;
    }
    const formatted = formatProExpiry(iso);
    container.hidden = false;
    container.classList.toggle('expired', !!expired);
    container.classList.toggle('profile-expiry--active', !expired);
    if (valueEl) {
      valueEl.textContent = formatted ? (expired ? `${formatted} · sudah berakhir` : formatted) : '-';
    }
  }

  function updateProfileCard(account) {
    updateWatermarkNotice(account);
    if (!profileCard || !account) return;
    const display = account.display_name || account.username || 'User';
    const username = account.username ? `@${account.username}` : '';
    const coins = Number.isFinite(Number(account.coins)) ? Number(account.coins) : 0;
    const effectiveTier = getSubscriptionTier(account);
    const originalSubscription = account.subscription || effectiveTier;
    const proExpired = accountProExpired(account);
    const showProExpiry = (account.subscription || '').toLowerCase() === 'pro' && account.pro_expires_at;
    const isVerified = typeof account.is_verified === 'boolean'
      ? account.is_verified
      : effectiveTier !== 'free';
    let badgeLabel = formatSubscriptionLabel(originalSubscription);
    if ((account.subscription || '').toLowerCase() === 'pro') {
      badgeLabel = proExpired ? 'PRO (EXPIRED)' : 'PRO';
    } else if ((account.subscription || '').toLowerCase() !== effectiveTier) {
      badgeLabel = formatSubscriptionLabel(effectiveTier);
    }
    const isBanned = !!account.is_banned;
    const isBlocked = !!account.is_blocked;
    const formattedCoins = coins.toLocaleString('id-ID');
    const initials = initialsFrom(display, account.username);

    if (profileDisplayEl) profileDisplayEl.textContent = display;
    if (profileUsernameEl) profileUsernameEl.textContent = username;
    if (profileCoinsEl) profileCoinsEl.textContent = formattedCoins;
    if (profileBadgeEl) profileBadgeEl.textContent = badgeLabel;
    applyAvatarToElement(profileAvatarEl, account.avatar_url, initials);

    if (profileDisplayMobile) profileDisplayMobile.textContent = display;
    if (profileUsernameMobile) profileUsernameMobile.textContent = username;
    if (profileBadgeMobile) profileBadgeMobile.textContent = badgeLabel;
    if (profileCoinsMobile) profileCoinsMobile.textContent = formattedCoins;
    applyAvatarToElement(profileAvatarMobile, account.avatar_url, initials);
    if (mobileCoinValue) mobileCoinValue.textContent = formattedCoins;
    if (avatarUrlInput) {
      avatarUrlInput.value = account.avatar_url || '';
    }
    updateAvatarPreview(account.avatar_url || '', initials);

    const badgeElements = [profileBadgeEl, profileBadgeMobile].filter(Boolean);
    badgeElements.forEach(badge => {
      badge.classList.toggle('profile-badge--premium', isVerified);
      badge.classList.toggle('profile-badge--free', !isVerified);
    });

    if (profileVerifiedBadgeEl) {
      profileVerifiedBadgeEl.classList.toggle('verified-badge-star--hidden', !isVerified);
      profileVerifiedBadgeEl.hidden = !isVerified;
    }

    updateProExpiryDisplay(profileProExpiryEl, profileProExpiryValueEl, showProExpiry ? account.pro_expires_at : null, proExpired);
    updateProExpiryDisplay(profileProExpiryMobileEl, profileProExpiryMobileValueEl, showProExpiry ? account.pro_expires_at : null, proExpired);

    if (profileStatusTextEl) {
      if (isBanned) {
        profileStatusTextEl.textContent = 'Banned';
      } else if (isBlocked) {
        profileStatusTextEl.textContent = 'Blocked';
      } else {
        profileStatusTextEl.textContent = 'Live';
      }
    }

    if (profileStatusMobileEl) {
      if (isBanned) {
        profileStatusMobileEl.textContent = 'Banned';
      } else if (isBlocked) {
        profileStatusMobileEl.textContent = 'Blocked';
      } else {
        profileStatusMobileEl.textContent = 'Live';
      }
    }

    const statusDot = profileCard.querySelector('.status-dot');
    if (statusDot) {
      statusDot.classList.toggle('offline', isBanned || isBlocked);
    }

    if (profileCardMobile) {
      const mobileDot = profileCardMobile.querySelector('.status-dot');
      if (mobileDot) {
        mobileDot.classList.toggle('offline', isBanned || isBlocked);
      }
      profileCardMobile.classList.toggle('profile-card--alert', isBanned || isBlocked);
    }

    profileCard.classList.toggle('profile-card--alert', isBanned || isBlocked);
    applyAccountRestrictions(account);
    updateDashboardStats();
    maybeShowFreeUpgrade(account);
  }

  async function fetchAccountState() {
    const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=account', {
      credentials: 'same-origin'
    });
    if (!res.ok) {
      throw new Error('Gagal memuat akun (HTTP ' + res.status + ')');
    }
    const payload = await res.json();
    if (!payload.ok) {
      throw new Error(payload.error || 'Gagal memuat akun.');
    }
    return payload.data || {};
  }

  async function persistTheme(theme) {
    const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=account-theme', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ theme })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error((data && data.error) || 'Gagal menyimpan tema.');
    }
    return data.data;
  }

  async function loadAccountState() {
    try {
      const account = await fetchAccountState();
      currentAccount = account;
      applyPlatformState(account.platform || null);
      applyTheme(account.theme || currentTheme);
      updateProfileCard(account);
      renderDashboardAnnouncements(account.announcements || []);
      applyAccountRestrictions(account);
      setDriveAccess(true);
      if (driveLoaded) {
        renderDriveItems();
      } else {
        updateDriveSummary();
      }
    } catch (err) {
      console.warn('Tidak dapat memuat akun:', err);
      applyTheme(currentTheme);
      currentAccount = null;
      updateWatermarkNotice(null);
      applyPlatformState(null);
      renderDashboardAnnouncements([]);
      applyAccountRestrictions(null);
      closeFreeUpgradeModal({ skipDismiss: true });
      freeUpgradeDismissed = false;
      freeUpgradeAutoShown = false;
      driveItems = [];
      driveLoaded = false;
      setDriveAccess(false);
      renderDriveItems();
    }
  }

  function ensureCoins(amount) {
    if (!currentAccount) {
      return false;
    }
    const balance = Number.isFinite(Number(currentAccount.coins)) ? Number(currentAccount.coins) : 0;
    return balance >= amount;
  }

  async function spendCoins(amount) {
    if (!amount || amount <= 0) return;
    const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=account-coins', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ amount })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      const message = (data && data.error) ? JSON.stringify(data.error) : 'Koin tidak bisa diperbarui.';
      throw new Error(message);
    }
    if (!currentAccount) currentAccount = {};
    currentAccount.coins = data.data && typeof data.data.coins !== 'undefined' ? data.data.coins : currentAccount.coins;
    updateProfileCard(currentAccount);
  }

  if (themeToggle) {
    themeToggle.addEventListener('click', async () => {
      const next = currentTheme === 'dark' ? 'light' : 'dark';
      const previous = currentTheme;
      applyTheme(next);
      try {
        const updated = await persistTheme(next);
        if (updated) {
          currentAccount = updated;
          updateProfileCard(updated);
        }
      } catch (err) {
        console.warn('Gagal menyimpan tema:', err);
        applyTheme(previous);
      }
    });
  }

  async function performLogout(trigger) {
    if (!trigger) return;
    if (trigger.classList.contains('loading')) {
      return;
    }
    const hadDisabled = typeof trigger.disabled === 'boolean';
    const previousDisabled = hadDisabled ? trigger.disabled : null;
    trigger.classList.add('loading');
    if (hadDisabled) {
      trigger.disabled = true;
    } else {
      trigger.setAttribute('aria-disabled', 'true');
    }
    try {
      const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=logout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({})
      });

      if (!res.ok) {
        const text = await res.text().catch(() => '');
        console.error('Logout gagal:', text);
        alert('Logout gagal. Silakan coba lagi.');
        return;
      }

      window.location.reload();
    } catch (err) {
      console.error('Logout error', err);
      alert('Terjadi kesalahan saat logout.');
    } finally {
      trigger.classList.remove('loading');
      if (hadDisabled) {
        trigger.disabled = !!previousDisabled;
      } else {
        trigger.removeAttribute('aria-disabled');
      }
    }
  }

  if (logoutButton) {
    logoutButton.addEventListener('click', () => performLogout(logoutButton));
  }

  if (maintenanceLogoutButton) {
    maintenanceLogoutButton.addEventListener('click', () => performLogout(maintenanceLogoutButton));
  }

  if (freeUpgradeClose) {
    freeUpgradeClose.addEventListener('click', () => closeFreeUpgradeModal());
  }

  if (freeUpgradeBackdrop) {
    freeUpgradeBackdrop.addEventListener('click', () => closeFreeUpgradeModal());
  }

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && freeUpgradeModal && freeUpgradeModal.classList.contains('active')) {
      closeFreeUpgradeModal();
    }
  });

  if (avatarUploadBtn && avatarFileInput) {
    avatarUploadBtn.addEventListener('click', () => avatarFileInput.click());
  }

  if (avatarRemoveBtn) {
    avatarRemoveBtn.addEventListener('click', () => {
      if (avatarUrlInput) {
        avatarUrlInput.value = '';
      }
      updateAvatarPreview('', currentAccountInitials());
      showInlineStatus(avatarFormStatus, 'Avatar akan dihapus setelah disimpan.', 'progress');
    });
  }

  if (avatarUrlInput) {
    avatarUrlInput.addEventListener('input', () => {
      const value = avatarUrlInput.value.trim();
      updateAvatarPreview(value, currentAccountInitials());
      showInlineStatus(avatarFormStatus, '', null);
    });
  }

  if (avatarFileInput) {
    avatarFileInput.addEventListener('change', async event => {
      const file = event.target.files && event.target.files[0];
      if (!file) return;
      if (!file.type || !file.type.startsWith('image/')) {
        showInlineStatus(avatarFormStatus, 'File harus gambar (PNG/JPG/WEBP).', 'err');
        avatarFileInput.value = '';
        return;
      }

      showInlineStatus(avatarFormStatus, `Mengunggah ${file.name}…`, 'progress');
      try {
        const result = await uploadFileToServer(file);
        if (!result || !result.url) {
          throw new Error('Upload gagal.');
        }
        if (avatarUrlInput) {
          avatarUrlInput.value = result.url;
        }
        updateAvatarPreview(result.url, currentAccountInitials());
        showInlineStatus(avatarFormStatus, 'Upload berhasil, klik Simpan Avatar untuk menyimpan.', 'ok');
      } catch (err) {
        showInlineStatus(avatarFormStatus, err.message || 'Upload gagal.', 'err');
      } finally {
        avatarFileInput.value = '';
      }
    });
  }

  if (avatarForm) {
    avatarForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (!currentAccount) {
        alert('Data akun belum siap. Muat ulang halaman.');
        return;
      }

      const avatarValue = avatarUrlInput ? avatarUrlInput.value.trim() : '';
      if (avatarValue && !/^https?:\/\//i.test(avatarValue)) {
        showInlineStatus(avatarFormStatus, 'Gunakan URL gambar yang diawali http/https.', 'err');
        return;
      }

      showInlineStatus(avatarFormStatus, 'Menyimpan avatar…', 'progress');
      try {
        const res = await fetch(ACCOUNT_AVATAR_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ avatar: avatarValue })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
          throw new Error(pickErrorMessage(data && data.error));
        }

        if (!currentAccount) currentAccount = {};
        currentAccount = Object.assign({}, currentAccount, data.data || {});
        updateProfileCard(currentAccount);
        showInlineStatus(avatarFormStatus, 'Avatar diperbarui.', 'ok');
      } catch (err) {
        showInlineStatus(avatarFormStatus, err.message || 'Gagal menyimpan avatar.', 'err');
      }
    });
  }

  if (passwordForm) {
    passwordForm.addEventListener('submit', async event => {
      event.preventDefault();
      const current = currentPasswordInput ? currentPasswordInput.value : '';
      const next = newPasswordInput ? newPasswordInput.value : '';
      const confirm = confirmPasswordInput ? confirmPasswordInput.value : '';

      if (!next || next.length < 6) {
        showInlineStatus(passwordFormStatus, 'Password baru minimal 6 karakter.', 'err');
        return;
      }
      if (next !== confirm) {
        showInlineStatus(passwordFormStatus, 'Konfirmasi password harus sama.', 'err');
        return;
      }

      showInlineStatus(passwordFormStatus, 'Memperbarui password…', 'progress');
      try {
        const res = await fetch(ACCOUNT_PASSWORD_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ current, password: next, confirm })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
          throw new Error(pickErrorMessage(data && data.error));
        }

        if (currentPasswordInput) currentPasswordInput.value = '';
        if (newPasswordInput) newPasswordInput.value = '';
        if (confirmPasswordInput) confirmPasswordInput.value = '';
        showInlineStatus(passwordFormStatus, data.message || 'Password berhasil diperbarui.', 'ok');
      } catch (err) {
        showInlineStatus(passwordFormStatus, err.message || 'Gagal memperbarui password.', 'err');
      }
    });

    [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(input => {
      if (!input) return;
      input.addEventListener('input', () => showInlineStatus(passwordFormStatus, '', null));
    });
  }

  setDriveAccess(false);
  updateDriveSummary();
  renderDriveItems();

  // ===== GEMINI MODES =====
  const GEMINI_MODE_META = {
    text: {
      title: 'Mode 1: Text-to-Image',
      desc: 'Masukkan prompt deskriptif tanpa gambar referensi.',
      helper: 'Mode ini tidak memerlukan gambar referensi.',
      min: 0,
      max: 0
    },
    single: {
      title: 'Mode 2: Single Image-to-Image',
      desc: 'Tambahkan 1 gambar referensi untuk diedit mengikuti caption.',
      helper: 'Unggah atau tambahkan tepat 1 URL gambar referensi.',
      min: 1,
      max: 1
    },
    multi: {
      title: 'Mode 3: Multi-Image Reference',
      desc: 'Gunakan 2-3 gambar referensi agar gaya/objeknya digabungkan.',
      helper: 'Unggah hingga 3 gambar referensi sekaligus (minimal 2).',
      min: 2,
      max: 3
    }
  };

  const VIDEO_LAYOUT_OPTIONS = [
    { value: 'portrait',  label: 'Portrait 9:16',  ratio: 'portrait_9_16' },
    { value: 'landscape', label: 'Landscape 16:9', ratio: 'landscape_16_9' },
    { value: 'square',    label: 'Square 1:1',     ratio: 'square_1_1' }
  ];

  const VIDEO_LAYOUT_TO_RATIO = VIDEO_LAYOUT_OPTIONS.reduce((acc, opt) => {
    acc[opt.value] = opt.ratio;
    return acc;
  }, {});

  const VIDEO_MODEL_DURATION_OPTIONS = {
    wan480: { values: [4, 6, 10], default: 6, defaultLayout: 'portrait' },
    wan720: { values: [4, 6, 10], default: 6, defaultLayout: 'portrait' },
    seedancePro480: { values: [5, 10], default: 5, defaultLayout: 'portrait' },
    seedancePro720: { values: [5, 10], default: 5, defaultLayout: 'portrait' },
    seedancePro1080: { values: [5, 10], default: 5, defaultLayout: 'landscape' },
    klingStd21: { values: [5, 8], default: 5, defaultLayout: 'portrait' },
    kling25Pro: { values: [5, 8, 12], default: 5, defaultLayout: 'landscape' },
    minimax1080: { values: [6, 12], default: 6, defaultLayout: 'landscape' },
    _default: { values: [5], default: 5, defaultLayout: 'portrait' }
  };

  function mapVideoAspect(layoutKey) {
    if (!layoutKey) return 'auto';
    const key = String(layoutKey).toLowerCase();
    return VIDEO_LAYOUT_TO_RATIO[key] || 'auto';
  }

  function normalizeVideoImageField(target, field) {
    if (!target || typeof target !== 'object' || !(field in target)) return;
    const rawValue = target[field];
    if (typeof rawValue !== 'string') return;
    const trimmed = rawValue.trim();
    if (!trimmed) {
      delete target[field];
      return;
    }

    const dataUrlMatch = trimmed.match(/^data:image\/[a-z0-9.+-]+;base64,(.+)$/i);
    if (dataUrlMatch && dataUrlMatch[1]) {
      target[field] = dataUrlMatch[1];
      return;
    }

    if (/^https?:\/\//i.test(trimmed)) {
      target[`${field}_url`] = trimmed;
      delete target[field];
      return;
    }

    target[field] = trimmed;
  }

  function applyVideoExtras(body, formData = {}) {
    if (!body || typeof body !== 'object') return body;
    const payload = { ...body };

    if (typeof payload.prompt === 'string') {
      payload.prompt = payload.prompt.trim();
    }

    normalizeVideoImageField(payload, 'image');
    normalizeVideoImageField(payload, 'first_frame_image');

    const duration = formData.videoDuration;
    if (typeof duration === 'number' && !Number.isNaN(duration) && duration > 0) {
      payload.duration = duration;
    }
    const ratio = mapVideoAspect(formData.videoLayout);
    if (ratio && (ratio !== 'auto' || !payload.aspect_ratio)) {
      payload.aspect_ratio = ratio;
    } else if (!payload.aspect_ratio) {
      payload.aspect_ratio = 'auto';
    }
    return payload;
  }

  // ===== MODEL CONFIG =====
  const MODEL_CONFIG = {
    gemini: {
      id: 'gemini',
      label: 'Gemini 2.5 Flash',
      type: 'image',
      path: '/v1/ai/gemini-2-5-flash-image-preview',
      statusPath: taskId => `/v1/ai/gemini-2-5-flash-image-preview/${taskId}`,
      buildBody: f => {
        const body = { prompt: f.prompt, num_images: f.numImages || 1 };
        if (f.aspectRatio) body.aspect_ratio = f.aspectRatio;
        if (Array.isArray(f.referenceImages) && f.referenceImages.length) {
          body.reference_images = f.referenceImages;
        }
        return body;
      }
    },
    imagen3: {
      id: 'imagen3',
      label: 'Imagen 3',
      type: 'image',
      path: '/v1/ai/text-to-image/imagen3',
      statusPath: taskId => `/v1/ai/text-to-image/imagen3/${taskId}`,
      buildBody: f => {
        const body = { prompt: f.prompt, num_images: f.numImages || 1 };
        if (f.aspectRatio) body.aspect_ratio = f.aspectRatio;
        return body;
      }
    },
    seedream4: {
      id: 'seedream4',
      label: 'Seedream 4',
      type: 'image',
      path: '/v1/ai/text-to-image/seedream-v4',
      statusPath: taskId => `/v1/ai/text-to-image/seedream-v4/${taskId}`,
      buildBody: f => {
        const body = { prompt: f.prompt, num_images: f.numImages || 1 };
        if (f.aspectRatio) {
          body.aspect_ratio = f.aspectRatio;
        }
        return body;
      }
    },
    seedream4edit: {
      id: 'seedream4edit',
      label: 'Seedream 4 Edit',
      type: 'image',
      // WAJIB: pakai endpoint EDIT, bukan seedream-v4
      path: '/v1/ai/text-to-image/seedream-v4-edit',
      statusPath: taskId => `/v1/ai/text-to-image/seedream-v4-edit/${taskId}`,
      buildBody: f => {
        const refs = [];
        // product images
        if (Array.isArray(f.productImages)) refs.push(...f.productImages);
        // model image optional
        if (f.modelImage) refs.push(f.modelImage);

        const aspect = f.aspectRatio || f.aspect_ratio;

        return {
          prompt: f.prompt,
          aspect_ratio: aspect || 'square_1_1',
          guidance_scale: 2.5,
          reference_images: refs  // <== ini yang bikin produk mengikuti foto upload
        };
      }
    },

    fluxPro11: {
      id: 'fluxPro11',
      label: 'Flux Pro v1.1',
      type: 'image',
      path: '/v1/ai/text-to-image/flux-pro-v1-1',
      statusPath: taskId => `/v1/ai/text-to-image/flux-pro-v1-1/${taskId}`,
      buildBody: f => {
        const body = { prompt: f.prompt, num_images: f.numImages || 1 };
        if (f.aspectRatio) body.aspect_ratio = f.aspectRatio;
        return body;
      }
    },
    mystic: {
      id: 'mystic',
      label: 'Image Mystic',
      type: 'image',
      path: '/v1/ai/text-to-image/mystic',
      statusPath: taskId => `/v1/ai/text-to-image/mystic/${taskId}`,
      buildBody: f => {
        const body = { prompt: f.prompt, num_images: f.numImages || 1 };
        if (f.aspectRatio) body.aspect_ratio = f.aspectRatio;
        return body;
      }
    },
    getHyperflux: {
      id: 'getHyperflux',
      label: 'Get Hyperflux',
      type: 'image',
      path: '/v1/ai/text-to-image/get-hyperflux',
      statusPath: taskId => `/v1/ai/text-to-image/get-hyperflux/${taskId}`,
      buildBody: f => {
        const body = { prompt: f.prompt, num_images: f.numImages || 1 };
        if (f.aspectRatio) body.aspect_ratio = f.aspectRatio;
        return body;
      }
    },

    upscalerCreative: {
      id: 'upscalerCreative',
      label: 'Upscaler Creative',
      type: 'image',
      path: '/v1/ai/image-upscaler',
      statusPath: taskId => `/v1/ai/image-upscaler/${taskId}`,
      buildBody: f => ({ image: f.imageUrl, prompt: f.prompt || undefined })
    },
    upscalePrecV1: {
      id: 'upscalePrecV1',
      label: 'Upscale Precision V1',
      type: 'image',
      path: '/v1/ai/image-upscaler-precision',
      statusPath: taskId => `/v1/ai/image-upscaler-precision/${taskId}`,
      buildBody: f => ({ image: f.imageUrl, prompt: f.prompt || undefined })
    },
    upscalePrecV2: {
      id: 'upscalePrecV2',
      label: 'Upscale Precision V2',
      type: 'image',
      path: '/v1/ai/image-upscaler-precision-v2',
      statusPath: taskId => `/v1/ai/image-upscaler-precision-v2/${taskId}`,
      buildBody: f => ({ image: f.imageUrl, prompt: f.prompt || undefined })
    },
    removeBg: {
      id: 'removeBg',
      label: 'Remove Background',
      type: 'image',
      path: '/v1/ai/beta/remove-background',
      statusPath: null,
      contentType: 'form',
      buildBody: f => `image_url=${encodeURIComponent(f.imageUrl)}`
    },

    wan480: {
      id: 'wan480',
      label: 'Wan v2.2 – 480p',
      type: 'video',
      path: '/v1/ai/image-to-video/wan-v2-2-480p',
      statusPath: taskId => `/v1/ai/image-to-video/wan-v2-2-480p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    wan720: {
      id: 'wan720',
      label: 'Wan v2.2 – 720p',
      type: 'video',
      path: '/v1/ai/image-to-video/wan-v2-2-720p',
      statusPath: taskId => `/v1/ai/image-to-video/wan-v2-2-720p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    seedancePro480: {
      id: 'seedancePro480',
      label: 'Seedance Pro – 480p',
      type: 'video',
      path: '/v1/ai/image-to-video/seedance-pro-480p',
      statusPath: taskId => `/v1/ai/image-to-video/seedance-pro-480p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    seedancePro720: {
      id: 'seedancePro720',
      label: 'Seedance Pro – 720p',
      type: 'video',
      path: '/v1/ai/image-to-video/seedance-pro-720p',
      statusPath: taskId => `/v1/ai/image-to-video/seedance-pro-720p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    seedancePro1080: {
      id: 'seedancePro1080',
      label: 'Seedance Pro – 1080p',
      type: 'video',
      path: '/v1/ai/image-to-video/seedance-pro-1080p',
      statusPath: taskId => `/v1/ai/image-to-video/seedance-pro-1080p/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    klingStd21: {
      id: 'klingStd21',
      label: 'Kling Std v2.1',
      type: 'video',
      path: '/v1/ai/image-to-video/kling-v2-1-std',
      statusPath: taskId => `/v1/ai/image-to-video/kling-v2-1-std/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    kling21Master: {
      id: 'kling21Master',
      label: 'Kling v2.1 Master',
      type: 'video',
      path: '/v1/ai/image-to-video/kling-v2-1-master',
      statusPath: taskId => `/v1/ai/image-to-video/kling-v2-1-master/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    kling25Pro: {
      id: 'kling25Pro',
      label: 'Kling v2.5 Pro',
      type: 'video',
      path: '/v1/ai/image-to-video/kling-v2-5-pro',
      statusPath: taskId => `/v1/ai/image-to-video/kling-v2-5-pro/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    pixverse: {
      id: 'pixverse',
      label: 'PixVerse',
      type: 'video',
      path: '/v1/ai/image-to-video/pixverse',
      statusPath: taskId => `/v1/ai/image-to-video/pixverse/${taskId}`,
      buildBody: f => applyVideoExtras({ prompt: f.prompt, image: f.imageUrl }, f)
    },
    minimax1080: {
      id: 'minimax1080',
      label: 'MiniMax Hailuo 02 – 1080p',
      type: 'video',
      path: '/v1/ai/image-to-video/minimax-hailuo-02-1080p',
      statusPath: taskId => `/v1/ai/image-to-video/minimax-hailuo-02-1080p/${taskId}`,
      buildBody: f => applyVideoExtras({
        prompt: f.prompt,
        first_frame_image: f.imageUrl || undefined
      }, f)
    },

    latentSync: {
      id: 'latentSync',
      label: 'Latent-Sync',
      type: 'video',
      path: '/v1/ai/lip-sync/latent-sync',
      statusPath: taskId => `/v1/ai/lip-sync/latent-sync/${taskId}`,
      buildBody: f => ({
        video_url: f.videoUrl,
        audio_url: f.audioUrl,
        prompt: f.prompt || undefined
      })
    }
  };
  modelConfigMap = MODEL_CONFIG;

  const FEATURE_MODELS = {
    imageGen: ['gemini','imagen3','seedream4','fluxPro11','mystic','getHyperflux','seedream4edit','upscalerCreative','upscalePrecV1','upscalePrecV2','removeBg'],
    videoGen: ['wan480','wan720','seedancePro480','seedancePro720','seedancePro1080','klingStd21','kling21Master','kling25Pro','pixverse','minimax1080','latentSync']
  };

  const STORAGE_KEY = 'freepik_jobs_v1';
  const DRIVE_ENDPOINT = '<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=drive';
  const DRIVE_DELETE_ENDPOINT = '<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=drive-delete';
  const ACCOUNT_AVATAR_ENDPOINT = '<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=account-avatar';
  const ACCOUNT_PASSWORD_ENDPOINT = '<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=account-password';
  const GEMINI_ENDPOINT = '<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=gemini';
  jobs = loadJobs();
  updateDashboardStats();
  let activeJobId = null;
  let pollingTimers = {};
  let progressTimers = {};
  let statusProgressHideTimeout = null;

  function loadJobs() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return [];
      const arr = JSON.parse(raw);
      if (!Array.isArray(arr)) return [];
      return arr.map(job => {
        if (job && typeof job === 'object') {
          if (typeof job.progress !== 'number') {
            job.progress = finalStatus(job.status) ? 100 : 0;
          }
          if (job.driveSynced && typeof job.driveSynced !== 'string') {
            job.driveSynced = String(job.driveSynced);
          }
        }
        return job;
      });
    } catch {
      return [];
    }
  }
  function saveJobs() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(jobs));
  }
  function uuid() {
    return 'xxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }
  function nowIso() { return new Date().toISOString(); }
  function shortTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  }
  function finalStatus(status) {
    if (!status) return false;
    const s = status.toUpperCase();
    return s === 'COMPLETED' || s === 'FAILED' || s === 'ERROR';
  }

  function getJobProgress(job) {
    if (!job) return 0;
    if (typeof job.progress === 'number' && !Number.isNaN(job.progress)) {
      return Math.max(0, Math.min(100, Math.round(job.progress)));
    }
    return finalStatus(job.status) ? 100 : 0;
  }

  function hideStatusProgress() {
    if (statusProgressWrapper) {
      statusProgressWrapper.classList.remove('active');
    }
    if (statusProgressFill) {
      statusProgressFill.style.width = '0%';
    }
    if (statusPercent) {
      statusPercent.textContent = '0%';
    }
    if (previewProgress) {
      previewProgress.classList.remove('active');
    }
    if (previewProgressFill) {
      previewProgressFill.style.width = '0%';
    }
    if (previewProgressPercent) {
      previewProgressPercent.textContent = '0%';
    }
  }

  function syncStatusProgress(job) {
    if (!statusProgressWrapper || !statusProgressFill || !statusPercent) return;
    clearTimeout(statusProgressHideTimeout);

    if (!job) {
      hideStatusProgress();
      return;
    }

    const percent = getJobProgress(job);
    statusProgressWrapper.classList.add('active');
    statusProgressFill.style.width = percent + '%';
    statusPercent.textContent = percent + '%';

    if (previewProgress && previewProgressFill && previewProgressPercent) {
      previewProgress.classList.add('active');
      previewProgressFill.style.width = percent + '%';
      previewProgressPercent.textContent = percent + '%';
    }

    if (finalStatus(job.status) && percent >= 100) {
      statusProgressHideTimeout = setTimeout(() => {
        hideStatusProgress();
      }, 1400);
    }
  }

  function stopProgressTimer(jobId) {
    if (progressTimers[jobId]) {
      clearInterval(progressTimers[jobId]);
      delete progressTimers[jobId];
    }
  }

  function startJobProgress(job) {
    if (!job) return;
    if (typeof job.progress !== 'number' || Number.isNaN(job.progress)) {
      job.progress = finalStatus(job.status) ? 100 : 8;
    } else if (job.progress < 5 && !finalStatus(job.status)) {
      job.progress = 8;
    }

    stopProgressTimer(job.id);
    if (finalStatus(job.status)) {
      job.progress = 100;
      updateProgressUI(job);
      return;
    }

    progressTimers[job.id] = setInterval(() => {
      const current = jobs.find(j => j.id === job.id);
      if (!current) {
        stopProgressTimer(job.id);
        return;
      }
      if (finalStatus(current.status)) {
        current.progress = 100;
        stopProgressTimer(job.id);
        updateProgressUI(current);
        saveJobs();
        return;
      }
      const target = 92;
      const next = Math.min(target, (current.progress || 0) + (Math.random() * 7 + 3));
      current.progress = next;
      updateProgressUI(current);
      saveJobs();
    }, 1600);

    updateProgressUI(job);
  }

  function finishJobProgress(job) {
    if (!job) return;
    job.progress = 100;
    stopProgressTimer(job.id);
    updateProgressUI(job);
    saveJobs();
  }

  function updateProgressUI(job) {
    if (job && activeJobId === job.id) {
      syncStatusProgress(job);
    }
    renderJobs();
  }

  const modelSelect = document.getElementById('modelSelect');
  const modelHint = document.getElementById('modelHint');
  const promptInput = document.getElementById('prompt');
  const imageUrlInput = document.getElementById('imageUrl');
  const imageUploadInput = document.getElementById('imageUploadInput');
  const imageUploadButton = document.getElementById('imageUploadButton');
  const imageUploadDropzone = document.getElementById('imageUploadDropzone');
  const imageUploadStatus = document.getElementById('imageUploadStatus');
  const imageUploadPreview = document.getElementById('imageUploadPreview');
  const geminiModeSection = document.getElementById('geminiModeSection');
  const geminiModeButtons = document.querySelectorAll('[data-gemini-mode]');
  const geminiModeDescription = document.getElementById('geminiModeDescription');
  const geminiReferenceSection = document.getElementById('geminiReferenceSection');
  const geminiDropzone = document.getElementById('geminiDropzone');
  const geminiFileInput = document.getElementById('geminiFileInput');
  const geminiFileButton = document.getElementById('geminiFileButton');
  const geminiRefHelper = document.getElementById('geminiRefHelper');
  const geminiDropCounter = document.getElementById('geminiDropCounter');
  const geminiRefUrl = document.getElementById('geminiRefUrl');
  const geminiRefAddBtn = document.getElementById('geminiRefAddBtn');
  const geminiRefList = document.getElementById('geminiRefList');
  const geminiTextForm = document.getElementById('geminiTextForm');
  const geminiTextModel = document.getElementById('geminiTextModel');
  const geminiTextPrompt = document.getElementById('geminiTextPrompt');
  const geminiTextSystem = document.getElementById('geminiTextSystem');
  const geminiTextTemperature = document.getElementById('geminiTextTemperature');
  const geminiTextStatus = document.getElementById('geminiTextStatus');
  const geminiTextOutput = document.getElementById('geminiTextOutput');
  const geminiTextCopyBtn = document.getElementById('geminiTextCopy');
  const geminiTextResetBtn = document.getElementById('geminiTextReset');
  const geminiTextSubmitBtn = document.getElementById('geminiTextSubmit');
  const geminiSpeechForm = document.getElementById('geminiSpeechForm');
  const geminiSpeechModel = document.getElementById('geminiSpeechModel');
  const geminiSpeechPrompt = document.getElementById('geminiSpeechPrompt');
  const geminiSpeechVoice = document.getElementById('geminiSpeechVoice');
  const geminiSpeechLanguage = document.getElementById('geminiSpeechLanguage');
  const geminiSpeechFormat = document.getElementById('geminiSpeechFormat');
  const geminiSpeechTemperature = document.getElementById('geminiSpeechTemperature');
  const geminiSpeechReference = document.getElementById('geminiSpeechReference');
  const geminiSpeechStatus = document.getElementById('geminiSpeechStatus');
  const geminiSpeechSubmitBtn = document.getElementById('geminiSpeechSubmit');
  const geminiSpeechResetBtn = document.getElementById('geminiSpeechReset');
  const geminiSpeechAudio = document.getElementById('geminiSpeechAudio');
  const geminiSpeechDownload = document.getElementById('geminiSpeechDownload');
  const geminiVeoForm = document.getElementById('geminiVeoForm');
  const geminiVeoPrompt = document.getElementById('geminiVeoPrompt');
  const geminiVeoDialogue = document.getElementById('geminiVeoDialogue');
  const geminiVeoDuration = document.getElementById('geminiVeoDuration');
  const geminiVeoAspect = document.getElementById('geminiVeoAspect');
  const geminiVeoStyle = document.getElementById('geminiVeoStyle');
  const geminiVeoStatus = document.getElementById('geminiVeoStatus');
  const geminiVeoSubmitBtn = document.getElementById('geminiVeoSubmit');
  const geminiVeoClearBtn = document.getElementById('geminiVeoClear');
  const geminiVeoResults = document.getElementById('geminiVeoResults');
  const defaultGeminiTextTemperature = geminiTextTemperature ? geminiTextTemperature.value : '0.7';
  const defaultGeminiSpeechModel = geminiSpeechModel ? geminiSpeechModel.value : 'gemini-1.5-flash-latest';
  const defaultGeminiSpeechTemperature = geminiSpeechTemperature ? geminiSpeechTemperature.value : '0.3';
  const defaultGeminiVeoDuration = geminiVeoDuration ? geminiVeoDuration.value : '6';
  const defaultGeminiVeoAspect = geminiVeoAspect ? geminiVeoAspect.value : '16:9';
  const videoUrlInput = document.getElementById('videoUrl');
  const audioUrlInput = document.getElementById('audioUrl');
  const rowVideoSettings = document.getElementById('rowVideoSettings');
  const videoDurationSelect = document.getElementById('videoDuration');
  const videoLayoutSelect = document.getElementById('videoLayout');
  const numImagesInput = document.getElementById('numImages');
  const aspectRatioInput = document.getElementById('aspectRatio');
  const submitBtn = document.getElementById('submitBtn');
  const clearPromptBtn = document.getElementById('clearPromptBtn');
  const statusText = document.getElementById('statusText');
  const statusPill = document.getElementById('statusPill');
  const statusProgressWrapper = document.getElementById('statusProgressWrapper');
  const statusProgressFill = document.getElementById('statusProgressFill');
  const statusPercent = document.getElementById('statusPercent');
  const previewEmpty = document.getElementById('previewEmpty');
  const previewContainer = document.getElementById('previewContainer');
  const previewJobMeta = document.getElementById('previewJobMeta');
  const previewGrid = document.getElementById('previewGrid');
  const previewProgress = document.getElementById('previewProgress');
  const previewProgressFill = document.getElementById('previewProgressFill');
  const previewProgressPercent = document.getElementById('previewProgressPercent');
  const clearPreviewBtn = document.getElementById('clearPreviewBtn');
  const queueList = document.getElementById('queueList');
  const queueEmpty = document.getElementById('queueEmpty');
  const historyList = document.getElementById('historyList');
  const historyEmpty = document.getElementById('historyEmpty');
  const refreshQueueBtn = document.getElementById('refreshQueueBtn');
  const clearHistoryBtn = document.getElementById('clearHistoryBtn');
  const webhookForm = document.getElementById('webhookForm');
  const webhookUrlInput = document.getElementById('webhookUrl');
  const webhookEventsInput = document.getElementById('webhookEvents');
  const webhookSecretInput = document.getElementById('webhookSecret');
  const webhookStatus = document.getElementById('webhookStatus');
  const webhookList = document.getElementById('webhookList');
  const webhookRefreshBtn = document.getElementById('webhookRefreshBtn');
  const webhookSubmitBtn = document.getElementById('webhookSubmit');
  const featureTabs = document.querySelectorAll('.feature-tab');
  const featureLabel = document.getElementById('featureLabel');
  let navButtons = [];
  let viewDashboardSection = null;
  let viewDriveSection = null;
  let viewHubSection = null;
  let viewFilmSection = null;
  let viewUGCSection = null;
  let viewAudioSection = null;
  let viewAccountSection = null;
  let viewSections = {};

  const rowPrompt      = document.getElementById('rowPrompt');
  const rowImageUrl    = document.getElementById('rowImageUrl');
  const rowVideoAudio  = document.getElementById('rowVideoAudio');
  const rowTIOptions   = document.getElementById('rowTIOptions');
  const fieldsTitle    = document.getElementById('fieldsTitle');

  // Film
  const filmCharacterDrop = document.getElementById('filmCharacterDrop');
  const filmCharacterInput = document.getElementById('filmCharacterInput');
  const filmCharacterIdle = document.getElementById('filmCharacterIdle');
  const filmCharacterPreview = document.getElementById('filmCharacterPreview');
  const filmBriefInput = document.getElementById('filmBrief');
  const filmSceneCount = document.getElementById('filmSceneCount');
  const filmSceneCountLabel = document.getElementById('filmSceneCountLabel');
  const filmAspectButtons = document.querySelectorAll('[data-film-aspect]');
  const filmStatePicker = document.getElementById('filmStatePicker');
  const filmStateTrigger = document.getElementById('filmStateTrigger');
  const filmStateMenu = document.getElementById('filmStateMenu');
  const filmStateValueInput = document.getElementById('filmStateValue');
  const filmStateLabelEl = document.getElementById('filmStateLabel');
  const filmStateDescEl = document.getElementById('filmStateDescription');
  const filmStateIconEl = document.getElementById('filmStateIcon');
  const filmGenerateBtn = document.getElementById('filmGenerateBtn');
  const filmScenesEmpty = document.getElementById('filmScenesEmpty');
  const filmScenesContainer = document.getElementById('filmScenesContainer');

  // UGC
  const ugcList           = document.getElementById('ugcList');
  const ugcEmpty          = document.getElementById('ugcEmpty');
  const ugcProductDrop    = document.getElementById('ugcProductDrop');
  const ugcProductInput   = document.getElementById('ugcProductInput');
  const ugcProductPreview = document.getElementById('ugcProductPreview');
  const ugcModelDrop       = document.getElementById('ugcModelDrop');
  const ugcModelInput      = document.getElementById('ugcModelInput');
  const ugcModelIdle       = document.getElementById('ugcModelIdle');
  const ugcModelPreview    = document.getElementById('ugcModelPreview');
  const ugcStylePicker     = document.getElementById('ugcStylePicker');
  const ugcStyleTrigger    = document.getElementById('ugcStyleTrigger');
  const ugcStyleMenu       = document.getElementById('ugcStyleMenu');
  const ugcStyleValueInput = document.getElementById('ugcStyleValue');
  const ugcStyleLabelEl    = document.getElementById('ugcStyleLabel');
  const ugcStyleDescEl     = document.getElementById('ugcStyleDescription');
  const ugcStyleIconEl     = document.getElementById('ugcStyleIcon');
  const ugcBriefInput      = document.getElementById('ugcBrief');
  const ugcGenerateBtn     = document.getElementById('ugcGenerateBtn');

  function setStatus(text, mode = 'idle') {
    statusText.textContent = text;
    statusText.classList.remove('flash');
    void statusText.offsetWidth;
    statusText.classList.add('flash');
    statusPill.classList.remove('ok', 'err');
    if (mode === 'ok') {
      statusPill.textContent = 'OK';
      statusPill.classList.add('ok');
    } else if (mode === 'err') {
      statusPill.textContent = 'ERROR';
      statusPill.classList.add('err');
    } else {
      statusPill.textContent = 'IDLE';
    }
  }

  let geminiMode = 'text';
  let geminiReferences = [];

  function isHttpUrl(value) {
    return typeof value === 'string' && /^https?:\/\//i.test(value);
  }

  function isDataImage(value) {
    return typeof value === 'string' && /^data:image\//i.test(value);
  }

  function stripDataUrlPrefix(dataUrl) {
    return typeof dataUrl === 'string'
      ? dataUrl.replace(/^data:image\/[a-z0-9.+-]+;base64,/i, '')
      : dataUrl;
  }

  async function fetchLocalImageAsBase64(url) {
    const absolute = new URL(url, window.location.href).href;
    const res = await fetch(absolute, {
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    const blob = await res.blob();
    return await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onerror = () => reject(new Error('Konversi base64 gagal'));
      reader.onloadend = () => {
        const result = reader.result;
        if (typeof result === 'string') {
          resolve(stripDataUrlPrefix(result));
        } else {
          reject(new Error('Pembacaan file tidak valid'));
        }
      };
      reader.readAsDataURL(blob);
    });
  }

  async function prepareGeminiReferenceImages(urls) {
    const results = [];
    for (const entry of urls) {
      if (!entry) continue;
      if (isDataImage(entry)) {
        results.push(stripDataUrlPrefix(entry));
        continue;
      }
      if (isHttpUrl(entry)) {
        try {
          const target = new URL(entry, window.location.href);
          if (target.origin !== window.location.origin) {
            results.push(entry);
            continue;
          }
        } catch (err) {
          // Jika URL tidak valid, lanjutkan ke percobaan fetch lokal di bawah
        }
      }

      try {
        const base64 = await fetchLocalImageAsBase64(entry);
        results.push(base64);
      } catch (err) {
        throw new Error(`Gagal membaca referensi: ${err.message}`);
      }
    }
    return results;
  }

  function toggleButtonLoading(button, loading, loadingLabel) {
    if (!button) return;
    if (!button.dataset.originalLabel) {
      button.dataset.originalLabel = button.textContent || button.value || '';
    }
    if (loading) {
      button.disabled = true;
      if (loadingLabel) {
        button.textContent = loadingLabel;
      }
      button.classList.add('is-loading');
    } else {
      const original = button.dataset.originalLabel || '';
      if (original && button.textContent !== original) {
        button.textContent = original;
      }
      button.classList.remove('is-loading');
      setDisabledState(button, accountRestricted());
    }
  }

  function clampTemperature(value) {
    if (!Number.isFinite(value)) return null;
    if (value < 0) return 0;
    if (value > 2) return 2;
    return parseFloat(value.toFixed(2));
  }

  function parseNumberInput(input) {
    if (!input) return null;
    const raw = typeof input.value === 'string' ? input.value.trim() : '';
    if (raw === '') return null;
    const num = Number(raw);
    return Number.isFinite(num) ? num : null;
  }

  function mimeToExtension(mime) {
    if (!mime || typeof mime !== 'string') return 'bin';
    const normalized = mime.toLowerCase();
    if (normalized.includes('mp3')) return 'mp3';
    if (normalized.includes('wav')) return 'wav';
    if (normalized.includes('ogg')) return 'ogg';
    if (normalized.includes('mpeg')) return 'mp3';
    if (normalized.includes('aac')) return 'aac';
    if (normalized.includes('flac')) return 'flac';
    return normalized.split('/').pop() || 'bin';
  }

  async function callGemini(action, payload = {}, { signal } = {}) {
    if (!action) {
      throw new Error('Aksi Gemini tidak valid.');
    }

    let response;
    try {
      response = await fetch(GEMINI_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ ...payload, action }),
        signal,
      });
    } catch (err) {
      throw new Error('Gagal terhubung ke server Gemini.');
    }

    let data;
    try {
      data = await response.json();
    } catch (err) {
      throw new Error('Respons Gemini tidak dapat dibaca.');
    }

    if (!response.ok || !data || !data.ok) {
      if (data && data.status === 401) {
        window.location.reload();
        return Promise.reject(new Error('Sesi berakhir. Memuat ulang…'));
      }
      const message = pickErrorMessage(data && data.error) || `Permintaan gagal (HTTP ${response.status || 500}).`;
      throw new Error(message);
    }

    return data.data || {};
  }

  function renderGeminiVeoResults(result) {
    if (!geminiVeoResults) return;
    geminiVeoResults.innerHTML = '';

    if (!result) {
      const placeholder = document.createElement('div');
      placeholder.className = 'gemini-placeholder';
      placeholder.textContent = 'Belum ada hasil video. Isi prompt lalu generate untuk melihat preview.';
      geminiVeoResults.appendChild(placeholder);
      return;
    }

    const urls = Array.isArray(result.videoUrls) ? result.videoUrls.filter(Boolean) : [];
    if (urls.length) {
      urls.forEach((url, idx) => {
        const item = document.createElement('div');
        item.className = 'gemini-video-item';

        const video = document.createElement('video');
        video.controls = true;
        video.loop = true;
        video.muted = true;
        video.playsInline = true;
        video.src = url;
        item.appendChild(video);

        const actions = document.createElement('div');
        actions.className = 'gemini-video-actions';

        const label = document.createElement('span');
        label.className = 'muted';
        label.textContent = `Video ${idx + 1}`;
        actions.appendChild(label);

        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = 'Download';
        link.className = 'download-link';
        actions.appendChild(link);

        item.appendChild(actions);
        geminiVeoResults.appendChild(item);
      });
    }

    const op = result.operation || '';
    if (!urls.length || op) {
      const info = document.createElement('div');
      info.className = 'muted';
      info.style.fontSize = '12px';
      info.style.marginTop = urls.length ? '12px' : '0';
      info.textContent = op
        ? `ID operasi: ${op}. Jika URL belum muncul, periksa status di dashboard Gemini.`
        : 'Gemini tidak mengembalikan URL video. Coba ulangi dengan prompt berbeda.';
      geminiVeoResults.appendChild(info);
    }
  }

  function getGeminiMeta(mode = geminiMode) {
    return GEMINI_MODE_META[mode] || GEMINI_MODE_META.text;
  }

  function getGeminiLimit(mode = geminiMode) {
    const meta = getGeminiMeta(mode);
    return meta && typeof meta.max === 'number' ? meta.max : 0;
  }

  function resetGeminiState(resetMode = true) {
    if (resetMode) geminiMode = 'text';
    geminiReferences = [];
    if (geminiRefUrl) geminiRefUrl.value = '';
  }

  function renderGeminiRefs(isGemini = (modelSelect && modelSelect.value === 'gemini')) {
    if (!geminiRefList) return;
    const meta = getGeminiMeta();
    if (geminiDropCounter) {
      const current = isGemini ? geminiReferences.length : 0;
      const total = meta.max || 0;
      geminiDropCounter.textContent = `${current}/${total}`;
    }
    if (geminiRefHelper) {
      if (!isGemini) {
        geminiRefHelper.textContent = 'Aktifkan Gemini Flash untuk menambah referensi.';
      } else if (meta.max > 0) {
        geminiRefHelper.textContent = `${meta.helper} (${geminiReferences.length}/${meta.max}).`;
      } else {
        geminiRefHelper.textContent = meta.helper;
      }
    }

    geminiRefList.innerHTML = '';
    if (!isGemini) {
      if (geminiDropzone) geminiDropzone.classList.remove('has-file');
      return;
    }

    if (geminiMode === 'text') {
      const info = document.createElement('div');
      info.className = 'muted';
      info.style.fontSize = '11px';
      info.textContent = 'Mode text-only tidak menggunakan referensi.';
      geminiRefList.appendChild(info);
      if (geminiDropzone) geminiDropzone.classList.remove('has-file');
      return;
    }

    if (!geminiReferences.length) {
      const empty = document.createElement('div');
      empty.className = 'muted';
      empty.style.fontSize = '11px';
      empty.textContent = 'Belum ada referensi. Upload gambar atau tambahkan URL di bawah.';
      geminiRefList.appendChild(empty);
      if (geminiDropzone) geminiDropzone.classList.remove('has-file');
      return;
    }

    geminiReferences.forEach((ref, idx) => {
      const item = document.createElement('div');
      item.className = 'gemini-ref-item';

      const thumb = document.createElement('img');
      thumb.className = 'gemini-ref-thumb';
      thumb.src = ref.url;
      thumb.alt = ref.name || ('Referensi ' + (idx + 1));

      const metaDiv = document.createElement('div');
      metaDiv.className = 'gemini-ref-meta';
      const title = document.createElement('strong');
      title.textContent = ref.name || ('Referensi ' + (idx + 1));
      const urlSpan = document.createElement('span');
      urlSpan.textContent = ref.url;
      metaDiv.appendChild(title);
      metaDiv.appendChild(urlSpan);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'small secondary';
      removeBtn.textContent = 'Hapus';
      removeBtn.addEventListener('click', () => {
        geminiReferences.splice(idx, 1);
        renderGeminiRefs(modelSelect && modelSelect.value === 'gemini');
      });

      item.appendChild(thumb);
      item.appendChild(metaDiv);
      item.appendChild(removeBtn);
      geminiRefList.appendChild(item);
    });

    if (geminiDropzone) {
      geminiDropzone.classList.toggle('has-file', geminiReferences.length > 0);
    }
  }

  function updateGeminiModeUI(isGemini = (modelSelect && modelSelect.value === 'gemini')) {
    const meta = getGeminiMeta();
    if (geminiModeButtons && geminiModeButtons.forEach) {
      geminiModeButtons.forEach(btn => {
        const mode = btn.dataset.geminiMode;
        btn.classList.toggle('active', mode === geminiMode);
      });
    }
    if (geminiModeDescription && meta) {
      geminiModeDescription.textContent = `${meta.title} — ${meta.desc}`;
    }
    if (geminiModeSection) {
      geminiModeSection.classList.toggle('hidden', !isGemini);
    }
    if (geminiReferenceSection) {
      const showRefs = isGemini && geminiMode !== 'text';
      geminiReferenceSection.classList.toggle('hidden', !showRefs);
    }
    renderGeminiRefs(isGemini);
  }

  function syncGeminiVisibility() {
    const isGemini = modelSelect && modelSelect.value === 'gemini';
    if (!isGemini) {
      resetGeminiState(true);
    }
    updateGeminiModeUI(isGemini);
  }

  function setGeminiMode(mode) {
    if (!mode || !GEMINI_MODE_META[mode]) return;
    if (geminiMode === mode) {
      updateGeminiModeUI(modelSelect && modelSelect.value === 'gemini');
      return;
    }
    geminiMode = mode;
    const limit = getGeminiLimit(mode);
    if (limit === 0) {
      geminiReferences = [];
    } else if (geminiReferences.length > limit) {
      geminiReferences = geminiReferences.slice(0, limit);
    }
    updateGeminiModeUI(modelSelect && modelSelect.value === 'gemini');
  }

  if (geminiModeButtons && geminiModeButtons.forEach) {
    geminiModeButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const mode = btn.dataset.geminiMode;
        setGeminiMode(mode);
      });
    });
  }

  function ensureGeminiAccess() {
    if (accountRestricted()) {
      showRestrictionNotice();
      return false;
    }
    if (!featureAvailableForCurrentUser('audioGen')) {
      showFeatureLockedMessage('audioGen');
      return false;
    }
    return true;
  }

  function resetGeminiTextForm() {
    if (geminiTextPrompt) geminiTextPrompt.value = '';
    if (geminiTextSystem) geminiTextSystem.value = '';
    if (geminiTextTemperature && typeof defaultGeminiTextTemperature !== 'undefined') {
      geminiTextTemperature.value = defaultGeminiTextTemperature;
    }
    if (geminiTextOutput) geminiTextOutput.value = '';
    showInlineStatus(geminiTextStatus, '', null);
  }

  function clearGeminiSpeechOutput() {
    if (geminiSpeechAudio) {
      try {
        geminiSpeechAudio.pause();
      } catch (err) {
        // abaikan
      }
      geminiSpeechAudio.removeAttribute('src');
      geminiSpeechAudio.load();
      geminiSpeechAudio.style.display = 'none';
    }
    if (geminiSpeechDownload) {
      geminiSpeechDownload.hidden = true;
      geminiSpeechDownload.removeAttribute('href');
      geminiSpeechDownload.removeAttribute('download');
    }
  }

  function resetGeminiSpeechForm() {
    if (geminiSpeechPrompt) geminiSpeechPrompt.value = '';
    if (geminiSpeechModel && typeof defaultGeminiSpeechModel !== 'undefined') {
      geminiSpeechModel.value = defaultGeminiSpeechModel;
    }
    if (geminiSpeechTemperature && typeof defaultGeminiSpeechTemperature !== 'undefined') {
      geminiSpeechTemperature.value = defaultGeminiSpeechTemperature;
    }
    if (geminiSpeechReference) {
      geminiSpeechReference.value = '';
    }
    clearGeminiSpeechOutput();
    showInlineStatus(geminiSpeechStatus, '', null);
  }

  function resetGeminiVeoForm() {
    if (geminiVeoPrompt) geminiVeoPrompt.value = '';
    if (geminiVeoDialogue) geminiVeoDialogue.value = '';
    if (geminiVeoStyle) geminiVeoStyle.value = '';
    if (geminiVeoDuration && typeof defaultGeminiVeoDuration !== 'undefined') {
      geminiVeoDuration.value = defaultGeminiVeoDuration;
    }
    if (geminiVeoAspect && typeof defaultGeminiVeoAspect !== 'undefined') {
      geminiVeoAspect.value = defaultGeminiVeoAspect;
    }
    showInlineStatus(geminiVeoStatus, '', null);
    renderGeminiVeoResults(null);
  }

  if (geminiTextForm) {
    geminiTextForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (!ensureGeminiAccess()) {
        return;
      }

      const prompt = geminiTextPrompt ? geminiTextPrompt.value.trim() : '';
      if (!prompt) {
        showInlineStatus(geminiTextStatus, 'Prompt wajib diisi.', 'err');
        if (geminiTextPrompt) geminiTextPrompt.focus();
        return;
      }

      const payload = {
        prompt,
        model: geminiTextModel && geminiTextModel.value ? geminiTextModel.value : 'gemini-1.5-flash-latest',
      };

      const system = geminiTextSystem ? geminiTextSystem.value.trim() : '';
      if (system) {
        payload.system = system;
      }

      const temp = clampTemperature(parseNumberInput(geminiTextTemperature));
      if (temp !== null) {
        payload.temperature = temp;
      }

      toggleButtonLoading(geminiTextSubmitBtn, true, 'Menghasilkan…');
      showInlineStatus(geminiTextStatus, 'Menghubungkan ke Gemini…', 'progress');

      try {
        const result = await callGemini('text', payload);
        const text = result && typeof result.text === 'string' ? result.text.trim() : '';
        if (geminiTextOutput) {
          geminiTextOutput.value = text;
        }
        if (text) {
          showInlineStatus(geminiTextStatus, 'Teks berhasil dibuat.', 'ok');
        } else {
          showInlineStatus(geminiTextStatus, 'Gemini tidak mengembalikan teks.', 'err');
        }
      } catch (err) {
        showInlineStatus(geminiTextStatus, err.message || 'Gagal membuat teks.', 'err');
      } finally {
        toggleButtonLoading(geminiTextSubmitBtn, false);
      }
    });
  }

  if (geminiTextResetBtn) {
    geminiTextResetBtn.addEventListener('click', () => {
      resetGeminiTextForm();
    });
  }

  if (geminiTextCopyBtn) {
    geminiTextCopyBtn.addEventListener('click', async () => {
      const value = geminiTextOutput ? geminiTextOutput.value.trim() : '';
      if (!value) {
        showInlineStatus(geminiTextStatus, 'Tidak ada teks untuk disalin.', 'err');
        return;
      }
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(value);
        } else {
          const textarea = document.createElement('textarea');
          textarea.value = value;
          textarea.setAttribute('readonly', '');
          textarea.style.position = 'absolute';
          textarea.style.left = '-9999px';
          document.body.appendChild(textarea);
          textarea.select();
          document.execCommand('copy');
          document.body.removeChild(textarea);
        }
        showInlineStatus(geminiTextStatus, 'Teks disalin ke clipboard.', 'ok');
        setTimeout(() => showInlineStatus(geminiTextStatus, '', null), 1800);
      } catch (err) {
        showInlineStatus(geminiTextStatus, 'Gagal menyalin teks.', 'err');
      }
    });
  }

  if (geminiSpeechForm) {
    geminiSpeechForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (!ensureGeminiAccess()) {
        return;
      }

      let prompt = geminiSpeechPrompt ? geminiSpeechPrompt.value.trim() : '';
      if (!prompt) {
        showInlineStatus(geminiSpeechStatus, 'Teks narasi wajib diisi.', 'err');
        if (geminiSpeechPrompt) geminiSpeechPrompt.focus();
        return;
      }

      const format = geminiSpeechFormat && geminiSpeechFormat.value ? geminiSpeechFormat.value : 'audio/mp3';
      const model = geminiSpeechModel && geminiSpeechModel.value ? geminiSpeechModel.value : 'gemini-1.5-flash-latest';
      const reference = geminiSpeechReference && geminiSpeechReference.value ? geminiSpeechReference.value.trim() : '';
      if (reference) {
        prompt += `\n\n[Voice reference: ${reference}]`;
      }
      const payload = {
        prompt,
        model,
        voice: geminiSpeechVoice && geminiSpeechVoice.value ? geminiSpeechVoice.value : 'Puck',
        language: geminiSpeechLanguage && geminiSpeechLanguage.value ? geminiSpeechLanguage.value : 'en-US',
        mimeType: format,
      };

      const temp = clampTemperature(parseNumberInput(geminiSpeechTemperature));
      if (temp !== null) {
        payload.temperature = temp;
      }

      clearGeminiSpeechOutput();
      toggleButtonLoading(geminiSpeechSubmitBtn, true, 'Menghasilkan…');
      showInlineStatus(geminiSpeechStatus, 'Menghubungkan ke Gemini…', 'progress');

      try {
        const result = await callGemini('speech', payload);
        const audioData = result && typeof result.audio === 'string' ? result.audio : '';
        const mimeType = result && result.mimeType ? result.mimeType : format;
        if (!audioData) {
          throw new Error('Gemini tidak mengembalikan audio.');
        }
        const source = `data:${mimeType};base64,${audioData}`;
        if (geminiSpeechAudio) {
          geminiSpeechAudio.src = source;
          geminiSpeechAudio.style.display = 'block';
          geminiSpeechAudio.load();
          geminiSpeechAudio.play().catch(() => {});
        }
        if (geminiSpeechDownload) {
          geminiSpeechDownload.href = source;
          geminiSpeechDownload.hidden = false;
          geminiSpeechDownload.download = `gemini-speech-${Date.now()}.${mimeToExtension(mimeType)}`;
        }
        showInlineStatus(geminiSpeechStatus, 'Audio berhasil dibuat.', 'ok');
      } catch (err) {
        clearGeminiSpeechOutput();
        showInlineStatus(geminiSpeechStatus, err.message || 'Gagal membuat audio.', 'err');
      } finally {
        toggleButtonLoading(geminiSpeechSubmitBtn, false);
      }
    });
  }

  if (geminiSpeechResetBtn) {
    geminiSpeechResetBtn.addEventListener('click', () => {
      resetGeminiSpeechForm();
    });
  }

  if (geminiVeoForm) {
    geminiVeoForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (!ensureGeminiAccess()) {
        return;
      }

      const prompt = geminiVeoPrompt ? geminiVeoPrompt.value.trim() : '';
      if (!prompt) {
        showInlineStatus(geminiVeoStatus, 'Prompt video wajib diisi.', 'err');
        if (geminiVeoPrompt) geminiVeoPrompt.focus();
        return;
      }

      const payload = {
        prompt,
        model: 'veo-3.1',
      };

      const dialogue = geminiVeoDialogue ? geminiVeoDialogue.value.trim() : '';
      if (dialogue) {
        payload.dialogue = dialogue;
      }

      const style = geminiVeoStyle ? geminiVeoStyle.value.trim() : '';
      if (style) {
        payload.style = style;
      }

      const durationValue = geminiVeoDuration ? parseInt(geminiVeoDuration.value, 10) : NaN;
      if (!Number.isNaN(durationValue) && durationValue > 0) {
        payload.duration = durationValue;
      }

      const aspectValue = geminiVeoAspect && geminiVeoAspect.value ? geminiVeoAspect.value : '';
      if (aspectValue) {
        payload.aspectRatio = aspectValue;
      }

      toggleButtonLoading(geminiVeoSubmitBtn, true, 'Mengirim…');
      showInlineStatus(geminiVeoStatus, 'Menghubungkan ke Gemini…', 'progress');

      try {
        const result = await callGemini('veo', payload);
        const urls = result && Array.isArray(result.videoUrls) ? result.videoUrls.filter(Boolean) : [];
        renderGeminiVeoResults(result);
        if (urls.length) {
          showInlineStatus(geminiVeoStatus, 'Video berhasil dibuat.', 'ok');
        } else if (result && result.operation) {
          showInlineStatus(geminiVeoStatus, 'Permintaan diterima. Menunggu rendering video.', 'progress');
        } else {
          showInlineStatus(geminiVeoStatus, 'Tidak ada URL video yang diterima.', 'err');
        }
      } catch (err) {
        showInlineStatus(geminiVeoStatus, err.message || 'Gagal membuat video.', 'err');
      } finally {
        toggleButtonLoading(geminiVeoSubmitBtn, false);
      }
    });
  }

  if (geminiVeoClearBtn) {
    geminiVeoClearBtn.addEventListener('click', () => {
      resetGeminiVeoForm();
    });
  }

  renderGeminiVeoResults(null);


  function setModelHint(id) {
    if (id === 'gemini') {
      modelHint.textContent = 'Gemini Flash 2.5: pilih mode (Text / 1 referensi / 2-3 referensi). Prompt wajib, num_images & aspect ratio opsional.';
    } else if (['imagen3','seedream4','fluxPro11'].includes(id)) {
      modelHint.textContent = 'Text-to-image: wajib prompt. num_images opsional. Aspect ratio opsional.';
    } else if (id === 'seedream4edit') {
      modelHint.textContent = 'Seedream 4 Edit: wajib prompt + image (URL). Direkomendasikan untuk workflow UGC.';
    } else if (['upscalerCreative','upscalePrecV1','upscalePrecV2'].includes(id)) {
      modelHint.textContent = 'Upscaler: wajib image URL. Prompt opsional.';
    } else if (id === 'removeBg') {
      modelHint.textContent = 'Remove Background: wajib image URL. Response langsung URL hasil (valid 5 menit).';
    } else if (['wan480','wan720','seedancePro480','seedancePro720','seedancePro1080','klingStd21','kling25Pro','minimax1080'].includes(id)) {
      modelHint.textContent = 'Image-to-video: wajib image URL + prompt singkat. Pilih durasi & layout (portrait / landscape / square).';
    } else if (id === 'latentSync') {
      modelHint.textContent = 'Latent-Sync: wajib video URL dan audio URL. Prompt opsional.';
    } else {
      modelHint.textContent = 'Isi prompt dan field sesuai model.';
    }
  }

  function getVideoDurationMeta(modelId) {
    return VIDEO_MODEL_DURATION_OPTIONS[modelId] || VIDEO_MODEL_DURATION_OPTIONS._default;
  }

  function ensureVideoLayoutOptions() {
    if (!videoLayoutSelect || videoLayoutSelect.dataset.populated) return;
    videoLayoutSelect.innerHTML = '';
    VIDEO_LAYOUT_OPTIONS.forEach(opt => {
      const option = document.createElement('option');
      option.value = opt.value;
      option.textContent = opt.label;
      videoLayoutSelect.appendChild(option);
    });
    videoLayoutSelect.dataset.populated = '1';
  }

  function configureVideoControls(modelId) {
    if (!rowVideoSettings || !videoDurationSelect || !videoLayoutSelect) return;
    ensureVideoLayoutOptions();

    const meta = getVideoDurationMeta(modelId);
    const durations = Array.isArray(meta.values) && meta.values.length ? meta.values : getVideoDurationMeta('_default').values;

    videoDurationSelect.innerHTML = '';
    durations.forEach(sec => {
      const option = document.createElement('option');
      option.value = String(sec);
      option.textContent = `${sec} detik`;
      videoDurationSelect.appendChild(option);
    });

    const defaultDuration = meta.default || durations[0] || null;
    if (defaultDuration) {
      videoDurationSelect.value = String(defaultDuration);
    } else if (videoDurationSelect.options.length) {
      videoDurationSelect.selectedIndex = 0;
    }

    const layoutDefault = (meta.defaultLayout && VIDEO_LAYOUT_TO_RATIO[meta.defaultLayout]) ? meta.defaultLayout : (videoLayoutSelect.options[0] ? videoLayoutSelect.options[0].value : '');
    if (layoutDefault) {
      videoLayoutSelect.value = layoutDefault;
    }
  }

  function updateFields() {
    const id = modelSelect.value;

    const isT2I = ['gemini','imagen3','seedream4','fluxPro11','mystic','getHyperflux'].includes(id);
    const isEdit = ['seedream4edit','upscalerCreative','upscalePrecV1','upscalePrecV2','removeBg'].includes(id);
    const isI2V = ['wan480','wan720','seedancePro480','seedancePro720','seedancePro1080','klingStd21','kling21Master','kling25Pro','pixverse','minimax1080'].includes(id);
    const isLip = id === 'latentSync';

    rowImageUrl.classList.add('hidden');
    rowVideoAudio.classList.add('hidden');
    rowVideoSettings.classList.add('hidden');
    rowTIOptions.classList.add('hidden');
    rowPrompt.classList.remove('hidden');

    if (isT2I) {
      fieldsTitle.textContent = 'Image Gen';
      rowTIOptions.classList.remove('hidden');
    } else if (isEdit) {
      fieldsTitle.textContent = 'Image Editing';
      rowImageUrl.classList.remove('hidden');
      if (id === 'removeBg') rowPrompt.classList.add('hidden');
    } else if (isI2V) {
      fieldsTitle.textContent = 'Video Gen';
      rowImageUrl.classList.remove('hidden');
      rowVideoSettings.classList.remove('hidden');
      configureVideoControls(id);
    } else if (isLip) {
      fieldsTitle.textContent = 'Video Gen (Lipsync)';
      rowVideoAudio.classList.remove('hidden');
    } else {
      fieldsTitle.textContent = 'Input';
    }

    syncGeminiVisibility();
  }

  let currentFeature = 'imageGen';

  function setFeature(featureKey) {
    if (!featureKey) return;
    if (!featureAvailableForCurrentUser(featureKey)) {
      showFeatureLockedMessage(featureKey);
      updateFeatureTabsAvailability();
      return;
    }
    currentFeature = featureKey;
    if (featureTabs.length) {
      featureTabs.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.feature === featureKey);
      });
    }

    updateFeatureTabsAvailability();

    const allowed = new Set(FEATURE_MODELS[featureKey] || []);
    const options = modelSelect.querySelectorAll('option');
    let firstVisible = null;

    options.forEach(opt => {
      const id = opt.value;
      if (!id) return;
      if (allowed.has(id)) {
        opt.disabled = false;
        opt.hidden = false;
        if (!firstVisible) firstVisible = opt;
      } else {
        opt.disabled = true;
        opt.hidden = true;
      }
    });

    if (firstVisible) {
      modelSelect.value = firstVisible.value;
      setModelHint(firstVisible.value);
      updateFields();
    }

    if (!featureLabel) return;
    let label;
    if (featureKey === 'imageGen') label = 'Image Gen';
    else if (featureKey === 'videoGen') label = 'Video Gen';
    else label = 'AI Hub';
    featureLabel.textContent = label;
    if (navButtons.length && viewHubSection && viewHubSection.style.display !== 'none') {
      activateNav('viewHub', featureKey);
    }
  }

  function renderJobs() {
    queueList.innerHTML = '';
    historyList.innerHTML = '';

    const queue = jobs.filter(j => !finalStatus(j.status));
    const history = jobs.filter(j => finalStatus(j.status));

    queueEmpty.style.display = queue.length ? 'none' : 'block';
    historyEmpty.style.display = history.length ? 'none' : 'block';

    queue.forEach(j => queueList.appendChild(renderJobItem(j)));
    history.forEach(j => historyList.appendChild(renderJobItem(j)));

    if (activeJobId) {
      const activeJob = jobs.find(j => j.id === activeJobId);
      if (activeJob) syncStatusProgress(activeJob);
    }

    updateDashboardStats();
  }

  function renderJobItem(job) {
    const cfg = MODEL_CONFIG[job.modelId];
    const el = document.createElement('div');
    el.className = 'job-item';

    const header = document.createElement('div');
    header.className = 'job-header';

    const title = document.createElement('div');
    title.className = 'job-title';
    title.textContent = cfg ? cfg.label : job.modelId;

    const status = document.createElement('div');
    status.className = 'job-status';
    const st = (job.status || '').toUpperCase();
    if (st === 'COMPLETED') status.classList.add('completed');
    else if (st === 'FAILED' || st === 'ERROR') status.classList.add('error');
    else status.classList.add('in-progress');
    status.textContent = st || 'UNKNOWN';

    header.appendChild(title);
    header.appendChild(status);

    const meta = document.createElement('div');
    meta.className = 'job-meta';
    meta.textContent = `${shortTime(job.createdAt)} • ${job.type.toUpperCase()} • ${job.taskId ? 'task ' + job.taskId.slice(0,8) : 'no task_id'}`;

    const actions = document.createElement('div');
    actions.className = 'job-actions';

    const viewBtn = document.createElement('button');
    viewBtn.type = 'button';
    viewBtn.className = 'small secondary';
    viewBtn.textContent = 'View';
    viewBtn.onclick = () => {
      activeJobId = job.id;
      renderPreview(job);
    };
    actions.appendChild(viewBtn);

    const cfgHasStatus = cfg && cfg.statusPath && job.taskId;
    if (!finalStatus(job.status) && cfgHasStatus) {
      const checkBtn = document.createElement('button');
      checkBtn.type = 'button';
      checkBtn.className = 'small secondary';
      checkBtn.textContent = 'Check';
      checkBtn.onclick = () => pollJobOnce(job.id);
      actions.appendChild(checkBtn);
    }

    el.appendChild(header);
    el.appendChild(meta);

    if (!finalStatus(job.status)) {
      const progressWrap = document.createElement('div');
      progressWrap.className = 'job-progress';

      const label = document.createElement('div');
      label.className = 'job-progress-label';
      const pct = getJobProgress(job);
      label.innerHTML = `<span>Progress</span><span>${pct}%</span>`;

      const track = document.createElement('div');
      track.className = 'progress-track';
      const fill = document.createElement('div');
      fill.className = 'progress-fill';
      fill.style.width = pct + '%';
      track.appendChild(fill);

      progressWrap.appendChild(label);
      progressWrap.appendChild(track);
      el.appendChild(progressWrap);
    }

    el.appendChild(actions);
    return el;
  }

  function renderPreview(job) {
    if (!job) {
      previewContainer.style.display = 'none';
      previewEmpty.style.display = 'block';
      syncStatusProgress(null);
      return;
    }
    const flashPreviewItem = (el) => {
      if (!el) return;
      el.classList.add('preview-item--active');
      setTimeout(() => {
        el.classList.remove('preview-item--active');
      }, 220);
    };
    
    const cfg = MODEL_CONFIG[job.modelId];
    previewContainer.style.display = 'block';
    previewEmpty.style.display = 'none';

    previewJobMeta.textContent =
      `${cfg ? cfg.label : job.modelId} • status ${job.status || 'UNKNOWN'} • dibuat ${shortTime(job.createdAt)}`;

    syncStatusProgress(job);

    previewGrid.innerHTML = '';

    const urls = [];
    if (Array.isArray(job.localUrls) && job.localUrls.length) {
      urls.push(...job.localUrls);
    } else if (Array.isArray(job.generated)) {
      urls.push(...job.generated);
    }
    if (job.extraUrl && !urls.length) urls.push(job.extraUrl);

    if (!urls.length) {
      const msg = document.createElement('div');
      msg.className = 'muted';
      msg.style.fontSize = '11px';
      msg.textContent = 'Belum ada URL hasil untuk job ini.';
      previewGrid.appendChild(msg);
      return;
    }

    urls.forEach((url, idx) => {
      const item = document.createElement('div');
      item.className = 'preview-item';

      const assetType = (job.type === 'video' || isVideoUrl(url)) ? 'video' : 'image';

      const thumb = document.createElement('div');
      thumb.className = 'preview-thumb';
      thumb.classList.add(assetType === 'video' ? 'is-video' : 'is-image');

      let media;
      if (assetType === 'video') {
        media = document.createElement('video');
        media.src = url;
        media.controls = true;
        media.loop = true;
        media.muted = true;
        media.playsInline = true;
      } else {
        media = document.createElement('img');
        media.src = url;
        media.alt = `Result ${idx + 1}`;
        media.classList.add('clickable-media');
      }

      thumb.appendChild(media);
      const wm = document.createElement('div');
      wm.className = 'watermark-overlay';
      thumb.appendChild(wm);

      if (assetType === 'image') {
        thumb.addEventListener('click', () => {
          flashPreviewItem(item);
          openAssetPreview(url, assetType);
        });
      } else {
        thumb.addEventListener('click', () => {
          flashPreviewItem(item);
        });
      }

      const metaRow = document.createElement('div');
      metaRow.className = 'preview-meta';

      const btnGroup = document.createElement('div');
      btnGroup.className = 'preview-btn-group';

      const previewBtn = document.createElement('button');
      previewBtn.type = 'button';
      previewBtn.className = 'small secondary';
      previewBtn.textContent = 'Preview';
      previewBtn.addEventListener('click', () => {
        flashPreviewItem(item);
        openAssetPreview(url, assetType);
      });
      btnGroup.appendChild(previewBtn);

      const urlSpan = document.createElement('div');
      urlSpan.className = 'preview-url';
      urlSpan.textContent = url;

      const dlLink = document.createElement('a');
      dlLink.href = url;
      dlLink.target = '_blank';
      dlLink.download = '';
      dlLink.className = 'download-link';

      const dlBtn = document.createElement('button');
      dlBtn.type = 'button';
      dlBtn.className = 'small';
      dlBtn.textContent = 'Download';
      dlLink.appendChild(dlBtn);

      btnGroup.appendChild(dlLink);

      metaRow.appendChild(btnGroup);
      metaRow.appendChild(urlSpan);

      item.appendChild(thumb);
      item.appendChild(metaRow);

      previewGrid.appendChild(item);
    });
  }

  function refreshPreview() {
    const job = jobs.find(j => j.id === activeJobId);
    renderPreview(job || null);
  }

  async function callFreepik(cfg, body, method = 'POST') {
    const payload = {
      path: cfg.statusPath && method === 'GET' ? cfg.statusPath(body.taskId) : cfg.path,
      method,
      body: method === 'GET' ? undefined : body,
      contentType: cfg.contentType || 'json'
    };

    const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=freepik', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      console.error('Response /?api=freepik bukan JSON. Raw:', text);
      throw new Error('Endpoint PHP mengembalikan HTML / non-JSON. Cek bagian proxy di atas file.');
    }

    if (!json.ok) {
      throw new Error(`HTTP ${json.status} – ${(json.data && json.data.message) || json.error || 'unknown error'}`);
    }
    return json.data;
  }

  async function callFreepikEndpoint({ path, method = 'GET', body, contentType = 'json' } = {}) {
    if (!path) {
      throw new Error('Endpoint path wajib diisi.');
    }
    const payload = { path, method, contentType };
    if (method !== 'GET' && typeof body !== 'undefined') {
      payload.body = body;
    }

    const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=freepik', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      console.error('Response /?api=freepik bukan JSON. Raw:', text);
      throw new Error('Endpoint PHP mengembalikan HTML / non-JSON.');
    }

    if (!json.ok) {
      throw new Error(`HTTP ${json.status} – ${(json.data && json.data.message) || json.error || 'unknown error'}`);
    }
    return json.data;
  }

  function getSelectedWebhookEvents(selectEl) {
    if (!selectEl || !selectEl.options) return [];
    return Array.from(selectEl.options)
      .filter(opt => opt.selected && opt.value)
      .map(opt => opt.value);
  }

  function renderWebhookList(items) {
    if (!webhookList) return;
    webhookList.innerHTML = '';
    const list = Array.isArray(items) ? items : [];

    if (!list.length) {
      const empty = document.createElement('div');
      empty.className = 'muted';
      empty.style.fontSize = '11px';
      empty.textContent = 'Belum ada webhook terdaftar.';
      webhookList.appendChild(empty);
      return;
    }

    list.forEach(item => {
      const id = item && (item.id || item.webhook_id || item.uuid || item.name);
      const callbackUrl = item && (item.callback_url || item.url || item.endpoint || item.target_url) || '-';
      const eventsRaw = item && (item.events || item.event_types || item.event || []);
      const events = Array.isArray(eventsRaw)
        ? eventsRaw.filter(Boolean)
        : (typeof eventsRaw === 'string' && eventsRaw ? [eventsRaw] : []);
      const createdAt = item && (item.created_at || item.createdAt || item.created || null);
      const isActive = item && typeof item.active === 'boolean' ? item.active : null;

      const wrapper = document.createElement('div');
      wrapper.className = 'webhook-item';

      const header = document.createElement('div');
      header.className = 'webhook-item__header';

      const urlEl = document.createElement('div');
      urlEl.className = 'webhook-item__url';
      urlEl.textContent = callbackUrl;
      header.appendChild(urlEl);

      const actions = document.createElement('div');
      actions.className = 'webhook-item__actions';
      if (id) {
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'small secondary';
        deleteBtn.textContent = 'Hapus';
        deleteBtn.addEventListener('click', () => deleteWebhook(id));
        actions.appendChild(deleteBtn);
      }
      header.appendChild(actions);
      wrapper.appendChild(header);

      const metaParts = [];
      if (id) metaParts.push(`ID: ${id}`);
      if (events.length) metaParts.push(`Events: ${events.join(', ')}`);
      if (typeof isActive === 'boolean') metaParts.push(isActive ? 'Aktif' : 'Nonaktif');
      if (createdAt) {
        const date = new Date(createdAt);
        if (!Number.isNaN(date.getTime())) {
          metaParts.push(date.toLocaleString('id-ID'));
        }
      }
      if (metaParts.length) {
        const metaLine = document.createElement('div');
        metaLine.className = 'webhook-item__meta';
        metaLine.textContent = metaParts.join(' • ');
        wrapper.appendChild(metaLine);
      }

      webhookList.appendChild(wrapper);
    });
  }

  async function fetchWebhooksList({ showStatus = true } = {}) {
    if (!webhookList) return;
    if (accountRestricted()) {
      renderWebhookList([]);
      showInlineStatus(webhookStatus, 'Akun dibatasi. Tidak dapat memuat webhook.', 'err');
      return;
    }
    if (showStatus) {
      showInlineStatus(webhookStatus, 'Memuat webhook…', 'progress');
    }
    try {
      const data = await callFreepikEndpoint({ path: '/v1/ai/webhooks', method: 'GET' });
      const list = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []);
      renderWebhookList(list);
      if (showStatus) {
        showInlineStatus(webhookStatus, `Memuat ${list.length} webhook.`, 'ok');
        setTimeout(() => showInlineStatus(webhookStatus, '', null), 1600);
      }
    } catch (err) {
      renderWebhookList([]);
      showInlineStatus(webhookStatus, err.message || 'Gagal memuat webhook.', 'err');
    }
  }

  async function deleteWebhook(id) {
    if (!id) return;
    if (!confirm('Hapus webhook ini?')) {
      return;
    }
    if (accountRestricted()) {
      showRestrictionNotice();
      return;
    }
    showInlineStatus(webhookStatus, 'Menghapus webhook…', 'progress');
    try {
      await callFreepikEndpoint({ path: `/v1/ai/webhooks/${encodeURIComponent(id)}`, method: 'DELETE' });
      showInlineStatus(webhookStatus, 'Webhook dihapus.', 'ok');
      setTimeout(() => showInlineStatus(webhookStatus, '', null), 1600);
      fetchWebhooksList({ showStatus: false });
    } catch (err) {
      showInlineStatus(webhookStatus, err.message || 'Gagal menghapus webhook.', 'err');
    }
  }

  // ===== CACHE DI SERVER =====
  async function cacheUrl(remoteUrl) {
    const res = await fetch('<?= htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES) ?>?api=cache', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: remoteUrl })
    });

    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      console.error('Response /?api=cache bukan JSON. Raw:', text);
      throw new Error('Endpoint cache mengembalikan HTML / non-JSON.');
    }

    if (!json.ok) {
      throw new Error(json.error || 'Cache gagal');
    }
    return json.url || json.path;
  }

  function resetImageUploadArea(clearStatus = true) {
    if (imageUploadInput) imageUploadInput.value = '';
    if (imageUploadPreview) {
      imageUploadPreview.src = '';
      imageUploadPreview.style.display = 'none';
    }
    if (imageUploadDropzone) {
      imageUploadDropzone.classList.remove('has-file', 'dragover');
    }
    if (clearStatus) setImageUploadStatus('', null);
  }

  function setImageUploadStatus(text, mode) {
    if (!imageUploadStatus) return;
    imageUploadStatus.textContent = text || '';
    imageUploadStatus.classList.remove('ok', 'err', 'progress');
    if (!text) {
      imageUploadStatus.style.display = 'none';
      return;
    }
    if (mode) imageUploadStatus.classList.add(mode);
    imageUploadStatus.style.display = 'block';
  }

  async function uploadFileToServer(file) {
    const formData = new FormData();
    formData.append('file', file);

    const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=upload', {
      method: 'POST',
      body: formData
    });

    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      console.error('Response /?api=upload bukan JSON. Raw:', text);
      throw new Error('Endpoint upload mengembalikan format tidak valid.');
    }

    if (!json.ok) {
      throw new Error(json.error || 'Upload gagal');
    }

    return {
      url: json.url || json.path,
      name: json.name || file.name
    };
  }

  async function uploadImageFile(file) {
    if (!file) return;
    if (!file.type || !file.type.startsWith('image/')) {
      setImageUploadStatus('File harus gambar (PNG/JPG/WEBP).', 'err');
      return;
    }

    setImageUploadStatus('Mengunggah ' + file.name + '…', 'progress');

    try {
      const result = await uploadFileToServer(file);
      const url = result.url;

      if (imageUrlInput && url) {
        imageUrlInput.value = url;
      }
      if (imageUploadPreview && url) {
        imageUploadPreview.src = url;
        imageUploadPreview.style.display = 'block';
      }
      if (imageUploadDropzone) {
        imageUploadDropzone.classList.add('has-file');
      }

      setImageUploadStatus('Upload sukses: ' + (result.name || file.name), 'ok');
      setStatus('Gambar berhasil diupload ke server.', 'ok');
    } catch (err) {
      console.error(err);
      setImageUploadStatus(err.message || 'Upload gagal.', 'err');
      setStatus('Upload gagal: ' + err.message, 'err');
    }
  }

  function handleImageFileList(fileList) {
    if (!fileList || !fileList.length) return;
    const file = fileList[0];
    resetImageUploadArea();
    uploadImageFile(file);
  }

  async function uploadGeminiFile(file) {
    if (!file || !file.type || !file.type.startsWith('image/')) {
      setStatus('File harus gambar (PNG/JPG/WEBP).', 'err');
      return;
    }
    const isGemini = modelSelect && modelSelect.value === 'gemini';
    if (!isGemini) {
      setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
      return;
    }
    const limit = getGeminiLimit();
    if (!limit) {
      setStatus('Mode text-only tidak memerlukan referensi.', 'err');
      return;
    }
    if (geminiReferences.length >= limit) {
      setStatus(`Batas referensi tercapai (${limit}).`, 'err');
      return;
    }

    try {
      setStatus('Mengunggah referensi ' + file.name + '…');
      const result = await uploadFileToServer(file);
      const url = result.url;
      if (!url) throw new Error('URL hasil upload tidak ditemukan.');
      if (geminiReferences.some(ref => ref.url === url)) {
        setStatus('Gambar sudah ada dalam daftar referensi.', 'err');
        return;
      }
      geminiReferences.push({ url, name: result.name || file.name, source: 'upload' });
      renderGeminiRefs(isGemini);
      setStatus('Referensi ditambahkan.', 'ok');
    } catch (err) {
      console.error(err);
      setStatus('Upload referensi gagal: ' + err.message, 'err');
    }
  }

  async function handleGeminiFileList(fileList) {
    if (!fileList || !fileList.length) return;
    const isGemini = modelSelect && modelSelect.value === 'gemini';
    if (!isGemini) {
      setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
      return;
    }
    const limit = getGeminiLimit();
    if (!limit) {
      setStatus('Mode text-only tidak memerlukan referensi.', 'err');
      return;
    }
    let available = limit - geminiReferences.length;
    if (available <= 0) {
      setStatus(`Batas referensi tercapai (${limit}).`, 'err');
      return;
    }

    const files = Array.from(fileList).filter(f => f && f.type && f.type.startsWith('image/'));
    if (!files.length) {
      setStatus('Tidak ada file gambar yang valid.', 'err');
      return;
    }

    let processed = 0;
    for (const file of files) {
      if (available <= 0) break;
      await uploadGeminiFile(file);
      processed += 1;
      available = limit - geminiReferences.length;
    }

    if (processed < files.length) {
      setStatus('Sebagian file diabaikan karena batas referensi tercapai.', 'idle');
    }
  }

  function addGeminiReferenceUrl() {
    if (!geminiRefUrl) return;
    const isGemini = modelSelect && modelSelect.value === 'gemini';
    if (!isGemini) {
      setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
      return;
    }
    const url = geminiRefUrl.value.trim();
    if (!url) {
      setStatus('Masukkan URL gambar referensi terlebih dahulu.', 'err');
      return;
    }
    if (!/^https?:\/\//i.test(url)) {
      setStatus('URL harus diawali http:// atau https://', 'err');
      return;
    }
    const limit = getGeminiLimit();
    if (!limit) {
      setStatus('Mode text-only tidak memerlukan referensi.', 'err');
      return;
    }
    if (geminiReferences.length >= limit) {
      setStatus(`Batas referensi tercapai (${limit}).`, 'err');
      return;
    }
    if (geminiReferences.some(ref => ref.url === url)) {
      setStatus('URL sudah ada dalam daftar referensi.', 'err');
      return;
    }

    const nameHint = url.split(/[/?#]/).filter(Boolean).pop() || 'URL Referensi';
    geminiReferences.push({ url, name: nameHint, source: 'url' });
    geminiRefUrl.value = '';
    renderGeminiRefs(isGemini);
    setStatus('URL referensi ditambahkan.', 'ok');
  }

  if (imageUploadButton) {
    imageUploadButton.addEventListener('click', () => {
      if (imageUploadInput) imageUploadInput.click();
    });
  }

  if (imageUploadInput) {
    imageUploadInput.addEventListener('change', e => {
      handleImageFileList(e.target.files);
      e.target.value = '';
    });
  }

  if (imageUploadDropzone) {
    imageUploadDropzone.addEventListener('click', () => {
      if (imageUploadInput) imageUploadInput.click();
    });
    ['dragenter','dragover'].forEach(evt => {
      imageUploadDropzone.addEventListener(evt, ev => {
        ev.preventDefault();
        imageUploadDropzone.classList.add('dragover');
      });
    });
    ['dragleave','dragend'].forEach(evt => {
      imageUploadDropzone.addEventListener(evt, ev => {
        ev.preventDefault();
        imageUploadDropzone.classList.remove('dragover');
      });
    });
    imageUploadDropzone.addEventListener('drop', ev => {
      ev.preventDefault();
      imageUploadDropzone.classList.remove('dragover');
      if (ev.dataTransfer && ev.dataTransfer.files) {
        handleImageFileList(ev.dataTransfer.files);
      }
    });
  }

  if (geminiFileButton) {
    geminiFileButton.addEventListener('click', () => {
      if (!modelSelect || modelSelect.value !== 'gemini') {
        setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
        return;
      }
      if (geminiMode === 'text') {
        setStatus('Mode text-only tidak memerlukan referensi.', 'err');
        return;
      }
      if (geminiFileInput) geminiFileInput.click();
    });
  }

  if (geminiFileInput) {
    geminiFileInput.addEventListener('change', async e => {
      await handleGeminiFileList(e.target.files);
      e.target.value = '';
    });
  }

  if (geminiDropzone) {
    geminiDropzone.addEventListener('click', () => {
      if (!modelSelect || modelSelect.value !== 'gemini') {
        setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
        return;
      }
      if (geminiMode === 'text') {
        setStatus('Mode text-only tidak memerlukan referensi.', 'err');
        return;
      }
      if (geminiFileInput) geminiFileInput.click();
    });
    ['dragenter','dragover'].forEach(evt => {
      geminiDropzone.addEventListener(evt, ev => {
        const isGemini = modelSelect && modelSelect.value === 'gemini';
        if (!isGemini || geminiMode === 'text') return;
        ev.preventDefault();
        geminiDropzone.classList.add('dragover');
      });
    });
    ['dragleave','dragend'].forEach(evt => {
      geminiDropzone.addEventListener(evt, ev => {
        ev.preventDefault();
        geminiDropzone.classList.remove('dragover');
      });
    });
    geminiDropzone.addEventListener('drop', async ev => {
      ev.preventDefault();
      geminiDropzone.classList.remove('dragover');
      const isGemini = modelSelect && modelSelect.value === 'gemini';
      if (!isGemini) {
        setStatus('Aktifkan Gemini Flash untuk menambah referensi.', 'err');
        return;
      }
      if (geminiMode === 'text') {
        setStatus('Mode text-only tidak memerlukan referensi.', 'err');
        return;
      }
      if (ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files.length) {
        await handleGeminiFileList(ev.dataTransfer.files);
      }
    });
  }

  if (geminiRefAddBtn) {
    geminiRefAddBtn.addEventListener('click', () => addGeminiReferenceUrl());
  }

  if (geminiRefUrl) {
    geminiRefUrl.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        addGeminiReferenceUrl();
      }
    });
  }

  if (imageUrlInput) {
    imageUrlInput.addEventListener('input', () => {
      if (!imageUrlInput.value) {
        resetImageUploadArea();
      }
    });
  }

  document.addEventListener('paste', async ev => {
    const files = ev.clipboardData && ev.clipboardData.files;
    if (!files || !files.length) return;
    const target = ev.target;
    const tag = target && target.tagName ? target.tagName.toLowerCase() : '';
    if (['input','textarea'].includes(tag)) return;

    const isGemini = modelSelect && modelSelect.value === 'gemini' && geminiMode !== 'text';
    if (isGemini) {
      ev.preventDefault();
      setStatus('Menempel gambar referensi ke Gemini…');
      await handleGeminiFileList(files);
      return;
    }

    if (!imageUploadDropzone) return;
    handleImageFileList(files);
    setStatus('Menempel gambar dari clipboard…');
  });

  async function ensureLocalFiles(job) {
    if (!job || !Array.isArray(job.generated) || !job.generated.length) return;
    if (job.localUrls && job.localUrls.length === job.generated.length) return;

    const local = [];
    for (const u of job.generated) {
      try {
        const lu = await cacheUrl(u);
        local.push(lu);
      } catch (e) {
        console.error('Gagal cache', u, e);
      }
    }
    if (local.length) {
      job.localUrls = local;
      job.updatedAt = nowIso();
      saveJobs();
      renderJobs();
      if (job.id === activeJobId) refreshPreview();
    }
  }

  async function createTask(modelId) {
    const cfg = MODEL_CONFIG[modelId];
    if (!cfg) throw new Error('Model tidak dikenal');

    const formData = {
      prompt: promptInput.value.trim(),
      imageUrl: imageUrlInput.value.trim(),
      videoUrl: videoUrlInput.value.trim(),
      audioUrl: audioUrlInput.value.trim(),
      videoDuration: (videoDurationSelect && videoDurationSelect.value) ? Number(videoDurationSelect.value) : null,
      videoLayout: videoLayoutSelect && videoLayoutSelect.value ? videoLayoutSelect.value : null,
      numImages: numImagesInput.value ? Number(numImagesInput.value) : null,
      aspectRatio: aspectRatioInput.value || null
    };

    if (formData.imageUrl) {
      formData.imageUrl = ensureAbsoluteUrl(formData.imageUrl);
      if (imageUrlInput) {
        imageUrlInput.value = formData.imageUrl;
      }
    }
    if (formData.videoUrl) {
      formData.videoUrl = ensureAbsoluteUrl(formData.videoUrl);
      if (videoUrlInput) {
        videoUrlInput.value = formData.videoUrl;
      }
    }
    if (formData.audioUrl) {
      formData.audioUrl = ensureAbsoluteUrl(formData.audioUrl);
      if (audioUrlInput) {
        audioUrlInput.value = formData.audioUrl;
      }
    }

    const requireImageModels = [
      'upscalerCreative','upscalePrecV1','upscalePrecV2','removeBg',
      'wan480','wan720','seedancePro480','seedancePro720','seedancePro1080',
      'klingStd21','kling25Pro','minimax1080','seedream4edit'
    ];

    if (!formData.prompt && ['gemini','imagen3','seedream4','fluxPro11'].includes(modelId)) {
      throw new Error('Prompt wajib diisi untuk model text-to-image.');
    }
    if (requireImageModels.includes(modelId) && !formData.imageUrl) {
      throw new Error('Image URL wajib diisi untuk model ini.');
    }
    if (modelId === 'latentSync') {
      if (!formData.videoUrl || !formData.audioUrl) {
        throw new Error('Latent-Sync butuh video URL dan audio URL.');
      }
    }

    let usedGeminiRefs = null;
    let usedGeminiMode = null;
    if (modelId === 'gemini') {
      const meta = getGeminiMeta(geminiMode);
      const refs = geminiReferences.map(ref => ref.url).filter(Boolean);
      if (meta.max > 0) {
        if (refs.length < meta.min) {
          if (geminiMode === 'single') {
            throw new Error('Mode 2 membutuhkan tepat 1 gambar referensi.');
          }
          throw new Error('Mode 3 membutuhkan minimal 2 gambar referensi.');
        }
        if (refs.length > meta.max) {
          refs.splice(meta.max);
        }
      }
      if (refs.length) {
        try {
          formData.referenceImages = await prepareGeminiReferenceImages(refs);
        } catch (err) {
          throw new Error('Gagal memproses gambar referensi: ' + err.message);
        }
      } else {
        formData.referenceImages = [];
      }
      formData.geminiMode = geminiMode;
      usedGeminiRefs = refs.slice();
      usedGeminiMode = geminiMode;
    }

    const body = cfg.buildBody(formData);
    const data = await callFreepik(cfg, body, 'POST');

    let taskId = null;
    let status = null;
    let generated = null;
    let extraUrl = null;

    if (data && data.data && typeof data.data === 'object') {
      taskId   = data.data.task_id || null;
      status   = data.data.status   || null;
      generated = data.data.generated || null;
    } else if (data && typeof data === 'object') {
      if (cfg.id === 'removeBg') {
        extraUrl = data.url || data.high_resolution || data.preview || null;
        generated = [];
        if (data.url) generated.push(data.url);
        else if (data.high_resolution) generated.push(data.high_resolution);
        else if (data.preview) generated.push(data.preview);
        status = 'COMPLETED';
      } else if (Array.isArray(data.generated)) {
        generated = data.generated;
      }
    }

    return {
      taskId,
      status,
      generated,
      extraUrl,
      references: usedGeminiRefs,
      geminiModeUsed: usedGeminiMode,
      formData
    };
  }

  async function fetchStatus(modelId, taskId) {
    const cfg = MODEL_CONFIG[modelId];
    if (!cfg || !cfg.statusPath) throw new Error('Model tidak punya endpoint status.');
    const data = await callFreepik(cfg, { taskId }, 'GET');

    let status = null;
    let generated = null;
    if (data && data.data) {
      status = data.data.status || null;
      generated = data.data.generated || null;
    }
    return { status, generated };
  }

  function startPolling(job) {
    const cfg = MODEL_CONFIG[job.modelId];
    if (!cfg || !cfg.statusPath || !job.taskId) return;
    if (pollingTimers[job.id]) clearInterval(pollingTimers[job.id]);

    pollingTimers[job.id] = setInterval(() => {
      pollJobOnce(job.id);
    }, 8000);
  }

  async function pollJobOnce(jobId) {
    const job = jobs.find(j => j.id === jobId);
    if (!job) return;
    const cfg = MODEL_CONFIG[job.modelId];
    if (!cfg || !cfg.statusPath || !job.taskId) return;

    try {
      const { status, generated } = await fetchStatus(job.modelId, job.taskId);
      job.status = status || job.status;
      if (generated && Array.isArray(generated) && generated.length) {
        job.generated = generated;
      }
      job.updatedAt = nowIso();
      saveJobs();
      renderJobs();
      if (job.id === activeJobId) refreshPreview();

      if (finalStatus(job.status)) {
        finishJobProgress(job);
        if (pollingTimers[job.id]) {
          clearInterval(pollingTimers[job.id]);
          delete pollingTimers[job.id];
        }
        if (job.generated && job.generated.length) {
          await ensureLocalFiles(job);
        }
        await syncJobToDrive(job);
        if (job.type === 'video') {
          const url = (job.localUrls && job.localUrls[0]) ||
                      (job.generated && job.generated[0]) ||
                      job.extraUrl || null;
          if (url) {
            ugcItems.forEach(it => {
              if (it.videoJobId === job.id && !it.videoUrl) {
                it.videoUrl = url;
              }
            });
            renderUgcList();
          }
        }
      }
    } catch (err) {
      console.error(err);
      setStatus('Gagal cek status: ' + err.message, 'err');
    }
  }

  document.getElementById('jobForm').addEventListener('submit', async e => {
    e.preventDefault();
    const modelId = modelSelect.value;
    const cfg = MODEL_CONFIG[modelId];
    if (!cfg) {
      setStatus('Model tidak valid.', 'err');
      return;
    }

    if (!featureAvailableForCurrentUser(currentFeature)) {
      showFeatureLockedMessage(currentFeature);
      return;
    }

    if (accountRestricted()) {
      showRestrictionNotice();
      return;
    }

    if (!currentAccount) {
      setStatus('Data akun belum siap, coba lagi sesaat lagi.', 'err');
      return;
    }
    if (!ensureCoins(COIN_COST_STANDARD)) {
      setStatus('Koin kamu tidak cukup untuk generate.', 'err');
      return;
    }

    submitBtn.disabled = true;
    setStatus('Server proses generate..');

    try {
      const {
        taskId,
        status,
        generated,
        extraUrl,
        references,
        geminiModeUsed,
        formData
      } = await createTask(modelId);
      await spendCoins(COIN_COST_STANDARD);
      const jobId = uuid();

      const job = {
        id: jobId,
        modelId,
        type: cfg.type,
        taskId: taskId || null,
        createdAt: nowIso(),
        updatedAt: nowIso(),
        status: status || (taskId ? 'CREATED' : 'COMPLETED'),
        generated: generated || [],
        extraUrl: extraUrl || null,
        prompt: formData ? formData.prompt || null : null
      };

      if (modelId === 'gemini') {
        job.references = Array.isArray(references) ? references : [];
        job.geminiMode = geminiModeUsed || geminiMode;
      }

      jobs.unshift(job);
      saveJobs();
      renderJobs();

      activeJobId = jobId;
      renderPreview(job);

      if (taskId && !finalStatus(job.status)) {
        startJobProgress(job);
        startPolling(job);
        setStatus('Task dibuat: ' + taskId.slice(0,8) + '…', 'ok');
      } else {
        finishJobProgress(job);
        setStatus('Task selesai (synchronous).', 'ok');
        if (job.generated && job.generated.length) {
          await ensureLocalFiles(job);
        }
        await syncJobToDrive(job);
      }
    } catch (err) {
      console.error(err);
      setStatus('Error: ' + err.message, 'err');
    } finally {
      submitBtn.disabled = accountRestricted();
    }
  });

  modelSelect.addEventListener('change', () => {
    setModelHint(modelSelect.value);
    updateFields();
  });

  clearPromptBtn.addEventListener('click', () => {
    promptInput.value = '';
    imageUrlInput.value = '';
    videoUrlInput.value = '';
    audioUrlInput.value = '';
    numImagesInput.value = '1';
    aspectRatioInput.value = '';
    if (videoDurationSelect && modelSelect) {
      configureVideoControls(modelSelect.value);
    }
    resetImageUploadArea();
    resetGeminiState(true);
    updateGeminiModeUI(modelSelect && modelSelect.value === 'gemini');
    setStatus('Form dibersihkan.');
  });
  clearPreviewBtn.addEventListener('click', () => {
    activeJobId = null;
    renderPreview(null);
  });
  refreshQueueBtn.addEventListener('click', async () => {
    const queue = jobs.filter(j => !finalStatus(j.status) && j.taskId);
    for (const job of queue) {
      await pollJobOnce(job.id);
    }
    setStatus('Queue di-refresh.', 'ok');
  });
  clearHistoryBtn.addEventListener('click', () => {
    jobs = jobs.filter(j => !finalStatus(j.status));
    saveJobs();
    renderJobs();
    if (activeJobId && !jobs.find(j => j.id === activeJobId)) {
      activeJobId = null;
      renderPreview(null);
    }
  });

  if (webhookRefreshBtn) {
    webhookRefreshBtn.addEventListener('click', () => {
      fetchWebhooksList();
    });
  }

  if (webhookForm) {
    webhookForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (accountRestricted()) {
        showRestrictionNotice();
        return;
      }

      const url = webhookUrlInput ? webhookUrlInput.value.trim() : '';
      if (!url) {
        showInlineStatus(webhookStatus, 'Callback URL wajib diisi.', 'err');
        if (webhookUrlInput) webhookUrlInput.focus();
        return;
      }

      const events = getSelectedWebhookEvents(webhookEventsInput);
      if (!events.length) {
        showInlineStatus(webhookStatus, 'Pilih minimal satu event webhook.', 'err');
        return;
      }

      const payload = { callback_url: url, events };
      const secret = webhookSecretInput && webhookSecretInput.value.trim();
      if (secret) {
        payload.secret = secret;
      }

      try {
        if (webhookSubmitBtn) {
          toggleButtonLoading(webhookSubmitBtn, true, 'Mendaftarkan…');
        }
        showInlineStatus(webhookStatus, 'Mendaftarkan webhook…', 'progress');
        await callFreepikEndpoint({ path: '/v1/ai/webhooks', method: 'POST', body: payload });
        showInlineStatus(webhookStatus, 'Webhook berhasil dibuat.', 'ok');
        webhookForm.reset();
        if (webhookEventsInput) {
          Array.from(webhookEventsInput.options).forEach(opt => {
            opt.selected = opt.hasAttribute('selected');
          });
        }
        setTimeout(() => showInlineStatus(webhookStatus, '', null), 1800);
        fetchWebhooksList({ showStatus: false });
      } catch (err) {
        showInlineStatus(webhookStatus, err.message || 'Gagal membuat webhook.', 'err');
      } finally {
        if (webhookSubmitBtn) {
          toggleButtonLoading(webhookSubmitBtn, false);
        }
      }
    });
  }

  if (featureTabs.length) {
    featureTabs.forEach(btn => {
      btn.addEventListener('click', () => {
        const key = btn.dataset.feature;
        if (!featureAvailableForCurrentUser(key)) {
          showFeatureLockedMessage(key);
          return;
        }
        setFeature(key);
      });
    });
  }

  if (driveTypeFilter) {
    driveTypeFilter.addEventListener('change', renderDriveItems);
  }
  if (driveSortFilter) {
    driveSortFilter.addEventListener('change', renderDriveItems);
  }
  if (driveDateFilter) {
    driveDateFilter.addEventListener('change', renderDriveItems);
  }
  if (driveClearDateBtn) {
    driveClearDateBtn.addEventListener('click', () => {
      if (driveDateFilter) driveDateFilter.value = '';
      renderDriveItems();
    });
  }

  navButtons = Array.from(document.querySelectorAll('.sidebar-link[data-target], .js-dashboard-nav[data-target]'));
  viewDashboardSection = document.getElementById('viewDashboard');
  viewDriveSection = document.getElementById('viewDrive');
  viewHubSection = document.getElementById('viewHub');
  viewFilmSection = document.getElementById('viewFilm');
  viewUGCSection = document.getElementById('viewUGC');
  viewAudioSection = document.getElementById('viewAudio');
  viewAccountSection = document.getElementById('viewAccount');
  viewSections = {
    viewDashboard: viewDashboardSection,
    viewAccount: viewAccountSection,
    viewDrive: viewDriveSection,
    viewHub: viewHubSection,
    viewFilm: viewFilmSection,
    viewUGC: viewUGCSection,
    viewAudio: viewAudioSection
  };

  updateNavAvailability();
  updateFeatureTabsAvailability();
  updateMaintenanceOverlay();

  if (webhookList) {
    fetchWebhooksList({ showStatus: false });
  }

  function activateNav(target, featureKey) {
    navButtons.forEach(btn => {
      const isHub = btn.dataset.target === 'viewHub';
      const matches = btn.dataset.target === target && (!isHub || (btn.dataset.feature || 'imageGen') === (featureKey || 'imageGen'));
      if (btn.classList.contains('mobile-bottom-nav__item')) {
        btn.classList.toggle('is-active', matches);
      } else {
        btn.classList.toggle('active', matches);
      }
    });
  }

  function setActiveView(target) {
    Object.entries(viewSections).forEach(([key, el]) => {
      if (!el) return;
      const isActive = key === target;
      if (isActive) {
        el.style.display = '';
        el.removeAttribute('hidden');
      } else {
        el.style.display = 'none';
        el.setAttribute('hidden', '');
      }
    });
  }

  function showView(target, featureKey) {
    if (target === 'viewFilm' && !featureAvailableForCurrentUser('filmmaker')) {
      showFeatureLockedMessage('filmmaker');
      return;
    }
    if (target === 'viewUGC' && !featureAvailableForCurrentUser('ugc')) {
      showFeatureLockedMessage('ugc');
      return;
    }
    if (target === 'viewAudio' && !featureAvailableForCurrentUser('audioGen')) {
      showFeatureLockedMessage('audioGen');
      return;
    }

    let resolvedFeatureKey = featureKey;
    if (target === 'viewHub') {
      resolvedFeatureKey = featureKey || currentFeature || 'imageGen';
      if (!featureAvailableForCurrentUser(resolvedFeatureKey)) {
        showFeatureLockedMessage(resolvedFeatureKey);
        return;
      }
    }

    setActiveView(target);

    if (target === 'viewHub') {
      setFeature(resolvedFeatureKey || 'imageGen');
    }
    if (target === 'viewDrive') {
      loadDriveItems();
    }

    activateNav(target, resolvedFeatureKey);
    closeSidebarOnMobile();
  }

  navButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled) {
        return;
      }
      const target = btn.dataset.target || 'viewDashboard';
      const featureKey = btn.dataset.feature || (target === 'viewHub' ? 'imageGen' : undefined);
      if (featureKey && !featureAvailableForCurrentUser(featureKey)) {
        showFeatureLockedMessage(featureKey);
        return;
      }
      showView(target, featureKey);
    });
  });

  if (externalFeatureLinks.length) {
    externalFeatureLinks.forEach(link => {
      link.addEventListener('click', event => {
        const featureKey = link.dataset.feature;
        if (featureKey && !featureAvailableForCurrentUser(featureKey)) {
          event.preventDefault();
          event.stopPropagation();
          showFeatureLockedMessage(featureKey);
        }
      });
    });
  }

  showView('viewDashboard');

  // ===== FILMMAKER STATE =====
  let filmCharacterDataUrl = null;
  let filmAspect = '16:9';
  let filmScenes = [];
  let filmPollTimer = null;
  let filmStateMode = 'auto';

  const filmSceneLocations = [
    'crowded street market filled with ambient details',
    'rain-soaked neon city alley with reflective puddles',
    'dimly lit interior workspace surrounded by holographic monitors',
    'rooftop overlooking the skyline during blue hour',
    'abandoned warehouse with shafts of light piercing through windows',
    'lush urban park with misty morning atmosphere',
    'hi-tech control room glowing with translucent interfaces',
    'narrow subway platform with motion blur from passing trains'
  ];

  const filmSceneLightingPresets = [
    'dramatic rim lighting with strong contrast',
    'soft diffused lighting with pastel highlights',
    'high-contrast chiaroscuro with deep shadows',
    'golden hour sunlight with warm highlights',
    'cold tungsten practicals mixed with cyan fill light',
    'moody volumetric light cutting through atmosphere',
    'noir-style lighting with slatted window shadows',
    'neon glow accents with reflective surfaces'
  ];

  const filmSceneCameraAngles = [
    'wide establishing shot from a slightly elevated angle',
    'shoulder-level medium shot highlighting expressions',
    'dynamic low-angle shot that empowers the protagonist',
    'tracking shot with slight motion blur to imply movement',
    'close-up focusing on hands and important props',
    'dutch angle to emphasize tension and imbalance',
    'overhead shot revealing spatial relationships',
    'long lens compression shot isolating the subject'
  ];

  const filmSceneMoods = [
    'anticipation and intrigue',
    'quiet determination',
    'rising tension with subtle anxiety',
    'pulse-pounding urgency',
    'mystery with analytical focus',
    'confrontational and intense',
    'reflective calm after the storm',
    'hopeful yet unresolved cliffhanger tone'
  ];

  const filmNarrativeBeats = [
    'Opening beat that introduces the world and protagonist.',
    'Complication emerges, revealing a new obstacle.',
    'Discovery beat where new information shifts the stakes.',
    'Escalation sequence pushing the conflict forward.',
    'Strategic regrouping before the confrontation.',
    'Climactic confrontation with the central threat.',
    'Falling action showing immediate consequences.',
    'Teaser for the next chapter, leaving a lingering question.'
  ];

  const FILM_STATE_PRESETS = [
    {
      key: 'auto',
      label: 'AUTO STATE',
      description: 'Tidak Memilih · diproses oleh server',
      icon: '🤖',
      prompt: ''
    },
    {
      key: 'kdramaMelodrama',
      label: 'Melodrama K-Drama',
      description: 'Emotional, Slow-Burn',
      icon: '🌸',
      prompt: `
    **Scene Lokasi**: lush urban park with misty morning atmosphere or a rooftop overlooking the skyline during blue hour, often featuring rain.
    **LightingPresets**: soft diffused lighting with pastel highlights, giving a washed-out, dreamy look, mixed with ambient blue moon lighting for a cool night scene.
    **SceneCameraAngles**: long lens compression shot isolating the subject, emphasizing distance and longing. Use smooth crane shots revealing scale and transition, but keep the pacing slow.
    **SceneMoods**: quiet determination mixed with melancholic and deep sadness, often leading to a hopeful yet unresolved cliffhanger tone.
    **NarrativeBeats**: Two characters experience a painful separation or a moment of deep regret and longing. The dialogue is minimal; the emphasis is on sustained eye contact, slow reaction shots, and the environment reflecting their inner turmoil.
  `
    },
    {
      key: 'detectiveRealization',
      label: 'Realisasi Detektif',
      description: 'Misteri, Fokus',
      icon: '🔍',
      prompt: `
    **Scene Lokasi**: ornate, empty library reading room with dust motes dancing in the light.
    **LightingPresets**: high-contrast chiaroscuro with deep shadows, emphasizing one point of light.
    **SceneCameraAngles**: extreme close-up on the eyes to reveal deep emotion, transitioning to an overhead shot revealing spatial relationships.
    **SceneMoods**: mystery with analytical focus, shifting to sudden, jarring shock.
    **NarrativeBeats**: The protagonist finds the crucial, hidden clue in the seemingly empty room, followed immediately by the realization of the killer's identity.
  `
    },
    {
      key: 'cinematicBlockbuster',
      label: 'Film Sinematik',
      description: 'High-End, Wide-Screen Drama',
      icon: '🎞️',
      prompt: `
    **Scene Lokasi**: rooftop overlooking the skyline during blue hour, or a hi-tech control room glowing with translucent interfaces.
    **LightingPresets**: moody volumetric light cutting through atmosphere, utilizing a Teal & Orange color palette. Use dramatic rim lighting with strong contrast to separate the subject from the background.
    **SceneCameraAngles**: smooth crane shot revealing scale and transition, primarily using the wide establishing shot from a slightly elevated angle, often framed in a 21:9 aspect ratio.
    **SceneMoods**: rising tension with subtle anxiety, leading to quiet determination. The overall feeling is grand and polished.
    **NarrativeBeats**: The protagonist stands alone, contemplating a large-scale impending threat or decision that affects the city/world. The scene is about establishing the stakes and their powerful, singular resolve against a vast backdrop.
  `
    },
    {
      key: 'romanticNostalgia',
      label: 'Nostalgia Hangat',
      description: 'Reflektif Penuh Kenyamanan',
      icon: '☕️',
      prompt: `
    **Scene Lokasi**: a vintage diner booth late at night, under a broken neon sign.
    **LightingPresets**: warm fireplace glow casting dynamic shadows on walls, mixed with practical lighting only (lamps and screens) for natural realism.
    **SceneCameraAngles**: over-the-shoulder (OTS) shot for dialogue and conversation, frequently using a close-up focusing on hands (touching or holding a cup).
    **SceneMoods**: warm nostalgia and comfort of familiarity, transitioning to a hopeful yet unresolved cliffhanger tone.
    **NarrativeBeats**: Two characters share a late-night conversation, reminiscing about a shared past. The dialogue leads to a moment of soft realization about their current feelings for each other, leaving the future unspoken but implied.
  `
    },
    {
      key: 'nusantaraEpic',
      label: 'Epos Nusantara',
      description: 'Rich Cultural and Historical',
      icon: '🕌',
      prompt: `
    **Scene Lokasi**: a crowded street market filled with ambient details, featuring traditional textiles and food vendors, or an ancient, sun-drenched temple ruin.
    **LightingPresets**: strong, natural golden hour sunlight with warm highlights, emphasizing deep saturation of colors (Reds, Yellows, Golds). Use high-contrast lighting to define textures.
    **SceneCameraAngles**: wide establishing shot from a slightly elevated angle to capture the scope of the location, contrasted with close-up focusing on hands (crafting, offering, or traditional gestures).
    **SceneMoods**: quiet determination and a sense of warm nostalgia, leading to an eventual hopeful yet unresolved cliffhanger tone.
    **NarrativeBeats**: The protagonist is engaged in a moment of traditional ritual or communal life. The scene slowly builds tension as they must choose between preserving cultural heritage and navigating modern challenges. The focus is on the beauty of the setting and the gravitas of the decision.
  `
    },
    {
      key: 'situationalComedy',
  label: 'Komedi Situasional Ceria',
  description: 'Bright, High-Key',
  icon: '😂', 
  prompt: `
    **Scene Lokasi**: sun-drenched apartment balcony with potted plants and city noise, or a brightly lit, sterile location like a hi-tech control room glowing with translucent interfaces.
    **LightingPresets**: high-key bright white lighting for a sterile or comedic effect, or soft diffused lighting with maximum brightness to avoid shadows and ensure clear visibility of facial reactions.
    **SceneCameraAngles**: shoulder-level medium shot highlighting expressions and physical comedy, frequently using a reverse shot showing the exaggerated reaction of the listening character. Avoid dynamic movements like steadicam or crane shots.
    **SceneMoods**: whimsical and lighthearted with a sense of wonder, transitioning to playful competition and confident swagger. The tone must remain fast-paced and upbeat.
    **NarrativeBeats**: Two characters are in a minor disagreement or awkward situation. The action focuses on rapid-fire dialogue and visual gags. The scene ends abruptly with a punchline or a highly confused reaction from one character.
  `
    },
    {
      key: 'ecomAd',
      label: 'Iklan E-Commerce',
      description: 'Product Spotlight',
      icon: '🛍️',
      prompt: 'Product-forward advertising energy with hero lighting, macro beauty shots, upbeat rhythm, benefits-focused overlays, and aspirational lifestyle cutaways.'
    },
    {
      key: 'aiExplainer',
      label: 'Video Explainer AI',
      description: 'Futuristic Insight',
      icon: '🤖',
      prompt: 'Futuristic explainer tone blending holographic UI overlays, smooth dolly or orbit moves, clean gradients, and concise voiceover-ready storytelling beats.'
    },
    {
      key: 'formalPresentation',
      label: 'Presentasi YouTube',
      description: 'Formal',
      icon: '🧑‍🏫',
      prompt: 'Professional presentation mood with studio lighting, center-framed speaker, slide-insert cutaways, minimal transitions, and calm confident pacing.'
    },
    {
      key: 'cyberpunk',
      label: 'CyberPunk',
      description: 'Neon Noir',
      icon: '🌌',
      prompt: 'Cyberpunk worldbuilding drenched in neon, rain-soaked surfaces, holographic signage, kinetic camera moves, synth ambience, and magenta-teal color contrast.'
    }
  ];

  const FILM_STATE_LIBRARY = FILM_STATE_PRESETS.reduce((map, preset) => {
    map[preset.key] = preset;
    return map;
  }, {});

  function getFilmStatePreset(key = 'auto') {
    return FILM_STATE_LIBRARY[key] || FILM_STATE_LIBRARY.auto;
  }

  function updateFilmStateActiveState(activeKey) {
    if (!filmStateMenu) return;
    filmStateMenu.querySelectorAll('.ugc-style-option').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.value === activeKey);
    });
  }

  function toggleFilmStateMenu(forceOpen) {
    if (!filmStateMenu || !filmStateTrigger) return;
    const shouldOpen = typeof forceOpen === 'boolean'
      ? forceOpen
      : filmStateMenu.classList.contains('hidden');
    if (shouldOpen) {
      filmStateMenu.classList.remove('hidden');
      filmStateTrigger.classList.add('open');
    } else {
      filmStateMenu.classList.add('hidden');
      filmStateTrigger.classList.remove('open');
    }
  }

  function selectFilmState(key, closeMenu = true) {
    const preset = getFilmStatePreset(key);
    filmStateMode = preset.key;
    if (filmStateValueInput) filmStateValueInput.value = preset.key;
    if (filmStateLabelEl) filmStateLabelEl.textContent = preset.label;
    if (filmStateDescEl) filmStateDescEl.textContent = preset.description;
    if (filmStateIconEl) filmStateIconEl.textContent = preset.icon || '🎬';
    updateFilmStateActiveState(preset.key);
    if (closeMenu) toggleFilmStateMenu(false);
  }

  function renderFilmStateMenu() {
    if (!filmStateMenu) return;
    filmStateMenu.innerHTML = '';
    FILM_STATE_PRESETS.forEach(preset => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'ugc-style-option';
      option.dataset.value = preset.key;
      option.innerHTML = `
        <span class="ugc-style-option-icon">${preset.icon || '🎬'}</span>
        <div class="ugc-style-option-meta">
          <div class="ugc-style-option-label">${preset.label}</div>
          <div class="ugc-style-option-desc">${preset.description}</div>
        </div>
      `;
      option.addEventListener('click', () => selectFilmState(preset.key));
      filmStateMenu.appendChild(option);
    });
  }

  function capitalizeFirst(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function ensureSentence(text, fallback) {
    const base = (text || '').trim();
    if (!base) return fallback;
    return /[.!?…]$/.test(base) ? base : base + '.';
  }

  function collapseWhitespace(text) {
    return (text || '').replace(/\s+/g, ' ').trim();
  }

  function summarizeSceneSnippet(text) {
    const cleaned = collapseWhitespace(text);
    if (!cleaned) {
      return 'Peristiwa penting yang mendorong cerita';
    }
    return cleaned.length > 140 ? cleaned.slice(0, 137) + '…' : cleaned;
  }

  function extractStoryPartsForScenes(brief, count) {
    const cleaned = (brief || '').replace(/\r\n?/g, '\n');
    const parts = [];

    cleaned
      .split(/\n+/)
      .map(part => part.trim())
      .filter(Boolean)
      .forEach(part => parts.push(part));

    if (parts.length < count) {
      cleaned
        .split(/(?<=[.!?])\s+/)
        .map(part => part.trim())
        .filter(Boolean)
        .forEach(sentence => {
          if (!parts.includes(sentence)) {
            parts.push(sentence);
          }
        });
    }

    if (!parts.length) {
      parts.push(brief.trim() || 'Describe the protagonist in action');
    }

    const baseLength = parts.length;
    let idx = 0;
    while (parts.length < count) {
      parts.push(parts[idx % baseLength]);
      idx += 1;
    }

    return parts.slice(0, count);
  }

  function buildFilmScenePlans(brief, count) {
    const parts = extractStoryPartsForScenes(brief, count);
    const preset = getFilmStatePreset(filmStateMode);
    const storyBriefSection = ensureSentence(summarizeSceneSnippet(brief), 'Story brief tidak tersedia.');
    const filmStateSection = preset.key === 'auto'
      ? 'AUTO STATE · Sistem menentukan treatment sinematik terbaik.'
      : `${preset.label} · ${preset.prompt}`;
    let previousSummary = 'Belum ada scene sebelumnya; buka cerita berdasarkan story brief.';

    return parts.map((part, idx) => {
      const index = idx + 1;
      const action = ensureSentence(capitalizeFirst(part), 'Describe the protagonist in action.');
      const environment = filmSceneLocations[idx % filmSceneLocations.length];
      const lighting = filmSceneLightingPresets[idx % filmSceneLightingPresets.length];
      const camera = filmSceneCameraAngles[idx % filmSceneCameraAngles.length];
      const mood = filmSceneMoods[idx % filmSceneMoods.length];
      const nextSummary = idx + 1 < parts.length ? summarizeSceneSnippet(parts[idx + 1]) : null;
      const prevSummaryForPrompt = previousSummary;

      const beatIntro = ensureSentence(filmNarrativeBeats[idx % filmNarrativeBeats.length], 'Bangun ketegangan cerita.');
      const reactionLine = idx === 0
        ? 'Respon langsung terhadap premis story brief dan kenalkan motivasi utama tokoh'
        : `Respon terhadap peristiwa sebelumnya: ${prevSummaryForPrompt}`;
      const causeLine = nextSummary
        ? `Picu aksi menuju adegan selanjutnya: ${nextSummary}`
        : 'Berikan konsekuensi akhir yang menutup bab ini sekaligus membuka ruang refleksi';
      const narrativeBeat = [
        beatIntro,
        ensureSentence(reactionLine, 'Respon terhadap adegan sebelumnya.'),
        ensureSentence(causeLine, 'Picu aksi berikutnya.')
      ].join(' ');

      const currentSummary = ensureSentence(summarizeSceneSnippet(part), 'Adegan bergerak maju.');
      previousSummary = currentSummary;

      const previousLabel = index === 1 ? 'Scene Sebelumnya' : `Scene ${index - 1}`;
      const promptSections = [
        `[StoryBrief] ${storyBriefSection}`,
        `[FilmmakerState] ${filmStateSection}`,
        `[RINGKASAN Singkat ${previousLabel}] ${prevSummaryForPrompt}`,
        `[NarrativeBeat Scene ${index}] ${narrativeBeat}`,
        'characterReferences: gunakan gambar referensi karakter yang diupload agar tokoh konsisten.',
        `Setting/environment: ${environment}. Lighting: ${lighting}. Camera style: ${camera}. Mood: ${mood}.`
      ];

      return {
        index,
        prompt: promptSections.join(' '),
        meta: {
          action,
          environment,
          lighting,
          camera,
          mood,
          previousSummary: prevSummaryForPrompt,
          narrativeBeat,
          theme: preset ? preset.label : 'AUTO STATE'
        }
      };
    });
  }

  filmCharacterDrop.addEventListener('click', () => filmCharacterInput.click());

  filmCharacterInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('File harus gambar (PNG/JPG).');
      return;
    }
    const reader = new FileReader();
    reader.onload = ev => {
      filmCharacterDataUrl = ev.target.result;
      filmCharacterPreview.src = filmCharacterDataUrl;
      filmCharacterPreview.style.display = 'block';
      filmCharacterIdle.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });

  filmSceneCount.addEventListener('input', () => {
    filmSceneCountLabel.textContent = filmSceneCount.value + ' scenes';
  });

  renderFilmStateMenu();
  const initialFilmState = filmStateValueInput && filmStateValueInput.value ? filmStateValueInput.value : 'auto';
  selectFilmState(initialFilmState, false);

  if (filmStateTrigger) {
    filmStateTrigger.addEventListener('click', () => {
      const shouldOpen = filmStateMenu ? filmStateMenu.classList.contains('hidden') : true;
      toggleFilmStateMenu(shouldOpen);
    });
  }

  if (filmStatePicker) {
    document.addEventListener('click', event => {
      if (!filmStateMenu || !filmStateTrigger) return;
      if (!filmStatePicker.contains(event.target)) {
        toggleFilmStateMenu(false);
      }
    });
  }

  filmAspectButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      filmAspectButtons.forEach(b => b.classList.remove('film-aspect-active'));
      btn.classList.add('film-aspect-active');
      filmAspect = btn.dataset.filmAspect;
    });
  });

  function updateFilmProgressUI() {
    if (!filmProgressEl) return;
    const summary = summarizeTaskProgress(filmScenes, {
      isStarted: scene => !!(scene && scene.taskId),
      isCompleted: scene => !!(scene && finalStatus(scene.status))
    });
    const label = summary.total
      ? `${summary.percent}% (${summary.completed}/${summary.total})`
      : '0%';
    setInlineProgressState(filmProgressEl, filmProgressFill, filmProgressValue, summary.percent, summary.total > 0, label);
  }

  function renderFilmScenes() {
    if (!filmScenes.length) {
      filmScenesEmpty.style.display = 'flex';
      filmScenesContainer.innerHTML = '';
      updateFilmProgressUI();
      return;
    }
    filmScenesEmpty.style.display = 'none';
    filmScenesContainer.innerHTML = '';

    filmScenes.forEach(scene => {
      const card = document.createElement('div');
      card.className = 'film-scene-card';

      const header = document.createElement('div');
      header.className = 'film-scene-header';

      const title = document.createElement('div');
      title.className = 'film-scene-title';
      title.textContent = 'Scene ' + scene.index;

      const status = document.createElement('div');
      status.className = 'film-scene-status';
      const st = (scene.status || '').toUpperCase();
      if (st === 'COMPLETED') {
        status.classList.add('done');
        status.textContent = 'COMPLETED';
      } else if (st === 'FAILED' || st === 'ERROR') {
        status.classList.add('error');
        status.textContent = st;
      } else {
        status.classList.add('progress');
        status.textContent = st || 'CREATED';
      }

      header.appendChild(title);
      header.appendChild(status);
      card.appendChild(header);

      if (scene.url) {
        const img = document.createElement('img');
        img.className = 'film-scene-thumb';
        img.src = scene.url;
        img.alt = 'Scene ' + scene.index;
        img.classList.add('clickable-media');
        img.addEventListener('click', () => openAssetPreview(scene.url, 'image'));
        card.appendChild(img);

        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.justifyContent = 'flex-end';
        actions.style.marginTop = '4px';
        actions.style.gap = '6px';

        const previewBtn = document.createElement('button');
        previewBtn.type = 'button';
        previewBtn.className = 'small secondary';
        previewBtn.textContent = 'Preview';
        previewBtn.addEventListener('click', () => openAssetPreview(scene.url, 'image'));
        actions.appendChild(previewBtn);

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'small secondary';
        saveBtn.textContent = 'Simpan ke Drive';
        saveBtn.disabled = !scene.url;
        saveBtn.addEventListener('click', () => saveSceneToDrive(scene, saveBtn));
        actions.appendChild(saveBtn);

        const a = document.createElement('a');
        a.href = scene.url;
        a.target = '_blank';
        a.download = '';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'small';
        btn.textContent = 'Download';

        a.appendChild(btn);
        actions.appendChild(a);
        card.appendChild(actions);
      }

      const promptDiv = document.createElement('div');
      promptDiv.className = 'film-scene-prompt';
      promptDiv.textContent = scene.prompt || '';
      card.appendChild(promptDiv);

      filmScenesContainer.appendChild(card);
    });

    updateFilmProgressUI();
  }

  async function pollFilmScenesOnce() {
    const pending = filmScenes.filter(s => s.taskId && !finalStatus(s.status));
    if (!pending.length) return;

    for (const scene of pending) {
      try {
        const { status, generated } = await fetchStatus('gemini', scene.taskId);
        if (status) scene.status = status;
        if (generated && Array.isArray(generated) && generated.length) {
          const remote = generated[0];
          try {
            const local = await cacheUrl(remote);
            scene.url = local || remote;
          } catch {
            scene.url = remote;
          }
        }
      } catch (err) {
        scene.status = 'ERROR';
      }
    }
    renderFilmScenes();

    const stillPending = filmScenes.some(s => s.taskId && !finalStatus(s.status));
    if (!stillPending && filmPollTimer) {
      clearInterval(filmPollTimer);
      filmPollTimer = null;
    }
  }

  function startFilmPolling() {
    if (filmPollTimer) clearInterval(filmPollTimer);
    filmPollTimer = setInterval(pollFilmScenesOnce, 8000);
  }

  filmGenerateBtn.addEventListener('click', async () => {
    if (!featureAvailableForCurrentUser('filmmaker')) {
      showFeatureLockedMessage('filmmaker');
      return;
    }
    const brief = filmBriefInput.value.trim();
    const count = Number(filmSceneCount.value || '0');

    if (!filmCharacterDataUrl) {
      alert('Upload character image dulu.');
      return;
    }
    if (!brief) {
      alert('Story brief wajib diisi.');
      return;
    }
    if (count < 1) {
      alert('Minimal 1 scene.');
      return;
    }

    const cfg = MODEL_CONFIG.gemini;
    const base64 = filmCharacterDataUrl.replace(/^data:image\/[a-zA-Z+]+;base64,/, '');

    const scenePlans = buildFilmScenePlans(brief, count);
    const requiredCoins = Math.max(1, scenePlans.length * COIN_COST_FILM_PER_SCENE);
    if (!currentAccount) {
      alert('Data akun belum siap. Muat ulang halaman.');
      return;
    }
    if (!ensureCoins(requiredCoins)) {
      alert('Koin kamu tidak cukup untuk generate film.');
      return;
    }

    filmGenerateBtn.disabled = true;
    filmScenes = [];
    renderFilmScenes();

    let successfulScenes = 0;

    for (const plan of scenePlans) {
      const scenePrompt = plan.prompt;

      const body = {
        prompt: scenePrompt,
        num_images: 1,
        reference_images: [base64]
      };
      if (filmAspect === '9:16') {
        body.aspect_ratio = 'social_story_9_16';
      } else if (filmAspect === '16:9') {
        body.aspect_ratio = 'widescreen_16_9';
      }

      let taskId = null;
      let status = 'ERROR';
      let success = false;

      try {
        const data = await callFreepik(cfg, body, 'POST');
        status = 'CREATED';
        if (data && data.data) {
          taskId = data.data.task_id || null;
          status  = data.data.status   || status;
        }
        success = true;
      } catch (err) {
        console.error(err);
        status = 'ERROR';
      }

      filmScenes.push({
        index: plan.index,
        prompt: scenePrompt,
        meta: plan.meta,
        taskId,
        status,
        url: null
      });

      if (success) {
        const normalized = String(status || '').toUpperCase();
        if (normalized !== 'ERROR' && normalized !== 'FAILED') {
          successfulScenes += 1;
        }
      }

      renderFilmScenes();
    }

    const coinsToSpend = successfulScenes * COIN_COST_FILM_PER_SCENE;
    if (coinsToSpend > 0) {
      try {
        await spendCoins(coinsToSpend);
      } catch (err) {
        console.error('Gagal mengurangi koin film:', err);
        alert('Koin tidak dapat dikurangi: ' + err.message);
        try {
          await loadAccountState();
        } catch (loadErr) {
          console.warn('Tidak bisa me-refresh akun setelah gagal mengurangi koin:', loadErr);
        }
      }
    }

    startFilmPolling();
    filmGenerateBtn.disabled = accountRestricted();
  });

  const UGC_IDEA_COUNT = 5;

  const UGC_STYLE_GROUPS = [
    {
      id: 'basic',
      title: 'Basic – Diverse & Flexible',
      styles: [
        {
          key: 'basic',
          label: 'Basic',
          description: 'Diverse & Flexible contexts',
          icon: '✨',
          prompt: 'balanced lifestyle composition highlighting the product naturally, creator-driven framing, warm ambient light'
        }
      ]
    },
    {
      id: 'action',
      title: 'Action with Product',
      styles: [
        { key: 'holding', label: 'Holding', description: 'Hand Positions & Grips', icon: '🤲', prompt: 'close-up of hands holding the product, showcasing grip, authentic user perspective, gentle focus on logo' },
        { key: 'using', label: 'Using', description: 'Active Application', icon: '👐', prompt: 'real person actively using the product mid-action, dynamic pose, candid smile, motion-friendly blur, real environment' },
        { key: 'unboxing', label: 'Unboxing', description: 'First Reveal', icon: '📦', prompt: 'first reveal moment with packaging being opened, excited reaction, tabletop context, natural daylight and product focus' },
        { key: 'in_use', label: 'In-Use', description: 'Real Moments', icon: '🎬', prompt: 'real-life scenario capturing product in everyday use, documentary angle, genuine emotion, ambient background details' },
        { key: 'quick_demo', label: 'Quick Demo', description: 'How-To Tutorial', icon: '🎥', prompt: 'step-by-step demonstration shot, clear view of key features, instructional framing, slightly angled top view' }
      ]
    },
    {
      id: 'showcase',
      title: 'Showcase & Demos',
      styles: [
        { key: 'pointing', label: 'Pointing', description: 'Direct Attention', icon: '👉', prompt: 'creator pointing at the product or key detail, guiding viewer attention, mid-shot with confident gesture' },
        { key: 'demo_review', label: 'Demo', description: 'Review Style', icon: '🎤', prompt: 'review-style presentation facing camera, product held near face, talk-to-camera energy, inviting smile' },
        { key: 'hands_only', label: 'Hands Only', description: 'Product Focus', icon: '✋', prompt: 'hands-only macro focus with minimal background, product centered, soft diffused lighting, tactile emphasis' },
        { key: 'size_compare', label: 'Size Compare', description: 'Scale Reference', icon: '📏', prompt: 'product side-by-side with familiar object to show scale, neutral background, overhead or eye-level perspective' }
      ]
    },
    {
      id: 'lifestyle',
      title: 'Lifestyle',
      styles: [
        { key: 'home', label: 'Home', description: 'Indoor Cozy', icon: '🏡', prompt: 'cozy indoor home setting, warm lighting, soft furnishings, lifestyle moment featuring the product naturally' },
        { key: 'outdoor', label: 'Outdoor', description: 'On-the-Go', icon: '🏞️', prompt: 'on-the-go outdoor lifestyle, natural sunlight, candid movement, engaging urban or nature backdrop' },
        { key: 'workspace', label: 'Workspace', description: 'Productivity', icon: '💻', prompt: 'productive workspace scene, desk accessories, modern tech aesthetic, cool daylight, tidy composition' },
        { key: 'routine', label: 'Routine', description: 'Step-by-Step', icon: '🔁', prompt: 'daily routine storytelling, sequential actions, clean bathroom or vanity setting, natural morning light' },
        { key: 'travel', label: 'Travel', description: 'Portable & Compact', icon: '✈️', prompt: 'portable travel vibe with suitcase or travel props, aspirational destination lighting, compact packing view' },
        { key: 'bedroom', label: 'Bedroom', description: 'Modern Lifestyle', icon: '🛏️', prompt: 'comfortable bedroom environment, soft textiles, morning glow, relaxed mood featuring the product' },
        { key: 'studio_fitting', label: 'Studio Fitting', description: 'Product Review', icon: '👗', prompt: 'studio fitting setup with fashion rack or mirror, editorial lighting, model evaluating outfit with product' },
        { key: 'getting_ready', label: 'Getting Ready', description: 'GRWM Routine', icon: '💄', prompt: 'get ready with me energy, mirror interaction, beauty station props, playful yet polished atmosphere' }
      ]
    },
    {
      id: 'flatlay',
      title: 'Flat Lay',
      styles: [
        { key: 'flatlay_minimal', label: 'Minimal', description: 'Clean & Simple', icon: '⚪', prompt: 'minimalist flat lay on neutral surface, overhead view, generous negative space, soft shadow, curated props' },
        { key: 'flatlay_styled', label: 'Styled', description: 'Editorial Props', icon: '🎀', prompt: 'editorial flat lay with styled props, colorful backdrop, layered textures, overhead composition with depth' }
      ]
    },
    {
      id: 'outfit',
      title: 'Outfit & Variants',
      styles: [
        { key: 'outfit', label: 'Outfit', description: 'Fashion OOTD Layout', icon: '👚', prompt: 'full outfit moment or layout, fashion-forward styling, vertical framing, confident stance or neatly arranged pieces' },
        { key: 'ingredients', label: 'Ingredients', description: 'Contents Focus', icon: '🥣', prompt: 'product components or ingredients arranged neatly, storytelling labels, tabletop lighting, organized composition' },
        { key: 'color_variants', label: 'Color Variants', description: 'Options Showcase', icon: '🎨', prompt: 'multiple color variations displayed side-by-side, consistent spacing, vibrant yet controlled palette, clean backdrop' }
      ]
    },
    {
      id: 'closeup',
      title: 'Close-Up',
      styles: [
        { key: 'detail', label: 'Detail', description: 'Feature Focus', icon: '🔍', prompt: 'macro detail close-up showcasing craftsmanship, shallow depth of field, crisp highlight on key feature' },
        { key: 'texture', label: 'Texture', description: 'Material Feel', icon: '🧵', prompt: 'extreme close-up emphasizing material texture, directional lighting to reveal surface qualities' },
        { key: 'swatches', label: 'Swatches', description: 'Texture Samples', icon: '🧩', prompt: 'product swatches or samples arranged in grid, clean background, color accuracy, top-down view' },
        { key: 'packaging', label: 'Packaging', description: 'Design Focus', icon: '🎁', prompt: 'packaging hero shot, clean studio backdrop, professional lighting, crisp shadows highlighting structure' }
      ]
    }
  ];

  const DEFAULT_UGC_STYLE_KEY = 'basic';
  const UGC_STYLE_LIBRARY = UGC_STYLE_GROUPS.reduce((acc, group) => {
    group.styles.forEach(style => {
      acc[style.key] = style;
    });
    return acc;
  }, {});

  let ugcProductImages = [];
  let ugcModelImage = null;
  let ugcItems = [];
  let ugcPollTimer = null;

  function normalizeUgcReference(entry) {
    if (!entry) return null;
    if (typeof entry === 'string') {
      return stripDataUrlPrefix(entry);
    }
    if (entry.dataUrl) {
      return stripDataUrlPrefix(entry.dataUrl);
    }
    if (entry.url) {
      return entry.url;
    }
    return null;
  }

  function buildUgcReferences() {
    const referenceLimit = 3;
    const productRefs = ugcProductImages
      .map(img => normalizeUgcReference(img))
      .filter(Boolean);

    const refs = productRefs.slice(0, referenceLimit);
    const modelRef = normalizeUgcReference(ugcModelImage);

    if (modelRef) {
      if (!productRefs.length) {
        return [modelRef];
      }
      if (refs.length < referenceLimit) {
        refs.push(modelRef);
      } else {
        refs[refs.length - 1] = modelRef;
      }
    }

    return refs;
  }

  function getUgcStyle(key = DEFAULT_UGC_STYLE_KEY) {
    return UGC_STYLE_LIBRARY[key] || UGC_STYLE_LIBRARY[DEFAULT_UGC_STYLE_KEY];
  }

  function buildUgcImagePrompt(basePrompt, styleKey, index) {
    const style = getUgcStyle(styleKey);
    const cleaned = (basePrompt || '').trim().replace(/\s+/g, ' ');
    const promptBase = cleaned || 'Product UGC photo shot';
    return `UGC Image #${index}: ${promptBase}. Style focus: ${style.label} — ${style.prompt}. Capture in square format with authentic creator energy.`;
  }

  function updateUgcStyleActiveState(activeKey) {
    if (!ugcStyleMenu) return;
    const options = ugcStyleMenu.querySelectorAll('.ugc-style-option');
    options.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.value === activeKey);
    });
  }

  function selectUgcStyle(key, closeMenu = true) {
    const style = getUgcStyle(key);
    if (ugcStyleValueInput) ugcStyleValueInput.value = style.key;
    if (ugcStyleLabelEl) ugcStyleLabelEl.textContent = style.label;
    if (ugcStyleDescEl) ugcStyleDescEl.textContent = style.description;
    if (ugcStyleIconEl) ugcStyleIconEl.textContent = style.icon || '✨';
    updateUgcStyleActiveState(style.key);
    if (closeMenu) toggleUgcStyleMenu(false);
  }

  function renderUgcStyleMenu() {
    if (!ugcStyleMenu) return;
    ugcStyleMenu.innerHTML = '';
    UGC_STYLE_GROUPS.forEach(group => {
      const groupEl = document.createElement('div');
      groupEl.className = 'ugc-style-group';

      const titleEl = document.createElement('div');
      titleEl.className = 'ugc-style-group-title';
      titleEl.textContent = group.title;
      groupEl.appendChild(titleEl);

      group.styles.forEach(style => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'ugc-style-option';
        option.dataset.value = style.key;
        option.innerHTML = `
          <span class="ugc-style-option-icon">${style.icon || '✨'}</span>
          <div class="ugc-style-option-meta">
            <div class="ugc-style-option-label">${style.label}</div>
            <div class="ugc-style-option-desc">${style.description}</div>
          </div>
        `;
        option.addEventListener('click', () => {
          selectUgcStyle(style.key);
        });
        groupEl.appendChild(option);
      });

      ugcStyleMenu.appendChild(groupEl);
    });

    const current = ugcStyleValueInput && ugcStyleValueInput.value ? ugcStyleValueInput.value : DEFAULT_UGC_STYLE_KEY;
    updateUgcStyleActiveState(current);
  }

  function toggleUgcStyleMenu(forceOpen) {
    if (!ugcStyleMenu || !ugcStyleTrigger) return;
    const isHidden = ugcStyleMenu.classList.contains('hidden');
    const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : isHidden;
    if (shouldOpen) {
      ugcStyleMenu.classList.remove('hidden');
      ugcStyleTrigger.classList.add('open');
    } else {
      ugcStyleMenu.classList.add('hidden');
      ugcStyleTrigger.classList.remove('open');
    }
  }

  function updateUgcProgressUI() {
    if (!ugcProgressEl) return;
    const summary = summarizeTaskProgress(ugcItems, {
      isStarted: item => !!(item && item.taskId),
      isCompleted: item => !!(item && finalStatus(item.status))
    });
    const label = summary.total
      ? `${summary.percent}% (${summary.completed}/${summary.total})`
      : '0%';
    setInlineProgressState(ugcProgressEl, ugcProgressFill, ugcProgressValue, summary.percent, summary.total > 0, label);
  }

  function renderUgcList() {
    ugcList.innerHTML = '';
    if (!ugcItems.length) {
      ugcEmpty.style.display = 'flex';
      ugcList.appendChild(ugcEmpty);
      updateUgcProgressUI();
      return;
    }
    ugcEmpty.style.display = 'none';

    ugcItems.forEach(item => {
      const row = document.createElement('div');
      row.className = 'ugc-row';
      row.dataset.index = item.index;

      const imageCol = document.createElement('div');
      imageCol.className = 'ugc-column ugc-column-image';

      const imageLabel = document.createElement('div');
      imageLabel.className = 'ugc-column-label';
      imageLabel.textContent = 'Image ' + item.index;
      imageCol.appendChild(imageLabel);

      const imgCard = document.createElement('div');
      imgCard.className = 'ugc-image-card';

      if (item.imageUrl) {
        const img = document.createElement('img');
        img.src = item.imageUrl;
        img.alt = 'UGC Image ' + item.index;
        img.classList.add('clickable-media');
        img.addEventListener('click', () => openAssetPreview(item.imageUrl, 'image'));
        imgCard.appendChild(img);
      } else {
        const placeholder = document.createElement('div');
        placeholder.className = 'ugc-image-placeholder';
        const title = document.createElement('div');
        title.className = 'ugc-placeholder-title';
        title.textContent = 'Image #' + item.index;
        const status = document.createElement('div');
        status.className = 'ugc-placeholder-status';
        status.textContent = 'Generating… ' + (item.status || 'CREATED');
        placeholder.appendChild(title);
        placeholder.appendChild(status);
        imgCard.appendChild(placeholder);
      }

      imageCol.appendChild(imgCard);

      const videoCol = document.createElement('div');
      videoCol.className = 'ugc-column ugc-column-video';

      const videoLabel = document.createElement('div');
      videoLabel.className = 'ugc-column-label';
      videoLabel.textContent = 'Video';
      videoCol.appendChild(videoLabel);

      const videoCard = document.createElement('div');
      videoCard.className = 'ugc-video-card';

      if (item.videoUrl) {
        const badge = document.createElement('div');
        badge.className = 'ugc-video-badge';
        badge.textContent = 'Video ready';

        const video = document.createElement('video');
        video.src = item.videoUrl;
        video.controls = true;
        video.loop = true;
        video.muted = true;
        video.playsInline = true;

        videoCard.appendChild(badge);
        videoCard.appendChild(video);
        videoCol.appendChild(videoCard);

        const actions = document.createElement('div');
        actions.className = 'ugc-video-actions';

        const previewVideoBtn = document.createElement('button');
        previewVideoBtn.type = 'button';
        previewVideoBtn.className = 'ugc-link-btn';
        previewVideoBtn.textContent = 'Preview Video';
        previewVideoBtn.addEventListener('click', () => openAssetPreview(item.videoUrl, 'video'));
        actions.appendChild(previewVideoBtn);

        const videoDownloadBtn = document.createElement('button');
        videoDownloadBtn.type = 'button';
        videoDownloadBtn.className = 'ugc-link-btn';
        videoDownloadBtn.textContent = 'Download Video';
        videoDownloadBtn.addEventListener('click', () => {
          const a = document.createElement('a');
          a.href = item.videoUrl;
          a.download = '';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        });
        actions.appendChild(videoDownloadBtn);

        const videoSaveBtn = document.createElement('button');
        videoSaveBtn.type = 'button';
        videoSaveBtn.className = 'ugc-link-btn';
        videoSaveBtn.textContent = 'Simpan Video';
        videoSaveBtn.addEventListener('click', () => saveUgcVideoToDrive(item, videoSaveBtn));
        actions.appendChild(videoSaveBtn);

        videoCol.appendChild(actions);
      } else {
        const placeholder = document.createElement('div');
        placeholder.className = 'ugc-video-placeholder';
        const title = document.createElement('div');
        title.className = 'ugc-placeholder-title';
        if (item.videoJobId) {
          title.textContent = 'Video generating…';
        } else {
          title.textContent = 'No video yet';
        }
        const status = document.createElement('div');
        status.className = 'ugc-placeholder-status';
        if (item.videoJobId) {
          status.textContent = 'Check status di Queue';
        } else {
          status.textContent = 'Generate video setelah gambar siap';
        }
        placeholder.appendChild(title);
        placeholder.appendChild(status);
        videoCard.appendChild(placeholder);
        videoCol.appendChild(videoCard);
      }

      const detailCol = document.createElement('div');
      detailCol.className = 'ugc-column ugc-column-details';

      const header = document.createElement('div');
      header.className = 'ugc-result-header';
      const title = document.createElement('div');
      title.className = 'ugc-result-title';
      title.textContent = 'UGC Image #' + item.index;
      header.appendChild(title);

      const styleLabel = (item.styleLabel || '').trim();
      const styleDescription = (item.styleDescription || '').trim();
      if (styleLabel || styleDescription) {
        const meta = document.createElement('div');
        meta.className = 'ugc-result-subtitle';
        const parts = [];
        if (styleLabel) parts.push(styleLabel);
        if (styleDescription) parts.push(styleDescription);
        meta.textContent = 'Generated with ' + parts.join(' • ');
        header.appendChild(meta);
      }

      detailCol.appendChild(header);

      const downloadGroup = document.createElement('div');
      downloadGroup.className = 'ugc-download-group';

      const dlBtn = document.createElement('button');
      dlBtn.type = 'button';
      dlBtn.className = 'ugc-download-btn';
      dlBtn.textContent = 'Download Image';
      dlBtn.disabled = !item.imageUrl;
      if (item.imageUrl) {
        dlBtn.addEventListener('click', () => {
          const a = document.createElement('a');
          a.href = item.imageUrl;
          a.download = '';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        });
      }
      downloadGroup.appendChild(dlBtn);

      const secondaryActions = document.createElement('div');
      secondaryActions.className = 'ugc-secondary-actions';

      const previewImgBtn = document.createElement('button');
      previewImgBtn.type = 'button';
      previewImgBtn.className = 'ugc-link-btn';
      previewImgBtn.textContent = 'Preview Image';
      previewImgBtn.disabled = !item.imageUrl;
      if (item.imageUrl) {
        previewImgBtn.addEventListener('click', () => openAssetPreview(item.imageUrl, 'image'));
      }
      secondaryActions.appendChild(previewImgBtn);

      const saveImgBtn = document.createElement('button');
      saveImgBtn.type = 'button';
      saveImgBtn.className = 'ugc-link-btn';
      saveImgBtn.textContent = 'Simpan ke Drive';
      saveImgBtn.disabled = !item.imageUrl;
      if (item.imageUrl) {
        saveImgBtn.addEventListener('click', () => saveUgcImageToDrive(item, saveImgBtn));
      }
      secondaryActions.appendChild(saveImgBtn);

      downloadGroup.appendChild(secondaryActions);
      detailCol.appendChild(downloadGroup);

      const promptGroup = document.createElement('div');
      promptGroup.className = 'ugc-form-group';

      const pLabel = document.createElement('label');
      pLabel.className = 'ugc-field-label';
      pLabel.textContent = 'UGC Prompt #' + item.index;
      pLabel.setAttribute('for', 'ugcPrompt' + item.index);
      const pText = document.createElement('textarea');
      pText.className = 'ugc-textarea';
      pText.id = 'ugcPrompt' + item.index;
      pText.value = item.prompt || '';
      pText.rows = 3;
      pText.addEventListener('input', () => {
        item.prompt = pText.value;
      });

      promptGroup.appendChild(pLabel);
      promptGroup.appendChild(pText);
      detailCol.appendChild(promptGroup);

      const videoPromptGroup = document.createElement('div');
      videoPromptGroup.className = 'ugc-form-group';

      const vLabel = document.createElement('label');
      vLabel.className = 'ugc-field-label';
      vLabel.textContent = 'Video Animation Prompt';
      vLabel.setAttribute('for', 'ugcVideoPrompt' + item.index);
      const vText = document.createElement('textarea');
      vText.className = 'ugc-textarea';
      vText.id = 'ugcVideoPrompt' + item.index;
      vText.placeholder = 'contoh: model showing the product with a smile';
      vText.value = item.videoPrompt || '';
      vText.rows = 2;
      vText.addEventListener('input', () => {
        item.videoPrompt = vText.value;
      });

      videoPromptGroup.appendChild(vLabel);
      videoPromptGroup.appendChild(vText);
      detailCol.appendChild(videoPromptGroup);

      const generateRow = document.createElement('div');
      generateRow.className = 'ugc-generate-row';

      const vidBtn = document.createElement('button');
      vidBtn.type = 'button';
      vidBtn.className = 'ugc-generate-btn';
      vidBtn.textContent = 'Generate Video';
      vidBtn.disabled = !item.imageUrl;
      vidBtn.addEventListener('click', () => ugcGenerateVideo(item));

      generateRow.appendChild(vidBtn);
      detailCol.appendChild(generateRow);

      row.appendChild(imageCol);
      row.appendChild(videoCol);
      row.appendChild(detailCol);

      ugcList.appendChild(row);
    });

    updateUgcProgressUI();
  }

  async function pollUgcOnce() {
    const pending = ugcItems.filter(s => s.taskId && !finalStatus(s.status));
    if (!pending.length) return;

    for (const item of pending) {
      try {
        const { status, generated } = await fetchStatus('gemini', item.taskId);
        if (status) item.status = status;
       if (generated && Array.isArray(generated) && generated.length && !item.imageUrl) {
  const remote = generated[0];

  // SIMPAN URL ASLI DARI FREEPIK (WAJIB)
  item.remoteUrl = remote;

  try {
    const local = await cacheUrl(remote);
    // imageUrl = file di server kamu (buat preview & download)
    item.imageUrl = local || remote;
  } catch {
    item.imageUrl = remote;
  }
}


      } catch (e) {
        item.status = 'ERROR';
      }
    }
    renderUgcList();

    const stillPending = ugcItems.some(s => s.taskId && !finalStatus(s.status));
    if (!stillPending && ugcPollTimer) {
      clearInterval(ugcPollTimer);
      ugcPollTimer = null;
    }
  }

  function startUgcPolling() {
    if (ugcPollTimer) clearInterval(ugcPollTimer);
    ugcPollTimer = setInterval(() => { pollUgcOnce(); }, 8000);
  }

  ugcProductDrop.addEventListener('click', () => ugcProductInput.click());
  ugcProductInput.addEventListener('change', e => {
    const files = Array.from(e.target.files || []);
    ugcProductImages = [];
    const max = 3;
    const used = files.slice(0, max);
    ugcProductPreview.innerHTML = '';
    used.forEach((file, idx) => {
      if (!file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = ev => {
        ugcProductImages.push({ id: idx + 1, dataUrl: ev.target.result });
        const img = document.createElement('img');
        img.src = ev.target.result;
        ugcProductPreview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  });

  ugcModelDrop.addEventListener('click', () => ugcModelInput.click());
  ugcModelInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('Model image harus gambar');
      return;
    }
    const reader = new FileReader();
    reader.onload = ev => {
      ugcModelImage = { dataUrl: ev.target.result };
      ugcModelPreview.src = ev.target.result;
      ugcModelPreview.style.display = 'block';
      ugcModelIdle.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });

  if (ugcStyleTrigger) {
    ugcStyleTrigger.addEventListener('click', () => {
      const shouldOpen = ugcStyleMenu ? ugcStyleMenu.classList.contains('hidden') : true;
      toggleUgcStyleMenu(shouldOpen);
    });
  }

  if (ugcStylePicker) {
    document.addEventListener('click', event => {
      if (!ugcStyleMenu || !ugcStyleTrigger) return;
      if (!ugcStylePicker.contains(event.target)) {
        toggleUgcStyleMenu(false);
      }
    });
  }

  renderUgcStyleMenu();
  const initialUgcStyle = ugcStyleValueInput && ugcStyleValueInput.value ? ugcStyleValueInput.value : DEFAULT_UGC_STYLE_KEY;
  selectUgcStyle(initialUgcStyle, false);

  async function ugcGenerate() {
    if (!featureAvailableForCurrentUser('ugc')) {
      showFeatureLockedMessage('ugc');
      return;
    }
    if (!ugcProductImages.length) {
      alert('Minimal upload 1 product image.');
      return;
    }
    if (!currentAccount) {
      alert('Data akun belum siap. Muat ulang halaman.');
      return;
    }
    const styleKey = (ugcStyleValueInput && ugcStyleValueInput.value) || DEFAULT_UGC_STYLE_KEY;
    const styleMeta = getUgcStyle(styleKey);
    const brief = ugcBriefInput.value.trim() || 'Product UGC photo shot';
    const requiredCoins = Math.max(1, UGC_IDEA_COUNT * COIN_COST_UGC);
    if (!ensureCoins(requiredCoins)) {
      alert('Koin kamu tidak cukup untuk generate UGC.');
      return;
    }

    ugcGenerateBtn.disabled = true;
    ugcItems = [];
    renderUgcList();

    const cfg = MODEL_CONFIG.gemini;
    const refs = buildUgcReferences();
    let successfulIdeas = 0;

    for (let i = 1; i <= UGC_IDEA_COUNT; i++) {
      const prompt = buildUgcImagePrompt(brief, styleKey, i);
      const item = {
        index: i,
        prompt,
        videoPrompt: '',
        status: 'CREATED',
        taskId: null,
        imageUrl: null,
        videoJobId: null,
        videoUrl: null,
        styleKey,
        styleLabel: styleMeta && styleMeta.label ? styleMeta.label : '',
        styleDescription: styleMeta && styleMeta.description ? styleMeta.description : ''
      };
      ugcItems.push(item);
      renderUgcList();

      const body = {
        prompt,
        num_images: 1,
        aspect_ratio: 'square_1_1'
      };
      if (refs.length) {
        body.reference_images = refs.slice();
      }
      let success = false;
      try {
        const data = await callFreepik(cfg, body, 'POST');
        if (data && data.data) {
          item.taskId = data.data.task_id || null;
          item.status = data.data.status || 'CREATED';
        }
        success = true;
      } catch (e) {
        console.error(e);
        item.status = 'ERROR';
      }
      if (success) {
        const normalized = String(item.status || '').toUpperCase();
        if (normalized !== 'ERROR' && normalized !== 'FAILED') {
          successfulIdeas += 1;
        }
      }
      renderUgcList();
    }

    if (ugcItems.some(s => s.taskId)) startUgcPolling();
    const coinsToSpend = successfulIdeas * COIN_COST_UGC;
    if (coinsToSpend > 0) {
      try {
        await spendCoins(coinsToSpend);
      } catch (err) {
        console.error('Gagal mengurangi koin UGC:', err);
        alert('Koin tidak dapat dikurangi: ' + err.message);
        try {
          await loadAccountState();
        } catch (loadErr) {
          console.warn('Tidak bisa me-refresh akun setelah gagal mengurangi koin:', loadErr);
        }
      }
    }
    ugcGenerateBtn.disabled = accountRestricted();
  }

  ugcGenerateBtn.addEventListener('click', () => { ugcGenerate(); });

  async function ugcGenerateVideo(item) {
    if (!featureAvailableForCurrentUser('ugc')) {
      showFeatureLockedMessage('ugc');
      return;
    }
    // WAJIB: pakai URL asli dari Freepik, bukan path lokal
    if (!item.remoteUrl || !item.remoteUrl.startsWith('http')) {
      alert('URL gambar untuk video belum valid.\n' +
            'Pastikan UGC image sudah COMPLETED, lalu klik Generate Video lagi.');
      return;
    }

    const cfg = MODEL_CONFIG.seedancePro1080;
    const body = {
      prompt: item.videoPrompt || ('UGC video animation for image #' + item.index),
      image: item.remoteUrl,   // <-- PENTING
      duration: 10,
      aspect_ratio: 'auto'
    };

    try {
      const data = await callFreepik(cfg, body, 'POST');
      let taskId = null;
      let status = 'CREATED';
      let generated = null;

      if (data && data.data) {
        taskId = data.data.task_id || null;
        status  = data.data.status   || status;
        generated = data.data.generated || null;
      }

      const jobId = uuid();
      const job = {
        id: jobId,
        modelId: 'seedancePro1080',
        type: 'video',
        taskId,
        createdAt: nowIso(),
        updatedAt: nowIso(),
        status,
        generated: generated || [],
        extraUrl: null,
        prompt: body.prompt || null
      };
      jobs.unshift(job);
      saveJobs();
      renderJobs();
      if (taskId && !finalStatus(status)) {
        startJobProgress(job);
        startPolling(job);
      } else {
        finishJobProgress(job);
        if (job.generated && job.generated.length) {
          await ensureLocalFiles(job);
        }
        await syncJobToDrive(job);
        const immediateUrl = (job.localUrls && job.localUrls[0]) ||
                              (job.generated && job.generated[0]) ||
                              job.extraUrl || null;
        if (immediateUrl) {
          item.videoUrl = immediateUrl;
          renderUgcList();
        }
      }

      if (!finalStatus(status) && generated && Array.isArray(generated) && generated.length) {
        const generatedUrl = generated.find(u => typeof u === 'string' && u.trim() !== '');
        if (generatedUrl) {
          item.videoUrl = generatedUrl;
          renderUgcList();
        }
      }

      item.videoJobId = jobId;
      renderUgcList();
    } catch (e) {
      console.error(e);
      alert('Gagal membuat video: ' + e.message);
    }
  }


const assetPreviewModal = document.getElementById('assetPreviewModal');
const assetPreviewBody = document.getElementById('assetPreviewBody');
const assetPreviewClose = document.getElementById('assetPreviewClose');
const assetPreviewDownload = document.getElementById('assetPreviewDownload');

function isVideoUrl(url = '') {
  return /\.(mp4|webm|mov|m4v)(\?|$)/i.test(url);
}

function openAssetPreview(url, type = 'image') {
  if (!assetPreviewModal || !assetPreviewBody || !url) return;
  assetPreviewBody.innerHTML = '';
  let el;
  if (type === 'video' || isVideoUrl(url)) {
    el = document.createElement('video');
    el.src = url;
    el.controls = true;
    el.autoplay = true;
    el.loop = true;
    el.playsInline = true;
  } else {
    el = document.createElement('img');
    el.src = url;
    el.alt = 'Preview';
    el.classList.add('clickable-media');
  }
  const frame = document.createElement('div');
  frame.className = 'asset-preview-frame';
  frame.appendChild(el);
  const overlay = document.createElement('div');
  overlay.className = 'watermark-overlay';
  frame.appendChild(overlay);
  assetPreviewBody.appendChild(frame);

  if (assetPreviewDownload) {
    assetPreviewDownload.href = url;
    assetPreviewDownload.style.display = 'inline-flex';
  }

  assetPreviewModal.classList.remove('hidden');
  document.body.classList.add('modal-open');
}

function closeAssetPreview() {
  if (!assetPreviewModal || !assetPreviewBody) return;
  const video = assetPreviewBody.querySelector('video');
  if (video) {
    video.pause();
  }
  assetPreviewBody.innerHTML = '';
  assetPreviewModal.classList.add('hidden');
  document.body.classList.remove('modal-open');
  if (assetPreviewDownload) {
    assetPreviewDownload.href = '#';
    assetPreviewDownload.style.display = 'none';
  }
}

if (assetPreviewClose) {
  assetPreviewClose.addEventListener('click', closeAssetPreview);
}

if (assetPreviewModal) {
  assetPreviewModal.addEventListener('click', event => {
    if (event.target === assetPreviewModal) {
      closeAssetPreview();
    }
  });
}

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    if (assetPreviewModal && !assetPreviewModal.classList.contains('hidden')) {
      closeAssetPreview();
    }
    toggleUgcStyleMenu(false);
  }
});


  // ===== INIT =====
  applyAccountRestrictions(currentAccount);
  loadAccountState();
  setFeature('imageGen');
  jobs.filter(j => !finalStatus(j.status)).forEach(job => startJobProgress(job));
  renderJobs();
  jobs.filter(j => finalStatus(j.status) && !j.driveSynced).forEach(job => {
    syncJobToDrive(job).catch(err => console.warn('Resync drive gagal:', err));
  });
  if (jobs.length) {
    const lastCompleted = jobs.find(j => finalStatus(j.status)) || jobs[0];
    activeJobId = lastCompleted.id;
    renderPreview(lastCompleted);
  }
  updateFilmProgressUI();
  updateUgcProgressUI();
  filmSceneCountLabel.textContent = filmSceneCount.value + ' scenes';
</script>
</body>
</html>
