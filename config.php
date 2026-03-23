<?php
/**
 * RMS EXIF Optimizer — App Configuration
 */

define('APP_NAME', 'RMS Image Optimizer');
define('APP_VERSION', '1.0.0');

// ── File Upload ──────────────────────────────────────────────────────────────
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB in bytes
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// ── Image Processing ─────────────────────────────────────────────────────────
define('DEFAULT_JPEG_QUALITY', 82); // 70–100
define('MIN_JPEG_QUALITY', 60);
define('MAX_JPEG_QUALITY', 100);

define('RESIZE_PRESETS', [
    'original' => ['label' => 'Original size',  'w' => 0,    'h' => 0],
    '1280x720' => ['label' => '1280 × 720 (HD)', 'w' => 1280, 'h' => 720],
    '1200x900' => ['label' => '1200 × 900',      'w' => 1200, 'h' => 900],
    '1024x768' => ['label' => '1024 × 768',      'w' => 1024, 'h' => 768],
]);

// ── Paths ────────────────────────────────────────────────────────────────────
define('BASE_DIR',    __DIR__);
define('TMP_DIR',     __DIR__ . '/tmp');
define('OUTPUT_DIR',  __DIR__ . '/output');

// ── ExifTool ─────────────────────────────────────────────────────────────────
// Looks for exiftool.exe in project root, then in PATH
$exiftoolCandidates = [
    __DIR__ . '/exiftool.exe',   // Windows standalone in project root
    __DIR__ . '/exiftool',       // Linux/macOS
    'exiftool',                  // System PATH
];
$detectedExiftool = null;
foreach ($exiftoolCandidates as $candidate) {
    if (file_exists($candidate) || (strpos($candidate, '/') === false && strpos($candidate, '\\') === false)) {
        // Try to execute
        $test = shell_exec(escapeshellcmd($candidate) . ' -ver 2>&1');
        if ($test && preg_match('/^\d+\.\d+/', trim($test))) {
            $detectedExiftool = $candidate;
            break;
        }
    }
}
define('EXIFTOOL_PATH', $detectedExiftool);
define('EXIFTOOL_AVAILABLE', $detectedExiftool !== null);

// ── Session / Security ───────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// ── URL Base Path (auto-detected) ────────────────────────────────────────────
// Computes the web-root-relative path to THIS project directory.
// Works for:
//   http://rms-exif.test/          → BASE_PATH = ''
//   http://localhost/rms-exif/     → BASE_PATH = '/rms-exif'
//
// Strategy: compare __DIR__ (project root) against DOCUMENT_ROOT, then
// normalise separators. Falls back to '' if DOCUMENT_ROOT is not set.
if (!defined('BASE_PATH')) {
    $docRoot    = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projectDir = rtrim(str_replace('\\', '/', __DIR__), '/');

    if ($docRoot !== '' && strpos($projectDir, $docRoot) === 0) {
        $rel = substr($projectDir, strlen($docRoot));  // e.g. '' or '/rms-exif'
    } else {
        // Fallback: derive from SCRIPT_NAME using the top-level script
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        // SCRIPT_NAME for api/process.php gives /api — strip any /api sub-path
        // The project root is always one level up from /api, or at root level
        $scriptDir = preg_replace('#/api$#', '', $scriptDir);
        $rel = ($scriptDir === '/' || $scriptDir === '') ? '' : $scriptDir;
    }

    define('BASE_PATH', $rel);
}

define('OUTPUT_URL_BASE', BASE_PATH . '/output/');
define('TMP_URL_BASE',    BASE_PATH . '/tmp/');
