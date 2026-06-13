import React, { useState, useEffect } from 'react';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';

export default function PresencesPage() {
  const [seances, setSeances] = useState([]);
  const [selectedSeance, setSelectedSeance] = useState(null);
  const [presences, setPresences] = useState({});
  const [isSaving, setIsSaving] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  const loadSeances = async () => {
    setIsLoading(true);
    try {
      const res = await api.get('/seances', {
        params: { date_debut: new Date().toISOString().split('T')[0], statut: 'planifiée', per_page: 50 }
      });
      setSeances(res.data || []);
    } catch { /* silent */ }
    finally { setIsLoading(false); }
  };

  useEffect(() => { loadSeances(); }, []);

  const selectSeance = async (seance) => {
    setSelectedSeance(seance);
    setIsLoading(true);
    try {
      const res = await api.get(`/seances/${seance.id}/presences`);
      const data = res.data || res.liste || [];
      const map = {};
      data.forEach(p => { map[p.inscription_id || p.eleve_id] = p.statut || 'abscent'; });
      setPresences(map);
    } catch {
      const res = await api.get(`/seances/${seance.id}`);
      const inscrits = res.inscriptions || [];
      const map = {};
      inscrits.forEach(i => { map[i.id] = 'abscent'; });
      setPresences(map);
    }
    finally { setIsLoading(false); }
  };

  const togglePresence = (inscriptionId, statut) => {
    setPresences(prev => ({ ...prev, [inscriptionId]: statut }));
  };

  const savePresences = async () => {
    setIsSaving(true);
    try {
      await api.post(`/seances/${selectedSeance.id}/presences`, { presences });
      toast.success(`✅ Présences enregistrées pour ${selectedSeance.matiere}`);
      loadSeances();
    } catch (err) {
      toast.error(err?.error?.message || 'Erreur lors de l\'enregistrement');
    } finally {
      setIsSaving(false);
    }
  };

  const getInscriptions = () => {
    if (!selectedSeance?.inscriptions) return [];
    if (selectedSeance.groupe?.inscriptions) return selectedSeance.groupe.inscriptions;
    return selectedSeance.inscriptions || [];
  };

  const STATUT_STYLES = {
    present: { bg: 'bg-green-100', text: 'text-green-700', border: 'border-green-500' },
    abscent: { bg: 'bg-red-100', text: 'text-red-700', border: 'border-red-500' },
    retard: { bg: 'bg-orange-100', text: 'text-orange-700', border: 'border-orange-500' },
    justifié: { bg: 'bg-blue-100', text: 'text-blue-700', border: 'border-blue-500' },
  };

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-neutral-800">📋 Gestion des présences</h1>
        <p className="text-neutral-500 text-sm mt-1">Sélectionnez une séance pour enregistrer les présences</p>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="col-span-1 bg-white rounded-2xl border border-neutral-200 p-4">
          <h2 className="text-sm font-bold text-neutral-700 mb-3 uppercase tracking-wider">Séances du jour</h2>
          {isLoading ? (
            <div className="text-center py-8 text-neutral-400">Chargement...</div>
          ) : seances.length === 0 ? (
            <div className="text-center py-8 text-neutral-400">Aucune séance aujourd'hui</div>
          ) : (
            <div className="space-y-2">
              {seances.map(s => (
                <button
                  key={s.id}
                  onClick={() => selectSeance(s)}
                  className={`w-full text-left p-3 rounded-xl border-2 transition-all ${selectedSeance?.id === s.id ? 'border-primary-500 bg-primary-50' : 'border-transparent bg-neutral-50 hover:bg-neutral-100'}`}
                >
                  <p className="font-bold text-sm text-neutral-800">{s.matiere}</p>
                  <p className="text-xs text-neutral-500 mt-0.5">{s.groupe}</p>
                  <p className="text-xs font-mono text-neutral-400 mt-1">{s.heure_debut?.substring(0,5)} - {s.heure_fin?.substring(0,5)}</p>
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="col-span-2 bg-white rounded-2xl border border-neutral-200 p-4">
          {!selectedSeance ? (
            <div className="text-center py-16 text-neutral-400">
              <p className="text-4xl mb-3">👈</p>
              <p>Sélectionnez une séance à gauche</p>
            </div>
          ) : (
            <>
              <div className="flex items-center justify-between mb-4">
                <div>
                  <h2 className="text-lg font-bold text-neutral-800">{selectedSeance.matiere}</h2>
                  <p className="text-sm text-neutral-500">{selectedSeance.groupe}</p>
                </div>
                <button onClick={savePresences} disabled={isSaving} className="px-6 py-2.5 bg-primary-600 text-white rounded-xl font-semibold text-sm hover:bg-primary-700 disabled:opacity-60 flex items-center gap-2">
                  {isSaving ? <><span className="animate-spin">⏳</span> Sauvegarde...</> : '💾 Enregistrer'}
                </button>
              </div>

              <div className="flex gap-2 mb-4">
                {Object.entries(STATUT_STYLES).map(([key, style]) => (
                  <button key={key} onClick={() => {
                    getInscriptions().forEach(i => {
                      setPresences(prev => ({ ...prev, [i.id]: key }));
                    });
                  }} className={`px-3 py-1.5 rounded-lg text-xs font-semibold ${style.bg} ${style.text}`}>
                    Tout {key}
                  </button>
                ))}
              </div>

              <table className="w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    <th className="text-left p-3 font-semibold text-neutral-600">Élève</th>
                    <th className="text-center p-3 font-semibold text-neutral-600">Présent</th>
                    <th className="text-center p-3 font-semibold text-neutral-600">Abscent</th>
                    <th className="text-center p-3 font-semibold text-neutral-600">Retard</th>
                    <th className="text-center p-3 font-semibold text-neutral-600">Justifié</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {getInscriptions().map(inscription => {
                    const currentStatut = presences[inscription.id] || 'abscent';
                    return (
                      <tr key={inscription.id} className="hover:bg-neutral-50">
                        <td className="p-3 font-medium text-neutral-700">{inscription.eleve_nom || inscription.eleve?.nom} {inscription.eleve_prenom || inscription.eleve?.prenom}</td>
                        {['present', 'abscent', 'retard', 'justifié'].map(statut => {
                          const st = STATUT_STYLES[statut];
                          const isSelected = currentStatut === statut;
                          return (
                            <td key={statut} className="p-3 text-center">
                              <button onClick={() => togglePresence(inscription.id, statut)} className={`w-6 h-6 rounded-full border-2 transition-all ${isSelected ? `${st.border} ${st.bg}` : 'border-neutral-200 hover:border-neutral-400'}`}>
                                {isSelected && <span className="block text-xs">✓</span>}
                              </button>
                            </td>
                          );
                        })}
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
