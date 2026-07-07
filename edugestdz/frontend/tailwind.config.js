/** @type {import('tailwindcss').Config} */
export default {
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
          border:   '#1E2D40',
          text:     '#E2E8F0',
          muted:    '#64748B',
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
      },
      animation: {
        'fade-in': 'fadeIn 0.2s ease forwards',
        'pulse-slow': 'pulse 2s infinite',
      },
    },
  },
  plugins: [],
}
