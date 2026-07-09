export default function KpiCard({ label, value, sub, icon: Icon, color = '#2563EB', trend, trendUp, loading }) {
  return (
    <div className="bg-surface border border-border rounded-xl p-5 flex items-center gap-4 transition-all duration-200 hover:shadow-eg hover:-translate-y-px">
      <div
        className="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
        style={{ background: color + '22' }}
      >
        <Icon size={22} color={color} />
      </div>
      <div className="min-w-0">
        <div className="text-[11px] font-semibold text-muted uppercase tracking-wider">{label}</div>
        <div className="text-2xl font-black text-text mt-0.5 truncate">
          {loading ? (
            <span className="inline-block w-16 h-5 rounded bg-surface3 animate-pulse" />
          ) : value}
        </div>
        <div className="flex items-center gap-2 mt-0.5">
          {sub && <div className="text-[10px] text-muted2">{sub}</div>}
          {trend !== undefined && (
            <div className={`flex items-center gap-0.5 text-[10px] font-bold ${trendUp ? 'text-green' : 'text-red'}`}>
              <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                <path d={trendUp ? "M4 0L8 6H0Z" : "M4 8L0 2H8Z"} fill="currentColor" />
              </svg>
              {Math.abs(trend)}%
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
