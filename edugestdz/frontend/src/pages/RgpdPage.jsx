import { useState, useEffect } from 'react';
import { api } from '@api/client';

export default function RgpdPage() {
  const [demandes,    setDemandes]    = useState([]);
  const [loading,     setLoading]     = useState(true);
  const [annee,       setAnnee]       = useState(`${new Date().getFullYear()-1}-${new Date().getFullYear()}`);
  const [msg,         setMsg]         = useState('');
  const [msgType,     setMsgType]     = useState('ok');

  useEffect(() => {
    api('/rgpd/demandes')
      .then(r => { if (r.success) setDemandes(r.data ?? []); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const notify = (m, type = 'ok') => { setMsg(m); setMsgType(type); setTimeout(() => setMsg(''), 5000); };

  const exporterTenant = async () => {
    if (!confirm('Lancer l\'export complet des données de votre établissement ?')) return;
    try {
      const r = await api('/rgpd/export-tenant');
      if (r.success) notify('Export lancé ! Vous serez notifié quand il sera prêt.');
    } catch (e) { notify(e.message, 'err'); }
  };

  const archiverAnnee = async () => {
    if (!confirm(`Archiver définitivement l'année ${annee} ? Cette action est irréversible.`)) return;
    try {
      const r = await api('/rgpd/archiver-annee', { method:'POST', body: JSON.stringify({ annee_scolaire: annee, confirme: true }) });
      if (r.success) notify(`Archivage de l'année ${annee} lancé !`);
    } catch (e) { notify(e.message, 'err'); }
  };

  return (
    <div className="animate-fadeIn space-y-6" style={{ maxWidth:'860px' }}>
      <div>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'var(--text)' }}>RGPD & Loi 18-07</h1>
        <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'4px' }}>
          Gestion des données personnelles — Conformité Loi 18-07 Algérie (ANPDP)
        </p>
      </div>

      {msg && (
        <div style={{ background: msgType === 'ok' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)', border:`1px solid ${msgType === 'ok' ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)'}`, borderRadius:'10px', padding:'12px 16px', color: msgType === 'ok' ? 'var(--green)' : '#f87171', fontSize:'13px', fontWeight:600 }}>
          {msg}
        </div>
      )}

      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:'16px' }}>
        {[
          { emoji:'📦', titre:'Export complet', desc:'Toutes les données de votre école au format JSON', action: exporterTenant, btn:'Demander l\'export', couleur:'var(--accent)' },
          { emoji:'🗂️', titre:'Archivage annuel', desc:'Clôturer l\'année scolaire et archiver les données',
            content: (
              <div style={{ marginTop:'10px' }}>
                <input value={annee} onChange={e => setAnnee(e.target.value)} placeholder="2024-2025"
                  style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'7px 10px', color:'var(--text)', fontSize:'12px', marginBottom:'8px', outline:'none', boxSizing:'border-box' }} />
                <button onClick={archiverAnnee} style={{ width:'100%', background:'rgba(234,179,8,0.2)', color:'#ca8a04', border:'1px solid rgba(234,179,8,0.3)', borderRadius:'8px', padding:'8px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
                  Archiver l'année
                </button>
              </div>
            )
          },
          { emoji:'🔒', titre:'Politique de données', desc:'Afficher la politique de confidentialité aux parents', content:(
              <div style={{ marginTop:'10px', fontSize:'12px', color:'var(--muted)', lineHeight:'1.6' }}>
                <p>Données stockées en Algérie (après VPS Hostarts)</p>
                <p>Chiffrement AES-256 en transit et au repos</p>
                <p>Accès limité au tenant concerné (RLS)</p>
                <p>Déclaration ANPDP en attente</p>
              </div>
            )
          },
        ].map(card => (
          <div key={card.titre} style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'14px', padding:'20px' }}>
            <div style={{ fontSize:'28px', marginBottom:'8px' }}>{card.emoji}</div>
            <h3 style={{ fontSize:'14px', fontWeight:800, color:'var(--text)', marginBottom:'4px' }}>{card.titre}</h3>
            <p style={{ fontSize:'12px', color:'var(--muted)', lineHeight:'1.5', marginBottom:'12px' }}>{card.desc}</p>
            {card.action && (
              <button onClick={card.action} style={{ background:`${card.couleur}1a`, color:card.couleur, border:`1px solid ${card.couleur}44`, borderRadius:'8px', padding:'8px 14px', fontSize:'12px', fontWeight:700, cursor:'pointer', width:'100%' }}>
                {card.btn}
              </button>
            )}
            {card.content && card.content}
          </div>
        ))}
      </div>

      <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'16px', padding:'24px' }}>
        <h3 style={{ fontSize:'15px', fontWeight:800, color:'var(--text)', marginBottom:'16px' }}>
          Demandes RGPD reçues ({demandes.length})
        </h3>
        {loading ? <div style={{ height:'80px', background:'var(--surface2)', borderRadius:'10px', animation:'pulse 1.5s infinite' }} />
        : demandes.length === 0 ? (
          <p style={{ color:'var(--muted)', fontSize:'13px', textAlign:'center', padding:'20px' }}>Aucune demande reçue</p>
        ) : (
          <table style={{ width:'100%', borderCollapse:'collapse', fontSize:'13px' }}>
            <thead>
              <tr style={{ borderBottom:'1px solid var(--border)' }}>
                {['Demandeur','Type','Statut','Date','Actions'].map(h => (
                  <th key={h} style={{ padding:'8px 12px', textAlign:'left', color:'var(--muted)', fontWeight:600, fontSize:'11px', textTransform:'uppercase' }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {demandes.map(d => (
                <tr key={d.id} style={{ borderBottom:'1px solid var(--border)' }}>
                  <td style={{ padding:'10px 12px', color:'var(--text)', fontWeight:600 }}>{d.demandeur}</td>
                  <td style={{ padding:'10px 12px', color:'var(--muted)' }}>{d.type}</td>
                  <td style={{ padding:'10px 12px' }}>
                    <span style={{
                      background: d.statut === 'traite' ? 'rgba(16,185,129,0.1)' : 'rgba(234,179,8,0.1)',
                      color: d.statut === 'traite' ? 'var(--green)' : '#ca8a04',
                      padding:'2px 10px', borderRadius:'12px', fontSize:'11px', fontWeight:700,
                    }}>
                      {d.statut === 'en_cours' ? 'En cours' : 'Traité'}
                    </span>
                  </td>
                  <td style={{ padding:'10px 12px', color:'var(--muted)', fontSize:'12px' }}>
                    {new Date(d.created_at).toLocaleDateString('fr-DZ')}
                  </td>
                  <td style={{ padding:'10px 12px' }}>
                    {d.statut === 'en_cours' && (
                      <button style={{ fontSize:'11px', color:'var(--accent)', background:'none', border:'none', cursor:'pointer' }}>
                        Traiter
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
