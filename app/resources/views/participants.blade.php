<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios · Huésped</title>
    <link rel="stylesheet" href="{{ asset('panel.css') }}">
    <link rel="stylesheet" href="{{ asset('participants.css') }}">
</head>
<body>
<header>
    <a class="brand" href="{{ route('dashboard') }}" aria-label="Fundación Huésped · Panel"><x-application-logo /></a>
    <a href="{{ route('dashboard') }}">Preguntas</a>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="outline">Cerrar sesión</button></form>
</header>
<main>
    <p class="eyebrow">ENLACES PERSONALES</p>
    <div class="panel-heading">
        <h1>Usuarios</h1>
        <a class="participant-link" href="{{ route('participants.export') }}">Descargar CSV con enlaces</a>
    </div>
    <p>Cargá el ID de cada usuario junto con su nombre y mail. Ese ID se usa en su enlace personal para completar los datos del formulario.</p>
    @if (session('status'))
        <p class="participant-notice" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="participant-errors" role="alert">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif
    <div class="participant-forms">
        <section class="card" aria-labelledby="import-title">
            <h2 id="import-title">Importar un CSV</h2>
            <p>Columnas: <strong>id,user,mail</strong>. También se admite punto y coma. Hasta 2 MB.</p>
            <p>Si el ID ya existe, se actualizan el usuario y el mail. Los ceros iniciales del ID se conservan.</p>
            <form method="POST" action="{{ route('participants.import') }}" enctype="multipart/form-data">
                @csrf
                <label for="csv">Archivo de usuarios</label>
                <input id="csv" name="csv" type="file" accept=".csv,text/csv" required>
                <button type="submit">Importar usuarios</button>
            </form>
        </section>
        <section class="card" aria-labelledby="create-title">
            <h2 id="create-title">Cargar una persona</h2>
            <form method="POST" action="{{ route('participants.store') }}">
                @csrf
                <label for="uid">ID del usuario</label>
                <input id="uid" name="uid" value="{{ old('uid') }}" maxlength="40" pattern="[A-Za-z0-9_-]+" required>
                <label for="name">Usuario</label>
                <input id="name" name="name" value="{{ old('name') }}" maxlength="150" required>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" required>
                <button type="submit">Crear enlace personal</button>
            </form>
        </section>
    </div>
    <section class="participant-list" aria-labelledby="participants-title">
        <h2 id="participants-title">Usuarios cargados <span>{{ $participants->total() }}</span></h2>
        <div class="participant-grid">
            @forelse ($participants as $participant)
                <article class="card">
                    <h3>{{ $participant->name }}</h3>
                    <p class="participant-id"><strong>ID:</strong> {{ $participant->uid }}</p>
                    <p class="email">{{ $participant->email }}</p>
                    <label for="link-{{ $participant->id }}">Enlace personal</label>
                    <input id="link-{{ $participant->id }}" type="url" value="{{ route('formulario', ['uid' => $participant->uid]) }}" readonly onclick="this.select()">
                    <a class="participant-link" href="{{ route('formulario', ['uid' => $participant->uid]) }}" target="_blank" rel="noopener noreferrer">Abrir formulario</a>
                </article>
            @empty
                <p class="empty">Todavía no hay usuarios cargados.</p>
            @endforelse
        </div>
        <nav aria-label="Páginas de usuarios">
            @if ($participants->previousPageUrl())<a href="{{ $participants->previousPageUrl() }}">Anterior</a>@endif
            <span>Página {{ $participants->currentPage() }} de {{ $participants->lastPage() }}</span>
            @if ($participants->nextPageUrl())<a href="{{ $participants->nextPageUrl() }}">Siguiente</a>@endif
        </nav>
    </section>
</main>
</body>
</html>
