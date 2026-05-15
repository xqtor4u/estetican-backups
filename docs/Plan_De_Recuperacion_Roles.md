# Bitácora de Instalación y Plan de Recuperación de Desastres (Fail Safe)

**Fecha de Ejecución:** 13 de Abril de 2026, 14:42 HRS
**Operación:** Migración hacia el esquema dinámico de Roles y Permisos (Spatie Laravel Permission).

## Estado Previo ("El Antes")
Antes de la instalación de `spatie/laravel-permission`, el sistema operaba bajo un modelo de confianza, sin restricciones granuladas. Cualquier usuario autenticado tenía acceso mayoritario a todos los controladores (Crear/Eliminar Clientes, Citas, Mascotas). Las únicas restricciones existían en código (ancladas a `App\Policies\UserPolicy`) para que los usuarios genéricos no pudiesen borrar o editar otros Administradores basándose en una columna estática de rol (`$this->role === 'admin'`).

Debido a que esta migración altera el modelo base `User.php` e inyecta al menos 5 nuevas tablas a la base de datos (roles, permissions, model_has_roles, etc.), se creó un Respaldo de Transición (Failover).

---

## 🛑 Plan de Recuperación ("Cómo revertir")

Si la instalación falla o se corrompe el sistema durante las modificaciones, se debe proceder exactamente en estos pasos para devolver el sistema a su estado intacto "Previo-Spatie":

### Ubicación del Respaldo
Todo el respaldo se almacena dentro de Linux/WSL en la ruta:
`/home/tomas/EstetiCAN_2/backups/failover_pre_spatie/`

### Recuperación Paso a Paso (Desde WSL)

1. **Detener Contenedores (Precaución)**
   Asegúrate de bajar el sistema temporalmente para evitar escrituras sucias o interferencias.
   ```bash
   cd /home/tomas/EstetiCAN_2
   ./apagar_backoffice.sh
   ```

2. **Restaurar el Código Fuente (Archivos y Configuraciones)**
   Borraremos la carpeta actual corrompida de código y la reemplazaremos por el Zip intocable.
   ```bash
   cd /home/tomas/EstetiCAN_2
   rm -rf apps/backoffice-laravel
   tar -xzf backups/failover_pre_spatie/source_code.tar.gz
   ```

3. **Restaurar la Base de Datos (Volcado)**
   Subimos los contenedores, purgamos la base actual y le inyectamos la foto limpia (SQL).
   ```bash
   # Volvemos a encender solo la infraestructura
   ./levantar_backoffice.sh 
   
   # Conectamos con MySQL en Docker y quemamos la base actual por el respaldo limpio
   docker exec -i backoffice-laravel-mysql-1 mysql -u root -ppassword laravel < backups/failover_pre_spatie/database_snapshot.sql
   ```

4. **Resultado Final**
Al finalizar estos comandos y recargar en `localhost:8000`, el sistema funcionará 100% igual que en los días anteriores a proponer el modelo perimetral.

---

## 📝 Bitácora de Estabilización (14 de Abril, 2026)

Tras un intento inestable de migración, se procedió a una **reconstrucción controlada** para asegurar el funcionamiento del backoffice con Spatie Laravel Permission.

### Acciones Realizadas:
1.  **Spatie Clean Start**: Se resetearon las tablas de Spatie y se creó un `BaseRolesSeeder` para establecer el rol `admin` con permisos base.
2.  **Hybrid User Model**: Se adaptó el modelo `User.php` para reconocer tanto el campo `role` legacy como los roles de Spatie mediante `is_super_admin`.
3.  **Seguridad de Rutas**: Se implementó protección mediante middleware `role:admin` para Usuarios y Ajustes del Sistema.
4.  **Correcciones de Interfaz & DB**:
    *   Se eliminó la columna inexistente `permisos` (JSON) que causaba errores 500.
    *   Se restauraron rutas funcionales perdidas (Duplicados, Reservas, Citas).
    *   Se habilitó el **Borrador de Mascotas** (CRUD destroy) con confirmación.

### Resultado:
El sistema se encuentra en un estado **operativo, seguro y estable**.

