import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '@hooks/useAuth';
import { useI18n } from '@context/I18nContext';

const { lang, changeLang } = useI18n();

const NAV_ITEMS = [
  { label: 'Tableau de bord', path: '/', icon: '📊' },
  { label: 'Planning', path: '/planning', icon: '📅' },
  { label: 'Présences', path: '/presences', icon: '📋' },
  { label: 'Facturation', path: '/factures', icon: '💰' },
  { label: 'Élèves', path: '/eleves', icon: '👨‍🎓' },
  { label: 'Enseignants', path: '/enseignants', icon: '👨‍🏫' },
  { label: 'Groupes', path: '/groupes', icon: '📚' },
  { label: 'Salles', path: '/salles', icon: '🏫' },
];

export default function Sidebar() {
  const { user, tenant, logout } = useAuth();

  return (
    <aside className="w-64 bg-white border-r border-neutral-200 flex flex-col h-screen sticky top-0">
      <div className="p-5 border-b border-neutral-100">
        <h1 className="text-xl font-bold text-primary-700">EduGest DZ</h1>
        {tenant && <p className="text-xs text-neutral-400 mt-0.5">{tenant.nom}</p>}
      </div>
      <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
        {NAV_ITEMS.map(item => (
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
        ))}
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
