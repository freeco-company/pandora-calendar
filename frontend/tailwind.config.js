/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,ts,tsx,js,jsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#fbf6ee',
          100: '#f5e8d4',
          200: '#ecd2a8',
          300: '#e1b577',
          400: '#d4a574',
          500: '#c89368',
          600: '#9F6B3E',
          700: '#7d5530',
          800: '#5b3e23',
          900: '#3a281600',
        },
        phase: {
          menstrual: '#e85a71',
          follicular: '#ffd166',
          ovulation: '#06d6a0',
          luteal: '#9d8df1',
        },
      },
      fontFamily: {
        sans: ['"Noto Sans TC"', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
