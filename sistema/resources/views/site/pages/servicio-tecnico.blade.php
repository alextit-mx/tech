@extends('layouts.site')

@section('title', 'Servicio técnico: mantenimiento, reparación y actualización | Tech Computer')
@section('description', 'Mantenimiento, reparación y actualización de PCs gamer en Plaza de la Tecnología: limpieza, pasta térmica, diagnóstico de fallas, más memoria, SSD y tarjeta de video.')

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page">
    <div class="page-head">
        <h1>Servicio técnico</h1>
        <p class="lede">Mantenimiento, reparación y actualización de tu PC. Revisamos, te explicamos qué tiene y qué recomendamos hacer, y te la entregamos probada.</p>
        <div class="actions">
            <a class="btn btn-primary" data-whats="Hola Tech Computer, mi PC necesita servicio. Le pasa lo siguiente:" href="https://wa.me/525549114435">
                Contarnos qué le pasa
                <span class="ic"><span class="mask" style="--icon: url('https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/whatsapp.svg')"></span></span>
            </a>
        </div>
    </div>

    <section class="section" id="mantenimiento">
        <h2>Mantenimiento</h2>
        <p class="intro">El polvo y la pasta térmica seca suben la temperatura, el ruido y bajan el rendimiento. Un mantenimiento lo regresa a como debe estar.</p>
        <ul class="sheet">
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/wind.svg')"></span></span>
                <h3>Limpieza a fondo</h3>
                <p>Gabinete, ventiladores, disipador, fuente y filtros, sin dañar componentes.</p>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/thermometer.svg')"></span></span>
                <h3>Pasta térmica y temperaturas</h3>
                <p>Cambio de pasta térmica del procesador y revisión de temperaturas en reposo y con carga.</p>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/fan.svg')"></span></span>
                <h3>Ventiladores y flujo de aire</h3>
                <p>Revisamos ventiladores y curvas, y ordenamos cables para que el aire circule.</p>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/refresh-cw.svg')"></span></span>
                <h3>Software al día</h3>
                <p>Actualización de BIOS y controladores, y revisión de programas que se inician solos.</p>
            </li>
        </ul>
    </section>

    <section class="section" id="reparacion">
        <h2>Reparación</h2>
        <p class="intro">Cuéntanos qué le pasa. Estas son las fallas que más vemos y lo que revisamos en cada una.</p>
        <ul class="symptoms">
            <li><strong>No enciende</strong><span>Fuente, botón y cables de encendido, tarjeta madre.</span></li>
            <li><strong>Enciende pero no da imagen</strong><span>Memoria, tarjeta de video, cable y puerto del monitor, BIOS.</span></li>
            <li><strong>Se apaga o se reinicia sola</strong><span>Temperaturas, fuente de poder y estabilidad de la memoria.</span></li>
            <li><strong>Pantallazo azul</strong><span>Controladores, memoria, disco y configuración de Windows.</span></li>
            <li><strong>Se calienta o hace mucho ruido</strong><span>Polvo, pasta térmica, ventiladores y flujo de aire.</span></li>
            <li><strong>Los juegos se traban o bajan los cuadros</strong><span>Temperaturas, controladores, memoria en doble canal y fuente.</span></li>
            <li><strong>Va lenta</strong><span>Estado del disco, memoria, programas al inicio y malware.</span></li>
            <li><strong>No reconoce un disco o una memoria</strong><span>Conexiones, compatibilidad con la tarjeta madre y configuración del BIOS.</span></li>
        </ul>
        <p class="small footnote">¿Es una laptop? Escríbenos con el modelo y la falla y te decimos si podemos revisarla.</p>
    </section>

    <section class="section" id="actualizacion">
        <h2>Actualización</h2>
        <p class="intro">A veces no hace falta una PC nueva: la pieza correcta hace que tu equipo rinda como nuevo.</p>
        <ul class="sheet">
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/memory-stick.svg')"></span></span>
                <h3>Más memoria</h3>
                <p>Pasar a 32 GB en doble canal ayuda en juegos nuevos, navegadores con muchas pestañas y edición.</p>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/hard-drive.svg')"></span></span>
                <h3>SSD NVMe</h3>
                <p>Si tu equipo aún arranca de disco duro, es el cambio que más se nota.</p>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/gamepad-2.svg')"></span></span>
                <h3>Tarjeta de video</h3>
                <p>Revisamos que tu fuente, tu gabinete y tu procesador la aprovechen antes de recomendarte una.</p>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/cpu.svg')"></span></span>
                <h3>Procesador y tarjeta madre</h3>
                <p>Cuando el procesador limita a tu tarjeta de video, te decimos qué plataforma conviene.</p>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/monitor.svg')"></span></span>
                <h3>Windows y controladores</h3>
                <p>Instalación limpia de Windows con los controladores correctos para tu equipo.</p>
            </li>
        </ul>
    </section>

    <section class="section" id="proceso">
        <h2>Cómo trabajamos tu equipo</h2>
        <ol class="steps">
            <li><h3>Recepción</h3><p>Nos cuentas qué le pasa y revisamos cómo llega.</p></li>
            <li><h3>Diagnóstico</h3><p>Probamos pieza por pieza hasta encontrar la causa.</p></li>
            <li><h3>Te explicamos</h3><p>Qué encontramos, qué recomendamos y cuánto cuesta, antes de empezar.</p></li>
            <li><h3>Reparación y pruebas</h3><p>Lo resolvemos y lo probamos con carga antes de entregártelo.</p></li>
        </ol>
    </section>
</main>

<section class="cta-band">
    <h2>Tráela al local o cuéntanos por WhatsApp qué le pasa.</h2>
    <div class="actions">
        <a class="btn btn-primary" data-whats="Hola Tech Computer, mi PC necesita servicio. Le pasa lo siguiente:" href="https://wa.me/525549114435">
            Escribir por WhatsApp
            <span class="ic"><span class="mask" style="--icon: url('https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/whatsapp.svg')"></span></span>
        </a>
        <a class="btn btn-ghost" href="/contacto">
            Cómo llegar
            <span class="ic"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/map-pin.svg')"></span></span>
        </a>
    </div>
</section>
@endsection
