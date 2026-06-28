import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { toast } from 'react-hot-toast';
import { marketplaceApi } from '@api/marketplace.api';

export default function MarketplaceReservationPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [offre, setOffre] = useState(null);
  const [step, setStep] = useState(1);
  const [dateDebut, setDateDebut] = useState('');
  const [message, setMessage] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [reservation, setReservation] = useState(null);
  const [paymentUrl, setPaymentUrl] = useState(null);
  const [lienVisio, setLienVisio] = useState(null);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await marketplaceApi.getOffre(id);
        setOffre(res.data?.offre || res.data);
      } catch {
        toast.error('Offre introuvable');
        navigate('/marketplace');
      } finally { setIsLoading(false); }
    };
    load();
  }, [id, navigate]);

  const handleConfirm = async () => {
    if (!dateDebut) {
      toast.error('Veuillez choisir une date de début');
      return;
    }
    setIsSubmitting(true);
    try {
      const res = await marketplaceApi.createReservation({
        offre_id: id,
        date_debut: dateDebut,
        message: message || undefined,
      });
      setReservation(res.data || res);
      setStep(2);
      toast.success('Réservation créée');
    } catch (err) {
      toast.error(err?.error?.message || 'Erreur lors de la réservation');
    } finally { setIsSubmitting(false); }
  };

  const handlePayer = async () => {
    setIsSubmitting(true);
    try {
      const res = await marketplaceApi.payerReservation(reservation?.id || id);
      const data = res.data || res;
      setReservation(data?.reservation || data);
      if (data?.redirect_url) {
        setPaymentUrl(data.redirect_url);
        setStep(3);
      } else {
        setStep(3);
        toast.success('Paiement confirmé');
      }
      setLienVisio(data?.lien_visio);
    } catch (err) {
      toast.error(err?.error?.message || 'Erreur de paiement');
    } finally { setIsSubmitting(false); }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full" />
      </div>
    );
  }

  if (!offre) return null;

  const commission = Math.round(offre.tarif_seance * 0.07);
  const total = offre.tarif_seance;

  return (
    <div className="min-h-screen bg-neutral-50">
      <div className="max-w-3xl mx-auto px-4 py-8">
        <Link to={`/marketplace/offres/${id}`} className="text-sm text-primary-600 hover:underline mb-4 inline-block">&larr; Retour à l'offre</Link>
        <h1 className="text-2xl font-bold text-neutral-800 mb-6">Réservation</h1>

        {step === 1 && (
          <div className="space-y-6">
            <div className="card p-5">
              <h2 className="font-semibold text-neutral-700 mb-3">Récapitulatif</h2>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-neutral-500">Matière</span>
                  <span className="font-medium">{offre.matiere?.nom_fr}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-neutral-500">Niveau</span>
                  <span className="font-medium">{offre.niveau}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-neutral-500">Type</span>
                  <span className="font-medium">
                    {offre.type_cours === 'en_ligne' ? 'En ligne' : offre.type_cours === 'presentiel' ? 'Présentiel' : 'Mixte'}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-neutral-500">Tarif séance</span>
                  <span className="font-bold text-primary-700">{offre.tarif_seance.toLocaleString('fr-DZ')} DA</span>
                </div>
              </div>
            </div>

            <div className="card p-5">
              <label className="block mb-1 text-sm font-medium text-neutral-700">Date de début</label>
              <input type="date" value={dateDebut}
                onChange={e => setDateDebut(e.target.value)}
                min={new Date().toISOString().split('T')[0]}
                className="input w-full" />
            </div>

            <div className="card p-5">
              <label className="block mb-1 text-sm font-medium text-neutral-700">Message (optionnel)</label>
              <textarea value={message} onChange={e => setMessage(e.target.value)}
                className="input w-full" rows={3}
                placeholder="Un message pour l'enseignant..." />
            </div>

            <button onClick={handleConfirm} disabled={isSubmitting}
              className="btn btn-primary w-full">
              {isSubmitting ? 'Réservation en cours...' : 'Confirmer la réservation'}
            </button>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-6">
            <div className="card p-5">
              <h2 className="font-semibold text-neutral-700 mb-3">Paiement</h2>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-neutral-500">Montant</span>
                  <span className="font-medium">{total.toLocaleString('fr-DZ')} DA</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-neutral-500">Commission (7%)</span>
                  <span className="font-medium text-neutral-400">{commission.toLocaleString('fr-DZ')} DA</span>
                </div>
                <div className="border-t pt-2 flex justify-between">
                  <span className="font-semibold">Total</span>
                  <span className="font-bold text-primary-700">{total.toLocaleString('fr-DZ')} DA</span>
                </div>
              </div>
            </div>

            <button onClick={handlePayer} disabled={isSubmitting}
              className="btn btn-primary w-full">
              {isSubmitting ? 'Redirection...' : 'Payer avec CIB / Dahabia'}
            </button>
          </div>
        )}

        {step === 3 && (
          <div className="card p-8 text-center">
            <div className="text-5xl mb-4">{lienVisio ? '🎉' : '✅'}</div>
            <h2 className="text-xl font-bold text-neutral-800 mb-2">
              {lienVisio ? 'Paiement confirmé !' : 'Réservation confirmée'}
            </h2>
            <p className="text-neutral-500 mb-6">
              {lienVisio
                ? 'Votre lien de visioconférence est prêt.'
                : 'Votre réservation a été enregistrée.'}
            </p>

            {lienVisio && (
              <a href={lienVisio} target="_blank" rel="noopener noreferrer"
                className="btn btn-primary mb-4 inline-block">
                Rejoindre la visio
              </a>
            )}

            {paymentUrl && (
              <p className="text-sm text-neutral-400 mb-4">
                Si vous n'êtes pas redirigé,{' '}
                <a href={paymentUrl} target="_blank" rel="noopener noreferrer"
                  className="text-primary-600 underline">cliquez ici</a>
              </p>
            )}

            <div className="flex justify-center gap-3">
              <Link to="/mes-reservations" className="btn btn-outline">Voir mes réservations</Link>
              <Link to="/marketplace" className="btn btn-outline">Retour au marketplace</Link>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
