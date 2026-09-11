import { useState } from 'react';
import { useModules } from '@context/ModulesContext';
import { useI18n } from '@context/I18nContext';

import { Circle, Info, Settings, Users } from 'lucide-react';

// categorie = clé API ; label = clé i18n (rendue via t(), repli brut
// pour une catégorie inconnue).
const CATEGORIES = {
  core:        { label: 'modules_cat_core',        color: '#2563EB' },
  pedagogie:   { label: 'modules_cat_pedagogie',   color: '#7C3AED' },
  finance:     { label: 'modules_cat_finance',     color: '#10B981' },
  gestion:     { label: 'modules_cat_gestion',     color: '#F59E0B' },
  rh:          { label: 'modules_cat_rh',          color: '#06B6D4' },
  vie_scolaire:{ label: 'modules_cat_vie_scolaire', color: '#EF4444' },
  securite:    { label: 'modules_cat_securite',    color: '#EF4444' },
  marketing:   { label: 'modules_cat_marketing',   color: '#7C3AED' },
};

// Plans tarifaires : noms propres, non traduits (comme CIB/Dahabia).
const PLANS = {
  starter:  { label: 'Starter',  color: '#64748B' },
  standard: { label: 'Standard', color: '#2563EB' },
  premium:  { label: 'Premium',  color: '#F59E0B' },
};

export default function ModulesPage() {
  const { t } = useI18n();
  const { modules, loading, isActive, activerModule, desactiverModule, recharger } = useModules();
  const [saving, setSaving]       = useState({});
  const [msg, setMsg]             = useState('');
  const [msgOk, setMsgOk] = useState(true);
  const [showRaison, setShowRaison] = useState(null);
  const [raison, setRaison]       = useState('');

  const toggle = async (moduleKey, actuel) => {
    if (!actuel) {
      setSaving(s => ({ ...s, [moduleKey]: true }));
      const res = await activerModule(moduleKey);
      setSaving(s => ({ ...s, [moduleKey]: false }));
      setMsg(res.success ? t('modules_active', { module: moduleKey }) : res.message); setMsgOk(res.success);
      setTimeout(() => setMsg(''), 3000);
    } else {
      setShowRaison(moduleKey);
    }
  };

  const confirmerDesactivation = async () => {
    if (!showRaison) return;
    setSaving(s => ({ ...s, [showRaison]: true }));
    const res = await desactiverModule(showRaison, raison);
    setSaving(s => ({ ...s, [showRaison]: false }));
    setShowRaison(null);
    setRaison('');
    setMsg(res.success ? t('modules_desactive_ok', { module: showRaison }) : res.message); setMsgOk(res.success);
    setTimeout(() => setMsg(''), 3000);
  };

  const parCategorie = modules.reduce((acc, m) => {
    if (!acc[m.categorie]) acc[m.categorie] = [];
    acc[m.categorie].push(m);
    return acc;
  }, {});

  return (
    <div style={{ padding: '24px', background: '#070B14', minHeight: '100vh' }}>
      <div style={{ marginBottom: '24px' }}>
        <h1 style={{ fontSize: '22px', fontWeight: 900, color: '#fff', marginBottom: '4px' }}>
          <Settings size={22} aria-hidden='true' />{t('modules_title')}
                  </h1>
        <p style={{ fontSize: '12px', color: '#64748B' }}>
          {t('modules_intro')}
        </p>
      </div>

      {msg && (
        <div style={{ background: msgOk ? '#0d2515' : '#450a0a', border: `1px solid ${msgOk ? '#16a34a' : '#b91c1c'}`, borderRadius: '9px', padding: '10px 16px', marginBottom: '16px', fontSize: '12px', color: msgOk ? '#4ade80' : '#f87171' }}>
          {msg}
        </div>
      )}

      <div style={{ background: '#1e3a5f22', border: '1px solid #2563eb44', borderRadius: '10px', padding: '12px 16px', marginBottom: '20px', fontSize: '11px', color: '#93C5FD', display: 'flex', gap: '10px', alignItems: 'center' }}>
        <span style={{ fontSize: '18px' }}><Info size={18} aria-hidden='true' /></span>
        <span>
          {t('modules_info_a')} <strong>{t('modules_info_b')}</strong> {t('modules_info_c')}<strong> {t('modules_info_d')}</strong>.
        </span>
      </div>

      {loading ? (
        <div style={{ textAlign: 'center', color: '#64748B', padding: '60px' }}>
          {t('modules_chargement')}
        </div>
      ) : (
        Object.entries(parCategorie).map(([categorie, mods]) => {
          const catInfo = CATEGORIES[categorie] || { label: categorie, color: '#64748B' };
          return (
            <div key={categorie} style={{ marginBottom: '24px' }}>
              <div style={{ fontSize: '11px', fontWeight: 800, color: catInfo.color, textTransform: 'uppercase', letterSpacing: '1.5px', marginBottom: '10px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <div style={{ width: '16px', height: '2px', background: catInfo.color, borderRadius: '99px' }} />
                {t(catInfo.label)}
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '10px' }}>
                {mods.map(module => {
                  const isSaving = saving[module.key];
                  const planInfo = PLANS[module.plan_minimum] || PLANS.starter;
                  return (
                    <div key={module.key} style={{
                      background: '#0D1117',
                      border: `1px solid ${module.actif ? '#1E2D40' : '#1E2D4088'}`,
                      borderRadius: '12px', padding: '16px',
                      opacity: module.obligatoire ? 1 : (module.actif ? 1 : 0.65),
                      transition: 'all 0.2s',
                    }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <span style={{ fontSize: '22px' }}>{module.icon}</span>
                          <div>
                            <div style={{ fontSize: '12px', fontWeight: 800, color: module.actif ? '#fff' : '#64748B' }}>
                              {module.label}
                            </div>
                            <span style={{ fontSize: '8px', fontWeight: 700, padding: '1px 6px', borderRadius: '20px', background: planInfo.color + '22', color: planInfo.color }}>
                              {planInfo.label}
                            </span>
                          </div>
                        </div>
                        {module.obligatoire ? (
                          <span style={{ fontSize: '9px', background: '#2563EB22', color: '#93C5FD', padding: '3px 8px', borderRadius: '20px', fontWeight: 700 }}>
                            {t('modules_obligatoire')}
                          </span>
                        ) : (
                          <button
                            onClick={() => !isSaving && toggle(module.key, module.actif)}
                            disabled={isSaving}
                            style={{
                              width: '44px', height: '24px', borderRadius: '99px',
                              background: isSaving ? '#1E2D40' : (module.actif ? '#10B981' : '#1E2D40'),
                              border: 'none', cursor: isSaving ? 'wait' : 'pointer',
                              position: 'relative', transition: 'background 0.2s', flexShrink: 0,
                            }}
                          >
                            <div style={{
                              position: 'absolute', width: '18px', height: '18px',
                              borderRadius: '50%', background: '#fff',
                              top: '3px', left: isSaving ? '13px' : (module.actif ? '23px' : '3px'),
                              transition: 'left 0.2s', boxShadow: '0 1px 3px rgba(0,0,0,0.4)',
                            }} />
                          </button>
                        )}
                      </div>
                      <p style={{ fontSize: '10px', color: '#64748B', lineHeight: '1.5', marginBottom: '6px' }}>
                        {module.description}
                      </p>
                      {module.pour_qui && (
                        <div style={{ fontSize: '9px', color: '#475569', fontStyle: 'italic' }}>
                          <Users size={9} aria-hidden='true' /> {module.pour_qui}
                        </div>
                      )}
                      {!module.actif && !module.obligatoire && (
                        <div style={{ marginTop: '8px', padding: '6px 8px', background: '#1E2D40', borderRadius: '6px', fontSize: '9px', color: '#64748B' }}>
                          <Circle size={9} fill="#f87171" color="#f87171" aria-hidden='true' />{t('modules_desactive_info')}
                                                  </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          );
        })
      )}

      {showRaison && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.7)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div style={{ background: '#111318', border: '1px solid #1E2D40', borderRadius: '14px', padding: '24px', width: '420px', maxWidth: '90%' }}>
            <h3 style={{ color: '#fff', fontWeight: 800, marginBottom: '8px' }}>
              {t('modules_confirm_desactivation', { module: showRaison })}
            </h3>
            <p style={{ fontSize: '12px', color: '#64748B', marginBottom: '16px', lineHeight: '1.6' }}>
              {t('modules_desc_1')} <strong style={{ color: '#E2E8F0' }}>{t('modules_desc_2')}</strong> {t('modules_desc_3')}
            </p>
            <label style={{ fontSize: '10px', color: '#64748B', display: 'block', marginBottom: '6px' }}>
              {t('modules_raison')}
            </label>
            <input
              value={raison}
              onChange={e => setRaison(e.target.value)}
              placeholder={t('modules_ph_raison')}
              style={{ width: '100%', background: '#1E293B', border: '1px solid #334155', borderRadius: '8px', color: '#E2E8F0', padding: '9px 12px', fontSize: '12px', marginBottom: '16px', fontFamily: 'Inter, sans-serif' }}
            />
            <div style={{ display: 'flex', gap: '10px' }}>
              <button onClick={() => { setShowRaison(null); setRaison(''); }}
                style={{ flex: 1, background: '#1E293B', border: '1px solid #1E2D40', color: '#94A3B8', borderRadius: '8px', padding: '10px', cursor: 'pointer', fontWeight: 700 }}>
                {t('cancel')}
              </button>
              <button onClick={confirmerDesactivation}
                style={{ flex: 2, background: '#EF4444', color: '#fff', border: 'none', borderRadius: '8px', padding: '10px', cursor: 'pointer', fontWeight: 700 }}>
                <Circle size={16} fill="#f87171" color="#f87171" aria-hidden='true' />{t('modules_desactiver_btn')}
                              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
