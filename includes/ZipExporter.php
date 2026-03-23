<?php
require_once __DIR__ . '/../config.php';

/**
 * ZipExporter — bundle multiple output files into a downloadable ZIP
 */
class ZipExporter
{
    /**
     * Build a ZIP archive in memory and stream it as a download.
     *
     * @param array  $files  Associative ['filename.jpg' => '/absolute/path/to/file.jpg']
     * @param string $zipName  Name of the downloaded ZIP file
     */
    public static function streamDownload(array $files, string $zipName = 'optimized-images.zip'): void
    {
        $tmpZip = tempnam(TMP_DIR, 'zip_') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not create ZIP archive']);
            return;
        }

        foreach ($files as $archiveName => $realPath) {
            if (file_exists($realPath) && is_readable($realPath)) {
                $zip->addFile($realPath, $archiveName);
            }
        }

        $zip->close();

        if (!file_exists($tmpZip)) {
            http_response_code(500);
            echo json_encode(['error' => 'ZIP file not created']);
            return;
        }

        // Stream the download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . addslashes($zipName) . '"');
        header('Content-Length: ' . filesize($tmpZip));
        header('Cache-Control: no-store, no-cache');
        header('Pragma: no-cache');

        readfile($tmpZip);
        @unlink($tmpZip);
        exit;
    }

    /**
     * Collect session output files from $_SESSION['output_files']
     * Returns ['seo-filename.jpg' => '/abs/path/to/file']
     */
    public static function collectSessionFiles(): array
    {
        $result = [];
        $files  = $_SESSION['output_files'] ?? [];

        foreach ($files as $entry) {
            $realPath = $entry['abs_path'] ?? '';
            $seoName  = $entry['seo_name'] ?? basename($realPath);
            if (file_exists($realPath)) {
                $result[$seoName] = $realPath;
            }
        }

        return $result;
    }
}
