{{-- Variante PDF de `layouts.report` (A1, 27/09/2026): mismas clases que usan las vistas de
     `reports/*`, pero con CSS que dompdf sí entiende — sin variables CSS, flexbox ni grid (se
     reemplazan por tablas), sin el botón de imprimir ni su <script>. Las vistas eligen el layout
     con `$asPdf`, así el contenido de cada documento vive en un solo lugar. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Reporte EstetiCAN')</title>
    <style>
        @page { size: letter; margin: 1.5cm; }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.45;
            margin: 0;
        }

        .container { width: 100%; }

        /* Encabezado: dos columnas con tabla (dompdf no soporta flexbox) */
        .report-header {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 12px;
        }
        .report-header > .brand-box, .report-header > .document-info { display: table-cell; vertical-align: top; width: 50%; }
        .document-info { text-align: right; }
        .logo { max-height: 60px; margin-bottom: 8px; }
        .business-name { font-size: 18px; font-weight: bold; color: #2c3e50; margin: 0; }
        .fiscal-data { font-size: 9px; color: #7f8c8d; margin-top: 4px; }
        .document-type { font-size: 16px; font-weight: bold; color: #3498db; text-transform: uppercase; margin: 0; }
        .document-number { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .document-date { color: #7f8c8d; margin-top: 2px; }

        /* Cajas de información: dos celdas (dompdf no soporta grid) */
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 0 -10px 24px -10px;
        }
        /* Solo es celda dentro de .info-grid: dompdf no admite una celda sin su tabla madre, y la
           orden de trabajo usa .info-box también suelta. */
        .info-grid > .info-box { display: table-cell; width: 50%; vertical-align: top; }
        .info-box {
            margin-bottom: 12px;
            background: #f8f9fa;
            padding: 10px;
            border: 1px solid #ecf0f1;
        }
        .info-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #7f8c8d;
            margin-bottom: 4px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
        }
        .info-content { font-size: 11px; }
        .info-row { margin-bottom: 2px; }
        .info-label { font-weight: bold; display: inline-block; width: 80px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th {
            background: #2c3e50;
            color: #fff;
            text-align: left;
            padding: 7px 10px;
            text-transform: uppercase;
            font-size: 9px;
        }
        td { padding: 8px 10px; border-bottom: 1px solid #ecf0f1; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Totales alineados a la derecha, cada renglón en dos celdas */
        .totals-wrapper { text-align: right; }
        .totals-box { display: inline-block; width: 250px; text-align: left; }
        .total-row { display: table; width: 100%; padding: 4px 0; border-bottom: 1px solid #ecf0f1; }
        .total-row > span { display: table-cell; }
        .total-row > span:last-child { text-align: right; }
        .total-row.grand-total { font-size: 14px; font-weight: bold; color: #2c3e50; border-bottom: 2px solid #2c3e50; }

        .report-footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9px;
            color: #7f8c8d;
            border-top: 1px solid #ecf0f1;
            padding-top: 16px;
        }

        .signature-box { display: table; width: 100%; margin-top: 50px; }
        .signature-box > .signature-line { display: table-cell; }
        .signature-line {
            width: 40%;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 5px;
        }

        .badge { padding: 2px 6px; font-size: 9px; background: #eee; }
        .no-print { display: none; }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')

        <div class="report-footer">
            <p>{{ $settings['fiscal']['fiscal_report_footer'] ?? 'Gracias por su preferencia.' }}</p>
            <p>{{ $settings['branding']['brand_business_name'] ?? 'EstetiCAN' }} | {{ $settings['branding']['brand_url'] ?? '' }}</p>
        </div>
    </div>
</body>
</html>
