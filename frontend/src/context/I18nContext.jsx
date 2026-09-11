import { createContext, useContext, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { baseLang, RTL_LANGS, formatDate, formatNumber } from '../i18n';

// Façade ascendante : l'API historique ({ lang, t, changeLang, isRTL,
// LANG_META }) est inchangée, seul le moteur passe sur i18next.
// Note : t(key, { count }) déclenche désormais les pluriels CLDR.
const I18nContext = createContext(null);

export const LANG_META = {
  fr: { label: 'Français',  flag: 'FR', dir: 'ltr' },
  ar: { label: 'العربية',  flag: 'AR', dir: 'rtl' },
  en: { label: 'English',  flag: 'EN', dir: 'ltr' },
  dz: { label: 'الدارجة', flag: 'DZ', dir: 'rtl' },
};

const SUPPORTED = Object.keys(LANG_META);

export function I18nProvider({ children }) {
  const { t: xt, i18n } = useTranslation();
  const lang = baseLang(i18n.language);

  const t = useCallback((key, params = {}) => xt(key, params), [xt]);

  const changeLang = useCallback(
    (newLang) => {
      if (!SUPPORTED.includes(newLang)) return Promise.resolve();
      return i18n.changeLanguage(newLang);
    },
    [i18n]
  );

  const value = useMemo(
    () => ({ lang, t, changeLang, isRTL: RTL_LANGS.includes(lang), LANG_META, formatDate, formatNumber }),
    [lang, t, changeLang]
  );

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error('useI18n must be used within <I18nProvider>');
  return ctx;
}
