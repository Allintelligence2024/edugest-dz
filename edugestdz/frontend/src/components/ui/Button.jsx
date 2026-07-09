const BASE = 'inline-flex items-center justify-center gap-1.5 font-bold rounded-lg transition-all duration-150 active:scale-[0.97] disabled:opacity-50 disabled:cursor-not-allowed';

const SIZES = { sm: 'text-[11px] px-3 py-1.5', md: 'text-xs px-[18px] py-[9px]', lg: 'text-sm px-6 py-3' };

const VARIANTS = {
  primary:   'bg-accent text-white hover:bg-accent-hover shadow-glow-blue',
  secondary: 'bg-surface2 text-text border border-border hover:bg-surface3',
  success:   'bg-green/10 text-green border border-green/25 hover:bg-green/20',
  danger:    'bg-red/10 text-red border border-red/25 hover:bg-red/20',
  ghost:     'bg-transparent text-muted hover:text-text hover:bg-surface2',
};

export default function Button({
  children, variant = 'primary', icon, loading, onClick,
  disabled, type = 'button', size = 'md', className = '',
}) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      onClick={onClick}
      className={`${BASE} ${SIZES[size]} ${VARIANTS[variant] || VARIANTS.primary} ${className}`}
    >
      {loading ? (
        <span className="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin" />
      ) : (
        icon && <span className="w-4 h-4 flex items-center justify-center">{icon}</span>
      )}
      {loading ? 'Chargement...' : children}
    </button>
  );
}
