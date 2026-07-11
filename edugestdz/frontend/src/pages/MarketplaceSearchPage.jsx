import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api } from '@api/client';

const TYPE_COURS_OPTIONS = [
  { value: 'presentiel', label: 'Présentiel' },
  { value: 'en_ligne', label: 'En ligne' },
  { value: 'les_deux', label: 'Les deux' },
];

const NIVEAUX = ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS','universitaire'];

function StarRating({ note }) {
  if (!note) return <span style={{ color: 'var(--muted)', fontSize: '0.875rem' }}>—</span>;
  const full = Math.floor(note);
  const stars = Array.from({ length: 5 }, (_, i) => i < full ? '★' : '☆');
  return <span style={{ color: 'var(--orange)', fontSize: '0.875rem' }}>{stars.join('')} {note.toFixed(1)}</span>;
}

export default function MarketplaceSearchPage() {
  const [offres, setOffres] = useState([]);
  const [meta, setMeta] = useState({ total: 0, last_page: 1 });
  const [isLoading, setIsLoading] = useState(false);
  const [filters, setFilters] = useState({
    wilaya_id: '', matiere_id: '', niveau: '',
    tarif_min: '', tarif_max: '', type_cours: '', q: '',
  });
  const [page, setPage] = useState(1);
  const [matieres, setMatieres] = useState([]);
  const [wilayas, setWilayas] = useState([]);

  useEffect(() => {
    const loadRefs = async () => {
      try {
        const [matRes, wilRes] = await Promise.all([
          api('/matieres?per_page=200'),
          api('/parametres/wilayas'),
        ]);
        setMatieres(matRes.data || []);
        setWilayas(wilRes.data || []);
      } catch { /* ignore */ }
    };
    loadRefs();
  }, []);

  const loadOffres = useCallback(async () => {
    setIsLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '12' });
      Object.entries(filters).forEach(([k, v]) => { if (v) params.set(k, v); });
      const res = await api(`/marketplace/offres?${params.toString()}`);
      setOffres(res.data || []);
      setMeta(res.meta || { total: 0, last_page: 1 });
    } catch { setOffres([]); }
    finally { setIsLoading(false); }
  }, [page, filters]);

  useEffect(() => { loadOffres(); }, [loadOffres]);

  const handleFilterChange = (key, value) => {
    setFilters(f => ({ ...f, [key]: value }));
    setPage(1);
  };

  const inputStyle = {
    background: 'var(--input-bg)', border: '1px solid var(--input-border)',
    borderRadius: '8px', color: 'var(--text)', padding: '8px 12px', fontSize: '0.875rem',
  };

  return (
    <div style={{ minHeight: '100vh', background: 'var(--bg)' }}>
      <div style={{ maxWidth: '80rem', margin: '0 auto', padding: '2rem 1rem' }}>
        <div style={{ marginBottom: '2rem' }}>
          <h1 style={{ fontSize: '1.875rem', fontWeight: 700, color: 'var(--text)' }}>Marketplace</h1>
          <p style={{ color: 'var(--muted)', marginTop: '0.25rem' }}>Trouvez le cours particulier idéal</p>
        </div>

        <div style={{
          background: 'var(--surface)', border: '1px solid var(--border)',
          borderRadius: '12px', padding: '1.25rem', marginBottom: '2rem',
        }}>
          <input
            type="text"
            value={filters.q}
            onChange={e => handleFilterChange('q', e.target.value)}
            placeholder="Rechercher une matière, un niveau..."
            style={{ ...inputStyle, width: '100%', marginBottom: '1rem', boxSizing: 'border-box' }}
          />
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))', gap: '0.75rem' }}>
            <select value={filters.matiere_id} onChange={e => handleFilterChange('matiere_id', e.target.value)}
              style={inputStyle}>
              <option value="">Matière</option>
              {matieres.map(m => <option key={m.id} value={m.id}>{m.nom_fr}</option>)}
            </select>
            <select value={filters.niveau} onChange={e => handleFilterChange('niveau', e.target.value)}
              style={inputStyle}>
              <option value="">Niveau</option>
              {NIVEAUX.map(n => <option key={n} value={n}>{n}</option>)}
            </select>
            <select value={filters.wilaya_id} onChange={e => handleFilterChange('wilaya_id', e.target.value)}
              style={inputStyle}>
              <option value="">Wilaya</option>
              {wilayas.map(w => <option key={w.id} value={w.id}>{w.nom_fr}</option>)}
            </select>
            <select value={filters.type_cours} onChange={e => handleFilterChange('type_cours', e.target.value)}
              style={inputStyle}>
              <option value="">Type</option>
              {TYPE_COURS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
            <input type="number" placeholder="Tarif min" value={filters.tarif_min}
              onChange={e => handleFilterChange('tarif_min', e.target.value)}
              style={inputStyle} />
            <input type="number" placeholder="Tarif max" value={filters.tarif_max}
              onChange={e => handleFilterChange('tarif_max', e.target.value)}
              style={inputStyle} />
          </div>
        </div>

        {isLoading ? (
          <div style={{ display: 'flex', justifyContent: 'center', padding: '4rem 0' }}>
            <div style={{
              width: '2.5rem', height: '2.5rem', border: '4px solid var(--accent)',
              borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 1s linear infinite',
            }} />
          </div>
        ) : offres.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '4rem 0', color: 'var(--muted)' }}>
            <p style={{ fontSize: '3rem', marginBottom: '0.75rem' }}>&#128269;</p>
            <p style={{ fontSize: '1.125rem' }}>Aucune offre trouvée</p>
          </div>
        ) : (
          <>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '1.25rem' }}>
              {offres.map(offre => (
                <Link key={offre.id} to={`/marketplace/offres/${offre.id}`}
                  style={{
                    background: 'var(--surface)', border: '1px solid var(--border)',
                    borderRadius: '12px', padding: '1.25rem', display: 'block',
                    textDecoration: 'none', transition: 'border-color 0.2s, box-shadow 0.2s',
                  }}
                  onMouseEnter={e => { e.currentTarget.style.borderColor = 'var(--accent)'; e.currentTarget.style.boxShadow = 'var(--glow)'; }}
                  onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--border)'; e.currentTarget.style.boxShadow = 'none'; }}
                >
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '0.75rem', marginBottom: '0.75rem' }}>
                    <div style={{
                      width: '3rem', height: '3rem', borderRadius: '50%',
                      background: 'var(--accent)', opacity: 0.15,
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                      color: 'var(--accent)', fontWeight: 700, fontSize: '1rem', flexShrink: 0,
                    }}>
                      <span style={{ opacity: 1 }}>{offre.enseignant?.prenom?.[0]}{offre.enseignant?.nom?.[0]}</span>
                    </div>
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <p style={{ fontWeight: 600, color: 'var(--text)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {offre.enseignant ? `${offre.enseignant.prenom} ${offre.enseignant.nom}` : 'Centre'}
                      </p>
                      <p style={{ fontSize: '0.875rem', color: 'var(--muted)' }}>{offre.matiere?.nom_fr} — {offre.niveau}</p>
                    </div>
                  </div>

                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.5rem' }}>
                    <span style={{
                      background: 'var(--accent)', color: '#fff', fontSize: '0.75rem',
                      padding: '2px 8px', borderRadius: '9999px', fontWeight: 500,
                    }}>
                      {offre.type_cours === 'en_ligne' ? 'En ligne' : offre.type_cours === 'presentiel' ? 'Présentiel' : 'Mixte'}
                    </span>
                    {offre.wilaya && <span style={{ fontSize: '0.75rem', color: 'var(--muted)' }}>&#128205; {offre.wilaya.nom_fr}</span>}
                  </div>

                  <p style={{ fontSize: '0.875rem', color: 'var(--text2)', overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', marginBottom: '0.75rem' }}>
                    {offre.description}
                  </p>

                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div>
                      <span style={{ fontSize: '1.125rem', fontWeight: 700, color: 'var(--accent)' }}>
                        {offre.tarif_seance?.toLocaleString('fr-DZ')} DA
                      </span>
                      <span style={{ fontSize: '0.75rem', color: 'var(--muted)' }}> / séance</span>
                    </div>
                    <div>
                      {offre.places_restantes > 0 ? (
                        <span style={{
                          background: 'rgba(16,185,129,0.15)', color: 'var(--green)',
                          fontSize: '0.75rem', padding: '2px 8px', borderRadius: '9999px',
                        }}>
                          {offre.places_restantes} place{offre.places_restantes > 1 ? 's' : ''}
                        </span>
                      ) : (
                        <span style={{
                          background: 'rgba(239,68,68,0.15)', color: 'var(--red)',
                          fontSize: '0.75rem', padding: '2px 8px', borderRadius: '9999px',
                        }}>Complet</span>
                      )}
                    </div>
                  </div>
                </Link>
              ))}
            </div>
            {meta.last_page > 1 && (
              <div style={{ display: 'flex', justifyContent: 'center', gap: '0.5rem', marginTop: '1.5rem' }}>
                {Array.from({ length: meta.last_page }, (_, i) => i + 1).map(p => (
                  <button key={p} onClick={() => setPage(p)}
                    style={{
                      padding: '6px 14px', borderRadius: '8px', border: 'none', cursor: 'pointer',
                      background: p === page ? 'var(--accent)' : 'var(--surface)',
                      color: p === page ? '#fff' : 'var(--text)',
                      fontWeight: p === page ? 600 : 400,
                      transition: 'all 0.15s',
                    }}>
                    {p}
                  </button>
                ))}
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
