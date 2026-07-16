import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

export default function TransportPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/transport/dashboard')
      .then(res => setData(res.data?.data ?? null))
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"/></div>;

  if (!data) return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-bold text-text">🚌 Transport Scolaire</h1>
      <div className="text-center py-16">
        <p className="text-sm text-muted mb-4">Aucune donnée de transport disponible.</p>
        <p className="text-xs text-muted2">Configurez vos circuits de ramassage pour commencer.</p>
      </div>
    </div>
  );

  const { stats, circuits } = data;

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">🚌 Transport Scolaire</h1>
          <p className="text-gray-500 mt-1">Gestion des circuits de ramassage</p>
        </div>
        <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium">
          + Nouveau circuit
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {[
          { label: 'Circuits actifs', value: stats?.nb_circuits ?? 0, color: 'blue', icon: '🚌' },
          { label: 'Élèves transportés', value: stats?.nb_eleves_total ?? 0, color: 'green', icon: '👦' },
          { label: 'Alertes maintenance', value: stats?.alertes_maintenance ?? 0, color: stats?.alertes_maintenance > 0 ? 'red' : 'gray', icon: '⚠️' },
        ].map((k, i) => (
          <div key={i} className="bg-white rounded-xl shadow-sm border p-5">
            <div className="flex items-center gap-3">
              <span className="text-3xl">{k.icon}</span>
              <div>
                <div className={`text-2xl font-bold text-${k.color}-600`}>{k.value}</div>
                <div className="text-sm text-gray-500">{k.label}</div>
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-6 py-4 border-b">
          <h2 className="font-semibold text-gray-900">Circuits de ramassage</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50">
              <tr>
                {['Circuit', 'Chauffeur', 'Véhicule', 'Élèves', 'Remplissage', 'Statut', 'Actions'].map(h => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {(circuits || []).map(c => (
                <tr key={c.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-900">{c.nom}</td>
                  <td className="px-4 py-3 text-gray-600">
                    {c.chauffeur ? `${c.chauffeur.nom} ${c.chauffeur.prenom}` : <span className="text-red-500">Non assigné</span>}
                  </td>
                  <td className="px-4 py-3 text-gray-600 font-mono text-xs">{c.vehicule_immat}</td>
                  <td className="px-4 py-3">
                    <span className="font-medium">{c.nb_eleves}</span>
                    <span className="text-gray-400">/{c.capacite}</span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <div className="flex-1 bg-gray-200 rounded-full h-2">
                        <div
                          className={`h-2 rounded-full ${c.taux_remplissage > 80 ? 'bg-red-500' : c.taux_remplissage > 60 ? 'bg-orange-400' : 'bg-green-500'}`}
                          style={{ width: `${c.taux_remplissage}%` }}
                        />
                      </div>
                      <span className="text-xs text-gray-500">{c.taux_remplissage}%</span>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${c.actif ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>
                      {c.actif ? 'Actif' : 'Inactif'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <button className="text-blue-600 hover:text-blue-800 text-xs font-medium">Voir</button>
                      <button className="text-gray-500 hover:text-gray-700 text-xs font-medium">Modifier</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
