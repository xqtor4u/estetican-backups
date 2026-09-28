<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
        .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #f8f9fa; padding-bottom: 16px; }
        .footer { font-size: 12px; color: #777; text-align: center; margin-top: 32px; padding-top: 16px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $businessName }}</h2>
            <p>{{ $title }}@if($petName !== '') — {{ $petName }}@endif</p>
        </div>

        <p>Hola{{ $clientName !== '' ? ' '.$clientName : '' }},</p>
        <p>Te compartimos {{ mb_strtolower($title) }}@if($petName !== '') de <strong>{{ $petName }}</strong>@endif. Va adjunto en PDF.</p>
        <p>Si tienes alguna duda, respóndenos a este correo.</p>

        <div class="footer">
            <p>{{ $businessName }}</p>
        </div>
    </div>
</body>
</html>
