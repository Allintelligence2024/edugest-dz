import React, { useState, useRef, useEffect } from 'react';

import { Search, X } from 'lucide-react';

/**
 * Barre de recherche avec anti-rebond.
 *
 * Deux contrats de callback sont acceptés :
 *   • `onSearch(valeur)` — contrat d'origine du composant ;
 *   • `onChange(valeur)` — contrat utilisé par les pages de liste.
 *
 * Les deux coexistent volontairement. Les pages Élèves, Enseignants,
 * Groupes, Matières, Notes et Salles passaient `value` + `onChange` alors
 * que le composant n'attendait que `onSearch` : la frappe déclenchait
 * `TypeError: onSearch is not a function` dans le timer d'anti-rebond —
 * exception asynchrone, donc invisible à l'écran — et la recherche
 * n'atteignait jamais l'API. La barre semblait fonctionner, elle ne
 * faisait rien.
 *
 * L'appel est désormais optionnel des deux côtés : aucun callback ne peut
 * faire planter la saisie.
 */
export default function SearchBar({
  placeholder = 'Rechercher...',
  onSearch,
  onChange,
  value,
  delay = 400,
  className = '',
}) {
  const estControle = value !== undefined;
  const [valeurLocale, setValeurLocale] = useState(value ?? '');
  const timerRef = useRef(null);

  // Suit la valeur imposée par le parent (réinitialisation d'un filtre, etc.).
  useEffect(() => {
    if (estControle) setValeurLocale(value ?? '');
  }, [estControle, value]);

  const notifier = (valeurRecherchee) => {
    if (typeof onSearch === 'function') onSearch(valeurRecherchee);
    if (typeof onChange === 'function') onChange(valeurRecherchee);
  };

  const handleChange = (e) => {
    const nouvelle = e.target.value;
    setValeurLocale(nouvelle);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => notifier(nouvelle), delay);
  };

  const clear = () => {
    if (timerRef.current) clearTimeout(timerRef.current);
    setValeurLocale('');
    notifier('');
  };

  useEffect(() => () => {
    if (timerRef.current) clearTimeout(timerRef.current);
  }, []);

  return (
    <div className={`relative flex-1 ${className}`}>
      <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400"><Search size={16} aria-hidden='true' /></span>
      <input type="text" value={valeurLocale} onChange={handleChange} placeholder={placeholder}
             className="w-full pl-10 pr-9 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500 transition-colors bg-white" />
      {valeurLocale && (
        <button onClick={clear} aria-label="Effacer la recherche"
                className="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 transition-colors"><X size={16} aria-hidden="true" /></button>
      )}
    </div>
  );
}
