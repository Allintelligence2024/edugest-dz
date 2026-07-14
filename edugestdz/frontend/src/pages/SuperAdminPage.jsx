import { useState, useEffect } from 'react';
import { Shield, Users, Building2, CheckCircle, XCircle, BarChart3 } from 'lucide-react';

const api = (path) => fetch(`/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('access_token')}` }
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
      headers: { Authorization: `Bearer ${localStorage.getItem('access_token')}` },
    });
    setTenants(t => t.map(x => x.id === id ? { ...x, actif: false } : x));
  };

  const verifierMarketplace = async (tenantId) => {
    await fetch(`/api/v1/super-admin/marketplace/${tenantId}/verifier`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${localStorage.getItem('access_token')}` },
    });
    alert('Tenant vérifié sur la marketplace');
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

      <div style={{ display:'grid', gridTemplateColumns:'repeat(5,1fr)', gap:'10px', marginBottom:'24px' }}>
        <StatBox label="Total tenants"    value={stats?.total_tenants ?? 0}    color="#60a5fa" />
        <StatBox label="Tenants actifs"   value={stats?.tenants_actifs ?? 0}   color="#4ade80" />
        <StatBox label="Total élèves"     value={stats?.total_eleves ?? 0}     color="#a78bfa" />
        <StatBox label="CA global (DA)"   value={Intl.NumberFormat('fr').format(stats?.ca_global ?? 0)} color="#fb923c" />
        <StatBox label="Marketplace"      value={stats?.profils_marketplace ?? 0} color="#f59e0b" />
      </div>

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
            {tab === 'tenants' ? 'Tenants' : 'Marketplace'}
          </button>
        ))}
      </div>

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
                {t.actif ? 'ACTIF' : 'SUSPENDU'}
              </span>
              <div style={{ display:'flex', gap:'6px' }}>
                <button onClick={() => verifierMarketplace(t.id)}
                  style={{ background:'#1e3a5f', color:'#60a5fa', border:'none',
                    borderRadius:'6px', padding:'5px 10px', fontSize:'10px', cursor:'pointer', fontWeight:700 }}>
                  Vérifier
                </button>
                {t.actif && (
                  <button onClick={() => suspendre(t.id)}
                    style={{ background:'#450a0a', color:'#f87171', border:'none',
                      borderRadius:'6px', padding:'5px 10px', fontSize:'10px', cursor:'pointer', fontWeight:700 }}>
                    Suspendre
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
