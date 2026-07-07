import React, { createContext, useContext, useState, useEffect } from 'react';

const ThemeContext = createContext(null);

const THEMES = {
  dark: {
    '--eg-bg':       '#070B14',
    '--eg-surface':  '#0D1117',
    '--eg-surface2': '#161C26',
    '--eg-border':   '#1E2D40',
    '--eg-text':     '#E2E8F0',
    '--eg-text2':    '#94A3B8',
    '--eg-muted':    '#64748B',
    '--eg-input-bg': '#161C26',
    '--eg-card-bg':  '#0D1117',
    '--eg-nav-bg':   '#0D1117',
    '--eg-topbar-bg':'#0D1117',
    '--eg-hover':    '#161C2688',
    '--eg-shadow':   '0 4px 24px rgba(0,0,0,0.5)',
  },
  light: {
    '--eg-bg':       '#F8FAFC',
    '--eg-surface':  '#FFFFFF',
    '--eg-surface2': '#F1F5F9',
    '--eg-border':   '#E2E8F0',
    '--eg-text':     '#0F172A',
    '--eg-text2':    '#475569',
    '--eg-muted':    '#94A3B8',
    '--eg-input-bg': '#F8FAFC',
    '--eg-card-bg':  '#FFFFFF',
    '--eg-nav-bg':   '#FFFFFF',
    '--eg-topbar-bg':'#FFFFFF',
    '--eg-hover':    '#F1F5F988',
    '--eg-shadow':   '0 2px 12px rgba(0,0,0,0.08)',
  },
};

function applyTheme(theme) {
  const root = document.documentElement;
  const tokens = THEMES[theme] || THEMES.dark;
  Object.entries(tokens).forEach(([prop, val]) => {
    root.style.setProperty(prop, val);
  });
  root.setAttribute('data-theme', theme);
  if (theme === 'dark') {
    root.classList.add('dark');
  } else {
    root.classList.remove('dark');
  }
}

export function ThemeProvider({ children }) {
  const [theme, setTheme] = useState(() => {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark' || saved === 'light') return saved;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  });

  useEffect(() => {
    applyTheme(theme);
    localStorage.setItem('theme', theme);
  }, [theme]);

  useEffect(() => {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = (e) => {
      if (!localStorage.getItem('theme')) {
        setTheme(e.matches ? 'dark' : 'light');
      }
    };
    mq.addEventListener('change', handler);
    return () => mq.removeEventListener('change', handler);
  }, []);

  const toggleTheme = () => setTheme(t => t === 'dark' ? 'light' : 'dark');
  const isDark = theme === 'dark';

  return (
    <ThemeContext.Provider value={{ theme, toggleTheme, isDark, setTheme }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error('useTheme must be used within <ThemeProvider>');
  return ctx;
}
