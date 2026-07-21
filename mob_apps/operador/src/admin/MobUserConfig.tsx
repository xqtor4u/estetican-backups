import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../AuthContext';
import { useUserPrefs, ThemeMode } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';
import { PhotoEditorModal } from '../PhotoEditorModal';
import { forgetBiometric, hasRegisteredCredential, isWebAuthnAvailable, registerBiometric } from '../lib/webauthnLock';

function Toggle({ value, onChange, label, description }: {
  value: boolean;
  onChange: (v: boolean) => void;
  label: string;
  description?: string;
}) {
  return (
    <button
      onClick={() => onChange(!value)}
      className="flex items-center gap-4 w-full px-4 py-3.5 text-left active:bg-surface-container-high transition-colors"
    >
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-on-surface">{label}</p>
        {description && <p className="text-xs text-on-surface-variant mt-0.5">{description}</p>}
      </div>
      <div className={`relative w-12 h-6 rounded-full transition-colors shrink-0 ${value ? 'bg-primary' : 'bg-outline'}`}>
        <span className={`absolute top-0.5 w-5 h-5 rounded-full bg-surface shadow transition-transform ${value ? 'translate-x-6' : 'translate-x-0.5'}`} />
      </div>
    </button>
  );
}

function Field({ label, ...props }: { label: string } & React.InputHTMLAttributes<HTMLInputElement>) {
  return (
    <label className="block px-4 py-2.5">
      <span className="text-xs font-medium text-on-surface-variant">{label}</span>
      <input
        {...props}
        className="mt-1 w-full bg-transparent text-sm text-on-surface border-b border-outline-variant focus:border-primary outline-none py-1"
      />
    </label>
  );
}

const THEME_OPTIONS: { value: ThemeMode; label: string; icon: string }[] = [
  { value: 'light',  label: 'Claro',   icon: 'light_mode' },
  { value: 'dark',   label: 'Oscuro',  icon: 'dark_mode' },
  { value: 'system', label: 'Sistema', icon: 'brightness_auto' },
];

export function MobUserConfig() {
  const navigate = useNavigate();
  const { user, setUser } = useAuth();
  const { prefs, update } = useUserPrefs();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [profileForm, setProfileForm] = useState({
    first_name: user?.first_name ?? '',
    apellido_paterno: user?.apellido_paterno ?? '',
    apellido_materno: user?.apellido_materno ?? '',
    email: user?.email ?? '',
  });
  const [profileSaving, setProfileSaving] = useState(false);
  const [profileMsg, setProfileMsg] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);

  const [passwordForm, setPasswordForm] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [passwordSaving, setPasswordSaving] = useState(false);
  const [passwordMsg, setPasswordMsg] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);

  const [photoUploading, setPhotoUploading] = useState(false);
  const [editingFile, setEditingFile] = useState<File | null>(null);
  const [watermarkEnabled, setWatermarkEnabled] = useState(false);

  const [biometricEnabled, setBiometricEnabled] = useState(() => !!user && hasRegisteredCredential(user.id));
  const [biometricBusy, setBiometricBusy] = useState(false);
  const [biometricError, setBiometricError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/settings/photos')
      .then(r => r.ok ? r.json() : null)
      .then(data => { if (data) setWatermarkEnabled(!!data.watermark_enabled); })
      .catch(() => {});
  }, []);

  const toggleBiometric = async (value: boolean) => {
    if (!user) return;
    setBiometricError(null);
    if (!value) {
      forgetBiometric(user.id);
      setBiometricEnabled(false);
      return;
    }
    setBiometricBusy(true);
    const ok = await registerBiometric(user.id, user.name);
    setBiometricBusy(false);
    if (ok) setBiometricEnabled(true);
    else setBiometricError('No se pudo registrar. Verifica que tu teléfono tenga Face ID, huella o PIN configurado.');
  };

  const saveProfile = async () => {
    setProfileSaving(true);
    setProfileMsg(null);
    try {
      const res = await fetch('/api/me', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(profileForm),
      });
      const data = await res.json();
      if (!res.ok) {
        setProfileMsg({ type: 'error', text: data.errors ? Object.values<string[]>(data.errors).flat().join(' ') : (data.message ?? 'No se pudo guardar') });
        return;
      }
      setUser(data);
      setProfileMsg({ type: 'ok', text: 'Datos actualizados.' });
    } finally {
      setProfileSaving(false);
    }
  };

  const changePassword = async () => {
    setPasswordSaving(true);
    setPasswordMsg(null);
    try {
      const res = await fetch('/api/me/password', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(passwordForm),
      });
      const data = await res.json();
      if (!res.ok) {
        setPasswordMsg({ type: 'error', text: data.errors ? Object.values<string[]>(data.errors).flat().join(' ') : (data.message ?? 'No se pudo cambiar la contraseña') });
        return;
      }
      setPasswordForm({ current_password: '', password: '', password_confirmation: '' });
      setPasswordMsg({ type: 'ok', text: 'Contraseña actualizada.' });
    } finally {
      setPasswordSaving(false);
    }
  };

  const uploadPhoto = async (blob: Blob) => {
    setPhotoUploading(true);
    try {
      const body = new FormData();
      body.append('photo', blob, 'photo.jpg');
      const res = await fetch('/api/me/photo', { method: 'POST', body, headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (res.ok) setUser(data);
    } finally {
      setPhotoUploading(false);
    }
  };

  const removePhoto = async () => {
    setPhotoUploading(true);
    try {
      const res = await fetch('/api/me/photo', { method: 'DELETE', headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (res.ok) setUser(data);
    } finally {
      setPhotoUploading(false);
    }
  };

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">

      <ScreenHeader
        title="Configuración personal"
        screenTag="MobUserConf"
        onBack={() => navigate(-1)}
        noCrumbs
      />

      {/* Perfil + foto */}
      {user && (
        <div className="mx-4 mt-5 bg-surface-container border border-outline-variant rounded-2xl px-4 py-4 flex items-center gap-4">
          <div className="relative shrink-0">
            <button
              onClick={() => fileInputRef.current?.click()}
              className="w-16 h-16 rounded-full bg-primary/20 overflow-hidden flex items-center justify-center"
            >
              {user.photo_url
                ? <img src={user.photo_url} alt={user.name} className="w-full h-full object-cover" />
                : <span className="material-symbols-outlined text-primary text-3xl" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
              }
            </button>
            <span className="absolute bottom-0 right-0 w-6 h-6 rounded-full bg-primary text-on-primary flex items-center justify-center border-2 border-surface-container">
              <span className="material-symbols-outlined text-[14px]">photo_camera</span>
            </span>
            {photoUploading && (
              <div className="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center">
                <span className="material-symbols-outlined text-white text-xl animate-spin">progress_activity</span>
              </div>
            )}
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={e => { const f = e.target.files?.[0]; if (f) setEditingFile(f); e.target.value = ''; }}
            />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-on-surface truncate">{user.name}</p>
            <p className="text-xs text-on-surface-variant truncate">{user.email}</p>
            {user.operator_role && (
              <p className="text-xs text-primary mt-0.5">{user.operator_role}</p>
            )}
            {user.photo_url && (
              <button onClick={removePhoto} className="text-xs text-error mt-1">Quitar foto</button>
            )}
          </div>
        </div>
      )}

      {/* Sección: Datos personales */}
      <div className="mx-4 mt-5">
        <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1 px-1">Datos personales</p>
        <div className="bg-surface-container border border-outline-variant rounded-2xl overflow-hidden divide-y divide-outline-variant/50">
          <Field label="Nombre" value={profileForm.first_name} onChange={e => setProfileForm(f => ({ ...f, first_name: e.target.value }))} />
          <Field label="Apellido paterno" value={profileForm.apellido_paterno} onChange={e => setProfileForm(f => ({ ...f, apellido_paterno: e.target.value }))} />
          <Field label="Apellido materno" value={profileForm.apellido_materno} onChange={e => setProfileForm(f => ({ ...f, apellido_materno: e.target.value }))} />
          <Field label="Correo" type="email" value={profileForm.email} onChange={e => setProfileForm(f => ({ ...f, email: e.target.value }))} />
        </div>
        {profileMsg && (
          <p className={`text-xs mt-2 px-1 ${profileMsg.type === 'ok' ? 'text-secondary' : 'text-error'}`}>{profileMsg.text}</p>
        )}
        <button
          onClick={saveProfile}
          disabled={profileSaving}
          className="mt-3 w-full bg-primary text-on-primary font-label-md text-label-md px-4 py-2.5 rounded-xl disabled:opacity-50"
        >
          {profileSaving ? 'Guardando…' : 'Guardar cambios'}
        </button>
      </div>

      {/* Sección: Contraseña */}
      <div className="mx-4 mt-6">
        <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1 px-1">Cambiar contraseña</p>
        <div className="bg-surface-container border border-outline-variant rounded-2xl overflow-hidden divide-y divide-outline-variant/50">
          <Field label="Contraseña actual" type="password" value={passwordForm.current_password} onChange={e => setPasswordForm(f => ({ ...f, current_password: e.target.value }))} />
          <Field label="Contraseña nueva" type="password" value={passwordForm.password} onChange={e => setPasswordForm(f => ({ ...f, password: e.target.value }))} />
          <Field label="Confirmar contraseña nueva" type="password" value={passwordForm.password_confirmation} onChange={e => setPasswordForm(f => ({ ...f, password_confirmation: e.target.value }))} />
        </div>
        {passwordMsg && (
          <p className={`text-xs mt-2 px-1 ${passwordMsg.type === 'ok' ? 'text-secondary' : 'text-error'}`}>{passwordMsg.text}</p>
        )}
        <button
          onClick={changePassword}
          disabled={passwordSaving || !passwordForm.current_password || !passwordForm.password}
          className="mt-3 w-full bg-primary text-on-primary font-label-md text-label-md px-4 py-2.5 rounded-xl disabled:opacity-50"
        >
          {passwordSaving ? 'Cambiando…' : 'Cambiar contraseña'}
        </button>
      </div>

      {/* Sección: Seguridad */}
      <div className="mx-4 mt-6">
        <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1 px-1">Seguridad</p>
        <div className="bg-surface-container border border-outline-variant rounded-2xl overflow-hidden divide-y divide-outline-variant/50">
          {isWebAuthnAvailable() ? (
            <Toggle
              value={biometricEnabled}
              onChange={toggleBiometric}
              label="Desbloqueo con Face ID / huella"
              description={biometricBusy ? 'Sigue las instrucciones de tu teléfono…' : 'Úsalo en vez de la contraseña cuando la app se bloquee'}
            />
          ) : (
            <div className="px-4 py-3.5">
              <p className="text-sm font-medium text-on-surface">Desbloqueo con Face ID / huella</p>
              <p className="text-xs text-on-surface-variant mt-0.5">Tu navegador no lo soporta — se usará la contraseña</p>
            </div>
          )}
          <div className="px-4 py-3.5">
            <p className="text-sm font-medium text-on-surface">Bloqueo automático</p>
            <p className="text-xs text-on-surface-variant mt-0.5">La app se bloquea sola tras 5 minutos sin uso, o al cambiar de aplicación</p>
          </div>
        </div>
        {biometricError && <p className="text-xs text-error mt-2 px-1">{biometricError}</p>}
      </div>

      {/* Sección: Disponibilidad (solo operadores) */}
      {user?.operator_role && (
        <div className="mx-4 mt-6">
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1 px-1">Disponibilidad</p>
          <button
            onClick={() => navigate('/configuracion/disponibilidad')}
            className="w-full flex items-center gap-3 px-4 py-3.5 bg-surface-container border border-outline-variant rounded-2xl text-left active:bg-surface-container-high transition-colors"
          >
            <span className="material-symbols-outlined text-xl text-primary">event_busy</span>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-on-surface">Bloquear horas de vacaciones/permiso</p>
              <p className="text-xs text-on-surface-variant mt-0.5">Marca periodos en los que no se te debe agendar</p>
            </div>
            <span className="material-symbols-outlined text-base text-on-surface-variant">chevron_right</span>
          </button>
        </div>
      )}

      {/* Sección: Tema */}
      <div className="mx-4 mt-6">
        <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1 px-1">Tema</p>
        <div className="bg-surface-container border border-outline-variant rounded-2xl p-1.5 flex gap-1">
          {THEME_OPTIONS.map(opt => (
            <button
              key={opt.value}
              onClick={() => update({ theme: opt.value })}
              className={`flex-1 flex flex-col items-center gap-1 py-2.5 rounded-xl transition-colors ${
                prefs.theme === opt.value ? 'bg-primary text-on-primary' : 'text-on-surface-variant active:bg-surface-container-high'
              }`}
            >
              <span className="material-symbols-outlined text-xl">{opt.icon}</span>
              <span className="text-xs font-medium">{opt.label}</span>
            </button>
          ))}
        </div>
      </div>

      {/* Sección: Navegación */}
      <div className="mx-4 mt-6">
        <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1 px-1">Navegación</p>
        <div className="bg-surface-container border border-outline-variant rounded-2xl overflow-hidden divide-y divide-outline-variant/50">
          <Toggle
            value={prefs.showBreadcrumbs}
            onChange={v => update({ showBreadcrumbs: v })}
            label="Mostrar ruta de navegación"
            description="Muestra desde dónde llegaste en el encabezado de cada pantalla"
          />
        </div>
      </div>

      {/* Versión */}
      <p className="text-center text-xs text-on-surface-variant/40 mt-auto pt-8">EstetiCAN · App Operador</p>

      {editingFile && (
        <PhotoEditorModal
          file={editingFile}
          label={user?.name ?? ''}
          watermarkEnabled={watermarkEnabled}
          onCancel={() => setEditingFile(null)}
          onConfirm={blob => { setEditingFile(null); uploadPhoto(blob); }}
        />
      )}

    </div>
  );
}
