# 🤖 MISSION DEEPSEEK — Frontend React : Pages des nouveaux modules
## EduGest DZ · Branche : develop · 1er Juillet 2026
## Stack : React 18 + Vite + TailwindCSS

---

## CONTEXTE EXACT

### Ce qui EXISTE déjà dans le frontend (ne pas recréer)
```
frontend/src/
  pages/
    DashboardPage.jsx       ✅ complet (mode démo + API)
    ElevesListPage.jsx      ✅ complet
    EnseignantsListPage.jsx ✅ complet
    FacturesPage.jsx        ✅ complet
    PlanningPage.jsx        ✅ complet
    PresencesPage.jsx       ✅ complet
    NotesPage.jsx           ✅ complet
    BulletinsPage.jsx       ✅ complet
    GroupesPage.jsx         ✅ complet
    PaiesPage.jsx           ✅ complet
    MessagesPage.jsx        ✅ complet
    CampagnesPage.jsx       ✅ complet
    MarketplaceSearchPage.jsx ✅ complet
    ... (autres)
  components/
    Sidebar.jsx             ✅ avec navigation existante
    Header.jsx              ✅
    dashboard/StatCard.jsx  ✅
  api/axiosInstance.js      ✅
  context/AuthContext.jsx   ✅
  App.jsx                   ✅ avec routes existantes
```

### Ce qui MANQUE — pages pour les nouveaux modules
```
pages/
  TransportPage.jsx         ← M09 Transport scolaire
  CantinePage.jsx           ← M10 Cantine/Restauration
  StockInventairePage.jsx   ← M11 Stock & Inventaire
  PersonnelAdminPage.jsx    ← M12 Personnel non-enseignant
  BudgetPage.jsx            ← M13 Budget Annuel
  EntretienPage.jsx         ← M14 Entretien Bâtiment
  AbsencesPage.jsx          ← Absences journalières (P1)
  BilletsPage.jsx           ← Billets entrée/sortie
```

### Style de référence — DashboardPage.jsx
- `useEffect` + `useState` pour les données API
- Mode démo avec `DEMO_DATA` si API inaccessible
- `api.get()` via `@api/axiosInstance`
- Classes Tailwind pour le style
- Composants réutilisables : `StatCard`

### Pattern à respecter pour chaque page
```jsx
import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = { /* données fictives réalistes */ };

export default function XxxPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/xxx')
      .then(res => setData(res.data?.data ?? DEMO_DATA))
      .catch(() => setData(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="...">Chargement...</div>;
  return ( /* JSX */ );
}
```

---

## ÉTAPE 1 — TransportPage.jsx

**Créer :** `edugestdz/frontend/src/pages/TransportPage.jsx`

```jsx
import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = {
  stats: { nb_circuits: 4, nb_eleves_total: 67, alertes_maintenance: 1 },
  circuits: [
    { id: '1', nom: 'Circuit Nord', nb_eleves: 18, capacite: 25, taux_remplissage: 72, chauffeur: { nom: 'BELKACEM', prenom: 'Hocine' }, vehicule_immat: '16-ABC-12', actif: true },
    { id: '2', nom: 'Circuit Sud', nb_eleves: 22, capacite: 30, taux_remplissage: 73, chauffeur: { nom: 'MANSOURI', prenom: 'Salim' }, vehicule_immat: '16-DEF-34', actif: true },
    { id: '3', nom: 'Circuit Est',  nb_eleves: 15, capacite: 20, taux_remplissage: 75, chauffeur: null, vehicule_immat: '16-GHI-56', actif: true },
    { id: '4', nom: 'Circuit Ouest',nb_eleves: 12, capacite: 20, taux_remplissage: 60, chauffeur: { nom: 'DJEBLI', prenom: 'Karim' }, vehicule_immat: '16-JKL-78', actif: false },
  ],
};

export default function TransportPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('circuits');

  useEffect(() => {
    api.get('/transport/dashboard')
      .then(res => setData(res.data?.data ?? DEMO_DATA))
      .catch(() => setData(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"/></div>;

  const { stats, circuits } = data;

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">🚌 Transport Scolaire</h1>
          <p className="text-gray-500 mt-1">Gestion des circuits de ramassage</p>
        </div>
        <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium">
          + Nouveau circuit
        </button>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {[
          { label: 'Circuits actifs', value: stats?.nb_circuits ?? 0, color: 'blue', icon: '🚌' },
          { label: 'Élèves transportés', value: stats?.nb_eleves_total ?? 0, color: 'green', icon: '👦' },
          { label: 'Alertes maintenance', value: stats?.alertes_maintenance ?? 0, color: stats?.alertes_maintenance > 0 ? 'red' : 'gray', icon: '⚠️' },
        ].map((k, i) => (
          <div key={i} className={`bg-white rounded-xl shadow-sm border p-5`}>
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

      {/* Circuits */}
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
```

---

## ÉTAPE 2 — CantinePage.jsx

**Créer :** `edugestdz/frontend/src/pages/CantinePage.jsx`

```jsx
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
    { id: 'dashboard', label: '📊 Vue d\'ensemble' },
    { id: 'menus', label: '🍽️ Menus' },
    { id: 'inscriptions', label: '👦 Inscrits' },
    { id: 'stock', label: '📦 Stock cuisine' },
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

      {/* Tabs */}
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
          {/* KPIs */}
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

          {/* Menu du jour */}
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

          {/* Menus semaine */}
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

          {/* Alertes stock */}
          {(data?.alertes_stock ?? 0) > 0 && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
              <span className="text-2xl">⚠️</span>
              <div>
                <p className="font-medium text-red-800">{data.alertes_stock} article(s) en alerte de stock</p>
                <p className="text-sm text-red-600">Vérifiez le stock cuisine avant le prochain repas</p>
              </div>
              <button className="ml-auto text-red-600 text-sm font-medium hover:text-red-800">Voir le stock →</button>
            </div>
          )}
        </div>
      )}

      {activeTab === 'menus' && (
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <p className="text-gray-500 text-center py-8">Gestion des menus — connectez l'API <code className="bg-gray-100 px-2 py-1 rounded">/api/v1/cantine/menus</code></p>
        </div>
      )}
      {activeTab === 'inscriptions' && (
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <p className="text-gray-500 text-center py-8">Liste des inscrits — connectez l'API <code className="bg-gray-100 px-2 py-1 rounded">/api/v1/cantine/inscriptions</code></p>
        </div>
      )}
      {activeTab === 'stock' && (
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <p className="text-gray-500 text-center py-8">Stock cuisine — connectez l'API <code className="bg-gray-100 px-2 py-1 rounded">/api/v1/cantine/stock</code></p>
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 3 — StockInventairePage.jsx

**Créer :** `edugestdz/frontend/src/pages/StockInventairePage.jsx`

```jsx
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

      {/* Alertes */}
      {(stats?.articles_en_alerte ?? 0) > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-3">
          <span className="text-xl">⚠️</span>
          <div className="flex-1">
            <p className="font-medium text-amber-800">{stats.articles_en_alerte} article(s) sous le seuil minimum</p>
            {(stats?.prets_en_retard ?? 0) > 0 && <p className="text-sm text-amber-600">{stats.prets_en_retard} prêt(s) en retard</p>}
          </div>
          <button className="text-amber-700 text-sm font-medium">Voir les alertes →</button>
        </div>
      )}

      {/* KPIs */}
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
        {/* Par catégorie */}
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

        {/* Derniers mouvements */}
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
```

---

## ÉTAPE 4 — PersonnelAdminPage.jsx

**Créer :** `edugestdz/frontend/src/pages/PersonnelAdminPage.jsx`

```jsx
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

      {/* Stats */}
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

      {/* Tableau pointage */}
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
```

---

## ÉTAPE 5 — BudgetPage.jsx

**Créer :** `edugestdz/frontend/src/pages/BudgetPage.jsx`

```jsx
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
  const [activeTab, setActiveTab] = useState('dashboard');

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

      {/* KPIs principaux */}
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

      {/* Évolution 6 mois */}
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

      {/* Dépenses par catégorie */}
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
```

---

## ÉTAPE 6 — EntretienPage.jsx

**Créer :** `edugestdz/frontend/src/pages/EntretienPage.jsx`

```jsx
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
  const [showModal, setShowModal] = useState(false);

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
          <p className="text-gray-500 mt-1">Tickets d'intervention · Préventif · Prestataires</p>
        </div>
        <button onClick={() => setShowModal(true)} className="bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700 font-medium">
          + Signaler intervention
        </button>
      </div>

      {/* Alertes urgentes */}
      {(stats?.tickets_urgents ?? 0) > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
          <span className="text-2xl">🚨</span>
          <div className="flex-1">
            <p className="font-medium text-red-800">{stats.tickets_urgents} ticket(s) urgents en attente d'intervention</p>
            {(stats?.locaux_critique ?? 0) > 0 && <p className="text-sm text-red-600">{stats.locaux_critique} local(aux) en état critique</p>}
          </div>
        </div>
      )}

      {/* KPIs */}
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

      {/* Liste des tickets */}
      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-6 py-4 border-b flex justify-between items-center">
          <h3 className="font-semibold text-gray-900">Tickets ouverts</h3>
          <button className="text-teal-600 text-sm font-medium hover:text-teal-800">Voir tous →</button>
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
```

---

## ÉTAPE 7 — AbsencesPage.jsx

**Créer :** `edugestdz/frontend/src/pages/AbsencesPage.jsx`

```jsx
import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = {
  date: new Date().toLocaleDateString('fr-DZ', { weekday: 'long', day: 'numeric', month: 'long' }),
  stats: { total: 24, absents: 5, presents: 16, retards: 3 },
  data: [
    { id: '1', statut: 'absent', eleve: { nom: 'BENALI', prenom: 'Amine', niveau_scolaire: '3AS' }, sms_parent_envoye: true },
    { id: '2', statut: 'absent', eleve: { nom: 'MEKKI', prenom: 'Sara', niveau_scolaire: '2AS' }, sms_parent_envoye: false },
    { id: '3', statut: 'retard', eleve: { nom: 'REZGUI', prenom: 'Karim', niveau_scolaire: '1AS' }, heure_arrivee: '08:47', sms_parent_envoye: false },
    { id: '4', statut: 'absent', eleve: { nom: 'BOUZID', prenom: 'Lina', niveau_scolaire: '4AM' }, sms_parent_envoye: true },
  ],
};

export default function AbsencesPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const today = new Date().toISOString().split('T')[0];

  useEffect(() => {
    api.get('/absences/jour', { params: { date: today } })
      .then(res => setData(res.data ?? DEMO_DATA))
      .catch(() => setData(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-red-500"/></div>;

  const absences = data?.data || [];
  const stats = data?.meta?.stats || data?.stats || {};
  const absentsEtRetards = absences.filter(a => ['absent', 'retard'].includes(a.statut));

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">✅ Absences Journalières</h1>
        <p className="text-gray-500 mt-1">{data?.date || today}</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Élèves absents', value: stats.absents ?? 0, color: 'red', icon: '❌' },
          { label: 'En retard', value: stats.retards ?? 0, color: 'orange', icon: '⏰' },
          { label: 'Présents', value: stats.presents ?? 0, color: 'green', icon: '✅' },
          { label: 'SMS envoyés', value: absences.filter(a => a.sms_parent_envoye).length, color: 'blue', icon: '📱' },
        ].map((k, i) => (
          <div key={i} className="bg-white rounded-xl shadow-sm border p-4">
            <div className="text-2xl mb-1">{k.icon}</div>
            <div className={`text-xl font-bold text-${k.color}-600`}>{k.value}</div>
            <div className="text-xs text-gray-500">{k.label}</div>
          </div>
        ))}
      </div>

      {/* Liste absents */}
      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-6 py-4 border-b">
          <h3 className="font-semibold text-gray-900">Absents & Retards du jour ({absentsEtRetards.length})</h3>
        </div>
        {absentsEtRetards.length === 0 ? (
          <div className="p-12 text-center">
            <div className="text-4xl mb-3">🎉</div>
            <p className="text-gray-500 font-medium">Tous les élèves sont présents !</p>
          </div>
        ) : (
          <div className="divide-y">
            {absentsEtRetards.map((a, i) => (
              <div key={i} className="px-6 py-4 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold text-gray-600">
                    {a.eleve?.prenom?.[0]}{a.eleve?.nom?.[0]}
                  </div>
                  <div>
                    <p className="font-medium text-gray-900">{a.eleve?.prenom} {a.eleve?.nom}</p>
                    <p className="text-xs text-gray-500">{a.eleve?.niveau_scolaire}
                      {a.statut === 'retard' && a.heure_arrivee && ` · Arrivé à ${a.heure_arrivee.slice(0, 5)}`}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  {a.sms_parent_envoye ? (
                    <span className="text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full">📱 SMS envoyé</span>
                  ) : (
                    <span className="text-xs text-gray-400">SMS non envoyé</span>
                  )}
                  <span className={`px-2 py-1 rounded-full text-xs font-medium ${a.statut === 'absent' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'}`}>
                    {a.statut === 'absent' ? '❌ Absent' : '⏰ Retard'}
                  </span>
                  <button className="text-blue-600 hover:text-blue-800 text-xs font-medium">Justifier</button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
```

---

## ÉTAPE 8 — BilletsPage.jsx

**Créer :** `edugestdz/frontend/src/pages/BilletsPage.jsx`

```jsx
import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = [
  { id: '1', type: 'retard', type_label: 'Billet de Retard', date_billet: '2026-07-01', heure: '08:45:00', eleve: { nom: 'BENALI', prenom: 'Amine', niveau_scolaire: '3AS' }, motif: 'Embouteillages', parent_prevenu: true },
  { id: '2', type: 'sortie_autorisee', type_label: 'Autorisation de Sortie', date_billet: '2026-07-01', heure: '14:00:00', eleve: { nom: 'MEKKI', prenom: 'Sara', niveau_scolaire: '2AS' }, motif: 'Rendez-vous médical', parent_prevenu: true },
  { id: '3', type: 'convocation', type_label: 'Convocation Parent', date_billet: '2026-06-30', heure: null, eleve: { nom: 'REZGUI', prenom: 'Karim', niveau_scolaire: '1AS' }, motif: 'Comportement en classe', parent_prevenu: false },
];

const TYPE_COLORS = {
  retard: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  sortie_autorisee: 'bg-blue-100 text-blue-800 border-blue-200',
  convocation: 'bg-red-100 text-red-800 border-red-200',
  entree_exceptionnelle: 'bg-green-100 text-green-800 border-green-200',
};

const TYPE_ICONS = { retard: '⏰', sortie_autorisee: '🚪', convocation: '📋', entree_exceptionnelle: '🔓' };

export default function BilletsPage() {
  const [billets, setBillets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ type: 'retard', motif: '', heure: '', parent_prevenu: false });
  const [eleveSearch, setEleveSearch] = useState('');
  const [selectedEleve, setSelectedEleve] = useState(null);

  useEffect(() => {
    api.get('/billets')
      .then(res => setBillets(res.data?.data || DEMO_DATA))
      .catch(() => setBillets(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  const creerBillet = async (e) => {
    e.preventDefault();
    if (!selectedEleve) return;
    try {
      await api.post('/billets', { ...form, eleve_id: selectedEleve.id, date_billet: new Date().toISOString().split('T')[0] });
      setShowForm(false);
      // Refresh
    } catch (err) {
      console.error(err);
    }
  };

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-600"/></div>;

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">🎫 Billets</h1>
          <p className="text-gray-500 mt-1">Retards · Sorties · Convocations · Entrées exceptionnelles</p>
        </div>
        <button onClick={() => setShowForm(true)} className="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 font-medium">
          + Créer un billet
        </button>
      </div>

      {/* Formulaire création */}
      {showForm && (
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <h3 className="font-semibold text-gray-900 mb-4">Nouveau billet</h3>
          <form onSubmit={creerBillet} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Type de billet</label>
              <select value={form.type} onChange={e => setForm({...form, type: e.target.value})}
                className="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="retard">⏰ Billet de retard</option>
                <option value="sortie_autorisee">🚪 Autorisation de sortie</option>
                <option value="convocation">📋 Convocation parent</option>
                <option value="entree_exceptionnelle">🔓 Entrée exceptionnelle</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Heure (optionnel)</label>
              <input type="time" value={form.heure} onChange={e => setForm({...form, heure: e.target.value})}
                className="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"/>
            </div>
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1">Motif</label>
              <textarea value={form.motif} onChange={e => setForm({...form, motif: e.target.value})}
                rows={2} className="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                placeholder="Motif du billet..."/>
            </div>
            <div className="flex items-center gap-2">
              <input type="checkbox" id="parent_prevenu" checked={form.parent_prevenu} onChange={e => setForm({...form, parent_prevenu: e.target.checked})} className="rounded"/>
              <label htmlFor="parent_prevenu" className="text-sm text-gray-700">Parent prévenu</label>
            </div>
            <div className="flex gap-2 justify-end md:col-span-2">
              <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">Annuler</button>
              <button type="submit" className="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900">Créer & Imprimer</button>
            </div>
          </form>
        </div>
      )}

      {/* Liste */}
      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-6 py-4 border-b"><h3 className="font-semibold text-gray-900">Billets récents ({billets.length})</h3></div>
        <div className="divide-y">
          {billets.map((b, i) => (
            <div key={i} className="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
              <div className="flex items-center gap-3">
                <span className="text-2xl">{TYPE_ICONS[b.type] || '🎫'}</span>
                <div>
                  <div className="flex items-center gap-2">
                    <span className={`px-2 py-0.5 rounded border text-xs font-medium ${TYPE_COLORS[b.type]}`}>{b.type_label || b.type}</span>
                    {b.parent_prevenu && <span className="text-xs text-green-600">📱 Parent prévenu</span>}
                  </div>
                  <p className="font-medium text-gray-900 mt-0.5">{b.eleve?.prenom} {b.eleve?.nom} · <span className="text-gray-500 font-normal text-sm">{b.eleve?.niveau_scolaire}</span></p>
                  {b.motif && <p className="text-xs text-gray-500 mt-0.5">{b.motif}</p>}
                </div>
              </div>
              <div className="flex items-center gap-3 text-right">
                <div>
                  <p className="text-xs text-gray-500">{b.date_billet}</p>
                  {b.heure && <p className="text-xs text-gray-400">{b.heure.slice(0, 5)}</p>}
                </div>
                <button className="text-blue-600 hover:text-blue-800 text-xs font-medium bg-blue-50 px-2 py-1 rounded">🖨️ Imprimer</button>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
```

---

## ÉTAPE 9 — Ajouter les routes dans App.jsx

**Modifier :** `edugestdz/frontend/src/App.jsx`

Ajouter les imports après les imports existants :

```jsx
import TransportPage        from '@pages/TransportPage';
import CantinePage          from '@pages/CantinePage';
import StockInventairePage  from '@pages/StockInventairePage';
import PersonnelAdminPage   from '@pages/PersonnelAdminPage';
import BudgetPage           from '@pages/BudgetPage';
import EntretienPage        from '@pages/EntretienPage';
import AbsencesPage         from '@pages/AbsencesPage';
import BilletsPage          from '@pages/BilletsPage';
```

Ajouter les routes dans le bloc `<Routes>` existant (dans `ProtectedLayout`) :

```jsx
<Route path="/transport"        element={<TransportPage />} />
<Route path="/cantine"          element={<CantinePage />} />
<Route path="/stock"            element={<StockInventairePage />} />
<Route path="/personnel-admin"  element={<PersonnelAdminPage />} />
<Route path="/budget"           element={<BudgetPage />} />
<Route path="/entretien"        element={<EntretienPage />} />
<Route path="/absences"         element={<AbsencesPage />} />
<Route path="/billets"          element={<BilletsPage />} />
```

---

## ÉTAPE 10 — Ajouter les liens dans Sidebar.jsx

**Modifier :** `edugestdz/frontend/src/components/Sidebar.jsx`

Ajouter ces entrées dans le tableau `NAV_ITEMS` :

```jsx
// Après 'Présences'
{ label: 'Absences', path: '/absences', icon: '✅' },
{ label: 'Billets', path: '/billets', icon: '🎫' },

// Nouveau groupe Gestion
{
  label: 'Gestion Centre',
  path: '/gestion',
  icon: '🏫',
  children: [
    { label: 'Transport', path: '/transport', icon: '🚌' },
    { label: 'Cantine', path: '/cantine', icon: '🍽️' },
    { label: 'Stock & Inventaire', path: '/stock', icon: '📦' },
    { label: 'Personnel admin.', path: '/personnel-admin', icon: '👷' },
    { label: 'Budget & Finances', path: '/budget', icon: '📊' },
    { label: 'Entretien Bâtiment', path: '/entretien', icon: '🔧' },
  ],
},
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser develop avec main
git checkout develop
git pull origin main

# 1. Créer les 8 pages (dans l'ordre)
create: edugestdz/frontend/src/pages/TransportPage.jsx
create: edugestdz/frontend/src/pages/CantinePage.jsx
create: edugestdz/frontend/src/pages/StockInventairePage.jsx
create: edugestdz/frontend/src/pages/PersonnelAdminPage.jsx
create: edugestdz/frontend/src/pages/BudgetPage.jsx
create: edugestdz/frontend/src/pages/EntretienPage.jsx
create: edugestdz/frontend/src/pages/AbsencesPage.jsx
create: edugestdz/frontend/src/pages/BilletsPage.jsx

# 2. Ajouter routes dans App.jsx
modify: edugestdz/frontend/src/App.jsx
# → Imports + Routes dans ProtectedLayout

# 3. Ajouter liens dans Sidebar.jsx
modify: edugestdz/frontend/src/components/Sidebar.jsx
# → Ajout dans NAV_ITEMS

# 4. Vérifier compilation
cd edugestdz/frontend
npm run build 2>&1 | tail -20
# → Doit terminer sans erreur

# 5. Si OK
cd ../..
git add edugestdz/frontend/src/
git commit -m "feat(frontend): 8 nouvelles pages — Transport + Cantine + Stock + Personnel + Budget + Entretien + Absences + Billets"
git push origin develop

# 6. PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Dossier de travail : edugestdz/frontend/
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_FRONTEND_NOUVEAUX_MODULES.md — 10 étapes dans l'ordre.

IMPORTANT :
- Ce sont des fichiers React JSX (frontend), pas PHP
- Utiliser le pattern avec DEMO_DATA pour que les pages
  fonctionnent même sans backend (mode démo)
- Vérifier compilation : cd edugestdz/frontend && npm run build
- 0 erreur de compilation exigée avant commit
- PR develop → main à la fin
```
