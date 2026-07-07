export default function StatCard({ icon, label, value, color = '#2563EB', sub, trend, onClick, loading = false }) {
  return (
    <div
      onClick={onClick}
      className="card-hover"
      style={{
        background: '#0D1117',
        border: `1px solid #1E2D40`,
        borderTop: `2px solid ${color}`,
        borderRadius: '14px',
        padding: '18px 20px',
        cursor: onClick ? 'pointer' : 'default',
        position: 'relative',
        overflow: 'hidden',
      }}
    >
      <div style={{
        position: 'absolute', top: 0, right: 0,
        width: '80px', height: '80px', borderRadius: '50%',
        background: color,
        opacity: 0.04,
        transform: 'translate(20px, -20px)',
      }} />

      <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: '12px' }}>
        <div style={{ fontSize: '10px', fontWeight: 700, color: '#64748B', textTransform: 'uppercase', letterSpacing: '1px' }}>
          {label}
        </div>
        <div style={{
          width: '34px', height: '34px', borderRadius: '9px',
          background: `${color}22`,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          fontSize: '16px',
        }}>
          {icon}
        </div>
      </div>

      <div style={{ fontSize: '26px', fontWeight: 900, color: '#fff', marginBottom: '6px' }}>
        {loading ? (
          <div style={{ width: '80px', height: '28px', background: '#1E2D40', borderRadius: '6px', animation: 'pulse 1.5s infinite' }} />
        ) : value}
      </div>

      {(sub || trend) && (
        <div style={{ fontSize: '11px', display: 'flex', alignItems: 'center', gap: '6px' }}>
          {trend && (
            <span style={{ color: trend.startsWith('+') ? '#10B981' : '#EF4444', fontWeight: 700 }}>
              {trend}
            </span>
          )}
          {sub && <span style={{ color: '#64748B' }}>{sub}</span>}
        </div>
      )}
    </div>
  );
}
