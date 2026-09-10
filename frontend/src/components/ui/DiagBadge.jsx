import { AlertTriangle, Star } from 'lucide-react';
import { useI18n } from '@context/I18nContext';

export default function DiagBadge({ niveau, score, size = 'default' }) {
  const { t } = useI18n();
  // Labels = clés i18n (diag_risk…, plus diagnostic_excellent/critical
  // partagés avec la page Diagnostic).
  const BADGES = {
    risque:        { label: 'diag_risk',          bg: 'var(--red)',     color: 'var(--red-light)',     icon: '●' },
    moyen:         { label: 'diag_medium',        bg: 'var(--orange)',  color: 'var(--orange-light)',  icon: '●' },
    bon:           { label: 'diag_good',          bg: 'var(--green)',   color: 'var(--green-light)',   icon: '●' },
    excellent:     { label: 'diagnostic_excellent', bg: 'var(--accent)',  color: 'var(--accent-light)', icon: <Star size={10} fill="currentColor" aria-hidden="true" /> },
    alerte:        { label: 'diag_alert',         bg: 'var(--red)',     color: 'var(--red-light)',     icon: <AlertTriangle size={10} aria-hidden="true" /> },
    critique:      { label: 'diagnostic_critical', bg: 'var(--red)',    color: 'var(--red-light)',     icon: '●' },
    'non_evalue':  { label: 'diag_not_evaluated', bg: 'var(--muted2)',  color: 'var(--muted)',         icon: '—' },
  };

  const b = BADGES[niveau] || BADGES['non_evalue'];
  const isCompact = size === 'compact';

  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: isCompact ? '3px' : '5px',
        padding: isCompact ? '3px 8px' : '4px 10px',
        borderRadius: '999px',
        fontSize: isCompact ? '9px' : '10px',
        fontWeight: 700,
        background: `${b.bg}22`,
        color: b.color,
        whiteSpace: 'nowrap',
      }}
    >
      <span style={{ fontSize: isCompact ? '8px' : '10px' }}>{b.icon}</span>
      {t(b.label)}
      {score !== undefined && (
        <span style={{ fontWeight: 800 }}>{score}</span>
      )}
    </span>
  );
}
