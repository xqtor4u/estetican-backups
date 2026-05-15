<?php

namespace App\Support\Pages;

use App\Models\Operator;

class OperatorsPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Operadores', 'current' => true]],
            static::header('Catálogo operativo', 'Operadores', 'Personal que ejecuta trabajos sobre la mascota, separado de usuarios y preparado para trazabilidad de desempeño.'),
            'OpeInd',
        );
    }

    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Operadores', 'url' => route('operators.index')], ['label' => 'Nuevo operador', 'current' => true]],
            static::header('Alta operativa', 'Crear operador', 'Registra a la persona que ejecuta trabajos y será referencia para trazabilidad futura de desempeño.'),
            'OpeCre',
        );
    }

    public static function show(Operator $operator): array
    {
        $name = $operator->full_name ?: $operator->name;

        return static::page(
            [static::home(), ['label' => 'Operadores', 'url' => route('operators.index')], ['label' => $name, 'current' => true]],
            static::header('Detalle operativo', $name, 'Ficha base del ejecutor operativo con trazabilidad preliminar de trabajos realizados.'),
            'OpeSho',
        );
    }

    public static function edit(Operator $operator): array
    {
        $name = $operator->full_name ?: $operator->name;

        return static::page(
            [static::home(), ['label' => 'Operadores', 'url' => route('operators.index')], ['label' => $name, 'url' => route('operators.show', $operator)], ['label' => 'Editar', 'current' => true]],
            static::header('Mantenimiento operativo', 'Editar operador', 'Ajusta identidad operativa y contexto del ejecutor sin mezclar todavía autenticación o permisos.'),
            'OpeEdi',
        );
    }
}