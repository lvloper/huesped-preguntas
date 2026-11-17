<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Panel de preguntas · Huésped</title>
    <link rel="stylesheet" href="{{ asset('panel.css') }}?v={{ filemtime(public_path('panel.css')) }}">
    <script src="{{ asset('panel.js') }}?v={{ filemtime(public_path('panel.js')) }}" defer></script>
</head>
<body data-poll-ms="{{ config('aging.poll_ms', 5000) }}">
<header>
    <a class="brand" href="{{ route('dashboard') }}" aria-label="Fundación Huésped · Panel"><x-application-logo /></a>
    <span>Panel de preguntas</span>
    <a href="{{ route('participants.index') }}">Participantes</a>
    <form method="POST" action="{{ url('/logout') }}">@csrf<button class="outline">Cerrar sesión</button></form>
</header>
<main>
    <p class="eyebrow">MODERACIÓN EN VIVO</p>
    <div class="panel-heading">
        <h1>Las preguntas de tu encuentro</h1>
        <button id="show-discarded" class="outline" type="button" aria-expanded="false" aria-controls="discarded-section">Preguntas descartadas <span id="discarded-count">0</span></button>
    </div>
    <p>Seleccioná las preguntas que querés leer y marcalas cuando estén respondidas.</p>
    <p id="connection" role="status">Conectando…</p>
    <p id="error" role="alert" hidden></p>
    <section id="discarded-section" aria-labelledby="discarded-title" hidden>
        <h2 id="discarded-title">Preguntas descartadas</h2>
        <p class="archive-note">Se conservan las 100 preguntas descartadas más recientes. Al superar ese límite, se elimina la más antigua.</p>
        <div id="discarded-list" class="cards"></div>
        <nav id="discarded-pages" aria-label="Páginas de preguntas descartadas"></nav>
    </section>
    <div class="columns">
        <section aria-labelledby="received-title">
            <div class="section-heading">
                <h2 id="received-title">Preguntas recibidas <span id="received-count"></span></h2>
                <button id="sort-received" class="outline" type="button" title="Más antiguas primero. Cambiar a DESC.">Orden: ASC ↑</button>
            </div>
            <div id="received-list" class="cards"></div>
            <nav id="received-pages" aria-label="Páginas de preguntas recibidas"></nav>
        </section>
        <section aria-labelledby="selected-title">
            <h2 id="selected-title">Seleccionadas para leer <span id="selected-count"></span></h2>
            <div id="selected-list" class="cards"></div>
            <button id="show-answered" class="outline" aria-expanded="false" aria-controls="answered-section">Ver preguntas respondidas</button>
            <section id="answered-section" hidden>
                <h3>Preguntas respondidas</h3>
                <div id="answered-list" class="cards"></div>
                <nav id="answered-pages" aria-label="Páginas de preguntas respondidas"></nav>
            </section>
        </section>
    </div>
</main>
</body>
</html>
