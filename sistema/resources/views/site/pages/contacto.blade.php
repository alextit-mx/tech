@extends('layouts.site')

@section('title', 'Contacto y ubicación | Tech Computer')
@section('description', 'Visítanos en Plaza de la Tecnología: República de Uruguay 9, entrada 1, planta baja, locales 64 y 60, Centro, Ciudad de México. WhatsApp 55 4911 4435.')

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page">
    <div class="split">
        <div>
            <div class="page-head">
                <h1>Visítanos<br />en la plaza.</h1>
            </div>
            <p class="lede">Plaza de la Tecnología, Centro de la Ciudad de México.</p>
            <address>
                República de Uruguay 9, entrada 1, planta baja, locales 64 y 60
                <span>Centro, Ciudad de México</span>
            </address>
            <div class="contact-list">
                <a data-whats="Hola Tech Computer" href="https://wa.me/525549114435"><span class="mask" style="--icon: url('https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/whatsapp.svg')"></span>55 4911 4435</a>
                <a href="mailto:techcomputerlocal64@gmail.com"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/mail.svg')"></span>techcomputerlocal64@gmail.com</a>
                <a href="mailto:aliciatorressegundo@gmail.com"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/mail.svg')"></span>aliciatorressegundo@gmail.com</a>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="https://www.google.com/maps/search/?api=1&amp;query=Plaza%20de%20la%20Tecnolog%C3%ADa%20Rep%C3%BAblica%20de%20Uruguay%209%20Centro%20Ciudad%20de%20M%C3%A9xico" target="_blank" rel="noopener">
                    Abrir en Google Maps
                    <span class="ic"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/map-pin.svg')"></span></span>
                </a>
            </div>
        </div>

        <div class="panel pay">
            <div class="core">
                <h3>Pago por transferencia</h3>
                <p class="bank">BBVA a nombre de Tech Computer ATS SA de CV</p>
                <dl>
                    <div><dt>Número de cuenta</dt><dd><span>0124218469</span><button class="copy" type="button" data-copy="0124218469">Copiar</button></dd></div>
                    <div><dt>CLABE interbancaria</dt><dd><span>012180001242184691</span><button class="copy" type="button" data-copy="012180001242184691">Copiar</button></dd></div>
                </dl>
            </div>
        </div>
    </div>

    <div class="map">
        <iframe
            title="Mapa de Plaza de la Tecnología, República de Uruguay 9, Centro"
            src="https://maps.google.com/maps?q=Rep%C3%BAblica%20de%20Uruguay%209%2C%20Centro%2C%20Ciudad%20de%20M%C3%A9xico&amp;z=17&amp;output=embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
        ></iframe>
    </div>
</main>
@endsection
