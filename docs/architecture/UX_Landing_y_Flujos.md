# Propuesta de UX: Landing y Flujos Operativos

## Objetivo
Diseñar una experiencia de usuario (UX) funcional y eficiente para el backoffice, priorizando accesos rápidos y flujos naturales del negocio.

---

## Landing Page (Inicio)

### Accesos rápidos principales
- **Alta rápida cliente + mascota**
  - Un solo flujo para registrar cliente y su mascota.
  - Al finalizar, opción directa para agendar cita.
- **Nueva cita**
  - Busca cliente y mascota existentes.
  - Si no existen, permite alta rápida antes de agendar.
- **Buscar cliente/mascota**
  - Acceso a ficha, historial y acciones rápidas.
- **Agenda del día**
  - Ver y gestionar citas del día.

### Layout sugerido (ASCII)

```
┌──────────────────────────────────────────────┐
│ [Alta rápida cliente+mascota] [Nueva cita]  │
│ [Buscar cliente/mascota]    [Agenda del día]│
└──────────────────────────────────────────────┘

Resumen: próximas citas, alertas, mascotas en servicio
```

---

## Flujos recomendados

### 1. Alta rápida cliente + mascota
- Formulario combinado: datos de cliente, luego mascota.
- Al guardar, opción de agendar cita inmediatamente.

### 2. Nueva cita
- Paso 1: Buscar cliente (autocompletar o listado).
- Paso 2: Seleccionar mascota del cliente (o dar de alta si no existe).
- Paso 3: Formulario de cita (servicio, fecha, hora, operador, etc.).

### 3. Buscar cliente/mascota
- Buscador global con autocompletado.
- Acceso directo a ficha, historial, agendar cita, editar datos.

### 4. Agenda del día
- Vista de calendario o lista de citas del día.
- Acciones rápidas: ver detalle, reprogramar, cancelar, marcar no-show.

---

## Principios de UX aplicados
- Accesos directos a lo más usado.
- Flujos guiados y sin fricción.
- Mensajes claros y botones destacados.
- Opción de alta rápida en cualquier punto del flujo.
- Resumen visual de lo importante en la home.

---

## Siguientes pasos
- Validar con usuarios reales los flujos propuestos.
- Iterar sobre la landing y los formularios según feedback.
- Documentar cada ajuste relevante en esta página.

---

*Última actualización: 2026-03-28*
