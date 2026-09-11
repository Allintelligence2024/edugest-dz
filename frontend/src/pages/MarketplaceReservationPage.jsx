import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { toast } from 'react-hot-toast';
import { api } from '@api/client';

const inputStyle = {
  width: '100%', boxSizing: 'border-box', background: 'var(--input-bg)',
  border: '1px solid var(--input-border)', borderRadius: '8px',
  color: 'var(--text)', padding: '8px 12px', fontSize: '0.875rem',
};

const cardStyle = {
  background: 'var(--surface)', border: '1px solid var(--border)',
  borderRadius: '12px', padding: '1.25rem',
};

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
        const res = await api(`/marketplace/offres/${id}`);
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
      const res = await api('/marketplace/reservations', {
        method: 'POST',
        body: JSON.stringify({ offre_id: Number(id), date_debut: dateDebut, message: message || undefined }),
      });
      setReservation(res.data || res);
      setStep(2);
      toast.success('Réservation créée');
    } catch (err) {
      toast.error(err?.message || 'Erreur lors de la réservation');
    } finally { setIsSubmitting(false); }
  };

  const handlePayer = async () => {
    setIsSubmitting(true);
    try {
      const res = await api(`/marketplace/reservations/${reservation?.id || id}/payer`, {
        method: 'POST',
      });
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
      toast.error(err?.message || 'Erreur de paiement');
    } finally { setIsSubmitting(false); }
  };

  if (isLoading) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <div style={{
          width: '2.5rem', height: '2.5rem', border: '4px solid var(--accent)',
          borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 1s linear infinite',
        }} />
      </div>
    );
  }

  if (!offre) return null;

  const commission = Math.round(offre.tarif_seance * 0.07);
  const total = offre.tarif_seance;

  return (
    <div style={{ minHeight: '100vh', background: 'var(--bg)' }}>
      <div style={{ maxWidth: '48rem', margin: '0 auto', padding: '2rem 1rem' }}>
        <Link to={`/marketplace/offres/${id}`} style={{ fontSize: '0.875rem', color: 'var(--accent)', textDecoration: 'none', marginBottom: '1rem', display: 'inline-block' }}>
          &larr; Retour à l'offre
        </Link>
        <h1 style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text)', marginBottom: '1.5rem' }}>Réservation</h1>

        {step === 1 && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
            <div style={cardStyle}>
              <h2 style={{ fontWeight: 600, color: 'var(--text)', marginBottom: '0.75rem', fontSize: '0.95rem' }}>Récapitulatif</h2>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', fontSize: '0.875rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ color: 'var(--muted)' }}>Matière</span>
                  <span style={{ fontWeight: 500, color: 'var(--text)' }}>{offre.matiere?.nom_fr}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ color: 'var(--muted)' }}>Niveau</span>
                  <span style={{ fontWeight: 500, color: 'var(--text)' }}>{offre.niveau}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ color: 'var(--muted)' }}>Type</span>
                  <span style={{ fontWeight: 500, color: 'var(--text)' }}>
                    {offre.type_cours === 'en_ligne' ? 'En ligne' : offre.type_cours === 'presentiel' ? 'Présentiel' : 'Mixte'}
                  </span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ color: 'var(--muted)' }}>Tarif séance</span>
                  <span style={{ fontWeight: 700, color: 'var(--accent)' }}>{offre.tarif_seance?.toLocaleString('fr-DZ')} DA</span>
                </div>
              </div>
            </div>

            <div style={cardStyle}>
              <label style={{ display: 'block', marginBottom: '4px', fontSize: '0.875rem', fontWeight: 500, color: 'var(--text)' }}>Date de début</label>
              <input type="date" value={dateDebut}
                onChange={e => setDateDebut(e.target.value)}
                min={new Date().toISOString().split('T')[0]}
                style={inputStyle} />
            </div>

            <div style={cardStyle}>
              <label style={{ display: 'block', marginBottom: '4px', fontSize: '0.875rem', fontWeight: 500, color: 'var(--text)' }}>Message (optionnel)</label>
              <textarea value={message} onChange={e => setMessage(e.target.value)}
                style={{ ...inputStyle, resize: 'vertical' }} rows={3}
                placeholder="Un message pour l'enseignant..." />
            </div>

            <button onClick={handleConfirm} disabled={isSubmitting}
              style={{
                width: '100%', padding: '12px', borderRadius: '8px', border: 'none',
                background: isSubmitting ? 'var(--surface2)' : 'var(--accent)',
                color: isSubmitting ? 'var(--muted)' : '#fff',
                fontWeight: 600, fontSize: '0.875rem', cursor: isSubmitting ? 'not-allowed' : 'pointer',
              }}>
              {isSubmitting ? 'Réservation en cours...' : 'Confirmer la réservation'}
            </button>
          </div>
        )}

        {step === 2 && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
            <div style={cardStyle}>
              <h2 style={{ fontWeight: 600, color: 'var(--text)', marginBottom: '0.75rem', fontSize: '0.95rem' }}>Paiement</h2>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', fontSize: '0.875rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ color: 'var(--muted)' }}>Montant</span>
                  <span style={{ fontWeight: 500, color: 'var(--text)' }}>{total.toLocaleString('fr-DZ')} DA</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ color: 'var(--muted)' }}>Commission (7%)</span>
                  <span style={{ fontWeight: 500, color: 'var(--muted)' }}>{commission.toLocaleString('fr-DZ')} DA</span>
                </div>
                <div style={{ borderTop: '1px solid var(--border)', paddingTop: '0.5rem', display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ fontWeight: 600, color: 'var(--text)' }}>Total</span>
                  <span style={{ fontWeight: 700, color: 'var(--accent)' }}>{total.toLocaleString('fr-DZ')} DA</span>
                </div>
              </div>
            </div>

            <button onClick={handlePayer} disabled={isSubmitting}
              style={{
                width: '100%', padding: '12px', borderRadius: '8px', border: 'none',
                background: isSubmitting ? 'var(--surface2)' : 'var(--accent)',
                color: isSubmitting ? 'var(--muted)' : '#fff',
                fontWeight: 600, fontSize: '0.875rem', cursor: isSubmitting ? 'not-allowed' : 'pointer',
              }}>
              {isSubmitting ? 'Redirection...' : 'Payer avec CIB / Dahabia'}
            </button>
          </div>
        )}

        {step === 3 && (
          <div style={{ ...cardStyle, textAlign: 'center', padding: '2rem' }}>
            <div style={{ fontSize: '3rem', marginBottom: '1rem' }}>{lienVisio ? '&#127881;' : '&#9989;'}</div>
            <h2 style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--text)', marginBottom: '0.5rem' }}>
              {lienVisio ? 'Paiement confirmé !' : 'Réservation confirmée'}
            </h2>
            <p style={{ color: 'var(--muted)', marginBottom: '1.5rem' }}>
              {lienVisio
                ? 'Votre lien de visioconférence est prêt.'
                : 'Votre réservation a été enregistrée.'}
            </p>

            {lienVisio && (
              <a href={lienVisio} target="_blank" rel="noopener noreferrer"
                style={{
                  display: 'inline-block', padding: '10px 20px', borderRadius: '8px',
                  background: 'var(--accent)', color: '#fff', textDecoration: 'none',
                  fontWeight: 600, fontSize: '0.875rem', marginBottom: '1rem',
                }}>
                Rejoindre la visio
              </a>
            )}

            {paymentUrl && (
              <p style={{ fontSize: '0.875rem', color: 'var(--muted)', marginBottom: '1rem' }}>
                Si vous n'êtes pas redirigé,{' '}
                <a href={paymentUrl} target="_blank" rel="noopener noreferrer"
                  style={{ color: 'var(--accent)', textDecoration: 'underline' }}>cliquez ici</a>
              </p>
            )}

            <div style={{ display: 'flex', justifyContent: 'center', gap: '0.75rem' }}>
              <Link to="/mes-reservations" style={{
                padding: '10px 20px', borderRadius: '8px', border: '1px solid var(--border)',
                background: 'transparent', color: 'var(--text)', textDecoration: 'none', fontSize: '0.875rem',
              }}>Voir mes réservations</Link>
              <Link to="/marketplace" style={{
                padding: '10px 20px', borderRadius: '8px', border: '1px solid var(--border)',
                background: 'transparent', color: 'var(--text)', textDecoration: 'none', fontSize: '0.875rem',
              }}>Retour au marketplace</Link>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
