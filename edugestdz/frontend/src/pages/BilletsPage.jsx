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

  useEffect(() => {
    api.get('/billets')
      .then(res => setBillets(res.data?.data || DEMO_DATA))
      .catch(() => setBillets(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  const creerBillet = async (e) => {
    e.preventDefault();
    try {
      await api.post('/billets', { ...form, date_billet: new Date().toISOString().split('T')[0] });
      setShowForm(false);
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
              <button type="submit" className="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900">Créer &amp; Imprimer</button>
            </div>
          </form>
        </div>
      )}

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
