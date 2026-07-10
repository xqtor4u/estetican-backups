import React, { useEffect, useState } from 'react';
import { BrowserRouter as Router, Routes, Route, NavLink, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './AuthContext';
import { LoginScreen } from './LoginScreen';
import { PetSearch } from './admin/PetSearch';
import { PetDetail } from './admin/PetDetail';
import { ClientDetail } from './admin/ClientDetail';
import { ClientSearch } from './admin/ClientSearch';
import { GroomerPicker } from './admin/GroomerPicker';
import { GroomerAgenda } from './admin/GroomerAgenda';
import { Directory } from './admin/Directory';
import { AssignService } from './admin/AssignService';
import { TeamPanel } from './admin/TeamPanel';
import { GlobalAgenda } from './admin/GlobalAgenda';
import { MobCitaNueva } from './admin/MobCitaNueva';
import { MobCitaDet } from './admin/MobCitaDet';
import { MobCobro }      from './admin/MobCobro';
import { MobPetJobs }    from './admin/MobPetJobs';
import { MobUserConfig } from './admin/MobUserConfig';
import { MobCaja }             from './admin/MobCaja';
import { MobCajaMovimientos } from './admin/MobCajaMovimientos';
import { clearNavCrumbs } from './navState';

/* ═══════════════════════════════════════════════════════════
   FUENTE ÚNICA DEL MENÚ — agregar secciones aquí
   ═══════════════════════════════════════════════════════════ */
export const MENU_SECTIONS = [
  {
    title: 'Principal',
    items: [
      { to: '/agenda',               icon: 'calendar_month', label: 'Agenda' },
      { to: '/mascotas',             icon: 'pets',           label: 'Mascotas' },
      { to: '/clientes/seleccionar', icon: 'person_search',  label: 'Clientes' },
      { to: '/groomer',              icon: 'content_cut',    label: 'Operador' },
    ],
  },
  {
    title: 'Finanzas',
    items: [
      { to: '/caja', icon: 'point_of_sale', label: 'Caja' },
    ],
  },
];

const NAV_TABS = MENU_SECTIONS[0].items;

/* ═══════════════════════════════════════════════════════════
   Widget de check-in en el drawer
   ═══════════════════════════════════════════════════════════ */
interface CheckinStatus {
  checked_in: boolean;
  checkin_id?: number;
  branch?: { id: number; name: string };
  checked_in_at?: string;
}

function CheckinWidget() {
  const [status,   setStatus]   = useState<CheckinStatus | null>(null);
  const [branches, setBranches] = useState<{ id: number; name: string }[]>([]);
  const [selBranch, setSelBranch] = useState<number | ''>('');
  const [expanded, setExpanded] = useState(false);
  const [busy,     setBusy]     = useState(false);

  const refresh = () => {
    fetch('/api/checkin/status').then(r => r.json()).then(setStatus).catch(() => {});
  };

  useEffect(() => {
    refresh();
    fetch('/api/branches').then(r => r.json()).then(setBranches).catch(() => {});
  }, []);

  const doCheckin = async () => {
    if (!selBranch) return;
    setBusy(true);
    await fetch('/api/checkin', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ branch_id: selBranch }),
    });
    setExpanded(false);
    setSelBranch('');
    await refresh();
    setBusy(false);
  };

  const doCheckout = async () => {
    setBusy(true);
    await fetch('/api/checkout', { method: 'POST' });
    await refresh();
    setBusy(false);
  };

  if (!status) return null;

  if (status.checked_in && status.branch) {
    const since = status.checked_in_at
      ? new Date(status.checked_in_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
      : '';
    return (
      <div className="mx-4 mb-3 bg-tertiary-container/30 border border-tertiary-fixed-dim rounded-2xl px-4 py-3">
        <div className="flex items-center gap-2">
          <span className="material-symbols-outlined text-tertiary text-lg" style={{ fontVariationSettings: "'FILL' 1" }}>location_on</span>
          <div className="flex-1 min-w-0">
            <p className="text-xs text-on-surface-variant">Check-in activo</p>
            <p className="text-sm font-semibold text-on-surface truncate">{status.branch.name}</p>
            {since && <p className="text-xs text-on-surface-variant">desde {since}</p>}
          </div>
          <button
            onClick={doCheckout}
            disabled={busy}
            className="flex items-center gap-1 px-3 py-1.5 rounded-full bg-error/10 text-error text-xs font-semibold border border-error/30 active:scale-95 transition-transform disabled:opacity-50"
          >
            <span className="material-symbols-outlined text-sm">logout</span>
            Salir
          </button>
        </div>
      </div>
    );
  }

  // No hay check-in activo
  return (
    <div className="mx-4 mb-3 bg-surface-container border border-outline-variant rounded-2xl px-4 py-3">
      {!expanded ? (
        <button
          onClick={() => setExpanded(true)}
          className="flex items-center gap-2 w-full text-left"
        >
          <span className="material-symbols-outlined text-on-surface-variant text-lg">add_location</span>
          <span className="text-sm text-on-surface-variant flex-1">Sin check-in activo</span>
          <span className="text-xs font-semibold text-primary">Registrar</span>
        </button>
      ) : (
        <div className="flex flex-col gap-2">
          <p className="text-xs font-semibold text-on-surface-variant">Selecciona sucursal</p>
          <select
            value={selBranch}
            onChange={e => setSelBranch(Number(e.target.value))}
            className="bg-background border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface outline-none focus:border-primary"
          >
            <option value="">— Sucursal —</option>
            {branches.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
          </select>
          <div className="flex gap-2">
            <button
              onClick={() => { setExpanded(false); setSelBranch(''); }}
              className="flex-1 py-2 rounded-xl text-sm border border-outline-variant text-on-surface-variant active:scale-95 transition-transform"
            >
              Cancelar
            </button>
            <button
              onClick={doCheckin}
              disabled={!selBranch || busy}
              className="flex-1 py-2 rounded-xl text-sm font-semibold bg-primary text-on-primary active:scale-95 transition-transform disabled:opacity-40"
            >
              {busy ? '…' : 'Check-in'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

/* ═══════════════════════════════════════════════════════════
   Drawer de menú completo
   ═══════════════════════════════════════════════════════════ */
function MenuDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
  const navigate  = useNavigate();
  const { pathname } = useLocation();
  const { user, logout } = useAuth();

  const go = (to: string) => { clearNavCrumbs(); onClose(); navigate(to); };

  const handleLogout = async () => {
    onClose();
    await logout();
  };

  return (
    <>
      {open && (
        <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      )}

      <div
        className={`fixed left-0 right-0 bottom-0 z-50 bg-surface rounded-t-3xl shadow-2xl transition-transform duration-300 ease-out ${
          open ? 'translate-y-0' : 'translate-y-full'
        }`}
        style={{ maxHeight: '85vh', overflowY: 'auto' }}
      >
        {/* Asa */}
        <div className="flex justify-center pt-3 pb-1">
          <div className="w-10 h-1 rounded-full bg-outline-variant" />
        </div>

        {/* Info de usuario */}
        {user && (
          <div className="mx-4 mt-2 mb-3 bg-surface-container rounded-2xl px-4 py-3 flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
              <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>
                account_circle
              </span>
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-semibold text-on-surface truncate">{user.name}</p>
              <p className="text-xs text-on-surface-variant truncate">
                {user.operator_role ?? (user.is_admin ? 'Administrador' : user.roles[0] ?? 'Usuario')}
              </p>
            </div>
          </div>
        )}

        {/* Widget de check-in */}
        <CheckinWidget />

        <div className="px-4 pb-4 flex flex-col gap-5">
          {MENU_SECTIONS.map(section => (
            <div key={section.title}>
              <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-widest mb-2 px-1">
                {section.title}
              </p>
              <div className="bg-surface-container rounded-2xl overflow-hidden">
                {section.items.map((item, i) => {
                  const isActive = pathname.startsWith(item.to);
                  return (
                    <button
                      key={item.to}
                      onClick={() => go(item.to)}
                      className={`w-full flex items-center gap-4 px-4 py-3.5 text-left active:bg-surface-container-high transition-colors ${
                        i < section.items.length - 1 ? 'border-b border-outline-variant' : ''
                      }`}
                    >
                      <span
                        className="material-symbols-outlined text-2xl"
                        style={{
                          fontVariationSettings: isActive ? "'FILL' 1" : "'FILL' 0",
                          color: isActive ? 'var(--color-primary)' : 'var(--color-on-surface-variant)',
                        }}
                      >
                        {item.icon}
                      </span>
                      <span className={`text-sm font-medium ${isActive ? 'text-primary' : 'text-on-surface'}`}>
                        {item.label}
                      </span>
                      {isActive && <span className="ml-auto w-2 h-2 rounded-full bg-primary" />}
                    </button>
                  );
                })}
              </div>
            </div>
          ))}

          {/* Configuración personal */}
          <div className="bg-surface-container rounded-2xl overflow-hidden">
            <button
              onClick={() => go('/configuracion')}
              className="w-full flex items-center gap-4 px-4 py-3.5 text-left active:bg-surface-container-high transition-colors"
            >
              <span className="material-symbols-outlined text-2xl text-on-surface-variant">manage_accounts</span>
              <span className="text-sm font-medium text-on-surface">Configuración personal</span>
              <span className="material-symbols-outlined text-base text-on-surface-variant ml-auto">chevron_right</span>
            </button>
          </div>

          {/* Cerrar sesión */}
          <div className="bg-surface-container rounded-2xl overflow-hidden">
            <button
              onClick={handleLogout}
              className="w-full flex items-center gap-4 px-4 py-3.5 text-left active:bg-error/10 transition-colors"
            >
              <span className="material-symbols-outlined text-2xl text-error">logout</span>
              <span className="text-sm font-medium text-error">Cerrar sesión</span>
            </button>
          </div>
        </div>
      </div>
    </>
  );
}

/* ═══════════════════════════════════════════════════════════
   Barra de navegación inferior
   ═══════════════════════════════════════════════════════════ */
function BottomNav() {
  const { pathname } = useLocation();
  const [menuOpen, setMenuOpen] = useState(false);
  const active = (path: string) => pathname.startsWith(path);

  return (
    <>
      <nav className="fixed bottom-0 left-0 right-0 z-40 bg-surface border-t border-outline-variant flex justify-around items-center h-16 px-2">
        {NAV_TABS.map(({ to, icon, label }) => (
          <NavLink
            key={to}
            to={to}
            onClick={clearNavCrumbs}
            className="flex flex-col items-center justify-center gap-0.5 flex-1 py-2"
          >
            <span
              className="material-symbols-outlined text-2xl transition-colors"
              style={{
                fontVariationSettings: active(to) ? "'FILL' 1" : "'FILL' 0",
                color: active(to) ? 'var(--color-primary)' : 'var(--color-on-surface-variant)',
              }}
            >
              {icon}
            </span>
            <span
              className="text-[11px] font-medium transition-colors"
              style={{ color: active(to) ? 'var(--color-primary)' : 'var(--color-on-surface-variant)' }}
            >
              {label}
            </span>
          </NavLink>
        ))}

        <button
          onClick={() => setMenuOpen(true)}
          className="flex flex-col items-center justify-center gap-0.5 flex-1 py-2"
        >
          <span className="material-symbols-outlined text-2xl" style={{ color: 'var(--color-on-surface-variant)' }}>
            menu
          </span>
          <span className="text-[11px] font-medium" style={{ color: 'var(--color-on-surface-variant)' }}>
            Menú
          </span>
        </button>
      </nav>

      <MenuDrawer open={menuOpen} onClose={() => setMenuOpen(false)} />
    </>
  );
}

/* ═══════════════════════════════════════════════════════════
   Guard de autenticación
   ═══════════════════════════════════════════════════════════ */
function AuthGuard({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="theme-admin min-h-screen bg-background flex items-center justify-center">
        <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">
          progress_activity
        </span>
      </div>
    );
  }

  if (!user) return <LoginScreen />;

  return <>{children}</>;
}

function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="theme-admin min-h-screen bg-background">
      {children}
      <BottomNav />
    </div>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <Router>
        <AuthGuard>
          <Routes>
            <Route path="/" element={<Navigate to="/agenda" replace />} />
            <Route path="/agenda"               element={<AdminLayout><GlobalAgenda /></AdminLayout>} />
            <Route path="/equipo"               element={<AdminLayout><TeamPanel /></AdminLayout>} />
            <Route path="/groomer"              element={<AdminLayout><GroomerPicker /></AdminLayout>} />
            <Route path="/groomer/:id"          element={<AdminLayout><GroomerAgenda /></AdminLayout>} />
            <Route path="/directorio"           element={<AdminLayout><Directory /></AdminLayout>} />
            <Route path="/directorio/asignar"   element={<AdminLayout><AssignService /></AdminLayout>} />
            <Route path="/mascotas"                    element={<AdminLayout><PetSearch /></AdminLayout>} />
            <Route path="/mascotas/nuevo"             element={<AdminLayout><PetDetail /></AdminLayout>} />
            <Route path="/mascotas/:id/trabajos"      element={<AdminLayout><MobPetJobs /></AdminLayout>} />
            <Route path="/mascotas/:id/cita/nueva"    element={<AdminLayout><MobCitaNueva /></AdminLayout>} />
            <Route path="/citas/:id"                  element={<AdminLayout><MobCitaDet /></AdminLayout>} />
            <Route path="/citas/:id/cobro"            element={<AdminLayout><MobCobro /></AdminLayout>} />
            <Route path="/mascotas/:id"               element={<AdminLayout><PetDetail /></AdminLayout>} />
            <Route path="/clientes/seleccionar" element={<AdminLayout><ClientSearch /></AdminLayout>} />
            <Route path="/clientes/nuevo"       element={<AdminLayout><ClientDetail /></AdminLayout>} />
            <Route path="/clientes/:id"         element={<AdminLayout><ClientDetail /></AdminLayout>} />
            <Route path="/caja"                 element={<AdminLayout><MobCaja /></AdminLayout>} />
            <Route path="/caja/movimientos"    element={<AdminLayout><MobCajaMovimientos /></AdminLayout>} />
            <Route path="/configuracion"        element={<AdminLayout><MobUserConfig /></AdminLayout>} />
          </Routes>
        </AuthGuard>
      </Router>
    </AuthProvider>
  );
}
