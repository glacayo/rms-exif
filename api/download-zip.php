<?php
/**
 * api/download-zip.php — Bundle all session output files into a ZIP download
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ZipExporter.php';

session_start();

$files = ZipExporter::collectSessionFiles();

if (empty($files)) {
    http_response_code(404);
    exit(json_encode(['error' => 'No processed images found in session.']));
}

ZipExporter::streamDownload($files, 'rms-optimized-images.zip');
