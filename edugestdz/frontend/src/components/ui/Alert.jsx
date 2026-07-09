const STYLES = {
  info:    { bg: '#2563EB15', border: '#2563EB44', color: '#60A5FA', icon: 'i' },
  success: { bg: '#10B98115', border: '#10B98144', color: '#10B981', icon: '\u2713' },
  warning: { bg: '#F59E0B15', border: '#F59E0B44', color: '#F59E0B', icon: '!' },
  error:   { bg: '#EF444415', border: '#EF444444', color: '#EF4444', icon: '\u2717' },
};

export default function Alert({ type = 'info', children, className = '', onDismiss }) {
  const s = STYLES[type] || STYLES.info;
  return (
    <div
      className={`flex items-start gap-3 px-4 py-3 rounded-xl text-xs font-medium ${className}`}
      style={{ background: s.bg, border: `1px solid ${s.border}`, color: s.color }}
    >
      <span className="w-5 h-5 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 mt-0.5"
        style={{ background: s.color + '33', color: s.color }}
      >
        {s.icon}
      </span>
      <div className="flex-1 text-text2">{children}</div>
      {onDismiss && (
        <button onClick={onDismiss} className="text-muted hover:text-text transition-colors text-sm leading-none">&times;</button>
      )}
    </div>
  );
}
