<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/GeoHelper.php';

/**
 * ExifManager — Read EXIF data with PHP, write via ExifTool
 */
class ExifManager
{
    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Read EXIF data from a file. Returns normalised associative array.
     */
    public static function read(string $filePath): array
    {
        $data = [];

        // Use PHP's built-in EXIF reader (read-only)
        if (function_exists('exif_read_data')) {
            $raw = @exif_read_data($filePath, 'ANY_TAG', true);
            if ($raw) {
                $data['raw'] = $raw;

                // Flatten some common fields
                $ifd0 = $raw['IFD0'] ?? [];
                $exif  = $raw['EXIF']  ?? [];
                $gps   = $raw['GPS']   ?? [];

                $data['ImageDescription'] = $ifd0['ImageDescription'] ?? '';
                $data['Make']             = $ifd0['Make']             ?? '';
                $data['Model']            = $ifd0['Model']            ?? '';
                $data['DateTime']         = $ifd0['DateTime']         ?? ($exif['DateTimeOriginal'] ?? '');
                $data['Artist']           = $ifd0['Artist']           ?? '';
                $data['Copyright']        = $ifd0['Copyright']        ?? '';

                // GPS
                if (!empty($gps['GPSLatitude']) && !empty($gps['GPSLongitude'])) {
                    $data['GPSLatitude']    = self::dmsToDecimal($gps['GPSLatitude'],  $gps['GPSLatitudeRef']  ?? 'N');
                    $data['GPSLongitude']   = self::dmsToDecimal($gps['GPSLongitude'], $gps['GPSLongitudeRef'] ?? 'E');
                    $data['GPSLatitudeRef']  = $gps['GPSLatitudeRef']  ?? 'N';
                    $data['GPSLongitudeRef'] = $gps['GPSLongitudeRef'] ?? 'E';
                }
            }
        }

        // Also try ExifTool for richer data (XPKeywords, etc.)
        if (EXIFTOOL_AVAILABLE) {
            $toolData = self::readViaExifTool($filePath);
            // Merge — ExifTool is more reliable for Unicode fields
            foreach ($toolData as $k => $v) {
                if (!empty($v) && empty($data[$k])) {
                    $data[$k] = $v;
                }
            }
        }

        return $data;
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Write metadata to a JPEG file using ExifTool.
     * All values must already be sanitized.
     *
     * @param string   $filePath
     * @param array    $meta  ['ImageDescription', 'XPKeywords', 'Artist', 'Copyright']
     * @param float|null $lat
     * @param float|null $lng
     * @param string   $city
     * @param string   $state
     * @param string   $country
     * @return bool
     */
    public static function write(
        string $filePath,
        array  $meta,
        ?float $lat        = null,
        ?float $lng        = null,
        string $city       = '',
        string $state      = '',
        string $country    = '',
        string $sourcePath = ''
    ): bool {
        if (!EXIFTOOL_AVAILABLE) {
            return false;
        }

        $args = [
            '-overwrite_original',
            '-charset', 'filename=UTF8',
        ];

        // ── Preserve original tags ────────────────────────────────────────────
        if ($sourcePath !== '' && file_exists($sourcePath)) {
            // Copy ALL tags from source before applying overrides
            $args[] = '-tagsFromFile';
            $args[] = $sourcePath;
            $args[] = '-all:all';
        }

        // Text metadata
        if (!empty($meta['ImageDescription'])) {
            $args[] = '-ImageDescription=' . $meta['ImageDescription'];
            $args[] = '-XMP:Description='  . $meta['ImageDescription'];
        }
        if (!empty($meta['XPKeywords'])) {
            // ExifTool XPKeywords expects semicolon-separated on Windows EXIF
            $kwds = str_replace(',', ';', $meta['XPKeywords']);
            $args[] = '-XPKeywords=' . $kwds;
            // Also write as IPTC and XMP subject for wider compatibility
            foreach (array_map('trim', explode(',', $meta['XPKeywords'])) as $kw) {
                if ($kw !== '') {
                    $args[] = '-IPTC:Keywords+=' . $kw;
                    $args[] = '-XMP:Subject+='   . $kw;
                }
            }
        }
        if (!empty($meta['Artist']))    $args[] = '-Artist='    . $meta['Artist'];
        if (!empty($meta['Copyright'])) $args[] = '-Copyright=' . $meta['Copyright'];

        // GPS
        if ($lat !== null && $lng !== null) {
            $gpsArgs = GeoHelper::buildExifToolArgs($lat, $lng, $city, $state, $country);
            $args = array_merge($args, $gpsArgs);
        }

        return self::runExifTool($args, $filePath);
    }

    // ── Private: ExifTool Wrapper ─────────────────────────────────────────────

    private static function runExifTool(array $args, string $filePath): bool
    {
        $cmd = EXIFTOOL_PATH;

        // Build argument string safely
        $parts = [$cmd];
        foreach ($args as $arg) {
            $parts[] = $arg;
        }
        $parts[] = $filePath;

        // Build command using proc_open for proper argument handling
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($parts, $descriptors, $pipes);
        if (!is_resource($process)) return false;

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return $exitCode === 0;
    }

    private static function readViaExifTool(string $filePath): array
    {
        $cmd = [EXIFTOOL_PATH, '-json', '-ImageDescription', '-XPKeywords', '-Keywords', '-Subject',
                '-Artist', '-Copyright', '-GPSLatitude#', '-GPSLongitude#', '-GPSLatitudeRef', '-GPSLongitudeRef',
                '-City', '-State', '-Country', $filePath];

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) return [];

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $rows = json_decode($output, true);
        if (!$rows) return [];

        $row = $rows[0];
        $data = [];

        if (!empty($row['ImageDescription'])) $data['ImageDescription'] = $row['ImageDescription'];
        if (!empty($row['Artist']))           $data['Artist']           = $row['Artist'];
        if (!empty($row['Copyright']))        $data['Copyright']        = $row['Copyright'];
        if (!empty($row['City']))             $data['City']             = $row['City'];
        if (!empty($row['State']))            $data['State']            = $row['State'];
        if (!empty($row['Country']))          $data['Country']          = $row['Country'];

        // Keywords: may come as string or array
        $kwSources = ['XPKeywords', 'Keywords', 'Subject'];
        foreach ($kwSources as $src) {
            if (!empty($row[$src])) {
                $kw = is_array($row[$src]) ? implode(', ', $row[$src]) : $row[$src];
                $data['XPKeywords'] = $kw;
                break;
            }
        }

        // GPS (numeric format)
        if (isset($row['GPSLatitude']))  $data['GPSLatitude']  = (float) $row['GPSLatitude'];
        if (isset($row['GPSLongitude'])) $data['GPSLongitude'] = (float) $row['GPSLongitude'];
        if (isset($row['GPSLatitudeRef']))  $data['GPSLatitudeRef']  = $row['GPSLatitudeRef'];
        if (isset($row['GPSLongitudeRef'])) $data['GPSLongitudeRef'] = $row['GPSLongitudeRef'];

        return $data;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Convert EXIF DMS rational array to decimal degrees
     * Input format: ["40/1", "26/1", "4640/100"]
     */
    private static function dmsToDecimal(array $dms, string $ref): float
    {
        $degrees = self::rationalToFloat($dms[0] ?? '0/1');
        $minutes = self::rationalToFloat($dms[1] ?? '0/1');
        $seconds = self::rationalToFloat($dms[2] ?? '0/1');

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
        return (strtoupper($ref) === 'S' || strtoupper($ref) === 'W') ? -$decimal : $decimal;
    }

    private static function rationalToFloat(string $rational): float
    {
        if (strpos($rational, '/') !== false) {
            [$num, $den] = explode('/', $rational, 2);
            return $den != 0 ? (float) $num / (float) $den : 0.0;
        }
        return (float) $rational;
    }
}
