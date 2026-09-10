// Socle i18next — Sprint 5.6, phase 1.
//
// Remplace le dictionnaire artisanal (I18nContext historique) par i18next :
// pluriels CLDR, interpolation {{var}}, repli fr, RTL. Les composants ne
// changent pas : `useI18n()` reste la façade (voir context/I18nContext.jsx).
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import fr from './lang/fr.json';
import ar from './lang/ar.json';
import en from './lang/en.json';
import dz from './lang/dz.json';

export const RTL_LANGS = ['ar', 'dz'];
export const FALLBACK_LANG = 'fr';

// Locales Intl par langue. Darija : chiffres latins (usage algérien),
// pas de chiffres arabes-indic — d'où le -u-nu-latn explicite.
export const LOCALES = {
  fr: 'fr-FR',
  ar: 'ar-DZ',
  en: 'en-US',
  dz: 'ar-DZ-u-nu-latn',
};

export function baseLang(lng) {
  return String(lng || FALLBACK_LANG).split('-')[0];
}

export function applyDirection(lng) {
  if (typeof document === 'undefined') return;
  const base = baseLang(lng);
  document.documentElement.dir = RTL_LANGS.includes(base) ? 'rtl' : 'ltr';
  document.documentElement.lang = base;
}

function storedLang() {
  try {
    if (typeof window === 'undefined') return FALLBACK_LANG;
    return window.localStorage?.getItem('lang') || FALLBACK_LANG;
  } catch {
    return FALLBACK_LANG; // navigation privée / localStorage indisponible
  }
}

function persistLang(lng) {
  try {
    window.localStorage?.setItem('lang', baseLang(lng));
  } catch {
    // Stockage indisponible : la langue reste en mémoire pour la session.
  }
}

// Le darija ('dz') suit les règles plurielles de l'arabe (CLDR). i18next ≥ 25
// a supprimé `addRule` : les pluriels passent par `Intl.PluralRules`, où 'dz'
// désigne… le dzongkha (une seule catégorie « other », zéro/one/two/few/many
// perdus). On délègue donc 'dz' vers la règle compilée de l'arabe.
function wireDzPlurals() {
  try {
    const resolver = i18n.services?.pluralResolver;
    if (!resolver || typeof resolver.getRule !== 'function' || resolver.__dzWired) return;
    const getRuleOrig = resolver.getRule.bind(resolver);
    resolver.getRule = (code, options) =>
      typeof code === 'string' && code.replace('_', '-').split('-')[0] === 'dz'
        ? getRuleOrig('ar', options)
        : getRuleOrig(code, options);
    resolver.__dzWired = true;
  } catch {
    // Pluriels dz par défaut — le test i18n.test.js garde le câblage nominal.
  }
}

i18n.use(initReactI18next);

// `i18nReady` ne se résout qu'APRÈS le câblage : l'attendre garantit un
// socle complet (pluriels dz, direction). L'événement 'initialized' est
// l'API documentée, contrairement à l'ordre callback/promesse.
export const i18nReady = new Promise((resolve) => {
  i18n.on('initialized', () => {
    wireDzPlurals();
    applyDirection(i18n.language);
    resolve();
  });
});

i18n.init({
  resources: {
    fr: { translation: fr },
    ar: { translation: ar },
    en: { translation: en },
    dz: { translation: dz },
  },
  lng: storedLang(),
  fallbackLng: FALLBACK_LANG,
  interpolation: { escapeValue: false }, // React échappe déjà
  returnEmptyString: false,
  react: { useSuspense: false }, // pas de <Suspense> autour d'App
});

applyDirection(storedLang()); // direction immédiate, avant la fin de l'init

i18n.on('languageChanged', (lng) => {
  applyDirection(lng);
  persistLang(lng);
});

export function formatNumber(value, lang = i18n.language) {
  return new Intl.NumberFormat(LOCALES[baseLang(lang)] || LOCALES.fr).format(value);
}

export function formatDate(value, lang = i18n.language, options) {
  return new Intl.DateTimeFormat(LOCALES[baseLang(lang)] || LOCALES.fr, options).format(value);
}

export default i18n;
