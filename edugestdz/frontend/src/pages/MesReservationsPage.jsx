import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'react-hot-toast';
import { api } from '@api/client';

const STATUT_STYLES = {
  en_attente: { bg: 'rgba(245,158,11,0.15)', color: 'var(--orange)' },
  confirmee: { bg: 'rgba(6,182,212,0.15)', color: 'var(--teal)' },
  payee: { bg: 'rgba(16,185,129,0.15)', color: 'var(--green)' },
  annulee: { bg: 'rgba(239,68,68,0.15)', color: 'var(--red)' },
  terminee: { bg: 'var(--surface2)', color: 'var(--muted)' },
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
      const res = await api('/marketplace/reservations');
      const raw = res.data;
      if (Array.isArray(raw)) {
        setReservations(raw);
      } else if (raw && Array.isArray(raw.data)) {
        setReservations(raw.data);
      } else {
        setReservations([]);
      }
    } catch { setReservations([]); }
    finally { setIsLoading(false); }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleAnnuler = async (reservationId) => {
    if (!window.confirm('Annuler cette réservation ?')) return;
    try {
      await api(`/marketplace/reservations/${reservationId}/annuler`, { method: 'POST' });
      toast.success('Réservation annulée');
      load();
    } catch (err) {
      toast.error(err?.message || 'Erreur');
    }
  };

  const handleTerminer = async (reservationId) => {
    try {
      await api(`/marketplace/reservations/${reservationId}/terminer`, { method: 'POST' });
      toast.success('Réservation terminée');
      load();
    } catch (err) {
      toast.error(err?.message || 'Erreur');
    }
  };

  const handleAvisSubmit = async () => {
    if (!avisModal) return;
    try {
      await api('/marketplace/avis', {
        method: 'POST',
        body: JSON.stringify({
          reservation_id: avisModal.id,
          note: avisNote,
          commentaire: avisCommentaire || undefined,
        }),
      });
      toast.success('Avis laissé');
      setAvisModal(null);
      setAvisNote(5);
      setAvisCommentaire('');
      load();
    } catch (err) {
      toast.error(err?.message || 'Erreur');
    }
  };

  const cardStyle = {
    background: 'var(--surface)', border: '1px solid var(--border)',
    borderRadius: '12px', padding: '1.25rem',
  };

  return (
    <div style={{ maxWidth: '64rem', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
      <div>
        <h1 style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text)', margin: 0 }}>Mes réservations</h1>
        <p style={{ fontSize: '0.875rem', color: 'var(--muted)', marginTop: '2px' }}>Suivez vos réservations de cours</p>
      </div>

      {isLoading ? (
        <div style={{ display: 'flex', justifyContent: 'center', padding: '4rem 0' }}>
          <div style={{
            width: '2.5rem', height: '2.5rem', border: '4px solid var(--accent)',
            borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 1s linear infinite',
          }} />
        </div>
      ) : reservations.length === 0 ? (
        <div style={{ ...cardStyle, textAlign: 'center', padding: '3rem', color: 'var(--muted)' }}>
          <p style={{ fontSize: '3rem', marginBottom: '0.75rem' }}>&#128203;</p>
          <p style={{ fontSize: '1.125rem', marginBottom: '0.5rem' }}>Aucune réservation</p>
          <Link to="/marketplace" style={{ color: 'var(--accent)', textDecoration: 'none', fontSize: '0.875rem' }}>
            Découvrir les offres
          </Link>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {reservations.map(res => (
            <div key={res.id} style={cardStyle}>
              <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '1rem' }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: '0.75rem', flex: 1, minWidth: 0 }}>
                  <div style={{
                    width: '2.75rem', height: '2.75rem', borderRadius: '50%',
                    background: 'var(--accent)', opacity: 0.15,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    color: 'var(--accent)', fontWeight: 700, flexShrink: 0,
                  }}>
                    <span style={{ opacity: 1 }}>{res.offre?.enseignant?.prenom?.[0]}{res.offre?.enseignant?.nom?.[0]}</span>
                  </div>
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <p style={{ fontWeight: 600, color: 'var(--text)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', margin: 0 }}>
                      {res.offre?.matiere?.nom_fr || 'Offre'} — {res.offre?.niveau}
                    </p>
                    <p style={{ fontSize: '0.875rem', color: 'var(--muted)', margin: '2px 0 0' }}>
                      {res.offre?.enseignant ? `${res.offre.enseignant.prenom} ${res.offre.enseignant.nom}` : 'Centre'}
                      {res.offre?.wilaya ? ` — ${res.offre.wilaya.nom_fr}` : ''}
                    </p>
                    <p style={{ fontSize: '0.75rem', color: 'var(--muted)', margin: '4px 0 0' }}>
                      Début: {new Date(res.date_debut).toLocaleDateString('fr-DZ')}
                      {res.lien_visio && (
                        <a href={res.lien_visio} target="_blank" rel="noopener noreferrer"
                          style={{ marginLeft: '0.5rem', color: 'var(--accent)', textDecoration: 'underline' }}>Lien visio</a>
                      )}
                    </p>
                  </div>
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '0.5rem', flexShrink: 0 }}>
                  <span style={{
                    ...(STATUT_STYLES[res.statut] || STATUT_STYLES.terminee),
                    fontSize: '0.75rem', padding: '2px 10px', borderRadius: '9999px', fontWeight: 500,
                  }}>
                    {STATUT_LABELS[res.statut] || res.statut}
                  </span>
                  <span style={{ fontWeight: 600, color: 'var(--accent)', fontSize: '0.875rem' }}>
                    {res.montant?.toLocaleString('fr-DZ')} DA
                  </span>
                </div>
              </div>

              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginTop: '0.75rem', paddingTop: '0.75rem', borderTop: '1px solid var(--border)' }}>
                {res.statut === 'en_attente' && (
                  <button onClick={() => handleAnnuler(res.id)}
                    style={{
                      padding: '6px 14px', borderRadius: '8px', border: '1px solid rgba(239,68,68,0.3)',
                      background: 'transparent', color: 'var(--red)', cursor: 'pointer', fontSize: '0.8rem',
                    }}>
                    Annuler
                  </button>
                )}
                {res.statut === 'payee' && (
                  <button onClick={() => handleTerminer(res.id)}
                    style={{
                      padding: '6px 14px', borderRadius: '8px', border: 'none',
                      background: 'var(--accent)', color: '#fff', cursor: 'pointer', fontSize: '0.8rem', fontWeight: 500,
                    }}>
                    Terminer
                  </button>
                )}
                {res.statut === 'terminee' && !res.avis && (
                  <button onClick={() => setAvisModal(res)}
                    style={{
                      padding: '6px 14px', borderRadius: '8px', border: '1px solid var(--border)',
                      background: 'transparent', color: 'var(--text)', cursor: 'pointer', fontSize: '0.8rem',
                    }}>
                    Laisser un avis
                  </button>
                )}
                {res.statut === 'terminee' && res.avis && (
                  <span style={{ fontSize: '0.75rem', color: 'var(--orange)' }}>&#11088; Avis laissé</span>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {avisModal && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.4)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 50, padding: '1rem',
        }}>
          <div style={{
            background: 'var(--surface)', border: '1px solid var(--border)',
            borderRadius: '16px', padding: '1.5rem', maxWidth: '28rem', width: '100%',
            boxShadow: 'var(--shadow)',
          }}>
            <h3 style={{ fontSize: '1.125rem', fontWeight: 600, color: 'var(--text)', marginBottom: '1rem' }}>Laisser un avis</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              <div>
                <label style={{ display: 'block', fontSize: '0.875rem', fontWeight: 500, color: 'var(--text)', marginBottom: '4px' }}>Note</label>
                <div style={{ display: 'flex', gap: '4px' }}>
                  {[1,2,3,4,5].map(n => (
                    <button key={n} onClick={() => setAvisNote(n)}
                      style={{
                        fontSize: '1.875rem', background: 'none', border: 'none', cursor: 'pointer',
                        color: n <= avisNote ? 'var(--orange)' : 'var(--border)',
                        transition: 'color 0.15s',
                      }}>
                      &#9733;
                    </button>
                  ))}
                </div>
              </div>
              <div>
                <label style={{ display: 'block', fontSize: '0.875rem', fontWeight: 500, color: 'var(--text)', marginBottom: '4px' }}>Commentaire (optionnel)</label>
                <textarea value={avisCommentaire} onChange={e => setAvisCommentaire(e.target.value)}
                  rows={3} placeholder="Votre avis..."
                  style={{
                    width: '100%', boxSizing: 'border-box', background: 'var(--input-bg)',
                    border: '1px solid var(--input-border)', borderRadius: '8px',
                    color: 'var(--text)', padding: '8px 12px', fontSize: '0.875rem',
                  }} />
              </div>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <button onClick={handleAvisSubmit} style={{
                  flex: 1, padding: '10px', borderRadius: '8px', border: 'none',
                  background: 'var(--accent)', color: '#fff', cursor: 'pointer', fontWeight: 600, fontSize: '0.875rem',
                }}>Envoyer</button>
                <button onClick={() => setAvisModal(null)} style={{
                  padding: '10px 20px', borderRadius: '8px', border: '1px solid var(--border)',
                  background: 'transparent', color: 'var(--text)', cursor: 'pointer', fontSize: '0.875rem',
                }}>Annuler</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
