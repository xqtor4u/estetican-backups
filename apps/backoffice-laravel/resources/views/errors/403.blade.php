{{--
    Página de 403 con acabado propio (SYNC-081).

    Laravel la renderiza automáticamente para cualquier HttpException 403:
    el middleware `permission:`/`role:` de Spatie (lanza UnauthorizedException, que
    extiende HttpException con estado 403) y el `abort(403, ...)` del middleware
    `superadmin` (EnsureSuperAdmin). Antes, sin esta vista, se mostraba la página
    cruda de Laravel con el mensaje en inglés "User does not have the right
    permissions." — que aparecía incluso justo después de un login correcto si la
    cuenta no tenía acceso a la pantalla de aterrizaje (`/dashboard`).

    Es deliberadamente autónoma (no extiende `layouts.app`): una página de error
    debe renderizar aunque el estado compartido de las vistas o la navegación estén
    a medias. Sin JS — la CSP del motor exige nonce para scripts inline.
--}}
@php
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso restringido</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f4f1ea;
            color: #2b2b2b;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            background: #fff;
            border: 1px solid #e4ded1;
            border-radius: 1rem;
            box-shadow: 0 12px 32px rgba(60, 50, 30, 0.12);
            padding: 2rem;
            text-align: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3.25rem;
            height: 3.25rem;
            border-radius: 999px;
            background: #f3e6e0;
            color: #a8452c;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.35rem;
            margin: 0 0 0.5rem;
        }
        p {
            margin: 0.35rem 0;
            color: #5c574d;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .who {
            margin-top: 0.75rem;
            font-size: 0.85rem;
            color: #8a8375;
        }
        .who strong { color: #5c574d; }
        .actions {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 0.65rem 1rem;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #2b2b2b;
            color: #fff;
        }
        .btn-secondary {
            background: transparent;
            border-color: #d8d2c4;
            color: #5c574d;
        }
        form { margin: 0; }
        @media (prefers-color-scheme: dark) {
            body { background: #1c1a17; color: #ede9e0; }
            .card { background: #262320; border-color: #3a352e; box-shadow: none; }
            .badge { background: #3a2a25; color: #e79a84; }
            p, .who { color: #b3ab9c; }
            .who strong { color: #d8d2c4; }
            .btn-primary { background: #ede9e0; color: #1c1a17; }
            .btn-secondary { border-color: #4a453c; color: #d8d2c4; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">!</div>
        <h1>Acceso restringido para este usuario</h1>

        @if($user)
            <p>Tu cuenta no tiene permiso para entrar a esta sección del sistema.</p>
            <p class="who">Sesión iniciada como <strong>{{ $user->name }}</strong>@if($user->email) &lt;{{ $user->email }}&gt;@endif</p>
            <div class="actions">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Cambiar de usuario</button>
                </form>
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('login') }}" class="btn btn-secondary">Volver</a>
            </div>
        @else
            <p>Necesitas iniciar sesión con una cuenta autorizada para ver esta página.</p>
            <div class="actions">
                <a href="{{ route('login') }}" class="btn btn-primary">Iniciar sesión</a>
            </div>
        @endif
    </div>
</body>
</html>
