import { useState, useEffect } from 'react';
import { User, Lock, Bell, Eye, EyeOff, Save } from 'lucide-react';

const api = (path, opts) => fetch(`/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, 'Content-Type':'application/json' },
  ...opts,
}).then(r => r.json());

export default function ProfilePage() {
  const [user, setUser]           = useState(null);
  const [tab, setTab]             = useState('profil');
  const [showPwd, setShowPwd]     = useState(false);
  const [saving, setSaving]       = useState(false);
  const [msg, setMsg]             = useState('');

  const [form, setForm] = useState({ nom:'', prenom:'', email:'', telephone:'' });
  const [pwdForm, setPwdForm] = useState({ current_password:'', new_password:'', new_password_confirmation:'' });

  useEffect(() => {
    api('/auth/me').then(r => {
      const u = r?.data ?? r?.user ?? {};
      setUser(u);
      setForm({ nom: u.nom ?? '', prenom: u.prenom ?? '', email: u.email ?? '', telephone: u.telephone ?? '' });
    });
  }, []);

  const saveProfil = async () => {
    setSaving(true);
    const r = await api('/auth/me', { method:'PUT', body: JSON.stringify(form) });
    setMsg(r.success ? 'Profil mis à jour' : 'Erreur : ' + r.message);
    setSaving(false);
    setTimeout(() => setMsg(''), 3000);
  };

  const savePassword = async () => {
    if (pwdForm.new_password !== pwdForm.new_password_confirmation) { setMsg('Mots de passe différents'); return; }
    setSaving(true);
    const r = await api('/auth/password', {
      method:'PUT',
      body: JSON.stringify({ current_password: pwdForm.current_password, new_password: pwdForm.new_password, new_password_confirmation: pwdForm.new_password_confirmation }),
    });
    setMsg(r.success ? 'Mot de passe modifié' : (r.error?.message ?? r.message ?? 'Erreur'));
    setSaving(false);
    setPwdForm({ current_password:'', new_password:'', new_password_confirmation:'' });
    setTimeout(() => setMsg(''), 3000);
  };

  const Input = ({ label, value, onChange, type='text' }) => (
    <div style={{ marginBottom:'12px' }}>
      <label style={{ fontSize:'10px', color:'#64748b', display:'block', marginBottom:'4px' }}>{label}</label>
      <div style={{ position:'relative' }}>
        <input type={type === 'password' && showPwd ? 'text' : type}
          value={value} onChange={e => onChange(e.target.value)}
          style={{ width:'100%', background:'#1e293b', border:'1px solid #334155',
            borderRadius:'8px', color:'#e2e8f0', padding:'10px 12px', fontSize:'12px' }} />
        {type === 'password' && (
          <button onClick={() => setShowPwd(!showPwd)}
            style={{ position:'absolute', right:'10px', top:'50%', transform:'translateY(-50%)',
              background:'none', border:'none', cursor:'pointer', color:'#64748b' }}>
            {showPwd ? <EyeOff size={14} /> : <Eye size={14} />}
          </button>
        )}
      </div>
    </div>
  );

  return (
    <div style={{ padding:'24px', background:'#08090f', minHeight:'100vh', maxWidth:'700px', margin:'0 auto' }}>
      <div style={{ marginBottom:'24px' }}>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}>Mon Profil</h1>
        {user && <p style={{ fontSize:'12px', color:'#64748b' }}>{user.email} · Rôle : {user.role}</p>}
      </div>

      {msg && (
        <div style={{ background: msg.includes('✅') ? '#0d2515' : '#1a0808',
          border:`1px solid ${msg.includes('✅') ? '#16a34a' : '#b91c1c'}`,
          borderRadius:'8px', padding:'10px 14px', marginBottom:'16px', fontSize:'12px',
          color: msg.includes('✅') ? '#4ade80' : '#f87171' }}>
          {msg}
        </div>
      )}

      <div style={{ display:'flex', gap:'4px', marginBottom:'20px' }}>
        {[['profil','Profil'],['password','Mot de passe'],['security','Sécurité']].map(([id, label]) => (
          <button key={id} onClick={() => setTab(id)} style={{
            background: tab === id ? '#1e3a5f' : '#111318',
            color: tab === id ? '#60a5fa' : '#64748b',
            border: `1px solid ${tab === id ? '#3b82f6' : '#1e293b'}`,
            borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer',
          }}>{label}</button>
        ))}
      </div>

      <div style={{ background:'#111318', border:'1px solid #1e293b', borderRadius:'12px', padding:'20px' }}>
        {tab === 'profil' && (
          <>
            <Input label="Nom"       value={form.nom}       onChange={v => setForm(f=>({...f, nom:v}))} />
            <Input label="Prénom"    value={form.prenom}    onChange={v => setForm(f=>({...f, prenom:v}))} />
            <Input label="Email"     value={form.email}     onChange={v => setForm(f=>({...f, email:v}))} type="email" />
            <Input label="Téléphone" value={form.telephone} onChange={v => setForm(f=>({...f, telephone:v}))} />
            <button onClick={saveProfil} disabled={saving} style={{
              background:'linear-gradient(135deg,#3b82f6,#1d4ed8)', color:'#fff',
              border:'none', borderRadius:'8px', padding:'10px 20px',
              fontWeight:700, fontSize:'12px', cursor:'pointer', marginTop:'4px',
            }}>
              {saving ? 'Enregistrement...' : 'Enregistrer'}
            </button>
          </>
        )}

        {tab === 'password' && (
          <>
            <Input label="Mot de passe actuel"    value={pwdForm.current_password}  onChange={v => setPwdForm(f=>({...f, current_password:v}))}  type="password" />
            <Input label="Nouveau mot de passe"   value={pwdForm.new_password}      onChange={v => setPwdForm(f=>({...f, new_password:v}))}      type="password" />
            <Input label="Confirmer mot de passe" value={pwdForm.new_password_confirmation}  onChange={v => setPwdForm(f=>({...f, new_password_confirmation:v}))}  type="password" />
            <button onClick={savePassword} disabled={saving} style={{
              background:'linear-gradient(135deg,#3b82f6,#1d4ed8)', color:'#fff',
              border:'none', borderRadius:'8px', padding:'10px 20px',
              fontWeight:700, fontSize:'12px', cursor:'pointer',
            }}>
              {saving ? 'Modification...' : 'Modifier le mot de passe'}
            </button>
          </>
        )}

        {tab === 'security' && (
          <div>
            <div style={{ marginBottom:'16px' }}>
              <div style={{ fontSize:'13px', fontWeight:700, color:'#f1f5f9', marginBottom:'4px' }}>
                Double authentification (2FA)
              </div>
              <div style={{ fontSize:'11px', color:'#64748b', marginBottom:'10px' }}>
                Protégez votre compte avec un code TOTP (Google Authenticator, Authy).
              </div>
              <button style={{ background:'#14532d', color:'#4ade80', border:'none',
                borderRadius:'8px', padding:'8px 14px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                Activer la 2FA
              </button>
            </div>
            <div style={{ borderTop:'1px solid #1e293b', paddingTop:'16px' }}>
              <div style={{ fontSize:'13px', fontWeight:700, color:'#f1f5f9', marginBottom:'4px' }}>
                Sessions actives
              </div>
              <div style={{ fontSize:'11px', color:'#64748b' }}>
                Gérez vos appareils connectés depuis le panel admin.
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
