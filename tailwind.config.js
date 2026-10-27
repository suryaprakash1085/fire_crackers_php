/** Tailwind config — colours come from CSS variables that the Admin panel (App Customization) controls. */
const withVar = (name) => `rgb(var(--c-${name}-rgb) / <alpha-value>)`;
module.exports = {
  content: ['./app/**/*.php', './public/assets/js/**/*.js', './public/index.php'],
  theme: {
    extend: {
      colors: {
        primary: withVar('primary'),
        secondary: withVar('secondary'),
        accent: withVar('accent'),
        ink: withVar('text'),
      },
      fontFamily: {
        sans: ['Inter', 'Noto Sans Tamil', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Poppins', 'Noto Sans Tamil', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        soft: '0 1px 2px rgb(16 24 40 / .04), 0 6px 20px rgb(16 24 40 / .06)',
        lift: '0 14px 40px rgb(16 24 40 / .12)',
      },
    },
  },
  plugins: [],
};
