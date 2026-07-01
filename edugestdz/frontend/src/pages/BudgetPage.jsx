import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = {
  periode: { mois: new Date().getMonth() + 1, annee: new Date().getFullYear() },
  recettes: 485000,
  depenses: 312000,
  resultat_net: 173000,
  impayes: 62000,
  evolution: [
    { label: 'Fév', recettes: 420000, depenses: 290000, resultat: 130000 },
    { label: 'Mar', recettes: 455000, depenses: 305000, resultat: 150000 },
    { label: 'Avr', recettes: 390000, depenses: 280000, resultat: 110000 },
    { label: 'Mai', recettes: 510000, depenses: 320000, resultat: 190000 },
    { label: 'Jun', recettes: 480000, depenses: 310000, resultat: 170000 },
    { label: 'Jul', recettes: 485000, depenses: 312000, resultat: 173000 },
  ],
  par_categorie: [
    { categorie: 'salaires_enseignants', libelle: 'Salaires enseignants', total: 180000, prevu: 200000 },
    { categorie: 'loyer', libelle: 'Loyer', total: 80000, prevu: 80000 },
    { categorie: 'salaires_personnel', libelle: 'Salaires personnel', total: 32000, prevu: 35000 },
    { categorie: 'electricite_gaz', libelle: 'Électricité & Gaz', total: 12000, prevu: 15000 },
    { categorie: 'fournitures_pedagogiques', libelle: 'Fournitures péda.', total: 8000, prevu: 10000 },
  ],
};

export default function BudgetPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/budget/dashboard')
      .then(res => setData(res.data?.data ?? DEMO_DATA))
      .catch(() => setData(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"/></div>;

  const fmt = (n) => Number(n ?? 0).toLocaleString('fr-DZ');

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">📊 Budget Annuel</h1>
          <p className="text-gray-500 mt-1">Recettes · Dépenses · Prévisionnel</p>
        </div>
        <div className="flex gap-2">
          <button className="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm">📄 Bilan annuel</button>
          <button className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-medium">+ Saisir dépense</button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        {[
          { label: 'Recettes du mois', value: `${fmt(data?.recettes)} DA`, icon: '📈', bg: 'bg-green-50', text: 'text-green-700', border: 'border-green-200' },
          { label: 'Dépenses du mois', value: `${fmt(data?.depenses)} DA`, icon: '📉', bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200' },
          { label: 'Résultat net', value: `${fmt(data?.resultat_net)} DA`, icon: '💰', bg: (data?.resultat_net ?? 0) >= 0 ? 'bg-blue-50' : 'bg-red-50', text: (data?.resultat_net ?? 0) >= 0 ? 'text-blue-700' : 'text-red-700', border: 'border-blue-200' },
          { label: 'Impayés', value: `${fmt(data?.impayes)} DA`, icon: '⚠️', bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200' },
        ].map((k, i) => (
          <div key={i} className={`${k.bg} border ${k.border} rounded-xl p-5`}>
            <div className="text-2xl mb-2">{k.icon}</div>
            <div className={`text-lg font-bold ${k.text}`}>{k.value}</div>
            <div className="text-xs text-gray-500 mt-1">{k.label}</div>
          </div>
        ))}
      </div>

      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-6 py-4 border-b"><h3 className="font-semibold text-gray-900">Évolution 6 derniers mois</h3></div>
        <div className="p-6">
          <div className="flex items-end gap-3 h-32">
            {(data?.evolution || []).map((m, i) => {
              const maxVal = Math.max(...(data.evolution || []).map(e => e.recettes));
              const heightRec = maxVal > 0 ? (m.recettes / maxVal) * 100 : 0;
              const heightDep = maxVal > 0 ? (m.depenses / maxVal) * 100 : 0;
              return (
                <div key={i} className="flex-1 flex flex-col items-center gap-1">
                  <div className="w-full flex gap-1 items-end" style={{ height: '96px' }}>
                    <div className="flex-1 bg-green-400 rounded-t" style={{ height: `${heightRec}%` }} title={`Recettes: ${fmt(m.recettes)} DA`}/>
                    <div className="flex-1 bg-red-400 rounded-t" style={{ height: `${heightDep}%` }} title={`Dépenses: ${fmt(m.depenses)} DA`}/>
                  </div>
                  <span className="text-xs text-gray-500">{m.label}</span>
                </div>
              );
            })}
          </div>
          <div className="flex gap-4 mt-3">
            <div className="flex items-center gap-1"><div className="w-3 h-3 bg-green-400 rounded"/><span className="text-xs text-gray-500">Recettes</span></div>
            <div className="flex items-center gap-1"><div className="w-3 h-3 bg-red-400 rounded"/><span className="text-xs text-gray-500">Dépenses</span></div>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-6 py-4 border-b"><h3 className="font-semibold text-gray-900">Dépenses par catégorie — prévisionnel vs réalisé</h3></div>
        <div className="divide-y">
          {(data?.par_categorie || []).map((c, i) => {
            const pct = c.prevu > 0 ? Math.round((c.total / c.prevu) * 100) : 0;
            return (
              <div key={i} className="px-6 py-3">
                <div className="flex justify-between items-center mb-1">
                  <span className="text-sm font-medium text-gray-700">{c.libelle}</span>
                  <div className="text-sm text-right">
                    <span className={pct > 100 ? 'text-red-600 font-bold' : 'text-gray-700'}>{fmt(c.total)} DA</span>
                    <span className="text-gray-400 ml-1">/ {fmt(c.prevu)} DA</span>
                  </div>
                </div>
                <div className="bg-gray-100 rounded-full h-1.5">
                  <div className={`h-1.5 rounded-full ${pct > 100 ? 'bg-red-500' : pct > 80 ? 'bg-orange-400' : 'bg-green-500'}`} style={{ width: `${Math.min(pct, 100)}%` }}/>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
