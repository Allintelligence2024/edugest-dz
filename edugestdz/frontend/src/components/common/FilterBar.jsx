import React from 'react';

export default function FilterBar({ filters, values, onChange, onReset }) {
  return (
    <div className="flex flex-wrap items-center gap-3">
      {filters.map(filter => (
        <div key={filter.key}>
          {filter.type === 'select' ? (
            <select value={values[filter.key] ?? ''}
                    onChange={e => onChange({ [filter.key]: e.target.value })}
                    className="px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500 bg-white cursor-pointer">
              <option value="">{filter.placeholder || filter.label}</option>
              {filter.options.map(opt => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
          ) : filter.type === 'date' ? (
            <input type="date" value={values[filter.key] ?? ''}
                   onChange={e => onChange({ [filter.key]: e.target.value })}
                   className="px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500 bg-white" />
          ) : null}
        </div>
      ))}
      {Object.values(values).some(v => v) && (
        <button onClick={onReset}
                className="text-sm text-neutral-500 hover:text-primary-600 flex items-center gap-1 transition-colors">
          🔄 Réinitialiser
        </button>
      )}
    </div>
  );
}
