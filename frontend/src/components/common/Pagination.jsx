import React from 'react';

export default function Pagination({ meta, onChange }) {
  if (!meta || meta.last_page <= 1) return null;
  const { current_page, last_page, total, from, to } = meta;

  const pages = [];
  const delta = 2;
  for (let i = Math.max(1, current_page - delta); i <= Math.min(last_page, current_page + delta); i++) {
    pages.push(i);
  }

  return (
    <div className="flex flex-wrap items-center justify-between gap-4 mt-4">
      <p className="text-sm text-neutral-500">
        <span className="font-medium text-neutral-800">{from}–{to}</span> sur{' '}
        <span className="font-medium text-neutral-800">{total}</span> résultats
      </p>
      <div className="flex items-center gap-1">
        <button onClick={() => onChange(current_page - 1)} disabled={current_page === 1}
                className="px-3 py-2 text-sm rounded-lg border border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors font-medium">◀</button>
        {pages[0] > 1 && (
          <>
            <button onClick={() => onChange(1)}
                    className="px-3 py-2 text-sm rounded-lg border border-neutral-200 hover:bg-neutral-50 transition-colors">1</button>
            {pages[0] > 2 && <span className="text-neutral-400 px-1">…</span>}
          </>
        )}
        {pages.map(p => (
          <button key={p} onClick={() => onChange(p)}
                  className={`px-3 py-2 text-sm rounded-lg border font-medium transition-colors
                    ${p === current_page ? 'bg-primary-600 text-white border-primary-600' : 'border-neutral-200 hover:bg-neutral-50'}`}>{p}</button>
        ))}
        {pages[pages.length - 1] < last_page && (
          <>
            {pages[pages.length - 1] < last_page - 1 && <span className="text-neutral-400 px-1">…</span>}
            <button onClick={() => onChange(last_page)}
                    className="px-3 py-2 text-sm rounded-lg border border-neutral-200 hover:bg-neutral-50 transition-colors">{last_page}</button>
          </>
        )}
        <button onClick={() => onChange(current_page + 1)} disabled={current_page === last_page}
                className="px-3 py-2 text-sm rounded-lg border border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors font-medium">▶</button>
      </div>
    </div>
  );
}
