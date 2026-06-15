import React from 'react';
import { useNavigate } from 'react-router-dom';
import { getNavCrumbs } from './navState';
import { getUserPrefs } from './hooks/useUserPrefs';

interface Props {
  title: string;
  screenTag?: string;
  subtitle?: string;
  onBack?: () => void;
  backIcon?: string;
  rightAction?: React.ReactNode;
  /** Suprimir breadcrumbs (ej: mientras se está editando) */
  noCrumbs?: boolean;
}

export function ScreenHeader({
  title,
  screenTag,
  subtitle,
  onBack,
  backIcon = 'arrow_back',
  rightAction,
  noCrumbs = false,
}: Props) {
  const navigate = useNavigate();
  const crumbs = getNavCrumbs();
  const showBreadcrumbs = getUserPrefs().showBreadcrumbs;

  const showCrumbs = !noCrumbs && showBreadcrumbs && crumbs.length > 0;

  return (
    <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
      <button
        onClick={onBack ?? (() => navigate(-1))}
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
                  onClick={() => navigate(c.to)}
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
        {subtitle && <p className="text-xs text-on-surface-variant truncate">{subtitle}</p>}
      </div>

      {rightAction != null && <div className="shrink-0">{rightAction}</div>}
    </header>
  );
}
