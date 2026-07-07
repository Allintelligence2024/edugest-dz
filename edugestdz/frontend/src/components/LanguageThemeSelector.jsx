import { useState } from 'react';
import { useI18n, LANG_META } from '@context/I18nContext';
import { useTheme } from '@context/ThemeContext';

export default function LanguageThemeSelector({ compact = false }) {
  const { lang, changeLang, t } = useI18n();
  const { isDark, toggleTheme }  = useTheme();
  const [showLangMenu, setShowLangMenu] = useState(false);

  const currentLang = LANG_META[lang] || LANG_META.fr;

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '6px', position: 'relative' }}>

      <button
        onClick={toggleTheme}
        title={isDark ? t('theme_light') : t('theme_dark')}
        style={{
          width: '36px', height: '36px',
          background: 'var(--eg-surface2)',
          border: '1px solid var(--eg-border)',
          borderRadius: '9px',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          cursor: 'pointer', fontSize: '16px',
          transition: 'all 0.15s',
          color: 'var(--eg-text)',
        }}
        onMouseEnter={e => e.currentTarget.style.borderColor = 'var(--eg-blue)'}
        onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--eg-border)'}
      >
        {isDark ? '☀️' : '🌙'}
      </button>

      <div style={{ position: 'relative' }}>
        <button
          onClick={() => setShowLangMenu(!showLangMenu)}
          title={t('lang_select')}
          style={{
            height: '36px',
            background: 'var(--eg-surface2)',
            border: '1px solid var(--eg-border)',
            borderRadius: '9px',
            display: 'flex', alignItems: 'center',
            gap: '6px', padding: '0 10px',
            cursor: 'pointer', fontSize: '12px',
            fontWeight: 700,
            color: 'var(--eg-text)',
            fontFamily: 'Inter, sans-serif',
            transition: 'all 0.15s',
          }}
          onMouseEnter={e => e.currentTarget.style.borderColor = 'var(--eg-blue)'}
          onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--eg-border)'}
        >
          <span>{currentLang.flag}</span>
          {!compact && <span>{currentLang.label}</span>}
          <span style={{ fontSize: '10px', color: 'var(--eg-muted)' }}>▾</span>
        </button>

        {showLangMenu && (
          <>
            <div
              style={{ position: 'fixed', inset: 0, zIndex: 99 }}
              onClick={() => setShowLangMenu(false)}
            />
            <div style={{
              position: 'absolute',
              top: '42px',
              right: 0,
              background: 'var(--eg-surface)',
              border: '1px solid var(--eg-border)',
              borderRadius: '12px',
              boxShadow: 'var(--eg-shadow)',
              zIndex: 100,
              overflow: 'hidden',
              minWidth: '160px',
            }}>
              {Object.entries(LANG_META).map(([code, meta]) => (
                <button
                  key={code}
                  onClick={() => { changeLang(code); setShowLangMenu(false); }}
                  style={{
                    width: '100%',
                    display: 'flex', alignItems: 'center', gap: '10px',
                    padding: '10px 14px',
                    background: lang === code ? 'var(--eg-blue)18' : 'transparent',
                    border: 'none',
                    borderBottom: '1px solid var(--eg-border)',
                    cursor: 'pointer',
                    fontSize: '12px', fontWeight: lang === code ? 700 : 500,
                    color: lang === code ? 'var(--eg-blue-light)' : 'var(--eg-text)',
                    textAlign: 'left',
                    fontFamily: 'Inter, sans-serif',
                    direction: meta.dir,
                    transition: 'background 0.1s',
                  }}
                  onMouseEnter={e => { if (lang !== code) e.currentTarget.style.background = 'var(--eg-hover)'; }}
                  onMouseLeave={e => { if (lang !== code) e.currentTarget.style.background = 'transparent'; }}
                >
                  <span style={{ fontSize: '18px' }}>{meta.flag}</span>
                  <span>{meta.label}</span>
                  {lang === code && (
                    <span style={{ marginLeft: 'auto', fontSize: '12px', color: 'var(--eg-green)' }}>✓</span>
                  )}
                </button>
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
