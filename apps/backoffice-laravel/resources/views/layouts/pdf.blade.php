<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Documento EstetiCAN')</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.5;
        }

        @page {
            size: letter;
            margin: 1.5cm;
        }

        table.header-table {
            width: 100%;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        table.header-table td { vertical-align: top; }
        .logo { max-height: 60px; }
        .business-name { font-size: 18px; font-weight: bold; color: #2c3e50; margin: 0; }
        .document-type { font-size: 16px; font-weight: bold; color: #3498db; text-transform: uppercase; margin: 0; text-align: right; }
        .document-date { color: #7f8c8d; text-align: right; margin-top: 2px; }

        table.info-table { width: 100%; margin-bottom: 20px; }
        table.info-table td { width: 50%; vertical-align: top; padding: 8px; background: #f8f9fa; border: 1px solid #ecf0f1; }
        .info-title { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #7f8c8d; border-bottom: 1px solid #ddd; padding-bottom: 2px; margin-bottom: 4px; }

        h3.section-title { font-size: 12px; color: #2c3e50; border-bottom: 1px solid #ecf0f1; padding-bottom: 4px; margin-top: 22px; margin-bottom: 8px; }

        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data-table th { background: #2c3e50; color: #fff; text-align: left; padding: 5px 8px; font-size: 9px; text-transform: uppercase; }
        table.data-table td { padding: 6px 8px; border-bottom: 1px solid #ecf0f1; }

        .muted { color: #7f8c8d; }
        .badge { padding: 1px 5px; border-radius: 3px; font-size: 9px; background: #eee; }

        table.signature-table { width: 100%; margin-top: 50px; }
        table.signature-table td { width: 50%; text-align: center; padding-top: 5px; border-top: 1px solid #333; font-size: 10px; }

        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #7f8c8d; border-top: 1px solid #ecf0f1; padding-top: 10px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                @if($logoPath ?? null)
                    <img src="{{ $logoPath }}" class="logo"><br>
                @endif
                <p class="business-name">{{ $businessName ?? 'EstetiCAN' }}</p>
            </td>
            <td style="width: 40%;">
                <p class="document-type">@yield('document-type', 'Documento')</p>
                <p class="document-date">{{ now()->format('d/m/Y H:i') }}</p>
            </td>
        </tr>
    </table>

    @yield('content')

    <div class="footer">
        <p>Documento generado por el sistema EstetiCAN el {{ now()->format('d/m/Y H:i') }}.</p>
    </div>
</body>
</html>
