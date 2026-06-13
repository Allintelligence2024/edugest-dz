import React from 'react';
import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { AuthProvider, useAuth } from '@context/AuthContext';
import { I18nProvider } from '@context/I18nContext';
import Sidebar from '@components/Sidebar';
import Header from '@components/Header';
import LoginPage from '@pages/LoginPage';
import DashboardPage from '@pages/DashboardPage';
import PlanningPage from '@pages/PlanningPage';
import PresencesPage from '@pages/PresencesPage';
import FacturesPage from '@pages/FacturesPage';

function ProtectedLayout() {
  const { isAuthenticated, isLoading, user } = useAuth();

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return (
    <div className="flex min-h-screen">
      <Sidebar />
      <div className="flex-1 flex flex-col">
        <Header user={user} />
        <main className="flex-1 p-6 overflow-y-auto bg-neutral-50">
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
      <I18nProvider>
        <AuthProvider>
          <Toaster position="top-right" toastOptions={{
            duration: 3500,
            style: {
              borderRadius: '12px',
              background: '#fff',
              color: '#212529',
              fontSize: '14px',
              boxShadow: '0 8px 30px rgba(0,0,0,0.12)',
            },
          }} />
          <Routes>
            <Route path="/login" element={<PublicRoute><LoginPage /></PublicRoute>} />
            <Route element={<ProtectedLayout />}>
              <Route index element={<DashboardPage />} />
              <Route path="planning" element={<PlanningPage />} />
              <Route path="presences" element={<PresencesPage />} />
              <Route path="factures" element={<FacturesPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </AuthProvider>
      </I18nProvider>
    </BrowserRouter>
  );
}
