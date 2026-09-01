import { useEffect, useState } from 'react';

/** Marca configurada en el backoffice (Configuración → Identidad y Branding). */
export interface Branding {
  businessName: string;
  /** URL relativa del logo (`/storage/...`) o `null` si no hay uno configurado. */
  logoUrl: string | null;
  /** Favicon configurado; si no hay, cae al logo. `null` si tampoco hay logo. */
  faviconUrl: string | null;
}

const DEFAULT: Branding = { businessName: 'EstetiCAN', logoUrl: null, faviconUrl: null };

// Caché a nivel de módulo — la marca no cambia dentro de una sesión, así que basta
// una sola llamada aunque varias pantallas usen el hook (login, agenda, config).
let cache: Branding | null = null;
let inFlight: Promise<Branding> | null = null;

function applyFavicon(url: string | null) {
  if (!url || typeof document === 'undefined') return;
  let link = document.querySelector<HTMLLinkElement>("link[rel~='icon']");
  if (!link) {
    link = document.createElement('link');
    link.rel = 'icon';
    document.head.appendChild(link);
  }
  if (link.href !== url) link.href = url;
}

function fetchBranding(): Promise<Branding> {
  if (cache) return Promise.resolve(cache);
  if (inFlight) return inFlight;

  inFlight = fetch('/api/settings/branding')
    .then(r => r.json())
    .then(data => {
      cache = {
        businessName: data?.business_name || DEFAULT.businessName,
        logoUrl: data?.logo_url || null,
        faviconUrl: data?.favicon_url || data?.logo_url || null,
      };
      applyFavicon(cache.faviconUrl);
      return cache;
    })
    .catch(() => DEFAULT)
    .finally(() => { inFlight = null; });

  return inFlight;
}

/** Marca completa: nombre + logo + favicon. */
export function useBranding(): Branding {
  const [branding, setBranding] = useState<Branding>(cache ?? DEFAULT);

  useEffect(() => {
    let active = true;
    fetchBranding().then(b => { if (active) setBranding(b); });
    return () => { active = false; };
  }, []);

  return branding;
}

/** Compatibilidad: solo el nombre del negocio. */
export function useBusinessName(): string {
  return useBranding().businessName;
}
