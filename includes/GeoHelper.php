<?php
/**
 * GeoHelper — GPS coordinate conversion utilities
 */
class GeoHelper
{
    /**
     * Convert decimal degrees to degrees/minutes/seconds array
     * Returns ['deg' => int, 'min' => int, 'sec' => float]
     */
    public static function decimalToDMS(float $decimal): array
    {
        $absolute = abs($decimal);
        $deg = (int) $absolute;
        $minFloat = ($absolute - $deg) * 60;
        $min = (int) $minFloat;
        $sec = ($minFloat - $min) * 60;

        return ['deg' => $deg, 'min' => $min, 'sec' => round($sec, 4)];
    }

    /**
     * Format decimal coordinate to ExifTool-compatible string: "deg deg min min sec sec"
     * e.g. 29.7604° → "29 0 45 0 37.44 0"  (rational notation)
     */
    public static function toExifToolFormat(float $decimal): string
    {
        $dms = self::decimalToDMS($decimal);
        // ExifTool accepts "deg min sec" as rational fractions or floats
        return sprintf('%d %d %.6f', $dms['deg'], $dms['min'], $dms['sec']);
    }

    /**
     * Build ExifTool GPS argument array for a lat/lng pair
     */
    public static function buildExifToolArgs(float $lat, float $lng, string $city = '', string $state = '', string $country = ''): array
    {
        $latRef = $lat >= 0 ? 'N' : 'S';
        $lngRef = $lng >= 0 ? 'E' : 'W';

        $args = [
            '-GPSLatitude='    . self::toExifToolFormat($lat),
            '-GPSLatitudeRef=' . $latRef,
            '-GPSLongitude='   . self::toExifToolFormat($lng),
            '-GPSLongitudeRef=' . $lngRef,
        ];

        if (!empty($city))    $args[] = '-XMP:City='        . $city;
        if (!empty($state))   $args[] = '-XMP:State='       . $state;
        if (!empty($country)) $args[] = '-XMP:Country='     . $country;

        return $args;
    }

    /**
     * Validate that a coordinate string is a valid decimal degree
     */
    public static function validateCoordinate(string $value, string $type = 'lat'): bool
    {
        if (!is_numeric($value)) return false;
        $float = (float) $value;
        if ($type === 'lat') return $float >= -90  && $float <= 90;
        if ($type === 'lng') return $float >= -180 && $float <= 180;
        return false;
    }

    /**
     * Human-readable DMS string for UI display
     */
    public static function formatDMSDisplay(float $decimal, string $posLabel, string $negLabel): string
    {
        $dms = self::decimalToDMS($decimal);
        $dir = $decimal >= 0 ? $posLabel : $negLabel;
        return sprintf('%d°%d\'%.2f"%s', $dms['deg'], $dms['min'], $dms['sec'], $dir);
    }
}
