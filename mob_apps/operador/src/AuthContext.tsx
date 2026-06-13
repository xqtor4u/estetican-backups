import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';

interface AuthUser {
  id: number;
  name: string;
  email: string;
  roles: string[];
  is_admin: boolean;
  operator_role: string | null;
}

interface AuthContextType {
  user: AuthUser | null;
  token: string | null;
  loading: boolean;
  login: (username: string, password: string) => Promise<string | null>;
  logout: () => Promise<void>;
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

  // Al montar: restaurar sesión desde localStorage
  useEffect(() => {
    const saved = localStorage.getItem('api_token');
    if (saved) {
      setAuthToken(saved);
      setToken(saved);
      fetch('/api/me')
        .then(r => r.ok ? r.json() : Promise.reject())
        .then((u: AuthUser) => setUser(u))
        .catch(() => {
          localStorage.removeItem('api_token');
          setAuthToken(null);
          setToken(null);
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, []);

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
    <AuthContext.Provider value={{ user, token, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
