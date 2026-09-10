/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ['selector', '[data-theme="dark"]'],
  content: ['./index.html', './src/**/*.{js,jsx,ts,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },
      colors: {
        bg:       'var(--bg)',
        surface:  'var(--surface)',
        surface2: 'var(--surface2)',
        surface3: 'var(--surface3)',
        border:   'var(--border)',
        border2:  'var(--border2)',
        text:     'var(--text)',
        text2:    'var(--text2)',
        muted:    'var(--muted)',
        muted2:   'var(--muted2)',
        input:    'var(--input-bg)',
        primary: {
          DEFAULT: 'var(--accent)',
          hover:   'var(--accent-h)',
          50:  '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: 'var(--accent-light)',
          400: 'var(--blue-light)',
          500: 'var(--accent)',
          600: 'var(--accent)',
          700: 'var(--accent-h)',
          800: '#1e40af',
          900: '#1e3a8a',
        },
        accent: {
          DEFAULT: 'var(--accent)',
          hover:   'var(--accent-h)',
          purple:  'var(--accent2)',
        },
        green: {
          DEFAULT: 'var(--green)',
          hover:   'var(--green-h)',
        },
        orange: {
          DEFAULT: 'var(--orange)',
        },
        red: {
          DEFAULT: 'var(--red)',
        },
        teal: {
          DEFAULT: 'var(--teal)',
        },
        pink: {
          DEFAULT: 'var(--pink)',
        },
        eg: {
          bg:       'var(--bg)',
          surface:  'var(--surface)',
          surface2: 'var(--surface2)',
          surface3: 'var(--surface3)',
          border:   'var(--border)',
          border2:  'var(--border2)',
          text:     'var(--text)',
          text2:    'var(--text2)',
          muted:    'var(--muted)',
          muted2:   'var(--muted2)',
          input:    'var(--input-bg)',
        },
      },
      borderRadius: {
        'xl':  '12px',
        '2xl': '16px',
        '3xl': '24px',
      },
      boxShadow: {
        'glow-blue':   '0 0 20px rgba(37,99,235,0.3)',
        'glow-green':  '0 0 20px rgba(16,185,129,0.3)',
        'card':        '0 1px 3px rgba(0,0,0,0.4)',
        'card-hover':  '0 8px 24px rgba(0,0,0,0.5)',
        'eg':          '0 4px 24px rgba(0,0,0,0.4)',
        'eg-sm':       '0 2px 8px rgba(0,0,0,0.3)',
      },
      animation: {
        'fade-in': 'fadeIn 0.2s ease forwards',
        'pulse-slow': 'pulse 2s infinite',
      },
    },
  },
  plugins: [],
}
