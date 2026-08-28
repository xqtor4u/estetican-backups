<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f8f9fa; padding-bottom: 20px; }
        .content { margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        th { color: #7f8c8d; font-weight: 600; }
        .tag { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .tag-nueva { background: #e8f5e9; color: #2e7d32; }
        .tag-actualizada { background: #e3f2fd; color: #1565c0; }
        .tag-cancelada { background: #fdecea; color: #c62828; }
        .footer { font-size: 12px; color: #777; text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $businessName }}</h2>
            <p>Tu calendario se actualizó</p>
        </div>

        <div class="content">
            <p>Hola {{ $recipientName }},</p>
            <p>La sincronización con Google Calendar registró {{ count($changes) }} {{ count($changes) === 1 ? 'cambio' : 'cambios' }} en las citas que ves:</p>

            <table>
                <thead>
                    <tr>
                        <th>Cambio</th>
                        <th>Mascota / Servicio</th>
                        <th>Operador</th>
                        <th>Fecha y hora</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($changes as $change)
                        <tr>
                            <td><span class="tag tag-{{ $change['type'] }}">{{ ucfirst($change['type']) }}</span></td>
                            <td>{{ $change['pet'] }} — {{ $change['services'] }}</td>
                            <td>{{ $change['operator'] }}</td>
                            <td>{{ $change['when'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p style="margin-top: 20px;">Los cambios ya están reflejados en tu Google Calendar. Para ver el detalle completo entra a <a href="{{ $appUrl }}">{{ $appUrl }}</a>.</p>
        </div>

        <div class="footer">
            <p>Recibes este correo porque activaste el aviso de cambios de calendario en tu perfil de usuario. Para dejar de recibirlo, desactívalo ahí.</p>
        </div>
    </div>
</body>
</html>
