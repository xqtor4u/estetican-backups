import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from './AuthContext';
import { useAppLock } from './AppLockContext';

export interface Crumb { label: string; to: string }

export interface ScreenHeaderProps {
  title: string;
  screenTag?: string;
  subtitle?: string;
  /** Omitir para ocultar el botón de regreso (pantallas raíz) */
  onBack?: () => void;
  backIcon?: string;
  /** Acción opcional a la derecha (botones guardar, editar, etc.) */
  rightAction?: React.ReactNode;
  /** Oculta el avatar de usuario (ej. modo edición a pantalla completa) */
  hideAvatar?: boolean;
  /** Si se pasan (aunque sea uno), el título queda flanqueado por ‹ › para saltar a un
   *  hermano (ej. la cita anterior/siguiente del mismo día). `undefined` = flecha inactiva.
   *  Patrón estándar de pantallas de DETALLE: usar el hook `useSiblingNav(id, kind)` y pasar
   *  `sib.onPrev` / `sib.onNext` acá + `{...sib.swipeHandlers}` en el <div> raíz. */
  onTitlePrev?: () => void;
  onTitleNext?: () => void;
  /** Crumbs leídos del módulo navState por la pantalla padre */
  crumbs?: Crumb[];
  showBreadcrumbs?: boolean;
  /** Pasar true en modo edición para suprimir breadcrumbs */
  noCrumbs?: boolean;
  /** Callback cuando el usuario toca un crumb; recibe la ruta y los crumbs previos al tocado */
  onCrumbClick?: (to: string, prevCrumbs: Crumb[]) => void;
}

/** Avatar del usuario logueado + menú rápido — visible en todas las pantallas para que
 *  siempre se sepa con qué cuenta se está operando (antes solo se veía abriendo el drawer). */
function UserAvatarMenu() {
  const { user, logout } = useAuth();
  const { lock } = useAppLock();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  if (!user) return null;

  const roleLine = user.operator_role ?? (user.is_admin ? 'Administrador' : user.roles[0] ?? 'Usuario');

  return (
    <div className="shrink-0 relative">
      <button
        onClick={() => setOpen(v => !v)}
        aria-label="Cuenta"
        className="w-8 h-8 rounded-full overflow-hidden bg-primary/20 flex items-center justify-center border border-outline-variant active:scale-95 transition-transform"
      >
        {user.photo_url
          ? <img src={user.photo_url} alt={user.name} className="w-full h-full object-cover" />
          : <span className="material-symbols-outlined text-primary text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>account_circle</span>}
      </button>

      {open && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
          <div className="absolute right-0 top-10 z-50 w-56 bg-surface rounded-2xl shadow-2xl border border-outline-variant overflow-hidden">
            <div className="px-4 py-3 border-b border-outline-variant flex items-center gap-3">
              <div className="w-9 h-9 rounded-full overflow-hidden bg-primary/20 flex items-center justify-center shrink-0">
                {user.photo_url
                  ? <img src={user.photo_url} alt="" className="w-full h-full object-cover" />
                  : <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>account_circle</span>}
              </div>
              <div className="min-w-0">
                <p className="text-sm font-semibold text-on-surface truncate">{user.name}</p>
                <p className="text-xs text-on-surface-variant truncate">
                  {roleLine}{user.branch_name ? ` · ${user.branch_name}` : ''}
                </p>
              </div>
            </div>
            <button
              onClick={() => { setOpen(false); navigate('/configuracion'); }}
              className="w-full flex items-center gap-3 px-4 py-3 text-left text-sm text-on-surface active:bg-surface-container-high transition-colors"
            >
              <span className="material-symbols-outlined text-lg text-on-surface-variant">manage_accounts</span>
              Configuración personal
            </button>
            <button
              onClick={() => { setOpen(false); lock(); }}
              className="w-full flex items-center gap-3 px-4 py-3 text-left text-sm text-on-surface border-t border-outline-variant active:bg-surface-container-high transition-colors"
            >
              <span className="material-symbols-outlined text-lg text-on-surface-variant">lock</span>
              Bloquear ahora
            </button>
            <button
              onClick={() => { setOpen(false); logout(); }}
              className="w-full flex items-center gap-3 px-4 py-3 text-left text-sm text-error border-t border-outline-variant active:bg-error/10 transition-colors"
            >
              <span className="material-symbols-outlined text-lg">logout</span>
              Cerrar sesión
            </button>
          </div>
        </>
      )}
    </div>
  );
}

export function ScreenHeader({
  title,
  screenTag,
  subtitle,
  onBack,
  backIcon = 'arrow_back',
  rightAction,
  hideAvatar = false,
  onTitlePrev,
  onTitleNext,
  crumbs = [],
  showBreadcrumbs = true,
  noCrumbs = false,
  onCrumbClick,
}: ScreenHeaderProps) {
  const showCrumbs = !noCrumbs && showBreadcrumbs && crumbs.length > 0;
  const hasTitleNav = onTitlePrev !== undefined || onTitleNext !== undefined;

  const titleNav = hasTitleNav && (
    <span className="inline-flex items-center shrink-0">
      <button
        onClick={onTitlePrev}
        disabled={!onTitlePrev}
        aria-label="Anterior"
        className="p-0.5 rounded-full disabled:opacity-25 active:bg-surface-container-high transition-colors"
      >
        <span className="material-symbols-outlined text-on-surface text-lg">chevron_left</span>
      </button>
      <button
        onClick={onTitleNext}
        disabled={!onTitleNext}
        aria-label="Siguiente"
        className="p-0.5 rounded-full disabled:opacity-25 active:bg-surface-container-high transition-colors"
      >
        <span className="material-symbols-outlined text-on-surface text-lg">chevron_right</span>
      </button>
    </span>
  );

  return (
    <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
      {onBack && (
        <button
          onClick={onBack}
          className="p-2 rounded-full hover:bg-surface-container-high transition-colors"
        >
          <span className="material-symbols-outlined text-on-surface">{backIcon}</span>
        </button>
      )}

      <div className="flex-1 min-w-0">
        {showCrumbs ? (
          <div className="flex items-center gap-1 overflow-hidden">
            {crumbs.map((c, i) => (
              <React.Fragment key={i}>
                {i > 0 && <span className="text-xs text-on-surface-variant/50 shrink-0">›</span>}
                <button
                  onClick={() => onCrumbClick?.(c.to, crumbs.slice(0, i))}
                  className="text-xs text-primary font-medium truncate active:opacity-70"
                >
                  {c.label}
                </button>
              </React.Fragment>
            ))}
            <span className="text-xs text-on-surface-variant/50 shrink-0">›</span>
            {titleNav}
            <span className="text-sm font-bold text-on-surface truncate">{title}</span>
            {screenTag && (
              <span className="ml-1.5 shrink-0 rounded border border-on-surface-variant/20 px-1 py-px text-[9px] font-mono font-normal text-on-surface-variant/50">
                {screenTag}
              </span>
            )}
          </div>
        ) : (
          <div className="flex items-center gap-1 min-w-0">
            {titleNav}
            <p className="font-bold text-on-surface text-lg leading-tight truncate">
              {title}
              {screenTag && (
                <span className="ml-1.5 rounded border border-on-surface-variant/20 px-1 py-px text-[9px] font-mono font-normal text-on-surface-variant/50">
                  {screenTag}
                </span>
              )}
            </p>
          </div>
        )}
        {subtitle && (
          <p className="text-xs text-on-surface-variant truncate">{subtitle}</p>
        )}
      </div>

      {rightAction != null && <div className="shrink-0">{rightAction}</div>}
      {!hideAvatar && <UserAvatarMenu />}
    </header>
  );
}
