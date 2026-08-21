import React, { useEffect, useState } from 'react';

interface WhatsAppTemplateOption { id: number; name: string }

interface WhatsAppMessageSheetProps {
  clientId: number;
  phone: string;
  onClose: () => void;
}

/**
 * Mismo patrón de overlay que `PhotoSourceSheet` (`fixed inset-0 z-50`, hermano de nivel
 * superior en el árbol, nunca anidado dentro de un elemento con `transform`) — ver esa nota
 * para el bug real que este patrón evita.
 *
 * Ofrece dos caminos para escribirle a un cliente por WhatsApp: mensaje directo (chat vacío,
 * como ya funcionaba) o una plantilla de contexto "cliente" con los datos del cliente ya
 * preformateados en el texto (`App\Support\WhatsApp\TemplateResolver::resolveForClient()`).
 */
export function WhatsAppMessageSheet({ clientId, phone, onClose }: WhatsAppMessageSheetProps) {
  const [templates, setTemplates] = useState<WhatsAppTemplateOption[] | null>(null);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/whatsapp-templates')
      .then(r => r.json())
      .then(data => setTemplates(Array.isArray(data) ? data : []))
      .catch(() => setTemplates([]));
  }, []);

  const openLink = async (templateId: number | null) => {
    setSending(true);
    setError(null);

    try {
      const params = new URLSearchParams({ phone });
      if (templateId) params.set('template_id', String(templateId));

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

  return (
    <div className="fixed inset-0 z-50 bg-black/60 flex items-end" onClick={onClose}>
      <div
        className="w-full bg-surface rounded-t-3xl p-4 pb-8 flex flex-col gap-2 max-h-[70vh] overflow-y-auto"
        onClick={e => e.stopPropagation()}
      >
        <p className="text-center text-sm font-semibold text-on-surface-variant mb-1">Enviar WhatsApp</p>

        {error && <p className="text-xs text-error text-center mb-1">{error}</p>}

        <button
          onClick={() => openLink(null)}
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
            onClick={() => openLink(t.id)}
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
      </div>
    </div>
  );
}
