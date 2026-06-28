import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useI18n } from '@context/I18nContext';
import api from '@api/axiosInstance';

export default function Header({ user }) {
  const { lang, changeLang } = useI18n();
  const [nonLu, setNonLu] = useState(0);
  const navigate = useNavigate();

  useEffect(() => {
    const fetchNonLu = async () => {
      try {
        const res = await api.get('/messages/conversations?per_page=1');
        setNonLu(res.meta?.non_lu || 0);
      } catch { /* ignore */ }
    };
    fetchNonLu();
    const interval = setInterval(fetchNonLu, 30000);
    return () => clearInterval(interval);
  }, []);

  return (
    <header className="bg-white border-b border-neutral-200 px-6 py-3 flex items-center justify-between" dir={lang === 'ar' || lang === 'dz' ? 'rtl' : 'ltr'}>
      <div className="flex items-center gap-3">
        <h1 className="text-lg font-bold text-neutral-800">EduGest DZ</h1>
      </div>
      <div className="flex items-center gap-4">
        <button onClick={() => navigate('/messages')} className="relative p-2 rounded-lg hover:bg-neutral-100 text-neutral-500">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
          {nonLu > 0 && <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">{nonLu > 9 ? '9+' : nonLu}</span>}
        </button>
        <select value={lang} onChange={e => changeLang(e.target.value)} className="text-xs border border-neutral-200 rounded-lg px-2 py-1 bg-white">
          <option value="fr">🇫🇷 Français</option>
          <option value="ar">🇩🇿 العربية</option>
          <option value="dz">🇩🇿 دارجة</option>
        </select>
        <span className="text-sm text-neutral-500">{user?.nom} {user?.prenom}</span>
        <div className="w-9 h-9 bg-primary-100 text-primary-700 rounded-full flex items-center justify-center text-sm font-bold">
          {user?.nom?.[0]}{user?.prenom?.[0]}
        </div>
      </div>
    </header>
  );
}
