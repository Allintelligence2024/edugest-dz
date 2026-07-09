const STYLES = {
  success: { bg: '#10B98122', color: '#10B981', dot: '#10B981' },
  danger:  { bg: '#EF444422', color: '#EF4444', dot: '#EF4444' },
  warning: { bg: '#F59E0B22', color: '#F59E0B', dot: '#F59E0B' },
  info:    { bg: '#2563EB22', color: '#93C5FD', dot: '#2563EB' },
  muted:   { bg: '#64748B22', color: '#94A3B8', dot: '#64748B' },
  purple:  { bg: '#7C3AED22', color: '#C4B5FD', dot: '#7C3AED' },
};

export default function Badge({ type = 'info', color, bg, children, dot = true, className = '' }) {
  const s = STYLES[type] || STYLES.info;
  return (
    <span
      className={`inline-flex items-center gap-1 text-[10px] font-bold px-[9px] py-[2px] rounded-full ${className}`}
      style={{ background: bg || s.bg, color: color || s.color }}
    >
      {dot && (
        <span
          className="w-[5px] h-[5px] rounded-full shrink-0 inline-block"
          style={{ background: color || s.dot }}
        />
      )}
      {children}
    </span>
  );
}
