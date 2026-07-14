import { useState, useEffect, useCallback } from 'react';
import { AlertTriangle, Camera, CheckCircle, Clock, Shield, RefreshCw } from 'lucide-react';

const api = (path, opts) => fetch(`/api/v1${path}`, {
  headers: {
    Authorization: `Bearer ${localStorage.getItem('access_token')}`,
    'Content-Type': 'application/json',
    'X-Tenant-ID': localStorage.getItem('tenantId') ?? '',
  },
  ...opts,
}).then(r => r.json());

const NIVEAUX = {
  critical: { color: '#f87171', bg: '#450a0a', border: '#b91c1c', label: '🚨 CRITIQUE' },
  warning:  { color: '#fb923c', bg: '#1f1008', border: '#c2410c', label: '⚠️ Alerte'  },
  info:     { color: '#60a5fa', bg: '#0c1a30', border: '#1d4ed8', label: 'ℹ️ Info'    },
};

const TYPES_LABELS = {
  VideoMotion:        '📹 Mouvement détecté',
  AlarmLocal:         '🚨 Alarme locale',
  CrossLineDetection: '🚧 Franchissement ligne',
  IntrusionDetection: '⛔ Intrusion détectée',
  FaceDetection:      '👤 Visage détecté',
  VideoLoss:          '📵 Perte signal vidéo',
  VideoBlind:         '🙈 Sabotage caméra',
  DiskFull:           '💾 Disque plein',
  DiskError:          '❌ Erreur disque',
  NetworkAbort:       '🌐 Perte réseau',
};

export default function SurveillancePage() {
  const [alertes, setAlertes]   = useState([]);
  const [cameras, setCameras]   = useState([]);
  const [stats, setStats]       = useState({});
  const [loading, setLoading]   = useState(true);
  const [tab, setTab]           = useState('alertes');
  const [filtreNiveau, setFiltreNiveau] = useState('');
  const [filtreTraite, setFiltreTraite] = useState('false');
  const [showAddCamera, setShowAddCamera] = useState(false);
  const [newCamera, setNewCamera] = useState({ nom: '', serial_no: '', type: 'entree', ip_locale: '', localisation: '', heure_ouverture: '07:00', heure_fermeture: '20:00' });
  const [saving, setSaving]     = useState(false);
  const [webhookInfo, setWebhookInfo] = useState(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (filtreNiveau) params.append('niveau', filtreNiveau);
      if (filtreTraite !== '') params.append('traite', filtreTraite);

      const [alertesRes, camerasRes] = await Promise.all([
        api(`/surveillance/alertes?${params}`),
        api('/surveillance/cameras'),
      ]);
      setAlertes(alertesRes?.data?.alertes?.data ?? []);
      setStats(alertesRes?.data?.stats ?? {});
      setCameras(camerasRes?.data ?? []);
    } catch (e) { console.error(e); }
    finally { setLoading(false); }
  }, [filtreNiveau, filtreTraite]);

  useEffect(() => { loadData(); }, [loadData]);

  useEffect(() => {
    const interval = setInterval(loadData, 30000);
    return () => clearInterval(interval);
  }, [loadData]);

  const traiter = async (id) => {
    const note = prompt('Note (optionnelle) — ex: Fausse alarme, vérification effectuée');
    if (note === null) return;
    await api(`/surveillance/alertes/${id}/traiter`, {
      method: 'POST',
      body: JSON.stringify({ note_admin: note }),
    });
    loadData();
  };

  const ajouterCamera = async () => {
    setSaving(true);
    try {
      const res = await api('/surveillance/cameras', {
        method: 'POST',
        body: JSON.stringify(newCamera),
      });
      if (res.success) {
        setWebhookInfo(res.data);
        setShowAddCamera(false);
        setNewCamera({ nom: '', serial_no: '', type: 'entree', ip_locale: '', localisation: '', heure_ouverture: '07:00', heure_fermeture: '20:00' });
        loadData();
      } else {
        alert('Erreur : ' + (res.message ?? 'Échec enregistrement'));
      }
    } finally { setSaving(false); }
  };

  const StatBox = ({ label, value, color, urgent }) => (
    <div style={{
      background: urgent && value > 0 ? '#450a0a' : '#111318',
      border: `1px solid ${urgent && value > 0 ? '#b91c1c' : '#1e293b'}`,
      borderRadius: '10px', padding: '16px', textAlign: 'center',
      animation: urgent && value > 0 ? 'pulse 2s infinite' : 'none',
    }}>
      <div style={{ fontSize: '28px', fontWeight: 900, color }}>{value ?? 0}</div>
      <div style={{ fontSize: '10px', color: '#64748b', marginTop: '2px', textTransform: 'uppercase', letterSpacing: '1px' }}>{label}</div>
    </div>
  );

  const AlerteCard = ({ alerte }) => {
    const n = NIVEAUX[alerte.niveau] ?? NIVEAUX.warning;
    const typeLabel = TYPES_LABELS[alerte.type_alerte] ?? alerte.type_alerte;
    const heure = new Date(alerte.survenu_le).toLocaleTimeString('fr-DZ', { hour: '2-digit', minute: '2-digit' });
    const date  = new Date(alerte.survenu_le).toLocaleDateString('fr-DZ');

    return (
      <div style={{
        background: n.bg, border: `1px solid ${n.border}`,
        borderRadius: '10px', padding: '14px 16px', marginBottom: '8px',
        display: 'flex', alignItems: 'center', gap: '14px',
      }}>
        <div style={{
          width: '40px', height: '40px', borderRadius: '10px',
          background: n.color + '22', display: 'flex', alignItems: 'center',
          justifyContent: 'center', fontSize: '18px', flexShrink: 0,
        }}>
          {alerte.niveau === 'critical' ? '🚨' : alerte.niveau === 'warning' ? '⚠️' : 'ℹ️'}
        </div>

        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '2px' }}>
            <span style={{ fontWeight: 800, fontSize: '13px', color: n.color }}>{typeLabel}</span>
            <span style={{ background: n.color + '22', color: n.color, fontSize: '9px', fontWeight: 700, padding: '1px 6px', borderRadius: '20px' }}>{n.label}</span>
          </div>
          <div style={{ fontSize: '11px', color: '#94a3b8' }}>
            📍 {alerte.camera?.nom ?? 'Caméra inconnue'}
            {alerte.camera?.localisation && ` — ${alerte.camera.localisation}`}
          </div>
          <div style={{ fontSize: '10px', color: '#64748b', marginTop: '2px' }}>
            🕐 {date} à {heure}
            {alerte.sms_envoye && ' · 📱 SMS envoyé'}
            {alerte.push_envoye && ' · 🔔 Push envoyé'}
          </div>
          {alerte.note_admin && (
            <div style={{ fontSize: '10px', color: '#4ade80', marginTop: '4px', fontStyle: 'italic' }}>
              ✅ {alerte.note_admin}
            </div>
          )}
        </div>

        {!alerte.traite ? (
          <button onClick={() => traiter(alerte.id)} style={{
            background: '#14532d', color: '#4ade80', border: 'none',
            borderRadius: '8px', padding: '8px 12px', fontSize: '11px',
            fontWeight: 700, cursor: 'pointer', flexShrink: 0,
          }}>✅ Traiter</button>
        ) : (
          <div style={{ color: '#4ade80', fontSize: '10px', fontWeight: 700, flexShrink: 0 }}>
            ✅ Traité
          </div>
        )}
      </div>
    );
  };

  return (
    <div style={{ padding: '24px', background: '#08090f', minHeight: '100vh' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
        <div>
          <h1 style={{ fontSize: '22px', fontWeight: 900, color: '#fff', display: 'flex', alignItems: 'center', gap: '10px' }}>
            <Shield size={22} color="#f59e0b" /> Surveillance Dahua
          </h1>
          <p style={{ fontSize: '12px', color: '#64748b' }}>
            Alertes temps réel · Refresh auto 30s
          </p>
        </div>
        <button onClick={loadData} style={{
          background: '#111318', border: '1px solid #1e293b', borderRadius: '8px',
          color: '#60a5fa', padding: '8px 14px', cursor: 'pointer',
          display: 'flex', alignItems: 'center', gap: '6px', fontSize: '11px',
        }}>
          <RefreshCw size={13} /> Actualiser
        </button>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: '10px', marginBottom: '24px' }}>
        <StatBox label="Non traitées"    value={stats.non_traitees}  color="#f87171" urgent />
        <StatBox label="Critiques 24h"   value={stats.critiques_24h} color="#fb923c" urgent />
        <StatBox label="Total 24h"       value={stats.total_24h}     color="#60a5fa" />
        <StatBox label="Caméras actives" value={stats.cameras_actives} color="#4ade80" />
      </div>

      <div style={{ display: 'flex', gap: '4px', marginBottom: '16px' }}>
        {[['alertes', '🔔 Alertes'], ['cameras', '📷 Caméras'], ['config', '⚙️ Config DVR']].map(([id, label]) => (
          <button key={id} onClick={() => setTab(id)} style={{
            background: tab === id ? '#1e3a5f' : '#111318',
            color: tab === id ? '#60a5fa' : '#64748b',
            border: `1px solid ${tab === id ? '#3b82f6' : '#1e293b'}`,
            borderRadius: '8px', padding: '8px 16px', fontSize: '11px',
            fontWeight: 700, cursor: 'pointer',
          }}>{label}</button>
        ))}
      </div>

      {tab === 'alertes' && (
        <div>
          <div style={{ display: 'flex', gap: '10px', marginBottom: '16px' }}>
            <select value={filtreNiveau} onChange={e => setFiltreNiveau(e.target.value)}
              style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '8px', color: '#e2e8f0', padding: '8px 12px', fontSize: '11px' }}>
              <option value="">Tous les niveaux</option>
              <option value="critical">🚨 Critiques</option>
              <option value="warning">⚠️ Alertes</option>
              <option value="info">ℹ️ Info</option>
            </select>
            <select value={filtreTraite} onChange={e => setFiltreTraite(e.target.value)}
              style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '8px', color: '#e2e8f0', padding: '8px 12px', fontSize: '11px' }}>
              <option value="false">Non traitées</option>
              <option value="true">Traitées</option>
              <option value="">Toutes</option>
            </select>
          </div>

          {loading ? (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px' }}>Chargement...</div>
          ) : alertes.length === 0 ? (
            <div style={{ background: '#0d2515', border: '1px solid #16a34a', borderRadius: '10px', padding: '24px', textAlign: 'center', color: '#4ade80' }}>
              ✅ Aucune alerte {filtreTraite === 'false' ? 'non traitée' : ''} — Système opérationnel
            </div>
          ) : (
            alertes.map(a => <AlerteCard key={a.id} alerte={a} />)
          )}
        </div>
      )}

      {tab === 'cameras' && (
        <div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '12px' }}>
            <button onClick={() => setShowAddCamera(true)} style={{
              background: 'linear-gradient(135deg,#3b82f6,#1d4ed8)', color: '#fff',
              border: 'none', borderRadius: '8px', padding: '10px 16px',
              fontSize: '12px', fontWeight: 700, cursor: 'pointer',
            }}>+ Ajouter une caméra</button>
          </div>

          {cameras.map(cam => (
            <div key={cam.id} style={{
              background: '#111318', border: '1px solid #1e293b',
              borderRadius: '10px', padding: '14px 16px', marginBottom: '8px',
              display: 'flex', alignItems: 'center', gap: '14px',
            }}>
              <Camera size={20} color="#60a5fa" />
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 700, fontSize: '13px', color: '#f1f5f9' }}>{cam.nom}</div>
                <div style={{ fontSize: '10px', color: '#64748b' }}>
                  Serial: {cam.serial_no} · Type: {cam.type}
                  {cam.localisation && ` · ${cam.localisation}`}
                </div>
                <div style={{ fontSize: '10px', color: '#475569' }}>
                  Horaires: {cam.heure_ouverture} – {cam.heure_fermeture}
                </div>
              </div>
              {cam.alertes_non_traitees > 0 && (
                <div style={{ background: '#450a0a', color: '#f87171', fontSize: '11px', fontWeight: 800, padding: '4px 10px', borderRadius: '20px' }}>
                  {cam.alertes_non_traitees} alerte(s)
                </div>
              )}
              <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: cam.actif ? '#4ade80' : '#f87171' }} />
            </div>
          ))}

          {cameras.length === 0 && (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px', fontSize: '12px' }}>
              Aucune caméra configurée. Cliquez sur "Ajouter une caméra".
            </div>
          )}
        </div>
      )}

      {tab === 'config' && (
        <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '12px', padding: '20px' }}>
          <h3 style={{ color: '#f59e0b', fontWeight: 800, marginBottom: '16px', fontSize: '14px' }}>
            ⚙️ Configuration DVR/NVR Dahua
          </h3>
          <div style={{ fontSize: '12px', color: '#94a3b8', lineHeight: '2' }}>
            <p style={{ marginBottom: '16px' }}>
              Pour recevoir les alertes Dahua dans EduGest, configurer le <strong style={{ color: '#60a5fa' }}>webhook HTTP</strong> sur votre DVR :
            </p>
            {[
              ['1', 'Accéder au DVR', 'Navigateur → http://[IP_DVR] (ex: http://192.168.1.64)'],
              ['2', 'Paramètres réseau', 'Menu → Paramètres → Réseau → Notification HTTP'],
              ['3', 'URL Webhook', `${window.location.origin}/api/v1/surveillance/webhook`],
              ['4', 'Méthode', 'POST · Format : JSON'],
              ['5', 'Événements', 'Cocher : Détection mouvement, Alarme, Intrusion, Perte vidéo'],
              ['6', 'Test', 'Cliquer "Tester" — vérifier qu\'une alerte apparaît dans EduGest'],
            ].map(([num, titre, detail]) => (
              <div key={num} style={{ display: 'flex', gap: '12px', marginBottom: '12px', alignItems: 'flex-start' }}>
                <div style={{ background: '#1e3a5f', color: '#60a5fa', width: '24px', height: '24px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '11px', fontWeight: 900, flexShrink: 0 }}>{num}</div>
                <div>
                  <div style={{ fontWeight: 700, color: '#e2e8f0', fontSize: '12px' }}>{titre}</div>
                  <div style={{ color: '#64748b', fontSize: '11px', fontFamily: num === '3' ? 'monospace' : 'inherit', background: num === '3' ? '#1e293b' : 'none', padding: num === '3' ? '4px 8px' : '0', borderRadius: '4px', marginTop: '2px' }}>{detail}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {showAddCamera && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.7)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}
          onClick={() => setShowAddCamera(false)}>
          <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '16px', padding: '24px', width: '500px', maxWidth: '90%' }}
            onClick={e => e.stopPropagation()}>
            <h3 style={{ color: '#fff', fontWeight: 800, marginBottom: '16px' }}>📷 Ajouter une caméra Dahua</h3>

            {[
              { label: 'Nom de la caméra *', key: 'nom', placeholder: 'Entrée principale' },
              { label: 'Numéro de série DVR *', key: 'serial_no', placeholder: 'DAH2026XXXXXX' },
              { label: 'IP locale du DVR', key: 'ip_locale', placeholder: '192.168.1.64' },
              { label: 'Localisation', key: 'localisation', placeholder: 'Bâtiment A - RDC' },
            ].map(({ label, key, placeholder }) => (
              <div key={key} style={{ marginBottom: '10px' }}>
                <label style={{ fontSize: '10px', color: '#64748b', display: 'block', marginBottom: '4px' }}>{label}</label>
                <input value={newCamera[key]} onChange={e => setNewCamera(c => ({ ...c, [key]: e.target.value }))}
                  placeholder={placeholder}
                  style={{ width: '100%', background: '#1e293b', border: '1px solid #334155', borderRadius: '8px', color: '#e2e8f0', padding: '9px 12px', fontSize: '12px' }} />
              </div>
            ))}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', marginBottom: '10px' }}>
              <div>
                <label style={{ fontSize: '10px', color: '#64748b', display: 'block', marginBottom: '4px' }}>Type</label>
                <select value={newCamera.type} onChange={e => setNewCamera(c => ({ ...c, type: e.target.value }))}
                  style={{ width: '100%', background: '#1e293b', border: '1px solid #334155', borderRadius: '8px', color: '#e2e8f0', padding: '9px 12px', fontSize: '12px' }}>
                  {['entree', 'couloir', 'classe', 'parking', 'cantine', 'bus', 'autre'].map(t => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
              </div>
              <div>
                <label style={{ fontSize: '10px', color: '#64748b', display: 'block', marginBottom: '4px' }}>Horaires normaux</label>
                <div style={{ display: 'flex', gap: '4px' }}>
                  <input type="time" value={newCamera.heure_ouverture} onChange={e => setNewCamera(c => ({ ...c, heure_ouverture: e.target.value }))}
                    style={{ flex: 1, background: '#1e293b', border: '1px solid #334155', borderRadius: '6px', color: '#e2e8f0', padding: '8px', fontSize: '11px' }} />
                  <input type="time" value={newCamera.heure_fermeture} onChange={e => setNewCamera(c => ({ ...c, heure_fermeture: e.target.value }))}
                    style={{ flex: 1, background: '#1e293b', border: '1px solid #334155', borderRadius: '6px', color: '#e2e8f0', padding: '8px', fontSize: '11px' }} />
                </div>
              </div>
            </div>

            <div style={{ display: 'flex', gap: '10px', marginTop: '16px' }}>
              <button onClick={() => setShowAddCamera(false)}
                style={{ flex: 1, background: '#1e293b', color: '#94a3b8', border: 'none', borderRadius: '8px', padding: '10px', cursor: 'pointer', fontWeight: 700 }}>
                Annuler
              </button>
              <button onClick={ajouterCamera} disabled={saving || !newCamera.nom || !newCamera.serial_no}
                style={{ flex: 2, background: 'linear-gradient(135deg,#3b82f6,#1d4ed8)', color: '#fff', border: 'none', borderRadius: '8px', padding: '10px', cursor: 'pointer', fontWeight: 700 }}>
                {saving ? 'Enregistrement...' : '✅ Enregistrer'}
              </button>
            </div>
          </div>
        </div>
      )}

      {webhookInfo && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.8)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1001 }}>
          <div style={{ background: '#0d2515', border: '1px solid #16a34a', borderRadius: '16px', padding: '24px', width: '500px', maxWidth: '90%' }}>
            <h3 style={{ color: '#4ade80', fontWeight: 800, marginBottom: '16px' }}>✅ Caméra enregistrée !</h3>
            <p style={{ color: '#94a3b8', fontSize: '12px', marginBottom: '16px' }}>
              Configurez maintenant le webhook sur votre DVR Dahua :
            </p>
            {webhookInfo.instructions && Object.entries(webhookInfo.instructions).map(([k, v]) => (
              <div key={k} style={{ marginBottom: '8px', fontSize: '11px' }}>
                <span style={{ color: '#4ade80', fontWeight: 700 }}>{k.replace('_', ' ').toUpperCase()} : </span>
                <span style={{ color: '#94a3b8', fontFamily: v.includes('http') ? 'monospace' : 'inherit', background: v.includes('http') ? '#1e293b' : 'none', padding: v.includes('http') ? '2px 6px' : '0', borderRadius: '4px' }}>{v}</span>
              </div>
            ))}
            <button onClick={() => setWebhookInfo(null)} style={{
              width: '100%', background: '#14532d', color: '#4ade80', border: 'none',
              borderRadius: '8px', padding: '10px', marginTop: '16px', cursor: 'pointer', fontWeight: 700,
            }}>Fermer</button>
          </div>
        </div>
      )}
    </div>
  );
}
