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
$FREEPIK_API_KEYS = [
    getenv('FREEPIK_API_KEY_1') ?: 'FPSX06967c376cb6d87d9c551ccb33ed4d56',
    getenv('FREEPIK_API_KEY_2') ?: 'REPLACE_WITH_API_KEY_2',
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
        'url'    => $publicPath,
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
        'url'    => $publicPath
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
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Masuk · Freepik Multi Suite</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color-scheme: dark;
    }
    body {
      margin: 0;
      min-height: 100vh;
      background: radial-gradient(circle at top, rgba(59,130,246,0.35), transparent 55%),
                  radial-gradient(circle at bottom, rgba(56,189,248,0.18), transparent 50%),
                  #020617;
      color: #f9fafb;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px 20px;
    }
    .auth-layout {
      width: min(960px, 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 24px;
    }
    .security-card,
    .auth-card {
      width: min(460px, 100%);
      background: rgba(15, 23, 42, 0.88);
      border: 1px solid rgba(59, 130, 246, 0.25);
      border-radius: 20px;
      padding: 32px 30px;
      box-shadow: 0 35px 80px rgba(2, 6, 23, 0.55);
      backdrop-filter: blur(18px);
    }
    .security-card h1 {
      margin: 0 0 12px;
      font-size: 28px;
      font-weight: 600;
      letter-spacing: 0.02em;
    }
    .security-card p {
      margin: 0 0 20px;
      color: rgba(226, 232, 240, 0.78);
      font-size: 15px;
      line-height: 1.6;
    }
    .ip-panel {
      border-radius: 16px;
      border: 1px solid rgba(248, 113, 113, 0.35);
      background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
      padding: 18px 20px 20px;
      margin-bottom: 20px;
      text-align: center;
    }
    .ip-label {
      display: block;
      font-size: 13px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(248, 250, 252, 0.6);
      margin-bottom: 6px;
    }
    .ip-value {
      font-size: 26px;
      font-weight: 700;
      color: #f97316;
      letter-spacing: 0.04em;
    }
    .ip-note {
      display: block;
      margin-top: 10px;
      font-size: 12px;
      color: rgba(248, 250, 252, 0.55);
    }
    .security-card button,
    .auth-card button[type="submit"] {
      width: 100%;
      border-radius: 12px;
      border: none;
      padding: 12px 18px;
      font-size: 15px;
      font-weight: 600;
      letter-spacing: 0.02em;
      cursor: pointer;
      color: #0f172a;
      background: linear-gradient(135deg, #ef4444, #f97316);
      box-shadow: 0 22px 48px rgba(239, 68, 68, 0.35);
      transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }
    .auth-card button[type="submit"] {
      background: linear-gradient(135deg, #6366f1, #22c55e);
      box-shadow: 0 22px 48px rgba(79, 70, 229, 0.35);
    }
    .security-card button:hover,
    .auth-card button[type="submit"]:hover {
      transform: translateY(-1px);
      box-shadow: 0 28px 56px rgba(94, 234, 212, 0.25);
    }
    .security-card button:disabled,
    .auth-card button[type="submit"].loading {
      opacity: 0.6;
      cursor: wait;
    }
    .security-status,
    .form-status {
      margin-top: 16px;
      font-size: 13px;
      min-height: 18px;
      text-align: center;
    }
    .auth-header h1 {
      margin: 0 0 6px;
      font-size: 26px;
      font-weight: 600;
    }
    .auth-header p {
      margin: 0 0 18px;
      color: rgba(148, 163, 184, 0.82);
      font-size: 14px;
      line-height: 1.6;
    }
    .auth-tabs {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 18px;
      background: rgba(15, 23, 42, 0.55);
      padding: 6px;
      border-radius: 12px;
    }
    .auth-tabs .tab {
      border: none;
      border-radius: 10px;
      padding: 10px 16px;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.015em;
      cursor: pointer;
      color: rgba(226, 232, 240, 0.75);
      background: transparent;
      transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    }
    .auth-tabs .tab.active {
      background: linear-gradient(135deg, rgba(99, 102, 241, 0.22), rgba(45, 212, 191, 0.18));
      color: #f8fafc;
      box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.45);
    }
    .auth-content {
      display: none;
      animation: fadeIn 0.25s ease forwards;
    }
    .auth-content.active {
      display: block;
    }
    label {
      display: block;
      font-size: 13px;
      margin-bottom: 6px;
      color: rgba(226, 232, 240, 0.86);
      letter-spacing: 0.01em;
    }
    input[type="text"],
    input[type="email"],
    input[type="password"] {
      width: 100%;
      border-radius: 12px;
      border: 1px solid rgba(148, 163, 184, 0.28);
      background: rgba(2, 6, 23, 0.92);
      color: #f9fafb;
      font-size: 14px;
      padding: 11px 14px;
      margin-bottom: 16px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus {
      outline: none;
      border-color: rgba(99, 102, 241, 0.8);
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
    }
    .form-note {
      margin-top: -8px;
      margin-bottom: 16px;
      font-size: 12px;
      color: rgba(148, 163, 184, 0.7);
    }
    .hidden {
      display: none !important;
    }
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(6px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    @media (max-width: 480px) {
      .security-card,
      .auth-card {
        padding: 26px 22px;
        border-radius: 16px;
      }
      .security-card h1 {
        font-size: 24px;
      }
      .ip-value {
        font-size: 22px;
      }
    }
  </style>
</head>
<body>
  <?php $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; ?>
  <div class="auth-layout">
    <section class="security-card" id="securityGate">
      <h1>Security Check</h1>
      <p>Memverifikasi alamat IP kamu sebelum mengakses Freepik Multi Suite.</p>
      <div class="ip-panel">
        <span class="ip-label">Your IP Address</span>
        <span class="ip-value" id="securityIp"><?= htmlspecialchars($clientIp, ENT_QUOTES) ?></span>
        <span class="ip-note">IP ini akan dipantau untuk pembagian yang tidak sah.</span>
      </div>
      <button type="button" id="securityContinue">Continue</button>
      <div class="security-status" id="securityStatus"></div>
    </section>

    <main class="auth-card hidden" id="authPanel">
      <header class="auth-header">
        <h1>Freepik Multi Suite</h1>
        <p>Masuk dengan akun kamu atau daftar menggunakan API key Freepik yang valid.</p>
      </header>
      <div class="auth-tabs" role="tablist">
        <button type="button" class="tab active" data-target="login" aria-selected="true">Login</button>
        <button type="button" class="tab" data-target="register" aria-selected="false">Register</button>
      </div>
      <section class="auth-content active" data-panel="login" id="loginPanel">
        <form id="loginForm" autocomplete="off">
          <label for="loginUsername">Username</label>
          <input type="text" id="loginUsername" name="username" autocomplete="username" required>
          <label for="loginPassword">Password</label>
          <input type="password" id="loginPassword" name="password" autocomplete="current-password" required>
          <button type="submit" id="loginSubmit">Masuk</button>
        </form>
        <div class="form-status" id="loginStatus"></div>
      </section>
      <section class="auth-content" data-panel="register" id="registerPanel">
        <form id="registerForm" autocomplete="off">
          <label for="registerApiKey">Freepik API Key</label>
          <input type="text" id="registerApiKey" name="freepik_api_key" autocomplete="off" required>
          <div class="form-note">Pastikan API key aktif dan belum digunakan akun lain.</div>
          <label for="registerUsername">Username</label>
          <input type="text" id="registerUsername" name="username" autocomplete="new-username" required>
          <label for="registerEmail">Email</label>
          <input type="email" id="registerEmail" name="email" autocomplete="email" required>
          <label for="registerPassword">Password</label>
          <input type="password" id="registerPassword" name="password" autocomplete="new-password" required>
          <button type="submit" id="registerSubmit">Daftar</button>
        </form>
        <div class="form-status" id="registerStatus"></div>
      </section>
    </main>
  </div>

  <script>
    const securityGate = document.getElementById('securityGate');
    const authPanel = document.getElementById('authPanel');
    const securityContinue = document.getElementById('securityContinue');
    const securityStatus = document.getElementById('securityStatus');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const loginStatus = document.getElementById('loginStatus');
    const registerStatus = document.getElementById('registerStatus');
    const loginSubmit = document.getElementById('loginSubmit');
    const registerSubmit = document.getElementById('registerSubmit');
    const tabs = document.querySelectorAll('.auth-tabs .tab');
    const panels = document.querySelectorAll('.auth-content');

    function setStatus(el, message, isError = true) {
      if (!el) return;
      el.textContent = message || '';
      el.style.color = isError ? '#f97373' : '#4ade80';
    }

    function formatErrors(error) {
      if (!error) {
        return 'Terjadi kesalahan. Coba lagi.';
      }
      if (typeof error === 'string') {
        return error;
      }
      if (Array.isArray(error)) {
        return error.join('\n');
      }
      if (typeof error === 'object') {
        return Object.entries(error)
          .map(([key, value]) => `${key}: ${value}`)
          .join('\n');
      }
      return String(error);
    }

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.target;
        tabs.forEach((btn) => {
          const isActive = btn === tab;
          btn.classList.toggle('active', isActive);
          btn.setAttribute('aria-selected', String(isActive));
        });
        panels.forEach((panel) => {
          panel.classList.toggle('active', panel.dataset.panel === target);
        });
      });
    });

    if (securityContinue) {
      securityContinue.addEventListener('click', async () => {
        securityContinue.disabled = true;
        setStatus(securityStatus, 'Memverifikasi IP...', false);
        try {
          const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=security-check', {
            method: 'POST',
          });
          const data = await res.json();
          if (!res.ok || !data.ok) {
            throw new Error(data.error || 'Security check gagal.');
          }
          const ipValue = document.getElementById('securityIp');
          if (ipValue && data.ip) {
            ipValue.textContent = data.ip;
          }
          setStatus(securityStatus, 'IP tervalidasi. Silakan lanjut.', false);
          setTimeout(() => {
            securityGate?.classList.add('hidden');
            authPanel?.classList.remove('hidden');
            setStatus(securityStatus, '');
          }, 400);
        } catch (error) {
          setStatus(securityStatus, formatErrors(error.message), true);
          securityContinue.disabled = false;
        }
      });
    }

    if (loginForm) {
      loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!loginSubmit) return;
        setStatus(loginStatus, '');
        loginSubmit.classList.add('loading');
        loginSubmit.disabled = true;
        try {
          const formData = new FormData(loginForm);
          const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=login', {
            method: 'POST',
            body: formData,
          });
          const data = await res.json();
          if (!res.ok || !data.ok) {
            const error = data.error || 'Login gagal. Periksa kembali data kamu.';
            setStatus(loginStatus, formatErrors(error), true);
          } else {
            setStatus(loginStatus, 'Login berhasil! Mengalihkan...', false);
            setTimeout(() => window.location.reload(), 600);
          }
        } catch (err) {
          setStatus(loginStatus, 'Terjadi kesalahan jaringan. Coba lagi.', true);
        } finally {
          loginSubmit.classList.remove('loading');
          loginSubmit.disabled = false;
        }
      });
    }

    if (registerForm) {
      registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!registerSubmit) return;
        setStatus(registerStatus, '');
        registerSubmit.classList.add('loading');
        registerSubmit.disabled = true;
        try {
          const formData = new FormData(registerForm);
          const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=register', {
            method: 'POST',
            body: formData,
          });
          const data = await res.json();
          if (!res.ok || !data.ok) {
            const error = data.error || 'Registrasi gagal.';
            setStatus(registerStatus, formatErrors(error), true);
          } else {
            setStatus(registerStatus, 'Registrasi berhasil! Silakan login.', false);
            const loginUser = document.getElementById('loginUsername');
            if (loginUser && registerForm.username) {
              loginUser.value = registerForm.username.value;
            }
            tabs.forEach((tab) => {
              const isLogin = tab.dataset.target === 'login';
              tab.classList.toggle('active', isLogin);
              tab.setAttribute('aria-selected', String(isLogin));
            });
            panels.forEach((panel) => {
              panel.classList.toggle('active', panel.dataset.panel === 'login');
            });
            registerForm.reset();
          }
        } catch (err) {
          setStatus(registerStatus, 'Terjadi kesalahan jaringan. Coba lagi.', true);
        } finally {
          registerSubmit.classList.remove('loading');
          registerSubmit.disabled = false;
        }
      });
    }
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
  <title>Freepik Multi Suite – AI Hub + Filmmaker + UGC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color-scheme: dark;
      --bg: #050509;
      --card: #11121a;
      --card-soft: #181926;
      --accent: #6366f1;
      --accent-soft: rgba(99, 102, 241, 0.15);
      --border: #202236;
      --text: #f9fafb;
      --muted: #9ca3af;
      --danger: #f97373;
      --success: #4ade80;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background: radial-gradient(circle at top, #1f2937 0, #02030a 55%, #000 100%);
      color: var(--text);
    }
    body::before,
    body::after {
      content: "";
      position: fixed;
      inset: -40vh -40vw;
      pointer-events: none;
      background: radial-gradient(circle at center, rgba(99,102,241,0.18), transparent 55%);
      opacity: 0.6;
      filter: blur(60px);
      animation: orbitGlow 28s linear infinite;
      z-index: 0;
    }
    body::after {
      animation-duration: 36s;
      animation-direction: reverse;
      background: radial-gradient(circle at center, rgba(34,197,94,0.18), transparent 55%);
    }
    @keyframes orbitGlow {
      from { transform: rotate(0deg) scale(1.05); }
      50% { transform: rotate(180deg) scale(1.15); }
      to { transform: rotate(360deg) scale(1.05); }
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 20;
      padding: 10px 16px 6px;
      background: rgba(5,5,12,0.96);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(31,41,55,0.7);
      display: flex;
      justify-content: space-between;
      align-items: center;
      column-gap: 12px;
      row-gap: 10px;
      flex-wrap: wrap;
    }
    .topbar-brand {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .topbar-title {
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.04em;
    }
    .topbar-sub {
      font-size: 11px;
      color: var(--muted);
    }
    .topbar-tabs {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      justify-content: center;
      flex: 1;
    }
    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-left: auto;
    }
    .topbar-user {
      font-size: 12px;
      color: var(--muted);
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 8px;
      border-radius: 999px;
      background: rgba(148, 163, 184, 0.12);
      border: 1px solid rgba(148, 163, 184, 0.18);
    }
    .logout-btn {
      border-radius: 999px;
      border: 1px solid rgba(99,102,241,0.35);
      background: rgba(15,23,42,0.9);
      color: var(--text);
      font-size: 12px;
      padding: 6px 14px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .logout-btn:hover {
      border-color: rgba(129,140,248,0.7);
      color: #e0e7ff;
      box-shadow: 0 8px 22px rgba(79,70,229,0.35);
    }
    .logout-btn.loading {
      opacity: 0.65;
      pointer-events: none;
    }
    @media (max-width: 768px) {
      .topbar {
        justify-content: flex-start;
      }
      .topbar-actions {
        width: 100%;
        justify-content: flex-end;
      }
      .topbar-tabs {
        width: 100%;
        justify-content: flex-start;
      }
    }
    .top-tab {
      border-radius: 999px;
      border: 1px solid var(--border);
      background: #020617;
      color: var(--muted);
      padding: 6px 14px;
      font-size: 12px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.25s ease;
    }
    .top-tab.active {
      background: linear-gradient(135deg, #6366f1, #22c55e);
      color: #020617;
      border-color: transparent;
      box-shadow: 0 8px 26px rgba(79,70,229,0.55);
    }
    .top-tab:not(.active):hover {
      border-color: rgba(99,102,241,0.6);
      color: var(--text);
      box-shadow: 0 8px 26px rgba(79,70,229,0.25);
    }
    .top-tab .dot {
      width: 7px;
      height: 7px;
      border-radius: 999px;
      background: var(--success);
    }

    .app {
      display: grid;
      grid-template-columns: 260px 1fr 320px;
      gap: 18px;
      min-height: calc(100vh - 50px);
      padding: 12px 16px 16px;
      position: relative;
      z-index: 1;
    }
    @media (max-width: 1100px) {
      .app { grid-template-columns: 1fr; }
    }

    .card {
      background: radial-gradient(circle at top left, #1e1b4b 0, var(--card) 55%);
      border-radius: 14px;
      border: 1px solid var(--border);
      padding: 14px 16px;
      box-shadow: 0 18px 60px rgba(0, 0, 0, 0.7);
      position: relative;
      overflow: hidden;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card::before {
      content: "";
      position: absolute;
      inset: -30%;
      background: conic-gradient(from 90deg, rgba(99,102,241,0.35), transparent 45%, rgba(34,197,94,0.25));
      filter: blur(60px);
      opacity: 0;
      transition: opacity 0.4s ease;
      animation: cardGlow 12s linear infinite;
      z-index: 0;
    }
    .card:hover {
      transform: translateY(-4px);
      box-shadow: 0 24px 80px rgba(15, 23, 42, 0.75);
    }
    .card:hover::before {
      opacity: 0.9;
    }
    @keyframes cardGlow {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    @keyframes gradientShift {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }
    .card > * { position: relative; z-index: 1; }
    .card-soft {
      background: var(--card-soft);
      border-radius: 12px;
      border: 1px solid var(--border);
      padding: 12px 14px;
      position: relative;
      overflow: hidden;
    }
    .card-soft::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(14,165,233,0.04));
      opacity: 0;
      transition: opacity 0.4s ease;
      z-index: 0;
    }
    .card-soft:hover::before {
      opacity: 1;
    }
    .card-soft > * { position: relative; z-index: 1; }
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
      border: 1px solid rgba(148, 163, 184, 0.5);
      background: radial-gradient(circle at top, #111827 0, #020617 70%);
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
      background: #020617;
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
      background: #020617;
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
      background: #020617;
      color: var(--muted);
      font-size: 11px;
      padding: 6px 10px;
      cursor: pointer;
      text-align: center;
      transition: all 0.25s ease;
      position: relative;
      overflow: hidden;
    }
    .feature-tab.active {
      background: var(--accent-soft);
      border-color: var(--accent);
      color: var(--text);
      box-shadow: 0 10px 30px rgba(79,70,229,0.35);
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
      height: 8px;
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
    .preview-card { margin-top: 8px; }
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
      height: 6px;
    }
    .preview-item {
      background: #020617;
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
      aspect-ratio: 1 / 1;
      border-radius: 8px;
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
      object-fit: contain;
      background: #000;
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
      background: #020617;
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
      background: #020617;
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
      background: #020617;
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
      height: 6px;
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
      background: radial-gradient(circle at top, #020617 0, #020617 60%);
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
      background: #020617;
    }
    .film-scenes-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 10px;
    }
    .film-scene-card {
      background: #020617;
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
      max-height: 220px;
      border-radius: 8px;
      object-fit: contain;
      background: #020617;
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
      background: #020617;
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
      background: #020617;
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
      background: radial-gradient(circle at top, #020617 0, #020617 60%);
      border-radius: 12px;
      border: 1px dashed rgba(75,85,99,0.8);
      padding: 12px 14px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .ugc-empty {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 32px 10px;
      font-size: 12px;
      color: var(--muted);
    }
    .ugc-row {
      background: #020617;
      border-radius: 12px;
      border: 1px solid var(--border);
      display: grid;
      grid-template-columns: 210px 1fr;
      gap: 12px;
      padding: 10px;
    }
    @media (max-width: 900px) {
      .ugc-row { grid-template-columns: 1fr; }
    }
    .ugc-media-block {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .ugc-media-card {
      border-radius: 10px;
      border: 1px solid var(--border);
      background: linear-gradient(180deg,#7c3aed,#0f172a);
      padding: 10px 8px;
      color: #e5e7eb;
      font-size: 11px;
      text-align: center;
      min-height: 160px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .ugc-media-card img {
      width: 100%;
      max-height: 210px;
      border-radius: 8px;
      object-fit: cover;
      background:#000;
    }
    .ugc-media-title {
      font-size: 11px;
      font-weight: 500;
      margin-bottom: 2px;
    }
    .ugc-media-status {
      font-size: 10px;
      opacity: .8;
    }
    .ugc-video-card {
      border-radius: 10px;
      border: 1px solid var(--border);
      background: radial-gradient(circle at top,#111827,#020617);
      padding: 8px;
      font-size: 11px;
      text-align: center;
      min-height: 80px;
      display:flex;
      flex-direction: column;
      gap: 6px;
      align-items:center;
      justify-content:center;
    }
    .ugc-video-card video {
      width: 100%;
      max-height: 210px;
      border-radius: 8px;
      background: #000;
    }
    .ugc-video-actions {
      display: flex;
      gap: 6px;
      justify-content: center;
      flex-wrap: wrap;
    }
    .ugc-right {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .ugc-prompt-label {
      font-size: 11px;
      color: var(--muted);
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
      background:#020617;
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
      background: #020617;
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
      background: #020617;
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
      background: #020617;
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
      overflow: hidden;
    }
    .asset-preview-body img,
    .asset-preview-body video {
      max-width: 100%;
      max-height: 100%;
      border-radius: 12px;
      background: #000;
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
  </style>
</head>
<body>

<div class="topbar">
  <div class="topbar-brand">
    <div class="topbar-title">Freepik Multi Suite</div>
    <div class="topbar-sub">AI Hub • Filmmaker • UGC Tool</div>
  </div>
  <div class="topbar-tabs">
    <button class="top-tab active" data-target="viewHub">
      <span class="dot"></span> AI Hub
    </button>
    <button class="top-tab" data-target="viewFilm">
      <span class="dot"></span> Filmmaker
    </button>
    <button class="top-tab" data-target="viewUGC">
      <span class="dot"></span> UGC Tool
    </button>
  </div>
  <div class="topbar-actions">
    <?php if ($currentUser !== ''): ?>
      <span class="topbar-user">👤 <?= htmlspecialchars($currentUser, ENT_QUOTES) ?></span>
    <?php endif; ?>
    <button type="button" class="logout-btn" id="logoutButton">Keluar</button>
  </div>
</div>

<!-- ======================= AI HUB ======================= -->
<div id="viewHub" class="app">
  <div class="card">
    <div class="header">
      <div>
        <div class="title">Freepik AI Studio</div>
        <div class="subtitle">
          <span id="featureLabel">Video Generator</span> · Single PHP • Multi model Freepik
        </div>
      </div>
      <div class="badge">
        <span class="dot-large"></span>
        <span>Proxy PHP aktif</span>
      </div>
    </div>

    <div class="feature-tabs">
      <button type="button" class="feature-tab" data-feature="imageGen">Image Generator</button>
      <button type="button" class="feature-tab" data-feature="imageEdit">Image Editing</button>
      <button type="button" class="feature-tab active" data-feature="videoGen">Video Generator</button>
      <button type="button" class="feature-tab" data-feature="lipsync">Lipsync Studio</button>
    </div>

    <div class="select-group">
      <div class="model-group-label">Model</div>
      <select id="modelSelect">
        <optgroup label="Text → Image">
          <option value="gemini">Gemini 2.5 Flash</option>
          <option value="imagen3">Google Imagen 3</option>
          <option value="seedream4">Seedream 4</option>
          <option value="seedream4edit">Seedream 4 Edit</option>
          <option value="fluxPro11">Flux Pro v1.1</option>
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
          <option value="kling25Pro">Kling v2.5 Pro</option>
          <option value="minimax1080">MiniMax Hailuo 02 – 1080p</option>
        </optgroup>
        <optgroup label="Lip Sync">
          <option value="latentSync">Latent-Sync</option>
        </optgroup>
      </select>
    </div>

    <div class="card-soft">
      <div class="small-label">Hint input</div>
      <div id="modelHint" class="muted" style="font-size:11px">
        Text→Image: cukup prompt.  
        Upscale/remove BG: wajib image URL.  
        Image→Video: image URL + prompt.  
        Latent-Sync: video URL + audio URL.
      </div>
    </div>
  </div>

  <div class="card main-layout">
    <form id="jobForm">
      <div class="two-col">
        <div id="rowPrompt">
          <label for="prompt">Prompt</label>
          <textarea id="prompt" placeholder="Deskripsikan gambar/video yang diinginkan"></textarea>
        </div>

        <div>
          <div id="fieldsTitle" class="form-section-title">Video Generator</div>

          <div id="geminiModeSection" class="gemini-mode-section hidden">
            <div class="form-section-title">Gemini Flash Modes</div>
            <div class="gemini-mode-toggle">
              <button type="button" class="gemini-mode-btn active" data-gemini-mode="text">
                <strong>Mode 1 · Text-to-Image</strong>
                <span>Kirim deskripsi tanpa gambar referensi.</span>
              </button>
              <button type="button" class="gemini-mode-btn" data-gemini-mode="single">
                <strong>Mode 2 · Single Image-to-Image</strong>
                <span>Unggah 1 gambar + caption untuk editing.</span>
              </button>
              <button type="button" class="gemini-mode-btn" data-gemini-mode="multi">
                <strong>⭐ Mode 3 · Multi-Image Reference</strong>
                <span>Kombinasikan 2-3 gambar referensi + caption.</span>
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
        <div class="preview-grid" id="previewGrid"></div>
      </div>
    </div>
  </div>

  <div class="jobs-col">
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
  </div>
</div>

<!-- ======================= FILMMAKER ======================= -->
<div id="viewFilm" class="film-app" style="display:none">
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
          <div class="subtitle">No scenes yet</div>
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
            <div style="margin-bottom:4px;">Upload character image</div>
            <span>PNG, JPG · dipakai konsisten untuk semua scene</span>
          </div>
          <img id="filmCharacterPreview" class="film-character-preview" style="display:none" alt="Character preview">
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
      </div>
    </div>
  </div>
</div>

<!-- ======================= UGC TOOL ======================= -->
<div id="viewUGC" class="ugc-app" style="display:none">
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
            <span>PNG, JPG · max 3 images</span>
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
            <span>PNG, JPG · 1 image</span>
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
        <textarea id="ugcBrief" placeholder="Contoh: Promoting a sustainable water bottle for fitness enthusiasts, emphasizing eco-friendly lifestyle and outdoor activities..."></textarea>
      </div>

      <div>
        <button type="button" id="ugcGenerateBtn" style="width:100%;margin-top:4px;">
          Generate UGC
        </button>
        <div class="muted" style="font-size:10px;margin-top:6px;">
          Sistem akan membuat 5 ide UGC beserta gambar dari Gemini Flash 2.5.
          Tiap baris punya prompt video + tombol Generate Video (Wan 720) &amp; Download.
        </div>
      </div>
    </div>
  </div>
</div>

<div id="assetPreviewModal" class="asset-preview hidden">
  <div class="asset-preview-inner">
    <button type="button" id="assetPreviewClose" class="asset-preview-close">&times;</button>
    <div id="assetPreviewBody" class="asset-preview-body"></div>
    <a id="assetPreviewDownload" class="asset-preview-download" href="#" download target="_blank">Download file</a>
  </div>
</div>

<script>
  const logoutButton = document.getElementById('logoutButton');
  if (logoutButton) {
    logoutButton.addEventListener('click', async () => {
      if (logoutButton.classList.contains('loading')) {
        return;
      }

      logoutButton.classList.add('loading');
      try {
        const res = await fetch('<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>?api=logout', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({})
        });

        if (!res.ok) {
          const text = await res.text();
          console.error('Logout gagal:', text);
          alert('Logout gagal. Silakan coba lagi.');
          return;
        }

        window.location.reload();
      } catch (err) {
        console.error('Logout error', err);
        alert('Terjadi kesalahan saat logout.');
      } finally {
        logoutButton.classList.remove('loading');
      }
    });
  }

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

  function applyVideoExtras(body, formData = {}) {
    if (!body || typeof body !== 'object') return body;
    const duration = formData.videoDuration;
    if (typeof duration === 'number' && !Number.isNaN(duration) && duration > 0) {
      body.duration = duration;
    }
    const ratio = mapVideoAspect(formData.videoLayout);
    if (ratio && (ratio !== 'auto' || !body.aspect_ratio)) {
      body.aspect_ratio = ratio;
    } else if (!body.aspect_ratio) {
      body.aspect_ratio = 'auto';
    }
    return body;
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
      buildBody: f => ({ prompt: f.prompt, num_images: f.numImages || 1 })
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

      return {
        prompt: f.prompt,
        aspect_ratio: f.aspect_ratio || 'square_1_1',
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
    kling25Pro: {
      id: 'kling25Pro',
      label: 'Kling v2.5 Pro',
      type: 'video',
      path: '/v1/ai/image-to-video/kling-v2-5-pro',
      statusPath: taskId => `/v1/ai/image-to-video/kling-v2-5-pro/${taskId}`,
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

  const FEATURE_MODELS = {
    imageGen: ['gemini','imagen3','seedream4','fluxPro11'],
    imageEdit: ['seedream4edit','upscalerCreative','upscalePrecV1','upscalePrecV2','removeBg'],
    videoGen: ['wan480','wan720','seedancePro480','seedancePro720','seedancePro1080','klingStd21','kling25Pro','minimax1080'],
    lipsync: ['latentSync']
  };

  const STORAGE_KEY = 'freepik_jobs_v1';
  let jobs = loadJobs();
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
  const featureTabs = document.querySelectorAll('.feature-tab');
  const featureLabel = document.getElementById('featureLabel');

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

    const isT2I = ['gemini','imagen3','seedream4','fluxPro11'].includes(id);
    const isEdit = ['seedream4edit','upscalerCreative','upscalePrecV1','upscalePrecV2','removeBg'].includes(id);
    const isI2V = ['wan480','wan720','seedancePro480','seedancePro720','seedancePro1080','klingStd21','kling25Pro','minimax1080'].includes(id);
    const isLip = id === 'latentSync';

    rowImageUrl.classList.add('hidden');
    rowVideoAudio.classList.add('hidden');
    rowVideoSettings.classList.add('hidden');
    rowTIOptions.classList.add('hidden');
    rowPrompt.classList.remove('hidden');

    if (isT2I) {
      fieldsTitle.textContent = 'Image Generator';
      rowTIOptions.classList.remove('hidden');
    } else if (isEdit) {
      fieldsTitle.textContent = 'Image Editing';
      rowImageUrl.classList.remove('hidden');
      if (id === 'removeBg') rowPrompt.classList.add('hidden');
    } else if (isI2V) {
      fieldsTitle.textContent = 'Video Generator';
      rowImageUrl.classList.remove('hidden');
      rowVideoSettings.classList.remove('hidden');
      configureVideoControls(id);
    } else if (isLip) {
      fieldsTitle.textContent = 'Lipsync Studio';
      rowVideoAudio.classList.remove('hidden');
    } else {
      fieldsTitle.textContent = 'Input';
    }

    syncGeminiVisibility();
  }

  let currentFeature = 'videoGen';

  function setFeature(featureKey) {
    currentFeature = featureKey;
    featureTabs.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.feature === featureKey);
    });

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
    if (featureKey === 'imageGen') label = 'Image Generator';
    else if (featureKey === 'imageEdit') label = 'Image Editing';
    else if (featureKey === 'videoGen') label = 'Video Generator';
    else if (featureKey === 'lipsync') label = 'Lipsync Studio';
    else label = 'AI Hub';
    featureLabel.textContent = label;
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

    return { taskId, status, generated, extraUrl, references: usedGeminiRefs, geminiModeUsed: usedGeminiMode };
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

    submitBtn.disabled = true;
    setStatus('Membuat task ke Freepik…');

    try {
      const { taskId, status, generated, extraUrl, references, geminiModeUsed } = await createTask(modelId);
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
        extraUrl: extraUrl || null
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
      }
    } catch (err) {
      console.error(err);
      setStatus('Error: ' + err.message, 'err');
    } finally {
      submitBtn.disabled = false;
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

  featureTabs.forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.dataset.feature;
      setFeature(key);
    });
  });

  const topTabs = document.querySelectorAll('.top-tab');
  const viewHub = document.getElementById('viewHub');
  const viewFilm = document.getElementById('viewFilm');
  const viewUGC = document.getElementById('viewUGC');

  topTabs.forEach(btn => {
    btn.addEventListener('click', () => {
      topTabs.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const target = btn.dataset.target;
      viewHub.style.display  = (target === 'viewHub')  ? 'grid' : 'none';
      viewFilm.style.display = (target === 'viewFilm') ? 'grid' : 'none';
      viewUGC.style.display  = (target === 'viewUGC')  ? 'grid' : 'none';
    });
  });

  // ===== FILMMAKER STATE =====
  let filmCharacterDataUrl = null;
  let filmAspect = '16:9';
  let filmScenes = [];
  let filmPollTimer = null;

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

  function capitalizeFirst(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function ensureSentence(text, fallback) {
    const base = (text || '').trim();
    if (!base) return fallback;
    return /[.!?]$/.test(base) ? base : base + '.';
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
    return parts.map((part, idx) => {
      const index = idx + 1;
      const action = ensureSentence(capitalizeFirst(part), 'Describe the protagonist in action.');
      const environment = filmSceneLocations[idx % filmSceneLocations.length];
      const lighting = filmSceneLightingPresets[idx % filmSceneLightingPresets.length];
      const camera = filmSceneCameraAngles[idx % filmSceneCameraAngles.length];
      const mood = filmSceneMoods[idx % filmSceneMoods.length];
      const continuity = idx === 0
        ? 'Opening beat introducing the story.'
        : filmNarrativeBeats[(idx - 1) % filmNarrativeBeats.length];

      const promptLines = [
        `Scene ${index}: ${action}`,
        `Setting/environment: ${environment}.`,
        `Lighting: ${lighting}.`,
        `Camera style: ${camera}.`,
        `Mood: ${mood}.`,
        `Narrative continuity: ${continuity}`,
        'Maintain the protagonist consistent with the uploaded character reference.'
      ];

      return {
        index,
        prompt: promptLines.join(' '),
        meta: {
          action,
          environment,
          lighting,
          camera,
          mood,
          continuity
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

  filmAspectButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      filmAspectButtons.forEach(b => b.classList.remove('film-aspect-active'));
      btn.classList.add('film-aspect-active');
      filmAspect = btn.dataset.filmAspect;
    });
  });

  function renderFilmScenes() {
    if (!filmScenes.length) {
      filmScenesEmpty.style.display = 'flex';
      filmScenesContainer.innerHTML = '';
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

    filmGenerateBtn.disabled = true;
    filmScenes = [];
    renderFilmScenes();

    const cfg = MODEL_CONFIG.gemini;
    const base64 = filmCharacterDataUrl.replace(/^data:image\/[a-zA-Z+]+;base64,/, '');

    const scenePlans = buildFilmScenePlans(brief, count);

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

      try {
        const data = await callFreepik(cfg, body, 'POST');
        let taskId = null;
        let status = 'CREATED';
        if (data && data.data) {
          taskId = data.data.task_id || null;
          status  = data.data.status   || status;
        }
        filmScenes.push({
          index: plan.index,
          prompt: scenePrompt,
          meta: plan.meta,
          taskId,
          status,
          url: null
        });
      } catch (err) {
        console.error(err);
        filmScenes.push({
          index: plan.index,
          prompt: scenePrompt,
          meta: plan.meta,
          taskId: null,
          status: 'ERROR',
          url: null
        });
      }
      renderFilmScenes();
    }

    startFilmPolling();
    filmGenerateBtn.disabled = false;
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

  function renderUgcList() {
    ugcList.innerHTML = '';
    if (!ugcItems.length) {
      ugcEmpty.style.display = 'flex';
      ugcList.appendChild(ugcEmpty);
      return;
    }
    ugcEmpty.style.display = 'none';

    ugcItems.forEach(item => {
      const row = document.createElement('div');
      row.className = 'ugc-row';
      row.dataset.index = item.index;

      const left = document.createElement('div');
      left.className = 'ugc-media-block';

      const imgCard = document.createElement('div');
      imgCard.className = 'ugc-media-card';

      if (item.imageUrl) {
        const img = document.createElement('img');
        img.src = item.imageUrl;
        img.alt = 'UGC Image ' + item.index;
        img.classList.add('clickable-media');
        img.addEventListener('click', () => openAssetPreview(item.imageUrl, 'image'));
        imgCard.innerHTML = '';
        imgCard.appendChild(img);
      } else {
        imgCard.innerHTML = '<div><div class=\"ugc-media-title\">Image #' + item.index +
          '</div><div class=\"ugc-media-status\">Generating... ' + (item.status || 'CREATED') + '</div></div>';
      }

      const videoCard = document.createElement('div');
      videoCard.className = 'ugc-video-card';
      if (item.videoUrl) {
        videoCard.innerHTML = '';
        const title = document.createElement('div');
        title.className = 'ugc-media-title';
        title.textContent = 'Video ready';

        const video = document.createElement('video');
        video.src = item.videoUrl;
        video.controls = true;
        video.loop = true;
        video.muted = true;
        video.playsInline = true;

        const actions = document.createElement('div');
        actions.className = 'ugc-video-actions';

        const previewVideoBtn = document.createElement('button');
        previewVideoBtn.type = 'button';
        previewVideoBtn.className = 'small secondary';
        previewVideoBtn.textContent = 'Preview Video';
        previewVideoBtn.addEventListener('click', () => openAssetPreview(item.videoUrl, 'video'));
        actions.appendChild(previewVideoBtn);

        const videoDownloadLink = document.createElement('a');
        videoDownloadLink.href = item.videoUrl;
        videoDownloadLink.target = '_blank';
        videoDownloadLink.download = '';
        videoDownloadLink.className = 'download-link';

        const videoDownloadBtn = document.createElement('button');
        videoDownloadBtn.type = 'button';
        videoDownloadBtn.className = 'small';
        videoDownloadBtn.textContent = 'Download';
        videoDownloadLink.appendChild(videoDownloadBtn);
        actions.appendChild(videoDownloadLink);

        videoCard.appendChild(title);
        videoCard.appendChild(video);
        videoCard.appendChild(actions);
      } else if (item.videoJobId) {
        videoCard.innerHTML = '<div><div class=\"ugc-media-title\">Video generating...</div><div class=\"ugc-media-status\">Check status di Queue</div></div>';
      } else {
        videoCard.innerHTML = '<div><div class=\"ugc-media-title\">Video</div><div class=\"ugc-media-status\">No video yet</div></div>';
      }

      left.appendChild(imgCard);
      left.appendChild(videoCard);

      const right = document.createElement('div');
      right.className = 'ugc-right';

      const pLabel = document.createElement('div');
      pLabel.className = 'ugc-prompt-label';
      pLabel.textContent = 'UGC Prompt #' + item.index;
      const pText = document.createElement('textarea');
      pText.value = item.prompt || '';
      pText.rows = 3;
      pText.addEventListener('input', () => {
        item.prompt = pText.value;
      });

      const vLabel = document.createElement('div');
      vLabel.className = 'ugc-prompt-label';
      vLabel.textContent = 'Video Animation Prompt';
      const vText = document.createElement('textarea');
      vText.placeholder = 'contoh: model showing the product with a smile';
      vText.value = item.videoPrompt || '';
      vText.rows = 2;
      vText.addEventListener('input', () => {
        item.videoPrompt = vText.value;
      });

      const btnRow = document.createElement('div');
      btnRow.className = 'btn-group';

      const previewImgBtn = document.createElement('button');
      previewImgBtn.type = 'button';
      previewImgBtn.className = 'secondary small';
      previewImgBtn.textContent = 'Preview Image';
      previewImgBtn.disabled = !item.imageUrl;
      if (item.imageUrl) {
        previewImgBtn.addEventListener('click', () => openAssetPreview(item.imageUrl, 'image'));
      }

      const dlBtn = document.createElement('button');
      dlBtn.type = 'button';
      dlBtn.className = 'small';
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

      const vidBtn = document.createElement('button');
      vidBtn.type = 'button';
      vidBtn.className = 'small';
      vidBtn.textContent = 'Generate Video';
      vidBtn.disabled = !item.imageUrl;
      vidBtn.addEventListener('click', () => ugcGenerateVideo(item));

      btnRow.appendChild(previewImgBtn);
      btnRow.appendChild(dlBtn);
      btnRow.appendChild(vidBtn);

      right.appendChild(pLabel);
      right.appendChild(pText);
      right.appendChild(vLabel);
      right.appendChild(vText);
      right.appendChild(btnRow);

      row.appendChild(left);
      row.appendChild(right);

      ugcList.appendChild(row);
    });
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
    if (!ugcProductImages.length) {
      alert('Minimal upload 1 product image.');
      return;
    }
    const styleKey = (ugcStyleValueInput && ugcStyleValueInput.value) || DEFAULT_UGC_STYLE_KEY;
    const brief = ugcBriefInput.value.trim() || 'Product UGC photo shot';

    ugcGenerateBtn.disabled = true;
    ugcItems = [];
    renderUgcList();

    const cfg = MODEL_CONFIG.gemini;
    const refs = buildUgcReferences();

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
        videoUrl: null
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
      try {
        const data = await callFreepik(cfg, body, 'POST');
        if (data && data.data) {
          item.taskId = data.data.task_id || null;
          item.status = data.data.status || 'CREATED';
        }
      } catch (e) {
        console.error(e);
        item.status = 'ERROR';
      }
      renderUgcList();
    }

    if (ugcItems.some(s => s.taskId)) startUgcPolling();
    ugcGenerateBtn.disabled = false;
  }

  ugcGenerateBtn.addEventListener('click', () => { ugcGenerate(); });

  async function ugcGenerateVideo(item) {
  // WAJIB: pakai URL asli dari Freepik, bukan path lokal
  if (!item.remoteUrl || !item.remoteUrl.startsWith('http')) {
    alert('URL gambar untuk video belum valid.\n' +
          'Pastikan UGC image sudah COMPLETED, lalu klik Generate Video lagi.');
    return;
  }

  const cfg = MODEL_CONFIG.wan720;
  const body = {
    prompt: item.videoPrompt || ('UGC video animation for image #' + item.index),
    image: item.remoteUrl,   // <-- PENTING
    duration: 5,
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
      modelId: 'wan720',
      type: 'video',
      taskId,
      createdAt: nowIso(),
      updatedAt: nowIso(),
      status,
      generated: generated || [],
      extraUrl: null
    };
    jobs.unshift(job);
    saveJobs();
    renderJobs();
    if (taskId && !finalStatus(status)) {
      startJobProgress(job);
      startPolling(job);
    } else {
      finishJobProgress(job);
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
  assetPreviewBody.appendChild(el);

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
  setFeature('videoGen');
  jobs.filter(j => !finalStatus(j.status)).forEach(job => startJobProgress(job));
  renderJobs();
  if (jobs.length) {
    const lastCompleted = jobs.find(j => finalStatus(j.status)) || jobs[0];
    activeJobId = lastCompleted.id;
    renderPreview(lastCompleted);
  }
  filmSceneCountLabel.textContent = filmSceneCount.value + ' scenes';
</script>
</body>
</html>
