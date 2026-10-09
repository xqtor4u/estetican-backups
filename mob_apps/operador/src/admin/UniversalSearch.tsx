import React, { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { setNavCrumbs } from '../navState';
import { ScreenHeader } from '../ScreenHeader';
import { WhatsAppMessageSheet } from '../WhatsAppMessageSheet';

/* ZEUS-032 (Fase 5 "Mínimo de Clicks Posible"): búsqueda universal — mascota, cliente, teléfono
   o cita en un solo cuadro. Reusa /api/search (Api\SearchController), que a su vez reusa
   TokenSearch tal cual ya la usan /api/pets y /api/clients — acá solo se muestran los 3 grupos. */

interface SearchClientRef { id: number; name: string; phone: string | null }
interface SearchPet { id: number; name: string; breed: string | null; photo: string | null; client: SearchClientRef | null }
interface SearchClient { id: number; name: string; phone: string | null; pet_count: number }
interface SearchBooking {
  id: number;
  date_label: string;
  time: string;
  status: string;
  pet: { id: number; name: string } | null;
  services: { name: string }[];
}
interface SearchResults { pets: SearchPet[]; clients: SearchClient[]; bookings: SearchBooking[] }
const EMPTY: SearchResults = { pets: [], clients: [], bookings: [] };

const STATUS_LABEL: Record<string, string> = {
  scheduled: 'Programada',
  work_order: 'En proceso',
  completed: 'Completada',
  cancelled: 'Cancelada',
  no_show: 'No se presentó',
};

export function UniversalSearch() {
  const navigate = useNavigate();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResults>(EMPTY);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const [waSheet, setWaSheet] = useState<{ clientId: number; phone: string; petId?: number } | null>(null);

  const runSearch = useCallback(async (q: string) => {
    if (q.trim().length < 2) { setResults(EMPTY); setSearched(false); setLoading(false); return; }
    setLoading(true);
    try {
      const res = await fetch(`/api/search?q=${encodeURIComponent(q)}`);
      const data = await res.json().catch(() => EMPTY);
      setResults(res.ok ? data : EMPTY);
    } catch {
      setResults(EMPTY);
    } finally {
      setSearched(true);
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const t = setTimeout(() => runSearch(query), 300);
    return () => clearTimeout(t);
  }, [query, runSearch]);

  const goPet = (id: number) => {
    setNavCrumbs([{ label: 'Buscar', to: '/buscar' }]);
    navigate(`/mascotas/${id}`, { state: { _crumbs: [{ label: 'Buscar', to: '/buscar' }] } });
  };
  const goClient = (id: number) => {
    setNavCrumbs([{ label: 'Buscar', to: '/buscar' }]);
    navigate(`/clientes/${id}`, { state: { _crumbs: [{ label: 'Buscar', to: '/buscar' }] } });
  };
  const goBooking = (id: number) => {
    setNavCrumbs([{ label: 'Buscar', to: '/buscar' }]);
    navigate(`/citas/${id}`, { state: { _crumbs: [{ label: 'Buscar', to: '/buscar' }] } });
  };
  const goAgendar = (petId: number) => navigate(`/mascotas/${petId}/cita/nueva`);

  const totalResults = results.pets.length + results.clients.length + results.bookings.length;

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">
      <ScreenHeader title="Buscar" screenTag="MobBuscar" onBack={() => navigate(-1)} hideSearch noCrumbs />

      <div className="flex flex-col gap-3 px-4 pt-4">
        <div className="flex items-center gap-2 bg-surface-container rounded-2xl px-4 py-2 border border-outline-variant">
          <span className="material-symbols-outlined text-on-surface-variant">search</span>
          <input
            type="text"
            placeholder="Mascota, cliente, teléfono o cita…"
            value={query}
            onChange={e => setQuery(e.target.value)}
            className="flex-1 bg-transparent text-on-surface placeholder:text-on-surface-variant outline-none text-sm"
            autoFocus
          />
          {query.length > 0 && (
            <button onClick={() => setQuery('')}>
              <span className="material-symbols-outlined text-on-surface-variant text-xl">close</span>
            </button>
          )}
        </div>

        {loading && (
          <div className="flex justify-center py-16">
            <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
          </div>
        )}

        {!loading && query.trim().length > 0 && query.trim().length < 2 && (
          <p className="text-xs text-on-surface-variant text-center py-8">Escribe al menos 2 caracteres.</p>
        )}

        {!loading && searched && totalResults === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-on-surface-variant gap-3">
            <span className="material-symbols-outlined text-5xl">search_off</span>
            <p className="text-sm">Sin resultados para "{query}"</p>
          </div>
        )}

        {!loading && results.pets.length > 0 && (
          <section className="flex flex-col gap-2">
            <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-widest px-1">Mascotas</p>
            <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
              {results.pets.map((pet, i) => (
                <div key={pet.id} className={`px-4 py-3 ${i < results.pets.length - 1 ? 'border-b border-outline-variant' : ''}`}>
                  <button onClick={() => goPet(pet.id)} className="flex items-center gap-3 w-full text-left active:opacity-70 transition-opacity">
                    <div className="w-10 h-10 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                      {pet.photo
                        ? <img src={pet.photo} className="w-full h-full object-cover" alt={pet.name} />
                        : <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-semibold text-on-surface truncate">{pet.name}</p>
                      <p className="text-xs text-on-surface-variant truncate">
                        {pet.breed ?? 'Sin raza'}{pet.client ? ` · ${pet.client.name}` : ''}
                      </p>
                    </div>
                    <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
                  </button>
                  <div className="flex items-center gap-2 mt-2 pl-[52px]">
                    <button
                      onClick={() => goAgendar(pet.id)}
                      className="min-h-9 flex items-center gap-1.5 bg-primary/10 text-primary border border-primary/30 px-3 rounded-full text-xs font-semibold active:scale-95 transition-transform"
                    >
                      <span className="material-symbols-outlined text-base">event_available</span>
                      Agendar
                    </button>
                    {pet.client?.phone && (
                      <>
                        <button
                          onClick={() => setWaSheet({ clientId: pet.client!.id, phone: pet.client!.phone!, petId: pet.id })}
                          className="min-h-9 min-w-9 flex items-center justify-center bg-green-100 text-green-700 rounded-full active:scale-95 transition-transform"
                          aria-label="WhatsApp"
                        >
                          <span className="material-symbols-outlined text-lg" style={{ fontVariationSettings: "'FILL' 1" }}>chat</span>
                        </button>
                        <a
                          href={`tel:${pet.client.phone}`}
                          className="min-h-9 min-w-9 flex items-center justify-center bg-surface-container text-on-surface-variant border border-outline-variant rounded-full active:scale-95 transition-transform"
                          aria-label="Llamar"
                        >
                          <span className="material-symbols-outlined text-lg">call</span>
                        </a>
                      </>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}

        {!loading && results.clients.length > 0 && (
          <section className="flex flex-col gap-2">
            <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-widest px-1">Clientes</p>
            <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
              {results.clients.map((client, i) => (
                <div key={client.id} className={`px-4 py-3 ${i < results.clients.length - 1 ? 'border-b border-outline-variant' : ''}`}>
                  <button onClick={() => goClient(client.id)} className="flex items-center gap-3 w-full text-left active:opacity-70 transition-opacity">
                    <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                      <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-semibold text-on-surface truncate">{client.name}</p>
                      <p className="text-xs text-on-surface-variant truncate">
                        {client.phone ?? 'Sin teléfono'} · {client.pet_count} mascota{client.pet_count !== 1 ? 's' : ''}
                      </p>
                    </div>
                    <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
                  </button>
                  {client.phone && (
                    <div className="flex items-center gap-2 mt-2 pl-[52px]">
                      <button
                        onClick={() => setWaSheet({ clientId: client.id, phone: client.phone! })}
                        className="min-h-9 min-w-9 flex items-center justify-center bg-green-100 text-green-700 rounded-full active:scale-95 transition-transform"
                        aria-label="WhatsApp"
                      >
                        <span className="material-symbols-outlined text-lg" style={{ fontVariationSettings: "'FILL' 1" }}>chat</span>
                      </button>
                      <a
                        href={`tel:${client.phone}`}
                        className="min-h-9 min-w-9 flex items-center justify-center bg-surface-container text-on-surface-variant border border-outline-variant rounded-full active:scale-95 transition-transform"
                        aria-label="Llamar"
                      >
                        <span className="material-symbols-outlined text-lg">call</span>
                      </a>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </section>
        )}

        {!loading && results.bookings.length > 0 && (
          <section className="flex flex-col gap-2">
            <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-widest px-1">Citas</p>
            <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
              {results.bookings.map((b, i) => (
                <button
                  key={b.id}
                  onClick={() => goBooking(b.id)}
                  className={`flex items-center gap-3 w-full px-4 py-3 text-left active:bg-surface-container transition-colors ${
                    i < results.bookings.length - 1 ? 'border-b border-outline-variant' : ''
                  }`}
                >
                  <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                    <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>event</span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-on-surface truncate">
                      {b.pet?.name ?? 'Cita'} · {b.date_label} {b.time}
                    </p>
                    <p className="text-xs text-on-surface-variant truncate">
                      {STATUS_LABEL[b.status] ?? b.status}
                      {b.services.length > 0 && ` · ${b.services.map(s => s.name).join(', ')}`}
                    </p>
                  </div>
                  <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
                </button>
              ))}
            </div>
          </section>
        )}
      </div>

      {waSheet && (
        <WhatsAppMessageSheet clientId={waSheet.clientId} phone={waSheet.phone} petId={waSheet.petId} onClose={() => setWaSheet(null)} />
      )}
    </div>
  );
}
