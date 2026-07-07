import React, { useState, useEffect } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '@context/AuthContext';
import { useI18n } from '@context/I18nContext';
import { useTheme } from '@context/ThemeContext';

const MODULE_COLORS = {
  '/':              '#2563EB',
  '/eleves':        '#10B981',
  '/planning':      '#06B6D4',
  '/presences':     '#10B981',
  '/absences':      '#F59E0B',
  '/billets':       '#F59E0B',
  '/notes':         '#7C3AED',
  '/bulletins':     '#7C3AED',
  '/diagnostic':    '#7C3AED',
  '/factures':      '#10B981',
  '/budget':        '#10B981',
  '/transport':     '#2563EB',
  '/cantine':       '#F59E0B',
  '/stock':         '#06B6D4',
  '/personnel':     '#64748B',
  '/personnel-admin':'#64748B',
  '/entretien':     '#F59E0B',
  '/surveillance':  '#EF4444',
  '/marketplace':   '#7C3AED',
  '/centres':       '#7C3AED',
  '/pointage':      '#06B6D4',
  '/messages':      '#2563EB',
  '/campagnes':     '#2563EB',
  '/super-admin':   '#EF4444',
};

function useNavSections(t) {
  return [
    {
      label: t('section_main'),
      items: [
        { label: t('nav_dashboard'), path: '/',        icon: '📊', end: true },
        { label: t('nav_students'),  path: '/eleves',  icon: '👦', badge: null },
        { label: t('nav_planning'),  path: '/planning', icon: '📅' },
        { label: t('nav_attendance'),path: '/presences',icon: '✅' },
        { label: t('nav_absences'),  path: '/absences', icon: '⚠️', badgeKey: 'absences' },
        { label: t('nav_tickets'),   path: '/billets',  icon: '🎫' },
      ],
    },
    {
      label: t('section_pedagogy'),
      items: [
        { label: t('nav_notes'),     path: '/notes',      icon: '📝' },
        { label: t('nav_bulletins'), path: '/bulletins',  icon: '📄' },
        { label: t('nav_diagnostic'),path: '/diagnostic', icon: '🔬', badgeKey: 'critiques' },
        { label: 'Examens Officiels', path: '/examens', icon: '🎓' },
      ],
    },
    {
      label: t('section_finance'),
      items: [
        { label: t('nav_finance'),   path: '/factures',  icon: '💰', badgeKey: 'impayes' },
        { label: t('nav_budget'),    path: '/budget',    icon: '📈' },
        { label: t('nav_pointage'),  path: '/pointage',  icon: '🏷️' },
      ],
    },
    {
      label: t('section_management'),
      items: [
        { label: t('nav_transport'),     path: '/transport',      icon: '🚌' },
        { label: t('nav_canteen'),       path: '/cantine',        icon: '🍽️' },
        { label: t('nav_stock'),         path: '/stock',          icon: '📦' },
        { label: t('nav_staff'),         path: '/personnel-admin',icon: '👷' },
        { label: t('nav_maintenance'),   path: '/entretien',      icon: '🔧' },
        { label: t('nav_surveillance'),  path: '/surveillance',   icon: '🔒', badgeKey: 'alertes' },
      ],
    },
    {
      label: t('section_communication'),
      items: [
        { label: t('nav_messages'),  path: '/messages',   icon: '💬', badgeKey: 'messages' },
        { label: t('nav_campaigns'), path: '/campagnes',  icon: '📢' },
      ],
    },
    {
      label: t('section_main'),
      items: [
        { label: t('nav_marketplace'), path: '/centres',         icon: '🛒' },
        { label: 'Mes réservations',  path: '/mes-reservations',icon: '📅' },
      ],
    },
    {
      label: t('section_settings'),
      items: [
        { label: t('nav_profile'),    path: '/profil',     icon: '⚙️' },
        { label: t('nav_audit'),      path: '/audit-logs', icon: '📋' },
        { label: t('nav_superadmin'), path: '/super-admin',icon: '🛡️', role: 'super_admin' },
      ],
    },
  ];
}

export default function Sidebar() {
  const { user, logout } = useAuth();
  const { t } = useI18n();
  const { isDark } = useTheme();
  const location = useLocation();
  const [collapsed, setCollapsed]   = useState(false);
  const [badges, setBadges]         = useState({});
  const [collapsedSections, setCollapsedSections] = useState({});

  const NAV_SECTIONS = useNavSections(t);

  const activeColor = MODULE_COLORS[location.pathname] || '#2563EB';

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) return;

    const headers = { Authorization: `Bearer ${token}` };
    const BASE = import.meta.env.VITE_API_BASE_URL?.replace('/api/v1', '') || '';

    Promise.allSettled([
      fetch(`${BASE}/api/v1/absences?per_page=1&statut=non_justifiée`, { headers }).then(r => r.json()),
      fetch(`${BASE}/api/v1/diagnostic/dashboard`, { headers }).then(r => r.json()),
      fetch(`${BASE}/api/v1/surveillance/alertes?traite=false&per_page=1`, { headers }).then(r => r.json()),
      fetch(`${BASE}/api/v1/finance/factures?statut=en_retard&per_page=1`, { headers }).then(r => r.json()),
    ]).then(([absRes, diagRes, survRes, facRes]) => {
      setBadges({
        absences: absRes.status === 'fulfilled' ? (absRes.value?.meta?.total || 0) : 0,
        critiques: diagRes.status === 'fulfilled' ? (diagRes.value?.data?.par_niveau?.critique || 0) : 0,
        alertes:   survRes.status === 'fulfilled' ? (survRes.value?.data?.stats?.non_traitees || 0) : 0,
        impayes:   facRes.status === 'fulfilled' ? (facRes.value?.meta?.total || 0) : 0,
        messages:  0,
      });
    });
  }, []);

  const toggleSection = (label) => {
    setCollapsedSections(prev => ({ ...prev, [label]: !prev[label] }));
  };

  const userInitials = user
    ? `${(user.nom || '')[0] || ''}${(user.prenom || '')[0] || ''}`.toUpperCase()
    : 'U';

  const tenantName   = localStorage.getItem('tenantName')  || t('app_name');
  const tenantWilaya = localStorage.getItem('tenantWilaya') || 'Algérie';

  return (
    <aside
      style={{
        width: collapsed ? '64px' : '240px',
        background: 'var(--eg-nav-bg)',
        borderRight: '1px solid var(--eg-border)',
        display: 'flex',
        flexDirection: 'column',
        height: '100vh',
        position: 'sticky',
        top: 0,
        transition: 'width 0.2s ease',
        flexShrink: 0,
        overflow: 'hidden',
        zIndex: 40,
      }}
    >
      <div style={{
        padding: collapsed ? '18px 14px' : '18px 16px',
        borderBottom: '1px solid var(--eg-border)',
        display: 'flex', alignItems: 'center', gap: '10px',
        minHeight: '64px',
      }}>
        <div style={{
          width: '34px', height: '34px', borderRadius: '10px', flexShrink: 0,
          background: `linear-gradient(135deg, #2563EB, #7C3AED)`,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          fontSize: '18px', cursor: 'pointer',
        }} onClick={() => setCollapsed(!collapsed)}>
          🎓
        </div>
        {!collapsed && (
          <div style={{ overflow: 'hidden' }}>
            <div style={{ fontSize: '14px', fontWeight: 800, color: '#fff', whiteSpace: 'nowrap' }}>
              {t('app_name')}
            </div>
            <div style={{ fontSize: '9px', color: 'var(--eg-muted)', whiteSpace: 'nowrap' }}>
              {t('app_subtitle')}
            </div>
          </div>
        )}
        {!collapsed && (
          <button
            onClick={() => setCollapsed(true)}
            style={{ marginLeft: 'auto', background: 'none', border: 'none', color: '#475569', cursor: 'pointer', fontSize: '14px' }}
          >
            ◀
          </button>
        )}
      </div>

      {!collapsed && (
        <div style={{
          margin: '10px 12px',
          background: 'linear-gradient(135deg, #1e3a5f22, #7c3aed22)',
          border: '1px solid #2563eb33',
          borderRadius: '10px', padding: '9px 12px',
        }}>
          <div style={{ fontSize: '11px', fontWeight: 700, color: '#fff', marginBottom: '2px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
            🏫 {tenantName}
          </div>
          <div style={{ fontSize: '9px', color: 'var(--eg-muted)' }}>
            📍 {tenantWilaya}
          </div>
        </div>
      )}

      <nav style={{ flex: 1, overflowY: 'auto', padding: '6px 0' }}>
        {NAV_SECTIONS.map(section => {
          const items = section.items.filter(item =>
            !item.role || user?.role === item.role
          );
          if (items.length === 0) return null;

          const sectionCollapsed = collapsedSections[section.label];

          return (
            <div key={section.label} style={{ marginBottom: '2px' }}>
              {!collapsed && (
                <button
                  onClick={() => toggleSection(section.label)}
                  style={{
                    width: '100%', display: 'flex', alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '6px 16px 3px',
                    background: 'none', border: 'none', cursor: 'pointer',
                  }}
                >
                  <span style={{ fontSize: '9px', fontWeight: 700, color: '#475569', textTransform: 'uppercase', letterSpacing: '1.2px' }}>
                    {section.label}
                  </span>
                  <span style={{ fontSize: '10px', color: '#475569' }}>
                    {sectionCollapsed ? '▶' : '▾'}
                  </span>
                </button>
              )}

              {!sectionCollapsed && items.map(item => {
                const isActive = item.end
                  ? location.pathname === item.path
                  : location.pathname.startsWith(item.path) && item.path !== '/';
                const badgeCount = item.badgeKey ? badges[item.badgeKey] : 0;
                const color = MODULE_COLORS[item.path] || '#2563EB';

                return (
                  <NavLink
                    key={item.path}
                    to={item.path}
                    end={item.end}
                    style={({ isActive: navActive }) => ({
                      display: 'flex',
                      alignItems: 'center',
                      gap: collapsed ? '0' : '9px',
                      padding: collapsed ? '9px' : '8px 14px',
                      justifyContent: collapsed ? 'center' : 'flex-start',
                      margin: '1px 8px',
                      borderRadius: '9px',
                      fontSize: '12px',
                      fontWeight: navActive || isActive ? 700 : 500,
                      color: navActive || isActive ? color : 'var(--eg-muted)',
                      background: navActive || isActive
                        ? `${color}18`
                        : 'transparent',
                      textDecoration: 'none',
                      transition: 'all 0.12s',
                      position: 'relative',
                      borderLeft: navActive || isActive
                        ? `2px solid ${color}`
                        : '2px solid transparent',
                    })}
                    title={collapsed ? item.label : undefined}
                  >
                    <span style={{ fontSize: '15px', flexShrink: 0 }}>{item.icon}</span>
                    {!collapsed && (
                      <>
                        <span style={{ flex: 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                          {item.label}
                        </span>
                        {badgeCount > 0 && (
                          <span style={{
                            background: badgeCount > 0 ? '#EF4444' : '#1e293b',
                            color: '#fff',
                            fontSize: '9px', fontWeight: 800,
                            padding: '1px 6px', borderRadius: '20px',
                            minWidth: '18px', textAlign: 'center',
                          }}>
                            {badgeCount > 99 ? '99+' : badgeCount}
                          </span>
                        )}
                      </>
                    )}
                    {collapsed && badgeCount > 0 && (
                      <span style={{
                        position: 'absolute', top: '4px', right: '4px',
                        width: '8px', height: '8px',
                        background: '#EF4444', borderRadius: '50%',
                        border: '2px solid var(--eg-nav-bg)',
                      }} />
                    )}
                  </NavLink>
                );
              })}
            </div>
          );
        })}
      </nav>

      <div style={{ borderTop: '1px solid var(--eg-border)', padding: '12px' }}>
        {!collapsed ? (
          <div style={{
            display: 'flex', alignItems: 'center', gap: '10px',
            padding: '9px 10px', borderRadius: '10px',
            background: 'var(--eg-surface2)', cursor: 'pointer',
          }}>
            <div style={{
              width: '30px', height: '30px', borderRadius: '8px', flexShrink: 0,
              background: 'linear-gradient(135deg, #2563EB, #7C3AED)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: '11px', fontWeight: 800, color: '#fff',
            }}>
              {userInitials}
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--eg-text)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                {user?.nom} {user?.prenom}
              </div>
              <div style={{ fontSize: '9px', color: 'var(--eg-muted)', textTransform: 'capitalize' }}>
                {user?.role}
              </div>
            </div>
            <button
              onClick={logout}
              title={t('nav_logout')}
              style={{ background: 'none', border: 'none', color: '#475569', cursor: 'pointer', fontSize: '14px' }}
            >
              ↩
            </button>
          </div>
        ) : (
          <div style={{
            width: '38px', height: '38px', borderRadius: '9px', margin: '0 auto',
            background: 'linear-gradient(135deg, #2563EB, #7C3AED)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: '13px', fontWeight: 800, color: '#fff', cursor: 'pointer',
          }} onClick={() => setCollapsed(false)}>
            {userInitials}
          </div>
        )}
      </div>
    </aside>
  );
}
