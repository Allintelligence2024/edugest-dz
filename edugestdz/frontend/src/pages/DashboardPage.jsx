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
        total_eleves:  elevesRes?.meta?.total ?? 0,
        eleves_actifs: elevesRes?.meta?.total ?? 0,
        ca_mois:       financeRes?.data?.ca_mois ?? 0,
        impayes:       financeRes?.data?.impayes ?? 0,
        nb_impayes:    financeRes?.data?.nb_impayes ?? 0,
        absences_jour: absencesRes?.meta?.total ?? 0,
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
          Tableau de bord
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
        <div style={{ background:'#111318', border:'1px solid #1e293b', borderRadius:'12px', padding:'20px' }}>
          <div style={{ fontSize:'12px', fontWeight:700, color:'#60a5fa', marginBottom:'16px' }}>
            Évolution CA — 6 derniers mois
          </div>
          {loading ? (
            <div style={{ color:'#475569', fontSize:'12px' }}>Chargement...</div>
          ) : (
            <div style={{ fontSize:'11px', color:'#64748b' }}>
              Données disponibles après branchement complet API finance.
            </div>
          )}
        </div>

        <div style={{ background:'#111318', border:'1px solid #1e293b', borderRadius:'12px', padding:'20px' }}>
          <div style={{ fontSize:'12px', fontWeight:700, color:'#60a5fa', marginBottom:'16px' }}>
            Actions rapides
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
