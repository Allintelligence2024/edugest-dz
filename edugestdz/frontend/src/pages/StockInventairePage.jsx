import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = {
  stats: { total_articles: 234, articles_en_alerte: 8, valeur_totale_da: 4250000, prets_en_retard: 2, bons_pendants: 1 },
  par_categorie: [
    { categorie: 'mobilier', nb: 120, qte: 850 },
    { categorie: 'fourniture_bureau', nb: 45, qte: 1200 },
    { categorie: 'equipement_pedagogique', nb: 35, qte: 68 },
    { categorie: 'equipement_informatique', nb: 20, qte: 24 },
    { categorie: 'materiel_entretien', nb: 14, qte: 340 },
  ],
  derniers_mouvements: [
    { article: { nom: 'Chaises élèves' }, type: 'entree', quantite: 20, date_mouvement: '2026-07-01' },
    { article: { nom: 'Marqueurs effaçables' }, type: 'sortie', quantite: 10, date_mouvement: '2026-06-30' },
    { article: { nom: 'Papier A4 (rame)' }, type: 'sortie', quantite: 5, date_mouvement: '2026-06-30' },
  ],
};

const CAT_LABELS = {
  mobilier: 'Mobilier', fourniture_bureau: 'Fournitures bureau',
  equipement_pedagogique: 'Équip. pédagogique', equipement_informatique: 'Informatique',
  materiel_entretien: 'Entretien',
};

export default function StockInventairePage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/stock/dashboard')
      .then(res => setData(res.data?.data ?? DEMO_DATA))
      .catch(() => setData(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"/></div>;

  const { stats, par_categorie, derniers_mouvements } = data;

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">📦 Stock & Inventaire</h1>
          <p className="text-gray-500 mt-1">Mobilier, équipements et fournitures</p>
        </div>
        <div className="flex gap-2">
          <button className="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm font-medium">📄 Rapport annuel</button>
          <button className="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 font-medium">+ Ajouter article</button>
        </div>
      </div>

      {(stats?.articles_en_alerte ?? 0) > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-3">
          <span className="text-xl">⚠️</span>
          <div className="flex-1">
            <p className="font-medium text-amber-800">{stats.articles_en_alerte} article(s) sous le seuil minimum</p>
            {(stats?.prets_en_retard ?? 0) > 0 && <p className="text-sm text-amber-600">{stats.prets_en_retard} prêt(s) en retard</p>}
          </div>
          <button className="text-amber-700 text-sm font-medium">Voir les alertes</button>
        </div>
      )}

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Articles recensés', value: stats?.total_articles ?? 0, icon: '📦', color: 'indigo' },
          { label: 'Valeur totale', value: `${Number(stats?.valeur_totale_da ?? 0).toLocaleString()} DA`, icon: '💰', color: 'green' },
          { label: 'En alerte stock', value: stats?.articles_en_alerte ?? 0, icon: '🔴', color: 'red' },
          { label: 'Prêts en cours', value: stats?.prets_en_retard ?? 0, icon: '🔄', color: 'orange' },
        ].map((k, i) => (
          <div key={i} className="bg-white rounded-xl shadow-sm border p-4">
            <div className="text-2xl mb-1">{k.icon}</div>
            <div className={`text-xl font-bold text-${k.color}-600`}>{k.value}</div>
            <div className="text-xs text-gray-500">{k.label}</div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div className="px-6 py-4 border-b"><h3 className="font-semibold text-gray-900">Répartition par catégorie</h3></div>
          <div className="divide-y">
            {(par_categorie || []).map((c, i) => (
              <div key={i} className="px-6 py-3 flex justify-between items-center">
                <span className="text-sm font-medium text-gray-700">{CAT_LABELS[c.categorie] ?? c.categorie}</span>
                <div className="flex gap-4 text-sm text-gray-500">
                  <span>{c.nb} articles</span>
                  <span>{c.qte} unités</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div className="px-6 py-4 border-b"><h3 className="font-semibold text-gray-900">Derniers mouvements</h3></div>
          <div className="divide-y">
            {(derniers_mouvements || []).map((m, i) => (
              <div key={i} className="px-6 py-3 flex justify-between items-center">
                <div>
                  <p className="text-sm font-medium text-gray-800">{m.article?.nom}</p>
                  <p className="text-xs text-gray-500">{m.date_mouvement}</p>
                </div>
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${m.type === 'entree' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                  {m.type === 'entree' ? `+${m.quantite}` : `-${m.quantite}`}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
