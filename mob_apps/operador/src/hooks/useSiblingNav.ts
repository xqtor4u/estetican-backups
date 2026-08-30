import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getSiblingNav, setSiblingNav, subscribeSiblingNav } from '../navState';
import { useSwipe } from './useSwipe';

/**
 * Navegación entre "hermanos" para CUALQUIER pantalla de detalle (mascota, cliente, cita…).
 *
 * Patrón (parte del diseño de la "plantilla" de pantallas de detalle):
 *  1. La pantalla de LISTA, justo antes de navegar al detalle, llama
 *     `setSiblingNav(idsEnElOrdenQueSeVe, id => '/ruta/' + id)`.
 *  2. La pantalla de DETALLE llama `const sib = useSiblingNav(idActual)` y:
 *     - pasa `sib.onPrev` / `sib.onNext` a `<ScreenHeader onTitlePrev/onTitleNext>` (flechas ‹ ›),
 *     - esparce `{...sib.swipeHandlers}` en su `<div>` raíz (arrastre lateral).
 *  Si no hubo lista (deep-link directo), `hasNav` es false y no se muestra nada — salvo que
 *  la propia pantalla rellene la lista tarde con `setSiblingNav` (p. ej. `MobCitaDet` con la
 *  agenda del día); este hook se re-renderiza solo cuando eso pasa.
 */
export function useSiblingNav(currentId: number | string | null | undefined, kind: string) {
  const navigate = useNavigate();
  const [nav, setNav] = useState(getSiblingNav);

  useEffect(() => subscribeSiblingNav(() => setNav(getSiblingNav())), []);

  const ids = nav && nav.kind === kind ? nav.ids : [];
  const cur = currentId == null ? NaN : Number(currentId);
  const idx = Number.isNaN(cur) ? -1 : ids.indexOf(cur);
  const prevId = idx > 0 ? ids[idx - 1] : null;
  const nextId = idx >= 0 && idx < ids.length - 1 ? ids[idx + 1] : null;

  const go = (id: number) => { if (nav) navigate(nav.makePath(id)); };
  const onPrev = idx >= 0 && prevId != null ? () => go(prevId) : undefined;
  const onNext = idx >= 0 && nextId != null ? () => go(nextId) : undefined;
  const hasNav = idx >= 0 && (onPrev !== undefined || onNext !== undefined);

  const swipe = useSwipe({ onSwipeLeft: onNext, onSwipeRight: onPrev });

  return {
    onPrev,
    onNext,
    hasNav,
    /** { current, total } 1-indexado para un "3 / 12" opcional; null si no hay lista */
    position: idx >= 0 ? { current: idx + 1, total: ids.length } : null,
    /** Poner en el <div> raíz de la pantalla de detalle */
    swipeHandlers: hasNav ? swipe : ({} as Partial<ReturnType<typeof useSwipe>>),
  };
}

export { setSiblingNav };
