import React, { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api } from '@api/client';
import { useAuth } from '@context/AuthContext';
import { toast } from 'react-hot-toast';

function StarRating({ note }) {
  if (!note) return <span style={{ color: 'var(--muted)', fontSize: '0.875rem' }}>Aucun avis</span>;
  const full = Math.floor(note);
  const stars = Array.from({ length: 5 }, (_, i) => i < full ? '★' : '☆');
  return (
    <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
      <span style={{ color: 'var(--orange)', fontSize: '1.125rem' }}>{stars.join('')}</span>
      <span style={{ fontSize: '0.875rem', color: 'var(--muted)' }}>{note.toFixed(1)}/5</span>
    </span>
  );
}

export default function MarketplaceOffreDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const [offre, setOffre] = useState(null);
  const [noteMoyenne, setNoteMoyenne] = useState(null);
  const [avisList, setAvisList] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  const [dateDebut, setDateDebut] = useState('');
  const [message, setMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    const load = async () => {
      setIsLoading(true);
      try {
        const res = await api(`/marketplace/offres/${id}`);
        setOffre(res.data?.offre || res.data);

        const enseignantId = res.data?.offre?.enseignant_id;
        if (enseignantId) {
          try {
            const avisRes = await api(`/marketplace/avis/enseignant/${enseignantId}`);
            setAvisList(avisRes.data?.avis || []);
            setNoteMoyenne(avisRes.data?.moyenne ?? null);
          } catch {
            setAvisList([]);
            setNoteMoyenne(null);
          }
        }
      } catch { setOffre(null); }
      finally { setIsLoading(false); }
    };
    load();
  }, [id]);

  const handleReservation = async () => {
    if (!dateDebut) {
      toast.error('Veuillez choisir une date de début');
      return;
    }
    setIsSubmitting(true);
    try {
      await api('/marketplace/reservations', {
        method: 'POST',
        body: JSON.stringify({ offre_id: Number(id), date_debut: dateDebut, message: message || undefined }),
      });
      toast.success('Réservation envoyée !');
      navigate('/mes-reservations');
    } catch (err) {
      toast.error(err?.message || 'Erreur lors de la réservation');
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

  if (!offre) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--muted)' }}>
        Offre introuvable
      </div>
    );
  }

  const cardStyle = {
    background: 'var(--surface)', border: '1px solid var(--border)',
    borderRadius: '12px', padding: '1.5rem',
  };

  return (
    <div style={{ minHeight: '100vh', background: 'var(--bg)' }}>
      <div style={{ maxWidth: '64rem', margin: '0 auto', padding: '2rem 1rem' }}>
        <Link to="/marketplace" style={{ fontSize: '0.875rem', color: 'var(--accent)', textDecoration: 'none', marginBottom: '1rem', display: 'inline-block' }}>
          &larr; Retour au marketplace
        </Link>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 340px', gap: '1.5rem' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
            <div style={cardStyle}>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: '1rem', marginBottom: '1rem' }}>
                <div style={{
                  width: '4rem', height: '4rem', borderRadius: '50%',
                  background: 'var(--accent)', opacity: 0.15,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  color: 'var(--accent)', fontWeight: 700, fontSize: '1.25rem', flexShrink: 0,
                }}>
                  <span style={{ opacity: 1 }}>{offre.enseignant?.prenom?.[0]}{offre.enseignant?.nom?.[0]}</span>
                </div>
                <div>
                  <h1 style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text)', margin: 0 }}>
                    {offre.enseignant ? `${offre.enseignant.prenom} ${offre.enseignant.nom}` : 'Centre'}
                  </h1>
                  {offre.enseignant?.experience_annees && (
                    <p style={{ fontSize: '0.875rem', color: 'var(--muted)', margin: '2px 0 0' }}>
                      {offre.enseignant.experience_annees} ans d'expérience
                    </p>
                  )}
                  <div style={{ marginTop: '4px' }}>
                    <StarRating note={noteMoyenne} />
                  </div>
                </div>
              </div>

              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '1rem' }}>
                <span style={{ background: 'var(--accent)', color: '#fff', fontSize: '0.75rem', padding: '2px 10px', borderRadius: '9999px' }}>
                  {offre.matiere?.nom_fr}
                </span>
                <span style={{ background: 'var(--surface2)', color: 'var(--text)', fontSize: '0.75rem', padding: '2px 10px', borderRadius: '9999px' }}>
                  {offre.niveau}
                </span>
                <span style={{ background: 'rgba(6,182,212,0.15)', color: 'var(--teal)', fontSize: '0.75rem', padding: '2px 10px', borderRadius: '9999px' }}>
                  {offre.type_cours === 'en_ligne' ? 'En ligne' : offre.type_cours === 'presentiel' ? 'Présentiel' : 'Mixte'}
                </span>
              </div>

              {offre.description && (
                <div style={{ marginBottom: '1rem' }}>
                  <h3 style={{ fontWeight: 600, color: 'var(--text)', marginBottom: '0.25rem', fontSize: '0.9rem' }}>Description</h3>
                  <p style={{ color: 'var(--text2)', fontSize: '0.875rem', lineHeight: 1.6 }}>{offre.description}</p>
                </div>
              )}

              {offre.wilaya && (
                <p style={{ fontSize: '0.875rem', color: 'var(--muted)' }}>
                  &#128205; {offre.wilaya.nom_fr}{offre.adresse ? ` — ${offre.adresse}` : ''}
                </p>
              )}
            </div>

            <div style={cardStyle}>
              <h2 style={{ fontSize: '1.125rem', fontWeight: 600, color: 'var(--text)', marginBottom: '1rem' }}>
                Avis ({avisList.length})
              </h2>
              {avisList.length === 0 ? (
                <p style={{ color: 'var(--muted)', fontSize: '0.875rem' }}>Aucun avis pour le moment</p>
              ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                  {avisList.map(avis => (
                    <div key={avis.id} style={{ borderBottom: '1px solid var(--border)', paddingBottom: '0.75rem' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                        <span style={{ color: 'var(--orange)', fontSize: '0.875rem' }}>
                          {Array.from({ length: 5 }, (_, i) => i < avis.note ? '★' : '☆').join('')}
                        </span>
                        <span style={{ fontSize: '0.75rem', color: 'var(--muted)' }}>
                          {new Date(avis.created_at).toLocaleDateString('fr-DZ')}
                        </span>
                      </div>
                      {avis.commentaire && (
                        <p style={{ fontSize: '0.875rem', color: 'var(--text2)' }}>{avis.commentaire}</p>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            <div style={cardStyle}>
              <h3 style={{ fontWeight: 600, color: 'var(--text)', marginBottom: '0.75rem', fontSize: '0.95rem' }}>Tarifs</h3>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ fontSize: '0.875rem', color: 'var(--muted)' }}>Séance</span>
                  <span style={{ fontWeight: 700, color: 'var(--accent)' }}>{offre.tarif_seance?.toLocaleString('fr-DZ')} DA</span>
                </div>
                {offre.tarif_mensuel && (
                  <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                    <span style={{ fontSize: '0.875rem', color: 'var(--muted)' }}>Mensuel</span>
                    <span style={{ fontWeight: 700, color: 'var(--accent)' }}>{offre.tarif_mensuel.toLocaleString('fr-DZ')} DA</span>
                  </div>
                )}
              </div>
            </div>

            <div style={cardStyle}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
                <span style={{ fontSize: '0.875rem', color: 'var(--muted)' }}>Places</span>
                <span style={{ fontWeight: 600, color: offre.places_restantes > 0 ? 'var(--green)' : 'var(--red)' }}>
                  {offre.places_restantes > 0 ? `${offre.places_restantes} place${offre.places_restantes > 1 ? 's' : ''}` : 'Complet'}
                </span>
              </div>

              {isAuthenticated ? (
                <>
                  <label style={{ display: 'block', fontSize: '0.8rem', color: 'var(--muted)', marginBottom: '4px' }}>Date de début</label>
                  <input type="date" value={dateDebut}
                    onChange={e => setDateDebut(e.target.value)}
                    min={new Date().toISOString().split('T')[0]}
                    style={{
                      width: '100%', boxSizing: 'border-box', background: 'var(--input-bg)',
                      border: '1px solid var(--input-border)', borderRadius: '8px',
                      color: 'var(--text)', padding: '8px 12px', fontSize: '0.875rem', marginBottom: '0.75rem',
                    }} />

                  <label style={{ display: 'block', fontSize: '0.8rem', color: 'var(--muted)', marginBottom: '4px' }}>Message (optionnel)</label>
                  <textarea value={message} onChange={e => setMessage(e.target.value)}
                    rows={3} placeholder="Un message pour l'enseignant..."
                    style={{
                      width: '100%', boxSizing: 'border-box', background: 'var(--input-bg)',
                      border: '1px solid var(--input-border)', borderRadius: '8px',
                      color: 'var(--text)', padding: '8px 12px', fontSize: '0.875rem', marginBottom: '0.75rem',
                      resize: 'vertical',
                    }} />

                  <button onClick={handleReservation}
                    disabled={isSubmitting || offre.places_restantes <= 0}
                    style={{
                      width: '100%', padding: '10px', borderRadius: '8px', border: 'none', cursor: (isSubmitting || offre.places_restantes <= 0) ? 'not-allowed' : 'pointer',
                      background: (isSubmitting || offre.places_restantes <= 0) ? 'var(--surface2)' : 'var(--accent)',
                      color: (isSubmitting || offre.places_restantes <= 0) ? 'var(--muted)' : '#fff',
                      fontWeight: 600, fontSize: '0.875rem', transition: 'background 0.15s',
                    }}>
                    {isSubmitting ? 'Réservation en cours...' : offre.places_restantes <= 0 ? 'Complet' : 'Réserver'}
                  </button>
                </>
              ) : (
                <Link to="/login" style={{
                  display: 'block', textAlign: 'center', padding: '10px', borderRadius: '8px',
                  border: '1px solid var(--border)', background: 'transparent',
                  color: 'var(--text)', textDecoration: 'none', fontSize: '0.875rem',
                }}>
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
