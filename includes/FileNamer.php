<?php
/**
 * FileNamer — SEO-friendly filename generator
 */
class FileNamer
{
    /**
     * Sanitize any string into an SEO-safe slug
     * Result: lowercase, hyphens, no accents, no special chars
     */
    public static function sanitize(string $input): string
    {
        // Convert to UTF-8
        $str = mb_strtolower(trim($input), 'UTF-8');

        // Transliterate accented characters
        $str = self::transliterate($str);

        // Replace non-alphanumeric characters with hyphens
        $str = preg_replace('/[^a-z0-9]+/', '-', $str);

        // Collapse multiple hyphens and trim
        $str = trim(preg_replace('/-+/', '-', $str), '-');

        return $str;
    }

    /**
     * Build a full SEO filename from components
     * e.g. "move-in-cleaning", "Houston", "TX", "hardwood-floors" → "move-in-cleaning-houston-tx-hardwood-floors.jpg"
     */
    public static function build(string $service, string $city, string $state, string $descriptor = ''): string
    {
        $parts = array_filter([
            self::sanitize($service),
            self::sanitize($city),
            self::sanitize($state),
            self::sanitize($descriptor),
        ]);

        return implode('-', $parts) . '.jpg';
    }

    /**
     * Validate a finished filename
     */
    public static function validate(string $filename): bool
    {
        // Must end in .jpg, only lowercase letters, digits, hyphens
        return (bool) preg_match('/^[a-z0-9][a-z0-9\-]*\.jpg$/', $filename);
    }

    /**
     * Make any uploaded filename safe (strip path traversal attempts etc.)
     */
    public static function safeName(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        return self::sanitize($base);
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    private static function transliterate(string $str): string
    {
        $map = [
            'á' => 'a','à' => 'a','ä' => 'a','â' => 'a','ã' => 'a','å' => 'a','æ' => 'ae',
            'é' => 'e','è' => 'e','ë' => 'e','ê' => 'e',
            'í' => 'i','ì' => 'i','ï' => 'i','î' => 'i',
            'ó' => 'o','ò' => 'o','ö' => 'o','ô' => 'o','õ' => 'o','ø' => 'o',
            'ú' => 'u','ù' => 'u','ü' => 'u','û' => 'u',
            'ý' => 'y','ÿ' => 'y',
            'ñ' => 'n','ç' => 'c','ß' => 'ss',
            'ð' => 'd','þ' => 'th','œ' => 'oe',
        ];
        return strtr($str, $map);
    }
}
