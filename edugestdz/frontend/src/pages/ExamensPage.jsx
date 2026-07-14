import { useState, useEffect } from 'react';

const api = (path, opts) => fetch(`/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('access_token')}`, 'Content-Type':'application/json', 'X-Tenant-ID': localStorage.getItem('tenantId') ?? '' },
  ...opts,
}).then(r => r.json());

const STATUTS = { brouillon:'⚪ Brouillon', planifie:'🔵 Planifié', en_cours:'🟡 En cours', termine:'🟢 Terminé', annule:'🔴 Annulé' };
const TYPES   = { BEM:'📋 BEM', BAC:'🎓 BAC', autre:'📄 Autre' };

export default function ExamensPage() {
  const [sessions, setSessions] = useState([]);
  const [selected, setSelected] = useState(null);
  const [dashboard, setDashboard] = useState(null);
  const [loading, setLoading]   = useState(true);
  const [tab, setTab] = useState('sessions');
  const [showNew, setShowNew]   = useState(false);
  const [form, setForm] = useState({ type:'BAC', annee_scolaire:'2025/2026', session:'principale', date_debut:'', date_fin:'', wilaya:'Oran', nom_centre:'', max_candidats_par_salle:20, nb_surveillants_par_salle:3 });
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState('');

  useEffect(() => { loadSessions(); }, []);

  const loadSessions = async () => {
    setLoading(true);
    const res = await api('/examens');
    setSessions(res?.data?.data ?? []);
    setLoading(false);
  };

  const loadDashboard = async (id) => {
    const res = await api(`/examens/${id}`);
    setSelected(res?.data);
    setDashboard(res?.dashboard);
    setTab('dashboard');
  };

  const createSession = async () => {
    setSaving(true);
    const res = await api('/examens', { method:'POST', body: JSON.stringify(form) });
    setSaving(false);
    if (res.success) { setShowNew(false); loadSessions(); setMsg('✅ Session créée'); }
    else setMsg('❌ ' + res.message);
    setTimeout(() => setMsg(''), 3000);
  };

  const affecterCandidats = async (id) => {
    const res = await api(`/examens/${id}/affecter-candidats`, { method:'POST' });
    alert(res.message ?? (res.success ? '✅ Affectation terminée' : '❌ Erreur'));
    if (res.success) loadDashboard(id);
  };

  const affecterSurveillants = async (id) => {
    const res = await api(`/examens/${id}/affecter-surveillants`, { method:'POST' });
    alert(res.message ?? (res.success ? '✅ Affectation terminée' : '❌ Erreur'));
    if (res.success) loadDashboard(id);
  };

  const imprimerConvocations = (sessionId) => {
    window.open(`/api/v1/examens/${sessionId}/toutes-convocations`, '_blank');
  };

  const S = (v, color, bg) => (
    <span style={{ background:bg||color+'22', color:color||'#60a5fa', fontSize:'10px', fontWeight:700, padding:'2px 9px', borderRadius:'20px' }}>{v}</span>
  );

  return (
    <div style={{ padding:'24px', background:'#070B14', minHeight:'100vh' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'24px' }}>
        <div>
          <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}>📝 Examens Officiels BEM/BAC</h1>
          <p style={{ fontSize:'12px', color:'#64748B' }}>Calendrier · Salles · Surveillants · Convocations PDF</p>
        </div>
        <button onClick={() => setShowNew(true)} style={{ background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'9px', padding:'10px 18px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
          + Nouvelle session
        </button>
      </div>

      {msg && <div style={{ background:msg.includes('✅')?'#0d2515':'#450a0a', border:`1px solid ${msg.includes('✅')?'#16a34a':'#b91c1c'}`, borderRadius:'9px', padding:'10px 16px', marginBottom:'16px', fontSize:'12px', color:msg.includes('✅')?'#4ade80':'#f87171' }}>{msg}</div>}

      <div style={{ display:'flex', gap:'4px', marginBottom:'20px' }}>
        {[['sessions','📋 Sessions'],selected&&['dashboard','📊 Dashboard'],selected&&['epreuves','🗓️ Épreuves'],selected&&['salles','🏫 Salles'],selected&&['candidats','👦 Candidats'],selected&&['surveillants','👨‍🏫 Surveillants']].filter(Boolean).map(([id,label]) => (
          <button key={id} onClick={() => setTab(id)} style={{ background:tab===id?'#1e3a5f':'#111318', color:tab===id?'#60a5fa':'#64748B', border:`1px solid ${tab===id?'#3b82f6':'#1E2D40'}`, borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>{label}</button>
        ))}
      </div>

      {tab === 'sessions' && (
        <div style={{ display:'grid', gap:'10px' }}>
          {loading ? <div style={{ color:'#64748B', textAlign:'center', padding:'40px' }}>Chargement...</div>
          : sessions.length === 0 ? <div style={{ color:'#64748B', textAlign:'center', padding:'40px' }}>Aucune session. Créer une session BEM ou BAC.</div>
          : sessions.map(s => (
            <div key={s.id} style={{ background:'#0D1117', border:'1px solid #1E2D40', borderRadius:'12px', padding:'16px 20px', display:'flex', alignItems:'center', gap:'16px' }}>
              <div style={{ fontSize:'28px' }}>{s.type === 'BAC' ? '🎓' : '📋'}</div>
              <div style={{ flex:1 }}>
                <div style={{ fontWeight:800, fontSize:'14px', color:'#fff' }}>{TYPES[s.type] ?? s.type} — {s.annee_scolaire}</div>
                <div style={{ fontSize:'11px', color:'#64748B' }}>
                  {s.session} · {s.nom_centre ?? 'Centre à définir'} · {s.wilaya ?? ''}
                  · Du {s.date_debut} au {s.date_fin}
                </div>
                <div style={{ display:'flex', gap:'8px', marginTop:'6px' }}>
                  {S(STATUTS[s.statut] ?? s.statut, s.statut==='termine'?'#10B981':s.statut==='en_cours'?'#F59E0B':'#60a5fa')}
                  {S(`👦 ${s.candidats_count??0} candidats`, '#60a5fa')}
                  {S(`🏫 ${s.salles_count??0} salles`, '#7C3AED')}
                  {S(`📚 ${s.epreuves_count??0} épreuves`, '#10B981')}
                </div>
              </div>
              <button onClick={() => loadDashboard(s.id)} style={{ background:'#2563EB', color:'#fff', border:'none', borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                Gérer →
              </button>
            </div>
          ))}
        </div>
      )}

      {tab === 'dashboard' && selected && dashboard && (
        <div>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:'12px', marginBottom:'20px' }}>
            {[
              ['👦 Candidats',  dashboard.nb_candidats_total,    '#2563EB'],
              ['✅ Affectés',   dashboard.nb_candidats_affectes, '#10B981'],
              ['🏫 Salles',     dashboard.nb_salles,             '#7C3AED'],
              ['👨‍🏫 Surveillants', dashboard.nb_surveillants,  '#F59E0B'],
            ].map(([label, val, color]) => (
              <div key={label} style={{ background:'#0D1117', border:`1px solid #1E2D40`, borderTop:`2px solid ${color}`, borderRadius:'12px', padding:'16px' }}>
                <div style={{ fontSize:'10px', color:'#64748B', marginBottom:'8px' }}>{label}</div>
                <div style={{ fontSize:'26px', fontWeight:900, color:'#fff' }}>{val ?? 0}</div>
              </div>
            ))}
          </div>

          {dashboard.alertes?.length > 0 && dashboard.alertes.map((a,i) => (
            <div key={i} style={{ background:a.type==='danger'?'#450a0a':'#1f1008', border:`1px solid ${a.type==='danger'?'#b91c1c':'#c2410c'}`, borderRadius:'9px', padding:'10px 14px', marginBottom:'8px', fontSize:'11px', color:a.type==='danger'?'#f87171':'#fb923c' }}>
              ⚠️ {a.msg}
            </div>
          ))}

          <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'10px', marginTop:'16px' }}>
            <button onClick={() => affecterCandidats(selected.id)} style={{ background:'#2563EB', color:'#fff', border:'none', borderRadius:'9px', padding:'12px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
              🤖 Affecter candidats aux salles
            </button>
            <button onClick={() => affecterSurveillants(selected.id)} style={{ background:'#7C3AED', color:'#fff', border:'none', borderRadius:'9px', padding:'12px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
              👨‍🏫 Affecter surveillants
            </button>
            <button onClick={() => imprimerConvocations(selected.id)} style={{ background:'#10B981', color:'#fff', border:'none', borderRadius:'9px', padding:'12px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
              🖨️ Imprimer toutes les convocations PDF
            </button>
          </div>
        </div>
      )}

      {showNew && (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.7)', display:'flex', alignItems:'center', justifyContent:'center', zIndex:1000 }} onClick={() => setShowNew(false)}>
          <div style={{ background:'#111318', border:'1px solid #1E2D40', borderRadius:'16px', padding:'24px', width:'520px', maxWidth:'90%' }} onClick={e=>e.stopPropagation()}>
            <h3 style={{ color:'#fff', fontWeight:800, marginBottom:'20px' }}>📝 Nouvelle Session d'Examen</h3>
            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px' }}>
              {[
                { label:'Type *', key:'type', type:'select', opts:{BEM:'BEM',BAC:'BAC',autre:'Autre'} },
                { label:'Année scolaire *', key:'annee_scolaire', type:'text', ph:'2025/2026' },
                { label:'Session', key:'session', type:'select', opts:{principale:'Principale',rattrapage:'Rattrapage'} },
                { label:'Date début *', key:'date_debut', type:'date' },
                { label:'Date fin *', key:'date_fin', type:'date' },
                { label:'Wilaya', key:'wilaya', type:'text', ph:'Oran' },
                { label:'Nom centre', key:'nom_centre', type:'text', ph:'Lycée Ibn Khaldoun' },
                { label:'Max candidats/salle', key:'max_candidats_par_salle', type:'number' },
              ].map(({ label, key, type, opts, ph }) => (
                <div key={key}>
                  <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>{label}</label>
                  {type === 'select' ? (
                    <select value={form[key]} onChange={e=>setForm(f=>({...f,[key]:e.target.value}))}
                      style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
                      {Object.entries(opts).map(([k,v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                  ) : (
                    <input type={type} value={form[key]} onChange={e=>setForm(f=>({...f,[key]:e.target.value}))} placeholder={ph}
                      style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
                  )}
                </div>
              ))}
            </div>
            <div style={{ display:'flex', gap:'10px', marginTop:'20px' }}>
              <button onClick={() => setShowNew(false)} style={{ flex:1, background:'#1E293B', border:'1px solid #1E2D40', color:'#94A3B8', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>Annuler</button>
              <button onClick={createSession} disabled={saving || !form.date_debut || !form.date_fin} style={{ flex:2, background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>
                {saving ? 'Création...' : '✅ Créer la session'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
