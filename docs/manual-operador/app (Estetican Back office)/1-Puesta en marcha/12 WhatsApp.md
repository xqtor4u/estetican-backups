---
tags: [manual, app]
---

# 12 WhatsApp

**Para qué sirve:** preparar los mensajes de WhatsApp que tu equipo manda a los clientes: recordatorios, «ya está listo», promociones.

**Antes de empezar:**
- [[01 Datos del negocio]].
- Para los recordatorios de servicio vencido, que tus servicios tengan **Recurrencia (días)** ([[06 Servicios y precios]]).

**Dónde está:** **WhatsApp → Plantillas de mensaje**, **WhatsApp → Bandeja diaria** y **WhatsApp → Recurrencias**.

## Qué existe hoy

| Qué | Cómo funciona |
|---|---|
| **Plantillas de mensaje** | Mensajes ya escritos con datos que se llenan solos: {cliente}, {mascota}, {servicio}… |
| **Bandeja diaria** | Lista de las citas de un día. Eliges a quiénes y les mandas un recordatorio. |
| **Recurrencias** | Mascotas a las que ya se les pasó su servicio periódico (por ejemplo, el baño de cada 30 días). |
| **WhatsApp desde el celular** | El operador manda un mensaje desde la cita ([[09 Avisar por WhatsApp]]). |

En todos los casos **una persona confirma el envío**: se abre WhatsApp con el mensaje ya escrito y alguien toca enviar. Por correo, el mensaje sí sale solo desde el servidor.

## Pasos

### Crear una plantilla
1. En **Plantillas de mensaje**, toca **Nueva plantilla**.
2. Escribe el **Nombre de la plantilla**. Así la ve el operador; por ejemplo «👋🏻 ya estoy listo 🐶».
3. En **Contexto de uso**, elige dónde aparece:
   - **Bandeja diaria (citas del día)**: en la bandeja y en las citas del celular.
   - **Recurrencias (servicio periódico vencido)**: en Recurrencias.
   - **Mensaje directo a cliente (ficha del cliente)**: en la ficha del cliente y en el celular.
   - **General (campaña, oferta de temporada u otro mensaje)**: en todos lados.
4. Escribe el **Mensaje**. Toca las variables para insertarlas.
5. Guarda.

### Mandar recordatorios del día
1. En **Bandeja diaria**, elige el día.
2. En **Plantilla a usar:**, elige la plantilla.
3. En **Enviar por:**, elige **WhatsApp** o **Correo**.
4. Marca a los clientes y toca **Enviar seleccionados**.
5. Para cada uno se abre WhatsApp con el mensaje: envíalo ahí, o toca **Omitir**.

## Efecto en el mob
- Las plantillas de **Bandeja diaria**, **Mensaje directo** y **General** son las que el operador ve al tocar **WhatsApp** en una cita ([[09 Avisar por WhatsApp]]).

## Recordatorios automáticos: todavía no funcionan
En **Configuración del sistema → Mensajería de WhatsApp** hay campos para mandar recordatorios **solos**, sin que nadie confirme, por medio de WhatsApp Business de Meta.

**Hoy no se manda nada automático.** Para que funcione falta:
1. Dar de alta el número en WhatsApp Business de Meta. Ese trámite sigue en proceso.
2. Llenar **Phone Number ID** y **Access Token** con los datos que da Meta.
3. Que Meta apruebe una plantilla y poner su nombre exacto en **Nombre de la plantilla aprobada**, con su **Idioma de la plantilla**.
4. Elegir las **Horas de anticipación del recordatorio** (hoy: 24).
5. Prender **Activar recordatorios automáticos**. Hoy está apagado.

## Qué vas a ver
- En **Bandeja diaria**, cada cita con su estado: **Pendiente** o **Enviado hoy**.

## Si algo sale mal
| Lo que ves | Qué hacer |
|---|---|
| **No reconocido como teléfono mexicano de 10 dígitos** | Corrige el teléfono del cliente ([[04 Clientes y mascotas]]). |
| **No acepta recordatorios** | El cliente pidió no recibirlos. Respétalo. |
| **Sin teléfono** | El cliente no tiene teléfono. Agrégalo. |
| No ves el menú **WhatsApp** | Si no ves esta opción, tu usuario no tiene acceso; pídelo al administrador principal. |

**Siguiente:** vuelve al [[00 Checklist de arranque]] o sigue con [[01 Tablero]].
