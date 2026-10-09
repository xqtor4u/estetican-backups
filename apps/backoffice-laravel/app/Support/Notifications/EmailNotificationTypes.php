<?php

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Avisos por correo que cada usuario puede elegir recibir (`users.email_notifications`, JSON
 * `{clave: bool}`). Se marcan en la ficha del usuario. Un aviso nuevo (respaldos, errores, caja
 * no abierta, cobros no asentados…) se agrega aquí y aparece solo en el formulario.
 */
class EmailNotificationTypes
{
    public const SERIES_DAILY = 'series_daily';

    /** @return array<string, array{label: string, help: string}> */
    public static function all(): array
    {
        return [
            self::SERIES_DAILY => [
                'label' => 'Reporte diario de citas recurrentes',
                'help' => 'Al final del día: citas de series atendidas, fijadas, descartadas y perdidas, y quién lo hizo.',
            ],
        ];
    }

    /** Usuarios activos con ese aviso encendido y un correo al cual mandarlo. */
    public static function recipients(string $type): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where("email_notifications->{$type}", true)
            ->get();
    }
}
