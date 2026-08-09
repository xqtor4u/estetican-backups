# Proyecto IA — Motor de IA local en el servidor (Orange Pi)

> **Estado:** Prototipo funcional real, corriendo en la Orange Pi — texto + voz por Telegram, con tool-calling contra Huellitas (sandbox de Zeus-Estetican, nunca contra producción real de EstetiCAN). Sigue sin integrarse a EstetiCAN mismo ni a datos reales — sigue fuera del sprint activo de `BACKLOG.md`.
>
> **Última actualización:** 09/08/2026 — ver "Estado real de la implementación" más abajo para el detalle completo de esta fase. Las secciones de investigación/plan originales (06/08/2026) se dejan intactas como referencia histórica de lo que se decidió antes de construir nada.

---

## Objetivo

Meter un motor de IA ligero **en este mismo servidor** (Orange Pi 5 Plus de producción) para que ayude al staff — caso de uso ancla: **dictar por voz para agendar una cita**, con el modelo haciendo sus propias verificaciones (evitar traslapes de horario, mascotas inexistentes, datos faltantes) antes de ejecutar la acción.

Condición explícita del usuario: **"sin comprometer nada"** — ni seguridad, ni estabilidad, ni rendimiento del negocio real que ya corre ahí.

La propuesta original de arquitectura (React → `/api/ai/assistant` → Laravel → `App\Domain\AIAssistant` → Ollama/Qwen) la generó el propio Qwen corriendo en una instalación separada del usuario, como sugerencia de cómo encajarlo en este proyecto.

---

## Ya existe un precedente real en este proyecto — no es un punto de partida en blanco

`AssistantChatController` (`/api/assistant/chat`, BL-042, en producción) ya llama a un motor de IA — pero es un animal distinto:

| | Asistente público existente (BL-042) | Proyecto IA (este documento) |
|---|---|---|
| Motor | Claude/Anthropic vía API | Qwen local (Ollama o `rknn-llm`) |
| Alcance | Solo informativo — prohibido explícitamente agendar o tomar datos | Agente con herramientas: puede buscar mascotas y **crear citas** |
| Audiencia | Visitantes anónimos del sitio público | Staff autenticado (operadores) |
| Dónde vive | `AssistantChatController`, `App\Support\Assistant\ServiceCatalogPromptBuilder` | Por definir — **nombre distinto obligatorio** para no colisionar conceptualmente con `/api/assistant/*` |

Vale la pena replicar de ese precedente: guardar conversaciones para auditoría (ahí ya existe `ServiceAiChat`), configuración en `SystemSettings` en vez de `.env`.

---

## Estado real de la implementación (08-09/08/2026)

**Decisión tomada en la práctica, no solo teórica:** el sandbox de este proyecto se construyó **reutilizando Huellitas** (el tenant clon de Zeus-Estetican, `/opt/www/zeus-estetican/tenants/huellitas/`), no un sandbox 100% nuevo como proponía la sección "Metodología de prueba" original. Registrado también como `ZEUS-024` (resuelto) en el backlog de Zeus-Estetican. Huellitas ya estaba aislado (BD propia, red Docker propia, sin dominio) y ahorró tiempo de armar infraestructura desde cero — el trade-off real es que comparte CPU/RAM con producción por ser el mismo hardware físico, exactamente como ya advertía este documento.

### Infraestructura real, ya funcionando

- **Runtime de inferencia:** RKLLama (`ghcr.io/notpunchnox/rkllama:main`), en Docker, **usa el NPU real del RK3588** (no CPU) — `/srv/rkllama/docker-compose.yml`, `name:` propio, sin compartir red ni nombre con nada de EstetiCAN/Zeus. Puerto 8090 (el 8080 lo ocupaba otro contenedor existente, `testweb`).
- **Modelos cargados**, todos en `/srv/rkllama/models/`:
  - LLM: `qwen3:8b` (`dulimov/Qwen3-8B-rk3588-1.2.1-unsloth-16k`, contexto 16K, cuantización W8A8)
  - STT: `omniasr-ctc:300m` (`danielferr85/omniASR-ctc-rknn`) — se probó también la variante 1B (2.2GB): sin mejora de precisión en español y notablemente más lenta, descartada
  - TTS: `mms_tts_spa` (`danielferr85/mms-tts-rknn`), español genérico (sin variante regional es_MX/es_ES)
- **Bot:** `/srv/telegram-bot/bot_v3.py` — script Python standalone, **sin git todavía**, sin relación de código con Laravel. Corre vía `nohup python3 -u bot_v3.py &` (foreground/manual, sin systemd — se cae si se reinicia el proceso a mano y no se relanza). Polling directo a la API de Telegram (`getUpdates`/`sendMessage`/`sendVoice`), sin webhook.
- **Flujo de voz:** nota de voz → `ffmpeg` a WAV 16kHz/mono → STT → texto → LLM (con tool-calling) → TTS → `ffmpeg` a Opus/OGG → se manda transcripción + texto + nota de voz. Solo responde con voz si el mensaje entrante fue voz.

### Huellitas poblado con datos de demo reales (antes estaba completamente vacío — ni una sucursal)

Sembrado vía script PHP corrido por `tinker` (usa `BookingService::scheduleSpaSession()` real para las citas, no `Model::create()` crudo):
- 1 sucursal: **Bosque Sereno**
- **7 servicios reales copiados 1:1 de la producción real de EstetiCAN** (mismo `code`/nombre/precio, leídos de `estetican_mysql` en solo lectura)
- 2 operadoras inventadas, 5 clientes / 6 mascotas inventados (uno de los clientes con 2 mascotas), 6 citas de ejemplo, 6 artículos de venta

### Tool-calling real — 4 herramientas, todas contra la API ya existente de Huellitas (nada nuevo del lado de Laravel)

| Herramienta | Endpoint real que usa | Nota |
|---|---|---|
| `buscar_cliente` | `GET /api/clients?search=` + `GET /api/clients/{id}` | El segundo llamado es necesario porque el índice no trae nombres de mascotas, solo el conteo |
| `ver_stock` | `GET /api/items?search=` | La respuesta real viene envuelta en `{items, departments, brands}`, no un array plano — bug real encontrado y corregido |
| `consultar_citas_hoy` | `GET /api/agenda` (default `view=day`, `date=hoy`) | Ya existía, no hizo falta crear ningún endpoint nuevo |
| `crear_cita` | `POST /api/bookings` | Reusa el endpoint real, que ya valida horario operativo y conflictos de operador (`OperatorAvailabilityChecker`) — resuelve de fondo el gap #4 de la revisión original de abajo |

Autenticación: login real contra `POST /api/login` con `Admin`/`admin` (usuario sembrado de Huellitas), token guardado en memoria del proceso con reintento automático si expira (401). **Nota de diseño pendiente para cuando esto deje de ser un prototipo:** hoy el bot usa un único token de `Admin` compartido para cualquier persona que le escriba por Telegram — no hay diferenciación de rol por `chat_id`, cualquiera que hable con el bot tiene efectivamente permisos de super-admin. Ver gap #1 de la revisión original más abajo, sigue sin resolver en la práctica.

Memoria de conversación agregada (`CONVERSATIONS` en memoria del proceso, por `chat_id`, no persistente — se pierde si el bot se reinicia). Fecha real del día inyectada en el system prompt para resolver fechas relativas ("el lunes que entra") antes de llamar a `crear_cita`.

### Bugs y hallazgos de confiabilidad encontrados probando en vivo

1. **Reales, ya corregidos:** `/items` con forma de respuesta distinta a la asumida; `/agenda` trae `client` como hermano de `pet`, no anidado; falta de parámetro de duración en `crear_cita` (ignoraba "de 2 horas" y usaba la duración por defecto del servicio).
2. **Sin resolver — patrón de corte de respuesta:** el modelo corta su propia generación de forma prematura (termina en 3-4 palabras, a veces con un token de fin de secuencia emitido de golpe) en una fracción real de los turnos — más seguido cuando el turno anterior fue un **resultado de herramienta con error** (herramienta alucinada que no existe, conflicto de horario). **Bajar la temperatura no lo arregló** (probado explícitamente, 4/4 intentos igual de rotos a `temperature=0.2`) — descarta que sea puramente aleatoriedad de sampling. Sospecha sin confirmar: incompatibilidad entre cómo RKLLama arma el prompt para el rol `tool` en estado de error y cómo `qwen3` lo interpreta en este runtime NPU específico.
3. **Sin resolver — token especial filtrado:** al menos una vez apareció `<｜begin▁of▁sentence｜>` (token de control interno de la plantilla de chat) al inicio de una respuesta, sin que rompiera la conversación — parece un problema de la plantilla de chat de RKLLama para `qwen3`, no del bot.
4. **Calidad de recall entre turnos:** en un caso real, al preguntar "¿cuáles son sus mascotas?" sobre un cliente con 2 mascotas ya mencionadas en el turno anterior, el modelo solo recordó una — la memoria de conversación en sí funciona (no volvió a buscar), pero el resumen que hizo del historial fue incompleto. Limitación de calidad del modelo de 8B, no bug de código.
5. **El modelo alucina nombres de herramientas** que no existen en el `tools` declarado (ej. inventó `agregar_cita` antes de que se construyera de verdad) — el código lo maneja sin crashear ("Herramienta desconocida"), pero confirma que no hay que confiar ciegamente en que el modelo solo llame a lo declarado.

### Pendientes para retomar

- Decidir si vale la pena perseguir el patrón de corte de respuesta en errores (aislar más: ¿es cualquier tool_call, o solo resultados de error?) o aceptarlo como límite conocido de este modelo/runtime.
- Investigar el token especial filtrado si se vuelve a ver.
- La cita real que se agendó para "Luna" (lunes 15/08) quedó con 30 min en vez de las 2 horas pedidas originalmente (bug de duración, corregido después — no se corrigió esa cita en particular).
- El código del bot no tiene control de versiones — considerar un repo Git local (mismo criterio que Zeus-Estetican: sin remoto, contenido interno).
- Diseño de auth por usuario real (hoy todo corre con el token de `Admin` compartido) antes de que esto sea algo más que un prototipo de un solo operador probando.
- Nada de esto toca `docs/tecnico/BACKLOG.md` de EstetiCAN todavía — sigue siendo un proyecto paralelo sin fecha de integración real decidida.

---

## Hallazgos técnicos verificados contra el servidor real

### Hardware (Orange Pi 5 Plus, RK3588)
- CPU: 8 núcleos ARM (4× Cortex-A76 + 4× Cortex-A55)
- RAM: 16GB totales, **13GB libres** hoy (todo lo que ya corre —MySQL, PHP-FPM, nginx, mob— usa solo 1.8GB)
- Disco: 228GB, 210GB libres
- Sin swap configurado
- Kernel: Armbian 26.5.1 (Debian 13 trixie), `6.1.115-vendor-rk35xx`

**Veredicto de capacidad:** RAM y disco sobran para un Qwen2.5:7B cuantizado (Q4 ≈ 5GB). La duda real es **velocidad**, no capacidad.

### NPU (RK3588, 6 TOPS)
- El **driver del NPU ya está cargado y activo a nivel de kernel** — confirmado vía `devfreq` (`fdab0000.npu`, escalado 300MHz–1000MHz funcionando). Nada de compilar módulos ni tocar el kernel.
- El **runtime de usuario (`rknn-toolkit2`/`librknnrt.so`) no está instalado** — sería la primera vez que se toca ese stack en esta máquina.
- **Ollama no usa el NPU** — solo CPU. Para aprovechar el NPU hace falta `rknn-llm` (proyecto de GitHub aparte, instalación manual por terminal, fuera de Ollama).
- Motivación real del NPU en este servidor: **no es batería** (es un servidor con corriente 24/7, no un dispositivo portátil) — es **calor** (inferencia sostenida en un rack/clóset) y **liberar los 8 núcleos** para que no compitan con el tráfico real del negocio.

### Restricción de infraestructura ya existente que afecta esto
- `nginx.conf` de `mob_apps/operador` tiene `proxy_read_timeout 30s` en el `location` que proxea `/api/` — cualquier llamada a la IA que tarde más de eso desde el móvil se corta antes de llegar a Laravel. Hay que ajustar esto (o resolver con un patrón async) antes de que tool-calling con Ollama/NPU funcione de punta a punta desde `mov`.
- No hay infraestructura de colas hoy (`supervisord` de `estetican_app` solo corre `artisan serve`) — si se necesita un patrón asíncrono, hay que construirlo (como se hizo con el cron de WhatsApp para BL-024b).

---

## Revisión de la propuesta original (Qwen + Ollama + tool-calling)

Gaps encontrados al contrastarla contra el código real de EstetiCAN (documentado en detalle en la conversación de origen, resumen aquí):

1. **Falta `permission:` en la ruta** — la propuesta solo exige sesión válida, no el permiso real de agendar. Es la misma clase de bug que se auditó y remedió a fondo el 04-05/08/2026 (IDOR/escalación de privilegios).
2. **Middleware de auth incorrecto** — el proyecto no usa `auth:sanctum`, usa `ApiAuthenticate` custom con tokens propios en `api_tokens`.
3. **Búsqueda de mascota con `LIKE` simple** — reintroduce el bug que `TokenSearch` ya corrigió en 12 listados del proyecto.
4. **Creación de cita cruda (`Cita::create()`)** — se salta `App\Domain\Planning\BookingService::scheduleSpaSession()` real (transacción, cálculo de precio, `attachServices()`, auditoría) y `OperatorAvailabilityChecker::hasConflict()`/`isOutsideWorkingHours()` (que si no se usan, la IA podría agendar encima de otra cita).
5. **`while(true)` sin límite de iteraciones** en el loop de tool-calling — riesgo de worker de PHP-FPM atascado indefinidamente (mismo tipo de bug que NT-054, fetch sin timeout, resuelto hace poco en el lado del cliente).
6. **Timeout de 120s no sirve tal cual** — choca con el `proxy_read_timeout 30s` de nginx mencionado arriba.
7. **Historial de conversación (`history`) confiado del cliente sin validar roles** — un cliente podría en teoría inyectar mensajes falsos de rol `system`/`tool`.
8. **Nombres de modelo/campos del ejemplo no coinciden con el esquema real** (`Mascota`→`Pet`, `Cita`→`SpaBooking`, `estatus`→`status`, etc.) — solo relevante para cuando se traduzca a código real.
9. **No sigue la convención de interfaz + implementación** que usa el resto de `app/Domain/`.
10. **Networking Docker** — Laravel corre en contenedor; `http://orange-pi-ip:11434` debe ser la IP LAN real del host, no `localhost`. Puede necesitar regla de `ufw` explícita (ver BL-028).

**Nada de esto invalida la idea** — el esqueleto (IA nunca expuesta al cliente, Laravel de intermediario, tool-calling con funciones propias) es el mismo patrón ya usado en WhatsApp/Meta Catalog. Son ajustes de encaje, más una decisión de producto pendiente: **¿cualquier operador puede agendar vía IA, o solo quien ya podría hacerlo manualmente desde la UI?**

---

## Metodología de prueba acordada: sandbox aislado

**No se toca EstetiCAN para probar esto.** Plan:

- Carpeta totalmente aparte de `/opt/www/estetican` (sin git compartido, sin BITACORA/BACKLOG, sin protocolo de este proyecto).
- Docker Compose con `name:` propio y distinto — evita el tipo de incidente real que ya pasó una vez en este servidor (13/06/2026: dos compose con el mismo nombre de proyecto, uno tumbó el MySQL del otro).
- Se prueba Ollama+Qwen ahí: 100% desechable, `docker compose down -v` y desaparece sin dejar rastro.
- **Matiz importante:** si se prueba también `rknn-llm` (camino NPU), el runtime de usuario del NPU se instala a nivel de sistema operativo, no dentro de un contenedor aislado — no toca el kernel (eso ya está bien), pero no es tan "una carpeta que se borra y ya" como el resto del sandbox.
- CPU/RAM se comparten con producción por ser el mismo hardware físico — con 13GB libres y núcleos casi ociosos, una prueba puntual (no sostenida) no debería sentirse en el negocio real.

---

## Plan de investigación — qué queremos saber antes de decidir

> Este plan se escribió el 06/08/2026, antes de construir nada. El 08-09/08/2026 se empezó a
> responder en la práctica, no solo en teoría — ver "Estado real de la implementación" arriba
> para los hallazgos reales del Bloque 1 (tool-calling confiable en el camino feliz, cortes de
> respuesta reales en rutas de error, fechas relativas resueltas bien) y del Bloque 4 (sandbox
> confirmado aislado, se usó Huellitas en vez de uno nuevo). Bloques 2 y 3 (rendimiento, CPU vs.
> NPU) siguen sin medirse formalmente.

### 1. Calidad de razonamiento (¿el modelo sirve para esto?)
- ¿Entiende instrucciones y responde bien en español, sin mezclar inglés ni perder contexto?
- ¿Tool-calling confiable? — llama a la función correcta, con los argumentos correctos, sin "alucinar" que ya hizo algo sin llamarla.
- ¿Maneja bien fechas relativas en español ("el próximo martes", "en 15 días")?
- Si la herramienta responde "horario no disponible" o "mascota no encontrada" — ¿el modelo respeta esa respuesta, o insiste/inventa que sí se pudo?
- ¿Sabe pedir el dato que le falta en vez de inventarlo?

### 2. Rendimiento real (¿es utilizable en la práctica?)
- Latencia de una respuesta simple vs. una con varias llamadas a herramientas encadenadas.
- Uso real de RAM/CPU durante la inferencia (¿satura los 8 núcleos? ¿sube mucho la temperatura?).
- Qué tan lento es el "primer arranque" (cargar el modelo en memoria) vs. con el modelo ya caliente.
- Qué pasa si llegan dos conversaciones al mismo tiempo (dos operadores usándolo a la vez).

### 3. CPU (Ollama) vs. NPU (`rknn-llm`) — comparación directa
- Diferencia real de velocidad entre los dos caminos, no solo en teoría.
- Qué tan complicada es en la práctica la instalación/conversión de modelo para `rknn-llm` (¿la conversión a `.rknn` corre nativa en ARM o necesita una máquina x86 aparte?).
- Qué API expone `rknn-llm` (¿compatible con el formato tipo OpenAI que ya se usa, o hay que adaptarlo?).

### 4. Aislamiento del sandbox
- Confirmar en vivo que nada del sandbox comparte red/nombre/puerto con los contenedores de EstetiCAN, antes de dar por buena cualquier prueba de carga.

### Deliberadamente fuera de esta primera ronda
- **Voz** (Whisper local vs. Web Speech API del navegador) — validar primero si el cerebro (Qwen) razona bien, antes de meterle otra pieza (transcripción) encima. Cuando se retome: Whisper local mantiene todo en el servidor (coherente con "motor propio"), pero es otro proceso más compitiendo por los mismos recursos; Web Speech API es gratis y sin instalación pero manda el audio a la nube de Google.
- Cualquier integración real con datos/lógica de EstetiCAN — eso viene después, solo si el sandbox da luz verde.

---

## Decisiones de producto pendientes (no técnicas)

1. ¿Cualquier operador autenticado puede usar el asistente para agendar, o solo quien ya tendría el permiso de agendar manualmente? **Sigue sin decidirse** — el prototipo real de hoy usa un único token de `Admin` compartido para cualquiera, ver "Estado real de la implementación".
2. Nombre definitivo del componente nuevo (para no colisionar con `AssistantChatController`/`/api/assistant/*` ya existente). El bot de Telegram hoy se identifica como "EstetiCAN Assistant" de forma informal, sin que sea una decisión de nombre definitiva ni tenga relación de código con `AssistantChatController`.
3. Si el sandbox valida bien CPU (Ollama) pero el NPU no compensa la complejidad de instalación — ¿vale la pena perseguir el NPU igual, o Ollama-CPU es suficiente para el volumen real de uso esperado? **Parcialmente adelantado:** se fue directo por el camino NPU (`rknn-llm`/RKLLama) sin probar Ollama-CPU en paralelo todavía — la comparación directa del Bloque 3 del plan de investigación sigue pendiente.
