<?php

namespace App\Domain\MetaCatalog\Services;

use App\Domain\MetaCatalog\Contracts\MetaCatalogSyncServiceInterface;
use App\Models\Item;
use App\Support\SystemSettings\SystemSettings;
use App\Support\WhatsApp\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sube/actualiza artículos hacia el catálogo de Meta Commerce Manager vía la
 * Graph API de productos (`POST /{catalog_id}/products`). Meta identifica cada
 * producto por `retailer_id` (asignado por nosotros, determinístico: "item-{id}")
 * y hace upsert sobre él — no hace falta guardar el ID que Meta le asigna internamente.
 *
 * `price` verificado contra la API real el 20/07/2026: a diferencia del feed CSV/batch de
 * Meta (que usa un string "9.99 USD"), este endpoint de creación individual de producto
 * exige un entero en la unidad mínima de la moneda (centavos) — el string devolvía
 * "(#100) Param price must be a number".
 *
 * `category`/`color`/`retailer_product_group_id` (BL-052b) verificados contra la API real
 * el 20/07/2026 con una sola variante real (#10, "ID TAG 38mm — Negro") — Meta aceptó y
 * devolvió los 3 campos tal cual se mandaron, incluyendo el string de categoría con "&"
 * literal (formato de la taxonomía de Google). **No verificado todavía:** el comportamiento
 * de agrupación visible en Commerce Manager con 2+ variantes reales compartiendo
 * `retailer_product_group_id` — pendiente hasta que existan más colores reales del mismo
 * producto (ver BACKLOG BL-052b).
 */
class MetaCatalogSyncService implements MetaCatalogSyncServiceInterface
{
    private const GRAPH_VERSION = 'v21.0';

    public function __construct(private SystemSettings $settings) {}

    public function sync(): array
    {
        $all = $this->settings->all();
        $catalogId = $all['whatsapp_catalog_id'];
        $accessToken = $all['whatsapp_catalog_access_token'];
        $currency = $all['finanzas_moneda'] ?? 'MXN';
        $waNumber = PhoneNormalizer::toWhatsAppNumber($all['brand_whatsapp_number'] ?? null);

        $published = [];
        $skipped = [];
        $failed = [];

        $items = Item::where('is_active', true)
            ->where('ai_visible', true)
            ->where('stock_quantity', '>', 0)
            ->get();

        foreach ($items as $item) {
            if (! $item->photo_url) {
                $skipped[] = ['item' => $item->name, 'reason' => 'sin foto'];

                continue;
            }

            if ($item->price === null) {
                $skipped[] = ['item' => $item->name, 'reason' => 'sin precio'];

                continue;
            }

            $response = Http::withToken($accessToken)->post(
                'https://graph.facebook.com/'.self::GRAPH_VERSION."/{$catalogId}/products",
                $this->buildPayload($item, $currency, $waNumber)
            );

            if ($response->failed()) {
                $reason = $response->json('error.message') ?? $response->body();
                Log::error('Fallo al publicar artículo en el catálogo de Meta', [
                    'item_id' => $item->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $failed[] = ['item' => $item->name, 'reason' => $reason];

                continue;
            }

            $published[] = $item->name;
        }

        return ['published' => $published, 'skipped' => $skipped, 'failed' => $failed];
    }

    /** @return array<string, string|int> */
    private function buildPayload(Item $item, string $currency, ?string $waNumber): array
    {
        $description = collect([$item->brand, $item->presentation])->filter()->implode(' — ');
        $productUrl = $waNumber
            ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode("Hola, me interesa: {$item->name}")
            : '';

        $payload = [
            'retailer_id' => "item-{$item->id}",
            'name' => $item->name,
            'description' => $description !== '' ? $description : $item->name,
            'availability' => 'in stock',
            'condition' => 'new',
            'price' => (int) round(((float) $item->price) * 100),
            'currency' => $currency,
            'image_url' => $item->photo_url,
            'url' => $productUrl,
        ];

        if ($item->meta_category) {
            $payload['category'] = $item->meta_category;
        }

        // Mandar uno sin el otro sería un grupo de variante sin atributo que lo distinga —
        // no tiene sentido para Meta. El artículo se publica igual, solo sin agrupar.
        if ($item->meta_variant_group && $item->meta_color) {
            $payload['retailer_product_group_id'] = $item->meta_variant_group;
            $payload['color'] = $item->meta_color;
        }

        return $payload;
    }
}
