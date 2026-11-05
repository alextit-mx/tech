<?php

namespace App\Support\Hardware;

use App\Models\Product;
use App\Support\WebpImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;

class Icecat
{
    private Filesystem $cache;

    private bool $queried = false;

    private ?array $modelIndex = null;

    public function __construct(?string $cacheDirectory = null)
    {
        $this->cache = Storage::build(['driver' => 'local', 'root' => $cacheDirectory ?? storage_path('app/icecat'), 'throw' => true]);
    }

    public static function gtin(string $sku): ?string
    {
        $digits = preg_replace('/^[a-z]+/i', '', trim($sku));

        return preg_match('/^\d{8,14}$/D', $digits) ? $digits : null;
    }

    /** @return array{data: ?array, method: ?string, code: int, attempts: array} */
    public function find(Product $product, bool $refresh = false): array
    {
        $queries = [];
        if ($gtin = self::gtin($product->sku)) {
            $queries['gtin'] = ['GTIN' => $gtin];
        }
        $tokens = preg_split('/\s+/u', trim($product->name));
        if ($product->brand?->name && ($part = end($tokens))) {
            $queries['part'] = ['Brand' => $product->brand->name, 'ProductCode' => $part];
        }
        $attempts = [];
        foreach ($queries as $method => $query) {
            $result = $this->query($query, $refresh);
            $attempts[] = ['method' => $method, 'code' => $result['code']];
            if ($result['data'] !== null) {
                return [...$result, 'method' => $method, 'attempts' => $attempts];
            }
        }

        return ['data' => null, 'method' => null, 'code' => $result['code'] ?? 404, 'attempts' => $attempts];
    }

    private function query(array $query, bool $refresh): array
    {
        $params = ['UserName' => config('services.icecat.username'), 'Language' => 'es', ...$query];
        $key = hash('sha256', json_encode($params)).'.json';
        $cached = $this->cache->exists($key) ? json_decode($this->cache->get($key), true) : null;
        if (! $refresh && is_array($cached) && isset($cached['queried_at'], $cached['code']) && array_key_exists('data', $cached)
            && ($cached['data'] !== null || $cached['queried_at'] > now()->subDays(30)->timestamp)) {
            return ['data' => $cached['data'], 'code' => $cached['code']];
        }
        if ($this->queried) {
            Sleep::for(200)->milliseconds();
        }
        $this->queried = true;
        try {
            $response = Http::acceptJson()->timeout(30)->get('https://live.icecat.biz/api/', $params);
            $data = $response->json('data');
            $code = $response->status();
            if (! $response->successful() || ! is_array($data) || empty($data['GeneralInfo'])) {
                $data = null;
                if ($response->successful()) {
                    $code = (int) ($response->json('statusCode') ?: 404);
                    $code = $code >= 400 ? $code : 404;
                }
            }
        } catch (ConnectionException) {
            $data = null;
            $code = 0;
        }
        $this->cache->put($key, json_encode(['queried_at' => now()->timestamp, 'code' => $code, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return ['data' => $data, 'code' => $code];
    }

    public static function modelName(array $data): ?string
    {
        $info = $data['GeneralInfo'] ?? [];
        foreach ([$info['ProductName'] ?? null, $info['ProductNameInfo']['ProductLocalName']['Value'] ?? null,
            $info['ProductNameInfo']['ProductIntName'] ?? null, $info['BrandPartCode'] ?? null, $info['ProductCode'] ?? null] as $value) {
            if (is_string($value) && trim($value) !== '' && mb_strlen(trim($value)) <= 40 && ! preg_match('/ - |,|tarjeta|gr[aá]fica|memoria|disco|procesador/iu', $value)) {
                return trim($value);
            }
        }

        return null;
    }

    /** Solo lee la caché: completar modelos nunca debe consultar Icecat. */
    public function cachedModel(Product $product, bool $original = false): ?string
    {
        if ($this->modelIndex === null) {
            $this->modelIndex = [];
            foreach ($this->cache->files() as $file) {
                if (! str_ends_with($file, '.json')) {
                    continue;
                }
                $cached = json_decode($this->cache->get($file), true);
                $data = $cached['data'] ?? null;
                if (! is_array($data) || ! ($model = self::modelName($data))) {
                    continue;
                }
                $info = $data['GeneralInfo'];
                foreach ($info['GTIN'] ?? [] as $gtin) {
                    $this->modelIndex['gtin:'.$gtin] = ['model' => $model, 'original' => $info['ProductName'] ?? null];
                }
                $partCode = $info['BrandPartCode'] ?? $info['ProductCode'] ?? null;
                if (! empty($info['Brand']) && filled($partCode)) {
                    $this->modelIndex['part:'.mb_strtoupper($info['Brand'].'|'.$partCode)] = ['model' => $model, 'original' => $info['ProductName'] ?? null];
                }
            }
        }
        if (($gtin = self::gtin($product->sku)) && isset($this->modelIndex['gtin:'.$gtin])) {
            return $this->modelIndex['gtin:'.$gtin][$original ? 'original' : 'model'];
        }
        $tokens = preg_split('/\s+/u', trim($product->name));
        $part = end($tokens);

        return $this->modelIndex['part:'.mb_strtoupper(($product->brand?->name ?? '').'|'.$part)][$original ? 'original' : 'model'] ?? null;
    }

    public static function gallery(array $data): array
    {
        $urls = [];
        foreach ($data['Gallery'] ?? [] as $image) {
            // Pic es el original; los otros campos de Gallery son sus reducciones.
            $url = $image['Pic'] ?? $image['HighPic'] ?? $image['Pic500x500'] ?? $image['LowPic'] ?? null;
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    public function addImages(Product $product, array $data): int
    {
        if ($product->images()->exists()) {
            return 0;
        }
        $count = 0;
        foreach (self::gallery($data) as $url) {
            if ($count === 6) {
                break;
            }
            try {
                $response = Http::timeout(30)->get($url);
                if (! $response->successful()) {
                    continue;
                }
                $path = WebpImage::storeContent($response->body(), 'products');
            } catch (ConnectionException|ValidationException) {
                continue;
            }
            $product->images()->create(['path' => $path, 'sort' => $count + 1]);
            $count++;
        }

        return $count;
    }

    public function writePending(array $rows): void
    {
        $file = fopen('php://temp', 'w+b');
        fputcsv($file, ['id', 'sku', 'nombre', 'slot', 'marca', 'campos_pendientes', 'conflictos'], escape: '');
        foreach ($rows as $row) {
            fputcsv($file, $row, escape: '');
        }
        rewind($file);
        $this->cache->put('pendientes.csv', stream_get_contents($file));
        fclose($file);
    }
}
