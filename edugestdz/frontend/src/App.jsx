import React, { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate, Outlet, useLocation } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { AuthProvider, useAuth } from '@context/AuthContext';
import { setSessionExpiredHandler } from '@api/client';
import { I18nProvider } from '@context/I18nContext';
import { ThemeProvider } from '@context/ThemeContext';
import { ModulesProvider } from '@context/ModulesContext';
import ProtectedRoute from '@components/ProtectedRoute';
import Sidebar from '@components/Sidebar';
import Topbar from '@components/layout/Topbar';
import LoginPage from '@pages/LoginPage';
import MotDePasseOubliePage from '@pages/MotDePasseOubliePage';
import AccesRefusePage from '@pages/AccesRefusePage';
import OnboardingPage from '@pages/OnboardingPage';
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
import LmsPage from '@pages/LmsPage';
import MarketplacePageCentres from '@pages/MarketplacePageCentres';
import PointagePage from '@pages/PointagePage';
import ProfilePage from '@pages/ProfilePage';
import SurveillancePage from '@pages/SurveillancePage';
import DiagnosticPage from '@pages/DiagnosticPage';
import ExamensPage from '@pages/ExamensPage';
import ModulesPage from '@pages/ModulesPage';
import NotificationsPage from '@pages/NotificationsPage';
import DevoirsPage from '@pages/DevoirsPage';
import FeedbackEnseignantPage from '@pages/FeedbackEnseignantPage';
import PredictionIAPage from '@pages/PredictionIAPage';
import RgpdPage from '@pages/RgpdPage';
import OfflineBanner from '@components/ui/OfflineBanner';

function ProtectedLayout() {
  const { isAuthenticated, isLoading, user, role, onboardingComplete } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-bg">
        <div className="text-center">
          <div className="text-[40px] mb-4">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#2563EB" strokeWidth="1.5"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 1.657 2.686 3 6 3s6-1.343 6-3v-5"/></svg>
          </div>
          <div className="w-10 h-10 mx-auto border-3 border-border rounded-full animate-spin"
            style={{ borderTopColor: '#2563EB' }} />
          <div className="mt-4 text-xs text-muted">Chargement EduGest DZ...</div>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (role === 'admin' && !onboardingComplete && location.pathname !== '/onboarding') {
    return <Navigate to="/onboarding" replace />;
  }

  return (
    <div className="flex min-h-screen bg-bg">
      <Sidebar />
      <div className="flex-1 flex flex-col min-w-0">
        <Topbar user={user} />
        <main className="flex-1 overflow-y-auto p-6">
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

function AppInner() {
  const { onSessionExpired } = useAuth();
  useEffect(() => {
    setSessionExpiredHandler(onSessionExpired);
  }, [onSessionExpired]);

  return (
    <ThemeProvider>
      <OfflineBanner />
      <I18nProvider>
        <ModulesProvider>
          <Toaster position="top-right" toastOptions={{
            duration: 3500,
            style: {
              borderRadius: '10px',
              background: 'var(--surface)',
              color: 'var(--text)',
              fontSize: '13px',
              border: '1px solid var(--border)',
              boxShadow: 'var(--shadow)',
            },
          }} />
          <Routes>
            <Route path="/login" element={<PublicRoute><LoginPage /></PublicRoute>} />
            <Route path="/mot-de-passe-oublie" element={<MotDePasseOubliePage />} />
            <Route path="/acces-refuse" element={<AccesRefusePage />} />
            <Route path="/onboarding" element={<ProtectedRoute><OnboardingPage /></ProtectedRoute>} />
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
            <Route path="examens" element={<ExamensPage />} />
            <Route path="lms" element={<LmsPage />} />
            <Route path="modules" element={<ModulesPage />} />
            <Route path="notifications" element={<NotificationsPage />} />
            <Route path="devoirs" element={<DevoirsPage />} />
            <Route path="feedback-enseignant" element={<FeedbackEnseignantPage />} />
            <Route path="prediction-ia" element={<PredictionIAPage />} />
            <Route path="rgpd" element={<RgpdPage />} />
          </Route>
            <Route path="marketplace" element={<MarketplaceSearchPage />} />
            <Route path="marketplace/offres/:id" element={<MarketplaceOffreDetailPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </ModulesProvider>
      </I18nProvider>
    </ThemeProvider>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <AppInner />
      </BrowserRouter>
    </AuthProvider>
  );
}
