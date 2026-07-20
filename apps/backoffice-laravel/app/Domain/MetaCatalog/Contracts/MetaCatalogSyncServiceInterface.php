<?php

namespace App\Domain\MetaCatalog\Contracts;

interface MetaCatalogSyncServiceInterface
{
    /**
     * Publica hacia el catálogo de Meta (Commerce Manager) los `Item` con
     * `is_active && ai_visible && stock_quantity > 0` — mismo filtro que ya usa
     * `ServiceCatalogPromptBuilder` para el asistente de IA.
     *
     * @return array{published: array<int, string>, skipped: array<int, array{item: string, reason: string}>, failed: array<int, array{item: string, reason: string}>}
     */
    public function sync(): array;
}
