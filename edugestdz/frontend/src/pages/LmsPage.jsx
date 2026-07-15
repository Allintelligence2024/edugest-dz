import { useState, useEffect } from 'react';

const BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/api\/v1\/?$/, '');
const api = (path, opts) => fetch(`${BASE_URL}/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('access_token')}`, 'Content-Type':'application/json', 'X-Tenant-ID': localStorage.getItem('tenantId') ?? '' },
  ...opts,
}).then(r => r.json());

const TYPES = { texte:'📄', video:'🎥', pdf:'📑', audio:'🎵', lien:'🔗', quiz:'✏️', devoir:'📝' };
const NIVEAUX_COULEURS = { '3AS':'#2563EB','2AS':'#7C3AED','1AS':'#10B981','4AM':'#F59E0B','3AM':'#EF4444' };

export default function LmsPage() {
  const [cours, setCours]         = useState([]);
  const [selected, setSelected]   = useState(null);
  const [tab, setTab]             = useState('catalogue');
  const [loading, setLoading]     = useState(true);
  const [stats, setStats]         = useState(null);
  const [showNew, setShowNew]     = useState(false);
  const [form, setForm] = useState({ titre:'', description:'', matiere:'', langue:'ar', niveaux_cibles:[], seuil_completion:80, certificat_actif:true });
  const [saving, setSaving]       = useState(false);
  const [msg, setMsg]             = useState('');

  useEffect(() => { loadCours(); }, []);

  const loadCours = async () => {
    setLoading(true);
    const [coursRes, dashRes] = await Promise.all([
      api('/lms/cours'), api('/lms/dashboard'),
    ]);
    setCours(coursRes?.data?.data ?? []);
    setStats(dashRes?.data);
    setLoading(false);
  };

  const creerCours = async () => {
    setSaving(true);
    const res = await api('/lms/cours', { method:'POST', body:JSON.stringify(form) });
    setSaving(false);
    if (res.success) { setShowNew(false); loadCours(); setMsg('✅ Cours créé'); }
    else setMsg('❌ ' + res.message);
    setTimeout(() => setMsg(''), 3000);
  };

  const publier = async (id) => {
    const res = await api(`/lms/cours/${id}/publier`, { method:'POST' });
    if (res.success) loadCours();
    else alert(res.message);
  };

  const St = ({ label, value, color, icon }) => (
    <div style={{ background:'#0D1117', border:`1px solid #1E2D40`, borderTop:`2px solid ${color}`, borderRadius:'12px', padding:'16px 20px' }}>
      <div style={{ fontSize:'10px', fontWeight:700, color:'#64748B', textTransform:'uppercase', letterSpacing:'1px', marginBottom:'8px' }}>{icon} {label}</div>
      <div style={{ fontSize:'26px', fontWeight:900, color:'#fff' }}>{loading ? '...' : (value ?? 0)}</div>
    </div>
  );

  return (
    <div style={{ padding:'24px', background:'#070B14', minHeight:'100vh' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'24px' }}>
        <div>
          <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}>📚 LMS — Cours en ligne</h1>
          <p style={{ fontSize:'12px', color:'#64748B' }}>Vidéos · PDF · Quiz · Devoirs · Certificats</p>
        </div>
        <button onClick={() => setShowNew(true)} style={{ background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'9px', padding:'10px 18px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
          + Créer un cours
        </button>
      </div>

      {msg && <div style={{ background:msg.includes('✅')?'#0d2515':'#450a0a', border:`1px solid ${msg.includes('✅')?'#16a34a':'#b91c1c'}`, borderRadius:'9px', padding:'10px 16px', marginBottom:'16px', fontSize:'12px', color:msg.includes('✅')?'#4ade80':'#f87171' }}>{msg}</div>}

      {stats && (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:'12px', marginBottom:'24px' }}>
          <St label="Cours publiés"    value={stats.total_cours}       color="#2563EB" icon="📚" />
          <St label="Élèves inscrits"  value={stats.total_inscrits}    color="#10B981" icon="👦" />
          <St label="Cours complétés"  value={stats.cours_completes}   color="#7C3AED" icon="✅" />
          <St label="Certificats"      value={stats.certificats}       color="#F59E0B" icon="🎓" />
        </div>
      )}

      <div style={{ display:'flex', gap:'4px', marginBottom:'20px' }}>
        {[['catalogue','📚 Catalogue'],['activite','📊 Activité récente']].map(([id,label]) => (
          <button key={id} onClick={() => setTab(id)} style={{ background:tab===id?'#1e3a5f':'#111318', color:tab===id?'#60a5fa':'#64748B', border:`1px solid ${tab===id?'#3b82f6':'#1E2D40'}`, borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>{label}</button>
        ))}
      </div>

      {tab === 'catalogue' && (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'14px' }}>
          {loading ? <div style={{ gridColumn:'1/-1', textAlign:'center', color:'#64748B', padding:'40px' }}>Chargement...</div>
          : cours.length === 0 ? <div style={{ gridColumn:'1/-1', textAlign:'center', color:'#64748B', padding:'40px' }}>Aucun cours. Créez votre premier cours LMS.</div>
          : cours.map(c => (
            <div key={c.id} style={{ background:'#0D1117', border:'1px solid #1E2D40', borderRadius:'14px', overflow:'hidden' }}>
              <div style={{ height:'120px', background:'linear-gradient(135deg,#1e3a5f,#2563eb33)', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'48px' }}>
                {c.matiere?.includes('Maths') ? '📐' : c.matiere?.includes('Phys') ? '⚗️' : c.matiere?.includes('Arabe') ? '📖' : '📚'}
              </div>
              <div style={{ padding:'14px' }}>
                <div style={{ display:'flex', justifyContent:'space-between', alignItems:'flex-start', marginBottom:'6px' }}>
                  <div style={{ fontWeight:800, fontSize:'13px', color:'#fff', flex:1 }}>{c.titre}</div>
                  <span style={{ background:c.publie?'#10B98122':'#64748b22', color:c.publie?'#10B981':'#94A3B8', fontSize:'9px', fontWeight:700, padding:'2px 8px', borderRadius:'20px', flexShrink:0 }}>
                    {c.publie ? '✅ Publié' : '⚪ Brouillon'}
                  </span>
                </div>
                {c.matiere && <div style={{ fontSize:'11px', color:'#64748B', marginBottom:'6px' }}>📚 {c.matiere}</div>}
                <div style={{ display:'flex', gap:'6px', flexWrap:'wrap', marginBottom:'10px' }}>
                  {(c.niveaux_cibles || []).map(n => (
                    <span key={n} style={{ background:(NIVEAUX_COULEURS[n]||'#64748b')+'22', color:NIVEAUX_COULEURS[n]||'#94A3B8', fontSize:'9px', fontWeight:700, padding:'1px 7px', borderRadius:'20px' }}>{n}</span>
                  ))}
                </div>
                <div style={{ display:'flex', justifyContent:'space-between', fontSize:'10px', color:'#64748B', marginBottom:'10px' }}>
                  <span>👦 {c.inscriptions_count ?? 0} inscrits</span>
                  <span>📖 {c.nb_lecons ?? 0} leçons</span>
                  <span>⏱ {c.duree_estimee || '—'}</span>
                </div>
                <div style={{ display:'flex', gap:'6px' }}>
                  <button onClick={() => { setSelected(c); setTab('cours-detail'); }} style={{ flex:2, background:'#2563EB', color:'#fff', border:'none', borderRadius:'8px', padding:'7px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                    Gérer le cours
                  </button>
                  <button onClick={() => publier(c.id)} style={{ flex:1, background:c.publie?'#64748b22':'#10B98122', color:c.publie?'#94A3B8':'#10B981', border:`1px solid ${c.publie?'#64748b44':'#10B98144'}`, borderRadius:'8px', padding:'7px', fontSize:'10px', fontWeight:700, cursor:'pointer' }}>
                    {c.publie ? 'Dépublier' : 'Publier'}
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {tab === 'activite' && (
        <div style={{ background:'#0D1117', border:'1px solid #1E2D40', borderRadius:'14px', overflow:'hidden' }}>
          <div style={{ padding:'14px 20px', borderBottom:'1px solid #1E2D40', fontSize:'13px', fontWeight:700, color:'#fff' }}>
            📊 Activité récente des élèves
          </div>
          {(stats?.activite_recente || []).length === 0 ? (
            <div style={{ padding:'40px', textAlign:'center', color:'#64748B' }}>Aucune activité enregistrée.</div>
          ) : (stats?.activite_recente || []).map((p, i) => (
            <div key={i} style={{ display:'flex', alignItems:'center', gap:'12px', padding:'12px 20px', borderBottom:'1px solid #1E2D4044' }}>
              <span style={{ fontSize:'18px' }}>👦</span>
              <div style={{ flex:1 }}>
                <div style={{ fontSize:'12px', fontWeight:700, color:'#E2E8F0' }}>{p.eleve?.prenom} {p.eleve?.nom}</div>
                <div style={{ fontSize:'10px', color:'#64748B' }}>a complété : {p.lecon?.titre}</div>
              </div>
              <span style={{ fontSize:'10px', color:p.completee?'#10B981':'#64748B', fontWeight:700 }}>
                {p.completee ? '✅ Complété' : '⏳ En cours'}
              </span>
            </div>
          ))}
        </div>
      )}

      {showNew && (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.7)', display:'flex', alignItems:'center', justifyContent:'center', zIndex:1000 }} onClick={() => setShowNew(false)}>
          <div style={{ background:'#111318', border:'1px solid #1E2D40', borderRadius:'16px', padding:'24px', width:'520px', maxWidth:'90%' }} onClick={e=>e.stopPropagation()}>
            <h3 style={{ color:'#fff', fontWeight:800, marginBottom:'20px' }}>📚 Nouveau cours LMS</h3>
            {[
              ['Titre *', 'titre', 'text', 'ex: Cours de Mathématiques 3AS'],
              ['Matière', 'matiere', 'text', 'ex: Mathématiques'],
              ['Durée estimée', 'duree_estimee', 'text', 'ex: 3h30'],
            ].map(([label, key, type, ph]) => (
              <div key={key} style={{ marginBottom:'10px' }}>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>{label}</label>
                <input type={type} value={form[key]} onChange={e=>setForm(f=>({...f,[key]:e.target.value}))} placeholder={ph}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
              </div>
            ))}
            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px', marginBottom:'10px' }}>
              <div>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>Langue</label>
                <select value={form.langue} onChange={e=>setForm(f=>({...f,langue:e.target.value}))}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
                  <option value="ar">العربية</option>
                  <option value="fr">Français</option>
                  <option value="en">English</option>
                </select>
              </div>
              <div>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>Seuil certificat (%)</label>
                <input type="number" value={form.seuil_completion} min="1" max="100"
                  onChange={e=>setForm(f=>({...f,seuil_completion:e.target.value}))}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
              </div>
            </div>
            <div style={{ marginBottom:'14px', fontSize:'11px', color:'#64748B', background:'#1E293B', borderRadius:'8px', padding:'10px' }}>
              📖 Après la création → ajouter des chapitres, des leçons, des quiz et des devoirs.
            </div>
            <div style={{ display:'flex', gap:'10px' }}>
              <button onClick={() => setShowNew(false)} style={{ flex:1, background:'#1E293B', border:'1px solid #1E2D40', color:'#94A3B8', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>Annuler</button>
              <button onClick={creerCours} disabled={saving || !form.titre} style={{ flex:2, background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>
                {saving ? 'Création...' : '✅ Créer le cours'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
