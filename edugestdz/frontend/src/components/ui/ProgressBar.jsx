export default function ProgressBar({ value = 0, max = 100, color = '#10B981', height = 6, label, className = '' }) {
  const pct = Math.min(100, Math.max(0, (value / max) * 100));
  return (
    <div className={`flex items-center gap-3 ${className}`}>
      {label && <span className="text-[10px] font-bold text-muted shrink-0 w-12">{label}</span>}
      <div className="flex-1 rounded-full" style={{ height, background: '#1E2D40' }}>
        <div
          className="rounded-full h-full transition-all duration-500"
          style={{ width: `${pct}%`, background: color }}
        />
      </div>
      <span className="text-[10px] font-bold text-muted shrink-0 w-8 text-right">{Math.round(pct)}%</span>
    </div>
  );
}
