import { useNavigate } from 'react-router-dom';

const ACTIONS = [
  { label: 'Ajouter un élève',     path: '/eleves',       color: 'var(--accent)',  icon: 'M12 5v14m7-7H5' },
  { label: 'Créer une facture',    path: '/factures',     color: 'var(--green)',   icon: 'M12 5v14m7-7H5' },
  { label: 'Gérer le planning',    path: '/planning',     color: 'var(--accent2)', icon: 'M8 2V6M16 2V6M3 10H21M5 4H19C20.1 4 21 4.9 21 6V20C21 21.1 20.1 22 19 22H5C3.9 22 3 21.1 3 20V6C3 4.9 3.9 4 5 4Z' },
  { label: 'Saisir les présences', path: '/presences',    color: 'var(--orange)',  icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
];

export default function QuickActions({ items }) {
  const navigate = useNavigate();
  const actions = items || ACTIONS;

  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '8px' }}>
      {actions.map((a) => (
        <button
          key={a.path || a.label}
          onClick={() => a.path && navigate(a.path)}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '10px',
            padding: '12px',
            borderRadius: '12px',
            border: '1px solid var(--border)',
            background: 'var(--surface2)',
            color: 'var(--text)',
            cursor: 'pointer',
            textAlign: 'left',
            transition: 'all 0.15s',
          }}
        >
          <div
            style={{
              width: '36px',
              height: '36px',
              borderRadius: '10px',
              background: `${a.color}18`,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
            }}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={a.color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d={a.icon} />
            </svg>
          </div>
          <span style={{ fontSize: '11px', fontWeight: 600, lineHeight: '1.3' }}>{a.label}</span>
        </button>
      ))}
    </div>
  );
}
