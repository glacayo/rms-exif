<?php
/**
 * api/download.php — Stream a single processed file as attachment download
 */
require_once __DIR__ . '/../config.php';

session_start();

$tempKey = preg_replace('/[^a-f0-9]/', '', $_GET['key'] ?? '');

if (empty($tempKey) || !isset($_SESSION['output_files'][$tempKey])) {
    http_response_code(404);
    exit('File not found or session expired.');
}

$entry   = $_SESSION['output_files'][$tempKey];
$absPath = $entry['abs_path'];
$seoName = $entry['seo_name'];

if (!file_exists($absPath) || !is_readable($absPath)) {
    http_response_code(404);
    exit('File not found on disk.');
}

// Security: ensure file is within output directory
$realOutputDir = realpath(OUTPUT_DIR);
$realFile      = realpath($absPath);
if (!$realFile || strpos($realFile, $realOutputDir) !== 0) {
    http_response_code(403);
    exit('Access denied.');
}

header('Content-Type: image/jpeg');
header('Content-Disposition: attachment; filename="' . addslashes($seoName) . '"');
header('Content-Length: ' . filesize($absPath));
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($absPath);
exit;
