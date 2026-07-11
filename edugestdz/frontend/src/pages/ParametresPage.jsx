import { useState, useEffect, useRef } from 'react';
import api, { getApiUrl, getToken } from '@api/client';

const JOURS = ['Samedi','Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi'];

export default function ParametresPage() {
  const [params,   setParams]   = useState(null);
  const [loading,  setLoading]  = useState(true);
  const [saving,   setSaving]   = useState(false);
  const [onglet,   setOnglet]   = useState('general');
  const [success,  setSuccess]  = useState('');
  const [error,    setError]    = useState('');
  const [logoPreview, setLogoPreview] = useState(null);
  const fileInputRef = useRef();

  useEffect(() => {
    api('/parametres')
      .then(r => { if (r.success) setParams(r.data ?? {}); })
      .catch(() => setParams({}))
      .finally(() => setLoading(false));
  }, []);

  const sauvegarder = async (section, data) => {
    setSaving(true); setError(''); setSuccess('');
    try {
      await api('/parametres', { method:'PATCH', body: JSON.stringify(data) });
      setSuccess('Paramètres sauvegardés !');
      setTimeout(() => setSuccess(''), 3000);
    } catch (e) { setError(e.message); }
    finally { setSaving(false); }
  };

  const uploadLogo = async (file) => {
    const formData = new FormData();
    formData.append('logo', file);
    try {
      const res = await fetch(getApiUrl('/parametres/logo'), {
        method: 'POST',
        headers: { Authorization: `Bearer ${getToken()}` },
        body: formData,
      });
      const data = await res.json();
      if (data.success) { setLogoPreview(data.logo_url); setSuccess('Logo mis à jour !'); }
      else setError(data.message);
    } catch (e) { setError('Erreur upload logo'); }
  };

  const horairesParDefaut = JOURS.map(j => ({ jour: j, ouvert: !['Vendredi'].includes(j), debut:'08:00', fin:'18:00' }));

  if (loading) return <div style={{ padding:'40px', textAlign:'center', color:'var(--muted)' }}>Chargement des paramètres...</div>;

  const onglets = [
    { id:'general',   label:'Général',    icon:'' },
    { id:'contact',   label:'Contact',     icon:'' },
    { id:'horaires',  label:'Horaires',    icon:'' },
    { id:'tarifs',    label:'Tarifs',      icon:'' },
    { id:'email',     label:'Email SMTP',  icon:'' },
    { id:'niveaux',   label:'Niveaux',     icon:'' },
  ];

  return (
    <div className="animate-fadeIn" style={{ maxWidth:'900px' }}>
      <div style={{ marginBottom:'24px' }}>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'var(--text)' }}>Paramètres de l'établissement</h1>
        <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'4px' }}>
          Configuration de votre école — visible par vos parents et élèves
        </p>
      </div>

      {success && (
        <div style={{ background:'rgba(16,185,129,0.1)', border:'1px solid rgba(16,185,129,0.3)', borderRadius:'10px', padding:'12px 16px', marginBottom:'16px', color:'var(--green)', fontSize:'13px', fontWeight:600 }}>
          {success}
        </div>
      )}
      {error && (
        <div style={{ background:'rgba(239,68,68,0.1)', border:'1px solid rgba(239,68,68,0.3)', borderRadius:'10px', padding:'12px 16px', marginBottom:'16px', color:'#f87171', fontSize:'13px' }}>
          {error}
        </div>
      )}

      <div style={{ display:'grid', gridTemplateColumns:'200px 1fr', gap:'20px', alignItems:'start' }}>

        <div style={{ display:'flex', flexDirection:'column', gap:'4px' }}>
          {onglets.map(o => (
            <button key={o.id} onClick={() => setOnglet(o.id)}
              style={{
                background: onglet === o.id ? 'rgba(37,99,235,0.12)' : 'none',
                border: onglet === o.id ? '1px solid rgba(37,99,235,0.3)' : '1px solid transparent',
                color: onglet === o.id ? 'var(--accent)' : 'var(--muted)',
                padding:'10px 14px', borderRadius:'10px', cursor:'pointer',
                textAlign:'left', fontWeight: onglet === o.id ? 700 : 500, fontSize:'13px',
                display:'flex', alignItems:'center', gap:'8px', transition:'all 0.15s',
              }}>
              {o.label}
            </button>
          ))}
        </div>

        <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'16px', padding:'28px' }}>

          {onglet === 'general' && (
            <div style={{ display:'flex', flexDirection:'column', gap:'20px' }}>
              <div>
                <label style={{ fontSize:'12px', fontWeight:700, color:'var(--muted)', textTransform:'uppercase', display:'block', marginBottom:'16px' }}>Logo de l'établissement</label>
                <div style={{ display:'flex', alignItems:'center', gap:'20px' }}>
                  <div style={{
                    width:'80px', height:'80px', borderRadius:'12px',
                    background:'var(--surface2)', border:'2px dashed var(--border)',
                    display:'flex', alignItems:'center', justifyContent:'center',
                    overflow:'hidden', cursor:'pointer',
                  }} onClick={() => fileInputRef.current?.click()}>
                    {(logoPreview || params?.logo_url) ? (
                      <img src={logoPreview || params.logo_url} alt="Logo" style={{ width:'100%', height:'100%', objectFit:'cover' }} />
                    ) : (
                      <span style={{ fontSize:'24px' }}>🏫</span>
                    )}
                  </div>
                  <div>
                    <button onClick={() => fileInputRef.current?.click()}
                      style={{ background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 16px', color:'var(--text)', fontSize:'13px', cursor:'pointer', fontWeight:600 }}>
                      Changer le logo
                    </button>
                    <p style={{ color:'var(--muted)', fontSize:'11px', marginTop:'4px' }}>PNG, JPG ou WebP · Max 2 MB</p>
                  </div>
                </div>
                <input ref={fileInputRef} type="file" accept="image/*" style={{ display:'none' }}
                  onChange={e => { if (e.target.files[0]) uploadLogo(e.target.files[0]); }} />
              </div>

              <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'16px' }}>
                <div>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>Nom de l'établissement</label>
                  <input defaultValue={params?.nom_ecole ?? ''} id="nom_ecole"
                    style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'9px 12px', color:'var(--text)', fontSize:'13px', outline:'none', boxSizing:'border-box' }} />
                </div>
                <div>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>Couleur principale</label>
                  <input type="color" defaultValue={params?.couleur_principale ?? '#2563eb'} id="couleur_principale"
                    style={{ height:'40px', width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', cursor:'pointer', padding:'4px' }} />
                </div>
              </div>

              <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'16px' }}>
                <div>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>Devise</label>
                  <select defaultValue={params?.devise ?? 'DA'} id="devise"
                    style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'9px 12px', color:'var(--text)', fontSize:'13px', outline:'none' }}>
                    <option value="DA">DA (Dinar Algérien)</option>
                    <option value="EUR">EUR (Euro)</option>
                    <option value="USD">USD (Dollar)</option>
                  </select>
                </div>
                <div>
                  <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>Langue par défaut</label>
                  <select defaultValue={params?.langue_defaut ?? 'fr'} id="langue_defaut"
                    style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'9px 12px', color:'var(--text)', fontSize:'13px', outline:'none' }}>
                    <option value="fr">Français</option>
                    <option value="ar">العربية</option>
                    <option value="en">English</option>
                    <option value="dz">Darija</option>
                  </select>
                </div>
              </div>

              <button onClick={() => sauvegarder('general', {
                nom_ecole: document.getElementById('nom_ecole').value,
                couleur_principale: document.getElementById('couleur_principale').value,
                devise: document.getElementById('devise').value,
                langue_defaut: document.getElementById('langue_defaut').value,
              })} disabled={saving}
                style={{ alignSelf:'flex-start', background:'var(--accent)', color:'white', border:'none', borderRadius:'10px', padding:'10px 24px', fontSize:'13px', fontWeight:700, cursor:'pointer' }}>
                {saving ? 'Sauvegarde...' : 'Sauvegarder'}
              </button>
            </div>
          )}

          {onglet === 'email' && (
            <SmtpSection params={params?.smtp_config} onSave={sauvegarder} saving={saving} />
          )}

          {onglet === 'horaires' && (
            <HorairesSection horaires={params?.horaires_ouverture ?? horairesParDefaut} onSave={sauvegarder} saving={saving} />
          )}

          {['contact','tarifs','niveaux'].includes(onglet) && (
            <div style={{ textAlign:'center', padding:'40px', color:'var(--muted)' }}>
              <div style={{ fontSize:'32px', marginBottom:'12px' }}>🚧</div>
              <p style={{ fontWeight:700, color:'var(--text)' }}>Section {onglet} — À remplir</p>
              <p style={{ fontSize:'13px', marginTop:'4px' }}>Configurer les champs de cette section selon les besoins spécifiques.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function SmtpSection({ params, onSave, saving }) {
  const [smtp, setSmtp] = useState(params ?? {});
  const [testing, setTesting] = useState(false);
  const [testEmail, setTestEmail] = useState('');
  const [testResult, setTestResult] = useState(null);

  const testerSmtp = async () => {
    if (!testEmail) return;
    setTesting(true); setTestResult(null);
    try {
      const res = await api('/parametres/tester-smtp', {
        method: 'POST',
        body: JSON.stringify({ ...smtp, to: testEmail }),
      });
      setTestResult({ ok: true, msg: res.message });
    } catch (e) { setTestResult({ ok: false, msg: e.message }); }
    finally { setTesting(false); }
  };

  return (
    <div style={{ display:'flex', flexDirection:'column', gap:'16px' }}>
      <div style={{ background:'rgba(234,179,8,0.08)', border:'1px solid rgba(234,179,8,0.2)', borderRadius:'10px', padding:'12px 14px', fontSize:'12px', color:'#ca8a04' }}>
        Configurez votre propre serveur SMTP pour que les emails envoyés à vos parents et élèves viennent de votre adresse (ex: noreply@mon-ecole.dz).
      </div>
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'12px' }}>
        {[
          ['host', 'Serveur SMTP', 'smtp.gmail.com'],
          ['port', 'Port', '587'],
          ['username', 'Identifiant', 'votre@email.dz'],
          ['from_address', 'Email expéditeur', 'noreply@mon-ecole.dz'],
          ['from_name', 'Nom expéditeur', 'Mon École DZ'],
        ].map(([k, label, ph]) => (
          <div key={k}>
            <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'5px' }}>{label}</label>
            <input type={k === 'port' ? 'number' : 'text'} value={smtp[k] ?? ''} onChange={e => setSmtp(s => ({...s, [k]: e.target.value}))} placeholder={ph}
              style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 12px', color:'var(--text)', fontSize:'13px', outline:'none', boxSizing:'border-box' }} />
          </div>
        ))}
        <div>
          <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'5px' }}>Mot de passe SMTP</label>
          <input type="password" value={smtp.password ?? ''} onChange={e => setSmtp(s => ({...s, password: e.target.value}))} placeholder="Mot de passe ou App Password"
            style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 12px', color:'var(--text)', fontSize:'13px', outline:'none', boxSizing:'border-box' }} />
        </div>
      </div>
      <div style={{ display:'flex', gap:'10px', alignItems:'center', flexWrap:'wrap' }}>
        <input value={testEmail} onChange={e => setTestEmail(e.target.value)} placeholder="Email de test (votre@email.dz)" type="email"
          style={{ flex:1, minWidth:'200px', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 12px', color:'var(--text)', fontSize:'13px', outline:'none' }} />
        <button onClick={testerSmtp} disabled={testing || !testEmail}
          style={{ background:'var(--teal,#0891b2)', color:'white', border:'none', borderRadius:'8px', padding:'9px 16px', fontSize:'13px', fontWeight:700, cursor:'pointer', whiteSpace:'nowrap' }}>
          {testing ? 'Test...' : 'Tester'}
        </button>
        <button onClick={() => onSave('smtp', { smtp_config: smtp })} disabled={saving}
          style={{ background:'var(--accent)', color:'white', border:'none', borderRadius:'8px', padding:'9px 16px', fontSize:'13px', fontWeight:700, cursor:'pointer' }}>
          {saving ? '...' : 'Sauvegarder'}
        </button>
      </div>
      {testResult && (
        <div style={{ background: testResult.ok ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)', border:`1px solid ${testResult.ok ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)'}`, borderRadius:'8px', padding:'10px 14px', fontSize:'12px', color: testResult.ok ? 'var(--green)' : '#f87171' }}>
          {testResult.ok ? '' : ''} {testResult.msg}
        </div>
      )}
    </div>
  );
}

function HorairesSection({ horaires, onSave, saving }) {
  const [h, setH] = useState(Array.isArray(horaires) ? horaires : JOURS.map(j => ({jour:j, ouvert:true, debut:'08:00', fin:'18:00'})));

  return (
    <div>
      <div style={{ display:'flex', flexDirection:'column', gap:'8px', marginBottom:'16px' }}>
        {h.map((jour, i) => (
          <div key={jour.jour} style={{ display:'flex', alignItems:'center', gap:'12px', background:'var(--surface2)', borderRadius:'10px', padding:'10px 14px' }}>
            <label style={{ display:'flex', alignItems:'center', gap:'6px', width:'100px', cursor:'pointer' }}>
              <input type="checkbox" checked={jour.ouvert} onChange={e => setH(prev => prev.map((p,j) => j===i ? {...p, ouvert:e.target.checked} : p))} />
              <span style={{ fontSize:'13px', fontWeight:600, color: jour.ouvert ? 'var(--text)' : 'var(--muted)' }}>{jour.jour}</span>
            </label>
            {jour.ouvert && (<>
              <input type="time" value={jour.debut} onChange={e => setH(prev => prev.map((p,j) => j===i ? {...p, debut:e.target.value} : p))}
                style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'6px', padding:'4px 8px', color:'var(--text)', fontSize:'12px', outline:'none' }} />
              <span style={{ color:'var(--muted)', fontSize:'12px' }}>→</span>
              <input type="time" value={jour.fin} onChange={e => setH(prev => prev.map((p,j) => j===i ? {...p, fin:e.target.value} : p))}
                style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'6px', padding:'4px 8px', color:'var(--text)', fontSize:'12px', outline:'none' }} />
            </>)}
            {!jour.ouvert && <span style={{ color:'var(--muted)', fontSize:'12px' }}>Fermé</span>}
          </div>
        ))}
      </div>
      <button onClick={() => onSave('horaires', { horaires_ouverture: h })} disabled={saving}
        style={{ background:'var(--accent)', color:'white', border:'none', borderRadius:'10px', padding:'10px 24px', fontSize:'13px', fontWeight:700, cursor:'pointer' }}>
        {saving ? 'Sauvegarde...' : 'Sauvegarder les horaires'}
      </button>
    </div>
  );
}
