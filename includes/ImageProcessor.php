<?php
require_once __DIR__ . '/../config.php';

/**
 * ImageProcessor — GD-based image conversion, resize, and compression
 */
class ImageProcessor
{
    private string $sourcePath;
    private string $mimeType;
    private GdImage $image;
    private int $originalWidth;
    private int $originalHeight;

    public function __construct(string $sourcePath)
    {
        $this->sourcePath = $sourcePath;
        $this->mimeType   = $this->detectMime($sourcePath);
        $this->image      = $this->loadImage();
        $this->originalWidth  = imagesx($this->image);
        $this->originalHeight = imagesy($this->image);
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Apply resize preset (key from RESIZE_PRESETS config)
     */
    public function resize(string $preset): self
    {
        $presets = RESIZE_PRESETS;
        if (!isset($presets[$preset]) || $preset === 'original') {
            return $this;
        }

        $targetW = $presets[$preset]['w'];
        $targetH = $presets[$preset]['h'];

        // Only downscale, never upscale
        if ($this->originalWidth <= $targetW && $this->originalHeight <= $targetH) {
            return $this;
        }

        [$newW, $newH] = $this->calculateFit($this->originalWidth, $this->originalHeight, $targetW, $targetH);

        $resized = imagecreatetruecolor($newW, $newH);
        $this->fillWhite($resized);
        imagecopyresampled($resized, $this->image, 0, 0, 0, 0, $newW, $newH, $this->originalWidth, $this->originalHeight);
        imagedestroy($this->image);
        $this->image = $resized;

        return $this;
    }

    /**
     * Save as JPEG to destination path at given quality (0-100)
     */
    public function saveAsJpeg(string $destPath, int $quality = DEFAULT_JPEG_QUALITY): bool
    {
        imageinterlace($this->image, 1); // Progressive JPEG
        return imagejpeg($this->image, $destPath, $quality);
    }

    /**
     * Get current image dimensions
     */
    public function getDimensions(): array
    {
        return [
            'width'  => imagesx($this->image),
            'height' => imagesy($this->image),
        ];
    }

    /**
     * Get original dimensions before any resize
     */
    public function getOriginalDimensions(): array
    {
        return ['width' => $this->originalWidth, 'height' => $this->originalHeight];
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function destroy(): void
    {
        if (isset($this->image)) {
            imagedestroy($this->image);
        }
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function detectMime(string $path): string
    {
        $info = getimagesize($path);
        if (!$info) throw new RuntimeException("Cannot read image: $path");
        return $info['mime'];
    }

    private function loadImage(): GdImage
    {
        return match ($this->mimeType) {
            'image/jpeg' => imagecreatefromjpeg($this->sourcePath) ?: throw new RuntimeException('Failed to load JPEG'),
            'image/png'  => $this->loadPng($this->sourcePath),
            'image/webp' => imagecreatefromwebp($this->sourcePath) ?: throw new RuntimeException('Failed to load WEBP'),
            default      => throw new RuntimeException("Unsupported mime type: {$this->mimeType}"),
        };
    }

    /**
     * Load PNG and flatten transparency onto white background
     */
    private function loadPng(string $path): GdImage
    {
        $src = imagecreatefrompng($path);
        if (!$src) throw new RuntimeException('Failed to load PNG');

        $w = imagesx($src);
        $h = imagesy($src);

        $flat = imagecreatetruecolor($w, $h);
        $this->fillWhite($flat);
        imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);

        return $flat;
    }

    /**
     * Fill GD image with white color
     */
    private function fillWhite(GdImage $img): void
    {
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
    }

    /**
     * Calculate new width/height maintaining aspect ratio to fit within maxW×maxH
     */
    private function calculateFit(int $srcW, int $srcH, int $maxW, int $maxH): array
    {
        $ratio = min($maxW / $srcW, $maxH / $srcH);
        return [max(1, (int) round($srcW * $ratio)), max(1, (int) round($srcH * $ratio))];
    }
}
