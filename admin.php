<?php
require_once __DIR__ . '/auth.php';

auth_session_start();

function load_admin_data($fresh = false) {
    return auth_storage_read($fresh);
}

function save_admin_data($data) {
    return auth_storage_write($data);
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

if (!auth_is_admin()) {
    $message = 'Akses khusus admin. Silakan login sebagai admin.';
    if (isset($_GET['api'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'status' => 403,
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(403);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>403 Dilarang</title></head><body style="font-family:system-ui;background:#020617;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;">'
        . '<div style="text-align:center;max-width:480px;line-height:1.5;">'
        . '<h1 style="font-size:28px;margin-bottom:16px;">403 - Dilarang</h1>'
        . '<p style="margin:0;opacity:0.8;">' . htmlspecialchars($message, ENT_QUOTES) . '</p>'
        . '</div></body></html>';
    exit;
}

if (isset($_GET['api'])) {
    $action = $_GET['api'];

    if (!auth_is_admin()) {
        respond_error('Forbidden', 403);
    }

    switch ($action) {
        case 'state':
            $data = load_admin_data();
            respond_ok([
                'users' => array_map('ensure_user_defaults', $data['users']),
                'accounts' => array_map('auth_account_admin_view', $data['accounts']),
                'apiKeys' => array_values($data['apiKeys']),
                'meta' => $data['meta'],
                'nextKey' => compute_next_key($data),
                'activeKeyCount' => count_active_keys($data),
                'platform' => auth_platform_admin_view($data['platform'] ?? null),
            ]);
            break;

        case 'addUser':
        case 'addAccount':
            $payload = read_payload();
            $errors = [];
            $account = auth_create_account_entry($payload, $errors);
            if (!$account) {
                $status = isset($errors['general']) && count($errors) === 1 ? 500 : 422;
                respond_json([
                    'ok' => false,
                    'status' => $status,
                    'error' => $errors,
                ], $status);
            }

            respond_ok(['account' => auth_account_admin_view($account)], 201);
            break;

        case 'updateUser':
        case 'updateAccount':
            $payload = read_payload();
            $id = $payload['id'] ?? '';
            if (!$id) {
                respond_error('ID akun wajib diisi', 422);
            }

            $errors = [];
            $account = auth_update_account_entry($id, $payload, $errors);
            if (!$account) {
                $status = isset($errors['general']) && count($errors) === 1 ? 500 : 422;
                respond_json([
                    'ok' => false,
                    'status' => $status,
                    'error' => $errors,
                ], $status);
            }

            respond_ok(['account' => auth_account_admin_view($account)]);
            break;

        case 'deleteUser':
        case 'deleteAccount':
            $payload = read_payload();
            $id = $payload['id'] ?? '';
            if (!$id) {
                respond_error('ID akun wajib diisi', 422);
            }

            $errors = [];
            if (!auth_delete_account_entry($id, $errors)) {
                $status = isset($errors['general']) && $errors['general'] === 'Akun tidak ditemukan.' ? 404 : 422;
                if (isset($errors['general']) && $errors['general'] === 'Tidak dapat menghapus akun admin.') {
                    $status = 403;
                } elseif (isset($errors['general']) && $errors['general'] === 'Gagal menghapus akun.') {
                    $status = 500;
                }
                respond_json([
                    'ok' => false,
                    'status' => $status,
                    'error' => $errors,
                ], $status);
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

        case 'setGenerator':
            $payload = read_payload();
            $key = isset($payload['key']) ? trim((string)$payload['key']) : '';
            if ($key === '') {
                respond_error('Key generator wajib diisi', 422);
            }
            $enabled = !empty($payload['enabled']);
            $platform = auth_platform_set_generator($key, $enabled);
            if (!$platform) {
                respond_error('Gagal memperbarui generator atau key tidak ditemukan', 404);
            }
            respond_ok(['platform' => auth_platform_admin_view($platform)]);
            break;

        case 'setMaintenance':
            $payload = read_payload();
            $active = !empty($payload['active']);
            $message = array_key_exists('message', $payload) ? (string)$payload['message'] : null;
            $platform = auth_platform_set_maintenance($active, $message);
            if (!$platform) {
                respond_error('Gagal memperbarui status maintenance', 500);
            }
            respond_ok(['platform' => auth_platform_admin_view($platform)]);
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
      color-scheme: dark;
      --bg: #020617;
      --panel: rgba(12,18,35,0.78);
      --panel-border: rgba(129,140,248,0.24);
      --panel-shadow: rgba(2,6,23,0.45);
      --page-background: radial-gradient(circle at top, rgba(79,70,229,0.32), transparent 55%),
                        radial-gradient(circle at bottom, rgba(14,165,233,0.18), transparent 52%),
                        #020617;
      --text: #e2e8f0;
      --muted: rgba(148,163,184,0.78);
      --accent: #818cf8;
      --danger: #f87171;
      --success: #34d399;
      --warning: #fbbf24;
      --panel-meta: rgba(203,213,225,0.75);
      --toast-bg: rgba(10,14,30,0.88);
      --toast-border: rgba(129,140,248,0.32);
      --toast-shadow: rgba(2,6,23,0.45);
      --back-link-bg: rgba(17,24,39,0.6);
      --back-link-border: rgba(129,140,248,0.28);
      --btn-bg: rgba(17,24,39,0.55);
      --btn-border: rgba(129,140,248,0.28);
      --btn-ghost-border: rgba(148,163,184,0.32);
      --btn-hover-shadow: rgba(2,6,23,0.45);
      --input-bg: rgba(8,12,24,0.7);
      --input-border: rgba(129,140,248,0.28);
      --input-focus-border: rgba(129,140,248,0.45);
      --input-focus-ring: rgba(129,140,248,0.2);
      --empty-border: rgba(129,140,248,0.3);
      --chip-bg: rgba(129,140,248,0.22);
      --chip-border: rgba(129,140,248,0.35);
      --chip-text: #c7d2fe;
      --chip-accent-text: #e0e7ff;
      --info-text: rgba(125,211,252,0.92);
      --chip-coins: rgba(125,211,252,0.95);
      --rotation-bg: rgba(15,23,42,0.55);
      --maintenance-bg: rgba(15,23,42,0.6);
      --generator-bg: rgba(13,20,38,0.7);
      --card-bg: rgba(13,19,36,0.82);
      --card-border: rgba(129,140,248,0.22);
      --card-shadow: rgba(2,6,23,0.55);
      --accent-soft: rgba(129,140,248,0.28);
      --accent-strong: rgba(129,140,248,0.5);
      --highlight-text: #c7d2fe;
    }
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: var(--page-background);
      color: var(--text);
      padding: 32px 16px 48px;
      transition: background 0.3s ease, color 0.3s ease;
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
      border: 1px solid var(--back-link-border);
      color: var(--text);
      text-decoration: none;
      background: var(--back-link-bg);
      transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
    }
    .back-link:hover {
      transform: translateY(-1px);
      border-color: var(--accent-strong);
    }
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 10px 16px;
      border-radius: 10px;
      background: var(--toast-bg);
      border: 1px solid var(--toast-border);
      color: var(--text);
      font-size: 13px;
      line-height: 1.4;
      max-width: 360px;
      white-space: pre-line;
      box-shadow: 0 10px 30px var(--toast-shadow);
      opacity: 0;
      transform: translateY(-10px);
      pointer-events: none;
      transition: opacity 0.2s ease, transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
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
      box-shadow: 0 25px 60px var(--panel-shadow);
      backdrop-filter: blur(18px);
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .panel header,
    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
    }
    .panel header h2,
    .panel-header h2 {
      margin: 0;
      font-size: 18px;
      font-weight: 600;
    }
    .panel-sub {
      font-size: 12px;
      color: var(--muted);
      display: block;
      margin-top: 4px;
    }
    .panel-meta {
      margin: 4px 0 0;
      font-size: 13px;
      color: var(--panel-meta);
      max-width: 420px;
      line-height: 1.5;
    }
    .form-grid {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      align-items: end;
    }
    .account-form .hint {
      color: var(--danger);
      font-weight: 600;
      margin-left: 4px;
    }
    .account-grid {
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .field label {
      display: block;
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 4px;
    }
    .field.toggles {
      display: flex;
      align-items: center;
      gap: 16px;
      grid-column: 1 / -1;
      padding: 4px 0 8px;
    }
    .toggle {
      font-size: 12px;
      color: var(--muted);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .input {
      width: 100%;
      border-radius: 10px;
      border: 1px solid var(--input-border);
      background: var(--input-bg);
      color: var(--text);
      padding: 10px 12px;
      font-size: 13px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
      resize: vertical;
    }
    .input:focus {
      outline: none;
      border-color: var(--input-focus-border);
      box-shadow: 0 0 0 3px var(--input-focus-ring);
    }
    .btn {
      border-radius: 999px;
      border: 1px solid var(--btn-border);
      padding: 8px 16px;
      font-size: 13px;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
      background: var(--btn-bg);
      color: var(--text);
    }
    .btn.primary {
      background: linear-gradient(120deg, rgba(99,102,241,0.8), rgba(59,130,246,0.75));
      border-color: rgba(99,102,241,0.5);
      box-shadow: 0 12px 24px rgba(59,130,246,0.35);
    }
    .btn.ghost {
      border-color: var(--btn-ghost-border);
      background: transparent;
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
      box-shadow: 0 12px 24px var(--btn-hover-shadow);
    }
    .btn.small {
      padding: 6px 12px;
      font-size: 12px;
    }
    .list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .empty {
      padding: 20px;
      border-radius: 14px;
      border: 1px dashed var(--empty-border);
      color: var(--muted);
      font-size: 13px;
      text-align: center;
    }
    .card {
      border-radius: 18px;
      border: 1px solid var(--card-border);
      background: var(--card-bg);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 14px;
      box-shadow: 0 18px 36px var(--card-shadow);
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }
    .account-card.is-admin {
      border-color: rgba(250,204,21,0.35);
      box-shadow: 0 0 0 1px rgba(250,204,21,0.25), 0 25px 60px rgba(250,204,21,0.08);
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
      background: var(--chip-bg);
      border: 1px solid var(--chip-border);
      color: var(--chip-text);
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
      background: var(--accent-soft);
      border-color: var(--accent-strong);
      color: var(--chip-accent-text);
    }
    .chip.info {
      background: rgba(59,130,246,0.18);
      border-color: rgba(59,130,246,0.35);
      color: var(--info-text);
    }
    .chip-admin {
      border-color: rgba(250,204,21,0.35);
      background: rgba(250,204,21,0.14);
      color: rgba(250,204,21,0.9);
    }
    .chip-coins strong {
      color: var(--chip-coins);
    }
    .card-body {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .chip-status {
      margin: 12px 0 4px;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
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
      border: 1px solid var(--btn-ghost-border);
      background: var(--rotation-bg);
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }
    .rotation-info.highlight {
      border-color: var(--accent-strong);
      background: var(--accent-soft);
      color: var(--highlight-text);
    }
    .key-card.next-key {
      border-color: rgba(129,140,248,0.6);
      box-shadow: 0 18px 36px rgba(99,102,241,0.28);
    }
    .key-card.inactive {
      opacity: 0.72;
    }
    .maintenance-form {
      display: flex;
      flex-direction: column;
      gap: 12px;
      padding: 12px;
      border-radius: 14px;
      border: 1px solid var(--btn-ghost-border);
      background: var(--maintenance-bg);
    }
    .maintenance-toggle {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      color: var(--muted);
    }
    .maintenance-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .maintenance-status {
      font-size: 12px;
      color: var(--muted);
    }
    .generator-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .generator-card {
      border-radius: 16px;
      border: 1px solid var(--btn-ghost-border);
      background: var(--generator-bg);
      padding: 14px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
    }
    .generator-card.offline {
      border-color: rgba(248,113,113,0.32);
      box-shadow: 0 18px 32px rgba(248,113,113,0.08);
    }
    .generator-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .generator-name {
      font-weight: 600;
      font-size: 14px;
    }
    .generator-desc {
      font-size: 12px;
      color: var(--muted);
      max-width: 420px;
    }
    .generator-meta {
      font-size: 11px;
      color: rgba(148,163,184,0.7);
    }
    .generator-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .generator-status-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      border-radius: 999px;
      padding: 4px 10px;
      background: rgba(34,197,94,0.16);
      color: #bbf7d0;
    }
    .generator-status-chip.off {
      background: rgba(248,113,113,0.18);
      color: #fecaca;
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
      <article class="panel panel-accounts">
        <header class="panel-header">
          <div>
            <h2>Manajemen Akun</h2>
            <span id="accountCount" class="panel-sub">0 akun</span>
          </div>
          <p class="panel-meta">Registrasi manual, atur password, coins, subscription, dan Freepik API key.</p>
        </header>

        <form id="addAccountForm" class="form-grid account-form">
          <div class="field">
            <label for="newAccountDisplay">Display Name</label>
            <input id="newAccountDisplay" type="text" class="input" placeholder="Nama tampilan">
          </div>
          <div class="field">
            <label for="newAccountUsername">Username <span class="hint">*</span></label>
            <input id="newAccountUsername" type="text" class="input" placeholder="username" required>
          </div>
          <div class="field">
            <label for="newAccountEmail">Email</label>
            <input id="newAccountEmail" type="email" class="input" placeholder="user@example.com">
          </div>
          <div class="field">
            <label for="newAccountPassword">Password <span class="hint">*</span></label>
            <input id="newAccountPassword" type="password" class="input" placeholder="Minimal 6 karakter" required>
          </div>
          <div class="field">
            <label for="newAccountSubscription">Subscription</label>
            <select id="newAccountSubscription" class="input">
              <option value="free">Free</option>
              <option value="pro" selected>Pro</option>
              <option value="enterprise">Enterprise</option>
            </select>
          </div>
          <div class="field">
            <label for="newAccountCoins">Koin</label>
            <input id="newAccountCoins" type="number" class="input" min="0" step="1" value="25">
          </div>
          <div class="field">
            <label for="newAccountRole">Role</label>
            <select id="newAccountRole" class="input">
              <option value="user" selected>User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="field full">
            <label for="newAccountApiKey">Freepik API Key</label>
            <input id="newAccountApiKey" type="text" class="input" placeholder="Opsional, masukkan API key">
          </div>
          <div class="field toggles">
            <label class="toggle"><input type="checkbox" id="newAccountBanned" style="accent-color:#f87171;"> Ban langsung</label>
            <label class="toggle"><input type="checkbox" id="newAccountBlocked" style="accent-color:#facc15;"> Blok akses</label>
          </div>
          <div class="field" style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
            <button type="submit" class="btn primary">Tambah Akun</button>
          </div>
        </form>

        <div id="accountList" class="list account-list">
          <div class="empty">Belum ada akun user. Tambahkan akun baru untuk mulai mengelola akses.</div>
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

      <article class="panel panel-platform">
        <header class="panel-header">
          <div>
            <h2>Platform & Maintenance</h2>
            <span class="panel-sub">Kelola maintenance website & akses generator</span>
          </div>
          <p class="panel-meta">Nonaktifkan generator tertentu untuk user saat maintenance, admin tetap bisa menguji.</p>
        </header>

        <form id="maintenanceForm" class="maintenance-form">
          <label class="maintenance-toggle" for="maintenanceActive">
            <input type="checkbox" id="maintenanceActive" style="accent-color:#6366f1;">
            Aktifkan mode maintenance website
          </label>
          <textarea id="maintenanceMessage" class="input" rows="2" placeholder="Pesan maintenance untuk user"></textarea>
          <div class="maintenance-actions">
            <button type="submit" class="btn primary small">Simpan Maintenance</button>
            <span id="maintenanceStatus" class="maintenance-status"></span>
          </div>
        </form>

        <div class="generator-list" id="generatorList">
          <div class="empty">Tidak ada generator terdaftar.</div>
        </div>
      </article>
    </section>
  </div>

  <script>
    (function() {
      const state = {
        accounts: [],
        apiKeys: [],
        meta: { rollingIndex: 0 },
        nextKey: null,
        activeKeyCount: 0,
        platform: {
          maintenance: { active: false, message: '', updated_at: null },
          generators: []
        }
      };

      const toastEl = document.getElementById('toast');
      const accountList = document.getElementById('accountList');
      const accountCount = document.getElementById('accountCount');
      const addAccountForm = document.getElementById('addAccountForm');
      const newAccountDisplay = document.getElementById('newAccountDisplay');
      const newAccountUsername = document.getElementById('newAccountUsername');
      const newAccountEmail = document.getElementById('newAccountEmail');
      const newAccountPassword = document.getElementById('newAccountPassword');
      const newAccountSubscription = document.getElementById('newAccountSubscription');
      const newAccountCoins = document.getElementById('newAccountCoins');
      const newAccountRole = document.getElementById('newAccountRole');
      const newAccountApiKey = document.getElementById('newAccountApiKey');
      const newAccountBanned = document.getElementById('newAccountBanned');
      const newAccountBlocked = document.getElementById('newAccountBlocked');
      const keyList = document.getElementById('keyList');
      const keyMeta = document.getElementById('keyMeta');
      const rotationInfo = document.getElementById('rotationInfo');
      const addKeyForm = document.getElementById('addKeyForm');
      const newKeyLabel = document.getElementById('newKeyLabel');
      const newKeyValue = document.getElementById('newKeyValue');
      const newKeyActive = document.getElementById('newKeyActive');
      const maintenanceForm = document.getElementById('maintenanceForm');
      const maintenanceToggle = document.getElementById('maintenanceActive');
      const maintenanceMessage = document.getElementById('maintenanceMessage');
      const maintenanceStatus = document.getElementById('maintenanceStatus');
      const generatorList = document.getElementById('generatorList');
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

      function formatErrorDetail(error) {
        if (!error) return '';
        if (typeof error === 'string') return error;
        if (Array.isArray(error)) {
          return error
            .map(item => formatErrorDetail(item))
            .filter(Boolean)
            .join('\n');
        }
        if (typeof error === 'object') {
          return Object.entries(error)
            .map(([key, value]) => {
              const prefix = key === 'general' ? '' : `${key}: `;
              const detail = formatErrorDetail(value);
              return `${prefix}${detail}`.trim();
            })
            .filter(Boolean)
            .join('\n');
        }
        return String(error);
      }

      function resolveErrorMessage(err, fallback = 'Terjadi kesalahan') {
        if (!err) return fallback;
        if (err.details) {
          const detail = formatErrorDetail(err.details);
          if (detail) return detail;
        }
        if (err.message) return err.message;
        if (err.status) return `${fallback} (HTTP ${err.status})`;
        return fallback;
      }

      function handleApiError(err, fallback = 'Operasi gagal') {
        console.error(err);
        showToast(resolveErrorMessage(err, fallback), 'error');
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

      function defaultPlatformState() {
        return {
          maintenance: { active: false, message: '', updated_at: null },
          generators: []
        };
      }

      function normalizePlatform(platform) {
        if (!platform || typeof platform !== 'object') {
          return defaultPlatformState();
        }
        const maintenance = platform.maintenance && typeof platform.maintenance === 'object'
          ? platform.maintenance
          : {};
        const normalized = {
          maintenance: {
            active: !!maintenance.active,
            message: maintenance.message ? String(maintenance.message) : '',
            updated_at: maintenance.updated_at || null,
          },
          generators: []
        };
        const items = Array.isArray(platform.generators)
          ? platform.generators
          : (platform.generators && typeof platform.generators === 'object'
              ? Object.values(platform.generators)
              : []);
        items
          .filter(item => item && typeof item === 'object')
          .forEach(item => {
            const key = item.key || item.id || '';
            normalized.generators.push({
              key,
              label: item.label || key || 'Generator',
              description: item.description || '',
              enabled: !!item.enabled,
              updated_at: item.updated_at || null,
            });
          });
        return normalized;
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
          const error = new Error('Respons server tidak valid');
          error.status = res.status;
          error.raw = text;
          throw error;
        }
        if (!res.ok || !json.ok) {
          const detail = json && json.error ? formatErrorDetail(json.error) : '';
          const message = detail || `Operasi gagal (HTTP ${res.status})`;
          const error = new Error(message);
          error.status = json && json.status ? json.status : res.status;
          if (json && Object.prototype.hasOwnProperty.call(json, 'error')) {
            error.details = json.error;
          }
          error.payload = json;
          throw error;
        }
        return json;
      }

      async function loadState() {
        try {
          const json = await apiCall('state', {}, 'GET');
          const data = json.data || {};
          state.accounts = Array.isArray(data.accounts) ? data.accounts : [];
          state.apiKeys = Array.isArray(data.apiKeys) ? data.apiKeys : [];
          state.meta = data.meta || { rollingIndex: 0 };
          state.nextKey = data.nextKey || null;
          state.activeKeyCount = data.activeKeyCount || 0;
          state.platform = normalizePlatform(data.platform);
          renderAccounts();
          renderKeys();
          renderPlatform();
        } catch (err) {
          handleApiError(err, 'Gagal memuat data');
        }
      }

      function renderAccounts() {
        if (!accountList) return;
        accountList.innerHTML = '';
        const accounts = Array.isArray(state.accounts) ? state.accounts : [];
        const count = accounts.length;
        if (accountCount) {
          accountCount.textContent = `${count} akun`;
        }
        if (!count) {
          const empty = document.createElement('div');
          empty.className = 'empty';
          empty.textContent = 'Belum ada akun user. Tambahkan akun baru untuk mulai mengelola akses.';
          accountList.appendChild(empty);
          return;
        }

        accounts.forEach(account => {
          if (!account || typeof account !== 'object') return;
          const card = document.createElement('div');
          card.className = 'card account-card';
          card.dataset.id = account.id;
          const isAdmin = (account.role || 'user') === 'admin';
          if (isAdmin) {
            card.classList.add('is-admin');
          }

          card.innerHTML = `
            <div class="card-header">
              <div>
                <div class="card-title account-name"></div>
                <div class="card-meta account-meta"></div>
              </div>
              <div class="chip-row">
                <span class="chip account-role"></span>
                <span class="chip chip-coins"><strong class="account-coins"></strong> Coins</span>
              </div>
            </div>
            <div class="card-body">
              <div class="field-grid account-grid">
                <div class="field">
                  <label>Display Name</label>
                  <input type="text" class="input" data-field="display_name" placeholder="Nama tampilan">
                </div>
                <div class="field">
                  <label>Username</label>
                  <input type="text" class="input" data-field="username" placeholder="username">
                </div>
                <div class="field">
                  <label>Email</label>
                  <input type="email" class="input" data-field="email" placeholder="user@example.com">
                </div>
                <div class="field">
                  <label>Subscription</label>
                  <input type="text" class="input" data-field="subscription" placeholder="free / pro / enterprise">
                </div>
                <div class="field">
                  <label>Koin</label>
                  <input type="number" class="input" data-field="coins" min="0" step="1">
                </div>
                <div class="field">
                  <label>Role</label>
                  <select class="input" data-field="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>
                <div class="field full">
                  <label>Freepik API Key</label>
                  <input type="text" class="input" data-field="freepik_api_key" placeholder="Opsional">
                </div>
                <div class="field">
                  <label>Theme</label>
                  <select class="input" data-field="theme">
                    <option value="dark">Dark</option>
                    <option value="light">Light</option>
                  </select>
                </div>
                <div class="field">
                  <label>Password Baru</label>
                  <input type="password" class="input" data-field="password" placeholder="Kosongkan jika tidak diubah">
                </div>
              </div>
              <div class="chip-row chip-status"></div>
              <div class="action-row">
                <button type="button" class="btn primary" data-action="save">Simpan</button>
                <button type="button" class="btn ghost" data-action="toggle-ban"></button>
                <button type="button" class="btn ghost" data-action="toggle-block"></button>
                <button type="button" class="btn ghost danger" data-action="delete">Hapus</button>
              </div>
            </div>
          `;

          const nameEl = card.querySelector('.account-name');
          const metaEl = card.querySelector('.account-meta');
          const roleChip = card.querySelector('.account-role');
          const coinsEl = card.querySelector('.account-coins');
          const statusRow = card.querySelector('.chip-status');
          const displayInput = card.querySelector('[data-field="display_name"]');
          const usernameInput = card.querySelector('[data-field="username"]');
          const emailInput = card.querySelector('[data-field="email"]');
          const subscriptionInput = card.querySelector('[data-field="subscription"]');
          const coinsInput = card.querySelector('[data-field="coins"]');
          const roleSelect = card.querySelector('[data-field="role"]');
          const keyInput = card.querySelector('[data-field="freepik_api_key"]');
          const themeSelect = card.querySelector('[data-field="theme"]');
          const passwordInput = card.querySelector('[data-field="password"]');
          const banBtn = card.querySelector('[data-action="toggle-ban"]');
          const blockBtn = card.querySelector('[data-action="toggle-block"]');
          const deleteBtn = card.querySelector('[data-action="delete"]');

          if (nameEl) {
            nameEl.textContent = account.display_name || account.username || '(Tanpa nama)';
          }
          if (metaEl) {
            const emailText = account.email && account.email !== '' ? account.email : 'Tidak ada email';
            const created = account.created_at ? `Dibuat ${formatDate(account.created_at)}` : '';
            const lastLogin = account.last_login_at ? `Login ${formatDate(account.last_login_at)}` : '';
            const pieces = [emailText, `@${account.username || 'unknown'}`, String(account.subscription || 'free').toUpperCase()];
            if (created) pieces.push(created);
            if (lastLogin) pieces.push(lastLogin);
            metaEl.textContent = pieces.join(' · ');
          }
          if (roleChip) {
            roleChip.textContent = isAdmin ? 'Admin' : 'User';
            if (isAdmin) {
              roleChip.classList.add('chip-admin');
            }
          }
          if (coinsEl) {
            coinsEl.textContent = Number.isFinite(Number(account.coins)) ? Number(account.coins) : 0;
          }
          if (statusRow) {
            statusRow.innerHTML = '';
            statusRow.appendChild(makeChip(account.is_banned ? 'Banned' : 'Active', account.is_banned ? 'danger' : 'success'));
            statusRow.appendChild(makeChip(account.is_blocked ? 'Blocked' : 'Live', account.is_blocked ? 'warning' : 'info'));
          }
          if (displayInput) displayInput.value = account.display_name || '';
          if (usernameInput) usernameInput.value = account.username || '';
          if (emailInput) emailInput.value = account.email || '';
          if (subscriptionInput) subscriptionInput.value = account.subscription || 'free';
          if (coinsInput) coinsInput.value = Number.isFinite(Number(account.coins)) ? Number(account.coins) : 0;
          if (roleSelect) roleSelect.value = isAdmin ? 'admin' : 'user';
          if (keyInput) keyInput.value = account.freepik_api_key || '';
          if (themeSelect) themeSelect.value = account.theme === 'light' ? 'light' : 'dark';
          if (passwordInput) passwordInput.value = '';
          if (banBtn) {
            banBtn.textContent = account.is_banned ? 'Unban' : 'Ban';
            banBtn.classList.toggle('danger', !account.is_banned);
          }
          if (blockBtn) {
            blockBtn.textContent = account.is_blocked ? 'Unblock' : 'Block';
          }
          if (deleteBtn && isAdmin) {
            deleteBtn.disabled = true;
            deleteBtn.title = 'Tidak dapat menghapus akun admin';
          }

          accountList.appendChild(card);
        });
      }

      function renderPlatform() {
        const platform = normalizePlatform(state.platform);
        state.platform = platform;

        if (maintenanceToggle) {
          maintenanceToggle.checked = !!platform.maintenance.active;
        }

        if (maintenanceMessage && document.activeElement !== maintenanceMessage) {
          maintenanceMessage.value = platform.maintenance.message || '';
        }

        if (maintenanceStatus) {
          maintenanceStatus.textContent = platform.maintenance.updated_at
            ? `Update ${formatDate(platform.maintenance.updated_at)}`
            : '';
        }

        if (!generatorList) {
          return;
        }

        generatorList.innerHTML = '';
        const items = platform.generators.slice().sort((a, b) => {
          return (a.label || '').localeCompare(b.label || '');
        });

        if (!items.length) {
          const empty = document.createElement('div');
          empty.className = 'empty';
          empty.textContent = 'Tidak ada generator terdaftar.';
          generatorList.appendChild(empty);
          return;
        }

        items.forEach(gen => {
          const card = document.createElement('div');
          card.className = 'generator-card';
          if (!gen.enabled) {
            card.classList.add('offline');
          }

          const info = document.createElement('div');
          info.className = 'generator-info';

          const name = document.createElement('div');
          name.className = 'generator-name';
          name.textContent = gen.label || gen.key || 'Generator';
          info.appendChild(name);

          if (gen.description) {
            const desc = document.createElement('div');
            desc.className = 'generator-desc';
            desc.textContent = gen.description;
            info.appendChild(desc);
          }

          const meta = document.createElement('div');
          meta.className = 'generator-meta';
          meta.textContent = gen.updated_at ? `Update ${formatDate(gen.updated_at)}` : 'Admin selalu punya akses';
          info.appendChild(meta);

          const statusChip = document.createElement('span');
          statusChip.className = 'generator-status-chip' + (gen.enabled ? '' : ' off');
          statusChip.textContent = gen.enabled ? 'Aktif untuk user' : 'Maintenance user';
          info.appendChild(statusChip);

          const actions = document.createElement('div');
          actions.className = 'generator-actions';

          const toggleBtn = document.createElement('button');
          toggleBtn.type = 'button';
          toggleBtn.className = 'btn small ' + (gen.enabled ? 'ghost' : 'primary');
          if (gen.enabled) {
            toggleBtn.classList.add('danger');
            toggleBtn.textContent = 'Matikan untuk user';
          } else {
            toggleBtn.textContent = 'Aktifkan kembali';
          }
          toggleBtn.addEventListener('click', () => {
            if (!gen.key) return;
            updateGenerator(gen.key, !gen.enabled);
          });

          actions.appendChild(toggleBtn);

          card.appendChild(info);
          card.appendChild(actions);
          generatorList.appendChild(card);
        });
      }

      async function updateGenerator(key, enabled) {
        if (!key) return;
        try {
          const json = await apiCall('setGenerator', { key, enabled });
          const data = json.data || {};
          if (data.platform) {
            state.platform = normalizePlatform(data.platform);
            renderPlatform();
          }
          showToast('Status generator diperbarui', 'success');
        } catch (err) {
          handleApiError(err, 'Gagal memperbarui generator');
        }
      }

      async function submitMaintenance() {
        const payload = {
          active: maintenanceToggle ? !!maintenanceToggle.checked : false,
          message: maintenanceMessage ? maintenanceMessage.value : ''
        };
        try {
          const json = await apiCall('setMaintenance', payload);
          const data = json.data || {};
          if (data.platform) {
            state.platform = normalizePlatform(data.platform);
            renderPlatform();
          }
          showToast('Status maintenance disimpan', 'success');
        } catch (err) {
          handleApiError(err, 'Gagal menyimpan maintenance');
        }
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
        if (tone === 'info') chip.classList.add('info');
        if (tone === 'accent') chip.classList.add('accent');
        chip.textContent = text;
        return chip;
      }

      if (addAccountForm) {
        addAccountForm.addEventListener('submit', async (event) => {
          event.preventDefault();
          const payload = {
            display_name: newAccountDisplay.value.trim(),
            username: newAccountUsername.value.trim(),
            email: newAccountEmail.value.trim(),
            password: newAccountPassword.value,
            subscription: newAccountSubscription.value,
            coins: Number(newAccountCoins.value || 0),
            role: newAccountRole.value,
            freepik_api_key: newAccountApiKey.value.trim(),
            is_banned: newAccountBanned.checked,
            is_blocked: newAccountBlocked.checked
          };

          if (!payload.username) {
            showToast('Username wajib diisi', 'error');
            return;
          }
          if (!payload.password || payload.password.length < 6) {
            showToast('Password minimal 6 karakter', 'error');
            return;
          }
          if (!Number.isFinite(payload.coins) || payload.coins < 0) {
            payload.coins = 0;
          }
          if (payload.freepik_api_key === '') {
            delete payload.freepik_api_key;
          }

          try {
            await apiCall('addAccount', payload);
            showToast('Akun berhasil ditambahkan', 'success');
            addAccountForm.reset();
            if (newAccountCoins) newAccountCoins.value = 25;
            if (newAccountSubscription) newAccountSubscription.value = 'pro';
            if (newAccountRole) newAccountRole.value = 'user';
            await loadState();
          } catch (err) {
            handleApiError(err, 'Gagal menambah akun');
          }
        });
      }

      if (accountList) accountList.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        const action = target.dataset.action;
        if (!action) return;
        const card = target.closest('.account-card');
        if (!card) return;
        const id = card.dataset.id;
        if (!id) return;
        const current = state.accounts.find(account => account.id === id);
        if (!current) return;

        if (action === 'save') {
          const displayInput = card.querySelector('[data-field="display_name"]');
          const usernameInput = card.querySelector('[data-field="username"]');
          const emailInput = card.querySelector('[data-field="email"]');
          const coinsInput = card.querySelector('[data-field="coins"]');
          const subscriptionInput = card.querySelector('[data-field="subscription"]');
          const roleSelect = card.querySelector('[data-field="role"]');
          const keyInput = card.querySelector('[data-field="freepik_api_key"]');
          const themeSelect = card.querySelector('[data-field="theme"]');
          const passwordInput = card.querySelector('[data-field="password"]');
          const payload = {
            id,
            display_name: displayInput ? displayInput.value.trim() : '',
            username: usernameInput ? usernameInput.value.trim() : '',
            email: emailInput ? emailInput.value.trim() : '',
            coins: coinsInput ? Number(coinsInput.value || 0) : 0,
            subscription: subscriptionInput ? subscriptionInput.value.trim() : '',
            role: roleSelect ? roleSelect.value : undefined,
            freepik_api_key: keyInput ? keyInput.value.trim() : '',
            theme: themeSelect ? themeSelect.value : undefined,
            password: passwordInput ? passwordInput.value : ''
          };
          if (!payload.username) payload.username = current.username;
          if (!Number.isFinite(payload.coins) || payload.coins < 0) payload.coins = 0;
          if (payload.freepik_api_key === '') {
            payload.freepik_api_key = null;
          }
          if (payload.password === '') {
            delete payload.password;
          }
          try {
            await apiCall('updateAccount', payload);
            showToast('Akun diperbarui', 'success');
            await loadState();
          } catch (err) {
            handleApiError(err, 'Gagal memperbarui akun');
          }
        }

        if (action === 'toggle-ban') {
          try {
            await apiCall('updateAccount', { id, is_banned: !current.is_banned });
            showToast(current.is_banned ? 'Akun di-unban' : 'Akun di-ban', 'success');
            await loadState();
          } catch (err) {
            handleApiError(err, 'Gagal mengubah status ban');
          }
        }

        if (action === 'toggle-block') {
          try {
            await apiCall('updateAccount', { id, is_blocked: !current.is_blocked });
            showToast(current.is_blocked ? 'Akun di-unblock' : 'Akun diblokir', 'success');
            await loadState();
          } catch (err) {
            handleApiError(err, 'Gagal mengubah status block');
          }
        }

        if (action === 'delete') {
          if (!confirm('Hapus akun ini? Tindakan tidak dapat dibatalkan.')) return;
          try {
            await apiCall('deleteAccount', { id });
            showToast('Akun dihapus', 'success');
            await loadState();
          } catch (err) {
            handleApiError(err, 'Gagal menghapus akun');
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
          handleApiError(err, 'Gagal menambah API key');
        }
      });

      if (maintenanceForm) {
        maintenanceForm.addEventListener('submit', async event => {
          event.preventDefault();
          await submitMaintenance();
        });
      }

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
            handleApiError(err, 'Gagal memperbarui API key');
          }
        }

        if (action === 'toggle-active') {
          try {
            await apiCall('updateApiKey', { id, active: !current.active });
            showToast(!current.active ? 'API key diaktifkan' : 'API key dinonaktifkan', 'success');
            await loadState();
          } catch (err) {
            handleApiError(err, 'Gagal mengubah status API key');
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
            handleApiError(err, 'Gagal memperbarui rotasi');
          }
        }

        if (action === 'delete') {
          if (!confirm('Hapus API key ini dari daftar?')) return;
          try {
            await apiCall('deleteApiKey', { id });
            showToast('API key dihapus', 'success');
            await loadState();
          } catch (err) {
            handleApiError(err, 'Gagal menghapus API key');
          }
        }
      });

      loadState();
    })();
  </script>
</body>
</html>
