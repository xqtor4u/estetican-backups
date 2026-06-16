import React, { useEffect, useState } from 'react';
import { useNavigate, useParams, useLocation } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';

interface Phone  { id: number; number: string; type: string }
interface PetRef { id: number; name: string; breed: string | null; photo: string | null }

interface Client {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  zip_code: string | null;
  notes: string | null;
  phones: Phone[];
  pets: PetRef[];
}

type PhoneEdit = { number: string; type: string };

type EditState = {
  first_name: string; last_name: string; email: string;
  address: string; city: string; state: string; zip_code: string; notes: string;
  phones: PhoneEdit[];
};

/* ── Subcomponentes reutilizables (mismo patrón que PetDetail) ── */
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <label className="text-xs text-on-surface-variant">{label}</label>
      {children}
    </div>
  );
}
function TextInput({ value, onChange, placeholder, type = 'text' }: { value: string; onChange: (v: string) => void; placeholder?: string; type?: string }) {
  return (
    <input type={type} value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
      className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary" />
  );
}

function clientToEdits(c: Client): EditState {
  return {
    first_name: c.first_name, last_name: c.last_name ?? '',
    email: c.email ?? '', address: c.address ?? '',
    city: c.city ?? '', state: c.state ?? '',
    zip_code: c.zip_code ?? '', notes: c.notes ?? '',
    phones: c.phones.length
      ? c.phones.map(p => ({ number: p.number, type: p.type }))
      : [{ number: '', type: 'mobile' }],
  };
}

const PHONE_TYPE: Record<string, string> = { mobile: 'Celular', home: 'Casa', work: 'Trabajo' };

/* ══════════════════════════════════════════════════════════
   Formulario de alta de nuevo cliente
   ══════════════════════════════════════════════════════════ */
function NewClientForm({ navigate, returnTo }: { navigate: ReturnType<typeof useNavigate>; returnTo?: string }) {
  const [form, setForm] = useState({
    first_name: '', last_name: '', phone: '', email: '',
    address: '', city: '', state: '', zip_code: '', notes: '',
  });
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const set = (key: string) => (val: string) =>
    setForm(f => ({ ...f, [key]: val }));

  const save = async () => {
    const errs: Record<string, string> = {};
    if (!form.first_name.trim()) errs.first_name = 'El nombre es obligatorio';
    if (!form.phone.trim())      errs.phone      = 'El teléfono es obligatorio';
    if (Object.keys(errs).length) { setErrors(errs); return; }
    setSaving(true);
    setErrors({});

    const res = await fetch('/api/clients', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        first_name: form.first_name,
        last_name:  form.last_name  || null,
        phone:      form.phone,
        email:      form.email      || null,
        address:    form.address    || null,
        city:       form.city       || null,
        state:      form.state      || null,
        zip_code:   form.zip_code   || null,
        notes:      form.notes      || null,
      }),
    });
    const data = await res.json();

    if (!res.ok) {
      setErrors(data.errors ?? { general: 'Error al guardar' });
      setSaving(false);
      return;
    }

    if (returnTo) {
      // Volver a donde veníamos (e.g. nueva mascota) con el cliente recién creado
      navigate(returnTo, { state: { selectedClient: { id: data.id, name: data.name } } });
    } else {
      navigate(`/clientes/${data.id}`, { replace: true });
    }
  };

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">
      <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
        <button onClick={() => navigate(-1)} className="p-2 rounded-full hover:bg-surface-container-high">
          <span className="material-symbols-outlined text-on-surface">arrow_back</span>
        </button>
        <h1 className="font-bold text-lg text-on-surface flex-1">Nuevo cliente <span className="text-[9px] font-mono text-on-surface-variant/30 font-normal">MobCliNew</span></h1>
        <button onClick={save} disabled={saving}
          className="flex items-center gap-1 bg-primary text-on-primary px-3 py-1.5 rounded-full text-sm font-semibold active:scale-95 transition-transform disabled:opacity-60">
          <span className="material-symbols-outlined text-base">save</span>
          {saving ? 'Guardando…' : 'Guardar'}
        </button>
      </header>

      <div className="flex flex-col gap-4 px-4 pt-4">
        <div className="grid grid-cols-2 gap-3">
          <Field label="Nombre(s) *">
            <TextInput value={form.first_name} onChange={set('first_name')} placeholder="Nombre" />
            {errors.first_name && <p className="text-xs text-error mt-1">{errors.first_name}</p>}
          </Field>
          <Field label="Apellido(s)">
            <TextInput value={form.last_name} onChange={set('last_name')} placeholder="Apellido" />
          </Field>
          <div className="col-span-2">
            <Field label="Teléfono celular *">
              <TextInput value={form.phone} onChange={set('phone')} type="tel" placeholder="10 dígitos" />
              {errors.phone && <p className="text-xs text-error mt-1">{errors.phone}</p>}
            </Field>
          </div>
          <div className="col-span-2">
            <Field label="Correo electrónico">
              <TextInput value={form.email} onChange={set('email')} type="email" placeholder="correo@ejemplo.com" />
            </Field>
          </div>
          <div className="col-span-2">
            <Field label="Dirección">
              <TextInput value={form.address} onChange={set('address')} placeholder="Calle y número" />
            </Field>
          </div>
          <Field label="Ciudad">
            <TextInput value={form.city} onChange={set('city')} />
          </Field>
          <Field label="Estado">
            <TextInput value={form.state} onChange={set('state')} />
          </Field>
          <Field label="Código postal">
            <TextInput value={form.zip_code} onChange={set('zip_code')} />
          </Field>
        </div>

        <Field label="Notas">
          <textarea value={form.notes} onChange={e => set('notes')(e.target.value)} rows={3}
            placeholder="Observaciones…"
            className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary resize-none" />
        </Field>

        {errors.general && <p className="text-sm text-error text-center">{errors.general}</p>}
      </div>
    </div>
  );
}

export function ClientDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const isNew = !id || id === 'nuevo';
  const returnTo: string | undefined = (location.state as any)?.returnTo;
  const _stateCrumbs: import('../navState').NavCrumb[] | undefined = (location.state as any)?._crumbs;

  const [client, setClient] = useState<Client | null>(null);
  const [loading, setLoading] = useState(!isNew);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [edits, setEdits] = useState<EditState | null>(null);

  const set = (key: keyof EditState) => (val: string) =>
    setEdits(d => d ? { ...d, [key]: val } : d);

  useEffect(() => {
    if (isNew) return;
    fetch(`/api/clients/${id}`).then(r => r.json()).then((data: Client) => {
      setClient(data);
      setEdits(clientToEdits(data));
      setLoading(false);
    });
  }, [id, isNew]);

  const cancelEdit = () => { setEditing(false); if (client) setEdits(clientToEdits(client)); };

  const save = async () => {
    if (!client || !edits) return;
    setSaving(true);
    await fetch(`/api/clients/${client.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        first_name: edits.first_name, last_name: edits.last_name || null,
        email: edits.email || null, address: edits.address || null,
        city: edits.city || null, state: edits.state || null,
        zip_code: edits.zip_code || null, notes: edits.notes || null,
        phones: edits.phones.filter(p => p.number.trim()),
      }),
    });
    const updated: Client = await fetch(`/api/clients/${client.id}`).then(r => r.json());
    setClient(updated);
    setEdits(clientToEdits(updated));
    setEditing(false);
    setSaving(false);
  };

  /* ── Estados de carga / nuevo ─────────────────────────── */
  if (loading) return (
    <div className="min-h-screen bg-background flex items-center justify-center">
      <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
    </div>
  );

  if (isNew) return <NewClientForm navigate={navigate} returnTo={returnTo} />;

  if (!client || !edits) return null;

  /* NUNCA usar módulo como fallback — si no hay state, sin crumbs (evita crumbs rancios) */
  const crumbs = Array.isArray(_stateCrumbs) ? _stateCrumbs : [];
  const { showBreadcrumbs } = getUserPrefs();

  /* ── Render principal ─────────────────────────────────── */
  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">

      <ScreenHeader
        title={editing ? `${edits.first_name} ${edits.last_name}`.trim() || client.full_name : client.full_name}
        screenTag="MobCliDet"
        onBack={() => navigate(-1)}
        crumbs={crumbs}
        showBreadcrumbs={showBreadcrumbs}
        noCrumbs={editing}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); }}
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
          <button onClick={() => setEditing(true)} className="p-2 rounded-full hover:bg-surface-container-high transition-colors">
            <span className="material-symbols-outlined text-on-surface">edit</span>
          </button>
        )}
      />

      <div className="flex flex-col gap-4 px-4 pt-5 pb-4">

        {/* ── VISTA ─────────────────────────────────────────── */}
        {!editing && (
          <>
            {/* Avatar + nombre */}
            <div className="flex items-center gap-4">
              <div className="w-20 h-20 rounded-2xl bg-secondary/10 flex items-center justify-center shrink-0">
                <span className="material-symbols-outlined text-5xl text-secondary" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
              </div>
              <div className="flex-1 min-w-0">
                <h2 className="text-2xl font-bold text-on-surface">{client.full_name}</h2>
                {client.email && <p className="text-sm text-on-surface-variant">{client.email}</p>}
              </div>
            </div>

            {/* Teléfonos */}
            {client.phones.length > 0 && (
              <div className="flex flex-col gap-2">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Teléfonos</p>
                {client.phones.map(p => (
                  <div key={p.id} className="bg-surface border border-outline-variant rounded-2xl px-4 py-3 flex items-center gap-3">
                    <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>
                      {p.type === 'mobile' ? 'smartphone' : 'call'}
                    </span>
                    <div className="flex-1">
                      <p className="text-sm font-semibold text-on-surface">{p.number}</p>
                      <p className="text-xs text-on-surface-variant">{PHONE_TYPE[p.type] ?? p.type}</p>
                    </div>
                    <a href={`tel:${p.number}`} className="p-2 rounded-full bg-primary/10 text-primary active:scale-95 transition-transform">
                      <span className="material-symbols-outlined text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>call</span>
                    </a>
                    <a href={`https://wa.me/${p.number.replace(/\D/g, '')}`} target="_blank" rel="noreferrer"
                      className="p-2 rounded-full bg-green-100 text-green-700 active:scale-95 transition-transform">
                      <span className="material-symbols-outlined text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>chat</span>
                    </a>
                  </div>
                ))}
              </div>
            )}

            {/* Dirección */}
            {(client.address || client.city) && (
              <div className="bg-surface border border-outline-variant rounded-2xl px-4 py-3 flex items-start gap-3">
                <span className="material-symbols-outlined text-on-surface-variant mt-0.5" style={{ fontVariationSettings: "'FILL' 1" }}>location_on</span>
                <div>
                  {client.address && <p className="text-sm text-on-surface">{client.address}</p>}
                  <p className="text-xs text-on-surface-variant">
                    {[client.city, client.state, client.zip_code].filter(Boolean).join(', ')}
                  </p>
                </div>
              </div>
            )}

            {/* Notas */}
            {client.notes && (
              <div className="bg-surface-container-low border border-outline-variant rounded-2xl px-4 py-3">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1">Notas</p>
                <p className="text-sm text-on-surface leading-relaxed">{client.notes}</p>
              </div>
            )}

            {/* Mascotas */}
            {client.pets.length > 0 && (
              <div className="flex flex-col gap-2">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">
                  Mascotas ({client.pets.length})
                </p>
                {client.pets.map(pet => (
                  <button key={pet.id} onClick={() => { const nc = [...crumbs, { label: client.full_name, to: `/clientes/${client.id}` }]; setNavCrumbs(nc); navigate(`/mascotas/${pet.id}`, { state: { _crumbs: nc } }); }}
                    className="bg-surface border border-outline-variant rounded-2xl px-4 py-3 flex items-center gap-3 w-full text-left active:bg-surface-container transition-colors">
                    <div className="w-10 h-10 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                      {pet.photo
                        ? <img src={pet.photo} alt={pet.name} className="w-full h-full object-cover" />
                        : <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>
                      }
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-semibold text-on-surface">{pet.name}</p>
                      {pet.breed && <p className="text-xs text-on-surface-variant">{pet.breed}</p>}
                    </div>
                    <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
                  </button>
                ))}
              </div>
            )}
          </>
        )}

        {/* ── EDICIÓN ───────────────────────────────────────── */}
        {editing && edits && (
          <div className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-3">
              <Field label="Nombre(s)">
                <TextInput value={edits.first_name} onChange={set('first_name')} />
              </Field>
              <Field label="Apellido(s)">
                <TextInput value={edits.last_name} onChange={set('last_name')} />
              </Field>
              <div className="col-span-2">
                <Field label="Correo electrónico">
                  <TextInput value={edits.email} onChange={set('email')} type="email" placeholder="correo@ejemplo.com" />
                </Field>
              </div>
              <div className="col-span-2">
                <Field label="Dirección">
                  <TextInput value={edits.address} onChange={set('address')} placeholder="Calle y número" />
                </Field>
              </div>
              <Field label="Ciudad">
                <TextInput value={edits.city} onChange={set('city')} />
              </Field>
              <Field label="Estado">
                <TextInput value={edits.state} onChange={set('state')} />
              </Field>
              <Field label="Código postal">
                <TextInput value={edits.zip_code} onChange={set('zip_code')} />
              </Field>
            </div>

            <Field label="Notas">
              <textarea value={edits.notes} onChange={e => set('notes')(e.target.value)} rows={3}
                placeholder="Agregar notas…"
                className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary resize-none" />
            </Field>

            {/* Teléfonos */}
            <div className="flex flex-col gap-2">
              <div className="flex items-center justify-between">
                <label className="text-xs text-on-surface-variant">Teléfonos</label>
                <button
                  type="button"
                  onClick={() => setEdits(d => d ? { ...d, phones: [...d.phones, { number: '', type: 'mobile' }] } : d)}
                  className="flex items-center gap-1 text-xs text-primary font-medium"
                >
                  <span className="material-symbols-outlined text-base">add</span>
                  Agregar
                </button>
              </div>
              {edits.phones.map((p, i) => (
                <div key={i} className="flex items-center gap-2">
                  <select
                    value={p.type}
                    onChange={e => setEdits(d => {
                      if (!d) return d;
                      const phones = [...d.phones];
                      phones[i] = { ...phones[i], type: e.target.value };
                      return { ...d, phones };
                    })}
                    className="bg-surface-container border border-outline-variant rounded-xl px-2 py-2.5 text-sm text-on-surface outline-none focus:border-primary shrink-0"
                  >
                    <option value="mobile">Celular</option>
                    <option value="home">Casa</option>
                    <option value="work">Trabajo</option>
                  </select>
                  <input
                    type="tel"
                    value={p.number}
                    onChange={e => setEdits(d => {
                      if (!d) return d;
                      const phones = [...d.phones];
                      phones[i] = { ...phones[i], number: e.target.value };
                      return { ...d, phones };
                    })}
                    placeholder="Número"
                    className="flex-1 bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary"
                  />
                  <button
                    type="button"
                    onClick={() => setEdits(d => d ? { ...d, phones: d.phones.filter((_, j) => j !== i) } : d)}
                    className="p-2 rounded-full text-error active:bg-error/10 transition-colors shrink-0"
                  >
                    <span className="material-symbols-outlined text-xl">delete</span>
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}

      </div>
    </div>
  );
}
