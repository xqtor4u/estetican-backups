<?php

namespace App\Support\Pages;

use App\Models\User;

class UsersPage extends BasePage
{
    /**
     * Metadata for the users list page (USEIND)
     */
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Usuarios', 'current' => true]],
            static::header('Administración de sistema', 'Listado de usuarios', 'Gestión de acceso al backoffice, roles administrativos y personal operativo fusionado.'),
            'USeInd',
        );
    }

    /**
     * Metadata for a single user detail page (USESHO)
     */
    public static function show(User $user): array
    {
        $name = ($user->first_name || $user->last_name) 
            ? trim($user->first_name . ' ' . $user->last_name) 
            : $user->name;

        return static::page(
            [static::home(), ['label' => 'Usuarios', 'url' => route('users.index')], ['label' => $name, 'current' => true]],
            static::header('Ficha de usuario', $name, 'Detalle de identidad, permisos de acceso y perfil operativo del personal.'),
            'USeSho',
        );
    }

    /**
     * Metadata for creating a new user (USECRE)
     */
    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Usuarios', 'url' => route('users.index')], ['label' => 'Nuevo usuario', 'current' => true]],
            static::header('Alta de personal', 'Crear nuevo usuario', 'Registra identidad base y define permisos de acceso al backoffice.'),
            'USeCre',
        );
    }

    /**
     * Metadata for editing a user (USEEDI)
     */
    public static function edit(User $user): array
    {
        $name = ($user->first_name || $user->last_name) 
            ? trim($user->first_name . ' ' . $user->last_name) 
            : $user->name;

        return static::page(
            [static::home(), ['label' => 'Usuarios', 'url' => route('users.index')], ['label' => $name, 'url' => route('users.show', $user)], ['label' => 'Editar', 'current' => true]],
            static::header('Actualización de datos', 'Editar perfil', 'Modifica información de contacto, credenciales o tipos de operador del usuario.'),
            'USeEdi',
        );
    }
}
