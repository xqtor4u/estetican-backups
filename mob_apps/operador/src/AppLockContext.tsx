import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { useAuth } from './AuthContext';

const IDLE_TIMEOUT_MS = 5 * 60 * 1000; // 5 minutos sin actividad
const ACTIVITY_EVENTS = ['touchstart', 'mousedown', 'keydown', 'scroll'] as const;

interface AppLockContextType {
  locked: boolean;
  lock: () => void;
  unlock: () => void;
}

const AppLockContext = createContext<AppLockContextType>({
  locked: false,
  lock: () => {},
  unlock: () => {},
});

export function useAppLock() {
  return useContext(AppLockContext);
}

export function AppLockProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  const enabled = !!user;
  const [locked, setLocked] = useState(false);
  const timerRef = useRef<number | null>(null);

  const resetTimer = useCallback(() => {
    if (timerRef.current) window.clearTimeout(timerRef.current);
    if (!enabled) return;
    timerRef.current = window.setTimeout(() => setLocked(true), IDLE_TIMEOUT_MS);
  }, [enabled]);

  useEffect(() => {
    if (!enabled) {
      setLocked(false);
      return;
    }

    resetTimer();
    ACTIVITY_EVENTS.forEach(ev => document.addEventListener(ev, resetTimer, { passive: true }));

    const onVisibilityChange = () => {
      if (document.hidden) setLocked(true);
      else resetTimer();
    };
    document.addEventListener('visibilitychange', onVisibilityChange);

    return () => {
      ACTIVITY_EVENTS.forEach(ev => document.removeEventListener(ev, resetTimer));
      document.removeEventListener('visibilitychange', onVisibilityChange);
      if (timerRef.current) window.clearTimeout(timerRef.current);
    };
  }, [enabled, resetTimer]);

  const lock = useCallback(() => setLocked(true), []);
  const unlock = useCallback(() => { setLocked(false); resetTimer(); }, [resetTimer]);

  return (
    <AppLockContext.Provider value={{ locked, lock, unlock }}>
      {children}
    </AppLockContext.Provider>
  );
}
