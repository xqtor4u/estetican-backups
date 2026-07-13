<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
        .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #f8f9fa; padding-bottom: 20px; }
        .logo { max-height: 80px; margin-bottom: 10px; }
        .content { margin-bottom: 24px; white-space: pre-wrap; }
        .cta { text-align: center; margin: 24px 0; }
        .cta a {
            display: inline-block;
            padding: 12px 24px;
            background: #25D366;
            color: #ffffff;
            text-decoration: none;
            border-radius: 24px;
            font-weight: bold;
        }
        .footer { font-size: 12px; color: #777; text-align: center; margin-top: 32px; padding-top: 16px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($settings['brand_logo_web'] ?? null)
                <img src="{{ config('app.url') . Storage::disk('public')->url($settings['brand_logo_web']) }}" class="logo">
            @endif
            <h2>{{ $settings['brand_business_name'] ?? 'EstetiCAN' }}</h2>
        </div>

        <div class="content">{{ $messageBody }}</div>

        @if($settings['brand_whatsapp_number'] ?? null)
            <div class="cta">
                <a href="https://wa.me/{{ $settings['brand_whatsapp_number'] }}">Escríbenos por WhatsApp</a>
            </div>
        @endif

        <div class="footer">
            <p>{{ $settings['brand_business_name'] ?? 'EstetiCAN' }}</p>
            @if($preferencesUrl ?? null)
                <p><a href="{{ $preferencesUrl }}" style="color: #777;">Gestionar mis preferencias de comunicación</a></p>
            @endif
        </div>
    </div>
</body>
</html>
