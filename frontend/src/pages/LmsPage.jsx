import { useState, useEffect } from 'react';
import { getAccessToken } from '../api/tokenStore';
import { useI18n } from '@context/I18nContext';

import {
  Award,
  BarChart3,
  BookOpen,
  CheckCircle,
  Circle,
  FlaskConical,
  GraduationCap,
  Hourglass,
  Ruler,
  Timer,
  User,
} from 'lucide-react';

const BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/api\/v1\/?$/, '');
const api = (path, opts) => fetch(`${BASE_URL}/api/v1${path}`, {
  headers: { Authorization: `Bearer ${getAccessToken()}`, 'Content-Type':'application/json', 'X-Tenant-ID': localStorage.getItem('tenantId') ?? '' },
  ...opts,
}).then(r => r.json());

const NIVEAUX_COULEURS = { '3AS':'#2563EB','2AS':'#7C3AED','1AS':'#10B981','4AM':'#F59E0B','3AM':'#EF4444' };

export default function LmsPage() {
  const { t } = useI18n();
  const [cours, setCours]         = useState([]);
  const [selected, setSelected]   = useState(null);
  const [tab, setTab]             = useState('catalogue');
  const [loading, setLoading]     = useState(true);
  const [stats, setStats]         = useState(null);
  const [showNew, setShowNew]     = useState(false);
  const [form, setForm] = useState({ titre:'', description:'', matiere:'', langue:'ar', niveaux_cibles:[], seuil_completion:80, certificat_actif:true });
  const [saving, setSaving]       = useState(false);
  const [msg, setMsg]             = useState('');
  const [msgOk, setMsgOk] = useState(true);

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
    if (res.success) { setShowNew(false); loadCours(); setMsg(t('lms_cours_cree')); setMsgOk(true); }
    else { setMsg(res.message); setMsgOk(false); }
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
          <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}><BookOpen size={22} aria-hidden='true' />{t('lms_titre')}</h1>
          <p style={{ fontSize:'12px', color:'#64748B' }}>{t('lms_sous_titre')}</p>
        </div>
        <button onClick={() => setShowNew(true)} style={{ background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'9px', padding:'10px 18px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
          + {t('lms_creer')}
        </button>
      </div>

      {msg && <div style={{ background:msgOk?'#0d2515':'#450a0a', border:`1px solid ${msgOk?'#16a34a':'#b91c1c'}`, borderRadius:'9px', padding:'10px 16px', marginBottom:'16px', fontSize:'12px', color:msgOk?'#4ade80':'#f87171' }}>{msg}</div>}

      {stats && (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:'12px', marginBottom:'24px' }}>
          <St label={t('lms_stat_publies')}    value={stats.total_cours}       color="#2563EB" icon={<BookOpen size={12} aria-hidden="true" />} />
          <St label={t('lms_stat_inscrits')}  value={stats.total_inscrits}    color="#10B981" icon={<GraduationCap size={12} aria-hidden="true" />} />
          <St label={t('lms_stat_completes')}  value={stats.cours_completes}   color="#7C3AED" icon={<CheckCircle size={12} aria-hidden="true" />} />
          <St label={t('lms_stat_certificats')}      value={stats.certificats}       color="#F59E0B" icon={<Award size={12} aria-hidden="true" />} />
        </div>
      )}

      <div style={{ display:'flex', gap:'4px', marginBottom:'20px' }}>
        {[['catalogue', <BookOpen size={12} aria-hidden="true" />, t('lms_tab_catalogue')],['activite', <BarChart3 size={12} aria-hidden="true" />, t('lms_tab_activite')]].map(([id,icon,label]) => (
          <button key={id} onClick={() => setTab(id)} style={{ background:tab===id?'#1e3a5f':'#111318', color:tab===id?'#60a5fa':'#64748B', border:`1px solid ${tab===id?'#3b82f6':'#1E2D40'}`, borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>{icon} {label}</button>
        ))}
      </div>

      {tab === 'catalogue' && (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'14px' }}>
          {loading ? <div style={{ gridColumn:'1/-1', textAlign:'center', color:'#64748B', padding:'40px' }}>{t('loading')}</div>
          : cours.length === 0 ? <div style={{ gridColumn:'1/-1', textAlign:'center', color:'#64748B', padding:'40px' }}>{t('lms_aucun_cours')}</div>
          : cours.map(c => (
            <div key={c.id} style={{ background:'#0D1117', border:'1px solid #1E2D40', borderRadius:'14px', overflow:'hidden' }}>
              <div style={{ height:'120px', background:'linear-gradient(135deg,#1e3a5f,#2563eb33)', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'48px' }}>
                {c.matiere?.includes('Maths') ? <Ruler size={48} aria-hidden='true' /> : c.matiere?.includes('Phys') ? <FlaskConical size={48} aria-hidden='true' /> : c.matiere?.includes('Arabe') ? <BookOpen size={48} aria-hidden='true' /> : <BookOpen size={48} aria-hidden='true' />}
              </div>
              <div style={{ padding:'14px' }}>
                <div style={{ display:'flex', justifyContent:'space-between', alignItems:'flex-start', marginBottom:'6px' }}>
                  <div style={{ fontWeight:800, fontSize:'13px', color:'#fff', flex:1 }}>{c.titre}</div>
                  <span style={{ background:c.publie?'#10B98122':'#64748b22', color:c.publie?'#10B981':'#94A3B8', fontSize:'9px', fontWeight:700, padding:'2px 8px', borderRadius:'20px', flexShrink:0 }}>
                    {c.publie ? <><CheckCircle size={9} aria-hidden='true' />{t('lms_publie')}</> : <><Circle size={9} aria-hidden='true' />{t('lms_brouillon')}</>}
                  </span>
                </div>
                {c.matiere && <div style={{ fontSize:'11px', color:'#64748B', marginBottom:'6px' }}><BookOpen size={11} aria-hidden='true' /> {c.matiere}</div>}
                <div style={{ display:'flex', gap:'6px', flexWrap:'wrap', marginBottom:'10px' }}>
                  {(c.niveaux_cibles || []).map(n => (
                    <span key={n} style={{ background:(NIVEAUX_COULEURS[n]||'#64748b')+'22', color:NIVEAUX_COULEURS[n]||'#94A3B8', fontSize:'9px', fontWeight:700, padding:'1px 7px', borderRadius:'20px' }}>{n}</span>
                  ))}
                </div>
                <div style={{ display:'flex', justifyContent:'space-between', fontSize:'10px', color:'#64748B', marginBottom:'10px' }}>
                  <span><User size={16} aria-hidden='true' /> {t('lms_inscrits_count', { count: c.inscriptions_count ?? 0 })}</span>
                  <span><BookOpen size={16} aria-hidden='true' /> {t('lms_lecons_count', { count: c.nb_lecons ?? 0 })}</span>
                  <span><Timer size={16} aria-hidden='true' /> {c.duree_estimee || '—'}</span>
                </div>
                <div style={{ display:'flex', gap:'6px' }}>
                  <button onClick={() => { setSelected(c); setTab('cours-detail'); }} style={{ flex:2, background:'#2563EB', color:'#fff', border:'none', borderRadius:'8px', padding:'7px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                    {t('lms_gerer')}
                  </button>
                  <button onClick={() => publier(c.id)} style={{ flex:1, background:c.publie?'#64748b22':'#10B98122', color:c.publie?'#94A3B8':'#10B981', border:`1px solid ${c.publie?'#64748b44':'#10B98144'}`, borderRadius:'8px', padding:'7px', fontSize:'10px', fontWeight:700, cursor:'pointer' }}>
                    {c.publie ? t('lms_depublier') : t('lms_publier')}
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
            <BarChart3 size={13} aria-hidden='true' />{t('lms_activite_titre')}
                      </div>
          {(stats?.activite_recente || []).length === 0 ? (
            <div style={{ padding:'40px', textAlign:'center', color:'#64748B' }}>{t('lms_aucune_activite')}</div>
          ) : (stats?.activite_recente || []).map((p, i) => (
            <div key={i} style={{ display:'flex', alignItems:'center', gap:'12px', padding:'12px 20px', borderBottom:'1px solid #1E2D4044' }}>
              <span style={{ fontSize:'18px' }}><User size={18} aria-hidden='true' /></span>
              <div style={{ flex:1 }}>
                <div style={{ fontSize:'12px', fontWeight:700, color:'#E2E8F0' }}>{p.eleve?.prenom} {p.eleve?.nom}</div>
                <div style={{ fontSize:'10px', color:'#64748B' }}>{t('lms_a_complete', { titre: p.lecon?.titre ?? '' })}</div>
              </div>
              <span style={{ fontSize:'10px', color:p.completee?'#10B981':'#64748B', fontWeight:700 }}>
                {p.completee ? <><CheckCircle size={10} aria-hidden='true' />{t('lms_complete')}</> : <><Hourglass size={10} aria-hidden='true' />{t('lms_en_cours')}</>}
              </span>
            </div>
          ))}
        </div>
      )}

      {showNew && (
        <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.7)', display:'flex', alignItems:'center', justifyContent:'center', zIndex:1000 }} onClick={() => setShowNew(false)}>
          <div style={{ background:'#111318', border:'1px solid #1E2D40', borderRadius:'16px', padding:'24px', width:'520px', maxWidth:'90%' }} onClick={e=>e.stopPropagation()}>
            <h3 style={{ color:'#fff', fontWeight:800, marginBottom:'20px' }}><BookOpen size={16} aria-hidden='true' />{t('lms_modal_titre')}</h3>
            {[
              ['lms_champ_titre', 'titre', 'text', 'lms_ph_titre'],
              ['type_matiere', 'matiere', 'text', 'lms_ph_matiere'],
              ['lms_champ_duree', 'duree_estimee', 'text', 'lms_ph_duree'],
            ].map(([label, key, type, ph]) => (
              <div key={key} style={{ marginBottom:'10px' }}>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>{t(label)}</label>
                <input type={type} value={form[key]} onChange={e=>setForm(f=>({...f,[key]:e.target.value}))} placeholder={t(ph)}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
              </div>
            ))}
            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px', marginBottom:'10px' }}>
              <div>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>{t('lang_select')}</label>
                <select value={form.langue} onChange={e=>setForm(f=>({...f,langue:e.target.value}))}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }}>
                  <option value="ar">{t('lang_ar')}</option>
                  <option value="fr">{t('lang_fr')}</option>
                  <option value="en">{t('lang_en')}</option>
                </select>
              </div>
              <div>
                <label style={{ fontSize:'10px', color:'#64748B', display:'block', marginBottom:'4px' }}>{t('lms_champ_seuil')}</label>
                <input type="number" value={form.seuil_completion} min="1" max="100"
                  onChange={e=>setForm(f=>({...f,seuil_completion:e.target.value}))}
                  style={{ width:'100%', background:'#1E293B', border:'1px solid #334155', borderRadius:'8px', color:'#E2E8F0', padding:'9px 12px', fontSize:'12px', fontFamily:'Inter,sans-serif' }} />
              </div>
            </div>
            <div style={{ marginBottom:'14px', fontSize:'11px', color:'#64748B', background:'#1E293B', borderRadius:'8px', padding:'10px' }}>
              <BookOpen size={11} aria-hidden='true' />{t('lms_apres_creation')}
                          </div>
            <div style={{ display:'flex', gap:'10px' }}>
              <button onClick={() => setShowNew(false)} style={{ flex:1, background:'#1E293B', border:'1px solid #1E2D40', color:'#94A3B8', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>{t('cancel')}</button>
              <button onClick={creerCours} disabled={saving || !form.titre} style={{ flex:2, background:'linear-gradient(135deg,#2563EB,#1d4ed8)', color:'#fff', border:'none', borderRadius:'8px', padding:'10px', cursor:'pointer', fontWeight:700 }}>
                {saving ? t('creation_en_cours') : <><CheckCircle size={16} aria-hidden='true' />{t('lms_creer_le_cours')}</>}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
