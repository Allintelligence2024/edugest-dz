export default function FilterChips({ filters = [], active = {}, onToggle, onClear }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '6px', flexWrap: 'wrap' }}>
      {filters.map((f) => {
        const isActive = !!active[f.key];
        return (
          <button
            key={f.key}
            onClick={() => onToggle?.(f.key)}
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '4px',
              padding: '5px 12px',
              fontSize: '11px',
              fontWeight: 600,
              borderRadius: '999px',
              border: `1px solid ${isActive ? 'var(--accent)' : 'var(--border)'}`,
              background: isActive ? 'rgba(37,99,235,0.15)' : 'var(--surface2)',
              color: isActive ? 'var(--accent-light)' : 'var(--muted)',
              cursor: 'pointer',
              transition: 'all 0.15s',
              whiteSpace: 'nowrap',
            }}
          >
            {f.icon && <span style={{ fontSize: '12px' }}>{f.icon}</span>}
            {f.label}
          </button>
        );
      })}
      {Object.keys(active).length > 0 && onClear && (
        <button
          onClick={onClear}
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            padding: '5px 10px',
            fontSize: '11px',
            fontWeight: 600,
            borderRadius: '999px',
            border: '1px solid var(--border)',
            background: 'var(--surface)',
            color: 'var(--muted2)',
            cursor: 'pointer',
          }}
        >
          Effacer
        </button>
      )}
    </div>
  );
}
