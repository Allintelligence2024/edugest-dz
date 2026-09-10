import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import fr from '../lang/fr.json';
import ar from '../lang/ar.json';
import en from '../lang/en.json';
import dz from '../lang/dz.json';

const LANGUAGES = { fr, ar, en, dz };
const RTL_LANGS  = ['ar', 'dz'];

export const LANG_META = {
  fr: { label: 'Français',  flag: '🇫🇷', dir: 'ltr' },
  ar: { label: 'العربية',  flag: '🇩🇿', dir: 'rtl' },
  en: { label: 'English',  flag: '🇬🇧', dir: 'ltr' },
  dz: { label: 'الدارجة', flag: '🇩🇿', dir: 'rtl' },
};

const I18nContext = createContext(null);

export function I18nProvider({ children }) {
  const [lang, setLang] = useState(() => {
    return localStorage.getItem('lang') || 'fr';
  });

  useEffect(() => {
    const dir = RTL_LANGS.includes(lang) ? 'rtl' : 'ltr';
    document.documentElement.dir  = dir;
    document.documentElement.lang = lang;
  }, [lang]);

  const t = useCallback((key, params = {}) => {
    const translations = LANGUAGES[lang] || LANGUAGES.fr;
    let text = translations[key] || LANGUAGES.fr[key] || key;
    Object.entries(params).forEach(([k, v]) => {
      text = text.replace(`{${k}}`, String(v));
    });
    return text;
  }, [lang]);

  const changeLang = useCallback((newLang) => {
    if (!LANGUAGES[newLang]) return;
    setLang(newLang);
    localStorage.setItem('lang', newLang);
    const dir = RTL_LANGS.includes(newLang) ? 'rtl' : 'ltr';
    document.documentElement.dir  = dir;
    document.documentElement.lang = newLang;
  }, []);

  const isRTL = RTL_LANGS.includes(lang);

  return (
    <I18nContext.Provider value={{ lang, t, changeLang, isRTL, LANG_META }}>
      {children}
    </I18nContext.Provider>
  );
}

export function useI18n() {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error('useI18n must be used within <I18nProvider>');
  return ctx;
}
