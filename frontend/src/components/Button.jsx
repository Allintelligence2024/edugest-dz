export default function Button({
  children, variant = 'primary', icon, loading = false,
  onClick, disabled, type = 'button', size = 'md', style: extraStyle
}) {
  const sizes = { sm: '7px 12px', md: '9px 18px', lg: '12px 24px' };
  const fontSizes = { sm: '11px', md: '12px', lg: '14px' };

  const variants = {
    primary:   { background: 'linear-gradient(135deg,#2563EB,#1d4ed8)', color: '#fff', border: 'none' },
    secondary: { background: '#161C26', color: '#E2E8F0', border: '1px solid #1E2D40' },
    success:   { background: '#10B98122', color: '#10B981', border: '1px solid #10B98144' },
    danger:    { background: '#EF444422', color: '#EF4444', border: '1px solid #EF444444' },
    ghost:     { background: 'transparent', color: '#64748B', border: 'none' },
  };

  const v = variants[variant] || variants.primary;

  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled || loading}
      style={{
        ...v,
        padding: sizes[size],
        borderRadius: '9px',
        fontSize: fontSizes[size],
        fontWeight: 700,
        cursor: disabled || loading ? 'not-allowed' : 'pointer',
        opacity: disabled ? 0.5 : 1,
        display: 'inline-flex', alignItems: 'center', gap: '6px',
        transition: 'all 0.15s',
        fontFamily: 'Inter, sans-serif',
        ...extraStyle,
      }}
      onMouseEnter={e => !disabled && !loading && (e.currentTarget.style.transform = 'translateY(-1px)')}
      onMouseLeave={e => (e.currentTarget.style.transform = 'translateY(0)')}
    >
      {loading ? (
        <>
          <span style={{ width: '12px', height: '12px', border: '2px solid currentColor', borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 0.7s linear infinite', display: 'inline-block' }} />
          Chargement...
        </>
      ) : (
        <>
          {icon && <span>{icon}</span>}
          {children}
        </>
      )}
    </button>
  );
}
