/** Crumbs de navegación — se setean antes de navegar a MobCitaDet */
export interface NavCrumb { label: string; to: string }

let _crumbs: NavCrumb[] = [];

export function setNavCrumbs(crumbs: NavCrumb[]) { _crumbs = crumbs; }
export function getNavCrumbs(): NavCrumb[]         { return _crumbs; }
export function clearNavCrumbs()                   { _crumbs = []; }
