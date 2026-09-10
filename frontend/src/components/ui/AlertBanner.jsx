export default function AlertBanner({ type = 'info', title, message, actions, onDismiss }) {
  const STYLES = {
    info:    { bg: 'linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(124,58,237,0.12) 100%)', border: 'var(--accent)', color: 'var(--accent-light)', iconColor: '#60A5FA' },
    success: { bg: 'linear-gradient(135deg, rgba(16,185,129,0.12) 0%, rgba(6,182,212,0.12) 100%)', border: 'var(--green)', color: 'var(--green-light)', iconColor: '#10B981' },
    warning: { bg: 'linear-gradient(135deg, rgba(245,158,11,0.12) 0%, rgba(236,72,153,0.12) 100%)', border: 'var(--orange)', color: 'var(--orange-light)', iconColor: '#F59E0B' },
    danger:  { bg: 'linear-gradient(135deg, rgba(239,68,68,0.12) 0%, rgba(236,72,153,0.12) 100%)', border: 'var(--red)', color: 'var(--red-light)', iconColor: '#EF4444' },
    critical:{ bg: 'linear-gradient(135deg, rgba(239,68,68,0.15) 0%, rgba(124,58,237,0.15) 100%)', border: 'var(--red)', color: 'var(--red-light)', iconColor: '#EF4444' },
  };
  const s = STYLES[type] || STYLES.info;

  return (
    <div
      style={{
        background: s.bg,
        border: `1px solid ${s.border}33`,
        borderRadius: '0.75rem',
        padding: '16px',
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
      }}
    >
      <div style={{ width: '32px', height: '32px', borderRadius: '8px', background: `${s.iconColor}22`, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={s.iconColor} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div style={{ flex: 1, minWidth: 0 }}>
        {title && <div style={{ fontSize: '12px', fontWeight: 700, color: 'var(--text)', marginBottom: '2px' }}>{title}</div>}
        {message && <div style={{ fontSize: '11px', color: 'var(--text2)' }}>{message}</div>}
      </div>
      {actions && (
        <div style={{ display: 'flex', gap: '8px', flexShrink: 0 }}>
          {actions.map((a, i) => (
            <button
              key={i}
              onClick={a.onClick}
              style={{
                fontSize: '11px',
                fontWeight: 600,
                padding: '6px 12px',
                borderRadius: '8px',
                border: a.primary ? 'none' : '1px solid var(--border)',
                background: a.primary ? 'var(--accent)' : 'var(--surface2)',
                color: a.primary ? '#fff' : 'var(--text)',
                cursor: 'pointer',
              }}
            >
              {a.label}
            </button>
          ))}
        </div>
      )}
      {onDismiss && (
        <button
          onClick={onDismiss}
          style={{ background: 'none', border: 'none', color: 'var(--muted2)', cursor: 'pointer', fontSize: '14px', padding: '4px', flexShrink: 0 }}
        >
          &times;
        </button>
      )}
    </div>
  );
}
