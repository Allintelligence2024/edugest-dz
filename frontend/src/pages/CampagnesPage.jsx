import React, { useState, useCallback, useEffect } from 'react';
import { campagneApi } from '@api/campagne.api';
import toast from 'react-hot-toast';

const CANAUX = ['in_app', 'email', 'sms', 'push'];
const FILTRES_AUDIENCE = [
  { value: 'tous', label: 'Tous les utilisateurs' },
  { value: 'parents', label: 'Parents uniquement' },
  { value: 'impayes', label: 'Parents avec impayés' },
];

export default function CampagnesPage() {
  const [campagnes, setCampagnes] = useState([]);
  const [showForm, setShowForm] = useState(false);
  const [titre, setTitre] = useState('');
  const [message, setMessage] = useState('');
  const [canaux, setCanaux] = useState(['in_app']);
  const [filtreAudience, setFiltreAudience] = useState('tous');
  const [loading, setLoading] = useState(false);

  const fetchCampagnes = useCallback(async () => {
    try { const r = await campagneApi.list({ per_page: 50 }); setCampagnes(r.data || []); } catch { /* ignore */ }
  }, []);

  useEffect(() => { fetchCampagnes(); }, [fetchCampagnes]);

  const toggleCanal = (c) => setCanaux(prev => prev.includes(c) ? prev.filter(x => x !== c) : [...prev, c]);

  const handleCreate = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      await campagneApi.create({ titre, message, canaux, filtres: filtreAudience === 'tous' ? [] : [filtreAudience] });
      toast.success('Campagne créée et envoyée');
      setShowForm(false);
      setTitre(''); setMessage(''); setCanaux(['in_app']);
      fetchCampagnes();
    } catch { toast.error('Erreur création'); } finally { setLoading(false); }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-neutral-800">Campagnes de communication</h1>
        <button onClick={() => setShowForm(!showForm)} className="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium">{showForm ? 'Annuler' : 'Nouvelle campagne'}</button>
      </div>

      {showForm && (
        <form onSubmit={handleCreate} className="bg-white rounded-2xl border p-6 space-y-4">
          <input value={titre} onChange={e => setTitre(e.target.value)} placeholder="Titre de la campagne" className="w-full px-4 py-2.5 border rounded-xl text-sm" required />
          <textarea value={message} onChange={e => setMessage(e.target.value)} placeholder="Message..." rows={4} className="w-full px-4 py-2.5 border rounded-xl text-sm resize-none" required />
          <div className="flex gap-4">
            <span className="text-sm font-medium text-neutral-600 self-center">Canaux :</span>
            {CANAUX.map(c => (
              <button key={c} type="button" onClick={() => toggleCanal(c)} className={`px-3 py-1.5 rounded-lg text-xs font-medium border ${canaux.includes(c) ? 'bg-primary-50 border-primary-300 text-primary-700' : 'bg-white border-neutral-300 text-neutral-500'}`}>{c}</button>
            ))}
          </div>
          <div className="flex gap-4 items-center">
            <span className="text-sm font-medium text-neutral-600">Audience :</span>
            <select value={filtreAudience} onChange={e => setFiltreAudience(e.target.value)} className="px-3 py-2 border rounded-xl text-sm bg-white">
              {FILTRES_AUDIENCE.map(f => <option key={f.value} value={f.value}>{f.label}</option>)}
            </select>
          </div>
          <button type="submit" disabled={loading} className="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-medium disabled:opacity-50">{loading ? 'Envoi...' : 'Envoyer la campagne'}</button>
        </form>
      )}

      <div className="bg-white rounded-2xl border border-neutral-100 shadow-sm overflow-x-auto">
        <table className="w-full text-sm">
          <thead><tr className="border-b bg-neutral-50"><th className="text-left px-4 py-3">Titre</th><th className="text-left px-4 py-3">Canaux</th><th className="text-center px-4 py-3">Destinataires</th><th className="text-center px-4 py-3">Envoyés</th><th className="text-center px-4 py-3">Échecs</th><th className="text-center px-4 py-3">Statut</th><th className="text-center px-4 py-3">Date</th></tr></thead>
          <tbody>
            {campagnes.map(c => (
              <tr key={c.id} className="border-b hover:bg-neutral-50">
                <td className="px-4 py-3 font-medium">{c.titre}</td>
                <td className="px-4 py-3">{c.canaux?.join(', ')}</td>
                <td className="px-4 py-3 text-center">{c.nb_destinataires}</td>
                <td className="px-4 py-3 text-center text-green-600">{c.nb_envoyes}</td>
                <td className="px-4 py-3 text-center text-red-500">{c.nb_echecs}</td>
                <td className="px-4 py-3 text-center"><span className={`px-2.5 py-1 rounded-full text-xs font-medium ${c.statut === 'en_cours' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'}`}>{c.statut}</span></td>
                <td className="px-4 py-3 text-neutral-500">{c.envoyee_le ? new Date(c.envoyee_le).toLocaleDateString('fr-DZ') : '-'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
