export default function Table({ headers, rows, empty = 'Aucune donnée', className = '' }) {
  return (
    <div className={`overflow-x-auto ${className}`}>
      <table className="w-full border-collapse">
        <thead>
          <tr className="border-b border-border">
            {headers.map((h, i) => (
              <th
                key={i}
                className="text-left text-[10px] font-bold text-muted uppercase tracking-wider px-4 py-3"
              >
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={headers.length} className="text-center text-muted text-xs py-12">
                {empty}
              </td>
            </tr>
          ) : rows.map((row, ri) => (
            <tr key={ri} className="border-b border-border/50 last:border-0 hover:bg-surface2/50 transition-colors">
              {row.cells.map((cell, ci) => (
                <td key={ci} className="px-4 py-3 text-xs text-text">
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
