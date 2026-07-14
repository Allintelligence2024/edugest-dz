import { useState, useEffect } from 'react';
import { Cpu, RefreshCw, TrendingDown, AlertTriangle, Users, Activity, Brain, ChevronDown } from 'lucide-react';

const api = (path, opts) => fetch(`/api/v1${path}`, {
  headers: {
    Authorization: `Bearer ${localStorage.getItem('access_token')}`,
    'X-Tenant-ID': localStorage.getItem('tenantId') ?? '',
    ...opts?.headers,
  },
  ...opts,
}).then(r => r.json());

const RISQUE_COLORS = {
  critique: { color: '#ef4444', bg: '#450a0a', border: '#b91c1c', label: 'CRITIQUE' },
  eleve:    { color: '#f87171', bg: '#350808', border: '#991b1b', label: 'ÉLEVÉ' },
  modere:   { color: '#fb923c', bg: '#1f1008', border: '#c2410c', label: 'MODÉRÉ' },
  faible:   { color: '#4ade80', bg: '#0d2515', border: '#16a34a', label: 'FAIBLE' },
};

const RISQUE_ICONS = {
  critique: '🚨',
  eleve:    '🔴',
  modere:   '⚠️',
  faible:   '✅',
};

export default function PredictionIAPage() {
  const [stats, setStats]           = useState(null);
  const [predictions, setPredictions] = useState([]);
  const [loading, setLoading]       = useState(true);
  const [selected, setSelected]     = useState(null);
  const [detail, setDetail]         = useState(null);
  const [tab, setTab]               = useState('dashboard');
  const [calculating, setCalculating] = useState(false);
  const [filtreNiveau, setFiltreNiveau] = useState('');

  useEffect(() => { loadData(); }, [filtreNiveau]);

  const loadData = async () => {
    setLoading(true);
    try {
      const params = filtreNiveau ? `?niveau=${filtreNiveau}` : '';
      const res = await api(`/ia/prediction/classement${params}`);
      setStats(res?.data?.stats ?? []);
      setPredictions(res?.data?.predictions ?? []);
    } catch (e) {
      console.error('Erreur chargement prédictions', e);
    }
    setLoading(false);
  };

  const voirDetail = async (eleveId) => {
    setSelected(eleveId);
    setTab('detail');
    try {
      const res = await api(`/ia/prediction/eleve/${eleveId}`);
      setDetail(res?.data);
    } catch (e) {
      console.error('Erreur détail prédiction', e);
    }
  };

  const recalculerTous = async () => {
    setCalculating(true);
    try {
      await api('/ia/prediction/tout', { method: 'POST' });
      await loadData();
    } catch (e) {
      console.error('Erreur recalcul', e);
    }
    setCalculating(false);
  };

  const statsMap = {};
  (stats || []).forEach(s => { statsMap[s.niveau_risque] = s; });

  const totalPred = (stats || []).reduce((a, s) => a + parseInt(s.total || 0), 0);

  const StatBox = ({ label, value, color, icon: Icon }) => (
    <div style={{
      background: '#111318', border: '1px solid #1e293b', borderRadius: '10px',
      padding: '14px', textAlign: 'center',
    }}>
      <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '6px' }}>
        <Icon size={18} color={color} />
      </div>
      <div style={{ fontSize: '26px', fontWeight: 900, color }}>{value ?? 0}</div>
      <div style={{ fontSize: '9px', color: '#64748b', marginTop: '2px', textTransform: 'uppercase', letterSpacing: '1px' }}>
        {label}
      </div>
    </div>
  );

  const PredictionCard = ({ p }) => {
    const r = RISQUE_COLORS[p.niveau_risque] ?? RISQUE_COLORS.faible;
    const proba = parseFloat(p.probabilite_echec || 0);
    const confiance = parseFloat(p.confiance || 0);

    return (
      <div style={{
        background: r.bg, border: `1px solid ${r.border}`, borderRadius: '10px',
        padding: '12px 16px', marginBottom: '8px',
        display: 'flex', alignItems: 'center', gap: '12px',
      }}>
        <div style={{ fontSize: '22px' }}>{RISQUE_ICONS[p.niveau_risque] ?? '❓'}</div>
        <div style={{ flex: 1 }}>
          <div style={{ fontWeight: 800, fontSize: '13px', color: r.color }}>
            {p.prenom} {p.nom}
            <span style={{
              fontSize: '9px', background: r.color + '22', color: r.color,
              padding: '1px 6px', borderRadius: '20px', marginLeft: '8px', fontWeight: 700,
            }}>
              {r.label}
            </span>
          </div>
          <div style={{ fontSize: '10px', color: '#64748b', marginTop: '2px' }}>
            {p.niveau_scolaire} · Horizon : {p.horizon?.replace('_', ' ')}
          </div>
        </div>
        <div style={{ textAlign: 'right' }}>
          <div style={{ fontSize: '20px', fontWeight: 900, color: r.color }}>
            {proba.toFixed(0)}%
          </div>
          <div style={{ fontSize: '9px', color: '#64748b' }}>
            Confiance {confiance.toFixed(0)}%
          </div>
        </div>
        <button onClick={() => voirDetail(p.eleve_id)}
          style={{
            background: '#1e293b', color: '#60a5fa', border: 'none',
            borderRadius: '6px', padding: '5px 10px', fontSize: '10px', cursor: 'pointer', fontWeight: 700,
          }}>
          Détail
        </button>
      </div>
    );
  };

  return (
    <div style={{ padding: '24px', background: '#08090f', minHeight: '100vh' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
        <div>
          <h1 style={{ fontSize: '22px', fontWeight: 900, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <Brain size={24} color="#7C3AED" />
            Prédiction IA — Échec Scolaire
          </h1>
          <p style={{ fontSize: '12px', color: '#64748b' }}>
            Modèle logistique v1 · Analyse prédictive multi-signaux
          </p>
        </div>
        <button onClick={recalculerTous} disabled={calculating} style={{
          background: calculating ? '#1e293b' : 'linear-gradient(135deg,#7C3AED,#2563EB)',
          color: '#fff', border: 'none', borderRadius: '8px', padding: '10px 16px',
          fontSize: '12px', fontWeight: 700, cursor: 'pointer',
          display: 'flex', alignItems: 'center', gap: '6px',
        }}>
          <RefreshCw size={13} className={calculating ? 'animate-spin' : ''} />
          {calculating ? 'Calcul en cours...' : 'Recalculer tout'}
        </button>
      </div>

      {/* Tabs */}
      <div style={{ display: 'flex', gap: '4px', marginBottom: '20px' }}>
        {[
          ['dashboard', '📊 Tableau de bord'],
          ['classement', '🏆 Classement risque'],
          ['faible', '✅ Risque faible'],
          ['modere', '⚠️ Risque modéré'],
          ['eleve', '🔴 Risque élevé'],
          ['critique', '🚨 Critique'],
          ...(detail ? [['detail', '🔍 Détail élève']] : []),
        ].map(([id, label]) => (
          <button key={id} onClick={() => {
            if (id === 'faible' || id === 'modere' || id === 'eleve' || id === 'critique') {
              setFiltreNiveau(id);
              setTab('classement');
            } else {
              setFiltreNiveau('');
              setTab(id);
            }
            if (id === 'detail') setTab('detail');
          }} style={{
            background: tab === id || (tab === 'classement' && filtreNiveau === id) ? '#1e3a5f' : '#111318',
            color: tab === id || (tab === 'classement' && filtreNiveau === id) ? '#60a5fa' : '#64748b',
            border: `1px solid ${tab === id || (tab === 'classement' && filtreNiveau === id) ? '#3b82f6' : '#1e293b'}`,
            borderRadius: '8px', padding: '8px 14px', fontSize: '11px',
            fontWeight: 700, cursor: 'pointer',
          }}>{label}</button>
        ))}
      </div>

      {/* Loading */}
      {loading && (
        <div style={{ textAlign: 'center', padding: '60px 0' }}>
          <div style={{ width: '36px', height: '36px', border: '3px solid #1e293b', borderTopColor: '#7C3AED',
            borderRadius: '50%', margin: '0 auto', animation: 'spin 1s linear infinite' }} />
          <div style={{ color: '#64748b', fontSize: '12px', marginTop: '12px' }}>Chargement des prédictions...</div>
        </div>
      )}

      {/* Dashboard Tab */}
      {tab === 'dashboard' && !loading && (
        <div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: '10px', marginBottom: '24px' }}>
            <StatBox label="Total prédit" value={totalPred} color="#7C3AED" icon={Brain} />
            <StatBox label="Critiques" value={statsMap.critique?.total ?? 0} color="#ef4444" icon={AlertTriangle} />
            <StatBox label="Risque élevé" value={statsMap.eleve?.total ?? 0} color="#f87171" icon={TrendingDown} />
            <StatBox label="Risque modéré" value={statsMap.modere?.total ?? 0} color="#fb923c" icon={Activity} />
          </div>

          {/* Probabilité moyenne par niveau */}
          <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '10px', padding: '20px', marginBottom: '20px' }}>
            <div style={{ fontSize: '13px', fontWeight: 800, color: '#fff', marginBottom: '16px' }}>
              Probabilité moyenne par niveau de risque
            </div>
            <div style={{ display: 'flex', gap: '12px' }}>
              {['faible', 'modere', 'eleve', 'critique'].map(niv => {
                const s = statsMap[niv];
                const r = RISQUE_COLORS[niv];
                const proba = s ? parseFloat(s.proba_moyenne || 0).toFixed(0) : '—';
                return (
                  <div key={niv} style={{
                    flex: 1, background: r.bg, border: `1px solid ${r.border}`,
                    borderRadius: '8px', padding: '12px', textAlign: 'center',
                  }}>
                    <div style={{ fontSize: '9px', color: r.color, fontWeight: 700, textTransform: 'uppercase' }}>{r.label}</div>
                    <div style={{ fontSize: '22px', fontWeight: 900, color: r.color, margin: '4px 0' }}>{proba}%</div>
                    <div style={{ fontSize: '9px', color: '#64748b' }}>{s?.total ?? 0} élèves</div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Top 5 critiques */}
          <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '10px', padding: '20px' }}>
            <div style={{ fontSize: '13px', fontWeight: 800, color: '#fff', marginBottom: '12px' }}>
              🚨 Top 5 élèves les plus à risque
            </div>
            {predictions.slice(0, 5).map(p => (
              <PredictionCard key={p.eleve_id} p={p} />
            ))}
            {predictions.length === 0 && (
              <div style={{ color: '#64748b', fontSize: '12px', textAlign: 'center', padding: '20px' }}>
                Aucune prédiction disponible
              </div>
            )}
          </div>
        </div>
      )}

      {/* Classement Tab */}
      {tab === 'classement' && !loading && (
        <div>
          {filtreNiveau && (
            <div style={{ fontSize: '11px', color: '#64748b', marginBottom: '12px' }}>
              Filtré par : <span style={{ color: RISQUE_COLORS[filtreNiveau]?.color, fontWeight: 700 }}>
                {RISQUE_COLORS[filtreNiveau]?.label}
              </span>
            </div>
          )}
          {predictions.map(p => (
            <PredictionCard key={p.eleve_id} p={p} />
          ))}
          {predictions.length === 0 && (
            <div style={{ color: '#64748b', fontSize: '12px', textAlign: 'center', padding: '40px' }}>
              Aucune prédiction pour ce niveau
            </div>
          )}
        </div>
      )}

      {/* Detail Tab */}
      {tab === 'detail' && detail && (
        <div>
          <button onClick={() => { setTab('classement'); setDetail(null); }}
            style={{
              background: '#1e293b', color: '#60a5fa', border: 'none',
              borderRadius: '6px', padding: '6px 12px', fontSize: '11px', cursor: 'pointer', marginBottom: '16px',
            }}>
            ← Retour au classement
          </button>

          {/* Élève info */}
          <div style={{
            background: '#111318', border: '1px solid #1e293b', borderRadius: '10px',
            padding: '20px', marginBottom: '16px',
          }}>
            <div style={{ fontSize: '16px', fontWeight: 900, color: '#fff' }}>
              {detail.eleve?.prenom} {detail.eleve?.nom}
            </div>
            <div style={{ fontSize: '12px', color: '#64748b' }}>
              {detail.eleve?.niveau_scolaire}
            </div>
          </div>

          {/* Prédiction */}
          <div style={{
            background: RISQUE_COLORS[detail.prediction?.niveau_risque]?.bg || '#111318',
            border: `1px solid ${RISQUE_COLORS[detail.prediction?.niveau_risque]?.border || '#1e293b'}`,
            borderRadius: '10px', padding: '20px', marginBottom: '16px',
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '12px' }}>
              <Cpu size={24} color={RISQUE_COLORS[detail.prediction?.niveau_risque]?.color} />
              <div>
                <div style={{ fontSize: '14px', fontWeight: 900, color: RISQUE_COLORS[detail.prediction?.niveau_risque]?.color }}>
                  Probabilité : {detail.prediction?.probabilite}%
                </div>
                <div style={{ fontSize: '10px', color: '#64748b' }}>
                  Confiance : {detail.prediction?.confiance}% · Moteur : {detail.prediction?.moteur}
                </div>
              </div>
              <div style={{
                background: RISQUE_COLORS[detail.prediction?.niveau_risque]?.color + '22',
                color: RISQUE_COLORS[detail.prediction?.niveau_risque]?.color,
                padding: '4px 12px', borderRadius: '20px', fontWeight: 800, fontSize: '11px',
              }}>
                {RISQUE_COLORS[detail.prediction?.niveau_risque]?.label}
              </div>
            </div>

            <div style={{ fontSize: '11px', color: '#94a3b8', marginBottom: '12px' }}>
              {detail.prediction?.resume}
            </div>

            {/* Facteurs de risque */}
            {detail.prediction?.facteurs_risque?.length > 0 && (
              <div style={{ marginTop: '12px' }}>
                <div style={{ fontSize: '11px', fontWeight: 700, color: '#fff', marginBottom: '8px' }}>Facteurs de risque :</div>
                {detail.prediction.facteurs_risque.map((f, i) => (
                  <div key={i} style={{
                    display: 'flex', alignItems: 'center', gap: '8px', padding: '6px 0',
                    borderBottom: i < detail.prediction.facteurs_risque.length - 1 ? '1px solid #1e293b' : 'none',
                  }}>
                    <span style={{ fontSize: '14px' }}>{f.icone}</span>
                    <span style={{ fontSize: '11px', color: '#94a3b8', flex: 1 }}>{f.label}</span>
                    <span style={{ fontSize: '9px', color: '#64748b' }}>Poids: {(f.poids * 100).toFixed(0)}%</span>
                  </div>
                ))}
              </div>
            )}

            {/* Recommandations */}
            {detail.prediction?.recommandations?.length > 0 && (
              <div style={{ marginTop: '16px' }}>
                <div style={{ fontSize: '11px', fontWeight: 700, color: '#fff', marginBottom: '8px' }}>Recommandations :</div>
                {detail.prediction.recommandations.map((rec, i) => (
                  <div key={i} style={{
                    background: '#0d1117', border: '1px solid #1e293b', borderRadius: '6px',
                    padding: '8px 12px', marginBottom: '6px',
                    display: 'flex', alignItems: 'center', gap: '8px',
                  }}>
                    <span style={{
                      fontSize: '9px', fontWeight: 700, padding: '2px 6px', borderRadius: '10px',
                      background: rec.urgence === 'immediate' ? '#ef444422' : rec.urgence === 'urgent' ? '#fb923c22' : '#3b82f622',
                      color: rec.urgence === 'immediate' ? '#ef4444' : rec.urgence === 'urgent' ? '#fb923c' : '#60a5fa',
                    }}>
                      {rec.urgence?.toUpperCase()}
                    </span>
                    <span style={{ fontSize: '11px', color: '#94a3b8', flex: 1 }}>{rec.label}</span>
                    <span style={{ fontSize: '9px', color: '#64748b' }}>{rec.delai}</span>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Profil apprentissage */}
          {detail.profil_apprentissage && (
            <div style={{
              background: '#111318', border: '1px solid #1e293b', borderRadius: '10px',
              padding: '20px', marginBottom: '16px',
            }}>
              <div style={{ fontSize: '13px', fontWeight: 800, color: '#fff', marginBottom: '12px' }}>
                {detail.profil_apprentissage.emoji} Profil : {detail.profil_apprentissage.label_fr}
              </div>
              <div style={{ fontSize: '11px', color: '#94a3b8', marginBottom: '12px' }}>
                {detail.profil_apprentissage.explication}
              </div>
              {detail.profil_apprentissage.points_forts?.length > 0 && (
                <div style={{ fontSize: '10px', color: '#4ade80', marginBottom: '4px' }}>
                  Points forts : {detail.profil_apprentissage.points_forts.join(', ')}
                </div>
              )}
              {detail.profil_apprentissage.points_faibles?.length > 0 && (
                <div style={{ fontSize: '10px', color: '#f87171' }}>
                  Points faibles : {detail.profil_apprentissage.points_faibles.join(', ')}
                </div>
              )}
              <div style={{ fontSize: '10px', color: '#64748b', marginTop: '8px' }}>
                Stabilité : {detail.profil_apprentissage.stabilite}/100
              </div>
            </div>
          )}

          {/* Historique */}
          {detail.historique?.length > 0 && (
            <div style={{
              background: '#111318', border: '1px solid #1e293b', borderRadius: '10px',
              padding: '20px',
            }}>
              <div style={{ fontSize: '13px', fontWeight: 800, color: '#fff', marginBottom: '12px' }}>
                Historique des prédictions
              </div>
              <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                {detail.historique.map((h, i) => {
                  const r = RISQUE_COLORS[h.niveau_risque] ?? RISQUE_COLORS.faible;
                  return (
                    <div key={i} style={{
                      background: r.bg, border: `1px solid ${r.border}`,
                      borderRadius: '6px', padding: '8px 10px', textAlign: 'center', minWidth: '80px',
                    }}>
                      <div style={{ fontSize: '14px', fontWeight: 900, color: r.color }}>
                        {parseFloat(h.probabilite_echec).toFixed(0)}%
                      </div>
                      <div style={{ fontSize: '8px', color: '#64748b' }}>
                        {new Date(h.created_at).toLocaleDateString('fr-FR')}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
