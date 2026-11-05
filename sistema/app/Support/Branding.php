<?php

namespace App\Support;

use App\Models\Setting;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class Branding
{
    public static function siteLogoUrl(): string
    {
        return self::url(self::stored('site'), 'img/ats-computer.png');
    }

    public static function pdfLogoPath(): string
    {
        $path = self::stored('pdf');

        return $path ? Storage::disk('uploads')->path($path) : public_path('img/ats-computer.png');
    }

    public static function iconPath(int $size): string
    {
        $path = self::iconFile($size);

        return $path ? Storage::disk('uploads')->path($path) : public_path($size === 32 ? 'img/favicon.png' : 'img/ats-emblema.png');
    }

    public static function iconUrl(int $size = 512): string
    {
        return self::url(self::iconFile($size), $size === 32 ? 'img/favicon.png' : 'img/ats-emblema.png');
    }

    public static function shared(): array
    {
        return ['siteLogo' => self::siteLogoUrl(), 'icon' => self::iconUrl(), 'favicon' => self::iconUrl(32), 'appleTouchIcon' => self::iconUrl(180), 'customSiteLogo' => self::stored('site') !== null];
    }

    public static function previews(): array
    {
        return [
            'site' => ['url' => self::siteLogoUrl(), 'custom' => self::stored('site') !== null],
            'pdf' => ['url' => self::url(self::stored('pdf'), 'img/ats-computer.png'), 'custom' => self::stored('pdf') !== null],
            'icon' => ['url' => self::iconUrl(), 'custom' => self::stored('icon') !== null],
        ];
    }

    private static function stored(string $type): ?string
    {
        $path = setting('logo_'.$type);

        return is_string($path) && preg_match('~^branding/[a-zA-Z0-9-]+\.(png|jpg|jpeg|webp)$~D', $path) && Storage::disk('uploads')->exists($path) ? $path : null;
    }

    private static function iconFile(int $size): ?string
    {
        abort_unless(in_array($size, [32, 180, 512], true), 404);
        $path = self::stored('icon');
        $variant = $path ? str_replace('-512.png', '-'.$size.'.png', $path) : null;

        return $variant && Storage::disk('uploads')->exists($variant) ? $variant : null;
    }

    private static function url(?string $path, string $fallback): string
    {
        $disk = Storage::disk('uploads');
        $file = $path ? $disk->path($path) : public_path($fallback);
        clearstatcache(true, $file);

        return ($path ? $disk->url($path) : asset($fallback)).'?v='.filemtime($file);
    }

    public static function replace(string $type, ?UploadedFile $file): void
    {
        $paths = [];
        try {
            if ($file) {
                $image = @imagecreatefromstring($file->getContent());
                if (! $image instanceof GdImage) {
                    throw ValidationException::withMessages(['image' => 'No se pudo leer la imagen. Sube un archivo PNG, JPG o WebP.']);
                }
                try {
                    $base = 'branding/'.Str::uuid();
                    if ($type === 'icon') {
                        foreach ([32, 180, 512] as $size) {
                            $path = $base.'-'.$size.'.png';
                            $paths[] = $path;
                            self::writePng($image, $path, $size, $size);
                        }
                    } else {
                        $scale = min(1, ($type === 'site' ? 800 : 1200) / imagesx($image));
                        $path = $base.'.png';
                        $paths[] = $path;
                        self::writePng($image, $path, max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
                    }
                } finally {
                    imagedestroy($image);
                }
            }
            $old = DB::transaction(function () use ($type, $paths) {
                $setting = Setting::whereKey('logo_'.$type)->lockForUpdate()->first();
                $old = $setting?->value;
                Setting::updateOrCreate(['key' => 'logo_'.$type], ['value' => $paths ? end($paths) : null]);

                return $old;
            });
            Cache::forget('setting:logo_'.$type);
        } catch (Throwable $e) {
            Storage::disk('uploads')->delete($paths);
            throw $e;
        }
        if (is_string($old) && preg_match('~^branding/[a-zA-Z0-9-]+\.(png|jpg|jpeg|webp)$~D', $old)) {
            Storage::disk('uploads')->delete($type === 'icon' ? array_map(fn ($size) => str_replace('-512.png', '-'.$size.'.png', $old), [32, 180, 512]) : [$old]);
        }
    }

    private static function writePng(GdImage $image, string $path, int $width, int $height): void
    {
        $out = imagecreatetruecolor($width, $height);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        ob_start();
        try {
            imagepng($out, null, 8);
            $bytes = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($out);
        }
        Storage::disk('uploads')->put($path, $bytes);
    }
}
