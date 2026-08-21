import React, { useEffect, useState } from 'react';

interface WhatsAppTemplateOption { id: number; name: string; context: string }
interface LivePet { id: number; name: string }

interface WhatsAppMessageSheetProps {
  clientId: number;
  phone: string;
  /** Si ya se sabe qué mascota es (ej. la de la cita desde donde se abre), nunca se pregunta. */
  petId?: number;
  onClose: () => void;
}

/**
 * Mismo patrón de overlay que `PhotoSourceSheet` (`fixed inset-0 z-50`, hermano de nivel
 * superior en el árbol, nunca anidado dentro de un elemento con `transform`) — ver esa nota
 * para el bug real que este patrón evita.
 *
 * Ofrece dos caminos para escribirle a un cliente por WhatsApp: mensaje directo (chat vacío,
 * como ya funcionaba) o una plantilla de contexto "cliente"/"general" con los datos del cliente
 * ya preformateados en el texto (`App\Support\WhatsApp\TemplateResolver`). Si la plantilla es de
 * contexto "general" (puede usar `{mascota}`), no viene un `petId` fijo, y el cliente tiene más
 * de una mascota viva, se pregunta cuál antes de enviar — con una sola, o ninguna, no hace falta.
 */
export function WhatsAppMessageSheet({ clientId, phone, petId, onClose }: WhatsAppMessageSheetProps) {
  const [templates, setTemplates] = useState<WhatsAppTemplateOption[] | null>(null);
  const [step, setStep] = useState<'menu' | 'pets'>('menu');
  const [livePets, setLivePets] = useState<LivePet[] | null>(null);
  const [pendingTemplateId, setPendingTemplateId] = useState<number | null>(null);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/whatsapp-templates')
      .then(r => r.json())
      .then(data => setTemplates(Array.isArray(data) ? data : []))
      .catch(() => setTemplates([]));
  }, []);

  const doSend = async (templateId: number | null, chosenPetId: number | null) => {
    setSending(true);
    setError(null);

    try {
      const params = new URLSearchParams({ phone });
      if (templateId) params.set('template_id', String(templateId));
      if (chosenPetId) params.set('pet_id', String(chosenPetId));

      const res = await fetch(`/api/clients/${clientId}/whatsapp-link?${params.toString()}`);
      const data = await res.json();

      if (!res.ok) {
        setError(data.message || 'No se pudo generar el mensaje.');
        setSending(false);
        return;
      }

      window.open(data.wa_link, '_blank');
      onClose();
    } catch {
      setError('Error de red. Intenta de nuevo.');
      setSending(false);
    }
  };

  const choose = async (templateId: number | null, context: string | null) => {
    if (sending) return;

    if (petId || !templateId || context !== 'general') {
      await doSend(templateId, petId ?? null);
      return;
    }

    setSending(true);
    let pets = livePets;
    if (pets === null) {
      try {
        const res = await fetch(`/api/clients/${clientId}/whatsapp-live-pets`);
        pets = res.ok ? await res.json() : [];
      } catch {
        pets = [];
      }
      setLivePets(pets);
    }
    setSending(false);

    if (!pets || pets.length <= 1) {
      await doSend(templateId, null);
      return;
    }

    setPendingTemplateId(templateId);
    setStep('pets');
  };

  return (
    <div className="fixed inset-0 z-50 bg-black/60 flex items-end" onClick={onClose}>
      <div
        className="w-full bg-surface rounded-t-3xl p-4 pb-8 flex flex-col gap-2 max-h-[70vh] overflow-y-auto"
        onClick={e => e.stopPropagation()}
      >
        {step === 'menu' && (
          <>
            <p className="text-center text-sm font-semibold text-on-surface-variant mb-1">Enviar WhatsApp</p>

            {error && <p className="text-xs text-error text-center mb-1">{error}</p>}

            <button
              onClick={() => choose(null, null)}
              disabled={sending}
              className="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-surface-container active:bg-surface-container-high text-on-surface font-medium transition-colors disabled:opacity-60"
            >
              <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>chat</span>
              Mensaje directo (sin plantilla)
            </button>

            {templates === null && (
              <p className="text-xs text-on-surface-variant text-center py-2">Cargando plantillas…</p>
            )}

            {templates !== null && templates.length === 0 && (
              <p className="text-xs text-on-surface-variant text-center py-2">
                No hay plantillas de mensaje directo configuradas todavía.
              </p>
            )}

            {templates?.map(t => (
              <button
                key={t.id}
                onClick={() => choose(t.id, t.context)}
                disabled={sending}
                className="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-surface-container active:bg-surface-container-high text-on-surface font-medium transition-colors disabled:opacity-60"
              >
                <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>description</span>
                {t.name}
              </button>
            ))}

            <button
              onClick={onClose}
              className="flex items-center justify-center px-4 py-3.5 rounded-2xl border border-outline-variant text-on-surface-variant font-medium mt-1"
            >
              Cancelar
            </button>
          </>
        )}

        {step === 'pets' && (
          <>
            <p className="text-center text-sm font-semibold text-on-surface-variant mb-1">¿Para cuál mascota?</p>

            {error && <p className="text-xs text-error text-center mb-1">{error}</p>}

            {livePets?.map(p => (
              <button
                key={p.id}
                onClick={() => doSend(pendingTemplateId, p.id)}
                disabled={sending}
                className="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-surface-container active:bg-surface-container-high text-on-surface font-medium transition-colors disabled:opacity-60"
              >
                <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>
                {p.name}
              </button>
            ))}

            <button
              onClick={() => doSend(pendingTemplateId, null)}
              disabled={sending}
              className="flex items-center justify-center px-4 py-3.5 rounded-2xl text-on-surface-variant font-medium"
            >
              Ninguna en particular
            </button>

            <button
              onClick={() => setStep('menu')}
              disabled={sending}
              className="flex items-center justify-center px-4 py-3.5 rounded-2xl border border-outline-variant text-on-surface-variant font-medium mt-1"
            >
              Volver
            </button>
          </>
        )}
      </div>
    </div>
  );
}
