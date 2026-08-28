import React, { useState } from 'react';
import { BrowserRouter as Router, Routes, Route, NavLink, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { AuthProvider, useAuth, type AuthUser } from './AuthContext';
import { AppLockProvider, useAppLock } from './AppLockContext';
import { LockScreen } from './LockScreen';
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
import { MobUnavailability } from './admin/MobUnavailability';
import { MobFinanzas }         from './admin/MobFinanzas';
import { MobCaja }             from './admin/MobCaja';
import { MobCajaMovimientos } from './admin/MobCajaMovimientos';
import { MobCajaReportes } from './admin/MobCajaReportes';
import { MobCajaResumen } from './admin/MobCajaResumen';
import { MobCajaMetodosPago } from './admin/MobCajaMetodosPago';
import { MobCajaPorOperador } from './admin/MobCajaPorOperador';
import { MobCajaPendientes } from './admin/MobCajaPendientes';
import { MobCajaCierres } from './admin/MobCajaCierres';
import { ItemSearch } from './admin/ItemSearch';
import { clearNavCrumbs } from './navState';
import { CheckinWidget } from './CheckinWidget';

/* ═══════════════════════════════════════════════════════════
   FUENTE ÚNICA DEL MENÚ — agregar secciones aquí
   ═══════════════════════════════════════════════════════════ */
/** Claves booleanas de `AuthUser` que un ítem de menú puede exigir para mostrarse. */
type MenuVisibilityFlag = 'can_view_caja' | 'can_view_all_agenda' | 'can_view_clients' | 'can_view_pets' | 'can_view_operators';

interface MenuItem {
  to: string;
  icon: string;
  label: string;
  /** Oculta el ítem si `AuthUser[requiresFlag]` es falso — generaliza el `caja.ver` original. */
  requiresFlag?: MenuVisibilityFlag;
}
interface MenuSection {
  title: string;
  items: MenuItem[];
}
export const MENU_SECTIONS: MenuSection[] = [
  {
    title: 'Principal',
    items: [
      { to: '/agenda',               icon: 'calendar_month', label: 'Agenda' },
      { to: '/mascotas',             icon: 'pets',           label: 'Mascotas', requiresFlag: 'can_view_pets' },
      { to: '/clientes/seleccionar', icon: 'person_search',  label: 'Clientes', requiresFlag: 'can_view_clients' },
      { to: '/groomer',              icon: 'content_cut',    label: 'Operador', requiresFlag: 'can_view_operators' },
    ],
  },
  {
    title: 'Finanzas',
    items: [
      { to: '/finanzas', icon: 'account_balance_wallet', label: 'Finanzas', requiresFlag: 'can_view_caja' },
    ],
  },
  {
    title: 'Catálogo',
    items: [
      { to: '/articulos', icon: 'inventory_2', label: 'Artículos' },
    ],
  },
];

/** Un usuario sin sesión (`user` null) ve todo — el `AuthGuard` ya bloqueó antes de llegar acá. */
function visibleMenuItems(items: MenuItem[], user: AuthUser | null): MenuItem[] {
  return items.filter(item => !item.requiresFlag || !user || user[item.requiresFlag]);
}

const NAV_TABS = MENU_SECTIONS[0].items;

/* ═══════════════════════════════════════════════════════════
   Drawer de menú completo
   ═══════════════════════════════════════════════════════════ */
function MenuDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
  const navigate  = useNavigate();
  const { pathname } = useLocation();
  const { user, logout } = useAuth();
  const { lock } = useAppLock();

  const go = (to: string) => { clearNavCrumbs(); onClose(); navigate(to); };

  const handleLogout = async () => {
    onClose();
    await logout();
  };

  const handleLock = () => {
    onClose();
    lock();
  };

  return (
    <>
      {open && (
        <div className="fixed inset-0 z-40 bg-black/40" onClick={onClose} />
      )}

      <div
        className={`fixed left-0 right-0 bottom-0 z-50 bg-surface rounded-t-3xl shadow-2xl transition-transform duration-300 ease-out ${
          open ? '[transform:translateY(0)]' : '[transform:translateY(100%)]'
        }`}
        style={{ maxHeight: '85vh', overflowY: 'auto' }}
      >
        {/* Asa + cerrar */}
        <div className="relative flex justify-center pt-3 pb-1">
          <div className="w-10 h-1 rounded-full bg-outline-variant" />
          <button
            onClick={onClose}
            aria-label="Cerrar menú"
            className="absolute right-1 top-0 min-w-11 min-h-11 flex items-center justify-center rounded-full active:bg-surface-container-high transition-colors"
          >
            <span className="material-symbols-outlined text-xl text-on-surface-variant">close</span>
          </button>
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
          {MENU_SECTIONS.map(section => {
            // Sin este filtro, el menú mostraba cada ítem a cualquier usuario logueado sin
            // importar sus permisos granulares, y quien no tuviera el permiso correspondiente
            // entraba a una pantalla rota (o, peor, veía datos que no le tocan).
            const items = visibleMenuItems(section.items, user);
            if (items.length === 0) return null;
            return (
            <div key={section.title}>
              <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-widest mb-2 px-1">
                {section.title}
              </p>
              <div className="bg-surface-container rounded-2xl overflow-hidden">
                {items.map((item, i) => {
                  const isActive = pathname.startsWith(item.to);
                  return (
                    <button
                      key={item.to}
                      onClick={() => go(item.to)}
                      className={`w-full flex items-center gap-4 px-4 py-3.5 text-left active:bg-surface-container-high transition-colors ${
                        i < items.length - 1 ? 'border-b border-outline-variant' : ''
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
            );
          })}

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

          {/* Bloquear */}
          <div className="bg-surface-container rounded-2xl overflow-hidden">
            <button
              onClick={handleLock}
              className="w-full flex items-center gap-4 px-4 py-3.5 text-left active:bg-surface-container-high transition-colors"
            >
              <span className="material-symbols-outlined text-2xl text-on-surface-variant">lock</span>
              <span className="text-sm font-medium text-on-surface">Bloquear ahora</span>
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
  const { user } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const active = (path: string) => pathname.startsWith(path);
  // NAV_TABS es estático (MENU_SECTIONS[0].items) — a diferencia del drawer, esta barra nunca
  // filtraba por permiso, así que un operador restringido veía "Mascotas"/"Clientes"/"Operador"
  // acá aunque el drawer ya los escondiera.
  const visibleTabs = visibleMenuItems(NAV_TABS, user);

  return (
    <>
      <nav className="fixed bottom-0 left-0 right-0 z-40 bg-surface border-t border-outline-variant flex justify-around items-center h-16 px-2">
        {visibleTabs.map(({ to, icon, label }) => (
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
  const { user, loading, sessionCheckFailed, retrySessionCheck } = useAuth();
  const { locked, unlock } = useAppLock();

  if (loading) {
    return (
      <div className="theme-admin min-h-screen bg-background flex items-center justify-center">
        <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">
          progress_activity
        </span>
      </div>
    );
  }

  // No es un 401 (esos ya limpiaron el token y caen a LoginScreen abajo) — es que no se pudo
  // confirmar la sesión guardada (timeout, sin conexión). Reintentar en vez de forzar login.
  if (!user && sessionCheckFailed) {
    return (
      <div className="theme-admin min-h-screen bg-background flex flex-col items-center justify-center px-6 gap-4 text-center">
        <span className="material-symbols-outlined text-4xl text-on-surface-variant">wifi_off</span>
        <p className="text-sm text-on-surface-variant max-w-xs">
          No se pudo confirmar tu sesión. Revisa tu conexión e intenta de nuevo.
        </p>
        <button
          onClick={retrySessionCheck}
          className="bg-primary text-on-primary px-6 py-3 rounded-2xl font-semibold"
        >
          Reintentar
        </button>
      </div>
    );
  }

  if (!user) return <LoginScreen />;

  return (
    <>
      {children}
      {locked && <LockScreen onUnlock={unlock} />}
    </>
  );
}

function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="theme-admin min-h-screen bg-background">
      <div style={{ paddingBottom: 'calc(4rem + env(safe-area-inset-bottom, 0px))' }}>
        {children}
      </div>
      <BottomNav />
    </div>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <AppLockProvider>
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
            <Route path="/finanzas"             element={<AdminLayout><MobFinanzas /></AdminLayout>} />
            <Route path="/caja"                 element={<AdminLayout><MobCaja /></AdminLayout>} />
            <Route path="/caja/movimientos"    element={<AdminLayout><MobCajaMovimientos /></AdminLayout>} />
            <Route path="/caja/reportes"        element={<AdminLayout><MobCajaReportes /></AdminLayout>} />
            <Route path="/caja/reportes/resumen" element={<AdminLayout><MobCajaResumen /></AdminLayout>} />
            <Route path="/caja/reportes/metodos-pago" element={<AdminLayout><MobCajaMetodosPago /></AdminLayout>} />
            <Route path="/caja/reportes/por-operador" element={<AdminLayout><MobCajaPorOperador /></AdminLayout>} />
            <Route path="/caja/reportes/pendientes" element={<AdminLayout><MobCajaPendientes /></AdminLayout>} />
            <Route path="/caja/reportes/cierres" element={<AdminLayout><MobCajaCierres /></AdminLayout>} />
            <Route path="/configuracion"        element={<AdminLayout><MobUserConfig /></AdminLayout>} />
            <Route path="/configuracion/disponibilidad" element={<AdminLayout><MobUnavailability /></AdminLayout>} />
            <Route path="/articulos"            element={<AdminLayout><ItemSearch /></AdminLayout>} />
            {/* Cualquier ruta que no matchee (typo real de un usuario o URL inventada) —
                sin esto, React Router no renderiza nada dentro de <Routes> y queda
                pantalla en blanco sin ningún mensaje. */}
            <Route path="*" element={<Navigate to="/agenda" replace />} />
          </Routes>
        </AuthGuard>
      </Router>
      </AppLockProvider>
    </AuthProvider>
  );
}
