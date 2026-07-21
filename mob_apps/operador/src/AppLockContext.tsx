import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { useAuth } from './AuthContext';
import { getUserPrefs } from './hooks/useUserPrefs';

const DEFAULT_IDLE_TIMEOUT_MS = 5 * 60 * 1000; // fallback si la preferencia no es válida
const ACTIVITY_EVENTS = ['touchstart', 'mousedown', 'keydown', 'scroll'] as const;
const STORAGE_KEY = 'estetican_lock_state';
const ACTIVITY_WRITE_THROTTLE_MS = 5000;
// Al ocultarse la pestaña (visibilitychange), algunos WebView de Android disparan
// `hidden` momentáneamente durante navegación interna o al abrir un picker nativo
// (fecha, foto) sin que el usuario haya salido de verdad de la app. Se espera este
// margen antes de bloquear — un cambio real de app dura muchísimo más que esto.
const HIDDEN_GRACE_MS = 1500;

function getIdleTimeoutMs(): number {
  const minutes = getUserPrefs().lockTimeoutMinutes;

  return Number.isFinite(minutes) && minutes > 0 ? minutes * 60 * 1000 : DEFAULT_IDLE_TIMEOUT_MS;
}

interface StoredLockState {
  locked: boolean;
  lastActivity: number;
}

function readStoredState(): StoredLockState | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function writeStoredState(state: StoredLockState) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  } catch {
    // localStorage no disponible (modo privado, cuota llena, etc.) — el candado sigue
    // funcionando en memoria durante esta sesión de JS, solo no sobrevive a un reload.
  }
}

function clearStoredState() {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch {
    /* noop */
  }
}

// Reconstruye si debería estar bloqueado ahora mismo a partir de lo persistido —
// se usa tanto al montar (reload, back/forward, o Android matando la pestaña en
// segundo plano, todos destruyen el estado de React en memoria) como al restaurar
// desde el back/forward cache del navegador (bfcache), donde el JS queda congelado
// con el valor de `locked` que tenía en el momento de salir de la página, que puede
// haber quedado desactualizado.
function computeLockedFromStorage(): boolean {
  const stored = readStoredState();
  if (!stored) return false;
  if (stored.locked) return true;
  return Date.now() - stored.lastActivity > getIdleTimeoutMs();
}

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
  const { user, loading: authLoading } = useAuth();
  const enabled = !!user;
  const [locked, setLockedState] = useState<boolean>(() => computeLockedFromStorage());
  const timerRef = useRef<number | null>(null);
  const hiddenTimerRef = useRef<number | null>(null);
  const lastWriteRef = useRef(0);
  // Refleja `locked` de forma síncrona para leer dentro de `resetTimer` sin
  // recrear ese callback (y sin re-suscribir los listeners de actividad) cada
  // vez que cambia — ver el guard más abajo.
  const lockedRef = useRef(locked);
  useEffect(() => { lockedRef.current = locked; }, [locked]);

  const setLocked = useCallback((next: boolean) => {
    setLockedState(next);
    const now = Date.now();
    lastWriteRef.current = now;
    writeStoredState({ locked: next, lastActivity: now });
  }, []);

  const resetTimer = useCallback(() => {
    if (timerRef.current) window.clearTimeout(timerRef.current);
    if (!enabled) return;
    timerRef.current = window.setTimeout(() => setLocked(true), getIdleTimeoutMs());

    // Los eventos de actividad (touch/click/tecla/scroll) burbujean hasta acá
    // incluso cuando el toque/tecleo ocurrió DENTRO de la pantalla de bloqueo
    // (por ejemplo, escribiendo la contraseña sin terminar de desbloquear). Si
    // ya está bloqueado no hay que tocar lo persistido — de lo contrario un
    // reload a mitad de escribir la contraseña mostraría la app desbloqueada.
    if (lockedRef.current) return;

    const now = Date.now();
    if (now - lastWriteRef.current > ACTIVITY_WRITE_THROTTLE_MS) {
      lastWriteRef.current = now;
      writeStoredState({ locked: false, lastActivity: now });
    }
  }, [enabled, setLocked]);

  useEffect(() => {
    // Mientras `useAuth()` todavía está resolviendo la sesión (fetch a /api/me
    // tras leer el token de localStorage, típico en cada reload) `enabled` es
    // `false` aunque en realidad sí haya una sesión guardada — no tocar nada
    // todavía, ni el estado ni lo persistido, hasta saber la respuesta real.
    if (authLoading) return;

    if (!enabled) {
      setLockedState(false);
      clearStoredState();
      return;
    }

    // Re-sincroniza contra lo persistido ahora que se confirmó la sesión — el
    // valor inicial de `locked` (calculado en el primer render, antes de saber
    // si había sesión) puede haber quedado desactualizado.
    setLockedState(computeLockedFromStorage());

    resetTimer();
    ACTIVITY_EVENTS.forEach(ev => document.addEventListener(ev, resetTimer, { passive: true }));

    const onVisibilityChange = () => {
      if (document.hidden) {
        // No bloquear de inmediato — ver HIDDEN_GRACE_MS. Si la pestaña vuelve a
        // ser visible antes de que se cumpla el margen, se cancela más abajo.
        hiddenTimerRef.current = window.setTimeout(() => setLocked(true), HIDDEN_GRACE_MS);
      } else {
        if (hiddenTimerRef.current) {
          window.clearTimeout(hiddenTimerRef.current);
          hiddenTimerRef.current = null;
        }
        resetTimer();
      }
    };
    document.addEventListener('visibilitychange', onVisibilityChange);

    // Restaurado desde bfcache (gesto "atrás"/"adelante" del navegador): el JS
    // estaba congelado, así que hay que revalidar contra lo persistido en vez de
    // confiar en el `locked` que tenía en memoria al momento de salir de la página.
    const onPageShow = (e: PageTransitionEvent) => {
      if (e.persisted) setLockedState(computeLockedFromStorage());
    };
    window.addEventListener('pageshow', onPageShow);

    return () => {
      ACTIVITY_EVENTS.forEach(ev => document.removeEventListener(ev, resetTimer));
      document.removeEventListener('visibilitychange', onVisibilityChange);
      window.removeEventListener('pageshow', onPageShow);
      if (timerRef.current) window.clearTimeout(timerRef.current);
      if (hiddenTimerRef.current) window.clearTimeout(hiddenTimerRef.current);
    };
  }, [authLoading, enabled, resetTimer, setLocked]);

  const lock = useCallback(() => setLocked(true), [setLocked]);
  const unlock = useCallback(() => { setLocked(false); resetTimer(); }, [setLocked, resetTimer]);

  return (
    <AppLockContext.Provider value={{ locked, lock, unlock }}>
      {children}
    </AppLockContext.Provider>
  );
}
