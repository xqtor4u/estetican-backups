<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preferencias de comunicación — {{ $branding['brand_business_name'] ?? 'EstetiCAN' }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; background: #f7f9fb; margin: 0; padding: 24px 16px; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; padding: 24px; border: 1px solid #eee; border-radius: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo { max-height: 64px; margin-bottom: 8px; }
        h1 { font-size: 1.1rem; margin: 0 0 4px; }
        .client-name { color: #777; font-size: 0.9rem; margin-bottom: 20px; }
        .success { background: #e8f8ee; color: #1c6b3c; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; }
        .pref { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .pref:last-of-type { border-bottom: none; }
        .pref input { margin-top: 3px; }
        .pref label { font-size: 0.95rem; }
        button { width: 100%; margin-top: 20px; padding: 12px; background: #2c3e50; color: #fff; border: none; border-radius: 24px; font-weight: bold; font-size: 0.95rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($branding['brand_logo_web'] ?? null)
                <img src="{{ config('app.url') . \Illuminate\Support\Facades\Storage::disk('public')->url($branding['brand_logo_web']) }}" class="logo">
            @endif
            <h1>{{ $branding['brand_business_name'] ?? 'EstetiCAN' }}</h1>
        </div>

        <p class="client-name">Preferencias de comunicación de <strong>{{ $client->full_name }}</strong></p>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ $updateUrl }}">
            @csrf

            <div class="pref">
                <input type="checkbox" id="receives_offers" name="receives_offers" value="1" {{ $client->receives_offers ? 'checked' : '' }}>
                <label for="receives_offers">Ofertas y promociones</label>
            </div>
            <div class="pref">
                <input type="checkbox" id="receives_service_reminders" name="receives_service_reminders" value="1" {{ $client->receives_service_reminders ? 'checked' : '' }}>
                <label for="receives_service_reminders">Recordatorios de servicio (citas próximas, servicios vencidos)</label>
            </div>
            <div class="pref">
                <input type="checkbox" id="receives_job_updates" name="receives_job_updates" value="1" {{ $client->receives_job_updates ? 'checked' : '' }}>
                <label for="receives_job_updates">Estado de trabajo y resúmenes de atención</label>
            </div>
            <div class="pref">
                <input type="checkbox" id="receives_account_statements" name="receives_account_statements" value="1" {{ $client->receives_account_statements ? 'checked' : '' }}>
                <label for="receives_account_statements">Estado de cuenta</label>
            </div>
            <div class="pref">
                <input type="checkbox" id="receives_other_notifications" name="receives_other_notifications" value="1" {{ $client->receives_other_notifications ? 'checked' : '' }}>
                <label for="receives_other_notifications">Otras notificaciones</label>
            </div>

            <button type="submit">Guardar preferencias</button>
        </form>
    </div>
</body>
</html>
