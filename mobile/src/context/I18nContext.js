import React, { createContext, useContext, useState, useCallback, useMemo } from 'react';
import * as SecureStore from 'expo-secure-store';
import api, { TOKEN_KEY, REFRESH_KEY } from '../api/axios';
import { authApi } from '../api/endpoints';

const I18nContext = createContext(null);

const LANGUAGES = {
  fr: { label: 'Français', flag: '🇫🇷', rtl: false },
  ar: { label: 'العربية', flag: '🇩🇿', rtl: true },
  dz: { label: 'دارجة', flag: '🇩🇿', rtl: true },
};

export function I18nProvider({ children }) {
  const [lang, setLang] = useState('fr');
  const [isRTL, setIsRTL] = useState(false);

  const changeLang = useCallback((code) => {
    if (LANGUAGES[code]) {
      setLang(code);
      setIsRTL(LANGUAGES[code].rtl);
    }
  }, []);

  const t = useCallback((key, params = {}) => {
    const translations = lang === 'ar'
      ? require('../lang/ar').default
      : lang === 'dz'
        ? require('../lang/dz').default
        : require('../lang/fr').default;
    let text = translations[key] || key;
    Object.entries(params).forEach(([k, v]) => {
      text = text.replace(`{${k}}`, v);
    });
    return text;
  }, [lang]);

  const value = useMemo(() => ({ lang, isRTL, changeLang, t, languages: LANGUAGES }), [lang, isRTL, changeLang, t]);

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error('useI18n must be used within I18nProvider');
  return ctx;
}

export { LANGUAGES };
