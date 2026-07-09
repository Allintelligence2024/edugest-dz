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
        eg: {
          bg:       '#070B14',
          surface:  '#0D1117',
          surface2: '#161C26',
          surface3: '#1A2236',
          border:   '#1E2D40',
          border2:  '#253347',
          text:     '#E2E8F0',
          text2:    '#CBD5E1',
          muted:    '#64748B',
          muted2:   '#475569',
          input:    '#161C26',
        },
        primary: {
          50:  '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
        accent: {
          DEFAULT: '#2563EB',
          hover:   '#1D4ED8',
          purple:  '#7C3AED',
        },
        green: {
          DEFAULT: '#10B981',
          hover:   '#059669',
        },
        orange: {
          DEFAULT: '#F59E0B',
        },
        red: {
          DEFAULT: '#EF4444',
        },
        teal: {
          DEFAULT: '#06B6D4',
        },
        pink: {
          DEFAULT: '#EC4899',
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
