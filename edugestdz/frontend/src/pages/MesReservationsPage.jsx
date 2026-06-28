import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'react-hot-toast';
import { marketplaceApi } from '@api/marketplace.api';

const STATUT_STYLES = {
  en_attente: 'badge-warning',
  confirmee: 'badge-info',
  payee: 'badge-success',
  annulee: 'badge-error',
  terminee: 'badge-neutral',
};

const STATUT_LABELS = {
  en_attente: 'En attente',
  confirmee: 'Confirmée',
  payee: 'Payée',
  annulee: 'Annulée',
  terminee: 'Terminée',
};

export default function MesReservationsPage() {
  const [reservations, setReservations] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [avisModal, setAvisModal] = useState(null);
  const [avisNote, setAvisNote] = useState(5);
  const [avisCommentaire, setAvisCommentaire] = useState('');

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await marketplaceApi.mesReservations();
      setReservations(res.data || []);
    } catch { setReservations([]); }
    finally { setIsLoading(false); }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleAnnuler = async (id) => {
    if (!window.confirm('Annuler cette réservation ?')) return;
    try {
      await marketplaceApi.annulerReservation(id);
      toast.success('Réservation annulée');
      load();
    } catch (err) {
      toast.error(err?.error?.message || 'Erreur');
    }
  };

  const handleTerminer = async (id) => {
    try {
      await marketplaceApi.terminerReservation(id);
      toast.success('Réservation terminée');
      load();
    } catch (err) {
      toast.error(err?.error?.message || 'Erreur');
    }
  };

  const handleAvisSubmit = async () => {
    if (!avisModal) return;
    try {
      await marketplaceApi.createAvis({
        reservation_id: avisModal.id,
        note: avisNote,
        commentaire: avisCommentaire || undefined,
      });
      toast.success('Avis laissé');
      setAvisModal(null);
      setAvisNote(5);
      setAvisCommentaire('');
      load();
    } catch (err) {
      toast.error(err?.error?.message || 'Erreur');
    }
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-neutral-800">Mes réservations</h1>
        <p className="text-sm text-neutral-400 mt-0.5">Suivez vos réservations de cours</p>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-16">
          <div className="animate-spin w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full" />
        </div>
      ) : reservations.length === 0 ? (
        <div className="card p-12 text-center text-neutral-400">
          <p className="text-5xl mb-3">📋</p>
          <p className="text-lg mb-2">Aucune réservation</p>
          <Link to="/marketplace" className="text-primary-600 hover:underline text-sm">
            Découvrir les offres
          </Link>
        </div>
      ) : (
        <div className="space-y-4">
          {reservations.map(res => (
            <div key={res.id} className="card p-5">
              <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3 min-w-0 flex-1">
                  <div className="w-11 h-11 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold shrink-0">
                    {res.offre?.enseignant?.prenom?.[0]}{res.offre?.enseignant?.nom?.[0]}
                  </div>
                  <div className="min-w-0">
                    <p className="font-semibold text-neutral-800 truncate">
                      {res.offre?.matiere?.nom_fr || 'Offre'} — {res.offre?.niveau}
                    </p>
                    <p className="text-sm text-neutral-500">
                      {res.offre?.enseignant ? `${res.offre.enseignant.prenom} ${res.offre.enseignant.nom}` : 'Centre'}
                      {res.offre?.wilaya ? ` — ${res.offre.wilaya.nom_fr}` : ''}
                    </p>
                    <p className="text-xs text-neutral-400 mt-1">
                      Début: {new Date(res.date_debut).toLocaleDateString('fr-DZ')}
                      {res.lien_visio && (
                        <a href={res.lien_visio} target="_blank" rel="noopener noreferrer"
                          className="ml-2 text-primary-600 underline">Lien visio</a>
                      )}
                    </p>
                  </div>
                </div>
                <div className="flex flex-col items-end gap-2 shrink-0">
                  <span className={`badge ${STATUT_STYLES[res.statut] || 'badge-neutral'}`}>
                    {STATUT_LABELS[res.statut] || res.statut}
                  </span>
                  <span className="font-semibold text-primary-700 text-sm">
                    {res.montant.toLocaleString('fr-DZ')} DA
                  </span>
                </div>
              </div>

              <div className="flex items-center gap-2 mt-3 pt-3 border-t border-neutral-100">
                {res.statut === 'en_attente' && (
                  <button onClick={() => handleAnnuler(res.id)}
                    className="btn btn-sm btn-outline text-red-600 border-red-200 hover:bg-red-50">
                    Annuler
                  </button>
                )}
                {res.statut === 'payee' && (
                  <button onClick={() => handleTerminer(res.id)}
                    className="btn btn-sm btn-primary">
                    Terminer
                  </button>
                )}
                {res.statut === 'terminee' && !res.avis && (
                  <button onClick={() => setAvisModal(res)}
                    className="btn btn-sm btn-outline">
                    Laisser un avis
                  </button>
                )}
                {res.statut === 'terminee' && res.avis && (
                  <span className="text-xs text-amber-500">⭐ Avis laissé</span>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {avisModal && (
        <div className="fixed inset-0 bg-black/30 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl p-6 max-w-md w-full shadow-xl">
            <h3 className="text-lg font-semibold text-neutral-800 mb-4">Laisser un avis</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Note</label>
                <div className="flex gap-1">
                  {[1,2,3,4,5].map(n => (
                    <button key={n} onClick={() => setAvisNote(n)}
                      className={`text-3xl transition-colors ${n <= avisNote ? 'text-amber-400' : 'text-neutral-200'}`}>
                      ★
                    </button>
                  ))}
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Commentaire (optionnel)</label>
                <textarea value={avisCommentaire} onChange={e => setAvisCommentaire(e.target.value)}
                  className="input w-full" rows={3} placeholder="Votre avis..." />
              </div>
              <div className="flex gap-2">
                <button onClick={handleAvisSubmit} className="btn btn-primary flex-1">Envoyer</button>
                <button onClick={() => setAvisModal(null)} className="btn btn-outline">Annuler</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
