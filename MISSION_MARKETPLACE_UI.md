# 🛒 MISSION 2 — Marketplace UI Complète (Recherche, Réservation, Paiement, Avis)
## EduGest DZ · Branche : develop · Tests : 859+ ✅
## Source de revenus principale via commissions 5-10%

---

## DIAGNOSTIC LU DANS LE REPO

```
BACKEND (75% complet) :
✅ OffreController : recherche/show/store/update
✅ ReservationController : store/payer/confirmer/terminer/annuler/index
✅ AvisController : store/byEnseignant
✅ CommissionService : calculateCommission() + enregistrer()
✅ VisioService : génère lien Jitsi
✅ Routes /api/v1/marketplace/* toutes définies

FRONTEND (0%) :
❌ MarketplacePage.jsx → ABSENT
❌ OffreDetailPage.jsx → ABSENT
❌ ReservationFlowPage.jsx → ABSENT
❌ EspaceEnseignantMarketplace.jsx → ABSENT
❌ Aucun lien dans la Sidebar pour accéder au marketplace
```

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
cd edugestdz/frontend
```

---

## ÉTAPE 1 — MarketplacePage.jsx (catalogue des offres)

**Créer** : `edugestdz/frontend/src/pages/MarketplacePage.jsx`

```jsx
import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '@api/client';

const NIVEAUX = ['tous', '1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS'];
const TYPES   = ['tous', 'presentiel', 'en_ligne', 'les_deux'];

function EtoilesNote({ note, total }) {
  return (
    <span style={{ fontSize:'12px' }}>
      {'⭐'.repeat(Math.round(note ?? 0))}{'☆'.repeat(5 - Math.round(note ?? 0))}
      {total != null && <span style={{ color:'var(--muted)', marginLeft:'4px' }}>({total})</span>}
    </span>
  );
}

export default function MarketplacePage() {
  const [offres,     setOffres]     = useState([]);
  const [meta,       setMeta]       = useState(null);
  const [loading,    setLoading]    = useState(true);
  const [q,          setQ]          = useState('');
  const [niveau,     setNiveau]     = useState('tous');
  const [typeC,      setTypeC]      = useState('tous');
  const [tarifMax,   setTarifMax]   = useState('');
  const [page,       setPage]       = useState(1);

  const charger = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page, per_page: 12 });
      if (q)        params.append('q', q);
      if (niveau !== 'tous') params.append('niveau', niveau);
      if (typeC  !== 'tous') params.append('type_cours', typeC);
      if (tarifMax)  params.append('tarif_max', tarifMax);

      const res = await api(`/marketplace/offres?${params}`);
      if (res.success) { setOffres(res.data ?? []); setMeta(res.meta); }
    } catch {}
    finally { setLoading(false); }
  }, [q, niveau, typeC, tarifMax, page]);

  useEffect(() => { charger(); }, [charger]);

  const typeLabel = (t) => ({ presentiel:'Présentiel', en_ligne:'En ligne', les_deux:'Présentiel & Ligne' })[t] ?? t;

  return (
    <div className="animate-fadeIn space-y-5">
      <div>
        <h1 style={{ fontSize:'22px', fontWeight:900, color:'var(--text)' }}>🛒 Marketplace des Cours</h1>
        <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'4px' }}>
          Trouvez un enseignant qualifié pour votre enfant dans toute l'Algérie
        </p>
      </div>

      {/* Filtres */}
      <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'16px', padding:'20px', display:'flex', flexWrap:'wrap', gap:'12px', alignItems:'flex-end' }}>
        <div style={{ flex:'1', minWidth:'200px' }}>
          <label style={{ fontSize:'11px', fontWeight:700, color:'var(--muted)', textTransform:'uppercase' }}>Rechercher</label>
          <input value={q} onChange={e => { setQ(e.target.value); setPage(1); }} placeholder="Mathématiques, Physique..."
            style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 12px', color:'var(--text)', fontSize:'13px', marginTop:'4px', outline:'none', boxSizing:'border-box' }} />
        </div>
        <div>
          <label style={{ fontSize:'11px', fontWeight:700, color:'var(--muted)', textTransform:'uppercase' }}>Niveau</label>
          <select value={niveau} onChange={e => { setNiveau(e.target.value); setPage(1); }}
            style={{ display:'block', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 10px', color:'var(--text)', fontSize:'13px', marginTop:'4px', outline:'none' }}>
            {NIVEAUX.map(n => <option key={n} value={n}>{n === 'tous' ? 'Tous niveaux' : n}</option>)}
          </select>
        </div>
        <div>
          <label style={{ fontSize:'11px', fontWeight:700, color:'var(--muted)', textTransform:'uppercase' }}>Type</label>
          <select value={typeC} onChange={e => { setTypeC(e.target.value); setPage(1); }}
            style={{ display:'block', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 10px', color:'var(--text)', fontSize:'13px', marginTop:'4px', outline:'none' }}>
            {TYPES.map(t => <option key={t} value={t}>{typeLabel(t)}</option>)}
          </select>
        </div>
        <div>
          <label style={{ fontSize:'11px', fontWeight:700, color:'var(--muted)', textTransform:'uppercase' }}>Tarif max (DA)</label>
          <input type="number" value={tarifMax} onChange={e => { setTarifMax(e.target.value); setPage(1); }} placeholder="3000"
            style={{ width:'100px', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 10px', color:'var(--text)', fontSize:'13px', marginTop:'4px', outline:'none' }} />
        </div>
      </div>

      {/* Grille offres */}
      {loading ? (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fill,minmax(300px,1fr))', gap:'16px' }}>
          {[1,2,3,4,5,6].map(i => <div key={i} style={{ height:'200px', background:'var(--surface2)', borderRadius:'16px', animation:'pulse 1.5s infinite' }} />)}
        </div>
      ) : offres.length === 0 ? (
        <div style={{ textAlign:'center', padding:'60px 20px', background:'var(--surface)', borderRadius:'16px', border:'1px solid var(--border)' }}>
          <div style={{ fontSize:'48px', marginBottom:'12px' }}>🔍</div>
          <p style={{ color:'var(--text)', fontWeight:700 }}>Aucune offre trouvée</p>
          <p style={{ color:'var(--muted)', fontSize:'13px', marginTop:'4px' }}>Modifiez vos filtres ou revenez plus tard</p>
        </div>
      ) : (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(auto-fill,minmax(300px,1fr))', gap:'16px' }}>
          {offres.map(offre => (
            <Link key={offre.id} to={`/marketplace/offres/${offre.id}`} style={{ textDecoration:'none' }}>
              <div style={{
                background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'16px',
                padding:'20px', transition:'all 0.2s', cursor:'pointer',
                ':hover': { borderColor:'var(--accent)', transform:'translateY(-2px)' }
              }}
              onMouseEnter={e => { e.currentTarget.style.borderColor='rgba(37,99,235,0.4)'; e.currentTarget.style.transform='translateY(-2px)'; }}
              onMouseLeave={e => { e.currentTarget.style.borderColor='var(--border)'; e.currentTarget.style.transform='none'; }}>
                <div style={{ display:'flex', alignItems:'flex-start', justifyContent:'space-between', gap:'12px', marginBottom:'12px' }}>
                  <div style={{ flex:1 }}>
                    <div style={{ fontSize:'11px', fontWeight:700, color:'var(--accent)', marginBottom:'4px' }}>
                      {offre.matiere?.nom_fr ?? 'Matière'}
                    </div>
                    <div style={{ fontSize:'14px', fontWeight:800, color:'var(--text)' }}>
                      {offre.enseignant?.user?.nom} {offre.enseignant?.user?.prenom}
                    </div>
                  </div>
                  <div style={{ textAlign:'right', flexShrink:0 }}>
                    <div style={{ fontSize:'18px', fontWeight:900, color:'var(--green)' }}>
                      {Number(offre.tarif_seance).toLocaleString('fr-DZ')} DA
                    </div>
                    <div style={{ fontSize:'10px', color:'var(--muted)' }}>/séance</div>
                  </div>
                </div>

                <div style={{ display:'flex', flexWrap:'wrap', gap:'6px', marginBottom:'10px' }}>
                  <span style={{ background:'rgba(37,99,235,0.12)', color:'#60a5fa', padding:'2px 8px', borderRadius:'10px', fontSize:'11px', fontWeight:600 }}>
                    {offre.niveau}
                  </span>
                  <span style={{ background:'var(--surface2)', color:'var(--muted)', padding:'2px 8px', borderRadius:'10px', fontSize:'11px' }}>
                    {typeLabel(offre.type_cours)}
                  </span>
                  {offre.places_restantes != null && (
                    <span style={{ background: offre.places_restantes > 0 ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)', color: offre.places_restantes > 0 ? 'var(--green)' : 'var(--red)', padding:'2px 8px', borderRadius:'10px', fontSize:'11px', fontWeight:600 }}>
                      {offre.places_restantes > 0 ? `${offre.places_restantes} place(s)` : 'Complet'}
                    </span>
                  )}
                </div>

                {offre.description && (
                  <p style={{ color:'var(--muted)', fontSize:'12px', lineHeight:'1.5', marginBottom:'10px',
                    overflow:'hidden', display:'-webkit-box', WebkitLineClamp:2, WebkitBoxOrient:'vertical' }}>
                    {offre.description}
                  </p>
                )}

                <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between' }}>
                  <EtoilesNote note={offre.note_moyenne} total={offre.nb_avis} />
                  {offre.wilaya?.nom && (
                    <span style={{ color:'var(--muted)', fontSize:'11px' }}>📍 {offre.wilaya.nom}</span>
                  )}
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div style={{ display:'flex', justifyContent:'center', gap:'8px' }}>
          <button onClick={() => setPage(p => Math.max(1, p-1))} disabled={page === 1}
            style={{ padding:'8px 16px', background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'8px', color:'var(--text)', cursor:'pointer', fontSize:'13px', fontWeight:600, opacity: page === 1 ? 0.5 : 1 }}>
            ← Préc.
          </button>
          <span style={{ padding:'8px 16px', color:'var(--muted)', fontSize:'13px' }}>
            Page {meta.current_page} / {meta.last_page} ({meta.total} offres)
          </span>
          <button onClick={() => setPage(p => Math.min(meta.last_page, p+1))} disabled={page === meta.last_page}
            style={{ padding:'8px 16px', background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'8px', color:'var(--text)', cursor:'pointer', fontSize:'13px', fontWeight:600, opacity: page === meta.last_page ? 0.5 : 1 }}>
            Suiv. →
          </button>
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 2 — OffreDetailPage.jsx (fiche offre + réservation)

**Créer** : `edugestdz/frontend/src/pages/OffreDetailPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '@api/client';

export default function OffreDetailPage() {
  const { id }     = useParams();
  const navigate   = useNavigate();
  const [offre,    setOffre]    = useState(null);
  const [avis,     setAvis]     = useState([]);
  const [loading,  setLoading]  = useState(true);
  const [dateDebut,setDateDebut]= useState('');
  const [message,  setMessage]  = useState('');
  const [reserving,setReserving]= useState(false);
  const [success,  setSuccess]  = useState(null);
  const [error,    setError]    = useState('');

  useEffect(() => {
    Promise.all([
      api(`/marketplace/offres/${id}`),
      api(`/marketplace/avis/enseignant/${id}`).catch(() => ({ data: { avis: [], moyenne: null } })),
    ]).then(([offreRes, avisRes]) => {
      if (offreRes.success) setOffre(offreRes.data.offre);
      if (avisRes.success)  setAvis(avisRes.data?.avis ?? []);
    }).finally(() => setLoading(false));
  }, [id]);

  const reserver = async () => {
    if (!dateDebut) { setError('Veuillez choisir une date de début.'); return; }
    setReserving(true); setError('');
    try {
      const res = await api('/marketplace/reservations', {
        method: 'POST',
        body: JSON.stringify({ offre_id: id, date_debut: dateDebut, message }),
      });
      if (res.success) setSuccess(res.data);
    } catch (e) { setError(e.message ?? 'Erreur lors de la réservation'); }
    finally { setReserving(false); }
  };

  if (loading) return <div style={{ padding:'40px', textAlign:'center', color:'var(--muted)' }}>Chargement...</div>;
  if (!offre)  return <div style={{ padding:'40px', textAlign:'center', color:'var(--red)' }}>Offre introuvable</div>;

  const enseignant  = offre.enseignant;
  const nomComplet  = `${enseignant?.user?.nom ?? ''} ${enseignant?.user?.prenom ?? ''}`.trim();
  const tarif       = Number(offre.tarif_seance).toLocaleString('fr-DZ');

  if (success) {
    return (
      <div style={{ maxWidth:'500px', margin:'0 auto', padding:'40px 20px', textAlign:'center' }}>
        <div style={{ fontSize:'64px', marginBottom:'16px' }}>✅</div>
        <h2 style={{ color:'var(--green)', fontWeight:800, fontSize:'22px', marginBottom:'8px' }}>Réservation créée !</h2>
        <p style={{ color:'var(--muted)', fontSize:'13px', marginBottom:'24px', lineHeight:'1.6' }}>
          L'enseignant va confirmer votre réservation. Vous serez notifié par email et notification in-app.
        </p>
        <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'12px', padding:'16px', marginBottom:'20px', fontSize:'13px', textAlign:'left' }}>
          <div style={{ fontWeight:700, color:'var(--text)', marginBottom:'8px' }}>Détails de la réservation :</div>
          <div style={{ color:'var(--muted)' }}>📅 Date début : <strong style={{ color:'var(--text)' }}>{dateDebut}</strong></div>
          <div style={{ color:'var(--muted)' }}>💰 Montant : <strong style={{ color:'var(--green)' }}>{tarif} DA</strong></div>
          <div style={{ color:'var(--muted)' }}>🔄 Statut : <strong>En attente de confirmation</strong></div>
        </div>
        <button onClick={() => navigate('/marketplace/mes-reservations')}
          style={{ background:'var(--accent)', color:'white', border:'none', borderRadius:'10px', padding:'12px 24px', fontSize:'14px', fontWeight:700, cursor:'pointer', marginRight:'8px' }}>
          Mes réservations
        </button>
        <button onClick={() => navigate('/marketplace')}
          style={{ background:'var(--surface2)', color:'var(--text)', border:'1px solid var(--border)', borderRadius:'10px', padding:'12px 24px', fontSize:'14px', cursor:'pointer' }}>
          Continuer la recherche
        </button>
      </div>
    );
  }

  return (
    <div style={{ maxWidth:'900px', margin:'0 auto' }} className="animate-fadeIn">
      <button onClick={() => navigate(-1)} style={{ background:'none', border:'none', color:'var(--muted)', cursor:'pointer', fontSize:'13px', marginBottom:'20px', display:'flex', alignItems:'center', gap:'4px' }}>
        ← Retour
      </button>

      <div style={{ display:'grid', gridTemplateColumns:'1fr 340px', gap:'24px', alignItems:'start' }}>

        {/* Colonne info */}
        <div>
          <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'16px', padding:'24px', marginBottom:'20px' }}>
            <div style={{ fontSize:'12px', fontWeight:700, color:'var(--accent)', marginBottom:'6px' }}>
              {offre.matiere?.nom_fr ?? 'Matière'}
            </div>
            <h1 style={{ fontSize:'22px', fontWeight:900, color:'var(--text)', marginBottom:'4px' }}>{nomComplet}</h1>
            <div style={{ color:'var(--muted)', fontSize:'13px', marginBottom:'16px' }}>
              {enseignant?.specialite && `Spécialité : ${enseignant.specialite} · `}
              {offre.wilaya?.nom && `📍 ${offre.wilaya.nom}`}
            </div>

            <div style={{ display:'flex', flexWrap:'wrap', gap:'8px', marginBottom:'16px' }}>
              <span style={{ background:'rgba(37,99,235,0.12)', color:'#60a5fa', padding:'4px 12px', borderRadius:'12px', fontSize:'12px', fontWeight:600 }}>{offre.niveau}</span>
              <span style={{ background:'var(--surface2)', color:'var(--muted)', padding:'4px 12px', borderRadius:'12px', fontSize:'12px' }}>
                {({ presentiel:'Présentiel', en_ligne:'En ligne', les_deux:'Présentiel & Ligne' })[offre.type_cours] ?? offre.type_cours}
              </span>
              <span style={{ background: offre.places_restantes > 0 ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)', color: offre.places_restantes > 0 ? 'var(--green)' : 'var(--red)', padding:'4px 12px', borderRadius:'12px', fontSize:'12px', fontWeight:600 }}>
                {offre.places_restantes > 0 ? `${offre.places_restantes} place(s) dispo` : 'Complet'}
              </span>
            </div>

            {offre.description && (
              <p style={{ color:'var(--muted)', fontSize:'13px', lineHeight:'1.7' }}>{offre.description}</p>
            )}
          </div>

          {/* Avis */}
          <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'16px', padding:'24px' }}>
            <h3 style={{ fontWeight:800, fontSize:'15px', color:'var(--text)', marginBottom:'16px' }}>
              ⭐ Avis ({avis.length})
            </h3>
            {avis.length === 0 ? (
              <p style={{ color:'var(--muted)', fontSize:'13px' }}>Pas encore d'avis pour cet enseignant.</p>
            ) : (
              <div style={{ display:'flex', flexDirection:'column', gap:'12px' }}>
                {avis.map(a => (
                  <div key={a.id} style={{ borderBottom:'1px solid var(--border)', paddingBottom:'12px' }}>
                    <div style={{ display:'flex', alignItems:'center', gap:'8px', marginBottom:'6px' }}>
                      <span style={{ fontSize:'12px' }}>{'⭐'.repeat(a.note)}{'☆'.repeat(5-a.note)}</span>
                      <span style={{ fontSize:'11px', color:'var(--muted)' }}>
                        {new Date(a.created_at).toLocaleDateString('fr-DZ')}
                      </span>
                    </div>
                    {a.commentaire && <p style={{ color:'var(--text)', fontSize:'13px' }}>{a.commentaire}</p>}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Panneau réservation sticky */}
        <div style={{ position:'sticky', top:'20px' }}>
          <div style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontSize:'28px', fontWeight:900, color:'var(--green)', marginBottom:'4px' }}>
              {tarif} DA
            </div>
            <div style={{ color:'var(--muted)', fontSize:'12px', marginBottom:'20px' }}>par séance</div>

            {error && (
              <div style={{ background:'rgba(239,68,68,0.1)', border:'1px solid rgba(239,68,68,0.3)', borderRadius:'8px', padding:'10px', marginBottom:'12px', color:'#f87171', fontSize:'12px' }}>
                ❌ {error}
              </div>
            )}

            <div style={{ marginBottom:'14px' }}>
              <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>
                Date de début souhaitée *
              </label>
              <input type="date" value={dateDebut} onChange={e => setDateDebut(e.target.value)}
                min={new Date().toISOString().split('T')[0]}
                style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 12px', color:'var(--text)', fontSize:'13px', outline:'none', boxSizing:'border-box' }} />
            </div>

            <div style={{ marginBottom:'16px' }}>
              <label style={{ display:'block', fontSize:'12px', fontWeight:700, color:'var(--text)', marginBottom:'6px' }}>
                Message à l'enseignant (optionnel)
              </label>
              <textarea value={message} onChange={e => setMessage(e.target.value)} rows={3} maxLength={500}
                placeholder="Expliquez les besoins de votre enfant..."
                style={{ width:'100%', background:'var(--surface2)', border:'1px solid var(--border)', borderRadius:'8px', padding:'8px 12px', color:'var(--text)', fontSize:'13px', outline:'none', resize:'none', boxSizing:'border-box' }} />
            </div>

            <button onClick={reserver} disabled={reserving || offre.places_restantes < 1}
              style={{
                width:'100%', background: (reserving || offre.places_restantes < 1) ? 'var(--surface2)' : 'var(--accent)',
                color: (reserving || offre.places_restantes < 1) ? 'var(--muted)' : 'white',
                border:'none', borderRadius:'10px', padding:'14px', fontSize:'15px', fontWeight:800,
                cursor: (reserving || offre.places_restantes < 1) ? 'not-allowed' : 'pointer',
              }}>
              {offre.places_restantes < 1 ? '❌ Complet' : reserving ? '⏳ Réservation...' : '📅 Réserver cette offre'}
            </button>

            <p style={{ color:'var(--muted)', fontSize:'11px', textAlign:'center', marginTop:'12px', lineHeight:'1.6' }}>
              Paiement après confirmation de l'enseignant.<br/>
              Annulation gratuite sous 24h.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
```

---

## ÉTAPE 3 — MesReservationsPage.jsx

**Créer** : `edugestdz/frontend/src/pages/MesReservationsPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import api from '@api/client';

const STATUT_CONFIG = {
  en_attente:  { label:'⏳ En attente', color:'var(--yellow)' },
  confirmee:   { label:'✅ Confirmée',  color:'var(--green)' },
  payee:       { label:'💳 Payée',      color:'var(--accent)' },
  terminee:    { label:'🏁 Terminée',   color:'var(--muted)' },
  annulee:     { label:'❌ Annulée',    color:'var(--red)' },
};

export default function MesReservationsPage() {
  const [reservations, setReservations] = useState([]);
  const [loading,      setLoading]      = useState(true);
  const [filtreStatut, setFiltreStatut] = useState('tous');

  const charger = async () => {
    setLoading(true);
    try {
      const res = await api('/marketplace/reservations');
      if (res.success) setReservations(res.data?.data ?? res.data ?? []);
    } catch {}
    finally { setLoading(false); }
  };

  useEffect(() => { charger(); }, []);

  const annuler = async (id) => {
    if (!confirm('Annuler cette réservation ?')) return;
    try {
      await api(`/marketplace/reservations/${id}/annuler`, { method:'PATCH', body: JSON.stringify({ motif:'Annulation client' }) });
      charger();
    } catch (e) { alert(e.message); }
  };

  const filtrees = filtreStatut === 'tous' ? reservations : reservations.filter(r => r.statut === filtreStatut);

  return (
    <div className="animate-fadeIn space-y-5">
      <h1 style={{ fontSize:'22px', fontWeight:900, color:'var(--text)' }}>📅 Mes Réservations</h1>

      <div style={{ display:'flex', gap:'8px', flexWrap:'wrap' }}>
        {['tous', ...Object.keys(STATUT_CONFIG)].map(s => (
          <button key={s} onClick={() => setFiltreStatut(s)}
            style={{ padding:'6px 14px', borderRadius:'20px', fontSize:'12px', fontWeight:600, border:'1px solid', cursor:'pointer',
              background: filtreStatut === s ? 'var(--accent)' : 'var(--surface)',
              color:      filtreStatut === s ? 'white' : 'var(--muted)',
              borderColor:filtreStatut === s ? 'var(--accent)' : 'var(--border)' }}>
            {s === 'tous' ? `Toutes (${reservations.length})` : STATUT_CONFIG[s].label}
          </button>
        ))}
      </div>

      {loading ? (
        <div style={{ height:'200px', background:'var(--surface2)', borderRadius:'16px', animation:'pulse 1.5s infinite' }} />
      ) : filtrees.length === 0 ? (
        <div style={{ textAlign:'center', padding:'60px', background:'var(--surface)', borderRadius:'16px', border:'1px solid var(--border)' }}>
          <div style={{ fontSize:'48px', marginBottom:'12px' }}>📅</div>
          <p style={{ color:'var(--text)', fontWeight:700 }}>Aucune réservation</p>
          <a href="/marketplace" style={{ color:'var(--accent)', fontSize:'13px', marginTop:'8px', display:'inline-block' }}>Parcourir les offres →</a>
        </div>
      ) : (
        <div style={{ display:'flex', flexDirection:'column', gap:'12px' }}>
          {filtrees.map(r => {
            const sc = STATUT_CONFIG[r.statut] ?? { label: r.statut, color:'var(--muted)' };
            return (
              <div key={r.id} style={{ background:'var(--surface)', border:'1px solid var(--border)', borderRadius:'14px', padding:'20px' }}>
                <div style={{ display:'flex', alignItems:'flex-start', justifyContent:'space-between', gap:'16px' }}>
                  <div>
                    <div style={{ fontWeight:800, fontSize:'15px', color:'var(--text)', marginBottom:'4px' }}>
                      {r.offre?.matiere?.nom_fr ?? 'Cours'} — {r.offre?.enseignant?.user?.nom} {r.offre?.enseignant?.user?.prenom}
                    </div>
                    <div style={{ color:'var(--muted)', fontSize:'12px' }}>
                      📅 Début : {r.date_debut ? new Date(r.date_debut).toLocaleDateString('fr-DZ') : '—'} &nbsp;·&nbsp;
                      💰 {Number(r.montant).toLocaleString('fr-DZ')} DA
                    </div>
                    {r.lien_visio && (
                      <a href={r.lien_visio} target="_blank" rel="noreferrer"
                        style={{ color:'var(--green)', fontSize:'12px', fontWeight:700, marginTop:'6px', display:'inline-block' }}>
                        🎥 Rejoindre le cours (Jitsi) →
                      </a>
                    )}
                  </div>
                  <div style={{ textAlign:'right', flexShrink:0 }}>
                    <span style={{ display:'inline-block', padding:'4px 12px', borderRadius:'20px', fontSize:'12px', fontWeight:700,
                      background:`${sc.color}18`, color: sc.color }}>
                      {sc.label}
                    </span>
                    {['en_attente','confirmee'].includes(r.statut) && (
                      <div style={{ marginTop:'8px' }}>
                        <button onClick={() => annuler(r.id)}
                          style={{ fontSize:'11px', color:'var(--red)', background:'none', border:'1px solid var(--red)', borderRadius:'6px', padding:'4px 10px', cursor:'pointer' }}>
                          Annuler
                        </button>
                      </div>
                    )}
                    {r.statut === 'terminee' && (
                      <div style={{ marginTop:'8px' }}>
                        <a href={`/marketplace/avis/${r.id}`}
                          style={{ fontSize:'11px', color:'var(--accent)', textDecoration:'none' }}>
                          ⭐ Laisser un avis
                        </a>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 4 — Ajouter routes dans App.jsx + Sidebar

**Ajouter dans** `edugestdz/frontend/src/App.jsx` :

```jsx
import MarketplacePage      from '@pages/MarketplacePage';
import OffreDetailPage      from '@pages/OffreDetailPage';
import MesReservationsPage  from '@pages/MesReservationsPage';

// Dans les routes :
<Route path="/marketplace"                  element={<MarketplacePage />} />
<Route path="/marketplace/offres/:id"       element={<OffreDetailPage />} />
<Route path="/marketplace/mes-reservations" element={<MesReservationsPage />} />
```

**Ajouter dans** `edugestdz/frontend/src/components/Sidebar.jsx` :

```jsx
{ path: '/marketplace',                 icon: ShoppingBag,  label: '🛒 Marketplace',       roles: ['all'] },
{ path: '/marketplace/mes-reservations',icon: Calendar,     label: '📅 Mes Réservations',  roles: ['eleve', 'parent'] },
```

---

## ÉTAPE 5 — Exécution

```bash
cd edugestdz/frontend
npm run build → 0 erreurs

git add \
  frontend/src/pages/MarketplacePage.jsx \
  frontend/src/pages/OffreDetailPage.jsx \
  frontend/src/pages/MesReservationsPage.jsx \
  frontend/src/App.jsx \
  frontend/src/components/Sidebar.jsx

git commit -m "feat(marketplace-ui): Interface complète marketplace — catalogue, fiche offre, réservation, mes réservations

- MarketplacePage: catalogue paginé avec filtres (niveau, type, tarif, recherche texte)
- OffreDetailPage: fiche complète + avis enseignant + formulaire réservation sticky
- MesReservationsPage: liste réservations avec filtres statut + annulation + lien Jitsi
- Sidebar: liens Marketplace + Mes Réservations
- App.jsx: 3 nouvelles routes"

git push origin develop → PR → main
```

---

## PROMPT EXACT POUR DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_MARKETPLACE_UI.md — 5 étapes.

RÈGLES :
1. MarketplacePage : l'endpoint GET /api/v1/marketplace/offres retourne {success, data, meta}
   Utiliser api() de @api/client.js (qui gère le token automatiquement).
2. OffreDetailPage : GET /api/v1/marketplace/offres/{id} retourne {success, data: {offre}}
   GET /api/v1/marketplace/avis/enseignant/{enseignantId} retourne {success, data: {avis, moyenne}}
   Si avis endpoint échoue → catch silencieux (avis=[], moyenne=null)
3. Les styles utilisent var(--bg), var(--surface), var(--accent) — JAMAIS de hex hardcodé
4. Pas de dépendances npm supplémentaires. Tout en React vanilla + fetch.
5. OffreDetailPage : le formulaire de réservation appelle POST /api/v1/marketplace/reservations
   avec {offre_id, date_debut, message}. Si places_restantes = 0 → bouton disabled.
6. MesReservationsPage : GET /api/v1/marketplace/reservations (liste pour l'utilisateur connecté)
   Si l'API retourne {data: {data: [...]}} (pagination) → extraire data.data

npm run build → 0 erreurs
git push origin develop → PR → main
```
