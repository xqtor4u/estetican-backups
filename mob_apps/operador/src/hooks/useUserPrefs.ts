import { useState, useCallback } from 'react';

export type ThemeMode = 'light' | 'dark' | 'system';

export interface UserPrefs {
  showBreadcrumbs: boolean;
  theme: ThemeMode;
}

const STORAGE_KEY = 'estetican:prefs';
const DEFAULTS: UserPrefs = { showBreadcrumbs: true, theme: 'system' };

function readPrefs(): UserPrefs {
  try {
    return { ...DEFAULTS, ...JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') };
  } catch {
    return { ...DEFAULTS };
  }
}

/** Aplica el modo de tema al documento. 'system' sigue la preferencia del sistema operativo. */
export function applyTheme(theme: ThemeMode) {
  const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.classList.toggle('dark', dark);
}

let systemListenerAttached = false;

/** Llamar una vez al iniciar la app (fuera de React) para pintar el tema correcto antes del primer render. */
export function bootTheme() {
  const prefs = readPrefs();
  applyTheme(prefs.theme);

  if (!systemListenerAttached) {
    systemListenerAttached = true;
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      if (readPrefs().theme === 'system') applyTheme('system');
    });
  }
}

export function useUserPrefs() {
  const [prefs, setPrefs] = useState<UserPrefs>(readPrefs);

  const update = useCallback((patch: Partial<UserPrefs>) => {
    setPrefs(prev => {
      const next = { ...prev, ...patch };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      if (patch.theme) applyTheme(patch.theme);
      return next;
    });
  }, []);

  return { prefs, update };
}

/** Leer prefs sin hook (para componentes que solo leen una vez al montar) */
export function getUserPrefs(): UserPrefs {
  return readPrefs();
}
