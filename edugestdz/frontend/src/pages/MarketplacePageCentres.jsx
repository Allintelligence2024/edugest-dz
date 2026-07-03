import { useState, useEffect } from 'react';

const SearchIcon = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
  </svg>
);

const StarIcon = ({ filled }) => (
  <svg width="12" height="12" viewBox="0 0 24 24" fill={filled ? '#f59e0b' : 'none'} stroke={filled ? '#f59e0b' : '#475569'} strokeWidth="2">
    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
  </svg>
);

const MapPinIcon = () => (
  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
  </svg>
);

const CheckCircleIcon = () => (
  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#4ade80" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
  </svg>
);

const WILAYAS_DZ = [
  'Adrar','Chlef','Laghouat','Oum El Bouaghi','Batna','Béjaïa','Biskra','Béchar',
  'Blida','Bouira','Tamanrasset','Tébessa','Tlemcen','Tiaret','Tizi Ouzou','Alger',
  'Djelfa','Jijel','Sétif','Saïda','Skikda','Sidi Bel Abbès','Annaba','Guelma',
  'Constantine','Médéa','Mostaganem','MSila','Mascara','Ouargla','Oran','El Bayadh',
  'Illizi','Bordj Bou Arréridj','Boumerdès','El Tarf','Tindouf','Tissemsilt',
  'El Oued','Khenchela','Souk Ahras','Tipaza','Mila','Aïn Defla','Naâma',
  'Aïn Témouchent','Ghardaïa','Relizane',
];

const MATIERES = ['Mathématiques','Physique','Chimie','Français','Anglais','Arabe',
  'Histoire-Géographie','Philosophie','Informatique','Sciences Naturelles','Tamazight'];

const NIVEAUX = ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM',
  '1AS','2AS','3AS'];

export default function MarketplacePageCentres() {
  const [centres, setCentres]       = useState([]);
  const [featured, setFeatured]     = useState([]);
  const [loading, setLoading]       = useState(false);
  const [selectedCentre, setSelectedCentre] = useState(null);
  const [filtres, setFiltres]       = useState({
    wilaya: '', matiere: '', niveau: '', tarif_max: '', essai_gratuit: false,
  });

  useEffect(() => {
    fetchFeatured();
  }, []);

  const fetchFeatured = async () => {
    try {
      const res = await fetch('/api/v1/marketplace/featured');
      const data = await res.json();
      if (data.success) setFeatured(data.data);
    } catch (e) { console.error(e); }
  };

  const rechercher = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      Object.entries(filtres).forEach(([k, v]) => { if (v) params.append(k, v); });
      const res  = await fetch(`/api/v1/marketplace/recherche?${params}`);
      const data = await res.json();
      if (data.success) setCentres(data.data.centres);
    } catch (e) { console.error(e); }
    finally { setLoading(false); }
  };

  const fetchProfil = async (tenantId) => {
    try {
      const res  = await fetch(`/api/v1/marketplace/centres/${tenantId}`);
      const data = await res.json();
      if (data.success) setSelectedCentre(data.data);
    } catch (e) { console.error(e); }
  };

  const Stars = ({ note }) => (
    <div style={{ display:'flex', gap:'2px' }}>
      {[1,2,3,4,5].map(i => (
        <StarIcon key={i} filled={i <= Math.round(note)} />
      ))}
      <span style={{ fontSize:'11px', color:'#94a3b8', marginLeft:'4px' }}>{note}</span>
    </div>
  );

  const CentreCard = ({ centre, onClick }) => (
    <div onClick={onClick} style={{
      background:'#111318', border:'1px solid #1e293b', borderRadius:'12px',
      padding:'16px', cursor:'pointer', transition:'border-color .2s',
    }}
      onMouseEnter={e => e.currentTarget.style.borderColor='#3b82f6'}
      onMouseLeave={e => e.currentTarget.style.borderColor='#1e293b'}
    >
      <div style={{ display:'flex', gap:'12px', marginBottom:'10px' }}>
        <div style={{
          width:'48px', height:'48px', borderRadius:'10px',
          background:'#1e293b', display:'flex', alignItems:'center',
          justifyContent:'center', fontSize:'20px', flexShrink:0,
        }}>
          {centre.logo_url ? <img src={centre.logo_url} alt="" style={{ width:'100%', borderRadius:'10px' }} />
            : <span>🏫</span>}
        </div>
        <div style={{ flex:1, minWidth:0 }}>
          <div style={{ display:'flex', alignItems:'center', gap:'6px' }}>
            <span style={{ fontWeight:800, fontSize:'13px', color:'#f1f5f9' }}>
              {centre.nom_etablissement}
            </span>
            {centre.verifie && <CheckCircleIcon />}
          </div>
          <div style={{ display:'flex', alignItems:'center', gap:'4px', color:'#64748b', fontSize:'11px' }}>
            <MapPinIcon /> {centre.wilaya}
          </div>
          <Stars note={centre.note_moyenne} />
        </div>
        {centre.score && (
          <div style={{ fontSize:'10px', color:'#60a5fa', fontWeight:700 }}>
            Score {centre.score}
          </div>
        )}
      </div>

      <div style={{ display:'flex', flexWrap:'wrap', gap:'4px', marginBottom:'8px' }}>
        {(centre.matieres_enseignees || []).slice(0, 4).map(m => (
          <span key={m} style={{
            background:'#1e3a5f', color:'#60a5fa', fontSize:'9px',
            padding:'2px 7px', borderRadius:'20px',
          }}>{m}</span>
        ))}
      </div>

      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <span style={{ fontSize:'11px', color:'#94a3b8' }}>
          {centre.tarif_heure_min && `Dès ${centre.tarif_heure_min} DA/h`}
        </span>
        {centre.accepte_essai_gratuit && (
          <span style={{ background:'#14532d', color:'#4ade80', fontSize:'9px',
            padding:'2px 7px', borderRadius:'20px', fontWeight:700 }}>
            Essai gratuit
          </span>
        )}
      </div>
    </div>
  );

  return (
    <div style={{ minHeight:'100vh', background:'#08090f', color:'#e2e8f0', padding:'24px' }}>

      <div style={{ marginBottom:'28px' }}>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'#fff', marginBottom:'4px' }}>
          🏛️ Centres — Marketplace EduGest DZ
        </h1>
        <p style={{ fontSize:'12px', color:'#64748b' }}>
          Trouvez le meilleur centre ou enseignant près de chez vous
        </p>
      </div>

      <div style={{
        background:'#111318', border:'1px solid #1e293b', borderRadius:'12px',
        padding:'16px', marginBottom:'20px',
      }}>
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr auto', gap:'10px' }}>
          <select value={filtres.wilaya}
            onChange={e => setFiltres(f => ({ ...f, wilaya: e.target.value }))}
            style={{ background:'#1e293b', border:'none', borderRadius:'8px',
              color:'#e2e8f0', padding:'10px 12px', fontSize:'12px' }}>
            <option value="">📍 Toutes les wilayas</option>
            {WILAYAS_DZ.map(w => <option key={w} value={w}>{w}</option>)}
          </select>

          <select value={filtres.matiere}
            onChange={e => setFiltres(f => ({ ...f, matiere: e.target.value }))}
            style={{ background:'#1e293b', border:'none', borderRadius:'8px',
              color:'#e2e8f0', padding:'10px 12px', fontSize:'12px' }}>
            <option value="">📚 Toutes les matières</option>
            {MATIERES.map(m => <option key={m} value={m}>{m}</option>)}
          </select>

          <select value={filtres.niveau}
            onChange={e => setFiltres(f => ({ ...f, niveau: e.target.value }))}
            style={{ background:'#1e293b', border:'none', borderRadius:'8px',
              color:'#e2e8f0', padding:'10px 12px', fontSize:'12px' }}>
            <option value="">🎓 Tous les niveaux</option>
            {NIVEAUX.map(n => <option key={n} value={n}>{n}</option>)}
          </select>

          <button onClick={rechercher} disabled={loading}
            style={{
              background: loading ? '#1e293b' : 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
              color:'#fff', border:'none', borderRadius:'8px',
              padding:'10px 20px', fontWeight:700, fontSize:'12px', cursor:'pointer',
              display:'flex', alignItems:'center', gap:'6px',
            }}>
            {loading ? '...' : <><SearchIcon />Rechercher</>}
          </button>
        </div>

        <div style={{ marginTop:'10px', display:'flex', alignItems:'center', gap:'12px' }}>
          <label style={{ display:'flex', alignItems:'center', gap:'6px', fontSize:'11px', color:'#94a3b8', cursor:'pointer' }}>
            <input type="checkbox" checked={filtres.essai_gratuit}
              onChange={e => setFiltres(f => ({ ...f, essai_gratuit: e.target.checked }))} />
            Essai gratuit uniquement
          </label>
          <input type="number" placeholder="Tarif max (DA/h)"
            value={filtres.tarif_max}
            onChange={e => setFiltres(f => ({ ...f, tarif_max: e.target.value }))}
            style={{ background:'#1e293b', border:'none', borderRadius:'6px',
              color:'#e2e8f0', padding:'6px 10px', fontSize:'11px', width:'140px' }} />
        </div>
      </div>

      {centres.length > 0 && (
        <div style={{ marginBottom:'28px' }}>
          <div style={{ fontSize:'11px', color:'#64748b', marginBottom:'12px' }}>
            {centres.length} centre{centres.length > 1 ? 's' : ''} trouvé{centres.length > 1 ? 's' : ''}
          </div>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'10px' }}>
            {centres.map(c => (
              <CentreCard key={c.id || c.tenant_id} centre={c}
                onClick={() => fetchProfil(c.tenant_id)} />
            ))}
          </div>
        </div>
      )}

      {centres.length === 0 && featured.length > 0 && (
        <div>
          <div style={{ fontSize:'11px', color:'#60a5fa', fontWeight:700,
            textTransform:'uppercase', letterSpacing:'1.5px', marginBottom:'12px' }}>
            ⭐ Centres vérifiés — Mis en avant
          </div>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'10px' }}>
            {featured.map(c => (
              <CentreCard key={c.id || c.tenant_id} centre={c}
                onClick={() => fetchProfil(c.tenant_id)} />
            ))}
          </div>
        </div>
      )}

      {selectedCentre && (
        <div style={{
          position:'fixed', inset:0, background:'rgba(0,0,0,.7)',
          display:'flex', alignItems:'center', justifyContent:'center', zIndex:1000,
        }} onClick={() => setSelectedCentre(null)}>
          <div style={{
            background:'#111318', border:'1px solid #1e293b', borderRadius:'16px',
            padding:'24px', maxWidth:'600px', width:'90%', maxHeight:'80vh',
            overflowY:'auto',
          }} onClick={e => e.stopPropagation()}>
            <div style={{ display:'flex', justifyContent:'space-between', alignItems:'flex-start', marginBottom:'16px' }}>
              <div>
                <h2 style={{ fontSize:'18px', fontWeight:900, color:'#fff', marginBottom:'4px' }}>
                  {selectedCentre.nom_etablissement}
                  {selectedCentre.verifie && <CheckCircleIcon />}
                </h2>
                <div style={{ fontSize:'11px', color:'#64748b' }}>
                  📍 {selectedCentre.wilaya} {selectedCentre.commune && `· ${selectedCentre.commune}`}
                </div>
                <Stars note={selectedCentre.note_moyenne} />
              </div>
              <button onClick={() => setSelectedCentre(null)}
                style={{ background:'none', border:'none', color:'#64748b', cursor:'pointer', fontSize:'20px' }}>
                ×
              </button>
            </div>

            {selectedCentre.description && (
              <p style={{ fontSize:'12px', color:'#94a3b8', marginBottom:'16px', lineHeight:1.7 }}>
                {selectedCentre.description}
              </p>
            )}

            {selectedCentre.offres?.length > 0 && (
              <div style={{ marginBottom:'16px' }}>
                <div style={{ fontSize:'11px', color:'#60a5fa', fontWeight:700, marginBottom:'8px' }}>
                  OFFRES DISPONIBLES
                </div>
                {selectedCentre.offres.map(o => (
                  <div key={o.id} style={{
                    background:'#1e293b', borderRadius:'8px', padding:'10px 12px',
                    marginBottom:'6px', display:'flex', justifyContent:'space-between',
                  }}>
                    <div>
                      <div style={{ fontSize:'12px', fontWeight:700, color:'#f1f5f9' }}>{o.titre}</div>
                      <div style={{ fontSize:'10px', color:'#64748b' }}>{o.matiere} · {o.type}</div>
                    </div>
                    <div style={{ textAlign:'right' }}>
                      <div style={{ fontSize:'13px', fontWeight:800, color:'#4ade80' }}>
                        {o.tarif_heure} DA/h
                      </div>
                      {o.essai_gratuit && (
                        <span style={{ fontSize:'9px', background:'#14532d', color:'#4ade80',
                          padding:'1px 6px', borderRadius:'20px' }}>Essai gratuit</span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}

            {selectedCentre.avis?.length > 0 && (
              <div>
                <div style={{ fontSize:'11px', color:'#60a5fa', fontWeight:700, marginBottom:'8px' }}>
                  AVIS ({selectedCentre.nb_avis})
                </div>
                {selectedCentre.avis.slice(0,3).map(a => (
                  <div key={a.id} style={{
                    background:'#0d2515', borderRadius:'8px', padding:'10px 12px', marginBottom:'6px',
                  }}>
                    <div style={{ display:'flex', justifyContent:'space-between', marginBottom:'4px' }}>
                      <span style={{ fontSize:'11px', fontWeight:700, color:'#4ade80' }}>
                        {a.parent?.prenom} {a.parent?.nom}
                      </span>
                      <Stars note={a.note} />
                    </div>
                    {a.commentaire && (
                      <p style={{ fontSize:'11px', color:'#94a3b8', margin:0 }}>{a.commentaire}</p>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
