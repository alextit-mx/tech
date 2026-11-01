<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sin contraseña por defecto: si OWNER_PASSWORD no está en .env se genera una y se muestra una sola vez.
        $password = env('OWNER_PASSWORD') ?: Str::password(16);
        if (! env('OWNER_PASSWORD')) {
            $this->command?->warn("Contraseña del dueño generada: {$password}");
        }
        User::updateOrCreate(['email' => env('OWNER_EMAIL', 'dueno@techcomputer.com.mx')], ['name' => 'Dueño Tech Computer', 'password' => Hash::make($password), 'role' => 'owner', 'is_active' => true]);
        $settings = ['exchange_rate' => '17.50', 'quote_validity_days' => '3', 'business_name' => 'Tech Computer', 'legal_name' => 'Tech Computer ATS SA de CV', 'rfc' => 'TCA240830IM3', 'address' => 'Plaza de la Tecnología, República de Uruguay 9, Centro, Ciudad de México', 'whatsapp' => '525549114435', 'email' => 'techcomputerlocal64@gmail.com', 'website' => '', 'bank_details' => 'BBVA a nombre de Tech Computer ATS SA de CV. CLABE: 012180001242184691', 'warranty_terms' => 'Garantía sujeta a las condiciones del fabricante.', 'quote_terms' => 'Precios en pesos mexicanos con IVA incluido. Cotización válida por 3 días.'];
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $tree = [
            'PCs armadas' => [], 'Componentes' => ['Procesadores' => 'cpu', 'Tarjetas madre' => 'motherboard', 'Memoria RAM' => 'ram', 'Tarjetas de video' => 'gpu', 'Almacenamiento' => 'storage', 'Fuentes de poder' => 'psu', 'Enfriamiento' => 'cooler', 'Gabinetes' => 'case'],
            'Periféricos gamer' => ['Teclados' => null, 'Mouse' => null, 'Audífonos' => null, 'Monitores' => null, 'Sillas' => null, 'Controles' => null],
            'Accesorios' => ['Cables y adaptadores' => null, 'Almacenamiento externo y USB' => null, 'Redes' => null, 'Energía (no-break y reguladores)' => null],
            'Impresoras y consumibles' => ['Impresoras' => null, 'Tintas' => null, 'Tóner' => null, 'Papel' => null],
        ];
        $sort = 0;
        foreach ($tree as $parent => $children) {
            $p = Category::firstOrCreate(['slug' => Str::slug($parent)], ['name' => $parent, 'sort' => $sort++]);
            foreach ($children as $name => $slot) {
                Category::firstOrCreate(['slug' => Str::slug($name)], ['parent_id' => $p->id, 'name' => $name, 'builder_slot' => $slot, 'sort' => $sort++]);
            }
        }
        if (app()->environment('local', 'development', 'testing') && env('SEED_DEMO', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
