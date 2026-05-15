# Extracto del manual: Contextos principales / módulos y base de datos

## Módulos funcionales del negocio

| Módulo | Responsabilidad | Pantallas mínimas | API mínima |
|---|---|---|---|
| Leads | Registrar origen de campañas, WhatsApp, llamada o formulario | Bandeja, detalle, conversión | `/leads`, `/leads/{id}` |
| Clientes | Dueño, contacto, notas y consentimiento | Listado, ficha, historial | `/clients` |
| Mascotas | Perfil, raza, peso, temperamento, vacunas, fotos, alertas | Listado, ficha, historial | `/pets` |
| Citas SPA | Agenda por servicio, groomer y franja | Calendario, detalle, reprogramación | `/spa-bookings` |
| Reservas hotel | Reserva planeada y cupo estimado | Calendario, tablero de ocupación | `/hotel-reservations` |
| Estancias | Check-in real, check-out real, ocupación efectiva | Ingreso, salida, tablero vivo | `/stays` |
| Estados | Timeline operativo: recibido, baño, corte, listo, etc. | Panel rápido, detalle | `/service-status` |
| Notificaciones | Recordatorios y logs de envío | Plantillas, cola, historial | `/notifications` |

## Contextos / dominios del backoffice Laravel

- Leads
- Clients
- Pets
- SpaBookings
- HotelReservations
- Stays
- ServiceStatus
- Notifications
- Users
- Reports

## Modelo de datos base

La base debe distinguir entre lo reservado y lo realmente ejecutado. Esa diferencia evita errores en hotel y agenda.

- Una reserva de hotel puede cancelarse sin generar estancia.
- Una estancia puede extenderse sin cambiar la reserva original, pero debe quedar registrada.
- Los recordatorios se calculan desde reserva o estancia según el caso.
- Los estados operativos se guardan como bitácora; no solo como un campo actual.

## Tablas base sugeridas

| Tabla | Propósito | Claves principales | Notas |
|---|---|---|---|
| `clients` | Dueños y contactos | `id`, `phone`, `email` | Puede tener varias mascotas |
| `pets` | Ficha de mascota | `id`, `client_id` | Incluye alertas y atributos clínicos básicos |
| `services` | Catálogo SPA/hotel | `id`, `type` | Baño, corte, hotel, combo, extra |
| `spa_bookings` | Reserva planeada de SPA | `id`, `pet_id`, `service_id`, `scheduled_at` | No equivale a servicio realizado |
| `hotel_reservations` | Reserva planeada del hotel | `id`, `pet_id`, `start_at`, `end_at` | Consume cupo planeado |
| `stays` | Ocupación real | `id`, `pet_id`, `check_in_at`, `check_out_at` | Consume cupo real |
| `service_status_logs` | Evolución del servicio | `id`, `booking_id` | Timeline y auditoría |
| `notification_queue` | Mensajes pendientes | `id`, `channel`, `send_at` | Preparado para WhatsApp/SMS/email |
| `campaign_sources` | Origen del lead | `id`, `source`, `medium` | SEO, Meta Ads, orgánico, llamada |
| `audit_logs` | Trazabilidad | `id`, `actor_id`, `action` | Indispensable en cambios de estado |

## Idea estructural central

El manual separa claramente:

- **Captación**: Leads y campaign sources
- **Relación comercial**: Clients
- **Entidad operativa principal**: Pets
- **Planeación**: Spa bookings y hotel reservations
- **Ejecución real**: Stays
- **Seguimiento operacional**: Service status logs
- **Comunicación**: Notification queue
- **Auditoría**: Audit logs
