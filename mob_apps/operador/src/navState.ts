/** Crumbs de navegación — se setean antes de navegar a MobCitaDet */
export interface NavCrumb { label: string; to: string }

let _crumbs: NavCrumb[] = [];

export function setNavCrumbs(crumbs: NavCrumb[]) { _crumbs = crumbs; }
export function getNavCrumbs(): NavCrumb[]         { return _crumbs; }
export function clearNavCrumbs()                   { _crumbs = []; }

/**
 * Fecha "en tránsito" del flujo Agenda → + Cita → buscar mascota → formulario.
 * Antes, ese flujo pasaba por el detalle completo de la mascota y perdía la fecha
 * seleccionada en la Agenda por el camino — MobCitaNueva siempre arrancaba en HOY sin
 * importar qué día estuviera viendo el operador. Se propaga como este singleton (mismo
 * patrón que los crumbs de arriba) en vez de por query param, para no tener que tocar
 * cada ruta intermedia. Se limpia sola al consumirse (one-shot) — si alguien entra a
 * "Nueva cita" desde otro lado (ej. el ícono de agendar en el detalle de una mascota,
 * navegación normal sin pasar por Agenda), no debe quedar una fecha vieja pegada.
 */
let _citaPresetDate: string | null = null; // 'YYYY-MM-DD'

export function setCitaPresetDate(dateStr: string) { _citaPresetDate = dateStr; }
/** No consume — para que una pantalla intermedia (PetSearch) sepa que está en el flujo
 *  "elegir mascota para esta cita" sin todavía gastar el valor (lo consume MobCitaNueva). */
export function peekCitaPresetDate(): string | null { return _citaPresetDate; }
export function consumeCitaPresetDate(): string | null {
  const d = _citaPresetDate;
  _citaPresetDate = null;
  return d;
}

/**
 * Operador "en tránsito" del mismo flujo — si Agenda tenía un filtro de operador activo
 * (o GroomerAgenda, que YA está viendo la agenda de un operador puntual), no tiene sentido
 * volver a preguntarlo en el formulario. Mismo patrón one-shot que la fecha.
 */
let _citaPresetOperator: number | null = null;

export function setCitaPresetOperator(operatorId: number | null) { _citaPresetOperator = operatorId; }
export function consumeCitaPresetOperator(): number | null {
  const o = _citaPresetOperator;
  _citaPresetOperator = null;
  return o;
}

/**
 * Estado de vista de la Agenda global (fecha ancla, vista día/semana/mes, filtro de operador).
 * GlobalAgenda se desmonta al entrar al detalle de una cita; al volver con `navigate(-1)`
 * remontaba SIEMPRE en HOY / vista Día / sin filtro, perdiendo dónde estaba parado el operador.
 * Se guarda acá (mismo patrón singleton que los crumbs) y GlobalAgenda lo restaura al montar.
 * NO es one-shot: persiste toda la sesión para que ir y volver varias veces respete la posición.
 */
export interface AgendaViewState { dateStr: string; calView: 'day' | 'week' | 'month'; filterOp: number | null }
let _agendaViewState: AgendaViewState | null = null;
export function setAgendaViewState(s: AgendaViewState) { _agendaViewState = s; }
export function getAgendaViewState(): AgendaViewState | null { return _agendaViewState; }
export function clearAgendaViewState() { _agendaViewState = null; }

/**
 * Navegación entre "hermanos" para las pantallas de DETALLE (cita, mascota, cliente…):
 * la pantalla de lista (Agenda, buscador de mascotas/clientes, lista de mascotas de un
 * cliente) deja acá el orden de ids que se ve en pantalla + cómo construir la ruta de cada
 * detalle. La pantalla de detalle lo lee con `useSiblingNav` y ofrece ‹ › + arrastre
 * lateral hacia el anterior/siguiente. Si viene vacío (deep-link directo), no se muestran
 * las flechas (salvo que la propia pantalla tenga un fallback, como `MobCitaDet` con la
 * agenda del día).
 */
export interface SiblingNav { kind: string; ids: number[]; makePath: (id: number) => string }
let _siblingNav: SiblingNav | null = null;
const _siblingNavListeners = new Set<() => void>();
/** `kind` ('cita' | 'mascota' | 'cliente' …) evita que un detalle use una lista de otro tipo
 *  (p. ej. entrar al cliente desde una mascota no debe heredar la lista de mascotas). */
export function setSiblingNav(kind: string, ids: number[], makePath: (id: number) => string) {
  _siblingNav = { kind, ids, makePath };
  _siblingNavListeners.forEach(l => l());
}
export function getSiblingNav(): SiblingNav | null { return _siblingNav; }
export function clearSiblingNav() { _siblingNav = null; _siblingNavListeners.forEach(l => l()); }
/** Se notifica cuando cambia la lista de hermanos (p. ej. un detalle que la completa tarde). */
export function subscribeSiblingNav(l: () => void): () => void {
  _siblingNavListeners.add(l);
  return () => { _siblingNavListeners.delete(l); };
}
