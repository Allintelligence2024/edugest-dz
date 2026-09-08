# 🤖 MISSION DEEPSEEK — Upgrade Design Frontend Professionnel
## EduGest DZ · Branche : develop · 6 Juillet 2026
## Objectif : Transformer le frontend actuel en interface professionnelle EdTech

---

## ANALYSE DU CODE EXISTANT (vérifié sur GitHub)

### Ce qui existe
- `Sidebar.jsx` → Tailwind CSS · light theme `bg-white border-neutral-200` · NavLink react-router
- `App.jsx` → `bg-neutral-50` · layout `flex min-h-screen` · Toaster react-hot-toast
- `Header.jsx` → existe (composant séparé)
- `DashboardPage.jsx` → inline styles dark · données partiellement mockées
- `index.css` ou `tailwind.config.js` → Tailwind configuré avec `primary` color

### Ce qu'on NE TOUCHE PAS
- La logique des API calls dans chaque page
- Les routes dans App.jsx
- Les Context (AuthContext, I18nContext)
- Les composants métier (AbsencesPage, BudgetPage, etc.)

### Ce qu'on REMPLACE / AMÉLIORE
- Sidebar.jsx → design dark professionnel + badges + school badge + collapse
- Header.jsx → topbar dark avec search + notifications + breadcrumb
- App.jsx → changer `bg-neutral-50` en dark theme + layout cohérent
- index.css → variables CSS design system complet
- LoginPage.jsx → design professionnel (déjà amélioré mais à vérifier)
- DashboardPage.jsx → données réelles + design cohérent

### RÈGLES ABSOLUES
1. Ne JAMAIS casser les routes existantes
2. Ne JAMAIS supprimer les imports de pages existantes dans App.jsx
3. Tailwind reste le système — ne pas tout passer en inline styles
4. Tester que `npm run build` passe sans erreur avant de committer
5. 0 régression backend — ne pas toucher au backend

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop
git pull origin main
cd edugestdz/frontend
```

---

## ÉTAPE 1 — Système de design : variables CSS globales

**Modifier :** `edugestdz/frontend/src/index.css`

Remplacer ou ajouter en haut du fichier (garder les imports Tailwind existants) :

```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

@tailwind base;
@tailwind components;
@tailwind utilities;

/* ── Design System EduGest DZ ── */
:root {
  /* Couleurs principales */
  --eg-bg:        #070B14;
  --eg-surface:   #0D1117;
  --eg-surface2:  #161C26;
  --eg-border:    #1E2D40;
  --eg-text:      #E2E8F0;
  --eg-muted:     #64748B;

  /* Accents */
  --eg-blue:      #2563EB;
  --eg-blue-light:#93C5FD;
  --eg-green:     #10B981;
  --eg-orange:    #F59E0B;
  --eg-red:       #EF4444;
  --eg-purple:    #7C3AED;
  --eg-teal:      #06B6D4;

  /* Sidebar */
  --sidebar-w:    240px;

  /* Transitions */
  --transition:   all 0.15s ease;
}

/* Police globale */
* { font-family: 'Inter', system-ui, -apple-system, sans-serif; }

/* Scrollbar dark */
::-webkit-scrollbar       { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: var(--eg-surface); }
::-webkit-scrollbar-thumb { background: var(--eg-border); border-radius: 99px; }
::-webkit-scrollbar-thumb:hover { background: var(--eg-muted); }

/* Animations réutilisables */
@keyframes fadeIn  { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
@keyframes pulse   { 0%,100%{opacity:1} 50%{opacity:.5} }
@keyframes slideIn { from { transform:translateX(-100%); } to { transform:translateX(0); } }

.animate-fadeIn  { animation: fadeIn  0.2s ease forwards; }
.animate-pulse-slow { animation: pulse 2s infinite; }

/* Card hover effect */
.card-hover { transition: var(--transition); }
.card-hover:hover { transform: translateY(-2px); border-color: #2563eb55 !important; }

/* Gradient text */
.gradient-text {
  background: linear-gradient(135deg, var(--eg-blue), var(--eg-purple), var(--eg-teal));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
```

---

## ÉTAPE 2 — tailwind.config.js : étendre la config

**Modifier :** `edugestdz/frontend/tailwind.config.js`

```js
/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx,ts,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },
      colors: {
        // Design system EduGest DZ
        eg: {
          bg:       '#070B14',
          surface:  '#0D1117',
          surface2: '#161C26',
          border:   '#1E2D40',
          text:     '#E2E8F0',
          muted:    '#64748B',
        },
        primary: {
          50:  '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
      },
      borderRadius: {
        'xl':  '12px',
        '2xl': '16px',
        '3xl': '24px',
      },
      boxShadow: {
        'glow-blue':   '0 0 20px rgba(37,99,235,0.3)',
        'glow-green':  '0 0 20px rgba(16,185,129,0.3)',
        'card':        '0 1px 3px rgba(0,0,0,0.4)',
        'card-hover':  '0 8px 24px rgba(0,0,0,0.5)',
      },
      animation: {
        'fade-in': 'fadeIn 0.2s ease forwards',
        'pulse-slow': 'pulse 2s infinite',
      },
    },
  },
  plugins: [],
}
```

---

## ÉTAPE 3 — Sidebar.jsx : design dark professionnel complet

**Remplacer complètement :**
`edugestdz/frontend/src/components/Sidebar.jsx`

```jsx
import React, { useState, useEffect } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '@context/AuthContext';

// ── Couleurs par module (accent de la barre de navigation) ────────────────
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

// ── Structure de navigation ───────────────────────────────────────────────
const NAV_SECTIONS = [
  {
    label: 'Principal',
    items: [
      { label: 'Tableau de bord', path: '/',        icon: '📊', end: true },
      { label: 'Élèves',          path: '/eleves',  icon: '👦', badge: null },
      { label: 'Planning',        path: '/planning', icon: '📅' },
      { label: 'Présences',       path: '/presences',icon: '✅' },
      { label: 'Absences',        path: '/absences', icon: '⚠️', badgeKey: 'absences' },
      { label: 'Billets',         path: '/billets',  icon: '🎫' },
    ],
  },
  {
    label: 'Pédagogie',
    items: [
      { label: 'Notes',           path: '/notes',      icon: '📝' },
      { label: 'Bulletins',       path: '/bulletins',  icon: '📄' },
      { label: 'Diagnostic niveau',path: '/diagnostic',icon: '🔬', badgeKey: 'critiques' },
    ],
  },
  {
    label: 'Finance',
    items: [
      { label: 'Factures',        path: '/factures',  icon: '💰', badgeKey: 'impayes' },
      { label: 'Budget & Dépenses',path: '/budget',   icon: '📈' },
      { label: 'Pointage',        path: '/pointage',  icon: '🏷️' },
    ],
  },
  {
    label: 'Gestion Centre',
    items: [
      { label: 'Transport',       path: '/transport',      icon: '🚌' },
      { label: 'Cantine',         path: '/cantine',        icon: '🍽️' },
      { label: 'Stock & Inventaire',path: '/stock',        icon: '📦' },
      { label: 'Personnel admin.',  path: '/personnel-admin',icon: '👷' },
      { label: 'Entretien',        path: '/entretien',     icon: '🔧' },
      { label: 'Surveillance',     path: '/surveillance',  icon: '🔒', badgeKey: 'alertes' },
    ],
  },
  {
    label: 'Communication',
    items: [
      { label: 'Messages',        path: '/messages',   icon: '💬', badgeKey: 'messages' },
      { label: 'Campagnes',       path: '/campagnes',  icon: '📢' },
    ],
  },
  {
    label: 'Marketplace',
    items: [
      { label: 'Centres (public)', path: '/centres',         icon: '🛒' },
      { label: 'Mes réservations', path: '/mes-reservations',icon: '📅' },
    ],
  },
  {
    label: 'Paramètres',
    items: [
      { label: 'Mon Profil',      path: '/profil',     icon: '⚙️' },
      { label: 'Journal audit',   path: '/audit-logs', icon: '📋' },
      { label: 'Super-Admin',     path: '/super-admin',icon: '🛡️', role: 'super_admin' },
    ],
  },
];

export default function Sidebar() {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [collapsed, setCollapsed]   = useState(false);
  const [badges, setBadges]         = useState({});
  const [collapsedSections, setCollapsedSections] = useState({});

  // Couleur active selon la route
  const activeColor = MODULE_COLORS[location.pathname] || '#2563EB';

  // Charger les badges depuis l'API (nombres d'alertes)
  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) return;

    const headers = { Authorization: `Bearer ${token}` };
    const BASE = import.meta.env.VITE_API_BASE_URL?.replace('/api/v1', '') || '';

    // Charger les stats en parallèle, silencieusement
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

  const tenantName   = localStorage.getItem('tenantName')  || 'Mon établissement';
  const tenantWilaya = localStorage.getItem('tenantWilaya') || 'Algérie';

  return (
    <aside
      style={{
        width: collapsed ? '64px' : '240px',
        background: '#0D1117',
        borderRight: '1px solid #1E2D40',
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
      {/* ── LOGO ── */}
      <div style={{
        padding: collapsed ? '18px 14px' : '18px 16px',
        borderBottom: '1px solid #1E2D40',
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
              EduGest DZ
            </div>
            <div style={{ fontSize: '9px', color: '#64748B', whiteSpace: 'nowrap' }}>
              Gestion Scolaire
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

      {/* ── SCHOOL BADGE ── */}
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
          <div style={{ fontSize: '9px', color: '#64748B' }}>
            📍 {tenantWilaya}
          </div>
        </div>
      )}

      {/* ── NAVIGATION ── */}
      <nav style={{ flex: 1, overflowY: 'auto', padding: '6px 0' }}>
        {NAV_SECTIONS.map(section => {
          // Filtrer par rôle
          const items = section.items.filter(item =>
            !item.role || user?.role === item.role
          );
          if (items.length === 0) return null;

          const sectionCollapsed = collapsedSections[section.label];

          return (
            <div key={section.label} style={{ marginBottom: '2px' }}>
              {/* Label section */}
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

              {/* Items */}
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
                      color: navActive || isActive ? color : '#64748B',
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
                    {/* Badge sur icône quand collapsed */}
                    {collapsed && badgeCount > 0 && (
                      <span style={{
                        position: 'absolute', top: '4px', right: '4px',
                        width: '8px', height: '8px',
                        background: '#EF4444', borderRadius: '50%',
                        border: '2px solid #0D1117',
                      }} />
                    )}
                  </NavLink>
                );
              })}
            </div>
          );
        })}
      </nav>

      {/* ── USER FOOTER ── */}
      <div style={{ borderTop: '1px solid #1E2D40', padding: '12px' }}>
        {!collapsed ? (
          <div style={{
            display: 'flex', alignItems: 'center', gap: '10px',
            padding: '9px 10px', borderRadius: '10px',
            background: '#161C26', cursor: 'pointer',
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
              <div style={{ fontSize: '11px', fontWeight: 700, color: '#E2E8F0', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                {user?.nom} {user?.prenom}
              </div>
              <div style={{ fontSize: '9px', color: '#64748B', textTransform: 'capitalize' }}>
                {user?.role}
              </div>
            </div>
            <button
              onClick={logout}
              title="Déconnexion"
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
```

---

## ÉTAPE 4 — Header.jsx : topbar dark professionnel

**Remplacer complètement :**
`edugestdz/frontend/src/components/Header.jsx`

```jsx
import React, { useState, useEffect } from 'react';
import { useLocation, Link } from 'react-router-dom';

// Map chemin → titre + breadcrumb
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
  '/audit-logs':       { title: 'Journal d\'audit',   crumb: ['Paramètres','Audit'] },
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
    // Charger les notifications parent non lues (si rôle parent)
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

      {/* ── Titre + Breadcrumb ── */}
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

      {/* ── Date ── */}
      <div style={{ fontSize: '11px', color: '#475569', whiteSpace: 'nowrap', display: 'none' }}
           className="md:block">
        {today}
      </div>

      {/* ── Search ── */}
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

      {/* ── Notifications ── */}
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

        {/* Dropdown notifications */}
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

      {/* ── Settings ── */}
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
```

---

## ÉTAPE 5 — App.jsx : changer le layout en dark theme

**Modifier :** `edugestdz/frontend/src/App.jsx`

Trouver et remplacer uniquement ces lignes dans `ProtectedLayout` :

```jsx
// AVANT :
<div className="flex min-h-screen">
  <Sidebar/>
  <div className="flex-1 flex flex-col">
    <Header user={user}/>
    <main className="flex-1 p-6 overflow-y-auto bg-neutral-50">
      <Outlet/>
    </main>
  </div>
</div>

// APRÈS :
<div style={{ display: 'flex', minHeight: '100vh', background: '#070B14' }}>
  <Sidebar/>
  <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
    <Header user={user}/>
    <main style={{
      flex: 1,
      padding: '24px',
      overflowY: 'auto',
      background: '#070B14',
      color: '#E2E8F0',
    }}>
      <Outlet/>
    </main>
  </div>
</div>
```

Et changer le spinner de loading :

```jsx
// AVANT :
<div className="min-h-screen flex items-center justify-center">
  <div className="animate-spin w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full"/>
</div>

// APRÈS :
<div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#070B14' }}>
  <div style={{ textAlign: 'center' }}>
    <div style={{ fontSize: '40px', marginBottom: '16px' }}>🎓</div>
    <div style={{ width: '40px', height: '40px', margin: '0 auto', border: '3px solid #1E2D40', borderTop: '3px solid #2563EB', borderRadius: '50%', animation: 'spin 0.8s linear infinite' }} />
    <div style={{ marginTop: '16px', fontSize: '13px', color: '#64748B' }}>Chargement EduGest DZ...</div>
  </div>
</div>
```

Ajouter dans `index.css` l'animation spin :
```css
@keyframes spin { to { transform: rotate(360deg); } }
```

Aussi modifier le Toaster dans App.jsx :

```jsx
// AVANT :
<Toaster position="top-right" toastOptions={{
  duration: 3500,
  style: { borderRadius: '12px', background: '#fff', color: '#212529', fontSize: '14px', boxShadow: '0 8px 30px rgba(0,0,0,0.12)' },
}}/>

// APRÈS :
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
}}/>
```

---

## ÉTAPE 6 — Composant réutilisable : StatCard

**Créer :** `edugestdz/frontend/src/components/StatCard.jsx`

```jsx
/**
 * StatCard — Carte KPI réutilisable pour tous les dashboards
 * Usage :
 *   <StatCard icon="👦" label="Élèves actifs" value={247} color="#10B981" sub="+8 ce mois" />
 */
export default function StatCard({ icon, label, value, color = '#2563EB', sub, trend, onClick, loading = false }) {
  return (
    <div
      onClick={onClick}
      className="card-hover"
      style={{
        background: '#0D1117',
        border: `1px solid #1E2D40`,
        borderTop: `2px solid ${color}`,
        borderRadius: '14px',
        padding: '18px 20px',
        cursor: onClick ? 'pointer' : 'default',
        position: 'relative',
        overflow: 'hidden',
      }}
    >
      {/* Glow de fond */}
      <div style={{
        position: 'absolute', top: 0, right: 0,
        width: '80px', height: '80px', borderRadius: '50%',
        background: color,
        opacity: 0.04,
        transform: 'translate(20px, -20px)',
      }} />

      <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: '12px' }}>
        <div style={{ fontSize: '10px', fontWeight: 700, color: '#64748B', textTransform: 'uppercase', letterSpacing: '1px' }}>
          {label}
        </div>
        <div style={{
          width: '34px', height: '34px', borderRadius: '9px',
          background: `${color}22`,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          fontSize: '16px',
        }}>
          {icon}
        </div>
      </div>

      <div style={{ fontSize: '26px', fontWeight: 900, color: '#fff', marginBottom: '6px' }}>
        {loading ? (
          <div style={{ width: '80px', height: '28px', background: '#1E2D40', borderRadius: '6px', animation: 'pulse 1.5s infinite' }} />
        ) : value}
      </div>

      {(sub || trend) && (
        <div style={{ fontSize: '11px', display: 'flex', alignItems: 'center', gap: '6px' }}>
          {trend && (
            <span style={{ color: trend.startsWith('+') ? '#10B981' : '#EF4444', fontWeight: 700 }}>
              {trend}
            </span>
          )}
          {sub && <span style={{ color: '#64748B' }}>{sub}</span>}
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 7 — Composant réutilisable : PageHeader

**Créer :** `edugestdz/frontend/src/components/PageHeader.jsx`

```jsx
/**
 * PageHeader — En-tête de page réutilisable
 * Usage :
 *   <PageHeader
 *     title="Gestion des Élèves"
 *     subtitle="247 élèves actifs"
 *     actions={<button>+ Nouvel élève</button>}
 *   />
 */
export default function PageHeader({ title, subtitle, actions, color = '#2563EB' }) {
  return (
    <div style={{
      display: 'flex', alignItems: 'center', justifyContent: 'space-between',
      marginBottom: '24px', paddingBottom: '20px',
      borderBottom: '1px solid #1E2D40',
    }}>
      <div>
        <h1 style={{
          fontSize: '20px', fontWeight: 900, color: '#fff',
          marginBottom: '4px', display: 'flex', alignItems: 'center', gap: '8px',
        }}>
          <span style={{
            display: 'inline-block', width: '4px', height: '20px',
            background: color, borderRadius: '2px',
          }} />
          {title}
        </h1>
        {subtitle && (
          <p style={{ fontSize: '12px', color: '#64748B' }}>{subtitle}</p>
        )}
      </div>
      {actions && (
        <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
          {actions}
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 8 — Composant réutilisable : DataTable

**Créer :** `edugestdz/frontend/src/components/DataTable.jsx`

```jsx
import { useState } from 'react';

/**
 * DataTable — Tableau réutilisable avec tri, recherche locale et pagination
 * Usage :
 *   <DataTable
 *     columns={[{ key:'nom', label:'Nom', sortable:true }, ...]}
 *     data={eleves}
 *     loading={loading}
 *     emptyMessage="Aucun élève trouvé"
 *   />
 */
export default function DataTable({ columns = [], data = [], loading = false, emptyMessage = 'Aucune donnée' }) {
  const [sortKey, setSortKey]   = useState(null);
  const [sortDir, setSortDir]   = useState('asc');
  const [page, setPage]         = useState(1);
  const perPage = 10;

  const sorted = [...data].sort((a, b) => {
    if (!sortKey) return 0;
    const va = a[sortKey] ?? '';
    const vb = b[sortKey] ?? '';
    return sortDir === 'asc'
      ? String(va).localeCompare(String(vb))
      : String(vb).localeCompare(String(va));
  });

  const totalPages = Math.ceil(sorted.length / perPage);
  const paged = sorted.slice((page - 1) * perPage, page * perPage);

  const handleSort = (key) => {
    if (sortKey === key) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    else { setSortKey(key); setSortDir('asc'); }
  };

  return (
    <div style={{ background: '#0D1117', border: '1px solid #1E2D40', borderRadius: '14px', overflow: 'hidden' }}>
      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr style={{ background: '#161C26' }}>
            {columns.map(col => (
              <th key={col.key}
                onClick={() => col.sortable && handleSort(col.key)}
                style={{
                  padding: '11px 16px', textAlign: 'left',
                  fontSize: '10px', fontWeight: 700, color: '#64748B',
                  textTransform: 'uppercase', letterSpacing: '1px',
                  borderBottom: '1px solid #1E2D40',
                  cursor: col.sortable ? 'pointer' : 'default',
                  userSelect: 'none', whiteSpace: 'nowrap',
                }}
              >
                {col.label}
                {col.sortable && sortKey === col.key && (
                  <span style={{ marginLeft: '4px' }}>{sortDir === 'asc' ? '↑' : '↓'}</span>
                )}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {loading ? (
            Array.from({ length: 5 }).map((_, i) => (
              <tr key={i}>
                {columns.map((col, j) => (
                  <td key={j} style={{ padding: '12px 16px', borderBottom: '1px solid #1E2D4044' }}>
                    <div style={{ height: '14px', background: '#1E2D40', borderRadius: '4px', width: j === 0 ? '60%' : '40%', animation: 'pulse 1.5s infinite' }} />
                  </td>
                ))}
              </tr>
            ))
          ) : paged.length === 0 ? (
            <tr>
              <td colSpan={columns.length} style={{ padding: '40px', textAlign: 'center', color: '#64748B', fontSize: '13px' }}>
                {emptyMessage}
              </td>
            </tr>
          ) : (
            paged.map((row, i) => (
              <tr key={row.id || i} style={{ transition: 'background .1s' }}
                onMouseEnter={e => e.currentTarget.style.background = '#161C2688'}
                onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
              >
                {columns.map(col => (
                  <td key={col.key} style={{
                    padding: '12px 16px', fontSize: '12px', color: '#E2E8F0',
                    borderBottom: '1px solid #1E2D4044', verticalAlign: 'middle',
                  }}>
                    {col.render ? col.render(row[col.key], row) : row[col.key]}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>

      {/* Pagination */}
      {totalPages > 1 && (
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          padding: '12px 16px', borderTop: '1px solid #1E2D40',
          fontSize: '11px', color: '#64748B',
        }}>
          <span>Affichage {(page-1)*perPage+1}-{Math.min(page*perPage,data.length)} sur {data.length}</span>
          <div style={{ display: 'flex', gap: '4px' }}>
            {Array.from({ length: Math.min(totalPages, 5) }).map((_, i) => (
              <button key={i+1} onClick={() => setPage(i+1)} style={{
                width: '28px', height: '28px', borderRadius: '7px',
                border: `1px solid ${page === i+1 ? '#2563EB' : '#1E2D40'}`,
                background: page === i+1 ? '#2563EB' : '#161C26',
                color: page === i+1 ? '#fff' : '#64748B',
                fontSize: '11px', cursor: 'pointer', fontWeight: page === i+1 ? 700 : 400,
              }}>{i+1}</button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 9 — Composant réutilisable : Badge / StatusBadge

**Créer :** `edugestdz/frontend/src/components/Badge.jsx`

```jsx
/**
 * Badge — Badge de statut réutilisable
 * Usage : <Badge type="success">Actif</Badge>
 *         <Badge type="danger">Critique</Badge>
 *         <Badge color="#10B981">Custom</Badge>
 */
const TYPES = {
  success: { bg: '#10B98122', color: '#10B981', dot: '#10B981' },
  danger:  { bg: '#EF444422', color: '#EF4444', dot: '#EF4444' },
  warning: { bg: '#F59E0B22', color: '#F59E0B', dot: '#F59E0B' },
  info:    { bg: '#2563EB22', color: '#93C5FD', dot: '#2563EB' },
  muted:   { bg: '#64748B22', color: '#94A3B8', dot: '#64748B' },
  purple:  { bg: '#7C3AED22', color: '#C4B5FD', dot: '#7C3AED' },
};

export default function Badge({ type = 'info', color, bg, children, dot = true }) {
  const style = TYPES[type] || TYPES.info;
  const finalColor = color || style.color;
  const finalBg    = bg || style.bg;

  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: '4px',
      background: finalBg, color: finalColor,
      fontSize: '10px', fontWeight: 700,
      padding: '2px 9px', borderRadius: '20px',
    }}>
      {dot && (
        <span style={{
          width: '5px', height: '5px', borderRadius: '50%',
          background: color || style.dot, flexShrink: 0,
        }} />
      )}
      {children}
    </span>
  );
}
```

---

## ÉTAPE 10 — Bouton primaire réutilisable

**Créer :** `edugestdz/frontend/src/components/Button.jsx`

```jsx
/**
 * Button — Bouton réutilisable
 * Usage :
 *   <Button>Sauvegarder</Button>
 *   <Button variant="secondary" icon="⬇">Exporter</Button>
 *   <Button variant="danger" loading={saving}>Supprimer</Button>
 */
export default function Button({
  children, variant = 'primary', icon, loading = false,
  onClick, disabled, type = 'button', size = 'md', style: extraStyle
}) {
  const sizes = { sm: '7px 12px', md: '9px 18px', lg: '12px 24px' };
  const fontSizes = { sm: '11px', md: '12px', lg: '14px' };

  const variants = {
    primary:   { background: 'linear-gradient(135deg,#2563EB,#1d4ed8)', color: '#fff', border: 'none' },
    secondary: { background: '#161C26', color: '#E2E8F0', border: '1px solid #1E2D40' },
    success:   { background: '#10B98122', color: '#10B981', border: '1px solid #10B98144' },
    danger:    { background: '#EF444422', color: '#EF4444', border: '1px solid #EF444444' },
    ghost:     { background: 'transparent', color: '#64748B', border: 'none' },
  };

  const v = variants[variant] || variants.primary;

  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled || loading}
      style={{
        ...v,
        padding: sizes[size],
        borderRadius: '9px',
        fontSize: fontSizes[size],
        fontWeight: 700,
        cursor: disabled || loading ? 'not-allowed' : 'pointer',
        opacity: disabled ? 0.5 : 1,
        display: 'inline-flex', alignItems: 'center', gap: '6px',
        transition: 'all 0.15s',
        fontFamily: 'Inter, sans-serif',
        ...extraStyle,
      }}
      onMouseEnter={e => !disabled && !loading && (e.currentTarget.style.transform = 'translateY(-1px)')}
      onMouseLeave={e => (e.currentTarget.style.transform = 'translateY(0)')}
    >
      {loading ? (
        <>
          <span style={{ width: '12px', height: '12px', border: '2px solid currentColor', borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 0.7s linear infinite', display: 'inline-block' }} />
          Chargement...
        </>
      ) : (
        <>
          {icon && <span>{icon}</span>}
          {children}
        </>
      )}
    </button>
  );
}
```

---

## ÉTAPE 11 — Exporter les composants depuis un index

**Créer :** `edugestdz/frontend/src/components/index.js`

```js
export { default as StatCard }    from './StatCard';
export { default as PageHeader }  from './PageHeader';
export { default as DataTable }   from './DataTable';
export { default as Badge }       from './Badge';
export { default as Button }      from './Button';
// Les anciens composants restent exportés aussi :
export { default as Sidebar }     from './Sidebar';
export { default as Header }      from './Header';
```

---

## ÉTAPE 12 — Vérifier le build

```bash
cd edugestdz/frontend

# Vérifier qu'il n'y a pas d'erreurs
npm run build 2>&1 | tail -20

# Si erreurs → lire et corriger AVANT de committer
# Erreurs fréquentes :
# - Import manquant → ajouter l'import
# - Prop non utilisée → supprimer
# - Syntaxe JSX incorrecte → vérifier les balises
```

---

## ÉTAPE 13 — Commit & Push

```bash
git add \
  edugestdz/frontend/src/index.css \
  edugestdz/frontend/tailwind.config.js \
  edugestdz/frontend/src/components/Sidebar.jsx \
  edugestdz/frontend/src/components/Header.jsx \
  edugestdz/frontend/src/App.jsx \
  edugestdz/frontend/src/components/StatCard.jsx \
  edugestdz/frontend/src/components/PageHeader.jsx \
  edugestdz/frontend/src/components/DataTable.jsx \
  edugestdz/frontend/src/components/Badge.jsx \
  edugestdz/frontend/src/components/Button.jsx \
  edugestdz/frontend/src/components/index.js

git commit -m "feat(design): Dark theme professionnel — Sidebar collapsible + badges live + Header breadcrumb + composants réutilisables (StatCard, DataTable, Badge, Button)"
git push origin develop
# → PR develop → main
```

---

## RÉCAPITULATIF — Ce que cette mission fait

| Fichier | Action | Résultat visible |
|---|---|---|
| `index.css` | Variables CSS + Inter font + animations | Police pro, scrollbar dark, animations |
| `tailwind.config.js` | Couleurs eg.* + shadows | Classes Tailwind cohérentes |
| `Sidebar.jsx` | Réécriture complète | Dark, collapsible, badges live, school badge |
| `Header.jsx` | Réécriture complète | Breadcrumb, search, notifs dropdown, dark |
| `App.jsx` | Layout dark + Toaster dark | Fond #070B14, spinner branded |
| `StatCard.jsx` | Nouveau composant | Cards KPI réutilisables partout |
| `PageHeader.jsx` | Nouveau composant | En-tête uniforme sur toutes les pages |
| `DataTable.jsx` | Nouveau composant | Tableau avec tri + pagination réutilisable |
| `Badge.jsx` | Nouveau composant | Badges statut cohérents |
| `Button.jsx` | Nouveau composant | Boutons avec loading state |

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_DESIGN_FRONTEND_UPGRADE.md — 13 étapes dans l'ordre.

RÈGLES ABSOLUES :
1. Ne JAMAIS supprimer les routes existantes dans App.jsx.
2. Ne JAMAIS supprimer les imports de pages dans App.jsx.
3. Ne jamais toucher au backend.
4. Après chaque modification majeure : npm run build → vérifier 0 erreur.
5. Sidebar.jsx : garder la compatibilité avec useAuth() et NavLink react-router-dom.
6. Header.jsx : garder la prop user={user} passée depuis App.jsx.
7. Si une erreur "Cannot find module '@context/AuthContext'" → 
   c'est un alias Vite — ne pas changer le chemin, c'est correct.
8. Tester que le login fonctionne toujours après les modifications.

npm run build → 0 erreur
git push origin develop → PR develop → main
```
