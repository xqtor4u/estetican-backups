import React, { useEffect, useState } from 'react';
import { useNavigate, useParams, useLocation } from 'react-router-dom';
import { setNavCrumbs } from '../navState';
import { ScreenHeader } from '../ScreenHeader';

interface Owner { id: number; name: string; phone: string | null }
interface Alert { id: number; description: string }
interface Booking { id: number; date: string; time: string; status: string }


interface Pet {
  id: number;
  name: string;
  species: string | null;
  breed: string | null;
  sex: string | null;
  size: string | null;
  coat_color: string | null;
  birth_date: string | null;
  age: string | null;
  is_sterilized: boolean;
  flagged_for_deletion: boolean;
  microchip: string | null;
  tattoo: string | null;
  notes: string | null;
  photo: string | null;
  owner: Owner | null;
  medical_alerts: Alert[];
  upcoming_bookings: Booking[];
}

type EditState = {
  name: string; species: string; breed: string; sex: string; size: string;
  coat_color: string; birth_date: string; death_date: string;
  microchip: string; tattoo: string; is_sterilized: boolean;
  flagged_for_deletion: boolean; notes: string;
};

const SEX_OPTS = [{ v: '', l: '—' }, { v: 'male', l: 'Macho' }, { v: 'female', l: 'Hembra' }];
const SIZE_OPTS = [{ v: '', l: '—' }, { v: 'small', l: 'Pequeño' }, { v: 'medium', l: 'Mediano' }, { v: 'large', l: 'Grande' }, { v: 'giant', l: 'Gigante' }];
const SEX_LABEL: Record<string, string> = { male: 'Macho', female: 'Hembra' };
const SIZE_LABEL: Record<string, string> = { small: 'Pequeño', medium: 'Mediano', large: 'Grande', giant: 'Gigante' };

/* ── Subcomponentes de formulario ───────────────────────── */
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <label className="text-xs text-on-surface-variant">{label}</label>
      {children}
    </div>
  );
}
function TextInput({ value, onChange, placeholder }: { value: string; onChange: (v: string) => void; placeholder?: string }) {
  return (
    <input type="text" value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
      className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary" />
  );
}
function DateInput({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return (
    <input type="date" value={value} onChange={e => onChange(e.target.value)}
      className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary" />
  );
}
function SelectInput({ value, onChange, opts }: { value: string; onChange: (v: string) => void; opts: { v: string; l: string }[] }) {
  return (
    <select value={value} onChange={e => onChange(e.target.value)}
      className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary">
      {opts.map(o => <option key={o.v} value={o.v}>{o.l}</option>)}
    </select>
  );
}
function Toggle({ value, onChange, label }: { value: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <button onClick={() => onChange(!value)}
      className={`flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm border transition-colors ${
        value ? 'bg-primary/10 text-primary border-primary/30' : 'bg-surface-container text-on-surface-variant border-outline-variant'
      }`}>
      <span className="material-symbols-outlined text-base" style={{ fontVariationSettings: `'FILL' ${value ? 1 : 0}` }}>
        {value ? 'check_circle' : 'radio_button_unchecked'}
      </span>
      {label}
    </button>
  );
}

/* ── Chip de vista ──────────────────────────────────────── */
function Chip({ icon, label, highlight }: { icon: string; label: string; highlight?: boolean }) {
  return (
    <span className={`flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium ${
      highlight ? 'bg-primary/10 text-primary' : 'bg-surface-container text-on-surface-variant border border-outline-variant'
    }`}>
      <span className="material-symbols-outlined text-sm" style={{ fontVariationSettings: "'FILL' 1" }}>{icon}</span>
      {label}
    </span>
  );
}

/* ══════════════════════════════════════════════════════════
   Formulario de alta de nueva mascota
   ══════════════════════════════════════════════════════════ */
function NewPetForm({ client, navigate }: { client: { id: number; name: string }; navigate: ReturnType<typeof useNavigate> }) {
  const STORAGE_KEY = `new_pet_form_${client.id}`;

  const [form, setForm] = useState(() => {
    try {
      const saved = sessionStorage.getItem(STORAGE_KEY);
      if (saved) return JSON.parse(saved);
    } catch {}
    return { name: '', species: 'Perro', breed: '', sex: '', size: '',
             coat_color: '', birth_date: '', microchip: '', tattoo: '',
             is_sterilized: false, notes: '' };
  });
  const PHOTO_KEY = `new_pet_photo_${client.id}`;

  const [photo, setPhoto] = useState<File | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(() => {
    return sessionStorage.getItem(PHOTO_KEY) ?? null;
  });
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Persiste campos de texto en sessionStorage
  useEffect(() => {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(form));
  }, [form, STORAGE_KEY]);

  const set = (key: string) => (val: string | boolean) =>
    setForm((f: typeof form) => ({ ...f, [key]: val }));

  const handlePhoto = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setPhoto(file);

    // Convertir a base64 para sobrevivir recargas de página
    const reader = new FileReader();
    reader.onload = () => {
      const b64 = reader.result as string;
      setPhotoPreview(b64);
      sessionStorage.setItem(PHOTO_KEY, b64);
    };
    reader.readAsDataURL(file);
  };

  const save = async () => {
    if (!form.name.trim()) { setErrors({ name: 'El nombre es obligatorio' }); return; }
    setSaving(true);
    setErrors({});

    const body = new FormData();
    body.append('client_id', String(client.id));
    body.append('name', form.name);
    if (form.species)   body.append('species', form.species);
    if (form.breed)     body.append('breed', form.breed);
    if (form.sex)       body.append('sex', form.sex);
    if (form.size)      body.append('size', form.size);
    if (form.coat_color)  body.append('coat_color', form.coat_color);
    if (form.birth_date)  body.append('birth_date', form.birth_date);
    if (form.microchip)   body.append('microchip_code', form.microchip);
    if (form.tattoo)      body.append('tattoo_code', form.tattoo);
    body.append('is_sterilized', form.is_sterilized ? '1' : '0');
    if (form.notes)     body.append('notes', form.notes);

    // Si hay File en memoria, usarlo; si no, recuperar desde base64 en sessionStorage
    if (photo) {
      body.append('photo', photo);
    } else if (photoPreview?.startsWith('data:')) {
      const res2 = await fetch(photoPreview);
      const blob = await res2.blob();
      body.append('photo', blob, 'photo.jpg');
    }

    const res = await fetch('/api/pets', { method: 'POST', body, headers: { Accept: 'application/json' } });
    const data = await res.json();

    if (!res.ok) {
      setErrors(data.errors ?? { general: 'Error al guardar' });
      setSaving(false);
      return;
    }
    sessionStorage.removeItem(STORAGE_KEY);
    sessionStorage.removeItem(PHOTO_KEY);
    navigate(`/mascotas/${data.id}`, { replace: true });
  };

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">
      {/* Header */}
      <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
        <button onClick={() => navigate(-1)} className="p-2 rounded-full hover:bg-surface-container-high">
          <span className="material-symbols-outlined text-on-surface">arrow_back</span>
        </button>
        <h1 className="font-bold text-lg text-on-surface flex-1">Nueva mascota <span className="text-[9px] font-mono text-on-surface-variant/30 font-normal">MobPetNew</span></h1>
        <button onClick={save} disabled={saving}
          className="flex items-center gap-1 bg-primary text-on-primary px-3 py-1.5 rounded-full text-sm font-semibold active:scale-95 transition-transform disabled:opacity-60">
          <span className="material-symbols-outlined text-base">save</span>
          {saving ? 'Guardando…' : 'Guardar'}
        </button>
      </header>

      {/* Banner dueño */}
      <div className="bg-surface border-b border-outline-variant px-4 py-3 flex items-center gap-3">
        <div className="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
          <span className="material-symbols-outlined text-primary text-lg" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-[10px] text-on-surface-variant uppercase tracking-wide">Dueño</p>
          <p className="text-sm font-semibold text-on-surface truncate">{client.name}</p>
        </div>
        <button
          onClick={() => navigate('/clientes/seleccionar', { state: { returnTo: '/mascotas/nuevo' } })}
          className="flex items-center gap-1 text-xs font-medium text-primary border border-primary/40 bg-primary/5 px-3 py-1.5 rounded-full active:scale-95 transition-transform shrink-0">
          <span className="material-symbols-outlined text-sm">swap_horiz</span>
          Cambiar
        </button>
      </div>

      <div className="flex flex-col gap-4 px-4 pt-4">

        {/* Foto */}
        <div className="flex items-center gap-4">
          <label className="w-24 h-24 rounded-2xl bg-primary/10 overflow-hidden flex items-center justify-center shrink-0 cursor-pointer border-2 border-dashed border-primary/30 active:scale-95 transition-transform">
            {photoPreview
              ? <img src={photoPreview} alt="preview" className="w-full h-full object-cover" />
              : <div className="flex flex-col items-center gap-1 text-primary">
                  <span className="material-symbols-outlined text-3xl" style={{ fontVariationSettings: "'FILL' 1" }}>add_a_photo</span>
                  <span className="text-[10px] font-medium">Foto</span>
                </div>
            }
            <input type="file" accept="image/*" className="hidden" onChange={handlePhoto} />
          </label>
          <p className="text-xs text-on-surface-variant leading-relaxed">Toca para tomar o elegir una foto desde la galería</p>
        </div>

        {/* Campos */}
        <div className="grid grid-cols-2 gap-3">
          <div className="col-span-2">
            <Field label="Nombre *">
              <TextInput value={form.name} onChange={set('name')} placeholder="Nombre de la mascota" />
              {errors.name && <p className="text-xs text-error mt-1">{errors.name}</p>}
            </Field>
          </div>
          <Field label="Especie">
            <TextInput value={form.species} onChange={set('species')} placeholder="Perro, Gato…" />
          </Field>
          <Field label="Raza">
            <TextInput value={form.breed} onChange={set('breed')} />
          </Field>
          <Field label="Sexo">
            <SelectInput value={form.sex} onChange={set('sex')} opts={SEX_OPTS} />
          </Field>
          <Field label="Tamaño">
            <SelectInput value={form.size} onChange={set('size')} opts={SIZE_OPTS} />
          </Field>
          <div className="col-span-2">
            <Field label="Color de pelaje">
              <TextInput value={form.coat_color} onChange={set('coat_color')} />
            </Field>
          </div>
          <Field label="Fecha de nacimiento">
            <DateInput value={form.birth_date} onChange={set('birth_date')} />
          </Field>
          <div /> {/* espacio */}
          <Field label="Microchip">
            <TextInput value={form.microchip} onChange={set('microchip')} />
          </Field>
          <Field label="Tatuaje">
            <TextInput value={form.tattoo} onChange={set('tattoo')} />
          </Field>
        </div>

        <Toggle value={form.is_sterilized} onChange={val => set('is_sterilized')(val)} label="Esterilizado" />

        <Field label="Notas">
          <textarea value={form.notes} onChange={e => set('notes')(e.target.value)} rows={3}
            placeholder="Observaciones, carácter, cuidados especiales…"
            className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary resize-none" />
        </Field>

        {errors.general && (
          <p className="text-sm text-error text-center">{errors.general}</p>
        )}

      </div>
    </div>
  );
}

function petToEdits(pet: Pet): EditState {
  return {
    name: pet.name, species: pet.species ?? '', breed: pet.breed ?? '',
    sex: pet.sex ?? '', size: pet.size ?? '', coat_color: pet.coat_color ?? '',
    birth_date: '', death_date: '', microchip: pet.microchip ?? '',
    tattoo: pet.tattoo ?? '', is_sterilized: pet.is_sterilized,
    flagged_for_deletion: pet.flagged_for_deletion, notes: pet.notes ?? '',
  };
}

/* ══════════════════════════════════════════════════════════ */
export function PetDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const isNew = !id || id === 'nuevo';
  const selectedClient: { id: number; name: string } | undefined = (location.state as any)?.selectedClient;

  const [pet, setPet] = useState<Pet | null>(null);
  const [loading, setLoading] = useState(!isNew);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [edits, setEdits] = useState<EditState | null>(null);

  const set = (key: keyof EditState) => (val: string | boolean) =>
    setEdits(d => d ? { ...d, [key]: val } : d);

  useEffect(() => {
    if (isNew) return;
    fetch(`/api/pets/${id}`).then(r => r.json()).then((data: Pet) => {
      setPet(data);
      setEdits(petToEdits(data));
      setLoading(false);
    });
  }, [id, isNew]);

  const cancelEdit = () => { setEditing(false); if (pet) setEdits(petToEdits(pet)); };

  const save = async () => {
    if (!pet || !edits) return;
    setSaving(true);
    const body: Record<string, unknown> = {
      name: edits.name, species: edits.species || null, breed: edits.breed || null,
      sex: edits.sex || null, size: edits.size || null, coat_color: edits.coat_color || null,
      microchip_code: edits.microchip || null, tattoo_code: edits.tattoo || null,
      is_sterilized: edits.is_sterilized, flagged_for_deletion: edits.flagged_for_deletion,
      notes: edits.notes || null,
    };
    if (edits.birth_date) body.birth_date = edits.birth_date;
    if (edits.death_date) body.death_date = edits.death_date;

    await fetch(`/api/pets/${pet.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    });
    const updated: Pet = await fetch(`/api/pets/${pet.id}`).then(r => r.json());
    setPet(updated);
    setEdits(petToEdits(updated));
    setEditing(false);
    setSaving(false);
  };

  /* ── Estados de carga / nuevo ─────────────────────────── */
  if (loading) return (
    <div className="min-h-screen bg-background flex items-center justify-center">
      <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
    </div>
  );

  if (isNew) {
    // Sin dueño seleccionado → ir a buscar/crear dueño primero
    if (!selectedClient) {
      return (
        <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">
          <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
            <button onClick={() => navigate(-1)} className="p-2 rounded-full hover:bg-surface-container-high">
              <span className="material-symbols-outlined text-on-surface">arrow_back</span>
            </button>
            <h1 className="font-bold text-lg text-on-surface flex-1">Nueva mascota <span className="text-[9px] font-mono text-on-surface-variant/30 font-normal">MobPetNew</span></h1>
          </header>
          <div className="flex flex-col items-center justify-center flex-1 gap-4 p-8 text-center">
            <span className="material-symbols-outlined text-6xl text-on-surface-variant/40" style={{ fontVariationSettings: "'FILL' 1" }}>person_search</span>
            <div>
              <p className="font-semibold text-on-surface mb-1">Primero selecciona al dueño</p>
              <p className="text-sm text-on-surface-variant">Busca un cliente existente o dale de alta nuevo</p>
            </div>
            <button
              onClick={() => navigate('/clientes/seleccionar', { state: { returnTo: '/mascotas/nuevo' } })}
              className="flex items-center gap-2 bg-primary text-on-primary px-5 py-3 rounded-2xl font-semibold active:scale-95 transition-transform"
            >
              <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>person_search</span>
              Buscar / crear dueño
            </button>
          </div>
        </div>
      );
    }

    // Con dueño seleccionado → formulario completo de nueva mascota
    return <NewPetForm client={selectedClient} navigate={navigate} />;
  }

  if (!pet || !edits) return null;

  /* ── Render principal ─────────────────────────────────── */
  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">

      <ScreenHeader
        title={editing ? edits.name || pet.name : pet.name}
        screenTag="MobPetDet"
        noCrumbs={editing}
        rightAction={editing ? (
          <div className="flex items-center gap-2">
            <button onClick={cancelEdit} className="px-3 py-1.5 rounded-full text-sm text-on-surface-variant border border-outline-variant active:scale-95 transition-transform">
              Cancelar
            </button>
            <button onClick={save} disabled={saving}
              className="flex items-center gap-1 bg-primary text-on-primary px-3 py-1.5 rounded-full text-sm font-semibold active:scale-95 transition-transform disabled:opacity-60">
              <span className="material-symbols-outlined text-base">save</span>
              {saving ? 'Guardando…' : 'Guardar'}
            </button>
          </div>
        ) : (
          <div className="flex items-center gap-1">
            <button onClick={() => { setNavCrumbs([{ label: pet.name, to: `/mascotas/${pet.id}` }]); navigate(`/mascotas/${pet.id}/cita/nueva`); }}
              className="p-2 rounded-full hover:bg-surface-container-high transition-colors" title="Agregar cita">
              <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>event_add</span>
            </button>
            <button onClick={() => setEditing(true)} className="p-2 rounded-full hover:bg-surface-container-high transition-colors" title="Editar">
              <span className="material-symbols-outlined text-on-surface">edit</span>
            </button>
          </div>
        )}
      />

      <div className="flex flex-col gap-4 px-4 pt-5 pb-4">

        {/* Bandera de eliminación */}
        {(pet.flagged_for_deletion && !editing) && (
          <div className="bg-error/10 border border-error/30 rounded-2xl px-4 py-3 flex items-center gap-2">
            <span className="material-symbols-outlined text-error" style={{ fontVariationSettings: "'FILL' 1" }}>flag</span>
            <p className="text-sm text-error font-medium">Marcada para eliminación — pendiente en backoffice</p>
          </div>
        )}

        {/* ── VISTA ────────────────────────────────────────── */}
        {!editing && (
          <>
            {/* Foto + nombre */}
            <div className="flex items-center gap-4">
              <div className="w-24 h-24 rounded-2xl bg-primary/10 overflow-hidden flex items-center justify-center shrink-0 shadow">
                {pet.photo
                  ? <img src={pet.photo} alt={pet.name} className="w-full h-full object-cover" />
                  : <span className="material-symbols-outlined text-5xl text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>
                }
              </div>
              <div className="flex-1 min-w-0">
                <h2 className="text-2xl font-bold text-on-surface">{pet.name}</h2>
                <p className="text-sm text-on-surface-variant">{pet.breed}</p>
                {pet.age && <p className="text-xs text-on-surface-variant/70 mt-0.5">{pet.age}</p>}
              </div>
            </div>

            {/* Chips */}
            <div className="flex flex-wrap gap-2">
              {pet.sex && <Chip icon={pet.sex === 'male' ? 'male' : 'female'} label={SEX_LABEL[pet.sex] ?? pet.sex} />}
              {pet.size && <Chip icon="straighten" label={SIZE_LABEL[pet.size] ?? pet.size} />}
              {pet.coat_color && <Chip icon="palette" label={pet.coat_color} />}
              <Chip icon={pet.is_sterilized ? 'check_circle' : 'cancel'} label={pet.is_sterilized ? 'Esterilizado' : 'Sin esterilizar'} highlight={pet.is_sterilized} />
            </div>

            {/* Dueño */}
            {pet.owner && (
              <button onClick={() => navigate(`/clientes/${pet.owner!.id}`)}
                className="bg-surface border border-outline-variant rounded-2xl px-4 py-3 flex items-center gap-3 w-full text-left active:bg-surface-container transition-colors">
                <div className="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center shrink-0">
                  <span className="material-symbols-outlined text-secondary" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-xs text-on-surface-variant">Dueño</p>
                  <p className="text-sm font-semibold text-on-surface truncate">{pet.owner.name}</p>
                  {pet.owner.phone && <p className="text-xs text-on-surface-variant">{pet.owner.phone}</p>}
                </div>
                <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
              </button>
            )}

            {/* Alertas médicas */}
            {pet.medical_alerts.length > 0 && (
              <div className="flex flex-col gap-2">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Alertas médicas</p>
                {pet.medical_alerts.map(a => (
                  <div key={a.id} className="bg-error/10 border border-error/30 rounded-2xl px-4 py-3 flex items-start gap-2">
                    <span className="material-symbols-outlined text-error mt-0.5" style={{ fontVariationSettings: "'FILL' 1" }}>warning</span>
                    <p className="text-sm text-on-surface">{a.description}</p>
                  </div>
                ))}
              </div>
            )}

            {/* Identificación */}
            {(pet.microchip || pet.tattoo) && (
              <div className="bg-surface border border-outline-variant rounded-2xl px-4 py-3 flex flex-col gap-2">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Identificación</p>
                {pet.microchip && <div className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-base text-on-surface-variant">memory</span>
                  <span className="text-xs text-on-surface-variant">Microchip:</span>
                  <span className="text-sm font-mono text-on-surface">{pet.microchip}</span>
                </div>}
                {pet.tattoo && <div className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-base text-on-surface-variant">tag</span>
                  <span className="text-xs text-on-surface-variant">Tatuaje:</span>
                  <span className="text-sm font-mono text-on-surface">{pet.tattoo}</span>
                </div>}
              </div>
            )}

            {/* Próximas citas */}
            {pet.upcoming_bookings.length > 0 && (
              <div className="flex flex-col gap-2">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Próximas citas</p>
                {pet.upcoming_bookings.map(b => (
                  <button key={b.id} onClick={() => { setNavCrumbs([{ label: pet.name, to: `/mascotas/${pet.id}` }]); navigate(`/citas/${b.id}`); }}
                    className="bg-surface border border-outline-variant rounded-2xl px-4 py-3 flex items-center gap-3 w-full text-left active:bg-surface-container transition-colors">
                    <span className="material-symbols-outlined text-primary text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>event</span>
                    <div className="flex-1">
                      <p className="text-sm font-semibold">{b.date}</p>
                      <p className="text-xs text-on-surface-variant">{b.time}</p>
                    </div>
                    <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ${
                      b.status === 'work_order' ? 'bg-secondary-container text-on-secondary-container' : 'bg-surface-container text-on-surface-variant'
                    }`}>{b.status === 'work_order' ? 'En curso' : 'Agendada'}</span>
                    <span className="material-symbols-outlined text-on-surface-variant text-lg">chevron_right</span>
                  </button>
                ))}
              </div>
            )}

            {/* Botón de historial de trabajos */}
            <button onClick={() => { setNavCrumbs([{ label: pet.name, to: `/mascotas/${pet.id}` }]); navigate(`/mascotas/${pet.id}/trabajos`); }}
              className="w-full flex items-center gap-3 bg-surface border border-outline-variant rounded-2xl px-4 py-3 active:bg-surface-container transition-colors text-left">
              <span className="material-symbols-outlined text-primary text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>work_history</span>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-on-surface">Historial de trabajos</p>
                <p className="text-xs text-on-surface-variant">Servicios, cobros y movimientos</p>
              </div>
              <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
            </button>

            {/* Notas */}
            {pet.notes && (
              <div className="bg-surface-container-low border border-outline-variant rounded-2xl px-4 py-3">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1">Notas</p>
                <p className="text-sm text-on-surface leading-relaxed">{pet.notes}</p>
              </div>
            )}
          </>
        )}

        {/* ── EDICIÓN ──────────────────────────────────────── */}
        {editing && edits && (
          <div className="flex flex-col gap-3">

            {/* Foto (read-only en mobile) */}
            <div className="flex items-center gap-4 pb-2">
              <div className="w-20 h-20 rounded-2xl bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                {pet.photo
                  ? <img src={pet.photo} alt={pet.name} className="w-full h-full object-cover" />
                  : <span className="material-symbols-outlined text-4xl text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>
                }
              </div>
              <p className="text-xs text-on-surface-variant">La foto se gestiona desde el backoffice</p>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="col-span-2"><Field label="Nombre"><TextInput value={edits.name} onChange={set('name')} /></Field></div>
              <Field label="Especie"><TextInput value={edits.species} onChange={set('species')} placeholder="Perro, Gato…" /></Field>
              <Field label="Raza"><TextInput value={edits.breed} onChange={set('breed')} /></Field>
              <Field label="Sexo"><SelectInput value={edits.sex} onChange={set('sex')} opts={SEX_OPTS} /></Field>
              <Field label="Tamaño"><SelectInput value={edits.size} onChange={set('size')} opts={SIZE_OPTS} /></Field>
              <div className="col-span-2"><Field label="Color de pelaje"><TextInput value={edits.coat_color} onChange={set('coat_color')} /></Field></div>
              <Field label="Fecha de nacimiento"><DateInput value={edits.birth_date} onChange={set('birth_date')} /></Field>
              <Field label="Fecha de defunción"><DateInput value={edits.death_date} onChange={set('death_date')} /></Field>
              <Field label="Microchip"><TextInput value={edits.microchip} onChange={set('microchip')} /></Field>
              <Field label="Tatuaje"><TextInput value={edits.tattoo} onChange={set('tattoo')} /></Field>
            </div>

            <Toggle value={edits.is_sterilized} onChange={set('is_sterilized') as (v: boolean) => void} label="Esterilizado" />

            <Field label="Notas">
              <textarea value={edits.notes} onChange={e => set('notes')(e.target.value)} rows={3}
                placeholder="Agregar notas…"
                className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary resize-none" />
            </Field>

            {/* Cambiar dueño — placeholder */}
            <button className="flex items-center gap-2 text-sm text-on-surface-variant border border-dashed border-outline-variant rounded-xl px-3 py-2.5 w-full">
              <span className="material-symbols-outlined text-base">person_swap</span>
              Cambiar dueño — próximamente
            </button>

            {/* Marcar para eliminación */}
            <button onClick={() => set('flagged_for_deletion')(!edits.flagged_for_deletion)}
              className={`flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-medium border transition-colors w-full ${
                edits.flagged_for_deletion ? 'bg-error/10 text-error border-error/40' : 'bg-surface-container text-on-surface-variant border-outline-variant'
              }`}>
              <span className="material-symbols-outlined text-base" style={{ fontVariationSettings: `'FILL' ${edits.flagged_for_deletion ? 1 : 0}` }}>
                {edits.flagged_for_deletion ? 'flag' : 'outlined_flag'}
              </span>
              {edits.flagged_for_deletion ? 'Quitar bandera de eliminación' : 'Marcar para eliminación'}
            </button>
          </div>
        )}

      </div>
    </div>
  );
}
