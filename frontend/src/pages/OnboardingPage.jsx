import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '@api/client';
import { useAuth } from '@context/AuthContext';

const ETAPES = [
  {
    id:    1, emoji: '📚', titre: 'Votre première matière',
    desc:  'Commencez par créer les matières enseignées dans votre établissement.',
    action:'Créer une matière', url:'/matieres',
    champs:[
      { key:'nom_fr', label:'Nom en français', placeholder:'Mathématiques', required:true },
      { key:'nom_ar', label:'الاسم بالعربية',  placeholder:'الرياضيات', required:false },
      { key:'code',   label:'Code court',       placeholder:'MATH', required:false },
    ],
  },
  {
    id:    2, emoji: '👩‍🏫', titre: 'Votre premier enseignant',
    desc:  'Ajoutez l\'enseignant qui donnera les premiers cours.',
    action:'Ajouter un enseignant', url:'/enseignants',
    champs:[
      { key:'nom',       label:'Nom',       placeholder:'Benali',   required:true },
      { key:'prenom',    label:'Prénom',    placeholder:'Amina',    required:true },
      { key:'email',     label:'Email',     placeholder:'prof@ecole.dz', type:'email', required:true },
      { key:'telephone', label:'Téléphone', placeholder:'0555 12 34 56', required:false },
      { key:'specialite',label:'Spécialité',placeholder:'Mathématiques', required:false },
    ],
  },
  {
    id:    3, emoji: '📋', titre: 'Votre premier groupe',
    desc:  'Créez un groupe de niveau pour regrouper vos élèves.',
    action:'Créer un groupe', url:'/groupes',
    champs:[
      { key:'nom',    label:'Nom du groupe',   placeholder:'3ème AM - Groupe A', required:true },
      { key:'niveau', label:'Niveau scolaire', placeholder:'3AM', required:true },
      { key:'capacite_max', label:'Capacité maximale', placeholder:'25', type:'number', required:false },
    ],
  },
  {
    id:    4, emoji: '👨‍🎓', titre: 'Votre premier élève',
    desc:  'Inscrivez le premier élève de votre établissement.',
    action:'Inscrire un élève', url:'/eleves',
    champs:[
      { key:'nom',         label:'Nom',    placeholder:'Mammeri',  required:true },
      { key:'prenom',      label:'Prénom', placeholder:'Karim',    required:true },
      { key:'date_naissance', label:'Date de naissance', type:'date', required:false },
      { key:'niveau_scolaire', label:'Niveau',    placeholder:'3AM', required:false },
      { key:'telephone_parent', label:'Téléphone parent', placeholder:'0555 00 00 00', required:false },
    ],
  },
  {
    id:    5, emoji: '🔔', titre: 'Tester les notifications',
    desc:  'Envoyez-vous une notification de test pour confirmer que tout fonctionne.',
    action:'Envoyer la notification test',
    champs:[],
  },
];

export default function OnboardingPage() {
  const navigate   = useNavigate();
  const { marquerOnboardingComplete } = useAuth();
  const [statut,   setStatut]   = useState(null);
  const [etapeIdx, setEtapeIdx] = useState(0);
  const [form,     setForm]     = useState({});
  const [loading,  setLoading]  = useState(false);
  const [error,    setError]    = useState('');
  const [success,  setSuccess]  = useState('');

  useEffect(() => {
    api('/onboarding').then(r => {
      if (r.success) {
        setStatut(r);
        const prochaine = r.etapes.findIndex(e => !e.complete);
        setEtapeIdx(prochaine === -1 ? 4 : prochaine);
      }
    }).catch(() => {});
  }, []);

  const etape = ETAPES[etapeIdx];

  const handleSubmit = async () => {
    setLoading(true); setError(''); setSuccess('');
    try {
      if (etape.id === 5) {
        await api('/onboarding/tester-notification', { method:'POST' });
        marquerOnboardingComplete();
        setSuccess('🎉 Notification envoyée ! Votre installation est terminée.');
        setTimeout(() => navigate('/dashboard'), 2000);
      } else {
        await api(etape.url, { method:'POST', body: JSON.stringify(form) });
        await api('/onboarding/avancer', { method:'POST', body: JSON.stringify({ etape: etape.id }) });
        setSuccess(`✅ ${etape.emoji} ${etape.titre} créé(e) avec succès !`);
        setTimeout(() => {
          setSuccess('');
          setForm({});
          if (etapeIdx < ETAPES.length - 1) setEtapeIdx(e => e + 1);
        }, 1500);
      }
    } catch (e) { setError(e.message ?? 'Erreur lors de la sauvegarde'); }
    finally { setLoading(false); }
  };

  const skip = async () => {
    await api('/onboarding/ignorer', { method:'POST' }).catch(() => {});
    marquerOnboardingComplete();
    navigate('/dashboard');
  };

  const pctComplete = statut ? (statut.etapes.filter(e => e.complete).length / 5) * 100 : 0;

  return (
    <div style={{ minHeight:'100vh', background:'var(--bg)', display:'flex', alignItems:'center', justifyContent:'center', padding:'20px' }}>
      <div style={{ width:'100%', maxWidth:'600px' }}>

        <div style={{ textAlign:'center', marginBottom:'32px' }}>
          <div style={{ fontSize:'48px', marginBottom:'8px' }}>🎓</div>
          <h1 style={{ fontSize:'26px', fontWeight:900, color:'var(--text)' }}>
            Bienvenue sur <span style={{ color:'var(--accent)' }}>EduGest DZ</span>
          </h1>
          <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'6px' }}>
            Configurez votre établissement en 5 étapes simples — Moins de 5 minutes
          </p>
        </div>

        <div style={{ background:'var(--surface2)', borderRadius:'50px', height:'8px', marginBottom:'8px', overflow:'hidden' }}>
          <div style={{ height:'100%', background:'var(--accent)', borderRadius:'50px', width:`${pctComplete}%`, transition:'width 0.5s ease' }} />
        </div>
        <div style={{ display:'flex', justifyContent:'space-between', marginBottom:'28px' }}>
          {ETAPES.map((e, i) => {
            const done = statut?.etapes[i]?.complete;
            return (
              <div key={e.id} style={{ textAlign:'center', cursor: done ? 'pointer' : 'default' }}
                onClick={() => done && setEtapeIdx(i)}>
                <div style={{
                  width:'32px', height:'32px', borderRadius:'50%', margin:'0 auto 4px',
                  display:'flex', alignItems:'center', justifyContent:'center',
                  fontSize:'14px',
                  background: done ? 'rgba(16,185,129,0.2)' : i === etapeIdx ? 'rgba(37,99,235,0.2)' : 'var(--surface2)',
                  border: `2px solid ${done ? 'var(--green)' : i === etapeIdx ? 'var(--accent)' : 'var(--border)'}`,
                }}>
                  {done ? '✓' : e.emoji}
                </div>
                <div style={{ fontSize:'10px', color: i === etapeIdx ? 'var(--text)' : 'var(--muted)', fontWeight: i === etapeIdx ? 700 : 400, maxWidth:'70px' }}>
                  {e.titre.split(' ').slice(0,2).join(' ')}
                </div>
              </div>
            );
          })}
        </div>

        <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'20px', padding:'32px', boxShadow:'0 20px 60px rgba(0,0,0,0.3)' }}>
          <div style={{ textAlign:'center', marginBottom:'24px' }}>
            <div style={{ fontSize:'48px', marginBottom:'8px' }}>{etape.emoji}</div>
            <h2 style={{ fontSize:'20px', fontWeight:800, color:'var(--text)', marginBottom:'6px' }}>
              Étape {etapeIdx + 1} — {etape.titre}
            </h2>
            <p style={{ color:'var(--muted)', fontSize:'13px' }}>{etape.desc}</p>
          </div>

          {error && (
            <div style={{ background:'rgba(239,68,68,0.1)', border:'1px solid rgba(239,68,68,0.3)', borderRadius:'10px', padding:'12px', marginBottom:'16px', color:'#f87171', fontSize:'13px' }}>
              ❌ {error}
            </div>
          )}
          {success && (
            <div style={{ background:'rgba(16,185,129,0.1)', border:'1px solid rgba(16,185,129,0.3)', borderRadius:'10px', padding:'12px', marginBottom:'16px', color:'var(--green)', fontSize:'13px', fontWeight:600 }}>
              {success}
            </div>
          )}

          {etape.champs.length > 0 && (
            <div style={{ display:'grid', gridTemplateColumns: etape.champs.length > 2 ? '1fr 1fr' : '1fr', gap:'14px', marginBottom:'20px' }}>
              {etape.champs.map(ch => (
                <div key={ch.key}>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'5px' }}>
                    {ch.label} {ch.required && <span style={{ color:'var(--red)' }}>*</span>}
                  </label>
                  <input
                    type={ch.type ?? 'text'}
                    value={form[ch.key] ?? ''}
                    onChange={e => setForm(f => ({...f, [ch.key]: e.target.value}))}
                    placeholder={ch.placeholder}
                    required={ch.required}
                    style={{
                      width:'100%', background:'var(--surface2)', border:'1px solid var(--border)',
                      borderRadius:'10px', padding:'9px 12px', color:'var(--text)', fontSize:'13px',
                      outline:'none', boxSizing:'border-box',
                    }}
                  />
                </div>
              ))}
            </div>
          )}

          {etape.id === 5 && (
            <div style={{ textAlign:'center', padding:'20px 0' }}>
              <div style={{ fontSize:'64px', marginBottom:'16px' }}>🔔</div>
              <p style={{ color:'var(--muted)', fontSize:'13px', lineHeight:'1.7' }}>
                Cliquez pour recevoir votre première notification EduGest DZ.<br/>
                Elle apparaîtra dans votre cloche 🔔 en haut à droite.
              </p>
            </div>
          )}

          <div style={{ display:'flex', gap:'10px' }}>
            <button onClick={handleSubmit} disabled={loading || !!success}
              style={{
                flex:1, background: loading ? 'var(--surface2)' : 'var(--accent)',
                color: loading ? 'var(--muted)' : 'white',
                border:'none', borderRadius:'12px', padding:'14px', fontSize:'15px',
                fontWeight:800, cursor: loading ? 'not-allowed' : 'pointer',
              }}>
              {loading ? '⏳ En cours...' : `${etape.action} →`}
            </button>

            {etapeIdx < ETAPES.length - 1 && (
              <button onClick={() => { setForm({}); setEtapeIdx(e => e + 1); }}
                style={{ background:'none', border:'1px solid var(--border)', borderRadius:'12px', padding:'14px 20px', color:'var(--muted)', fontSize:'13px', cursor:'pointer' }}>
                Passer
              </button>
            )}
          </div>

          {etapeIdx === 0 && (
            <div style={{ textAlign:'center', marginTop:'16px' }}>
              <button onClick={skip} style={{ background:'none', border:'none', color:'var(--muted)', fontSize:'12px', cursor:'pointer', textDecoration:'underline' }}>
                Je suis déjà configuré, accéder au dashboard →
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
