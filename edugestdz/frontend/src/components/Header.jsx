import React, { useState, useEffect } from 'react';
import { useLocation, Link } from 'react-router-dom';

const PAGE_TITLES = {
  '/':                 { title: 'Tableau de bord',     crumb: [] },
  '/eleves':           { title: 'Gestion des Élèves',  crumb: ['Élèves'] },
  '/planning':         { title: 'Planning & Séances',  crumb: ['Planning'] },
  '/presences':        { title: 'Présences',           crumb: ['Présences'] },
  '/absences':         { title: 'Absences Journalières',crumb: ['Absences'] },
  '/billets':          { title: 'Billets',             crumb: ['Billets'] },
  '/notes':            { title: 'Notes & Évaluations', crumb: ['Pédagogie','Notes'] },
  '/bulletins':        { title: 'Bulletins PDF',       crumb: ['Pédagogie','Bulletins'] },
  '/diagnostic':       { title: 'Diagnostic Niveau',   crumb: ['Pédagogie','Diagnostic'] },
  '/factures':         { title: 'Finance & Paiements', crumb: ['Finance','Factures'] },
  '/budget':           { title: 'Budget Annuel',       crumb: ['Finance','Budget'] },
  '/transport':        { title: 'Transport Scolaire',  crumb: ['Gestion','Transport'] },
  '/cantine':          { title: 'Cantine',             crumb: ['Gestion','Cantine'] },
  '/stock':            { title: 'Stock & Inventaire',  crumb: ['Gestion','Stock'] },
  '/personnel-admin':  { title: 'Personnel',           crumb: ['Gestion','Personnel'] },
  '/entretien':        { title: 'Entretien Bâtiment',  crumb: ['Gestion','Entretien'] },
  '/surveillance':     { title: 'Surveillance Dahua',  crumb: ['Surveillance'] },
  '/pointage':         { title: 'Pointage Enseignants',crumb: ['Pointage'] },
  '/messages':         { title: 'Messages',            crumb: ['Communication','Messages'] },
  '/campagnes':        { title: 'Campagnes',           crumb: ['Communication','Campagnes'] },
  '/centres':          { title: 'Marketplace',         crumb: ['Marketplace'] },
  '/mes-reservations': { title: 'Mes Réservations',   crumb: ['Marketplace','Réservations'] },
  '/profil':           { title: 'Mon Profil',          crumb: ['Paramètres','Profil'] },
  '/audit-logs':       { title: "Journal d'audit",    crumb: ['Paramètres','Audit'] },
  '/super-admin':      { title: 'Super-Admin',         crumb: ['Super-Admin'] },
};

export default function Header({ user }) {
  const location = useLocation();
  const [notifications, setNotifications] = useState([]);
  const [showNotifs, setShowNotifs]        = useState(false);
  const [notifCount, setNotifCount]        = useState(0);
  const [search, setSearch]               = useState('');
  const [today, setToday]                 = useState('');

  const pageInfo = PAGE_TITLES[location.pathname] || { title: 'EduGest DZ', crumb: [] };

  useEffect(() => {
    const d = new Date();
    setToday(d.toLocaleDateString('fr-DZ', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
    }));
  }, []);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) return;

    const BASE = import.meta.env.VITE_API_BASE_URL?.replace('/api/v1', '') || '';
    fetch(`${BASE}/api/v1/notifications/parent?lu=false&per_page=5`, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then(r => r.json())
      .then(data => {
        if (data?.success) {
          setNotifications(data.data?.data || []);
          setNotifCount(data.non_lues || 0);
        }
      })
      .catch(() => {});
  }, [location.pathname]);

  const handleSearch = (e) => {
    if (e.key === 'Enter' && search.trim()) {
      window.location.href = `/eleves?search=${encodeURIComponent(search)}`;
    }
  };

  return (
    <header style={{
      background: '#0D1117',
      borderBottom: '1px solid #1E2D40',
      height: '60px',
      display: 'flex',
      alignItems: 'center',
      padding: '0 24px',
      gap: '16px',
      position: 'sticky',
      top: 0,
      zIndex: 30,
      flexShrink: 0,
    }}>

      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: '15px', fontWeight: 800, color: '#fff', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
          {pageInfo.title}
        </div>
        {pageInfo.crumb.length > 0 && (
          <div style={{ display: 'flex', alignItems: 'center', gap: '4px', fontSize: '10px', color: '#64748B', marginTop: '1px' }}>
            <Link to="/" style={{ color: '#64748B', textDecoration: 'none' }}>EduGest</Link>
            {pageInfo.crumb.map((c, i) => (
              <React.Fragment key={i}>
                <span>›</span>
                <span style={{ color: i === pageInfo.crumb.length - 1 ? '#93C5FD' : '#64748B' }}>{c}</span>
              </React.Fragment>
            ))}
          </div>
        )}
      </div>

      <div style={{ fontSize: '11px', color: '#475569', whiteSpace: 'nowrap', display: 'none' }}
           className="md:block">
        {today}
      </div>

      <div style={{
        display: 'flex', alignItems: 'center', gap: '8px',
        background: '#161C26', border: '1px solid #1E2D40',
        borderRadius: '8px', padding: '7px 12px',
        fontSize: '12px', color: '#64748B', cursor: 'text',
        minWidth: '200px', maxWidth: '260px',
      }}>
        <span>🔍</span>
        <input
          value={search}
          onChange={e => setSearch(e.target.value)}
          onKeyDown={handleSearch}
          placeholder="Rechercher élève, facture..."
          style={{
            background: 'none', border: 'none', outline: 'none',
            color: '#E2E8F0', fontSize: '12px', width: '100%',
            fontFamily: 'Inter, sans-serif',
          }}
        />
      </div>

      <div style={{ position: 'relative' }}>
        <button
          onClick={() => setShowNotifs(!showNotifs)}
          style={{
            width: '36px', height: '36px',
            background: '#161C26', border: '1px solid #1E2D40',
            borderRadius: '9px', display: 'flex', alignItems: 'center',
            justifyContent: 'center', cursor: 'pointer', fontSize: '16px',
            position: 'relative',
          }}
        >
          🔔
          {notifCount > 0 && (
            <span style={{
              position: 'absolute', top: '5px', right: '6px',
              width: '8px', height: '8px',
              background: '#EF4444', borderRadius: '50%',
              border: '2px solid #0D1117',
            }} />
          )}
        </button>

        {showNotifs && (
          <div style={{
            position: 'absolute', right: 0, top: '44px',
            width: '300px', background: '#0D1117',
            border: '1px solid #1E2D40', borderRadius: '12px',
            boxShadow: '0 16px 48px rgba(0,0,0,0.5)',
            zIndex: 100, overflow: 'hidden',
          }}>
            <div style={{ padding: '12px 16px', borderBottom: '1px solid #1E2D40', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span style={{ fontSize: '12px', fontWeight: 700, color: '#E2E8F0' }}>
                Notifications {notifCount > 0 && <span style={{ color: '#EF4444' }}>({notifCount})</span>}
              </span>
              <Link to="/profil" onClick={() => setShowNotifs(false)} style={{ fontSize: '10px', color: '#93C5FD', textDecoration: 'none' }}>
                Tout voir
              </Link>
            </div>
            {notifications.length === 0 ? (
              <div style={{ padding: '24px', textAlign: 'center', color: '#64748B', fontSize: '12px' }}>
                ✅ Aucune notification non lue
              </div>
            ) : (
              notifications.map((n, i) => (
                <div key={n.id || i} style={{
                  padding: '10px 16px', borderBottom: '1px solid #1E2D4044',
                  display: 'flex', gap: '10px', alignItems: 'flex-start',
                  background: !n.lu ? '#2563eb08' : 'transparent',
                }}>
                  <span style={{ fontSize: '18px' }}>
                    {{ note:'📝', bulletin:'📄', absence:'⚠️', signalement:'📋', paiement:'💳' }[n.type] || '🔔'}
                  </span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: '11px', fontWeight: 600, color: '#E2E8F0', marginBottom: '2px' }}>{n.titre}</div>
                    <div style={{ fontSize: '10px', color: '#64748B', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{n.corps}</div>
                  </div>
                  {!n.lu && <div style={{ width: '6px', height: '6px', borderRadius: '50%', background: '#2563EB', flexShrink: 0, marginTop: '4px' }} />}
                </div>
              ))
            )}
          </div>
        )}
      </div>

      <Link to="/profil" style={{
        width: '36px', height: '36px',
        background: '#161C26', border: '1px solid #1E2D40',
        borderRadius: '9px', display: 'flex', alignItems: 'center',
        justifyContent: 'center', fontSize: '16px', textDecoration: 'none',
      }}>
        ⚙️
      </Link>

    </header>
  );
}
