# 📱 Arquitectura de Base de Datos y Acceso para la App Móvil (EstetiCAN)

La aplicación móvil no debe replicar toda la base de datos del Backoffice de Laravel, sino mantener una **caché local** estructurada (usando SQLite, Room, o CoreData) para permitir una experiencia fluida, rápida y con capacidades *offline*. 

La fuente de la verdad seguirá siendo el **Backoffice en Laravel**.

---

## 🗄️ 1. Estructura de la Base de Datos Local (App Móvil)

La base de datos en el dispositivo móvil debe estructurarse en tablas simplificadas que se sincronizan con la API.

### A. Módulo de Autenticación y Sesión
- **`current_user`**
  - `id` (PK)
  - `name`, `email`, `role` (Client / Operator)
  - `profile_photo_url`
  - `api_token` (Token de acceso seguro)
  - `last_sync_at` (Timestamp para control de caché)

### B. Módulo de Identidad (Pacientes y Clientes)
*Si la app es para operadores, descargará sus clientes del día. Si es para clientes, solo verá su propia información y mascotas.*
- **`clients`** (Caché local)
  - `id` (PK)
  - `first_name`, `last_name`, `email`, `phone`
- **`pets`**
  - `id` (PK)
  - `client_id` (FK)
  - `name`, `species`, `breed`, `weight`
  - `profile_photo_path` (Sincronizado de la DB central)
  - `medical_alerts` (Texto resumen o JSON)

### C. Módulo Operativo (Citas y Agenda)
- **`bookings`** (Combina Spa, Clínica y Hotel para la vista de agenda)
  - `id` (PK)
  - `type` (spa / hotel / clinica)
  - `pet_id` (FK)
  - `operator_id` (FK - Opcional)
  - `date`, `time`, `status` (pending, in_progress, completed)
  - `notes`

### D. Módulo Financiero (Presupuestos y Pagos)
- **`quotes`** (Presupuestos pendientes de aprobación)
  - `id` (PK)
  - `booking_id` (FK)
  - `total_amount`, `advance_required`
  - `status` (draft, accepted, rejected)

---

## 📡 2. ¿Cómo accederá a la Base de Datos? (Capa de Conexión)

La App **NUNCA** se conectará directamente a la base de datos relacional (MySQL/PostgreSQL) del servidor por razones de seguridad. Accederá exclusivamente a través de una **API RESTful construida en Laravel**.

### Arquitectura de Conexión:

1. **Autenticación (Laravel Sanctum):**
   - La app envía las credenciales (`POST /api/login`).
   - El servidor Laravel devuelve un **Token de Acceso** (Token Bearer).
   - La app guarda este token en el Secure Storage del dispositivo (Keychain en iOS, Keystore en Android).
   - En cada petición subsecuente, la app envía este token en las cabeceras HTTP: 
     `Authorization: Bearer 1|TokenGenerado...`

2. **Endpoints Principales (API REST):**
   - `GET /api/user`: Obtiene los datos del usuario logueado.
   - `GET /api/agenda?date=YYYY-MM-DD`: Obtiene las citas asignadas o programadas del día.
   - `GET /api/pets/{id}`: Obtiene el perfil detallado de una mascota.
   - `POST /api/bookings`: Crea o solicita una nueva cita.
   - `POST /api/photos/upload`: Sube una foto de evidencia o de perfil usando *Multipart Form-Data*.

3. **Estrategia de Sincronización (Offline-First):**
   - **Lectura:** La app primero consulta y muestra los datos en la base de datos local (SQLite). En segundo plano, hace un request a la API para buscar datos nuevos, actualiza SQLite y refresca la UI silenciosamente.
   - **Escritura:** Cuando el usuario hace una acción (ej. subir foto o aprobar presupuesto), la app envía la petición a la API. Si no hay conexión a internet, guarda la acción en una tabla local `sync_queue` y la despacha automáticamente en cuanto recupere la conexión.

### Ejemplo de Petición desde la App:
```javascript
// Ejemplo utilizando Fetch/Axios
axios.get('https://api.estetican.com/api/agenda', {
    headers: {
        'Authorization': `Bearer ${localToken}`,
        'Accept': 'application/json'
    },
    params: {
        date: '2026-05-09'
    }
}).then(response => {
    // 1. Guardar o actualizar registros en SQLite local
    // 2. Renderizar la UI con los datos actualizados
});
```
