<?php

namespace App\Support\Pages;

use App\Models\ClinicalVisit;
use App\Models\Pet;

class ClinicalPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Veterinaria', 'current' => true]],
            static::header('Módulo veterinario', 'Veterinaria', 'Expediente clínico por mascota — área de negocio independiente del spa/grooming/hotel.'),
            'CliInd',
        );
    }

    public static function showPet(Pet $pet): array
    {
        return static::page(
            [static::home(), ['label' => 'Veterinaria', 'url' => route('clinical.index')], ['label' => $pet->name, 'current' => true]],
            static::header('Expediente clínico', $pet->name, 'Visitas SOAP, peso, alergias, condiciones y vacunas de esta mascota.'),
            'CliPet',
        );
    }

    public static function createVisit(Pet $pet): array
    {
        return static::page(
            [static::home(), ['label' => 'Veterinaria', 'url' => route('clinical.index')], ['label' => $pet->name, 'url' => route('clinical.pets.show', $pet)], ['label' => 'Nueva visita', 'current' => true]],
            static::header('Expediente clínico', 'Nueva visita', 'Formato SOAP: Subjetivo, Objetivo, Evaluación y Plan.'),
            'CliVisCreate',
        );
    }

    public static function showVisit(ClinicalVisit $visit): array
    {
        return static::page(
            [static::home(), ['label' => 'Veterinaria', 'url' => route('clinical.index')], ['label' => $visit->pet->name, 'url' => route('clinical.pets.show', $visit->pet)], ['label' => 'Visita', 'current' => true]],
            static::header('Expediente clínico', 'Visita #'.$visit->id, $visit->status === 'signed' ? 'Firmada, inmutable.' : 'Borrador.'),
            'CliVisShow',
        );
    }
}
