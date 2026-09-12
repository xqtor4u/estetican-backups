<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SYNC-105 (portado desde Zeus/ZEUS-037): `QuoteItem::name()`/`unitPrice()` leían nombre y
     * precio en vivo del catálogo cuando no había un `price_override` explícito — un presupuesto
     * podía reimprimirse o aceptarse días después mostrando precios distintos a los que se le
     * cotizaron al cliente, y el recibo de pago heredaba el mismo hueco. Mismo patrón de
     * congelado que `executed_service_items`.
     */
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('name_snapshot')->nullable()->after('item_id');
            $table->text('description_snapshot')->nullable()->after('name_snapshot');
        });

        // Backfill best-effort para filas ya existentes: no se puede recuperar el precio/nombre
        // real que se le mostró al cliente en su momento, así que se usa el dato vivo actual del
        // catálogo como aproximación — deja de empeorar hacia adelante, no corrige el pasado.
        $rows = DB::table('quote_items')
            ->leftJoin('services', 'services.id', '=', 'quote_items.service_id')
            ->leftJoin('items', 'items.id', '=', 'quote_items.item_id')
            ->select(
                'quote_items.id',
                'quote_items.price_override',
                'services.name as service_name',
                'services.description as service_description',
                'services.price as service_price',
                'items.name as item_name',
                'items.price as item_price'
            )
            ->get();

        foreach ($rows as $row) {
            DB::table('quote_items')->where('id', $row->id)->update([
                'name_snapshot' => $row->item_name ?? $row->service_name,
                'description_snapshot' => $row->service_description,
                'price_override' => $row->price_override ?? ($row->item_price ?? $row->service_price ?? 0),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn(['name_snapshot', 'description_snapshot']);
        });
    }
};
