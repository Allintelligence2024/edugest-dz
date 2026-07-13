import { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

function getScoreBadge(score) {
  if (score >= 70) return { bg: '#EDFAF3', text: '#229A54', label: 'Excellent' };
  if (score >= 40) return { bg: '#FFF8EC', text: '#E08E0B', label: 'Bon' };
  return { bg: '#FDECEA', text: '#C0392B', label: 'Faible' };
}

function CheckIcon({ ok }) {
  return (
    <span style={{ color: ok ? '#229A54' : '#ccc', marginRight: 4 }}>
      {ok ? '✓' : '—'}
    </span>
  );
}

export default function SuggestionRemplacantCard({ seanceId, onSelect }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!seanceId) return;
    setLoading(true);
    api.get(`/remplacements/suggestions/${seanceId}`)
      .then((res) => {
        setData(res.data);
        setError(null);
      })
      .catch((err) => {
        setError(err?.error?.message || 'Erreur de chargement');
        setData(null);
      })
      .finally(() => setLoading(false));
  }, [seanceId]);

  if (loading) {
    return (
      <div style={{ padding: 24, textAlign: 'center', color: '#999' }}>
        Chargement des suggestions...
      </div>
    );
  }

  if (error) {
    return (
      <div style={{ padding: 24, background: '#FDECEA', borderRadius: 12, color: '#C0392B' }}>
        {error}
      </div>
    );
  }

  if (!data?.suggestions?.length) {
    return (
      <div style={{ padding: 24, textAlign: 'center', color: '#999' }}>
        Aucun remplaçant disponible trouvé.
      </div>
    );
  }

  const seance = data.seance;
  const matiere = seance?.cours?.matiere?.nom || seance?.cours?.groupe?.nom || '';
  const heure = seance?.heure_debut?.substring(0, 5) + ' – ' + seance?.heure_fin?.substring(0, 5);

  return (
    <div style={{
      background: 'var(--bg, #fff)',
      border: '1px solid var(--border, #e5e7eb)',
      borderRadius: 12,
      overflow: 'hidden',
    }}>
      <div style={{
        padding: '16px 20px',
        borderBottom: '1px solid var(--border, #e5e7eb)',
        background: 'var(--surface, #f9fafb)',
      }}>
        <div style={{ fontWeight: 700, fontSize: 15, color: 'var(--text, #111)' }}>
          Suggestions de remplaçant
        </div>
        <div style={{ fontSize: 13, color: 'var(--muted, #666)', marginTop: 4 }}>
          {matiere} — {heure}
        </div>
      </div>

      <div style={{ padding: '8px 0' }}>
        {data.suggestions.map((s, i) => {
          const badge = getScoreBadge(s.score);
          return (
            <div
              key={s.id}
              style={{
                display: 'flex',
                alignItems: 'center',
                padding: '12px 20px',
                gap: 12,
                borderBottom: i < data.suggestions.length - 1 ? '1px solid var(--border, #f0f0f0)' : 'none',
              }}
            >
              <div style={{
                width: 36,
                height: 36,
                borderRadius: '50%',
                background: badge.bg,
                color: badge.text,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontWeight: 700,
                fontSize: 14,
                flexShrink: 0,
              }}>
                {s.score}
              </div>

              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 14, color: 'var(--text, #111)' }}>
                  {s.prenom} {s.nom}
                </div>
                <div style={{ fontSize: 12, color: 'var(--muted, #666)', marginTop: 2 }}>
                  {s.specialite || '—'}
                </div>
                <div style={{ fontSize: 12, color: 'var(--muted, #888)', marginTop: 4 }}>
                  <CheckIcon ok={s.matiere_match} /> Spécialité
                  <span style={{ margin: '0 6px' }}>·</span>
                  <CheckIcon ok={s.disponibilite_ok} /> Disponible
                  <span style={{ margin: '0 6px' }}>·</span>
                  <CheckIcon ok={s.experience_groupe} /> Connu du groupe
                </div>
              </div>

              <button
                onClick={() => onSelect?.(s.id)}
                style={{
                  padding: '6px 16px',
                  borderRadius: 8,
                  border: 'none',
                  background: s.disponibilite_ok ? '#1E5EBC' : '#ccc',
                  color: '#fff',
                  fontWeight: 600,
                  fontSize: 13,
                  cursor: s.disponibilite_ok ? 'pointer' : 'not-allowed',
                  opacity: s.disponibilite_ok ? 1 : 0.5,
                  flexShrink: 0,
                }}
                disabled={!s.disponibilite_ok}
              >
                Choisir
              </button>
            </div>
          );
        })}
      </div>
    </div>
  );
}
