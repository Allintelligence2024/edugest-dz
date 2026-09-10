export default function DiagBadge({ niveau, score, size = 'default' }) {
  const BADGES = {
    risque:        { label: 'À risque',        bg: 'var(--red)',     color: 'var(--red-light)',     icon: '●' },
    moyen:         { label: 'Moyen',           bg: 'var(--orange)',  color: 'var(--orange-light)',  icon: '●' },
    bon:           { label: 'Bon',             bg: 'var(--green)',   color: 'var(--green-light)',   icon: '●' },
    excellent:     { label: 'Excellent',       bg: 'var(--accent)',  color: 'var(--accent-light)',  icon: '★' },
    alerte:        { label: 'Alerte',          bg: 'var(--red)',     color: 'var(--red-light)',     icon: '⚠' },
    critique:      { label: 'Critique',        bg: 'var(--red)',     color: 'var(--red-light)',     icon: '●' },
    'non_evalue':  { label: 'Non évalué',     bg: 'var(--muted2)',  color: 'var(--muted)',         icon: '—' },
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
      {b.label}
      {score !== undefined && (
        <span style={{ fontWeight: 800 }}>{score}</span>
      )}
    </span>
  );
}
