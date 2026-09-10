import { useState } from 'react';
import { useModules } from '@context/ModulesContext';

const CATEGORIES = {
  core:        { label: 'Fonctions de base',    color: '#2563EB' },
  pedagogie:   { label: 'Pédagogie',            color: '#7C3AED' },
  finance:     { label: 'Finance',              color: '#10B981' },
  gestion:     { label: 'Gestion Centre',       color: '#F59E0B' },
  rh:          { label: 'Ressources Humaines',  color: '#06B6D4' },
  vie_scolaire:{ label: 'Vie Scolaire',         color: '#EF4444' },
  securite:    { label: 'Sécurité',             color: '#EF4444' },
  marketing:   { label: 'Marketing',            color: '#7C3AED' },
};

const PLANS = {
  starter:  { label: 'Starter',  color: '#64748B' },
  standard: { label: 'Standard', color: '#2563EB' },
  premium:  { label: 'Premium',  color: '#F59E0B' },
};

export default function ModulesPage() {
  const { modules, loading, isActive, activerModule, desactiverModule, recharger } = useModules();
  const [saving, setSaving]       = useState({});
  const [msg, setMsg]             = useState('');
  const [showRaison, setShowRaison] = useState(null);
  const [raison, setRaison]       = useState('');

  const toggle = async (moduleKey, actuel) => {
    if (!actuel) {
      setSaving(s => ({ ...s, [moduleKey]: true }));
      const res = await activerModule(moduleKey);
      setSaving(s => ({ ...s, [moduleKey]: false }));
      setMsg(res.success ? `✅ ${moduleKey} activé` : `❌ ${res.message}`);
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
    setMsg(res.success ? `✅ ${showRaison} désactivé` : `❌ ${res.message}`);
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
          ⚙️ Gestion des Modules
        </h1>
        <p style={{ fontSize: '12px', color: '#64748B' }}>
          Activez ou désactivez les modules selon les besoins de votre établissement.
          Les modules désactivés n'apparaissent plus dans la navigation.
        </p>
      </div>

      {msg && (
        <div style={{ background: msg.includes('✅') ? '#0d2515' : '#450a0a', border: `1px solid ${msg.includes('✅') ? '#16a34a' : '#b91c1c'}`, borderRadius: '9px', padding: '10px 16px', marginBottom: '16px', fontSize: '12px', color: msg.includes('✅') ? '#4ade80' : '#f87171' }}>
          {msg}
        </div>
      )}

      <div style={{ background: '#1e3a5f22', border: '1px solid #2563eb44', borderRadius: '10px', padding: '12px 16px', marginBottom: '20px', fontSize: '11px', color: '#93C5FD', display: 'flex', gap: '10px', alignItems: 'center' }}>
        <span style={{ fontSize: '18px' }}>ℹ️</span>
        <span>
          Les modules <strong>obligatoires</strong> (Dashboard, Élèves, Planning, Notes, Bulletins, Factures)
          ne peuvent pas être désactivés. Tous les autres peuvent être activés/désactivés à tout moment
          <strong> sans perte de données</strong>.
        </span>
      </div>

      {loading ? (
        <div style={{ textAlign: 'center', color: '#64748B', padding: '60px' }}>
          Chargement des modules...
        </div>
      ) : (
        Object.entries(parCategorie).map(([categorie, mods]) => {
          const catInfo = CATEGORIES[categorie] || { label: categorie, color: '#64748B' };
          return (
            <div key={categorie} style={{ marginBottom: '24px' }}>
              <div style={{ fontSize: '11px', fontWeight: 800, color: catInfo.color, textTransform: 'uppercase', letterSpacing: '1.5px', marginBottom: '10px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <div style={{ width: '16px', height: '2px', background: catInfo.color, borderRadius: '99px' }} />
                {catInfo.label}
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
                            Obligatoire
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
                          👥 {module.pour_qui}
                        </div>
                      )}
                      {!module.actif && !module.obligatoire && (
                        <div style={{ marginTop: '8px', padding: '6px 8px', background: '#1E2D40', borderRadius: '6px', fontSize: '9px', color: '#64748B' }}>
                          🔴 Module désactivé — cliquer pour réactiver
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
              Désactiver « {showRaison} » ?
            </h3>
            <p style={{ fontSize: '12px', color: '#64748B', marginBottom: '16px', lineHeight: '1.6' }}>
              Ce module sera caché de la navigation. <strong style={{ color: '#E2E8F0' }}>Vos données ne seront pas supprimées</strong> et vous pourrez réactiver le module à tout moment.
            </p>
            <label style={{ fontSize: '10px', color: '#64748B', display: 'block', marginBottom: '6px' }}>
              Raison (optionnelle)
            </label>
            <input
              value={raison}
              onChange={e => setRaison(e.target.value)}
              placeholder="ex: Nous n'avons pas de service de cantine"
              style={{ width: '100%', background: '#1E293B', border: '1px solid #334155', borderRadius: '8px', color: '#E2E8F0', padding: '9px 12px', fontSize: '12px', marginBottom: '16px', fontFamily: 'Inter, sans-serif' }}
            />
            <div style={{ display: 'flex', gap: '10px' }}>
              <button onClick={() => { setShowRaison(null); setRaison(''); }}
                style={{ flex: 1, background: '#1E293B', border: '1px solid #1E2D40', color: '#94A3B8', borderRadius: '8px', padding: '10px', cursor: 'pointer', fontWeight: 700 }}>
                Annuler
              </button>
              <button onClick={confirmerDesactivation}
                style={{ flex: 2, background: '#EF4444', color: '#fff', border: 'none', borderRadius: '8px', padding: '10px', cursor: 'pointer', fontWeight: 700 }}>
                🔴 Désactiver le module
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
