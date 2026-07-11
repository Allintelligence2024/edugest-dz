import React, { useState, useEffect, useCallback } from 'react';
import api from '@api/axiosInstance';

const STATUT_COLORS = {
  a_faire: { bg: '#2563EB18', text: '#2563EB', label: 'À faire' },
  en_cours: { bg: '#F59E0B18', text: '#F59E0B', label: 'En cours' },
  rendu: { bg: '#10B98118', text: '#10B981', label: 'Rendu' },
  corrige: { bg: '#7C3AED18', text: '#7C3AED', label: 'Corrigé' },
  en_retard: { bg: '#EF444418', text: '#EF4444', label: 'En retard' },
};

export default function DevoirsPage() {
  const [devoirs, setDevoirs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filtreStatut, setFiltreStatut] = useState('tous');

  const fetchDevoirs = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get('/devoirs', { params: { per_page: 50 } });
      setDevoirs(res.data || []);
    } catch {
      setDevoirs([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchDevoirs(); }, [fetchDevoirs]);

  const devoirsFiltres = devoirs.filter(d =>
    filtreStatut === 'tous' || d.statut === filtreStatut
  );

  const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('fr-DZ', { day: '2-digit', month: 'short', year: 'numeric' });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-10 h-10 border-3 border-border rounded-full animate-spin" style={{ borderTopColor: 'var(--accent)' }} />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-text">Devoirs</h1>
          <p className="text-muted text-sm mt-1">{devoirs.length} devoir{devoirs.length > 1 ? 's' : ''}</p>
        </div>
      </div>

      <div className="flex gap-2 flex-wrap">
        {['tous', ...Object.keys(STATUT_COLORS)].map(statut => (
          <button
            key={statut}
            onClick={() => setFiltreStatut(statut)}
            className="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
            style={{
              background: filtreStatut === statut ? 'var(--accent)' : 'var(--surface2)',
              color: filtreStatut === statut ? '#fff' : 'var(--text2)',
              border: `1px solid ${filtreStatut === statut ? 'var(--accent)' : 'var(--border)'}`,
            }}
          >
            {statut === 'tous' ? 'Tous' : (STATUT_COLORS[statut]?.label || statut)}
          </button>
        ))}
      </div>

      {devoirsFiltres.length === 0 ? (
        <div className="bg-surface rounded-2xl border border-border p-12 text-center">
          <div className="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center" style={{ background: 'var(--accent)18' }}>
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" strokeWidth="1.5">
              <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <p className="text-muted text-sm">Aucun devoir pour le moment.</p>
        </div>
      ) : (
        <div className="space-y-2">
          {devoirsFiltres.map(devoir => {
            const statutCfg = STATUT_COLORS[devoir.statut] || STATUT_COLORS.a_faire;
            return (
              <div
                key={devoir.id}
                className="p-4 rounded-xl border transition-all duration-150 hover:shadow-md"
                style={{ background: 'var(--surface)', borderColor: 'var(--border)' }}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <h3 className="text-sm font-bold text-text truncate">{devoir.titre || devoir.sujet || 'Devoir'}</h3>
                      <span
                        className="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0"
                        style={{ background: statutCfg.bg, color: statutCfg.text }}
                      >
                        {statutCfg.label}
                      </span>
                    </div>
                    <p className="text-xs text-muted line-clamp-2 mb-2">{devoir.description || devoir.consigne || ''}</p>
                    <div className="flex items-center gap-3 text-[10px] text-muted2">
                      {devoir.matiere && <span>Matière: {devoir.matiere}</span>}
                      {devoir.enseignant && <span>Enseignant: {devoir.enseignant}</span>}
                      {devoir.date_rendu && <span>Échéance: {formatDate(devoir.date_rendu)}</span>}
                      {devoir.note && <span>Note: {devoir.note}/20</span>}
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
