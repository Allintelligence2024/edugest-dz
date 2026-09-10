import { describe, it, expect, beforeAll, afterEach } from 'vitest';
import i18n, { i18nReady, baseLang, formatDate, formatNumber, LOCALES } from './i18n';
import arDict from './lang/ar.json';

beforeAll(async () => {
  await i18nReady;
});

afterEach(async () => {
  // Restaurer l'arabe si un test l'a retiré, revenir au français.
  i18n.addResourceBundle('ar', 'translation', arDict, true, true);
  await i18n.changeLanguage('fr');
});

describe('socle i18next (5.6-phase-1)', () => {
  it("démarre en français quand rien n'est stocké", () => {
    expect(baseLang(i18n.language)).toBe('fr');
    expect(document.documentElement.dir).toBe('ltr');
    expect(document.documentElement.lang).toBe('fr');
  });

  it('traduit et interpole {{var}}', () => {
    expect(i18n.t('save')).toBe('Enregistrer');
    expect(i18n.t('welcome', { name: 'Amine' })).toBe('Bonjour, Amine 👋');
    expect(i18n.t('showing', { from: 1, to: 20, total: 42 })).toBe('Affichage 1-20 sur 42');
  });

  it('retombe sur le français quand la langue manque', async () => {
    i18n.removeResourceBundle('ar', 'translation');
    await i18n.changeLanguage('ar');
    expect(i18n.t('save')).toBe('Enregistrer');
  });

  it('renvoie la clé quand elle manque partout', () => {
    expect(i18n.t('cle_inexistante_xyz')).toBe('cle_inexistante_xyz');
  });

  it('bascule RTL en arabe et revient en LTR', async () => {
    await i18n.changeLanguage('ar');
    expect(document.documentElement.dir).toBe('rtl');
    expect(document.documentElement.lang).toBe('ar');
    await i18n.changeLanguage('dz');
    expect(document.documentElement.dir).toBe('rtl');
    await i18n.changeLanguage('fr');
    expect(document.documentElement.dir).toBe('ltr');
  });

  it('pluriels français (0/1 → singulier)', () => {
    i18n.addResourceBundle(
      'fr',
      'translation',
      { test_item_one: '{{count}} élément', test_item_other: '{{count}} éléments' },
      true,
      true
    );
    expect(i18n.t('test_item', { count: 1 })).toBe('1 élément');
    expect(i18n.t('test_item', { count: 2 })).toBe('2 éléments');
    expect(i18n.t('test_item', { count: 0 })).toBe('0 élément');
  });

  it('pluriels darija = règles arabes (CLDR)', () => {
    i18n.addResourceBundle(
      'dz',
      'translation',
      {
        test_item_zero: 'zéro',
        test_item_one: 'un',
        test_item_two: 'deux',
        test_item_few: 'quelques',
        test_item_many: 'beaucoup',
        test_item_other: 'autres',
      },
      true,
      true
    );
    expect(i18n.t('test_item', { count: 0, lng: 'dz' })).toBe('zéro');
    expect(i18n.t('test_item', { count: 1, lng: 'dz' })).toBe('un');
    expect(i18n.t('test_item', { count: 2, lng: 'dz' })).toBe('deux');
    expect(i18n.t('test_item', { count: 5, lng: 'dz' })).toBe('quelques');
    expect(i18n.t('test_item', { count: 15, lng: 'dz' })).toBe('beaucoup');
    expect(i18n.t('test_item', { count: 100, lng: 'dz' })).toBe('autres');
  });

  it('formate nombres et dates par langue', () => {
    const frNum = formatNumber(1234.5, 'fr');
    expect(frNum).toContain('234');
    expect(frNum).toContain(',');
    expect(formatNumber(1234.5, 'en')).toBe('1,234.5');
    expect(LOCALES.dz).toContain('latn'); // darija : chiffres latins
    expect(formatDate(new Date(2026, 8, 10), 'dz')).toContain('2026');
    expect(typeof formatDate(new Date(), 'ar')).toBe('string');
  });

  it('baseLang sécurise les entrées', () => {
    expect(baseLang('ar-DZ')).toBe('ar');
    expect(baseLang(undefined)).toBe('fr');
    expect(baseLang(null)).toBe('fr');
  });
});
