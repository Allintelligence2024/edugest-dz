import { useState, useEffect } from 'react';
import { AlertTriangle, Star, TrendingDown, TrendingUp, Users, RefreshCw, BookOpen, Phone } from 'lucide-react';

const BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/api\/v1\/?$/, '');
const api = (path) => fetch(`${BASE_URL}/api/v1${path}`, {
  headers: {
    Authorization: `Bearer ${localStorage.getItem('access_token')}`,
    'X-Tenant-ID': localStorage.getItem('tenantId') ?? '',
  },
}).then(r => r.json());

const post = (path, body) => fetch(`${BASE_URL}/api/v1${path}`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${localStorage.getItem('access_token')}`,
    'Content-Type': 'application/json',
    'X-Tenant-ID': localStorage.getItem('tenantId') ?? '',
  },
  body: JSON.stringify(body),
}).then(r => r.json());

const NIVEAUX = {
  critique:  { color: '#ef4444', bg: '#450a0a', border: '#b91c1c', emoji: '🚨', label: 'CRITIQUE' },
  danger:    { color: '#f87171', bg: '#350808', border: '#991b1b', emoji: '🔴', label: 'DANGER' },
  vigilance: { color: '#fb923c', bg: '#1f1008', border: '#c2410c', emoji: '⚠️', label: 'VIGILANCE' },
  normal:    { color: '#60a5fa', bg: '#0c1a30', border: '#1d4ed8', emoji: '✅', label: 'NORMAL' },
  excellent: { color: '#4ade80', bg: '#0d2515', border: '#16a34a', emoji: '⭐', label: 'EXCELLENT' },
};

export default function DiagnosticPage() {
  const [dashboard, setDashboard] = useState(null);
  const [eleves, setEleves]       = useState([]);
  const [loading, setLoading]     = useState(true);
  const [filtreNiveau, setFiltreNiveau] = useState('');
  const [filtreAction, setFiltreAction] = useState('');
  const [selected, setSelected]   = useState(null);
  const [detail, setDetail]       = useState(null);
  const [analysing, setAnalysing] = useState(false);
  const [tab, setTab]             = useState('dashboard');

  useEffect(() => { loadData(); }, [filtreNiveau, filtreAction]);

  const loadData = async () => {
    setLoading(true);
    const params = new URLSearchParams();
    if (filtreNiveau) params.append('niveau', filtreNiveau);
    if (filtreAction) params.append('action', filtreAction);

    const [dash, elvs] = await Promise.all([
      api('/diagnostic/dashboard'),
      api(`/diagnostic/eleves?${params}&per_page=50`),
    ]);
    setDashboard(dash?.data);
    setEleves(elvs?.data?.data ?? []);
    setLoading(false);
  };

  const voirDetail = async (eleveId) => {
    setSelected(eleveId);
    const res = await api(`/diagnostic/eleves/${eleveId}`);
    setDetail(res?.data);
    setTab('detail');
  };

  const analyserTous = async () => {
    setAnalysing(true);
    const res = await post('/diagnostic/analyser-tous', {});
    alert(`✅ Analyse terminée : ${res?.data?.total} élèves analysés`);
    setAnalysing(false);
    loadData();
  };

  const convoquer = async (eleveId) => {
    const msg = prompt('Message de convocation (sera envoyé par SMS) :',
      "Nous vous prions de bien vouloir vous présenter à l'établissement pour discuter du niveau académique de votre enfant.");
    if (!msg) return;
    const res = await post('/diagnostic/convocations', {
      eleve_id: eleveId, motif: 'niveau_critique', message: msg, canal: 'sms',
    });
    alert(res?.message ?? 'Convocation envoyée');
  };

  const StatBox = ({ label, value, color, onClick }) => (
    <div onClick={onClick} style={{
      background: '#111318', border: '1px solid #1e293b', borderRadius: '10px',
      padding: '14px', textAlign: 'center', cursor: onClick ? 'pointer' : 'default',
      transition: 'border-color .2s',
    }}
      onMouseEnter={e => onClick && (e.currentTarget.style.borderColor = color)}
      onMouseLeave={e => onClick && (e.currentTarget.style.borderColor = '#1e293b')}
    >
      <div style={{ fontSize: '26px', fontWeight: 900, color }}>{value ?? 0}</div>
      <div style={{ fontSize: '9px', color: '#64748b', marginTop: '2px', textTransform: 'uppercase', letterSpacing: '1px' }}>
        {label}
      </div>
    </div>
  );

  const EleveCard = ({ d }) => {
    const n = NIVEAUX[d.niveau_global] ?? NIVEAUX.normal;
    return (
      <div style={{
        background: n.bg, border: `1px solid ${n.border}`, borderRadius: '10px',
        padding: '12px 16px', marginBottom: '8px',
        display: 'flex', alignItems: 'center', gap: '12px',
      }}>
        <div style={{ fontSize: '22px' }}>{n.emoji}</div>
        <div style={{ flex: 1 }}>
          <div style={{ fontWeight: 800, fontSize: '13px', color: n.color }}>
            {d.eleve?.prenom} {d.eleve?.nom}
            <span style={{ fontSize: '9px', background: n.color + '22', color: n.color,
              padding: '1px 6px', borderRadius: '20px', marginLeft: '8px', fontWeight: 700 }}>
              {n.label}
            </span>
          </div>
          <div style={{ fontSize: '10px', color: '#64748b' }}>
            {d.eleve?.niveau_scolaire} · Moyenne : {d.moyenne_generale ?? '—'}/20
            {d.tendance !== null && (
              <span style={{ color: d.tendance < 0 ? '#f87171' : '#4ade80', marginLeft: '8px' }}>
                {d.tendance > 0 ? '↑' : '↓'} {Math.abs(d.tendance)}pts
              </span>
            )}
          </div>
          {d.matieres_en_danger?.length > 0 && (
            <div style={{ fontSize: '9px', color: '#f87171', marginTop: '2px' }}>
              Difficultés : {d.matieres_en_danger.map(m => m.matiere).join(', ')}
            </div>
          )}
        </div>
        <div style={{ display: 'flex', gap: '6px' }}>
          <button onClick={() => voirDetail(d.eleve_id)}
            style={{ background: '#1e293b', color: '#60a5fa', border: 'none',
              borderRadius: '6px', padding: '5px 10px', fontSize: '10px', cursor: 'pointer', fontWeight: 700 }}>
            Détail
          </button>
          {(d.niveau_global === 'critique' || d.niveau_global === 'danger') && (
            <button onClick={() => convoquer(d.eleve_id)}
              style={{ background: '#450a0a', color: '#f87171', border: 'none',
                borderRadius: '6px', padding: '5px 10px', fontSize: '10px', cursor: 'pointer', fontWeight: 700 }}>
              📱 Convoquer
            </button>
          )}
        </div>
      </div>
    );
  };

  return (
    <div style={{ padding: '24px', background: '#08090f', minHeight: '100vh' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
        <div>
          <h1 style={{ fontSize: '22px', fontWeight: 900, color: '#fff' }}>
            🔬 Diagnostic de Niveau
          </h1>
          <p style={{ fontSize: '12px', color: '#64748b' }}>
            Early Warning System — surveillance continue du niveau académique
          </p>
        </div>
        <button onClick={analyserTous} disabled={analysing} style={{
          background: analysing ? '#1e293b' : 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
          color: '#fff', border: 'none', borderRadius: '8px', padding: '10px 16px',
          fontSize: '12px', fontWeight: 700, cursor: 'pointer',
          display: 'flex', alignItems: 'center', gap: '6px',
        }}>
          <RefreshCw size={13} />
          {analysing ? 'Analyse en cours...' : 'Analyser tous'}
        </button>
      </div>

      <div style={{ display: 'flex', gap: '4px', marginBottom: '20px' }}>
        {[
          ['dashboard', '📊 Vue globale'],
          ['liste', '👥 Tous les élèves'],
          ['critique', '🚨 Critiques'],
          ['excellence', '⭐ Excellents'],
          ...(detail ? [['detail', '🔍 Détail élève']] : []),
        ].map(([id, label]) => (
          <button key={id} onClick={() => {
            setTab(id);
            if (id === 'critique') { setFiltreNiveau('critique'); setFiltreAction(''); }
            else if (id === 'excellence') { setFiltreNiveau('excellent'); setFiltreAction(''); }
            else if (id === 'liste') { setFiltreNiveau(''); setFiltreAction(''); }
          }} style={{
            background: tab === id ? '#1e3a5f' : '#111318',
            color: tab === id ? '#60a5fa' : '#64748b',
            border: `1px solid ${tab === id ? '#3b82f6' : '#1e293b'}`,
            borderRadius: '8px', padding: '8px 14px', fontSize: '11px',
            fontWeight: 700, cursor: 'pointer',
          }}>{label}</button>
        ))}
      </div>

      {tab === 'dashboard' && dashboard && (
        <div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5,1fr)', gap: '10px', marginBottom: '20px' }}>
            <StatBox label="Excellents" value={dashboard.par_niveau?.excellent} color="#4ade80"
              onClick={() => { setFiltreNiveau('excellent'); setTab('liste'); }} />
            <StatBox label="Normaux"    value={dashboard.par_niveau?.normal}    color="#60a5fa" />
            <StatBox label="Vigilance"  value={dashboard.par_niveau?.vigilance} color="#fb923c"
              onClick={() => { setFiltreNiveau('vigilance'); setTab('liste'); }} />
            <StatBox label="Danger"     value={dashboard.par_niveau?.danger}    color="#f87171"
              onClick={() => { setFiltreNiveau('danger'); setTab('liste'); }} />
            <StatBox label="Critiques"  value={dashboard.par_niveau?.critique}  color="#ef4444"
              onClick={() => { setFiltreNiveau('critique'); setTab('critique'); }} />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
            <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '12px', padding: '16px' }}>
              <div style={{ fontSize: '11px', color: '#f87171', fontWeight: 800, marginBottom: '12px' }}>
                🔴 TOP 5 ÉLÈVES À RISQUE
              </div>
              {dashboard.top_risque?.map((e, i) => (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between',
                  alignItems: 'center', padding: '8px 0', borderBottom: '1px solid #1e293b' }}>
                  <span style={{ fontSize: '12px', color: '#e2e8f0' }}>{e.eleve}</span>
                  <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                    <span style={{ fontSize: '10px', color: '#64748b' }}>{e.moyenne ?? '—'}/20</span>
                    <span style={{ fontSize: '9px', color: NIVEAUX[e.niveau]?.color, fontWeight: 700 }}>
                      {NIVEAUX[e.niveau]?.emoji} {e.niveau?.toUpperCase()}
                    </span>
                  </div>
                </div>
              ))}
            </div>

            <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '12px', padding: '16px' }}>
              <div style={{ fontSize: '11px', color: '#4ade80', fontWeight: 800, marginBottom: '12px' }}>
                ⭐ TOP 5 MEILLEURS ÉLÈVES
              </div>
              {dashboard.top_excellence?.length === 0 && (
                <div style={{ color: '#475569', fontSize: '12px', textAlign: 'center', padding: '20px' }}>
                  Aucun élève excellent détecté
                </div>
              )}
              {dashboard.top_excellence?.map((e, i) => (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between',
                  alignItems: 'center', padding: '8px 0', borderBottom: '1px solid #1e293b' }}>
                  <span style={{ fontSize: '12px', color: '#e2e8f0' }}>
                    {i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '⭐'} {e.eleve}
                  </span>
                  <span style={{ fontSize: '11px', color: '#4ade80', fontWeight: 800 }}>
                    {e.moyenne}/20
                  </span>
                </div>
              ))}
            </div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: '10px', marginTop: '12px' }}>
            <div style={{ background: '#1a0808', border: '1px solid #b91c1c', borderRadius: '10px', padding: '14px', textAlign: 'center' }}>
              <div style={{ fontSize: '24px', fontWeight: 900, color: '#f87171' }}>{dashboard.actions_requises?.convocations}</div>
              <div style={{ fontSize: '10px', color: '#64748b' }}>CONVOCATIONS REQUISES</div>
            </div>
            <div style={{ background: '#1f1008', border: '1px solid #c2410c', borderRadius: '10px', padding: '14px', textAlign: 'center' }}>
              <div style={{ fontSize: '24px', fontWeight: 900, color: '#fb923c' }}>{dashboard.actions_requises?.rattrapages}</div>
              <div style={{ fontSize: '10px', color: '#64748b' }}>RATTRAPAGES REQUIS</div>
            </div>
            <div style={{ background: '#0d2515', border: '1px solid #16a34a', borderRadius: '10px', padding: '14px', textAlign: 'center' }}>
              <div style={{ fontSize: '24px', fontWeight: 900, color: '#4ade80' }}>{dashboard.actions_requises?.excellents}</div>
              <div style={{ fontSize: '10px', color: '#64748b' }}>MENTIONS EXCELLENCE</div>
            </div>
          </div>
        </div>
      )}

      {(tab === 'liste' || tab === 'critique' || tab === 'excellence') && (
        <div>
          <div style={{ display: 'flex', gap: '10px', marginBottom: '14px' }}>
            {Object.entries(NIVEAUX).map(([key, val]) => (
              <button key={key} onClick={() => setFiltreNiveau(filtreNiveau === key ? '' : key)} style={{
                background: filtreNiveau === key ? val.bg : '#111318',
                color: filtreNiveau === key ? val.color : '#64748b',
                border: `1px solid ${filtreNiveau === key ? val.border : '#1e293b'}`,
                borderRadius: '20px', padding: '5px 12px', fontSize: '10px',
                fontWeight: 700, cursor: 'pointer',
              }}>{val.emoji} {val.label}</button>
            ))}
          </div>

          {loading ? (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px' }}>Analyse en cours...</div>
          ) : eleves.length === 0 ? (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px' }}>
              Aucun élève dans ce niveau. Lancez une analyse d'abord.
            </div>
          ) : (
            eleves.map(d => <EleveCard key={d.id} d={d} />)
          )}
        </div>
      )}

      {tab === 'detail' && detail && (
        <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '12px', padding: '20px' }}>
          <div style={{ display: 'flex', gap: '12px', marginBottom: '20px' }}>
            <div style={{ fontSize: '32px' }}>{NIVEAUX[detail.diagnostic?.niveau_global]?.emoji}</div>
            <div>
              <h2 style={{ fontSize: '18px', fontWeight: 900, color: '#fff' }}>
                {detail.diagnostic?.eleve?.prenom} {detail.diagnostic?.eleve?.nom}
              </h2>
              <div style={{ fontSize: '11px', color: '#64748b' }}>
                {detail.diagnostic?.eleve?.niveau_scolaire} ·
                Niveau : {NIVEAUX[detail.diagnostic?.niveau_global]?.label} ·
                Score risque : {detail.diagnostic?.score_risque}/100
              </div>
            </div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: '10px', marginBottom: '16px' }}>
            {[
              ['Moyenne', `${detail.diagnostic?.moyenne_generale ?? '—'}/20`, '#60a5fa'],
              ['Tendance', detail.diagnostic?.tendance !== null ? `${detail.diagnostic.tendance > 0 ? '+' : ''}${detail.diagnostic.tendance}pts` : '—',
                detail.diagnostic?.tendance < 0 ? '#f87171' : '#4ade80'],
              ['Absences/mois', detail.diagnostic?.nb_absences_mois, '#fb923c'],
              ['Notes < 5', detail.diagnostic?.nb_notes_sous_5, '#f87171'],
            ].map(([label, val, color]) => (
              <div key={label} style={{ background: '#1e293b', borderRadius: '8px', padding: '12px', textAlign: 'center' }}>
                <div style={{ fontSize: '22px', fontWeight: 900, color }}>{val}</div>
                <div style={{ fontSize: '9px', color: '#64748b' }}>{label}</div>
              </div>
            ))}
          </div>

          {detail.recommandations?.length > 0 && (
            <div style={{ marginBottom: '16px' }}>
              <div style={{ fontSize: '11px', color: '#60a5fa', fontWeight: 800, marginBottom: '8px' }}>
                💡 RECOMMANDATIONS
              </div>
              {detail.recommandations.map((r, i) => (
                <div key={i} style={{
                  display: 'flex', gap: '10px', alignItems: 'center',
                  padding: '6px 10px', borderRadius: '6px', marginBottom: '4px',
                  background: r.priorite === 'urgente' ? '#450a0a'
                    : r.priorite === 'haute' ? '#1f1008'
                    : r.priorite === 'info' ? '#0d2515' : '#0c1a30',
                }}>
                  <span style={{ fontSize: '10px', color:
                    r.priorite === 'urgente' ? '#f87171'
                    : r.priorite === 'haute' ? '#fb923c'
                    : r.priorite === 'info' ? '#4ade80' : '#60a5fa',
                    fontWeight: 700, width: '60px', flexShrink: 0 }}>
                    {r.priorite?.toUpperCase()}
                  </span>
                  <span style={{ fontSize: '11px', color: '#94a3b8' }}>{r.action}</span>
                </div>
              ))}
            </div>
          )}

          {detail.diagnostic?.matieres_en_danger?.length > 0 && (
            <div style={{ marginBottom: '16px' }}>
              <div style={{ fontSize: '11px', color: '#f87171', fontWeight: 800, marginBottom: '8px' }}>
                🔴 MATIÈRES EN DIFFICULTÉ
              </div>
              <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                {detail.diagnostic.matieres_en_danger.map(m => (
                  <div key={m.matiere} style={{
                    background: '#450a0a', border: '1px solid #b91c1c',
                    borderRadius: '8px', padding: '8px 12px',
                  }}>
                    <div style={{ fontSize: '12px', fontWeight: 700, color: '#f87171' }}>{m.matiere}</div>
                    <div style={{ fontSize: '10px', color: '#64748b' }}>Moy : {m.moyenne}/20</div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {detail.historique?.length > 0 && (
            <div>
              <div style={{ fontSize: '11px', color: '#60a5fa', fontWeight: 800, marginBottom: '8px' }}>
                📈 HISTORIQUE DE PROGRESSION
              </div>
              <div style={{ display: 'flex', gap: '6px', overflowX: 'auto' }}>
                {detail.historique.slice(0, 8).reverse().map((h, i) => (
                  <div key={i} style={{
                    background: '#1e293b', borderRadius: '6px', padding: '8px',
                    minWidth: '70px', textAlign: 'center', flexShrink: 0,
                  }}>
                    <div style={{ fontSize: '13px', fontWeight: 800,
                      color: NIVEAUX[h.niveau_global]?.color ?? '#60a5fa' }}>
                      {h.moyenne_generale ?? '—'}
                    </div>
                    <div style={{ fontSize: '8px', color: '#64748b' }}>
                      {new Date(h.analyse_le).toLocaleDateString('fr-DZ', { day: '2-digit', month: '2-digit' })}
                    </div>
                    <div style={{ fontSize: '8px', color: NIVEAUX[h.niveau_global]?.color }}>
                      {NIVEAUX[h.niveau_global]?.emoji}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
