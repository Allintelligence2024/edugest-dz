import { useState } from 'react';

export default function DataTable({ columns = [], data = [], loading = false, emptyMessage = 'Aucune donnée' }) {
  const [sortKey, setSortKey]   = useState(null);
  const [sortDir, setSortDir]   = useState('asc');
  const [page, setPage]         = useState(1);
  const perPage = 10;

  const sorted = [...data].sort((a, b) => {
    if (!sortKey) return 0;
    const va = a[sortKey] ?? '';
    const vb = b[sortKey] ?? '';
    return sortDir === 'asc'
      ? String(va).localeCompare(String(vb))
      : String(vb).localeCompare(String(va));
  });

  const totalPages = Math.ceil(sorted.length / perPage);
  const paged = sorted.slice((page - 1) * perPage, page * perPage);

  const handleSort = (key) => {
    if (sortKey === key) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    else { setSortKey(key); setSortDir('asc'); }
  };

  return (
    <div style={{ background: '#0D1117', border: '1px solid #1E2D40', borderRadius: '14px', overflow: 'hidden' }}>
      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr style={{ background: '#161C26' }}>
            {columns.map(col => (
              <th key={col.key}
                onClick={() => col.sortable && handleSort(col.key)}
                style={{
                  padding: '11px 16px', textAlign: 'left',
                  fontSize: '10px', fontWeight: 700, color: '#64748B',
                  textTransform: 'uppercase', letterSpacing: '1px',
                  borderBottom: '1px solid #1E2D40',
                  cursor: col.sortable ? 'pointer' : 'default',
                  userSelect: 'none', whiteSpace: 'nowrap',
                }}
              >
                {col.label}
                {col.sortable && sortKey === col.key && (
                  <span style={{ marginLeft: '4px' }}>{sortDir === 'asc' ? '↑' : '↓'}</span>
                )}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {loading ? (
            Array.from({ length: 5 }).map((_, i) => (
              <tr key={i}>
                {columns.map((col, j) => (
                  <td key={j} style={{ padding: '12px 16px', borderBottom: '1px solid #1E2D4044' }}>
                    <div style={{ height: '14px', background: '#1E2D40', borderRadius: '4px', width: j === 0 ? '60%' : '40%', animation: 'pulse 1.5s infinite' }} />
                  </td>
                ))}
              </tr>
            ))
          ) : paged.length === 0 ? (
            <tr>
              <td colSpan={columns.length} style={{ padding: '40px', textAlign: 'center', color: '#64748B', fontSize: '13px' }}>
                {emptyMessage}
              </td>
            </tr>
          ) : (
            paged.map((row, i) => (
              <tr key={row.id || i} style={{ transition: 'background .1s' }}
                onMouseEnter={e => e.currentTarget.style.background = '#161C2688'}
                onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
              >
                {columns.map(col => (
                  <td key={col.key} style={{
                    padding: '12px 16px', fontSize: '12px', color: '#E2E8F0',
                    borderBottom: '1px solid #1E2D4044', verticalAlign: 'middle',
                  }}>
                    {col.render ? col.render(row[col.key], row) : row[col.key]}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>

      {totalPages > 1 && (
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          padding: '12px 16px', borderTop: '1px solid #1E2D40',
          fontSize: '11px', color: '#64748B',
        }}>
          <span>Affichage {(page-1)*perPage+1}-{Math.min(page*perPage,data.length)} sur {data.length}</span>
          <div style={{ display: 'flex', gap: '4px' }}>
            {Array.from({ length: Math.min(totalPages, 5) }).map((_, i) => (
              <button key={i+1} onClick={() => setPage(i+1)} style={{
                width: '28px', height: '28px', borderRadius: '7px',
                border: `1px solid ${page === i+1 ? '#2563EB' : '#1E2D40'}`,
                background: page === i+1 ? '#2563EB' : '#161C26',
                color: page === i+1 ? '#fff' : '#64748B',
                fontSize: '11px', cursor: 'pointer', fontWeight: page === i+1 ? 700 : 400,
              }}>{i+1}</button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
