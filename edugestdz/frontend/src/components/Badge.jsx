const TYPES = {
  success: { bg: '#10B98122', color: '#10B981', dot: '#10B981' },
  danger:  { bg: '#EF444422', color: '#EF4444', dot: '#EF4444' },
  warning: { bg: '#F59E0B22', color: '#F59E0B', dot: '#F59E0B' },
  info:    { bg: '#2563EB22', color: '#93C5FD', dot: '#2563EB' },
  muted:   { bg: '#64748B22', color: '#94A3B8', dot: '#64748B' },
  purple:  { bg: '#7C3AED22', color: '#C4B5FD', dot: '#7C3AED' },
};

export default function Badge({ type = 'info', color, bg, children, dot = true }) {
  const style = TYPES[type] || TYPES.info;
  const finalColor = color || style.color;
  const finalBg    = bg || style.bg;

  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: '4px',
      background: finalBg, color: finalColor,
      fontSize: '10px', fontWeight: 700,
      padding: '2px 9px', borderRadius: '20px',
    }}>
      {dot && (
        <span style={{
          width: '5px', height: '5px', borderRadius: '50%',
          background: color || style.dot, flexShrink: 0,
        }} />
      )}
      {children}
    </span>
  );
}
