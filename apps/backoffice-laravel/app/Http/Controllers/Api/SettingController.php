<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\ValidPhoneNumber;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function booking(SystemSettings $settings): JsonResponse
    {
        $all = $settings->all();

        return response()->json([
            'grace_minutes' => (int) ($all['booking_grace_minutes'] ?? 15),
            'opening_time' => (string) ($all['booking_opening_time'] ?? '09:00'),
            'closing_time' => (string) ($all['booking_closing_time'] ?? '19:00'),
        ]);
    }

    public function photos(SystemSettings $settings): JsonResponse
    {
        $all = $settings->all();

        return response()->json([
            'watermark_enabled' => (bool) ($all['photo_watermark_enabled'] ?? false),
        ]);
    }

    public function branding(SystemSettings $settings): JsonResponse
    {
        $all = $settings->all();

        $logo = $all['brand_logo_web'] ?? null;
        $favicon = $all['brand_favicon'] ?? null;

        return response()->json([
            'business_name' => (string) ($all['brand_business_name'] ?? 'EstetiCAN'),
            // Rutas relativas (`/storage/...`) — el nginx de la app móvil ya proxya
            // `/storage/` al backend, así que sirven igual desde la app del operador.
            'logo_url' => $logo ? Storage::disk('public')->url($logo) : null,
            'favicon_url' => $favicon
                ? Storage::disk('public')->url($favicon)
                : ($logo ? Storage::disk('public')->url($logo) : null),
        ]);
    }

    public function phoneFormat(SystemSettings $settings): JsonResponse
    {
        $rule = ValidPhoneNumber::fromSettings($settings);

        return response()->json([
            'allow_country_code' => (bool) ($settings->all()['commercial_clients_phone_allow_country_code'] ?? false),
            'min_digits' => $rule->minDigits,
            'max_digits' => $rule->maxDigits,
        ]);
    }
}
