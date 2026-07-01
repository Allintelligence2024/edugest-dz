import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = {
  stats: { tickets_ouverts: 5, tickets_urgents: 2, resolus_ce_mois: 8, cout_mois: 45000, locaux_critique: 1, preventifs_retard: 1, preventifs_30j: 3 },
  derniers_tickets: [
    { id: '1', titre: 'Fuite robinet WC Nord', priorite: 'urgente', statut: 'en_cours', local: { nom: 'Sanitaires Nord' }, prestataire: { nom: 'Plomberie Alger' } },
    { id: '2', titre: 'Climatisation classe 102 HS', priorite: 'haute', statut: 'signale', local: { nom: 'Salle 102' }, prestataire: null },
    { id: '3', titre: 'Vitre cassée couloir', priorite: 'normale', statut: 'signale', local: { nom: 'Couloir 1er étage' }, prestataire: null },
    { id: '4', titre: 'Tableau blanc à remplacer', priorite: 'basse', statut: 'en_attente', local: { nom: 'Salle 201' }, prestataire: null },
  ],
};

const PRIORITE_COLORS = { urgente: 'bg-red-100 text-red-800', haute: 'bg-orange-100 text-orange-800', normale: 'bg-blue-100 text-blue-800', basse: 'bg-gray-100 text-gray-600' };
const STATUT_COLORS = { signale: 'bg-yellow-100 text-yellow-800', en_cours: 'bg-blue-100 text-blue-800', en_attente: 'bg-gray-100 text-gray-600', resolu: 'bg-green-100 text-green-800' };

export default function EntretienPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/entretien/dashboard')
      .then(res => setData(res.data?.data ?? DEMO_DATA))
      .catch(() => setData(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-600"/></div>;

  const { stats, derniers_tickets } = data;

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">🔧 Entretien Bâtiment</h1>
          <p className="text-gray-500 mt-1">Tickets d&apos;intervention · Préventif · Prestataires</p>
        </div>
        <button className="bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700 font-medium">
          + Signaler intervention
        </button>
      </div>

      {(stats?.tickets_urgents ?? 0) > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
          <span className="text-2xl">🚨</span>
          <div className="flex-1">
            <p className="font-medium text-red-800">{stats.tickets_urgents} ticket(s) urgents en attente d&apos;intervention</p>
            {(stats?.locaux_critique ?? 0) > 0 && <p className="text-sm text-red-600">{stats.locaux_critique} local(aux) en état critique</p>}
          </div>
        </div>
      )}

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Tickets ouverts', value: stats?.tickets_ouverts ?? 0, icon: '🎫', color: 'orange' },
          { label: 'Résolus ce mois', value: stats?.resolus_ce_mois ?? 0, icon: '✅', color: 'green' },
          { label: 'Coût du mois', value: `${Number(stats?.cout_mois ?? 0).toLocaleString()} DA`, icon: '💰', color: 'blue' },
          { label: 'Préventifs < 30j', value: stats?.preventifs_30j ?? 0, icon: '📅', color: (stats?.preventifs_retard ?? 0) > 0 ? 'red' : 'gray' },
        ].map((k, i) => (
          <div key={i} className="bg-white rounded-xl shadow-sm border p-4">
            <div className="text-2xl mb-1">{k.icon}</div>
            <div className={`text-xl font-bold text-${k.color}-600`}>{k.value}</div>
            <div className="text-xs text-gray-500">{k.label}</div>
          </div>
        ))}
      </div>

      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-6 py-4 border-b flex justify-between items-center">
          <h3 className="font-semibold text-gray-900">Tickets ouverts</h3>
          <button className="text-teal-600 text-sm font-medium hover:text-teal-800">Voir tous</button>
        </div>
        <div className="divide-y">
          {(derniers_tickets || []).map((t, i) => (
            <div key={i} className="px-6 py-4 flex items-start justify-between hover:bg-gray-50">
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${PRIORITE_COLORS[t.priorite]}`}>{t.priorite}</span>
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUT_COLORS[t.statut]}`}>{t.statut?.replace('_', ' ')}</span>
                </div>
                <p className="font-medium text-gray-900">{t.titre}</p>
                <p className="text-sm text-gray-500 mt-0.5">
                  📍 {t.local?.nom ?? '—'}
                  {t.prestataire && ` · 👷 ${t.prestataire.nom}`}
                </p>
              </div>
              <div className="flex gap-2 ml-4">
                <button className="text-teal-600 hover:text-teal-800 text-xs font-medium">Voir</button>
                {t.statut !== 'resolu' && <button className="text-gray-500 hover:text-gray-700 text-xs font-medium">Assigner</button>}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
