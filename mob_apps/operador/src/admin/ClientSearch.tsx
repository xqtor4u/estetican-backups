import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';

interface ClientRow {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  pet_count: number;
}

export function ClientSearch() {
  const navigate = useNavigate();
  const location = useLocation();

  // Estado de retorno: de dónde venimos y a dónde volver al seleccionar
  const returnTo: string | undefined = (location.state as any)?.returnTo;
  const isSelecting = !!returnTo;

  const [query, setQuery] = useState('');
  const [clients, setClients] = useState<ClientRow[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchClients = useCallback(async (search: string) => {
    setLoading(true);
    try {
      const url = search ? `/api/clients?search=${encodeURIComponent(search)}` : '/api/clients';
      const data = await fetch(url).then(r => r.json());
      setClients(data);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const t = setTimeout(() => fetchClients(query), query ? 300 : 0);
    return () => clearTimeout(t);
  }, [query, fetchClients]);

  const selectClient = (client: ClientRow) => {
    if (isSelecting) {
      // Volver a la pantalla que pidió la selección, llevando el cliente elegido
      navigate(returnTo, { state: { selectedClient: { id: client.id, name: client.name } } });
    } else {
      navigate(`/clientes/${client.id}`);
    }
  };

  const goNuevoCliente = () => {
    navigate('/clientes/nuevo', { state: { returnTo } });
  };

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">
      {/* Header */}
      <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
        <button onClick={() => navigate(-1)} className="p-2 rounded-full hover:bg-surface-container-high transition-colors">
          <span className="material-symbols-outlined text-on-surface">arrow_back</span>
        </button>
        <h1 className="font-bold text-lg text-on-surface flex-1">
          {isSelecting ? 'Seleccionar dueño' : 'Clientes'}
        </h1>
        <button
          onClick={goNuevoCliente}
          className="flex items-center gap-1.5 bg-primary text-on-primary px-3 py-1.5 rounded-full text-sm font-semibold active:scale-95 transition-transform"
        >
          <span className="material-symbols-outlined text-lg">add</span>
          Nuevo
        </button>
      </header>

      {/* Banner de contexto */}
      {isSelecting && (
        <div className="bg-primary/10 border-b border-primary/20 px-4 py-2 flex items-center gap-2">
          <span className="material-symbols-outlined text-primary text-base" style={{ fontVariationSettings: "'FILL' 1" }}>info</span>
          <p className="text-xs text-primary font-medium">Selecciona el dueño de la nueva mascota</p>
        </div>
      )}

      <div className="flex flex-col gap-3 px-4 pt-4">
        {/* Buscador */}
        <div className="flex items-center gap-2 bg-surface-container rounded-2xl px-4 py-2 border border-outline-variant">
          <span className="material-symbols-outlined text-on-surface-variant">search</span>
          <input
            type="text"
            placeholder="Buscar por nombre o teléfono…"
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

        <span className="text-xs text-on-surface-variant">
          {clients.length} resultado{clients.length !== 1 ? 's' : ''}
        </span>

        {/* Tabla */}
        {!loading && clients.length > 0 && (
          <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
            <div className="grid grid-cols-[1fr_auto_auto] bg-surface-container-low border-b border-outline-variant px-4 py-2">
              <span className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Cliente</span>
              <span className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide text-center px-3">Mascotas</span>
              <span className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide"></span>
            </div>
            {clients.map((client, i) => (
              <button
                key={client.id}
                onClick={() => selectClient(client)}
                className={`w-full grid grid-cols-[1fr_auto_auto] items-center px-4 py-3 active:bg-surface-container transition-colors text-left ${
                  i < clients.length - 1 ? 'border-b border-outline-variant' : ''
                }`}
              >
                <div className="min-w-0">
                  <p className="text-sm font-semibold text-on-surface truncate">{client.name}</p>
                  <p className="text-xs text-on-surface-variant truncate">{client.phone ?? client.email ?? '—'}</p>
                </div>
                <div className="flex items-center justify-center px-3">
                  <span className="flex items-center gap-1 text-xs text-on-surface-variant">
                    <span className="material-symbols-outlined text-sm" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>
                    {client.pet_count}
                  </span>
                </div>
                <span className="material-symbols-outlined text-on-surface-variant">
                  {isSelecting ? 'check_circle' : 'chevron_right'}
                </span>
              </button>
            ))}
          </div>
        )}

        {loading && (
          <div className="flex justify-center py-16">
            <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
          </div>
        )}

        {!loading && clients.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-on-surface-variant gap-3">
            <span className="material-symbols-outlined text-5xl">search_off</span>
            <p className="text-sm">{query ? `Sin resultados para "${query}"` : 'No hay clientes registrados'}</p>
            <button onClick={goNuevoCliente}
              className="flex items-center gap-1.5 bg-primary text-on-primary px-4 py-2 rounded-full text-sm font-semibold active:scale-95 transition-transform">
              <span className="material-symbols-outlined text-base">add</span>
              Dar de alta nuevo cliente
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
