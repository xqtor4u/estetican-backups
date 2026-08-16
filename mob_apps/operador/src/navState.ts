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
