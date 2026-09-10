import { useState, useEffect } from 'react';
import { api } from '@api/client';
import { useI18n } from '@context/I18nContext';

import { Archive, Lock, Package } from 'lucide-react';


export default function RgpdPage() {
  const { t } = useI18n();
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
    if (!confirm(t('rgpd_confirm_export'))) return;
    try {
      const r = await api('/rgpd/export-tenant');
      if (r.success) notify(t('rgpd_export_lance'));
    } catch (e) { notify(e.message, 'err'); }
  };

  const archiverAnnee = async () => {
    if (!confirm(t('rgpd_confirm_archivage', { annee }))) return;
    try {
      const r = await api('/rgpd/archiver-annee', { method:'POST', body: JSON.stringify({ annee_scolaire: annee, confirme: true }) });
      if (r.success) notify(t('rgpd_archivage_lance', { annee }));
    } catch (e) { notify(e.message, 'err'); }
  };

  return (
    <div className="animate-fadeIn space-y-6" style={{ maxWidth:'860px' }}>
      <div>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'var(--text)' }}>{t('rgpd_titre')}</h1>
        <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'4px' }}>
          {t('rgpd_sous_titre')}
        </p>
      </div>

      {msg && (
        <div style={{ background: msgType === 'ok' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)', border:`1px solid ${msgType === 'ok' ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)'}`, borderRadius:'10px', padding:'12px 16px', color: msgType === 'ok' ? 'var(--green)' : '#f87171', fontSize:'13px', fontWeight:600 }}>
          {msg}
        </div>
      )}

      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:'16px' }}>
        {[
          { emoji: <Package size={28} aria-hidden="true" />, titre: t('rgpd_card_export_titre'), desc: t('rgpd_card_export_desc'), action: exporterTenant, btn: t('rgpd_card_export_btn'), couleur:'var(--accent)' },
          { emoji: <Archive size={28} aria-hidden="true" />, titre: t('rgpd_card_archivage_titre'), desc: t('rgpd_card_archivage_desc'),
            content: (
              <div style={{ marginTop:'10px' }}>
                <input value={annee} onChange={e => setAnnee(e.target.value)} placeholder="2024-2025"
                  style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'7px 10px', color:'var(--text)', fontSize:'12px', marginBottom:'8px', outline:'none', boxSizing:'border-box' }} />
                <button onClick={archiverAnnee} style={{ width:'100%', background:'rgba(234,179,8,0.2)', color:'#ca8a04', border:'1px solid rgba(234,179,8,0.3)', borderRadius:'8px', padding:'8px', fontSize:'12px', fontWeight:700, cursor:'pointer' }}>
                  {t('rgpd_archiver_annee')}
                </button>
              </div>
            )
          },
          { emoji: <Lock size={28} aria-hidden="true" />, titre: t('rgpd_card_politique_titre'), desc: t('rgpd_card_politique_desc'), content:(
              <div style={{ marginTop:'10px', fontSize:'12px', color:'var(--muted)', lineHeight:'1.6' }}>
                <p>{t('rgpd_pol_hebergement')}</p>
                <p>{t('rgpd_pol_chiffrement')}</p>
                <p>{t('rgpd_pol_rls')}</p>
                <p>{t('rgpd_pol_anpdp')}</p>
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
          {t('rgpd_demandes_recues', { count: demandes.length })}
        </h3>
        {loading ? <div style={{ height:'80px', background:'var(--surface2)', borderRadius:'10px', animation:'pulse 1.5s infinite' }} />
        : demandes.length === 0 ? (
          <p style={{ color:'var(--muted)', fontSize:'13px', textAlign:'center', padding:'20px' }}>{t('rgpd_aucune_demande')}</p>
        ) : (
          <table style={{ width:'100%', borderCollapse:'collapse', fontSize:'13px' }}>
            <thead>
              <tr style={{ borderBottom:'1px solid var(--border)' }}>
                {[t('rgpd_th_demandeur'), t('rgpd_th_type'), t('status'), t('date'), t('actions')].map(h => (
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
                      {d.statut === 'en_cours' ? t('rgpd_statut_en_cours') : t('rgpd_statut_traite')}
                    </span>
                  </td>
                  <td style={{ padding:'10px 12px', color:'var(--muted)', fontSize:'12px' }}>
                    {new Date(d.created_at).toLocaleDateString('fr-DZ')}
                  </td>
                  <td style={{ padding:'10px 12px' }}>
                    {d.statut === 'en_cours' && (
                      <button style={{ fontSize:'11px', color:'var(--accent)', background:'none', border:'none', cursor:'pointer' }}>
                        {t('rgpd_traiter')}
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
