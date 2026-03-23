<?php
/**
 * api/upload.php
 * Handles image upload, validation, and returns initial EXIF data
 */
ob_start(); // Buffer output — prevents PHP notices from corrupting JSON
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ExifManager.php';

session_start();

try {
    // ── Validate request ─────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('Method not allowed', 405);
    }

    if (empty($_FILES['images'])) {
        throw new InvalidArgumentException('No files uploaded');
    }

    // Normalise $_FILES array for multiple uploads
    $files = normaliseFiles($_FILES['images']);
    $results = [];

    foreach ($files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $results[] = ['error' => uploadErrorMessage($file['error']), 'name' => $file['name']];
            continue;
        }

        // ── Size check ────────────────────────────────────────────────────────
        if ($file['size'] > MAX_FILE_SIZE) {
            $results[] = ['error' => 'File exceeds ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB limit', 'name' => $file['name']];
            continue;
        }

        // ── MIME + extension whitelist ────────────────────────────────────────
        $realMime = mime_content_type($file['tmp_name']);
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($realMime, ALLOWED_MIME_TYPES, true) || !in_array($ext, ALLOWED_EXTENSIONS, true)) {
            $results[] = ['error' => 'File type not allowed. Accepted: JPG, PNG, WEBP', 'name' => $file['name']];
            continue;
        }

        // ── Move to TMP ───────────────────────────────────────────────────────
        $tempKey  = bin2hex(random_bytes(16)); // unique session key
        $tempName = $tempKey . '.tmp';
        $tempPath = TMP_DIR . '/' . $tempName;

        if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
            $results[] = ['error' => 'Failed to store uploaded file', 'name' => $file['name']];
            continue;
        }

        // ── Read EXIF ─────────────────────────────────────────────────────────
        $exif = ExifManager::read($tempPath);

        // Store in session
        if (!isset($_SESSION['uploads'])) $_SESSION['uploads'] = [];
        $_SESSION['uploads'][$tempKey] = [
            'temp_path'  => $tempPath,
            'orig_name'  => $file['name'],
            'orig_size'  => $file['size'],
            'mime'       => $realMime,
            'uploaded_at' => time(),
        ];

        // ── Image dimensions ──────────────────────────────────────────────────
        [$w, $h] = getimagesize($tempPath);

        $results[] = [
            'success'      => true,
            'temp_key'     => $tempKey,
            'original_name' => $file['name'],
            'size'         => $file['size'],
            'size_human'   => humanFileSize($file['size']),
            'width'        => $w,
            'height'       => $h,
            'mime'         => $realMime,
            'exif'         => [
                'ImageDescription' => $exif['ImageDescription'] ?? '',
                'XPKeywords'       => $exif['XPKeywords']       ?? '',
                'Artist'           => $exif['Artist']           ?? '',
                'Copyright'        => $exif['Copyright']        ?? '',
                'DateTime'         => $exif['DateTime']         ?? '',
                'Make'             => $exif['Make']             ?? '',
                'Model'            => $exif['Model']            ?? '',
                'GPSLatitude'      => $exif['GPSLatitude']      ?? null,
                'GPSLongitude'     => $exif['GPSLongitude']     ?? null,
                'City'             => $exif['City']             ?? '',
                'State'            => $exif['State']            ?? '',
                'Country'          => $exif['Country']          ?? '',
            ],
        ];
    }

    ob_end_clean();
    echo json_encode(['uploads' => $results]);

} catch (InvalidArgumentException $e) {
    ob_end_clean();
    $code = $e->getCode() ?: 400;
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function normaliseFiles(array $files): array
{
    $result = [];
    if (is_array($files['name'])) {
        foreach ($files['name'] as $i => $name) {
            $result[] = [
                'name'     => $name,
                'tmp_name' => $files['tmp_name'][$i],
                'size'     => $files['size'][$i],
                'error'    => $files['error'][$i],
            ];
        }
    } else {
        $result[] = $files;
    }
    return $result;
}

function uploadErrorMessage(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large',
        UPLOAD_ERR_PARTIAL  => 'Upload was partial',
        UPLOAD_ERR_NO_FILE  => 'No file uploaded',
        default             => 'Upload error (code ' . $code . ')',
    };
}

function humanFileSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1)    . ' KB';
    return $bytes . ' B';
}
