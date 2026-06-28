import React, { useState, useRef } from 'react';

export default function SearchBar({ placeholder = 'Rechercher...', onSearch, delay = 400, className = '' }) {
  const [value, setValue] = useState('');
  const timerRef = useRef(null);

  const handleChange = (e) => {
    setValue(e.target.value);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => onSearch(e.target.value), delay);
  };

  const clear = () => { setValue(''); onSearch(''); };

  return (
    <div className={`relative flex-1 ${className}`}>
      <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400">🔍</span>
      <input type="text" value={value} onChange={handleChange} placeholder={placeholder}
             className="w-full pl-10 pr-9 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500 transition-colors bg-white" />
      {value && (
        <button onClick={clear}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 transition-colors">✕</button>
      )}
    </div>
  );
}
