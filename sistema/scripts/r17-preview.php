<?php

// Ejemplos aislados: nunca abre la base configurada en .env ni ejecuta seeders.
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warranty;
use App\Services\QuoteService;
use App\Support\Pdf;
use App\Support\QuotePdf;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$database = '/tmp/r17-preview/database.sqlite';
if (file_exists($database)) {
    fwrite(STDERR, "La base temporal ya existe. Usa un directorio temporal nuevo para regenerar los ejemplos.\n");
    exit(1);
}
@mkdir(dirname($database), 0777, true);
touch($database);
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'APP_URL' => 'http://127.0.0.1:8187'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'cache.default' => 'array']);
app('migrator')->setConnection('sqlite');
app('migrator')->getRepository()->createRepository();
app('migrator')->run([database_path('migrations')]);

foreach ([
    'business_name' => 'Tech Computer', 'location_name' => 'Sucursal de demostración',
    'legal_name' => 'NEGOCIO DE DEMOSTRACIÓN', 'rfc' => 'AAA010101AA1', 'address' => "CALLE DE EJEMPLO 10\nCENTRO, CIUDAD DE MÉXICO",
    'phone' => '5555555555', 'email' => 'ventas@example.test', 'whatsapp' => '',
    'quote_terms' => '', 'quote_validity_days' => '15',
] as $key => $value) {
    Setting::create(compact('key', 'value'));
}
$user = User::factory()->create(['name' => 'Vendedor de prueba', 'email' => 'r17@example.test', 'password' => 'R17-preview-local', 'role' => 'owner']);
$customer = Customer::create([
    'kind' => 'registrado', 'name' => 'Ana Pérez', 'company' => 'Estudio de demostración', 'position' => 'Compras',
    'legal_name' => 'ESTUDIO DE DEMOSTRACIÓN', 'rfc' => 'BBB010101BB1', 'tax_regime' => '601', 'tax_zip' => '06000',
    'phone' => '5555555556', 'email' => 'contacto@example.test', 'street' => 'Calle de ejemplo', 'ext_number' => '20',
    'neighborhood' => 'Centro', 'city' => 'Cuauhtémoc', 'state' => 'Ciudad de México', 'zip' => '06000',
]);
$directory = storage_path('app/r17');
@mkdir($directory, 0777, true);
foreach ([2, 12] as $count) {
    $lines = [];
    for ($i = 0; $i < $count; $i++) {
        $lines[] = ['brand' => 'MSI', 'model' => 'X870E TOMAHAWK MAX WIFI7', 'description' => 'Tarjeta madre AMD X870E, socket AM5, memoria DDR5, formato ATX. Partida '.($i + 1).'.', 'warranty' => '1 AÑO', 'quantity' => 1, 'unit_price' => 7308];
    }
    $quote = app(QuoteService::class)->saveFromAdmin(null, [
        'customer_id' => $customer->id, 'valid_until' => now()->addDays(15)->toDateString(), 'items' => $lines,
        'notes' => $count === 2 ? 'Cotización de demostración. Los datos de contacto son ficticios.' : null,
    ], $user);
    file_put_contents($directory.'/cotizacion-'.$count.'-partidas.pdf', QuotePdf::download($quote)->getContent());
}
$order = ServiceOrder::create([
    'folio' => 'OS-DEMO-0001', 'customer_id' => $customer->id, 'user_id' => $user->id, 'device_type' => 'laptop',
    'brand' => 'Lenovo', 'model' => 'Equipo de prueba', 'reported_issue' => 'El equipo no enciende.', 'received_at' => now(),
]);
file_put_contents($directory.'/orden-servicio.pdf', Pdf::stream('pdf.service-order', ['order' => $order->load('customer', 'user')], 'orden.pdf')->getContent());
$warranty = Warranty::create(['folio' => 'GAR-DEMO-0001', 'customer_id' => $customer->id, 'user_id' => $user->id, 'starts_at' => today()]);
$warranty->items()->create(['description' => 'Tarjeta madre MSI X870E de prueba', 'serial' => 'DEMO-001', 'months' => 12]);
file_put_contents($directory.'/garantia.pdf', Pdf::stream('pdf.warranty', ['warranty' => $warranty->load('customer', 'items')], 'garantia.pdf')->getContent());
echo "Ejemplos guardados en storage/app/r17/. Base aislada: {$database}\n";
