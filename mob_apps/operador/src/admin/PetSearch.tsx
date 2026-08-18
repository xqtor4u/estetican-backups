import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { peekCitaPresetDate, setNavCrumbs } from '../navState';
import { ScreenHeader } from '../ScreenHeader';
import { PhotoEditorModal } from '../PhotoEditorModal';
import { PhotoSourceSheet } from '../PhotoSourceSheet';
import { usePhotoPicker } from '../hooks/usePhotoPicker';
import { createLongPressHandlers } from '../hooks/useLongPress';

interface Pet {
  id: number;
  name: string;
  breed: string;
  owner: string | null;
  photo: string | null;
  next_visit: string | null;
}

function PetPhotoThumb({ pet, size, uploading, onLongPress }: { pet: Pet; size: 'sm' | 'lg'; uploading: boolean; onLongPress: () => void }) {
  const dims = size === 'lg' ? 'w-14 h-14' : 'w-8 h-8';
  const iconSize = size === 'lg' ? 'text-3xl' : 'text-base';
  return (
    <div
      {...createLongPressHandlers(onLongPress)}
      className={`${dims} rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0 select-none relative`}
      style={{ WebkitTouchCallout: 'none', WebkitUserSelect: 'none' }}
    >
      {pet.photo
        ? <img src={pet.photo} alt={pet.name} className="w-full h-full object-cover pointer-events-none" draggable={false} />
        : <span className={`material-symbols-outlined ${iconSize} text-primary pointer-events-none`} style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>
      }
      {uploading && (
        <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
          <span className="material-symbols-outlined text-white text-sm animate-spin">progress_activity</span>
        </div>
      )}
    </div>
  );
}

export function PetSearch() {
  const navigate = useNavigate();
  const [query, setQuery] = useState('');
  const [view, setView] = useState<'table' | 'cards'>('cards');
  const [pets, setPets] = useState<Pet[]>([]);
  const [loading, setLoading] = useState(true);

  /* ── Cambiar foto desde la lista (long-press) ──────────── */
  const [menuPetId, setMenuPetId] = useState<number | null>(null);
  const [editingPhoto, setEditingPhoto] = useState<{ file: File; pet: Pet } | null>(null);
  const [uploadingId, setUploadingId] = useState<number | null>(null);
  const [watermarkEnabled, setWatermarkEnabled] = useState(false);
  const activePetRef = useRef<Pet | null>(null);

  const { inputs: photoInputs, openCamera: openPhotoCamera, openGallery: openPhotoGallery } = usePhotoPicker(file => {
    if (activePetRef.current) setEditingPhoto({ file, pet: activePetRef.current });
  });

  useEffect(() => {
    fetch('/api/settings/photos')
      .then(r => r.ok ? r.json() : null)
      .then(data => { if (data) setWatermarkEnabled(!!data.watermark_enabled); })
      .catch(() => {});
  }, []);

  const openPhotoMenuFor = (pet: Pet) => setMenuPetId(pet.id);

  const uploadPetPhoto = async (pet: Pet, blob: Blob) => {
    setUploadingId(pet.id);
    try {
      const body = new FormData();
      body.append('photo', blob, 'photo.jpg');
      const res = await fetch(`/api/pets/${pet.id}/photo`, { method: 'POST', body, headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (res.ok) setPets(ps => ps.map(p => p.id === pet.id ? { ...p, photo: data.photo } : p));
    } finally {
      setUploadingId(null);
    }
  };

  const fetchPets = useCallback(async (search: string) => {
    setLoading(true);
    try {
      const url = search ? `/api/pets?search=${encodeURIComponent(search)}` : '/api/pets';
      const res = await fetch(url);
      const data = await res.json();
      setPets(data);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const t = setTimeout(() => fetchPets(query), query ? 300 : 0);
    return () => clearTimeout(t);
  }, [query, fetchPets]);

  const filtered = pets;

  // Si venimos de Agenda → + Cita → Nueva cita, saltar directo al formulario de cita en
  // vez de pasar por el detalle completo de la mascota — ese salto extra era el flujo
  // largo (y el punto donde se perdía la fecha elegida en Agenda) que se reportó.
  const citaPresetDate = peekCitaPresetDate();
  const citaPresetLabel = citaPresetDate
    ? (() => {
        const [y, m, d] = citaPresetDate.split('-').map(Number);
        return new Date(y, m - 1, d).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
      })()
    : null;

  const goToPet = (id: number) => {
    if (citaPresetDate) {
      navigate(`/mascotas/${id}/cita/nueva`);
      return;
    }
    const nc = [{ label: 'Mascotas', to: '/mascotas' }];
    setNavCrumbs(nc);
    navigate(`/mascotas/${id}`, { state: { _crumbs: nc } });
  };

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">
      <ScreenHeader
        title={citaPresetDate ? 'Elegí la mascota' : 'Mascotas'}
        subtitle={citaPresetLabel ? `Cita para ${citaPresetLabel}` : undefined}
        screenTag="MobPetSrch"
        onBack={() => navigate(-1)}
        noCrumbs
        rightAction={
          citaPresetDate ? undefined : (
            <button
              onClick={() => navigate('/clientes/seleccionar', { state: { returnTo: '/mascotas/nuevo' } })}
              className="flex items-center gap-1.5 bg-primary text-on-primary px-3 py-1.5 rounded-full text-sm font-semibold active:scale-95 transition-transform"
            >
              <span className="material-symbols-outlined text-lg">add</span>
              Nueva
            </button>
          )
        }
      />

      <div className="flex flex-col gap-3 px-4 pt-4">
        {/* Buscador */}
        <div className="flex items-center gap-2 bg-surface-container rounded-2xl px-4 py-2 border border-outline-variant">
          <span className="material-symbols-outlined text-on-surface-variant">search</span>
          <input
            type="text"
            placeholder="Buscar por nombre, raza o dueño…"
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

        {/* Toggle vista */}
        <div className="flex items-center justify-between">
          <span className="text-xs text-on-surface-variant">
            {filtered.length} resultado{filtered.length !== 1 ? 's' : ''}
          </span>
          <div className="flex rounded-xl overflow-hidden border border-outline-variant">
            <button
              onClick={() => setView('cards')}
              className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium transition-colors ${
                view === 'cards'
                  ? 'bg-primary text-on-primary'
                  : 'bg-surface text-on-surface-variant'
              }`}
            >
              <span className="material-symbols-outlined text-base">grid_view</span>
              Tarjetas
            </button>
            <button
              onClick={() => setView('table')}
              className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium transition-colors ${
                view === 'table'
                  ? 'bg-primary text-on-primary'
                  : 'bg-surface text-on-surface-variant'
              }`}
            >
              <span className="material-symbols-outlined text-base">table_rows</span>
              Tabla
            </button>
          </div>
        </div>

        {/* Vista tarjetas */}
        {view === 'cards' && (
          <div className="grid grid-cols-2 gap-3">
            {filtered.map(pet => (
              <button
                key={pet.id}
                onClick={() => goToPet(pet.id)}
                className="bg-surface border border-outline-variant rounded-2xl p-4 flex flex-col items-center gap-2 active:scale-95 transition-transform shadow-sm text-left"
              >
                <PetPhotoThumb pet={pet} size="lg" uploading={uploadingId === pet.id} onLongPress={() => openPhotoMenuFor(pet)} />
                <div className="w-full text-center">
                  <p className="font-bold text-on-surface text-sm">{pet.name}</p>
                  <p className="text-xs text-on-surface-variant truncate">{pet.owner ?? '—'}</p>
                  <p className="text-[10px] text-on-surface-variant/60 truncate">{pet.breed}</p>
                </div>
                {pet.next_visit
                  ? <span className="text-[10px] font-medium text-primary bg-primary/10 px-2 py-0.5 rounded-full">Cita: {pet.next_visit}</span>
                  : <span className="text-[10px] text-on-surface-variant/50">Sin cita</span>
                }
              </button>
            ))}
          </div>
        )}

        {/* Vista tabla */}
        {view === 'table' && (
          <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
            <div className="grid grid-cols-[1fr_1fr_auto] bg-surface-container-low border-b border-outline-variant px-4 py-2">
              <span className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Mascota</span>
              <span className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Dueño</span>
              <span className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Visita</span>
            </div>
            {filtered.map((pet, i) => (
              <button
                key={pet.id}
                onClick={() => goToPet(pet.id)}
                className={`w-full grid grid-cols-[1fr_1fr_auto] items-center px-4 py-3 active:bg-surface-container transition-colors text-left ${
                  i < filtered.length - 1 ? 'border-b border-outline-variant' : ''
                }`}
              >
                <div className="flex items-center gap-2">
                  <PetPhotoThumb pet={pet} size="sm" uploading={uploadingId === pet.id} onLongPress={() => openPhotoMenuFor(pet)} />
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-on-surface truncate">{pet.name}</p>
                    <p className="text-[10px] text-on-surface-variant/60 truncate">{pet.breed}</p>
                  </div>
                </div>
                <p className="text-xs text-on-surface truncate pr-2">{pet.owner ?? '—'}</p>
                <p className="text-xs whitespace-nowrap font-medium" style={{ color: pet.next_visit ? 'var(--color-primary)' : 'var(--color-on-surface-variant)' }}>
                  {pet.next_visit ?? 'Sin cita'}
                </p>
              </button>
            ))}
          </div>
        )}

        {loading && (
          <div className="flex justify-center py-16">
            <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
          </div>
        )}

        {!loading && filtered.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-on-surface-variant gap-2">
            <span className="material-symbols-outlined text-5xl">search_off</span>
            <p className="text-sm">{query ? `Sin resultados para "${query}"` : 'No hay mascotas registradas'}</p>
          </div>
        )}
      </div>

      {photoInputs}

      {menuPetId !== null && (
        <PhotoSourceSheet
          title={`Foto de ${pets.find(p => p.id === menuPetId)?.name ?? 'la mascota'}`}
          onPickCamera={() => {
            const pet = pets.find(p => p.id === menuPetId);
            setMenuPetId(null);
            if (pet) { activePetRef.current = pet; openPhotoCamera(); }
          }}
          onPickGallery={() => {
            const pet = pets.find(p => p.id === menuPetId);
            setMenuPetId(null);
            if (pet) { activePetRef.current = pet; openPhotoGallery(); }
          }}
          onClose={() => setMenuPetId(null)}
        />
      )}

      {editingPhoto && (
        <PhotoEditorModal
          file={editingPhoto.file}
          label={editingPhoto.pet.name}
          watermarkEnabled={watermarkEnabled}
          onCancel={() => setEditingPhoto(null)}
          onConfirm={blob => { const pet = editingPhoto.pet; setEditingPhoto(null); uploadPetPhoto(pet, blob); }}
        />
      )}
    </div>
  );
}
