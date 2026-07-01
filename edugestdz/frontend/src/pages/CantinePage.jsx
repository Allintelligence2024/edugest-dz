import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = {
  date: new Date().toLocaleDateString('fr-DZ', { weekday: 'long', day: 'numeric', month: 'long' }),
  menu_du_jour: { plat_principal: 'Couscous au poulet', accompagnement: 'Légumes vapeur', dessert: 'Fruit de saison', prix_unitaire: 250 },
  inscrits_actifs: 89,
  presents_aujourdhui: 74,
  taux_presence: 83,
  alertes_stock: 2,
  ca_mois: 178500,
  menus_semaine: [
    { date_repas: 'Lun 01/07', plat_principal: 'Couscous au poulet' },
    { date_repas: 'Mar 02/07', plat_principal: 'Lentilles & Viande' },
    { date_repas: 'Mer 03/07', plat_principal: 'Sardines grillées' },
    { date_repas: 'Jeu 04/07', plat_principal: 'Tajine de bœuf' },
    { date_repas: 'Dim 07/07', plat_principal: 'Poulet rôti' },
  ],
};

export default function CantinePage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('dashboard');

  useEffect(() => {
    api.get('/cantine/dashboard')
      .then(res => setData(res.data?.data ?? DEMO_DATA))
      .catch(() => setData(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500"/></div>;

  const tabs = [
    { id: 'dashboard', label: 'Vue d\'ensemble' },
    { id: 'menus', label: 'Menus' },
    { id: 'inscriptions', label: 'Inscrits' },
    { id: 'stock', label: 'Stock cuisine' },
  ];

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">🍽️ Cantine / Restauration</h1>
          <p className="text-gray-500 mt-1">{data?.date}</p>
        </div>
        <button className="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 font-medium">
          + Ajouter menu
        </button>
      </div>

      <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
        {tabs.map(t => (
          <button key={t.id} onClick={() => setActiveTab(t.id)}
            className={`px-4 py-2 rounded-md text-sm font-medium transition ${activeTab === t.id ? 'bg-white shadow text-orange-600' : 'text-gray-600 hover:text-gray-900'}`}>
            {t.label}
          </button>
        ))}
      </div>

      {activeTab === 'dashboard' && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {[
              { label: 'Inscrits', value: data?.inscrits_actifs ?? 0, icon: '👦', color: 'blue' },
              { label: 'Présents aujourd\'hui', value: data?.presents_aujourdhui ?? 0, icon: '✅', color: 'green' },
              { label: 'Taux présence', value: `${data?.taux_presence ?? 0}%`, icon: '📊', color: 'purple' },
              { label: 'CA ce mois', value: `${Number(data?.ca_mois ?? 0).toLocaleString()} DA`, icon: '💰', color: 'orange' },
            ].map((k, i) => (
              <div key={i} className="bg-white rounded-xl shadow-sm border p-4">
                <div className="text-2xl mb-1">{k.icon}</div>
                <div className={`text-xl font-bold text-${k.color}-600`}>{k.value}</div>
                <div className="text-xs text-gray-500">{k.label}</div>
              </div>
            ))}
          </div>

          {data?.menu_du_jour && (
            <div className="bg-white rounded-xl shadow-sm border p-5">
              <h3 className="font-semibold text-gray-900 mb-3">🍽️ Menu du jour</h3>
              <div className="flex gap-6 flex-wrap">
                <div><span className="text-xs text-gray-500">Plat principal</span><p className="font-medium">{data.menu_du_jour.plat_principal}</p></div>
                {data.menu_du_jour.accompagnement && <div><span className="text-xs text-gray-500">Accompagnement</span><p className="font-medium">{data.menu_du_jour.accompagnement}</p></div>}
                {data.menu_du_jour.dessert && <div><span className="text-xs text-gray-500">Dessert</span><p className="font-medium">{data.menu_du_jour.dessert}</p></div>}
                <div><span className="text-xs text-gray-500">Prix</span><p className="font-medium text-orange-600">{data.menu_du_jour.prix_unitaire} DA</p></div>
              </div>
            </div>
          )}

          <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div className="px-6 py-4 border-b"><h3 className="font-semibold text-gray-900">Menu de la semaine</h3></div>
            <div className="divide-y">
              {(data?.menus_semaine || []).map((m, i) => (
                <div key={i} className="px-6 py-3 flex justify-between items-center hover:bg-gray-50">
                  <span className="text-sm font-medium text-gray-700">{m.date_repas || m.label}</span>
                  <span className="text-sm text-gray-600">{m.plat_principal}</span>
                </div>
              ))}
            </div>
          </div>

          {(data?.alertes_stock ?? 0) > 0 && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
              <span className="text-2xl">⚠️</span>
              <div>
                <p className="font-medium text-red-800">{data.alertes_stock} article(s) en alerte de stock</p>
                <p className="text-sm text-red-600">Vérifiez le stock cuisine avant le prochain repas</p>
              </div>
              <button className="ml-auto text-red-600 text-sm font-medium hover:text-red-800">Voir le stock</button>
            </div>
          )}
        </div>
      )}

      {activeTab === 'menus' && (
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <p className="text-gray-500 text-center py-8">Gestion des menus — connectez l&apos;API <code className="bg-gray-100 px-2 py-1 rounded">/api/v1/cantine/menus</code></p>
        </div>
      )}
      {activeTab === 'inscriptions' && (
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <p className="text-gray-500 text-center py-8">Liste des inscrits — connectez l&apos;API <code className="bg-gray-100 px-2 py-1 rounded">/api/v1/cantine/inscriptions</code></p>
        </div>
      )}
      {activeTab === 'stock' && (
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <p className="text-gray-500 text-center py-8">Stock cuisine — connectez l&apos;API <code className="bg-gray-100 px-2 py-1 rounded">/api/v1/cantine/stock</code></p>
        </div>
      )}
    </div>
  );
}
