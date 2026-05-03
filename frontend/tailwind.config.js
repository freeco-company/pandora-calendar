/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,ts,tsx,js,jsx}'],
  theme: {
    extend: {
      colors: {
        // legacy brand 棕色系 — 保留供既有 ring class / e2e 不破
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
          900: '#3a2816',
        },
        // 軟風日和風主調
        cream: { 50: '#FBF6EE', 100: '#F8EFE0', 200: '#F2E2C9', 300: '#E9D2AC', 400: '#DEC08F' },
        peach: { 50: '#FFF4ED', 100: '#FFE4D2', 200: '#FFCCA8', 300: '#FFAE7A', 400: '#FF8E55', 500: '#E97A45' },
        sakura: { 50: '#FFF5F7', 100: '#FCE4EA', 200: '#F8C7D2', 300: '#F0A0B2', 400: '#E07A91', 500: '#C86079' },
        lavender: { 50: '#F6F3FB', 100: '#E9E0F4', 200: '#D2C0E8', 300: '#B59AD7', 400: '#9678C2', 500: '#7C5DA8' },
        sage: { 50: '#F2F6F0', 100: '#E0EBDB', 200: '#C3D8B9', 300: '#9EBE92', 400: '#7BA46D', 500: '#5F8852' },
        phase: {
          menstrual: '#E07A91',
          follicular: '#FFCCA8',
          ovulation: '#9EBE92',
          luteal: '#B59AD7',
        },
      },
      // 對齊 pandora-meal — 全 app 字體統一 Zen Kaku Gothic New，無 Shippori 明體分歧
      // 紅線：禁加新字體；data/數字/英文/中文都用同一字體（Zen Kaku 在 latin / digit / kanji / hiragana 皆完整）
      fontFamily: {
        // display / pop / cute / rounded / sans / zen 全部同 alias 到 Zen Kaku
        // 既有 utility class 不破壞但視覺一致（meal 風）
        display: ['"Zen Kaku Gothic New"', '"Noto Sans TC"', '"PingFang TC"', '-apple-system', 'sans-serif'],
        zen: ['"Zen Kaku Gothic New"', '"Noto Sans TC"', '"PingFang TC"', '-apple-system', 'sans-serif'],
        maru: ['"Zen Maru Gothic"', '"Noto Sans TC"', 'sans-serif'],
        rounded: ['"Zen Kaku Gothic New"', '"Noto Sans TC"', '"PingFang TC"', '-apple-system', 'sans-serif'],
        pop: ['"Zen Kaku Gothic New"', '"Noto Sans TC"', '"PingFang TC"', '-apple-system', 'sans-serif'],
        cute: ['"Zen Kaku Gothic New"', '"Noto Sans TC"', '"PingFang TC"', '-apple-system', 'sans-serif'],
        sans: ['"Zen Kaku Gothic New"', '"Noto Sans TC"', '"PingFang TC"', '-apple-system', 'sans-serif'],
        // serif fallback（極少用，不影響整體視覺）
        serif: ['"Noto Serif TC"', 'serif'],
      },
      boxShadow: {
        soft: '0 6px 24px -8px rgba(159, 107, 62, 0.18)',
        // peach 色 shadow（療癒感，取代黑灰 shadow）
        'soft-md': '0 4px 14px -4px rgba(255, 192, 173, 0.30)',
        'soft-lg': '0 8px 24px -8px rgba(255, 192, 173, 0.40)',
        'soft-xl': '0 16px 40px -10px rgba(255, 192, 173, 0.45)',
        glow: '0 0 32px rgba(255, 205, 178, 0.55)',
        'glow-peach': '0 0 20px rgba(255, 169, 145, 0.30)',
        'glow-sakura': '0 0 20px rgba(240, 160, 178, 0.30)',
      },
      borderRadius: { '2.5xl': '1.25rem', '4xl': '2rem' },
      // Semantic radius scale — for new code, prefer tokens over raw rounded-xl/2xl/3xl
      // sm 8 / md 12 / lg 16 / xl 24 (= 既有 rounded-3xl)
      // Spacing scale 對齊 Tailwind 4px base：1/2/3/4/6/8/12 = 4/8/12/16/24/32/48 px
      backgroundImage: {
        'cream-gradient': 'linear-gradient(180deg, #FFF7EC 0%, #FBE9D8 60%, #F5DCC2 100%)',
        'peach-gradient': 'linear-gradient(135deg, #FFE4D2 0%, #FFCCA8 100%)',
        'sakura-gradient': 'linear-gradient(135deg, #FFE4EA 0%, #F8C7D2 100%)',
        'lavender-gradient': 'linear-gradient(135deg, #E9E0F4 0%, #D2C0E8 100%)',
        'dawn-gradient': 'linear-gradient(160deg, #FFF7EC 0%, #FFE4EA 50%, #E9E0F4 100%)',
      },
      keyframes: {
        floaty: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-6px)' } },
        sparkle: { '0%, 100%': { opacity: '0.6', transform: 'scale(1)' }, '50%': { opacity: '1', transform: 'scale(1.15)' } },
        pop: {
          '0%': { transform: 'scale(0.6)', opacity: '0' },
          '60%': { transform: 'scale(1.05)', opacity: '1' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
        slidein: {
          '0%': { transform: 'translateX(120%)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' },
        },
        fadein: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
        'pulse-slow': {
          '0%, 100%': { boxShadow: '0 0 0 0 rgba(255, 153, 119, 0.45)' },
          '50%': { boxShadow: '0 0 0 6px rgba(255, 153, 119, 0)' },
        },
        breathe: {
          '0%, 100%': { transform: 'scale(1)', opacity: '0.85' },
          '50%': { transform: 'scale(1.04)', opacity: '1' },
        },
        floatUp: {
          '0%': { transform: 'translateY(0) rotate(0)', opacity: '0' },
          '50%': { opacity: '0.6' },
          '100%': { transform: 'translateY(-100px) rotate(180deg)', opacity: '0' },
        },
        'gradient-shift': {
          '0%, 100%': { 'background-position': '0% 50%' },
          '50%': { 'background-position': '100% 50%' },
        },
        'spring-bounce': {
          '0%': { transform: 'scale(0.9)' },
          '60%': { transform: 'scale(1.05)' },
          '100%': { transform: 'scale(1)' },
        },
      },
      animation: {
        floaty: 'floaty 4s ease-in-out infinite',
        sparkle: 'sparkle 1.6s ease-in-out infinite',
        pop: 'pop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)',
        slidein: 'slidein 0.45s cubic-bezier(0.22, 1, 0.36, 1)',
        fadein: 'fadein 0.3s ease-out',
        'pulse-slow': 'pulse-slow 2.4s ease-in-out infinite',
        breathe: 'breathe 4s ease-in-out infinite',
        'float-up': 'floatUp 6s ease-in-out infinite',
        'gradient-shift': 'gradient-shift 12s ease infinite',
        'spring-bounce': 'spring-bounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)',
      },
    },
  },
  plugins: [],
}
