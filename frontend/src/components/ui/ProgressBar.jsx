export default function ProgressBar({ value = 0, max = 100, color, variant = 'green', height = 6, label, className = '' }) {
  const VARIANT_COLORS = {
    green:  'var(--green)',
    blue:   'var(--accent)',
    red:    'var(--red)',
    yellow: 'var(--orange)',
    purple: 'var(--accent2)',
  };
  const c = color || VARIANT_COLORS[variant] || 'var(--green)';
  const pct = Math.min(100, Math.max(0, (value / max) * 100));

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }} className={className}>
      {label && (
        <span style={{ fontSize: '10px', fontWeight: 700, color: 'var(--muted)', flexShrink: 0, width: '48px' }}>
          {label}
        </span>
      )}
      <div style={{ flex: 1, borderRadius: '999px', height, background: 'var(--border)' }}>
        <div
          style={{
            borderRadius: '999px',
            height: '100%',
            transition: 'width 0.5s ease',
            width: `${pct}%`,
            background: c,
          }}
        />
      </div>
      <span style={{ fontSize: '10px', fontWeight: 700, color: 'var(--muted)', flexShrink: 0, width: '32px', textAlign: 'right' }}>
        {Math.round(pct)}%
      </span>
    </div>
  );
}
