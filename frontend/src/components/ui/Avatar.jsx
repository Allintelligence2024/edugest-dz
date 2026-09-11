const GRADIENTS = [
  { from: '#2563EB', to: '#7C3AED' },
  { from: '#10B981', to: '#06B6D4' },
  { from: '#EC4899', to: '#F59E0B' },
  { from: '#7C3AED', to: '#EC4899' },
  { from: '#06B6D4', to: '#10B981' },
  { from: '#F59E0B', to: '#EF4444' },
  { from: '#2563EB', to: '#10B981' },
  { from: '#EF4444', to: '#7C3AED' },
];

function hashString(str) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash);
  }
  return Math.abs(hash);
}

export default function Avatar({ name = '', src, size = 32, shape = 'circle', className = '' }) {
  const initials = name
    .split(' ')
    .map(w => w[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase();

  const gradient = GRADIENTS[hashString(name || 'A') % GRADIENTS.length];
  const isCircle = shape === 'circle';

  if (src) {
    return (
      <img
        src={src}
        alt={name}
        className={className}
        style={{
          width: size,
          height: size,
          borderRadius: isCircle ? '50%' : '8px',
          objectFit: 'cover',
        }}
      />
    );
  }

  return (
    <div
      className={className}
      style={{
        width: size,
        height: size,
        borderRadius: isCircle ? '50%' : '8px',
        background: `linear-gradient(135deg, ${gradient.from}, ${gradient.to})`,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: size * 0.36,
        fontWeight: 800,
        color: '#fff',
        flexShrink: 0,
        userSelect: 'none',
      }}
    >
      {initials || '?'}
    </div>
  );
}
