<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f8f9fa; padding-bottom: 20px; }
        h3 { margin: 24px 0 4px; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        th { color: #7f8c8d; font-weight: 600; }
        .empty { color: #999; font-size: 13px; }
        .footer { font-size: 12px; color: #777; text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $businessName }}</h2>
            <p>Citas recurrentes del {{ $day->translatedFormat('l d/m/Y') }}</p>
        </div>

        <p>Hola {{ $recipientName }},</p>

        <h3>Atendidas ({{ $report['attended']->count() }})</h3>
        @if($report['attended']->isEmpty())
            <p class="empty">Ninguna.</p>
        @else
            <table>
                <tr><th>Hora</th><th>Mascota</th><th>Operador</th><th>La fijó</th></tr>
                @foreach($report['attended'] as $b)
                    <tr>
                        <td>{{ $b->scheduled_at->format('H:i') }}</td>
                        <td>{{ $b->pet?->name ?? '—' }}</td>
                        <td>{{ $b->operator?->full_name ?? '—' }}</td>
                        <td>{{ $b->seriesConfirmedBy?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @foreach([
            'pinned' => 'Fijadas hoy (convertidas en cita real)',
            'discarded' => 'Descartadas ("no se atenderá")',
            'expired' => 'Perdidas: nadie las fijó y se borraron',
        ] as $key => $title)
            <h3>{{ $title }} ({{ $report[$key]->count() }})</h3>
            @if($report[$key]->isEmpty())
                <p class="empty">Ninguna.</p>
            @else
                <table>
                    <tr><th>Cita</th><th>Mascota</th>@unless($key === 'expired')<th>Quién</th>@endunless</tr>
                    @foreach($report[$key] as $e)
                        <tr>
                            <td>{{ $e->scheduled_at?->format('d/m H:i') ?? '—' }}</td>
                            <td>{{ $e->series?->pet?->name ?? '—' }}</td>
                            @unless($key === 'expired')<td>{{ $e->user?->name ?? '—' }}@if($e->details) — {{ $e->details }}@endif</td>@endunless
                        </tr>
                    @endforeach
                </table>
            @endif
        @endforeach

        <p style="margin-top: 20px;">Series y vigencias en <a href="{{ $appUrl }}/agenda/series">{{ $appUrl }}/agenda/series</a>.</p>

        <div class="footer">
            <p>Recibes este correo porque tienes activado "Reporte diario de citas recurrentes" en tu usuario. Para dejar de recibirlo, desactívalo ahí.</p>
        </div>
    </div>
</body>
</html>
