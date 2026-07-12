/**
 * Desbloqueo biométrico 100% local (Face ID / huella / PIN del sistema) vía WebAuthn.
 * No hay verificación en el servidor — la credencial se crea y se guarda en este mismo
 * dispositivo, y solo sirve como candado local sobre una sesión que ya es válida
 * (el token de la API no depende de esto). Por eso el "challenge" puede ser aleatorio
 * generado acá, sin pedirlo al backend.
 */

const CRED_KEY_PREFIX = 'estetican:webauthn:';

export function isWebAuthnAvailable(): boolean {
  return typeof window !== 'undefined' && !!window.PublicKeyCredential;
}

export function hasRegisteredCredential(userId: number): boolean {
  return !!localStorage.getItem(CRED_KEY_PREFIX + userId);
}

export function forgetBiometric(userId: number): void {
  localStorage.removeItem(CRED_KEY_PREFIX + userId);
}

function base64urlToBuffer(base64url: string): Uint8Array {
  const padding = '='.repeat((4 - (base64url.length % 4)) % 4);
  const base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const buffer = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) buffer[i] = raw.charCodeAt(i);
  return buffer;
}

export async function registerBiometric(userId: number, userName: string): Promise<boolean> {
  if (!isWebAuthnAvailable()) return false;

  try {
    const challenge = crypto.getRandomValues(new Uint8Array(32));
    const credential = await navigator.credentials.create({
      publicKey: {
        challenge,
        rp: { name: 'EstetiCAN Operador' },
        user: {
          id: new TextEncoder().encode(String(userId)),
          name: userName,
          displayName: userName,
        },
        pubKeyCredParams: [
          { alg: -7, type: 'public-key' },   // ES256
          { alg: -257, type: 'public-key' }, // RS256
        ],
        authenticatorSelection: {
          authenticatorAttachment: 'platform',
          userVerification: 'required',
        },
        timeout: 60000,
      },
    }) as PublicKeyCredential | null;

    if (!credential) return false;

    localStorage.setItem(CRED_KEY_PREFIX + userId, credential.id);
    return true;
  } catch {
    return false;
  }
}

export async function verifyBiometric(userId: number): Promise<boolean> {
  const credId = localStorage.getItem(CRED_KEY_PREFIX + userId);
  if (!credId || !isWebAuthnAvailable()) return false;

  try {
    const challenge = crypto.getRandomValues(new Uint8Array(32));
    const assertion = await navigator.credentials.get({
      publicKey: {
        challenge,
        allowCredentials: [{ id: base64urlToBuffer(credId), type: 'public-key' }],
        userVerification: 'required',
        timeout: 60000,
      },
    });
    return !!assertion;
  } catch {
    return false;
  }
}
