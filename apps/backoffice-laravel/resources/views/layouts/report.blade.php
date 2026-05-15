<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Reporte EstetiCAN')</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #7f8c8d;
            --accent-color: #3498db;
            --border-color: #ecf0f1;
            --text-color: #333;
            --bg-light: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: var(--text-color);
            line-height: 1.5;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        @page {
            size: letter;
            margin: 1.5cm;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            .container { width: 100% !important; max-width: none !important; border: none !important; box-shadow: none !important; }
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Encabezado */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
        }

        .brand-box { flex: 1; }
        .logo { max-height: 70px; margin-bottom: 10px; }
        .business-name { font-size: 20px; font-weight: bold; color: var(--primary-color); margin: 0; }
        .fiscal-data { font-size: 10px; color: var(--secondary-color); margin-top: 5px; }

        .document-info {
            text-align: right;
            flex: 1;
        }
        .document-type { font-size: 18px; font-weight: bold; color: var(--accent-color); text-transform: uppercase; margin: 0; }
        .document-number { font-size: 14px; font-weight: bold; margin-top: 5px; }
        .document-date { color: var(--secondary-color); margin-top: 2px; }

        /* Grids de información */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            background: var(--bg-light);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .info-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: var(--secondary-color);
            margin-bottom: 5px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
        }

        .info-content { font-size: 12px; }
        .info-row { display: flex; margin-bottom: 2px; }
        .info-label { font-weight: bold; width: 80px; }

        /* Tablas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th {
            background: var(--primary-color);
            color: #fff;
            text-align: left;
            padding: 8px 12px;
            text-transform: uppercase;
            font-size: 10px;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Totales */
        .totals-wrapper {
            display: flex;
            justify-content: flex-end;
        }

        .totals-box {
            width: 250px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .total-row.grand-total {
            font-size: 16px;
            font-weight: bold;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            margin-top: 5px;
        }

        /* Footer */
        .report-footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: var(--secondary-color);
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }

        .signature-box {
            margin-top: 60px;
            display: flex;
            justify-content: space-around;
        }

        .signature-line {
            width: 200px;
            border-top: 1px solid var(--text-color);
            text-align: center;
            padding-top: 5px;
        }

        /* Utilidades */
        .badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            background: #eee;
        }
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

    {{-- Botón flotante para imprimir en pantalla --}}
    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 50px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            Imprimir Documento
        </button>
    </div>
</body>
</html>
