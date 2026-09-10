import React from 'react';

import { RefreshCw } from 'lucide-react';
import { useI18n } from '@context/I18nContext';

/**
 * Barre de filtres déroulants.
 *
 * Deux tolérances par rapport à la version d'origine, toutes deux motivées
 * par des filtres qui ne s'affichaient tout simplement pas :
 *
 *  1. `type` est facultatif. Le composant n'affichait un `<select>` que si
 *     `filter.type === 'select'` ; or aucune des pages (Élèves, Enseignants,
 *     Groupes, Salles) ne renseignait ce champ. La barre rendait donc du
 *     vide sur toutes les pages de liste, sans erreur. Un filtre pourvu
 *     d'`options` est désormais un `select` par défaut.
 *
 *  2. Les options peuvent être des chaînes brutes (`'3AS'`) autant que des
 *     objets `{ value, label }`. Les pages utilisaient les deux formes.
 *
 * Le rendu ne dépend plus de conventions implicites : ce qui est déclaré
 * s'affiche.
 */

const normaliserOptions = (options = []) =>
  options.map((option) =>
    option !== null && typeof option === 'object'
      ? option
      : { value: option, label: String(option) }
  );

const typeDuFiltre = (filter) => {
  if (filter.type) return filter.type;
  return Array.isArray(filter.options) ? 'select' : null;
};

export default function FilterBar({ filters = [], values = {}, onChange, onReset }) {
  const { t } = useI18n();
  const emettre = (cle, valeur) => {
    if (typeof onChange !== 'function') return;
    // Les pages passent directement `setFilters` : on fusionne pour ne pas
    // écraser les autres filtres actifs.
    onChange({ ...values, [cle]: valeur });
  };

  return (
    <div className="flex flex-wrap items-center gap-3">
      {filters.map((filter) => {
        const type = typeDuFiltre(filter);

        if (type === 'select') {
          return (
            <div key={filter.key}>
              <select value={values[filter.key] ?? ''}
                      aria-label={filter.label}
                      onChange={(e) => emettre(filter.key, e.target.value)}
                      className="px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500 bg-white cursor-pointer">
                <option value="">{filter.placeholder || filter.label}</option>
                {normaliserOptions(filter.options).map((opt) => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </select>
            </div>
          );
        }

        if (type === 'date') {
          return (
            <div key={filter.key}>
              <input type="date" value={values[filter.key] ?? ''}
                     aria-label={filter.label}
                     onChange={(e) => emettre(filter.key, e.target.value)}
                     className="px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500 bg-white" />
            </div>
          );
        }

        return null;
      })}

      {Object.values(values).some((v) => v) && (
        <button onClick={onReset}
                className="text-sm text-neutral-500 hover:text-primary-600 flex items-center gap-1 transition-colors">
          <RefreshCw size={14} aria-hidden='true' />{t('reset')}
                  </button>
      )}
    </div>
  );
}
