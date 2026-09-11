export default function DonutChart({ value = 65, size = 100, stroke = 6, color = '#2563EB', bg = '#1E2D40', label, sub }) {
  const r = (size - stroke) / 2;
  const circ = 2 * Math.PI * r;
  const offset = circ - (value / 100) * circ;
  return (
    <div className="flex items-center gap-4">
      <div className="relative shrink-0" style={{ width: size, height: size }}>
        <svg width={size} height={size} className="transform -rotate-90">
          <circle cx={size / 2} cy={size / 2} r={r} stroke={bg} strokeWidth={stroke} fill="none" />
          <circle
            cx={size / 2} cy={size / 2} r={r} stroke={color} strokeWidth={stroke}
            fill="none" strokeLinecap="round"
            strokeDasharray={circ}
            strokeDashoffset={offset}
            className="transition-all duration-700"
          />
        </svg>
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="text-lg font-black text-text">{Math.round(value)}%</span>
        </div>
      </div>
      {(label || sub) && (
        <div>
          {label && <div className="text-xs font-bold text-text">{label}</div>}
          {sub && <div className="text-[10px] text-muted mt-0.5">{sub}</div>}
        </div>
      )}
    </div>
  );
}
