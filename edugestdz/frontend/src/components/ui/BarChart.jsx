export default function BarChart({ data = [], height = 160, color = '#2563EB', className = '' }) {
  const max = Math.max(...data.map(d => d.value), 1);
  return (
    <div className={`flex items-end gap-2 ${className}`} style={{ height }}>
      {data.map((d, i) => (
        <div key={i} className="flex-1 flex flex-col items-center gap-1 h-full justify-end">
          <span className="text-[9px] font-bold text-muted">{d.value}</span>
          <div
            className="w-full rounded-t-md transition-all duration-500 hover:opacity-80"
            style={{
              height: `${(d.value / max) * 100}%`,
              background: d.color || color,
              minHeight: d.value > 0 ? 4 : 0,
            }}
          />
          <span className="text-[8px] text-muted2 font-semibold">{d.label}</span>
        </div>
      ))}
    </div>
  );
}
