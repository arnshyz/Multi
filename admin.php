<?php
require_once __DIR__ . '/auth.php';

auth_session_start();

$ADMIN_DATA_FILE = __DIR__ . '/admin-data.json';

function admin_default_auth()
{
    $defaults = auth_default_credential_values();
    $defaults['updated_at'] = null;
    return $defaults;
}

function admin_default_data() {
    return [
        'users' => [],
        'apiKeys' => [],
        'meta' => [
            'rollingIndex' => 0,
        ],
        'auth' => admin_default_auth(),
    ];
}

function admin_data_path() {
    global $ADMIN_DATA_FILE;
    return $ADMIN_DATA_FILE;
}

function load_admin_data() {
    $path = admin_data_path();
    if (!is_file($path)) {
        $data = admin_default_data();
        save_admin_data($data);
        return $data;
    }

    $json = @file_get_contents($path);
    if ($json === false) {
        $data = admin_default_data();
        save_admin_data($data);
        return $data;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        $data = admin_default_data();
        save_admin_data($data);
        return $data;
    }

    $needsSave = false;

    if (!isset($data['users']) || !is_array($data['users'])) {
        $data['users'] = [];
        $needsSave = true;
    }
    if (!isset($data['apiKeys']) || !is_array($data['apiKeys'])) {
        $data['apiKeys'] = [];
        $needsSave = true;
    }
    if (!isset($data['meta']) || !is_array($data['meta'])) {
        $data['meta'] = [];
        $needsSave = true;
    }
    if (!isset($data['meta']['rollingIndex']) || !is_numeric($data['meta']['rollingIndex'])) {
        $data['meta']['rollingIndex'] = 0;
        $needsSave = true;
    }

    $authDefaults = admin_default_auth();
    if (!isset($data['auth']) || !is_array($data['auth'])) {
        $data['auth'] = $authDefaults;
        $needsSave = true;
    } else {
        $auth = $data['auth'];
        $username = isset($auth['username']) ? trim((string)$auth['username']) : '';
        if ($username === '') {
            $auth['username'] = $authDefaults['username'];
            $needsSave = true;
        } else {
            $auth['username'] = $username;
        }

        $passwordHash = isset($auth['password_hash']) ? trim((string)$auth['password_hash']) : '';
        if ($passwordHash === '' && isset($auth['password']) && $auth['password'] !== '') {
            $passwordHash = password_hash((string)$auth['password'], PASSWORD_DEFAULT);
            $needsSave = true;
        }
        if ($passwordHash === '') {
            $passwordHash = $authDefaults['password_hash'];
            $needsSave = true;
        }
        $auth['password_hash'] = $passwordHash;
        if (isset($auth['password'])) {
            unset($auth['password']);
            $needsSave = true;
        }

        if (!isset($auth['updated_at'])) {
            $auth['updated_at'] = $authDefaults['updated_at'];
            $needsSave = true;
        }

        $data['auth'] = $auth;
    }

    if ($needsSave) {
        save_admin_data($data);
    }

    return $data;
}

function save_admin_data($data) {
    $path = admin_data_path();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    return @file_put_contents($path, $json, LOCK_EX) !== false;
}

function respond_json($payload, $status = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function respond_ok($data, $status = 200) {
    respond_json([
        'ok' => true,
        'status' => $status,
        'data' => $data,
    ], $status);
}

function respond_error($message, $status = 400) {
    respond_json([
        'ok' => false,
        'status' => $status,
        'error' => $message,
    ], $status);
}

function read_payload() {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if ($data === null && trim($raw) !== '' && json_last_error() !== JSON_ERROR_NONE) {
        respond_error('Payload bukan JSON valid', 400);
    }
    if (!is_array($data)) {
        return [];
    }
    return $data;
}

function active_api_keys($data) {
    $keys = [];
    if (!empty($data['apiKeys']) && is_array($data['apiKeys'])) {
        foreach ($data['apiKeys'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (empty($item['active'])) {
                continue;
            }
            $keys[] = $item;
        }
    }
    return $keys;
}

function compute_next_key($data) {
    $active = active_api_keys($data);
    if (!$active) {
        return null;
    }
    $index = (int)($data['meta']['rollingIndex'] ?? 0);
    if ($index < 0) {
        $index = 0;
    }
    $next = $active[$index % count($active)];
    return [
        'id' => $next['id'] ?? null,
        'label' => $next['label'] ?? '',
        'key' => $next['key'] ?? '',
    ];
}

function count_active_keys($data) {
    return count(active_api_keys($data));
}

function ensure_user_defaults($user) {
    if (!is_array($user)) {
        return [];
    }
    $user['id'] = $user['id'] ?? uniqid('user_', true);
    $user['name'] = $user['name'] ?? '';
    $user['email'] = $user['email'] ?? '';
    $user['coins'] = isset($user['coins']) ? (int)$user['coins'] : 0;
    $user['subscription'] = $user['subscription'] ?? 'free';
    $user['banned'] = !empty($user['banned']);
    $user['blocked'] = !empty($user['blocked']);
    $user['created_at'] = $user['created_at'] ?? gmdate('c');
    $user['updated_at'] = $user['updated_at'] ?? gmdate('c');
    return $user;
}

if (isset($_GET['api'])) {
    $action = $_GET['api'];

    if (!auth_is_logged_in()) {
        respond_error('Unauthorized', 401);
    }

    switch ($action) {
        case 'state':
            $data = load_admin_data();
            respond_ok([
                'users' => array_map('ensure_user_defaults', $data['users']),
                'apiKeys' => array_values($data['apiKeys']),
                'meta' => $data['meta'],
                'nextKey' => compute_next_key($data),
                'activeKeyCount' => count_active_keys($data),
            ]);
            break;

        case 'addUser':
            $payload = read_payload();
            $name = trim($payload['name'] ?? '');
            if ($name === '') {
                respond_error('Nama wajib diisi', 422);
            }
            $email = trim($payload['email'] ?? '');
            $coins = isset($payload['coins']) ? max(0, (int)$payload['coins']) : 0;
            $subscription = trim($payload['subscription'] ?? 'free');
            if ($subscription === '') {
                $subscription = 'free';
            }

            $user = [
                'id' => uniqid('user_', true),
                'name' => $name,
                'email' => $email,
                'coins' => $coins,
                'subscription' => $subscription,
                'banned' => !empty($payload['banned']),
                'blocked' => !empty($payload['blocked']),
                'created_at' => gmdate('c'),
                'updated_at' => gmdate('c'),
            ];

            $data = load_admin_data();
            $data['users'][] = $user;
            if (!save_admin_data($data)) {
                respond_error('Gagal menyimpan data user', 500);
            }

            respond_ok(['user' => $user], 201);
            break;

        case 'updateUser':
            $payload = read_payload();
            $id = $payload['id'] ?? '';
            if (!$id) {
                respond_error('ID user wajib diisi', 422);
            }

            $data = load_admin_data();
            $updated = null;

            foreach ($data['users'] as &$user) {
                if (!is_array($user) || ($user['id'] ?? null) !== $id) {
                    continue;
                }

                if (array_key_exists('name', $payload)) {
                    $user['name'] = trim((string)$payload['name']);
                }
                if (array_key_exists('email', $payload)) {
                    $user['email'] = trim((string)$payload['email']);
                }
                if (array_key_exists('coins', $payload)) {
                    $user['coins'] = max(0, (int)$payload['coins']);
                }
                if (array_key_exists('subscription', $payload)) {
                    $subscription = trim((string)$payload['subscription']);
                    $user['subscription'] = $subscription === '' ? 'free' : $subscription;
                }
                if (array_key_exists('banned', $payload)) {
                    $user['banned'] = (bool)$payload['banned'];
                }
                if (array_key_exists('blocked', $payload)) {
                    $user['blocked'] = (bool)$payload['blocked'];
                }

                $user['updated_at'] = gmdate('c');
                $updated = $user;
                break;
            }
            unset($user);

            if (!$updated) {
                respond_error('User tidak ditemukan', 404);
            }

            if (!save_admin_data($data)) {
                respond_error('Gagal memperbarui user', 500);
            }

            respond_ok(['user' => $updated]);
            break;

        case 'deleteUser':
            $payload = read_payload();
            $id = $payload['id'] ?? '';
            if (!$id) {
                respond_error('ID user wajib diisi', 422);
            }
            $data = load_admin_data();
            $before = count($data['users']);
            $data['users'] = array_values(array_filter($data['users'], function ($user) use ($id) {
                return is_array($user) && ($user['id'] ?? null) !== $id;
            }));
            if ($before === count($data['users'])) {
                respond_error('User tidak ditemukan', 404);
            }
            if (!save_admin_data($data)) {
                respond_error('Gagal menghapus user', 500);
            }
            respond_ok(['deleted' => true]);
            break;

        case 'addApiKey':
            $payload = read_payload();
            $keyValue = trim($payload['key'] ?? '');
            if ($keyValue === '') {
                respond_error('API key wajib diisi', 422);
            }
            $label = trim($payload['label'] ?? '');
            $active = array_key_exists('active', $payload) ? (bool)$payload['active'] : true;

            $data = load_admin_data();
            $apiKey = [
                'id' => uniqid('key_', true),
                'label' => $label,
                'key' => $keyValue,
                'active' => $active,
                'created_at' => gmdate('c'),
                'updated_at' => gmdate('c'),
            ];
            $data['apiKeys'][] = $apiKey;
            if (!save_admin_data($data)) {
                respond_error('Gagal menyimpan API key', 500);
            }
            respond_ok(['apiKey' => $apiKey], 201);
            break;

        case 'updateApiKey':
            $payload = read_payload();
            $id = $payload['id'] ?? '';
            if (!$id) {
                respond_error('ID API key wajib diisi', 422);
            }

            $data = load_admin_data();
            $updated = null;

            foreach ($data['apiKeys'] as &$item) {
                if (!is_array($item) || ($item['id'] ?? null) !== $id) {
                    continue;
                }
                if (array_key_exists('label', $payload)) {
                    $item['label'] = trim((string)$payload['label']);
                }
                if (array_key_exists('key', $payload)) {
                    $newKey = trim((string)$payload['key']);
                    if ($newKey === '') {
                        respond_error('API key tidak boleh kosong', 422);
                    }
                    $item['key'] = $newKey;
                }
                if (array_key_exists('active', $payload)) {
                    $item['active'] = (bool)$payload['active'];
                }
                $item['updated_at'] = gmdate('c');
                $updated = $item;
                break;
            }
            unset($item);

            if (!$updated) {
                respond_error('API key tidak ditemukan', 404);
            }

            if (!save_admin_data($data)) {
                respond_error('Gagal memperbarui API key', 500);
            }

            respond_ok(['apiKey' => $updated]);
            break;

        case 'deleteApiKey':
            $payload = read_payload();
            $id = $payload['id'] ?? '';
            if (!$id) {
                respond_error('ID API key wajib diisi', 422);
            }
            $data = load_admin_data();
            $before = count($data['apiKeys']);
            $data['apiKeys'] = array_values(array_filter($data['apiKeys'], function ($item) use ($id) {
                return is_array($item) && ($item['id'] ?? null) !== $id;
            }));
            if ($before === count($data['apiKeys'])) {
                respond_error('API key tidak ditemukan', 404);
            }
            if (!save_admin_data($data)) {
                respond_error('Gagal menghapus API key', 500);
            }
            respond_ok(['deleted' => true]);
            break;

        case 'setPrimaryKey':
            $payload = read_payload();
            $id = $payload['id'] ?? '';
            if (!$id) {
                respond_error('ID API key wajib diisi', 422);
            }
            $data = load_admin_data();
            $activeIds = [];
            foreach ($data['apiKeys'] as $item) {
                if (!is_array($item) || empty($item['active']) || !isset($item['id'])) {
                    continue;
                }
                $activeIds[] = $item['id'];
            }
            if (!$activeIds) {
                respond_error('Tidak ada API key aktif untuk dirotasi', 422);
            }
            $idx = array_search($id, $activeIds, true);
            if ($idx === false) {
                respond_error('API key tidak ditemukan atau tidak aktif', 404);
            }
            $data['meta']['rollingIndex'] = (int)$idx;
            if (!save_admin_data($data)) {
                respond_error('Gagal memperbarui meta rotasi', 500);
            }
            respond_ok([
                'meta' => $data['meta'],
                'nextKey' => compute_next_key($data),
            ]);
            break;

        default:
            respond_error('Endpoint tidak ditemukan', 404);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Control Center</title>
  <style>
    :root {
      --bg: #020617;
      --panel: rgba(15,23,42,0.82);
      --panel-border: rgba(96,165,250,0.18);
      --text: #e2e8f0;
      --muted: rgba(148,163,184,0.8);
      --accent: rgba(99,102,241,0.92);
      --danger: #f87171;
      --success: #34d399;
      --warning: #facc15;
    }
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: radial-gradient(circle at top, rgba(30,64,175,0.35), transparent 55%),
                  radial-gradient(circle at bottom, rgba(59,130,246,0.2), transparent 50%),
                  #030712;
      color: var(--text);
      padding: 32px 16px 48px;
    }
    .wrapper {
      width: min(1120px, 100%);
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    header.top {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
    }
    header.top h1 {
      margin: 0 0 6px;
      font-size: 24px;
      font-weight: 600;
    }
    header.top p {
      margin: 0;
      color: var(--muted);
      font-size: 14px;
    }
    .back-link {
      align-self: flex-start;
      padding: 8px 14px;
      border-radius: 999px;
      border: 1px solid rgba(148,163,184,0.3);
      color: var(--text);
      text-decoration: none;
      background: rgba(15,23,42,0.6);
      transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .back-link:hover {
      transform: translateY(-1px);
      border-color: rgba(99,102,241,0.4);
    }
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 10px 16px;
      border-radius: 10px;
      background: rgba(15,23,42,0.9);
      border: 1px solid rgba(99,102,241,0.35);
      color: var(--text);
      font-size: 13px;
      box-shadow: 0 10px 30px rgba(2,6,23,0.35);
      opacity: 0;
      transform: translateY(-10px);
      pointer-events: none;
      transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .toast.show {
      opacity: 1;
      transform: translateY(0);
    }
    .toast.success {
      border-color: rgba(52,211,153,0.45);
      color: var(--success);
    }
    .toast.error {
      border-color: rgba(248,113,113,0.55);
      color: var(--danger);
    }
    .grid {
      display: grid;
      gap: 24px;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    }
    .panel {
      background: var(--panel);
      border: 1px solid var(--panel-border);
      border-radius: 20px;
      padding: 20px;
      box-shadow: 0 25px 60px rgba(15,23,42,0.25);
      backdrop-filter: blur(18px);
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .panel header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
    }
    .panel header h2 {
      margin: 0;
      font-size: 18px;
      font-weight: 600;
    }
    .panel header span {
      font-size: 12px;
      color: var(--muted);
    }
    .form-grid {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      align-items: end;
    }
    .field label {
      display: block;
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 4px;
    }
    .input {
      width: 100%;
      border-radius: 10px;
      border: 1px solid rgba(148,163,184,0.2);
      background: rgba(10,15,30,0.7);
      color: var(--text);
      padding: 10px 12px;
      font-size: 13px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      resize: vertical;
    }
    .input:focus {
      outline: none;
      border-color: rgba(99,102,241,0.45);
      box-shadow: 0 0 0 3px rgba(99,102,241,0.18);
    }
    .btn {
      border-radius: 999px;
      border: 1px solid transparent;
      padding: 8px 16px;
      font-size: 13px;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      background: rgba(15,23,42,0.6);
      color: var(--text);
    }
    .btn.primary {
      background: linear-gradient(120deg, rgba(99,102,241,0.8), rgba(59,130,246,0.75));
      border-color: rgba(99,102,241,0.5);
      box-shadow: 0 12px 24px rgba(59,130,246,0.35);
    }
    .btn.ghost {
      border-color: rgba(148,163,184,0.3);
    }
    .btn.danger {
      border-color: rgba(248,113,113,0.4);
      color: var(--danger);
    }
    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .btn:not(:disabled):hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(15,23,42,0.35);
    }
    .list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .empty {
      padding: 20px;
      border-radius: 14px;
      border: 1px dashed rgba(148,163,184,0.3);
      color: var(--muted);
      font-size: 13px;
      text-align: center;
    }
    .card {
      border-radius: 18px;
      border: 1px solid rgba(99,102,241,0.18);
      background: rgba(12,16,32,0.82);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .card-header {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
    }
    .card-title {
      font-size: 15px;
      font-weight: 600;
    }
    .card-meta {
      font-size: 11px;
      color: var(--muted);
      margin-top: 2px;
    }
    .chip-row {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }
    .chip {
      font-size: 11px;
      padding: 3px 8px;
      border-radius: 999px;
      background: rgba(99,102,241,0.18);
      border: 1px solid rgba(99,102,241,0.35);
      color: #c7d2fe;
    }
    .chip.success {
      background: rgba(52,211,153,0.18);
      border-color: rgba(52,211,153,0.4);
      color: var(--success);
    }
    .chip.danger {
      background: rgba(248,113,113,0.18);
      border-color: rgba(248,113,113,0.4);
      color: var(--danger);
    }
    .chip.warning {
      background: rgba(250,204,21,0.18);
      border-color: rgba(250,204,21,0.35);
      color: var(--warning);
    }
    .chip.accent {
      background: rgba(99,102,241,0.22);
      border-color: rgba(129,140,248,0.45);
      color: #c7d2fe;
    }
    .card-body {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .field-grid {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }
    .field.full {
      grid-column: 1 / -1;
    }
    .action-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: flex-end;
    }
    .rotation-info {
      font-size: 12px;
      color: var(--muted);
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(148,163,184,0.25);
      background: rgba(15,23,42,0.55);
    }
    .rotation-info.highlight {
      border-color: rgba(99,102,241,0.5);
      color: #c7d2fe;
    }
    .key-card.next-key {
      border-color: rgba(129,140,248,0.6);
      box-shadow: 0 18px 36px rgba(99,102,241,0.28);
    }
    .key-card.inactive {
      opacity: 0.72;
    }
    @media (max-width: 640px) {
      body {
        padding: 24px 12px 40px;
      }
      .panel {
        padding: 18px;
      }
      .action-row {
        justify-content: flex-start;
      }
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <header class="top">
      <div>
        <h1>Admin Control Center</h1>
        <p>Kelola koin, subscription user, dan rolling Freepik API key.</p>
      </div>
      <a class="back-link" href="index.php">&larr; Kembali ke aplikasi</a>
    </header>

    <div id="toast" class="toast"></div>

    <section class="grid">
      <article class="panel">
        <header>
          <h2>Manajemen User</h2>
          <span id="userCount">0 user</span>
        </header>

        <form id="addUserForm" class="form-grid">
          <div class="field">
            <label for="newUserName">Nama</label>
            <input id="newUserName" type="text" class="input" placeholder="Nama user" required>
          </div>
          <div class="field">
            <label for="newUserEmail">Email</label>
            <input id="newUserEmail" type="email" class="input" placeholder="user@example.com">
          </div>
          <div class="field">
            <label for="newUserCoins">Koin</label>
            <input id="newUserCoins" type="number" class="input" min="0" step="1" value="0">
          </div>
          <div class="field">
            <label for="newUserSubscription">Subscription</label>
            <input id="newUserSubscription" type="text" class="input" placeholder="free / pro / enterprise" value="free">
          </div>
          <div class="field" style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
            <button type="submit" class="btn primary">Tambah User</button>
          </div>
        </form>

        <div id="userList" class="list">
          <div class="empty">Belum ada user. Tambahkan user baru untuk mulai mengelola koin &amp; subscription.</div>
        </div>
      </article>

      <article class="panel">
        <header>
          <h2>Freepik API Keys</h2>
          <span id="keyMeta">Active 0 / 0</span>
        </header>

        <form id="addKeyForm" class="form-grid">
          <div class="field">
            <label for="newKeyLabel">Label</label>
            <input id="newKeyLabel" type="text" class="input" placeholder="Mis. Cluster A">
          </div>
          <div class="field full">
            <label for="newKeyValue">API Key</label>
            <textarea id="newKeyValue" class="input" rows="2" placeholder="Masukkan Freepik API key" required></textarea>
          </div>
          <div class="field" style="display:flex; align-items:center; gap:12px;">
            <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--muted);">
              <input type="checkbox" id="newKeyActive" checked style="accent-color:#6366f1;"> Aktif
            </label>
            <button type="submit" class="btn primary">Tambah API Key</button>
          </div>
        </form>

        <div id="rotationInfo" class="rotation-info">Tidak ada API key aktif. Tambahkan atau aktifkan minimal satu key.</div>

        <div id="keyList" class="list">
          <div class="empty">Belum ada API key tersimpan.</div>
        </div>
      </article>
    </section>
  </div>

  <script>
    (function() {
      const state = {
        users: [],
        apiKeys: [],
        meta: { rollingIndex: 0 },
        nextKey: null,
        activeKeyCount: 0
      };

      const toastEl = document.getElementById('toast');
      const userList = document.getElementById('userList');
      const userCount = document.getElementById('userCount');
      const addUserForm = document.getElementById('addUserForm');
      const newUserName = document.getElementById('newUserName');
      const newUserEmail = document.getElementById('newUserEmail');
      const newUserCoins = document.getElementById('newUserCoins');
      const newUserSubscription = document.getElementById('newUserSubscription');
      const keyList = document.getElementById('keyList');
      const keyMeta = document.getElementById('keyMeta');
      const rotationInfo = document.getElementById('rotationInfo');
      const addKeyForm = document.getElementById('addKeyForm');
      const newKeyLabel = document.getElementById('newKeyLabel');
      const newKeyValue = document.getElementById('newKeyValue');
      const newKeyActive = document.getElementById('newKeyActive');
      let toastTimer = null;

      function showToast(message, type = 'info') {
        if (!toastEl) return;
        toastEl.textContent = message;
        toastEl.className = 'toast show';
        if (type === 'success') {
          toastEl.classList.add('success');
        } else if (type === 'error') {
          toastEl.classList.add('error');
        }
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
          toastEl.classList.remove('show', 'success', 'error');
        }, 2600);
      }

      function maskKey(key) {
        if (!key) return '';
        const str = String(key);
        if (str.length <= 8) return str;
        return str.slice(0, 4) + '...' + str.slice(-4);
      }

      function formatDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
      }

      async function apiCall(action, payload = {}, method = 'POST') {
        const options = {
          method,
          headers: { 'Accept': 'application/json' }
        };
        if (method !== 'GET') {
          options.headers['Content-Type'] = 'application/json';
          options.body = JSON.stringify(payload || {});
        }
        const res = await fetch(`admin.php?api=${encodeURIComponent(action)}`, options);
        const text = await res.text();
        let json;
        try {
          json = JSON.parse(text);
        } catch (err) {
          throw new Error('Respons server tidak valid');
        }
        if (!json.ok) {
          throw new Error(json.error || 'Operasi gagal');
        }
        return json;
      }

      async function loadState() {
        try {
          const json = await apiCall('state', {}, 'GET');
          const data = json.data || {};
          state.users = Array.isArray(data.users) ? data.users : [];
          state.apiKeys = Array.isArray(data.apiKeys) ? data.apiKeys : [];
          state.meta = data.meta || { rollingIndex: 0 };
          state.nextKey = data.nextKey || null;
          state.activeKeyCount = data.activeKeyCount || 0;
          renderUsers();
          renderKeys();
        } catch (err) {
          showToast(err.message || 'Gagal memuat data', 'error');
        }
      }

      function renderUsers() {
        if (!userList) return;
        userList.innerHTML = '';
        const count = state.users.length;
        if (userCount) {
          userCount.textContent = `${count} user${count !== 1 ? 's' : ''}`;
        }
        if (!count) {
          const empty = document.createElement('div');
          empty.className = 'empty';
          empty.textContent = 'Belum ada user. Tambahkan user baru untuk mulai mengelola koin & subscription.';
          userList.appendChild(empty);
          return;
        }

        state.users.forEach(user => {
          const card = document.createElement('div');
          card.className = 'card user-card';
          card.dataset.id = user.id;

          card.innerHTML = `
            <div class="card-header">
              <div>
                <div class="card-title user-name"></div>
                <div class="card-meta user-meta"></div>
              </div>
              <div class="chip-row"></div>
            </div>
            <div class="card-body">
              <div class="field-grid">
                <div class="field">
                  <label>Nama</label>
                  <input type="text" class="input" data-field="name" placeholder="Nama user">
                </div>
                <div class="field">
                  <label>Email</label>
                  <input type="email" class="input" data-field="email" placeholder="user@example.com">
                </div>
                <div class="field">
                  <label>Koin</label>
                  <input type="number" class="input" data-field="coins" min="0" step="1">
                </div>
                <div class="field">
                  <label>Subscription</label>
                  <input type="text" class="input" data-field="subscription" placeholder="free / pro / enterprise">
                </div>
              </div>
              <div class="action-row">
                <button type="button" class="btn primary" data-action="save">Simpan</button>
                <button type="button" class="btn ghost" data-action="toggle-ban"></button>
                <button type="button" class="btn ghost" data-action="toggle-block"></button>
                <button type="button" class="btn ghost danger" data-action="delete">Hapus</button>
              </div>
            </div>
          `;

          const nameEl = card.querySelector('.user-name');
          const metaEl = card.querySelector('.user-meta');
          const chips = card.querySelector('.chip-row');
          const nameInput = card.querySelector('[data-field="name"]');
          const emailInput = card.querySelector('[data-field="email"]');
          const coinsInput = card.querySelector('[data-field="coins"]');
          const subscriptionInput = card.querySelector('[data-field="subscription"]');
          const banBtn = card.querySelector('[data-action="toggle-ban"]');
          const blockBtn = card.querySelector('[data-action="toggle-block"]');

          if (nameEl) nameEl.textContent = user.name || '(Tanpa nama)';
          if (metaEl) metaEl.textContent = `${user.email ? user.email : 'Tidak ada email'} · Dibuat ${formatDate(user.created_at)}`;

          if (chips) {
            if (user.banned) {
              chips.appendChild(makeChip('Banned', 'danger'));
            }
            if (user.blocked) {
              chips.appendChild(makeChip('Blocked', 'warning'));
            }
            if (!user.banned && !user.blocked) {
              chips.appendChild(makeChip('Active', 'success'));
            }
          }

          if (nameInput) nameInput.value = user.name || '';
          if (emailInput) emailInput.value = user.email || '';
          if (coinsInput) coinsInput.value = Number.isFinite(Number(user.coins)) ? Number(user.coins) : 0;
          if (subscriptionInput) subscriptionInput.value = user.subscription || 'free';

          if (banBtn) {
            banBtn.textContent = user.banned ? 'Unban' : 'Ban';
            if (!user.banned) {
              banBtn.classList.add('danger');
            } else {
              banBtn.classList.remove('danger');
            }
          }
          if (blockBtn) {
            blockBtn.textContent = user.blocked ? 'Unblock' : 'Block';
          }

          userList.appendChild(card);
        });
      }

      function renderKeys() {
        if (!keyList) return;
        keyList.innerHTML = '';
        const total = state.apiKeys.length;
        if (keyMeta) {
          keyMeta.textContent = `Active ${state.activeKeyCount} / ${total}`;
        }
        if (rotationInfo) {
          if (state.activeKeyCount && state.nextKey) {
            rotationInfo.textContent = `Next rotation: ${(state.nextKey.label || 'Tanpa label')} (${maskKey(state.nextKey.key)})`;
            rotationInfo.classList.add('highlight');
          } else {
            rotationInfo.textContent = 'Tidak ada API key aktif. Tambahkan atau aktifkan minimal satu key.';
            rotationInfo.classList.remove('highlight');
          }
        }

        if (!total) {
          const empty = document.createElement('div');
          empty.className = 'empty';
          empty.textContent = 'Belum ada API key tersimpan.';
          keyList.appendChild(empty);
          return;
        }

        state.apiKeys.forEach(key => {
          const card = document.createElement('div');
          card.className = 'card key-card';
          card.dataset.id = key.id;
          if (!key.active) {
            card.classList.add('inactive');
          }
          if (state.nextKey && state.nextKey.id && state.nextKey.id === key.id) {
            card.classList.add('next-key');
          }

          card.innerHTML = `
            <div class="card-header">
              <div>
                <div class="card-title key-label"></div>
                <div class="card-meta key-meta"></div>
              </div>
              <div class="chip-row"></div>
            </div>
            <div class="card-body">
              <div class="field-grid">
                <div class="field">
                  <label>Label</label>
                  <input type="text" class="input" data-field="label" placeholder="Mis. Cluster A">
                </div>
                <div class="field full">
                  <label>API Key</label>
                  <textarea class="input" rows="2" data-field="key" placeholder="Masukkan Freepik API key"></textarea>
                </div>
              </div>
              <div class="action-row">
                <button type="button" class="btn primary" data-action="save">Simpan</button>
                <button type="button" class="btn ghost" data-action="toggle-active"></button>
                <button type="button" class="btn ghost" data-action="set-primary">Set Next</button>
                <button type="button" class="btn ghost danger" data-action="delete">Hapus</button>
              </div>
            </div>
          `;

          const labelEl = card.querySelector('.key-label');
          const metaEl = card.querySelector('.key-meta');
          const chips = card.querySelector('.chip-row');
          const labelInput = card.querySelector('[data-field="label"]');
          const keyInput = card.querySelector('[data-field="key"]');
          const toggleBtn = card.querySelector('[data-action="toggle-active"]');
          const setPrimaryBtn = card.querySelector('[data-action="set-primary"]');

          if (labelEl) labelEl.textContent = key.label ? key.label : 'Tanpa label';
          if (metaEl) metaEl.textContent = `ID ${key.id} · Dibuat ${formatDate(key.created_at)}`;

          if (chips) {
            chips.appendChild(makeChip(key.active ? 'Active' : 'Inactive', key.active ? 'success' : 'warning'));
            if (state.nextKey && state.nextKey.id === key.id) {
              chips.appendChild(makeChip('Next', 'accent'));
            }
          }

          if (labelInput) labelInput.value = key.label || '';
          if (keyInput) keyInput.value = key.key || '';
          if (toggleBtn) {
            toggleBtn.textContent = key.active ? 'Nonaktifkan' : 'Aktifkan';
            if (!key.active) {
              toggleBtn.classList.add('danger');
            } else {
              toggleBtn.classList.remove('danger');
            }
          }
          if (setPrimaryBtn) {
            setPrimaryBtn.disabled = !key.active;
          }

          keyList.appendChild(card);
        });
      }

      function makeChip(text, tone) {
        const chip = document.createElement('span');
        chip.className = 'chip';
        if (tone === 'success') chip.classList.add('success');
        if (tone === 'danger') chip.classList.add('danger');
        if (tone === 'warning') chip.classList.add('warning');
        if (tone === 'accent') chip.classList.add('accent');
        chip.textContent = text;
        return chip;
      }

      addUserForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = {
          name: newUserName.value.trim(),
          email: newUserEmail.value.trim(),
          coins: Number(newUserCoins.value || 0),
          subscription: newUserSubscription.value.trim()
        };
        if (!payload.name) {
          showToast('Nama wajib diisi', 'error');
          return;
        }
        if (!Number.isFinite(payload.coins) || payload.coins < 0) {
          payload.coins = 0;
        }
        try {
          await apiCall('addUser', payload);
          showToast('User berhasil ditambahkan', 'success');
          addUserForm.reset();
          newUserCoins.value = 0;
          newUserSubscription.value = 'free';
          await loadState();
        } catch (err) {
          showToast(err.message || 'Gagal menambah user', 'error');
        }
      });

      userList.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        const action = target.dataset.action;
        if (!action) return;
        const card = target.closest('.user-card');
        if (!card) return;
        const id = card.dataset.id;
        if (!id) return;
        const current = state.users.find(user => user.id === id);
        if (!current) return;

        if (action === 'save') {
          const nameInput = card.querySelector('[data-field="name"]');
          const emailInput = card.querySelector('[data-field="email"]');
          const coinsInput = card.querySelector('[data-field="coins"]');
          const subscriptionInput = card.querySelector('[data-field="subscription"]');
          const payload = {
            id,
            name: nameInput ? nameInput.value.trim() : '',
            email: emailInput ? emailInput.value.trim() : '',
            coins: coinsInput ? Number(coinsInput.value || 0) : 0,
            subscription: subscriptionInput ? subscriptionInput.value.trim() : ''
          };
          if (!payload.name) payload.name = '';
          if (!Number.isFinite(payload.coins) || payload.coins < 0) payload.coins = 0;
          try {
            await apiCall('updateUser', payload);
            showToast('User diperbarui', 'success');
            await loadState();
          } catch (err) {
            showToast(err.message || 'Gagal memperbarui user', 'error');
          }
        }

        if (action === 'toggle-ban') {
          try {
            await apiCall('updateUser', { id, banned: !current.banned });
            showToast(current.banned ? 'User di-unban' : 'User di-ban', 'success');
            await loadState();
          } catch (err) {
            showToast(err.message || 'Gagal mengubah status ban', 'error');
          }
        }

        if (action === 'toggle-block') {
          try {
            await apiCall('updateUser', { id, blocked: !current.blocked });
            showToast(current.blocked ? 'User di-unblock' : 'User diblokir', 'success');
            await loadState();
          } catch (err) {
            showToast(err.message || 'Gagal mengubah status block', 'error');
          }
        }

        if (action === 'delete') {
          if (!confirm('Hapus user ini? Tindakan tidak dapat dibatalkan.')) return;
          try {
            await apiCall('deleteUser', { id });
            showToast('User dihapus', 'success');
            await loadState();
          } catch (err) {
            showToast(err.message || 'Gagal menghapus user', 'error');
          }
        }
      });

      addKeyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = {
          label: newKeyLabel.value.trim(),
          key: newKeyValue.value.trim(),
          active: newKeyActive.checked
        };
        if (!payload.key) {
          showToast('API key wajib diisi', 'error');
          return;
        }
        try {
          await apiCall('addApiKey', payload);
          showToast('API key ditambahkan', 'success');
          addKeyForm.reset();
          newKeyActive.checked = true;
          await loadState();
        } catch (err) {
          showToast(err.message || 'Gagal menambah API key', 'error');
        }
      });

      keyList.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        const action = target.dataset.action;
        if (!action) return;
        const card = target.closest('.key-card');
        if (!card) return;
        const id = card.dataset.id;
        if (!id) return;
        const current = state.apiKeys.find(item => item.id === id);
        if (!current) return;

        if (action === 'save') {
          const labelInput = card.querySelector('[data-field="label"]');
          const keyInput = card.querySelector('[data-field="key"]');
          const payload = {
            id,
            label: labelInput ? labelInput.value.trim() : '',
            key: keyInput ? keyInput.value.trim() : ''
          };
          if (!payload.key) {
            showToast('API key tidak boleh kosong', 'error');
            return;
          }
          try {
            await apiCall('updateApiKey', payload);
            showToast('API key diperbarui', 'success');
            await loadState();
          } catch (err) {
            showToast(err.message || 'Gagal memperbarui API key', 'error');
          }
        }

        if (action === 'toggle-active') {
          try {
            await apiCall('updateApiKey', { id, active: !current.active });
            showToast(!current.active ? 'API key diaktifkan' : 'API key dinonaktifkan', 'success');
            await loadState();
          } catch (err) {
            showToast(err.message || 'Gagal mengubah status API key', 'error');
          }
        }

        if (action === 'set-primary') {
          if (!current.active) {
            showToast('Aktifkan key sebelum menjadikannya prioritas', 'error');
            return;
          }
          try {
            await apiCall('setPrimaryKey', { id });
            showToast('Urutan rotasi diperbarui', 'success');
            await loadState();
          } catch (err) {
            showToast(err.message || 'Gagal memperbarui rotasi', 'error');
          }
        }

        if (action === 'delete') {
          if (!confirm('Hapus API key ini dari daftar?')) return;
          try {
            await apiCall('deleteApiKey', { id });
            showToast('API key dihapus', 'success');
            await loadState();
          } catch (err) {
            showToast(err.message || 'Gagal menghapus API key', 'error');
          }
        }
      });

      loadState();
    })();
  </script>
</body>
</html>
