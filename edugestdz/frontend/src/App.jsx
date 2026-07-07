import React from 'react';
import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { AuthProvider, useAuth } from '@context/AuthContext';
import { I18nProvider } from '@context/I18nContext';
import { ThemeProvider } from '@context/ThemeContext';
import Sidebar from '@components/Sidebar';
import Header from '@components/Header';
import LoginPage from '@pages/LoginPage';
import DashboardPage from '@pages/DashboardPage';
import PlanningPage from '@pages/PlanningPage';
import PresencesPage from '@pages/PresencesPage';
import FacturesPage from '@pages/FacturesPage';
import ElevesListPage from '@pages/ElevesListPage';
import NotesPage from '@pages/NotesPage';
import GroupesPage from '@pages/GroupesPage';
import EnseignantsListPage from '@pages/EnseignantsListPage';
import SallesPage from '@pages/SallesPage';
import MatieresPage from '@pages/MatieresPage';
import PaiesPage from '@pages/PaiesPage';
import MessagesPage from '@pages/MessagesPage';
import BulletinsPage from '@pages/BulletinsPage';
import AuditLogPage from '@pages/AuditLogPage';
import CampagnesPage from '@pages/CampagnesPage';
import SuperAdminPage from '@pages/SuperAdminPage';
import TwoFactorSetupPage from '@pages/TwoFactorSetupPage';
import MarketplaceSearchPage from '@pages/MarketplaceSearchPage';
import MarketplaceOffreDetailPage from '@pages/MarketplaceOffreDetailPage';
import MarketplaceReservationPage from '@pages/MarketplaceReservationPage';
import MesReservationsPage from '@pages/MesReservationsPage';
import TransportPage from '@pages/TransportPage';
import CantinePage from '@pages/CantinePage';
import StockInventairePage from '@pages/StockInventairePage';
import PersonnelAdminPage from '@pages/PersonnelAdminPage';
import BudgetPage from '@pages/BudgetPage';
import EntretienPage from '@pages/EntretienPage';
import AbsencesPage from '@pages/AbsencesPage';
import BilletsPage from '@pages/BilletsPage';
import MarketplacePageCentres from '@pages/MarketplacePageCentres';
import PointagePage from '@pages/PointagePage';
import ProfilePage from '@pages/ProfilePage';
import SurveillancePage from '@pages/SurveillancePage';
import DiagnosticPage from '@pages/DiagnosticPage';

function ProtectedLayout() {
  const { isAuthenticated, isLoading, user } = useAuth();

  if (isLoading) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#070B14' }}>
        <div style={{ textAlign: 'center' }}>
          <div style={{ fontSize: '40px', marginBottom: '16px' }}>🎓</div>
          <div style={{ width: '40px', height: '40px', margin: '0 auto', border: '3px solid #1E2D40', borderTop: '3px solid #2563EB', borderRadius: '50%', animation: 'spin 0.8s linear infinite' }} />
          <div style={{ marginTop: '16px', fontSize: '13px', color: '#64748B' }}>Chargement EduGest DZ...</div>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return (
    <div style={{ display: 'flex', minHeight: '100vh', background: '#070B14' }}>
      <Sidebar />
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <Header user={user} />
        <main style={{
          flex: 1,
          padding: '24px',
          overflowY: 'auto',
          background: '#070B14',
          color: '#E2E8F0',
        }}>
          <Outlet />
        </main>
      </div>
    </div>
  );
}

function PublicRoute({ children }) {
  const { isAuthenticated, isLoading } = useAuth();
  if (isLoading) return null;
  if (isAuthenticated) return <Navigate to="/" replace />;
  return children;
}

export default function App() {
  return (
    <BrowserRouter>
      <ThemeProvider>
        <I18nProvider>
          <AuthProvider>
          <Toaster position="top-right" toastOptions={{
            duration: 3500,
            style: {
              borderRadius: '10px',
              background: '#0D1117',
              color: '#E2E8F0',
              fontSize: '13px',
              border: '1px solid #1E2D40',
              boxShadow: '0 8px 32px rgba(0,0,0,0.5)',
            },
          }} />
          <Routes>
            <Route path="/login" element={<PublicRoute><LoginPage /></PublicRoute>} />
            <Route element={<ProtectedLayout />}>
              <Route index element={<DashboardPage />} />
              <Route path="planning" element={<PlanningPage />} />
              <Route path="presences" element={<PresencesPage />} />
              <Route path="factures" element={<FacturesPage />} />
              <Route path="eleves" element={<ElevesListPage />} />
              <Route path="notes" element={<NotesPage />} />
              <Route path="groupes" element={<GroupesPage />} />
              <Route path="enseignants" element={<EnseignantsListPage />} />
              <Route path="salles" element={<SallesPage />} />
              <Route path="matieres" element={<MatieresPage />} />
              <Route path="paie" element={<PaiesPage />} />
              <Route path="messages" element={<MessagesPage />} />
              <Route path="bulletins" element={<BulletinsPage />} />
              <Route path="audit-logs" element={<AuditLogPage />} />
              <Route path="campagnes" element={<CampagnesPage />} />
              <Route path="super-admin" element={<SuperAdminPage />} />
              <Route path="parametres/securite" element={<TwoFactorSetupPage />} />
            <Route path="marketplace/reservation/:id" element={<MarketplaceReservationPage />} />
            <Route path="mes-reservations" element={<MesReservationsPage />} />
            <Route path="transport" element={<TransportPage />} />
            <Route path="cantine" element={<CantinePage />} />
            <Route path="stock" element={<StockInventairePage />} />
            <Route path="personnel-admin" element={<PersonnelAdminPage />} />
            <Route path="budget" element={<BudgetPage />} />
            <Route path="entretien" element={<EntretienPage />} />
            <Route path="absences" element={<AbsencesPage />} />
            <Route path="billets" element={<BilletsPage />} />
            <Route path="centres" element={<MarketplacePageCentres />} />
            <Route path="pointage" element={<PointagePage />} />
            <Route path="profil" element={<ProfilePage />} />
            <Route path="surveillance" element={<SurveillancePage />} />
            <Route path="diagnostic" element={<DiagnosticPage />} />
            </Route>
            <Route path="marketplace" element={<MarketplaceSearchPage />} />
            <Route path="marketplace/offres/:id" element={<MarketplaceOffreDetailPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
          </AuthProvider>
        </I18nProvider>
      </ThemeProvider>
    </BrowserRouter>
  );
}
