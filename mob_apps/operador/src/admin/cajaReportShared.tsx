import React, { useState } from 'react';

/**
 * Componentes compartidos entre las pantallas de reportes de Caja (Resumen, Métodos de pago,
 * y los que sigan) — la mecánica de descargar/enviar es idéntica en todos, solo cambia la URL
 * del endpoint y el nombre del archivo.
 */

/** Descarga el PDF vía fetch+blob, no con un <a href> directo — la API exige el header
    Authorization (Bearer), que solo el wrapper de fetch en AuthContext.tsx agrega a /api/*;
    una navegación de <a> normal no lo llevaría y el backend respondería 401. */
export function DownloadPdfButton({ pdfUrl, filename }: { pdfUrl: string; filename: string }) {
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleDownload = async () => {
    setDownloading(true);
    setError(null);
    try {
      const res = await fetch(pdfUrl);
      if (!res.ok) { setError('No se pudo generar el PDF.'); return; }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    } catch {
      setError('No se pudo conectar con el servidor.');
    } finally {
      setDownloading(false);
    }
  };

  return (
    <div className="flex-1">
      <button onClick={handleDownload} disabled={downloading}
        className="w-full flex items-center justify-center gap-2 py-3 rounded-2xl border border-outline-variant text-sm text-on-surface-variant active:bg-surface-container transition-colors disabled:opacity-40">
        <span className="material-symbols-outlined text-lg">{downloading ? 'progress_activity' : 'download'}</span>
        {downloading ? 'Generando…' : 'Descargar PDF'}
      </button>
      {error && <p className="text-xs text-error mt-1 text-center">{error}</p>}
    </div>
  );
}

/** Bottom sheet para confirmar/editar el destinatario antes de mandar el reporte por correo —
    precargado con el email del usuario logueado (caso más común: mandárselo a uno mismo). */
export function EmailReportSheet({ emailUrl, defaultEmail, onClose }: {
  emailUrl: string; defaultEmail: string; onClose: () => void;
}) {
  const [email, setEmail]   = useState(defaultEmail);
  const [sending, setSending] = useState(false);
  const [error, setError]     = useState<string | null>(null);
  const [sent, setSent]       = useState(false);

  const canSubmit = /\S+@\S+\.\S+/.test(email) && !sending;

  const handleSend = async () => {
    if (!canSubmit) return;
    setSending(true);
    setError(null);
    try {
      const res = await fetch(emailUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message ?? 'No se pudo enviar el correo.');
      setSent(true);
    } catch (e: any) {
      setError(e.message ?? 'No se pudo enviar el correo.');
    } finally {
      setSending(false);
    }
  };

  return (
    <>
      <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className="fixed left-0 right-0 bottom-0 z-50 bg-surface rounded-t-3xl shadow-2xl"
           style={{ maxHeight: '90vh', overflowY: 'auto' }}>
        <div className="flex justify-center pt-3 pb-1">
          <div className="w-10 h-1 rounded-full bg-outline-variant" />
        </div>
        <div className="px-4 pb-8 pt-2 flex flex-col gap-4">
          {sent ? (
            <>
              <div className="flex flex-col items-center gap-2 py-4">
                <span className="material-symbols-outlined text-4xl text-primary">mark_email_read</span>
                <p className="text-sm font-semibold text-on-surface">Reporte enviado a {email}</p>
              </div>
              <button onClick={onClose}
                className="w-full py-3.5 rounded-2xl font-semibold text-sm bg-primary text-on-primary active:scale-[0.98] transition-transform">
                Listo
              </button>
            </>
          ) : (
            <>
              <h2 className="text-base font-semibold text-on-surface">Enviar reporte por correo</h2>
              <div className="flex flex-col gap-1">
                <label className="text-xs text-on-surface-variant font-medium">Correo destino</label>
                <input type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="correo@ejemplo.com"
                  className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary" />
              </div>
              {error && <p className="text-sm text-error bg-error/10 rounded-xl px-3 py-2">{error}</p>}
              <button onClick={handleSend} disabled={!canSubmit}
                className="w-full py-3.5 rounded-2xl font-semibold text-sm bg-primary text-on-primary active:scale-[0.98] transition-transform disabled:opacity-40">
                {sending ? 'Enviando…' : 'Enviar'}
              </button>
            </>
          )}
        </div>
      </div>
    </>
  );
}

/** Barra de acciones — se repite igual en cada pantalla de reporte. */
export function ReportActionsBar({ pdfUrl, emailUrl, filename, defaultEmail }: {
  pdfUrl: string; emailUrl: string; filename: string; defaultEmail: string;
}) {
  const [showEmailSheet, setShowEmailSheet] = useState(false);

  return (
    <>
      <div className="flex gap-2">
        <DownloadPdfButton pdfUrl={pdfUrl} filename={filename} />
        <button onClick={() => setShowEmailSheet(true)}
          className="flex-1 flex items-center justify-center gap-2 py-3 rounded-2xl border border-outline-variant text-sm text-on-surface-variant active:bg-surface-container transition-colors">
          <span className="material-symbols-outlined text-lg">mail</span>
          Enviar por correo
        </button>
      </div>

      {showEmailSheet && (
        <EmailReportSheet
          emailUrl={emailUrl}
          defaultEmail={defaultEmail}
          onClose={() => setShowEmailSheet(false)}
        />
      )}
    </>
  );
}
