<?php
/**
 * api/process.php
 * Accepts form data + temp_key, processes image, writes EXIF, returns comparison data
 */
ob_start(); // Buffer output — prevents PHP notices from corrupting JSON
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ImageProcessor.php';
require_once __DIR__ . '/../includes/ExifManager.php';
require_once __DIR__ . '/../includes/FileNamer.php';
require_once __DIR__ . '/../includes/GeoHelper.php';

session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('Method not allowed', 405);
    }

    $input    = json_decode(file_get_contents('php://input'), true);
    $tempKey  = sanitizeString($input['temp_key'] ?? '');
    $quality  = clampInt((int)($input['quality'] ?? DEFAULT_JPEG_QUALITY), MIN_JPEG_QUALITY, MAX_JPEG_QUALITY);
    $preset   = sanitizeString($input['resize_preset'] ?? 'original');
    $seoName  = sanitizeString($input['seo_filename'] ?? '');

    // EXIF metadata
    $metaDesc = mb_substr(strip_tags($input['description'] ?? ''), 0, 255);
    $metaKw   = sanitizeString($input['keywords'] ?? '');
    $artist   = sanitizeString($input['artist']   ?? '');
    $copyright = sanitizeString($input['copyright'] ?? '');

    // GPS
    $lat     = isset($input['latitude'])  && $input['latitude']  !== '' ? (float) $input['latitude']  : null;
    $lng     = isset($input['longitude']) && $input['longitude'] !== '' ? (float) $input['longitude'] : null;
    $city    = sanitizeString($input['city']    ?? '');
    $state   = sanitizeString($input['state']   ?? '');
    $country = sanitizeString($input['country'] ?? '');

    // ── Validate session key ──────────────────────────────────────────────────
    if (empty($tempKey) || !isset($_SESSION['uploads'][$tempKey])) {
        throw new InvalidArgumentException('Invalid or expired session. Please re-upload.');
    }

    $upload   = $_SESSION['uploads'][$tempKey];
    $tempPath = $upload['temp_path'];

    if (!file_exists($tempPath)) {
        throw new RuntimeException('Temporary file not found. Please re-upload.');
    }

    // ── Validate preset ───────────────────────────────────────────────────────
    if (!array_key_exists($preset, RESIZE_PRESETS)) {
        $preset = 'original';
    }

    // ── Validate GPS if provided ──────────────────────────────────────────────
    if ($lat !== null && !GeoHelper::validateCoordinate((string)$lat, 'lat')) {
        throw new InvalidArgumentException('Invalid latitude value.');
    }
    if ($lng !== null && !GeoHelper::validateCoordinate((string)$lng, 'lng')) {
        throw new InvalidArgumentException('Invalid longitude value.');
    }

    // ── Build output filename ─────────────────────────────────────────────────
    if (empty($seoName) || !FileNamer::validate($seoName)) {
        // Auto-generate from original filename as fallback
        $seoName = FileNamer::safeName($upload['orig_name']) . '.jpg';
    }

    // Ensure uniqueness within this session
    $outputPath  = OUTPUT_DIR . '/' . $seoName;
    if (file_exists($outputPath)) {
        $base    = pathinfo($seoName, PATHINFO_FILENAME);
        $seoName = $base . '-' . substr($tempKey, 0, 6) . '.jpg';
        $outputPath = OUTPUT_DIR . '/' . $seoName;
    }

    // ── Process image ─────────────────────────────────────────────────────────
    $origSize = filesize($tempPath);
    [$origW, $origH] = getimagesize($tempPath);

    $processor = new ImageProcessor($tempPath);
    $processor->resize($preset);
    $saved = $processor->saveAsJpeg($outputPath, $quality);
    $processor->destroy();

    if (!$saved || !file_exists($outputPath)) {
        throw new RuntimeException('Image processing failed.');
    }

    // ── Write EXIF metadata ───────────────────────────────────────────────────
    if (EXIFTOOL_AVAILABLE) {
        ExifManager::write(
            $outputPath,
            [
                'ImageDescription' => $metaDesc,
                'XPKeywords'       => $metaKw,
                'Artist'           => $artist,
                'Copyright'        => $copyright,
            ],
            $lat,
            $lng,
            $city,
            $state,
            $country,
            $tempPath
        );
    }

    // ── Gather comparison data ────────────────────────────────────────────────
    $newSize = filesize($outputPath);
    [$newW, $newH] = getimagesize($outputPath);
    $savings = $origSize > 0 ? round((1 - $newSize / $origSize) * 100, 1) : 0;

    // Read final EXIF for preview
    $finalExif = ExifManager::read($outputPath);

    // DMS display strings
    $latDMS = ($lat !== null) ? GeoHelper::formatDMSDisplay($lat, 'N', 'S') : '';
    $lngDMS = ($lng !== null) ? GeoHelper::formatDMSDisplay($lng, 'E', 'W') : '';

    // Store in session for download
    if (!isset($_SESSION['output_files'])) $_SESSION['output_files'] = [];
    $_SESSION['output_files'][$tempKey] = [
        'abs_path' => $outputPath,
        'seo_name' => $seoName,
    ];

    ob_end_clean(); // Discard any buffered notices/warnings
    echo json_encode([
        'success'         => true,
        'seo_filename'    => $seoName,
        'original_size'   => $origSize,
        'optimized_size'  => $newSize,
        'original_size_human'   => humanFileSize($origSize),
        'optimized_size_human'  => humanFileSize($newSize),
        'savings_percent' => $savings,
        'original_dimensions'   => ['w' => $origW, 'h' => $origH],
        'optimized_dimensions'  => ['w' => $newW,  'h' => $newH],
        'preview_url'     => OUTPUT_URL_BASE . rawurlencode($seoName),
        'exif_summary'    => [
            'ImageDescription' => $metaDesc,
            'Keywords'         => $metaKw,
            'Artist'           => $artist,
            'GPS'              => $lat !== null ? "$latDMS, $lngDMS" : '',
            'City'             => $city,
            'State'            => $state,
            'Country'          => $country,
            'ExifToolUsed'     => EXIFTOOL_AVAILABLE,
        ],
        'temp_key'        => $tempKey,
    ]);

} catch (InvalidArgumentException $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function sanitizeString(mixed $v): string
{
    return htmlspecialchars(strip_tags((string)($v ?? '')), ENT_QUOTES, 'UTF-8');
}

function clampInt(int $v, int $min, int $max): int
{
    return max($min, min($max, $v));
}

function humanFileSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1)    . ' KB';
    return $bytes . ' B';
}
