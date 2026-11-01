<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }
        foreach (['AMD', 'Intel', 'NVIDIA', 'ASUS', 'Kingston', 'Corsair', 'Samsung', 'Gigabyte'] as $brand) {
            Brand::firstOrCreate(['slug' => Str::slug($brand)], ['name' => $brand]);
        }
        foreach (['vendedor1@techcomputer.mx', 'vendedor2@techcomputer.mx', 'vendedor3@techcomputer.mx'] as $i => $email) {
            User::firstOrCreate(['email' => $email], ['name' => 'Vendedor '.($i + 1), 'password' => 'password', 'role' => 'seller', 'is_active' => true]);
        }
        $samples = [
            ['Procesadores', 'CPU-5600', 'AMD Ryzen 5 5600', 'AMD', ['socket' => 'AM4', 'cores' => 6, 'threads' => 12, 'tdp_w' => 90, 'igpu' => false, 'tier' => 1], 2350, 3200, 14],
            ['Procesadores', 'CPU-7600', 'AMD Ryzen 5 7600', 'AMD', ['socket' => 'AM5', 'cores' => 6, 'threads' => 12, 'tdp_w' => 90, 'igpu' => true, 'tier' => 2], 3850, 4990, 17],
            ['Procesadores', 'CPU-9800X3D', 'AMD Ryzen 7 9800X3D', 'AMD', ['socket' => 'AM5', 'cores' => 8, 'threads' => 16, 'tdp_w' => 160, 'igpu' => true, 'tier' => 4], 8500, 10990, 40],
            ['Tarjetas de video', 'GPU-RTX5070', 'NVIDIA GeForce RTX 5070 12 GB', 'NVIDIA', ['tdp_w' => 250, 'vram_gb' => 12, 'length_mm' => 290, 'tier' => 3], 10500, 13990, 4],
            ['Tarjetas de video', 'GPU-RTX5080', 'NVIDIA GeForce RTX 5080 16 GB', 'NVIDIA', ['tdp_w' => 360, 'vram_gb' => 16, 'length_mm' => 330, 'tier' => 5], 19000, 24990, 2],
        ];
        foreach ($samples as [$category,$sku,$name,$brand,$specs,$cost,$price,$stock]) {
            $cat = Category::where('name', $category)->first();
            $b = Brand::where('name', $brand)->first();
            Product::firstOrCreate(['sku' => $sku], ['name' => $name, 'slug' => Str::slug($name), 'category_id' => $cat->id, 'brand_id' => $b->id, 'cost' => $cost, 'cost_currency' => 'MXN', 'price' => $price, 'price_mode' => 'show', 'stock' => $stock, 'is_published' => true, 'specs' => $specs, 'short_description' => $name]);
        }
        $processors = [
            ['AMD Ryzen 5 8600G', 'AMD', 'AM5', 6, 12, 90, true, 1],
            ['Intel Core i5-14400F', 'Intel', 'LGA1700', 10, 16, 150, false, 1],
            ['AMD Ryzen 5 9600X', 'AMD', 'AM5', 6, 12, 90, true, 2],
            ['AMD Ryzen 7 9700X', 'AMD', 'AM5', 8, 16, 90, true, 3],
            ['Intel Core Ultra 5 245K', 'Intel', 'LGA1851', 14, 14, 160, true, 3],
            ['AMD Ryzen 9 9900X', 'AMD', 'AM5', 12, 24, 160, true, 4],
            ['Intel Core Ultra 7 265K', 'Intel', 'LGA1851', 20, 20, 220, true, 4],
            ['AMD Ryzen 9 9950X3D', 'AMD', 'AM5', 16, 32, 230, true, 5],
            ['Intel Core Ultra 9 285K', 'Intel', 'LGA1851', 24, 24, 250, true, 5],
        ];
        foreach ($processors as $index => [$name, $brandName, $socket, $cores, $threads, $tdp, $igpu, $tier]) {
            $category = Category::where('builder_slot', 'cpu')->first();
            $brand = Brand::where('name', $brandName)->first();
            Product::firstOrCreate(['sku' => 'CPU-DEMO-'.($index + 10)], ['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id, 'brand_id' => $brand->id, 'cost' => 2500 + $tier * 1500, 'price' => 3490 + $tier * 2000, 'cost_currency' => 'MXN', 'stock' => 3, 'price_mode' => 'show', 'is_published' => true, 'specs' => ['socket' => $socket, 'cores' => $cores, 'threads' => $threads, 'tdp_w' => $tdp, 'igpu' => $igpu, 'tier' => $tier]]);
        }
        $graphics = [
            ['Intel Arc B580 12 GB', 'Intel', 12, 190, 270, 1], ['NVIDIA GeForce RTX 5060 8 GB', 'NVIDIA', 8, 145, 240, 1],
            ['AMD Radeon RX 9060 XT 16 GB', 'AMD', 16, 180, 260, 2], ['NVIDIA GeForce RTX 5060 Ti 16 GB', 'NVIDIA', 16, 180, 250, 2],
            ['NVIDIA GeForce RTX 5070 12 GB', 'NVIDIA', 12, 250, 290, 3], ['AMD Radeon RX 9070 16 GB', 'AMD', 16, 220, 300, 3],
            ['AMD Radeon RX 9070 XT 16 GB', 'AMD', 16, 304, 330, 4], ['NVIDIA GeForce RTX 5070 Ti 16 GB', 'NVIDIA', 16, 300, 320, 4],
            ['NVIDIA GeForce RTX 5080 16 GB', 'NVIDIA', 16, 360, 330, 5], ['NVIDIA GeForce RTX 5090 32 GB', 'NVIDIA', 32, 575, 350, 6],
        ];
        foreach ($graphics as $index => [$name, $brandName, $vram, $tdp, $length, $tier]) {
            if (Product::where('name', $name)->exists()) {
                continue;
            }
            $category = Category::where('builder_slot', 'gpu')->first();
            $brand = Brand::where('name', $brandName)->first();
            $cost = 8000 + $tier * 6500;
            Product::firstOrCreate(['sku' => 'GPU-DEMO-'.($index + 1)], ['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id, 'brand_id' => $brand->id, 'cost' => $cost, 'price' => ceil(($cost * 1.18) / 10) * 10, 'cost_currency' => 'MXN', 'stock' => 2, 'price_mode' => 'show', 'is_published' => true, 'specs' => ['tdp_w' => $tdp, 'vram_gb' => $vram, 'length_mm' => $length, 'tier' => $tier]]);
        }
        $extras = [
            'Tarjetas madre' => [3000, 4290, ['socket' => 'AM5', 'memory_type' => 'DDR5', 'form_factor' => 'mATX', 'm2_slots' => 2]],
            'Memoria RAM' => [1500, 2190, ['memory_type' => 'DDR5', 'capacity_gb' => 32, 'modules' => 2, 'speed_mhz' => 6000]],
            'Almacenamiento' => [1100, 1590, ['type' => 'NVMe', 'capacity_gb' => 1000]],
            'Fuentes de poder' => [1500, 2190, ['watts' => 750, 'certification' => '80+ Gold']],
            'Enfriamiento' => [900, 1390, ['type' => 'aire', 'sockets' => ['AM5', 'AM4', 'LGA1700', 'LGA1851'], 'max_tdp_w' => 200]],
            'Gabinetes' => [1600, 2390, ['form_factors' => ['ATX', 'mATX', 'ITX'], 'max_gpu_length_mm' => 360]],
        ];
        foreach (['Tarjetas madre', 'Memoria RAM', 'Almacenamiento', 'Fuentes de poder', 'Enfriamiento', 'Gabinetes', 'Teclados', 'Mouse', 'Audífonos', 'Monitores', 'Impresoras', 'Tintas', 'Tóner'] as $name) {
            $c = Category::where('name', $name)->first();
            $brand = Brand::first();
            [$cost, $price, $specs] = $extras[$name] ?? [500, 890, []];
            Product::firstOrCreate(['sku' => 'DEMO-'.Str::upper(Str::slug($name))], ['name' => $name.' para demostración', 'slug' => 'demo-'.Str::slug($name), 'category_id' => $c->id, 'brand_id' => $brand->id, 'cost' => $cost, 'price' => $price, 'cost_currency' => 'MXN', 'stock' => 5, 'price_mode' => $name === 'Impresoras' ? 'quote' : 'show', 'is_published' => true, 'specs' => $specs]);
        }
    }
}
