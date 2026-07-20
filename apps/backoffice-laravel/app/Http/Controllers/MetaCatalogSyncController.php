<?php

namespace App\Http\Controllers;

use App\Domain\MetaCatalog\Contracts\MetaCatalogSyncServiceInterface;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\RedirectResponse;

class MetaCatalogSyncController extends Controller
{
    public function store(SystemSettings $settings, MetaCatalogSyncServiceInterface $sync): RedirectResponse
    {
        $all = $settings->all();

        if (! $all['whatsapp_catalog_id'] || ! $all['whatsapp_catalog_access_token']) {
            return redirect()->route('items.index')
                ->with('error', 'Configura el Catalog ID y el Access Token en Configuración → Catálogo de WhatsApp/Meta antes de sincronizar.');
        }

        $result = $sync->sync();

        $summary = count($result['published']).' publicado(s)';

        if ($result['skipped'] !== []) {
            $summary .= ', '.count($result['skipped']).' omitido(s) ('.implode(', ', array_column($result['skipped'], 'reason')).')';
        }

        if ($result['failed'] !== []) {
            $summary .= ', '.count($result['failed']).' con error';

            return redirect()->route('items.index')->with('error', 'Sincronización con errores: '.$summary.'. Ver logs para el detalle de Meta.');
        }

        return redirect()->route('items.index')->with('success', 'Sincronización completa: '.$summary.'.');
    }
}
