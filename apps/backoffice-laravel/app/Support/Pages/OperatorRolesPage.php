<?php

namespace App\Support\Pages;

use App\Models\OperatorRole;

class OperatorRolesPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Tipos de operador', 'current' => true]],
            static::header('Catálogo canónico', 'Tipos de operador', 'Especialidades operativas controladas para evitar duplicidades semánticas y sostener reglas de asignación confiables.'),
            'OprRolInd',
        );
    }

    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Tipos de operador', 'url' => route('operator-roles.index')], ['label' => 'Nuevo tipo', 'current' => true]],
            static::header('Alta de catálogo', 'Crear tipo de operador', 'Define clave, descripción y tarifa base de la especialidad operativa antes de asignarla a personas.'),
            'OprRolCre',
        );
    }

    public static function show(OperatorRole $operatorRole): array
    {
        return static::page(
            [static::home(), ['label' => 'Tipos de operador', 'url' => route('operator-roles.index')], ['label' => $operatorRole->name, 'current' => true]],
            static::header('Detalle de catálogo', $operatorRole->name, 'Definición controlada de la especialidad operativa usada para asignar operadores sin ambigüedades.'),
            'OprRolSho',
        );
    }

    public static function edit(OperatorRole $operatorRole): array
    {
        return static::page(
            [static::home(), ['label' => 'Tipos de operador', 'url' => route('operator-roles.index')], ['label' => $operatorRole->name, 'url' => route('operator-roles.show', $operatorRole)], ['label' => 'Editar', 'current' => true]],
            static::header('Mantenimiento de catálogo', 'Editar tipo de operador', 'Ajusta el catálogo canónico sin reabrir capturas libres ni duplicar significados operativos.'),
            'OprRolEdi',
        );
    }
}