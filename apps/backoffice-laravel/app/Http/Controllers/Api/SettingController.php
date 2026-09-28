<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\ValidPhoneNumber;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Support\Branches\BranchResolver;
use App\Support\SystemSettings\BusinessHours;

class SettingController extends Controller
{
    public function booking(SystemSettings $settings, BusinessHours $businessHours): JsonResponse
    {
        $all = $settings->all();
        // B2: la cuadrícula de horarios del móvil usa el horario de la sucursal de quien agenda.
        $hours = $businessHours->for(BranchResolver::forNewBooking(null));

        return response()->json([
            'grace_minutes' => (int) ($all['booking_grace_minutes'] ?? 15),
            'opening_time' => $hours->openingTime(),
            'closing_time' => $hours->closingTime(),
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
