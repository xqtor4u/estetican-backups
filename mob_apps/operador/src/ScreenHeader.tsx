import React from 'react';

export interface Crumb { label: string; to: string }

export interface ScreenHeaderProps {
  title: string;
  screenTag?: string;
  subtitle?: string;
  /** Handler del botón de regreso */
  onBack: () => void;
  backIcon?: string;
  /** Acción opcional a la derecha (botones guardar, editar, etc.) */
  rightAction?: React.ReactNode;
  /** Crumbs leídos del módulo navState por la pantalla padre */
  crumbs?: Crumb[];
  showBreadcrumbs?: boolean;
  /** Pasar true en modo edición para suprimir breadcrumbs */
  noCrumbs?: boolean;
  /** Callback cuando el usuario toca un crumb */
  onCrumbClick?: (to: string) => void;
}

export function ScreenHeader({
  title,
  screenTag,
  subtitle,
  onBack,
  backIcon = 'arrow_back',
  rightAction,
  crumbs = [],
  showBreadcrumbs = true,
  noCrumbs = false,
  onCrumbClick,
}: ScreenHeaderProps) {
  const showCrumbs = !noCrumbs && showBreadcrumbs && crumbs.length > 0;

  return (
    <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
      <button
        onClick={onBack}
        className="p-2 rounded-full hover:bg-surface-container-high transition-colors"
      >
        <span className="material-symbols-outlined text-on-surface">{backIcon}</span>
      </button>

      <div className="flex-1 min-w-0">
        {showCrumbs ? (
          <div className="flex items-center gap-1 overflow-hidden">
            {crumbs.map((c, i) => (
              <React.Fragment key={i}>
                {i > 0 && <span className="text-xs text-on-surface-variant/50 shrink-0">›</span>}
                <button
                  onClick={() => onCrumbClick?.(c.to)}
                  className="text-xs text-primary font-medium truncate active:opacity-70"
                >
                  {c.label}
                </button>
              </React.Fragment>
            ))}
            <span className="text-xs text-on-surface-variant/50 shrink-0">›</span>
            <span className="text-sm font-bold text-on-surface truncate">{title}</span>
          </div>
        ) : (
          <p className="font-bold text-on-surface text-base leading-tight truncate">
            {title}
            {screenTag && (
              <span className="text-[9px] font-mono text-on-surface-variant/30 font-normal ml-1">
                {screenTag}
              </span>
            )}
          </p>
        )}
        {subtitle && (
          <p className="text-xs text-on-surface-variant truncate">{subtitle}</p>
        )}
      </div>

      {rightAction != null && <div className="shrink-0">{rightAction}</div>}
    </header>
  );
}
