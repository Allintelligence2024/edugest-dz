export default function KpiCard({ label, value, sub, icon: Icon, color, variant = 'blue', trend, trendUp, loading }) {
  const VARIANT_COLORS = {
    blue:   'var(--accent)',
    green:  'var(--green)',
    red:    'var(--red)',
    yellow: 'var(--orange)',
    purple: 'var(--accent2)',
    orange: 'var(--orange)',
  };
  const c = color || VARIANT_COLORS[variant] || 'var(--accent)';

  return (
    <div
      style={{
        background: 'var(--surface)',
        border: '1px solid var(--border)',
        borderTop: `2px solid ${c}`,
        borderRadius: '0.75rem',
        padding: '1.25rem',
        display: 'flex',
        alignItems: 'center',
        gap: '1rem',
        transition: 'all 0.2s',
      }}
    >
      {Icon && (
        <div
          style={{
            width: '48px',
            height: '48px',
            borderRadius: '9px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            flexShrink: 0,
            background: `${c}22`,
          }}
        >
          <Icon size={22} color={c} />
        </div>
      )}
      <div style={{ minWidth: 0, flex: 1 }}>
        <div
          style={{
            fontSize: '11px',
            fontWeight: 600,
            color: 'var(--muted)',
            textTransform: 'uppercase',
            letterSpacing: '0.05em',
          }}
        >
          {label}
        </div>
        <div
          style={{
            fontSize: '1.5rem',
            fontWeight: 900,
            color: 'var(--text)',
            marginTop: '2px',
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            whiteSpace: 'nowrap',
          }}
        >
          {loading ? (
            <span
              style={{
                display: 'inline-block',
                width: '64px',
                height: '20px',
                borderRadius: '4px',
                background: 'var(--surface3)',
                animation: 'pulse 2s ease-in-out infinite',
              }}
            />
          ) : value}
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '2px' }}>
          {sub && <div style={{ fontSize: '10px', color: 'var(--muted2)' }}>{sub}</div>}
          {trend !== undefined && (
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '2px',
                fontSize: '10px',
                fontWeight: 700,
                color: trendUp ? 'var(--green)' : 'var(--red)',
              }}
            >
              <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                <path d={trendUp ? 'M4 0L8 6H0Z' : 'M4 8L0 2H8Z'} fill="currentColor" />
              </svg>
              {Math.abs(trend)}%
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
