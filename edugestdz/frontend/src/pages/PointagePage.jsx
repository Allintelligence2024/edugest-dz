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
    if (pt.arrivee && pt.depart) return <span style={{ background:'#14532d', color:'#4ade80', fontSize:'10px', padding:'3px 8px', borderRadius:'20px' }}>Journée complète</span>;
    if (pt.arrivee) return <span style={{ background:'#1e3a5f', color:'#60a5fa', fontSize:'10px', padding:'3px 8px', borderRadius:'20px' }}>En cours ({pt.arrivee})</span>;
    return <span style={{ background:'#450a0a', color:'#f87171', fontSize:'10px', padding:'3px 8px', borderRadius:'20px' }}>Absent</span>;
  };

  return (
    <div style={{ padding:'24px', background:'#08090f', minHeight:'100vh' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'24px' }}>
        <div>
          <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff' }}>Pointage Enseignants</h1>
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

      <div style={{ position:'relative', marginBottom:'16px' }}>
        <Search size={14} style={{ position:'absolute', left:'12px', top:'50%', transform:'translateY(-50%)', color:'#475569' }} />
        <input value={search} onChange={e => setSearch(e.target.value)}
          placeholder="Rechercher un enseignant..."
          style={{ width:'100%', background:'#111318', border:'1px solid #1e293b', borderRadius:'8px',
            color:'#e2e8f0', padding:'10px 12px 10px 34px', fontSize:'12px' }} />
      </div>

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
                    Arrivée
                  </button>
                )}
                {pt?.arrivee && !pt?.depart && (
                  <button onClick={() => pointer(ens.id, 'départ')}
                    style={{ background:'#1e3a5f', color:'#60a5fa', border:'none', borderRadius:'6px',
                      padding:'6px 12px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                    Départ
                  </button>
                )}
                {!pt?.arrivee && (
                  <button onClick={() => pointer(ens.id, 'absent')}
                    style={{ background:'#450a0a', color:'#f87171', border:'none', borderRadius:'6px',
                      padding:'6px 12px', fontSize:'11px', fontWeight:700, cursor:'pointer' }}>
                    Absent
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
