<?php
// upload.php - simpan gambar dan kembalikan URL publik (https://domain/uploads/xxx.png)
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'File tidak diterima server']);
    exit;
}

// folder target
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// deteksi mime
$tmpPath = $_FILES['file']['tmp_name'];
$mime = function_exists('mime_content_type') ? mime_content_type($tmpPath) : ($_FILES['file']['type'] ?? '');

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'error' => 'Tipe file tidak didukung: ' . $mime]);
    exit;
}

$ext = $allowed[$mime];
$name = 'fm_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = $uploadDir . $name;

if (!move_uploaded_file($tmpPath, $target)) {
    echo json_encode(['success' => false, 'error' => 'Gagal menyimpan file di server']);
    exit;
}

// buat URL absolut
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$url    = $scheme . '://' . $host . '/uploads/' . $name;

echo json_encode([
    'success' => true,
    'url'     => $url,
]);
