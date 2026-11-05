@extends('layouts.site')

@section('title', 'Asesoría para elegir tu PC | Tech Computer')
@section('description', 'Qué componente importa más para jugar en 1080p, 1440p o 4K, transmitir, editar o estudiar. Respuestas a las dudas más comunes al armar una PC.')

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page">
    <div class="page-head page-head--editorial">
        <h1>Elige bien.<br />Arma mejor.</h1>
        <div class="page-head-copy">
        <p class="lede">Te ayudamos a invertir donde se nota: qué pieza importa para lo que haces, qué puede esperar y qué no conviene recortar. Pregúntanos sin compromiso.</p>
        <div class="actions">
            <a class="btn btn-primary" data-whats="Hola Tech Computer, quiero asesoría para elegir mi PC. La quiero para:" href="https://wa.me/525549114435">
                Pedir asesoría
                <span class="ic"><span class="mask" style="--icon: url('https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/whatsapp.svg')"></span></span>
            </a>
        </div>
        </div>
    </div>

    <section class="section" id="segun-uso">
        <h2>Elige según tu uso</h2>
        <ul class="guide">
            <li>
                <h3>Juegos en 1080p</h3>
                <p><span class="k">Lo que más importa</span>Una tarjeta de video de entrada o media y 16 GB de memoria; 32 GB si juegas títulos nuevos.</p>
                <p><span class="k">Un buen punto de partida</span>RTX 5060, Arc B580 o RX 9060 XT con un Ryzen 5. <a class="more" href="/arma-tu-pc?uso=gaming1080&amp;presupuesto=equilibrada">Armarla</a></p>
            </li>
            <li>
                <h3>Juegos en 1440p</h3>
                <p><span class="k">Lo que más importa</span>La tarjeta de video pesa más que el procesador; busca de 12 a 16 GB de memoria de video.</p>
                <p><span class="k">Un buen punto de partida</span>RTX 5070 o RX 9070 con un Ryzen 7 y 32 GB. <a class="more" href="/arma-tu-pc?uso=gaming1440&amp;presupuesto=equilibrada">Armarla</a></p>
            </li>
            <li>
                <h3>Juegos en 4K</h3>
                <p><span class="k">Lo que más importa</span>Una tarjeta de gama alta con 16 GB o más y una fuente con margen.</p>
                <p><span class="k">Un buen punto de partida</span>RTX 5070 Ti, RX 9070 XT o RTX 5080 con un Ryzen 7 9800X3D. <a class="more" href="/arma-tu-pc?uso=gaming4k&amp;presupuesto=alta">Armarla</a></p>
            </li>
            <li>
                <h3>Jugar y transmitir</h3>
                <p><span class="k">Lo que más importa</span>Ocho núcleos o más, 32 GB y un buen codificador de video en la tarjeta.</p>
                <p><span class="k">Un buen punto de partida</span>Ryzen 7 o Core Ultra 5 con una GeForce RTX. <a class="more" href="/arma-tu-pc?uso=stream&amp;presupuesto=equilibrada">Armarla</a></p>
            </li>
            <li>
                <h3>Edición, diseño e IA</h3>
                <p><span class="k">Lo que más importa</span>Núcleos, mucha memoria y, para render e IA, una GeForce con más memoria de video.</p>
                <p><span class="k">Un buen punto de partida</span>Ryzen 9 o Core Ultra 7 con 64 GB y una RTX 5070 o superior. <a class="more" href="/arma-tu-pc?uso=create&amp;presupuesto=alta">Armarla</a></p>
            </li>
            <li>
                <h3>Escuela y oficina</h3>
                <p><span class="k">Lo que más importa</span>Un SSD y 16 GB de memoria; no necesita tarjeta de video.</p>
                <p><span class="k">Un buen punto de partida</span>Ryzen 5 8600G con sus gráficos integrados. <a class="more" href="/arma-tu-pc?uso=office&amp;presupuesto=esencial">Armarla</a></p>
            </li>
        </ul>
    </section>

    <section class="section" id="preguntas">
        <h2>Preguntas frecuentes</h2>
        <div class="faq">
            <details>
                <summary>¿AMD o Intel?</summary>
                <div class="answer">
                    <p>Para jugar, los Ryzen con 3D V-Cache, como el Ryzen 7 9800X3D, son hoy los más rápidos. Para trabajo con muchos núcleos, los Ryzen 9 y los Core Ultra 7 y 9 rinden muy bien.</p>
                    <p>La plataforma AM5 de AMD te deja cambiar después a un procesador más nuevo sin cambiar la tarjeta madre.</p>
                </div>
            </details>
            <details>
                <summary>¿Cuánta memoria RAM necesito?</summary>
                <div class="answer">
                    <p>16 GB alcanzan para escuela, oficina y juegos competitivos. 32 GB es lo recomendado para juegos nuevos, transmitir y editar. Para video 4K, 3D o IA, 64 GB o más.</p>
                    <p>Siempre en dos módulos iguales, para que trabajen en doble canal.</p>
                </div>
            </details>
            <details>
                <summary>¿Cuánta memoria de video necesita la tarjeta?</summary>
                <div class="answer">
                    <p>8 GB funcionan en 1080p con calidad media a alta. Para 1440p y juegos nuevos con texturas en alto, de 12 a 16 GB. Para 4K, 16 GB o más.</p>
                </div>
            </details>
            <details>
                <summary>¿De cuántos watts debe ser la fuente?</summary>
                <div class="answer">
                    <p>Se suma lo que consumen el procesador y la tarjeta de video, más el resto del equipo, y se deja un margen de 30 a 40 % para los picos. El configurador lo calcula por ti.</p>
                    <p>Las tarjetas como la RTX 5070 Ti, 5080 y 5090 usan el conector 12V-2x6: lo ideal es una fuente ATX 3.1 que traiga ese cable. La fuente no es la pieza para ahorrar.</p>
                </div>
            </details>
            <details>
                <summary>¿Vale la pena un SSD Gen5?</summary>
                <div class="answer">
                    <p>Para jugar, un NVMe Gen4 carga prácticamente igual. Un Gen5 conviene si mueves archivos muy grandes, como video en bruto, y necesita una ranura M.2 Gen5 en la tarjeta madre.</p>
                </div>
            </details>
            <details>
                <summary>¿Enfriamiento por aire o líquido?</summary>
                <div class="answer">
                    <p>Un buen disipador de torre enfría bien a la mayoría de los procesadores. El enfriamiento líquido de 240 o 360 mm conviene en procesadores de alto consumo o si buscas menos ruido y un equipo más limpio a la vista.</p>
                </div>
            </details>
            <details>
                <summary>¿Me conviene actualizar o comprar una PC nueva?</summary>
                <div class="answer">
                    <p>Depende de tu plataforma. Más memoria o un SSD casi siempre valen la pena. Una tarjeta de video nueva rinde menos si tu procesador ya es de varias generaciones atrás.</p>
                    <p>Tráenos tu equipo o mándanos sus datos y te decimos qué conviene.</p>
                </div>
            </details>
            <details>
                <summary>¿Qué es el cuello de botella?</summary>
                <div class="answer">
                    <p>Es cuando una pieza no deja rendir a otra. En 1080p el procesador influye más; en 4K casi todo el trabajo es de la tarjeta de video. Por eso equilibramos las dos según la resolución a la que juegas.</p>
                </div>
            </details>
        </div>
    </section>
</main>

<section class="cta-band">
    <h2>Resolvemos tus dudas</h2>
    <div class="actions">
        <a class="btn btn-primary" data-whats="Hola Tech Computer, tengo una duda:" href="https://wa.me/525549114435">
            Escribir por WhatsApp
            <span class="ic"><span class="mask" style="--icon: url('https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/whatsapp.svg')"></span></span>
        </a>
        <a class="btn btn-ghost" href="/arma-tu-pc">
            Arma tu PC
            <span class="ic"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/cpu.svg')"></span></span>
        </a>
    </div>
</section>
@endsection
