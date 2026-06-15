import { useState, useCallback } from 'react';

export interface UserPrefs {
  showBreadcrumbs: boolean;
}

const STORAGE_KEY = 'estetican:prefs';
const DEFAULTS: UserPrefs = { showBreadcrumbs: true };

function readPrefs(): UserPrefs {
  try {
    return { ...DEFAULTS, ...JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') };
  } catch {
    return { ...DEFAULTS };
  }
}

export function useUserPrefs() {
  const [prefs, setPrefs] = useState<UserPrefs>(readPrefs);

  const update = useCallback((patch: Partial<UserPrefs>) => {
    setPrefs(prev => {
      const next = { ...prev, ...patch };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      return next;
    });
  }, []);

  return { prefs, update };
}

/** Leer prefs sin hook (para componentes que solo leen una vez al montar) */
export function getUserPrefs(): UserPrefs {
  return readPrefs();
}
