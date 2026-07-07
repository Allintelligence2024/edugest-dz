export default function PageHeader({ title, subtitle, actions, color = '#2563EB' }) {
  return (
    <div style={{
      display: 'flex', alignItems: 'center', justifyContent: 'space-between',
      marginBottom: '24px', paddingBottom: '20px',
      borderBottom: '1px solid #1E2D40',
    }}>
      <div>
        <h1 style={{
          fontSize: '20px', fontWeight: 900, color: '#fff',
          marginBottom: '4px', display: 'flex', alignItems: 'center', gap: '8px',
        }}>
          <span style={{
            display: 'inline-block', width: '4px', height: '20px',
            background: color, borderRadius: '2px',
          }} />
          {title}
        </h1>
        {subtitle && (
          <p style={{ fontSize: '12px', color: '#64748B' }}>{subtitle}</p>
        )}
      </div>
      {actions && (
        <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
          {actions}
        </div>
      )}
    </div>
  );
}
