import React from 'react';
import { BrowserRouter as Router, Routes, Route, Link } from 'react-router-dom';
import clsx from 'clsx';
import { ClientDashboard } from './client/Dashboard';
import { ClientBooking } from './client/Booking';
import { GroomerDashboard } from './admin/GroomerDashboard';
import { ActiveService } from './admin/ActiveService';
import { Directory } from './admin/Directory';
import { AssignService } from './admin/AssignService';
import { TeamPanel } from './admin/TeamPanel';
import { GlobalAgenda } from './admin/GlobalAgenda';

function RoleSelection() {
  return (
    <div className="min-h-screen bg-[#f7f9fb] flex flex-col items-center justify-center p-6">
      <h1 className="text-3xl font-bold mb-8 text-[#005da7]">Portal de Prototipos</h1>
      <p className="text-gray-500 mb-8 max-w-xl text-center">Para mantener las experiencias separadas, por favor selecciona qué aplicación deseas probar. Cada una tiene su propio diseño y flujo.</p>
      
      <div className="grid md:grid-cols-2 gap-8 w-full max-w-4xl">
        
        {/* Client App Card */}
        <div className="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 flex flex-col items-center text-center">
          <div className="w-20 h-20 bg-[#d4e3ff] rounded-full flex items-center justify-center mb-6">
            <span className="material-symbols-outlined text-4xl text-[#005da7]">pets</span>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">App Cliente (EstetiCAN)</h2>
          <p className="text-gray-500 mb-8">Interfaz para dueños de mascotas. Permite ver el estado del servicio y agendar citas.</p>
          <div className="flex flex-col gap-3 w-full mt-auto">
            <Link to="/client/dashboard" className="w-full bg-[#005da7] hover:bg-[#004883] text-white py-3 rounded-xl font-semibold transition-colors">
              Ver Tablero (Inicio)
            </Link>
            <Link to="/client/booking" className="w-full border-2 border-[#005da7] text-[#005da7] hover:bg-[#f2f4f6] py-3 rounded-xl font-semibold transition-colors">
              Ver Agendamiento
            </Link>
          </div>
        </div>

        {/* Admin App Card */}
        <div className="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 flex flex-col items-center text-center">
          <div className="w-20 h-20 bg-[#dae2fd] rounded-full flex items-center justify-center mb-6">
            <span className="material-symbols-outlined text-4xl text-[#131b2e]">storefront</span>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">App Administrador</h2>
          <p className="text-gray-500 mb-8">Interfaz para el personal (BarkStyle Pro / Admin). Permite gestionar la agenda y operaciones.</p>
          <div className="grid grid-cols-2 gap-3 w-full mt-auto">
            <Link to="/agenda-global" className="w-full bg-[#131b2e] hover:bg-black text-white py-3 rounded-xl font-semibold transition-colors col-span-2">
              Agenda Global
            </Link>
            <Link to="/admin/team" className="w-full border-2 border-[#131b2e] text-[#131b2e] hover:bg-gray-50 py-3 rounded-xl font-semibold transition-colors col-span-2">
              Panel de Equipo
            </Link>
            <Link to="/groomer" className="w-full border-2 border-[#131b2e] text-[#131b2e] hover:bg-gray-50 py-3 rounded-xl font-semibold transition-colors">
              Groomer Dashboard
            </Link>
            <Link to="/admin/directory" className="w-full border-2 border-[#131b2e] text-[#131b2e] hover:bg-gray-50 py-3 rounded-xl font-semibold transition-colors">
              Directorio / Clientes
            </Link>
            <Link to="/groomer/active" className="w-full border-2 border-[#131b2e] text-[#131b2e] hover:bg-gray-50 py-3 rounded-xl font-semibold transition-colors">
              Servicio Activo
            </Link>
            <Link to="/admin/assign" className="w-full border-2 border-[#131b2e] text-[#131b2e] hover:bg-gray-50 py-3 rounded-xl font-semibold transition-colors">
              Asignar Cita
            </Link>
          </div>
        </div>

      </div>
    </div>
  );
}

function ThemeWrapper({ children, theme }: { children: React.ReactNode, theme: 'theme-client' | 'theme-admin' }) {
  return (
    <div className={clsx("min-h-screen", theme)}>
      {children}
    </div>
  );
}

export default function App() {
  return (
    <Router>
      <Routes>
        <Route path="/" element={<RoleSelection />} />
        
        <Route path="/client/dashboard" element={<ThemeWrapper theme="theme-client"><ClientDashboard /></ThemeWrapper>} />
        <Route path="/client/booking" element={<ThemeWrapper theme="theme-client"><ClientBooking /></ThemeWrapper>} />
        
        <Route path="/groomer" element={<ThemeWrapper theme="theme-admin"><GroomerDashboard /></ThemeWrapper>} />
        <Route path="/groomer/active" element={<ThemeWrapper theme="theme-admin"><ActiveService /></ThemeWrapper>} />
        <Route path="/admin/directory" element={<ThemeWrapper theme="theme-admin"><Directory /></ThemeWrapper>} />
        <Route path="/admin/assign" element={<ThemeWrapper theme="theme-admin"><AssignService /></ThemeWrapper>} />
        <Route path="/admin/team" element={<ThemeWrapper theme="theme-admin"><TeamPanel /></ThemeWrapper>} />
        <Route path="/agenda-global" element={<ThemeWrapper theme="theme-admin"><GlobalAgenda /></ThemeWrapper>} />
      </Routes>
    </Router>
  );
}
