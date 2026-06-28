import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { marketplaceApi } from '@api/marketplace.api';
import { useAuth } from '@context/AuthContext';

function StarRating({ note }) {
  if (!note) return <span className="text-neutral-400 text-sm">Aucun avis</span>;
  const full = Math.floor(note);
  const stars = Array.from({ length: 5 }, (_, i) => i < full ? '★' : '☆');
  return (
    <span className="flex items-center gap-1">
      <span className="text-amber-400 text-lg">{stars.join('')}</span>
      <span className="text-sm text-neutral-500">{note.toFixed(1)}/5</span>
    </span>
  );
}

export default function MarketplaceOffreDetailPage() {
  const { id } = useParams();
  const { isAuthenticated } = useAuth();
  const [offre, setOffre] = useState(null);
  const [noteMoyenne, setNoteMoyenne] = useState(null);
  const [avisList, setAvisList] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      setIsLoading(true);
      try {
        const res = await marketplaceApi.getOffre(id);
        setOffre(res.data?.offre || res.data);
        setNoteMoyenne(res.data?.note_moyenne);

        if (res.data?.offre?.enseignant_id) {
          const avisRes = await marketplaceApi.getAvisEnseignant(res.data.offre.enseignant_id);
          setAvisList(avisRes.data?.avis || []);
        }
      } catch { /* ignore */ }
      finally { setIsLoading(false); }
    };
    load();
  }, [id]);

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full" />
      </div>
    );
  }

  if (!offre) {
    return (
      <div className="min-h-screen flex items-center justify-center text-neutral-400">
        Offre introuvable
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-neutral-50">
      <div className="max-w-5xl mx-auto px-4 py-8">
        <Link to="/marketplace" className="text-sm text-primary-600 hover:underline mb-4 inline-block">&larr; Retour au marketplace</Link>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <div className="card p-6">
              <div className="flex items-start gap-4 mb-4">
                <div className="w-16 h-16 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold text-xl shrink-0">
                  {offre.enseignant?.prenom?.[0]}{offre.enseignant?.nom?.[0]}
                </div>
                <div>
                  <h1 className="text-2xl font-bold text-neutral-800">
                    {offre.enseignant ? `${offre.enseignant.prenom} ${offre.enseignant.nom}` : 'Centre'}
                  </h1>
                  {offre.enseignant?.experience_annees && (
                    <p className="text-sm text-neutral-500">{offre.enseignant.experience_annees} ans d'expérience</p>
                  )}
                  <div className="flex items-center gap-2 mt-1">
                    <StarRating note={noteMoyenne} />
                  </div>
                </div>
              </div>

              <div className="flex flex-wrap gap-2 mb-4">
                <span className="badge badge-primary">{offre.matiere?.nom_fr}</span>
                <span className="badge badge-neutral">{offre.niveau}</span>
                <span className="badge badge-info">
                  {offre.type_cours === 'en_ligne' ? 'En ligne' : offre.type_cours === 'presentiel' ? 'Présentiel' : 'Mixte'}
                </span>
              </div>

              {offre.description && (
                <div className="mb-4">
                  <h3 className="font-semibold text-neutral-700 mb-1">Description</h3>
                  <p className="text-neutral-600 text-sm leading-relaxed">{offre.description}</p>
                </div>
              )}

              {offre.wilaya && (
                <div className="text-sm text-neutral-500">
                  📍 {offre.wilaya.nom_fr}{offre.adresse ? ` — ${offre.adresse}` : ''}
                </div>
              )}
            </div>

            <div className="card p-6">
              <h2 className="text-lg font-semibold text-neutral-800 mb-4">Avis ({avisList.length})</h2>
              {avisList.length === 0 ? (
                <p className="text-neutral-400 text-sm">Aucun avis pour le moment</p>
              ) : (
                <div className="space-y-4">
                  {avisList.map(avis => (
                    <div key={avis.id} className="border-b border-neutral-100 pb-3 last:border-0">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="text-amber-400 text-sm">
                          {Array.from({ length: 5 }, (_, i) => i < avis.note ? '★' : '☆').join('')}
                        </span>
                        <span className="text-xs text-neutral-400">
                          {new Date(avis.created_at).toLocaleDateString('fr-DZ')}
                        </span>
                      </div>
                      {avis.commentaire && (
                        <p className="text-sm text-neutral-600">{avis.commentaire}</p>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          <div className="space-y-4">
            <div className="card p-6">
              <h3 className="font-semibold text-neutral-700 mb-3">Tarifs</h3>
              <div className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-sm text-neutral-500">Séance</span>
                  <span className="font-bold text-primary-700">{offre.tarif_seance?.toLocaleString('fr-DZ')} DA</span>
                </div>
                {offre.tarif_mensuel && (
                  <div className="flex justify-between">
                    <span className="text-sm text-neutral-500">Mensuel</span>
                    <span className="font-bold text-primary-700">{offre.tarif_mensuel.toLocaleString('fr-DZ')} DA</span>
                  </div>
                )}
              </div>
            </div>

            <div className="card p-6">
              <div className="flex items-center justify-between mb-3">
                <span className="text-sm text-neutral-500">Places disponibles</span>
                <span className={`font-semibold ${offre.places_restantes > 0 ? 'text-green-600' : 'text-red-600'}`}>
                  {offre.places_restantes > 0 ? `${offre.places_restantes} place${offre.places_restantes > 1 ? 's' : ''}` : 'Complet'}
                </span>
              </div>

              {isAuthenticated ? (
                offre.places_restantes > 0 ? (
                  <Link to={`/marketplace/reservation/${offre.id}`}
                    className="btn btn-primary w-full text-center">
                    Réserver
                  </Link>
                ) : (
                  <button disabled className="btn btn-disabled w-full">Complet</button>
                )
              ) : (
                <Link to="/login" className="btn btn-outline w-full text-center">
                  Connectez-vous pour réserver
                </Link>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
