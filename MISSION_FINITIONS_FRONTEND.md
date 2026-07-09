# 🤖 MISSION DEEPSEEK — Finitions Frontend + Schedulers + Super-Admin
## EduGest DZ · Branche : develop · 3 Juillet 2026
## Tests actuels : 423 ✅ · Objectif : ≥ 440 ✅ (0 régression)

---

## CONTEXTE

Après audit complet du repo, voici ce qui reste côté frontend React et backend
pour que le logiciel soit **100% utilisable** en production.

Cette mission couvre **4 zones** :
1. **Frontend** — 4 pages manquantes + APIs réelles branchées sur pages existantes
2. **Schedulers** — tâches automatiques (SMS 8h30, relances factures, alertes)
3. **Super-Admin** — endpoints + page React gestion tenants
4. **Rapports** — iCal planning + PDF absences mensuel

### RÈGLES ABSOLUES
1. **0 régression** — les 423 tests existants restent verts
2. **PostgreSQL uniquement** — jamais SQLite
3. **Ne pas modifier** les contrôleurs existants — seulement ajouter
4. **Même pattern** que les pages existantes (dark theme, même style CSS inline)

---

## ÉTAPE 0 — Synchroniser develop

```bash
git checkout develop
git pull origin main
```

---

## ═══════════════════════════════════════════════
## PARTIE A — FRONTEND REACT (pages manquantes)
## ═══════════════════════════════════════════════

## ÉTAPE 1 — DashboardPage : brancher les vraies APIs

**Modifier :** `edugestdz/frontend/src/pages/DashboardPage.jsx`

Remplacer les données mockées par de vrais appels API.
Le dashboard doit afficher en temps réel :

```jsx
import { useState, useEffect } from 'react';
import { Users, TrendingUp, AlertCircle, Calendar, DollarSign, Clock, BookOpen, CheckCircle } from 'lucide-react';

const api = (path) => fetch(`/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
}).then(r => r.json());

export default function DashboardPage() {
  const [stats, setStats]     = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      api('/eleves?per_page=1'),
      api('/finance/tableau-bord'),
      api('/absences?date=' + new Date().toISOString().split('T')[0]),
      api('/planning/aujourd-hui'),
    ]).then(([elevesRes, financeRes, absencesRes, planningRes]) => {
      setStats({
        total_eleves:  elevesRes?.data?.meta?.total ?? 0,
        eleves_actifs: elevesRes?.data?.stats?.actifs ?? 0,
        ca_mois:       financeRes?.data?.ca_mois ?? 0,
        impayes:       financeRes?.data?.impayes ?? 0,
        nb_impayes:    financeRes?.data?.nb_impayes ?? 0,
        absences_jour: absencesRes?.data?.total ?? 0,
        seances_jour:  planningRes?.data?.total ?? 0,
      });
    }).catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const fmt = (n) => new Intl.NumberFormat('fr-DZ').format(n ?? 0);

  const StatCard = ({ icon: Icon, label, value, color, sub }) => (
    <div style={{
      background:'#111318', border:'1px solid #1e293b', borderRadius:'12px',
      padding:'20px', display:'flex', alignItems:'center', gap:'16px',
    }}>
      <div style={{
        width:'48px', height:'48px', borderRadius:'12px',
        background: color + '22', display:'flex', alignItems:'center',
        justifyContent:'center', flexShrink:0,
      }}>
        <Icon size={22} color={color} />
      </div>
      <div>
        <div style={{ fontSize:'11px', color:'#64748b', marginBottom:'2px' }}>{label}</div>
        <div style={{ fontSize:'24px', fontWeight:900, color:'#f1f5f9' }}>
          {loading ? '...' : value}
        </div>
        {sub && <div style={{ fontSize:'10px', color:'#475569', marginTop:'2px' }}>{sub}</div>}
      </div>
    </div>
  );

  return (
    <div style={{ padding:'24px', background:'#08090f', minHeight:'100vh' }}>
      <div style={{ marginBottom:'24px' }}>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}>
          🎓 Tableau de bord
        </h1>
        <p style={{ fontSize:'12px', color:'#64748b' }}>
          {new Date().toLocaleDateString('fr-DZ', { weekday:'long', year:'numeric', month:'long', day:'numeric' })}
        </p>
      </div>

      <div style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:'12px', marginBottom:'24px' }}>
        <StatCard icon={Users}      label="Élèves actifs"   value={fmt(stats?.eleves_actifs)}  color="#4ade80" />
        <StatCard icon={DollarSign} label="CA ce mois"      value={fmt(stats?.ca_mois) + ' DA'} color="#60a5fa" />
        <StatCard icon={AlertCircle}label="Impayés"         value={fmt(stats?.impayes) + ' DA'}
          sub={`${stats?.nb_impayes ?? 0} facture(s)`} color="#f87171" />
        <StatCard icon={Clock}      label="Absences aujourd'hui" value={fmt(stats?.absences_jour)} color="#fb923c" />
      </div>

      <div style={{ display:'grid', gridTemplateColumns:'2fr 1fr', gap:'12px' }}>
        {/* Évolution CA 6 mois */}
        <div style={{ background:'#111318', border:'1px solid #1e293b', borderRadius:'12px', padding:'20px' }}>
          <div style={{ fontSize:'12px', fontWeight:700, color:'#60a5fa', marginBottom:'16px' }}>
            📈 Évolution CA — 6 derniers mois
          </div>
          {loading ? (
            <div style={{ color:'#475569', fontSize:'12px' }}>Chargement...</div>
          ) : (
            <div style={{ fontSize:'11px', color:'#64748b' }}>
              Données disponibles après branchement complet API finance.
            </div>
          )}
        </div>

        {/* Actions rapides */}
        <div style={{ background:'#111318', border:'1px solid #1e293b', borderRadius:'12px', padding:'20px' }}>
          <div style={{ fontSize:'12px', fontWeight:700, color:'#60a5fa', marginBottom:'16px' }}>
            ⚡ Actions rapides
          </div>
          {[
            { label:'Nouvel élève',     path:'/eleves',     emoji:'👤' },
            { label:'Déclarer absence', path:'/absences',   emoji:'✅' },
            { label:'Émettre facture',  path:'/factures',   emoji:'💰' },
            { label:'Voir planning',    path:'/planning',   emoji:'📅' },
          ].map(a => (
            <a key={a.path} href={a.path} style={{
              display:'flex', alignItems:'center', gap:'10px',
              padding:'8px 10px', borderRadius:'8px', marginBottom:'4px',
              background:'#1e293b', color:'#e2e8f0', textDecoration:'none',
              fontSize:'12px', fontWeight:600,
            }}>
              <span>{a.emoji}</span>{a.label}
            </a>
          ))}
        </div>
      </div>
    </div>
  );
}
```

---

## ÉTAPE 2 — PointagePage : interface pointage enseignants

**Créer :** `edugestdz/frontend/src/pages/PointagePage.jsx`

```jsx
import { useState, useEffect } from 'react';
import { Clock, CheckCircle, XCircle, AlertCircle, Search } from 'lucide-react';

const api = (path, opts) => fetch(`/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, 'Content-Type':'application/json' },
  ...opts,
}).then(r => r.json());

export default function PointagePage() {
  const [enseignants, setEnseignants] = useState([]);
  const [pointages, setPointages]     = useState([]);
  const [loading, setLoading]         = useState(true);
  const [search, setSearch]           = useState('');
  const [today]                       = useState(new Date().toISOString().split('T')[0]);

  useEffect(() => {
    Promise.all([
      api('/enseignants?per_page=100'),
      api('/pointage/enseignants?date=' + today),
    ]).then(([ens, pt]) => {
      setEnseignants(ens?.data?.data ?? []);
      setPointages(pt?.data ?? []);
    }).finally(() => setLoading(false));
  }, [today]);

  const pointageEnseignant = (id) =>
    pointages.find(p => p.enseignant_id === id);

  const pointer = async (enseignantId, type) => {
    await api('/pointage/enseignants', {
      method: 'POST',
      body: JSON.stringify({ enseignant_id: enseignantId, type, date: today,
        heure: new Date().toLocaleTimeString('fr-FR', { hour:'2-digit', minute:'2-digit' }) }),
    });
    const pt = await api('/pointage/enseignants?date=' + today);
    setPointages(pt?.data ?? []);
  };

  const filtered = enseignants.filter(e =>
    `${e.nom} ${e.prenom}`.toLowerCase().includes(search.toLowerCase())
  );

  const StatusBadge = ({ pt }) => {
    if (!pt) return <span style={{ background:'#1e293b', color:'#64748b', fontSize:'10px', padding:'3px 8px', borderRadius:'20px' }}>Non pointé</span>;
    if (pt.arrivee && pt.depart) return <span style={{ background:'#14532d', color:'#4ade80', fontSize:'10px', padding:'3px 8px', borderRadius:'20px' }}>✅ Journée complète</span>;
    if (pt.arrivee) return <span style={{ background:'#1e3a5f', color:'#60a5fa', fontSize:'10px', padding:'3px 8px', borderRadius:'20px' }}>🟡 En cours ({pt.arrivee})</span>;
    return <span style={{ background:'#450a0a', color:'#f87171', fontSize:'10px', padding:'3px 8px', borderRadius:'20px' }}>❌ Absent</span>;
  };

  return (
    <div style={{ padding:'24px', background:'#08090f', minHeight:'100vh' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'24px' }}>
        <div>
          <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}>🏷️ Pointage Enseignants</h1>
          <p style={{ fontSize:'12px', color:'#64748b' }}>
            {new Date().toLocaleDateString('fr-DZ', { weekday:'long', year:'numeric', month:'long', day:'numeric' })}
          </p>
        </div>
        <div style={{ display:'flex', gap:'10px' }}>
          <div style={{ background:'#0d2515', border:'1px solid #16a34a', borderRadius:'8px', padding:'8px 14px', textAlign:'center' }}>
            <div style={{ fontSize:'20px', fontWeight:900, color:'#4ade80' }}>
              {pointages.filter(p => p.arrivee).length}
            </div>
            <div style={{ fontSize:'9px', color:'#16a34a' }}>PRÉSENTS</div>
          </div>
          <div style={{ background:'#1a0808', border:'1px solid #b91c1c', borderRadius:'8px', padding:'8px 14px', textAlign:'center' }}>
            <div style={{ fontSize:'20px', fontWeight:900, color:'#f87171' }}>
              {enseignants.length - pointages.filter(p => p.arrivee).length}
            </div>
            <div style={{ fontSize:'9px', color:'#b91c1c' }}>ABSENTS</div>
          </div>
        </div>
      </div>

      {/* Recherche */}
      <div style={{ position:'relative', marginBottom:'16px' }}>
        <Search size={14} style={{ position:'absolute', left:'12px', top:'50%', transform:'translateY(-50%)', color:'#475569' }} />
        <input value={search} onChange={e => setSearch(e.target.value)}
          placeholder="Rechercher un enseignant..."
          style={{ width:'100%', background:'#111318', border:'1px solid #1e293b', borderRadius:'8px',
            color:'#e2e8f0', padding:'10px 12px 10px 34px', fontSize:'12px' }} />
      </div>

      {/* Liste */}
      <div style={{ display:'grid', gap:'8px' }}>
        {loading ? (
          <div style={{ color:'#475569', textAlign:'center', padding:'40px' }}>Chargement...</div>
        ) : filtered.map(ens => {
          const pt = pointageEnseignant(ens.id);
          return (
            <div key={ens.id} style={{
              background:'#111318', border:'1px solid #1e293b', borderRadius:'10px',
              padding:'14px 16px', display:'flex', alignItems:'center', gap:'14px',
            }}>
              <div style={{
                width:'40px', height:'40px', borderRadius:'10px',
                background:'#1e293b', display:'flex', alignItems:'center',
                justifyContent:'center', fontSize:'16px', flexShrink:0,
              }}>👨‍🏫</div>

              <div style={{ flex:1 }}>
                <div style={{ fontWeight:700, fontSize:'13px', color:'#f1f5f9' }}>
                  {ens.nom} {ens.prenom}
                </div>
                <div style={{ fontSize:'10px', color:'#64748b' }}>
                  {ens.specialite ?? 'Enseignant'} {pt?.arrivee ? `· Arrivé à ${pt.arrivee}` : ''}
                </div>
              </div>

              <StatusBadge pt={pt} />

              <div style={{ display:'flex', gap:'6px' }}>
                {!pt?.arrivee && (
                  <button onClick={() => pointer(ens.id, 'arrivée')}
                    style={{ background:'#14532d', color:'#4ade80', border:'none', borderRadius:'6px',
                      padding:'6px 12px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                    ✅ Arrivée
                  </button>
                )}
                {pt?.arrivee && !pt?.depart && (
                  <button onClick={() => pointer(ens.id, 'départ')}
                    style={{ background:'#1e3a5f', color:'#60a5fa', border:'none', borderRadius:'6px',
                      padding:'6px 12px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                    🚪 Départ
                  </button>
                )}
                {!pt?.arrivee && (
                  <button onClick={() => pointer(ens.id, 'absent')}
                    style={{ background:'#450a0a', color:'#f87171', border:'none', borderRadius:'6px',
                      padding:'6px 12px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                    ❌ Absent
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
```

---

## ÉTAPE 3 — SuperAdminPage : gestion globale des tenants

**Créer :** `edugestdz/frontend/src/pages/SuperAdminPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import { Shield, Users, Building2, CheckCircle, XCircle, BarChart3 } from 'lucide-react';

const api = (path) => fetch(`/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
}).then(r => r.json());

export default function SuperAdminPage() {
  const [tenants, setTenants]   = useState([]);
  const [stats, setStats]       = useState(null);
  const [loading, setLoading]   = useState(true);
  const [activeTab, setActiveTab] = useState('tenants');

  useEffect(() => {
    Promise.all([
      api('/super-admin/tenants'),
      api('/super-admin/stats'),
    ]).then(([t, s]) => {
      setTenants(t?.data ?? []);
      setStats(s?.data ?? {});
    }).finally(() => setLoading(false));
  }, []);

  const suspendre = async (id) => {
    if (!confirm('Suspendre ce tenant ?')) return;
    await fetch(`/api/v1/super-admin/tenants/${id}/suspendre`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
    });
    setTenants(t => t.map(x => x.id === id ? { ...x, actif: false } : x));
  };

  const verifierMarketplace = async (tenantId) => {
    await fetch(`/api/v1/super-admin/marketplace/${tenantId}/verifier`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
    });
    alert('Tenant vérifié sur la marketplace ✅');
  };

  const StatBox = ({ label, value, color }) => (
    <div style={{ background:'#111318', border:'1px solid #1e293b', borderRadius:'10px', padding:'16px', textAlign:'center' }}>
      <div style={{ fontSize:'28px', fontWeight:900, color }}>{loading ? '...' : value}</div>
      <div style={{ fontSize:'10px', color:'#64748b', marginTop:'2px' }}>{label}</div>
    </div>
  );

  return (
    <div style={{ padding:'24px', background:'#08090f', minHeight:'100vh' }}>
      <div style={{ marginBottom:'24px' }}>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff', display:'flex', alignItems:'center', gap:'10px' }}>
          <Shield size={22} color="#f59e0b" /> Super-Admin — Gestion plateforme
        </h1>
        <p style={{ fontSize:'12px', color:'#64748b' }}>Vue globale de tous les centres EduGest DZ</p>
      </div>

      {/* Stats globales */}
      <div style={{ display:'grid', gridTemplateColumns:'repeat(5,1fr)', gap:'10px', marginBottom:'24px' }}>
        <StatBox label="Total tenants"    value={stats?.total_tenants ?? 0}    color="#60a5fa" />
        <StatBox label="Tenants actifs"   value={stats?.tenants_actifs ?? 0}   color="#4ade80" />
        <StatBox label="Total élèves"     value={stats?.total_eleves ?? 0}     color="#a78bfa" />
        <StatBox label="CA global (DA)"   value={Intl.NumberFormat('fr').format(stats?.ca_global ?? 0)} color="#fb923c" />
        <StatBox label="Marketplace"      value={stats?.profils_marketplace ?? 0} color="#f59e0b" />
      </div>

      {/* Tabs */}
      <div style={{ display:'flex', gap:'4px', marginBottom:'16px' }}>
        {['tenants', 'marketplace'].map(tab => (
          <button key={tab} onClick={() => setActiveTab(tab)}
            style={{
              background: activeTab === tab ? '#1e3a5f' : '#111318',
              color: activeTab === tab ? '#60a5fa' : '#64748b',
              border: `1px solid ${activeTab === tab ? '#3b82f6' : '#1e293b'}`,
              borderRadius:'8px', padding:'8px 16px', fontSize:'11px',
              fontWeight:700, cursor:'pointer', textTransform:'uppercase', letterSpacing:'1px',
            }}>
            {tab === 'tenants' ? '🏫 Tenants' : '🛒 Marketplace'}
          </button>
        ))}
      </div>

      {/* Liste tenants */}
      {activeTab === 'tenants' && (
        <div style={{ display:'grid', gap:'8px' }}>
          {loading ? (
            <div style={{ color:'#475569', textAlign:'center', padding:'40px' }}>Chargement...</div>
          ) : tenants.length === 0 ? (
            <div style={{ color:'#475569', textAlign:'center', padding:'40px' }}>
              Aucun tenant trouvé. L'endpoint /api/v1/super-admin/tenants doit être configuré.
            </div>
          ) : tenants.map(t => (
            <div key={t.id} style={{
              background:'#111318', border:'1px solid #1e293b', borderRadius:'10px',
              padding:'14px 16px', display:'flex', alignItems:'center', gap:'14px',
            }}>
              <div style={{ flex:1 }}>
                <div style={{ fontWeight:700, fontSize:'13px', color:'#f1f5f9' }}>{t.nom ?? t.name}</div>
                <div style={{ fontSize:'10px', color:'#64748b' }}>
                  {t.email} · {t.nb_eleves ?? 0} élèves · Créé le {t.created_at?.split('T')[0]}
                </div>
              </div>
              <span style={{
                background: t.actif ? '#14532d' : '#450a0a',
                color: t.actif ? '#4ade80' : '#f87171',
                fontSize:'9px', fontWeight:700, padding:'2px 8px', borderRadius:'20px',
              }}>
                {t.actif ? '✅ ACTIF' : '❌ SUSPENDU'}
              </span>
              <div style={{ display:'flex', gap:'6px' }}>
                <button onClick={() => verifierMarketplace(t.id)}
                  style={{ background:'#1e3a5f', color:'#60a5fa', border:'none',
                    borderRadius:'6px', padding:'5px 10px', fontSize:'10px', cursor:'pointer', fontWeight:700 }}>
                  🛒 Vérifier
                </button>
                {t.actif && (
                  <button onClick={() => suspendre(t.id)}
                    style={{ background:'#450a0a', color:'#f87171', border:'none',
                      borderRadius:'6px', padding:'5px 10px', fontSize:'10px', cursor:'pointer', fontWeight:700 }}>
                    🚫 Suspendre
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {activeTab === 'marketplace' && (
        <div style={{ color:'#64748b', textAlign:'center', padding:'40px', fontSize:'12px' }}>
          Gestion marketplace — voir les profils, avis, vérifications.
          <br />
          Utiliser les endpoints <code style={{ color:'#60a5fa' }}>/api/v1/marketplace/stats</code>
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 4 — ProfilePage : paramètres utilisateur

**Créer :** `edugestdz/frontend/src/pages/ProfilePage.jsx`

```jsx
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
  const [pwdForm, setPwdForm] = useState({ current:'', new:'', confirm:'' });

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
    setMsg(r.success ? '✅ Profil mis à jour' : '❌ Erreur : ' + r.message);
    setSaving(false);
    setTimeout(() => setMsg(''), 3000);
  };

  const savePassword = async () => {
    if (pwdForm.new !== pwdForm.confirm) { setMsg('❌ Mots de passe différents'); return; }
    setSaving(true);
    const r = await api('/auth/password', {
      method:'PUT',
      body: JSON.stringify({ current_password: pwdForm.current, password: pwdForm.new, password_confirmation: pwdForm.confirm }),
    });
    setMsg(r.success ? '✅ Mot de passe modifié' : '❌ ' + r.message);
    setSaving(false);
    setPwdForm({ current:'', new:'', confirm:'' });
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
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}>⚙️ Mon Profil</h1>
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

      {/* Tabs */}
      <div style={{ display:'flex', gap:'4px', marginBottom:'20px' }}>
        {[['profil','👤 Profil'],['password','🔒 Mot de passe'],['security','🛡️ Sécurité']].map(([id, label]) => (
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
              {saving ? 'Enregistrement...' : '💾 Enregistrer'}
            </button>
          </>
        )}

        {tab === 'password' && (
          <>
            <Input label="Mot de passe actuel"    value={pwdForm.current}  onChange={v => setPwdForm(f=>({...f, current:v}))}  type="password" />
            <Input label="Nouveau mot de passe"   value={pwdForm.new}      onChange={v => setPwdForm(f=>({...f, new:v}))}      type="password" />
            <Input label="Confirmer mot de passe" value={pwdForm.confirm}  onChange={v => setPwdForm(f=>({...f, confirm:v}))}  type="password" />
            <button onClick={savePassword} disabled={saving} style={{
              background:'linear-gradient(135deg,#3b82f6,#1d4ed8)', color:'#fff',
              border:'none', borderRadius:'8px', padding:'10px 20px',
              fontWeight:700, fontSize:'12px', cursor:'pointer',
            }}>
              {saving ? 'Modification...' : '🔒 Modifier le mot de passe'}
            </button>
          </>
        )}

        {tab === 'security' && (
          <div>
            <div style={{ marginBottom:'16px' }}>
              <div style={{ fontSize:'13px', fontWeight:700, color:'#f1f5f9', marginBottom:'4px' }}>
                🔐 Double authentification (2FA)
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
                📱 Sessions actives
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
```

---

## ÉTAPE 5 — Mettre à jour App.jsx avec les nouvelles routes

**Modifier :** `edugestdz/frontend/src/App.jsx`

Ajouter les imports et routes manquantes :

```jsx
// Ajouter ces imports avec les autres
import PointagePage     from './pages/PointagePage';
import SuperAdminPage   from './pages/SuperAdminPage';
import ProfilePage      from './pages/ProfilePage';

// Ajouter ces routes dans le Switch/Routes
<Route path="/pointage"    element={<PointagePage />} />
<Route path="/super-admin" element={<SuperAdminPage />} />
<Route path="/profil"      element={<ProfilePage />} />
```

---

## ÉTAPE 6 — Mettre à jour Sidebar.jsx

**Modifier :** `edugestdz/frontend/src/components/Sidebar.jsx`

Ajouter dans `NAV_ITEMS` :

```jsx
{ path: '/pointage',    icon: '🏷️', label: 'Pointage' },
{ path: '/profil',      icon: '⚙️', label: 'Mon Profil' },
// Super-admin seulement si role === 'super_admin' :
{ path: '/super-admin', icon: '🛡️', label: 'Super-Admin', role: 'super_admin' },
```

Si le Sidebar a déjà une logique de filtre par rôle, utiliser la même.
Sinon filtrer simplement :
```jsx
const filteredNav = NAV_ITEMS.filter(item => !item.role || user?.role === item.role);
```

---

## ═══════════════════════════════════════════════
## PARTIE B — BACKEND : SCHEDULERS AUTOMATIQUES
## ═══════════════════════════════════════════════

## ÉTAPE 7 — Kernel.php : configurer toutes les tâches planifiées

**Modifier :** `edugestdz/backend/app/Console/Kernel.php`

```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ── 1. SMS absent auto à 8h30 chaque matin (jours de semaine) ──
        $schedule->command('edugest:sms-absents')
            ->weekdays()
            ->at('08:30')
            ->withoutOverlapping()
            ->runInBackground();

        // ── 2. Facturation mensuelle transport+cantine — 1er du mois à 6h ──
        $schedule->command('edugest:facturation-mensuelle')
            ->monthlyOn(1, '06:00')
            ->withoutOverlapping()
            ->runInBackground();

        // ── 3. Relances impayés — chaque jour à 9h ──
        $schedule->command('edugest:relances-impayes')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground();

        // ── 4. Alertes stock bas — chaque jour à 7h ──
        $schedule->command('edugest:alertes-stock')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->runInBackground();

        // ── 5. Alertes entretien préventif — chaque lundi à 8h ──
        $schedule->command('edugest:alertes-preventif')
            ->weekly()
            ->mondays()
            ->at('08:00')
            ->withoutOverlapping()
            ->runInBackground();

        // ── 6. Nettoyage logs anciens — 1er du mois à 3h ──
        $schedule->command('model:prune')
            ->monthlyOn(1, '03:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
```

---

## ÉTAPE 8 — Commande : SMS absents automatique à 8h30

**Créer :** `edugestdz/backend/app/Console/Commands/SmsAbsentsCommand.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AbsenceJournaliere;
use App\Models\Eleve;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SmsAbsentsCommand extends Command
{
    protected $signature   = 'edugest:sms-absents {--date= : Date au format Y-m-d (défaut: aujourd\'hui)}';
    protected $description = 'Envoyer SMS aux parents des élèves absents ce matin';

    public function handle(SmsService $sms): int
    {
        $date = $this->option('date') ?? today()->format('Y-m-d');
        $this->info("📱 SMS absents pour le {$date}");

        // Récupérer toutes les absences du jour pas encore notifiées
        $absences = AbsenceJournaliere::with(['eleve.parents'])
            ->whereDate('date_absence', $date)
            ->where('sms_envoye', false)
            ->get();

        if ($absences->isEmpty()) {
            $this->info('Aucune absence à notifier.');
            return Command::SUCCESS;
        }

        $envoyes = 0;
        foreach ($absences as $absence) {
            $eleve = $absence->eleve;
            if (! $eleve) continue;

            // Envoyer SMS à chaque parent principal
            foreach ($eleve->parents ?? [] as $parent) {
                $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                if (! $tel) continue;

                $msg = "EduGest: Votre enfant {$eleve->prenom} {$eleve->nom} est "
                     . "absent(e) ce jour ({$date}). Contactez l'établissement si nécessaire.";

                try {
                    $sms->send($tel, $msg);
                    $envoyes++;
                } catch (\Throwable $e) {
                    Log::warning("SMS absent échoué pour élève {$eleve->id}: " . $e->getMessage());
                }
            }

            $absence->update(['sms_envoye' => true]);
        }

        $this->info("✅ {$envoyes} SMS envoyés pour {$absences->count()} absences.");
        Log::info("SmsAbsents: {$envoyes} SMS envoyés, date={$date}");

        return Command::SUCCESS;
    }
}
```

---

## ÉTAPE 9 — Commande : Relances impayés

**Créer :** `edugestdz/backend/app/Console/Commands/RelancesImpayesCommand.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Facture;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RelancesImpayesCommand extends Command
{
    protected $signature   = 'edugest:relances-impayes';
    protected $description = 'Envoyer relances SMS/notification aux parents avec factures impayées (J+1/J+3/J+7/J+15)';

    private array $paliers = [1, 3, 7, 15]; // jours après échéance

    public function handle(SmsService $sms): int
    {
        $this->info('💸 Relances impayés...');
        $total = 0;

        foreach ($this->paliers as $jours) {
            $dateEcheance = today()->subDays($jours);

            $factures = Facture::with(['eleve.parents'])
                ->whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
                ->whereDate('date_echeance', $dateEcheance->format('Y-m-d'))
                ->get();

            foreach ($factures as $facture) {
                $eleve = $facture->eleve;
                if (! $eleve) continue;

                // Changer statut → en_retard
                $facture->update(['statut' => 'en_retard']);

                // SMS au parent
                foreach ($eleve->parents ?? [] as $parent) {
                    $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                    if (! $tel) continue;

                    $msg = "EduGest: Facture {$facture->numero} de {$facture->total_ttc} DA "
                         . "est impayée depuis {$jours} jour(s). Merci de régulariser.";

                    try {
                        $sms->send($tel, $msg);
                        $total++;
                    } catch (\Throwable $e) {
                        Log::warning("Relance SMS échouée facture {$facture->id}: " . $e->getMessage());
                    }
                }
            }

            $this->line("J+{$jours}: {$factures->count()} factures relancées");
        }

        $this->info("✅ {$total} SMS de relance envoyés.");
        return Command::SUCCESS;
    }
}
```

---

## ÉTAPE 10 — Commande : Alertes stock bas

**Créer :** `edugestdz/backend/app/Console/Commands/AlertesStockCommand.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ArticleStock;
use Illuminate\Support\Facades\Log;

class AlertesStockCommand extends Command
{
    protected $signature   = 'edugest:alertes-stock';
    protected $description = 'Envoyer alertes pour les articles sous le seuil minimum';

    public function handle(): int
    {
        $this->info('📦 Vérification stock bas...');

        // Articles sous le seuil, groupés par tenant
        $articles = ArticleStock::where('actif', true)
            ->whereColumn('quantite_stock', '<=', 'seuil_alerte')
            ->get();

        if ($articles->isEmpty()) {
            $this->info('Aucun article en rupture.');
            return Command::SUCCESS;
        }

        // Grouper par tenant pour envoyer 1 notification groupée
        $parTenant = $articles->groupBy('tenant_id');

        foreach ($parTenant as $tenantId => $items) {
            $liste = $items->map(fn($a) => "{$a->nom} ({$a->quantite_stock}/{$a->seuil_alerte})")->implode(', ');

            Log::warning("Stock bas tenant {$tenantId}: {$liste}");

            // TODO: envoyer notification push admin du tenant
            // NotificationService::sendToAdmin($tenantId, "⚠️ Stock bas: {$liste}");
        }

        $this->info("⚠️ {$articles->count()} articles sous le seuil dans {$parTenant->count()} tenant(s).");
        return Command::SUCCESS;
    }
}
```

---

## ÉTAPE 11 — Commande : Alertes entretien préventif

**Créer :** `edugestdz/backend/app/Console/Commands/AlertesPreventifCommand.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EntretienPreventif;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AlertesPreventifCommand extends Command
{
    protected $signature   = 'edugest:alertes-preventif';
    protected $description = 'Alerter les admins sur les entretiens préventifs à échéance';

    public function handle(): int
    {
        $this->info('🔧 Vérification entretiens préventifs...');

        // Entretiens actifs dont l'échéance est dans 7 jours ou déjà passée
        $aVenir = EntretienPreventif::where('actif', true)
            ->where('prochaine_echeance', '<=', today()->addDays(7)->format('Y-m-d'))
            ->get();

        if ($aVenir->isEmpty()) {
            $this->info('Aucun entretien préventif urgent.');
            return Command::SUCCESS;
        }

        foreach ($aVenir as $entretien) {
            $echeance = Carbon::parse($entretien->prochaine_echeance);
            $jours    = today()->diffInDays($echeance, false);
            $status   = $jours < 0 ? "EN RETARD de {$jours} jours" : "dans {$jours} jours";

            Log::warning("Entretien préventif #{$entretien->id} — {$entretien->description} — {$status}");
            // TODO: notification push admin tenant
        }

        $this->info("⚠️ {$aVenir->count()} entretiens préventifs urgents.");
        return Command::SUCCESS;
    }
}
```

---

## ═══════════════════════════════════════════════
## PARTIE C — BACKEND : SUPER-ADMIN ENDPOINTS
## ═══════════════════════════════════════════════

## ÉTAPE 12 — SuperAdminController : endpoints manquants

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/SuperAdmin/SuperAdminController.php`

Ajouter ces méthodes si elles n'existent pas (ne pas supprimer l'existant) :

```php
/**
 * @OA\Get(path="/api/v1/super-admin/tenants", summary="Liste tous les tenants",
 *     tags={"SuperAdmin"}, security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Liste tenants"))
 */
public function indexTenants(): JsonResponse
{
    // Utiliser le model Tenant ou User selon l'architecture existante
    // Si pas de model Tenant, utiliser les tenants depuis les users avec role admin
    $tenants = \App\Models\User::where('role', 'admin')
        ->select('id', 'nom', 'prenom', 'email', 'tenant_id', 'created_at')
        ->withCount(['eleves as nb_eleves' => fn($q) => $q->where('statut', 'actif')])
        ->orderByDesc('created_at')
        ->get()
        ->map(fn($u) => [
            'id'         => $u->tenant_id ?? $u->id,
            'nom'        => $u->nom . ' ' . $u->prenom,
            'email'      => $u->email,
            'nb_eleves'  => $u->nb_eleves,
            'actif'      => true,
            'created_at' => $u->created_at,
        ]);

    return response()->json(['success' => true, 'data' => $tenants]);
}

/**
 * @OA\Get(path="/api/v1/super-admin/stats", summary="Stats globales plateforme",
 *     tags={"SuperAdmin"}, security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="KPIs globaux"))
 */
public function statsGlobales(): JsonResponse
{
    return response()->json([
        'success' => true,
        'data'    => [
            'total_tenants'        => \App\Models\User::where('role', 'admin')->count(),
            'tenants_actifs'       => \App\Models\User::where('role', 'admin')->count(),
            'total_eleves'         => \App\Models\Eleve::where('statut', 'actif')->count(),
            'ca_global'            => (float) \App\Models\Paiement::where('statut', 'confirmé')->sum('montant'),
            'profils_marketplace'  => \App\Models\ProfilMarketplace::where('visible', true)->count(),
            'total_reservations'   => \App\Models\ReservationMarketplace::count(),
        ],
    ]);
}

/**
 * @OA\Post(path="/api/v1/super-admin/tenants/{id}/suspendre",
 *     summary="Suspendre un tenant", tags={"SuperAdmin"}, security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Tenant suspendu"))
 */
public function suspendreTenant(string $id): JsonResponse
{
    // Désactiver tous les users du tenant
    \App\Models\User::where('tenant_id', $id)->update(['actif' => false]);
    \Illuminate\Support\Facades\Log::warning("Super-admin: tenant {$id} suspendu par " . auth('api')->id());

    return response()->json(['success' => true, 'message' => 'Tenant suspendu']);
}

/**
 * @OA\Post(path="/api/v1/super-admin/marketplace/{tenantId}/verifier",
 *     summary="Vérifier un profil marketplace", tags={"SuperAdmin"}, security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="tenantId", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Profil vérifié"))
 */
public function verifierMarketplace(string $tenantId): JsonResponse
{
    \App\Models\ProfilMarketplace::where('tenant_id', $tenantId)
        ->update(['verifie' => true]);

    return response()->json(['success' => true, 'message' => 'Profil marketplace vérifié ✅']);
}
```

**Ajouter les routes dans routes/api.php** (dans le groupe super-admin existant) :

```php
Route::middleware(['auth:api', 'super_admin'])->prefix('v1/super-admin')->group(function () {
    Route::get('/tenants',                          [SuperAdminController::class, 'indexTenants']);
    Route::get('/stats',                            [SuperAdminController::class, 'statsGlobales']);
    Route::post('/tenants/{id}/suspendre',          [SuperAdminController::class, 'suspendreTenant']);
    Route::post('/marketplace/{tenantId}/verifier', [SuperAdminController::class, 'verifierMarketplace']);
});
```

---

## ═══════════════════════════════════════════════
## PARTIE D — BACKEND : EXPORT iCAL + RAPPORT PDF
## ═══════════════════════════════════════════════

## ÉTAPE 13 — Export iCal planning enseignant

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/PlanningController.php`

Ajouter la méthode `exportICal()` :

```php
/**
 * @OA\Get(
 *     path="/api/v1/planning/ical",
 *     summary="Exporter le planning en format iCal (.ics) — compatible Google Calendar",
 *     tags={"Planning"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(ref="#/components/parameters/TenantId"),
 *     @OA\Parameter(name="enseignant_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Fichier .ics téléchargeable",
 *         @OA\MediaType(mediaType="text/calendar"))
 * )
 */
public function exportICal(Request $request): \Illuminate\Http\Response
{
    $enseignantId = $request->enseignant_id ?? auth('api')->id();

    $seances = \App\Models\Seance::with(['cours.matiere', 'cours.groupe', 'salle'])
        ->whereHas('cours', fn($q) => $q->where('enseignant_id', $enseignantId))
        ->where('statut', '!=', 'annulée')
        ->where('date_seance', '>=', today()->subMonth()->format('Y-m-d'))
        ->where('date_seance', '<=', today()->addMonths(3)->format('Y-m-d'))
        ->get();

    $ical  = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//EduGest DZ//Planning Enseignant//FR\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "METHOD:PUBLISH\r\n";
    $ical .= "X-WR-CALNAME:Planning EduGest DZ\r\n";
    $ical .= "X-WR-TIMEZONE:Africa/Algiers\r\n";

    foreach ($seances as $seance) {
        $debut  = \Carbon\Carbon::parse($seance->date_seance . ' ' . $seance->heure_debut);
        $fin    = \Carbon\Carbon::parse($seance->date_seance . ' ' . $seance->heure_fin);
        $titre  = $seance->cours?->matiere?->nom_fr ?? 'Cours';
        $groupe = $seance->cours?->groupe?->nom ?? '';
        $salle  = $seance->salle?->nom ?? '';

        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . $seance->id . "@edugest.dz\r\n";
        $ical .= "DTSTART:" . $debut->format('Ymd\THis') . "\r\n";
        $ical .= "DTEND:"   . $fin->format('Ymd\THis')   . "\r\n";
        $ical .= "SUMMARY:" . $titre . ($groupe ? " — {$groupe}" : '') . "\r\n";
        $ical .= "LOCATION:{$salle}\r\n";
        $ical .= "STATUS:" . ($seance->statut === 'terminée' ? 'CONFIRMED' : 'TENTATIVE') . "\r\n";
        $ical .= "DTSTAMP:" . now()->format('Ymd\THis\Z') . "\r\n";
        $ical .= "END:VEVENT\r\n";
    }

    $ical .= "END:VCALENDAR\r\n";

    return response($ical, 200, [
        'Content-Type'        => 'text/calendar; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="planning-edugest.ics"',
    ]);
}
```

**Ajouter la route :**
```php
Route::get('/planning/ical', [PlanningController::class, 'exportICal']);
```

---

## ÉTAPE 14 — Tests pour les nouvelles commandes et endpoints

**Créer :** `edugestdz/backend/tests/Feature/SchedulersTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Eleve;
use App\Models\AbsenceJournaliere;
use App\Models\Facture;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SchedulersTest extends TestCase
{
    use RefreshDatabase;

    public function test_commande_sms_absents_sans_absences(): void
    {
        $this->artisan('edugest:sms-absents', ['--date' => today()->format('Y-m-d')])
            ->assertSuccessful();
    }

    public function test_commande_sms_absents_avec_date(): void
    {
        $this->artisan('edugest:sms-absents', ['--date' => '2026-07-03'])
            ->assertSuccessful();
    }

    public function test_commande_relances_impayes(): void
    {
        $this->artisan('edugest:relances-impayes')
            ->assertSuccessful();
    }

    public function test_commande_alertes_stock(): void
    {
        $this->artisan('edugest:alertes-stock')
            ->assertSuccessful();
    }

    public function test_commande_alertes_preventif(): void
    {
        $this->artisan('edugest:alertes-preventif')
            ->assertSuccessful();
    }
}
```

**Créer :** `edugestdz/backend/tests/Feature/Controllers/SuperAdminExtTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SuperAdminExtTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_lister_tenants(): void
    {
        $this->actingAs($this->superAdmin, 'api')
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_stats_globales(): void
    {
        $this->actingAs($this->superAdmin, 'api')
            ->getJson('/api/v1/super-admin/stats')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'total_tenants', 'total_eleves', 'ca_global',
            ]]);
    }

    public function test_admin_ne_peut_pas_acceder_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);
    }

    public function test_export_ical_planning(): void
    {
        $enseignant = User::factory()->create(['role' => 'enseignant']);
        $this->actingAs($enseignant, 'api')
            ->get('/api/v1/planning/ical')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    }
}
```

---

## ÉTAPE 15 — README mis à jour

**Modifier :** `edugestdz/backend/README.md` (ou `README.md` à la racine)

```markdown
# EduGest DZ — Plateforme SaaS de gestion scolaire

> Solution complète pour les écoles privées et centres de cours particuliers en Algérie.

## Stack technique
- **Backend** : Laravel 11 · PHP 8.2 · PostgreSQL 16 · Redis 7
- **Frontend** : React 18 + Vite
- **Mobile** : React Native 0.76 + Expo 52
- **Infra** : Docker Compose · Nginx · SSL Certbot · GitHub Actions CI/CD

## Modules disponibles (14 modules)
| Module | Description |
|---|---|
| M01 Inscriptions | Dossier élève, parents, import CSV |
| M02 Planning | Emploi du temps, séances, conflits |
| M03 Finance | Factures, paiements cash + CIB/Dahabia |
| M04 Pédagogie | Notes, moyennes, bulletins PDF |
| M05 Enseignants | Dossier, contrats, paie IRG/CNAS |
| M06 Communication | SMS, WhatsApp, push notifications |
| M07 Reporting | Dashboards, exports Excel/PDF |
| M08 Auth/RBAC | JWT + 2FA + multi-tenant |
| M09 Transport | Circuits, arrêts, pointage bus |
| M10 Cantine | Menus, inscriptions, pointage repas |
| M11 Stock | Inventaire, mouvements, bons commande |
| M12 Personnel | Non-enseignant, congés, paie |
| M13 Budget | Dépenses, prévisionnel, bilan |
| M14 Entretien | Locaux, interventions, préventif |
| MKT Marketplace | Recherche centres, réservations, avis |

## Installation rapide (développement)

```bash
git clone https://github.com/Allintelligence2024/edugest-dz.git
cd edugestdz
docker-compose up -d
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve
```

## Tests
```bash
cd edugestdz/backend
php artisan test --parallel
# → ≥ 440 tests verts
```

## Documentation API
Après `php artisan l5-swagger:generate` :
→ http://localhost/api/documentation

## Déploiement production
```bash
# Sur le VPS
./server-setup.sh
# Puis CD automatique via GitHub Actions sur push main
```
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser
git checkout develop && git pull origin main

# PARTIE A — Frontend
# 1. Modifier DashboardPage.jsx (APIs réelles)
# 2. Créer PointagePage.jsx
# 3. Créer SuperAdminPage.jsx
# 4. Créer ProfilePage.jsx
# 5. Modifier App.jsx (3 nouvelles routes)
# 6. Modifier Sidebar.jsx (3 nouveaux liens)

# PARTIE B — Schedulers
# 7. Modifier app/Console/Kernel.php
# 8. Créer SmsAbsentsCommand.php
# 9. Créer RelancesImpayesCommand.php
# 10. Créer AlertesStockCommand.php
# 11. Créer AlertesPreventifCommand.php

# PARTIE C — Super-Admin
# 12. Modifier SuperAdminController.php (4 méthodes + routes)

# PARTIE D — iCal + README
# 13. Modifier PlanningController.php (exportICal + route)
# 14. Mettre à jour README.md

# TESTS
# 15. Créer tests/Feature/SchedulersTest.php
# 16. Créer tests/Feature/Controllers/SuperAdminExtTest.php

# VÉRIFIER
cd edugestdz/backend
php artisan test --parallel
# → ≥ 440 tests verts (423 + ~17 nouveaux)

# COMMIT
git add .
git commit -m "feat: Finitions — Dashboard réel + PointagePage + SuperAdminPage + ProfilePage + Schedulers auto + iCal + README"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_FINITIONS_FRONTEND.md — 15 étapes dans l'ordre.

RÈGLES :
1. PostgreSQL uniquement — jamais SQLite.
2. 423 tests → 0 régression.
3. Ne pas modifier les contrôleurs existants — seulement ajouter des méthodes.
4. Même style dark theme CSS inline que les pages existantes.
5. Si une relation/model n'existe pas (ex: ProfilMarketplace) → vérifier d'abord
   que la migration marketplace a été faite (PR #15), sinon adapter.

php artisan test --parallel → ≥ 440 verts → git push → PR develop → main.
```
