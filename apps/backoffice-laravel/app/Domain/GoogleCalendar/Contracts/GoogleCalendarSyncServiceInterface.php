<?php

namespace App\Domain\GoogleCalendar\Contracts;

use App\Models\Operator;
use App\Models\SpaBooking;

interface GoogleCalendarSyncServiceInterface
{
    /**
     * Crea (si no existe) el calendario de Google del operador y persiste su id.
     * Devuelve null si no se pudo (credenciales no configuradas, fallo de API).
     */
    public function ensureCalendarForOperator(Operator $operator): ?string;

    /**
     * Comparte el calendario con el email dado, rol lector. Devuelve false si no se pudo.
     */
    public function shareCalendarWithEmail(string $calendarId, string $email): bool;

    /**
     * Crea o actualiza el evento de Google correspondiente a la cita, en el calendario
     * del operador asignado. No hace nada si el operador no tiene calendario todavía.
     */
    public function upsertBookingEvent(SpaBooking $booking): void;

    /**
     * Borra el evento de Google de la cita (si existe) — usado cuando la cita se cancela
     * o queda como no showed.
     */
    public function deleteBookingEvent(SpaBooking $booking): void;
}
