import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { fetchWithTimeout } from './lib/fetchWithTimeout';

interface AuthUser {
  id: number;
  name: string;
  first_name: string | null;
  last_name: string | null;
  apellido_paterno: string | null;
  apellido_materno: string | null;
  email: string;
  roles: string[];
  is_admin: boolean;
  can_override_schedule: boolean;
  operator_id: number | null;
  operator_role: string | null;
  photo_url: string | null;
}

interface AuthContextType {
  user: AuthUser | null;
  token: string | null;
  loading: boolean;
  sessionCheckFailed: boolean;
  retrySessionCheck: () => void;
  login: (username: string, password: string) => Promise<string | null>;
  logout: () => Promise<void>;
  setUser: (user: AuthUser) => void;
}

const AuthContext = createContext<AuthContextType>(null!);

export function useAuth() {
  return useContext(AuthContext);
}

// Adjunta el token a todas las peticiones fetch a /api/*
const _originalFetch = window.fetch.bind(window);
let _currentToken: string | null = null;

export function setAuthToken(token: string | null) {
  _currentToken = token;
}

window.fetch = (input, init = {}) => {
  const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : (input as Request).url;
  if (url.startsWith('/api/') && _currentToken) {
    init = {
      ...init,
      headers: {
        ...init.headers,
        Authorization: `Bearer ${_currentToken}`,
        Accept: 'application/json',
      },
    };
  }
  return _originalFetch(input, init);
};

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser]   = useState<AuthUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [sessionCheckFailed, setSessionCheckFailed] = useState(false);

  // Restaura sesión desde localStorage. Solo un 401 explícito invalida el token guardado —
  // un timeout o error de red (típico al reanudar la app en Android con conexión inestable)
  // no debe desloguear a nadie con una sesión válida, solo ofrecer reintentar.
  const checkSession = useCallback(() => {
    const saved = localStorage.getItem('api_token');
    if (!saved) {
      setLoading(false);
      return;
    }
    setAuthToken(saved);
    setToken(saved);
    setLoading(true);
    setSessionCheckFailed(false);
    fetchWithTimeout('/api/me', {}, 12000)
      .then(r => {
        if (r.ok) return r.json();
        if (r.status === 401) throw new Error('unauthorized');
        throw new Error('server-error');
      })
      .then((u: AuthUser) => setUser(u))
      .catch((err) => {
        if (err instanceof Error && err.message === 'unauthorized') {
          localStorage.removeItem('api_token');
          setAuthToken(null);
          setToken(null);
        } else {
          // Timeout, sin conexión, o error de servidor transitorio — la sesión guardada
          // puede seguir siendo válida, solo no se pudo confirmar ahora.
          setSessionCheckFailed(true);
        }
      })
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { checkSession(); }, [checkSession]);

  const login = useCallback(async (username: string, password: string): Promise<string | null> => {
    let res: Response;
    let data: any;
    try {
      res = await fetch('/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ username, password }),
      });
      data = await res.json();
    } catch {
      return 'No se pudo conectar con el servidor';
    }

    if (!res.ok) return data.message ?? `Error del servidor (${res.status})`;

    localStorage.setItem('api_token', data.token);
    setAuthToken(data.token);
    setToken(data.token);
    setUser(data.user);
    return null;
  }, []);

  const logout = useCallback(async () => {
    await fetch('/api/logout', { method: 'POST' }).catch(() => {});
    localStorage.removeItem('api_token');
    setAuthToken(null);
    setToken(null);
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, token, loading, sessionCheckFailed, retrySessionCheck: checkSession, login, logout, setUser }}>
      {children}
    </AuthContext.Provider>
  );
}
