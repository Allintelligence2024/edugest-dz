import React, { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSearch } from '@hooks/useSearch';

const TYPE_CONFIG = {
  eleve:      { label: 'Élève',      color: 'var(--accent)',   route: '/eleves' },
  enseignant: { label: 'Enseignant', color: 'var(--green)',    route: '/enseignants' },
  matiere:    { label: 'Matière',    color: 'var(--teal)',     route: '/matieres' },
  groupe:     { label: 'Groupe',     color: 'var(--orange)',   route: '/groupes' },
  salle:      { label: 'Salle',      color: 'var(--accent2)',  route: '/salles' },
  parent:     { label: 'Parent',     color: 'var(--pink)',     route: '/eleves' },
};

function HighlightMatch({ text, query }) {
  if (!query || !text) return <>{text}</>;
  const idx = text.toLowerCase().indexOf(query.toLowerCase());
  if (idx === -1) return <>{text}</>;
  return (
    <>
      {text.slice(0, idx)}
      <span style={{ color: 'var(--accent)', fontWeight: 600 }}>{text.slice(idx, idx + query.length)}</span>
      {text.slice(idx + query.length)}
    </>
  );
}

export default function SearchModal({ isOpen, onClose }) {
  const { query, setQuery, results, total, isLoading } = useSearch(300);
  const navigate = useNavigate();
  const inputRef = useRef(null);

  useEffect(() => {
    if (isOpen && inputRef.current) {
      inputRef.current.focus();
    }
  }, [isOpen]);

  useEffect(() => {
    const handleKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        if (isOpen) onClose();
        else onClose(true);
      }
      if (e.key === 'Escape' && isOpen) onClose();
    };
    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const handleSelect = (item) => {
    const config = TYPE_CONFIG[item.type] || TYPE_CONFIG.eleve;
    navigate(config.route);
    onClose();
    setQuery('');
  };

  return (
    <div style={{
      position: 'fixed', inset: 0, zIndex: 100,
      display: 'flex', alignItems: 'flex-start', justifyContent: 'center',
      paddingTop: '15vh',
    }}>
      <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.5)' }} onClick={() => onClose()} />

      <div style={{
        position: 'relative', width: '100%', maxWidth: '32rem',
        background: 'var(--surface)', border: '1px solid var(--border)',
        borderRadius: '16px', boxShadow: 'var(--shadow)', overflow: 'hidden',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', padding: '12px 16px', gap: '10px', borderBottom: '1px solid var(--border)' }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
          </svg>
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={e => setQuery(e.target.value)}
            placeholder="Rechercher élève, enseignant, matière..."
            style={{
              flex: 1, background: 'transparent', border: 'none', outline: 'none',
              color: 'var(--text)', fontSize: '0.95rem',
            }}
          />
          {isLoading && (
            <div style={{
              width: '16px', height: '16px', border: '2px solid var(--border)',
              borderTopColor: 'var(--accent)', borderRadius: '50%',
              animation: 'spin 0.6s linear infinite',
            }} />
          )}
          <kbd style={{
            fontSize: '10px', padding: '2px 6px', borderRadius: '4px',
            background: 'var(--surface2)', border: '1px solid var(--border)',
            color: 'var(--muted)', fontFamily: 'monospace',
          }}>ESC</kbd>
        </div>

        <div style={{ maxHeight: '60vh', overflowY: 'auto' }}>
          {query.length < 2 ? (
            <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--muted)', fontSize: '0.875rem' }}>
              Tapez au moins 2 caractères pour rechercher
            </div>
          ) : results.length === 0 && !isLoading ? (
            <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--muted)', fontSize: '0.875rem' }}>
              Aucun résultat pour "{query}"
            </div>
          ) : (
            <>
              <div style={{ padding: '6px 12px', fontSize: '0.7rem', color: 'var(--muted)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                {total} résultat{total > 1 ? 's' : ''}
              </div>
              {results.map((item, idx) => {
                const config = TYPE_CONFIG[item.type] || TYPE_CONFIG.eleve;
                return (
                  <button
                    key={`${item.type}-${item.id}-${idx}`}
                    onClick={() => handleSelect(item)}
                    style={{
                      width: '100%', display: 'flex', alignItems: 'center', gap: '10px',
                      padding: '8px 12px', border: 'none', cursor: 'pointer', textAlign: 'left',
                      background: 'transparent', color: 'var(--text)', fontSize: '0.875rem',
                      transition: 'background 0.1s',
                    }}
                    onMouseEnter={e => e.currentTarget.style.background = 'var(--surface2)'}
                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                  >
                    <span style={{
                      fontSize: '0.65rem', padding: '2px 6px', borderRadius: '4px',
                      background: `${config.color}20`, color: config.color,
                      fontWeight: 600, flexShrink: 0, minWidth: '60px', textAlign: 'center',
                    }}>
                      {config.label}
                    </span>
                    <span style={{ flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {item.nom && <HighlightMatch text={`${item.prenom ? item.prenom + ' ' : ''}${item.nom}`} query={query} />}
                    </span>
                    {item.ref && (
                      <span style={{ fontSize: '0.75rem', color: 'var(--muted)' }}>{item.ref}</span>
                    )}
                  </button>
                );
              })}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
