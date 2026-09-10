import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

export default function PlanningFilters({ filtres, onChange }) {
  const [enseignants, setEnseignants] = useState([]);
  const [groupes, setGroupes] = useState([]);

  useEffect(() => {
    const load = async () => {
      try {
        const [ens, grp] = await Promise.all([
          api.get('/enseignants', { params: { statut: 'actif', per_page: 100 } }),
          api.get('/groupes', { params: { statut: 'actif', per_page: 100 } }),
        ]);
        setEnseignants(ens.data || []);
        setGroupes(grp.data || []);
      } catch { /* silent */ }
    };
    load();
  }, []);

  return (
    <div className="bg-white rounded-2xl border border-neutral-200 p-4">
      <div className="flex flex-wrap gap-3">
        <select
          value={filtres.enseignant_id || ''}
          onChange={e => onChange(f => ({ ...f, enseignant_id: e.target.value || undefined }))}
          className="px-4 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500"
        >
          <option value="">Tous les enseignants</option>
          {enseignants.map(e => (
            <option key={e.id} value={e.id}>{e.nom} {e.prenom}</option>
          ))}
        </select>
        <select
          value={filtres.groupe_id || ''}
          onChange={e => onChange(f => ({ ...f, groupe_id: e.target.value || undefined }))}
          className="px-4 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500"
        >
          <option value="">Tous les groupes</option>
          {groupes.map(g => (
            <option key={g.id} value={g.id}>{g.nom} — {g.niveau_scolaire}</option>
          ))}
        </select>
      </div>
    </div>
  );
}
