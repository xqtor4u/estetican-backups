<?php

namespace App\Support\Pages;

abstract class BasePage
{
    /**
     * @return array<string, string>
     */
    protected static function header(string $eyebrow, string $title, string $subtitle): array
    {
        return [
            'eyebrow' => $eyebrow,
            'title' => $title,
            'subtitle' => $subtitle,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function page(array $breadcrumbs, array $header, string $screenId): array
    {
        return [
            'breadcrumbs' => $breadcrumbs,
            'header' => $header,
            'screen_id' => $screenId,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function home(): array
    {
        return ['label' => 'Inicio', 'url' => route('home')];
    }
}