import React, { createContext, useContext, useState, useCallback } from 'react';
import fr from '../lang/fr.json';
import ar from '../lang/ar.json';
import dz from '../lang/dz.json';

const LANGUAGES = { fr, ar, dz };
const RTL_LANGS = ['ar', 'dz'];
const I18nContext = createContext(null);

export function I18nProvider({ children }) {
  const [lang, setLang] = useState(() => {
    return localStorage.getItem('lang') || 'fr';
  });

  const t = useCallback((key, params = {}) => {
    const translations = LANGUAGES[lang] || LANGUAGES.fr;
    let text = translations[key] || key;
    if (params) {
      Object.entries(params).forEach(([k, v]) => {
        text = text.replace(`{${k}}`, v);
      });
    }
    return text;
  }, [lang]);

  const changeLang = useCallback((newLang) => {
    if (LANGUAGES[newLang]) {
      setLang(newLang);
      localStorage.setItem('lang', newLang);
      document.documentElement.dir = RTL_LANGS.includes(newLang) ? 'rtl' : 'ltr';
      document.documentElement.lang = newLang;
    }
  }, []);

  return (
    <I18nContext.Provider value={{ lang, t, changeLang }}>
      {children}
    </I18nContext.Provider>
  );
}

export function useI18n() {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error('useI18n must be used within <I18nProvider>');
  return ctx;
}
