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
     * Llama a la API sin condición — usar `ensureCalendarSharedWith()` en bucles del cron.
     */
    public function shareCalendarWithEmail(string $calendarId, string $email): bool;

    /**
     * Como `shareCalendarWithEmail()` pero SOLO llama a la API si el email no figura ya
     * en la ACL del calendario. Lee la lista de ACL una vez por calendario (cacheada por
     * la vida del objeto) para no repetir `acl.insert` en cada corrida del cron — Google
     * limita fuerte las operaciones de ACL (403 "Calendar usage limits exceeded").
     */
    public function ensureCalendarSharedWith(string $calendarId, string $email): bool;

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
