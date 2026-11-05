<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reduce una imagen subida y la guarda en el disco uploads con GD (sin binarios externos: hosting compartido).
 * WebP cuando el GD del servidor lo soporta; si no, JPEG (o PNG si trae transparencia).
 */
class WebpImage
{
    /** Permite forzar el respaldo sin WebP en pruebas. */
    public static ?bool $webp = null;

    public static function store(UploadedFile $file, string $dir, int $maxSide = 1600, int $quality = 85, string $field = 'images'): string
    {
        return self::storeContent((string) file_get_contents($file->getRealPath()), $dir, $maxSide, $quality, $field, $file);
    }

    public static function storeContent(string $raw, string $dir, int $maxSide = 1600, int $quality = 85, string $field = 'images', ?UploadedFile $file = null): string
    {
        $image = $raw !== '' ? @imagecreatefromstring($raw) : false;
        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([$field => $file ? "No se pudo leer «{$file->getClientOriginalName()}». Sube una foto JPG, PNG o WebP." : 'No se pudo leer la imagen descargada. Se requiere una foto JPG, PNG o WebP.']);
        }
        $image = self::orient($image, $file, $raw);
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $maxSide / max($w, $h));
        $out = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        imagedestroy($image);

        [$bytes, $ext] = self::encode($out, $quality);
        imagedestroy($out);
        $path = trim($dir, '/').'/'.Str::uuid().'.'.$ext;
        Storage::disk('uploads')->put($path, $bytes);

        return $path;
    }

    /** @return array{0: string, 1: string} */
    private static function encode(GdImage $img, int $quality): array
    {
        ob_start();
        if ((self::$webp ?? function_exists('imagewebp')) && @imagewebp($img, null, $quality)) {
            return [(string) ob_get_clean(), 'webp'];
        }
        ob_clean();
        if (self::hasAlpha($img)) {
            imagepng($img, null, 8);

            return [(string) ob_get_clean(), 'png'];
        }
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imageinterlace($flat, true);
        imagejpeg($flat, null, $quality);
        imagedestroy($flat);

        return [(string) ob_get_clean(), 'jpg'];
    }

    /** Muestrea una rejilla de píxeles: suficiente para saber si la foto usa transparencia. */
    private static function hasAlpha(GdImage $img): bool
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, (int) (min($w, $h) / 24));
        for ($x = 0; $x < $w; $x += $step) {
            for ($y = 0; $y < $h; $y += $step) {
                if ((imagecolorat($img, $x, $y) >> 24) & 0x7F) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Las fotos de celular traen la rotación en EXIF; GD no la aplica sola. */
    private static function orient(GdImage $img, ?UploadedFile $file, string $raw): GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($raw, "\xFF\xD8")) {
            return $img;
        }
        $stream = null;
        if (! $file) {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $raw);
            rewind($stream);
        }
        $orientation = @exif_read_data($file ? $file->getRealPath() : $stream)['Orientation'] ?? 1;
        if (is_resource($stream)) {
            fclose($stream);
        }
        $angle = match ((int) $orientation) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };
        if ($angle === 0) {
            return $img;
        }
        $rotated = imagerotate($img, $angle, 0);
        if (! $rotated instanceof GdImage) {
            return $img;
        }
        imagedestroy($img);

        return $rotated;
    }
}
