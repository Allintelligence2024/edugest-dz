import { describe, it, expect, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { I18nProvider, useI18n, LANG_META } from './I18nContext';
import i18n, { i18nReady } from '../i18n';
import arDict from '../lang/ar.json';

const wrapper = ({ children }) => <I18nProvider>{children}</I18nProvider>;

describe('façade useI18n (compat ascendante)', () => {
  afterEach(async () => {
    await i18n.changeLanguage('fr');
  });

  it('traduit, change de langue, ignore les langues inconnues', async () => {
    await i18nReady;
    const { result } = renderHook(() => useI18n(), { wrapper });
    await act(async () => {
      await i18n.changeLanguage('fr');
    });

    expect(result.current.lang).toBe('fr');
    expect(result.current.isRTL).toBe(false);
    expect(result.current.t('save')).toBe('Enregistrer');
    expect(result.current.LANG_META).toBe(LANG_META);
    expect(typeof result.current.formatNumber).toBe('function');
    expect(typeof result.current.formatDate).toBe('function');

    await act(async () => {
      await result.current.changeLang('ar');
    });
    expect(result.current.lang).toBe('ar');
    expect(result.current.isRTL).toBe(true);
    expect(document.documentElement.dir).toBe('rtl');
    expect(result.current.t('save')).toBe(arDict.save);

    // Langue inconnue : ignorée (comportement historique).
    await act(async () => {
      await result.current.changeLang('xx');
    });
    expect(result.current.lang).toBe('ar');
  });

  it('lève une erreur hors provider', () => {
    expect(() => renderHook(() => useI18n())).toThrow('useI18n must be used within');
  });
});
