import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '@context/AuthContext';
import { useI18n } from '@context/I18nContext';

const NAV_ITEMS = [
  { label: 'Tableau de bord', path: '/', icon: '📊' },
  { label: 'Planning', path: '/planning', icon: '📅' },
  { label: 'Présences', path: '/presences', icon: '📋' },
  { label: 'Absences', path: '/absences', icon: '✅' },
  { label: 'Billets', path: '/billets', icon: '🎫' },
  { label: 'Facturation', path: '/factures', icon: '💰' },
  { label: 'Élèves', path: '/eleves', icon: '👨‍🎓' },
  {
    label: 'Personnel',
    path: '/personnel',
    icon: '👥',
    children: [
      { label: 'Enseignants', path: '/enseignants', icon: '👨‍🏫' },
      { label: 'Paie', path: '/paie', icon: '💰' },
    ],
  },
  { label: 'Groupes', path: '/groupes', icon: '📚' },
  { label: 'Salles', path: '/salles', icon: '🏫' },
  { label: 'Matières', path: '/matieres', icon: '📖' },
  { label: 'Marketplace', path: '/marketplace', icon: '🛒' },
  {
    label: 'Pédagogie',
    path: '/pedagogie',
    icon: '📚',
    children: [
      { label: 'Notes', path: '/notes', icon: '📝' },
      { label: 'Bulletins', path: '/bulletins', icon: '📜' },
    ],
  },
  {
    label: 'Gestion Centre',
    path: '/gestion',
    icon: '🏫',
    children: [
      { label: 'Transport', path: '/transport', icon: '🚌' },
      { label: 'Cantine', path: '/cantine', icon: '🍽️' },
      { label: 'Stock & Inventaire', path: '/stock', icon: '📦' },
      { label: 'Personnel admin.', path: '/personnel-admin', icon: '👷' },
      { label: 'Budget & Finances', path: '/budget', icon: '📊' },
      { label: 'Entretien Bâtiment', path: '/entretien', icon: '🔧' },
    ],
  },
  {
    label: 'Communication',
    path: '/communication',
    icon: '💬',
    children: [
      { label: 'Messages', path: '/messages', icon: '✉️' },
      { label: 'Campagnes', path: '/campagnes', icon: '📢' },
    ],
  },
  {
    label: 'Paramètres',
    path: '/parametres',
    icon: '⚙️',
    children: [
      { label: 'Journal audit', path: '/audit-logs', icon: '📋' },
    ],
  },
];

export default function Sidebar() {
  const { user, tenant, logout } = useAuth();
  const { lang, changeLang } = useI18n();

  return (
    <aside className="w-64 bg-white border-r border-neutral-200 flex flex-col h-screen sticky top-0">
      <div className="p-5 border-b border-neutral-100">
        <h1 className="text-xl font-bold text-primary-700">EduGest DZ</h1>
        {tenant && <p className="text-xs text-neutral-400 mt-0.5">{tenant.nom}</p>}
      </div>
      <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
        {NAV_ITEMS.map(item =>
          item.children ? (
            <div key={item.path}>
              <div className="flex items-center gap-3 px-4 py-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">
                <span className="text-lg">{item.icon}</span>
                <span>{item.label}</span>
              </div>
              <div className="ml-4 space-y-0.5">
                {item.children.map(child => (
                  <NavLink
                    key={child.path}
                    to={child.path}
                    className={({ isActive }) =>
                      `flex items-center gap-3 px-4 py-2 rounded-xl text-sm font-medium transition-colors ${
                        isActive
                          ? 'bg-primary-50 text-primary-700'
                          : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-800'
                      }`
                    }
                  >
                    <span className="text-lg">{child.icon}</span>
                    <span>{child.label}</span>
                  </NavLink>
                ))}
              </div>
            </div>
          ) : (
            <NavLink
              key={item.path}
              to={item.path}
              end={item.path === '/'}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-800'
                }`
              }
            >
              <span className="text-lg">{item.icon}</span>
              <span>{item.label}</span>
            </NavLink>
          ),
        )}
      </nav>
      <div className="p-4 border-t border-neutral-100">
        <div className="flex items-center gap-3 mb-3">
          <div className="w-8 h-8 bg-primary-100 text-primary-700 rounded-full flex items-center justify-center text-xs font-bold">
            {user?.nom?.[0]}{user?.prenom?.[0]}
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-neutral-700 truncate">{user?.nom} {user?.prenom}</p>
            <p className="text-xs text-neutral-400 truncate">{user?.email}</p>
          </div>
        </div>
        <button
          onClick={() => changeLang(lang === 'fr' ? 'ar' : 'fr')}
          className="w-full py-2 rounded-xl text-sm font-medium text-neutral-600 hover:bg-primary-50 hover:text-primary-600 transition-colors mb-1"
        >
          {lang === 'fr' ? '🇩🇿 العربية' : '🇫🇷 Français'}
        </button>
        <button
          onClick={logout}
          className="w-full py-2 rounded-xl text-sm font-medium text-neutral-600 hover:bg-red-50 hover:text-red-600 transition-colors"
        >
          Déconnexion
        </button>
      </div>
    </aside>
  );
}
