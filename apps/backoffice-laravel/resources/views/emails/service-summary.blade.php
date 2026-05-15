<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f8f9fa; padding-bottom: 20px; }
        .logo { max-height: 80px; margin-bottom: 10px; }
        .content { margin-bottom: 30px; }
        .service-item { padding: 10px; background: #f8f9fa; margin-bottom: 5px; border-radius: 5px; }
        .footer { font-size: 12px; color: #777; text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }
        .signature { margin-top: 20px; font-style: italic; }
        .price-box { float: right; font-weight: bold; }
        .total-row { font-size: 18px; font-weight: bold; margin-top: 20px; text-align: right; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; background: #e9ecef; color: #495057; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($settings['branding']['brand_logo_web'] ?? null)
                <img src="{{ config('app.url') . Storage::disk('public')->url($settings['branding']['brand_logo_web']) }}" class="logo">
            @endif
            <h2>{{ $settings['branding']['brand_business_name'] ?? 'EstetiCAN' }}</h2>
            <p>{{ $settings['fiscal']['fiscal_business_name'] ?? '' }}</p>
        </div>

        <div class="content">
            <p>Estimado/a <strong>{{ $booking->pet->client->full_name }}</strong>,</p>
            <p>Es un gusto saludarte. Te compartimos el resumen del servicio profesional realizado el día de hoy para <strong>{{ $booking->pet->name }}</strong>.</p>

            <h3 style="color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 5px;">Resumen de Atención</h3>
            
            @php($acceptedQuote = $booking->quotes->firstWhere('status', 'accepted'))
            @if($acceptedQuote)
                @foreach($acceptedQuote->items as $item)
                    <div class="service-item">
                        <span class="price-box">${{ number_format($item->price_override ?? $item->service->price, 2) }}</span>
                        <strong>{{ $item->service->name }}</strong>
                        @if($item->operator)
                            <br><small>Por: {{ $item->operator->full_name }} ({{ $item->operator->specialty }})</small>
                        @endif
                    </div>
                @endforeach
                
                <div class="total-row">
                    Total: ${{ number_format($acceptedQuote->total_amount, 2) }}
                </div>
            @endif

            @if($booking->notes)
                <div style="margin-top: 20px; padding: 15px; border-left: 4px solid #3498db; background: #ebf5fb;">
                    <strong>Observaciones del especialista:</strong><br>
                    {{ $booking->notes }}
                </div>
            @endif
        </div>

        <div class="footer">
            <p>{{ $settings['branding']['brand_address'] ?? '' }}</p>
            <p>{{ $settings['fiscal']['fiscal_rfc'] ?? '' }} | {{ $settings['branding']['brand_phone'] ?? '' }}</p>
            <div class="signature">
                {{ $settings['operational']['operational_email_signature_text'] ?? 'Atentamente, el equipo de EstetiCAN' }}
            </div>
            @if($settings['branding']['brand_url'] ?? null)
                <p><a href="{{ $settings['branding']['brand_url'] }}" style="color: #3498db;">{{ $settings['branding']['brand_url'] }}</a></p>
            @endif
        </div>
    </div>
</body>
</html>
