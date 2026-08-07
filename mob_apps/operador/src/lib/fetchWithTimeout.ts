/**
 * Android puede congelar o matar el socket de un fetch en curso al mandar la pestaña a
 * segundo plano, sin que la promesa nativa de `fetch` llegue a resolver ni rechazar nunca
 * (no hay timeout por default en la Fetch API). Cualquier pantalla que dependa de un fetch
 * así para salir de un estado "cargando" queda atrapada ahí para siempre. Este helper le pone
 * un límite explícito.
 */
export class FetchTimeoutError extends Error {
  constructor() {
    super('fetch timeout');
    this.name = 'FetchTimeoutError';
  }
}

export function fetchWithTimeout(
  input: RequestInfo | URL,
  init: RequestInit = {},
  timeoutMs = 12000,
): Promise<Response> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  return fetch(input, { ...init, signal: controller.signal })
    .catch(err => {
      if (err?.name === 'AbortError') throw new FetchTimeoutError();
      throw err;
    })
    .finally(() => clearTimeout(timer));
}
