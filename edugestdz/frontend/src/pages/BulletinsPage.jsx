import React, { useState, useCallback } from 'react';
import { bulletinApi } from '@api/bulletin.api';
import { groupeApi } from '@api/groupe.api';
import toast from 'react-hot-toast';

const STATUT_STYLES = { brouillon: 'bg-neutral-100 text-neutral-600', publié: 'bg-green-100 text-green-700', envoyé: 'bg-blue-100 text-blue-700' };

export default function BulletinsPage() {
  const [groupes, setGroupes] = useState([]);
  const [groupeId, setGroupeId] = useState('');
  const [trimestre, setTrimestre] = useState('T1');
  const [anneeScolaire, setAnneeScolaire] = useState('2025-2026');
  const [bulletins, setBulletins] = useState([]);
  const [loading, setLoading] = useState(false);
  const [generating, setGenerating] = useState(false);

  const loadGroupes = useCallback(async () => {
    try { const r = await groupeApi.list({ per_page: 100 }); setGroupes(r.data || []); } catch { /* ignore */ }
  }, []);

  const fetchBulletins = useCallback(async () => {
    if (!groupeId) return;
    setLoading(true);
    try { const r = await bulletinApi.list({ groupe_id: groupeId, trimestre, annee_scolaire: anneeScolaire }); setBulletins(r.data || []); } catch { toast.error('Erreur'); } finally { setLoading(false); }
  }, [groupeId, trimestre, anneeScolaire]);

  const handleGenerer = async () => {
    setGenerating(true);
    try {
      const r = await bulletinApi.generer({ groupe_id: groupeId, trimestre, annee_scolaire: anneeScolaire });
      toast.success(r.message || 'Bulletins générés');
      fetchBulletins();
    } catch { toast.error('Erreur génération'); } finally { setGenerating(false); }
  };

  const handlePdf = async (id) => {
    try { const blob = await bulletinApi.pdf(id); const url = window.URL.createObjectURL(blob); window.open(url); } catch { toast.error('Erreur PDF'); }
  };

  const handleEnvoyer = async (id) => {
    try { await bulletinApi.envoyer(id); toast.success('Bulletin envoyé'); fetchBulletins(); } catch { toast.error('Erreur envoi'); }
  };

  // Load group list on mount
  const loaded = React.useRef(false);
  React.useEffect(() => { if (!loaded.current) { loaded.current = true; loadGroupes(); } }, [loadGroupes]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-neutral-800">Bulletins de notes</h1>
        <div className="flex items-center gap-3">
          <select value={groupeId} onChange={e => setGroupeId(e.target.value)} className="px-3 py-2 border rounded-xl text-sm bg-white">
            <option value="">Sélectionner un groupe</option>
            {groupes.map(g => <option key={g.id} value={g.id}>{g.nom}</option>)}
          </select>
          <select value={trimestre} onChange={e => setTrimestre(e.target.value)} className="px-3 py-2 border rounded-xl text-sm bg-white">
            {['T1','T2','T3'].map(t => <option key={t} value={t}>{t}</option>)}
          </select>
          <input value={anneeScolaire} onChange={e => setAnneeScolaire(e.target.value)} className="px-3 py-2 border rounded-xl text-sm bg-white w-28" />
          <button onClick={fetchBulletins} className="px-4 py-2 border border-neutral-300 rounded-xl text-sm hover:bg-neutral-50">Actualiser</button>
          <button onClick={handleGenerer} disabled={generating || !groupeId} className="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium disabled:opacity-50">{generating ? 'Génération...' : 'Générer les bulletins'}</button>
        </div>
      </div>

      <div className="bg-white rounded-2xl border border-neutral-100 shadow-sm overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-neutral-50">
              <th className="text-left px-4 py-3 font-semibold text-neutral-600">Élève</th>
              <th className="text-right px-4 py-3 font-semibold text-neutral-600">Moyenne</th>
              <th className="text-center px-4 py-3 font-semibold text-neutral-600">Rang</th>
              <th className="text-center px-4 py-3 font-semibold text-neutral-600">Statut</th>
              <th className="text-center px-4 py-3 font-semibold text-neutral-600">Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? <tr><td colSpan={5} className="text-center py-8 text-neutral-400">Chargement...</td></tr>
            : bulletins.length === 0 ? <tr><td colSpan={5} className="text-center py-8 text-neutral-400">Aucun bulletin</td></tr>
            : bulletins.map(b => (
              <tr key={b.id} className="border-b hover:bg-neutral-50">
                <td className="px-4 py-3 font-medium">{b.eleve?.nom} {b.eleve?.prenom}</td>
                <td className="px-4 py-3 text-right font-bold">{b.moyenne_generale}/20</td>
                <td className="px-4 py-3 text-center">{b.rang}/{b.effectif_classe}</td>
                <td className="px-4 py-3 text-center"><span className={`px-2.5 py-1 rounded-full text-xs font-medium ${STATUT_STYLES[b.statut] || ''}`}>{b.statut || 'brouillon'}</span></td>
                <td className="px-4 py-3 text-center">
                  <div className="flex items-center justify-center gap-2">
                    <button onClick={() => handlePdf(b.id)} className="px-3 py-1.5 text-xs font-medium bg-primary-50 text-primary-700 rounded-lg hover:bg-primary-100">PDF</button>
                    <button onClick={() => handleEnvoyer(b.id)} className="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-700 rounded-lg hover:bg-green-100">Envoyer</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
