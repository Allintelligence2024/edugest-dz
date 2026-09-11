import { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import LanguageThemeSelector from '@components/LanguageThemeSelector';
import { useTheme } from '@context/ThemeContext';
import SearchModal from '@components/SearchModal';
import { useI18n } from '@context/I18nContext';
import { getAccessToken } from '../../api/tokenStore';

// Titres et fils d'Ariane par route — valeurs = clés i18n (src/lang/*.json),
// traduites au rendu. t() renvoie la clé si elle manque (repli visible).
const PAGE_META = {
  '/':                 { title: 'dashboard_title',    crumb: [] },
  '/eleves':           { title: 'students_title',     crumb: ['nav_students'] },
  '/planning':         { title: 'planning_title',     crumb: ['nav_planning'] },
  '/presences':        { title: 'presences_title',    crumb: ['nav_attendance'] },
  '/absences':         { title: 'absences_title',     crumb: ['nav_absences'] },
  '/billets':          { title: 'billets_title',      crumb: ['nav_tickets'] },
  '/notes':            { title: 'notes_title',        crumb: ['section_pedagogy', 'nav_notes'] },
  '/bulletins':        { title: 'bulletins_title',    crumb: ['section_pedagogy', 'nav_bulletins'] },
  '/diagnostic':       { title: 'diagnostic_title',   crumb: ['section_pedagogy', 'crumb_diagnostic'] },
  '/factures':         { title: 'finance_title',      crumb: ['section_finance', 'crumb_factures'] },
  '/budget':           { title: 'budget_title',       crumb: ['section_finance', 'nav_budget'] },
  '/transport':        { title: 'transport_title',    crumb: ['crumb_gestion', 'nav_transport'] },
  '/cantine':          { title: 'canteen_title',      crumb: ['crumb_gestion', 'nav_canteen'] },
  '/stock':            { title: 'stock_title',        crumb: ['crumb_gestion', 'nav_stock'] },
  '/personnel-admin':  { title: 'personnel_title',    crumb: ['crumb_gestion', 'nav_staff'] },
  '/entretien':        { title: 'entretien_title',    crumb: ['crumb_gestion', 'nav_maintenance'] },
  '/surveillance':     { title: 'surveillance_title', crumb: ['nav_surveillance'] },
  '/pointage':         { title: 'pointage_title',     crumb: ['nav_pointage'] },
  '/messages':         { title: 'messages_title',     crumb: ['section_communication', 'nav_messages'] },
  '/campagnes':        { title: 'campagnes_title',    crumb: ['section_communication', 'nav_campaigns'] },
  '/centres':          { title: 'marketplace_title',  crumb: ['nav_marketplace'] },
  '/mes-reservations': { title: 'mes_reservations_title', crumb: ['nav_marketplace', 'crumb_reservations'] },
  '/profil':           { title: 'profile_title',      crumb: ['section_settings', 'crumb_profil'] },
  '/audit-logs':       { title: 'audit_title',        crumb: ['section_settings', 'crumb_audit'] },
  '/super-admin':      { title: 'nav_superadmin',     crumb: ['nav_superadmin'] },
  '/modules':          { title: 'modules_title',      crumb: ['section_settings', 'crumb_modules'] },
};

export default function Topbar({ user }) {
  const location = useLocation();
  const { isDark, toggleTheme } = useTheme();
  const { lang, t, formatDate } = useI18n();
  const [notifCount, setNotifCount] = useState(0);
  const [today, setToday] = useState('');
  const [searchOpen, setSearchOpen] = useState(false);

  const meta = PAGE_META[location.pathname] || { title: 'app_name', crumb: [] };

  useEffect(() => {
    setToday(formatDate(new Date(), lang, {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    }));
  }, [lang, formatDate]);

  useEffect(() => {
    const token = getAccessToken();
    if (!token) return;
    const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/api\/v1\/?$/, '');
    fetch(`${BASE}/api/v1/notifications/parent?lu=false&per_page=1`, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then(r => r.json())
      .then(d => setNotifCount(d?.non_lues || 0))
      .catch(() => {});
  }, [location.pathname]);

  return (
    <header className="bg-surface border-b border-border h-16 flex items-center px-6 gap-4 sticky top-0 z-30 shrink-0">
      <div className="flex-1 min-w-0">
        <h1 className="text-base font-extrabold text-text truncate">
          {t(meta.title)}
        </h1>
        {meta.crumb.length > 0 && (
          <div className="flex items-center gap-1 text-[10px] text-muted mt-0.5">
            <Link to="/" className="text-muted hover:text-accent transition-colors no-underline">EduGest</Link>
            {meta.crumb.map((c, i) => (
              <span key={i} className="flex items-center gap-1">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                  <path d="M3 2L6 5L3 8" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
                <span className={i === meta.crumb.length - 1 ? 'text-accent' : 'text-muted'}>{t(c)}</span>
              </span>
            ))}
          </div>
        )}
      </div>

      <span className="text-[11px] text-muted2 hidden md:block">{today}</span>

      <button
        onClick={() => setSearchOpen(true)}
        className="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-border bg-surface2 text-muted hover:text-text hover:border-accent transition-colors text-xs"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
        </svg>
        {t('topbar_search')}
        <kbd className="ml-2 text-[9px] px-1.5 py-0.5 rounded bg-surface border border-border font-mono">⌘K</kbd>
      </button>

      <SearchModal isOpen={searchOpen} onClose={() => setSearchOpen(false)} />

      <label className="relative inline-flex items-center cursor-pointer">
        <input type="checkbox" checked={!isDark} onChange={toggleTheme} className="sr-only peer" />
        <div className="w-[44px] h-[24px] rounded-full transition-colors duration-200 peer-checked:bg-[#E2E8F0] bg-[#0D1117] border border-border"></div>
        <div className="absolute left-[2px] top-[2px] w-5 h-5 rounded-full transition-all duration-200 peer-checked:translate-x-5 peer-checked:bg-white bg-accent flex items-center justify-center shadow-sm">
          {isDark ? (
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
          ) : (
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#2563EB" strokeWidth="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          )}
        </div>
      </label>

      <Link
        to="/profil"
        className="w-9 h-9 rounded-lg bg-surface2 border border-border flex items-center justify-center text-muted hover:text-text hover:bg-surface3 transition-colors no-underline"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
      </Link>
    </header>
  );
}
