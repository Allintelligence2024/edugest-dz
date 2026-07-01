import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = {
  date: new Date().toLocaleDateString('fr-DZ'),
  stats: { total: 12, actifs: 10, presents: 8, absents: 2, par_poste: { femme_menage: 3, surveillant: 4, chauffeur: 2, secretaire: 2, autre: 1 } },
  par_poste: [
    { agent: { nom: 'KACI', prenom: 'Fatima', poste: 'femme_menage' }, statut: 'present', heure_arrivee: '07:30', pointe: true },
    { agent: { nom: 'RAIS', prenom: 'Ahmed', poste: 'surveillant' }, statut: 'present', heure_arrivee: '08:00', pointe: true },
    { agent: { nom: 'BELAID', prenom: 'Omar', poste: 'chauffeur' }, statut: 'absent', heure_arrivee: null, pointe: false },
    { agent: { nom: 'HAMDI', prenom: 'Samira', poste: 'secretaire' }, statut: 'present', heure_arrivee: '08:15', pointe: true },
  ],
};

const POSTE_LABELS = { femme_menage: 'Femme de ménage', surveillant: 'Surveillant(e)', chauffeur: 'Chauffeur', secretaire: 'Secrétaire', technicien: 'Technicien', agent_securite: 'Agent sécurité', autre: 'Autre' };

export default function PersonnelAdminPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/personnel/tableau-bord')
      .then(res => setData(res.data?.data ?? DEMO_DATA))
      .catch(() => setData(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"/></div>;

  const { stats, par_poste } = data;
  const agents = Array.isArray(par_poste) ? par_poste : Object.values(par_poste || {}).flat();

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">👷 Personnel Non-Enseignant</h1>
          <p className="text-gray-500 mt-1">Présences du {data?.date}</p>
        </div>
        <div className="flex gap-2">
          <button className="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm">💰 Calculer paies</button>
          <button className="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 font-medium">+ Nouvel agent</button>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Total agents', value: stats?.total ?? 0, color: 'purple', icon: '👷' },
          { label: 'Présents', value: stats?.presents ?? 0, color: 'green', icon: '✅' },
          { label: 'Absents', value: stats?.absents ?? 0, color: 'red', icon: '❌' },
          { label: 'Taux présence', value: stats?.total ? `${Math.round(((stats?.presents ?? 0) / stats.total) * 100)}%` : '—', color: 'blue', icon: '📊' },
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
          <h3 className="font-semibold text-gray-900">Pointage du jour</h3>
          <span className="text-sm text-gray-500">{agents.length} agent(s)</span>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50">
              <tr>
                {['Agent', 'Poste', 'Statut', 'Arrivée', 'Actions'].map(h => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {agents.map((a, i) => {
                const agent = a.agent || a;
                return (
                  <tr key={i} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-medium text-gray-900">{agent.prenom} {agent.nom}</td>
                    <td className="px-4 py-3 text-gray-600">{POSTE_LABELS[agent.poste] ?? agent.poste}</td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${a.statut === 'present' ? 'bg-green-100 text-green-800' : a.statut === 'retard' ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'}`}>
                        {a.statut === 'present' ? '✅ Présent' : a.statut === 'retard' ? '⏰ Retard' : '❌ Absent'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-600">{a.heure_arrivee ? a.heure_arrivee.slice(0, 5) : '—'}</td>
                    <td className="px-4 py-3">
                      {!a.pointe && (
                        <button className="text-blue-600 hover:text-blue-800 text-xs font-medium">Pointer arrivée</button>
                      )}
                      {a.pointe && !a.heure_depart && (
                        <button className="text-gray-500 hover:text-gray-700 text-xs font-medium">Pointer départ</button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
