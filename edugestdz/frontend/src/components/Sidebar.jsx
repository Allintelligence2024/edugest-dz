import { useState, useEffect } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '@context/AuthContext';
import { useI18n } from '@context/I18nContext';
import { useModules } from '@context/ModulesContext';

const MODULE_ICONS = {
  '/':              { icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', color: '#2563EB' },
  '/eleves':        { icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', color: '#10B981' },
  '/planning':      { icon: 'M8 2V6M16 2V6M3 10H21M5 4H19C20.1046 4 21 4.89543 21 6V20C21 21.1046 20.1046 22 19 22H5C3.89543 22 3 21.1046 3 20V6C3 4.89543 3.89543 4 5 4Z', color: '#06B6D4' },
  '/presences':     { icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', color: '#10B981' },
  '/absences':      { icon: 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: '#F59E0B' },
  '/billets':       { icon: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z', color: '#F59E0B' },
  '/notes':         { icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', color: '#7C3AED' },
  '/bulletins':     { icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', color: '#7C3AED' },
  '/diagnostic':    { icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', color: '#7C3AED' },
  '/factures':      { icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: '#10B981' },
  '/budget':        { icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', color: '#10B981' },
  '/transport':     { icon: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z', color: '#2563EB' },
  '/cantine':       { icon: 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4', color: '#F59E0B' },
  '/stock':         { icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', color: '#06B6D4' },
  '/personnel-admin':{ icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z', color: '#64748B' },
  '/entretien':     { icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', color: '#F59E0B' },
  '/surveillance':  { icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', color: '#EF4444' },
  '/pointage':      { icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', color: '#06B6D4' },
  '/messages':      { icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', color: '#2563EB' },
  '/campagnes':     { icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z', color: '#2563EB' },
  '/centres':       { icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', color: '#7C3AED' },
  '/mes-reservations':{ icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', color: '#7C3AED' },
  '/profil':        { icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', color: '#64748B' },
  '/audit-logs':    { icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', color: '#64748B' },
  '/modules':       { icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z', color: '#7C3AED' },
  '/super-admin':   { icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', color: '#EF4444' },
  '/lms':           { icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', color: '#7C3AED' },
  '/examens':       { icon: 'M12 14l9-5-9-5-9 5 9 5z', color: '#7C3AED' },
  '/marketplace':   { icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', color: '#7C3AED' },
  '/notifications': { icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', color: '#F59E0B' },
  '/devoirs':       { icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', color: '#06B6D4' },
  '/feedback-enseignant': { icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', color: '#EC4899' },
  '/prediction-ia': { icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', color: '#7C3AED' },
  '/rgpd':         { icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', color: '#10B981' },
};

const SECTIONS = [
  { label: 'section_main', items: ['/','/eleves','/planning','/presences','/absences','/billets','/notifications'] },
  { label: 'section_pedagogy', items: ['/notes','/bulletins','/diagnostic','/examens','/lms','/devoirs','/feedback-enseignant','/prediction-ia'] },
  { label: 'section_finance', items: ['/factures','/budget','/pointage'] },
  { label: 'section_management', items: ['/transport','/cantine','/stock','/personnel-admin','/entretien','/surveillance'] },
  { label: 'section_communication', items: ['/messages','/campagnes'] },
  { label: 'section_main', items: ['/centres','/mes-reservations','/marketplace'] },
  { label: 'section_settings', items: ['/profil','/audit-logs','/modules','/rgpd','/super-admin'] },
];

const MODULE_MAP = {
  '/billets': 'billets', '/diagnostic': 'diagnostic', '/examens': 'examens',
  '/lms': 'lms', '/budget': 'budget', '/pointage': 'pointage',
  '/transport': 'transport', '/cantine': 'cantine', '/stock': 'stock',
  '/personnel-admin': 'personnel', '/entretien': 'entretien',
  '/surveillance': 'surveillance', '/modules': 'modules',
};

const ROLE_MAP = { '/super-admin': 'super_admin', '/rgpd': 'admin' };

export default function Sidebar() {
  const { user, logout } = useAuth();
  const { t } = useI18n();
  const { isActive } = useModules();
  const location = useLocation();
  const [collapsed, setCollapsed] = useState(false);
  const [badges, setBadges] = useState({});
  const [sectionsOpen, setSectionsOpen] = useState({});

  const activeColor = MODULE_ICONS[location.pathname]?.color || '#2563EB';

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    if (!token) return;
    const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/api\/v1\/?$/, '');
    Promise.allSettled([
      fetch(`${BASE}/api/v1/absences?per_page=1&statut=non_justifiée`, { headers: { Authorization: `Bearer ${token}` } }).then(r => r.json()),
      fetch(`${BASE}/api/v1/diagnostic/dashboard`, { headers: { Authorization: `Bearer ${token}` } }).then(r => r.json()),
      fetch(`${BASE}/api/v1/surveillance/alertes?traite=false&per_page=1`, { headers: { Authorization: `Bearer ${token}` } }).then(r => r.json()),
      fetch(`${BASE}/api/v1/factures?statut=en_retard&per_page=1`, { headers: { Authorization: `Bearer ${token}` } }).then(r => r.json()),
    ]).then(([a, d, s, f]) => {
      setBadges({
        absences: a.status === 'fulfilled' ? (a.value?.meta?.total || 0) : 0,
        critiques: d.status === 'fulfilled' ? (d.value?.data?.par_niveau?.critique || 0) : 0,
        alertes:   s.status === 'fulfilled' ? (s.value?.data?.stats?.non_traitees || 0) : 0,
        impayes:   f.status === 'fulfilled' ? (f.value?.meta?.total || 0) : 0,
      });
    });
  }, []);

  const tenantName = localStorage.getItem('tenantName') || t('app_name');
  const tenantWilaya = localStorage.getItem('tenantWilaya') || 'Algérie';

  return (
    <aside
      className={`h-screen sticky top-0 flex flex-col shrink-0 overflow-hidden z-40 bg-surface border-r border-border transition-all duration-200 ${collapsed ? 'w-[64px]' : 'w-[240px]'}`}
    >
      {/* Logo / Header */}
      <div className={`flex items-center gap-2.5 border-b border-border min-h-[64px] ${collapsed ? 'px-3.5 py-[18px] justify-center' : 'px-4 py-[18px]'}`}>
        <button
          onClick={() => setCollapsed(!collapsed)}
          className="w-[34px] h-[34px] rounded-xl shrink-0 flex items-center justify-center text-lg cursor-pointer"
          style={{ background: 'linear-gradient(135deg, #2563EB, #7C3AED)' }}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 1.657 2.686 3 6 3s6-1.343 6-3v-5"/>
          </svg>
        </button>
        {!collapsed && (
          <div className="flex items-center gap-1 min-w-0 flex-1">
            <div className="min-w-0 flex-1">
              <div className="text-sm font-extrabold text-text truncate">{t('app_name')}</div>
              <div className="text-[9px] text-muted truncate">{t('app_subtitle')}</div>
            </div>
            <button
              onClick={() => setCollapsed(true)}
              className="text-muted2 hover:text-text transition-colors bg-none border-none cursor-pointer text-sm"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
          </div>
        )}
      </div>

      {/* Tenant badge */}
      {!collapsed && (
        <div className="mx-3 my-2.5 rounded-xl px-3 py-2"
          style={{ background: 'linear-gradient(135deg, #1e3a5f22, #7c3aed22)', border: '1px solid #2563eb33' }}>
          <div className="text-[11px] font-bold text-text truncate flex items-center gap-1.5">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#2563EB" strokeWidth="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
            {tenantName}
          </div>
          <div className="text-[9px] text-muted mt-0.5 flex items-center gap-1">
            <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            {tenantWilaya}
          </div>
        </div>
      )}

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto py-1.5 px-0">
        {SECTIONS.map((sec) => {
          const items = sec.items
            .map(p => ({ path: p, meta: MODULE_ICONS[p] }))
            .filter(item => {
              const role = ROLE_MAP[item.path];
              const mod  = MODULE_MAP[item.path];
              if (role && user?.role !== role) return false;
              if (mod && !isActive(mod)) return false;
              return true;
            });
          if (items.length === 0) return null;

          return (
            <div key={sec.label} className="mb-0.5">
              {!collapsed && (
                <div className="px-4 pt-1.5 pb-0.5">
                  <span className="text-[9px] font-bold text-muted2 uppercase tracking-wider">
                    {t(sec.label)}
                  </span>
                </div>
              )}
              {items.map(({ path, meta }) => {
                const isActiveRoute = path === '/'
                  ? location.pathname === path
                  : location.pathname.startsWith(path);
                const badgeCount =
                  (path === '/absences' ? badges.absences : 0) ||
                  (path === '/diagnostic' ? badges.critiques : 0) ||
                  (path === '/surveillance' ? badges.alertes : 0) ||
                  (path === '/factures' ? badges.impayes : 0);
                const color = meta?.color || '#2563EB';

                return (
                  <NavLink
                    key={path}
                    to={path}
                    end={path === '/'}
                    className={({ isActive: navActive }) => {
                      const active = navActive || isActiveRoute;
                      return [
                        'flex items-center mx-2 my-[1px] rounded-lg text-xs font-medium no-underline transition-all duration-100 relative',
                        collapsed ? 'justify-center px-0 py-[9px]' : 'px-3.5 py-2 gap-[9px]',
                        active ? 'font-bold' : 'font-medium',
                      ].join(' ');
                    }}
                    style={({ isActive: navActive }) => {
                      const active = navActive || isActiveRoute;
                      return {
                        color: active ? color : 'var(--muted)',
                        background: active ? `${color}18` : 'transparent',
                        borderLeft: active ? `2px solid ${color}` : '2px solid transparent',
                      };
                    }}
                    title={collapsed ? t(`nav_${path.replace('/', '') || 'dashboard'}`) : undefined}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="shrink-0">
                      {meta?.icon ? <path d={meta.icon} /> : <circle cx="12" cy="12" r="3"/>}
                    </svg>
                    {!collapsed && (
                      <>
                        <span className="flex-1 truncate">{t(`nav_${path.replace('/', '') || 'dashboard'}`)}</span>
                        {badgeCount > 0 && (
                          <span className="bg-red text-white text-[9px] font-extrabold px-[5px] py-[1px] rounded-full text-center leading-none min-w-[18px]">
                            {badgeCount > 99 ? '99+' : badgeCount}
                          </span>
                        )}
                      </>
                    )}
                    {collapsed && badgeCount > 0 && (
                      <span className="absolute top-1 right-1 w-2 h-2 bg-red rounded-full"
                        style={{ border: '2px solid var(--surface)' }}
                      />
                    )}
                  </NavLink>
                );
              })}
            </div>
          );
        })}
      </nav>

      {/* User footer */}
      <div className="border-t border-border px-3 py-3">
        {!collapsed ? (
          <div className="flex items-center gap-2.5 px-2.5 py-[9px] rounded-xl bg-surface2">
            <div className="w-[30px] h-[30px] rounded-lg shrink-0 flex items-center justify-center text-[11px] font-extrabold text-white"
              style={{ background: 'linear-gradient(135deg, #2563EB, #7C3AED)' }}>
              {user ? `${(user.nom || '')[0]}${(user.prenom || '')[0]}`.toUpperCase() : 'U'}
            </div>
            <div className="flex-1 min-w-0">
              <div className="text-[11px] font-bold text-text truncate">{user?.nom} {user?.prenom}</div>
              <div className="text-[9px] text-muted capitalize truncate">{user?.role}</div>
            </div>
            <button
              onClick={logout}
              title={t('nav_logout')}
              className="bg-none border-none text-muted2 hover:text-text cursor-pointer transition-colors"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
              </svg>
            </button>
          </div>
        ) : (
          <div
            onClick={() => setCollapsed(false)}
            className="w-[38px] h-[38px] rounded-lg mx-auto flex items-center justify-center text-sm font-extrabold text-white cursor-pointer"
            style={{ background: 'linear-gradient(135deg, #2563EB, #7C3AED)' }}
          >
            {user ? `${(user.nom || '')[0]}${(user.prenom || '')[0]}`.toUpperCase() : 'U'}
          </div>
        )}
      </div>
    </aside>
  );
}
