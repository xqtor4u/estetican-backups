<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f8f9fa; padding-bottom: 20px; }
        .content { margin-bottom: 30px; }
        .stat-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .stat-label { color: #7f8c8d; }
        .stat-value { font-weight: bold; }
        .footer { font-size: 12px; color: #777; text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $report['businessName'] }}</h2>
            <p>Métodos de pago — {{ $report['dateFrom'] }} a {{ $report['dateTo'] }}</p>
        </div>

        <div class="content">
            <p>Sucursal: <strong>{{ $report['branchName'] }}</strong></p>

            <div class="stat-row"><span class="stat-label">Total cobrado</span><span class="stat-value">${{ number_format($report['totalCobrado'], 2) }}</span></div>
            @foreach($report['byMethod'] as $group)
                <div class="stat-row"><span class="stat-label">{{ $group['method'] }} ({{ $group['count'] }})</span><span class="stat-value">${{ number_format($group['amount'], 2) }}</span></div>
            @endforeach

            <p style="margin-top: 20px;">El detalle completo va adjunto en PDF.</p>
        </div>

        <div class="footer">
            <p>Generado por {{ $report['generatedBy'] }} el {{ now()->format('d/m/Y H:i') }}.</p>
        </div>
    </div>
</body>
</html>
