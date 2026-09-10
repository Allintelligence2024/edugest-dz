import React from 'react';

export default function DataTable({ columns, data, isLoading, emptyIcon = '📋', emptyMessage = 'Aucune donnée',
                                     onRowClick, selectedId, rowClassName = '' }) {
  if (isLoading) {
    return (
      <div className="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div className="flex items-center justify-center py-20">
          <div className="flex flex-col items-center gap-3">
            <div className="w-10 h-10 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin" />
            <p className="text-sm text-neutral-400">Chargement...</p>
          </div>
        </div>
      </div>
    );
  }

  if (!data?.length) {
    return (
      <div className="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div className="flex flex-col items-center justify-center py-16 gap-3">
          <span className="text-5xl">{emptyIcon}</span>
          <p className="text-neutral-500 font-medium text-sm">{emptyMessage}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-2xl border border-neutral-200 overflow-hidden shadow-card">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              {columns.map(col => (
                <th key={col.key} className={`px-4 py-3 text-left text-xs font-bold text-neutral-500 uppercase tracking-wider whitespace-nowrap ${col.className ?? ''}`}>
                  {col.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {data.map((row, i) => (
              <tr key={row.id ?? i} onClick={() => onRowClick?.(row)}
                  className={`transition-colors ${onRowClick ? 'cursor-pointer' : ''}
                    ${selectedId === row.id ? 'bg-primary-50 border-l-4 border-l-primary-500' : 'hover:bg-neutral-50'}
                    ${rowClassName}`}>
                {columns.map(col => (
                  <td key={col.key} className={`px-4 py-3 text-sm ${col.tdClassName ?? ''}`}>
                    {col.render ? col.render(row[col.key], row) : row[col.key]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
