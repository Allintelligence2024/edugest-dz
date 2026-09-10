import { useState, useEffect, useCallback } from 'react';
import {
  AlertTriangle,
  Bell,
  Camera,
  CheckCircle,
  Clock,
  Construction,
  EyeOff,
  Globe,
  Info,
  MapPin,
  OctagonX,
  RefreshCw,
  Save,
  Settings,
  Shield,
  Siren,
  Smartphone,
  User,
  Video,
  WifiOff,
  XCircle,
} from 'lucide-react';
import { useI18n } from '@context/I18nContext';
import { getAccessToken } from '../api/tokenStore';

const BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/api\/v1\/?$/, '');
const api = (path, opts) => fetch(`${BASE_URL}/api/v1${path}`, {
  headers: {
    Authorization: `Bearer ${getAccessToken()}`,
    'Content-Type': 'application/json',
    'X-Tenant-ID': localStorage.getItem('tenantId') ?? '',
  },
  ...opts,
}).then(r => r.json());

// label = clé i18n (rendue via t()), l'icône reste un composant.
const NIVEAUX = {
  critical: { color: '#f87171', bg: '#450a0a', border: '#b91c1c', label: 'surveillance_critical', icon: <Siren size={10} aria-hidden="true" /> },
  warning:  { color: '#fb923c', bg: '#1f1008', border: '#c2410c', label: 'surveillance_niveau_warning', icon: <AlertTriangle size={10} aria-hidden="true" /> },
  info:     { color: '#60a5fa', bg: '#0c1a30', border: '#1d4ed8', label: 'surveillance_niveau_info', icon: <Info size={10} aria-hidden="true" /> },
};

// type_alerte = énum API Dahua (jamais traduit dans les échanges) ;
// clé de libellé + repli sur la valeur brute à l'affichage.
const TYPES_ALERTE = {
  VideoMotion:        { key: 'surveillance_type_video_motion', Icon: Video },
  AlarmLocal:         { key: 'surveillance_type_alarm_local', Icon: Siren },
  CrossLineDetection: { key: 'surveillance_type_cross_line', Icon: Construction },
  IntrusionDetection: { key: 'surveillance_type_intrusion', Icon: OctagonX },
  FaceDetection:      { key: 'surveillance_type_face', Icon: User },
  VideoLoss:          { key: 'surveillance_type_video_loss', Icon: WifiOff },
  VideoBlind:         { key: 'surveillance_type_video_blind', Icon: EyeOff },
  DiskFull:           { key: 'surveillance_type_disk_full', Icon: Save },
  DiskError:          { key: 'surveillance_type_disk_error', Icon: XCircle },
  NetworkAbort:       { key: 'surveillance_type_network_abort', Icon: Globe },
};

// type caméra = énum API (valeur POST inchangée), clé pour l'affichage.
const CAMERA_TYPES = {
  entree:  'surveillance_camera_type_entree',
  couloir: 'surveillance_camera_type_couloir',
  classe:  'surveillance_camera_type_classe',
  parking: 'surveillance_camera_type_parking',
  cantine: 'surveillance_camera_type_cantine',
  bus:     'surveillance_camera_type_bus',
  autre:   'surveillance_camera_type_autre',
};

export default function SurveillancePage() {
  const { t } = useI18n();
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
    const note = prompt(t('surveillance_note_prompt'));
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
        alert(`${t('surveillance_erreur')} ${res.message ?? t('surveillance_erreur_enregistrement')}`);
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
    const meta = TYPES_ALERTE[alerte.type_alerte];
    const typeLabel = meta
      ? <><meta.Icon size={13} aria-hidden="true" /> {t(meta.key)}</>
      : alerte.type_alerte;
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
          {alerte.niveau === 'critical' ? <Siren size={18} aria-hidden='true' /> : alerte.niveau === 'warning' ? <AlertTriangle size={18} aria-hidden='true' /> : <Info size={18} aria-hidden='true' />}
        </div>

        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '2px' }}>
            <span style={{ fontWeight: 800, fontSize: '13px', color: n.color }}>{typeLabel}</span>
            <span style={{ background: n.color + '22', color: n.color, fontSize: '9px', fontWeight: 700, padding: '1px 6px', borderRadius: '20px' }}>{n.icon} {t(n.label)}</span>
          </div>
          <div style={{ fontSize: '11px', color: '#94a3b8' }}>
            <MapPin size={11} aria-hidden='true' /> {alerte.camera?.nom ?? t('surveillance_camera_inconnue')}
            {alerte.camera?.localisation && ` — ${alerte.camera.localisation}`}
          </div>
          <div style={{ fontSize: '10px', color: '#64748b', marginTop: '2px' }}>
            <Clock size={10} aria-hidden='true' /> {date} {t('surveillance_a')} {heure}
            {alerte.sms_envoye && <> · <Smartphone size={10} aria-hidden="true" /> {t('absences_sms_sent')}</>}
            {alerte.push_envoye && <> · <Bell size={10} aria-hidden="true" /> {t('surveillance_push_envoye')}</>}
          </div>
          {alerte.note_admin && (
            <div style={{ fontSize: '10px', color: '#4ade80', marginTop: '4px', fontStyle: 'italic' }}>
              <CheckCircle size={10} aria-hidden='true' /> {alerte.note_admin}
            </div>
          )}
        </div>

        {!alerte.traite ? (
          <button onClick={() => traiter(alerte.id)} style={{
            background: '#14532d', color: '#4ade80', border: 'none',
            borderRadius: '8px', padding: '8px 12px', fontSize: '11px',
            fontWeight: 700, cursor: 'pointer', flexShrink: 0,
          }}><CheckCircle size={11} aria-hidden='true' />{t('surveillance_treat')}</button>
        ) : (
          <div style={{ color: '#4ade80', fontSize: '10px', fontWeight: 700, flexShrink: 0 }}>
            <CheckCircle size={10} aria-hidden='true' />{t('surveillance_traite')}
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
            <Shield size={22} color="#f59e0b" /> {t('surveillance_title')}
          </h1>
          <p style={{ fontSize: '12px', color: '#64748b' }}>
            {t('surveillance_sous_titre')}
          </p>
        </div>
        <button onClick={loadData} style={{
          background: '#111318', border: '1px solid #1e293b', borderRadius: '8px',
          color: '#60a5fa', padding: '8px 14px', cursor: 'pointer',
          display: 'flex', alignItems: 'center', gap: '6px', fontSize: '11px',
        }}>
          <RefreshCw size={13} /> {t('surveillance_actualiser')}
        </button>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: '10px', marginBottom: '24px' }}>
        <StatBox label={t('surveillance_stat_non_traitees')}  value={stats.non_traitees}  color="#f87171" urgent />
        <StatBox label={t('surveillance_stat_critiques_24h')} value={stats.critiques_24h} color="#fb923c" urgent />
        <StatBox label={t('surveillance_stat_total_24h')}     value={stats.total_24h}     color="#60a5fa" />
        <StatBox label={t('surveillance_stat_cameras_actives')} value={stats.cameras_actives} color="#4ade80" />
      </div>

      <div style={{ display: 'flex', gap: '4px', marginBottom: '16px' }}>
        {[['alertes', <Bell size={12} aria-hidden="true" />, t('surveillance_alerts')], ['cameras', <Camera size={12} aria-hidden="true" />, t('surveillance_cameras')], ['config', <Settings size={12} aria-hidden="true" />, t('surveillance_config_dvr')]].map(([id, icon, label]) => (
          <button key={id} onClick={() => setTab(id)} style={{
            background: tab === id ? '#1e3a5f' : '#111318',
            color: tab === id ? '#60a5fa' : '#64748b',
            border: `1px solid ${tab === id ? '#3b82f6' : '#1e293b'}`,
            borderRadius: '8px', padding: '8px 16px', fontSize: '11px',
            fontWeight: 700, cursor: 'pointer',
          }}>{icon} {label}</button>
        ))}
      </div>

      {tab === 'alertes' && (
        <div>
          <div style={{ display: 'flex', gap: '10px', marginBottom: '16px' }}>
            <select value={filtreNiveau} onChange={e => setFiltreNiveau(e.target.value)}
              style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '8px', color: '#e2e8f0', padding: '8px 12px', fontSize: '11px' }}>
              <option value="">{t('surveillance_tous_niveaux')}</option>
              <option value="critical">{t('surveillance_filtre_critiques')}</option>
              <option value="warning">{t('surveillance_niveau_warning')}</option>
              <option value="info">{t('surveillance_niveau_info')}</option>
            </select>
            <select value={filtreTraite} onChange={e => setFiltreTraite(e.target.value)}
              style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '8px', color: '#e2e8f0', padding: '8px 12px', fontSize: '11px' }}>
              <option value="false">{t('surveillance_stat_non_traitees')}</option>
              <option value="true">{t('surveillance_filtre_traitees')}</option>
              <option value="">{t('surveillance_toutes')}</option>
            </select>
          </div>

          {loading ? (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px' }}>{t('loading')}</div>
          ) : alertes.length === 0 ? (
            <div style={{ background: '#0d2515', border: '1px solid #16a34a', borderRadius: '10px', padding: '24px', textAlign: 'center', color: '#4ade80' }}>
              <CheckCircle size={16} aria-hidden='true' />{t('surveillance_aucune_alerte')} {filtreTraite === 'false' ? t('surveillance_alerte_non_traitee') : ''}— {t('surveillance_all_ok')}
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
            }}>+ {t('surveillance_add_camera')}</button>
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
                  {t('surveillance_label_serial')}: {cam.serial_no} · {t('surveillance_label_type')}: {t(CAMERA_TYPES[cam.type] ?? cam.type)}
                  {cam.localisation && ` · ${cam.localisation}`}
                </div>
                <div style={{ fontSize: '10px', color: '#475569' }}>
                  {t('surveillance_horaires')}: {cam.heure_ouverture} – {cam.heure_fermeture}
                </div>
              </div>
              {cam.alertes_non_traitees > 0 && (
                <div style={{ background: '#450a0a', color: '#f87171', fontSize: '11px', fontWeight: 800, padding: '4px 10px', borderRadius: '20px' }}>
                  {t('surveillance_alertes_count', { count: cam.alertes_non_traitees })}
                </div>
              )}
              <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: cam.actif ? '#4ade80' : '#f87171' }} />
            </div>
          ))}

          {cameras.length === 0 && (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px', fontSize: '12px' }}>
              {t('surveillance_aucune_camera')}
            </div>
          )}
        </div>
      )}

      {tab === 'config' && (
        <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '12px', padding: '20px' }}>
          <h3 style={{ color: '#f59e0b', fontWeight: 800, marginBottom: '16px', fontSize: '14px' }}>
            <Settings size={14} aria-hidden='true' />{t('surveillance_config_titre')}
                      </h3>
          <div style={{ fontSize: '12px', color: '#94a3b8', lineHeight: '2' }}>
            <p style={{ marginBottom: '16px' }}>
              {t('surveillance_config_intro')}
            </p>
            {[
              ['1', t('surveillance_step1_titre'), t('surveillance_step1_detail')],
              ['2', t('surveillance_step2_titre'), t('surveillance_step2_detail')],
              ['3', t('surveillance_step3_titre'), `${window.location.origin}/api/v1/surveillance/webhook`],
              ['4', t('surveillance_step4_titre'), t('surveillance_step4_detail')],
              ['5', t('surveillance_step5_titre'), t('surveillance_step5_detail')],
              ['6', t('surveillance_step6_titre'), t('surveillance_step6_detail')],
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
            <h3 style={{ color: '#fff', fontWeight: 800, marginBottom: '16px' }}><Camera size={16} aria-hidden='true' />{t('surveillance_modal_titre')}</h3>

            {[
              { label: t('surveillance_champ_nom'), key: 'nom', placeholder: t('surveillance_ph_nom') },
              { label: t('surveillance_champ_serial'), key: 'serial_no', placeholder: 'DAH2026XXXXXX' },
              { label: t('surveillance_champ_ip'), key: 'ip_locale', placeholder: '192.168.1.64' },
              { label: t('surveillance_champ_localisation'), key: 'localisation', placeholder: t('surveillance_ph_localisation') },
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
                <label style={{ fontSize: '10px', color: '#64748b', display: 'block', marginBottom: '4px' }}>{t('surveillance_label_type')}</label>
                <select value={newCamera.type} onChange={e => setNewCamera(c => ({ ...c, type: e.target.value }))}
                  style={{ width: '100%', background: '#1e293b', border: '1px solid #334155', borderRadius: '8px', color: '#e2e8f0', padding: '9px 12px', fontSize: '12px' }}>
                  {['entree', 'couloir', 'classe', 'parking', 'cantine', 'bus', 'autre'].map(ty => (
                    <option key={ty} value={ty}>{t(CAMERA_TYPES[ty] ?? ty)}</option>
                  ))}
                </select>
              </div>
              <div>
                <label style={{ fontSize: '10px', color: '#64748b', display: 'block', marginBottom: '4px' }}>{t('surveillance_champ_horaires')}</label>
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
                {t('cancel')}
              </button>
              <button onClick={ajouterCamera} disabled={saving || !newCamera.nom || !newCamera.serial_no}
                style={{ flex: 2, background: 'linear-gradient(135deg,#3b82f6,#1d4ed8)', color: '#fff', border: 'none', borderRadius: '8px', padding: '10px', cursor: 'pointer', fontWeight: 700 }}>
                {saving ? t('surveillance_enregistrement_en_cours') : <><CheckCircle size={16} aria-hidden='true' />{t('save')}</>}
              </button>
            </div>
          </div>
        </div>
      )}

      {webhookInfo && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.8)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1001 }}>
          <div style={{ background: '#0d2515', border: '1px solid #16a34a', borderRadius: '16px', padding: '24px', width: '500px', maxWidth: '90%' }}>
            <h3 style={{ color: '#4ade80', fontWeight: 800, marginBottom: '16px' }}><CheckCircle size={16} aria-hidden='true' />{t('surveillance_camera_enregistree')}</h3>
            <p style={{ color: '#94a3b8', fontSize: '12px', marginBottom: '16px' }}>
              {t('surveillance_webhook_instructions')}
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
            }}>{t('close')}</button>
          </div>
        </div>
      )}
    </div>
  );
}
